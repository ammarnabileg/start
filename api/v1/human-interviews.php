<?php
Auth::requireHR();
$db  = Database::getInstance();
$tid = Auth::tenantId();

// GET /api/v1/human-interviews
if ($method === 'GET' && !$id) {
    $page   = max(1,(int)$req->get('page',1));
    $status = $req->get('status','');
    $from   = $req->get('from','');
    $to     = $req->get('to','');

    $where  = "hi.tenant_id=?";
    $params = [$tid];
    if ($status) { $where .= " AND hi.status=?"; $params[] = $status; }
    if ($from)   { $where .= " AND hi.interview_date >= ?"; $params[] = $from.' 00:00:00'; }
    if ($to)     { $where .= " AND hi.interview_date <= ?"; $params[] = $to.' 23:59:59'; }

    $result = $db->paginate(
        "SELECT hi.*, CONCAT(u.first_name,' ',u.last_name) as candidate_name,
                j.title as job_title
         FROM human_interviews hi
         JOIN applications a ON a.id=hi.application_id
         JOIN users u ON u.id=a.candidate_id
         JOIN jobs j ON j.id=a.job_id
         WHERE {$where}
         ORDER BY hi.interview_date ASC",
        $params, $page, 20
    );

    foreach ($result['data'] as &$row) {
        $row['evaluators'] = $db->fetchAll(
            "SELECT hie.*, CONCAT(u.first_name,' ',u.last_name) as name
             FROM human_interview_evaluators hie
             JOIN users u ON u.id=hie.user_id
             WHERE hie.interview_id=?",
            [$row['id']]
        );
    }

    Response::paginated($result['data'], $result['total'], $result['page'], $result['per_page']);
}

// POST /api/v1/human-interviews
if ($method === 'POST' && !$id) {
    $appId          = (int)$req->input('application_id');
    $type           = $req->input('interview_type','video');
    $date           = $req->input('interview_date');
    $duration       = (int)$req->input('duration_minutes', 60);
    $notes          = $req->input('notes','');
    $interviewerIds = $req->input('interviewer_ids', []);

    if (!$appId || !$date) Response::error('application_id and interview_date required');

    // Verify app belongs to tenant
    $app = $db->fetch("SELECT id FROM applications WHERE id=? AND tenant_id=?", [$appId, $tid]);
    if (!$app) Response::error('Application not found', 404);

    $hiId = $db->insert('human_interviews', [
        'tenant_id'      => $tid,
        'application_id' => $appId,
        'interview_type' => $type,
        'interview_date' => $date,
        'duration_minutes' => $duration,
        'notes'          => $notes,
        'status'         => 'scheduled',
    ]);

    if (is_array($interviewerIds)) {
        foreach ($interviewerIds as $uid) {
            $db->insert('human_interview_evaluators', ['interview_id' => $hiId, 'user_id' => (int)$uid]);
        }
    }

    Response::success(['id' => $hiId], 'Interview scheduled');
}

// PUT /api/v1/human-interviews/{id}
if ($method === 'PUT' && $id) {
    $hi = $db->fetch("SELECT id FROM human_interviews WHERE id=? AND tenant_id=?", [$id, $tid]);
    if (!$hi) Response::error('Not found', 404);

    $data = $req->only(['interview_type','interview_date','duration_minutes','notes','status']);
    if ($data) $db->update('human_interviews', $data, ['id' => $id]);
    Response::success(null, 'Updated');
}

// DELETE /api/v1/human-interviews/{id}
if ($method === 'DELETE' && $id) {
    $db->update('human_interviews', ['status' => 'cancelled'], ['id' => $id, 'tenant_id' => $tid]);
    Response::success(null, 'Cancelled');
}

// POST /api/v1/human-interviews/{id}/evaluate
if ($method === 'POST' && $id && $sub === 'evaluate') {
    $score     = (int)$req->input('overall_score', 3);
    $notes     = $req->input('notes','');
    $recommend = $req->input('recommendation','maybe');

    $db->insert('human_interview_evaluations', [
        'interview_id'   => (int)$id,
        'evaluator_id'   => Auth::id(),
        'overall_score'  => max(1,min(5,$score)),
        'notes'          => $notes,
        'recommendation' => $recommend,
    ]);

    Response::success(null, 'Evaluation submitted');
}

Response::notFound();
