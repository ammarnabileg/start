<?php
/**
 * HR Dashboard View
 * Layout: app
 */
?>
<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="min-h-screen bg-gray-50">
  <!-- Page Header -->
  <div class="bg-white border-b border-gray-200 px-6 py-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-0.5">Welcome back, <?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?></p>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-sm text-gray-400" id="last-updated">Loading...</span>
        <button onclick="loadDashboard()" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Refresh
        </button>
      </div>
    </div>
  </div>

  <div class="px-6 py-6 max-w-7xl mx-auto">

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
      <?php
      $statCards = [
        ['key' => 'total_jobs',           'label' => 'Active Jobs',           'color' => 'blue',   'icon' => 'briefcase'],
        ['key' => 'total_applications',   'label' => 'Total Applications',    'color' => 'indigo', 'icon' => 'users'],
        ['key' => 'pending_screening',    'label' => 'Pending AI Screening',  'color' => 'yellow', 'icon' => 'cpu'],
        ['key' => 'qualified',            'label' => 'Qualified Candidates',  'color' => 'green',  'icon' => 'check'],
        ['key' => 'scheduled_interviews', 'label' => 'Scheduled Interviews',  'color' => 'purple', 'icon' => 'calendar'],
        ['key' => 'pending_offers',       'label' => 'Pending Offers',        'color' => 'orange', 'icon' => 'document'],
      ];
      foreach ($statCards as $card):
      ?>
      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-lg bg-<?= $card['color'] ?>-50 flex items-center justify-center">
            <?php if ($card['icon'] === 'briefcase'): ?>
            <svg class="w-5 h-5 text-<?= $card['color'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <?php elseif ($card['icon'] === 'users'): ?>
            <svg class="w-5 h-5 text-<?= $card['color'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <?php elseif ($card['icon'] === 'cpu'): ?>
            <svg class="w-5 h-5 text-<?= $card['color'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
            <?php elseif ($card['icon'] === 'check'): ?>
            <svg class="w-5 h-5 text-<?= $card['color'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php elseif ($card['icon'] === 'calendar'): ?>
            <svg class="w-5 h-5 text-<?= $card['color'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <?php else: ?>
            <svg class="w-5 h-5 text-<?= $card['color'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <?php endif; ?>
          </div>
          <span class="text-xs font-medium text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full stat-change-badge" data-key="<?= $card['key'] ?>">—</span>
        </div>
        <div class="stat-value text-2xl font-bold text-gray-900 mb-1" data-key="<?= $card['key'] ?>">
          <div class="h-7 w-12 bg-gray-100 rounded animate-pulse inline-block"></div>
        </div>
        <p class="text-xs text-gray-500"><?= $card['label'] ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
      <!-- Recent Applications -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h2 class="text-base font-semibold text-gray-900">Recent Applications</h2>
          <a href="/applications" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View all →</a>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 text-left">
                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Candidate</th>
                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Job</th>
                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Stage</th>
                <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Applied</th>
                <th class="px-6 py-3"></th>
              </tr>
            </thead>
            <tbody id="recent-applications-body" class="divide-y divide-gray-100">
              <tr>
                <td colspan="5" class="px-6 py-10 text-center">
                  <div class="flex flex-col items-center gap-2">
                    <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-gray-400 text-sm">Loading applications...</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right column -->
      <div class="flex flex-col gap-6">
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <h2 class="text-base font-semibold text-gray-900 mb-4">Quick Actions</h2>
          <div class="flex flex-col gap-3">
            <a href="/jobs/create" class="flex items-center gap-3 p-3 bg-blue-50 hover:bg-blue-100 rounded-lg text-blue-700 font-medium text-sm transition-colors group">
              <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center group-hover:bg-blue-700 transition-colors flex-shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              </div>
              Post New Job
            </a>
            <a href="/pipeline" class="flex items-center gap-3 p-3 bg-purple-50 hover:bg-purple-100 rounded-lg text-purple-700 font-medium text-sm transition-colors group">
              <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center group-hover:bg-purple-700 transition-colors flex-shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
              </div>
              View Pipeline
            </a>
            <a href="/interviews/schedule" class="flex items-center gap-3 p-3 bg-green-50 hover:bg-green-100 rounded-lg text-green-700 font-medium text-sm transition-colors group">
              <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center group-hover:bg-green-700 transition-colors flex-shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </div>
              Schedule Interview
            </a>
            <a href="/candidates" class="flex items-center gap-3 p-3 bg-orange-50 hover:bg-orange-100 rounded-lg text-orange-700 font-medium text-sm transition-colors group">
              <div class="w-8 h-8 bg-orange-600 rounded-lg flex items-center justify-center group-hover:bg-orange-700 transition-colors flex-shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </div>
              Browse Candidates
            </a>
          </div>
        </div>

        <!-- Top Jobs Bar Chart -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 flex-1">
          <h2 class="text-base font-semibold text-gray-900 mb-4">Top Jobs by Applications</h2>
          <div id="top-jobs-chart" class="flex flex-col gap-3">
            <div class="h-4 bg-gray-100 rounded animate-pulse w-full"></div>
            <div class="h-4 bg-gray-100 rounded animate-pulse w-4/5"></div>
            <div class="h-4 bg-gray-100 rounded animate-pulse w-3/5"></div>
            <div class="h-4 bg-gray-100 rounded animate-pulse w-2/5"></div>
            <div class="h-4 bg-gray-100 rounded animate-pulse w-1/3"></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  const CSRF = document.querySelector('meta[name=csrf]').content;

  const STAGE_BADGE = {
    applied:            'bg-gray-100 text-gray-700',
    ai_screening:       'bg-yellow-100 text-yellow-800',
    qualified:          'bg-green-100 text-green-800',
    disqualified:       'bg-red-100 text-red-700',
    tech_interview:     'bg-blue-100 text-blue-800',
    manager_interview:  'bg-indigo-100 text-indigo-800',
    final_review:       'bg-purple-100 text-purple-800',
    offer:              'bg-orange-100 text-orange-800',
    hired:              'bg-emerald-100 text-emerald-800',
    rejected:           'bg-red-100 text-red-700',
    withdrawn:          'bg-gray-100 text-gray-500',
  };

  const STAGE_LABEL = {
    applied:           'Applied',
    ai_screening:      'AI Screening',
    qualified:         'Qualified',
    disqualified:      'Disqualified',
    tech_interview:    'Tech Interview',
    manager_interview: 'Manager Interview',
    final_review:      'Final Review',
    offer:             'Offer',
    hired:             'Hired',
    rejected:          'Rejected',
    withdrawn:         'Withdrawn',
  };

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return String(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function renderRecentApplications(applications) {
    const tbody = document.getElementById('recent-applications-body');
    if (!applications || !applications.length) {
      tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-gray-400 text-sm">No recent applications found.</td></tr>`;
      return;
    }
    tbody.innerHTML = applications.map(app => {
      const badgeClass = STAGE_BADGE[app.stage] || 'bg-gray-100 text-gray-700';
      const label = STAGE_LABEL[app.stage] || app.stage;
      const fn = String(app.first_name || '');
      const ln = String(app.last_name || '');
      const initials = ((fn[0] || '') + (ln[0] || '')).toUpperCase() || '?';
      return `
      <tr class="hover:bg-gray-50 transition-colors">
        <td class="px-6 py-3">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold flex-shrink-0">${escHtml(initials)}</div>
            <div>
              <div class="font-medium text-gray-900 text-sm">${escHtml(fn + ' ' + ln)}</div>
              <div class="text-xs text-gray-400">${escHtml(app.email || '')}</div>
            </div>
          </div>
        </td>
        <td class="px-6 py-3 text-sm text-gray-700 max-w-[180px] truncate">${escHtml(app.job_title || '—')}</td>
        <td class="px-6 py-3">
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${badgeClass}">${escHtml(label)}</span>
        </td>
        <td class="px-6 py-3 text-sm text-gray-500 whitespace-nowrap">${escHtml(formatDate(app.applied_at))}</td>
        <td class="px-6 py-3 text-right">
          <a href="/applications/${escHtml(String(app.id))}" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View →</a>
        </td>
      </tr>`;
    }).join('');
  }

  function renderTopJobsChart(jobs) {
    const container = document.getElementById('top-jobs-chart');
    if (!jobs || !jobs.length) {
      container.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No data available yet.</p>';
      return;
    }
    const max = Math.max(...jobs.map(j => Number(j.count) || 0), 1);
    const colors = ['bg-blue-500', 'bg-indigo-500', 'bg-purple-500', 'bg-pink-500', 'bg-orange-500'];
    container.innerHTML = jobs.slice(0, 5).map((job, i) => {
      const count = Number(job.count) || 0;
      const pct = Math.round((count / max) * 100);
      return `
      <div>
        <div class="flex items-center justify-between mb-1">
          <span class="text-xs font-medium text-gray-700 truncate max-w-[150px]" title="${escHtml(job.title)}">${escHtml(job.title)}</span>
          <span class="text-xs font-bold text-gray-600 ml-2 flex-shrink-0">${escHtml(String(count))}</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2">
          <div class="${colors[i % colors.length]} h-2 rounded-full transition-all duration-700" style="width:${pct}%"></div>
        </div>
      </div>`;
    }).join('');
  }

  window.loadDashboard = async function () {
    document.getElementById('last-updated').textContent = 'Refreshing...';
    try {
      const res = await fetch('/api/v1/dashboard/stats', {
        headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/json' }
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Failed to load stats');

      const stats = json.data.stats || {};
      ['total_jobs','total_applications','pending_screening','qualified','scheduled_interviews','pending_offers'].forEach(key => {
        const el = document.querySelector(`.stat-value[data-key="${key}"]`);
        if (el) el.textContent = (Number(stats[key]) || 0).toLocaleString();
        const badge = document.querySelector(`.stat-change-badge[data-key="${key}"]`);
        if (badge) {
          const change = stats[key + '_change'] ?? null;
          if (change !== null) {
            const n = Number(change);
            badge.textContent = (n >= 0 ? '+' : '') + n + '%';
            badge.classList.remove('text-green-600', 'text-red-500', 'text-gray-400');
            badge.classList.add(n >= 0 ? 'text-green-600' : 'text-red-500');
          }
        }
      });

      renderRecentApplications(json.data.recent_applications || []);
      renderTopJobsChart(json.data.top_jobs || []);
      document.getElementById('last-updated').textContent = 'Updated ' + new Date().toLocaleTimeString();
    } catch (e) {
      console.error('Dashboard load error:', e);
      document.getElementById('last-updated').textContent = 'Failed to load';
      document.getElementById('recent-applications-body').innerHTML = `
        <tr><td colspan="5" class="px-6 py-8 text-center text-sm text-red-500">
          Failed to load data. <button onclick="loadDashboard()" class="underline ml-1 font-medium">Retry</button>
        </td></tr>`;
    }
  };

  loadDashboard();
  setInterval(loadDashboard, 120000);
})();
</script>
