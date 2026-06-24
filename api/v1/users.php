<?php
/**
 * /api/v1/users
 *   GET    /            list
 *   POST   /            create
 *   GET    /{id}        one
 *   PUT    /{id}        update
 *   DELETE /{id}        delete
 *   PUT    /{id}/role   assign role (role_id in body)
 */

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Users\UserService;

$api = $GLOBALS['__api'];
$req = new Request();
$auth = new Auth();
$service = new UserService();
$tenantId = (new Tenant())->currentId();

$id = $api['sub'];
$action = $api['sub2'];

if ($api['method'] === 'GET' && ($id === null || $id === '')) {
    $auth->requirePermission('users.view');
    $users = array_map(function ($u) {
        unset($u['password_hash']);
        return $u;
    }, $service->getUsers($tenantId));
    Response::success($users);
    return;
}

if ($api['method'] === 'POST' && ($id === null || $id === '')) {
    $auth->requirePermission('users.manage');
    $data = $req->all();
    [$ok, $errors] = (new App\Core\Validator())->validate($data, [
        'email'    => 'required|email',
        'password' => 'required|min:8',
    ]);
    if (!$ok) {
        Response::error('Validation failed', 422, $errors);
        return;
    }
    $user = $service->createUser($data, $tenantId);
    if (is_array($user)) {
        unset($user['password_hash']);
    }
    Response::success($user, 'User created', 201);
    return;
}

if ($api['method'] === 'PUT' && $action === 'role') {
    $auth->requirePermission('users.manage');
    $service->assignRole((int) $id, (int) $req->post('role_id', 0));
    Response::success(null, 'Role assigned');
    return;
}

if ($api['method'] === 'PUT' && $id !== null) {
    $auth->requirePermission('users.manage');
    $service->updateUser((int) $id, $req->all());
    Response::success(null, 'User updated');
    return;
}

if ($api['method'] === 'DELETE' && $id !== null) {
    $auth->requirePermission('users.manage');
    $service->deleteUser((int) $id);
    Response::success(null, 'User deleted');
    return;
}

if ($api['method'] === 'GET' && $id !== null) {
    $auth->requirePermission('users.view');
    $users = $service->getUsers($tenantId);
    foreach ($users as $u) {
        if ((int) $u['id'] === (int) $id) {
            unset($u['password_hash']);
            Response::success($u);
            return;
        }
    }
    Response::error('User not found', 404);
    return;
}

Response::error('Method not allowed', 405);
