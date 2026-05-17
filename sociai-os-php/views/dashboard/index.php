<?php
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
ob_start();
extract($data ?? []);

// ── Default mock data ─────────────────────────────────────────────────────
$metrics = $metrics ?? [
    'total_reach'      => ['value' => '2.4M',   'change' => '+18.3%', 'trend' => 'up'],
    'engagement_rate'  => ['value' => '6.8%',   'change' => '+2.1%',  'trend' => 'up'],
    'followers_gained' => ['value' => '12,847', 'change' => '+9.4%',  'trend' => 'up'],
    'viral_score'      => ['value' => '87',      'change' => '+5.2%',  'trend' => 'up'],
];

$agents = $agents ?? [
    ['name' => 'Orchestrator',       'status' => 'running', 'last_run' => '2 min ago',  'tasks_completed' => 1284, 'icon' => '🧠'],
    ['name' => 'Strategy Agent',     'status' => 'running', 'last_run' => '5 min ago',  'tasks_completed' => 342,  'icon' => '🎯'],
    ['name' => 'Copywriting Agent',  'status' => 'running', 'last_run' => '8 min ago',  'tasks_completed' => 891,  'icon' => '✍️'],
    ['name' => 'Design Agent',       'status' => 'idle',    'last_run' => '32 min ago', 'tasks_completed' => 215,  'icon' => '🎨'],
    ['name' => 'Video Agent',        'status' => 'idle',    'last_run' => '1 hr ago',   'tasks_completed' => 78,   'icon' => '🎬'],
    ['name' => 'Publishing Agent',   'status' => 'running', 'last_run' => '1 min ago',  'tasks_completed' => 2103, 'icon' => '📤'],
    ['name' => 'Analytics Agent',    'status' => 'running', 'last_run' => '3 min ago',  'tasks_completed' => 564,  'icon' => '📊'],
    ['name' => 'Community Agent',    'status' => 'running', 'last_run' => 'Just now',   'tasks_completed' => 1497, 'icon' => '💬'],
    ['name' => 'Research Agent',     'status' => 'idle',    'last_run' => '45 min ago', 'tasks_completed' => 302,  'icon' => '🔍'],
];

$platforms = $platforms ?? [
    ['name' => 'LinkedIn',    'icon' => 'in', 'hex' => '#0A66C2', 'connected' => true,  'followers' => '48.2K',  'last_post' => '2 hrs ago'],
    ['name' => 'Instagram',   'icon' => '📷', 'hex' => '#E1306C', 'connected' => true,  'followers' => '124.7K', 'last_post' => '4 hrs ago'],
    ['name' => 'Facebook',    'icon' => 'f',  'hex' => '#1877F2', 'connected' => true,  'followers' => '31.5K',  'last_post' => '6 hrs ago'],
    ['name' => 'TikTok',      'icon' => '♪',  'hex' => '#69C9D0', 'connected' => true,  'followers' => '89.3K',  'last_post' => '1 hr ago'],
    ['name' => 'X / Twitter', 'icon' => 'X',  'hex' => '#60A5FA', 'connected' => true,  'followers' => '57.1K',  'last_post' => '30 min ago'],
    ['name' => 'YouTube',     'icon' => '▶',  'hex' => '#FF0000', 'connected' => true,  'followers' => '22.9K',  'last_post' => '1 day ago'],
    ['name' => 'Snapchat',    'icon' => '👻', 'hex' => '#F7C034', 'connected' => false, 'followers' => '—',      'last_post' => '—'],
    ['name' => 'Threads',     'icon' => '@',  'hex' => '#A78BFA', 'connected' => true,  'followers' => '14.6K',  'last_post' => '3 hrs ago'],
    ['name' => 'Pinterest',   'icon' => 'P',  'hex' => '#E60023', 'connected' => false, 'followers' => '—',      'last_post' => '—'],
    ['name' => 'WhatsApp',    'icon' => '💬', 'hex' => '#25D366', 'connected' => true,  'followers' => '3.2K',   'last_post' => '5 hrs ago'],
    ['name' => 'Telegram',    'icon' => '✈',  'hex' => '#229ED9', 'connected' => true,  'followers' => '8.4K',   'last_post' => '7 hrs ago'],
];

