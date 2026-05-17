<?php
/**
 * SociAI OS — Content Hub Dashboard
 * Real content_pieces data with AI generation and platform publishing.
 */

declare(strict_types=1);

use SociAI\Core\{Auth, Database};

Auth::requireAuth();
$user = Auth::getCurrentUser();
$db   = Database::getInstance();

$brandId = $_SESSION['active_brand_id'] ?? '';
if (empty($brandId)) {
    $row = $db->fetchOne(
        "SELECT b.id FROM brands b
         INNER JOIN team_members tm ON tm.brand_id = b.id
         WHERE tm.user_id = ? ORDER BY tm.created_at ASC LIMIT 1",
        [$user['id']]
    );
    $brandId = $row['id'] ?? '';
    if ($brandId) $_SESSION['active_brand_id'] = $brandId;
}

$csrf = Auth::csrfToken();

// Stats
$statsRow = $db->fetchOne(
    "SELECT
        COUNT(*) AS total,
        SUM(approval_status = 'draft') AS drafts,
        SUM(approval_status = 'pending') AS pending,
        SUM(approval_status = 'approved') AS approved,
        SUM(approval_status = 'published') AS published,
        SUM(approval_status = 'rejected') AS rejected,
        ROUND(AVG(viral_score), 1) AS avg_viral_score
     FROM content_pieces WHERE brand_id = ?",
    [$brandId]
) ?: ['total'=>0,'drafts'=>0,'pending'=>0,'approved'=>0,'published'=>0,'rejected'=>0,'avg_viral_score'=>null];

// Filters
$filterStatus   = $_GET['status']   ?? 'all';
$filterPlatform = $_GET['platform'] ?? 'all';
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 15;

$validStatuses  = ['draft', 'pending', 'approved', 'published', 'rejected'];
$validPlatforms = ['instagram', 'twitter', 'linkedin', 'facebook', 'tiktok', 'youtube'];

$where  = ['cp.brand_id = ?'];
$params = [$brandId];

if ($filterStatus !== 'all' && in_array($filterStatus, $validStatuses, true)) {
    $where[]  = 'cp.approval_status = ?';
    $params[] = $filterStatus;
}

$wc     = implode(' AND ', $where);
$offset = ($page - 1) * $perPage;
$total  = (int)$db->fetchColumn("SELECT COUNT(*) FROM content_pieces cp WHERE {$wc}", $params);
$posts  = $db->fetchAll(
    "SELECT cp.id, cp.content_type, cp.topic, cp.body_text, cp.approval_status,
            cp.language, cp.viral_score, cp.created_at, cp.media_urls, cp.hashtags,
            cp.ai_generated, cp.approved_at,
            u.full_name AS created_by_name
     FROM content_pieces cp
     LEFT JOIN users u ON u.id = cp.created_by
     WHERE {$wc}
     ORDER BY cp.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);
$totalPages = max(1, (int)ceil($total / $perPage));

foreach ($posts as &$p) {
    $p['media_urls'] = json_decode($p['media_urls'] ?? '[]', true) ?: [];
    $p['hashtags']   = json_decode($p['hashtags']   ?? '[]', true) ?: [];
}
unset($p);

// Connected platforms for publish modal
$connectedPlatforms = $db->fetchAll(
    "SELECT id, platform, account_name FROM platform_accounts WHERE brand_id = ? AND is_active = 1 ORDER BY platform",
    [$brandId]
);

$statusColors = [
    'draft'     => ['bg' => 'rgba(107,114,128,.15)', 'color' => '#9ca3af'],
    'pending'   => ['bg' => 'rgba(245,158,11,.15)',  'color' => '#fbbf24'],
    'approved'  => ['bg' => 'rgba(59,130,246,.15)',  'color' => '#60a5fa'],
    'published' => ['bg' => 'rgba(16,185,129,.15)',  'color' => '#34d399'],
    'rejected'  => ['bg' => 'rgba(239,68,68,.15)',   'color' => '#f87171'],
];

