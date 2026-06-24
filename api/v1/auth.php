<?php
/**
 * /api/v1/auth
 *   POST   /login     POST /logout     POST /refresh     GET /me
 */

use App\Core\Auth;
use App\Core\JWT;
use App\Core\Request;
use App\Core\Response;

$api = $GLOBALS['__api'];
$req = new Request();
$auth = new Auth();
$action = $api['sub'];

switch ($api['method'] . ':' . $action) {
    case 'POST:login':
        $email = trim((string) $req->post('email', ''));
        $password = (string) $req->post('password', '');
        $tenantId = (new App\Core\Tenant())->currentId();
        if ($email === '' || $password === '') {
            Response::error('Email and password are required', 422);
            return;
        }
        $token = $auth->login($email, $password, $tenantId);
        if ($token === false) {
            Response::error('Invalid credentials', 401);
            return;
        }
        Response::success([
            'token' => $token,
            'user'  => sanitizeUser($auth->user()),
        ], 'Logged in');
        return;

    case 'POST:logout':
        $auth->logout();
        Response::success(null, 'Logged out');
        return;

    case 'POST:refresh':
        $token = $req->bearerToken() ?? (string) $req->post('token', '');
        $cfg = config('app')['jwt'];
        $new = JWT::refresh($token, $cfg['secret'], $cfg['expiry']);
        if ($new === false) {
            Response::error('Invalid or expired token', 401);
            return;
        }
        Response::success(['token' => $new]);
        return;

    case 'GET:me':
        $auth->requireAuth();
        Response::success(['user' => sanitizeUser($auth->user())]);
        return;

    default:
        Response::error('Not found', 404);
}

function sanitizeUser(?array $user): ?array
{
    if (!$user) {
        return null;
    }
    unset($user['password_hash']);
    return $user;
}
