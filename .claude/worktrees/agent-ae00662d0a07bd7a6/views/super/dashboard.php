<?php
/**
 * Super Admin Dashboard View
 * Rendered inside the main layout — no <html>/<body> tags needed.
 *
 * Available:
 *   $user  — Auth::user() array  [id, first_name, last_name, email, type, tenant_id]
 *   $req   — request object with csrf() method
 */
?>
<meta name="csrf" content="<?= htmlspecialchars($req->csrf(), ENT_QUOTES, 'UTF-8') ?>">

<div class="min-h-screen bg-gray-50 p-6">

  <!-- ─── Page header ──────────────────────────────────────────────────────── -->
  <div class="mb-8 flex items-center justify-between">
    <div>
      <h1 class="text-3xl font-bold text-gray-900">
        Welcome back, <?= htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') ?>!
      </h1>
      <p class="mt-1 text-sm text-gray-500">
        Super Admin &mdash; platform overview as of <span id="last-updated" class="font-medium text-gray-700">loading…</span>
      </p>
    </div>
    <button
      id="btn-refresh"
      onclick="loadStats()"
      class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-indigo-700 active:scale-95 transition">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 4v5h.582M20 20v-5h-.581M5.635 19A9 9 0 104.582 9H4" />
      </svg>
      Refresh
    </button>
  </div>

  <!-- ─── Alert banner (hidden by default) ─────────────────────────────────── -->
  <div id="error-banner" class="hidden mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
    <span id="error-msg"></span>
  </div>

  <!-- ─── Stats cards ───────────────────────────────────────────────────────── -->
  <section class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-5">

    <!-- Total Companies -->
    <div class="stat-card relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Companies</p>
          <p id="stat-companies" class="mt-2 text-3xl font-bold text-gray-900 skeleton-text">—</p>
        </div>
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 8h2" />
          </svg>
        </span>
      </div>
      <p class="mt-3 text-xs text-gray-400">All registered tenants</p>
      <div class="absolute bottom-0 left-0 h-1 w-full bg-indigo-500 opacity-20 rounded-b-2xl"></div>
    </div>

    <!-- Active Subscriptions -->
    <div class="stat-card relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Active Subs</p>
          <p id="stat-subscriptions" class="mt-2 text-3xl font-bold text-gray-900 skeleton-text">—</p>
        </div>
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </span>
      </div>
      <p class="mt-3 text-xs text-gray-400">Paying customers</p>
      <div class="absolute bottom-0 left-0 h-1 w-full bg-emerald-500 opacity-20 rounded-b-2xl"></div>
    </div>

    <!-- Total Users -->
    <div class="stat-card relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Users</p>
          <p id="stat-users" class="mt-2 text-3xl font-bold text-gray-900 skeleton-text">—</p>
        </div>
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-100 text-sky-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4 0a4 4 0 100-8 4 4 0 000 8z" />
          </svg>
        </span>
      </div>
      <p class="mt-3 text-xs text-gray-400">Across all tenants</p>
      <div class="absolute bottom-0 left-0 h-1 w-full bg-sky-500 opacity-20 rounded-b-2xl"></div>
    </div>

    <!-- Total AI Interviews -->
    <div class="stat-card relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">AI Interviews</p>
          <p id="stat-interviews" class="mt-2 text-3xl font-bold text-gray-900 skeleton-text">—</p>
        </div>
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-violet-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
        </span>
      </div>
      <p class="mt-3 text-xs text-gray-400">All-time conducted</p>
      <div class="absolute bottom-0 left-0 h-1 w-full bg-violet-500 opacity-20 rounded-b-2xl"></div>
    </div>

    <!-- Tokens Used Today -->
    <div class="stat-card relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Tokens Today</p>
          <p id="stat-tokens" class="mt-2 text-3xl font-bold text-gray-900 skeleton-text">—</p>
        </div>
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </span>
      </div>
      <p class="mt-3 text-xs text-gray-400">LLM tokens consumed</p>
      <div class="absolute bottom-0 left-0 h-1 w-full bg-amber-500 opacity-20 rounded-b-2xl"></div>
    </div>

  </section>

  <!-- ─── Main two-column grid ─────────────────────────────────────────────── -->
  <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">

    <!-- Recent Signups table (spans 2 cols on xl) -->
    <div class="xl:col-span-2 rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
      <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
        <h2 class="text-base font-semibold text-gray-800">Recent Company Signups</h2>
        <a href="/super/companies" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">View all &rarr;</a>
      </div>

      <!-- Loading skeleton -->
      <div id="signups-skeleton" class="divide-y divide-gray-50">
        <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="flex items-center gap-4 px-6 py-4 animate-pulse">
          <div class="h-8 w-8 rounded-full bg-gray-200"></div>
          <div class="flex-1 space-y-2">
            <div class="h-3 w-1/3 rounded bg-gray-200"></div>
            <div class="h-2 w-1/4 rounded bg-gray-100"></div>
          </div>
          <div class="h-5 w-16 rounded-full bg-gray-200"></div>
          <div class="h-3 w-20 rounded bg-gray-200"></div>
          <div class="h-5 w-14 rounded-full bg-gray-200"></div>
        </div>
        <?php endfor; ?>
      </div>

      <!-- Real table (hidden until data arrives) -->
      <div id="signups-table-wrap" class="hidden overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
              <th class="px-6 py-3">Company</th>
              <th class="px-6 py-3">Plan</th>
              <th class="px-6 py-3">Joined</th>
              <th class="px-6 py-3">Status</th>
            </tr>
          </thead>
          <tbody id="signups-tbody" class="divide-y divide-gray-50 text-gray-700">
            <!-- rows injected by JS -->
          </tbody>
        </table>
        <div id="signups-empty" class="hidden py-12 text-center text-sm text-gray-400">No signups found.</div>
      </div>
    </div>

    <!-- System health (1 col) -->
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100 flex flex-col">
      <div class="border-b border-gray-100 px-6 py-4">
        <h2 class="text-base font-semibold text-gray-800">System Health</h2>
      </div>
      <div class="flex-1 space-y-6 px-6 py-5">

        <!-- DB status -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-medium text-gray-600">Database</span>
            <span id="db-status-label" class="text-xs font-semibold text-gray-400">checking…</span>
          </div>
          <div class="flex items-center gap-2">
            <span id="db-dot" class="inline-block h-3 w-3 rounded-full bg-gray-300 animate-pulse"></span>
            <span id="db-text" class="text-xs text-gray-500">Waiting for data</span>
          </div>
        </div>

        <!-- Storage usage -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-medium text-gray-600">Storage</span>
            <span id="storage-pct" class="text-xs font-semibold text-gray-400">—%</span>
          </div>
          <div class="h-2.5 w-full rounded-full bg-gray-100 overflow-hidden">
            <div id="storage-bar" class="h-full rounded-full bg-indigo-500 transition-all duration-700" style="width:0%"></div>
          </div>
          <p id="storage-detail" class="mt-1 text-xs text-gray-400">Loading storage info…</p>
        </div>

        <!-- Queue depth -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-medium text-gray-600">Job Queue</span>
            <span id="queue-count" class="text-xs font-semibold text-gray-400">—</span>
          </div>
          <div class="flex items-center gap-2">
            <span id="queue-dot" class="inline-block h-3 w-3 rounded-full bg-gray-300"></span>
            <span id="queue-text" class="text-xs text-gray-500">Pending jobs</span>
          </div>
        </div>

        <!-- Last backup -->
        <div>
          <p class="text-sm font-medium text-gray-600 mb-1">Last Backup</p>
          <p id="last-backup" class="text-xs text-gray-500">Loading…</p>
        </div>

      </div>
    </div>

  </div><!-- /main grid -->

  <!-- ─── Signups bar chart (last 7 days) ──────────────────────────────────── -->
  <section class="mt-6 rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
      <div>
        <h2 class="text-base font-semibold text-gray-800">New Company Signups &mdash; Last 7 Days</h2>
        <p class="text-xs text-gray-400 mt-0.5">Daily count of new tenant registrations</p>
      </div>
      <span id="chart-total" class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">Total: —</span>
    </div>

    <!-- Skeleton -->
    <div id="chart-skeleton" class="flex items-end gap-3 px-8 py-8 animate-pulse">
      <?php foreach ([60, 30, 80, 45, 100, 70, 55] as $h): ?>
      <div class="flex-1 rounded-t-lg bg-gray-200" style="height:<?= $h ?>px"></div>
      <?php endforeach; ?>
    </div>

    <!-- Real chart -->
    <div id="chart-wrap" class="hidden px-8 pb-6 pt-4">
      <div id="chart-bars" class="flex items-end gap-3" style="height:160px">
        <!-- bars injected by JS -->
      </div>
      <div id="chart-labels" class="mt-2 flex gap-3 text-center">
        <!-- labels injected by JS -->
      </div>
    </div>
  </section>

