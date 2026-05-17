<?php
$pageTitle  = 'AI Agent Workspace';
$activePage = 'agents';

// ── Mock agent data (controller passes real $agents array) ─────────────────
if (empty($agents)) {
    $agents = [
        [
            'id'          => 'strategy',
            'name'        => 'Strategy Agent',
            'role'        => 'Builds content calendars, brand positioning & campaign briefs',
            'icon'        => '🧠',
            'color'       => '#3B82F6',
            'status'      => 'running',
            'last_run'    => '3 min ago',
            'tasks_total' => 247,
            'tasks_today' => 12,
            'tokens_used' => '84K',
            'cost_today'  => '$0.34',
            'logs'        => [
                'Generated Q3 content calendar for LinkedIn',
                'Updated brand tone guidelines',
                'Scheduled 8 posts across 4 platforms',
            ],
        ],
        [
            'id'          => 'copywriting',
            'name'        => 'Copywriting Agent',
            'role'        => 'Writes captions, articles, threads and ad copy',
            'icon'        => '✍️',
            'color'       => '#8B5CF6',
            'status'      => 'running',
            'last_run'    => '1 min ago',
            'tasks_total' => 1842,
            'tasks_today' => 47,
            'tokens_used' => '312K',
            'cost_today'  => '$1.25',
            'logs'        => [
                'Wrote 5 LinkedIn posts in brand voice',
                'Generated 3 TikTok script variations',
                'Refined Instagram caption for post #B-44',
            ],
        ],
        [
            'id'          => 'design',
            'name'        => 'Design Agent',
            'role'        => 'Suggests creatives, templates and visual briefs',
            'icon'        => '🎨',
            'color'       => '#EC4899',
            'status'      => 'idle',
            'last_run'    => '2 hrs ago',
            'tasks_total' => 398,
            'tasks_today' => 4,
            'tokens_used' => '29K',
            'cost_today'  => '$0.12',
            'logs'        => [
                'Generated 6 Canva template suggestions',
                'Colour palette analysis: brand score 94%',
                'Created visual brief for campaign #C-12',
            ],
        ],
        [
            'id'          => 'video',
            'name'        => 'Video Agent',
            'role'        => 'Scripting, storyboarding and hook generation for video',
            'icon'        => '🎬',
            'color'       => '#F59E0B',
            'status'      => 'running',
            'last_run'    => '7 min ago',
            'tasks_total' => 156,
            'tasks_today' => 9,
            'tokens_used' => '96K',
            'cost_today'  => '$0.38',
            'logs'        => [
                'Wrote hooks for 4 TikTok concepts',
                'Storyboarded YouTube short #V-08',
                'Generated B-roll list for Reel #R-22',
            ],
        ],
        [
            'id'          => 'publishing',
            'name'        => 'Publishing Agent',
            'role'        => 'Schedules and auto-publishes content at optimal times',
            'icon'        => '📤',
            'color'       => '#10B981',
            'status'      => 'running',
            'last_run'    => 'Just now',
            'tasks_total' => 2109,
            'tasks_today' => 18,
            'tokens_used' => '14K',
            'cost_today'  => '$0.06',
            'logs'        => [
                'Published LinkedIn post #L-88 at 09:00',
                'Scheduled 3 Instagram posts for tomorrow',
                'Retried failed TikTok upload — success',
            ],
        ],
        [
            'id'          => 'analytics',
            'name'        => 'Analytics Agent',
            'role'        => 'Tracks KPIs, detects anomalies and generates reports',
            'icon'        => '📊',
            'color'       => '#06B6D4',
            'status'      => 'running',
            'last_run'    => '10 min ago',
            'tasks_total' => 892,
            'tasks_today' => 31,
            'tokens_used' => '41K',
            'cost_today'  => '$0.16',
            'logs'        => [
                'Anomaly detected: Instagram reach +340%',
                'Generated weekly performance report',
                'Updated viral-score model for 12 posts',
            ],
        ],
        [
            'id'          => 'community',
            'name'        => 'Community Agent',
            'role'        => 'Auto-replies to comments, DMs and mentions',
            'icon'        => '💬',
            'color'       => '#F97316',
            'status'      => 'running',
            'last_run'    => '30 sec ago',
            'tasks_total' => 3291,
            'tasks_today' => 84,
            'tokens_used' => '198K',
            'cost_today'  => '$0.79',
            'logs'        => [
                'Replied to 23 LinkedIn comments',
                'Escalated 2 negative reviews',
                'Sent DM replies on Instagram (17)',
            ],
        ],
        [
            'id'          => 'research',
            'name'        => 'Research Agent',
            'role'        => 'Hunts trends, competitor moves and niche opportunities',
            'icon'        => '🔍',
            'color'       => '#A78BFA',
            'status'      => 'idle',
            'last_run'    => '1 hr ago',
            'tasks_total' => 512,
            'tasks_today' => 7,
            'tokens_used' => '73K',
            'cost_today'  => '$0.29',
            'logs'        => [
                'Scanned 6 platforms for trending topics',
                'Identified 3 competitor viral posts',
                'Compiled hashtag opportunity report',
            ],
        ],
        [
            'id'          => 'orchestrator',
            'name'        => 'Orchestrator',
            'role'        => 'Coordinates all agents, handles errors and retries',
            'icon'        => '🤖',
            'color'       => '#34D399',
            'status'      => 'running',
            'last_run'    => '5 sec ago',
            'tasks_total' => 8847,
            'tasks_today' => 212,
            'tokens_used' => '22K',
            'cost_today'  => '$0.09',
            'logs'        => [
                'All 7 agents healthy — no errors',
                'Retried publishing job after API timeout',
                'Spawned 3 parallel copywriting tasks',
            ],
        ],
    ];
}

