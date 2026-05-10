<?php
require_once __DIR__ . '/_bootstrap.php';
$auth = api_authenticate();
$u = db_one('SELECT id, name, email, role_id, last_login_at FROM ' . tbl('users') . ' WHERE id = :id', ['id' => auth_id()]);
$stats = db_one('SELECT * FROM ' . tbl('user_stats') . ' WHERE user_id = :u', ['u' => auth_id()]);
api_ok(['user' => $u, 'stats' => $stats, 'permissions' => $_SESSION['crm_perms'] ?? []]);
