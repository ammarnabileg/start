<?php
/**
 * Super Admin – System Settings
 */
$db = Database::getInstance();

// Load current settings from a settings table (key => value)
$rawSettings = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings") ?? [];
$cfg = array_column($rawSettings, 'setting_value', 'setting_key');

$activeTab = $_GET['tab'] ?? 'general';
$tabs = [
    'general'  => 'General',
    'ai'       => 'AI Config',
    'email'    => 'Email',
    'security' => 'Security',
    'billing'  => 'Billing',
];
?>

<!-- Tab Nav ---------------------------------------------------------------->
<div class="flex gap-1 mb-6 bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 overflow-x-auto">
  <?php foreach ($tabs as $key => $label): ?>
  <button onclick="switchTab('<?= $key ?>')"
          id="tab-btn-<?= $key ?>"
          class="tab-btn flex-1 min-w-fit px-4 py-2 rounded-xl text-sm font-medium transition-colors whitespace-nowrap
                 <?= $activeTab === $key ? 'bg-violet-600 text-white' : 'text-gray-600 hover:bg-gray-50' ?>">
    <?= $label ?>
  </button>
  <?php endforeach; ?>
</div>

<!-- ═══ General ══════════════════════════════════════════════════════════════ -->
<div id="tab-general" class="tab-panel <?= $activeTab !== 'general' ? 'hidden' : '' ?>">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-5">General Settings</h2>
    <form onsubmit="saveSection(event,'general')">
      <div class="space-y-5 max-w-2xl">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Platform Name</label>
          <input type="text" name="platform_name" value="<?= htmlspecialchars($cfg['platform_name'] ?? 'HireAI') ?>"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          <p class="text-xs text-gray-400 mt-1">Displayed in the sidebar, emails, and browser title.</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Contact Email</label>
          <input type="email" name="contact_email" value="<?= htmlspecialchars($cfg['contact_email'] ?? 'support@hireai.com') ?>"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Trial Days</label>
            <input type="number" name="trial_days" value="<?= (int)($cfg['trial_days'] ?? 14) ?>" min="0" max="90"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Default Plan</label>
            <select name="default_plan" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white">
              <?php foreach (['starter'=>'Starter','professional'=>'Professional','enterprise'=>'Enterprise'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($cfg['default_plan'] ?? 'starter') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Support URL</label>
          <input type="url" name="support_url" value="<?= htmlspecialchars($cfg['support_url'] ?? '') ?>"
                 placeholder="https://support.example.com"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="maintenance_mode" value="1" <?= !empty($cfg['maintenance_mode']) ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
            <span class="text-sm font-medium text-gray-700">Maintenance Mode</span>
          </label>
          <p class="text-xs text-gray-400 mt-1 ml-7">Prevent non-super-admin users from logging in.</p>
        </div>
      </div>
      <div class="mt-6 pt-5 border-t border-gray-100 flex gap-3">
        <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-full text-sm font-medium transition-colors">
          Save General Settings
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ AI Config ════════════════════════════════════════════════════════════ -->
<div id="tab-ai" class="tab-panel <?= $activeTab !== 'ai' ? 'hidden' : '' ?>">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-5">AI Configuration</h2>
    <form onsubmit="saveSection(event,'ai')">
      <div class="space-y-5 max-w-2xl">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">OpenAI Model</label>
          <select name="openai_model" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white">
            <?php
            $models = ['gpt-4o'=>'GPT-4o (Recommended)','gpt-4o-mini'=>'GPT-4o Mini (Cost-efficient)','gpt-4-turbo'=>'GPT-4 Turbo','gpt-4.1'=>'GPT-4.1','gpt-4.1-mini'=>'GPT-4.1 Mini','gpt-3.5-turbo'=>'GPT-3.5 Turbo (Legacy)'];
            $cur = $cfg['openai_model'] ?? 'gpt-4o';
            foreach ($models as $v => $l): ?>
            <option value="<?= $v ?>" <?= $cur === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Tokens / Request</label>
            <input type="number" name="ai_max_tokens" value="<?= (int)($cfg['ai_max_tokens'] ?? 4096) ?>" min="256" max="32000" step="256"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Temperature</label>
            <input type="number" name="ai_temperature" value="<?= number_format((float)($cfg['ai_temperature'] ?? 0.7), 1) ?>" min="0" max="2" step="0.1"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Monthly Token Limit / Company</label>
            <input type="number" name="ai_monthly_token_limit" value="<?= (int)($cfg['ai_monthly_token_limit'] ?? 500000) ?>" min="0" step="10000"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
            <p class="text-xs text-gray-400 mt-1">Set 0 for unlimited.</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Rate Limit (requests/min)</label>
            <input type="number" name="ai_rate_limit_rpm" value="<?= (int)($cfg['ai_rate_limit_rpm'] ?? 60) ?>" min="1"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">OpenAI API Key</label>
          <input type="password" name="openai_api_key" placeholder="sk-…"
                 value="<?= !empty($cfg['openai_api_key']) ? '••••••••••••••••' : '' ?>"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          <p class="text-xs text-gray-400 mt-1">Leave blank to keep the existing key.</p>
        </div>
        <div>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="ai_enabled" value="1" <?= !empty($cfg['ai_enabled']) || !isset($cfg['ai_enabled']) ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
            <span class="text-sm font-medium text-gray-700">AI Features Enabled</span>
          </label>
        </div>
      </div>
      <div class="mt-6 pt-5 border-t border-gray-100">
        <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-full text-sm font-medium transition-colors">
          Save AI Config
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ Email ════════════════════════════════════════════════════════════════ -->
<div id="tab-email" class="tab-panel <?= $activeTab !== 'email' ? 'hidden' : '' ?>">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-5">Email Configuration</h2>
    <form onsubmit="saveSection(event,'email')">
      <div class="space-y-5 max-w-2xl">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Host</label>
            <input type="text" name="smtp_host" value="<?= htmlspecialchars($cfg['smtp_host'] ?? 'smtp.mailgun.org') ?>"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Port</label>
            <input type="number" name="smtp_port" value="<?= (int)($cfg['smtp_port'] ?? 587) ?>"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Username</label>
            <input type="text" name="smtp_user" value="<?= htmlspecialchars($cfg['smtp_user'] ?? '') ?>"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Password</label>
            <input type="password" name="smtp_pass" placeholder="••••••••"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">From Name</label>
            <input type="text" name="mail_from_name" value="<?= htmlspecialchars($cfg['mail_from_name'] ?? 'HireAI Platform') ?>"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">From Address</label>
            <input type="email" name="mail_from_address" value="<?= htmlspecialchars($cfg['mail_from_address'] ?? 'noreply@hireai.com') ?>"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Encryption</label>
          <select name="smtp_encryption" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white max-w-xs">
            <option value="tls" <?= ($cfg['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Recommended)</option>
            <option value="ssl" <?= ($cfg['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
            <option value="none" <?= ($cfg['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
          </select>
        </div>
        <div class="flex gap-3">
          <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-full text-sm font-medium transition-colors">
            Save Email Settings
          </button>
          <button type="button" onclick="sendTestEmail()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2.5 rounded-full text-sm font-medium transition-colors">
            Send Test Email
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ═══ Security ═════════════════════════════════════════════════════════════ -->
<div id="tab-security" class="tab-panel <?= $activeTab !== 'security' ? 'hidden' : '' ?>">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-5">Security Settings</h2>
    <form onsubmit="saveSection(event,'security')">
      <div class="space-y-5 max-w-2xl">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">JWT Expiry (minutes)</label>
            <input type="number" name="jwt_expiry" value="<?= (int)($cfg['jwt_expiry'] ?? 60) ?>" min="5"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Session Timeout (minutes)</label>
            <input type="number" name="session_timeout" value="<?= (int)($cfg['session_timeout'] ?? 120) ?>" min="5"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Max Login Attempts</label>
            <input type="number" name="max_login_attempts" value="<?= (int)($cfg['max_login_attempts'] ?? 5) ?>" min="1" max="20"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Lockout Duration (minutes)</label>
            <input type="number" name="lockout_duration" value="<?= (int)($cfg['lockout_duration'] ?? 30) ?>" min="1"
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">IP Whitelist for Admin</label>
          <textarea name="ip_whitelist" rows="4" placeholder="One IP per line:&#10;192.168.1.1&#10;10.0.0.0/24"
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none resize-none"><?= htmlspecialchars($cfg['ip_whitelist'] ?? '') ?></textarea>
          <p class="text-xs text-gray-400 mt-1">Leave blank to allow any IP. CIDR ranges supported.</p>
        </div>
        <div>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="force_2fa_admin" value="1" <?= !empty($cfg['force_2fa_admin']) ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
            <span class="text-sm font-medium text-gray-700">Require 2FA for Admin Accounts</span>
          </label>
        </div>
        <div>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="log_all_api_calls" value="1" <?= !empty($cfg['log_all_api_calls']) ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
            <span class="text-sm font-medium text-gray-700">Log All API Calls</span>
          </label>
          <p class="text-xs text-gray-400 mt-1 ml-7">May impact performance on high-traffic plans.</p>
        </div>
      </div>
      <div class="mt-6 pt-5 border-t border-gray-100">
        <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-full text-sm font-medium transition-colors">
          Save Security Settings
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ Billing ══════════════════════════════════════════════════════════════ -->
<div id="tab-billing" class="tab-panel <?= $activeTab !== 'billing' ? 'hidden' : '' ?>">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-5">Billing Settings</h2>
    <form onsubmit="saveSection(event,'billing')">
      <div class="space-y-5 max-w-2xl">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Stripe Publishable Key</label>
          <input type="text" name="stripe_pk" value="<?= htmlspecialchars($cfg['stripe_pk'] ?? '') ?>" placeholder="pk_live_…"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Stripe Secret Key</label>
          <input type="password" name="stripe_sk" placeholder="sk_live_…"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Stripe Webhook Secret</label>
          <input type="password" name="stripe_webhook_secret" placeholder="whsec_…"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
        </div>

        <!-- Plan pricing -->
        <div class="border-t border-gray-100 pt-5">
          <h3 class="text-sm font-semibold text-gray-800 mb-4">Plan Pricing (USD/month)</h3>
          <div class="grid grid-cols-3 gap-4">
            <?php foreach (['starter'=>'Starter','professional'=>'Professional','enterprise'=>'Enterprise'] as $v => $l): ?>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5"><?= $l ?></label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                <input type="number" name="plan_price_<?= $v ?>" step="0.01" min="0"
                       value="<?= number_format((float)($cfg['plan_price_' . $v] ?? 0), 2) ?>"
                       class="w-full border border-gray-200 rounded-xl pl-7 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none">
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="billing_enabled" value="1" <?= !empty($cfg['billing_enabled']) ? 'checked' : '' ?>
                   class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
            <span class="text-sm font-medium text-gray-700">Billing / Stripe Enabled</span>
          </label>
        </div>
      </div>
      <div class="mt-6 pt-5 border-t border-gray-100">
        <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-full text-sm font-medium transition-colors">
          Save Billing Settings
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Tab switching ────────────────────────────────────────────────────────────
function switchTab(key) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.classList.remove('bg-violet-600', 'text-white');
    b.classList.add('text-gray-600', 'hover:bg-gray-50');
  });
  document.getElementById('tab-' + key).classList.remove('hidden');
  const btn = document.getElementById('tab-btn-' + key);
  btn.classList.add('bg-violet-600', 'text-white');
  btn.classList.remove('text-gray-600', 'hover:bg-gray-50');
  history.replaceState(null, '', '?tab=' + key);
}

// ── Save sections ────────────────────────────────────────────────────────────
async function saveSection(e, section) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const data = { section };
  for (const [k, v] of fd.entries()) data[k] = v;

  try {
    const r = await fetch('/api/v1/admin?action=save_settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data)
    });
    const d = await r.json();
    if (d.ok) showToast('Settings saved.', 'success');
    else showToast(d.message || 'Failed to save settings.', 'error');
  } catch (err) {
    showToast('Request failed.', 'error');
  }
}

async function sendTestEmail() {
  const email = prompt('Send test email to:');
  if (!email) return;
  try {
    const r = await fetch('/api/v1/admin?action=test_email', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ email })
    });
    const d = await r.json();
    showToast(d.ok ? 'Test email sent!' : (d.message || 'Failed.'), d.ok ? 'success' : 'error');
  } catch (err) {
    showToast('Request failed.', 'error');
  }
}
</script>
