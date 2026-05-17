<?php
$pageTitle  = 'Campaigns';
$activePage = 'campaigns';

$filterStatus = $_GET['status'] ?? 'all';

$campaigns = $campaigns ?? [
    [
        'id'          => 1,
        'name'        => 'Q3 Brand Awareness Push',
        'description' => 'Multi-platform awareness campaign targeting tech decision-makers across LinkedIn, Twitter, and YouTube.',
        'status'      => 'active',
        'start_date'  => '2025-07-01',
        'end_date'    => '2025-09-30',
        'platforms'   => ['linkedin', 'twitter', 'youtube'],
        'content_pct' => 68,
        'publish_pct' => 42,
        'budget'      => 15000,
        'spent'       => 6430,
        'target_reach'=> '500K',
        'actual_reach'=> '214K',
        'engagement'  => '8.4%',
        'team'        => [['initials'=>'AA','color'=>'#3B82F6'],['initials'=>'SA','color'=>'#8B5CF6'],['initials'=>'MK','color'=>'#10B981']],
    ],
    [
        'id'          => 2,
        'name'        => 'Product Launch: SociAI Pro',
        'description' => 'Launch campaign for new SociAI Pro tier featuring AI autopilot and advanced analytics.',
        'status'      => 'planning',
        'start_date'  => '2025-08-15',
        'end_date'    => '2025-09-15',
        'platforms'   => ['linkedin', 'instagram', 'tiktok', 'facebook'],
        'content_pct' => 22,
        'publish_pct' => 0,
        'budget'      => 25000,
        'spent'       => 1200,
        'target_reach'=> '1M',
        'actual_reach'=> '—',
        'engagement'  => '—',
        'team'        => [['initials'=>'AA','color'=>'#3B82F6'],['initials'=>'RJ','color'=>'#F59E0B']],
    ],
    [
        'id'          => 3,
        'name'        => 'Community Growth Sprint',
        'description' => 'Aggressive 30-day engagement push to grow community following across Instagram and TikTok.',
        'status'      => 'completed',
        'start_date'  => '2025-05-01',
        'end_date'    => '2025-05-31',
        'platforms'   => ['instagram', 'tiktok'],
        'content_pct' => 100,
        'publish_pct' => 100,
        'budget'      => 5000,
        'spent'       => 4870,
        'target_reach'=> '200K',
        'actual_reach'=> '318K',
        'engagement'  => '12.1%',
        'team'        => [['initials'=>'SA','color'=>'#8B5CF6'],['initials'=>'MK','color'=>'#10B981'],['initials'=>'LT','color'=>'#EC4899']],
    ],
    [
        'id'          => 4,
        'name'        => 'Holiday Season 2025',
        'description' => 'Festive content series with seasonal promotions, gift guides, and year-end recaps.',
        'status'      => 'paused',
        'start_date'  => '2025-11-01',
        'end_date'    => '2025-12-31',
        'platforms'   => ['instagram', 'facebook', 'pinterest'],
        'content_pct' => 10,
        'publish_pct' => 0,
        'budget'      => 20000,
        'spent'       => 0,
        'target_reach'=> '750K',
        'actual_reach'=> '—',
        'engagement'  => '—',
        'team'        => [['initials'=>'AA','color'=>'#3B82F6'],['initials'=>'SA','color'=>'#8B5CF6']],
    ],
];

$platformEmojis = [
    'linkedin'  => '💼',
    'instagram' => '📸',
    'tiktok'    => '🎵',
    'facebook'  => '👥',
    'twitter'   => '🐦',
    'youtube'   => '▶️',
    'snapchat'  => '👻',
    'threads'   => '🧵',
    'pinterest' => '📌',
    'whatsapp'  => '💬',
    'telegram'  => '✈️',
];

$statusConfig = [
    'all'       => ['label' => 'All',       'badge' => ''],
    'planning'  => ['label' => 'Planning',  'badge' => 'badge-info'],
    'active'    => ['label' => 'Active',    'badge' => 'badge-success'],
    'paused'    => ['label' => 'Paused',    'badge' => 'badge-warning'],
    'completed' => ['label' => 'Completed', 'badge' => 'badge-neutral'],
    'cancelled' => ['label' => 'Cancelled', 'badge' => 'badge-danger'],
];

$visibleCampaigns = $filterStatus === 'all'
    ? $campaigns
    : array_values(array_filter($campaigns, fn($c) => $c['status'] === $filterStatus));

ob_start();
?>

