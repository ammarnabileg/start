<?php
$pageTitle  = 'Settings';
$activePage = 'settings';

$activeTab = $_GET['tab'] ?? 'general';

$settings = $settings ?? [
    'general' => [
        'brand_name'  => 'Nexus Digital',
        'timezone'    => 'Asia/Dubai',
        'language'    => 'en',
        'date_format' => 'D M Y',
    ],
    'brand' => [
        'name'        => 'Nexus Digital',
        'industry'    => 'B2B SaaS',
        'description' => 'Enterprise AI tools for modern marketing teams.',
        'website'     => 'https://nexusdigital.io',
        'colors'      => ['#3B82F6', '#8B5CF6', '#10B981'],
        'handles'     => [
            'linkedin'  => 'nexus-digital',
            'instagram' => '@nexusdigital',
            'tiktok'    => '@nexusdigital',
            'twitter'   => '@NexusDigital',
            'youtube'   => 'NexusDigitalIO',
            'facebook'  => 'NexusDigitalIO',
        ],
    ],
    'ai' => [
        'provider'       => 'anthropic',
        'model'          => 'claude-sonnet-4-6',
        'api_key'        => 'sk-ant-••••••••••••••••••••••',
        'monthly_budget' => 500,
        'current_usage'  => 312,
        'cost_tracking'  => true,
    ],
    'notifications' => [
        'email_notifications' => true,
        'agent_alerts'        => true,
        'content_approval'    => true,
        'trend_alerts'        => false,
        'weekly_report'       => true,
    ],
];

$platforms = [
    ['id'=>'linkedin',  'name'=>'LinkedIn',   'emoji'=>'💼', 'status'=>'connected',    'handle'=>'@nexus-digital',   'followers'=>'48.2K',  'connected_date'=>'Jan 12, 2025'],
    ['id'=>'instagram', 'name'=>'Instagram',  'emoji'=>'📸', 'status'=>'connected',    'handle'=>'@nexusdigital',    'followers'=>'127.5K', 'connected_date'=>'Jan 12, 2025'],
    ['id'=>'tiktok',    'name'=>'TikTok',     'emoji'=>'🎵', 'status'=>'connected',    'handle'=>'@nexusdigital',    'followers'=>'89.3K',  'connected_date'=>'Feb 3, 2025'],
    ['id'=>'facebook',  'name'=>'Facebook',   'emoji'=>'👥', 'status'=>'connected',    'handle'=>'NexusDigitalIO',   'followers'=>'34.1K',  'connected_date'=>'Jan 12, 2025'],
    ['id'=>'twitter',   'name'=>'Twitter/X',  'emoji'=>'🐦', 'status'=>'expired',      'handle'=>'@NexusDigital',    'followers'=>'22.8K',  'connected_date'=>'Jan 15, 2025'],
    ['id'=>'youtube',   'name'=>'YouTube',    'emoji'=>'▶️', 'status'=>'connected',    'handle'=>'NexusDigitalIO',   'followers'=>'15.4K',  'connected_date'=>'Mar 5, 2025'],
    ['id'=>'snapchat',  'name'=>'Snapchat',   'emoji'=>'👻', 'status'=>'connected',    'handle'=>'nexusdigital',     'followers'=>'8.9K',   'connected_date'=>'Feb 20, 2025'],
    ['id'=>'threads',   'name'=>'Threads',    'emoji'=>'🧵', 'status'=>'connected',    'handle'=>'@nexusdigital',    'followers'=>'5.2K',   'connected_date'=>'Apr 1, 2025'],
    ['id'=>'pinterest', 'name'=>'Pinterest',  'emoji'=>'📌', 'status'=>'disconnected', 'handle'=>null,               'followers'=>null,     'connected_date'=>null],
    ['id'=>'whatsapp',  'name'=>'WhatsApp',   'emoji'=>'💬', 'status'=>'disconnected', 'handle'=>null,               'followers'=>null,     'connected_date'=>null],
    ['id'=>'telegram',  'name'=>'Telegram',   'emoji'=>'✈️', 'status'=>'connected',    'handle'=>'@nexusdigital_ch', 'followers'=>'4.1K',   'connected_date'=>'Mar 18, 2025'],
];

$sessions = [
    ['device'=>'MacBook Pro (Safari)',    'ip'=>'82.105.44.12',  'location'=>'Dubai, UAE',  'last_active'=>'Now (current)', 'current'=>true],
    ['device'=>'iPhone 15 Pro (iOS App)', 'ip'=>'82.105.44.14',  'location'=>'Dubai, UAE',  'last_active'=>'2 hours ago',   'current'=>false],
    ['device'=>'Windows PC (Chrome)',     'ip'=>'194.29.171.3',  'location'=>'Riyadh, KSA', 'last_active'=>'Yesterday',     'current'=>false],
];

