<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseAgent.php';

class ResearchAgent extends BaseAgent
{
    public function execute(string $task, array $params): array
    {
        return match ($task) {
            'scanTrends'             => $this->scanTrends(
                $params['platform'] ?? 'instagram',
                $params['niche']    ?? ''
            ),
            'analyzeHashtags'        => $this->analyzeHashtags(
                $params['niche']    ?? '',
                $params['platform'] ?? 'instagram',
                (int) ($params['count'] ?? 20)
            ),
            'scrapeCompetitorContent'=> $this->scrapeCompetitorContent(
                (array) ($params['handles'] ?? []),
                $params['platform'] ?? 'instagram'
            ),
            'generateReactiveContent'=> $this->generateReactiveContent(
                $params['trend']  ?? '',
                $params['brand']  ?? $this->getBrandContext()
            ),
            'monitorNews'            => $this->monitorNews(
                (array) ($params['topics']     ?? []),
                (array) ($params['industries'] ?? [])
            ),
            'findViralSounds'        => $this->findViralSounds($params['platform'] ?? 'tiktok'),
            default => throw new \InvalidArgumentException("Unknown task: {$task}"),
        };
    }

    // =========================================================================
    // Scan Trends
    // =========================================================================

    public function scanTrends(string $platform, string $niche): array
    {
        $cacheKey = "trends_{$platform}_{$niche}";
        $cached   = $this->getMemory($cacheKey);
        if ($cached !== null) return (array) $cached;

        $brandName = $this->getBrandName();

        $system = <<<SYS
You are a social media trend analyst with real-time knowledge of what's trending across platforms.
You provide actionable trend intelligence for content creators.
SYS;

        $prompt = <<<PROMPT
Identify the top 10 trending topics/formats for {$platform} in the {$niche} niche right now (2025).
Brand: {$brandName}

Return JSON array:
[
  {
    "trend": "trend name",
    "type": "topic|hashtag|format|audio|challenge|meme",
    "platform": "{$platform}",
    "score": 1-100,
    "momentum": "rising|peak|declining",
    "example_angles": ["angle1", "angle2"],
    "ideal_format": "reel|carousel|story|post|thread",
    "best_time_to_post": "now|this_week|fading",
    "audience_affinity": "high|medium|low",
    "brand_fit": "high|medium|low",
    "sample_caption_idea": "brief caption idea"
  }
]

Focus on CURRENT trends as of 2025. Return ONLY valid JSON array.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 2000);
        $trends = $this->parseJsonFromAI($raw);

        if (empty($trends)) {
            $trends = $this->getFallbackTrends($platform, $niche);
        }

        $this->saveTask('scanTrends', ['platform' => $platform, 'niche' => $niche], $trends, 'completed');
        $this->setMemory($cacheKey, $trends, 3600); // 1hr cache

