<?php
declare(strict_types=1);

/**
 * api/v1/index.php - REST API dispatcher for the AI Recruitment platform.
 *
 * Reached via the front controller for any /api/v1/* request. It guarantees a
 * bootstrapped environment (core classes + Modules autoloader), applies JSON
 * defaults, then routes to the appropriate handler group:
 *
 *   /api/v1/interviews/{token}/...   -> interviews.php  (public, token auth)
 *   /api/v1/ai/...                   -> ai.php          (HR auth required)
 *
 * All responses are JSON via the global Response helper.
 */

// ----------------------------------------------------------------------
// Bootstrap (idempotent — the front controller may have already loaded core).
// ----------------------------------------------------------------------
require_once dirname(__DIR__, 2) . '/bootstrap.php';

if (!class_exists('Response')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'message' => 'API bootstrap failed: core not available.']);
    exit;
}

// ----------------------------------------------------------------------
// CORS + JSON defaults.
// ----------------------------------------------------------------------
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Tenant-ID');
    header('Vary: Origin');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ----------------------------------------------------------------------
// Resolve the path within /api/v1.
// ----------------------------------------------------------------------
$request = isset($request) && $request instanceof Request ? $request : new Request();
$fullPath = $request->path();                 // e.g. /api/v1/ai/build-job
$apiPath  = preg_replace('#^/api/v1#', '', $fullPath); // e.g. /ai/build-job
$apiPath  = '/' . trim((string) $apiPath, '/');
$method   = $request->method();

$segments = $apiPath === '/' ? [] : explode('/', trim($apiPath, '/'));
$group    = $segments[0] ?? '';

