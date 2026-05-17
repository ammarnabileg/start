<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::community()
// $brand, $brandId, $stats, $interactions, $byPlatform, $bySentiment,
// $statusFilter, $platformFilter, $page, $total, $totalPages, $csrf

$platformColors = [
    'linkedin'  => '#0A66C2', 'instagram' => '#E1306C', 'tiktok'    => '#010101',
    'twitter'   => '#1DA1F2', 'facebook'  => '#1877F2', 'youtube'   => '#FF0000',
    'threads'   => '#6B7280', 'snapchat'  => '#FFFC00',
];
$platformLabels = [
    'linkedin'  => 'in', 'instagram' => '📸', 'tiktok'    => '♪',
    'twitter'   => 'X',  'facebook'  => 'f',  'youtube'   => '▶',
    'threads'   => '@',  'snapchat'  => '👻',
];
$sentimentColors = [
    'positive' => 'var(--green-light)', 'negative' => 'var(--red)',
    'neutral'  => 'var(--text-muted)',  'mixed'    => 'var(--orange)',
];
?>
<style>
.community-inbox { display: grid; grid-template-columns: 1fr 300px; gap: 1.5rem; }
.filter-bar { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 1rem; }
.filter-btn {
  padding: 0.38rem 0.85rem; border-radius: 99px; font-size: 0.78rem; font-weight: 500;
  border: 1px solid var(--glass-border); background: var(--glass-bg);
  color: var(--text-secondary); cursor: pointer; text-decoration: none;
  transition: all var(--tr);
}
.filter-btn:hover { background: var(--glass-bg-hover); color: var(--text-primary); }
.filter-btn.active { background: var(--gradient-primary); color: #fff; border-color: transparent; }
.ic-card {
  background: var(--glass-bg); border: 1px solid var(--glass-border);
  border-radius: var(--radius-md); padding: 1rem; margin-bottom: 0.75rem;
  transition: border-color var(--tr);
}
.ic-card:hover { border-color: rgba(255,255,255,0.15); }
.ic-card.sn-negative { border-left: 3px solid #ef4444; }
.ic-card.sn-positive { border-left: 3px solid #10b981; }
.ic-card.status-replied { opacity: 0.65; }
.platform-pill {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 8px; font-size: 0.72rem;
  font-weight: 700; color: #fff; flex-shrink: 0;
}
.sentiment-badge {
  display: inline-flex; align-items: center; padding: 1px 7px;
  border-radius: 99px; font-size: 0.7rem; font-weight: 600;
}
.sb-positive { background: rgba(16,185,129,.15); color: #34d399; border: 1px solid rgba(16,185,129,.3); }
.sb-negative { background: rgba(239,68,68,.15);  color: #f87171; border: 1px solid rgba(239,68,68,.3); }
.sb-neutral  { background: rgba(107,114,128,.15);color: #9ca3af; border: 1px solid rgba(107,114,128,.3);}
.sb-mixed    { background: rgba(249,115,22,.15);  color: #fb923c; border: 1px solid rgba(249,115,22,.3);}
.ai-reply-block {
  margin-top: 0.75rem;
  background: rgba(59,130,246,0.07); border: 1px solid rgba(59,130,246,0.2);
  border-radius: var(--radius-sm); padding: 0.8rem;
}
.reply-ta {
  width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm);
  border: 1px solid var(--glass-border); background: var(--glass-bg);
  color: var(--text-primary); font-size: 0.82rem; resize: vertical; min-height: 70px;
  font-family: inherit;
}
.reply-ta:focus { outline: none; border-color: var(--blue-light); }
.ic-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.65rem; }
.metric-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--glass-border); font-size: 0.82rem; }
.metric-row:last-child { border-bottom: none; }
@media (max-width: 900px) { .community-inbox { grid-template-columns: 1fr; } }
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">💬 Community Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Monitor, reply and manage interactions across all connected platforms</p>
    </div>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
        <button class="btn btn-ghost btn-sm" onclick="syncNow()" id="syncBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
            Sync Now
        </button>
        <?php if ($brandId): ?>
        <button class="btn btn-primary btn-sm" onclick="bulkReplyAll()" id="bulkBtn">
            🤖 Reply All with AI
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="glass-card" style="padding:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem;">
            <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Pending</div>
            <span style="font-size:1.1rem;">⏳</span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--yellow);"><?= number_format((int)($stats['pending'] ?? 0)) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">Needs attention</div>
    </div>
    <div class="glass-card" style="padding:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem;">
            <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Replied</div>
            <span style="font-size:1.1rem;">✅</span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--green-light);"><?= number_format((int)($stats['replied'] ?? 0)) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">Total replied</div>
    </div>
    <div class="glass-card" style="padding:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem;">
            <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Total</div>
            <span style="font-size:1.1rem;">📬</span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-primary);"><?= number_format((int)($stats['total'] ?? 0)) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">All interactions</div>
    </div>
    <div class="glass-card" style="padding:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem;">
            <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Ignored</div>
            <span style="font-size:1.1rem;">🚫</span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-muted);"><?= number_format((int)($stats['ignored'] ?? 0)) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;">Filtered out</div>
    </div>