</div><!-- /page wrapper -->

<!-- ─── Styles ────────────────────────────────────────────────────────────── -->
<style>
  .skeleton-text { color: transparent; background: #e5e7eb; border-radius: 4px; }
  .skeleton-text.loaded { color: inherit; background: none; }

  .plan-badge-free      { background:#f3f4f6; color:#374151; }
  .plan-badge-starter   { background:#dbeafe; color:#1d4ed8; }
  .plan-badge-pro       { background:#ede9fe; color:#6d28d9; }
  .plan-badge-enterprise{ background:#fef3c7; color:#92400e; }

  .status-active    { background:#d1fae5; color:#065f46; }
  .status-trial     { background:#dbeafe; color:#1e40af; }
  .status-inactive  { background:#fee2e2; color:#991b1b; }
  .status-suspended { background:#fef3c7; color:#92400e; }

  .chart-bar {
    flex: 1;
    border-radius: 6px 6px 0 0;
    min-width: 0;
    transition: height 0.6s cubic-bezier(.4,0,.2,1), opacity 0.3s;
    position: relative;
    cursor: default;
    align-self: flex-end;
  }
  .chart-bar:hover::after {
    content: attr(data-value);
    position: absolute;
    top: -24px;
    left: 50%;
    transform: translateX(-50%);
    background: #1e1b4b;
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    white-space: nowrap;
    pointer-events: none;
  }
  .chart-label {
    flex: 1;
    font-size: 11px;
    color: #9ca3af;
    text-align: center;
    min-width: 0;
  }
</style>

<!-- ─── JavaScript ─────────────────────────────────────────────────────────── -->
<script>
(function () {
  'use strict';

  const csrfToken = () => document.querySelector('meta[name=csrf]').content;

  function fmtNumber(n) {
    if (n === undefined || n === null) return '—';
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000)    return (n / 1000).toFixed(1) + 'K';
    return String(n);
  }

  function fmtDate(iso) {
    if (!iso) return '—';
    try {
      return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch (e) { return iso; }
  }

  function escHtml(s) {
    return String(s != null ? s : '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showError(msg) {
    document.getElementById('error-msg').textContent = msg;
    document.getElementById('error-banner').classList.remove('hidden');
  }

  function hideError() {
    document.getElementById('error-banner').classList.add('hidden');
  }

  /* ── Stats cards ─────────────────────────────────────────────────── */
  function renderStats(data) {
    var map = {
      'stat-companies':     data.total_companies,
      'stat-subscriptions': data.active_subscriptions,
      'stat-users':         data.total_users,
      'stat-interviews':    data.total_ai_interviews,
      'stat-tokens':        data.total_tokens_today
    };
    Object.keys(map).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.textContent = fmtNumber(map[id]);
        el.classList.add('loaded');
        el.classList.remove('skeleton-text');
      }
    });
  }

  /* ── Recent signups table ────────────────────────────────────────── */
  var PLAN_CLASS = {
    free:       'plan-badge-free',
    starter:    'plan-badge-starter',
    pro:        'plan-badge-pro',
    enterprise: 'plan-badge-enterprise'
  };
  var STATUS_CLASS = {
    active:    'status-active',
    trial:     'status-trial',
    inactive:  'status-inactive',
    suspended: 'status-suspended'
  };

  function planBadge(plan) {
    var key = String(plan || 'free').toLowerCase();
    var cls = PLAN_CLASS[key] || 'plan-badge-free';
    return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ' + cls + '">' + escHtml(plan) + '</span>';
  }

  function statusBadge(status) {
    var key = String(status || 'inactive').toLowerCase();
    var cls = STATUS_CLASS[key] || 'status-inactive';
    return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ' + cls + '">' + escHtml(status) + '</span>';
  }

  function renderSignups(rows) {
    document.getElementById('signups-skeleton').classList.add('hidden');
    var wrap = document.getElementById('signups-table-wrap');
    wrap.classList.remove('hidden');

    var tbody = document.getElementById('signups-tbody');
    var empty = document.getElementById('signups-empty');

    if (!rows || rows.length === 0) {
      tbody.innerHTML = '';
      empty.classList.remove('hidden');
      return;
    }
    empty.classList.add('hidden');

    tbody.innerHTML = rows.map(function (r) {
      var initial = escHtml(String(r.company_name || '?').charAt(0).toUpperCase());
      return '<tr class="hover:bg-gray-50 transition">' +
        '<td class="px-6 py-3">' +
          '<div class="flex items-center gap-3">' +
            '<span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold uppercase flex-shrink-0">' + initial + '</span>' +
            '<div>' +
              '<p class="font-medium text-gray-800 text-sm">' + escHtml(r.company_name) + '</p>' +
              '<p class="text-xs text-gray-400">' + escHtml(r.email || '') + '</p>' +
            '</div>' +
          '</div>' +
        '</td>' +
        '<td class="px-6 py-3">' + planBadge(r.plan || 'Free') + '</td>' +
        '<td class="px-6 py-3 text-gray-500 text-xs whitespace-nowrap">' + fmtDate(r.joined_at) + '</td>' +
        '<td class="px-6 py-3">' + statusBadge(r.status || 'inactive') + '</td>' +
      '</tr>';
    }).join('');
  }

  /* ── Bar chart ───────────────────────────────────────────────────── */
  var CHART_COLORS = [
    'bg-indigo-400','bg-indigo-500','bg-violet-500',
    'bg-sky-500','bg-emerald-500','bg-amber-500','bg-indigo-600'
  ];

  function renderChart(days) {
    document.getElementById('chart-skeleton').classList.add('hidden');
    var wrap = document.getElementById('chart-wrap');
    wrap.classList.remove('hidden');

    if (!days || days.length === 0) return;

    var counts  = days.map(function (d) { return d.count || 0; });
    var maxVal  = Math.max.apply(null, counts.concat([1]));
    var total   = counts.reduce(function (s, c) { return s + c; }, 0);
    document.getElementById('chart-total').textContent = 'Total: ' + total;

    var HEIGHT = 150;
    var barsEl   = document.getElementById('chart-bars');
    var labelsEl = document.getElementById('chart-labels');

    barsEl.innerHTML = days.map(function (d, i) {
      var count = d.count || 0;
      var hpx   = count > 0 ? Math.max(Math.round((count / maxVal) * HEIGHT), 6) : 2;
      var color = CHART_COLORS[i % CHART_COLORS.length];
      return '<div class="chart-bar ' + color + '" style="height:' + hpx + 'px" data-value="' + count + ' signups" title="' + escHtml(d.date || '') + ': ' + count + '"></div>';
    }).join('');

    labelsEl.innerHTML = days.map(function (d) {
      return '<span class="chart-label">' + escHtml(d.label || d.date || '') + '</span>';
    }).join('');
  }

  /* ── System health ───────────────────────────────────────────────── */
  function renderHealth(health) {
    // DB
    var dbDot   = document.getElementById('db-dot');
    var dbText  = document.getElementById('db-text');
    var dbLabel = document.getElementById('db-status-label');
    var dbOk    = health.db_status === 'ok' || health.db_status === true;

    dbDot.classList.remove('bg-gray-300','bg-green-500','bg-red-500','animate-pulse');
    if (dbOk) {
      dbDot.classList.add('bg-green-500');
      dbText.textContent  = 'Connected — responding normally';
      dbLabel.textContent = 'Healthy';
      dbLabel.className   = 'text-xs font-semibold text-emerald-600';
    } else {
      dbDot.classList.add('bg-red-500');
      dbText.textContent  = health.db_message || 'Connection error';
      dbLabel.textContent = 'Degraded';
      dbLabel.className   = 'text-xs font-semibold text-red-600';
    }

    // Storage
    var storagePct     = Math.min(Math.round(health.storage_pct != null ? health.storage_pct : 0), 100);
    var storageBar     = document.getElementById('storage-bar');
    var storagePctEl   = document.getElementById('storage-pct');
    var storageDetail  = document.getElementById('storage-detail');

    storagePctEl.textContent = storagePct + '%';
    storageBar.style.width   = storagePct + '%';
    storageBar.className     = 'h-full rounded-full transition-all duration-700 ' + (
      storagePct >= 90 ? 'bg-red-500' :
      storagePct >= 70 ? 'bg-amber-500' :
      'bg-indigo-500'
    );
    storageDetail.textContent = (health.storage_used && health.storage_total)
      ? health.storage_used + ' used of ' + health.storage_total
      : storagePct + '% of capacity used';

    // Queue
    var queueCount = health.queue_depth != null ? health.queue_depth : 0;
    var queueDot   = document.getElementById('queue-dot');
    document.getElementById('queue-count').textContent = fmtNumber(queueCount);
    document.getElementById('queue-text').textContent  = 'pending job' + (queueCount !== 1 ? 's' : '');
    queueDot.classList.remove('bg-gray-300','bg-green-500','bg-amber-400','bg-red-500');
    queueDot.classList.add(queueCount > 100 ? 'bg-red-500' : queueCount > 20 ? 'bg-amber-400' : 'bg-green-500');

    // Last backup
    document.getElementById('last-backup').textContent = health.last_backup ? fmtDate(health.last_backup) : 'Unknown';
  }

  /* ── Main loader ─────────────────────────────────────────────────── */
  function loadStats() {
    hideError();
    var btn = document.getElementById('btn-refresh');
    btn.disabled = true;

    fetch('/api/v1/super/stats', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken()
      },
      credentials: 'same-origin'
    })
    .then(function (resp) {
      if (!resp.ok) throw new Error('HTTP ' + resp.status + ': ' + resp.statusText);
      return resp.json();
    })
    .then(function (json) {
      if (!json.ok) throw new Error(json.message || 'API returned an error');
      var data = json.data || {};
      renderStats(data);
      renderSignups(data.recent_signups  || []);
      renderChart(data.signups_last_7d   || []);
      renderHealth(data.health           || {});
      document.getElementById('last-updated').textContent =
        new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    })
    .catch(function (err) {
      showError('Failed to load dashboard data: ' + err.message);
      console.error('[SuperDash]', err);
    })
    .finally(function () {
      btn.disabled = false;
    });
  }

  // Boot
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadStats);
  } else {
    loadStats();
  }
})();
</script>
