<?php
// Public job detail page — variables: $job, $tenant, $tenantSlug
$jobTitle   = htmlspecialchars($job['title'] ?? 'Position');
$company    = htmlspecialchars($tenant['name'] ?? $job['company_name'] ?? 'Company');
$applyUrl   = '/register?job=' . (int)$job['id'];

$sym = match($job['currency'] ?? 'USD') { 'GBP' => '£', 'EUR' => '€', default => '$' };
$fmt = fn($n) => $sym . number_format((float)$n / 1000, 0) . 'k';
$salary = null;
if (!empty($job['salary_min']) || !empty($job['salary_max'])) {
    if (!empty($job['salary_min']) && !empty($job['salary_max'])) {
        $salary = $fmt($job['salary_min']) . ' – ' . $fmt($job['salary_max']) . '/yr';
    } elseif (!empty($job['salary_min'])) {
        $salary = 'From ' . $fmt($job['salary_min']) . '/yr';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $jobTitle ?> — <?= $company ?></title>
<meta property="og:title" content="<?= $jobTitle ?> at <?= $company ?>">
<meta property="og:description" content="<?= htmlspecialchars(strip_tags(substr($job['description'] ?? '', 0, 200))) ?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { font-family: 'Inter', sans-serif; }
.prose p { margin-bottom: 1rem; color: #374151; }
.prose h3 { font-weight: 700; font-size: 1rem; margin: 1.25rem 0 0.5rem; color: #111827; }
.prose ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 1rem; color: #374151; }
.prose li { margin-bottom: 0.25rem; }
</style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Header -->
<header class="bg-white border-b border-gray-100 sticky top-0 z-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
        <a href="/careers/<?= htmlspecialchars($tenantSlug ?? '') ?>" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
            <div class="w-9 h-9 rounded-xl bg-violet-600 flex items-center justify-center text-white font-bold">
                <?= htmlspecialchars(strtoupper(substr($tenant['name'] ?? 'C', 0, 1))) ?>
            </div>
            <div>
                <div class="font-bold text-gray-900 text-sm"><?= $company ?></div>
                <div class="text-xs text-gray-400">← All Positions</div>
            </div>
        </a>
        <a href="<?= htmlspecialchars($applyUrl) ?>"
           class="bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-semibold transition-colors">
            Apply Now
        </a>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Main content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Job header -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-7">
                <h1 class="text-2xl font-extrabold text-gray-900 mb-3"><?= $jobTitle ?></h1>
                <div class="flex flex-wrap items-center gap-2.5">
                    <?php if ($job['department']): ?>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-violet-50 text-violet-700"><?= htmlspecialchars($job['department']) ?></span>
                    <?php endif; ?>
                    <?php if ($job['location']): ?>
                        <span class="flex items-center gap-1.5 text-sm text-gray-500">
                            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-5.2-7-11a7 7 0 0 1 14 0c0 5.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <?= htmlspecialchars($job['location']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($job['job_type']): ?>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 capitalize"><?= htmlspecialchars(str_replace('-', ' ', $job['job_type'])) ?></span>
                    <?php endif; ?>
                    <?php if ($job['experience_level']): ?>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 capitalize"><?= htmlspecialchars($job['experience_level']) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Description -->
            <?php if (!empty($job['description'])): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-7">
                <h2 class="text-lg font-bold text-gray-900 mb-4">About this role</h2>
                <div class="prose text-sm leading-relaxed">
                    <?= nl2br(htmlspecialchars($job['description'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Requirements -->
            <?php if (!empty($job['requirements'])): ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-7">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Requirements</h2>
                <div class="prose text-sm leading-relaxed">
                    <?= nl2br(htmlspecialchars($job['requirements'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="space-y-5">
            <!-- Apply card -->
            <div class="bg-violet-600 rounded-2xl p-6 text-white text-center shadow-lg">
                <div class="text-lg font-bold mb-2">Ready to apply?</div>
                <p class="text-violet-200 text-sm mb-4">Our AI interview takes about 20 minutes. You can do it anytime.</p>
                <a href="<?= htmlspecialchars($applyUrl) ?>"
                   class="block w-full bg-white text-violet-700 font-bold rounded-full py-3 text-sm hover:bg-violet-50 transition-colors">
                    Apply Now →
                </a>
                <p class="text-violet-300 text-xs mt-3">No account needed to view — sign up free to apply</p>
            </div>

            <!-- Details card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 space-y-4">
                <h3 class="font-bold text-gray-900 text-sm">Position Details</h3>
                <?php if ($salary): ?>
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">Compensation</div>
                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($salary) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($job['application_deadline']): ?>
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-rose-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">Deadline</div>
                        <div class="font-semibold text-gray-800"><?= htmlspecialchars(date('M j, Y', strtotime($job['application_deadline']))) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-8 h-8 rounded-lg bg-violet-50 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-violet-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">Interview</div>
                        <div class="font-semibold text-gray-800 capitalize">AI <?= htmlspecialchars(str_replace('_', ' ', $job['interview_type'] ?? 'text')) ?> · ~<?= (int)($job['interview_duration'] ?? 20) ?> min</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400">Company</div>
                        <div class="font-semibold text-gray-800"><?= $company ?></div>
                    </div>
                </div>
            </div>

            <!-- Share card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-gray-900 text-sm mb-3">Share this role</h3>
                <div class="flex gap-2">
                    <button onclick="navigator.clipboard.writeText(window.location.href).then(()=>alert('Link copied!'))"
                            class="flex-1 flex items-center justify-center gap-2 border border-gray-200 hover:bg-gray-50 rounded-xl py-2.5 text-xs font-medium text-gray-600 transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                        Copy Link
                    </button>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI']) ?>"
                       target="_blank" rel="noopener"
                       class="flex items-center justify-center w-11 border border-gray-200 hover:bg-blue-50 hover:border-blue-200 rounded-xl text-blue-600 transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="border-t border-gray-100 bg-white py-8 text-center text-xs text-gray-400 mt-8">
    Powered by HireAI · AI-Powered Recruitment Platform
</footer>
</body>
</html>
