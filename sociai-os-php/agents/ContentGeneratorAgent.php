<?php
/**
 * SociAI OS - Content Generator Agent
 * AI-powered content generation for all platforms and languages.
 */

declare(strict_types=1);

namespace SociAI\Agents;

use SociAI\Core\{AI, Database, Security};
use SociAI\Models\{Brand, Content, Analytics};

class ContentGeneratorAgent
{
    private Database  $db;
    private Brand     $brandModel;
    private Content   $contentModel;
    private Analytics $analyticsModel;

    public function __construct()
    {
        $this->db             = Database::getInstance();
        $this->brandModel     = new Brand();
        $this->contentModel   = new Content();
        $this->analyticsModel = new Analytics();
    }

    // --------------------------------------------------------
    // Main generation entry point
    // --------------------------------------------------------
    public function generate(array $params): array
    {
        $taskId = Security::generateUUID();
        $brandId = $params['brand_id'];

        // Create task record
        $this->db->insert('agent_tasks', [
            'id'         => $taskId,
            'brand_id'   => $brandId,
            'user_id'    => $params['user_id'] ?? null,
            'agent_type' => 'content_generator',
            'task_name'  => 'Generate ' . ($params['content_type'] ?? 'post'),
            'input_data' => json_encode($params),
            'status'     => 'running',
            'progress'   => 0,
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $this->updateProgress($taskId, 10);

            // Load brand strategy for context
            $strategy = $this->brandModel->getActiveStrategy($brandId);
            $brand    = $this->brandModel->findOrFail($brandId);

            $this->updateProgress($taskId, 20);

            // Build generation prompt
            $systemPrompt = $this->buildSystemPrompt($brand, $strategy);
            $userPrompt   = $this->buildGenerationPrompt($params);

            $this->updateProgress($taskId, 30);

            // Generate main content
            $aiResult = AI::generate($userPrompt, $systemPrompt, 2048, 0.8);
            $rawText  = $aiResult['text'];

            $this->updateProgress($taskId, 60);

            // Parse the structured response
            $parsed = $this->parseGeneratedContent($rawText, $params);

            $this->updateProgress($taskId, 75);

            // Generate platform-specific variants
            $variants = $this->generatePlatformVariants($parsed, $params, $strategy);

            $this->updateProgress($taskId, 85);

            // Calculate viral score prediction
            $viralScore = $this->predictViralScore($parsed, $params);

            $this->updateProgress($taskId, 92);

            // Persist content
            $contentId = null;
            if (!empty($params['save'])) {
                $contentData = [
                    'brand_id'          => $brandId,
                    'campaign_id'       => $params['campaign_id'] ?? null,
                    'title'             => $parsed['title'] ?? '',
                    'content_type'      => $params['content_type'] ?? 'post',
                    'topic'             => $params['topic'] ?? '',
                    'writing_style'     => $params['writing_style'] ?? 'professional',
                    'language'          => $params['language'] ?? 'english',
                    'body_text'         => $parsed['body_text'],
                    'hook'              => $parsed['hook'] ?? '',
                    'cta'               => $parsed['cta'] ?? '',
                    'hashtags'          => $parsed['hashtags'] ?? [],
                    'platform_variants' => $variants,
                    'viral_score'       => $viralScore,
                    'approval_status'   => 'draft',
                    'created_by'        => $params['user_id'] ?? null,
                    'ai_generated'      => 1,
                    'ai_prompt_used'    => $userPrompt,
                ];
                $content   = $this->contentModel->create($contentData);
                $contentId = $content['id'];
            }

            $this->updateProgress($taskId, 100);

            $output = [
                'content_id'       => $contentId,
                'title'            => $parsed['title'] ?? '',
                'body_text'        => $parsed['body_text'],
                'hook'             => $parsed['hook'] ?? '',
                'cta'              => $parsed['cta'] ?? '',
                'hashtags'         => $parsed['hashtags'] ?? [],
                'platform_variants'=> $variants,
                'viral_score'      => $viralScore,
                'tokens_used'      => $aiResult['input_tokens'] + $aiResult['output_tokens'],
                'cost_usd'         => $aiResult['cost_usd'],
            ];

            $this->db->update('agent_tasks', [
                'output_data' => json_encode($output),
                'status'      => 'completed',
                'progress'    => 100,
                'tokens_used' => $aiResult['input_tokens'] + $aiResult['output_tokens'],
                'cost_usd'    => $aiResult['cost_usd'],
                'completed_at'=> date('Y-m-d H:i:s'),
            ], 'id = ?', [$taskId]);

            return ['task_id' => $taskId, 'success' => true, 'output' => $output];

        } catch (\Throwable $e) {
            $this->db->update('agent_tasks', [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => date('Y-m-d H:i:s'),
            ], 'id = ?', [$taskId]);
            throw $e;
        }
    }

    // --------------------------------------------------------
    // Bulk generation
    // --------------------------------------------------------
    public function generateBatch(string $brandId, array $requests, string $userId): array
    {
        $results = [];
        foreach ($requests as $req) {
            $req['brand_id'] = $brandId;
            $req['user_id']  = $userId;
            $req['save']     = true;
            $results[] = $this->generate($req);
        }
        return $results;
    }

    // --------------------------------------------------------
    // Prompt builders
    // --------------------------------------------------------
    private function buildSystemPrompt(array $brand, ?array $strategy): string
    {
        $pillars    = '';
        $tone       = 'professional and engaging';
        $goals      = '';
        $audience   = '';

        if ($strategy) {
            $pillars  = is_string($strategy['content_pillars']) ? $strategy['content_pillars'] : json_encode($strategy['content_pillars'] ?? []);
            $tone     = $strategy['brand_tone'] ?? $tone;
            $goals    = is_string($strategy['business_goals']) ? $strategy['business_goals'] : json_encode($strategy['business_goals'] ?? []);
            $audience = is_string($strategy['target_audience']) ? $strategy['target_audience'] : json_encode($strategy['target_audience'] ?? []);
        }

        return <<<PROMPT
You are an expert social media content strategist for the brand "{$brand['name']}" in the {$brand['industry']} industry.

Brand Voice & Tone: {$tone}
Content Pillars: {$pillars}
Business Goals: {$goals}
Target Audience: {$audience}

Always produce content that:
1. Aligns perfectly with the brand voice and values
2. Is designed to maximise engagement and virality
3. Includes a strong hook in the first line
4. Has a clear, compelling call-to-action
5. Uses platform-appropriate formatting
6. Is culturally sensitive and inclusive
7. Avoids generic phrases — be specific, authentic, and human

Respond ONLY in the structured JSON format requested. No additional commentary.
PROMPT;
    }

    private function buildGenerationPrompt(array $params): string
    {
        $platform    = $params['platform'] ?? 'instagram';
        $contentType = $params['content_type'] ?? 'post';
        $topic       = $params['topic'] ?? '';
        $language    = $params['language'] ?? 'english';
        $style       = $params['writing_style'] ?? 'professional';
        $goal        = $params['goal'] ?? 'engagement';
        $tone        = $params['tone'] ?? 'inspiring';
        $extras      = $params['additional_notes'] ?? '';

        $langInstruction = match ($language) {
            'arabic' => 'Write entirely in Modern Standard Arabic (فصحى) unless the brand uses a specific dialect. Ensure proper Arabic punctuation and RTL formatting considerations.',
            'mixed'  => 'Write primarily in Arabic with natural English code-switching for technical terms, brand names, and hashtags. This is common in Gulf/Levant social media.',
            default  => 'Write in clear, engaging English.',
        };

        return <<<PROMPT
Create a {$contentType} for {$platform} about: {$topic}

Requirements:
- Language: {$language}. {$langInstruction}
- Writing style: {$style}
- Tone: {$tone}
- Primary goal: {$goal}
- Platform: {$platform}
{$extras}

Return a JSON object with EXACTLY this structure:
{
  "title": "Internal content title (3-7 words)",
  "hook": "Opening line that stops the scroll (max 150 chars)",
  "body_text": "Full post body with proper formatting for {$platform}",
  "cta": "Clear call-to-action sentence",
  "hashtags": ["tag1","tag2","tag3",...],
  "emoji_suggestions": ["🚀","💡",...],
  "best_posting_time": "Suggested day/time to post",
  "viral_elements": ["element1","element2"],
  "content_warnings": []
}
PROMPT;
    }

    // --------------------------------------------------------
    // Parse AI response
    // --------------------------------------------------------
    private function parseGeneratedContent(string $rawText, array $params): array
    {
        // Extract JSON from the response
        $jsonStr = $rawText;
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/i', $rawText, $m)) {
            $jsonStr = $m[1];
        } elseif (preg_match('/\{[\s\S]+\}/s', $rawText, $m)) {
            $jsonStr = $m[0];
        }

        $parsed = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            // Fallback: use raw text as body
            return [
                'title'     => 'Generated Content',
                'body_text' => $rawText,
                'hook'      => '',
                'cta'       => '',
                'hashtags'  => [],
            ];
        }
        return $parsed;
    }

    // --------------------------------------------------------
    // Platform variants
    // --------------------------------------------------------
    private function generatePlatformVariants(array $content, array $params, ?array $strategy): array
    {
        $targetPlatforms = $params['platforms'] ?? [$params['platform'] ?? 'instagram'];
        $variants        = [];

        foreach ($targetPlatforms as $platform) {
            $variants[$platform] = $this->adaptForPlatform($content, $platform);
        }
        return $variants;
    }

    private function adaptForPlatform(array $content, string $platform): array
    {
        $body = $content['body_text'] ?? '';
        $adapted = match ($platform) {
            'twitter' => [
                'body_text' => substr($body, 0, 270) . (strlen($body) > 270 ? '...' : ''),
                'max_chars' => 280,
                'thread'    => str_split($body, 270),
            ],
            'linkedin' => [
                'body_text' => $body,
                'note'      => 'Add professional framing. Lead with insight.',
            ],
            'instagram' => [
                'body_text' => $body,
                'caption_tip'=> 'Put CTA at end. Hashtags in first comment.',
                'hashtags'  => $content['hashtags'] ?? [],
            ],
            'tiktok' => [
                'body_text' => substr($body, 0, 2200),
                'note'      => 'Focus on hook. Add trending sounds/effects.',
            ],
            'facebook' => [
                'body_text' => $body,
                'note'      => 'Ask a question to drive comments.',
            ],
            'pinterest' => [
                'body_text' => substr($body, 0, 500),
                'note'      => 'Use keyword-rich description.',
            ],
            default => ['body_text' => $body],
        };
        return $adapted;
    }

    // --------------------------------------------------------
    // Viral score prediction
    // --------------------------------------------------------
    private function predictViralScore(array $content, array $params): float
    {
        $score = 50.0; // Base score

        // Hook quality
        $hook = $content['hook'] ?? $content['body_text'] ?? '';
        if (str_contains($hook, '?'))         $score += 8;   // Question hooks
        if (preg_match('/^\d/', $hook))       $score += 6;   // Number hooks
        if (strlen($hook) <= 100)             $score += 5;   // Concise hook
        if (preg_match('/\b(you|your)\b/i', $hook)) $score += 4; // Second person

        // CTA presence
        if (!empty($content['cta']))          $score += 8;

        // Hashtag count (sweet spot: 5-15)
        $hashCount = count($content['hashtags'] ?? []);
        if ($hashCount >= 5 && $hashCount <= 15) $score += 6;
        elseif ($hashCount > 15)              $score -= 5;  // Over-hashtagging penalty

        // Platform bonus
        $platform = $params['platform'] ?? 'instagram';
        $score += match ($platform) {
            'tiktok'    => 10,
            'instagram' => 6,
            'linkedin'  => 4,
            'twitter'   => 5,
            default     => 2,
        };

        // Content type bonus
        $type = $params['content_type'] ?? 'post';
        $score += match ($type) {
            'reel', 'short' => 12,
            'carousel'      => 8,
            'story'         => 6,
            default         => 0,
        };

        // Language bonus (Arabic content often higher engagement in MENA)
        if (($params['language'] ?? '') === 'arabic') $score += 5;

        return min(100.0, max(0.0, round($score, 2)));
    }

    // --------------------------------------------------------
    // Task progress update
    // --------------------------------------------------------
    private function updateProgress(string $taskId, int $progress): void
    {
        $this->db->update('agent_tasks', ['progress' => $progress], 'id = ?', [$taskId]);
    }
}