try {
    switch ($group) {
        case 'interviews':
            require __DIR__ . '/interviews.php';
            if (class_exists('InterviewApi')) {
                (new InterviewApi($request))->dispatch($segments, $method);
            }
            break;

        case 'ai':
            require __DIR__ . '/ai.php';
            if (class_exists('AiApi')) {
                (new AiApi($request))->dispatch($segments, $method);
            }
            break;

        case 'auth':
            require __DIR__ . '/auth.php';
            break;

        case 'jobs':
            require __DIR__ . '/jobs.php';
            break;

        case 'candidates':
            require __DIR__ . '/candidates.php';
            break;

        case 'applications':
            require __DIR__ . '/applications.php';
            break;

        case 'offers':
            require __DIR__ . '/offers.php';
            break;

        case 'users':
            require __DIR__ . '/users.php';
            break;

        case 'admin':
            require __DIR__ . '/admin.php';
            break;

        case 'settings':
            Auth::requireAuth();
            $action = $request->get('action') ?? $request->input('action') ?? '';
            $db  = Database::getInstance();
            $tid = (int) (Auth::user()['tenant_id'] ?? 0);

            if ($action === 'save_settings') {
                // Generic key-value settings (non-API-key)
                $settings = $request->input('settings', []);
                foreach ($settings as $key => $value) {
                    $key = preg_replace('/[^a-z0-9_.]/i', '', $key);
                    $existing = $db->fetchColumn(
                        "SELECT id FROM system_settings WHERE setting_key=? AND tenant_id" . ($tid ? "=?" : " IS NULL"),
                        array_filter([$key, $tid ?: null])
                    );
                    if ($existing) {
                        $db->update('system_settings', ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $existing]);
                    } else {
                        $db->insert('system_settings', ['tenant_id' => $tid ?: null, 'setting_key' => $key, 'setting_value' => $value, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                    }
                }
                Response::success(['message' => 'Settings saved']);

            } elseif ($action === 'save_api_keys') {
                // Per-tenant API key storage (encrypted)
                if (!$tid) Response::error('No tenant context', 403);
                $updated = [];
                foreach (['openai' => 'openai_api_key', 'heygen' => 'heygen_api_key', 'openai_model' => 'openai_model'] as $input => $col) {
                    $val = $request->input($input);
                    if ($val === null) continue;
                    $val = trim($val);
                    if ($val === '') {
                        // Clear the key
                        $db->query("UPDATE tenants SET `{$col}` = NULL, updated_at = NOW() WHERE id = ?", [$tid]);
                    } else {
                        ApiKeyManager::saveTenantKey($tid, $input === 'openai_model' ? 'openai_model' : $input, $val);
                    }
                    $updated[] = $input;
                }
                ApiKeyManager::clearCache();
                Response::success(['message' => 'API keys saved', 'updated' => $updated]);

            } elseif ($action === 'test_openai') {
                $key = trim($request->input('key', ''));
                if ($key === '') {
                    // Test the tenant's stored key
                    $key = ApiKeyManager::getTenantOpenAIKey($tid ?: null);
                }
                if ($key === '') Response::error('No OpenAI key configured', 400);
                Response::json(ApiKeyManager::testOpenAIKey($key));

            } elseif ($action === 'test_heygen') {
                $key = trim($request->input('key', ''));
                if ($key === '') {
                    $key = ApiKeyManager::getTenantHeyGenKey($tid ?: null);
                }
                if ($key === '') Response::error('No HeyGen key configured', 400);
                Response::json(ApiKeyManager::testHeyGenKey($key));

            } elseif ($action === 'get_api_keys') {
                // Return masked keys so the UI can show whether keys are set
                if (!$tid) Response::error('No tenant context', 403);
                $row = $db->fetch('SELECT openai_api_key, heygen_api_key, openai_model FROM tenants WHERE id = ?', [$tid]);
                $mask = fn(?string $enc) => $enc
                    ? (function(string $plain) { return $plain !== '' ? '***' . substr($plain, -4) : null; })(ApiKeyManager::decrypt($enc))
                    : null;
                Response::success([
                    'openai_set'    => !empty($row['openai_api_key']),
                    'heygen_set'    => !empty($row['heygen_api_key']),
                    'openai_masked' => $mask($row['openai_api_key'] ?? null),
                    'heygen_masked' => $mask($row['heygen_api_key'] ?? null),
                    'openai_model'  => $row['openai_model'] ?? 'gpt-4o',
                ]);

            } elseif ($action === 'get_ai_status') {
                if (!$tid) Response::error('No tenant context', 403);
                Response::success(\TenantAIProvider::status($tid));

            } else {
                Response::error('Unknown settings action', 400);
            }
            break;

        case 'avatars':
            // Avatar management
            Auth::requireAuth();
            $db = Database::getInstance();
            $tid = Auth::user()['tenant_id'];
            $avs = $db->fetchAll("SELECT * FROM avatars WHERE tenant_id = ? ORDER BY created_at DESC", [$tid]) ?: [];
            Response::success($avs);
            break;

        case 'talent-pools':
            Auth::requireAuth();
            $db = Database::getInstance();
            $tid = Auth::user()['tenant_id'];
            $pools = $db->fetchAll("SELECT tp.*, (SELECT COUNT(*) FROM talent_pool_candidates tpc WHERE tpc.pool_id = tp.id) as candidate_count FROM talent_pools tp WHERE tp.tenant_id = ? ORDER BY tp.created_at DESC", [$tid]) ?: [];
            Response::success($pools);
            break;

        case 'notifications':
            Auth::requireAuth();
            $db  = Database::getInstance();
            $uid = (int)(Auth::user()['id'] ?? 0);
            $tid = (int)(Auth::user()['tenant_id'] ?? 0);
            if ($method === 'POST') {
                // Mark all as read
                $db->query("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL", [$uid]);
                Response::success(['message' => 'Marked as read']);
            } else {
                $items = $db->fetchAll(
                    "SELECT id, type, title, COALESCE(message, body) as body, read_at, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
                    [$uid]
                ) ?: [];
                Response::success($items);
            }
            break;

        case 'cv':
            Auth::requireAuth();
            $db  = Database::getInstance();
            $cid = (int)(Auth::user()['id'] ?? 0);
            $cvAction = $request->get('action') ?? $request->input('action') ?? '';

            if ($cvAction === 'upload' && $method === 'POST') {
                if (empty($_FILES['cv']['tmp_name'])) { Response::error('No file uploaded', 422); exit; }
                $file = $_FILES['cv'];
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf','doc','docx'])) { Response::error('Only PDF/DOC/DOCX allowed', 422); exit; }
                if ($file['size'] > 10 * 1024 * 1024) { Response::error('File too large (max 10MB)', 422); exit; }
                $uploadDir = dirname(__DIR__, 2) . '/storage/cvs/';
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
                $filename = 'cv_' . $cid . '_' . time() . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) { Response::error('Upload failed', 500); exit; }
                $url = '/storage/cvs/' . $filename;
                $db->query("UPDATE candidates SET cv_url = ?, updated_at = NOW() WHERE id = ?", [$url, $cid]);
                Response::success(['url' => $url, 'filename' => $file['name']]);

            } elseif ($cvAction === 'delete' && $method === 'POST') {
                $row = $db->fetch("SELECT cv_url FROM candidates WHERE id = ?", [$cid]);
                if (!empty($row['cv_url'])) {
                    $fullPath = dirname(__DIR__, 2) . $row['cv_url'];
                    if (file_exists($fullPath)) @unlink($fullPath);
                }
                $db->query("UPDATE candidates SET cv_url = NULL, cv_text = NULL, updated_at = NOW() WHERE id = ?", [$cid]);
                Response::success(['message' => 'CV removed']);

            } else {
                Response::error('Unknown CV action', 400);
            }
            break;

        case '':
            Response::success(['name' => 'AI Recruitment API', 'version' => 'v1', 'status' => 'ok']);
            break;

        default:
            Response::error('Unknown API resource: ' . $group, 404);
    }
} catch (\Throwable $e) {
    $debug = (($_ENV['APP_DEBUG'] ?? 'false') === 'true');
    $message = $debug ? $e->getMessage() : 'Internal server error.';
    // Log the full error regardless of debug mode.
    $logDir = ($_ENV['APP_LOG_PATH'] ?? (dirname(__DIR__, 2) . '/storage/logs'));
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents(
        rtrim($logDir, '/\\') . '/api.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL,
        FILE_APPEND
    );
    Response::error($message, 500);
}
