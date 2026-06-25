<?php
require_once __DIR__ . '/../partials/helpers.php';
$pageTitle = 'AI Analytics';
$activeNav = 'ai-analytics';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'AI Analytics']];
$db  = Database::getInstance();
$tid = Auth::user()['tenant_id'];
$aiMissingOpenAI = !ApiKeyManager::hasTenantOpenAIKey();
$aiFeatureLabel  = 'AI Analytics';
ob_start();
?>
<?php require VIEWS_PATH . '/partials/ai-keys-banner.php'; ?>
<?php
$days = (int)($_GET['days'] ?? 30);
$days = in_array($days, [7, 30, 90]) ? $days : 30;
$since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
// fetch from ai_usage_logs where tenant_id = $tid and created_at >= $since

// Summary stats
$totalRequests = (int)($db->fetchColumn(
    "SELECT COUNT(*) FROM ai_usage_logs WHERE tenant_id = ? AND created_at >= ?",
    [$tid, $since]
) ?? 0);

$totalTokens = (int)($db->fetchColumn(
    "SELECT COALESCE(SUM(total_tokens), 0) FROM ai_usage_logs WHERE tenant_id = ? AND created_at >= ?",
    [$tid, $since]
) ?? 0);

$estimatedCost = round($totalTokens / 1000 * 0.002, 4);

$mostUsedFeature = $db->fetchColumn(
    "SELECT feature FROM ai_usage_logs
     WHERE tenant_id = ? AND created_at >= ?
     GROUP BY feature ORDER BY COUNT(*) DESC LIMIT 1",
    [$tid, $since]
) ?? '—';

// Usage breakdown by feature
$breakdown = $db->fetchAll(
    "SELECT feature,
            COUNT(*) as request_count,
            COALESCE(SUM(total_tokens), 0) as total_tokens,
            COALESCE(AVG(total_tokens), 0) as avg_tokens
     FROM ai_usage_logs
     WHERE tenant_id = ? AND created_at >= ?
     GROUP BY feature
     ORDER BY request_count DESC",
    [$tid, $since]
) ?: [];

// Daily usage for chart (last N days)
$dailyUsage = $db->fetchAll(
    "SELECT DATE(created_at) as day,
            COUNT(*) as requests,
            COALESCE(SUM(total_tokens), 0) as tokens
     FROM ai_usage_logs
     WHERE tenant_id = ? AND created_at >= ?
     GROUP BY DATE(created_at)
     ORDER BY day ASC",
    [$tid, $since]
) ?: [];

// Fill missing days with zeros
$dailyMap = [];
foreach ($dailyUsage as $row) {
    $dailyMap[$row['day']] = $row;
}
$chartLabels   = [];
$chartRequests = [];
$chartTokens   = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[]   = date('M j', strtotime($d));
    $chartRequests[] = isset($dailyMap[$d]) ? (int)$dailyMap[$d]['requests'] : 0;
    $chartTokens[]   = isset($dailyMap[$d]) ? (int)$dailyMap[$d]['tokens']   : 0;
}

// Recent logs
$recentLogs = $db->fetchAll(
    "SELECT l.*, CONCAT(u.first_name,' ',u.last_name) as user_name
     FROM ai_usage_logs l
     LEFT JOIN users u ON u.id = l.user_id
     WHERE l.tenant_id = ? AND l.created_at >= ?
     ORDER BY l.created_at DESC
     LIMIT 50",
    [$tid, $since]
) ?: [];

// Feature display names
$featureNames = [
    'cv_screening'      => 'CV Screening',
    'job_description'   => 'Job Description',
    'interview_conduct' => 'AI Interview',
    'interview_eval'    => 'Interview Evaluation',
    'offer_letter'      => 'Offer Letter',
    'email_compose'     => 'Email Compose',
    'talent_match'      => 'Talent Matching',
    'video_avatar'      => 'Video Avatar',
    'summary'           => 'Candidate Summary',
    'chat'              => 'AI Chat',
];
function featureName(string $key, array $map): string {
    return $map[$key] ?? ucwords(str_replace('_', ' ', $key));
}

$featureIcons = [
    'cv_screening'      => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'job_description'   => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    'interview_conduct' => 'M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
    'interview_eval'    => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    'offer_letter'      => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
];
function featureIcon(string $key, array $icons): string {
    return $icons[$key] ?? 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z';
}
?>

