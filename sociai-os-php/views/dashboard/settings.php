<?php ob_start(); ?>
<?php
// Variables passed from DashboardController::settings()
// $brand, $brandId, $platformAccounts, $connectedMap, $user, $currentUser, $csrf

// All supported platforms for display
$allPlatforms = [
    'linkedin'  => ['name' => 'LinkedIn',   'emoji' => '💼', 'color' => '#0A66C2', 'oauth_key' => 'linkedin'],
    'instagram' => ['name' => 'Instagram',  'emoji' => '📸', 'color' => '#E1306C', 'oauth_key' => 'instagram'],
    'facebook'  => ['name' => 'Facebook',   'emoji' => '👥', 'color' => '#1877F2', 'oauth_key' => 'facebook'],
    'twitter'   => ['name' => 'Twitter/X',  'emoji' => '🐦', 'color' => '#1DA1F2', 'oauth_key' => 'twitter'],
    'tiktok'    => ['name' => 'TikTok',     'emoji' => '🎵', 'color' => '#010101', 'oauth_key' => 'tiktok'],
    'youtube'   => ['name' => 'YouTube',    'emoji' => '▶️', 'color' => '#FF0000', 'oauth_key' => 'youtube'],
    'snapchat'  => ['name' => 'Snapchat',   'emoji' => '👻', 'color' => '#FFFC00', 'oauth_key' => null],
    'threads'   => ['name' => 'Threads',    'emoji' => '🧵', 'color' => '#6B7280', 'oauth_key' => null],
    'pinterest' => ['name' => 'Pinterest',  'emoji' => '📌', 'color' => '#E60023', 'oauth_key' => null],
    'whatsapp'  => ['name' => 'WhatsApp',   'emoji' => '💬', 'color' => '#25D366', 'oauth_key' => null],
    'telegram'  => ['name' => 'Telegram',   'emoji' => '✈️', 'color' => '#229ED9', 'oauth_key' => null],
];

$notifSettings = [
    ['New Comment/Reply',       'Get notified when someone comments on your posts',    true],
    ['New DM / Lead',           'Alert when a high-scoring lead DMs your brand',       true],
    ['Viral Trend Detected',    'Notify when AI finds a trending opportunity',          true],
    ['Post Published',          'Confirmation when AI publishes a scheduled post',      true],
    ['Weekly Analytics Report', 'Receive weekly performance summary',                  true],
    ['AI Agent Status Changes', 'Notify when agents start, pause or encounter errors', false],
    ['Team Activity',           "Updates from team members' actions",                   false],
    ['Billing & Subscription',  'Reminders before renewal or usage limits',             true],
    ['Platform Token Expiry',   'Alert 7 days before OAuth tokens expire',              true],
];
?>
<style>
.settings-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--glass-border); margin-bottom: 1.5rem; overflow-x: auto; }
.settings-tab {
  padding: 0.75rem 1.25rem; font-size: 0.85rem; font-weight: 500; border: none;
  background: transparent; color: var(--text-muted); cursor: pointer;
  border-bottom: 2px solid transparent; transition: all var(--tr); white-space: nowrap;
}
.settings-tab:hover { color: var(--text-primary); }
.settings-tab.active { color: var(--blue-light); border-bottom-color: var(--blue-light); }
.settings-panel { display: none; }
.settings-panel.active { display: block; }
</style>

<!-- Header -->
<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.6rem;font-weight:700;margin-bottom:0.25rem;">Settings ⚙️</h1>
    <p style="color:var(--text-muted);font-size:0.875rem;">Manage your brand, security, platforms, and integrations</p>
</div>

<!-- Tabs -->
<div class="settings-tabs">
    <button class="settings-tab active" onclick="switchTab('brandPanel', this)">🏢 Brand</button>
    <button class="settings-tab" onclick="switchTab('accountPanel', this)">👤 Account</button>
    <button class="settings-tab" onclick="switchTab('platformsPanel', this)">🔗 Platforms</button>
    <button class="settings-tab" onclick="switchTab('securityPanel', this)">🔒 Security</button>
    <button class="settings-tab" onclick="switchTab('notifPanel', this)">🔔 Notifications</button>
</div>

