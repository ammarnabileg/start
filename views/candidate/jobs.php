<?php
$pageTitle = 'Find Jobs';
$db = Database::getInstance();
$cid = Auth::user()['id'];
$tid = Auth::user()['tenant_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$jobs = $db->paginate("SELECT j.*, t.name as company_name,
    (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id AND a.candidate_id = ?) as applied_count
    FROM jobs j JOIN tenants t ON t.id = j.tenant_id
    WHERE j.status = 'published' AND j.tenant_id = ?
    ORDER BY j.published_at DESC", [$cid, $tid], $page, 12);

// ── Helpers ────────────────────────────────────────────────────────────────────
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 3600)    return (int)($diff / 60) . ' min ago';
    if ($diff < 86400)   return (int)($diff / 3600) . ' hr ago';
    if ($diff < 604800)  { $d = (int)($diff / 86400); return $d . ' day' . ($d > 1 ? 's' : '') . ' ago'; }
    if ($diff < 2592000) { $w = (int)($diff / 604800); return $w . ' wk ago'; }
    return date('M j, Y', strtotime($datetime));
}

function jobTypeBadge(string $type): string {
    return match(strtolower(trim($type))) {
        'remote'  => '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-100"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>Remote</span>',
        'hybrid'  => '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>Hybrid</span>',
        'on-site','onsite','on_site' => '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-orange-50 text-orange-700 border border-orange-100"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>On-site</span>',
        default   => '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">' . htmlspecialchars(ucfirst($type)) . '</span>',
    };
}

function formatSalary(?int $min, ?int $max, ?string $currency = 'USD'): ?string {
    if (!$min && !$max) return null;
    $sym = match($currency) { 'GBP' => '£', 'EUR' => '€', default => '$' };
    $fmt = fn($n) => $n >= 1000 ? $sym . number_format($n / 1000, 0) . 'k' : $sym . number_format($n);
    if ($min && $max) return $fmt($min) . ' – ' . $fmt($max) . '/yr';
    if ($min)         return 'From ' . $fmt($min) . '/yr';
    return 'Up to ' . $fmt($max) . '/yr';
}

// Fetch departments for filter dropdown
$departments = $db->fetchAll(
    "SELECT DISTINCT department FROM jobs WHERE status='published' AND tenant_id=? AND department IS NOT NULL AND department != '' ORDER BY department",
    [$tid]
) ?: [];
?>

