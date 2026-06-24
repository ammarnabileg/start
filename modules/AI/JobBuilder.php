<?php
declare(strict_types=1);

namespace Modules\AI;

/**
 * JobBuilder - AI-powered job description generator.
 *
 * Given a title, seniority and industry (plus optional context such as a
 * salary range, location, must-have skills or company blurb) it produces a
 * complete, ready-to-edit job posting: description, requirements,
 * responsibilities, benefits, a salary suggestion, suggested evaluation
 * criteria (with weights/targets) and suggested interview questions.
 */
class JobBuilder
{
    private OpenAIService $ai;

    public function __construct(?OpenAIService $ai = null)
    {
        $this->ai = $ai ?? new OpenAIService();
    }

    /**
     * Generate a job posting.
     *
     * @param string $title     Job title (e.g. "Senior Backend Engineer").
     * @param string $seniority intern|junior|mid|senior|lead|manager|director|executive.
     * @param string $industry  Industry / sector (e.g. "Fintech").
     * @param array  $context   Optional: company_name, location, work_type,
     *                          currency, salary_min, salary_max, must_have_skills,
     *                          notes, language, tenant_id, user_id.
     *
     * @return array Documented job structure.
     */
    public function generate(string $title, string $seniority, string $industry, array $context = []): array
    {
        $title = trim($title) !== '' ? trim($title) : 'Untitled Role';

        $system = $this->systemPrompt();
        $user   = $this->userPrompt($title, $seniority, $industry, $context);

        $result = $this->ai->chatJson(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            $this->schema(),
            ['temperature' => 0.6, 'max_tokens' => 3000]
        );

        $this->ai->logUsage(
            (int) ($context['tenant_id'] ?? 0),
            (int) ($context['user_id'] ?? 0),
            'job_builder',
            ['model' => $result['model']] + $result['tokens'] + ['cost' => $result['cost']],
            'job',
            0
        );

        return $this->normalize($result['data'], $context);
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are an expert recruitment copywriter and compensation analyst. You write
compelling, inclusive, modern job postings that attract strong candidates while
being precise about what the role actually requires.

Guidelines:
- The description should be engaging and specific (2-4 short paragraphs): what
  the team/company does, why the role matters, and what success looks like.
- requirements: 5-9 concrete, screenable items appropriate to the seniority.
  Distinguish genuine must-haves from nice-to-haves; avoid unnecessary degree
  or years requirements that exclude good candidates.
- responsibilities: 5-8 action-oriented bullet points.
- benefits: 5-8 attractive, realistic benefits.
- salary_suggestion: a sensible market range for the role/seniority/industry in
  the requested currency (default USD). Provide integer min and max.
- suggested_criteria: 5-8 evaluation criteria for screening interviews. Each has
  a name, an integer weight, and a target_score on a 0-5 scale. The weights MUST
  sum to exactly 100.
- suggested_questions: 6-10 interview questions. Each has the question text, a
  skill tag (e.g. "technical", "communication", "leadership", "problem_solving",
  "culture"), and a difficulty ("easy", "medium", "hard").
- Use inclusive, bias-free language. Avoid gendered terms and culturally narrow
  idioms. Write in English.
- Output ONLY the JSON object defined by the schema. No markdown, no prose.
PROMPT;
    }

    private function userPrompt(string $title, string $seniority, string $industry, array $context): string
    {
        $company   = (string) ($context['company_name'] ?? '');
        $location  = (string) ($context['location'] ?? '');
        $workType  = (string) ($context['work_type'] ?? '');
        $currency  = (string) ($context['currency'] ?? 'USD');
        $salaryMin = $context['salary_min'] ?? null;
        $salaryMax = $context['salary_max'] ?? null;
        $notes     = trim((string) ($context['notes'] ?? ''));

        $mustHave = $context['must_have_skills'] ?? [];
        if (is_string($mustHave)) {
            $mustHave = array_filter(array_map('trim', explode(',', $mustHave)));
        }
        $mustHaveTxt = !empty($mustHave) ? implode(', ', (array) $mustHave) : 'Not specified';

        $salaryHint = ($salaryMin !== null || $salaryMax !== null)
            ? sprintf('Target salary range hint: %s %s - %s', $currency, $this->num($salaryMin), $this->num($salaryMax))
            : 'No salary range provided; suggest a market-appropriate range.';

        $lines = [
            'Title: ' . $title,
            'Seniority: ' . ($seniority !== '' ? $seniority : 'mid'),
            'Industry: ' . ($industry !== '' ? $industry : 'General'),
            'Currency: ' . $currency,
        ];
        if ($company !== '')  { $lines[] = 'Company: ' . $company; }
        if ($location !== '') { $lines[] = 'Location: ' . $location; }
        if ($workType !== '') { $lines[] = 'Work type: ' . $workType; }
        $lines[] = $salaryHint;
        $lines[] = 'Must-have skills: ' . $mustHaveTxt;
        if ($notes !== '') { $lines[] = 'Additional context: ' . $notes; }

        $body = implode("\n", $lines);

        return <<<PROMPT
Create a complete job posting for the following role and return it as the
structured JSON defined by the schema.

{$body}
PROMPT;
    }