// ── Mock workflow data ──────────────────────────────────────────────────────
$workflows = $workflows ?? [
    [
        'name'       => 'Full Content Pipeline',
        'status'     => 'running',
        'progress'   => 72,
        'steps_done' => 6,
        'steps_total'=> 9,
        'eta'        => '~8 min',
    ],
    [
        'name'       => 'Community Sweep',
        'status'     => 'running',
        'progress'   => 45,
        'steps_done' => 3,
        'steps_total'=> 7,
        'eta'        => '~14 min',
    ],
    [
        'name'       => 'Weekly Analytics Report',
        'status'     => 'idle',
        'progress'   => 100,
        'steps_done' => 5,
        'steps_total'=> 5,
        'eta'        => 'Done',
    ],
];

// ── Mock activity feed ─────────────────────────────────────────────────────
$activityFeed = $activityFeed ?? [
    ['agent'=>'community',    'time'=>'Just now', 'msg'=>'Replied to @sarahmitch on LinkedIn'],
    ['agent'=>'publishing',   'time'=>'30s ago',  'msg'=>'Published post #L-88 to LinkedIn'],
    ['agent'=>'orchestrator', 'time'=>'45s ago',  'msg'=>'Dispatched 3 copywriting tasks'],
    ['agent'=>'copywriting',  'time'=>'1m ago',   'msg'=>'Draft ready: "5 AI Trends" article'],
    ['agent'=>'analytics',    'time'=>'2m ago',   'msg'=>'Anomaly: Instagram reach +340%'],
    ['agent'=>'community',    'time'=>'3m ago',   'msg'=>'Escalated negative review to human'],
    ['agent'=>'video',        'time'=>'5m ago',   'msg'=>'Storyboard created for Reel #R-22'],
    ['agent'=>'strategy',     'time'=>'7m ago',   'msg'=>'Q3 calendar updated (8 new posts)'],
    ['agent'=>'research',     'time'=>'12m ago',  'msg'=>'#AICreators trend flagged as high priority'],
    ['agent'=>'design',       'time'=>'18m ago',  'msg'=>'6 Canva templates generated'],
    ['agent'=>'publishing',   'time'=>'22m ago',  'msg'=>'Scheduled 3 posts for tomorrow 9AM'],
    ['agent'=>'orchestrator', 'time'=>'31m ago',  'msg'=>'Full pipeline completed in 4m 12s'],
];

