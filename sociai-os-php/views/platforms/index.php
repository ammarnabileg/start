<?php
$pageTitle  = 'Platform Connections';
$activePage = 'platforms';
ob_start();
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Platform Connections</h1>
    <p class="page-subtitle">Manage your social media platform connections</p>
  </div>
  <a href="/dashboard/platforms/refresh-all" class="btn btn-ghost">Refresh All</a>
</div>

<?php
$allPlatforms = [
  ['id'=>'linkedin','name'=>'LinkedIn','color'=>'#0A66C2','desc'=>'Professional networking','icon'=>'in'],
  ['id'=>'instagram','name'=>'Instagram','color'=>'#E1306C','desc'=>'Photo & video sharing','icon'=>'IG'],
  ['id'=>'facebook','name'=>'Facebook','color'=>'#1877F2','desc'=>'Social networking','icon'=>'f'],
  ['id'=>'tiktok','name'=>'TikTok','color'=>'#010101','desc'=>'Short-form video','icon'=>'TT'],
  ['id'=>'twitter','name'=>'X / Twitter','color'=>'#1DA1F2','desc'=>'Microblogging','icon'=>'𝕏'],
  ['id'=>'youtube','name'=>'YouTube','color'=>'#FF0000','desc'=>'Video platform','icon'=>'▶'],
  ['id'=>'snapchat','name'=>'Snapchat','color'=>'#FFFC00','desc'=>'Ephemeral content','icon'=>'👻'],
  ['id'=>'threads','name'=>'Threads','color'=>'#000000','desc'=>'Text-based sharing','icon'=>'@'],
  ['id'=>'pinterest','name'=>'Pinterest','color'=>'#E60023','desc'=>'Visual discovery','icon'=>'P'],
  ['id'=>'whatsapp','name'=>'WhatsApp Business','color'=>'#25D366','desc'=>'Business messaging','icon'=>'W'],
  ['id'=>'telegram','name'=>'Telegram','color'=>'#2CA5E0','desc'=>'Messaging & channels','icon'=>'✈'],
];
$accounts = $platformAccounts ?? [];
$connected = array_column($accounts, null, 'platform');
?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;margin-top:1.5rem">
<?php foreach ($allPlatforms as $p):
  $acct = $connected[$p['id']] ?? null;
  $isConnected = !empty($acct) && ($acct['status'] ?? '') !== 'expired';
  $isExpired = !empty($acct) && ($acct['status'] ?? '') === 'expired';
?>
<div class="glass-card" style="padding:1.25rem;border-color:<?= $isConnected ? 'rgba(16,185,129,0.3)' : ($isExpired ? 'rgba(239,68,68,0.3)' : 'var(--glass-border)') ?>">
  <div style="display:flex;align-items:center;gap:0.875rem;margin-bottom:1rem">
    <div style="width:44px;height:44px;border-radius:10px;background:<?= $p['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:700;color:<?= $p['id']==='snapchat'?'#000':'#fff' ?>;flex-shrink:0">
      <?= $p['icon'] ?>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-weight:600;font-size:0.95rem"><?= htmlspecialchars($p['name']) ?></div>
      <div style="font-size:0.75rem;color:var(--text-muted)"><?= $p['desc'] ?></div>
    </div>
    <?php if ($isConnected): ?>
    <span class="badge badge-success" style="flex-shrink:0">Connected</span>
    <?php elseif ($isExpired): ?>
    <span class="badge badge-error" style="flex-shrink:0">Expired</span>
    <?php else: ?>
    <span class="badge" style="background:rgba(255,255,255,0.05);color:var(--text-muted);flex-shrink:0">—</span>
    <?php endif; ?>
  </div>

  <?php if ($acct): ?>
  <div style="background:var(--glass-bg);border-radius:var(--radius-sm);padding:0.75rem;margin-bottom:1rem;font-size:0.8rem">
    <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
      <span style="color:var(--text-muted)">Handle</span>
      <span style="font-weight:500">@<?= htmlspecialchars($acct['handle'] ?? 'unknown') ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem">
      <span style="color:var(--text-muted)">Followers</span>
      <span style="font-weight:500"><?= number_format((int)($acct['follower_count'] ?? 0)) ?></span>
    </div>
    <div style="display:flex;justify-content:space-between">
      <span style="color:var(--text-muted)">Connected</span>
      <span><?= isset($acct['connected_at']) ? date('M j, Y', strtotime($acct['connected_at'])) : '—' ?></span>
    </div>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:0.5rem">
    <?php if ($isConnected): ?>
    <a href="/dashboard/platforms/test/<?= $p['id'] ?>" class="btn btn-ghost" style="flex:1;text-align:center;font-size:0.8rem;padding:0.4rem">Test</a>
    <a href="/auth/platform/disconnect/<?= $p['id'] ?>" class="btn btn-ghost" style="flex:1;text-align:center;font-size:0.8rem;padding:0.4rem;color:var(--red)" onclick="return confirm('Disconnect <?= $p['name'] ?>?')">Disconnect</a>
    <?php elseif ($isExpired): ?>
    <a href="/auth/platform/connect/<?= $p['id'] ?>" class="btn btn-primary" style="flex:1;text-align:center;font-size:0.85rem">Reconnect</a>
    <?php else: ?>
    <a href="/auth/platform/connect/<?= $p['id'] ?>" class="btn btn-primary" style="flex:1;text-align:center;font-size:0.85rem">Connect</a>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<div class="glass-card" style="padding:1.25rem;margin-top:1.5rem">
  <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:1rem">Connection Summary</h3>
  <?php
  $totalConnected = count(array_filter($allPlatforms, fn($p) => isset($connected[$p['id']]) && ($connected[$p['id']]['status'] ?? '') !== 'expired'));
  $totalExpired = count(array_filter($allPlatforms, fn($p) => isset($connected[$p['id']]) && ($connected[$p['id']]['status'] ?? '') === 'expired'));
  ?>
  <div style="display:flex;gap:2rem">
    <div><span style="font-size:1.5rem;font-weight:700;color:var(--green)"><?= $totalConnected ?></span><span style="font-size:0.8rem;color:var(--text-muted);display:block">Connected</span></div>
    <div><span style="font-size:1.5rem;font-weight:700;color:var(--red)"><?= $totalExpired ?></span><span style="font-size:0.8rem;color:var(--text-muted);display:block">Expired</span></div>
    <div><span style="font-size:1.5rem;font-weight:700;color:var(--text-muted)"><?= 11 - $totalConnected - $totalExpired ?></span><span style="font-size:0.8rem;color:var(--text-muted);display:block">Not Connected</span></div>
  </div>
</div>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
