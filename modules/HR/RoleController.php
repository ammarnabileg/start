<?php
declare(strict_types=1);

class RoleController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('roles.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $roles = $db->fetchAll(
            "SELECT r.*, COUNT(ur.user_id) AS user_count
             FROM roles r
             LEFT JOIN user_roles ur ON ur.role_id = r.id
             LEFT JOIN users u ON u.id = ur.user_id AND u.tenant_id = ?
             WHERE r.slug NOT IN ('super_admin')
             GROUP BY r.id
             ORDER BY r.name",
            [$tenantId]
        );

        $allPermissions = $db->fetchAll(
            "SELECT * FROM permissions ORDER BY group_name, name"
        );

        $permsByGroup = [];
        foreach ($allPermissions as $perm) {
            $permsByGroup[$perm['group_name']][] = $perm;
        }

        $rolePermissions = [];
        foreach ($roles as $role) {
            $perms = $db->fetchAll(
                "SELECT permission_id FROM role_permissions WHERE role_id = ?",
                [(int)$role['id']]
            );
            $rolePermissions[$role['id']] = array_column($perms, 'permission_id');
        }

        renderView('hr/roles', compact('roles', 'permsByGroup', 'rolePermissions'), 'app');
    }

    public static function savePermissions(Request $r, int $id): void
    {
        Auth::requirePermission('roles.manage');
        $db = Database::getInstance();

        $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);
        if (!$role) { Response::error('Role not found.', 404); return; }
        if ($role['slug'] === 'super_admin') { Response::error('Cannot modify super_admin permissions.', 403); return; }

        $permIds = (array)$r->post('permissions', []);
        $now     = date('Y-m-d H:i:s');

        $db->query("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
        foreach ($permIds as $permId) {
            $perm = $db->fetch("SELECT id FROM permissions WHERE id = ?", [(int)$permId]);
            if ($perm) {
                $db->insertOrIgnore('role_permissions', [
                    'role_id'       => $id,
                    'permission_id' => (int)$permId,
                    'created_at'    => $now,
                ]);
            }
        }

        Audit::log('role.permissions_updated', 'role', $id, null, ['permission_count' => count($permIds)]);
        Response::success(null, 'Permissions saved.');
    }
}
