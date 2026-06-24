<?php
namespace App\Modules\Users;

use App\Core\Database;
use App\Core\RBAC;

/**
 * User management business logic: creation with hashed passwords, updates,
 * deletion and role assignment.
 */
class UserService
{
    private UserRepository $repository;
    private RBAC $rbac;
    private Database $db;

    public function __construct(?UserRepository $repository = null, ?RBAC $rbac = null, ?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
        $this->repository = $repository ?? new UserRepository($this->db);
        $this->rbac = $rbac ?? new RBAC($this->db);
    }

    public function getUsers(int $tenantId): array
    {
        return $this->repository->findAll($tenantId);
    }

    public function getUser(int $id): ?array
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a user in a tenant. Hashes the password, defaults status to
     * active and optionally assigns a role when data['role_id'] is given.
     *
     * @param array<string,mixed> $data
     */
    public function createUser(array $data, int $tenantId): int
    {
        $row = [
            'tenant_id'      => $tenantId,
            'email'          => trim((string) ($data['email'] ?? '')),
            'password_hash'  => password_hash((string) ($data['password'] ?? ''), PASSWORD_BCRYPT),
            'first_name'     => $this->nullable($data['first_name'] ?? null),
            'last_name'      => $this->nullable($data['last_name'] ?? null),
            'status'         => $this->normalizeStatus($data['status'] ?? 'active'),
            'is_super_admin' => 0,
        ];

        // Make sure the connection is scoped so the insert lands in the tenant.
        $this->db->setTenantId($tenantId);
        $userId = $this->repository->create($row);

        if (!empty($data['role_id'])) {
            $this->rbac->assignRole($userId, (int) $data['role_id']);
        }

        return $userId;
    }

    /**
     * Update a user. The password is only re-hashed when a new one is provided.
     *
     * @param array<string,mixed> $data
     */
    public function updateUser(int $id, array $data): int
    {
        $update = [];

        if (array_key_exists('email', $data) && $data['email'] !== null && $data['email'] !== '') {
            $update['email'] = trim((string) $data['email']);
        }
        if (array_key_exists('first_name', $data)) {
            $update['first_name'] = $this->nullable($data['first_name']);
        }
        if (array_key_exists('last_name', $data)) {
            $update['last_name'] = $this->nullable($data['last_name']);
        }
        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $update['status'] = $this->normalizeStatus($data['status']);
        }
        if (!empty($data['password'])) {
            $update['password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        $affected = $this->repository->update($id, $update);

        // Optional role re-assignment.
        if (array_key_exists('role_id', $data) && $data['role_id'] !== null && $data['role_id'] !== '') {
            $this->assignRole($id, (int) $data['role_id']);
        }

        return $affected;
    }

    public function deleteUser(int $id): int
    {
        return $this->repository->delete($id);
    }

    /**
     * Assign a role to a user (idempotent via RBAC).
     */
    public function assignRole(int $userId, int $roleId): void
    {
        $this->rbac->assignRole($userId, $roleId);
    }

    public function getRepository(): UserRepository
    {
        return $this->repository;
    }

    private function nullable($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeStatus($status): string
    {
        $status = is_string($status) ? strtolower($status) : 'active';
        return in_array($status, ['active', 'inactive'], true) ? $status : 'active';
    }
}