$recentContent = $recentContent ?? [
    ['title' => 'How AI is reshaping brand strategy in 2025',    'platform' => 'LinkedIn',    'status' => 'published', 'scheduled_at' => 'Today 09:00',    'viral_score' => 91],
    ['title' => '5 productivity hacks that tripled our output',  'platform' => 'Instagram',   'status' => 'published', 'scheduled_at' => 'Today 11:30',    'viral_score' => 84],
    ['title' => 'The secret framework behind viral content',      'platform' => 'TikTok',      'status' => 'scheduled', 'scheduled_at' => 'Today 14:00',    'viral_score' => 78],
    ['title' => 'Thread: Building a $1M brand with no budget',   'platform' => 'X / Twitter', 'status' => 'scheduled', 'scheduled_at' => 'Today 16:00',    'viral_score' => 95],
    ['title' => 'Behind the scenes: Our AI content workflow',    'platform' => 'YouTube',     'status' => 'draft',     'scheduled_at' => 'Tomorrow 10:00', 'viral_score' => 72],
    ['title' => 'Community Q&A — your top questions answered',   'platform' => 'Facebook',    'status' => 'published', 'scheduled_at' => 'Today 08:00',    'viral_score' => 63],
];

$trends = $trends ?? [
    ['hashtag' => '#AIContentCreation', 'growth' => '+342%', 'relevance' => 96, 'posts' => '128K'],
    ['hashtag' => '#DigitalMarketing',  'growth' => '+89%',  'relevance' => 88, 'posts' => '412K'],
    ['hashtag' => '#PersonalBranding',  'growth' => '+167%', 'relevance' => 82, 'posts' => '73K'],
];

$reachData = $reachData ?? [180, 220, 195, 340, 280, 410, 390, 520, 480, 610, 570, 720, 680, 790, 750, 870, 830, 960, 910, 1050, 990, 1120, 1080, 1200, 1150, 1320, 1270, 1410, 1360, 1520];

function dashStatusBadge(string $s): string {
    $map = ['published' => 'badge badge-success', 'scheduled' => 'badge badge-warning', 'draft' => 'badge', 'failed' => 'badge badge-error'];
    return $map[$s] ?? 'badge';
}
?>

<!-- ── Page Header ────────────────────────────────────────────────────────── -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.75rem;font-weight:700;margin-bottom:0.25rem;">Dashboard Overview</h1>
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

<!-- ── Quick Actions Bar ──────────────────────────────────────────────────── -->
<div class="glass-card glass-card-sm" style="margin-bottom:2rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;padding:0.9rem 1.25rem;">
    <span style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;white-space:nowrap;">Quick Actions</span>
    <a href="/dashboard/content"   class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">✨ Generate Content</a>
    <a href="/dashboard/content"   class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">📅 Schedule Posts</a>
    <a href="/dashboard/analytics" class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">📊 View Analytics</a>
    <a href="/dashboard/agents"    class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">🤖 Run Agents</a>
    <a href="/dashboard/trends"    class="btn btn-ghost" style="font-size:0.8rem;padding:0.35rem 0.85rem;">🔥 Trending</a>
</div>

<!-- ── Metric Cards ──────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(215px,1fr));gap:1.25rem;margin-bottom:2rem;">

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(59,130,246,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">📡</div>
            <span class="badge badge-success" style="font-size:0.7rem;"><?= htmlspecialchars($metrics['total_reach']['change'] ?? '+18.3%') ?></span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.2rem;"><?= htmlspecialchars($metrics['total_reach']['value'] ?? '2.4M') ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">Total Reach</div>
        <div style="margin-top:0.85rem;height:3px;background:var(--glass-bg);border-radius:2px;overflow:hidden;"><div style="width:73%;height:100%;background:linear-gradient(90deg,var(--blue),var(--purple));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(139,92,246,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">💫</div>
            <span class="badge badge-success" style="font-size:0.7rem;"><?= htmlspecialchars($metrics['engagement_rate']['change'] ?? '+2.1%') ?></span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.2rem;"><?= htmlspecialchars($metrics['engagement_rate']['value'] ?? '6.8%') ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">Engagement Rate</div>
        <div style="margin-top:0.85rem;height:3px;background:var(--glass-bg);border-radius:2px;overflow:hidden;"><div style="width:68%;height:100%;background:linear-gradient(90deg,var(--purple),var(--pink));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(16,185,129,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">👥</div>
            <span class="badge badge-success" style="font-size:0.7rem;"><?= htmlspecialchars($metrics['followers_gained']['change'] ?? '+9.4%') ?></span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.2rem;"><?= htmlspecialchars($metrics['followers_gained']['value'] ?? '12,847') ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">Followers Gained</div>
        <div style="margin-top:0.85rem;height:3px;background:var(--glass-bg);border-radius:2px;overflow:hidden;"><div style="width:59%;height:100%;background:linear-gradient(90deg,var(--green),var(--cyan));border-radius:2px;"></div></div>
    </div>

    <div class="metric-card glass-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
            <div style="width:42px;height:42px;border-radius:10px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;font-size:1.2rem;">🔥</div>
            <span class="badge badge-success" style="font-size:0.7rem;"><?= htmlspecialchars($metrics['viral_score']['change'] ?? '+5.2%') ?></span>
        </div>
        <div style="font-size:1.9rem;font-weight:700;color:var(--text-primary);line-height:1.1;margin-bottom:0.2rem;"><?= htmlspecialchars($metrics['viral_score']['value'] ?? '87') ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted);font-weight:500;">Viral Score</div>
        <div style="margin-top:0.85rem;height:3px;background:var(--glass-bg);border-radius:2px;overflow:hidden;"><div style="width:87%;height:100%;background:linear-gradient(90deg,var(--yellow),var(--orange));border-radius:2px;"></div></div>
    </div>

