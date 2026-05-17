<?php $pageTitle = 'Connect Platforms'; ?>
<?php ob_start() ?>
<div style="width:100%;max-width:800px;position:relative;z-index:1;animation:slideUp 0.5s ease">
  <!-- Header -->
  <div style="text-align:center;margin-bottom:2rem">
    <div class="auth-logo-mark" style="margin:0 auto 1rem">S</div>
    <h1 style="font-size:1.8rem;font-weight:800;margin-bottom:0.5rem">Connect Your Platforms 🔗</h1>
    <p style="color:var(--text-muted);font-size:0.95rem">Connect your social accounts so SociAI OS can manage them with AI autopilot</p>
  </div>

  <!-- Progress -->
  <div class="glass-card" style="margin-bottom:1.5rem;padding:1.25rem">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem">
      <span style="font-size:0.85rem;font-weight:600;color:var(--text-secondary)">
        <span class="connected-count">0</span> of 11 platforms connected
      </span>
      <span style="font-size:0.8rem;color:var(--text-muted)">Connect at least 1 to continue</span>
    </div>
    <div class="progress-bar lg">
      <div class="progress-fill connect-progress-fill" style="width:0%"></div>
    </div>
  </div>

  <!-- Platforms Grid -->
  <?php
  $platforms = [
    ['id'=>'linkedin',  'name'=>'LinkedIn',   'emoji'=>'💼', 'color'=>'#0A66C2', 'desc'=>'Professional network & B2B leads', 'users'=>'950M+'],
    ['id'=>'instagram', 'name'=>'Instagram',  'emoji'=>'📸', 'color'=>'#E1306C', 'desc'=>'Visual content & stories',          'users'=>'2B+'],
    ['id'=>'facebook',  'name'=>'Facebook',   'emoji'=>'👥', 'color'=>'#1877F2', 'desc'=>'Community & paid advertising',      'users'=>'3B+'],
    ['id'=>'tiktok',    'name'=>'TikTok',     'emoji'=>'🎵', 'color'=>'#FE2C55', 'desc'=>'Short-form viral video content',    'users'=>'1.5B+'],
    ['id'=>'twitter',   'name'=>'Twitter/X',  'emoji'=>'🐦', 'color'=>'#1DA1F2', 'desc'=>'Real-time news & conversations',    'users'=>'550M+'],
    ['id'=>'youtube',   'name'=>'YouTube',    'emoji'=>'▶️', 'color'=>'#FF0000', 'desc'=>'Long-form video & shorts',          'users'=>'2.7B+'],
    ['id'=>'snapchat',  'name'=>'Snapchat',   'emoji'=>'👻', 'color'=>'#FFFC00', 'desc'=>'Ephemeral content & Gen-Z',         'users'=>'750M+'],
    ['id'=>'threads',   'name'=>'Threads',    'emoji'=>'🧵', 'color'=>'#000000', 'desc'=>'Text-based social conversations',   'users'=>'200M+'],
    ['id'=>'pinterest', 'name'=>'Pinterest',  'emoji'=>'📌', 'color'=>'#BD081C', 'desc'=>'Visual discovery & inspiration',    'users'=>'480M+'],
    ['id'=>'whatsapp',  'name'=>'WhatsApp',   'emoji'=>'💬', 'color'=>'#25D366', 'desc'=>'Messaging & business channel',      'users'=>'2B+'],
    ['id'=>'telegram',  'name'=>'Telegram',   'emoji'=>'✈️', 'color'=>'#0088CC', 'desc'=>'Channel broadcast & communities',   'users'=>'900M+'],
  ];
  ?>
  <div class="platform-connect-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem">
    <?php foreach ($platforms as $p): ?>
    <div class="platform-connect-card glass-card" data-platform="<?= $p['id'] ?>">
      <div class="platform-logo" style="font-size:2.5rem;margin-bottom:0.5rem"><?= $p['emoji'] ?></div>
      <h4 style="font-size:0.9rem;font-weight:700;margin-bottom:0.2rem"><?= htmlspecialchars($p['name']) ?></h4>
      <p style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.25rem"><?= htmlspecialchars($p['desc']) ?></p>
      <p style="font-size:0.7rem;color:var(--blue-light);margin-bottom:0.75rem;font-weight:600"><?= $p['users'] ?> users</p>
      <button class="btn btn-primary btn-sm btn-block connect-platform-btn">
        Connect
      </button>
    </div>
    <?php endforeach ?>
  </div>

  <!-- CTA Row -->
  <div class="glass-card" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
    <div>
      <div style="font-size:0.85rem;font-weight:600;margin-bottom:0.25rem">Ready to launch?</div>
      <div style="font-size:0.8rem;color:var(--text-muted)">You can always connect more platforms later from Settings</div>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center">
      <a href="/dashboard" style="font-size:0.85rem;color:var(--text-muted)">Skip for now</a>
      <button class="btn btn-primary btn-lg launch-sociai-btn" disabled onclick="window.location.href='/dashboard'">
        🚀 Launch SociAI OS
      </button>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/auth.php' ?>
