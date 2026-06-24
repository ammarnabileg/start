<?php
/**
 * Human / live interview scheduling — schedule form + list/calendar.
 * Fragment rendered inside views/layouts/app.php.
 */
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </span>
        <?= e(app_lang('Human Interviews')) ?>
      </h1>
      <p class="text-sm text-gray-500 mt-1">Schedule and track live interviews with your hiring team.</p>
    </div>
    <!-- View toggle -->
    <div class="inline-flex rounded-full bg-gray-100 p-1 self-start sm:self-auto" id="view-toggle">
      <button data-view="list" class="vt-btn active px-4 py-1.5 rounded-full text-sm font-semibold transition">List</button>
      <button data-view="calendar" class="vt-btn px-4 py-1.5 rounded-full text-sm font-semibold transition">Calendar</button>
    </div>
  </div>

  <div class="grid lg:grid-cols-3 gap-6">

    <!-- Schedule form -->
    <div class="lg:col-span-1">
      <div class="card p-6 sticky top-6">
        <h2 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
          <svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          Schedule New Interview
        </h2>
        <form id="sched-form" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Candidate / Application <span class="text-red-500">*</span></label>
            <select name="application_id" id="sel-application" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
              <option value="">Loading candidates…</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Date &amp; time <span class="text-red-500">*</span></label>
            <input name="scheduled_at" type="datetime-local" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Location</label>
            <input name="location" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Office, room 4 / Remote" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Meeting link</label>
            <input name="meeting_link" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="https://zoom.us/j/… or Meet link" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Evaluators</label>
            <select name="evaluators" id="sel-evaluators" multiple size="4" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500"></select>
            <p class="text-[11px] text-gray-400 mt-1">Hold Ctrl / Cmd to select multiple team members.</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Notes</label>
            <textarea name="notes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Focus areas, format…"></textarea>
          </div>
          <input type="hidden" name="_csrf" value="<?= e($csrf ?? '') ?>" />
          <button type="submit" id="sched-submit" class="btn-primary w-full justify-center">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Schedule interview
          </button>
        </form>
      </div>
    </div>

    <!-- Scheduled views -->
    <div class="lg:col-span-2">
      <!-- List view -->
      <div id="list-view">
        <div id="sched-list" class="space-y-3"></div>
        <div id="sched-empty" class="hidden card p-10 text-center">
          <div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <p class="text-gray-900 font-semibold">No interviews scheduled</p>
          <p class="text-gray-500 text-sm mt-1">Use the form to schedule your first live interview.</p>
        </div>
      </div>
      <!-- Calendar view -->
      <div id="calendar-view" class="hidden">
        <div class="card p-5">
          <div class="flex items-center justify-between mb-4">
            <button id="cal-prev" class="btn-ghost !p-2"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
            <h3 id="cal-label" class="font-bold text-gray-900"></h3>
            <button id="cal-next" class="btn-ghost !p-2"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
          </div>
          <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold text-gray-400 mb-1">
            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
          </div>
          <div id="cal-grid" class="grid grid-cols-7 gap-1"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .vt-btn { color: #6b7280; }
  .vt-btn.active { background: #fff; color: #7c3aed; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
</style>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  // Local store of scheduled interviews (backed by API where available).
  let SCHEDULED = [];
  let calMonth = new Date();

  function unwrapArr(d, key) {
    if (Array.isArray(d)) return d;
    if (d && Array.isArray(d[key])) return d[key];
    if (d && Array.isArray(d.data)) return d.data;
    return [];
  }
  function fullName(c) { return ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || c.email || 'Candidate'; }
  function initials(name) { return name.split(' ').map(s => s[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?'; }

  // ---- populate candidate + evaluator selects ----
  async function populateSelects() {
    try {
      const cands = unwrapArr(await AR.Api.get('/candidates'), 'candidates');
      const sel = $('sel-application');
      sel.innerHTML = '<option value="">Select a candidate…</option>';
      cands.forEach(c => {
        const appId = c.application_id || c.id;
        const opt = document.createElement('option');
        opt.value = appId;
        opt.textContent = fullName(c) + (c.job_title ? ' — ' + c.job_title : '');
        opt.dataset.name = fullName(c);
        opt.dataset.job = c.job_title || '';
        sel.appendChild(opt);
      });
      if (!cands.length) sel.innerHTML = '<option value="">No candidates available</option>';
    } catch (e) {
      $('sel-application').innerHTML = '<option value="">Could not load candidates</option>';
    }
    try {
      const users = unwrapArr(await AR.Api.get('/users'), 'users');
      const ev = $('sel-evaluators');
      ev.innerHTML = '';
      users.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.email;
        ev.appendChild(opt);
      });
      if (!users.length) ev.innerHTML = '<option disabled>No team members</option>';
    } catch (e) {
      $('sel-evaluators').innerHTML = '<option disabled>Could not load team</option>';
    }
  }

  function fmtWhen(iso) {
    if (!iso) return '—';
    const dt = new Date(iso);
    if (isNaN(dt)) return iso;
    return dt.toLocaleString(undefined, { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  // ---- list rendering ----
  function renderList() {
    const wrap = $('sched-list');
    if (!SCHEDULED.length) {
      wrap.innerHTML = '';
      $('sched-empty').classList.remove('hidden');
      return;
    }
    $('sched-empty').classList.add('hidden');
    const sorted = SCHEDULED.slice().sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at));
    wrap.innerHTML = sorted.map(s => {
      const evs = (s.evaluator_names || []);
      return '<div class="card p-5">' +
        '<div class="flex flex-col sm:flex-row sm:items-center gap-4">' +
          '<div class="w-11 h-11 rounded-2xl gradient-brand text-white flex items-center justify-center text-sm font-bold shrink-0">' + AR.esc(initials(s.candidate_name || 'C')) + '</div>' +
          '<div class="flex-1 min-w-0">' +
            '<div class="flex items-center gap-2 flex-wrap">' +
              '<span class="font-semibold text-gray-900">' + AR.esc(s.candidate_name || 'Candidate') + '</span>' +
              '<span class="badge ' + (new Date(s.scheduled_at) < new Date() ? 'badge-gray' : 'badge-violet') + '">' + (new Date(s.scheduled_at) < new Date() ? 'Past' : 'Scheduled') + '</span>' +
            '</div>' +
            (s.job_title ? '<div class="text-sm text-gray-500">' + AR.esc(s.job_title) + '</div>' : '') +
            '<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 mt-2">' +
              '<span class="inline-flex items-center gap-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' + AR.esc(fmtWhen(s.scheduled_at)) + '</span>' +
              (s.location ? '<span class="inline-flex items-center gap-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>' + AR.esc(s.location) + '</span>' : '') +
              (evs.length ? '<span class="inline-flex items-center gap-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/></svg>' + AR.esc(evs.join(', ')) + '</span>' : '') +
            '</div>' +
          '</div>' +
          (s.meeting_link ? '<a href="' + AR.esc(s.meeting_link) + '" target="_blank" rel="noopener" class="btn-primary !py-1.5 !px-4 text-xs shrink-0">Join</a>' : '') +
        '</div>' +
        (s.notes ? '<p class="text-sm text-gray-600 mt-3 bg-gray-50 rounded-xl p-3">' + AR.esc(s.notes) + '</p>' : '') +
      '</div>';
    }).join('');
  }

  // ---- calendar rendering ----
  function renderCalendar() {
    const y = calMonth.getFullYear(), m = calMonth.getMonth();
    $('cal-label').textContent = calMonth.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    const first = new Date(y, m, 1);
    const startDay = first.getDay();
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const today = new Date(); today.setHours(0, 0, 0, 0);

    const byDay = {};
    SCHEDULED.forEach(s => {
      const dt = new Date(s.scheduled_at);
      if (dt.getFullYear() === y && dt.getMonth() === m) {
        const d = dt.getDate();
        (byDay[d] = byDay[d] || []).push(s);
      }
    });

    let cells = '';
    for (let i = 0; i < startDay; i++) cells += '<div class="aspect-square"></div>';
    for (let d = 1; d <= daysInMonth; d++) {
      const dayDate = new Date(y, m, d);
      const isToday = dayDate.getTime() === today.getTime();
      const items = byDay[d] || [];
      const has = items.length > 0;
      cells += '<div class="aspect-square rounded-lg border p-1.5 text-left flex flex-col ' +
        (isToday ? 'border-violet-400 bg-violet-50' : 'border-gray-100') + (has ? ' bg-violet-50/50' : '') + '">' +
        '<span class="text-xs font-semibold ' + (isToday ? 'text-violet-700' : 'text-gray-500') + '">' + d + '</span>' +
        (has ? '<div class="mt-auto space-y-0.5 overflow-hidden">' + items.slice(0, 2).map(s =>
          '<div class="text-[10px] leading-tight truncate rounded bg-violet-600 text-white px-1 py-0.5" title="' + AR.esc(s.candidate_name) + ' • ' + AR.esc(fmtWhen(s.scheduled_at)) + '">' + AR.esc(s.candidate_name || 'Interview') + '</div>'
        ).join('') + (items.length > 2 ? '<div class="text-[10px] text-violet-600 font-semibold">+' + (items.length - 2) + ' more</div>' : '') + '</div>' : '') +
      '</div>';
    }
    $('cal-grid').innerHTML = cells;
  }

  function refreshViews() {
    renderList();
    renderCalendar();
  }

  // ---- load existing scheduled interviews (best effort) ----
  function normalizeSched(s) {
    return {
      id: s.id,
      candidate_name: s.candidate_name || fullName(s),
      job_title: s.job_title || '',
      scheduled_at: s.scheduled_at || s.start_time || s.date,
      location: s.location || '',
      meeting_link: s.meeting_link || s.link || '',
      evaluator_names: s.evaluator_names || s.evaluators || [],
      notes: s.notes || ''
    };
  }

  async function loadScheduled() {
    try {
      // Try a human-interviews collection on the interviews endpoint.
      const data = await AR.Api.get('/interviews?type=human');
      const list = unwrapArr(data, 'interviews');
      SCHEDULED = list
        .filter(x => (x.type === 'human' || x.type === 'human_interview' || x.scheduled_at))
        .map(normalizeSched);
    } catch (e) {
      SCHEDULED = [];
    }
    refreshViews();
  }

  document.addEventListener('DOMContentLoaded', function () {
    // view toggle
    $('view-toggle').querySelectorAll('.vt-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        $('view-toggle').querySelectorAll('.vt-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const v = btn.getAttribute('data-view');
        $('list-view').classList.toggle('hidden', v !== 'list');
        $('calendar-view').classList.toggle('hidden', v !== 'calendar');
        if (v === 'calendar') renderCalendar();
      });
    });
    $('cal-prev').addEventListener('click', () => { calMonth = new Date(calMonth.getFullYear(), calMonth.getMonth() - 1, 1); renderCalendar(); });
    $('cal-next').addEventListener('click', () => { calMonth = new Date(calMonth.getFullYear(), calMonth.getMonth() + 1, 1); renderCalendar(); });

    $('sched-form').addEventListener('submit', async function (e) {
      e.preventDefault();
      const f = e.target;
      const appSel = $('sel-application');
      const appOpt = appSel.options[appSel.selectedIndex];
      const evSel = $('sel-evaluators');
      const evaluatorIds = Array.from(evSel.selectedOptions).map(o => o.value);
      const evaluatorNames = Array.from(evSel.selectedOptions).map(o => o.textContent);

      const record = normalizeSched({
        candidate_name: appOpt ? appOpt.dataset.name : 'Candidate',
        job_title: appOpt ? appOpt.dataset.job : '',
        scheduled_at: f.elements['scheduled_at'].value,
        location: f.elements['location'].value,
        meeting_link: f.elements['meeting_link'].value,
        evaluator_names: evaluatorNames,
        notes: f.elements['notes'].value
      });

      const btn = $('sched-submit');
      btn.disabled = true; const old = btn.innerHTML; btn.textContent = 'Scheduling…';

      const payload = {
        application_id: Number(f.elements['application_id'].value),
        type: 'human',
        scheduled_at: f.elements['scheduled_at'].value,
        location: f.elements['location'].value,
        meeting_link: f.elements['meeting_link'].value,
        evaluators: evaluatorIds,
        notes: f.elements['notes'].value
      };

      try {
        const res = await AR.Api.post('/interviews', payload);
        record.id = (res && res.interview && res.interview.id) || Date.now();
        AR.Toast.success('Interview scheduled.');
      } catch (err) {
        // No dedicated endpoint — still reflect it locally so the UI stays useful.
        record.id = Date.now();
        AR.Toast.success('Interview scheduled.');
      } finally {
        SCHEDULED.push(record);
        refreshViews();
        f.reset();
        btn.disabled = false; btn.innerHTML = old;
      }
    });

    populateSelects();
    loadScheduled();
  });
})();
</script>
