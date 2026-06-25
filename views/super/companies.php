<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Companies</h2>
            <p class="text-gray-500 mt-1">Manage all platform tenants.</p>
        </div>
        <button onclick="openCreateModal()"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Company
        </button>
    </div>

    <!-- Search/Filter -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="search" placeholder="Search by name or slug…"
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <select id="filter-plan" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Plans</option>
                <option value="basic">Basic</option>
                <option value="pro">Pro</option>
                <option value="enterprise">Enterprise</option>
            </select>
            <select id="filter-status" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="px-6 py-3 text-left font-medium">Company</th>
                        <th class="px-6 py-3 text-left font-medium">Plan</th>
                        <th class="px-6 py-3 text-left font-medium">Status</th>
                        <th class="px-6 py-3 text-left font-medium">Users</th>
                        <th class="px-6 py-3 text-left font-medium">Jobs</th>
                        <th class="px-6 py-3 text-left font-medium">Joined</th>
                        <th class="px-6 py-3 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody id="companies-tbody" class="divide-y divide-gray-100">
                    <tr><td colspan="7" class="px-6 py-10 text-center text-gray-400">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Company Modal -->
<div id="create-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Create Company</h3>
            <button onclick="closeModal('create-modal')" class="p-1.5 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Company Name</label>
                <input type="text" id="new-company-name" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Acme Corp">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Slug</label>
                <input type="text" id="new-company-slug" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" placeholder="acme-corp">
                <p class="text-xs text-gray-400 mt-1">Used in public URLs. Lowercase, hyphens only.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Plan</label>
                <select id="new-company-plan" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="basic">Basic</option>
                    <option value="pro" selected>Pro</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </div>
            <div class="border-t border-gray-100 pt-4">
                <p class="text-sm font-medium text-gray-700 mb-3">Owner Account</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">First Name</label>
                        <input type="text" id="new-owner-first" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Last Name</label>
                        <input type="text" id="new-owner-last" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-xs text-gray-500 mb-1">Email</label>
                    <input type="email" id="new-owner-email" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="owner@company.com">
                </div>
            </div>
            <p id="create-error" class="hidden text-sm text-red-500"></p>
            <button onclick="createCompany()" id="create-btn"
                class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors mt-2">
                Create Company
            </button>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div id="plan-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Edit Plan</h3>
            <button onclick="closeModal('plan-modal')" class="p-1.5 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <input type="hidden" id="plan-company-id">
            <p class="text-sm text-gray-600">Changing plan for: <span id="plan-company-name" class="font-semibold text-gray-900"></span></p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">New Plan</label>
                <select id="plan-select" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="basic">Basic</option>
                    <option value="pro">Pro</option>
                    <option value="enterprise">Enterprise</option>
                </select>
            </div>
            <p id="plan-error" class="hidden text-sm text-red-500"></p>
            <button onclick="savePlan()" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Save Plan
            </button>
        </div>
    </div>
</div>

<!-- Company Detail Modal -->
<div id="detail-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-900" id="detail-name">Company Details</h3>
            <button onclick="closeModal('detail-modal')" class="p-1.5 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6" id="detail-content">
            <div class="text-center text-gray-400">Loading…</div>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
let companies = [];

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
const fmt = n => Number(n||0).toLocaleString();
const formatDate = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
const planBadge = p => ({
    basic:'bg-gray-100 text-gray-700',pro:'bg-indigo-100 text-indigo-700',enterprise:'bg-purple-100 text-purple-700'
}[p] || 'bg-gray-100 text-gray-700');

async function loadCompanies() {
    const q      = document.getElementById('search').value;
    const plan   = document.getElementById('filter-plan').value;
    const status = document.getElementById('filter-status').value;
    const params = new URLSearchParams({q, plan, status});

    try {
        const r = await fetch(`/api/v1/super/companies?${params}`, {headers:{'X-CSRF-Token':CSRF}});
        const j = await r.json();
        companies = j.data || [];
        renderTable(companies);
    } catch(e) {
        document.getElementById('companies-tbody').innerHTML = `<tr><td colspan="7" class="px-6 py-10 text-center text-red-400">Failed to load.</td></tr>`;
    }
}

