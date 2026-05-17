<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::content()
// $brand, $brandId, $contentStats, $contentPieces, $connectedPlatforms,
// $filterStatus, $page, $perPage, $total, $totalPages, $csrf

$statusColors = [
    'draft'     => ['bg' => 'rgba(107,114,128,.15)', 'color' => '#9ca3af'],
    'pending'   => ['bg' => 'rgba(245,158,11,.15)',  'color' => '#fbbf24'],
    'approved'  => ['bg' => 'rgba(59,130,246,.15)',  'color' => '#60a5fa'],
    'published' => ['bg' => 'rgba(16,185,129,.15)',  'color' => '#34d399'],
    'rejected'  => ['bg' => 'rgba(239,68,68,.15)',   'color' => '#f87171'],
];

$validStatuses  = ['draft', 'pending', 'approved', 'published', 'rejected'];
$validPlatforms = ['instagram', 'twitter', 'linkedin', 'facebook', 'tiktok', 'youtube'];
?>
<style>
.content-grid-cols { display: grid; grid-template-columns: repeat(auto-fill,minmax(320px,1fr)); gap: 1rem; }
.content-card {
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  border-radius: var(--radius-md); padding: 1rem;
  transition: border-color var(--tr), transform var(--tr);
}
.content-card:hover { border-color: rgba(255,255,255,0.15); transform: translateY(-1px); }
.status-pill {
  display: inline-flex; align-items: center; padding: 2px 9px;
  border-radius: 99px; font-size: 0.7rem; font-weight: 600;
}
.filter-bar { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: 1rem; }
.filter-btn {
  padding: .38rem .85rem; border-radius: 99px; font-size: .78rem; font-weight: 500;
  border: 1px solid var(--glass-border); background: var(--glass-bg);
  color: var(--text-secondary); cursor: pointer; text-decoration: none;
  transition: all var(--tr);
}
.filter-btn:hover { background: var(--glass-bg-hover); color: var(--text-primary); }
.filter-btn.active { background: var(--gradient-primary); color: #fff; border-color: transparent; }
.gen-panel {
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;
}
.c-modal { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center; }
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">Content Hub 📋</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Create, schedule, and publish AI-powered content across all platforms</p>
    </div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="/dashboard/analytics" class="btn btn-ghost">📊 Analytics</a>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create Content
        </button>
    </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <?php
    $statItems = [
        ['key' => 'total',     'label' => 'Total',     'color' => 'var(--text-primary)'],
        ['key' => 'draft',     'label' => 'Draft',     'color' => 'var(--text-muted)'],
        ['key' => 'pending',   'label' => 'Pending',   'color' => 'var(--yellow)'],
        ['key' => 'approved',  'label' => 'Approved',  'color' => 'var(--blue-light)'],
        ['key' => 'published', 'label' => 'Published', 'color' => 'var(--green-light)'],
    ];
    foreach ($statItems as $si):
        $val = (int)($contentStats[$si['key']] ?? 0);
    ?>
    <div class="glass-card" style="padding:1rem;text-align:center;">
        <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;"><?= $si['label'] ?></div>
        <div style="font-size:1.8rem;font-weight:700;color:<?= $si['color'] ?>;"><?= number_format($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($brandId): ?>
<!-- Quick AI Generator -->
<div class="gen-panel">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
        <div style="width:36px;height:36px;border-radius:10px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;font-size:1.1rem;">✨</div>
        <div>
            <div style="font-weight:600;font-size:0.9rem;">Quick AI Content Generator</div>
            <div style="font-size:.78rem;color:var(--text-muted);">Generate captions, hashtags, and ideas with AI</div>
        </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.75rem;align-items:end;">
        <div>
            <label class="form-label">Topic / Brief</label>
            <input type="text" class="form-input" id="genTopic" placeholder="e.g. Product launch, tips, announcement…">
        </div>
        <div>
            <label class="form-label">Platform</label>
            <select class="form-select" id="genPlatform">
                <option value="instagram">Instagram</option>
                <option value="linkedin">LinkedIn</option>
                <option value="twitter">Twitter/X</option>
                <option value="tiktok">TikTok</option>
                <option value="facebook">Facebook</option>
                <option value="youtube">YouTube</option>
            </select>
        </div>
        <div>
            <label class="form-label">Tone</label>
            <select class="form-select" id="genTone">
                <option value="professional">Professional</option>
                <option value="casual">Casual</option>
                <option value="energetic">Energetic</option>
                <option value="educational">Educational</option>
                <option value="humorous">Humorous</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="generateContent()" id="genBtn">Generate</button>
    </div>
    <div id="genResult" style="display:none;margin-top:1rem;padding:1rem;background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.2);border-radius:var(--radius-sm);">
        <div style="font-size:.72rem;font-weight:600;color:var(--blue-light);margin-bottom:.5rem;">✨ Generated Caption</div>
        <textarea id="genCaption" class="form-textarea" rows="4" style="margin-bottom:.5rem;"></textarea>
        <div style="font-size:.72rem;font-weight:600;color:var(--blue-light);margin-bottom:.35rem;">Hashtags</div>
        <div id="genHashtags" style="font-size:.8rem;color:var(--text-secondary);margin-bottom:.75rem;"></div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <button class="btn btn-primary btn-sm" onclick="saveGenerated()">💾 Save as Draft</button>
            <button class="btn btn-ghost btn-sm" onclick="generateContent()">↻ Regenerate</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="filter-bar">
    <?php foreach (['all' => 'All', 'draft' => 'Drafts', 'pending' => 'Pending', 'approved' => 'Approved', 'published' => 'Published', 'rejected' => 'Rejected'] as $sv => $sl): ?>
    <a href="?status=<?= $sv ?>" class="filter-btn <?= $filterStatus === $sv ? 'active' : '' ?>"><?= $sl ?><?php if ($sv !== 'all' && isset($contentStats[$sv])): ?> <span style="opacity:.7;">(<?= (int)$contentStats[$sv] ?>)</span><?php endif; ?></a>
    <?php endforeach; ?>
</div>

<!-- Content Cards -->
<?php if (empty($contentPieces)): ?>
<div style="text-align:center;padding:4rem 1rem;color:var(--text-muted);">
    <div style="font-size:3.5rem;margin-bottom:1rem;">📝</div>
    <h3 style="font-size:1.1rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;">
        <?= $filterStatus !== 'all' ? 'No ' . $filterStatus . ' content' : 'No content yet' ?>
    </h3>
    <p style="margin-bottom:1.5rem;font-size:0.875rem;">
        <?= $filterStatus !== 'all' ? 'Try a different filter or create new content.' : 'Generate AI-powered content tailored to your brand.' ?>
    </p>
    <?php if (!$brandId): ?>
    <a href="/brands/create" class="btn btn-primary">Create Your Brand First</a>
    <?php else: ?>
    <button class="btn btn-primary" onclick="openCreateModal()">✨ Generate with AI</button>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="content-grid-cols">
<?php foreach ($contentPieces as $post):
    $sc          = $statusColors[$post['approval_status']] ?? $statusColors['draft'];
    $previewText = mb_substr(strip_tags($post['body_text'] ?? ''), 0, 120);
    $tags        = array_slice($post['hashtags'] ?? [], 0, 4);
    $title       = $post['title'] ?? mb_substr($post['body_text'] ?? '', 0, 60) ?: '(Untitled)';
?>
<div class="content-card" id="cc<?= htmlspecialchars((string)$post['id']) ?>">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem;margin-bottom:.75rem;">
        <div style="flex:1;min-width:0;">
            <div style="font-weight:600;font-size:.88rem;margin-bottom:.25rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($title) ?>">
                <?= htmlspecialchars($title) ?>
            </div>
            <div style="font-size:.72rem;color:var(--text-muted);">
                <?= ucfirst(htmlspecialchars($post['content_type'] ?? 'post')) ?>
                <?php if ($post['ai_generated']): ?> · ✨ AI Generated<?php endif; ?>
                · <?= date('M j, Y', strtotime($post['created_at'])) ?>
            </div>
        </div>
        <span class="status-pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;flex-shrink:0;">
            <?= ucfirst(htmlspecialchars($post['approval_status'] ?? 'draft')) ?>
        </span>
    </div>

    <?php if ($previewText): ?>
    <p style="font-size:.82rem;color:var(--text-secondary);line-height:1.5;margin-bottom:.6rem;">
        <?= htmlspecialchars($previewText) ?><?= mb_strlen($post['body_text'] ?? '') > 120 ? '…' : '' ?>
    </p>
    <?php endif; ?>

    <?php if (!empty($tags)): ?>
    <div style="margin-bottom:.6rem;display:flex;gap:.3rem;flex-wrap:wrap;">
        <?php foreach ($tags as $tag): ?>
        <span style="font-size:.68rem;color:var(--blue-light);background:rgba(59,130,246,.1);border-radius:4px;padding:1px 6px;">#<?= htmlspecialchars(ltrim((string)$tag, '#')) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($post['viral_score']) && (int)$post['viral_score'] > 0): ?>
    <div style="display:flex;align-items:center;gap:0.4rem;margin-bottom:.6rem;">
        <span style="font-size:.7rem;color:var(--text-muted);">Viral Score:</span>
        <?php $vs = (int)$post['viral_score']; $vc = $vs >= 85 ? 'var(--green-light)' : ($vs >= 70 ? 'var(--yellow)' : 'var(--text-secondary)'); ?>
        <span style="font-size:.78rem;font-weight:700;color:<?= $vc ?>;"><?= $vs ?>/100</span>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
        <?php if (in_array($post['approval_status'], ['draft', 'pending'], true)): ?>
        <button class="btn btn-sm btn-primary" onclick="approveContent('<?= htmlspecialchars((string)$post['id']) ?>')">✓ Approve</button>
        <?php endif; ?>
        <?php if ($post['approval_status'] === 'approved'): ?>
        <button class="btn btn-sm btn-primary" onclick="openPublishModal('<?= htmlspecialchars((string)$post['id']) ?>')">🚀 Publish</button>
        <?php endif; ?>
        <button class="btn btn-sm btn-ghost" onclick="openScheduleModal('<?= htmlspecialchars((string)$post['id']) ?>')">📅 Schedule</button>
        <?php if ($post['approval_status'] !== 'published'): ?>
        <button class="btn btn-sm btn-ghost" style="color:var(--red);" onclick="deleteContent('<?= htmlspecialchars((string)$post['id']) ?>')">✕</button>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap;">
    <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
    <a href="?status=<?= urlencode($filterStatus) ?>&page=<?= $pg ?>"
       class="btn btn-sm <?= $pg === $page ? 'btn-primary' : 'btn-ghost' ?>"><?= $pg ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Create Content Modal -->
<div class="c-modal" id="createModal">
    <div class="glass-card" style="width:100%;max-width:600px;margin:1rem;padding:0;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3>✏️ Create Content</h3>
            <button class="modal-close" onclick="closeCreateModal()">×</button>
        </div>
        <div style="padding:1.25rem;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group">
                    <label class="form-label">Content Type</label>
                    <select class="form-select" id="cmType">
                        <?php foreach (['post', 'story', 'reel', 'thread', 'carousel', 'article'] as $ct): ?>
                        <option value="<?= $ct ?>"><?= ucfirst($ct) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="cmStatus">
                        <option value="draft">Draft</option>
                        <option value="pending">Pending Review</option>
                        <option value="approved">Approved</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" class="form-input" id="cmTitle" placeholder="Content title…">
            </div>
            <div class="form-group">
                <label class="form-label">Body Text</label>
                <textarea class="form-textarea" id="cmBody" rows="5" placeholder="Write your content here, or use AI to generate…"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Hashtags (space-separated)</label>
                <input type="text" class="form-input" id="cmHashtags" placeholder="#tag1 #tag2 #tag3">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeCreateModal()">Cancel</button>
            <button class="btn btn-ghost" onclick="aiGenerateForModal()">✨ AI Fill</button>
            <button class="btn btn-primary" onclick="saveContent()">Save Content</button>
        </div>
    </div>
</div>

<!-- Publish Modal -->
<div class="c-modal" id="publishModal">
    <div class="glass-card" style="width:100%;max-width:480px;margin:1rem;padding:0;">
        <div class="modal-header"><h3>🚀 Publish Now</h3><button class="modal-close" onclick="closePublishModal()">×</button></div>
        <div style="padding:1.25rem;">
            <input type="hidden" id="publishContentId">
            <div class="form-group">
                <label class="form-label">Select Platform Account</label>
                <select class="form-select" id="publishAccount">
                    <?php foreach ($connectedPlatforms as $cp): ?>
                    <option value="<?= htmlspecialchars((string)$cp['id']) ?>"><?= ucfirst(htmlspecialchars($cp['platform'])) ?> — <?= htmlspecialchars($cp['account_name']) ?></option>
                    <?php endforeach; ?>
                    <?php if (empty($connectedPlatforms)): ?>
                    <option value="">No connected platforms</option>
                    <?php endif; ?>
                </select>
            </div>
            <?php if (empty($connectedPlatforms)): ?>
            <div style="padding:.75rem;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:var(--radius-sm);font-size:.82rem;color:var(--yellow);">
                ⚠️ No platforms connected. <a href="/dashboard/settings" style="color:var(--blue-light);">Go to Settings → Platforms</a> to connect your accounts.
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closePublishModal()">Cancel</button>
            <button class="btn btn-primary" onclick="publishNow()" <?= empty($connectedPlatforms) ? 'disabled' : '' ?>>🚀 Publish Now</button>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="c-modal" id="scheduleModal">
    <div class="glass-card" style="width:100%;max-width:440px;margin:1rem;padding:0;">
        <div class="modal-header"><h3>📅 Schedule Post</h3><button class="modal-close" onclick="closeScheduleModal()">×</button></div>
        <div style="padding:1.25rem;">
            <input type="hidden" id="schedContentId">
            <div class="form-group">
                <label class="form-label">Schedule Date &amp; Time</label>
                <input type="datetime-local" class="form-input" id="schedDate">
            </div>
            <?php if (!empty($connectedPlatforms)): ?>
            <div class="form-group">
                <label class="form-label">Platform Account</label>
                <select class="form-select" id="schedAccount">
                    <?php foreach ($connectedPlatforms as $cp): ?>
                    <option value="<?= htmlspecialchars((string)$cp['id']) ?>"><?= ucfirst(htmlspecialchars($cp['platform'])) ?> — <?= htmlspecialchars($cp['account_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeScheduleModal()">Cancel</button>
            <button class="btn btn-primary" onclick="scheduleNow()">📅 Schedule</button>
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

async function generateContent() {
    const topic    = document.getElementById('genTopic').value.trim();
    const platform = document.getElementById('genPlatform').value;
    const tone     = document.getElementById('genTone').value;
    if (!topic) { showToast('Enter a topic first', false); return; }
    const btn = document.getElementById('genBtn');
    btn.disabled = true; btn.textContent = '⏳ Generating…';
    try {
        const d = await apiPost('/api/content/generate', { topic, platform, tone, content_type: 'post' });
        if (d.success) {
            document.getElementById('genCaption').value = d.caption || '';
            document.getElementById('genHashtags').textContent = (d.hashtags || []).map(h => '#' + h).join(' ');
            document.getElementById('genResult').style.display = 'block';
        } else { showToast(d.error || 'Generation failed', false); }
    } catch(e) { showToast('Error: ' + e.message, false); }
    btn.disabled = false; btn.textContent = 'Generate';
}

async function saveGenerated() {
    const body  = document.getElementById('genCaption').value.trim();
    const topic = document.getElementById('genTopic').value.trim();
    const tags  = document.getElementById('genHashtags').textContent;
    if (!body) { showToast('No content to save', false); return; }
    const d = await apiPost('/api/content/list', {
        title: topic || body.slice(0, 60),
        body_text: body, hashtags: tags, content_type: 'post',
        approval_status: 'draft', ai_generated: 1,
    });
    showToast(d.success ? 'Saved as draft!' : (d.error || 'Failed'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 1000);
}

function openCreateModal()   { document.getElementById('createModal').style.display  = 'flex'; }
function closeCreateModal()  { document.getElementById('createModal').style.display  = 'none'; }
function openPublishModal(id){ document.getElementById('publishContentId').value = id; document.getElementById('publishModal').style.display = 'flex'; }
function closePublishModal() { document.getElementById('publishModal').style.display = 'none'; }
function openScheduleModal(id){ document.getElementById('schedContentId').value = id; document.getElementById('scheduleModal').style.display = 'flex'; }
function closeScheduleModal() { document.getElementById('scheduleModal').style.display = 'none'; }

['createModal','publishModal','scheduleModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});

async function aiGenerateForModal() {
    const topic    = document.getElementById('cmTitle').value.trim();
    const type     = document.getElementById('cmType').value;
    if (!topic) { showToast('Enter a title/topic first', false); return; }
    const d = await apiPost('/api/content/generate', { topic, platform: 'instagram', tone: 'professional', content_type: type });
    if (d.success) {
        document.getElementById('cmBody').value     = d.caption || '';
        document.getElementById('cmHashtags').value = (d.hashtags || []).map(h => '#' + h).join(' ');
        showToast('AI content generated!');
    } else { showToast(d.error || 'Failed', false); }
}

async function saveContent() {
    const title  = document.getElementById('cmTitle').value.trim();
    const body   = document.getElementById('cmBody').value.trim();
    const tags   = document.getElementById('cmHashtags').value.trim();
    const type   = document.getElementById('cmType').value;
    const status = document.getElementById('cmStatus').value;
    if (!body && !title) { showToast('Enter a title or body text', false); return; }
    const d = await apiPost('/api/content/list', {
        title: title || body.slice(0, 60),
        body_text: body, hashtags: tags,
        content_type: type, approval_status: status,
    });
    showToast(d.success ? 'Content saved!' : (d.error || 'Failed'), d.success !== false);
    if (d.success) { closeCreateModal(); setTimeout(() => location.reload(), 800); }
}

async function approveContent(id) {
    const d = await apiPost('/api/content/approve', { id });
    showToast(d.success ? 'Approved!' : (d.error || 'Failed'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 500);
}

async function deleteContent(id) {
    if (!confirm('Delete this content piece?')) return;
    const d = await apiPost('/api/content/reject', { id });
    showToast(d.success ? 'Deleted' : 'Failed', d.success !== false);
    if (d.success) { const c = document.getElementById('cc' + id); if (c) c.remove(); }
}

async function publishNow() {
    const id      = document.getElementById('publishContentId').value;
    const account = document.getElementById('publishAccount').value;
    if (!account) { showToast('Select a platform account', false); return; }
    const d = await apiPost('/api/content/publish/' + id, { platform_account_id: account, id });
    closePublishModal();
    showToast(d.success ? '🚀 Published!' : (d.error || 'Publish failed'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 1000);
}

async function scheduleNow() {
    const id      = document.getElementById('schedContentId').value;
    const dt      = document.getElementById('schedDate').value;
    const accEl   = document.getElementById('schedAccount');
    const account = accEl ? accEl.value : '';
    if (!dt) { showToast('Select a date and time', false); return; }
    const d = await apiPost('/api/content/schedule', { id, scheduled_at: dt, platform_account_id: account });
    closeScheduleModal();
    showToast(d.success ? '📅 Scheduled!' : (d.error || 'Failed'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 800);
}

// Set min date for schedule picker
const schedDateEl = document.getElementById('schedDate');
if (schedDateEl) {
    const now = new Date();
    now.setMinutes(now.getMinutes() + 30);
    schedDateEl.min = now.toISOString().slice(0, 16);
}
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
