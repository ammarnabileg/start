<?php
/**
 * AI Interviews management page.
 * Controller may inject: $interviews, $jobs, $counts, $filters
 */
require_once __DIR__ . '/../../partials/helpers.php';

$filters = [
    'job'    => $_GET['job']    ?? '',
    'from'   => $_GET['from']   ?? '',
    'to'     => $_GET['to']     ?? '',
    'rec'    => $_GET['rec']    ?? '',
    'status' => $_GET['status'] ?? '',
];

$db = Database::getInstance();
$tid = Auth::user()['tenant_id'] ?? 0;

try {
    $jobs = $db->fetchAll("SELECT id, title FROM jobs WHERE tenant_id = ? ORDER BY title", [$tid]) ?: [];

    $where = "i.tenant_id = ?";
    $params = [$tid];
    if ($filters['job'])    { $where .= " AND a.job_id = ?"; $params[] = (int)$filters['job']; }
    if ($filters['from'])   { $where .= " AND i.completed_at >= ?"; $params[] = $filters['from']; }
    if ($filters['to'])     { $where .= " AND i.completed_at <= ?"; $params[] = $filters['to']; }
    if ($filters['rec'])    { $where .= " AND ie.recommendation = ?"; $params[] = $filters['rec']; }
    if ($filters['status']) { $where .= " AND i.status = ?"; $params[] = $filters['status']; }

    $interviews = $db->fetchAll(
        "SELECT i.id, i.status, i.completed_at,
                a.candidate_id, a.job_id,
                CONCAT(c.first_name,' ',c.last_name) as candidate,
                j.title as job,
                ie.overall_score as score,
                ie.recommendation,
                TIMESTAMPDIFF(MINUTE, i.created_at, IFNULL(i.completed_at, NOW())) as duration_min
         FROM interviews i
         JOIN applications a ON a.id = i.application_id
         JOIN candidates c ON c.id = a.candidate_id
         JOIN jobs j ON j.id = a.job_id
         LEFT JOIN interview_evaluations ie ON ie.interview_id = i.id
         WHERE $where
         ORDER BY i.created_at DESC
         LIMIT 100",
        $params
    ) ?: [];
    foreach ($interviews as &$iv) {
        $iv['duration'] = $iv['duration_min'] ? $iv['duration_min'].'m' : null;
        $iv['completed_at_raw'] = $iv['completed_at'] ?: null;
        $iv['completed_at'] = $iv['completed_at'] ? date('d M Y', strtotime($iv['completed_at'])) : null;
    }
    unset($iv);
    $counts = [
        'total'       => count($interviews),
        'completed'   => count(array_filter($interviews, fn($i)=>$i['status']==='completed')),
        'in_progress' => count(array_filter($interviews, fn($i)=>$i['status']==='in_progress')),
        'strong'      => count(array_filter($interviews, fn($i)=>$i['recommendation']==='strong')),
    ];
} catch (\Exception $e) {
    $jobs = [];
    $interviews = [];
    $counts = ['total'=>0,'completed'=>0,'in_progress'=>0,'strong'=>0];
}

$pageTitle   = 'AI Interviews';
$activeNav   = 'ai-interviews';
$breadcrumbs = [['label'=>'Home','url'=>'/dashboard'],['label'=>'AI Interviews']];

$aiMissingOpenAI = !ApiKeyManager::hasTenantOpenAIKey();
$aiMissingHeyGen = !ApiKeyManager::hasTenantHeyGenKey();
$aiFeatureLabel  = 'AI Video Interviews';

ob_start();
?>
<?php require VIEWS_PATH . '/partials/ai-keys-banner.php'; ?>

