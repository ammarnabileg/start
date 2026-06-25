<?php
ob_start();
$db = Database::getInstance();
$tenantId = $user['tenant_id'] ?? 0;

// Stats (cached 5 min)
$stats = Cache::remember(Cache::tenantKey('dashboard_stats', $tenantId), 300, function() use ($db, $tenantId) {
    return [
        'active_jobs'     => $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE tenant_id = ? AND status = 'published'", [$tenantId]) ?? 0,
        'total_candidates'=> $db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id = ?", [$tenantId]) ?? 0,
        'interviews_today'=> $db->fetchColumn("SELECT COUNT(*) FROM interviews i JOIN applications a ON a.id = i.application_id WHERE a.tenant_id = ? AND DATE(i.created_at) = CURDATE()", [$tenantId]) ?? 0,
        'hired_month'     => $db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id = ? AND current_stage = 'hired' AND MONTH(updated_at) = MONTH(NOW())", [$tenantId]) ?? 0,
        'pending_decision'=> $db->fetchColumn("SELECT COUNT(*) FROM applications WHERE tenant_id = ? AND current_stage IN ('qualified','tech_interview','manager_interview','final_review')", [$tenantId]) ?? 0,
    ];
});

// Recent interviews
$recentInterviews = $db->fetchAll("SELECT i.*, a.current_stage as stage, c.full_name, c.email, j.title as job_title, e.overall_score, e.recommendation
    FROM interviews i
    JOIN applications a ON a.id = i.application_id
    JOIN candidates c ON c.id = a.candidate_id
    JOIN jobs j ON j.id = a.job_id
    LEFT JOIN interview_evaluations e ON e.interview_id = i.id
    WHERE a.tenant_id = ? AND i.status = 'completed'
    ORDER BY i.completed_at DESC LIMIT 8", [$tenantId]);

// Stage distribution
$stages = $db->fetchAll("SELECT current_stage as stage, COUNT(*) as cnt FROM applications WHERE tenant_id = ? GROUP BY current_stage", [$tenantId]);
$stageCounts = array_column($stages, 'cnt', 'stage');
$totalApps = array_sum($stageCounts);

function recBadge(?string $rec): string {
    return match($rec) {
        'strong' => '<span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs font-semibold rounded-full px-2.5 py-1">✓ Strong</span>',
        'suitable' => '<span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 border border-blue-200 text-xs font-semibold rounded-full px-2.5 py-1">✓ Suitable</span>',
        'possible' => '<span class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 border border-amber-200 text-xs font-semibold rounded-full px-2.5 py-1">◉ Possible</span>',
        'not_recommended' => '<span class="inline-flex items-center gap-1 bg-red-100 text-red-700 border border-red-200 text-xs font-semibold rounded-full px-2.5 py-1">✗ Not Recommended</span>',
        default => '<span class="text-xs text-gray-400">—</span>',
    };
}
function scoreBadge(?float $score): string {
    if ($score === null) return '<span class="text-gray-400 text-sm">—</span>';
    $color = $score >= 82 ? 'text-emerald-700 bg-emerald-50' : ($score >= 68 ? 'text-blue-700 bg-blue-50' : ($score >= 50 ? 'text-amber-700 bg-amber-50' : 'text-red-700 bg-red-50'));
    return "<span class='inline-block font-bold text-sm {$color} rounded-lg px-2.5 py-0.5'>" . number_format($score, 0) . "</span>";
}
?>
<!-- Stats Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
  <?php
  $statCards = [
    ['Active Jobs', $stats['active_jobs'], 'briefcase', 'bg-violet-100 text-violet-700', '+2 this week'],
    ['Total Candidates', $stats['total_candidates'], 'users', 'bg-blue-100 text-blue-700', 'All time'],
    ['Interviews Today', $stats['interviews_today'], 'sparkles', 'bg-amber-100 text-amber-700', 'AI conducted'],
    ['Hired This Month', $stats['hired_month'], 'check-circle', 'bg-emerald-100 text-emerald-700', 'This month'],
    ['Pending Decisions', $stats['pending_decision'], 'clock', 'bg-orange-100 text-orange-700', 'Need attention'],
  ];
  $sIcons = [
    'briefcase'=>'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    'users'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    'sparkles'=>'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    'check-circle'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    'clock'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
  ];
  foreach ($statCards as [$label, $value, $icon, $iconClass, $sub]): ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow">
    <div class="flex items-start justify-between mb-4">
      <div class="<?= $iconClass ?> w-11 h-11 rounded-xl flex items-center justify-center">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $sIcons[$icon] ?>"/></svg>
      </div>
    </div>
    <div class="text-3xl font-bold text-gray-900 mb-1"><?= number_format((int)$value) ?></div>
    <div class="text-sm font-medium text-gray-700"><?= $label ?></div>
    <div class="text-xs text-gray-400 mt-0.5"><?= $sub ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  <!-- Recent AI Interviews -->
  <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm">
    <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
      <h2 class="font-semibold text-gray-900">Recent AI Interviews</h2>
      <a href="/ai-interviews" class="text-sm text-violet-600 hover:text-violet-800 font-medium">View all →</a>
    </div>
    <?php if (empty($recentInterviews)): ?>
    <div class="py-16 text-center">
      <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      </div>
      <p class="text-gray-500 text-sm font-medium mb-1">No interviews yet</p>
      <p class="text-gray-400 text-xs">Create a job and share an interview link to get started.</p>
      <a href="/jobs/create" class="mt-4 inline-flex items-center gap-2 bg-violet-700 text-white text-sm rounded-full px-5 py-2 font-medium hover:bg-violet-800 transition-colors">+ Create First Job</a>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-50">
      <?php foreach ($recentInterviews as $iv): ?>
      <div class="px-6 py-4 hover:bg-gray-50 transition-colors flex items-center gap-4">
        <div class="w-9 h-9 rounded-xl bg-violet-100 flex items-center justify-center text-violet-700 font-bold text-sm flex-shrink-0">
          <?= strtoupper(substr($iv['full_name'], 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-medium text-gray-900 text-sm truncate"><?= htmlspecialchars($iv['full_name']) ?></div>
          <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($iv['job_title']) ?></div>
        </div>
        <div class="hidden sm:block"><?= scoreBadge($iv['overall_score'] ?? null) ?></div>
        <div class="hidden md:block"><?= recBadge($iv['recommendation'] ?? null) ?></div>
        <a href="/candidates/<?= $iv['candidate_id'] ?? '' ?>" class="text-violet-600 hover:text-violet-800 text-xs font-medium flex-shrink-0">View →</a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right Column -->
  <div class="space-y-4">
    <!-- Quick Actions -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <h3 class="font-semibold text-gray-900 mb-4">Quick Actions</h3>
      <div class="space-y-2">
        <a href="/jobs" class="flex items-center gap-3 p-3 bg-violet-700 hover:bg-violet-800 text-white rounded-xl transition-colors text-sm font-semibold">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Create New Job
        </a>
        <a href="/pipeline" class="flex items-center gap-3 p-3 border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl transition-colors text-sm font-medium">
          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
          View Pipeline
        </a>
        <a href="/candidates" class="flex items-center gap-3 p-3 border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl transition-colors text-sm font-medium">
          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          Browse Candidates
        </a>
        <a href="/ai-analytics" class="flex items-center gap-3 p-3 border border-gray-200 text-gray-700 hover:bg-gray-50 rounded-xl transition-colors text-sm font-medium">
          <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          AI Analytics
        </a>
      </div>
    </div>

    <!-- Pipeline Overview -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
      <h3 class="font-semibold text-gray-900 mb-4">Pipeline Overview</h3>
      <?php
      $pipelineStages = ['applied'=>'Applied','ai_screening'=>'AI Screening','qualified'=>'Qualified','tech_interview'=>'Tech Interview','hired'=>'Hired'];
      $pipelineColors = ['applied'=>'bg-gray-400','ai_screening'=>'bg-violet-500','qualified'=>'bg-blue-500','tech_interview'=>'bg-indigo-500','hired'=>'bg-emerald-500'];
      foreach ($pipelineStages as $stage => $label):
        $count = (int)($stageCounts[$stage] ?? 0);
        $pct = $totalApps > 0 ? min(100, round($count / $totalApps * 100)) : 0;
      ?>
      <div class="mb-3 last:mb-0">
        <div class="flex justify-between text-xs mb-1">
          <span class="text-gray-600 font-medium"><?= $label ?></span>
          <span class="text-gray-900 font-bold"><?= $count ?></span>
        </div>
        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
          <div class="h-full <?= $pipelineColors[$stage] ?> rounded-full transition-all duration-700" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <a href="/pipeline" class="mt-4 block text-center text-sm text-violet-600 hover:text-violet-800 font-medium">View Full Pipeline →</a>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/app.php'; ?>