$platformEmojis = [
    'instagram' => '📸', 'twitter' => '𝕏', 'linkedin' => '💼',
    'facebook' => 'f', 'tiktok' => '♪', 'youtube' => '▶',
];

$pageTitle  = 'Content Hub';
$activePage = 'content';
?>
<?php ob_start(); ?>

<style>
.content-grid-cols { display: grid; grid-template-columns: repeat(auto-fill,minmax(320px,1fr)); gap: 1rem; }
.content-card {
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  border-radius: var(--radius-md); padding: 1rem;
  transition: border-color var(--transition), transform var(--transition);
}
.content-card:hover { border-color: var(--glass-border-hover); transform: translateY(-1px); }
.status-pill {
  display: inline-flex; align-items: center; padding: 2px 9px;
  border-radius: 99px; font-size: 0.7rem; font-weight: 600;
}
.filter-bar { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: 1rem; }
.filter-btn {
  padding: .38rem .85rem; border-radius: 99px; font-size: .78rem; font-weight: 500;
  border: 1px solid var(--glass-border); background: var(--glass-bg);
  color: var(--text-secondary); cursor: pointer; text-decoration: none;
  transition: all var(--transition);
}
.filter-btn:hover { background: var(--glass-bg-hover); color: var(--text-primary); }
.filter-btn.active { background: var(--gradient-primary); color: #fff; border-color: transparent; }
.gen-panel {
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;
}
.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center; }
</style>

<!-- Header -->
<div class="page-header page-header-row" style="margin-bottom:1.5rem">
  <div>
    <h1>Content Hub 📋</h1>
    <p>Create, schedule, and publish AI-powered content across all platforms</p>
  </div>
  <div style="display:flex;gap:.75rem">
    <a href="/brands/default/calendar" class="btn btn-ghost">📅 Calendar</a>
    <button class="btn btn-primary" onclick="openCreateModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Create Content
    </button>
  </div>
</div>

<!-- Stats -->
<div class="dashboard-grid grid-cols-5" style="margin-bottom:1.5rem">
  <div class="metric-card"><div class="metric-label">Total</div><div class="metric-value"><?= number_format((int)$statsRow['total']) ?></div></div>
  <div class="metric-card"><div class="metric-label">Draft</div><div class="metric-value" style="font-size:1.6rem"><?= number_format((int)$statsRow['drafts']) ?></div></div>
  <div class="metric-card"><div class="metric-label">Pending</div><div class="metric-value" style="font-size:1.6rem;color:var(--yellow)"><?= number_format((int)$statsRow['pending']) ?></div></div>
  <div class="metric-card"><div class="metric-label">Published</div><div class="metric-value" style="font-size:1.6rem;color:var(--green-light)"><?= number_format((int)$statsRow['published']) ?></div></div>
  <div class="metric-card"><div class="metric-label">Avg Viral Score</div><div class="metric-value" style="font-size:1.6rem"><?= $statsRow['avg_viral_score'] ?? 'N/A' ?></div></div>
</div>