<!-- ═══════════════════════ PAGE HEADER ═══════════════════════ -->
<div class="mb-6">
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Find Jobs</h1>
      <p class="text-sm text-gray-500 mt-0.5">
        <span id="result-count"><?= number_format($jobs->total ?? 0) ?></span>
        open position<?= ($jobs->total ?? 0) !== 1 ? 's' : '' ?> available
      </p>
    </div>
    <!-- Sort toggle -->
    <div class="flex items-center bg-gray-100 rounded-full p-1 gap-0.5 self-start sm:self-auto">
      <button id="sort-latest" onclick="setSort('latest')"
        class="sort-btn px-4 py-1.5 rounded-full text-sm font-medium transition-all bg-white text-gray-900 shadow-sm">
        <span class="flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Latest
        </span>
      </button>
      <button id="sort-match" onclick="setSort('match')"
        class="sort-btn px-4 py-1.5 rounded-full text-sm font-medium transition-all text-gray-500 hover:text-gray-700">
        <span class="flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
          Best Match
        </span>
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════ SEARCH & FILTERS ═══════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
  <div class="flex flex-col lg:flex-row gap-3">

    <!-- Search bar -->
    <div class="relative flex-1">
      <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>
      <input id="search-input" type="text" placeholder="Search titles, keywords, company…"
        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
        class="w-full pl-10 pr-10 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition-all"
        oninput="debounceFilter()">
      <button id="search-clear-btn" onclick="clearSearchInput()" class="hidden absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <!-- Location filter -->
    <div class="relative">
      <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
      </div>
      <input id="filter-location" type="text" placeholder="Location"
        value="<?= htmlspecialchars($_GET['location'] ?? '') ?>"
        class="pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition-all w-full lg:w-40"
        oninput="debounceFilter()">
    </div>

    <!-- Job type filter -->
    <div class="relative">
      <select id="filter-type" onchange="applyFilters()"
        class="pl-3 pr-8 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition-all appearance-none cursor-pointer w-full lg:w-auto">
        <option value="">All Types</option>
        <option value="remote"  <?= ($_GET['type'] ?? '') === 'remote'  ? 'selected' : '' ?>>Remote</option>
        <option value="hybrid"  <?= ($_GET['type'] ?? '') === 'hybrid'  ? 'selected' : '' ?>>Hybrid</option>
        <option value="on-site" <?= ($_GET['type'] ?? '') === 'on-site' ? 'selected' : '' ?>>On-site</option>
      </select>
      <div class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </div>
    </div>

    <!-- Department filter -->
    <div class="relative">
      <select id="filter-dept" onchange="applyFilters()"
        class="pl-3 pr-8 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition-all appearance-none cursor-pointer w-full lg:w-auto">
        <option value="">All Departments</option>
        <?php foreach ($departments as $dept):
          $d   = htmlspecialchars($dept['department']);
          $sel = ($_GET['dept'] ?? '') === $dept['department'] ? 'selected' : '';
        ?>
        <option value="<?= $d ?>" <?= $sel ?>><?= $d ?></option>
        <?php endforeach; ?>
      </select>
      <div class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </div>
    </div>

    <!-- Experience level filter -->
    <div class="relative">
      <select id="filter-exp" onchange="applyFilters()"
        class="pl-3 pr-8 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700 focus:outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition-all appearance-none cursor-pointer w-full lg:w-auto">
        <option value="">Experience Level</option>
        <option value="entry"     <?= ($_GET['exp'] ?? '') === 'entry'     ? 'selected' : '' ?>>Entry Level</option>
        <option value="mid"       <?= ($_GET['exp'] ?? '') === 'mid'       ? 'selected' : '' ?>>Mid Level</option>
        <option value="senior"    <?= ($_GET['exp'] ?? '') === 'senior'    ? 'selected' : '' ?>>Senior</option>
        <option value="lead"      <?= ($_GET['exp'] ?? '') === 'lead'      ? 'selected' : '' ?>>Lead / Principal</option>
        <option value="executive" <?= ($_GET['exp'] ?? '') === 'executive' ? 'selected' : '' ?>>Executive</option>
      </select>
      <div class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </div>
    </div>

    <!-- Clear filters -->
    <button id="clear-filters-btn" onclick="clearFilters()"
      class="hidden items-center gap-1.5 px-4 py-2.5 text-sm text-gray-600 hover:text-gray-900 font-medium bg-gray-100 hover:bg-gray-200 rounded-xl transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      Clear
    </button>
  </div>

  <!-- Active filter chips -->
  <div id="active-chips" class="hidden gap-2 mt-3 pt-3 border-t border-gray-100 flex-wrap">
  </div>
</div>

<!-- ═══════════════════════ JOB GRID / EMPTY ═══════════════════════ -->
<?php if (empty($jobs->data)): ?>
<!-- Server-side empty state -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 py-20 text-center px-6">
  <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
    </svg>
  </div>
  <h3 class="text-gray-900 font-semibold text-lg mb-1">No open positions right now</h3>
  <p class="text-gray-500 text-sm max-w-sm mx-auto">
    Check back soon — new opportunities are posted regularly.
  </p>
</div>

<?php else: ?>

