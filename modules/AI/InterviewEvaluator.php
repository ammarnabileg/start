<?php

namespace App\Modules\AI;

/**
 * Produces a structured, normalized evaluation of a completed interview
 * transcript. Falls back to neutral mid-range scores when AI is unavailable.
 */
class InterviewEvaluator
{
    /**
     * The eleven competency dimensions every evaluation must contain.
     */
    private const SKILLS = [
        'communication',
        'technical_knowledge',
        'problem_solving',
        'leadership',
        'teamwork',
        'adaptability',
        'critical_thinking',
        'emotional_intelligence',
        'creativity',
        'work_ethic',
        'cultural_fit',
    ];

    private OpenAIService $ai;

    public function __construct(?OpenAIService $ai = null)
    {
        $this->ai = $ai ?? new OpenAIService();
    }

    /**
     * Evaluate an interview.
     *
     * @param array<int,array{role:string,content:string}> $interviewMessages
     * @param array<int,array<string,mixed>> $jobCriteria
     * @return array<string,mixed> Exactly the documented evaluation shape.
     */
    public function evaluate(array $interviewMessages, array $jobCriteria = []): array
    {
        $transcript = $this->buildTranscript($interviewMessages);

        $criteriaLines = [];
        foreach ($jobCriteria as $c) {
            $name = trim((string) ($c['criterion_name'] ?? ''));
            if ($name !== '') {
                $criteriaLines[] = '- ' . $name . ' (weight ' . (float) ($c['weight'] ?? 1) . ')';
            }
        }
        $criteriaBlock = $criteriaLines === [] ? '(none provided)' : implode("\n", $criteriaLines);

        $result = [];
        if ($this->ai->isConfigured() && trim($transcript) !== '') {
            $skillList = implode(', ', self::SKILLS);
            $system = 'You are an expert assessment psychologist and hiring assessor. You analyze interview '
                . 'transcripts and produce a rigorous, calibrated evaluation. Respond ONLY with a strict JSON object '
                . '(no Markdown, no prose). Required keys: overall_score (integer 0-100), recommendation (one of '
                . '"hire","maybe","reject"), skill_scores (array of {skill, score 0-10, notes}) covering exactly these '
                . 'skills: ' . $skillList . '; personality (object with disc:{D,I,S,C each 0-100} and '
                . 'big5:{openness,conscientiousness,extraversion,agreeableness,neuroticism each 0-100}); '
                . 'red_flags (array of {type, description, severity one of "low","medium","high"}); '
                . 'strengths (array of strings); areas_for_improvement (array of strings); summary (string); '
                . 'hiring_recommendation_reason (string).';

            $user = "JOB EVALUATION CRITERIA:\n" . $criteriaBlock . "\n\n"
                . "INTERVIEW TRANSCRIPT:\n" . mb_substr($transcript, 0, 9000) . "\n\n"
                . 'Assess the candidate across all listed skills, infer personality from communication style and '
                . 'content, flag any genuine concerns, and return the JSON now.';

            $result = $this->ai->chatJSON([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
                'feature' => 'interview_evaluation',
            ]);
        }

        if (!is_array($result) || $result === []) {
            return $this->fallback();
        }

        return $this->normalize($result);
    }

    /**
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private function normalize(array $r): array
    {
        $overall = $this->clampInt($r['overall_score'] ?? null, 0, 100, 60);

        $rec = strtolower(trim((string) ($r['recommendation'] ?? '')));
        if (!in_array($rec, ['hire', 'maybe', 'reject'], true)) {
            $rec = $overall >= 75 ? 'hire' : ($overall >= 50 ? 'maybe' : 'reject');
        }

        // Index any provided skill scores by normalized skill name.
        $provided = [];
        if (isset($r['skill_scores']) && is_array($r['skill_scores'])) {
            foreach ($r['skill_scores'] as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $key = $this->slugSkill((string) ($s['skill'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $provided[$key] = [
                    'score' => $this->clampInt($s['score'] ?? null, 0, 10, 5),
                    'notes' => trim((string) ($s['notes'] ?? '')),
                ];
            }
        }

        $skillScores = [];
        foreach (self::SKILLS as $skill) {
            if (isset($provided[$skill])) {
                $skillScores[] = [
                    'skill' => $skill,
                    'score' => $provided[$skill]['score'],
                    'notes' => $provided[$skill]['notes'],
                ];
            } else {
                $skillScores[] = [
                    'skill' => $skill,
                    'score' => 5,
                    'notes' => 'Not enough evidence in the transcript to assess this dimension.',
                ];
            }
        }

        $personality = $this->normalizePersonality($r['personality'] ?? []);
        $redFlags    = $this->normalizeRedFlags($r['red_flags'] ?? []);

        return [
            'overall_score'                => $overall,
            'recommendation'               => $rec,
            'skill_scores'                 => $skillScores,
            'personality'                  => $personality,
            'red_flags'                    => $redFlags,
            'strengths'                    => $this->stringList($r['strengths'] ?? []),
            'areas_for_improvement'        => $this->stringList($r['areas_for_improvement'] ?? []),
            'summary'                      => trim((string) ($r['summary'] ?? '')) ?: 'Evaluation of the candidate based on the interview transcript.',
            'hiring_recommendation_reason' => trim((string) ($r['hiring_recommendation_reason'] ?? '')) ?: 'Recommendation derived from the overall interview performance.',
        ];
    }

    /**
     * @param mixed $p
     * @return array{disc:array{D:int,I:int,S:int,C:int},big5:array{openness:int,conscientiousness:int,extraversion:int,agreeableness:int,neuroticism:int}}
     */
    private function normalizePersonality($p): array
    {
        $p = is_array($p) ? $p : [];
        $disc = is_array($p['disc'] ?? null) ? $p['disc'] : [];
        $big5 = is_array($p['big5'] ?? null) ? $p['big5'] : [];

        return [
            'disc' => [
                'D' => $this->clampInt($disc['D'] ?? $disc['d'] ?? null, 0, 100, 50),
                'I' => $this->clampInt($disc['I'] ?? $disc['i'] ?? null, 0, 100, 50),
                'S' => $this->clampInt($disc['S'] ?? $disc['s'] ?? null, 0, 100, 50),
                'C' => $this->clampInt($disc['C'] ?? $disc['c'] ?? null, 0, 100, 50),
            ],
            'big5' => [
                'openness'          => $this->clampInt($big5['openness'] ?? null, 0, 100, 50),
                'conscientiousness' => $this->clampInt($big5['conscientiousness'] ?? null, 0, 100, 50),
                'extraversion'      => $this->clampInt($big5['extraversion'] ?? null, 0, 100, 50),
                'agreeableness'     => $this->clampInt($big5['agreeableness'] ?? null, 0, 100, 50),
                'neuroticism'       => $this->clampInt($big5['neuroticism'] ?? null, 0, 100, 50),
            ],
        ];
    }

