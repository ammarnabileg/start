<?php
ob_start();
$pageTitle = 'Company Settings';
$db = Database::getInstance();
$tid = Auth::user()['tenant_id'];
$tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tid]);
$settings = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE tenant_id = ?", [$tid]) ?: [];
$settingsMap = array_column($settings, 'setting_value', 'setting_key');
$teamMembers = $db->fetchAll("SELECT u.id, u.full_name, u.email, u.avatar_url, r.name as role_name FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id WHERE u.tenant_id = ? ORDER BY u.full_name ASC", [$tid]) ?: [];
$activeTab = $_GET['tab'] ?? 'general';
?>

<div class="max-w-4xl mx-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Company Settings</h1>
    <p class="text-sm text-gray-500 mt-1">Manage your company profile, team, integrations, and career page</p>
  </div>

  <!-- Tab Navigation -->
  <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-8">
    <?php foreach (['general' => 'Company Profile', 'team' => 'Team', 'integrations' => 'Integrations', 'billing' => 'Billing', 'career' => 'Career Page'] as $k => $v): ?>
    <button onclick="switchTab('<?= $k ?>')" data-tab="<?= $k ?>"
      class="flex-1 py-2.5 text-sm font-medium rounded-lg transition-all tab-btn <?= $activeTab === $k ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
      <?= $v ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- ===================== GENERAL TAB ===================== -->
  <div id="tab-general" class="<?= $activeTab !== 'general' ? 'hidden' : '' ?> space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="font-semibold text-gray-900 mb-1">Company Profile</h3>
      <p class="text-sm text-gray-500 mb-5">Basic information about your company</p>
      <form id="general-form" class="space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Company Name <span class="text-red-500">*</span></label>
            <input type="text" name="company_name" value="<?= htmlspecialchars($tenant['name'] ?? '') ?>" required
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none transition-colors">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Industry</label>
            <select name="company_industry" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none">
              <?php foreach (['Technology', 'Finance & Banking', 'Healthcare', 'Education', 'Retail & E-commerce', 'Manufacturing', 'Media & Entertainment', 'Legal', 'Real Estate', 'Consulting', 'Logistics', 'Other'] as $ind): ?>
              <option value="<?= $ind ?>" <?= ($settingsMap['company_industry'] ?? '') === $ind ? 'selected' : '' ?>><?= $ind ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Company Size</label>
            <select name="company_size" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none">
              <?php foreach (['1-10', '11-50', '51-200', '201-500', '500+'] as $sz): ?>
              <option value="<?= $sz ?>" <?= ($settingsMap['company_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?> employees</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Website URL</label>
            <input type="url" name="company_website" value="<?= htmlspecialchars($settingsMap['company_website'] ?? '') ?>"
              placeholder="https://yourcompany.com"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">About Company</label>
          <textarea name="company_about" rows="4" placeholder="Describe your company, culture, and what makes you unique..."
            class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm resize-none focus:ring-2 focus:ring-violet-500 focus:border-transparent focus:outline-none"><?= htmlspecialchars($settingsMap['company_about'] ?? '') ?></textarea>
        </div>
        <div class="pt-2">
          <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-full text-sm font-medium transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ===================== TEAM TAB ===================== -->
  <div id="tab-team" class="<?= $activeTab !== 'team' ? 'hidden' : '' ?> space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h3 class="font-semibold text-gray-900">Team Members</h3>
          <p class="text-sm text-gray-500 mt-0.5"><?= count($teamMembers) ?> member<?= count($teamMembers) !== 1 ? 's' : '' ?> in your workspace</p>
        </div>
        <button onclick="openModal('invite-modal')" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
          Invite Member
        </button>
      </div>
      <div class="divide-y divide-gray-50">
        <?php if (empty($teamMembers)): ?>
        <p class="text-gray-500 text-sm py-4 text-center">No team members yet. Invite your first team member!</p>
        <?php else: foreach ($teamMembers as $member): ?>
        <div class="flex items-center justify-between py-4 first:pt-0 last:pb-0">
          <div class="flex items-center gap-3">
            <?php if ($member['avatar_url']): ?>
            <img src="<?= htmlspecialchars($member['avatar_url']) ?>" class="w-10 h-10 rounded-full object-cover">
            <?php else: ?>
            <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center text-violet-700 font-semibold text-sm">
              <?= strtoupper(substr($member['full_name'] ?? 'U', 0, 1)) ?>
            </div>
            <?php endif; ?>
            <div>
              <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($member['full_name']) ?></p>
              <p class="text-xs text-gray-500"><?= htmlspecialchars($member['email']) ?></p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-xs bg-violet-50 text-violet-700 px-2.5 py-1 rounded-full font-medium">
              <?= htmlspecialchars(str_replace('_', ' ', ucfirst($member['role_name'] ?? 'Member'))) ?>
            </span>
            <button onclick="removeTeamMember(<?= $member['id'] ?>, '<?= htmlspecialchars($member['full_name']) ?>')"
              class="text-gray-400 hover:text-red-500 transition-colors" title="Remove">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- Pending Invitations -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Pending Invitations</h3>
      <div id="pending-invites">
        <p class="text-sm text-gray-400 italic">No pending invitations.</p>
      </div>
    </div>
  </div>

  <!-- ===================== INTEGRATIONS TAB ===================== -->
  <div id="tab-integrations" class="<?= $activeTab !== 'integrations' ? 'hidden' : '' ?> space-y-6">

    <!-- Per-company key notice -->
    <div class="bg-violet-50 border border-violet-200 rounded-2xl px-5 py-4 flex gap-3 items-start">
      <svg class="w-5 h-5 text-violet-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
      <div>
        <p class="text-sm font-semibold text-violet-900">Your company API keys — billed to you</p>
        <p class="text-xs text-violet-700 mt-0.5">These keys are encrypted and stored securely. All AI usage is billed directly to your OpenAI/HeyGen accounts — the platform owner is not charged for your AI usage.</p>
      </div>
    </div>

    <!-- OpenAI -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <div>
          <h3 class="font-semibold text-gray-900">OpenAI</h3>
          <p class="text-xs text-gray-500 mt-0.5">Powers AI screening, evaluation, and letter generation</p>
        </div>
        <div class="ml-auto" id="openai-status-badge"></div>
      </div>
      <form id="openai-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">API Key</label>
          <div class="relative">
            <input type="password" name="openai_api_key" id="openai-key"
              value="<?= !empty($tenant['openai_api_key']) ? '••••••••••••' : '' ?>"
              placeholder="sk-..."
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 pr-11 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono">
            <button type="button" onclick="togglePassword('openai-key', this)"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
              <svg class="w-5 h-5 eye-closed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
            </button>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Model</label>
          <select name="openai_model" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
            <?php foreach (['gpt-4o-mini' => 'GPT-4o Mini — Fast & Cost-effective', 'gpt-4o' => 'GPT-4o — Recommended', 'gpt-4-turbo' => 'GPT-4 Turbo — Highest Capability'] as $m => $l): ?>
            <option value="<?= $m ?>" <?= ($tenant['openai_model'] ?? 'gpt-4o') === $m ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex items-center gap-3 pt-1">
          <button type="button" onclick="testOpenAI()" id="test-openai-btn"
            class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-full hover:bg-gray-50 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Test Connection
          </button>
          <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-medium transition-colors">Save</button>
        </div>
      </form>
    </div>

    <!-- HeyGen -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        </div>
        <div>
          <h3 class="font-semibold text-gray-900">HeyGen</h3>
          <p class="text-xs text-gray-500 mt-0.5">AI video avatar for automated interview sessions</p>
        </div>
        <div class="ml-auto" id="heygen-status-badge"></div>
      </div>
      <form id="heygen-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">API Key</label>
          <div class="relative">
            <input type="password" name="heygen_api_key" id="heygen-key"
              value="<?= !empty($tenant['heygen_api_key']) ? '••••••••••••' : '' ?>"
              placeholder="Your HeyGen API key"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 pr-11 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono">
            <button type="button" onclick="togglePassword('heygen-key', this)"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5 eye-closed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
            </button>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button type="button" onclick="testHeyGen()" id="test-heygen-btn"
            class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-full hover:bg-gray-50 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Test API
          </button>
          <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-medium transition-colors">Save</button>
        </div>
      </form>
      <!-- HeyGen Avatars -->
      <div id="heygen-avatars" class="mt-6 hidden">
        <h4 class="text-sm font-medium text-gray-700 mb-3">Available Avatars</h4>
        <div id="avatars-grid" class="grid grid-cols-3 sm:grid-cols-5 gap-3"></div>
      </div>
    </div>

    <!-- Email SMTP -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
          <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
        <div>
          <h3 class="font-semibold text-gray-900">Email (SMTP)</h3>
          <p class="text-xs text-gray-500 mt-0.5">Configure outgoing email for notifications and offers</p>
        </div>
      </div>
      <form id="smtp-form" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Host</label>
            <input type="text" name="smtp_host" value="<?= htmlspecialchars($settingsMap['smtp_host'] ?? '') ?>"
              placeholder="smtp.gmail.com"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Port</label>
            <input type="number" name="smtp_port" value="<?= htmlspecialchars($settingsMap['smtp_port'] ?? '587') ?>"
              placeholder="587"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
            <input type="text" name="smtp_username" value="<?= htmlspecialchars($settingsMap['smtp_username'] ?? '') ?>"
              placeholder="your@email.com"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
            <div class="relative">
              <input type="password" name="smtp_password" id="smtp-password"
                value="<?= strlen($settingsMap['smtp_password'] ?? '') > 0 ? '••••••••' : '' ?>"
                placeholder="App password or SMTP password"
                class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 pr-11 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
              <button type="button" onclick="togglePassword('smtp-password', this)"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 eye-closed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
              </button>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">From Name</label>
            <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($settingsMap['smtp_from_name'] ?? ($tenant['name'] ?? '')) ?>"
              placeholder="Acme Careers"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">From Email</label>
            <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($settingsMap['smtp_from_email'] ?? '') ?>"
              placeholder="careers@acme.com"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          </div>
        </div>
        <div class="flex items-center gap-3 pt-1">
          <button type="button" onclick="testSmtp()" id="test-smtp-btn"
            class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-full hover:bg-gray-50 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
            Test Email
          </button>
          <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-medium transition-colors">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ===================== BILLING TAB ===================== -->
  <?php
  $plan        = $tenant['plan'] ?? 'growth';
  $planLabels  = ['starter'=>'Starter','growth'=>'Growth','scale'=>'Scale','enterprise'=>'Enterprise'];
  $planPrices  = ['starter'=>'Free','growth'=>'$49/mo','scale'=>'$149/mo','enterprise'=>'Custom'];
  $planColors  = ['starter'=>'gray','growth'=>'violet','scale'=>'amber','enterprise'=>'emerald'];
  $planLimits  = [
    'starter'    => ['interviews'=>50,  'jobs'=>5,   'members'=>3,  'credits'=>100000],
    'growth'     => ['interviews'=>200, 'jobs'=>25,  'members'=>10, 'credits'=>500000],
    'scale'      => ['interviews'=>1000,'jobs'=>100, 'members'=>50, 'credits'=>2000000],
    'enterprise' => ['interviews'=>null,'jobs'=>null,'members'=>null,'credits'=>null],
  ];
  $limits = $planLimits[$plan] ?? $planLimits['growth'];
  $planColor = $planColors[$plan] ?? 'violet';
  // Usage (live counts with demo fallback)
  $monthStart = date('Y-m-01 00:00:00');
  $monthEnd   = date('Y-m-t 23:59:59');
  $usedInterviews = (int)($db->fetch("SELECT COUNT(*) c FROM interviews i JOIN applications a ON a.id=i.application_id WHERE a.tenant_id=? AND i.created_at BETWEEN ? AND ?", [$tid, $monthStart, $monthEnd])['c'] ?? 0);
  $usedJobs       = (int)($db->fetch("SELECT COUNT(*) c FROM jobs WHERE tenant_id=? AND status='published'", [$tid])['c'] ?? 12);
  $usedMembers    = count($teamMembers);
  $usedCredits    = (int)($db->fetch("SELECT SUM(tokens_used) c FROM ai_usage_logs WHERE tenant_id=? AND created_at BETWEEN ? AND ?", [$tid, $monthStart, $monthEnd])['c'] ?? 0);
  $invoices = $db->fetchAll("SELECT * FROM invoices WHERE tenant_id=? ORDER BY created_at DESC LIMIT 6", [$tid]) ?: [
    ['id'=>'INV-2026-006','period'=>'June 2026',  'amount'=>4900,'status'=>'paid'],
    ['id'=>'INV-2026-005','period'=>'May 2026',   'amount'=>4900,'status'=>'paid'],
    ['id'=>'INV-2026-004','period'=>'April 2026', 'amount'=>4900,'status'=>'paid'],
  ];
  ?>
  <div id="tab-billing" class="<?= $activeTab !== 'billing' ? 'hidden' : '' ?> space-y-6">

    <!-- Current Plan Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Current Plan</p>
          <div class="flex items-center gap-3">
            <h2 class="text-2xl font-bold text-gray-900"><?= $planLabels[$plan] ?? ucfirst($plan) ?></h2>
            <span class="bg-<?= $planColor ?>-100 text-<?= $planColor ?>-700 text-sm font-semibold px-3 py-1 rounded-full">
              <?= $planPrices[$plan] ?? 'Custom' ?>
            </span>
          </div>
          <p class="text-sm text-gray-500 mt-2">
            <?= match($plan) {
              'starter'    => 'Great for small teams exploring AI-powered hiring.',
              'growth'     => 'Everything you need for a growing recruitment operation.',
              'scale'      => 'High-volume hiring with advanced AI capabilities.',
              'enterprise' => 'Unlimited capacity with dedicated support and SLAs.',
              default      => 'AI-powered recruitment platform.'
            } ?>
          </p>
          <?php if (!empty($tenant['plan_expires_at'])): ?>
          <p class="text-xs text-amber-600 mt-2 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Renews <?= date('F j, Y', strtotime($tenant['plan_expires_at'])) ?>
          </p>
          <?php endif; ?>
        </div>
        <?php if ($plan !== 'enterprise'): ?>
        <button onclick="window.open('/billing/upgrade','_blank')"
          class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-full text-sm font-medium whitespace-nowrap flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
          Upgrade Plan
        </button>
        <?php else: ?>
        <button onclick="window.open('mailto:enterprise@hireai.com')"
          class="border border-gray-200 hover:bg-gray-50 text-gray-700 px-6 py-2.5 rounded-full text-sm font-medium whitespace-nowrap">
          Contact Sales
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Usage Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <?php
      $statsCards = [
        ['label'=>'AI Interviews', 'used'=>$usedInterviews, 'limit'=>$limits['interviews'], 'color'=>'violet',  'icon'=>'M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
        ['label'=>'Active Jobs',   'used'=>$usedJobs,       'limit'=>$limits['jobs'],       'color'=>'blue',    'icon'=>'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['label'=>'Team Members',  'used'=>$usedMembers,    'limit'=>$limits['members'],    'color'=>'emerald', 'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['label'=>'AI Credits',    'used'=>$usedCredits,    'limit'=>$limits['credits'],    'color'=>'amber',   'icon'=>'M13 10V3L4 14h7v7l9-11h-7z', 'format'=>'number'],
      ];
      foreach ($statsCards as $card):
        $isUnlimited = $card['limit'] === null;
        $pct = $isUnlimited ? 100 : ($card['limit'] > 0 ? min(100, round($card['used'] / $card['limit'] * 100)) : 0);
        $barColor = $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-' . $card['color'] . '-500');
        $usedDisplay  = isset($card['format']) ? number_format($card['used'])  : $card['used'];
        $limitDisplay = $isUnlimited ? '∞' : (isset($card['format']) ? number_format($card['limit']) : $card['limit']);
      ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
        <div class="flex items-center gap-2 mb-3">
          <div class="w-8 h-8 bg-<?= $card['color'] ?>-100 rounded-lg flex items-center justify-center">
            <svg class="w-4 h-4 text-<?= $card['color'] ?>-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $card['icon'] ?>"/>
            </svg>
          </div>
          <span class="text-xs font-medium text-gray-600 leading-tight"><?= $card['label'] ?></span>
        </div>
        <p class="text-xl font-bold text-gray-900"><?= $usedDisplay ?></p>
        <p class="text-xs text-gray-400 mb-2.5">of <?= $limitDisplay ?><?= $isUnlimited ? '' : ' this month' ?></p>
        <div class="w-full bg-gray-100 rounded-full h-1.5">
          <div class="<?= $isUnlimited ? 'bg-emerald-400' : $barColor ?> h-1.5 rounded-full transition-all" style="width:<?= $isUnlimited ? 100 : $pct ?>%"></div>
        </div>
        <p class="text-xs mt-1 <?= $isUnlimited ? 'text-emerald-600' : 'text-gray-400' ?>">
          <?= $isUnlimited ? 'Unlimited' : $pct . '% used' ?>
        </p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Invoices + Payment Method (side by side on lg) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Invoice History -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-gray-900">Invoice History</h3>
          <button onclick="window.open('/billing/portal','_blank')"
            class="text-sm text-violet-600 hover:text-violet-700 font-medium">
            Manage Billing &rarr;
          </button>
        </div>
        <?php if (empty($invoices)): ?>
        <p class="text-sm text-gray-400 text-center py-6">No invoices yet.</p>
        <?php else: ?>
        <div class="divide-y divide-gray-50">
          <?php foreach ($invoices as $inv):
            $stClass = match($inv['status'] ?? 'paid') {
              'paid'   => 'bg-emerald-100 text-emerald-700',
              'open'   => 'bg-amber-100 text-amber-700',
              'failed' => 'bg-red-100 text-red-700',
              default  => 'bg-gray-100 text-gray-600',
            };
          ?>
          <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
            <div>
              <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($inv['period'] ?? '') ?></p>
              <p class="text-xs text-gray-500"><?= htmlspecialchars($inv['id']) ?></p>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-sm font-semibold text-gray-900">$<?= number_format(($inv['amount'] ?? 0) / 100, 2) ?></span>
              <span class="text-xs px-2.5 py-0.5 rounded-full font-medium <?= $stClass ?>"><?= ucfirst($inv['status'] ?? 'paid') ?></span>
              <button onclick="window.open('/billing/invoice/<?= $inv['id'] ?>/pdf','_blank')"
                class="text-gray-400 hover:text-violet-600 p-1 rounded hover:bg-violet-50 transition-colors" title="Download PDF">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Payment Method -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-gray-900">Payment Method</h3>
          <button onclick="window.open('/billing/portal','_blank')" class="text-sm text-violet-600 hover:text-violet-700 font-medium">Update</button>
        </div>
        <?php if (!empty($settingsMap['stripe_last4'])): ?>
        <div class="p-4 bg-gray-50 rounded-xl flex items-center gap-3">
          <div class="w-10 h-7 bg-white border border-gray-200 rounded flex items-center justify-center text-xs font-bold text-blue-700">VISA</div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900">•••• <?= htmlspecialchars($settingsMap['stripe_last4']) ?></p>
            <p class="text-xs text-gray-500">Expires <?= htmlspecialchars($settingsMap['stripe_card_exp'] ?? '—') ?></p>
          </div>
          <span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-medium">Active</span>
        </div>
        <?php else: ?>
        <div class="text-center py-8">
          <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
          </div>
          <p class="text-sm text-gray-500 mb-3">No payment method on file</p>
          <button onclick="window.open('/billing/portal','_blank')"
            class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2 rounded-full text-sm font-medium">
            Add Card
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ===================== CAREER PAGE TAB ===================== -->
  <div id="tab-career" class="<?= $activeTab !== 'career' ? 'hidden' : '' ?> space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="font-semibold text-gray-900 mb-1">Public Career Page</h3>
      <p class="text-sm text-gray-500 mb-5">Customize how candidates discover your company and open roles</p>
      <form id="career-form" class="space-y-5">

        <!-- Enable toggle -->
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-100">
          <div>
            <p class="text-sm font-medium text-gray-900">Enable Public Career Page</p>
            <p class="text-xs text-gray-500 mt-0.5">Allow candidates to discover and apply to your open positions</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="career_page_enabled" value="1"
              <?= ($settingsMap['career_page_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
              class="sr-only peer" id="career-toggle">
            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-violet-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"></div>
          </label>
        </div>

        <!-- Public URL -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Public URL</label>
          <div class="flex items-stretch">
            <span class="inline-flex items-center px-3.5 text-sm text-gray-500 bg-gray-50 border border-r-0 border-gray-200 rounded-l-xl whitespace-nowrap">
              <?= htmlspecialchars(($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') . '/careers/') ?>
            </span>
            <input type="text" value="<?= htmlspecialchars($tenant['slug'] ?? '') ?>" readonly
              class="flex-1 border border-gray-200 rounded-r-xl px-3.5 py-2.5 text-sm bg-gray-50 text-gray-500 cursor-not-allowed min-w-0">
          </div>
          <p class="text-xs text-gray-400 mt-1">The URL slug is set by your super administrator.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Company Tagline</label>
            <input type="text" name="career_tagline" value="<?= htmlspecialchars($settingsMap['career_tagline'] ?? '') ?>"
              placeholder="Join our team and shape the future"
              class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Theme Color</label>
            <div class="flex items-center gap-3">
              <input type="color" name="career_theme_color" id="career-color-picker"
                value="<?= htmlspecialchars($settingsMap['career_theme_color'] ?? '#7C3AED') ?>"
                class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer p-1" oninput="syncColorHex(this)">
              <input type="text" id="career-color-hex"
                value="<?= htmlspecialchars($settingsMap['career_theme_color'] ?? '#7C3AED') ?>"
                placeholder="#7C3AED" maxlength="7"
                class="flex-1 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:outline-none"
                oninput="syncColorPicker(this)">
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Header Image URL</label>
          <input type="url" name="career_header_image" value="<?= htmlspecialchars($settingsMap['career_header_image'] ?? '') ?>"
            placeholder="https://yourcdn.com/careers-banner.jpg"
            class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          <p class="text-xs text-gray-400 mt-1">Recommended: 1440×480px, JPEG or WebP</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">About (for candidates)</label>
          <p class="text-xs text-gray-500 mb-2">This is separate from your general company about — write in a candidate-friendly tone</p>
          <textarea name="career_about" rows="5" placeholder="Tell candidates what makes your company a great place to work, your culture, perks, and team..."
            class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm resize-none focus:ring-2 focus:ring-violet-500 focus:outline-none"><?= htmlspecialchars($settingsMap['career_about'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center gap-3 pt-1">
          <button type="button" onclick="previewCareerPage()"
            class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-full hover:bg-gray-50 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            Preview Page
          </button>
          <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-full text-sm font-medium transition-colors">Save Settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ===================== INVITE MEMBER MODAL ===================== -->
<div id="invite-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-semibold text-gray-900">Invite Team Member</h3>
      <button onclick="closeModal('invite-modal')" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="invite-form" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
        <input type="email" name="email" required placeholder="colleague@company.com"
          class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name (optional)</label>
        <input type="text" name="name" placeholder="Jane Smith"
          class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Role</label>
        <select name="role" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          <option value="recruiter">Recruiter</option>
          <option value="hiring_manager">Hiring Manager</option>
          <option value="interviewer">Interviewer</option>
          <option value="hr_admin">HR Admin</option>
          <option value="viewer">Viewer</option>
        </select>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('invite-modal')" class="flex-1 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-full transition-colors">Cancel</button>
        <button type="submit" class="flex-1 py-2.5 text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 rounded-full transition-colors">Send Invitation</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => {
    const active = b.dataset.tab === tab;
    b.classList.toggle('bg-white', active);
    b.classList.toggle('text-gray-900', active);
    b.classList.toggle('shadow-sm', active);
    b.classList.toggle('text-gray-500', !active);
    b.classList.toggle('hover:text-gray-700', !active);
  });
  ['general', 'team', 'integrations', 'billing', 'career'].forEach(t => {
    const el = document.getElementById('tab-' + t);
    if (el) el.classList.toggle('hidden', t !== tab);
  });
  history.replaceState(null, '', '?tab=' + tab);
}

// ── Password visibility toggle ─────────────────────────────────────────────
function togglePassword(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.querySelector('svg').style.opacity = inp.type === 'text' ? '0.5' : '1';
}

// ── Modal helpers ──────────────────────────────────────────────────────────
function openModal(id) {
  const m = document.getElementById(id);
  m.classList.remove('hidden');
  m.classList.add('flex');
}
function closeModal(id) {
  const m = document.getElementById(id);
  m.classList.add('hidden');
  m.classList.remove('flex');
}
document.querySelectorAll('#invite-modal').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
});

// ── Generic AJAX helper (expects window.ajax from layout) ──────────────────
async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify(data)
  });
  return res.json();
}

// ── Toast helper ───────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
  if (window.showToast) { window.showToast(msg, type); return; }
  const t = document.createElement('div');
  t.className = `fixed top-5 right-5 z-[9999] px-4 py-3 rounded-xl shadow-lg text-sm font-medium text-white transition-all ${type === 'success' ? 'bg-emerald-600' : 'bg-red-600'}`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3200);
}

