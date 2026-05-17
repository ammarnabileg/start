<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::campaigns()
// $brand, $brandId, $campaigns, $campaignStats, $csrf

$statusBadgeMap = [
    'active'    => 'badge-success',
    'paused'    => 'badge-warning',
    'draft'     => '',
    'completed' => 'badge-info',
];

function campFormatBudget(float $n): string {
    if ($n >= 1000) return '$' . number_format($n / 1000, 1) . 'K';
    return '$' . number_format($n, 0);
}
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">Campaigns 🎯</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage multi-platform AI-driven marketing campaigns</p>
    </div>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
        <button class="btn btn-ghost" onclick="if(window.SociAI)SociAI.showToast('AI Brief Generator coming soon','info')">🤖 AI Brief</button>
        <button class="btn btn-primary" onclick="openCreateCampaignModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Campaign
        </button>
    </div>
</div>

<!-- Summary Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <?php
    $statCards = [
        ['label' => 'Active',    'val' => $campaignStats['active'] ?? 0,    'color' => 'var(--green-light)'],
        ['label' => 'Draft',     'val' => $campaignStats['draft'] ?? 0,     'color' => 'var(--text-muted)'],
        ['label' => 'Paused',    'val' => $campaignStats['paused'] ?? 0,    'color' => 'var(--yellow)'],
        ['label' => 'Completed', 'val' => $campaignStats['completed'] ?? 0, 'color' => 'var(--blue-light)'],
        ['label' => 'Total Budget', 'val' => campFormatBudget((float)($campaignStats['total_budget'] ?? 0)), 'color' => 'var(--text-primary)', 'raw' => true],
    ];
    foreach ($statCards as $sc):
    ?>
    <div class="glass-card" style="padding:1rem;">
        <div style="font-size:0.7rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;"><?= $sc['label'] ?></div>
        <div style="font-size:1.7rem;font-weight:700;color:<?= $sc['color'] ?>;">
            <?= isset($sc['raw']) ? $sc['val'] : number_format((int)$sc['val']) ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Campaign List -->
<?php if (!$brandId): ?>
<div class="glass-card" style="text-align:center;padding:4rem 2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">🏢</div>
    <h3 style="font-size:1rem;margin-bottom:0.5rem;color:var(--text-secondary);">No brand set up yet</h3>
    <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.25rem;">Create a brand workspace first.</p>
    <a href="/brands/create" class="btn btn-primary">Create Brand</a>
</div>

<?php elseif (empty($campaigns)): ?>
<div class="glass-card" style="text-align:center;padding:4rem 2rem;">
    <div style="font-size:4rem;margin-bottom:1rem;">🎯</div>
    <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:0.5rem;color:var(--text-secondary);">No campaigns yet</h2>
    <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.5rem;max-width:380px;margin-left:auto;margin-right:auto;">
        Create your first campaign to start organizing your content, tracking performance, and managing budgets across platforms.
    </p>
    <button class="btn btn-primary" onclick="openCreateCampaignModal()">🚀 Create First Campaign</button>
</div>

<?php else: ?>
<div style="display:flex;flex-direction:column;gap:1rem;">
    <?php foreach ($campaigns as $c):
        $badge = $statusBadgeMap[$c['status']] ?? '';
        $goals = $c['goals'] ?? [];
        $goalList = is_array($goals) ? $goals : [];

        // Calculate progress based on dates
        $now       = time();
        $start     = !empty($c['start_date']) ? strtotime($c['start_date']) : $now;
        $end       = !empty($c['end_date'])   ? strtotime($c['end_date'])   : $now;
        $duration  = max(1, $end - $start);
        $elapsed   = $now - $start;
        $progress  = $c['status'] === 'completed' ? 100 : min(100, max(0, (int)round($elapsed / $duration * 100)));
        $progress  = $c['status'] === 'draft'     ? 0   : $progress;
    ?>
    <div class="glass-card" style="padding:1.25rem;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:0.75rem;">
            <div>
                <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.3rem;"><?= htmlspecialchars($c['name']) ?></h3>
                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                    <span class="badge <?= $badge ?>">
                        <span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block;margin-right:4px;"></span>
                        <?= ucfirst(htmlspecialchars($c['status'])) ?>
                    </span>
                    <?php if (!empty($goalList)): ?>
                    <?php foreach (array_slice($goalList, 0, 2) as $g): ?>
                    <span class="badge" style="font-size:0.68rem;"><?= htmlspecialchars(is_string($g) ? $g : (string)($g['label'] ?? $g['goal'] ?? '')) ?></span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;flex-shrink:0;">
                <button class="btn btn-ghost btn-sm" onclick="if(window.SociAI)SociAI.showToast('Campaign editor coming soon','info')">✏️ Edit</button>
                <a href="/dashboard/analytics" class="btn btn-ghost btn-sm">📊 Analytics</a>
                <?php if ($c['status'] === 'active'): ?>
                <button class="btn btn-sm" style="background:rgba(245,158,11,.15);color:var(--yellow);border:1px solid rgba(245,158,11,.2);" onclick="updateCampaignStatus('<?= htmlspecialchars((string)$c['id']) ?>','paused')">⏸ Pause</button>
                <?php elseif ($c['status'] === 'paused'): ?>
                <button class="btn btn-sm btn-primary" onclick="updateCampaignStatus('<?= htmlspecialchars((string)$c['id']) ?>','active')">▶ Resume</button>
                <?php elseif ($c['status'] === 'draft'): ?>
                <button class="btn btn-sm btn-primary" onclick="updateCampaignStatus('<?= htmlspecialchars((string)$c['id']) ?>','active')">🚀 Launch</button>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-bottom:0.85rem;font-size:0.8rem;color:var(--text-muted);">
            <?php if (!empty($c['start_date']) || !empty($c['end_date'])): ?>
            <span>📅 <?= $c['start_date'] ? date('M j, Y', strtotime($c['start_date'])) : '—' ?> → <?= $c['end_date'] ? date('M j, Y', strtotime($c['end_date'])) : '—' ?></span>
            <?php endif; ?>
            <?php if (!empty($c['budget'])): ?>
            <span>💰 Budget: <?= campFormatBudget((float)$c['budget']) ?></span>
            <?php endif; ?>
            <span>🗓 Created: <?= date('M j, Y', strtotime($c['created_at'])) ?></span>
        </div>

        <!-- Progress bar -->
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="flex:1;height:6px;background:var(--glass-bg);border-radius:3px;overflow:hidden;">
                <?php
                $barColor = match($c['status']) {
                    'active'    => 'linear-gradient(90deg,var(--blue),var(--purple))',
                    'completed' => 'linear-gradient(90deg,var(--green),var(--cyan))',
                    'paused'    => 'var(--yellow)',
                    default     => 'var(--glass-border)',
                };
                ?>
                <div style="width:<?= $progress ?>%;height:100%;background:<?= $barColor ?>;border-radius:3px;transition:width 0.4s;"></div>
            </div>
            <span style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;min-width:55px;"><?= $progress ?>% done</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Create Campaign Modal -->