<div class="campaigns-page">

  <!-- ── PAGE HEADER ──────────────────────────────── -->
  <div class="page-header page-header-row" style="margin-bottom:1.5rem">
    <div>
      <h1>Campaigns</h1>
      <p style="color:var(--text-muted);margin-top:0.25rem">Plan, track, and optimise your marketing campaigns across all platforms</p>
    </div>
    <button class="btn btn-primary" onclick="SociAI.openModal('newCampaignModal')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Campaign
    </button>
  </div>

  <!-- ── STATUS FILTER TABS ───────────────────────── -->
  <div style="display:flex;gap:0.25rem;margin-bottom:1.5rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:0.3rem;width:fit-content">
    <?php foreach ($statusConfig as $key => $cfg): ?>
    <?php $count = $key === 'all' ? count($campaigns) : count(array_filter($campaigns, fn($c) => $c['status'] === $key)); ?>
    <a href="?status=<?= $key ?>"
       style="display:flex;align-items:center;gap:0.4rem;padding:0.45rem 1rem;border-radius:var(--radius-sm);font-size:0.82rem;font-weight:<?= $filterStatus === $key ? '600' : '400' ?>;color:<?= $filterStatus === $key ? 'var(--text-primary)' : 'var(--text-muted)' ?>;background:<?= $filterStatus === $key ? 'var(--glass-bg-hover)' : 'transparent' ?>;text-decoration:none;transition:all 0.2s;white-space:nowrap">
      <?= $cfg['label'] ?>
      <?php if ($count > 0): ?>
      <span style="background:var(--glass-border);border-radius:999px;padding:0 0.4em;font-size:0.72rem;min-width:1.4em;text-align:center"><?= $count ?></span>
      <?php endif ?>
    </a>
    <?php endforeach ?>
  </div>

  <!-- ── CAMPAIGNS GRID ───────────────────────────── -->
  <?php if (empty($visibleCampaigns)): ?>
  <div class="glass-card" style="text-align:center;padding:4rem 2rem;color:var(--text-muted)">
    <div style="font-size:3rem;margin-bottom:1rem">📋</div>
    <div style="font-size:1rem;font-weight:600;margin-bottom:0.5rem">No campaigns found</div>
    <div style="font-size:0.875rem;margin-bottom:1.5rem">Create your first campaign to get started</div>
    <button class="btn btn-primary" onclick="SociAI.openModal('newCampaignModal')">New Campaign</button>
  </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1.25rem;margin-bottom:1.5rem">
    <?php foreach ($visibleCampaigns as $c): ?>
    <?php
    $badgeCls = $statusConfig[$c['status']]['badge'] ?? 'badge-neutral';
    $budgetPct = $c['budget'] > 0 ? round($c['spent'] / $c['budget'] * 100) : 0;
    ?>
    <div class="glass-card campaign-card" data-id="<?= (int)$c['id'] ?>"
         style="cursor:pointer;transition:all 0.2s"
         onmouseover="this.style.borderColor='var(--glass-border-hover)'"
         onmouseout="this.style.borderColor='var(--glass-border)'"
         onclick="campaignPanel.open(<?= (int)$c['id'] ?>)">

      <!-- Card Header -->
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem">
        <div style="flex:1;min-width:0">
          <h3 style="font-size:1rem;font-weight:700;margin:0 0 0.3rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($c['name']) ?></h3>
          <p style="font-size:0.78rem;color:var(--text-muted);margin:0;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= htmlspecialchars($c['description']) ?></p>
        </div>
        <span class="badge <?= $badgeCls ?> badge-dot" style="margin-left:0.75rem;flex-shrink:0"><?= ucfirst($c['status']) ?></span>
      </div>

      <!-- Date Range -->
      <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.78rem;color:var(--text-muted);margin-bottom:0.75rem">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <?= date('M j, Y', strtotime($c['start_date'])) ?> → <?= date('M j, Y', strtotime($c['end_date'])) ?>
      </div>

      <!-- Progress Bars -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem">
        <div>
          <div style="display:flex;justify-content:space-between;font-size:0.72rem;margin-bottom:0.25rem">
            <span style="color:var(--text-muted)">Content Created</span>
            <span style="font-weight:600;color:var(--blue-light)"><?= $c['content_pct'] ?>%</span>
          </div>
          <div class="progress-bar sm">
            <div class="progress-fill" style="width:<?= $c['content_pct'] ?>%;background:var(--blue)"></div>
          </div>
        </div>
        <div>
          <div style="display:flex;justify-content:space-between;font-size:0.72rem;margin-bottom:0.25rem">
            <span style="color:var(--text-muted)">Posts Published</span>
            <span style="font-weight:600;color:var(--green-light)"><?= $c['publish_pct'] ?>%</span>
          </div>
          <div class="progress-bar sm">
            <div class="progress-fill" style="width:<?= $c['publish_pct'] ?>%;background:var(--green)"></div>
          </div>
        </div>
      </div>

      <!-- Platform Badges -->
      <div style="display:flex;flex-wrap:wrap;gap:0.3rem;margin-bottom:0.75rem">
        <?php foreach ($c['platforms'] as $pl): ?>
        <span style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:999px;padding:0.15rem 0.55rem;font-size:0.72rem;display:flex;align-items:center;gap:0.3rem">
          <?= $platformEmojis[$pl] ?? '🌐' ?> <?= ucfirst($pl) ?>
        </span>
        <?php endforeach ?>
      </div>

      <!-- Budget & KPIs -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;margin-bottom:0.75rem;padding:0.75rem;background:var(--glass-bg);border-radius:var(--radius-sm)">
        <?php if ($c['budget'] > 0): ?>
        <div style="text-align:center">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.2rem">Budget</div>
          <div style="font-size:0.85rem;font-weight:700"><?= $c['budget'] >= 1000 ? '$'.number_format($c['budget']/1000, 0).'K' : '$'.$c['budget'] ?></div>
          <div style="font-size:0.68rem;color:<?= $budgetPct > 80 ? 'var(--red-light)' : 'var(--text-muted)' ?>">$<?= number_format($c['spent']) ?> spent</div>
        </div>
        <?php else: ?>
        <div style="text-align:center">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.2rem">Budget</div>
          <div style="font-size:0.82rem;color:var(--text-muted)">—</div>
        </div>
        <?php endif ?>
        <div style="text-align:center;border-left:1px solid var(--glass-border);border-right:1px solid var(--glass-border)">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.2rem">Reach</div>
          <div style="font-size:0.85rem;font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($c['actual_reach']) ?></div>
          <div style="font-size:0.68rem;color:var(--text-muted)">target <?= htmlspecialchars($c['target_reach']) ?></div>
        </div>
        <div style="text-align:center">
          <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.2rem">Engagement</div>
          <div style="font-size:0.85rem;font-weight:700;color:<?= $c['engagement'] !== '—' ? 'var(--green)' : 'var(--text-muted)' ?>"><?= htmlspecialchars($c['engagement']) ?></div>
        </div>
      </div>

      <!-- Team & Actions -->
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div style="display:flex;align-items:center">
          <?php foreach (array_slice($c['team'], 0, 4) as $idx => $member): ?>
          <div style="width:28px;height:28px;border-radius:50%;background:<?= htmlspecialchars($member['color']) ?>;display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:700;color:#fff;border:2px solid var(--bg-primary);margin-left:<?= $idx > 0 ? '-8px' : '0' ?>;z-index:<?= 10 - $idx ?>">
            <?= htmlspecialchars($member['initials']) ?>
          </div>
          <?php endforeach ?>
          <?php if (count($c['team']) > 4): ?>
          <div style="width:28px;height:28px;border-radius:50%;background:var(--glass-bg-hover);display:flex;align-items:center;justify-content:center;font-size:0.6rem;font-weight:700;color:var(--text-muted);border:2px solid var(--bg-primary);margin-left:-8px">
            +<?= count($c['team']) - 4 ?>
          </div>
          <?php endif ?>
        </div>
        <div style="display:flex;gap:0.35rem" onclick="event.stopPropagation()">
          <button class="btn btn-ghost btn-sm" onclick="campaignPanel.open(<?= (int)$c['id'] ?>)">View</button>
          <button class="btn btn-ghost btn-sm" onclick="window.location.href='/dashboard/campaigns/<?= (int)$c['id'] ?>/edit'">Edit</button>
          <?php if ($c['status'] === 'active'): ?>
          <button class="btn btn-ghost btn-sm"
                  onclick="if(confirm('Pause this campaign?')) window.location.href='/dashboard/campaigns/<?= (int)$c['id'] ?>/pause'"
                  style="color:var(--yellow)">Pause</button>
          <?php elseif ($c['status'] === 'paused'): ?>
          <button class="btn btn-ghost btn-sm"
                  onclick="window.location.href='/dashboard/campaigns/<?= (int)$c['id'] ?>/resume'"
                  style="color:var(--green)">Resume</button>
          <?php endif ?>
          <button class="btn btn-ghost btn-sm"
                  onclick="window.location.href='/dashboard/campaigns/<?= (int)$c['id'] ?>/duplicate'"
                  title="Duplicate">⧉</button>
        </div>
      </div>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

