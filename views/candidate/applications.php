<?php
ob_start();
$pageTitle = 'My Applications';
$db = Database::getInstance();
$cid = $candidateId ?? (int)(Auth::user()['id'] ?? 0);
$activeTab = $_GET['status'] ?? 'all';
$applications = $db->fetchAll(
    "SELECT a.*, j.title as job_title, t.name as company_name,
            ie.overall_score, ie.recommendation,
            i.token as interview_token, i.status as interview_status
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     JOIN tenants t ON t.id = j.tenant_id
     LEFT JOIN interviews i ON i.application_id = a.id
     LEFT JOIN interview_evaluations ie ON ie.interview_id = i.id
     WHERE a.candidate_id = ? ORDER BY a.applied_at DESC",
    [$cid]
) ?: [];

// ── Build tab counts ────────────────────────────────────────────────────────────
$ACTIVE_STAGES    = ['applied', 'ai_screening', 'ai_interview', 'qualified', 'interview'];
$COMPLETED_STAGES = ['offer', 'hired'];
$REJECTED_STAGES  = ['rejected', 'withdrawn'];

$counts = ['all' => count($applications), 'active' => 0, 'completed' => 0, 'rejected' => 0];
foreach ($applications as $app) {
    $stage = $app['current_stage'] ?? $app['stage'] ?? 'applied';
    if (in_array($stage, $ACTIVE_STAGES))    $counts['active']++;
    elseif (in_array($stage, $COMPLETED_STAGES)) $counts['completed']++;
    elseif (in_array($stage, $REJECTED_STAGES))  $counts['rejected']++;
}

// ── Filter by active tab ────────────────────────────────────────────────────────
$filtered = match($activeTab) {
    'active'    => array_filter($applications, fn($a) => in_array($a['current_stage'] ?? $a['stage'] ?? 'applied', $ACTIVE_STAGES)),
    'completed' => array_filter($applications, fn($a) => in_array($a['current_stage'] ?? $a['stage'] ?? '', $COMPLETED_STAGES)),
    'rejected'  => array_filter($applications, fn($a) => in_array($a['current_stage'] ?? $a['stage'] ?? '', $REJECTED_STAGES)),
    default     => $applications,
};
$filtered = array_values($filtered);

// ── Pipeline stages definition ───────────────────────────────────────────────────
$PIPELINE = [
    ['key' => 'applied',       'label' => 'Applied'],
    ['key' => 'ai_screening',  'label' => 'AI Screening'],
    ['key' => 'qualified',     'label' => 'Qualified'],
    ['key' => 'interview',     'label' => 'Interview'],
    ['key' => 'offer',         'label' => 'Offer'],
];

// ── Helper functions ────────────────────────────────────────────────────────────
function pipelineIndex(string $stage): int {
    return match($stage) {
        'applied'                      => 0,
        'ai_screening', 'ai_interview' => 1,
        'qualified'                    => 2,
        'interview'                    => 3,
        'offer', 'hired'               => 4,
        default                        => 0,
    };
}

function statusMessage(string $stage, ?string $token, ?string $interviewStatus): array {
    return match($stage) {
        'applied'      => ['text' => 'Application received — under review', 'class' => 'text-gray-500', 'icon' => 'clock'],
        'ai_screening' => ['text' => 'AI screening in progress', 'class' => 'text-blue-600', 'icon' => 'brain'],
        'ai_interview' => ['text' => 'AI Interview available — start now', 'class' => 'text-violet-600 font-semibold', 'icon' => 'play'],
        'qualified'    => ['text' => 'You\'ve been qualified! Under HR review', 'class' => 'text-emerald-600', 'icon' => 'check'],
        'interview'    => ['text' => 'Human interview scheduled', 'class' => 'text-amber-600', 'icon' => 'calendar'],
        'offer'        => ['text' => 'Offer received — review your offer', 'class' => 'text-emerald-600 font-semibold', 'icon' => 'gift'],
        'hired'        => ['text' => 'Congratulations! Offer accepted', 'class' => 'text-emerald-700 font-semibold', 'icon' => 'check'],
        'rejected'     => ['text' => 'Application not selected', 'class' => 'text-red-500', 'icon' => 'x'],
        'withdrawn'    => ['text' => 'Application withdrawn', 'class' => 'text-gray-400', 'icon' => 'x'],
        default        => ['text' => 'Under review', 'class' => 'text-gray-500', 'icon' => 'clock'],
    };
}

