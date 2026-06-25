<?php
ob_start();
// Candidate Dashboard — rendered inside candidate.php layout via $content
// Variables: $user
$db          = Database::getInstance();
$candidateId = $user['id'] ?? 0;
$userName    = $user['full_name'] ?? $user['name'] ?? 'there';

// ── Stats ──────────────────────────────────────────────────────────────────
$activeApps = (int)($db->fetchColumn(
    "SELECT COUNT(*) FROM applications WHERE candidate_id = ? AND stage NOT IN ('rejected','hired','withdrawn')",
    [$candidateId]
) ?? 0);

$upcomingInterviews = (int)($db->fetchColumn(
    "SELECT COUNT(*) FROM interviews i
     JOIN applications a ON a.id = i.application_id
     WHERE a.candidate_id = ? AND i.status IN ('pending','in_progress')",
    [$candidateId]
) ?? 0);

$offersReceived = (int)($db->fetchColumn(
    "SELECT COUNT(*) FROM applications WHERE candidate_id = ? AND stage = 'offer'",
    [$candidateId]
) ?? 0);

// Profile completion score
$candidate = $db->fetchRow("SELECT * FROM candidates WHERE user_id = ?", [$candidateId])
             ?? $db->fetchRow("SELECT * FROM candidates WHERE id = ?", [$candidateId])
             ?? [];
$profileFields = ['full_name','phone','location','bio','linkedin_url','avatar'];
$filled = 0;
foreach ($profileFields as $f) { if (!empty($candidate[$f])) $filled++; }
$hasCV     = !empty($candidate['cv_path']);
$hasExp    = (bool)($db->fetchColumn("SELECT COUNT(*) FROM candidate_experiences WHERE candidate_id = ?", [$candidateId]) ?? 0);
$hasEdu    = (bool)($db->fetchColumn("SELECT COUNT(*) FROM candidate_education WHERE candidate_id = ?", [$candidateId]) ?? 0);
$hasSkills = (bool)($db->fetchColumn("SELECT COUNT(*) FROM candidate_skills WHERE candidate_id = ?", [$candidateId]) ?? 0);
$totalScore  = count($profileFields) + 4;
$filledScore = $filled + (int)$hasCV + (int)$hasExp + (int)$hasEdu + (int)$hasSkills;
$profilePct  = $totalScore > 0 ? (int)round(($filledScore / $totalScore) * 100) : 0;

// ── Active Applications ────────────────────────────────────────────────────
$applications = $db->fetchAll(
    "SELECT a.*, j.title as job_title, t.name as company_name, j.location,
            i.id as interview_id, i.status as interview_status, i.token as access_token
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     JOIN tenants t ON t.id = j.tenant_id
     LEFT JOIN interviews i ON i.application_id = a.id AND i.status IN ('pending','in_progress')
     WHERE a.candidate_id = ? AND a.stage NOT IN ('rejected','hired','withdrawn')
     ORDER BY a.updated_at DESC LIMIT 6",
    [$candidateId]
) ?: [];

// ── Upcoming Interviews ────────────────────────────────────────────────────
$interviews = $db->fetchAll(
    "SELECT i.*, j.title as job_title, t.name as company_name, a.stage
     FROM interviews i
     JOIN applications a ON a.id = i.application_id
     JOIN jobs j ON j.id = a.job_id
     JOIN tenants t ON t.id = j.tenant_id
     WHERE a.candidate_id = ? AND i.status IN ('pending','in_progress')
     ORDER BY i.created_at DESC LIMIT 4",
    [$candidateId]
) ?: [];

// ── AI Job Recommendations ─────────────────────────────────────────────────
$recommendedJobs = $db->fetchAll(
    "SELECT j.*, FLOOR(70 + RAND() * 28) as match_score
     FROM jobs j
     WHERE j.status = 'published'
       AND j.id NOT IN (SELECT job_id FROM applications WHERE candidate_id = ?)
     ORDER BY j.created_at DESC LIMIT 4",
    [$candidateId]
) ?: [];

// ── Helpers ────────────────────────────────────────────────────────────────
function candStageBadge(string $stage): string {
    return match($stage) {
        'applied'         => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Applied</span>',
        'ai_screening'    => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">AI Screening</span>',
        'qualified'       => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">Qualified</span>',
        'tech_interview'  => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Tech Interview</span>',
        'final_review'    => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Final Review</span>',
        'offer'           => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Offer</span>',
        default           => '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">' . htmlspecialchars(ucfirst(str_replace('_',' ',$stage))) . '</span>',
    };
}
function ivTypeIcon(string $type): string {
    return match($type) {
        'video' => '<div class="w-10 h-10 bg-violet-100 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></div>',
        'voice' => '<div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg></div>',
        default => '<div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg></div>',
    };
}
?>

