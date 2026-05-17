<?php
/**
 * Reusable Agent Status Card Component
 *
 * @param string $name    Agent name (e.g. "Content Writer")
 * @param string $role    Agent role description
 * @param string $status  One of: running, idle, stopped, error
 * @param string $icon    Emoji icon
 * @param array  $stats   Array of ['label'=>string, 'value'=>string] stats
 * @param string $lastAction  Last action description
 * @param bool   $selected    Whether card is selected/highlighted
 */

$name       = $name       ?? 'AI Agent';
$role       = $role       ?? 'Autonomous AI assistant';
$status     = $status     ?? 'idle';
$icon       = $icon       ?? '🤖';
$stats      = $stats      ?? [];
$lastAction = $lastAction ?? null;
$selected   = $selected   ?? false;
$onClick    = $onClick    ?? null;

$statusColors = [
  'running' => ['class' => 'status-running', 'label' => 'Running',  'color' => 'var(--green-light)'],
  'idle'    => ['class' => 'status-idle',    'label' => 'Idle',     'color' => 'var(--yellow)'],
  'stopped' => ['class' => 'status-stopped', 'label' => 'Stopped',  'color' => 'var(--text-muted)'],
  'error'   => ['class' => 'status-error',   'label' => 'Error',    'color' => 'var(--red)'],
];
$sc = $statusColors[$status] ?? $statusColors['idle'];
?>
<div class="agent-card <?= $selected ? 'selected' : '' ?>" <?= $onClick ? "onclick=\"" . htmlspecialchars($onClick) . "\"" : '' ?>>
  <div class="agent-card-header">
    <span class="agent-emoji"><?= $icon ?></span>
    <div class="agent-info" style="flex:1;min-width:0">
      <h4><?= htmlspecialchars($name) ?></h4>
      <p><?= htmlspecialchars($role) ?></p>
    </div>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem">
    <span style="font-size:0.75rem;display:flex;align-items:center;gap:4px;color:<?= $sc['color'] ?>">
      <span class="status-dot <?= $sc['class'] ?>"></span>
      <?= $sc['label'] ?>
    </span>
    <?php if ($status === 'running'): ?>
    <span style="font-size:0.68rem;color:var(--text-muted);background:rgba(16,185,129,0.1);padding:2px 6px;border-radius:99px">● Live</span>
    <?php elseif ($status === 'error'): ?>
    <span style="font-size:0.68rem;color:var(--red);background:rgba(239,68,68,0.1);padding:2px 6px;border-radius:99px">⚠ Error</span>
    <?php endif ?>
  </div>

  <?php if (!empty($stats)): ?>
  <div class="agent-stats">
    <?php foreach ($stats as $stat): ?>
    <div class="agent-stat" style="flex:1">
      <div class="agent-stat-value"><?= htmlspecialchars($stat['value']) ?></div>
      <div class="agent-stat-label"><?= htmlspecialchars($stat['label']) ?></div>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <?php if ($lastAction): ?>
  <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.75rem;border-top:1px solid rgba(255,255,255,0.05);padding-top:0.5rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
    <?= htmlspecialchars($lastAction) ?>
  </div>
  <?php endif ?>
</div>
