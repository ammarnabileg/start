<?php
/**
 * الـ <head> المشترك: الخط (Alexandria)، الألوان، والمكونات.
 * الألوان متعرّفة كمتغيرات CSS، فالوضع الفاتح والداكن بيستخدموا نفس الكلاسات.
 * الفاتح هو الأساسي. الداكن بيتفعل بكلاس dark على <html>.
 */
function page_head(string $title): void
{
    ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="<?= theme() ?>">
<meta name="csrf-token" content="<?= e(session_status() === PHP_SESSION_ACTIVE ? csrf_token() : '') ?>">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--ink-50:245 247 251;--ink-100:237 241 248;--ink-200:221 227 238;--ink-300:195 205 223;--ink-400:138 151 176;--ink-500:95 109 136;--ink-600:69 82 109;--ink-700:49 61 87;--ink-800:29 40 66;--ink-900:13 22 48;--brand-50:238 244 255;--brand-100:219 231 255;--brand-200:188 211 255;--brand-300:143 181 255;--brand-400:91 142 249;--brand-500:59 111 240;--brand-600:37 87 224;--brand-700:29 69 189;--brand-800:28 58 150;--brand-900:27 52 119;--cyan-50:236 254 255;--cyan-100:207 250 254;--cyan-200:165 243 252;--cyan-300:103 232 249;--cyan-400:34 211 238;--cyan-500:6 182 212;--cyan-600:8 145 178;--cyan-700:14 116 144;--cyan-800:21 94 117;--cyan-900:22 78 99;--lime-50:242 253 228;--lime-100:226 251 196;--lime-200:198 244 143;--lime-300:163 232 79;--lime-400:132 216 31;--lime-500:101 184 15;--lime-600:76 146 10;--lime-700:60 112 12;--lime-800:51 90 16;--lime-900:44 76 18;--red-50:254 242 242;--red-100:254 226 226;--red-200:254 202 202;--red-300:252 165 165;--red-400:248 113 113;--red-500:239 68 68;--red-600:220 38 38;--red-700:185 28 28;--red-800:153 27 27;--red-900:127 29 29;--amber-50:255 251 235;--amber-100:254 243 199;--amber-200:253 230 138;--amber-300:252 211 77;--amber-400:251 191 36;--amber-500:245 158 11;--amber-600:217 119 6;--amber-700:180 83 9;--amber-800:146 64 14;--amber-900:120 53 15;--surface:255 255 255;}
html.dark{--ink-50:10 16 32;--ink-100:16 26 51;--ink-200:28 40 70;--ink-300:43 58 94;--ink-400:93 109 144;--ink-500:133 148 178;--ink-600:167 179 204;--ink-700:198 207 225;--ink-800:223 229 241;--ink-900:242 245 251;--brand-50:18 34 74;--brand-100:23 43 92;--brand-200:33 57 122;--brand-300:47 79 163;--brand-400:75 116 224;--brand-500:107 144 245;--brand-600:59 111 240;--brand-700:157 184 255;--brand-800:188 208 255;--brand-900:219 230 255;--cyan-50:8 47 58;--cyan-100:11 60 74;--cyan-200:15 84 102;--cyan-300:18 122 146;--cyan-400:15 165 196;--cyan-500:34 211 238;--cyan-600:34 211 238;--cyan-700:103 232 249;--cyan-800:165 243 252;--cyan-900:207 250 254;--lime-50:22 38 11;--lime-100:29 51 13;--lime-200:44 77 19;--lime-300:63 110 23;--lime-400:101 184 15;--lime-500:132 216 31;--lime-600:163 232 79;--lime-700:190 242 100;--lime-800:217 249 157;--lime-900:236 252 203;--red-50:42 18 22;--red-100:56 22 27;--red-200:90 31 39;--red-300:127 41 51;--red-400:194 65 76;--red-500:239 91 102;--red-600:248 113 113;--red-700:252 165 165;--red-800:254 202 202;--red-900:254 226 226;--amber-50:42 33 16;--amber-100:56 43 18;--amber-200:90 68 22;--amber-300:127 95 27;--amber-400:194 142 34;--amber-500:245 181 50;--amber-600:251 191 36;--amber-700:252 211 77;--amber-800:253 230 138;--amber-900:254 243 199;--surface:16 26 51;}
</style>
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>window.T = <?= json_encode([
    'working' => t('جاري التنفيذ...'), 'comment' => t('تعليق'), 'copied' => t('اتنسخ ✓'),
    'schedule' => t('جدولة المنشور'), 'now' => t('انشر دلوقتي'), 'draft' => t('حفظ المسودة'), 'queue' => t('ضيف للطابور'), 'sending' => t('جاري الإرسال...'), 'notSent' => t('ما اتبعتتش · اتأكد من النت وجرب تاني'), 'liveFailed' => t('حصلت مشكلة في الاتصال. جرب تاني.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php if (function_exists('ai_presets') && session_status() === PHP_SESSION_ACTIVE && current_user() && current_workspace() && can('ai.use') && ai_connection()): ?>
<script>window.AI_PRESETS = <?= json_encode(['items' => array_map(function ($p) { return $p['label']; }, array_filter(ai_presets(), function ($k) { return ai_feature_on($k); }, ARRAY_FILTER_USE_KEY)), 'title' => t('أوامر الذكاء الاصطناعي'), 'use' => t('استخدم هذا النص'), 'copy' => t('نسخ'), 'insights' => array_map(function ($i) { return ['label' => $i['label'], 'icon' => $i['icon'], 'input' => $i['input'] ?? '', 'csv' => !empty($i['csv']), 'page' => !empty($i['page'])]; }, array_filter(ai_insights(), function ($k) { return ai_feature_on($k); }, ARRAY_FILTER_USE_KEY)), 'run' => t('تشغيل'), 'csvBtn' => t('تحميل CSV للنشر الجماعي'), 'rowsTxt' => t('منشور'), 'thinking' => t('جاري التحليل من بياناتك...'), 'aiTitle' => t('تحليلات الذكاء الاصطناعي')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php endif; ?>
<script>
(function () {
  var v = function (n) { var o = {}; [50,100,200,300,400,500,600,700,800,900].forEach(function (k) { o[k] = 'rgb(var(--' + n + '-' + k + ') / <alpha-value>)'; }); return o; };
  tailwind.config = { darkMode: 'class', theme: { extend: {
    fontFamily: { sans: ['Alexandria', 'system-ui', 'sans-serif'] },
    colors: { white: 'rgb(var(--surface) / <alpha-value>)', ink: v('ink'), brand: v('brand'), cyan: v('cyan'), lime: v('lime'), red: v('red'), amber: v('amber'), slate: v('ink'), gray: v('ink'), indigo: v('brand'), blue: v('brand'), emerald: v('lime'), green: v('lime'), rose: v('red'), orange: v('amber') }
  } } };
})();
</script>
<style type="text/tailwindcss">
  @layer base {
    body { @apply bg-ink-50 text-ink-800 antialiased font-light; }
    b, strong { @apply font-semibold; }
    input, textarea, select { color-scheme: inherit; }
    input:not([type=checkbox]):not([type=radio]):not([type=file]), textarea, select { @apply bg-white text-ink-900; }
    input[type=file] { @apply text-sm text-ink-600 file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 file:cursor-pointer; }
    input[type=checkbox], input[type=radio] { accent-color: rgb(var(--brand-600)); }
  }
  @layer components {
    .card { @apply bg-white rounded-2xl border border-ink-200 shadow-[0_1px_2px_rgba(13,22,48,.04)]; }
    .input { @apply mt-1.5 w-full rounded-xl border border-ink-200 px-3.5 py-2.5 text-sm placeholder:text-ink-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 outline-none transition; }
    .label { @apply text-sm font-medium text-ink-700; }
    .btn { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition disabled:opacity-60; }
    .btn-primary { @apply btn text-[#fff] bg-gradient-to-l from-brand-600 to-cyan-600 hover:brightness-110 shadow-sm shadow-brand-600/25; }
    .btn-ghost { @apply btn bg-ink-100 text-ink-900 border border-ink-200 hover:bg-ink-200; }
    .link { @apply text-brand-600 dark:text-brand-700 font-medium hover:underline underline-offset-4; }
    .badge { @apply inline-flex items-center rounded-lg bg-ink-100 px-2 py-0.5 text-xs font-medium text-ink-600 dark:bg-ink-200 dark:text-ink-800; }
    .badge-green { @apply bg-lime-100 text-lime-700; }
    .nav-item { @apply flex items-center gap-3 rounded-xl px-3 py-2 text-[15px] text-ink-600 hover:bg-ink-100 hover:text-ink-900; }
    .nav-on { @apply bg-brand-50 text-brand-700 font-medium; }
    .nav-label { @apply px-3 pt-5 pb-1.5 text-xs font-medium uppercase tracking-wide text-ink-400; }
    .menu { @apply absolute w-64 rounded-2xl border border-ink-200 bg-white p-1.5 shadow-2xl shadow-ink-900/15 z-50; }
    .menu-item { @apply flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-ink-700 hover:bg-ink-100 hover:text-ink-900 w-full text-start; }
    .seg { @apply flex rounded-xl bg-ink-100 p-0.5 text-xs; }
    .seg a { @apply px-2.5 py-1 rounded-lg text-ink-500 hover:text-ink-900; }
    .chip { @apply inline-flex items-center gap-2 rounded-full border border-ink-200 bg-white px-3 py-1.5 text-sm text-ink-600 whitespace-nowrap hover:bg-ink-50 transition; }
    .chip.on { @apply border-brand-600 bg-brand-50 text-brand-700 font-medium; }
    .seg a.on { @apply bg-brand-600 text-[#fff] shadow-sm font-medium; }
  }
  #sidebar.sb-open { transform: none !important; }
  html, body { overflow-x: clip; }
  details > summary { list-style: none; } details > summary::-webkit-details-marker { display: none; }
  /* الأزرار القديمة بخلفية اللون الأساسي: نصها أبيض في الوضعين */
  .bg-indigo-600.text-white, .bg-brand-600.text-white, .bg-rose-600.text-white, .bg-green-600.text-white, .bg-red-600.text-white { color: #fff; }
</style>
    <?php
}

/** وسم <html> باللغة والاتجاه والوضع */
function html_open(): string
{
    return '<html lang="' . lang() . '" dir="' . (is_rtl() ? 'rtl' : 'ltr') . '" class="' . (theme() === 'dark' ? 'dark' : '') . '">';
}

/** زرارين اللغة والوضع */
function pref_switch(): string
{
    $l = lang(); $th = theme();
    return '<div class="seg"><a class="' . ($l === 'en' ? 'on' : '') . '" href="' . e(url('pref', ['lang' => 'en'])) . '">EN</a><a class="' . ($l === 'ar' ? 'on' : '') . '" href="' . e(url('pref', ['lang' => 'ar'])) . '">ع</a></div>'
         . '<div class="seg"><a class="' . ($th === 'light' ? 'on' : '') . '" href="' . e(url('pref', ['theme' => 'light'])) . '" title="Light">☀</a><a class="' . ($th === 'dark' ? 'on' : '') . '" href="' . e(url('pref', ['theme' => 'dark'])) . '" title="Dark">☾</a></div>';
}
