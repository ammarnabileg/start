<?php
/**
 * Compare Candidates — side-by-side analysis of up to 4 candidate profiles.
 * Fragment rendered inside views/layouts/app.php. ids come from the query string in JS.
 */
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in">

  <!-- ============ Header ============ -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
        <span class="inline-flex w-9 h-9 rounded-xl gradient-brand text-white items-center justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>
        </span>
        Compare Candidates
      </h1>
      <p class="text-sm text-gray-500 mt-1">AI skills, recommendation and personality side-by-side.</p>
    </div>
    <a href="/candidates" class="btn-ghost self-start sm:self-auto inline-flex">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
      Back to Candidates
    </a>
  </div>

  <!-- Info banner (shown when fewer than 2 profiles load) -->
  <div id="cmp-note" class="hidden mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 flex items-start gap-2">
    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
    <span id="cmp-note-text"></span>
  </div>

  <!-- Loading skeleton -->
  <div id="cmp-loading" class="card p-12 flex flex-col items-center justify-center text-center">
    <svg class="w-9 h-9 text-violet-600 spin mb-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
    <p class="text-sm text-gray-500">Loading candidate profiles…</p>
  </div>

  <!-- Empty state (no ids in query) -->
  <div id="cmp-empty" class="hidden card p-12 text-center">
    <div class="mx-auto w-14 h-14 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center mb-4">
      <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>
    </div>
    <p class="text-gray-900 font-semibold">Nothing to compare yet</p>
    <p class="text-gray-500 text-sm mt-1">Select 2–4 candidates to compare.</p>
    <a href="/candidates" class="btn-primary mt-5 inline-flex">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
      Go to Candidates
    </a>
  </div>

  <!-- Comparison grid (table for clean row alignment) -->
  <div id="cmp-wrap" class="hidden card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="table-fixed w-full border-collapse text-sm">
        <thead id="cmp-head"></thead>
        <tbody id="cmp-body" class="divide-y divide-gray-100"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);

  const LABEL_W = 'w-44 sm:w-56';   // sticky left label column width

  // ---- Parse ids from query string --------------------------------------
  function parseIds() {
    const raw = new URLSearchParams(location.search).get('ids') || '';
    const seen = new Set();
    return raw.split(',')
      .map((s) => s.trim())
      .filter(Boolean)
      .filter((id) => (seen.has(id) ? false : (seen.add(id), true)))
      .slice(0, 4);
  }

  // ---- Normalizers / data-shape helpers ---------------------------------
  function num(v) {
    if (v == null || v === '') return null;
    const n = Number(v);
    return isNaN(n) ? null : n;
  }
  function clamp(n) { return Math.max(0, Math.min(100, n)); }

  function fullName(c) {
    if (!c) return 'Candidate';
    const n = [c.first_name, c.last_name].filter(Boolean).join(' ').trim();
    return n || c.email || c.name || 'Candidate';
  }
  function initials(c) {
    const a = (c && c.first_name ? c.first_name.trim() : '');
    const b = (c && c.last_name ? c.last_name.trim() : '');
    let s = ((a ? a[0] : '') + (b ? b[0] : '')).toUpperCase();
    if (!s && c) s = String(c.email || c.name || '?').trim().charAt(0).toUpperCase();
    return s || '?';
  }
  function pretty(v) {
    if (v == null || v === '') return '';
    return String(v).replace(/[_-]+/g, ' ').replace(/\b\w/g, (ch) => ch.toUpperCase());
  }

  // Read skills/personality/eval from BOTH top-level and nested-under-evaluation.
  function getSkills(p) {
    const s = (p && p.skill_scores) || (p && p.evaluation && p.evaluation.skill_scores) || [];
    return Array.isArray(s) ? s : [];
  }
  function getPersonality(p) {
    return (p && p.personality_analysis) || (p && p.evaluation && p.evaluation.personality) || null;
  }
  function getEval(p) {
    return (p && p.evaluation) || null;
  }

  // Build a fast skill_name -> score lookup for one candidate.
  function skillMap(p) {
    const m = {};
    getSkills(p).forEach((s) => {
      if (!s) return;
      const key = s.skill_name != null ? String(s.skill_name) : '';
      if (key) m[key] = num(s.score);
    });
    return m;
  }

  // Stable union of skill names: seed from the first candidate that has skills,
  // then append any names the others introduce (preserving first-seen order).
  function skillUnion(profiles) {
    const order = [];
    const seen = new Set();
    profiles.forEach((p) => {
      getSkills(p).forEach((s) => {
        const key = s && s.skill_name != null ? String(s.skill_name) : '';
        if (key && !seen.has(key)) { seen.add(key); order.push(key); }
      });
    });
    return order;
  }

  // ---- Small render helpers ---------------------------------------------
  function ringColorFor(score) {
    if (score == null) return '#E5E7EB';
    if (score >= 75) return '#16a34a';
    if (score >= 50) return '#F59E0B';
    return '#dc2626';
  }
  // A circular score ring rendered with conic-gradient (pure CSS, from data).
  function scoreRing(score) {
    if (score == null) {
      return '<div class="w-20 h-20 rounded-full flex items-center justify-center bg-gray-50 text-gray-400 ring-1 ring-gray-200">' +
               '<span class="text-lg font-bold">—</span></div>';
    }
    const v = clamp(Math.round(score));
    const col = ringColorFor(v);
    const txt = AR.scoreColor(v) === 'badge-green' ? 'text-green-700'
              : AR.scoreColor(v) === 'badge-yellow' ? 'text-amber-700' : 'text-red-700';
    return '<div class="w-20 h-20 rounded-full flex items-center justify-center" ' +
             'style="background: conic-gradient(' + col + ' ' + (v * 3.6) + 'deg, #EDE9FE ' + (v * 3.6) + 'deg);">' +
             '<div class="w-[60px] h-[60px] rounded-full bg-white flex flex-col items-center justify-center leading-none">' +
               '<span class="text-xl font-extrabold ' + txt + '">' + v + '</span>' +
               '<span class="text-[10px] text-gray-400 -mt-0.5">/100</span>' +
             '</div>' +
           '</div>';
  }

  function scoreBar(score) {
    if (score == null) return '<span class="text-gray-400">—</span>';
    const v = clamp(Math.round(score));
    return '<div class="flex items-center gap-2">' +
             '<div class="score-bar flex-1 min-w-[80px]"><span style="width:' + v + '%"></span></div>' +
             '<span class="text-xs font-semibold text-gray-700 w-9 text-end">' + v + '</span>' +
           '</div>';
  }

  // A single skill cell: tinted by value, ★ + ring when it is (one of) the row's best.
  function skillCell(score, isBest) {
    if (score == null) return '<span class="text-gray-300">—</span>';
    const v = Math.round(score);
    let tint;
    if (v >= 75) tint = 'bg-green-50 text-green-700';
    else if (v >= 50) tint = 'bg-amber-50 text-amber-700';
    else tint = 'bg-red-50 text-red-700';
    const best = isBest ? ' ring-2 ring-violet-400 font-bold' : '';
    const star = isBest ? '<svg class="w-3 h-3 text-violet-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.05 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.957a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.367 2.446a1 1 0 00-.364 1.118l1.287 3.957c.3.922-.755 1.688-1.54 1.118l-3.366-2.446a1 1 0 00-1.176 0l-3.366 2.446c-.784.57-1.838-.196-1.539-1.118l1.287-3.957a1 1 0 00-.364-1.118L2.354 9.39c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.286-3.957z"/></svg>' : '';
    return '<span class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs ' + tint + best + '">' +
             star + v +
           '</span>';
  }

  // Compact DISC: 4 tiny labeled horizontal bars from personality_analysis.
  function discBlock(pers) {
    if (!pers) return '<span class="text-gray-400 text-xs">—</span>';
    const rows = [
      ['D', num(pers.disc_d), '#7C3AED'],
      ['I', num(pers.disc_i), '#9333EA'],
      ['S', num(pers.disc_s), '#F59E0B'],
      ['C', num(pers.disc_c), '#0EA5E9']
    ];
    const any = rows.some((r) => r[1] != null);
    if (!any) return '<span class="text-gray-400 text-xs">—</span>';
    return '<div class="space-y-1.5 min-w-[120px]">' + rows.map((r) => {
      const label = r[0];
      const val = r[1];
      const col = r[2];
      if (val == null) {
        return '<div class="flex items-center gap-2">' +
                 '<span class="w-4 text-[10px] font-bold text-gray-500">' + label + '</span>' +
                 '<span class="text-[10px] text-gray-300">—</span></div>';
      }
      const v = clamp(Math.round(val));
      return '<div class="flex items-center gap-2">' +
               '<span class="w-4 text-[10px] font-bold text-gray-600">' + label + '</span>' +
               '<div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">' +
                 '<div class="h-full rounded-full" style="width:' + v + '%;background:' + col + ';"></div>' +
               '</div>' +
               '<span class="w-6 text-[10px] text-gray-500 text-end">' + v + '</span>' +
             '</div>';
    }).join('') + '</div>';
  }

  function recoHeaderBadge(ev) {
    const reco = ev && ev.recommendation ? String(ev.recommendation) : '';
    if (!reco) return '<span class="badge badge-gray">Not evaluated</span>';
    return '<span class="badge ' + AR.recoBadge(reco.toLowerCase()) + ' capitalize">' + AR.esc(pretty(reco)) + '</span>';
  }

  // ---- Cell wrappers (consistent table cell chrome) ---------------------
  function labelCell(title, sub) {
    return '<td class="px-4 py-3 ' + LABEL_W + ' sticky start-0 bg-white z-10 border-e border-gray-100 align-top">' +
             '<div class="font-semibold text-gray-700">' + AR.esc(title) + '</div>' +
             (sub ? '<div class="text-[11px] text-gray-400 mt-0.5">' + AR.esc(sub) + '</div>' : '') +
           '</td>';
  }
  function dataCell(inner, extra) {
    return '<td class="px-4 py-3 align-top text-center ' + (extra || '') + '">' + inner + '</td>';
  }

  // ---- Render the full comparison table ---------------------------------
  function renderTable(profiles) {
    const head = $('cmp-head');
    const bodyEl = $('cmp-body');

    // Column header cards.
    let headRow = '<tr>' +
      '<th class="px-4 py-4 ' + LABEL_W + ' sticky start-0 bg-white z-20 border-e border-b border-gray-100 text-left align-bottom">' +
        '<span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Attribute</span>' +
      '</th>';
    headRow += profiles.map((p) => {
      const c = p.candidate || {};
      const ev = getEval(p);
      const score = ev ? num(ev.overall_score) : null;
      const headline = c.current_title || c.headline || '';
      return '<th class="px-4 py-5 border-b border-gray-100 align-top text-center min-w-[200px]">' +
               '<div class="flex flex-col items-center gap-2">' +
                 '<div class="w-14 h-14 rounded-2xl gradient-brand text-white flex items-center justify-center text-lg font-bold shadow-sm">' + AR.esc(initials(c)) + '</div>' +
                 '<div class="font-bold text-gray-900 leading-tight">' + AR.esc(fullName(c)) + '</div>' +
                 (headline ? '<div class="text-xs text-gray-400 leading-tight">' + AR.esc(headline) + '</div>' : '') +
                 '<div class="mt-1">' + scoreRing(score) + '</div>' +
                 '<div class="mt-1">' + recoHeaderBadge(ev) + '</div>' +
               '</div>' +
             '</th>';
    }).join('');
    headRow += '</tr>';
    head.innerHTML = headRow;

    const rows = [];

    // (b) Overall Score row — score bar per column.
    rows.push('<tr class="hover:bg-gray-50/60 transition">' +
      labelCell('Overall Score', 'AI evaluation') +
      profiles.map((p) => {
        const ev = getEval(p);
        return dataCell(scoreBar(ev ? num(ev.overall_score) : null));
      }).join('') +
    '</tr>');

    // (c) The skill rows — union of skill_name with per-row best highlighting.
    const union = skillUnion(profiles);
    const maps = profiles.map(skillMap);

    if (union.length) {
      rows.push('<tr><td colspan="' + (profiles.length + 1) + '" class="px-4 py-2 bg-violet-50/50 text-[11px] font-semibold uppercase tracking-wide text-violet-700 sticky start-0">Skill Breakdown</td></tr>');
    }

    union.forEach((skill) => {
      // Compute the row maximum across columns from the data.
      let best = null;
      maps.forEach((m) => {
        const v = m[skill];
        if (v != null && (best == null || v > best)) best = v;
      });
      rows.push('<tr class="hover:bg-gray-50/60 transition">' +
        labelCell(skill) +
        maps.map((m) => {
          const v = m[skill];
          const isBest = (v != null && best != null && v === best);
          return dataCell(skillCell(v == null ? null : v, isBest));
        }).join('') +
      '</tr>');
    });

    // (d) DISC Profile row.
    rows.push('<tr class="hover:bg-gray-50/60 transition">' +
      labelCell('DISC Profile', 'Dominance · Influence · Steadiness · Conscientiousness') +
      profiles.map((p) => dataCell(discBlock(getPersonality(p)), 'text-left')).join('') +
    '</tr>');

    // (e) Actions row.
    rows.push('<tr>' +
      labelCell('Decision') +
      profiles.map((p) => {
        const c = p.candidate || {};
        const id = c.id;
        return dataCell(
          '<div class="flex flex-col items-center gap-2">' +
            '<a href="/candidates/' + encodeURIComponent(id) + '" class="btn-primary !py-2 !px-4 text-xs justify-center w-full max-w-[150px]">' +
              '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>' +
              'Hire This One' +
            '</a>' +
            '<a href="/candidates/' + encodeURIComponent(id) + '" class="btn-ghost !py-1.5 !px-3 text-xs justify-center w-full max-w-[150px] inline-flex">' +
              'View 360' +
              '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>' +
            '</a>' +
          '</div>'
        );
      }).join('') +
    '</tr>');

    bodyEl.innerHTML = rows.join('');
  }

  // ---- Boot --------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', function () {
    const ids = parseIds();

    if (!ids.length) {
      $('cmp-loading').classList.add('hidden');
      $('cmp-empty').classList.remove('hidden');
      return;
    }

    Promise.allSettled(ids.map((id) => AR.Api.get('/candidates/' + encodeURIComponent(id) + '/profile')))
      .then((results) => {
        const profiles = [];
        results.forEach((r, i) => {
          if (r.status === 'fulfilled' && r.value) {
            profiles.push(r.value);
          } else {
            console.warn('Skipping candidate ' + ids[i] + ': ' + ((r.reason && r.reason.message) || 'profile load failed'));
          }
        });

        $('cmp-loading').classList.add('hidden');

        if (!profiles.length) {
          $('cmp-empty').classList.remove('hidden');
          AR.Toast.error('Could not load any candidate profiles.');
          return;
        }

        // Partial load: still render what we have, but explain the gap.
        if (profiles.length < ids.length) {
          const note = $('cmp-note');
          const missing = ids.length - profiles.length;
          $('cmp-note-text').textContent =
            'Showing ' + profiles.length + ' of ' + ids.length + ' candidate' + (ids.length === 1 ? '' : 's') +
            '. ' + missing + ' profile' + (missing === 1 ? '' : 's') + ' could not be loaded.';
          note.classList.remove('hidden');
        }

        renderTable(profiles);
        $('cmp-wrap').classList.remove('hidden');
      })
      .catch((e) => {
        $('cmp-loading').classList.add('hidden');
        $('cmp-empty').classList.remove('hidden');
        AR.Toast.error((e && e.message) || 'Failed to load comparison.');
      });
  });
})();
</script>
