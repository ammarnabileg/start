<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$apiPath = substr($path, strlen('/api/v1'));
if (empty($apiPath)) $apiPath = '/';

function apiResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function apiError(string $message, int $status = 400, array $errors = []): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message, 'errors' => $errors]);
    exit;
}

// ── Auth check for protected API routes ───────────────────
$publicApiRoutes = ['/v1/jobs', '/v1/health'];
$requiresAuth = !in_array('/v1' . $apiPath, $publicApiRoutes);

if ($requiresAuth && !Auth::check()) {
    apiError('Unauthorized', 401);
}

$user = Auth::user();
$tenantId = Auth::tenantId();

// ── Route dispatcher ─────────────────────────────────────
switch (true) {

    // Health check
    case $apiPath === '/health' && $method === 'GET':
        apiResponse(['status' => 'ok', 'timestamp' => time()]);

    // Jobs listing (public)
    case $apiPath === '/jobs' && $method === 'GET':
        $db = Database::getInstance();
        $q = $request->get('q', '');
        $sql = "SELECT id, title, description, location, job_type, salary_min, salary_max, remote_ok
                FROM jobs WHERE status = 'active'";
        $params = [];
        if ($q) {
            $sql .= " AND (title LIKE ? OR description LIKE ?)";
            $params[] = "%$q%"; $params[] = "%$q%";
        }
        if ($tenantId) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        $jobs = $db->fetchAll($sql, $params);
        apiResponse(['success' => true, 'data' => $jobs]);

    // Candidate applications
    case $apiPath === '/applications' && $method === 'GET':
        if (!Auth::isCandidate()) apiError('Forbidden', 403);
        $db = Database::getInstance();
        $apps = $db->fetchAll(
            "SELECT a.id, a.status, a.created_at, j.title AS job_title, j.location,
                    t.name AS company_name
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN tenants t ON t.id = j.tenant_id
             WHERE a.candidate_id = ? AND a.deleted_at IS NULL
             ORDER BY a.created_at DESC LIMIT 100",
            [(int)$user['id']]
        );
        apiResponse(['success' => true, 'data' => $apps]);

    // AI Interview status
    case preg_match('#^/interviews/(\d+)/status$#', $apiPath, $m) && $method === 'GET':
        $db = Database::getInstance();
        $interview = $db->fetch(
            "SELECT id, status, overall_score, recommendation FROM ai_interviews WHERE id = ? AND tenant_id = ?",
            [(int)$m[1], $tenantId]
        );
        if (!$interview) apiError('Not found', 404);
        apiResponse(['success' => true, 'data' => $interview]);

    // Dashboard stats
    case $apiPath === '/stats' && $method === 'GET':
        if (!$tenantId) apiError('Forbidden', 403);
        $db = Database::getInstance();
        $stats = [
            'active_jobs' => (int)$db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE tenant_id = ? AND status = 'active'", [$tenantId]),
            'total_applications' => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.tenant_id = ? AND a.deleted_at IS NULL", [$tenantId]),
            'ai_interviews' => (int)$db->fetchColumn("SELECT COUNT(*) FROM ai_interviews WHERE tenant_id = ?", [$tenantId]),
            'hired_this_month' => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.tenant_id = ? AND a.status = 'hired' AND a.updated_at >= DATE_FORMAT(NOW(),'%Y-%m-01')", [$tenantId]),
        ];
        apiResponse(['success' => true, 'data' => $stats]);

    default:
        apiError('API endpoint not found', 404);
}
