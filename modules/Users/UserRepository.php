<?php
namespace App\Modules\Users;

use App\Core\Database;

/**
 * Data access for tenant users.
 */
class UserRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * All users belonging to a tenant, newest first. Roles are attached as a
     * comma-separated list plus a structured array for convenience.
     */
    public function findAll(int $tenantId): array
    {
        $users = $this->db->fetchAll(
            'SELECT id, tenant_id, email, first_name, last_name, status, is_super_admin, last_login_at, created_at, updated_at
               FROM users
              WHERE tenant_id = :tid
              ORDER BY created_at DESC',
            [':tid' => $tenantId]
        );

        foreach ($users as &$user) {
            $user['roles'] = $this->fetchRoles((int) $user['id']);
        }
        unset($user);

        return $users;
    }

    /**
     * Fetch one user (with roles).
     */
    public function findById(int $id): ?array
    {
        $user = $this->db->fetch('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => $id]);
        if ($user === null) {
            return null;
        }
        unset($user['password_hash']);
        $user['roles'] = $this->fetchRoles($id);
        return $user;
    }

    /**
     * Fetch a user by email within a tenant (includes password_hash for auth).
     */
    public function findByEmail(string $email, int $tenantId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM users WHERE email = :email AND tenant_id = :tid LIMIT 1',
            [':email' => $email, ':tid' => $tenantId]
        );
    }

    /**
     * Insert a user. Returns the new id.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        return $this->db->insert('users', $data);
    }

    /**
     * Update a user by id.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        return $this->db->update('users', $data, ['id' => $id]);
    }

    /**
     * Delete a user and detach its role assignments.
     */
    public function delete(int $id): int
    {
        $this->db->query('DELETE FROM user_roles WHERE user_id = :id', [':id' => $id]);
        return $this->db->delete('users', ['id' => $id]);
    }

    /**
     * @return array<int,array{id:int,name:string,display_name:?string}>
     */
    private function fetchRoles(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT r.id, r.name, r.display_name
               FROM user_roles ur
               JOIN roles r ON r.id = ur.role_id
              WHERE ur.user_id = :uid',
            [':uid' => $userId]
        );
        return array_map(static function (array $r): array {
            return [
                'id'           => (int) $r['id'],
                'name'         => $r['name'],
                'display_name' => $r['display_name'] ?? null,
            ];
        }, $rows);
    }
}