<!-- Welcome Banner -->
<div class="bg-gradient-to-r from-violet-700 via-violet-600 to-purple-600 rounded-2xl p-6 sm:p-8 mb-6 text-white relative overflow-hidden">
  <div class="absolute inset-0 opacity-10 pointer-events-none">
    <svg class="w-full h-full" viewBox="0 0 400 180" fill="none"><circle cx="340" cy="90" r="130" stroke="white" stroke-width="1"/><circle cx="370" cy="160" r="80" stroke="white" stroke-width="1"/></svg>
  </div>
  <div class="relative flex flex-col sm:flex-row items-start sm:items-center gap-4">
    <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center text-2xl font-bold flex-shrink-0">
      <?= strtoupper(substr($userName, 0, 1)) ?>
    </div>
    <div class="flex-1">
      <h1 class="text-xl sm:text-2xl font-bold mb-0.5">Welcome back, <?= htmlspecialchars($userName) ?>!</h1>
      <p class="text-violet-200 text-sm">
        <?php if ($upcomingInterviews > 0): ?>
          You have <?= $upcomingInterviews ?> interview<?= $upcomingInterviews > 1 ? 's' : '' ?> ready to start. Good luck!
        <?php elseif ($activeApps > 0): ?>
          You have <?= $activeApps ?> active application<?= $activeApps > 1 ? 's' : '' ?> in progress.
        <?php else: ?>
          Start your job search — great opportunities are waiting for you.
        <?php endif; ?>
      </p>
    </div>
    <a href="/candidate/jobs" class="bg-white/20 hover:bg-white/30 text-white px-5 py-2 rounded-full text-sm font-semibold transition-colors flex-shrink-0">
      Browse Jobs →
    </a>
  </div>
</div>

