<?php
$pageTitle  = 'AI Agents';
$activePage = 'agents';
$agents = [
  ['name'=>'Content Strategist', 'role'=>'Plans & schedules your entire content calendar',  'icon'=>'🧠','status'=>'running','tasks'=>247,'rate'=>'98.2%','desc'=>'Analyzes your brand strategy and generates a 30-day content calendar automatically. Optimizes posting times and content mix.','lastAction'=>'Generated 7-day calendar · 5 min ago'],
  ['name'=>'Content Writer',     'role'=>'Generates captions, articles & copy for all platforms','icon'=>'✍️','status'=>'running','tasks'=>1842,'rate'=>'99.1%','desc'=>'Creates platform-optimized content in your brand voice. Supports 11 platforms, 11 content types, Arabic & English.','lastAction'=>'Wrote 14 captions for Instagram · 2 min ago'],
  ['name'=>'Community Manager',  'role'=>'Replies to comments & DMs with context-aware AI',  'icon'=>'💬','status'=>'running','tasks'=>3291,'rate'=>'97.8%','desc'=>'Monitors and responds to all comments and DMs across platforms. Escalates complex queries to your team automatically.','lastAction'=>'Replied to 23 comments on LinkedIn · 1 min ago'],
  ['name'=>'Trend Hunter',       'role'=>'Scans viral trends across all platforms 24/7',     'icon'=>'🔥','status'=>'running','tasks'=>156,'rate'=>'100%','desc'=>'Monitors trending topics, hashtags, and sounds. Generates content angles for each detected trend in real-time.','lastAction'=>'Found 3 new viral trends · 8 min ago'],
  ['name'=>'Analytics Agent',    'role'=>'Tracks KPIs and generates AI performance reports', 'icon'=>'📊','status'=>'running','tasks'=>89,'rate'=>'99.5%','desc'=>'Monitors all performance metrics, generates weekly reports, and provides actionable AI recommendations.','lastAction'=>'Generated weekly report · 1 hr ago'],
  ['name'=>'Ad Optimizer',       'role'=>'Manages paid campaigns and optimizes ad spend',    'icon'=>'🎯','status'=>'idle',   'tasks'=>34,'rate'=>'96.3%','desc'=>'Creates and optimizes Meta and Google Ads campaigns. A/B tests creatives and automatically reallocates budget to top performers.','lastAction'=>'Paused low-performing ad · 3 hr ago'],
  ['name'=>'Visual Creator',     'role'=>'Suggests creatives, templates and visual formats', 'icon'=>'🎨','status'=>'idle',   'tasks'=>67,'rate'=>'94.7%','desc'=>'Recommends visual content formats, color schemes, and layouts based on platform best practices and brand guidelines.','lastAction'=>'Suggested 5 reel templates · 2 hr ago'],
  ['name'=>'Hashtag Researcher', 'role'=>'Finds optimal hashtags for maximum reach',         'icon'=>'#️⃣','status'=>'running','tasks'=>512,'rate'=>'99.8%','desc'=>'Researches and curates optimal hashtag sets for each post and platform. Updates strategies based on real-time performance data.','lastAction'=>'Updated LinkedIn hashtag set · 12 min ago'],
];
$workflows = [
  ['name'=>'Full Content Pipeline','agents'=>['🧠','✍️','🎨'],'desc'=>'Strategy → Write → Visual','status'=>'running','runs'=>'Daily 8 AM'],
  ['name'=>'Community AutoPilot', 'agents'=>['💬','📊'],'desc'=>'Reply → Analyze → Report','status'=>'running','runs'=>'Every 15 min'],
  ['name'=>'Trend to Content',    'agents'=>['🔥','✍️','📅'],'desc'=>'Detect → Create → Schedule','status'=>'running','runs'=>'Every 2 hrs'],
  ['name'=>'Performance Report',  'agents'=>['📊','🧠'],'desc'=>'Analyze → Strategize','status'=>'idle','runs'=>'Weekly Mon 9 AM'],
];
?>
<?php ob_start() ?>
<div class="page-header page-header-row">
  <div>
    <h1>AI Agent Command Center 🤖</h1>
    <p>8 specialized AI agents working around the clock to grow your brand</p>
  </div>
  <div style="display:flex;gap:0.75rem;align-items:center">
    <div style="display:flex;align-items:center;gap:0.4rem;font-size:0.82rem;color:var(--green-light);padding:0.4rem 0.85rem;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);border-radius:99px">
      <span class="status-dot status-running"></span> 6 Active · 2 Idle
    </div>
    <button class="btn btn-ghost" onclick="SociAI.showToast('Viewing task history...','info')">📋 Task Log</button>
    <button class="btn btn-primary run-all-agents">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      Run All Agents
    </button>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem">
  <!-- Agent Cards Grid -->
  <div>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;margin-bottom:1.5rem">
      <?php foreach ($agents as $i => $ag): ?>
      <div class="agent-card <?= $i===0?'selected':'' ?>" data-agent-id="<?= $i ?>" onclick="selectAgent(<?= $i ?>)">
        <div class="agent-card-header">
          <span class="agent-emoji"><?= $ag['icon'] ?></span>
          <div class="agent-info">
            <h4><?= htmlspecialchars($ag['name']) ?></h4>
            <p><?= htmlspecialchars($ag['role']) ?></p>
          </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem">
          <span style="font-size:0.75rem;display:flex;align-items:center;gap:4px;color:<?= $ag['status']==='running'?'var(--green-light)':'var(--yellow)' ?>">
            <span class="status-dot status-<?= $ag['status'] ?>"></span>
            <?= ucfirst($ag['status']) ?>
          </span>
          <button class="btn btn-ghost btn-sm" onclick="event.stopPropagation();toggleAgent(<?= $i ?>)" id="toggleBtn<?= $i ?>">
            <?= $ag['status']==='running' ? 'Pause' : 'Start' ?>
          </button>
        </div>
        <div class="agent-stats">
          <div class="agent-stat">
            <div class="agent-stat-value agent-task-count"><?= number_format($ag['tasks']) ?></div>
            <div class="agent-stat-label">Tasks Done</div>
          </div>
          <div class="agent-stat">
            <div class="agent-stat-value"><?= $ag['rate'] ?></div>
            <div class="agent-stat-label">Success Rate</div>
          </div>
        </div>
        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.5rem;border-top:1px solid rgba(255,255,255,0.05);padding-top:0.5rem"><?= htmlspecialchars($ag['lastAction']) ?></div>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Multi-Agent Workflows -->
    <div class="glass-card">
      <div class="section-header">
        <h3>⚙️ Multi-Agent Workflows</h3>
        <button class="btn btn-ghost btn-sm" onclick="SociAI.openModal('newWorkflowModal')">+ New Workflow</button>
      </div>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.75rem">
        <?php foreach ($workflows as $wf): ?>
        <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:1rem;transition:all 0.2s" onmouseover="this.style.borderColor='var(--glass-border-hover)'" onmouseout="this.style.borderColor='var(--glass-border)'">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem">
            <span style="font-size:0.875rem;font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($wf['name']) ?></span>
            <span class="badge <?= $wf['status']==='running'?'badge-success':'badge-warning' ?> badge-dot"><?= ucfirst($wf['status']) ?></span>
          </div>
          <div style="display:flex;gap:0.3rem;margin-bottom:0.5rem">
            <?php foreach ($wf['agents'] as $a): ?>
            <span style="font-size:1.1rem"><?= $a ?></span>
            <?php endforeach ?>
            <span style="font-size:0.75rem;color:var(--text-muted);align-self:center;margin-left:0.25rem"><?= htmlspecialchars($wf['desc']) ?></span>
          </div>
          <div style="font-size:0.72rem;color:var(--text-muted)"><?= htmlspecialchars($wf['runs']) ?></div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>

  <!-- Agent Detail Panel -->
  <div style="display:flex;flex-direction:column;gap:1.25rem">
    <div class="glass-card agent-detail-panel" id="agentDetailPanel">
      <div style="text-align:center;padding:0.5rem 0 1rem">
        <div class="agent-emoji" style="font-size:3rem" id="detailEmoji">🧠</div>
        <h3 id="agentDetailName" style="margin-top:0.5rem">Content Strategist</h3>
        <p id="agentDetailRole" style="font-size:0.82rem;color:var(--text-muted)">Plans & schedules your entire content calendar</p>
        <span class="badge badge-success badge-dot" id="agentDetailStatus">Running</span>
      </div>
      <hr class="divider">
      <div id="agentDetailDesc" style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:1rem;line-height:1.6">
        Analyzes your brand strategy and generates a 30-day content calendar automatically. Optimizes posting times and content mix.
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem">
        <div style="text-align:center;padding:0.875rem;background:var(--glass-bg);border-radius:var(--radius-md)">
          <div style="font-size:1.5rem;font-weight:700;color:var(--blue-light)" id="detailTasks">247</div>
          <div style="font-size:0.72rem;color:var(--text-muted)">Tasks Completed</div>
        </div>
        <div style="text-align:center;padding:0.875rem;background:var(--glass-bg);border-radius:var(--radius-md)">
          <div style="font-size:1.5rem;font-weight:700;color:var(--green-light)" id="detailRate">98.2%</div>
          <div style="font-size:0.72rem;color:var(--text-muted)">Success Rate</div>
        </div>
      </div>
      <div style="display:flex;gap:0.5rem;flex-direction:column">
        <button class="btn btn-ghost btn-block" onclick="SociAI.showToast('Configuring agent...','info')">⚙️ Configure Agent</button>
        <button class="btn btn-ghost btn-block" onclick="SociAI.showToast('Viewing full task log...','info')">📋 View Task Log</button>
        <button class="btn btn-danger btn-block" onclick="SociAI.showToast('Agent paused','warning')">⏸️ Pause Agent</button>
      </div>
    </div>

    <!-- Task History Log -->
    <div class="glass-card">
      <div class="section-header"><h3>📋 Recent Tasks</h3></div>
      <?php
      $tasks = [
        ['icon'=>'🧠','text'=>'Generated 7-day content calendar','time'=>'5m ago','status'=>'success'],
        ['icon'=>'✍️','text'=>'Wrote 14 Instagram captions','time'=>'2m ago','status'=>'success'],
        ['icon'=>'💬','text'=>'Replied to 23 LinkedIn comments','time'=>'1m ago','status'=>'success'],
        ['icon'=>'🔥','text'=>'Detected 3 viral trends','time'=>'8m ago','status'=>'success'],
        ['icon'=>'🎯','text'=>'Paused underperforming ad set','time'=>'3h ago','status'=>'warning'],
        ['icon'=>'📊','text'=>'Generated weekly analytics report','time'=>'1h ago','status'=>'success'],
      ];
      ?>
      <?php foreach ($tasks as $t): ?>
      <div style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.8rem">
        <span><?= $t['icon'] ?></span>
        <span style="flex:1;color:var(--text-secondary)"><?= htmlspecialchars($t['text']) ?></span>
        <span style="color:var(--text-muted);font-size:0.72rem;white-space:nowrap"><?= $t['time'] ?></span>
        <span style="width:6px;height:6px;border-radius:50%;background:<?= $t['status']==='success'?'var(--green)':'var(--yellow)' ?>;flex-shrink:0"></span>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</div>

