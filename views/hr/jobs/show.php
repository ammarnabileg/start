<?php
// views/hr/jobs/show.php
// Variables: $job, $applications, $stats, $settings, $criteria, $questions, $links, $tab
$job          = $job ?? [];
$applications = $applications ?? [];
$stats        = $stats ?? [];
$settings     = $settings ?? [];
$criteria     = $criteria ?? [];
$questions    = $questions ?? [];
$links        = $links ?? [];
$tab          = $tab ?? 'overview';

function showStatusBadge(string $s): string {
    $map = [
        'applied'           => ['#3b82f620','#60a5fa','#3b82f640','Applied'],
        'ai_screening'      => ['#8b5cf620','#a78bfa','#8b5cf640','AI Screening'],
        'qualified'         => ['#22c55e20','#4ade80','#22c55e40','Qualified'],
        'disqualified'      => ['#ef444420','#f87171','#ef444440','Disqualified'],
        'tech_interview'    => ['#f59e0b20','#fbbf24','#f59e0b40','Tech Interview'],
        'manager_interview' => ['#f59e0b20','#fbbf24','#f59e0b40','Mgr Interview'],
        'final_review'      => ['#ec489920','#f472b6','#ec489940','Final Review'],
        'offer'             => ['#14b8a620','#2dd4bf','#14b8a640','Offer'],
        'hired'             => ['#22c55e20','#4ade80','#22c55e40','Hired'],
        'rejected'          => ['#ef444420','#f87171','#ef444440','Rejected'],
        'withdrawn'         => ['#64748b20','#94a3b8','#64748b40','Withdrawn'],
    ];
    [$bg,$fg,$bd,$label] = $map[$s] ?? ['#64748b20','#94a3b8','#64748b40',ucfirst($s)];
    return "<span style=\"display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;background:{$bg};color:{$fg};border:1px solid {$bd}\">{$label}</span>";
}
function showRecBadge(?string $r): string {
    if (!$r) return '';
    $map = ['strong_yes'=>['#4ade80','Strong Yes'],'yes'=>['#86efac','Yes'],'maybe'=>['#fbbf24','Maybe'],'no'=>['#f87171','No']];
    [$c,$l] = $map[$r] ?? ['#94a3b8', ucfirst($r)];
    return "<span style=\"font-weight:700;color:{$c};font-size:0.8rem;\">{$l}</span>";
}
function showScore(?float $s): string {
    if ($s === null) return '<span style="color:#475569;">—</span>';
    $c = $s >= 80 ? '#4ade80' : ($s >= 60 ? '#fbbf24' : '#f87171');
    return "<span style=\"font-weight:700;color:{$c}\">" . number_format($s, 1) . "</span>";
}
$jobStatus = $job['status'] ?? 'draft';
$statusColors = ['active'=>'#4ade80','draft'=>'#94a3b8','paused'=>'#fbbf24','archived'=>'#f87171','closed'=>'#f87171'];
$statusColor = $statusColors[$jobStatus] ?? '#94a3b8';
?>
<style>
  .page-header { display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:16px; }
  .page-title { font-size:1.5rem;font-weight:800;color:#f1f5f9;line-height:1.2; }
  .page-meta { display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:8px; }
  .meta-chip { font-size:0.78rem;color:#64748b;display:flex;align-items:center;gap:4px; }
  .btn { display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;font-size:0.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all 0.15s; }
  .btn-primary { background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff; }
  .btn-primary:hover { opacity:0.9; }
  .btn-ghost { background:transparent;color:#94a3b8;border:1px solid rgba(79,70,229,0.25); }
  .btn-ghost:hover { background:rgba(79,70,229,0.1);color:#e2e8f0; }
  .btn-sm { padding:6px 12px;font-size:0.8rem; }
  .header-actions { display:flex;gap:10px;flex-wrap:wrap;align-items:center; }
  .stats-row { display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px; }
  .mini-stat { background:#1e1e32;border:1px solid rgba(79,70,229,0.12);border-radius:12px;padding:16px 18px;text-align:center; }
  .mini-stat-val { font-size:1.75rem;font-weight:800;color:#f1f5f9; }
  .mini-stat-label { font-size:0.75rem;color:#64748b;margin-top:3px;text-transform:uppercase;letter-spacing:0.05em; }
  .tab-bar { display:flex;gap:2px;margin-bottom:20px;border-bottom:1px solid rgba(79,70,229,0.12);padding-bottom:0;overflow-x:auto; }
  .tab-link { padding:10px 16px;font-size:0.85rem;font-weight:500;color:#64748b;text-decoration:none;border-bottom:2px solid transparent;white-space:nowrap;margin-bottom:-1px;transition:all 0.15s; }
  .tab-link:hover { color:#e2e8f0; }
  .tab-link.active { color:#818cf8;border-bottom-color:#4f46e5; }
  .card { background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:14px;overflow:hidden; }
  .card-body { padding:24px; }
  .data-table { width:100%;border-collapse:collapse; }
  .data-table th { padding:11px 16px;text-align:left;font-size:0.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;background:rgba(15,15,26,0.5);border-bottom:1px solid rgba(79,70,229,0.1); }
  .data-table td { padding:13px 16px;font-size:0.875rem;color:#cbd5e1;border-bottom:1px solid rgba(79,70,229,0.06);vertical-align:middle; }
  .data-table tr:last-child td { border-bottom:none; }
  .data-table tr:hover td { background:rgba(79,70,229,0.04); }
  .initials { width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:inline-flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;color:#fff; }
  .candidate-cell { display:flex;align-items:center;gap:10px; }
  .filter-bar-sm { display:flex;gap:10px;padding:14px 16px;border-bottom:1px solid rgba(79,70,229,0.08);align-items:center;flex-wrap:wrap; }
  .search-box-sm { display:flex;align-items:center;gap:8px;flex:1;min-width:180px;background:rgba(15,15,26,0.6);border:1px solid rgba(79,70,229,0.2);border-radius:8px;padding:0 10px; }
  .search-box-sm input { background:none;border:none;outline:none;color:#e2e8f0;font-size:0.85rem;padding:8px 0;width:100%; }
  .info-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px; }
  .info-row { display:flex;flex-direction:column;gap:4px; }
  .info-label { font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#475569; }
  .info-val { font-size:0.9rem;color:#e2e8f0; }
  .description-block { color:#94a3b8;font-size:0.9rem;line-height:1.7;white-space:pre-wrap; }
  .criteria-bar { margin-bottom:14px; }
  .criteria-header { display:flex;justify-content:space-between;margin-bottom:5px;font-size:0.82rem;color:#94a3b8; }
  .criteria-track { height:6px;background:rgba(79,70,229,0.15);border-radius:3px;overflow:hidden; }
  .criteria-fill { height:100%;background:linear-gradient(90deg,#4f46e5,#7c3aed);border-radius:3px; }
  .q-item { padding:12px 16px;border-bottom:1px solid rgba(79,70,229,0.06); }
  .q-item:last-child { border-bottom:none; }
  .q-text { color:#e2e8f0;font-size:0.875rem;margin-bottom:4px; }
  .q-meta { font-size:0.75rem;color:#475569; }
  .link-item { padding:14px 16px;border-bottom:1px solid rgba(79,70,229,0.06);display:flex;align-items:center;justify-content:space-between;gap:12px; }
  .link-item:last-child { border-bottom:none; }
  .link-url { font-family:monospace;font-size:0.8rem;color:#818cf8;word-break:break-all;flex:1; }
  .link-meta { font-size:0.75rem;color:#64748b;white-space:nowrap; }
  .empty-tab { text-align:center;padding:48px;color:#475569; }
  .breadcrumb { display:flex;align-items:center;gap:8px;font-size:0.82rem;color:#64748b;margin-bottom:16px; }
  .breadcrumb a { color:#64748b;text-decoration:none; }
  .breadcrumb a:hover { color:#818cf8; }
  .settings-form { display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px; }
  .setting-group { display:flex;flex-direction:column;gap:6px; }
  .setting-label { font-size:0.82rem;font-weight:600;color:#94a3b8; }
  .setting-input { padding:9px 12px;background:#0f0f1a;border:1px solid rgba(79,70,229,0.25);border-radius:8px;color:#e2e8f0;font-size:0.875rem;outline:none; }
  .setting-input:focus { border-color:#4f46e5;box-shadow:0 0 0 2px rgba(79,70,229,0.1); }
  .toggle-row { display:flex;align-items:center;gap:10px; }
  .toggle-row input { accent-color:#4f46e5; }
</style>

<div class="breadcrumb">
  <a href="/jobs">Jobs</a>
  <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
  <span style="color:#94a3b8;"><?= htmlspecialchars($job['title'] ?? 'Job') ?></span>
</div>

<div class="page-header">
  <div>
    <div class="page-title"><?= htmlspecialchars($job['title'] ?? '') ?></div>
    <div class="page-meta">
      <span style="display:inline-flex;align-items:center;gap:4px;font-size:0.78rem;font-weight:600;color:<?= $statusColor ?>;">
        <span style="width:6px;height:6px;border-radius:50%;background:<?= $statusColor ?>;display:inline-block;"></span>
        <?= ucfirst($jobStatus) ?>
      </span>
      <?php if ($job['dept_name'] ?? ''): ?>
        <span class="meta-chip">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/></svg>
          <?= htmlspecialchars($job['dept_name']) ?>
        </span>
      <?php endif; ?>
      <?php if ($job['seniority'] ?? ''): ?>
        <span class="meta-chip" style="text-transform:capitalize;"><?= htmlspecialchars($job['seniority']) ?></span>
      <?php endif; ?>
      <?php if ($job['location'] ?? ''): ?>
        <span class="meta-chip">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <?= htmlspecialchars($job['location']) ?>
          <?= $job['is_remote'] ? ' · Remote' : '' ?>
        </span>
      <?php endif; ?>
    </div>
  </div>
  <div class="header-actions">
    <button class="btn btn-ghost btn-sm" onclick="generateLink(<?= (int)$job['id'] ?>)">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
      Generate Link
    </button>
    <a href="/jobs/<?= (int)$job['id'] ?>/edit" class="btn btn-ghost btn-sm">Edit Job</a>
    <a href="/jobs/create" class="btn btn-primary btn-sm">+ New Job</a>
  </div>
</div>

<!-- Stats row -->
<div class="stats-row">
  <div class="mini-stat">
    <div class="mini-stat-val"><?= (int)($stats['total'] ?? 0) ?></div>
    <div class="mini-stat-label">Total Apps</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-val"><?= (int)($stats['ai_done'] ?? 0) ?></div>
    <div class="mini-stat-label">AI Done</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-val"><?= (int)($stats['qualified'] ?? 0) ?></div>
    <div class="mini-stat-label">Qualified</div>
  </div>
  <div class="mini-stat">
    <div class="mini-stat-val"><?= (int)($stats['hired'] ?? 0) ?></div>
    <div class="mini-stat-label">Hired</div>
  </div>
</div>

<!-- Tabs -->
<div class="tab-bar">
  <?php
  $tabs = ['overview'=>'Overview','applications'=>'Applications','criteria'=>'Criteria','questions'=>'Questions','settings'=>'Settings','links'=>'Links'];
  foreach ($tabs as $tKey => $tLabel):
    $href = '/jobs/' . (int)$job['id'] . '?tab=' . $tKey;
  ?>
    <a href="<?= $href ?>" class="tab-link <?= $tab === $tKey ? 'active' : '' ?>">
      <?= $tLabel ?>
      <?php if ($tKey === 'applications'): ?>
        <span style="font-size:0.72rem;background:rgba(79,70,229,0.2);color:#818cf8;padding:1px 6px;border-radius:10px;margin-left:4px;"><?= count($applications) ?></span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Tab: Overview -->
<?php if ($tab === 'overview'): ?>
<div class="card">
  <div class="card-body">
    <div class="info-grid" style="margin-bottom:24px;">
      <div class="info-row">
        <div class="info-label">Employment Type</div>
        <div class="info-val" style="text-transform:capitalize;"><?= htmlspecialchars(str_replace('_', ' ', $job['employment_type'] ?? '')) ?></div>
      </div>
      <div class="info-row">
        <div class="info-label">Avatar</div>
        <div class="info-val"><?= htmlspecialchars($job['avatar_name'] ?? 'Default') ?></div>
      </div>
      <?php if ($job['salary_min'] ?? null): ?>
      <div class="info-row">
        <div class="info-label">Salary Range</div>
        <div class="info-val"><?= htmlspecialchars($job['currency'] ?? 'USD') ?> <?= number_format((float)$job['salary_min']) ?>–<?= number_format((float)($job['salary_max'] ?? 0)) ?></div>
      </div>
      <?php endif; ?>
      <div class="info-row">
        <div class="info-label">Published</div>
        <div class="info-val"><?= $job['published_at'] ? date('M j, Y', strtotime($job['published_at'])) : 'Not published' ?></div>
      </div>
    </div>
    <?php if ($job['description'] ?? ''): ?>
      <div style="margin-bottom:20px;">
        <div class="info-label" style="margin-bottom:8px;">Description</div>
        <div class="description-block"><?= htmlspecialchars($job['description']) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($job['requirements'] ?? ''): ?>
      <div style="margin-bottom:20px;">
        <div class="info-label" style="margin-bottom:8px;">Requirements</div>
        <div class="description-block"><?= htmlspecialchars($job['requirements']) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($job['benefits'] ?? ''): ?>
      <div>
        <div class="info-label" style="margin-bottom:8px;">Benefits</div>
        <div class="description-block"><?= htmlspecialchars($job['benefits']) ?></div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Tab: Applications -->
<?php elseif ($tab === 'applications'): ?>
<div class="card">
  <div class="filter-bar-sm">
    <div class="search-box-sm">
      <svg width="13" height="13" fill="none" stroke="#64748b" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" id="appSearch" placeholder="Search candidates…" oninput="filterApps(this.value)">
    </div>
    <select id="statusFilter" onchange="filterApps()" style="background:#0f0f1a;border:1px solid rgba(79,70,229,0.2);border-radius:8px;color:#e2e8f0;padding:7px 10px;font-size:0.82rem;outline:none;">
      <option value="">All Statuses</option>
      <?php foreach (['applied','ai_screening','qualified','disqualified','tech_interview','manager_interview','final_review','offer','hired','rejected','withdrawn'] as $st): ?>
        <option value="<?= $st ?>"><?= ucwords(str_replace('_', ' ', $st)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (empty($applications)): ?>
    <div class="empty-tab">No applications yet for this job.</div>
  <?php else: ?>
    <table class="data-table" id="appsTable">
      <thead>
        <tr>
          <th>Candidate</th>
          <th>Status</th>
          <th>AI Score</th>
          <th>Recommendation</th>
          <th>Applied</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($applications as $app): ?>
          <?php
            $initials = strtoupper(substr($app['first_name'] ?? 'U', 0, 1) . substr($app['last_name'] ?? '', 0, 1));
          ?>
          <tr data-name="<?= htmlspecialchars(strtolower(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''))) ?>"
              data-status="<?= htmlspecialchars($app['status'] ?? '') ?>">
            <td>
              <div class="candidate-cell">
                <div class="initials"><?= $initials ?></div>
                <div>
                  <div style="font-weight:600;color:#e2e8f0;font-size:0.875rem;"><?= htmlspecialchars(trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''))) ?></div>
                  <div style="font-size:0.75rem;color:#475569;"><?= htmlspecialchars($app['email'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td><?= showStatusBadge($app['status'] ?? 'applied') ?></td>
            <td><?= showScore(isset($app['final_score']) ? (float)$app['final_score'] : null) ?></td>
            <td><?= showRecBadge($app['recommendation'] ?? null) ?></td>
            <td style="color:#64748b;white-space:nowrap;"><?= $app['applied_at'] ? date('M j, Y', strtotime($app['applied_at'])) : '—' ?></td>
            <td>
              <a href="/candidates/<?= (int)$app['id'] ?>" class="btn btn-ghost btn-sm">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Tab: Criteria -->
<?php elseif ($tab === 'criteria'): ?>
<div class="card">
  <div class="card-body">
    <?php if (empty($criteria)): ?>
      <div class="empty-tab">No criteria configured. <a href="#" style="color:#818cf8;">Add criteria</a> to score candidates.</div>
    <?php else: ?>
      <?php foreach ($criteria as $c): ?>
        <div class="criteria-bar">
          <div class="criteria-header">
            <span style="font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($c['name']) ?></span>
            <span>Weight: <?= htmlspecialchars((string)$c['weight']) ?> &middot; Pass: <?= htmlspecialchars((string)$c['pass_score']) ?>/<?= htmlspecialchars((string)$c['max_score']) ?></span>
          </div>
          <?php if ($c['description'] ?? ''): ?>
            <div style="font-size:0.78rem;color:#475569;margin-bottom:6px;"><?= htmlspecialchars($c['description']) ?></div>
          <?php endif; ?>
          <div class="criteria-track">
            <div class="criteria-fill" style="width:<?= min(100, ((float)$c['weight'] / 5) * 100) ?>%;"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Tab: Questions -->
<?php elseif ($tab === 'questions'): ?>
<div class="card">
  <?php if (empty($questions)): ?>
    <div class="empty-tab">No questions added yet.</div>
  <?php else: ?>
    <?php foreach ($questions as $q): ?>
      <div class="q-item">
        <div class="q-text"><?= htmlspecialchars($q['question']) ?></div>
        <div class="q-meta">
          <?php if ($q['category'] ?? ''): ?><?= htmlspecialchars($q['category']) ?> &middot;<?php endif; ?>
          <?= ucfirst($q['difficulty'] ?? 'medium') ?> &middot;
          <?= strtoupper($q['language'] ?? 'en') ?>
          <?= !($q['is_active'] ?? true) ? ' &middot; <span style="color:#f87171;">Inactive</span>' : '' ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Tab: Settings -->
<?php elseif ($tab === 'settings'): ?>
<div class="card">
  <div class="card-body">
    <form method="POST" action="/jobs/<?= (int)$job['id'] ?>/settings">
      <div class="settings-form">
        <div class="setting-group">
          <label class="setting-label">Interview Mode</label>
          <select name="interview_mode" class="setting-input">
            <?php foreach (['text'=>'Text','voice'=>'Voice','video'=>'Video'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($settings['interview_mode'] ?? 'text') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="setting-group">
          <label class="setting-label">Interview Language</label>
          <select name="interview_language" class="setting-input">
            <?php foreach (['auto'=>'Auto','en'=>'English','ar'=>'Arabic'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($settings['interview_language'] ?? 'auto') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="setting-group">
          <label class="setting-label">Max Questions</label>
          <input type="number" name="max_questions" class="setting-input" value="<?= (int)($settings['max_questions'] ?? 12) ?>" min="1" max="30">
        </div>
        <div class="setting-group">
          <label class="setting-label">Time Limit (minutes)</label>
          <input type="number" name="time_limit_minutes" class="setting-input" value="<?= (int)($settings['time_limit_minutes'] ?? 20) ?>" min="5">
        </div>
        <div class="setting-group">
          <label class="setting-label">Passing Score (%)</label>
          <input type="number" name="passing_score" class="setting-input" value="<?= (float)($settings['passing_score'] ?? 68) ?>" min="0" max="100" step="0.5">
        </div>
        <div class="setting-group">
          <label class="setting-label">Auto-Qualify Score (%)</label>
          <input type="number" name="auto_qualify_score" class="setting-input" value="<?= (float)($settings['auto_qualify_score'] ?? 82) ?>" min="0" max="100" step="0.5">
        </div>
        <div class="setting-group">
          <label class="setting-label">Auto-Disqualify Score (%)</label>
          <input type="number" name="auto_disqualify_score" class="setting-input" value="<?= (float)($settings['auto_disqualify_score'] ?? 50) ?>" min="0" max="100" step="0.5">
        </div>
        <div class="setting-group">
          <label class="setting-label">Link Expiry (days)</label>
          <input type="number" name="link_expiry_days" class="setting-input" value="<?= (int)($settings['link_expiry_days'] ?? 14) ?>" min="1">
        </div>
        <div class="setting-group" style="grid-column:1/-1;">
          <label class="setting-label">CV Screening</label>
          <div class="toggle-row">
            <input type="checkbox" name="cv_screening_enabled" id="cv_screening" value="1" <?= ($settings['cv_screening_enabled'] ?? 1) ? 'checked' : '' ?>>
            <label for="cv_screening" style="font-size:0.875rem;color:#94a3b8;cursor:pointer;">Enable AI CV screening before interview</label>
          </div>
        </div>
      </div>
      <div style="margin-top:24px;">
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </div>
    </form>
  </div>
</div>

<!-- Tab: Links -->
<?php elseif ($tab === 'links'): ?>
<div class="card">
  <div style="padding:14px 16px;border-bottom:1px solid rgba(79,70,229,0.08);display:flex;justify-content:space-between;align-items:center;">
    <span style="font-size:0.85rem;color:#64748b;"><?= count($links) ?> link(s) generated</span>
    <button class="btn btn-primary btn-sm" onclick="generateLink(<?= (int)$job['id'] ?>)">+ Generate Link</button>
  </div>
  <?php if (empty($links)): ?>
    <div class="empty-tab">No interview links generated yet.</div>
  <?php else: ?>
    <?php
      $appUrl = $_ENV['APP_URL'] ?? '';
      foreach ($links as $link):
        $url = rtrim($appUrl, '/') . '/interview/' . $link['token'];
        $expired = strtotime($link['expires_at']) < time();
    ?>
      <div class="link-item">
        <div style="flex:1;">
          <div class="link-url"><?= htmlspecialchars($url) ?></div>
          <div class="link-meta">
            By <?= htmlspecialchars(($link['first_name'] ?? '') . ' ' . ($link['last_name'] ?? '')) ?>
            &middot; Expires <?= date('M j, Y', strtotime($link['expires_at'])) ?>
            &middot; <?= $link['used_at'] ? '<span style="color:#4ade80;">Used</span>' : ($expired ? '<span style="color:#f87171;">Expired</span>' : '<span style="color:#fbbf24;">Active</span>') ?>
          </div>
        </div>
        <?php if (!$expired && !$link['used_at']): ?>
          <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($url, ENT_JS) ?>').then(()=>alert('Copied!'))" class="btn btn-ghost btn-sm">Copy</button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div id="linkModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#1e1e32;border:1px solid rgba(79,70,229,0.3);border-radius:16px;padding:32px;max-width:500px;width:90%;position:relative;">
    <button onclick="document.getElementById('linkModal').style.display='none'" style="position:absolute;top:12px;right:12px;background:none;border:none;color:#64748b;cursor:pointer;font-size:1.4rem;">&times;</button>
    <h3 style="color:#e2e8f0;margin-bottom:8px;">Interview Link Generated</h3>
    <p style="color:#64748b;font-size:0.85rem;margin-bottom:16px;">Share with the candidate. Expires in <?= (int)($settings['link_expiry_days'] ?? 14) ?> days.</p>
    <div style="display:flex;gap:8px;">
      <input id="linkOutput" readonly style="flex:1;background:#0f0f1a;border:1px solid rgba(79,70,229,0.3);border-radius:8px;padding:10px 12px;color:#e2e8f0;font-size:0.85rem;outline:none;" value="">
      <button onclick="document.getElementById('linkOutput').select();document.execCommand('copy');" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;padding:10px 16px;cursor:pointer;font-weight:600;font-size:0.875rem;">Copy</button>
    </div>
  </div>
</div>

<script>
function generateLink(jobId) {
  fetch('/jobs/' + jobId + '/generate-link', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(r=>r.json()).then(d=>{
      if (d.data && d.data.link) {
        document.getElementById('linkOutput').value = d.data.link;
        document.getElementById('linkModal').style.display = 'flex';
      } else { alert(d.message || 'Error'); }
    });
}
function filterApps(val) {
  const search = (document.getElementById('appSearch')?.value || '').toLowerCase();
  const status = document.getElementById('statusFilter')?.value || '';
  document.querySelectorAll('#appsTable tbody tr').forEach(row => {
    const name = row.dataset.name || '';
    const st = row.dataset.status || '';
    const show = name.includes(search) && (!status || st === status);
    row.style.display = show ? '' : 'none';
  });
}
</script>
