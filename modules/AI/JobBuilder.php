<?php

namespace App\Modules\AI;

/**
 * Generates complete job postings, scoring criteria, and interview question
 * banks from a short brief. Every method has a deterministic fallback so the
 * builder still produces usable output without an API key.
 */
class JobBuilder
{
    private const JOB_TYPES = ['full-time', 'part-time', 'contract', 'remote', 'internship'];

    private OpenAIService $ai;

    public function __construct(?OpenAIService $ai = null)
    {
        $this->ai = $ai ?? new OpenAIService();
    }

    /**
     * Turn a short brief into a complete job posting.
     *
     * @param array<string,mixed> $company
     * @return array<string,mixed>
     */
    public function buildFromPrompt(string $prompt, array $company = []): array
    {
        $prompt = trim($prompt);
        $companyName = trim((string) ($company['name'] ?? $company['company_name'] ?? ''));
        $industry    = trim((string) ($company['industry'] ?? ''));
        $location    = trim((string) ($company['location'] ?? ''));

        $result = [];
        if ($this->ai->isConfigured() && $prompt !== '') {
            $context = $companyName !== '' ? "Company: {$companyName}. " : '';
            $context .= $industry !== '' ? "Industry: {$industry}. " : '';
            $context .= $location !== '' ? "Default location: {$location}. " : '';

            $system = 'You are an expert recruitment copywriter and talent strategist. Given a short brief you produce '
                . 'a complete, professional, inclusive job posting. Respond ONLY with a strict JSON object (no Markdown). '
                . 'Required keys: title (string), description (string, rich multi-paragraph with an intro, '
                . 'responsibilities, and what success looks like; use \\n\\n between paragraphs), requirements (string, '
                . 'a newline-separated bulleted list using "- " prefixes), department (string), location (string), '
                . 'job_type (one of "full-time","part-time","contract","remote","internship"), salary_min (number), '
                . 'salary_max (number), currency (3-letter code), ai_criteria (array of 5-8 {criterion_name, weight 1-5, '
                . 'description}), question_bank (array of exactly 20 interview question strings).';

            $user = $context . "\n\nBRIEF: " . $prompt . "\n\nGenerate the full job posting JSON now.";

            $result = $this->ai->chatJSON([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
                'feature' => 'job_builder',
            ]);
        }

        if (!is_array($result) || $result === []) {
            return $this->fallbackJob($prompt, $companyName, $location);
        }

        return $this->normalizeJob($result, $prompt, $location);
    }

    /**
     * Generate 5-8 weighted scoring criteria for a job description.
     *
     * @return array<int,array{criterion_name:string,weight:int,description:string}>
     */
    public function generateCriteria(string $jobDescription): array
    {
        $jobDescription = trim($jobDescription);
        $result = [];
        if ($this->ai->isConfigured() && $jobDescription !== '') {
            $system = 'You are an expert hiring manager. From a job description you derive the key evaluation criteria '
                . 'used to score candidates. Respond ONLY with a strict JSON object with a single key "criteria" whose '
                . 'value is an array of 5 to 8 objects {criterion_name (string), weight (integer 1-5, 5 = most '
                . 'important), description (string)}.';
            $user = "JOB DESCRIPTION:\n" . mb_substr($jobDescription, 0, 4000) . "\n\nReturn the criteria JSON now.";
            $result = $this->ai->chatJSON([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
                'feature' => 'job_builder',
            ]);
        }

        $list = $this->extractList($result, ['criteria', 'ai_criteria', 'items']);
        $criteria = $this->normalizeCriteria($list);

        if ($criteria === []) {
            return $this->fallbackCriteria();
        }
        return $criteria;
    }

