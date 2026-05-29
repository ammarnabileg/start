<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
logout_user();
header('Location: /platform/login.php');
exit;
