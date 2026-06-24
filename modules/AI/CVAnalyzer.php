<?php
declare(strict_types=1);

namespace Modules\AI;

/**
 * CVAnalyzer - Analyzes a candidate CV against a specific job using the LLM
 * and returns a structured, machine-readable assessment.
 *
 * The output is a fixed JSON shape (see analyze()) suitable for storing in
 * applications.cv_analysis and surfacing a CV match score in the pipeline.
 */
class CVAnalyzer
{
    private OpenAIService $ai;

    public function __construct(?OpenAIService $ai = null)
    {
        $this->ai = $ai ?? new OpenAIService();
    }

    /**
     * Analyze a CV against a job.
     *
     * @param string $cvText    Extracted plain text of the CV.
     * @param array  $job       Job row (title, description, requirements, ...).
     * @param array  $candidate Candidate row (full_name, expected_salary, ...).
     * @param int    $tenantId  Tenant id (for usage logging).
     * @param int    $userId    Acting user id (optional, for usage logging).
     *
     * @return array Structured analysis (always contains the documented keys).
     */
    public function analyze(string $cvText, array $job, array $candidate, int $tenantId, int $userId = 0): array
    {
        $cvText = trim($cvText);
        if ($cvText === '') {
            return $this->emptyResult('No CV text was provided for analysis.');
        }

        // Guard against very large CVs blowing the context window.
        $cvText = mb_substr($cvText, 0, 24000);

        $system = $this->systemPrompt();
        $user   = $this->userPrompt($cvText, $job, $candidate);

        $result = $this->ai->chatJson(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            $this->schema(),
            ['temperature' => 0.2, 'max_tokens' => 2500]
        );

        $this->ai->logUsage(
            $tenantId,
            $userId,
            'cv_analysis',
            ['model' => $result['model']] + $result['tokens'] + ['cost' => $result['cost']],
            'candidate',
            (int) ($candidate['id'] ?? 0)
        );

        return $this->normalize($result['data']);
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are an expert technical recruiter and talent assessor with 15+ years of
experience screening resumes across engineering, sales, operations and
leadership roles. You evaluate a candidate's CV strictly against the
requirements of a specific job and produce an objective, evidence-based
assessment.

Operating rules:
- Be rigorous and skeptical. Reward concrete, verifiable achievements over
  buzzwords. Do not be swayed by formatting or self-praise.
- Base every judgement ONLY on what the CV actually states. Never invent
  experience, skills, employers or dates that are not present in the text.
- A "skill found" means there is explicit or strongly implied evidence in the
  CV. A "skill missing" means a job requirement that has no supporting
  evidence in the CV.
- match_score is an integer 0-100 reflecting overall fit for THIS job:
  90-100 exceptional, 75-89 strong, 60-74 moderate, 40-59 weak, <40 poor.
- experience_years_detected is your best estimate of total relevant
  professional experience in whole years derived from the work history.
- education_relevance is one of: "high", "medium", "low", "none".
- salary_alignment is one of: "below_range", "within_range", "above_range",
  "unknown" — compare the candidate's expected salary (if given) to the job's
  salary range.
- career_gaps lists employment gaps of 6+ months you can infer from dates,
  each as a short string (e.g. "Jan 2022 - Sep 2022 (8 months)").
- red_flags lists objective concerns (job hopping, unexplained gaps,
  inflated/contradictory claims, missing critical requirements). Use an empty
  array when there are none. Do not fabricate red flags.
- strengths and weaknesses must be specific and tied to job requirements.
- Output ONLY the JSON object defined by the schema. No prose, no markdown.
PROMPT;
    }

    private function userPrompt(string $cvText, array $job, array $candidate): string
    {
        $title        = (string) ($job['title'] ?? 'Unspecified role');
        $seniority    = (string) ($job['seniority'] ?? 'unspecified');
        $description  = $this->clip((string) ($job['description'] ?? ''), 4000);
        $requirements = $this->clip($this->stringifyList($job['requirements'] ?? ''), 3000);
        $responsibilities = $this->clip($this->stringifyList($job['responsibilities'] ?? ''), 2000);

        $salaryMin = $job['salary_min'] ?? null;
        $salaryMax = $job['salary_max'] ?? null;
        $currency  = (string) ($job['currency'] ?? 'USD');
        $salaryRange = ($salaryMin !== null || $salaryMax !== null)
            ? sprintf('%s %s - %s', $currency, $this->num($salaryMin), $this->num($salaryMax))
            : 'Not specified';

        $candName     = (string) ($candidate['full_name'] ?? 'Candidate');
        $candExpected = $candidate['expected_salary'] ?? null;
        $candCurrency = (string) ($candidate['salary_currency'] ?? $currency);
        $expectedSalary = $candExpected !== null
            ? sprintf('%s %s', $candCurrency, $this->num($candExpected))
            : 'Not provided';
        $candExperience = $candidate['years_experience'] ?? null;

        return <<<PROMPT
=== JOB ===
Title: {$title}
Seniority: {$seniority}
Salary range: {$salaryRange}

Description:
{$description}

Requirements:
{$requirements}

Responsibilities:
{$responsibilities}

=== CANDIDATE PROFILE ===
Name: {$candName}
Expected salary: {$expectedSalary}
Self-reported years of experience: {$this->num($candExperience)}

=== CANDIDATE CV (extracted text) ===
{$cvText}

=== TASK ===
Assess how well this candidate fits the job above. Return the structured JSON
assessment exactly as specified by the schema.
PROMPT;
    }