</div>

<!-- Main Grid -->
<div class="community-inbox">

    <!-- LEFT: Queue -->
    <div>
        <div class="glass-card" style="padding:1.25rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
                <h3 style="margin:0;font-size:1rem;font-weight:600;">Interaction Queue</h3>
                <span style="font-size:0.8rem;color:var(--text-muted);"><?= $total ?> total · page <?= $page ?>/<?= $totalPages ?></span>
            </div>

            <!-- Status Tabs -->
            <div class="filter-bar">
                <?php foreach (['all' => 'All', 'new' => 'Pending', 'in_review' => 'In Review', 'replied' => 'Replied', 'ignored' => 'Ignored', 'escalated' => 'Escalated'] as $sv => $sl): ?>
                <a href="?status=<?= $sv ?>&platform=<?= urlencode($platformFilter) ?>"
                   class="filter-btn <?= $statusFilter === $sv ? 'active' : '' ?>"><?= $sl ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Platform Tabs -->
            <div class="filter-bar" style="margin-bottom:1.25rem;">
                <?php foreach (['all' => 'All', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'Twitter/X', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'] as $pv => $pl): ?>
                <a href="?status=<?= urlencode($statusFilter) ?>&platform=<?= $pv ?>"
                   class="filter-btn <?= $platformFilter === $pv ? 'active' : '' ?>"><?= $pl ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Cards or Empty State -->
            <?php if (!$brandId): ?>
            <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
                <div style="font-size:2.5rem;margin-bottom:.75rem;">🔌</div>
                <h3 style="font-size:0.95rem;margin-bottom:0.4rem;color:var(--text-secondary);">No brand set up yet</h3>
                <p style="font-size:0.82rem;margin-bottom:1rem;">Create a brand to start managing community interactions.</p>
                <a href="/brands/create" class="btn btn-primary" style="font-size:0.82rem;">Create Brand</a>
            </div>
            <?php elseif (empty($interactions)): ?>
            <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
                <div style="font-size:2.5rem;margin-bottom:.75rem;">📭</div>
                <h3 style="font-size:0.95rem;margin-bottom:0.4rem;color:var(--text-secondary);">No messages yet</h3>
                <p style="font-size:0.82rem;margin-bottom:1rem;">Connect platforms to start receiving interactions, or sync to fetch the latest.</p>
                <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;">
                    <a href="/dashboard/settings" class="btn btn-primary" style="font-size:0.82rem;">🔌 Connect Platforms</a>
                    <button class="btn btn-ghost" style="font-size:0.82rem;" onclick="syncNow()">↻ Sync Now</button>
                </div>
            </div>
            <?php else: ?>

            <?php foreach ($interactions as $ic):
                $color  = htmlspecialchars($platformColors[$ic['platform']] ?? '#555');
                $lbl    = $platformLabels[$ic['platform']] ?? '?';
                $sClass = 'sb-' . ($ic['sentiment'] ?? 'neutral');
                $cClass = 'ic-card sn-' . ($ic['sentiment'] ?? 'neutral') . ($ic['status'] === 'replied' ? ' status-replied' : '');
                $hasAI  = !empty($ic['ai_suggested_reply']);
                $ts     = strtotime($ic['created_at'] ?? 'now');
                $diff   = time() - $ts;
                $ago    = $diff < 60 ? $diff . 's ago' : ($diff < 3600 ? (int)($diff / 60) . 'm ago' : ($diff < 86400 ? (int)($diff / 3600) . 'h ago' : (int)($diff / 86400) . 'd ago'));
            ?>
            <div class="<?= $cClass ?>" id="ic<?= htmlspecialchars((string)$ic['id']) ?>">
                <div style="display:flex;align-items:flex-start;gap:.75rem;">
                    <?php if (!empty($ic['author_avatar'])): ?>
                    <img src="<?= htmlspecialchars($ic['author_avatar']) ?>" alt=""
                         style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;"
                         onerror="this.style.display='none'">
                    <?php endif; ?>
                    <span class="platform-pill" style="background:<?= $color ?>;"><?= htmlspecialchars($lbl) ?></span>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                            <span style="font-weight:600;font-size:.875rem;"><?= htmlspecialchars($ic['author_name'] ?? 'Unknown') ?></span>
                            <?php if (!empty($ic['author_handle'])): ?>
                            <span style="font-size:.75rem;color:var(--text-muted);">@<?= htmlspecialchars($ic['author_handle']) ?></span>
                            <?php endif; ?>
                            <span class="sentiment-badge <?= $sClass ?>"><?= ucfirst(htmlspecialchars($ic['sentiment'] ?? 'neutral')) ?></span>
                            <span style="font-size:.72rem;color:var(--text-muted);margin-left:auto;"><?= $ago ?></span>
                        </div>
                        <p style="margin:.35rem 0 0;font-size:.83rem;color:var(--text-secondary);line-height:1.5;">
                            <?= htmlspecialchars(mb_substr($ic['message_text'] ?? '', 0, 300)) ?><?= mb_strlen($ic['message_text'] ?? '') > 300 ? '…' : '' ?>
                        </p>
                    </div>
                </div>

                <?php if ($ic['status'] === 'replied' && !empty($ic['actual_reply'])): ?>
                <div style="margin-top:.6rem;padding:.6rem .75rem;background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius-sm);font-size:.78rem;color:var(--green-light);">
                    ✓ <?= htmlspecialchars(mb_substr($ic['actual_reply'], 0, 150)) ?>
                </div>
                <?php elseif ($hasAI): ?>
                <div class="ai-reply-block" id="air<?= htmlspecialchars((string)$ic['id']) ?>">
                    <div style="font-size:.72rem;font-weight:600;color:var(--blue-light);margin-bottom:.5rem;">✨ AI Suggested Reply</div>
                    <textarea class="reply-ta" id="rt<?= htmlspecialchars((string)$ic['id']) ?>" rows="3"><?= htmlspecialchars($ic['ai_suggested_reply']) ?></textarea>
                    <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                        <button class="btn btn-sm btn-primary" onclick="approveAndPublish(<?= (int)$ic['id'] ?>)">✓ Approve &amp; Publish</button>
                        <button class="btn btn-sm btn-ghost" onclick="saveOnly(<?= (int)$ic['id'] ?>)">Save Draft</button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($ic['status'] !== 'replied'): ?>
                <div class="ic-actions">
                    <button class="btn btn-sm btn-primary" onclick="openReplyModal(<?= (int)$ic['id'] ?>)">💬 Reply</button>
                    <?php if ($hasAI): ?>
                    <button class="btn btn-sm btn-ghost" onclick="toggleAIBlock(<?= (int)$ic['id'] ?>)" style="color:var(--blue-light);">✨ AI Reply</button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-ghost" onclick="ignoreItem(<?= (int)$ic['id'] ?>)">Ignore</button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div style="display:flex;gap:.5rem;justify-content:center;margin-top:1rem;flex-wrap:wrap;">
                <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
                <a href="?status=<?= urlencode($statusFilter) ?>&platform=<?= urlencode($platformFilter) ?>&page=<?= $pg ?>"
                   class="btn btn-sm <?= $pg === $page ? 'btn-primary' : 'btn-ghost' ?>"><?= $pg ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT: Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <!-- Platform breakdown -->
        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:.75rem;">📊 By Platform</h3>
            <?php if (empty($byPlatform)): ?>
            <p style="font-size:.8rem;color:var(--text-muted);text-align:center;padding:1rem 0;">Connect platforms &amp; sync to see data.</p>
            <?php else: foreach ($byPlatform as $bp): ?>
            <div class="metric-row">
                <span style="display:flex;align-items:center;gap:.5rem;color:var(--text-secondary);">
                    <span class="platform-pill" style="background:<?= htmlspecialchars($platformColors[$bp['platform']] ?? '#666') ?>;width:20px;height:20px;font-size:.6rem;">
                        <?= htmlspecialchars($platformLabels[$bp['platform']] ?? '?') ?>
                    </span>
                    <?= ucfirst(htmlspecialchars($bp['platform'])) ?>
                </span>
                <span style="font-weight:600;"><?= (int)$bp['cnt'] ?> <small style="color:var(--text-muted);">(<?= (int)($bp['pending'] ?? 0) ?> new)</small></span>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Sentiment -->
        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:.75rem;">🎭 Sentiment</h3>
            <?php if (empty($bySentiment)): ?>
            <p style="font-size:.8rem;color:var(--text-muted);text-align:center;padding:.5rem 0;">No sentiment data yet.</p>
            <?php else: foreach ($bySentiment as $bs): ?>
            <div class="metric-row">
                <span style="color:<?= $sentimentColors[$bs['sentiment']] ?? 'var(--text-muted)' ?>;"><?= ucfirst(htmlspecialchars($bs['sentiment'])) ?></span>
                <span style="font-weight:600;"><?= (int)$bs['cnt'] ?></span>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:.75rem;">⚡ Quick Actions</h3>
            <div style="display:flex;flex-direction:column;gap:.5rem;">
                <button class="btn btn-ghost" style="width:100%;justify-content:flex-start;font-size:0.82rem;" onclick="syncNow()">🔄 Sync All Platforms</button>
                <?php if ($brandId): ?>
                <button class="btn btn-ghost" style="width:100%;justify-content:flex-start;font-size:0.82rem;" onclick="bulkReplyAll()">🤖 Bulk AI Reply</button>
                <?php endif; ?>
                <a href="?status=new" class="btn btn-ghost" style="justify-content:flex-start;font-size:0.82rem;">📥 Pending (<?= (int)($stats['pending'] ?? 0) ?>)</a>
                <a href="?status=replied" class="btn btn-ghost" style="justify-content:flex-start;font-size:0.82rem;">✅ Replied (<?= (int)($stats['replied'] ?? 0) ?>)</a>
                <a href="/dashboard/settings" class="btn btn-ghost" style="justify-content:flex-start;font-size:0.82rem;">🔌 Connect Platforms</a>
            </div>
        </div>

    </div>
</div>

<!-- Reply Modal -->
<div id="replyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;">
    <div class="glass-card" style="width:100%;max-width:540px;margin:1rem;padding:0;">
        <div class="modal-header">
            <h3>💬 Send Reply</h3>
            <button class="modal-close" onclick="closeReply()">×</button>
        </div>
        <div style="padding:1.25rem;">
            <input type="hidden" id="rmId">
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">Reply Text</label>
                <textarea class="form-textarea reply-ta" id="rmText" rows="5" placeholder="Write your reply…"></textarea>
            </div>
            <label style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;color:var(--text-secondary);cursor:pointer;">
                <input type="checkbox" id="rmPublish" checked style="accent-color:var(--blue-light);">
                Publish to platform immediately
            </label>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeReply()">Cancel</button>
            <button class="btn btn-primary" onclick="sendModal()">Send Reply</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:none;">
    <div id="toastMsg" style="background:var(--navy-mid,#1e1e3f);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:.75rem 1.25rem;font-size:.85rem;box-shadow:0 4px 24px rgba(0,0,0,.4);"></div>
</div>

<script>
const CSRF = <?= json_encode($csrf ?? '') ?>;

function showToast(msg, ok = true) {
    const m = document.getElementById('toastMsg');
    if (!m) return;
    m.textContent = msg;
    m.style.borderLeft = '3px solid ' + (ok ? 'var(--green-light)' : '#f87171');
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

async function syncNow() {
    const btn = document.getElementById('syncBtn');
    btn.disabled = true; btn.textContent = '⏳ Syncing…';
    try {
        const d = await apiPost('/api/community/sync', {});
        showToast(d.message || (d.success ? 'Synced!' : 'Sync failed'), d.success !== false);
        if (d.success && (d.new_count || 0) > 0) setTimeout(() => location.reload(), 1500);
    } catch(e) { showToast('Sync error', false); }
    btn.disabled = false; btn.textContent = '↻ Sync Now';
}

async function bulkReplyAll() {
    if (!confirm('Reply to all pending interactions using AI suggestions?')) return;
    const btn = document.getElementById('bulkBtn');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Replying…'; }
    try {
        const d = await apiPost('/api/community/auto-reply-all', {});
        showToast(d.message || 'Done!', d.success !== false);
        if (d.success) setTimeout(() => location.reload(), 1500);
    } catch(e) { showToast('Error', false); }
    if (btn) { btn.disabled = false; btn.textContent = '🤖 Reply All with AI'; }
}

function toggleAIBlock(id) {
    const el = document.getElementById('air' + id);
    if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

async function approveAndPublish(id) {
    const ta   = document.getElementById('rt' + id);
    const text = ta ? ta.value.trim() : '';
    if (!text) return showToast('Empty reply', false);
    const d = await apiPost('/api/community/reply', { interaction_id: id, reply_text: text, publish: true });
    showToast(d.success ? 'Reply published!' : (d.error || 'Failed'), d.success !== false);
    if (d.success) { const c = document.getElementById('ic' + id); if (c) c.classList.add('status-replied'); }
}

async function saveOnly(id) {
    const ta   = document.getElementById('rt' + id);
    const text = ta ? ta.value.trim() : '';
    if (!text) return;
    const d = await apiPost('/api/community/reply', { interaction_id: id, reply_text: text, publish: false });
    showToast(d.success ? 'Saved' : 'Failed', d.success !== false);
}

function openReplyModal(id) {
    document.getElementById('rmId').value = id;
    document.getElementById('rmText').value = '';
    const modal = document.getElementById('replyModal');
    modal.style.display = 'flex';
    setTimeout(() => document.getElementById('rmText').focus(), 50);
}
function closeReply() { document.getElementById('replyModal').style.display = 'none'; }
document.getElementById('replyModal').addEventListener('click', function(e) { if (e.target === this) closeReply(); });

async function sendModal() {
    const id   = document.getElementById('rmId').value;
    const text = document.getElementById('rmText').value.trim();
    const pub  = document.getElementById('rmPublish').checked;
    if (!text) return showToast('Reply text required', false);
    const d = await apiPost('/api/community/reply', { interaction_id: id, reply_text: text, publish: pub });
    closeReply();
    showToast(d.success ? (pub ? 'Published!' : 'Saved!') : (d.error || 'Failed'), d.success !== false);
    if (d.success) { const c = document.getElementById('ic' + id); if (c) c.classList.add('status-replied'); }
}

async function ignoreItem(id) {
    const d = await apiPost('/api/community/reply', { interaction_id: id, reply_text: ' ', publish: false });
    showToast('Ignored');
    const c = document.getElementById('ic' + id);
    if (c) { c.style.transition = 'opacity .3s'; c.style.opacity = '.3'; }
}
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