    /**
     * @param mixed $flags
     * @return array<int,array{type:string,description:string,severity:string}>
     */
    private function normalizeRedFlags($flags): array
    {
        if (!is_array($flags)) {
            return [];
        }
        $out = [];
        foreach ($flags as $f) {
            if (!is_array($f)) {
                if (is_string($f) && trim($f) !== '') {
                    $out[] = ['type' => 'concern', 'description' => trim($f), 'severity' => 'low'];
                }
                continue;
            }
            $sev = strtolower(trim((string) ($f['severity'] ?? 'low')));
            if (!in_array($sev, ['low', 'medium', 'high'], true)) {
                $sev = 'low';
            }
            $out[] = [
                'type'        => trim((string) ($f['type'] ?? 'concern')) ?: 'concern',
                'description' => trim((string) ($f['description'] ?? '')),
                'severity'    => $sev,
            ];
        }
        return $out;
    }

    /**
     * Neutral mid-range evaluation used when AI is unavailable.
     *
     * @return array<string,mixed>
     */
    private function fallback(): array
    {
        $skillScores = [];
        foreach (self::SKILLS as $skill) {
            $skillScores[] = [
                'skill' => $skill,
                'score' => 5,
                'notes' => 'Automatic evaluation unavailable; neutral default applied.',
            ];
        }

        return [
            'overall_score'                => 60,
            'recommendation'               => 'maybe',
            'skill_scores'                 => $skillScores,
            'personality'                  => [
                'disc' => ['D' => 50, 'I' => 50, 'S' => 50, 'C' => 50],
                'big5' => [
                    'openness'          => 50,
                    'conscientiousness' => 50,
                    'extraversion'      => 50,
                    'agreeableness'     => 50,
                    'neuroticism'       => 50,
                ],
            ],
            'red_flags'                    => [],
            'strengths'                    => ['Completed the interview'],
            'areas_for_improvement'        => ['A detailed AI evaluation could not be generated'],
            'summary'                      => 'AI evaluation was not available, so neutral mid-range scores were applied. '
                . 'A human reviewer should assess this candidate.',
            'hiring_recommendation_reason' => 'No AI assessment available; defaulting to a neutral "maybe" pending human review.',
        ];
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     */
    private function buildTranscript(array $messages): string
    {
        $lines = [];
        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? '');
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $label = match (strtolower($role)) {
                'ai', 'assistant', 'interviewer' => 'Interviewer',
                'candidate', 'user'              => 'Candidate',
                default                          => ucfirst($role ?: 'Speaker'),
            };
            $lines[] = $label . ': ' . $content;
        }
        return implode("\n\n", $lines);
    }

    private function slugSkill(string $skill): string
    {
        $s = strtolower(trim($skill));
        $s = str_replace([' ', '-'], '_', $s);
        $s = preg_replace('/[^a-z_]/', '', $s) ?? '';
        // Map a few common synonyms onto the canonical set.
        $aliases = [
            'technical'              => 'technical_knowledge',
            'technical_skills'       => 'technical_knowledge',
            'tech_knowledge'         => 'technical_knowledge',
            'problemsolving'         => 'problem_solving',
            'eq'                     => 'emotional_intelligence',
            'emotional_iq'           => 'emotional_intelligence',
            'critical_thinking_'     => 'critical_thinking',
            'team_work'              => 'teamwork',
            'culture_fit'            => 'cultural_fit',
            'work_ethics'            => 'work_ethic',
        ];
        return $aliases[$s] ?? $s;
    }

    private function clampInt($value, int $min, int $max, int $default): int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }
        return (int) max($min, min($max, (int) round((float) $value)));
    }

    /**
     * @return array<int,string>
     */
    private function stringList($value): array
    {
        if (!is_array($value)) {
            return $value !== null && $value !== '' ? [(string) $value] : [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $item = trim($item);
                if ($item !== '') {
                    $out[] = $item;
                }
            } elseif (is_array($item)) {
                $text = trim((string) ($item['text'] ?? $item['description'] ?? $item['value'] ?? ''));
                if ($text !== '') {
                    $out[] = $text;
                }
            }
        }
        return $out;
    }
}
