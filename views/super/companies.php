<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="min-h-screen bg-gray-50">
  <!-- Toast Notification -->
  <div id="toast" class="fixed top-4 right-4 z-50 hidden">
    <div id="toast-inner" class="flex items-center gap-3 px-5 py-3 rounded-lg shadow-lg text-white text-sm font-medium transition-all duration-300">
      <span id="toast-icon"></span>
      <span id="toast-msg"></span>
    </div>
  </div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Companies</h1>
        <p class="mt-1 text-sm text-gray-500">Manage all tenant companies on the platform</p>
      </div>
      <button onclick="openCreateModal()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg shadow-sm transition-colors duration-150">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Create Company
      </button>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
      <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
          </svg>
          <input id="search-input" type="text" placeholder="Search companies by name or slug..." oninput="filterCompanies()" class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"/>
        </div>
        <select id="plan-filter" onchange="filterCompanies()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
          <option value="">All Plans</option>
          <option value="basic">Basic</option>
          <option value="pro">Pro</option>
          <option value="enterprise">Enterprise</option>
        </select>
        <select id="status-filter" onchange="filterCompanies()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
        </select>
        <button onclick="loadCompanies()" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          Refresh
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div id="table-loading" class="flex items-center justify-center py-16">
        <div class="flex flex-col items-center gap-3">
          <svg class="animate-spin w-8 h-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
          </svg>
          <span class="text-sm text-gray-500">Loading companies...</span>
        </div>
      </div>
      <div id="table-empty" class="flex items-center justify-center py-16 hidden">
        <div class="text-center">
          <svg class="mx-auto w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21H5a2 2 0 01-2-2V7a2 2 0 012-2h4l2-3h4l2 3h4a2 2 0 012 2v12a2 2 0 01-2 2z"/>
          </svg>
          <p class="text-gray-500 text-sm font-medium">No companies found</p>
          <p class="text-gray-400 text-xs mt-1">Try adjusting your search or filters</p>
        </div>
      </div>
      <div id="table-wrap" class="overflow-x-auto hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Slug</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Users</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Jobs</th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Joined</th>
              <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody id="companies-tbody" class="bg-white divide-y divide-gray-100"></tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div id="pagination" class="flex items-center justify-between px-6 py-3 border-t border-gray-200 bg-gray-50 hidden">
        <span id="pagination-info" class="text-sm text-gray-600"></span>
        <div class="flex gap-2">
          <button id="prev-btn" onclick="changePage(-1)" class="px-3 py-1.5 text-sm border border-gray-300 rounded-md text-gray-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">Previous</button>
          <button id="next-btn" onclick="changePage(1)" class="px-3 py-1.5 text-sm border border-gray-300 rounded-md text-gray-600 hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed">Next</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ======== CREATE COMPANY MODAL ======== -->
<div id="create-modal" class="fixed inset-0 z-40 hidden">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeCreateModal()"></div>
  <div class="relative flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 max-h-screen overflow-y-auto">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white rounded-t-2xl">
        <h2 class="text-lg font-semibold text-gray-900">Create New Company</h2>
        <button onclick="closeCreateModal()" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <form id="create-form" onsubmit="submitCreateCompany(event)" class="px-6 py-5 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Company Name <span class="text-red-500">*</span></label>
          <input type="text" id="c-company_name" oninput="autoSlug()" required placeholder="Acme Corp"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-red-500">*</span></label>
          <div class="flex gap-2">
            <input type="text" id="c-slug" required placeholder="acme-corp"
              class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"/>
            <button type="button" onclick="autoSlug()"
              class="px-3 py-2 border border-gray-300 rounded-lg text-xs text-gray-600 hover:bg-gray-50 whitespace-nowrap">Auto</button>
          </div>
          <p class="mt-1 text-xs text-gray-400">Lowercase letters, numbers, and hyphens only</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Plan <span class="text-red-500">*</span></label>
          <select id="c-plan" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
            <option value="">Select a plan</option>
            <option value="basic">Basic</option>
            <option value="pro">Pro</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
        <div class="border-t border-gray-100 pt-4">
          <p class="text-sm font-semibold text-gray-700 mb-3">Owner Account</p>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Owner Email <span class="text-red-500">*</span></label>
              <input type="email" id="c-owner_email" required placeholder="owner@company.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                <input type="text" id="c-owner_first_name" required placeholder="Jane"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                <input type="text" id="c-owner_last_name" required placeholder="Doe"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"/>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-2 pb-1">
          <button type="button" onclick="closeCreateModal()"
            class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
          <button type="submit" id="create-submit-btn"
            class="px-5 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
            <span id="create-btn-text">Create Company</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ======== COMPANY DETAIL MODAL ======== -->
