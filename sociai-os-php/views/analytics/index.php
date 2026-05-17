<?php
$pageTitle  = 'Analytics';
$activePage = 'analytics';
$kpis = [
  ['label'=>'Total Reach',        'value'=>'4.7M',    'change'=>'+18.4%','up'=>true, 'icon'=>'🌐','color'=>'blue'],
  ['label'=>'Total Impressions',  'value'=>'12.3M',   'change'=>'+22.1%','up'=>true, 'icon'=>'👁️','color'=>'purple'],
  ['label'=>'Engagement Rate',    'value'=>'8.3%',    'change'=>'+2.1%', 'up'=>true, 'icon'=>'❤️','color'=>'pink'],
  ['label'=>'Follower Growth',    'value'=>'+12,431', 'change'=>'+34.2%','up'=>true, 'icon'=>'📈','color'=>'green'],
  ['label'=>'Avg. Viral Score',   'value'=>'78.4',    'change'=>'+5pts', 'up'=>true, 'icon'=>'🔥','color'=>'yellow'],
  ['label'=>'Link Clicks',        'value'=>'89.2K',   'change'=>'-3.4%', 'up'=>false,'icon'=>'🔗','color'=>'cyan'],
  ['label'=>'Profile Visits',     'value'=>'234K',    'change'=>'+11.8%','up'=>true, 'icon'=>'👤','color'=>'orange'],
  ['label'=>'Conversions',        'value'=>'1,847',   'change'=>'+28.3%','up'=>true, 'icon'=>'🎯','color'=>'green'],
];
$iconColors = ['blue'=>'metric-icon-blue','pink'=>'metric-icon-pink','green'=>'metric-icon-green','yellow'=>'metric-icon-yellow','purple'=>'metric-icon-purple','cyan'=>'metric-icon-cyan','orange'=>'metric-icon-orange'];
?>
<?php ob_start() ?>
<div class="page-header page-header-row">
  <div>
    <h1>Analytics Dashboard 📊</h1>
    <p>Track performance across all platforms with AI-powered insights</p>
  </div>
  <div style="display:flex;gap:0.5rem;align-items:center">
    <div style="display:flex;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);overflow:hidden">
      <?php foreach(['7D'=>'7d','30D'=>'30d','90D'=>'90d','6M'=>'6m','1Y'=>'1y'] as $l=>$v): ?>
      <button class="lang-btn <?= $v==='30d'?'active':'' ?>" onclick="SociAI.showToast('Loading <?= $l ?> data...','info')"><?= $l ?></button>
      <?php endforeach ?>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Exporting PDF report...','info')">📥 Export</button>
  </div>
</div>

<!-- KPI Row 1 -->
<div class="dashboard-grid grid-cols-4 mb-4">
  <?php foreach (array_slice($kpis,0,4) as $kpi): ?>
  <div class="metric-card">
    <div class="metric-header">
      <div>
        <div class="metric-label"><?= htmlspecialchars($kpi['label']) ?></div>
        <div class="metric-value"><?= htmlspecialchars($kpi['value']) ?></div>
      </div>
      <div class="metric-icon <?= $iconColors[$kpi['color']] ?>"><?= $kpi['icon'] ?></div>
    </div>
    <div class="metric-change <?= $kpi['up']?'trend-up':'trend-down' ?>">
      <?= $kpi['up']?'↑':'↓' ?> <?= htmlspecialchars($kpi['change']) ?> vs last period
    </div>
  </div>
  <?php endforeach ?>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:1.25rem">
  <!-- Line Chart -->
  <div class="glass-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
      <h3>📈 Reach Growth Over Time</h3>
      <div style="display:flex;gap:0.5rem">
        <span class="legend-item"><span class="legend-dot" style="background:#3B82F6"></span>Reach</span>
        <span class="legend-item"><span class="legend-dot" style="background:#10B981"></span>Engagement</span>
      </div>
    </div>
    <div class="chart-wrapper" style="height:200px">
      <canvas id="reachChart" style="width:100%;height:200px"></canvas>
    </div>
  </div>

  <!-- Donut: Content Mix -->
  <div class="glass-card">
    <h3 style="margin-bottom:1rem">🥧 Content Mix</h3>
    <div class="donut-chart" data-segments='[{"pct":30,"color":"#3B82F6"},{"pct":25,"color":"#EC4899"},{"pct":20,"color":"#EF4444"},{"pct":15,"color":"#60A5FA"},{"pct":10,"color":"#FCA5A5"}]' style="width:150px;height:150px">
      <svg class="donut-svg" width="150" height="150" viewBox="0 0 180 180"></svg>
      <div class="donut-center"><span class="val" style="font-size:1.1rem">1.2K</span><span class="lbl">Posts</span></div>
    </div>
    <div class="chart-legend" style="margin-top:0.75rem">
      <div class="legend-item"><span class="legend-dot" style="background:#3B82F6"></span>LinkedIn 30%</div>
      <div class="legend-item"><span class="legend-dot" style="background:#EC4899"></span>Instagram 25%</div>
      <div class="legend-item"><span class="legend-dot" style="background:#EF4444"></span>TikTok 20%</div>
      <div class="legend-item"><span class="legend-dot" style="background:#60A5FA"></span>Facebook 15%</div>
      <div class="legend-item"><span class="legend-dot" style="background:#FCA5A5"></span>Other 10%</div>
    </div>
  </div>