<!-- Job cards grid -->
<div id="jobs-grid" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">

  <?php foreach ($jobs->data as $job):
    $applied    = (int)($job['applied_count'] ?? 0) > 0;
    $salary     = formatSalary(
        !empty($job['salary_min']) ? (int)$job['salary_min'] : null,
        !empty($job['salary_max']) ? (int)$job['salary_max'] : null,
        $job['currency'] ?? $job['salary_currency'] ?? 'USD'
    );
    $postedAt   = !empty($job['published_at']) ? timeAgo($job['published_at']) : 'Recently';
    $matchScore = !empty($job['match_score']) ? (int)$job['match_score'] : null;
    $jobType    = $job['job_type'] ?? $job['work_type'] ?? '';
    $location   = htmlspecialchars($job['location'] ?? '');
    $department = htmlspecialchars($job['department'] ?? '');
    $experience = $job['experience_level'] ?? $job['seniority'] ?? '';
    $expLabel   = match(strtolower($experience)) {
        'entry'     => 'Entry Level',
        'mid'       => 'Mid Level',
        'senior'    => 'Senior',
        'lead'      => 'Lead',
        'executive' => 'Executive',
        default     => $experience ? ucwords(str_replace('_', ' ', $experience)) : '',
    };
  ?>
  <div class="job-card bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hover:shadow-md hover:border-violet-100 transition-all duration-200 flex flex-col group"
    data-title="<?= htmlspecialchars(strtolower($job['title'] ?? '')) ?>"
    data-company="<?= htmlspecialchars(strtolower($job['company_name'] ?? '')) ?>"
    data-location="<?= htmlspecialchars(strtolower($job['location'] ?? '')) ?>"
    data-type="<?= htmlspecialchars(strtolower($jobType)) ?>"
    data-dept="<?= htmlspecialchars(strtolower($job['department'] ?? '')) ?>"
    data-exp="<?= htmlspecialchars(strtolower($experience)) ?>"
    data-match="<?= (int)($matchScore ?? 0) ?>"
    data-date="<?= strtotime($job['published_at'] ?? 'now') ?>">

    <!-- Top row: title + AI match badge -->
    <div class="flex items-start justify-between gap-3 mb-3">
      <div class="flex-1 min-w-0">
        <h3 class="text-base font-bold text-gray-900 group-hover:text-violet-700 transition-colors leading-snug">
          <?= htmlspecialchars($job['title'] ?? '') ?>
        </h3>
        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
          <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($job['company_name'] ?? '') ?></span>
          <?php if ($department): ?>
          <span class="text-gray-300 text-sm">·</span>
          <span class="text-sm text-gray-500"><?= $department ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($matchScore): ?>
      <div class="flex-shrink-0">
        <span class="inline-flex items-center gap-1 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-bold px-2.5 py-1 rounded-full whitespace-nowrap">
          <svg class="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
          </svg>
          Match: <?= $matchScore ?>%
        </span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Chips row: location, job type, experience level -->
    <div class="flex flex-wrap gap-2 mb-4">
      <?php if ($location): ?>
      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <?= $location ?>
      </span>
      <?php endif; ?>
      <?php if ($jobType): ?>
        <?= jobTypeBadge($jobType) ?>
      <?php endif; ?>
      <?php if ($expLabel): ?>
      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-violet-50 text-violet-700 border border-violet-100">
        <?= htmlspecialchars($expLabel) ?>
      </span>
      <?php endif; ?>
    </div>

    <!-- Salary + posted date -->
    <div class="flex items-center justify-between text-xs mb-5">
      <?php if ($salary): ?>
      <span class="flex items-center gap-1.5 font-semibold text-gray-700">
        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <?= $salary ?>
      </span>
      <?php else: ?>
      <span class="text-gray-400">Salary not disclosed</span>
      <?php endif; ?>
      <span class="flex items-center gap-1 text-gray-400">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <?= $postedAt ?>
      </span>
    </div>

    <!-- Footer actions -->
    <div class="border-t border-gray-50 mt-auto pt-4 flex items-center justify-between gap-3">
      <a href="/candidate/jobs/<?= (int)$job['id'] ?>"
        class="text-sm text-gray-500 hover:text-violet-600 font-medium transition-colors">
        View details →
      </a>

      <?php if ($applied): ?>
      <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 px-4 py-2 rounded-full text-sm font-semibold">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        Applied ✓
      </span>
      <?php else: ?>
      <a href="/c/jobs/<?= (int)$job['id'] ?>/apply"
        class="inline-flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-full text-sm font-medium transition-colors shadow-sm shadow-violet-200 group-hover:shadow-violet-300">
        Apply Now
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
        </svg>
      </a>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>

</div>

<!-- Client-side no-results message (shown when filter hides all cards) -->
<div id="no-results-msg" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 py-16 text-center px-6 mb-6">
  <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
    <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
  </div>
  <p class="text-gray-700 font-semibold">No jobs match your search</p>
  <p class="text-gray-400 text-sm mt-1">Try different keywords or remove some filters.</p>
  <button onclick="clearFilters()"
    class="mt-4 inline-flex items-center gap-1.5 text-sm text-white bg-violet-600 hover:bg-violet-700 px-4 py-2 rounded-full font-medium transition-colors">
    Clear all filters
  </button>
</div>

<!-- ═══════════════════════ PAGINATION ═══════════════════════ -->
<?php
$currentPage = $jobs->currentPage ?? 1;
$totalPages  = $jobs->totalPages  ?? 1;
if ($totalPages > 1):
  $baseParams = $_GET;
  unset($baseParams['page']);
  $baseQuery  = http_build_query($baseParams);
  $sep        = $baseQuery ? '&' : '';