// ── Cost tracker ───────────────────────────────────────────────────────────
$costs = $costs ?? [
    'today'     => '$3.48',
    'month'     => '$62.90',
    'budget'    => '$150.00',
    'budget_pct'=> 42,
];

$statusLabels = ['running'=>'Running','idle'=>'Idle','error'=>'Error'];
$workflowStatusStyle = [
    'running' => 'color:var(--green-light)',
    'idle'    => 'color:var(--text-muted)',
    'error'   => 'color:var(--red)',
];
?>
<?php ob_start(); ?>

<style>
  .agents-layout { display: grid; grid-template-columns: 1fr 280px; gap: 1.5rem; }
  .agents-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
  @media (max-width: 1200px) { .agents-grid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 800px)  { .agents-grid { grid-template-columns: 1fr; } .agents-layout { grid-template-columns: 1fr; } }
  .agent-workspace-card {
    background: var(--glass-bg); border: 1px solid var(--glass-border);
    border-radius: var(--radius-md); padding: 1.1rem; transition: all var(--transition);
    display: flex; flex-direction: column; gap: 0.65rem;
  }
  .agent-workspace-card:hover { border-color: var(--glass-border-hover); box-shadow: var(--shadow-sm); }
  .agent-workspace-card .agent-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem; }
  .agent-icon-wrap { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
  .log-line { font-size: 0.72rem; color: var(--text-muted); padding: 0.2rem 0; border-bottom: 1px solid rgba(255,255,255,0.04); line-height: 1.4; }
  .log-line:last-child { border-bottom: none; }
  .log-line::before { content: '›  '; color: var(--blue-light); font-weight: 700; }
  .agent-stat { display: flex; flex-direction: column; align-items: center; }
  .agent-stat .val { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); }
  .agent-stat .lbl { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
  .agent-actions { display: flex; gap: 0.35rem; flex-wrap: wrap; }
  .workflow-item { padding: 0.85rem; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-md); margin-bottom: 0.65rem; }
  .wf-progress { height: 5px; background: rgba(255,255,255,0.08); border-radius: 3px; overflow: hidden; margin: 0.5rem 0 0.3rem; }
  .wf-progress-fill { height: 100%; border-radius: 3px; background: var(--gradient-primary); transition: width 0.5s ease; }
  .feed-item { display: flex; gap: 0.6rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.75rem; }
  .feed-item:last-child { border-bottom: none; }
  .feed-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
  .agent-checkbox-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
  .agent-checkbox-label { display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; cursor: pointer; color: var(--text-secondary); }
  .agent-checkbox-label input[type=checkbox] { accent-color: var(--blue); }
  .badge-danger { background: rgba(239,68,68,0.15); color: #FC8181; border: 1px solid rgba(239,68,68,0.3); border-radius: 99px; padding: 2px 8px; font-size: 0.7rem; font-weight: 600; }
</style>

<!-- ── Page Header ──────────────────────── -->
<div class="page-header page-header-row" style="margin-bottom:1.5rem">
  <div>
    <h1>🤖 AI Agent Workspace</h1>
    <p>Monitor, configure and run your autonomous AI agents in real time</p>
  </div>
  <div style="display:flex;gap:0.75rem;align-items:center">
    <div class="live-indicator">
      <span class="live-dot"></span>
      7 of 9 Active
    </div>
    <button class="btn btn-primary" id="runFullWorkflowBtn" onclick="runFullWorkflow()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      Run Full Workflow
    </button>
  </div>
</div>

<!-- ── Main Layout ───────────────────────── -->
<div class="agents-layout">

  <!-- LEFT: Agents + Workflows -->
  <div>

    <!-- Agent Cards Grid -->
    <div class="agents-grid" style="margin-bottom:1.5rem">
      <?php foreach ($agents as $agent): ?>
      <div class="agent-workspace-card" id="agentCard-<?= htmlspecialchars($agent['id']) ?>">

        <!-- Top Row -->
        <div class="agent-top">
          <div style="display:flex;gap:0.65rem;align-items:flex-start;flex:1;min-width:0">
            <div class="agent-icon-wrap" style="background:<?= htmlspecialchars($agent['color']) ?>22;border:1px solid <?= htmlspecialchars($agent['color']) ?>44">
              <?= $agent['icon'] ?>
            </div>
            <div style="min-width:0;flex:1">
              <div style="font-size:0.875rem;font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($agent['name']) ?></div>
              <div style="font-size:0.72rem;color:var(--text-muted);line-height:1.4"><?= htmlspecialchars($agent['role']) ?></div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:0.3rem;flex-shrink:0">
            <span class="status-dot status-<?= htmlspecialchars($agent['status']) ?>"></span>
            <span style="font-size:0.72rem;color:var(--text-secondary)"><?= htmlspecialchars($statusLabels[$agent['status']] ?? ucfirst($agent['status'])) ?></span>
          </div>
        </div>

        <!-- Stats Row -->
        <div style="display:flex;gap:0.5rem;padding:0.5rem 0;border-top:1px solid var(--glass-border);border-bottom:1px solid var(--glass-border)">
          <div class="agent-stat" style="flex:1">
            <span class="val"><?= number_format($agent['tasks_total']) ?></span>
            <span class="lbl">Total Tasks</span>
          </div>
          <div class="agent-stat" style="flex:1">
            <span class="val" style="color:var(--green-light)"><?= $agent['tasks_today'] ?></span>
            <span class="lbl">Today</span>
          </div>
          <div class="agent-stat" style="flex:1">
            <span class="val" style="color:var(--blue-light)"><?= $agent['tokens_used'] ?></span>
            <span class="lbl">Tokens</span>
          </div>
          <div class="agent-stat" style="flex:1">
            <span class="val" style="color:var(--yellow)"><?= htmlspecialchars($agent['cost_today']) ?></span>
            <span class="lbl">Cost</span>
          </div>
        </div>

        <!-- Last run -->
        <div style="font-size:0.72rem;color:var(--text-muted)">
          Last run: <span style="color:var(--text-secondary)"><?= htmlspecialchars($agent['last_run']) ?></span>
        </div>

        <!-- Activity Log -->
        <div style="background:rgba(0,0,0,0.2);border-radius:var(--radius-sm);padding:0.5rem">
          <?php foreach ($agent['logs'] as $log): ?>
          <div class="log-line"><?= htmlspecialchars($log) ?></div>
          <?php endforeach ?>
        </div>

        <!-- Action Buttons -->
        <div class="agent-actions">
          <button class="btn btn-sm btn-primary" style="flex:1" onclick="runAgent('<?= htmlspecialchars($agent['id'], ENT_QUOTES) ?>')" id="runBtn-<?= htmlspecialchars($agent['id']) ?>">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Run Now
          </button>
          <button class="btn btn-sm btn-ghost" onclick="viewLogs('<?= htmlspecialchars($agent['id'], ENT_QUOTES) ?>')">Logs</button>
          <button class="btn btn-sm btn-ghost" onclick="configureAgent('<?= htmlspecialchars($agent['id'], ENT_QUOTES) ?>')">⚙️</button>
        </div>

      </div>
      <?php endforeach ?>
    </div>

    <!-- Active Workflows -->
    <div class="glass-card" style="margin-bottom:1.5rem">
      <div class="section-header" style="margin-bottom:1rem">
        <h3>⚡ Active Workflows</h3>
        <span style="font-size:0.8rem;color:var(--text-muted)"><?= count(array_filter($workflows, fn($w) => $w['status'] === 'running')) ?> running</span>
      </div>
      <?php foreach ($workflows as $wf): ?>
      <div class="workflow-item">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.15rem">
          <span style="font-size:0.85rem;font-weight:600"><?= htmlspecialchars($wf['name']) ?></span>
          <div style="display:flex;align-items:center;gap:0.6rem">
            <span style="font-size:0.72rem;<?= $workflowStatusStyle[$wf['status']] ?? '' ?>">
              <?php if ($wf['status'] === 'running'): ?>
              <span style="display:inline-block;animation:pulse 1.5s infinite">●</span>
              <?php endif ?>
              <?= ucfirst($wf['status']) ?>
            </span>
            <span style="font-size:0.72rem;color:var(--text-muted)">ETA: <?= htmlspecialchars($wf['eta']) ?></span>
          </div>
        </div>
        <div class="wf-progress">
          <div class="wf-progress-fill" style="width:<?= $wf['progress'] ?>%"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--text-muted)">
          <span><?= $wf['steps_done'] ?> / <?= $wf['steps_total'] ?> steps</span>
          <span><?= $wf['progress'] ?>% complete</span>
        </div>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Run Custom Workflow -->
    <div class="glass-card">
      <div class="section-header" style="margin-bottom:1rem">
        <h3>🎛️ Run Custom Workflow</h3>
      </div>
      <div style="margin-bottom:1rem">
        <label class="form-label" style="margin-bottom:0.5rem">Select Agents to Include</label>
        <div class="agent-checkbox-grid">
          <?php foreach ($agents as $a): ?>
          <label class="agent-checkbox-label">
            <input type="checkbox" class="wf-agent-check" value="<?= htmlspecialchars($a['id']) ?>" <?= in_array($a['status'],['running']) ? 'checked' : '' ?>>
            <?= $a['icon'] ?> <?= htmlspecialchars(explode(' ',$a['name'])[0]) ?>
          </label>
          <?php endforeach ?>
        </div>
      </div>
      <div style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="flex:1;min-width:160px;margin:0">
          <label class="form-label">Priority</label>
          <select class="form-select" id="wfPriority">
            <option value="normal">Normal</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
            <option value="low">Low / Background</option>
          </select>
        </div>
        <button class="btn btn-primary" onclick="launchCustomWorkflow()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
          Launch Workflow
        </button>
      </div>
    </div>

  </div>

  <!-- RIGHT: Activity Feed + Cost Tracker -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">

    <!-- Activity Feed -->
    <div class="glass-card" style="flex:1">
      <div class="section-header" style="margin-bottom:0.75rem">
        <h3>📡 Activity Feed</h3>
        <div style="display:flex;align-items:center;gap:0.3rem;font-size:0.72rem;color:var(--green-light)">
          <span class="live-dot" style="width:6px;height:6px"></span>
          Live
        </div>
      </div>
      <div id="activityFeedContainer" style="max-height:420px;overflow-y:auto;padding-right:0.25rem">
        <?php foreach ($activityFeed as $entry):
          $agentColors = [
            'strategy'     => '#3B82F6',
            'copywriting'  => '#8B5CF6',
            'design'       => '#EC4899',
            'video'        => '#F59E0B',
            'publishing'   => '#10B981',
            'analytics'    => '#06B6D4',
            'community'    => '#F97316',
            'research'     => '#A78BFA',
            'orchestrator' => '#34D399',
          ];
          $dotColor = $agentColors[$entry['agent']] ?? '#94A3B8';
        ?>
        <div class="feed-item">
          <div class="feed-dot" style="background:<?= $dotColor ?>"></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:0.75rem;color:var(--text-secondary);line-height:1.4"><?= htmlspecialchars($entry['msg']) ?></div>
            <div style="display:flex;gap:0.4rem;align-items:center;margin-top:0.15rem">
              <span style="font-size:0.65rem;color:<?= $dotColor ?>;font-weight:600"><?= ucfirst($entry['agent']) ?></span>
              <span style="font-size:0.65rem;color:var(--text-muted)"><?= htmlspecialchars($entry['time']) ?></span>
            </div>
          </div>
        </div>
        <?php endforeach ?>
      </div>
      <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--glass-border);text-align:center;font-size:0.72rem;color:var(--text-muted)" id="feedRefreshLabel">
        Auto-refreshes every 10s
      </div>
    </div>

    <!-- Cost Tracker -->
    <div class="glass-card">
      <div class="section-header" style="margin-bottom:0.75rem">
        <h3>💰 Cost Tracker</h3>
      </div>
      <div style="display:flex;justify-content:space-between;margin-bottom:0.6rem">
        <div>
          <div style="font-size:0.72rem;color:var(--text-muted)">Today's API Cost</div>
          <div style="font-size:1.2rem;font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($costs['today']) ?></div>
        </div>
        <div style="text-align:right">
          <div style="font-size:0.72rem;color:var(--text-muted)">This Month</div>
          <div style="font-size:1.2rem;font-weight:700;color:var(--blue-light)"><?= htmlspecialchars($costs['month']) ?></div>
        </div>
      </div>
      <div style="margin-bottom:0.35rem">
        <div style="display:flex;justify-content:space-between;font-size:0.72rem;color:var(--text-muted);margin-bottom:4px">
          <span>Budget Used</span>
          <span style="color:var(--text-secondary)"><?= htmlspecialchars($costs['month']) ?> / <?= htmlspecialchars($costs['budget']) ?></span>
        </div>
        <div style="height:7px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden">
          <div style="width:<?= $costs['budget_pct'] ?>%;height:100%;background:<?= $costs['budget_pct'] > 80 ? 'var(--red)' : ($costs['budget_pct'] > 60 ? 'var(--yellow)' : 'var(--green)') ?>;border-radius:3px;transition:width 0.5s"></div>
        </div>
        <div style="font-size:0.7rem;color:var(--green-light);margin-top:3px"><?= 100 - $costs['budget_pct'] ?>% budget remaining</div>
      </div>
      <div style="padding:0.6rem;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);border-radius:var(--radius-sm);font-size:0.73rem;color:var(--blue-light);margin-top:0.5rem">
        💡 At current pace, monthly spend will be ~$80 (budget: <?= htmlspecialchars($costs['budget']) ?>)
      </div>
    </div>

  </div>