<div class="fade-in">
  <!-- Page Header -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">AI Analytics</h1>
      <p class="text-sm text-gray-500 mt-0.5">Monitor AI usage, costs, and performance across your workspace</p>
    </div>

    <!-- Date Range Filter -->
    <div class="flex items-center gap-1 bg-white border border-gray-200 rounded-full p-1 shadow-sm">
      <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $label): ?>
      <a href="?days=<?= $d ?>"
        class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors <?= $days === $d ? 'bg-violet-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50' ?>">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <!-- Total Requests -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between mb-4">
        <div class="w-11 h-11 bg-violet-100 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
        </div>
        <span class="text-xs text-gray-400 font-medium"><?= $days ?>d</span>
      </div>
      <div class="text-3xl font-bold text-gray-900 mb-1"><?= number_format($totalRequests) ?></div>
      <div class="text-sm font-medium text-gray-700">Total AI Requests</div>
      <div class="text-xs text-gray-400 mt-0.5">Across all features</div>
    </div>

    <!-- Total Tokens -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between mb-4">
        <div class="w-11 h-11 bg-blue-100 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
          </svg>
        </div>
        <span class="text-xs text-gray-400 font-medium"><?= $days ?>d</span>
      </div>
      <div class="text-3xl font-bold text-gray-900 mb-1"><?= $totalTokens === 0 ? '0' : ($totalTokens >= 1000000 ? number_format($totalTokens / 1000000, 1) . 'M' : number_format($totalTokens / 1000, 1) . 'K') ?></div>
      <div class="text-sm font-medium text-gray-700">Tokens Used</div>
      <div class="text-xs text-gray-400 mt-0.5">Prompt + completion</div>
    </div>

    <!-- Estimated Cost -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between mb-4">
        <div class="w-11 h-11 bg-emerald-100 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <span class="text-xs text-gray-400 font-medium">@ $0.002/1K</span>
      </div>
      <div class="text-3xl font-bold text-gray-900 mb-1">$<?= number_format($estimatedCost, 2) ?></div>
      <div class="text-sm font-medium text-gray-700">Estimated Cost</div>
      <div class="text-xs text-gray-400 mt-0.5">Based on token usage</div>
    </div>

    <!-- Most Used Feature -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between mb-4">
        <div class="w-11 h-11 bg-amber-100 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
          </svg>
        </div>
        <span class="text-xs text-amber-500 font-semibold bg-amber-50 rounded-full px-2 py-0.5">Top</span>
      </div>
      <div class="text-lg font-bold text-gray-900 mb-1 truncate">
        <?= htmlspecialchars(featureName($mostUsedFeature, $featureNames)) ?>
      </div>
      <div class="text-sm font-medium text-gray-700">Most Used Feature</div>
      <div class="text-xs text-gray-400 mt-0.5">In the last <?= $days ?> days</div>
    </div>
  </div>

  <!-- Chart + Breakdown Row -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">

    <!-- Daily Usage Chart -->
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h2 class="text-sm font-semibold text-gray-900">Daily Usage</h2>
          <p class="text-xs text-gray-400 mt-0.5">AI requests over the past <?= $days ?> days</p>
        </div>
        <div class="flex items-center gap-4 text-xs text-gray-500">
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-violet-500 inline-block"></span>Requests
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-amber-400 inline-block"></span>Tokens (÷1K)
          </span>
        </div>
      </div>
      <div class="relative" style="height:240px">
        <canvas id="aiUsageChart"></canvas>
      </div>
    </div>

    <!-- Feature Breakdown Donut -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h2 class="text-sm font-semibold text-gray-900 mb-5">Feature Split</h2>
      <div class="relative mx-auto" style="height:180px;width:180px">
        <canvas id="aiDonutChart"></canvas>
        <div class="absolute inset-0 flex items-center justify-center flex-col pointer-events-none">
          <span class="text-2xl font-bold text-gray-900"><?= number_format($totalRequests) ?></span>
          <span class="text-xs text-gray-400">total</span>
        </div>
      </div>
      <div class="mt-5 space-y-2" id="donutLegend">
        <?php foreach (array_slice($breakdown, 0, 5) as $row):
          $pct = $totalRequests > 0 ? round($row['request_count'] / $totalRequests * 100, 1) : 0;
        ?>
        <div class="flex items-center justify-between text-xs">
          <span class="text-gray-600 font-medium truncate"><?= htmlspecialchars(featureName($row['feature'], $featureNames)) ?></span>
          <span class="text-gray-400 ml-2 flex-shrink-0"><?= $pct ?>%</span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($breakdown)): ?>
        <p class="text-xs text-gray-400 text-center py-4">No data for this period.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Usage Breakdown Table -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-5">
    <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
      <div>
        <h2 class="text-sm font-semibold text-gray-900">Usage Breakdown by Feature</h2>
        <p class="text-xs text-gray-400 mt-0.5">Detailed stats for each AI capability</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Feature</th>
            <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Requests</th>
            <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Tokens</th>
            <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Cost</th>
            <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Avg / Request</th>
            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3 w-32">Usage</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($breakdown)): ?>
          <tr>
            <td colspan="6" class="text-center py-16">
              <div class="flex flex-col items-center gap-2 text-gray-400">
                <svg class="w-10 h-10 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <p class="text-sm font-medium">No AI usage in this period</p>
                <p class="text-xs">AI requests will appear here once features are used.</p>
              </div>
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($breakdown as $row):
            $cost   = round($row['total_tokens'] / 1000 * 0.002, 4);
            $avg    = round($row['avg_tokens']);
            $pct    = $totalRequests > 0 ? round($row['request_count'] / $totalRequests * 100, 1) : 0;
            $icon   = featureIcon($row['feature'], $featureIcons);
            $fname  = featureName($row['feature'], $featureNames);
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-violet-50 rounded-lg flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
                  </svg>
                </div>
                <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($fname) ?></span>
              </div>
            </td>
            <td class="px-6 py-4 text-right">
              <span class="text-sm font-semibold text-gray-900"><?= number_format($row['request_count']) ?></span>
            </td>
            <td class="px-6 py-4 text-right">
              <span class="text-sm text-gray-700"><?= number_format($row['total_tokens']) ?></span>
            </td>
            <td class="px-6 py-4 text-right">
              <span class="text-sm font-medium <?= $cost > 1 ? 'text-amber-600' : 'text-gray-700' ?>">$<?= number_format($cost, 3) ?></span>
            </td>
            <td class="px-6 py-4 text-right">
              <span class="text-sm text-gray-500"><?= number_format($avg) ?> tok</span>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                  <div class="bg-violet-500 h-1.5 rounded-full" style="width:<?= min($pct, 100) ?>%"></div>
                </div>
                <span class="text-xs text-gray-400 w-10 text-right"><?= $pct ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <?php if (!empty($breakdown)): ?>
        <tfoot class="bg-gray-50 border-t border-gray-100">
          <tr>
            <td class="px-6 py-3 text-xs font-semibold text-gray-600">Total</td>
            <td class="px-6 py-3 text-right text-xs font-semibold text-gray-900"><?= number_format($totalRequests) ?></td>
            <td class="px-6 py-3 text-right text-xs font-semibold text-gray-900"><?= number_format($totalTokens) ?></td>
            <td class="px-6 py-3 text-right text-xs font-semibold text-gray-900">$<?= number_format($estimatedCost, 3) ?></td>
            <td class="px-6 py-3 text-right text-xs text-gray-400">
              <?= $totalRequests > 0 ? number_format(round($totalTokens / $totalRequests)) : '0' ?> tok
            </td>
            <td class="px-6 py-3"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- Recent AI Logs -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
      <div>
        <h2 class="text-sm font-semibold text-gray-900">Recent AI Requests</h2>
        <p class="text-xs text-gray-400 mt-0.5">Last 50 requests — most recent first</p>
      </div>
      <button onclick="exportLogs()"
        class="text-xs text-violet-600 hover:text-violet-800 font-medium flex items-center gap-1.5 border border-violet-200 hover:border-violet-400 rounded-full px-3 py-1.5 transition-all">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Export CSV
      </button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Timestamp</th>
            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Action</th>
            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Model</th>
            <th class="text-right text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Tokens</th>
            <th class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider px-6 py-3">Triggered by</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php if (empty($recentLogs)): ?>
          <tr>
            <td colspan="5" class="text-center py-12 text-gray-400 text-sm">No AI requests found for this period.</td>
          </tr>
          <?php else: ?>
          <?php foreach ($recentLogs as $log):
            $ts = date('M j, Y · H:i', strtotime($log['created_at']));
            $fname = featureName($log['feature'] ?? '', $featureNames);
            $model = $log['model'] ?? 'gpt-4o';
            $tokens = (int)($log['total_tokens'] ?? 0);
            $triggeredBy = $log['user_name'] ?? 'System';
            $modelColor = str_contains($model, '4o') ? 'bg-violet-50 text-violet-700' : (str_contains($model, 'mini') ? 'bg-blue-50 text-blue-700' : 'bg-gray-100 text-gray-600');
          ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-3 text-xs text-gray-500 whitespace-nowrap font-mono"><?= $ts ?></td>
            <td class="px-6 py-3">
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-800 bg-violet-50 rounded-full px-2.5 py-1">
                <svg class="w-3 h-3 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                <?= htmlspecialchars($fname) ?>
              </span>
            </td>
            <td class="px-6 py-3">
              <span class="text-xs font-mono font-medium <?= $modelColor ?> rounded-full px-2.5 py-1"><?= htmlspecialchars($model) ?></span>
            </td>
            <td class="px-6 py-3 text-right">
              <span class="text-sm font-medium <?= $tokens > 4000 ? 'text-amber-600' : 'text-gray-700' ?>"><?= number_format($tokens) ?></span>
            </td>
            <td class="px-6 py-3">
              <div class="flex items-center gap-2">
                <?php
                $parts = array_filter(explode(' ', $triggeredBy));
                $initials = strtoupper(implode('', array_map(fn($p) => $p[0], array_slice(array_values($parts), 0, 2)))) ?: '?';
                ?>
                <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600 flex-shrink-0">
                  <?= $initials ?>
                </div>
                <span class="text-sm text-gray-700"><?= htmlspecialchars($triggeredBy) ?></span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (count($recentLogs) === 50): ?>
    <div class="px-6 py-4 border-t border-gray-50 text-center">
      <a href="/hr/ai-analytics/logs?days=<?= $days ?>" class="text-sm text-violet-600 hover:text-violet-800 font-medium">
        View all logs →
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ===== Daily Bar Chart =====
(function() {
  const labels   = <?= json_encode($chartLabels) ?>;
  const requests = <?= json_encode($chartRequests) ?>;
  const tokens   = <?= json_encode(array_map(fn($t) => round($t / 1000, 1), $chartTokens)) ?>;

  const ctx = document.getElementById('aiUsageChart');
  if (!ctx) return;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Requests',
          data: requests,
          backgroundColor: 'rgba(124,58,237,0.85)',
          borderRadius: 6,
          borderSkipped: false,
          yAxisID: 'y',
          order: 1,
        },
        {
          label: 'Tokens (K)',
          data: tokens,
          type: 'line',
          borderColor: '#F59E0B',
          backgroundColor: 'rgba(245,158,11,0.1)',
          borderWidth: 2,
          pointRadius: 3,
          pointBackgroundColor: '#F59E0B',
          tension: 0.4,
          fill: true,
          yAxisID: 'y1',
          order: 0,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(17,24,39,0.92)',
          titleColor: '#F9FAFB',
          bodyColor: '#D1D5DB',
          padding: 12,
          borderColor: 'rgba(255,255,255,0.1)',
          borderWidth: 1,
          callbacks: {
            label(ctx) {
              return ctx.datasetIndex === 0
                ? ` ${ctx.parsed.y} requests`
                : ` ${ctx.parsed.y}K tokens`;
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            color: '#9CA3AF',
            font: { size: 11 },
            maxTicksLimit: <?= min($days, 14) ?>,
          }
        },
        y: {
          position: 'left',
          grid: { color: 'rgba(0,0,0,0.04)' },
          ticks: { color: '#9CA3AF', font: { size: 11 } },
          beginAtZero: true,
        },
        y1: {
          position: 'right',
          grid: { display: false },
          ticks: {
            color: '#F59E0B',
            font: { size: 11 },
            callback: v => v + 'K'
          },
          beginAtZero: true,
        }
      }
    }
  });
})();

