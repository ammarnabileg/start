<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseAgent.php';

class CopywritingAgent extends BaseAgent
{
    // =========================================================================
    // BaseAgent::execute dispatcher
    // =========================================================================

    public function execute(string $task, array $params): array
    {
        return match ($task) {
            'generateCaption'     => ['caption'   => $this->generateCaption(
                $params['platform']     ?? 'instagram',
                $params['topic']        ?? '',
                $params['style']        ?? 'professional',
                $params['language']     ?? 'english',
                $params['brandContext'] ?? $this->getBrandContext()
            )],
            'generateLinkedInPost' => ['post' => $this->generateLinkedInPost(
                $params['topic']        ?? '',
                $params['tone']         ?? 'professional',
                $params['brandContext'] ?? $this->getBrandContext()
            )],
            'generateThread' => ['thread' => $this->generateThread(
                $params['topic']     ?? '',
                (int) ($params['numTweets'] ?? 7),
                $params['style']     ?? 'casual'
            )],
            'generateScript' => ['script' => $this->generateScript(
                $params['videoType']    ?? 'short_reel',
                (int) ($params['duration'] ?? 60),
                $params['hook']         ?? '',
                $params['brandContext'] ?? $this->getBrandContext()
            )],
            'generateHooks' => ['hooks' => $this->generateHooks(
                $params['topic']  ?? '',
                (int) ($params['count'] ?? 5),
                $params['style']  ?? 'casual'
            )],
            'generateCTA' => ['cta' => $this->generateCTA(
                $params['goal']     ?? 'engagement',
                $params['platform'] ?? 'instagram',
                $params['style']    ?? 'persuasive'
            )],
            'generateAdCopy' => ['ad_copy' => $this->generateAdCopy(
                $params['product']  ?? '',
                $params['audience'] ?? 'general audience',
                $params['platform'] ?? 'instagram',
                $params['style']    ?? 'persuasive'
            )],
            'generateCarouselText' => ['slides' => $this->generateCarouselText(
                $params['topic']  ?? '',
                (int) ($params['slides'] ?? 5),
                $params['style']  ?? 'educational'
            )],
            'generateCommentReply' => ['reply' => $this->generateCommentReply(
                $params['comment']    ?? '',
                $params['brandVoice'] ?? 'professional',
                $params['platform']   ?? 'instagram'
            )],
            'generateDMReply' => ['reply' => $this->generateDMReply(
                $params['message']      ?? '',
                $params['brandContext'] ?? $this->getBrandContext()
            )],
            default => throw new \InvalidArgumentException("Unknown task: {$task}"),
        };
    }

    // =========================================================================
    // Caption
    // =========================================================================

    public function generateCaption(
        string $platform,
        string $topic,
        string $style,
        string $language,
        array  $brandContext
    ): string {
        $cacheKey = "caption_{$platform}_{$style}_{$language}_" . md5($topic);
        $cached   = $this->getMemory($cacheKey);
        if ($cached !== null) return (string) $cached;

        $brandName  = $brandContext['brand_name'] ?? $this->getBrandName();
        $brandVoice = $brandContext['brand_voice'] ?? 'professional';
        $pillars    = $brandContext['content_pillars'] ?? '';

        $langInstruction = $this->getLanguageInstruction($language);

        $system = <<<SYS
You are an expert social media copywriter with deep expertise in creating viral, platform-optimized content.
You specialize in {$platform} content that drives engagement, reach, and conversions.
Brand: {$brandName}
Brand Voice: {$brandVoice}
Writing Style: {$style}
{$langInstruction}
SYS;

        $platformLimits = $this->getPlatformLimits($platform);

        $prompt = <<<PROMPT
Write a {$platform} caption for the following topic:
TOPIC: {$topic}
WRITING STYLE: {$style}
{$langInstruction}

Requirements:
- Maximum {$platformLimits['caption_limit']} characters
- Platform-optimized for {$platform}
- Include {$platformLimits['hashtag_count']} relevant hashtags at the end
- Include a compelling call-to-action
- Match the {$style} writing style
- Sound authentic and not AI-generated
- Content pillars context: {$pillars}

Return ONLY the caption text, no explanations.
PROMPT;

        $result = $this->callClaude($prompt, $system, 800);

        $this->saveTask('generateCaption', [
            'platform' => $platform,
            'topic'    => $topic,
            'style'    => $style,
            'language' => $language,
        ], ['caption' => $result], 'completed');

        $this->setMemory($cacheKey, $result, 1800); // 30 min cache
        return $result;
    }