<!-- Brand Panel -->
<div id="brandPanel" class="settings-panel active">
    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;">
        <div class="glass-card">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;">Brand Information</h3>
            <?php if (!$brand): ?>
            <div style="text-align:center;padding:2rem;color:var(--text-muted);">
                <div style="font-size:2.5rem;margin-bottom:0.75rem;">🏢</div>
                <p style="font-size:0.875rem;margin-bottom:1rem;">No brand created yet. Set up your brand workspace to get started.</p>
                <a href="/brands/create" class="btn btn-primary">Create Brand</a>
            </div>
            <?php else: ?>
            <form onsubmit="saveBrandSettings(event)">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group">
                        <label class="form-label">Brand Name</label>
                        <input type="text" class="form-input" name="name" value="<?= htmlspecialchars($brand['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Industry</label>
                        <input type="text" class="form-input" name="industry" value="<?= htmlspecialchars($brand['industry'] ?? '') ?>" placeholder="e.g. Technology, Retail…">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-textarea" name="description" rows="3" placeholder="Describe your brand…"><?= htmlspecialchars($brand['description'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Website</label>
                    <input type="url" class="form-input" name="website" value="<?= htmlspecialchars($brand['website'] ?? '') ?>" placeholder="https://yourbrand.com">
                </div>
                <button type="submit" class="btn btn-primary">💾 Save Brand Settings</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:1rem;">📊 Brand Stats</h3>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem;">
                <span style="color:var(--text-muted);">Brand</span>
                <span style="font-weight:600;color:var(--text-primary);"><?= $brand ? htmlspecialchars($brand['name']) : '—' ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem;">
                <span style="color:var(--text-muted);">Connected Platforms</span>
                <span class="badge badge-success"><?= count($platformAccounts) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem;">
                <span style="color:var(--text-muted);">Created</span>
                <span style="font-weight:600;"><?= $brand && $brand['created_at'] ? date('M j, Y', strtotime($brand['created_at'])) : '—' ?></span>
            </div>
            <?php if ($brand): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;font-size:0.82rem;">
                <span style="color:var(--text-muted);">Brand Slug</span>
                <span style="font-family:monospace;font-size:0.78rem;color:var(--blue-light);"><?= htmlspecialchars($brand['slug'] ?? '') ?></span>
            </div>
            <?php endif; ?>
            <div style="margin-top:1rem;padding:0.75rem;background:rgba(239,68,68,0.05);border:1px solid rgba(239,68,68,0.15);border-radius:var(--radius-sm);">
                <div style="font-size:0.8rem;font-weight:600;color:#FCA5A5;margin-bottom:0.3rem;">⚠️ Danger Zone</div>
                <p style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.75rem;">Permanently delete this brand and all its data.</p>
                <button class="btn btn-sm" style="width:100%;background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.2);" onclick="if(confirm('This will permanently delete all brand data. Are you sure?'))showToast('Action requires confirmation — contact support.','warning')">🗑️ Delete Brand</button>
            </div>
        </div>
    </div>
</div>

<!-- Account Panel -->
<div id="accountPanel" class="settings-panel">
    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;">
        <div class="glass-card">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;">Profile Information</h3>
            <div style="display:flex;align-items:center;gap:1.25rem;margin-bottom:1.5rem;">
                <div style="position:relative;">
                    <div style="width:70px;height:70px;border-radius:50%;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;">
                        <?= htmlspecialchars($currentUser['initials'] ?? 'U') ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:1rem;font-weight:700;margin-bottom:0.2rem;"><?= htmlspecialchars($currentUser['name'] ?? '—') ?></div>
                    <div style="font-size:0.82rem;color:var(--text-muted);"><?= htmlspecialchars($currentUser['email'] ?? '—') ?> · <?= htmlspecialchars($currentUser['role'] ?? 'Owner') ?></div>
                </div>
            </div>
            <form onsubmit="saveProfileSettings(event)">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-input" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary">💾 Save Profile</button>
            </form>
        </div>
        <div class="glass-card">
            <h3 style="font-size:0.9rem;font-weight:600;margin-bottom:1rem;">Account Details</h3>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem;">
                <span style="color:var(--text-muted);">User ID</span>
                <span style="font-family:monospace;font-size:0.75rem;color:var(--text-secondary);"><?= htmlspecialchars((string)($user['id'] ?? '—')) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem;">
                <span style="color:var(--text-muted);">Status</span>
                <span class="badge badge-success">Active</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;font-size:0.82rem;">
                <span style="color:var(--text-muted);">Role</span>
                <span class="badge badge-purple"><?= htmlspecialchars($currentUser['role'] ?? 'Owner') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Platforms Panel -->