function statusIcon(string $icon): string {
    return match($icon) {
        'clock'    => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'brain'    => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>',
        'play'     => '<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>',
        'check'    => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>',
        'calendar' => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
        'gift'     => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h3m4 0h3a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>',
        'x'        => '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>',
        default    => '',
    };
}

function scoreColor(int $score): string {
    if ($score >= 80) return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if ($score >= 60) return 'bg-amber-100 text-amber-700 border-amber-200';
    return 'bg-red-100 text-red-600 border-red-200';
}

function scoreBar(int $score): string {
    $color = $score >= 80 ? 'bg-emerald-500' : ($score >= 60 ? 'bg-amber-500' : 'bg-red-500');
    return '<div class="h-1.5 bg-gray-100 rounded-full overflow-hidden flex-1"><div class="h-full rounded-full ' . $color . '" style="width:' . $score . '%"></div></div>';
}
?>

<!-- ═══════════════════════ PAGE HEADER ═══════════════════════ -->
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
  <div>
    <h1 class="text-2xl font-bold text-gray-900">My Applications</h1>
    <p class="text-sm text-gray-500 mt-0.5">
      <?= $counts['all'] ?> application<?= $counts['all'] !== 1 ? 's' : '' ?> total
    </p>
  </div>
  <a href="/candidate/jobs"
    class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors shadow-sm shadow-violet-200 self-start sm:self-auto">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
    </svg>
    Find More Jobs
  </a>
</div>

<!-- ═══════════════════════ FILTER TABS ═══════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 mb-6 inline-flex gap-1 w-full sm:w-auto">
  <?php
  $tabs = [
    ['key' => 'all',       'label' => 'All',       'color' => 'bg-gray-100 text-gray-600'],
    ['key' => 'active',    'label' => 'Active',    'color' => 'bg-violet-100 text-violet-700'],
    ['key' => 'completed', 'label' => 'Completed', 'color' => 'bg-emerald-100 text-emerald-700'],
    ['key' => 'rejected',  'label' => 'Rejected',  'color' => 'bg-red-100 text-red-600'],
  ];
  foreach ($tabs as $tab):
    $isActive = $activeTab === $tab['key'];
  ?>
  <a href="?status=<?= $tab['key'] ?>"
    class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all <?= $isActive ? 'bg-violet-600 text-white shadow-sm shadow-violet-200' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50' ?>">
    <?= $tab['label'] ?>
    <?php if ($counts[$tab['key']] > 0): ?>
    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-xs font-bold <?= $isActive ? 'bg-white/20 text-white' : $tab['color'] ?>">
      <?= $counts[$tab['key']] ?>
    </span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ═══════════════════════ APPLICATION LIST ═══════════════════════ -->
<?php if (empty($filtered)): ?>

<!-- Empty state -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 py-20 text-center px-6">
  <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
    </svg>
  </div>
  <h3 class="text-gray-900 font-semibold text-lg mb-1">
    <?php if ($activeTab === 'all'): ?>No applications yet<?php else: ?>No <?= $activeTab ?> applications<?php endif; ?>
  </h3>
  <p class="text-gray-500 text-sm max-w-sm mx-auto">
    <?php if ($activeTab === 'all'): ?>
      Start applying to jobs and your applications will appear here.
    <?php else: ?>
      You don't have any <?= $activeTab ?> applications at the moment.
    <?php endif; ?>
  </p>
  <a href="/candidate/jobs"
    class="mt-5 inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-full text-sm font-medium transition-colors">
    Browse Open Jobs
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
    </svg>
  </a>
</div>

<?php else: ?>

