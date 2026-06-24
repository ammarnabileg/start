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

    $where  = ['tenant_id = ?', "role != 'candidate'"];
    $params = [$tid];
    if ($search) { $where[] = '(full_name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($role)   { $where[] = 'role = ?'; $params[] = $role; }

    $sql = "SELECT id, full_name, email, role, status, last_login, created_at
            FROM users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";

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

    $newUserId = $db->insert('users', [
        'tenant_id'  => $tid,
        'full_name'  => $data['full_name'] ?? $data['email'],
        'email'      => strtolower(trim($data['email'])),
        'password'   => password_hash($data['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
        'role'       => $data['role'],
        'status'     => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    $db->insert('audit_logs', [
        'tenant_id' => $tid, 'user_id' => $userId,
        'action' => 'user.invited', 'entity_type' => 'user', 'entity_id' => $newUserId,
        'meta' => json_encode(['email' => $data['email'], 'role' => $data['role']]),
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

    $data = array_filter($request->only(['full_name','role','status']), fn($v) => $v !== null);
    if (empty($data)) { Response::error('Nothing to update', 422); exit; }

    $data['updated_at'] = date('Y-m-d H:i:s');
    $db->update('users', $data, ['id' => $id]);

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
        'action' => 'user.removed', 'entity_type' => 'user', 'entity_id' => $targetId,
        'meta' => '{}', 'created_at' => date('Y-m-d H:i:s')
    ]);

    Response::success(['message' => 'User removed']);
}

else {
    Response::error('Method not allowed', 405);
}
