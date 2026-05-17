<?php
$pageTitle  = 'Trend Hunter';
$activePage = 'trends';

// ── Mock data (controller can override via $hashtags, $platformTrends, $sounds, $competitors, $insights) ──
if (empty($hashtags)) {
    $hashtags = [
        ['tag'=>'#AICreators',       'growth'=>'+340%','posts'=>'2.4M', 'relevance'=>97, 'platforms'=>['linkedin','tiktok','instagram','twitter']],
        ['tag'=>'#FutureOfWork',     'growth'=>'+210%','posts'=>'1.8M', 'relevance'=>94, 'platforms'=>['linkedin','twitter']],
        ['tag'=>'#SustainableBrands','growth'=>'+187%','posts'=>'980K', 'relevance'=>89, 'platforms'=>['instagram','facebook','linkedin']],
        ['tag'=>'#CreatorEconomy',   'growth'=>'+165%','posts'=>'3.1M', 'relevance'=>91, 'platforms'=>['tiktok','instagram','youtube']],
        ['tag'=>'#B2BMarketing',     'growth'=>'+142%','posts'=>'560K', 'relevance'=>86, 'platforms'=>['linkedin']],
        ['tag'=>'#ReelsStrategy',    'growth'=>'+298%','posts'=>'4.7M', 'relevance'=>88, 'platforms'=>['instagram','tiktok']],
        ['tag'=>'#StartupLife',      'growth'=>'+123%','posts'=>'1.2M', 'relevance'=>83, 'platforms'=>['twitter','linkedin','instagram']],
        ['tag'=>'#VideoMarketing',   'growth'=>'+156%','posts'=>'2.0M', 'relevance'=>90, 'platforms'=>['youtube','tiktok','instagram']],
        ['tag'=>'#GrowthHacking',    'growth'=>'+118%','posts'=>'875K', 'relevance'=>85, 'platforms'=>['twitter','linkedin']],
        ['tag'=>'#ViralContent',     'growth'=>'+203%','posts'=>'5.5M', 'relevance'=>92, 'platforms'=>['tiktok','instagram','twitter']],
        ['tag'=>'#SocialSelling',    'growth'=>'+99%', 'posts'=>'430K', 'relevance'=>80, 'platforms'=>['linkedin']],
        ['tag'=>'#PersonalBranding', 'growth'=>'+134%','posts'=>'1.6M', 'relevance'=>87, 'platforms'=>['linkedin','instagram','twitter']],
    ];
}

if (empty($platformTrends)) {
    $platformTrends = [
        'linkedin' => [
            ['topic'=>'AI-driven recruitment strategies in 2025',          'engagement'=>'14.2K','posts'=>'32K'],
            ['topic'=>'The 5-hour CEO: reclaiming executive time with AI', 'engagement'=>'11.8K','posts'=>'28K'],
            ['topic'=>'Remote work is dead — long live async-first culture','engagement'=>'9.4K', 'posts'=>'41K'],
            ['topic'=>'B2B founders: why thought leadership beats ads now', 'engagement'=>'8.7K', 'posts'=>'19K'],
            ['topic'=>'How I grew my newsletter to 100K with zero paid ads', 'engagement'=>'7.3K', 'posts'=>'15K'],
        ],
        'instagram' => [
            ['topic'=>'Before & after brand transformation reels',          'engagement'=>'290K', 'posts'=>'120K'],
            ['topic'=>'Day-in-the-life founder content',                    'engagement'=>'218K', 'posts'=>'87K'],
            ['topic'=>'Product packaging reveal carousels',                 'engagement'=>'185K', 'posts'=>'63K'],
            ['topic'=>'Behind-the-scenes office culture clips',             'engagement'=>'172K', 'posts'=>'95K'],
            ['topic'=>'\"Get ready with me\" CEO edition',                  'engagement'=>'154K', 'posts'=>'44K'],
        ],
        'tiktok' => [
            ['topic'=>'Business mistakes I made so you don\'t have to',    'engagement'=>'4.8M', 'posts'=>'220K'],
            ['topic'=>'POV: running a $1M business from your laptop',       'engagement'=>'3.9M', 'posts'=>'185K'],
            ['topic'=>'Real-time cold-call reactions',                      'engagement'=>'2.7M', 'posts'=>'98K'],
            ['topic'=>'Entrepreneur daily routine check-ins',               'engagement'=>'2.1M', 'posts'=>'140K'],
            ['topic'=>'Startup fails compilation (honest edition)',         'engagement'=>'1.8M', 'posts'=>'76K'],
        ],
        'twitter' => [
            ['topic'=>'Unpopular opinions about SaaS pricing models',       'engagement'=>'38K',  'posts'=>'24K'],
            ['topic'=>'Thread: everything I learned building to $10M ARR',  'engagement'=>'32K',  'posts'=>'18K'],
            ['topic'=>'Hot takes on AI replacing jobs vs augmenting them',  'engagement'=>'29K',  'posts'=>'41K'],
            ['topic'=>'Founder war stories: the hardest week we survived',  'engagement'=>'24K',  'posts'=>'12K'],
            ['topic'=>'Marketing is dead. Long live distribution.',         'engagement'=>'21K',  'posts'=>'16K'],
        ],
        'youtube' => [
            ['topic'=>'Full business autopsy: what killed my startup',      'engagement'=>'890K', 'posts'=>'3.2K'],
            ['topic'=>'Reacting to viral brand campaigns (honest review)',   'engagement'=>'720K', 'posts'=>'2.8K'],
            ['topic'=>'Building in public: month-by-month breakdowns',      'engagement'=>'640K', 'posts'=>'1.9K'],
            ['topic'=>'The exact funnel that generates our leads',          'engagement'=>'580K', 'posts'=>'1.4K'],
            ['topic'=>'AI tools demo: 3 hours of work done in 7 minutes',   'engagement'=>'510K', 'posts'=>'2.1K'],
        ],
    ];
}

