<?php
/**
 * SociAI OS - Content Controller
 * Real content generation, scheduling, and platform publishing.
 */

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response, Security, AI, PlatformManager};

require_once __DIR__ . '/../core/PlatformManager.php';
require_once __DIR__ . '/../agents/CopywritingAgent.php';

class ContentController
{
    private Database $db;
    private Request  $request;

    private const PLATFORMS = [
        'instagram', 'twitter', 'linkedin', 'facebook', 'tiktok', 'youtube', 'threads', 'snapchat',
    ];

    private const CONTENT_TYPES = [
        'post', 'story', 'reel', 'thread', 'carousel', 'article',
    ];

    private const STATUSES = [
        'draft', 'pending', 'approved', 'rejected', 'published',
    ];

    public function __construct()
    {
        $this->db      = Database::getInstance();
        $this->request = new Request();
    }

    // --------------------------------------------------------
    // Content list
    // --------------------------------------------------------

    public function index(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $filter  = $this->request->get('status',   'all');
        $platform= $this->request->get('platform',  'all');
        $page    = max(1, (int)$this->request->get('page', 1));

        [$posts, $total, $pages] = $this->fetchPosts($brandId, $filter, $platform, $page, 20);

        // Get connected platforms for the "Publish" modal
        $connectedPlatforms = $this->getConnectedPlatforms($brandId);

        Response::view('content/index', [
            'title'              => 'Content Hub – SociAI OS',
            'posts'              => $posts,
            'total'              => $total,
            'page'               => $page,
            'totalPages'         => $pages,
            'perPage'            => 20,
            'filterStatus'       => $filter,
            'filterPlatform'     => $platform,
            'platforms'          => self::PLATFORMS,
            'statuses'           => self::STATUSES,
            'connectedPlatforms' => $connectedPlatforms,
            'brandId'            => $brandId,
            'csrf'               => Auth::csrfToken(),
        ]);
    }

    // --------------------------------------------------------
    // Create content form
    // --------------------------------------------------------

    public function create(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        Response::view('content/create', [
            'title'         => 'Create Content – SociAI OS',
            'platforms'     => self::PLATFORMS,
            'contentTypes'  => self::CONTENT_TYPES,
            'brandId'       => $brandId,
            'csrf'          => Auth::csrfToken(),
        ]);
    }

    // --------------------------------------------------------
    // Store content piece
    // --------------------------------------------------------

