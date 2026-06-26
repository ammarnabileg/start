<?php
// views/hr/candidates/index.php
// Variables: $candidates (array), $jobs (array for filter), $filters (array), $total (int), $page (int)
$candidates = $candidates ?? [];
$jobs       = $jobs ?? [];
$filters    = $filters ?? [];
$total      = $total ?? count($candidates);
$page       = $page ?? 1;
$viewMode   = $_GET['view'] ?? 'table';
$selectedIds = [];

function candStatusBadge(string $s): string {
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
    [$bg,$fg,$bd,$label] = $map[$s] ?? ['#64748b20','#94a3b8','#64748b40', ucfirst($s)];
    return "<span style=\"display:inline-block;padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;background:{$bg};color:{$fg};border:1px solid {$bd}\">{$label}</span>";
}
function candScore(?float $s): string {
    if ($s === null) return '<span style="color:#475569;font-size:0.8rem;">—</span>';
    $c = $s >= 80 ? '#4ade80' : ($s >= 60 ? '#fbbf24' : '#f87171');
    return "<span style=\"font-weight:700;color:{$c}\">" . number_format($s, 1) . "</span>";
}
function candRec(?string $r): string {
    if (!$r) return '';
    $map = ['strong_yes'=>['#4ade80','Strong Yes'],'yes'=>['#86efac','Yes'],'maybe'=>['#fbbf24','Maybe'],'no'=>['#f87171','No']];
    [$c,$l] = $map[$r] ?? ['#94a3b8', ucfirst((string)$r)];
    return "<span style=\"font-size:0.78rem;font-weight:600;color:{$c}\">{$l}</span>";
}
?>
<style>
  .page-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px; }
  .page-title { font-size:1.5rem;font-weight:800;color:#f1f5f9; }
  .page-subtitle { color:#64748b;font-size:0.875rem;margin-top:2px; }
  .btn { display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all 0.15s; }
  .btn-primary { background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff; }
  .btn-primary:hover { opacity:0.9; }
  .btn-ghost { background:transparent;color:#94a3b8;border:1px solid rgba(79,70,229,0.25); }
  .btn-ghost:hover { background:rgba(79,70,229,0.1);color:#e2e8f0; }
  .btn-sm { padding:6px 12px;font-size:0.78rem; }
  .filter-panel { background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:14px;padding:16px 20px;margin-bottom:16px; }
  .filter-row { display:flex;gap:12px;align-items:center;flex-wrap:wrap; }
  .search-box { display:flex;align-items:center;gap:8px;flex:1;min-width:200px;background:rgba(15,15,26,0.7);border:1px solid rgba(79,70,229,0.2);border-radius:8px;padding:0 12px; }
  .search-box input { background:none;border:none;outline:none;color:#e2e8f0;font-size:0.875rem;padding:9px 0;width:100%; }
  .search-box input::placeholder { color:#475569; }
  .filter-select { background:#0f0f1a;border:1px solid rgba(79,70,229,0.2);border-radius:8px;color:#e2e8f0;padding:9px 12px;font-size:0.85rem;outline:none;cursor:pointer; }
  .filter-select option { background:#1e1e32; }
  .adv-toggle { color:#818cf8;font-size:0.8rem;cursor:pointer;display:inline-flex;align-items:center;gap:4px;background:none;border:none; }
  .adv-filters { display:none;margin-top:14px;padding-top:14px;border-top:1px solid rgba(79,70,229,0.1);display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px; }
  .adv-label { font-size:0.75rem;font-weight:600;color:#475569;margin-bottom:4px;text-transform:uppercase;letter-spacing:0.04em; }
  .score-range { display:flex;align-items:center;gap:8px; }
  .score-range input[type=number] { background:#0f0f1a;border:1px solid rgba(79,70,229,0.2);border-radius:6px;color:#e2e8f0;padding:7px 10px;font-size:0.85rem;outline:none;width:70px; }
  .view-toggle { display:flex;gap:4px;background:rgba(15,15,26,0.5);padding:4px;border-radius:8px; }
  .view-btn { width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;cursor:pointer;color:#64748b;border:none;background:none; }
  .view-btn.active { background:#4f46e5;color:#fff; }
  .toolbar { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px; }
  .bulk-bar { display:none;background:#4f46e5;border-radius:10px;padding:10px 16px;align-items:center;gap:12px;margin-bottom:12px; }
  .bulk-bar.visible { display:flex; }
  .bulk-count { color:#fff;font-size:0.875rem;font-weight:600; }
  .bulk-btn { background:rgba(255,255,255,0.15);border:none;color:#fff;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.8rem;font-weight:600; }
  .bulk-btn:hover { background:rgba(255,255,255,0.25); }
  /* TABLE VIEW */
  .card { background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:14px;overflow:hidden; }
  .data-table { width:100%;border-collapse:collapse; }
  .data-table th { padding:11px 16px;text-align:left;font-size:0.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;background:rgba(15,15,26,0.5);border-bottom:1px solid rgba(79,70,229,0.1); }
  .data-table td { padding:13px 16px;font-size:0.875rem;color:#cbd5e1;border-bottom:1px solid rgba(79,70,229,0.06);vertical-align:middle; }
  .data-table tr:last-child td { border-bottom:none; }
  .data-table tr:hover td { background:rgba(79,70,229,0.04); }
  .initials { width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:inline-flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:#fff;flex-shrink:0; }
  .cand-cell { display:flex;align-items:center;gap:10px; }
  .cand-name { font-weight:600;color:#e2e8f0;font-size:0.875rem; }
  .cand-email { font-size:0.75rem;color:#475569;margin-top:1px; }
  /* CARD VIEW */
  .cards-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px; }
  .cand-card {
    background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:14px;padding:20px;
    transition:border-color 0.2s,box-shadow 0.2s;position:relative;
  }
  .cand-card:hover { border-color:rgba(79,70,229,0.4);box-shadow:0 4px 20px rgba(79,70,229,0.08); }
  .cand-card-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:14px; }
  .cand-card-info { flex:1; }
  .cand-card-name { font-weight:700;color:#e2e8f0;font-size:0.95rem; }
  .cand-card-email { font-size:0.75rem;color:#475569;margin-top:2px; }
  .cand-card-job { font-size:0.8rem;color:#64748b;margin-top:6px; }
  .score-circle { width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.85rem;flex-shrink:0; }
  .cand-card-footer { display:flex;align-items:center;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid rgba(79,70,229,0.08); }
  .cand-card-check { position:absolute;top:14px;left:14px; }
  .empty-state { text-align:center;padding:64px;color:#475569; }
  .empty-state svg { margin:0 auto 16px;display:block;opacity:0.4; }
  .empty-state h3 { color:#94a3b8;font-size:1rem;margin-bottom:6px; }
  .pagination { display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px; }
  .page-btn { width:34px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#1e1e32;border:1px solid rgba(79,70,229,0.2);color:#94a3b8;cursor:pointer;font-size:0.85rem;text-decoration:none; }
  .page-btn.active { background:#4f46e5;border-color:#4f46e5;color:#fff; }
  .page-btn:hover:not(.active) { background:rgba(79,70,229,0.1);color:#e2e8f0; }
</style>

<div class="page-header">
  <div>
    <div class="page-title">Candidates</div>
    <div class="page-subtitle"><?= $total ?> candidate<?= $total !== 1 ? 's' : '' ?> found</div>
  </div>
  <div style="display:flex;gap:10px;align-items:center;">
    <a href="/comparisons" class="btn btn-ghost">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
      Compare
    </a>
    <a href="/candidates?export=1" class="btn btn-ghost">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
      Export
    </a>
  </div>
</div>

<!-- Filters -->
<form method="GET" action="/candidates" id="filterForm">
<div class="filter-panel">
  <div class="filter-row">
    <div class="search-box">
      <svg width="14" height="14" fill="none" stroke="#64748b" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Search by name, email, skills…">
    </div>
    <select name="job_id" class="filter-select" onchange="document.getElementById('filterForm').submit()">
      <option value="">All Jobs</option>
      <?php foreach ($jobs as $j): ?>
        <option value="<?= (int)$j['id'] ?>" <?= ($filters['job_id'] ?? '') == $j['id'] ? 'selected' : '' ?>><?= htmlspecialchars($j['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="filter-select" onchange="document.getElementById('filterForm').submit()">
      <option value="">All Statuses</option>
      <?php foreach (['applied','ai_screening','qualified','disqualified','tech_interview','manager_interview','final_review','offer','hired','rejected','withdrawn'] as $st): ?>
        <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $st)) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Search</button>
    <button type="button" class="adv-toggle" onclick="toggleAdv()">
      <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
      Advanced
    </button>
  </div>
  <div class="adv-filters" id="advFilters" style="display:<?= (!empty($filters['score_min']) || !empty($filters['score_max']) || !empty($filters['recommendation']) || !empty($filters['skills'])) ? 'grid' : 'none' ?>;">
    <div>
      <div class="adv-label">Score Range</div>
      <div class="score-range">
        <input type="number" name="score_min" value="<?= htmlspecialchars($filters['score_min'] ?? '') ?>" placeholder="Min" min="0" max="100">
        <span style="color:#475569;">—</span>
        <input type="number" name="score_max" value="<?= htmlspecialchars($filters['score_max'] ?? '') ?>" placeholder="Max" min="0" max="100">
      </div>
    </div>
    <div>
      <div class="adv-label">Recommendation</div>
      <select name="recommendation" class="filter-select" style="width:100%;">
        <option value="">Any</option>
        <option value="strong_yes" <?= ($filters['recommendation'] ?? '') === 'strong_yes' ? 'selected' : '' ?>>Strong Yes</option>
        <option value="yes" <?= ($filters['recommendation'] ?? '') === 'yes' ? 'selected' : '' ?>>Yes</option>
        <option value="maybe" <?= ($filters['recommendation'] ?? '') === 'maybe' ? 'selected' : '' ?>>Maybe</option>
        <option value="no" <?= ($filters['recommendation'] ?? '') === 'no' ? 'selected' : '' ?>>No</option>
      </select>
    </div>
    <div>
      <div class="adv-label">Skills (comma-separated)</div>
      <input type="text" name="skills" value="<?= htmlspecialchars($filters['skills'] ?? '') ?>" placeholder="e.g. Python, React" style="background:#0f0f1a;border:1px solid rgba(79,70,229,0.2);border-radius:8px;color:#e2e8f0;padding:9px 12px;font-size:0.85rem;outline:none;width:100%;">
    </div>
    <div style="display:flex;align-items:flex-end;">
      <a href="/candidates" class="btn btn-ghost" style="width:100%;justify-content:center;">Clear Filters</a>
    </div>
  </div>
</div>
</form>

<!-- Toolbar -->
<div class="toolbar">
  <div id="bulkBar" class="bulk-bar">
    <span class="bulk-count"><span id="bulkCount">0</span> selected</span>
    <button class="bulk-btn" onclick="bulkAction('pipeline')">Move Stage</button>
    <button class="bulk-btn" onclick="bulkAction('export')">Export</button>
    <button class="bulk-btn" onclick="bulkAction('compare')">Compare</button>
    <button class="bulk-btn" style="background:rgba(239,68,68,0.3);" onclick="bulkAction('reject')">Reject</button>
    <button class="bulk-btn" onclick="clearSelection()">&times; Clear</button>
  </div>
  <div style="display:flex;align-items:center;gap:10px;margin-left:auto;">
    <span style="font-size:0.82rem;color:#64748b;"><?= count($candidates) ?> result<?= count($candidates) !== 1 ? 's' : '' ?></span>
    <div class="view-toggle">
      <a href="?<?= http_build_query(array_merge($filters, ['view' => 'table'])) ?>" class="view-btn <?= $viewMode === 'table' ? 'active' : '' ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </a>
      <a href="?<?= http_build_query(array_merge($filters, ['view' => 'cards'])) ?>" class="view-btn <?= $viewMode === 'cards' ? 'active' : '' ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      </a>
    </div>
  </div>
</div>

<?php if (empty($candidates)): ?>
  <div class="empty-state">
    <svg width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <h3>No candidates found</h3>
    <p>Try adjusting your search filters or generate interview links to invite candidates.</p>
  </div>
<?php elseif ($viewMode === 'cards'): ?>
  <div class="cards-grid" id="candidateList">
    <?php foreach ($candidates as $c):
      $initials = strtoupper(substr($c['first_name'] ?? 'U', 0, 1) . substr($c['last_name'] ?? '', 0, 1));
      $score = isset($c['final_score']) ? (float)$c['final_score'] : null;
      $scoreColor = $score === null ? '#475569' : ($score >= 80 ? '#4ade80' : ($score >= 60 ? '#fbbf24' : '#f87171'));
      $scoreBg = $score === null ? 'rgba(71,85,105,0.15)' : ($score >= 80 ? 'rgba(34,197,94,0.15)' : ($score >= 60 ? 'rgba(245,158,11,0.15)' : 'rgba(239,68,68,0.15)'));
    ?>
      <div class="cand-card" id="card-<?= (int)$c['application_id'] ?? (int)$c['id'] ?>">
        <input type="checkbox" class="cand-check cand-card-check" value="<?= (int)($c['application_id'] ?? $c['id']) ?>" onchange="updateBulk(this)">
        <div class="cand-card-header">
          <div class="initials" style="width:42px;height:42px;font-size:0.85rem;"><?= $initials ?></div>
          <div class="cand-card-info">
            <div class="cand-card-name">
              <a href="/candidates/<?= (int)($c['application_id'] ?? $c['id']) ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></a>
            </div>
            <div class="cand-card-email"><?= htmlspecialchars($c['email'] ?? '') ?></div>
            <?php if ($c['job_title'] ?? ''): ?>
              <div class="cand-card-job"><?= htmlspecialchars($c['job_title']) ?></div>
            <?php endif; ?>
          </div>
          <div class="score-circle" style="background:<?= $scoreBg ?>;color:<?= $scoreColor ?>;">
            <?= $score !== null ? number_format($score, 0) : '—' ?>
          </div>
        </div>
        <div class="cand-card-footer">
          <?= candStatusBadge($c['status'] ?? 'applied') ?>
          <?= candRec($c['recommendation'] ?? null) ?>
          <a href="/candidates/<?= (int)($c['application_id'] ?? $c['id']) ?>" class="btn btn-ghost btn-sm">View</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card">
    <table class="data-table" id="candidateList">
      <thead>
        <tr>
          <th style="width:36px;"><input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="accent-color:#4f46e5;"></th>
          <th>Candidate</th>
          <th>Job</th>
          <th>Status</th>
          <th>AI Score</th>
          <th>Recommendation</th>
          <th>Applied</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($candidates as $c):
          $initials = strtoupper(substr($c['first_name'] ?? 'U', 0, 1) . substr($c['last_name'] ?? '', 0, 1));
          $appId = (int)($c['application_id'] ?? $c['id']);
        ?>
          <tr>
            <td><input type="checkbox" class="cand-check" value="<?= $appId ?>" onchange="updateBulk(this)" style="accent-color:#4f46e5;"></td>
            <td>
              <div class="cand-cell">
                <div class="initials"><?= $initials ?></div>
                <div>
                  <div class="cand-name">
                    <a href="/candidates/<?= $appId ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?></a>
                  </div>
                  <div class="cand-email"><?= htmlspecialchars($c['email'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td style="color:#94a3b8;font-size:0.82rem;"><?= htmlspecialchars($c['job_title'] ?? '—') ?></td>
            <td><?= candStatusBadge($c['status'] ?? 'applied') ?></td>
            <td><?= candScore(isset($c['final_score']) ? (float)$c['final_score'] : null) ?></td>
            <td><?= candRec($c['recommendation'] ?? null) ?></td>
            <td style="color:#64748b;white-space:nowrap;"><?= $c['applied_at'] ? date('M j, Y', strtotime($c['applied_at'])) : '—' ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <a href="/candidates/<?= $appId ?>" class="btn btn-ghost btn-sm">View</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<script>
let selectedIds = new Set();
function updateBulk(cb) {
  if (cb.checked) selectedIds.add(cb.value); else selectedIds.delete(cb.value);
  const bar = document.getElementById('bulkBar');
  const cnt = document.getElementById('bulkCount');
  if (selectedIds.size > 0) { bar.classList.add('visible'); cnt.textContent = selectedIds.size; }
  else bar.classList.remove('visible');
}
function toggleAll(cb) {
  document.querySelectorAll('.cand-check').forEach(c => { c.checked = cb.checked; updateBulk(c); });
}
function clearSelection() {
  document.querySelectorAll('.cand-check').forEach(c => { c.checked = false; });
  selectedIds.clear();
  document.getElementById('bulkBar').classList.remove('visible');
  const sa = document.getElementById('selectAll');
  if (sa) sa.checked = false;
}
function bulkAction(action) {
  const ids = Array.from(selectedIds).join(',');
  if (!ids) return;
  if (action === 'compare') { window.location = '/comparisons?ids=' + ids; }
  else if (action === 'export') { window.location = '/candidates?export=1&ids=' + ids; }
  else if (action === 'pipeline') {
    const stage = prompt('Move to stage: applied / ai_screening / qualified / disqualified / tech_interview / manager_interview / final_review / offer / hired / rejected / withdrawn');
    if (stage) {
      fetch('/pipeline/bulk-move', {method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({ids:Array.from(selectedIds),status:stage})})
        .then(r=>r.json()).then(d=>{ alert(d.message || 'Done'); location.reload(); });
    }
  } else if (action === 'reject') {
    if (confirm('Reject ' + selectedIds.size + ' candidate(s)?')) {
      fetch('/pipeline/bulk-move', {method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({ids:Array.from(selectedIds),status:'rejected'})})
        .then(r=>r.json()).then(d=>{ alert(d.message || 'Done'); location.reload(); });
    }
  }
}
function toggleAdv() {
  const el = document.getElementById('advFilters');
  el.style.display = el.style.display === 'none' ? 'grid' : 'none';
}
</script>