$loginHistory = [
    ['date'=>'May 17, 2025 09:14 AM', 'device'=>'MacBook Pro',   'ip'=>'82.105.44.12',  'status'=>'success'],
    ['date'=>'May 16, 2025 11:32 PM', 'device'=>'iPhone 15 Pro', 'ip'=>'82.105.44.14',  'status'=>'success'],
    ['date'=>'May 16, 2025 03:07 PM', 'device'=>'Windows PC',    'ip'=>'194.29.171.3',  'status'=>'success'],
    ['date'=>'May 15, 2025 08:55 AM', 'device'=>'MacBook Pro',   'ip'=>'82.105.44.12',  'status'=>'success'],
    ['date'=>'May 14, 2025 07:23 PM', 'device'=>'Unknown',       'ip'=>'103.42.19.87',  'status'=>'failed'],
];

$tabs = [
    ['id'=>'general',       'label'=>'General',       'icon'=>'⚙️'],
    ['id'=>'brand',         'label'=>'Brand',         'icon'=>'🎨'],
    ['id'=>'platforms',     'label'=>'Platforms',     'icon'=>'🔗'],
    ['id'=>'ai',            'label'=>'AI Config',     'icon'=>'🤖'],
    ['id'=>'notifications', 'label'=>'Notifications', 'icon'=>'🔔'],
    ['id'=>'security',      'label'=>'Security',      'icon'=>'🔐'],
    ['id'=>'billing',       'label'=>'Billing',       'icon'=>'💳'],
];

ob_start();
?>

