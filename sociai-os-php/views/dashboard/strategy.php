<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::strategy()
// $brand, $brandId, $strategies, $csrf

$docTypes = [
    ['icon' => '📋', 'name' => 'Brand Guide',         'desc' => 'Brand voice, values, visual identity'],
    ['icon' => '🎯', 'name' => 'Marketing Plan',      'desc' => 'Goals, budget, channel strategy'],
    ['icon' => '👤', 'name' => 'Customer Personas',   'desc' => 'Target audience profiles'],
    ['icon' => '📊', 'name' => 'Competitor Analysis', 'desc' => 'Market positioning data'],
    ['icon' => '📅', 'name' => 'Content Calendar',    'desc' => 'Editorial schedule & themes'],
    ['icon' => '💰', 'name' => 'Product Catalog',     'desc' => 'Products, services, pricing'],
    ['icon' => '🏆', 'name' => 'Case Studies',        'desc' => 'Success stories & testimonials'],
    ['icon' => '📰', 'name' => 'Press Releases',      'desc' => 'News & announcements'],
    ['icon' => '📈', 'name' => 'Analytics Report',    'desc' => 'Historical performance data'],
    ['icon' => '🎨', 'name' => 'Creative Brief',      'desc' => 'Campaign concepts & visuals'],
    ['icon' => '📜', 'name' => 'Mission Statement',   'desc' => 'Company vision & values'],
];
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">Strategy Intelligence 🧠</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Upload brand documents and let AI build your marketing strategy</p>
    </div>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
        <?php if ($brandId): ?>
        <button class="btn btn-primary" onclick="openGenerateStrategyModal()">✨ Generate with AI</button>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;">

    <!-- Left: Upload + Saved Strategies -->
    <div>
        <!-- Upload Zone -->
        <div class="glass-card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">📁 Upload Brand Documents</h3>
            <div style="border:2px dashed var(--glass-border);border-radius:var(--radius-md);padding:2.5rem;text-align:center;cursor:pointer;transition:border-color var(--tr);" id="uploadZone" onclick="document.getElementById('fileInput').click()" onmouseover="this.style.borderColor='var(--blue-light)'" onmouseout="this.style.borderColor='var(--glass-border)'">
                <div style="font-size:2.5rem;margin-bottom:0.75rem;">📤</div>
                <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">Drop files here or click to browse</h3>
                <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.3rem;">Supports PDF, Word, Excel, PowerPoint, Images</p>
                <p style="font-size:0.72rem;color:var(--text-muted);">Max 50MB per file · Multiple files supported</p>
                <input type="file" id="fileInput" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" style="display:none;" onchange="handleFileUpload(this)">
                <button class="btn btn-primary" style="margin-top:1rem;" onclick="event.stopPropagation();document.getElementById('fileInput').click()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Choose Files
                </button>
            </div>
            <div id="uploadProgress" style="display:none;margin-top:1rem;">
                <div style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:0.5rem;">Analyzing documents with AI…</div>
                <div style="height:6px;background:var(--glass-bg);border-radius:3px;overflow:hidden;"><div id="uploadBar" style="height:100%;background:var(--gradient-primary);border-radius:3px;width:0%;transition:width 0.3s;"></div></div>
            </div>

            <!-- Doc type grid -->
            <div style="margin-top:1.5rem;">
                <div style="font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem;">Accepted Document Types</div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.5rem;">
                    <?php foreach ($docTypes as $doc): ?>
                    <div style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);font-size:0.75rem;color:var(--text-secondary);">
                        <span><?= $doc['icon'] ?></span>
                        <div>
                            <div style="font-weight:600;color:var(--text-primary);font-size:0.73rem;"><?= htmlspecialchars($doc['name']) ?></div>
                            <div style="font-size:0.67rem;color:var(--text-muted);"><?= htmlspecialchars($doc['desc']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Saved Strategies -->
        <div class="glass-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
                <h3 style="font-size:1rem;font-weight:600;">📄 Saved Strategies</h3>
                <span style="font-size:0.78rem;color:var(--text-muted);"><?= count($strategies) ?> total</span>
            </div>
            <?php if (!$brandId): ?>
            <div style="text-align:center;padding:2rem;color:var(--text-muted);">
                <div style="font-size:2rem;margin-bottom:0.5rem;">🏢</div>
                <p style="font-size:0.82rem;">Create a brand to start building strategies.</p>
                <a href="/brands/create" class="btn btn-ghost" style="margin-top:0.75rem;font-size:0.78rem;">Create Brand</a>
            </div>
            <?php elseif (empty($strategies)): ?>
            <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
                <div style="font-size:3rem;margin-bottom:1rem;">🧠</div>
                <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:0.4rem;color:var(--text-secondary);">No strategies yet</h3>
                <p style="font-size:0.82rem;margin-bottom:1rem;">Upload brand documents above or generate a strategy with AI to get started.</p>
                <button class="btn btn-primary" style="font-size:0.82rem;" onclick="openGenerateStrategyModal()">✨ Generate with AI</button>
            </div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:0.75rem;">
                <?php foreach ($strategies as $strat): ?>
                <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:1rem;transition:border-color var(--tr);" onmouseover="this.style.borderColor='rgba(255,255,255,.15)'" onmouseout="this.style.borderColor='var(--glass-border)'">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.75rem;margin-bottom:0.5rem;">
                        <h4 style="font-size:0.875rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($strat['title']) ?></h4>
                        <span style="font-size:0.7rem;color:var(--text-muted);white-space:nowrap;flex-shrink:0;"><?= date('M j, Y', strtotime($strat['created_at'])) ?></span>
                    </div>
                    <?php if (!empty($strat['content'])): ?>
                    <p style="font-size:0.8rem;color:var(--text-muted);line-height:1.5;margin-bottom:0.75rem;">
                        <?= htmlspecialchars(mb_substr($strat['content'], 0, 200)) ?><?= mb_strlen($strat['content']) > 200 ? '…' : '' ?>
                    </p>
                    <?php endif; ?>
                    <div style="display:flex;gap:0.4rem;">
                        <button class="btn btn-ghost btn-sm" onclick="viewStrategy(<?= (int)$strat['id'] ?>)">📖 View</button>
                        <button class="btn btn-ghost btn-sm" onclick="if(window.SociAI)SociAI.showToast('Generating content from strategy…','info')">✨ Use for Content</button>
                        <button class="btn btn-ghost btn-sm" style="color:var(--red);" onclick="deleteStrategy(<?= (int)$strat['id'] ?>)">✕</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <div class="glass-card" style="background:linear-gradient(135deg,rgba(59,130,246,0.1),rgba(139,92,246,0.1));border-color:rgba(59,130,246,0.3);">
            <div style="font-size:1.3rem;margin-bottom:0.5rem;">🎯</div>
            <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:0.5rem;">Strategy Score</h3>
            <?php if (empty($strategies)): ?>
            <div style="font-size:2.5rem;font-weight:800;color:var(--text-muted);margin-bottom:0.25rem;">—<span style="font-size:1rem;color:var(--text-muted);">/100</span></div>
            <p style="font-size:0.8rem;color:var(--text-muted);">Upload documents or generate a strategy to get your score.</p>
            <?php else: ?>
            <div style="font-size:2.5rem;font-weight:800;color:var(--blue-light);margin-bottom:0.25rem;"><?= min(100, count($strategies) * 15 + 40) ?><span style="font-size:1rem;color:var(--text-muted);">/100</span></div>
            <p style="font-size:0.8rem;color:var(--text-muted);">Upload more documents and generate AI insights to improve your score.</p>
            <?php endif; ?>
        </div>

        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:1rem;">⚡ Quick Actions</h3>
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <?php if ($brandId): ?>
                <button class="btn btn-primary" style="width:100%;justify-content:center;font-size:0.82rem;" onclick="openGenerateStrategyModal()">✨ Generate AI Strategy</button>
                <a href="/dashboard/content" class="btn btn-ghost" style="width:100%;justify-content:flex-start;font-size:0.82rem;">📝 Create Content</a>
                <a href="/dashboard/campaigns" class="btn btn-ghost" style="width:100%;justify-content:flex-start;font-size:0.82rem;">🎯 View Campaigns</a>
                <a href="/dashboard/analytics" class="btn btn-ghost" style="width:100%;justify-content:flex-start;font-size:0.82rem;">📊 View Analytics</a>
                <?php else: ?>
                <a href="/brands/create" class="btn btn-primary" style="width:100%;justify-content:center;font-size:0.82rem;">🏢 Create Brand First</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:0.75rem;">📊 Strategy Overview</h3>
            <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--glass-border);font-size:0.82rem;">
                <span style="color:var(--text-muted);">Total Strategies</span>
                <span style="font-weight:600;"><?= count($strategies) ?></span>
            </div>
            <?php if (!empty($strategies)): ?>
            <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--glass-border);font-size:0.82rem;">
                <span style="color:var(--text-muted);">Latest</span>
                <span style="font-weight:600;color:var(--text-primary);font-size:0.75rem;"><?= date('M j', strtotime($strategies[0]['created_at'])) ?></span>
            </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;padding:0.5rem 0;font-size:0.82rem;">
                <span style="color:var(--text-muted);">Brand</span>
                <span style="font-weight:600;color:var(--blue-light);"><?= $brand ? htmlspecialchars($brand['name']) : '—' ?></span>
            </div>
        </div>

    </div>
