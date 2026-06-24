<?php
declare(strict_types=1);

namespace Modules\AI;

/**
 * CandidateMatcher - Scores and ranks candidates against a job, and runs a
 * natural-language ("semantic") search over a talent pool.
 *
 *   - matchSingle():    deep match analysis for one candidate vs a job
 *   - rankCandidates():  score a batch of candidates and return them sorted
 *   - semanticSearch():  interpret a free-text query and return matching
 *                        candidates with a relevance score and reason
 */
class CandidateMatcher
{
    private OpenAIService $ai;

    public function __construct(?OpenAIService $ai = null)
    {
        $this->ai = $ai ?? new OpenAIService();
    }

    /**
     * Match a single candidate to a job using all available signals
     * (candidate profile, application/pipeline data, interview evaluation).
     *
     * @return array{match_score:int, recommendation:string, summary:string,
     *               strengths:string[], concerns:string[], skill_overlap:string[],
     *               skill_gaps:string[]}
     */
    public function matchSingle(array $candidate, array $application, array $evaluation, array $job): array
    {
        $system = <<<PROMPT
You are an expert recruiter matching a single candidate to a specific job. You
weigh CV/profile fit, interview performance and pipeline signals to produce an
objective match assessment. Base everything strictly on the provided data; do
not invent facts. match_score is an integer 0-100. recommendation is one of
"strong", "suitable", "possible", "not_recommended". Output ONLY JSON.
PROMPT;

        $user = $this->singleMatchPrompt($candidate, $application, $evaluation, $job);

        $result = $this->ai->chatJson(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            $this->singleSchema(),
            ['temperature' => 0.2, 'max_tokens' => 1200]
        );

        $this->ai->logUsage(
            (int) ($job['tenant_id'] ?? $candidate['tenant_id'] ?? 0),
            0,
            'candidate_match',
            ['model' => $result['model']] + $result['tokens'] + ['cost' => $result['cost']],
            'candidate',
            (int) ($candidate['id'] ?? 0)
        );

        return $this->normalizeSingle($result['data']);
    }

    /**
     * Score and rank a batch of candidates against a job in a single call.
     *
     * @param array $candidates List of candidate rows. Each may include
     *                          'application' and 'evaluation' sub-arrays.
     * @param array $job        Job row.
     *
     * @return array Sorted list (best first): each item is the candidate id,
     *               name, match_score, recommendation and reason.
     */
    public function rankCandidates(array $candidates, array $job): array
    {
        if (empty($candidates)) {
            return [];
        }

        $system = <<<PROMPT
You are an expert recruiter ranking multiple candidates for one job. For each
candidate, produce an integer match_score (0-100), a recommendation
("strong"|"suitable"|"possible"|"not_recommended") and a one-sentence reason
grounded in their data. Be consistent and comparative across candidates. Return
a JSON object with a "candidates" array. Reference each candidate by the "id"
given. Do not invent candidates or data. Output ONLY JSON.
PROMPT;

        $user = $this->rankPrompt($candidates, $job);

        $result = $this->ai->chatJson(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            $this->rankSchema(),
            ['temperature' => 0.2, 'max_tokens' => 3000]
        );

        $this->ai->logUsage(
            (int) ($job['tenant_id'] ?? 0),
            0,
            'candidate_ranking',
            ['model' => $result['model']] + $result['tokens'] + ['cost' => $result['cost']],
            'job',
            (int) ($job['id'] ?? 0)
        );

        return $this->normalizeRanking($result['data'], $candidates);
    }

