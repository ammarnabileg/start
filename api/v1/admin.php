<?php
declare(strict_types=1);
/**
 * api/v1/admin.php — Super Admin API endpoints
 */

Auth::requireSuper();
$db     = Database::getInstance();
$userId = Auth::user()['id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $request->get('action') ?? $request->input('action') ?? '';

// ── Terminal ───────────────────────────────────────────────────────────────
if ($action === 'terminal') {
    $cmd = $request->input('command', '');

    $allowed = [
        'php_version'       => fn() => 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')',
        'php_extensions'    => fn() => implode(', ', get_loaded_extensions()),
        'disk_space'        => fn() => 'Total: ' . formatBytes(disk_total_space('/')) . ' | Free: ' . formatBytes(disk_free_space('/')),
        'memory_info'       => fn() => 'Peak: ' . formatBytes(memory_get_peak_usage(true)) . ' | Current: ' . formatBytes(memory_get_usage(true)),
        'mysql_version'     => fn() => $db->fetchColumn('SELECT VERSION()') ?? 'Unknown',
        'clear_cache'       => function() {
            $dir = BASE_PATH . '/storage/cache';
            $count = 0;
            if (is_dir($dir)) {
                foreach (glob("$dir/*.cache") ?: [] as $f) { unlink($f); $count++; }
            }
            if (function_exists('opcache_reset')) { opcache_reset(); return "Cleared $count cache files. OPcache reset."; }
            return "Cleared $count cache files.";
        },
        'list_tenants'      => function() use ($db) {
            $rows = $db->fetchAll("SELECT id, name, slug, status FROM tenants ORDER BY created_at DESC LIMIT 20");
            if (!$rows) return "No tenants found.";
            return implode("\n", array_map(fn($r) => "[{$r['id']}] {$r['name']} ({$r['slug']}) — {$r['status']}", $rows));
        },
        'show_stats'        => function() use ($db) {
            $t = $db->fetchColumn("SELECT COUNT(*) FROM tenants") ?? 0;
            $u = $db->fetchColumn("SELECT COUNT(*) FROM users") ?? 0;
            $j = $db->fetchColumn("SELECT COUNT(*) FROM jobs") ?? 0;
            $i = $db->fetchColumn("SELECT COUNT(*) FROM interviews") ?? 0;
            return "Tenants: $t | Users: $u | Jobs: $j | Interviews: $i";
        },
        'check_writable'    => function() {
            $dirs = ['storage', 'storage/cache', 'storage/logs', 'public/uploads'];
            $res = [];
            foreach ($dirs as $d) {
                $full = BASE_PATH . '/' . $d;
                $res[] = $d . ': ' . (is_writable($full) ? '✓ writable' : '✗ NOT writable');
            }
            return implode("\n", $res);
        },
        'env_check'         => function() {
            $keys = ['APP_NAME','DB_HOST','DB_NAME','OPENAI_API_KEY','HEYGEN_API_KEY'];
            $res = [];
            foreach ($keys as $k) {
                $val = $_ENV[$k] ?? '';
                $res[] = $k . ': ' . ($val ? '✓ set' : '✗ MISSING');
            }
            return implode("\n", $res);
        },
        'installed_date'    => function() {
            $f = BASE_PATH . '/setup/.installed';
            return file_exists($f) ? 'Installed: ' . file_get_contents($f) : 'Not installed via setup wizard.';
        },
        'view_logs'         => function() {
            $f = BASE_PATH . '/storage/logs/app.log';
            if (!file_exists($f)) return 'No log file found.';
            $lines = array_slice(file($f) ?: [], -20);
            return implode('', $lines) ?: 'Log is empty.';
        }
    ];

    function formatBytes(int|float $bytes): string {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
        return round($bytes / 1024, 2) . ' KB';
    }

    // Aliases: frontend uses colon-style, API uses underscore-style
    $aliases = ['cache:clear'=>'clear_cache','tenants:list'=>'list_tenants','logs:tail'=>'view_logs','migrate:status'=>'show_stats','queue:status'=>'show_stats','users:count'=>'show_stats','ai:usage'=>'show_stats','status'=>'show_stats','version'=>'php_version','whoami'=>'env_check'];
    if (isset($aliases[$cmd])) $cmd = $aliases[$cmd];

    if (!isset($allowed[$cmd])) {
        Response::error("Command not found: $cmd. Type 'help' for available commands.", 403);
        exit;
    }

    try {
        $output = $allowed[$cmd]();
        Response::success(['output' => $output, 'command' => $cmd, 'time' => date('H:i:s')]);
    } catch (Throwable $e) {
        Response::error('Command error: ' . $e->getMessage(), 500);
    }
    exit;
}

// ── Company management ────────────────────────────────────────────────────
if ($action === 'companies' || ($method === 'GET' && !$action)) {
    $page   = max(1, (int)$request->get('page', 1));
    $status = $request->get('status', '');
    $search = trim($request->get('search', ''));

    $where  = ['1=1'];
    $params = [];
    if ($status) { $where[] = 'status = ?'; $params[] = $status; }
    if ($search) { $where[] = '(name LIKE ? OR slug LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

    $sql = "SELECT t.id, t.name, t.slug, t.plan, t.status, t.created_at,
                   (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) as user_count,
                   (SELECT COUNT(*) FROM jobs j WHERE j.tenant_id = t.id) as job_count,
                   (SELECT COUNT(*) FROM interviews i JOIN applications a ON a.id = i.application_id WHERE a.tenant_id = t.id) as interview_count
            FROM tenants t
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.created_at DESC";
    $result = $db->paginate($sql, $params, $page, 20);
    Response::paginated($result['data'], $result['total'], $page, 20);
    exit;
}

// ── Create company ────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'create_company') {
    $data = $request->only(['name','slug','plan','max_users','max_jobs','owner_email','owner_name','owner_password']);

    if (empty($data['name']) || empty($data['slug']) || empty($data['owner_email']) || empty($data['owner_password'])) {
        Response::error('Name, slug, owner email, and password are required', 422); exit;
    }
    // Also store max_users/max_jobs in settings JSON
    $settingsJson = json_encode(['max_users' => (int)($data['max_users'] ?? 10), 'max_jobs' => (int)($data['max_jobs'] ?? 20)]);

    $exists = $db->fetchColumn("SELECT id FROM tenants WHERE slug = ?", [$data['slug']]);
    if ($exists) { Response::error('Slug already taken', 409); exit; }

    $tenantId = $db->insert('tenants', [
        'name'       => $data['name'],
        'slug'       => $data['slug'],
        'plan'       => $data['plan'] ?? 'starter',
        'status'     => 'active',
        'settings'   => $settingsJson,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    $ownerName  = trim($data['owner_name'] ?? $data['owner_email']);
    $ownerParts = explode(' ', $ownerName, 2);
    $ownerId = $db->insert('users', [
        'tenant_id'         => $tenantId,
        'first_name'        => $ownerParts[0],
        'last_name'         => $ownerParts[1] ?? '',
        'email'             => $data['owner_email'],
        'password_hash'     => password_hash($data['owner_password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
        'status'            => 'active',
        'email_verified_at' => date('Y-m-d H:i:s'),
        'created_at'        => date('Y-m-d H:i:s'),
        'updated_at'        => date('Y-m-d H:i:s')
    ]);
    // Assign company_owner role
    $ownerRole = $db->fetch("SELECT id FROM roles WHERE slug = 'company_owner' LIMIT 1");
    if ($ownerRole) {
        $db->query("INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())", [$ownerId, $ownerRole['id']]);
    }

    Response::success(['tenant_id' => $tenantId, 'owner_id' => $ownerId]);
    exit;
}

// ── Update company ────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'update_company') {
    $id   = (int)$request->input('id');
    if (!$id) { Response::error('Company ID required', 422); exit; }

    $name = trim($request->input('name', ''));
    $slug = trim($request->input('slug', ''));
    $plan = $request->input('plan', 'starter');

    if (!$name) { Response::error('Company name is required', 422); exit; }

    // Check slug uniqueness (excluding current)
    if ($slug) {
        $conflict = $db->fetchColumn("SELECT id FROM tenants WHERE slug = ? AND id != ?", [$slug, $id]);
        if ($conflict) { Response::error('Slug already taken by another company', 409); exit; }
    }

    $updates = ['name' => $name, 'plan' => $plan, 'updated_at' => date('Y-m-d H:i:s')];
    if ($slug) $updates['slug'] = $slug;

    // Store max_users/max_jobs in settings JSON
    $maxUsers = (int)$request->input('max_users', 10);
    $maxJobs  = (int)$request->input('max_jobs', 20);
    $existing = $db->fetch("SELECT settings FROM tenants WHERE id = ?", [$id]);
    $settings = json_decode($existing['settings'] ?? '{}', true) ?: [];
    $settings['max_users'] = $maxUsers;
    $settings['max_jobs']  = $maxJobs;
    $updates['settings'] = json_encode($settings);

    $db->update('tenants', $updates, ['id' => $id]);
    Response::success(['message' => 'Company updated']);
    exit;
}

// ── Change company status (suspend / activate / archive) ──────────────────
if ($method === 'POST' && in_array($action, ['company_status','suspend_company','activate_company','archive_company'])) {
    $tenantId = (int)($request->input('tenant_id') ?: $request->input('id'));
    if (!$tenantId) { Response::error('Company ID required', 422); exit; }

    if ($action === 'company_status') {
        $newStatus = $request->input('status', '');
        $allowed   = ['active', 'suspended', 'inactive', 'archived'];
        if (!in_array($newStatus, $allowed)) { Response::error('Invalid status', 422); exit; }
    } else {
        $statusMap = ['suspend_company'=>'suspended','activate_company'=>'active','archive_company'=>'archived'];
        $newStatus = $statusMap[$action];
    }

    $db->update('tenants', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $tenantId]);
    Response::success(['status' => $newStatus]);
    exit;
}

// ── Delete company ────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'delete_company') {
    $tenantId = (int)($request->input('tenant_id') ?: $request->input('id'));
    if (!$tenantId) { Response::error('Company ID required', 422); exit; }

    // Cascade delete: users, jobs, applications, interviews, settings
    $db->query("DELETE FROM system_settings WHERE tenant_id = ?", [$tenantId]);
    $db->query("DELETE FROM notifications WHERE tenant_id = ?", [$tenantId]);
    $db->query("DELETE FROM ai_usage_logs WHERE tenant_id = ?", [$tenantId]);
    // Delete interviews via applications
    $db->query("DELETE ie FROM interview_evaluations ie JOIN interviews i ON i.id = ie.interview_id JOIN applications a ON a.id = i.application_id WHERE a.tenant_id = ?", [$tenantId]);
    $db->query("DELETE i FROM interviews i JOIN applications a ON a.id = i.application_id WHERE a.tenant_id = ?", [$tenantId]);
    $db->query("DELETE FROM applications WHERE tenant_id = ?", [$tenantId]);
    $db->query("DELETE FROM jobs WHERE tenant_id = ?", [$tenantId]);
    $db->query("DELETE FROM users WHERE tenant_id = ?", [$tenantId]);
    $db->query("DELETE FROM tenants WHERE id = ?", [$tenantId]);

    Response::success(['message' => 'Company and all data permanently deleted']);
    exit;
}

// ── Bulk company actions ──────────────────────────────────────────────────
if ($method === 'POST' && $action === 'bulk_companies') {
    $ids    = array_filter(array_map('intval', (array)$request->input('ids', [])));
    $bulkAct = $request->input('action', '');
    if (empty($ids)) { Response::error('No companies selected', 422); exit; }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    if ($bulkAct === 'suspend') {
        $db->query("UPDATE tenants SET status='suspended', updated_at=NOW() WHERE id IN ($placeholders)", $ids);
    } elseif ($bulkAct === 'archive') {
        $db->query("UPDATE tenants SET status='archived', updated_at=NOW() WHERE id IN ($placeholders)", $ids);
    } elseif ($bulkAct === 'activate') {
        $db->query("UPDATE tenants SET status='active', updated_at=NOW() WHERE id IN ($placeholders)", $ids);
    } else {
        Response::error('Unknown bulk action', 422); exit;
    }
    Response::success(['affected' => count($ids)]);
    exit;
}

// ── Impersonate ──────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'impersonate') {
    $targetUserId = (int)$request->input('user_id');
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$targetUserId]);
    if (!$user) { Response::error('User not found', 404); exit; }

    $_SESSION['impersonating'] = $userId;
    $_SESSION['user']          = $user;

    Response::success(['redirect' => '/dashboard']);
    exit;
}

