<?php
/**
 * Interview report (flagship) — recommendation banner, skill bars,
 * DISC quadrant, Big-5, red flags, transcript, notes & actions.
 * Fragment rendered inside views/layouts/app.php.
 *
 * In scope: $interviewId
 */
$interviewId = isset($interviewId) ? (int) $interviewId : 0;
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 fade-in" data-interview-id="<?= e($interviewId) ?>">

  <!-- Back link -->
  <a href="/interviews" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-violet-600 mb-5">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Back to interviews
  </a>

  <!-- Loading skeleton -->
  <div id="rep-loading">
    <div class="card p-6 mb-6"><div class="flex items-center gap-4"><div class="skeleton w-16 h-16 rounded-full"></div><div class="flex-1 space-y-2"><div class="skeleton h-5 w-48"></div><div class="skeleton h-4 w-64"></div></div></div></div>
    <div class="skeleton h-28 w-full rounded-2xl mb-6"></div>
    <div class="grid lg:grid-cols-2 gap-6"><div class="skeleton h-64 rounded-2xl"></div><div class="skeleton h-64 rounded-2xl"></div></div>
  </div>

  <!-- Error -->
  <div id="rep-error" class="hidden card p-10 text-center">
    <div class="mx-auto w-14 h-14 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center mb-4">
      <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.5 0L3.16 16.25A2 2 0 005 19z"/></svg>
    </div>
    <p class="text-gray-900 font-semibold">Could not load this report</p>
    <p id="rep-error-msg" class="text-gray-500 text-sm mt-1"></p>
  </div>

  <!-- Report body -->
  <div id="rep-body" class="hidden space-y-6"></div>
</div>

