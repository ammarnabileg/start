<?php
unset($_SESSION['pi_admin_id']);
session_destroy();
header('Location: admin.php?p=login');
exit;
