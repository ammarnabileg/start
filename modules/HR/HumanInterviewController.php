<?php
declare(strict_types=1);

class HumanInterviewController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('interviews.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $interviews = $db->fetchAll(
            "SELECT hi.*, a.status AS app_status,
                    u.first_name, u.last_name, u.email,
                    j.title AS job_title,
                    iv.first_name AS interviewer_fname, iv.last_name AS interviewer_lname
             FROM human_interviews hi
             JOIN applications a ON a.id = hi.application_id
             JOIN users u ON u.id = a.user_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN users iv ON iv.id = hi.interviewer_id
             WHERE hi.tenant_id = ?
             ORDER BY hi.scheduled_at DESC",
            [$tenantId]
        );

        $teamMembers = $db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name FROM users u
             WHERE u.tenant_id = ? AND u.status = 'active' AND u.is_super_admin = 0
             ORDER BY u.first_name",
            [$tenantId]
        );

        renderView('hr/human-interviews', compact('interviews', 'teamMembers'), 'app');
    }

    public static function create(Request $r): void
    {
        Auth::requirePermission('interviews.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $data = $r->only(['application_id', 'interviewer_id', 'type', 'scheduled_at', 'location', 'notes', 'duration_minutes']);
        $v = Validator::make($data, [
            'application_id' => 'required|integer',
            'interviewer_id' => 'required|integer',
            'type'           => 'required|in:in_person,video,phone',
            'scheduled_at'   => 'required|date',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $app = $db->fetch("SELECT id FROM applications WHERE id = ? AND tenant_id = ?", [(int)$data['application_id'], $tenantId]);
        if (!$app) { Response::error('Application not found.', 404); return; }

        $now = date('Y-m-d H:i:s');
        $id  = $db->insert('human_interviews', [
            'application_id'   => (int)$data['application_id'],
            'tenant_id'        => $tenantId,
            'interviewer_id'   => (int)$data['interviewer_id'],
            'type'             => $data['type'],
            'scheduled_at'     => $data['scheduled_at'],
            'location'         => $data['location'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'duration_minutes' => isset($data['duration_minutes']) ? (int)$data['duration_minutes'] : 60,
            'status'           => 'scheduled',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        $db->update('applications', ['status' => 'human_interview', 'updated_at' => $now], ['id' => (int)$data['application_id']]);

        Response::success(['id' => $id], 'Interview scheduled.');
    }

    public static function evaluate(Request $r, int $id): void
    {
        Auth::requirePermission('interviews.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $interview = $db->fetch(
            "SELECT hi.* FROM human_interviews hi WHERE hi.id = ? AND hi.tenant_id = ?",
            [$id, $tenantId]
        );
        if (!$interview) { Response::error('Not found', 404); return; }

        $data = $r->only(['overall_score', 'technical_score', 'communication_score', 'culture_score', 'recommendation', 'feedback', 'decision']);
        $v = Validator::make($data, [
            'overall_score' => 'required|numeric',
            'recommendation' => 'required|in:strong_yes,yes,maybe,no,strong_no',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $now = date('Y-m-d H:i:s');
        $db->update('human_interviews', [
            'overall_score'       => (float)$data['overall_score'],
            'technical_score'     => isset($data['technical_score']) ? (float)$data['technical_score'] : null,
            'communication_score' => isset($data['communication_score']) ? (float)$data['communication_score'] : null,
            'culture_fit_score'   => isset($data['culture_score']) ? (float)$data['culture_score'] : null,
            'recommendation'      => $data['recommendation'],
            'feedback_notes'      => $data['feedback'] ?? null,
            'status'              => 'completed',
            'completed_at'        => $now,
            'updated_at'          => $now,
        ], ['id' => $id]);

        Response::success(null, 'Evaluation saved.');
    }
}
