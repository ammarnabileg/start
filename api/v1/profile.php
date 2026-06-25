<?php
Auth::requireAuth();
$db  = Database::getInstance();
$uid = Auth::id();

// GET /api/v1/profile
if ($method === 'GET' && !$id) {
    $u = $db->fetch("SELECT id,email,first_name,last_name,phone,linkedin_url,portfolio_url,status,last_login_at FROM users WHERE id=?", [$uid]);
    Response::success($u);
}

// POST /api/v1/profile
if ($method === 'POST' && !$id) {
    $allowed = ['first_name','last_name','phone','linkedin_url','portfolio_url'];
    $data    = [];
    foreach ($allowed as $k) {
        $v = $req->input($k);
        if ($v !== null) $data[$k] = $v;
    }
    if ($data) {
        $db->update('users', $data, ['id' => $uid]);
        Auth::refresh();
    }
    Response::success(null, 'Profile updated');
}

// POST /api/v1/profile/password
if ($method === 'POST' && $id === 'password') {
    $current  = $req->input('current_password','');
    $new      = $req->input('password','');
    $confirm  = $req->input('password_confirm','');

    if (strlen($new) < 8) Response::error('Password must be at least 8 characters');
    if ($new !== $confirm) Response::error('Passwords do not match');

    $user = $db->fetch("SELECT password_hash FROM users WHERE id=?", [$uid]);
    if (!$user || !password_verify($current, $user['password_hash'])) {
        Response::error('Current password is incorrect');
    }

    $db->update('users', ['password_hash' => password_hash($new, PASSWORD_BCRYPT)], ['id' => $uid]);
    Response::success(null, 'Password changed');
}

Response::notFound();