<div class="settings-page">

  <!-- PAGE HEADER -->
  <div class="page-header" style="margin-bottom:1.5rem">
    <h1>Settings</h1>
    <p style="color:var(--text-muted);margin-top:0.25rem">Configure your account, brand, platforms, and AI preferences</p>
  </div>

  <div style="display:grid;grid-template-columns:220px 1fr;gap:1.5rem;align-items:start">

    <!-- LEFT TAB NAVIGATION -->
    <div class="glass-card" style="padding:0.5rem;position:sticky;top:1rem">
      <?php foreach ($tabs as $tab): ?>
      <button onclick="settingsTabs.show('<?= $tab['id'] ?>')"
              id="tab-btn-<?= $tab['id'] ?>"
              style="width:100%;display:flex;align-items:center;gap:0.65rem;padding:0.65rem 0.9rem;border-radius:var(--radius-sm);border:none;cursor:pointer;font-size:0.875rem;font-weight:<?= $activeTab===$tab['id']?'600':'400' ?>;color:<?= $activeTab===$tab['id']?'var(--text-primary)':'var(--text-muted)' ?>;background:<?= $activeTab===$tab['id']?'var(--glass-bg-hover)':'transparent' ?>;text-align:left;transition:all 0.15s;margin-bottom:0.15rem">
        <span><?= $tab['icon'] ?></span>
        <span><?= htmlspecialchars($tab['label']) ?></span>
      </button>
      <?php endforeach ?>
    </div>

    <!-- TAB CONTENT PANELS -->
    <div>

      <!-- ======= GENERAL ======= -->
      <div id="tab-general" class="settings-tab-panel" style="display:<?= $activeTab==='general'?'block':'none' ?>">
        <div class="glass-card">
          <h3 style="margin:0 0 1.25rem;font-size:1rem">General Settings</h3>
          <form action="/dashboard/settings/general" method="POST">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
              <div class="form-group" style="margin:0">
                <label class="form-label">Brand / Account Name</label>
                <input type="text" class="form-input" name="brand_name" value="<?= htmlspecialchars($settings['general']['brand_name']) ?>">
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Timezone</label>
                <select class="form-select" name="timezone">
                  <?php foreach (['UTC'=>'UTC','Asia/Dubai'=>'Asia/Dubai (GST)','America/New_York'=>'America/New_York (ET)','Europe/London'=>'Europe/London (GMT)','Asia/Riyadh'=>'Asia/Riyadh (AST)','Asia/Singapore'=>'Asia/Singapore (SGT)'] as $val=>$label): ?>
                  <option value="<?= $val ?>" <?= $settings['general']['timezone']===$val?'selected':'' ?>><?= htmlspecialchars($label) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Language</label>
                <select class="form-select" name="language">
                  <option value="en" <?= $settings['general']['language']==='en'?'selected':'' ?>>English</option>
                  <option value="ar" <?= $settings['general']['language']==='ar'?'selected':'' ?>>العربية (Arabic)</option>
                  <option value="fr" <?= $settings['general']['language']==='fr'?'selected':'' ?>>Français (French)</option>
                </select>
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Date Format</label>
                <select class="form-select" name="date_format">
                  <option value="D M Y" <?= $settings['general']['date_format']==='D M Y'?'selected':'' ?>>DD MMM YYYY (e.g. 17 May 2025)</option>
                  <option value="m/d/Y" <?= $settings['general']['date_format']==='m/d/Y'?'selected':'' ?>>MM/DD/YYYY (US)</option>
                  <option value="d/m/Y" <?= $settings['general']['date_format']==='d/m/Y'?'selected':'' ?>>DD/MM/YYYY (EU)</option>
                  <option value="Y-m-d" <?= $settings['general']['date_format']==='Y-m-d'?'selected':'' ?>>YYYY-MM-DD (ISO)</option>
                </select>
              </div>
            </div>
            <div style="display:flex;justify-content:flex-end">
              <button type="submit" class="btn btn-primary">Save General Settings</button>
            </div>
          </form>
        </div>
      </div>

      <!-- ======= BRAND ======= -->
      <div id="tab-brand" class="settings-tab-panel" style="display:<?= $activeTab==='brand'?'block':'none' ?>">
        <div class="glass-card">
          <h3 style="margin:0 0 1.25rem;font-size:1rem">Brand Settings</h3>
          <form action="/dashboard/settings/brand" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
              <div class="form-group" style="margin:0">
                <label class="form-label">Brand Name</label>
                <input type="text" class="form-input" name="name" value="<?= htmlspecialchars($settings['brand']['name']) ?>">
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Industry</label>
                <select class="form-select" name="industry">
                  <?php foreach (['B2B SaaS','E-commerce','Healthcare','Education','Finance','Retail','Technology','Media','Consulting','Other'] as $ind): ?>
                  <option <?= $settings['brand']['industry']===$ind?'selected':'' ?>><?= htmlspecialchars($ind) ?></option>
                  <?php endforeach ?>
                </select>
              </div>
              <div class="form-group" style="grid-column:1/-1;margin:0">
                <label class="form-label">Brand Description</label>
                <textarea class="form-textarea" name="description" rows="2"><?= htmlspecialchars($settings['brand']['description']) ?></textarea>
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Website</label>
                <input type="url" class="form-input" name="website" value="<?= htmlspecialchars($settings['brand']['website']) ?>">
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Logo Upload</label>
                <input type="file" class="form-input" name="logo" accept="image/png,image/jpeg,image/svg+xml" style="padding:0.4rem">
              </div>
            </div>

            <div style="margin-bottom:1rem">
              <label class="form-label">Brand Colors</label>
              <div id="brandColorsRow" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap">
                <?php foreach ($settings['brand']['colors'] as $i => $color): ?>
                <div style="display:flex;flex-direction:column;align-items:center;gap:0.3rem">
                  <input type="color" name="colors[]" value="<?= htmlspecialchars($color) ?>"
                         style="width:44px;height:44px;border:none;border-radius:var(--radius-sm);cursor:pointer;background:none;padding:0">
                  <span style="font-size:0.68rem;color:var(--text-muted)"><?= ['Primary','Secondary','Accent'][$i] ?? 'Color '.($i+1) ?></span>
                </div>
                <?php endforeach ?>
                <button type="button" onclick="brandSettings.addColor()" class="btn btn-ghost btn-sm">+ Add Color</button>
              </div>
            </div>

            <div style="margin-bottom:1rem">
              <label class="form-label">Social Handles</label>
              <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem">
                <?php foreach (['linkedin'=>'💼 LinkedIn','instagram'=>'📸 Instagram','tiktok'=>'🎵 TikTok','twitter'=>'🐦 Twitter/X','youtube'=>'▶️ YouTube','facebook'=>'👥 Facebook'] as $pl=>$label): ?>
                <div class="form-group" style="margin:0">
                  <label class="form-label" style="font-size:0.75rem"><?= $label ?></label>
                  <input type="text" class="form-input" name="handles[<?= $pl ?>]"
                         value="<?= htmlspecialchars($settings['brand']['handles'][$pl] ?? '') ?>"
                         placeholder="@yourbrand" style="font-size:0.82rem">
                </div>
                <?php endforeach ?>
              </div>
            </div>

            <div style="display:flex;justify-content:flex-end">
              <button type="submit" class="btn btn-primary">Save Brand Settings</button>
            </div>
          </form>
        </div>
      </div>

      <!-- ======= PLATFORMS ======= -->
      <div id="tab-platforms" class="settings-tab-panel" style="display:<?= $activeTab==='platforms'?'block':'none' ?>">
        <div class="glass-card">
          <h3 style="margin:0 0 1.25rem;font-size:1rem">Connected Platforms</h3>
          <div style="display:flex;flex-direction:column;gap:0.75rem">
            <?php foreach ($platforms as $p): ?>
            <?php
            $statusColor = match($p['status']) {
                'connected'    => 'var(--green)',
                'expired'      => 'var(--red-light)',
                'disconnected' => 'var(--text-muted)',
                default        => 'var(--text-muted)',
            };
            $statusLabel = match($p['status']) {
                'connected'    => 'Connected',
                'expired'      => 'Token Expired',
                'disconnected' => 'Not Connected',
                default        => ucfirst($p['status']),
            };
            ?>
            <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:1rem;display:flex;align-items:center;gap:1rem">
              <span style="font-size:1.8rem;flex-shrink:0"><?= $p['emoji'] ?></span>
              <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.2rem">
                  <span style="font-weight:600;font-size:0.9rem"><?= htmlspecialchars($p['name']) ?></span>
                  <span style="width:7px;height:7px;border-radius:50%;background:<?= $statusColor ?>;display:inline-block;<?= $p['status']==='connected'?'box-shadow:0 0 5px '.$statusColor.';':'' ?>"></span>
                  <span style="font-size:0.75rem;color:<?= $statusColor ?>"><?= $statusLabel ?></span>
                </div>
                <?php if ($p['status']!=='disconnected' && $p['handle']): ?>
                <div style="font-size:0.78rem;color:var(--text-muted)">
                  <?= htmlspecialchars($p['handle']) ?>
                  <?php if ($p['followers']): ?> · <?= htmlspecialchars($p['followers']) ?> followers<?php endif ?>
                  <?php if ($p['connected_date']): ?> · Connected <?= htmlspecialchars($p['connected_date']) ?><?php endif ?>
                </div>
                <?php endif ?>
              </div>
              <div style="flex-shrink:0">
                <?php if ($p['status']==='connected'): ?>
                <button class="btn btn-ghost btn-sm" style="color:var(--red-light)"
                        onclick="if(confirm('Disconnect <?= htmlspecialchars(addslashes($p['name'])) ?>?')) window.location.href='/auth/platform/disconnect/<?= htmlspecialchars($p['id']) ?>'">
                  Disconnect
                </button>
                <?php elseif ($p['status']==='expired'): ?>
                <a href="/auth/platform/connect/<?= htmlspecialchars($p['id']) ?>" class="btn btn-ghost btn-sm" style="color:var(--yellow)">Reconnect</a>
                <?php else: ?>
                <a href="/auth/platform/connect/<?= htmlspecialchars($p['id']) ?>" class="btn btn-primary btn-sm">Connect</a>
                <?php endif ?>
              </div>
            </div>
            <?php endforeach ?>
          </div>
        </div>
      </div>

      <!-- ======= AI CONFIG ======= -->
      <div id="tab-ai" class="settings-tab-panel" style="display:<?= $activeTab==='ai'?'block':'none' ?>">
        <div class="glass-card">
          <h3 style="margin:0 0 1.25rem;font-size:1rem">AI Configuration</h3>
          <form action="/dashboard/settings/ai" method="POST">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">

            <div class="form-group" style="margin-bottom:1.25rem">
              <label class="form-label">AI Provider</label>
              <div style="display:flex;gap:1rem">
                <label id="providerAnthropicLabel"
                       style="display:flex;align-items:center;gap:0.6rem;padding:0.75rem 1.25rem;background:var(--glass-bg);border:2px solid <?= $settings['ai']['provider']==='anthropic'?'var(--blue)':'var(--glass-border)' ?>;border-radius:var(--radius-md);cursor:pointer;font-size:0.875rem;font-weight:600;flex:1;transition:border-color 0.2s"
                       onclick="aiConfig.selectProvider('anthropic')">
                  <input type="radio" name="provider" value="anthropic" id="providerAnthropic" <?= $settings['ai']['provider']==='anthropic'?'checked':'' ?> style="display:none">
                  🟣 Anthropic Claude
                </label>
                <label id="providerOpenAILabel"
                       style="display:flex;align-items:center;gap:0.6rem;padding:0.75rem 1.25rem;background:var(--glass-bg);border:2px solid <?= $settings['ai']['provider']==='openai'?'var(--blue)':'var(--glass-border)' ?>;border-radius:var(--radius-md);cursor:pointer;font-size:0.875rem;font-weight:600;flex:1;transition:border-color 0.2s"
                       onclick="aiConfig.selectProvider('openai')">
                  <input type="radio" name="provider" value="openai" id="providerOpenAI" <?= $settings['ai']['provider']==='openai'?'checked':'' ?> style="display:none">
                  🟢 OpenAI
                </label>
              </div>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
              <label class="form-label">API Key</label>
              <div style="display:flex;gap:0.5rem;align-items:center">
                <input type="password" class="form-input" name="api_key" id="apiKeyInput"
                       value="<?= htmlspecialchars($settings['ai']['api_key']) ?>"
                       style="flex:1;font-family:monospace" readonly>
                <button type="button" class="btn btn-ghost btn-sm" onclick="aiConfig.toggleEdit()">✏️ Edit</button>
                <button type="button" class="btn btn-ghost btn-sm" id="apiKeyVisBtn" onclick="aiConfig.toggleVisibility()">👁 Show</button>
              </div>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
              <label class="form-label">Model</label>
              <select class="form-select" name="model">
                <optgroup label="Anthropic Claude">
                  <option value="claude-opus-4-7"   <?= $settings['ai']['model']==='claude-opus-4-7'  ?'selected':'' ?>>claude-opus-4-7 (Most capable)</option>
                  <option value="claude-sonnet-4-6" <?= $settings['ai']['model']==='claude-sonnet-4-6'?'selected':'' ?>>claude-sonnet-4-6 (Balanced)</option>
                  <option value="claude-haiku-3-5"  <?= $settings['ai']['model']==='claude-haiku-3-5' ?'selected':'' ?>>claude-haiku-3-5 (Fastest)</option>
                </optgroup>
                <optgroup label="OpenAI">
                  <option value="gpt-4o"            <?= $settings['ai']['model']==='gpt-4o'           ?'selected':'' ?>>gpt-4o (Most capable)</option>
                  <option value="gpt-4o-mini"       <?= $settings['ai']['model']==='gpt-4o-mini'      ?'selected':'' ?>>gpt-4o-mini (Fast &amp; cheap)</option>
                </optgroup>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem">
              <label class="form-label">Monthly Token Budget (USD)</label>
              <input type="number" class="form-input" name="monthly_budget"
                     value="<?= (int)$settings['ai']['monthly_budget'] ?>" min="10" max="10000" step="10">
            </div>

            <?php
            $usagePct   = min(100, round($settings['ai']['current_usage'] / $settings['ai']['monthly_budget'] * 100));
            $usageColor = $usagePct >= 90 ? 'var(--red)' : ($usagePct >= 70 ? 'var(--yellow)' : 'var(--green)');
            ?>
            <div style="margin-bottom:1.25rem;padding:1rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md)">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem">
                <span style="font-size:0.82rem;font-weight:600">Current Month Usage</span>
                <span style="font-size:0.82rem;color:<?= $usageColor ?>;font-weight:700">
                  $<?= number_format($settings['ai']['current_usage']) ?> / $<?= number_format($settings['ai']['monthly_budget']) ?>
                </span>
              </div>
              <div class="progress-bar lg">
                <div class="progress-fill" style="width:<?= $usagePct ?>%;background:<?= $usageColor ?>"></div>
              </div>
              <div style="font-size:0.72rem;color:var(--text-muted);margin-top:0.35rem"><?= $usagePct ?>% used · Resets June 1, 2025</div>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;padding:0.85rem 1rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);margin-bottom:1.25rem">
              <div>
                <div style="font-size:0.875rem;font-weight:600">Cost Tracking</div>
                <div style="font-size:0.75rem;color:var(--text-muted)">Log token usage and costs per operation</div>
              </div>
              <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer">
                <input type="checkbox" name="cost_tracking" value="1" id="costTrackingCb" <?= $settings['ai']['cost_tracking']?'checked':'' ?> style="opacity:0;width:0;height:0">
                <span id="costTrackingSlider" style="position:absolute;inset:0;border-radius:999px;background:<?= $settings['ai']['cost_tracking']?'var(--blue)':'var(--glass-border)' ?>;transition:background 0.2s;cursor:pointer"
                      onclick="const cb=document.getElementById('costTrackingCb');cb.checked=!cb.checked;this.style.background=cb.checked?'var(--blue)':'var(--glass-border)';document.getElementById('costTrackingKnob').style.left=cb.checked?'22px':'2px'">
                  <span id="costTrackingKnob" style="position:absolute;top:2px;left:<?= $settings['ai']['cost_tracking']?'22':'2' ?>px;width:20px;height:20px;border-radius:50%;background:#fff;transition:left 0.2s;pointer-events:none"></span>
                </span>
              </label>
            </div>

            <div style="display:flex;justify-content:flex-end">
              <button type="submit" class="btn btn-primary">Save AI Settings</button>
            </div>
          </form>
        </div>
      </div>

      <!-- ======= NOTIFICATIONS ======= -->
      <div id="tab-notifications" class="settings-tab-panel" style="display:<?= $activeTab==='notifications'?'block':'none' ?>">
        <div class="glass-card">
          <h3 style="margin:0 0 1.25rem;font-size:1rem">Notification Preferences</h3>
          <form action="/dashboard/settings/notifications" method="POST">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">
            <?php
            $notifItems = [
                'email_notifications' => ['label'=>'Email Notifications',     'desc'=>'Receive a daily digest of activity and alerts to your email'],
                'agent_alerts'        => ['label'=>'AI Agent Alerts',         'desc'=>'Get notified when an AI agent completes a task or encounters an error'],
                'content_approval'    => ['label'=>'Content Approval',        'desc'=>'Notify me when content is pending my approval before publishing'],
                'trend_alerts'        => ['label'=>'Trend Alerts',            'desc'=>'Receive real-time alerts when a new viral trend is detected'],
                'weekly_report'       => ['label'=>'Weekly Performance Report','desc'=>'Get a comprehensive weekly summary of your social media performance'],
            ];
            ?>
            <div style="display:flex;flex-direction:column;gap:0.75rem">
              <?php foreach ($notifItems as $key => $item): ?>
              <?php $checked = $settings['notifications'][$key] ?? false; ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md)">
                <div>
                  <div style="font-size:0.875rem;font-weight:600;margin-bottom:0.2rem"><?= htmlspecialchars($item['label']) ?></div>
                  <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($item['desc']) ?></div>
                </div>
                <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;flex-shrink:0">
                  <input type="checkbox" name="<?= $key ?>" value="1" id="notif-<?= $key ?>" <?= $checked?'checked':'' ?> style="opacity:0;width:0;height:0">
                  <span style="position:absolute;inset:0;border-radius:999px;background:<?= $checked?'var(--blue)':'var(--glass-border)' ?>;transition:background 0.2s;cursor:pointer"
                        onclick="const cb=document.getElementById('notif-<?= $key ?>');cb.checked=!cb.checked;this.style.background=cb.checked?'var(--blue)':'var(--glass-border)';this.querySelector('span').style.left=cb.checked?'22px':'2px'">
                    <span style="position:absolute;top:2px;left:<?= $checked?'22':'2' ?>px;width:20px;height:20px;border-radius:50%;background:#fff;transition:left 0.2s;pointer-events:none"></span>
                  </span>
                </label>
              </div>
              <?php endforeach ?>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:1.25rem">
              <button type="submit" class="btn btn-primary">Save Notification Settings</button>
            </div>
          </form>
        </div>
      </div>

      <!-- ======= SECURITY ======= -->
      <div id="tab-security" class="settings-tab-panel" style="display:<?= $activeTab==='security'?'block':'none' ?>">

        <div class="glass-card" style="margin-bottom:1.25rem">
          <h3 style="margin:0 0 1.25rem;font-size:1rem">🔑 Change Password</h3>
          <form action="/dashboard/settings/password" method="POST">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(bin2hex(random_bytes(16))) ?>">
            <div style="display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1rem">
              <div class="form-group" style="margin:0">
                <label class="form-label">Current Password</label>
                <input type="password" class="form-input" name="current_password" required autocomplete="current-password">
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">New Password</label>
                <input type="password" class="form-input" name="new_password" id="newPasswordInput"
                       required minlength="8" autocomplete="new-password"
                       oninput="securitySettings.checkStrength(this.value)">
                <div id="passwordStrengthBar" style="margin-top:0.4rem;display:none">
                  <div class="progress-bar sm">
                    <div id="passwordStrengthFill" class="progress-fill" style="width:0%"></div>
                  </div>
                  <div id="passwordStrengthLabel" style="font-size:0.7rem;margin-top:0.2rem"></div>
                </div>
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Confirm New Password</label>
                <input type="password" class="form-input" name="new_password_confirmation" required autocomplete="new-password">
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Update Password</button>
          </form>
        </div>

        <div class="glass-card" style="margin-bottom:1.25rem">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <div>
              <h3 style="margin:0 0 0.2rem;font-size:1rem">🔒 Two-Factor Authentication</h3>
              <div style="font-size:0.8rem;color:var(--text-muted)">Protect your account with an authenticator app</div>
            </div>
            <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer">
              <input type="checkbox" id="twoFAToggle" style="opacity:0;width:0;height:0">
              <span style="position:absolute;inset:0;border-radius:999px;background:var(--glass-border);transition:background 0.2s;cursor:pointer"
                    onclick="const cb=document.getElementById('twoFAToggle');cb.checked=!cb.checked;this.style.background=cb.checked?'var(--blue)':'var(--glass-border)';document.getElementById('twoFAKnob').style.left=cb.checked?'22px':'2px';securitySettings.toggle2FA(cb.checked)">
                <span id="twoFAKnob" style="position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:50%;background:#fff;transition:left 0.2s;pointer-events:none"></span>
              </span>
            </label>
          </div>
          <div id="twoFASetup" style="display:none">
            <div style="display:flex;gap:1.5rem;align-items:flex-start">
              <div style="width:140px;height:140px;background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.72rem;color:var(--text-muted);text-align:center;padding:0.5rem">
                [QR Code]<br>Scan with your<br>authenticator app
              </div>
              <div style="flex:1">
                <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:0.75rem;line-height:1.6">
                  Scan the QR code with Google Authenticator, Authy, or any TOTP-compatible app, then enter the 6-digit code to verify.
                </div>
                <div class="form-group" style="margin-bottom:0.75rem">
                  <label class="form-label">Verification Code</label>
                  <input type="text" class="form-input" id="twoFACode" placeholder="000000"
                         maxlength="6" style="letter-spacing:0.3em;font-size:1.1rem;max-width:160px">
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="securitySettings.verify2FA()">Verify &amp; Enable 2FA</button>
              </div>
            </div>
          </div>
        </div>

        <div class="glass-card" style="margin-bottom:1.25rem">
          <h3 style="margin:0 0 1rem;font-size:1rem">💻 Active Sessions</h3>
          <div style="display:flex;flex-direction:column;gap:0.5rem">
            <?php foreach ($sessions as $s): ?>
            <div style="display:flex;align-items:center;gap:1rem;padding:0.85rem;background:var(--glass-bg);border:1px solid <?= $s['current']?'var(--blue)':'var(--glass-border)' ?>;border-radius:var(--radius-md)">
              <div style="font-size:1.4rem;flex-shrink:0"><?= (strpos($s['device'],'iPhone')!==false||strpos($s['device'],'iOS')!==false)?'📱':'💻' ?></div>
              <div style="flex:1;min-width:0">
                <div style="font-size:0.875rem;font-weight:600">
                  <?= htmlspecialchars($s['device']) ?>
                  <?php if ($s['current']): ?><span style="font-size:0.68rem;color:var(--blue-light);font-weight:400"> (this session)</span><?php endif ?>
                </div>
                <div style="font-size:0.75rem;color:var(--text-muted)"><?= htmlspecialchars($s['ip']) ?> · <?= htmlspecialchars($s['location']) ?> · <?= htmlspecialchars($s['last_active']) ?></div>
              </div>
              <?php if (!$s['current']): ?>
              <button class="btn btn-ghost btn-sm" style="color:var(--red-light);flex-shrink:0"
                      onclick="if(confirm('Revoke this session?')) window.location.href='/dashboard/settings/sessions/revoke'">
                Revoke
              </button>
              <?php endif ?>
            </div>
            <?php endforeach ?>
          </div>
          <div style="margin-top:0.75rem;display:flex;justify-content:flex-end">
            <button class="btn btn-ghost btn-sm" style="color:var(--red-light)"
                    onclick="if(confirm('Revoke all other sessions?')) window.location.href='/dashboard/settings/sessions/revoke-all'">
              Revoke All Other Sessions
            </button>
          </div>
        </div>

        <div class="glass-card">
          <h3 style="margin:0 0 1rem;font-size:1rem">📋 Login History</h3>
          <div class="table-wrapper">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date &amp; Time</th>
                  <th>Device</th>
                  <th>IP Address</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($loginHistory as $l): ?>
                <tr>
                  <td style="font-size:0.8rem;color:var(--text-muted)"><?= htmlspecialchars($l['date']) ?></td>
                  <td><?= htmlspecialchars($l['device']) ?></td>
                  <td style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($l['ip']) ?></td>
                  <td>
                    <?php if ($l['status']==='success'): ?>
                    <span class="badge badge-success badge-dot">Success</span>
                    <?php else: ?>
                    <span class="badge badge-danger badge-dot">Failed</span>
                    <?php endif ?>
                  </td>
                </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ======= BILLING ======= -->
      <div id="tab-billing" class="settings-tab-panel" style="display:<?= $activeTab==='billing'?'block':'none' ?>">
        <div class="glass-card" style="margin-bottom:1.25rem">
          <h3 style="margin:0 0 1.25rem;font-size:1rem">💳 Billing &amp; Subscription</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">
            <div style="background:var(--glass-bg);border:1px solid var(--blue);border-radius:var(--radius-md);padding:1.25rem">
              <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--blue-light);margin-bottom:0.5rem">Current Plan</div>
              <div style="font-size:1.4rem;font-weight:800;margin-bottom:0.25rem">Enterprise</div>
              <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:0.75rem">$299 / month · Renews June 1, 2025</div>
              <span class="badge badge-success badge-dot">Active</span>
            </div>
            <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-md);padding:1.25rem">
              <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:0.5rem">Payment Method</div>
              <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem">
                <span style="font-size:1.4rem">💳</span>
                <span style="font-weight:600">Visa ending in 4291</span>
              </div>
              <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.75rem">Expires 08/2027</div>
              <button class="btn btn-ghost btn-sm">Update Card</button>
            </div>
          </div>
          <div style="display:flex;gap:0.75rem">
            <button class="btn btn-ghost btn-sm">Download Invoice</button>
            <button class="btn btn-ghost btn-sm">View Billing History</button>
            <button class="btn btn-ghost btn-sm" style="color:var(--red-light)">Cancel Subscription</button>
          </div>
        </div>

        <div class="glass-card">
          <h3 style="margin:0 0 1rem;font-size:1rem">📊 Usage This Month</h3>
          <?php
          $usageItems = [
              ['label'=>'AI Generations',      'used'=>3842, 'limit'=>10000, 'unit'=>'generations'],
              ['label'=>'Scheduled Posts',     'used'=>127,  'limit'=>500,   'unit'=>'posts'],
              ['label'=>'Team Seats',          'used'=>5,    'limit'=>20,    'unit'=>'seats'],
              ['label'=>'Platform Connections','used'=>9,    'limit'=>11,    'unit'=>'platforms'],
          ];
          ?>
          <div style="display:flex;flex-direction:column;gap:0.75rem">
            <?php foreach ($usageItems as $u): ?>
            <?php $pct = round($u['used'] / $u['limit'] * 100); ?>
            <div>
              <div style="display:flex;justify-content:space-between;font-size:0.82rem;margin-bottom:0.3rem">
                <span style="font-weight:500"><?= htmlspecialchars($u['label']) ?></span>
                <span style="color:var(--text-muted)"><?= number_format($u['used']) ?> / <?= number_format($u['limit']) ?> <?= $u['unit'] ?></span>
              </div>
              <div class="progress-bar sm">
                <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $pct>=90?'var(--red)':($pct>=70?'var(--yellow)':'var(--blue)') ?>"></div>
              </div>
            </div>
            <?php endforeach ?>
          </div>
        </div>
      </div>

    </div><!-- end right column -->
  </div><!-- end settings grid -->