// ── Spinner helper ─────────────────────────────────────────────────────────
function setLoading(btn, loading, label = '') {
  if (!btn) return;
  btn.disabled = loading;
  if (loading) { btn._orig = btn.innerHTML; if (label) btn.innerHTML = `<svg class="animate-spin w-4 h-4 inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>${label}`; }
  else { btn.innerHTML = btn._orig || btn.innerHTML; }
}

// ── General settings save ──────────────────────────────────────────────────
document.getElementById('general-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type="submit"]');
  setLoading(btn, true, 'Saving...');
  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd.entries());
  try {
    const res = await apiPost('/api/v1/settings', { action: 'save_general', ...body });
    toast(res.message || 'Settings saved', res.ok || res.success ? 'success' : 'error');
  } catch { toast('Failed to save settings', 'error'); }
  finally { setLoading(btn, false); }
});

// ── OpenAI save ────────────────────────────────────────────────────────────
document.getElementById('openai-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type="submit"]');
  setLoading(btn, true, 'Saving...');
  const fd = new FormData(e.target);
  const key = fd.get('openai_api_key') || '';
  const model = fd.get('openai_model') || 'gpt-4o';
  try {
    // Only send key if user typed something (not the masked placeholder)
    const payload = { action: 'save_api_keys', openai_model: model };
    if (key && !key.includes('••')) payload.openai = key;
    const res = await apiPost('/api/v1/settings', payload);
    toast(res.message || 'OpenAI settings saved', res.ok || res.success ? 'success' : 'error');
  } catch { toast('Failed to save', 'error'); }
  finally { setLoading(btn, false); }
});

