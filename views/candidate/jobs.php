<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-6">

    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Browse Jobs</h2>
        <p class="text-gray-500 mt-1">Find your next opportunity.</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input id="search-input" type="text" placeholder="Search job title or company…"
                    class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <select id="filter-mode" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-gray-700">
                <option value="">All Work Modes</option>
                <option value="remote">Remote</option>
                <option value="onsite">On-site</option>
                <option value="hybrid">Hybrid</option>
            </select>
            <select id="filter-type" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white text-gray-700">
                <option value="">All Types</option>
                <option value="full_time">Full-time</option>
                <option value="part_time">Part-time</option>
                <option value="contract">Contract</option>
                <option value="internship">Internship</option>
            </select>
            <input id="filter-location" type="text" placeholder="Location…"
                class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-40">
            <button onclick="loadJobs()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">
                Search
            </button>
        </div>
    </div>

    <!-- Results count -->
    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500"><span id="results-count">0</span> jobs found</p>
    </div>

    <!-- Job Cards Grid -->
    <div id="jobs-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <div class="col-span-full flex items-center justify-center py-16 text-gray-400">
            <svg class="w-6 h-6 animate-spin mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Loading jobs…
        </div>
    </div>
</div>

<!-- Apply Confirm Modal -->
<div id="apply-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Apply for this position?</h3>
            <p class="text-gray-500 text-sm mb-1">You are applying for:</p>
            <p class="font-semibold text-gray-800 text-base" id="modal-job-title">—</p>
            <p class="text-sm text-gray-500" id="modal-company">—</p>

            <div class="mt-5 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
                <strong>Note:</strong> After applying, you may be asked to complete an AI screening interview. Make sure your profile is up to date.
            </div>

            <div class="flex gap-3 mt-6">
                <button onclick="closeApplyModal()" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button id="confirm-apply-btn" onclick="confirmApply()" class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">
                    Confirm Apply
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;
let pendingJobId = null;

function escHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatSalary(min, max, currency) {
    if (!min && !max) return 'Salary not specified';
    const fmt = n => new Intl.NumberFormat('en-US', {style:'currency', currency: currency||'USD', maximumFractionDigits:0}).format(n);
    if (min && max) return `${fmt(min)} – ${fmt(max)}`;
    return min ? `From ${fmt(min)}` : `Up to ${fmt(max)}`;
}

const modeBadge = m => ({ remote:'bg-green-100 text-green-700', onsite:'bg-gray-100 text-gray-700', hybrid:'bg-blue-100 text-blue-700' }[m] || 'bg-gray-100 text-gray-700');
const typeBadge = t => ({ full_time:'bg-indigo-100 text-indigo-700', part_time:'bg-purple-100 text-purple-700', contract:'bg-orange-100 text-orange-700', internship:'bg-pink-100 text-pink-700' }[t] || 'bg-gray-100 text-gray-700');
const typeLabel = t => t ? t.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()) : '';

async function loadJobs() {
    const grid = document.getElementById('jobs-grid');
    grid.innerHTML = `<div class="col-span-full flex items-center justify-center py-16 text-gray-400"><svg class="w-6 h-6 animate-spin mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Loading…</div>`;

    const params = new URLSearchParams({
        q:        document.getElementById('search-input').value,
        work_mode:document.getElementById('filter-mode').value,
        type:     document.getElementById('filter-type').value,
        location: document.getElementById('filter-location').value,
    });

    try {
        const res = await fetch(`/api/v1/careers/jobs?${params}`, {headers:{'X-CSRF-Token':CSRF}});
        const json = await res.json();
        const jobs = json.data || [];
        document.getElementById('results-count').textContent = jobs.length;

        if (jobs.length === 0) {
            grid.innerHTML = `<div class="col-span-full text-center py-16 text-gray-400"><svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg><p class="font-medium">No jobs found</p><p class="text-sm mt-1">Try adjusting your filters</p></div>`;
            return;
        }

        grid.innerHTML = jobs.map(j => `
            <div class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-col gap-4 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 truncate">${escHtml(j.title)}</h3>
                        <p class="text-sm text-gray-500 mt-0.5">${escHtml(j.company_name)}</p>
                    </div>
                    ${j.applied ? `<span class="flex-shrink-0 text-xs bg-green-100 text-green-700 px-2.5 py-1 rounded-full font-medium">Applied</span>` : ''}
                </div>

                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-medium ${modeBadge(j.work_mode)}">${typeLabel(j.work_mode)}</span>
                    <span class="inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full font-medium ${typeBadge(j.type)}">${typeLabel(j.type)}</span>
                </div>

                <div class="space-y-1.5 text-sm text-gray-500">
                    ${j.location ? `<div class="flex items-center gap-2"><svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>${escHtml(j.location)}</div>` : ''}
                    <div class="flex items-center gap-2"><svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>${formatSalary(j.salary_min, j.salary_max, j.currency)}</div>
                </div>

                <div class="pt-1 mt-auto">
                    ${j.applied
                        ? `<button disabled class="w-full py-2.5 bg-gray-100 text-gray-400 text-sm font-medium rounded-xl cursor-not-allowed">Already Applied</button>`
                        : `<button onclick="openApplyModal(${j.id}, '${escHtml(j.title)}', '${escHtml(j.company_name)}')" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">Apply Now</button>`
                    }
                </div>
            </div>`).join('');
    } catch(e) {
        grid.innerHTML = `<div class="col-span-full text-center py-16 text-red-400">Failed to load jobs. Please try again.</div>`;
    }
}

function openApplyModal(jobId, title, company) {
    pendingJobId = jobId;
    document.getElementById('modal-job-title').textContent = title;
    document.getElementById('modal-company').textContent = company;
    document.getElementById('apply-modal').classList.remove('hidden');
}

function closeApplyModal() {
    document.getElementById('apply-modal').classList.add('hidden');
    pendingJobId = null;
}

async function confirmApply() {
    if (!pendingJobId) return;
    const btn = document.getElementById('confirm-apply-btn');
    btn.disabled = true;
    btn.textContent = 'Submitting…';
    try {
        const res = await fetch('/api/v1/careers/apply', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
            body: JSON.stringify({job_id: pendingJobId})
        });
        const json = await res.json();
        if (json.ok) {
            closeApplyModal();
            loadJobs();
        } else {
            alert(json.message || 'Failed to apply. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Confirm Apply';
        }
    } catch(e) {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Confirm Apply';
    }
}

document.getElementById('search-input').addEventListener('keydown', e => { if(e.key==='Enter') loadJobs(); });
document.getElementById('apply-modal').addEventListener('click', e => { if(e.target === e.currentTarget) closeApplyModal(); });

loadJobs();
</script>
