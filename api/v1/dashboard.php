<?php
Auth::requireHR();
$db  = Database::getInstance();
$tid = Auth::tenantId();

if ($method === 'GET' && $sub === 'stats') {
    $stats = [
        'total_jobs'           => (int)$db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE tenant_id=? AND status != 'closed'", [$tid]),
        'active_jobs'          => (int)$db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE tenant_id=? AND status='active'", [$tid]),
        'total_applications'   => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id=?", [$tid]),
        'pending_ai_screening' => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id=? AND current_stage='ai_screening'", [$tid]),
        'qualified'            => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id=? AND current_stage='qualified'", [$tid]),
        'scheduled_interviews' => (int)$db->fetchColumn("SELECT COUNT(*) FROM human_interviews WHERE tenant_id=? AND status='scheduled'", [$tid]),
        'pending_offers'       => (int)$db->fetchColumn("SELECT COUNT(*) FROM offers WHERE tenant_id=? AND status='sent'", [$tid]),
    ];

    $recent = $db->fetchAll(
        "SELECT a.id, a.current_stage, a.ai_score, a.applied_at,
                CONCAT(u.first_name,' ',u.last_name) as candidate_name,
                j.title as job_title
         FROM applications a
         JOIN users u ON u.id = a.candidate_id
         JOIN jobs j ON j.id = a.job_id
         WHERE a.tenant_id=?
         ORDER BY a.applied_at DESC LIMIT 10",
        [$tid]
    );

    Response::success(['stats' => $stats, 'recent_applications' => $recent]);
}

Response::notFound();