    // =========================================================================
    // LinkedIn Post
    // =========================================================================

    public function generateLinkedInPost(
        string $topic,
        string $tone,
        array  $brandContext
    ): string {
        $brandName = $brandContext['brand_name'] ?? $this->getBrandName();

        $system = <<<SYS
You are a LinkedIn thought leadership content expert. You write posts that get thousands of impressions,
drive meaningful engagement from professionals, and establish authority. You understand LinkedIn's algorithm
and format content for maximum reach.
Brand: {$brandName}
Tone: {$tone}
SYS;

        $prompt = <<<PROMPT
Write a high-performing LinkedIn post about:
TOPIC: {$topic}
TONE: {$tone}

Structure requirements:
1. HOOK (first line that stops the scroll — max 150 characters)
2. 3-5 paragraph body with personal insight, data point, or story
3. Key takeaways (optional bullet list)
4. Call-to-action question to drive comments
5. 3-5 relevant hashtags

LinkedIn-specific guidelines:
- Start with a bold statement or counterintuitive insight
- Use short paragraphs (1-2 sentences max)
- Use line breaks for readability
- Sound human, not corporate
- Maximum 3,000 characters total

Return ONLY the complete LinkedIn post text.
PROMPT;

        return $this->callClaude($prompt, $system, 1200);
    }

    // =========================================================================
    // Twitter/X Thread
    // =========================================================================

    public function generateThread(
        string $topic,
        int    $numTweets,
        string $style
    ): array {
        $numTweets  = max(3, min(25, $numTweets));
        $brandName  = $this->getBrandName();

        $system = <<<SYS
You are a viral Twitter/X thread writer. You create threads that get thousands of retweets and replies.
You understand thread psychology — the hook, building curiosity, delivering value, and the CTA tweet.
SYS;

        $prompt = <<<PROMPT
Create a {$numTweets}-tweet thread about:
TOPIC: {$topic}
STYLE: {$style}
BRAND: {$brandName}

Rules:
- Tweet 1: Irresistible hook (under 280 chars) — promise what they'll learn
- Tweets 2 to {last}: Deliver value, facts, steps, or insights (each under 280 chars)
- Final tweet: Summary + CTA (follow, retweet, reply with thoughts)
- Maintain {$style} style throughout
- Number each tweet like: 1/
- Each tweet on its own line, separated by ---

Return ONLY the tweets, one per section, no explanations.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 2000);
        $tweets = [];

        // Parse tweets separated by --- or newlines with numbering
        $parts = preg_split('/---+|\n(?=\d+[\/\.])/m', $raw);
        foreach ($parts as $part) {
            $part = trim($part ?? '');
            if (!empty($part)) {
                $tweets[] = $part;
            }
        }

        // Fallback: split by double newlines
        if (count($tweets) < 3) {
            $tweets = array_values(array_filter(
                array_map('trim', preg_split('/\n{2,}/', $raw) ?: []),
                fn($t) => strlen($t) > 5
            ));
        }

