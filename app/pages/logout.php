<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_activity('logout');
    $_SESSION = [];
    $cp = session_get_cookie_params();
    session_destroy();
    setcookie('zin', '', ['expires' => time() - 3600, 'path' => '/']);
    // نمسح كوكي الجلسة عشان مايظهرش «انتهت جلستك» بعد خروج مقصود
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $cp['path'], 'domain' => $cp['domain'], 'secure' => $cp['secure'], 'httponly' => $cp['httponly'], 'samesite' => $cp['samesite'] ?: 'Lax']);
}
redirect(url('login'));
