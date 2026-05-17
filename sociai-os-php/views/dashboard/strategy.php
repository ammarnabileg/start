<?php
$pageTitle  = 'Strategy Intelligence';
$activePage = 'strategy';
$docTypes = [
  ['icon'=>'📋','name'=>'Brand Guide',        'desc'=>'Brand voice, values, visual identity'],
  ['icon'=>'🎯','name'=>'Marketing Plan',     'desc'=>'Goals, budget, channel strategy'],
  ['icon'=>'👤','name'=>'Customer Personas',  'desc'=>'Target audience profiles'],
  ['icon'=>'📊','name'=>'Competitor Analysis','desc'=>'Market positioning data'],
  ['icon'=>'📅','name'=>'Content Calendar',   'desc'=>'Editorial schedule & themes'],
  ['icon'=>'💰','name'=>'Product Catalog',    'desc'=>'Products, services, pricing'],
  ['icon'=>'🏆','name'=>'Case Studies',       'desc'=>'Success stories & testimonials'],
  ['icon'=>'📰','name'=>'Press Releases',     'desc'=>'News & announcements'],
  ['icon'=>'📈','name'=>'Analytics Report',   'desc'=>'Historical performance data'],
  ['icon'=>'🎨','name'=>'Creative Brief',     'desc'=>'Campaign concepts & visuals'],
  ['icon'=>'📜','name'=>'Mission Statement',  'desc'=>'Company vision & values'],
];
?>
<?php ob_start() ?>
<div class="page-header page-header-row">
  <div>
    <h1>Strategy Intelligence 🧠</h1>
    <p>Upload your brand documents and let AI analyze your strategy in seconds</p>
  </div>
  <div class="live-indicator"><span class="live-dot"></span>AI Analysis Ready</div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem">

  <!-- Left: Upload -->
  <div>
    <div class="glass-card mb-4 upload-section">
      <h3 style="margin-bottom:1rem">📁 Upload Brand Documents</h3>
      <div class="upload-zone">
        <div class="upload-icon">📤</div>
        <h3>Drop files here or click to browse</h3>
        <p style="margin:0.5rem 0">Supports PDF, Word, Excel, PowerPoint, Images, Video</p>
        <p style="font-size:0.75rem;color:var(--text-muted)">Max 50MB per file · Multiple files supported</p>
        <button class="btn btn-primary" style="margin-top:1rem" onclick="event.stopPropagation()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Choose Files
        </button>
      </div>

      <div class="upload-progress" style="display:none;margin-top:1rem">
        <div style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:0.5rem">Analyzing documents with AI...</div>
        <div class="progress-bar"><div class="progress-fill" style="width:0%;animation:shimmer 1.5s infinite;background-size:200% 100%"></div></div>
      </div>

      <!-- Doc Type Grid -->
      <div style="margin-top:1.5rem">
        <div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem">Accepted Document Types</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.5rem">
          <?php foreach ($docTypes as $doc): ?>
          <div style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);font-size:0.75rem;color:var(--text-secondary)">
            <span><?= $doc['icon'] ?></span>
            <div>
              <div style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($doc['name']) ?></div>
              <div style="font-size:0.68rem"><?= htmlspecialchars($doc['desc']) ?></div>
            </div>
          </div>
          <?php endforeach ?>
        </div>
      </div>
    </div>

    <!-- Analysis Results -->
    <div class="glass-card analysis-results" style="display:none">
      <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem">
        <span style="font-size:1.3rem">🎯</span>
        <h3>AI Analysis Complete</h3>
        <span class="badge badge-success badge-dot">Analyzed</span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <!-- Brand Voice -->
        <div style="background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);border-radius:var(--radius-md);padding:1.25rem">
          <div style="font-size:0.8rem;font-weight:700;color:var(--blue-light);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem">Brand Voice</div>
          <?php foreach(['Professional yet approachable','Data-driven & insightful','Empowering & motivating','Clear and concise'] as $t): ?>
          <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.82rem;color:var(--text-secondary);margin-bottom:0.3rem"><span style="color:var(--green)">✓</span><?= $t ?></div>
          <?php endforeach ?>
        </div>

        <!-- Target Audience -->
        <div style="background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2);border-radius:var(--radius-md);padding:1.25rem">
          <div style="font-size:0.8rem;font-weight:700;color:var(--purple-light);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem">Target Audience</div>
          <?php foreach(['Entrepreneurs 25-45','Marketing professionals','SMB business owners','Tech-forward leaders'] as $t): ?>
          <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.82rem;color:var(--text-secondary);margin-bottom:0.3rem"><span style="color:var(--purple)">✓</span><?= $t ?></div>
          <?php endforeach ?>
        </div>

        <!-- Content Pillars -->
        <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius-md);padding:1.25rem">
          <div style="font-size:0.8rem;font-weight:700;color:var(--green-light);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem">Content Pillars</div>
          <?php foreach(['🎓 Education & Tips (35%)','💡 Industry Insights (25%)','🏆 Success Stories (20%)','🛠️ Product Features (20%)'] as $t): ?>
          <div style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:0.35rem"><?= $t ?></div>
          <?php endforeach ?>
        </div>

        <!-- Goals -->
        <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:var(--radius-md);padding:1.25rem">
          <div style="font-size:0.8rem;font-weight:700;color:#FCD34D;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.75rem">Business Goals</div>
          <?php foreach(['Grow followers to 500K','Increase lead gen by 40%','Build thought leadership','Launch 3 new markets'] as $t): ?>
          <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.82rem;color:var(--text-secondary);margin-bottom:0.3rem"><span style="color:var(--yellow)">→</span><?= $t ?></div>
          <?php endforeach ?>
        </div>
      </div>

      <div style="margin-top:1.25rem;padding:1rem;background:rgba(59,130,246,0.05);border:1px solid rgba(59,130,246,0.15);border-radius:var(--radius-md)">
        <div style="font-size:0.85rem;font-weight:600;margin-bottom:0.5rem">🤖 AI Recommendation</div>
        <p style="font-size:0.85rem;margin:0">Based on your documents, I recommend a <strong>60/40 educational-promotional content mix</strong> with heavy emphasis on LinkedIn and Instagram. Your brand voice is best suited for long-form storytelling and data-driven posts. Optimal posting frequency: 3x/day across platforms.</p>
      </div>

      <button class="btn btn-primary btn-lg btn-block" style="margin-top:1.25rem">
        🚀 Activate AI Strategy System
      </button>
    </div>
  </div>

  <!-- Right: Content Mix Chart -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">
    <div class="glass-card">
      <h3 style="margin-bottom:1.25rem">📊 Recommended Content Mix</h3>
      <div class="donut-chart" data-segments='[{"pct":35,"color":"#3B82F6"},{"pct":25,"color":"#8B5CF6"},{"pct":20,"color":"#10B981"},{"pct":20,"color":"#F59E0B"}]' style="width:180px;height:180px;margin:0 auto 1rem">
        <svg class="donut-svg" width="180" height="180" viewBox="0 0 180 180"></svg>
        <div class="donut-center"><span class="val">100%</span><span class="lbl">Content Mix</span></div>
      </div>
      <div class="chart-legend">
        <div class="legend-item"><span class="legend-dot" style="background:#3B82F6"></span>Education 35%</div>
        <div class="legend-item"><span class="legend-dot" style="background:#8B5CF6"></span>Insights 25%</div>
        <div class="legend-item"><span class="legend-dot" style="background:#10B981"></span>Stories 20%</div>
        <div class="legend-item"><span class="legend-dot" style="background:#F59E0B"></span>Product 20%</div>
      </div>
    </div>

    <div class="glass-card">
      <h3 style="margin-bottom:1rem">🗓️ AI Posting Schedule</h3>
      <?php
      $schedule = [
        ['platform'=>'LinkedIn',  'time'=>'8:00 AM',  'freq'=>'Daily',      'type'=>'Articles & posts'],
        ['platform'=>'Instagram', 'time'=>'12:00 PM', 'freq'=>'2x Daily',   'type'=>'Reels & carousels'],
        ['platform'=>'TikTok',    'time'=>'6:00 PM',  'freq'=>'Daily',      'type'=>'Short videos'],
        ['platform'=>'Twitter/X', 'time'=>'10:00 AM', 'freq'=>'3x Daily',   'type'=>'Threads & replies'],
        ['platform'=>'Facebook',  'time'=>'3:00 PM',  'freq'=>'Daily',      'type'=>'Posts & stories'],
      ];
      ?>
      <?php foreach ($schedule as $s): ?>
      <div style="display:flex;justify-content:space-between;padding:0.65rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem">
        <div style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($s['platform']) ?></div>
        <div style="color:var(--text-muted)"><?= htmlspecialchars($s['time']) ?> · <?= htmlspecialchars($s['freq']) ?></div>
      </div>
      <?php endforeach ?>
    </div>

    <div class="glass-card" style="background:linear-gradient(135deg,rgba(59,130,246,0.1),rgba(139,92,246,0.1));border-color:rgba(59,130,246,0.3)">
      <div style="font-size:1.3rem;margin-bottom:0.5rem">🎯</div>
      <h3 style="margin-bottom:0.5rem">Strategy Score</h3>
      <div style="font-size:3rem;font-weight:800;color:var(--blue-light);margin-bottom:0.25rem">92<span style="font-size:1rem;color:var(--text-muted)">/100</span></div>
      <p style="font-size:0.82rem;margin:0">Your strategy is highly optimized. Upload more documents to improve your score.</p>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
