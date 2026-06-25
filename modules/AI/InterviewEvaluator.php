<?php
declare(strict_types=1);

namespace Modules\AI;

/**
 * InterviewEvaluator - Produces a comprehensive post-interview evaluation.
 *
 * Combines the full interview transcript, the job criteria and the candidate's
 * CV analysis into a single structured assessment: an 11-skill weighted
 * competency profile, criteria validation against targets, personality
 * analysis (DISC + Big Five), red flags, CV-vs-interview consistency notes and
 * a final recommendation.
 *
 * The overall_score is computed deterministically in PHP from the weighted
 * skill scores so it is reproducible and auditable, independent of the model.
 */
class InterviewEvaluator
{
    private OpenAIService $ai;

    /**
     * The canonical 11 competencies and their default weights (sum = 100).
     * The model is asked to score each 0-100; the weighted sum yields the
     * overall score.
     */
    private array $skillWeights = [
        'technical_competency'  => 18,
        'communication'         => 12,
        'problem_solving'       => 12,
        'critical_thinking'     => 10,
        'confidence'            => 8,
        'leadership'            => 8,
        'culture_fit'           => 8,
        'professionalism'       => 8,
        'ai_knowledge'          => 6,
        'english_proficiency'   => 6,
        'learning_ability'      => 4,
    ];

    public function __construct(?OpenAIService $ai = null)
    {
        $this->ai = $ai ?? new OpenAIService();
    }

    /**
     * Run the full evaluation.
     *
     * @param array $interview   Interview row.
     * @param array $messages    Full transcript (role/content [+ flags]).
     * @param array $job         Job row.
     * @param array $criteria    job_criteria rows.
     * @param array $candidateCv CV analysis (from CVAnalyzer) or candidate row.
     *
     * @return array Full evaluation structure (documented shape).
     */
    public function evaluate(array $interview, array $messages, array $job, array $criteria, array $candidateCv = []): array
    {
        if (empty($messages)) {
            return $this->emptyEvaluation('No interview transcript available to evaluate.');
        }

        $system = $this->systemPrompt();
        $user   = $this->userPrompt($interview, $messages, $job, $criteria, $candidateCv);

        try {
            $result = $this->ai->chatJson(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                $this->schema(),
                ['temperature' => 0.2, 'max_tokens' => 4000]
            );
        } catch (\Throwable $e) {
            error_log('[InterviewEvaluator] OpenAI error: ' . $e->getMessage());
            return $this->emptyEvaluation();
        }

        $this->ai->logUsage(
            (int) ($interview['tenant_id'] ?? 0),
            0,
            'interview_evaluation',
            ['model' => $result['model']] + $result['tokens'] + ['cost' => $result['cost']],
            'interview',
            (int) ($interview['id'] ?? 0)
        );

        $evaluation = $this->normalize($result['data'], $criteria);
        $evaluation['ai_tokens_used'] = (int) ($result['tokens']['total'] ?? 0);

        return $evaluation;
    }

    // ==================================================================
    // Prompts
    // ==================================================================

    private function systemPrompt(): string
    {
        $skills = implode(', ', array_keys($this->skillWeights));

        return <<<PROMPT
You are a world-class hiring assessor and organizational psychologist. You read
a complete interview transcript and produce a rigorous, evidence-based
evaluation of the candidate. You are fair, objective and free of bias related
to gender, ethnicity, age, accent or background.

Core principles:
- Every score and claim MUST be grounded in concrete evidence from the
  transcript. For each competency, cite a brief, specific piece of evidence
  (paraphrase or short quote). If there is no evidence for a competency, score
  it conservatively (around 40-55) and say evidence was limited — never invent
  details.
- Score each of these 11 competencies from 0 to 100:
  {$skills}.
  Use the full range honestly: 85-100 outstanding, 70-84 strong, 55-69
  adequate, 40-54 weak, below 40 poor.
- For each job criterion provided, give a score on a 0-5 scale (one decimal
  allowed), compare it to its target, and mark whether it was met.
- personality_analysis: estimate a DISC profile (D, I, S, C each 0-100 with a
  primary and secondary letter) and a Big Five profile (openness,
  conscientiousness, extraversion, agreeableness, neuroticism each 0-100),
  plus a growth_score, a stress_score (lower is calmer), a leadership_style and
  a learning_style. Base these on observable communication patterns only.
- red_flags: list objective concerns with a severity of "low", "medium" or
  "high" and the supporting evidence. Examples: salary expectations far outside
  range, unexplained employment gaps not addressed when asked, evasive or
  contradictory answers, claims that conflict with the CV. Use an empty array
  when there are none. Do NOT fabricate red flags.
- cv_match_notes: note any inconsistency between what the CV claims and what the
  interview revealed (e.g. years of experience, scope of past roles). If the CV
  analysis is not provided, say so briefly.
- language_analysis: report the detected language ("en", "ar" or "mixed"),
  fluency level and any neutral accent notes (or null).
- executive_summary: 2-4 sentences a busy hiring manager can read in 10 seconds.
- strengths and weaknesses: specific and actionable, tied to evidence.
- Do NOT compute the final overall score yourself beyond the competency scores;
  the system derives it from your competency scores and their weights.
- Output ONLY the JSON object defined by the schema. No markdown, no prose.
PROMPT;
    }