<!-- Quick AI Generator -->
<div class="gen-panel">
  <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
    <div style="width:36px;height:36px;border-radius:10px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;font-size:1.1rem">✨</div>
    <div>
      <div style="font-weight:600">Quick AI Content Generator</div>
      <div style="font-size:.78rem;color:var(--text-muted)">Generate captions, hashtags, and images with AI</div>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.75rem;align-items:end">
    <div class="form-group" style="margin:0">
      <label class="form-label">Topic / Brief</label>
      <input type="text" class="form-input" id="genTopic" placeholder="e.g. Product launch, tips, announcement…">
    </div>
    <div class="form-group" style="margin:0">
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
    <div class="form-group" style="margin:0">
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
  <div id="genResult" style="display:none;margin-top:1rem;padding:1rem;background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.2);border-radius:var(--radius-sm)">
    <div style="font-size:.72rem;font-weight:600;color:var(--blue-light);margin-bottom:.5rem">✨ Generated Caption</div>
    <textarea id="genCaption" class="form-textarea" rows="4" style="margin-bottom:.5rem"></textarea>
    <div style="font-size:.72rem;font-weight:600;color:var(--blue-light);margin-bottom:.35rem">Hashtags</div>
    <div id="genHashtags" style="font-size:.8rem;color:var(--text-secondary);margin-bottom:.75rem"></div>
    <div style="display:flex;gap:.5rem">
      <button class="btn btn-primary btn-sm" onclick="saveGenerated()">💾 Save as Draft</button>
      <button class="btn btn-ghost btn-sm" onclick="generateContent()">↻ Regenerate</button>
      <button class="btn btn-ghost btn-sm" onclick="genImageFromCaption()">🎨 Generate Image</button>
    </div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
  <?php foreach (['all'=>'All','draft'=>'Drafts','pending'=>'Pending','approved'=>'Approved','published'=>'Published'] as $sv=>$sl): ?>
  <a href="?status=<?= $sv ?>&platform=<?= urlencode($filterPlatform) ?>" class="filter-btn <?= $filterStatus===$sv?'active':'' ?>"><?= $sl ?></a>
  <?php endforeach; ?>
</div>

<!-- Content Cards -->
<?php if (empty($posts)): ?>
<div style="text-align:center;padding:4rem 1rem;color:var(--text-muted)">
  <div style="font-size:3rem;margin-bottom:1rem">📝</div>
  <p style="margin-bottom:1rem">No content found. Use the generator above or create content manually.</p>
  <button class="btn btn-primary" onclick="openCreateModal()">+ Create First Content</button>
</div>
<?php else: ?>

<div class="content-grid-cols">
<?php foreach ($posts as $post):
  $sc = $statusColors[$post['approval_status']] ?? $statusColors['draft'];
  $previewText = mb_substr(strip_tags($post['body_text'] ?? $post['topic'] ?? ''), 0, 120);
  $tags = array_slice($post['hashtags'] ?? [], 0, 4);
?>
<div class="content-card" id="cc<?= htmlspecialchars($post['id']) ?>">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem;margin-bottom:.75rem">
    <div style="flex:1;min-width:0">
      <div style="font-weight:600;font-size:.88rem;margin-bottom:.25rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        <?= htmlspecialchars($post['topic'] ?: mb_substr($post['body_text']??'',0,60)) ?>
      </div>
      <div style="font-size:.72rem;color:var(--text-muted)">
        <?= ucfirst($post['content_type']??'post') ?> ·
        <?= $post['created_by_name'] ? htmlspecialchars($post['created_by_name']) : 'You' ?> ·
        <?= date('M j', strtotime($post['created_at']??'now')) ?>
        <?php if ($post['ai_generated']): ?> · ✨ AI<?php endif; ?>
      </div>
    </div>
    <span class="status-pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;flex-shrink:0">
      <?= ucfirst($post['approval_status']??'draft') ?>
    </span>
  </div>

  <?php if ($previewText): ?>
  <p style="font-size:.82rem;color:var(--text-secondary);line-height:1.5;margin-bottom:.6rem"><?= htmlspecialchars($previewText) ?>…</p>
  <?php endif; ?>

  <?php if (!empty($tags)): ?>
  <div style="margin-bottom:.6rem;display:flex;gap:.3rem;flex-wrap:wrap">
    <?php foreach ($tags as $tag): ?>
    <span style="font-size:.68rem;color:var(--blue-light);background:rgba(59,130,246,.1);border-radius:4px;padding:1px 6px">#<?= htmlspecialchars(ltrim($tag,'#')) ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($post['media_urls'][0])): ?>
  <div style="margin-bottom:.75rem;border-radius:var(--radius-sm);overflow:hidden;max-height:120px">
    <img src="<?= htmlspecialchars($post['media_urls'][0]) ?>" alt="" style="width:100%;height:120px;object-fit:cover" onerror="this.remove()">
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:.4rem;flex-wrap:wrap">
    <?php if ($post['approval_status'] === 'draft' || $post['approval_status'] === 'pending'): ?>
    <button class="btn btn-sm btn-primary" onclick="approveContent('<?= $post['id'] ?>')">✓ Approve</button>
    <?php endif; ?>
    <?php if ($post['approval_status'] === 'approved'): ?>
    <button class="btn btn-sm btn-primary" onclick="openPublishModal('<?= htmlspecialchars($post['id']) ?>')">🚀 Publish</button>
    <?php endif; ?>
    <button class="btn btn-sm btn-ghost" onclick="openScheduleModal('<?= htmlspecialchars($post['id']) ?>')">📅 Schedule</button>
    <?php if ($post['approval_status'] !== 'published'): ?>
    <button class="btn btn-sm btn-ghost" style="color:var(--red)" onclick="deleteContent('<?= htmlspecialchars($post['id']) ?>')">✕</button>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap">
  <?php for ($pg=1;$pg<=$totalPages;$pg++): ?>
  <a href="?status=<?= urlencode($filterStatus)?>&platform=<?= urlencode($filterPlatform)?>&page=<?= $pg ?>"
     class="btn btn-sm <?= $pg===$page?'btn-primary':'btn-ghost'?>"><?= $pg ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Create Content Modal -->
