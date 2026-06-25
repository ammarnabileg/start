<?php
/**
 * Jobs Listing View
 * Layout: app
 */
?>
<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="min-h-screen bg-gray-50">
  <!-- Page Header -->
  <div class="bg-white border-b border-gray-200 px-6 py-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Jobs</h1>
        <p class="text-sm text-gray-500 mt-0.5">Manage your open positions and job listings</p>
      </div>
      <?php if (Auth::can('jobs.create')): ?>
      <a href="/jobs/create" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Post New Job
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="px-6 py-6 max-w-7xl mx-auto">

    <!-- Filter Bar -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6">
      <div class="flex flex-col sm:flex-row gap-3">
        <!-- Search -->
        <div class="relative flex-1">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input
            type="text"
            id="filter-search"
            placeholder="Search by job title..."
            class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            oninput="debounceLoad()"
          >
        </div>
        <!-- Status Filter -->
        <select id="filter-status" onchange="loadJobs()" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white min-w-[140px]">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="paused">Paused</option>
          <option value="closed">Closed</option>
          <option value="draft">Draft</option>
        </select>
        <!-- Department Filter -->
        <select id="filter-department" onchange="loadJobs()" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white min-w-[160px]">
          <option value="">All Departments</option>
        </select>
        <!-- Type Filter -->
        <select id="filter-type" onchange="loadJobs()" class="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white min-w-[140px]">
          <option value="">All Types</option>
          <option value="full_time">Full Time</option>
          <option value="part_time">Part Time</option>
          <option value="contract">Contract</option>
          <option value="internship">Internship</option>
        </select>
        <button onclick="resetFilters()" class="px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
          Clear Filters
        </button>
      </div>
    </div>

    <!-- Summary row -->
    <div class="flex items-center justify-between mb-4">
      <p class="text-sm text-gray-500" id="results-summary">Loading...</p>
    </div>

    <!-- Jobs Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200 text-left">
              <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Job Title</th>
              <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Department</th>
              <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Location</th>
              <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">Applications</th>
              <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Created</th>
              <th class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="jobs-table-body" class="divide-y divide-gray-100">
            <tr>
              <td colspan="8" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center gap-2">
                  <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                  <span class="text-gray-400 text-sm">Loading jobs...</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50" id="pagination-container">
        <span class="text-sm text-gray-500" id="pagination-info"></span>
        <div class="flex items-center gap-1" id="pagination-controls"></div>
      </div>
    </div>
  </div>
</div>

<!-- Archive Confirm Modal -->
<div id="archive-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeArchiveModal()"></div>
  <div class="absolute inset-0 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-6 max-w-md w-full">
      <h3 class="text-lg font-bold text-gray-900 mb-2">Archive Job</h3>
      <p class="text-gray-600 text-sm mb-6">Are you sure you want to archive this job? It will no longer appear in active listings. You can restore it later.</p>
      <div class="flex gap-3 justify-end">
        <button onclick="closeArchiveModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
        <button onclick="confirmArchive()" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">Archive Job</button>
      </div>
    </div>
  </div>
</div>

<!-- Toast notification -->
<div id="toast" class="fixed bottom-6 right-6 z-50 hidden">
  <div class="bg-gray-900 text-white text-sm font-medium px-4 py-3 rounded-xl shadow-lg flex items-center gap-2">
    <span id="toast-msg"></span>
  </div>
</div>