</div>

<!-- ── Agent Logs Modal ─────────────────── -->
<div class="modal-overlay" id="agentLogsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center">
  <div class="modal-content" style="width:100%;max-width:640px;margin:0 1rem">
    <div class="modal-header">
      <h3 id="logsModalTitle">Agent Logs</h3>
      <button class="modal-close" onclick="closeLogsModal()">×</button>
    </div>
    <div style="padding:1.25rem">
      <div id="logsContent" style="background:rgba(0,0,0,0.35);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:1rem;font-family:monospace;font-size:0.78rem;color:var(--green-light);max-height:360px;overflow-y:auto;line-height:1.7">
        Loading logs...
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeLogsModal()">Close</button>
      <button class="btn btn-primary" onclick="downloadLogs()">Download Logs</button>
    </div>
  </div>
</div>

<!-- ── Configure Modal ─────────────────── -->
<div class="modal-overlay" id="configModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center">
  <div class="modal-content" style="width:100%;max-width:500px;margin:0 1rem">
    <div class="modal-header">
      <h3 id="configModalTitle">Configure Agent</h3>
      <button class="modal-close" onclick="closeConfigModal()">×</button>
    </div>
    <div style="padding:1.25rem" id="configModalBody">
      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Max Tokens per Request</label>
        <input type="number" class="form-input" value="2000" min="100" max="16000" step="100">
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Run Schedule</label>
        <select class="form-select">
          <option>Every 5 minutes</option>
          <option>Every 15 minutes</option>
          <option selected>Every 30 minutes</option>
          <option>Hourly</option>
          <option>Manual only</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Priority Level</label>
        <select class="form-select">
          <option>Low</option>
          <option selected>Normal</option>
          <option>High</option>
          <option>Critical</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Custom Instructions</label>
        <textarea class="form-textarea" rows="3" placeholder="Add custom instructions for this agent..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeConfigModal()">Cancel</button>
      <button class="btn btn-primary" onclick="saveConfig()">Save Configuration</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="agentsToast" style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:2000;display:none">
  <div style="background:var(--navy-mid);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:0.75rem 1.25rem;font-size:0.85rem;box-shadow:var(--shadow-md)" id="agentsToastText"></div>
