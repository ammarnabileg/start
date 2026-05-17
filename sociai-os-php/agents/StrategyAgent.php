<?php

declare(strict_types=1);

require_once __DIR__ . '/BaseAgent.php';

class StrategyAgent extends BaseAgent
{
    public function execute(string $task, array $params): array
    {
        return match ($task) {
            'analyzeDocument'     => $this->analyzeDocument($params['file_path'] ?? ''),
            'extractBrandTone'    => ['brand_tone' => $this->extractBrandTone($params['text'] ?? '')],
            'extractContentPillars' => ['pillars'  => $this->extractContentPillars($params['text'] ?? '')],
            'extractTargetAudience' => ['audience' => $this->extractTargetAudience($params['text'] ?? '')],
            'generateMonthlyPlan' => $this->generateMonthlyPlan($params['strategy'] ?? []),
            'generateCampaignBrief' => ['brief'    => $this->generateCampaignBrief(
                $params['goal']      ?? '',
                $params['audience']  ?? '',
                $params['platforms'] ?? []
            )],
            default => throw new \InvalidArgumentException("Unknown task: {$task}"),
        };
    }

    // =========================================================================
    // Analyze uploaded strategy document
    // =========================================================================

    public function analyzeDocument(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $text = $this->extractTextFromFile($filePath, $ext);

        if (empty(trim($text))) {
            throw new \RuntimeException('Could not extract text from document');
        }

        // Truncate to fit token limits (approx 6000 words)
        $text = mb_substr($text, 0, 24000);

        $system = <<<SYS
You are a senior brand strategist and social media consultant. You analyze brand documents and extract
actionable strategic insights. You are analytical, structured, and produce output as clean JSON.
SYS;

        $prompt = <<<PROMPT
Analyze this brand/strategy document and extract key information.

DOCUMENT CONTENT:
{$text}

Return a JSON object with these exact keys:
{
  "brand_name": "string",
  "brand_tone": "string (1-2 sentences describing tone of voice)",
  "brand_voice": "string (key adjectives: professional, warm, bold, etc.)",
  "mission_statement": "string",
  "value_proposition": "string",
  "content_pillars": ["pillar1", "pillar2", "pillar3", "pillar4", "pillar5"],
  "target_audience": {
    "primary": "description of primary audience",
    "demographics": "age, location, interests",
    "pain_points": ["pain1", "pain2", "pain3"],
    "desires": ["desire1", "desire2", "desire3"]
  },
  "competitors": ["competitor1", "competitor2"],
  "key_differentiators": ["diff1", "diff2", "diff3"],
  "recommended_platforms": ["platform1", "platform2"],
  "content_themes": ["theme1", "theme2", "theme3"],
  "posting_frequency": "X times per week per platform",
  "campaign_ideas": ["idea1", "idea2", "idea3"],
  "summary": "2-3 sentence strategic summary"
}

Return ONLY valid JSON, no markdown.
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 2000);
        $result = $this->parseJsonFromAI($raw);

        $this->saveTask('analyzeDocument', ['file' => basename($filePath)], $result, 'completed');
        $this->log("Document analyzed: {$filePath}");

