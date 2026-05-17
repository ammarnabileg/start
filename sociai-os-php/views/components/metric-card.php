<?php
/**
 * Reusable Metric Card Component
 *
 * @param string $label     The metric label/title
 * @param string $value     The metric value (e.g. "4.7M", "8.3%")
 * @param string $change    Change indicator (e.g. "+18.4%")
 * @param bool   $up        True = positive trend, False = negative trend
 * @param string $icon      Emoji or HTML icon
 * @param string $color     One of: blue, purple, green, yellow, pink, cyan, orange
 * @param string $subtitle  Optional subtitle/description
 * @param string $footer    Optional footer text
 */

$label    = $label    ?? 'Metric';
$value    = $value    ?? '—';
$change   = $change   ?? null;
$up       = $up       ?? true;
$icon     = $icon     ?? '📊';
$color    = $color    ?? 'blue';
$subtitle = $subtitle ?? null;
$footer   = $footer   ?? null;

$iconColorMap = [
  'blue'   => 'metric-icon-blue',
  'purple' => 'metric-icon-purple',
  'green'  => 'metric-icon-green',
  'yellow' => 'metric-icon-yellow',
  'pink'   => 'metric-icon-pink',
  'cyan'   => 'metric-icon-cyan',
  'orange' => 'metric-icon-orange',
];
$iconClass = $iconColorMap[$color] ?? 'metric-icon-blue';
?>
<div class="metric-card">
  <div class="metric-header">
    <div style="flex:1;min-width:0">
      <div class="metric-label"><?= htmlspecialchars($label) ?></div>
      <div class="metric-value"><?= htmlspecialchars($value) ?></div>
      <?php if ($subtitle): ?>
      <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.15rem"><?= htmlspecialchars($subtitle) ?></div>
      <?php endif ?>
    </div>
    <div class="metric-icon <?= $iconClass ?>"><?= $icon ?></div>
  </div>
  <?php if ($change !== null): ?>
  <div class="metric-change <?= $up ? 'trend-up' : 'trend-down' ?>">
    <?= $up ? '↑' : '↓' ?> <?= htmlspecialchars($change) ?>
    <?php if ($footer): ?>
    <span style="font-weight:400;opacity:0.7"> <?= htmlspecialchars($footer) ?></span>
    <?php else: ?>
    <span style="font-weight:400;opacity:0.7"> vs last period</span>
    <?php endif ?>
  </div>
  <?php endif ?>
</div>
