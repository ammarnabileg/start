<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\InterviewStatus;
use App\Events\AgentMessageStreamed;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnswerRequest;
use App\Models\Interview;
use App\Models\Recording;
use App\Services\AI\InterviewEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Drives the live interview from the candidate's browser. Lightweight session auth: the browser
 * that completed intake holds the interview public_id in its session (see InvitationController).
 */
class InterviewApiController extends Controller
{
    public function __construct(private readonly InterviewEngine $engine) {}

    public function start(Interview $interview): JsonResponse
    {
        $this->guard($interview);

        if ($interview->status !== InterviewStatus::Scheduled) {
            return response()->json($this->resume($interview));
        }

        return response()->json($this->engine->start($interview));
    }

    public function answer(AnswerRequest $request, Interview $interview): JsonResponse
    {
        $this->guard($interview);
        abort_unless($interview->status === InterviewStatus::InProgress, 409, 'Interview is not active.');

        $onDelta = fn (string $delta) => broadcast(new AgentMessageStreamed($interview->public_id, $delta));

        $result = $this->engine->handleTurn($interview, $request->validated()['text'], $onDelta);

        return response()->json($result);
    }

    public function complete(Interview $interview): JsonResponse
    {
        $this->guard($interview);

        if ($interview->status === InterviewStatus::InProgress) {
            return response()->json($this->engine->complete($interview));
        }

        return response()->json(['status' => $interview->status->value]);
    }

    /** Voice mode: store the candidate's recorded audio for the most recent answer turn. */
    public function uploadAudio(Request $request, Interview $interview): JsonResponse
    {
        $this->guard($interview);
        $request->validate(['audio' => ['required', 'file', 'mimes:webm,ogg,wav,mp3,m4a', 'max:10240']]);

        $path = $request->file('audio')->store("interviews/{$interview->public_id}/audio");

        Recording::create([
            'interview_id' => $interview->id,
            'kind'         => 'audio',
            'provider'     => 'local',
            'url'          => $path,
            'status'       => 'ready',
        ]);

        $interview->messages()->where('role', 'candidate')->orderByDesc('seq')->first()
            ?->update(['audio_path' => $path]);

        return response()->json(['ok' => true]);
    }

    public function state(Interview $interview): JsonResponse
    {
        $this->guard($interview);

        $last = $interview->messages()->where('role', 'agent')->orderByDesc('seq')->first();

        return response()->json([
            'status'    => $interview->status->value,
            'last_agent' => $last?->content,
            'progress'  => [
                'asked' => $interview->question_count,
                'phase' => $interview->state['phase'] ?? 'core',
            ],
        ]);
    }

    private function resume(Interview $interview): array
    {
        $last = $interview->messages()->where('role', 'agent')->orderByDesc('seq')->first();

        return [
            'status' => $interview->status->value,
            'agent'  => ['text' => $last?->content ?? '', 'seq' => $last?->seq ?? 0],
        ];
    }

    private function guard(Interview $interview): void
    {
        abort_unless(session('interview_id') === $interview->public_id, 403);
    }
}
