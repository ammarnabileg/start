<?php
namespace App\Modules\TalentPool;

use App\Core\Database;

/**
 * Talent pool business logic. Pools are tenant-scoped; their membership lives in
 * talent_pool_candidates. SQL is kept inline via the Database helper.
 */
class TalentPoolService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * List a tenant's pools with a candidate_count.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getPools(int $tenantId): array
    {
        $sql = 'SELECT p.*,
                    (SELECT COUNT(*) FROM talent_pool_candidates tpc WHERE tpc.pool_id = p.id) AS candidate_count
                FROM talent_pools p
                WHERE p.tenant_id = :tenant_id
                ORDER BY p.created_at DESC';
        return $this->db->fetchAll($sql, [':tenant_id' => $tenantId]);
    }

    /**
     * Fetch a single pool (tenant-scoped) with its candidates joined in.
     *
     * @return array<string,mixed>|null
     */
    public function getPool(int $id, int $tenantId): ?array
    {
        $pool = $this->db->fetch(
            'SELECT * FROM talent_pools WHERE id = :id AND tenant_id = :tid LIMIT 1',
            [':id' => $id, ':tid' => $tenantId]
        );
        if ($pool === null) {
            return null;
        }

        $candidates = $this->db->fetchAll(
            'SELECT c.*, tpc.added_at
                FROM talent_pool_candidates tpc
                INNER JOIN candidates c ON c.id = tpc.candidate_id
                WHERE tpc.pool_id = :pid
                ORDER BY tpc.added_at DESC',
            [':pid' => $id]
        );

        $pool['candidates'] = $candidates;
        $pool['candidate_count'] = count($candidates);
        return $pool;
    }

    /**
     * Create a pool.
     *
     * @param array<string,mixed> $data must include tenant_id and name
     * @return array<string,mixed> the created pool row
     */
    public function createPool(array $data): array
    {
        $id = $this->db->insert('talent_pools', [
            'tenant_id'   => (int) $data['tenant_id'],
            'name'        => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'created_by'  => isset($data['created_by']) ? (int) $data['created_by'] : null,
        ]);

        return $this->db->fetch('SELECT * FROM talent_pools WHERE id = :id LIMIT 1', [':id' => $id])
            ?? ['id' => $id, 'tenant_id' => (int) $data['tenant_id'], 'name' => $data['name']];
    }

    /**
     * Update a pool's editable fields (tenant-scoped).
     *
     * @param array<string,mixed> $data
     */
    public function updatePool(int $id, int $tenantId, array $data): ?array
    {
        $pool = $this->db->fetch(
            'SELECT * FROM talent_pools WHERE id = :id AND tenant_id = :tid LIMIT 1',
            [':id' => $id, ':tid' => $tenantId]
        );
        if ($pool === null) {
            return null;
        }

        $update = [];
        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (!empty($update)) {
            $this->db->update('talent_pools', $update, ['id' => $id, 'tenant_id' => $tenantId]);
        }
        return $this->db->fetch('SELECT * FROM talent_pools WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function deletePool(int $id, int $tenantId): bool
    {
        $pool = $this->db->fetch(
            'SELECT id FROM talent_pools WHERE id = :id AND tenant_id = :tid LIMIT 1',
            [':id' => $id, ':tid' => $tenantId]
        );
        if ($pool === null) {
            return false;
        }
        $this->db->delete('talent_pool_candidates', ['pool_id' => $id]);
        $this->db->delete('talent_pools', ['id' => $id, 'tenant_id' => $tenantId]);
        return true;
    }

    /**
     * Add a candidate to a pool (idempotent).
     */
    public function addCandidate(int $poolId, int $candidateId): bool
    {
        $this->db->query(
            'INSERT IGNORE INTO talent_pool_candidates (pool_id, candidate_id) VALUES (:pid, :cid)',
            [':pid' => $poolId, ':cid' => $candidateId]
        );
        return true;
    }

    /**
     * Remove a candidate from a pool.
     */
    public function removeCandidate(int $poolId, int $candidateId): bool
    {
        $this->db->delete('talent_pool_candidates', ['pool_id' => $poolId, 'candidate_id' => $candidateId]);
        return true;
    }

    /**
     * Search candidates within a pool by name, email or CV text (LIKE). Supports
     * multi-term natural-language queries by ANDing the individual terms across
     * the searchable columns — no external AI dependency required.
     *
     * @return array<int,array<string,mixed>>
     */
    public function searchPool(int $poolId, string $query): array
    {
        $query = trim($query);
        $base = 'SELECT c.*, tpc.added_at
                 FROM talent_pool_candidates tpc
                 INNER JOIN candidates c ON c.id = tpc.candidate_id
                 WHERE tpc.pool_id = :pid';
        $params = [':pid' => $poolId];

        if ($query !== '') {
            $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            // Cap the number of terms to keep the query bounded.
            $terms = array_slice($terms, 0, 8);
            $termClauses = [];
            foreach ($terms as $i => $term) {
                $ph = ':t' . $i;
                $termClauses[] = "(c.first_name LIKE {$ph} OR c.last_name LIKE {$ph} "
                    . "OR CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) LIKE {$ph} "
                    . "OR c.email LIKE {$ph} OR c.cv_text LIKE {$ph})";
                $params[$ph] = '%' . $term . '%';
            }
            if ($termClauses !== []) {
                $base .= ' AND (' . implode(' AND ', $termClauses) . ')';
            }
        }

        $base .= ' ORDER BY tpc.added_at DESC';
        return $this->db->fetchAll($base, $params);
    }
}
