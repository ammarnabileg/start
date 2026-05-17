<?php
$pageTitle  = 'Trend Hunter';
$activePage = 'trends';
$trends = [
  ['topic'=>'#AIAgents',          'platform'=>'linkedin',  'score'=>97,'growth'=>'+340%','volume'=>'2.1M posts','angle'=>'How autonomous AI agents are replacing entire social media teams','hashtags'=>['#AI','#Automation','#Future','#Tech','#AgentAI']],
  ['topic'=>'#CreatorEconomy2025','platform'=>'tiktok',    'score'=>94,'growth'=>'+280%','volume'=>'8.7M posts','angle'=>'The new rules for monetizing your audience in 2025','hashtags'=>['#Creator','#Monetize','#TikTok','#ContentCreator','#Brand']],
  ['topic'=>'#SustainableBrands', 'platform'=>'instagram', 'score'=>91,'growth'=>'+215%','volume'=>'4.3M posts','angle'=>'Brands that put sustainability first are winning Gen-Z loyalty','hashtags'=>['#Sustainable','#GreenBrand','#EcoFriendly','#GenZ','#CSR']],
  ['topic'=>'#RemoteWorkTools',   'platform'=>'twitter',   'score'=>88,'growth'=>'+175%','volume'=>'1.8M posts','angle'=>'The 10 productivity tools every remote team is using in 2025','hashtags'=>['#RemoteWork','#Productivity','#WFH','#Tools','#Tech']],
  ['topic'=>'#ViralMarketing',    'platform'=>'instagram', 'score'=>85,'growth'=>'+155%','volume'=>'3.2M posts','angle'=>'What makes content go viral? Unpacking the algorithm secrets','hashtags'=>['#Marketing','#Viral','#Content','#Growth','#Algorithm']],
  ['topic'=>'#AIHealthcare',      'platform'=>'linkedin',  'score'=>83,'growth'=>'+130%','volume'=>'890K posts','angle'=>'How AI is transforming patient outcomes and healthcare efficiency','hashtags'=>['#AI','#Healthcare','#MedTech','#Innovation','#Digital']],
  ['topic'=>'#DigitalNomad2025',  'platform'=>'tiktok',    'score'=>80,'growth'=>'+120%','volume'=>'5.6M posts','angle'=>'The reality of working from anywhere: income, lifestyle, tips','hashtags'=>['#DigitalNomad','#Travel','#Freedom','#Remote','#Lifestyle']],
  ['topic'=>'#B2BMarketing',      'platform'=>'linkedin',  'score'=>78,'growth'=>'+95%', 'volume'=>'1.1M posts','angle'=>'LinkedIn strategies that actually convert to pipeline in 2025','hashtags'=>['#B2B','#LinkedIn','#Marketing','#Sales','#Growth']],
];
$sounds = [
  ['name'=>'Epic Drop',          'artist'=>'Trending Audio','uses'=>'2.1M','growth'=>'+450%'],
  ['name'=>'Motivational Rise',  'artist'=>'Background',    'uses'=>'1.8M','growth'=>'+320%'],
  ['name'=>'Corporate Beat',     'artist'=>'BizPodcast',    'uses'=>'980K','growth'=>'+280%'],
  ['name'=>'Story Tension',      'artist'=>'CinemaFX',      'uses'=>'756K','growth'=>'+215%'],
];
?>
<?php ob_start() ?>
<div class="trends-page">
  <div class="page-header page-header-row">
    <div>
      <h1>Trend Hunter 🔥</h1>
      <p>AI-powered real-time trend detection across all 11 platforms</p>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center">
      <div class="live-indicator"><span class="live-dot"></span>Scanning Live</div>
      <button class="btn btn-ghost" onclick="SociAI.showToast('Refreshing trends...','info')">🔄 Refresh</button>
    </div>
  </div>

  <!-- Platform Filters -->
  <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <button class="platform-filter-btn btn btn-primary btn-sm" data-platform="all">🌐 All Platforms</button>
    <?php foreach(['linkedin'=>'💼 LinkedIn','instagram'=>'📸 Instagram','tiktok'=>'🎵 TikTok','twitter'=>'🐦 Twitter/X','facebook'=>'👥 Facebook','youtube'=>'▶️ YouTube'] as $k=>$v): ?>
    <button class="platform-filter-btn btn btn-ghost btn-sm" data-platform="<?= $k ?>"><?= $v ?></button>
    <?php endforeach ?>
  </div>

  <!-- Trend Cards Grid -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1rem;margin-bottom:2rem">
    <?php foreach ($trends as $t): ?>
    <div class="trend-card" data-platform="<?= $t['platform'] ?>">
      <div class="trend-header">
        <div>
          <div class="trend-name"><?= htmlspecialchars($t['topic']) ?></div>
          <span class="platform-badge platform-<?= $t['platform'] ?>" style="margin-top:0.3rem;display:inline-flex"><?= ucfirst($t['platform']) ?></span>
        </div>
        <div class="virality-score"><?= $t['score'] ?>/100</div>
      </div>
      <div class="trend-stats">
        <span>📈 <?= htmlspecialchars($t['growth']) ?></span>
        <span>📝 <?= htmlspecialchars($t['volume']) ?></span>
      </div>
      <div class="trend-angle">
        💡 <em><?= htmlspecialchars($t['angle']) ?></em>
      </div>
      <div class="hashtag-row" style="margin-bottom:0.75rem">
        <?php foreach ($t['hashtags'] as $h): ?>
        <span class="hashtag"><?= htmlspecialchars($h) ?></span>
        <?php endforeach ?>
      </div>
      <button class="btn btn-primary btn-sm btn-block gen-trend-btn">
        ✨ Generate Content from Trend
      </button>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Viral Sounds Section -->
  <div class="glass-card">
    <div class="section-header" style="margin-bottom:1rem">
      <h3>🎵 Trending Sounds (TikTok & Reels)</h3>
      <span class="live-indicator" style="font-size:0.72rem"><span class="live-dot"></span>Real-time</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:0.75rem">
      <?php foreach ($sounds as $s): ?>
      <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:1rem;transition:all 0.2s" onmouseover="this.style.borderColor='var(--glass-border-hover)'" onmouseout="this.style.borderColor='var(--glass-border)'">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem">
          <div style="width:40px;height:40px;background:var(--gradient-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;animation:spin 3s linear infinite">🎵</div>
          <div>
            <div style="font-size:0.85rem;font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($s['name']) ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted)"><?= htmlspecialchars($s['artist']) ?></div>
          </div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--text-muted);margin-bottom:0.75rem">
          <span>🎬 <?= $s['uses'] ?> uses</span>
          <span style="color:var(--green-light)"><?= $s['growth'] ?></span>
        </div>
        <button class="btn btn-ghost btn-sm btn-block" onclick="SociAI.showToast('Opening audio details...','info')">Use This Sound</button>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
