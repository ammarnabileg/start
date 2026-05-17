<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::agents()
// $brand, $brandId, $agentTasks, $agentSummary, $csrf

// All supported agent types with metadata
$agentMeta = [
    'content_generator'   => ['name' => 'Content Generator',   'icon' => '✍️',  'desc' => 'Generates platform-optimized captions, articles, and social posts in your brand voice.'],
    'strategy'            => ['name' => 'Strategy Agent',       'icon' => '🧠',  'desc' => 'Analyzes brand data and builds data-driven marketing strategies and content calendars.'],
    'community'           => ['name' => 'Community Manager',    'icon' => '💬',  'desc' => 'Monitors and responds to comments, DMs, and interactions across all platforms.'],
    'trend_hunter'        => ['name' => 'Trend Hunter',         'icon' => '🔥',  'desc' => 'Scans trending topics, hashtags, and sounds to identify viral opportunities.'],
    'analytics'           => ['name' => 'Analytics Agent',      'icon' => '📊',  'desc' => 'Tracks KPIs, generates performance reports, and surfaces AI insights.'],
    'publishing'          => ['name' => 'Publishing Agent',     'icon' => '📤',  'desc' => 'Schedules and publishes approved content across connected platforms automatically.'],
    'copywriting'         => ['name' => 'Copywriting Agent',    'icon' => '🖊️', 'desc' => 'Generates headlines, ad copy, captions, hooks, and long-form content on demand.'],
    'research'            => ['name' => 'Research Agent',       'icon' => '🔍',  'desc' => 'Researches competitors, audience trends, and market opportunities.'],
    'strategy_extractor'  => ['name' => 'Strategy Extractor',  'icon' => '🗂️',  'desc' => 'Extracts brand voice, goals, and insights from uploaded documents.'],
];

// Compute counts
$runningCount   = 0;
$completedCount = 0;
$failedCount    = 0;
foreach ($agentSummary as $s) {
    $runningCount   += (int)($s['running'] ?? 0);
    $completedCount += (int)($s['completed'] ?? 0);
    $failedCount    += (int)($s['failed'] ?? 0);
}
?>

<!-- Header -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">AI Agent Command Center 🤖</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;"><?= count($agentMeta) ?> specialized AI agents working around the clock to grow your brand</p>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        <?php if ($runningCount > 0): ?>
        <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.82rem;color:var(--green-light);padding:0.4rem 0.85rem;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);border-radius:99px;">
            <span class="status-dot status-running"></span>
            <?= $runningCount ?> Running
        </div>
        <?php endif; ?>
        <?php if ($brandId): ?>
        <button class="btn btn-primary" onclick="runAllAgents()" id="runAllBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Run All Agents
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$brandId): ?>
<div class="glass-card" style="text-align:center;padding:4rem 2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">🤖</div>
    <h3 style="font-size:1rem;margin-bottom:0.5rem;color:var(--text-secondary);">Set up a brand first</h3>
    <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.25rem;">Create a brand to enable AI agents for your workspace.</p>
    <a href="/brands/create" class="btn btn-primary">Create Brand</a>
