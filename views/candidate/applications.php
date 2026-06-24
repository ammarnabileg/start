<?php
/**
 * My applications with an expandable horizontal status timeline (stepper).
 * Fragment wrapped by layouts/candidate.php. Data hydrated via API with a
 * friendly empty state.
 */
$active = 'applications';

// Canonical pipeline stages, in order.
$__stages = [
    'applied'         => app_lang('stage_applied'),
    'screening'       => app_lang('stage_screening'),
    'ai_interview'    => app_lang('stage_ai_interview'),
    'human_interview' => app_lang('stage_human_interview'),
    'offer'           => app_lang('stage_offer'),
    'hired'           => app_lang('stage_hired'),
];
$__stagesJson = json_encode($__stages);
?>
<!-- ============ Header ============ -->
<div class="flex items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900"><?= e(app_lang('nav_my_applications')) ?></h1>
    <p class="mt-1 text-gray-500">Follow each application through every stage.</p>
  </div>
  <a href="/candidate/jobs" class="hidden sm:inline-flex btn-primary text-sm">Browse more jobs</a>
</div>

<!-- ============ Loading ============ -->
<div id="apps-loading" class="mt-6 space-y-4">
  <div class="card p-5"><div class="skeleton h-6 w-1/3"></div><div class="skeleton h-10 w-full mt-4"></div></div>
  <div class="card p-5"><div class="skeleton h-6 w-1/3"></div><div class="skeleton h-10 w-full mt-4"></div></div>
</div>

<!-- ============ List ============ -->
<div id="apps-list" class="mt-6 hidden space-y-4"></div>

<!-- ============ Empty ============ -->
<div id="apps-empty" class="mt-6 hidden card p-12 text-center">
  <div class="mx-auto w-16 h-16 rounded-2xl bg-violet-50 flex items-center justify-center">
    <svg class="w-8 h-8 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" /></svg>
  </div>
  <p class="mt-4 text-lg font-semibold text-gray-800">No applications yet</p>
  <p class="mt-1 text-sm text-gray-500">Once you apply to a role, you’ll be able to track its progress here.</p>
  <a href="/candidate/jobs" class="btn-primary mt-5 text-sm">Browse open positions</a>
</div>

