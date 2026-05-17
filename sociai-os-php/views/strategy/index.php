<?php
$pageTitle  = 'Marketing Strategy';
$activePage = 'strategy';

// Mock data defaults
$strategy = $strategy ?? null;
$analysis = $analysis ?? null;
$pastStrategies = $pastStrategies ?? [
    ['id' => 1, 'date' => '2025-04-10', 'name' => 'Q2 Growth Campaign Strategy', 'type' => 'Marketing Strategy',    'status' => 'active'],
    ['id' => 2, 'date' => '2025-03-01', 'name' => 'Spring Brand Guidelines',      'type' => 'Brand Guidelines',      'status' => 'archived'],
    ['id' => 3, 'date' => '2025-02-14', 'name' => 'February Content Plan',        'type' => 'Content Plan',          'status' => 'archived'],
    ['id' => 4, 'date' => '2025-01-05', 'name' => 'Year-Start Objectives 2025',   'type' => 'Business Goals',        'status' => 'archived'],
    ['id' => 5, 'date' => '2024-12-01', 'name' => 'Holiday Campaign Timeline',    'type' => 'Campaign Timeline',     'status' => 'archived'],
];

$mockAnalysis = [
    'brand' => [
        'name'           => 'Nexus Digital',
        'industry'       => 'B2B SaaS / Technology',
        'tone'           => 'Professional, Innovative, Empowering',
        'target_audience'=> 'Marketing teams at mid-market tech companies (50–500 employees)',
    ],
    'pillars' => [
        ['title' => 'Thought Leadership',  'description' => 'In-depth industry insights, research findings, and expert opinions that establish brand authority.',              'color' => '#3B82F6'],
        ['title' => 'Product Education',   'description' => 'How-to guides, feature spotlights, and use-case walkthroughs that drive product adoption.',                   'color' => '#8B5CF6'],
        ['title' => 'Customer Success',    'description' => 'Case studies, testimonials, and ROI stories that build social proof and reduce sales friction.',              'color' => '#10B981'],
        ['title' => 'Industry Trends',     'description' => 'Curated commentary on market shifts, competitive landscape, and emerging technologies.',                      'color' => '#F59E0B'],
        ['title' => 'Community & Culture', 'description' => 'Behind-the-scenes content, team spotlights, and brand values that humanise the company.',                     'color' => '#EC4899'],
    ],
    'calendar' => [
        ['platform' => 'LinkedIn',   'emoji' => '💼', 'frequency' => 'Daily',        'posts_month' => 30, 'best_time' => '9:00 AM – 11:00 AM'],
        ['platform' => 'Instagram',  'emoji' => '📸', 'frequency' => '4× per week',  'posts_month' => 17, 'best_time' => '11:00 AM & 7:00 PM'],
        ['platform' => 'Twitter/X',  'emoji' => '🐦', 'frequency' => '2× daily',     'posts_month' => 60, 'best_time' => '8:00 AM & 5:00 PM'],
        ['platform' => 'TikTok',     'emoji' => '🎵', 'frequency' => '3× per week',  'posts_month' => 13, 'best_time' => '7:00 PM – 9:00 PM'],
        ['platform' => 'YouTube',    'emoji' => '▶️', 'frequency' => 'Weekly',        'posts_month' => 4,  'best_time' => 'Thursday 2:00 PM'],
        ['platform' => 'Facebook',   'emoji' => '👥', 'frequency' => '3× per week',  'posts_month' => 13, 'best_time' => '1:00 PM – 3:00 PM'],
    ],
    'confidence' => [
        'Brand Voice'       => 94,
        'Target Audience'   => 91,
        'Content Pillars'   => 88,
        'Posting Cadence'   => 85,
        'Platform Selection'=> 97,
        'Campaign Goals'    => 82,
    ],
];
$displayAnalysis = $analysis ?? ($strategy ? $mockAnalysis : null);

$documentTypes = [
    'Marketing Strategy', 'Content Plan', 'Brand Guidelines', 'Tone of Voice',
    'Business Goals', 'Target Audience', 'Competitor References',
    'Visual Identity', 'Content Library', 'Monthly Objectives', 'Campaign Timeline',
];
ob_start();
?>

