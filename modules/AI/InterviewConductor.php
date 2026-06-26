<?php
declare(strict_types=1);

class InterviewConductor
{
    private OpenAIService $ai;
    private int $tenantId;
    private int $interviewId;

    public function __construct(int $tenantId, int $interviewId)
    {
        $this->tenantId    = $tenantId;
        $this->interviewId = $interviewId;
        $this->ai          = new OpenAIService($tenantId);
    }

    /**
     * Build the system prompt for the AI interview conductor.
     *
     * @param array  $job      Job row from DB (must include title, description, tenant_id).
     * @param array  $avatar   Avatar row from DB (name, style, personality_prompt).
     * @param string $language Language code: 'ar', 'en', or 'auto'.
     * @return string
     */
    public function getSystemPrompt(array $job, array $avatar, string $language): string
    {
        $db = Database::getInstance();

        $template = $db->fetch(
            "SELECT system_prompt, user_prompt_template
             FROM ai_prompt_templates
             WHERE slug = 'interview_conductor' AND (tenant_id IS NULL OR tenant_id = ?)
             ORDER BY tenant_id DESC LIMIT 1",
            [$this->tenantId]
        );

        $tenantName = '';
        $tenant     = $db->fetch("SELECT name FROM tenants WHERE id = ?", [$this->tenantId]);
        if ($tenant) {
            $tenantName = $tenant['name'];
        }

        $avatarName  = $avatar['name'] ?? 'AI Interviewer';
        $style       = $avatar['style'] ?? 'formal';
        $jobTitle    = $job['title'] ?? '';
        $langLabel   = match ($language) {
            'ar'    => 'Arabic',
            'en'    => 'English',
            default => 'the candidate\'s preferred language',
        };

        if ($template && !empty($template['system_prompt'])) {
            $prompt = str_replace(
                ['{avatar_name}', '{style}', '{company_name}', '{job_title}', '{language}'],
                [$avatarName, $style, $tenantName, $jobTitle, $langLabel],
                $template['system_prompt']
            );
        } else {
            $personalityExtra = !empty($avatar['personality_prompt'])
                ? "\n\nPersonality guidance: " . $avatar['personality_prompt']
                : '';

            $prompt = "You are {$avatarName}, an AI interviewer for {$tenantName}. "
                . "You are conducting a {$style} job interview for the position of {$jobTitle}. "
                . "Conduct the interview in {$langLabel}. "
                . "Ask one question at a time, listen carefully to answers, and ask relevant follow-ups. "
                . "Be professional, encouraging, and fair. "
                . "Your goal is to assess the candidate's skills, experience, and cultural fit."
                . $personalityExtra;
        }

        return $prompt;
    }

    /**
     * Generate the AI's next response in the interview conversation.
     *
     * @param string $candidateMessage     The latest candidate message.
     * @param array  $conversationHistory  Previous messages [{role, content}].
     * @param array  $job                  Job row.
     * @param array  $avatar               Avatar row.
     * @param int    $questionCount        Number of questions already asked.
     * @param int    $maxQuestions         Maximum allowed questions.
     * @return array {message: string, isLastQuestion: bool, questionNumber: int}
     */
    public function respond(
        string $candidateMessage,
        array  $conversationHistory,
        array  $job,
        array  $avatar,
        int    $questionCount,
        int    $maxQuestions
    ): array {
        $db = Database::getInstance();

        $language      = $job['language'] ?? 'en';
        $systemPrompt  = $this->getSystemPrompt($job, $avatar, $language);
        $isLastQuestion = false;
        $nextNumber     = $questionCount + 1;

        // Build messages array
        $messages   = [['role' => 'system', 'content' => $systemPrompt]];
        $messages   = array_merge($messages, $conversationHistory);
        $messages[] = ['role' => 'user', 'content' => $candidateMessage];

        if ($questionCount >= $maxQuestions) {
            // Generate closing message
            $messages[] = [
                'role'    => 'system',
                'content' => 'This is the last question. After the candidate\'s response, '
                    . 'generate a warm, professional closing message thanking them for their time '
                    . 'and letting them know they will be contacted with results. Do NOT ask another question.',
            ];
            $isLastQuestion = true;
        } elseif ($nextNumber === $maxQuestions) {
            $messages[] = [
                'role'    => 'system',
                'content' => "This will be question {$nextNumber} of {$maxQuestions} (the last one). "
                    . 'Ask your final interview question.',
            ];
            $isLastQuestion = true;
        }

        $response = $this->ai->chat($messages, [
            'max_tokens'  => 500,
            'temperature' => 0.7,
            'feature'     => 'interview_conductor',
        ]);

        $aiMessage = $response ? ($this->ai->getContent($response) ?? '') : '';

        if (empty($aiMessage)) {
            $aiMessage = 'Thank you for your response. Could you elaborate a bit more?';
        }

        $now = date('Y-m-d H:i:s');

        // Save candidate message
        $db->insert('ai_interview_messages', [
            'interview_id'    => $this->interviewId,
            'role'            => 'candidate',
            'content'         => $candidateMessage,
            'question_number' => $questionCount,
            'sent_at'         => $now,
            'created_at'      => $now,
        ]);

        // Save AI response
        $db->insert('ai_interview_messages', [
            'interview_id'    => $this->interviewId,
            'role'            => 'ai',
            'content'         => $aiMessage,
            'question_number' => $nextNumber,
            'sent_at'         => $now,
            'created_at'      => $now,
        ]);

        // Update questions_asked counter
        $db->update('ai_interviews', [
            'questions_asked' => $nextNumber,
            'updated_at'      => $now,
        ], ['id' => $this->interviewId]);

        return [
            'message'         => $aiMessage,
            'isLastQuestion'  => $isLastQuestion,
            'questionNumber'  => $nextNumber,
        ];
    }