<div id="detail-modal" class="fixed inset-0 z-40 hidden">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeDetailModal()"></div>
  <div class="relative flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl relative z-10">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900" id="detail-title">Company Details</h2>
        <button onclick="closeDetailModal()" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div id="detail-content" class="px-6 py-5"></div>
    </div>
  </div>
</div>

<!-- ======== EDIT PLAN MODAL ======== -->
<div id="plan-modal" class="fixed inset-0 z-40 hidden">
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closePlanModal()"></div>
  <div class="relative flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm relative z-10">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Change Plan</h2>
        <button onclick="closePlanModal()" class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="px-6 py-5">
        <p class="text-sm text-gray-600 mb-4">Update the subscription plan for <strong id="plan-company-name" class="text-gray-900"></strong>.</p>
        <input type="hidden" id="plan-company-id"/>
        <div class="mb-5 space-y-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">Select New Plan</label>
          <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-indigo-300 transition-colors">
            <input type="radio" name="new-plan" value="basic" class="accent-indigo-600"/>
            <div>
              <div class="text-sm font-medium text-gray-900">Basic</div>
              <div class="text-xs text-gray-500">Essential recruitment features for small teams</div>
            </div>
          </label>
          <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-indigo-300 transition-colors">
            <input type="radio" name="new-plan" value="pro" class="accent-indigo-600"/>
            <div>
              <div class="text-sm font-medium text-gray-900">Pro</div>
              <div class="text-xs text-gray-500">Advanced AI interviews &amp; analytics for growing teams</div>
            </div>
          </label>
          <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-indigo-300 transition-colors">
            <input type="radio" name="new-plan" value="enterprise" class="accent-indigo-600"/>
            <div>
              <div class="text-sm font-medium text-gray-900">Enterprise</div>
              <div class="text-xs text-gray-500">Full suite with custom AI models &amp; dedicated support</div>
            </div>
          </label>
        </div>
        <div class="flex justify-end gap-3">
          <button onclick="closePlanModal()"
            class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
          <button onclick="submitPlanChange()" id="plan-submit-btn"
            class="px-5 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors disabled:opacity-60">
            Update Plan
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
var CSRF = function() { return document.querySelector('meta[name=csrf]').content; };

var allCompanies = [];
var currentPage = 1;
var perPage = 15;

// ── Toast ──────────────────────────────────────────────
function showToast(msg, type) {
  type = type || 'success';
  var toast = document.getElementById('toast');
  var inner = document.getElementById('toast-inner');
  var icon  = document.getElementById('toast-icon');
  var text  = document.getElementById('toast-msg');
  var styles = { success: { bg: 'bg-green-600', icon: '✓' }, error: { bg: 'bg-red-600', icon: '✕' }, info: { bg: 'bg-indigo-600', icon: 'ℹ' } };
  var s = styles[type] || styles.info;
  inner.className = 'flex items-center gap-3 px-5 py-3 rounded-lg shadow-lg text-white text-sm font-medium ' + s.bg;
  icon.textContent = s.icon;
  text.textContent = msg;
  toast.classList.remove('hidden');
  clearTimeout(toast._t);
  toast._t = setTimeout(function() { toast.classList.add('hidden'); }, 3500);
}

// ── HTML escape ────────────────────────────────────────
function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Slug generator ─────────────────────────────────────
function autoSlug() {
  var name = document.getElementById('c-company_name').value;
  var slug = name.toLowerCase().trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
  document.getElementById('c-slug').value = slug;
}

// ── Badges ─────────────────────────────────────────────
function planBadge(plan) {
  var map = { basic: 'bg-gray-100 text-gray-700', pro: 'bg-blue-100 text-blue-700', enterprise: 'bg-purple-100 text-purple-700' };
  var cls = map[plan] || 'bg-gray-100 text-gray-700';
  return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' + cls + ' capitalize">' + escHtml(plan || '—') + '</span>';
}

