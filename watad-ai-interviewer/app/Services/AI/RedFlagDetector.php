<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\RedFlagType;
use App\Models\Interview;
use App\Services\AI\Prompts\PromptLibrary;

/**
 * Detects transcript-supported red flags via the LLM, plus a deterministic salary-mismatch check
 * computed from the posted band and the candidate's expectation. See docs/08.
 */
final class RedFlagDetector
{
    use BuildsTranscript;

    public function __construct(
        private readonly LlmManager $llm,
        private readonly PromptLibrary $prompts,
    ) {}

    public function detect(Interview $interview): void
    {
        $interview->loadMissing(['jobPosition', 'candidate']);
        $interview->redFlags()->delete(); // idempotent re-run

        // 1) LLM-detected flags (evidence-bound)
        $data = $this->llm->json('analysis', [
            'system'   => [['type' => 'text', 'text' => $this->prompts->redFlagSystem()]],
            'thinking' => $this->prompts->analysisThinking(),
            'messages' => [['role' => 'user', 'content' => $this->analysisContext($interview)]],
        ]);

        foreach ($data['red_flags'] ?? [] as $flag) {
            $type = $flag['type'] ?? null;
            if (! $type || ! in_array($type, RedFlagType::values(), true)) {
                continue;
            }
            $interview->redFlags()->create([
                'type'        => $type,
                'severity'    => in_array($flag['severity'] ?? '', ['low', 'medium', 'high'], true) ? $flag['severity'] : 'medium',
                'description' => $flag['description'] ?? '',
                'evidence'    => $flag['evidence_seqs'] ?? null,
            ]);
        }

        // 2) Deterministic salary-mismatch check
        $this->salaryCheck($interview);
    }

    private function salaryCheck(Interview $interview): void
    {
        $candidate = $interview->candidate;
        $job       = $interview->jobPosition;
        $expected  = $candidate?->expected_salary;
        $min       = $job?->salary_min;
        $max       = $job?->salary_max;

        if (! $expected || ! $min || ! $max) {
            return;
        }

        $tol     = (float) config('watad.scoring.salary_tolerance');
        $floor   = $min * (1 - $tol);
        $ceiling = $max * (1 + $tol);

        if ($expected < $floor || $expected > $ceiling) {
            $interview->redFlags()->updateOrCreate(
                ['type' => RedFlagType::SalaryMismatch->value],
                [
                    'severity'    => $expected > $ceiling ? 'medium' : 'low',
                    'description' => "Expected salary {$expected} is outside the posted band {$min}-{$max} (±".(int) ($tol * 100).'%).',
                    'evidence'    => ['expected' => $expected, 'band' => [$min, $max]],
                ],
            );
        }
    }
}