async function testOpenAI() {
  const btn = document.getElementById('test-openai-btn');
  setLoading(btn, true, 'Testing...');
  try {
    const key = document.getElementById('openai-key').value;
    const payload = { action: 'test_openai' };
    if (key && !key.includes('••')) payload.key = key;
    const res = await apiPost('/api/v1/settings', payload);
    const badge = document.getElementById('openai-status-badge');
    if (res.ok || res.success) {
      badge.innerHTML = '<span class="text-xs bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full font-medium flex items-center gap-1"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full inline-block"></span>Connected</span>';
      toast('OpenAI connected successfully', 'success');
    } else {
      badge.innerHTML = '<span class="text-xs bg-red-100 text-red-700 px-2.5 py-1 rounded-full font-medium">Failed</span>';
      toast(res.message || 'Connection failed', 'error');
    }
  } catch { toast('Connection test failed', 'error'); }
  finally { setLoading(btn, false); }
}

// ── HeyGen save ────────────────────────────────────────────────────────────
document.getElementById('heygen-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type="submit"]');
  setLoading(btn, true, 'Saving...');
  const fd = new FormData(e.target);
  const key = fd.get('heygen_api_key') || '';
  try {
    const payload = { action: 'save_api_keys' };
    if (key && !key.includes('••')) payload.heygen = key;
    const res = await apiPost('/api/v1/settings', payload);
    toast(res.message || 'HeyGen settings saved', res.ok || res.success ? 'success' : 'error');
  } catch { toast('Failed to save', 'error'); }
  finally { setLoading(btn, false); }
});

