<?php
/**
 * Candidate 360 — "HR Decision Center" (flagship).
 * A single-fetch profile screen: header with recommendation + score ring,
 * 6 tabs (Overview / Interview / Personality / Red Flags / Timeline / Actions),
 * a real canvas radar chart of skills, DISC quadrant, Big-Five bars, a
 * chronological activity timeline, and three working action modals.
 *
 * Fragment: rendered into $content and wrapped by views/layouts/app.php.
 * In scope: $candidateId (int). All other data is fetched client-side from
 *           GET /api/v1/candidates/{id}/profile via window.AR.
 */
$candidateId = isset($candidateId) ? (int) $candidateId : 0;
?>
<div class="max-w-6xl mx-auto fade-in" data-cand-id="<?= e($candidateId) ?>">

  <!-- Back link -->
  <a href="/candidates" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-violet-600 mb-5 transition">
    <svg class="w-4 h-4 rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Back to Candidates
  </a>

  <!-- ============ HEADER CARD ============ -->
  <div class="card p-6 mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center gap-6">

      <!-- LEFT: identity -->
      <div class="flex items-start gap-4 flex-1 min-w-0">
        <div id="cand-initials" class="w-16 h-16 shrink-0 rounded-2xl gradient-brand text-white flex items-center justify-center text-xl font-bold shadow-sm">
          <span class="skeleton inline-block w-7 h-7 rounded"></span>
        </div>
        <div class="min-w-0">
          <h1 id="cand-name" class="text-2xl font-bold text-gray-900 leading-tight">
            <span class="skeleton inline-block w-48 h-7 align-middle rounded"></span>
          </h1>
          <p id="cand-role" class="text-sm text-gray-500 mt-0.5 hidden"></p>
          <div id="cand-contact" class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 mt-2">
            <span class="skeleton inline-block w-40 h-4 rounded"></span>
          </div>
        </div>
      </div>

      <!-- RIGHT: decision block -->
      <div class="flex items-center gap-5 lg:gap-6 lg:ps-6 lg:border-s lg:border-gray-100 shrink-0">
        <div class="text-center">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Recommendation</p>
          <span id="cand-reco" class="badge badge-gray text-sm px-3 py-1">…</span>
        </div>
        <div id="cand-score-ring" class="relative w-[104px] h-[104px] shrink-0" title="Overall AI score">
          <div class="skeleton w-full h-full rounded-full"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ TABS ============ -->
  <div id="cand-tabs" class="border-b border-gray-200 mb-6 overflow-x-auto">
    <nav class="flex items-center gap-6 min-w-max" role="tablist" aria-label="Candidate sections">
      <button type="button" data-tab="overview"    class="cand-tab relative -mb-px py-3 text-sm whitespace-nowrap transition border-b-2 border-violet-600 text-violet-600 font-semibold">AI Analysis</button>
      <button type="button" data-tab="interview"   class="cand-tab relative -mb-px py-3 text-sm whitespace-nowrap transition border-b-2 border-transparent text-gray-500 hover:text-gray-700">Interview &amp; Skills</button>
      <button type="button" data-tab="personality" class="cand-tab relative -mb-px py-3 text-sm whitespace-nowrap transition border-b-2 border-transparent text-gray-500 hover:text-gray-700">Personality</button>
      <button type="button" data-tab="redflags"    class="cand-tab relative -mb-px py-3 text-sm whitespace-nowrap transition border-b-2 border-transparent text-gray-500 hover:text-gray-700">Red Flags <span id="tab-count-redflags" class="ms-1 align-middle hidden"></span></button>
      <button type="button" data-tab="timeline"    class="cand-tab relative -mb-px py-3 text-sm whitespace-nowrap transition border-b-2 border-transparent text-gray-500 hover:text-gray-700">Timeline <span id="tab-count-timeline" class="ms-1 align-middle hidden"></span></button>
      <button type="button" data-tab="actions"     class="cand-tab relative -mb-px py-3 text-sm whitespace-nowrap transition border-b-2 border-transparent text-gray-500 hover:text-gray-700">Actions</button>
    </nav>
  </div>

  <!-- Global loading skeleton (hidden once profile resolves) -->
  <div id="cand-loading">
    <div class="grid sm:grid-cols-3 gap-4 mb-6">
      <div class="skeleton h-24 rounded-2xl"></div>
      <div class="skeleton h-24 rounded-2xl"></div>
      <div class="skeleton h-24 rounded-2xl"></div>
    </div>
    <div class="grid lg:grid-cols-2 gap-6">
      <div class="skeleton h-56 rounded-2xl"></div>
      <div class="skeleton h-56 rounded-2xl"></div>
    </div>
  </div>

  <!-- Fatal error card (replaces panels) -->
  <div id="cand-error" class="hidden card p-12 text-center">
    <div class="mx-auto w-14 h-14 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mb-4">
      <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0L3.16 16.25A2 2 0 005 19z"/></svg>
    </div>
    <p class="text-gray-900 font-semibold text-lg">Could not load this candidate.</p>
    <p id="cand-error-msg" class="text-gray-500 text-sm mt-1">Please try again in a moment.</p>
    <a href="/candidates" class="btn-ghost mt-5 inline-flex">Back to Candidates</a>
  </div>

  <!-- Panels host (filled once profile resolves) -->
  <div id="cand-panels" class="hidden">
    <section data-panel="overview"></section>
    <section data-panel="interview" class="hidden"></section>
    <section data-panel="personality" class="hidden"></section>
    <section data-panel="redflags" class="hidden"></section>
    <section data-panel="timeline" class="hidden"></section>
    <section data-panel="actions" class="hidden"></section>
  </div>
</div>

<!-- ============ Schedule Human Interview modal ============ -->
<div id="schedule-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card overflow-hidden">
    <div class="gradient-brand text-white px-5 py-4 flex items-center gap-3">
      <span class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      </span>
      <div class="flex-1 min-w-0">
        <p class="font-semibold leading-tight">Schedule Human Interview</p>
        <p class="text-xs text-white/70">Book a live conversation with the team</p>
      </div>
      <button type="button" data-modal-close="schedule-modal" aria-label="Close" class="w-8 h-8 rounded-full hover:bg-white/15 flex items-center justify-center transition">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="schedule-form" class="p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Date &amp; time</label>
        <input id="schedule-when" name="scheduled_at" type="datetime-local" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500"/>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Interview type</label>
        <select id="schedule-type" name="round" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          <option value="screening">Phone screening</option>
          <option value="technical">Technical interview</option>
          <option value="cultural">Cultural / values fit</option>
          <option value="panel">Panel interview</option>
          <option value="final">Final round</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
        <textarea id="schedule-notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Logistics, interviewers, focus areas…"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn-ghost" data-modal-close="schedule-modal">Cancel</button>
        <button type="submit" class="btn-primary">Schedule</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Make Offer modal ============ -->
