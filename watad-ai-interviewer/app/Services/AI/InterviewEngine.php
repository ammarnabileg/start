<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\InterviewStatus;
use App\Jobs\FinalizeInterview;
use App\Models\Interview;
use App\Services\AI\Prompts\PromptLibrary;
use Illuminate\Support\Facades\Log;

/**
 * Server-side state machine that drives the live AI interviewer turn-by-turn.
 * Owns coverage tracking, adaptive branching limits, time/length budgets, and the hand-off to
 * the async finalization pipeline. Mode-agnostic — always operates on text turns; voice/video
 * differ only at the I/O edge. See docs/07-interview-engine-logic.md.
 */
final class InterviewEngine
{
    public function __construct(
        private readonly LlmManager $llm,
        private readonly PromptLibrary $prompts,
    ) {}

    /** Begin the interview: agent introduces itself, explains the role, asks the first question. */
    public function start(Interview $interview): array
    {
        $this->hydrate($interview);

        $interview->state       = $this->initState($interview);
        $interview->status      = InterviewStatus::InProgress;
        $interview->started_at  = now();
        $interview->save();

        $result = $this->llm->stream('conversation', [
            'system'   => $this->prompts->interviewerSystemBlocks($interview),
            'tools'    => $this->prompts->interviewerTools(),
            'thinking' => $this->prompts->interviewerThinking(),
            'messages' => [[
                'role'    => 'user',
                'content' => '[The candidate has joined. Introduce yourself and Watad, briefly explain the role, then ask your first question.]',
            ]],
        ], fn ($delta) => null);

        $this->recordUsage($interview, $result);
        $this->emitEvent($interview, 'introduction', 'info', 'Interview started', 0);

        return $this->applyAgentResult($interview, $result, isIntro: true);
    }

    /**
     * Handle one candidate answer → next agent question (or conclusion).
     *
     * @param  callable|null  $onDelta  receives streamed agent text deltas (for the live channel)
     */
    public function handleTurn(Interview $interview, string $candidateText, ?callable $onDelta = null): array
    {
        $this->hydrate($interview);
        $state = $interview->state ?: $this->initState($interview);

        $this->persistMessage($interview, 'candidate', $candidateText);

        $messages = $this->buildTranscript($interview);
        if ($this->nearBudget($interview, $state)) {
            $messages[] = $this->prompts->wrapUpNudge();
        }

        $result = $this->llm->stream('conversation', [
            'system'   => $this->prompts->interviewerSystemBlocks($interview),
            'tools'    => $this->prompts->interviewerTools(),
            'thinking' => $this->prompts->interviewerThinking(),
            'messages' => $messages,
        ], $onDelta ?? fn ($delta) => null);

        $this->recordUsage($interview, $result);

        if ($result->wasRefused()) {
            Log::warning('Interviewer LLM refusal', ['interview' => $interview->id]);
            return $this->askFallback($interview, $state);
        }

        return $this->applyAgentResult($interview, $result);
    }

    /** Candidate- or system-initiated finish. */
    public function complete(Interview $interview): array
    {
        $this->hydrate($interview);
        return $this->conclude($interview, $this->defaultClosing($interview));
    }

    /* ------------------------------------------------------------------ */

