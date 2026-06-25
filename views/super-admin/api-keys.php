<?php
/**
 * Super Admin – API Keys Management
 * Global platform keys + per-tenant key overrides
 */
$db = Database::getInstance();

// Load global keys from system_settings
$globalSettings = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE tenant_id IS NULL") ?? [];
$global = array_column($globalSettings, 'setting_value', 'setting_key');

// Load all tenants with their key status
$tenants = $db->fetchAll(
    "SELECT id, name, slug, status, plan,
            openai_api_key, heygen_api_key, openai_model
     FROM tenants ORDER BY name ASC"
) ?? [];

function maskKey(?string $enc): string {
    if (!$enc) return '';
    try {
        $plain = ApiKeyManager::decrypt($enc);
        if ($plain === '') return '';
        return '••••••••' . substr($plain, -4);
    } catch (\Throwable $e) {
        return '••••••••';
    }
}

function keySet(?string $enc): bool {
    if (!$enc) return false;
    try { return ApiKeyManager::decrypt($enc) !== ''; } catch (\Throwable $e) { return false; }
}
?>

<!-- Page Header -->
<div class="mb-6 flex items-center justify-between">
  <div>
    <h1 class="text-xl font-bold text-gray-900">API Keys</h1>
    <p class="text-sm text-gray-500 mt-0.5">Manage global platform keys and per-company overrides</p>
  </div>
</div>

<!-- ═══ Global / Platform Keys ══════════════════════════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
  <div class="flex items-center gap-3 mb-5">
    <div class="w-9 h-9 bg-violet-100 rounded-xl flex items-center justify-center">
      <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
    </div>
    <div>
      <h2 class="font-semibold text-gray-900">Global Platform Keys</h2>
      <p class="text-xs text-gray-400">Used as fallback when a company hasn't set its own keys</p>
    </div>
  </div>
  <form id="globalForm" onsubmit="saveGlobal(event)" class="space-y-5 max-w-2xl">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1.5">OpenAI API Key</label>
      <div class="flex gap-2">
        <input type="password" id="g_openai" name="openai_api_key" placeholder="sk-…"
               class="flex-1 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none"
               autocomplete="new-password">
        <button type="button" onclick="toggleVis('g_openai')" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-500 hover:bg-gray-50 transition-colors">Show</button>
        <button type="button" onclick="testKey('openai','g_openai','g_openai_status')" class="px-3 py-2.5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-medium hover:bg-emerald-100 transition-colors">Test</button>
      </div>
      <p id="g_openai_status" class="text-xs mt-1 text-gray-400">Leave blank to keep existing key.</p>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1.5">HeyGen API Key</label>
      <div class="flex gap-2">
        <input type="password" id="g_heygen" name="heygen_api_key" placeholder="HeyGen key…"
               class="flex-1 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none"
               autocomplete="new-password">
        <button type="button" onclick="toggleVis('g_heygen')" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-500 hover:bg-gray-50 transition-colors">Show</button>
        <button type="button" onclick="testKey('heygen','g_heygen','g_heygen_status')" class="px-3 py-2.5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-medium hover:bg-emerald-100 transition-colors">Test</button>
      </div>
      <p id="g_heygen_status" class="text-xs mt-1 text-gray-400">Leave blank to keep existing key.</p>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1.5">Default OpenAI Model</label>
      <select name="openai_model" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white">
        <?php
        $models = ['gpt-4o'=>'GPT-4o (Recommended)','gpt-4o-mini'=>'GPT-4o Mini (Fast & Cheap)','gpt-4.1'=>'GPT-4.1','gpt-4.1-mini'=>'GPT-4.1 Mini','gpt-4-turbo'=>'GPT-4 Turbo'];
        $cur = $global['openai_model'] ?? 'gpt-4o';
        foreach ($models as $v => $l): ?>
        <option value="<?= $v ?>" <?= $cur === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="pt-2">
      <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-full text-sm font-semibold transition-colors">Save Global Keys</button>
      <span id="globalSaveStatus" class="ml-3 text-sm text-gray-400"></span>
    </div>
  </form>
</div>