</div>
<?php else: ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;">

    <!-- Agent Cards -->
    <div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;margin-bottom:1.5rem;">
            <?php foreach ($agentMeta as $type => $meta):
                $summary   = $agentSummary[$type] ?? null;
                $total     = $summary ? (int)$summary['total_tasks']  : 0;
                $completed = $summary ? (int)$summary['completed']     : 0;
                $failed    = $summary ? (int)$summary['failed']        : 0;
                $running   = $summary ? (int)$summary['running']       : 0;
                $lastRun   = $summary && $summary['last_run'] ? date('M j, g:i a', strtotime($summary['last_run'])) : 'Never';
                $isRunning = $running > 0;
                $successRate = $total > 0 ? round($completed / $total * 100) : 0;
            ?>
            <div class="glass-card" style="padding:1.1rem;<?= $isRunning ? 'border-color:rgba(16,185,129,0.3);' : '' ?>">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <span style="font-size:1.4rem;"><?= $meta['icon'] ?></span>
                        <div>
                            <div style="font-size:0.83rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($meta['name']) ?></div>
                            <div style="display:flex;align-items:center;gap:0.35rem;margin-top:0.15rem;">
                                <span class="status-dot <?= $isRunning ? 'status-running' : '' ?>" style="<?= !$isRunning ? 'background:var(--text-muted);box-shadow:none;' : '' ?>"></span>
                                <span style="font-size:0.7rem;color:<?= $isRunning ? 'var(--green-light)' : 'var(--text-muted)' ?>;"><?= $isRunning ? 'Running' : 'Idle' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.4rem;margin-bottom:0.75rem;">
                    <div style="text-align:center;padding:0.4rem;background:var(--glass-bg);border-radius:var(--radius-sm);">
                        <div style="font-size:1rem;font-weight:700;color:var(--blue-light);"><?= number_format($total) ?></div>
                        <div style="font-size:0.62rem;color:var(--text-muted);">Total</div>
                    </div>
                    <div style="text-align:center;padding:0.4rem;background:var(--glass-bg);border-radius:var(--radius-sm);">
                        <div style="font-size:1rem;font-weight:700;color:var(--green-light);"><?= number_format($completed) ?></div>
                        <div style="font-size:0.62rem;color:var(--text-muted);">Done</div>
                    </div>
                    <div style="text-align:center;padding:0.4rem;background:var(--glass-bg);border-radius:var(--radius-sm);">
                        <div style="font-size:1rem;font-weight:700;color:<?= $failed > 0 ? 'var(--red)' : 'var(--text-muted)' ?>;"><?= $failed ?></div>
                        <div style="font-size:0.62rem;color:var(--text-muted);">Failed</div>
                    </div>
                </div>

                <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.75rem;">Last run: <span style="color:var(--text-secondary);"><?= htmlspecialchars($lastRun) ?></span></div>

                <?php if ($total > 0): ?>
                <div style="margin-bottom:0.75rem;">
                    <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--text-muted);margin-bottom:0.3rem;">
                        <span>Success Rate</span>
                        <span style="color:var(--green-light);"><?= $successRate ?>%</span>
                    </div>
                    <div style="height:4px;background:var(--glass-bg);border-radius:2px;overflow:hidden;">
                        <div style="width:<?= $successRate ?>%;height:100%;background:var(--green);border-radius:2px;"></div>
                    </div>
                </div>
                <?php endif; ?>

                <button class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;font-size:0.78rem;" onclick="runAgent('<?= htmlspecialchars($type) ?>', this)">
                    ▶ Run <?= htmlspecialchars($meta['name']) ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right: Task Log -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <!-- Summary stats -->
        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:0.75rem;">📈 Task Stats</h3>
            <?php
            $totalTasks = array_sum(array_map(fn($s) => (int)$s['total_tasks'], $agentSummary));
            ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
                <div style="text-align:center;padding:0.75rem;background:var(--glass-bg);border-radius:var(--radius-sm);">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--blue-light);"><?= number_format($totalTasks) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">Total Tasks</div>
                </div>
                <div style="text-align:center;padding:0.75rem;background:var(--glass-bg);border-radius:var(--radius-sm);">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--green-light);"><?= number_format($completedCount) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">Completed</div>
                </div>
                <div style="text-align:center;padding:0.75rem;background:var(--glass-bg);border-radius:var(--radius-sm);">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--yellow);"><?= number_format($runningCount) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">Running</div>
                </div>
                <div style="text-align:center;padding:0.75rem;background:var(--glass-bg);border-radius:var(--radius-sm);">
                    <div style="font-size:1.5rem;font-weight:700;color:<?= $failedCount > 0 ? 'var(--red)' : 'var(--text-muted)' ?>;"><?= number_format($failedCount) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">Failed</div>
                </div>
            </div>
        </div>

        <!-- Recent Tasks -->
        <div class="glass-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <h3 style="font-size:0.9rem;font-weight:600;">📋 Recent Tasks</h3>
                <span style="font-size:0.72rem;color:var(--text-muted);">Last 20</span>
            </div>
            <?php if (empty($agentTasks)): ?>
            <div style="text-align:center;padding:2rem 0.5rem;color:var(--text-muted);">
                <div style="font-size:2rem;margin-bottom:0.5rem;">📋</div>
                <p style="font-size:0.82rem;">No tasks yet. Run your first agent above.</p>
            </div>
            <?php else: ?>
            <?php foreach ($agentTasks as $task):
                $meta   = $agentMeta[$task['agent_type']] ?? ['name' => ucfirst(str_replace('_', ' ', $task['agent_type'])), 'icon' => '🤖'];
                $ts     = strtotime($task['created_at'] ?? 'now');
                $diff   = time() - $ts;
                $ago    = $diff < 60 ? $diff . 's' : ($diff < 3600 ? (int)($diff / 60) . 'm' : ($diff < 86400 ? (int)($diff / 3600) . 'h' : (int)($diff / 86400) . 'd')) . ' ago';
                $dotColor = match($task['status']) {
                    'completed' => 'var(--green)',
                    'running'   => 'var(--yellow)',
                    'failed'    => 'var(--red)',
                    default     => 'var(--text-muted)',
                };
            ?>
            <div style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.8rem;">
                <span style="font-size:1rem;flex-shrink:0;"><?= $meta['icon'] ?></span>
                <div style="flex:1;min-width:0;">
                    <div style="color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($meta['name']) ?></div>
                    <?php if (!empty($task['error_message'])): ?>
                    <div style="font-size:0.7rem;color:var(--red);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(mb_substr($task['error_message'], 0, 50)) ?></div>
                    <?php endif; ?>
                </div>
                <span style="color:var(--text-muted);font-size:0.7rem;white-space:nowrap;"><?= $ago ?></span>
                <span style="width:7px;height:7px;border-radius:50%;background:<?= $dotColor ?>;flex-shrink:0;"></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:0.75rem;">⚡ Quick Actions</h3>
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <button class="btn btn-primary" style="width:100%;justify-content:center;font-size:0.82rem;" onclick="runAllAgents()" id="runAllBtn2">🚀 Run All Agents</button>
                <button class="btn btn-ghost" style="width:100%;justify-content:flex-start;font-size:0.82rem;" onclick="runAgent('content_generator', this)">✍️ Run Content Generator</button>
                <button class="btn btn-ghost" style="width:100%;justify-content:flex-start;font-size:0.82rem;" onclick="runAgent('community', this)">💬 Run Community Manager</button>
                <button class="btn btn-ghost" style="width:100%;justify-content:flex-start;font-size:0.82rem;" onclick="runAgent('trend_hunter', this)">🔥 Run Trend Hunter</button>
                <button class="btn btn-ghost" style="width:100%;justify-content:flex-start;font-size:0.82rem;" onclick="runAgent('analytics', this)">📊 Run Analytics Agent</button>
            </div>
        </div>

    </div>
