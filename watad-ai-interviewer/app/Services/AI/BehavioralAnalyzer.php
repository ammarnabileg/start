<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\BehavioralAnalysis;
use App\Models\Interview;
use App\Services\AI\Prompts\PromptLibrary;

/**
 * Produces an interview-based (approximate) behavioral profile: DISC, Big-Five, leadership,
 * growth-mindset, stress-handling, risk/integrity indicators. Not a clinical instrument.
 */
final class BehavioralAnalyzer
{
    use BuildsTranscript;

    public function __construct(
        private readonly LlmManager $llm,
        private readonly PromptLibrary $prompts,
    ) {}

    public function analyze(Interview $interview): BehavioralAnalysis
    {
        $interview->loadMissing(['jobPosition', 'candidate.latestCvAnalysis']);

        $data = $this->llm->json('analysis', [
            'system'   => [['type' => 'text', 'text' => $this->prompts->behavioralSystem()]],
            'thinking' => $this->prompts->analysisThinking(),
            'messages' => [['role' => 'user', 'content' => $this->analysisContext($interview)]],
        ]);

        return BehavioralAnalysis::updateOrCreate(
            ['interview_id' => $interview->id],
            [
                'personality_type'      => $data['personality_type'] ?? null,
                'disc'                  => $data['disc'] ?? null,
                'big_five'              => $data['big_five'] ?? null,
                'leadership_tendency'   => $data['leadership_tendency'] ?? null,
                'growth_mindset_score'  => isset($data['growth_mindset_score']) ? (float) $data['growth_mindset_score'] : null,
                'stress_handling_score' => isset($data['stress_handling_score']) ? (float) $data['stress_handling_score'] : null,
                'risk_indicators'       => $data['risk_indicators'] ?? null,
                'integrity_indicators'  => $data['integrity_indicators'] ?? null,
                'observations'          => $data['observations'] ?? null,
                'model'                 => $this->llm->model('analysis'),
            ],
        );
    }
}
