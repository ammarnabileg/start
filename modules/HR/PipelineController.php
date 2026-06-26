<?php
declare(strict_types=1);

class PipelineController
{
    private static array $stages = [
        'applied', 'screening', 'ai_interview', 'technical_test', 'human_interview',
        'shortlisted', 'reference_check', 'offer_extended', 'offer_accepted',
        'offer_declined', 'hired', 'rejected',
    ];

    public static function index(Request $r): void
    {
        Auth::requirePermission('pipeline.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $jobId = (int)$r->get('job_id', 0);

        $sql = "SELECT a.id, a.status, a.expected_salary, a.created_at,
                       u.first_name, u.last_name, u.email,
                       j.title AS job_title, j.id AS job_id,
                       ai.overall_score AS ai_score
                FROM applications a
                JOIN users u ON u.id = a.user_id
                JOIN jobs j ON j.id = a.job_id
                LEFT JOIN ai_interviews ai ON ai.application_id = a.id AND ai.status = 'completed'
                WHERE a.tenant_id = ?";
        $params = [$tenantId];

        if ($jobId) { $sql .= " AND a.job_id = ?"; $params[] = $jobId; }
        $sql .= " ORDER BY a.updated_at DESC";

        $applications = $db->fetchAll($sql, $params);

        // Group by stage
        $kanban = [];
        foreach (self::$stages as $stage) {
            $kanban[$stage] = [];
        }
        foreach ($applications as $app) {
            $stage = $app['status'];
            if (!isset($kanban[$stage])) $kanban[$stage] = [];
            $kanban[$stage][] = $app;
        }

        $jobs = $db->fetchAll("SELECT id, title FROM jobs WHERE tenant_id = ? AND status = 'active' ORDER BY title", [$tenantId]);

        renderView('hr/pipeline', compact('kanban', 'stages', 'jobs', 'jobId'), 'app');
    }

    public static function move(Request $r): void
    {
        Auth::requirePermission('pipeline.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $appId     = (int)$r->post('application_id', 0);
        $newStatus = (string)$r->post('status', '');

        if (!in_array($newStatus, self::$stages, true)) {
            Response::error('Invalid status.', 422);
            return;
        }

        $app = $db->fetch("SELECT id, status FROM applications WHERE id = ? AND tenant_id = ?", [$appId, $tenantId]);
        if (!$app) { Response::error('Not found', 404); return; }

        $now = date('Y-m-d H:i:s');
        $db->update('applications', ['status' => $newStatus, 'updated_at' => $now], ['id' => $appId]);
        $db->insert('application_stage_history', [
            'application_id' => $appId,
            'from_status'    => $app['status'],
            'to_status'      => $newStatus,
            'changed_by'     => Auth::id(),
        ]);

        Audit::log('application.moved', 'application', $appId, ['status' => $app['status']], ['status' => $newStatus]);
        Response::success(['status' => $newStatus], 'Moved successfully.');
    }

    public static function bulkMove(Request $r): void
    {
        Auth::requirePermission('pipeline.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $ids       = (array)$r->post('ids', []);
        $newStatus = (string)$r->post('status', '');

        if (!in_array($newStatus, self::$stages, true)) {
            Response::error('Invalid status.', 422);
            return;
        }

        $now   = date('Y-m-d H:i:s');
        $moved = 0;

        foreach ($ids as $id) {
            $id  = (int)$id;
            $app = $db->fetch("SELECT id, status FROM applications WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$app) continue;

            $db->update('applications', ['status' => $newStatus, 'updated_at' => $now], ['id' => $id]);
            $db->insert('application_stage_history', [
                'application_id' => $id,
                'from_status'    => $app['status'],
                'to_status'      => $newStatus,
                'changed_by'     => Auth::id(),
            ]);
            $moved++;
        }

        Response::success(['moved' => $moved], "$moved applications moved.");
    }
}