</div>

<?php endif; // brand ?>

<!-- Toast -->
<div id="toast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:none;">
    <div id="toastMsg" style="background:var(--navy-mid,#1e1e3f);border:1px solid var(--glass-border);border-left:3px solid var(--green-light);border-radius:var(--radius-md);padding:.75rem 1.25rem;font-size:.85rem;box-shadow:0 4px 24px rgba(0,0,0,.4);"></div>
</div>

<script>
const CSRF = <?= json_encode($csrf ?? '') ?>;

function showToast(msg, ok = true) {
    const m = document.getElementById('toastMsg');
    if (!m) return;
    m.textContent = msg;
    m.style.borderLeftColor = ok ? 'var(--green-light)' : '#f87171';
    const t = document.getElementById('toast');
    t.style.display = 'block';
    clearTimeout(t._tid);
    t._tid = setTimeout(() => t.style.display = 'none', 4000);
}

async function apiPost(url, data) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(data),
    });
    return r.json();
}

async function runAgent(type, btn) {
    const origText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Running…'; }
    try {
        const d = await apiPost('/api/agents/run', { agent_type: type });
        showToast(d.message || (d.success ? 'Agent started!' : 'Failed to start agent'), d.success !== false);
        if (d.success) setTimeout(() => location.reload(), 2000);
    } catch(e) { showToast('Error starting agent', false); }
    if (btn) { btn.disabled = false; btn.textContent = origText; }
}

async function runAllAgents() {
    const btns = [document.getElementById('runAllBtn'), document.getElementById('runAllBtn2')];
    btns.forEach(b => { if (b) { b.disabled = true; b.textContent = '⏳ Running…'; } });
    try {
        const d = await apiPost('/api/agents/run-workflow', { trigger: 'manual' });
        showToast(d.message || (d.success ? 'All agents started!' : 'Failed'), d.success !== false);
        if (d.success) setTimeout(() => location.reload(), 2500);
    } catch(e) { showToast('Error starting agents', false); }
    btns.forEach(b => { if (b) { b.disabled = false; b.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg> Run All Agents'; } });
}
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
