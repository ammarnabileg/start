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
            // Settings API — save system/company settings
            Auth::requireAuth();
            $action = $request->get('action') ?? $request->input('action') ?? '';
            $db = Database::getInstance();
            if ($action === 'save_settings') {
                $settings = $request->input('settings', []);
                $tid = Auth::user()['tenant_id'] ?? null;
                foreach ($settings as $key => $value) {
                    $key = preg_replace('/[^a-z0-9_.]/i', '', $key);
                    $existing = $db->fetchColumn("SELECT id FROM system_settings WHERE setting_key=? AND tenant_id" . ($tid ? "=?" : " IS NULL"), array_filter([$key, $tid]));
                    if ($existing) {
                        $db->update('system_settings', ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $existing]);
                    } else {
                        $db->insert('system_settings', ['tenant_id' => $tid, 'setting_key' => $key, 'setting_value' => $value, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                    }
                }
                Response::success(['message' => 'Settings saved']);
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
