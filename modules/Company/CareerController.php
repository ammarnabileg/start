<?php
class CareerController {
    public static function handle(string $path, Request $request): void {
        $db = Database::getInstance();

        // Extract tenant slug from path: /careers/{slug} or /careers
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $slug = $segments[1] ?? null; // /careers/{slug}

        $tenant = null;
        if ($slug) {
            $tenant = $db->fetch("SELECT * FROM tenants WHERE slug = ? AND status = 'active'", [$slug]);
        }

        if (!$tenant) {
            http_response_code(404);
            echo self::layout('Company Not Found', '<div class="text-center py-20"><h1 class="text-2xl font-bold text-gray-700">Company not found.</h1></div>');
            return;
        }

        $jobs = $db->fetchAll(
            "SELECT id, title, department, location, job_type, salary_min, salary_max, salary_currency, description, created_at
             FROM jobs WHERE tenant_id = ? AND status = 'published' ORDER BY created_at DESC",
            [$tenant['id']]
        );

        $careerSettings = $db->fetch("SELECT * FROM career_page_settings WHERE tenant_id = ?", [$tenant['id']]);
        $companyName = $careerSettings['company_name'] ?? $tenant['name'] ?? 'Our Company';
        $primaryColor = $careerSettings['primary_color'] ?? '#7C3AED';
        $description  = $careerSettings['description'] ?? "Join our team and make an impact.";

        $jobsHtml = '';
        if (empty($jobs)) {
            $jobsHtml = '<div class="text-center py-16 text-gray-500"><p class="text-lg">No open positions at this time.</p><p class="text-sm mt-2">Check back soon!</p></div>';
        } else {
            foreach ($jobs as $job) {
                $title   = htmlspecialchars($job['title']);
                $dept    = htmlspecialchars($job['department'] ?? '');
                $loc     = htmlspecialchars($job['location'] ?? 'Remote');
                $type    = htmlspecialchars($job['job_type'] ?? 'full-time');
                $salary  = '';
                if (!empty($job['salary_min']) && !empty($job['salary_max'])) {
                    $cur    = htmlspecialchars($job['salary_currency'] ?? 'USD');
                    $salary = "<span class='text-sm font-medium text-green-600'>{$cur} " . number_format((float)$job['salary_min']) . ' – ' . number_format((float)$job['salary_max']) . "</span>";
                }
                $applyUrl = "/careers/{$slug}/apply/{$job['id']}";
                $jobsHtml .= "
                <div class='bg-white rounded-2xl border border-gray-100 p-6 hover:shadow-md transition-shadow'>
                    <div class='flex items-start justify-between gap-4'>
                        <div>
                            <h3 class='text-lg font-bold text-gray-900'>{$title}</h3>
                            <div class='flex flex-wrap items-center gap-2 mt-2'>
                                " . ($dept ? "<span class='text-xs bg-violet-50 text-violet-700 px-2 py-0.5 rounded-full'>{$dept}</span>" : '') . "
                                <span class='text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full'>{$loc}</span>
                                <span class='text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full'>{$type}</span>
                                {$salary}
                            </div>
                        </div>
                        <a href='{$applyUrl}' class='flex-shrink-0 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-5 py-2 rounded-full transition-colors'>
                            Apply Now
                        </a>
                    </div>
                </div>";
            }
        }

        $countText = count($jobs) === 1 ? '1 open position' : count($jobs) . ' open positions';
        $body = "
        <div class='min-h-screen bg-gray-50'>
            <div style='background:{$primaryColor}' class='py-16 text-white text-center px-4'>
                <h1 class='text-4xl font-bold mb-3'>" . htmlspecialchars($companyName) . "</h1>
                <p class='text-lg opacity-90 max-w-xl mx-auto'>" . htmlspecialchars($description) . "</p>
                <p class='mt-4 text-sm opacity-70'>{$countText}</p>
            </div>
            <div class='max-w-3xl mx-auto px-4 py-10 flex flex-col gap-4'>
                {$jobsHtml}
            </div>
        </div>";

        echo self::layout(htmlspecialchars($companyName) . ' — Careers', $body);
    }

    private static function layout(string $title, string $body): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>*{font-family:'Inter',sans-serif}</style>
</head>
<body>{$body}</body>
</html>
HTML;
    }
}
