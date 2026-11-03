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
    if (is_admin()) {
        echo '<p class="text-sm">روح لـ <a class="underline font-semibold" href="' . e(url('settings')) . '">الإعدادات</a> وضيف مفتاح الـ API.</p>';
    } else {
        echo '<p class="text-sm">اطلب من مدير النظام يضيف مفتاح Zernio API من صفحة الإعدادات.</p>';
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