</div>

<!-- ── 30-Day Reach Chart + Trending ──────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem;margin-bottom:2rem;">

    <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
            <div>
                <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">30-Day Reach Trend</h3>
                <p style="font-size:0.8rem;color:var(--text-muted);">Cumulative reach over the last 30 days</p>
            </div>
            <span class="badge badge-success">+18.3% vs last month</span>
        </div>
        <div class="chart-wrapper" style="position:relative;height:220px;">
            <canvas id="reachChart" style="width:100%;height:100%;display:block;"></canvas>
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
        <div style="display:flex;flex-direction:column;gap:0.9rem;">
            <?php foreach ($trends as $trend): ?>
            <div style="padding:0.9rem;background:var(--glass-bg);border-radius:var(--radius-md);border:1px solid var(--glass-border);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.45rem;">
                    <span style="font-size:0.875rem;font-weight:600;color:var(--blue-light);"><?= htmlspecialchars($trend['hashtag']) ?></span>
                    <span class="badge badge-success" style="font-size:0.68rem;"><?= htmlspecialchars($trend['growth']) ?></span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                    <span style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($trend['posts']) ?> posts</span>
                    <span style="font-size:0.72rem;color:var(--text-muted);">Relevance: <strong style="color:var(--green-light);"><?= (int)$trend['relevance'] ?>%</strong></span>
                </div>
                <div style="height:2px;background:rgba(255,255,255,0.06);border-radius:2px;overflow:hidden;">
                    <div style="width:<?= min(100,(int)$trend['relevance']) ?>%;height:100%;background:linear-gradient(90deg,var(--blue),var(--purple));border-radius:2px;"></div>
                </div>
            </div>
            <?php endforeach ?>
        </div>
        <a href="/dashboard/copywriting" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1rem;font-size:0.8rem;">✨ Generate Trend Content</a>
    </div>

</div>

