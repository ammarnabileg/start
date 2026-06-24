<?php
/**
 * Super-admin layout. Same structure as the HR app but a DARK slate sidebar
 * to visually distinguish the platform console from the (white) HR workspace.
 * Receives: $content, $title, $active, $__user, $csrf.
 */
$__lang = $_COOKIE['lang'] ?? 'en';
$__dir  = $__lang === 'ar' ? 'rtl' : 'ltr';
$__csrf = $csrf ?? ($csrf_token ?? '');
$__user = $__user ?? null;

$__fn = trim((string)($__user['first_name'] ?? ''));
$__ln = trim((string)($__user['last_name'] ?? ''));
$__fullName = trim($__fn . ' ' . $__ln);
if ($__fullName === '') { $__fullName = (string)($__user['email'] ?? 'Admin'); }
$__initials = strtoupper((($__fn !== '' ? $__fn[0] : '') . ($__ln !== '' ? $__ln[0] : '')));
if ($__initials === '') { $__initials = strtoupper(substr((string)($__user['email'] ?? 'A'), 0, 1)); }

$__active = $active ?? '';

$__nav = function (string $key, string $href, string $label, string $icon) use ($__active): string {
    $isActive = $__active === $key;
    // Dark sidebar: base text light slate; active = violet pill (nav-pill .active handles bg/text).
    $base = $isActive
        ? 'nav-pill active'
        : 'nav-pill text-slate-300 hover:!bg-slate-800 hover:!text-white';
    $aria = $isActive ? ' aria-current="page"' : '';
    return '<a href="' . e($href) . '" data-nav class="' . $base . '"' . $aria . '>'
         . '<svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">' . $icon . '</svg>'
         . '<span class="truncate">' . e($label) . '</span>'
         . '</a>';
};

