<?php
declare(strict_types=1);
/**
 * api/v1/users.php — Users CRUD (HR team management)
 */

Auth::requireAuth();
$db     = Database::getInstance();
$userId = Auth::user()['id'];
$tid    = Auth::user()['tenant_id'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($request->get('id') ?? $request->input('id') ?? 0);

// ── List users ────────────────────────────────────────────────────────────
if ($method === 'GET') {
    Auth::requirePermission('users.view');
    $page   = max(1, (int)$request->get('page', 1));
    $search = trim($request->get('search', ''));
    $role   = $request->get('role', '');

    $where  = ['u.tenant_id = ?'];
    $params = [$tid];
    if ($search) { $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($role)   { $where[] = 'EXISTS (SELECT 1 FROM user_roles ur2 JOIN roles r2 ON r2.id=ur2.role_id WHERE ur2.user_id=u.id AND r2.slug=?)'; $params[] = $role; }

    // Exclude candidates
    $where[] = 'NOT EXISTS (SELECT 1 FROM user_roles ur3 JOIN roles r3 ON r3.id=ur3.role_id WHERE ur3.user_id=u.id AND r3.slug=\'candidate\')';

    $sql = "SELECT u.id, u.full_name, u.email, u.status, u.last_login_at as last_login, u.created_at,
                   GROUP_CONCAT(r.slug) as roles
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY u.id ORDER BY u.created_at DESC";

    $result = $db->paginate($sql, $params, $page, 25);
    Response::paginated($result['data'], $result['total'], $page, 25);
}

// ── Create / invite user ──────────────────────────────────────────────────
elseif ($method === 'POST') {
    Auth::requirePermission('users.create');

    $data = $request->only(['full_name','email','role','password']);

    if (empty($data['email']) || empty($data['role'])) {
        Response::error('Email and role are required', 422); exit;
    }

    $exists = $db->fetchColumn("SELECT id FROM users WHERE email = ? AND tenant_id = ?", [$data['email'], $tid]);
    if ($exists) { Response::error('Email already exists in your team', 409); exit; }

    $rawName   = trim($data['full_name'] ?? $data['email']);
    $nameParts = explode(' ', $rawName, 2);

    $newUserId = $db->insert('users', [
        'tenant_id'         => $tid,
        'first_name'        => $nameParts[0],
        'last_name'         => $nameParts[1] ?? '',
        'email'             => strtolower(trim($data['email'])),
        'password_hash'     => password_hash($data['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
        'status'            => 'active',
        'email_verified_at' => date('Y-m-d H:i:s'),
        'created_at'        => date('Y-m-d H:i:s'),
        'updated_at'        => date('Y-m-d H:i:s')
    ]);

    // Assign role
    $roleRow = $db->fetch("SELECT id FROM roles WHERE slug = ? AND (tenant_id = ? OR tenant_id IS NULL) LIMIT 1", [$data['role'], $tid]);
    if ($roleRow) {
        $db->query("INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())", [$newUserId, $roleRow['id']]);
    }

    $db->insert('audit_logs', [
        'tenant_id' => $tid, 'user_id' => $userId,
        'action' => 'user.invited', 'resource_type' => 'user', 'resource_id' => $newUserId,
        'details' => json_encode(['email' => $data['email'], 'role' => $data['role']]),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    Response::success(['id' => $newUserId, 'message' => 'Team member added']);
}

// ── Update user role/status ───────────────────────────────────────────────
elseif ($method === 'PUT') {
    Auth::requirePermission('users.edit');
    if (!$id) { Response::error('User ID required', 422); exit; }

    $user = $db->fetch("SELECT id FROM users WHERE id = ? AND tenant_id = ?", [$id, $tid]);
    if (!$user) { Response::error('Not found', 404); exit; }

    $rawData = $request->only(['full_name','role','status']);
    $updates = [];

    if (!empty($rawData['full_name'])) {
        $nameParts = explode(' ', trim($rawData['full_name']), 2);
        $updates['first_name'] = $nameParts[0];
        $updates['last_name']  = $nameParts[1] ?? '';
    }
    if (!empty($rawData['status'])) {
        $updates['status'] = $rawData['status'];
    }
    if (!empty($rawData['role'])) {
        $roleRow = $db->fetch("SELECT id FROM roles WHERE slug = ? AND (tenant_id = ? OR tenant_id IS NULL) LIMIT 1", [$rawData['role'], $tid]);
        if ($roleRow) {
            $db->query("DELETE FROM user_roles WHERE user_id = ?", [$id]);
            $db->query("INSERT INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())", [$id, $roleRow['id']]);
        }
    }

    if (empty($updates)) { Response::error('Nothing to update', 422); exit; }

    $updates['updated_at'] = date('Y-m-d H:i:s');
    $db->update('users', $updates, ['id' => $id]);

    Response::success(['message' => 'Updated']);
}

// ── Remove user ───────────────────────────────────────────────────────────
elseif ($method === 'DELETE') {
    Auth::requirePermission('users.delete');
    $targetId = $id ?: (int)$request->input('id');
    if ($targetId === $userId) { Response::error('Cannot remove yourself', 403); exit; }

    $user = $db->fetch("SELECT id FROM users WHERE id = ? AND tenant_id = ?", [$targetId, $tid]);
    if (!$user) { Response::error('Not found', 404); exit; }

    $db->update('users', ['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $targetId]);

    $db->insert('audit_logs', [
        'tenant_id' => $tid, 'user_id' => $userId,
        'action' => 'user.removed', 'resource_type' => 'user', 'resource_id' => $targetId,
        'details' => '{}', 'created_at' => date('Y-m-d H:i:s')
    ]);

    Response::success(['message' => 'User removed']);
}

else {
    Response::error('Method not allowed', 405);
}
