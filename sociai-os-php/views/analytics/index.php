<?php
$pageTitle  = 'Analytics';
$activePage = 'analytics';
ob_start();
extract($data ?? []);

// ── Default mock data ─────────────────────────────────────────────────────
$topMetrics = $topMetrics ?? [
    'total_reach'       => ['value' => '4.7M',    'change' => '+18.4%', 'up' => true],
    'total_impressions' => ['value' => '12.3M',   'change' => '+22.1%', 'up' => true],
    'engagement_rate'   => ['value' => '8.3%',    'change' => '+2.1%',  'up' => true],
    'new_followers'     => ['value' => '+12,431', 'change' => '+34.2%', 'up' => true],
    'posts_published'   => ['value' => '247',     'change' => '+12.8%', 'up' => true],
];

$chartData = $chartData ?? [
    'reach' => [
        'labels' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar','Apr','May','Jun'],
        'values' => [180, 220, 195, 340, 280, 410, 390, 520, 480, 610, 570, 720, 680, 790, 750, 870, 830, 960, 910, 1050, 990, 1120, 1080, 1200, 1150, 1320, 1270, 1410, 1360, 1520],
    ],
    'engagement' => [
        'labels' => ['LinkedIn','Instagram','TikTok','X/Twitter','Facebook','YouTube'],
        'values' => [8.4, 12.7, 18.2, 6.3, 4.8, 9.1],
        'colors' => ['#0A66C2','#E1306C','#69C9D0','#60A5FA','#1877F2','#FF0000'],
    ],
];

$topPosts = $topPosts ?? [
    ['rank' => 1, 'platform' => 'TikTok',    'content' => 'Why brands fail at TikTok (full breakdown)',       'reach' => '892K', 'engagement' => '22.1K', 'viral_score' => 95, 'published' => 'May 14'],
    ['rank' => 2, 'platform' => 'LinkedIn',  'content' => '5 AI trends reshaping business strategy in 2025', 'reach' => '287K', 'engagement' => '14.2K', 'viral_score' => 92, 'published' => 'May 10'],
    ['rank' => 3, 'platform' => 'Instagram', 'content' => 'Behind the scenes: Our product launch day',       'reach' => '156K', 'engagement' => '8.7K',  'viral_score' => 88, 'published' => 'May 8'],
    ['rank' => 4, 'platform' => 'Instagram', 'content' => 'Weekly motivation carousel — 5 mindset shifts',   'reach' => '89K',  'engagement' => '5.3K',  'viral_score' => 79, 'published' => 'May 5'],
    ['rank' => 5, 'platform' => 'YouTube',   'content' => 'How we grew to 100K followers in 90 days',        'reach' => '74K',  'engagement' => '4.1K',  'viral_score' => 75, 'published' => 'May 3'],
    ['rank' => 6, 'platform' => 'Facebook',  'content' => 'Customer success story: 10x growth in 6 months', 'reach' => '67K',  'engagement' => '3.1K',  'viral_score' => 71, 'published' => 'May 1'],
];

$platformBreakdown = $platformBreakdown ?? [
    ['name' => 'TikTok',     'icon' => '♪',   'followers' => '89.3K',  'reach' => '892K', 'engagement' => '18.2%', 'posts' => 47],
    ['name' => 'Instagram',  'icon' => '📷',  'followers' => '124.7K', 'reach' => '456K', 'engagement' => '12.7%', 'posts' => 68],
    ['name' => 'LinkedIn',   'icon' => 'in',  'followers' => '48.2K',  'reach' => '287K', 'engagement' => '8.4%',  'posts' => 32],
    ['name' => 'YouTube',    'icon' => '▶',   'followers' => '22.9K',  'reach' => '214K', 'engagement' => '9.1%',  'posts' => 18],
    ['name' => 'X/Twitter',  'icon' => 'X',   'followers' => '57.1K',  'reach' => '178K', 'engagement' => '6.3%',  'posts' => 54],
    ['name' => 'Facebook',   'icon' => 'f',   'followers' => '31.5K',  'reach' => '134K', 'engagement' => '4.8%',  'posts' => 29],
];

