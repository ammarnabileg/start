<?php
$pageTitle  = 'Copywriting Studio';
$activePage = 'copywriting';
$contentTypes = [
  ['id'=>'caption',  'label'=>'Caption'],
  ['id'=>'linkedin', 'label'=>'LinkedIn'],
  ['id'=>'thread',   'label'=>'Thread'],
  ['id'=>'script',   'label'=>'Script'],
  ['id'=>'hook',     'label'=>'Hook'],
  ['id'=>'cta',      'label'=>'CTA'],
  ['id'=>'adcopy',   'label'=>'Ad Copy'],
  ['id'=>'carousel', 'label'=>'Carousel'],
  ['id'=>'story',    'label'=>'Story'],
  ['id'=>'comment',  'label'=>'Comment Reply'],
  ['id'=>'dm',       'label'=>'DM Reply'],
];
$styles = ['Professional','Casual','Humorous','Inspirational','Educational','Storytelling','Bold','Minimal','Emotional','Provocative'];
$platforms = ['LinkedIn','Instagram','TikTok','Twitter/X','Facebook','YouTube','Snapchat','Threads','Pinterest'];
?>
<?php ob_start() ?>
<div class="copywriting-studio">
  <div class="page-header page-header-row">
    <div>
      <h1>Copywriting Studio ✍️</h1>
      <p>AI-powered content generation for every platform and format</p>
    </div>
    <div style="display:flex;gap:0.75rem">
      <button class="btn btn-ghost">📚 My Library</button>
      <button class="btn btn-ghost">📋 Templates</button>
    </div>
  </div>

  <!-- Content Type Tabs -->
  <div class="content-type-tabs">
    <?php foreach ($contentTypes as $ct): ?>
    <button class="content-type-tab <?= $ct['id']==='caption'?'active':'' ?>" data-type="<?= $ct['id'] ?>">
      <?= htmlspecialchars($ct['label']) ?>
    </button>
    <?php endforeach ?>
  </div>

  <!-- Studio Layout -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;min-height:600px">

    <!-- LEFT PANEL -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">
      <div class="glass-card">
        <h3 style="font-size:0.9rem;margin-bottom:1rem;color:var(--text-secondary)">✏️ Content Brief</h3>

        <div class="form-group">
          <label class="form-label">Topic / Idea</label>
          <textarea class="form-textarea" id="topicInput" rows="4" placeholder="Describe your topic, product announcement, idea, or paste a URL...&#10;&#10;Example: How AI agents are replacing 80% of social media tasks for enterprise brands"></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Target Platform</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.4rem">
            <?php foreach ($platforms as $i => $pl): ?>
            <button class="style-btn <?= $i===0?'active':'' ?>" data-platform="<?= strtolower(explode('/',$pl)[0]) ?>"><?= htmlspecialchars($pl) ?></button>
            <?php endforeach ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Writing Style</label>
          <div class="style-grid">
            <?php foreach ($styles as $i => $st): ?>
            <button class="style-btn <?= $i===0?'active':'' ?>"><?= htmlspecialchars($st) ?></button>
            <?php endforeach ?>
          </div>
        </div>

        <div class="form-row" style="margin-bottom:1rem">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Language</label>
            <select class="form-select" id="langSelect">
              <option value="en">English</option>
              <option value="ar">Arabic (عربي)</option>
              <option value="mixed">Mixed (EN + AR)</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Tone</label>
            <select class="form-select">
              <option>Formal</option>
              <option>Semi-formal</option>
              <option>Casual</option>
              <option>Gen-Z</option>
            </select>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Keywords / Hashtags (optional)</label>
          <input type="text" class="form-input" placeholder="#AI #marketing #growth — separate with commas">
        </div>
      </div>

      <div class="glass-card" style="padding:1rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem">
          <label class="form-label" style="margin:0">Variations</label>
          <div style="display:flex;gap:4px">
            <?php foreach ([1,2,3] as $v): ?>
            <button class="style-btn <?= $v===1?'active':'' ?>" style="padding:0.3rem 0.6rem"><?= $v ?>x</button>
            <?php endforeach ?>
          </div>
        </div>
        <button class="btn btn-primary btn-block btn-lg generate-btn" style="gap:0.75rem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          Generate with AI
        </button>
      </div>
    </div>

    <!-- RIGHT PANEL -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">
      <div class="glass-card" style="flex:1;display:flex;flex-direction:column">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
          <h3 style="font-size:0.9rem;color:var(--text-secondary)">🤖 AI Output</h3>
          <div style="display:flex;gap:0.4rem">
            <button class="btn btn-ghost btn-sm copy-output-btn" title="Copy to clipboard">📋 Copy</button>
            <button class="btn btn-ghost btn-sm save-output-btn" title="Save to library">💾 Save</button>
            <button class="btn btn-ghost btn-sm regen-output-btn" title="Regenerate">🔄 Regen</button>
          </div>
        </div>

        <!-- AI Loading -->
        <div class="ai-loading" style="margin-bottom:1rem">
          <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.4rem">AI is generating your content...</div>
          <div class="ai-bar-row">
            <?php for ($i=0;$i<12;$i++): ?><div class="ai-bar" style="height:6px"></div><?php endfor ?>
          </div>
        </div>

        <div class="output-box" style="flex:1;min-height:280px">
          <span class="output-placeholder">Your generated content will appear here...

Click "Generate with AI" to create compelling content for your selected platform and style.</span>
        </div>

        <!-- Character count -->
        <div style="display:flex;justify-content:space-between;margin-top:0.75rem;font-size:0.75rem;color:var(--text-muted)">
          <span>Characters: <span id="charCount">0</span></span>
          <span>Words: <span id="wordCount">0</span></span>
          <span id="charLimit" style="color:var(--green-light)">✓ Within limit</span>
        </div>
      </div>

      <!-- Platform Preview -->
      <div class="glass-card">
        <h3 style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:0.75rem">📱 Platform Preview</h3>
        <div style="background:rgba(0,0,0,0.3);border-radius:var(--radius-md);padding:1rem;max-height:200px;overflow-y:auto">
          <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.75rem">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.8rem;flex-shrink:0">B</div>
            <div>
              <div style="font-size:0.82rem;font-weight:600">Your Brand</div>
              <div style="font-size:0.72rem;color:var(--text-muted)">Just now</div>
            </div>
          </div>
          <div id="previewText" style="font-size:0.85rem;line-height:1.6;color:var(--text-secondary)">
            Your content preview will appear here after generation...
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.6rem">
        <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Scheduled for best time!','success')">📅 Schedule</button>
        <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Added to queue!','success')">➕ Add to Queue</button>
        <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Opening editor...','info')">✏️ Full Editor</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const output = document.querySelector('.output-box');
  const previewText = document.getElementById('previewText');
  const charCount = document.getElementById('charCount');
  const wordCount = document.getElementById('wordCount');

  // Sync preview
  const observer = new MutationObserver(() => {
    const text = output?.textContent?.trim() || '';
    if (previewText && text && !text.includes('will appear here')) {
      previewText.textContent = text.slice(0, 300) + (text.length > 300 ? '...' : '');
    }
    if (charCount) charCount.textContent = text.length.toLocaleString();
    if (wordCount) wordCount.textContent = text ? text.split(/\s+/).filter(Boolean).length.toLocaleString() : '0';
  });
  if (output) observer.observe(output, { childList: true, subtree: true, characterData: true });

  // Platform style-btn group
  document.querySelectorAll('.copywriting-studio .style-btn[data-platform]').forEach(btn => {
    btn.addEventListener('click', () => {
      btn.closest('div').querySelectorAll('.style-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });
});
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
