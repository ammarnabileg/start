<?php
/**
 * Super Admin — AI Usage Analytics (fragment, rendered into $content).
 * Wrapped by views/layouts/admin.php.
 */
$csrf = $csrf ?? '';
?>
<div class="px-4 sm:px-6 lg:px-8 py-6 max-w-7xl mx-auto fade-in" data-page="admin-ai-analytics">

  <!-- Header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div>
      <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-violet-600 uppercase mb-1">
        <span class="inline-block w-2 h-2 rounded-full bg-violet-600"></span>
        <?= e(app_lang('AI Operations')) ?>
      </div>
      <h1 class="text-2xl font-bold text-gray-900"><?= e(app_lang('AI Usage Analytics')) ?></h1>
      <p class="text-sm text-gray-500 mt-1"><?= e(app_lang('Token consumption, cost and feature breakdown across all tenants.')) ?></p>
    </div>
    <!-- Period selector -->
    <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 self-start" role="tablist" id="period-selector">
      <button type="button" data-period="7d"  class="period-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600"><?= e(app_lang('7 days')) ?></button>
      <button type="button" data-period="30d" class="period-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600"><?= e(app_lang('30 days')) ?></button>
      <button type="button" data-period="90d" class="period-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600"><?= e(app_lang('90 days')) ?></button>
    </div>
  </div>

  <!-- Error banner -->
  <div id="ai-error" class="hidden mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 flex items-center gap-2">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
    <span id="ai-error-text"><?= e(app_lang('Could not load AI analytics.')) ?></span>
  </div>

  <!-- Summary cards -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="card p-5">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= e(app_lang('Total Tokens')) ?></p>
          <p id="sum-tokens" class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">—</p>
          <p id="sum-tokens-sub" class="text-xs text-gray-400 mt-1">&nbsp;</p>
        </div>
        <span class="w-11 h-11 rounded-full bg-violet-100 text-violet-600 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </span>
      </div>
    </div>
    <div class="card p-5">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= e(app_lang('Total Cost')) ?></p>
          <p id="sum-cost" class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">—</p>
          <p id="sum-cost-sub" class="text-xs text-amber-600 mt-1 font-medium">&nbsp;</p>
        </div>
        <span class="w-11 h-11 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
      </div>
    </div>
    <div class="card p-5">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= e(app_lang('Total Calls')) ?></p>
          <p id="sum-calls" class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">—</p>
          <p id="sum-calls-sub" class="text-xs text-gray-400 mt-1">&nbsp;</p>
        </div>
        <span class="w-11 h-11 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        </span>
      </div>
    </div>
  </div>

  <!-- Charts grid -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="card p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-900"><?= e(app_lang('Token Usage Over Time')) ?></h2>
        <span class="badge badge-violet" id="time-range-badge">—</span>
      </div>
      <div class="relative h-56"><canvas id="chart-tokens-time" class="w-full h-full"></canvas></div>
    </div>
    <div class="card p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-900"><?= e(app_lang('Cost by Feature')) ?></h2>
        <span class="badge badge-yellow"><?= e(app_lang('USD')) ?></span>
      </div>
      <div class="relative h-56"><canvas id="chart-cost-feature" class="w-full h-full"></canvas></div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Most used features -->
    <div class="card p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-900"><?= e(app_lang('Most Used Features')) ?></h2>
        <span class="text-xs text-gray-400"><?= e(app_lang('by tokens')) ?></span>
      </div>
      <div id="feature-ranking" class="space-y-3">
        <p class="text-sm text-gray-400 py-6 text-center"><?= e(app_lang('Loading…')) ?></p>
      </div>
    </div>

    <!-- Per-company breakdown -->
    <div class="card overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-900"><?= e(app_lang('Per-Company Breakdown')) ?></h2>
        <span class="text-xs text-gray-400"><?= e(app_lang('top consumers')) ?></span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-400 border-b border-gray-100">
              <th class="px-5 py-3"><?= e(app_lang('Company')) ?></th>
              <th class="px-5 py-3 ltr:text-right rtl:text-left"><?= e(app_lang('Tokens')) ?></th>
              <th class="px-5 py-3 ltr:text-right rtl:text-left"><?= e(app_lang('Cost')) ?></th>
              <th class="px-5 py-3 w-32"><?= e(app_lang('Share')) ?></th>
            </tr>
          </thead>
          <tbody id="company-body" class="divide-y divide-gray-50">
            <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400"><?= e(app_lang('Loading…')) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var AR = window.AR || {};
  var esc = AR.esc || function (s) { return s == null ? '' : String(s); };

  /* ---------------- Canvas charting helpers (self-contained) ---------------- */
  var VIOLET = '#7C3AED', AMBER = '#FBBF24', GRID = '#EEF0F4', AXIS = '#9CA3AF';

  function prep(canvas) {
    var dpr = window.devicePixelRatio || 1;
    var rect = canvas.getBoundingClientRect();
    var w = Math.max(rect.width, 200), h = Math.max(rect.height, 120);
    canvas.width = Math.floor(w * dpr);
    canvas.height = Math.floor(h * dpr);
    var ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    return { ctx: ctx, w: w, h: h };
  }
  function niceMax(v) {
    if (!isFinite(v) || v <= 0) return 10;
    var pow = Math.pow(10, Math.floor(Math.log10(v)));
    var n = v / pow;
    var step = n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10;
    return step * pow;
  }
  function fmt(n) {
    n = Number(n) || 0;
    if (Math.abs(n) >= 1e9) return (n / 1e9).toFixed(1).replace(/\.0$/, '') + 'B';
    if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
    if (Math.abs(n) >= 1e3) return (n / 1e3).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(Math.round(n));
  }
  function money(n) { return '$' + (Math.round((Number(n) || 0) * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function gridY(ctx, w, h, pad, max, fmtFn) {
    ctx.font = '10px Inter, sans-serif'; ctx.fillStyle = AXIS; ctx.strokeStyle = GRID; ctx.lineWidth = 1;
    var rows = 4;
    for (var i = 0; i <= rows; i++) {
      var y = pad.t + (h - pad.t - pad.b) * (i / rows);
      ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(w - pad.r, y); ctx.stroke();
      var val = max * (1 - i / rows);
      ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
      ctx.fillText((fmtFn || fmt)(val), pad.l - 6, y);
    }
  }
  function xlabels(ctx, labels, w, h, pad) {
    if (!labels || !labels.length) return;
    ctx.fillStyle = AXIS; ctx.font = '10px Inter, sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'top';
    var iw = w - pad.l - pad.r;
    var stepEvery = Math.ceil(labels.length / 7);
    for (var i = 0; i < labels.length; i++) {
      if (i % stepEvery !== 0 && i !== labels.length - 1) continue;
      var x = labels.length === 1 ? pad.l + iw / 2 : pad.l + iw * (i / (labels.length - 1));
      ctx.fillText(labels[i], x, h - pad.b + 6);
    }
  }
  function emptyState(ctx, w, h) {
    ctx.fillStyle = '#9CA3AF'; ctx.font = '12px Inter, sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText('No data for this period', w / 2, h / 2);
  }

  function drawAreaChart(canvas, points, opts) {
    opts = opts || {};
    var p = prep(canvas), ctx = p.ctx, w = p.w, h = p.h;
    var pad = { t: 12, r: 14, b: 24, l: 44 };
    var vals = (points || []).map(function (d) { return Number(d.value) || 0; });
    if (!vals.length) { emptyState(ctx, w, h); return; }
    var max = niceMax(Math.max.apply(null, vals.concat([1])));
    gridY(ctx, w, h, pad, max);
    var iw = w - pad.l - pad.r, ih = h - pad.t - pad.b;
    var color = opts.color || VIOLET;
    function X(i) { return points.length === 1 ? pad.l + iw / 2 : pad.l + iw * (i / (points.length - 1)); }
    function Y(v) { return pad.t + ih * (1 - v / max); }
    var g = ctx.createLinearGradient(0, pad.t, 0, h - pad.b);
    g.addColorStop(0, opts.fill || 'rgba(124,58,237,0.20)');
    g.addColorStop(1, 'rgba(124,58,237,0)');
    ctx.beginPath(); ctx.moveTo(X(0), Y(vals[0]));
    for (var i = 1; i < vals.length; i++) ctx.lineTo(X(i), Y(vals[i]));
    ctx.lineTo(X(vals.length - 1), h - pad.b); ctx.lineTo(X(0), h - pad.b); ctx.closePath();
    ctx.fillStyle = g; ctx.fill();
    ctx.beginPath(); ctx.moveTo(X(0), Y(vals[0]));
    for (var j = 1; j < vals.length; j++) ctx.lineTo(X(j), Y(vals[j]));
    ctx.strokeStyle = color; ctx.lineWidth = 2.5; ctx.lineJoin = 'round'; ctx.stroke();
    ctx.fillStyle = color;
    for (var k = 0; k < vals.length; k++) { ctx.beginPath(); ctx.arc(X(k), Y(vals[k]), 2.2, 0, Math.PI * 2); ctx.fill(); }
    xlabels(ctx, points.map(function (d) { return d.label || ''; }), w, h, pad);
  }

  function drawBarChart(canvas, labels, values, opts) {
    opts = opts || {};
    var p = prep(canvas), ctx = p.ctx, w = p.w, h = p.h;
    var pad = { t: 12, r: 14, b: 40, l: 44 };
    values = values || [];
    if (!values.length) { emptyState(ctx, w, h); return; }
    var max = niceMax(Math.max.apply(null, values.concat([1])));
    gridY(ctx, w, h, pad, max, opts.yfmt);
    var iw = w - pad.l - pad.r, ih = h - pad.t - pad.b;
    var n = values.length;
    var bw = Math.max(6, Math.min(48, (iw / n) * 0.62));
    var color = opts.color || AMBER;
    for (var i = 0; i < n; i++) {
      var cx = pad.l + (iw / n) * (i + 0.5);
      var bh = ih * ((Number(values[i]) || 0) / max);
      var x = cx - bw / 2, y = pad.t + ih - bh;
      var g = ctx.createLinearGradient(0, y, 0, y + bh);
      g.addColorStop(0, color); g.addColorStop(1, opts.color2 || color);
      ctx.fillStyle = g;
      var r = Math.min(5, bw / 2);
      ctx.beginPath();
      ctx.moveTo(x, y + bh); ctx.lineTo(x, y + r);
      ctx.quadraticCurveTo(x, y, x + r, y);
      ctx.lineTo(x + bw - r, y); ctx.quadraticCurveTo(x + bw, y, x + bw, y + r);
      ctx.lineTo(x + bw, y + bh); ctx.closePath(); ctx.fill();
    }
    // rotated-ish labels (truncate)
    ctx.fillStyle = AXIS; ctx.font = '10px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
    for (var m = 0; m < n; m++) {
      var lx = pad.l + (iw / n) * (m + 0.5);
      var lbl = String(labels[m] || '');
      if (lbl.length > 10) lbl = lbl.slice(0, 9) + '…';
      ctx.fillText(lbl, lx, h - pad.b + 6);
    }
  }

  /* ---------------- Data wiring ---------------- */
  var currentPeriod = '30d';

  function num(v) { return Number(v) || 0; }
  function prettyFeature(f) {
    return String(f || '—').replace(/[_\-]+/g, ' ').replace(/\b\w/g, function (m) { return m.toUpperCase(); });
  }
  function shortDay(s) {
    if (!s) return '';
    var d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(s).slice(5);
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }

  function setActivePeriod(period) {
    document.querySelectorAll('.period-btn').forEach(function (b) {
      var on = b.getAttribute('data-period') === period;
      b.classList.toggle('bg-violet-600', on);
      b.classList.toggle('text-white', on);
      b.classList.toggle('text-gray-600', !on);
      b.classList.toggle('shadow-sm', on);
    });
    var label = period === '7d' ? 'Last 7 days' : period === '90d' ? 'Last 90 days' : 'Last 30 days';
    var badge = document.getElementById('time-range-badge');
    if (badge) badge.textContent = label;
  }

  function renderFeatureRanking(features) {
    var box = document.getElementById('feature-ranking');
    var list = (features || []).slice().sort(function (a, b) { return num(b.tokens) - num(a.tokens); }).slice(0, 6);
    if (!list.length) {
      box.innerHTML = '<p class="text-sm text-gray-400 py-6 text-center">' + esc('No feature usage recorded.') + '</p>';
      return;
    }
    var max = Math.max.apply(null, list.map(function (f) { return num(f.tokens); }).concat([1]));
    box.innerHTML = list.map(function (f) {
      var pct = Math.max(2, Math.round((num(f.tokens) / max) * 100));
      return '<div>' +
        '<div class="flex items-center justify-between text-sm mb-1">' +
          '<span class="font-medium text-gray-700">' + esc(prettyFeature(f.feature)) + '</span>' +
          '<span class="text-gray-500 tabular-nums">' + fmt(f.tokens) + ' <span class="text-gray-300">·</span> ' + num(f.calls) + ' calls</span>' +
        '</div>' +
        '<div class="score-bar"><span style="width:' + pct + '%"></span></div>' +
      '</div>';
    }).join('');
  }

  function renderCompanyTable(companies) {
    var body = document.getElementById('company-body');
    var list = (companies || []).slice().sort(function (a, b) { return num(b.tokens) - num(a.tokens); });
    if (!list.length) {
      body.innerHTML = '<tr><td colspan="4" class="px-5 py-10 text-center text-gray-400">' + esc('No company usage data.') + '</td></tr>';
      return;
    }
    var totalTokens = list.reduce(function (s, c) { return s + num(c.tokens); }, 0) || 1;
    body.innerHTML = list.slice(0, 10).map(function (c) {
      var pct = Math.round((num(c.tokens) / totalTokens) * 100);
      var initial = esc((c.name || '?').charAt(0).toUpperCase());
      return '<tr class="hover:bg-gray-50 transition-colors">' +
        '<td class="px-5 py-3">' +
          '<div class="flex items-center gap-3">' +
            '<span class="w-7 h-7 rounded-lg bg-violet-100 text-violet-700 text-xs font-bold flex items-center justify-center">' + initial + '</span>' +
            '<span class="font-medium text-gray-900">' + esc(c.name || '—') + '</span>' +
          '</div>' +
        '</td>' +
        '<td class="px-5 py-3 ltr:text-right rtl:text-left text-gray-700 tabular-nums">' + fmt(c.tokens) + '</td>' +
        '<td class="px-5 py-3 ltr:text-right rtl:text-left text-gray-700 tabular-nums">' + money(c.cost) + '</td>' +
        '<td class="px-5 py-3">' +
          '<div class="flex items-center gap-2">' +
            '<div class="score-bar flex-1"><span style="width:' + Math.max(2, pct) + '%"></span></div>' +
            '<span class="text-xs text-gray-400 w-8 ltr:text-right rtl:text-left tabular-nums">' + pct + '%</span>' +
          '</div>' +
        '</td>' +
      '</tr>';
    }).join('');
  }

  function paint(data) {
    var byDay = Array.isArray(data.by_day) ? data.by_day : [];
    var byFeature = Array.isArray(data.by_feature) ? data.by_feature : [];
    var byCompany = Array.isArray(data.by_company) ? data.by_company : [];

    // Aggregates (prefer explicit totals if present, else derive).
    var totalTokens = data.total_tokens != null ? num(data.total_tokens)
      : byDay.reduce(function (s, d) { return s + num(d.tokens); }, 0)
        || byFeature.reduce(function (s, f) { return s + num(f.tokens); }, 0);
    var totalCost = data.total_cost != null ? num(data.total_cost)
      : byDay.reduce(function (s, d) { return s + num(d.cost); }, 0)
        || byFeature.reduce(function (s, f) { return s + num(f.cost); }, 0);
    var totalCalls = data.total_calls != null ? num(data.total_calls)
      : byFeature.reduce(function (s, f) { return s + num(f.calls); }, 0);

    document.getElementById('sum-tokens').textContent = fmt(totalTokens);
    document.getElementById('sum-tokens-sub').textContent = totalTokens.toLocaleString() + ' tokens';
    document.getElementById('sum-cost').textContent = money(totalCost);
    var costSub = totalTokens > 0 ? (money(totalCost / totalTokens * 1000) + ' / 1k tok') : 'no spend';
    document.getElementById('sum-cost-sub').textContent = costSub;
    document.getElementById('sum-calls').textContent = totalCalls.toLocaleString();
    var avg = totalCalls > 0 ? Math.round(totalTokens / totalCalls) : 0;
    document.getElementById('sum-calls-sub').textContent = avg.toLocaleString() + ' tokens / call';

    // Token usage over time
    var timePoints = byDay.map(function (d) { return { label: shortDay(d.day || d.date), value: num(d.tokens) }; });
    drawAreaChart(document.getElementById('chart-tokens-time'), timePoints, { color: VIOLET, fill: 'rgba(124,58,237,0.20)' });

    // Cost by feature
    var fLabels = byFeature.map(function (f) { return prettyFeature(f.feature); });
    var fCosts = byFeature.map(function (f) { return num(f.cost); });
    drawBarChart(document.getElementById('chart-cost-feature'), fLabels, fCosts, { color: AMBER, color2: '#F59E0B', yfmt: money });

    renderFeatureRanking(byFeature);
    renderCompanyTable(byCompany);
  }

  function showError(msg) {
    var box = document.getElementById('ai-error');
    if (box) { document.getElementById('ai-error-text').textContent = msg; box.classList.remove('hidden'); }
  }
  function hideError() { var b = document.getElementById('ai-error'); if (b) b.classList.add('hidden'); }

  async function load(period) {
    currentPeriod = period || currentPeriod;
    setActivePeriod(currentPeriod);
    hideError();
    try {
      var data = await AR.Api.get('/admin/ai-analytics?period=' + encodeURIComponent(currentPeriod));
      paint(data || {});
    } catch (err) {
      showError((err && err.message) || 'Failed to load AI analytics.');
      if (AR.Toast) AR.Toast.error((err && err.message) || 'Failed to load AI analytics.');
      paint({}); // render empty charts/tables instead of broken UI
    }
  }

  var resizeTimer = null;
  function init() {
    document.querySelectorAll('.period-btn').forEach(function (b) {
      b.addEventListener('click', function () { load(b.getAttribute('data-period')); });
    });
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () { load(currentPeriod); }, 200);
    });
    load('30d');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
</script>
