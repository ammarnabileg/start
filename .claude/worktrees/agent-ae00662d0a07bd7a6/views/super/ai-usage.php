<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="min-h-screen bg-gray-50 p-6">

  <!-- Page Header -->
  <div class="mb-6 flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">AI Usage Analytics</h1>
      <p class="mt-1 text-sm text-gray-500">Platform-wide AI token consumption and cost breakdown by company.</p>
    </div>
    <button
      id="btn-export-csv"
      class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
      disabled
    >
      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 12v4m0 0l-3-3m3 3l3-3M12 4v8"/>
      </svg>
      Export CSV
    </button>
  </div>

  <!-- Date Range Filter Bar -->
  <div class="mb-6 flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <div>
      <label for="filter-from" class="block text-xs font-medium text-gray-600 mb-1">From</label>
      <input
        type="date"
        id="filter-from"
        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
      />
    </div>
    <div>
      <label for="filter-to" class="block text-xs font-medium text-gray-600 mb-1">To</label>
      <input
        type="date"
        id="filter-to"
        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-800 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
      />
    </div>
    <button
      id="btn-apply"
      class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
    >
      Apply
    </button>
    <div id="filter-status" class="ml-auto self-center hidden">
      <span class="inline-flex items-center gap-1 text-xs text-gray-400">
        <svg class="h-3 w-3 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
        Loading&hellip;
      </span>
    </div>
  </div>

  <!-- Platform Totals: Stat Cards -->
  <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">

    <!-- Total Tokens -->
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-gray-500">Total Tokens Used</p>
        <span class="rounded-full bg-indigo-50 p-2">
          <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-4M13 3h4a2 2 0 012 2v4M9 12h6m-3-3v6"/>
          </svg>
        </span>
      </div>
      <p id="stat-tokens" class="mt-3 text-3xl font-bold text-gray-900">&#8212;</p>
      <p class="mt-1 text-xs text-gray-400">across all companies</p>
    </div>

    <!-- Estimated Cost -->
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-gray-500">Estimated Cost</p>
        <span class="rounded-full bg-emerald-50 p-2">
          <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </span>
      </div>
      <p id="stat-cost" class="mt-3 text-3xl font-bold text-gray-900">&#8212;</p>
      <p class="mt-1 text-xs text-gray-400">@ $0.002 per 1k tokens</p>
    </div>

    <!-- Total Interviews -->
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
      <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-gray-500">Total Interviews Conducted</p>
        <span class="rounded-full bg-amber-50 p-2">
          <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-5.197-3.796M9 20H4v-2a4 4 0 015.197-3.796M15 7a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </span>
      </div>
      <p id="stat-interviews" class="mt-3 text-3xl font-bold text-gray-900">&#8212;</p>
      <p class="mt-1 text-xs text-gray-400">platform total</p>
    </div>

  </div>

  <!-- Token Usage Bar Chart (top 10) -->
  <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <h2 class="mb-4 text-base font-semibold text-gray-800">Token Usage by Company (Top 10)</h2>

    <div id="chart-loading" class="flex items-center justify-center py-10 text-sm text-gray-400">
      <svg class="mr-2 h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
      </svg>
      Loading chart&hellip;
    </div>

    <div id="chart-container" class="hidden space-y-3"></div>

    <div id="chart-empty" class="hidden py-10 text-center text-sm text-gray-400">
      No data available for the selected period.
    </div>
  </div>

  <!-- By-Company Breakdown Table -->
  <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
      <h2 class="text-base font-semibold text-gray-800">Company Breakdown</h2>
      <span id="table-count" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600"></span>
    </div>

    <!-- Skeleton loader -->
    <div id="table-loading" class="p-6 space-y-3">
      <?php for ($i = 0; $i < 6; $i++): ?>
      <div class="flex gap-4 animate-pulse">
        <div class="h-4 flex-1 rounded bg-gray-100"></div>
        <div class="h-4 w-20 rounded bg-gray-100"></div>
        <div class="h-4 w-24 rounded bg-gray-100"></div>
        <div class="h-4 w-20 rounded bg-gray-100"></div>
        <div class="h-4 w-20 rounded bg-gray-100"></div>
        <div class="h-4 w-28 rounded bg-gray-100"></div>
        <div class="h-4 w-32 rounded bg-gray-100"></div>
      </div>
      <?php endfor; ?>
    </div>

    <div id="table-container" class="hidden overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-5 py-3 text-left">Company</th>
            <th class="px-5 py-3 text-left">Plan</th>
            <th class="px-5 py-3 text-right">Total Tokens</th>
            <th class="px-5 py-3 text-right">Cost Estimate</th>
            <th class="px-5 py-3 text-right">Interviews</th>
            <th class="px-5 py-3 text-right">Avg Tokens / Interview</th>
            <th class="px-5 py-3 text-left min-w-[140px]">Relative Usage</th>
          </tr>
        </thead>
        <tbody id="table-body" class="divide-y divide-gray-100 text-gray-700"></tbody>
      </table>
    </div>

    <div id="table-empty" class="hidden py-16 text-center text-sm text-gray-400">
      <svg class="mx-auto mb-3 h-8 w-8 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h18"/>
      </svg>
      No usage data found for the selected date range.
    </div>

    <div id="table-error" class="hidden px-5 py-6 rounded-b-xl bg-red-50 border-t border-red-100">
      <p class="text-sm font-medium text-red-700">
        Failed to load data: <span id="table-error-msg" class="font-normal"></span>
      </p>
    </div>
  </div>

