<?php
declare(strict_types=1);
/**
 * api/v1/auth.php — Authentication endpoints
 *   POST /api/v1/auth?action=login
 *   POST /api/v1/auth?action=logout
 *   GET  /api/v1/auth?action=me
 */

$method = $_SERVER['REQUEST_METHOD'];
$action = $request->get('action') ?? $request->input('action') ?? '';

if ($method === 'POST' && $action === 'login') {
    $email    = trim((string) $request->input('email', ''));
    $password = (string) $request->input('password', '');
    $slug     = trim((string) $request->input('tenant_slug', ''));

    if ($email === '' || $password === '') {
        Response::error('Email and password are required', 422);
    }

    $result = Auth::login($email, $password, $slug ?: null);
    if ($result === false) {
        Response::error('Invalid credentials', 401);
    }

    unset($result['user']['password_hash'], $result['user']['remember_token']);
    Response::success(['token' => $result['token'], 'user' => $result['user']], 'Logged in');
}

elseif ($method === 'POST' && $action === 'logout') {
    Auth::logout();
    Response::success(null, 'Logged out');
}

elseif ($method === 'GET' && $action === 'me') {
    Auth::requireAuth();
    $user = Auth::user();
    unset($user['password_hash'], $user['remember_token']);
    Response::success(['user' => $user]);
}

else {
    Response::error('Unknown auth action', 400);
}