    /**
     * Natural-language search across a set of candidates.
     *
     * @param string $query      e.g. "backend developers with leadership skills".
     * @param array  $candidates Candidate rows to search within.
     * @param int    $tenantId   Tenant id (usage logging).
     *
     * @return array Matching candidates (best first) with relevance_score and reason.
     */
    public function semanticSearch(string $query, array $candidates, int $tenantId): array
    {
        $query = trim($query);
        if ($query === '' || empty($candidates)) {
            return [];
        }

        $system = <<<PROMPT
You are a talent-search engine. Given a natural-language query and a list of
candidate profiles, identify which candidates best satisfy the query's intent
(skills, seniority, domain, traits, language, location, etc.). For each match,
return its id, a relevance_score (0-100) and a concise reason citing the
matching evidence. Exclude candidates that do not meaningfully match. Order by
relevance_score descending. Reference candidates by the given "id". Do not
invent candidates. Output ONLY JSON with a "matches" array.
PROMPT;

        $user = "Search query:\n\"{$query}\"\n\n=== CANDIDATES ===\n" . $this->formatCandidatesForSearch($candidates);

        $result = $this->ai->chatJson(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            $this->searchSchema(),
            ['temperature' => 0.2, 'max_tokens' => 2000]
        );

        $this->ai->logUsage(
            $tenantId,
            0,
            'semantic_search',
            ['model' => $result['model']] + $result['tokens'] + ['cost' => $result['cost']],
            'search',
            0
        );

        return $this->normalizeSearch($result['data'], $candidates);
    }

    // ==================================================================
    // Prompt builders
    // ==================================================================

    private function singleMatchPrompt(array $candidate, array $application, array $evaluation, array $job): string
    {
        $jobBlock  = $this->formatJob($job);
        $candBlock = $this->formatCandidate($candidate);

        $appBlock = '';
        if (!empty($application)) {
            $appBlock = "\n=== APPLICATION ===\n"
                . 'Stage: ' . ($application['stage'] ?? 'n/a') . "\n"
                . 'CV match score: ' . ($application['cv_match_score'] ?? 'n/a') . "\n";
            if (!empty($application['cv_analysis'])) {
                $appBlock .= 'CV analysis: ' . $this->jsonClip($application['cv_analysis'], 1500) . "\n";
            }
        }

        $evalBlock = '';
        if (!empty($evaluation)) {
            $evalBlock = "\n=== INTERVIEW EVALUATION ===\n"
                . 'Overall score: ' . ($evaluation['overall_score'] ?? 'n/a') . "\n"
                . 'Recommendation: ' . ($evaluation['recommendation'] ?? 'n/a') . "\n"
                . 'Summary: ' . ($evaluation['executive_summary'] ?? '') . "\n"
                . 'Strengths: ' . $this->listClip($evaluation['strengths'] ?? []) . "\n"
                . 'Weaknesses: ' . $this->listClip($evaluation['weaknesses'] ?? []) . "\n";
        }

        return <<<PROMPT
{$jobBlock}

{$candBlock}
{$appBlock}{$evalBlock}

=== TASK ===
Assess how well this candidate matches the job. Return the structured JSON.
PROMPT;
    }

    private function rankPrompt(array $candidates, array $job): string
    {
        $jobBlock = $this->formatJob($job);
        $lines = [];
        foreach ($candidates as $c) {
            $id = (int) ($c['id'] ?? 0);
            $lines[] = "--- Candidate id={$id} ---\n" . $this->formatCandidate($c, true) . $this->formatEmbeddedSignals($c);
        }
        $candidateBlock = implode("\n\n", $lines);

        return <<<PROMPT
{$jobBlock}

=== CANDIDATES TO RANK ===
{$candidateBlock}

=== TASK ===
Score and rank every candidate above for this job. Return a JSON object with a
"candidates" array containing {id, match_score, recommendation, reason} for each.
PROMPT;
    }

    // ==================================================================
    // Schemas
    // ==================================================================