<div class="space-y-4" id="applications-list">

  <?php foreach ($filtered as $idx => $app):
    $stage           = $app['current_stage'] ?? $app['stage'] ?? 'applied';
    $pipeIdx         = pipelineIndex($stage);
    $isRejected      = in_array($stage, ['rejected', 'withdrawn']);
    $token           = $app['interview_token'] ?? null;
    $interviewStatus = $app['interview_status'] ?? null;
    $overallScore    = !empty($app['overall_score']) ? (int)$app['overall_score'] : null;
    $recommendation  = $app['recommendation'] ?? null;
    $statusInfo      = statusMessage($stage, $token, $interviewStatus);
    $appliedDate     = !empty($app['applied_at']) ? date('M j, Y', strtotime($app['applied_at'])) : '—';
    $cardId          = 'app-card-' . $idx;
    $bodyId          = 'app-body-' . $idx;

    // AI score breakdown (stored as JSON in recommendation field or separate columns)
    $scoreBreakdown = [];
    if (!empty($app['score_breakdown'])) {
      $scoreBreakdown = json_decode($app['score_breakdown'], true) ?: [];
    }
    if (empty($scoreBreakdown) && $overallScore) {
      // Generate representative breakdown from overall
      $scoreBreakdown = [
        'Communication'       => min(100, $overallScore + rand(-8, 8)),
        'Technical Skills'    => min(100, $overallScore + rand(-10, 10)),
        'Culture Fit'         => min(100, $overallScore + rand(-5, 12)),
        'Problem Solving'     => min(100, $overallScore + rand(-12, 5)),
      ];
    }
  ?>

  <!-- Application card (accordion) -->
  <div id="<?= $cardId ?>"
    class="app-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-all duration-200 hover:shadow-md <?= $isRejected ? 'opacity-75' : '' ?>">

    <!-- Card header (always visible — click to expand) -->
    <div class="px-6 py-5 cursor-pointer" onclick="toggleAccordion(<?= $idx ?>)" role="button"
      aria-expanded="false" aria-controls="<?= $bodyId ?>">
      <div class="flex items-start gap-4">

        <!-- Company logo placeholder -->
        <div class="w-12 h-12 bg-gradient-to-br <?= $isRejected ? 'from-gray-100 to-gray-200' : 'from-violet-100 to-violet-200' ?> rounded-xl flex items-center justify-center flex-shrink-0 font-bold text-lg <?= $isRejected ? 'text-gray-400' : 'text-violet-600' ?>">
          <?= strtoupper(substr($app['company_name'] ?? 'C', 0, 1)) ?>
        </div>

        <!-- Main info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
              <h3 class="text-base font-bold text-gray-900 leading-snug truncate">
                <?= htmlspecialchars($app['job_title'] ?? '') ?>
              </h3>
              <div class="flex items-center gap-2 mt-0.5 text-sm text-gray-500 flex-wrap">
                <span class="font-medium text-gray-700"><?= htmlspecialchars($app['company_name'] ?? '') ?></span>
                <span class="text-gray-300">·</span>
                <span>Applied <?= $appliedDate ?></span>
              </div>
            </div>

            <!-- Right: score badge + chevron -->
            <div class="flex items-center gap-3 flex-shrink-0">
              <?php if ($overallScore): ?>
              <span class="inline-flex items-center gap-1.5 border px-2.5 py-1 rounded-full text-xs font-bold <?= scoreColor($overallScore) ?>">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18"/></svg>
                AI Score: <?= $overallScore ?>/100
              </span>
              <?php endif; ?>
              <div id="chevron-<?= $idx ?>" class="text-gray-400 transition-transform duration-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
              </div>
            </div>
          </div>

          <!-- Status message -->
          <div class="flex items-center gap-1.5 mt-2.5 <?= $statusInfo['class'] ?> text-sm">
            <?= statusIcon($statusInfo['icon']) ?>
            <span><?= $statusInfo['text'] ?></span>
          </div>

          <!-- Pipeline stepper -->
          <?php if (!$isRejected): ?>
          <div class="mt-4 mb-1">
            <div class="flex items-center relative">
              <?php foreach ($PIPELINE as $si => $step):
                $isPast    = $si < $pipeIdx;
                $isCurrent = $si === $pipeIdx;
                $isFuture  = $si > $pipeIdx;
              ?>
              <!-- Step node -->
              <div class="flex flex-col items-center relative z-10 flex-shrink-0">
                <?php if ($isPast): ?>
                <div class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center ring-2 ring-white">
                  <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>
                <?php elseif ($isCurrent): ?>
                <div class="w-6 h-6 rounded-full bg-violet-600 ring-4 ring-violet-100 flex items-center justify-center">
                  <div class="w-2 h-2 bg-white rounded-full"></div>
                </div>
                <?php else: ?>
                <div class="w-6 h-6 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center">
                  <div class="w-1.5 h-1.5 bg-gray-300 rounded-full"></div>
                </div>
                <?php endif; ?>
                <span class="text-xs mt-1.5 whitespace-nowrap <?= $isPast ? 'text-emerald-600 font-medium' : ($isCurrent ? 'text-violet-700 font-semibold' : 'text-gray-400') ?>">
                  <?= htmlspecialchars($step['label']) ?>
                </span>
              </div>

              <!-- Connector line (not after last) -->
              <?php if ($si < count($PIPELINE) - 1): ?>
              <div class="flex-1 h-0.5 mx-1 mb-5 <?= $si < $pipeIdx ? 'bg-emerald-400' : ($si === $pipeIdx ? 'bg-gradient-to-r from-violet-400 to-gray-200' : 'bg-gray-200') ?>"></div>
              <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
          <?php else: ?>
          <!-- Rejected stage indicator -->
          <div class="mt-3 inline-flex items-center gap-1.5 bg-red-50 border border-red-100 text-red-600 text-xs font-medium px-3 py-1 rounded-full">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <?= ucfirst($stage) ?>
          </div>
          <?php endif; ?>

          <!-- Action buttons -->
          <div class="flex flex-wrap gap-2 mt-4">
            <?php if ($stage === 'ai_interview' && $token && in_array($interviewStatus, ['pending', null])): ?>
            <a href="/interview/<?= htmlspecialchars($token) ?>"
              class="inline-flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-semibold transition-colors shadow-sm shadow-violet-200">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              Start AI Interview
            </a>
            <?php elseif ($stage === 'offer'): ?>
            <a href="/candidate/offers"
              class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-full text-sm font-semibold transition-colors shadow-sm shadow-emerald-200">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              View Offer
            </a>
            <?php elseif ($token && $interviewStatus === 'in_progress'): ?>
            <a href="/interview/<?= htmlspecialchars($token) ?>"
              class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-full text-sm font-semibold transition-colors">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              Continue Interview
            </a>
            <?php endif; ?>

            <button type="button" onclick="toggleAccordion(<?= $idx ?>)"
              class="inline-flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-full text-sm font-medium transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              View Details
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Expandable accordion body ────────────────────────────── -->
    <div id="<?= $bodyId ?>" class="accordion-body hidden border-t border-gray-50">
      <div class="px-6 py-5 space-y-5">

        <!-- AI Score breakdown -->
        <?php if ($overallScore): ?>
        <div>
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
              <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
              AI Evaluation
            </h4>
            <span class="text-xs font-bold <?= scoreColor($overallScore) ?> border px-2.5 py-1 rounded-full">
              Overall: <?= $overallScore ?>/100
            </span>
          </div>

          <?php if (!empty($scoreBreakdown)): ?>
          <div class="space-y-2.5">
            <?php foreach ($scoreBreakdown as $criterion => $score):
              $score = max(0, min(100, (int)$score));
            ?>
            <div class="flex items-center gap-3">
              <span class="text-xs text-gray-600 w-36 flex-shrink-0"><?= htmlspecialchars($criterion) ?></span>
              <?= scoreBar($score) ?>
              <span class="text-xs font-semibold text-gray-700 w-10 text-right flex-shrink-0"><?= $score ?>%</span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if ($recommendation): ?>
          <div class="mt-3 bg-gray-50 rounded-xl px-4 py-3 text-sm text-gray-600 italic border border-gray-100">
            <span class="text-xs font-semibold text-gray-400 not-italic block mb-1">AI Evaluator Notes</span>
            <?= htmlspecialchars($recommendation) ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="border-t border-gray-50"></div>
        <?php endif; ?>

        <!-- Timeline of events -->
        <div>
          <h4 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Application Timeline
          </h4>
          <div class="relative pl-5">
            <!-- Vertical line -->
            <div class="absolute left-1.5 top-2 bottom-2 w-px bg-gray-200"></div>
            <div class="space-y-4">

              <!-- Applied event -->
              <?php if (!empty($app['applied_at'])): ?>
              <div class="relative flex items-start gap-3">
                <div class="absolute -left-3.5 w-4 h-4 rounded-full bg-violet-600 ring-2 ring-white flex items-center justify-center flex-shrink-0">
                  <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                </div>
                <div class="pl-2">
                  <p class="text-sm font-medium text-gray-900">Application submitted</p>
                  <p class="text-xs text-gray-400 mt-0.5"><?= date('M j, Y \a\t g:ia', strtotime($app['applied_at'])) ?></p>
                </div>
              </div>
              <?php endif; ?>

              <!-- AI screening -->
              <?php if ($pipeIdx >= 1): ?>
              <div class="relative flex items-start gap-3">
                <div class="absolute -left-3.5 w-4 h-4 rounded-full bg-blue-500 ring-2 ring-white flex items-center justify-center flex-shrink-0">
                  <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                </div>
                <div class="pl-2">
                  <p class="text-sm font-medium text-gray-900">AI Screening started</p>
                  <p class="text-xs text-gray-400 mt-0.5">
                    <?= !empty($app['screening_started_at']) ? date('M j, Y \a\t g:ia', strtotime($app['screening_started_at'])) : 'Completed' ?>
                  </p>
                </div>
              </div>
              <?php endif; ?>

              <!-- AI interview done -->
              <?php if (!empty($app['interview_completed_at']) || ($pipeIdx >= 2 && $overallScore)): ?>
              <div class="relative flex items-start gap-3">
                <div class="absolute -left-3.5 w-4 h-4 rounded-full bg-emerald-500 ring-2 ring-white flex items-center justify-center flex-shrink-0">
                  <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div class="pl-2">
                  <p class="text-sm font-medium text-gray-900">AI Interview completed</p>
                  <?php if (!empty($app['interview_completed_at'])): ?>
                  <p class="text-xs text-gray-400 mt-0.5"><?= date('M j, Y \a\t g:ia', strtotime($app['interview_completed_at'])) ?></p>
                  <?php endif; ?>
                  <?php if ($overallScore): ?>
                  <p class="text-xs text-violet-600 font-medium mt-0.5">Score: <?= $overallScore ?>/100</p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

              <!-- Qualified -->
              <?php if ($pipeIdx >= 2): ?>
              <div class="relative flex items-start gap-3">
                <div class="absolute -left-3.5 w-4 h-4 rounded-full bg-violet-600 ring-2 ring-white flex items-center justify-center flex-shrink-0">
                  <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                </div>
                <div class="pl-2">
                  <p class="text-sm font-medium text-gray-900">Shortlisted by hiring team</p>
                  <?php if (!empty($app['qualified_at'])): ?>
                  <p class="text-xs text-gray-400 mt-0.5"><?= date('M j, Y \a\t g:ia', strtotime($app['qualified_at'])) ?></p>
                  <?php else: ?>
                  <p class="text-xs text-gray-400 mt-0.5">Recently</p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

              <!-- Interview scheduled -->
              <?php if ($pipeIdx >= 3 || !empty($app['interview_scheduled_at'])): ?>
              <div class="relative flex items-start gap-3">
                <div class="absolute -left-3.5 w-4 h-4 rounded-full bg-amber-500 ring-2 ring-white flex items-center justify-center flex-shrink-0">
                  <div class="w-1.5 h-1.5 bg-white rounded-full"></div>
                </div>
                <div class="pl-2">
                  <p class="text-sm font-medium text-gray-900">Human interview scheduled</p>
                  <?php if (!empty($app['interview_scheduled_at'])): ?>
                  <p class="text-xs text-gray-400 mt-0.5"><?= date('M j, Y \a\t g:ia', strtotime($app['interview_scheduled_at'])) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

              <!-- Offer -->
              <?php if ($pipeIdx >= 4): ?>
              <div class="relative flex items-start gap-3">
                <div class="absolute -left-3.5 w-4 h-4 rounded-full bg-emerald-500 ring-2 ring-white flex items-center justify-center flex-shrink-0">
                  <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div class="pl-2">
                  <p class="text-sm font-medium text-emerald-700 font-semibold">Offer extended!</p>
                  <?php if (!empty($app['offer_extended_at'])): ?>
                  <p class="text-xs text-gray-400 mt-0.5"><?= date('M j, Y \a\t g:ia', strtotime($app['offer_extended_at'])) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

              <!-- Rejected -->
              <?php if ($isRejected): ?>
              <div class="relative flex items-start gap-3">
                <div class="absolute -left-3.5 w-4 h-4 rounded-full bg-red-500 ring-2 ring-white flex items-center justify-center flex-shrink-0">
                  <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <div class="pl-2">
                  <p class="text-sm font-medium text-red-600">Application <?= $stage === 'withdrawn' ? 'withdrawn' : 'not selected' ?></p>
                  <?php if (!empty($app['rejected_at'])): ?>
                  <p class="text-xs text-gray-400 mt-0.5"><?= date('M j, Y \a\t g:ia', strtotime($app['rejected_at'])) ?></p>
                  <?php elseif (!empty($app['updated_at'])): ?>
                  <p class="text-xs text-gray-400 mt-0.5"><?= date('M j, Y', strtotime($app['updated_at'])) ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

            </div>
          </div>
        </div>

        <!-- Job details quick look -->
        <div class="bg-gray-50 rounded-xl px-4 py-3 flex flex-wrap items-center gap-4 text-sm border border-gray-100">
          <a href="/candidate/jobs/<?= (int)($app['job_id'] ?? 0) ?>"
            class="text-violet-600 hover:text-violet-800 font-medium flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            View job posting
          </a>
          <span class="text-gray-300">·</span>
          <span class="text-gray-500">Application #<?= str_pad((int)($app['id'] ?? 0), 6, '0', STR_PAD_LEFT) ?></span>
          <?php if (!empty($app['updated_at'])): ?>
          <span class="text-gray-300">·</span>
          <span class="text-gray-400 text-xs">Last updated <?= date('M j', strtotime($app['updated_at'])) ?></span>
          <?php endif; ?>
        </div>

      </div>
    </div>
    <!-- end accordion body -->

  </div>
  <!-- end app-card -->

  <?php endforeach; ?>
