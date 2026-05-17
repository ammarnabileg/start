<?php
$pageTitle  = 'Campaigns';
$activePage = 'campaigns';
$campaigns = [
  ['name'=>'Q2 Product Launch',       'status'=>'active',  'goal'=>'Brand Awareness','start'=>'May 1','end'=>'Jun 30','budget'=>'$5,000','spent'=>'$2,840','progress'=>57,'platforms'=>['linkedin','instagram','tiktok','twitter'],'impressions'=>'2.1M','engagement'=>'8.4%'],
  ['name'=>'Summer Sale 2025',         'status'=>'active',  'goal'=>'Conversions',    'start'=>'Jun 1','end'=>'Jul 31','budget'=>'$8,000','spent'=>'$1,200','progress'=>15,'platforms'=>['instagram','facebook','tiktok'],'impressions'=>'890K','engagement'=>'6.2%'],
  ['name'=>'Thought Leadership Series','status'=>'active',  'goal'=>'Lead Generation','start'=>'Apr 1','end'=>'Jul 1', 'budget'=>'$3,500','spent'=>'$3,100','progress'=>89,'platforms'=>['linkedin','twitter'],'impressions'=>'4.7M','engagement'=>'12.1%'],
  ['name'=>'Ramadan Campaign',         'status'=>'paused',  'goal'=>'Engagement',     'start'=>'Mar 1','end'=>'Apr 2', 'budget'=>'$4,200','spent'=>'$4,200','progress'=>100,'platforms'=>['instagram','tiktok','snapchat'],'impressions'=>'8.9M','engagement'=>'18.7%'],
  ['name'=>'Influencer Collab Wave',   'status'=>'draft',   'goal'=>'Reach',          'start'=>'Jul 1','end'=>'Aug 31','budget'=>'$12,000','spent'=>'$0',   'progress'=>0,'platforms'=>['instagram','tiktok','youtube'],'impressions'=>'—','engagement'=>'—'],
];
$statusMap = ['active'=>'badge-success','paused'=>'badge-warning','draft'=>'badge-neutral','completed'=>'badge-info'];
?>
<?php ob_start() ?>
<div class="page-header page-header-row">
  <div>
    <h1>Campaigns 🎯</h1>
    <p>Manage multi-platform AI-driven marketing campaigns</p>
  </div>
  <div style="display:flex;gap:0.75rem">
    <button class="btn btn-ghost ai-brief-btn">🤖 AI Brief Generator</button>
    <button class="btn btn-primary create-campaign-btn" data-modal="createCampaignModal">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Campaign
    </button>
  </div>
</div>

<!-- Summary Stats -->
<div class="dashboard-grid grid-cols-4 mb-4">
  <?php foreach([['Active','3','badge-success'],['Total Budget','$33.7K','badge-info'],['Total Spent','$11.3K','badge-warning'],['Total Reach','16.5M','badge-purple']] as [$l,$v,$b]): ?>
  <div class="metric-card" style="padding:1.25rem">
    <div class="metric-label"><?= $l ?></div>
    <div class="metric-value" style="font-size:1.8rem"><?= $v ?></div>
  </div>
  <?php endforeach ?>
</div>

