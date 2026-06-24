<?php
namespace App\Modules\Jobs;

use App\Core\Database;

/**
 * Data access for jobs. All reads are tenant-scoped explicitly; JSON columns
 * (ai_criteria, question_bank) are decoded on the way out.
 */
class JobRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * List a tenant's jobs with optional filters.
     *
     * @param array{status?:string,department?:string,search?:string} $filters
     */
    public function findAll(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :tid'];
        $params = [':tid' => $tenantId];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['department'])) {
            $where[] = 'department = :department';
            $params[':department'] = $filters['department'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(title LIKE :search OR description LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql = 'SELECT * FROM jobs WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
        $rows = $this->db->fetchAll($sql, $params);

        return array_map([$this, 'decodeRow'], $rows);
    }

    /**
     * Fetch a single job scoped to a tenant.
     */
    public function findById(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM jobs WHERE id = :id AND tenant_id = :tid LIMIT 1',
            [':id' => $id, ':tid' => $tenantId]
        );
        return $row === null ? null : $this->decodeRow($row);
    }

    /**
     * Insert a job. Returns the new id. JSON columns may be passed as strings
     * or arrays; arrays are encoded by the Database layer automatically.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        return $this->db->insert('jobs', $data);
    }

    /**
     * Update a job by id.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        return $this->db->update('jobs', $data, ['id' => $id]);
    }

    /**
     * Delete a job by id.
     */
    public function delete(int $id): int
    {
        return $this->db->delete('jobs', ['id' => $id]);
    }

    /**
     * Change a job's status.
     */
    public function updateStatus(int $id, string $status): int
    {
        return $this->db->update('jobs', ['status' => $status], ['id' => $id]);
    }

    /**
     * Published jobs for a tenant (public listing).
     */
    public function findPublished(int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM jobs WHERE tenant_id = :tid AND status = 'published' ORDER BY created_at DESC",
            [':tid' => $tenantId]
        );
        return array_map([$this, 'decodeRow'], $rows);
    }

    /**
     * Decode JSON columns on a job row.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decodeRow(array $row): array
    {
        foreach (['ai_criteria', 'question_bank'] as $col) {
            if (array_key_exists($col, $row)) {
                $row[$col] = $this->decodeJson($row[$col]);
            }
        }
        return $row;
    }

    private function decodeJson($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return $decoded === null && json_last_error() !== JSON_ERROR_NONE ? $value : $decoded;
    }
}
