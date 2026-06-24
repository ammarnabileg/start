<?php
/**
 * Jobs list — filterable grid of role cards with publish/delete actions,
 * plus an "AI Build Job" modal that generates a full job from a prompt.
 * Fragment: rendered into $content and wrapped by views/layouts/app.php.
 */
?>
<div class="space-y-6">

  <!-- ============ Header ============ -->
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h1 class="text-3xl font-bold tracking-tight text-gray-900">Jobs</h1>
      <p class="mt-1 text-gray-500">Create, publish and manage your open roles.</p>
    </div>
    <div class="flex items-center gap-3">
      <button type="button" data-modal-open="ai-job-modal" class="btn-accent">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 002.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
        </svg>
        <span>AI Build Job</span>
      </button>
      <a href="/jobs/create" class="btn-primary">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        <span>New Job</span>
      </a>
    </div>
  </div>

  <!-- ============ Filter bar ============ -->
  <div class="card">
    <div class="flex flex-wrap gap-3 items-center p-4">
      <select id="filter-status"
              class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-brand transition">
        <option value="">All statuses</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="closed">Closed</option>
      </select>

      <input id="filter-department" type="text" placeholder="Department" autocomplete="off"
             class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-brand transition" />

      <div class="relative flex-1 min-w-[200px]">
        <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
          </svg>
        </span>
        <input id="filter-search" type="text" placeholder="Search jobs…" autocomplete="off"
               class="w-full rounded-lg border border-gray-200 bg-white ps-10 pe-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-brand transition" />
      </div>
    </div>
  </div>

  <!-- ============ Results grid ============ -->
  <div id="jobs-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    <!-- 6 skeleton cards (replaced by JS) -->
    <div class="card p-5"><div class="space-y-3"><div class="flex justify-between"><span class="skeleton inline-block w-40 h-5"></span><span class="skeleton inline-block w-16 h-5 rounded-full"></span></div><span class="skeleton inline-block w-32 h-4"></span><span class="skeleton inline-block w-24 h-5 rounded-full"></span><span class="skeleton inline-block w-28 h-4"></span><div class="pt-3 border-t border-gray-100 flex gap-2"><span class="skeleton inline-block w-16 h-8 rounded-full"></span><span class="skeleton inline-block w-9 h-8 rounded-full"></span></div></div></div>
    <div class="card p-5"><div class="space-y-3"><div class="flex justify-between"><span class="skeleton inline-block w-40 h-5"></span><span class="skeleton inline-block w-16 h-5 rounded-full"></span></div><span class="skeleton inline-block w-32 h-4"></span><span class="skeleton inline-block w-24 h-5 rounded-full"></span><span class="skeleton inline-block w-28 h-4"></span><div class="pt-3 border-t border-gray-100 flex gap-2"><span class="skeleton inline-block w-16 h-8 rounded-full"></span><span class="skeleton inline-block w-9 h-8 rounded-full"></span></div></div></div>
    <div class="card p-5"><div class="space-y-3"><div class="flex justify-between"><span class="skeleton inline-block w-40 h-5"></span><span class="skeleton inline-block w-16 h-5 rounded-full"></span></div><span class="skeleton inline-block w-32 h-4"></span><span class="skeleton inline-block w-24 h-5 rounded-full"></span><span class="skeleton inline-block w-28 h-4"></span><div class="pt-3 border-t border-gray-100 flex gap-2"><span class="skeleton inline-block w-16 h-8 rounded-full"></span><span class="skeleton inline-block w-9 h-8 rounded-full"></span></div></div></div>
    <div class="card p-5"><div class="space-y-3"><div class="flex justify-between"><span class="skeleton inline-block w-40 h-5"></span><span class="skeleton inline-block w-16 h-5 rounded-full"></span></div><span class="skeleton inline-block w-32 h-4"></span><span class="skeleton inline-block w-24 h-5 rounded-full"></span><span class="skeleton inline-block w-28 h-4"></span><div class="pt-3 border-t border-gray-100 flex gap-2"><span class="skeleton inline-block w-16 h-8 rounded-full"></span><span class="skeleton inline-block w-9 h-8 rounded-full"></span></div></div></div>
    <div class="card p-5"><div class="space-y-3"><div class="flex justify-between"><span class="skeleton inline-block w-40 h-5"></span><span class="skeleton inline-block w-16 h-5 rounded-full"></span></div><span class="skeleton inline-block w-32 h-4"></span><span class="skeleton inline-block w-24 h-5 rounded-full"></span><span class="skeleton inline-block w-28 h-4"></span><div class="pt-3 border-t border-gray-100 flex gap-2"><span class="skeleton inline-block w-16 h-8 rounded-full"></span><span class="skeleton inline-block w-9 h-8 rounded-full"></span></div></div></div>
    <div class="card p-5"><div class="space-y-3"><div class="flex justify-between"><span class="skeleton inline-block w-40 h-5"></span><span class="skeleton inline-block w-16 h-5 rounded-full"></span></div><span class="skeleton inline-block w-32 h-4"></span><span class="skeleton inline-block w-24 h-5 rounded-full"></span><span class="skeleton inline-block w-28 h-4"></span><div class="pt-3 border-t border-gray-100 flex gap-2"><span class="skeleton inline-block w-16 h-8 rounded-full"></span><span class="skeleton inline-block w-9 h-8 rounded-full"></span></div></div></div>
  </div>
