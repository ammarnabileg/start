<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6">

    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Platform Users</h2>
        <p class="text-gray-500 mt-1">Manage all users across the platform.</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="search" placeholder="Search by name or email…"
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <select id="filter-company" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-40">
                <option value="">All Companies</option>
            </select>
            <select id="filter-type" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Types</option>
                <option value="super_admin">Super Admin</option>
                <option value="hr">HR Admin</option>
                <option value="candidate">Candidate</option>
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
                        <th class="px-6 py-3 text-left font-medium">User</th>
                        <th class="px-6 py-3 text-left font-medium">Company</th>
                        <th class="px-6 py-3 text-left font-medium">Type</th>
                        <th class="px-6 py-3 text-left font-medium">Status</th>
                        <th class="px-6 py-3 text-left font-medium">Last Login</th>
                        <th class="px-6 py-3 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody id="users-tbody" class="divide-y divide-gray-100">
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-6 py-3 border-t border-gray-100 flex items-center justify-between" id="pagination">
            <p class="text-sm text-gray-500" id="page-info">—</p>
            <div class="flex gap-2" id="page-buttons"></div>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
let currentPage = 1;
let totalPages  = 1;
let users = [];

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
const formatDate = d => d ? new Date(d).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}) : 'Never';

const typeBadge = t => ({
    super_admin: 'bg-purple-100 text-purple-700',
    hr:          'bg-blue-100 text-blue-700',
    candidate:   'bg-gray-100 text-gray-700',
}[t] || 'bg-gray-100 text-gray-600');

const typeLabel = t => ({super_admin:'Super Admin', hr:'HR Admin', candidate:'Candidate'}[t] || t || '—');

async function loadUsers(page = 1) {
    currentPage = page;
    const q       = document.getElementById('search').value;
    const company = document.getElementById('filter-company').value;
    const type    = document.getElementById('filter-type').value;
    const status  = document.getElementById('filter-status').value;
    const params  = new URLSearchParams({q, company_id: company, type, status, page});

    document.getElementById('users-tbody').innerHTML = `<tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">Loading…</td></tr>`;

    try {
        const r = await fetch(`/api/v1/super/users?${params}`, {headers:{'X-CSRF-Token':CSRF}});
        const j = await r.json();
        users = j.data || [];
        totalPages = j.meta?.total_pages || 1;
        renderTable(users);
        renderPagination(j.meta);
    } catch(e) {
        document.getElementById('users-tbody').innerHTML = `<tr><td colspan="6" class="px-6 py-10 text-center text-red-400">Failed to load.</td></tr>`;
    }
}

function renderTable(rows) {
    const tbody = document.getElementById('users-tbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">No users found.</td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map((u, i) => {
        const initials = (u.first_name||'?')[0].toUpperCase() + (u.last_name||'')[0].toUpperCase();
        return `
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600 flex-shrink-0">
                        ${esc(initials)}
                    </div>
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900 truncate">${esc((u.first_name||'')+' '+(u.last_name||''))}</p>
                        <p class="text-xs text-gray-400 truncate">${esc(u.email)}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 text-gray-600 text-xs">${esc(u.company_name || '—')}</td>
            <td class="px-6 py-4"><span class="text-xs px-2.5 py-1 rounded-full font-medium ${typeBadge(u.type)}">${typeLabel(u.type)}</span></td>
            <td class="px-6 py-4">
                ${u.status === 'active'
                    ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Active</span>'
                    : '<span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">Suspended</span>'}
            </td>
            <td class="px-6 py-4 text-gray-500 text-xs">${formatDate(u.last_login_at)}</td>
            <td class="px-6 py-4">
                <button onclick="toggleSuspend(${i})"
                    class="text-xs px-2.5 py-1.5 rounded-lg font-medium transition-colors ${u.status === 'active' ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100'}">
                    ${u.status === 'active' ? 'Suspend' : 'Activate'}
                </button>
            </td>
        </tr>`;
    }).join('');
}

function renderPagination(meta) {
    if (!meta) return;
    document.getElementById('page-info').textContent = `Showing ${meta.from||1}–${meta.to||users.length} of ${meta.total||users.length}`;
    const btns = document.getElementById('page-buttons');
    btns.innerHTML = '';
    for (let p = 1; p <= totalPages; p++) {
        const btn = document.createElement('button');
        btn.textContent = p;
        btn.className = `w-8 h-8 rounded-lg text-xs font-medium transition-colors ${p === currentPage ? 'bg-indigo-600 text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'}`;
        btn.onclick = () => loadUsers(p);
        btns.appendChild(btn);
    }
}

async function toggleSuspend(idx) {
    const u = users[idx];
    const action = u.status === 'active' ? 'suspend' : 'activate';
    if (!confirm(`${action.charAt(0).toUpperCase()+action.slice(1)} ${u.first_name} ${u.last_name}?`)) return;
    try {
        const r = await fetch(`/api/v1/super/users/${u.id}/${action}`, {
            method: 'POST', headers: {'X-CSRF-Token':CSRF}
        });
        const j = await r.json();
        if (j.ok) loadUsers(currentPage);
        else alert(j.message || 'Action failed.');
    } catch(e) { alert('Network error.'); }
}

async function loadCompanyFilter() {
    try {
        const r = await fetch('/api/v1/super/companies?per_page=200', {headers:{'X-CSRF-Token':CSRF}});
        const j = await r.json();
        const sel = document.getElementById('filter-company');
        (j.data || []).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.company_name;
            sel.appendChild(opt);
        });
    } catch(e) {}
}

let debounce;
['search','filter-company','filter-type','filter-status'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => loadUsers(1), 300);
    });
});

loadCompanyFilter();
loadUsers();
</script>
