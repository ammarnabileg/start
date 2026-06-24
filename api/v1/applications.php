<?php
declare(strict_types=1);
/**
 * api/v1/applications.php — Applications REST endpoints
 */

Auth::requireAuth();
$db     = Database::getInstance();
$userId = Auth::user()['id'];
$tid    = Auth::user()['tenant_id'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$action = $request->get('action') ?? $request->input('action') ?? '';

// ── List applications ──────────────────────────────────────────────────────
if ($method === 'GET' && !$action) {
    Auth::requirePermission('applications.view');
    $page  = max(1, (int)$request->get('page', 1));
    $jobId = (int)$request->get('job_id');
    $stage = $request->get('stage', '');

    $where  = ['a.tenant_id = ?'];
    $params = [$tid];
    if ($jobId) { $where[] = 'a.job_id = ?'; $params[] = $jobId; }
    if ($stage) { $where[] = 'a.current_stage = ?'; $params[] = $stage; }

    $sql = "SELECT a.id, a.job_id, a.current_stage, a.applied_at,
                   c.full_name, c.email,
                   j.title as job_title,
                   ie.overall_score, ie.recommendation
            FROM applications a
            JOIN candidates c ON c.id = a.candidate_id
            JOIN jobs j ON j.id = a.job_id
            LEFT JOIN interviews i ON i.application_id = a.id
            LEFT JOIN interview_evaluations ie ON ie.interview_id = i.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.applied_at DESC";

    $result = $db->paginate($sql, $params, $page, 25);
    Response::paginated($result['data'], $result['total'], $page, 25);
}

// ── Get pipeline board data ────────────────────────────────────────────────
elseif ($action === 'pipeline') {
    Auth::requirePermission('applications.view');
    $jobId = (int)$request->get('job_id');
    if (!$jobId) { Response::error('job_id required', 422); exit; }

    $stages = ['applied','ai_screening','qualified','tech_interview','manager_interview','final_review','offer','hired'];
    $pipeline = [];

    foreach ($stages as $stage) {
        $rows = $db->fetchAll(
            "SELECT a.id, a.current_stage, a.applied_at,
                    c.full_name, c.email, c.location,
                    ie.overall_score, ie.recommendation,
                    i.token as interview_token, i.status as interview_status
             FROM applications a
             JOIN candidates c ON c.id = a.candidate_id
             LEFT JOIN interviews i ON i.application_id = a.id
             LEFT JOIN interview_evaluations ie ON ie.interview_id = i.id
             WHERE a.tenant_id = ? AND a.job_id = ? AND a.current_stage = ? AND a.is_archived = 0
             ORDER BY ie.overall_score DESC, a.applied_at ASC
             LIMIT 50",
            [$tid, $jobId, $stage]
        );
        $pipeline[$stage] = $rows;
    }
    Response::success($pipeline);
}

// ── Move stage (also handled in candidates.php, this is for applications endpoint) ───
elseif ($method === 'POST' && $action === 'move_stage') {
    Auth::requirePermission('pipeline.manage');
    $appId    = (int)$request->input('application_id');
    $newStage = $request->input('stage');

    $valid = ['applied','ai_screening','qualified','disqualified','tech_interview',
              'manager_interview','final_review','offer','hired','rejected','withdrawn'];
    if (!in_array($newStage, $valid, true)) { Response::error('Invalid stage', 422); exit; }

    $app = $db->fetch("SELECT id, current_stage FROM applications WHERE id = ? AND tenant_id = ?", [$appId, $tid]);
    if (!$app) { Response::error('Not found', 404); exit; }

    $db->update('applications', ['current_stage' => $newStage, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $appId]);

    $db->insert('audit_logs', [
        'tenant_id' => $tid, 'user_id' => $userId,
        'action' => 'application.stage_changed', 'entity_type' => 'application', 'entity_id' => $appId,
        'meta' => json_encode(['from' => $app['current_stage'], 'to' => $newStage]),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    Response::success(['stage' => $newStage]);
}

// ── Get application detail ─────────────────────────────────────────────────
elseif ($method === 'GET' && $action === 'detail') {
    Auth::requirePermission('applications.view');
    $id = (int)$request->get('id');
    $row = $db->fetch(
        "SELECT a.*, c.full_name, c.email, c.phone, c.location,
                j.title as job_title, j.department,
                ie.overall_score, ie.recommendation, ie.ai_summary,
                ie.skills_scores, ie.disc_profile, ie.big_five, ie.red_flags,
                ie.transcript
         FROM applications a
         JOIN candidates c ON c.id = a.candidate_id
         JOIN jobs j ON j.id = a.job_id
         LEFT JOIN interviews i ON i.application_id = a.id
         LEFT JOIN interview_evaluations ie ON ie.interview_id = i.id
         WHERE a.id = ? AND a.tenant_id = ?",
        [$id, $tid]
    );
    if (!$row) { Response::error('Not found', 404); exit; }
    // Decode JSON fields
    foreach (['skills_scores','disc_profile','big_five','red_flags'] as $f) {
        if (isset($row[$f])) $row[$f] = json_decode($row[$f], true);
    }
    Response::success($row);
}

// ── Bulk actions ──────────────────────────────────────────────────────────
elseif ($method === 'POST' && $action === 'bulk_action') {
    Auth::requirePermission('pipeline.manage');
    $ids  = array_map('intval', (array)$request->input('application_ids', []));
    $bulk = $request->input('bulk_action');
    if (empty($ids)) { Response::error('No IDs', 422); exit; }

    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge($ids, [$tid]);

    match ($bulk) {
        'archive' => $db->query("UPDATE applications SET is_archived=1, updated_at=NOW() WHERE id IN ($ph) AND tenant_id=?", $params),
        'reject'  => $db->query("UPDATE applications SET current_stage='rejected', updated_at=NOW() WHERE id IN ($ph) AND tenant_id=?", $params),
        'qualify' => $db->query("UPDATE applications SET current_stage='qualified', updated_at=NOW() WHERE id IN ($ph) AND tenant_id=?", $params),
        default   => (function() { Response::error('Unknown action', 422); exit; })()
    };

    Response::success(['updated' => count($ids)]);
}

// ── Add note ──────────────────────────────────────────────────────────────
elseif ($method === 'POST' && $action === 'add_note') {
    Auth::requirePermission('candidates.view');
    $appId = (int)$request->input('application_id');
    $note  = trim($request->input('note', ''));
    if (!$note) { Response::error('Note cannot be empty', 422); exit; }

    $app = $db->fetch("SELECT id FROM applications WHERE id = ? AND tenant_id = ?", [$appId, $tid]);
    if (!$app) { Response::error('Not found', 404); exit; }

    $db->insert('audit_logs', [
        'tenant_id' => $tid, 'user_id' => $userId,
        'action' => 'application.note_added', 'entity_type' => 'application', 'entity_id' => $appId,
        'meta' => json_encode(['note' => $note]),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    Response::success(['message' => 'Note saved']);
}

else {
    Response::error('Unknown action', 400);
}
