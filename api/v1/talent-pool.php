<?php
Auth::requireHR();
$db  = Database::getInstance();
$tid = Auth::tenantId();

// GET /api/v1/talent-pool — list groups with counts
if ($method === 'GET' && !$id) {
    $groups = $db->fetchAll(
        "SELECT tpg.*, COUNT(tpe.id) as candidate_count
         FROM talent_pool_groups tpg
         LEFT JOIN talent_pool_entries tpe ON tpe.group_id=tpg.id
         WHERE tpg.tenant_id=?
         GROUP BY tpg.id
         ORDER BY tpg.created_at DESC",
        [$tid]
    );
    Response::success($groups);
}

// POST /api/v1/talent-pool/groups
if ($method === 'POST' && $id === 'groups' && !$sub) {
    $name = trim($req->input('name',''));
    $desc = $req->input('description','');
    if (!$name) Response::error('Name required');

    $gid = $db->insert('talent_pool_groups', ['tenant_id'=>$tid,'name'=>$name,'description'=>$desc]);
    Response::success(['id'=>$gid], 'Group created');
}

// GET /api/v1/talent-pool/groups/{gid}/candidates
if ($method === 'GET' && $id === 'groups' && $sub && !$sub2) {
    $gid = (int)$sub;
    $candidates = $db->fetchAll(
        "SELECT u.id, CONCAT(u.first_name,' ',u.last_name) as name, u.email,
                a.overall_score as ai_score, tpe.added_at
         FROM talent_pool_entries tpe
         JOIN users u ON u.id=tpe.candidate_id
         LEFT JOIN (
             SELECT candidate_id, MAX(overall_score) as overall_score
             FROM ai_interviews GROUP BY candidate_id
         ) a ON a.candidate_id=u.id
         WHERE tpe.group_id=?
         ORDER BY tpe.added_at DESC",
        [$gid]
    );
    Response::success($candidates);
}

// POST /api/v1/talent-pool/groups/{gid}/candidates
if ($method === 'POST' && $id === 'groups' && $sub && $sub2 === 'candidates') {
    $gid         = (int)$sub;
    $candidateId = (int)$req->input('candidate_id');
    if (!$candidateId) Response::error('candidate_id required');

    // Check not already in group
    $exists = $db->fetchColumn("SELECT id FROM talent_pool_entries WHERE group_id=? AND candidate_id=?", [$gid,$candidateId]);
    if ($exists) Response::error('Candidate already in this group');

    $db->insert('talent_pool_entries', ['group_id'=>$gid,'candidate_id'=>$candidateId,'tenant_id'=>$tid]);
    Response::success(null, 'Added to talent pool');
}

// DELETE /api/v1/talent-pool/groups/{gid}/candidates/{cid}
if ($method === 'DELETE' && $id === 'groups' && $sub && $sub2) {
    $gid = (int)$sub;
    $cid = (int)$sub2;
    $db->query("DELETE FROM talent_pool_entries WHERE group_id=? AND candidate_id=?", [$gid,$cid]);
    Response::success(null, 'Removed from talent pool');
}

Response::notFound();