?>
<div id="pagination-wrap">
  <div class="flex items-center justify-center gap-2 mt-2">

    <!-- Previous -->
    <?php if ($currentPage > 1): ?>
    <a href="?<?= $baseQuery . $sep ?>page=<?= $currentPage - 1 ?>"
      class="flex items-center gap-1 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-full hover:bg-gray-50 hover:border-violet-300 hover:text-violet-700 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      Previous
    </a>
    <?php else: ?>
    <span class="flex items-center gap-1 px-4 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-100 rounded-full cursor-not-allowed select-none">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      Previous
    </span>
    <?php endif; ?>

    <!-- Page numbers -->
    <div class="flex items-center gap-1">
      <?php
      $start = max(1, $currentPage - 2);
      $end   = min($totalPages, $currentPage + 2);
      if ($start > 1): ?>
        <a href="?<?= $baseQuery . $sep ?>page=1"
          class="w-9 h-9 flex items-center justify-center text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-full hover:bg-violet-50 hover:border-violet-300 hover:text-violet-700 transition-all">1</a>
        <?php if ($start > 2): ?><span class="px-1 text-gray-400 text-sm">…</span><?php endif; ?>
      <?php endif; ?>

      <?php for ($p = $start; $p <= $end; $p++): ?>
        <?php if ($p === $currentPage): ?>
        <span class="w-9 h-9 flex items-center justify-center text-sm font-bold text-white bg-violet-600 rounded-full shadow-sm shadow-violet-200">
          <?= $p ?>
        </span>
        <?php else: ?>
        <a href="?<?= $baseQuery . $sep ?>page=<?= $p ?>"
          class="w-9 h-9 flex items-center justify-center text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-full hover:bg-violet-50 hover:border-violet-300 hover:text-violet-700 transition-all">
          <?= $p ?>
        </a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?><span class="px-1 text-gray-400 text-sm">…</span><?php endif; ?>
        <a href="?<?= $baseQuery . $sep ?>page=<?= $totalPages ?>"
          class="w-9 h-9 flex items-center justify-center text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-full hover:bg-violet-50 hover:border-violet-300 hover:text-violet-700 transition-all">
          <?= $totalPages ?>
        </a>
      <?php endif; ?>
    </div>

    <!-- Next -->
    <?php if ($currentPage < $totalPages): ?>
    <a href="?<?= $baseQuery . $sep ?>page=<?= $currentPage + 1 ?>"
      class="flex items-center gap-1 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-full hover:bg-gray-50 hover:border-violet-300 hover:text-violet-700 transition-all">
      Next
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php else: ?>
    <span class="flex items-center gap-1 px-4 py-2 text-sm font-medium text-gray-300 bg-white border border-gray-100 rounded-full cursor-not-allowed select-none">
      Next
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </span>
    <?php endif; ?>

  </div>

  <!-- Page info text -->
  <p class="text-center text-xs text-gray-400 mt-3">
    Page <?= $currentPage ?> of <?= $totalPages ?> &middot; <?= number_format($jobs->total ?? 0) ?> total results
  </p>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- ═══════════════════════ JAVASCRIPT ═══════════════════════ -->
