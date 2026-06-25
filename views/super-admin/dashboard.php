<?php
/**
 * Super Admin Dashboard
 * Rendered by SuperAdminRouter -> views/layouts/app.php wraps this content.
 */
$db = Database::getInstance();

// ── Global stats (cached 5 min) ─────────────────────────────────────────────
$stats = Cache::remember('super_dashboard_stats', 300, function () use ($db) {
    return [
        'total_companies'  => $db->fetchColumn("SELECT COUNT(*) FROM tenants") ?? 0,
        'active_companies' => $db->fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'active'") ?? 0,
        'total_users'      => $db->fetchColumn("SELECT COUNT(*) FROM users") ?? 0,
        'interviews_30d'   => $db->fetchColumn(
            "SELECT COUNT(*) FROM interviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ) ?? 0,
        'tokens_30d'       => $db->fetchColumn(
            "SELECT COALESCE(SUM(total_tokens),0) FROM ai_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ) ?? 0,
    ];
});

// ── Recent companies ─────────────────────────────────────────────────────────
$recentCompanies = Cache::remember('super_recent_companies', 120, function () use ($db) {
    return $db->fetchAll("
        SELECT t.*,
               COUNT(DISTINCT u.id) AS user_count,
               (SELECT COUNT(*) FROM interviews i
                  JOIN applications a ON a.id = i.application_id
                 WHERE a.tenant_id = t.id AND i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS active_interviews
          FROM tenants t
          LEFT JOIN users u ON u.tenant_id = t.id
         GROUP BY t.id
         ORDER BY t.created_at DESC
         LIMIT 10
    ") ?? [];
});

// ── System health ────────────────────────────────────────────────────────────
$sysHealth = [
    'php_version'  => PHP_VERSION,
    'mysql_version'=> $db->fetchColumn("SELECT VERSION()") ?? '—',
    'disk_free'    => @disk_free_space('/'),
    'disk_total'   => @disk_total_space('/'),
    'memory_limit' => ini_get('memory_limit'),
    'cache_status' => class_exists('Cache') ? 'OK' : 'Unavailable',
];

// ── AI usage (last 30 days daily) ───────────────────────────────────────────
$aiDailyUsage = Cache::remember('super_ai_daily_30d', 300, function () use ($db) {
    return $db->fetchAll("
        SELECT DATE(created_at) AS day, COALESCE(SUM(total_tokens),0) AS tokens
          FROM ai_usage_logs
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at)
         ORDER BY day ASC
    ") ?? [];
});

$diskPct = ($sysHealth['disk_total'] > 0)
    ? round((($sysHealth['disk_total'] - $sysHealth['disk_free']) / $sysHealth['disk_total']) * 100)
    : 0;

function statusBadge(string $status): string {
    return match($status) {
        'active'    => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Active</span>',
        'suspended' => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Suspended</span>',
        'archived'  => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Archived</span>',
        default     => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">' . htmlspecialchars(ucfirst($status)) . '</span>',
    };
}
?>

<!-- Header ----------------------------------------------------------------->
<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-bold text-gray-900">Super Admin Dashboard</h1>
    <p class="text-gray-500 text-sm mt-1">Global platform overview and system health</p>
  </div>
  <div class="flex gap-2">
    <a href="/super/companies" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors">
      + Add Company
    </a>
    <button onclick="clearCache()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">
      Clear Cache
    </button>
  </div>
</div>

<!-- Stat Cards ------------------------------------------------------------->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
  <?php
  $cards = [
    ['Total Companies',       $stats['total_companies'],  'bg-violet-100 text-violet-700', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
    ['Active Companies',      $stats['active_companies'], 'bg-emerald-100 text-emerald-700','M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['Total Users',           $stats['total_users'],      'bg-blue-100 text-blue-700',     'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
    ['AI Interviews (30d)',   $stats['interviews_30d'],   'bg-amber-100 text-amber-700',   'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
    ['AI Tokens (30d)',       number_format((int)$stats['tokens_30d']), 'bg-indigo-100 text-indigo-700', 'M13 10V3L4 14h7v7l9-11h-7z'],
  ];
  foreach ($cards as [$label, $value, $iconCls, $path]): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
    <div class="<?= $iconCls ?> w-11 h-11 rounded-xl flex items-center justify-center mb-4">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $path ?>"/>
      </svg>
    </div>
    <div class="text-3xl font-bold text-gray-900 mb-1"><?= is_numeric($value) ? number_format((int)$value) : htmlspecialchars($value) ?></div>
    <div class="text-sm font-medium text-gray-600"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Main Grid ------------------------------------------------------------->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

  <!-- Recent Companies Table -->
  <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
      <h2 class="font-semibold text-gray-900">Recent Companies</h2>
      <a href="/super/companies" class="text-sm text-violet-600 hover:text-violet-800 font-medium">View all →</a>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 text-left">
            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Users</th>
            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Interviews</th>
            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Joined</th>
            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($recentCompanies)): ?>
          <tr><td colspan="7" class="px-6 py-10 text-center text-gray-400 text-sm">No companies yet</td></tr>
          <?php else: ?>
          <?php foreach ($recentCompanies as $co): ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-violet-100 rounded-xl flex items-center justify-center text-violet-700 font-bold text-sm flex-shrink-0">
                  <?= strtoupper(substr($co['name'] ?? 'C', 0, 1)) ?>
                </div>
                <div>
                  <div class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($co['name'] ?? '') ?></div>
                  <div class="text-xs text-gray-400"><?= htmlspecialchars($co['slug'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-violet-50 text-violet-700"><?= htmlspecialchars(ucfirst($co['plan'] ?? 'starter')) ?></span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-700"><?= (int)($co['user_count'] ?? 0) ?></td>
            <td class="px-6 py-4 text-sm text-gray-700"><?= (int)($co['active_interviews'] ?? 0) ?></td>
            <td class="px-6 py-4 text-xs text-gray-400"><?= isset($co['created_at']) ? date('M j, Y', strtotime($co['created_at'])) : '—' ?></td>
            <td class="px-6 py-4"><?= statusBadge($co['status'] ?? 'active') ?></td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-2">
                <a href="/super/companies?view=<?= (int)$co['id'] ?>" class="text-xs text-violet-600 hover:text-violet-800 font-medium">View</a>
                <span class="text-gray-300">|</span>
                <button onclick="impersonateCompany(<?= (int)$co['id'] ?>)" class="text-xs text-amber-600 hover:text-amber-800 font-medium">Impersonate</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Column -->
  <div class="space-y-5">

    <!-- Quick Actions -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <h3 class="font-semibold text-gray-900 mb-4">Quick Actions</h3>
      <div class="space-y-2">
        <a href="/super/companies" class="flex items-center gap-3 p-3 bg-violet-600 hover:bg-violet-700 text-white rounded-xl transition-colors text-sm font-semibold">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Add Company
        </a>
        <a href="/super/users" class="flex items-center gap-3 p-3 border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl transition-colors text-sm font-medium">
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          View All Users
        </a>
        <a href="/super/settings" class="flex items-center gap-3 p-3 border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl transition-colors text-sm font-medium">
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          System Settings
        </a>
        <button onclick="clearCache()" class="w-full flex items-center gap-3 p-3 border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl transition-colors text-sm font-medium">
          <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Clear Cache
        </button>
      </div>
    </div>

    <!-- System Health -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <h3 class="font-semibold text-gray-900 mb-4">System Health</h3>
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-500">PHP Version</span>
          <span class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($sysHealth['php_version']) ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-500">MySQL Version</span>
          <span class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($sysHealth['mysql_version']) ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-500">Memory Limit</span>
          <span class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($sysHealth['memory_limit']) ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-500">Cache Status</span>
          <span class="text-sm font-semibold <?= $sysHealth['cache_status'] === 'OK' ? 'text-emerald-600' : 'text-red-600' ?>"><?= htmlspecialchars($sysHealth['cache_status']) ?></span>
        </div>
        <div class="pt-2">
          <div class="flex items-center justify-between text-sm mb-1.5">
            <span class="text-gray-500">Disk Usage</span>
            <span class="font-semibold text-gray-900"><?= $diskPct ?>%</span>
          </div>
          <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all <?= $diskPct > 85 ? 'bg-red-500' : ($diskPct > 70 ? 'bg-amber-500' : 'bg-emerald-500') ?>" style="width:<?= $diskPct ?>%"></div>
          </div>
          <div class="text-xs text-gray-400 mt-1">
            <?= $sysHealth['disk_free'] !== false ? round($sysHealth['disk_free'] / 1073741824, 1) . ' GB free' : '—' ?>
            <?= $sysHealth['disk_total'] !== false ? ' / ' . round($sysHealth['disk_total'] / 1073741824, 1) . ' GB total' : '' ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- AI Usage Chart --------------------------------------------------------->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
  <div class="flex items-center justify-between mb-5">
    <h2 class="font-semibold text-gray-900">AI Token Usage — Last 30 Days</h2>
    <a href="/super/ai-usage" class="text-sm text-violet-600 hover:text-violet-800 font-medium">Full Analytics →</a>
  </div>
  <canvas id="aiUsageChart" height="80"></canvas>
</div>

<script>
// ── Chart.js via CDN ────────────────────────────────────────────────────────
(function loadChart() {
  if (window.Chart) { return drawChart(); }
  const s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
  s.onload = drawChart;
  document.head.appendChild(s);
})();

function drawChart() {
  const labels = <?= json_encode(array_column($aiDailyUsage, 'day')) ?>;
  const data   = <?= json_encode(array_map(fn($r) => (int)$r['tokens'], $aiDailyUsage)) ?>;

  if (!labels.length) {
    document.getElementById('aiUsageChart').closest('.bg-white').querySelector('canvas').insertAdjacentHTML('beforebegin',
      '<p class="text-center text-gray-400 text-sm py-10">No AI usage data for the last 30 days.</p>'
    );
    document.getElementById('aiUsageChart').style.display = 'none';
    return;
  }

  new Chart(document.getElementById('aiUsageChart'), {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Tokens',
        data,
        borderColor: '#7C3AED',
        backgroundColor: 'rgba(124,58,237,0.08)',
        borderWidth: 2,
        pointBackgroundColor: '#7C3AED',
        pointRadius: 3,
        tension: 0.4,
        fill: true,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { maxRotation: 0, font: { size: 11 } } },
        y: { grid: { color: '#F3F4F6' }, ticks: { font: { size: 11 } } }
      }
    }
  });
}

// ── Actions ─────────────────────────────────────────────────────────────────
async function clearCache() {
  try {
    const r = await fetch('/api/v1/admin?action=terminal', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ command: 'cache:clear' })
    });
    const d = await r.json();
    showToast(d.ok ? 'Cache cleared successfully.' : (d.message || 'Failed to clear cache.'), d.ok ? 'success' : 'error');
  } catch (e) {
    showToast('Request failed.', 'error');
  }
}

function impersonateCompany(id) {
  if (!confirm('Impersonate this company owner? You will be redirected to their dashboard.')) return;
  window.location.href = '/super/impersonate?tenant_id=' + id;
}
</script>
