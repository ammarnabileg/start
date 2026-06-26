<?php
// views/hr/candidates/show.php
// Variables: $application, $candidate, $job, $aiInterview, $skillScores, $personality,
//            $redFlags, $recommendation, $cvAnalysis, $transcript, $notes, $humanInterviews
//            $criteriaScores, $questions, $documents
$application    = $application ?? [];
$candidate      = $candidate ?? [];
$job            = $job ?? [];
$aiInterview    = $aiInterview ?? [];
$skillScores    = $skillScores ?? [];
$personality    = $personality ?? [];
$redFlags       = $redFlags ?? [];
$recommendation = $recommendation ?? [];
$cvAnalysis     = $cvAnalysis ?? [];
$transcript     = $transcript ?? [];
$notes          = $notes ?? [];
$humanInterviews = $humanInterviews ?? [];
$criteriaScores = $criteriaScores ?? [];
$questions      = $questions ?? [];
$documents      = $documents ?? [];
$timeline       = $timeline ?? [];

$tab = $_GET['tab'] ?? 'summary';
$appId = (int)($application['id'] ?? 0);
$appStatus = $application['status'] ?? 'applied';
$firstName = $candidate['first_name'] ?? $application['first_name'] ?? 'Unknown';
$lastName  = $candidate['last_name']  ?? $application['last_name']  ?? '';
$fullName  = trim("$firstName $lastName");
$initials  = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1)) ?: 'U';
$finalScore = isset($recommendation['final_score']) ? (float)$recommendation['final_score'] : null;
$rec = $recommendation['recommendation'] ?? null;

$allStages = [
    'applied'           => 'Applied',
    'ai_screening'      => 'AI Screening',
    'qualified'         => 'Qualified',
    'disqualified'      => 'Disqualified',
    'tech_interview'    => 'Tech Interview',
    'manager_interview' => 'Mgr Interview',
    'final_review'      => 'Final Review',
    'offer'             => 'Offer',
    'hired'             => 'Hired',
    'rejected'          => 'Rejected',
    'withdrawn'         => 'Withdrawn',
];

