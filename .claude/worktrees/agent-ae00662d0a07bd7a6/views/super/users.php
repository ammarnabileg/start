<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6">
  <!-- Page Header -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Platform Users</h1>
      <p class="mt-1 text-sm text-gray-500">Manage all users across the platform</p>
    </div>
    <div class="text-sm text-gray-500">
      Logged in as <span class="font-medium text-gray-700"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
    </div>
  </div>

  <!-- Toast Notification -->
  <div id="toast" class="fixed top-4 right-4 z-50 hidden max-w-sm w-full">
    <div id="toast-inner" class="flex items-center p-4 rounded-lg shadow-lg text-white text-sm font-medium">
      <span id="toast-icon" class="mr-3 text-lg"></span>
      <span id="toast-message"></span>
      <button onclick="hideToast()" class="ml-auto text-white/80 hover:text-white">&times;</button>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Search -->
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
        </div>
        <input
          type="text"
          id="filter-search"
          placeholder="Search name or email..."
          class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
        />
      </div>

      <!-- Company Filter -->
      <div>
        <select
          id="filter-company"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
        >
          <option value="">All Companies</option>
        </select>
      </div>

      <!-- Type Filter -->
      <div>
        <select
          id="filter-type"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
        >
          <option value="">All Types</option>
          <option value="super_admin">Super Admin</option>
          <option value="hr">HR</option>
          <option value="candidate">Candidate</option>
        </select>
      </div>

      <!-- Status Filter -->
      <div>
        <select
          id="filter-status"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
        >
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Users Table -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <!-- Table Header -->
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-gray-700">
        Users <span id="user-count" class="ml-1 text-xs font-normal text-gray-400"></span>
      </h2>
      <button
        onclick="loadUsers()"
        class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
      >
        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        Refresh
      </button>
    </div>

    <!-- Loading State -->
    <div id="table-loading" class="flex items-center justify-center py-16 hidden">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
      <span class="ml-3 text-sm text-gray-500">Loading users...</span>
    </div>

    <!-- Empty State -->
    <div id="table-empty" class="flex flex-col items-center justify-center py-16 hidden">
      <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      <p class="text-sm font-medium text-gray-500">No users found</p>
      <p class="text-xs text-gray-400 mt-1">Try adjusting your filters</p>
    </div>

    <!-- Table -->
    <div id="table-wrapper" class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Login</th>
            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody id="users-tbody" class="bg-white divide-y divide-gray-100">
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div id="pagination" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between hidden">
      <div class="text-sm text-gray-500">
        Showing <span id="page-from" class="font-medium text-gray-700"></span>&#8211;<span id="page-to" class="font-medium text-gray-700"></span>
        of <span id="page-total" class="font-medium text-gray-700"></span> users
      </div>
      <div class="flex items-center gap-2">
        <button
          id="btn-prev"
          onclick="changePage(-1)"
          class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
          disabled
        >
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
          Prev
        </button>
        <span id="page-info" class="text-sm text-gray-600 px-2"></span>
        <button
          id="btn-next"
          onclick="changePage(1)"
          class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition"
          disabled
        >
          Next
          <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div id="edit-modal" class="fixed inset-0 z-40 hidden" aria-modal="true" role="dialog">
  <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeEditModal()"></div>
  <div class="relative z-50 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
      <!-- Modal Header -->
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">Edit User</h3>
          <p class="text-xs text-gray-500 mt-0.5">Update user profile details</p>
        </div>
        <button
          onclick="closeEditModal()"
          class="text-gray-400 hover:text-gray-600 transition rounded-lg p-1 hover:bg-gray-100"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Modal Body -->
      <form id="edit-form" onsubmit="submitEdit(event)" class="px-6 py-5 space-y-4">
        <input type="hidden" id="edit-user-id" />

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
            <input
              type="text"
              id="edit-first-name"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
            <input
              type="text"
              id="edit-last-name"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
          <input
            type="email"
            id="edit-email"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">User Type</label>
          <select
            id="edit-type"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
          >
            <option value="super_admin">Super Admin</option>
            <option value="hr">HR</option>
            <option value="candidate">Candidate</option>
          </select>
        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-end gap-3 pt-2">
          <button
            type="button"
            onclick="closeEditModal()"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
          >
            Cancel
          </button>
          <button
            type="submit"
            id="edit-submit-btn"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <span id="edit-btn-text">Save Changes</span>
            <svg id="edit-btn-spinner" class="hidden animate-spin ml-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var CSRF = function () { return document.querySelector('meta[name=csrf]').content; };

  // State
  var currentPage = 1;
  var totalPages = 1;
  var totalUsers = 0;
  var perPage = 20;
  var searchDebounceTimer = null;

  // ---------- Toast ----------
  window.showToast = function (message, type) {
    type = type || 'success';
    var toast = document.getElementById('toast');
    var inner = document.getElementById('toast-inner');
    var icon  = document.getElementById('toast-icon');
    var msg   = document.getElementById('toast-message');

    msg.textContent = message;

    if (type === 'success') {
      inner.className = 'flex items-center p-4 rounded-lg shadow-lg text-white text-sm font-medium bg-emerald-600';
      icon.textContent = '✓';
    } else {
      inner.className = 'flex items-center p-4 rounded-lg shadow-lg text-white text-sm font-medium bg-red-600';
      icon.textContent = '✕';
    }

    toast.classList.remove('hidden');
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(hideToast, 4000);
  };

  window.hideToast = function () {
    document.getElementById('toast').classList.add('hidden');
  };

  // ---------- Load Companies for filter ----------
  function loadCompanies() {
    fetch('/api/v1/super/tenants?per_page=200', {
      headers: { 'X-CSRF-Token': CSRF() }
    })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      if (json.ok && json.data) {
        var items = json.data.items || json.data || [];
        var select = document.getElementById('filter-company');
        items.forEach(function (c) {
          var opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = c.name;
          select.appendChild(opt);
        });
      }
    })
    .catch(function () { /* non-critical */ });
  }

  // ---------- Load Users ----------
  window.loadUsers = function () {
    var search    = document.getElementById('filter-search').value.trim();
    var companyId = document.getElementById('filter-company').value;
    var type      = document.getElementById('filter-type').value;
    var status    = document.getElementById('filter-status').value;

    var params = new URLSearchParams({ page: currentPage, per_page: perPage });
    if (search)    params.set('search',     search);
    if (companyId) params.set('company_id', companyId);
    if (type)      params.set('type',       type);
    if (status)    params.set('status',     status);

    setLoading(true);

    fetch('/api/v1/super/users?' + params.toString(), {
      headers: { 'X-CSRF-Token': CSRF() }
    })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      if (!json.ok) throw new Error(json.message || 'Failed to load users');

      var data  = json.data || {};
      var items = data.items || data || [];
      totalUsers = data.total || items.length;
      totalPages = data.pages || Math.ceil(totalUsers / perPage) || 1;

      renderTable(items);
      renderPagination(items.length);
      setLoading(false);
    })
    .catch(function (err) {
      showToast(err.message || 'Error loading users', 'error');
      renderTable([]);
      setLoading(false);
    });
  };

  function setLoading(on) {
    document.getElementById('table-loading').classList.toggle('hidden', !on);
    document.getElementById('table-wrapper').classList.toggle('hidden', on);
  }

  function renderTable(items) {
    var tbody = document.getElementById('users-tbody');
    var empty = document.getElementById('table-empty');
    var count = document.getElementById('user-count');

    count.textContent = totalUsers > 0 ? '(' + totalUsers + ')' : '';

    if (!items.length) {
      tbody.innerHTML = '';
      empty.classList.remove('hidden');
      document.getElementById('pagination').classList.add('hidden');
      return;
    }

    empty.classList.add('hidden');

    tbody.innerHTML = items.map(function (u) {
      var userData = JSON.stringify(u).replace(/\\/g, '\\\\').replace(/"/g, '&quot;');
      return '<tr class="hover:bg-gray-50 transition-colors">'
        + '<td class="px-6 py-4 whitespace-nowrap">'
        +   '<div class="flex items-center">'
        +     '<div class="h-9 w-9 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">'
        +       '<span class="text-xs font-semibold text-indigo-700">' + initials(u.first_name, u.last_name) + '</span>'
        +     '</div>'
        +     '<div class="ml-3">'
        +       '<p class="text-sm font-medium text-gray-900">' + esc(u.first_name) + ' ' + esc(u.last_name) + '</p>'
        +       '<p class="text-xs text-gray-400">#' + u.id + '</p>'
        +     '</div>'
        +   '</div>'
        + '</td>'
        + '<td class="px-6 py-4 whitespace-nowrap"><span class="text-sm text-gray-700">' + esc(u.email) + '</span></td>'
        + '<td class="px-6 py-4 whitespace-nowrap"><span class="text-sm text-gray-600">' + esc(u.company_name || u.tenant_name || '—') + '</span></td>'
        + '<td class="px-6 py-4 whitespace-nowrap">' + typeBadge(u.type) + '</td>'
        + '<td class="px-6 py-4 whitespace-nowrap">' + statusBadge(u.status) + '</td>'
        + '<td class="px-6 py-4 whitespace-nowrap"><span class="text-sm text-gray-500">' + formatDate(u.last_login_at || u.last_login) + '</span></td>'
        + '<td class="px-6 py-4 whitespace-nowrap text-right">'
        +   '<div class="flex items-center justify-end gap-2">'
        +     '<button onclick="openEditModal(' + userData + ')" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition">'
        +       '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>'
        +       'Edit'
        +     '</button>'
        +     (u.status === 'suspended'
        ?       '<button onclick="activateUser(' + u.id + ', this)" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition">'
        +         '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        +         'Activate'
        +       '</button>'
        :       '<button onclick="suspendUser(' + u.id + ', this)" class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition">'
        +         '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>'
        +         'Suspend'
        +       '</button>'
        )
        +   '</div>'
        + '</td>'
        + '</tr>';
    }).join('');
  }

  function renderPagination(count) {
    var pg = document.getElementById('pagination');
    if (totalUsers === 0) { pg.classList.add('hidden'); return; }

    pg.classList.remove('hidden');
    var from = (currentPage - 1) * perPage + 1;
    var to   = Math.min(currentPage * perPage, totalUsers);

    document.getElementById('page-from').textContent  = from;
    document.getElementById('page-to').textContent    = to;
    document.getElementById('page-total').textContent = totalUsers;
    document.getElementById('page-info').textContent  = 'Page ' + currentPage + ' of ' + totalPages;

    document.getElementById('btn-prev').disabled = currentPage <= 1;
    document.getElementById('btn-next').disabled = currentPage >= totalPages;
  }

  window.changePage = function (delta) {
    var next = currentPage + delta;
    if (next < 1 || next > totalPages) return;
    currentPage = next;
    loadUsers();
  };

  // ---------- Helpers ----------
  function esc(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function initials(first, last) {
    return ((first || '').charAt(0) + (last || '').charAt(0)).toUpperCase();
  }

  function typeBadge(type) {
    var map   = { super_admin: 'bg-red-100 text-red-800', hr: 'bg-blue-100 text-blue-800', candidate: 'bg-green-100 text-green-800' };
    var label = { super_admin: 'Super Admin', hr: 'HR', candidate: 'Candidate' };
    var cls   = map[type] || 'bg-gray-100 text-gray-700';
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ' + cls + '">' + esc(label[type] || type) + '</span>';
  }

  function statusBadge(status) {
    if (status === 'suspended') {
      return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800"><span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5 inline-block"></span>Suspended</span>';
    }
    return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><span class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5 inline-block"></span>Active</span>';
  }

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
      var d = new Date(dateStr);
      if (isNaN(d)) return '—';
      return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    } catch (e) { return '—'; }
  }

  // ---------- Edit Modal ----------
  window.openEditModal = function (user) {
    document.getElementById('edit-user-id').value    = user.id;
    document.getElementById('edit-first-name').value = user.first_name || '';
    document.getElementById('edit-last-name').value  = user.last_name  || '';
    document.getElementById('edit-email').value      = user.email      || '';
    document.getElementById('edit-type').value       = user.type       || 'candidate';
    document.getElementById('edit-modal').classList.remove('hidden');
  };

  window.closeEditModal = function () {
    document.getElementById('edit-modal').classList.add('hidden');
  };

  window.submitEdit = function (e) {
    e.preventDefault();
    var id      = document.getElementById('edit-user-id').value;
    var btn     = document.getElementById('edit-submit-btn');
    var spinner = document.getElementById('edit-btn-spinner');
    var btnText = document.getElementById('edit-btn-text');

    btn.disabled = true;
    spinner.classList.remove('hidden');
    btnText.textContent = 'Saving…';

    var body = {
      first_name: document.getElementById('edit-first-name').value.trim(),
      last_name:  document.getElementById('edit-last-name').value.trim(),
      email:      document.getElementById('edit-email').value.trim(),
      type:       document.getElementById('edit-type').value,
    };

    fetch('/api/v1/super/users/' + id, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF(),
      },
      body: JSON.stringify(body),
    })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      if (!json.ok) throw new Error(json.message || 'Update failed');
      closeEditModal();
      showToast('User updated successfully');
      loadUsers();
    })
    .catch(function (err) {
      showToast(err.message || 'Failed to update user', 'error');
    })
    .finally(function () {
      btn.disabled = false;
      spinner.classList.add('hidden');
      btnText.textContent = 'Save Changes';
    });
  };

  // ---------- Suspend / Activate ----------
  window.suspendUser = function (id, btn) {
    if (!confirm('Suspend this user? They will lose access to the platform.')) return;
    btn.disabled = true;

    fetch('/api/v1/super/users/' + id + '/suspend', {
      method: 'POST',
      headers: { 'X-CSRF-Token': CSRF() },
    })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      if (!json.ok) throw new Error(json.message || 'Suspend failed');
      showToast('User suspended');
      loadUsers();
    })
    .catch(function (err) {
      showToast(err.message || 'Failed to suspend user', 'error');
      btn.disabled = false;
    });
  };

  window.activateUser = function (id, btn) {
    btn.disabled = true;

    fetch('/api/v1/super/users/' + id + '/activate', {
      method: 'POST',
      headers: { 'X-CSRF-Token': CSRF() },
    })
    .then(function (res) { return res.json(); })
    .then(function (json) {
      if (!json.ok) throw new Error(json.message || 'Activate failed');
      showToast('User activated');
      loadUsers();
    })
    .catch(function (err) {
      showToast(err.message || 'Failed to activate user', 'error');
      btn.disabled = false;
    });
  };

  // ---------- Filter listeners ----------
  document.getElementById('filter-search').addEventListener('input', function () {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(function () {
      currentPage = 1;
      loadUsers();
    }, 400);
  });

  ['filter-company', 'filter-type', 'filter-status'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', function () {
      currentPage = 1;
      loadUsers();
    });
  });

  // Close modal on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeEditModal();
  });

  // ---------- Init ----------
  loadCompanies();
  loadUsers();
})();
</script>