function statusBadge(status) {
  var map = { active: 'bg-green-100 text-green-700', suspended: 'bg-red-100 text-red-700' };
  var cls = map[status] || 'bg-gray-100 text-gray-600';
  return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' + cls + ' capitalize">' + escHtml(status || '—') + '</span>';
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// ── Load companies ─────────────────────────────────────
async function loadCompanies() {
  document.getElementById('table-loading').classList.remove('hidden');
  document.getElementById('table-empty').classList.add('hidden');
  document.getElementById('table-wrap').classList.add('hidden');
  document.getElementById('pagination').classList.add('hidden');
  try {
    var res = await fetch('/api/v1/super/companies', {
      headers: { 'X-CSRF-Token': CSRF(), 'Accept': 'application/json' }
    });
    var json = await res.json();
    if (!json.ok) throw new Error(json.message || 'Failed to load companies');
    allCompanies = json.data || [];
    currentPage = 1;
    renderTable();
  } catch (err) {
    showToast(err.message, 'error');
    document.getElementById('table-loading').classList.add('hidden');
    document.getElementById('table-empty').classList.remove('hidden');
  }
}

// ── Filter ─────────────────────────────────────────────
function filterCompanies() { currentPage = 1; renderTable(); }

function getFiltered() {
  var search = document.getElementById('search-input').value.toLowerCase().trim();
  var plan   = document.getElementById('plan-filter').value;
  var status = document.getElementById('status-filter').value;
  return allCompanies.filter(function(c) {
    var ms = !search || (c.company_name||'').toLowerCase().indexOf(search) !== -1 || (c.slug||'').toLowerCase().indexOf(search) !== -1;
    var mp = !plan   || c.plan === plan;
    var mv = !status || c.status === status;
    return ms && mp && mv;
  });
}

// ── Render table ───────────────────────────────────────
function renderTable() {
  var filtered = getFiltered();
  var total    = filtered.length;
  var start    = (currentPage - 1) * perPage;
  var end      = Math.min(start + perPage, total);
  var page     = filtered.slice(start, end);
  var tbody    = document.getElementById('companies-tbody');
  tbody.innerHTML = '';

  document.getElementById('table-loading').classList.add('hidden');

  if (total === 0) {
    document.getElementById('table-empty').classList.remove('hidden');
    document.getElementById('table-wrap').classList.add('hidden');
    document.getElementById('pagination').classList.add('hidden');
    return;
  }

  document.getElementById('table-empty').classList.add('hidden');
  document.getElementById('table-wrap').classList.remove('hidden');

  page.forEach(function(c) {
    var suspended = c.status === 'suspended';
    var initial = (c.company_name || '?')[0].toUpperCase();
    var toggleCls = suspended ? 'bg-green-50 hover:bg-green-100 text-green-700' : 'bg-red-50 hover:bg-red-100 text-red-700';
    var toggleLabel = suspended ? 'Activate' : 'Suspend';

    var row = document.createElement('tr');
    row.className = 'hover:bg-gray-50 transition-colors';
    row.innerHTML =
      '<td class="px-6 py-4 whitespace-nowrap">' +
        '<div class="flex items-center gap-3">' +
          '<div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">' +
            '<span class="text-indigo-700 font-semibold text-sm">' + initial + '</span>' +
          '</div>' +
          '<div>' +
            '<div class="text-sm font-semibold text-gray-900">' + escHtml(c.company_name || '') + '</div>' +
            '<div class="text-xs text-gray-400">ID #' + c.id + '</div>' +
          '</div>' +
        '</div>' +
      '</td>' +
      '<td class="px-6 py-4 whitespace-nowrap"><code class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">' + escHtml(c.slug || '') + '</code></td>' +
      '<td class="px-6 py-4 whitespace-nowrap">' + planBadge(c.plan) + '</td>' +
      '<td class="px-6 py-4 whitespace-nowrap">' + statusBadge(c.status) + '</td>' +
      '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">' + (c.users_count !== undefined ? c.users_count : '—') + '</td>' +
      '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">' + (c.jobs_count !== undefined ? c.jobs_count : '—') + '</td>' +
      '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' + fmtDate(c.created_at) + '</td>' +
      '<td class="px-6 py-4 whitespace-nowrap text-right">' +
        '<div class="flex items-center justify-end gap-2">' +
          '<button data-action="view" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md transition-colors">' +
            '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>' +
            ' View' +
          '</button>' +
          '<button data-action="plan" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-md transition-colors">' +
            '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>' +
            ' Plan' +
          '</button>' +
          '<button data-action="toggle" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium ' + toggleCls + ' rounded-md transition-colors">' +
            toggleLabel +
          '</button>' +
        '</div>' +
      '</td>';

    // Attach event listeners with closure over c
    (function(company) {
      row.querySelector('[data-action=view]').addEventListener('click', function() { openDetailModal(company); });
      row.querySelector('[data-action=plan]').addEventListener('click', function() { openPlanModal(company); });
      row.querySelector('[data-action=toggle]').addEventListener('click', function() { toggleStatus(company.id, company.status, company.company_name); });
    })(c);

    tbody.appendChild(row);
  });

  if (total > perPage) {
    var totalPages = Math.ceil(total / perPage);
    document.getElementById('pagination-info').textContent = 'Showing ' + (start + 1) + '–' + end + ' of ' + total + ' companies';
    document.getElementById('prev-btn').disabled = currentPage === 1;
    document.getElementById('next-btn').disabled = currentPage >= totalPages;
    document.getElementById('pagination').classList.remove('hidden');
  } else {
    document.getElementById('pagination').classList.add('hidden');
  }
}

function changePage(dir) {
  var total = getFiltered().length;
  var totalPages = Math.ceil(total / perPage);
  currentPage = Math.max(1, Math.min(currentPage + dir, totalPages));
  renderTable();
}

// ── Create modal ───────────────────────────────────────
function openCreateModal() {
  document.getElementById('create-form').reset();
  document.getElementById('create-modal').classList.remove('hidden');
}
function closeCreateModal() {
  document.getElementById('create-modal').classList.add('hidden');
}

async function submitCreateCompany(e) {
  e.preventDefault();
  var btn    = document.getElementById('create-submit-btn');
  var btnTxt = document.getElementById('create-btn-text');
  btn.disabled = true;
  btnTxt.textContent = 'Creating...';
  var payload = {
    company_name:     document.getElementById('c-company_name').value.trim(),
    slug:             document.getElementById('c-slug').value.trim(),
    plan:             document.getElementById('c-plan').value,
    owner_email:      document.getElementById('c-owner_email').value.trim(),
    owner_first_name: document.getElementById('c-owner_first_name').value.trim(),
    owner_last_name:  document.getElementById('c-owner_last_name').value.trim(),
  };
  try {
    var res  = await fetch('/api/v1/super/companies', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF() },
      body: JSON.stringify(payload),
    });
    var json = await res.json();
    if (!json.ok) throw new Error(json.message || 'Failed to create company');
    showToast('Company created successfully!', 'success');
    closeCreateModal();
    loadCompanies();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    btn.disabled = false;
    btnTxt.textContent = 'Create Company';
  }
}

