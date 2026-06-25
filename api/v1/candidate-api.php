<?php
Auth::requireAuth();
if (!Auth::isCandidate()) Response::error('Access denied', 403);
$db  = Database::getInstance();
$uid = Auth::id();

// GET /api/v1/candidate/profile
if ($method === 'GET' && $id === 'profile') {
    $u = $db->fetch("SELECT id,email,first_name,last_name,phone,linkedin_url,portfolio_url,cv_path FROM users WHERE id=?", [$uid]);
    Response::success($u);
}

// POST /api/v1/candidate/profile
if ($method === 'POST' && $id === 'profile') {
    $allowed = ['first_name','last_name','phone','linkedin_url','portfolio_url'];
    $data    = [];
    foreach ($allowed as $k) {
        $v = $req->input($k);
        if ($v !== null) $data[$k] = $v;
    }
    if ($data) { $db->update('users', $data, ['id' => $uid]); Auth::refresh(); }
    Response::success(null, 'Profile updated');
}

// POST /api/v1/candidate/cv
if ($method === 'POST' && $id === 'cv') {
    if (empty($_FILES['cv']['tmp_name'])) Response::error('No file uploaded');
    $file = $_FILES['cv'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf','doc','docx'])) Response::error('CV must be PDF, DOC, or DOCX');
    if ($file['size'] > 5 * 1024 * 1024) Response::error('File must be under 5MB');

    $filename = 'cv_' . $uid . '_' . time() . '.' . $ext;
    $dest     = STORAGE_PATH . '/cvs/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) Response::error('Upload failed');

    $db->update('users', ['cv_path' => $filename], ['id' => $uid]);
    Auth::refresh();
    Response::success(['filename' => $filename], 'CV uploaded');
}

// GET /api/v1/candidate/applications
if ($method === 'GET' && $id === 'applications') {
    $apps = $db->fetchAll(
        "SELECT a.*, j.title as job_title, t.name as company_name,
                ai.overall_score, ai.recommendation
         FROM applications a
         JOIN jobs j ON j.id=a.job_id
         JOIN tenants t ON t.id=a.tenant_id
         LEFT JOIN ai_interviews ai ON ai.application_id=a.id
         WHERE a.candidate_id=?
         ORDER BY a.applied_at DESC",
        [$uid]
    );
    Response::success($apps);
}

// GET /api/v1/candidate/offers
if ($method === 'GET' && $id === 'offers') {
    $offers = $db->fetchAll(
        "SELECT o.*, j.title as job_title, t.name as company_name
         FROM offers o
         JOIN applications a ON a.id=o.application_id
         JOIN jobs j ON j.id=a.job_id
         JOIN tenants t ON t.id=o.tenant_id
         WHERE a.candidate_id=?
         ORDER BY o.created_at DESC",
        [$uid]
    );
    Response::success($offers);
}

// POST /api/v1/candidate/offers/{id}/respond
if ($method === 'POST' && $id === 'offers' && $sub && $sub2 === 'respond') {
    $offerId = (int)$sub;
    $status  = $req->input('status','');
    if (!in_array($status, ['accepted','rejected'])) Response::error('Invalid status');

    // Verify this offer belongs to candidate
    $offer = $db->fetch(
        "SELECT o.id FROM offers o
         JOIN applications a ON a.id=o.application_id
         WHERE o.id=? AND a.candidate_id=? AND o.status='sent'",
        [$offerId, $uid]
    );
    if (!$offer) Response::error('Offer not found or already responded', 404);

    $db->update('offers', ['status' => $status], ['id' => $offerId]);
    Response::success(null, 'Response submitted');
}

Response::notFound();
