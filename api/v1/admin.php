<?php
/**
 * /api/v1/admin (super admin only)
 *   GET  /stats                     platform stats
 *   GET  /companies                 list companies
 *   POST /companies                 create company
 *   PUT  /companies/{id}/status     toggle status
 *   POST /terminal                  execute whitelisted command
 */

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Modules\SuperAdmin\SuperAdminService;

$api = $GLOBALS['__api'];
$req = new Request();
$auth = new Auth();
$auth->requirePermission('platform.admin');

$service = new SuperAdminService();
$action = $api['sub'];

if ($api['method'] === 'GET' && $action === 'stats') {
    Response::success($service->getPlatformStats());
    return;
}

if ($api['method'] === 'GET' && $action === 'companies') {
    Response::success($service->getCompanies([
        'status' => $req->get('status'),
        'plan'   => $req->get('plan'),
        'search' => $req->get('search'),
    ]));
    return;
}

if ($api['method'] === 'POST' && $action === 'companies') {
    $data = $req->all();
    [$ok, $errors] = (new App\Core\Validator())->validate($data, [
        'name'           => 'required',
        'subdomain'      => 'required|unique:tenants.subdomain',
        'admin_email'    => 'required|email',
        'admin_password' => 'required|min:8',
    ]);
    if (!$ok) {
        Response::error('Validation failed', 422, $errors);
        return;
    }
    $company = $service->createCompany($data);
    Response::success($company, 'Company created', 201);
    return;
}

if ($api['method'] === 'PUT' && $action === 'companies' && ($api['sub3'] ?? null) === 'status') {
    $id = (int) ($api['sub2'] ?? 0);
    $company = $service->toggleCompanyStatus($id);
    Response::success($company, 'Company status updated');
    return;
}

if ($api['method'] === 'POST' && $action === 'terminal') {
    $auth->requirePermission('platform.terminal');
    (new App\Modules\Terminal\TerminalController())->execute();
    return;
}

if ($api['method'] === 'GET' && $action === 'ai-analytics') {
    Response::success($service->getAIUsageAnalytics((string) $req->get('period', '30d')));
    return;
}

Response::error('Not found', 404);
