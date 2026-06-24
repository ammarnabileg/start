<?php
/**
 * Candidates list — searchable, filterable talent table with bulk compare + CV import.
 * Fragment rendered inside views/layouts/app.php. All dynamic data is fetched by JS.
 */
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- ============ Header ============ -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
        </span>
        Candidates
      </h1>
      <p class="text-sm text-gray-500 mt-1">Search, filter and review your talent.</p>
    </div>
    <div class="flex items-center gap-2 self-start sm:self-auto">
      <button type="button" data-modal-open="import-modal" class="btn-accent">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
        Import CV
      </button>
      <button type="button" id="compare-btn" disabled aria-disabled="true"
              class="btn-primary opacity-50 cursor-not-allowed" title="Select 2–4 candidates to compare">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>
        Compare
      </button>
    </div>
  </div>

  <!-- ============ Filter bar ============ -->
  <div class="card p-4 mb-4">
    <div class="flex flex-wrap gap-3 items-center">
      <div class="relative flex-1 min-w-[220px]">
        <span class="pointer-events-none absolute inset-y-0 start-0 ps-3 flex items-center text-gray-400">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
        </span>
        <input id="filter-search" type="search" autocomplete="off" placeholder="Search by name or email…"
               class="w-full rounded-xl border border-gray-200 ps-10 pe-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
      </div>
      <select id="filter-status" class="rounded-xl border border-gray-200 px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="new">New</option>
        <option value="interviewing">Interviewing</option>
        <option value="hired">Hired</option>
        <option value="rejected">Rejected</option>
      </select>
      <select id="filter-job" class="rounded-xl border border-gray-200 px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500 max-w-[240px]">
        <option value="">All jobs</option>
      </select>
    </div>
  </div>

  <!-- ============ Bulk action bar (hidden until a row is selected) ============ -->
  <div id="bulk-bar" class="hidden sticky top-16 z-10 mb-4 rounded-2xl border border-violet-200 bg-violet-50/90 backdrop-blur px-4 py-3 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
      <span class="inline-flex items-center gap-2 text-sm font-semibold text-violet-800">
        <span class="inline-flex w-7 h-7 rounded-lg bg-violet-600 text-white items-center justify-center text-xs font-bold" id="bulk-count-badge">0</span>
        <span id="bulk-count-text">0 selected</span>
      </span>
      <span class="text-xs text-violet-500 hidden sm:inline">Select 2–4 to compare side-by-side.</span>
      <div class="flex-1"></div>
      <button type="button" id="bulk-compare" disabled aria-disabled="true"
              class="btn-primary opacity-50 cursor-not-allowed !py-2 !px-4 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>
        Compare selected
      </button>
      <button type="button" id="bulk-clear" class="btn-ghost !py-2 !px-4 text-sm">Clear</button>
    </div>
  </div>

  <!-- ============ Table card ============ -->
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-gray-100 bg-gray-50/60">
            <th class="px-5 py-3 w-10">
              <input type="checkbox" id="check-all" aria-label="Select all visible candidates"
                     class="h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 align-middle cursor-pointer" />
            </th>
            <th class="px-5 py-3 font-semibold">Name</th>
            <th class="px-5 py-3 font-semibold">Job Applied</th>
            <th class="px-5 py-3 font-semibold">AI Score</th>
            <th class="px-5 py-3 font-semibold">Stage</th>
            <th class="px-5 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="candidates-body" class="divide-y divide-gray-100"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- ============ Import modal ============ -->
