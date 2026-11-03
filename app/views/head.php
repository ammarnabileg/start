<?php
/** الـ <head> المشترك: الخط والألوان والمكونات (أزرار، كروت، خانات) */
function page_head(string $title): void
{
    ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(session_status() === PHP_SESSION_ACTIVE ? csrf_token() : '') ?>">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>
(function () {
  var brand = {50:'#f1f0ff',100:'#e5e3ff',200:'#cdc9ff',300:'#aaa2ff',400:'#8673fd',500:'#6d4ff7',600:'#5b31ec',700:'#4d24d0',800:'#3f1fa8',900:'#351d86'};
  var ink = {50:'#f7f7f8',100:'#eeeef1',200:'#dcdce2',300:'#c0c0ca',400:'#8e8e9c',500:'#6b6b7a',600:'#53535f',700:'#3f3f49',800:'#26262d',900:'#16161b'};
  tailwind.config = { theme: { extend: {
    fontFamily: { sans: ['"IBM Plex Sans Arabic"', 'system-ui', 'sans-serif'] },
    colors: { brand: brand, ink: ink, indigo: brand, slate: ink }
  } } };
})();
</script>
<style type="text/tailwindcss">
  @layer base { body { @apply bg-ink-50 text-ink-800 antialiased; } }
  @layer components {
  .card { @apply bg-white rounded-2xl border border-ink-200/80 shadow-[0_1px_2px_rgba(22,22,27,.04)]; }
  .input { @apply mt-1.5 w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm placeholder:text-ink-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-100 outline-none transition; }
  .label { @apply text-sm font-semibold text-ink-700; }
  .btn { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:opacity-60; }
  .btn-primary { @apply btn bg-brand-600 text-white hover:bg-brand-700 shadow-sm shadow-brand-600/20; }
  .btn-ghost { @apply btn border border-ink-200 bg-white text-ink-700 hover:bg-ink-50; }
  .link { @apply text-brand-700 font-semibold hover:underline; }
  .badge { @apply inline-flex items-center rounded-full bg-ink-100 px-2 py-0.5 text-xs font-semibold text-ink-600; }
  .badge-green { @apply bg-emerald-50 text-emerald-700; }
  .tab { @apply whitespace-nowrap px-3 py-3 text-sm font-medium text-ink-500 border-b-2 border-transparent hover:text-ink-800; }
  .tab-on { @apply text-ink-900 border-brand-600; }
  .menu { @apply absolute mt-2 w-64 rounded-2xl border border-ink-200 bg-white p-1.5 shadow-xl shadow-ink-900/10 z-50; }
  .menu-item { @apply flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-ink-700 hover:bg-ink-50 w-full text-right; }
  }
  details > summary { list-style: none; } details > summary::-webkit-details-marker { display: none; }
</style>
    <?php
}