// ===== Donut Chart =====
(function() {
  const breakdown = <?= json_encode(array_values($breakdown)) ?>;
  const featureNames = <?= json_encode($featureNames) ?>;

  const ctx = document.getElementById('aiDonutChart');
  if (!ctx || !breakdown.length) return;

  const colors = [
    '#7C3AED','#3B82F6','#F59E0B','#10B981','#EC4899','#8B5CF6','#06B6D4','#EF4444','#84CC16','#F97316'
  ];

  const labels = breakdown.map(r => featureNames[r.feature] || r.feature.replace(/_/g, ' '));
  const data   = breakdown.map(r => parseInt(r.request_count, 10));

  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: colors.slice(0, breakdown.length),
        borderWidth: 2,
        borderColor: '#FFFFFF',
        hoverBorderColor: '#FFFFFF',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(17,24,39,0.92)',
          titleColor: '#F9FAFB',
          bodyColor: '#D1D5DB',
          padding: 10,
          callbacks: {
            label(ctx) {
              const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
              const pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
              return ` ${ctx.parsed} requests (${pct}%)`;
            }
          }
        }
      }
    }
  });
})();

// ===== Export CSV =====
function exportLogs() {
  const days  = new URLSearchParams(location.search).get('days') || '30';
  window.location.href = `/api/v1/ai-analytics?action=export&days=${days}`;
}
</script>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/app.php'; ?>
