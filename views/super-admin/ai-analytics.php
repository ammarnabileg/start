<?php
ob_start();
/**
 * Super Admin – AI Usage Analytics
 * Route: /super/ai-usage -> super-admin/ai-analytics
 */
$db = Database::getInstance();

// ── Date range ──────────────────────────────────────────────────────────────
$range = (int)($_GET['range'] ?? 30);
$range = in_array($range, [7, 30, 90]) ? $range : 30;

$fromDate = date('Y-m-d 00:00:00', strtotime("-{$range} days"));
$toDate   = date('Y-m-d 23:59:59');

// ── Summary totals ──────────────────────────────────────────────────────────
$totals = Cache::remember("super_ai_totals_{$range}", 300, function () use ($db, $fromDate, $toDate) {
    return $db->fetch("
        SELECT
            COUNT(*) AS requests,
            COALESCE(SUM(total_tokens),0) AS total_tokens,
            COALESCE(SUM(cost_usd),0) AS total_cost,
            COUNT(DISTINCT tenant_id) AS active_companies
          FROM ai_usage_logs
         WHERE created_at BETWEEN ? AND ?
    ", [$fromDate, $toDate]) ?? [];
});

$avgPerCompany = ($totals['active_companies'] ?? 0) > 0
    ? round(($totals['total_tokens'] ?? 0) / ($totals['active_companies']), 0)
    : 0;

// ── Usage by company ────────────────────────────────────────────────────────
$byCompany = Cache::remember("super_ai_by_company_{$range}", 300, function () use ($db, $fromDate, $toDate) {
    return $db->fetchAll("
        SELECT t.name AS company_name, t.plan,
               COALESCE(SUM(l.total_tokens),0) AS tokens,
               COALESCE(SUM(l.cost_usd),0) AS cost,
               COUNT(*) AS requests,
               (SELECT l2.feature FROM ai_usage_logs l2
                 WHERE l2.tenant_id = l.tenant_id AND l2.created_at BETWEEN ? AND ?
                 GROUP BY l2.feature ORDER BY COUNT(*) DESC LIMIT 1) AS top_feature
          FROM ai_usage_logs l
          JOIN tenants t ON t.id = l.tenant_id
         WHERE l.created_at BETWEEN ? AND ?
         GROUP BY l.tenant_id
         ORDER BY tokens DESC
         LIMIT 50
    ", [$fromDate, $toDate, $fromDate, $toDate]) ?? [];
});

// ── Usage by feature ────────────────────────────────────────────────────────
$byFeature = Cache::remember("super_ai_by_feature_{$range}", 300, function () use ($db, $fromDate, $toDate) {
    return $db->fetchAll("
        SELECT feature,
               COUNT(*) AS requests,
               COALESCE(SUM(total_tokens),0) AS tokens,
               COALESCE(SUM(cost_usd),0) AS cost
          FROM ai_usage_logs
         WHERE created_at BETWEEN ? AND ?
         GROUP BY feature
         ORDER BY cost DESC
    ", [$fromDate, $toDate]) ?? [];
});

// ── Daily usage for chart ───────────────────────────────────────────────────
$dailyUsage = Cache::remember("super_ai_daily_{$range}", 300, function () use ($db, $fromDate, $toDate) {
    return $db->fetchAll("
        SELECT DATE(created_at) AS day,
               COALESCE(SUM(total_tokens),0) AS tokens,
               COALESCE(SUM(cost_usd),0) AS cost,
               COUNT(*) AS requests
          FROM ai_usage_logs
         WHERE created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at)
         ORDER BY day ASC
    ", [$fromDate, $toDate]) ?? [];
});

// ── Top expensive single actions ────────────────────────────────────────────
$topExpensive = $db->fetchAll("
    SELECT l.feature, l.model, l.total_tokens, l.cost_usd, l.created_at,
           t.name AS company_name
      FROM ai_usage_logs l
      LEFT JOIN tenants t ON t.id = l.tenant_id
     WHERE l.created_at BETWEEN ? AND ?
     ORDER BY l.cost_usd DESC
     LIMIT 10
", [$fromDate, $toDate]) ?? [];

$featureColors = ['#7C3AED','#F59E0B','#10B981','#3B82F6','#EC4899','#6366F1','#F97316','#14B8A6','#8B5CF6','#EF4444'];
?>

<!-- Header ----------------------------------------------------------------->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
  <p class="text-gray-500 text-sm">Platform-wide AI usage and cost analytics.</p>
  <!-- Date range selector -->
  <div class="flex items-center gap-1 bg-white rounded-2xl shadow-sm border border-gray-100 p-1">
    <?php foreach ([7 => '7 Days', 30 => '30 Days', 90 => '90 Days'] as $d => $l): ?>
    <a href="?range=<?= $d ?>"
       class="px-3 py-1.5 rounded-xl text-sm font-medium transition-colors <?= $range === $d ? 'bg-violet-600 text-white' : 'text-gray-600 hover:bg-gray-50' ?>">
      <?= $l ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Summary Cards ---------------------------------------------------------->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
  <?php
  $summaryCards = [
    ['Total Tokens',       number_format((int)($totals['total_tokens'] ?? 0)), 'bg-violet-100 text-violet-700', 'M13 10V3L4 14h7v7l9-11h-7z'],
    ['Total Cost (USD)',   '$' . number_format((float)($totals['total_cost'] ?? 0), 4), 'bg-amber-100 text-amber-700', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['Total Requests',     number_format((int)($totals['requests'] ?? 0)), 'bg-blue-100 text-blue-700', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
    ['Avg Tokens / Co.',   number_format($avgPerCompany), 'bg-emerald-100 text-emerald-700', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
  ];
  foreach ($summaryCards as [$label, $value, $iconCls, $path]): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
    <div class="<?= $iconCls ?> w-11 h-11 rounded-xl flex items-center justify-center mb-4">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $path ?>"/>
      </svg>
    </div>
    <div class="text-2xl font-bold text-gray-900 mb-1"><?= $value ?></div>
    <div class="text-sm text-gray-500"><?= $label ?></div>
    <div class="text-xs text-gray-400 mt-0.5">Last <?= $range ?> days</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Main Grid ------------------------------------------------------------->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

  <!-- Daily Usage Chart -->
  <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="font-semibold text-gray-900 mb-5">Daily Token Usage</h2>
    <canvas id="dailyChart" height="100"></canvas>
  </div>

  <!-- Feature Breakdown -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="font-semibold text-gray-900 mb-5">Usage by Feature</h2>
    <?php if (empty($byFeature)): ?>
    <div class="text-center py-10 text-gray-400 text-sm">No data available.</div>
    <?php else: ?>
    <canvas id="featurePieChart" height="180"></canvas>
    <div class="mt-4 space-y-2">
      <?php foreach (array_slice($byFeature, 0, 6) as $i => $f): ?>
      <div class="flex items-center justify-between text-sm">
        <div class="flex items-center gap-2">
          <div class="w-3 h-3 rounded-full flex-shrink-0" style="background:<?= $featureColors[$i % count($featureColors)] ?>"></div>
          <span class="text-gray-700 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $f['feature'])) ?></span>
        </div>
        <span class="text-gray-500 font-medium"><?= number_format((int)$f['tokens']) ?> tokens</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Usage by Company -------------------------------------------------------->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
  <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
    <h2 class="font-semibold text-gray-900">Usage by Company</h2>
    <span class="text-xs text-gray-400"><?= count($byCompany) ?> active companies</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead>
        <tr class="bg-gray-50">
          <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
          <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
          <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Tokens</th>
          <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Cost (USD)</th>
          <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Requests</th>
          <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Top Feature</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($byCompany)): ?>
        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">No AI usage in this period.</td></tr>
        <?php else: ?>
        <?php
        $maxTokens = max(1, max(array_column($byCompany, 'tokens')));
        foreach ($byCompany as $row):
          $pct = min(100, round(($row['tokens'] / $maxTokens) * 100));
          $planCls = match($row['plan'] ?? 'starter') {
            'enterprise'   => 'bg-violet-100 text-violet-700',
            'professional' => 'bg-blue-100 text-blue-700',
            default        => 'bg-gray-100 text-gray-600',
          };
        ?>
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-6 py-4">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 bg-violet-100 rounded-lg flex items-center justify-center text-violet-700 font-bold text-xs flex-shrink-0">
                <?= strtoupper(substr($row['company_name'] ?? 'C', 0, 1)) ?>
              </div>
              <div>
                <div class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($row['company_name'] ?? '—') ?></div>
                <div class="w-24 h-1.5 bg-gray-100 rounded-full mt-1.5 overflow-hidden">
                  <div class="h-full bg-violet-500 rounded-full" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
            </div>
          </td>
          <td class="px-6 py-4">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $planCls ?>">
              <?= htmlspecialchars(ucfirst($row['plan'] ?? 'starter')) ?>
            </span>
          </td>
          <td class="px-6 py-4 text-right text-sm font-medium text-gray-900"><?= number_format((int)$row['tokens']) ?></td>
          <td class="px-6 py-4 text-right text-sm text-gray-700">$<?= number_format((float)$row['cost'], 4) ?></td>
          <td class="px-6 py-4 text-right text-sm text-gray-700"><?= number_format((int)$row['requests']) ?></td>
          <td class="px-6 py-4">
            <span class="text-xs text-gray-600 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $row['top_feature'] ?? '—')) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Top Expensive Actions --------------------------------------------------->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="px-6 py-4 border-b border-gray-50">
    <h2 class="font-semibold text-gray-900">Most Expensive Requests</h2>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead>
        <tr class="bg-gray-50">
          <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Feature</th>
          <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
          <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Model</th>
          <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Tokens</th>
          <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Cost</th>
          <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php if (empty($topExpensive)): ?>
        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">No data available.</td></tr>
        <?php else: ?>
        <?php foreach ($topExpensive as $r): ?>
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-6 py-4">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 capitalize">
              <?= htmlspecialchars(str_replace('_', ' ', $r['feature'] ?? '—')) ?>
            </span>
          </td>
          <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($r['company_name'] ?? '—') ?></td>
          <td class="px-6 py-4 text-xs text-gray-500 font-mono"><?= htmlspecialchars($r['model'] ?? '—') ?></td>
          <td class="px-6 py-4 text-right text-sm font-medium text-gray-900"><?= number_format((int)$r['total_tokens']) ?></td>
          <td class="px-6 py-4 text-right text-sm font-semibold text-red-600">$<?= number_format((float)$r['cost_usd'], 5) ?></td>
          <td class="px-6 py-4 text-xs text-gray-400"><?= isset($r['created_at']) ? date('M j H:i', strtotime($r['created_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function loadChart() {
  if (window.Chart) { return drawCharts(); }
  const s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
  s.onload = drawCharts;
  document.head.appendChild(s);
})();

function drawCharts() {
  // ── Daily line chart ──────────────────────────────────────────────────────
  const dailyLabels = <?= json_encode(array_column($dailyUsage, 'day')) ?>;
  const dailyTokens = <?= json_encode(array_map(fn($r) => (int)$r['tokens'], $dailyUsage)) ?>;
  const dailyCosts  = <?= json_encode(array_map(fn($r) => round((float)$r['cost'], 4), $dailyUsage)) ?>;

  if (dailyLabels.length && document.getElementById('dailyChart')) {
    new Chart(document.getElementById('dailyChart'), {
      type: 'bar',
      data: {
        labels: dailyLabels,
        datasets: [
          {
            label: 'Tokens',
            data: dailyTokens,
            backgroundColor: 'rgba(124,58,237,0.15)',
            borderColor: '#7C3AED',
            borderWidth: 2,
            borderRadius: 4,
            yAxisID: 'y',
          },
          {
            label: 'Cost (USD)',
            data: dailyCosts,
            type: 'line',
            borderColor: '#F59E0B',
            backgroundColor: 'transparent',
            borderWidth: 2,
            pointBackgroundColor: '#F59E0B',
            pointRadius: 3,
            tension: 0.4,
            yAxisID: 'y1',
          }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
        scales: {
          x: { grid: { display: false }, ticks: { maxRotation: 0, font: { size: 10 } } },
          y:  { grid: { color: '#F3F4F6' }, ticks: { font: { size: 10 } }, position: 'left' },
          y1: { position: 'right', grid: { display: false }, ticks: { callback: v => '$' + v, font: { size: 10 } } }
        }
      }
    });
  }

  // ── Feature pie chart ─────────────────────────────────────────────────────
  const pieEl = document.getElementById('featurePieChart');
  if (!pieEl) return;
  const featureLabels = <?= json_encode(array_map(fn($f) => str_replace('_', ' ', $f['feature']), $byFeature)) ?>;
  const featureTokens = <?= json_encode(array_map(fn($f) => (int)$f['tokens'], $byFeature)) ?>;
  const pieColors     = <?= json_encode($featureColors) ?>;

  if (featureLabels.length) {
    new Chart(pieEl, {
      type: 'doughnut',
      data: {
        labels: featureLabels,
        datasets: [{
          data: featureTokens,
          backgroundColor: pieColors,
          borderWidth: 0,
          hoverOffset: 6,
        }]
      },
      options: {
        responsive: true,
        cutout: '65%',
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed.toLocaleString() + ' tokens' } }
        }
      }
    });
  }
}
</script>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/app.php'; ?>