</div>

<!-- Bar Chart -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem">
  <div class="glass-card">
    <h3 style="margin-bottom:1rem">📊 Engagement by Platform</h3>
    <div class="chart-wrapper" style="height:220px">
      <canvas id="engagementChart" style="width:100%;height:220px"></canvas>
    </div>
  </div>

  <!-- KPI Row 2 -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
    <?php foreach (array_slice($kpis,4) as $kpi): ?>
    <div class="metric-card" style="padding:1rem">
      <div class="metric-header" style="margin-bottom:0.5rem">
        <div>
          <div class="metric-label" style="font-size:0.72rem"><?= htmlspecialchars($kpi['label']) ?></div>
          <div class="metric-value" style="font-size:1.3rem"><?= htmlspecialchars($kpi['value']) ?></div>
        </div>
        <div class="metric-icon <?= $iconColors[$kpi['color']] ?>" style="width:34px;height:34px;font-size:0.9rem"><?= $kpi['icon'] ?></div>
      </div>
      <div class="metric-change <?= $kpi['up']?'trend-up':'trend-down' ?>" style="font-size:0.7rem">
        <?= $kpi['up']?'↑':'↓' ?> <?= htmlspecialchars($kpi['change']) ?>
      </div>
    </div>
    <?php endforeach ?>
  </div>
</div>

<!-- Top Performing Content -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem">
  <div class="glass-card">
    <div class="section-header"><h3>🏆 Top Posts This Month</h3></div>
    <?php
    $topPosts = [
      ['title'=>'5 AI trends reshaping business','platform'=>'linkedin','engagement'=>'14.2K','reach'=>'287K','viral'=>92],
      ['title'=>'Behind the scenes launch video','platform'=>'instagram','engagement'=>'8.7K','reach'=>'156K','viral'=>88],
      ['title'=>'Why brands fail at TikTok','platform'=>'tiktok','engagement'=>'22.1K','reach'=>'892K','viral'=>95],
      ['title'=>'Weekly motivation carousel','platform'=>'instagram','engagement'=>'5.3K','reach'=>'89K','viral'=>79],
      ['title'=>'Customer 10x growth story','platform'=>'facebook','engagement'=>'3.1K','reach'=>'67K','viral'=>71],
    ];
    ?>
    <?php foreach ($topPosts as $i => $p): ?>
    <div style="display:flex;align-items:center;gap:0.75rem;padding:0.65rem 0;border-bottom:1px solid rgba(255,255,255,0.04)">
      <span style="font-size:1.2rem;font-weight:800;color:var(--text-muted);min-width:20px"><?= $i+1 ?></span>
      <div style="flex:1;min-width:0">
        <div style="font-size:0.82rem;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($p['title']) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted)"><?= $p['platform'] ?> · <?= $p['reach'] ?> reach</div>
      </div>
      <span class="viral-score <?= $p['viral']>=85?'viral-high':($p['viral']>=70?'viral-mid':'viral-low') ?>"><?= $p['viral'] ?></span>
    </div>
    <?php endforeach ?>
  </div>

  <!-- AI Recommendations -->
  <div class="glass-card">
    <div class="section-header"><h3>🤖 AI Insights</h3></div>
    <?php
    $insights = [
      ['icon'=>'📈','color'=>'blue','text'=>'Your TikTok engagement is 2.3x higher than average. Increase posting frequency to 2x daily.'],
      ['icon'=>'⏰','color'=>'green','text'=>'Best performing time slots: 8-9 AM and 7-8 PM. 94% of your top posts hit these windows.'],
      ['icon'=>'🎯','color'=>'purple','text'=>'Educational content outperforms promotional by 340%. Shift content mix toward more tutorials.'],
      ['icon'=>'⚠️','color'=>'yellow','text'=>'Snapchat engagement dropped 12% last week. Consider refreshing your story format.'],
    ];
    $ic = ['blue'=>'rgba(59,130,246,0.1)','green'=>'rgba(16,185,129,0.1)','purple'=>'rgba(139,92,246,0.1)','yellow'=>'rgba(245,158,11,0.1)'];
    $bc = ['blue'=>'rgba(59,130,246,0.2)','green'=>'rgba(16,185,129,0.2)','purple'=>'rgba(139,92,246,0.2)','yellow'=>'rgba(245,158,11,0.2)'];
    ?>
    <?php foreach ($insights as $ins): ?>
    <div style="display:flex;gap:0.75rem;padding:0.875rem;background:<?= $ic[$ins['color']] ?>;border:1px solid <?= $bc[$ins['color']] ?>;border-radius:var(--radius-md);margin-bottom:0.75rem">
      <span style="font-size:1.1rem;flex-shrink:0;margin-top:1px"><?= $ins['icon'] ?></span>
      <p style="font-size:0.82rem;margin:0;line-height:1.5"><?= htmlspecialchars($ins['text']) ?></p>
    </div>
    <?php endforeach ?>
  </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
