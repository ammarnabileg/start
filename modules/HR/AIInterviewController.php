<?php
declare(strict_types=1);

class AIInterviewController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('ai_interviews.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $status = $r->get('status', '');
        $page   = max(1, (int)$r->get('page', 1));

        $sql = "SELECT ai.*, u.first_name, u.last_name, u.email, j.title AS job_title
                FROM ai_interviews ai
                JOIN applications a ON a.id = ai.application_id
                JOIN users u ON u.id = a.user_id
                JOIN jobs j ON j.id = a.job_id
                WHERE a.tenant_id = ?";
        $params = [$tenantId];

        if ($status) { $sql .= " AND ai.status = ?"; $params[] = $status; }
        $sql .= " ORDER BY ai.created_at DESC";

        $result = $db->paginate($sql, $params, $page, 20);

        renderView('hr/ai-interviews/index', [
            'interviews' => $result['data'],
            'pagination' => $result,
            'status'     => $status,
        ], 'app');
    }

    public static function show(Request $r, int $id): void
    {
        Auth::requirePermission('ai_interviews.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $interview = $db->fetch(
            "SELECT ai.*, u.first_name, u.last_name, u.email, j.title AS job_title,
                    a.id AS application_id, a.status AS app_status
             FROM ai_interviews ai
             JOIN applications a ON a.id = ai.application_id
             JOIN users u ON u.id = a.user_id
             JOIN jobs j ON j.id = a.job_id
             WHERE ai.id = ? AND a.tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$interview) {
            http_response_code(404);
            renderView('errors/404', [], 'app');
            return;
        }

        $messages = $db->fetchAll(
            "SELECT * FROM ai_interview_messages WHERE interview_id = ? ORDER BY created_at ASC",
            [$id]
        );

        $skillScores = $db->fetchAll(
            "SELECT * FROM ai_skill_scores WHERE interview_id = ? ORDER BY score DESC",
            [$id]
        );

        $personality = $db->fetch(
            "SELECT * FROM ai_personality_analyses WHERE interview_id = ?",
            [$id]
        );

        $redFlags = $db->fetchAll(
            "SELECT * FROM ai_red_flags WHERE interview_id = ? ORDER BY severity DESC",
            [$id]
        );

        $recommendation = $db->fetch(
            "SELECT * FROM ai_recommendations WHERE interview_id = ?",
            [$id]
        );

        renderView('hr/ai-interviews/show', compact(
            'interview', 'messages', 'skillScores', 'personality', 'redFlags', 'recommendation'
        ), 'app');
    }
}