<!-- ═══ Per-Company Keys ═════════════════════════════════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
    <div>
      <h2 class="font-semibold text-gray-900">Company API Keys</h2>
      <p class="text-xs text-gray-400 mt-0.5">Override per company — leave blank to inherit global keys</p>
    </div>
    <span class="text-sm text-gray-400"><?= count($tenants) ?> companies</span>
  </div>

  <?php if (empty($tenants)): ?>
  <div class="py-14 text-center text-sm text-gray-400">No companies yet.</div>
  <?php else: ?>
  <div class="divide-y divide-gray-50">
    <?php foreach ($tenants as $t):
      $hasOpenAI = keySet($t['openai_api_key'] ?? null);
      $hasHeyGen = keySet($t['heygen_api_key'] ?? null);
    ?>
    <div class="px-6 py-4 flex items-center gap-4" id="row-<?= $t['id'] ?>">
      <div class="w-9 h-9 bg-violet-100 rounded-xl flex items-center justify-center text-violet-700 font-bold text-sm flex-shrink-0">
        <?= strtoupper(substr($t['name'], 0, 1)) ?>
      </div>
      <div class="flex-1 min-w-0">
        <div class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($t['name']) ?></div>
        <div class="text-xs text-gray-400"><?= htmlspecialchars($t['slug']) ?> · <?= htmlspecialchars($t['plan']) ?></div>
      </div>
      <!-- Key Status Badges -->
      <div class="hidden sm:flex items-center gap-2 flex-shrink-0">
        <span id="badge-openai-<?= $t['id'] ?>" class="inline-flex items-center gap-1 text-xs font-medium rounded-full px-2.5 py-1 <?= $hasOpenAI ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
          OpenAI <?= $hasOpenAI ? '✓' : '—' ?>
        </span>
        <span id="badge-heygen-<?= $t['id'] ?>" class="inline-flex items-center gap-1 text-xs font-medium rounded-full px-2.5 py-1 <?= $hasHeyGen ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
          HeyGen <?= $hasHeyGen ? '✓' : '—' ?>
        </span>
      </div>
      <button onclick="openTenantModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['name'])) ?>', '<?= htmlspecialchars(addslashes($t['openai_model'] ?? '')) ?>')"
              class="flex-shrink-0 bg-gray-100 hover:bg-violet-100 hover:text-violet-700 text-gray-600 text-xs font-semibold px-3 py-1.5 rounded-full transition-colors">
        Edit Keys
      </button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ Tenant Edit Modal ════════════════════════════════════════════════════ -->
<div id="tenantModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="font-semibold text-gray-900" id="modalTitle">Edit API Keys</h3>
      <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="p-6 space-y-4">
      <input type="hidden" id="modalTenantId">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">OpenAI API Key</label>
        <div class="flex gap-2">
          <input type="password" id="t_openai" placeholder="sk-… (blank = use global)"
                 class="flex-1 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none"
                 autocomplete="new-password">
          <button type="button" onclick="toggleVis('t_openai')" class="px-3 border border-gray-200 rounded-xl text-sm text-gray-500 hover:bg-gray-50">Show</button>
        </div>
        <div class="flex gap-2 mt-1.5">
          <button type="button" onclick="testKey('openai','t_openai','t_openai_status')" class="text-xs bg-emerald-50 border border-emerald-200 text-emerald-700 px-2.5 py-1 rounded-full hover:bg-emerald-100 transition-colors">Test</button>
          <button type="button" onclick="clearTenantKey('openai')" class="text-xs bg-red-50 border border-red-200 text-red-600 px-2.5 py-1 rounded-full hover:bg-red-100 transition-colors">Clear</button>
          <span id="t_openai_status" class="text-xs text-gray-400 self-center ml-1"></span>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">HeyGen API Key</label>
        <div class="flex gap-2">
          <input type="password" id="t_heygen" placeholder="HeyGen key… (blank = use global)"
                 class="flex-1 border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm font-mono focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none"
                 autocomplete="new-password">
          <button type="button" onclick="toggleVis('t_heygen')" class="px-3 border border-gray-200 rounded-xl text-sm text-gray-500 hover:bg-gray-50">Show</button>
        </div>
        <div class="flex gap-2 mt-1.5">
          <button type="button" onclick="testKey('heygen','t_heygen','t_heygen_status')" class="text-xs bg-emerald-50 border border-emerald-200 text-emerald-700 px-2.5 py-1 rounded-full hover:bg-emerald-100 transition-colors">Test</button>
          <button type="button" onclick="clearTenantKey('heygen')" class="text-xs bg-red-50 border border-red-200 text-red-600 px-2.5 py-1 rounded-full hover:bg-red-100 transition-colors">Clear</button>
          <span id="t_heygen_status" class="text-xs text-gray-400 self-center ml-1"></span>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">OpenAI Model Override</label>
        <select id="t_model" class="w-full border border-gray-200 rounded-xl px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white">
          <option value="">— Use global default —</option>
          <?php foreach ($models as $v => $l): ?>
          <option value="<?= $v ?>"><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="px-6 py-4 border-t border-gray-100 flex gap-3 justify-end">
      <button onclick="closeModal()" class="px-4 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">Cancel</button>
      <button onclick="saveTenantKeys()" class="px-5 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl transition-colors">Save Keys</button>
    </div>
  </div>
</div>

<script>
function toggleVis(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}