<div id="createCampaignModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;">
    <div class="glass-card" style="width:100%;max-width:600px;margin:1rem;padding:0;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3>🚀 Create New Campaign</h3>
            <button class="modal-close" onclick="closeCreateCampaignModal()">×</button>
        </div>
        <div style="padding:1.25rem;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group">
                    <label class="form-label">Campaign Name</label>
                    <input type="text" class="form-input" id="campName" placeholder="e.g. Summer Sale 2025">
                </div>
                <div class="form-group">
                    <label class="form-label">Budget (USD)</label>
                    <input type="number" class="form-input" id="campBudget" placeholder="5000" min="0">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-input" id="campStart">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-input" id="campEnd">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Goals (comma-separated)</label>
                <input type="text" class="form-input" id="campGoals" placeholder="Brand Awareness, Lead Generation, Conversions">
            </div>
            <div class="form-group">
                <label class="form-label">Campaign Brief</label>
                <textarea class="form-textarea" id="campBrief" rows="3" placeholder="Describe your campaign objectives, target audience, key messages…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeCreateCampaignModal()">Cancel</button>
            <button class="btn btn-ghost" onclick="saveCampaign('draft')">💾 Save Draft</button>
            <button class="btn btn-primary" onclick="saveCampaign('active')">🚀 Launch Campaign</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:none;">
    <div id="toastMsg" style="background:var(--navy-mid,#1e1e3f);border:1px solid var(--glass-border);border-left:3px solid var(--green-light);border-radius:var(--radius-md);padding:.75rem 1.25rem;font-size:.85rem;box-shadow:0 4px 24px rgba(0,0,0,.4);"></div>
</div>

<script>
const CSRF = <?= json_encode($csrf ?? '') ?>;

function showToast(msg, ok = true) {
    const m = document.getElementById('toastMsg');
    if (!m) return;
    m.textContent = msg;
    m.style.borderLeftColor = ok ? 'var(--green-light)' : '#f87171';
    const t = document.getElementById('toast');
    t.style.display = 'block';
    clearTimeout(t._tid);
    t._tid = setTimeout(() => t.style.display = 'none', 4000);
}

async function apiPost(url, data) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(data),
    });
    return r.json();
}

function openCreateCampaignModal()  { document.getElementById('createCampaignModal').style.display = 'flex'; }
function closeCreateCampaignModal() { document.getElementById('createCampaignModal').style.display = 'none'; }

document.getElementById('createCampaignModal').addEventListener('click', function(e) {
    if (e.target === this) closeCreateCampaignModal();
});

async function saveCampaign(status) {
    const name   = document.getElementById('campName').value.trim();
    const budget = document.getElementById('campBudget').value;
    const start  = document.getElementById('campStart').value;
    const end    = document.getElementById('campEnd').value;
    const goals  = document.getElementById('campGoals').value.trim().split(',').map(s => s.trim()).filter(Boolean);

    if (!name) { showToast('Campaign name is required', false); return; }

    const d = await apiPost('/api/campaigns/create', {
        name, status, budget: parseFloat(budget) || 0,
        start_date: start, end_date: end, goals,
    });
    closeCreateCampaignModal();
    showToast(d.success ? (status === 'draft' ? '💾 Saved as draft!' : '🚀 Campaign launched!') : (d.error || 'Failed'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 1000);
}

async function updateCampaignStatus(id, status) {
    const d = await apiPost('/api/campaigns/update-status', { id, status });
    showToast(d.success ? 'Campaign updated!' : (d.error || 'Failed'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 500);
}
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