</div>

<!-- ============ AI Job modal ============ -->
<div id="ai-job-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card max-w-2xl overflow-hidden flex flex-col" style="max-height:92vh">
    <!-- Header -->
    <div class="gradient-brand text-white px-5 py-4 flex items-center gap-3">
      <span class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 002.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
      </span>
      <div class="flex-1 min-w-0">
        <p class="font-semibold leading-tight">Build a Job with AI</p>
        <p class="text-xs text-white/70">Describe the role — we'll draft the rest</p>
      </div>
      <button type="button" data-modal-close="ai-job-modal" aria-label="Close"
              class="w-8 h-8 rounded-full hover:bg-white/15 flex items-center justify-center transition">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
    </div>

    <!-- Body -->
    <div class="p-5 overflow-y-auto">
      <label for="ai-job-prompt" class="block text-sm font-semibold text-gray-700 mb-1.5">Describe the role</label>
      <textarea id="ai-job-prompt" rows="4"
                class="w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand/40 focus:border-brand transition"
                placeholder="Describe the role you want to hire for… e.g. 'Senior React engineer in Dubai, fintech, 5+ years, hybrid.'"></textarea>

      <!-- Generated preview -->
      <div id="ai-job-preview" class="hidden mt-5 rounded-xl border border-gray-200 bg-gray-50 p-5 fade-in">
        <!-- filled by JS -->
      </div>
    </div>

    <!-- Footer -->
    <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
      <button id="ai-build-btn" type="button" class="btn-accent">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>
        <span>Build with AI</span>
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  'use strict';
  var AR = window.AR;

  var grid = document.getElementById('jobs-grid');
  var fStatus = document.getElementById('filter-status');
  var fDept = document.getElementById('filter-department');
  var fSearch = document.getElementById('filter-search');

  var currencySymbols = { USD: '$', EUR: '€', GBP: '£', SAR: 'SAR ', AED: 'AED ' };

  // ---------------------------------------------------------------- helpers
  function prettify(s) {
    if (!s) return '';
    return String(s).replace(/[_-]+/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }
  function shortDate(x) {
    if (!x) return '';
    var d = new Date(x);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }
  function statusBadge(status) {
    var map = { published: 'badge-green', draft: 'badge-yellow', closed: 'badge-gray' };
    var cls = map[status] || 'badge-gray';
    return '<span class="badge ' + cls + '">' + AR.esc(prettify(status || 'Draft')) + '</span>';
  }
  function money(min, max, currency) {
    var sym = currencySymbols[currency] || (currency ? (currency + ' ') : '$');
    function fmt(n) { return sym + Number(n).toLocaleString(); }
    var hasMin = min !== null && min !== undefined && min !== '' && !isNaN(Number(min));
    var hasMax = max !== null && max !== undefined && max !== '' && !isNaN(Number(max));
    if (hasMin && hasMax) return fmt(min) + ' – ' + fmt(max);
    if (hasMin) return 'From ' + fmt(min);
    if (hasMax) return 'Up to ' + fmt(max);
    return null;
  }
  function buildQuery() {
    var params = [];
    var s = (fStatus && fStatus.value) || '';
    var d = (fDept && fDept.value.trim()) || '';
    var q = (fSearch && fSearch.value.trim()) || '';
    if (s) params.push('status=' + encodeURIComponent(s));
    if (d) params.push('department=' + encodeURIComponent(d));
    if (q) params.push('search=' + encodeURIComponent(q));
    return params.length ? ('?' + params.join('&')) : '';
  }
  function filtersActive() {
    return !!((fStatus && fStatus.value) || (fDept && fDept.value.trim()) || (fSearch && fSearch.value.trim()));
  }

  // ---------------------------------------------------------------- icons
  var ICON_PIN = '<svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>';
  var ICON_DEPT = '<svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>';
  var ICON_CAL = '<svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>';
  var ICON_MONEY = '<svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
  var ICON_TRASH = '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>';

  // ---------------------------------------------------------------- card markup
  function jobCard(job) {
    var id = job.id != null ? job.id : '';
    var title = job.title || 'Untitled role';
    var dept = job.department || '';
    var loc = job.location || '';

    var subParts = [];
    if (dept) subParts.push('<span class="inline-flex items-center gap-1">' + ICON_DEPT + AR.esc(dept) + '</span>');
    if (loc) subParts.push('<span class="inline-flex items-center gap-1">' + ICON_PIN + AR.esc(loc) + '</span>');
    var subLine = subParts.length
      ? '<div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">' + subParts.join('') + '</div>'
      : '';

    var typeBadge = job.job_type
      ? '<span class="badge badge-violet">' + AR.esc(prettify(job.job_type)) + '</span>'
      : '';

    var salary = money(job.salary_min, job.salary_max, job.currency);
    var salaryLine = salary
      ? '<div class="flex items-center gap-1 text-sm font-medium text-gray-700">' + ICON_MONEY + AR.esc(salary) + '</div>'
      : '<div class="flex items-center gap-1 text-sm text-gray-400">' + ICON_MONEY + 'Salary not specified</div>';

    var posted = shortDate(job.created_at);
    var postedLine = posted
      ? '<div class="flex items-center gap-1 text-xs text-gray-400">' + ICON_CAL + 'Posted ' + AR.esc(posted) + '</div>'
      : '';

    var publishBtn = (job.status === 'draft')
      ? '<button type="button" data-act="publish" data-id="' + AR.esc(id) + '" class="btn-primary !py-1.5 !px-3 text-sm">' +
          '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v13.5m0-13.5L7.5 7.5M12 3l4.5 4.5M3.75 21h16.5" /></svg>' +
          'Publish</button>'
      : '';

    return '<div class="card p-5 flex flex-col hover:shadow-md transition fade-in">' +
        '<div class="flex items-start justify-between gap-3">' +
          '<h3 class="font-semibold text-lg text-gray-900 truncate" title="' + AR.esc(title) + '">' + AR.esc(title) + '</h3>' +
          statusBadge(job.status) +
        '</div>' +
        (subLine ? '<div class="mt-2">' + subLine + '</div>' : '') +
        (typeBadge ? '<div class="mt-2.5">' + typeBadge + '</div>' : '') +
        '<div class="mt-3 space-y-1.5">' + salaryLine + postedLine + '</div>' +
        '<div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-2">' +
          '<a href="/jobs/' + AR.esc(id) + '" class="btn-ghost !py-1.5 !px-3 text-sm">View</a>' +
          publishBtn +
          '<div class="flex-1"></div>' +
          '<button type="button" data-act="delete" data-id="' + AR.esc(id) + '" aria-label="Delete job" ' +
                  'class="w-9 h-9 rounded-full border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 flex items-center justify-center transition">' +
            ICON_TRASH +
          '</button>' +
        '</div>' +
      '</div>';
  }

  // ---------------------------------------------------------------- empty / error states
  function emptyState() {
    var active = filtersActive();
    grid.className = '';
    grid.innerHTML =
      '<div class="card p-12 text-center fade-in">' +
        '<div class="mx-auto w-16 h-16 rounded-full bg-violet-100 text-violet-600 flex items-center justify-center">' +
          '<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V14.15M16.5 6.75V5.25a2.25 2.25 0 00-2.25-2.25h-4.5A2.25 2.25 0 007.5 5.25v1.5m13.5 0H3.75a1.5 1.5 0 00-1.5 1.5v3.026c0 .55.27 1.06.71 1.39l.01.01a17.93 17.93 0 0019.06 0l.01-.01c.44-.33.71-.84.71-1.39V8.25a1.5 1.5 0 00-1.5-1.5z" /></svg>' +
        '</div>' +
        '<h3 class="mt-4 text-lg font-semibold text-gray-900">No jobs found</h3>' +
        '<p class="mt-1 text-sm text-gray-500">' +
          (active ? 'No jobs match your filters. Try adjusting or clearing them.'
                  : 'Get started by creating your first role and publishing it to candidates.') +
        '</p>' +
        '<div class="mt-5">' +
          '<a href="/jobs/create" class="btn-primary inline-flex">' +
            '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>' +
            'Create your first job</a>' +
        '</div>' +
      '</div>';
  }

  function errorState() {
    grid.className = '';
    grid.innerHTML =
      '<div class="card p-12 text-center fade-in">' +
        '<div class="mx-auto w-16 h-16 rounded-full bg-red-100 text-red-600 flex items-center justify-center">' +
          '<svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>' +
        '</div>' +
        '<h3 class="mt-4 text-lg font-semibold text-gray-900">Could not load jobs</h3>' +
        '<p class="mt-1 text-sm text-gray-500">Something went wrong while fetching your roles.</p>' +
        '<div class="mt-5"><button type="button" id="jobs-retry" class="btn-primary inline-flex">' +
          '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>' +
          'Retry</button></div>' +
      '</div>';
    var retry = document.getElementById('jobs-retry');
    if (retry) retry.addEventListener('click', loadJobs);
  }

  function gridSkeleton() {
    grid.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5';
    var one = '<div class="card p-5"><div class="space-y-3"><div class="flex justify-between"><span class="skeleton inline-block w-40 h-5"></span><span class="skeleton inline-block w-16 h-5 rounded-full"></span></div><span class="skeleton inline-block w-32 h-4"></span><span class="skeleton inline-block w-24 h-5 rounded-full"></span><span class="skeleton inline-block w-28 h-4"></span><div class="pt-3 border-t border-gray-100 flex gap-2"><span class="skeleton inline-block w-16 h-8 rounded-full"></span><span class="skeleton inline-block w-9 h-8 rounded-full"></span></div></div></div>';
    grid.innerHTML = one + one + one + one + one + one;
  }

  // ---------------------------------------------------------------- render
  function renderJobs(jobs) {
    if (!Array.isArray(jobs) || jobs.length === 0) {
      emptyState();
      return;
    }
    grid.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5';
    grid.innerHTML = jobs.map(jobCard).join('');
  }

  // Event delegation for publish / delete.
  grid.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn) return;
    var act = btn.getAttribute('data-act');
    var id = btn.getAttribute('data-id');
    if (!id) return;

    if (act === 'publish') {
      btn.disabled = true;
      AR.Api.post('/jobs/' + encodeURIComponent(id) + '/publish')
        .then(function () { AR.Toast.success('Job published'); loadJobs(); })
        .catch(function (err) { AR.Toast.error((err && err.message) || 'Could not publish job'); btn.disabled = false; });
    } else if (act === 'delete') {
      if (!confirm('Delete this job? This cannot be undone.')) return;
      btn.disabled = true;
      AR.Api.del('/jobs/' + encodeURIComponent(id))
        .then(function () { AR.Toast.success('Job deleted'); loadJobs(); })
        .catch(function (err) { AR.Toast.error((err && err.message) || 'Could not delete job'); btn.disabled = false; });
    }
  });

  // ---------------------------------------------------------------- load
  function loadJobs() {
    gridSkeleton();
    AR.Api.get('/jobs' + buildQuery())
      .then(function (jobs) { renderJobs(jobs); })
      .catch(function (err) {
        AR.Toast.error((err && err.message) || 'Could not load jobs');
        errorState();
      });
  }

  // ---------------------------------------------------------------- filters wiring
  if (fStatus) fStatus.addEventListener('change', loadJobs);
  if (fDept) fDept.addEventListener('change', loadJobs);

  var searchTimer = null;
  if (fSearch) {
    fSearch.addEventListener('input', function () {
      if (searchTimer) clearTimeout(searchTimer);
      searchTimer = setTimeout(loadJobs, 300);
    });
  }

  loadJobs();

  // ---------------------------------------------------------------- AI Job modal
  (function aiJob() {
    var buildBtn = document.getElementById('ai-build-btn');
    var promptEl = document.getElementById('ai-job-prompt');
    var preview = document.getElementById('ai-job-preview');
    if (!buildBtn || !promptEl || !preview) return;

    var generated = null;
    var building = false;
    var creating = false;

    function setBuildBtn(busy) {
      building = busy;
      buildBtn.disabled = busy;
      if (busy) {
        buildBtn.innerHTML =
          '<svg class="w-5 h-5 spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M12 3a9 9 0 109 9" /></svg>' +
          '<span>Building…</span>';
      } else {
        buildBtn.innerHTML =
          '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>' +
          '<span>Build with AI</span>';
      }
    }

    function qText(q) {
      if (q == null) return '';
      if (typeof q === 'string') return q;
      return q.question || q.text || q.criterion_name || JSON.stringify(q);
    }

    function renderPreview(job) {
      var badges = [];
      if (job.department) badges.push('<span class="badge badge-gray">' + AR.esc(prettify(job.department)) + '</span>');
      if (job.location) badges.push('<span class="badge badge-gray">' + AR.esc(job.location) + '</span>');
      if (job.job_type) badges.push('<span class="badge badge-violet">' + AR.esc(prettify(job.job_type)) + '</span>');

      var salary = money(job.salary_min, job.salary_max, job.currency);
      var salaryHtml = salary
        ? '<p class="mt-2 text-sm font-medium text-gray-700">' + AR.esc(salary) + '</p>'
        : '';

      var descHtml = job.description
        ? '<div class="mt-3"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Description</p>' +
            '<div class="text-sm text-gray-600 whitespace-pre-wrap max-h-32 overflow-y-auto rounded-lg bg-white border border-gray-200 p-3">' + AR.esc(job.description) + '</div></div>'
        : '';

      var reqHtml = job.requirements
        ? '<div class="mt-3"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Requirements</p>' +
            '<div class="text-sm text-gray-600 whitespace-pre-wrap max-h-32 overflow-y-auto rounded-lg bg-white border border-gray-200 p-3">' + AR.esc(job.requirements) + '</div></div>'
        : '';

      var critHtml = '';
      if (Array.isArray(job.ai_criteria) && job.ai_criteria.length) {
        var items = job.ai_criteria.map(function (c) {
          var nm = c.criterion_name || c.name || 'Criterion';
          var w = (c.weight !== null && c.weight !== undefined && c.weight !== '') ? c.weight : null;
          var wBadge = w !== null ? '<span class="badge badge-violet ms-2">' + AR.esc(String(w)) + '</span>' : '';
          var desc = c.description ? '<p class="text-xs text-gray-500 mt-0.5">' + AR.esc(c.description) + '</p>' : '';
          return '<li class="rounded-lg bg-white border border-gray-200 px-3 py-2">' +
              '<div class="flex items-center"><span class="text-sm font-medium text-gray-800">' + AR.esc(nm) + '</span>' + wBadge + '</div>' +
              desc +
            '</li>';
        }).join('');
        critHtml = '<div class="mt-3"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1.5">AI Evaluation Criteria</p>' +
          '<ul class="space-y-2">' + items + '</ul></div>';
      }

      var qHtml = '';
      if (Array.isArray(job.question_bank) && job.question_bank.length) {
        var qs = job.question_bank.slice(0, 8).map(function (q) {
          return '<li class="flex items-start gap-2 text-sm text-gray-600">' +
              '<span class="mt-1.5 w-1.5 h-1.5 shrink-0 rounded-full bg-brand"></span>' +
              '<span>' + AR.esc(qText(q)) + '</span></li>';
        }).join('');
        var more = job.question_bank.length > 8
          ? '<p class="mt-2 text-xs text-gray-400">+' + (job.question_bank.length - 8) + ' more question' + ((job.question_bank.length - 8) === 1 ? '' : 's') + '</p>'
          : '';
        qHtml = '<div class="mt-3"><p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Interview Questions</p>' +
          '<ul class="space-y-1.5">' + qs + '</ul>' + more + '</div>';
      }

      preview.innerHTML =
        '<div class="flex items-center gap-2 mb-2 text-brand">' +
          '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" /></svg>' +
          '<span class="text-xs font-semibold uppercase tracking-wide">AI Draft</span>' +
        '</div>' +
        '<h4 class="text-lg font-bold text-gray-900">' + AR.esc(job.title || 'Untitled role') + '</h4>' +
        (badges.length ? '<div class="mt-2 flex flex-wrap gap-2">' + badges.join('') + '</div>' : '') +
        salaryHtml +
        descHtml +
        reqHtml +
        critHtml +
        qHtml +
        '<div class="mt-5 pt-4 border-t border-gray-200 flex items-center gap-3">' +
          '<button type="button" id="ai-use-btn" class="btn-primary">' +
            '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>' +
            'Use this Job</button>' +
          '<button type="button" id="ai-discard-btn" class="btn-ghost">Discard</button>' +
        '</div>';

      preview.classList.remove('hidden');
      wireUseDiscard();
    }

    function wireUseDiscard() {
      var useBtn = document.getElementById('ai-use-btn');
      var discardBtn = document.getElementById('ai-discard-btn');

      if (discardBtn) {
        discardBtn.addEventListener('click', function () {
          generated = null;
          preview.classList.add('hidden');
          preview.innerHTML = '';
        });
      }

      if (useBtn) {
        useBtn.addEventListener('click', function () {
          if (!generated || creating) return;
          creating = true;
          useBtn.disabled = true;
          useBtn.innerHTML =
            '<svg class="w-5 h-5 spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M12 3a9 9 0 109 9" /></svg>' +
            '<span>Creating…</span>';

          var payload = {
            title: generated.title || '',
            description: generated.description || '',
            requirements: generated.requirements || '',
            department: generated.department || '',
            location: generated.location || '',
            job_type: generated.job_type || '',
            salary_min: generated.salary_min != null ? generated.salary_min : null,
            salary_max: generated.salary_max != null ? generated.salary_max : null,
            currency: generated.currency || 'USD',
            interview_type: 'ai_text',
            avatar_id: null
          };

          AR.Api.post('/jobs', payload)
            .then(function (created) {
              AR.Toast.success('Job created');
              if (created && created.id != null) {
                window.location = '/jobs/' + created.id;
              } else {
                window.location = '/jobs';
              }
            })
            .catch(function (err) {
              AR.Toast.error((err && err.message) || 'Could not create job');
              creating = false;
              useBtn.disabled = false;
              useBtn.innerHTML =
                '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>' +
                'Use this Job';
            });
        });
      }
    }

    buildBtn.addEventListener('click', function () {
      if (building) return;
      var prompt = promptEl.value.trim();
      if (!prompt) { AR.Toast.error('Describe the role first.'); return; }

      setBuildBtn(true);
      AR.Api.post('/ai/build-job', { prompt: prompt })
        .then(function (job) {
          generated = job || {};
          renderPreview(generated);
        })
        .catch(function (err) {
          AR.Toast.error((err && err.message) || 'AI could not build the job. Try again.');
        })
        .finally(function () {
          setBuildBtn(false);
        });
    });
  })();
});
</script>