<script>
const agentsData = <?= json_encode($agents) ?>;
function selectAgent(id) {
  document.querySelectorAll('.agent-card').forEach(c => c.classList.remove('selected'));
  document.querySelectorAll('.agent-card')[id]?.classList.add('selected');
  const ag = agentsData[id];
  if (!ag) return;
  document.getElementById('detailEmoji').textContent = ag.icon;
  document.getElementById('agentDetailName').textContent = ag.name;
  document.getElementById('agentDetailRole').textContent = ag.role;
  document.getElementById('agentDetailDesc').textContent = ag.desc;
  document.getElementById('detailTasks').textContent = ag.tasks.toLocaleString();
  document.getElementById('detailRate').textContent = ag.rate;
  const statusEl = document.getElementById('agentDetailStatus');
  statusEl.textContent = ag.status.charAt(0).toUpperCase() + ag.status.slice(1);
  statusEl.className = `badge ${ag.status === 'running' ? 'badge-success' : 'badge-warning'} badge-dot`;
}
function toggleAgent(id) {
  const ag = agentsData[id];
  if (!ag) return;
  ag.status = ag.status === 'running' ? 'idle' : 'running';
  const btn = document.getElementById('toggleBtn' + id);
  if (btn) btn.textContent = ag.status === 'running' ? 'Pause' : 'Start';
  const dot = document.querySelectorAll('.agent-card')[id]?.querySelector('.status-dot');
  if (dot) {
    dot.className = `status-dot status-${ag.status}`;
    dot.nextSibling.textContent = ' ' + ag.status.charAt(0).toUpperCase() + ag.status.slice(1);
  }
  SociAI.showToast(`${ag.name} ${ag.status === 'running' ? 'started' : 'paused'}`, ag.status === 'running' ? 'success' : 'warning');
  if (document.querySelectorAll('.agent-card.selected')[0]?.dataset.agentId == id) selectAgent(id);
}
</script>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