    private function userPrompt(array $interview, array $messages, array $job, array $criteria, array $candidateCv): string
    {
        $jobTitle   = (string) ($job['title'] ?? 'Unspecified role');
        $seniority  = (string) ($job['seniority'] ?? 'unspecified');
        $jobDesc    = $this->clip((string) ($job['description'] ?? ''), 2500);
        $criteriaTxt = $this->formatCriteria($criteria);
        $transcript = $this->formatTranscript($messages);
        $cvBlock    = $this->formatCv($candidateCv);
        $weightsTxt = $this->formatWeights();

        return <<<PROMPT
=== JOB ===
Title: {$jobTitle}
Seniority: {$seniority}
Description:
{$jobDesc}

=== JOB CRITERIA (score each 0-5 against its target) ===
{$criteriaTxt}

=== COMPETENCY WEIGHTS (for your awareness; score each competency 0-100) ===
{$weightsTxt}

=== CV ANALYSIS (for consistency checking) ===
{$cvBlock}

=== INTERVIEW TRANSCRIPT ===
{$transcript}

=== TASK ===
Evaluate this candidate thoroughly and return the structured JSON evaluation
exactly as specified by the schema. Ground every score in transcript evidence.
PROMPT;
    }

    // ==================================================================
    // Schema
    // ==================================================================

