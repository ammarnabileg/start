<?php
declare(strict_types=1);
use SociAI\Core\{Auth, Database};

$pageTitle  = 'Settings';
$activePage = 'settings';

// Load real platform accounts from DB
$_db     = Database::getInstance();
$_user   = Auth::getCurrentUser();
$_brandId = $_SESSION['active_brand_id'] ?? '';
if (empty($_brandId)) {
    $row = $_db->fetchOne("SELECT b.id FROM brands b INNER JOIN team_members tm ON tm.brand_id=b.id WHERE tm.user_id=? ORDER BY tm.created_at ASC LIMIT 1", [$_user['id']]);
    $_brandId = $row['id'] ?? '';
    if ($_brandId) $_SESSION['active_brand_id'] = $_brandId;
}

$connectedAccounts = $_db->fetchAll(
    "SELECT id, platform, account_name, account_id, follower_count, avatar_url, token_expires_at, last_synced_at, is_active
     FROM platform_accounts WHERE brand_id = ? ORDER BY platform ASC",
    [$_brandId]
);

// Build lookup by platform
$connectedMap = [];
foreach ($connectedAccounts as $acc) {
    $connectedMap[$acc['platform']] = $acc;
}

// All supported platforms
$supportedPlatforms = [
    'facebook'  => ['name'=>'Facebook',    'emoji'=>'👥', 'oauth'=>'meta'],
    'instagram' => ['name'=>'Instagram',   'emoji'=>'📸', 'oauth'=>'meta'],
    'twitter'   => ['name'=>'Twitter/X',   'emoji'=>'🐦', 'oauth'=>'twitter'],
    'linkedin'  => ['name'=>'LinkedIn',    'emoji'=>'💼', 'oauth'=>'linkedin'],
    'tiktok'    => ['name'=>'TikTok',      'emoji'=>'🎵', 'oauth'=>'tiktok'],
    'youtube'   => ['name'=>'YouTube',     'emoji'=>'▶️', 'oauth'=>'youtube'],
];

// Legacy static fallback for display of non-supported platforms
$legacyPlatforms = [
  ['name'=>'Snapchat',  'emoji'=>'👻','status'=>'not_connected','account'=>'—','followers'=>'—'],
  ['name'=>'Threads',   'emoji'=>'🧵','status'=>'not_connected','account'=>'—','followers'=>'—'],
  ['name'=>'Pinterest', 'emoji'=>'📌','status'=>'not_connected','account'=>'—','followers'=>'—'],
];
$apiKeys = [
  ['name'=>'OpenAI API Key',      'key'=>'sk-proj-••••••••••••••••••••••••••••••xK8A'],
  ['name'=>'Meta Business Token', 'key'=>'EAAx••••••••••••••••••••••••••••••••••••'],
  ['name'=>'YouTube Data API',    'key'=>'AIza••••••••••••••••••••••••••••••••••••'],
  ['name'=>'LinkedIn API Secret', 'key'=>'••••••••••••••••••••••••••••••••••••••••'],
];
?>
<?php ob_start() ?>
<div class="page-header">
  <h1>Settings ⚙️</h1>
  <p>Manage your account, security, platforms and integrations</p>
</div>

<div class="settings-tabs">
  <button class="settings-tab active" data-panel="accountPanel">👤 Account</button>
  <button class="settings-tab" data-panel="securityPanel">🔒 Security</button>
  <button class="settings-tab" data-panel="platformsPanel">🔗 Platforms</button>
  <button class="settings-tab" data-panel="apiPanel">🔑 API Keys</button>
  <button class="settings-tab" data-panel="notifPanel">🔔 Notifications</button>
</div>

