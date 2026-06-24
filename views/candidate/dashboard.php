<?php
/**
 * Candidate portal dashboard — fragment wrapped by layouts/candidate.php.
 * Sets $active for the nav. Session-less friendly; data hydrated via API
 * with graceful empty states.
 */
$active = 'dashboard';
?>
<!-- ============ Welcome banner ============ -->
<section class="gradient-brand rounded-2xl p-6 sm:p-8 text-white overflow-hidden relative">
  <div class="pointer-events-none absolute -top-16 -right-10 w-64 h-64 rounded-full bg-white/10 blur-2xl"></div>
  <div class="pointer-events-none absolute -bottom-20 -left-10 w-64 h-64 rounded-full bg-accent/20 blur-2xl"></div>
  <div class="relative">
    <p class="text-white/70 text-sm font-medium"><?= e(app_lang('welcome_back')) ?></p>
    <h1 id="greet-name" class="mt-1 text-2xl sm:text-3xl font-extrabold">Hello there!</h1>
    <p class="mt-2 max-w-xl text-white/80 text-sm sm:text-base">Track your applications, join interviews, and review offers — all in one place.</p>
    <div class="mt-5 flex flex-wrap gap-3">
      <a href="/candidate/jobs" class="btn-accent text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
        Browse open positions
      </a>
      <a href="/candidate/profile" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/15 hover:bg-white/25 ring-1 ring-white/25 text-sm font-semibold transition">
        <?= e(app_lang('nav_profile')) ?>
      </a>
    </div>
  </div>
</section>

