<?php
$pageTitle  = 'Content Hub';
$activePage = 'content';

$stats = [
  ['label'=>'Total Content', 'value'=>'1,248', 'color'=>'blue'],
  ['label'=>'Scheduled',     'value'=>'47',    'color'=>'purple'],
  ['label'=>'Pending Review','value'=>'12',    'color'=>'yellow'],
  ['label'=>'Published',     'value'=>'1,189', 'color'=>'green'],
  ['label'=>'Avg Viral Score','value'=>'78.4', 'color'=>'pink'],
];

$contentItems = [
  ['id'=>1,'title'=>'5 AI trends reshaping business in 2025','platform'=>'linkedin','type'=>'Article','status'=>'published','viral'=>92,'predicted'=>'14.2K','scheduled'=>'Published 2h ago','thumb'=>'📰'],
  ['id'=>2,'title'=>'Behind the scenes: Product launch week','platform'=>'instagram','type'=>'Reel','status'=>'published','viral'=>88,'predicted'=>'8.7K','scheduled'=>'Published 5h ago','thumb'=>'🎬'],
  ['id'=>3,'title'=>'Why most brands fail at TikTok (full breakdown)','platform'=>'tiktok','type'=>'Video','status'=>'scheduled','viral'=>85,'predicted'=>'25K+','scheduled'=>'Today 3:00 PM','thumb'=>'🎵'],
  ['id'=>4,'title'=>'Weekly motivation carousel — Monday edition','platform'=>'instagram','type'=>'Carousel','status'=>'scheduled','viral'=>79,'predicted'=>'5.2K','scheduled'=>'Tomorrow 9:00 AM','thumb'=>'🎨'],
  ['id'=>5,'title'=>'New feature announcement — AI copilot mode','platform'=>'twitter','type'=>'Thread','status'=>'pending','viral'=>84,'predicted'=>'12K+','scheduled'=>'Pending approval','thumb'=>'🐦'],
  ['id'=>6,'title'=>'Customer success story: 10x growth in 90 days','platform'=>'facebook','type'=>'Post','status'=>'published','viral'=>71,'predicted'=>'3.1K','scheduled'=>'Published 1d ago','thumb'=>'📖'],
  ['id'=>7,'title'=>'How to build a viral LinkedIn strategy','platform'=>'linkedin','type'=>'Article','status'=>'draft','viral'=>68,'predicted'=>'—','scheduled'=>'Draft','thumb'=>'✍️'],
  ['id'=>8,'title'=>'Product demo walkthrough video','platform'=>'youtube','type'=>'Video','status'=>'scheduled','viral'=>76,'predicted'=>'18K','scheduled'=>'Friday 11:00 AM','thumb'=>'▶️'],
];
?>
<?php ob_start() ?>
<div class="page-header page-header-row">
  <div>
    <h1>Content Hub 📋</h1>
    <p>Manage all your AI-generated content across 11 platforms</p>
  </div>
  <div style="display:flex;gap:0.75rem">
    <button class="btn btn-ghost" onclick="SociAI.showToast('Opening content calendar...','info')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Calendar View
    </button>
    <a href="/dashboard/copywriting" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Create Content
    </a>
  </div>
</div>

<!-- Stats Row -->
<div class="dashboard-grid grid-cols-5 mb-4">
  <?php foreach ($stats as $s): ?>
  <div class="metric-card" style="padding:1.25rem">
    <div class="metric-label"><?= htmlspecialchars($s['label']) ?></div>
    <div class="metric-value" style="font-size:1.6rem"><?= htmlspecialchars($s['value']) ?></div>
  </div>
  <?php endforeach ?>
</div>

<!-- Pipeline Stages -->
<div class="pipeline-stages mb-4">
  <div class="pipeline-stage active"><span class="count">1,248</span>All</div>
  <div class="pipeline-stage"><span class="count">1,189</span>Published</div>
  <div class="pipeline-stage"><span class="count">47</span>Scheduled</div>
  <div class="pipeline-stage"><span class="count">12</span>Pending</div>
  <div class="pipeline-stage"><span class="count">—</span>Draft</div>
</div>