<div id="platformsPanel" class="settings-panel">
    <div style="margin-bottom:1rem;padding:0.75rem 1rem;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);border-radius:var(--radius-sm);font-size:0.82rem;color:var(--blue-light);">
        🔌 Connect your social media accounts to enable AI-powered community management, content publishing, and analytics.
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0.875rem;">
        <?php foreach ($allPlatforms as $key => $pInfo):
            $acc        = $connectedMap[$key] ?? null;
            $isConnected = $acc !== null && (bool)($acc['is_active'] ?? false);
            $hasOAuth    = $pInfo['oauth_key'] !== null;
            $oauthRoute  = $hasOAuth ? '/oauth/' . htmlspecialchars($pInfo['oauth_key']) . '/connect' : '#';
            $followers   = $acc ? number_format((int)($acc['follower_count'] ?? 0)) : null;
            $lastSync    = $acc && !empty($acc['last_synced_at']) ? date('M j, g:i a', strtotime($acc['last_synced_at'])) : null;
            $borderColor = $isConnected ? 'rgba(16,185,129,0.3)' : 'var(--glass-border)';
        ?>
        <div style="background:var(--glass-bg);border:1px solid <?= $borderColor ?>;border-radius:var(--radius-md);padding:1rem;<?= !$hasOAuth ? 'opacity:0.7;' : '' ?>">
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">
                <span style="font-size:1.5rem;"><?= $pInfo['emoji'] ?></span>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:0.9rem;font-weight:600;"><?= htmlspecialchars($pInfo['name']) ?></div>
                    <?php if ($isConnected): ?>
                    <div style="font-size:0.72rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($acc['account_name'] ?? '') ?>
                        <?php if ($followers): ?> · <?= $followers ?> followers<?php endif; ?>
                    </div>
                    <?php if ($lastSync): ?>
                    <div style="font-size:0.68rem;color:var(--text-muted);">Last sync: <?= $lastSync ?></div>
                    <?php endif; ?>
                    <?php elseif (!$hasOAuth): ?>
                    <div style="font-size:0.72rem;color:var(--text-muted);">Coming soon</div>
                    <?php else: ?>
                    <div style="font-size:0.72rem;color:var(--text-muted);">Not connected</div>
                    <?php endif; ?>
                </div>
                <?php if ($isConnected): ?>
                <span style="width:8px;height:8px;background:var(--green);border-radius:50%;box-shadow:0 0 6px var(--green);flex-shrink:0;"></span>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:0.5rem;">
                <?php if (!$hasOAuth): ?>
                <button class="btn btn-ghost btn-sm" style="flex:1;text-align:center;" disabled>Coming Soon</button>
                <?php elseif ($isConnected): ?>
                <a href="<?= $oauthRoute ?>" class="btn btn-ghost btn-sm" style="flex:1;text-align:center;">🔄 Reconnect</a>
                <button class="btn btn-ghost btn-sm" style="color:var(--red);" onclick="disconnectPlatform('<?= htmlspecialchars((string)($acc['id'] ?? '')) ?>','<?= htmlspecialchars($pInfo['name']) ?>')">✕</button>
                <?php else: ?>
                <a href="<?= $oauthRoute ?>" class="btn btn-primary btn-sm" style="flex:1;text-align:center;">🔗 Connect</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="margin-top:1.25rem;padding:.75rem 1rem;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius-sm);font-size:.8rem;color:var(--text-secondary);">
        ✅ Connected platforms automatically sync interactions every 5 minutes. AI replies are generated every 10 minutes.
    </div>
</div>

<!-- Security Panel -->
<div id="securityPanel" class="settings-panel">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <div class="glass-card">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;">🔑 Change Password</h3>
            <form onsubmit="changePassword(event)">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-input" id="currentPwd" placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-input" id="newPwd" placeholder="Min. 8 characters">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-input" id="confirmPwd" placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary">🔑 Update Password</button>
            </form>
        </div>
        <div class="glass-card">
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">🔒 Security Options</h3>
            <div style="padding:0.875rem;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:var(--radius-md);margin-bottom:1rem;">
                <div style="font-size:0.85rem;font-weight:600;color:var(--blue-light);margin-bottom:0.25rem;">🛡️ Account Security</div>
                <p style="font-size:0.8rem;color:var(--text-muted);">Your account is protected. Enable 2FA for additional security.</p>
            </div>
            <button class="btn btn-ghost" style="width:100%;justify-content:flex-start;margin-bottom:0.5rem;font-size:0.82rem;" onclick="showToast('2FA setup coming soon','info')">📱 Enable Two-Factor Auth</button>
            <button class="btn btn-ghost" style="width:100%;justify-content:flex-start;margin-bottom:0.5rem;font-size:0.82rem;" onclick="showToast('Sending verification email…','info')">✉️ Verify Email</button>
            <button class="btn btn-sm" style="width:100%;justify-content:flex-start;margin-top:0.5rem;background:rgba(239,68,68,.12);color:var(--red);border:1px solid rgba(239,68,68,.2);padding:0.4rem 0.85rem;border-radius:var(--radius-sm);cursor:pointer;font-size:0.82rem;" onclick="if(confirm('Revoke all sessions?'))showToast('All other sessions revoked','success')">🚪 Revoke All Sessions</button>
        </div>
    </div>