</div>

<script>
const settingsTabs = {
  show(id) {
    document.querySelectorAll('.settings-tab-panel').forEach(p => p.style.display = 'none');
    const panel = document.getElementById('tab-' + id);
    if (panel) panel.style.display = 'block';
    document.querySelectorAll('[id^="tab-btn-"]').forEach(btn => {
      const active = btn.id === 'tab-btn-' + id;
      btn.style.fontWeight = active ? '600'                   : '400';
      btn.style.color      = active ? 'var(--text-primary)'   : 'var(--text-muted)';
      btn.style.background = active ? 'var(--glass-bg-hover)' : 'transparent';
    });
    const url = new URL(window.location);
    url.searchParams.set('tab', id);
    history.replaceState({}, '', url);
  }
};

const aiConfig = {
  _editing: false,
  _visible: false,
  selectProvider(p) {
    document.getElementById('providerAnthropic').checked = p === 'anthropic';
    document.getElementById('providerOpenAI').checked    = p === 'openai';
    document.getElementById('providerAnthropicLabel').style.borderColor = p === 'anthropic' ? 'var(--blue)' : 'var(--glass-border)';
    document.getElementById('providerOpenAILabel').style.borderColor    = p === 'openai'    ? 'var(--blue)' : 'var(--glass-border)';
  },
  toggleEdit() {
    const input = document.getElementById('apiKeyInput');
    this._editing = !this._editing;
    input.readOnly = !this._editing;
    if (this._editing) { input.type = 'text'; input.focus(); input.select(); }
  },
  toggleVisibility() {
    const input = document.getElementById('apiKeyInput');
    const btn   = document.getElementById('apiKeyVisBtn');
    this._visible = !this._visible;
    input.type      = this._visible ? 'text'     : 'password';
    btn.textContent = this._visible ? '🙈 Hide'  : '👁 Show';
  }
};