<!-- Filters Row -->
<div class="glass-card mb-4" style="padding:1rem 1.25rem">
  <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
    <div style="position:relative;flex:1;min-width:200px">
      <span style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </span>
      <input type="text" class="form-input" placeholder="Search content..." style="padding-left:2.25rem">
    </div>
    <select class="form-select" style="max-width:160px">
      <option>All Platforms</option>
      <option>LinkedIn</option><option>Instagram</option><option>TikTok</option>
      <option>Twitter/X</option><option>Facebook</option><option>YouTube</option>
    </select>
    <select class="form-select" style="max-width:140px">
      <option>All Types</option>
      <option>Article</option><option>Video</option><option>Reel</option>
      <option>Carousel</option><option>Thread</option><option>Story</option>
    </select>
    <select class="form-select" style="max-width:130px">
      <option>Sort: Newest</option>
      <option>Sort: Viral Score</option>
      <option>Sort: Engagement</option>
    </select>
  </div>
</div>

<!-- Content List -->
<div class="glass-card p-0">
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th><input type="checkbox" style="accent-color:var(--blue);width:14px;height:14px"></th>
          <th>Content</th>
          <th>Platform</th>
          <th>Type</th>
          <th>Status</th>
          <th>Viral Score</th>
          <th>Predicted Eng.</th>
          <th>Scheduled Time</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contentItems as $item): ?>
        <tr>
          <td><input type="checkbox" style="accent-color:var(--blue);width:14px;height:14px"></td>
          <td>
            <div style="display:flex;align-items:center;gap:0.6rem">
              <span style="font-size:1.3rem;flex-shrink:0"><?= $item['thumb'] ?></span>
              <span class="td-primary" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.85rem"><?= htmlspecialchars($item['title']) ?></span>
            </div>
          </td>
          <td><span class="platform-badge platform-<?= $item['platform'] ?>"><?= ucfirst($item['platform']) ?></span></td>
          <td><span class="badge badge-neutral"><?= htmlspecialchars($item['type']) ?></span></td>
          <td>
            <?php
            $smap = ['published'=>'badge-success','scheduled'=>'badge-info','pending'=>'badge-warning','draft'=>'badge-neutral'];
            ?>
            <span class="badge <?= $smap[$item['status']] ?? 'badge-neutral' ?> badge-dot"><?= ucfirst($item['status']) ?></span>
          </td>
          <td>
            <?php $vc = $item['viral']>=85?'viral-high':($item['viral']>=70?'viral-mid':'viral-low'); ?>
            <span class="viral-score <?= $vc ?>"><?= $item['viral'] !== '—' ? $item['viral'] : '—' ?></span>
          </td>
          <td style="font-size:0.85rem"><?= htmlspecialchars($item['predicted']) ?></td>
          <td style="font-size:0.78rem;color:var(--text-muted)"><?= htmlspecialchars($item['scheduled']) ?></td>
          <td>
            <div style="display:flex;gap:0.35rem">
              <button class="btn btn-ghost btn-sm">Edit</button>
              <?php if ($item['status'] === 'pending'): ?>
              <button class="btn btn-success btn-sm" onclick="SociAI.showToast('Content approved!','success')">Approve</button>
              <?php elseif ($item['status'] === 'draft'): ?>
              <button class="btn btn-primary btn-sm" onclick="SociAI.showToast('Scheduled!','success')">Schedule</button>
              <?php else: ?>
              <button class="btn btn-ghost btn-sm">View</button>
              <?php endif ?>
              <button class="btn btn-danger btn-sm" onclick="SociAI.showToast('Deleted','error')">✕</button>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <div style="padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--glass-border);font-size:0.82rem;color:var(--text-muted)">
    <span>Showing 1–8 of 1,248 items</span>
    <div style="display:flex;gap:0.35rem">
      <button class="btn btn-ghost btn-sm">← Prev</button>
      <button class="btn btn-primary btn-sm">1</button>
      <button class="btn btn-ghost btn-sm">2</button>
      <button class="btn btn-ghost btn-sm">3</button>
      <span style="padding:0.35rem 0.5rem">...</span>
      <button class="btn btn-ghost btn-sm">156</button>
      <button class="btn btn-ghost btn-sm">Next →</button>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