    private function singleSchema(): array
    {
        return [
            'name'   => 'candidate_match',
            'strict' => false,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'match_score'    => ['type' => 'integer'],
                    'recommendation' => ['type' => 'string'],
                    'summary'        => ['type' => 'string'],
                    'strengths'      => ['type' => 'array', 'items' => ['type' => 'string']],
                    'concerns'       => ['type' => 'array', 'items' => ['type' => 'string']],
                    'skill_overlap'  => ['type' => 'array', 'items' => ['type' => 'string']],
                    'skill_gaps'     => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['match_score', 'recommendation', 'summary'],
            ],
        ];
    }

    private function rankSchema(): array
    {
        return [
            'name'   => 'candidate_ranking',
            'strict' => false,
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'candidates' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'             => ['type' => 'integer'],
                                'match_score'    => ['type' => 'integer'],
                                'recommendation' => ['type' => 'string'],
                                'reason'         => ['type' => 'string'],
                            ],
                            'required' => ['id', 'match_score'],
                        ],
                    ],
                ],
                'required' => ['candidates'],
            ],
        ];
    }

    private function searchSchema(): array
    {
        return [
            'name'   => 'semantic_search',
            'strict' => false,
            'schema' => [
                'type'       => 'object',
                'properties' => [
                    'matches' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'              => ['type' => 'integer'],
                                'relevance_score' => ['type' => 'integer'],
                                'reason'          => ['type' => 'string'],
                            ],
                            'required' => ['id', 'relevance_score'],
                        ],
                    ],
                ],
                'required' => ['matches'],
            ],
        ];
    }

    // ==================================================================
    // Normalizers
    // ==================================================================

    private function normalizeSingle(array $data): array
    {
        $score = max(0, min(100, (int) round((float) ($data['match_score'] ?? 0))));
        return [
            'match_score'    => $score,
            'recommendation' => $this->normalizeRecommendation($data['recommendation'] ?? '', $score),
            'summary'        => (string) ($data['summary'] ?? ''),
            'strengths'      => $this->stringArray($data['strengths'] ?? []),
            'concerns'       => $this->stringArray($data['concerns'] ?? []),
            'skill_overlap'  => $this->stringArray($data['skill_overlap'] ?? []),
            'skill_gaps'     => $this->stringArray($data['skill_gaps'] ?? []),
        ];
    }

    private function normalizeRanking(array $data, array $candidates): array
    {
        $byId = $this->indexById($candidates);
        $rows = [];

        foreach ((array) ($data['candidates'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = (int) ($item['id'] ?? 0);
            $score = max(0, min(100, (int) round((float) ($item['match_score'] ?? 0))));
            $cand = $byId[$id] ?? [];
            $rows[] = [
                'id'             => $id,
                'name'           => (string) ($cand['full_name'] ?? ($item['name'] ?? 'Unknown')),
                'match_score'    => $score,
                'recommendation' => $this->normalizeRecommendation($item['recommendation'] ?? '', $score),
                'reason'         => (string) ($item['reason'] ?? ''),
            ];
        }

        usort($rows, static fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        return $rows;
    }

    private function normalizeSearch(array $data, array $candidates): array
    {
        $byId = $this->indexById($candidates);
        $rows = [];

        foreach ((array) ($data['matches'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = (int) ($item['id'] ?? 0);
            if (!isset($byId[$id])) {
                continue;
            }
            $cand = $byId[$id];
            $rows[] = [
                'id'              => $id,
                'name'            => (string) ($cand['full_name'] ?? 'Unknown'),
                'email'           => (string) ($cand['email'] ?? ''),
                'relevance_score' => max(0, min(100, (int) round((float) ($item['relevance_score'] ?? 0)))),
                'reason'          => (string) ($item['reason'] ?? ''),
            ];
        }

        usort($rows, static fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);
        return $rows;
    }

    private function normalizeRecommendation(string $value, int $score): string
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['strong', 'suitable', 'possible', 'not_recommended'], true)) {
            return $value;
        }
        return match (true) {
            $score >= 82 => 'strong',
            $score >= 68 => 'suitable',
            $score >= 50 => 'possible',
            default      => 'not_recommended',
        };
    }

    // ==================================================================
    // Formatting helpers
    // ==================================================================

    private function formatJob(array $job): string
    {
        $title  = (string) ($job['title'] ?? 'Role');
        $sen    = (string) ($job['seniority'] ?? '');
        $desc   = $this->clip((string) ($job['description'] ?? ''), 1800);
        $req    = $this->listClip($job['requirements'] ?? '', 1500);
        return "=== JOB ===\nTitle: {$title}\nSeniority: {$sen}\nDescription: {$desc}\nRequirements: {$req}";
    }

    private function formatCandidate(array $c, bool $compact = false): string
    {
        $name   = (string) ($c['full_name'] ?? 'Candidate');
        $exp    = $c['years_experience'] ?? 'n/a';
        $skills = $this->listClip($c['skills'] ?? '', 800);
        $summary = $this->clip((string) ($c['summary'] ?? ''), $compact ? 500 : 1200);
        $location = (string) ($c['location'] ?? '');
        $langs  = $this->listClip($c['languages_spoken'] ?? '', 300);
        $expected = $c['expected_salary'] ?? '';
        $currency = (string) ($c['salary_currency'] ?? '');

        $lines = [
            ($compact ? '' : "=== CANDIDATE ===\n") . 'Name: ' . $name,
            'Years experience: ' . $exp,
            'Skills: ' . $skills,
        ];
        if ($location !== '') { $lines[] = 'Location: ' . $location; }
        if ($langs !== '' && $langs !== 'n/a') { $lines[] = 'Languages: ' . $langs; }
        if ($expected !== '' && $expected !== null) { $lines[] = 'Expected salary: ' . $currency . ' ' . $expected; }
        if ($summary !== '') { $lines[] = 'Summary: ' . $summary; }

        return implode("\n", $lines);
    }

    /**
     * Append interview/match signals embedded directly on the candidate row.
     */
    private function formatEmbeddedSignals(array $c): string
    {
        $out = '';
        $eval = $c['evaluation'] ?? null;
        if (is_array($eval) && !empty($eval)) {
            $out .= "\nInterview overall score: " . ($eval['overall_score'] ?? 'n/a');
            $out .= "\nInterview recommendation: " . ($eval['recommendation'] ?? 'n/a');
            if (!empty($eval['executive_summary'])) {
                $out .= "\nInterview summary: " . $this->clip((string) $eval['executive_summary'], 500);
            }
        } elseif (isset($c['avg_match_score']) || isset($c['avg_skill_score'])) {
            $out .= "\nAvg match score: " . ($c['avg_match_score'] ?? 'n/a');
            $out .= ' | Avg skill score: ' . ($c['avg_skill_score'] ?? 'n/a');
        }
        return $out;
    }

    private function formatCandidatesForSearch(array $candidates): string
    {
        $lines = [];
        foreach ($candidates as $c) {
            $id = (int) ($c['id'] ?? 0);
            $lines[] = "--- id={$id} ---\n" . $this->formatCandidate($c, true);
        }
        $text = implode("\n\n", $lines);
        return mb_strlen($text) > 22000 ? mb_substr($text, 0, 22000) : $text;
    }

    private function indexById(array $candidates): array
    {
        $byId = [];
        foreach ($candidates as $c) {
            $byId[(int) ($c['id'] ?? 0)] = $c;
        }
        return $byId;
    }

    // ----- primitives -----

    private function stringArray($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $value)));
        }
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
        return array_values(array_unique($out));
    }

    private function listClip($value, int $max = 1000): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (is_array($value)) {
            $value = implode(', ', array_map(
                static fn($v) => is_scalar($v) ? (string) $v : json_encode($v),
                $value
            ));
        }
        $value = trim((string) $value);
        if ($value === '') {
            return 'n/a';
        }
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) . '…' : $value;
    }

    private function jsonClip($value, int $max): string
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?? $value;
        }
        $json = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        return mb_strlen($json) > $max ? mb_substr($json, 0, $max) . '…' : $json;
    }

    private function clip(string $text, int $max): string
    {
        $text = trim($text);
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
    }
}