async function testHeyGen() {
  const btn = document.getElementById('test-heygen-btn');
  setLoading(btn, true, 'Testing...');
  try {
    const key = document.getElementById('heygen-key').value;
    const res = await apiPost('/api/v1/settings', { action: 'test_heygen', key });
    const badge = document.getElementById('heygen-status-badge');
    if (res.ok || res.success) {
      badge.innerHTML = '<span class="text-xs bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full font-medium flex items-center gap-1"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full inline-block"></span>Connected</span>';
      toast('HeyGen API connected', 'success');
      // Load avatars
      if (res.avatars && res.avatars.length) {
        const grid = document.getElementById('avatars-grid');
        const wrap = document.getElementById('heygen-avatars');
        wrap.classList.remove('hidden');
        grid.innerHTML = res.avatars.map(av => `
          <div class="flex flex-col items-center gap-1 cursor-pointer group" title="${av.name || av.avatar_id}">
            <img src="${av.preview_image_url || ''}" class="w-16 h-16 rounded-xl object-cover border-2 border-transparent group-hover:border-violet-400 transition-all" onerror="this.src='https://ui-avatars.com/api/?name=AI&background=7C3AED&color=fff&size=64'">
            <span class="text-xs text-gray-500 truncate w-full text-center">${av.name || 'Avatar'}</span>
          </div>
        `).join('');
      }
    } else {
      badge.innerHTML = '<span class="text-xs bg-red-100 text-red-700 px-2.5 py-1 rounded-full font-medium">Failed</span>';
      toast(res.message || 'HeyGen connection failed', 'error');
    }
  } catch { toast('Connection test failed', 'error'); }
  finally { setLoading(btn, false); }
}

