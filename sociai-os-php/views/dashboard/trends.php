<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::trends()
// $brand, $brandId, $trends, $platformFilter, $csrf

$platformColors = [
    'linkedin'  => '#0A66C2', 'instagram' => '#E1306C', 'tiktok'    => '#010101',
    'twitter'   => '#1DA1F2', 'facebook'  => '#1877F2', 'youtube'   => '#FF0000',
    'threads'   => '#6B7280', 'snapchat'  => '#FFFC00', 'pinterest' => '#E60023',
];

$platformLabels = [
    'linkedin'  => 'LinkedIn',   'instagram' => 'Instagram', 'tiktok'    => 'TikTok',
    'twitter'   => 'Twitter/X',  'facebook'  => 'Facebook',  'youtube'   => 'YouTube',
    'threads'   => 'Threads',    'snapchat'  => 'Snapchat',  'pinterest' => 'Pinterest',
];

$allPlatformFilters = ['all', 'linkedin', 'instagram', 'tiktok', 'twitter', 'facebook', 'youtube'];
?>

<div style="padding:0;">
    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">Trend Hunter 🔥</h1>
            <p style="color:var(--text-muted);font-size:0.875rem;">Real-time trend opportunities across all platforms</p>
        </div>
        <div style="display:flex;gap:0.75rem;align-items:center;">
            <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.82rem;color:var(--green-light);padding:0.4rem 0.85rem;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);border-radius:99px;">
                <span class="status-dot status-running"></span> Live Data
            </div>
            <form method="get" style="display:none;" id="refreshForm"></form>
            <button class="btn btn-ghost" onclick="document.getElementById('refreshForm').submit()">🔄 Refresh</button>
        </div>
    </div>

    <!-- Platform Filters -->
    <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
        <?php foreach ($allPlatformFilters as $pf): ?>
        <a href="?platform=<?= $pf ?>"
           class="btn btn-sm <?= $platformFilter === $pf ? 'btn-primary' : 'btn-ghost' ?>"
           style="font-size:0.8rem;">
            <?php if ($pf === 'all'): ?>🌐 All Platforms<?php else: ?>
            <?= htmlspecialchars($platformLabels[$pf] ?? ucfirst($pf)) ?>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Trend Cards or Empty State -->
    <?php if (empty($trends)): ?>
    <div class="glass-card" style="text-align:center;padding:5rem 2rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">🔥</div>
        <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:0.5rem;color:var(--text-secondary);">No trend data available yet</h2>
        <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.5rem;max-width:400px;margin-left:auto;margin-right:auto;">
            Trend data is fetched by the AI Trend Hunter agent. Run the agent to populate trending topics and hashtags.
        </p>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
            <a href="/dashboard/agents" class="btn btn-primary">🤖 Run Trend Hunter Agent</a>
            <?php if ($platformFilter !== 'all'): ?>
            <a href="?platform=all" class="btn btn-ghost">🌐 View All Platforms</a>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:1rem;margin-bottom:2rem;">
        <?php foreach ($trends as $t):
            $platform = $t['platform'] ?? 'unknown';
            $hexColor = htmlspecialchars($platformColors[$platform] ?? '#555');
            $platLabel = htmlspecialchars($platformLabels[$platform] ?? ucfirst($platform));
            $relevance = min(100, (int)($t['relevance_score'] ?? 0));
            $growth    = round((float)($t['growth_rate'] ?? 0), 1);
            $posts     = number_format((int)($t['post_count'] ?? 0));
            $region    = $t['region'] ?? null;
        ?>
        <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:1.25rem;transition:border-color var(--tr),transform var(--tr);" onmouseover="this.style.borderColor='rgba(255,255,255,.15)';this.style.transform='translateY(-1px)'" onmouseout="this.style.borderColor='var(--glass-border)';this.style.transform='none'">
            <!-- Header -->
            <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
                <div>
                    <div style="font-size:1rem;font-weight:700;color:var(--blue-light);margin-bottom:0.3rem;"><?= htmlspecialchars($t['hashtag'] ?? '—') ?></div>
                    <span style="display:inline-flex;align-items:center;gap:0.3rem;font-size:0.7rem;font-weight:600;color:#fff;background:<?= $hexColor ?>;padding:2px 8px;border-radius:99px;"><?= $platLabel ?></span>
                </div>
                <div style="text-align:center;flex-shrink:0;">
                    <?php $vc = $relevance >= 85 ? 'var(--green)' : ($relevance >= 70 ? 'var(--yellow)' : 'var(--text-muted)'); ?>
                    <div style="font-size:1.4rem;font-weight:800;color:<?= $vc ?>;"><?= $relevance ?></div>
                    <div style="font-size:0.62rem;color:var(--text-muted);">/ 100</div>
                </div>
            </div>

            <!-- Stats Row -->
            <div style="display:flex;gap:1rem;margin-bottom:0.75rem;font-size:0.78rem;color:var(--text-muted);">
                <span style="color:var(--green-light);font-weight:600;">
                    📈 +<?= $growth ?>%
                </span>
                <span>📝 <?= $posts ?> posts</span>
                <?php if ($region): ?>
                <span>🌍 <?= htmlspecialchars($region) ?></span>
                <?php endif; ?>
            </div>

            <!-- Relevance Bar -->
            <div style="margin-bottom:0.85rem;">
                <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--text-muted);margin-bottom:0.25rem;">
                    <span>Relevance Score</span>
                    <span style="color:<?= $vc ?>;"><?= $relevance ?>%</span>
                </div>
                <div style="height:4px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden;">
                    <div style="width:<?= $relevance ?>%;height:100%;background:linear-gradient(90deg,var(--blue),var(--purple));border-radius:2px;transition:width 0.4s;"></div>
                </div>
            </div>

            <!-- Detected At -->
            <?php if (!empty($t['created_at'])): ?>
            <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:0.85rem;">
                Detected <?= date('M j, g:i a', strtotime($t['created_at'])) ?>
            </div>
            <?php endif; ?>

            <!-- Generate CTA -->
            <a href="/dashboard/copywriting" class="btn btn-primary btn-sm" style="width:100%;text-align:center;display:block;" onclick="sessionStorage.setItem('trend_hashtag','<?= htmlspecialchars($t['hashtag'] ?? '') ?>')">
                ✨ Generate Content from Trend
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="text-align:center;padding:0.5rem 0 1rem;color:var(--text-muted);font-size:0.78rem;">
        Showing <?= count($trends) ?> trend<?= count($trends) !== 1 ? 's' : '' ?><?= $platformFilter !== 'all' ? ' for ' . htmlspecialchars($platformLabels[$platformFilter] ?? $platformFilter) : '' ?> · Updated by AI Trend Hunter Agent
    </div>

    <?php endif; ?>

</div>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
