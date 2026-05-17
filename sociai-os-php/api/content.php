<?php

declare(strict_types=1);

/**
 * REST API: /api/content
 *
 * Routes:
 *   GET    /api/content                    → list content
 *   POST   /api/content/generate           → AI generate content
 *   POST   /api/content                    → create content
 *   PUT    /api/content/{id}               → update content
 *   DELETE /api/content/{id}               → delete content
 *   POST   /api/content/{id}/approve       → approve content
 *   POST   /api/content/{id}/schedule      → schedule content
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../agents/CopywritingAgent.php';

// ─── Bootstrap ───────────────────────────────────────────────────────────────

$response = new Response();
$request  = new Request();

// CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── Auth ─────────────────────────────────────────────────────────────────────

function getBearerToken(): ?string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        return trim($m[1]);
    }
    return null;
}

function authenticateApiRequest(\PDO $db): ?array
{
    $token = getBearerToken();
    if (empty($token)) return null;

    $hash = hash('sha256', $token);
    $stmt = $db->prepare(
        'SELECT at.user_id, at.brand_id, u.name, u.role
         FROM api_tokens at
         INNER JOIN users u ON u.id = at.user_id
         WHERE at.token_hash = ? AND at.expires_at > NOW() AND at.is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

    if ($row) {
        // Update last used
        $db->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?')->execute([$hash]);
    }

    return $row ?: null;
}

// ─── Router ──────────────────────────────────────────────────────────────────

$db       = Database::getInstance();
$authUser = authenticateApiRequest($db);

if (!$authUser) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Valid Bearer token required']);
    exit;
}

$brandId = (int) $authUser['brand_id'];
$method  = $_SERVER['REQUEST_METHOD'];
$uri     = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri     = rtrim(preg_replace('#/+#', '/', $uri), '/');

// Parse path: /api/content[/{id}[/{action}]]
preg_match('#/api/content(?:/(\d+|generate))?(?:/([a-z_]+))?#i', $uri, $pathParts);
$idOrAction = $pathParts[1] ?? '';
$subAction  = $pathParts[2] ?? '';

$postId = (int) $idOrAction;

try {
    // ── GET /api/content ──────────────────────────────────────────────────────
    if ($method === 'GET' && empty($idOrAction)) {
        $status   = $_GET['status']   ?? 'all';
        $platform = $_GET['platform'] ?? 'all';
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset   = ($page - 1) * $perPage;

        $where  = ["cp.brand_id = ?", "cp.status != 'deleted'"];
        $params = [$brandId];

        $allowedStatuses   = ['draft','pending_approval','approved','scheduled','published','rejected'];
        $allowedPlatforms  = ['instagram','twitter','linkedin','facebook','tiktok','youtube','threads','snapchat'];

        if ($status !== 'all' && in_array($status, $allowedStatuses, true)) {
            $where[]  = 'cp.status = ?';
            $params[] = $status;
        }
        if ($platform !== 'all' && in_array($platform, $allowedPlatforms, true)) {
            $where[]  = 'cp.platform = ?';
            $params[] = $platform;
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM content_posts cp WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $db->prepare(
            "SELECT cp.id, cp.platform, cp.content_type, cp.content_text, cp.status,
                    cp.scheduled_at, cp.published_at, cp.created_at,
                    cp.media_urls, cp.hashtags,
                    COALESCE(pm.impressions,0) AS impressions,
                    COALESCE(pm.engagement_rate,0) AS engagement_rate
             FROM content_posts cp
             LEFT JOIN post_metrics pm ON pm.content_post_id = cp.id
             WHERE {$whereClause}
             ORDER BY cp.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $dataStmt->execute($params);
        $posts = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($posts as &$p) {
            $p['media_urls'] = json_decode($p['media_urls'] ?? '[]', true);
            $p['hashtags']   = json_decode($p['hashtags'] ?? '[]', true);
        }
        unset($p);

        echo json_encode([
            'data' => $posts,
            'meta' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int) ceil($total / $perPage),
            ],
        ]);
        exit;
    }

    // ── POST /api/content/generate ────────────────────────────────────────────
    if ($method === 'POST' && $idOrAction === 'generate') {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        $platform    = $body['platform']     ?? 'instagram';
        $topic       = trim($body['topic']   ?? '');
        $style       = $body['style']        ?? 'professional';
        $language    = $body['language']     ?? 'english';
        $contentType = $body['content_type'] ?? 'caption';

        if (empty($topic)) {
            http_response_code(400);
            echo json_encode(['error' => 'topic is required']);
            exit;
        }

        $agent        = new CopywritingAgent($brandId);
        $brandContext = [];

        $stratStmt = $db->prepare('SELECT field_name, field_value FROM brand_strategy WHERE brand_id = ?');
        $stratStmt->execute([$brandId]);
        foreach ($stratStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $brandContext[$r['field_name']] = $r['field_value'];
        }

        $result = $agent->execute($contentType, [
            'platform'     => $platform,
            'topic'        => $topic,
            'style'        => $style,
            'language'     => $language,
            'brandContext' => $brandContext,
        ]);

        echo json_encode(['generated' => $result]);
        exit;
    }

    // ── POST /api/content ─────────────────────────────────────────────────────
    if ($method === 'POST' && empty($idOrAction)) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        $platform    = $body['platform']     ?? '';
        $contentText = trim($body['content_text'] ?? '');
        $contentType = $body['content_type'] ?? 'post';
        $hashtags    = (array) ($body['hashtags'] ?? []);
        $mediaUrls   = (array) ($body['media_urls'] ?? []);
        $status      = in_array($body['status'] ?? 'draft', ['draft','pending_approval'], true)
            ? ($body['status'] ?? 'draft')
            : 'draft';

        if (empty($platform) || empty($contentText)) {
            http_response_code(422);
            echo json_encode(['error' => 'platform and content_text are required']);
            exit;
        }

        $stmt = $db->prepare(
            'INSERT INTO content_posts (brand_id, platform, content_text, content_type, hashtags, media_urls, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $brandId, $platform, $contentText, $contentType,
            json_encode($hashtags), json_encode($mediaUrls), $status, $authUser['user_id'],
        ]);
        $newId = (int) $db->lastInsertId();

        http_response_code(201);
        echo json_encode(['id' => $newId, 'status' => $status, 'message' => 'Content created.']);
        exit;
    }

    // ── PUT /api/content/{id} ─────────────────────────────────────────────────
    if ($method === 'PUT' && $postId > 0) {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];

        $stmt = $db->prepare("SELECT id, status FROM content_posts WHERE id = ? AND brand_id = ? AND status != 'deleted' LIMIT 1");
        $stmt->execute([$postId, $brandId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Content not found']);
            exit;
        }
        if ($post['status'] === 'published') {
            http_response_code(422);
            echo json_encode(['error' => 'Cannot edit published content']);
            exit;
        }

        $updates = [];
        $params  = [];
        $allowed = ['content_text', 'platform', 'content_type', 'hashtags'];

        foreach ($allowed as $field) {
            if (isset($body[$field])) {
                $updates[] = "{$field} = ?";
                $params[]  = is_array($body[$field]) ? json_encode($body[$field]) : $body[$field];
            }
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update']);
            exit;
        }

        $updates[] = 'updated_at = NOW()';
        $params[]  = $postId;
        $params[]  = $brandId;

        $db->prepare('UPDATE content_posts SET ' . implode(', ', $updates) . ' WHERE id = ? AND brand_id = ?')
           ->execute($params);

        echo json_encode(['id' => $postId, 'message' => 'Updated.']);
        exit;
    }

    // ── DELETE /api/content/{id} ──────────────────────────────────────────────
    if ($method === 'DELETE' && $postId > 0) {
        $stmt = $db->prepare("SELECT id, status FROM content_posts WHERE id = ? AND brand_id = ? AND status != 'deleted' LIMIT 1");
        $stmt->execute([$postId, $brandId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Content not found']);
            exit;
        }

        $db->prepare("UPDATE content_posts SET status = 'deleted', deleted_at = NOW() WHERE id = ? AND brand_id = ?")
           ->execute([$postId, $brandId]);

        echo json_encode(['message' => 'Deleted.']);
        exit;
    }

    // ── POST /api/content/{id}/approve ────────────────────────────────────────
    if ($method === 'POST' && $postId > 0 && $subAction === 'approve') {
        if (!in_array($authUser['role'], ['owner', 'admin', 'editor'], true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }

        $stmt = $db->prepare("SELECT id, status FROM content_posts WHERE id = ? AND brand_id = ? LIMIT 1");
        $stmt->execute([$postId, $brandId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post || $post['status'] !== 'pending_approval') {
            http_response_code(422);
            echo json_encode(['error' => 'Post not pending approval']);
            exit;
        }

        $db->prepare(
            'UPDATE content_posts SET status = "approved", approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ? AND brand_id = ?'
        )->execute([$authUser['user_id'], $postId, $brandId]);

        echo json_encode(['id' => $postId, 'status' => 'approved']);
        exit;
    }

    // ── POST /api/content/{id}/schedule ───────────────────────────────────────
    if ($method === 'POST' && $postId > 0 && $subAction === 'schedule') {
        $body        = json_decode(file_get_contents('php://input'), true) ?: [];
        $scheduledAt = $body['scheduled_at'] ?? '';

        if (empty($scheduledAt) || strtotime($scheduledAt) < time()) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid or past schedule time']);
            exit;
        }

        $stmt = $db->prepare("SELECT id, status FROM content_posts WHERE id = ? AND brand_id = ? AND status NOT IN ('deleted','published') LIMIT 1");
        $stmt->execute([$postId, $brandId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Post not found or not schedulable']);
            exit;
        }

        $db->prepare(
            'UPDATE content_posts SET status = "scheduled", scheduled_at = ?, updated_at = NOW() WHERE id = ? AND brand_id = ?'
        )->execute([$scheduledAt, $postId, $brandId]);

        echo json_encode(['id' => $postId, 'status' => 'scheduled', 'scheduled_at' => $scheduledAt]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Route not found', 'path' => $uri, 'method' => $method]);

} catch (\Throwable $e) {
    error_log('API /content error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