    private function applyAgentResult(Interview $interview, LlmResult $result, bool $isIntro = false): array
    {
        $state = $interview->state ?: $this->initState($interview);

        foreach ($result->toolCalls as $call) {
            if ($call['name'] === 'record_observation') {
                $this->applyObservation($interview, $state, $call['input']);
            }
        }

        if ($conclude = $result->toolCall('conclude_interview')) {
            $interview->state = $state;
            $interview->save();
            return $this->conclude($interview, $conclude['input']['closing_message'] ?? $this->defaultClosing($interview));
        }

        $ask          = $result->toolCall('ask_question');
        $questionText = $ask['input']['text'] ?? ($result->text !== '' ? $result->text : null);

        // On the intro turn, the model may emit intro text separately from the ask_question tool.
        if ($isIntro && $ask && $result->text !== '' && ! str_contains((string) $questionText, $result->text)) {
            $questionText = trim($result->text."\n\n".$questionText);
        }

        if (! $questionText) {
            return $this->askFallback($interview, $state);
        }

        $message = $this->persistMessage($interview, 'agent', $questionText, [
            'competency'   => $ask['input']['targets_competency'] ?? null,
            'thread_key'   => $ask['input']['thread_key'] ?? null,
            'is_follow_up' => (bool) ($ask['input']['is_follow_up'] ?? false),
        ]);

        $state['asked_count'] = ($state['asked_count'] ?? 0) + 1;
        $state['phase']       = $this->phaseFor($state);
        $interview->state          = $state;
        $interview->question_count = $state['asked_count'];
        $interview->save();

        return [
            'status' => 'in_progress',
            'agent'  => [
                'text'         => $questionText,
                'seq'          => $message->seq,
                'is_follow_up' => $message->is_follow_up,
            ],
            'progress' => $this->progress($interview, $state),
        ];
    }

    private function conclude(Interview $interview, string $closing): array
    {
        $message = $this->persistMessage($interview, 'agent', $closing);

        $interview->status           = InterviewStatus::Processing;
        $interview->completed_at      = now();
        $interview->duration_seconds = $interview->started_at
            ? now()->diffInSeconds($interview->started_at)
            : null;
        $interview->save();

        $this->emitEvent($interview, 'wrap_up', 'info', 'Interview concluded', $this->msOffset($interview));

        FinalizeInterview::dispatch($interview->id);

        return ['status' => 'concluded', 'agent' => ['text' => $closing, 'seq' => $message->seq]];
    }

    private function applyObservation(Interview $interview, array &$state, array $input): void
    {
        $competency = $input['competency'] ?? null;
        $signal     = $input['signal'] ?? 'adequate';
        $delta      = $input['confidence_delta'] ?? 'flat';
        $flag       = $input['possible_red_flag'] ?? null;

        if ($competency) {
            $increment = match ($signal) {
                'strong'   => 0.35,
                'adequate' => 0.20,
                default    => 0.10,
            };
            $state['coverage'][$competency] = min(1.0, ($state['coverage'][$competency] ?? 0) + $increment);
        }

        $state['observations'] = ($state['observations'] ?? 0) + 1;

        if ($delta === 'up') {
            $this->emitEvent($interview, 'confidence_up', 'positive', 'Candidate confidence increased', $this->msOffset($interview));
        } elseif ($delta === 'down') {
            $this->emitEvent($interview, 'confidence_down', 'warning', 'Candidate confidence dipped', $this->msOffset($interview));
        }

        if ($signal === 'strong') {
            $this->emitEvent($interview, 'strong_answer', 'positive', 'Strong answer'.($competency ? " ({$competency})" : ''), $this->msOffset($interview));
        }

        if ($flag) {
            $state['pending_red_flags'] = array_values(array_unique([...($state['pending_red_flags'] ?? []), $flag]));
            $this->emitEvent($interview, 'red_flag', 'critical', 'Possible red flag: '.str_replace('_', ' ', $flag), $this->msOffset($interview));
        }
    }

    /* ------------------------------ state ----------------------------- */

    private function initState(Interview $interview): array
    {
        $maxDuration = ($interview->template->max_duration_min ?? config('watad.interview.default_max_duration_min')) * 60;

        return [
            'phase'             => 'intro',
            'asked_count'       => 0,
            'coverage'          => array_fill_keys($this->prompts->enabledCompetencyKeys($interview), 0.0),
            'observations'      => 0,
            'pending_red_flags' => [],
            'started_at'        => now()->timestamp,
            'deadline_at'       => now()->timestamp + $maxDuration,
        ];
    }

    private function phaseFor(array $state): string
    {
        $asked = $state['asked_count'] ?? 0;
        return match (true) {
            $asked <= 1 => 'intro',
            $asked <= 4 => 'core',
            default     => 'probing',
        };
    }