<script>
(function () {
  'use strict';

  // ── Sort ─────────────────────────────────────────────────────────────────────
  let currentSort = 'latest';

  window.setSort = function (mode) {
    currentSort = mode;
    const latestBtn = document.getElementById('sort-latest');
    const matchBtn  = document.getElementById('sort-match');
    const active    = 'bg-white text-gray-900 shadow-sm';
    const inactive  = 'text-gray-500 hover:text-gray-700';

    if (mode === 'latest') {
      latestBtn.classList.add(...active.split(' '));
      latestBtn.classList.remove(...inactive.split(' '));
      matchBtn.classList.remove(...active.split(' '));
      matchBtn.classList.add(...inactive.split(' '));
    } else {
      matchBtn.classList.add(...active.split(' '));
      matchBtn.classList.remove(...inactive.split(' '));
      latestBtn.classList.remove(...active.split(' '));
      latestBtn.classList.add(...inactive.split(' '));
    }
    sortCards();
  };

  function sortCards() {
    const grid  = document.getElementById('jobs-grid');
    if (!grid) return;
    const cards = Array.from(grid.querySelectorAll('.job-card'));
    cards.sort((a, b) => {
      if (currentSort === 'match') {
        const diff = parseInt(b.dataset.match || 0) - parseInt(a.dataset.match || 0);
        if (diff !== 0) return diff;
      }
      return parseInt(b.dataset.date || 0) - parseInt(a.dataset.date || 0);
    });
    // Re-append in sorted order (style.display preserved)
    cards.forEach(c => grid.appendChild(c));
  }

  // ── Client-side filtering ─────────────────────────────────────────────────────
  let filterTimer = null;

  window.debounceFilter = function () {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFilters, 260);
  };

  function getFilterValues() {
    return {
      q:    (document.getElementById('search-input')?.value   || '').toLowerCase().trim(),
      loc:  (document.getElementById('filter-location')?.value || '').toLowerCase().trim(),
      type: (document.getElementById('filter-type')?.value    || '').toLowerCase().trim(),
      dept: (document.getElementById('filter-dept')?.value    || '').toLowerCase().trim(),
      exp:  (document.getElementById('filter-exp')?.value     || '').toLowerCase().trim(),
    };
  }

  function applyFilters() {
    const f = getFilterValues();
    const cards = document.querySelectorAll('.job-card');
    let visible = 0;

    cards.forEach(card => {
      const matchQ    = !f.q    || card.dataset.title.includes(f.q)    || card.dataset.company.includes(f.q);
      const matchLoc  = !f.loc  || card.dataset.location.includes(f.loc);
      const matchType = !f.type || card.dataset.type.includes(f.type);
      const matchDept = !f.dept || card.dataset.dept.includes(f.dept);
      const matchExp  = !f.exp  || card.dataset.exp.includes(f.exp);

      const show = matchQ && matchLoc && matchType && matchDept && matchExp;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    // No-results message
    const noMsg = document.getElementById('no-results-msg');
    if (noMsg) noMsg.classList.toggle('hidden', visible > 0);

    // Hide pagination when filtering client-side
    const pagWrap = document.getElementById('pagination-wrap');
    if (pagWrap) pagWrap.classList.toggle('hidden', Object.values(f).some(v => v !== ''));

    // Update result count text
    const countEl = document.getElementById('result-count');
    if (countEl) countEl.textContent = visible;

    // Search clear button
    const searchClearBtn = document.getElementById('search-clear-btn');
    if (searchClearBtn) searchClearBtn.classList.toggle('hidden', !f.q);

    // Active chips + clear button
    updateChips(f);
    updateClearBtn(f);
  }
  window.applyFilters = applyFilters;

  function updateClearBtn(f) {
    const hasAny = Object.values(f).some(v => v !== '');
    const btn = document.getElementById('clear-filters-btn');
    if (!btn) return;
    btn.classList.toggle('hidden', !hasAny);
    btn.classList.toggle('inline-flex', hasAny);
  }

  function updateChips(f) {
    const container = document.getElementById('active-chips');
    if (!container) return;
    const labels = { q: 'Search', loc: 'Location', type: 'Type', dept: 'Dept', exp: 'Level' };
    const active  = Object.entries(f).filter(([, v]) => v !== '');

    if (active.length === 0) {
      container.classList.add('hidden');
      container.classList.remove('flex');
      container.innerHTML = '';
      return;
    }

    container.innerHTML = active.map(([key, val]) => `
      <span class="inline-flex items-center gap-1.5 bg-violet-100 text-violet-700 text-xs font-semibold px-3 py-1 rounded-full">
        ${labels[key]}: ${val}
        <button type="button" onclick="clearChip('${key}')" class="hover:text-violet-900 transition-colors ml-0.5" aria-label="Remove filter">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </span>
    `).join('');

    container.classList.remove('hidden');
    container.classList.add('flex');
  }

  window.clearChip = function (key) {
    const map = {
      q: 'search-input', loc: 'filter-location',
      type: 'filter-type', dept: 'filter-dept', exp: 'filter-exp',
    };
    const el = document.getElementById(map[key]);
    if (el) el.value = '';
    applyFilters();
  };

  window.clearFilters = function () {
    ['search-input','filter-location','filter-type','filter-dept','filter-exp']
      .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    applyFilters();
  };

  window.clearSearchInput = function () {
    const el = document.getElementById('search-input');
    if (el) { el.value = ''; el.focus(); }
    applyFilters();
  };

  // ── Init ──────────────────────────────────────────────────────────────────────
  // Sort if URL has sort=match
  if (new URLSearchParams(location.search).get('sort') === 'match') {
    setSort('match');
  }

  // Trigger filter if any pre-filled values exist
  const f0 = getFilterValues();
  if (Object.values(f0).some(v => v !== '')) applyFilters();
})();
</script>