$sentiment = $sentiment ?? [
    'positive' => ['pct' => 72, 'label' => 'Positive',  'emoji' => '😊', 'color' => 'var(--green)'],
    'neutral'  => ['pct' => 20, 'label' => 'Neutral',   'emoji' => '😐', 'color' => 'var(--text-muted)'],
    'negative' => ['pct' => 8,  'label' => 'Negative',  'emoji' => '😞', 'color' => 'var(--red)'],
];

$insights = $insights ?? [
    ['icon' => '📈', 'color' => 'blue',   'title' => 'TikTok is your growth engine',    'text' => 'Your TikTok engagement is 2.3x higher than the platform average. Increasing to 2x daily posts could add ~40K reach per week.'],
    ['icon' => '⏰', 'color' => 'green',  'title' => 'Post timing is critical',         'text' => 'Best performing windows are 8–9 AM and 7–8 PM. Over 94% of your top-performing posts were published in these windows.'],
    ['icon' => '🎯', 'color' => 'purple', 'title' => 'Educate, don\'t promote',         'text' => 'Educational content outperforms promotional content by 340% in engagement. Shift your content mix to 70/30 educational vs. promotional.'],
];

$activePeriod = $activePeriod ?? '30d';

function analyticsStatusBadge(string $platform): string {
    $colors = [
        'TikTok'    => '#69C9D0', 'LinkedIn'  => '#0A66C2', 'Instagram' => '#E1306C',
        'YouTube'   => '#FF0000', 'X/Twitter' => '#60A5FA', 'Facebook'  => '#1877F2',
    ];
    $bg = $colors[$platform] ?? '#8B5CF6';
    return "<span style=\"display:inline-block;padding:0.15rem 0.5rem;border-radius:4px;font-size:0.72rem;font-weight:600;background:{$bg}22;color:{$bg};\">$platform</span>";
}
?>

<!-- ── Page Header ────────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.75rem;font-weight:700;margin-bottom:0.25rem;">Analytics &amp; Insights</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Deep-dive performance across all platforms · Updated in real-time</p>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        <!-- Period Selector -->
        <div id="periodSelector" style="display:flex;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);overflow:hidden;">
            <?php foreach (['7d' => '7 Days', '30d' => '30 Days', '90d' => '90 Days', 'custom' => 'Custom'] as $val => $label): ?>
            <button
                class="period-btn"
                data-period="<?= $val ?>"
                onclick="selectPeriod(this, '<?= $val ?>')"
                style="padding:0.45rem 0.85rem;font-size:0.8rem;font-weight:500;border:none;cursor:pointer;transition:all 0.15s;white-space:nowrap;background:<?= $val === $activePeriod ? 'rgba(59,130,246,0.2)' : 'transparent' ?>;color:<?= $val === $activePeriod ? 'var(--blue-light)' : 'var(--text-muted)' ?>;"
            ><?= $label ?></button>
            <?php endforeach ?>
        </div>
        <!-- Export buttons -->
        <a href="/api/analytics/export?format=csv" class="btn btn-ghost" style="font-size:0.8rem;padding:0.45rem 0.9rem;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.3rem;vertical-align:-1px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
        </a>
        <a href="/api/analytics/export?format=pdf" class="btn btn-primary" style="font-size:0.8rem;padding:0.45rem 0.9rem;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.3rem;vertical-align:-1px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
            Export PDF
        </a>
    </div>
</div>

