<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseAgent.php';

class CommunityAgent extends BaseAgent
{
    public function execute(string $task, array $params): array
    {
        return match ($task) {
            'autoReplyComment'    => $this->autoReplyComment(
                $params['comment']    ?? '',
                $params['brandVoice'] ?? 'professional',
                $params['platform']   ?? 'instagram'
            ),
            'handleDM'            => $this->handleDM(
                $params['message']      ?? '',
                $params['platform']     ?? 'instagram',
                $params['brandContext'] ?? $this->getBrandContext()
            ),
            'detectSpam'          => $this->detectSpam($params['text'] ?? ''),
            'analyzeSentiment'    => $this->analyzeSentiment($params['text'] ?? ''),
            'qualifyLead'         => $this->qualifyLead($params['message'] ?? ''),
            'needsEscalation'     => ['needs_escalation' => $this->needsEscalation($params['text'] ?? '')],
            'generateFAQResponse' => ['response' => $this->generateFAQResponse(
                $params['question'] ?? '',
                $params['faqData']  ?? []
            )],
            'getQueue'   => $this->getQueueData(),
            'bulkReply'  => $this->bulkReplyQueue((array) ($params['comment_ids'] ?? [])),
            default => throw new \InvalidArgumentException("Unknown task: {$task}"),
        };
    }

    // =========================================================================
    // Auto-reply to a comment
    // =========================================================================

    public function autoReplyComment(
        string $comment,
        string $brandVoice,
        string $platform
    ): array {
        $brandName = $this->getBrandName();

        $system = <<<SYS
You are a community manager for {$brandName}. You craft authentic, helpful, and brand-appropriate
replies to social media comments. Brand voice: {$brandVoice}.
SYS;

        $prompt = <<<PROMPT
Reply to this {$platform} comment as the brand {$brandName}.
COMMENT: "{$comment}"
BRAND VOICE: {$brandVoice}

Requirements:
- Warm, genuine, and human — not robotic
- 1-3 sentences
- Match brand voice: {$brandVoice}
- If complaint: acknowledge + offer help
- If question: answer directly
- If compliment: thank sincerely
- If spam/irrelevant: "We appreciate all feedback! ❤️" (neutral)
- Include 0-1 relevant emoji at most

Return JSON:
{
  "reply": "the reply text",
  "reply_type": "compliment_response|question_answer|complaint_resolution|neutral",
  "sentiment_detected": "positive|negative|neutral",
  "is_spam": false,
  "confidence": 0.9
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 400);
        $result = $this->parseJsonFromAI($raw);

        if (empty($result['reply'])) {
            $result['reply'] = 'Thank you for your comment! We appreciate your engagement. ❤️';
        }

        $this->saveTask('autoReplyComment', ['comment' => $comment, 'platform' => $platform], $result, 'completed');

        return $result;
    }

    // =========================================================================
    // Handle DM
    // =========================================================================

    public function handleDM(
        string $message,
        string $platform,
        array  $brandContext
    ): array {
        $brandName  = $brandContext['brand_name'] ?? $this->getBrandName();
        $brandVoice = $brandContext['brand_voice'] ?? 'helpful and professional';

        $system = <<<SYS
You are a customer experience specialist for {$brandName}. You handle DMs with intelligence, empathy,
and brand consistency. You identify sales opportunities, support needs, and escalation triggers.
SYS;

        $prompt = <<<PROMPT
Handle this {$platform} DM for {$brandName}:
MESSAGE: "{$message}"
BRAND VOICE: {$brandVoice}

Analyze and respond:
{
  "reply": "the DM reply text (max 200 words, warm and helpful)",
  "intent": "sales_inquiry|support_request|partnership|general|complaint|spam",
  "is_lead": true/false,
  "lead_score": 0-100,
  "needs_escalation": true/false,
  "escalation_reason": "reason if escalation needed",
  "sentiment": "positive|negative|neutral",
  "suggested_next_action": "follow_up|close|escalate|ignore",
  "response_urgency": "immediate|within_hour|within_day|low"
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 600);
        $result = $this->parseJsonFromAI($raw);

        if (empty($result['reply'])) {
            $result['reply'] = "Hi! Thank you for reaching out to {$brandName}. We'll get back to you shortly! 😊";
        }

        return $result;
    }

    // =========================================================================
    // Spam Detection
    // =========================================================================

    public function detectSpam(string $text): array
    {
        $cacheKey = 'spam_' . md5($text);
        $cached   = $this->getMemory($cacheKey);
        if ($cached !== null) return (array) $cached;

        // Quick rule-based pre-check
        $spamSignals = [
            'buy followers',
            'get rich quick',
            'click here',
            'dm for promo',
            'free money',
            'limited offer',
            'check my bio',
        ];

        $lowerText     = strtolower($text);
        $quickSpam     = false;
        $matchedSignal = '';

        foreach ($spamSignals as $signal) {
            if (str_contains($lowerText, $signal)) {
                $quickSpam     = true;
                $matchedSignal = $signal;
                break;
            }
        }

        if ($quickSpam) {
            $result = [
                'is_spam'       => true,
                'confidence'    => 0.95,
                'spam_type'     => 'keyword_match',
                'matched_signal'=> $matchedSignal,
                'action'        => 'auto_hide',
            ];
            $this->setMemory($cacheKey, $result, 3600);
            return $result;
        }

        // AI-powered detection for ambiguous cases
        $system = 'You are a spam detection model for social media comments. Be precise and return JSON only.';

        $prompt = <<<PROMPT
Is this social media comment spam? Analyze:
TEXT: "{$text}"

Return JSON:
{
  "is_spam": true/false,
  "confidence": 0.0-1.0,
  "spam_type": "promotional|bot|scam|irrelevant|genuine|null",
  "reasoning": "brief explanation",
  "action": "auto_hide|flag_review|allow"
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 300);
        $result = $this->parseJsonFromAI($raw);

        if (!isset($result['is_spam'])) {
            $result = ['is_spam' => false, 'confidence' => 0.5, 'action' => 'allow'];
        }

        $this->setMemory($cacheKey, $result, 3600);
        return $result;
    }

