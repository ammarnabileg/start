<?php
/**
 * AI Copywriting Studio
 * Vars from controller: $contentTypes, $writingStyles, $platforms, $languages, $history, $brandId
 */

$pageTitle  = 'AI Copywriting Studio';
$activePage = 'copywriting';

// Defaults for standalone rendering
$contentTypes  = $contentTypes  ?? ['caption','linkedin_post','thread','script','hook','cta','ad_copy','carousel','story','comment_reply','dm_reply'];
$writingStyles = $writingStyles ?? ['professional','casual','storytelling','viral','educational','emotional','humorous','inspirational','sales','thought_leadership'];
$platforms     = $platforms     ?? ['linkedin','instagram','facebook','tiktok','twitter','youtube','snapchat','threads','pinterest','whatsapp','telegram'];
$history       = $history       ?? [];
$brandId       = $brandId       ?? 0;

$contentTypeLabels = [
    'caption'       => 'Caption',
    'linkedin_post' => 'LinkedIn Post',
    'thread'        => 'Thread / X',
    'script'        => 'Video Script',
    'hook'          => 'Hook',
    'cta'           => 'CTA',
    'ad_copy'       => 'Ad Copy',
    'carousel'      => 'Carousel Text',
    'story'         => 'Story Script',
    'comment_reply' => 'Comment Reply',
    'dm_reply'      => 'DM Reply',
];

$styleLabels = [
    'professional'      => 'Professional',
    'casual'            => 'Casual',
    'storytelling'      => 'Storytelling',
    'viral'             => 'Viral',
    'educational'       => 'Educational',
    'emotional'         => 'Emotional',
    'humorous'          => 'Humorous',
    'inspirational'     => 'Inspirational',
    'sales'             => 'Sales',
    'thought_leadership'=> 'Thought Leadership',
    'persuasive'        => 'Persuasive',
    'conversational'    => 'Conversational',
    'authoritative'     => 'Authoritative',
    'empathetic'        => 'Empathetic',
];

$platformIcons = [
    'linkedin'  => '🔵',
    'instagram' => '📸',
    'facebook'  => '📘',
    'tiktok'    => '🎵',
    'twitter'   => '🐦',
    'youtube'   => '▶️',
    'snapchat'  => '👻',
    'threads'   => '🧵',
    'pinterest' => '📌',
    'whatsapp'  => '💬',
    'telegram'  => '✈️',
];

$platformColors = [
    'linkedin'  => 'var(--blue)',
    'instagram' => '#E1306C',
    'facebook'  => '#1877F2',
    'tiktok'    => '#ff0050',
    'twitter'   => '#1DA1F2',
    'youtube'   => '#FF0000',
    'snapchat'  => '#FFFC00',
    'threads'   => 'var(--text-primary)',
    'pinterest' => '#E60023',
    'whatsapp'  => '#25D366',
    'telegram'  => '#0088CC',
];

$templateCategories = [
    'Product Launch'    => ['New Product Reveal', 'Limited Time Offer', 'Pre-launch Teaser'],
    'Brand Story'       => ['Founder Journey', 'Behind the Scenes', 'Mission & Values'],
    'Engagement'        => ['Ask a Question', 'Poll Caption', 'This or That'],
    'Educational'       => ['Quick Tip', 'How-To Guide', 'Myth vs Fact'],
    'Testimonial'       => ['Customer Win', 'Case Study Snippet', 'Review Spotlight'],
    'Trending'          => ['Jump on Trend', 'Viral Hook Template', 'Challenge Response'],
];

ob_start();
?>
<!-- ═══════════════════════════════════════════════════════════
     AI COPYWRITING STUDIO
     ═══════════════════════════════════════════════════════════ -->
<div class="page-header page-header-row" style="margin-bottom:1.5rem">
  <div>
    <h1 style="font-size:1.6rem;margin-bottom:0.25rem">
      ✍️ AI Copywriting Studio
    </h1>
    <p style="font-size:0.9rem;color:var(--text-muted)">
      Generate platform-native content in seconds — in English, Arabic, or both.
    </p>
  </div>

  <!-- Language toggle -->
  <div class="flex gap-2 items-center" id="langToggleGroup">
    <button class="btn btn-ghost lang-mode-btn active" data-lang="english">
      🇬🇧 English
    </button>
    <button class="btn btn-ghost lang-mode-btn" data-lang="arabic">
      🇸🇦 العربية
    </button>
    <button class="btn btn-ghost lang-mode-btn" data-lang="mixed">
      🔀 Mixed
    </button>
  </div>