</div><!-- /page wrapper -->

<script>
(function () {
  'use strict';

  const COST_PER_1K = 0.002;
  const CSRF = document.querySelector('meta[name=csrf]').content;

  // Plan display config
  const PLAN_META = {
    free:       { badge: 'bg-gray-100 text-gray-600',     bar: '#94a3b8', label: 'Free'       },
    starter:    { badge: 'bg-blue-100 text-blue-700',     bar: '#60a5fa', label: 'Starter'    },
    pro:        { badge: 'bg-indigo-100 text-indigo-700', bar: '#818cf8', label: 'Pro'        },
    business:   { badge: 'bg-purple-100 text-purple-700', bar: '#a78bfa', label: 'Business'   },
    enterprise: { badge: 'bg-amber-100 text-amber-700',   bar: '#f59e0b', label: 'Enterprise' },
  };

  function getPlanMeta(plan) {
    const key = String(plan || 'free').toLowerCase();
    return PLAN_META[key] || { badge: 'bg-gray-100 text-gray-600', bar: '#94a3b8', label: plan || 'Free' };
  }

  // Formatting helpers
  function fmtTokens(n) {
    n = n || 0;
    if (n >= 1000000) return (n / 1000000).toFixed(2) + 'M';
    if (n >= 1000)    return (n / 1000).toFixed(1) + 'K';
    return n.toLocaleString();
  }

  function fmtCost(tokens) {
    return '$' + ((tokens / 1000) * COST_PER_1K).toFixed(2);
  }

  function toISODate(d) {
    return d.toISOString().slice(0, 10);
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // Default date range: last 30 days
  const today  = new Date();
  const d30ago = new Date();
  d30ago.setDate(today.getDate() - 30);

  const inputFrom = document.getElementById('filter-from');
  const inputTo   = document.getElementById('filter-to');
  inputFrom.value = toISODate(d30ago);
  inputTo.value   = toISODate(today);

  // DOM refs
  const statTokens     = document.getElementById('stat-tokens');
  const statCost       = document.getElementById('stat-cost');
  const statInterviews = document.getElementById('stat-interviews');
  const filterStatus   = document.getElementById('filter-status');
  const chartLoading   = document.getElementById('chart-loading');
  const chartContainer = document.getElementById('chart-container');
  const chartEmpty     = document.getElementById('chart-empty');
  const tableLoading   = document.getElementById('table-loading');
  const tableContainer = document.getElementById('table-container');
  const tableBody      = document.getElementById('table-body');
  const tableEmpty     = document.getElementById('table-empty');
  const tableError     = document.getElementById('table-error');
  const tableErrorMsg  = document.getElementById('table-error-msg');
  const tableCount     = document.getElementById('table-count');
  const btnApply       = document.getElementById('btn-apply');
  const btnExport      = document.getElementById('btn-export-csv');

  let cachedRows = [];

  // Data loading
  async function loadData() {
    const from = inputFrom.value;
    const to   = inputTo.value;
    if (!from || !to) return;

    filterStatus.classList.remove('hidden');
    btnApply.disabled  = true;
    btnExport.disabled = true;

    statTokens.textContent     = '—';
    statCost.textContent       = '—';
    statInterviews.textContent = '—';

    chartLoading.classList.remove('hidden');
    chartContainer.classList.add('hidden');
    chartEmpty.classList.add('hidden');
    chartContainer.innerHTML = '';

    tableLoading.classList.remove('hidden');
    tableContainer.classList.add('hidden');
    tableEmpty.classList.add('hidden');
    tableError.classList.add('hidden');
    tableBody.innerHTML    = '';
    tableCount.textContent = '';

    try {
      const params = new URLSearchParams({ from_date: from, to_date: to });
      const res    = await fetch('/api/v1/super/ai-usage?' + params.toString(), {
        headers: {
          'Accept':       'application/json',
          'X-CSRF-Token': CSRF,
        },
      });

      if (!res.ok) throw new Error('HTTP ' + res.status + ' ' + res.statusText);

      const json = await res.json();
      if (!json.ok) throw new Error(json.message || 'Server returned an error.');

      cachedRows = Array.isArray(json.data) ? json.data : [];
      renderAll(cachedRows);

    } catch (err) {
      chartLoading.classList.add('hidden');
      tableLoading.classList.add('hidden');
      tableError.classList.remove('hidden');
      tableErrorMsg.textContent = err.message || 'Unknown error.';
      cachedRows = [];
    } finally {
      filterStatus.classList.add('hidden');
      btnApply.disabled  = false;
      btnExport.disabled = cachedRows.length === 0;
    }
  }

  function renderAll(rows) {
    renderTotals(rows);
    renderChart(rows);
    renderTable(rows);
  }

  // Stat cards
  function renderTotals(rows) {
    const totalTokens     = rows.reduce((s, r) => s + (r.total_tokens    || 0), 0);
    const totalInterviews = rows.reduce((s, r) => s + (r.interview_count || 0), 0);
    statTokens.textContent     = totalTokens.toLocaleString();
    statCost.textContent       = fmtCost(totalTokens);
    statInterviews.textContent = totalInterviews.toLocaleString();
  }

  // Bar chart (top 10)
  function renderChart(rows) {
    chartLoading.classList.add('hidden');

    const top10 = [...rows]
      .sort((a, b) => (b.total_tokens || 0) - (a.total_tokens || 0))
      .slice(0, 10);

    if (top10.length === 0) {
      chartEmpty.classList.remove('hidden');
      return;
    }

    const maxTokens = top10[0].total_tokens || 1;

    // Plan legend
    const legendPlans = [...new Set(top10.map(r => String(r.plan || 'free').toLowerCase()))];
    const legend = document.createElement('div');
    legend.className = 'mb-4 flex flex-wrap gap-3';
    legendPlans.forEach(function(plan) {
      const meta = getPlanMeta(plan);
      const item = document.createElement('span');
      item.className = 'inline-flex items-center gap-1.5 text-xs text-gray-600';
      item.innerHTML = '<span class="inline-block h-2.5 w-2.5 rounded-full" style="background:' + meta.bar + '"></span>' + escHtml(meta.label);
      legend.appendChild(item);
    });
    chartContainer.appendChild(legend);

    top10.forEach(function(row) {
      const tokens = row.total_tokens || 0;
      const pct    = Math.max(1, Math.round((tokens / maxTokens) * 100));
      const meta   = getPlanMeta(row.plan);
      const name   = row.company_name || 'Unknown';

      const wrap = document.createElement('div');
      wrap.className = 'flex items-center gap-3';
      wrap.innerHTML =
        '<div class="w-40 shrink-0 truncate text-right text-xs font-medium text-gray-600" title="' + escHtml(name) + '">' + escHtml(name) + '</div>' +
        '<div class="relative flex-1 h-6 rounded-full bg-gray-100 overflow-hidden">' +
          '<div class="h-6 rounded-full transition-all duration-700 ease-out" style="width:' + pct + '%; background-color:' + meta.bar + ';"></div>' +
        '</div>' +
        '<div class="w-20 shrink-0 text-right text-xs tabular-nums text-gray-500">' + fmtTokens(tokens) + '</div>';
      chartContainer.appendChild(wrap);
    });

    chartContainer.classList.remove('hidden');
  }

  // Table
  function renderTable(rows) {
    tableLoading.classList.add('hidden');

    if (rows.length === 0) {
      tableEmpty.classList.remove('hidden');
      return;
    }

    tableCount.textContent = rows.length + (rows.length === 1 ? ' company' : ' companies');

    const maxTokens = Math.max.apply(null, rows.map(function(r) { return r.total_tokens || 0; }).concat([1]));
    const sorted    = [...rows].sort(function(a, b) { return (b.total_tokens || 0) - (a.total_tokens || 0); });

    sorted.forEach(function(row, idx) {
      const tokens     = row.total_tokens    || 0;
      const interviews = row.interview_count || 0;
      const avg        = interviews > 0 ? Math.round(tokens / interviews) : 0;
      const pct        = Math.max(1, Math.round((tokens / maxTokens) * 100));
      const meta       = getPlanMeta(row.plan);
      const name       = row.company_name || 'Unknown';

      const tr = document.createElement('tr');
      tr.className = 'hover:bg-gray-50 transition-colors duration-100';
      tr.innerHTML =
        '<td class="px-5 py-3 font-medium text-gray-800 whitespace-nowrap">' +
          '<span class="mr-2 text-xs text-gray-400 tabular-nums">' + (idx + 1) + '.</span>' + escHtml(name) +
        '</td>' +
        '<td class="px-5 py-3 whitespace-nowrap">' +
          '<span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ' + meta.badge + '">' + escHtml(meta.label) + '</span>' +
        '</td>' +
        '<td class="px-5 py-3 text-right tabular-nums text-gray-700 whitespace-nowrap">' + tokens.toLocaleString() + '</td>' +
        '<td class="px-5 py-3 text-right tabular-nums font-medium text-gray-800 whitespace-nowrap">' + fmtCost(tokens) + '</td>' +
        '<td class="px-5 py-3 text-right tabular-nums text-gray-700 whitespace-nowrap">' + interviews.toLocaleString() + '</td>' +
        '<td class="px-5 py-3 text-right tabular-nums text-gray-500 whitespace-nowrap">' + (avg > 0 ? avg.toLocaleString() : '<span class="text-gray-300">—</span>') + '</td>' +
        '<td class="px-5 py-3 min-w-[140px]">' +
          '<div class="h-2 w-full rounded-full bg-gray-100 overflow-hidden">' +
            '<div class="h-2 rounded-full" style="width:' + pct + '%; background-color:' + meta.bar + ';"></div>' +
          '</div>' +
          '<span class="text-[10px] text-gray-400">' + pct + '%</span>' +
        '</td>';
      tableBody.appendChild(tr);
    });

    tableContainer.classList.remove('hidden');
  }

  // CSV export
  function exportCSV() {
    if (cachedRows.length === 0) return;

    const headers = ['Rank', 'Company', 'Plan', 'Total Tokens', 'Cost Estimate (USD)', 'Interview Count', 'Avg Tokens per Interview'];
    const sorted  = [...cachedRows].sort(function(a, b) { return (b.total_tokens || 0) - (a.total_tokens || 0); });

    const dataRows = sorted.map(function(row, idx) {
      const tokens     = row.total_tokens    || 0;
      const interviews = row.interview_count || 0;
      const avg        = interviews > 0 ? Math.round(tokens / interviews) : 0;
      const cost       = ((tokens / 1000) * COST_PER_1K).toFixed(4);
      return [idx + 1, csvCell(row.company_name || ''), csvCell(row.plan || 'free'), tokens, cost, interviews, avg].join(',');
    });

    const csv  = [headers.join(',')].concat(dataRows).join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'ai-usage-' + inputFrom.value + '-to-' + inputTo.value + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
  }

  function csvCell(val) {
    var s = String(val);
    if (/[,"\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
    return s;
  }

  // Event wiring
  btnApply.addEventListener('click', loadData);
  btnExport.addEventListener('click', exportCSV);
  inputFrom.addEventListener('keydown', function(e) { if (e.key === 'Enter') loadData(); });
  inputTo.addEventListener('keydown',   function(e) { if (e.key === 'Enter') loadData(); });

  // Initial fetch
  loadData();

}());
</script>
