<?php
/**
 * View helpers & demo-data bootstrap.
 *
 * Included at the very top of every view. Provides:
 *   - A guaranteed e() escaping helper (no-op if app bootstrap already defined it).
 *   - Small presentation helpers for badges/colors used across the recruitment UI.
 *   - Demo fallback data so any view renders standalone when the controller has
 *     not (yet) injected real data. Real controller-supplied variables always win:
 *     every helper that reads a variable uses the value already in scope if set.
 *
 * This file intentionally contains NO output. include it, then read the helpers.
 */

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!defined('VIEWS_PATH')) {
    define('VIEWS_PATH', dirname(__DIR__));
}

if (!defined('ASSET_BASE')) {
    // Public web path to /public/assets. Adjust by env if your docroot differs.
    define('ASSET_BASE', '/assets');
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return ASSET_BASE . '/' . ltrim($path, '/');
    }
}

if (!function_exists('config')) {
    /**
     * Minimal config() fallback so views render standalone when the app
     * bootstrap (which normally defines this) has not been loaded. The real
     * bootstrap guards with function_exists, so this never collides.
     */
    function config(string $file): array
    {
        static $cache = [];
        if (!array_key_exists($file, $cache)) {
            $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $path = $base . '/config/' . $file . '.php';
            $cache[$file] = is_file($path) ? (require $path) : [];
        }
        return is_array($cache[$file]) ? $cache[$file] : [];
    }
}

if (!function_exists('initials')) {
    function initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/', $name);
        $first = mb_substr($parts[0], 0, 1);
        $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return mb_strtoupper($first . $last);
    }
}

if (!function_exists('score_color')) {
    /** Tailwind text/bg class set for a 0-100 score. */
    function score_color(?float $score): array
    {
        $s = (float) $score;
        if ($s >= 80) return ['text' => 'text-emerald-700', 'bg' => 'bg-emerald-500', 'soft' => 'bg-emerald-100 text-emerald-700', 'ring' => 'text-emerald-500'];
        if ($s >= 65) return ['text' => 'text-blue-700',    'bg' => 'bg-blue-500',    'soft' => 'bg-blue-100 text-blue-700',       'ring' => 'text-blue-500'];
        if ($s >= 50) return ['text' => 'text-amber-700',   'bg' => 'bg-amber-400',   'soft' => 'bg-amber-100 text-amber-700',     'ring' => 'text-amber-500'];
        return                ['text' => 'text-rose-700',    'bg' => 'bg-rose-500',    'soft' => 'bg-rose-100 text-rose-700',       'ring' => 'text-rose-500'];
    }
}

if (!function_exists('recommendation_badge')) {
    /** Returns [label, classes] for an ai_recommendation enum value. */
    function recommendation_badge(?string $rec): array
    {
        return match ($rec) {
            'strong'          => ['Strong',          'bg-emerald-100 text-emerald-700 ring-emerald-200'],
            'suitable'        => ['Suitable',        'bg-blue-100 text-blue-700 ring-blue-200'],
            'possible'        => ['Possible',        'bg-amber-100 text-amber-700 ring-amber-200'],
            'not_recommended' => ['Not Recommended', 'bg-rose-100 text-rose-700 ring-rose-200'],
            default           => ['Pending',         'bg-gray-100 text-gray-600 ring-gray-200'],
        };
    }
}

if (!function_exists('stage_meta')) {
    /** Pipeline stage label + color. */
    function stage_meta(string $stage): array
    {
        $map = [
            'applied'            => ['Applied',            'bg-gray-100 text-gray-700',     '#9CA3AF'],
            'ai_screening'       => ['AI Screening',       'bg-violet-100 text-violet-700', '#7C3AED'],
            'qualified'          => ['Qualified',          'bg-emerald-100 text-emerald-700','#10B981'],
            'disqualified'       => ['Disqualified',       'bg-rose-100 text-rose-700',     '#F43F5E'],
            'tech_interview'     => ['Tech Interview',     'bg-blue-100 text-blue-700',     '#3B82F6'],
            'manager_interview'  => ['Manager Interview',  'bg-indigo-100 text-indigo-700', '#6366F1'],
            'final_review'       => ['Final Review',       'bg-amber-100 text-amber-700',   '#F59E0B'],
            'offer'              => ['Offer',              'bg-yellow-100 text-yellow-700', '#FBBF24'],
            'hired'              => ['Hired',              'bg-green-100 text-green-700',    '#22C55E'],
            'rejected'          => ['Rejected',           'bg-red-100 text-red-700',       '#EF4444'],
            'withdrawn'          => ['Withdrawn',          'bg-slate-100 text-slate-600',   '#64748B'],
        ];
        return $map[$stage] ?? [ucfirst(str_replace('_', ' ', $stage)), 'bg-gray-100 text-gray-700', '#9CA3AF'];
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): array
    {
        return match ($status) {
            'active'   => ['Active',   'bg-emerald-100 text-emerald-700'],
            'draft'    => ['Draft',    'bg-gray-100 text-gray-600'],
            'paused'   => ['Paused',   'bg-amber-100 text-amber-700'],
            'archived' => ['Archived', 'bg-slate-100 text-slate-600'],
            'closed'   => ['Closed',   'bg-rose-100 text-rose-700'],
            default    => [ucfirst($status), 'bg-gray-100 text-gray-600'],
        };
    }
}

if (!function_exists('money')) {
    function money($amount, string $currency = 'USD'): string
    {
        if ($amount === null || $amount === '') return '—';
        return $currency . ' ' . number_format((float) $amount, 0);
    }
}