<!-- Account Panel -->
<div class="settings-panel" id="accountPanel">
  <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem">
    <div class="glass-card">
      <h3 style="margin-bottom:1.25rem">Profile Information</h3>
      <div style="display:flex;align-items:center;gap:1.25rem;margin-bottom:1.5rem">
        <div style="position:relative">
          <div class="user-avatar" style="width:80px;height:80px;font-size:1.5rem;background:var(--gradient-primary);border-radius:50%">AA</div>
          <button onclick="SociAI.showToast('Upload photo feature...','info')" style="position:absolute;bottom:0;right:0;width:26px;height:26px;background:var(--blue);border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:0.7rem;color:#fff">✏️</button>
        </div>
        <div>
          <div style="font-size:1rem;font-weight:700;margin-bottom:0.25rem">Ahmed Al-Rashid</div>
          <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.5rem">ahmed@brand.com · Owner</div>
          <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Opening file picker...','info')">Change Photo</button>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name</label>
          <input type="text" class="form-input" value="Ahmed">
        </div>
        <div class="form-group">
          <label class="form-label">Last Name</label>
          <input type="text" class="form-input" value="Al-Rashid">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" class="form-input" value="ahmed@brand.com">
      </div>
      <div class="form-group">
        <label class="form-label">Company / Brand Name</label>
        <input type="text" class="form-input" value="Ahmed Brand Inc.">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Language</label>
          <select class="form-select">
            <option selected>English</option>
            <option>Arabic (عربي)</option>
            <option>Mixed (EN + AR)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Timezone</label>
          <select class="form-select">
            <option>UTC+3 (Riyadh)</option>
            <option>UTC+4 (Dubai)</option>
            <option>UTC+0 (London)</option>
            <option>UTC-5 (New York)</option>
          </select>
        </div>
      </div>
      <button class="btn btn-primary" onclick="SociAI.showToast('Profile saved!','success')">💾 Save Changes</button>
    </div>

    <div style="display:flex;flex-direction:column;gap:1rem">
      <div class="glass-card">
        <h3 style="margin-bottom:1rem">📊 Account Stats</h3>
        <?php foreach([['Plan','Enterprise Pro','badge-purple'],['Billing','Annual · $299/mo','badge-info'],['Posts Published','1,248','badge-success'],['AI Tokens Used','84,200 / 100K','badge-warning'],['Team Members','6 / 20','badge-neutral']] as [$l,$v,$b]): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem">
          <span style="color:var(--text-muted)"><?= $l ?></span>
          <span class="badge <?= $b ?>" style="font-size:0.72rem"><?= $v ?></span>
        </div>
        <?php endforeach ?>
        <button class="btn btn-ghost btn-block btn-sm" style="margin-top:0.75rem" onclick="SociAI.showToast('Opening billing portal...','info')">💳 Manage Subscription</button>
      </div>

      <div class="glass-card" style="background:rgba(239,68,68,0.05);border-color:rgba(239,68,68,0.2)">
        <h3 style="margin-bottom:0.5rem;color:#FCA5A5">⚠️ Danger Zone</h3>
        <p style="font-size:0.82rem;margin-bottom:1rem">These actions are irreversible. Please proceed with caution.</p>
        <button class="btn btn-danger btn-block btn-sm" onclick="SociAI.showToast('Are you sure? This will delete all data!','error')">🗑️ Delete Account</button>
      </div>
    </div>
  </div>
</div>

