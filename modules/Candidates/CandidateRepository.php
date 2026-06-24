<?php
namespace App\Modules\Candidates;

use App\Core\Database;

/**
 * Data access for candidates. All reads are scoped to a tenant id passed in by
 * the caller so the repository can be used in both authenticated (HR) and
 * tenant-resolved public contexts.
 */
class CandidateRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * List candidates for a tenant.
     *
     * Supported filters:
     *   - search: matches first_name, last_name or email (LIKE)
     *   - status: exact candidate status
     *   - job_id: restrict to candidates that applied to this job and surface
     *             that application's ai_match_score + pipeline_stage
     *
     * When no job_id filter is given, the latest application's match score and
     * pipeline stage are exposed via correlated subqueries.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAll(int $tenantId, array $filters = []): array
    {
        $params = [':tenant_id' => $tenantId];
        $where = ['c.tenant_id = :tenant_id'];

        if (!empty($filters['search'])) {
            $where[] = '(c.first_name LIKE :search OR c.last_name LIKE :search '
                . "OR CONCAT(c.first_name, ' ', c.last_name) LIKE :search OR c.email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['job_id'])) {
            // Scope to candidates who applied to the given job and pull that
            // application's scoring directly via JOIN.
            $params[':job_id'] = (int) $filters['job_id'];
            $sql = 'SELECT c.*, a.id AS application_id, a.ai_match_score, a.pipeline_stage, a.status AS application_status
                    FROM candidates c
                    INNER JOIN applications a ON a.candidate_id = c.id AND a.job_id = :job_id
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY a.ai_match_score IS NULL, a.ai_match_score DESC, c.created_at DESC';
            return $this->db->fetchAll($sql, $params);
        }

        // No job filter: surface the most recent application's score/stage.
        $sql = 'SELECT c.*,
                    (SELECT a.ai_match_score FROM applications a WHERE a.candidate_id = c.id ORDER BY a.applied_at DESC LIMIT 1) AS ai_match_score,
                    (SELECT a.pipeline_stage FROM applications a WHERE a.candidate_id = c.id ORDER BY a.applied_at DESC LIMIT 1) AS pipeline_stage,
                    (SELECT COUNT(*) FROM applications a WHERE a.candidate_id = c.id) AS application_count
                FROM candidates c
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.created_at DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM candidates WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function findByEmail(string $email, int $tenantId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM candidates WHERE email = :email AND tenant_id = :tenant_id ORDER BY id ASC LIMIT 1',
            [':email' => $email, ':tenant_id' => $tenantId]
        );
    }

    /**
     * Insert a candidate. tenant_id must be present in $data (public flows pass
     * the job's tenant explicitly).
     */
    public function create(array $data): int
    {
        return $this->db->insert('candidates', $data);
    }

    public function update(int $id, array $data): int
    {
        return $this->db->update('candidates', $data, ['id' => $id]);
    }

    /**
     * All applications for a candidate, with job title joined in.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getApplications(int $candidateId): array
    {
        $sql = 'SELECT a.*, j.title AS job_title, j.department AS job_department, j.location AS job_location
                FROM applications a
                INNER JOIN jobs j ON j.id = a.job_id
                WHERE a.candidate_id = :cid
                ORDER BY a.applied_at DESC';
        return $this->db->fetchAll($sql, [':cid' => $candidateId]);
    }

    /**
     * All interviews for a candidate, reached through their applications.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getInterviews(int $candidateId): array
    {
        $sql = 'SELECT i.*, a.job_id, j.title AS job_title,
                    e.overall_score, e.recommendation
                FROM interviews i
                INNER JOIN applications a ON a.id = i.application_id
                INNER JOIN jobs j ON j.id = a.job_id
                LEFT JOIN interview_evaluations e ON e.interview_id = i.id
                WHERE a.candidate_id = :cid
                ORDER BY i.created_at DESC';
        return $this->db->fetchAll($sql, [':cid' => $candidateId]);
    }
}