<div class="strategy-page">

  <!-- ── PAGE HEADER ──────────────────────────────── -->
  <div class="page-header page-header-row" style="margin-bottom:1.5rem">
    <div>
      <h1>Marketing Strategy</h1>
      <p style="color:var(--text-muted);margin-top:0.25rem">Upload your brand strategy documents and let AI extract insights automatically</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('uploadSection').scrollIntoView({behavior:'smooth'})">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Upload Strategy
    </button>
  </div>

  <?php if ($strategy): ?>
  <!-- ── ACTIVE STRATEGY SUMMARY ──────────────────── -->
  <div class="glass-card" style="margin-bottom:1.5rem;border-left:3px solid var(--green)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
      <div style="display:flex;align-items:center;gap:0.75rem">
        <span style="font-size:1.6rem">📋</span>
        <div>
          <h3 style="margin:0"><?= htmlspecialchars($strategy['name'] ?? 'Active Strategy') ?></h3>
          <span style="font-size:0.8rem;color:var(--text-muted)"><?= htmlspecialchars($strategy['type'] ?? 'Marketing Strategy') ?> · Uploaded <?= htmlspecialchars($strategy['date'] ?? date('M j, Y')) ?></span>
        </div>
      </div>
      <span class="badge badge-success badge-dot">Active</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem">
      <div class="metric-card" style="padding:1rem">
        <div class="metric-label">Brand Voice</div>
        <div class="metric-value" style="font-size:1.4rem"><?= htmlspecialchars($strategy['tone'] ?? 'Professional') ?></div>
      </div>
      <div class="metric-card" style="padding:1rem">
        <div class="metric-label">Content Pillars</div>
        <div class="metric-value" style="font-size:1.4rem"><?= $strategy['pillars_count'] ?? 5 ?></div>
      </div>
      <div class="metric-card" style="padding:1rem">
        <div class="metric-label">Platforms Covered</div>
        <div class="metric-value" style="font-size:1.4rem"><?= $strategy['platforms_count'] ?? 6 ?></div>
      </div>
      <div class="metric-card" style="padding:1rem">
        <div class="metric-label">AI Confidence</div>
        <div class="metric-value" style="font-size:1.4rem;color:var(--green)"><?= $strategy['confidence'] ?? '91%' ?></div>
      </div>
    </div>
  </div>
  <?php endif ?>

  <!-- ── UPLOAD SECTION ──────────────────────────── -->
  <div class="glass-card" id="uploadSection" style="margin-bottom:1.5rem">
    <div class="section-header" style="margin-bottom:1.25rem">
      <h3>📤 Upload Strategy Document</h3>
    </div>

    <form id="strategyUploadForm" action="/dashboard/strategy/upload" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">

      <!-- Drag-and-Drop Zone -->
      <div id="dropZone"
           style="border:2px dashed var(--glass-border);border-radius:var(--radius-lg);padding:3rem 2rem;text-align:center;cursor:pointer;transition:all 0.25s;margin-bottom:1.25rem;position:relative"
           ondragover="strategyUpload.onDragOver(event)"
           ondragleave="strategyUpload.onDragLeave(event)"
           ondrop="strategyUpload.onDrop(event)"
           onclick="document.getElementById('strategyFile').click()">
        <input type="file" id="strategyFile" name="document" accept=".pdf,.docx,.doc,.txt"
               style="position:absolute;inset:0;opacity:0;cursor:pointer"
               onchange="strategyUpload.onFileSelect(event)">
        <div id="dropZoneIdle">
          <div style="font-size:3rem;margin-bottom:0.75rem">📁</div>
          <div style="font-size:1rem;font-weight:600;color:var(--text-primary);margin-bottom:0.4rem">Drop your strategy file here or click to browse</div>
          <div style="font-size:0.82rem;color:var(--text-muted)">Supports PDF, DOCX, DOC, TXT · Max 50 MB</div>
        </div>
        <div id="dropZoneSelected" style="display:none">
          <div style="font-size:3rem;margin-bottom:0.75rem">✅</div>
          <div id="selectedFileName" style="font-size:1rem;font-weight:600;color:var(--green);margin-bottom:0.25rem"></div>
          <div id="selectedFileSize" style="font-size:0.8rem;color:var(--text-muted)"></div>
          <button type="button" style="margin-top:0.75rem;font-size:0.8rem;color:var(--blue-light);background:none;border:none;cursor:pointer"
                  onclick="event.stopPropagation();strategyUpload.clearFile()">Remove file</button>
        </div>
      </div>

      <!-- Document Type Selector -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">
        <div class="form-group" style="margin:0">
          <label class="form-label">Document Type</label>
          <select class="form-select" name="document_type">
            <?php foreach ($documentTypes as $type): ?>
            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Strategy Name (optional)</label>
          <input type="text" class="form-input" name="strategy_name" placeholder="e.g. Q3 2025 Growth Strategy">
        </div>
      </div>

      <!-- Upload Progress Bar (hidden by default) -->
      <div id="uploadProgressWrap" style="display:none;margin-bottom:1.25rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem">
          <span style="font-size:0.82rem;color:var(--text-muted)">Uploading &amp; analysing…</span>
          <span id="uploadPct" style="font-size:0.82rem;font-weight:600;color:var(--blue-light)">0%</span>
        </div>
        <div class="progress-bar lg">
          <div id="uploadProgressBar" class="progress-fill" style="width:0%;transition:width 0.3s ease"></div>
        </div>
      </div>

      <!-- Submit -->
      <div style="display:flex;gap:0.75rem;justify-content:flex-end">
        <button type="reset" class="btn btn-ghost" onclick="strategyUpload.clearFile()">Clear</button>
        <button type="submit" class="btn btn-primary" id="analyzeBtn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          Analyze with AI
        </button>
      </div>
    </form>
  </div>

  <?php if ($displayAnalysis): ?>
  <!-- ── ANALYSIS RESULTS ─────────────────────────── -->
  <div class="glass-card" id="analysisResults" style="margin-bottom:1.5rem">
    <div class="section-header" style="margin-bottom:1.5rem">
      <h3>🧠 AI Analysis Results</h3>
      <div style="display:flex;gap:0.75rem">
        <button class="btn btn-ghost btn-sm" onclick="strategyUpload.regenerate()">🔄 Regenerate</button>
        <button class="btn btn-ghost btn-sm" onclick="strategyUpload.editManually()">✏️ Edit Manually</button>
        <button class="btn btn-primary btn-sm" onclick="strategyUpload.acceptStrategy()">✅ Accept Strategy</button>
      </div>
    </div>

    <!-- Brand Summary Card -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.5rem">
      <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:1.25rem">
        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:0.75rem">Brand Summary</div>
        <div style="display:flex;flex-direction:column;gap:0.6rem">
          <div style="display:flex;justify-content:space-between;font-size:0.875rem">
            <span style="color:var(--text-muted)">Brand Name</span>
            <span style="font-weight:600"><?= htmlspecialchars($displayAnalysis['brand']['name']) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.875rem">
            <span style="color:var(--text-muted)">Industry</span>
            <span style="font-weight:600"><?= htmlspecialchars($displayAnalysis['brand']['industry']) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.875rem;align-items:flex-start;gap:0.5rem">
            <span style="color:var(--text-muted);flex-shrink:0">Tone</span>
            <span style="font-weight:600;text-align:right"><?= htmlspecialchars($displayAnalysis['brand']['tone']) ?></span>
          </div>
          <div style="border-top:1px solid var(--glass-border);padding-top:0.6rem;font-size:0.82rem;color:var(--text-muted)">
            <span style="font-weight:600;color:var(--text-secondary)">Target Audience: </span><?= htmlspecialchars($displayAnalysis['brand']['target_audience']) ?>
          </div>
        </div>
      </div>

      <!-- AI Confidence Scores -->
      <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:1.25rem">
        <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:0.75rem">AI Confidence Scores</div>
        <div style="display:flex;flex-direction:column;gap:0.55rem">
          <?php foreach ($displayAnalysis['confidence'] as $label => $score): ?>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:0.2rem">
              <span style="color:var(--text-secondary)"><?= htmlspecialchars($label) ?></span>
              <span style="font-weight:600;color:<?= $score >= 90 ? 'var(--green)' : ($score >= 80 ? 'var(--blue-light)' : 'var(--yellow)') ?>"><?= $score ?>%</span>
            </div>
            <div class="progress-bar sm">
              <div class="progress-fill" style="width:<?= $score ?>%;background:<?= $score >= 90 ? 'var(--green)' : ($score >= 80 ? 'var(--blue)' : 'var(--yellow)') ?>"></div>
            </div>
          </div>
          <?php endforeach ?>
        </div>
      </div>
    </div>

    <!-- Content Pillars -->
    <div style="margin-bottom:1.5rem">
      <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:0.75rem">Content Pillars</div>
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0.75rem">
        <?php foreach ($displayAnalysis['pillars'] as $i => $pillar): ?>
        <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-top:3px solid <?= htmlspecialchars($pillar['color']) ?>;border-radius:var(--radius-md);padding:1rem;transition:all 0.2s"
             onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
          <div style="font-size:0.72rem;font-weight:700;color:<?= htmlspecialchars($pillar['color']) ?>;margin-bottom:0.35rem;text-transform:uppercase;letter-spacing:0.05em">Pillar <?= $i + 1 ?></div>
          <div style="font-size:0.875rem;font-weight:700;margin-bottom:0.4rem;color:var(--text-primary)"><?= htmlspecialchars($pillar['title']) ?></div>
          <div style="font-size:0.75rem;color:var(--text-muted);line-height:1.5"><?= htmlspecialchars($pillar['description']) ?></div>
        </div>
        <?php endforeach ?>
      </div>
    </div>

    <!-- Content Calendar Recommendations -->
    <div>
      <div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:0.75rem">Content Calendar Recommendations</div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Platform</th>
              <th>Posting Frequency</th>
              <th>Posts / Month</th>
              <th>Best Time to Post</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($displayAnalysis['calendar'] as $cal): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:0.5rem">
                  <span style="font-size:1.2rem"><?= $cal['emoji'] ?></span>
                  <span style="font-weight:600"><?= htmlspecialchars($cal['platform']) ?></span>
                </div>
              </td>
              <td><?= htmlspecialchars($cal['frequency']) ?></td>
              <td>
                <span style="font-weight:700;color:var(--blue-light)"><?= $cal['posts_month'] ?></span>
                <span style="color:var(--text-muted);font-size:0.78rem"> posts</span>
              </td>
              <td style="color:var(--text-muted);font-size:0.82rem"><?= htmlspecialchars($cal['best_time']) ?></td>
            </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Action Buttons -->
    <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--glass-border)">
      <button class="btn btn-ghost" onclick="strategyUpload.editManually()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit Manually
      </button>
      <button class="btn btn-ghost" onclick="strategyUpload.regenerate()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
        Regenerate
      </button>
      <button class="btn btn-primary" onclick="strategyUpload.acceptStrategy()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Accept Strategy
      </button>
    </div>
  </div>
  <?php endif ?>

  <!-- ── PAST STRATEGIES TABLE ────────────────────── -->
  <div class="glass-card">
    <div class="section-header" style="margin-bottom:1rem">
      <h3>📚 Past Strategies</h3>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Upload Date</th>
            <th>Strategy Name</th>
            <th>Document Type</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pastStrategies as $s): ?>
          <tr>
            <td style="color:var(--text-muted);font-size:0.82rem"><?= htmlspecialchars(date('M j, Y', strtotime($s['date']))) ?></td>
            <td class="td-primary"><?= htmlspecialchars($s['name']) ?></td>
            <td><span class="badge badge-neutral"><?= htmlspecialchars($s['type']) ?></span></td>
            <td>
              <?php if ($s['status'] === 'active'): ?>
                <span class="badge badge-success badge-dot">Active</span>
              <?php else: ?>
                <span class="badge badge-neutral badge-dot">Archived</span>
              <?php endif ?>
            </td>
            <td>
              <div style="display:flex;gap:0.4rem">
                <button class="btn btn-ghost btn-sm">View</button>
                <button class="btn btn-ghost btn-sm">Re-analyse</button>
                <button class="btn btn-ghost btn-sm" style="color:var(--red-light)"
                        onclick="if(confirm('Delete this strategy?')) window.location.href='/dashboard/strategy/delete/<?= (int)$s['id'] ?>'">Delete</button>
              </div>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
