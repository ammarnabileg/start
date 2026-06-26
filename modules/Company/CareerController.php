<?php
class CareerController {
    public static function handle(string $path, Request $request): void {
        // Routes: /careers/{slug} or /careers/{slug}/{job-id}
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        // $parts[0] = 'careers', $parts[1] = slug, $parts[2] = job-id
        $tenantSlug = $parts[1] ?? '';
        $jobId      = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : null;

        require_once dirname(__DIR__, 2) . '/bootstrap.php';
        $db = Database::getInstance();

        // Resolve tenant from slug or show generic page.
        $tenant = $tenantSlug
            ? ($db->fetch("SELECT id, name, slug, status FROM tenants WHERE slug = ? LIMIT 1", [$tenantSlug]) ?: null)
            : null;

        if ($tenantSlug && !$tenant) {
            http_response_code(404);
            echo self::errorPage('Company Not Found', 'This career page does not exist or has been removed.');
            return;
        }

        $tenantId = $tenant ? (int)$tenant['id'] : null;

        if ($jobId) {
            self::showJob($db, $tenant, $tenantSlug, $tenantId, $jobId);
        } else {
            self::showListings($db, $tenant, $tenantSlug, $tenantId);
        }
    }

    private static function showListings($db, ?array $tenant, string $tenantSlug, ?int $tenantId): void {
        $sql    = "SELECT id, title, department, location, job_type, salary_min, salary_max, currency,
                          description, experience_level, published_at
                   FROM jobs WHERE status = 'published'";
        $params = [];
        if ($tenantId) {
            $sql    .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $sql .= ' ORDER BY published_at DESC LIMIT 100';

        $jobs     = $db->fetchAll($sql, $params) ?: [];
        $totalJobs = count($jobs);
        $departments = array_unique(array_filter(array_column($jobs, 'department')));
        sort($departments);

        $viewFile = dirname(__DIR__, 2) . '/views/careers/index.php';
        require $viewFile;
    }

    private static function showJob($db, ?array $tenant, string $tenantSlug, ?int $tenantId, int $jobId): void {
        $sql    = "SELECT j.*, t.name AS company_name, t.slug AS tenant_slug
                   FROM jobs j JOIN tenants t ON t.id = j.tenant_id
                   WHERE j.id = ? AND j.status = 'published'";
        $params = [$jobId];
        if ($tenantId) {
            $sql    .= ' AND j.tenant_id = ?';
            $params[] = $tenantId;
        }
        $job = $db->fetch($sql, $params);

        if (!$job) {
            http_response_code(404);
            echo self::errorPage('Position Not Found', 'This job posting is no longer available.');
            return;
        }

        // Merge tenant from job if not already resolved.
        if (!$tenant) {
            $tenant     = ['name' => $job['company_name'], 'slug' => $job['tenant_slug']];
            $tenantSlug = $job['tenant_slug'];
        }

        $viewFile = dirname(__DIR__, 2) . '/views/careers/job.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            // Minimal inline fallback.
            self::inlineJobPage($job, $tenant, $tenantSlug);
        }
    }

    private static function inlineJobPage(array $job, array $tenant, string $tenantSlug): void {
        $title   = htmlspecialchars($job['title']);
        $company = htmlspecialchars($tenant['name'] ?? 'Company');
        $applyUrl = '/register?job=' . (int)$job['id'];
        echo <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title} — {$company}</title><script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>*{font-family:'Inter',sans-serif}</style></head>
<body class="bg-gray-50 min-h-screen">
<div class="max-w-3xl mx-auto px-4 py-12">
  <a href="/careers/{$tenantSlug}" class="text-sm text-violet-600 hover:underline mb-8 inline-block">← Back to all positions</a>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">{$title}</h1>
    <p class="text-sm text-gray-500 mb-6">{$company}</p>
    <div class="prose prose-sm text-gray-700 mb-8">{$job['description']}</div>
    <a href="{$applyUrl}" class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white rounded-full px-6 py-3 font-semibold transition-colors">Apply Now</a>
  </div>
</div></body></html>
HTML;
    }

    private static function errorPage(string $title, string $message): string {
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title><script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>*{font-family:'Inter',sans-serif}</style></head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
<div class="text-center max-w-md">
  <div class="w-20 h-20 bg-violet-100 rounded-3xl flex items-center justify-center mx-auto mb-6">
    <svg class="w-10 h-10 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  </div>
  <h1 class="text-2xl font-bold text-gray-900 mb-3">{$title}</h1>
  <p class="text-gray-500 text-sm leading-relaxed">{$message}</p>
  <a href="/" class="mt-6 inline-block text-sm text-violet-600 hover:underline">Go to homepage</a>
</div></body></html>
HTML;
    }
}