    public function store(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $data = $this->validatePostData();
        if (isset($data['error'])) {
            Response::json(['success' => false, 'error' => $data['error']], 422);
            return;
        }

        $mediaUrls = $this->handleMediaUpload($brandId);
        $id        = Security::generateUUID();

        $this->db->insert('content_pieces', [
            'id'              => $id,
            'brand_id'        => $brandId,
            'content_type'    => $data['content_type'],
            'topic'           => $data['topic'],
            'writing_style'   => $data['writing_style'],
            'language'        => $data['language'],
            'body_text'       => $data['body_text'],
            'hashtags'        => json_encode($data['hashtags']),
            'media_urls'      => json_encode($mediaUrls),
            'approval_status' => $data['approval_status'],
            'created_by'      => $user['id'],
            'ai_generated'    => $data['ai_generated'] ?? 0,
            'ai_prompt_used'  => $data['prompt_used']  ?? null,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        Response::json(['success' => true, 'post_id' => $id, 'message' => 'Content saved.']);
    }

    // --------------------------------------------------------
    // AI text generation endpoint
    // --------------------------------------------------------

    public function generateAI(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platform    = $this->request->post('platform',     'instagram');
        $topic       = trim((string)$this->request->post('topic',       ''));
        $tone        = $this->request->post('tone',        'professional');
        $language    = $this->request->post('language',    'english');
        $contentType = $this->request->post('content_type','post');

        if (empty($topic)) {
            Response::json(['success' => false, 'error' => 'Topic is required'], 400);
            return;
        }

        try {
            $brand    = $this->getBrand($brandId);
            $settings = json_decode($brand['settings'] ?? '{}', true) ?? [];
            $voice    = $settings['brand_voice'] ?? ($brand['description'] ?? '');
            $name     = $brand['name']            ?? 'our brand';

            $platformLimits = [
                'twitter'   => 280,
                'instagram' => 2200,
                'linkedin'  => 3000,
                'tiktok'    => 2200,
                'facebook'  => 63206,
                'youtube'   => 5000,
                'threads'   => 500,
                'snapchat'  => 250,
            ];
            $maxChars = $platformLimits[$platform] ?? 2000;

            $systemPrompt = <<<PROMPT
You are a social media copywriter for {$name}.
Brand voice: {$voice}
Platform: {$platform}
Tone: {$tone}
Language: {$language}

Rules:
- Keep captions under {$maxChars} characters
- For Instagram/TikTok: emojis encouraged, conversational
- For LinkedIn: professional, insightful, no fluff
- For Twitter: punchy, under 280 chars
- Include a clear call-to-action
- Return ONLY the caption/post text, no explanations
PROMPT;

            $prompt = "Write a {$contentType} for {$platform} about: {$topic}";

            $result   = AI::generate($prompt, $systemPrompt, 1024, 0.8);
            $caption  = trim($result['text'] ?? '');

            // Generate hashtags
            $hashtagResult = AI::generate(
                "Generate 5-10 relevant hashtags for a {$platform} post about: {$topic}. Return ONLY hashtags, one per line, no # symbol.",
                "You are a social media hashtag expert. Return only hashtags, no explanations.",
                256,
                0.7
            );
            $hashLines = array_filter(array_map('trim', explode("\n", $hashtagResult['text'] ?? '')));
            $hashtags  = array_values(array_map(fn($h) => ltrim($h, '#'), $hashLines));

            Response::json([
                'success'  => true,
                'caption'  => $caption,
                'hashtags' => $hashtags,
                'model'    => $result['model'] ?? 'unknown',
                'tokens'   => ($result['input_tokens'] ?? 0) + ($result['output_tokens'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            error_log('[ContentController] generateAI error: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // --------------------------------------------------------
    // AI image generation endpoint
    // --------------------------------------------------------

    public function generateImage(): void
    {
        Auth::requireAuth();

        $prompt = trim((string)$this->request->post('prompt', ''));
        $size   = $this->request->post('size', '1024x1024');

        if (empty($prompt)) {
            Response::json(['success' => false, 'error' => 'Image prompt is required'], 400);
            return;
        }

        try {
            [$w, $h] = array_pad(array_map('intval', explode('x', $size, 2)), 2, 1024);
            $result   = AI::generateImage($prompt, $w, $h);

            Response::json([
                'success'        => true,
                'url'            => $result['url'] ?? '',
                'revised_prompt' => $result['revised_prompt'] ?? $prompt,
                'provider'       => $result['provider'] ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            error_log('[ContentController] generateImage error: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // --------------------------------------------------------
    // Schedule a content piece
    // --------------------------------------------------------

    public function schedule(string $id = ''): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        if (empty($id)) {
            $id = $this->request->post('id', '');
        }

        $post = $this->getPost($id, $brandId);
        if (!$post) {
            Response::json(['success' => false, 'error' => 'Content piece not found'], 404);
            return;
        }

        $scheduledAt        = $this->request->post('scheduled_at',         '');
        $platformAccountId  = $this->request->post('platform_account_id',  '');
        $platform           = $this->request->post('platform',              '');

        if (empty($scheduledAt)) {
            Response::json(['success' => false, 'error' => 'scheduled_at datetime required'], 400);
            return;
        }

        if (strtotime($scheduledAt) === false || strtotime($scheduledAt) < time()) {
            Response::json(['success' => false, 'error' => 'scheduled_at must be a future datetime'], 400);
            return;
        }

        if (empty($platformAccountId)) {
            // Try to find a platform account for this brand
            $account = $this->db->fetchOne(
                "SELECT id FROM platform_accounts WHERE brand_id = ? AND platform = ? AND is_active = 1 LIMIT 1",
                [$brandId, $platform]
            );
            $platformAccountId = $account['id'] ?? '';
        }

        if (empty($platformAccountId)) {
            Response::json(['success' => false, 'error' => 'No connected platform account found'], 400);
            return;
        }

        // Update content status to approved/pending
        $this->db->update('content_pieces', [
            'approval_status' => 'approved',
            'updated_at'      => date('Y-m-d H:i:s'),
        ], 'id = ? AND brand_id = ?', [$id, $brandId]);

        // Create scheduled_posts entry
        $schedId = Security::generateUUID();
        $this->db->insert('scheduled_posts', [
            'id'                  => $schedId,
            'content_id'          => $id,
            'platform_account_id' => $platformAccountId,
            'platform'            => $platform,
            'scheduled_at'        => date('Y-m-d H:i:s', strtotime($scheduledAt)),
            'status'              => 'scheduled',
        ]);

        Response::json([
            'success'       => true,
            'scheduled_id'  => $schedId,
            'scheduled_at'  => $scheduledAt,
            'message'       => 'Content scheduled successfully.',
        ]);
    }

    // --------------------------------------------------------
    // Publish now — immediately publish to platform
    // --------------------------------------------------------

    public function publishNow(string $id = ''): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        if (empty($id)) {
            $id = $this->request->post('id', '');
        }

        $post = $this->getPost($id, $brandId);
        if (!$post) {
            Response::json(['success' => false, 'error' => 'Content piece not found'], 404);
            return;
        }

        $platformAccountId = $this->request->post('platform_account_id', '');

        if (empty($platformAccountId)) {
            Response::json(['success' => false, 'error' => 'platform_account_id required'], 400);
            return;
        }

        try {
            $result = PlatformManager::publishContent($post, $platformAccountId, $brandId);

            if ($result['success'] ?? false) {
                Response::json([
                    'success'          => true,
                    'platform_post_id' => $result['platform_post_id'] ?? null,
                    'url'              => $result['url'] ?? null,
                    'message'          => 'Content published successfully!',
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'error'   => $result['error'] ?? 'Publish failed.',
                ], 500);
            }
        } catch (\Throwable $e) {
            error_log('[ContentController] publishNow error: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // --------------------------------------------------------
    // Show single content piece
    // --------------------------------------------------------

    public function show(string $id): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post    = $this->getPost($id, $brandId);

        if (!$post) {
            Response::view('errors/404', ['title' => '404']);
            return;
        }

        $post['media_urls'] = json_decode($post['media_urls'] ?? '[]', true);
        $post['hashtags']   = json_decode($post['hashtags']   ?? '[]', true);

        Response::view('content/show', [
            'title' => ($post['topic'] ?? 'Content') . ' – SociAI OS',
            'post'  => $post,
            'brandId' => $brandId,
            'csrf'  => Auth::csrfToken(),
            'connectedPlatforms' => $this->getConnectedPlatforms($brandId),
        ]);
    }

    // --------------------------------------------------------
    // Edit / Update / Delete / Approve / Reject
    // --------------------------------------------------------

    public function edit(string $id): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post    = $this->getPost($id, $brandId);

        if (!$post) {
            Response::view('errors/404', ['title' => '404']);
            return;
        }

        $post['media_urls'] = json_decode($post['media_urls'] ?? '[]', true);
        $post['hashtags']   = json_decode($post['hashtags']   ?? '[]', true);

        Response::view('content/edit', [
            'title'     => 'Edit Content – SociAI OS',
            'post'      => $post,
            'platforms' => self::PLATFORMS,
            'brandId'   => $brandId,
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post    = $this->getPost($id, $brandId);

        if (!$post) {
            Response::json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }
        if ($post['approval_status'] === 'published') {
            Response::json(['success' => false, 'error' => 'Cannot edit published content'], 422);
            return;
        }

        $data = $this->validatePostData();
        if (isset($data['error'])) {
            Response::json(['success' => false, 'error' => $data['error']], 422);
            return;
        }

        $this->db->update('content_pieces', [
            'content_type'    => $data['content_type'],
            'topic'           => $data['topic'],
            'writing_style'   => $data['writing_style'],
            'language'        => $data['language'],
            'body_text'       => $data['body_text'],
            'hashtags'        => json_encode($data['hashtags']),
            'approval_status' => $data['approval_status'],
            'updated_at'      => date('Y-m-d H:i:s'),
        ], 'id = ? AND brand_id = ?', [$id, $brandId]);

        Response::json(['success' => true, 'message' => 'Updated.']);
    }

    public function delete(string $id): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post    = $this->getPost($id, $brandId);

        if (!$post) {
            Response::json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }
        if ($post['approval_status'] === 'published') {
            Response::json(['success' => false, 'error' => 'Cannot delete published content'], 422);
            return;
        }

        $this->db->update('content_pieces', [
            'approval_status' => 'rejected',
            'updated_at'      => date('Y-m-d H:i:s'),
        ], 'id = ? AND brand_id = ?', [$id, $brandId]);

        Response::json(['success' => true, 'message' => 'Deleted.']);
    }

    public function approve(string $id): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post    = $this->getPost($id, $brandId);

        if (!$post) {
            Response::json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }

        $this->db->update('content_pieces', [
            'approval_status' => 'approved',
            'approved_by'     => $user['id'],
            'approved_at'     => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ], 'id = ? AND brand_id = ?', [$id, $brandId]);

        Response::json(['success' => true, 'message' => 'Approved.']);
    }

    public function reject(string $id): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post    = $this->getPost($id, $brandId);

        if (!$post) {
            Response::json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }

        $reason = trim((string)$this->request->post('reason', ''));
        $this->db->update('content_pieces', [
            'approval_status' => 'rejected',
            'rejection_reason'=> $reason,
            'updated_at'      => date('Y-m-d H:i:s'),
        ], 'id = ? AND brand_id = ?', [$id, $brandId]);

        Response::json(['success' => true, 'message' => 'Rejected.']);
    }

    // --------------------------------------------------------
    // Legacy generate (from CopywritingAgent)
    // --------------------------------------------------------

    public function generate(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);

        $platform    = $this->request->post('platform', 'instagram');
        $topic       = trim((string)$this->request->post('topic', ''));
        $style       = $this->request->post('style', 'professional');
        $language    = $this->request->post('language', 'english');
        $contentType = $this->request->post('content_type', 'caption');

        if (empty($topic)) {
            Response::json(['success' => false, 'error' => 'Topic required'], 400);
            return;
        }

        try {
            $agent  = new \CopywritingAgent($brandId);
            $ctx    = $this->getBrand($brandId);
            $result = match ($contentType) {
                'caption'       => ['text'   => $agent->generateCaption($platform, $topic, $style, $language, $ctx)],
                'linkedin_post' => ['text'   => $agent->generateLinkedInPost($topic, $style, $ctx)],
                'thread'        => ['thread' => $agent->generateThread($topic, 7, $style)],
                'hooks'         => ['hooks'  => $agent->generateHooks($topic, 5, $style)],
                'cta'           => ['text'   => $agent->generateCTA('engagement', $platform, $style)],
                default         => ['text'   => $agent->generateCaption($platform, $topic, $style, $language, $ctx)],
            };
            Response::json(['success' => true, 'generated' => $result]);
        } catch (\Throwable $e) {
            error_log('[ContentController] generate error: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // --------------------------------------------------------
    // Private helpers
    // --------------------------------------------------------

    /**
     * @return array{0: array, 1: int, 2: int}
     */
    private function fetchPosts(string $brandId, string $filter, string $platform, int $page, int $perPage): array
    {
        $where  = ['cp.brand_id = ?'];
        $params = [$brandId];

        if ($filter !== 'all' && in_array($filter, self::STATUSES, true)) {
            $where[]  = 'cp.approval_status = ?';
            $params[] = $filter;
        }

        if ($platform !== 'all' && in_array($platform, self::PLATFORMS, true)) {
            $where[]  = 'cp.content_type LIKE ?';
            $params[] = '%' . $platform . '%';
        }

        $wc     = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM content_pieces cp WHERE {$wc}",
            $params
        );

        $posts = $this->db->fetchAll(
            "SELECT cp.id, cp.content_type, cp.topic, cp.body_text, cp.approval_status,
                    cp.language, cp.viral_score, cp.created_at, cp.media_urls, cp.hashtags,
                    cp.ai_generated, cp.approved_at,
                    u.full_name AS created_by_name
             FROM content_pieces cp
             LEFT JOIN users u ON u.id = cp.created_by
             WHERE {$wc}
             ORDER BY cp.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        foreach ($posts as &$p) {
            $p['media_urls'] = json_decode($p['media_urls'] ?? '[]', true);
            $p['hashtags']   = json_decode($p['hashtags']   ?? '[]', true);
        }
        unset($p);

        return [$posts, $total, max(1, (int)ceil($total / $perPage))];
    }

    private function validatePostData(): array
    {
        $bodyText     = trim((string)$this->request->post('body_text',     ''));
        $topic        = trim((string)$this->request->post('topic',          ''));
        $type         = $this->request->post('content_type',   'post');
        $status       = $this->request->post('approval_status','draft');
        $language     = $this->request->post('language',       'english');
        $writingStyle = $this->request->post('writing_style',  'professional');
        $htags        = $this->request->post('hashtags',       '');
        $aiGenerated  = (int)(bool)$this->request->post('ai_generated', 0);
        $promptUsed   = $this->request->post('prompt_used', null);

        if (empty($topic) && empty($bodyText)) {
            return ['error' => 'Topic or body text is required'];
        }
        if (strlen($bodyText) > 10000) {
            return ['error' => 'Body text too long (max 10,000 characters)'];
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'draft';
        }
        if (!in_array($language, ['arabic', 'english', 'mixed'], true)) {
            $language = 'english';
        }

        $hashtags = [];
        if (!empty($htags)) {
            preg_match_all('/#?\w+/', $htags, $m);
            $hashtags = array_values(array_map(fn($h) => ltrim($h, '#'), $m[0]));
        }

        return [
            'content_type'    => $type,
            'topic'           => $topic,
            'body_text'       => $bodyText,
            'writing_style'   => $writingStyle,
            'language'        => $language,
            'approval_status' => $status,
            'hashtags'        => $hashtags,
            'ai_generated'    => $aiGenerated,
            'prompt_used'     => $promptUsed,
        ];
    }

    private function handleMediaUpload(string $brandId): array
    {
        $urls = [];
        if (empty($_FILES['media'])) {
            return $urls;
        }

        $dir = UPLOAD_DIR . '/media/' . $brandId . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $files   = $_FILES['media'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi'];
        $list    = is_array($files['name']) ? $files['name'] : [$files['name']];

        foreach (array_keys($list) as $i) {
            $name = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
            $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $size = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];
            $err  = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];

            if ($err !== UPLOAD_ERR_OK || $size > MAX_UPLOAD_SIZE) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                continue;
            }

            $fname = Security::generateToken(12) . '.' . $ext;
            if (move_uploaded_file($tmp, $dir . $fname)) {
                $urls[] = UPLOAD_URL . '/media/' . $brandId . '/' . $fname;
            }
        }
        return $urls;
    }

    private function getPost(string $id, string $brandId): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM content_pieces WHERE id = ? AND brand_id = ? LIMIT 1",
            [$id, $brandId]
        );
    }

    private function getBrand(string $brandId): array
    {
        return $this->db->fetchOne(
            "SELECT id, name, description, industry, settings FROM brands WHERE id = ? LIMIT 1",
            [$brandId]
        ) ?: ['id' => $brandId, 'name' => '', 'description' => '', 'settings' => '{}'];
    }

    private function getConnectedPlatforms(string $brandId): array
    {
        return $this->db->fetchAll(
            "SELECT id, platform, account_name, account_id, follower_count, avatar_url, token_expires_at, last_synced_at
             FROM platform_accounts
             WHERE brand_id = ? AND is_active = 1
             ORDER BY platform ASC",
            [$brandId]
        );
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $row = $this->db->fetchOne(
            "SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ?
             ORDER BY tm.created_at ASC LIMIT 1",
            [$userId]
        );
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