if (empty($sounds)) {
    $sounds = [
        ['name'=>'Levitating (Sped-Up)',          'artist'=>'Dua Lipa', 'uses'=>'4.2M', 'growth'=>'+180%'],
        ['name'=>'Paint The Town Red (Remix)',     'artist'=>'Doja Cat', 'uses'=>'3.8M', 'growth'=>'+224%'],
        ['name'=>'As It Was (Trending Edit)',      'artist'=>'Harry Styles', 'uses'=>'2.9M', 'growth'=>'+95%'],
        ['name'=>'Original Sound: hustle era 🔥', 'artist'=>'@motivate.daily', 'uses'=>'1.7M', 'growth'=>'+312%'],
    ];
}

if (empty($competitors)) {
    $competitors = [
        ['name'=>'TechBrand Co.',    'last_post'=>'2 hours ago',   'engagement'=>'12.4K','content_type'=>'Carousel', 'platform'=>'linkedin'],
        ['name'=>'Innovate Studio',  'last_post'=>'5 hours ago',   'engagement'=>'8.7K', 'content_type'=>'Reel',     'platform'=>'instagram'],
        ['name'=>'GrowthLab HQ',     'last_post'=>'Yesterday',     'engagement'=>'34.2K','content_type'=>'Thread',   'platform'=>'twitter'],
        ['name'=>'DigitalFirst Inc.','last_post'=>'3 hours ago',   'engagement'=>'5.1K', 'content_type'=>'Video',    'platform'=>'tiktok'],
        ['name'=>'CloudScale SaaS',  'last_post'=>'8 hours ago',   'engagement'=>'9.3K', 'content_type'=>'Article',  'platform'=>'linkedin'],
    ];
}

if (empty($insights)) {
    $insights = [
        [
            'title'      => 'Ride the #AICreators Wave',
            'desc'       => 'Post a 60-second TikTok or Reel showing one real AI tool integration from your workflow. This content type is converting 3x above average right now.',
            'confidence' => 96,
            'urgency'    => 'high',
            'platforms'  => ['tiktok','instagram'],
        ],
        [
            'title'      => 'Publish a LinkedIn Thought-Leadership Thread',
            'desc'       => '#FutureOfWork is spiking on LinkedIn. A 5-point opinion post on async culture or AI hiring could hit 10K+ impressions this week.',
            'confidence' => 91,
            'urgency'    => 'medium',
            'platforms'  => ['linkedin'],
        ],
        [
            'title'      => 'React to a Competitor\'s Viral Post',
            'desc'       => 'GrowthLab HQ\'s latest thread (34K engagements) is sparking debate. A thoughtful counter-perspective could drive significant discovery reach.',
            'confidence' => 84,
            'urgency'    => 'medium',
            'platforms'  => ['twitter','linkedin'],
        ],
    ];
}