async function testKey(type, inputId, statusId) {
  const key = document.getElementById(inputId).value.trim();
  const status = document.getElementById(statusId);
  status.textContent = 'Testing…';
  try {
    const fd = new FormData();
    fd.append('key', key);
    const r = await fetch(`/api/v1/settings?action=test_${type}`, {method:'POST', body: fd});
    const d = await r.json();
    if (d.ok) { status.textContent = '✓ Valid'; status.className = 'text-xs mt-1 text-emerald-600'; }
    else { status.textContent = '✗ ' + (d.message || 'Invalid'); status.className = 'text-xs mt-1 text-red-600'; }
  } catch(e) { status.textContent = 'Error'; status.className = 'text-xs mt-1 text-red-600'; }
}

async function saveGlobal(e) {
  e.preventDefault();
  const form = document.getElementById('globalForm');
  const fd = new FormData(form);
  const payload = { openai: fd.get('openai_api_key'), heygen: fd.get('heygen_api_key'), openai_model: fd.get('openai_model') };
  const status = document.getElementById('globalSaveStatus');
  status.textContent = 'Saving…';
  try {
    const r = await fetch('/api/v1/settings?action=save_api_keys', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const d = await r.json();
    // Also save the model as a setting
    await fetch('/api/v1/settings?action=save_settings', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({settings: {openai_model: fd.get('openai_model')}})
    });
    if (d.ok) { status.textContent = '✓ Saved'; status.className = 'ml-3 text-sm text-emerald-600'; }
    else { status.textContent = '✗ ' + d.message; status.className = 'ml-3 text-sm text-red-600'; }
  } catch(e) { status.textContent = '✗ Error'; status.className = 'ml-3 text-sm text-red-600'; }
  setTimeout(() => { status.textContent = ''; }, 4000);
}

let currentTenantId = null;
function openTenantModal(id, name, model) {
  currentTenantId = id;
  document.getElementById('modalTitle').textContent = 'Edit API Keys – ' + name;
  document.getElementById('modalTenantId').value = id;
  document.getElementById('t_openai').value = '';
  document.getElementById('t_heygen').value = '';
  document.getElementById('t_model').value = model || '';
  document.getElementById('t_openai_status').textContent = '';
  document.getElementById('t_heygen_status').textContent = '';
  document.getElementById('tenantModal').classList.remove('hidden');
  document.getElementById('tenantModal').classList.add('flex');
}
function closeModal() {
  document.getElementById('tenantModal').classList.add('hidden');
  document.getElementById('tenantModal').classList.remove('flex');
  currentTenantId = null;
}
async function clearTenantKey(type) {
  if (!currentTenantId) return;
  const statusId = 't_' + (type === 'openai' ? 'openai' : 'heygen') + '_status';
  document.getElementById(statusId).textContent = 'Clearing…';
  const payload = {tenant_id: currentTenantId};
  payload[type] = '';
  payload['openai_model'] = document.getElementById('t_model').value;
  const r = await fetch('/api/v1/admin?action=save_tenant_keys', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
  });
  const d = await r.json();
  if (d.ok) {
    document.getElementById(statusId).textContent = 'Cleared';
    document.getElementById('badge-' + type + '-' + currentTenantId).className = 'inline-flex items-center gap-1 text-xs font-medium rounded-full px-2.5 py-1 bg-gray-100 text-gray-500';
    document.getElementById('badge-' + type + '-' + currentTenantId).textContent = (type === 'openai' ? 'OpenAI' : 'HeyGen') + ' —';
  }
}
async function saveTenantKeys() {
  if (!currentTenantId) return;
  const payload = {
    tenant_id: currentTenantId,
    openai: document.getElementById('t_openai').value,
    heygen: document.getElementById('t_heygen').value,
    openai_model: document.getElementById('t_model').value,
  };
  const r = await fetch('/api/v1/admin?action=save_tenant_keys', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
  });
  const d = await r.json();
  if (d.ok) {
    // Update badges
    if (payload.openai) {
      const b = document.getElementById('badge-openai-' + currentTenantId);
      if (b) { b.className = 'inline-flex items-center gap-1 text-xs font-medium rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-700'; b.textContent = 'OpenAI ✓'; }
    }
    if (payload.heygen) {
      const b = document.getElementById('badge-heygen-' + currentTenantId);
      if (b) { b.className = 'inline-flex items-center gap-1 text-xs font-medium rounded-full px-2.5 py-1 bg-emerald-100 text-emerald-700'; b.textContent = 'HeyGen ✓'; }
    }
    closeModal();
    if (typeof showToast === 'function') showToast('Keys saved for company', 'success');
  } else {
    if (typeof showToast === 'function') showToast('Error: ' + d.message, 'error'); else alert('Error: ' + d.message);
  }
}
document.getElementById('tenantModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
