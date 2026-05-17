<?php
/**
 * SociAI OS - Platform Manager
 * Factory, sync orchestration, AI reply generation, and publishing.
 */

declare(strict_types=1);

namespace SociAI\Core;

use SociAI\Platforms\BasePlatform;
use SociAI\Platforms\MetaPlatform;
use SociAI\Platforms\TwitterPlatform;
use SociAI\Platforms\LinkedInPlatform;
use SociAI\Platforms\TikTokPlatform;
use SociAI\Platforms\YouTubePlatform;

// Autoload platform files
$platformDir = __DIR__ . '/platforms/';
foreach (['BasePlatform', 'MetaPlatform', 'TwitterPlatform', 'LinkedInPlatform', 'TikTokPlatform', 'YouTubePlatform'] as $cls) {
    $file = $platformDir . $cls . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}

class PlatformManager
{
    // --------------------------------------------------------
    // Factory — returns correct platform instance
    // --------------------------------------------------------

    /**
     * @param array<string, mixed> $extra  Platform-specific extra data
     *                                     e.g. ['ig_user_id' => '...'] for Meta/Instagram
     */
    public static function get(
        string $platform,
        string $accessToken,
        string $accountId,
        array  $extra = []
    ): BasePlatform {
        return match (strtolower($platform)) {
            'facebook'  => new MetaPlatform($accessToken, $accountId, 'facebook', $extra['ig_user_id'] ?? ''),
            'instagram' => new MetaPlatform($accessToken, $accountId, 'instagram', $extra['ig_user_id'] ?? $accountId),
            'twitter',
            'x'         => new TwitterPlatform($accessToken, $accountId),
            'linkedin'  => new LinkedInPlatform($accessToken, $accountId),
            'tiktok'    => new TikTokPlatform($accessToken, $accountId),
            'youtube'   => new YouTubePlatform($accessToken, $accountId),
            default     => throw new \InvalidArgumentException("Unsupported platform: {$platform}"),
        };
    }

    // --------------------------------------------------------
    // Sync all interactions for a brand
    // --------------------------------------------------------

    /**
     * Pull comments + DMs from all connected platform accounts for a brand.
     * Stores new items in community_interactions table.
     * Returns count of new interactions added.
     */
    public static function syncAllInteractions(string $brandId): int
    {
        $db  = Database::getInstance();
        $new = 0;

        // Load all active platform accounts for this brand
        $accounts = $db->fetchAll(
            "SELECT id, platform, account_id, access_token_encrypted, extra_data, last_synced_at
             FROM platform_accounts
             WHERE brand_id = ? AND is_active = 1",
            [$brandId]
        );

        foreach ($accounts as $account) {
            try {
                $accessToken = self::decryptToken($account['access_token_encrypted'] ?? '');
                if (empty($accessToken)) {
                    error_log("[PlatformManager] Skipping {$account['platform']} — no token.");
                    continue;
                }

                $extra = json_decode($account['extra_data'] ?? '{}', true) ?? [];
                $since = $account['last_synced_at'];

                $client = self::get(
                    $account['platform'],
                    $accessToken,
                    $account['account_id'],
                    $extra
                );

                // Pull comments / mentions
                $comments = [];
                try {
                    $comments = $client->getComments($since);
                } catch (\Throwable $e) {
                    error_log("[PlatformManager] getComments failed ({$account['platform']}): " . $e->getMessage());
                }

                // Pull DMs
                $dms = [];
                try {
                    $dms = $client->getDMs($since);
                } catch (\Throwable $e) {
                    error_log("[PlatformManager] getDMs failed ({$account['platform']}): " . $e->getMessage());
                }

                $interactions = array_merge($comments, $dms);

                foreach ($interactions as $interaction) {
                    try {
                        $inserted = self::storeInteraction($db, $brandId, $account, $interaction);
                        if ($inserted) {
                            $new++;
                        }
                    } catch (\Throwable $e) {
                        error_log("[PlatformManager] storeInteraction error: " . $e->getMessage());
                    }
                }

                // Update last_synced_at
                $db->update(
                    'platform_accounts',
                    ['last_synced_at' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$account['id']]
                );
            } catch (\Throwable $e) {
                error_log("[PlatformManager] syncAllInteractions error for account {$account['id']}: " . $e->getMessage());
            }
        }

        return $new;
    }

    // --------------------------------------------------------
    // Store a single interaction in community_interactions
    // --------------------------------------------------------

