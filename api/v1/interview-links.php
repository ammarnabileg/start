<?php
Auth::requireHR();
$db  = Database::getInstance();
$tid = Auth::tenantId();

// GET /api/v1/interview-links
if ($method === 'GET' && !$id) {
    $job_id = $req->get('job_id');
    $where  = "il.tenant_id=?";
    $params = [$tid];
    if ($job_id) { $where .= " AND il.job_id=?"; $params[] = $job_id; }

    $links = $db->fetchAll(
        "SELECT il.*, j.title as job_title, a.name as avatar_name
         FROM interview_links il
         JOIN jobs j ON j.id=il.job_id
         LEFT JOIN avatars a ON a.id=il.avatar_id
         WHERE {$where}
         ORDER BY il.created_at DESC",
        $params
    );
    Response::success($links);
}

// POST /api/v1/interview-links
if ($method === 'POST') {
    $job_id    = (int)$req->input('job_id');
    $avatar_id = $req->input('avatar_id') ? (int)$req->input('avatar_id') : null;

    if (!$job_id) Response::error('job_id required');

    // Verify job belongs to tenant
    $job = $db->fetch("SELECT id FROM jobs WHERE id=? AND tenant_id=?", [$job_id, $tid]);
    if (!$job) Response::error('Job not found', 404);

    $token     = bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+14 days'));

    $linkId = $db->insert('interview_links', [
        'tenant_id'  => $tid,
        'job_id'     => $job_id,
        'avatar_id'  => $avatar_id,
        'token'      => $token,
        'expires_at' => $expiresAt,
        'is_active'  => 1,
        'used_count' => 0,
    ]);

    $appUrl = $_ENV['APP_URL'] ?? '';
    Response::success([
        'id'         => $linkId,
        'token'      => $token,
        'url'        => $appUrl . '/interview/' . $token,
        'expires_at' => $expiresAt,
    ], 'Interview link created');
}

// DELETE /api/v1/interview-links/{id}
if ($method === 'DELETE' && $id) {
    $db->update('interview_links', ['is_active' => 0], ['id' => $id, 'tenant_id' => $tid]);
    Response::success(null, 'Link deactivated');
}

Response::notFound();
