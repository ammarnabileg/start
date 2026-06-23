<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\Competency;
use App\Models\Interview;
use App\Services\AI\Prompts\PromptLibrary;

/**
 * Scores each enabled competency 0-100 from the transcript, persisting one competency_scores row
 * per competency and returning the weighted overall score. See docs/08-scoring-and-analysis.md.
 */
final class ScoringService
{
    use BuildsTranscript;

    public function __construct(
        private readonly LlmManager $llm,
        private readonly PromptLibrary $prompts,
    ) {}

    /** @return array{overall: float, scores: array<string, float>} */
    public function score(Interview $interview): array
    {
        $interview->loadMissing(['jobPosition', 'template.competencies', 'candidate.latestCvAnalysis']);

        $data = $this->llm->json('analysis', [
            'system'   => [['type' => 'text', 'text' => $this->prompts->scoringSystem($interview)]],
            'thinking' => $this->prompts->analysisThinking(),
            'messages' => [[
                'role'    => 'user',
                'content' => $this->analysisContext($interview),
            ]],
        ]);

        $weights  = $this->weights($interview);
        $enabled  = $this->prompts->enabledCompetencyKeys($interview);
        $scores   = [];
        $totalW   = 0.0;
        $weighted = 0.0;

        foreach ($data['scores'] ?? [] as $row) {
            $key = $row['competency'] ?? null;
            if (! $key || ! in_array($key, $enabled, true)) {
                continue;
            }
            $value  = max(0, min(100, (float) ($row['score'] ?? 0)));
            $weight = $weights[$key] ?? Competency::from($key)->defaultWeight();

            $interview->competencyScores()->updateOrCreate(
                ['competency' => $key],
                [
                    'score'      => $value,
                    'weight'     => $weight,
                    'confidence' => isset($row['confidence']) ? (float) $row['confidence'] : null,
                    'rationale'  => $row['rationale'] ?? null,
                    'evidence'   => $row['evidence_seqs'] ?? null,
                ],
            );

            $scores[$key] = $value;
            $weighted += $value * $weight;
            $totalW   += $weight;
        }

        $overall = $totalW > 0 ? round($weighted / $totalW, 2) : 0.0;

        return ['overall' => $overall, 'scores' => $scores];
    }

    /** @return array<string, float> competency => weight */
    private function weights(Interview $interview): array
    {
        $weights = [];
        foreach ($interview->template?->competencies ?? [] as $tc) {
            if ($tc->is_enabled) {
                $weights[$tc->competency] = (float) $tc->weight;
            }
        }
        return $weights;
    }
}
