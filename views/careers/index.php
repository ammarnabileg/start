<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf" content="<?= $req->csrf() ?>">
    <title><?= htmlspecialchars($tenant['company_name'] ?? 'Careers') ?> — Open Positions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<?php
$tenant     = $tenant ?? [];
$companyName= htmlspecialchars($tenant['company_name'] ?? 'Company');
$tenantSlug = htmlspecialchars($tenant['slug'] ?? '');
$logoUrl    = $tenant['logo_url'] ?? '';
$brandColor = $tenant['brand_color'] ?? '#6366f1';
?>

<!-- Company Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-20 shadow-sm">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <?php if ($logoUrl): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= $companyName ?>" class="h-10 w-auto object-contain">
            <?php else: ?>
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                    <?= strtoupper(substr($tenant['company_name'] ?? 'C', 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="text-lg font-bold text-gray-900"><?= $companyName ?></h1>
                <p class="text-xs text-gray-500">Open Positions</p>
            </div>
        </div>
        <a href="/login" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 px-4 py-2 border border-indigo-200 rounded-xl hover:bg-indigo-50 transition-colors">
            Sign In
        </a>
    </div>
</header>

<!-- Hero -->
<div class="bg-white border-b border-gray-100">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-12 text-center">
        <h2 class="text-3xl font-bold text-gray-900 mb-3">Join <?= $companyName ?></h2>
        <p class="text-gray-500 max-w-xl mx-auto">Explore our open positions and find your next opportunity. We're building something great and looking for talented people to join us.</p>
    </div>
</div>

<!-- Search + Filters -->
<div class="bg-white border-b border-gray-100 sticky top-16 z-10 shadow-sm">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" id="search" placeholder="Search positions…"
                    class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
            </div>
            <select id="filter-type" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Types</option>
                <option value="full_time">Full-time</option>
                <option value="part_time">Part-time</option>
                <option value="contract">Contract</option>
                <option value="internship">Internship</option>
            </select>
            <select id="filter-mode" class="px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Locations</option>
                <option value="remote">Remote</option>
                <option value="onsite">On-site</option>
                <option value="hybrid">Hybrid</option>
            </select>
        </div>
    </div>
</div>

<!-- Jobs -->
<main class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-gray-500"><span id="count">—</span> open positions</p>
    </div>
    <div id="jobs-grid" class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="col-span-full text-center py-16 text-gray-400">
            <div class="inline-flex items-center gap-2">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Loading positions…
            </div>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="border-t border-gray-200 mt-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 text-center text-sm text-gray-400">
        Powered by <a href="/" class="text-indigo-600 hover:underline font-medium">RecruitAI</a>
    </div>
</footer>

<!-- Auth Modal (when not logged in, shown on Apply) -->
<div id="auth-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-1">Apply for this role</h3>
            <p id="auth-job-title" class="text-sm text-gray-500 mb-6">—</p>

            <div class="space-y-3">
                <a id="login-link" href="/login"
                    class="flex items-center justify-center gap-2 w-full px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl transition-colors text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Sign In to Apply
                </a>
                <a id="register-link" href="/register"
                    class="flex items-center justify-center gap-2 w-full px-4 py-3 border border-gray-200 hover:bg-gray-50 text-gray-700 font-medium rounded-xl transition-colors text-sm">
                    New here? Create an account
                </a>
            </div>
            <button onclick="closeAuthModal()" class="mt-4 w-full text-sm text-gray-400 hover:text-gray-600">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
const CSRF       = document.querySelector('meta[name=csrf]').content;
const SLUG       = <?= json_encode($tenantSlug) ?>;
const IS_LOGGED_IN = <?= json_encode(isset($user)) ?>;

let allJobs = [];
let pendingJobId = null;

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
const fmtSal = (min,max,cur='USD') => {
    if (!min && !max) return null;
    const f = n => new Intl.NumberFormat('en-US',{style:'currency',currency:cur,maximumFractionDigits:0}).format(n);
    return min && max ? `${f(min)} – ${f(max)}` : (min ? `From ${f(min)}` : `Up to ${f(max)}`);
};
const typeLabel = t => ({full_time:'Full-time',part_time:'Part-time',contract:'Contract',internship:'Internship'}[t] || t || '');
const modeLabel = m => ({remote:'Remote',onsite:'On-site',hybrid:'Hybrid'}[m] || m || '');
const modeCls   = m => ({remote:'bg-green-100 text-green-700',onsite:'bg-gray-100 text-gray-700',hybrid:'bg-blue-100 text-blue-700'}[m] || 'bg-gray-100 text-gray-700');
const typeCls   = t => ({full_time:'bg-indigo-100 text-indigo-700',part_time:'bg-purple-100 text-purple-700',contract:'bg-orange-100 text-orange-700',internship:'bg-pink-100 text-pink-700'}[t] || 'bg-gray-100 text-gray-700');

async function loadJobs() {
    const q    = document.getElementById('search').value;
    const type = document.getElementById('filter-type').value;
    const mode = document.getElementById('filter-mode').value;
    const params = new URLSearchParams({ q, type, work_mode: mode });

    try {
        const r = await fetch(`/api/v1/careers/${SLUG}/jobs?${params}`, { headers: {'X-CSRF-Token': CSRF} });
        const j = await r.json();
        allJobs = j.data || [];
        renderJobs(allJobs);
    } catch(e) {
        document.getElementById('jobs-grid').innerHTML = `<div class="col-span-full text-center py-16 text-red-400">Failed to load. Please refresh.</div>`;
    }
}

function renderJobs(jobs) {
    document.getElementById('count').textContent = jobs.length;
    const grid = document.getElementById('jobs-grid');
    if (jobs.length === 0) {
        grid.innerHTML = `<div class="col-span-full text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <p class="font-medium text-gray-600">No positions found</p>
            <p class="text-sm mt-1">Try adjusting your search or check back later.</p>
        </div>`;
        return;
    }

    grid.innerHTML = jobs.map(j => {
        const salary = fmtSal(j.salary_min, j.salary_max, j.currency);
        return `
        <div class="bg-white rounded-2xl border border-gray-200 p-5 flex flex-col gap-4 hover:shadow-md hover:border-indigo-200 transition-all">
            <div>
                <h3 class="font-semibold text-gray-900 text-base">${esc(j.title)}</h3>
                ${j.department ? `<p class="text-sm text-gray-500 mt-0.5">${esc(j.department)}</p>` : ''}
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="text-xs font-medium px-2.5 py-1 rounded-full ${typeCls(j.type)}">${typeLabel(j.type)}</span>
                <span class="text-xs font-medium px-2.5 py-1 rounded-full ${modeCls(j.work_mode)}">${modeLabel(j.work_mode)}</span>
            </div>

            <div class="space-y-1.5 text-sm text-gray-500">
                ${j.location ? `<div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    ${esc(j.location)}</div>` : ''}
                ${salary ? `<div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    ${salary}</div>` : ''}
            </div>

            ${j.description ? `<p class="text-sm text-gray-600 line-clamp-2">${esc(j.description)}</p>` : ''}

            <button onclick="handleApply(${j.id}, '${esc(j.title)}')"
                class="mt-auto w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">
                Apply Now
            </button>
        </div>`;
    }).join('');
}

function handleApply(jobId, title) {
    if (IS_LOGGED_IN) {
        window.location.href = `/careers/apply?job_id=${jobId}`;
    } else {
        pendingJobId = jobId;
        document.getElementById('auth-job-title').textContent = title;
        const base = window.location.href.split('?')[0];
        document.getElementById('login-link').href    = `/login?redirect=${encodeURIComponent(base + '?apply=' + jobId)}`;
        document.getElementById('register-link').href = `/register?redirect=${encodeURIComponent(base + '?apply=' + jobId)}`;
        document.getElementById('auth-modal').classList.remove('hidden');
    }
}

function closeAuthModal() {
    document.getElementById('auth-modal').classList.add('hidden');
}

// Auto-apply if redirected back with apply param
const urlParams = new URLSearchParams(window.location.search);
const autoApplyId = urlParams.get('apply');
if (IS_LOGGED_IN && autoApplyId) {
    window.location.href = `/careers/apply?job_id=${autoApplyId}`;
}

document.getElementById('auth-modal').addEventListener('click', e => { if(e.target===e.currentTarget) closeAuthModal(); });

let debounceTimer;
['search','filter-type','filter-mode'].forEach(id => {
    document.getElementById(id).addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadJobs, 300);
    });
});

loadJobs();
</script>
</body>
</html>