<script>
  (function () {
    'use strict';
    var esc = (window.AR && AR.esc) ? AR.esc : function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var STAGES = <?= $__stagesJson ?>;
    var ORDER = Object.keys(STAGES);

    function show(id) { var el = document.getElementById(id); if (el) el.classList.remove('hidden'); }
    function hide(id) { var el = document.getElementById(id); if (el) el.classList.add('hidden'); }

    function stageIndex(stage) {
      var i = ORDER.indexOf(stage);
      return i < 0 ? 0 : i;
    }
    function statusBadge(stage) {
      if (stage === 'rejected') return '<span class="badge badge-red"><?= e(app_lang('stage_rejected')) ?></span>';
      if (stage === 'hired') return '<span class="badge badge-green"><?= e(app_lang('stage_hired')) ?></span>';
      var label = STAGES[stage] || (stage ? String(stage).replace(/_/g, ' ') : '<?= e(app_lang('stage_applied')) ?>');
      return '<span class="badge badge-violet">' + esc(label) + '</span>';
    }

    // Horizontal stepper. Current stage highlighted in violet.
    function stepper(stage, rejected) {
      var current = stageIndex(stage);
      var html = '<div class="overflow-x-auto"><ol class="flex items-center min-w-[640px] sm:min-w-0">';
      ORDER.forEach(function (key, i) {
        var done = i < current;
        var isCurrent = i === current && !rejected;
        var dotCls, lineCls, txtCls, inner;
        if (rejected && i >= current) {
          dotCls = 'bg-gray-100 text-gray-300 ring-1 ring-gray-200';
          txtCls = 'text-gray-300';
          inner = '<span class="text-[11px] font-bold">' + (i + 1) + '</span>';
        } else if (done) {
          dotCls = 'bg-brand text-white';
          txtCls = 'text-gray-500';
          inner = '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>';
        } else if (isCurrent) {
          dotCls = 'bg-brand text-white ring-4 ring-brand/20';
          txtCls = 'text-brand font-semibold';
          inner = '<span class="text-[11px] font-bold">' + (i + 1) + '</span>';
        } else {
          dotCls = 'bg-white text-gray-400 ring-1 ring-gray-200';
          txtCls = 'text-gray-400';
          inner = '<span class="text-[11px] font-bold">' + (i + 1) + '</span>';
        }
        lineCls = (i < current && !rejected) ? 'bg-brand' : 'bg-gray-200';

        html += '<li class="flex items-center ' + (i < ORDER.length - 1 ? 'flex-1' : '') + '">' +
          '<div class="flex flex-col items-center text-center shrink-0">' +
            '<span class="w-8 h-8 rounded-full flex items-center justify-center ' + dotCls + '">' + inner + '</span>' +
            '<span class="mt-1.5 text-[11px] leading-tight max-w-[70px] ' + txtCls + '">' + esc(STAGES[key]) + '</span>' +
          '</div>' +
          (i < ORDER.length - 1 ? '<span class="h-0.5 flex-1 mx-1 mb-5 rounded ' + lineCls + '"></span>' : '') +
        '</li>';
      });
      html += '</ol></div>';
      if (rejected) {
        html += '<div class="mt-3 flex items-center gap-2 text-sm text-red-600"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg> This application was not successful this time.</div>';
      }
      return html;
    }

    function row(a, idx) {
      var stage = a.pipeline_stage || a.stage || 'applied';
      var rejected = stage === 'rejected' || a.status === 'rejected';
      var title = a.job_title || a.title || 'Position';
      var company = a.company_name || a.department || '';
      var applied = a.applied_at || a.created_at || '';
      var appliedTxt = applied ? new Date(applied).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '';
      var panelId = 'app-panel-' + idx;

      var wrap = document.createElement('div');
      wrap.className = 'card overflow-hidden';
      wrap.innerHTML =
        '<button type="button" class="app-toggle w-full flex items-center gap-4 p-5 text-left hover:bg-gray-50/60 transition" aria-expanded="false" data-panel="' + panelId + '">' +
          '<span class="w-11 h-11 rounded-xl bg-violet-50 text-brand flex items-center justify-center font-bold shrink-0">' + esc(title.charAt(0).toUpperCase()) + '</span>' +
          '<span class="min-w-0 flex-1">' +
            '<span class="block font-bold text-gray-900 truncate">' + esc(title) + '</span>' +
            '<span class="block text-xs text-gray-400 truncate">' + esc(company) + (appliedTxt ? ' · Applied ' + esc(appliedTxt) : '') + '</span>' +
          '</span>' +
          statusBadge(rejected ? 'rejected' : stage) +
          '<svg class="chevron w-5 h-5 text-gray-400 shrink-0 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>' +
        '</button>' +
        '<div id="' + panelId + '" class="app-panel hidden px-5 pb-6 pt-1 border-t border-gray-100">' +
          stepper(rejected ? stage : stage, rejected) +
        '</div>';
      return wrap;
    }

    function bindToggles() {
      document.querySelectorAll('.app-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var panel = document.getElementById(btn.getAttribute('data-panel'));
          var open = panel && !panel.classList.contains('hidden');
          if (panel) panel.classList.toggle('hidden');
          btn.setAttribute('aria-expanded', open ? 'false' : 'true');
          var chev = btn.querySelector('.chevron');
          if (chev) chev.classList.toggle('rotate-180', !open);
        });
      });
    }

    function render(apps) {
      hide('apps-loading');
      var list = document.getElementById('apps-list');
      if (!apps || !apps.length) { show('apps-empty'); return; }
      show('apps-list');
      list.innerHTML = '';
      apps.forEach(function (a, i) { list.appendChild(row(a, i)); });
      bindToggles();
      // Expand the first one for immediate context.
      var first = document.querySelector('.app-toggle');
      if (first) first.click();
    }

    function load() {
      if (!(window.AR && AR.Api)) { hide('apps-loading'); show('apps-empty'); return; }
      AR.Api.get('/candidates/me/applications')
        .then(function (data) { render(data || []); })
        .catch(function () { hide('apps-loading'); show('apps-empty'); });
    }

    document.addEventListener('DOMContentLoaded', load);
  })();
</script>
