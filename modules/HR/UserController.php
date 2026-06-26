<?php
declare(strict_types=1);

class UserController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('users.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $users = $db->fetchAll(
            "SELECT u.*, GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS roles
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.tenant_id = ? AND u.is_super_admin = 0
             GROUP BY u.id
             ORDER BY u.created_at DESC",
            [$tenantId]
        );

        $availableRoles = $db->fetchAll(
            "SELECT * FROM roles WHERE slug != 'super_admin' AND slug != 'candidate' ORDER BY name"
        );

        renderView('hr/users', compact('users', 'availableRoles'), 'app');
    }

    public static function create(Request $r): void
    {
        Auth::requirePermission('users.create');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        // Check subscription limit
        $sub = $db->fetch("SELECT max_users FROM tenant_subscriptions WHERE tenant_id = ?", [$tenantId]);
        if ($sub) {
            $currentUsers = (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND is_super_admin = 0", [$tenantId]);
            if ($currentUsers >= (int)$sub['max_users']) {
                Response::error("User limit reached ({$sub['max_users']}). Please upgrade your plan.", 422);
                return;
            }
        }

        $data = $r->only(['first_name', 'last_name', 'email', 'password', 'roles']);
        $v = Validator::make($data, [
            'first_name' => 'required|max:100',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:8',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $now    = date('Y-m-d H:i:s');
        $userId = $db->insert('users', [
            'tenant_id'    => $tenantId,
            'first_name'   => trim($data['first_name']),
            'last_name'    => trim($data['last_name'] ?? ''),
            'email'        => strtolower(trim($data['email'])),
            'password_hash'=> password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'status'       => 'active',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $roles = (array)($data['roles'] ?? []);
        foreach ($roles as $roleId) {
            $db->insertOrIgnore('user_roles', ['user_id' => $userId, 'role_id' => (int)$roleId, 'created_at' => $now]);
        }

        Audit::log('user.created', 'user', $userId, null, ['email' => $data['email']]);
        Response::success(['user_id' => $userId], 'User created.');
    }

    public static function toggle(Request $r, int $id): void
    {
        Auth::requirePermission('users.edit');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        if ($id === Auth::id()) { Response::error('Cannot deactivate your own account.', 422); return; }

        $user = $db->fetch("SELECT id, status FROM users WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$user) { Response::error('Not found', 404); return; }

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $db->update('users', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);

        Response::success(['status' => $newStatus], "User {$newStatus}.");
    }

    public static function syncRoles(Request $r, int $id): void
    {
        Auth::requirePermission('users.edit');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $user = $db->fetch("SELECT id FROM users WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$user) { Response::error('Not found', 404); return; }

        $roles = (array)$r->post('roles', []);
        $db->query("DELETE FROM user_roles WHERE user_id = ?", [$id]);
        $now = date('Y-m-d H:i:s');
        foreach ($roles as $roleId) {
            $db->insertOrIgnore('user_roles', ['user_id' => $id, 'role_id' => (int)$roleId, 'created_at' => $now]);
        }

        Response::success(null, 'Roles updated.');
    }

    public static function delete(Request $r, int $id): void
    {
        Auth::requirePermission('users.delete');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        if ($id === Auth::id()) { Response::error('Cannot delete your own account.', 422); return; }

        $user = $db->fetch("SELECT id FROM users WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$user) { Response::error('Not found', 404); return; }

        $db->update('users', ['status' => 'deleted', 'email' => 'deleted_' . $id . '_' . time() . '@deleted.invalid', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);

        Response::success(null, 'User deleted.');
    }
}