<!-- ── AI Agent Status Cards ──────────────────────────────────────────────── -->
<div class="glass-card" style="margin-bottom:2rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">🤖 AI Agent Status</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">Real-time status of all autonomous agents</p>
        </div>
        <a href="/dashboard/agents" class="btn btn-ghost" style="font-size:0.8rem;padding:0.4rem 0.9rem;">Manage All Agents →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(195px,1fr));gap:1rem;">
        <?php foreach ($agents as $agent): ?>
        <div class="agent-card glass-card glass-card-sm" style="padding:1rem;border-radius:var(--radius-md);">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                <span style="font-size:1.3rem;flex-shrink:0;"><?= $agent['icon'] ?></span>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:0.8rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($agent['name']) ?></div>
                </div>
                <span class="status-dot <?= $agent['status'] === 'running' ? 'status-running' : 'status-idle' ?>" style="flex-shrink:0;"></span>
            </div>
            <div style="display:flex;flex-direction:column;gap:0.3rem;">
                <div style="display:flex;justify-content:space-between;font-size:0.72rem;">
                    <span style="color:var(--text-muted);">Status</span>
                    <span style="color:<?= $agent['status'] === 'running' ? 'var(--green-light)' : 'var(--text-secondary)' ?>;font-weight:500;text-transform:capitalize;"><?= htmlspecialchars($agent['status']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.72rem;">
                    <span style="color:var(--text-muted);">Last run</span>
                    <span style="color:var(--text-secondary);"><?= htmlspecialchars($agent['last_run']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.72rem;">
                    <span style="color:var(--text-muted);">Tasks done</span>
                    <span style="color:var(--blue-light);font-weight:600;"><?= number_format((int)$agent['tasks_completed']) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach ?>
    </div>
</div>

<!-- ── Platform Health ────────────────────────────────────────────────────── -->
<div class="glass-card" style="margin-bottom:2rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">🌐 Platform Health</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">Connected accounts and recent activity across 11 platforms</p>
        </div>
        <a href="/dashboard/settings" class="btn btn-ghost" style="font-size:0.8rem;padding:0.4rem 0.9rem;">Connect More →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.85rem;">
        <?php foreach ($platforms as $platform): ?>
        <?php $conn = (bool)$platform['connected']; $hex = htmlspecialchars($platform['hex']); ?>
        <div style="padding:0.9rem;background:var(--glass-bg);border-radius:var(--radius-md);border:1px solid <?= $conn ? 'rgba(16,185,129,0.2)' : 'var(--glass-border)' ?>;transition:border-color 0.2s;" onmouseover="this.style.borderColor='<?= $conn ? 'rgba(16,185,129,0.4)' : 'var(--glass-border-hover)' ?>'" onmouseout="this.style.borderColor='<?= $conn ? 'rgba(16,185,129,0.2)' : 'var(--glass-border)' ?>'">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.6rem;">
                <div style="width:30px;height:30px;border-radius:8px;background:<?= $hex ?>22;display:flex;align-items:center;justify-content:center;font-size:0.82rem;font-weight:800;color:<?= $hex ?>;"><?= htmlspecialchars($platform['icon']) ?></div>
                <?php if ($conn): ?>
                    <span style="width:8px;height:8px;background:var(--green);border-radius:50%;display:block;box-shadow:0 0 5px var(--green);flex-shrink:0;"></span>
                <?php else: ?>
                    <span style="width:8px;height:8px;background:var(--text-muted);border-radius:50%;display:block;flex-shrink:0;"></span>
                <?php endif ?>
            </div>
            <div style="font-size:0.8rem;font-weight:600;color:var(--text-primary);margin-bottom:0.25rem;"><?= htmlspecialchars($platform['name']) ?></div>
            <?php if ($conn): ?>
                <div style="font-size:0.72rem;color:var(--text-secondary);margin-bottom:0.15rem;"><?= htmlspecialchars($platform['followers']) ?> followers</div>
                <div style="font-size:0.7rem;color:var(--text-muted);">Last: <?= htmlspecialchars($platform['last_post']) ?></div>
            <?php else: ?>
                <div style="font-size:0.72rem;color:var(--red);margin-bottom:0.15rem;">Not connected</div>
                <a href="/dashboard/settings" style="font-size:0.7rem;color:var(--blue-light);">Connect now →</a>
            <?php endif ?>
        </div>
        <?php endforeach ?>
    </div>
</div>

<!-- ── Recent Content Table ───────────────────────────────────────────────── -->
<div class="glass-card" style="margin-bottom:2rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.5rem;">
        <div>
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:0.15rem;">📋 Recent Content</h3>
            <p style="font-size:0.8rem;color:var(--text-muted);">Latest posts across all platforms</p>
        </div>
        <a href="/dashboard/content" class="btn btn-ghost" style="font-size:0.8rem;padding:0.4rem 0.9rem;">View All →</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Title</th>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Platform</th>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Status</th>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:left;border-bottom:1px solid var(--glass-border);">Scheduled</th>
                    <th style="padding:0.7rem 1rem;font-size:0.72rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;text-align:right;border-bottom:1px solid var(--glass-border);">Viral Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentContent as $item): ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.04);transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                    <td style="padding:0.85rem 1rem;"><span style="font-size:0.875rem;color:var(--text-primary);font-weight:500;display:block;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($item['title']) ?></span></td>
                    <td style="padding:0.85rem 1rem;"><span style="font-size:0.8rem;color:var(--text-secondary);"><?= htmlspecialchars($item['platform']) ?></span></td>
                    <td style="padding:0.85rem 1rem;"><span class="<?= dashStatusBadge($item['status']) ?>" style="font-size:0.72rem;text-transform:capitalize;"><?= htmlspecialchars($item['status']) ?></span></td>
                    <td style="padding:0.85rem 1rem;"><span style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($item['scheduled_at']) ?></span></td>
                    <td style="padding:0.85rem 1rem;text-align:right;">
                        <?php $vs=(int)$item['viral_score']; $vc=$vs>=85?'var(--green-light)':($vs>=70?'var(--yellow)':'var(--text-secondary)'); ?>
                        <span style="font-size:0.875rem;font-weight:700;color:<?= $vc ?>;"><?= $vs ?></span><span style="font-size:0.7rem;color:var(--text-muted);">/100</span>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Inline Chart Script ─────────────────────────────────────────────────── -->
