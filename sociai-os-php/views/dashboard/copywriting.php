<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::copywriting()
// $brand, $brandId, $user, $currentUser, $csrf

$contentTypes = [
    ['id' => 'caption',  'label' => 'Caption'],
    ['id' => 'linkedin', 'label' => 'LinkedIn'],
    ['id' => 'thread',   'label' => 'Thread'],
    ['id' => 'script',   'label' => 'Script'],
    ['id' => 'hook',     'label' => 'Hook'],
    ['id' => 'cta',      'label' => 'CTA'],
    ['id' => 'adcopy',   'label' => 'Ad Copy'],
    ['id' => 'carousel', 'label' => 'Carousel'],
    ['id' => 'story',    'label' => 'Story'],
    ['id' => 'comment',  'label' => 'Comment Reply'],
    ['id' => 'dm',       'label' => 'DM Reply'],
];
$styles = ['Professional', 'Casual', 'Humorous', 'Inspirational', 'Educational', 'Storytelling', 'Bold', 'Minimal', 'Emotional', 'Provocative'];
$platforms = ['LinkedIn', 'Instagram', 'TikTok', 'Twitter/X', 'Facebook', 'YouTube', 'Snapchat', 'Threads', 'Pinterest'];

$brandName = $brand ? htmlspecialchars($brand['name']) : 'Your Brand';
$industry  = $brand ? htmlspecialchars($brand['industry'] ?? '') : '';
?>
<style>
.content-type-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
.content-type-tab {
  padding: 0.4rem 0.85rem; border-radius: 99px; font-size: 0.78rem; font-weight: 500;
  border: 1px solid var(--glass-border); background: var(--glass-bg);
  color: var(--text-secondary); cursor: pointer; transition: all var(--tr);
}
.content-type-tab:hover { background: var(--glass-bg-hover); color: var(--text-primary); }
.content-type-tab.active { background: var(--gradient-primary); color: #fff; border-color: transparent; }
.style-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 0.35rem; }
.style-btn {
  padding: 0.35rem 0.6rem; border-radius: var(--radius-sm); font-size: 0.75rem; font-weight: 500;
  border: 1px solid var(--glass-border); background: var(--glass-bg);
  color: var(--text-secondary); cursor: pointer; transition: all var(--tr); text-align: center;
}
.style-btn:hover { background: var(--glass-bg-hover); color: var(--text-primary); }
.style-btn.active { background: var(--blue); color: #fff; border-color: var(--blue); }
.output-box {
  min-height: 280px; padding: 1rem; background: rgba(0,0,0,0.2);
  border: 1px solid var(--glass-border); border-radius: var(--radius-sm);
  font-size: 0.875rem; line-height: 1.7; color: var(--text-secondary);
  white-space: pre-wrap; word-break: break-word; flex: 1;
}
.output-placeholder { color: var(--text-muted); font-style: italic; font-size: 0.82rem; }
.ai-loading { display: none; }
.ai-loading.active { display: block; }
.ai-bar-row { display: flex; gap: 3px; }
.ai-bar {
  flex: 1; border-radius: 2px;
  background: var(--gradient-primary);
  animation: aibar 1.2s ease-in-out infinite;
}
.ai-bar:nth-child(2n) { animation-delay: 0.15s; }
.ai-bar:nth-child(3n) { animation-delay: 0.3s; }
@keyframes aibar { 0%,100%{opacity:.3;transform:scaleY(.5)} 50%{opacity:1;transform:scaleY(1)} }
</style>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">Copywriting Studio ✍️</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">
            AI-powered content generation for every platform and format
            <?php if ($brand): ?> · <span style="color:var(--blue-light);"><?= $brandName ?></span><?php endif; ?>
        </p>
    </div>
    <div style="display:flex;gap:0.75rem;">
        <a href="/dashboard/content" class="btn btn-ghost">📋 Content Library</a>
    </div>
</div>

<!-- Content Type Tabs -->
<div class="content-type-tabs">
    <?php foreach ($contentTypes as $ct): ?>
    <button class="content-type-tab <?= $ct['id'] === 'caption' ? 'active' : '' ?>" data-type="<?= htmlspecialchars($ct['id']) ?>">
        <?= htmlspecialchars($ct['label']) ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- Studio Layout -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;min-height:620px;">

    <!-- LEFT: Input Panel -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">
        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;color:var(--text-secondary);margin-bottom:1rem;">✏️ Content Brief</h3>

            <div class="form-group">
                <label class="form-label">Topic / Idea</label>
                <textarea class="form-textarea" id="topicInput" rows="4" placeholder="Describe your topic, product announcement, idea, or paste a URL…&#10;&#10;Example: How AI agents are replacing 80% of social media tasks for enterprise brands"><?= htmlspecialchars(htmlspecialchars_decode(urldecode((string)(session_status() === PHP_SESSION_ACTIVE ? ($_SESSION['prefill_topic'] ?? '') : '')))) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Target Platform</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.4rem;">
                    <?php foreach ($platforms as $i => $pl): ?>
                    <button class="style-btn <?= $i === 0 ? 'active' : '' ?>" data-platform="<?= strtolower(explode('/', $pl)[0]) ?>"><?= htmlspecialchars($pl) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Writing Style</label>
                <div class="style-grid">
                    <?php foreach ($styles as $i => $st): ?>
                    <button class="style-btn <?= $i === 0 ? 'active' : '' ?>" data-style><?= htmlspecialchars($st) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Language</label>
                    <select class="form-select" id="langSelect">
                        <option value="en">English</option>
                        <option value="ar">Arabic (عربي)</option>
                        <option value="mixed">Mixed (EN + AR)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Tone</label>
                    <select class="form-select" id="toneSelect">
                        <option value="formal">Formal</option>
                        <option value="semi-formal">Semi-formal</option>
                        <option value="casual">Casual</option>
                        <option value="gen-z">Gen-Z</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Keywords / Hashtags (optional)</label>
                <input type="text" class="form-input" id="keywordsInput" placeholder="#AI #marketing #growth — separate with spaces or commas">
            </div>
        </div>

        <div class="glass-card" style="padding:1rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                <label class="form-label" style="margin:0;">Variations</label>
                <div style="display:flex;gap:4px;">
                    <?php foreach ([1, 2, 3] as $v): ?>
                    <button class="style-btn <?= $v === 1 ? 'active' : '' ?>" data-variations="<?= $v ?>" style="padding:0.3rem 0.7rem;"><?= $v ?>x</button>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="btn btn-primary" id="generateBtn" style="width:100%;justify-content:center;padding:0.85rem;font-size:0.95rem;gap:0.6rem;" onclick="generateCopy()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                Generate with AI
            </button>
        </div>
    </div>

    <!-- RIGHT: Output Panel -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">
        <div class="glass-card" style="flex:1;display:flex;flex-direction:column;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                <h3 style="font-size:0.9rem;font-weight:600;color:var(--text-secondary);">🤖 AI Output</h3>
                <div style="display:flex;gap:0.4rem;">
                    <button class="btn btn-ghost btn-sm" onclick="copyOutput()" title="Copy to clipboard">📋 Copy</button>
                    <button class="btn btn-ghost btn-sm" onclick="saveOutput()" title="Save to library">💾 Save</button>
                    <button class="btn btn-ghost btn-sm" onclick="generateCopy()" title="Regenerate">🔄 Regen</button>
                </div>
            </div>

            <!-- Loading indicator -->
            <div class="ai-loading" id="aiLoading" style="margin-bottom:1rem;">
                <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.4rem;">AI is generating your content…</div>
                <div class="ai-bar-row">
                    <?php for ($i = 0; $i < 12; $i++): ?><div class="ai-bar" style="height:6px;"></div><?php endfor; ?>
                </div>
            </div>

            <!-- Output -->
            <div class="output-box" id="outputBox" style="flex:1;">
                <span class="output-placeholder">Your generated content will appear here…

Click "Generate with AI" to create compelling content for your selected platform and style.</span>
            </div>

            <!-- Character count -->
            <div style="display:flex;justify-content:space-between;margin-top:0.75rem;font-size:0.72rem;color:var(--text-muted);">
                <span>Characters: <span id="charCount">0</span></span>
                <span>Words: <span id="wordCount">0</span></span>
                <span id="charLimitStatus" style="color:var(--green-light);">✓ Within limit</span>
            </div>
        </div>

        <!-- Platform Preview -->
        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.75rem;">📱 Platform Preview</h3>
            <div style="background:rgba(0,0,0,0.3);border-radius:var(--radius-md);padding:1rem;max-height:200px;overflow-y:auto;">
                <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.75rem;">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;flex-shrink:0;">
                        <?= strtoupper(substr($brandName, 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-size:0.82rem;font-weight:600;"><?= $brandName ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);">Just now</div>
                    </div>
                </div>
                <div id="previewText" style="font-size:0.85rem;line-height:1.6;color:var(--text-secondary);">
                    Your content preview will appear here after generation…
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.6rem;">
            <button class="btn btn-ghost btn-sm" onclick="scheduleContent()">📅 Schedule</button>
            <button class="btn btn-ghost btn-sm" onclick="saveOutput()">➕ Save Draft</button>
            <a href="/dashboard/content" class="btn btn-ghost btn-sm" style="text-align:center;">✏️ Full Editor</a>
        </div>
    </div>

</div>

<!-- Toast -->
<div id="toast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:none;">
    <div id="toastMsg" style="background:var(--navy-mid,#1e1e3f);border:1px solid var(--glass-border);border-left:3px solid var(--green-light);border-radius:var(--radius-md);padding:.75rem 1.25rem;font-size:.85rem;box-shadow:0 4px 24px rgba(0,0,0,.4);"></div>
</div>

<script>
const CSRF       = <?= json_encode($csrf ?? '') ?>;
const BRAND_NAME = <?= json_encode($brandName) ?>;
const INDUSTRY   = <?= json_encode($industry) ?>;

let selectedType     = 'caption';
let selectedPlatform = 'linkedin';
let selectedStyle    = 'Professional';
let selectedVars     = 1;
let generatedContent = '';

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

// Content type tab selection
document.querySelectorAll('.content-type-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.content-type-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedType = btn.dataset.type;
    });
});

