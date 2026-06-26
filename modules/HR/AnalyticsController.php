<?php
declare(strict_types=1);

class AnalyticsController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('analytics.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();
        $period   = max(7, min(365, (int)$r->get('period', 30)));

        $stats = [
            'total_applications' => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id = ?", [$tenantId]),
            'this_month'         => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id = ? AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())", [$tenantId]),
            'ai_interviews_done' => (int)$db->fetchColumn("SELECT COUNT(*) FROM ai_interviews ai JOIN applications a ON a.id = ai.application_id WHERE a.tenant_id = ? AND ai.status = 'completed'", [$tenantId]),
            'hired'              => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id = ? AND status = 'hired'", [$tenantId]),
            'avg_ai_score'       => (float)($db->fetchColumn("SELECT AVG(ai.overall_score) FROM ai_interviews ai JOIN applications a ON a.id = ai.application_id WHERE a.tenant_id = ? AND ai.status = 'completed'", [$tenantId]) ?: 0),
        ];

        $byDay = $db->fetchAll(
            "SELECT DATE(created_at) AS date, COUNT(*) AS count FROM applications
             WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at) ORDER BY date",
            [$tenantId, $period]
        );

        $byStatus = $db->fetchAll(
            "SELECT status, COUNT(*) AS count FROM applications WHERE tenant_id = ? GROUP BY status",
            [$tenantId]
        );

        $byJob = $db->fetchAll(
            "SELECT j.title, COUNT(a.id) AS apps, COUNT(CASE WHEN a.status='hired' THEN 1 END) AS hired
             FROM applications a JOIN jobs j ON j.id = a.job_id
             WHERE a.tenant_id = ? GROUP BY j.id ORDER BY apps DESC LIMIT 10",
            [$tenantId]
        );

        $hireRate = $stats['total_applications'] > 0
            ? round(($stats['hired'] / $stats['total_applications']) * 100, 1)
            : 0;

        $timeToHire = $db->fetchColumn(
            "SELECT AVG(DATEDIFF(updated_at, created_at)) FROM applications WHERE tenant_id = ? AND status = 'hired'",
            [$tenantId]
        ) ?: 0;

        renderView('hr/analytics', compact('stats', 'byDay', 'byStatus', 'byJob', 'hireRate', 'timeToHire', 'period'), 'app');
    }

    public static function reports(Request $r): void
    {
        Auth::requirePermission('reports.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $savedReports = $db->fetchAll(
            "SELECT * FROM saved_reports WHERE tenant_id = ? ORDER BY created_at DESC",
            [$tenantId]
        );

        renderView('hr/reports', compact('savedReports'), 'app');
    }
}
