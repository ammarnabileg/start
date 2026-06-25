<?php
// POST /api/v1/auth/logout
if ($method === 'POST' && $sub === 'logout') {
    Auth::logout();
    Response::success(null, 'Logged out');
}

// GET /api/v1/auth/me
if ($method === 'GET' && $sub === 'me') {
    Auth::requireAuth();
    $u = Auth::user();
    unset($u['password_hash']);
    Response::success($u);
}

Response::notFound();
