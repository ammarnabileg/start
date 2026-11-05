<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/zernio.php';
require __DIR__ . '/app/views/render.php';
// الأجزاء الإضافية (تحليلات، واتساب، ...): كل ملف في app/modules بيضيف دوال Zernio بتاعته
foreach (glob(__DIR__ . '/app/modules/*.php') ?: [] as $m) {
    require $m;
}

// تغيير اللغة/الوضع شغال حتى قبل التسطيب
if (($_GET['p'] ?? '') === 'pref') {
    require __DIR__ . '/app/pages/pref.php';
}

// لو البرنامج مش متسطب، روح لصفحة التسطيب
if (!is_installed()) {
    redirect('setup.php');
}

date_default_timezone_set('UTC');
// ?refresh=1 بيجيب البيانات جديدة من غير الحفظ المؤقت
if (!empty($_GET['refresh'])) Zernio::$noCache = true;
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
// صفحات اتدمجت في 1.7.0
$merged = ['compose_pro' => 'compose', 'inbox_pro' => 'inbox'];
if (isset($merged[$page])) {
    redirect(url($merged[$page], array_diff_key($_GET, ['p' => 1])));
}

$public = ['login', 'register', 'invite', 'pref', 'cron', 'share', 'smsgate_hook', 'crm_hook'];
if (!in_array($page, $public, true) && !current_user()) {
    redirect(url('login'));
}

// لوحة إدارة النظام لمدير النظام بس
$adminPages = ['system', 'admin', 'admin_workspaces', 'admin_users', 'admin_activity', 'admin_compare'];
if (in_array($page, $adminPages, true) && !is_admin()) {
    redirect(url('dashboard'));
}

// وضع الصيانة: الموقع مقفول على الكل ما عدا مديري النظام
if (current_user() && !is_admin() && empty($_SESSION['impersonator']) && setting('maintenance', '0') === '1' && $page !== 'logout') {
    auth_page(t('وضع الصيانة'), function () {
        echo '<p class="text-center text-sm text-ink-600">' . e(setting('maintenance_message', '') ?: t('المنصة في وضع الصيانة دلوقتي. جرب بعد شوية.')) . '</p>';
        echo '<form method="post" action="' . e(url('logout')) . '">' . csrf_field() . '<button class="btn-ghost w-full">' . e(t('تسجيل خروج')) . '</button></form>';
    });
}

// الويبهوك جاي من موبايل/سيرفر بره ومحمي بتوكن وتوقيع، مش بـ CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page !== 'smsgate_hook' && $page !== 'crm_hook') {
    check_csrf();
}

// كل الصفحات بتشتغل جوه شركة. لو مالوش شركات، يروح صفحة الشركات (ينشئ واحدة أو يوافق على دعوة)
$noWorkspacePages = ['login', 'register', 'invite', 'pref', 'cron', 'share', 'smsgate_hook', 'crm_hook', 'logout', 'workspaces', 'switch', 'account', 'system', 'admin', 'admin_workspaces', 'admin_users', 'admin_activity', 'admin_compare', 'impersonate_stop'];
if (!in_array($page, $noWorkspacePages, true) && !current_workspace()) {
    redirect(url('workspaces'));
}

// قسم مقفول للشركة دي
if (current_workspace() && !module_enabled(page_module($page))) {
    flash('error', t('القسم ده مقفول للشركة دي. صاحب الشركة يقدر يفتحه من الإعدادات.'));
    redirect(url('dashboard'));
}

// الصلاحيات: كل صفحة ليها صلاحية مطلوبة حسب دور المستخدم في الشركة
$need = page_permission($page);
if ($need !== null && current_workspace() && !can($need)) {
    flash('error', t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.'));
    redirect(url('dashboard'));
}

$file = __DIR__ . '/app/pages/' . $page . '.php';
if (!is_file($file)) {
    http_response_code(404);
    $page = 'dashboard';
    $file = __DIR__ . '/app/pages/dashboard.php';
}

require $file;
