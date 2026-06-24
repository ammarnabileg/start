<?php
/**
 * /api/v1/jobs
 *   GET    /                       list (filters: status, department, search)
 *   POST   /                       create
 *   GET    /{id}                   one
 *   PUT    /{id}                   update
 *   DELETE /{id}                   delete
 *   POST   /{id}/publish           publish
 *   GET    /public/{subdomain}     public career-page jobs (no auth)
 */

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Jobs\JobService;

$api = $GLOBALS['__api'];
$req = new Request();
$auth = new Auth();
$service = new JobService();
$tenant = new Tenant();

$id = $api['sub'];
$action = $api['sub2'];

// Public career-page listing: GET /jobs/public/{subdomain}
if ($api['method'] === 'GET' && $id === 'public') {
    $subdomain = $api['sub2'] ?? '';
    $row = App\Core\Database::instance()->fetch(
        'SELECT id FROM tenants WHERE subdomain = :s AND status = "active" LIMIT 1',
        [':s' => $subdomain]
    );
    if (!$row) {
        Response::success([]); // unknown company -> empty list
        return;
    }
    $jobs = $service->getJobs((int) $row['id'], ['status' => 'published']);
    Response::success($jobs);
    return;
}

$tenantId = $tenant->currentId();

if ($api['method'] === 'GET' && ($id === null || $id === '')) {
    $auth->requirePermission('jobs.view');
    $jobs = $service->getJobs($tenantId, [
        'status'     => $req->get('status'),
        'department' => $req->get('department'),
        'search'     => $req->get('search'),
    ]);
    Response::success($jobs);
    return;
}

if ($api['method'] === 'GET' && $id !== null) {
    $auth->requirePermission('jobs.view');
    $job = $service->getJob((int) $id, $tenantId);
    $job ? Response::success($job) : Response::error('Job not found', 404);
    return;
}

if ($api['method'] === 'POST' && ($id === null || $id === '')) {
    $auth->requirePermission('jobs.create');
    $data = $req->all();
    if (trim((string) ($data['title'] ?? '')) === '') {
        Response::error('Title is required', 422);
        return;
    }
    $job = $service->createJob($data, $auth->id());
    Response::success($job, 'Job created', 201);
    return;
}

if ($api['method'] === 'POST' && $action === 'publish') {
    $auth->requirePermission('jobs.publish');
    $service->publishJob((int) $id);
    Response::success(null, 'Job published');
    return;
}

if ($api['method'] === 'PUT' && $id !== null) {
    $auth->requirePermission('jobs.edit');
    $service->updateJob((int) $id, $req->all());
    Response::success($service->getJob((int) $id, $tenantId), 'Job updated');
    return;
}

if ($api['method'] === 'DELETE' && $id !== null) {
    $auth->requirePermission('jobs.delete');
    $service->deleteJob((int) $id);
    Response::success(null, 'Job deleted');
    return;
}

Response::error('Method not allowed', 405);
