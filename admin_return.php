<?php
require_once 'includes/config.php';

if (!empty($_SESSION['pi_impersonate_admin_id'])) {
    $admin_id = (int)$_SESSION['pi_impersonate_admin_id'];
    unset($_SESSION['pi_user_id']);
    unset($_SESSION['pi_impersonate_admin_id']);
    $_SESSION['pi_admin_id'] = $admin_id;
}

header('Location: admin.php?p=users');
exit;
