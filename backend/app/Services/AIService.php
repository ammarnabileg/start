<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\Tenant;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Config;

class AIService
{
    private string $model = 'gpt-4o';

    public function __construct(private ?Tenant $tenant = null)
    {
        if ($tenant && $key = $tenant->getEffectiveOpenaiKey()) {
            Config::set('openai.api_key', $key);
        }
    }

    public function generateJobDescription(array $jobData): array
    {
        $prompt = "You are an expert HR consultant. Generate a complete job posting in both Arabic and English for:\n" . json_encode($jobData) . "\n\nReturn JSON with: description, description_ar, requirements, responsibilities, benefits, salary_suggestion";

        $response = $this->chat([['role' => 'user', 'content' => $prompt]], 'job_builder');

        return json_decode($response, true) ?? ['description' => $response];
    }

    public function generateInterviewQuestions(array $jobData, array $criteria): array
    {
        $prompt = "Generate {$jobData['max_questions']} interview questions for: " . json_encode($jobData) . "\nCriteria: " . json_encode($criteria) . "\n\nReturn JSON array with: question, question_ar, skill_category, difficulty, ideal_answer_hints";

        $response = $this->chat([['role' => 'user', 'content' => $prompt]], 'interview_builder');

        return json_decode($response, true) ?? [];
    }

    public function analyzeCv(string $cvText, array $jobData): array
    {
        $prompt = "Analyze this CV for the job:\n\nCV:\n{$cvText}\n\nJob:\n" . json_encode($jobData) . "\n\nReturn JSON: match_score (0-100), strengths (array), weaknesses (array), missing_skills (array), experience_years, skills (array), companies (array), education (array), risk_indicators (array), notes";

        $response = $this->chat([['role' => 'user', 'content' => $prompt]], 'cv_analysis');

        return json_decode($response, true) ?? ['match_score' => 0];
    }

    public function generateInterviewResponse(array $messages, array $jobData, array $criteria, array $questionBank, int $questionsAsked, int $maxQuestions): string
    {
        $systemPrompt = $this->buildInterviewSystemPrompt($jobData, $criteria, $questionBank, $questionsAsked, $maxQuestions);

        $response = $this->chat(
            array_merge([['role' => 'system', 'content' => $systemPrompt]], $messages),
            'interview_conduct'
        );

        return $response;
    }

    public function evaluateInterview(array $transcript, array $jobData, array $criteria, array $cvAnalysis): array
    {
        $transcriptText = collect($transcript)->map(fn($m) => "{$m['role']}: {$m['content']}")->join("\n");

        $prompt = "Evaluate this recruitment interview:\n\nJob: " . json_encode($jobData) . "\n\nCriteria: " . json_encode($criteria) . "\n\nCV Analysis: " . json_encode($cvAnalysis) . "\n\nTranscript:\n{$transcriptText}\n\n"
            . "Return JSON:\n"
            . "overall_score (0-100)\n"
            . "recommendation (strong_recommendation|suitable|possible_fit|not_recommended)\n"
            . "executive_summary (string)\n"
            . "strengths (array of strings)\n"
            . "weaknesses (array of strings)\n"
            . "missing_skills (array of strings)\n"
            . "criteria_scores (array: {criterion, score 0-5, notes})\n"
            . "skill_scores (array: {skill_key, score 0-100, confidence 0-1, evidence})\n"
            . "disc_profile (D, I, S, C percentages)\n"
            . "big_five (openness, conscientiousness, extraversion, agreeableness, neuroticism 0-100)\n"
            . "growth_score (0-100)\n"
            . "stress_score (0-100)\n"
            . "leadership_style (string)\n"
            . "learning_ability (string)\n"
            . "risk_flags (array: {flag_type, severity, description, evidence})\n"
            . "detected_language (ar|en)";

        $response = $this->chat([['role' => 'user', 'content' => $prompt]], 'ai_evaluation');

        return json_decode($response, true) ?? [];
    }

    public function matchCandidates(array $candidates, array $jobData): array
    {
        $prompt = "Rank these candidates for the job:\n\nJob: " . json_encode($jobData) . "\n\nCandidates: " . json_encode($candidates) . "\n\nReturn JSON array sorted by fit_score desc: {candidate_id, fit_score, ranking_reason}";

        $response = $this->chat([['role' => 'user', 'content' => $prompt]], 'candidate_matching');

        return json_decode($response, true) ?? [];
    }

