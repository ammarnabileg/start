<?php
$pageTitle = 'Settings';
$db     = Database::getInstance();
$tid    = Auth::user()['tenant_id'];
$tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tid]) ?: [];
$settings = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE tenant_id = ?", [$tid]) ?: [];
$s = array_column($settings, 'setting_value', 'setting_key');
$activeTab = $_GET['tab'] ?? 'general';
?>

<div class="max-w-4xl mx-auto">
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Company Settings</h1>
    <p class="text-sm text-gray-500 mt-1">Manage your company profile and integrations</p>
  </div>

  <!-- Tabs -->
  <div class="flex gap-1 bg-gray-100 rounded-xl p-1 mb-6">
    <?php foreach (['general'=>'General','integrations'=>'Integrations','career'=>'Career Page','billing'=>'Billing'] as $k=>$v): ?>
    <button onclick="switchTab('<?=$k?>')" data-tab="<?=$k?>"
      class="flex-1 py-2 text-sm font-medium rounded-lg transition-colors <?=$activeTab===$k?'bg-white text-gray-900 shadow-sm':'text-gray-500 hover:text-gray-700'?> tab-btn">
      <?=$v?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- General -->
  <div id="tab-general" class="<?= $activeTab !== 'general' ? 'hidden' : '' ?>">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Company Profile</h3>
      <form id="general-form" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($tenant['name']??'') ?>" required
              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Industry</label>
            <select name="industry" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
              <?php foreach (['Technology','Finance','Healthcare','Education','Retail','Manufacturing','Media','Legal','Other'] as $ind): ?>
              <option value="<?=$ind?>" <?= ($s['company_industry']??'')===$ind?'selected':'' ?>><?=$ind?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Company Size</label>
            <select name="size" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
              <?php foreach (['1-10','11-50','51-200','201-500','500+'] as $sz): ?>
              <option value="<?=$sz?>" <?= ($s['company_size']??'')===$sz?'selected':'' ?>><?=$sz?> employees</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Website URL</label>
            <input type="url" name="website" value="<?= htmlspecialchars($s['company_website']??'') ?>"
              placeholder="https://yourcompany.com"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">About Company</label>
          <textarea name="about" rows="3" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-violet-500 focus:outline-none"
            placeholder="Brief description of your company..."><?= htmlspecialchars($s['company_about']??'') ?></textarea>
        </div>
        <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2 rounded-full text-sm font-medium">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- Integrations -->
  <div id="tab-integrations" class="<?= $activeTab !== 'integrations' ? 'hidden' : '' ?> space-y-6">
    <!-- OpenAI -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <div>
          <h3 class="font-semibold text-gray-900">OpenAI</h3>
          <p class="text-xs text-gray-500">Powers all AI features on the platform</p>
        </div>
      </div>
      <form id="openai-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
          <div class="relative">
            <input type="password" name="openai_key" id="openai-key" value="<?= strlen($s['openai_api_key']??'') > 4 ? '••••••••'.substr($s['openai_api_key'],-4) : '' ?>"
              placeholder="sk-..."
              class="w-full border border-gray-200 rounded-xl px-3 py-2 pr-10 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
            <button type="button" onclick="togglePassword('openai-key',this)" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
          <select name="openai_model" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
            <?php foreach (['gpt-4o-mini'=>'GPT-4o Mini (Fast & Affordable)','gpt-4o'=>'GPT-4o (Recommended)','gpt-4-turbo'=>'GPT-4 Turbo'] as $m=>$l): ?>
            <option value="<?=$m?>" <?= ($s['openai_model']??'gpt-4o-mini')===$m?'selected':'' ?>><?=$l?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex gap-3">
          <button type="button" onclick="testOpenAI()" class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-full hover:bg-gray-50">Test Connection</button>
          <button type="submit" class="px-4 py-2 text-sm font-medium bg-violet-600 text-white rounded-full hover:bg-violet-700">Save</button>
        </div>
      </form>
    </div>

    <!-- HeyGen -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 bg-violet-100 rounded-xl flex items-center justify-center">
          <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        </div>
        <div>
          <h3 class="font-semibold text-gray-900">HeyGen</h3>
          <p class="text-xs text-gray-500">AI video avatar for video interviews</p>
        </div>
      </div>
      <form id="heygen-form" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
          <div class="relative">
            <input type="password" name="heygen_key" id="heygen-key" value="<?= strlen($s['heygen_api_key']??'') > 4 ? '••••••••'.substr($s['heygen_api_key'],-4) : '' ?>"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 pr-10 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
            <button type="button" onclick="togglePassword('heygen-key',this)" class="absolute right-3 top-2.5 text-gray-400">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
        </div>
        <div class="flex gap-3">
          <button type="button" onclick="testHeyGen()" class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-full hover:bg-gray-50">Test Connection</button>
          <button type="submit" class="px-4 py-2 text-sm font-medium bg-violet-600 text-white rounded-full hover:bg-violet-700">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Career Page -->
  <div id="tab-career" class="<?= $activeTab !== 'career' ? 'hidden' : '' ?>">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Career Page Settings</h3>
      <form id="career-form" class="space-y-4">
        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
          <div>
            <p class="font-medium text-gray-900 text-sm">Enable Public Career Page</p>
            <p class="text-xs text-gray-500">Let candidates discover and apply to your jobs</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="career_page_enabled" value="1" <?= ($s['career_page_enabled']??'0')==='1'?'checked':'' ?> class="sr-only peer">
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"></div>
          </label>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Public URL</label>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-l-xl px-3 py-2 text-sm whitespace-nowrap"><?= htmlspecialchars(($_SERVER['HTTP_HOST']??'yourdomain.com') . '/careers/') ?></span>
            <input type="text" value="<?= htmlspecialchars($tenant['slug']??'') ?>" readonly
              class="flex-1 border border-gray-200 rounded-r-xl px-3 py-2 text-sm bg-gray-50 text-gray-500">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tagline for Candidates</label>
          <input type="text" name="career_tagline" value="<?= htmlspecialchars($s['career_tagline']??'') ?>"
            placeholder="Join our team and shape the future"
            class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">About (for candidates)</label>
          <textarea name="career_about" rows="4" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-violet-500 focus:outline-none"
            placeholder="Tell candidates what makes your company a great place to work..."><?= htmlspecialchars($s['career_about']??'') ?></textarea>
        </div>
        <div class="flex gap-3">
          <a href="/careers/<?= htmlspecialchars($tenant['slug']??'') ?>" target="_blank"
            class="px-4 py-2 text-sm font-medium border border-gray-200 rounded-full hover:bg-gray-50">Preview Page</a>
          <button type="submit" class="px-4 py-2 text-sm font-medium bg-violet-600 text-white rounded-full hover:bg-violet-700">Save Settings</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Billing stub -->
  <div id="tab-billing" class="<?= $activeTab !== 'billing' ? 'hidden' : '' ?>">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
      <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
      </div>
      <h3 class="font-semibold text-gray-900 mb-2">Plan: <?= ucfirst($tenant['plan'] ?? 'starter') ?></h3>
      <p class="text-gray-500 text-sm">Billing management is handled by your super administrator.</p>
    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => {
    const active = b.dataset.tab === tab;
    b.classList.toggle('bg-white', active);
    b.classList.toggle('text-gray-900', active);
    b.classList.toggle('shadow-sm', active);
    b.classList.toggle('text-gray-500', !active);
  });
  ['general','integrations','career','billing'].forEach(t => {
    document.getElementById('tab-' + t)?.classList.toggle('hidden', t !== tab);
  });
  history.replaceState(null, '', '?tab=' + tab);
}

