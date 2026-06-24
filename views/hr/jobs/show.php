<?php
/**
 * Job detail — overview, candidates, interviews and settings tabs.
 * Fragment rendered inside views/layouts/app.php.
 * Controller passes $jobId.
 */
$jobId = isset($jobId) ? (int) $jobId : 0;
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Back link -->
  <nav class="mb-3">
    <a href="/jobs" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-violet-600 transition">
      <svg class="w-4 h-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
      Back to Jobs
    </a>
  </nav>

  <!-- ============ Header card ============ -->
  <div class="card p-6 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
      <div class="min-w-0">
        <div class="flex items-center gap-3 flex-wrap">
          <h1 id="job-title" class="text-2xl font-bold text-gray-900">
            <span class="skeleton inline-block h-7 w-64 align-middle rounded-md"></span>
          </h1>
          <span id="job-status" class="badge badge-gray hidden">—</span>
        </div>
        <p id="job-meta" class="text-sm text-gray-500 mt-2">
          <span class="skeleton inline-block h-4 w-80 max-w-full rounded"></span>
        </p>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <a id="job-edit" href="/jobs/<?= $jobId ?>/edit" class="btn-ghost">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 8.25l-3.75-3.75"/></svg>
          Edit
        </a>
        <button type="button" id="job-publish" class="btn-primary hidden">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-7 7m7-7l7 7"/></svg>
          <span class="publish-label">Publish</span>
        </button>
      </div>
    </div>
  </div>

  <!-- ============ Tabs ============ -->
  <div class="border-b border-gray-200 mb-6">
    <nav class="flex gap-6 -mb-px overflow-x-auto" id="job-tabs" role="tablist">
      <button type="button" data-tab="overview"
              class="job-tab whitespace-nowrap border-b-2 border-violet-600 text-violet-600 font-semibold py-3 px-1 text-sm transition">Overview</button>
      <button type="button" data-tab="candidates"
              class="job-tab whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-semibold py-3 px-1 text-sm transition">Candidates</button>
      <button type="button" data-tab="interviews"
              class="job-tab whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-semibold py-3 px-1 text-sm transition">Interviews</button>
      <button type="button" data-tab="settings"
              class="job-tab whitespace-nowrap border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-semibold py-3 px-1 text-sm transition">Settings</button>
    </nav>
  </div>

  <!-- ============ Panels ============ -->

  <!-- Overview -->
  <section data-panel="overview" class="space-y-6 fade-in">
    <div class="card p-6">
      <h2 class="text-base font-semibold text-gray-900 mb-3">Description</h2>
      <div id="ov-description" class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">
        <span class="skeleton block h-4 w-full mb-2 rounded"></span>
        <span class="skeleton block h-4 w-5/6 mb-2 rounded"></span>
        <span class="skeleton block h-4 w-2/3 rounded"></span>
      </div>
    </div>

    <div class="card p-6">
      <h2 class="text-base font-semibold text-gray-900 mb-3">Requirements</h2>
      <div id="ov-requirements" class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">—</div>
    </div>

    <div class="card p-6">
      <div class="flex items-center gap-2 mb-4">
        <span class="inline-flex w-7 h-7 rounded-lg bg-violet-50 text-violet-600 items-center justify-center">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
        </span>
        <h2 class="text-base font-semibold text-gray-900">AI Evaluation Criteria</h2>
      </div>
      <div id="ov-criteria" class="space-y-3">
        <span class="skeleton block h-12 w-full rounded-lg"></span>
      </div>
    </div>
  </section>

  <!-- Candidates -->
  <section data-panel="candidates" class="hidden fade-in">
    <div class="card overflow-hidden">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-5 border-b border-gray-100">
        <div>
          <h2 class="text-base font-semibold text-gray-900">Applicants</h2>
          <p class="text-sm text-gray-500 mt-0.5">Candidates who applied to this job.</p>
        </div>
        <button type="button" id="match-ai" class="btn-accent shrink-0">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
          <span class="match-label">Match with AI</span>
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-gray-100 bg-gray-50/60">
              <th class="px-5 py-3 font-semibold">Name</th>
              <th class="px-5 py-3 font-semibold">AI Score</th>
              <th class="px-5 py-3 font-semibold">Stage</th>
              <th class="px-5 py-3 font-semibold text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="job-candidates-body" class="divide-y divide-gray-100"></tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- Interviews -->
  <section data-panel="interviews" class="hidden fade-in">
    <div id="job-interviews" class="space-y-3">
      <div class="card p-4"><span class="skeleton block h-16 w-full rounded-lg"></span></div>
      <div class="card p-4"><span class="skeleton block h-16 w-full rounded-lg"></span></div>
    </div>
  </section>

  <!-- Settings -->
  <section data-panel="settings" class="hidden fade-in">
    <div class="card p-6">
      <div class="flex items-center gap-2 mb-5">
        <span class="inline-flex w-7 h-7 rounded-lg bg-violet-50 text-violet-600 items-center justify-center">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </span>
        <h2 class="text-base font-semibold text-gray-900">Interview configuration</h2>
      </div>

      <dl class="divide-y divide-gray-100">
        <div class="flex items-center justify-between gap-4 py-3">
          <dt class="text-sm text-gray-500">Interview Type</dt>
          <dd id="set-interview-type" class="text-sm font-medium text-gray-900 text-end">—</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-3">
          <dt class="text-sm text-gray-500">Avatar</dt>
          <dd id="set-avatar" class="text-sm font-medium text-gray-900 text-end">—</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-3">
          <dt class="text-sm text-gray-500">Department</dt>
          <dd id="set-department" class="text-sm font-medium text-gray-900 text-end">—</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-3">
          <dt class="text-sm text-gray-500">Location</dt>
          <dd id="set-location" class="text-sm font-medium text-gray-900 text-end">—</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-3">
          <dt class="text-sm text-gray-500">Job Type</dt>
          <dd id="set-job-type" class="text-sm font-medium text-gray-900 text-end">—</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-3">
          <dt class="text-sm text-gray-500">Salary</dt>
          <dd id="set-salary" class="text-sm font-medium text-gray-900 text-end">—</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-3">
          <dt class="text-sm text-gray-500">Currency</dt>
          <dd id="set-currency" class="text-sm font-medium text-gray-900 text-end">—</dd>
        </div>
        <div class="flex items-center justify-between gap-4 py-3">
          <dt class="text-sm text-gray-500">Status</dt>
          <dd id="set-status" class="text-end">—</dd>
        </div>
      </dl>

      <div class="mt-5 pt-4 border-t border-gray-100 flex items-center justify-between gap-3">
        <p class="text-xs text-gray-400">Edit the job to change these settings.</p>
        <a href="/jobs/<?= $jobId ?>/edit" class="btn-ghost text-sm">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 8.25l-3.75-3.75"/></svg>
          Edit Job
        </a>
      </div>
    </div>
  </section>