</div>

<!-- Strategy View Modal -->
<div id="strategyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;overflow:auto;">
    <div class="glass-card" style="width:100%;max-width:700px;margin:2rem 1rem;padding:0;">
        <div class="modal-header">
            <h3 id="strategyModalTitle">Strategy</h3>
            <button class="modal-close" onclick="document.getElementById('strategyModal').style.display='none'">×</button>
        </div>
        <div style="padding:1.25rem;max-height:70vh;overflow-y:auto;">
            <div id="strategyModalContent" style="font-size:0.875rem;color:var(--text-secondary);line-height:1.7;white-space:pre-wrap;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="document.getElementById('strategyModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Generate Strategy Modal -->
<div id="generateStrategyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;">
    <div class="glass-card" style="width:100%;max-width:520px;margin:1rem;padding:0;">
        <div class="modal-header">
            <h3>✨ Generate AI Strategy</h3>
            <button class="modal-close" onclick="document.getElementById('generateStrategyModal').style.display='none'">×</button>
        </div>
        <div style="padding:1.25rem;">
            <div class="form-group">
                <label class="form-label">Strategy Title</label>
                <input type="text" class="form-input" id="genStratTitle" placeholder="e.g. Q3 2025 Growth Strategy">
            </div>
            <div class="form-group">
                <label class="form-label">Focus Area</label>
                <select class="form-select" id="genStratFocus">
                    <option value="content_strategy">Content Strategy</option>
                    <option value="growth_hacking">Growth Hacking</option>
                    <option value="brand_awareness">Brand Awareness</option>
                    <option value="lead_generation">Lead Generation</option>
                    <option value="community_building">Community Building</option>
                    <option value="influencer_marketing">Influencer Marketing</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Brief / Context</label>
                <textarea class="form-textarea" id="genStratBrief" rows="4" placeholder="Describe your brand, goals, target audience, and any specific requirements…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="document.getElementById('generateStrategyModal').style.display='none'">Cancel</button>
            <button class="btn btn-primary" onclick="generateStrategy()" id="genStratBtn">✨ Generate Strategy</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:none;">
    <div id="toastMsg" style="background:var(--navy-mid,#1e1e3f);border:1px solid var(--glass-border);border-left:3px solid var(--green-light);border-radius:var(--radius-md);padding:.75rem 1.25rem;font-size:.85rem;box-shadow:0 4px 24px rgba(0,0,0,.4);"></div>
</div>

<script>
const CSRF = <?= json_encode($csrf ?? '') ?>;
const savedStrategies = <?= json_encode($strategies) ?>;

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

function openGenerateStrategyModal() {
    document.getElementById('generateStrategyModal').style.display = 'flex';
}

async function generateStrategy() {
    const title  = document.getElementById('genStratTitle').value.trim();
    const focus  = document.getElementById('genStratFocus').value;
    const brief  = document.getElementById('genStratBrief').value.trim();
    if (!title) { showToast('Enter a strategy title', false); return; }
    const btn = document.getElementById('genStratBtn');
    btn.disabled = true; btn.textContent = '⏳ Generating…';
    try {
        const d = await apiPost('/api/strategy/generate', { title, focus, brief });
        btn.disabled = false; btn.textContent = '✨ Generate Strategy';
        document.getElementById('generateStrategyModal').style.display = 'none';
        showToast(d.success ? 'Strategy generated!' : (d.error || 'Generation failed'), d.success !== false);
        if (d.success) setTimeout(() => location.reload(), 1000);
    } catch(e) {
        btn.disabled = false; btn.textContent = '✨ Generate Strategy';
        showToast('Error: ' + e.message, false);
    }
}

function viewStrategy(id) {
    const strat = savedStrategies.find(s => s.id == id);
    if (!strat) return;
    document.getElementById('strategyModalTitle').textContent = strat.title;
    document.getElementById('strategyModalContent').textContent = strat.content || '(No content)';
    document.getElementById('strategyModal').style.display = 'flex';
}

async function deleteStrategy(id) {
    if (!confirm('Delete this strategy?')) return;
    const d = await apiPost('/api/strategy/delete', { id });
    showToast(d.success ? 'Deleted' : 'Failed', d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 500);
}

function handleFileUpload(input) {
    if (!input.files.length) return;
    const progress = document.getElementById('uploadProgress');
    const bar      = document.getElementById('uploadBar');
    progress.style.display = 'block';
    let pct = 0;
    const interval = setInterval(() => {
        pct += Math.random() * 15;
        if (pct >= 90) { clearInterval(interval); }
        bar.style.width = Math.min(90, pct) + '%';
    }, 300);

    const formData = new FormData();
    for (const f of input.files) formData.append('files[]', f);
    formData.append('_token', CSRF);

    fetch('/api/strategy/upload', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            clearInterval(interval);
            bar.style.width = '100%';
            showToast(d.success ? 'Documents uploaded and analyzed!' : (d.error || 'Upload failed'), d.success !== false);
            if (d.success) setTimeout(() => location.reload(), 1200);
            else setTimeout(() => { progress.style.display = 'none'; bar.style.width = '0%'; }, 2000);
        })
        .catch(() => {
            clearInterval(interval);
            progress.style.display = 'none';
            bar.style.width = '0%';
            showToast('Upload error', false);
        });
}

['strategyModal','generateStrategyModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
