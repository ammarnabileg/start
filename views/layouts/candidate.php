<?php
/**
 * Candidate-facing portal layout. Light, friendly, simple top header + nav.
 * Receives: $content, $title, $active, $csrf.
 * Optional: $company (array with 'name','logo_url') for branding.
 */
$__lang = $_COOKIE['lang'] ?? 'en';
$__dir  = $__lang === 'ar' ? 'rtl' : 'ltr';
$__csrf = $csrf ?? ($csrf_token ?? '');
$__active = $active ?? '';

$__company     = $company ?? null;
$__companyName = $__company['name'] ?? app_lang('app_name');
$__companyLogo = $__company['logo_url'] ?? '';

$__cnav = function (string $key, string $href, string $label) use ($__active): string {
    $isActive = $__active === $key;
    $cls = $isActive
        ? 'text-brand font-semibold border-brand'
        : 'text-gray-500 hover:text-gray-900 border-transparent';
    return '<a href="' . e($href) . '" data-nav class="inline-flex items-center h-16 border-b-2 px-1 text-sm transition ' . $cls . '">' . e($label) . '</a>';
};
?>
<!doctype html>
<html lang="<?= e($__lang) ?>" dir="<?= e($__dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($__csrf) ?>">
  <title><?= e($title ?? app_lang('candidate_portal')) ?></title>

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
<body class="min-h-screen flex flex-col bg-gray-50 text-gray-900 antialiased">

  <!-- ============ Header ============ -->
  <header class="sticky top-0 z-30 bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
      <div class="h-16 flex items-center gap-4">
        <!-- Company branding -->
        <a href="/candidate/dashboard" class="flex items-center gap-3 shrink-0">
          <?php if ($__companyLogo !== ''): ?>
            <img src="<?= e($__companyLogo) ?>" alt="<?= e($__companyName) ?>" class="w-9 h-9 rounded-xl object-cover border border-gray-200">
          <?php else: ?>
            <span class="w-9 h-9 rounded-xl gradient-brand flex items-center justify-center text-white font-extrabold text-sm">AR</span>
          <?php endif; ?>
          <span class="font-bold text-gray-900 truncate max-w-[180px]"><?= e($__companyName) ?></span>
        </a>

        <!-- Desktop nav -->
        <nav class="hidden md:flex items-center gap-6 mx-auto">
          <?= $__cnav('dashboard',    '/candidate/dashboard',    app_lang('nav_my_dashboard')) ?>
          <?= $__cnav('jobs',         '/candidate/jobs',         app_lang('nav_jobs')) ?>
          <?= $__cnav('applications', '/candidate/applications', app_lang('nav_my_applications')) ?>
          <?= $__cnav('offers',       '/candidate/offers',       app_lang('nav_my_offers')) ?>
          <?= $__cnav('profile',      '/candidate/profile',      app_lang('nav_profile')) ?>
        </nav>

        <div class="flex items-center gap-2 <?= $__dir === 'rtl' ? 'mr-auto md:mr-0' : 'ml-auto md:ml-0' ?>">
          <!-- Language switcher -->
          <div class="flex items-center rounded-full border border-gray-200 bg-white p-0.5 text-xs font-semibold">
            <button type="button" data-lang="en" class="px-2.5 py-1 rounded-full transition <?= $__lang === 'en' ? 'bg-brand text-white' : 'text-gray-500 hover:text-gray-800' ?>">EN</button>
            <button type="button" data-lang="ar" class="px-2.5 py-1 rounded-full transition <?= $__lang === 'ar' ? 'bg-brand text-white' : 'text-gray-500 hover:text-gray-800' ?>">عربي</button>
          </div>
          <!-- Mobile menu toggle -->
          <button type="button" data-dropdown="candidate-mobile-nav" aria-label="<?= e(app_lang('menu')) ?>"
                  class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 transition">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile nav drawer -->
    <div id="candidate-mobile-nav" data-dropdown-menu class="hidden md:hidden border-t border-gray-100 bg-white">
      <nav class="max-w-6xl mx-auto px-4 py-2 flex flex-col">
        <a href="/candidate/dashboard"    class="py-2.5 text-sm <?= $__active === 'dashboard' ? 'text-brand font-semibold' : 'text-gray-600' ?>"><?= e(app_lang('nav_my_dashboard')) ?></a>
        <a href="/candidate/jobs"         class="py-2.5 text-sm <?= $__active === 'jobs' ? 'text-brand font-semibold' : 'text-gray-600' ?>"><?= e(app_lang('nav_jobs')) ?></a>
        <a href="/candidate/applications" class="py-2.5 text-sm <?= $__active === 'applications' ? 'text-brand font-semibold' : 'text-gray-600' ?>"><?= e(app_lang('nav_my_applications')) ?></a>
        <a href="/candidate/offers"       class="py-2.5 text-sm <?= $__active === 'offers' ? 'text-brand font-semibold' : 'text-gray-600' ?>"><?= e(app_lang('nav_my_offers')) ?></a>
        <a href="/candidate/profile"      class="py-2.5 text-sm <?= $__active === 'profile' ? 'text-brand font-semibold' : 'text-gray-600' ?>"><?= e(app_lang('nav_profile')) ?></a>
      </nav>
    </div>
  </header>

  <!-- ============ Main ============ -->
  <main class="flex-1 w-full">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 fade-in">
      <?= $content ?? '' ?>
    </div>
  </main>

  <!-- ============ Footer ============ -->
  <footer class="border-t border-gray-200 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-gray-400">
      <p>&copy; <?= date('Y') ?> <?= e($__companyName) ?>. <?= e(app_lang('all_rights_reserved')) ?></p>
      <p class="flex items-center gap-1.5">
        <?= e(app_lang('powered_by')) ?>
        <span class="inline-flex items-center gap-1.5 font-semibold text-gray-600">
          <span class="w-5 h-5 rounded-md gradient-brand inline-flex items-center justify-center text-white text-[9px] font-extrabold">AR</span>
          <?= e(app_lang('app_name')) ?>
        </span>
      </p>
    </div>
  </footer>

  <div id="toast-root"></div>
</body>
</html>
