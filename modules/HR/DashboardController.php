<?php
declare(strict_types=1);

class DashboardController
{
    public static function index(Request $r): void
    {
        Auth::requireAuth();
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $stats = [
            'active_jobs'        => (int)$db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE tenant_id = ? AND status = 'active'", [$tenantId]),
            'total_applications' => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id = ?", [$tenantId]),
            'ai_interviews'      => (int)$db->fetchColumn("SELECT COUNT(*) FROM ai_interviews ai JOIN applications a ON a.id = ai.application_id WHERE a.tenant_id = ?", [$tenantId]),
            'hired_this_month'   => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id = ? AND status = 'hired' AND MONTH(updated_at) = MONTH(NOW()) AND YEAR(updated_at) = YEAR(NOW())", [$tenantId]),
        ];

        $recentApplications = $db->fetchAll(
            "SELECT a.*, j.title AS job_title, u.first_name, u.last_name, u.email
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN users u ON u.id = a.user_id
             WHERE a.tenant_id = ?
             ORDER BY a.created_at DESC LIMIT 10",
            [$tenantId]
        );

        $byStatus = $db->fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM applications WHERE tenant_id = ? GROUP BY status",
            [$tenantId]
        );

        $recentInterviews = $db->fetchAll(
            "SELECT ai.*, u.first_name, u.last_name, j.title AS job_title
             FROM ai_interviews ai
             JOIN applications a ON a.id = ai.application_id
             JOIN users u ON u.id = a.user_id
             JOIN jobs j ON j.id = a.job_id
             WHERE a.tenant_id = ? AND ai.status = 'completed'
             ORDER BY ai.completed_at DESC LIMIT 5",
            [$tenantId]
        );

        $hasOpenAI = Tenant::hasOpenAI();

        renderView('hr/dashboard', compact('stats', 'recentApplications', 'byStatus', 'recentInterviews', 'hasOpenAI'), 'app');
    }
}
