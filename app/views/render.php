<?php
/** يلف محتوى الصفحة بالتصميم العام */
function render(string $title, string $active, callable $body): void
{
    ob_start();
    $body();
    $content = ob_get_clean();
    require __DIR__ . '/layout.php';
    exit;
}

/** شكل موحد لصندوق خطأ */
function error_box(string $msg): void
{
    echo '<div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 text-sm">' . e($msg) . '</div>';
}

function no_api_box(): void
{
    echo '<div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-5">';
    echo '<p class="font-bold mb-1">البرنامج لسه مش مربوط بـ Zernio</p>';
    if (is_ws_owner()) {
        echo '<p class="text-sm">روح <a class="underline font-semibold" href="' . e(url('settings')) . '">إعدادات الشركة</a> وضيف مفتاح Zernio API.</p>';
    } else {
        echo '<p class="text-sm">اطلب من صاحب الشركة يضيف مفتاح Zernio API.</p>';
    }
    echo '</div>';
}

function avatar(?string $src, string $name, string $size = 'w-10 h-10'): string
{
    if ($src) {
        return '<img src="' . e($src) . '" alt="" class="' . $size . ' rounded-full object-cover bg-slate-200" loading="lazy" referrerpolicy="no-referrer">';
    }
    $letter = mb_substr(trim($name) ?: '?', 0, 1);
    return '<div class="' . $size . ' rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">' . e($letter) . '</div>';
}

function card_open(string $extra = ''): string
{
    return '<div class="bg-white rounded-xl border border-slate-200 ' . $extra . '">';
}

/** صفحة بسيطة في النص (الدخول، التسجيل، الدعوة) */
function auth_page(string $title, callable $body): void
{
    $siteName = setting('site_name', 'لوحة النشر');
    ?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Cairo','sans-serif']}}}}</script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-sm bg-white border border-slate-200 rounded-2xl p-8 shadow-sm space-y-5">
    <div class="text-center">
      <h1 class="text-2xl font-bold text-indigo-700"><?= e($siteName) ?></h1>
      <p class="text-slate-500 text-sm mt-1"><?= e($title) ?></p>
    </div>
    <?php foreach (take_flashes() as $f): $cls = $f['type'] === 'error' ? 'bg-red-50 text-red-800 border-red-200' : 'bg-green-50 text-green-800 border-green-200'; ?>
      <div class="border rounded-lg p-3 text-sm <?= $cls ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <?php $body(); ?>
  </div>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
<?php
    exit;
}

function auth_input(string $name, string $label, string $type = 'text', string $value = '', bool $ltr = false): string
{
    return '<label class="block"><span class="text-sm font-semibold">' . e($label) . '</span>'
        . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" required'
        . ($type === 'password' ? ' minlength="8"' : '')
        . ' class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none"' . ($ltr ? ' dir="ltr"' : '') . '></label>';
}

/** بعد الدخول: لو كان جاي من لينك دعوة، رجّعه ليه */
function after_login_redirect(): void
{
    if (!empty($_SESSION['invite_token'])) {
        redirect(url('invite', ['t' => $_SESSION['invite_token']]));
    }
    redirect(url('dashboard'));
}