const strategyUpload = {
  clearFile() {
    document.getElementById('strategyFile').value = '';
    document.getElementById('dropZoneIdle').style.display = 'block';
    document.getElementById('dropZoneSelected').style.display = 'none';
  },
  onFileSelect(e) {
    const file = e.target.files[0];
    if (!file) return;
    this._showFile(file);
  },
  onDragOver(e) {
    e.preventDefault();
    e.currentTarget.style.borderColor = 'var(--blue)';
    e.currentTarget.style.background  = 'rgba(59,130,246,0.06)';
  },
  onDragLeave(e) {
    e.currentTarget.style.borderColor = 'var(--glass-border)';
    e.currentTarget.style.background  = '';
  },
  onDrop(e) {
    e.preventDefault();
    e.currentTarget.style.borderColor = 'var(--glass-border)';
    e.currentTarget.style.background  = '';
    const file = e.dataTransfer.files[0];
    if (!file) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('strategyFile').files = dt.files;
    this._showFile(file);
  },
  _showFile(file) {
    document.getElementById('dropZoneIdle').style.display = 'none';
    document.getElementById('dropZoneSelected').style.display = 'block';
    document.getElementById('selectedFileName').textContent = file.name;
    document.getElementById('selectedFileSize').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
  },
  regenerate() {
    const btn = document.querySelector('[onclick*="regenerate"]');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Regenerating…'; }
    setTimeout(() => window.location.reload(), 1800);
  },
  acceptStrategy() {
    const form = document.createElement('form');
    form.method = 'POST'; form.action = '/dashboard/strategy/accept';
    document.body.appendChild(form); form.submit();
  },
  editManually() {
    window.location.href = '/dashboard/strategy/edit';
  }
};

document.getElementById('strategyUploadForm').addEventListener('submit', function(e) {
  const file = document.getElementById('strategyFile').files[0];
  if (!file) { e.preventDefault(); alert('Please select a file to upload.'); return; }
  const wrap = document.getElementById('uploadProgressWrap');
  const bar  = document.getElementById('uploadProgressBar');
  const pct  = document.getElementById('uploadPct');
  wrap.style.display = 'block';
  document.getElementById('analyzeBtn').disabled = true;
  let p = 0;
  const iv = setInterval(() => {
    p = Math.min(p + Math.random() * 12, 92);
    bar.style.width = p + '%';
    pct.textContent = Math.round(p) + '%';
    if (p >= 92) clearInterval(iv);
  }, 200);
});
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