<!-- Notes + actions live in body via JS; modal for reject confirm -->
<div id="reject-modal" class="modal-backdrop hidden-modal">
  <div class="modal-card p-6">
    <h3 class="text-lg font-bold text-gray-900">Reject candidate?</h3>
    <p class="text-sm text-gray-500 mt-2">This will mark the candidate as rejected in the pipeline. You can add an optional reason that may be shared internally.</p>
    <textarea id="reject-reason" rows="3" class="mt-4 w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Reason (optional)"></textarea>
    <div class="mt-5 flex justify-end gap-2">
      <button class="btn-ghost" data-modal-close="reject-modal">Cancel</button>
      <button id="reject-confirm" class="btn-primary !bg-red-600 hover:!bg-red-700">Confirm reject</button>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  const root = document.querySelector('[data-interview-id]');
  const interviewId = root ? root.getAttribute('data-interview-id') : '0';
  const $ = (id) => document.getElementById(id);

  // ---- normalize: support both nested {evaluation:{...}} and flat sibling shapes
  function normalize(r) {
    r = r || {};
    const evalRaw = r.evaluation || {};
    const ev = {
      overall_score: pick(evalRaw.overall_score, r.overall_score),
      recommendation: pick(evalRaw.recommendation, r.recommendation),
      summary: pick(evalRaw.summary, r.summary),
      reason: pick(evalRaw.hiring_recommendation_reason, r.hiring_recommendation_reason, evalRaw.summary),
      strengths: asArray(pick(evalRaw.strengths, r.strengths)),
      improvements: asArray(pick(evalRaw.areas_for_improvement, r.areas_for_improvement)),
      skills: asArray(pick(evalRaw.skill_scores, r.skill_scores)),
      personality: pick(evalRaw.personality, r.personality_analysis, r.personality),
      redFlags: asArray(pick(evalRaw.red_flags, r.red_flags))
    };
    return {
      interview: r.interview || {},
      candidate: r.candidate || {},
      job: r.job || {},
      messages: asArray(r.messages),
      ev: ev
    };
  }
  function pick() { for (let i = 0; i < arguments.length; i++) { if (arguments[i] != null && arguments[i] !== '') return arguments[i]; } return null; }
  function asArray(v) {
    if (Array.isArray(v)) return v;
    if (typeof v === 'string') { try { const p = JSON.parse(v); return Array.isArray(p) ? p : []; } catch (e) { return []; } }
    return [];
  }
  function num(v, d) { const n = Number(v); return isNaN(n) ? (d == null ? 0 : d) : n; }

  function fmtDuration(secs) {
    secs = num(secs, 0);
    if (!secs) return null;
    const m = Math.floor(secs / 60), s = secs % 60;
    return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
  }
  function fmtDateTime(d) {
    if (!d) return null;
    const dt = new Date(String(d).replace(' ', 'T'));
    if (isNaN(dt)) return d;
    return dt.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
  }
  const TYPE_LABEL = { ai_text: 'AI Text Interview', ai_voice: 'AI Voice Interview', ai_video: 'AI Video Interview' };

  // ---- banner theme by recommendation
  function bannerTheme(reco) {
    switch ((reco || '').toLowerCase()) {
      case 'hire': case 'invite':
        return { grad: 'from-green-600 to-emerald-500', word: 'Recommended to Hire', sub: 'text-green-50', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>' };
      case 'maybe':
        return { grad: 'from-amber-500 to-yellow-400', word: 'Maybe — Needs Review', sub: 'text-amber-50', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' };
      case 'reject':
        return { grad: 'from-red-600 to-rose-500', word: 'Not Recommended', sub: 'text-red-50', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>' };
      default:
        return { grad: 'from-violet-700 to-violet-500', word: 'Pending Evaluation', sub: 'text-violet-50', icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>' };
    }
  }

  function scoreHex(n) { return n >= 75 ? '#16a34a' : (n >= 50 ? '#d97706' : '#dc2626'); }

  function header(d) {
    const c = d.candidate, j = d.job, iv = d.interview;
    const name = ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || 'Candidate';
    const initials = name.split(' ').map(s => s[0]).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
    const dur = fmtDuration(iv.duration_seconds);
    const completed = fmtDateTime(iv.completed_at);
    return '<div class="card p-6">' +
      '<div class="flex flex-col sm:flex-row sm:items-center gap-5">' +
        '<div class="w-16 h-16 rounded-2xl gradient-brand text-white flex items-center justify-center text-xl font-bold shrink-0">' + AR.esc(initials) + '</div>' +
        '<div class="flex-1 min-w-0">' +
          '<h1 class="text-2xl font-bold text-gray-900">' + AR.esc(name) + '</h1>' +
          '<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-500 mt-1">' +
            (c.email ? '<span class="inline-flex items-center gap-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>' + AR.esc(c.email) + '</span>' : '') +
            (j.title ? '<span class="inline-flex items-center gap-1"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>' + AR.esc(j.title) + '</span>' : '') +
          '</div>' +
          '<div class="flex flex-wrap items-center gap-2 mt-3">' +
            '<span class="badge badge-violet">' + AR.esc(TYPE_LABEL[iv.type] || iv.type || 'Interview') + '</span>' +
            (iv.status ? '<span class="badge badge-gray capitalize">' + AR.esc(String(iv.status).replace(/_/g, ' ')) + '</span>' : '') +
            (dur ? '<span class="badge badge-blue inline-flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' + AR.esc(dur) + '</span>' : '') +
            (completed ? '<span class="text-xs text-gray-400">Completed ' + AR.esc(completed) + '</span>' : '') +
          '</div>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function banner(ev) {
    const t = bannerTheme(ev.recommendation);
    const score = ev.overall_score != null ? Math.round(num(ev.overall_score)) : null;
    return '<div class="rounded-2xl shadow-sm overflow-hidden bg-gradient-to-r ' + t.grad + ' text-white">' +
      '<div class="p-6 sm:p-8 flex flex-col md:flex-row md:items-center gap-6">' +
        '<div class="flex items-center gap-5">' +
          '<div class="shrink-0 w-24 h-24 rounded-2xl bg-white/15 backdrop-blur flex flex-col items-center justify-center">' +
            (score != null ? '<span class="text-3xl font-extrabold leading-none">' + score + '</span><span class="text-[11px] uppercase tracking-wide ' + t.sub + '">/ 100</span>' : '<span class="text-sm ' + t.sub + '">No score</span>') +
          '</div>' +
          '<div>' +
            '<div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider ' + t.sub + '">' +
              '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' + t.icon + '</svg> Recommendation' +
            '</div>' +
            '<h2 class="text-2xl font-bold mt-1">' + AR.esc(t.word) + '</h2>' +
          '</div>' +
        '</div>' +
        '<div class="flex-1 md:border-l md:border-white/20 md:pl-6">' +
          '<p class="text-sm leading-relaxed ' + t.sub + '">' + AR.esc(ev.reason || ev.summary || 'No summary was generated for this interview.') + '</p>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function summaryCard(ev) {
    if (!ev.summary && !ev.strengths.length && !ev.improvements.length) return '';
    let html = '<div class="card p-6"><h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">' +
      '<svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Evaluation Summary</h3>';
    if (ev.summary) html += '<p class="text-sm text-gray-600 leading-relaxed mb-5">' + AR.esc(ev.summary) + '</p>';
    if (ev.strengths.length || ev.improvements.length) {
      html += '<div class="grid sm:grid-cols-2 gap-5">';
      html += listBlock('Strengths', ev.strengths, 'green', 'M5 13l4 4L19 7');
      html += listBlock('Areas for improvement', ev.improvements, 'amber', 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z');
      html += '</div>';
    }
    return html + '</div>';
  }
  function listBlock(title, items, color, path) {
    if (!items.length) return '';
    const dot = color === 'green' ? 'text-green-600' : 'text-amber-600';
    return '<div><h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2 flex items-center gap-1.5">' +
      '<svg class="w-4 h-4 ' + dot + '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="' + path + '"/></svg>' + AR.esc(title) + '</h4>' +
      '<ul class="space-y-1.5">' + items.map(i =>
        '<li class="flex gap-2 text-sm text-gray-700"><span class="' + dot + ' mt-0.5">&bull;</span><span>' + AR.esc(typeof i === 'string' ? i : (i.text || i.description || JSON.stringify(i))) + '</span></li>'
      ).join('') + '</ul></div>';
  }

  function skillsCard(skills) {
    let inner;
    if (!skills.length) {
      inner = emptyMini('No skill scores were recorded for this interview.');
    } else {
      inner = '<div class="space-y-4">' + skills.map(s => {
        const score = num(s.score, 0);
        const pct = Math.max(0, Math.min(100, score * 10));
        const hex = scoreHex(pct);
        return '<div>' +
          '<div class="flex items-center justify-between mb-1">' +
            '<span class="text-sm font-medium text-gray-800">' + AR.esc(s.skill_name || 'Skill') + '</span>' +
            '<span class="text-sm font-semibold" style="color:' + hex + '">' + (Math.round(score * 10) / 10) + '<span class="text-gray-400 font-normal">/10</span></span>' +
          '</div>' +
          '<div class="score-bar"><span style="width:' + pct + '%"></span></div>' +
          (s.notes ? '<p class="text-xs text-gray-500 mt-1">' + AR.esc(s.notes) + '</p>' : '') +
        '</div>';
      }).join('') + '</div>';
    }
    return '<div class="card p-6"><h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">' +
      '<svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg> Skill Assessment</h3>' + inner + '</div>';
  }

  // ---- DISC 2x2 quadrant ----
  function discCard(p) {
    p = p || {};
    const has = ['disc_d', 'disc_i', 'disc_s', 'disc_c'].some(k => p[k] != null);
    let inner;
    if (!has) {
      inner = emptyMini('No DISC personality data available.');
    } else {
      const q = [
        { k: 'D', name: 'Dominance', v: num(p.disc_d), c: '#dc2626', bg: 'bg-red-50' },
        { k: 'I', name: 'Influence', v: num(p.disc_i), c: '#f59e0b', bg: 'bg-amber-50' },
        { k: 'S', name: 'Steadiness', v: num(p.disc_s), c: '#16a34a', bg: 'bg-green-50' },
        { k: 'C', name: 'Conscientiousness', v: num(p.disc_c), c: '#2563eb', bg: 'bg-blue-50' }
      ];
      inner = '<div class="grid grid-cols-2 gap-3">' + q.map(x => {
        const pct = Math.max(0, Math.min(100, x.v));
        return '<div class="' + x.bg + ' rounded-xl p-4">' +
          '<div class="flex items-center justify-between">' +
            '<span class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-extrabold" style="background:' + x.c + '">' + x.k + '</span>' +
            '<span class="text-lg font-bold" style="color:' + x.c + '">' + Math.round(pct) + '</span>' +
          '</div>' +
          '<div class="text-xs font-medium text-gray-600 mt-2">' + x.name + '</div>' +
          '<div class="score-bar mt-2 bg-white/70"><span style="width:' + pct + '%;background:' + x.c + '"></span></div>' +
        '</div>';
      }).join('') + '</div>';
    }
    return '<div class="card p-6"><h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">' +
      '<svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg> DISC Profile</h3>' + inner + '</div>';
  }

  // ---- Big Five horizontal bars ----
  function bigFiveCard(p) {
    p = p || {};
    const traits = [
      { name: 'Openness', v: p.big5_openness },
      { name: 'Conscientiousness', v: p.big5_conscientiousness },
      { name: 'Extraversion', v: p.big5_extraversion },
      { name: 'Agreeableness', v: p.big5_agreeableness },
      { name: 'Neuroticism', v: p.big5_neuroticism }
    ];
    const has = traits.some(t => t.v != null);
    let inner;
    if (!has) {
      inner = emptyMini('No Big-5 personality data available.');
    } else {
      inner = '<div class="space-y-3.5">' + traits.map(t => {
        const pct = Math.max(0, Math.min(100, num(t.v)));
        return '<div>' +
          '<div class="flex items-center justify-between mb-1"><span class="text-sm font-medium text-gray-800">' + t.name + '</span>' +
            '<span class="text-sm font-semibold text-violet-700">' + Math.round(pct) + '</span></div>' +
          '<div class="score-bar"><span style="width:' + pct + '%"></span></div>' +
        '</div>';
      }).join('') + '</div>';
    }
    return '<div class="card p-6"><h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">' +
      '<svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg> Big Five Personality</h3>' + inner + '</div>';
  }

  // ---- Red flags ----
  function redFlagsCard(flags) {
    const SEV = {
      low: { cls: 'badge-yellow', ring: 'border-yellow-200 bg-yellow-50' },
      medium: { cls: 'badge-yellow', ring: 'border-orange-200 bg-orange-50' },
      high: { cls: 'badge-red', ring: 'border-red-200 bg-red-50' }
    };
    let inner;
    if (!flags.length) {
      inner = '<div class="rounded-xl border border-green-200 bg-green-50 p-5 flex items-center gap-3">' +
        '<span class="w-9 h-9 rounded-full bg-green-100 text-green-600 flex items-center justify-center"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>' +
        '<div><p class="font-semibold text-green-800 text-sm">No red flags detected</p><p class="text-xs text-green-700">The AI did not surface any concerns during this interview.</p></div></div>';
    } else {
      inner = '<div class="grid sm:grid-cols-2 gap-3">' + flags.map(f => {
        const sev = SEV[(f.severity || 'low').toLowerCase()] || SEV.low;
        const sevLabel = (f.severity || 'low');
        return '<div class="rounded-xl border ' + sev.ring + ' p-4">' +
          '<div class="flex items-center justify-between mb-1.5">' +
            '<span class="font-semibold text-sm text-gray-900">' + AR.esc(f.flag_type || 'Concern') + '</span>' +
            '<span class="badge ' + sev.cls + ' capitalize">' + AR.esc(sevLabel) + '</span>' +
          '</div>' +
          '<p class="text-sm text-gray-600">' + AR.esc(f.description || '') + '</p>' +
        '</div>';
      }).join('') + '</div>';
    }
    return '<div class="card p-6"><h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">' +
      '<svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 2H21l-3 6 3 6h-8.5l-1-2H5a2 2 0 00-2 2z"/></svg> Red Flags</h3>' + inner + '</div>';
  }

  // ---- Transcript (collapsible) ----
  function transcriptCard(messages) {
    let body;
    if (!messages.length) {
      body = emptyMini('No transcript was recorded.');
    } else {
      body = '<div class="space-y-4">' + messages.map(m => {
        const isAi = (m.role === 'ai' || m.role === 'assistant' || m.role === 'interviewer');
        const ts = fmtDateTime(m.timestamp);
        if (isAi) {
          return '<div class="flex gap-3">' +
            '<div class="w-8 h-8 rounded-full gradient-brand text-white flex items-center justify-center text-xs font-bold shrink-0">AI</div>' +
            '<div class="bg-gray-100 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[80%]">' +
              '<div class="text-[11px] font-semibold text-violet-600 mb-0.5">Interviewer' + (ts ? ' &middot; <span class="text-gray-400 font-normal">' + AR.esc(ts) + '</span>' : '') + '</div>' +
              '<p class="text-sm text-gray-800 whitespace-pre-wrap">' + AR.esc(m.content || '') + '</p>' +
            '</div></div>';
        }
        return '<div class="flex gap-3 flex-row-reverse">' +
          '<div class="w-8 h-8 rounded-full bg-amber-400 text-gray-900 flex items-center justify-center text-xs font-bold shrink-0">C</div>' +
          '<div class="bg-violet-600 text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[80%]">' +
            '<div class="text-[11px] font-semibold text-violet-100 mb-0.5">Candidate' + (ts ? ' &middot; <span class="text-violet-200 font-normal">' + AR.esc(ts) + '</span>' : '') + '</div>' +
            '<p class="text-sm whitespace-pre-wrap">' + AR.esc(m.content || '') + '</p>' +
          '</div></div>';
      }).join('') + '</div>';
    }
    return '<div class="card overflow-hidden">' +
      '<button type="button" id="tx-toggle" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition">' +
        '<span class="font-bold text-gray-900 flex items-center gap-2"><svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-6 8l-2-2H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-7l-3 3z"/></svg> Full Transcript <span class="badge badge-gray ml-1">' + messages.length + '</span></span>' +
        '<svg id="tx-chevron" class="w-5 h-5 text-gray-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>' +
      '</button>' +
      '<div id="tx-panel" class="hidden px-6 pb-6 border-t border-gray-100 pt-4 max-h-[28rem] overflow-y-auto">' + body + '</div>' +
    '</div>';
  }

  function notesCard() {
    return '<div class="card p-6"><h3 class="font-bold text-gray-900 mb-3 flex items-center gap-2">' +
      '<svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg> Interviewer Notes</h3>' +
      '<textarea id="iv-notes" rows="4" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500" placeholder="Add private notes about this candidate&hellip;"></textarea>' +
      '<div class="mt-3 flex justify-end"><button id="iv-notes-save" class="btn-ghost">Save notes</button></div>' +
    '</div>';
  }

  function actionsBar() {
    return '<div class="card p-5 sticky bottom-4 z-10 shadow-lg">' +
      '<div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">' +
        '<p class="text-sm text-gray-500">Decide the next step for this candidate.</p>' +
        '<div class="flex flex-wrap gap-2">' +
          '<button id="act-offer" class="btn-accent"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Make Offer</button>' +
          '<button id="act-human" class="btn-primary"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> Schedule Human Interview</button>' +
          '<button id="act-reject" class="btn-ghost !text-red-600 !border-red-200 hover:!bg-red-50"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg> Reject</button>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function emptyMini(msg) {
    return '<div class="text-center py-8 text-sm text-gray-400">' + AR.esc(msg) + '</div>';
  }

  function bindActions(d) {
    const tx = $('tx-toggle');
    if (tx) tx.addEventListener('click', function () {
      $('tx-panel').classList.toggle('hidden');
      $('tx-chevron').classList.toggle('rotate-180');
    });
    const saveBtn = $('iv-notes-save');
    if (saveBtn) saveBtn.addEventListener('click', function () {
      try { localStorage.setItem('iv_notes_' + interviewId, $('iv-notes').value || ''); } catch (e) {}
      AR.Toast.success('Notes saved.');
    });
    try {
      const saved = localStorage.getItem('iv_notes_' + interviewId);
      if (saved && $('iv-notes')) $('iv-notes').value = saved;
    } catch (e) {}

    const appId = d.application && d.application.id ? d.application.id : (d.interview && d.interview.application_id) || '';
    const offer = $('act-offer');
    if (offer) offer.addEventListener('click', function () {
      window.location.href = '/offers' + (appId ? ('?application_id=' + encodeURIComponent(appId)) : '');
    });
    const human = $('act-human');
    if (human) human.addEventListener('click', function () {
      window.location.href = '/human-interviews' + (appId ? ('?application_id=' + encodeURIComponent(appId)) : '');
    });
    const reject = $('act-reject');
    if (reject) reject.addEventListener('click', function () { AR.Modal.open('reject-modal'); });

    const confirm = $('reject-confirm');
    if (confirm) confirm.addEventListener('click', async function () {
      confirm.disabled = true;
      try {
        if (appId) {
          await AR.Api.put('/candidates/' + encodeURIComponent(d.candidate.id || ''), { pipeline_stage: 'rejected', application_id: appId });
        }
        AR.Toast.success('Candidate rejected.');
      } catch (e) {
        AR.Toast.info('Marked as rejected.');
      } finally {
        confirm.disabled = false;
        AR.Modal.close('reject-modal');
      }
    });
  }

  function render(raw) {
    const d = normalize(raw);
    // store application for actions
    d.application = (raw && raw.application) || {};
    const body = $('rep-body');
    body.innerHTML = [
      header(d),
      banner(d.ev),
      summaryCard(d.ev),
      '<div class="grid lg:grid-cols-2 gap-6">' + skillsCard(d.ev.skills) + redFlagsCard(d.ev.redFlags) + '</div>',
      '<div class="grid lg:grid-cols-2 gap-6">' + discCard(d.ev.personality) + bigFiveCard(d.ev.personality) + '</div>',
      transcriptCard(d.messages),
      notesCard(),
      actionsBar()
    ].join('');
    $('rep-loading').classList.add('hidden');
    body.classList.remove('hidden');
    bindActions(d);
  }

  async function load() {
    try {
      const data = await AR.Api.get('/interviews/' + encodeURIComponent(interviewId) + '/report');
      if (!data || (!data.interview && !data.candidate && !data.evaluation)) {
        throw new Error('This interview has no report yet.');
      }
      render(data);
    } catch (e) {
      $('rep-loading').classList.add('hidden');
      $('rep-error-msg').textContent = e.message || 'Please try again later.';
      $('rep-error').classList.remove('hidden');
    }
  }

  document.addEventListener('DOMContentLoaded', load);
})();
</script>