<!-- ── 5 Top Metric Cards ─────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(185px,1fr));gap:1.1rem;margin-bottom:2rem;">

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.7rem;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(59,130,246,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;">📡</div>
            <span class="badge badge-success" style="font-size:0.68rem;"><?= htmlspecialchars($topMetrics['total_reach']['change']) ?></span>
        </div>
        <div style="font-size:1.7rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.15rem;"><?= htmlspecialchars($topMetrics['total_reach']['value']) ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);font-weight:500;">Total Reach</div>
        <div style="margin-top:0.75rem;height:3px;background:var(--glass-bg);border-radius:2px;"><div style="width:74%;height:100%;background:linear-gradient(90deg,var(--blue),var(--purple));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.7rem;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(139,92,246,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;">👁️</div>
            <span class="badge badge-success" style="font-size:0.68rem;"><?= htmlspecialchars($topMetrics['total_impressions']['change']) ?></span>
        </div>
        <div style="font-size:1.7rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.15rem;"><?= htmlspecialchars($topMetrics['total_impressions']['value']) ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);font-weight:500;">Total Impressions</div>
        <div style="margin-top:0.75rem;height:3px;background:var(--glass-bg);border-radius:2px;"><div style="width:82%;height:100%;background:linear-gradient(90deg,var(--purple),var(--pink));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.7rem;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(236,72,153,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;">💫</div>
            <span class="badge badge-success" style="font-size:0.68rem;"><?= htmlspecialchars($topMetrics['engagement_rate']['change']) ?></span>
        </div>
        <div style="font-size:1.7rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.15rem;"><?= htmlspecialchars($topMetrics['engagement_rate']['value']) ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);font-weight:500;">Engagement Rate</div>
        <div style="margin-top:0.75rem;height:3px;background:var(--glass-bg);border-radius:2px;"><div style="width:63%;height:100%;background:linear-gradient(90deg,var(--pink),var(--orange));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.7rem;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(16,185,129,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;">👥</div>
            <span class="badge badge-success" style="font-size:0.68rem;"><?= htmlspecialchars($topMetrics['new_followers']['change']) ?></span>
        </div>
        <div style="font-size:1.7rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.15rem;"><?= htmlspecialchars($topMetrics['new_followers']['value']) ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);font-weight:500;">New Followers</div>
        <div style="margin-top:0.75rem;height:3px;background:var(--glass-bg);border-radius:2px;"><div style="width:55%;height:100%;background:linear-gradient(90deg,var(--green),var(--cyan));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.7rem;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;">📤</div>
            <span class="badge badge-success" style="font-size:0.68rem;"><?= htmlspecialchars($topMetrics['posts_published']['change']) ?></span>
        </div>
        <div style="font-size:1.7rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.15rem;"><?= htmlspecialchars($topMetrics['posts_published']['value']) ?></div>
        <div style="font-size:0.78rem;color:var(--text-muted);font-weight:500;">Posts Published</div>
        <div style="margin-top:0.75rem;height:3px;background:var(--glass-bg);border-radius:2px;"><div style="width:48%;height:100%;background:linear-gradient(90deg,var(--yellow),var(--orange));border-radius:2px;"></div></div>
    </div>

</div>

<!-- ── Charts Row: Reach + Engagement ───────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:2rem;">

    <!-- Reach Over Time -->
    <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
            <div>
                <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">Reach Over Time</h3>
                <p style="font-size:0.8rem;color:var(--text-muted);">Daily reach trend for selected period</p>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span style="width:10px;height:10px;border-radius:50%;background:#3B82F6;display:inline-block;"></span>
                <span style="font-size:0.75rem;color:var(--text-muted);">Reach</span>
            </div>
        </div>
        <div class="chart-wrapper" style="position:relative;height:230px;">
            <canvas id="analyticsReachChart" style="width:100%;height:100%;display:block;"></canvas>
        </div>
    </div>

    <!-- Engagement by Platform -->
    <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
            <div>
                <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">Engagement by Platform</h3>
                <p style="font-size:0.8rem;color:var(--text-muted);">Average engagement rate per platform</p>
            </div>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span style="width:10px;height:10px;border-radius:2px;background:var(--blue);display:inline-block;"></span>
                <span style="font-size:0.75rem;color:var(--text-muted);">Eng. Rate %</span>
            </div>
        </div>
        <div class="chart-wrapper" style="position:relative;height:230px;">
            <canvas id="engagementBarChart" style="width:100%;height:100%;display:block;"></canvas>
        </div>
    </div>

