<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::analytics()
// $brand, $brandId, $platformAnalytics, $topPosts, $totals, $csrf

$platformColors = [
    'linkedin'  => ['hex' => '#0A66C2', 'label' => 'LinkedIn'],
    'instagram' => ['hex' => '#E1306C', 'label' => 'Instagram'],
    'facebook'  => ['hex' => '#1877F2', 'label' => 'Facebook'],
    'tiktok'    => ['hex' => '#010101', 'label' => 'TikTok'],
    'twitter'   => ['hex' => '#1DA1F2', 'label' => 'Twitter/X'],
    'youtube'   => ['hex' => '#FF0000', 'label' => 'YouTube'],
    'threads'   => ['hex' => '#6B7280', 'label' => 'Threads'],
    'snapchat'  => ['hex' => '#F7C034', 'label' => 'Snapchat'],
];

function anaFormatNum(int $n): string {
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
    if ($n >= 1000) return round($n / 1000, 1) . 'K';
    return (string)$n;
}
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">Analytics Dashboard 📊</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Track performance across all platforms with AI-powered insights</p>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
        <button class="btn btn-ghost btn-sm" onclick="if(window.SociAI)SociAI.showToast('Exporting PDF report…','info')">📥 Export</button>
        <a href="/dashboard/content" class="btn btn-primary btn-sm">+ Create Content</a>
    </div>
</div>

<?php if (!$brandId): ?>
<!-- No Brand -->
<div class="glass-card" style="text-align:center;padding:4rem 2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">📊</div>
    <h3 style="font-size:1.1rem;margin-bottom:0.5rem;color:var(--text-secondary);">Set Up Your Brand First</h3>
    <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.25rem;">Create a brand and connect your platforms to start seeing analytics data.</p>
    <a href="/brands/create" class="btn btn-primary">Create Brand</a>
</div>

<?php elseif (empty($platformAnalytics)): ?>
<!-- No Analytics Data -->
<div class="glass-card" style="text-align:center;padding:5rem 2rem;margin-bottom:2rem;">
    <div style="font-size:4rem;margin-bottom:1rem;">📈</div>
    <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:0.5rem;color:var(--text-secondary);">No analytics data yet</h2>
    <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.5rem;max-width:420px;margin-left:auto;margin-right:auto;">
        Connect your social platforms and publish content to start tracking impressions, engagement, reach, and more.
    </p>
    <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
        <a href="/dashboard/settings" class="btn btn-primary">🔌 Connect Platforms</a>
        <a href="/dashboard/content" class="btn btn-ghost">✨ Create Content</a>
    </div>
</div>

<?php else: ?>