// ── Reset company owner password ─────────────────────────────────────────
if ($method === 'POST' && $action === 'reset_company_password') {
    $tenantId = (int)($request->input('tenant_id') ?: $request->input('id'));
    if (!$tenantId) { Response::error('Company ID required', 422); exit; }

    $owner = $db->fetch("SELECT id, email, first_name, last_name FROM users WHERE tenant_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1", [$tenantId]);
    if (!$owner) { Response::error('No active user found for this company', 404); exit; }

    $newPass = trim($request->input('password', ''));
    if ($newPass !== '') {
        if (strlen($newPass) < 8) { Response::error('Password must be at least 8 characters', 422); exit; }
        $db->update('users', ['password_hash' => password_hash($newPass, PASSWORD_DEFAULT), 'updated_at' => date('Y-m-d H:i:s')], ['id' => $owner['id']]);
    }

    Response::success([
        'email'      => $owner['email'],
        'name'       => trim($owner['first_name'] . ' ' . $owner['last_name']),
        'reset'      => $newPass !== '',
    ]);
    exit;
}

// ── Save per-tenant API keys ─────────────────────────────────────────────
if ($method === 'POST' && $action === 'save_tenant_keys') {
    $tenantId = (int)$request->input('tenant_id');
    if (!$tenantId) { Response::error('tenant_id required', 422); exit; }

    $updates = ['updated_at' => date('Y-m-d H:i:s')];

    $openai = $request->input('openai');
    $heygen = $request->input('heygen');
    $model  = $request->input('openai_model');

    if ($openai !== null) {
        $val = trim($openai);
        $updates['openai_api_key'] = $val === '' ? null : ApiKeyManager::encrypt($val);
    }
    if ($heygen !== null) {
        $val = trim($heygen);
        $updates['heygen_api_key'] = $val === '' ? null : ApiKeyManager::encrypt($val);
    }
    if ($model !== null) {
        $updates['openai_model'] = $model === '' ? null : $model;
    }

    $db->update('tenants', $updates, ['id' => $tenantId]);
    ApiKeyManager::clearCache();
    Response::success(['message' => 'Keys saved']);
    exit;
}

// ── Global stats for dashboard ────────────────────────────────────────────
if ($action === 'stats') {
    $stats = [
        'total_companies'  => (int)$db->fetchColumn("SELECT COUNT(*) FROM tenants") ?: 0,
        'active_companies' => (int)$db->fetchColumn("SELECT COUNT(*) FROM tenants WHERE status='active'") ?: 0,
        'total_users'      => (int)$db->fetchColumn("SELECT COUNT(*) FROM users") ?: 0,
        'total_interviews' => (int)$db->fetchColumn("SELECT COUNT(*) FROM interviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") ?: 0,
        'total_tokens_30d' => (int)$db->fetchColumn("SELECT COALESCE(SUM(tokens_used),0) FROM ai_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") ?: 0,
        'system' => [
            'php'   => PHP_VERSION,
            'disk'  => round(disk_free_space('/') / disk_total_space('/') * 100) . '% free',
            'cache' => count(glob(BASE_PATH . '/storage/cache/*.cache') ?: []) . ' files'
        ]
    ];
    Response::success($stats);
    exit;
}

Response::error('Unknown action', 400);