</div>

<!-- ── Top Performing Posts Table ────────────────────────────────────────── -->
<div class="glass-card" style="margin-bottom:2rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">🏆 Top Performing Posts</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">Highest reach and engagement for selected period</p>
        </div>
        <a href="/dashboard/content" class="btn btn-ghost" style="font-size:0.8rem;padding:0.4rem 0.9rem;">View All Content →</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="padding:0.65rem 0.85rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Rank</th>
                    <th style="padding:0.65rem 0.85rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Platform</th>
                    <th style="padding:0.65rem 0.85rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Content</th>
                    <th style="padding:0.65rem 0.85rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:1px solid var(--glass-border);">Reach</th>
                    <th style="padding:0.65rem 0.85rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:1px solid var(--glass-border);">Engagement</th>
                    <th style="padding:0.65rem 0.85rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:1px solid var(--glass-border);">Viral Score</th>
                    <th style="padding:0.65rem 0.85rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:1px solid var(--glass-border);">Published</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topPosts as $post): ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding:0.8rem 0.85rem;">
                        <span style="font-size:1rem;font-weight:800;color:<?= (int)$post['rank'] <= 3 ? 'var(--yellow)' : 'var(--text-muted)' ?>;"><?= (int)$post['rank'] ?></span>
                    </td>
                    <td style="padding:0.8rem 0.85rem;"><?= analyticsStatusBadge($post['platform']) ?></td>
                    <td style="padding:0.8rem 0.85rem;"><span style="font-size:0.82rem;color:var(--text-primary);font-weight:500;display:block;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($post['content']) ?></span></td>
                    <td style="padding:0.8rem 0.85rem;text-align:right;font-size:0.82rem;color:var(--text-secondary);font-weight:500;"><?= htmlspecialchars($post['reach']) ?></td>
                    <td style="padding:0.8rem 0.85rem;text-align:right;font-size:0.82rem;color:var(--text-secondary);font-weight:500;"><?= htmlspecialchars($post['engagement']) ?></td>
                    <td style="padding:0.8rem 0.85rem;text-align:right;">
                        <?php $vs = (int)$post['viral_score']; $vc = $vs >= 85 ? 'var(--green-light)' : ($vs >= 70 ? 'var(--yellow)' : 'var(--text-secondary)'); ?>
                        <span style="font-size:0.875rem;font-weight:700;color:<?= $vc ?>;"><?= $vs ?></span><span style="font-size:0.7rem;color:var(--text-muted);">/100</span>
                    </td>
                    <td style="padding:0.8rem 0.85rem;text-align:right;font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($post['published']) ?></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Platform Breakdown Cards ──────────────────────────────────────────── -->
