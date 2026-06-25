<meta name="csrf" content="<?= $req->csrf() ?>">

<?php
$firstName = htmlspecialchars($user['first_name'] ?? 'there');
$lastName  = htmlspecialchars($user['last_name'] ?? '');
?>

<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Welcome back, <?= $firstName ?>!</h2>
            <p class="text-gray-500 mt-1">Here's what's happening with your job search.</p>
        </div>
        <a href="/careers"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            Browse Jobs
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6" id="stats-grid">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Active Applications</p>
                <p class="text-3xl font-bold text-gray-900" id="stat-active">—</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.868V15.131a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Interviews Completed</p>
                <p class="text-3xl font-bold text-gray-900" id="stat-interviews">—</p>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500 font-medium">Pending Offers</p>
                <p class="text-3xl font-bold text-gray-900" id="stat-offers">—</p>
            </div>
        </div>
    </div>

    <!-- My Applications Table -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-900">My Applications</h3>
            <a href="/candidate/applications" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">View all →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3 text-left font-medium">Job Title</th>
                        <th class="px-6 py-3 text-left font-medium">Company</th>
                        <th class="px-6 py-3 text-left font-medium">Status</th>
                        <th class="px-6 py-3 text-left font-medium">Applied</th>
                        <th class="px-6 py-3 text-left font-medium">Action</th>
                    </tr>
                </thead>
                <tbody id="applications-tbody" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                            </svg>
                            Loading applications…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Upcoming Interviews -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Upcoming Interviews</h3>
        </div>
        <div id="interviews-list" class="divide-y divide-gray-100">
            <div class="px-6 py-10 text-center text-gray-400">
                <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Loading interviews…
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;

const stageBadge = (stage) => {
    const map = {
        applied:      'bg-gray-100 text-gray-700',
        ai_screening: 'bg-blue-100 text-blue-700',
        qualified:    'bg-green-100 text-green-700',
        interview:    'bg-purple-100 text-purple-700',
        offer:        'bg-amber-100 text-amber-700',
        disqualified: 'bg-red-100 text-red-700',
    };
    const cls = map[stage] || 'bg-gray-100 text-gray-700';
    const label = stage ? stage.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : 'Unknown';
    return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${cls}">${label}</span>`;
};

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '—';

async function loadDashboard() {
    try {
        const res = await fetch('/api/v1/candidate/dashboard', {
            headers: {'X-CSRF-Token': CSRF}
        });
        const json = await res.json();
        if (!json.ok) return;
        const d = json.data;

        document.getElementById('stat-active').textContent     = d.stats?.active_applications ?? 0;
        document.getElementById('stat-interviews').textContent = d.stats?.interviews_completed ?? 0;
        document.getElementById('stat-offers').textContent     = d.stats?.pending_offers ?? 0;

        // Applications table
        const tbody = document.getElementById('applications-tbody');
        const apps = d.applications || [];
        if (apps.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-10 text-center text-gray-400">No applications yet. <a href="/careers" class="text-indigo-600 hover:underline">Browse jobs →</a></td></tr>`;
        } else {
            tbody.innerHTML = apps.slice(0, 5).map(a => `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 font-medium text-gray-900">${escHtml(a.job_title)}</td>
                    <td class="px-6 py-4 text-gray-600">${escHtml(a.tenant_name)}</td>
                    <td class="px-6 py-4">${stageBadge(a.stage)}</td>
                    <td class="px-6 py-4 text-gray-500">${formatDate(a.applied_at)}</td>
                    <td class="px-6 py-4">
                        <a href="/candidate/applications?id=${a.id}" class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">View details</a>
                    </td>
                </tr>`).join('');
        }

        // Upcoming Interviews
        const list = document.getElementById('interviews-list');
        const interviews = d.upcoming_interviews || [];
        if (interviews.length === 0) {
            list.innerHTML = `<div class="px-6 py-10 text-center text-gray-400">No upcoming interviews scheduled.</div>`;
        } else {
            list.innerHTML = interviews.map(i => `
                <div class="flex items-center gap-4 px-6 py-4">
                    <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.868V15.131a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate">${escHtml(i.job_title)}</p>
                        <p class="text-sm text-gray-500">${escHtml(i.company)} · ${escHtml(i.interviewer_name || 'Interviewer TBD')}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900">${formatDate(i.scheduled_at)}</p>
                        <p class="text-xs text-gray-500">${i.time || ''}</p>
                    </div>
                </div>`).join('');
        }
    } catch (e) {
        console.error(e);
    }
}

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadDashboard();
</script>