        return $result;
    }

    // =========================================================================
    // Extract brand tone from text
    // =========================================================================

    public function extractBrandTone(string $text): string
    {
        $text   = mb_substr($text, 0, 10000);
        $system = 'You are a brand voice expert. Analyze text and identify brand tone in one precise sentence.';

        $prompt = <<<PROMPT
Analyze this text and identify the brand tone of voice in 1-2 sentences:
TEXT: {$text}

Describe:
- Core personality (e.g., "authoritative yet approachable")
- Communication style (formal/informal, direct/conversational)
- Emotional quality (inspiring, educational, humorous, etc.)

Return ONLY the tone description, 1-2 sentences.
PROMPT;

        return trim($this->callClaude($prompt, $system, 200));
    }

    // =========================================================================
    // Extract content pillars
    // =========================================================================

    public function extractContentPillars(string $text): array
    {
        $text   = mb_substr($text, 0, 10000);
        $system = 'You are a content strategy expert. Extract the core content pillars that should drive all social media content.';

        $prompt = <<<PROMPT
Based on this brand/strategy text, identify 4-6 content pillars for social media:
TEXT: {$text}

A content pillar is a core topic/theme the brand should consistently post about.
Examples: "Industry Education", "Behind the Scenes", "Customer Success Stories", "Product Features", "Thought Leadership"

Return ONLY the pillar names as a JSON array:
["Pillar 1", "Pillar 2", "Pillar 3", "Pillar 4", "Pillar 5"]
PROMPT;

        $raw    = $this->callClaude($prompt, $system, 400);
        $parsed = $this->parseJsonFromAI($raw);

        return !empty($parsed) ? array_values(array_filter($parsed, 'is_string')) : $this->parseListFromAI($raw);
    }

    // =========================================================================
    // Extract target audience
    // =========================================================================

    public function extractTargetAudience(string $text): array
    {
        $text   = mb_substr($text, 0, 10000);
        $system = 'You are a market research expert. Extract detailed target audience profiles from brand documents.';

        $prompt = <<<PROMPT
Extract target audience information from this text:
TEXT: {$text}

Return JSON:
{
  "primary_audience": "description",
  "age_range": "e.g. 25-40",
  "gender_split": "e.g. 60% female, 40% male",
  "location": "geographic focus",
  "interests": ["interest1", "interest2", "interest3"],
  "pain_points": ["pain1", "pain2", "pain3"],
  "goals": ["goal1", "goal2", "goal3"],
  "social_platforms": ["platform1", "platform2"],
  "content_preferences": ["format1", "format2"],
  "buyer_personas": [
    {"name": "Persona Name", "description": "Brief description"}
  ]
}
Return ONLY valid JSON.
PROMPT;

        $raw = $this->callClaude($prompt, $system, 800);
        return $this->parseJsonFromAI($raw);
    }

    // =========================================================================
    // Generate monthly content plan
    // =========================================================================

    public function generateMonthlyPlan(array $strategy): array
    {
        $cacheKey = 'monthly_plan_' . md5(json_encode($strategy));
        $cached   = $this->getMemory($cacheKey);
        if ($cached !== null) return (array) $cached;

        $brandName = $strategy['brand_name'] ?? $this->getBrandName();
        $pillars   = is_array($strategy['content_pillars'] ?? null)
            ? implode(', ', $strategy['content_pillars'])
            : ($strategy['content_pillars'] ?? '');
        $platforms = is_array($strategy['recommended_platforms'] ?? null)
            ? implode(', ', $strategy['recommended_platforms'])
            : 'Instagram, LinkedIn';
        $frequency = $strategy['posting_frequency'] ?? '5 posts per week';

        $system = 'You are a senior social media strategist creating comprehensive monthly content plans.';

        $prompt = <<<PROMPT
Create a 30-day social media content calendar.
BRAND: {$brandName}
CONTENT PILLARS: {$pillars}
PLATFORMS: {$platforms}
FREQUENCY: {$frequency}

Return a JSON array of 20-25 content pieces:
[
  {
    "day": 1,
    "platform": "instagram",
    "content_type": "carousel|reel|story|post|thread",
    "pillar": "Pillar Name",
    "topic": "Specific post topic",
    "angle": "The unique angle/hook",
    "hashtag_theme": "theme for hashtags",
    "notes": "production notes"
  }
]

Distribute across all platforms, vary content types, follow the pillar distribution.
Return ONLY valid JSON array.
PROMPT;

        $raw  = $this->callClaude($prompt, $system, 3000);
        $plan = $this->parseJsonFromAI($raw);

        if (empty($plan)) {
            $plan = [['day' => 1, 'platform' => 'instagram', 'topic' => 'Welcome post', 'pillar' => 'Brand Story']];
        }

        $this->saveTask('generateMonthlyPlan', $strategy, $plan, 'completed');
        $this->setMemory($cacheKey, $plan, 86400); // 24hr cache

        return $plan;
    }

    // =========================================================================
    // Generate campaign brief
    // =========================================================================

    public function generateCampaignBrief(
        string $goal,
        string $audience,
        array  $platforms
    ): string {
        $brandName     = $this->getBrandName();
        $brandContext  = $this->getBrandContext();
        $brandVoice    = $brandContext['brand_voice'] ?? 'professional';
        $platformList  = implode(', ', $platforms);

        $system = 'You are a creative director writing comprehensive campaign briefs for social media campaigns.';

        $prompt = <<<PROMPT
Write a complete campaign brief for:
BRAND: {$brandName}
CAMPAIGN GOAL: {$goal}
TARGET AUDIENCE: {$audience}
PLATFORMS: {$platformList}
BRAND VOICE: {$brandVoice}

Campaign brief structure:
# Campaign Brief

## Campaign Name
[Catchy, memorable campaign name]

## Executive Summary
[2-3 sentences]

## Campaign Objectives
[SMART objectives — 3-5 bullet points]

## Target Audience
[Detailed audience profile]

## Key Messages
[3-5 core messages]

## Campaign Concept
[The big creative idea]

## Platform Strategy
[Platform-specific tactics for: {$platformList}]

## Content Themes
[4-6 content themes with examples]

## KPIs & Success Metrics
[Measurable goals]

## Campaign Timeline
[Week-by-week breakdown over 4 weeks]

## Budget Allocation Suggestions
[How to allocate budget %]

Return the complete brief in markdown format.
PROMPT;

        return $this->callClaude($prompt, $system, 3000);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function extractTextFromFile(string $filePath, string $ext): string
    {
        switch ($ext) {
            case 'txt':
                return file_get_contents($filePath) ?: '';

            case 'pdf':
                // Use pdftotext if available, otherwise read raw
                if (function_exists('shell_exec') && !ini_get('safe_mode')) {
                    $escaped = escapeshellarg($filePath);
                    $text    = shell_exec("pdftotext {$escaped} - 2>/dev/null");
                    if (!empty($text)) return $text;
                }
                // Fallback: read PDF and extract text manually (basic)
                $content = file_get_contents($filePath) ?: '';
                // Extract text streams from PDF
                preg_match_all('/BT(.+?)ET/s', $content, $matches);
                $text = '';
                foreach ($matches[1] as $textBlock) {
                    preg_match_all('/\((.*?)\)/', $textBlock, $strings);
                    $text .= implode(' ', $strings[1]) . ' ';
                }
                return $text ?: $content;

            case 'docx':
                // Read DOCX (ZIP-based) word/document.xml
                if (class_exists('ZipArchive')) {
                    $zip = new \ZipArchive();
                    if ($zip->open($filePath) === true) {
                        $xml  = $zip->getFromName('word/document.xml');
                        $zip->close();
                        if ($xml) {
                            // Strip XML tags and extract text
                            return strip_tags(str_replace(['</w:p>', '</w:r>'], "\n", $xml));
                        }
                    }
                }
                return '';

            default:
                return file_get_contents($filePath) ?: '';
        }
    }
}