    private function schema(): array
    {
        return [
            'name'   => 'job_posting',
            'strict' => false,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'description'      => ['type' => 'string'],
                    'requirements'     => ['type' => 'array', 'items' => ['type' => 'string']],
                    'responsibilities' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'benefits'         => ['type' => 'array', 'items' => ['type' => 'string']],
                    'salary_suggestion' => [
                        'type'       => 'object',
                        'properties' => [
                            'min'      => ['type' => 'number'],
                            'max'      => ['type' => 'number'],
                            'currency' => ['type' => 'string'],
                        ],
                    ],
                    'suggested_criteria' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'         => ['type' => 'string'],
                                'weight'       => ['type' => 'number'],
                                'target_score' => ['type' => 'number'],
                            ],
                        ],
                    ],
                    'suggested_questions' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'question'   => ['type' => 'string'],
                                'skill'      => ['type' => 'string'],
                                'difficulty' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'required' => ['description', 'requirements', 'responsibilities', 'benefits'],
            ],
        ];
    }

    private function normalize(array $data, array $context): array
    {
        $currency = (string) ($context['currency'] ?? 'USD');

        $salary = is_array($data['salary_suggestion'] ?? null) ? $data['salary_suggestion'] : [];
        $min = (int) round((float) ($salary['min'] ?? ($context['salary_min'] ?? 0)));
        $max = (int) round((float) ($salary['max'] ?? ($context['salary_max'] ?? 0)));
        if ($max > 0 && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        $criteria = $this->normalizeCriteria($data['suggested_criteria'] ?? []);
        $questions = $this->normalizeQuestions($data['suggested_questions'] ?? []);

        return [
            'description'      => (string) ($data['description'] ?? ''),
            'requirements'     => $this->stringArray($data['requirements'] ?? []),
            'responsibilities' => $this->stringArray($data['responsibilities'] ?? []),
            'benefits'         => $this->stringArray($data['benefits'] ?? []),
            'salary_suggestion' => [
                'min'      => $min,
                'max'      => $max,
                'currency' => (string) ($salary['currency'] ?? $currency),
            ],
            'suggested_criteria'  => $criteria,
            'suggested_questions' => $questions,
        ];
    }

    /**
     * Clamp criteria weights and rescale them to sum to exactly 100.
     */
    private function normalizeCriteria($raw): array
    {
        $items = [];
        foreach ((array) $raw as $c) {
            if (!is_array($c)) {
                continue;
            }
            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $items[] = [
                'name'         => $name,
                'weight'       => max(0.0, (float) ($c['weight'] ?? 0)),
                'target_score' => max(0.0, min(5.0, (float) ($c['target_score'] ?? 3))),
            ];
        }

        if (empty($items)) {
            return [];
        }

        $total = array_sum(array_column($items, 'weight'));
        if ($total <= 0) {
            // Distribute evenly.
            $even = (int) round(100 / count($items));
            foreach ($items as &$it) {
                $it['weight'] = $even;
            }
            unset($it);
        } else {
            foreach ($items as &$it) {
                $it['weight'] = (int) round($it['weight'] / $total * 100);
            }
            unset($it);
        }

        // Fix rounding drift so weights sum to exactly 100.
        $sum = array_sum(array_column($items, 'weight'));
        $diff = 100 - $sum;
        if ($diff !== 0) {
            $items[0]['weight'] = max(0, $items[0]['weight'] + $diff);
        }

        foreach ($items as &$it) {
            $it['target_score'] = round($it['target_score'], 1);
        }
        unset($it);

        return $items;
    }

    private function normalizeQuestions($raw): array
    {
        $out = [];
        foreach ((array) $raw as $q) {
            if (!is_array($q)) {
                continue;
            }
            $text = trim((string) ($q['question'] ?? ''));
            if ($text === '') {
                continue;
            }
            $difficulty = strtolower((string) ($q['difficulty'] ?? 'medium'));
            if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                $difficulty = 'medium';
            }
            $out[] = [
                'question'   => $text,
                'skill'      => (string) ($q['skill'] ?? 'general'),
                'difficulty' => $difficulty,
            ];
        }
        return $out;
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

    private function num($value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }
        return is_numeric($value) ? (string) (int) $value : (string) $value;
    }
}
