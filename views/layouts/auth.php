<?php
/**
 * Authentication layout — split screen.
 * Left: brand / marketing panel (gradient). Right: centered form ($content).
 * Receives: $content, $title, $csrf.
 */
$__lang = $_COOKIE['lang'] ?? 'en';
$__dir  = $__lang === 'ar' ? 'rtl' : 'ltr';
$__csrf = $csrf ?? ($csrf_token ?? '');

$__features = [
    app_lang('feature_ai_interviews'),
    app_lang('feature_personality'),
    app_lang('feature_matching'),
    app_lang('feature_multilang'),
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
      theme: { extend: {
        fontFamily: { sans: ['Inter', 'Tajawal', 'Cairo', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
        colors: { brand: { DEFAULT: '#7C3AED', dark: '#5B21B6', deep: '#1E1B4B' }, accent: '#FBBF24' },
      } },
    };
  </script>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">
  <div class="min-h-screen grid lg:grid-cols-2">

    <!-- ============ Left: brand panel ============ -->
    <div class="hidden lg:flex relative flex-col justify-between gradient-brand text-white p-12 overflow-hidden">
      <!-- decorative blobs -->
      <div class="pointer-events-none absolute -top-24 -right-24 w-96 h-96 rounded-full bg-white/10 blur-3xl"></div>
      <div class="pointer-events-none absolute -bottom-32 -left-16 w-96 h-96 rounded-full bg-accent/20 blur-3xl"></div>

      <!-- Logo + name -->
      <div class="relative flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur flex items-center justify-center font-extrabold tracking-tight">AR</div>
        <span class="text-xl font-bold"><?= e(app_lang('app_name')) ?></span>
      </div>

      <!-- Headline + tagline + features -->
      <div class="relative max-w-md">
        <h2 class="text-4xl font-extrabold leading-tight"><?= e(app_lang('tagline')) ?></h2>
        <p class="mt-4 text-white/70 text-lg"><?= e(app_lang('sign_in_to_continue')) ?></p>

        <ul class="mt-10 space-y-4">
          <?php foreach ($__features as $__f): ?>
            <li class="flex items-center gap-3">
              <span class="flex items-center justify-center w-7 h-7 rounded-full bg-white/15 ring-1 ring-white/25 shrink-0">
                <svg class="w-4 h-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
              </span>
              <span class="text-white/90"><?= e($__f) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Footer -->
      <div class="relative text-sm text-white/60"><?= e(app_lang('trusted_by')) ?></div>
    </div>

    <!-- ============ Right: form ============ -->
    <div class="flex items-center justify-center p-6 sm:p-10 bg-gray-50 lg:bg-white">
      <div class="w-full max-w-md">
        <!-- Mobile-only logo (left panel hidden) -->
        <div class="lg:hidden flex items-center justify-center gap-3 mb-8">
          <div class="w-11 h-11 rounded-2xl gradient-brand flex items-center justify-center text-white font-extrabold">AR</div>
          <span class="text-lg font-bold text-gray-900"><?= e(app_lang('app_name')) ?></span>
        </div>

        <?= $content ?? '' ?>
      </div>
    </div>
  </div>

  <div id="toast-root"></div>
</body>
</html>
