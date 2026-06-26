<?php
// views/hr/jobs/index.php
// Variables: $jobs (array), $stats (array), $status (string), $search (string)
$jobs   = $jobs ?? [];
$stats  = $stats ?? [];
$status = $status ?? 'all';
$search = $search ?? '';

function jobStatusBadge(string $s): string {
    $map = [
        'active'   => ['#22c55e20','#4ade80','#22c55e40', 'Active'],
        'draft'    => ['#64748b20','#94a3b8','#64748b40', 'Draft'],
        'paused'   => ['#f59e0b20','#fbbf24','#f59e0b40', 'Paused'],
        'archived' => ['#ef444420','#f87171','#ef444440', 'Archived'],
        'closed'   => ['#ef444420','#f87171','#ef444440', 'Closed'],
    ];
    [$bg,$fg,$bd,$label] = $map[$s] ?? ['#64748b20','#94a3b8','#64748b40', ucfirst($s)];
    return "<span style=\"display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;background:{$bg};color:{$fg};border:1px solid {$bd}\">{$label}</span>";
}
?>
<style>
  .page-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px; }
  .page-title { font-size:1.6rem;font-weight:800;color:#f1f5f9; }
  .page-subtitle { color:#64748b;font-size:0.875rem;margin-top:2px; }
  .btn { display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:8px;font-size:0.875rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all 0.15s; }
  .btn-primary { background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff; }
  .btn-primary:hover { opacity:0.9;transform:translateY(-1px); }
  .btn-ghost { background:transparent;color:#94a3b8;border:1px solid rgba(79,70,229,0.25); }
  .btn-ghost:hover { background:rgba(79,70,229,0.1);color:#e2e8f0; }
  .btn-sm { padding:6px 12px;font-size:0.8rem; }
  .filter-bar {
    background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:12px;
    padding:14px 18px;margin-bottom:20px;
    display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  }
  .filter-tabs { display:flex;gap:4px;background:rgba(15,15,26,0.5);padding:4px;border-radius:8px; }
  .filter-tab { padding:6px 14px;border-radius:6px;font-size:0.82rem;font-weight:500;color:#64748b;cursor:pointer;text-decoration:none;transition:all 0.15s;white-space:nowrap; }
  .filter-tab.active { background:#4f46e5;color:#fff; }
  .filter-tab:not(.active):hover { color:#e2e8f0; }
  .search-box { display:flex;align-items:center;gap:8px;flex:1;min-width:200px;
    background:rgba(15,15,26,0.6);border:1px solid rgba(79,70,229,0.2);border-radius:8px;padding:0 12px; }
  .search-box svg { color:#475569;flex-shrink:0; }
  .search-box input { background:none;border:none;outline:none;color:#e2e8f0;font-size:0.875rem;width:100%;padding:8px 0; }
  .search-box input::placeholder { color:#475569; }
  .filter-count { font-size:0.78rem;color:#64748b;white-space:nowrap; }
  .stats-mini { display:flex;gap:16px;margin-left:auto;flex-wrap:wrap; }
  .stat-pill { font-size:0.78rem;color:#64748b;display:flex;align-items:center;gap:5px; }
  .stat-pill strong { color:#e2e8f0;font-weight:700; }
  .card { background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:14px;overflow:hidden; }
  .data-table { width:100%;border-collapse:collapse; }
  .data-table th { padding:11px 16px;text-align:left;font-size:0.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;background:rgba(15,15,26,0.5);border-bottom:1px solid rgba(79,70,229,0.1); }
  .data-table td { padding:14px 16px;font-size:0.875rem;color:#cbd5e1;border-bottom:1px solid rgba(79,70,229,0.06);vertical-align:middle; }
  .data-table tr:last-child td { border-bottom:none; }
  .data-table tr:hover td { background:rgba(79,70,229,0.04); }
  .job-title-cell a { color:#e2e8f0;font-weight:600;text-decoration:none;font-size:0.9rem; }
  .job-title-cell a:hover { color:#818cf8; }
  .job-meta { font-size:0.75rem;color:#475569;margin-top:3px; }
  .actions-cell { display:flex;gap:6px;align-items:center; }
  .icon-btn { width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:6px;background:rgba(79,70,229,0.08);border:1px solid rgba(79,70,229,0.15);cursor:pointer;transition:all 0.15s;text-decoration:none;color:#94a3b8; }
  .icon-btn:hover { background:rgba(79,70,229,0.2);color:#e2e8f0; }
  .icon-btn.danger:hover { background:rgba(239,68,68,0.15);color:#f87171;border-color:rgba(239,68,68,0.3); }
  .app-count { font-weight:700;color:#818cf8; }
  .empty-state { text-align:center;padding:64px 24px;color:#475569; }
  .empty-state svg { margin:0 auto 16px;display:block;opacity:0.4; }
  .empty-state h3 { color:#94a3b8;font-size:1rem;margin-bottom:8px; }
  .empty-state p { font-size:0.875rem; }
</style>

<div class="page-header">
  <div>
    <div class="page-title">Jobs</div>
    <div class="page-subtitle">Manage your open positions and hiring pipelines</div>
  </div>
  <a href="/jobs/create" class="btn btn-primary">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
    New Job
  </a>
</div>

<div class="filter-bar">
  <div class="filter-tabs">
    <?php
      $tabs = ['all' => 'All', 'active' => 'Active', 'draft' => 'Draft', 'paused' => 'Paused', 'archived' => 'Archived'];
      foreach ($tabs as $val => $label):
        $isActive = $status === $val;
        $qs = http_build_query(['status' => $val, 'q' => $search]);
    ?>
      <a href="/jobs?<?= $qs ?>" class="filter-tab <?= $isActive ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <form method="GET" action="/jobs" style="flex:1;min-width:180px;">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <div class="search-box">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search jobs, departments…" autocomplete="off">
    </div>
  </form>
  <div class="stats-mini">
    <div class="stat-pill"><strong><?= (int)($stats['active'] ?? 0) ?></strong> Active</div>
    <div class="stat-pill"><strong><?= (int)($stats['draft'] ?? 0) ?></strong> Draft</div>
    <div class="stat-pill"><strong><?= (int)($stats['archived'] ?? 0) ?></strong> Archived</div>
  </div>
</div>

<div class="card">
  <?php if (empty($jobs)): ?>
    <div class="empty-state">
      <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
      <h3>No jobs found</h3>
      <p><?= $search ? 'Try adjusting your search or filters.' : 'Create your first job posting to get started.' ?></p>
      <?php if (!$search): ?>
        <a href="/jobs/create" class="btn btn-primary" style="margin-top:16px;">+ New Job</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Job Title</th>
          <th>Department</th>
          <th>Seniority</th>
          <th>Applications</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($jobs as $job): ?>
          <tr>
            <td>
              <div class="job-title-cell">
                <a href="/jobs/<?= (int)$job['id'] ?>"><?= htmlspecialchars($job['title']) ?></a>
                <div class="job-meta">
                  <?= $job['location'] ? htmlspecialchars($job['location']) : '' ?>
                  <?= $job['is_remote'] ? '<span style="color:#4ade80;margin-left:4px;">Remote</span>' : '' ?>
                </div>
              </div>
            </td>
            <td style="color:#94a3b8;"><?= htmlspecialchars($job['dept_name'] ?? '—') ?></td>
            <td style="color:#94a3b8;text-transform:capitalize;"><?= htmlspecialchars($job['seniority'] ?? '—') ?></td>
            <td><span class="app-count"><?= (int)($job['app_count'] ?? 0) ?></span></td>
            <td><?= jobStatusBadge($job['status'] ?? 'draft') ?></td>
            <td style="color:#64748b;white-space:nowrap;"><?= date('M j, Y', strtotime($job['created_at'])) ?></td>
            <td>
              <div class="actions-cell">
                <a href="/jobs/<?= (int)$job['id'] ?>" class="icon-btn" title="View">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </a>
                <a href="/jobs/<?= (int)$job['id'] ?>?tab=settings" class="icon-btn" title="Settings">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </a>
                <button class="icon-btn" title="Generate Interview Link" onclick="generateLink(<?= (int)$job['id'] ?>)">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </button>
                <?php if ($job['status'] !== 'archived'): ?>
                  <form method="POST" action="/jobs/<?= (int)$job['id'] ?>/archive" style="display:inline;" onsubmit="return confirm('Archive this job?')">
                    <button type="submit" class="icon-btn danger" title="Archive">
                      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Link modal -->
<div id="linkModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#1e1e32;border:1px solid rgba(79,70,229,0.3);border-radius:16px;padding:32px;max-width:500px;width:90%;position:relative;">
    <button onclick="document.getElementById('linkModal').style.display='none'" style="position:absolute;top:12px;right:12px;background:none;border:none;color:#64748b;cursor:pointer;font-size:1.3rem;">&times;</button>
    <h3 style="color:#e2e8f0;margin-bottom:8px;">Interview Link Generated</h3>
    <p style="color:#64748b;font-size:0.85rem;margin-bottom:16px;">Share this link with the candidate. It expires per your job settings.</p>
    <div style="display:flex;gap:8px;">
      <input id="linkOutput" readonly style="flex:1;background:#0f0f1a;border:1px solid rgba(79,70,229,0.3);border-radius:8px;padding:10px 12px;color:#e2e8f0;font-size:0.875rem;outline:none;" value="">
      <button onclick="copyLink()" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;padding:10px 16px;cursor:pointer;font-weight:600;font-size:0.875rem;">Copy</button>
    </div>
  </div>
</div>

<script>
function generateLink(jobId) {
  fetch('/jobs/' + jobId + '/generate-link', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/json'}})
    .then(r=>r.json())
    .then(d=>{
      if (d.data && d.data.link) {
        document.getElementById('linkOutput').value = d.data.link;
        document.getElementById('linkModal').style.display = 'flex';
      } else { alert(d.message || 'Error generating link'); }
    })
    .catch(()=>alert('Failed to generate link'));
}
function copyLink() {
  const el = document.getElementById('linkOutput');
  el.select(); document.execCommand('copy');
  alert('Link copied!');
}
</script>