    private function schema(): array
    {
        $skillProps = [];
        foreach (array_keys($this->skillWeights) as $skill) {
            $skillProps[$skill] = [
                'type'       => 'object',
                'properties' => [
                    'score'    => ['type' => 'number'],
                    'evidence' => ['type' => 'string'],
                ],
            ];
        }

        return [
            'name'   => 'interview_evaluation',
            'strict' => false,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => true,
                'properties' => [
                    'recommendation'    => ['type' => 'string'],
                    'executive_summary' => ['type' => 'string'],
                    'strengths'         => ['type' => 'array', 'items' => ['type' => 'string']],
                    'weaknesses'        => ['type' => 'array', 'items' => ['type' => 'string']],
                    'skills_analysis'   => [
                        'type'       => 'object',
                        'properties' => $skillProps,
                    ],
                    'criteria_scores' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'criterion' => ['type' => 'string'],
                                'score'     => ['type' => 'number'],
                                'target'    => ['type' => 'number'],
                                'met'       => ['type' => 'boolean'],
                                'evidence'  => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'personality_analysis' => [
                        'type'       => 'object',
                        'properties' => [
                            'disc' => [
                                'type'       => 'object',
                                'properties' => [
                                    'D' => ['type' => 'number'], 'I' => ['type' => 'number'],
                                    'S' => ['type' => 'number'], 'C' => ['type' => 'number'],
                                    'primary' => ['type' => 'string'], 'secondary' => ['type' => 'string'],
                                ],
                            ],
                            'big_five' => [
                                'type'       => 'object',
                                'properties' => [
                                    'openness' => ['type' => 'number'], 'conscientiousness' => ['type' => 'number'],
                                    'extraversion' => ['type' => 'number'], 'agreeableness' => ['type' => 'number'],
                                    'neuroticism' => ['type' => 'number'],
                                ],
                            ],
                            'growth_score'     => ['type' => 'number'],
                            'stress_score'     => ['type' => 'number'],
                            'leadership_style' => ['type' => 'string'],
                            'learning_style'   => ['type' => 'string'],
                        ],
                    ],
                    'red_flags' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'flag'     => ['type' => 'string'],
                                'severity' => ['type' => 'string'],
                                'evidence' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'cv_match_notes'    => ['type' => 'string'],
                    'language_analysis' => [
                        'type'       => 'object',
                        'properties' => [
                            'detected'     => ['type' => 'string'],
                            'fluency'      => ['type' => 'string'],
                            'accent_notes' => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'required' => ['skills_analysis', 'executive_summary', 'strengths', 'weaknesses'],
            ],
        ];
    }

    // ==================================================================
    // Normalization + scoring
    // ==================================================================

    /**
     * Validate, clamp and complete the model output, then compute the
     * deterministic overall score and recommendation.
     */
    private function normalize(array $data, array $criteria): array
    {
        // --- Skills analysis (11 competencies) ---
        $rawSkills = is_array($data['skills_analysis'] ?? null) ? $data['skills_analysis'] : [];
        $skills = [];
        foreach ($this->skillWeights as $skill => $weight) {
            $entry  = is_array($rawSkills[$skill] ?? null) ? $rawSkills[$skill] : [];
            $score  = $this->clampScore((float) ($entry['score'] ?? 0), 0, 100);
            $skills[$skill] = [
                'score'    => $score,
                'weight'   => $weight,
                'evidence' => (string) ($entry['evidence'] ?? ''),
            ];
        }

        // --- Deterministic weighted overall score ---
        $overall = $this->weightedOverall($skills);

        // --- Recommendation thresholds ---
        $recommendation = $this->recommendationFor($overall);

        // --- Criteria scores validated against targets ---
        $criteriaScores = $this->normalizeCriteria($data['criteria_scores'] ?? [], $criteria);

        // --- Personality analysis ---
        $personality = $this->normalizePersonality($data['personality_analysis'] ?? []);

        // --- Red flags ---
        $redFlags = $this->normalizeRedFlags($data['red_flags'] ?? []);

        // --- Language analysis ---
        $language = $this->normalizeLanguage($data['language_analysis'] ?? []);

        return [
            'overall_score'        => $overall,
            'recommendation'       => $recommendation,
            'executive_summary'    => (string) ($data['executive_summary'] ?? ''),
            'strengths'            => $this->stringArray($data['strengths'] ?? []),
            'weaknesses'           => $this->stringArray($data['weaknesses'] ?? []),
            'skills_analysis'      => $skills,
            'criteria_scores'      => $criteriaScores,
            'personality_analysis' => $personality,
            'red_flags'            => $redFlags,
            'cv_match_notes'       => (string) ($data['cv_match_notes'] ?? ''),
            'language_analysis'    => $language,
        ];
    }

    /**
     * Weighted sum of competency scores. Weights are renormalized to their
     * actual total so the result is always a clean 0-100 figure.
     */
    private function weightedOverall(array $skills): int
    {
        $weightedSum = 0.0;
        $totalWeight = 0.0;
        foreach ($skills as $s) {
            $weightedSum += ((float) $s['score']) * ((float) $s['weight']);
            $totalWeight += (float) $s['weight'];
        }
        if ($totalWeight <= 0) {
            return 0;
        }
        return (int) round($weightedSum / $totalWeight);
    }

    /**
     * Recommendation thresholds:
     *   82+      => strong
     *   68-81    => suitable
     *   50-67    => possible
     *   below 50 => not_recommended
     */
    public function recommendationFor(int $overall): string
    {
        return match (true) {
            $overall >= 82 => 'strong',
            $overall >= 68 => 'suitable',
            $overall >= 50 => 'possible',
            default        => 'not_recommended',
        };
    }

    private function normalizeCriteria($raw, array $criteria): array
    {
        // Index the configured criteria by normalized name for target lookup.
        $targets = [];
        foreach ($criteria as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            if ($name !== '') {
                $targets[$this->key($name)] = (float) ($c['target_score'] ?? 3.0);
            }
        }

        $out = [];
        $seen = [];
        foreach ((array) $raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name   = trim((string) ($item['criterion'] ?? ''));
            if ($name === '') {
                continue;
            }
            $score  = $this->clampScore((float) ($item['score'] ?? 0), 0, 5);
            $target = isset($item['target'])
                ? (float) $item['target']
                : ($targets[$this->key($name)] ?? 3.0);
            $met = array_key_exists('met', $item) ? (bool) $item['met'] : ($score >= $target);

            $out[] = [
                'criterion' => $name,
                'score'     => round($score, 1),
                'target'    => round($target, 1),
                'met'       => $met,
                'evidence'  => (string) ($item['evidence'] ?? ''),
            ];
            $seen[$this->key($name)] = true;
        }

        // Ensure every configured criterion appears, even if the model omitted it.
        foreach ($criteria as $c) {
            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '' || isset($seen[$this->key($name)])) {
                continue;
            }
            $target = (float) ($c['target_score'] ?? 3.0);
            $out[] = [
                'criterion' => $name,
                'score'     => 0.0,
                'target'    => round($target, 1),
                'met'       => false,
                'evidence'  => 'Not assessed during the interview.',
            ];
        }

        return $out;
    }

    private function normalizePersonality($raw): array
    {
        $raw  = is_array($raw) ? $raw : [];
        $disc = is_array($raw['disc'] ?? null) ? $raw['disc'] : [];
        $big  = is_array($raw['big_five'] ?? null) ? $raw['big_five'] : [];

        $d = $this->clampScore((float) ($disc['D'] ?? 0), 0, 100);
        $i = $this->clampScore((float) ($disc['I'] ?? 0), 0, 100);
        $s = $this->clampScore((float) ($disc['S'] ?? 0), 0, 100);
        $c = $this->clampScore((float) ($disc['C'] ?? 0), 0, 100);

        // Derive primary/secondary if absent or invalid.
        $ranked = ['D' => $d, 'I' => $i, 'S' => $s, 'C' => $c];
        arsort($ranked);
        $letters  = array_keys($ranked);
        $primary  = (string) ($disc['primary'] ?? '');
        $secondary = (string) ($disc['secondary'] ?? '');
        if (!in_array($primary, ['D', 'I', 'S', 'C'], true)) {
            $primary = $letters[0];
        }
        if (!in_array($secondary, ['D', 'I', 'S', 'C'], true) || $secondary === $primary) {
            $secondary = $letters[1] ?? $letters[0];
        }

        return [
            'disc' => [
                'D' => $d, 'I' => $i, 'S' => $s, 'C' => $c,
                'primary' => $primary, 'secondary' => $secondary,
            ],
            'big_five' => [
                'openness'          => $this->clampScore((float) ($big['openness'] ?? 0), 0, 100),
                'conscientiousness' => $this->clampScore((float) ($big['conscientiousness'] ?? 0), 0, 100),
                'extraversion'      => $this->clampScore((float) ($big['extraversion'] ?? 0), 0, 100),
                'agreeableness'     => $this->clampScore((float) ($big['agreeableness'] ?? 0), 0, 100),
                'neuroticism'       => $this->clampScore((float) ($big['neuroticism'] ?? 0), 0, 100),
            ],
            'growth_score'     => $this->clampScore((float) ($raw['growth_score'] ?? 0), 0, 100),
            'stress_score'     => $this->clampScore((float) ($raw['stress_score'] ?? 0), 0, 100),
            'leadership_style' => (string) ($raw['leadership_style'] ?? ''),
            'learning_style'   => (string) ($raw['learning_style'] ?? ''),
        ];
    }

    private function normalizeRedFlags($raw): array
    {
        $out = [];
        foreach ((array) $raw as $item) {
            if (!is_array($item)) {
                // Allow a plain string flag.
                if (is_string($item) && trim($item) !== '') {
                    $out[] = ['flag' => trim($item), 'severity' => 'medium', 'evidence' => ''];
                }
                continue;
            }
            $flag = trim((string) ($item['flag'] ?? ''));
            if ($flag === '') {
                continue;
            }
            $severity = strtolower((string) ($item['severity'] ?? 'medium'));
            if (!in_array($severity, ['low', 'medium', 'high'], true)) {
                $severity = 'medium';
            }
            $out[] = [
                'flag'     => $flag,
                'severity' => $severity,
                'evidence' => (string) ($item['evidence'] ?? ''),
            ];
        }
        return $out;
    }

    private function normalizeLanguage($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $detected = strtolower((string) ($raw['detected'] ?? 'en'));
        if (!in_array($detected, ['en', 'ar', 'mixed'], true)) {
            $detected = 'en';
        }
        $accent = $raw['accent_notes'] ?? null;
        return [
            'detected'     => $detected,
            'fluency'      => (string) ($raw['fluency'] ?? ''),
            'accent_notes' => ($accent === null || $accent === '') ? null : (string) $accent,
        ];
    }

    private function emptyEvaluation(string $note = 'Evaluation could not be completed.'): array
    {
        $skills = [];
        foreach ($this->skillWeights as $skill => $weight) {
            $skills[$skill] = ['score' => 0, 'weight' => $weight, 'evidence' => ''];
        }
        return [
            'overall_score'        => 0,
            'recommendation'       => 'not_recommended',
            'executive_summary'    => $note,
            'strengths'            => [],
            'weaknesses'           => [],
            'skills_analysis'      => $skills,
            'criteria_scores'      => [],
            'personality_analysis' => $this->normalizePersonality([]),
            'red_flags'            => [],
            'cv_match_notes'       => '',
            'language_analysis'    => ['detected' => 'en', 'fluency' => '', 'accent_notes' => null],
            'ai_tokens_used'       => 0,
        ];
    }

    // ==================================================================
    // Formatting helpers
    // ==================================================================

    private function formatTranscript(array $messages): string
    {
        $lines = [];
        $idx = 0;
        foreach ($messages as $m) {
            $role    = ($m['role'] ?? '') === 'ai' ? 'INTERVIEWER' : 'CANDIDATE';
            $content = trim((string) ($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $tag = '';
            if (!empty($m['skill_assessed'])) {
                $tag = ' [assessing: ' . $m['skill_assessed'] . ']';
            }
            $lines[] = sprintf('%s%s: %s', $role, $tag, $content);
            $idx++;
        }
        $text = implode("\n\n", $lines);
        // Keep the transcript within a safe size.
        return mb_strlen($text) > 20000 ? mb_substr($text, -20000) : $text;
    }

    private function formatCriteria(array $criteria): string
    {
        if (empty($criteria)) {
            return '(No explicit criteria configured; assess general role fit.)';
        }
        $lines = [];
        foreach ($criteria as $c) {
            $name = (string) ($c['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $weight = isset($c['weight']) ? rtrim(rtrim((string) $c['weight'], '0'), '.') : '';
            $target = isset($c['target_score']) ? rtrim(rtrim((string) $c['target_score'], '0'), '.') : '3';
            $desc   = trim((string) ($c['description'] ?? ''));
            $lines[] = sprintf('- %s (weight %s, target %s/5)%s', $name, $weight, $target, $desc !== '' ? ': ' . $desc : '');
        }
        return implode("\n", $lines);
    }

    private function formatWeights(): string
    {
        $lines = [];
        foreach ($this->skillWeights as $skill => $weight) {
            $lines[] = sprintf('- %s: %d%%', $skill, $weight);
        }
        return implode("\n", $lines);
    }

    private function formatCv($candidateCv): string
    {
        if (empty($candidateCv) || !is_array($candidateCv)) {
            return '(No CV analysis provided.)';
        }
        $parts = [];
        if (isset($candidateCv['match_score'])) {
            $parts[] = 'CV match score: ' . (int) $candidateCv['match_score'] . '/100';
        }
        if (!empty($candidateCv['experience_years_detected'])) {
            $parts[] = 'Detected experience: ' . $candidateCv['experience_years_detected'] . ' years';
        }
        if (!empty($candidateCv['skills_found'])) {
            $parts[] = 'Skills found: ' . implode(', ', (array) $candidateCv['skills_found']);
        }
        if (!empty($candidateCv['skills_missing'])) {
            $parts[] = 'Skills missing: ' . implode(', ', (array) $candidateCv['skills_missing']);
        }
        if (!empty($candidateCv['career_gaps'])) {
            $parts[] = 'Career gaps: ' . implode('; ', (array) $candidateCv['career_gaps']);
        }
        if (!empty($candidateCv['red_flags'])) {
            $parts[] = 'CV red flags: ' . implode('; ', array_map(
                static fn($f) => is_array($f) ? (string) ($f['flag'] ?? '') : (string) $f,
                (array) $candidateCv['red_flags']
            ));
        }
        if (!empty($candidateCv['overall_notes'])) {
            $parts[] = 'CV notes: ' . $candidateCv['overall_notes'];
        }
        return $parts ? implode("\n", $parts) : '(No CV analysis provided.)';
    }

    // ----- primitives -----

    private function clampScore(float $value, float $min, float $max): float|int
    {
        $value = max($min, min($max, $value));
        // Return int for the 0-100 scale, float for the 0-5 scale.
        return $max <= 5 ? round($value, 1) : (int) round($value);
    }

    private function stringArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $v) {
            if (is_scalar($v)) {
                $s = trim((string) $v);
                if ($s !== '') {
                    $out[] = $s;
                }
            }
        }
        return array_values($out);
    }

    private function clip(string $text, int $max): string
    {
        $text = trim($text);
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
    }

    private function key(string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }
}