    /**
     * JSON schema describing the expected analysis structure.
     */
    private function schema(): array
    {
        return [
            'name'   => 'cv_analysis',
            'strict' => false,
            'schema' => [
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'match_score'              => ['type' => 'integer'],
                    'skills_found'             => ['type' => 'array', 'items' => ['type' => 'string']],
                    'skills_missing'           => ['type' => 'array', 'items' => ['type' => 'string']],
                    'experience_match'         => ['type' => 'boolean'],
                    'experience_years_detected' => ['type' => 'number'],
                    'strengths'                => ['type' => 'array', 'items' => ['type' => 'string']],
                    'weaknesses'               => ['type' => 'array', 'items' => ['type' => 'string']],
                    'education_relevance'      => ['type' => 'string'],
                    'companies'                => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'name'  => ['type' => 'string'],
                                'role'  => ['type' => 'string'],
                                'years' => ['type' => 'number'],
                            ],
                        ],
                    ],
                    'career_gaps'      => ['type' => 'array', 'items' => ['type' => 'string']],
                    'salary_alignment' => ['type' => 'string'],
                    'overall_notes'    => ['type' => 'string'],
                    'red_flags'        => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['match_score', 'skills_found', 'skills_missing', 'overall_notes'],
            ],
        ];
    }

    /**
     * Coerce/clamp the model output into the documented shape with safe defaults.
     */
    private function normalize(array $data): array
    {
        $score = (int) round((float) ($data['match_score'] ?? 0));
        $score = max(0, min(100, $score));

        $education = strtolower((string) ($data['education_relevance'] ?? 'none'));
        if (!in_array($education, ['high', 'medium', 'low', 'none'], true)) {
            $education = 'none';
        }

        $salary = strtolower((string) ($data['salary_alignment'] ?? 'unknown'));
        if (!in_array($salary, ['below_range', 'within_range', 'above_range', 'unknown'], true)) {
            $salary = 'unknown';
        }

        $companies = [];
        foreach ((array) ($data['companies'] ?? []) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $companies[] = [
                'name'  => (string) ($c['name'] ?? ''),
                'role'  => (string) ($c['role'] ?? ''),
                'years' => (float) ($c['years'] ?? 0),
            ];
        }

        return [
            'match_score'               => $score,
            'skills_found'              => $this->stringArray($data['skills_found'] ?? []),
            'skills_missing'            => $this->stringArray($data['skills_missing'] ?? []),
            'experience_match'          => (bool) ($data['experience_match'] ?? false),
            'experience_years_detected' => (float) ($data['experience_years_detected'] ?? 0),
            'strengths'                 => $this->stringArray($data['strengths'] ?? []),
            'weaknesses'                => $this->stringArray($data['weaknesses'] ?? []),
            'education_relevance'       => $education,
            'companies'                 => $companies,
            'career_gaps'               => $this->stringArray($data['career_gaps'] ?? []),
            'salary_alignment'          => $salary,
            'overall_notes'             => (string) ($data['overall_notes'] ?? ''),
            'red_flags'                 => $this->stringArray($data['red_flags'] ?? []),
        ];
    }

    private function emptyResult(string $note): array
    {
        return [
            'match_score'               => 0,
            'skills_found'              => [],
            'skills_missing'            => [],
            'experience_match'          => false,
            'experience_years_detected' => 0,
            'strengths'                 => [],
            'weaknesses'                => [],
            'education_relevance'       => 'none',
            'companies'                 => [],
            'career_gaps'               => [],
            'salary_alignment'          => 'unknown',
            'overall_notes'             => $note,
            'red_flags'                 => [],
        ];
    }

    // ----- small formatting helpers ------------------------------------

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
        return array_values(array_unique($out));
    }

    private function stringifyList($value): string
    {
        if (is_array($value)) {
            return implode("\n", array_map(static fn($v) => '- ' . (is_scalar($v) ? (string) $v : json_encode($v)), $value));
        }
        return (string) $value;
    }

    private function clip(string $text, int $max): string
    {
        $text = trim($text);
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
    }

    private function num($value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }
        return is_numeric($value) ? rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.') : (string) $value;
    }
}
