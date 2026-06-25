<?php
Auth::requirePermission('roles.view');
$db  = Database::getInstance();
$tid = Auth::tenantId();

// GET /api/v1/roles
if ($method === 'GET' && !$id) {
    $roles = $db->fetchAll(
        "SELECT r.* FROM roles r WHERE r.tenant_id=? OR r.tenant_id IS NULL ORDER BY r.name ASC",
        [$tid]
    );

    foreach ($roles as &$role) {
        $role['permissions'] = $db->fetchAll(
            "SELECT p.slug FROM permissions p
             JOIN role_permissions rp ON rp.permission_id=p.id
             WHERE rp.role_id=?",
            [$role['id']]
        );
        $role['permissions'] = array_column($role['permissions'], 'slug');
    }

    $permissions = $db->fetchAll("SELECT * FROM permissions ORDER BY module ASC, name ASC");
    $modules = [];
    foreach ($permissions as $p) {
        $modules[$p['module']][] = $p;
    }

    Response::success(['roles' => $roles, 'permissions' => $permissions, 'modules' => $modules]);
}

// PUT /api/v1/roles/{id}/permissions
if ($method === 'PUT' && $id && $sub === 'permissions') {
    Auth::requirePermission('roles.manage');
    $permSlugs = $req->input('permissions', []);

    // Convert slugs to IDs
    $permIds = [];
    if (!empty($permSlugs)) {
        $placeholders = implode(',', array_fill(0, count($permSlugs), '?'));
        $permIds = $db->fetchAll("SELECT id FROM permissions WHERE slug IN ($placeholders)", $permSlugs);
        $permIds = array_column($permIds, 'id');
    }

    // Delete old and insert new
    $db->query("DELETE FROM role_permissions WHERE role_id=?", [$id]);
    foreach ($permIds as $pid) {
        $db->insert('role_permissions', ['role_id' => $id, 'permission_id' => $pid]);
    }

    Response::success(null, 'Permissions updated');
}

Response::notFound();