<div id="offer-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card overflow-hidden">
    <div class="gradient-brand text-white px-5 py-4 flex items-center gap-3">
      <span class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </span>
      <div class="flex-1 min-w-0">
        <p class="font-semibold leading-tight">Make an Offer</p>
        <p class="text-xs text-white/70">Extend a formal offer to this candidate</p>
      </div>
      <button type="button" data-modal-close="offer-modal" aria-label="Close" class="w-8 h-8 rounded-full hover:bg-white/15 flex items-center justify-center transition">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="offer-form" class="p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Role / job title</label>
        <select id="offer-job" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500"></select>
        <input id="offer-job-other" type="text" class="hidden mt-2 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Enter a job title"/>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Salary</label>
          <input id="offer-salary" type="number" min="0" step="any" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="e.g. 120000"/>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
          <select id="offer-currency" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
            <option>USD</option><option>EUR</option><option>GBP</option><option>SAR</option><option>AED</option><option>EGP</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Start date</label>
        <input id="offer-start" type="date" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500"/>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
        <textarea id="offer-notes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Benefits, equity, conditions…"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn-ghost" data-modal-close="offer-modal">Cancel</button>
        <button type="submit" class="btn-accent">Send Offer</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Add to Talent Pool modal ============ -->
<div id="pool-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card overflow-hidden">
    <div class="gradient-brand text-white px-5 py-4 flex items-center gap-3">
      <span class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 011.04 0l2.12 5.11a.56.56 0 00.48.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.38a.56.56 0 01-.84.61l-4.72-2.88a.56.56 0 00-.59 0l-4.72 2.88a.56.56 0 01-.84-.61l1.28-5.38a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.48-.35L11.48 3.5z"/></svg>
      </span>
      <div class="flex-1 min-w-0">
        <p class="font-semibold leading-tight">Add to Talent Pool</p>
        <p class="text-xs text-white/70">Save this candidate for future roles</p>
      </div>
      <button type="button" data-modal-close="pool-modal" aria-label="Close" class="w-8 h-8 rounded-full hover:bg-white/15 flex items-center justify-center transition">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <form id="pool-form" class="p-5 space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Choose a pool</label>
        <select id="pool-select" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm bg-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500">
          <option value="" disabled selected>Loading pools…</option>
        </select>
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" class="btn-ghost" data-modal-close="pool-modal">Cancel</button>
        <button type="submit" id="pool-submit" class="btn-primary">Add to Pool</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';
  var AR = window.AR;
  var CAND_ID = <?= $candidateId ?>;
  var $ = function (id) { return document.getElementById(id); };
  var P = null;          // resolved profile payload
  var POOLS = null;      // lazily-cached talent pools

  /* ---------------- helpers ---------------- */
  function num(v, d) { var n = Number(v); return isNaN(n) ? (d == null ? 0 : d) : n; }
  function clamp(v) { return Math.max(0, Math.min(100, num(v))); }
  function pretty(s) {
    if (s == null || s === '') return '';
    return String(s).replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim()
      .replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }
  function fmtDate(x) {
    if (!x) return '';
    var d = new Date(String(x).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }
  function fmtDateTime(x) {
    if (!x) return '';
    var d = new Date(String(x).replace(' ', 'T'));
    if (isNaN(d.getTime())) return '';
    return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
  }
  function ts(x) { var d = new Date(String(x || '').replace(' ', 'T')); return isNaN(d.getTime()) ? 0 : d.getTime(); }
  function scoreHex(n) { return n >= 75 ? '#16a34a' : (n >= 50 ? '#d97706' : '#dc2626'); }

  // strengths/weaknesses may be array | JSON string | delimited text
  function normalizeList(v) {
    if (Array.isArray(v)) return v.map(itemText).filter(Boolean);
    if (v == null) return [];
    if (typeof v === 'object') return Object.values(v).map(itemText).filter(Boolean);
    var s = String(v).trim();
    if (!s) return [];
    if (s.charAt(0) === '[' || s.charAt(0) === '{') {
      try { var p = JSON.parse(s); if (Array.isArray(p)) return p.map(itemText).filter(Boolean); } catch (e) {}
    }
    return s.split(/\r?\n|•|·|;|,/).map(function (x) { return x.replace(/^[\s\-*]+/, '').trim(); }).filter(Boolean);
  }
  function itemText(i) {
    if (i == null) return '';
    if (typeof i === 'string') return i.trim();
    if (typeof i === 'object') return String(i.text || i.description || i.title || i.name || i.value || '').trim();
    return String(i);
  }

  function scoreBar(pct, color) {
    pct = clamp(pct);
    var style = color ? (';background:' + color) : '';
    return '<div class="score-bar"><span style="width:' + pct + '%' + style + '"></span></div>';
  }
  function emptyState(icon, title, sub, tone) {
    tone = tone || 'gray';
    var ring = tone === 'green' ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50';
    var ic = tone === 'green' ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400';
    return '<div class="rounded-2xl border ' + ring + ' p-8 text-center">' +
      '<span class="mx-auto mb-3 w-12 h-12 rounded-full ' + ic + ' flex items-center justify-center">' +
        '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">' + icon + '</svg></span>' +
      '<p class="font-semibold text-gray-800 text-sm">' + AR.esc(title) + '</p>' +
      (sub ? '<p class="text-xs text-gray-500 mt-1">' + AR.esc(sub) + '</p>' : '') +
    '</div>';
  }
  function cardWrap(title, iconPath, inner, accent) {
    accent = accent || 'text-violet-600';
    return '<div class="card p-6">' +
      '<h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">' +
        '<svg class="w-5 h-5 ' + accent + '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="' + iconPath + '"/></svg>' +
        AR.esc(title) + '</h3>' + inner + '</div>';
  }

  /* ---------------- resolve nested data robustly ---------------- */
  function getSkills(p) {
    var a = (p.skill_scores && p.skill_scores.length) ? p.skill_scores : (p.evaluation && p.evaluation.skill_scores);
    return Array.isArray(a) ? a : [];
  }
  function getPersonality(p) { return p.personality_analysis || (p.evaluation && p.evaluation.personality) || null; }
  function getFlags(p) {
    var a = (p.red_flags && p.red_flags.length) ? p.red_flags : (p.evaluation && p.evaluation.red_flags);
    return Array.isArray(a) ? a : [];
  }

  /* =========================================================
     HEADER
     ========================================================= */
  function renderHeader(p) {
    var c = p.candidate || {};
    var ev = p.evaluation || null;
    var name = ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || 'Candidate';
    var inits = ((c.first_name || ' ')[0] + (c.last_name || ' ')[0]).trim().toUpperCase() || (name[0] || '?').toUpperCase();

    var initEl = $('cand-initials'); if (initEl) initEl.textContent = inits;
    var nameEl = $('cand-name'); if (nameEl) nameEl.textContent = name;

    // role / headline line
    var roleEl = $('cand-role');
    var roleBits = [];
    if (c.headline) roleBits.push(c.headline);
    else if (c.current_title) roleBits.push(c.current_title);
    if (c.location) roleBits.push(c.location);
    if (roleEl) {
      if (roleBits.length) { roleEl.textContent = roleBits.join(' · '); roleEl.classList.remove('hidden'); }
      else roleEl.classList.add('hidden');
    }

    // contact chips
    var chips = [];
    if (c.email) chips.push('<a href="mailto:' + AR.esc(c.email) + '" class="inline-flex items-center gap-1.5 hover:text-violet-600 transition">' +
      '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>' +
      AR.esc(c.email) + '</a>');
    if (c.phone) chips.push('<span class="inline-flex items-center gap-1.5">' +
      '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.37a1.125 1.125 0 00-.852-1.091l-4.42-1.105a1.125 1.125 0 00-1.173.417l-.97 1.293a.75.75 0 01-.92.266 12.04 12.04 0 01-5.66-5.66.75.75 0 01.266-.92l1.293-.97a1.125 1.125 0 00.417-1.173L6.629 3.602A1.125 1.125 0 005.538 2.75H4.17A2.25 2.25 0 002.25 4.999z"/></svg>' +
      AR.esc(c.phone) + '</span>');
    if (c.linkedin_url) chips.push('<a href="' + AR.esc(c.linkedin_url) + '" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 hover:text-violet-600 transition">' +
      '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14zM8.34 17V10.5H6.27V17h2.07zM7.3 9.6a1.2 1.2 0 100-2.4 1.2 1.2 0 000 2.4zM18 17v-3.57c0-1.91-1.02-2.8-2.38-2.8-1.1 0-1.59.6-1.86 1.03v-.88h-2.07V17h2.07v-3.6c0-.19.01-.38.07-.52.15-.38.5-.77 1.08-.77.76 0 1.07.58 1.07 1.43V17H18z"/></svg>' +
      'LinkedIn</a>');
    var contactEl = $('cand-contact');
    if (contactEl) contactEl.innerHTML = chips.length ? chips.join('') : '<span class="text-gray-400">No contact details on file</span>';

    // recommendation badge
    var recoEl = $('cand-reco');
    if (recoEl) {
      var reco = ev && ev.recommendation ? String(ev.recommendation).toLowerCase() : null;
      var label = { hire: 'Hire', invite: 'Hire', maybe: 'Maybe', reject: 'Reject' };
      recoEl.className = 'badge ' + (reco ? AR.recoBadge(reco) : 'badge-gray') + ' text-sm px-3 py-1';
      recoEl.textContent = reco ? (label[reco] || pretty(reco)) : 'Not evaluated';
    }

    // score ring
    renderScoreRing(ev && ev.overall_score != null ? Math.round(num(ev.overall_score)) : null);
  }

  function renderScoreRing(score) {
    var box = $('cand-score-ring');
    if (!box) return;
    var has = score != null && !isNaN(score);
    var R = 44, C = 2 * Math.PI * R, size = 104, cx = size / 2;
    var pct = has ? clamp(score) : 0;
    var off = C * (1 - pct / 100);
    var gid = 'ringgrad';
    var prog = has
      ? '<circle cx="' + cx + '" cy="' + cx + '" r="' + R + '" fill="none" stroke="url(#' + gid + ')" stroke-width="9" stroke-linecap="round" ' +
        'stroke-dasharray="' + C.toFixed(2) + '" stroke-dashoffset="' + off.toFixed(2) + '" transform="rotate(-90 ' + cx + ' ' + cx + ')"/>'
      : '';
    var centerNum = has ? String(score) : '—';
    var centerSub = has ? '/100' : 'Not evaluated';
    box.innerHTML =
      '<svg viewBox="0 0 ' + size + ' ' + size + '" class="w-full h-full">' +
        '<defs><linearGradient id="' + gid + '" x1="0%" y1="0%" x2="100%" y2="100%">' +
          '<stop offset="0%" stop-color="#7C3AED"/><stop offset="100%" stop-color="#FBBF24"/></linearGradient></defs>' +
        '<circle cx="' + cx + '" cy="' + cx + '" r="' + R + '" fill="none" stroke="#e5e7eb" stroke-width="9"/>' +
        prog +
      '</svg>' +
      '<div class="absolute inset-0 flex flex-col items-center justify-center leading-none">' +
        '<span class="text-2xl font-extrabold ' + (has ? 'text-gray-900' : 'text-gray-400') + '">' + centerNum + '</span>' +
        '<span class="text-[10px] font-medium ' + (has ? 'text-gray-400' : 'text-gray-400') + '">' + centerSub + '</span>' +
      '</div>';
  }

  /* =========================================================
     TAB 1 — OVERVIEW (AI CV Analysis)
     ========================================================= */
  function renderOverview(p) {
    var host = document.querySelector('[data-panel="overview"]');
    if (!host) return;
    var ev = p.evaluation || {};
    var overall = ev.overall_score != null ? Math.round(num(ev.overall_score)) : null;
    var expRaw = ev.experience_relevance;
    var exp = expRaw != null ? clamp(expRaw > 0 && expRaw <= 10 ? expRaw * 10 : expRaw) : null;
    var reco = ev.recommendation ? String(ev.recommendation).toLowerCase() : null;
    var recoLabel = { hire: 'Hire', invite: 'Hire', maybe: 'Maybe', reject: 'Reject' };

    // stat tiles
    var tiles = '<div class="grid sm:grid-cols-3 gap-4 mb-6">';
    // overall
    tiles += '<div class="card p-5">' +
      '<p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Overall Match</p>' +
      '<div class="flex items-end gap-1 mt-1"><span class="text-3xl font-bold" style="color:' + (overall != null ? scoreHex(overall) : '#9ca3af') + '">' + (overall != null ? overall : '—') + '</span>' +
        (overall != null ? '<span class="text-gray-400 text-sm mb-1">/100</span>' : '') + '</div>' +
      '<div class="mt-3">' + scoreBar(overall != null ? overall : 0, overall != null ? scoreHex(overall) : '#e5e7eb') + '</div></div>';
    // experience
    tiles += '<div class="card p-5">' +
      '<p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Experience Relevance</p>' +
      '<div class="flex items-end gap-1 mt-1"><span class="text-3xl font-bold ' + (exp != null ? 'text-violet-700' : 'text-gray-400') + '">' + (exp != null ? Math.round(exp) : '—') + '</span>' +
        (exp != null ? '<span class="text-gray-400 text-sm mb-1">/100</span>' : '') + '</div>' +
      '<div class="mt-3">' + scoreBar(exp != null ? exp : 0) + '</div></div>';
    // recommendation
    tiles += '<div class="card p-5">' +
      '<p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Recommendation</p>' +
      '<div class="mt-2"><span class="badge ' + (reco ? AR.recoBadge(reco) : 'badge-gray') + ' text-sm px-3 py-1">' + AR.esc(reco ? (recoLabel[reco] || pretty(reco)) : 'Not evaluated') + '</span></div>' +
      '<p class="text-xs text-gray-400 mt-3">AI hiring signal based on CV &amp; interview</p></div>';
    tiles += '</div>';

    // strengths / weaknesses
    var strengths = normalizeList(ev.strengths);
    var weaknesses = normalizeList(ev.weaknesses);
    var cols = '<div class="grid lg:grid-cols-2 gap-6 mb-6">';
    cols += listCard('Strengths', strengths, 'green',
      'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
      'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'No strengths listed.');
    cols += listCard('Development Areas', weaknesses, 'amber',
      'M9 19v-6a2 2 0 00-2-2H5',
      'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z', 'No weaknesses listed.');
    cols += '</div>';

    // summary
    var summary = ev.summary ? String(ev.summary) : '';
    var sumCard = cardWrap('AI Summary',
      'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      (summary
        ? '<p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">' + AR.esc(summary) + '</p>'
        : '<p class="text-sm text-gray-400">No AI summary available yet.</p>'));

    host.innerHTML = tiles + cols + sumCard;
  }

  function listCard(title, items, tone, iconPath, emptyIcon, emptyMsg) {
    var dot = tone === 'green' ? 'text-green-600' : 'text-amber-600';
    var inner;
    if (!items.length) {
      inner = '<p class="text-sm text-gray-400">' + AR.esc(emptyMsg) + '</p>';
    } else {
      inner = '<ul class="space-y-3">' + items.map(function (t) {
        return '<li class="flex gap-3 text-sm text-gray-700">' +
          '<svg class="w-5 h-5 ' + dot + ' shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="' + iconPath + '"/></svg>' +
          '<span>' + AR.esc(t) + '</span></li>';
      }).join('') + '</ul>';
    }
    var headIcon = tone === 'green'
      ? 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
      : 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z';
    return cardWrap(title, headIcon, inner, dot);
  }

  /* =========================================================
     TAB 2 — INTERVIEW & SKILLS (radar + bars)
     ========================================================= */
  function renderInterview(p) {
    var host = document.querySelector('[data-panel="interview"]');
    if (!host) return;
    var interviews = Array.isArray(p.interviews) ? p.interviews.slice() : [];
    var skills = getSkills(p);

    // most recent interview (by completed_at)
    interviews.sort(function (a, b) { return ts(b && b.completed_at) - ts(a && a.completed_at); });
    var recent = interviews.length ? interviews[0] : null;

    var html = '';

    // meta strip
    if (recent) {
      var sc = recent.overall_score != null ? Math.round(num(recent.overall_score)) : null;
      var when = fmtDateTime(recent.completed_at);
      html += '<div class="card p-5 mb-6"><div class="flex flex-wrap items-center gap-3">' +
        '<span class="badge badge-violet">' + AR.esc(pretty(recent.type) || 'Interview') + '</span>' +
        (recent.status ? '<span class="badge badge-gray">' + AR.esc(pretty(recent.status)) + '</span>' : '') +
        (sc != null ? '<span class="badge ' + AR.scoreColor(sc) + '">Score ' + sc + '</span>' : '') +
        (when ? '<span class="text-xs text-gray-400 inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Completed ' + AR.esc(when) + '</span>' : '') +
      '</div></div>';
    }

    // transcript (only if real messages exist — never fabricate)
    var msgs = (recent && (recent.messages || recent.transcript)) || p.messages || p.transcript;
    if (Array.isArray(msgs) && msgs.length) {
      html += cardWrap('Interview Transcript',
        'M7 8h10M7 12h6m-6 8l-2-2H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-7l-3 3z',
        '<div class="space-y-4 max-h-96 overflow-y-auto pe-1">' + msgs.map(function (m) {
          var isAi = (m.role === 'ai' || m.role === 'assistant' || m.role === 'interviewer');
          var body = AR.esc(m.content || m.text || '');
          return '<div class="flex gap-3' + (isAi ? '' : ' flex-row-reverse') + '">' +
            '<div class="w-8 h-8 rounded-full ' + (isAi ? 'gradient-brand text-white' : 'bg-amber-400 text-gray-900') + ' flex items-center justify-center text-xs font-bold shrink-0">' + (isAi ? 'AI' : 'C') + '</div>' +
            '<div class="' + (isAi ? 'bg-gray-100 text-gray-800' : 'bg-violet-600 text-white') + ' rounded-2xl px-4 py-2.5 max-w-[80%]"><p class="text-sm whitespace-pre-wrap">' + body + '</p></div></div>';
        }).join('') + '</div>') + '<div class="h-6"></div>';
    } else {
      html += cardWrap('Interview Transcript',
        'M7 8h10M7 12h6m-6 8l-2-2H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-7l-3 3z',
        emptyState('<path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-4 4v-4z"/>',
          'Transcript not available.', 'No interview conversation has been recorded for this candidate yet.')) + '<div class="h-6"></div>';
    }

    // skills: radar + bars
    var skillsInner;
    if (skills.length < 3) {
      var note = skills.length
        ? '<p class="text-xs text-gray-400 mb-4">A radar chart needs at least 3 skills — showing scores as bars.</p>'
        : '';
      skillsInner = skills.length
        ? note + skillBars(skills)
        : emptyState('<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>',
            'No skill scores yet.', 'Skill ratings will appear here once the candidate is assessed.');
    } else {
      skillsInner = '<div class="grid lg:grid-cols-2 gap-6 items-center">' +
        '<div class="flex justify-center"><canvas id="skills-radar" width="420" height="420" class="max-w-full"></canvas></div>' +
        '<div class="space-y-4">' + skillBars(skills) + '</div>' +
      '</div>';
    }
    html += cardWrap('Skill Assessment',
      'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
      skillsInner);

    host.innerHTML = html;

    if (skills.length >= 3) drawRadar(skills);
  }

  function skillScale(score) {
    // normalize a skill score onto 0..100 (supports 0..10 or 0..100 inputs)
    var n = num(score);
    return clamp(n > 0 && n <= 10 ? n * 10 : n);
  }
  function skillBars(skills) {
    return skills.map(function (s) {
      var pct = skillScale(s.score);
      var hex = scoreHex(pct);
      var titleAttr = s.notes ? ' title="' + AR.esc(s.notes) + '"' : '';
      return '<div' + titleAttr + '>' +
        '<div class="flex items-center justify-between mb-1">' +
          '<span class="text-sm font-medium text-gray-800 truncate pe-2">' + AR.esc(s.skill_name || 'Skill') + '</span>' +
          '<span class="text-sm font-semibold shrink-0" style="color:' + hex + '">' + Math.round(pct) + '</span>' +
        '</div>' + scoreBar(pct, hex) +
        (s.notes ? '<p class="text-xs text-gray-400 mt-1 line-clamp-2">' + AR.esc(s.notes) + '</p>' : '') +
      '</div>';
    }).join('');
  }

  // Real radar chart on canvas (3..11 skills, generic)
  function drawRadar(skills) {
    var canvas = $('skills-radar');
    if (!canvas || !canvas.getContext) return;
    var ctx = canvas.getContext('2d');
    var W = canvas.width, H = canvas.height;
    ctx.clearRect(0, 0, W, H);
    var cx = W / 2, cy = H / 2;
    var R = Math.min(W, H) / 2 - 64;        // leave room for labels
    var n = Math.min(skills.length, 11);
    var rings = 4;
    var TWO_PI = Math.PI * 2;
    var start = -Math.PI / 2;               // 12 o'clock

    function pt(angle, radius) { return [cx + Math.cos(angle) * radius, cy + Math.sin(angle) * radius]; }

    // grid rings (concentric polygons)
    ctx.lineWidth = 1;
    for (var r = 1; r <= rings; r++) {
      var rr = R * (r / rings);
      ctx.beginPath();
      for (var i = 0; i < n; i++) {
        var a = start + TWO_PI * (i / n);
        var p = pt(a, rr);
        if (i === 0) ctx.moveTo(p[0], p[1]); else ctx.lineTo(p[0], p[1]);
      }
      ctx.closePath();
      ctx.strokeStyle = r === rings ? '#d1d5db' : '#e5e7eb';
      ctx.stroke();
    }

    // spokes + labels
    ctx.fillStyle = '#6b7280';
    ctx.font = '600 11px Inter, system-ui, sans-serif';
    ctx.textBaseline = 'middle';
    for (var s = 0; s < n; s++) {
      var ang = start + TWO_PI * (s / n);
      var edge = pt(ang, R);
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.lineTo(edge[0], edge[1]);
      ctx.strokeStyle = '#eef0f3';
      ctx.stroke();

      // label
      var lp = pt(ang, R + 22);
      var cos = Math.cos(ang);
      ctx.textAlign = Math.abs(cos) < 0.25 ? 'center' : (cos > 0 ? 'left' : 'right');
      var label = String(skills[s].skill_name || 'Skill');
      if (label.length > 16) label = label.slice(0, 15) + '…';
      ctx.fillText(label, lp[0], lp[1]);
    }

    // data polygon
    ctx.beginPath();
    for (var d = 0; d < n; d++) {
      var angle = start + TWO_PI * (d / n);
      var val = skillScale(skills[d].score) / 100;
      var dp = pt(angle, R * val);
      if (d === 0) ctx.moveTo(dp[0], dp[1]); else ctx.lineTo(dp[0], dp[1]);
    }
    ctx.closePath();
    ctx.fillStyle = 'rgba(124,58,237,0.25)';
    ctx.fill();
    ctx.lineWidth = 2;
    ctx.strokeStyle = '#7C3AED';
    ctx.stroke();

    // vertices
    ctx.fillStyle = '#7C3AED';
    for (var v = 0; v < n; v++) {
      var av = start + TWO_PI * (v / n);
      var vv = skillScale(skills[v].score) / 100;
      var vp = pt(av, R * vv);
      ctx.beginPath();
      ctx.arc(vp[0], vp[1], 3, 0, TWO_PI);
      ctx.fill();
    }
  }

  /* =========================================================
     TAB 3 — PERSONALITY (DISC quadrant + Big Five)
     ========================================================= */
  function renderPersonality(p) {
    var host = document.querySelector('[data-panel="personality"]');
    if (!host) return;
    var pers = getPersonality(p);

    if (!pers) {
      host.innerHTML = cardWrap('Personality Profile',
        'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z',
        emptyState('<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>',
          'Personality analysis not available.', 'Run an AI interview to generate DISC and Big-Five insights.'));
      return;
    }

    // DISC 2x2 quadrant
    var disc = [
      { k: 'D', name: 'Dominance', v: pers.disc_d, c: '#dc2626', bg: 'bg-red-50' },
      { k: 'I', name: 'Influence', v: pers.disc_i, c: '#f59e0b', bg: 'bg-amber-50' },
      { k: 'S', name: 'Steadiness', v: pers.disc_s, c: '#16a34a', bg: 'bg-green-50' },
      { k: 'C', name: 'Conscientiousness', v: pers.disc_c, c: '#2563eb', bg: 'bg-blue-50' }
    ];
    var discInner = '<div class="grid grid-cols-2 gap-3">' + disc.map(function (x) {
      var hasV = x.v != null;
      var pct = clamp(x.v);
      return '<div class="' + x.bg + ' rounded-xl p-4">' +
        '<div class="flex items-center justify-between">' +
          '<span class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-extrabold" style="background:' + x.c + '">' + x.k + '</span>' +
          '<span class="text-xl font-bold" style="color:' + (hasV ? x.c : '#9ca3af') + '">' + (hasV ? Math.round(pct) : '—') + '</span>' +
        '</div>' +
        '<div class="text-xs font-medium text-gray-600 mt-2">' + AR.esc(x.name) + '</div>' +
        '<div class="score-bar mt-2 bg-white/70"><span style="width:' + (hasV ? pct : 0) + '%;background:' + x.c + '"></span></div>' +
      '</div>';
    }).join('') + '</div>';

    // Big Five bars
    var traits = [
      { name: 'Openness', v: pers.big5_openness },
      { name: 'Conscientiousness', v: pers.big5_conscientiousness },
      { name: 'Extraversion', v: pers.big5_extraversion },
      { name: 'Agreeableness', v: pers.big5_agreeableness },
      { name: 'Neuroticism', v: pers.big5_neuroticism }
    ];
    var bigInner = '<div class="space-y-4">' + traits.map(function (t) {
      var hasV = t.v != null;
      var pct = clamp(t.v);
      return '<div>' +
        '<div class="flex items-center justify-between mb-1">' +
          '<span class="text-sm font-medium text-gray-800">' + AR.esc(t.name) + '</span>' +
          '<span class="text-sm font-semibold ' + (hasV ? 'text-violet-700' : 'text-gray-400') + '">' + (hasV ? Math.round(pct) : '—') + '</span>' +
        '</div>' + scoreBar(hasV ? pct : 0) +
      '</div>';
    }).join('') + '</div>';

    host.innerHTML = '<div class="grid lg:grid-cols-2 gap-6">' +
      cardWrap('DISC Profile', 'M17.66 18.66A8 8 0 116.34 7.34S7 9 9 10c0-2 .5-5 2.99-7C14 5 16.09 5.78 17.66 7.34A7.97 7.97 0 0120 13a7.97 7.97 0 01-2.34 5.66z', discInner) +
      cardWrap('Big Five Personality', 'M9.66 17h4.68M12 3v1m6.36 1.64l-.7.7M21 12h-1M4 12H3m3.34-5.66l-.7-.7m2.83 9.9a5 5 0 117.07 0l-.55.55A3.37 3.37 0 0014 18.47V19a2 2 0 11-4 0v-.53c0-.9-.36-1.76-.99-2.39l-.55-.54z', bigInner) +
    '</div>';
  }

  /* =========================================================
     TAB 4 — RED FLAGS
     ========================================================= */
  function renderRedFlags(p) {
    var host = document.querySelector('[data-panel="redflags"]');
    if (!host) return;
    var flags = getFlags(p).slice();
    var order = { high: 0, medium: 1, low: 2 };
    flags.sort(function (a, b) {
      var av = order[(a && a.severity || 'low').toLowerCase()]; if (av == null) av = 3;
      var bv = order[(b && b.severity || 'low').toLowerCase()]; if (bv == null) bv = 3;
      return av - bv;
    });

    var badge = $('tab-count-redflags');
    if (badge) {
      if (flags.length) { badge.className = 'ms-1 align-middle badge badge-red'; badge.textContent = flags.length; badge.classList.remove('hidden'); }
      else badge.classList.add('hidden');
    }

    if (!flags.length) {
      host.innerHTML = '<div class="card p-6">' +
        '<div class="rounded-2xl border border-green-200 bg-green-50 p-8 text-center">' +
          '<span class="mx-auto mb-3 w-14 h-14 rounded-full bg-green-100 text-green-600 flex items-center justify-center">' +
            '<svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>' +
          '<p class="font-semibold text-green-800">No red flags 🎉</p>' +
          '<p class="text-sm text-green-700 mt-1">This candidate raised no concerns during screening.</p>' +
        '</div></div>';
      return;
    }

    var SEV = {
      high:   { cls: 'badge-red',    ring: 'border-red-200 bg-red-50',       dot: '#dc2626' },
      medium: { cls: 'badge-yellow', ring: 'border-orange-200 bg-orange-50', dot: '#f97316' },
      low:    { cls: 'badge-gray',   ring: 'border-gray-200 bg-gray-50',     dot: '#6b7280' }
    };
    var rows = flags.map(function (f) {
      var sevKey = (f.severity || 'low').toLowerCase();
      var sev = SEV[sevKey] || SEV.low;
      return '<div class="card p-5 hover:shadow-md transition">' +
        '<div class="flex items-start gap-3">' +
          '<span class="mt-1 w-2.5 h-2.5 rounded-full shrink-0" style="background:' + sev.dot + '"></span>' +
          '<div class="min-w-0 flex-1">' +
            '<div class="flex items-center justify-between gap-3 mb-1">' +
              '<span class="font-semibold text-gray-900">' + AR.esc(pretty(f.flag_type) || 'Concern') + '</span>' +
              '<span class="badge ' + sev.cls + ' capitalize shrink-0">' + AR.esc(sevKey) + '</span>' +
            '</div>' +
            '<p class="text-sm text-gray-600">' + AR.esc(f.description || '') + '</p>' +
          '</div>' +
        '</div></div>';
    }).join('');

    host.innerHTML = '<div class="mb-4 text-sm text-gray-500">' + flags.length + ' concern' + (flags.length === 1 ? '' : 's') + ' surfaced by the AI screening, ordered by severity.</div>' +
      '<div class="space-y-4">' + rows + '</div>';
  }

  /* =========================================================
     TAB 5 — TIMELINE (applications + interviews)
     ========================================================= */
  function renderTimeline(p) {
    var host = document.querySelector('[data-panel="timeline"]');
    if (!host) return;
    var apps = Array.isArray(p.applications) ? p.applications : [];
    var interviews = Array.isArray(p.interviews) ? p.interviews : [];
    var entries = [];

    apps.forEach(function (a) {
      entries.push({
        t: ts(a.applied_at),
        when: a.applied_at,
        title: 'Applied to ' + (a.job_title || 'a role'),
        detail: [a.pipeline_stage ? 'Stage: ' + pretty(a.pipeline_stage) : '', a.status ? pretty(a.status) : ''].filter(Boolean).join(' · '),
        tone: 'violet',
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
      });
    });
    interviews.forEach(function (iv) {
      var sc = iv.overall_score != null ? Math.round(num(iv.overall_score)) : null;
      entries.push({
        t: ts(iv.completed_at),
        when: iv.completed_at,
        title: (pretty(iv.type) || 'Interview') + ' interview' + (iv.status ? ' · ' + pretty(iv.status) : ''),
        detail: sc != null ? ('Overall score ' + sc + '/100') : '',
        tone: 'amber',
        icon: 'M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H9v1.5H7.5v1.5H6v1.5H2.25v-1.5l5.155-5.155a6 6 0 116.345-9.345z'
      });
    });

    var badge = $('tab-count-timeline');
    if (badge) {
      if (entries.length) { badge.className = 'ms-1 align-middle badge badge-gray'; badge.textContent = entries.length; badge.classList.remove('hidden'); }
      else badge.classList.add('hidden');
    }

    if (!entries.length) {
      host.innerHTML = cardWrap('Activity Timeline',
        'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        emptyState('<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
          'No activity yet.', 'Applications and interviews will appear here over time.'));
      return;
    }

    // oldest -> newest (natural top-to-bottom reading)
    entries.sort(function (a, b) { return a.t - b.t; });

    var toneMap = { violet: 'bg-violet-500', amber: 'bg-amber-400', gray: 'bg-gray-400' };
    var nodes = entries.map(function (e, idx) {
      var last = idx === entries.length - 1;
      var when = fmtDateTime(e.when) || fmtDate(e.when) || 'Date unknown';
      return '<li class="relative ps-10 pb-6">' +
        (!last ? '<span class="absolute start-[14px] top-5 bottom-0 w-px bg-gray-200"></span>' : '') +
        '<span class="absolute start-2 top-1 w-5 h-5 rounded-full ' + (toneMap[e.tone] || 'bg-gray-400') + ' ring-4 ring-white flex items-center justify-center">' +
          '<svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="' + e.icon + '"/></svg>' +
        '</span>' +
        '<div class="card p-4 hover:shadow-md transition">' +
          '<p class="text-sm font-semibold text-gray-900">' + AR.esc(e.title) + '</p>' +
          '<p class="text-xs text-gray-400 mt-0.5">' + AR.esc(when) + '</p>' +
          (e.detail ? '<p class="text-xs text-gray-500 mt-1.5">' + AR.esc(e.detail) + '</p>' : '') +
        '</div>' +
      '</li>';
    }).join('');

    host.innerHTML = cardWrap('Activity Timeline',
      'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
      '<ol class="relative">' + nodes + '</ol>');
  }

  /* =========================================================
     TAB 6 — ACTIONS
     ========================================================= */
  function renderActions(p) {
    var host = document.querySelector('[data-panel="actions"]');
    if (!host) return;
    function action(id, tone, iconPath, title, desc) {
      var bg = tone === 'accent' ? 'bg-amber-100 text-amber-600' : (tone === 'ghost' ? 'bg-gray-100 text-gray-600' : 'bg-violet-100 text-violet-600');
      return '<button type="button" id="' + id + '" class="card p-5 text-start hover:shadow-md hover:-translate-y-0.5 transition flex items-start gap-4 w-full">' +
        '<span class="w-12 h-12 shrink-0 rounded-xl ' + bg + ' flex items-center justify-center">' +
          '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="' + iconPath + '"/></svg></span>' +
        '<span class="min-w-0"><span class="block font-semibold text-gray-900">' + AR.esc(title) + '</span>' +
          '<span class="block text-sm text-gray-400 mt-0.5">' + AR.esc(desc) + '</span></span>' +
      '</button>';
    }
    host.innerHTML =
      '<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">' +
        action('act-schedule', 'primary', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
          'Schedule Human Interview', 'Book a live round with the hiring team.') +
        action('act-offer', 'accent', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
          'Make Offer', 'Extend a formal offer to this candidate.') +
        action('act-pool', 'ghost', 'M11.48 3.5a.56.56 0 011.04 0l2.12 5.11a.56.56 0 00.48.35l5.52.44c.5.04.7.66.32.99l-4.2 3.6a.56.56 0 00-.18.56l1.28 5.38a.56.56 0 01-.84.61l-4.72-2.88a.56.56 0 00-.59 0l-4.72 2.88a.56.56 0 01-.84-.61l1.28-5.38a.56.56 0 00-.18-.56l-4.2-3.6a.56.56 0 01.32-.99l5.52-.44a.56.56 0 00.48-.35L11.48 3.5z',
          'Add to Talent Pool', 'Save for future roles and pipelines.') +
      '</div>' +
      '<div class="card p-5 mt-5">' +
        '<p class="text-sm font-semibold text-gray-900 mb-3">Quick decisions</p>' +
        '<div class="flex flex-wrap gap-3">' +
          '<button type="button" id="act-advance" class="btn-primary"><svg class="w-4 h-4 rtl:-scale-x-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg> Advance Stage</button>' +
          '<button type="button" id="act-reject" class="btn-ghost !text-red-600 !border-red-200 hover:!bg-red-50"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg> Reject</button>' +
        '</div>' +
      '</div>';

    bindActions(p);
  }

  /* ---------------- action wiring ---------------- */
  function firstApplicationId(p) {
    var apps = Array.isArray(p.applications) ? p.applications : [];
    for (var i = 0; i < apps.length; i++) { if (apps[i] && apps[i].id != null) return apps[i].id; }
    return null;
  }

  function bindActions(p) {
    var appId = firstApplicationId(p);

    var sched = $('act-schedule'); if (sched) sched.addEventListener('click', function () { AR.Modal.open('schedule-modal'); });
    var offer = $('act-offer'); if (offer) offer.addEventListener('click', function () { AR.Modal.open('offer-modal'); });
    var pool = $('act-pool'); if (pool) pool.addEventListener('click', openPoolModal);

    // Schedule submit
    var sForm = $('schedule-form');
    if (sForm && !sForm.dataset.bound) {
      sForm.dataset.bound = '1';
      sForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = sForm.querySelector('button[type="submit"]');
        var when = ($('schedule-when') || {}).value;
        if (!when) { AR.Toast.error('Please choose a date and time.'); return; }
        if (btn) btn.disabled = true;
        var body = {
          candidate_id: CAND_ID,
          type: 'human',
          scheduled_at: when,
          round: ($('schedule-type') || {}).value || '',
          notes: ($('schedule-notes') || {}).value || ''
        };
        if (appId != null) body.application_id = appId;
        AR.Api.post('/interviews', body)
          .then(function () { AR.Toast.success('Interview scheduled.'); AR.Modal.close('schedule-modal'); sForm.reset(); })
          .catch(function (err) { AR.Toast.error((err && err.message) || 'Could not schedule the interview.'); })
          .finally(function () { if (btn) btn.disabled = false; });
      });
    }

    // Offer: populate job select
    var jobSel = $('offer-job');
    var other = $('offer-job-other');
    if (jobSel) {
      var apps = Array.isArray(p.applications) ? p.applications : [];
      var seen = {}, opts = '';
      apps.forEach(function (a) {
        var t = a && a.job_title;
        if (t && !seen[t]) { seen[t] = 1; opts += '<option value="' + AR.esc(t) + '">' + AR.esc(t) + '</option>'; }
      });
      opts += '<option value="__other__">Other (type a title)…</option>';
      jobSel.innerHTML = opts;
      jobSel.addEventListener('change', function () {
        if (other) other.classList.toggle('hidden', jobSel.value !== '__other__');
      });
    }

    var oForm = $('offer-form');
    if (oForm && !oForm.dataset.bound) {
      oForm.dataset.bound = '1';
      oForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = oForm.querySelector('button[type="submit"]');
        var salary = ($('offer-salary') || {}).value;
        if (!salary) { AR.Toast.error('Please enter a salary.'); return; }
        var titleVal = jobSel ? jobSel.value : '';
        if (titleVal === '__other__') titleVal = (other && other.value) || '';
        if (btn) btn.disabled = true;
        var body = {
          candidate_id: CAND_ID,
          job_title: titleVal,
          salary: Number(salary),
          currency: ($('offer-currency') || {}).value || 'USD',
          start_date: ($('offer-start') || {}).value || '',
          notes: ($('offer-notes') || {}).value || ''
        };
        // attach a job_id if the chosen title maps to an application's job
        var apps2 = Array.isArray(p.applications) ? p.applications : [];
        for (var i = 0; i < apps2.length; i++) {
          if (apps2[i] && apps2[i].job_title === titleVal && apps2[i].job_id != null) { body.job_id = apps2[i].job_id; break; }
        }
        AR.Api.post('/offers', body)
          .then(function () { AR.Toast.success('Offer sent.'); AR.Modal.close('offer-modal'); oForm.reset(); if (other) other.classList.add('hidden'); })
          .catch(function (err) { AR.Toast.error((err && err.message) || 'Could not send the offer.'); })
          .finally(function () { if (btn) btn.disabled = false; });
      });
    }

    // Pool submit
    var pForm = $('pool-form');
    if (pForm && !pForm.dataset.bound) {
      pForm.dataset.bound = '1';
      pForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var sel = $('pool-select');
        var btn = $('pool-submit');
        var poolId = sel ? sel.value : '';
        if (!poolId) { AR.Toast.error('Please choose a talent pool.'); return; }
        if (btn) btn.disabled = true;
        AR.Api.post('/talent-pools/' + encodeURIComponent(poolId) + '/candidates', { candidate_id: CAND_ID })
          .then(function () { AR.Toast.success('Added to talent pool.'); AR.Modal.close('pool-modal'); })
          .catch(function (err) { AR.Toast.error((err && err.message) || 'Could not add to pool.'); })
          .finally(function () { if (btn) btn.disabled = false; });
      });
    }

    // Quick: advance / reject
    var adv = $('act-advance');
    if (adv) adv.addEventListener('click', function () {
      if (!window.confirm('Advance this candidate to the next pipeline stage?')) return;
      adv.disabled = true;
      AR.Api.put('/candidates/' + encodeURIComponent(CAND_ID), { pipeline_stage: 'interview' })
        .then(function () { AR.Toast.success('Candidate advanced.'); })
        .catch(function (err) { AR.Toast.error((err && err.message) || 'Could not update stage.'); })
        .finally(function () { adv.disabled = false; });
    });
    var rej = $('act-reject');
    if (rej) rej.addEventListener('click', function () {
      if (!window.confirm('Reject this candidate? This marks them as rejected in the pipeline.')) return;
      rej.disabled = true;
      AR.Api.put('/candidates/' + encodeURIComponent(CAND_ID), { pipeline_stage: 'rejected' })
        .then(function () { AR.Toast.success('Candidate rejected.'); })
        .catch(function (err) { AR.Toast.error((err && err.message) || 'Could not update candidate.'); })
        .finally(function () { rej.disabled = false; });
    });
  }

  function openPoolModal() {
    AR.Modal.open('pool-modal');
    var sel = $('pool-select');
    if (!sel) return;
    if (POOLS) { fillPools(sel, POOLS); return; }
    sel.innerHTML = '<option value="" disabled selected>Loading pools…</option>';
    AR.Api.get('/talent-pools')
      .then(function (pools) { POOLS = Array.isArray(pools) ? pools : []; fillPools(sel, POOLS); })
      .catch(function (err) {
        sel.innerHTML = '<option value="" disabled selected>Could not load pools</option>';
        AR.Toast.error((err && err.message) || 'Could not load talent pools.');
      });
  }
  function fillPools(sel, pools) {
    var submit = $('pool-submit');
    if (!pools.length) {
      sel.innerHTML = '<option value="" disabled selected>No pools yet</option>';
      if (submit) submit.disabled = true;
      return;
    }
    if (submit) submit.disabled = false;
    sel.innerHTML = pools.map(function (pl) {
      return '<option value="' + AR.esc(pl.id) + '">' + AR.esc(pl.name || ('Pool #' + pl.id)) + '</option>';
    }).join('');
  }

  /* ---------------- tabs ---------------- */
  function bindTabs() {
    var btns = document.querySelectorAll('.cand-tab');
    btns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-tab');
        btns.forEach(function (b) {
          var on = b === btn;
          b.classList.toggle('border-violet-600', on);
          b.classList.toggle('text-violet-600', on);
          b.classList.toggle('font-semibold', on);
          b.classList.toggle('border-transparent', !on);
          b.classList.toggle('text-gray-500', !on);
          b.classList.toggle('hover:text-gray-700', !on);
        });
        document.querySelectorAll('#cand-panels [data-panel]').forEach(function (sec) {
          sec.classList.toggle('hidden', sec.getAttribute('data-panel') !== key);
        });
      });
    });
  }

  /* ---------------- load ---------------- */
  function load() {
    AR.Api.get('/candidates/' + CAND_ID + '/profile')
      .then(function (data) {
        if (!data || !(data.candidate || data.evaluation || data.applications)) {
          throw new Error('This candidate profile is empty.');
        }
        P = data;
        renderHeader(P);
        renderOverview(P);
        renderInterview(P);
        renderPersonality(P);
        renderRedFlags(P);
        renderTimeline(P);
        renderActions(P);

        var loading = $('cand-loading'); if (loading) loading.classList.add('hidden');
        var panels = $('cand-panels'); if (panels) panels.classList.remove('hidden');
      })
      .catch(function (err) {
        var loading = $('cand-loading'); if (loading) loading.classList.add('hidden');
        var tabs = $('cand-tabs'); if (tabs) tabs.classList.add('hidden');
        var msg = $('cand-error-msg'); if (msg) msg.textContent = (err && err.message) || 'Please try again in a moment.';
        var errBox = $('cand-error'); if (errBox) errBox.classList.remove('hidden');
        // header skeletons -> graceful fallback
        var nameEl = $('cand-name'); if (nameEl) nameEl.textContent = 'Candidate';
        var initEl = $('cand-initials'); if (initEl) initEl.textContent = '?';
        var contactEl = $('cand-contact'); if (contactEl) contactEl.innerHTML = '<span class="text-gray-400">—</span>';
        var recoEl = $('cand-reco'); if (recoEl) { recoEl.className = 'badge badge-gray text-sm px-3 py-1'; recoEl.textContent = 'Not evaluated'; }
        renderScoreRing(null);
        AR.Toast.error('Could not load this candidate.');
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!AR || !CAND_ID) {
      var loading = $('cand-loading'); if (loading) loading.classList.add('hidden');
      var errBox = $('cand-error'); if (errBox) errBox.classList.remove('hidden');
      return;
    }
    bindTabs();
    load();
  });
})();
</script>