// Platform selection
document.querySelectorAll('[data-platform]').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('div').querySelectorAll('[data-platform]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedPlatform = btn.dataset.platform;
        updateCharLimit();
    });
});

// Style selection
document.querySelectorAll('[data-style]').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.style-grid').querySelectorAll('[data-style]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedStyle = btn.textContent;
    });
});

// Variations selection
document.querySelectorAll('[data-variations]').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('div').querySelectorAll('[data-variations]').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedVars = parseInt(btn.dataset.variations, 10);
    });
});

// Character limits by platform
const platformLimits = {
    linkedin: 3000, instagram: 2200, twitter: 280, tiktok: 2200,
    facebook: 63206, youtube: 5000, snapchat: 250, threads: 500,
    pinterest: 500,
};

function updateCharLimit() {
    const limit = platformLimits[selectedPlatform] || 2200;
    const len   = (document.getElementById('outputBox')?.textContent?.trim() || '').length;
    const el    = document.getElementById('charLimitStatus');
    if (!el) return;
    if (len === 0) { el.textContent = '✓ Within limit'; el.style.color = 'var(--green-light)'; return; }
    if (len > limit) {
        el.textContent = '⚠️ Over limit (' + len + '/' + limit + ')';
        el.style.color = 'var(--red)';
    } else {
        el.textContent = '✓ ' + len + '/' + limit;
        el.style.color = 'var(--green-light)';
    }
}