        return $trends;
    }

    // =========================================================================
    // Hashtag Analysis
    // =========================================================================

    public function analyzeHashtags(string $niche, string $platform, int $count): array
    {
        $cacheKey = "hashtags_{$platform}_{$niche}_{$count}";
        $cached   = $this->getMemory($cacheKey);
        if ($cached !== null) return (array) $cached;

        $system = 'You are a hashtag strategy expert with deep knowledge of platform algorithms and discoverability.';

        $prompt = <<<PROMPT
Generate {$count} optimized hashtags for {$platform} in the {$niche} niche.

Provide a mix:
- 20% mega hashtags (10M+ posts) — for visibility
- 40% mid-range hashtags (100K-10M posts) — for reach
- 40% niche hashtags (<100K posts) — for targeting

Return JSON array:
[
  {
    "hashtag": "#hashtagname",
    "size": "mega|large|medium|small|micro",
    "estimated_posts": number,
    "relevance_score": 0-100,
    "competition": "high|medium|low",
    "trending": true/false,
    "category": "brand|niche|audience|trending|evergreen"
  }
]

Order by relevance_score descending. Return ONLY valid JSON array.
PROMPT;

        $raw      = $this->callClaude($prompt, $system, 1500);
        $hashtags = $this->parseJsonFromAI($raw);

        if (!is_array($hashtags) || empty($hashtags)) {
            $hashtags = $this->getFallbackHashtags($niche, $count);
        }

        $this->setMemory($cacheKey, $hashtags, 7200); // 2hr cache

        return array_slice($hashtags, 0, $count);
    }

    // =========================================================================
    // Competitor Content Scraping (AI-simulated analysis)
    // =========================================================================

    public function scrapeCompetitorContent(array $handles, string $platform): array
    {
        if (empty($handles)) return [];

        $handleList = implode(', ', $handles);

        $system = 'You are a competitive intelligence researcher specializing in social media content analysis.';

        $prompt = <<<PROMPT
Analyze the typical content strategy of these {$platform} accounts: {$handleList}

For each account, return:
{
  "competitors": [
    {
      "handle": "@handle",
      "content_style": "description",
      "top_content_types": ["reel", "carousel", "etc"],
      "posting_frequency": "X per week",
      "avg_engagement_estimate": "X%",
      "signature_formats": ["format1", "format2"],
      "key_topics": ["topic1", "topic2", "topic3"],
      "audience_type": "description",
      "what_works": ["tactic1", "tactic2"],
      "content_gaps": ["gap1", "gap2"],
      "best_performing_style": "description"
    }
  ],
  "industry_patterns": ["pattern1", "pattern2", "pattern3"],
  "content_opportunities": ["opportunity1", "opportunity2"]
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 2000);
        return $this->parseJsonFromAI($raw);
    }

    // =========================================================================
    // Generate Reactive Content
    // =========================================================================

    public function generateReactiveContent(string $trend, array $brand): array
    {
        $brandName  = $brand['brand_name'] ?? $this->getBrandName();
        $brandVoice = $brand['brand_voice'] ?? 'professional';
        $pillars    = is_array($brand['content_pillars'] ?? null)
            ? implode(', ', $brand['content_pillars'])
            : ($brand['content_pillars'] ?? '');

        $system = <<<SYS
You are a reactive content strategist. You hijack trends to create on-brand, viral content that feels
timely and authentic. You balance trend relevance with brand safety.
SYS;

        $prompt = <<<PROMPT
Create reactive content ideas for this trend:
TREND: "{$trend}"
BRAND: {$brandName}
VOICE: {$brandVoice}
CONTENT PILLARS: {$pillars}

Generate 3-5 content angles:
{
  "trend_analysis": {
    "trend": "{$trend}",
    "is_brand_safe": true/false,
    "relevance_window": "24h|48h|1_week|evergreen",
    "risk_level": "low|medium|high"
  },
  "content_angles": [
    {
      "angle": "description of creative angle",
      "format": "reel|carousel|post|story|thread",
      "hook": "opening hook",
      "concept": "detailed concept",
      "caption_starter": "first line of caption",
      "hashtags": ["#trend", "#brand"],
      "urgency": "post_now|post_today|post_this_week"
    }
  ],
  "avoid": ["things to avoid for brand safety"]
}
Return ONLY valid JSON.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 1500);
        $result = $this->parseJsonFromAI($raw);

        $this->saveTask('generateReactiveContent', ['trend' => $trend], $result, 'completed');

        return $result;
    }

    // =========================================================================
    // Monitor News
    // =========================================================================

    public function monitorNews(array $topics, array $industries): array
    {
        $topicList    = implode(', ', $topics);
        $industryList = implode(', ', $industries);

        $system = 'You are a news monitoring and content opportunity analyst for social media teams.';

        $prompt = <<<PROMPT
Identify current news and events relevant to these topics and industries that could drive social media content.

TOPICS: {$topicList}
INDUSTRIES: {$industryList}
Current date context: 2025

Return JSON:
{
  "trending_topics": [
    {
      "topic": "topic name",
      "relevance": "high|medium|low",
      "content_angle": "how to use for social media",
      "urgency": "post_now|this_week|evergreen",
      "platforms": ["instagram", "linkedin"]
    }
  ],
  "industry_news": [
    {
      "headline": "news headline",
      "impact": "how it affects the audience",
      "content_idea": "content idea based on this"
    }
  ],
  "evergreen_opportunities": ["topic1", "topic2"]
}
Return ONLY valid JSON.
PROMPT;

        $raw = $this->callClaude($prompt, $system, 1500);
        return $this->parseJsonFromAI($raw);
    }

    // =========================================================================
    // Find Viral Sounds
    // =========================================================================

    public function findViralSounds(string $platform): array
    {
        $cacheKey = "viral_sounds_{$platform}";
        $cached   = $this->getMemory($cacheKey);
        if ($cached !== null) return (array) $cached;

        $system = 'You are a music and audio trend analyst for short-form video platforms.';

        $prompt = <<<PROMPT
Identify the top viral sounds/music tracks for {$platform} content creators in 2025.

Return JSON array:
[
  {
    "sound_name": "song or sound name",
    "artist": "artist name or creator",
    "vibe": "energetic|emotional|funny|inspirational|chill",
    "trending_since": "approximate timeframe",
    "usage_count_estimate": "1M+|500K+|100K+|50K+",
    "best_content_type": ["reel", "transition", "talking_head"],
    "niche_fit": ["fitness", "business", "lifestyle", "etc"],
    "momentum": "rising|peak|declining",
    "how_to_use": "brief creative direction"
  }
]

Return 15 sounds. Return ONLY valid JSON array.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 1200);
        $sounds = $this->parseJsonFromAI($raw);

        if (empty($sounds)) {
            $sounds = [];
        }

        $this->setMemory($cacheKey, $sounds, 3600);

        return $sounds;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function getFallbackTrends(string $platform, string $niche): array
    {
        return [
            [
                'trend'             => 'Educational carousels',
                'type'              => 'format',
                'platform'          => $platform,
                'score'             => 85,
                'momentum'          => 'rising',
                'example_angles'    => ['How-to guides', 'Industry tips'],
                'ideal_format'      => 'carousel',
                'best_time_to_post' => 'now',
                'audience_affinity' => 'high',
                'brand_fit'         => 'high',
                'sample_caption_idea' => '5 things about ' . $niche,
            ],
        ];
    }

    private function getFallbackHashtags(string $niche, int $count): array
    {
        $niclean = preg_replace('/[^a-z0-9]/', '', strtolower($niche));
        $base    = [
            ['hashtag' => '#' . $niclean,           'size' => 'medium',  'estimated_posts' => 500000,  'relevance_score' => 90, 'competition' => 'medium', 'trending' => false, 'category' => 'niche'],
            ['hashtag' => '#' . $niclean . 'tips',  'size' => 'small',   'estimated_posts' => 50000,   'relevance_score' => 85, 'competition' => 'low',    'trending' => false, 'category' => 'niche'],
            ['hashtag' => '#contentcreator',         'size' => 'mega',    'estimated_posts' => 50000000,'relevance_score' => 60, 'competition' => 'high',   'trending' => false, 'category' => 'audience'],
            ['hashtag' => '#socialmedia',            'size' => 'mega',    'estimated_posts' => 30000000,'relevance_score' => 55, 'competition' => 'high',   'trending' => false, 'category' => 'evergreen'],
        ];
        return array_slice(array_merge($base, $base), 0, $count);
    }
}