</div>

<script>
(function () {
  'use strict';
  var JOB_ID = <?= $jobId ?>;
  const $ = (id) => document.getElementById(id);

  // ---------- Formatting helpers ----------
  const STATUS_META = {
    draft:     { cls: 'badge-gray',   label: 'Draft' },
    published: { cls: 'badge-green',  label: 'Published' },
    closed:    { cls: 'badge-red',    label: 'Closed' }
  };
  const TYPE_LABELS = { ai_text: 'AI Text', ai_voice: 'AI Voice', ai_video: 'AI Video' };

  function prettyStage(s) {
    if (!s) return 'New';
    return String(s).replace(/[_-]+/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }
  function fmtMoney(n) {
    const num = Number(n);
    if (n == null || n === '' || isNaN(num)) return null;
    return num.toLocaleString();
  }
  function fmtSalary(job) {
    const min = fmtMoney(job.salary_min);
    const max = fmtMoney(job.salary_max);
    const cur = job.currency ? (job.currency + ' ') : '';
    if (min && max) return cur + min + ' – ' + max;
    if (min) return 'From ' + cur + min;
    if (max) return 'Up to ' + cur + max;
    return null;
  }
  function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(String(d).replace(' ', 'T'));
    if (isNaN(dt)) return String(d);
    return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }
  function statusBadge(status) {
    const m = STATUS_META[status] || { cls: 'badge-gray', label: prettyStage(status || 'unknown') };
    return '<span class="badge ' + m.cls + '">' + AR.esc(m.label) + '</span>';
  }
  function initials(name) {
    return (name || '').split(' ').map(function (s) { return s[0]; }).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
  }

  // ---------- Tabs ----------
  const tabs = Array.prototype.slice.call(document.querySelectorAll('.job-tab'));
  const panels = Array.prototype.slice.call(document.querySelectorAll('[data-panel]'));
  let loadedCandidates = false, loadedInterviews = false;

  function activate(name) {
    tabs.forEach(function (t) {
      const on = t.getAttribute('data-tab') === name;
      t.classList.toggle('border-violet-600', on);
      t.classList.toggle('text-violet-600', on);
      t.classList.toggle('border-transparent', !on);
      t.classList.toggle('text-gray-500', !on);
      t.classList.toggle('hover:text-gray-700', !on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach(function (p) {
      p.classList.toggle('hidden', p.getAttribute('data-panel') !== name);
    });
    if (name === 'candidates' && !loadedCandidates) { loadedCandidates = true; loadCandidates(); }
    if (name === 'interviews' && !loadedInterviews) { loadedInterviews = true; loadInterviews(); }
  }

  // ---------- Load job ----------
  let JOB = null;

  function renderError(msg) {
    $('job-title').innerHTML = '<span class="text-red-600">Could not load this job.</span>';
    $('job-status').classList.add('hidden');
    $('job-meta').innerHTML = '<span class="text-sm text-gray-500">' + AR.esc(msg || 'Please try again.') +
      ' &middot; </span><a href="/jobs" class="text-sm font-medium text-violet-600 hover:underline">Back to Jobs</a>';
    $('job-edit').classList.add('hidden');
  }

  function setText(id, value, fallback) {
    const el = $(id);
    if (el) el.textContent = (value == null || value === '') ? (fallback || '—') : value;
  }

  async function loadJob() {
    try {
      const job = await AR.Api.get('/jobs/' + JOB_ID);
      if (!job || typeof job !== 'object') { renderError('Job not found.'); return; }
      JOB = job;

      // Title + status
      $('job-title').textContent = job.title || 'Untitled job';
      const sBadge = $('job-status');
      const sm = STATUS_META[job.status] || { cls: 'badge-gray', label: prettyStage(job.status || 'unknown') };
      sBadge.className = 'badge ' + sm.cls;
      sBadge.textContent = sm.label;
      sBadge.classList.remove('hidden');

      // Meta line
      const bits = [];
      if (job.department) bits.push(AR.esc(job.department));
      if (job.location) bits.push(AR.esc(job.location));
      if (job.job_type) bits.push(AR.esc(prettyStage(job.job_type)));
      const sal = fmtSalary(job);
      if (sal) bits.push(AR.esc(sal));
      $('job-meta').innerHTML = bits.length
        ? bits.join('<span class="mx-2 text-gray-300">&bull;</span>')
        : '<span class="text-gray-400">No additional details.</span>';

      // Publish button (only for draft)
      const pub = $('job-publish');
      if (job.status === 'draft') pub.classList.remove('hidden');
      else pub.classList.add('hidden');

      // Overview
      $('ov-description').textContent = job.description || 'No description provided.';
      $('ov-requirements').textContent = job.requirements || 'No requirements provided.';
      renderCriteria(job.ai_criteria);

      // Settings
      setText('set-interview-type', TYPE_LABELS[job.interview_type] || (job.interview_type ? prettyStage(job.interview_type) : null));
      setText('set-avatar', job.avatar_name || (job.avatar_id != null ? ('Avatar #' + job.avatar_id) : null));
      setText('set-department', job.department);
      setText('set-location', job.location);
      setText('set-job-type', job.job_type ? prettyStage(job.job_type) : null);
      setText('set-salary', sal);
      setText('set-currency', job.currency);
      $('set-status').innerHTML = statusBadge(job.status);
    } catch (err) {
      renderError(err.message);
      AR.Toast.error(err.message || 'Could not load this job');
    }
  }

  function weightBadge(w) {
    if (w == null || w === '') return '';
    let n = Number(w);
    if (isNaN(n)) return '<span class="badge badge-violet">' + AR.esc(String(w)) + '</span>';
    if (n > 0 && n <= 1) n = Math.round(n * 100);
    else n = Math.round(n);
    return '<span class="badge badge-violet shrink-0">' + n + '%</span>';
  }
  function renderCriteria(criteria) {
    const box = $('ov-criteria');
    const arr = Array.isArray(criteria) ? criteria : [];
    if (!arr.length) {
      box.innerHTML = '<p class="text-sm text-gray-400">No AI criteria defined.</p>';
      return;
    }
    box.innerHTML = arr.map(function (c) {
      return '<div class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 p-3.5">' +
          '<div class="min-w-0">' +
            '<p class="text-sm font-semibold text-gray-900">' + AR.esc(c.criterion_name || c.name || 'Criterion') + '</p>' +
            (c.description ? '<p class="text-xs text-gray-500 leading-snug mt-0.5">' + AR.esc(c.description) + '</p>' : '') +
          '</div>' +
          weightBadge(c.weight) +
        '</div>';
    }).join('');
  }

  // ---------- Candidates ----------
  const cbody = $('job-candidates-body');

  function candidateSkeleton() {
    cbody.innerHTML = Array.from({ length: 4 }).map(function () {
      return '<tr>' + Array.from({ length: 4 }).map(function () {
        return '<td class="px-5 py-4"><div class="skeleton h-5 w-24"></div></td>';
      }).join('') + '</tr>';
    }).join('');
  }
  function candidateEmpty() {
    cbody.innerHTML = '<tr><td colspan="4" class="px-5 py-16 text-center">' +
      '<div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">' +
        '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>' +
      '</div>' +
      '<p class="text-gray-900 font-semibold">No applicants yet</p>' +
      '<p class="text-gray-500 text-sm mt-1">Candidates who apply to this job will appear here.</p>' +
    '</td></tr>';
  }
  function scoreCell(v) {
    if (v == null || v === '') return '<span class="badge badge-gray">N/A</span>';
    const n = Math.round(Number(v));
    if (isNaN(n)) return '<span class="badge badge-gray">N/A</span>';
    return '<span class="badge ' + AR.scoreColor(n) + '">' + n + '%</span>';
  }
  function candidateRow(c) {
    const name = [c.first_name, c.last_name].filter(Boolean).join(' ') || c.email || 'Candidate';
    return '<tr class="hover:bg-violet-50/40 transition">' +
      '<td class="px-5 py-4">' +
        '<div class="flex items-center gap-3">' +
          '<div class="w-9 h-9 rounded-full gradient-brand text-white flex items-center justify-center text-xs font-bold shrink-0">' + AR.esc(initials(name)) + '</div>' +
          '<div class="min-w-0">' +
            '<div class="font-medium text-gray-900 truncate">' + AR.esc(name) + '</div>' +
            (c.email ? '<div class="text-xs text-gray-400 truncate">' + AR.esc(c.email) + '</div>' : '') +
          '</div>' +
        '</div>' +
      '</td>' +
      '<td class="px-5 py-4">' + scoreCell(c.ai_match_score) + '</td>' +
      '<td class="px-5 py-4"><span class="badge badge-violet">' + AR.esc(prettyStage(c.pipeline_stage)) + '</span></td>' +
      '<td class="px-5 py-4 text-end">' +
        '<a href="/candidates/' + encodeURIComponent(c.id) + '" class="btn-ghost !py-1.5 !px-3 text-xs">View 360</a>' +
      '</td>' +
    '</tr>';
  }

  async function loadCandidates() {
    candidateSkeleton();
    try {
      const list = await AR.Api.get('/candidates?job_id=' + encodeURIComponent(JOB_ID));
      const arr = Array.isArray(list) ? list : [];
      if (!arr.length) { candidateEmpty(); return; }
      cbody.innerHTML = arr.map(candidateRow).join('');
    } catch (err) {
      cbody.innerHTML = '<tr><td colspan="4" class="px-5 py-12 text-center text-red-600">' +
        '<div class="font-semibold">Could not load applicants</div>' +
        '<div class="text-sm text-gray-500 mt-1">' + AR.esc(err.message || 'Please try again.') + '</div></td></tr>';
    }
  }

  // ---------- Match with AI ----------
  const matchBtn = $('match-ai');
  matchBtn.addEventListener('click', async function () {
    const label = matchBtn.querySelector('.match-label');
    matchBtn.disabled = true;
    matchBtn.classList.add('opacity-70', 'cursor-wait');
    const prev = label.textContent;
    label.textContent = 'Matching…';
    try {
      await AR.Api.post('/ai/match-candidates', { job_id: JOB_ID, candidate_ids: [] });
      AR.Toast.success('AI matching complete');
      await loadCandidates();
    } catch (err) {
      AR.Toast.error(err.message || 'Matching failed');
    } finally {
      matchBtn.disabled = false;
      matchBtn.classList.remove('opacity-70', 'cursor-wait');
      label.textContent = prev;
    }
  });

  // ---------- Interviews ----------
  const ivBox = $('job-interviews');

  function ivStatusBadge(s) {
    const map = {
      completed:   'badge-green',
      in_progress: 'badge-blue',
      scheduled:   'badge-yellow',
      invited:     'badge-yellow',
      pending:     'badge-gray',
      expired:     'badge-red',
      failed:      'badge-red'
    };
    const cls = map[s] || 'badge-gray';
    return '<span class="badge ' + cls + '">' + AR.esc(prettyStage(s || 'unknown')) + '</span>';
  }
  function ivEmpty() {
    ivBox.innerHTML = '<div class="card p-12 text-center">' +
      '<div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">' +
        '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-3 3-3-3z"/></svg>' +
      '</div>' +
      '<p class="text-gray-900 font-semibold">No interviews yet</p>' +
      '<p class="text-gray-500 text-sm mt-1">Interviews for this job will appear here.</p>' +
    '</div>';
  }
  function ivRow(iv) {
    const score = (iv.overall_score != null && iv.overall_score !== '')
      ? '<span class="badge ' + AR.scoreColor(Math.round(Number(iv.overall_score))) + '">' + Math.round(Number(iv.overall_score)) + '%</span>'
      : '';
    const date = fmtDate(iv.completed_at || iv.created_at);
    const canReport = iv.status === 'completed' || iv.overall_score != null;
    return '<div class="card p-4 flex flex-col sm:flex-row sm:items-center gap-3 hover:shadow-md transition">' +
        '<div class="flex items-center gap-3 flex-1 min-w-0">' +
          '<span class="inline-flex w-10 h-10 rounded-xl bg-violet-50 text-violet-600 items-center justify-center shrink-0">' +
            '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-3 3-3-3z"/></svg>' +
          '</span>' +
          '<div class="min-w-0">' +
            '<div class="flex items-center gap-2 flex-wrap">' +
              '<span class="badge badge-violet">' + AR.esc(TYPE_LABELS[iv.type] || prettyStage(iv.type || 'Interview')) + '</span>' +
              ivStatusBadge(iv.status) +
              score +
            '</div>' +
            (date ? '<p class="text-xs text-gray-400 mt-1">' + AR.esc(date) + '</p>' : '') +
          '</div>' +
        '</div>' +
        (canReport
          ? '<a href="/interviews/' + encodeURIComponent(iv.id) + '/report" class="btn-ghost !py-1.5 !px-3 text-xs shrink-0 self-start sm:self-auto">View report</a>'
          : '<span class="text-xs text-gray-400 italic shrink-0 self-start sm:self-auto">Awaiting completion</span>') +
      '</div>';
  }

  async function loadInterviews() {
    try {
      const list = await AR.Api.get('/interviews');
      let arr = Array.isArray(list) ? list : [];
      // Filter to this job when the field is present; otherwise we cannot reliably
      // attribute, so fall back to the friendly empty state.
      const hasJobField = arr.some(function (i) { return i && i.job_id != null; });
      if (hasJobField) {
        arr = arr.filter(function (i) { return String(i.job_id) === String(JOB_ID); });
      } else {
        arr = [];
      }
      if (!arr.length) { ivEmpty(); return; }
      ivBox.innerHTML = arr.map(ivRow).join('');
    } catch (err) {
      ivBox.innerHTML = '<div class="card p-8 text-center text-red-600">' +
        '<div class="font-semibold">Could not load interviews</div>' +
        '<div class="text-sm text-gray-500 mt-1">' + AR.esc(err.message || 'Please try again.') + '</div></div>';
    }
  }

  // ---------- Publish ----------
  const publishBtn = $('job-publish');
  publishBtn.addEventListener('click', async function () {
    const label = publishBtn.querySelector('.publish-label');
    publishBtn.disabled = true;
    publishBtn.classList.add('opacity-70', 'cursor-wait');
    const prev = label.textContent;
    label.textContent = 'Publishing…';
    try {
      await AR.Api.post('/jobs/' + JOB_ID + '/publish', {});
      AR.Toast.success('Job published');
      await loadJob();
    } catch (err) {
      AR.Toast.error(err.message || 'Could not publish job');
    } finally {
      publishBtn.disabled = false;
      publishBtn.classList.remove('opacity-70', 'cursor-wait');
      label.textContent = prev;
    }
  });

  // ---------- Boot ----------
  document.addEventListener('DOMContentLoaded', function () {
    tabs.forEach(function (t) {
      t.addEventListener('click', function () { activate(t.getAttribute('data-tab')); });
    });
    loadJob();
    // Eager-load the other tabs so they are instant when opened.
    loadedCandidates = true; loadCandidates();
    loadedInterviews = true; loadInterviews();
  });
})();
</script>