</div>

<!-- ── NEW CAMPAIGN MODAL ────────────────────────── -->
<div class="modal-overlay" id="newCampaignModal">
  <div class="modal-content" style="max-width:640px;width:100%">
    <div class="modal-header">
      <h3>🚀 New Campaign</h3>
      <button class="modal-close" onclick="SociAI.closeModal('newCampaignModal')">×</button>
    </div>
    <form action="/dashboard/campaigns/store" method="POST">
      <input type="hidden" name="_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="form-group" style="grid-column:1/-1;margin:0">
          <label class="form-label">Campaign Name <span style="color:var(--red-light)">*</span></label>
          <input type="text" class="form-input" name="name" placeholder="e.g. Q4 Product Launch" required>
        </div>
        <div class="form-group" style="grid-column:1/-1;margin:0">
          <label class="form-label">Description</label>
          <textarea class="form-textarea" name="description" rows="3" placeholder="What is this campaign about?"></textarea>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Start Date <span style="color:var(--red-light)">*</span></label>
          <input type="date" class="form-input" name="start_date" required>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">End Date <span style="color:var(--red-light)">*</span></label>
          <input type="date" class="form-input" name="end_date" required>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Target Platforms</label>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem">
          <?php foreach (['linkedin'=>'💼 LinkedIn','instagram'=>'📸 Instagram','tiktok'=>'🎵 TikTok','facebook'=>'👥 Facebook','twitter'=>'🐦 Twitter/X','youtube'=>'▶️ YouTube','snapchat'=>'👻 Snapchat','threads'=>'🧵 Threads','pinterest'=>'📌 Pinterest','whatsapp'=>'💬 WhatsApp','telegram'=>'✈️ Telegram'] as $val => $label): ?>
          <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;cursor:pointer;padding:0.4rem 0.5rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);transition:all 0.15s"
                 onmouseover="this.style.borderColor='var(--blue)'" onmouseout="this.style.borderColor='var(--glass-border)'">
            <input type="checkbox" name="platforms[]" value="<?= $val ?>"> <?= $label ?>
          </label>
          <?php endforeach ?>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Campaign Goals</label>
        <textarea class="form-textarea" name="goals" rows="2" placeholder="e.g. Increase brand awareness by 30%, generate 500 leads..."></textarea>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
        <div class="form-group" style="margin:0">
          <label class="form-label">Budget (optional)</label>
          <input type="number" class="form-input" name="budget" placeholder="0.00" min="0" step="100">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Team Members</label>
          <select class="form-select" name="team_members[]" multiple style="height:80px">
            <option value="1">Ahmed Al-Rashid (Admin)</option>
            <option value="2">Sara Ahmed (Editor)</option>
            <option value="3">Mohammed Khalid (Analyst)</option>
            <option value="4">Layla Tariq (Editor)</option>
            <option value="5">Rania Jabr (Viewer)</option>
          </select>
          <span style="font-size:0.72rem;color:var(--text-muted)">Hold Ctrl/Cmd to select multiple</span>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="SociAI.closeModal('newCampaignModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">🚀 Create Campaign</button>
      </div>
    </form>
  </div>
