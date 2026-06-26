<?php
// views/hr/pipeline.php
// Variables: $columns (assoc array keyed by status with 'label', 'cards' sub-array), $jobs (array), $filters (array)
// Each card has: application_id, first_name, last_name, job_title, final_score, days_in_stage, status
$columns = $columns ?? [];
$jobs    = $jobs ?? [];
$filters = $filters ?? [];

if (empty($columns)) {
    // Default empty column structure
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
    foreach ($allStages as $k => $v) {
        $columns[$k] = ['label' => $v, 'cards' => []];
    }
}

$columnColors = [
    'applied'           => '#60a5fa',
    'ai_screening'      => '#a78bfa',
    'qualified'         => '#4ade80',
    'disqualified'      => '#f87171',
    'tech_interview'    => '#fbbf24',
    'manager_interview' => '#fb923c',
    'final_review'      => '#f472b6',
    'offer'             => '#2dd4bf',
    'hired'             => '#4ade80',
    'rejected'          => '#f87171',
    'withdrawn'         => '#94a3b8',
];

function pipelineScore(?float $s): string {
    if ($s === null) return '';
    $c = $s >= 80 ? '#4ade80' : ($s >= 60 ? '#fbbf24' : '#f87171');
    return "<span style=\"font-size:0.72rem;font-weight:700;color:{$c};background:{$c}22;padding:2px 6px;border-radius:4px;\">" . number_format($s, 0) . "</span>";
}
?>
<style>
  .page-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px; }
  .page-title { font-size:1.5rem;font-weight:800;color:#f1f5f9; }
  .page-subtitle { color:#64748b;font-size:0.875rem;margin-top:2px; }
  .btn { display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;transition:all 0.15s; }
  .btn-ghost { background:transparent;color:#94a3b8;border:1px solid rgba(79,70,229,0.25); }
  .btn-ghost:hover { background:rgba(79,70,229,0.1);color:#e2e8f0; }
  .btn-sm { padding:6px 12px;font-size:0.78rem; }
  .filter-bar { background:#1e1e32;border:1px solid rgba(79,70,229,0.12);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap; }
  .search-box { display:flex;align-items:center;gap:8px;flex:1;min-width:180px;background:rgba(15,15,26,0.7);border:1px solid rgba(79,70,229,0.2);border-radius:8px;padding:0 10px; }
  .search-box input { background:none;border:none;outline:none;color:#e2e8f0;font-size:0.85rem;padding:8px 0;width:100%; }
  .search-box input::placeholder { color:#475569; }
  .filter-select { background:#0f0f1a;border:1px solid rgba(79,70,229,0.2);border-radius:8px;color:#e2e8f0;padding:8px 12px;font-size:0.85rem;outline:none; }
  /* Kanban */
  .kanban-wrapper { overflow-x:auto;padding-bottom:16px; }
  .kanban-board { display:flex;gap:14px;min-width:max-content;align-items:flex-start; }
  .kanban-col {
    width:256px;flex-shrink:0;
    background:#1a1a2e;border:1px solid rgba(79,70,229,0.12);border-radius:14px;
    display:flex;flex-direction:column;max-height:calc(100vh - 280px);
  }
  .col-header { padding:14px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(79,70,229,0.1);flex-shrink:0; }
  .col-title { font-size:0.82rem;font-weight:700;color:#e2e8f0; }
  .col-count { font-size:0.72rem;font-weight:700;padding:2px 8px;border-radius:10px;min-width:22px;text-align:center; }
  .col-body { flex:1;overflow-y:auto;padding:10px;display:flex;flex-direction:column;gap:8px; }
  .col-body::-webkit-scrollbar { width:4px; }
  .col-body::-webkit-scrollbar-track { background:transparent; }
  .col-body::-webkit-scrollbar-thumb { background:rgba(79,70,229,0.2);border-radius:2px; }
  /* Kanban card */
  .k-card {
    background:#1e1e32;border:1px solid rgba(79,70,229,0.15);border-radius:10px;padding:12px;
    cursor:grab;position:relative;
    transition:border-color 0.2s,box-shadow 0.2s,opacity 0.2s;
  }
  .k-card:hover { border-color:rgba(79,70,229,0.4);box-shadow:0 4px 12px rgba(0,0,0,0.3); }
  .k-card.dragging { opacity:0.4;cursor:grabbing; }
  .k-card.drag-over { border-color:#4f46e5;box-shadow:0 0 0 2px rgba(79,70,229,0.3); }
  .k-card-top { display:flex;align-items:flex-start;gap:8px;margin-bottom:8px; }
  .k-check { width:14px;height:14px;accent-color:#4f46e5;flex-shrink:0;margin-top:2px; }
  .k-initials { width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:0.6rem;font-weight:700;color:#fff;flex-shrink:0; }
  .k-name { font-size:0.8rem;font-weight:600;color:#e2e8f0;line-height:1.3;flex:1; }
  .k-name a { color:inherit;text-decoration:none; }
  .k-name a:hover { color:#818cf8; }
  .k-drag-handle { color:#475569;cursor:grab;flex-shrink:0;margin-top:2px; }
  .k-job { font-size:0.72rem;color:#64748b;margin-bottom:6px;padding-left:22px; }
  .k-footer { display:flex;align-items:center;justify-content:space-between;padding-top:6px;border-top:1px solid rgba(79,70,229,0.08); }
  .k-days { font-size:0.7rem;color:#475569; }
  .k-days.warning { color:#fbbf24; }
  .k-days.danger { color:#f87171; }
  .kanban-col.drag-target { background:#1e1e3c;border-color:rgba(79,70,229,0.4); }
  .col-empty { text-align:center;padding:20px;color:#475569;font-size:0.78rem; }
  /* Bulk bar */
  .pipeline-bulk { display:none;position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#4f46e5;border-radius:12px;padding:12px 20px;box-shadow:0 8px 32px rgba(79,70,229,0.4);z-index:200;align-items:center;gap:14px; }
  .pipeline-bulk.visible { display:flex; }
  .pb-count { color:#fff;font-size:0.875rem;font-weight:700; }
  .pb-btn { background:rgba(255,255,255,0.2);border:none;color:#fff;padding:6px 12px;border-radius:6px;cursor:pointer;font-size:0.8rem;font-weight:600; }
  .pb-btn:hover { background:rgba(255,255,255,0.35); }
</style>

<div class="page-header">
  <div>
    <div class="page-title">Pipeline</div>
    <div class="page-subtitle">Drag candidates between stages to update their status</div>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/candidates" class="btn btn-ghost btn-sm">List View</a>
  </div>
</div>

<!-- Filter bar -->
<form method="GET" action="/pipeline" id="filterForm">
<div class="filter-bar">
  <div class="search-box">
    <svg width="13" height="13" fill="none" stroke="#64748b" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Search candidates…" id="pipelineSearch" oninput="clientFilter(this.value)">
  </div>
  <select name="job_id" class="filter-select" onchange="document.getElementById('filterForm').submit()">
    <option value="">All Jobs</option>
    <?php foreach ($jobs as $j): ?>
      <option value="<?= (int)$j['id'] ?>" <?= ($filters['job_id'] ?? '') == $j['id'] ? 'selected' : '' ?>><?= htmlspecialchars($j['title']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
</form>

<!-- Kanban board -->
<div class="kanban-wrapper">
  <div class="kanban-board" id="kanbanBoard">
    <?php foreach ($columns as $status => $col):
      $color = $columnColors[$status] ?? '#94a3b8';
      $cards = $col['cards'] ?? [];
      $count = count($cards);
    ?>
      <div class="kanban-col" data-status="<?= htmlspecialchars($status) ?>"
           ondragover="event.preventDefault();this.classList.add('drag-target')"
           ondragleave="this.classList.remove('drag-target')"
           ondrop="handleDrop(event, '<?= htmlspecialchars($status) ?>')">
        <div class="col-header" style="border-top:3px solid <?= $color ?>;">
          <span class="col-title"><?= htmlspecialchars($col['label'] ?? ucwords(str_replace('_',' ',$status))) ?></span>
          <span class="col-count" style="background:<?= $color ?>22;color:<?= $color ?>;"><?= $count ?></span>
        </div>
        <div class="col-body" id="col-<?= htmlspecialchars($status) ?>">
          <?php if (empty($cards)): ?>
            <div class="col-empty">No candidates</div>
          <?php else: ?>
            <?php foreach ($cards as $card):
              $appId = (int)($card['application_id'] ?? $card['id'] ?? 0);
              $fn = $card['first_name'] ?? 'Unknown';
              $ln = $card['last_name'] ?? '';
              $initials = strtoupper(substr($fn, 0, 1) . substr($ln, 0, 1));
              $fullName = trim("$fn $ln");
              $days = (int)($card['days_in_stage'] ?? 0);
              $daysClass = $days > 14 ? 'danger' : ($days > 7 ? 'warning' : '');
              $score = isset($card['final_score']) ? (float)$card['final_score'] : null;
            ?>
              <div class="k-card"
                   id="card-<?= $appId ?>"
                   draggable="true"
                   data-id="<?= $appId ?>"
                   data-name="<?= htmlspecialchars(strtolower($fullName)) ?>"
                   ondragstart="handleDragStart(event)"
                   ondragend="handleDragEnd(event)">
                <div class="k-card-top">
                  <input type="checkbox" class="k-check pipe-check" value="<?= $appId ?>" onchange="updatePipeBulk()" onclick="event.stopPropagation()">
                  <div class="k-initials"><?= htmlspecialchars($initials) ?></div>
                  <div class="k-name">
                    <a href="/candidates/<?= $appId ?>"><?= htmlspecialchars($fullName) ?></a>
                  </div>
                  <div class="k-drag-handle" title="Drag to move">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>
                  </div>
                </div>
                <?php if ($card['job_title'] ?? ''): ?>
                  <div class="k-job"><?= htmlspecialchars($card['job_title']) ?></div>
                <?php endif; ?>
                <div class="k-footer">
                  <span class="k-days <?= $daysClass ?>"><?= $days ?>d in stage</span>
                  <?= pipelineScore($score) ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Bulk action bar -->
<div class="pipeline-bulk" id="pipeBulkBar">
  <span class="pb-count"><span id="pipeCount">0</span> selected</span>
  <button class="pb-btn" onclick="pipeBulkMove()">Move Stage</button>
  <button class="pb-btn" onclick="pipeBulkExport()">Export</button>
  <button class="pb-btn" onclick="pipeClearSel()">&times; Clear</button>
</div>

<script>
let draggedCardId = null;
let draggedCard = null;
let selectedCards = new Set();

function handleDragStart(e) {
  draggedCard = e.currentTarget;
  draggedCardId = draggedCard.dataset.id;
  setTimeout(() => draggedCard.classList.add('dragging'), 0);
  e.dataTransfer.effectAllowed = 'move';
}
function handleDragEnd(e) {
  if (draggedCard) draggedCard.classList.remove('dragging');
  document.querySelectorAll('.kanban-col').forEach(c => c.classList.remove('drag-target'));
}
function handleDrop(e, targetStatus) {
  e.preventDefault();
  const col = e.currentTarget;
  col.classList.remove('drag-target');
  if (!draggedCardId) return;
  const body = document.getElementById('col-' + targetStatus);
  if (!body || !draggedCard) return;
  // Optimistically move card in DOM
  const emptyMsg = body.querySelector('.col-empty');
  if (emptyMsg) emptyMsg.remove();
  body.appendChild(draggedCard);
  // Update count badges
  updateColCounts();
  // API call
  fetch('/pipeline/move', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ application_id: parseInt(draggedCardId), status: targetStatus })
  }).then(r => r.json()).then(d => {
    if (!d.success) { alert(d.message || 'Move failed'); location.reload(); }
  }).catch(() => { alert('Move failed'); location.reload(); });
  draggedCardId = null;
  draggedCard = null;
}
function updateColCounts() {
  document.querySelectorAll('.kanban-col').forEach(col => {
    const status = col.dataset.status;
    const body = document.getElementById('col-' + status);
    const cards = body ? body.querySelectorAll('.k-card').length : 0;
    const badge = col.querySelector('.col-count');
    if (badge) badge.textContent = cards;
  });
}
function updatePipeBulk() {
  selectedCards.clear();
  document.querySelectorAll('.pipe-check:checked').forEach(cb => selectedCards.add(cb.value));
  const bar = document.getElementById('pipeBulkBar');
  document.getElementById('pipeCount').textContent = selectedCards.size;
  bar.classList.toggle('visible', selectedCards.size > 0);
}
function pipeClearSel() {
  document.querySelectorAll('.pipe-check').forEach(cb => cb.checked = false);
  selectedCards.clear();
  document.getElementById('pipeBulkBar').classList.remove('visible');
}
function pipeBulkMove() {
  const stage = prompt('Move to stage:\napplied / ai_screening / qualified / disqualified / tech_interview / manager_interview / final_review / offer / hired / rejected / withdrawn');
  if (!stage || !selectedCards.size) return;
  fetch('/pipeline/bulk-move', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ ids: Array.from(selectedCards), status: stage })
  }).then(r => r.json()).then(d => { alert(d.message || 'Done'); location.reload(); });
}
function pipeBulkExport() {
  window.location = '/candidates?export=1&ids=' + Array.from(selectedCards).join(',');
}
function clientFilter(query) {
  query = query.toLowerCase();
  document.querySelectorAll('.k-card').forEach(card => {
    const name = card.dataset.name || '';
    card.style.display = name.includes(query) ? '' : 'none';
  });
  updateColCounts();
}
</script>