// ── Detail modal ───────────────────────────────────────
function openDetailModal(company) {
  document.getElementById('detail-title').textContent = company.company_name || 'Company Details';
  var content = document.getElementById('detail-content');
  var initial = (company.company_name || '?')[0].toUpperCase();

  content.innerHTML =
    '<div class="space-y-6">' +
      '<div class="flex items-start gap-4 pb-2">' +
        '<div class="w-14 h-14 rounded-xl bg-indigo-100 flex items-center justify-center flex-shrink-0">' +
          '<span class="text-indigo-700 font-bold text-xl">' + initial + '</span>' +
        '</div>' +
        '<div class="flex-1 min-w-0">' +
          '<h3 class="text-lg font-semibold text-gray-900 truncate">' + escHtml(company.company_name || '') + '</h3>' +
          '<p class="text-sm text-gray-500 font-mono">' + escHtml(company.slug || '') + '</p>' +
          '<div class="flex items-center flex-wrap gap-2 mt-2">' +
            planBadge(company.plan) + statusBadge(company.status) +
          '</div>' +
        '</div>' +
      '</div>' +
      '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">' +
        '<div class="bg-indigo-50 rounded-xl p-4 text-center">' +
          '<div class="text-2xl font-bold text-indigo-600">' + (company.users_count || 0) + '</div>' +
          '<div class="text-xs text-gray-500 mt-1 font-medium">Users</div>' +
        '</div>' +
        '<div class="bg-blue-50 rounded-xl p-4 text-center">' +
          '<div class="text-2xl font-bold text-blue-600">' + (company.jobs_count || 0) + '</div>' +
          '<div class="text-xs text-gray-500 mt-1 font-medium">Jobs</div>' +
        '</div>' +
        '<div class="bg-green-50 rounded-xl p-4 text-center">' +
          '<div class="text-2xl font-bold text-green-600">' + (company.interviews_count || 0) + '</div>' +
          '<div class="text-xs text-gray-500 mt-1 font-medium">Interviews</div>' +
        '</div>' +
        '<div class="bg-purple-50 rounded-xl p-4 text-center">' +
          '<div class="text-2xl font-bold text-purple-600">' + (company.tokens_used || 0) + '</div>' +
          '<div class="text-xs text-gray-500 mt-1 font-medium">Tokens Used</div>' +
        '</div>' +
      '</div>' +
      '<div class="border border-gray-200 rounded-xl divide-y divide-gray-100">' +
        '<div class="flex items-center justify-between px-4 py-3">' +
          '<span class="text-sm text-gray-500">Current Plan</span>' +
          planBadge(company.plan) +
        '</div>' +
        '<div class="flex items-center justify-between px-4 py-3">' +
          '<span class="text-sm text-gray-500">Tenant ID</span>' +
          '<code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-700">' + escHtml(String(company.tenant_id || company.id || '—')) + '</code>' +
        '</div>' +
        '<div class="flex items-center justify-between px-4 py-3">' +
          '<span class="text-sm text-gray-500">Joined</span>' +
          '<span class="text-sm text-gray-900">' + fmtDate(company.created_at) + '</span>' +
        '</div>' +
        '<div class="flex items-center justify-between px-4 py-3">' +
          '<span class="text-sm text-gray-500">Last Updated</span>' +
          '<span class="text-sm text-gray-900">' + fmtDate(company.updated_at) + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="flex justify-end gap-3">' +
        '<button id="detail-plan-btn" class="px-4 py-2 text-sm font-medium bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg transition-colors">Edit Plan</button>' +
        '<button onclick="closeDetailModal()" class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Close</button>' +
      '</div>' +
    '</div>';

  document.getElementById('detail-plan-btn').addEventListener('click', function() {
    closeDetailModal();
    openPlanModal(company);
  });

  document.getElementById('detail-modal').classList.remove('hidden');
}
function closeDetailModal() {
  document.getElementById('detail-modal').classList.add('hidden');
}