<div class="glass-card" style="margin-bottom:2rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">Platform Breakdown</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">Per-platform performance summary</p>
        </div>
        <a href="/dashboard/settings" class="btn btn-ghost" style="font-size:0.8rem;padding:0.4rem 0.9rem;">Manage Platforms →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">
        <?php
        $platHex = [
            'TikTok' => '#69C9D0', 'Instagram' => '#E1306C', 'LinkedIn' => '#0A66C2',
            'YouTube' => '#FF0000', 'X/Twitter' => '#60A5FA', 'Facebook' => '#1877F2',
        ];
        foreach ($platformBreakdown as $pb):
            $hex = $platHex[$pb['name']] ?? '#8B5CF6';
        ?>
        <div style="padding:1.1rem;background:var(--glass-bg);border-radius:var(--radius-md);border:1px solid var(--glass-border);transition:border-color 0.2s;" onmouseover="this.style.borderColor='var(--glass-border-hover)'" onmouseout="this.style.borderColor='var(--glass-border)'">
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.85rem;">
                <div style="width:34px;height:34px;border-radius:8px;background:<?= $hex ?>22;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:800;color:<?= $hex ?>;"><?= htmlspecialchars($pb['icon']) ?></div>
                <span style="font-size:0.875rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($pb['name']) ?></span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
                <div style="padding:0.5rem;background:rgba(255,255,255,0.03);border-radius:var(--radius-sm);">
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:0.15rem;">Followers</div>
                    <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($pb['followers']) ?></div>
                </div>
                <div style="padding:0.5rem;background:rgba(255,255,255,0.03);border-radius:var(--radius-sm);">
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:0.15rem;">Reach</div>
                    <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($pb['reach']) ?></div>
                </div>
                <div style="padding:0.5rem;background:rgba(255,255,255,0.03);border-radius:var(--radius-sm);">
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:0.15rem;">Eng. Rate</div>
                    <div style="font-size:0.875rem;font-weight:600;color:var(--green-light);"><?= htmlspecialchars($pb['engagement']) ?></div>
                </div>
                <div style="padding:0.5rem;background:rgba(255,255,255,0.03);border-radius:var(--radius-sm);">
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:0.15rem;">Posts</div>
                    <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary);"><?= (int)$pb['posts'] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach ?>
    </div>
</div>

<!-- ── Viral Score Distribution + Sentiment + Insights ──────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:2rem;">

    <!-- Viral Score Distribution -->
    <div class="glass-card">
        <div style="margin-bottom:1.25rem;">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">Viral Score Distribution</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">Distribution of viral scores across all published content</p>
        </div>
        <?php
        $scoreBands = [
            ['label' => '80–100 Viral',   'range' => [80,100], 'count' => 42, 'color' => 'var(--green)',  'pct' => 42],
            ['label' => '60–79 Strong',   'range' => [60,79],  'count' => 81, 'color' => 'var(--blue)',   'pct' => 33],
            ['label' => '40–59 Average',  'range' => [40,59],  'count' => 64, 'color' => 'var(--yellow)', 'pct' => 16],
            ['label' => '20–39 Below avg','range' => [20,39],  'count' => 32, 'color' => 'var(--orange)', 'pct' => 6],
            ['label' => '0–19 Low',       'range' => [0,19],   'count' => 28, 'color' => 'var(--red)',    'pct' => 3],
        ];
        ?>
        <div style="display:flex;flex-direction:column;gap:0.85rem;">
            <?php foreach ($scoreBands as $band): ?>
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.3rem;">
                    <span style="font-size:0.8rem;color:var(--text-secondary);font-weight:500;"><?= htmlspecialchars($band['label']) ?></span>
                    <span style="font-size:0.75rem;color:var(--text-muted);"><?= $band['count'] ?> posts · <?= $band['pct'] ?>%</span>
                </div>
                <div style="height:8px;background:rgba(255,255,255,0.06);border-radius:4px;overflow:hidden;">
                    <div style="width:<?= $band['pct'] ?>%;height:100%;background:<?= $band['color'] ?>;border-radius:4px;transition:width 0.5s ease;"></div>
                </div>
            </div>
            <?php endforeach ?>
        </div>
        <div style="margin-top:1rem;padding:0.75rem;background:rgba(59,130,246,0.08);border-radius:var(--radius-md);border:1px solid rgba(59,130,246,0.15);">
            <div style="font-size:0.78rem;color:var(--text-muted);">Average Viral Score</div>
            <div style="font-size:1.4rem;font-weight:700;color:var(--blue-light);">74.2 <span style="font-size:0.85rem;color:var(--green-light);">↑ +3.1 pts</span></div>
        </div>
    </div>

    <!-- Sentiment Analysis -->
    <div class="glass-card">
        <div style="margin-bottom:1.25rem;">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">Sentiment Analysis</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">AI-analyzed audience sentiment from comments &amp; reactions</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:1.1rem;">
            <?php foreach ($sentiment as $key => $s): ?>
            <div style="padding:1rem;background:var(--glass-bg);border-radius:var(--radius-md);border:1px solid var(--glass-border);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.65rem;">
                    <div style="display:flex;align-items:center;gap:0.6rem;">
                        <span style="font-size:1.4rem;"><?= $s['emoji'] ?></span>
                        <span style="font-size:0.875rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($s['label']) ?></span>
                    </div>
                    <span style="font-size:1.2rem;font-weight:700;color:<?= $s['color'] ?>;"><?= (int)$s['pct'] ?>%</span>
                </div>
                <div style="height:8px;background:rgba(255,255,255,0.06);border-radius:4px;overflow:hidden;">
                    <div style="width:<?= (int)$s['pct'] ?>%;height:100%;background:<?= $s['color'] ?>;border-radius:4px;transition:width 0.5s ease;"></div>
                </div>
            </div>
            <?php endforeach ?>
        </div>
        <div style="margin-top:1rem;padding:0.75rem;background:rgba(16,185,129,0.08);border-radius:var(--radius-md);border:1px solid rgba(16,185,129,0.15);">
            <p style="font-size:0.78rem;color:var(--text-muted);margin:0;">72% positive sentiment is above industry benchmark of 58%. Your community is highly engaged and receptive.</p>
        </div>
    </div>

