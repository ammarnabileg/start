<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::index()
// $brand, $brandId, $platforms, $platformCount, $contentStats,
// $communityNew, $recentContent, $campaignStats, $agentTasks, $trends, $user, $currentUser, $csrf

$platformMeta = [
    'linkedin'  => ['label' => 'LinkedIn',   'color' => '#0A66C2', 'icon' => 'in'],
    'instagram' => ['label' => 'Instagram',  'color' => '#E1306C', 'icon' => '📸'],
    'facebook'  => ['label' => 'Facebook',   'color' => '#1877F2', 'icon' => 'f'],
    'tiktok'    => ['label' => 'TikTok',     'color' => '#010101', 'icon' => '♪'],
    'twitter'   => ['label' => 'Twitter/X',  'color' => '#1DA1F2', 'icon' => 'X'],
    'youtube'   => ['label' => 'YouTube',    'color' => '#FF0000', 'icon' => '▶'],
    'snapchat'  => ['label' => 'Snapchat',   'color' => '#FFFC00', 'icon' => '👻'],
    'threads'   => ['label' => 'Threads',    'color' => '#6B7280', 'icon' => '@'],
    'pinterest' => ['label' => 'Pinterest',  'color' => '#E60023', 'icon' => 'P'],
    'whatsapp'  => ['label' => 'WhatsApp',   'color' => '#25D366', 'icon' => '💬'],
    'telegram'  => ['label' => 'Telegram',   'color' => '#229ED9', 'icon' => '✈'],
];

function idxStatusBadge(string $s): string {
    return match($s) {
        'published' => 'badge badge-success',
        'approved'  => 'badge badge-info',
        'pending'   => 'badge badge-warning',
        'rejected'  => 'badge badge-error',
        default     => 'badge',
    };
}

function idxFormatFollowers(int $n): string {
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
    if ($n >= 1000) return round($n / 1000, 1) . 'K';
    return (string)$n;
}

function idxAgentStatusColor(string $s): string {
    return match($s) {
        'running'   => 'var(--green-light)',
        'completed' => 'var(--blue-light)',
        'failed'    => 'var(--red)',
        default     => 'var(--text-muted)',
    };
}
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.75rem;font-weight:700;margin-bottom:0.25rem;">
            <?php if ($brand): ?>
                <?= htmlspecialchars($brand['name']) ?>
            <?php else: ?>
                Dashboard Overview
            <?php endif; ?>
        </h1>
        <p style="color:var(--text-muted);font-size:0.875rem;display:flex;align-items:center;gap:0.5rem;">
            <?= date('l, F j, Y') ?>
            <span style="color:var(--glass-border);">·</span>
            <span class="status-dot status-running" style="display:inline-block;"></span>
            All systems operational
        </p>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        <a href="/dashboard/content" class="btn btn-ghost" style="font-size:0.875rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:0.35rem;vertical-align:-2px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Content
        </a>
        <button class="btn btn-primary" style="font-size:0.875rem;" id="runWorkflowBtn" onclick="runFullWorkflow(this)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.35rem;vertical-align:-2px"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Run Full Workflow
        </button>
    </div>
</div>

<?php if (!$brand): ?>
<!-- ── No Brand State ─────────────────────────────────────────────────────── -->
<div class="glass-card" style="text-align:center;padding:4rem 2rem;margin-bottom:2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">🏢</div>
    <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:0.5rem;">Create Your First Brand</h2>
    <p style="color:var(--text-muted);margin-bottom:1.5rem;max-width:400px;margin-left:auto;margin-right:auto;">
        Get started by creating a brand workspace. Connect your social platforms and let AI manage your presence.
    </p>
    <a href="/brands/create" class="btn btn-primary" style="font-size:0.9rem;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:0.4rem;vertical-align:-2px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create Brand
    </a>
</div>
<?php else: ?>

<!-- ── Quick Actions Bar ──────────────────────────────────────────────────── -->
<div class="glass-card glass-card-sm" style="margin-bottom:2rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;padding:0.9rem 1.25rem;">
    <span style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;white-space:nowrap;">Quick Actions</span>
    <a href="/dashboard/content"   class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">✨ Generate Content</a>
    <a href="/dashboard/analytics" class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">📊 View Analytics</a>
    <a href="/dashboard/agents"    class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">🤖 Run Agents</a>
    <a href="/dashboard/trends"    class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">🔥 Trending</a>
    <a href="/dashboard/community" class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">
        💬 Community
        <?php if ($communityNew > 0): ?>
        <span style="background:var(--red);color:#fff;font-size:0.65rem;font-weight:700;padding:1px 5px;border-radius:99px;margin-left:0.25rem;"><?= $communityNew ?></span>
        <?php endif; ?>
    </a>
