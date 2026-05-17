<?php
declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Database, Request, Response};

require_once __DIR__ . '/../agents/CopywritingAgent.php';

class ContentController
{
    private Database $db;
    private Request $request;

    private const PLATFORMS = ['instagram','twitter','linkedin','facebook','tiktok','youtube','threads','snapchat'];
    private const STATUSES  = ['draft','pending','approved','rejected','published'];

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
        $filter  = $this->request->get('status', 'all');
        $platform= $this->request->get('platform', 'all');
        $page    = max(1, (int)$this->request->get('page', 1));
        [$posts, $total] = $this->fetchPosts($brandId, $filter, $platform, $page, 20);
        Response::view('content/index', [
            'title'     => 'Content - SociAI OS',
            'posts'     => $posts,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => 20,
            'filter'    => $filter,
            'platform'  => $platform,
            'platforms' => self::PLATFORMS,
            'statuses'  => self::STATUSES,
            'brandId'   => $brandId,
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function create(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        Response::view('content/create', [
            'title'     => 'Create Content - SociAI OS',
            'platforms' => self::PLATFORMS,
            'brandId'   => $brandId,
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function generate(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $platform    = $this->request->post('platform', 'instagram');
        $topic       = trim($this->request->post('topic', ''));
        $style       = $this->request->post('style', 'professional');
        $language    = $this->request->post('language', 'english');
        $contentType = $this->request->post('content_type', 'caption');
        if (empty($topic)) {
            Response::json(['success' => false, 'error' => 'Topic required'], 400);
            return;
        }
        try {
            $agent = new \CopywritingAgent($brandId);
            $ctx   = $this->getBrandContext($brandId);
            $result = match($contentType) {
                'caption'       => ['text' => $agent->generateCaption($platform, $topic, $style, $language, $ctx)],
                'linkedin_post' => ['text' => $agent->generateLinkedInPost($topic, $style, $ctx)],
                'thread'        => ['thread' => $agent->generateThread($topic, 7, $style)],
                'script'        => ['text' => $agent->generateScript('short_video', 60, '', $ctx)],
                'hooks'         => ['hooks' => $agent->generateHooks($topic, 5, $style)],
                'cta'           => ['text' => $agent->generateCTA('engagement', $platform, $style)],
                'ad_copy'       => ['text' => $agent->generateAdCopy($topic, 'general', $platform, $style)],
                'carousel'      => ['slides' => $agent->generateCarouselText($topic, 5, $style)],
                default         => ['text' => $agent->generateCaption($platform, $topic, $style, $language, $ctx)],
            };
            Response::json(['success' => true, 'generated' => $result]);
        } catch (\Throwable $e) {
            error_log('Content generate: ' . $e->getMessage());
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function store(): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $data    = $this->validatePostData();
        if (isset($data['error'])) {
            Response::json(['success' => false, 'error' => $data['error']], 422);
            return;
        }
        $mediaUrls = $this->handleMediaUpload($brandId);
        $id = $this->generateUuid();
        $stmt = $this->db->prepare(
            'INSERT INTO content_pieces
             (id, brand_id, content_type, topic, writing_style, language, body_text, hashtags, media_urls,
              approval_status, created_by, ai_generated, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->execute([
            $id,
            $brandId,
            $data['content_type'],
            $data['topic'],
            $data['writing_style'],
            $data['language'],
            $data['body_text'],
            json_encode($data['hashtags']),
            json_encode($mediaUrls),
            $data['approval_status'],
            $user['id'],
        ]);
        Response::json(['success' => true, 'post_id' => $id, 'message' => 'Content saved.']);
    }

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
        $post['hashtags']   = json_decode($post['hashtags'] ?? '[]', true);
        Response::view('content/edit', [
            'title'     => 'Edit Content - SociAI OS',
            'post'      => $post,
            'platforms' => self::PLATFORMS,
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
            Response::json(['success' => false, 'error' => 'Cannot edit published'], 422);
            return;
        }
        $data = $this->validatePostData();
        if (isset($data['error'])) {
            Response::json(['success' => false, 'error' => $data['error']], 422);
            return;
        }
        $this->db->prepare(
            'UPDATE content_pieces
             SET content_type=?, topic=?, writing_style=?, language=?, body_text=?, hashtags=?,
                 approval_status=?, updated_at=NOW()
             WHERE id=? AND brand_id=?'
        )->execute([
            $data['content_type'],
            $data['topic'],
            $data['writing_style'],
            $data['language'],
            $data['body_text'],
            json_encode($data['hashtags']),
            $data['approval_status'],
            $id,
            $brandId,
        ]);
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
            Response::json(['success' => false, 'error' => 'Cannot delete published'], 422);
            return;
        }
        $this->db->prepare(
            "UPDATE content_pieces SET approval_status='rejected', updated_at=NOW() WHERE id=? AND brand_id=?"
        )->execute([$id, $brandId]);
        Response::json(['success' => true, 'message' => 'Deleted.']);
    }

    public function approve(string $id): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post = $this->getPost($id, $brandId);
        if (!$post || $post['approval_status'] !== 'pending') {
            Response::json(['success' => false, 'error' => 'Not pending approval'], 404);
            return;
        }
        $this->db->prepare(
            "UPDATE content_pieces SET approval_status='approved', updated_at=NOW() WHERE id=? AND brand_id=?"
        )->execute([$id, $brandId]);
        Response::json(['success' => true, 'message' => 'Approved.']);
    }

    public function reject(string $id): void
    {
        Auth::requireAuth();
        $user    = Auth::getCurrentUser();
        $brandId = $this->getActiveBrandId($user['id']);
        $post   = $this->getPost($id, $brandId);
        if (!$post) {
            Response::json(['success' => false, 'error' => 'Not found'], 404);
            return;
        }
        $this->db->prepare(
            "UPDATE content_pieces SET approval_status='rejected', updated_at=NOW() WHERE id=? AND brand_id=?"
        )->execute([$id, $brandId]);
        Response::json(['success' => true, 'message' => 'Rejected.']);
    }

    private function fetchPosts(string $brandId, string $filter, string $platform, int $page, int $perPage): array
    {
        $where  = ['cp.brand_id=?']; $params = [$brandId];
        if ($filter !== 'all' && in_array($filter, self::STATUSES, true)) {
            $where[] = 'cp.approval_status=?'; $params[] = $filter;
        }
        $wc     = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        $cnt    = $this->db->prepare("SELECT COUNT(*) FROM content_pieces cp WHERE {$wc}");
        $cnt->execute($params);
        $total  = (int)$cnt->fetchColumn();
        $s      = $this->db->prepare(
            "SELECT cp.id, cp.content_type, cp.topic, cp.body_text, cp.approval_status,
                    cp.language, cp.viral_score, cp.created_at, cp.media_urls, cp.hashtags,
                    u.full_name AS created_by_name
             FROM content_pieces cp
             LEFT JOIN users u ON u.id = cp.created_by
             WHERE {$wc}
             ORDER BY cp.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $s->execute($params);
        $posts = $s->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($posts as &$p) {
            $p['media_urls'] = json_decode($p['media_urls'] ?? '[]', true);
            $p['hashtags']   = json_decode($p['hashtags'] ?? '[]', true);
        }
        unset($p);
        return [$posts, $total];
    }

    private function validatePostData(): array
    {
        $body_text    = trim($this->request->post('body_text', ''));
        $topic        = trim($this->request->post('topic', ''));
        $type         = $this->request->post('content_type', 'caption');
        $status       = $this->request->post('approval_status', 'draft');
        $language     = $this->request->post('language', 'english');
        $writing_style= $this->request->post('writing_style', 'professional');
        $htags        = $this->request->post('hashtags', '');

        if (empty($topic) && empty($body_text)) {
            return ['error' => 'Topic or body text required'];
        }
        if (strlen($body_text) > 5000) {
            return ['error' => 'Text too long (max 5000)'];
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
            $hashtags = array_map(fn($h) => ltrim($h, '#'), $m[0]);
        }
        return [
            'content_type'    => $type,
            'topic'           => $topic,
            'body_text'       => $body_text,
            'writing_style'   => $writing_style,
            'language'        => $language,
            'approval_status' => $status,
            'hashtags'        => $hashtags,
        ];
    }

    private function handleMediaUpload(string $brandId): array
    {
        $urls = [];
        if (empty($_FILES['media'])) return $urls;
        $dir     = __DIR__ . '/../storage/uploads/media/' . $brandId . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $files   = $_FILES['media'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'];
        $list    = is_array($files['name']) ? $files['name'] : [$files['name']];
        foreach (array_keys($list) as $i) {
            $name = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
            $tmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $size = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];
            $err  = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
            if ($err !== UPLOAD_ERR_OK || $size > 50 * 1024 * 1024) continue;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) continue;
            $fname = uniqid('media_', true) . '.' . $ext;
            if (move_uploaded_file($tmp, $dir . $fname)) {
                $urls[] = '/storage/uploads/media/' . $brandId . '/' . $fname;
            }
        }
        return $urls;
    }

    private function getPost(string $id, string $brandId): array|false
    {
        $s = $this->db->prepare(
            "SELECT * FROM content_pieces WHERE id=? AND brand_id=? LIMIT 1"
        );
        $s->execute([$id, $brandId]);
        return $s->fetch(\PDO::FETCH_ASSOC);
    }

    private function getBrandContext(string $brandId): array
    {
        $stmt = $this->db->prepare(
            'SELECT name, description, industry FROM brands WHERE id=? LIMIT 1'
        );
        $stmt->execute([$brandId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function generateUuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function getActiveBrandId(string $userId): string
    {
        if (!empty($_SESSION['active_brand_id'])) {
            return (string)$_SESSION['active_brand_id'];
        }
        $s = $this->db->prepare(
            'SELECT b.id FROM brands b
             INNER JOIN team_members tm ON tm.brand_id = b.id
             WHERE tm.user_id = ?
             ORDER BY tm.created_at ASC LIMIT 1'
        );
        $s->execute([$userId]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $_SESSION['active_brand_id'] = $row['id'];
            return (string)$row['id'];
        }
        return '';
    }
}
