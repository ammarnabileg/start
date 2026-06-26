<?php
declare(strict_types=1);

class InterviewEvaluator
{
    private OpenAIService $ai;
    private int $tenantId;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->ai       = new OpenAIService($tenantId);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildTranscript(int $interviewId): string
    {
        $db       = Database::getInstance();
        $messages = $db->fetchAll(
            "SELECT role, content FROM ai_interview_messages
             WHERE interview_id = ? ORDER BY created_at ASC",
            [$interviewId]
        );

        $lines = [];
        foreach ($messages as $m) {
            $speaker = ($m['role'] === 'ai') ? 'Interviewer' : 'Candidate';
            $lines[] = "{$speaker}: " . $m['content'];
        }

        return implode("\n\n", $lines);
    }

    private function loadPrompt(string $slug): ?array
    {
        $db = Database::getInstance();
        $row = $db->fetch(
            "SELECT system_prompt, user_prompt_template, max_tokens, temperature
             FROM ai_prompt_templates
             WHERE slug = ? AND (tenant_id IS NULL OR tenant_id = ?)
             ORDER BY tenant_id DESC LIMIT 1",
            [$slug, $this->tenantId]
        );
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // scoreSkills
    // -------------------------------------------------------------------------

    public function scoreSkills(int $interviewId, int $applicationId): array
    {
        $db         = Database::getInstance();
        $transcript = $this->buildTranscript($interviewId);
        $now        = date('Y-m-d H:i:s');

        $cvSummary = '';
        $cvRow     = $db->fetch(
            "SELECT skills_extracted, years_experience, education_level
             FROM ai_cv_analyses WHERE application_id = ? LIMIT 1",
            [$applicationId]
        );
        if ($cvRow) {
            $skills    = json_decode($cvRow['skills_extracted'] ?? '[]', true);
            $cvSummary = "CV Context – Skills: " . implode(', ', (array)$skills)
                . "; Years of experience: " . ($cvRow['years_experience'] ?? 'unknown')
                . "; Education: " . ($cvRow['education_level'] ?? 'unknown');
        }

        $template = $this->loadPrompt('skill_scorer');

        if ($template && !empty($template['user_prompt_template'])) {
            $userPrompt   = str_replace(
                ['{transcript}', '{cv_summary}'],
                [$transcript, $cvSummary],
                $template['user_prompt_template']
            );
            $systemPrompt = $template['system_prompt'] ?? null;
            $maxTokens    = (int)($template['max_tokens'] ?? 1200);
            $temperature  = (float)($template['temperature'] ?? 0.2);
        } else {
            $systemPrompt = 'You are an expert interview evaluator. Respond only with valid JSON.';
            $userPrompt   = "Score the candidate on each dimension (0-100) based on the interview transcript.\n\n"
                . ($cvSummary ? "{$cvSummary}\n\n" : '')
                . "Transcript:\n{$transcript}\n\n"
                . "Return JSON: {\"technical_competency\":0,\"communication\":0,\"problem_solving\":0,"
                . "\"critical_thinking\":0,\"confidence\":0,\"leadership\":0,\"culture_fit\":0,"
                . "\"professionalism\":0,\"ai_knowledge\":0,\"english_proficiency\":0,"
                . "\"learning_ability\":0,\"overall_score\":0,\"confidence_level\":0}";
            $maxTokens   = 1200;
            $temperature = 0.2;
        }

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $response = $this->ai->chat($messages, [
            'max_tokens'      => $maxTokens,
            'temperature'     => $temperature,
            'response_format' => ['type' => 'json_object'],
            'feature'         => 'skill_scorer',
        ]);

        $data  = $response ? ($this->ai->getJSON($response) ?? []) : [];
        $clamp = static fn($v) => min(100, max(0, (float)$v));

        $record = [
            'interview_id'          => $interviewId,
            'application_id'        => $applicationId,
            'technical_competency'  => $clamp($data['technical_competency'] ?? 0),
            'communication'         => $clamp($data['communication'] ?? 0),
            'problem_solving'       => $clamp($data['problem_solving'] ?? 0),
            'critical_thinking'     => $clamp($data['critical_thinking'] ?? 0),
            'confidence'            => $clamp($data['confidence'] ?? 0),
            'leadership'            => $clamp($data['leadership'] ?? 0),
            'culture_fit'           => $clamp($data['culture_fit'] ?? 0),
            'professionalism'       => $clamp($data['professionalism'] ?? 0),
            'ai_knowledge'          => $clamp($data['ai_knowledge'] ?? 0),
            'english_proficiency'   => $clamp($data['english_proficiency'] ?? 0),
            'learning_ability'      => $clamp($data['learning_ability'] ?? 0),
            'overall_score'         => $clamp($data['overall_score'] ?? 0),
            'confidence_level'      => $clamp($data['confidence_level'] ?? 0),
            'tokens_used'           => $response['usage']['total_tokens'] ?? null,
            'scored_at'             => $now,
            'updated_at'            => $now,
        ];

        $existing = $db->fetch(
            "SELECT id FROM ai_skill_scores WHERE interview_id = ? LIMIT 1",
            [$interviewId]
        );
        if ($existing) {
            $upd = $record;
            unset($upd['interview_id'], $upd['application_id']);
            $db->update('ai_skill_scores', $upd, ['id' => (int)$existing['id']]);
        } else {
            $record['created_at'] = $now;
            $db->insert('ai_skill_scores', $record);
        }

        return $record;
    }

    // -------------------------------------------------------------------------
    // analyzePersonality
    // -------------------------------------------------------------------------

    public function analyzePersonality(int $interviewId, int $applicationId): array
    {
        $db         = Database::getInstance();
        $transcript = $this->buildTranscript($interviewId);
        $now        = date('Y-m-d H:i:s');

        $template = $this->loadPrompt('personality_analyst');

        if ($template && !empty($template['user_prompt_template'])) {
            $userPrompt   = str_replace('{transcript}', $transcript, $template['user_prompt_template']);
            $systemPrompt = $template['system_prompt'] ?? null;
            $maxTokens    = (int)($template['max_tokens'] ?? 900);
            $temperature  = (float)($template['temperature'] ?? 0.3);
        } else {
            $systemPrompt = 'You are an organizational psychologist. Respond only with valid JSON.';
            $userPrompt   = "Analyze the candidate's personality based on their interview responses.\n\n"
                . "Transcript:\n{$transcript}\n\n"
                . "Return JSON: {\"disc\":{\"D\":0,\"I\":0,\"S\":0,\"C\":0},"
                . "\"big_five\":{\"openness\":0,\"conscientiousness\":0,\"extraversion\":0,"
                . "\"agreeableness\":0,\"neuroticism\":0},"
                . "\"growth_score\":0,\"pressure_score\":0,\"leadership_style\":\"\",\"summary\":\"\"}";
            $maxTokens   = 900;
            $temperature = 0.3;
        }

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $response = $this->ai->chat($messages, [
            'max_tokens'      => $maxTokens,
            'temperature'     => $temperature,
            'response_format' => ['type' => 'json_object'],
            'feature'         => 'personality_analyst',
        ]);

        $data  = $response ? ($this->ai->getJSON($response) ?? []) : [];
        $disc  = $data['disc'] ?? [];
        $big5  = $data['big_five'] ?? [];
        $clamp = static fn($v) => min(100, max(0, (float)$v));

        $record = [
            'interview_id'           => $interviewId,
            'application_id'         => $applicationId,
            'disc_d'                 => $clamp($disc['D'] ?? 0),
            'disc_i'                 => $clamp($disc['I'] ?? 0),
            'disc_s'                 => $clamp($disc['S'] ?? 0),
            'disc_c'                 => $clamp($disc['C'] ?? 0),
            'big5_openness'          => $clamp($big5['openness'] ?? 0),
            'big5_conscientiousness' => $clamp($big5['conscientiousness'] ?? 0),
            'big5_extraversion'      => $clamp($big5['extraversion'] ?? 0),
            'big5_agreeableness'     => $clamp($big5['agreeableness'] ?? 0),
            'big5_neuroticism'       => $clamp($big5['neuroticism'] ?? 0),
            'growth_score'           => isset($data['growth_score']) ? $clamp($data['growth_score']) : null,
            'pressure_score'         => isset($data['pressure_score']) ? $clamp($data['pressure_score']) : null,
            'leadership_style'       => $data['leadership_style'] ?? null,
            'summary'                => $data['summary'] ?? null,
            'tokens_used'            => $response['usage']['total_tokens'] ?? null,
            'analyzed_at'            => $now,
            'updated_at'             => $now,
        ];

        $existing = $db->fetch(
            "SELECT id FROM ai_personality_analyses WHERE interview_id = ? LIMIT 1",
            [$interviewId]
        );
        if ($existing) {
            $upd = $record;
            unset($upd['interview_id'], $upd['application_id']);
            $db->update('ai_personality_analyses', $upd, ['id' => (int)$existing['id']]);
        } else {
            $record['created_at'] = $now;
            $db->insert('ai_personality_analyses', $record);
        }

        return $record;
    }

    // -------------------------------------------------------------------------
    // detectRedFlags
    // -------------------------------------------------------------------------

    public function detectRedFlags(int $interviewId, int $applicationId, string $cvText): array
    {
        $db         = Database::getInstance();
        $transcript = $this->buildTranscript($interviewId);
        $now        = date('Y-m-d H:i:s');

        $template = $this->loadPrompt('red_flag_detector');

        if ($template && !empty($template['user_prompt_template'])) {
            $userPrompt   = str_replace(
                ['{transcript}', '{cv_text}'],
                [$transcript, $cvText],
                $template['user_prompt_template']
            );
            $systemPrompt = $template['system_prompt'] ?? null;
            $maxTokens    = (int)($template['max_tokens'] ?? 900);
            $temperature  = (float)($template['temperature'] ?? 0.2);
        } else {
            $systemPrompt = 'You are a senior HR risk analyst. Respond only with valid JSON.';
            $userPrompt   = "Review the interview transcript and CV for red flags or concerns.\n\n"
                . "Transcript:\n{$transcript}\n\n"
                . ($cvText ? "CV Text:\n" . substr($cvText, 0, 2000) . "\n\n" : '')
                . "Return JSON: {\"red_flags\":[{\"category\":\"...\","
                . "\"description\":\"...\",\"evidence\":\"...\",\"severity\":\"high|medium|low\"}]}";
            $maxTokens   = 900;
            $temperature = 0.2;
        }

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $response = $this->ai->chat($messages, [
            'max_tokens'      => $maxTokens,
            'temperature'     => $temperature,
            'response_format' => ['type' => 'json_object'],
            'feature'         => 'red_flag_detector',
        ]);

        $data     = $response ? ($this->ai->getJSON($response) ?? []) : [];
        $redFlags = $data['red_flags'] ?? [];

        $db->query(
            "DELETE FROM ai_red_flags WHERE interview_id = ? AND application_id = ?",
            [$interviewId, $applicationId]
        );

        $results = [];
        foreach ($redFlags as $flag) {
            if (empty($flag['description'])) {
                continue;
            }
            $severity = in_array($flag['severity'] ?? '', ['high', 'medium', 'low'])
                ? $flag['severity']
                : 'medium';
            $row = [
                'interview_id'   => $interviewId,
                'application_id' => $applicationId,
                'severity'       => $severity,
                'category'       => $flag['category'] ?? 'general',
                'description'    => $flag['description'],
                'evidence'       => $flag['evidence'] ?? null,
                'created_at'     => $now,
            ];
            $db->insert('ai_red_flags', $row);
            $results[] = $row;
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // generateRecommendation
    // -------------------------------------------------------------------------

    public function generateRecommendation(int $interviewId, int $applicationId): array
    {
        $db  = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        $skillRow = $db->fetch(
            "SELECT overall_score, technical_competency, communication, problem_solving,
                    critical_thinking, confidence, leadership, culture_fit, professionalism
             FROM ai_skill_scores WHERE interview_id = ? LIMIT 1",
            [$interviewId]
        );

        $cvRow = $db->fetch(
            "SELECT match_score, strengths, weaknesses, years_experience, education_level
             FROM ai_cv_analyses WHERE application_id = ? LIMIT 1",
            [$applicationId]
        );

        $redFlagRows = $db->fetchAll(
            "SELECT severity, category, description FROM ai_red_flags
             WHERE interview_id = ? ORDER BY severity ASC",
            [$interviewId]
        );

        $personalityRow = $db->fetch(
            "SELECT disc_d, disc_i, disc_s, disc_c, summary, leadership_style
             FROM ai_personality_analyses WHERE interview_id = ? LIMIT 1",
            [$interviewId]
        );

        $skillScore = (float)($skillRow['overall_score'] ?? 0);
        $cvScore    = (float)($cvRow['match_score'] ?? 0);
        $blended    = round($skillScore * 0.6 + $cvScore * 0.4, 1);

        $contextParts = [];
        if ($skillRow) {
            $contextParts[] = "Skill Score: {$skillScore}/100"
                . " (Technical: {$skillRow['technical_competency']}"
                . ", Communication: {$skillRow['communication']}"
                . ", Problem Solving: {$skillRow['problem_solving']}"
                . ", Culture Fit: {$skillRow['culture_fit']})";
        }
        if ($cvRow) {
            $contextParts[] = "CV Match Score: {$cvScore}/100"
                . "; Years Experience: " . ($cvRow['years_experience'] ?? 'N/A')
                . "; Education: " . ($cvRow['education_level'] ?? 'N/A');
        }
        if ($redFlagRows) {
            $flags = array_map(
                fn($f) => "[{$f['severity']}] {$f['category']}: {$f['description']}",
                $redFlagRows
            );
            $contextParts[] = "Red Flags:\n" . implode("\n", $flags);
        }
        if ($personalityRow && $personalityRow['summary']) {
            $contextParts[] = "Personality: " . $personalityRow['summary'];
        }
        $contextParts[] = "Blended Score: {$blended}/100";

        $context  = implode("\n\n", $contextParts);
        $template = $this->loadPrompt('recommendation_generator');

        if ($template && !empty($template['user_prompt_template'])) {
            $userPrompt   = str_replace('{context}', $context, $template['user_prompt_template']);
            $systemPrompt = $template['system_prompt'] ?? null;
            $maxTokens    = (int)($template['max_tokens'] ?? 1200);
            $temperature  = (float)($template['temperature'] ?? 0.3);
        } else {
            $systemPrompt = 'You are a senior talent acquisition specialist. Respond only with valid JSON.';
            $userPrompt   = "Based on the following evaluation data, provide a final hiring recommendation.\n\n"
                . "{$context}\n\n"
                . "Return JSON: {\"recommendation\":\"strong_yes|yes|maybe|no\","
                . "\"final_score\":0,\"executive_summary\":\"\","
                . "\"strengths\":[],\"weaknesses\":[],\"hiring_risks\":\"\"}";
            $maxTokens   = 1200;
            $temperature = 0.3;
        }

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $response = $this->ai->chat($messages, [
            'max_tokens'      => $maxTokens,
            'temperature'     => $temperature,
            'response_format' => ['type' => 'json_object'],
            'feature'         => 'recommendation_generator',
        ]);

        $data       = $response ? ($this->ai->getJSON($response) ?? []) : [];
        $clamp      = static fn($v) => min(100, max(0, (float)$v));
        $finalScore = $clamp($data['final_score'] ?? $blended);
        $validRecs  = ['strong_yes', 'yes', 'maybe', 'no'];
        $rec        = in_array($data['recommendation'] ?? '', $validRecs)
            ? $data['recommendation']
            : 'maybe';

        $record = [
            'interview_id'      => $interviewId,
            'application_id'    => $applicationId,
            'final_score'       => $finalScore,
            'recommendation'    => $rec,
            'executive_summary' => $data['executive_summary'] ?? null,
            'strengths'         => json_encode($data['strengths'] ?? []),
            'weaknesses'        => json_encode($data['weaknesses'] ?? []),
            'hiring_risks'      => $data['hiring_risks'] ?? null,
            'tokens_used'       => $response['usage']['total_tokens'] ?? null,
            'generated_at'      => $now,
            'updated_at'        => $now,
        ];

        $existing = $db->fetch(
            "SELECT id FROM ai_recommendations WHERE interview_id = ? LIMIT 1",
            [$interviewId]
        );
        if ($existing) {
            $upd = $record;
            unset($upd['interview_id'], $upd['application_id']);
            $db->update('ai_recommendations', $upd, ['id' => (int)$existing['id']]);
        } else {
            $record['created_at'] = $now;
            $db->insert('ai_recommendations', $record);
        }

        // Auto-update application status based on score
        if ($finalScore >= 82) {
            $newStatus = 'qualified';
        } elseif ($finalScore >= 50) {
            $newStatus = 'ai_screening';
        } else {
            $newStatus = 'disqualified';
        }

        $db->update('applications', [
            'status'        => $newStatus,
            'last_stage_at' => $now,
            'updated_at'    => $now,
        ], ['id' => $applicationId]);

        return $record;
    }

    // -------------------------------------------------------------------------
    // runFullEvaluation
    // -------------------------------------------------------------------------

    public function runFullEvaluation(int $interviewId, int $applicationId): void
    {
        try {
            $this->scoreSkills($interviewId, $applicationId);
        } catch (\Throwable $e) {
            error_log("InterviewEvaluator::scoreSkills failed [{$interviewId}]: " . $e->getMessage());
        }

        try {
            $this->analyzePersonality($interviewId, $applicationId);
        } catch (\Throwable $e) {
            error_log("InterviewEvaluator::analyzePersonality failed [{$interviewId}]: " . $e->getMessage());
        }

        // Retrieve CV text for red flag detection
        $cvText = '';
        try {
            $db    = Database::getInstance();
            $cvDoc = $db->fetch(
                "SELECT cd.file_path, cd.mime_type
                 FROM applications a
                 JOIN candidate_documents cd ON cd.id = a.cv_document_id
                 WHERE a.id = ? LIMIT 1",
                [$applicationId]
            );
            if ($cvDoc && !empty($cvDoc['file_path']) && file_exists($cvDoc['file_path'])) {
                $analyzer = new CVAnalyzer($this->tenantId);
                $mime     = $cvDoc['mime_type'] ?? '';
                if (str_contains($mime, 'pdf') || str_ends_with(strtolower($cvDoc['file_path']), '.pdf')) {
                    $cvText = $analyzer->extractTextFromPDF($cvDoc['file_path']);
                } else {
                    $cvText = $analyzer->extractTextFromDoc($cvDoc['file_path']);
                }
            }
        } catch (\Throwable $e) {
            error_log("InterviewEvaluator::runFullEvaluation CV text extraction failed: " . $e->getMessage());
        }

        try {
            $this->detectRedFlags($interviewId, $applicationId, $cvText);
        } catch (\Throwable $e) {
            error_log("InterviewEvaluator::detectRedFlags failed [{$interviewId}]: " . $e->getMessage());
        }

        try {
            $this->generateRecommendation($interviewId, $applicationId);
        } catch (\Throwable $e) {
            error_log("InterviewEvaluator::generateRecommendation failed [{$interviewId}]: " . $e->getMessage());
        }
    }
}
