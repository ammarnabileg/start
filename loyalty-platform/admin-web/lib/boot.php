<?php
// نقطة تحميل موحّدة لكل الصفحات.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/export.php';
require_once __DIR__ . '/smartlist.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/audit.php';
boot_session();