</div>

<!-- ── AI Insights ───────────────────────────────────────────────────────── -->
<div class="glass-card" style="margin-bottom:2rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">🤖 AI Insights &amp; Recommendations</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">Actionable recommendations generated by your Analytics Agent</p>
        </div>
        <a href="/dashboard/agents" class="btn btn-ghost" style="font-size:0.8rem;padding:0.4rem 0.9rem;">View Agent →</a>
    </div>
    <?php
    $insightColors = [
        'blue'   => ['bg' => 'rgba(59,130,246,0.08)',  'border' => 'rgba(59,130,246,0.2)'],
        'green'  => ['bg' => 'rgba(16,185,129,0.08)',  'border' => 'rgba(16,185,129,0.2)'],
        'purple' => ['bg' => 'rgba(139,92,246,0.08)',  'border' => 'rgba(139,92,246,0.2)'],
        'yellow' => ['bg' => 'rgba(245,158,11,0.08)',  'border' => 'rgba(245,158,11,0.2)'],
        'red'    => ['bg' => 'rgba(239,68,68,0.08)',   'border' => 'rgba(239,68,68,0.2)'],
    ];
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;">
        <?php foreach ($insights as $ins):
            $ic = $insightColors[$ins['color']] ?? $insightColors['blue'];
        ?>
        <div style="padding:1.1rem;background:<?= $ic['bg'] ?>;border:1px solid <?= $ic['border'] ?>;border-radius:var(--radius-md);">
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.6rem;">
                <span style="font-size:1.2rem;"><?= $ins['icon'] ?></span>
                <span style="font-size:0.875rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($ins['title']) ?></span>
            </div>
            <p style="font-size:0.82rem;color:var(--text-secondary);line-height:1.55;margin:0;"><?= htmlspecialchars($ins['text']) ?></p>
        </div>
        <?php endforeach ?>
    </div>
</div>

