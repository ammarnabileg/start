<?php
declare(strict_types=1);
/**
 * api/v1/candidates.php — Candidates & Applications REST endpoints
 */

Auth::requireAuth();
$db     = Database::getInstance();
$userId = Auth::user()['id'];
$tid    = Auth::user()['tenant_id'] ?? null;

$action = $request->get('action') ?? $request->input('action') ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── List candidates ────────────────────────────────────────────────────────
if ($method === 'GET' && !$action) {
    Auth::requirePermission('candidates.view');
    $page    = max(1, (int)$request->get('page', 1));
    $search  = trim($request->get('search', ''));
    $jobId   = (int)$request->get('job_id');
    $stage   = $request->get('stage', '');
    $score   = $request->get('score', '');

    $where  = ['a.tenant_id = ?'];
    $params = [$tid];

    if ($search) {
        $where[] = '(c.full_name LIKE ? OR c.email LIKE ?)';
        $params[] = "%$search%"; $params[] = "%$search%";
    }
    if ($jobId) { $where[] = 'a.job_id = ?'; $params[] = $jobId; }
    if ($stage) { $where[] = 'a.current_stage = ?'; $params[] = $stage; }
    if ($score === 'high')   { $where[] = 'ie.overall_score >= 82'; }
    if ($score === 'medium') { $where[] = 'ie.overall_score BETWEEN 50 AND 81'; }
    if ($score === 'low')    { $where[] = 'ie.overall_score < 50'; }

    $sql = "SELECT a.id, a.current_stage, a.applied_at, a.is_archived,
                   c.full_name, c.email, c.phone, c.location,
                   j.title as job_title,
                   ie.overall_score, ie.recommendation,
                   i.status as interview_status
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

// ── Quick view ─────────────────────────────────────────────────────────────
elseif ($action === 'quick_view') {
    Auth::requirePermission('candidates.view');
    $id = (int)$request->get('id');
    $row = $db->fetch(
        "SELECT a.id as application_id, a.current_stage, c.full_name as candidate_name,
                c.email as candidate_email, ie.overall_score, ie.recommendation,
                ie.executive_summary as ai_summary, i.token as interview_token
         FROM applications a
         JOIN candidates c ON c.id = a.candidate_id
         LEFT JOIN interviews i ON i.application_id = a.id
         LEFT JOIN interview_evaluations ie ON ie.interview_id = i.id
         WHERE a.id = ? AND a.tenant_id = ?",
        [$id, $tid]
    );
    if (!$row) { Response::error('Not found', 404); exit; }
    Response::success($row);
}

// ── Move stage ─────────────────────────────────────────────────────────────
elseif ($method === 'POST' && $action === 'move_stage') {
    Auth::requirePermission('pipeline.manage');
    $appId    = (int)$request->input('application_id');
    $newStage = $request->input('stage');

    $valid = ['applied','ai_screening','qualified','disqualified','tech_interview',
              'manager_interview','final_review','offer','hired','rejected','withdrawn'];
    if (!in_array($newStage, $valid)) { Response::error('Invalid stage', 422); exit; }

    $app = $db->fetch("SELECT id, tenant_id, current_stage FROM applications WHERE id = ? AND tenant_id = ?", [$appId, $tid]);
    if (!$app) { Response::error('Not found', 404); exit; }

    $db->update('applications', ['current_stage' => $newStage, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $appId]);

    // Log the stage change
    $db->insert('audit_logs', [
        'tenant_id'   => $tid,
        'user_id'     => $userId,
        'action'      => 'application.stage_changed',
        'entity_type' => 'application',
        'entity_id'   => $appId,
        'meta'        => json_encode(['from' => $app['current_stage'], 'to' => $newStage]),
        'created_at'  => date('Y-m-d H:i:s')
    ]);

    Response::success(['stage' => $newStage]);
}

// ── Bulk action ────────────────────────────────────────────────────────────
elseif ($method === 'POST' && $action === 'bulk_action') {
    Auth::requirePermission('pipeline.manage');
    $ids        = array_map('intval', (array)$request->input('application_ids', []));
    $bulkAction = $request->input('bulk_action');
    if (empty($ids)) { Response::error('No IDs provided', 422); exit; }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge($ids, [$tid]);

    switch ($bulkAction) {
        case 'archive':
            $db->query("UPDATE applications SET is_archived=1, updated_at=NOW() WHERE id IN ($placeholders) AND tenant_id=?", $params);
            break;
        case 'reject':
            $db->query("UPDATE applications SET current_stage='rejected', updated_at=NOW() WHERE id IN ($placeholders) AND tenant_id=?", $params);
            break;
        case 'qualify':
            $db->query("UPDATE applications SET current_stage='qualified', updated_at=NOW() WHERE id IN ($placeholders) AND tenant_id=?", $params);
            break;
        default:
            Response::error('Unknown bulk action', 422); exit;
    }
    Response::success(['updated' => count($ids)]);
}

// ── Export CSV ────────────────────────────────────────────────────────────
elseif ($action === 'export') {
    Auth::requirePermission('candidates.view');
    $jobId = (int)$request->get('job_id');
    $params = [$tid];
    $extra = $jobId ? 'AND a.job_id=?' : '';
    if ($jobId) $params[] = $jobId;
    $rows = $db->fetchAll(
        "SELECT c.full_name, c.email, c.phone, j.title as job, a.current_stage as stage,
                ie.overall_score as score, ie.recommendation, a.applied_at
         FROM applications a
         JOIN candidates c ON c.id=a.candidate_id
         JOIN jobs j ON j.id=a.job_id
         LEFT JOIN interviews i ON i.application_id=a.id
         LEFT JOIN interview_evaluations ie ON ie.interview_id=i.id
         WHERE a.tenant_id=? $extra
         ORDER BY a.applied_at DESC
         LIMIT 1000",
        $params
    );
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="candidates-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name','Email','Phone','Job','Stage','Score','Recommendation','Applied']);
    foreach ($rows as $r) fputcsv($out, array_values($r));
    fclose($out);
    exit;
}

else {
    Response::error('Unknown action', 400);
}
