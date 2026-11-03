<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/zernio.php';
require __DIR__ . '/app/views/render.php';

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
    exit('<div dir="rtl" style="font-family:sans-serif;padding:40px">تعذر الاتصال بقاعدة البيانات. راجع بياناتها أو كلم الدعم الفني.</div>');
}

/**
 * لو تم رفع نسخة جديدة وفيها تحديثات قاعدة بيانات لسه ما اتشغلتش،
 * نوقف الموقع ونطلب تشغيل صفحة التحديث.
 */
if (setting('app_version') !== APP_VERSION) {
    redirect('setup.php');
}

$page = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'dashboard'));

$public = ['login'];
if (!in_array($page, $public, true) && !current_user()) {
    redirect(url('login'));
}

$adminOnly = ['users', 'settings'];
if (in_array($page, $adminOnly, true) && !is_admin()) {
    flash('error', 'الصفحة دي للمدير بس.');
    redirect(url('dashboard'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
}

$file = __DIR__ . '/app/pages/' . $page . '.php';
if (!is_file($file)) {
    http_response_code(404);
    $page = 'dashboard';
    $file = __DIR__ . '/app/pages/dashboard.php';
}

require $file;