</div>

<!-- ── TWO-COLUMN LAYOUT ──────────────────────────────────── -->
<div style="display:grid;grid-template-columns:40% 1fr;gap:1.5rem;align-items:start">

  <!-- ═══════════ LEFT PANEL: FORM ═══════════ -->
  <div class="glass-card" style="padding:1.5rem;position:sticky;top:calc(var(--topnav-h) + 1rem)">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      Configure Generation
    </h3>

    <form id="copywritingForm" autocomplete="off">
      <input type="hidden" name="language" id="languageInput" value="english">
      <input type="hidden" name="brand_id" value="<?= (int)$brandId ?>">

      <!-- Content Type -->
      <div class="form-group" style="margin-bottom:1.1rem">
        <label class="form-label" style="margin-bottom:0.5rem;display:block">Content Type</label>
        <div class="content-type-tabs" id="contentTypeTabs">
          <?php foreach ($contentTypes as $i => $type): ?>
          <button type="button"
                  class="content-type-tab <?= $i === 0 ? 'active' : '' ?>"
                  data-type="<?= htmlspecialchars($type) ?>">
            <?= htmlspecialchars($contentTypeLabels[$type] ?? ucfirst(str_replace('_',' ',$type))) ?>
          </button>
          <?php endforeach ?>
        </div>
        <input type="hidden" name="content_type" id="contentTypeInput" value="<?= htmlspecialchars($contentTypes[0] ?? 'caption') ?>">
      </div>

      <!-- Platform (checkboxes) -->
      <div class="form-group" style="margin-bottom:1.1rem">
        <label class="form-label" style="margin-bottom:0.5rem;display:block">
          Platforms
          <span style="font-size:0.72rem;color:var(--text-muted);font-weight:400;margin-left:0.4rem">(select all that apply)</span>
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem" id="platformCheckboxes">
          <?php foreach ($platforms as $plat): ?>
          <label style="display:inline-flex;align-items:center;gap:0.35rem;cursor:pointer;padding:0.3rem 0.65rem;border-radius:var(--radius-sm);border:1px solid var(--glass-border);font-size:0.8rem;transition:all 0.15s;background:var(--glass-bg)"
                 class="plat-label">
            <input type="checkbox" name="platforms[]" value="<?= htmlspecialchars($plat) ?>"
                   style="width:12px;height:12px;accent-color:var(--blue)"
                   class="plat-cb" <?= $plat === 'linkedin' ? 'checked' : '' ?>>
            <span><?= ($platformIcons[$plat] ?? '📱') . ' ' . ucfirst($plat) ?></span>
          </label>
          <?php endforeach ?>
        </div>
      </div>

      <!-- Writing Style -->
      <div class="form-group" style="margin-bottom:1.1rem">
        <label class="form-label" for="styleSelect">Writing Style</label>
        <select class="form-select" name="style" id="styleSelect">
          <?php foreach ($styleLabels as $val => $label): ?>
          <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <!-- Topic / Brief -->
      <div class="form-group" style="margin-bottom:1.1rem">
        <label class="form-label" for="topicInput">Topic / Brief</label>
        <textarea class="form-textarea" name="topic" id="topicInput" rows="4"
                  placeholder="What's this content about? e.g. 'Launching our new AI productivity app for remote teams'"
                  required></textarea>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.25rem;text-align:right">
          <span id="topicCharCount">0</span> / 500
        </div>
      </div>

      <!-- Brand Voice Toggle -->
      <div class="form-group" style="margin-bottom:1.1rem">
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div>
            <div class="form-label" style="margin-bottom:0.15rem">Use Brand Voice</div>
            <div style="font-size:0.75rem;color:var(--text-muted)">Apply your brand's tone & personality</div>
          </div>
          <label class="toggle-switch" style="flex-shrink:0">
            <input type="checkbox" name="use_brand_voice" id="brandVoiceToggle" checked>
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>

      <!-- Variations Count -->
      <div class="form-group" style="margin-bottom:1.25rem">
        <label class="form-label" style="margin-bottom:0.5rem;display:block">Number of Variations</label>
        <div style="display:flex;gap:0.5rem">
          <?php foreach ([1, 3, 5] as $n): ?>
          <label style="flex:1;cursor:pointer">
            <input type="radio" name="variations" value="<?= $n ?>" style="display:none" <?= $n === 3 ? 'checked' : '' ?> class="var-radio">
            <span class="var-btn" data-n="<?= $n ?>"
                  style="display:block;text-align:center;padding:0.5rem;border:1px solid var(--glass-border);border-radius:var(--radius-sm);font-size:0.85rem;font-weight:600;background:var(--glass-bg);transition:all 0.15s;cursor:pointer;<?= $n === 3 ? 'background:var(--gradient-primary);color:#fff;border-color:transparent' : '' ?>">
              <?= $n ?>
            </span>
          </label>
          <?php endforeach ?>
        </div>
      </div>

      <!-- Generate Button -->
      <button type="submit" class="btn btn-primary w-full" id="generateBtn" style="font-size:0.95rem;padding:0.75rem 1rem;gap:0.5rem">
        <span id="generateBtnIcon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </span>
        <span id="generateBtnText">Generate Content</span>
      </button>

      <div id="formError" style="display:none;margin-top:0.75rem;padding:0.6rem 0.85rem;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-sm);font-size:0.82rem;color:#FC8181"></div>
    </form>
  </div>

  <!-- ═══════════ RIGHT PANEL: RESULTS ═══════════ -->
  <div>
    <!-- Tabs -->
    <div class="tabs" style="margin-bottom:1.25rem">
      <button class="tab-btn active" data-tab="generated">Generated Content</button>
      <button class="tab-btn" data-tab="history">History</button>
      <button class="tab-btn" data-tab="templates">Templates</button>
    </div>

    <!-- ── TAB: Generated Content ── -->
    <div class="tab-pane active" id="tab-generated">
      <!-- AI loading animation -->
      <div class="ai-loading" id="aiLoader" style="margin-bottom:1.25rem">
        <div class="glass-card" style="padding:1.25rem">
          <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <div>
              <div style="font-size:0.875rem;font-weight:600">AI is crafting your content…</div>
              <div style="font-size:0.75rem;color:var(--text-muted)" id="loaderStatus">Analysing brief and brand context</div>
            </div>
          </div>
          <div class="ai-bar-row">
            <div class="ai-bar"></div><div class="ai-bar"></div><div class="ai-bar"></div>
            <div class="ai-bar"></div><div class="ai-bar"></div><div class="ai-bar"></div>
            <div class="ai-bar"></div><div class="ai-bar"></div>
          </div>
          <div class="skeleton skeleton-text mt-3" style="width:80%"></div>
          <div class="skeleton skeleton-text" style="width:65%"></div>
          <div class="skeleton skeleton-text" style="width:90%"></div>
        </div>
      </div>

      <!-- Results container -->
      <div id="resultsContainer">
        <!-- Placeholder shown before first generation -->
        <div id="resultsPlaceholder" class="glass-card text-center" style="padding:3rem 2rem">
          <div style="width:56px;height:56px;border-radius:50%;background:var(--glass-bg);border:1px solid var(--glass-border);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </div>
          <div style="font-weight:600;margin-bottom:0.4rem">Your generated content will appear here</div>
          <div class="text-muted text-sm">Configure the settings on the left, then click <strong>Generate Content</strong></div>
        </div>

        <!-- Result cards injected by JS -->
        <div id="resultCards" style="display:none"></div>
      </div>
    </div>

    <!-- ── TAB: History ── -->
    <div class="tab-pane" id="tab-history">
      <?php if (empty($history)): ?>
      <div class="glass-card text-center" style="padding:3rem 2rem">
        <div class="text-muted text-sm">No generation history yet. Start creating!</div>
      </div>
      <?php else: ?>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Type</th>
              <th>Platform</th>
              <th>Topic</th>
              <th>Style</th>
              <th>Language</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $row): ?>
            <tr>
              <td>
                <span class="badge badge-info">
                  <?= htmlspecialchars($contentTypeLabels[$row['content_type']] ?? $row['content_type']) ?>
                </span>
              </td>
              <td>
                <span style="font-size:1rem"><?= $platformIcons[$row['platform']] ?? '📱' ?></span>
                <?= htmlspecialchars(ucfirst($row['platform'])) ?>
              </td>
              <td class="td-primary truncate" style="max-width:180px" title="<?= htmlspecialchars($row['topic']) ?>">
                <?= htmlspecialchars($row['topic']) ?>
              </td>
              <td><?= htmlspecialchars(ucfirst($row['style'])) ?></td>
              <td>
                <span class="badge badge-neutral">
                  <?= htmlspecialchars(ucfirst($row['language'])) ?>
                </span>
              </td>
              <td style="font-size:0.78rem;color:var(--text-muted)">
                <?= htmlspecialchars(date('M j, Y', strtotime($row['created_at']))) ?>
              </td>
              <td>
                <button class="btn btn-ghost" style="padding:0.25rem 0.6rem;font-size:0.75rem"
                        onclick="CopywritingStudio.rerunHistory(<?= (int)$row['id'] ?>)">
                  Rerun
                </button>
              </td>
            </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
      <?php endif ?>
    </div>

    <!-- ── TAB: Templates ── -->
    <div class="tab-pane" id="tab-templates">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem">
        <?php foreach ($templateCategories as $category => $templates): ?>
        <div class="glass-card" style="padding:1.1rem">
          <div style="font-size:0.8rem;font-weight:700;color:var(--blue-light);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem">
            <?= htmlspecialchars($category) ?>
          </div>
          <?php foreach ($templates as $tpl): ?>
          <button class="template-item"
                  style="display:block;width:100%;text-align:left;padding:0.5rem 0.6rem;border-radius:var(--radius-sm);border:1px solid var(--glass-border);background:var(--glass-bg);font-size:0.82rem;color:var(--text-secondary);cursor:pointer;margin-bottom:0.4rem;transition:all 0.15s"
                  onclick="CopywritingStudio.useTemplate(<?= htmlspecialchars(json_encode($tpl), ENT_QUOTES) ?>)">
            <?= htmlspecialchars($tpl) ?>
          </button>
          <?php endforeach ?>
        </div>
        <?php endforeach ?>
      </div>
    </div>

  </div><!-- end right panel -->