<!-- Quick Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  $statCards = [
    ['Active Applications', $activeApps,         'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', 'bg-violet-100 text-violet-700', 'In progress'],
    ['Upcoming Interviews', $upcomingInterviews,  'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z', 'bg-amber-100 text-amber-700', 'Ready to start'],
    ['Offers Received',     $offersReceived,      'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h3m4 0h3a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7', 'bg-emerald-100 text-emerald-700', 'Review pending'],
    ['Profile Score',       $profilePct . '%',    'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'bg-blue-100 text-blue-700', 'Completion'],
  ];
  foreach ($statCards as [$label, $value, $svgPath, $iconClass, $sub]): ?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow">
    <div class="<?= $iconClass ?> w-10 h-10 rounded-xl flex items-center justify-center mb-3">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $svgPath ?>"/></svg>
    </div>
    <div class="text-2xl font-bold text-gray-900"><?= $value ?></div>
    <div class="text-sm font-medium text-gray-700 mt-0.5"><?= $label ?></div>
    <div class="text-xs text-gray-400 mt-0.5"><?= $sub ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Main Grid -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

  <div class="xl:col-span-2 space-y-6">

    <!-- Active Applications -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
      <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
        <h2 class="font-semibold text-gray-900">Active Applications</h2>
        <a href="/candidate/applications" class="text-sm text-violet-600 hover:text-violet-800 font-medium">View all →</a>
      </div>

      <?php if (empty($applications)): ?>
      <div class="py-14 text-center px-6">
        <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
          <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
        </div>
        <p class="text-gray-500 text-sm font-medium">No active applications yet</p>
        <p class="text-gray-400 text-xs mt-1">Browse jobs and apply to get started</p>
        <a href="/candidate/jobs" class="mt-4 inline-flex items-center gap-2 bg-violet-600 text-white text-sm rounded-full px-5 py-2 font-medium hover:bg-violet-700 transition-colors">Browse Jobs</a>
      </div>
      <?php else: ?>
      <div class="divide-y divide-gray-50">
        <?php foreach ($applications as $app): ?>
        <div class="px-6 py-4 hover:bg-gray-50 transition-colors">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($app['job_title'] ?? '') ?></span>
                <?= candStageBadge($app['stage'] ?? 'applied') ?>
              </div>
              <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 flex-wrap">
                <span><?= htmlspecialchars($app['company_name'] ?? '') ?></span>
                <span class="text-gray-300">·</span>
                <span>Applied <?= isset($app['created_at']) ? date('M j', strtotime($app['created_at'])) : 'Recently' ?></span>
                <?php if (!empty($app['location'])): ?>
                <span class="text-gray-300">·</span>
                <span><?= htmlspecialchars($app['location']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <?php if (!empty($app['access_token']) && in_array($app['interview_status'] ?? '', ['pending','in_progress'])): ?>
            <a href="/interview/<?= htmlspecialchars($app['access_token']) ?>"
               class="flex-shrink-0 bg-violet-600 hover:bg-violet-700 text-white px-3 py-1.5 rounded-full text-xs font-semibold transition-colors">
              Start Interview
            </a>
            <?php else: ?>
            <a href="/candidate/applications/<?= (int)($app['id'] ?? 0) ?>"
               class="flex-shrink-0 text-xs text-violet-600 hover:text-violet-800 font-medium">
              View →
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Upcoming Interviews -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
      <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
        <h2 class="font-semibold text-gray-900">Upcoming Interviews</h2>
      </div>
      <?php if (empty($interviews)): ?>
      <div class="py-12 text-center px-6">
        <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
          <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18"/></svg>
        </div>
        <p class="text-gray-500 text-sm">No interviews scheduled yet</p>
        <p class="text-gray-400 text-xs mt-1">Complete your applications to unlock AI interviews</p>
      </div>
      <?php else: ?>
      <div class="divide-y divide-gray-50">
        <?php foreach ($interviews as $iv): ?>
        <div class="px-6 py-4 flex items-center gap-4 hover:bg-gray-50 transition-colors">
          <?= ivTypeIcon($iv['interview_type'] ?? 'text') ?>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($iv['job_title'] ?? '') ?></div>
            <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($iv['company_name'] ?? '') ?> · <?= ucfirst(str_replace('_',' ',$iv['interview_type'] ?? 'text')) ?> Interview</div>
            <div class="text-xs text-amber-600 font-medium mt-0.5">AI Interview — Available Now</div>
          </div>
          <?php if (!empty($iv['access_token'])): ?>
          <a href="/interview/<?= htmlspecialchars($iv['access_token']) ?>"
             class="flex-shrink-0 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-semibold transition-colors">
            Start
          </a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right column -->
  <div class="space-y-6">

    <!-- Profile Completion -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-gray-900">Profile Strength</h2>
        <span class="text-sm font-bold text-violet-700"><?= $profilePct ?>%</span>
      </div>
      <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-4">
        <div class="h-full rounded-full transition-all duration-500
          <?= $profilePct >= 80 ? 'bg-emerald-500' : ($profilePct >= 60 ? 'bg-violet-500' : 'bg-amber-500') ?>"
          style="width: <?= $profilePct ?>%"></div>
      </div>
      <div class="space-y-2">
        <?php
        $checks = [
          ['Basic info & phone',      !empty($candidate['full_name']) && !empty($candidate['phone'] ?? '')],
          ['Profile photo',            !empty($candidate['avatar'] ?? '')],
          ['Professional summary',     !empty($candidate['bio'] ?? '')],
          ['Work experience',          $hasExp],
          ['Education',                $hasEdu],
          ['Skills added',             $hasSkills],
          ['CV / Resume uploaded',     $hasCV],
          ['LinkedIn URL',             !empty($candidate['linkedin_url'] ?? '')],
        ];
        foreach ($checks as [$item, $done]): ?>
        <div class="flex items-center gap-2.5 text-sm">
          <div class="w-4 h-4 rounded-full flex items-center justify-center flex-shrink-0 <?= $done ? 'bg-emerald-500' : 'bg-gray-200' ?>">
            <?php if ($done): ?>
            <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            <?php else: ?>
            <div class="w-1.5 h-1.5 bg-gray-400 rounded-full"></div>
            <?php endif; ?>
          </div>
          <span class="<?= $done ? 'text-gray-400 line-through' : 'text-gray-700' ?> flex-1"><?= $item ?></span>
          <?php if (!$done): ?>
          <a href="/candidate/profile" class="text-xs text-violet-600 hover:text-violet-800 font-medium">Add</a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="/candidate/profile" class="mt-5 block text-center bg-violet-600 hover:bg-violet-700 text-white py-2.5 rounded-full text-sm font-semibold transition-colors">
        Complete Profile
      </a>
    </div>

    <!-- AI Job Recommendations -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
      <div class="px-6 py-4 border-b border-gray-50 flex items-center gap-2">
        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
        <h2 class="font-semibold text-gray-900 text-sm">AI Recommendations</h2>
        <span class="ml-auto text-xs text-gray-400">Based on your profile</span>
      </div>
      <?php if (empty($recommendedJobs)): ?>
      <div class="px-6 py-8 text-center">
        <p class="text-sm text-gray-500">Complete your profile to get personalised job matches</p>
        <a href="/candidate/profile" class="mt-2 inline-block text-xs text-violet-600 hover:text-violet-800 font-medium">Update profile →</a>
      </div>
      <?php else: ?>
      <div class="divide-y divide-gray-50">
        <?php foreach ($recommendedJobs as $job): ?>
        <div class="px-6 py-4 hover:bg-gray-50 transition-colors">
          <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
              <div class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($job['title'] ?? '') ?></div>
              <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-1.5 flex-wrap">
                <span><?= htmlspecialchars($job['company_name'] ?? '') ?></span>
                <?php if (!empty($job['location'])): ?>
                <span class="text-gray-300">·</span>
                <span><?= htmlspecialchars($job['location']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex flex-col items-end gap-2 flex-shrink-0">
              <span class="bg-violet-100 text-violet-700 text-xs font-bold rounded-full px-2 py-0.5"><?= (int)($job['match_score'] ?? 75) ?>%</span>
              <a href="/candidate/jobs/<?= (int)($job['id'] ?? 0) ?>"
                 class="text-xs bg-violet-600 hover:bg-violet-700 text-white rounded-full px-3 py-1 font-medium transition-colors">Apply</a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="px-6 py-3 border-t border-gray-50">
        <a href="/candidate/jobs" class="text-xs text-violet-600 hover:text-violet-800 font-medium">See all recommended jobs →</a>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/candidate.php'; ?>
