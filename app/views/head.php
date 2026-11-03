<?php
/**
 * الـ <head> المشترك: الخطوط والألوان والمكونات.
 * ثيم داكن دافي. الألوان الفاتحة القديمة في الصفحات (white/slate/indigo...) متحولة هنا لدرجات داكنة،
 * فكل الصفحات بتاخد الشكل الجديد من غير ما نعدل كل صفحة.
 */
function page_head(string $title): void
{
    ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<meta name="csrf-token" content="<?= e(session_status() === PHP_SESSION_ACTIVE ? csrf_token() : '') ?>">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Noto+Naskh+Arabic:wght@500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>
(function () {
  // درجات الرمادي الدافي: 50 أغمق خلفية ← 900 أفتح نص
  var ink = {50:'#1f1e1d',100:'#262624',200:'#3a3936',300:'#4d4c48',400:'#86857f',500:'#a3a29c',600:'#bfbdb6',700:'#d6d4cc',800:'#ebe9e1',900:'#faf9f5'};
  // اللون الأساسي: فاتح (الزرار الأساسي أبيض دافي والنص عليه غامق)
  var brand = {50:'#2c2b29',100:'#34332f',200:'#45443f',300:'#6b6a64',400:'#c9c7bf',500:'#e2e0d8',600:'#f0eee6',700:'#f5f4ee',800:'#faf9f5',900:'#ffffff'};
  var tint = function (bg, bd, fg) { return {50:bg,100:bg,200:bd,300:bd,500:fg,600:fg,700:fg,800:fg,900:fg}; };
  tailwind.config = { theme: { extend: {
    fontFamily: { sans: ['"IBM Plex Sans Arabic"', 'system-ui', 'sans-serif'], serif: ['"Noto Naskh Arabic"', 'Georgia', 'serif'] },
    colors: {
      white: '#1f1e1d',
      ink: ink, slate: ink, gray: ink, brand: brand, indigo: brand,
      red: tint('#3b1f1d', '#6b322d', '#f2a59c'), rose: tint('#3b1f22', '#6b3238', '#e5484d'),
      green: tint('#1c2e22', '#2f5a3c', '#8fd3a5'), emerald: tint('#1c2e22', '#2f5a3c', '#8fd3a5'),
      amber: tint('#33291a', '#6b5530', '#f0c27b'), orange: tint('#33251a', '#6b4a30', '#f0a97b'),
      blue: tint('#1b2636', '#2f4766', '#8ab4f8')
    }
  } } };
})();
</script>
<style type="text/tailwindcss">
  @layer base {
    body { @apply bg-ink-50 text-ink-800 antialiased; }
    h1, h2, .serif { font-family: "Noto Naskh Arabic", Georgia, serif; }
    ::selection { background: #4d4c48; }
    input, textarea, select { color-scheme: dark; }
    input:not([type=checkbox]):not([type=radio]):not([type=file]), textarea, select { background-color: #262624; color: #faf9f5; }
  }
  @layer components {
    .card { @apply bg-ink-50 rounded-xl border border-ink-200; }
    .input { @apply mt-1.5 w-full rounded-lg border border-ink-200 bg-ink-100 px-3.5 py-2.5 text-sm text-ink-900 placeholder:text-ink-400 focus:border-ink-400 focus:ring-2 focus:ring-ink-300/40 outline-none transition; }
    .label { @apply text-sm font-medium text-ink-700; }
    .btn { @apply inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition disabled:opacity-60; }
    .btn-primary { @apply btn bg-ink-900 text-ink-50 hover:bg-ink-800; }
    .btn-ghost { @apply btn bg-ink-200/70 text-ink-900 hover:bg-ink-200; }
    .link { @apply text-blue-500 hover:underline underline-offset-4; }
    .badge { @apply inline-flex items-center rounded-md bg-ink-200 px-2 py-0.5 text-xs font-medium text-ink-700; }
    .badge-green { @apply bg-emerald-50 text-emerald-600; }
    .nav-item { @apply flex items-center gap-3 rounded-lg px-3 py-2 text-[15px] text-ink-700 hover:bg-ink-100 hover:text-ink-900; }
    .nav-on { @apply bg-ink-200/70 text-ink-900; }
    .nav-label { @apply px-3 pt-5 pb-1.5 text-[13px] text-ink-400; }
    .menu { @apply absolute w-64 rounded-xl border border-ink-200 bg-ink-100 p-1.5 shadow-2xl shadow-black/50 z-50; }
    .menu-item { @apply flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-200/60 hover:text-ink-900 w-full text-right; }
  }
  details > summary { list-style: none; } details > summary::-webkit-details-marker { display: none; }
  /* أي زرار قديم بخلفية اللون الأساسي ياخد نص غامق */
  .bg-indigo-600.text-white, .bg-brand-600.text-white { color: #1f1e1d; }
</style>
    <?php
}
