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
    exit(t('<div dir="rtl" style="font-family:sans-serif;padding:40px">تعذّر الاتصال بقاعدة البيانات. راجع بياناتها أو تواصل مع الدعم الفني.</div>'));
}

/**
 * لو تم رفع نسخة جديدة وفيها تحديثات قاعدة بيانات لسه ما اتشغلتش،
 * نوقف الموقع ونطلب تشغيل صفحة التحديث.
 */
if (setting('app_version') !== APP_VERSION) {
    redirect('setup.php');
}

$page = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'dashboard'));
// حماية من تضمين صفحات المنصة جوه موقع تاني لخداع المستخدم (clickjacking). الصفحات العامة (الفورم، الحجز، صفحة الهبوط، روابط التتبع) مستثناة عشان العملاء يقدروا يضمّنوها في مواقعهم
if (!headers_sent()) {
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!in_array($page, ['lead_form', 'crm_book', 'lp', 'crm_js', 'crm_go', 'crm_t', 'crm_v', 'go', 'share'], true)) { header('X-Frame-Options: SAMEORIGIN'); header("Content-Security-Policy: frame-ancestors 'self'"); }
}
// صفحات اتدمجت في 1.7.0
$merged = ['compose_pro' => 'compose', 'inbox_pro' => 'inbox'];
// تحليلات الرسائل والتقارير بقوا أقسام جوه صفحة التحليلات (الصفحات نفسها بتشتغل جوه الصفحة الموحدة)
if (($page === 'inbox_analytics' || $page === 'reports') && $_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['embed']) && ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe') {
    redirect(url($page, ['embed' => 1] + array_diff_key($_GET, ['p' => 1]))); // رجوع بعد حفظ جوه القسم: يفضل جوه الإطار
}
if (($page === 'inbox_analytics' || $page === 'reports') && $_SERVER['REQUEST_METHOD'] === 'GET' && empty($_GET['embed']) && !array_diff(array_keys($_GET), ['p', 'd', 'days'])) {
    redirect(url('analytics', ['tab' => $page === 'reports' ? 'reports' : 'messages'] + array_intersect_key($_GET, ['d' => 1])));
}
if (isset($merged[$page])) {
    redirect(url($merged[$page], array_diff_key($_GET, ['p' => 1])));
}

$public = ['login', 'register', 'invite', 'pref', 'cron', 'share', 'smsgate_hook', 'zernio_hook', 'crm_hook', 'lead_form', 'crm_t', 'crm_book', 'crm_go', 'crm_v', 'crm_js', 'go', 'lp'];
// علامة «كان مسجّل دخول» (مش سر، مجرد 1): بتتحط طول ما المستخدم داخل وبتتمسح عند الخروج.
// لو موجودة ومفيش مستخدم = الجلسة انتهت فعلًا (مش زائر جديد).
if (current_user() && empty($_COOKIE['zin'])) setcookie('zin', '1', ['expires' => time() + 31536000, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
if (!in_array($page, $public, true) && !current_user()) {
    // الجلسة انتهت: نقول له السبب ونرجعه لنفس الصفحة بعد الدخول
    if (!empty($_COOKIE['zin']) && $page !== 'logout') {
        setcookie('zin', '', ['expires' => time() - 3600, 'path' => '/']);
        flash('info', t('انتهت جلستك، سجّل الدخول مرة أخرى للمتابعة.'));
        if ($_SERVER['REQUEST_METHOD'] === 'GET') $_SESSION['after_login'] = 'index.php?' . http_build_query($_GET);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false || !empty($_SERVER['HTTP_X_REQUESTED_WITH']))) { http_response_code(401); header('Content-Type: application/json; charset=utf-8'); exit(json_encode(['ok' => false, 'error' => t('انتهت جلستك، سجّل الدخول مرة أخرى للمتابعة.'), 'login' => true], JSON_UNESCAPED_UNICODE)); }
    }
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page !== 'smsgate_hook' && $page !== 'zernio_hook' && $page !== 'crm_hook' && $page !== 'lead_form' && $page !== 'crm_book' && $page !== 'lp') {
    check_csrf();
}

// كل الصفحات بتشتغل جوه شركة. لو مالوش شركات، يروح صفحة الشركات (ينشئ واحدة أو يوافق على دعوة)
$noWorkspacePages = ['login', 'register', 'invite', 'pref', 'cron', 'share', 'smsgate_hook', 'zernio_hook', 'crm_hook', 'lead_form', 'crm_t', 'crm_book', 'crm_go', 'crm_v', 'crm_js', 'go', 'lp', 'logout', 'workspaces', 'switch', 'account', 'system', 'admin', 'admin_workspaces', 'admin_users', 'admin_activity', 'admin_compare', 'impersonate_stop'];
if (!in_array($page, $noWorkspacePages, true) && !current_workspace()) {
    redirect(url('workspaces'));
}

// تبويبين على شركتين: الصفحة بتبعت رقم الشركة اللي اتفتحت عليها (X-WS-Id أو _ws)، ولو الشركة اتغيرت من تبويب تاني بنرفض بدل ما نحفظ في الشركة الغلط
if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_workspace() && !in_array($page, ['switch', 'workspaces', 'pref', 'logout', 'login'], true)) {
    $sentWs = (string)($_POST['_ws'] ?? ($_SERVER['HTTP_X_WS_ID'] ?? ''));
    if ($sentWs !== '' && ctype_digit($sentWs) && (int)$sentWs !== (int)ws('id')) {
        $msg = t('تم تغيير الشركة المفتوحة من تبويب آخر. حدّث الصفحة ثم أعد المحاولة؛ لم يُحفظ أي شيء.');
        $wantsJson = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'json') !== false || !empty($_SERVER['HTTP_X_WS_ID']) || isset($_POST['ajax']);
        if ($wantsJson) { http_response_code(409); header('Content-Type: application/json; charset=utf-8'); exit(json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE)); }
        flash('error', $msg); redirect(url($page));
    }
}

// قسم مقفول للشركة دي
// مكتبة الوسائط بتخدم الصور والرفع لباقي الصفحات (المنشورات، المصمم، الجماعي)، فقفلها بيقفل صفحتها بس
$mediaSvc = $page === 'media' && (isset($_GET['file']) || isset($_GET['ajax']) || $_SERVER['REQUEST_METHOD'] === 'POST');
if (current_workspace() && !$mediaSvc && !module_enabled(page_module($page))) {
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

if (!empty($_FILES)) { require_once __DIR__ . '/app/upload_shrink.php'; try { upload_shrink_all(); } catch (Throwable $e) {} }
require $file;
