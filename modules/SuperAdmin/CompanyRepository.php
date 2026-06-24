<?php
namespace App\Modules\SuperAdmin;

use App\Core\Database;

/**
 * Platform-level data access over the tenants table. These queries span all
 * tenants and therefore deliberately do NOT use the tenant-scoped helpers.
 */
class CompanyRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * List companies (tenants) with an aggregated user_count. Supports
     * filtering by status, plan and a free-text search over name/subdomain.
     *
     * @param array{status?:string,plan?:string,search?:string} $filters
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 't.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['plan'])) {
            $where[] = 't.plan = :plan';
            $params[':plan'] = $filters['plan'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(t.name LIKE :search OR t.subdomain LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql = 'SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS user_count
                  FROM tenants t';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.created_at DESC';

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Fetch a single tenant by id, including user_count.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS user_count
               FROM tenants t WHERE t.id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public function findBySubdomain(string $subdomain): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM tenants WHERE subdomain = :s LIMIT 1',
            [':s' => $subdomain]
        );
    }

    /**
     * Insert a new tenant. Returns the new tenant id.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $row = [
            'name'      => $data['name'] ?? '',
            'subdomain' => $data['subdomain'] ?? '',
            'plan'      => $data['plan'] ?? 'free',
            'status'    => $data['status'] ?? 'active',
        ];
        if (array_key_exists('settings', $data)) {
            // Database::insert json-encodes arrays automatically.
            $row['settings'] = $data['settings'];
        }

        // tenant_id must NOT be injected onto the tenants table itself.
        return $this->db->insert('tenants', $row);
    }

    /**
     * Change a tenant's status (active|inactive|suspended).
     */
    public function updateStatus(int $id, string $status): int
    {
        return $this->db->update('tenants', ['status' => $status], ['id' => $id]);
    }

    /**
     * Apply an arbitrary set of column updates to a tenant.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        return $this->db->update('tenants', $data, ['id' => $id]);
    }

    /**
     * Aggregate per-tenant resource counts for a company detail/stat view.
     *
     * @return array{users:int,jobs:int,candidates:int,interviews:int}
     */
    public function getStats(int $id): array
    {
        $users = (int) ($this->db->fetch(
            'SELECT COUNT(*) AS c FROM users WHERE tenant_id = :id',
            [':id' => $id]
        )['c'] ?? 0);

        $jobs = (int) ($this->db->fetch(
            'SELECT COUNT(*) AS c FROM jobs WHERE tenant_id = :id',
            [':id' => $id]
        )['c'] ?? 0);

        $candidates = (int) ($this->db->fetch(
            'SELECT COUNT(*) AS c FROM candidates WHERE tenant_id = :id',
            [':id' => $id]
        )['c'] ?? 0);

        // Interviews are reached via applications -> jobs which carry tenant_id.
        $interviews = (int) ($this->db->fetch(
            'SELECT COUNT(*) AS c
               FROM interviews i
               JOIN applications a ON a.id = i.application_id
               JOIN jobs j ON j.id = a.job_id
              WHERE j.tenant_id = :id',
            [':id' => $id]
        )['c'] ?? 0);

        return [
            'users'      => $users,
            'jobs'       => $jobs,
            'candidates' => $candidates,
            'interviews' => $interviews,
        ];
    }
}