<!-- ============ Stat strip ============ -->
<section class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
  <?php
  $__stats = [
      ['id' => 'stat-apps',       'label' => app_lang('nav_my_applications'), 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />', 'tone' => 'violet'],
      ['id' => 'stat-active',     'label' => 'In Progress',                   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />', 'tone' => 'blue'],
      ['id' => 'stat-interviews', 'label' => app_lang('nav_interviews'),      'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />', 'tone' => 'gold'],
      ['id' => 'stat-offers',     'label' => app_lang('nav_my_offers'),       'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />', 'tone' => 'green'],
  ];
  $__toneMap = [
      'violet' => 'bg-violet-50 text-brand',
      'blue'   => 'bg-blue-50 text-blue-600',
      'gold'   => 'bg-amber-50 text-amber-600',
      'green'  => 'bg-emerald-50 text-emerald-600',
  ];
  foreach ($__stats as $__s):
  ?>
  <div class="card p-4 flex items-center gap-3.5">
    <span class="w-11 h-11 rounded-xl <?= e($__toneMap[$__s['tone']]) ?> flex items-center justify-center shrink-0">
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><?= $__s['icon'] ?></svg>
    </span>
    <div class="min-w-0">
      <p id="<?= e($__s['id']) ?>" class="text-2xl font-extrabold text-gray-900 leading-none">0</p>
      <p class="mt-1 text-xs font-medium text-gray-500 truncate"><?= e($__s['label']) ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</section>

<div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- ============ My Applications ============ -->
  <div class="lg:col-span-2 space-y-6">
    <section class="card p-5 sm:p-6">
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-gray-900"><?= e(app_lang('nav_my_applications')) ?></h2>
        <a href="/candidate/applications" class="text-sm font-semibold text-brand hover:text-brand-dark">View all →</a>
      </div>

      <!-- loading -->
      <div id="apps-loading" class="mt-4 space-y-3">
        <div class="skeleton h-16 w-full"></div>
        <div class="skeleton h-16 w-full"></div>
      </div>

      <!-- list -->
      <ul id="apps-list" class="mt-4 hidden divide-y divide-gray-100"></ul>

      <!-- empty -->
      <div id="apps-empty" class="mt-4 hidden text-center py-10">
        <div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 flex items-center justify-center">
          <svg class="w-7 h-7 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
        </div>
        <p class="mt-3 font-semibold text-gray-800">You have no applications yet</p>
        <p class="text-sm text-gray-500">Find a role that fits and apply in minutes.</p>
        <a href="/candidate/jobs" class="btn-primary mt-4 text-sm">Browse jobs</a>
      </div>
    </section>

    <!-- ============ Upcoming Interviews ============ -->
    <section class="card p-5 sm:p-6">
      <h2 class="text-lg font-bold text-gray-900"><?= e(app_lang('pending_interviews')) ?></h2>

      <div id="iv-loading" class="mt-4 space-y-3">
        <div class="skeleton h-20 w-full"></div>
      </div>

      <ul id="iv-list" class="mt-4 hidden space-y-3"></ul>

      <div id="iv-empty" class="mt-4 hidden text-center py-10">
        <div class="mx-auto w-14 h-14 rounded-2xl bg-amber-50 flex items-center justify-center">
          <svg class="w-7 h-7 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
        </div>
        <p class="mt-3 font-semibold text-gray-800">No upcoming interviews</p>
        <p class="text-sm text-gray-500">When you’re invited to an interview, it’ll appear here with a join button.</p>
      </div>
    </section>
  </div>

  <!-- ============ Notifications ============ -->
  <aside class="space-y-6">
    <section class="card p-5 sm:p-6">
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
        <h2 class="text-lg font-bold text-gray-900"><?= e(app_lang('notifications')) ?></h2>
      </div>

      <ul id="notif-list" class="mt-4 hidden space-y-3"></ul>

      <div id="notif-empty" class="mt-4 text-center py-8">
        <div class="mx-auto w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center">
          <svg class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 003.844.148m-3.844-.148a23.856 23.856 0 01-5.455-1.31 8.964 8.964 0 002.3-5.542m3.155 6.852a3 3 0 005.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 003.536-1.003A8.967 8.967 0 0118 9.75V9A6 6 0 006.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53" /></svg>
        </div>
        <p class="mt-3 text-sm font-medium text-gray-600">You’re all caught up</p>
        <p class="text-xs text-gray-400">No new notifications.</p>
      </div>
    </section>

    <!-- Profile completeness nudge -->
    <section class="card p-5 sm:p-6">
      <h3 class="font-bold text-gray-900">Complete your profile</h3>
      <p class="mt-1 text-sm text-gray-500">A complete profile helps recruiters find you.</p>
      <div class="mt-3 score-bar"><span style="width:60%"></span></div>
      <a href="/candidate/profile" class="mt-4 inline-flex btn-ghost text-sm">Update profile</a>
    </section>
  </aside>
</div>

<script>
  (function () {
    'use strict';
    var esc = (window.AR && AR.esc) ? AR.esc : function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };

    function badgeForStage(stage) {
      var map = {
        applied: 'badge-blue', screening: 'badge-yellow', ai_interview: 'badge-violet',
        human_interview: 'badge-violet', offer: 'badge-green', hired: 'badge-green',
        rejected: 'badge-red'
      };
      return map[stage] || 'badge-gray';
    }
    function labelForStage(stage) {
      var map = {
        applied: 'Applied', screening: 'Screening', ai_interview: 'AI Interview',
        human_interview: 'Human Interview', offer: 'Offer', hired: 'Hired', rejected: 'Rejected'
      };
      return map[stage] || (stage ? String(stage).replace(/_/g, ' ') : 'Applied');
    }

    function show(id) { var el = document.getElementById(id); if (el) el.classList.remove('hidden'); }
    function hide(id) { var el = document.getElementById(id); if (el) el.classList.add('hidden'); }

    // ---- Applications --------------------------------------------------
    function renderApps(apps) {
      hide('apps-loading');
      var list = document.getElementById('apps-list');
      if (!apps || !apps.length) { show('apps-empty'); return; }
      show('apps-list');
      list.innerHTML = '';
      document.getElementById('stat-apps').textContent = apps.length;
      var inProgress = 0;
      apps.slice(0, 5).forEach(function (a) {
        var stage = a.pipeline_stage || a.stage || 'applied';
        if (stage !== 'hired' && stage !== 'rejected' && stage !== 'offer') inProgress++;
        var title = a.job_title || a.title || 'Position';
        var company = a.company_name || a.department || '';
        var li = document.createElement('li');
        li.className = 'py-3 flex items-center gap-3';
        li.innerHTML =
          '<span class="w-10 h-10 rounded-lg bg-violet-50 text-brand flex items-center justify-center font-bold text-sm shrink-0">' + esc(title.charAt(0).toUpperCase()) + '</span>' +
          '<div class="min-w-0 flex-1"><p class="font-semibold text-gray-900 truncate">' + esc(title) + '</p>' +
          '<p class="text-xs text-gray-400 truncate">' + esc(company) + '</p></div>' +
          '<span class="badge ' + badgeForStage(stage) + '">' + esc(labelForStage(stage)) + '</span>';
        list.appendChild(li);
      });
      document.getElementById('stat-active').textContent = inProgress;
    }

    // ---- Interviews ----------------------------------------------------
    function renderInterviews(items) {
      hide('iv-loading');
      var list = document.getElementById('iv-list');
      var upcoming = (items || []).filter(function (i) {
        var s = i.status || '';
        return s !== 'completed' && s !== 'evaluated' && s !== 'cancelled';
      });
      document.getElementById('stat-interviews').textContent = upcoming.length;
      if (!upcoming.length) { show('iv-empty'); return; }
      show('iv-list');
      list.innerHTML = '';
      upcoming.slice(0, 4).forEach(function (i) {
        var title = i.job_title || i.title || 'Interview';
        var token = i.token || '';
        var typeLabel = ({ ai_text: 'AI Text', ai_voice: 'AI Voice', ai_video: 'AI Video', human: 'Human' })[i.type] || 'Interview';
        var li = document.createElement('li');
        li.className = 'rounded-xl border border-gray-100 p-4 flex items-center gap-3';
        var btn = token
          ? '<a href="/interview/room/' + encodeURIComponent(token) + '" class="btn-primary text-sm shrink-0">Join</a>'
          : '<span class="badge badge-gray shrink-0">Scheduled</span>';
        li.innerHTML =
          '<span class="w-10 h-10 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">' +
          '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z" /></svg></span>' +
          '<div class="min-w-0 flex-1"><p class="font-semibold text-gray-900 truncate">' + esc(title) + '</p>' +
          '<p class="text-xs text-gray-400">' + esc(typeLabel) + '</p></div>' + btn;
        list.appendChild(li);
      });
    }

    // ---- Load (best-effort; tolerate missing endpoints) ----------------
    function load() {
      if (!(window.AR && AR.Api)) {
        hide('apps-loading'); show('apps-empty');
        hide('iv-loading'); show('iv-empty');
        return;
      }
      AR.Api.get('/candidates/me/applications')
        .then(function (data) { renderApps(data || []); })
        .catch(function () { hide('apps-loading'); show('apps-empty'); document.getElementById('stat-apps').textContent = '0'; });

      AR.Api.get('/interviews/me')
        .then(function (data) { renderInterviews(data || []); })
        .catch(function () { hide('iv-loading'); show('iv-empty'); document.getElementById('stat-interviews').textContent = '0'; });
    }

    document.addEventListener('DOMContentLoaded', load);
  })();
</script>
