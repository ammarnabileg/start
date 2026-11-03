<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_activity('logout');
    $_SESSION = [];
    session_destroy();
}
redirect(url('login'));