    /**
     * Generate exactly 20 interview questions for a job.
     *
     * @param array<int,array<string,mixed>> $criteria
     * @return array<int,string>
     */
    public function generateQuestionBank(string $jobDescription, array $criteria = []): array
    {
        $jobDescription = trim($jobDescription);
        $result = [];
        if ($this->ai->isConfigured() && $jobDescription !== '') {
            $critNames = [];
            foreach ($criteria as $c) {
                $n = trim((string) ($c['criterion_name'] ?? ''));
                if ($n !== '') {
                    $critNames[] = $n;
                }
            }
            $critBlock = $critNames === [] ? '' : "\n\nFocus areas: " . implode(', ', $critNames) . '.';

            $system = 'You are an expert interviewer. Produce a balanced bank of 20 high-quality interview questions '
                . 'mixing behavioral, situational, technical, and motivational styles for the given role. Respond ONLY '
                . 'with a strict JSON object with a single key "questions" whose value is an array of exactly 20 '
                . 'question strings.';
            $user = "JOB DESCRIPTION:\n" . mb_substr($jobDescription, 0, 4000) . $critBlock . "\n\nReturn the questions JSON now.";
            $result = $this->ai->chatJSON([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
                'feature' => 'job_builder',
            ]);
        }

        $list = $this->extractList($result, ['questions', 'question_bank', 'items']);
        $questions = [];
        foreach ($list as $q) {
            if (is_string($q)) {
                $q = trim($q);
            } elseif (is_array($q)) {
                $q = trim((string) ($q['question'] ?? $q['text'] ?? ''));
            } else {
                continue;
            }
            if ($q !== '') {
                $questions[] = $q;
            }
        }

        return $this->ensureTwenty($questions);
    }

    /**
     * Suggest improvements to a job description.
     *
     * @return array<int,string>
     */
    public function suggestImprovements(string $jobDescription): array
    {
        $jobDescription = trim($jobDescription);
        $result = [];
        if ($this->ai->isConfigured() && $jobDescription !== '') {
            $system = 'You are an expert recruitment copy editor focused on clarity, inclusivity, and candidate appeal. '
                . 'Review the job description and suggest concrete improvements. Respond ONLY with a strict JSON object '
                . 'with a single key "suggestions" whose value is an array of short, actionable suggestion strings.';
            $user = "JOB DESCRIPTION:\n" . mb_substr($jobDescription, 0, 4000) . "\n\nReturn the suggestions JSON now.";
            $result = $this->ai->chatJSON([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
                'feature' => 'job_builder',
            ]);
        }

        $list = $this->extractList($result, ['suggestions', 'improvements', 'items']);
        $out = [];
        foreach ($list as $s) {
            if (is_string($s)) {
                $s = trim($s);
            } elseif (is_array($s)) {
                $s = trim((string) ($s['suggestion'] ?? $s['text'] ?? $s['description'] ?? ''));
            } else {
                continue;
            }
            if ($s !== '') {
                $out[] = $s;
            }
        }

        if ($out === []) {
            return $this->fallbackImprovements();
        }
        return $out;
    }

    // ----------------------------------------------------------------------
    // Normalization helpers
    // ----------------------------------------------------------------------

