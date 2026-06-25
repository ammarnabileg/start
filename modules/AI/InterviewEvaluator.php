<?php

class InterviewEvaluator
{
    public function __construct(private OpenAIService $ai) {}

    /**
     * Evaluate a completed interview transcript and return structured scores.
     *
     * @param  array<array{role:string,content:string}> $transcript
     * @param  array<string,mixed>                      $job
     * @param  array<string,mixed>                      $criteria
     * @return array<string,mixed>
     */
    public function evaluate(array $transcript, array $job, array $criteria): array
    {
        $transcriptText = '';
        foreach ($transcript as $entry) {
            $role           = $entry['role'] === 'candidate' ? 'Candidate' : 'Interviewer';
            $transcriptText .= "{$role}: {$entry['content']}\n\n";
        }

        $criteriaText = '';
        if (!empty($criteria)) {
            foreach ($criteria as $c) {
                $weight       = $c['weight'] ?? 'N/A';
                $criteriaText .= "- {$c['name']} (weight: {$weight}): {$c['description']}\n";
            }
        }

        $systemPrompt = 'You are an expert hiring assessment specialist. Evaluate the provided interview transcript against the job requirements and screening criteria. Return your evaluation as a valid JSON object only — no markdown, no extra text. Schema: {"skills_scores":{"communication":0-100,"problem_solving":0-100,"technical_knowledge":0-100,"leadership":0-100,"teamwork":0-100,"adaptability":0-100,"creativity":0-100,"attention_to_detail":0-100,"work_ethic":0-100,"emotional_intelligence":0-100,"cultural_fit":0-100},"overall_score":0-100,"recommendation":"hire"|"maybe"|"no","reasoning":"string","red_flags":[{"flag":"string","severity":"low"|"medium"|"high"}],"behavioral_analysis":{"disc":{"D":0-100,"I":0-100,"S":0-100,"C":0-100},"big_five":{"openness":0-100,"conscientiousness":0-100,"extraversion":0-100,"agreeableness":0-100,"neuroticism":0-100}}}';

        $userPrompt = "Job Title: {$job['title']}\nDepartment: {$job['department']}\n\nJob Description:\n{$job['description']}\n\nScreening Criteria:\n{$criteriaText}\n\nInterview Transcript:\n{$transcriptText}\n\nEvaluate the candidate and return JSON matching the schema.";

        $result = $this->ai->chat(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            ['response_format' => ['type' => 'json_object']]
        );

        if ($result === null) {
            return $this->defaultResult();
        }

        $parsed = $this->parseJson($result['content']);

        if ($parsed === null) {
            return $this->defaultResult();
        }

        return $this->normalizeResult($parsed);
    }

    private function parseJson(string $content): ?array
    {
        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {}

        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $clean = preg_replace('/\s*```$/', '', $clean ?? '');
        try {
            return json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private function defaultResult(): array
    {
        return [
            'skills_scores' => [
                'communication' => 0, 'problem_solving' => 0, 'technical_knowledge' => 0,
                'leadership' => 0, 'teamwork' => 0, 'adaptability' => 0,
                'creativity' => 0, 'attention_to_detail' => 0, 'work_ethic' => 0,
                'emotional_intelligence' => 0, 'cultural_fit' => 0,
            ],
            'overall_score'       => 0,
            'recommendation'      => 'no',
            'reasoning'           => 'Evaluation could not be completed.',
            'red_flags'           => [],
            'behavioral_analysis' => [
                'disc'     => ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0],
                'big_five' => ['openness' => 0, 'conscientiousness' => 0, 'extraversion' => 0, 'agreeableness' => 0, 'neuroticism' => 0],
            ],
        ];
    }

    private function normalizeResult(array $parsed): array
    {
        $skillKeys = array_keys($this->defaultResult()['skills_scores']);
        $skills    = [];
        foreach ($skillKeys as $key) {
            $skills[$key] = min(100, max(0, (int) ($parsed['skills_scores'][$key] ?? 0)));
        }

        $recommendation = $parsed['recommendation'] ?? 'no';
        if (!in_array($recommendation, ['hire', 'maybe', 'no'], true)) {
            $recommendation = 'no';
        }

        $redFlags = [];
        foreach ((array) ($parsed['red_flags'] ?? []) as $rf) {
            if (!is_array($rf) || empty($rf['flag'])) { continue; }
            $severity = $rf['severity'] ?? 'low';
            if (!in_array($severity, ['low', 'medium', 'high'], true)) { $severity = 'low'; }
            $redFlags[] = ['flag' => (string) $rf['flag'], 'severity' => $severity];
        }

        $disc = $parsed['behavioral_analysis']['disc'] ?? [];
        $big5 = $parsed['behavioral_analysis']['big_five'] ?? [];

        return [
            'skills_scores'       => $skills,
            'overall_score'       => min(100, max(0, (int) ($parsed['overall_score'] ?? 0))),
            'recommendation'      => $recommendation,
            'reasoning'           => (string) ($parsed['reasoning'] ?? ''),
            'red_flags'           => $redFlags,
            'behavioral_analysis' => [
                'disc' => [
                    'D' => min(100, max(0, (int) ($disc['D'] ?? 0))),
                    'I' => min(100, max(0, (int) ($disc['I'] ?? 0))),
                    'S' => min(100, max(0, (int) ($disc['S'] ?? 0))),
                    'C' => min(100, max(0, (int) ($disc['C'] ?? 0))),
                ],
                'big_five' => [
                    'openness'          => min(100, max(0, (int) ($big5['openness'] ?? 0))),
                    'conscientiousness' => min(100, max(0, (int) ($big5['conscientiousness'] ?? 0))),
                    'extraversion'      => min(100, max(0, (int) ($big5['extraversion'] ?? 0))),
                    'agreeableness'     => min(100, max(0, (int) ($big5['agreeableness'] ?? 0))),
                    'neuroticism'       => min(100, max(0, (int) ($big5['neuroticism'] ?? 0))),
                ],
            ],
        ];
    }
}