<!-- Security Panel -->
<div class="settings-panel" id="securityPanel" style="display:none">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
    <div class="glass-card">
      <h3 style="margin-bottom:1.25rem">🔑 Change Password</h3>
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input type="password" class="form-input" placeholder="••••••••">
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" class="form-input" id="newPwd" placeholder="Min. 8 characters" data-strength>
        <div class="strength-meter">
          <div class="strength-bars"><div class="strength-bar"></div><div class="strength-bar"></div><div class="strength-bar"></div><div class="strength-bar"></div></div>
          <span class="strength-label">Enter new password</span>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input type="password" class="form-input" placeholder="••••••••">
      </div>
      <button class="btn btn-primary" onclick="SociAI.showToast('Password updated!','success')">🔑 Update Password</button>
    </div>

    <div class="glass-card">
      <h3 style="margin-bottom:1rem">📱 Two-Factor Authentication</h3>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius-md);margin-bottom:1rem">
        <span style="font-size:0.85rem;font-weight:600;color:var(--green-light)">✓ 2FA Enabled</span>
        <button class="btn btn-danger btn-sm" onclick="SociAI.showToast('2FA disabled — not recommended!','warning')">Disable</button>
      </div>
      <p style="font-size:0.85rem;margin-bottom:1rem">Scan this QR code with your authenticator app to set up a new device:</p>
      <div class="qr-placeholder"><div class="qr-pattern"></div></div>
      <p style="font-size:0.75rem;color:var(--text-muted);text-align:center;margin-top:0.5rem">Compatible with Google Authenticator, Authy & 1Password</p>
      <div class="form-group" style="margin-top:1rem">
        <label class="form-label">Verify Code</label>
        <div style="display:flex;gap:0.5rem">
          <input type="text" class="form-input" placeholder="000000" maxlength="6" style="letter-spacing:0.3em;font-size:1.1rem;text-align:center">
          <button class="btn btn-primary" onclick="SociAI.showToast('Device verified!','success')">Verify</button>
        </div>
      </div>
    </div>

    <div class="glass-card">
      <h3 style="margin-bottom:1rem">🔐 Active Sessions</h3>
      <?php
      $sessions = [
        ['device'=>'Chrome on MacBook Pro','location'=>'Riyadh, SA','time'=>'Current session','current'=>true],
        ['device'=>'Safari on iPhone 14',  'location'=>'Riyadh, SA','time'=>'2 hours ago',    'current'=>false],
        ['device'=>'Chrome on Windows',    'location'=>'Dubai, AE', 'time'=>'Yesterday',       'current'=>false],
      ];
      ?>
      <?php foreach ($sessions as $s): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 0;border-bottom:1px solid rgba(255,255,255,0.04)">
        <div>
          <div style="font-size:0.85rem;font-weight:500"><?= htmlspecialchars($s['device']) ?></div>
          <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($s['location']) ?> · <?= htmlspecialchars($s['time']) ?></div>
        </div>
        <?php if ($s['current']): ?>
        <span class="badge badge-success">Current</span>
        <?php else: ?>
        <button class="btn btn-danger btn-sm" onclick="SociAI.showToast('Session revoked','error')">Revoke</button>
        <?php endif ?>
      </div>
      <?php endforeach ?>
      <button class="btn btn-danger btn-sm" style="margin-top:0.75rem" onclick="SociAI.showToast('All other sessions revoked','warning')">Revoke All Other Sessions</button>
    </div>

    <div class="glass-card">
      <h3 style="margin-bottom:1rem">📜 Login History</h3>
      <?php foreach([['Successful login','Riyadh, SA','2m ago'],['Successful login','Riyadh, SA','Yesterday 8:15 AM'],['Failed attempt (wrong pwd)','Unknown, IR','2 days ago'],['Successful login','Dubai, AE','3 days ago']] as [$e,$l,$t]): ?>
      <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.8rem">
        <span style="color:<?= str_contains($e,'Failed')?'#FCA5A5':'var(--text-secondary)' ?>"><?= htmlspecialchars($e) ?></span>
        <div style="text-align:right">
          <div style="color:var(--text-muted)"><?= htmlspecialchars($l) ?></div>
          <div style="color:var(--text-muted);font-size:0.72rem"><?= htmlspecialchars($t) ?></div>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
</div>

