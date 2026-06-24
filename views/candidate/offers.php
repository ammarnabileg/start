<?php
/**
 * Offers — two modes:
 *   A) Public landing (route /offer/{token}): rendered directly by Response::view
 *      with NO layout, so it must emit a full self-contained HTML page. Shows a
 *      single offer with Accept / Decline that POST to
 *      /api/v1/offers/accept|reject/{token}.
 *   B) Logged-in candidate (/candidate/offers): fragment wrapped by
 *      layouts/candidate.php — lists offers as cards.
 *
 * Receives: optional $token, $public, $title.
 */
$__public = !empty($public);
$__token  = (string)($token ?? '');

if ($__public):
    // ---------------------------------------------------------------------
    // MODE A — public, self-contained full HTML page.
    // ---------------------------------------------------------------------
    $__lang = $_COOKIE['lang'] ?? 'en';
    $__dir  = $__lang === 'ar' ? 'rtl' : 'ltr';
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
  <title><?= e(app_lang('offer')) ?> · <?= e(app_lang('app_name')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Tajawal:wght@400;500;700;800&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: {
      fontFamily: { sans: ['Inter', 'Tajawal', 'Cairo', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
      colors: { brand: { DEFAULT: '#7C3AED', dark: '#5B21B6', deep: '#1E1B4B' }, accent: '#FBBF24' },
    } } };
  </script>
  <link rel="stylesheet" href="/assets/css/app.css">
  <style>
    .pop-in { animation: pop .5s cubic-bezier(.34,1.56,.64,1) both; }
    @keyframes pop { 0% { transform: scale(.6); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    .confetti { position: fixed; top: -10px; width: 9px; height: 14px; opacity: .9; z-index: 60; animation: fall linear forwards; }
    @keyframes fall { to { transform: translateY(105vh) rotate(540deg); opacity: 0; } }
  </style>
</head>
<body class="gradient-brand min-h-screen text-gray-900 antialiased flex items-center justify-center p-4">
  <div class="pointer-events-none fixed -top-24 -left-24 w-96 h-96 rounded-full bg-white/10 blur-3xl"></div>
  <div class="pointer-events-none fixed -bottom-32 -right-16 w-96 h-96 rounded-full bg-accent/20 blur-3xl"></div>

  <div class="relative w-full max-w-lg">
    <div class="card p-7 sm:p-9">

      <div class="flex items-center justify-center gap-2.5 mb-6">
        <span class="w-9 h-9 rounded-xl gradient-brand flex items-center justify-center text-white font-extrabold text-xs">AR</span>
        <span class="font-bold text-gray-900"><?= e(app_lang('app_name')) ?></span>
      </div>

      <!-- ===== Offer card (default) ===== -->
      <div id="offer-view" class="text-center">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center pop-in">
          <svg class="w-9 h-9 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
        </div>
        <p class="mt-5 text-sm font-medium text-brand uppercase tracking-wide">You have an offer</p>
        <h1 class="mt-1 text-2xl font-extrabold text-gray-900">Congratulations!</h1>
        <p class="mt-2 text-gray-600">We’re excited to extend you an offer to join our team. Please review the details below.</p>

        <!-- summary (filled after a response reveals it; until then a generic note) -->
        <dl id="offer-summary" class="mt-6 text-left rounded-2xl bg-gray-50 ring-1 ring-gray-100 divide-y divide-gray-100">
          <div class="flex items-center justify-between px-4 py-3">
            <dt class="text-sm text-gray-500"><?= e(app_lang('job_title')) ?></dt>
            <dd id="sum-job" class="text-sm font-semibold text-gray-900">The position</dd>
          </div>
          <div class="flex items-center justify-between px-4 py-3">
            <dt class="text-sm text-gray-500"><?= e(app_lang('salary')) ?></dt>
            <dd id="sum-salary" class="text-sm font-semibold text-gray-900">As discussed</dd>
          </div>
          <div class="flex items-center justify-between px-4 py-3">
            <dt class="text-sm text-gray-500"><?= e(app_lang('start_date')) ?></dt>
            <dd id="sum-start" class="text-sm font-semibold text-gray-900">To be confirmed</dd>
          </div>
        </dl>

        <div class="mt-7 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button id="btn-accept" type="button" class="btn-accent justify-center !py-3 text-base">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
            <?= e(app_lang('accept')) ?> offer
          </button>
          <button id="btn-reject" type="button" class="btn-ghost justify-center !py-3 text-base">
            <?= e(app_lang('decline')) ?>
          </button>
        </div>
        <p class="mt-4 text-xs text-gray-400">By accepting you confirm your intent to join. You can reach out to the hiring team with any questions.</p>
      </div>

      <!-- ===== Accepted state ===== -->
      <div id="offer-accepted" class="hidden text-center">
        <div class="mx-auto w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center pop-in">
          <svg class="w-11 h-11 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
        </div>
        <h1 class="mt-6 text-2xl font-extrabold text-gray-900">You’ve accepted the offer 🎉</h1>
        <p class="mt-2 text-gray-600">Welcome aboard! The team has been notified and will be in touch shortly with onboarding details.</p>
      </div>

      <!-- ===== Declined state ===== -->
      <div id="offer-declined" class="hidden text-center">
        <div class="mx-auto w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center pop-in">
          <svg class="w-10 h-10 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </div>
        <h1 class="mt-6 text-2xl font-extrabold text-gray-900">Offer declined</h1>
        <p class="mt-2 text-gray-600">Thank you for letting us know. We wish you the very best, and hope our paths cross again.</p>
      </div>

      <!-- ===== Error state ===== -->
      <div id="offer-error" class="hidden text-center">
        <div class="mx-auto w-16 h-16 rounded-full bg-red-50 flex items-center justify-center">
          <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
        </div>
        <h1 class="mt-5 text-xl font-bold text-gray-900"><?= e(app_lang('error_generic')) ?></h1>
        <p id="offer-error-msg" class="mt-2 text-gray-600">This offer link may have expired or already been responded to.</p>
      </div>

    </div>
  </div>

  <div id="toast-root"></div>
  <script src="/assets/js/app.js"></script>
  <script>
    (function () {
      'use strict';
      var token = <?= json_encode($__token) ?>;
      var esc = (window.AR && AR.esc) ? AR.esc : function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

      function fmtMoney(amount, currency) {
        if (amount === null || amount === undefined || amount === '') return null;
        var cur = currency || 'USD';
        try { return cur + ' ' + Number(amount).toLocaleString(); } catch (e) { return cur + ' ' + amount; }
      }
      function fmtDate(d) {
        if (!d) return null;
        try { return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }); } catch (e) { return d; }
      }
      function fillSummary(o) {
        if (!o) return;
        if (o.job_title) document.getElementById('sum-job').textContent = o.job_title;
        var sal = fmtMoney(o.salary, o.currency); if (sal) document.getElementById('sum-salary').textContent = sal;
        var sd = fmtDate(o.start_date); if (sd) document.getElementById('sum-start').textContent = sd;
      }

      function celebrate() {
        var colors = ['#7C3AED', '#FBBF24', '#34D399', '#60A5FA', '#F472B6'];
        for (var i = 0; i < 80; i++) {
          (function (i) {
            var c = document.createElement('div');
            c.className = 'confetti';
            c.style.left = Math.random() * 100 + 'vw';
            c.style.background = colors[i % colors.length];
            c.style.animationDuration = (2 + Math.random() * 2) + 's';
            c.style.animationDelay = (Math.random() * 0.4) + 's';
            c.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
            document.body.appendChild(c);
            setTimeout(function () { c.remove(); }, 4500);
          })(i);
        }
      }

      function showState(id) {
        ['offer-view', 'offer-accepted', 'offer-declined', 'offer-error'].forEach(function (s) {
          var el = document.getElementById(s);
          if (el) el.classList.toggle('hidden', s !== id);
        });
      }

      function respond(accept) {
        var path = '/offers/' + (accept ? 'accept' : 'reject') + '/' + encodeURIComponent(token);
        var aBtn = document.getElementById('btn-accept');
        var rBtn = document.getElementById('btn-reject');
        if (aBtn) aBtn.disabled = true;
        if (rBtn) rBtn.disabled = true;

        function done(offer) {
          fillSummary(offer);
          if (accept) { showState('offer-accepted'); celebrate(); if (window.AR) AR.Toast.success('<?= e(app_lang('offer_accepted')) ?>'); }
          else { showState('offer-declined'); }
        }
        function fail(err) {
          if (aBtn) aBtn.disabled = false;
          if (rBtn) rBtn.disabled = false;
          var msg = (err && err.message) || 'This offer could not be processed.';
          var box = document.getElementById('offer-error-msg');
          if (box) box.textContent = msg;
          showState('offer-error');
        }

        if (window.AR && AR.Api) {
          AR.Api.post(path, {}).then(done).catch(fail);
        } else { fail(); }
      }

      var ab = document.getElementById('btn-accept');
      var rb = document.getElementById('btn-reject');
      if (ab) ab.addEventListener('click', function () { respond(true); });
      if (rb) rb.addEventListener('click', function () {
        if (window.AR && AR.Modal && document.getElementById('confirm-decline')) { return; }
        respond(false);
      });

      if (!token) { showState('offer-error'); }
    })();
  </script>
</body>
</html>
<?php
    return; // stop — public mode is a full page.
endif;

// -------------------------------------------------------------------------
// MODE B — logged-in candidate fragment (wrapped by layouts/candidate.php).
// -------------------------------------------------------------------------
$active = 'offers';
?>
<div class="mb-6">
  <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900"><?= e(app_lang('nav_my_offers')) ?></h1>
  <p class="mt-1 text-gray-500">Review and respond to job offers extended to you.</p>
</div>

<!-- Loading -->
<div id="offers-loading" class="grid grid-cols-1 md:grid-cols-2 gap-5">
  <div class="card p-5"><div class="skeleton h-6 w-2/3"></div><div class="skeleton h-20 w-full mt-4"></div></div>
  <div class="card p-5"><div class="skeleton h-6 w-2/3"></div><div class="skeleton h-20 w-full mt-4"></div></div>
</div>

<!-- List -->
<div id="offers-list" class="hidden grid grid-cols-1 md:grid-cols-2 gap-5"></div>

<!-- Empty -->
<div id="offers-empty" class="hidden card p-12 text-center">
  <div class="mx-auto w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center">
    <svg class="w-8 h-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
  </div>
  <p class="mt-4 text-lg font-semibold text-gray-800">No offers yet</p>
  <p class="mt-1 text-sm text-gray-500">When a company extends you an offer, it’ll appear here.</p>
  <a href="/candidate/applications" class="btn-primary mt-5 text-sm"><?= e(app_lang('nav_my_applications')) ?></a>
</div>

<script>
  (function () {
    'use strict';
    var esc = (window.AR && AR.esc) ? AR.esc : function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

    function show(id) { var el = document.getElementById(id); if (el) el.classList.remove('hidden'); }
    function hide(id) { var el = document.getElementById(id); if (el) el.classList.add('hidden'); }

    function money(o) {
      if (o.salary === null || o.salary === undefined || o.salary === '') return 'As discussed';
      var cur = o.currency || 'USD';
      try { return cur + ' ' + Number(o.salary).toLocaleString(); } catch (e) { return cur + ' ' + o.salary; }
    }
    function dateTxt(d) {
      if (!d) return 'To be confirmed';
      try { return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }); } catch (e) { return d; }
    }
    function statusBadge(s) {
      var map = {
        sent: ['badge-blue', '<?= e(app_lang('offer_sent')) ?>'],
        draft: ['badge-gray', '<?= e(app_lang('draft')) ?>'],
        accepted: ['badge-green', '<?= e(app_lang('offer_accepted')) ?>'],
        hired: ['badge-green', '<?= e(app_lang('stage_hired')) ?>'],
        rejected: ['badge-red', '<?= e(app_lang('decline')) ?>d'],
        expired: ['badge-gray', 'Expired']
      };
      var m = map[s] || ['badge-gray', s || '—'];
      return '<span class="badge ' + m[0] + '">' + esc(m[1]) + '</span>';
    }

    function card(o) {
      var status = o.status || 'sent';
      var pending = status === 'sent';
      var title = o.job_title || o.title || 'Position';
      var actions = pending
        ? '<div class="mt-5 grid grid-cols-2 gap-2">' +
            '<button type="button" class="btn-accent justify-center text-sm act-accept" data-token="' + esc(o.token || '') + '" data-id="' + esc(o.id || '') + '"><?= e(app_lang('accept')) ?></button>' +
            '<button type="button" class="btn-ghost justify-center text-sm act-reject" data-token="' + esc(o.token || '') + '" data-id="' + esc(o.id || '') + '"><?= e(app_lang('decline')) ?></button>' +
          '</div>'
        : (status === 'accepted' || status === 'hired'
            ? '<div class="mt-5 flex items-center gap-2 text-sm text-emerald-600"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg> You accepted this offer.</div>'
            : '');

      return '' +
        '<article class="card p-5 flex flex-col">' +
          '<div class="flex items-start justify-between gap-3">' +
            '<div class="min-w-0"><h3 class="font-bold text-gray-900 truncate">' + esc(title) + '</h3>' +
              (o.company_name ? '<p class="text-xs text-gray-400 truncate">' + esc(o.company_name) + '</p>' : '') + '</div>' +
            statusBadge(status) +
          '</div>' +
          '<dl class="mt-4 space-y-2 text-sm">' +
            '<div class="flex justify-between"><dt class="text-gray-500"><?= e(app_lang('salary')) ?></dt><dd class="font-semibold text-gray-900">' + esc(money(o)) + '</dd></div>' +
            '<div class="flex justify-between"><dt class="text-gray-500"><?= e(app_lang('start_date')) ?></dt><dd class="font-semibold text-gray-900">' + esc(dateTxt(o.start_date)) + '</dd></div>' +
            (o.expiry_date ? '<div class="flex justify-between"><dt class="text-gray-500"><?= e(app_lang('expiry_date')) ?></dt><dd class="font-medium text-gray-700">' + esc(dateTxt(o.expiry_date)) + '</dd></div>' : '') +
          '</dl>' +
          actions +
        '</article>';
    }

    function bindActions() {
      function handle(accept) {
        return function () {
          var token = this.getAttribute('data-token');
          var id = this.getAttribute('data-id');
          var btn = this;
          btn.disabled = true; btn.style.opacity = '.7';
          if (!(window.AR && AR.Api)) { btn.disabled = false; return; }

          // Prefer token route (public processor); fall back to id update.
          var p = token
            ? AR.Api.post('/offers/' + (accept ? 'accept' : 'reject') + '/' + encodeURIComponent(token), {})
            : AR.Api.put('/offers/' + encodeURIComponent(id), { status: accept ? 'accepted' : 'rejected' });

          p.then(function () {
            AR.Toast.success(accept ? '<?= e(app_lang('offer_accepted')) ?>' : 'Offer declined');
            load();
          }).catch(function (err) {
            AR.Toast.error((err && err.message) || '<?= e(app_lang('error_generic')) ?>');
            btn.disabled = false; btn.style.opacity = '';
          });
        };
      }
      document.querySelectorAll('.act-accept').forEach(function (b) { b.addEventListener('click', handle(true)); });
      document.querySelectorAll('.act-reject').forEach(function (b) { b.addEventListener('click', handle(false)); });
    }

    function render(offers) {
      hide('offers-loading');
      var list = document.getElementById('offers-list');
      if (!offers || !offers.length) { show('offers-empty'); return; }
      show('offers-list');
      list.innerHTML = offers.map(card).join('');
      bindActions();
    }

    function load() {
      if (!(window.AR && AR.Api)) { hide('offers-loading'); show('offers-empty'); return; }
      AR.Api.get('/offers/me')
        .then(function (data) { render(data || []); })
        .catch(function () { hide('offers-loading'); show('offers-empty'); });
    }

    document.addEventListener('DOMContentLoaded', load);
  })();
</script>
