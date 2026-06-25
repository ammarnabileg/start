<?php

class RecruitmentCopilot
{
    public function __construct(private OpenAIService $ai) {}

    /**
     * General-purpose recruitment AI assistant.
     *
     * @param  string              $userMessage
     * @param  array<string,mixed> $context  Optional context: candidate, job, etc.
     * @return string
     */
    public function chat(string $userMessage, array $context = []): string
    {
        $systemPrompt = "You are an expert AI recruitment assistant. You help HR professionals and recruiters with candidate screening, job descriptions, interview questions, and hiring decisions. Be concise, practical, and data-driven in your responses.";

        if (!empty($context)) {
            $systemPrompt .= "\n\nContext provided:";

            if (!empty($context['job'])) {
                $job = $context['job'];
                $systemPrompt .= "\nJob: {$job['title']} in {$job['department']}";
            }

            if (!empty($context['candidate'])) {
                $c = $context['candidate'];
                $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                $systemPrompt .= "\nCandidate: {$name} (AI Score: " . ($c['ai_score'] ?? 'N/A') . ")";
            }

            if (!empty($context['application'])) {
                $app = $context['application'];
                $systemPrompt .= "\nApplication stage: " . ($app['current_stage'] ?? 'unknown');
            }
        }

        $result = $this->ai->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userMessage],
        ], ['max_tokens' => 1000]);

        if ($result === null) {
            return 'I apologize, but I was unable to process your request at this time. Please try again later.';
        }

        return $result['content'];
    }

    /**
     * Generate a professional job description.
     *
     * @param  string $title
     * @param  string $department
     * @param  string $requirements  Raw requirements notes
     * @return string
     */
    public function generateJobDescription(string $title, string $department, string $requirements): string
    {
        $systemPrompt = "You are an expert technical writer specializing in job descriptions. Create compelling, inclusive, and detailed job descriptions that attract top talent. Format the output in clean HTML using <p>, <ul>, <li>, <h3> tags.";

        $userPrompt = <<<PROMPT
Generate a complete job description for the following role:

Job Title: {$title}
Department: {$department}

Requirements / Notes:
{$requirements}

Include the following sections:
1. About the Role (2-3 paragraphs)
2. Key Responsibilities (bullet list, 6-8 items)
3. Required Qualifications (bullet list, 4-6 items)
4. Preferred Qualifications (bullet list, 3-4 items)
5. What We Offer (bullet list, 4-5 items)

Make it engaging, professional, and inclusive.
PROMPT;

        $result = $this->ai->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ], ['max_tokens' => 1500]);

        if ($result === null) {
            return "<p>Job description could not be generated. Please try again.</p>";
        }

        return $result['content'];
    }

    /**
     * Suggest screening criteria for a job.
     *
     * @param  string $jobTitle
     * @param  string $description
     * @return array<array{name:string,weight:int,description:string}>
     */
    public function suggestCriteria(string $jobTitle, string $description): array
    {
        $systemPrompt = 'You are an expert talent acquisition specialist. Suggest screening criteria for job positions. Return valid JSON only — no markdown, no extra text. Schema: [{"name":"string","weight":1-10,"description":"string"}]';

        $userPrompt = <<<PROMPT
Suggest 5-8 screening criteria for this job:

Job Title: {$jobTitle}

Job Description:
{$description}

For each criterion:
- name: short label (e.g. "Technical Skills", "Communication", "Leadership")
- weight: importance score from 1-10
- description: one sentence explaining what to look for

Return a JSON array.
PROMPT;

        $result = $this->ai->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ], ['response_format' => ['type' => 'json_object'], 'max_tokens' => 800]);

        if ($result === null) {
            return $this->defaultCriteria();
        }

        try {
            $parsed = json_decode($result['content'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $clean  = preg_replace('/^```(?:json)?\s*/i', '', trim($result['content']));
            $clean  = preg_replace('/\s*```$/', '', $clean ?? '');
            try {
                $parsed = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $this->defaultCriteria();
            }
        }

        // The model may wrap the array in an object key
        if (is_array($parsed) && !array_is_list($parsed)) {
            $parsed = reset($parsed);
        }

        if (!is_array($parsed)) {
            return $this->defaultCriteria();
        }

        $criteria = [];
        foreach ($parsed as $item) {
            if (!is_array($item) || empty($item['name'])) { continue; }
            $weight = max(1, min(10, (int) ($item['weight'] ?? 5)));
            $criteria[] = [
                'name'        => (string) $item['name'],
                'weight'      => $weight,
                'description' => (string) ($item['description'] ?? ''),
            ];
        }

        return $criteria ?: $this->defaultCriteria();
    }

    /**
     * @return array<array{name:string,weight:int,description:string}>
     */
    private function defaultCriteria(): array
    {
        return [
            ['name' => 'Technical Skills',    'weight' => 9, 'description' => 'Relevant technical knowledge and hands-on experience for the role.'],
            ['name' => 'Communication',        'weight' => 8, 'description' => 'Ability to communicate clearly and effectively in written and verbal form.'],
            ['name' => 'Problem Solving',      'weight' => 8, 'description' => 'Aptitude for identifying problems and developing effective solutions.'],
            ['name' => 'Teamwork',             'weight' => 7, 'description' => 'Ability to collaborate effectively within a team environment.'],
            ['name' => 'Cultural Fit',         'weight' => 6, 'description' => 'Alignment with company values and work culture.'],
            ['name' => 'Adaptability',         'weight' => 6, 'description' => 'Willingness and ability to adapt to changing requirements and environments.'],
            ['name' => 'Leadership Potential', 'weight' => 5, 'description' => 'Demonstrated ability or potential to lead and mentor others.'],
        ];
    }
}