<?php
/**
 * Main HR application layout (recruiter workspace).
 * Receives: $content (rendered inner HTML), $title, $active (active nav key),
 *           $__user (current user array|null), $csrf (CSRF token string).
 */
$__lang = $_COOKIE['lang'] ?? 'en';
$__dir  = $__lang === 'ar' ? 'rtl' : 'ltr';
$__csrf = $csrf ?? ($csrf_token ?? '');
$__user = $__user ?? null;

$__fn = trim((string)($__user['first_name'] ?? ''));
$__ln = trim((string)($__user['last_name'] ?? ''));
$__fullName = trim($__fn . ' ' . $__ln);
if ($__fullName === '') { $__fullName = (string)($__user['email'] ?? 'Guest'); }
$__initials = strtoupper((($__fn !== '' ? $__fn[0] : '') . ($__ln !== '' ? $__ln[0] : '')));
if ($__initials === '') { $__initials = strtoupper(substr((string)($__user['email'] ?? 'U'), 0, 1)); }

$__active = $active ?? '';

/**
 * Render a single sidebar nav item.
 * @param string $key   active key to compare against $__active
 * @param string $href  link target
 * @param string $label translated label
 * @param string $icon  inline <path>/<svg-body> markup
 */
$__nav = function (string $key, string $href, string $label, string $icon) use ($__active): string {
    $isActive = $__active === $key;
    $cls = 'nav-pill' . ($isActive ? ' active' : '');
    $aria = $isActive ? ' aria-current="page"' : '';
    return '<a href="' . e($href) . '" data-nav class="' . $cls . '"' . $aria . '>'
         . '<svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">' . $icon . '</svg>'
         . '<span class="truncate">' . e($label) . '</span>'
         . '</a>';
};

