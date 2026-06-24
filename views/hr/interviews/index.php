<?php
/**
 * Interviews list — AI interview history with filters.
 * Fragment rendered inside views/layouts/app.php.
 */
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-3 3-3-3z"/></svg>
        </span>
        <?= e(app_lang('Interviews')) ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">AI-conducted interviews, scores and reports.</p>
    </div>
    <a href="/pipeline" class="btn-ghost self-start sm:self-auto">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
      View Pipeline
    </a>
  </div>

  <!-- Filter bar -->
  <div class="card p-4 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Type</label>
        <select id="f-type" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          <option value="">All types</option>
          <option value="ai_text">AI Text</option>
          <option value="ai_voice">AI Voice</option>
          <option value="ai_video">AI Video</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
        <select id="f-status" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="in_progress">In progress</option>
          <option value="completed">Completed</option>
          <option value="expired">Expired</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Date</label>
        <input id="f-date" type="date" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
      </div>
      <div class="flex items-end gap-2">
        <button id="f-apply" class="btn-primary flex-1 justify-center">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L14 14.414V19a1 1 0 01-.553.894l-4 2A1 1 0 018 21v-6.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
          Filter
        </button>
        <button id="f-reset" class="btn-ghost" title="Reset filters">Reset</button>
      </div>
    </div>
  </div>

  <!-- Table card -->
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-gray-100 bg-gray-50/60">
            <th class="px-5 py-3 font-semibold">Candidate</th>
            <th class="px-5 py-3 font-semibold">Job</th>
            <th class="px-5 py-3 font-semibold">Type</th>
            <th class="px-5 py-3 font-semibold">Status</th>
            <th class="px-5 py-3 font-semibold">Date</th>
            <th class="px-5 py-3 font-semibold">Score</th>
            <th class="px-5 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="iv-rows" class="divide-y divide-gray-100"></tbody>
      </table>
    </div>
    <div id="iv-empty" class="hidden py-16 text-center">
      <div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">
        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-3 3-3-3z"/></svg>
      </div>
      <p class="text-gray-900 font-semibold">No interviews found</p>
      <p class="text-gray-500 text-sm mt-1">Invite candidates to an AI interview from the pipeline to see results here.</p>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const rows = $('iv-rows');

  function unwrap(d) {
    if (Array.isArray(d)) return d;
    if (d && Array.isArray(d.interviews)) return d.interviews;
    if (d && Array.isArray(d.data)) return d.data;
    return [];
  }

  const TYPE_META = {
    ai_text:  { label: 'AI Text',  cls: 'badge-blue',   icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.83L3 20l1.13-3.39A7.94 7.94 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>' },
    ai_voice: { label: 'AI Voice', cls: 'badge-violet', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-14 0m7 7v3m0-3a4 4 0 01-4-4V7a4 4 0 118 0v4a4 4 0 01-4 4z"/>' },
    ai_video: { label: 'AI Video', cls: 'badge-green',  icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 6h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/>' }
  };
  const STATUS_META = {
    completed:   'badge-green',
    in_progress: 'badge-yellow',
    pending:     'badge-gray',
    expired:     'badge-red',
    failed:      'badge-red'
  };

  function typeBadge(t) {
    const m = TYPE_META[t] || { label: t || '—', cls: 'badge-gray', icon: '' };
    return '<span class="badge ' + m.cls + ' gap-1">' +
      (m.icon ? '<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' + m.icon + '</svg>' : '') +
      AR.esc(m.label) + '</span>';
  }
  function statusBadge(s) {
    const cls = STATUS_META[s] || 'badge-gray';
    const label = (s || 'unknown').replace(/_/g, ' ');
    return '<span class="badge ' + cls + ' capitalize">' + AR.esc(label) + '</span>';
  }
  function fmtDate(d) {
    if (!d) return '<span class="text-gray-400">—</span>';
    const dt = new Date(String(d).replace(' ', 'T'));
    if (isNaN(dt)) return AR.esc(d);
    return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }
  function scoreCell(v) {
    if (v == null || v === '') return '<span class="badge badge-gray">—</span>';
    const n = Math.round(Number(v));
    return '<span class="badge ' + AR.scoreColor(n) + '">' + n + '%</span>';
  }

  function skeleton() {
    rows.innerHTML = Array.from({ length: 6 }).map(() =>
      '<tr>' + Array.from({ length: 7 }).map(() =>
        '<td class="px-5 py-4"><div class="skeleton h-4 w-24"></div></td>').join('') + '</tr>').join('');
    $('iv-empty').classList.add('hidden');
  }

  function rowHtml(iv) {
    const name = iv.candidate_name || 'Unknown candidate';
    const initials = (name).split(' ').map(s => s[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
    const canReport = iv.status === 'completed' || iv.overall_score != null;
    const action = canReport
      ? '<a href="/interviews/' + encodeURIComponent(iv.id) + '/report" class="btn-primary !py-1.5 !px-3 text-xs">View Report</a>'
      : '<span class="text-xs text-gray-400 italic">Awaiting completion</span>';
    return '<tr class="hover:bg-violet-50/40 transition">' +
      '<td class="px-5 py-4">' +
        '<div class="flex items-center gap-3">' +
          '<div class="w-9 h-9 rounded-full gradient-brand text-white flex items-center justify-center text-xs font-bold shrink-0">' + AR.esc(initials) + '</div>' +
          '<div class="font-medium text-gray-900">' + AR.esc(name) + '</div>' +
        '</div>' +
      '</td>' +
      '<td class="px-5 py-4 text-gray-600">' + AR.esc(iv.job_title || '—') + '</td>' +
      '<td class="px-5 py-4">' + typeBadge(iv.type) + '</td>' +
      '<td class="px-5 py-4">' + statusBadge(iv.status) + '</td>' +
      '<td class="px-5 py-4 text-gray-600">' + fmtDate(iv.completed_at || iv.created_at) + '</td>' +
      '<td class="px-5 py-4">' + scoreCell(iv.overall_score) + '</td>' +
      '<td class="px-5 py-4 text-right">' + action + '</td>' +
    '</tr>';
  }

  async function load() {
    skeleton();
    const params = new URLSearchParams();
    const t = $('f-type').value, s = $('f-status').value, d = $('f-date').value;
    if (t) params.set('type', t);
    if (s) params.set('status', s);
    if (d) params.set('date', d);
    const qs = params.toString() ? ('?' + params.toString()) : '';
    try {
      const list = unwrap(await AR.Api.get('/interviews' + qs));
      if (!list.length) {
        rows.innerHTML = '';
        $('iv-empty').classList.remove('hidden');
        return;
      }
      $('iv-empty').classList.add('hidden');
      rows.innerHTML = list.map(rowHtml).join('');
    } catch (e) {
      rows.innerHTML = '<tr><td colspan="7" class="px-5 py-12 text-center text-red-600">' +
        '<div class="font-semibold">Could not load interviews</div>' +
        '<div class="text-sm text-gray-500 mt-1">' + AR.esc(e.message || 'Please try again.') + '</div></td></tr>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    $('f-apply').addEventListener('click', load);
    $('f-reset').addEventListener('click', function () {
      $('f-type').value = ''; $('f-status').value = ''; $('f-date').value = '';
      load();
    });
    ['f-type', 'f-status', 'f-date'].forEach(id => $(id).addEventListener('change', load));
    load();
  });
})();
</script>
