<?php
/** الرجوع لحساب مدير النظام بعد "ادخل كـ" */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['impersonator'])) {
    $back = (int)$_SESSION['impersonator'];
    $as = current_user();
    unset($_SESSION['impersonator'], $_SESSION['ws']);
    session_regenerate_id(true);
    $_SESSION['uid'] = $back;
    db()->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'admin.impersonate_stop', ?)")
        ->execute([$back, (string)($as['email'] ?? '')]);
    redirect(url('admin_users'));
}
redirect(url('dashboard'));