<!-- Campaign Cards -->
<div style="display:flex;flex-direction:column;gap:1rem">
  <?php foreach ($campaigns as $c): ?>
  <div class="campaign-card glass-card">
    <div class="campaign-header">
      <div>
        <h3 style="font-size:1rem;margin-bottom:0.3rem"><?= htmlspecialchars($c['name']) ?></h3>
        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
          <span class="badge <?= $statusMap[$c['status']] ?? 'badge-neutral' ?> badge-dot"><?= ucfirst($c['status']) ?></span>
          <span class="badge badge-neutral"><?= htmlspecialchars($c['goal']) ?></span>
        </div>
      </div>
      <div style="display:flex;gap:0.5rem">
        <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Opening campaign editor...','info')">Edit</button>
        <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Viewing analytics...','info')">Analytics</button>
        <?php if ($c['status'] === 'active'): ?>
        <button class="btn btn-danger btn-sm" onclick="SociAI.showToast('Campaign paused','warning')">Pause</button>
        <?php elseif ($c['status'] === 'paused'): ?>
        <button class="btn btn-success btn-sm" onclick="SociAI.showToast('Campaign resumed!','success')">Resume</button>
        <?php elseif ($c['status'] === 'draft'): ?>
        <button class="btn btn-primary btn-sm" onclick="SociAI.showToast('Campaign launched!','success')">Launch</button>
        <?php endif ?>
      </div>
    </div>

    <div class="campaign-meta">
      <span>📅 <?= htmlspecialchars($c['start']) ?> → <?= htmlspecialchars($c['end']) ?></span>
      <span>💰 Budget: <?= htmlspecialchars($c['budget']) ?></span>
      <span>💸 Spent: <?= htmlspecialchars($c['spent']) ?></span>
      <?php if ($c['impressions'] !== '—'): ?>
      <span>👁️ <?= htmlspecialchars($c['impressions']) ?> impressions</span>
      <span>❤️ <?= htmlspecialchars($c['engagement']) ?> eng. rate</span>
      <?php endif ?>
    </div>

    <div class="campaign-platforms" style="margin-bottom:0.875rem">
      <?php foreach ($c['platforms'] as $p): ?>
      <span class="platform-badge platform-<?= $p ?>"><?= ucfirst($p) ?></span>
      <?php endforeach ?>
    </div>

    <div style="display:flex;align-items:center;gap:0.75rem">
      <div class="progress-bar" style="flex:1">
        <div class="progress-fill" style="width:<?= $c['progress'] ?>%"></div>
      </div>
      <span style="font-size:0.78rem;color:var(--text-muted);white-space:nowrap;min-width:45px"><?= $c['progress'] ?>% done</span>
    </div>
  </div>
  <?php endforeach ?>
</div>

<!-- Create Campaign Modal -->
<div class="modal-overlay" id="createCampaignModal">
  <div class="modal-content modal-content-lg">
    <div class="modal-header">
      <h3>🚀 Create New Campaign</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Campaign Name</label>
        <input type="text" class="form-input" placeholder="e.g. Summer Sale 2025">
      </div>
      <div class="form-group">
        <label class="form-label">Campaign Goal</label>
        <select class="form-select">
          <option>Brand Awareness</option><option>Lead Generation</option>
          <option>Conversions</option><option>Engagement</option><option>Reach</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Start Date</label>
        <input type="date" class="form-input">
      </div>
      <div class="form-group">
        <label class="form-label">End Date</label>
        <input type="date" class="form-input">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Budget (USD)</label>
      <input type="number" class="form-input" placeholder="5000">
    </div>
    <div class="form-group">
      <label class="form-label">Target Platforms</label>
      <div style="display:flex;flex-wrap:wrap;gap:0.4rem">
        <?php foreach(['LinkedIn','Instagram','TikTok','Twitter/X','Facebook','YouTube','Snapchat','Threads'] as $p): ?>
        <label style="display:flex;align-items:center;gap:0.3rem;font-size:0.8rem;cursor:pointer;padding:0.3rem 0.6rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:99px">
          <input type="checkbox" style="accent-color:var(--blue)">
          <?= htmlspecialchars($p) ?>
        </label>
        <?php endforeach ?>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Campaign Brief</label>
      <textarea class="form-textarea" rows="3" placeholder="Describe your campaign objectives, target audience, key messages..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="SociAI.closeModal('createCampaignModal')">Cancel</button>
      <button class="btn btn-ghost" onclick="SociAI.showToast('Saved as draft!','info');SociAI.closeModal('createCampaignModal')">Save Draft</button>
      <button class="btn btn-primary" onclick="SociAI.showToast('Campaign launched!','success');SociAI.closeModal('createCampaignModal')">🚀 Launch Campaign</button>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