    // =========================================================================
    // Sentiment Analysis (single text)
    // =========================================================================

    public function analyzeSentiment(string $text): array
    {
        $system = 'You are a sentiment analysis model. Return precise sentiment scores as JSON only.';

        $prompt = <<<PROMPT
Analyze the sentiment of this social media text:
TEXT: "{$text}"

Return JSON:
{
  "overall": "positive|negative|neutral|mixed",
  "score": -1.0 to 1.0,
  "emotions": {
    "joy": 0.0-1.0,
    "anger": 0.0-1.0,
    "sadness": 0.0-1.0,
    "fear": 0.0-1.0,
    "surprise": 0.0-1.0,
    "trust": 0.0-1.0
  },
  "intent": "complaint|praise|question|neutral|threat",
  "urgency": "immediate|high|medium|low",
  "key_phrases": ["phrase1", "phrase2"]
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 400);
        return $this->parseJsonFromAI($raw) ?: ['overall' => 'neutral', 'score' => 0];
    }

    // =========================================================================
    // Lead Qualification
    // =========================================================================

    public function qualifyLead(string $message): array
    {
        $brandName = $this->getBrandName();

        $system = 'You are a sales intelligence analyst. Qualify leads from social media messages with precision.';

        $prompt = <<<PROMPT
Qualify this social media message as a potential lead for {$brandName}:
MESSAGE: "{$message}"

Return JSON:
{
  "is_lead": true/false,
  "lead_quality": "hot|warm|cold|not_a_lead",
  "score": 0-100,
  "intent": "ready_to_buy|research_phase|price_checking|just_curious|not_interested",
  "buying_signals": ["signal1", "signal2"],
  "objections": ["objection1"],
  "recommended_approach": "description of how to engage",
  "follow_up_message": "suggested follow-up message",
  "urgency": "immediate|this_week|this_month|low",
  "product_interest": "what they seem interested in"
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 500);
        return $this->parseJsonFromAI($raw) ?: ['is_lead' => false, 'score' => 0];
    }

    // =========================================================================
    // Escalation Detection
    // =========================================================================

    public function needsEscalation(string $text): bool
    {
        // Quick rule-based check for high-urgency signals
        $escalationKeywords = [
            'legal action', 'lawsuit', 'refund', 'fraud', 'scam', 'stolen',
            'threatening', 'harassment', 'hate', 'crisis', 'emergency',
            'news', 'media', 'journalist', 'report you',
        ];

        $lowerText = strtolower($text);
        foreach ($escalationKeywords as $keyword) {
            if (str_contains($lowerText, $keyword)) {
                return true;
            }
        }

        // AI check for borderline cases
        $system = 'You are a social media crisis detection model. Return JSON only.';
        $prompt = <<<PROMPT
Does this social media message need immediate human escalation?
TEXT: "{$text}"

Return JSON: {"needs_escalation": true/false, "reason": "brief reason or null"}
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 100);
        $result = $this->parseJsonFromAI($raw);

        return (bool) ($result['needs_escalation'] ?? false);
    }

    // =========================================================================
    // FAQ Response
    // =========================================================================

    public function generateFAQResponse(string $question, array $faqData): string
    {
        $brandName  = $this->getBrandName();
        $faqContext = '';

        if (!empty($faqData)) {
            $faqContext = "BRAND FAQ DATA:\n";
            foreach ($faqData as $q => $a) {
                $faqContext .= "Q: {$q}\nA: {$a}\n\n";
            }
        }

        $system = "You are a knowledgeable customer support specialist for {$brandName}. Use the FAQ data to give accurate answers.";

        $prompt = <<<PROMPT
Answer this customer question:
QUESTION: "{$question}"

{$faqContext}

Requirements:
- Use FAQ data if available
- Be helpful, accurate, and concise
- Under 150 words
- Offer to help further if needed
- Match brand voice of {$brandName}

Return ONLY the answer text.
PROMPT;

        return trim($this->callClaude($prompt, $system, 300));
    }

    // =========================================================================
    // Internal queue helper
    // =========================================================================

    private function getQueueData(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, platform, commenter_username, comment_text, sentiment, is_lead, created_at
             FROM community_comments
             WHERE brand_id = ? AND status = "pending"
             ORDER BY created_at DESC
             LIMIT 50'
        );
        $stmt->execute([$this->brandId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function bulkReplyQueue(array $commentIds): array
    {
        if (empty($commentIds)) return [];

        $brandVoice = $this->getBrandContext()['brand_voice'] ?? 'professional';
        $results    = [];

        foreach ($commentIds as $id) {
            $stmt = $this->db->prepare(
                'SELECT id, platform, comment_text FROM community_comments WHERE id = ? AND brand_id = ? LIMIT 1'
            );
            $stmt->execute([$id, $this->brandId]);
            $comment = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$comment) continue;

            try {
                $reply = $this->autoReplyComment($comment['comment_text'], $brandVoice, $comment['platform']);
                $results[] = ['comment_id' => $id, 'reply' => $reply['reply'], 'status' => 'success'];
            } catch (\Throwable $e) {
                $results[] = ['comment_id' => $id, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