function updateCounts() {
    const box  = document.getElementById('outputBox');
    if (!box) return;
    const text = box.textContent?.trim() || '';
    const cc   = document.getElementById('charCount');
    const wc   = document.getElementById('wordCount');
    if (cc) cc.textContent = text.length.toLocaleString();
    if (wc) wc.textContent = text ? text.split(/\s+/).filter(Boolean).length.toLocaleString() : '0';

    const preview = document.getElementById('previewText');
    if (preview && text && !text.includes('will appear here')) {
        preview.textContent = text.slice(0, 280) + (text.length > 280 ? '…' : '');
    }
    updateCharLimit();
}

async function generateCopy() {
    const topic    = document.getElementById('topicInput')?.value?.trim() || '';
    const lang     = document.getElementById('langSelect')?.value || 'en';
    const tone     = document.getElementById('toneSelect')?.value || 'formal';
    const keywords = document.getElementById('keywordsInput')?.value?.trim() || '';

    if (!topic) { showToast('Please enter a topic or idea', false); return; }

    const btn     = document.getElementById('generateBtn');
    const loading = document.getElementById('aiLoading');
    const box     = document.getElementById('outputBox');

    btn.disabled = true;
    btn.textContent = '⏳ Generating…';
    if (loading) loading.classList.add('active');
    if (box) box.innerHTML = '';

    try {
        const d = await apiPost('/api/copywriting/generate', {
            topic,
            content_type: selectedType,
            platform:     selectedPlatform,
            style:        selectedStyle,
            tone,
            language:     lang,
            keywords,
            variations:   selectedVars,
            brand_name:   BRAND_NAME,
            industry:     INDUSTRY,
        });

        if (loading) loading.classList.remove('active');

        if (d.success && d.content) {
            generatedContent = d.content;
            if (box) box.textContent = d.content;
            updateCounts();
            showToast('✨ Content generated!');
        } else {
            if (box) box.innerHTML = '<span class="output-placeholder">Generation failed. Please try again.</span>';
            showToast(d.error || 'Generation failed', false);
        }
    } catch(e) {
        if (loading) loading.classList.remove('active');
        if (box) box.innerHTML = '<span class="output-placeholder">Error connecting to AI service. Please try again.</span>';
        showToast('Connection error: ' + e.message, false);
    }

    btn.disabled = false;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> Generate with AI';
}