<!-- Totals Row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <?php
    $totalCards = [
        ['label' => 'Total Impressions', 'key' => 'impressions', 'icon' => '👁️', 'color' => 'rgba(59,130,246,0.15)',  'textcolor' => 'var(--blue-light)'],
        ['label' => 'Total Reach',        'key' => 'reach',       'icon' => '🌐', 'color' => 'rgba(139,92,246,0.15)',  'textcolor' => 'var(--purple-light,#c4b5fd)'],
        ['label' => 'Engagements',        'key' => 'engagements', 'icon' => '❤️', 'color' => 'rgba(16,185,129,0.15)',  'textcolor' => 'var(--green-light)'],
        ['label' => 'Total Likes',        'key' => 'likes',       'icon' => '👍', 'color' => 'rgba(245,158,11,0.15)',  'textcolor' => 'var(--yellow)'],
        ['label' => 'Comments',           'key' => 'comments',    'icon' => '💬', 'color' => 'rgba(236,72,153,0.15)',  'textcolor' => '#f472b6'],
        ['label' => 'Shares',             'key' => 'shares',      'icon' => '🔁', 'color' => 'rgba(6,182,212,0.15)',   'textcolor' => 'var(--cyan)'],
    ];
    foreach ($totalCards as $tc):
        $val = (int)($totals[$tc['key']] ?? 0);
    ?>
    <div class="glass-card" style="padding:1rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
            <div style="font-size:0.7rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;"><?= $tc['label'] ?></div>
            <div style="width:32px;height:32px;border-radius:8px;background:<?= $tc['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:0.9rem;"><?= $tc['icon'] ?></div>
        </div>
        <div style="font-size:1.7rem;font-weight:700;color:<?= $tc['textcolor'] ?>;"><?= anaFormatNum($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Platform Breakdown -->
<div class="glass-card" style="margin-bottom:1.5rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
        <h3 style="font-size:1rem;font-weight:600;">📡 Performance by Platform</h3>
        <span style="font-size:0.78rem;color:var(--text-muted);"><?= count($platformAnalytics) ?> platform<?= count($platformAnalytics) !== 1 ? 's' : '' ?></span>
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:0.82rem;">
            <thead>
                <tr>
                    <?php foreach (['Platform', 'Posts', 'Impressions', 'Reach', 'Engagements', 'Likes', 'Comments', 'Shares'] as $th): ?>
                    <th style="padding:0.6rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:<?= $th === 'Platform' ? 'left' : 'right' ?>;border-bottom:1px solid var(--glass-border);white-space:nowrap;"><?= $th ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($platformAnalytics as $pa):
                    $meta  = $platformColors[$pa['platform']] ?? ['hex' => '#555', 'label' => ucfirst($pa['platform'])];
                    $total = max(1, (int)($totals['engagements'] ?: 1));
                    $pct   = round((int)$pa['engagements'] / $total * 100);
                ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding:0.75rem 1rem;">
                        <div style="display:flex;align-items:center;gap:0.6rem;">
                            <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($meta['hex']) ?>;flex-shrink:0;"></span>
                            <span style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($meta['label']) ?></span>
                        </div>
                    </td>
                    <td style="padding:0.75rem 1rem;text-align:right;color:var(--text-secondary);"><?= number_format((int)($pa['post_count'] ?? 0)) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;color:var(--text-secondary);"><?= anaFormatNum((int)$pa['impressions']) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;color:var(--text-secondary);"><?= anaFormatNum((int)$pa['reach']) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;">
                        <span style="font-weight:700;color:var(--green-light);"><?= anaFormatNum((int)$pa['engagements']) ?></span>
                        <span style="font-size:0.7rem;color:var(--text-muted);margin-left:0.3rem;"><?= $pct ?>%</span>
                    </td>
                    <td style="padding:0.75rem 1rem;text-align:right;color:var(--text-secondary);"><?= anaFormatNum((int)$pa['likes']) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;color:var(--text-secondary);"><?= anaFormatNum((int)$pa['comments']) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;color:var(--text-secondary);"><?= anaFormatNum((int)$pa['shares']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top:1px solid var(--glass-border);">
                    <td style="padding:0.75rem 1rem;font-weight:700;color:var(--text-primary);">Total</td>
                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;"></td>
                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:var(--text-primary);"><?= anaFormatNum((int)$totals['impressions']) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:var(--text-primary);"><?= anaFormatNum((int)$totals['reach']) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:700;color:var(--green-light);"><?= anaFormatNum((int)$totals['engagements']) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:var(--text-primary);"><?= anaFormatNum((int)$totals['likes']) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:var(--text-primary);"><?= anaFormatNum((int)$totals['comments']) ?></td>
                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:var(--text-primary);"><?= anaFormatNum((int)$totals['shares']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Engagement Bar Chart -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:1.5rem;">

    <div class="glass-card">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;">📊 Engagement by Platform</h3>
        <?php if (empty($platformAnalytics)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:2rem 0;">No data yet.</p>
        <?php else:
            $maxEng = max(array_map(fn($p) => (int)$p['engagements'], $platformAnalytics)) ?: 1;
        ?>
        <div style="display:flex;flex-direction:column;gap:0.85rem;">
            <?php foreach ($platformAnalytics as $pa):
                $meta = $platformColors[$pa['platform']] ?? ['hex' => '#555', 'label' => ucfirst($pa['platform'])];
                $pct  = round((int)$pa['engagements'] / $maxEng * 100);
            ?>
            <div>
                <div style="display:flex;justify-content:space-between;margin-bottom:0.3rem;">
                    <span style="font-size:0.8rem;font-weight:500;color:var(--text-secondary);"><?= htmlspecialchars($meta['label']) ?></span>
                    <span style="font-size:0.8rem;font-weight:700;color:var(--text-primary);"><?= anaFormatNum((int)$pa['engagements']) ?></span>
                </div>
                <div style="height:8px;background:var(--glass-bg);border-radius:4px;overflow:hidden;">
                    <div style="width:<?= $pct ?>%;height:100%;background:<?= htmlspecialchars($meta['hex']) ?>;border-radius:4px;transition:width 0.4s;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="glass-card">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;">🥧 Engagement Share</h3>
        <?php if (empty($platformAnalytics)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:2rem 0;">No data yet.</p>
        <?php else:
            $totalEng = max(1, array_sum(array_map(fn($p) => (int)$p['engagements'], $platformAnalytics)));
        ?>
        <div style="display:flex;flex-direction:column;gap:0.65rem;">
            <?php foreach ($platformAnalytics as $pa):
                $meta = $platformColors[$pa['platform']] ?? ['hex' => '#555', 'label' => ucfirst($pa['platform'])];
                $pct  = round((int)$pa['engagements'] / $totalEng * 100);
            ?>
            <div style="display:flex;align-items:center;gap:0.6rem;">
                <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($meta['hex']) ?>;flex-shrink:0;"></span>
                <span style="font-size:0.8rem;color:var(--text-secondary);flex:1;"><?= htmlspecialchars($meta['label']) ?></span>
                <span style="font-size:0.8rem;font-weight:700;color:var(--text-primary);"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Top Posts -->
<div class="glass-card" style="margin-bottom:1.5rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
        <h3 style="font-size:1rem;font-weight:600;">🏆 Top Performing Content</h3>
        <a href="/dashboard/content" style="font-size:0.75rem;color:var(--blue-light);">View All →</a>
    </div>
    <?php if (empty($topPosts)): ?>
    <div style="text-align:center;padding:2rem;color:var(--text-muted);">
        <div style="font-size:2rem;margin-bottom:0.5rem;">🏆</div>
        <p style="font-size:0.82rem;">No top posts data yet. Publish and track content to see rankings.</p>
    </div>
    <?php else: ?>
    <?php foreach ($topPosts as $i => $p):
        $meta = $platformColors[$p['platform']] ?? ['hex' => '#555', 'label' => ucfirst($p['platform'])];
        $vs   = (int)($p['viral_score'] ?? 0);
        $vc   = $vs >= 85 ? 'var(--green-light)' : ($vs >= 70 ? 'var(--yellow)' : 'var(--text-secondary)');
    ?>
    <div style="display:flex;align-items:center;gap:0.75rem;padding:0.7rem 0;border-bottom:1px solid rgba(255,255,255,0.04);">
        <span style="font-size:1.1rem;font-weight:800;color:var(--text-muted);min-width:22px;"><?= $i + 1 ?></span>
        <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($meta['hex']) ?>;flex-shrink:0;"></span>
        <div style="flex:1;min-width:0;">
            <div style="font-size:0.82rem;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= htmlspecialchars($p['title'] ?? '(Untitled)') ?>
            </div>
            <div style="font-size:0.72rem;color:var(--text-muted);">
                <?= htmlspecialchars($meta['label']) ?> · <?= anaFormatNum((int)$p['total_reach']) ?> reach
            </div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:0.82rem;font-weight:700;color:var(--green-light);"><?= anaFormatNum((int)$p['total_engagements']) ?></div>
            <div style="font-size:0.7rem;color:var(--text-muted);">engagements</div>
        </div>
        <?php if ($vs > 0): ?>
        <div style="text-align:center;flex-shrink:0;min-width:44px;">
            <div style="font-size:1rem;font-weight:800;color:<?= $vc ?>;"><?= $vs ?></div>
            <div style="font-size:0.62rem;color:var(--text-muted);">viral</div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php endif; // end analytics data check ?>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