<!-- Filter bar -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-5">
  <form method="GET" class="flex flex-wrap gap-3 items-end">
    <div class="min-w-40">
      <label class="block text-xs font-medium text-gray-500 mb-1.5">Job</label>
      <select name="job" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
        <option value="">All Jobs</option>
        <?php foreach ($jobs as $j): ?>
          <option value="<?= (int)$j['id'] ?>" <?= $filters['job']==(int)$j['id']?'selected':'' ?>><?= e($j['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-500 mb-1.5">From Date</label>
      <input type="date" name="from" value="<?= e($filters['from']) ?>" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-500 mb-1.5">To Date</label>
      <input type="date" name="to" value="<?= e($filters['to']) ?>" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
    </div>
    <div class="min-w-36">
      <label class="block text-xs font-medium text-gray-500 mb-1.5">Recommendation</label>
      <select name="rec" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
        <option value="">All</option>
        <option value="strong" <?= $filters['rec']==='strong'?'selected':'' ?>>Strong</option>
        <option value="suitable" <?= $filters['rec']==='suitable'?'selected':'' ?>>Suitable</option>
        <option value="possible" <?= $filters['rec']==='possible'?'selected':'' ?>>Possible</option>
        <option value="not_recommended" <?= $filters['rec']==='not_recommended'?'selected':'' ?>>Not Recommended</option>
      </select>
    </div>
    <div class="min-w-32">
      <label class="block text-xs font-medium text-gray-500 mb-1.5">Status</label>
      <select name="status" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none">
        <option value="">All Statuses</option>
        <option value="completed" <?= $filters['status']==='completed'?'selected':'' ?>>Completed</option>
        <option value="in_progress" <?= $filters['status']==='in_progress'?'selected':'' ?>>In Progress</option>
      </select>
    </div>
    <div class="flex gap-2 ml-auto">
      <a href="/ai-interviews" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl text-sm font-medium transition-colors">Reset</a>
      <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition-colors">Apply Filters</button>
    </div>
  </form>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $summaryCards = [
    ['Total Interviews', $counts['total'],       'bg-violet-100 text-violet-700', 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
    ['Completed',        $counts['completed'],    'bg-emerald-100 text-emerald-700', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['In Progress',      $counts['in_progress'],  'bg-amber-100 text-amber-700', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['Strong Recommendations', $counts['strong'], 'bg-blue-100 text-blue-700', 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
  ];
  foreach ($summaryCards as [$label, $value, $iconCls, $iconPath]): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-start justify-between mb-3">
      <div class="<?= $iconCls ?> w-10 h-10 rounded-xl flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $iconPath ?>"/></svg>
      </div>
    </div>
    <div class="text-3xl font-extrabold text-gray-900"><?= number_format((int)$value) ?></div>
    <div class="text-sm text-gray-500 mt-0.5"><?= e($label) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Bulk actions bar -->
<div id="bulkBar" class="hidden mb-3 bg-violet-50 border border-violet-200 rounded-xl px-4 py-3 flex items-center gap-3">
  <span class="text-sm font-semibold text-violet-800"><span id="bulkCount">0</span> selected</span>
  <div class="ml-auto flex gap-2">
    <button onclick="exportCSV()" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-1.5 rounded-full text-sm font-medium transition-colors flex items-center gap-1.5">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Export CSV
    </button>
    <button onclick="archiveSelected()" class="bg-white border border-gray-200 hover:bg-rose-50 text-rose-600 border-rose-200 px-4 py-1.5 rounded-full text-sm font-medium transition-colors">
      Archive Selected
    </button>
    <button onclick="clearSelection()" class="text-gray-400 hover:text-gray-600 px-2 py-1.5 text-sm">Clear</button>
  </div>
</div>

<!-- Interviews table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100" id="interviewsTable">
      <thead class="bg-gray-50">
        <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
          <th class="px-4 py-3">
            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" class="rounded accent-violet-600 w-4 h-4">
          </th>
          <th class="px-4 py-3">Candidate</th>
          <th class="px-4 py-3">Job</th>
          <th class="px-4 py-3 text-center">Score</th>
          <th class="px-4 py-3">Recommendation</th>
          <th class="px-4 py-3 text-center">Duration</th>
          <th class="px-4 py-3">Completed At</th>
          <th class="px-4 py-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50" id="tableBody">
        <?php foreach ($interviews as $iv):
          $sc = $iv['score'] !== null ? score_color((float)$iv['score']) : null;
          [$recLabel, $recCls] = recommendation_badge($iv['recommendation'] ?? null);
          $isCompleted = $iv['status'] === 'completed';
        ?>
        <tr class="hover:bg-gray-50 transition-colors interview-row" data-id="<?= (int)$iv['id'] ?>">
          <td class="px-4 py-3.5">
            <input type="checkbox" class="row-check rounded accent-violet-600 w-4 h-4" value="<?= (int)$iv['id'] ?>" onchange="updateBulkBar()">
          </td>
          <td class="px-4 py-3.5">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-violet-100 text-violet-700 font-bold text-xs flex items-center justify-center shrink-0">
                <?= e(initials($iv['candidate'])) ?>
              </div>
              <a href="/candidates/<?= (int)$iv['candidate_id'] ?>" class="font-medium text-gray-900 hover:text-violet-600 transition-colors text-sm">
                <?= e($iv['candidate']) ?>
              </a>
            </div>
          </td>
          <td class="px-4 py-3.5 text-sm text-gray-600"><?= e($iv['job']) ?></td>
          <td class="px-4 py-3.5 text-center">
            <?php if ($iv['score'] !== null): ?>
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-full font-extrabold text-sm <?= $sc['soft'] ?>">
                <?= (int)$iv['score'] ?>
              </span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 text-xs text-amber-600 font-medium">
                <span class="w-1.5 h-1.5 bg-amber-400 rounded-full animate-pulse"></span>Live
              </span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3.5">
            <?php if ($iv['recommendation']): ?>
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ring-1 <?= $recCls ?>">
                <?= e($recLabel) ?>
              </span>
            <?php else: ?>
              <span class="text-xs text-gray-400">—</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3.5 text-center text-sm text-gray-500">
            <?= $iv['duration'] ? e($iv['duration']) : '<span class="text-gray-300">—</span>' ?>
          </td>
          <td class="px-4 py-3.5 text-sm text-gray-500">
            <?= $iv['completed_at_raw'] ? e(time_ago($iv['completed_at_raw'])) : '<span class="text-amber-500 font-medium text-xs">In Progress</span>' ?>
          </td>
          <td class="px-4 py-3.5">
            <div class="flex items-center justify-end gap-1">
              <?php if ($isCompleted): ?>
                <a href="/ai-interviews/<?= (int)$iv['id'] ?>/report" class="inline-flex items-center gap-1 text-xs font-medium text-violet-600 hover:text-violet-800 px-2.5 py-1.5 rounded-lg hover:bg-violet-50 transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  Report
                </a>
                <button onclick="moveToPipeline(<?= (int)$iv['id'] ?>)" class="inline-flex items-center gap-1 text-xs font-medium text-gray-600 hover:text-gray-900 px-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                  Pipeline
                </button>
              <?php endif; ?>
              <div class="relative" data-dropdown>
                <button data-dropdown-trigger class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
                </button>
                <div data-dropdown-menu class="hidden absolute right-0 mt-1 w-44 bg-white rounded-xl border border-gray-100 shadow-lg z-20 overflow-hidden text-sm">
                  <a href="/candidates/<?= (int)$iv['candidate_id'] ?>" class="block px-4 py-2.5 hover:bg-gray-50 text-gray-700">View Candidate</a>
                  <?php if ($isCompleted): ?>
                    <a href="/ai-interviews/<?= (int)$iv['id'] ?>/report" class="block px-4 py-2.5 hover:bg-gray-50 text-gray-700">Full Report</a>
                  <?php endif; ?>
                  <button onclick="archiveInterview(<?= (int)$iv['id'] ?>)" class="w-full text-left px-4 py-2.5 hover:bg-rose-50 text-rose-600 border-t border-gray-100">Archive</button>
                </div>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (empty($interviews)): ?>
  <div class="py-16 text-center">
    <div class="w-16 h-16 bg-violet-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
      <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
    </div>
    <p class="font-semibold text-gray-900">No interviews yet</p>
    <p class="text-sm text-gray-500 mt-1">Create a job and share the interview link to start collecting AI interviews.</p>
    <a href="/jobs/create" class="mt-4 inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-full text-sm font-semibold transition-colors">
      Create Job
    </a>
  </div>
  <?php endif; ?>

  <!-- Table footer -->
  <?php if (!empty($interviews)): ?>
  <div class="px-5 py-3 border-t border-gray-50 flex items-center justify-between text-xs text-gray-400">
    <span><?= count($interviews) ?> interview(s) shown</span>
    <div class="flex items-center gap-1">
      <button class="px-2 py-1 rounded hover:bg-gray-100 transition-colors">← Prev</button>
      <span class="px-2 py-1 rounded bg-violet-600 text-white font-semibold">1</span>
      <button class="px-2 py-1 rounded hover:bg-gray-100 transition-colors">Next →</button>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function updateBulkBar() {
  var checked = document.querySelectorAll('.row-check:checked');
  var bar = document.getElementById('bulkBar');
  var count = document.getElementById('bulkCount');
  if (checked.length > 0) {
    bar.classList.remove('hidden');
    bar.classList.add('flex');
    count.textContent = checked.length;
  } else {
    bar.classList.add('hidden');
    bar.classList.remove('flex');
  }
  var all = document.getElementById('selectAll');
  var allChecks = document.querySelectorAll('.row-check');
  if (all) all.indeterminate = checked.length > 0 && checked.length < allChecks.length;
  if (all) all.checked = checked.length === allChecks.length && allChecks.length > 0;
}
function toggleSelectAll(cb) {
  document.querySelectorAll('.row-check').forEach(function(c){ c.checked = cb.checked; });
  updateBulkBar();
}
function clearSelection() {
  document.querySelectorAll('.row-check').forEach(function(c){ c.checked = false; });
  document.getElementById('selectAll').checked = false;
  updateBulkBar();
}
function getSelectedIds() {
  return Array.from(document.querySelectorAll('.row-check:checked')).map(function(c){ return c.value; });
}
function exportCSV() {
  var ids = getSelectedIds();
  showToast('Exporting ' + ids.length + ' interview(s) to CSV...', 'info');
  setTimeout(function(){ showToast('CSV download ready!', 'success'); }, 1500);
}
function archiveSelected() {
  var ids = getSelectedIds();
  if (!ids.length) return;
  if (!confirm('Archive ' + ids.length + ' interview(s)?')) return;
  ids.forEach(function(id) {
    var row = document.querySelector('.interview-row[data-id="' + id + '"]');
    if (row) row.style.opacity = '0.3';
  });
  showToast(ids.length + ' interview(s) archived.', 'success');
  clearSelection();
}
function archiveInterview(id) {
  if (!confirm('Archive this interview?')) return;
  var row = document.querySelector('.interview-row[data-id="' + id + '"]');
  if (row) { row.style.transition = 'all 0.3s'; row.style.opacity = '0'; setTimeout(function(){ row.remove(); }, 300); }
  showToast('Interview archived.', 'success');
}
function moveToPipeline(id) {
  showToast('Moving candidate to pipeline...', 'info');
  setTimeout(function(){ showToast('Candidate moved to Tech Interview stage!', 'success'); }, 800);
}
</script>
<?php require __DIR__ . '/../../partials/view_scripts.php'; ?>
<?php
$content = ob_get_clean();
require VIEWS_PATH . '/layouts/app.php';
