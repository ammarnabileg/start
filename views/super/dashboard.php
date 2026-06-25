<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6">

    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Platform Dashboard</h2>
        <p class="text-gray-500 mt-1">Overview of the RecruitAI platform.</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4" id="stats-grid">
        <?php
        $statCards = [
            ['id'=>'stat-companies',   'label'=>'Total Companies',     'color'=>'indigo', 'icon'=>'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
            ['id'=>'stat-subscriptions','label'=>'Active Subscriptions','color'=>'green',  'icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['id'=>'stat-users',       'label'=>'Total Users',          'color'=>'blue',   'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['id'=>'stat-interviews',  'label'=>'AI Interviews',        'color'=>'purple', 'icon'=>'M15 10l4.553-2.069A1 1 0 0121 8.868V15.131a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
            ['id'=>'stat-tokens',      'label'=>'Tokens Today',         'color'=>'amber',  'icon'=>'M13 10V3L4 14h7v7l9-11h-7z'],
        ];
        $colorMap = ['indigo'=>'bg-indigo-100 text-indigo-600','green'=>'bg-green-100 text-green-600','blue'=>'bg-blue-100 text-blue-600','purple'=>'bg-purple-100 text-purple-600','amber'=>'bg-amber-100 text-amber-600'];
        foreach ($statCards as $c):
        ?>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4">
            <div class="w-11 h-11 rounded-xl <?= $colorMap[$c['color']] ?> flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $c['icon'] ?>"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-gray-500 font-medium truncate"><?= $c['label'] ?></p>
                <p class="text-2xl font-bold text-gray-900" id="<?= $c['id'] ?>">—</p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- Recent Signups Table -->
        <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-900">Recent Company Signups</h3>
                <a href="/super/companies" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">View all →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3 text-left font-medium">Company</th>
                            <th class="px-6 py-3 text-left font-medium">Plan</th>
                            <th class="px-6 py-3 text-left font-medium">Joined</th>
                            <th class="px-6 py-3 text-left font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody id="signups-tbody" class="divide-y divide-gray-100">
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-400">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Health -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <h3 class="text-base font-semibold text-gray-900">System Health</h3>
            <div id="health-container" class="space-y-4">
                <div class="text-sm text-gray-400">Loading health data…</div>
            </div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-gray-900">Subscriptions by Plan</h3>
        </div>
        <div id="plan-chart" class="flex items-end gap-6 h-32">
            <div class="text-sm text-gray-400">Loading chart…</div>
        </div>
        <div id="plan-legend" class="flex gap-6 mt-4 flex-wrap"></div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
const fmt = n => Number(n||0).toLocaleString();
const formatDate = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';

const planBadge = p => ({
    basic:      'bg-gray-100 text-gray-700',
    pro:        'bg-indigo-100 text-indigo-700',
    enterprise: 'bg-purple-100 text-purple-700',
}[p] || 'bg-gray-100 text-gray-600');

const statusBadge = s => s === 'active'
    ? '<span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Active</span>'
    : '<span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">Suspended</span>';

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

async function loadDashboard() {
    try {
        const res = await fetch('/api/v1/super/stats', {headers:{'X-CSRF-Token':CSRF}});
        const json = await res.json();
        if (!json.ok) return;
        const d = json.data;

        document.getElementById('stat-companies').textContent    = fmt(d.total_companies);
        document.getElementById('stat-subscriptions').textContent= fmt(d.active_subscriptions);
        document.getElementById('stat-users').textContent        = fmt(d.total_users);
        document.getElementById('stat-interviews').textContent   = fmt(d.total_ai_interviews);
        document.getElementById('stat-tokens').textContent       = fmt(d.tokens_today);

        // Recent signups
        const tbody = document.getElementById('signups-tbody');
        const companies = d.recent_signups || [];
        if (!companies.length) {
            tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-8 text-center text-gray-400">No recent signups.</td></tr>`;
        } else {
            tbody.innerHTML = companies.map(c => `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-3.5">
                        <p class="font-medium text-gray-900">${escHtml(c.company_name)}</p>
                        <p class="text-xs text-gray-400">${escHtml(c.slug)}</p>
                    </td>
                    <td class="px-6 py-3.5"><span class="text-xs px-2.5 py-1 rounded-full font-medium ${planBadge(c.plan)}">${escHtml(c.plan)}</span></td>
                    <td class="px-6 py-3.5 text-gray-500 text-xs">${formatDate(c.created_at)}</td>
                    <td class="px-6 py-3.5">${statusBadge(c.status)}</td>
                </tr>`).join('');
        }

        // Health
        const health = d.system_health || {};
        const healthContainer = document.getElementById('health-container');
        const metrics = [
            { label:'Database', value: health.db_status || 'unknown', ok: health.db_status === 'ok' },
            { label:'Storage Used', value: health.storage_used || '—', ok: true },
            { label:'Storage Available', value: health.storage_available || '—', ok: true },
            { label:'Queue Size', value: fmt(health.queue_size || 0), ok: (health.queue_size || 0) < 1000 },
        ];
        healthContainer.innerHTML = metrics.map(m => `
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600">${m.label}</span>
                <span class="flex items-center gap-2 text-sm font-medium ${m.ok ? 'text-green-600' : 'text-red-600'}">
                    <span class="w-2 h-2 rounded-full ${m.ok ? 'bg-green-500' : 'bg-red-500'}"></span>
                    ${escHtml(String(m.value))}
                </span>
            </div>`).join('');

        // Plan chart
        const plans = d.plan_breakdown || {basic: 0, pro: 0, enterprise: 0};
        const total = Object.values(plans).reduce((a,b) => a+b, 0) || 1;
        const planColors = { basic:'bg-gray-400', pro:'bg-indigo-500', enterprise:'bg-purple-500' };
        const planLabels = { basic:'Basic', pro:'Pro', enterprise:'Enterprise' };

        document.getElementById('plan-chart').innerHTML = Object.entries(plans).map(([plan, count]) => {
            const pct = Math.max(4, Math.round((count / total) * 100));
            return `<div class="flex flex-col items-center gap-2 flex-1">
                <span class="text-sm font-bold text-gray-900">${fmt(count)}</span>
                <div class="w-full ${planColors[plan] || 'bg-gray-400'} rounded-t-lg transition-all" style="height:${pct}%"></div>
                <span class="text-xs text-gray-500 capitalize">${planLabels[plan] || plan}</span>
            </div>`;
        }).join('');

    } catch(e) {
        console.error('Dashboard load error:', e);
    }
}

loadDashboard();
</script>
