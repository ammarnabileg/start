<?php
$db = Database::getInstance();

// GET /api/v1/careers/jobs — all active jobs (optionally filtered by tenant_slug)
if ($method === 'GET' && $id === 'jobs') {
    $search    = $req->get('search','');
    $work_mode = $req->get('work_mode','');
    $type      = $req->get('type','');
    $slug      = $req->get('tenant_slug','');
    $page      = max(1,(int)$req->get('page',1));

    $where  = "j.status='active'";
    $params = [];

    if ($slug)      { $where .= " AND t.slug=?"; $params[] = $slug; }
    if ($search)    { $where .= " AND (j.title LIKE ? OR j.description LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }
    if ($work_mode) { $where .= " AND j.work_mode=?"; $params[] = $work_mode; }
    if ($type)      { $where .= " AND j.type=?"; $params[] = $type; }

    $result = $db->paginate(
        "SELECT j.id, j.title, j.location, j.type, j.work_mode, j.experience_level, j.salary_min, j.salary_max, j.currency, j.created_at,
                t.name as company_name, t.slug as company_slug
         FROM jobs j
         JOIN tenants t ON t.id=j.tenant_id
         WHERE {$where}
         ORDER BY j.created_at DESC",
        $params, $page, 20
    );
    Response::paginated($result['data'], $result['total'], $result['page'], $result['per_page']);
}

// GET /api/v1/careers/{slug}/jobs
if ($method === 'GET' && $id && $sub === 'jobs') {
    $slug   = $id;
    $tenant = $db->fetch("SELECT * FROM tenants WHERE slug=? AND status='active'", [$slug]);
    if (!$tenant) Response::notFound('Company not found');

    $jobs = $db->fetchAll(
        "SELECT j.*, t.name as company_name FROM jobs j
         JOIN tenants t ON t.id=j.tenant_id
         WHERE j.tenant_id=? AND j.status='active'
         ORDER BY j.created_at DESC",
        [$tenant['id']]
    );
    Response::success(['tenant' => $tenant, 'jobs' => $jobs]);
}

// POST /api/v1/careers/apply
if ($method === 'POST' && $id === 'apply') {
    Auth::requireAuth();
    if (!Auth::isCandidate()) Response::error('Only candidates can apply', 403);

    $jobId  = (int)$req->input('job_id');
    $cvPath = null;

    if (!$jobId) Response::error('job_id required');

    $job = $db->fetch("SELECT * FROM jobs WHERE id=? AND status='active'", [$jobId]);
    if (!$job) Response::notFound('Job not found or closed');

    $uid = Auth::id();
    $exists = $db->fetchColumn(
        "SELECT id FROM applications WHERE job_id=? AND candidate_id=?",
        [$jobId, $uid]
    );
    if ($exists) Response::error('You have already applied for this job');

    // Handle CV upload
    if (!empty($_FILES['cv']['tmp_name'])) {
        $file = $_FILES['cv'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','doc','docx'])) Response::error('CV must be PDF, DOC, or DOCX');
        if ($file['size'] > 5 * 1024 * 1024) Response::error('CV must be under 5MB');

        $filename = 'cv_' . $uid . '_' . time() . '.' . $ext;
        $dest     = STORAGE_PATH . '/cvs/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) Response::error('CV upload failed');
        $cvPath = $filename;
    }

    $appId = $db->insert('applications', [
        'tenant_id'     => $job['tenant_id'],
        'job_id'        => $jobId,
        'candidate_id'  => $uid,
        'current_stage' => 'applied',
        'cover_letter'  => $req->input('cover_letter',''),
        'cv_path'       => $cvPath,
        'applied_at'    => date('Y-m-d H:i:s'),
    ]);

    Response::success(['application_id' => $appId], 'Application submitted successfully');
}

Response::notFound();
