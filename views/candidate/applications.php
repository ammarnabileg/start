<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6">

    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">My Applications</h2>
        <p class="text-gray-500 mt-1">Track your application progress across all jobs.</p>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="px-6 py-3 text-left font-medium">Job</th>
                        <th class="px-6 py-3 text-left font-medium">Company</th>
                        <th class="px-6 py-3 text-left font-medium">Applied</th>
                        <th class="px-6 py-3 text-left font-medium">Stage</th>
                        <th class="px-6 py-3 text-left font-medium">AI Interview</th>
                        <th class="px-6 py-3 text-left font-medium">Action</th>
                    </tr>
                </thead>
                <tbody id="apps-tbody" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-300 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Loading…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Application Detail Modal -->
<div id="detail-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <div>
                <h3 class="text-lg font-semibold text-gray-900" id="detail-title">Application Details</h3>
                <p class="text-sm text-gray-500" id="detail-company"></p>
            </div>
            <button onclick="closeModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4">Application Timeline</h4>
            <div id="timeline" class="space-y-0"></div>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
const formatDate = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';

const stageCfg = {
    applied:        { cls:'bg-gray-100 text-gray-700',   icon:'M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2',     dot:'bg-gray-400' },
    ai_screening:   { cls:'bg-blue-100 text-blue-700',   icon:'M15 10l4.553-2.069A1 1 0 0121 8.868V15.131a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z', dot:'bg-blue-400' },
    qualified:      { cls:'bg-green-100 text-green-700', icon:'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',                                                              dot:'bg-green-500' },
    interview:      { cls:'bg-purple-100 text-purple-700',icon:'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',dot:'bg-purple-500'},
    offer:          { cls:'bg-amber-100 text-amber-700', icon:'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', dot:'bg-amber-500' },
    disqualified:   { cls:'bg-red-100 text-red-700',    icon:'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',                                    dot:'bg-red-400' },
    hired:          { cls:'bg-emerald-100 text-emerald-700',icon:'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',dot:'bg-emerald-500'},
};

function stageBadge(stage) {
    const cfg = stageCfg[stage] || stageCfg.applied;
    const label = stage ? stage.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()) : 'Unknown';
    return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium ${cfg.cls}">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${cfg.icon}"/></svg>
        ${label}</span>`;
}

const aiStatusBadge = s => ({
    pending:   '<span class="text-xs text-gray-500">Not started</span>',
    in_progress:'<span class="text-xs text-blue-600 font-medium">In progress</span>',
    completed: '<span class="text-xs text-green-600 font-medium">Completed</span>',
    skipped:   '<span class="text-xs text-gray-400">Skipped</span>',
}[s] || '<span class="text-xs text-gray-400">—</span>');

let appsData = [];

async function loadApplications() {
    try {
        const res = await fetch('/api/v1/candidate/applications', {headers:{'X-CSRF-Token':CSRF}});
        const json = await res.json();
        appsData = json.data || [];
        renderTable(appsData);
    } catch(e) {
        document.getElementById('apps-tbody').innerHTML = `<tr><td colspan="6" class="px-6 py-10 text-center text-red-400">Failed to load. Please refresh.</td></tr>`;
    }
}

function renderTable(apps) {
    const tbody = document.getElementById('apps-tbody');
    if (apps.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400"><p class="font-medium">No applications yet</p><a href="/candidate/jobs" class="text-indigo-600 hover:underline text-sm">Browse jobs →</a></td></tr>`;
        return;
    }
    tbody.innerHTML = apps.map((a, idx) => `
        <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="openDetail(${idx})">
            <td class="px-6 py-4 font-medium text-gray-900">${escHtml(a.job_title)}</td>
            <td class="px-6 py-4 text-gray-600">${escHtml(a.tenant_name)}</td>
            <td class="px-6 py-4 text-gray-500">${formatDate(a.applied_at)}</td>
            <td class="px-6 py-4">${stageBadge(a.stage)}</td>
            <td class="px-6 py-4">${aiStatusBadge(a.ai_interview_status)}</td>
            <td class="px-6 py-4">
                <button class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">View →</button>
            </td>
        </tr>`).join('');
}

function openDetail(idx) {
    const a = appsData[idx];
    document.getElementById('detail-title').textContent = a.job_title;
    document.getElementById('detail-company').textContent = a.tenant_name;

    const stages = a.timeline || [
        {stage:'applied', label:'Application Submitted', date: a.applied_at, note:'Your application was received.'},
        ...(a.ai_interview_status !== 'pending' ? [{stage:'ai_screening', label:'AI Screening', date: a.ai_interview_date, note:'AI interview '+(a.ai_interview_status === 'completed' ? 'completed' : 'in progress')+'.'}] : []),
        ...(a.stage === 'qualified' || a.stage === 'interview' || a.stage === 'offer' || a.stage === 'hired' ? [{stage:'qualified', label:'Application Qualified', date: a.qualified_at}] : []),
        ...(a.stage === 'interview' ? [{stage:'interview', label:'Interview Scheduled', date: a.interview_date, note: a.interview_note}] : []),
        ...(a.stage === 'offer' ? [{stage:'offer', label:'Offer Extended', date: a.offer_date}] : []),
        ...(a.stage === 'hired' ? [{stage:'hired', label:'Hired!', date: a.hired_at}] : []),
        ...(a.stage === 'disqualified' ? [{stage:'disqualified', label:'Not Selected', date: a.disqualified_at, note: a.disqualified_reason}] : []),
    ];

    document.getElementById('timeline').innerHTML = stages.map((s, i) => {
        const cfg = stageCfg[s.stage] || stageCfg.applied;
        const isLast = i === stages.length - 1;
        return `<div class="flex gap-4">
            <div class="flex flex-col items-center">
                <div class="w-8 h-8 rounded-full ${cfg.dot} flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${cfg.icon}"/></svg>
                </div>
                ${!isLast ? `<div class="w-0.5 bg-gray-200 flex-1 my-1"></div>` : ''}
            </div>
            <div class="pb-6 min-w-0">
                <p class="font-medium text-gray-900 text-sm">${escHtml(s.label)}</p>
                <p class="text-xs text-gray-500 mt-0.5">${formatDate(s.date)}</p>
                ${s.note ? `<p class="text-sm text-gray-600 mt-1">${escHtml(s.note)}</p>` : ''}
            </div>
        </div>`;
    }).join('');

    document.getElementById('detail-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('detail-modal').classList.add('hidden');
}

document.getElementById('detail-modal').addEventListener('click', e => { if(e.target===e.currentTarget) closeModal(); });

loadApplications();
</script>