        return array_slice($tweets, 0, $numTweets);
    }

    // =========================================================================
    // Video Script
    // =========================================================================

    public function generateScript(
        string $videoType,
        int    $duration,
        string $hook,
        array  $brandContext
    ): string {
        $brandName  = $brandContext['brand_name'] ?? $this->getBrandName();
        $brandVoice = $brandContext['brand_voice'] ?? 'engaging';

        $wordCount = (int) ($duration * 2.5); // ~150wpm

        $system = <<<SYS
You are a professional video scriptwriter specializing in social media content.
You understand the 3-second hook rule, retention curves, and how to write scripts that convert.
Brand: {$brandName}
Voice: {$brandVoice}
SYS;

        $hookLine = !empty($hook) ? "Use this hook: \"{$hook}\"" : 'Create a compelling hook that stops the scroll in 3 seconds';

        $prompt = <<<PROMPT
Write a complete video script for a {$videoType} ({$duration} seconds, ~{$wordCount} words).

{$hookLine}

Script structure:
[HOOK - 0-3 seconds]
(Dialogue/action that immediately grabs attention)

[PROBLEM/CONTEXT - 3-15 seconds]
(Establish why this matters)

[MAIN CONTENT - 15-{$dur2}s]
(Core value/message delivered clearly)

[CTA - Final 5 seconds]
(Clear call to action)

Include:
- Spoken dialogue in quotes
- [VISUAL/ACTION] directions in brackets
- B-roll suggestions as (NOTE: ...)
- On-screen text suggestions as [TEXT: ...]

Brand voice: {$brandVoice}
Return the complete formatted script.
PROMPT;

        // Fix for variable in heredoc
        $dur2   = max(15, $duration - 5);
        $prompt = str_replace('{$dur2}', (string) $dur2, $prompt);

        return $this->callClaude($prompt, $system, 2000);
    }

    // =========================================================================
    // Hooks
    // =========================================================================

    public function generateHooks(
        string $topic,
        int    $count,
        string $style
    ): array {
        $count = max(3, min(20, $count));

        $system = 'You are a viral copywriting expert who specializes in attention-grabbing hooks for social media.';

        $prompt = <<<PROMPT
Generate {$count} powerful hooks for this topic: "{$topic}"
Style: {$style}

Hook types to include (mix them):
- Question hooks (challenge assumptions)
- Stat/data hooks (surprising numbers)
- Contrarian hooks (challenge conventional wisdom)
- Story hooks (begin in the middle of action)
- List hooks (X things about...)
- Fear/desire hooks
- How-to hooks

Each hook should:
- Be under 150 characters
- Stop the scroll immediately
- Spark curiosity or urgency
- Be specific, not generic

Return ONLY the hooks, one per line, no numbering or explanations.
PROMPT;

        $raw = $this->callClaude($prompt, $system, 800);
        return array_slice($this->parseListFromAI($raw), 0, $count);
    }

    // =========================================================================
    // CTA
    // =========================================================================

    public function generateCTA(
        string $goal,
        string $platform,
        string $style
    ): string {
        $brandName = $this->getBrandName();

        $system = 'You are a conversion copywriter who writes CTAs that drive real action.';

        $prompt = <<<PROMPT
Write a compelling CTA for {$platform}.
GOAL: {$goal}
STYLE: {$style}
BRAND: {$brandName}

Requirements:
- Platform-appropriate for {$platform}
- Action-oriented with urgency or benefit
- Under 100 characters
- Matches {$style} tone
- Direct and specific

Return ONLY the CTA text, nothing else.
PROMPT;

        return trim($this->callClaude($prompt, $system, 200));
    }

    // =========================================================================
    // Ad Copy
    // =========================================================================

    public function generateAdCopy(
        string $product,
        string $audience,
        string $platform,
        string $style
    ): string {
        $brandName = $this->getBrandName();

        $system = <<<SYS
You are a performance marketing copywriter with expertise in {$platform} ads.
You write copy that drives clicks, conversions, and ROAS.
SYS;

        $prompt = <<<PROMPT
Write {$platform} ad copy for:
PRODUCT/SERVICE: {$product}
TARGET AUDIENCE: {$audience}
STYLE: {$style}
BRAND: {$brandName}

Include all of these components:
**HEADLINE:** (max 40 chars — bold, benefit-driven)
**PRIMARY TEXT:** (2-3 sentences — problem → solution → proof)
**DESCRIPTION:** (max 30 chars — supporting benefit)
**CTA BUTTON:** (single action word: Shop Now, Learn More, etc.)

Make it:
- Emotionally resonant with {$audience}
- Highlight the #1 benefit clearly
- Create urgency or scarcity if appropriate
- A/B testable (create 2 headline variants)

Return formatted ad copy with clear labels.
PROMPT;

        return $this->callClaude($prompt, $system, 600);
    }

    // =========================================================================
    // Carousel Text
    // =========================================================================

    public function generateCarouselText(
        string $topic,
        int    $slides,
        string $style
    ): array {
        $slides    = max(3, min(15, $slides));
        $brandName = $this->getBrandName();

        $system = 'You are a carousel/slideshow content expert who creates educational and engaging multi-slide posts.';

        $prompt = <<<PROMPT
Create a {$slides}-slide carousel post about: "{$topic}"
STYLE: {$style}
BRAND: {$brandName}

Slide structure:
- Slide 1: Hook slide (headline + one-line teaser)
- Slides 2-{$last}: Content slides (one key point per slide, max 50 words each)
- Final slide: Summary + CTA

For each slide, output:
SLIDE [N]:
HEADLINE: [bold headline]
BODY: [supporting text, max 50 words]
VISUAL NOTE: [brief description of ideal image/graphic]

Return all {$slides} slides in the format above.
PROMPT;

        $last   = $slides - 1;
        $prompt = str_replace('{$last}', (string) $last, $prompt);

        $raw    = $this->callClaude($prompt, $system, 2000);
        $slideArr = [];

        // Parse slides
        preg_match_all('/SLIDE\s+(\d+):(.*?)(?=SLIDE\s+\d+:|$)/si', $raw, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $slideContent = trim($match[2] ?? '');
            $headlineMatch = [];
            $bodyMatch     = [];
            preg_match('/HEADLINE:\s*(.*)/i', $slideContent, $headlineMatch);
            preg_match('/BODY:\s*([\s\S]*?)(?=VISUAL NOTE:|$)/i', $slideContent, $bodyMatch);

            $slideArr[] = [
                'slide'    => (int) ($match[1] ?? 0),
                'headline' => trim($headlineMatch[1] ?? ''),
                'body'     => trim($bodyMatch[1] ?? ''),
                'full'     => $slideContent,
            ];
        }

        // Fallback if parsing failed
        if (empty($slideArr)) {
            $parts = preg_split('/SLIDE\s+\d+:/i', $raw);
            foreach (array_slice($parts, 1) as $i => $part) {
                $slideArr[] = ['slide' => $i + 1, 'body' => trim($part), 'headline' => '', 'full' => trim($part)];
            }
        }

        return array_slice($slideArr, 0, $slides);
    }

    // =========================================================================
    // Comment Reply
    // =========================================================================

    public function generateCommentReply(
        string $comment,
        string $brandVoice,
        string $platform
    ): string {
        $brandName = $this->getBrandName();

        $system = <<<SYS
You are a community manager for {$brandName}. You write authentic, warm replies that build relationships
and reflect the brand voice: {$brandVoice}.
SYS;

        $prompt = <<<PROMPT
Write a reply to this {$platform} comment:
COMMENT: "{$comment}"
BRAND VOICE: {$brandVoice}
BRAND: {$brandName}

Reply requirements:
- Natural and conversational
- Acknowledge the commenter specifically
- 1-3 sentences maximum
- Reflect the brand voice: {$brandVoice}
- Do NOT use emojis excessively
- Never sound automated or copy-paste
- If it's a complaint, be empathetic and offer help
- If it's a compliment, be genuinely grateful
- If it's a question, answer helpfully

Return ONLY the reply text.
PROMPT;

        return trim($this->callClaude($prompt, $system, 300));
    }

    // =========================================================================
    // DM Reply
    // =========================================================================

    public function generateDMReply(
        string $message,
        array  $brandContext
    ): string {
        $brandName  = $brandContext['brand_name'] ?? $this->getBrandName();
        $brandVoice = $brandContext['brand_voice'] ?? 'friendly and helpful';

        $system = <<<SYS
You are a customer experience specialist and brand ambassador for {$brandName}.
You handle DMs with warmth, efficiency, and brand consistency.
Brand voice: {$brandVoice}
SYS;

        $prompt = <<<PROMPT
Write a DM reply to this message:
MESSAGE: "{$message}"
BRAND: {$brandName}
VOICE: {$brandVoice}

Reply requirements:
- Personal and warm, not template-sounding
- Address the actual question or need
- Under 200 words
- Include next step or resolution path
- Match brand voice: {$brandVoice}
- If it's a sales inquiry, gently move toward conversion
- If it's support, provide clear help

Return ONLY the DM reply text.
PROMPT;

        return trim($this->callClaude($prompt, $system, 400));
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function getLanguageInstruction(string $language): string
    {
        return match ($language) {
            'arabic' => 'IMPORTANT: Write ENTIRELY in Modern Standard Arabic (فصحى). Use proper Arabic grammar, RTL formatting, and culturally appropriate expressions for Arabic-speaking audiences.',
            'mixed'  => 'IMPORTANT: Write in a natural Arabic-English mix (Arabizi style common in Gulf/MENA social media). Use Arabic for emotional/cultural moments, English for technical terms and hashtags. Blend naturally.',
            default  => 'Write in clear, engaging English.',
        };
    }

    private function getPlatformLimits(string $platform): array
    {
        return match ($platform) {
            'twitter'   => ['caption_limit' => 280,   'hashtag_count' => 2],
            'instagram' => ['caption_limit' => 2200,  'hashtag_count' => 20],
            'linkedin'  => ['caption_limit' => 3000,  'hashtag_count' => 5],
            'facebook'  => ['caption_limit' => 63206, 'hashtag_count' => 5],
            'tiktok'    => ['caption_limit' => 2200,  'hashtag_count' => 10],
            'youtube'   => ['caption_limit' => 5000,  'hashtag_count' => 8],
            'threads'   => ['caption_limit' => 500,   'hashtag_count' => 5],
            'snapchat'  => ['caption_limit' => 250,   'hashtag_count' => 0],
            default     => ['caption_limit' => 2200,  'hashtag_count' => 10],
        };
    }
}