    /**
     * Generate and save the AI's opening message for the interview.
     */
    public function openInterview(array $job, array $avatar, string $language): string
    {
        $db = Database::getInstance();

        $systemPrompt = $this->getSystemPrompt($job, $avatar, $language);
        $jobTitle     = $job['title'] ?? 'this position';
        $tenantName   = '';
        $tenant       = $db->fetch("SELECT name FROM tenants WHERE id = ?", [$this->tenantId]);
        if ($tenant) {
            $tenantName = $tenant['name'];
        }

        $langLabel  = match ($language) {
            'ar'    => 'Arabic',
            'en'    => 'English',
            default => 'the candidate\'s preferred language',
        };

        $openingInstruction = "Generate a warm, professional opening message to start the interview. "
            . "Greet the candidate, introduce yourself, mention you are interviewing for the {$jobTitle} role "
            . "at {$tenantName}, briefly explain what to expect, and ask your first interview question. "
            . "Respond in {$langLabel}.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $openingInstruction],
        ];

        $response  = $this->ai->chat($messages, [
            'max_tokens'  => 400,
            'temperature' => 0.7,
            'feature'     => 'interview_conductor',
        ]);

        $aiMessage = $response ? ($this->ai->getContent($response) ?? '') : '';

        if (empty($aiMessage)) {
            $avatarName = $avatar['name'] ?? 'AI Interviewer';
            $aiMessage  = "Hello! I'm {$avatarName}, and I'll be conducting your interview for the {$jobTitle} "
                . "position today. I'll ask you a series of questions to learn more about your experience and skills. "
                . "Please take your time with each answer. Let's begin: Could you start by telling me a bit about yourself "
                . "and what drew you to this role?";
        }

        $now = date('Y-m-d H:i:s');
        $db->insert('ai_interview_messages', [
            'interview_id'    => $this->interviewId,
            'role'            => 'ai',
            'content'         => $aiMessage,
            'question_number' => 1,
            'sent_at'         => $now,
            'created_at'      => $now,
        ]);

        $db->update('ai_interviews', [
            'questions_asked' => 1,
            'updated_at'      => $now,
        ], ['id' => $this->interviewId]);

        return $aiMessage;
    }

    /**
     * Close the interview: mark completed, create feedback record, flag for async evaluation.
     */
    public function closeInterview(int $interviewId): void
    {
        $db  = Database::getInstance();
        $now = date('Y-m-d H:i:s');

        // Mark interview as completed
        $db->update('ai_interviews', [
            'status'       => 'completed',
            'completed_at' => $now,
            'updated_at'   => $now,
        ], ['id' => $interviewId]);

        // Create interview_feedback record (expires in 24 hours)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        try {
            $existing = $db->fetch(
                "SELECT id FROM interview_feedback WHERE interview_id = ? LIMIT 1",
                [$interviewId]
            );
            if (!$existing) {
                $db->insert('interview_feedback', [
                    'interview_id' => $interviewId,
                    'expires_at'   => $expiresAt,
                    'created_at'   => $now,
                ]);
            }
        } catch (\Throwable $e) {
            error_log("InterviewConductor::closeInterview feedback insert failed: " . $e->getMessage());
        }

        // Flag for post-interview analysis (async) by updating a metadata column or inserting a job queue entry
        // Using ai_interview_timeline as an async trigger flag
        try {
            $db->insert('ai_interview_timeline', [
                'interview_id' => $interviewId,
                'event_type'   => 'evaluation_pending',
                'description'  => 'Interview completed. Post-interview AI evaluation queued.',
                'metadata'     => json_encode(['trigger' => 'close_interview', 'tenant_id' => $this->tenantId]),
                'occurred_at'  => $now,
                'created_at'   => $now,
            ]);
        } catch (\Throwable $e) {
            error_log("InterviewConductor::closeInterview timeline insert failed: " . $e->getMessage());
        }
    }
}