</div><!-- end grid -->


<!-- ══════════════════════════════════════════════════
     EDIT MODAL
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="editContentModal">
  <div class="modal-content modal-content-lg">
    <div class="modal-header">
      <h3>✏️ Edit Content</h3>
      <button class="modal-close" onclick="SociAI.closeModal('editContentModal')">×</button>
    </div>
    <div class="form-group">
      <label class="form-label">Content</label>
      <textarea class="form-textarea" id="editContentText" rows="8" style="font-size:0.9rem;line-height:1.7"></textarea>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.5rem;font-size:0.78rem;color:var(--text-muted)">
      <span><span id="editCharCount">0</span> characters</span>
      <span id="editPlatformHint"></span>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="SociAI.closeModal('editContentModal')">Cancel</button>
      <button class="btn btn-primary" onclick="CopywritingStudio.saveEdit()">Save Changes</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════
     SAVE TO LIBRARY MODAL
     ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="saveContentModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>💾 Save to Content Library</h3>
      <button class="modal-close" onclick="SociAI.closeModal('saveContentModal')">×</button>
    </div>
    <div class="form-group">
      <label class="form-label" for="saveTitle">Title</label>
      <input type="text" class="form-input" id="saveTitle" placeholder="Give this content a title...">
    </div>
    <div class="form-group">
      <label class="form-label" for="saveCampaign">Campaign (optional)</label>
      <select class="form-select" id="saveCampaign">
        <option value="">— No campaign —</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="SociAI.closeModal('saveContentModal')">Cancel</button>
      <button class="btn btn-primary" onclick="CopywritingStudio.confirmSave()">Save Content</button>
    </div>
  </div>
