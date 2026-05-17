<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/CopywritingAgent.php';

class CopywritingController
{
    private Database $db;
    private Request $request;

    public const CONTENT_TYPES = [
        'caption', 'linkedin_post', 'thread', 'script', 'hook',
        'cta', 'ad_copy', 'carousel', 'story', 'comment_reply', 'dm_reply',
    ];

    public const WRITING_STYLES = [
        'professional', 'casual', 'inspirational', 'educational', 'humorous',
        'storytelling', 'persuasive', 'conversational', 'authoritative', 'empathetic',
    ];

    public const PLATFORMS = [
        'instagram', 'twitter', 'linkedin', 'facebook', 'tiktok',
        'youtube', 'threads', 'snapchat',
    ];

    public const LANGUAGES = ['english', 'arabic', 'mixed'];

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->request = new Request();
    }

    public function index(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $history = $this->loadGenerationHistory($brandId, 20);

        Response::view('copywriting/index', [
            'title'         => 'AI Copywriting – SociAI OS',
            'contentTypes'  => self::CONTENT_TYPES,
            'writingStyles' => self::WRITING_STYLES,
            'platforms'     => self::PLATFORMS,
            'languages'     => self::LANGUAGES,
            'history'       => $history,
            'brandId'       => $brandId,
            'csrf'          => Auth::csrfToken(),
        ]);
    }

    public function generate(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $contentType = $this->request->post('content_type', 'caption');
        $platform    = $this->request->post('platform', 'instagram');
        $topic       = trim($this->request->post('topic', ''));
        $style       = $this->request->post('style', 'professional');
        $language    = $this->request->post('language', 'english');
        $extra       = $this->request->post('extra', []);

        if (!in_array($contentType, self::CONTENT_TYPES, true)) {
            Response::json(['success' => false, 'error' => 'Invalid content type'], 400);
            return;
        }
        if (!in_array($platform, self::PLATFORMS, true)) {
            Response::json(['success' => false, 'error' => 'Invalid platform'], 400);
            return;
        }
        if (!in_array($style, self::WRITING_STYLES, true)) {
            Response::json(['success' => false, 'error' => 'Invalid writing style'], 400);
            return;
        }
        if (!in_array($language, self::LANGUAGES, true)) {
            Response::json(['success' => false, 'error' => 'Invalid language'], 400);
            return;
        }
        if (empty($topic)) {
            Response::json(['success' => false, 'error' => 'Topic is required'], 400);
            return;
        }

        $brandContext = $this->getBrandContext($brandId);
        $agent        = new \CopywritingAgent($brandId);

        try {
            $result = $this->dispatchGeneration(
                $agent, $contentType, $platform, $topic, $style, $language, $brandContext, (array)$extra
            );

            $this->logGeneration($brandId, $user['id'], $contentType, $platform, $topic, $style, $language, $result);

            Response::json([
                'success'      => true,
                'content_type' => $contentType,
                'platform'     => $platform,
                'language'     => $language,
                'style'        => $style,
                'result'       => $result,
            ]);
        } catch (\Throwable $e) {
            error_log('CopywritingController generate error: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => 'Generation failed: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function dispatchGeneration(
        \CopywritingAgent $agent,
        string $contentType,
        string $platform,
        string $topic,
        string $style,
        string $language,
        array $brandContext,
        array $extra
    ): array {
        return match ($contentType) {
            'caption'       => ['caption' => $agent->generateCaption($platform, $topic, $style, $language, $brandContext)],
            'linkedin_post' => ['post' => $agent->generateLinkedInPost($topic, $style, $brandContext)],
            'thread'        => ['tweets' => $agent->generateThread($topic, (int)($extra['num_tweets'] ?? 7), $style)],
            'script'        => ['script' => $agent->generateScript($extra['video_type'] ?? 'short_reel', (int)($extra['duration'] ?? 60), $extra['hook'] ?? '', $brandContext)],
            'hook'          => ['hooks' => $agent->generateHooks($topic, (int)($extra['count'] ?? 5), $style)],
            'cta'           => ['cta' => $agent->generateCTA($extra['goal'] ?? 'engagement', $platform, $style)],
            'ad_copy'       => ['ad_copy' => $agent->generateAdCopy($topic, $extra['audience'] ?? 'general audience', $platform, $style)],
            'carousel'      => ['slides' => $agent->generateCarouselText($topic, (int)($extra['slides'] ?? 5), $style)],
            'story'         => ['story_frames' => $agent->generateCarouselText($topic, (int)($extra['frames'] ?? 3), $style)],
            'comment_reply' => ['reply' => $agent->generateCommentReply($extra['comment'] ?? $topic, $brandContext['brand_voice'] ?? 'professional', $platform)],
            'dm_reply'      => ['reply' => $agent->generateDMReply($extra['message'] ?? $topic, $brandContext)],
            default         => ['text' => $agent->generateCaption($platform, $topic, $style, $language, $brandContext)],
        };
    }

    private function loadGenerationHistory(string $brandId, int $limit): array
    {
        // copywriting_history is an optional table; return empty if missing
        try {
            $stmt = $this->db->prepare(
                'SELECT id, content_type, platform, topic, style, language, created_at
                 FROM copywriting_history WHERE brand_id = ?
                 ORDER BY created_at DESC LIMIT ?'
            );
            $stmt->execute([$brandId, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function logGeneration(
        string $brandId,
        string $userId,
        string $contentType,
        string $platform,
        string $topic,
        string $style,
        string $language,
        array $result
    ): void {
        try {
            $this->db->prepare(
                'INSERT INTO copywriting_history
                 (brand_id, user_id, content_type, platform, topic, style, language, result_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([$brandId, $userId, $contentType, $platform, $topic, $style, $language, json_encode($result)]);
        } catch (\Throwable $e) {
            error_log('copywriting log failed: ' . $e->getMessage());
        }
    }

    private function getBrandContext(string $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT name, description, industry FROM brands WHERE id=? LIMIT 1'
        );
        $stmt->execute([$brandId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $stmt = $this->db->prepare(
            'SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ? ORDER BY tm.created_at ASC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