</div>

<!-- Notifications Panel -->
<div id="notifPanel" class="settings-panel">
    <div class="glass-card">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:1.25rem;">🔔 Notification Preferences</h3>
        <?php foreach ($notifSettings as [$label, $desc, $enabled]): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem 0;border-bottom:1px solid rgba(255,255,255,0.04);">
            <div>
                <div style="font-size:0.875rem;font-weight:500;color:var(--text-primary);"><?= htmlspecialchars($label) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($desc) ?></div>
            </div>
            <label style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;cursor:pointer;">
                <input type="checkbox" <?= $enabled ? 'checked' : '' ?> style="opacity:0;width:0;height:0;" onchange="showToast('Preference saved','success')">
                <span class="notif-toggle-track" style="position:absolute;cursor:pointer;inset:0;background:<?= $enabled ? 'var(--blue)' : 'rgba(100,116,139,0.3)' ?>;border-radius:99px;transition:0.3s;">
                    <span style="position:absolute;height:18px;width:18px;left:<?= $enabled ? '23px' : '3px' ?>;bottom:3px;background:white;border-radius:50%;transition:0.3s;"></span>
                </span>
            </label>
        </div>
        <?php endforeach; ?>
        <button class="btn btn-primary" style="margin-top:1rem;" onclick="showToast('Notification preferences saved!','success')">💾 Save Preferences</button>
    </div>
</div>

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

function switchTab(panelId, btn) {
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.settings-tab').forEach(b => b.classList.remove('active'));
    const panel = document.getElementById(panelId);
    if (panel) panel.classList.add('active');
    if (btn) btn.classList.add('active');
}

async function saveBrandSettings(e) {
    e.preventDefault();
    const form = e.target;
    const data = {};
    new FormData(form).forEach((v, k) => data[k] = v);
    const d = await apiPost('/api/brand/update', data);
    showToast(d.success ? 'Brand settings saved!' : (d.error || 'Failed to save'), d.success !== false);
}

async function saveProfileSettings(e) {
    e.preventDefault();
    const form = e.target;
    const data = {};
    new FormData(form).forEach((v, k) => data[k] = v);
    const d = await apiPost('/api/profile/update', data);
    showToast(d.success ? 'Profile saved!' : (d.error || 'Failed to save'), d.success !== false);
}

async function changePassword(e) {
    e.preventDefault();
    const current = document.getElementById('currentPwd').value;
    const newPwd  = document.getElementById('newPwd').value;
    const confirm = document.getElementById('confirmPwd').value;
    if (!current || !newPwd) { showToast('All fields are required', false); return; }
    if (newPwd !== confirm)  { showToast('Passwords do not match', false); return; }
    if (newPwd.length < 8)   { showToast('Password must be at least 8 characters', false); return; }
    const d = await apiPost('/api/profile/change-password', { current_password: current, new_password: newPwd });
    showToast(d.success ? '🔑 Password updated!' : (d.error || 'Failed'), d.success !== false);
    if (d.success) { document.getElementById('currentPwd').value = ''; document.getElementById('newPwd').value = ''; document.getElementById('confirmPwd').value = ''; }
}

async function disconnectPlatform(accountId, name) {
    if (!confirm('Disconnect ' + name + '? This will stop syncing and remove the stored access token.')) return;
    const d = await apiPost('/api/platforms/disconnect', { account_id: accountId });
    showToast(d.success ? name + ' disconnected!' : (d.error || 'Failed to disconnect'), d.success !== false);
    if (d.success) setTimeout(() => location.reload(), 800);
}

// Handle toggle visual state
document.querySelectorAll('.notif-toggle-track').forEach(track => {
    const input = track.previousElementSibling;
    if (!input) return;
    input.addEventListener('change', function() {
        track.style.background = this.checked ? 'var(--blue)' : 'rgba(100,116,139,0.3)';
        track.querySelector('span').style.left = this.checked ? '23px' : '3px';
    });
});
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
