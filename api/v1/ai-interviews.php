<?php
Auth::requireHR();
$db  = Database::getInstance();
$tid = Auth::tenantId();

// GET /api/v1/ai-interviews
if ($method === 'GET' && !$id) {
    $page    = max(1,(int)$req->get('page',1));
    $status  = $req->get('status','');
    $job_id  = $req->get('job_id','');

    $where  = "ai.tenant_id=?";
    $params = [$tid];

    if ($status) { $where .= " AND ai.status=?"; $params[] = $status; }
    if ($job_id) { $where .= " AND a.job_id=?"; $params[] = $job_id; }

    $result = $db->paginate(
        "SELECT ai.*, a.job_id,
                CONCAT(u.first_name,' ',u.last_name) as candidate_name,
                j.title as job_title
         FROM ai_interviews ai
         JOIN applications a ON a.id = ai.application_id
         JOIN users u ON u.id = a.candidate_id
         JOIN jobs j ON j.id = a.job_id
         WHERE {$where}
         ORDER BY ai.created_at DESC",
        $params, $page, 20
    );
    Response::paginated($result['data'], $result['total'], $result['page'], $result['per_page']);
}

// GET /api/v1/ai-interviews/{id}
if ($method === 'GET' && $id) {
    $interview = $db->fetch(
        "SELECT ai.*, CONCAT(u.first_name,' ',u.last_name) as candidate_name, j.title as job_title
         FROM ai_interviews ai
         JOIN applications a ON a.id = ai.application_id
         JOIN users u ON u.id = a.candidate_id
         JOIN jobs j ON j.id = a.job_id
         WHERE ai.id=? AND ai.tenant_id=?",
        [$id, $tid]
    );
    if (!$interview) Response::notFound();

    if ($interview['transcript']) $interview['transcript'] = json_decode($interview['transcript'], true);
    if ($interview['skills_scores']) $interview['skills_scores'] = json_decode($interview['skills_scores'], true);
    if ($interview['behavioral_analysis']) $interview['behavioral_analysis'] = json_decode($interview['behavioral_analysis'], true);
    if ($interview['red_flags']) $interview['red_flags'] = json_decode($interview['red_flags'], true);

    Response::success($interview);
}

Response::notFound();
