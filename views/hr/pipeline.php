<?php
/**
 * Kanban pipeline — 7 stage board with drag & drop.
 * Fragment rendered inside views/layouts/app.php.
 * Uses /assets/js/kanban.js (window.KanbanBoard).
 */
?>
<div class="px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Header -->
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
        </span>
        <?= e(app_lang('Pipeline')) ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">Drag candidates across stages. Double-click a card to open the profile.</p>
    </div>

    <!-- Controls -->
    <div class="flex flex-col sm:flex-row gap-3">
      <select id="kanban-job-filter" class="rounded-xl border border-gray-200 px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500 min-w-[12rem]">
        <option value="">All jobs</option>
      </select>
      <div class="relative">
        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input id="kanban-search" type="text" placeholder="Search candidates&hellip;" class="w-full sm:w-64 rounded-xl border border-gray-200 pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
      </div>
    </div>
  </div>

  <!-- Legend -->
  <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mb-5 text-xs text-gray-500">
    <span class="font-semibold text-gray-600">Stages:</span>
    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>Applied</span>
    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-400"></span>Screening</span>
    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-violet-500"></span>AI Interview</span>
    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>Human Interview</span>
    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>Offer</span>
    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>Hired</span>
    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>Rejected</span>
  </div>

  <!-- Board -->
  <div id="kanban-root" class="flex gap-4 overflow-x-auto pb-4"></div>
</div>

<script src="/assets/js/kanban.js"></script>
<script>
(function () {
  'use strict';

  function unwrapJobs(d) {
    if (Array.isArray(d)) return d;
    if (d && Array.isArray(d.jobs)) return d.jobs;
    if (d && Array.isArray(d.data)) return d.data;
    return [];
  }

  async function populateJobFilter() {
    const sel = document.getElementById('kanban-job-filter');
    if (!sel) return;
    try {
      const jobs = unwrapJobs(await window.AR.Api.get('/jobs'));
      jobs.forEach(function (j) {
        const opt = document.createElement('option');
        opt.value = j.id;
        opt.textContent = j.title || ('Job #' + j.id);
        sel.appendChild(opt);
      });
    } catch (e) {
      // Non-fatal: board still works with "All jobs".
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    populateJobFilter();
    const b = new KanbanBoard('kanban-root');
    b.init();
  });
})();
</script>
