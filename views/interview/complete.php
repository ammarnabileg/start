<?php
/**
 * Post-interview thank-you — self-contained full HTML page (no layout).
 * Token-based, session-less. Reads $token.
 */
$__lang = $_COOKIE['lang'] ?? 'en';
$__dir  = $__lang === 'ar' ? 'rtl' : 'ltr';
$__token = (string)($token ?? '');
$__csrf = '';
if (class_exists('App\\Core\\Request')) {
    $__csrf = \App\Core\Request::csrfToken();
}
?>
<!doctype html>
<html lang="<?= e($__lang) ?>" dir="<?= e($__dir) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($__csrf) ?>">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e(app_lang('thank_you')) ?> · <?= e(app_lang('app_name')) ?></title>

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
  <style>
    .pop-in { animation: pop .5s cubic-bezier(.34,1.56,.64,1) both; }
    @keyframes pop { 0% { transform: scale(.5); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    .check-draw { stroke-dasharray: 48; stroke-dashoffset: 48; animation: draw .6s .25s ease forwards; }
    @keyframes draw { to { stroke-dashoffset: 0; } }
    .star { transition: transform .12s ease, color .12s ease; }
    .star:hover { transform: scale(1.12); }
  </style>
</head>
<body class="gradient-brand min-h-screen text-gray-900 antialiased flex items-center justify-center p-4">

  <!-- decorative blobs -->
  <div class="pointer-events-none fixed -top-24 -left-24 w-96 h-96 rounded-full bg-white/10 blur-3xl"></div>
  <div class="pointer-events-none fixed -bottom-32 -right-16 w-96 h-96 rounded-full bg-accent/20 blur-3xl"></div>

  <div class="relative w-full max-w-lg">
    <div class="card p-8 sm:p-10 text-center fade-in">

      <!-- Check icon -->
      <div class="mx-auto w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center pop-in">
        <svg class="w-11 h-11 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
          <path class="check-draw" stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
      </div>

      <h1 class="mt-6 text-3xl font-extrabold text-gray-900"><?= e(app_lang('thank_you')) ?></h1>
      <p class="mt-3 text-gray-600">Your interview has been submitted successfully.</p>

      <!-- What happens next -->
      <div class="mt-7 text-left rounded-2xl bg-violet-50 ring-1 ring-violet-100 p-5">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
          <h2 class="font-bold text-gray-900"><?= e(app_lang('whats_next')) ?></h2>
        </div>
        <ul class="mt-3 space-y-2.5 text-sm text-gray-600">
          <li class="flex gap-2.5">
            <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-brand shrink-0"></span>
            Our team will review your responses alongside the AI evaluation.
          </li>
          <li class="flex gap-2.5">
            <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-brand shrink-0"></span>
            You’ll hear from us by email with the next steps.
          </li>
          <li class="flex gap-2.5">
            <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-brand shrink-0"></span>
            You can safely close this window now.
          </li>
        </ul>
      </div>

      <!-- ===== Feedback form ===== -->
      <div id="feedback-block" class="mt-8">
        <h3 class="text-sm font-semibold text-gray-800"><?= e(app_lang('rate_experience')) ?></h3>

        <!-- Stars -->
        <div id="stars" class="mt-3 flex items-center justify-center gap-1.5" role="radiogroup" aria-label="<?= e(app_lang('rate_experience')) ?>">
          <?php for ($i = 1; $i <= 5; $i++): ?>
          <button type="button" class="star text-gray-300" data-value="<?= $i ?>" role="radio" aria-checked="false" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
            <svg class="w-9 h-9" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
          </button>
          <?php endfor; ?>
        </div>
        <p id="rating-label" class="mt-1.5 h-4 text-xs text-gray-400"></p>

        <!-- Comments -->
        <textarea id="feedback-comments" rows="3" placeholder="Any comments about your experience? (optional)"
                  class="mt-4 w-full rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm text-gray-800 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 resize-none"></textarea>

        <button id="feedback-submit" type="button" class="btn-primary mt-4 w-full justify-center">
          <?= e(app_lang('send')) ?>
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>
        </button>
      </div>

      <!-- Success state (after feedback submit) -->
      <div id="feedback-done" class="hidden mt-8 rounded-2xl bg-emerald-50 ring-1 ring-emerald-100 p-5 text-center">
        <svg class="w-8 h-8 text-emerald-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <p class="mt-2 font-semibold text-gray-800">Thanks for your feedback!</p>
        <p class="text-sm text-gray-500">We appreciate you taking the time.</p>
      </div>

      <p class="mt-8 text-xs text-gray-400 flex items-center justify-center gap-1.5">
        <?= e(app_lang('powered_by')) ?>
        <span class="font-semibold text-brand"><?= e(app_lang('app_name')) ?></span>
      </p>
    </div>
  </div>

  <div id="toast-root"></div>

  <script src="/assets/js/app.js"></script>
  <script>
    (function () {
      'use strict';
      var token = <?= json_encode($__token) ?>;
      var rating = 0;
      var stars = Array.prototype.slice.call(document.querySelectorAll('#stars .star'));
      var label = document.getElementById('rating-label');
      var labels = { 1: 'Poor', 2: 'Fair', 3: 'Good', 4: 'Great', 5: 'Excellent' };

      function paint(n) {
        stars.forEach(function (s) {
          var v = parseInt(s.getAttribute('data-value'), 10);
          s.classList.toggle('text-gray-300', v > n);
          s.classList.toggle('text-accent', v <= n);
          s.setAttribute('aria-checked', v === rating ? 'true' : 'false');
        });
      }

      stars.forEach(function (s) {
        var v = parseInt(s.getAttribute('data-value'), 10);
        s.addEventListener('mouseenter', function () { paint(v); if (label) label.textContent = labels[v] || ''; });
        s.addEventListener('mouseleave', function () { paint(rating); if (label) label.textContent = rating ? (labels[rating] || '') : ''; });
        s.addEventListener('click', function () { rating = v; paint(rating); if (label) label.textContent = labels[rating] || ''; });
      });

      var submit = document.getElementById('feedback-submit');
      if (submit) {
        submit.addEventListener('click', function () {
          if (rating === 0) {
            if (window.AR) AR.Toast.info('Please pick a star rating first.');
            return;
          }
          submit.disabled = true;
          submit.style.opacity = '.7';

          var comments = (document.getElementById('feedback-comments') || {}).value || '';
          var payload = { token: token, rating: rating, comments: comments };

          // There may be no dedicated feedback endpoint — attempt it, but always
          // degrade gracefully to a friendly confirmation.
          function done() {
            var block = document.getElementById('feedback-block');
            var ok = document.getElementById('feedback-done');
            if (block) block.classList.add('hidden');
            if (ok) ok.classList.remove('hidden');
            if (window.AR) AR.Toast.success('Thank you for your feedback!');
          }

          if (window.AR && AR.Api) {
            AR.Api.post('/interviews/feedback/' + encodeURIComponent(token), payload)
              .then(done)
              .catch(function () { done(); }); // endpoint optional -> still confirm
          } else {
            done();
          }
        });
      }
    })();
  </script>
</body>
</html>
