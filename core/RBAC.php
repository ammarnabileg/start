<?php
declare(strict_types=1);

class RBAC
{
    public static function getUserRoles(int $userId): array
    {
        $db = Database::getInstance();
        return array_column(
            $db->fetchAll("SELECT r.slug, r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?", [$userId]),
            'slug'
        );
    }

    public static function getUserPermissions(int $userId): array
    {
        $db = Database::getInstance();
        return array_column(
            $db->fetchAll(
                "SELECT DISTINCT p.slug FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?
                 UNION
                 SELECT p.slug FROM permissions p JOIN user_permissions up ON up.permission_id = p.id
                 WHERE up.user_id = ? AND up.granted = 1",
                [$userId, $userId]
            ),
            'slug'
        );
    }

    public static function hasRole(int $userId, string $roleSlug): bool
    {
        return in_array($roleSlug, self::getUserRoles($userId));
    }

    public static function hasPermission(int $userId, string $permSlug): bool
    {
        return in_array($permSlug, self::getUserPermissions($userId));
    }

    public static function assignRole(int $userId, string $roleSlug): bool
    {
        $db   = Database::getInstance();
        $role = $db->fetch("SELECT id FROM roles WHERE slug = ?", [$roleSlug]);
        if (!$role) return false;
        $db->insertOrIgnore('user_roles', ['user_id' => $userId, 'role_id' => $role['id'], 'created_at' => date('Y-m-d H:i:s')]);
        return true;
    }

    public static function removeRole(int $userId, string $roleSlug): void
    {
        $db   = Database::getInstance();
        $role = $db->fetch("SELECT id FROM roles WHERE slug = ?", [$roleSlug]);
        if (!$role) return;
        $db->delete('user_roles', ['user_id' => $userId, 'role_id' => $role['id']]);
    }

    public static function syncRoles(int $userId, array $roleSlugs): void
    {
        $db = Database::getInstance();
        $db->query("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
        foreach ($roleSlugs as $slug) {
            self::assignRole($userId, $slug);
        }
    }

    public static function getRolePermissions(string $roleSlug): array
    {
        $db = Database::getInstance();
        return array_column(
            $db->fetchAll(
                "SELECT p.slug FROM permissions p
                 JOIN role_permissions rp ON rp.permission_id = p.id
                 JOIN roles r ON r.id = rp.role_id WHERE r.slug = ?",
                [$roleSlug]
            ),
            'slug'
        );
    }

    public static function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $db = Database::getInstance();
        $db->query("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
        foreach ($permissionIds as $permId) {
            $db->insertOrIgnore('role_permissions', ['role_id' => $roleId, 'permission_id' => $permId]);
        }
    }
}