    public function searchTalentPool(string $query, array $candidates): array
    {
        $prompt = "Search talent pool semantically.\n\nQuery: {$query}\n\nCandidates: " . json_encode($candidates) . "\n\nReturn JSON array of matching candidate_ids sorted by relevance with match_reason";

        $response = $this->chat([['role' => 'user', 'content' => $prompt]], 'talent_pool_search');

        return json_decode($response, true) ?? [];
    }

    public function recruitmentCopilot(string $question, array $context): string
    {
        $prompt = "You are an AI recruitment copilot. Answer based on this recruitment data:\n" . json_encode($context) . "\n\nQuestion: {$question}";

        return $this->chat([['role' => 'user', 'content' => $prompt]], 'copilot');
    }

    public function generateOffer(array $applicationData): array
    {
        $prompt = "Generate an offer letter for:\n" . json_encode($applicationData) . "\n\nReturn JSON: title, body, salary_suggestion, benefits_suggestion, negotiation_notes";

        $response = $this->chat([['role' => 'user', 'content' => $prompt]], 'offer_generator');

        return json_decode($response, true) ?? [];
    }

    public function generateEmail(string $type, array $data): array
    {
        $prompt = "Generate a professional recruitment email ({$type}) in Arabic and English for:\n" . json_encode($data) . "\n\nReturn JSON: subject, subject_ar, body, body_ar";

        $response = $this->chat([['role' => 'user', 'content' => $prompt]], 'email_generator');

        return json_decode($response, true) ?? [];
    }

    public function compareCandidates(array $candidates, ?string $question = null): array
    {
        $prompt = "Compare these candidates side by side:\n" . json_encode($candidates);
        if ($question) $prompt .= "\n\nSpecific question: {$question}";
        $prompt .= "\n\nReturn JSON: comparison_table (array of {criterion, values per candidate}), best_candidate_id, reasoning, recommendation";

        $response = $this->chat([['role' => 'user', 'content' => $prompt]], 'candidate_comparison');

        return json_decode($response, true) ?? [];
    }

    private function buildInterviewSystemPrompt(array $jobData, array $criteria, array $questionBank, int $questionsAsked, int $maxQuestions): string
    {
        $remaining = $maxQuestions - $questionsAsked;
        $criteriaText = collect($criteria)->map(fn($c) => "- {$c['criterion']} (weight: {$c['weight']}%, target: {$c['target_score']}/5)")->join("\n");
        $questionsText = collect($questionBank)->take(10)->map(fn($q) => "- {$q['question']}")->join("\n");

        return "You are {$jobData['avatar_name']}, a professional AI interviewer conducting a first-round interview for {$jobData['title']} at {$jobData['company_name']}.\n\n"
            . "LANGUAGE: Detect the candidate's language and respond in the same language (Arabic or English).\n\n"
            . "JOB DETAILS:\n" . json_encode($jobData) . "\n\n"
            . "EVALUATION CRITERIA:\n{$criteriaText}\n\n"
            . "QUESTION BANK (use as reference):\n{$questionsText}\n\n"
            . "INTERVIEW RULES:\n"
            . "- Questions asked so far: {$questionsAsked}/{$maxQuestions}\n"
            . "- Remaining: {$remaining} questions\n"
            . "- Ask one question at a time\n"
            . "- Ask follow-up questions when answers are vague\n"
            . "- Be professional but warm\n"
            . "- Never reveal you are AI unless directly asked\n"
            . ($remaining <= 2 ? "- You are near the end. Wrap up naturally after next 1-2 questions.\n" : "")
            . ($remaining === 0 ? "- The interview is complete. Thank the candidate warmly and close the interview.\n" : "");
    }

    private function chat(array $messages, string $feature): string
    {
        $response = OpenAI::chat()->create([
            'model' => $this->model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 4000,
        ]);

        $usage = $response->usage;
        AiUsageLog::track($feature, $usage->promptTokens, $usage->completionTokens, $this->model);

        return $response->choices[0]->message->content;
    }

    public function transcribeAudio(string $filePath): string
    {
        $response = OpenAI::audio()->transcribe([
            'model' => 'whisper-1',
            'file' => fopen($filePath, 'rb'),
            'language' => 'ar',
        ]);

        AiUsageLog::track('transcription', 0, 0, 'whisper-1', null, null);

        return $response->text;
    }

    public function chatStream(array $messages, string $feature): \Generator
    {
        $stream = OpenAI::chat()->createStreamed([
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => 1000,
        ]);

        $fullContent = '';
        foreach ($stream as $response) {
            $delta = $response->choices[0]->delta->content;
            if ($delta) {
                $fullContent .= $delta;
                yield $delta;
            }
        }
    }
}
