<?php
declare(strict_types=1);

namespace Modules\Interviews;

/**
 * InterviewRepository - Data access for interviews, their messages and
 * evaluations. All SQL lives here; the service layer composes business logic
 * on top of these primitives.
 *
 * Uses the platform's global Database singleton (PDO wrapper). Because that
 * wrapper binds values positionally and does not auto-encode arrays, JSON
 * columns are encoded explicitly here.
 */
class InterviewRepository
{
    /** @var object Database singleton (global \Database). */
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? \Database::getInstance();
    }

    // ==================================================================
    // Interviews
    // ==================================================================

    public function findById(int $id): ?array
    {
        $row = $this->db->fetch('SELECT * FROM interviews WHERE id = ? LIMIT 1', [$id]);
        return $row ?: null;
    }

    public function findByApplicationId(int $applicationId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM interviews WHERE application_id = ? ORDER BY id DESC LIMIT 1',
            [$applicationId]
        );
        return $row ?: null;
    }

    /**
     * Create an interview row and return its id.
     */
    public function create(array $data): int
    {
        $payload = [
            'application_id' => (int) ($data['application_id'] ?? 0),
            'type'           => (string) ($data['type'] ?? 'ai_text'),
            'status'         => (string) ($data['status'] ?? 'pending'),
            'token'          => $data['token'] ?? bin2hex(random_bytes(20)),
        ];
        return $this->db->insert('interviews', $payload);
    }

    public function updateStatus(int $interviewId, string $status): bool
    {
        $data = ['status' => $status];

        if ($status === 'in_progress') {
            $current = $this->findById($interviewId);
            if ($current && empty($current['started_at'])) {
                $data['started_at'] = date('Y-m-d H:i:s');
            }
        }

        if (in_array($status, ['completed', 'abandoned', 'expired'], true)) {
            $data['completed_at'] = date('Y-m-d H:i:s');
            $current = $this->findById($interviewId);
            if ($current && !empty($current['started_at'])) {
                $start = strtotime((string) $current['started_at']);
                if ($start) {
                    $data['duration_seconds'] = max(0, time() - $start);
                }
            }
        }

        $this->db->update('interviews', $data, ['id' => $interviewId]);
        return true;
    }

    /**
     * Persist arbitrary interview fields (e.g. language_detected, session_data,
     * questions_count, heygen_session_id).
     */
    public function update(int $interviewId, array $data): bool
    {
        if (isset($data['session_data']) && is_array($data['session_data'])) {
            $data['session_data'] = $this->encode($data['session_data']);
        }
        $this->db->update('interviews', $data, ['id' => $interviewId]);
        return true;
    }

    public function markStarted(int $interviewId): bool
    {
        return $this->updateStatus($interviewId, 'in_progress');
    }

    // ==================================================================
    // Messages
    // ==================================================================

    /**
     * @return array<int, array> Ordered chronologically.
     */
    public function getMessages(int $interviewId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM interview_messages WHERE interview_id = ? ORDER BY message_index ASC, id ASC',
            [$interviewId]
        );
    }

    public function getMessageCount(int $interviewId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM interview_messages WHERE interview_id = ?',
            [$interviewId]
        );
    }

    /**
     * Save a message. $meta may carry: is_question, is_followup, skill_assessed,
     * audio_url.
     *
     * @param string $role 'ai' or 'candidate'.
     * @return int New message id.
     */
    public function saveMessage(int $interviewId, string $role, string $content, array $meta = []): int
    {
        $role = $role === 'ai' ? 'ai' : 'candidate';

        $nextIndex = (int) $this->db->fetchColumn(
            'SELECT COALESCE(MAX(message_index), -1) + 1 FROM interview_messages WHERE interview_id = ?',
            [$interviewId]
        );

        $id = $this->db->insert('interview_messages', [
            'interview_id'   => $interviewId,
            'role'           => $role,
            'content'        => $content,
            'message_index'  => $nextIndex,
            'is_question'    => !empty($meta['is_question']) ? 1 : 0,
            'is_followup'    => !empty($meta['is_followup']) ? 1 : 0,
            'skill_assessed' => isset($meta['skill_assessed']) && $meta['skill_assessed'] !== ''
                ? (string) $meta['skill_assessed'] : null,
            'audio_url'      => $meta['audio_url'] ?? null,
        ]);

        // Keep a denormalized question counter on the interview for budgeting.
        if ($role === 'ai' && !empty($meta['is_question']) && empty($meta['is_followup'])) {
            $this->db->query(
                'UPDATE interviews SET questions_count = questions_count + 1 WHERE id = ?',
                [$interviewId]
            );
        }

        return $id;
    }

    // ==================================================================
    // Evaluations
    // ==================================================================

    public function getEvaluation(int $interviewId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM interview_evaluations WHERE interview_id = ? ORDER BY id DESC LIMIT 1',
            [$interviewId]
        );
        if (!$row) {
            return null;
        }
        return $this->decodeEvaluation($row);
    }

    /**
     * Insert (or replace) an interview evaluation. Returns the row id.
     */
    public function saveEvaluation(int $interviewId, array $evaluation): int
    {
        $interview = $this->findById($interviewId);
        $applicationId = (int) ($evaluation['application_id'] ?? ($interview['application_id'] ?? 0));

        // Replace any prior evaluation for idempotency.
        $this->db->query('DELETE FROM interview_evaluations WHERE interview_id = ?', [$interviewId]);

        return $this->db->insert('interview_evaluations', [
            'interview_id'         => $interviewId,
            'application_id'       => $applicationId,
            'overall_score'        => (float) ($evaluation['overall_score'] ?? 0),
            'recommendation'       => $this->validRecommendation($evaluation['recommendation'] ?? null),
            'executive_summary'    => (string) ($evaluation['executive_summary'] ?? ''),
            'strengths'            => $this->encode($evaluation['strengths'] ?? []),
            'weaknesses'           => $this->encode($evaluation['weaknesses'] ?? []),
            'skills_analysis'      => $this->encode($evaluation['skills_analysis'] ?? []),
            'personality_analysis' => $this->encode($evaluation['personality_analysis'] ?? []),
            'disc_profile'         => $this->encode($evaluation['personality_analysis']['disc'] ?? []),
            'big_five'             => $this->encode($evaluation['personality_analysis']['big_five'] ?? []),
            'red_flags'            => $this->encode($evaluation['red_flags'] ?? []),
            'cv_analysis'          => $this->encode(['cv_match_notes' => $evaluation['cv_match_notes'] ?? '']),
            'criteria_scores'      => $this->encode($evaluation['criteria_scores'] ?? []),
            'language_proficiency' => $this->encode($evaluation['language_analysis'] ?? []),
            'ai_tokens_used'       => (int) ($evaluation['ai_tokens_used'] ?? 0),
        ]);
    }

    // ==================================================================
    // Listing (HR)
    // ==================================================================

    /**
     * List interviews for a tenant with optional filters and pagination.
     *
     * Filters: status, type, job_id, stage, recommendation, search (candidate
     * name/email), page, per_page.
     *
     * @return array{data:array, total:int, pages:int, page:int, per_page:int}
     */
    public function getForTenant(int $tenantId, array $filters = []): array
    {
        $where  = ['a.tenant_id = ?'];
        $params = [$tenantId];

        if (!empty($filters['status'])) {
            $where[] = 'i.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'i.type = ?';
            $params[] = (string) $filters['type'];
        }
        if (!empty($filters['job_id'])) {
            $where[] = 'a.job_id = ?';
            $params[] = (int) $filters['job_id'];
        }
        if (!empty($filters['stage'])) {
            $where[] = 'a.stage = ?';
            $params[] = (string) $filters['stage'];
        }
        if (!empty($filters['recommendation'])) {
            $where[] = 'ev.recommendation = ?';
            $params[] = (string) $filters['recommendation'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(CONCAT(c.first_name,' ',c.last_name) LIKE ? OR c.email LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $baseSql = "
            SELECT
                i.id, i.type, i.status, i.started_at, i.completed_at,
                i.duration_seconds, i.questions_count, i.language_detected,
                i.created_at,
                a.id AS application_id, a.current_stage AS stage, a.final_score, a.ai_recommendation,
                j.id AS job_id, j.title AS job_title,
                c.id AS candidate_id, CONCAT(c.first_name,' ',c.last_name) AS candidate_name, c.email AS candidate_email,
                ev.overall_score, ev.recommendation
            FROM interviews i
            JOIN applications a ON a.id = i.application_id
            JOIN jobs j ON j.id = a.job_id
            JOIN candidates c ON c.id = a.candidate_id
            LEFT JOIN interview_evaluations ev ON ev.interview_id = i.id
            WHERE {$whereSql}
            ORDER BY i.created_at DESC
        ";

        $page    = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = max(1, min(100, $perPage));

        return $this->db->paginate($baseSql, $params, $page, $perPage);
    }

    /**
     * A fully-joined view of one interview for reporting.
     */
    public function getReportData(int $interviewId, ?int $tenantId = null): ?array
    {
        $params = [$interviewId];
        $tenantClause = '';
        if ($tenantId !== null) {
            $tenantClause = ' AND a.tenant_id = ?';
            $params[] = $tenantId;
        }

        $row = $this->db->fetch("
            SELECT
                i.*,
                a.job_id, a.candidate_id, a.current_stage AS stage, a.cv_match_score, a.cv_analysis,
                a.final_score, a.ai_recommendation, a.hr_decision, a.hr_notes,
                j.title AS job_title, j.experience_level AS seniority, j.description AS job_description,
                j.requirements AS job_requirements,
                CONCAT(c.first_name,' ',c.last_name) AS candidate_name, c.email AS candidate_email,
                c.phone AS candidate_phone, c.years_experience, c.expected_salary,
                c.salary_currency, c.linkedin_url, c.location
            FROM interviews i
            JOIN applications a ON a.id = i.application_id
            JOIN jobs j ON j.id = a.job_id
            JOIN candidates c ON c.id = a.candidate_id
            WHERE i.id = ?{$tenantClause}
            LIMIT 1
        ", $params);

        if (!$row) {
            return null;
        }

        $row['messages']   = $this->getMessages($interviewId);
        $row['evaluation'] = $this->getEvaluation($interviewId);
        return $row;
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function decodeEvaluation(array $row): array
    {
        foreach (['strengths', 'weaknesses', 'skills_analysis', 'personality_analysis',
                  'disc_profile', 'big_five', 'red_flags', 'cv_analysis',
                  'criteria_scores', 'language_proficiency'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                $row[$field] = is_array($decoded) ? $decoded : [];
            }
        }
        return $row;
    }

    private function encode($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        return json_encode($value ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function validRecommendation($value): ?string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($value, ['strong', 'suitable', 'possible', 'not_recommended'], true) ? $value : null;
    }
}
