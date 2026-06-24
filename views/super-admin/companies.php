<?php
/**
 * Super Admin — Companies Management (fragment, rendered into $content).
 * Wrapped by views/layouts/admin.php.
 */
$csrf = $csrf ?? '';
?>
<div class="px-4 sm:px-6 lg:px-8 py-6 max-w-7xl mx-auto fade-in" data-page="admin-companies">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
      <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-violet-600 uppercase mb-1">
        <span class="inline-block w-2 h-2 rounded-full bg-violet-600"></span>
        <?= e(app_lang('Tenant Administration')) ?>
      </div>
      <h1 class="text-2xl font-bold text-gray-900"><?= e(app_lang('Companies')) ?></h1>
      <p class="text-sm text-gray-500 mt-1"><?= e(app_lang('Provision, suspend and impersonate tenant workspaces.')) ?></p>
    </div>
    <button type="button" id="open-create" class="btn-primary text-sm self-start">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      <?= e(app_lang('Create Company')) ?>
    </button>
  </div>

  <!-- Filter bar -->
  <div class="card p-4 mb-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="lg:col-span-2 relative">
        <svg class="w-4 h-4 text-gray-400 absolute top-1/2 -translate-y-1/2 ltr:left-3 rtl:right-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
        <input id="f-search" type="text" placeholder="<?= e(app_lang('Search by name or subdomain…')) ?>"
               class="w-full ltr:pl-9 rtl:pr-9 pr-3 py-2 text-sm rounded-lg border border-gray-200 focus:border-violet-500 focus:ring-2 focus:ring-violet-100 outline-none" />
      </div>
      <select id="f-status" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:border-violet-500 focus:ring-2 focus:ring-violet-100 outline-none bg-white">
        <option value=""><?= e(app_lang('All statuses')) ?></option>
        <option value="active"><?= e(app_lang('Active')) ?></option>
        <option value="inactive"><?= e(app_lang('Inactive')) ?></option>
        <option value="suspended"><?= e(app_lang('Suspended')) ?></option>
      </select>
      <select id="f-plan" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:border-violet-500 focus:ring-2 focus:ring-violet-100 outline-none bg-white">
        <option value=""><?= e(app_lang('All plans')) ?></option>
        <option value="free"><?= e(app_lang('Free')) ?></option>
        <option value="starter"><?= e(app_lang('Starter')) ?></option>
        <option value="pro"><?= e(app_lang('Pro')) ?></option>
        <option value="enterprise"><?= e(app_lang('Enterprise')) ?></option>
      </select>
    </div>
  </div>

  <!-- Table -->
  <div class="card overflow-hidden">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
      <h2 class="text-sm font-semibold text-gray-900"><?= e(app_lang('All Companies')) ?> <span id="comp-count" class="ml-1 text-xs font-normal text-gray-400"></span></h2>
      <button type="button" id="comp-reload" class="text-xs font-semibold text-violet-600 hover:text-violet-700 inline-flex items-center gap-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582M20 20v-5h-.581M5.5 9a7.003 7.003 0 0113.197-1M18.5 15A7.003 7.003 0 015.303 16"/></svg>
        <?= e(app_lang('Reload')) ?>
      </button>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 border-b border-gray-100">
            <th class="px-5 py-3"><?= e(app_lang('Name')) ?></th>
            <th class="px-5 py-3"><?= e(app_lang('Subdomain')) ?></th>
            <th class="px-5 py-3"><?= e(app_lang('Plan')) ?></th>
            <th class="px-5 py-3"><?= e(app_lang('Status')) ?></th>
            <th class="px-5 py-3"><?= e(app_lang('Users')) ?></th>
            <th class="px-5 py-3"><?= e(app_lang('Created')) ?></th>
            <th class="px-5 py-3 ltr:text-right rtl:text-left"><?= e(app_lang('Actions')) ?></th>
          </tr>
        </thead>
        <tbody id="companies-body" class="divide-y divide-gray-50">
          <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400"><?= e(app_lang('Loading…')) ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create Company Modal -->