function copyOutput() {
    const text = document.getElementById('outputBox')?.textContent?.trim() || '';
    if (!text || text.includes('will appear here')) { showToast('Nothing to copy yet', false); return; }
    navigator.clipboard.writeText(text)
        .then(() => showToast('📋 Copied to clipboard!'))
        .catch(() => showToast('Failed to copy', false));
}

async function saveOutput() {
    const text  = document.getElementById('outputBox')?.textContent?.trim() || '';
    const topic = document.getElementById('topicInput')?.value?.trim() || '';
    if (!text || text.includes('will appear here')) { showToast('Nothing to save yet', false); return; }
    const d = await apiPost('/api/content/list', {
        title:           topic.slice(0, 80) || 'AI Generated Content',
        body_text:       text,
        content_type:    selectedType,
        approval_status: 'draft',
        ai_generated:    1,
    });
    showToast(d.success ? '💾 Saved as draft!' : (d.error || 'Failed to save'), d.success !== false);
}

function scheduleContent() {
    const text = document.getElementById('outputBox')?.textContent?.trim() || '';
    if (!text || text.includes('will appear here')) { showToast('Generate content first', false); return; }
    showToast('Save the content first, then schedule from Content Hub', 'info');
    setTimeout(() => window.location.href = '/dashboard/content', 2000);
}

// Watch for external content (e.g. from trends page)
document.addEventListener('DOMContentLoaded', () => {
    const trendHashtag = sessionStorage.getItem('trend_hashtag');
    if (trendHashtag) {
        const topicInput = document.getElementById('topicInput');
        if (topicInput && !topicInput.value) {
            topicInput.value = 'Create engaging content about the trend: ' + trendHashtag;
        }
        sessionStorage.removeItem('trend_hashtag');
    }
    updateCounts();
});
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