const securitySettings = {
  toggle2FA(enabled) {
    document.getElementById('twoFASetup').style.display = enabled ? 'block' : 'none';
  },
  verify2FA() {
    const code = document.getElementById('twoFACode').value.trim();
    if (code.length !== 6 || !/^\d+$/.test(code)) { alert('Please enter a valid 6-digit code.'); return; }
    const form  = document.createElement('form');
    form.method = 'POST'; form.action = '/dashboard/settings/2fa/enable';
    const inp   = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'code'; inp.value = code;
    form.appendChild(inp); document.body.appendChild(form); form.submit();
  },
  checkStrength(val) {
    const bar   = document.getElementById('passwordStrengthBar');
    const fill  = document.getElementById('passwordStrengthFill');
    const label = document.getElementById('passwordStrengthLabel');
    if (!val) { bar.style.display = 'none'; return; }
    bar.style.display = 'block';
    let score = 0;
    if (val.length >= 8)           score++;
    if (val.length >= 12)          score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val))  score++;
    const levels = [
      {pct:15,  color:'var(--red)',    text:'Very Weak'},
      {pct:35,  color:'var(--red)',    text:'Weak'},
      {pct:55,  color:'var(--yellow)', text:'Fair'},
      {pct:78,  color:'var(--blue)',   text:'Strong'},
      {pct:100, color:'var(--green)',  text:'Very Strong'},
    ];
    const lvl = levels[Math.max(0, score - 1)] || levels[0];
    fill.style.width      = lvl.pct + '%';
    fill.style.background = lvl.color;
    label.textContent     = lvl.text;
    label.style.color     = lvl.color;
  }
};

const brandSettings = {
  addColor() {
    const row   = document.getElementById('brandColorsRow');
    const addBtn = row.querySelector('button');
    const col   = document.createElement('div');
    col.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:0.3rem';
    const inp   = document.createElement('input');
    inp.type = 'color'; inp.name = 'colors[]'; inp.value = '#6B7280';
    inp.style.cssText = 'width:44px;height:44px;border:none;border-radius:var(--radius-sm);cursor:pointer;background:none;padding:0';
    const lbl   = document.createElement('span');
    lbl.style.cssText = 'font-size:0.68rem;color:var(--text-muted)';
    lbl.textContent = 'Color';
    col.appendChild(inp); col.appendChild(lbl);
    row.insertBefore(col, addBtn);
  }
};
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/main.php'; ?>
