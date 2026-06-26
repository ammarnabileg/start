<?php
// Public career page for a company (tenant)
// Variables: $tenant, $jobs, $totalJobs
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($tenant['name'] ?? 'Careers') ?> — Open Positions</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Header -->
<header class="bg-white border-b border-gray-100 sticky top-0 z-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-violet-600 flex items-center justify-center text-white font-bold text-lg">
                <?= htmlspecialchars(strtoupper(substr($tenant['name'] ?? 'C', 0, 1))) ?>
            </div>
            <div>
                <div class="font-bold text-gray-900"><?= htmlspecialchars($tenant['name'] ?? 'Company') ?></div>
                <div class="text-xs text-gray-400">Careers</div>
            </div>
        </div>
        <a href="/register" class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-semibold transition-colors">
            Sign Up to Apply
        </a>
    </div>
</header>

<!-- Hero -->
<section class="bg-white border-b border-gray-100 py-14">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 text-center">
        <h1 class="text-4xl font-extrabold text-gray-900 mb-4">
            Join <?= htmlspecialchars($tenant['name'] ?? 'Our Team') ?>
        </h1>
        <?php if (!empty($tenant['career_description'])): ?>
            <p class="text-lg text-gray-500 max-w-2xl mx-auto"><?= htmlspecialchars($tenant['career_description']) ?></p>
        <?php else: ?>
            <p class="text-lg text-gray-500 max-w-2xl mx-auto">We're hiring! Browse our open positions and find your next opportunity.</p>
        <?php endif; ?>
        <div class="mt-6 inline-flex items-center gap-2 bg-violet-50 text-violet-700 rounded-full px-4 py-2 text-sm font-semibold">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <?= (int)$totalJobs ?> Open Position<?= $totalJobs !== 1 ? 's' : '' ?>
        </div>
    </div>
</section>

<!-- Search / filter bar -->
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-full px-4 py-2.5 flex-1 shadow-sm focus-within:ring-2 focus-within:ring-violet-500/40">
            <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input id="search" type="text" placeholder="Search positions…" class="bg-transparent text-sm w-full outline-none" oninput="filterJobs()">
        </div>
        <select id="deptFilter" onchange="filterJobs()" class="bg-white border border-gray-200 rounded-full px-4 py-2.5 text-sm text-gray-700 shadow-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="typeFilter" onchange="filterJobs()" class="bg-white border border-gray-200 rounded-full px-4 py-2.5 text-sm text-gray-700 shadow-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
            <option value="">All Types</option>
            <option value="full-time">Full-time</option>
            <option value="part-time">Part-time</option>
            <option value="contract">Contract</option>
            <option value="remote">Remote</option>
            <option value="internship">Internship</option>
        </select>
    </div>
</div>

<!-- Job listings -->
<main class="max-w-5xl mx-auto px-4 sm:px-6 pb-16">
    <?php if (empty($jobs)): ?>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-violet-50 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="font-bold text-gray-900 text-lg">No open positions right now</h3>
            <p class="text-gray-400 text-sm mt-2">Check back later or follow us on LinkedIn for updates.</p>
        </div>
    <?php else: ?>
        <div id="jobList" class="space-y-4">
            <?php foreach ($jobs as $j):
                $salary = null;
                if (!empty($j['salary_min']) || !empty($j['salary_max'])) {
                    $sym = match($j['currency'] ?? 'USD') { 'GBP' => '£', 'EUR' => '€', default => '$' };
                    $fmt = fn($n) => $sym . number_format((float)$n / 1000, 0) . 'k';
                    if (!empty($j['salary_min']) && !empty($j['salary_max'])) {
                        $salary = $fmt($j['salary_min']) . ' – ' . $fmt($j['salary_max']) . '/yr';
                    } elseif (!empty($j['salary_min'])) {
                        $salary = 'From ' . $fmt($j['salary_min']) . '/yr';
                    }
                }
            ?>
            <article class="job-card bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-6"
                     data-title="<?= htmlspecialchars(strtolower($j['title'])) ?>"
                     data-dept="<?= htmlspecialchars($j['department'] ?? '') ?>"
                     data-type="<?= htmlspecialchars($j['job_type'] ?? '') ?>">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($j['title']) ?></h2>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <?php if ($j['department']): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-violet-50 text-violet-700"><?= htmlspecialchars($j['department']) ?></span>
                            <?php endif; ?>
                            <?php if ($j['location']): ?>
                                <span class="flex items-center gap-1 text-xs text-gray-500">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-5.2-7-11a7 7 0 0 1 14 0c0 5.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                    <?= htmlspecialchars($j['location']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($j['job_type']): ?>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 capitalize"><?= htmlspecialchars(str_replace('-', ' ', $j['job_type'])) ?></span>
                            <?php endif; ?>
                            <?php if ($salary): ?>
                                <span class="flex items-center gap-1 text-xs text-emerald-600 font-medium">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    <?= htmlspecialchars($salary) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($j['description'])): ?>
                            <p class="mt-3 text-sm text-gray-500 line-clamp-2"><?= htmlspecialchars(strip_tags(substr($j['description'], 0, 200))) ?>…</p>
                        <?php endif; ?>
                    </div>
                    <div class="shrink-0">
                        <a href="/careers/<?= htmlspecialchars($tenantSlug ?? '') ?>/<?= (int)$j['id'] ?>"
                           class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white rounded-full px-5 py-2.5 text-sm font-semibold transition-colors whitespace-nowrap">
                            Apply Now
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <div id="noResults" class="hidden text-center py-16 text-gray-400">No positions match your search.</div>
    <?php endif; ?>
</main>

<!-- Footer -->
<footer class="border-t border-gray-100 bg-white py-8 text-center text-xs text-gray-400">
    Powered by HireAI · AI-Powered Recruitment Platform
</footer>

<script>
function filterJobs() {
    var q    = document.getElementById('search').value.toLowerCase();
    var dept = document.getElementById('deptFilter').value.toLowerCase();
    var type = document.getElementById('typeFilter').value.toLowerCase();
    var cards = document.querySelectorAll('.job-card');
    var shown = 0;
    cards.forEach(function(c) {
        var titleMatch = c.dataset.title.includes(q);
        var deptMatch  = !dept || c.dataset.dept.toLowerCase() === dept;
        var typeMatch  = !type || c.dataset.type.toLowerCase() === type;
        var ok = titleMatch && deptMatch && typeMatch;
        c.style.display = ok ? '' : 'none';
        if (ok) shown++;
    });
    var nr = document.getElementById('noResults');
    if (nr) nr.classList.toggle('hidden', shown > 0);
}
</script>
</body>
</html>