<div id="modal-create-company" class="modal-backdrop hidden-modal">
  <div class="modal-card max-h-[92vh] overflow-y-auto">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
      <h3 class="text-lg font-bold text-gray-900"><?= e(app_lang('Create Company')) ?></h3>
      <button type="button" data-close-create class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="create-company-form" class="px-6 py-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e(app_lang('Company Name')) ?> <span class="text-red-500">*</span></label>
        <input name="name" required type="text" placeholder="<?= e(app_lang('Acme Talent Inc.')) ?>"
               class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:border-violet-500 focus:ring-2 focus:ring-violet-100 outline-none" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e(app_lang('Subdomain')) ?> <span class="text-red-500">*</span></label>
        <div class="flex items-stretch rounded-lg border border-gray-200 focus-within:border-violet-500 focus-within:ring-2 focus-within:ring-violet-100 overflow-hidden">
          <input name="subdomain" required type="text" placeholder="acme" autocomplete="off"
                 class="flex-1 px-3 py-2 text-sm outline-none" />
          <span class="px-3 py-2 text-sm text-gray-400 bg-gray-50 border-l border-gray-200 whitespace-nowrap select-none">.yourplatform.com</span>
        </div>
        <p class="text-xs text-gray-400 mt-1"><?= e(app_lang('Lowercase letters, numbers and hyphens only.')) ?></p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e(app_lang('Plan')) ?></label>
        <select name="plan" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:border-violet-500 focus:ring-2 focus:ring-violet-100 outline-none bg-white">
          <option value="free"><?= e(app_lang('Free')) ?></option>
          <option value="starter"><?= e(app_lang('Starter')) ?></option>
          <option value="pro" selected><?= e(app_lang('Pro')) ?></option>
          <option value="enterprise"><?= e(app_lang('Enterprise')) ?></option>
        </select>
      </div>

      <div class="relative py-1">
        <div class="border-t border-gray-100"></div>
        <span class="absolute -top-2 left-0 bg-white pr-2 text-xs font-semibold uppercase tracking-wide text-gray-400"><?= e(app_lang('Workspace Admin')) ?></span>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e(app_lang('Admin Full Name')) ?> <span class="text-red-500">*</span></label>
        <input name="admin_name" required type="text" placeholder="<?= e(app_lang('Jane Doe')) ?>"
               class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:border-violet-500 focus:ring-2 focus:ring-violet-100 outline-none" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e(app_lang('Admin Email')) ?> <span class="text-red-500">*</span></label>
        <input name="admin_email" required type="email" placeholder="admin@acme.com"
               class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:border-violet-500 focus:ring-2 focus:ring-violet-100 outline-none" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1"><?= e(app_lang('Admin Password')) ?> <span class="text-red-500">*</span></label>
        <div class="flex items-stretch rounded-lg border border-gray-200 focus-within:border-violet-500 focus-within:ring-2 focus-within:ring-violet-100 overflow-hidden">
          <input name="admin_password" required type="password" minlength="8" placeholder="••••••••"
                 class="flex-1 px-3 py-2 text-sm outline-none" />
          <button type="button" id="gen-pass" class="px-3 text-xs font-semibold text-violet-600 bg-gray-50 border-l border-gray-200 hover:bg-gray-100 whitespace-nowrap"><?= e(app_lang('Generate')) ?></button>
        </div>
        <p class="text-xs text-gray-400 mt-1"><?= e(app_lang('Minimum 8 characters.')) ?></p>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2">
        <button type="button" data-close-create class="btn-ghost text-sm"><?= e(app_lang('Cancel')) ?></button>
        <button type="submit" id="create-submit" class="btn-primary text-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          <span><?= e(app_lang('Create Company')) ?></span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';
  var AR = window.AR || {};
  var esc = AR.esc || function (s) { return s == null ? '' : String(s); };
  var MODAL_ID = 'modal-create-company';

  function num(v) { return Number(v) || 0; }
  function fmtDate(s) {
    if (!s) return '—';
    var d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return esc(s);
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }
  function planBadge(plan) {
    var map = { free: 'badge-gray', starter: 'badge-blue', pro: 'badge-violet', enterprise: 'badge-yellow' };
    var cls = map[(plan || '').toLowerCase()] || 'badge-gray';
    var label = plan ? plan.charAt(0).toUpperCase() + plan.slice(1) : '—';
    return '<span class="badge ' + cls + '">' + esc(label) + '</span>';
  }
  function statusMeta(st) {
    st = (st || '').toLowerCase();
    if (st === 'active') return { cls: 'badge-green', label: 'Active' };
    if (st === 'suspended') return { cls: 'badge-red', label: 'Suspended' };
    return { cls: 'badge-gray', label: st ? st.charAt(0).toUpperCase() + st.slice(1) : 'Inactive' };
  }

  function rowHtml(c) {
    var sm = statusMeta(c.status);
    var isActive = (c.status || '').toLowerCase() === 'active';
    var toggleLabel = isActive ? 'Deactivate' : 'Activate';
    var toggleCls = isActive
      ? 'text-amber-700 bg-amber-50 hover:bg-amber-100 border-amber-200'
      : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border-emerald-200';
    var initial = esc((c.name || '?').charAt(0).toUpperCase());
    var id = esc(c.id);
    return '<tr class="hover:bg-gray-50 transition-colors" data-id="' + id + '">' +
      '<td class="px-5 py-3">' +
        '<div class="flex items-center gap-3">' +
          '<span class="w-8 h-8 rounded-lg bg-violet-100 text-violet-700 text-xs font-bold flex items-center justify-center flex-shrink-0">' + initial + '</span>' +
          '<span class="font-medium text-gray-900">' + esc(c.name || '—') + '</span>' +
        '</div>' +
      '</td>' +
      '<td class="px-5 py-3 text-gray-500"><span class="font-mono text-xs">' + esc(c.subdomain || '—') + '</span></td>' +
      '<td class="px-5 py-3">' + planBadge(c.plan) + '</td>' +
      '<td class="px-5 py-3"><span class="badge ' + sm.cls + '" data-status-badge>' + esc(sm.label) + '</span></td>' +
      '<td class="px-5 py-3 text-gray-600 tabular-nums">' + num(c.user_count) + '</td>' +
      '<td class="px-5 py-3 text-gray-500">' + fmtDate(c.created_at) + '</td>' +
      '<td class="px-5 py-3">' +
        '<div class="flex items-center justify-end gap-2">' +
          '<button type="button" data-action="toggle" data-active="' + (isActive ? '1' : '0') + '" ' +
            'class="text-xs font-semibold px-2.5 py-1 rounded-md border transition-colors ' + toggleCls + '">' + esc(toggleLabel) + '</button>' +
          '<button type="button" data-action="impersonate" ' +
            'class="text-xs font-semibold px-2.5 py-1 rounded-md border border-violet-200 text-violet-700 bg-violet-50 hover:bg-violet-100 transition-colors inline-flex items-center gap-1">' +
            '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>' +
            esc('Impersonate') + '</button>' +
        '</div>' +
      '</td>' +
    '</tr>';
  }

  function setRows(html) { document.getElementById('companies-body').innerHTML = html; }
  function loadingRow() {
    setRows('<tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">' +
      '<svg class="w-5 h-5 spin inline-block mr-2 align-middle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582M20 20v-5h-.581M5.5 9a7.003 7.003 0 0113.197-1M18.5 15A7.003 7.003 0 015.303 16"/></svg>' +
      esc('Loading companies…') + '</td></tr>');
  }
  function emptyRow() {
    setRows('<tr><td colspan="7" class="px-5 py-14 text-center">' +
      '<div class="flex flex-col items-center gap-2 text-gray-400">' +
        '<svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3"/></svg>' +
        '<p class="text-sm font-medium text-gray-500">' + esc('No companies match your filters') + '</p>' +
        '<p class="text-xs">' + esc('Try clearing filters or create a new company.') + '</p>' +
      '</div></td></tr>');
  }
  function errorRow(msg) {
    setRows('<tr><td colspan="7" class="px-5 py-12 text-center text-red-500 text-sm">' + esc(msg) + '</td></tr>');
  }

  function qs() {
    var params = [];
    var st = document.getElementById('f-status').value;
    var pl = document.getElementById('f-plan').value;
    var s = document.getElementById('f-search').value.trim();
    if (st) params.push('status=' + encodeURIComponent(st));
    if (pl) params.push('plan=' + encodeURIComponent(pl));
    if (s) params.push('search=' + encodeURIComponent(s));
    return params.length ? '?' + params.join('&') : '';
  }

  async function load() {
    loadingRow();
    document.getElementById('comp-count').textContent = '';
    try {
      var rows = await AR.Api.get('/admin/companies' + qs());
      rows = Array.isArray(rows) ? rows : (rows && rows.items) || [];
      document.getElementById('comp-count').textContent = '(' + rows.length + ')';
      if (!rows.length) { emptyRow(); return; }
      setRows(rows.map(rowHtml).join(''));
    } catch (err) {
      errorRow((err && err.message) || 'Failed to load companies.');
      if (AR.Toast) AR.Toast.error((err && err.message) || 'Failed to load companies.');
    }
  }

  // Debounced search
  var t = null;
  function debouncedLoad() { clearTimeout(t); t = setTimeout(load, 300); }

  async function toggleStatus(tr, btn) {
    var id = tr.getAttribute('data-id');
    var wasActive = btn.getAttribute('data-active') === '1';
    btn.disabled = true; var prev = btn.textContent; btn.textContent = '…';
    try {
      var updated = await AR.Api.put('/admin/companies/' + encodeURIComponent(id) + '/status', { active: !wasActive });
      // Determine new status from response when available, else flip.
      var newStatus = (updated && updated.status) ? updated.status : (wasActive ? 'inactive' : 'active');
      var sm = statusMeta(newStatus);
      var nowActive = newStatus.toLowerCase() === 'active';
      var badge = tr.querySelector('[data-status-badge]');
      if (badge) { badge.className = 'badge ' + sm.cls; badge.textContent = sm.label; }
      btn.setAttribute('data-active', nowActive ? '1' : '0');
      btn.textContent = nowActive ? 'Deactivate' : 'Activate';
      btn.className = 'text-xs font-semibold px-2.5 py-1 rounded-md border transition-colors ' +
        (nowActive ? 'text-amber-700 bg-amber-50 hover:bg-amber-100 border-amber-200'
                   : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border-emerald-200');
      if (AR.Toast) AR.Toast.success('Company ' + (nowActive ? 'activated' : 'deactivated'));
    } catch (err) {
      btn.textContent = prev;
      if (AR.Toast) AR.Toast.error((err && err.message) || 'Could not update status.');
    } finally {
      btn.disabled = false;
    }
  }

  function impersonate(tr) {
    var id = tr.getAttribute('data-id');
    if (AR.Toast) AR.Toast.info('Impersonation session starting…');
    // Hand off to the server impersonation entry point.
    setTimeout(function () { window.location.href = '/admin/companies/' + encodeURIComponent(id) + '/impersonate'; }, 600);
  }

  function bindTable() {
    document.getElementById('companies-body').addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-action]');
      if (!btn) return;
      var tr = btn.closest('tr[data-id]');
      if (!tr) return;
      if (btn.getAttribute('data-action') === 'toggle') toggleStatus(tr, btn);
      else if (btn.getAttribute('data-action') === 'impersonate') impersonate(tr);
    });
  }

  /* ---------- Create modal ---------- */
  function openModal() { if (AR.Modal) AR.Modal.open(MODAL_ID); else document.getElementById(MODAL_ID).classList.remove('hidden-modal'); }
  function closeModal() { if (AR.Modal) AR.Modal.close(MODAL_ID); else document.getElementById(MODAL_ID).classList.add('hidden-modal'); }

  function slugify(s) {
    return String(s || '').toLowerCase().trim()
      .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
  }
  function genPassword() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    var out = '';
    var arr = (window.crypto && window.crypto.getRandomValues) ? window.crypto.getRandomValues(new Uint32Array(14)) : null;
    for (var i = 0; i < 14; i++) {
      var r = arr ? arr[i] : Math.floor(Math.random() * 1e9);
      out += chars.charAt(r % chars.length);
    }
    return out;
  }

  function bindModal() {
    document.getElementById('open-create').addEventListener('click', openModal);
    document.querySelectorAll('[data-close-create]').forEach(function (b) { b.addEventListener('click', closeModal); });
    var backdrop = document.getElementById(MODAL_ID);
    backdrop.addEventListener('click', function (e) { if (e.target === backdrop) closeModal(); });

    var form = document.getElementById('create-company-form');
    var nameInput = form.querySelector('[name="name"]');
    var subInput = form.querySelector('[name="subdomain"]');
    var subTouched = false;
    subInput.addEventListener('input', function () { subTouched = true; subInput.value = slugify(subInput.value); });
    nameInput.addEventListener('input', function () { if (!subTouched) subInput.value = slugify(nameInput.value); });

    document.getElementById('gen-pass').addEventListener('click', function () {
      var pw = form.querySelector('[name="admin_password"]');
      pw.type = 'text'; pw.value = genPassword();
    });

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      var btn = document.getElementById('create-submit');
      var label = btn.querySelector('span');
      var fd = new FormData(form);
      var payload = {
        name: (fd.get('name') || '').toString().trim(),
        subdomain: slugify(fd.get('subdomain')),
        plan: (fd.get('plan') || 'free').toString(),
        admin_name: (fd.get('admin_name') || '').toString().trim(),
        admin_email: (fd.get('admin_email') || '').toString().trim(),
        admin_password: (fd.get('admin_password') || '').toString()
      };
      if (!payload.name || !payload.subdomain || !payload.admin_name || !payload.admin_email || !payload.admin_password) {
        if (AR.Toast) AR.Toast.error('Please complete all required fields.');
        return;
      }
      btn.disabled = true; var prev = label.textContent; label.textContent = 'Creating…';
      try {
        await AR.Api.post('/admin/companies', payload);
        if (AR.Toast) AR.Toast.success('Company created successfully.');
        form.reset();
        var pw = form.querySelector('[name="admin_password"]'); pw.type = 'password';
        closeModal();
        load();
      } catch (err) {
        if (AR.Toast) AR.Toast.error((err && err.message) || 'Could not create company.');
      } finally {
        btn.disabled = false; label.textContent = prev;
      }
    });
  }

  function init() {
    bindTable();
    bindModal();
    document.getElementById('f-search').addEventListener('input', debouncedLoad);
    document.getElementById('f-status').addEventListener('change', load);
    document.getElementById('f-plan').addEventListener('change', load);
    document.getElementById('comp-reload').addEventListener('click', load);
    load();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
</script>