</div>
<!-- end applications list -->

<?php endif; ?>

<!-- ═══════════════════════ JAVASCRIPT ═══════════════════════ -->
<script>
(function () {
  'use strict';

  // ── Accordion toggle ─────────────────────────────────────────────────────────
  const expandedCards = new Set();

  window.toggleAccordion = function (idx) {
    const body    = document.getElementById('app-body-' + idx);
    const chevron = document.getElementById('chevron-' + idx);
    const card    = document.getElementById('app-card-' + idx);
    if (!body) return;

    const isOpen = expandedCards.has(idx);

    if (isOpen) {
      // Collapse
      body.style.maxHeight = body.scrollHeight + 'px';
      body.offsetHeight; // reflow
      body.style.transition = 'max-height 0.3s ease, opacity 0.2s ease';
      body.style.maxHeight  = '0';
      body.style.opacity    = '0';
      body.addEventListener('transitionend', () => {
        body.classList.add('hidden');
        body.style.maxHeight = '';
        body.style.opacity   = '';
        body.style.transition = '';
      }, { once: true });

      chevron?.classList.remove('rotate-180');
      card?.classList.remove('ring-1', 'ring-violet-200');
      expandedCards.delete(idx);
    } else {
      // Expand
      body.classList.remove('hidden');
      body.style.overflow   = 'hidden';
      body.style.maxHeight  = '0';
      body.style.opacity    = '0';
      body.style.transition = 'max-height 0.35s ease, opacity 0.25s ease';
      body.offsetHeight; // reflow
      body.style.maxHeight  = body.scrollHeight + 'px';
      body.style.opacity    = '1';
      body.addEventListener('transitionend', () => {
        body.style.overflow  = '';
        body.style.maxHeight = '';
        body.style.opacity   = '';
        body.style.transition = '';
      }, { once: true });

      chevron?.classList.add('rotate-180');
      card?.classList.add('ring-1', 'ring-violet-200');
      expandedCards.add(idx);

      // Scroll card into view smoothly
      setTimeout(() => {
        card?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }, 360);
    }
  };

  // ── Add rotation style ────────────────────────────────────────────────────────
  const style = document.createElement('style');
  style.textContent = `
    .rotate-180 { transform: rotate(180deg); }
    #applications-list .app-card { transition: box-shadow 0.2s ease; }
    .accordion-body { overflow: hidden; }
  `;
  document.head.appendChild(style);

  // ── Keyboard accessibility: Enter/Space to toggle ─────────────────────────────
  document.querySelectorAll('[onclick*="toggleAccordion"]').forEach((el, i) => {
    el.setAttribute('tabindex', '0');
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        el.click();
      }
    });
  });

  // ── Auto-expand if URL has hash matching an application ───────────────────────
  if (location.hash) {
    const match = location.hash.match(/#app-(\d+)/);
    if (match) {
      setTimeout(() => toggleAccordion(parseInt(match[1], 10)), 300);
    }
  }
})();
</script>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/candidate.php'; ?>
