<?php
declare(strict_types=1);
/**
 * api/v1/jobs.php — Jobs CRUD API
 */

Auth::requireAuth();
$db     = Database::getInstance();
$userId = Auth::user()['id'];
$tid    = Auth::user()['tenant_id'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$action = $request->get('action') ?? $request->input('action') ?? '';
$id     = (int)($request->get('id') ?? $request->input('id') ?? 0);

// ── List jobs ─────────────────────────────────────────────────────────────
if ($method === 'GET' && !$action && !$id) {
    Auth::requirePermission('jobs.view');
    $page   = max(1, (int)$request->get('page', 1));
    $status = $request->get('status', '');
    $dept   = $request->get('department', '');
    $search = trim($request->get('search', ''));

    $where  = ['j.tenant_id = ?'];
    $params = [$tid];
    if ($status) { $where[] = 'j.status = ?'; $params[] = $status; }
    if ($dept)   { $where[] = 'j.department = ?'; $params[] = $dept; }
    if ($search) { $where[] = 'j.title LIKE ?'; $params[] = "%$search%"; }

    $sql = "SELECT j.id, j.title, j.department, j.location, j.status, j.job_type,
                   j.salary_min, j.salary_max, j.salary_currency,
                   j.interview_process, j.created_at, j.published_at,
                   (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.tenant_id = j.tenant_id) as applicant_count,
                   (SELECT COUNT(*) FROM interviews i JOIN applications a2 ON a2.id = i.application_id WHERE a2.job_id = j.id) as interview_count
            FROM jobs j
            WHERE " . implode(' AND ', $where) . "
            ORDER BY j.created_at DESC";

    $result = $db->paginate($sql, $params, $page, 20);
    Response::paginated($result['data'], $result['total'], $page, 20);
}

// ── Get single job ────────────────────────────────────────────────────────
elseif ($method === 'GET' && $id) {
    Auth::requirePermission('jobs.view');
    $job = $db->fetch("SELECT * FROM jobs WHERE id = ? AND tenant_id = ?", [$id, $tid]);
    if (!$job) { Response::error('Not found', 404); exit; }
    foreach (['ai_criteria','benefits'] as $f) {
        if (isset($job[$f])) $job[$f] = json_decode($job[$f], true);
    }
    Response::success($job);
}

// ── Create job ────────────────────────────────────────────────────────────
elseif ($method === 'POST' && !$action) {
    Auth::requirePermission('jobs.create');

    $data = $request->only([
        'title','department','location','job_type','experience_level',
        'salary_min','salary_max','salary_currency','show_salary',
        'description','requirements','benefits','interview_process',
        'avatar_id','time_limit_minutes','ai_criteria',
        'deadline','max_applications','require_cv','require_cover_letter',
        'auto_reject_threshold','question_count'
    ]);

    if (empty($data['title'])) { Response::error('Title is required', 422); exit; }

    $jobId = $db->insert('jobs', [
        'tenant_id'              => $tid,
        'created_by'             => $userId,
        'title'                  => $data['title'],
        'department'             => $data['department'] ?? '',
        'location'               => $data['location'] ?? '',
        'job_type'               => $data['job_type'] ?? 'full_time',
        'experience_level'       => $data['experience_level'] ?? 'mid',
        'salary_min'             => $data['salary_min'] ?: null,
        'salary_max'             => $data['salary_max'] ?: null,
        'salary_currency'        => $data['salary_currency'] ?? 'USD',
        'show_salary'            => (int)($data['show_salary'] ?? 1),
        'description'            => $data['description'] ?? '',
        'requirements'           => $data['requirements'] ?? '',
        'benefits'               => is_array($data['benefits'] ?? null) ? json_encode($data['benefits']) : ($data['benefits'] ?? null),
        'interview_process'      => $data['interview_process'] ?? 'ai_text',
        'avatar_id'              => $data['avatar_id'] ?: null,
        'time_limit_minutes'     => (int)($data['time_limit_minutes'] ?? 30),
        'ai_criteria'            => is_array($data['ai_criteria'] ?? null) ? json_encode($data['ai_criteria']) : ($data['ai_criteria'] ?? null),
        'application_deadline'   => $data['deadline'] ?: null,
        'max_applications'       => $data['max_applications'] ?: null,
        'require_cv'             => (int)($data['require_cv'] ?? 1),
        'require_cover_letter'   => (int)($data['require_cover_letter'] ?? 0),
        'auto_reject_threshold'  => (int)($data['auto_reject_threshold'] ?? 0),
        'status'                 => 'draft',
        'created_at'             => date('Y-m-d H:i:s'),
        'updated_at'             => date('Y-m-d H:i:s')
    ]);

    $db->insert('audit_logs', [
        'tenant_id' => $tid, 'user_id' => $userId,
        'action' => 'job.created', 'entity_type' => 'job', 'entity_id' => $jobId,
        'meta' => json_encode(['title' => $data['title']]),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    Response::success(['id' => $jobId, 'message' => 'Job created']);
}

// ── Update job ────────────────────────────────────────────────────────────
elseif ($method === 'PUT' && $id) {
    Auth::requirePermission('jobs.edit');
    $job = $db->fetch("SELECT id FROM jobs WHERE id = ? AND tenant_id = ?", [$id, $tid]);
    if (!$job) { Response::error('Not found', 404); exit; }

    $data = $request->only([
        'title','department','location','job_type','experience_level',
        'salary_min','salary_max','salary_currency','show_salary',
        'description','requirements','benefits','interview_process',
        'avatar_id','time_limit_minutes','ai_criteria',
        'deadline','max_applications','require_cv','require_cover_letter','auto_reject_threshold'
    ]);

    if (isset($data['benefits']) && is_array($data['benefits'])) $data['benefits'] = json_encode($data['benefits']);
    if (isset($data['ai_criteria']) && is_array($data['ai_criteria'])) $data['ai_criteria'] = json_encode($data['ai_criteria']);
    if (array_key_exists('deadline', $data)) { $data['application_deadline'] = $data['deadline']; unset($data['deadline']); }
    $data['updated_at'] = date('Y-m-d H:i:s');

    $db->update('jobs', $data, ['id' => $id]);
    Response::success(['message' => 'Updated']);
}

// ── Publish / unpublish ───────────────────────────────────────────────────
elseif ($method === 'POST' && $action === 'publish') {
    Auth::requirePermission('jobs.edit');
    $targetId = $id ?: (int)$request->input('id');
    $job = $db->fetch("SELECT id, status FROM jobs WHERE id = ? AND tenant_id = ?", [$targetId, $tid]);
    if (!$job) { Response::error('Not found', 404); exit; }

    $newStatus = $job['status'] === 'published' ? 'draft' : 'published';
    $db->update('jobs', [
        'status'       => $newStatus,
        'published_at' => $newStatus === 'published' ? date('Y-m-d H:i:s') : null,
        'updated_at'   => date('Y-m-d H:i:s')
    ], ['id' => $targetId]);

    Response::success(['status' => $newStatus]);
}

// ── Archive ───────────────────────────────────────────────────────────────
elseif ($method === 'DELETE' || ($method === 'POST' && $action === 'archive')) {
    Auth::requirePermission('jobs.archive');
    $targetId = $id ?: (int)$request->input('id');
    $db->update('jobs', ['status' => 'archived', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $targetId, 'tenant_id' => $tid]);
    Response::success(['message' => 'Archived']);
}

// ── Duplicate ─────────────────────────────────────────────────────────────
elseif ($method === 'POST' && $action === 'duplicate') {
    Auth::requirePermission('jobs.create');
    $targetId = $id ?: (int)$request->input('id');
    $job = $db->fetch("SELECT * FROM jobs WHERE id = ? AND tenant_id = ?", [$targetId, $tid]);
    if (!$job) { Response::error('Not found', 404); exit; }

    unset($job['id']);
    $job['title']     = $job['title'] . ' (Copy)';
    $job['status']    = 'draft';
    $job['created_at']= $job['updated_at'] = date('Y-m-d H:i:s');
    $job['published_at'] = null;
    $job['created_by']   = $userId;

    $newId = $db->insert('jobs', $job);
    Response::success(['id' => $newId, 'message' => 'Duplicated']);
}

else {
    Response::error('Unknown action or method', 400);
}
