<?php
/**
 * User management — invite, edit, role change, status toggle, delete.
 * Fragment rendered inside views/layouts/app.php.
 */
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </span>
        <?= e(app_lang('Team & Users')) ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">Manage who has access to your recruitment workspace.</p>
    </div>
    <div class="flex gap-2 self-start sm:self-auto">
      <a href="/roles" class="btn-ghost">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        Roles &amp; Permissions
      </a>
      <button id="open-invite" class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        Invite User
      </button>
    </div>
  </div>

  <!-- Table -->
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-gray-100 bg-gray-50/60">
            <th class="px-5 py-3 font-semibold">Name</th>
            <th class="px-5 py-3 font-semibold">Email</th>
            <th class="px-5 py-3 font-semibold">Role</th>
            <th class="px-5 py-3 font-semibold">Status</th>
            <th class="px-5 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="u-rows" class="divide-y divide-gray-100"></tbody>
      </table>
    </div>
    <div id="u-empty" class="hidden py-16 text-center">
      <div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">
        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1z"/></svg>
      </div>
      <p class="text-gray-900 font-semibold">No team members yet</p>
      <p class="text-gray-500 text-sm mt-1">Invite recruiters and hiring managers to collaborate.</p>
    </div>
  </div>
</div>

<!-- Invite / Edit Modal -->
<div id="user-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card p-6">
    <div class="flex items-start justify-between mb-4">
      <h3 id="user-modal-title" class="text-lg font-bold text-gray-900">Invite User</h3>
      <button class="text-gray-400 hover:text-gray-600" data-modal-close="user-modal"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <form id="user-form" class="space-y-4">
      <input type="hidden" name="id" value="" />
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">First name <span class="text-red-500">*</span></label>
          <input name="first_name" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Last name <span class="text-red-500">*</span></label>
          <input name="last_name" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Email <span class="text-red-500">*</span></label>
        <input name="email" type="email" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
      </div>
      <div id="pw-wrap">
        <label class="block text-xs font-semibold text-gray-500 mb-1">Temporary password <span class="text-red-500">*</span></label>
        <input name="password" type="password" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="At least 8 characters" />
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Role</label>
        <select name="role_id" id="role-select" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          <option value="1">Admin</option>
          <option value="2" selected>Recruiter</option>
          <option value="3">Hiring Manager</option>
        </select>
      </div>
      <input type="hidden" name="_csrf" value="<?= e($csrf ?? '') ?>" />
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btn-ghost" data-modal-close="user-modal">Cancel</button>
        <button type="submit" id="user-submit" class="btn-primary">Send invite</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete confirm -->