<div class="modal-overlay" id="createModal">
  <div class="glass-card" style="width:100%;max-width:600px;margin:1rem;padding:0;max-height:90vh;overflow-y:auto">
    <div class="modal-header">
      <h3>✏️ Create Content</h3>
      <button class="modal-close" onclick="closeCreateModal()">×</button>
    </div>
    <div style="padding:1.25rem">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Platform</label>
          <select class="form-select" id="cmPlatform">
            <?php foreach ($validPlatforms as $pf): ?><option value="<?= $pf ?>"><?= ucfirst($pf) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Content Type</label>
          <select class="form-select" id="cmType">
            <?php foreach (['post','story','reel','thread','carousel','article'] as $ct): ?><option value="<?= $ct ?>"><?= ucfirst($ct) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Topic / Brief</label>
        <input type="text" class="form-input" id="cmTopic" placeholder="What is this content about?">
      </div>
      <div class="form-group">
        <label class="form-label">Caption / Body</label>
        <textarea class="form-textarea" id="cmBody" rows="5" placeholder="Write your content here, or use AI to generate…"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Hashtags</label>
        <input type="text" class="form-input" id="cmHashtags" placeholder="#tag1 #tag2 #tag3">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Language</label>
          <select class="form-select" id="cmLang"><option value="english">English</option><option value="arabic">Arabic</option><option value="mixed">Mixed</option></select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-select" id="cmStatus"><option value="draft">Draft</option><option value="pending">Pending Review</option><option value="approved">Approved</option></select>
        </div>
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
<div class="modal-overlay" id="publishModal">
  <div class="glass-card" style="width:100%;max-width:480px;margin:1rem;padding:0">
    <div class="modal-header"><h3>🚀 Publish Now</h3><button class="modal-close" onclick="closePublishModal()">×</button></div>
    <div style="padding:1.25rem">
      <input type="hidden" id="publishContentId">
      <div class="form-group">
        <label class="form-label">Select Platform Account</label>
        <select class="form-select" id="publishAccount">
          <?php foreach ($connectedPlatforms as $cp): ?>
          <option value="<?= htmlspecialchars($cp['id']) ?>"><?= ucfirst($cp['platform']) ?> — <?= htmlspecialchars($cp['account_name']) ?></option>
          <?php endforeach; ?>
          <?php if (empty($connectedPlatforms)): ?>
          <option value="">No connected platforms</option>
          <?php endif; ?>
        </select>
      </div>
      <?php if (empty($connectedPlatforms)): ?>
      <div style="padding:.75rem;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:var(--radius-sm);font-size:.82rem;color:var(--yellow)">
        ⚠️ No platforms connected. <a href="/dashboard/settings" style="color:var(--blue-light)">Go to Settings → Platforms</a> to connect your accounts.
      </div>
      <?php endif; ?>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closePublishModal()">Cancel</button>
      <button class="btn btn-primary" onclick="publishNow()" <?= empty($connectedPlatforms)?'disabled':'' ?>>🚀 Publish Now</button>
    </div>
  </div>