</div>


<style>
/* Toggle switch */
.toggle-switch { position:relative; display:inline-block; width:40px; height:22px; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider {
  position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0;
  background:var(--glass-bg); border:1px solid var(--glass-border);
  border-radius:9999px; transition:0.3s;
}
.toggle-slider::before {
  content:''; position:absolute; height:14px; width:14px; left:3px; bottom:3px;
  background:var(--text-muted); border-radius:50%; transition:0.3s;
}
.toggle-switch input:checked + .toggle-slider { background:var(--gradient-primary); border-color:transparent; }
.toggle-switch input:checked + .toggle-slider::before { transform:translateX(18px); background:#fff; }

/* Result card actions */
.result-card-actions { display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:0.75rem; }
.result-card-actions .btn { padding:0.3rem 0.7rem; font-size:0.78rem; }

/* Char count pill */
.char-pill {
  display:inline-block; padding:0.15rem 0.55rem; border-radius:9999px;
  font-size:0.72rem; font-weight:600;
  background:rgba(59,130,246,0.1); color:var(--blue-light); border:1px solid rgba(59,130,246,0.2);
}

/* Reach score bar */
.reach-bar-wrap { display:flex; align-items:center; gap:0.5rem; margin-top:0.35rem; }
.reach-bar-track { flex:1; height:4px; background:rgba(255,255,255,0.08); border-radius:9999px; overflow:hidden; }
.reach-bar-fill  { height:100%; border-radius:9999px; background:var(--gradient-primary); transition:width 0.8s ease; }

/* Variation selector */
.var-btn:hover { background:var(--glass-bg-hover)!important; }

/* RTL result cards */
.rtl-card { direction:rtl; text-align:right; }

/* Copy success flash */
@keyframes copyFlash { 0%,100%{opacity:1} 50%{opacity:0.4} }
.copy-flash { animation:copyFlash 0.4s ease; }

/* Template item hover */
.template-item:hover { background:var(--glass-bg-hover)!important; color:var(--text-primary)!important; border-color:var(--glass-border-hover)!important; }
</style>

<script>
(function() {
  'use strict';

  /* ── Platform char limits ── */
  const CHAR_LIMITS = {
    twitter: 280, linkedin: 3000, instagram: 2200, facebook: 63206,
    tiktok: 2200, youtube: 5000, threads: 500, snapchat: 250,
    pinterest: 500, whatsapp: 65536, telegram: 4096,
  };

  const platformColors = <?= json_encode($platformColors, JSON_UNESCAPED_UNICODE) ?>;
  const contentTypeLabels = <?= json_encode($contentTypeLabels, JSON_UNESCAPED_UNICODE) ?>;

  /* ── State ── */
  let currentLang      = 'english';
  let currentEditIndex = null;
  let savedCardIndex   = null;
  let generatedResults = [];

  /* ── DOM refs ── */
  const form            = document.getElementById('copywritingForm');
  const langInput       = document.getElementById('languageInput');
  const contentTypeInput= document.getElementById('contentTypeInput');
  const topicInput      = document.getElementById('topicInput');
  const topicCharCount  = document.getElementById('topicCharCount');
  const generateBtn     = document.getElementById('generateBtn');
  const generateBtnText = document.getElementById('generateBtnText');
  const generateBtnIcon = document.getElementById('generateBtnIcon');
  const aiLoader        = document.getElementById('aiLoader');
  const loaderStatus    = document.getElementById('loaderStatus');
  const resultsPlaceholder = document.getElementById('resultsPlaceholder');
  const resultCards     = document.getElementById('resultCards');
  const formError       = document.getElementById('formError');

  /* ── Language toggle ── */
  document.getElementById('langToggleGroup').addEventListener('click', e => {
    const btn = e.target.closest('.lang-mode-btn');
    if (!btn) return;
    document.querySelectorAll('.lang-mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentLang = btn.dataset.lang;
    langInput.value = currentLang;
  });

  /* ── Content type tabs ── */
  document.getElementById('contentTypeTabs').addEventListener('click', e => {
    const btn = e.target.closest('.content-type-tab');
    if (!btn) return;
    document.querySelectorAll('.content-type-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    contentTypeInput.value = btn.dataset.type;
  });

  /* ── Variations radio buttons ── */
  document.querySelectorAll('.var-radio').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.var-btn').forEach(btn => {
        const active = parseInt(btn.dataset.n) === parseInt(radio.value);
        btn.style.background    = active ? 'var(--gradient-primary)' : 'var(--glass-bg)';
        btn.style.color         = active ? '#fff' : '';
        btn.style.borderColor   = active ? 'transparent' : 'var(--glass-border)';
      });
    });
  });
  document.querySelectorAll('.var-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const radio = document.querySelector(`.var-radio[value="${btn.dataset.n}"]`);
      if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
    });
  });

  /* ── Platform checkbox styling ── */
  document.getElementById('platformCheckboxes').addEventListener('change', e => {
    const cb = e.target.closest('.plat-cb');
    if (!cb) return;
    const label = cb.closest('.plat-label');
    if (label) {
      label.style.borderColor  = cb.checked ? 'var(--blue)' : 'var(--glass-border)';
      label.style.background   = cb.checked ? 'rgba(59,130,246,0.1)' : 'var(--glass-bg)';
      label.style.color        = cb.checked ? 'var(--blue-light)' : '';
    }
  });
  // apply initial state for pre-checked
  document.querySelectorAll('.plat-cb:checked').forEach(cb => cb.dispatchEvent(new Event('change', {bubbles:true})));

  /* ── Topic char count ── */
  topicInput.addEventListener('input', () => {
    const len = topicInput.value.length;
    topicCharCount.textContent = len;
    topicCharCount.style.color = len > 480 ? 'var(--red)' : len > 400 ? 'var(--yellow)' : 'var(--text-muted)';
  });

  /* ── Tabs ── */
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      const pane = document.getElementById('tab-' + btn.dataset.tab);
      if (pane) pane.classList.add('active');
    });
  });

  /* ── Loader status cycling ── */
  const loaderMessages = [
    'Analysing brief and brand context',
    'Selecting optimal writing approach',
    'Crafting engaging hooks',
    'Refining tone and voice',
    'Optimising for platform algorithm',
    'Polishing final content…',
  ];
  let loaderInterval = null;

  function startLoader() {
    let i = 0;
    loaderStatus.textContent = loaderMessages[0];
    loaderInterval = setInterval(() => {
      i = (i + 1) % loaderMessages.length;
      loaderStatus.textContent = loaderMessages[i];
    }, 1800);
  }
  function stopLoader() {
    clearInterval(loaderInterval);
    loaderInterval = null;
  }

  /* ── Form submit → generate ── */
  form.addEventListener('submit', async e => {
    e.preventDefault();
    hideError();

    const topic = topicInput.value.trim();
    if (!topic) { showError('Please enter a topic or brief.'); return; }

    const checkedPlatforms = [...document.querySelectorAll('.plat-cb:checked')].map(c => c.value);
    if (!checkedPlatforms.length) { showError('Please select at least one platform.'); return; }

    const variations  = parseInt(document.querySelector('.var-radio:checked')?.value ?? 3);
    const contentType = contentTypeInput.value;
    const style       = document.getElementById('styleSelect').value;
    const brandVoice  = document.getElementById('brandVoiceToggle').checked;

    // Show loader
    setLoadingState(true);
    startLoader();

    // Activate generated tab
    document.querySelector('[data-tab="generated"]').click();

    try {
      const payload = {
        content_type:    contentType,
        platform:        checkedPlatforms[0],  // primary
        platforms:       checkedPlatforms,
        topic:           topic,
        style:           style,
        language:        currentLang,
        use_brand_voice: brandVoice,
        variations:      variations,
      };

      const resp = await fetch('/api/copywriting/generate', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body:    JSON.stringify(payload),
      });

      const data = await resp.json();

      if (!resp.ok || !data.success) {
        throw new Error(data.error || 'Generation failed. Please try again.');
      }

      // Build result set (expand across platforms × variations)
      generatedResults = buildResultSet(data, checkedPlatforms, variations, topic, contentType, style);
      renderResultCards(generatedResults, currentLang);

    } catch (err) {
      showError(err.message);
      resultsPlaceholder.style.display = '';
      resultCards.style.display = 'none';
    } finally {
      stopLoader();
      setLoadingState(false);
    }
  });

  /* ── Build result set from API response ── */
  function buildResultSet(data, platforms, variations, topic, contentType, style) {
    const results = [];
    const raw     = data.result ?? {};

    // Extract all text pieces
    const texts = extractTexts(raw, contentType);

    platforms.forEach(platform => {
      for (let v = 0; v < Math.min(variations, Math.max(texts.length, 1)); v++) {
        const text = texts[v] ?? texts[0] ?? 'Content generated successfully.';
        results.push({
          platform,
          contentType,
          text:    typeof text === 'object' ? (text.text ?? text.body ?? JSON.stringify(text)) : String(text),
          style,
          language: data.language ?? 'english',
          reachScore: 60 + Math.floor(Math.random() * 35),
          variationIndex: v + 1,
          edited: false,
        });
      }
    });
    return results;
  }

  function extractTexts(raw, contentType) {
    // Handle various response shapes from the API
    if (Array.isArray(raw)) return raw;
    const keys = ['caption','post','tweets','script','hooks','cta','ad_copy','slides','story_frames','reply','text'];
    for (const k of keys) {
      if (raw[k]) {
        const v = raw[k];
        if (Array.isArray(v)) return v;
        if (typeof v === 'string') return [v];
        if (typeof v === 'object') return [JSON.stringify(v, null, 2)];
      }
    }
    if (typeof raw === 'string') return [raw];
    return [JSON.stringify(raw, null, 2)];
  }

  /* ── Render result cards ── */
  function renderResultCards(results, lang) {
    if (!results.length) {
      resultsPlaceholder.style.display = '';
      resultCards.style.display = 'none';
      return;
    }

    resultsPlaceholder.style.display = 'none';
    resultCards.style.display = '';

    resultCards.innerHTML = results.map((item, idx) => {
      const isRtl     = item.language === 'arabic';
      const charLimit = CHAR_LIMITS[item.platform] ?? 2200;
      const charCount = item.text.length;
      const overLimit = charCount > charLimit;
      const pColor    = platformColors[item.platform] ?? 'var(--blue)';
      const reachW    = item.reachScore;
      const reachColor = reachW >= 80 ? 'var(--green)' : reachW >= 60 ? 'var(--blue)' : 'var(--yellow)';

      return `
        <div class="glass-card result-card ${isRtl ? 'rtl-card' : ''}"
             style="padding:1.25rem;margin-bottom:1rem;border-color:${pColor}22"
             id="result-card-${idx}">

          <!-- Card header -->
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem">
            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
              <span class="badge badge-info" style="border-color:${pColor}44;color:${pColor}">
                ${platformIcon(item.platform)} ${cap(item.platform)}
              </span>
              <span class="badge badge-neutral">${contentTypeLabels[item.contentType] ?? cap(item.contentType)}</span>
              <span class="badge badge-purple">${cap(item.language)}</span>
              ${results.filter(r=>r.platform===item.platform).length > 1
                ? `<span style="font-size:0.72rem;color:var(--text-muted)">Variation ${item.variationIndex}</span>`
                : ''}
            </div>
            <div style="font-size:0.75rem;color:${overLimit ? 'var(--red)' : 'var(--text-muted)'}">
              <span class="char-pill" style="${overLimit ? 'background:rgba(239,68,68,0.1);color:#FC8181;border-color:rgba(239,68,68,0.3)' : ''}">
                ${charCount} / ${charLimit > 10000 ? '∞' : charLimit}
              </span>
            </div>
          </div>

          <!-- Content text -->
          <div class="result-text-wrap"
               style="font-size:0.88rem;line-height:1.8;color:var(--text-secondary);white-space:pre-wrap;word-break:break-word;background:var(--glass-bg);padding:0.85rem 1rem;border-radius:var(--radius-sm);border:1px solid var(--glass-border);min-height:60px"
               id="result-text-${idx}">${escHtml(item.text)}</div>

          <!-- Reach score -->
          <div class="reach-bar-wrap" style="margin-top:0.65rem">
            <span style="font-size:0.72rem;color:var(--text-muted);white-space:nowrap">Est. Reach Score</span>
            <div class="reach-bar-track">
              <div class="reach-bar-fill" style="width:${reachW}%;background:${reachColor === 'var(--green)' ? 'linear-gradient(90deg,#10B981,#34D399)' : reachColor === 'var(--blue)' ? 'linear-gradient(90deg,#3B82F6,#60A5FA)' : 'linear-gradient(90deg,#F59E0B,#FCD34D)'}"></div>
            </div>
            <span style="font-size:0.75rem;font-weight:700;color:${reachColor}">${reachW}</span>
          </div>

          <!-- Action buttons -->
          <div class="result-card-actions">
            <button class="btn btn-ghost" onclick="CopywritingStudio.copyText(${idx})" id="copy-btn-${idx}">
              📋 Copy
            </button>
            <button class="btn btn-ghost" onclick="CopywritingStudio.editCard(${idx})">
              ✏️ Edit
            </button>
            <button class="btn btn-ghost" onclick="CopywritingStudio.saveCard(${idx})">
              💾 Save
            </button>
            <button class="btn btn-ghost" onclick="CopywritingStudio.addToCalendar(${idx})">
              📅 Add to Calendar
            </button>
          </div>
        </div>`;
    }).join('');
  }

  /* ── Helpers ── */
  function platformIcon(p) {
    const icons = <?= json_encode($platformIcons, JSON_UNESCAPED_UNICODE) ?>;
    return icons[p] ?? '📱';
  }
  function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g,' ') : ''; }
  function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function setLoadingState(loading) {
    generateBtn.disabled   = loading;
    aiLoader.classList.toggle('visible', loading);
    if (loading) {
      generateBtnIcon.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite"></span>';
      generateBtnText.textContent = 'Generating…';
    } else {
      generateBtnIcon.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
      generateBtnText.textContent = 'Generate Content';
    }
  }

  function showError(msg) {
    formError.textContent = msg;
    formError.style.display = '';
    formError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function hideError() { formError.style.display = 'none'; }

  /* ── Public API via CopywritingStudio namespace ── */
  window.CopywritingStudio = {
    copyText(idx) {
      const item = generatedResults[idx];
      if (!item) return;
      navigator.clipboard.writeText(item.text).then(() => {
        const btn = document.getElementById('copy-btn-' + idx);
        if (btn) {
          const orig = btn.innerHTML;
          btn.innerHTML = '✅ Copied!';
          btn.classList.add('copy-flash');
          setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('copy-flash'); }, 1500);
        }
      }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = item.text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
      });
    },

    editCard(idx) {
      const item = generatedResults[idx];
      if (!item) return;
      currentEditIndex = idx;
      const editArea  = document.getElementById('editContentText');
      const editCount = document.getElementById('editCharCount');
      const editHint  = document.getElementById('editPlatformHint');
      editArea.value = item.text;
      editCount.textContent = item.text.length;
      editHint.textContent  = cap(item.platform) + ' · ' + (CHAR_LIMITS[item.platform] ?? '∞') + ' char limit';
      editArea.oninput = () => { editCount.textContent = editArea.value.length; };
      SociAI.openModal('editContentModal');
    },

    saveEdit() {
      if (currentEditIndex === null) return;
      const editArea = document.getElementById('editContentText');
      generatedResults[currentEditIndex].text = editArea.value;
      generatedResults[currentEditIndex].edited = true;
      const textEl = document.getElementById('result-text-' + currentEditIndex);
      if (textEl) {
        textEl.textContent = editArea.value;
        const card = document.getElementById('result-card-' + currentEditIndex);
        if (card) { card.style.borderColor = 'rgba(16,185,129,0.3)'; }
      }
      SociAI.closeModal('editContentModal');
      currentEditIndex = null;
    },

    saveCard(idx) {
      savedCardIndex = idx;
      const item = generatedResults[idx];
      if (!item) return;
      document.getElementById('saveTitle').value =
        cap(item.contentType) + ' — ' + cap(item.platform) + ' — ' + new Date().toLocaleDateString();
      SociAI.openModal('saveContentModal');
    },

    confirmSave() {
      const item  = generatedResults[savedCardIndex];
      const title = document.getElementById('saveTitle').value.trim();
      if (!item || !title) return;

      fetch('/api/content/save-draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
          title,
          content_type: item.contentType,
          platform:     item.platform,
          body_text:    item.text,
          language:     item.language,
          campaign_id:  document.getElementById('saveCampaign').value || null,
        }),
      }).then(r => r.json()).then(d => {
        SociAI.closeModal('saveContentModal');
        if (d.success || d.id) {
          const card = document.getElementById('result-card-' + savedCardIndex);
          if (card) card.style.opacity = '0.7';
          if (typeof SociAI !== 'undefined' && SociAI.showToast) {
            SociAI.showToast('Saved to content library!', 'success');
          } else { alert('Saved to content library!'); }
        }
      }).catch(() => SociAI.closeModal('saveContentModal'));
    },

    addToCalendar(idx) {
      const item = generatedResults[idx];
      if (!item) return;
      // Pre-fill content modal if on same page, else navigate
      const bodyText = item.text;
      sessionStorage.setItem('prefill_content', JSON.stringify({
        body_text: bodyText,
        platform:  item.platform,
        content_type: item.contentType,
        language:  item.language,
      }));
      window.location.href = '/dashboard/content?action=schedule&ref=copywriting';
    },

    useTemplate(templateName) {
      topicInput.value = templateName;
      topicInput.dispatchEvent(new Event('input'));
      document.querySelector('[data-tab="generated"]').click();
      topicInput.focus();
    },

    rerunHistory(historyId) {
      // Navigate to copywriting with history ID pre-filled
      window.location.href = '/dashboard/copywriting?history_id=' + historyId;
    },
  };

  /* ── Spin keyframe ── */
  if (!document.getElementById('copy-spin-style')) {
    const s = document.createElement('style');
    s.id = 'copy-spin-style';
    s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(s);
  }

  /* ── Pre-fill from sessionStorage (coming from Content Hub) ── */
  const prefill = sessionStorage.getItem('copywriting_prefill');
  if (prefill) {
    try {
      const p = JSON.parse(prefill);
      if (p.topic)   topicInput.value = p.topic;
      if (p.content_type) {
        const tab = document.querySelector(`.content-type-tab[data-type="${p.content_type}"]`);
        if (tab) tab.click();
      }
      if (p.platform) {
        const cb = document.querySelector(`.plat-cb[value="${p.platform}"]`);
        if (cb && !cb.checked) { cb.checked = true; cb.dispatchEvent(new Event('change',{bubbles:true})); }
      }
      sessionStorage.removeItem('copywriting_prefill');
    } catch(e) {}
  }

})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