</div>

<!-- ── Stats Row ──────────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.25rem;margin-bottom:2rem;">

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(59,130,246,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🌐</div>
            <span class="badge" style="font-size:0.7rem;"><?= $platformCount ?> Active</span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.2rem;"><?= $platformCount ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">Connected Platforms</div>
        <div style="margin-top:0.85rem;height:3px;background:var(--glass-bg);border-radius:2px;overflow:hidden;"><div style="width:<?= min(100, $platformCount * 10) ?>%;height:100%;background:linear-gradient(90deg,var(--blue),var(--purple));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(16,185,129,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">📝</div>
            <span class="badge badge-success" style="font-size:0.7rem;"><?= $contentStats['published'] ?> Published</span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.2rem;"><?= number_format($contentStats['total']) ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">Total Content Pieces</div>
        <div style="margin-top:0.85rem;height:3px;background:var(--glass-bg);border-radius:2px;overflow:hidden;"><div style="width:<?= $contentStats['total'] > 0 ? min(100, round($contentStats['published'] / $contentStats['total'] * 100)) : 0 ?>%;height:100%;background:linear-gradient(90deg,var(--green),var(--cyan));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(239,68,68,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">💬</div>
            <?php if ($communityNew > 0): ?>
            <span class="badge badge-error" style="font-size:0.7rem;">Needs Attention</span>
            <?php else: ?>
            <span class="badge badge-success" style="font-size:0.7rem;">All Clear</span>
            <?php endif; ?>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.2rem;"><?= number_format($communityNew) ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">New Interactions</div>
        <div style="margin-top:0.85rem;height:3px;background:var(--glass-bg);border-radius:2px;overflow:hidden;"><div style="width:<?= $communityNew > 0 ? min(100, $communityNew) : 0 ?>%;height:100%;background:linear-gradient(90deg,var(--red),var(--orange));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🎯</div>
            <span class="badge badge-success" style="font-size:0.7rem;"><?= $campaignStats['active'] ?> Active</span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.2rem;"><?= number_format($campaignStats['total']) ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">Total Campaigns</div>
        <div style="margin-top:0.85rem;height:3px;background:var(--glass-bg);border-radius:2px;overflow:hidden;"><div style="width:<?= $campaignStats['total'] > 0 ? min(100, round($campaignStats['active'] / $campaignStats['total'] * 100)) : 0 ?>%;height:100%;background:linear-gradient(90deg,var(--yellow),var(--orange));border-radius:2px;"></div></div>
    </div>

</div>

<!-- ── Chart Placeholder + Trending ──────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:2rem;">

    <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
            <div>
                <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">30-Day Reach Trend</h3>
                <p style="font-size:0.8rem;color:var(--text-muted);">Connect platforms and publish content to see real reach data</p>
            </div>
            <a href="/dashboard/analytics" class="btn btn-ghost" style="font-size:0.75rem;padding:0.35rem 0.8rem;">View Analytics →</a>
        </div>
        <div style="position:relative;height:220px;display:flex;align-items:center;justify-content:center;background:var(--glass-bg);border-radius:var(--radius-sm);border:1px dashed var(--glass-border);">
            <div style="text-align:center;color:var(--text-muted);">
                <div style="font-size:2rem;margin-bottom:0.5rem;">📈</div>
                <p style="font-size:0.82rem;">Analytics data will appear here once you start publishing content.</p>
                <a href="/dashboard/content" class="btn btn-ghost" style="margin-top:0.75rem;font-size:0.78rem;">Create Content →</a>
            </div>
        </div>
    </div>

    <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <div>
                <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">Trending Now</h3>
                <p style="font-size:0.8rem;color:var(--text-muted);">Top hashtags for your niche</p>
            </div>
            <a href="/dashboard/trends" style="font-size:0.75rem;color:var(--blue-light);">See all →</a>
        </div>
        <?php if (empty($trends)): ?>
        <div style="text-align:center;padding:2rem 1rem;color:var(--text-muted);">
            <div style="font-size:2rem;margin-bottom:0.5rem;">🔥</div>
            <p style="font-size:0.82rem;">No trend data available yet.</p>
            <a href="/dashboard/trends" class="btn btn-ghost" style="margin-top:0.75rem;font-size:0.78rem;">Explore Trends →</a>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.9rem;">
            <?php foreach ($trends as $trend): ?>
            <div style="padding:0.9rem;background:var(--glass-bg);border-radius:var(--radius-md);border:1px solid var(--glass-border);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.45rem;">
                    <span style="font-size:0.875rem;font-weight:600;color:var(--blue-light);"><?= htmlspecialchars($trend['hashtag']) ?></span>
                    <span class="badge badge-success" style="font-size:0.68rem;">+<?= number_format((float)$trend['growth_rate'], 0) ?>%</span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                    <span style="font-size:0.72rem;color:var(--text-muted);"><?= number_format((int)$trend['post_count']) ?> posts</span>
                    <span style="font-size:0.72rem;color:var(--text-muted);">Relevance: <strong style="color:var(--green-light);"><?= (int)$trend['relevance_score'] ?>%</strong></span>
                </div>
                <div style="height:2px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden;">
                    <div style="width:<?= min(100,(int)$trend['relevance_score']) ?>%;height:100%;background:linear-gradient(90deg,var(--blue),var(--purple));border-radius:2px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <a href="/dashboard/copywriting" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1rem;font-size:0.8rem;">✨ Generate Trend Content</a>
        <?php endif; ?>
    </div>

</div>

<!-- ── Platform Health ────────────────────────────────────────────────────── -->
<div class="glass-card" style="margin-bottom:2rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">🌐 Platform Health</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);"><?= $platformCount ?> connected platform<?= $platformCount !== 1 ? 's' : '' ?></p>
        </div>
        <a href="/dashboard/settings" class="btn btn-ghost" style="font-size:0.8rem;padding:0.4rem 0.9rem;">Connect More →</a>
    </div>
    <?php if (empty($platforms)): ?>
    <div style="text-align:center;padding:3rem 1rem;">
        <div style="font-size:3rem;margin-bottom:1rem;">🔌</div>
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;color:var(--text-secondary);">No platforms connected yet</h3>
        <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.25rem;">Connect your social accounts to start publishing AI-powered content and managing your community.</p>
        <a href="/dashboard/settings" class="btn btn-primary">🔗 Connect Your First Platform</a>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.85rem;">
        <?php foreach ($platforms as $platform):
            $meta  = $platformMeta[$platform['platform']] ?? ['label' => ucfirst($platform['platform']), 'color' => '#555', 'icon' => '?'];
            $hex   = htmlspecialchars($meta['color']);
            $label = htmlspecialchars($meta['label']);
        ?>
        <div style="padding:0.9rem;background:var(--glass-bg);border-radius:var(--radius-md);border:1px solid rgba(16,185,129,0.2);transition:border-color 0.2s;" onmouseover="this.style.borderColor='rgba(16,185,129,0.4)'" onmouseout="this.style.borderColor='rgba(16,185,129,0.2)'">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.6rem;">
                <div style="width:30px;height:30px;border-radius:8px;background:<?= $hex ?>22;display:flex;align-items:center;justify-content:center;font-size:0.82rem;font-weight:800;color:<?= $hex ?>;"><?= htmlspecialchars($meta['icon']) ?></div>
                <span style="width:8px;height:8px;background:var(--green);border-radius:50%;display:block;box-shadow:0 0 5px var(--green);flex-shrink:0;"></span>
            </div>
            <div style="font-size:0.8rem;font-weight:600;color:var(--text-primary);margin-bottom:0.25rem;"><?= $label ?></div>
            <div style="font-size:0.72rem;color:var(--text-secondary);margin-bottom:0.15rem;"><?= idxFormatFollowers((int)$platform['follower_count']) ?> followers</div>
            <div style="font-size:0.7rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($platform['account_name']) ?>">@<?= htmlspecialchars($platform['account_name']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Recent Content Table ───────────────────────────────────────────────── -->
<div class="glass-card" style="margin-bottom:2rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">📋 Recent Content</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">Latest content pieces</p>
        </div>
        <a href="/dashboard/content" class="btn btn-ghost" style="font-size:0.8rem;padding:0.4rem 0.9rem;">View All →</a>
    </div>
    <?php if (empty($recentContent)): ?>
    <div style="text-align:center;padding:3rem 1rem;">
        <div style="font-size:2.5rem;margin-bottom:0.75rem;">📝</div>
        <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:0.4rem;color:var(--text-secondary);">No content yet</h3>
        <p style="color:var(--text-muted);font-size:0.82rem;margin-bottom:1rem;">Start generating AI-powered content for your brand.</p>
        <a href="/dashboard/content" class="btn btn-primary" style="font-size:0.82rem;">✨ Generate with AI</a>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Title</th>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Type</th>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Status</th>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Created</th>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:1px solid var(--glass-border);">Viral Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentContent as $item): ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding:0.85rem 1rem;">
                        <a href="/dashboard/content" style="font-size:0.875rem;color:var(--text-primary);font-weight:500;display:block;max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-decoration:none;" title="<?= htmlspecialchars($item['title']) ?>">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                    </td>
                    <td style="padding:0.85rem 1rem;"><span style="font-size:0.8rem;color:var(--text-secondary);text-transform:capitalize;"><?= htmlspecialchars($item['content_type'] ?? '—') ?></span></td>
                    <td style="padding:0.85rem 1rem;"><span class="<?= idxStatusBadge($item['approval_status']) ?>" style="font-size:0.72rem;text-transform:capitalize;"><?= htmlspecialchars($item['approval_status']) ?></span></td>
                    <td style="padding:0.85rem 1rem;"><span style="font-size:0.8rem;color:var(--text-muted);"><?= date('M j, Y', strtotime($item['created_at'])) ?></span></td>
                    <td style="padding:0.85rem 1rem;text-align:right;">
                        <?php $vs = (int)($item['viral_score'] ?? 0); $vc = $vs >= 85 ? 'var(--green-light)' : ($vs >= 70 ? 'var(--yellow)' : 'var(--text-secondary)'); ?>
                        <?php if ($vs > 0): ?>
                        <span style="font-size:0.875rem;font-weight:700;color:<?= $vc ?>;"><?= $vs ?></span><span style="font-size:0.7rem;color:var(--text-muted);">/100</span>
                        <?php else: ?>
                        <span style="font-size:0.8rem;color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Content Stats + Campaigns ─────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:2rem;">

    <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <h3 style="font-size:1rem;font-weight:600;">📊 Content Breakdown</h3>
            <a href="/dashboard/content" style="font-size:0.75rem;color:var(--blue-light);">Manage →</a>
        </div>
        <?php
        $statusItems = [
            ['key' => 'draft',     'label' => 'Drafts',    'color' => 'var(--text-muted)'],
            ['key' => 'pending',   'label' => 'Pending',   'color' => 'var(--yellow)'],
            ['key' => 'approved',  'label' => 'Approved',  'color' => 'var(--blue-light)'],
            ['key' => 'published', 'label' => 'Published', 'color' => 'var(--green-light)'],
            ['key' => 'rejected',  'label' => 'Rejected',  'color' => 'var(--red)'],
        ];
        $total = max(1, $contentStats['total']);
        foreach ($statusItems as $si):
            $count = (int)($contentStats[$si['key']] ?? 0);
            $pct   = round($count / $total * 100);
        ?>
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">
            <span style="font-size:0.8rem;color:var(--text-secondary);width:72px;flex-shrink:0;"><?= $si['label'] ?></span>
            <div style="flex:1;height:6px;background:var(--glass-bg);border-radius:3px;overflow:hidden;">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $si['color'] ?>;border-radius:3px;transition:width 0.4s;"></div>
            </div>
            <span style="font-size:0.8rem;font-weight:600;color:<?= $si['color'] ?>;min-width:28px;text-align:right;"><?= $count ?></span>
        </div>
        <?php endforeach; ?>
        <?php if ($contentStats['total'] === 0): ?>
        <div style="text-align:center;padding:1.5rem 0;color:var(--text-muted);font-size:0.82rem;">
            No content yet. <a href="/dashboard/content" style="color:var(--blue-light);">Create some →</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <h3 style="font-size:1rem;font-weight:600;">🎯 Campaign Overview</h3>
            <a href="/dashboard/campaigns" style="font-size:0.75rem;color:var(--blue-light);">Manage →</a>
        </div>
        <?php if ($campaignStats['total'] === 0): ?>
        <div style="text-align:center;padding:2rem 0;color:var(--text-muted);">
            <div style="font-size:2rem;margin-bottom:0.5rem;">🎯</div>
            <p style="font-size:0.82rem;margin-bottom:0.75rem;">No campaigns yet.</p>
            <a href="/dashboard/campaigns" class="btn btn-ghost" style="font-size:0.78rem;">Create Campaign →</a>
        </div>
        <?php else: ?>
        <?php
        $campItems = [
            ['key' => 'active',    'label' => 'Active',    'color' => 'var(--green-light)'],
            ['key' => 'draft',     'label' => 'Draft',     'color' => 'var(--text-muted)'],
            ['key' => 'paused',    'label' => 'Paused',    'color' => 'var(--yellow)'],
            ['key' => 'completed', 'label' => 'Completed', 'color' => 'var(--blue-light)'],
        ];
        foreach ($campItems as $ci):
            $count = (int)($campaignStats[$ci['key']] ?? 0);
            if ($count === 0) continue;
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid rgba(255,255,255,0.04);">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span style="width:8px;height:8px;border-radius:50%;background:<?= $ci['color'] ?>;flex-shrink:0;"></span>
                <span style="font-size:0.82rem;color:var(--text-secondary);"><?= $ci['label'] ?></span>
            </div>
            <span style="font-size:0.9rem;font-weight:700;color:<?= $ci['color'] ?>;"><?= $count ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;padding-top:0.75rem;border-top:1px solid var(--glass-border);">
            <span style="font-size:0.8rem;color:var(--text-muted);">Total Campaigns</span>
            <span style="font-size:1.1rem;font-weight:700;color:var(--text-primary);"><?= $campaignStats['total'] ?></span>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- ── AI Agent Tasks ─────────────────────────────────────────────────────── -->
<div class="glass-card" style="margin-bottom:2rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">🤖 Recent Agent Activity</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">Latest AI agent task runs</p>
        </div>
        <a href="/dashboard/agents" class="btn btn-ghost" style="font-size:0.8rem;padding:0.4rem 0.9rem;">Manage Agents →</a>
    </div>
    <?php if (empty($agentTasks)): ?>
    <div style="text-align:center;padding:2.5rem 1rem;">
        <div style="font-size:2.5rem;margin-bottom:0.75rem;">🤖</div>
        <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:0.4rem;color:var(--text-secondary);">No active tasks</h3>
        <p style="color:var(--text-muted);font-size:0.82rem;margin-bottom:1rem;">Run your first AI agent to start automating your social media presence.</p>
        <a href="/dashboard/agents" class="btn btn-primary" style="font-size:0.82rem;">🚀 Run Agents</a>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:0;">
        <?php foreach ($agentTasks as $task): ?>
        <div style="display:flex;align-items:center;gap:0.75rem;padding:0.7rem 0;border-bottom:1px solid rgba(255,255,255,0.04);">
            <div style="width:8px;height:8px;border-radius:50%;background:<?= idxAgentStatusColor($task['status']) ?>;flex-shrink:0;"></div>
            <div style="flex:1;min-width:0;">
                <span style="font-size:0.82rem;font-weight:500;color:var(--text-secondary);text-transform:capitalize;">
                    <?= htmlspecialchars(str_replace('_', ' ', $task['agent_type'])) ?>
                </span>
                <?php if (!empty($task['error_message'])): ?>
                <span style="font-size:0.72rem;color:var(--red);margin-left:0.4rem;">— <?= htmlspecialchars(mb_substr($task['error_message'], 0, 60)) ?></span>
                <?php endif; ?>
            </div>
            <span class="badge <?= match($task['status']) { 'completed' => 'badge-success', 'running' => 'badge-warning', 'failed' => 'badge-error', default => '' } ?>" style="font-size:0.68rem;text-transform:capitalize;">
                <?= htmlspecialchars($task['status']) ?>
            </span>
            <span style="font-size:0.72rem;color:var(--text-muted);white-space:nowrap;"><?= date('M j, g:i a', strtotime($task['created_at'])) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; // brand exists ?>

<script>
(function() {
    'use strict';
    window.runFullWorkflow = function(btn) {
        if (!btn) return;
        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.textContent = '⏳ Running…';
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        fetch('/api/agents/run-workflow', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
            body: JSON.stringify({ trigger: 'manual' })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.disabled = false; btn.innerHTML = orig;
            if (window.SociAI && SociAI.showToast) SociAI.showToast(d.message || 'Workflow started!', 'success');
        })
        .catch(function() {
            btn.disabled = false; btn.innerHTML = orig;
            if (window.SociAI && SociAI.showToast) SociAI.showToast('Workflow queued — agents starting shortly.', 'info');
        });
    };
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