<div id="import-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card fade-in">
    <!-- Header -->
    <div class="gradient-brand text-white rounded-t-2xl px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="inline-flex w-9 h-9 rounded-xl bg-white/15 items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>
        </span>
        <div>
          <h2 class="text-base font-bold leading-tight">Import Candidate</h2>
          <p class="text-xs text-white/70">Upload a CV or enter details manually.</p>
        </div>
      </div>
      <button type="button" data-modal-close="import-modal" aria-label="Close"
              class="inline-flex w-8 h-8 rounded-lg items-center justify-center text-white/80 hover:bg-white/15 transition">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
    </div>

    <!-- Body -->
    <form id="import-form" class="px-6 py-5 space-y-4">
      <!-- Drop-zone file input -->
      <div>
        <label for="import-file"
               class="flex flex-col items-center justify-center gap-2 w-full rounded-xl border-2 border-dashed border-violet-200 bg-violet-50/40 px-4 py-6 text-center cursor-pointer hover:border-violet-400 hover:bg-violet-50 transition">
          <span class="inline-flex w-10 h-10 rounded-full bg-violet-100 text-violet-600 items-center justify-center">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
          </span>
          <span class="text-sm font-semibold text-gray-800">Upload CV (PDF/DOC)</span>
          <span id="import-file-name" class="text-xs text-gray-400">Drag a file here or click to browse</span>
        </label>
        <input id="import-file" name="cv" type="file" accept=".pdf,.doc,.docx" class="sr-only" />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label for="imp-first" class="block text-xs font-semibold text-gray-500 mb-1">First name <span class="text-red-500">*</span></label>
          <input id="imp-first" type="text" required autocomplete="given-name"
                 class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
        </div>
        <div>
          <label for="imp-last" class="block text-xs font-semibold text-gray-500 mb-1">Last name</label>
          <input id="imp-last" type="text" autocomplete="family-name"
                 class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
        </div>
        <div>
          <label for="imp-email" class="block text-xs font-semibold text-gray-500 mb-1">Email <span class="text-red-500">*</span></label>
          <input id="imp-email" type="email" required autocomplete="email"
                 class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
        </div>
        <div>
          <label for="imp-phone" class="block text-xs font-semibold text-gray-500 mb-1">Phone</label>
          <input id="imp-phone" type="tel" autocomplete="tel"
                 class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
        </div>
      </div>

      <div>
        <label for="imp-job" class="block text-xs font-semibold text-gray-500 mb-1">Apply to job <span class="text-gray-300 font-normal">(optional)</span></label>
        <select id="imp-job" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          <option value="">No specific job</option>
        </select>
      </div>
    </form>

    <!-- Footer -->
    <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-2">
      <button type="button" data-modal-close="import-modal" class="btn-ghost">Cancel</button>
      <button type="submit" form="import-form" id="import-submit" class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        Add Candidate
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  const body       = $('candidates-body');
  const checkAll   = $('check-all');
  const compareBtn = $('compare-btn');
  const bulkBar    = $('bulk-bar');
  const bulkCompare= $('bulk-compare');
  const bulkClear  = $('bulk-clear');
  const bulkBadge  = $('bulk-count-badge');
  const bulkText   = $('bulk-count-text');

  const COLSPAN = 6;
  const selected = new Set();   // candidate ids currently checked
  let lastRows = [];            // last rendered candidate list (for empty-state context)
  let overflowToasted = false;  // throttle the ">4 selected" hint

  function unwrap(d) {
    if (Array.isArray(d)) return d;
    if (d && Array.isArray(d.candidates)) return d.candidates;
    if (d && Array.isArray(d.data)) return d.data;
    if (d && Array.isArray(d.jobs)) return d.jobs;
    return [];
  }

  function initials(first, last, email) {
    const a = (first || '').trim();
    const b = (last || '').trim();
    let s = ((a ? a[0] : '') + (b ? b[0] : '')).toUpperCase();
    if (!s) s = (email || '?').trim().charAt(0).toUpperCase();
    return s || '?';
  }

  function pretty(v) {
    if (v == null || v === '') return '';
    return String(v).replace(/[_-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }

  // ---- Selection / compare state ----------------------------------------
  function compareUrl() {
    return '/candidates/compare?ids=' + Array.from(selected).map(encodeURIComponent).join(',');
  }
  function setCompareEnabled(btn, on) {
    if (!btn) return;
    btn.disabled = !on;
    btn.setAttribute('aria-disabled', on ? 'false' : 'true');
    btn.classList.toggle('opacity-50', !on);
    btn.classList.toggle('cursor-not-allowed', !on);
  }
  function refreshSelectionUI() {
    const n = selected.size;
    const canCompare = n >= 2 && n <= 4;

    setCompareEnabled(compareBtn, canCompare);
    setCompareEnabled(bulkCompare, canCompare);

    // Bulk bar visibility + live counts.
    bulkBar.classList.toggle('hidden', n < 1);
    bulkBadge.textContent = String(n);
    bulkText.textContent = n + (n === 1 ? ' selected' : ' selected');

    // Gentle hint if the user goes past the 4-candidate compare cap.
    if (n > 4) {
      if (!overflowToasted) { AR.Toast.info('You can compare up to 4 candidates at once.'); overflowToasted = true; }
    } else {
      overflowToasted = false;
    }

    // check-all reflects the state of currently visible rows.
    const boxes = body.querySelectorAll('.row-check');
    const total = boxes.length;
    let checked = 0;
    boxes.forEach((b) => { if (b.checked) checked++; });
    checkAll.checked = total > 0 && checked === total;
    checkAll.indeterminate = checked > 0 && checked < total;
    checkAll.disabled = total === 0;
  }

  function goCompare() {
    if (selected.size >= 2 && selected.size <= 4) window.location = compareUrl();
  }

  // ---- Cell builders -----------------------------------------------------
  function scoreCell(v) {
    if (v == null || v === '') return '<span class="badge badge-gray">—</span>';
    const n = Math.round(Number(v));
    if (isNaN(n)) return '<span class="badge badge-gray">—</span>';
    return '<span class="badge ' + AR.scoreColor(n) + '">' + n + '%</span>';
  }
  function stageCell(c) {
    const raw = c.pipeline_stage || c.status || '';
    const label = pretty(raw);
    if (!label) return '<span class="badge badge-gray">—</span>';
    return '<span class="badge badge-violet capitalize">' + AR.esc(label) + '</span>';
  }

  function rowHtml(c) {
    const id = c.id;
    const name = [c.first_name, c.last_name].filter(Boolean).join(' ').trim() || c.email || 'Unnamed candidate';
    const ini = initials(c.first_name, c.last_name, c.email);
    const isChecked = selected.has(String(id));
    return '<tr class="hover:bg-gray-50 transition" data-row-id="' + AR.esc(String(id)) + '">' +
      '<td class="px-5 py-4 align-middle">' +
        '<input type="checkbox" class="row-check h-4 w-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 cursor-pointer" ' +
          'data-id="' + AR.esc(String(id)) + '"' + (isChecked ? ' checked' : '') + ' aria-label="Select ' + AR.esc(name) + '" />' +
      '</td>' +
      '<td class="px-5 py-4">' +
        '<div class="flex items-center gap-3 min-w-[200px]">' +
          '<div class="w-9 h-9 rounded-full gradient-brand text-white flex items-center justify-center text-xs font-bold shrink-0">' + AR.esc(ini) + '</div>' +
          '<div class="min-w-0 leading-tight">' +
            '<div class="font-semibold text-gray-900 truncate">' + AR.esc(name) + '</div>' +
            '<div class="text-xs text-gray-400 truncate">' + AR.esc(c.email || '—') + '</div>' +
          '</div>' +
        '</div>' +
      '</td>' +
      '<td class="px-5 py-4 text-gray-600">' + AR.esc(c.job_title || '—') + '</td>' +
      '<td class="px-5 py-4">' + scoreCell(c.ai_match_score) + '</td>' +
      '<td class="px-5 py-4">' + stageCell(c) + '</td>' +
      '<td class="px-5 py-4 text-right">' +
        '<a href="/candidates/' + encodeURIComponent(id) + '" class="btn-ghost !py-1.5 !px-3 text-xs inline-flex">' +
          'View 360' +
          '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>' +
        '</a>' +
      '</td>' +
    '</tr>';
  }

  function skeleton() {
    body.innerHTML = Array.from({ length: 5 }).map(() =>
      '<tr>' +
        '<td class="px-5 py-4"><div class="skeleton h-4 w-4 rounded"></div></td>' +
        '<td class="px-5 py-4"><div class="flex items-center gap-3"><div class="skeleton w-9 h-9 rounded-full"></div><div class="space-y-1.5"><div class="skeleton h-3.5 w-32"></div><div class="skeleton h-3 w-40"></div></div></div></td>' +
        '<td class="px-5 py-4"><div class="skeleton h-4 w-24"></div></td>' +
        '<td class="px-5 py-4"><div class="skeleton h-5 w-12 rounded-full"></div></td>' +
        '<td class="px-5 py-4"><div class="skeleton h-5 w-20 rounded-full"></div></td>' +
        '<td class="px-5 py-4 text-right"><div class="skeleton h-7 w-20 rounded-full ms-auto"></div></td>' +
      '</tr>').join('');
    checkAll.checked = false;
    checkAll.indeterminate = false;
    checkAll.disabled = true;
  }

  function emptyState(filtersActive) {
    body.innerHTML =
      '<tr><td colspan="' + COLSPAN + '" class="px-5 py-16 text-center">' +
        '<div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">' +
          '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>' +
        '</div>' +
        '<p class="text-gray-900 font-semibold">No candidates found</p>' +
        (filtersActive
          ? '<p class="text-gray-500 text-sm mt-1">Try clearing filters.</p>'
          : '<p class="text-gray-500 text-sm mt-1">Import a CV to add your first candidate.</p>') +
      '</td></tr>';
  }

  function errorState(msg) {
    body.innerHTML =
      '<tr><td colspan="' + COLSPAN + '" class="px-5 py-14 text-center">' +
        '<p class="text-red-600 font-semibold">Could not load candidates</p>' +
        '<p class="text-gray-500 text-sm mt-1">' + AR.esc(msg || 'Please try again.') + '</p>' +
        '<button type="button" id="cand-retry" class="btn-ghost mt-4 inline-flex">' +
          '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.99 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>' +
          'Retry' +
        '</button>' +
      '</td></tr>';
    const r = $('cand-retry');
    if (r) r.addEventListener('click', loadCandidates);
  }

  function filtersActive() {
    return !!($('filter-search').value.trim() || $('filter-status').value || $('filter-job').value);
  }

  function render(list) {
    lastRows = list;
    if (!list.length) { emptyState(filtersActive()); refreshSelectionUI(); return; }
    body.innerHTML = list.map(rowHtml).join('');
    refreshSelectionUI();
  }

  // ---- Data loads --------------------------------------------------------
  async function loadCandidates() {
    skeleton();
    const params = new URLSearchParams();
    const s  = $('filter-search').value.trim();
    const st = $('filter-status').value;
    const j  = $('filter-job').value;
    if (s)  params.set('search', s);
    if (st) params.set('status', st);
    if (j)  params.set('job_id', j);
    const qs = params.toString() ? ('?' + params.toString()) : '';
    try {
      const list = unwrap(await AR.Api.get('/candidates' + qs));
      render(list);
    } catch (e) {
      AR.Toast.error(e.message || 'Failed to load candidates');
      errorState(e.message);
    }
  }

  async function loadJobs() {
    try {
      const jobs = unwrap(await AR.Api.get('/jobs'));
      const opts = jobs.map((j) =>
        '<option value="' + AR.esc(String(j.id)) + '">' + AR.esc(j.title || ('Job #' + j.id)) + '</option>'
      ).join('');
      $('filter-job').insertAdjacentHTML('beforeend', opts);
      // Same list feeds the import modal's job picker.
      $('imp-job').insertAdjacentHTML('beforeend', opts);
    } catch (e) {
      // Non-fatal: filters still work without the job list.
      console.warn('Could not load jobs for filters:', e && e.message);
    }
  }

  // ---- Import (CV / manual) ---------------------------------------------
  async function submitImport(e) {
    e.preventDefault();
    const first = $('imp-first').value.trim();
    const last  = $('imp-last').value.trim();
    const email = $('imp-email').value.trim();
    const phone = $('imp-phone').value.trim();
    const jobId = $('imp-job').value;
    const fileEl = $('import-file');

    if (!first || !email) {
      AR.Toast.error('First name and email are required.');
      return;
    }

    const fd = new FormData();
    fd.append('first_name', first);
    fd.append('last_name', last);
    fd.append('email', email);
    fd.append('phone', phone);
    fd.append('job_id', jobId);
    if (fileEl.files && fileEl.files[0]) fd.append('cv', fileEl.files[0]);

    const btn = $('import-submit');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('opacity-70', 'cursor-not-allowed');
    btn.innerHTML = '<svg class="w-4 h-4 spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> Saving…';

    try {
      await AR.Api.post('/candidates', fd); // AR.Api passes FormData through as-is.
      AR.Toast.success('Candidate added');
      AR.Modal.close('import-modal');
      $('import-form').reset();
      $('import-file-name').textContent = 'Drag a file here or click to browse';
      loadCandidates();
    } catch (err) {
      AR.Toast.error(err.message || 'Could not add candidate');
    } finally {
      btn.disabled = false;
      btn.classList.remove('opacity-70', 'cursor-not-allowed');
      btn.innerHTML = original;
    }
  }

  // ---- Boot --------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    // Debounced live search.
    let searchTimer = null;
    $('filter-search').addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(loadCandidates, 300);
    });
    $('filter-status').addEventListener('change', loadCandidates);
    $('filter-job').addEventListener('change', loadCandidates);

    // Row selection (delegated — survives re-renders).
    body.addEventListener('change', function (ev) {
      const cb = ev.target.closest('.row-check');
      if (!cb) return;
      const id = String(cb.getAttribute('data-id'));
      if (cb.checked) selected.add(id); else selected.delete(id);
      refreshSelectionUI();
    });

    // Header "select all visible".
    checkAll.addEventListener('change', function () {
      const boxes = body.querySelectorAll('.row-check');
      boxes.forEach((cb) => {
        cb.checked = checkAll.checked;
        const id = String(cb.getAttribute('data-id'));
        if (checkAll.checked) selected.add(id); else selected.delete(id);
      });
      refreshSelectionUI();
    });

    // Compare actions.
    compareBtn.addEventListener('click', goCompare);
    bulkCompare.addEventListener('click', goCompare);
    bulkClear.addEventListener('click', function () {
      selected.clear();
      body.querySelectorAll('.row-check').forEach((cb) => { cb.checked = false; });
      refreshSelectionUI();
    });

    // Import modal: reflect chosen filename + submit.
    $('import-file').addEventListener('change', function () {
      const f = this.files && this.files[0];
      $('import-file-name').textContent = f ? f.name : 'Drag a file here or click to browse';
    });
    $('import-form').addEventListener('submit', submitImport);

    loadJobs();
    loadCandidates();
  });
})();
</script>
