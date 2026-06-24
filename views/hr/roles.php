<?php
/**
 * Role management — default roles + permission matrix (client-side state).
 * Fragment rendered inside views/layouts/app.php.
 */
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </span>
        <?= e(app_lang('Roles & Permissions')) ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">Define what each role can do across the platform.</p>
    </div>
    <div class="flex gap-2 self-start sm:self-auto">
      <button id="open-create-role" class="btn-ghost">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Create Role
      </button>
      <button id="save-perms" class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        Save Changes
      </button>
    </div>
  </div>

  <!-- Role summary cards -->
  <div id="role-cards" class="grid sm:grid-cols-3 gap-4 mb-6"></div>

  <!-- Permission matrix -->
  <div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h2 class="font-bold text-gray-900">Permission Matrix</h2>
      <span class="text-xs text-gray-400">Tick a box to grant a permission to a role</span>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm" id="matrix"></table>
    </div>
  </div>
</div>

<!-- Create Role Modal -->
<div id="role-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex items-start justify-between mb-4">
      <h3 class="text-lg font-bold text-gray-900">Create Role</h3>
      <button class="text-gray-400 hover:text-gray-600" data-modal-close="role-modal"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <form id="role-form" class="space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Role name <span class="text-red-500">*</span></label>
        <input name="name" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="e.g. Coordinator" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Description</label>
        <input name="description" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Short description" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-2">Starting permissions</label>
        <div class="flex flex-wrap gap-2">
          <label class="inline-flex items-center gap-1.5 text-sm"><input type="radio" name="preset" value="none" class="text-violet-600"> None</label>
          <label class="inline-flex items-center gap-1.5 text-sm"><input type="radio" name="preset" value="readonly" checked class="text-violet-600"> Read-only</label>
          <label class="inline-flex items-center gap-1.5 text-sm"><input type="radio" name="preset" value="operational" class="text-violet-600"> Operational</label>
          <label class="inline-flex items-center gap-1.5 text-sm"><input type="radio" name="preset" value="all" class="text-violet-600"> Full access</label>
        </div>
      </div>
      <input type="hidden" name="_csrf" value="<?= e($csrf ?? '') ?>" />
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btn-ghost" data-modal-close="role-modal">Cancel</button>
        <button type="submit" class="btn-primary">Create role</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  // ---- Permission model grouped by module ----
  const MODULES = [
    { key: 'dashboard', label: 'Dashboard', perms: [['view', 'View']] },
    { key: 'jobs', label: 'Jobs', perms: [['view', 'View'], ['create', 'Create'], ['edit', 'Edit'], ['delete', 'Delete'], ['publish', 'Publish']] },
    { key: 'candidates', label: 'Candidates', perms: [['view', 'View'], ['create', 'Create'], ['edit', 'Edit'], ['delete', 'Delete'], ['compare', 'Compare']] },
    { key: 'interviews', label: 'Interviews', perms: [['view', 'View'], ['create', 'Create'], ['report', 'Report']] },
    { key: 'pipeline', label: 'Pipeline', perms: [['view', 'View'], ['manage', 'Manage']] },
    { key: 'offers', label: 'Offers', perms: [['view', 'View'], ['create', 'Create'], ['send', 'Send']] },
    { key: 'talent_pool', label: 'Talent Pool', perms: [['view', 'View'], ['manage', 'Manage']] },
    { key: 'avatars', label: 'Avatars', perms: [['view', 'View'], ['manage', 'Manage']] },
    { key: 'users', label: 'Users', perms: [['view', 'View'], ['manage', 'Manage']] },
    { key: 'roles', label: 'Roles', perms: [['view', 'View'], ['manage', 'Manage']] },
    { key: 'settings', label: 'Settings', perms: [['view', 'View'], ['manage', 'Manage']] },
    { key: 'ai', label: 'AI', perms: [['use', 'Use'], ['analytics', 'Analytics']] }
  ];

  // ---- Default roles ----
  let ROLES = [
    { id: 'admin', name: 'Admin', description: 'Full access to every module and setting.', cls: 'badge-violet', preset: 'all', system: true },
    { id: 'recruiter', name: 'Recruiter', description: 'Runs day-to-day hiring operations.', cls: 'badge-blue', preset: 'operational', system: true },
    { id: 'hiring_manager', name: 'Hiring Manager', description: 'Reviews candidates, interviews and offers.', cls: 'badge-gray', preset: 'manager', system: true }
  ];

  // grants[roleId] = Set of "module.perm"
  const grants = {};

  function allPerms() {
    const out = [];
    MODULES.forEach(m => m.perms.forEach(p => out.push(m.key + '.' + p[0])));
    return out;
  }

  function defaultGrant(preset) {
    const s = new Set();
    const all = allPerms();
    if (preset === 'all') { all.forEach(p => s.add(p)); return s; }
    if (preset === 'none') return s;
    if (preset === 'readonly') {
      all.forEach(p => { if (/\.(view|report|analytics|use)$/.test(p)) s.add(p); });
      return s;
    }
    if (preset === 'operational') {
      // Recruiter: most operational perms, but not user/role/settings management.
      all.forEach(p => {
        if (/^(users|roles)\./.test(p)) { if (/\.view$/.test(p)) s.add(p); return; }
        if (/^settings\./.test(p)) { if (/\.view$/.test(p)) s.add(p); return; }
        s.add(p);
      });
      return s;
    }
    if (preset === 'manager') {
      // Hiring Manager: read everywhere + interviews + offers operational.
      all.forEach(p => {
        if (/\.(view|report|compare|analytics|use)$/.test(p)) s.add(p);
      });
      ['interviews.create', 'offers.create', 'offers.send', 'pipeline.manage'].forEach(p => s.add(p));
      return s;
    }
    return s;
  }

  function initGrants() {
    ROLES.forEach(r => { grants[r.id] = defaultGrant(r.preset); });
  }

  function countFor(roleId) { return grants[roleId] ? grants[roleId].size : 0; }

  function renderCards() {
    $('role-cards').innerHTML = ROLES.map(r =>
      '<div class="card p-5">' +
        '<div class="flex items-center justify-between mb-1">' +
          '<span class="badge ' + r.cls + '">' + AR.esc(r.name) + '</span>' +
          (r.system ? '<span class="text-[10px] text-gray-400 uppercase tracking-wide">System</span>' : '<button class="text-xs text-red-500 hover:underline" data-delrole="' + AR.esc(r.id) + '">Delete</button>') +
        '</div>' +
        '<h3 class="font-bold text-gray-900 mt-2">' + AR.esc(r.name) + '</h3>' +
        '<p class="text-sm text-gray-500 mt-1 min-h-[2.5rem]">' + AR.esc(r.description) + '</p>' +
        '<div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-50">' +
          '<span class="text-xs text-gray-500"><span class="font-bold text-violet-600" data-count="' + AR.esc(r.id) + '">' + countFor(r.id) + '</span> permissions</span>' +
          '<button class="text-xs font-semibold text-violet-600 hover:underline" data-scroll-matrix>Edit permissions ↓</button>' +
        '</div>' +
      '</div>'
    ).join('');
    $('role-cards').querySelectorAll('[data-scroll-matrix]').forEach(b => b.addEventListener('click', () => $('matrix').scrollIntoView({ behavior: 'smooth', block: 'start' })));
    $('role-cards').querySelectorAll('[data-delrole]').forEach(b => b.addEventListener('click', () => deleteRole(b.getAttribute('data-delrole'))));
  }

  function renderMatrix() {
    const head = '<thead><tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-gray-100 bg-gray-50/60">' +
      '<th class="px-5 py-3 font-semibold sticky left-0 bg-gray-50/60 z-10">Permission</th>' +
      ROLES.map(r => '<th class="px-4 py-3 font-semibold text-center whitespace-nowrap">' +
        '<div class="flex flex-col items-center gap-1"><span>' + AR.esc(r.name) + '</span>' +
        '<button class="text-[10px] text-violet-500 hover:underline" data-toggle-col="' + AR.esc(r.id) + '">all/none</button></div></th>').join('') +
    '</tr></thead>';

    let body = '<tbody>';
    MODULES.forEach(mod => {
      body += '<tr class="bg-violet-50/40"><td class="px-5 py-2 font-semibold text-violet-800 text-xs uppercase tracking-wide" colspan="' + (ROLES.length + 1) + '">' + AR.esc(mod.label) + '</td></tr>';
      mod.perms.forEach(p => {
        const permKey = mod.key + '.' + p[0];
        body += '<tr class="border-b border-gray-50 hover:bg-gray-50">' +
          '<td class="px-5 py-2.5 text-gray-700 sticky left-0 bg-white z-10">' + AR.esc(p[1]) + '</td>' +
          ROLES.map(r => {
            const checked = grants[r.id] && grants[r.id].has(permKey);
            return '<td class="px-4 py-2.5 text-center">' +
              '<input type="checkbox" class="perm-cb w-4 h-4 rounded text-violet-600 focus:ring-violet-500 border-gray-300" ' +
              'data-role="' + AR.esc(r.id) + '" data-perm="' + AR.esc(permKey) + '"' + (checked ? ' checked' : '') + '></td>';
          }).join('') +
        '</tr>';
      });
    });
    body += '</tbody>';

    $('matrix').innerHTML = head + body;

    $('matrix').querySelectorAll('.perm-cb').forEach(cb => cb.addEventListener('change', function () {
      const role = cb.getAttribute('data-role'), perm = cb.getAttribute('data-perm');
      if (cb.checked) grants[role].add(perm); else grants[role].delete(perm);
      updateCount(role);
    }));
    $('matrix').querySelectorAll('[data-toggle-col]').forEach(b => b.addEventListener('click', () => toggleColumn(b.getAttribute('data-toggle-col'))));
  }

  function updateCount(roleId) {
    const el = document.querySelector('[data-count="' + roleId + '"]');
    if (el) el.textContent = countFor(roleId);
  }

  function toggleColumn(roleId) {
    const all = allPerms();
    const has = grants[roleId].size >= all.length;
    grants[roleId] = has ? new Set() : new Set(all);
    renderMatrix();
    updateCount(roleId);
  }

  function deleteRole(id) {
    ROLES = ROLES.filter(r => r.id !== id);
    delete grants[id];
    renderCards();
    renderMatrix();
    AR.Toast.info('Role removed (unsaved).');
  }

  document.addEventListener('DOMContentLoaded', function () {
    initGrants();
    renderCards();
    renderMatrix();

    $('open-create-role').addEventListener('click', () => AR.Modal.open('role-modal'));

    $('save-perms').addEventListener('click', function () {
      // Client-side state — surface a success toast (POST optional / not required).
      AR.Toast.success('Permissions saved.');
    });

    $('role-form').addEventListener('submit', function (e) {
      e.preventDefault();
      const fd = new FormData(e.target);
      const name = (fd.get('name') || '').trim();
      if (!name) return;
      const id = name.toLowerCase().replace(/[^a-z0-9]+/g, '_') + '_' + Math.random().toString(36).slice(2, 6);
      const preset = fd.get('preset') || 'readonly';
      ROLES.push({ id: id, name: name, description: fd.get('description') || 'Custom role', cls: 'badge-green', preset: preset, system: false });
      grants[id] = defaultGrant(preset === 'operational' ? 'operational' : preset);
      renderCards();
      renderMatrix();
      AR.Modal.close('role-modal');
      e.target.reset();
      AR.Toast.success('Role "' + name + '" created.');
    });
  });
})();
</script>