</div>

<!-- Schedule Modal -->
<div class="modal-overlay" id="scheduleModal">
  <div class="glass-card" style="width:100%;max-width:440px;margin:1rem;padding:0">
    <div class="modal-header"><h3>📅 Schedule Post</h3><button class="modal-close" onclick="closeScheduleModal()">×</button></div>
    <div style="padding:1.25rem">
      <input type="hidden" id="schedContentId">
      <div class="form-group">
        <label class="form-label">Schedule Date & Time</label>
        <input type="datetime-local" class="form-input" id="schedDate">
      </div>
      <div class="form-group">
        <label class="form-label">Platform Account</label>
        <select class="form-select" id="schedAccount">
          <?php foreach ($connectedPlatforms as $cp): ?>
          <option value="<?= htmlspecialchars($cp['id']) ?>"><?= ucfirst($cp['platform']) ?> — <?= htmlspecialchars($cp['account_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeScheduleModal()">Cancel</button>
      <button class="btn btn-primary" onclick="scheduleNow()">Schedule</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="toast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:none">
  <div id="toastMsg" style="background:var(--navy-mid);border:1px solid var(--glass-border);border-left:3px solid var(--green-light);border-radius:var(--radius-md);padding:.75rem 1.25rem;font-size:.85rem;box-shadow:var(--shadow-md)"></div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;

function showToast(msg, ok=true) {
  const m = document.getElementById('toastMsg');
  m.textContent = msg;
  m.style.borderLeftColor = ok ? 'var(--green-light)' : '#f87171';
  const t = document.getElementById('toast');
  t.style.display = 'block';
  clearTimeout(t._tid);
  t._tid = setTimeout(() => t.style.display='none', 4000);
}

async function apiPost(url, data) {
  const r = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify(data),
  });
  return r.json();
}

// AI Generate
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
      document.getElementById('genCaption').value  = d.caption || '';
      document.getElementById('genHashtags').textContent = (d.hashtags||[]).map(h=>'#'+h).join(' ');
      document.getElementById('genResult').style.display = 'block';
    } else {
      showToast(d.error || 'Generation failed', false);
    }
  } catch(e) { showToast('Error: ' + e.message, false); }

  btn.disabled = false; btn.textContent = 'Generate';
}

// Save generated content
async function saveGenerated() {
  const body     = document.getElementById('genCaption').value.trim();
  const topic    = document.getElementById('genTopic').value.trim();
  const hashtags = document.getElementById('genHashtags').textContent;
  const platform = document.getElementById('genPlatform').value;

  if (!body) { showToast('No content to save', false); return; }

  const d = await apiPost('/api/content/list', {
    body_text: body, topic, hashtags, content_type: 'post',
    language: 'english', approval_status: 'draft', ai_generated: 1,
    writing_style: document.getElementById('genTone').value,
  });
  showToast(d.success ? 'Saved as draft!' : (d.error||'Failed'), d.success !== false);
  if (d.success) setTimeout(() => location.reload(), 1000);
}

// Generate image from caption
async function genImageFromCaption() {
  const caption = document.getElementById('genCaption').value.trim();
  if (!caption) { showToast('Generate a caption first', false); return; }
  showToast('Generating image…');
  const d = await apiPost('/api/content/generate-image', { prompt: caption + ' — social media post, professional photography', size: '1024x1024' });
  if (d.success && d.url) {
    const img = document.createElement('img');
    img.src = d.url; img.style.cssText = 'width:100%;border-radius:8px;margin-top:.5rem';
    document.getElementById('genResult').appendChild(img);
    showToast('Image generated!');
  } else {
    showToast(d.error || 'Image generation failed', false);
  }
}

