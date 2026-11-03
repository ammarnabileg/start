<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/zernio.php';
require __DIR__ . '/app/views/render.php';

// تغيير اللغة/الوضع شغال حتى قبل التسطيب
if (($_GET['p'] ?? '') === 'pref') {
    require __DIR__ . '/app/pages/pref.php';
}

// لو البرنامج مش متسطب، روح لصفحة التسطيب
if (!is_installed()) {
    redirect('setup.php');
}

date_default_timezone_set('UTC');
start_session();

try {
    db();
} catch (Throwable $t) {
    http_response_code(500);
    exit(t('<div dir="rtl" style="font-family:sans-serif;padding:40px">تعذر الاتصال بقاعدة البيانات. راجع بياناتها أو كلم الدعم الفني.</div>'));
}

/**
 * لو تم رفع نسخة جديدة وفيها تحديثات قاعدة بيانات لسه ما اتشغلتش،
 * نوقف الموقع ونطلب تشغيل صفحة التحديث.
 */
if (setting('app_version') !== APP_VERSION) {
    redirect('setup.php');
}

$page = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'dashboard'));

$public = ['login', 'register', 'invite', 'pref'];
if (!in_array($page, $public, true) && !current_user()) {
    redirect(url('login'));
}

// إعدادات النظام لمدير النظام بس
if ($page === 'system' && !is_admin()) {
    redirect(url('dashboard'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
}

// كل الصفحات بتشتغل جوه شركة. لو مالوش شركات، يروح صفحة الشركات (ينشئ واحدة أو يوافق على دعوة)
$noWorkspacePages = ['login', 'register', 'invite', 'pref', 'logout', 'workspaces', 'switch', 'account', 'system'];
if (!in_array($page, $noWorkspacePages, true) && !current_workspace()) {
    redirect(url('workspaces'));
}

// إعدادات الشركة وفريقها لصاحب الشركة بس
if (in_array($page, ['settings', 'team', 'connectors'], true) && !is_ws_owner()) {
    flash('error', t('الصفحة دي لصاحب الشركة بس.'));
    redirect(url('dashboard'));
}

$file = __DIR__ . '/app/pages/' . $page . '.php';
if (!is_file($file)) {
    http_response_code(404);
    $page = 'dashboard';
    $file = __DIR__ . '/app/pages/dashboard.php';
}

require $file;
