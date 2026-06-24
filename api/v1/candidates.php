<?php
/**
 * /api/v1/candidates
 *   GET    /                 list
 *   POST   /                 create
 *   GET    /{id}             one
 *   PUT    /{id}             update
 *   GET    /{id}/profile     full 360 profile
 *   POST   /apply/{jobId}    public application (no auth)
 */

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Candidates\CandidateService;

$api = $GLOBALS['__api'];
$req = new Request();
$auth = new Auth();
$service = new CandidateService();
$tenant = new Tenant();

$id = $api['sub'];
$action = $api['sub2'];

// Public application: POST /candidates/apply/{jobId}
if ($api['method'] === 'POST' && $id === 'apply') {
    $jobId = (int) ($api['sub2'] ?? 0);
    $controller = new App\Modules\Candidates\CandidateController();
    $controller->publicApply(['jobId' => $jobId]);
    return;
}

$tenantId = $tenant->currentId();

if ($api['method'] === 'GET' && ($id === null || $id === '')) {
    $auth->requirePermission('candidates.view');
    $candidates = $service->getCandidates($tenantId, [
        'search' => $req->get('search'),
        'status' => $req->get('status'),
        'job_id' => $req->get('job_id'),
    ]);
    Response::success($candidates);
    return;
}

if ($api['method'] === 'GET' && $action === 'profile') {
    $auth->requirePermission('candidates.view');
    Response::success($service->getFullProfile((int) $id));
    return;
}

if ($api['method'] === 'GET' && $id !== null) {
    $auth->requirePermission('candidates.view');
    $c = $service->getCandidate((int) $id, $tenantId);
    $c ? Response::success($c) : Response::error('Candidate not found', 404);
    return;
}

if ($api['method'] === 'POST' && ($id === null || $id === '')) {
    $auth->requirePermission('candidates.create');
    $data = $req->all();
    [$ok, $errors] = (new App\Core\Validator())->validate($data, [
        'email'      => 'required|email',
        'first_name' => 'required',
    ]);
    if (!$ok) {
        Response::error('Validation failed', 422, $errors);
        return;
    }
    $c = $service->createCandidate($data);
    Response::success($c, 'Candidate created', 201);
    return;
}

if ($api['method'] === 'PUT' && $id !== null) {
    $auth->requirePermission('candidates.edit');
    $service->updateCandidate((int) $id, $req->all());
    Response::success($service->getCandidate((int) $id, $tenantId), 'Candidate updated');
    return;
}

Response::error('Method not allowed', 405);