$lastScanned = $lastScanned ?? date('M j, Y \a\t g:i A', strtotime('-45 minutes'));

$platformIcons = [
    'linkedin'  => '💼',
    'instagram' => '📸',
    'tiktok'    => '♪',
    'twitter'   => '𝕏',
    'facebook'  => '👥',
    'youtube'   => '▶',
];
$urgencyStyles = [
    'high'   => 'background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.3);',
    'medium' => 'background:rgba(245,158,11,0.1);border-color:rgba(245,158,11,0.3);',
    'low'    => 'background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.3);',
];
$urgencyBadge = [
    'high'   => 'badge-danger',
    'medium' => 'badge-warning',
    'low'    => 'badge-success',
];
?>
<?php ob_start(); ?>

<style>
  .hashtag-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 0.9rem; }
  .hashtag-card {
    background: var(--glass-bg); border: 1px solid var(--glass-border);
    border-radius: var(--radius-md); padding: 1rem; transition: all var(--transition);
    display: flex; flex-direction: column; gap: 0.5rem;
  }
  .hashtag-card:hover { border-color: var(--glass-border-hover); transform: translateY(-2px); box-shadow: var(--shadow-md); }
  .hashtag-name { font-size: 0.95rem; font-weight: 700; color: var(--blue-light); }
  .growth-badge { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 600; color: var(--green-light); }
  .relevance-bar { height: 5px; background: rgba(255,255,255,0.08); border-radius: 3px; overflow: hidden; }
  .relevance-fill { height: 100%; border-radius: 3px; background: var(--gradient-primary); }
  .platform-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; }
  .platform-chip { font-size: 0.65rem; padding: 2px 6px; border-radius: 99px; background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--text-secondary); }
  .platform-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 1rem; }
  .platform-tab {
    padding: 0.4rem 1rem; border-radius: 99px; font-size: 0.8rem; font-weight: 500;
    border: 1px solid var(--glass-border); background: var(--glass-bg);
    color: var(--text-secondary); cursor: pointer; transition: all var(--transition);
  }
  .platform-tab:hover { background: var(--glass-bg-hover); color: var(--text-primary); }
  .platform-tab.active { background: var(--gradient-primary); color: #fff; border-color: transparent; }
  .platform-panel { display: none; }
  .platform-panel.active { display: block; }
  .trend-row { display: flex; align-items: center; justify-content: space-between; padding: 0.7rem 0; border-bottom: 1px solid var(--glass-border); gap: 0.75rem; }
  .trend-row:last-child { border-bottom: none; }
  .sound-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px,1fr)); gap: 0.9rem; }
  .sound-card {
    background: var(--glass-bg); border: 1px solid var(--glass-border);
    border-radius: var(--radius-md); padding: 1rem; transition: all var(--transition);
  }
  .sound-card:hover { border-color: var(--glass-border-hover); }
  .insight-card {
    border: 1px solid var(--glass-border); border-radius: var(--radius-md);
    padding: 1.1rem; transition: all var(--transition);
  }
  .insight-card:hover { border-color: var(--glass-border-hover); }
  .badge-danger { background: rgba(239,68,68,0.15); color: #FC8181; border: 1px solid rgba(239,68,68,0.3); border-radius: 99px; padding: 2px 8px; font-size: 0.7rem; font-weight: 600; }
  .badge-warning { background: rgba(249,115,22,0.15); color: #FDBA74; border: 1px solid rgba(249,115,22,0.3); border-radius: 99px; padding: 2px 8px; font-size: 0.7rem; font-weight: 600; }
  #scanOverlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(6px); z-index:1100; align-items:center; justify-content:center; flex-direction:column; gap:1rem; }
  .scan-spinner { width:56px; height:56px; border:4px solid rgba(59,130,246,0.2); border-top-color:var(--blue); border-radius:50%; animation:spin 0.8s linear infinite; }
  @keyframes spin { to { transform:rotate(360deg); } }
</style>

<!-- ── Page Header ─────────────────────────── -->
<div class="page-header page-header-row" style="margin-bottom:1.5rem">
  <div>
    <h1>🔥 Trend Hunter</h1>
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:0.25rem">
      <p style="margin:0">Discover viral trends before they peak — stay ahead of every platform</p>
      <span style="font-size:0.78rem;color:var(--text-muted)">Last scanned: <span style="color:var(--text-secondary)"><?= htmlspecialchars($lastScanned) ?></span></span>
      <span style="font-size:0.75rem;padding:0.25rem 0.65rem;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.25);border-radius:99px;color:var(--green-light)">
        🔄 Auto-scan every 6 hours
      </span>
    </div>
  </div>
  <button class="btn btn-primary" id="scanNowBtn" onclick="startScan()">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 4v6h6"/><path d="M23 20v-6h-6"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10M23 14l-4.64 4.36A9 9 0 013.51 15"/></svg>
    Scan Now
  </button>
</div>

<!-- ── Top Trending Hashtags ──────────────── -->
<div class="glass-card" style="margin-bottom:1.5rem">
  <div class="section-header" style="margin-bottom:1.25rem">
    <h3>🏷️ Top Trending Hashtags</h3>
    <span style="font-size:0.8rem;color:var(--text-muted)">Updated <?= htmlspecialchars($lastScanned) ?></span>
  </div>
  <div class="hashtag-grid">
    <?php foreach ($hashtags as $i => $h): ?>
    <div class="hashtag-card">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <span class="hashtag-name"><?= htmlspecialchars($h['tag']) ?></span>
        <span style="font-size:0.7rem;font-weight:700;color:var(--text-muted);background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:99px;padding:2px 7px">#<?= $i+1 ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:0.5rem">
        <span class="growth-badge">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
          <?= htmlspecialchars($h['growth']) ?>
        </span>
        <span style="font-size:0.73rem;color:var(--text-muted)"><?= htmlspecialchars($h['posts']) ?> posts</span>
      </div>
      <div>
        <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--text-muted);margin-bottom:3px">
          <span>Relevance</span>
          <span style="color:var(--text-secondary);font-weight:600"><?= $h['relevance'] ?>%</span>
        </div>
        <div class="relevance-bar">
          <div class="relevance-fill" style="width:<?= $h['relevance'] ?>%"></div>
        </div>
      </div>
      <div class="platform-chips">
        <?php foreach ($h['platforms'] as $plt): ?>
        <span class="platform-chip"><?= htmlspecialchars($platformIcons[$plt] ?? $plt) ?> <?= ucfirst($plt) ?></span>
        <?php endforeach ?>
      </div>
      <button class="btn btn-sm btn-primary btn-block" style="font-size:0.75rem;padding:0.35rem 0.75rem" onclick="useInContent('<?= htmlspecialchars($h['tag'], ENT_QUOTES) ?>')">
        Use in Content
      </button>
    </div>
    <?php endforeach ?>
  </div>
</div>

<!-- ── Platform Trends ────────────────────── -->
<div class="glass-card" style="margin-bottom:1.5rem">
  <div class="section-header" style="margin-bottom:1rem">
    <h3>📱 Platform Trends</h3>
    <span style="font-size:0.8rem;color:var(--text-muted)">Top 5 topics per platform</span>
  </div>

  <div class="platform-tabs" id="platformTabs">
    <?php $first = true; foreach (array_keys($platformTrends) as $pName): ?>
    <button class="platform-tab <?= $first ? 'active' : '' ?>" data-platform="<?= $pName ?>">
      <?= $platformIcons[$pName] ?? '' ?> <?= ucfirst($pName) ?>
    </button>
    <?php $first = false; endforeach ?>
  </div>

  <?php $first = true; foreach ($platformTrends as $pName => $topics): ?>
  <div class="platform-panel <?= $first ? 'active' : '' ?>" id="panel-<?= $pName ?>">
    <?php foreach ($topics as $idx => $topic): ?>
    <div class="trend-row">
      <div style="display:flex;align-items:flex-start;gap:0.75rem;flex:1;min-width:0">
        <span style="width:24px;height:24px;border-radius:50%;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:#fff;flex-shrink:0"><?= $idx+1 ?></span>
        <span style="font-size:0.85rem;color:var(--text-primary)"><?= htmlspecialchars($topic['topic']) ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:0.75rem;flex-shrink:0">
        <div style="text-align:right">
          <div style="font-size:0.75rem;font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($topic['engagement']) ?></div>
          <div style="font-size:0.68rem;color:var(--text-muted)">engagements</div>
        </div>
        <button class="btn btn-ghost btn-sm" style="font-size:0.72rem" onclick="createFromTrend(<?= htmlspecialchars(json_encode($topic['topic']), ENT_QUOTES) ?>, '<?= $pName ?>')">Create</button>
      </div>
    </div>
    <?php endforeach ?>
  </div>
  <?php $first = false; endforeach ?>
</div>

<!-- ── Viral Sounds ──────────────────────── -->
<div class="glass-card" style="margin-bottom:1.5rem">
  <div class="section-header" style="margin-bottom:1.25rem">
    <h3>🎵 Viral Sounds</h3>
    <span style="font-size:0.78rem;color:var(--text-muted)">TikTok &amp; Instagram Reels</span>
  </div>
  <div class="sound-grid">
    <?php foreach ($sounds as $sound): ?>
    <div class="sound-card">
      <div style="display:flex;align-items:center;gap:0.65rem;margin-bottom:0.6rem">
        <div style="width:40px;height:40px;background:var(--gradient-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0">🎵</div>
        <div style="min-width:0">
          <div style="font-size:0.85rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($sound['name']) ?></div>
          <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($sound['artist']) ?></div>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:0.75rem">
        <span style="color:var(--text-muted)"><?= htmlspecialchars($sound['uses']) ?> uses</span>
        <span class="growth-badge">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
          <?= htmlspecialchars($sound['growth']) ?>
        </span>
      </div>
      <button class="btn btn-sm btn-primary btn-block" style="font-size:0.75rem" onclick="createReel(<?= htmlspecialchars(json_encode($sound['name']), ENT_QUOTES) ?>)">
        🎬 Create Reel
      </button>
    </div>
    <?php endforeach ?>
  </div>
</div>

<!-- ── Competitor Moves ───────────────────── -->
<div class="glass-card" style="margin-bottom:1.5rem">
  <div class="section-header" style="margin-bottom:1rem">
    <h3>🕵️ Competitor Moves</h3>
    <span style="font-size:0.78rem;color:var(--text-muted)">Latest competitor activity</span>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Competitor</th>
          <th>Platform</th>
          <th>Last Post</th>
          <th>Content Type</th>
          <th>Engagement</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($competitors as $comp): ?>
        <tr>
          <td class="td-primary"><?= htmlspecialchars($comp['name']) ?></td>
          <td>
            <span class="platform-badge platform-<?= htmlspecialchars($comp['platform']) ?>">
              <?= $platformIcons[$comp['platform']] ?? '' ?> <?= ucfirst($comp['platform']) ?>
            </span>
          </td>
          <td style="color:var(--text-muted);font-size:0.82rem"><?= htmlspecialchars($comp['last_post']) ?></td>
          <td><span class="badge badge-neutral"><?= htmlspecialchars($comp['content_type']) ?></span></td>
          <td style="font-weight:600"><?= htmlspecialchars($comp['engagement']) ?></td>
          <td>
            <button class="btn btn-sm btn-ghost" style="font-size:0.75rem" onclick="reactToCompetitor(<?= htmlspecialchars(json_encode($comp), ENT_QUOTES) ?>)">
              React to This
            </button>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── AI Trend Insights ──────────────────── -->
<div class="glass-card">
  <div class="section-header" style="margin-bottom:1.25rem">
    <h3>✨ AI Trend Insights</h3>
    <span style="font-size:0.78rem;color:var(--text-muted)">Recommended content ideas</span>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem">
    <?php foreach ($insights as $insight): ?>
    <div class="insight-card" style="<?= $urgencyStyles[$insight['urgency']] ?? '' ?>">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.6rem">
        <span style="font-size:0.85rem;font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($insight['title']) ?></span>
        <span class="badge <?= $urgencyBadge[$insight['urgency']] ?? 'badge-neutral' ?>"><?= ucfirst($insight['urgency']) ?> urgency</span>
      </div>
      <p style="font-size:0.8rem;margin-bottom:0.75rem;line-height:1.55"><?= htmlspecialchars($insight['desc']) ?></p>
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem">
        <div style="display:flex;align-items:center;gap:0.35rem;font-size:0.72rem;color:var(--text-muted)">
          AI Confidence:
          <span style="font-weight:700;color:var(--green-light)"><?= $insight['confidence'] ?>%</span>
          <div style="width:60px;height:4px;background:rgba(255,255,255,0.08);border-radius:2px;overflow:hidden">
            <div style="width:<?= $insight['confidence'] ?>%;height:100%;background:var(--green);border-radius:2px"></div>
          </div>
        </div>
        <div class="platform-chips">
          <?php foreach ($insight['platforms'] as $plt): ?>
          <span class="platform-chip"><?= $platformIcons[$plt] ?? $plt ?></span>
          <?php endforeach ?>
        </div>
        <button class="btn btn-sm btn-primary" onclick="createFromInsight(<?= htmlspecialchars(json_encode($insight['title']), ENT_QUOTES) ?>)">
          Create Content
        </button>
      </div>
    </div>
    <?php endforeach ?>
  </div>
</div>

<!-- ── Scan Overlay ─────────────────────── -->
<div id="scanOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(6px);z-index:1100;align-items:center;justify-content:center;flex-direction:column;gap:1rem">
  <div class="scan-spinner"></div>
  <p style="color:var(--text-primary);font-weight:600;font-size:1rem">Scanning trends across 6 platforms...</p>
  <p style="color:var(--text-muted);font-size:0.82rem" id="scanStatus">Connecting to platform APIs</p>
</div>

<!-- Toast -->
<div id="trendsToast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:2000;display:none">
  <div style="background:var(--navy-mid);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:0.75rem 1.25rem;font-size:0.85rem;box-shadow:var(--shadow-md)" id="trendsToastText"></div>
</div>

<script>
(function() {
  // ── Platform Tab Switching ────────────────────
  const tabBtns   = document.querySelectorAll('.platform-tab');
  const tabPanels = document.querySelectorAll('.platform-panel');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('active'));
      tabPanels.forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      const panel = document.getElementById('panel-' + btn.dataset.platform);
      if (panel) panel.classList.add('active');
    });
  });

  // ── Scan Now ─────────────────────────────────
  window.startScan = function() {
    const overlay    = document.getElementById('scanOverlay');
    const statusEl   = document.getElementById('scanStatus');
    const scanBtn    = document.getElementById('scanNowBtn');
    const steps      = [
      'Connecting to platform APIs',
      'Fetching LinkedIn trending topics...',
      'Analysing TikTok viral content...',
      'Scanning Instagram hashtags...',
      'Checking Twitter/X trending...',
      'Processing YouTube analytics...',
      'Running AI relevance scoring...',
      'Generating content recommendations...',
      'Finalising report...',
    ];
    overlay.style.display = 'flex';
    scanBtn.disabled = true;
    let step = 0;
    const interval = setInterval(() => {
      if (step < steps.length) {
        statusEl.textContent = steps[step++];
      }
    }, 600);

    fetch('/api/trends/scan', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
    })
    .finally(() => {
      clearInterval(interval);
      statusEl.textContent = 'Scan complete! Refreshing data...';
      setTimeout(() => {
        overlay.style.display = 'none';
        scanBtn.disabled = false;
        showTrendsToast('Trend scan complete! Data refreshed.');
        window.location.reload();
      }, 1200);
    });
  };

  // ── Use in Content ─────────────────────────
  window.useInContent = function(tag) {
    showTrendsToast('Opening content editor with ' + tag);
    setTimeout(() => window.location.href = '/dashboard/copywriting?hashtag=' + encodeURIComponent(tag), 800);
  };

  // ── Create from Trend ──────────────────────
  window.createFromTrend = function(topic, platform) {
    showTrendsToast('Creating content for: ' + topic.substring(0, 40) + '...');
    setTimeout(() => window.location.href = '/dashboard/copywriting?topic=' + encodeURIComponent(topic) + '&platform=' + platform, 800);
  };

  // ── Create Reel ────────────────────────────
  window.createReel = function(soundName) {
    showTrendsToast('Starting Reel creation with: ' + soundName);
    setTimeout(() => window.location.href = '/dashboard/content?type=reel&sound=' + encodeURIComponent(soundName), 800);
  };

  // ── React to Competitor ────────────────────
  window.reactToCompetitor = function(comp) {
    showTrendsToast('Generating counter-content strategy for ' + comp.name + '...');
    setTimeout(() => window.location.href = '/dashboard/copywriting?competitor=' + encodeURIComponent(comp.name) + '&platform=' + comp.platform, 1200);
  };

  // ── Create from Insight ────────────────────
  window.createFromInsight = function(title) {
    showTrendsToast('Launching AI writer for: ' + title.substring(0, 40) + '...');
    setTimeout(() => window.location.href = '/dashboard/copywriting?insight=' + encodeURIComponent(title), 800);
  };

  // ── Toast ──────────────────────────────────
  window.showTrendsToast = function(msg) {
    const t = document.getElementById('trendsToast');
    document.getElementById('trendsToastText').textContent = msg;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3500);
  };
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
