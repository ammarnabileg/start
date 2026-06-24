<?php
namespace App\Core;

/**
 * Role-Based Access Control. Operates against roles / permissions /
 * role_permissions / user_roles tables.
 */
class RBAC
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** @return string[] permission names */
    public function getUserPermissions(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT p.name
               FROM user_roles ur
               JOIN role_permissions rp ON rp.role_id = ur.role_id
               JOIN permissions p ON p.id = rp.permission_id
              WHERE ur.user_id = :uid',
            [':uid' => $userId]
        );
        return array_column($rows, 'name');
    }

    /** @return array[] role rows */
    public function getUserRoles(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT r.* FROM user_roles ur
               JOIN roles r ON r.id = ur.role_id
              WHERE ur.user_id = :uid',
            [':uid' => $userId]
        );
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $exists = $this->db->fetch(
            'SELECT 1 FROM user_roles WHERE user_id = :u AND role_id = :r',
            [':u' => $userId, ':r' => $roleId]
        );
        if (!$exists) {
            $this->db->query(
                'INSERT INTO user_roles (user_id, role_id) VALUES (:u, :r)',
                [':u' => $userId, ':r' => $roleId]
            );
        }
    }

    public function revokeRole(int $userId, int $roleId): void
    {
        $this->db->query(
            'DELETE FROM user_roles WHERE user_id = :u AND role_id = :r',
            [':u' => $userId, ':r' => $roleId]
        );
    }

    /**
     * Create a role and attach permissions (by permission name).
     * @param string[] $permissions
     */
    public function createRole(?int $tenantId, string $name, array $permissions = [], string $displayName = '', bool $isSystem = false): int
    {
        $roleId = $this->db->insert('roles', [
            'tenant_id'    => $tenantId,
            'name'         => $name,
            'display_name' => $displayName ?: ucfirst($name),
            'is_system'    => $isSystem ? 1 : 0,
        ]);
        $this->syncPermissions($roleId, $permissions);
        return $roleId;
    }

    /** @param string[] $permissions */
    public function syncPermissions(int $roleId, array $permissions): void
    {
        $this->db->query('DELETE FROM role_permissions WHERE role_id = :r', [':r' => $roleId]);
        if (empty($permissions)) {
            return;
        }
        $in = implode(',', array_fill(0, count($permissions), '?'));
        $rows = $this->db->fetchAll('SELECT id FROM permissions WHERE name IN (' . $in . ')', array_values($permissions));
        foreach ($rows as $row) {
            $this->db->query(
                'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:r, :p)',
                [':r' => $roleId, ':p' => $row['id']]
            );
        }
    }

    public function can(int $userId, string $permission): bool
    {
        // Super admins bypass.
        $user = $this->db->fetch('SELECT is_super_admin FROM users WHERE id = :id', [':id' => $userId]);
        if ($user && (int) $user['is_super_admin'] === 1) {
            return true;
        }
        return in_array($permission, $this->getUserPermissions($userId), true);
    }
}