// ── SMTP save + test ───────────────────────────────────────────────────────
document.getElementById('smtp-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type="submit"]');
  setLoading(btn, true, 'Saving...');
  const fd = new FormData(e.target);
  try {
    const res = await apiPost('/api/v1/settings', { action: 'save_smtp', ...Object.fromEntries(fd.entries()) });
    toast(res.message || 'SMTP settings saved', res.ok || res.success ? 'success' : 'error');
  } catch { toast('Failed to save', 'error'); }
  finally { setLoading(btn, false); }
});

async function testSmtp() {
  const btn = document.getElementById('test-smtp-btn');
  setLoading(btn, true, 'Sending...');
  const fd = new FormData(document.getElementById('smtp-form'));
  try {
    const res = await apiPost('/api/v1/settings', { action: 'test_smtp', ...Object.fromEntries(fd.entries()) });
    toast(res.message || (res.ok ? 'Test email sent!' : 'SMTP test failed'), res.ok || res.success ? 'success' : 'error');
  } catch { toast('SMTP test failed', 'error'); }
  finally { setLoading(btn, false); }
}

// ── Career page save ───────────────────────────────────────────────────────
document.getElementById('career-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type="submit"]');
  setLoading(btn, true, 'Saving...');
  const fd = new FormData(e.target);
  const body = Object.fromEntries(fd.entries());
  body.career_page_enabled = document.getElementById('career-toggle').checked ? '1' : '0';
  body.career_theme_color = document.getElementById('career-color-hex').value;
  try {
    const res = await apiPost('/api/v1/settings', { action: 'save_career', ...body });
    toast(res.message || 'Career page settings saved', res.ok || res.success ? 'success' : 'error');
  } catch { toast('Failed to save', 'error'); }
  finally { setLoading(btn, false); }
});