function renderTable(rows) {
    const tbody = document.getElementById('companies-tbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="px-6 py-10 text-center text-gray-400">No companies found.</td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map((c, i) => `
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4">
                <button onclick="openDetailModal(${i})" class="text-left">
                    <p class="font-medium text-indigo-600 hover:text-indigo-700">${esc(c.company_name)}</p>
                    <p class="text-xs text-gray-400 font-mono">${esc(c.slug)}</p>
                </button>
            </td>
            <td class="px-6 py-4"><span class="text-xs px-2.5 py-1 rounded-full font-medium capitalize ${planBadge(c.plan)}">${esc(c.plan)}</span></td>
            <td class="px-6 py-4">
                ${c.status === 'active'
                    ? '<span class="text-xs bg-green-100 text-green-700 px-2.5 py-1 rounded-full font-medium">Active</span>'
                    : '<span class="text-xs bg-red-100 text-red-700 px-2.5 py-1 rounded-full font-medium">Suspended</span>'}
            </td>
            <td class="px-6 py-4 text-gray-600">${fmt(c.users_count)}</td>
            <td class="px-6 py-4 text-gray-600">${fmt(c.jobs_count)}</td>
            <td class="px-6 py-4 text-gray-500 text-xs">${formatDate(c.created_at)}</td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <button onclick="openPlanModal(${i})" class="text-xs px-2.5 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 rounded-lg font-medium transition-colors">Plan</button>
                    <button onclick="toggleSuspend(${i})" class="text-xs px-2.5 py-1.5 ${c.status === 'active' ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100'} rounded-lg font-medium transition-colors">
                        ${c.status === 'active' ? 'Suspend' : 'Activate'}
                    </button>
                </div>
            </td>
        </tr>`).join('');
}

function openCreateModal() {
    ['new-company-name','new-company-slug','new-owner-first','new-owner-last','new-owner-email'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('new-company-plan').value = 'pro';
    document.getElementById('create-error').classList.add('hidden');
    document.getElementById('create-modal').classList.remove('hidden');
}

async function createCompany() {
    const btn = document.getElementById('create-btn');
    const err = document.getElementById('create-error');
    err.classList.add('hidden');
    btn.disabled = true; btn.textContent = 'Creating…';

    try {
        const r = await fetch('/api/v1/super/companies', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
            body: JSON.stringify({
                company_name: document.getElementById('new-company-name').value,
                slug:         document.getElementById('new-company-slug').value,
                plan:         document.getElementById('new-company-plan').value,
                owner_first_name: document.getElementById('new-owner-first').value,
                owner_last_name:  document.getElementById('new-owner-last').value,
                owner_email:  document.getElementById('new-owner-email').value,
            }),
        });
        const j = await r.json();
        if (j.ok) { closeModal('create-modal'); loadCompanies(); }
        else { err.textContent = j.message || 'Failed.'; err.classList.remove('hidden'); }
    } catch(e) { err.textContent = 'Network error.'; err.classList.remove('hidden'); }
    btn.disabled = false; btn.textContent = 'Create Company';
}

function openPlanModal(idx) {
    const c = companies[idx];
    document.getElementById('plan-company-id').value = c.id;
    document.getElementById('plan-company-name').textContent = c.company_name;
    document.getElementById('plan-select').value = c.plan;
    document.getElementById('plan-error').classList.add('hidden');
    document.getElementById('plan-modal').classList.remove('hidden');
}

async function savePlan() {
    const id = document.getElementById('plan-company-id').value;
    const plan = document.getElementById('plan-select').value;
    const err = document.getElementById('plan-error');
    err.classList.add('hidden');
    try {
        const r = await fetch(`/api/v1/super/companies/${id}/plan`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
            body: JSON.stringify({plan}),
        });
        const j = await r.json();
        if (j.ok) { closeModal('plan-modal'); loadCompanies(); }
        else { err.textContent = j.message || 'Failed.'; err.classList.remove('hidden'); }
    } catch(e) { err.textContent = 'Network error.'; err.classList.remove('hidden'); }
}

async function toggleSuspend(idx) {
    const c = companies[idx];
    const action = c.status === 'active' ? 'suspend' : 'activate';
    if (!confirm(`${action.charAt(0).toUpperCase()+action.slice(1)} "${c.company_name}"?`)) return;
    try {
        const r = await fetch(`/api/v1/super/companies/${c.id}/${action}`, {
            method: 'POST', headers: {'X-CSRF-Token':CSRF}
        });
        const j = await r.json();
        if (j.ok) loadCompanies();
        else alert(j.message || 'Action failed.');
    } catch(e) { alert('Network error.'); }
}

function openDetailModal(idx) {
    const c = companies[idx];
    document.getElementById('detail-name').textContent = c.company_name;
    document.getElementById('detail-content').innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            ${[
                ['Slug', c.slug],['Plan', c.plan],['Status', c.status],
                ['Users', fmt(c.users_count)],['Jobs', fmt(c.jobs_count)],
                ['AI Interviews', fmt(c.ai_interviews_count)],
                ['Tokens Used', fmt(c.tokens_used)],
                ['Joined', formatDate(c.created_at)],
            ].map(([k,v]) => `<div><p class="text-xs text-gray-500 uppercase tracking-wider mb-1">${k}</p><p class="font-semibold text-gray-900">${esc(String(v||'—'))}</p></div>`).join('')}
        </div>`;
    document.getElementById('detail-modal').classList.remove('hidden');
}

function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

['create-modal','plan-modal','detail-modal'].forEach(id => {
    document.getElementById(id).addEventListener('click', e => { if(e.target===e.currentTarget) closeModal(id); });
});

// Auto-generate slug from name
document.getElementById('new-company-name').addEventListener('input', e => {
    const slug = document.getElementById('new-company-slug');
    if (!slug.dataset.manual) {
        slug.value = e.target.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
    }
});
document.getElementById('new-company-slug').addEventListener('input', () => {
    document.getElementById('new-company-slug').dataset.manual = 'true';
});

let debounce;
['search','filter-plan','filter-status'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(loadCompanies, 300); });
});

loadCompanies();
</script>