// ── Plan modal ─────────────────────────────────────────
function openPlanModal(company) {
  document.getElementById('plan-company-id').value = company.id;
  document.getElementById('plan-company-name').textContent = company.company_name || '';
  document.querySelectorAll('input[name="new-plan"]').forEach(function(r) { r.checked = r.value === company.plan; });
  document.getElementById('plan-modal').classList.remove('hidden');
}
function closePlanModal() {
  document.getElementById('plan-modal').classList.add('hidden');
}

async function submitPlanChange() {
  var id    = document.getElementById('plan-company-id').value;
  var planEl = document.querySelector('input[name="new-plan"]:checked');
  if (!planEl) { showToast('Please select a plan', 'error'); return; }
  var plan = planEl.value;
  var btn = document.getElementById('plan-submit-btn');
  btn.disabled = true;
  btn.textContent = 'Updating...';
  try {
    var res  = await fetch('/api/v1/super/companies/' + id + '/plan', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF() },
      body: JSON.stringify({ plan: plan }),
    });
    var json = await res.json();
    if (!json.ok) throw new Error(json.message || 'Failed to update plan');
    showToast('Plan updated successfully!', 'success');
    closePlanModal();
    loadCompanies();
  } catch (err) {
    showToast(err.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Update Plan';
  }
}

// ── Suspend / Activate ─────────────────────────────────
async function toggleStatus(id, currentStatus, name) {
  var action = currentStatus === 'suspended' ? 'activate' : 'suspend';
  if (!window.confirm('Are you sure you want to ' + action + ' "' + name + '"?')) return;
  try {
    var res  = await fetch('/api/v1/super/companies/' + id + '/' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF() },
    });
    var json = await res.json();
    if (!json.ok) throw new Error(json.message || 'Failed to ' + action + ' company');
    showToast('Company ' + action + 'd successfully!', action === 'activate' ? 'success' : 'info');
    loadCompanies();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// ── Init ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', loadCompanies);
</script>