<!-- Platforms Panel -->
<div class="settings-panel" id="platformsPanel" style="display:none">
  <div style="margin-bottom:1rem;padding:0.75rem 1rem;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);border-radius:var(--radius-sm);font-size:0.82rem;color:var(--blue-light)">
    🔌 Connect your social media accounts to enable AI-powered community management, content publishing, and analytics.
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:0.875rem">
    <?php foreach ($supportedPlatforms as $key => $pInfo):
      $acc        = $connectedMap[$key] ?? null;
      $isConnected = $acc !== null && ($acc['is_active'] ?? false);
      $isExpired   = $acc !== null && !empty($acc['token_expires_at']) && strtotime($acc['token_expires_at']) < time();
      $status      = $isConnected ? ($isExpired ? 'expired' : 'connected') : 'not_connected';
      $borderColor = $status === 'expired'
        ? 'rgba(245,158,11,0.4)'
        : ($status === 'connected' ? 'rgba(16,185,129,0.3)' : 'var(--glass-border)');
      $oauthRoute  = '/oauth/' . $pInfo['oauth'] . '/connect';
      $followers   = $acc ? number_format((int)($acc['follower_count'] ?? 0)) : null;
      $lastSync    = $acc && $acc['last_synced_at'] ? date('M j, g:i a', strtotime($acc['last_synced_at'])) : null;
    ?>
    <div style="background:var(--glass-bg);border:1px solid <?= $borderColor ?>;border-radius:var(--radius-md);padding:1rem">
      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem">
        <span style="font-size:1.5rem"><?= $pInfo['emoji'] ?></span>
        <div style="flex:1;min-width:0">
          <div style="font-size:0.9rem;font-weight:600"><?= htmlspecialchars($pInfo['name']) ?></div>
          <?php if ($isConnected && !$isExpired): ?>
          <div style="font-size:0.73rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= htmlspecialchars($acc['account_name'] ?? '') ?>
            <?php if ($followers): ?> · <?= $followers ?> followers<?php endif; ?>
          </div>
          <?php if ($lastSync): ?>
          <div style="font-size:0.7rem;color:var(--text-muted)">Last sync: <?= $lastSync ?></div>
          <?php endif; ?>
          <?php elseif ($isExpired): ?>
          <div style="font-size:0.73rem;color:var(--yellow)">⚠️ Token expired — reconnect</div>
          <?php else: ?>
          <div style="font-size:0.73rem;color:var(--text-muted)">Not connected</div>
          <?php endif; ?>
        </div>
        <?php if ($isConnected && !$isExpired): ?>
        <span style="width:8px;height:8px;background:var(--green);border-radius:50%;box-shadow:0 0 6px var(--green);flex-shrink:0"></span>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:0.5rem">
        <?php if ($isConnected && !$isExpired): ?>
        <a href="<?= $oauthRoute ?>" class="btn btn-ghost btn-sm" style="flex:1;text-align:center">🔄 Reconnect</a>
        <button class="btn btn-ghost btn-sm" style="color:var(--red)"
                onclick="disconnectPlatform('<?= htmlspecialchars($acc['id'] ?? '') ?>', '<?= htmlspecialchars($pInfo['name']) ?>')">✕</button>
        <?php elseif ($isExpired): ?>
        <a href="<?= $oauthRoute ?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center">🔄 Reconnect</a>
        <?php else: ?>
        <a href="<?= $oauthRoute ?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center">🔗 Connect</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Legacy / coming soon platforms -->
    <?php foreach ($legacyPlatforms as $p): ?>
    <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:1rem;opacity:.7">
      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem">
        <span style="font-size:1.5rem"><?= $p['emoji'] ?></span>
        <div><div style="font-size:0.9rem;font-weight:600"><?= htmlspecialchars($p['name']) ?></div>
        <div style="font-size:0.73rem;color:var(--text-muted)">Coming soon</div></div>
      </div>
      <button class="btn btn-ghost btn-sm btn-block" disabled>Coming Soon</button>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:1.25rem;padding:.75rem 1rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius-sm);font-size:.8rem">
    ✅ Connected platforms automatically sync interactions every 5 minutes. AI replies are generated every 10 minutes.
  </div>
</div>

<script>
async function disconnectPlatform(accountId, name) {
  if (!confirm('Disconnect ' + name + '? This will stop syncing and remove the stored access token.')) return;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const r = await fetch('/oauth/<?= 'meta' ?>/disconnect', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify({ account_id: accountId }),
  });
  const d = await r.json();
  alert(d.message || (d.success ? 'Disconnected!' : 'Error: ' + (d.error||'Failed')));
  if (d.success) location.reload();
}
</script>

