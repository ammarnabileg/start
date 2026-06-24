<?php

namespace App\Modules\AI;

/**
 * Analyzes a candidate CV against a job description using the LLM, with a
 * deterministic keyword-overlap fallback when AI is unavailable.
 */
class CVAnalyzer
{
    private OpenAIService $ai;

    public function __construct(?OpenAIService $ai = null)
    {
        $this->ai = $ai ?? new OpenAIService();
    }

    /**
     * Analyze a CV against a job description and weighted criteria.
     *
     * @param array<int,array{criterion_name?:string,weight?:mixed,description?:string}> $criteria
     * @return array{
     *   overall_match_score:int,
     *   skills_match:array<int,array{skill:string,found:bool,weight:float}>,
     *   strengths:array<int,string>,
     *   weaknesses:array<int,string>,
     *   experience_relevance:int,
     *   education_match:bool,
     *   summary:string,
     *   recommendation:string
     * }
     */
    public function analyze(string $cvText, string $jobDescription, array $criteria = []): array
    {
        $cv = mb_substr(trim($cvText), 0, 6000);

        $criteriaLines = [];
        foreach ($criteria as $c) {
            $name = trim((string) ($c['criterion_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $weight = (float) ($c['weight'] ?? 1);
            $desc   = trim((string) ($c['description'] ?? ''));
            $criteriaLines[] = '- ' . $name . ' (weight ' . $weight . ')' . ($desc !== '' ? ': ' . $desc : '');
        }
        $criteriaBlock = $criteriaLines === [] ? '(no explicit criteria provided)' : implode("\n", $criteriaLines);

        $system = 'You are an expert technical recruiter with 15 years of experience screening resumes. '
            . 'You objectively assess how well a candidate CV matches a job. '
            . 'You respond ONLY with a strict JSON object, no prose, no Markdown. '
            . 'The JSON must use exactly these keys: overall_match_score (integer 0-100), '
            . 'skills_match (array of {skill:string, found:boolean, weight:number}), '
            . 'strengths (array of strings), weaknesses (array of strings), '
            . 'experience_relevance (integer 0-100), education_match (boolean), '
            . 'summary (string, 2-4 sentences), recommendation (one of "invite","maybe","reject").';

        $user = "JOB DESCRIPTION:\n" . trim($jobDescription) . "\n\n"
            . "WEIGHTED EVALUATION CRITERIA:\n" . $criteriaBlock . "\n\n"
            . "CANDIDATE CV:\n" . $cv . "\n\n"
            . 'Score each listed criterion as a skill in skills_match (carry its weight). '
            . 'Be honest and calibrated. Return the JSON now.';

        $result = [];
        if ($this->ai->isConfigured()) {
            $messages = [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
                'feature' => 'cv_analysis',
            ];
            $result = $this->ai->chatJSON($messages);
        }

        if (!is_array($result) || $result === []) {
            return $this->heuristic($cvText, $jobDescription, $criteria);
        }

        return $this->normalize($result, $cvText, $jobDescription, $criteria);
    }

    /**
     * Coerce arbitrary AI output into the exact contract shape with sane defaults.
     *
     * @param array<string,mixed> $r
     * @param array<int,array<string,mixed>> $criteria
     */
    private function normalize(array $r, string $cvText, string $jobDescription, array $criteria): array
    {
        $score = $this->clampInt($r['overall_match_score'] ?? null, 0, 100, 50);

        $skills = [];
        if (isset($r['skills_match']) && is_array($r['skills_match'])) {
            foreach ($r['skills_match'] as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $name = trim((string) ($s['skill'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $skills[] = [
                    'skill'  => $name,
                    'found'  => $this->toBool($s['found'] ?? false),
                    'weight' => (float) ($s['weight'] ?? 1),
                ];
            }
        }
        if ($skills === [] && $criteria !== []) {
            // Backfill from the requested criteria so the UI always has rows.
            $cvLower = mb_strtolower($cvText);
            foreach ($criteria as $c) {
                $name = trim((string) ($c['criterion_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $skills[] = [
                    'skill'  => $name,
                    'found'  => str_contains($cvLower, mb_strtolower($name)),
                    'weight' => (float) ($c['weight'] ?? 1),
                ];
            }
        }

        $rec = strtolower(trim((string) ($r['recommendation'] ?? '')));
        if (!in_array($rec, ['invite', 'maybe', 'reject'], true)) {
            $rec = $score >= 70 ? 'invite' : ($score >= 45 ? 'maybe' : 'reject');
        }

        return [
            'overall_match_score'  => $score,
            'skills_match'         => $skills,
            'strengths'            => $this->stringList($r['strengths'] ?? []),
            'weaknesses'           => $this->stringList($r['weaknesses'] ?? []),
            'experience_relevance' => $this->clampInt($r['experience_relevance'] ?? null, 0, 100, $score),
            'education_match'      => $this->toBool($r['education_match'] ?? false),
            'summary'              => trim((string) ($r['summary'] ?? '')) ?: 'Automated analysis of the candidate against the role.',
            'recommendation'       => $rec,
        ];
    }

    /**
     * Deterministic keyword-overlap heuristic used when the AI is unavailable or
     * returns nothing usable.
     *
     * @param array<int,array<string,mixed>> $criteria
     */
    private function heuristic(string $cvText, string $jobDescription, array $criteria): array
    {
        $cvTokens  = $this->tokenize($cvText);
        $jobTokens = $this->tokenize($jobDescription);

        foreach ($criteria as $c) {
            foreach ($this->tokenize((string) ($c['criterion_name'] ?? '') . ' ' . (string) ($c['description'] ?? '')) as $t) {
                $jobTokens[$t] = true;
            }
        }

        $overlap = 0;
        $matched = [];
        foreach ($jobTokens as $tok => $_) {
            if (isset($cvTokens[$tok])) {
                $overlap++;
                $matched[$tok] = true;
            }
        }
        $denom = max(1, count($jobTokens));
        $ratio = $overlap / $denom;
        $score = (int) round(min(95, max(15, $ratio * 140)));

        // Build skill rows from criteria (or top job keywords) flagged by presence.
        $skills = [];
        if ($criteria !== []) {
            $cvLower = mb_strtolower($cvText);
            foreach ($criteria as $c) {
                $name = trim((string) ($c['criterion_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $skills[] = [
                    'skill'  => $name,
                    'found'  => str_contains($cvLower, mb_strtolower($name)),
                    'weight' => (float) ($c['weight'] ?? 1),
                ];
            }
        } else {
            $top = array_slice(array_keys($jobTokens), 0, 8);
            foreach ($top as $tok) {
                $skills[] = ['skill' => $tok, 'found' => isset($cvTokens[$tok]), 'weight' => 1.0];
            }
        }

        $strengths = [];
        foreach (array_slice(array_keys($matched), 0, 5) as $tok) {
            $strengths[] = 'Relevant experience with ' . $tok;
        }
        if ($strengths === []) {
            $strengths[] = 'CV submitted for review';
        }

        $missing = [];
        foreach ($jobTokens as $tok => $_) {
            if (!isset($cvTokens[$tok])) {
                $missing[] = $tok;
            }
            if (count($missing) >= 5) {
                break;
            }
        }
        $weaknesses = [];
        foreach ($missing as $tok) {
            $weaknesses[] = 'No clear evidence of ' . $tok;
        }
        if ($weaknesses === []) {
            $weaknesses[] = 'Limited automated insight without AI analysis';
        }

        $rec = $score >= 70 ? 'invite' : ($score >= 45 ? 'maybe' : 'reject');

        return [
            'overall_match_score'  => $score,
            'skills_match'         => $skills,
            'strengths'            => $strengths,
            'weaknesses'           => $weaknesses,
            'experience_relevance' => $score,
            'education_match'      => $ratio >= 0.3,
            'summary'              => 'Heuristic keyword match scored this candidate at ' . $score
                . '% based on overlap between the CV and the job requirements.',
            'recommendation'       => $rec,
        ];
    }

    /**
     * @return array<string,bool> set of significant lowercase tokens
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $parts = preg_split('/[^a-z0-9+#.]+/', $text) ?: [];
        $stop = $this->stopwords();
        $tokens = [];
        foreach ($parts as $p) {
            $p = trim($p, '.');
            if (strlen($p) < 3 || isset($stop[$p]) || ctype_digit($p)) {
                continue;
            }
            $tokens[$p] = true;
        }
        return $tokens;
    }

    /**
     * @return array<string,bool>
     */
    private function stopwords(): array
    {
        static $set = null;
        if ($set === null) {
            $words = ['the', 'and', 'for', 'with', 'you', 'our', 'are', 'will', 'this', 'that', 'have',
                'has', 'from', 'your', 'who', 'all', 'can', 'job', 'role', 'work', 'team', 'their',
                'they', 'them', 'about', 'into', 'such', 'must', 'should', 'would', 'able', 'across',
                'within', 'using', 'use', 'including', 'etc', 'years', 'year', 'experience', 'skills',
                'ability', 'strong', 'good', 'excellent', 'plus', 'preferred', 'required', 'requirements'];
            $set = array_fill_keys($words, true);
        }
        return $set;
    }

    private function clampInt($value, int $min, int $max, int $default): int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }
        return (int) max($min, min($max, (int) round((float) $value)));
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', 'yes', '1', 'y'], true);
        }
        return (bool) $value;
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
                // Tolerate {text:..} / {description:..} shapes.
                $text = trim((string) ($item['text'] ?? $item['description'] ?? $item['value'] ?? ''));
                if ($text !== '') {
                    $out[] = $text;
                }
            }
        }
        return $out;
    }
}
