<?php

class CVAnalyzer
{
    public function __construct(private OpenAIService $ai) {}

    /**
     * Analyze a candidate's CV text against job criteria.
     *
     * @param  string               $cvText    Plain text of the CV/resume
     * @param  array<string,mixed>  $job       Job record from DB
     * @param  array<string,mixed>  $criteria  Screening criteria for this job
     * @return array<string,mixed>
     */
    public function analyze(string $cvText, array $job, array $criteria): array
    {
        $criteriaText = '';
        if (!empty($criteria)) {
            foreach ($criteria as $c) {
                $weight       = $c['weight'] ?? 'N/A';
                $criteriaText .= "- {$c['name']} (weight: {$weight}): {$c['description']}\n";
            }
        }

        $systemPrompt = <<<PROMPT
You are an expert recruiter and talent assessment specialist. Analyze the provided CV against the job requirements and screening criteria.
Return your analysis as a valid JSON object only — no markdown, no extra text.

JSON schema:
{
  "skills_scores": {
    "communication": 0-100,
    "problem_solving": 0-100,
    "technical_knowledge": 0-100,
    "leadership": 0-100,
    "teamwork": 0-100,
    "adaptability": 0-100,
    "creativity": 0-100,
    "attention_to_detail": 0-100,
    "work_ethic": 0-100,
    "emotional_intelligence": 0-100,
    "cultural_fit": 0-100
  },
  "overall_score": 0-100,
  "recommendation": "hire" | "maybe" | "no",
  "reasoning": "string — concise explanation of the score and recommendation",
  "red_flags": [
    {"flag": "string", "severity": "low" | "medium" | "high"}
  ],
  "behavioral_analysis": {
    "disc": {"D": 0-100, "I": 0-100, "S": 0-100, "C": 0-100},
    "big_five": {
      "openness": 0-100,
      "conscientiousness": 0-100,
      "extraversion": 0-100,
      "agreeableness": 0-100,
      "neuroticism": 0-100
    }
  }
}
PROMPT;

        $userPrompt = <<<PROMPT
Job Title: {$job['title']}
Department: {$job['department']}

Job Description:
{$job['description']}

Screening Criteria:
{$criteriaText}

CV / Resume:
{$cvText}

Analyze this CV against the job requirements and return a JSON object matching the schema.
PROMPT;

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

        try {
            $parsed = json_decode($result['content'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Attempt to salvage JSON from the response (strip markdown fences)
            $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($result['content']));
            $clean = preg_replace('/\s*```$/', '', $clean);
            try {
                $parsed = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e2) {
                return $this->defaultResult();
            }
        }

        return $this->normalizeResult($parsed);
    }

    private function defaultResult(): array
    {
        return [
            'skills_scores' => [
                'communication'       => 0,
                'problem_solving'     => 0,
                'technical_knowledge' => 0,
                'leadership'          => 0,
                'teamwork'            => 0,
                'adaptability'        => 0,
                'creativity'          => 0,
                'attention_to_detail' => 0,
                'work_ethic'          => 0,
                'emotional_intelligence' => 0,
                'cultural_fit'        => 0,
            ],
            'overall_score'       => 0,
            'recommendation'      => 'no',
            'reasoning'           => 'Analysis could not be completed.',
            'red_flags'           => [],
            'behavioral_analysis' => [
                'disc'     => ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0],
                'big_five' => [
                    'openness'          => 0,
                    'conscientiousness' => 0,
                    'extraversion'      => 0,
                    'agreeableness'     => 0,
                    'neuroticism'       => 0,
                ],
            ],
        ];
    }

    private function normalizeResult(array $parsed): array
    {
        $default = $this->defaultResult();

        $skillKeys = array_keys($default['skills_scores']);
        $skills    = [];
        foreach ($skillKeys as $key) {
            $skills[$key] = (int) ($parsed['skills_scores'][$key] ?? 0);
        }

        $recommendation = $parsed['recommendation'] ?? 'no';
        if (!in_array($recommendation, ['hire', 'maybe', 'no'], true)) {
            $recommendation = 'no';
        }

        $redFlags = [];
        foreach ((array) ($parsed['red_flags'] ?? []) as $rf) {
            if (!is_array($rf) || empty($rf['flag'])) { continue; }
            $severity   = $rf['severity'] ?? 'low';
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
                    'D' => (int) ($disc['D'] ?? 0),
                    'I' => (int) ($disc['I'] ?? 0),
                    'S' => (int) ($disc['S'] ?? 0),
                    'C' => (int) ($disc['C'] ?? 0),
                ],
                'big_five' => [
                    'openness'          => (int) ($big5['openness'] ?? 0),
                    'conscientiousness' => (int) ($big5['conscientiousness'] ?? 0),
                    'extraversion'      => (int) ($big5['extraversion'] ?? 0),
                    'agreeableness'     => (int) ($big5['agreeableness'] ?? 0),
                    'neuroticism'       => (int) ($big5['neuroticism'] ?? 0),
                ],
            ],
        ];
    }
}
