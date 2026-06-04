<?php
require_once 'includes/config.php';
unset($_SESSION['pi_user_id']);
header('Location: index.php');
exit;