function showBadge(string $text, string $color, string $bg): string {
    return "<span style=\"display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;background:{$bg};color:{$color};border:1px solid {$color}33\">{$text}</span>";
}
function recBadge(?string $r): string {
    if (!$r) return '';
    $map = ['strong_yes'=>['#4ade80','Strong Yes'],'yes'=>['#86efac','Yes'],'maybe'=>['#fbbf24','Maybe'],'no'=>['#f87171','No']];
    [$c,$l] = $map[$r] ?? ['#94a3b8', ucfirst((string)$r)];
    return "<span style=\"display:inline-block;padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:700;background:{$c}22;color:{$c};border:1px solid {$c}44\">{$l}</span>";
}
function skillBar(string $name, float $val, float $weight = 1.0): string {
    $pct = min(100, $val);
    $color = $pct >= 80 ? '#4ade80' : ($pct >= 60 ? '#818cf8' : ($pct >= 40 ? '#fbbf24' : '#f87171'));
    return "
    <div style=\"margin-bottom:14px;\">
      <div style=\"display:flex;justify-content:space-between;margin-bottom:5px;\">
        <span style=\"font-size:0.85rem;color:#e2e8f0;font-weight:500;\">{$name}</span>
        <span style=\"font-size:0.85rem;font-weight:700;color:{$color};\">" . number_format($val, 1) . "</span>
      </div>
      <div style=\"height:7px;background:rgba(79,70,229,0.15);border-radius:4px;overflow:hidden;\">
        <div style=\"height:100%;width:{$pct}%;background:{$color};border-radius:4px;transition:width 0.5s;\"></div>
      </div>
    </div>";
}
?>
<style>
  .btn { display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all 0.15s; }
  .btn-primary { background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff; }
  .btn-primary:hover { opacity:0.9; }
  .btn-ghost { background:transparent;color:#94a3b8;border:1px solid rgba(79,70,229,0.25); }
  .btn-ghost:hover { background:rgba(79,70,229,0.1);color:#e2e8f0; }
  .btn-sm { padding:6px 12px;font-size:0.78rem; }
  .btn-danger { background:rgba(239,68,68,0.12);color:#f87171;border:1px solid rgba(239,68,68,0.3); }
  .btn-danger:hover { background:rgba(239,68,68,0.2); }
  .breadcrumb { display:flex;align-items:center;gap:8px;font-size:0.82rem;color:#64748b;margin-bottom:16px; }
  .breadcrumb a { color:#64748b;text-decoration:none; }
  .breadcrumb a:hover { color:#818cf8; }
  /* Candidate header */
  .cand-header { background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:16px;padding:24px;margin-bottom:16px; }
  .cand-header-top { display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap; }
  .cand-avatar { width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;color:#fff;flex-shrink:0; }
  .cand-info { flex:1;min-width:0; }
  .cand-name { font-size:1.4rem;font-weight:800;color:#f1f5f9;margin-bottom:6px; }
  .cand-meta { display:flex;gap:12px;flex-wrap:wrap;align-items:center; }
  .cand-meta-item { font-size:0.82rem;color:#64748b;display:flex;align-items:center;gap:4px; }
  .score-big { text-align:center;flex-shrink:0; }
  .score-circle-big {
    width:72px;height:72px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:1.4rem;font-weight:800;
    border:3px solid;
    margin:0 auto 6px;
  }
  .score-label { font-size:0.72rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em; }
  .header-actions { display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;align-items:center; }
  /* Stage bar */
  .stage-bar { display:flex;gap:4px;overflow-x:auto;margin-bottom:20px;padding:4px 0; }
  .stage-pill {
    padding:7px 13px;border-radius:20px;font-size:0.78rem;font-weight:600;
    cursor:pointer;white-space:nowrap;border:1px solid rgba(79,70,229,0.2);
    background:rgba(15,15,26,0.5);color:#64748b;
    text-decoration:none;transition:all 0.15s;
  }
  .stage-pill:hover { border-color:rgba(79,70,229,0.5);color:#e2e8f0; }
  .stage-pill.active { background:#4f46e5;color:#fff;border-color:#4f46e5; }
  /* Tabs */
  .tab-bar { display:flex;gap:2px;margin-bottom:20px;border-bottom:1px solid rgba(79,70,229,0.12);overflow-x:auto; }
  .tab-link { padding:10px 14px;font-size:0.82rem;font-weight:500;color:#64748b;text-decoration:none;border-bottom:2px solid transparent;white-space:nowrap;margin-bottom:-1px;transition:all 0.15s; }
  .tab-link:hover { color:#e2e8f0; }
  .tab-link.active { color:#818cf8;border-bottom-color:#4f46e5; }
  /* Content card */
  .content-card { background:#1e1e32;border:1px solid rgba(79,70,229,0.12);border-radius:14px;overflow:hidden; }
  .card-body { padding:24px; }
  /* Summary tab */
  .exec-summary { font-size:0.95rem;color:#94a3b8;line-height:1.8;white-space:pre-wrap; }
  .strengths-list, .weaknesses-list { list-style:none;padding:0;margin:0; }
  .strengths-list li::before { content:'+ ';color:#4ade80;font-weight:700; }
  .weaknesses-list li::before { content:'- ';color:#f87171;font-weight:700; }
  .strengths-list li, .weaknesses-list li { padding:5px 0;font-size:0.875rem;color:#94a3b8; }
  .two-col { display:grid;grid-template-columns:1fr 1fr;gap:20px; }
  .section-subtitle { font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#475569;margin-bottom:12px; }
  /* Personality */
  .disc-bars { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
  .disc-item { text-align:center; }
  .disc-bar-wrap { height:80px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:6px; }
  .disc-bar-inner { width:36px;border-radius:6px 6px 0 0;transition:height 0.5s; }
  .disc-label { font-size:0.8rem;font-weight:700;color:#94a3b8; }
  .disc-val { font-size:0.9rem;font-weight:800;margin-top:2px; }
  .big5-row { display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(79,70,229,0.06); }
  .big5-row:last-child { border-bottom:none; }
  .big5-label { font-size:0.85rem;color:#94a3b8; }
  .big5-bar { width:120px;height:6px;background:rgba(79,70,229,0.15);border-radius:3px;overflow:hidden; }
  .big5-fill { height:100%;background:linear-gradient(90deg,#4f46e5,#7c3aed);border-radius:3px; }
  /* Red flags */
  .flag-item { display:flex;gap:12px;padding:14px 0;border-bottom:1px solid rgba(79,70,229,0.06); }
  .flag-item:last-child { border-bottom:none; }
  .flag-severity { width:70px;flex-shrink:0;font-size:0.72rem;font-weight:700;padding:3px 8px;border-radius:6px;text-align:center;height:fit-content; }
  .severity-high { background:rgba(239,68,68,0.15);color:#f87171; }
  .severity-medium { background:rgba(245,158,11,0.15);color:#fbbf24; }
  .severity-low { background:rgba(100,116,139,0.2);color:#94a3b8; }
  .flag-desc { font-size:0.875rem;color:#e2e8f0;font-weight:500;margin-bottom:4px; }
  .flag-evidence { font-size:0.8rem;color:#64748b;font-style:italic; }
  /* Transcript */
  .msg-bubble { display:flex;gap:10px;margin-bottom:16px; }
  .msg-bubble.ai { flex-direction:row; }
  .msg-bubble.candidate { flex-direction:row-reverse; }
  .msg-avatar-sm { width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:700;flex-shrink:0; }
  .msg-content { max-width:75%;background:rgba(79,70,229,0.1);border:1px solid rgba(79,70,229,0.15);border-radius:12px;padding:10px 14px;font-size:0.875rem;color:#e2e8f0;line-height:1.6; }
  .msg-bubble.candidate .msg-content { background:rgba(15,15,26,0.6);border-color:rgba(100,116,139,0.2); }
  .msg-role { font-size:0.72rem;color:#475569;margin-bottom:4px; }
  /* Timeline */
  .timeline-item { display:flex;gap:14px;margin-bottom:16px; }
  .timeline-dot { width:10px;height:10px;border-radius:50%;background:#4f46e5;margin-top:6px;flex-shrink:0; }
  .timeline-line { width:1px;background:rgba(79,70,229,0.2);margin-left:4px;margin-right:10px; }
  .timeline-evt { font-size:0.875rem;color:#e2e8f0; }
  .timeline-time { font-size:0.75rem;color:#475569;margin-top:3px; }
  /* Notes */
  .note-item { padding:14px 0;border-bottom:1px solid rgba(79,70,229,0.06); }
  .note-item:last-child { border-bottom:none; }
  .note-author { font-size:0.78rem;font-weight:600;color:#818cf8; }
  .note-time { font-size:0.75rem;color:#475569;margin-left:8px; }
  .note-text { font-size:0.875rem;color:#94a3b8;margin-top:6px;line-height:1.6; }
  .note-form textarea { width:100%;background:#0f0f1a;border:1px solid rgba(79,70,229,0.25);border-radius:8px;color:#e2e8f0;padding:12px;font-size:0.875rem;font-family:inherit;resize:vertical;outline:none;min-height:80px; }
  .note-form textarea:focus { border-color:#4f46e5;box-shadow:0 0 0 2px rgba(79,70,229,0.1); }
  /* Human interviews */
  .hi-card { background:rgba(15,15,26,0.5);border:1px solid rgba(79,70,229,0.15);border-radius:12px;padding:18px;margin-bottom:12px; }
  .hi-title { font-weight:700;color:#e2e8f0;font-size:0.9rem; }
  .hi-meta { font-size:0.8rem;color:#64748b;margin-top:4px; }
  /* Docs */
  .doc-item { display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid rgba(79,70,229,0.06); }
  .doc-item:last-child { border-bottom:none; }
  .doc-icon { width:38px;height:38px;border-radius:8px;background:rgba(79,70,229,0.1);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
  .doc-name { font-size:0.875rem;color:#e2e8f0;font-weight:500; }
  .doc-meta { font-size:0.75rem;color:#475569;margin-top:2px; }
  /* Empty */
  .empty-tab { text-align:center;padding:48px;color:#475569;font-size:0.875rem; }
  .two-col-grid { display:grid;grid-template-columns:1fr 1fr;gap:24px; }
  @media (max-width:768px) { .two-col, .two-col-grid { grid-template-columns:1fr; } .disc-bars { grid-template-columns:repeat(4,1fr); } }
</style>

<div class="breadcrumb">
  <a href="/candidates">Candidates</a>
  <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <span style="color:#94a3b8;"><?= htmlspecialchars($fullName) ?></span>
</div>

<!-- Candidate Header -->
<div class="cand-header">
  <div class="cand-header-top">
    <div class="cand-avatar"><?= htmlspecialchars($initials) ?></div>
    <div class="cand-info">
      <div class="cand-name"><?= htmlspecialchars($fullName) ?></div>
      <div class="cand-meta">
        <span class="cand-meta-item">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <?= htmlspecialchars($candidate['email'] ?? $application['email'] ?? '') ?>
        </span>
        <?php if ($job['title'] ?? ''): ?>
          <span class="cand-meta-item">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/></svg>
            <?= htmlspecialchars($job['title']) ?>
          </span>
        <?php endif; ?>
        <?php if ($application['applied_at'] ?? ''): ?>
          <span class="cand-meta-item">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Applied <?= date('M j, Y', strtotime($application['applied_at'])) ?>
          </span>
        <?php endif; ?>
      </div>
      <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
        <?= recBadge($rec) ?>
      </div>
    </div>
    <?php if ($finalScore !== null): ?>
      <div class="score-big">
        <?php
          $sc = $finalScore;
          $borderColor = $sc >= 80 ? '#4ade80' : ($sc >= 60 ? '#fbbf24' : '#f87171');
          $bgColor = $sc >= 80 ? 'rgba(34,197,94,0.1)' : ($sc >= 60 ? 'rgba(245,158,11,0.1)' : 'rgba(239,68,68,0.1)');
        ?>
        <div class="score-circle-big" style="border-color:<?= $borderColor ?>;background:<?= $bgColor ?>;color:<?= $borderColor ?>;">
          <?= number_format($finalScore, 0) ?>
        </div>
        <div class="score-label">AI Score</div>
      </div>
    <?php endif; ?>
  </div>

  <div class="header-actions">
    <!-- Change Status dropdown -->
    <div style="position:relative;">
      <button class="btn btn-ghost" onclick="document.getElementById('statusDrop').classList.toggle('open')">
        Change Status
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div id="statusDrop" style="display:none;position:absolute;top:110%;left:0;background:#1a1a2e;border:1px solid rgba(79,70,229,0.3);border-radius:10px;z-index:100;min-width:180px;box-shadow:0 8px 24px rgba(0,0,0,0.4);overflow:hidden;">
        <?php foreach ($allStages as $stVal => $stLabel): ?>
          <form method="POST" action="/pipeline/move">
            <input type="hidden" name="application_id" value="<?= $appId ?>">
            <input type="hidden" name="status" value="<?= $stVal ?>">
            <button type="submit" style="display:block;width:100%;text-align:left;padding:9px 16px;background:none;border:none;color:<?= $appStatus === $stVal ? '#818cf8' : '#94a3b8' ?>;font-size:0.85rem;cursor:pointer;" onmouseover="this.style.background='rgba(79,70,229,0.1)'" onmouseout="this.style.background='none'">
              <?= htmlspecialchars($stLabel) ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
    <form method="POST" action="/talent-pool/add" style="display:inline;">
      <input type="hidden" name="application_id" value="<?= $appId ?>">
      <button type="submit" class="btn btn-ghost">+ Talent Pool</button>
    </form>
    <a href="/comparisons?ids=<?= $appId ?>" class="btn btn-ghost">Compare</a>
    <a href="/candidates/<?= $appId ?>?export=pdf" class="btn btn-ghost">Export PDF</a>
    <?php if ($appStatus !== 'rejected'): ?>
      <form method="POST" action="/pipeline/move" onsubmit="return confirm('Reject this candidate?')">
        <input type="hidden" name="application_id" value="<?= $appId ?>">
        <input type="hidden" name="status" value="rejected">
        <button type="submit" class="btn btn-danger">Reject</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Stage bar -->
<div class="stage-bar">
  <?php foreach ($allStages as $stVal => $stLabel): ?>
    <form method="POST" action="/pipeline/move" style="display:inline;">
      <input type="hidden" name="application_id" value="<?= $appId ?>">
      <input type="hidden" name="status" value="<?= $stVal ?>">
      <button type="submit" class="stage-pill <?= $appStatus === $stVal ? 'active' : '' ?>">
        <?= htmlspecialchars($stLabel) ?>
      </button>
    </form>
  <?php endforeach; ?>
</div>

<!-- Tabs -->
<div class="tab-bar">
  <?php
  $tabs = [
    'summary'    => 'Executive Summary',
    'skills'     => 'Skill Scores',
    'behavioral' => 'Behavioral Analysis',
    'redflags'   => 'Red Flags' . (!empty($redFlags) ? ' <span style="font-size:0.7rem;background:rgba(239,68,68,0.2);color:#f87171;padding:1px 5px;border-radius:8px;">' . count($redFlags) . '</span>' : ''),
    'cv'         => 'CV Analysis',
    'transcript' => 'Interview Transcript',
    'timeline'   => 'Timeline',
    'criteria'   => 'Criteria',
    'questions'  => 'Questions',
    'interviews' => 'Human Interviews',
    'notes'      => 'Notes' . (!empty($notes) ? ' <span style="font-size:0.7rem;background:rgba(79,70,229,0.2);color:#818cf8;padding:1px 5px;border-radius:8px;">' . count($notes) . '</span>' : ''),
    'documents'  => 'Documents',
  ];
  foreach ($tabs as $tKey => $tLabel):
    $href = '/candidates/' . $appId . '?tab=' . $tKey;
  ?>
    <a href="<?= $href ?>" class="tab-link <?= $tab === $tKey ? 'active' : '' ?>"><?= $tLabel ?></a>
  <?php endforeach; ?>
</div>

<!-- TAB CONTENT -->

<?php if ($tab === 'summary'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (!empty($recommendation['executive_summary'])): ?>
      <div style="margin-bottom:28px;">
        <div class="section-subtitle">Executive Summary</div>
        <div class="exec-summary"><?= htmlspecialchars($recommendation['executive_summary']) ?></div>
      </div>
    <?php endif; ?>
    <?php
      $strengths = is_string($recommendation['strengths'] ?? null) ? json_decode($recommendation['strengths'], true) : ($recommendation['strengths'] ?? []);
      $weaknesses = is_string($recommendation['weaknesses'] ?? null) ? json_decode($recommendation['weaknesses'], true) : ($recommendation['weaknesses'] ?? []);
    ?>
    <?php if (!empty($strengths) || !empty($weaknesses)): ?>
      <div class="two-col">
        <?php if (!empty($strengths)): ?>
          <div>
            <div class="section-subtitle">Strengths</div>
            <ul class="strengths-list">
              <?php foreach ($strengths as $s): ?>
                <li><?= htmlspecialchars((string)$s) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <?php if (!empty($weaknesses)): ?>
          <div>
            <div class="section-subtitle">Areas of Concern</div>
            <ul class="weaknesses-list">
              <?php foreach ($weaknesses as $w): ?>
                <li><?= htmlspecialchars((string)$w) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($recommendation['hiring_risks'] ?? ''): ?>
      <div style="margin-top:24px;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.2);border-radius:10px;padding:16px;">
        <div class="section-subtitle" style="color:#fbbf24;margin-bottom:8px;">Hiring Risks</div>
        <p style="font-size:0.875rem;color:#94a3b8;line-height:1.6;"><?= htmlspecialchars($recommendation['hiring_risks']) ?></p>
      </div>
    <?php endif; ?>
    <?php if (empty($recommendation)): ?>
      <div class="empty-tab">AI interview has not been completed yet. No recommendation available.</div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'skills'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (empty($skillScores)): ?>
      <div class="empty-tab">Skill scores not available. Complete an AI interview to generate scores.</div>
    <?php else: ?>
      <div class="two-col-grid">
        <div>
          <?php
          $leftSkills = [
            'Technical Competency' => (float)($skillScores['technical_competency'] ?? 0),
            'Communication'        => (float)($skillScores['communication'] ?? 0),
            'Problem Solving'      => (float)($skillScores['problem_solving'] ?? 0),
            'Critical Thinking'    => (float)($skillScores['critical_thinking'] ?? 0),
            'Confidence'           => (float)($skillScores['confidence'] ?? 0),
            'Leadership'           => (float)($skillScores['leadership'] ?? 0),
          ];
          foreach ($leftSkills as $name => $val): echo skillBar($name, $val); endforeach; ?>
        </div>
        <div>
          <?php
          $rightSkills = [
            'Culture Fit'          => (float)($skillScores['culture_fit'] ?? 0),
            'Professionalism'      => (float)($skillScores['professionalism'] ?? 0),
            'AI Knowledge'         => (float)($skillScores['ai_knowledge'] ?? 0),
            'English Proficiency'  => (float)($skillScores['english_proficiency'] ?? 0),
            'Learning Ability'     => (float)($skillScores['learning_ability'] ?? 0),
          ];
          foreach ($rightSkills as $name => $val): echo skillBar($name, $val); endforeach; ?>
          <div style="margin-top:20px;padding-top:20px;border-top:1px solid rgba(79,70,229,0.1);">
            <div class="section-subtitle">Overall Score</div>
            <div style="font-size:2.5rem;font-weight:800;color:<?= (float)($skillScores['overall_score'] ?? 0) >= 80 ? '#4ade80' : ((float)($skillScores['overall_score'] ?? 0) >= 60 ? '#fbbf24' : '#f87171') ?>;"><?= number_format((float)($skillScores['overall_score'] ?? 0), 1) ?><span style="font-size:1rem;color:#475569;">/100</span></div>
            <?php if ($skillScores['confidence_level'] ?? null): ?>
              <div style="font-size:0.78rem;color:#64748b;margin-top:4px;">Confidence level: <?= number_format((float)$skillScores['confidence_level'], 1) ?>%</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'behavioral'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (empty($personality)): ?>
      <div class="empty-tab">Behavioral analysis not available.</div>
    <?php else: ?>
      <div class="two-col-grid">
        <div>
          <div class="section-subtitle">DISC Profile</div>
          <div class="disc-bars">
            <?php
            $disc = [
              'D' => [(float)($personality['disc_d'] ?? 0), '#f87171'],
              'I' => [(float)($personality['disc_i'] ?? 0), '#fbbf24'],
              'S' => [(float)($personality['disc_s'] ?? 0), '#4ade80'],
              'C' => [(float)($personality['disc_c'] ?? 0), '#818cf8'],
            ];
            foreach ($disc as $letter => [$val, $color]): ?>
              <div class="disc-item">
                <div class="disc-bar-wrap">
                  <div class="disc-bar-inner" style="height:<?= max(4, $val) ?>px;background:<?= $color ?>;"></div>
                </div>
                <div class="disc-label"><?= $letter ?></div>
                <div class="disc-val" style="color:<?= $color ?>;"><?= number_format($val, 0) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($personality['leadership_style'] ?? ''): ?>
            <div style="margin-top:16px;padding:12px;background:rgba(79,70,229,0.06);border-radius:8px;">
              <div style="font-size:0.75rem;color:#475569;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.05em;">Leadership Style</div>
              <div style="font-size:0.9rem;color:#e2e8f0;font-weight:600;"><?= htmlspecialchars($personality['leadership_style']) ?></div>
            </div>
          <?php endif; ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;">
            <div style="background:rgba(34,197,94,0.06);border:1px solid rgba(34,197,94,0.2);border-radius:10px;padding:14px;text-align:center;">
              <div style="font-size:1.5rem;font-weight:800;color:#4ade80;"><?= number_format((float)($personality['growth_score'] ?? 0), 0) ?></div>
              <div style="font-size:0.75rem;color:#64748b;margin-top:4px;">Growth Score</div>
            </div>
            <div style="background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.2);border-radius:10px;padding:14px;text-align:center;">
              <div style="font-size:1.5rem;font-weight:800;color:#fbbf24;"><?= number_format((float)($personality['pressure_score'] ?? 0), 0) ?></div>
              <div style="font-size:0.75rem;color:#64748b;margin-top:4px;">Pressure Score</div>
            </div>
          </div>
        </div>
        <div>
          <div class="section-subtitle">Big Five Personality</div>
          <?php
          $big5 = [
            'Openness'          => (float)($personality['big5_openness'] ?? 0),
            'Conscientiousness' => (float)($personality['big5_conscientiousness'] ?? 0),
            'Extraversion'      => (float)($personality['big5_extraversion'] ?? 0),
            'Agreeableness'     => (float)($personality['big5_agreeableness'] ?? 0),
            'Neuroticism'       => (float)($personality['big5_neuroticism'] ?? 0),
          ];
          foreach ($big5 as $name => $val): ?>
            <div class="big5-row">
              <span class="big5-label"><?= $name ?></span>
              <div class="big5-bar"><div class="big5-fill" style="width:<?= min(100,$val) ?>%;"></div></div>
              <span style="font-size:0.82rem;font-weight:700;color:#818cf8;min-width:30px;text-align:right;"><?= number_format($val, 0) ?></span>
            </div>
          <?php endforeach; ?>
          <?php if ($personality['summary'] ?? ''): ?>
            <div style="margin-top:16px;font-size:0.85rem;color:#94a3b8;line-height:1.7;"><?= htmlspecialchars($personality['summary']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'redflags'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (empty($redFlags)): ?>
      <div class="empty-tab" style="color:#4ade80;">
        <svg width="40" height="40" fill="none" stroke="#4ade80" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;opacity:0.5;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        No red flags detected.
      </div>
    <?php else: ?>
      <?php
        usort($redFlags, function($a, $b) {
          $order = ['high'=>0,'medium'=>1,'low'=>2];
          return ($order[$a['severity']] ?? 1) <=> ($order[$b['severity']] ?? 1);
        });
        foreach ($redFlags as $flag): ?>
          <div class="flag-item">
            <div class="flag-severity severity-<?= htmlspecialchars($flag['severity'] ?? 'low') ?>"><?= ucfirst($flag['severity'] ?? 'low') ?></div>
            <div>
              <?php if ($flag['category'] ?? ''): ?>
                <div style="font-size:0.72rem;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px;"><?= htmlspecialchars($flag['category']) ?></div>
              <?php endif; ?>
              <div class="flag-desc"><?= htmlspecialchars($flag['description'] ?? '') ?></div>
              <?php if ($flag['evidence'] ?? ''): ?>
                <div class="flag-evidence">"<?= htmlspecialchars($flag['evidence']) ?>"</div>
              <?php endif; ?>
            </div>
          </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'cv'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (empty($cvAnalysis)): ?>
      <div class="empty-tab">CV analysis not available. Upload a CV and run screening to generate analysis.</div>
    <?php else: ?>
      <div class="two-col-grid" style="margin-bottom:24px;">
        <div style="background:rgba(79,70,229,0.06);border:1px solid rgba(79,70,229,0.2);border-radius:12px;padding:20px;text-align:center;">
          <div style="font-size:2.2rem;font-weight:800;color:#818cf8;"><?= number_format((float)($cvAnalysis['match_score'] ?? 0), 0) ?>%</div>
          <div style="font-size:0.8rem;color:#64748b;margin-top:4px;">CV Match Score</div>
        </div>
        <div style="background:rgba(15,15,26,0.5);border:1px solid rgba(79,70,229,0.1);border-radius:12px;padding:20px;">
          <div style="font-size:0.75rem;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">Education Level</div>
          <div style="font-size:0.95rem;color:#e2e8f0;font-weight:600;"><?= htmlspecialchars($cvAnalysis['education_level'] ?? 'Not specified') ?></div>
          <div style="font-size:0.75rem;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin-top:12px;margin-bottom:8px;">Years Experience</div>
          <div style="font-size:0.95rem;color:#e2e8f0;font-weight:600;"><?= number_format((float)($cvAnalysis['years_experience'] ?? 0), 1) ?> years</div>
        </div>
      </div>
      <?php
        $skills = is_string($cvAnalysis['skills_extracted'] ?? null) ? json_decode($cvAnalysis['skills_extracted'], true) : ($cvAnalysis['skills_extracted'] ?? []);
        $companies = is_string($cvAnalysis['companies_extracted'] ?? null) ? json_decode($cvAnalysis['companies_extracted'], true) : ($cvAnalysis['companies_extracted'] ?? []);
      ?>
      <?php if (!empty($skills)): ?>
        <div style="margin-bottom:20px;">
          <div class="section-subtitle">Extracted Skills</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ((array)$skills as $skill): ?>
              <span style="background:rgba(79,70,229,0.1);border:1px solid rgba(79,70,229,0.2);color:#818cf8;padding:4px 10px;border-radius:20px;font-size:0.8rem;"><?= htmlspecialchars((string)$skill) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      <?php if (!empty($companies)): ?>
        <div style="margin-bottom:20px;">
          <div class="section-subtitle">Previous Companies</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ((array)$companies as $company): ?>
              <span style="background:rgba(100,116,139,0.1);border:1px solid rgba(100,116,139,0.2);color:#94a3b8;padding:4px 10px;border-radius:20px;font-size:0.8rem;"><?= htmlspecialchars((string)$company) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($cvAnalysis['notes'] ?? ''): ?>
        <div>
          <div class="section-subtitle">Analyst Notes</div>
          <div style="font-size:0.875rem;color:#94a3b8;line-height:1.7;"><?= htmlspecialchars($cvAnalysis['notes']) ?></div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'transcript'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (empty($transcript)): ?>
      <div class="empty-tab">No interview transcript available.</div>
    <?php else: ?>
      <div style="max-height:600px;overflow-y:auto;padding-right:4px;">
        <?php foreach ($transcript as $msg): ?>
          <div class="msg-bubble <?= $msg['role'] === 'ai' ? 'ai' : 'candidate' ?>">
            <div class="msg-avatar-sm" style="background:<?= $msg['role'] === 'ai' ? 'linear-gradient(135deg,#4f46e5,#7c3aed)' : 'rgba(100,116,139,0.3)' ?>;color:#fff;">
              <?= $msg['role'] === 'ai' ? 'AI' : $initials ?>
            </div>
            <div>
              <div class="msg-role"><?= $msg['role'] === 'ai' ? 'AI Interviewer' : htmlspecialchars($fullName) ?> &middot; <?= isset($msg['sent_at']) ? date('H:i', strtotime($msg['sent_at'])) : '' ?></div>
              <div class="msg-content"><?= htmlspecialchars($msg['content'] ?? '') ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'timeline'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (empty($timeline)): ?>
      <div class="empty-tab">No timeline events recorded.</div>
    <?php else: ?>
      <?php foreach ($timeline as $event): ?>
        <div class="timeline-item">
          <div>
            <div class="timeline-dot"></div>
          </div>
          <div>
            <div class="timeline-evt"><?= htmlspecialchars($event['description'] ?? $event['event_type'] ?? '') ?></div>
            <div class="timeline-time"><?= isset($event['occurred_at']) ? date('M j, Y H:i', strtotime($event['occurred_at'])) : '' ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'criteria'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (empty($criteriaScores)): ?>
      <div class="empty-tab">No criteria scores available.</div>
    <?php else: ?>
      <?php foreach ($criteriaScores as $cs): ?>
        <div style="margin-bottom:20px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;align-items:center;">
            <div>
              <div style="font-size:0.875rem;font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($cs['criteria_name'] ?? $cs['name'] ?? '') ?></div>
              <?php if ($cs['notes'] ?? ''): ?>
                <div style="font-size:0.78rem;color:#64748b;margin-top:2px;"><?= htmlspecialchars($cs['notes']) ?></div>
              <?php endif; ?>
            </div>
            <div style="font-size:1rem;font-weight:800;color:<?= (float)($cs['score'] ?? 0) >= 3.5 ? '#4ade80' : ((float)($cs['score'] ?? 0) >= 2.5 ? '#fbbf24' : '#f87171') ?>;">
              <?= number_format((float)($cs['score'] ?? 0), 1) ?>/<?= number_format((float)($cs['max_score'] ?? 5), 0) ?>
            </div>
          </div>
          <div style="height:7px;background:rgba(79,70,229,0.12);border-radius:4px;overflow:hidden;">
            <div style="height:100%;width:<?= min(100, ((float)($cs['score'] ?? 0) / max(1, (float)($cs['max_score'] ?? 5))) * 100) ?>%;background:<?= (float)($cs['score'] ?? 0) >= 3.5 ? '#4ade80' : ((float)($cs['score'] ?? 0) >= 2.5 ? '#fbbf24' : '#f87171') ?>;border-radius:4px;"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'questions'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (empty($questions)): ?>
      <div class="empty-tab">No questions recorded for this interview.</div>
    <?php else: ?>
      <?php foreach ($questions as $i => $q): ?>
        <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid rgba(79,70,229,0.08);">
          <div style="font-size:0.72rem;color:#475569;margin-bottom:6px;">Q<?= $i + 1 ?></div>
          <div style="font-size:0.9rem;font-weight:600;color:#e2e8f0;margin-bottom:8px;"><?= htmlspecialchars($q['question_text'] ?? $q['question'] ?? '') ?></div>
          <?php if ($q['answer_text'] ?? ''): ?>
            <div style="background:rgba(15,15,26,0.5);border-left:3px solid rgba(79,70,229,0.4);padding:10px 14px;border-radius:0 8px 8px 0;">
              <div style="font-size:0.75rem;color:#475569;margin-bottom:4px;">Candidate Answer:</div>
              <div style="font-size:0.85rem;color:#94a3b8;line-height:1.6;"><?= htmlspecialchars($q['answer_text']) ?></div>
            </div>
          <?php endif; ?>
          <?php if (isset($q['score'])): ?>
            <div style="margin-top:8px;font-size:0.8rem;color:#818cf8;">Score: <strong><?= number_format((float)$q['score'], 1) ?></strong></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'interviews'): ?>
<div class="content-card">
  <div class="card-body">
    <div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
      <button class="btn btn-primary" onclick="document.getElementById('scheduleForm').style.display='block'">+ Schedule Interview</button>
    </div>
    <div id="scheduleForm" style="display:none;background:rgba(15,15,26,0.5);border:1px solid rgba(79,70,229,0.2);border-radius:12px;padding:20px;margin-bottom:20px;">
      <form method="POST" action="/human-interviews/create">
        <input type="hidden" name="application_id" value="<?= $appId ?>">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:14px;">
          <div><label style="font-size:0.78rem;color:#64748b;display:block;margin-bottom:4px;">Title</label>
            <input name="title" style="width:100%;background:#0f0f1a;border:1px solid rgba(79,70,229,0.25);border-radius:8px;color:#e2e8f0;padding:9px 12px;font-size:0.875rem;outline:none;" placeholder="Technical Interview" required></div>
          <div><label style="font-size:0.78rem;color:#64748b;display:block;margin-bottom:4px;">Type</label>
            <select name="type" style="width:100%;background:#0f0f1a;border:1px solid rgba(79,70,229,0.25);border-radius:8px;color:#e2e8f0;padding:9px 12px;font-size:0.875rem;outline:none;">
              <option value="technical">Technical</option><option value="manager">Manager</option><option value="final">Final</option><option value="hr">HR</option>
            </select></div>
          <div><label style="font-size:0.78rem;color:#64748b;display:block;margin-bottom:4px;">Date &amp; Time</label>
            <input type="datetime-local" name="scheduled_at" style="width:100%;background:#0f0f1a;border:1px solid rgba(79,70,229,0.25);border-radius:8px;color:#e2e8f0;padding:9px 12px;font-size:0.875rem;outline:none;" required></div>
          <div><label style="font-size:0.78rem;color:#64748b;display:block;margin-bottom:4px;">Duration (min)</label>
            <input type="number" name="duration_minutes" value="60" style="width:100%;background:#0f0f1a;border:1px solid rgba(79,70,229,0.25);border-radius:8px;color:#e2e8f0;padding:9px 12px;font-size:0.875rem;outline:none;"></div>
          <div><label style="font-size:0.78rem;color:#64748b;display:block;margin-bottom:4px;">Meeting Link</label>
            <input name="meeting_link" style="width:100%;background:#0f0f1a;border:1px solid rgba(79,70,229,0.25);border-radius:8px;color:#e2e8f0;padding:9px 12px;font-size:0.875rem;outline:none;" placeholder="https://meet.google.com/…"></div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('scheduleForm').style.display='none'">Cancel</button>
          <button type="submit" class="btn btn-primary">Schedule</button>
        </div>
      </form>
    </div>
    <?php if (empty($humanInterviews)): ?>
      <div class="empty-tab">No human interviews scheduled yet.</div>
    <?php else: ?>
      <?php foreach ($humanInterviews as $hi):
        $hiStatus = $hi['status'] ?? 'scheduled';
        $hiColors = ['scheduled'=>'#fbbf24','completed'=>'#4ade80','cancelled'=>'#f87171','no_show'=>'#f87171'];
        $hiColor = $hiColors[$hiStatus] ?? '#94a3b8';
      ?>
        <div class="hi-card">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
            <div>
              <div class="hi-title"><?= htmlspecialchars($hi['title'] ?? '') ?></div>
              <div class="hi-meta">
                <?= ucfirst($hi['type'] ?? '') ?> &middot;
                <?= $hi['scheduled_at'] ? date('M j, Y H:i', strtotime($hi['scheduled_at'])) : '' ?> &middot;
                <?= (int)($hi['duration_minutes'] ?? 60) ?> min
                <?= $hi['meeting_link'] ? ' &middot; <a href="' . htmlspecialchars($hi['meeting_link']) . '" target="_blank" style="color:#818cf8;">Join Link</a>' : '' ?>
              </div>
            </div>
            <span style="font-size:0.75rem;font-weight:700;color:<?= $hiColor ?>;background:<?= $hiColor ?>22;padding:3px 8px;border-radius:6px;white-space:nowrap;"><?= ucwords(str_replace('_',' ',$hiStatus)) ?></span>
          </div>
          <?php if ($hi['notes'] ?? ''): ?>
            <div style="margin-top:10px;font-size:0.8rem;color:#64748b;"><?= htmlspecialchars($hi['notes']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'notes'): ?>
<div class="content-card">
  <div class="card-body">
    <!-- Add note form -->
    <div style="margin-bottom:24px;" class="note-form">
      <form method="POST" action="/candidates/<?= $appId ?>/notes">
        <textarea name="note" placeholder="Add a note about this candidate…" required></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
          <label style="display:flex;align-items:center;gap:6px;font-size:0.8rem;color:#64748b;cursor:pointer;">
            <input type="checkbox" name="is_private" value="1" style="accent-color:#4f46e5;">
            Private note (only visible to you)
          </label>
          <button type="submit" class="btn btn-primary btn-sm">Add Note</button>
        </div>
      </form>
    </div>
    <!-- Existing notes -->
    <?php if (empty($notes)): ?>
      <div class="empty-tab" style="padding:24px;">No notes yet.</div>
    <?php else: ?>
      <?php foreach (array_reverse($notes) as $note): ?>
        <div class="note-item">
          <div>
            <span class="note-author"><?= htmlspecialchars(trim(($note['first_name'] ?? '') . ' ' . ($note['last_name'] ?? ''))) ?></span>
            <span class="note-time"><?= $note['created_at'] ? date('M j, Y H:i', strtotime($note['created_at'])) : '' ?></span>
            <?= ($note['is_private'] ?? false) ? '<span style="font-size:0.72rem;color:#fbbf24;margin-left:6px;">Private</span>' : '' ?>
          </div>
          <div class="note-text"><?= htmlspecialchars($note['note'] ?? '') ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'documents'): ?>
<div class="content-card">
  <div class="card-body">
    <?php if (empty($documents)): ?>
      <div class="empty-tab">No documents uploaded.</div>
    <?php else: ?>
      <?php foreach ($documents as $doc): ?>
        <div class="doc-item">
          <div class="doc-icon">
            <svg width="18" height="18" fill="none" stroke="#818cf8" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          </div>
          <div style="flex:1;">
            <div class="doc-name"><?= htmlspecialchars($doc['original_name'] ?? $doc['filename'] ?? '') ?></div>
            <div class="doc-meta">
              <?= ucfirst($doc['type'] ?? 'document') ?> &middot;
              <?= isset($doc['file_size']) ? number_format($doc['file_size'] / 1024, 1) . ' KB' : '' ?>
              &middot; <?= $doc['created_at'] ? date('M j, Y', strtotime($doc['created_at'])) : '' ?>
            </div>
          </div>
          <a href="<?= htmlspecialchars($doc['file_path'] ?? '#') ?>" target="_blank" class="btn btn-ghost btn-sm">Download</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<script>
// Close dropdown on outside click
document.addEventListener('click', function(e) {
  const drop = document.getElementById('statusDrop');
  if (drop && !drop.parentElement.contains(e.target)) {
    drop.style.display = 'none';
  }
});
const dropBtn = document.querySelector('[onclick*="statusDrop"]');
const dropMenu = document.getElementById('statusDrop');
if (dropBtn && dropMenu) {
  dropBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    dropMenu.style.display = dropMenu.style.display === 'none' ? 'block' : 'none';
  });
}
</script>
