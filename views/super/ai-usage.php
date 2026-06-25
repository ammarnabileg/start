<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6">

    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">AI Usage Analytics</h2>
        <p class="text-gray-500 mt-1">Monitor token usage and costs across all companies.</p>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <div class="flex flex-col sm:flex-row gap-3 items-end">
            <div>
                <label class="block text-xs text-gray-500 mb-1.5 font-medium">From</label>
                <input type="date" id="date-from"
                    class="px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1.5 font-medium">To</label>
                <input type="date" id="date-to"
                    class="px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <button onclick="loadUsage()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">
                Apply Filter
            </button>
            <button onclick="setPreset('7d')" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50">Last 7 days</button>
            <button onclick="setPreset('30d')" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50">Last 30 days</button>
        </div>
    </div>

    <!-- Platform Totals -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5" id="totals-grid">
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4">
            <div class="w-11 h-11 bg-indigo-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div><p class="text-xs text-gray-500 font-medium">Total Tokens</p><p class="text-2xl font-bold text-gray-900" id="total-tokens">—</p></div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4">
            <div class="w-11 h-11 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div><p class="text-xs text-gray-500 font-medium">Est. Cost (USD)</p><p class="text-2xl font-bold text-gray-900" id="total-cost">—</p></div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4">
            <div class="w-11 h-11 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.868V15.131a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <div><p class="text-xs text-gray-500 font-medium">Total Interviews</p><p class="text-2xl font-bold text-gray-900" id="total-interviews">—</p></div>
        </div>
    </div>

    <!-- By Company Table -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Usage by Company</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3 text-left font-medium">Company</th>
                        <th class="px-6 py-3 text-right font-medium">Tokens Used</th>
                        <th class="px-6 py-3 text-right font-medium">Est. Cost</th>
                        <th class="px-6 py-3 text-right font-medium">Interviews</th>
                        <th class="px-6 py-3 text-right font-medium">Avg Tokens/Interview</th>
                        <th class="px-6 py-3 text-left font-medium">Usage Bar</th>
                    </tr>
                </thead>
                <tbody id="usage-tbody" class="divide-y divide-gray-100">
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
const fmt = n => Number(n||0).toLocaleString();
const fmtCost = n => `$${(Number(n)||0).toFixed(4)}`;
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// Set default date range (last 30 days)
const today = new Date();
const thirtyDaysAgo = new Date(today); thirtyDaysAgo.setDate(today.getDate() - 30);
document.getElementById('date-to').value   = today.toISOString().split('T')[0];
document.getElementById('date-from').value = thirtyDaysAgo.toISOString().split('T')[0];

function setPreset(preset) {
    const t = new Date();
    const f = new Date(t);
    if (preset === '7d')  f.setDate(t.getDate() - 7);
    if (preset === '30d') f.setDate(t.getDate() - 30);
    document.getElementById('date-to').value   = t.toISOString().split('T')[0];
    document.getElementById('date-from').value = f.toISOString().split('T')[0];
    loadUsage();
}

async function loadUsage() {
    const from = document.getElementById('date-from').value;
    const to   = document.getElementById('date-to').value;
    const params = new URLSearchParams({from, to});

    document.getElementById('usage-tbody').innerHTML = `<tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">Loading…</td></tr>`;

    try {
        const r = await fetch(`/api/v1/super/ai-usage?${params}`, {headers:{'X-CSRF-Token':CSRF}});
        const j = await r.json();
        if (!j.ok) return;

        const d = j.data;
        document.getElementById('total-tokens').textContent     = fmt(d.totals?.tokens);
        document.getElementById('total-cost').textContent       = fmtCost(d.totals?.cost);
        document.getElementById('total-interviews').textContent = fmt(d.totals?.interviews);

        const rows = d.by_company || [];
        if (!rows.length) {
            document.getElementById('usage-tbody').innerHTML = `<tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">No usage data for selected period.</td></tr>`;
            return;
        }

        const maxTokens = Math.max(...rows.map(r => r.tokens || 0), 1);

        document.getElementById('usage-tbody').innerHTML = rows.map(r => {
            const pct = Math.round((r.tokens / maxTokens) * 100);
            const avg = r.interviews ? Math.round(r.tokens / r.interviews) : 0;
            return `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4">
                    <p class="font-medium text-gray-900">${esc(r.company_name)}</p>
                    <p class="text-xs text-gray-400">${esc(r.plan || '')}</p>
                </td>
                <td class="px-6 py-4 text-right text-gray-700 font-medium">${fmt(r.tokens)}</td>
                <td class="px-6 py-4 text-right text-gray-700">${fmtCost(r.cost)}</td>
                <td class="px-6 py-4 text-right text-gray-700">${fmt(r.interviews)}</td>
                <td class="px-6 py-4 text-right text-gray-500 text-xs">${fmt(avg)}</td>
                <td class="px-6 py-4">
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="bg-indigo-500 h-2 rounded-full transition-all" style="width:${pct}%"></div>
                    </div>
                </td>
            </tr>`;
        }).join('');

    } catch(e) {
        document.getElementById('usage-tbody').innerHTML = `<tr><td colspan="6" class="px-6 py-10 text-center text-red-400">Failed to load.</td></tr>`;
    }
}

loadUsage();
</script>