<div id="del-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card p-6">
    <h3 class="text-lg font-bold text-gray-900">Remove user?</h3>
    <p id="del-text" class="text-sm text-gray-500 mt-2">This will revoke their access immediately.</p>
    <div class="mt-5 flex justify-end gap-2">
      <button class="btn-ghost" data-modal-close="del-modal">Cancel</button>
      <button id="del-confirm" class="btn-primary !bg-red-600 hover:!bg-red-700">Remove</button>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const rows = $('u-rows');
  let CACHE = [];
  let DEL_ID = null;

  const ROLE_MAP = { admin: { id: 1, cls: 'badge-violet' }, recruiter: { id: 2, cls: 'badge-blue' }, hiring_manager: { id: 3, cls: 'badge-gray' }, 'hiring manager': { id: 3, cls: 'badge-gray' } };

  function unwrap(d) {
    if (Array.isArray(d)) return d;
    if (d && Array.isArray(d.users)) return d.users;
    if (d && Array.isArray(d.data)) return d.data;
    return [];
  }
  function roleName(u) {
    if (Array.isArray(u.roles) && u.roles.length) {
      const r = u.roles[0];
      return (typeof r === 'string') ? r : (r.name || r.slug || 'Member');
    }
    return u.role || u.role_name || 'Member';
  }
  function roleBadge(name) {
    const key = String(name || '').toLowerCase().replace(/\s+/g, '_');
    const cls = (ROLE_MAP[key] || ROLE_MAP[String(name || '').toLowerCase()] || { cls: 'badge-gray' }).cls;
    return '<span class="badge ' + cls + ' capitalize">' + AR.esc(String(name).replace(/_/g, ' ')) + '</span>';
  }
  function roleIdFor(name) {
    const key = String(name || '').toLowerCase().replace(/\s+/g, '_');
    return (ROLE_MAP[key] || { id: 2 }).id;
  }

  function skeleton() {
    rows.innerHTML = Array.from({ length: 5 }).map(() =>
      '<tr>' + Array.from({ length: 5 }).map(() => '<td class="px-5 py-4"><div class="skeleton h-4 w-24"></div></td>').join('') + '</tr>').join('');
    $('u-empty').classList.add('hidden');
  }

  function rowHtml(u) {
    const name = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.email || 'User';
    const init = name.split(' ').map(s => s[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
    const isActive = (u.status || 'active') === 'active';
    const rname = roleName(u);
    return '<tr class="hover:bg-violet-50/40 transition" data-id="' + AR.esc(u.id) + '">' +
      '<td class="px-5 py-4"><div class="flex items-center gap-3">' +
        '<div class="w-9 h-9 rounded-full gradient-brand text-white flex items-center justify-center text-xs font-bold shrink-0">' + AR.esc(init) + '</div>' +
        '<div class="font-medium text-gray-900">' + AR.esc(name) + '</div></div></td>' +
      '<td class="px-5 py-4 text-gray-600">' + AR.esc(u.email || '—') + '</td>' +
      '<td class="px-5 py-4">' +
        '<select class="role-dd text-xs rounded-lg border border-gray-200 px-2 py-1 bg-white focus:ring-2 focus:ring-violet-500" data-id="' + AR.esc(u.id) + '">' +
          '<option value="1"' + (rname.toLowerCase() === 'admin' ? ' selected' : '') + '>Admin</option>' +
          '<option value="2"' + (rname.toLowerCase() === 'recruiter' ? ' selected' : '') + '>Recruiter</option>' +
          '<option value="3"' + (/hiring/i.test(rname) ? ' selected' : '') + '>Hiring Manager</option>' +
        '</select>' +
      '</td>' +
      '<td class="px-5 py-4">' +
        '<button class="status-toggle inline-flex items-center gap-2" data-id="' + AR.esc(u.id) + '" data-active="' + (isActive ? '1' : '0') + '">' +
          '<span class="relative inline-block w-9 h-5 rounded-full transition ' + (isActive ? 'bg-green-500' : 'bg-gray-300') + '">' +
            '<span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform ' + (isActive ? 'translate-x-4' : '') + '"></span>' +
          '</span>' +
          '<span class="text-xs ' + (isActive ? 'text-green-700' : 'text-gray-500') + '">' + (isActive ? 'Active' : 'Inactive') + '</span>' +
        '</button>' +
      '</td>' +
      '<td class="px-5 py-4"><div class="flex items-center justify-end gap-2">' +
        '<button class="btn-ghost !py-1.5 !px-3 text-xs" data-edit="' + AR.esc(u.id) + '">Edit</button>' +
        '<button class="btn-ghost !py-1.5 !px-2 text-xs !text-red-600 !border-red-200 hover:!bg-red-50" data-del="' + AR.esc(u.id) + '" title="Remove">' +
          '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
        '</button>' +
      '</div></td>' +
    '</tr>';
  }

  function render() {
    if (!CACHE.length) {
      rows.innerHTML = '';
      $('u-empty').classList.remove('hidden');
      return;
    }
    $('u-empty').classList.add('hidden');
    rows.innerHTML = CACHE.map(rowHtml).join('');
    bind();
  }

  function bind() {
    rows.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openEdit(b.getAttribute('data-edit'))));
    rows.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => { DEL_ID = b.getAttribute('data-del'); const u = CACHE.find(x => String(x.id) === String(DEL_ID)); $('del-text').textContent = 'This will revoke access for ' + (((u && (u.first_name + ' ' + u.last_name)) || 'this user').trim()) + ' immediately.'; AR.Modal.open('del-modal'); }));
    rows.querySelectorAll('.status-toggle').forEach(b => b.addEventListener('click', () => toggleStatus(b)));
    rows.querySelectorAll('.role-dd').forEach(s => s.addEventListener('change', () => changeRole(s.getAttribute('data-id'), s.value, s)));
  }

  async function toggleStatus(btn) {
    const id = btn.getAttribute('data-id');
    const wasActive = btn.getAttribute('data-active') === '1';
    const next = wasActive ? 'inactive' : 'active';
    try {
      await AR.Api.put('/users/' + encodeURIComponent(id), { status: next });
      const u = CACHE.find(x => String(x.id) === String(id));
      if (u) u.status = next;
      render();
      AR.Toast.success('User ' + (next === 'active' ? 'activated' : 'deactivated') + '.');
    } catch (e) {
      AR.Toast.error(e.message || 'Could not update status.');
    }
  }

  async function changeRole(id, roleId, sel) {
    sel.disabled = true;
    try {
      await AR.Api.put('/users/' + encodeURIComponent(id) + '/role', { role_id: Number(roleId) });
      AR.Toast.success('Role updated.');
    } catch (e) {
      AR.Toast.error(e.message || 'Could not change role.');
    } finally {
      sel.disabled = false;
    }
  }

  function openInvite() {
    $('user-modal-title').textContent = 'Invite User';
    $('user-submit').textContent = 'Send invite';
    const f = $('user-form');
    f.reset();
    f.elements['id'].value = '';
    $('pw-wrap').style.display = '';
    f.elements['password'].required = true;
    AR.Modal.open('user-modal');
  }

  function openEdit(id) {
    const u = CACHE.find(x => String(x.id) === String(id));
    if (!u) return;
    $('user-modal-title').textContent = 'Edit User';
    $('user-submit').textContent = 'Save changes';
    const f = $('user-form');
    f.reset();
    f.elements['id'].value = u.id;
    f.elements['first_name'].value = u.first_name || '';
    f.elements['last_name'].value = u.last_name || '';
    f.elements['email'].value = u.email || '';
    f.elements['role_id'].value = roleIdFor(roleName(u));
    $('pw-wrap').style.display = 'none';
    f.elements['password'].required = false;
    AR.Modal.open('user-modal');
  }

  async function load() {
    skeleton();
    try {
      CACHE = unwrap(await AR.Api.get('/users'));
      render();
    } catch (e) {
      rows.innerHTML = '<tr><td colspan="5" class="px-5 py-12 text-center text-red-600">' +
        '<div class="font-semibold">Could not load users</div>' +
        '<div class="text-sm text-gray-500 mt-1">' + AR.esc(e.message || 'Please try again.') + '</div></td></tr>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    $('open-invite').addEventListener('click', openInvite);

    $('user-form').addEventListener('submit', async function (e) {
      e.preventDefault();
      const f = e.target;
      const id = f.elements['id'].value;
      const payload = {
        first_name: f.elements['first_name'].value.trim(),
        last_name: f.elements['last_name'].value.trim(),
        email: f.elements['email'].value.trim(),
        role_id: Number(f.elements['role_id'].value)
      };
      const pw = f.elements['password'].value;
      if (pw) payload.password = pw;
      const btn = $('user-submit');
      btn.disabled = true; const old = btn.textContent; btn.textContent = 'Saving…';
      try {
        if (id) {
          await AR.Api.put('/users/' + encodeURIComponent(id), payload);
          AR.Toast.success('User updated.');
        } else {
          await AR.Api.post('/users', payload);
          AR.Toast.success('Invitation sent.');
        }
        AR.Modal.close('user-modal');
        load();
      } catch (err) {
        AR.Toast.error(err.message || 'Could not save user.');
      } finally {
        btn.disabled = false; btn.textContent = old;
      }
    });

    $('del-confirm').addEventListener('click', async function () {
      if (!DEL_ID) return;
      const btn = this; btn.disabled = true;
      try {
        await AR.Api.del('/users/' + encodeURIComponent(DEL_ID));
        AR.Toast.success('User removed.');
        AR.Modal.close('del-modal');
        load();
      } catch (e) {
        AR.Toast.error(e.message || 'Could not remove user.');
      } finally {
        btn.disabled = false; DEL_ID = null;
      }
    });

    load();
  });
})();
</script>