<script>
(function() {
    'use strict';
    var reachData = <?= json_encode(array_values($reachData), JSON_NUMERIC_CHECK) ?>;

    function drawReachChart() {
        var canvas = document.getElementById('reachChart');
        if (!canvas) return;
        var container = canvas.parentElement;
        var W   = container.offsetWidth  || 560;
        var H   = container.offsetHeight || 220;
        var dpr = window.devicePixelRatio || 1;
        canvas.width        = W * dpr;
        canvas.height       = H * dpr;
        canvas.style.width  = W + 'px';
        canvas.style.height = H + 'px';
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);

        var PAD  = { top: 18, right: 16, bottom: 38, left: 52 };
        var cW   = W - PAD.left - PAD.right;
        var cH   = H - PAD.top  - PAD.bottom;
        var n    = reachData.length;
        var maxV = Math.max.apply(null, reachData);
        var minV = Math.min.apply(null, reachData);
        var rng  = maxV - minV || 1;

        // Grid + Y labels
        for (var gi = 0; gi <= 4; gi++) {
            var gy = PAD.top + (cH / 4) * gi;
            ctx.beginPath(); ctx.moveTo(PAD.left, gy); ctx.lineTo(PAD.left + cW, gy);
            ctx.strokeStyle = 'rgba(255,255,255,0.06)'; ctx.lineWidth = 1; ctx.stroke();
            var lv  = Math.round(maxV - (rng / 4) * gi);
            var lbl = lv >= 1000 ? (lv / 1000).toFixed(1) + 'K' : String(lv);
            ctx.fillStyle = 'rgba(148,163,184,0.75)';
            ctx.font = '10px Inter,system-ui,sans-serif';
            ctx.textAlign = 'right';
            ctx.fillText(lbl, PAD.left - 6, gy + 4);
        }

        // X labels every 5 days
        ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(148,163,184,0.7)';
        for (var xi = 0; xi < n; xi += 5) {
            ctx.fillText('Day '+(xi+1), PAD.left + (xi/(n-1))*cW, H - PAD.bottom + 15);
        }

        // Points
        var pts = reachData.map(function(v, i) {
            return { x: PAD.left + (i/(n-1))*cW, y: PAD.top + (1-(v-minV)/rng)*cH };
        });

        // Gradient fill
        var grad = ctx.createLinearGradient(0, PAD.top, 0, PAD.top + cH);
        grad.addColorStop(0,   'rgba(59,130,246,0.38)');
        grad.addColorStop(0.6, 'rgba(139,92,246,0.10)');
        grad.addColorStop(1,   'rgba(59,130,246,0)');
        ctx.beginPath();
        ctx.moveTo(pts[0].x, PAD.top + cH);
        ctx.lineTo(pts[0].x, pts[0].y);
        for (var fi = 1; fi < pts.length; fi++) {
            var fcx = (pts[fi-1].x + pts[fi].x) / 2;
            ctx.bezierCurveTo(fcx, pts[fi-1].y, fcx, pts[fi].y, pts[fi].x, pts[fi].y);
        }
        ctx.lineTo(pts[pts.length-1].x, PAD.top + cH);
        ctx.closePath(); ctx.fillStyle = grad; ctx.fill();

        // Line stroke
        ctx.beginPath(); ctx.moveTo(pts[0].x, pts[0].y);
        for (var li = 1; li < pts.length; li++) {
            var lcx = (pts[li-1].x + pts[li].x) / 2;
            ctx.bezierCurveTo(lcx, pts[li-1].y, lcx, pts[li].y, pts[li].x, pts[li].y);
        }
        ctx.strokeStyle = '#3B82F6'; ctx.lineWidth = 2.5; ctx.lineJoin = 'round'; ctx.stroke();

        // End-point dot
        var lp = pts[pts.length - 1];
        ctx.beginPath(); ctx.arc(lp.x, lp.y, 5, 0, Math.PI*2);
        ctx.fillStyle = '#3B82F6'; ctx.fill();
        ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.stroke();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', drawReachChart);
    } else {
        drawReachChart();
    }
    window.addEventListener('resize', drawReachChart);

    // Run full workflow
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

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/main.php';