function togglePassword(id, btn) {
  const inp = document.getElementById(id);
  const isPass = inp.type === 'password';
  inp.type = isPass ? 'text' : 'password';
}

async function saveSettings(formEl, settingKeys) {
  const fd = new FormData(formEl);
  const settings = {};
  settingKeys.forEach(k => { settings['company_' + k] = fd.get(k) || ''; });
  try {
    const res = await ajax('/api/v1/settings?action=save_settings', { body: { settings } });
    if (res.ok) showToast('Settings saved', 'success');
    else throw new Error(res.message);
  } catch(e) { showToast(e.message || 'Failed', 'error'); }
}

document.getElementById('general-form')?.addEventListener('submit', e => { e.preventDefault(); saveSettings(e.target, ['name','industry','size','website','about']); });
document.getElementById('career-form')?.addEventListener('submit', e => { e.preventDefault(); saveSettings(e.target, ['career_page_enabled','career_tagline','career_about']); });

document.getElementById('openai-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const settings = { openai_api_key: fd.get('openai_key'), openai_model: fd.get('openai_model') };
  const res = await ajax('/api/v1/settings?action=save_settings', { body: { settings } });
  if (res.ok) showToast('OpenAI settings saved', 'success');
  else showToast(res.message || 'Failed', 'error');
});

document.getElementById('heygen-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const settings = { heygen_api_key: fd.get('heygen_key') };
  const res = await ajax('/api/v1/settings?action=save_settings', { body: { settings } });
  if (res.ok) showToast('HeyGen settings saved', 'success');
  else showToast(res.message || 'Failed', 'error');
});

async function testOpenAI() {
  try {
    const res = await ajax('/setup/?action=test_openai', { body: { key: document.getElementById('openai-key').value } });
    showToast(res.ok ? 'OpenAI connected ✓' : (res.message || 'Failed'), res.ok ? 'success' : 'error');
  } catch(e) { showToast('Connection failed', 'error'); }
}
async function testHeyGen() {
  try {
    const res = await ajax('/setup/?action=test_heygen', { body: { key: document.getElementById('heygen-key').value } });
    showToast(res.ok ? 'HeyGen connected ✓' : (res.message || 'Failed'), res.ok ? 'success' : 'error');
  } catch(e) { showToast('Connection failed', 'error'); }
}
</script>
