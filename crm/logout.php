<?php
require_once __DIR__ . '/includes/auth.php';
auth_logout();
header('Location: ' . rtrim(CRM_BASE_URL, '/') . '/login.php');
