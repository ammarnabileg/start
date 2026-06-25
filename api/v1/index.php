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

        case 'roles':
            Auth::requireAuth();
            Auth::requirePermission('roles.manage');
            $db  = Database::getInstance();
            $tid = (int)(Auth::user()['tenant_id'] ?? 0);
            $rid = isset($segments[1]) && is_numeric($segments[1]) ? (int)$segments[1] : 0;
            $sub = $segments[2] ?? '';

            if ($sub === 'permissions' && $method === 'PUT') {
                // Save permission assignments for a role
                $permIds = (array)($request->input('permission_ids', []));
                $db->query("DELETE FROM role_permissions WHERE role_id = ?", [$rid]);
                foreach ($permIds as $pid) {
                    if ((int)$pid) $db->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)", [$rid, (int)$pid]);
                }
                Response::json(['success' => true, 'message' => 'Permissions saved']);

            } elseif ($rid && $method === 'PUT') {
                // Update role name/description
                $role = $db->fetch("SELECT id FROM roles WHERE id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND is_system = 0", [$rid, $tid]);
                if (!$role) { Response::error('Role not found or system role', 404); break; }
                $updates = array_filter([
                    'name'        => $request->input('name'),
                    'description' => $request->input('description'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ], fn($v) => $v !== null);
                $db->update('roles', $updates, ['id' => $rid]);
                Response::json(['success' => true, 'message' => 'Role updated']);

            } elseif ($rid && $method === 'DELETE') {
                $db->query("DELETE FROM user_roles WHERE role_id = ?", [$rid]);
                $db->query("DELETE FROM role_permissions WHERE role_id = ?", [$rid]);
                $db->query("DELETE FROM roles WHERE id = ? AND tenant_id = ? AND is_system = 0", [$rid, $tid]);
                Response::json(['success' => true, 'message' => 'Role deleted']);

            } elseif ($method === 'POST') {
                // Create new role
                $name     = trim($request->input('name', ''));
                $desc     = trim($request->input('description', ''));
                $copyFrom = (int)$request->input('copy_from_role_id', 0);
                if (!$name) { Response::error('Role name required', 422); break; }
                $slug  = preg_replace('/[^a-z0-9_]/', '_', strtolower($name));
                $newId = $db->insert('roles', [
                    'tenant_id'   => $tid,
                    'name'        => $name,
                    'slug'        => $slug . '_' . $tid,
                    'description' => $desc,
                    'is_system'   => 0,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
                if ($copyFrom) {
                    $rows = $db->fetchAll("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$copyFrom]);
                    foreach ($rows as $r) {
                        $db->query("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?,?)", [$newId, $r['permission_id']]);
                    }
                }
                Response::json(['success' => true, 'id' => $newId, 'message' => 'Role created']);

            } else {
                $roles = $db->fetchAll("SELECT r.*, COUNT(rp.permission_id) as perm_count FROM roles r LEFT JOIN role_permissions rp ON rp.role_id = r.id WHERE r.tenant_id = ? OR r.tenant_id IS NULL GROUP BY r.id ORDER BY r.is_system DESC, r.name ASC", [$tid]) ?: [];
                Response::success($roles);
            }
            break;

        case 'team':
            Auth::requireAuth();
            $db  = Database::getInstance();
            $tid = (int)(Auth::user()['tenant_id'] ?? 0);
            $members = $db->fetchAll(
                "SELECT u.id, CONCAT(u.first_name,' ',u.last_name) as name, u.email, u.avatar_url,
                        r.name as role
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.user_id = u.id
                 LEFT JOIN roles r ON r.id = ur.role_id
                 WHERE u.tenant_id = ? AND u.status = 'active'
                 ORDER BY u.first_name ASC",
                [$tid]
            ) ?: [];
            Response::success($members);
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

        case 'profile':
            Auth::requireAuth();
            $db  = Database::getInstance();
            $cid = (int)(Auth::user()['id'] ?? 0);
            if ($method === 'GET') {
                $row = $db->fetch("SELECT * FROM candidates WHERE id = ?", [$cid]);
                Response::success($row ?: []);
            } elseif ($method === 'POST') {
                $fullName = trim($request->input('full_name', ''));
                $parts    = $fullName !== '' ? explode(' ', $fullName, 2) : [];
                $updates  = array_filter([
                    'first_name'           => $parts[0] ?? ($request->input('first_name') ?? null),
                    'last_name'            => $parts[1] ?? ($request->input('last_name') ?? null),
                    'phone'                => $request->input('phone'),
                    'location'             => $request->input('location'),
                    'linkedin_url'         => $request->input('linkedin_url'),
                    'portfolio_url'        => $request->input('portfolio_url'),
                    'professional_summary' => $request->input('professional_summary'),
                    'availability'         => $request->input('availability'),
                    'updated_at'           => date('Y-m-d H:i:s'),
                ], fn($v) => $v !== null);
                if ($updates) $db->update('candidates', $updates, ['id' => $cid]);

                // Skills
                $skills = $request->input('skills', []);
                if (is_array($skills)) {
                    $db->query("DELETE FROM candidate_skills WHERE candidate_id = ?", [$cid]);
                    foreach ($skills as $skill) {
                        if (trim((string)$skill)) {
                            $db->insert('candidate_skills', ['candidate_id' => $cid, 'skill_name' => trim($skill), 'created_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
                // Work experiences
                $experiences = $request->input('work_experience', []);
                if (is_array($experiences)) {
                    $db->query("DELETE FROM candidate_experiences WHERE candidate_id = ?", [$cid]);
                    foreach ($experiences as $exp) {
                        if (!empty($exp['title'])) {
                            $db->insert('candidate_experiences', [
                                'candidate_id' => $cid,
                                'job_title'    => $exp['title'] ?? '',
                                'company'      => $exp['company'] ?? '',
                                'start_date'   => $exp['from'] ?? null,
                                'end_date'     => $exp['to'] ?? null,
                                'description'  => $exp['desc'] ?? '',
                                'created_at'   => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                }
                // Education
                $educations = $request->input('education', []);
                if (is_array($educations)) {
                    $db->query("DELETE FROM candidate_education WHERE candidate_id = ?", [$cid]);
                    foreach ($educations as $edu) {
                        if (!empty($edu['degree'])) {
                            $db->insert('candidate_education', [
                                'candidate_id' => $cid,
                                'degree'       => $edu['degree'] ?? '',
                                'institution'  => $edu['school'] ?? '',
                                'start_date'   => $edu['from'] ?? null,
                                'end_date'     => $edu['to'] ?? null,
                                'created_at'   => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                }
                Response::success(['message' => 'Profile saved']);
            } else {
                Response::error('Method not allowed', 405);
            }
            break;

        case 'hr-interviews':
            Auth::requireAuth();
            $db  = Database::getInstance();
            $tid = (int)(Auth::user()['tenant_id'] ?? 0);
            $hiAction = $request->get('action') ?? $request->input('action') ?? '';

            if ($hiAction === 'schedule' || ($method === 'POST' && !$hiAction)) {
                $candidateId   = (int)$request->input('candidate_id');
                $jobId         = (int)$request->input('job_id');
                $scheduledAt   = $request->input('scheduled_at') ?? $request->input('date') . ' ' . $request->input('time');
                $duration      = (int)($request->input('duration', 60));
                $type          = $request->input('type', 'video');
                $link          = $request->input('meeting_link', '');
                $notes         = $request->input('notes', '');
                $interviewerIds = (array)($request->input('interviewer_ids', []));

                // Find or create application for this candidate+job
                $app = $db->fetch("SELECT id FROM applications WHERE candidate_id = ? AND job_id = ? AND tenant_id = ? LIMIT 1", [$candidateId, $jobId, $tid]);
                if (!$app) { Response::error('No application found for this candidate + job', 404); break; }

                $hiId = $db->insert('human_interviews', [
                    'application_id' => $app['id'],
                    'scheduled_at'   => $scheduledAt,
                    'duration_min'   => $duration,
                    'interview_type' => $type,
                    'meeting_link'   => $link,
                    'notes'          => $notes,
                    'status'         => 'scheduled',
                    'created_by'     => Auth::user()['id'],
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
                foreach ($interviewerIds as $uid) {
                    if ((int)$uid) $db->insert('human_interview_evaluators', ['interview_id' => $hiId, 'user_id' => (int)$uid]);
                }
                $db->update('applications', ['current_stage' => 'tech_interview'], ['id' => $app['id']]);
                Response::success(['id' => $hiId, 'message' => 'Interview scheduled']);

            } elseif ($hiAction === 'update') {
                $hiId  = (int)$request->input('id');
                $updates = array_filter([
                    'scheduled_at'   => $request->input('scheduled_at') ?? (($request->input('date') ?? '') . ' ' . ($request->input('time') ?? '')),
                    'duration_min'   => $request->input('duration') ? (int)$request->input('duration') : null,
                    'interview_type' => $request->input('type'),
                    'meeting_link'   => $request->input('meeting_link'),
                    'notes'          => $request->input('notes'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ], fn($v) => $v !== null && $v !== '');
                if ($hiId && $updates) $db->update('human_interviews', $updates, ['id' => $hiId]);
                Response::success(['message' => 'Interview updated']);

            } elseif ($hiAction === 'cancel') {
                $hiId = (int)$request->input('id');
                if ($hiId) $db->update('human_interviews', ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $hiId]);
                Response::success(['message' => 'Interview cancelled']);

            } elseif ($hiAction === 'send_link') {
                // Generate / retrieve AI interview link for a candidate from talent pool
                Auth::requirePermission('interviews.view');
                $candidateId = (int)$request->input('candidate_id');
                if (!$candidateId) { Response::error('candidate_id required', 422); break; }
                // Find most recent active application for this candidate in this tenant
                $app = $db->fetch(
                    "SELECT a.id, a.job_id, j.interview_link_token FROM applications a
                     JOIN jobs j ON j.id = a.job_id
                     WHERE a.candidate_id = ? AND a.tenant_id = ? AND a.current_stage NOT IN ('hired','rejected','withdrawn')
                     ORDER BY a.applied_at DESC LIMIT 1",
                    [$candidateId, $tid]
                );
                if (!$app) { Response::error('No active application found for this candidate', 404); break; }
                $baseUrl = rtrim($_ENV['APP_URL'] ?? (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/');
                $link = $baseUrl . '/interview/' . ($app['interview_link_token'] ?? '');
                Response::success(['link' => $link, 'message' => 'Interview link ready']);

            } elseif ($hiAction === 'remind') {
                // Reminder logic placeholder — returns success (email integration can be added later)
                Response::success(['message' => 'Reminder sent']);

            } elseif ($hiAction === 'complete') {
                $hiId   = (int)$request->input('id');
                $rating = (int)($request->input('overall_rating', 3));
                $notes  = $request->input('outcome_notes', '');
                if ($hiId) {
                    $db->update('human_interviews', [
                        'status'          => 'completed',
                        'overall_rating'  => $rating,
                        'outcome_notes'   => $notes,
                        'completed_at'    => date('Y-m-d H:i:s'),
                        'updated_at'      => date('Y-m-d H:i:s'),
                    ], ['id' => $hiId]);
                }
                Response::success(['message' => 'Interview marked as complete']);

            } else {
                // List human interviews for this tenant
                $rows = $db->fetchAll(
                    "SELECT hi.*, CONCAT(c.first_name,' ',c.last_name) as candidate_name, j.title as job_title
                     FROM human_interviews hi
                     JOIN applications a ON a.id = hi.application_id
                     JOIN candidates c ON c.id = a.candidate_id
                     JOIN jobs j ON j.id = a.job_id
                     WHERE a.tenant_id = ? ORDER BY hi.scheduled_at DESC LIMIT 100",
                    [$tid]
                ) ?: [];
                Response::success($rows);
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
