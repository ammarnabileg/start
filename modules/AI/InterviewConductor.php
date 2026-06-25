<?php

class InterviewConductor
{
    public function __construct(private OpenAIService $ai) {}

    /**
     * Generate the opening message for an AI interview.
     *
     * @param  array<string,mixed>      $job
     * @param  array<array<string,mixed>> $questions
     * @param  array<string,mixed>|null $avatar
     * @return array{message:string,question_index:int}
     */
    public function startInterview(array $job, array $questions, ?array $avatar): array
    {
        $systemPrompt = $this->buildSystemPrompt($job, $avatar);

        $firstQuestion = $questions[0]['question'] ?? 'Tell me about yourself and why you are interested in this role.';

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            [
                'role'    => 'user',
                'content' => 'Please start the interview with a warm greeting and then ask the first question.',
            ],
        ];

        $result = $this->ai->chat($messages);

        if ($result === null) {
            $avatarName = $avatar['name'] ?? 'AI Interviewer';
            $companyName = $job['company_name'] ?? 'our company';
            $message = "Hello and welcome! I'm {$avatarName}, and I'll be conducting your interview today for the {$job['title']} position at {$companyName}. I'm excited to learn more about you. Let's get started!\n\n{$firstQuestion}";
        } else {
            $message = $result['content'];
        }

        return [
            'message'        => $message,
            'question_index' => 0,
        ];
    }

    /**
     * Process a candidate message and return the next interviewer message.
     *
     * @param  string                     $candidateMessage
     * @param  array<array<string,mixed>> $transcript        Previous messages [{role,content}]
     * @param  array<string,mixed>        $job
     * @param  array<array<string,mixed>> $questions         Structured questions from job
     * @param  int                        $questionIndex     Current question index
     * @return array{message:string,question_index:int,is_complete:bool}
     */
    public function processMessage(
        string $candidateMessage,
        array $transcript,
        array $job,
        array $questions,
        int $questionIndex
    ): array {
        $totalQuestions = count($questions);
        $systemPrompt   = $this->buildSystemPrompt($job, null);

        // Build conversation history
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($transcript as $entry) {
            $role = $entry['role'] === 'candidate' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $entry['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $candidateMessage];

        // Determine next step
        $nextIndex  = $questionIndex;
        $isComplete = false;

        // Check if we should move to the next question (simple heuristic: move after each reply)
        // The AI will decide naturally, but we track index for structured question delivery
        $nextIndex++;

        if ($nextIndex >= $totalQuestions) {
            $isComplete = true;
        }

        // Inject guidance into system context
        if ($isComplete) {
            $messages[0]['content'] .= "\n\nAll questions have been covered. Acknowledge the candidate's last answer and professionally close the interview. Thank them for their time.";
        } elseif (isset($questions[$nextIndex])) {
            $nextQuestion = $questions[$nextIndex]['question'];
            $messages[0]['content'] .= "\n\nAfter acknowledging the candidate's answer, ask this next question: \"{$nextQuestion}\"";
        }

        $result = $this->ai->chat($messages, ['max_tokens' => 600]);

        if ($result === null) {
            if ($isComplete) {
                $message = "Thank you so much for your time today. We've covered all the topics I wanted to discuss. We'll be in touch soon with next steps. Best of luck!";
            } elseif (isset($questions[$nextIndex])) {
                $message = "Thank you for sharing that. " . $questions[$nextIndex]['question'];
            } else {
                $message = "Thank you for your response. Let's continue.";
            }
        } else {
            $message = $result['content'];
        }

        return [
            'message'        => $message,
            'question_index' => min($nextIndex, $totalQuestions - 1),
            'is_complete'    => $isComplete,
        ];
    }

    /**
     * Build the system prompt for the AI interviewer.
     *
     * @param  array<string,mixed>      $job
     * @param  array<string,mixed>|null $avatar
     */
    public function buildSystemPrompt(array $job, ?array $avatar): string
    {
        $name        = $avatar['name'] ?? 'Alex';
        $personality = $avatar['personality'] ?? 'professional, warm, and encouraging';
        $companyName = $job['company_name'] ?? 'the company';
        $jobTitle    = $job['title'] ?? 'this position';
        $department  = $job['department'] ?? '';

        $prompt = "You are {$name}, an AI interviewer conducting a job interview on behalf of {$companyName}.\n";
        $prompt .= "You are interviewing a candidate for the role of {$jobTitle}";
        if ($department) {
            $prompt .= " in the {$department} department";
        }
        $prompt .= ".\n\n";

        $prompt .= "Your personality: {$personality}.\n\n";

        $prompt .= "Guidelines:\n";
        $prompt .= "- Be conversational, professional, and respectful at all times\n";
        $prompt .= "- Ask one question at a time\n";
        $prompt .= "- Briefly acknowledge or comment on the candidate's previous answer before asking the next question\n";
        $prompt .= "- Do not reveal that you are AI unless directly asked\n";
        $prompt .= "- Keep responses concise (2-4 sentences for acknowledgements, then the question)\n";
        $prompt .= "- Do not score or evaluate candidates during the interview\n";
        $prompt .= "- Maintain a positive, encouraging tone throughout\n";

        if (!empty($avatar['speaking_style'])) {
            $prompt .= "- Speaking style: {$avatar['speaking_style']}\n";
        }

        return $prompt;
    }
}