<!-- ── Canvas Charts + Period Selector JS ───────────────────────────────── -->
<script>
(function() {
    'use strict';

    var reachLabels = <?= json_encode($chartData['reach']['labels']) ?>;
    var reachVals   = <?= json_encode($chartData['reach']['values'], JSON_NUMERIC_CHECK) ?>;
    var engLabels   = <?= json_encode($chartData['engagement']['labels']) ?>;
    var engVals     = <?= json_encode($chartData['engagement']['values'], JSON_NUMERIC_CHECK) ?>;
    var engColors   = <?= json_encode($chartData['engagement']['colors']) ?>;

    // ── Reach Line Chart ───────────────────────────────────────────────────
    function drawReachChart(labels, values) {
        var canvas = document.getElementById('analyticsReachChart');
        if (!canvas) return;
        var container = canvas.parentElement;
        var W   = container.offsetWidth  || 520;
        var H   = container.offsetHeight || 230;
        var dpr = window.devicePixelRatio || 1;
        canvas.width        = W * dpr;
        canvas.height       = H * dpr;
        canvas.style.width  = W + 'px';
        canvas.style.height = H + 'px';
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);

        var PAD  = { top: 16, right: 14, bottom: 42, left: 52 };
        var cW   = W - PAD.left - PAD.right;
        var cH   = H - PAD.top  - PAD.bottom;
        var n    = values.length;
        var maxV = Math.max.apply(null, values);
        var minV = Math.min.apply(null, values);
        var rng  = maxV - minV || 1;

        // Grid + Y labels
        for (var gi = 0; gi <= 4; gi++) {
            var gy = PAD.top + (cH / 4) * gi;
            ctx.beginPath(); ctx.moveTo(PAD.left, gy); ctx.lineTo(PAD.left + cW, gy);
            ctx.strokeStyle = 'rgba(255,255,255,0.06)'; ctx.lineWidth = 1; ctx.stroke();
            var lv  = Math.round(maxV - (rng / 4) * gi);
            var lbl = lv >= 1000 ? (lv/1000).toFixed(1)+'K' : String(lv);
            ctx.fillStyle = 'rgba(148,163,184,0.7)';
            ctx.font = '10px Inter,system-ui,sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(lbl, PAD.left - 6, gy + 4);
        }

        // X labels (every 5th or every label if short)
        ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(148,163,184,0.7)';
        var step = n > 15 ? 5 : 1;
        for (var xi = 0; xi < n; xi += step) {
            var xp = PAD.left + (xi/(n-1))*cW;
            ctx.fillText(labels[xi] || ('D'+(xi+1)), xp, H - PAD.bottom + 15);
        }

        // Points
        var pts = values.map(function(v, i) {
            return { x: PAD.left+(i/(n-1))*cW, y: PAD.top+(1-(v-minV)/rng)*cH };
        });

        // Fill gradient
        var grad = ctx.createLinearGradient(0, PAD.top, 0, PAD.top+cH);
        grad.addColorStop(0,   'rgba(59,130,246,0.35)');
        grad.addColorStop(0.65,'rgba(139,92,246,0.08)');
        grad.addColorStop(1,   'rgba(59,130,246,0)');
        ctx.beginPath();
        ctx.moveTo(pts[0].x, PAD.top+cH);
        ctx.lineTo(pts[0].x, pts[0].y);
        for (var fi = 1; fi < pts.length; fi++) {
            var fcx = (pts[fi-1].x + pts[fi].x)/2;
            ctx.bezierCurveTo(fcx, pts[fi-1].y, fcx, pts[fi].y, pts[fi].x, pts[fi].y);
        }
        ctx.lineTo(pts[pts.length-1].x, PAD.top+cH);
        ctx.closePath(); ctx.fillStyle = grad; ctx.fill();

        // Stroke
        ctx.beginPath(); ctx.moveTo(pts[0].x, pts[0].y);
        for (var li = 1; li < pts.length; li++) {
            var lcx = (pts[li-1].x + pts[li].x)/2;
            ctx.bezierCurveTo(lcx, pts[li-1].y, lcx, pts[li].y, pts[li].x, pts[li].y);
        }
        ctx.strokeStyle = '#3B82F6'; ctx.lineWidth = 2.5; ctx.lineJoin = 'round'; ctx.stroke();

        // Last dot
        var lp = pts[pts.length-1];
        ctx.beginPath(); ctx.arc(lp.x, lp.y, 5, 0, Math.PI*2);
        ctx.fillStyle = '#3B82F6'; ctx.fill();
        ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.stroke();
    }

    // ── Engagement Bar Chart ───────────────────────────────────────────────
    function drawEngagementChart(labels, values, colors) {
        var canvas = document.getElementById('engagementBarChart');
        if (!canvas) return;
        var container = canvas.parentElement;
        var W   = container.offsetWidth  || 520;
        var H   = container.offsetHeight || 230;
        var dpr = window.devicePixelRatio || 1;
        canvas.width        = W * dpr;
        canvas.height       = H * dpr;
        canvas.style.width  = W + 'px';
        canvas.style.height = H + 'px';
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);

        var PAD  = { top: 16, right: 14, bottom: 42, left: 48 };
        var cW   = W - PAD.left - PAD.right;
        var cH   = H - PAD.top  - PAD.bottom;
        var n    = values.length;
        var maxV = Math.max.apply(null, values) * 1.15 || 1;

        // Grid + Y labels
        for (var gi = 0; gi <= 4; gi++) {
            var gy = PAD.top + (cH/4)*gi;
            ctx.beginPath(); ctx.moveTo(PAD.left, gy); ctx.lineTo(PAD.left+cW, gy);
            ctx.strokeStyle = 'rgba(255,255,255,0.06)'; ctx.lineWidth = 1; ctx.stroke();
            var lv = (maxV - (maxV/4)*gi).toFixed(1) + '%';
            ctx.fillStyle = 'rgba(148,163,184,0.7)';
            ctx.font = '10px Inter,system-ui,sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(lv, PAD.left - 5, gy + 4);
        }

        // Bars
        var barW    = (cW / n) * 0.55;
        var spacing = cW / n;
        for (var bi = 0; bi < n; bi++) {
            var bH   = (values[bi] / maxV) * cH;
            var bx   = PAD.left + bi * spacing + (spacing - barW) / 2;
            var by   = PAD.top  + cH - bH;
            var col  = colors[bi] || '#3B82F6';

            // Bar gradient
            var bGrad = ctx.createLinearGradient(0, by, 0, by+bH);
            bGrad.addColorStop(0, col);
            bGrad.addColorStop(1, col + '44');
            ctx.beginPath();
            ctx.roundRect(bx, by, barW, bH, [4, 4, 0, 0]);
            ctx.fillStyle = bGrad;
            ctx.fill();

            // Value label above bar
            ctx.fillStyle = 'rgba(240,244,255,0.85)';
            ctx.font = 'bold 10px Inter,system-ui,sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(values[bi] + '%', bx + barW/2, by - 5);

            // X label
            ctx.fillStyle = 'rgba(148,163,184,0.75)';
            ctx.font = '9.5px Inter,system-ui,sans-serif';
            ctx.fillText(labels[bi] || '', bx + barW/2, H - PAD.bottom + 15);
        }
    }

    function initCharts() {
        drawReachChart(reachLabels, reachVals);
        drawEngagementChart(engLabels, engVals, engColors);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }
    window.addEventListener('resize', initCharts);

    // ── Period selector ────────────────────────────────────────────────────
    window.selectPeriod = function(btn, period) {
        document.querySelectorAll('.period-btn').forEach(function(b) {
            b.style.background = 'transparent';
            b.style.color      = 'var(--text-muted)';
        });
        btn.style.background = 'rgba(59,130,246,0.2)';
        btn.style.color      = 'var(--blue-light)';

        if (period === 'custom') {
            if (window.SociAI && SociAI.showToast) SociAI.showToast('Custom date range picker coming soon.', 'info');
            return;
        }

        fetch('/api/analytics/data?period=' + period, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.reach)      { reachLabels = d.reach.labels  || reachLabels; reachVals = d.reach.values || reachVals; }
            if (d.engagement) { engLabels   = d.engagement.labels || engLabels; engVals = d.engagement.values || engVals; }
            initCharts();
            if (window.SociAI && SociAI.showToast) SociAI.showToast('Analytics updated for ' + period + ' period.', 'success');
        })
        .catch(function() {
            if (window.SociAI && SociAI.showToast) SociAI.showToast('Could not load data. Showing cached results.', 'warning');
        });
    };
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