// Heroicons-style (outline) icon bodies.
$__icons = [
    'dashboard'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 8.25V6zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 018.25 20.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />',
    'jobs'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V14.15M16.5 6.75V5.25a2.25 2.25 0 00-2.25-2.25h-4.5A2.25 2.25 0 007.5 5.25v1.5m13.5 0H3.75a1.5 1.5 0 00-1.5 1.5v3.026c0 .55.27 1.06.71 1.39l.01.01a17.93 17.93 0 0019.06 0l.01-.01c.44-.33.71-.84.71-1.39V8.25a1.5 1.5 0 00-1.5-1.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75h.008v.008H12v-.008z" />',
    'candidates' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />',
    'interviews' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />',
    'pipeline'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />',
    'offers'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
    'talent'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />',
    'avatars'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" />',
    'users'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />',
    'settings'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.759 6.759 0 010-.256c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
];
?>
<!doctype html>
<html lang="<?= e($__lang) ?>" dir="<?= e($__dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($__csrf) ?>">
  <title><?= e($title ?? app_lang('app_name')) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'Tajawal', 'Cairo', 'ui-sans-serif', 'system-ui', 'sans-serif'],
          },
          colors: { brand: { DEFAULT: '#7C3AED', dark: '#5B21B6', deep: '#1E1B4B' }, accent: '#FBBF24' },
        },
      },
    };
  </script>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">

  <!-- Mobile sidebar overlay (click closes) -->
  <div id="sidebar-overlay" class="fixed inset-0 z-30 bg-gray-900/40 backdrop-blur-sm hidden md:hidden"></div>

  <!-- ============ Sidebar ============ -->
  <aside id="app-sidebar"
         class="fixed inset-y-0 <?= $__dir === 'rtl' ? 'right-0' : 'left-0' ?> z-40 w-64 bg-white border-<?= $__dir === 'rtl' ? 'l' : 'r' ?> border-gray-200 flex flex-col transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0 rtl:translate-x-full rtl:md:translate-x-0">

    <!-- Brand -->
    <div class="h-16 flex items-center gap-3 px-5 border-b border-gray-100">
      <div class="w-10 h-10 rounded-xl gradient-brand flex items-center justify-center text-white font-extrabold text-sm tracking-tight shadow-sm">AR</div>
      <div class="leading-tight">
        <p class="font-bold text-gray-900"><?= e(app_lang('app_name')) ?></p>
        <p class="text-[11px] font-medium text-gray-400"><?= e(app_lang('recruiter_workspace')) ?></p>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
      <?= $__nav('dashboard',  '/dashboard',   app_lang('nav_dashboard'),   $__icons['dashboard']) ?>
      <?= $__nav('jobs',       '/jobs',        app_lang('nav_jobs'),        $__icons['jobs']) ?>
      <?= $__nav('candidates', '/candidates',  app_lang('nav_candidates'),  $__icons['candidates']) ?>
      <?= $__nav('interviews', '/interviews',  app_lang('nav_interviews'),  $__icons['interviews']) ?>
      <?= $__nav('pipeline',   '/pipeline',    app_lang('nav_pipeline'),    $__icons['pipeline']) ?>
      <?= $__nav('offers',     '/offers',      app_lang('nav_offers'),      $__icons['offers']) ?>
      <?= $__nav('talent-pool','/talent-pool', app_lang('nav_talent_pool'), $__icons['talent']) ?>

      <p class="px-3 pt-5 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400"><?= e(app_lang('general')) ?></p>
      <?= $__nav('avatars',    '/avatars',     app_lang('nav_avatars'),     $__icons['avatars']) ?>
      <?= $__nav('users',      '/users',       app_lang('nav_users'),       $__icons['users']) ?>
      <?= $__nav('settings',   '/settings',    app_lang('nav_settings'),    $__icons['settings']) ?>
    </nav>

    <!-- Sidebar footer (mini user card) -->
    <div class="px-3 py-3 border-t border-gray-100">
      <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 bg-gray-50">
        <div class="w-9 h-9 rounded-full gradient-accent flex items-center justify-center text-brand-deep font-bold text-xs shrink-0"><?= e($__initials) ?></div>
        <div class="min-w-0 leading-tight">
          <p class="text-sm font-semibold text-gray-900 truncate"><?= e($__fullName) ?></p>
          <p class="text-[11px] text-gray-400 truncate"><?= e($__user['email'] ?? '') ?></p>
        </div>
      </div>
    </div>
  </aside>

  <!-- ============ Top bar ============ -->
  <header class="sticky top-0 z-20 h-16 bg-white/90 backdrop-blur border-b border-gray-200 md:ml-64 rtl:md:ml-0 rtl:md:mr-64 flex items-center gap-3 px-4 sm:px-6">
    <!-- Hamburger (mobile) -->
    <button id="sidebar-toggle" type="button" aria-label="<?= e(app_lang('menu')) ?>"
            class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 transition">
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
    </button>

    <!-- Page title (desktop) -->
    <h1 class="hidden sm:block text-base font-semibold text-gray-800 truncate"><?= e($title ?? app_lang('app_name')) ?></h1>

    <div class="flex-1"></div>

    <!-- Language switcher -->
    <div class="hidden sm:flex items-center rounded-full border border-gray-200 bg-white p-0.5 text-xs font-semibold">
      <button type="button" data-lang="en" class="px-3 py-1 rounded-full transition <?= $__lang === 'en' ? 'bg-brand text-white' : 'text-gray-500 hover:text-gray-800' ?>">EN</button>
      <button type="button" data-lang="ar" class="px-3 py-1 rounded-full transition <?= $__lang === 'ar' ? 'bg-brand text-white' : 'text-gray-500 hover:text-gray-800' ?>">عربي</button>
    </div>

    <!-- Notifications -->
    <button type="button" aria-label="<?= e(app_lang('notifications')) ?>"
            class="relative inline-flex items-center justify-center w-10 h-10 rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition">
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
      <span class="absolute top-2 <?= $__dir === 'rtl' ? 'left-2.5' : 'right-2.5' ?> w-2 h-2 rounded-full bg-brand ring-2 ring-white"></span>
    </button>

    <!-- User menu -->
    <div class="relative">
      <button type="button" data-dropdown="user-menu" aria-label="<?= e(app_lang('my_account')) ?>"
              class="flex items-center gap-2 rounded-full hover:bg-gray-100 transition py-1 ps-1 pe-2">
        <span class="w-9 h-9 rounded-full gradient-brand flex items-center justify-center text-white font-bold text-xs"><?= e($__initials) ?></span>
        <span class="hidden lg:block text-sm font-medium text-gray-700 max-w-[140px] truncate"><?= e($__fullName) ?></span>
        <svg class="hidden lg:block w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
      </button>

      <div id="user-menu" data-dropdown-menu
           class="hidden absolute <?= $__dir === 'rtl' ? 'left-0' : 'right-0' ?> mt-2 w-60 rounded-xl bg-white shadow-xl ring-1 ring-gray-200 py-2 z-50">
        <div class="px-4 py-2 border-b border-gray-100">
          <p class="text-sm font-semibold text-gray-900 truncate"><?= e($__fullName) ?></p>
          <p class="text-xs text-gray-400 truncate"><?= e($__user['email'] ?? '') ?></p>
          <?php if (!empty($__user['is_super_admin'])): ?>
            <span class="mt-1.5 badge badge-violet"><?= e(app_lang('super_admin')) ?></span>
          <?php endif; ?>
        </div>
        <a href="/settings" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition">
          <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
          <?= e(app_lang('nav_settings')) ?>
        </a>
        <div class="my-1 border-t border-gray-100"></div>
        <a href="/logout" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
          <?= e(app_lang('sign_out')) ?>
        </a>
      </div>
    </div>
  </header>

  <!-- ============ Main content ============ -->
  <main class="md:ml-64 rtl:md:ml-0 rtl:md:mr-64 p-4 sm:p-6 fade-in">
    <?= $content ?? '' ?>
  </main>

  <div id="toast-root"></div>

  <script>
    // Close mobile sidebar when tapping the overlay.
    (function () {
      var overlay = document.getElementById('sidebar-overlay');
      var sidebar = document.getElementById('app-sidebar');
      var toggle = document.getElementById('sidebar-toggle');
      function isOpen() { return sidebar && !sidebar.classList.contains('-translate-x-full') && !sidebar.classList.contains('rtl:translate-x-full'); }
      function sync() { if (overlay) overlay.classList.toggle('hidden', !sidebar || sidebar.classList.contains('-translate-x-full')); }
      if (toggle) toggle.addEventListener('click', function () { setTimeout(sync, 0); });
      if (overlay && sidebar) overlay.addEventListener('click', function () { sidebar.classList.add('-translate-x-full'); overlay.classList.add('hidden'); });
    })();
  </script>
</body>
</html>