$__icons = [
    'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 018.25 20.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />',
    'companies' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />',
    'analytics' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />',
    'terminal'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />',
];
?>
<!doctype html>
<html lang="<?= e($__lang) ?>" dir="<?= e($__dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($__csrf) ?>">
  <title><?= e($title ?? app_lang('admin_console')) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        fontFamily: { sans: ['Inter', 'Tajawal', 'Cairo', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
        colors: { brand: { DEFAULT: '#7C3AED', dark: '#5B21B6', deep: '#1E1B4B' }, accent: '#FBBF24' },
      } },
    };
  </script>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">

  <!-- Mobile sidebar overlay -->
  <div id="sidebar-overlay" class="fixed inset-0 z-30 bg-gray-900/50 backdrop-blur-sm hidden md:hidden"></div>

  <!-- ============ Dark sidebar ============ -->
  <aside id="app-sidebar"
         class="fixed inset-y-0 <?= $__dir === 'rtl' ? 'right-0' : 'left-0' ?> z-40 w-64 bg-slate-900 text-slate-200 flex flex-col transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0 rtl:translate-x-full rtl:md:translate-x-0">

    <!-- Brand -->
    <div class="h-16 flex items-center gap-3 px-5 border-b border-slate-800">
      <div class="w-10 h-10 rounded-xl gradient-brand flex items-center justify-center text-white font-extrabold text-sm tracking-tight shadow-sm">AR</div>
      <div class="leading-tight">
        <p class="font-bold text-white"><?= e(app_lang('app_name')) ?></p>
        <p class="text-[11px] font-medium text-slate-400"><?= e(app_lang('admin_console')) ?></p>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
      <?= $__nav('dashboard',  '/admin/dashboard',    app_lang('nav_dashboard'),  $__icons['dashboard']) ?>
      <?= $__nav('companies',  '/admin/companies',    app_lang('nav_companies'),  $__icons['companies']) ?>
      <?= $__nav('ai-analytics', '/admin/ai-analytics', app_lang('nav_analytics'), $__icons['analytics']) ?>
      <?= $__nav('terminal',   '/admin/terminal',     app_lang('nav_terminal'),   $__icons['terminal']) ?>

      <div class="pt-4 mt-4 border-t border-slate-800">
        <a href="/logout" data-nav class="nav-pill text-slate-300 hover:!bg-red-500/15 hover:!text-red-300">
          <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
          <span class="truncate"><?= e(app_lang('sign_out')) ?></span>
        </a>
      </div>
    </nav>

    <!-- Sidebar footer -->
    <div class="px-3 py-3 border-t border-slate-800">
      <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 bg-slate-800/60">
        <div class="w-9 h-9 rounded-full gradient-accent flex items-center justify-center text-brand-deep font-bold text-xs shrink-0"><?= e($__initials) ?></div>
        <div class="min-w-0 leading-tight">
          <p class="text-sm font-semibold text-white truncate"><?= e($__fullName) ?></p>
          <p class="text-[11px] text-slate-400 truncate"><?= e(app_lang('super_admin')) ?></p>
        </div>
      </div>
    </div>
  </aside>

  <!-- ============ Top bar ============ -->
  <header class="sticky top-0 z-20 h-16 bg-white/90 backdrop-blur border-b border-gray-200 md:ml-64 rtl:md:ml-0 rtl:md:mr-64 flex items-center gap-3 px-4 sm:px-6">
    <button id="sidebar-toggle" type="button" aria-label="<?= e(app_lang('menu')) ?>"
            class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 transition">
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
    </button>

    <div class="flex items-center gap-2">
      <h1 class="hidden sm:block text-base font-semibold text-gray-800 truncate"><?= e($title ?? app_lang('admin_console')) ?></h1>
      <span class="badge badge-violet"><?= e(app_lang('super_admin')) ?></span>
    </div>

    <div class="flex-1"></div>

    <!-- Language switcher -->
    <div class="hidden sm:flex items-center rounded-full border border-gray-200 bg-white p-0.5 text-xs font-semibold">
      <button type="button" data-lang="en" class="px-3 py-1 rounded-full transition <?= $__lang === 'en' ? 'bg-brand text-white' : 'text-gray-500 hover:text-gray-800' ?>">EN</button>
      <button type="button" data-lang="ar" class="px-3 py-1 rounded-full transition <?= $__lang === 'ar' ? 'bg-brand text-white' : 'text-gray-500 hover:text-gray-800' ?>">عربي</button>
    </div>

    <!-- User menu -->
    <div class="relative">
      <button type="button" data-dropdown="admin-user-menu" aria-label="<?= e(app_lang('my_account')) ?>"
              class="flex items-center gap-2 rounded-full hover:bg-gray-100 transition py-1 ps-1 pe-2">
        <span class="w-9 h-9 rounded-full gradient-brand flex items-center justify-center text-white font-bold text-xs"><?= e($__initials) ?></span>
        <span class="hidden lg:block text-sm font-medium text-gray-700 max-w-[140px] truncate"><?= e($__fullName) ?></span>
        <svg class="hidden lg:block w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
      </button>

      <div id="admin-user-menu" data-dropdown-menu
           class="hidden absolute <?= $__dir === 'rtl' ? 'left-0' : 'right-0' ?> mt-2 w-60 rounded-xl bg-white shadow-xl ring-1 ring-gray-200 py-2 z-50">
        <div class="px-4 py-2 border-b border-gray-100">
          <p class="text-sm font-semibold text-gray-900 truncate"><?= e($__fullName) ?></p>
          <p class="text-xs text-gray-400 truncate"><?= e($__user['email'] ?? '') ?></p>
        </div>
        <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition">
          <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
          <?= e(app_lang('admin_console')) ?>
        </a>
        <div class="my-1 border-t border-gray-100"></div>
        <a href="/logout" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
          <?= e(app_lang('sign_out')) ?>
        </a>
      </div>
    </div>
  </header>

  <!-- ============ Main ============ -->
  <main class="md:ml-64 rtl:md:ml-0 rtl:md:mr-64 p-4 sm:p-6 fade-in">
    <?= $content ?? '' ?>
  </main>

  <div id="toast-root"></div>

  <script>
    (function () {
      var overlay = document.getElementById('sidebar-overlay');
      var sidebar = document.getElementById('app-sidebar');
      var toggle = document.getElementById('sidebar-toggle');
      function sync() { if (overlay) overlay.classList.toggle('hidden', !sidebar || sidebar.classList.contains('-translate-x-full')); }
      if (toggle) toggle.addEventListener('click', function () { setTimeout(sync, 0); });
      if (overlay && sidebar) overlay.addEventListener('click', function () { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); });
    })();
  </script>
</body>
</html>
