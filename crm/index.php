<?php
require_once __DIR__ . '/includes/auth.php';
auth_start_session();
if (auth_check()) redirect('dashboard.php');
redirect('login.php');