if (!function_exists('time_ago')) {
    function time_ago($datetime): string
    {
        if (!$datetime) return '—';
        $ts = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
        if (!$ts) return (string) $datetime;
        $diff = time() - $ts;
        if ($diff < 60)     return 'just now';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', $ts);
    }
}

/* --------------------------------------------------------------------------
 * Demo data scaffold. Every block only fills a variable when the controller
 * has not already provided it. This keeps views fully renderable in isolation
 * while guaranteeing real data is never overwritten.
 * ------------------------------------------------------------------------ */

$user   = $user   ?? ['full_name' => 'Sarah Mitchell', 'email' => 'sarah@acme.io', 'type' => 'company_user', 'role' => 'HR Manager', 'roles' => ['HR Manager'], 'tenant_id' => 1, 'avatar' => null];
$tenant = $tenant ?? ['name' => 'Acme Talent', 'slug' => 'acme', 'plan' => 'growth', 'logo' => null];
$isSuperAdmin = $isSuperAdmin ?? (($user['type'] ?? '') === 'super_admin');

$notifications = $notifications ?? [
    ['title' => 'New AI interview completed', 'body' => 'James Carter finished the Senior Backend interview', 'time' => '-8 minutes', 'is_read' => 0, 'type' => 'interview'],
    ['title' => 'Candidate qualified',        'body' => 'Aisha Khan moved to Tech Interview',                'time' => '-2 hours',   'is_read' => 0, 'type' => 'stage'],
    ['title' => 'Offer accepted',             'body' => 'Diego Fernandez accepted the Product Designer offer','time' => '-1 day',    'is_read' => 1, 'type' => 'offer'],
];
$unreadCount = $unreadCount ?? count(array_filter($notifications, fn ($n) => empty($n['is_read'])));

if (!function_exists('demo_candidates')) {
    function demo_candidates(): array
    {
        return [
            ['id' => 1, 'full_name' => 'James Carter',   'email' => 'james.carter@mail.com',  'job' => 'Senior Backend Engineer', 'score' => 88, 'rec' => 'strong',          'stage' => 'tech_interview',    'applied' => '-2 days',  'years' => 7, 'location' => 'London, UK',   'skills' => ['PHP', 'MySQL', 'Redis', 'AWS', 'Docker']],
            ['id' => 2, 'full_name' => 'Aisha Khan',      'email' => 'aisha.khan@mail.com',    'job' => 'Senior Backend Engineer', 'score' => 76, 'rec' => 'suitable',        'stage' => 'qualified',         'applied' => '-3 days',  'years' => 5, 'location' => 'Dubai, UAE',   'skills' => ['Node.js', 'Postgres', 'GraphQL', 'Kafka']],
            ['id' => 3, 'full_name' => 'Diego Fernandez', 'email' => 'diego.f@mail.com',       'job' => 'Product Designer',        'score' => 92, 'rec' => 'strong',          'stage' => 'offer',             'applied' => '-6 days',  'years' => 9, 'location' => 'Madrid, ES',   'skills' => ['Figma', 'Design Systems', 'Prototyping', 'UX Research']],
            ['id' => 4, 'full_name' => 'Mei Lin',         'email' => 'mei.lin@mail.com',       'job' => 'Data Analyst',            'score' => 61, 'rec' => 'possible',        'stage' => 'ai_screening',      'applied' => '-1 days',  'years' => 3, 'location' => 'Singapore',    'skills' => ['SQL', 'Python', 'Tableau', 'Excel']],
            ['id' => 5, 'full_name' => 'Tom Becker',      'email' => 'tom.becker@mail.com',    'job' => 'Data Analyst',            'score' => 44, 'rec' => 'not_recommended', 'stage' => 'disqualified',      'applied' => '-4 days',  'years' => 2, 'location' => 'Berlin, DE',   'skills' => ['Excel', 'Power BI']],
            ['id' => 6, 'full_name' => 'Olivia Reyes',    'email' => 'olivia.r@mail.com',      'job' => 'Frontend Engineer',       'score' => 81, 'rec' => 'strong',          'stage' => 'manager_interview', 'applied' => '-5 days',  'years' => 6, 'location' => 'Austin, US',   'skills' => ['React', 'TypeScript', 'Tailwind', 'Next.js']],
            ['id' => 7, 'full_name' => 'Noah Patel',      'email' => 'noah.patel@mail.com',    'job' => 'Frontend Engineer',       'score' => 69, 'rec' => 'suitable',        'stage' => 'applied',           'applied' => '-7 hours', 'years' => 4, 'location' => 'Toronto, CA',  'skills' => ['Vue', 'JavaScript', 'CSS']],
            ['id' => 8, 'full_name' => 'Grace Okafor',    'email' => 'grace.o@mail.com',       'job' => 'Product Designer',        'score' => 73, 'rec' => 'suitable',        'stage' => 'final_review',      'applied' => '-9 days',  'years' => 8, 'location' => 'Lagos, NG',    'skills' => ['Figma', 'Branding', 'Illustration']],
            ['id' => 9, 'full_name' => 'Liam Murphy',     'email' => 'liam.m@mail.com',        'job' => 'Senior Backend Engineer', 'score' => 85, 'rec' => 'strong',          'stage' => 'hired',             'applied' => '-14 days', 'years' => 10,'location' => 'Dublin, IE',   'skills' => ['Go', 'Kubernetes', 'gRPC', 'Terraform']],
            ['id' => 10,'full_name' => 'Sofia Rossi',     'email' => 'sofia.r@mail.com',       'job' => 'Frontend Engineer',       'score' => 39, 'rec' => 'not_recommended', 'stage' => 'rejected',          'applied' => '-11 days', 'years' => 1, 'location' => 'Milan, IT',    'skills' => ['HTML', 'CSS']],
        ];
    }
}