    /**
     * @param array<string, mixed> $account     Platform account row
     * @param array<string, mixed> $interaction Normalised interaction from platform client
     */
    private static function storeInteraction(
        Database $db,
        string   $brandId,
        array    $account,
        array    $interaction
    ): bool {
        $externalId = $interaction['external_id'] ?? '';
        $platform   = $account['platform'];

        if (empty($externalId)) {
            return false;
        }

        // Check for duplicate
        $existing = $db->fetchOne(
            "SELECT id FROM community_interactions WHERE platform = ? AND platform_item_id = ? AND brand_id = ? LIMIT 1",
            [$platform, $externalId, $brandId]
        );
        if ($existing) {
            return false;
        }

        // Detect sentiment (basic heuristic — AI will refine later)
        $sentiment = self::detectSentiment($interaction['content'] ?? '');

        $db->insert('community_interactions', [
            'brand_id'           => $brandId,
            'platform'           => $platform,
            'platform_account_id'=> $account['id'],
            'interaction_type'   => $interaction['type']          ?? 'comment',
            'platform_item_id'   => $externalId,
            'author_name'        => substr($interaction['author_name']   ?? 'Unknown', 0, 255),
            'author_handle'      => substr($interaction['author_id']     ?? '', 0, 255),
            'author_avatar'      => substr($interaction['author_avatar'] ?? '', 0, 512),
            'message_text'       => $interaction['content']       ?? '',
            'sentiment'          => $sentiment,
            'status'             => 'new',
            'created_at'         => $interaction['created_at']    ?? date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    // --------------------------------------------------------
    // AI Reply Generation
    // --------------------------------------------------------

    /**
     * Generate an AI reply for an interaction using brand voice.
     * @param array<string, mixed> $interaction community_interactions row
     * @param array<string, mixed> $brand       brands table row (must include settings JSON)
     */
    public static function generateAIReply(array $interaction, array $brand): string
    {
        $settings   = json_decode($brand['settings'] ?? '{}', true) ?? [];
        $brandVoice = $settings['brand_voice']   ?? ($brand['description'] ?? 'Professional and helpful');
        $brandName  = $brand['name']              ?? 'Our brand';
        $platform   = $interaction['platform']    ?? 'social media';
        $type       = $interaction['interaction_type'] ?? $interaction['type'] ?? 'comment';
        $content    = $interaction['message_text'] ?? $interaction['content'] ?? '';

        $systemPrompt = <<<PROMPT
You are a social media community manager for {$brandName}.
Your brand voice: {$brandVoice}
You are replying to a {$type} on {$platform}.

Rules:
- Keep replies concise (under 280 chars for Twitter, under 1000 for others).
- Match the platform's tone: casual for TikTok/Instagram, professional for LinkedIn.
- Never be defensive, always helpful and empathetic.
- Do not include hashtags unless it's Instagram or TikTok.
- Sign off naturally — no "Best regards" or email-style closings.
- Return ONLY the reply text, no preamble, no quotes.
PROMPT;

        $prompt = "Comment/message to reply to:\n{$content}\n\nWrite a reply:";

        try {
            $result = AI::generate($prompt, $systemPrompt, 512, 0.75);
            return trim($result['text'] ?? '');
        } catch (\Throwable $e) {
            error_log("[PlatformManager] generateAIReply error: " . $e->getMessage());
            return '';
        }
    }

    // --------------------------------------------------------
    // Publish a reply via the appropriate platform
    // --------------------------------------------------------

    /**
     * Publishes an approved reply and marks the interaction as replied.
     * @param array<string, mixed> $interaction community_interactions row
     * @param string               $replyText
     * @param string               $brandId
     */
    public static function publishReply(array $interaction, string $replyText, string $brandId): bool
    {
        $db          = Database::getInstance();
        $platform    = $interaction['platform']    ?? '';
        $accountId   = $interaction['platform_account_id'] ?? '';
        $externalId  = $interaction['platform_item_id']    ?? '';
        $interactionType = $interaction['interaction_type'] ?? 'comment';

        if (empty($platform) || empty($externalId)) {
            error_log("[PlatformManager] publishReply: missing platform or external_id.");
            return false;
        }

        // Load platform account
        $account = $db->fetchOne(
            "SELECT * FROM platform_accounts WHERE id = ? AND brand_id = ? AND is_active = 1 LIMIT 1",
            [$accountId, $brandId]
        );

        if (!$account) {
            // Try to find any active account for this platform+brand
            $account = $db->fetchOne(
                "SELECT * FROM platform_accounts WHERE brand_id = ? AND platform = ? AND is_active = 1 LIMIT 1",
                [$brandId, $platform]
            );
        }

        if (!$account) {
            error_log("[PlatformManager] publishReply: no active account found for platform {$platform}.");
            return false;
        }

        try {
            $accessToken = self::decryptToken($account['access_token_encrypted'] ?? '');
            if (empty($accessToken)) {
                error_log("[PlatformManager] publishReply: empty access token.");
                return false;
            }

            $extra  = json_decode($account['extra_data'] ?? '{}', true) ?? [];
            $client = self::get($platform, $accessToken, $account['account_id'], $extra);

            if ($interactionType === 'dm') {
                $success = $client->replyToDM($externalId, $replyText);
            } else {
                $success = $client->replyToComment($externalId, $replyText);
            }

            if ($success) {
                // Update the interaction record
                $db->update(
                    'community_interactions',
                    [
                        'actual_reply' => $replyText,
                        'status'       => 'replied',
                        'replied_at'   => date('Y-m-d H:i:s'),
                    ],
                    'id = ?',
                    [$interaction['id']]
                );
            }

            return $success;
        } catch (\Throwable $e) {
            error_log("[PlatformManager] publishReply error: " . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------
    // Publish content to a platform
    // --------------------------------------------------------

    /**
     * Publish a content piece to a specific platform account.
     * @param array<string, mixed> $contentPiece  content_pieces row
     * @param string               $platformAccountId
     * @param string               $brandId
     * @return array<string, mixed>
     */
    public static function publishContent(array $contentPiece, string $platformAccountId, string $brandId): array
    {
        $db = Database::getInstance();

        $account = $db->fetchOne(
            "SELECT * FROM platform_accounts WHERE id = ? AND brand_id = ? AND is_active = 1 LIMIT 1",
            [$platformAccountId, $brandId]
        );

        if (!$account) {
            return ['success' => false, 'error' => 'Platform account not found or inactive.'];
        }

        try {
            $accessToken = self::decryptToken($account['access_token_encrypted'] ?? '');
            if (empty($accessToken)) {
                return ['success' => false, 'error' => 'No valid access token.'];
            }

            $extra  = json_decode($account['extra_data'] ?? '{}', true) ?? [];
            $client = self::get($account['platform'], $accessToken, $account['account_id'], $extra);

            $mediaUrls = json_decode($contentPiece['media_urls'] ?? '[]', true) ?? [];
            $hashtags  = json_decode($contentPiece['hashtags']   ?? '[]', true) ?? [];
            $hashStr   = !empty($hashtags) ? ' ' . implode(' ', array_map(fn($h) => '#' . ltrim($h, '#'), $hashtags)) : '';

            $publishData = [
                'caption'   => ($contentPiece['body_text'] ?? '') . $hashStr,
                'text'      => $contentPiece['body_text']   ?? '',
                'title'     => $contentPiece['title']       ?? ($contentPiece['topic'] ?? ''),
                'image_url' => $mediaUrls[0] ?? '',
                'media_url' => $mediaUrls[0] ?? '',
            ];

            $result = $client->publishPost($publishData);

            if ($result['success'] ?? false) {
                // Update content_pieces with published info
                $db->update('content_pieces', [
                    'approval_status' => 'published',
                    'updated_at'      => date('Y-m-d H:i:s'),
                ], 'id = ?', [$contentPiece['id']]);

                // Store in scheduled_posts
                $db->insert('scheduled_posts', [
                    'id'                  => Security::generateUUID(),
                    'content_id'          => $contentPiece['id'],
                    'platform_account_id' => $platformAccountId,
                    'platform'            => $account['platform'],
                    'scheduled_at'        => date('Y-m-d H:i:s'),
                    'published_at'        => date('Y-m-d H:i:s'),
                    'status'              => 'published',
                    'platform_post_id'    => $result['platform_post_id'] ?? null,
                    'platform_post_url'   => $result['url'] ?? null,
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            error_log("[PlatformManager] publishContent error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // --------------------------------------------------------
    // Helpers
    // --------------------------------------------------------

    /**
     * Decrypt an access token stored with Security::encrypt().
     */
    private static function decryptToken(string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }
        try {
            return Security::decrypt($encrypted);
        } catch (\Throwable $e) {
            // Try legacy CBC decryption (older stored tokens)
            try {
                $key  = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : '';
                $data = base64_decode($encrypted, true);
                if ($data === false) {
                    return '';
                }
                $iv   = substr($data, 0, 16);
                $ct   = substr($data, 16);
                $dec  = openssl_decrypt($ct, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
                return (string)($dec ?: '');
            } catch (\Throwable) {
                return '';
            }
        }
    }

    /**
     * Basic heuristic sentiment detection.
     */
    private static function detectSentiment(string $text): string
    {
        $lower = strtolower($text);

        $positive = ['great', 'awesome', 'love', 'excellent', 'amazing', 'good', 'best', 'thank', 'fantastic', 'wonderful', 'helpful', 'brilliant'];
        $negative = ['bad', 'terrible', 'worst', 'hate', 'awful', 'horrible', 'disappointed', 'useless', 'broken', 'fail', 'scam', 'waste', 'problem', 'issue', 'bug'];

        $posScore = 0;
        $negScore = 0;
        foreach ($positive as $w) {
            if (str_contains($lower, $w)) {
                $posScore++;
            }
        }
        foreach ($negative as $w) {
            if (str_contains($lower, $w)) {
                $negScore++;
            }
        }

        if ($posScore > $negScore) {
            return 'positive';
        }
        if ($negScore > $posScore) {
            return 'negative';
        }
        return 'neutral';
    }
}
