<?php
namespace App\Modules\Interviews;

use App\Core\Database;

/**
 * Data access for interviews, their transcript messages and AI evaluations.
 * Interviews carry no tenant_id of their own, so tenant scoping is achieved by
 * joining through applications -> jobs.
 */
class InterviewRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * List interviews for a tenant with candidate name, job title and the
     * evaluation's overall score joined in.
     *
     * Supported filters: type, status, date (YYYY-MM-DD, matches created_at day).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAll(int $tenantId, array $filters = []): array
    {
        $params = [':tenant_id' => $tenantId];
        $where = ['j.tenant_id = :tenant_id'];

        if (!empty($filters['type'])) {
            $where[] = 'i.type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'i.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['date'])) {
            $where[] = 'DATE(i.created_at) = :date';
            $params[':date'] = $filters['date'];
        }

        $sql = 'SELECT i.*, a.candidate_id, a.job_id, a.pipeline_stage,
                    j.title AS job_title,
                    c.first_name, c.last_name, c.email AS candidate_email,
                    CONCAT(COALESCE(c.first_name, \'\'), \' \', COALESCE(c.last_name, \'\')) AS candidate_name,
                    e.overall_score, e.recommendation
                FROM interviews i
                INNER JOIN applications a ON a.id = i.application_id
                INNER JOIN jobs j ON j.id = a.job_id
                INNER JOIN candidates c ON c.id = a.candidate_id
                LEFT JOIN interview_evaluations e ON e.interview_id = i.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY i.created_at DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM interviews WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function findByToken(string $token): ?array
    {
        return $this->db->fetch('SELECT * FROM interviews WHERE token = :token LIMIT 1', [':token' => $token]);
    }

    public function create(array $data): int
    {
        return $this->db->insert('interviews', $data);
    }

    public function update(int $id, array $data): int
    {
        return $this->db->update('interviews', $data, ['id' => $id]);
    }

    /**
     * Append a transcript message.
     */
    public function addMessage(int $interviewId, string $role, string $content): int
    {
        return $this->db->insert('interview_messages', [
            'interview_id' => $interviewId,
            'role'         => $role,
            'content'      => $content,
        ]);
    }

    /**
     * Transcript ordered oldest-first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getMessages(int $interviewId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM interview_messages WHERE interview_id = :id ORDER BY timestamp ASC, id ASC',
            [':id' => $interviewId]
        );
    }

    /**
     * Persist a full evaluation: the evaluation row plus its skill scores,
     * personality analysis and red flags, all in one transaction.
     *
     * @param array<string,mixed> $evaluation as returned by InterviewEvaluator
     * @return int the new evaluation id
     */
    public function saveEvaluation(int $interviewId, array $evaluation): int
    {
        $this->db->beginTransaction();
        try {
            // Replace any prior evaluation for this interview to keep results idempotent.
            $existing = $this->db->fetchAll(
                'SELECT id FROM interview_evaluations WHERE interview_id = :id',
                [':id' => $interviewId]
            );
            foreach ($existing as $row) {
                $eid = (int) $row['id'];
                $this->db->delete('skill_scores', ['evaluation_id' => $eid]);
                $this->db->delete('personality_analysis', ['evaluation_id' => $eid]);
                $this->db->delete('red_flags', ['evaluation_id' => $eid]);
                $this->db->delete('interview_evaluations', ['id' => $eid]);
            }

            $recommendation = $this->normaliseRecommendation(
                $evaluation['recommendation'] ?? ($evaluation['hiring_recommendation'] ?? null)
            );

            $evaluationId = $this->db->insert('interview_evaluations', [
                'interview_id'  => $interviewId,
                'overall_score' => isset($evaluation['overall_score']) ? (float) $evaluation['overall_score'] : null,
                'recommendation' => $recommendation,
                'summary'       => $this->buildSummary($evaluation),
            ]);

            // Skill scores.
            foreach (($evaluation['skill_scores'] ?? []) as $skill) {
                if (!is_array($skill)) {
                    continue;
                }
                $name = (string) ($skill['skill'] ?? $skill['skill_name'] ?? $skill['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $this->db->insert('skill_scores', [
                    'evaluation_id' => $evaluationId,
                    'skill_name'    => $name,
                    'score'         => (float) ($skill['score'] ?? 0),
                    'notes'         => $skill['notes'] ?? null,
                ]);
            }

            // Personality analysis (DISC + Big Five).
            $personality = $evaluation['personality'] ?? [];
            if (is_array($personality) && $personality !== []) {
                $disc = $personality['disc'] ?? [];
                $big5 = $personality['big5'] ?? ($personality['big_five'] ?? []);
                $this->db->insert('personality_analysis', [
                    'evaluation_id'          => $evaluationId,
                    'disc_d'                 => $this->num($disc['D'] ?? $disc['d'] ?? null),
                    'disc_i'                 => $this->num($disc['I'] ?? $disc['i'] ?? null),
                    'disc_s'                 => $this->num($disc['S'] ?? $disc['s'] ?? null),
                    'disc_c'                 => $this->num($disc['C'] ?? $disc['c'] ?? null),
                    'big5_openness'          => $this->num($big5['openness'] ?? null),
                    'big5_conscientiousness' => $this->num($big5['conscientiousness'] ?? null),
                    'big5_extraversion'      => $this->num($big5['extraversion'] ?? null),
                    'big5_agreeableness'     => $this->num($big5['agreeableness'] ?? null),
                    'big5_neuroticism'       => $this->num($big5['neuroticism'] ?? null),
                    'analysis_notes'         => $personality['notes'] ?? ($personality['analysis_notes'] ?? null),
                ]);
            }

            // Red flags.
            foreach (($evaluation['red_flags'] ?? []) as $flag) {
                if (!is_array($flag)) {
                    continue;
                }
                $type = (string) ($flag['type'] ?? $flag['flag_type'] ?? '');
                if ($type === '') {
                    continue;
                }
                $this->db->insert('red_flags', [
                    'evaluation_id' => $evaluationId,
                    'flag_type'     => $type,
                    'description'   => $flag['description'] ?? null,
                    'severity'      => $this->normaliseSeverity($flag['severity'] ?? 'low'),
                ]);
            }

            $this->db->commit();
            return $evaluationId;
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Build a readable summary string from the evaluation payload.
     *
     * @param array<string,mixed> $evaluation
     */
    private function buildSummary(array $evaluation): ?string
    {
        $parts = [];
        if (!empty($evaluation['summary'])) {
            $parts[] = (string) $evaluation['summary'];
        }
        if (!empty($evaluation['hiring_recommendation_reason'])) {
            $parts[] = 'Recommendation reason: ' . (string) $evaluation['hiring_recommendation_reason'];
        }
        if (!empty($evaluation['strengths'])) {
            $parts[] = 'Strengths: ' . $this->stringifyList($evaluation['strengths']);
        }
        if (!empty($evaluation['areas_for_improvement'])) {
            $parts[] = 'Areas for improvement: ' . $this->stringifyList($evaluation['areas_for_improvement']);
        }
        $summary = trim(implode("\n\n", $parts));
        return $summary === '' ? null : $summary;
    }

    private function stringifyList($value): string
    {
        if (is_array($value)) {
            return implode('; ', array_map(static fn($v) => is_scalar($v) ? (string) $v : json_encode($v), $value));
        }
        return (string) $value;
    }

    private function normaliseRecommendation($value): ?string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['hire', 'maybe', 'reject'], true) ? $value : null;
    }

    private function normaliseSeverity($value): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['low', 'medium', 'high'], true) ? $value : 'low';
    }

    private function num($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }
}
