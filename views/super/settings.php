<?php /** @var array $settings @var Request $req */ ?>
<meta name="csrf" content="<?= htmlspecialchars($req->csrf()) ?>">

<div class="max-w-2xl space-y-6">
  <h1 class="text-2xl font-bold text-gray-900">Platform Settings</h1>
  <div id="flash-msg" class="hidden rounded-lg p-4 text-sm font-medium"></div>

  <form id="settings-form" class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
      <h2 class="font-semibold text-gray-900">General</h2>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Platform Name</label>
        <input type="text" name="platform_name" value="<?= htmlspecialchars($settings['platform_name'] ?? 'RecruitAI') ?>"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Support Email</label>
        <input type="email" name="support_email" value="<?= htmlspecialchars($settings['support_email'] ?? '') ?>"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
      <h2 class="font-semibold text-gray-900">Default AI Model</h2>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Default OpenAI Model</label>
        <select name="openai_model" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="gpt-4o-mini" <?= ($settings['openai_model']??'')=='gpt-4o-mini'?'selected':'' ?>>GPT-4o Mini (Fast & Cheap)</option>
          <option value="gpt-4o" <?= ($settings['openai_model']??'')=='gpt-4o'?'selected':'' ?>>GPT-4o (Most Capable)</option>
        </select>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
      <h2 class="font-semibold text-gray-900">SMTP (Email)</h2>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
          <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
          <input type="number" name="smtp_port" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">SMTP User</label>
          <input type="text" name="smtp_user" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
          <input type="password" name="smtp_pass" value="<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
          <input type="email" name="smtp_from" value="<?= htmlspecialchars($settings['smtp_from'] ?? '') ?>"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
          <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($settings['smtp_from_name'] ?? '') ?>"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
    </div>

    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700">Save Settings</button>
  </form>
</div>

<script>
const csrf = document.querySelector('meta[name=csrf]').content;
document.getElementById('settings-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const r = await fetch('/api/v1/super/settings', {
    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
    body: JSON.stringify(Object.fromEntries(fd))
  });
  const d = await r.json();
  const el = document.getElementById('flash-msg');
  el.textContent = d.message || (d.ok ? 'Settings saved!' : 'Error saving settings');
  el.className = 'rounded-lg p-4 text-sm font-medium ' + (d.ok ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200');
  el.classList.remove('hidden');
  setTimeout(() => el.classList.add('hidden'), 3000);
});
</script>