    private function coverageComplete(Interview $interview, array $state): bool
    {
        $target = (float) config('watad.interview.coverage_target');
        foreach ($state['coverage'] ?? [] as $value) {
            if ($value < $target) {
                return false;
            }
        }
        return ! empty($state['coverage']);
    }

    private function nearBudget(Interview $interview, array $state): bool
    {
        $maxQ = $interview->template->max_questions ?? config('watad.interview.default_max_questions');
        $minQ = $interview->template->min_questions ?? config('watad.interview.default_min_questions');

        return ($state['asked_count'] ?? 0) >= ($maxQ - 1)
            || now()->timestamp >= ($state['deadline_at'] ?? PHP_INT_MAX)
            || ($this->coverageComplete($interview, $state) && ($state['asked_count'] ?? 0) >= $minQ)
            || $this->overTokenBudget($interview);
    }

    private function overTokenBudget(Interview $interview): bool
    {
        return ($interview->llm_input_tokens + $interview->llm_output_tokens)
            >= (int) config('watad.interview.max_tokens_per_interview');
    }

    /* ---------------------------- helpers ----------------------------- */

    private function hydrate(Interview $interview): void
    {
        $interview->loadMissing([
            'avatar',
            'jobPosition',
            'template.competencies',
            'candidate.latestCvAnalysis',
        ]);
    }

    /** Build the messages array (transcript) for the model. */
    private function buildTranscript(Interview $interview): array
    {
        return $interview->messages()
            ->where('role', '!=', 'system')
            ->orderBy('seq')
            ->get()
            ->map(fn ($m) => [
                'role'    => $m->role === 'agent' ? 'assistant' : 'user',
                'content' => $m->content,
            ])
            ->all();
    }

    private function persistMessage(Interview $interview, string $role, string $content, array $extra = [])
    {
        return $interview->messages()->create(array_merge([
            'seq'       => $this->nextSeq($interview),
            'role'      => $role,
            'content'   => $content,
            'ms_offset' => $this->msOffset($interview),
        ], $extra));
    }

    private function nextSeq(Interview $interview): int
    {
        return (int) $interview->messages()->max('seq') + 1;
    }

    private function msOffset(Interview $interview): int
    {
        if (! $interview->started_at) {
            return 0;
        }
        return max(0, now()->diffInMilliseconds($interview->started_at));
    }

    private function emitEvent(Interview $interview, string $type, string $severity, string $label, int $msOffset): void
    {
        $interview->events()->create([
            'ms_offset' => $msOffset,
            'type'      => $type,
            'severity'  => $severity,
            'label'     => $label,
        ]);
    }

    private function recordUsage(Interview $interview, LlmResult $result): void
    {
        $interview->increment('llm_input_tokens', $result->inputTokens);
        $interview->increment('llm_output_tokens', $result->outputTokens);
    }

    private function progress(Interview $interview, array $state): array
    {
        return [
            'asked' => $state['asked_count'] ?? 0,
            'min'   => $interview->template->min_questions ?? config('watad.interview.default_min_questions'),
            'max'   => $interview->template->max_questions ?? config('watad.interview.default_max_questions'),
            'phase' => $state['phase'] ?? 'core',
        ];
    }

    private function askFallback(Interview $interview, array $state): array
    {
        $message = $this->persistMessage($interview, 'agent', $this->fallbackQuestion($interview));
        $state['asked_count'] = ($state['asked_count'] ?? 0) + 1;
        $interview->state = $state;
        $interview->save();

        return [
            'status'   => 'in_progress',
            'agent'    => ['text' => $message->content, 'seq' => $message->seq, 'is_follow_up' => false],
            'progress' => $this->progress($interview, $state),
        ];
    }

    private function fallbackQuestion(Interview $interview): string
    {
        return 'Thanks for sharing that. Could you walk me through a specific project you are proud of, your exact role in it, and the outcome?';
    }

    private function defaultClosing(Interview $interview): string
    {
        $name = $interview->candidate?->full_name ? ' '.explode(' ', $interview->candidate->full_name)[0] : '';
        return "Thank you{$name}, that's everything from my side. We appreciate your time — the Watad team will review your interview and be in touch about next steps. Have a great day!";
    }
}