</div>

<!-- ── CAMPAIGN DETAIL PANEL ─────────────────────── -->
<div id="campaignDetailPanel"
     style="position:fixed;top:0;right:-480px;width:480px;height:100vh;background:var(--bg-secondary);border-left:1px solid var(--glass-border);z-index:1200;transition:right 0.35s cubic-bezier(0.4,0,0.2,1);overflow-y:auto;display:flex;flex-direction:column">
  <div style="padding:1.5rem;border-bottom:1px solid var(--glass-border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
    <h3 id="panelCampaignName" style="margin:0;font-size:1rem"></h3>
    <button onclick="campaignPanel.close()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.4rem;line-height:1">×</button>
  </div>
  <div id="panelBody" style="padding:1.5rem;flex:1">
    <!-- Populated by JS -->
    <div style="text-align:center;padding:2rem;color:var(--text-muted)">
      <div style="font-size:2rem;margin-bottom:0.5rem">📋</div>
      <div>Select a campaign to view details</div>
    </div>
  </div>
</div>
<div id="panelBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:1100" onclick="campaignPanel.close()"></div>

<script>
const campaignData = <?= json_encode(array_column($campaigns, null, 'id'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;

const campaignPanel = {
  open(id) {
    const c = campaignData[id];
    if (!c) return;
    document.getElementById('panelCampaignName').textContent = c.name;
    const statusLabels = {planning:'Planning',active:'Active',paused:'Paused',completed:'Completed',cancelled:'Cancelled'};
    const statusColors = {planning:'var(--blue)',active:'var(--green)',paused:'var(--yellow)',completed:'var(--text-muted)',cancelled:'var(--red-light)'};
    document.getElementById('panelBody').innerHTML = `
      <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem">
        <span style="background:${statusColors[c.status] || 'var(--glass-border)'};color:#fff;border-radius:999px;padding:0.2rem 0.75rem;font-size:0.78rem;font-weight:600">${statusLabels[c.status]||c.status}</span>
        <span style="font-size:0.78rem;color:var(--text-muted)">${c.start_date} → ${c.end_date}</span>
      </div>
      <p style="font-size:0.875rem;color:var(--text-muted);margin-bottom:1.25rem;line-height:1.6">${c.description}</p>

      <div style="margin-bottom:1.25rem">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);margin-bottom:0.6rem">Progress</div>
        <div style="margin-bottom:0.5rem">
          <div style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:0.25rem">
            <span>Content Created</span><span style="font-weight:600">${c.content_pct}%</span>
          </div>
          <div class="progress-bar sm"><div class="progress-fill" style="width:${c.content_pct}%;background:var(--blue)"></div></div>
        </div>
        <div>
          <div style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:0.25rem">
            <span>Posts Published</span><span style="font-weight:600">${c.publish_pct}%</span>
          </div>
          <div class="progress-bar sm"><div class="progress-fill" style="width:${c.publish_pct}%;background:var(--green)"></div></div>
        </div>
      </div>

      <div style="margin-bottom:1.25rem">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);margin-bottom:0.6rem">Performance</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem">
          <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:0.75rem;text-align:center">
            <div style="font-size:0.68rem;color:var(--text-muted)">Target Reach</div>
            <div style="font-size:1rem;font-weight:700">${c.target_reach}</div>
          </div>
          <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:0.75rem;text-align:center">
            <div style="font-size:0.68rem;color:var(--text-muted)">Actual Reach</div>
            <div style="font-size:1rem;font-weight:700;color:var(--green)">${c.actual_reach}</div>
          </div>
          <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:0.75rem;text-align:center">
            <div style="font-size:0.68rem;color:var(--text-muted)">Engagement</div>
            <div style="font-size:1rem;font-weight:700;color:var(--blue-light)">${c.engagement}</div>
          </div>
        </div>
      </div>

      ${c.budget > 0 ? `
      <div style="margin-bottom:1.25rem">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);margin-bottom:0.6rem">Budget</div>
        <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.35rem">
          <span>$${c.spent.toLocaleString()} spent</span>
          <span style="font-weight:600">$${c.budget.toLocaleString()} total</span>
        </div>
        <div class="progress-bar sm">
          <div class="progress-fill" style="width:${Math.round(c.spent/c.budget*100)}%;background:${c.spent/c.budget>0.8?'var(--red)':'var(--purple)'}"></div>
        </div>
      </div>` : ''}

      <div style="margin-bottom:1.25rem">
        <div style="font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);margin-bottom:0.6rem">Content Calendar</div>
        <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:0.75rem">
          ${['Week 1','Week 2','Week 3','Week 4'].map((wk, wi) => `
            <div style="display:flex;align-items:center;justify-content:space-between;padding:0.4rem 0;${wi>0?'border-top:1px solid var(--glass-border)':''}">
              <span style="font-size:0.78rem;font-weight:600">${wk}</span>
              <div style="display:flex;gap:0.3rem">
                ${c.platforms.slice(0,3).map(pl => `<span style="background:var(--glass-bg-hover);border-radius:4px;padding:0.15rem 0.4rem;font-size:0.68rem">1 post</span>`).join('')}
              </div>
            </div>`).join('')}
        </div>
      </div>

      <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
        <a href="/dashboard/campaigns/${c.id}/edit" class="btn btn-primary btn-sm">Edit Campaign</a>
        ${c.status==='active'?`<a href="/dashboard/campaigns/${c.id}/pause" class="btn btn-ghost btn-sm" style="color:var(--yellow)">Pause</a>`:''}
        ${c.status==='paused'?`<a href="/dashboard/campaigns/${c.id}/resume" class="btn btn-ghost btn-sm" style="color:var(--green)">Resume</a>`:''}
        <a href="/dashboard/campaigns/${c.id}/duplicate" class="btn btn-ghost btn-sm">Duplicate</a>
      </div>`;
    document.getElementById('campaignDetailPanel').style.right = '0';
    document.getElementById('panelBackdrop').style.display = 'block';
  },
  close() {
    document.getElementById('campaignDetailPanel').style.right = '-480px';
    document.getElementById('panelBackdrop').style.display = 'none';
  }
};
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