<script>
(function () {
  const CSRF = document.querySelector('meta[name=csrf]').content;
  let currentPage = 1;
  let debounceTimer = null;
  let archiveTargetId = null;

  const STATUS_BADGE = {
    active:  'bg-green-100 text-green-800',
    paused:  'bg-yellow-100 text-yellow-800',
    closed:  'bg-gray-100 text-gray-700',
    draft:   'bg-blue-100 text-blue-700',
  };
  const TYPE_LABEL = {
    full_time:   'Full Time',
    part_time:   'Part Time',
    contract:    'Contract',
    internship:  'Internship',
  };
  const WORK_MODE_LABEL = {
    remote:  'Remote',
    onsite:  'On-site',
    hybrid:  'Hybrid',
  };

  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return String(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function showToast(msg, isError = false) {
    const toast = document.getElementById('toast');
    const msgEl = document.getElementById('toast-msg');
    msgEl.textContent = msg;
    toast.querySelector('div').className = `${isError ? 'bg-red-700' : 'bg-gray-900'} text-white text-sm font-medium px-4 py-3 rounded-xl shadow-lg flex items-center gap-2`;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 3500);
  }

  window.debounceLoad = function () {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { currentPage = 1; loadJobs(); }, 350);
  };

  window.resetFilters = function () {
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-department').value = '';
    document.getElementById('filter-type').value = '';
    currentPage = 1;
    loadJobs();
  };

  window.loadJobs = async function (page) {
    if (page) currentPage = page;
    const search = document.getElementById('filter-search').value.trim();
    const status = document.getElementById('filter-status').value;
    const department = document.getElementById('filter-department').value;
    const type = document.getElementById('filter-type').value;

    const params = new URLSearchParams({ page: currentPage, per_page: 15 });
    if (search) params.set('search', search);
    if (status) params.set('status', status);
    if (department) params.set('department', department);
    if (type) params.set('type', type);

    const tbody = document.getElementById('jobs-table-body');
    tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-12 text-center">
      <div class="flex flex-col items-center gap-2">
        <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
        <span class="text-gray-400 text-sm">Loading jobs...</span>
      </div>
    </td></tr>`;

    try {
      const res = await fetch('/api/v1/jobs?' + params.toString(), {
        headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/json' }
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Failed');

      const { data: jobs, meta } = json.data;
      renderTable(jobs || []);
      renderPagination(meta || {});

      const total = meta?.total ?? (jobs ? jobs.length : 0);
      document.getElementById('results-summary').textContent = `Showing ${jobs ? jobs.length : 0} of ${total} jobs`;

      // Populate department filter if empty
      const deptSel = document.getElementById('filter-department');
      if (deptSel.options.length <= 1 && json.data.departments) {
        json.data.departments.forEach(dept => {
          const opt = new Option(dept, dept);
          deptSel.add(opt);
        });
      }
    } catch (e) {
      console.error(e);
      tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-10 text-center text-red-500 text-sm">
        Failed to load jobs. <button onclick="loadJobs()" class="underline ml-1">Retry</button>
      </td></tr>`;
      document.getElementById('results-summary').textContent = '';
    }
  };

  function renderTable(jobs) {
    const tbody = document.getElementById('jobs-table-body');
    if (!jobs.length) {
      tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-16 text-center">
        <div class="flex flex-col items-center gap-3">
          <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <p class="text-gray-500 font-medium">No jobs found</p>
          <p class="text-gray-400 text-sm">Try adjusting your filters or post a new job.</p>
        </div>
      </td></tr>`;
      return;
    }
    tbody.innerHTML = jobs.map(job => {
      const statusBadge = STATUS_BADGE[job.status] || 'bg-gray-100 text-gray-700';
      const typeLabel = TYPE_LABEL[job.type] || job.type || '—';
      const workMode = WORK_MODE_LABEL[job.work_mode] || job.work_mode || '';
      return `
      <tr class="hover:bg-gray-50 transition-colors">
        <td class="px-6 py-4">
          <div class="font-medium text-gray-900">${escHtml(job.title)}</div>
          ${workMode ? `<div class="text-xs text-gray-400 mt-0.5">${escHtml(workMode)}</div>` : ''}
        </td>
        <td class="px-6 py-4 text-sm text-gray-600">${escHtml(job.department || '—')}</td>
        <td class="px-6 py-4 text-sm text-gray-600">${escHtml(job.location || '—')}</td>
        <td class="px-6 py-4">
          <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700">${escHtml(typeLabel)}</span>
        </td>
        <td class="px-6 py-4 text-center">
          <a href="/jobs/${escHtml(String(job.id))}?tab=applications" class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-50 text-blue-700 text-sm font-bold hover:bg-blue-100 transition-colors" title="View applications">
            ${escHtml(String(job.applications_count ?? 0))}
          </a>
        </td>
        <td class="px-6 py-4">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${statusBadge} capitalize">${escHtml(job.status || '—')}</span>
        </td>
        <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">${escHtml(formatDate(job.created_at))}</td>
        <td class="px-6 py-4 text-right">
          <div class="flex items-center justify-end gap-2">
            <a href="/jobs/${escHtml(String(job.id))}" class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </a>
            <a href="/jobs/${escHtml(String(job.id))}/edit" class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Edit">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </a>
            <button
              onclick="toggleJobStatus('${escHtml(String(job.id))}', '${escHtml(job.status)}')"
              class="p-1.5 rounded-lg transition-colors ${job.status === 'active' ? 'text-gray-500 hover:text-yellow-600 hover:bg-yellow-50' : 'text-gray-500 hover:text-green-600 hover:bg-green-50'}"
              title="${job.status === 'active' ? 'Pause' : 'Activate'}"
            >
              ${job.status === 'active'
                ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
                : `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
              }
            </button>
            <button onclick="openArchiveModal('${escHtml(String(job.id))}')" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Archive">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  function renderPagination(meta) {
    const info = document.getElementById('pagination-info');
    const controls = document.getElementById('pagination-controls');
    if (!meta || !meta.total) { info.textContent = ''; controls.innerHTML = ''; return; }

    const { current_page, last_page, from, to, total } = meta;
    info.textContent = `Showing ${from || 0}–${to || 0} of ${total} results`;

    let html = '';
    html += `<button onclick="loadJobs(${current_page - 1})" ${current_page <= 1 ? 'disabled' : ''} class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">← Prev</button>`;

    const startPage = Math.max(1, current_page - 2);
    const endPage   = Math.min(last_page, current_page + 2);
    if (startPage > 1) html += `<button onclick="loadJobs(1)" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">1</button>${startPage > 2 ? '<span class="px-1 text-gray-400">…</span>' : ''}`;
    for (let p = startPage; p <= endPage; p++) {
      html += `<button onclick="loadJobs(${p})" class="px-3 py-1.5 text-sm rounded-lg border transition-colors ${p === current_page ? 'bg-blue-600 text-white border-blue-600 font-semibold' : 'text-gray-600 border-gray-300 hover:bg-gray-100'}">${p}</button>`;
    }
    if (endPage < last_page) html += `${endPage < last_page - 1 ? '<span class="px-1 text-gray-400">…</span>' : ''}<button onclick="loadJobs(${last_page})" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">${last_page}</button>`;

    html += `<button onclick="loadJobs(${current_page + 1})" ${current_page >= last_page ? 'disabled' : ''} class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">Next →</button>`;
    controls.innerHTML = html;
  }

  window.toggleJobStatus = async function (id, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'paused' : 'active';
    try {
      const res = await fetch(`/api/v1/jobs/${id}/status`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/json' },
        body: JSON.stringify({ status: newStatus })
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Failed to update status');
      showToast(`Job ${newStatus === 'active' ? 'activated' : 'paused'} successfully`);
      loadJobs();
    } catch (e) {
      showToast(e.message || 'Failed to update job status', true);
    }
  };

  window.openArchiveModal = function (id) {
    archiveTargetId = id;
    document.getElementById('archive-modal').classList.remove('hidden');
  };

  window.closeArchiveModal = function () {
    archiveTargetId = null;
    document.getElementById('archive-modal').classList.add('hidden');
  };

  window.confirmArchive = async function () {
    if (!archiveTargetId) return;
    closeArchiveModal();
    try {
      const res = await fetch(`/api/v1/jobs/${archiveTargetId}/archive`, {
        method: 'POST',
        headers: { 'X-CSRF-Token': CSRF, 'Content-Type': 'application/json' }
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Failed');
      showToast('Job archived successfully');
      loadJobs();
    } catch (e) {
      showToast(e.message || 'Failed to archive job', true);
    }
  };

  // Initial load
  loadJobs();
})();
</script>
