<?php
/**
 * /api/v1/talent-pools
 *   GET    /                            list pools
 *   POST   /                            create pool
 *   GET    /{id}                        pool detail (with candidates)
 *   PUT    /{id}                         update
 *   DELETE /{id}                         delete
 *   POST   /{id}/candidates              add candidate (candidate_id in body)
 *   DELETE /{id}/candidates/{candId}     remove candidate
 *   GET    /{id}/search?q=               search within pool
 */

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\TalentPool\TalentPoolService;

$api = $GLOBALS['__api'];
$req = new Request();
$auth = new Auth();
$service = new TalentPoolService();
$tenantId = (new Tenant())->currentId();

$id = $api['sub'];
$action = $api['sub2'];

if ($api['method'] === 'GET' && ($id === null || $id === '')) {
    $auth->requirePermission('talent_pool.view');
    Response::success($service->getPools($tenantId));
    return;
}

if ($api['method'] === 'POST' && ($id === null || $id === '')) {
    $auth->requirePermission('talent_pool.manage');
    $name = trim((string) $req->post('name', ''));
    if ($name === '') {
        Response::error('Name is required', 422);
        return;
    }
    $pool = $service->createPool([
        'name'        => $name,
        'description' => $req->post('description', ''),
        'tenant_id'   => $tenantId,
        'created_by'  => $auth->id(),
    ]);
    Response::success($pool, 'Pool created', 201);
    return;
}

if ($api['method'] === 'GET' && $action === 'search') {
    $auth->requirePermission('talent_pool.view');
    Response::success($service->searchPool((int) $id, (string) $req->get('q', '')));
    return;
}

if ($api['method'] === 'GET' && $id !== null) {
    $auth->requirePermission('talent_pool.view');
    $pool = $service->getPool((int) $id, $tenantId);
    $pool ? Response::success($pool) : Response::error('Pool not found', 404);
    return;
}

if ($api['method'] === 'POST' && $action === 'candidates') {
    $auth->requirePermission('talent_pool.manage');
    $candidateId = (int) $req->post('candidate_id', 0);
    $service->addCandidate((int) $id, $candidateId);
    Response::success(null, 'Candidate added');
    return;
}

if ($api['method'] === 'DELETE' && $action === 'candidates' && $api['sub3'] !== null) {
    $auth->requirePermission('talent_pool.manage');
    $service->removeCandidate((int) $id, (int) $api['sub3']);
    Response::success(null, 'Candidate removed');
    return;
}

if ($api['method'] === 'DELETE' && $id !== null) {
    $auth->requirePermission('talent_pool.manage');
    App\Core\Database::instance()->delete('talent_pools', ['id' => (int) $id]);
    Response::success(null, 'Pool deleted');
    return;
}

Response::error('Method not allowed', 405);