function previewCareerPage() {
  const slug = '<?= htmlspecialchars($tenant['slug'] ?? '') ?>';
  if (!slug) { toast('No slug configured', 'error'); return; }
  window.open('/careers/' + slug, '_blank');
}

// ── Color picker sync ──────────────────────────────────────────────────────
function syncColorHex(picker) {
  document.getElementById('career-color-hex').value = picker.value.toUpperCase();
}
function syncColorPicker(hex) {
  if (/^#[0-9A-Fa-f]{6}$/.test(hex.value)) {
    document.getElementById('career-color-picker').value = hex.value;
  }
}

// ── Team member removal ────────────────────────────────────────────────────
async function removeTeamMember(userId, name) {
  if (!confirm(`Remove ${name} from your workspace?`)) return;
  try {
    const res = await apiPost('/api/v1/admin', { action: 'remove_user', user_id: userId });
    if (res.ok || res.success) { toast(name + ' removed', 'success'); setTimeout(() => location.reload(), 800); }
    else toast(res.message || 'Failed to remove', 'error');
  } catch { toast('Failed to remove team member', 'error'); }
}

// ── Invite form submit ─────────────────────────────────────────────────────
document.getElementById('invite-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type="submit"]');
  setLoading(btn, true, 'Sending...');
  const fd = new FormData(e.target);
  try {
    const res = await apiPost('/api/v1/admin', { action: 'invite_user', ...Object.fromEntries(fd.entries()) });
    if (res.ok || res.success) {
      toast('Invitation sent to ' + fd.get('email'), 'success');
      closeModal('invite-modal');
      e.target.reset();
    } else {
      toast(res.message || 'Failed to send invitation', 'error');
    }
  } catch { toast('Failed to send invitation', 'error'); }
  finally { setLoading(btn, false); }
});
</script>
<?php $content = ob_get_clean(); require VIEWS_PATH . '/layouts/app.php'; ?>