<!-- API Keys Panel -->
<div class="settings-panel" id="apiPanel" style="display:none">
  <div class="glass-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
      <h3>🔑 API Keys & Integrations</h3>
      <button class="btn btn-primary btn-sm" onclick="SociAI.openModal('addApiKeyModal')">+ Add New Key</button>
    </div>
    <?php foreach ($apiKeys as $k): ?>
    <div class="api-key-row">
      <span class="api-key-name"><?= htmlspecialchars($k['name']) ?></span>
      <span class="api-key-val"><?= htmlspecialchars($k['key']) ?></span>
      <button class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText('demo_key').then(()=>SociAI.showToast('Copied!','success'))">📋</button>
      <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Key rotated!','success')">🔄</button>
      <button class="btn btn-danger btn-sm" onclick="SociAI.showToast('Key deleted','error')">🗑️</button>
    </div>
    <?php endforeach ?>
    <div style="margin-top:1rem;padding:0.875rem;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:var(--radius-md);font-size:0.82rem">
      ⚠️ Keep your API keys secure. Never share them publicly or commit them to code repositories.
    </div>
  </div>

  <!-- Webhooks -->
  <div class="glass-card" style="margin-top:1rem">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
      <h3>🔔 Webhooks</h3>
      <button class="btn btn-ghost btn-sm" onclick="SociAI.showToast('Adding webhook...','info')">+ Add Webhook</button>
    </div>
    <?php foreach([['https://yourapp.com/hooks/sociai','All events','active'],['https://zapier.com/hooks/catch/xxx','New post published','active'],['https://n8n.io/webhook/sociai','Lead detected','paused']] as [$url,$evt,$st]): ?>
    <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.8rem">
      <span style="font-family:monospace;flex:1;color:var(--blue-light);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($url) ?></span>
      <span style="color:var(--text-muted)"><?= htmlspecialchars($evt) ?></span>
      <span class="badge <?= $st==='active'?'badge-success':'badge-warning' ?>"><?= $st ?></span>
      <button class="btn btn-ghost btn-sm">Test</button>
    </div>
    <?php endforeach ?>
  </div>
</div>

<!-- Notifications Panel -->
<div class="settings-panel" id="notifPanel" style="display:none">
  <div class="glass-card">
    <h3 style="margin-bottom:1.25rem">🔔 Notification Preferences</h3>
    <?php
    $notifSettings = [
      ['New Comment/Reply',           'Get notified when someone comments on your posts', true],
      ['New DM / Lead',               'Alert when a high-scoring lead DMs your brand',   true],
      ['Viral Trend Detected',        'Notify when AI finds a trending opportunity',      true],
      ['Post Published',              'Confirmation when AI publishes a scheduled post',  true],
      ['Weekly Analytics Report',     'Receive weekly performance summary',               true],
      ['AI Agent Status Changes',     'Notify when agents start, pause or error',         false],
      ['Team Activity',               'Updates from team members\' actions',              false],
      ['Billing & Subscription',      'Reminders before renewal or usage limits',         true],
      ['Platform Token Expiry',       'Alert 7 days before OAuth tokens expire',          true],
      ['Marketing & Product Updates', 'New features, tips and SociAI news',               false],
    ];
    ?>
    <?php foreach ($notifSettings as [$label, $desc, $enabled]): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 0;border-bottom:1px solid rgba(255,255,255,0.04)">
      <div>
        <div style="font-size:0.875rem;font-weight:500;color:var(--text-primary)"><?= htmlspecialchars($label) ?></div>
        <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($desc) ?></div>
      </div>
      <label style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;cursor:pointer">
        <input type="checkbox" <?= $enabled?'checked':'' ?> style="opacity:0;width:0;height:0" onchange="SociAI.showToast('Preference saved','success')">
        <span style="position:absolute;cursor:pointer;inset:0;background:<?= $enabled?'var(--blue)':'rgba(100,116,139,0.3)' ?>;border-radius:99px;transition:0.3s">
          <span style="position:absolute;height:18px;width:18px;left:<?= $enabled?'23px':'3px' ?>;bottom:3px;background:white;border-radius:50%;transition:0.3s"></span>
        </span>
      </label>
    </div>
    <?php endforeach ?>
    <button class="btn btn-primary" style="margin-top:1rem" onclick="SociAI.showToast('Notification preferences saved!','success')">💾 Save Preferences</button>
  </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php' ?>