    /**
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private function normalizeJob(array $r, string $prompt, string $defaultLocation): array
    {
        $title = trim((string) ($r['title'] ?? ''));
        if ($title === '') {
            $title = $prompt !== '' ? ucwords(mb_substr($prompt, 0, 80)) : 'Untitled Role';
        }

        $jobType = strtolower(trim((string) ($r['job_type'] ?? 'full-time')));
        $jobType = str_replace([' ', '_'], '-', $jobType);
        if (!in_array($jobType, self::JOB_TYPES, true)) {
            $jobType = 'full-time';
        }

        $requirements = $r['requirements'] ?? '';
        if (is_array($requirements)) {
            $lines = [];
            foreach ($requirements as $req) {
                $line = is_array($req) ? (string) ($req['text'] ?? '') : (string) $req;
                $line = trim($line);
                if ($line !== '') {
                    $lines[] = str_starts_with($line, '-') ? $line : '- ' . $line;
                }
            }
            $requirements = implode("\n", $lines);
        } else {
            $requirements = trim((string) $requirements);
        }

        $criteria = $this->normalizeCriteria($this->extractList(['x' => $r['ai_criteria'] ?? []], ['x']));
        if ($criteria === []) {
            $criteria = $this->fallbackCriteria();
        }

        $questions = [];
        foreach ($this->extractList(['x' => $r['question_bank'] ?? []], ['x']) as $q) {
            if (is_string($q)) {
                $q = trim($q);
            } elseif (is_array($q)) {
                $q = trim((string) ($q['question'] ?? $q['text'] ?? ''));
            } else {
                continue;
            }
            if ($q !== '') {
                $questions[] = $q;
            }
        }
        $questions = $this->ensureTwenty($questions);

        $salaryMin = $this->toNumber($r['salary_min'] ?? null);
        $salaryMax = $this->toNumber($r['salary_max'] ?? null);
        if ($salaryMin !== null && $salaryMax !== null && $salaryMax < $salaryMin) {
            [$salaryMin, $salaryMax] = [$salaryMax, $salaryMin];
        }

        $location = trim((string) ($r['location'] ?? '')) ?: ($defaultLocation !== '' ? $defaultLocation : 'Remote');
        $currency = strtoupper(trim((string) ($r['currency'] ?? 'USD'))) ?: 'USD';

        return [
            'title'         => $title,
            'description'   => trim((string) ($r['description'] ?? '')) ?: $this->fallbackDescription($title),
            'requirements'  => $requirements !== '' ? $requirements : $this->fallbackRequirements(),
            'department'    => trim((string) ($r['department'] ?? '')) ?: 'General',
            'location'      => $location,
            'job_type'      => $jobType,
            'salary_min'    => $salaryMin,
            'salary_max'    => $salaryMax,
            'currency'      => $currency,
            'ai_criteria'   => $criteria,
            'question_bank' => $questions,
        ];
    }

    /**
     * @param array<int,mixed> $list
     * @return array<int,array{criterion_name:string,weight:int,description:string}>
     */
    private function normalizeCriteria(array $list): array
    {
        $out = [];
        foreach ($list as $c) {
            if (!is_array($c)) {
                if (is_string($c) && trim($c) !== '') {
                    $out[] = ['criterion_name' => trim($c), 'weight' => 3, 'description' => ''];
                }
                continue;
            }
            $name = trim((string) ($c['criterion_name'] ?? $c['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $weight = (int) round((float) ($c['weight'] ?? 3));
            $weight = max(1, min(5, $weight));
            $out[] = [
                'criterion_name' => $name,
                'weight'         => $weight,
                'description'    => trim((string) ($c['description'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * Pull the first present list from an associative result by candidate keys.
     * Also tolerates a bare top-level array.
     *
     * @param mixed $result
     * @param array<int,string> $keys
     * @return array<int,mixed>
     */
    private function extractList($result, array $keys): array
    {
        if (!is_array($result)) {
            return [];
        }
        if (array_is_list($result)) {
            return $result;
        }
        foreach ($keys as $k) {
            if (isset($result[$k]) && is_array($result[$k])) {
                return array_values($result[$k]);
            }
        }
        // As a last resort, if the object has exactly one array value, use it.
        foreach ($result as $v) {
            if (is_array($v) && array_is_list($v)) {
                return $v;
            }
        }
        return [];
    }

    /**
     * @param array<int,string> $questions
     * @return array<int,string>
     */
    private function ensureTwenty(array $questions): array
    {
        $questions = array_values(array_unique(array_filter($questions, static fn($q) => trim((string) $q) !== '')));
        if (count($questions) >= 20) {
            return array_slice($questions, 0, 20);
        }
        foreach ($this->genericQuestions() as $q) {
            if (count($questions) >= 20) {
                break;
            }
            if (!in_array($q, $questions, true)) {
                $questions[] = $q;
            }
        }
        // Guarantee exactly 20 even if the generic bank was short (it is not).
        $i = 1;
        while (count($questions) < 20) {
            $questions[] = 'Tell me more about your relevant experience for this role. (' . $i++ . ')';
        }
        return array_slice($questions, 0, 20);
    }

    private function toNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        // Strip currency symbols/commas like "$120,000".
        $clean = preg_replace('/[^0-9.]/', '', (string) $value);
        return ($clean === null || $clean === '') ? null : (float) $clean;
    }

    // ----------------------------------------------------------------------
    // Fallbacks
    // ----------------------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    private function fallbackJob(string $prompt, string $companyName, string $location): array
    {
        $title = $prompt !== '' ? ucwords(trim(mb_substr($prompt, 0, 80))) : 'New Position';
        return [
            'title'         => $title,
            'description'   => $this->fallbackDescription($title, $companyName),
            'requirements'  => $this->fallbackRequirements(),
            'department'    => 'General',
            'location'      => $location !== '' ? $location : 'Remote',
            'job_type'      => 'full-time',
            'salary_min'    => null,
            'salary_max'    => null,
            'currency'      => 'USD',
            'ai_criteria'   => $this->fallbackCriteria(),
            'question_bank' => $this->ensureTwenty([]),
        ];
    }

    private function fallbackDescription(string $title, string $companyName = ''): string
    {
        $who = $companyName !== '' ? $companyName : 'Our team';
        return "{$who} is looking for a talented {$title} to join us.\n\n"
            . "In this role you will take ownership of meaningful work, collaborate closely with cross-functional "
            . "colleagues, and contribute directly to our goals. We value initiative, curiosity, and a commitment to "
            . "doing great work.\n\n"
            . "Responsibilities:\n"
            . "- Deliver high-quality work aligned with team objectives.\n"
            . "- Collaborate with stakeholders to understand needs and propose solutions.\n"
            . "- Continuously improve processes, quality, and outcomes.\n\n"
            . "Success in this role looks like consistently strong execution, clear communication, and positive impact "
            . "on the people and projects around you.";
    }

    private function fallbackRequirements(): string
    {
        return "- Relevant professional experience in a comparable role.\n"
            . "- Strong communication and collaboration skills.\n"
            . "- Proven problem-solving ability and attention to detail.\n"
            . "- Ability to manage priorities and meet deadlines.\n"
            . "- A growth mindset and willingness to learn.";
    }

    /**
     * @return array<int,array{criterion_name:string,weight:int,description:string}>
     */
    private function fallbackCriteria(): array
    {
        return [
            ['criterion_name' => 'Relevant Experience', 'weight' => 5, 'description' => 'Depth and relevance of prior experience for this role.'],
            ['criterion_name' => 'Technical / Role Skills', 'weight' => 5, 'description' => 'Core skills required to perform the job effectively.'],
            ['criterion_name' => 'Communication', 'weight' => 4, 'description' => 'Clarity and effectiveness when sharing ideas.'],
            ['criterion_name' => 'Problem Solving', 'weight' => 4, 'description' => 'Ability to analyze and resolve challenges.'],
            ['criterion_name' => 'Collaboration', 'weight' => 3, 'description' => 'Works well within a team and across functions.'],
            ['criterion_name' => 'Cultural Fit', 'weight' => 3, 'description' => 'Alignment with company values and ways of working.'],
        ];
    }

    /**
     * @return array<int,string>
     */
    private function fallbackImprovements(): array
    {
        return [
            'Add a concise, compelling opening paragraph that sells the mission and impact of the role.',
            'List responsibilities as clear bullet points so candidates can quickly scan them.',
            'Separate "must-have" from "nice-to-have" requirements to widen the qualified applicant pool.',
            'Include a salary range and benefits summary to improve transparency and conversion.',
            'Use inclusive, gender-neutral language and remove unnecessary jargon.',
            'Describe growth opportunities and what success looks like in the first 6-12 months.',
            'Add a short blurb about your company culture and values.',
        ];
    }

    /**
     * @return array<int,string>
     */
    private function genericQuestions(): array
    {
        return [
            'Tell me about yourself and what drew you to this role.',
            'What interests you most about this position and our company?',
            'Walk me through a project you are especially proud of and your specific contribution.',
            'Describe a challenging problem you solved. What was your approach?',
            'Tell me about a time you worked effectively as part of a team.',
            'Describe a situation where you had to handle conflicting priorities.',
            'Tell me about a time you received difficult feedback and how you responded.',
            'How do you keep your skills and knowledge up to date?',
            'Describe a time you took initiative beyond what was expected.',
            'Tell me about a mistake you made at work and what you learned from it.',
            'How do you approach learning something new under time pressure?',
            'Describe a time you had to influence someone without formal authority.',
            'What does great collaboration look like to you?',
            'Tell me about a time you improved a process or way of working.',
            'How do you handle ambiguity or incomplete information?',
            'Describe a time you had to make a difficult decision quickly.',
            'What motivates you in your day-to-day work?',
            'Tell me about a time you exceeded expectations on a goal.',
            'How do you handle stress and stay productive during busy periods?',
            'Where do you see yourself growing in this role over the next few years?',
        ];
    }
}
