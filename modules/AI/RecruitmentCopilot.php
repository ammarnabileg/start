<?php
declare(strict_types=1);

class RecruitmentCopilot
{
    private OpenAIService $ai;

    public function __construct(int $tenantId)
    {
        $this->ai = new OpenAIService($tenantId);
    }

    /**
     * Generate a complete job description for a given title and seniority level.
     *
     * @param string   $title        Job title (e.g. "Senior Backend Engineer").
     * @param string   $seniority    Seniority level (e.g. "senior", "junior", "manager").
     * @param string   $industry     Industry or domain (e.g. "FinTech", "Healthcare").
     * @param string[] $requirements Optional list of specific requirements to incorporate.
     * @return array {description: string, requirements: string, benefits: string}
     */
    public function buildJobDescription(
        string $title,
        string $seniority,
        string $industry,
        array  $requirements = []
    ): array {
        $requirementsHint = '';
        if (!empty($requirements)) {
            $requirementsHint = "\n\nAdditional requirements to incorporate:\n- "
                . implode("\n- ", $requirements);
        }

        $systemPrompt = 'You are an expert HR copywriter specializing in job postings. '
            . 'Write compelling, inclusive, and professional job descriptions. '
            . 'Respond only with valid JSON.';

        $userPrompt = "Write a complete job posting for a {$seniority}-level {$title} in the {$industry} industry."
            . $requirementsHint . "\n\n"
            . "Return JSON with exactly these keys:\n"
            . "{\n"
            . "  \"description\": \"<3-5 paragraph role overview, responsibilities, and team context>\",\n"
            . "  \"requirements\": \"<bulleted list of must-have and nice-to-have qualifications>\",\n"
            . "  \"benefits\": \"<bulleted list of compensation, perks, and growth opportunities>\"\n"
            . "}";

        $response = $this->ai->chat(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            [
                'max_tokens'      => 1800,
                'temperature'     => 0.7,
                'response_format' => ['type' => 'json_object'],
                'feature'         => 'job_description_builder',
            ]
        );

        $data = $response ? ($this->ai->getJSON($response) ?? []) : [];

        return [
            'description'  => $data['description']  ?? '',
            'requirements' => $data['requirements']  ?? '',
            'benefits'     => $data['benefits']      ?? '',
        ];
    }

    /**
     * Generate a list of interview questions for a given role.
     *
     * @param string $jobTitle   The job title.
     * @param string $seniority  Seniority level.
     * @param string $criteria   Comma-separated focus areas (e.g. "technical, leadership, culture fit").
     * @param int    $count      Number of questions to generate (default 10).
     * @return string[]          Array of question strings.
     */
    public function generateInterviewQuestions(
        string $jobTitle,
        string $seniority,
        string $criteria,
        int    $count = 10
    ): array {
        $count = max(1, min(50, $count));

        $systemPrompt = 'You are an expert interviewer and talent assessment specialist. '
            . 'Generate thoughtful, open-ended interview questions that reveal true competency. '
            . 'Respond only with valid JSON.';

        $userPrompt = "Generate exactly {$count} interview questions for a {$seniority}-level {$jobTitle}.\n"
            . "Focus areas: {$criteria}\n\n"
            . "Mix behavioral (STAR-method), situational, and technical questions as appropriate.\n"
            . "Return JSON: {\"questions\": [\"question 1\", \"question 2\", ...]}";

        $response = $this->ai->chat(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            [
                'max_tokens'      => 1200,
                'temperature'     => 0.8,
                'response_format' => ['type' => 'json_object'],
                'feature'         => 'interview_question_generator',
            ]
        );

        $data      = $response ? ($this->ai->getJSON($response) ?? []) : [];
        $questions = $data['questions'] ?? [];

        // Ensure array of strings
        return array_values(array_filter(
            array_map('strval', (array)$questions),
            fn($q) => trim($q) !== ''
        ));
    }

    /**
     * Answer a general HR question with contextual awareness.
     *
     * @param string $question  The HR user's question.
     * @param array  $context   Optional key-value context to inject (e.g. candidate name, job title).
     * @return string           AI-generated answer.
     */
    public function answerHRQuestion(string $question, array $context = []): string
    {
        $systemPrompt = 'You are an expert HR consultant and recruitment specialist. '
            . 'You assist HR professionals with recruitment strategy, compliance, candidate assessment, '
            . 'offer negotiation, and general HR best practices. '
            . 'Be concise, practical, and actionable. '
            . 'Always be professional and culturally sensitive.';

        $contextBlock = '';
        if (!empty($context)) {
            $lines = [];
            foreach ($context as $key => $value) {
                if (is_scalar($value)) {
                    $lines[] = ucwords(str_replace('_', ' ', $key)) . ': ' . $value;
                }
            }
            if ($lines) {
                $contextBlock = "\n\nContext:\n" . implode("\n", $lines) . "\n\n";
            }
        }

        $userPrompt = $contextBlock . "Question: {$question}";

        $response = $this->ai->chat(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            [
                'max_tokens'  => 800,
                'temperature' => 0.6,
                'feature'     => 'hr_copilot',
            ]
        );

        if (!$response) {
            return 'I was unable to process your request at this time. Please try again shortly.';
        }

        $content = $this->ai->getContent($response);

        return trim($content ?? '') ?: 'No response generated.';
    }
}