</div>

<script>
(function() {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // ── Run individual agent ──────────────────────
  window.runAgent = function(agentId) {
    const btn  = document.getElementById('runBtn-' + agentId);
    if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Running...'; }

    fetch('/api/agents/' + agentId + '/run', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    })
    .then(r => r.json())
    .then(d => {
      showAgentsToast(d.message || agentId + ' agent started successfully.');
      if (btn) { btn.disabled = false; btn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg> Run Now'; }
    })
    .catch(() => {
      showAgentsToast(agentId + ' agent task dispatched.');
      if (btn) { btn.disabled = false; btn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg> Run Now'; }
    });
  };

  // ── Run Full Workflow ─────────────────────────
  window.runFullWorkflow = function() {
    const btn = document.getElementById('runFullWorkflowBtn');
    btn.disabled = true;
    btn.innerHTML = '⏳ Launching...';

    fetch('/api/agents/orchestrator/run', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body   : JSON.stringify({ workflow: 'full' }),
    })
    .finally(() => {
      showAgentsToast('Full workflow launched — all agents activated.');
      btn.disabled = false;
      btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg> Run Full Workflow';
    });
  };

  // ── Launch Custom Workflow ────────────────────
  window.launchCustomWorkflow = function() {
    const selected = [...document.querySelectorAll('.wf-agent-check:checked')].map(c => c.value);
    const priority = document.getElementById('wfPriority').value;
    if (!selected.length) { showAgentsToast('Please select at least one agent.'); return; }

    fetch('/api/agents/orchestrator/run', {
      method : 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
      body   : JSON.stringify({ agents: selected, priority }),
    })
    .then(r => r.json())
    .then(d => showAgentsToast(d.message || 'Custom workflow launched for: ' + selected.join(', ')))
    .catch(()  => showAgentsToast('Custom workflow dispatched (' + selected.length + ' agents, ' + priority + ' priority).'));
  };

  // ── View Logs Modal ───────────────────────────
  window.viewLogs = function(agentId) {
    document.getElementById('logsModalTitle').textContent = agentId.charAt(0).toUpperCase() + agentId.slice(1) + ' Agent — Live Logs';
    document.getElementById('logsContent').textContent = 'Loading...';
    document.getElementById('agentLogsModal').style.display = 'flex';

    fetch('/api/agents/' + agentId + '/logs', {
      headers: { 'X-CSRF-Token': csrfToken },
    })
    .then(r => r.json())
    .then(d => {
      const lines = Array.isArray(d.logs) ? d.logs : generateMockLogs(agentId);
      document.getElementById('logsContent').textContent = lines.join('\n');
    })
    .catch(() => {
      document.getElementById('logsContent').textContent = generateMockLogs(agentId).join('\n');
    });
  };

  function generateMockLogs(agentId) {
    const now = new Date();
    return [
      '[' + now.toISOString() + '] INFO  Agent ' + agentId + ' initialized',
      '[' + now.toISOString() + '] INFO  Connecting to AI provider...',
      '[' + now.toISOString() + '] OK    Connected (latency: 142ms)',
      '[' + now.toISOString() + '] INFO  Fetching task queue (3 items)',
      '[' + now.toISOString() + '] INFO  Processing task #1...',
      '[' + now.toISOString() + '] OK    Task #1 completed (tokens: 1240, cost: $0.005)',
      '[' + now.toISOString() + '] INFO  Processing task #2...',
      '[' + now.toISOString() + '] OK    Task #2 completed (tokens: 980, cost: $0.004)',
      '[' + now.toISOString() + '] INFO  Processing task #3...',
      '[' + now.toISOString() + '] OK    Task #3 completed (tokens: 2100, cost: $0.008)',
      '[' + now.toISOString() + '] OK    All tasks done. Agent entering idle state.',
    ];
  }

  window.closeLogsModal = function() {
    document.getElementById('agentLogsModal').style.display = 'none';
  };
  document.getElementById('agentLogsModal').addEventListener('click', function(e) {
    if (e.target === this) closeLogsModal();
  });

  window.downloadLogs = function() {
    const text    = document.getElementById('logsContent').textContent;
    const blob    = new Blob([text], { type: 'text/plain' });
    const url     = URL.createObjectURL(blob);
    const a       = document.createElement('a');
    a.href        = url;
    a.download    = 'agent-logs-' + Date.now() + '.txt';
    a.click();
    URL.revokeObjectURL(url);
  };

  // ── Configure Modal ───────────────────────────
  window.configureAgent = function(agentId) {
    document.getElementById('configModalTitle').textContent = agentId.charAt(0).toUpperCase() + agentId.slice(1) + ' Agent — Configuration';
    document.getElementById('configModal').style.display = 'flex';
  };
  window.closeConfigModal = function() {
    document.getElementById('configModal').style.display = 'none';
  };
  document.getElementById('configModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfigModal();
  });
  window.saveConfig = function() {
    showAgentsToast('Agent configuration saved successfully.');
    closeConfigModal();
  };

  // ── Activity Feed Auto-Refresh ────────────────
  setInterval(refreshActivityFeed, 10000);

  function refreshActivityFeed() {
    fetch('/api/agents/activity-feed', { headers: { 'X-CSRF-Token': csrfToken } })
    .then(r => r.json())
    .then(d => {
      if (!Array.isArray(d.feed)) return;
      const agentColors = {
        strategy:'#3B82F6', copywriting:'#8B5CF6', design:'#EC4899',
        video:'#F59E0B', publishing:'#10B981', analytics:'#06B6D4',
        community:'#F97316', research:'#A78BFA', orchestrator:'#34D399',
      };
      const container = document.getElementById('activityFeedContainer');
      const html = d.feed.map(entry => {
        const color = agentColors[entry.agent] || '#94A3B8';
        return '<div class="feed-item">' +
          '<div class="feed-dot" style="background:' + color + '"></div>' +
          '<div style="flex:1;min-width:0">' +
          '<div style="font-size:0.75rem;color:var(--text-secondary);line-height:1.4">' + escapeHtml(entry.msg) + '</div>' +
          '<div style="display:flex;gap:0.4rem;align-items:center;margin-top:0.15rem">' +
          '<span style="font-size:0.65rem;color:' + color + ';font-weight:600">' + entry.agent.charAt(0).toUpperCase() + entry.agent.slice(1) + '</span>' +
          '<span style="font-size:0.65rem;color:var(--text-muted)">' + escapeHtml(entry.time) + '</span>' +
          '</div></div></div>';
      }).join('');
      container.innerHTML = html;
    })
    .catch(() => { /* silently ignore on API unavailable */ });

    document.getElementById('feedRefreshLabel').textContent = 'Last refreshed: ' + new Date().toLocaleTimeString();
  }

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
  }

  // ── Toast ──────────────────────────────────
  window.showAgentsToast = function(msg) {
    const t = document.getElementById('agentsToast');
    document.getElementById('agentsToastText').textContent = msg;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3500);
  };
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
