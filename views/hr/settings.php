<?php
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
    <?php foreach (['general' => 'General', 'team' => 'Team', 'integrations' => 'Integrations', 'career' => 'Career Page'] as $k => $v): ?>
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
              value="<?= strlen($settingsMap['openai_api_key'] ?? '') > 4 ? '••••••••' . substr($settingsMap['openai_api_key'], -4) : '' ?>"
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
            <option value="<?= $m ?>" <?= ($settingsMap['openai_model'] ?? 'gpt-4o-mini') === $m ? 'selected' : '' ?>><?= $l ?></option>
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
              value="<?= strlen($settingsMap['heygen_api_key'] ?? '') > 4 ? '••••••••' . substr($settingsMap['heygen_api_key'], -4) : '' ?>"
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
  ['general', 'team', 'integrations', 'career'].forEach(t => {
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
  try {
    const res = await apiPost('/api/v1/settings', { action: 'save_openai', api_key: fd.get('openai_api_key'), model: fd.get('openai_model') });
    toast(res.message || 'OpenAI settings saved', res.ok || res.success ? 'success' : 'error');
  } catch { toast('Failed to save', 'error'); }
  finally { setLoading(btn, false); }
});

async function testOpenAI() {
  const btn = document.getElementById('test-openai-btn');
  setLoading(btn, true, 'Testing...');
  try {
    const key = document.getElementById('openai-key').value;
    const res = await apiPost('/api/v1/settings', { action: 'test_openai', key });
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
  try {
    const res = await apiPost('/api/v1/settings', { action: 'save_heygen', api_key: fd.get('heygen_api_key') });
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