// Modal helpers
function openCreateModal() { document.getElementById('createModal').style.display='flex'; }
function closeCreateModal() { document.getElementById('createModal').style.display='none'; }
function openPublishModal(id) { document.getElementById('publishContentId').value=id; document.getElementById('publishModal').style.display='flex'; }
function closePublishModal() { document.getElementById('publishModal').style.display='none'; }
function openScheduleModal(id) { document.getElementById('schedContentId').value=id; document.getElementById('schedModal')?.style && (document.getElementById('scheduleModal').style.display='flex'); }
function closeScheduleModal() { document.getElementById('scheduleModal').style.display='none'; }

['createModal','publishModal','scheduleModal'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('click', function(e) { if(e.target===this) this.style.display='none'; });
});

// AI fill for create modal
async function aiGenerateForModal() {
  const topic    = document.getElementById('cmTopic').value.trim();
  const platform = document.getElementById('cmPlatform').value;
  if (!topic) { showToast('Enter a topic first', false); return; }
  const d = await apiPost('/api/content/generate', { topic, platform, tone: 'professional', content_type: document.getElementById('cmType').value });
  if (d.success) {
    document.getElementById('cmBody').value     = d.caption || '';
    document.getElementById('cmHashtags').value = (d.hashtags||[]).map(h=>'#'+h).join(' ');
    showToast('AI content generated!');
  } else showToast(d.error || 'Failed', false);
}

// Save content
async function saveContent() {
  const d = await apiPost('/api/content/list', {
    topic:           document.getElementById('cmTopic').value,
    body_text:       document.getElementById('cmBody').value,
    hashtags:        document.getElementById('cmHashtags').value,
    content_type:    document.getElementById('cmType').value,
    language:        document.getElementById('cmLang').value,
    approval_status: document.getElementById('cmStatus').value,
    writing_style:   'professional',
  });
  showToast(d.success ? 'Content saved!' : (d.error||'Failed'), d.success !== false);
  if (d.success) { closeCreateModal(); setTimeout(() => location.reload(), 800); }
}

// Approve content
async function approveContent(id) {
  const d = await apiPost('/api/content/approve', { id });
  showToast(d.success ? 'Approved!' : (d.error||'Failed'), d.success !== false);
  if (d.success) setTimeout(() => location.reload(), 500);
}

// Delete content
async function deleteContent(id) {
  if (!confirm('Delete this content?')) return;
  const d = await apiPost('/api/content/reject', { id });
  showToast(d.success ? 'Deleted' : 'Failed', d.success !== false);
  if (d.success) { const c = document.getElementById('cc'+id); if(c) c.remove(); }
}

// Publish now
async function publishNow() {
  const id      = document.getElementById('publishContentId').value;
  const account = document.getElementById('publishAccount').value;
  if (!account) { showToast('Select a platform account', false); return; }
  const d = await apiPost('/api/content/publish/' + id, { platform_account_id: account, id });
  closePublishModal();
  showToast(d.success ? '🚀 Published!' : (d.error||'Publish failed'), d.success !== false);
  if (d.success) setTimeout(() => location.reload(), 1000);
}

// Schedule
async function scheduleNow() {
  const id      = document.getElementById('schedContentId').value;
  const dt      = document.getElementById('schedDate').value;
  const account = document.getElementById('schedAccount').value;
  if (!dt) { showToast('Select a date and time', false); return; }
  const d = await apiPost('/api/content/schedule', { id, scheduled_at: dt, platform_account_id: account });
  closeScheduleModal();
  showToast(d.success ? '📅 Scheduled!' : (d.error||'Failed'), d.success !== false);
  if (d.success) setTimeout(() => location.reload(), 800);
}

// Set min date for schedule picker
const schedDate = document.getElementById('schedDate');
if (schedDate) {
  const now = new Date();
  now.setMinutes(now.getMinutes() + 30);
  schedDate.min = now.toISOString().slice(0,16);
}
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
