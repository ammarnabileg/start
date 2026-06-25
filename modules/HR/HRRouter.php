<?php

class HRRouter
{
    public static function dispatch(string $path, string $method, Request $req): void
    {
        Auth::requireHR();

        $db       = Database::getInstance();
        $tenantId = Auth::tenantId();

        // Strip trailing slash for consistency (keep root intact)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // ── Dashboard ──────────────────────────────────────────────────────
        if ($path === '/dashboard' && $method === 'GET') {
            $stats = [
                'total_jobs'        => $db->fetchColumn('SELECT COUNT(*) FROM jobs WHERE tenant_id = ?', [$tenantId]),
                'active_jobs'       => $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE tenant_id = ? AND status = 'published'", [$tenantId]),
                'total_applications'=> $db->fetchColumn('SELECT COUNT(*) FROM applications WHERE tenant_id = ?', [$tenantId]),
                'pending_interviews'=> $db->fetchColumn("SELECT COUNT(*) FROM human_interviews WHERE tenant_id = ? AND status = 'scheduled'", [$tenantId]),
            ];
            $recentApplications = $db->fetchAll(
                'SELECT a.*, u.first_name, u.last_name, u.email, j.title AS job_title
                 FROM applications a
                 JOIN users u ON u.id = a.candidate_id
                 JOIN jobs j ON j.id = a.job_id
                 WHERE a.tenant_id = ?
                 ORDER BY a.applied_at DESC LIMIT 10',
                [$tenantId]
            );
            view('hr/dashboard', [
                'stats'              => $stats,
                'recentApplications' => $recentApplications,
            ], 'hr');
            return;
        }

        // ── Jobs ──────────────────────────────────────────────────────────
        if ($path === '/jobs' && $method === 'GET') {
            $jobs = $db->fetchAll(
                'SELECT j.*, COUNT(a.id) AS application_count
                 FROM jobs j
                 LEFT JOIN applications a ON a.job_id = j.id
                 WHERE j.tenant_id = ?
                 GROUP BY j.id
                 ORDER BY j.created_at DESC',
                [$tenantId]
            );
            view('hr/jobs/index', ['jobs' => $jobs], 'hr');
            return;
        }

        if ($path === '/jobs/create' && $method === 'GET') {
            view('hr/jobs/create', ['job' => null], 'hr');
            return;
        }

        if (preg_match('#^/jobs/(\d+)/edit$#', $path, $m) && $method === 'GET') {
            $job = $db->fetch(
                'SELECT * FROM jobs WHERE id = ? AND tenant_id = ? LIMIT 1',
                [(int) $m[1], $tenantId]
            );
            if (!$job) { self::notFound(); return; }
            view('hr/jobs/create', ['job' => $job], 'hr');
            return;
        }

        if (preg_match('#^/jobs/(\d+)$#', $path, $m) && $method === 'GET') {
            $job = $db->fetch(
                'SELECT * FROM jobs WHERE id = ? AND tenant_id = ? LIMIT 1',
                [(int) $m[1], $tenantId]
            );
            if (!$job) { self::notFound(); return; }
            $applications = $db->fetchAll(
                'SELECT a.*, u.first_name, u.last_name, u.email
                 FROM applications a
                 JOIN users u ON u.id = a.candidate_id
                 WHERE a.job_id = ? AND a.tenant_id = ?
                 ORDER BY a.ai_score DESC, a.applied_at DESC',
                [(int) $m[1], $tenantId]
            );
            view('hr/jobs/show', ['job' => $job, 'applications' => $applications], 'hr');
            return;
        }

        // ── Pipeline ──────────────────────────────────────────────────────
        if ($path === '/pipeline' && $method === 'GET') {
            $stages = ['applied', 'screening', 'interview', 'assessment', 'offer', 'hired', 'rejected'];
            $pipeline = [];
            foreach ($stages as $stage) {
                $pipeline[$stage] = $db->fetchAll(
                    'SELECT a.*, u.first_name, u.last_name, j.title AS job_title
                     FROM applications a
                     JOIN users u ON u.id = a.candidate_id
                     JOIN jobs j ON j.id = a.job_id
                     WHERE a.tenant_id = ? AND a.current_stage = ?
                     ORDER BY a.ai_score DESC',
                    [$tenantId, $stage]
                );
            }
            view('hr/pipeline', ['pipeline' => $pipeline, 'stages' => $stages], 'hr');
            return;
        }

        // ── Candidates ────────────────────────────────────────────────────
        if ($path === '/candidates' && $method === 'GET') {
            $page = (int) $req->get('page', 1);
            $search = trim((string) $req->get('search', ''));

            $where  = 'a.tenant_id = ?';
            $params = [$tenantId];

            if ($search !== '') {
                $where   .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
                $like     = "%{$search}%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $result = $db->paginate(
                "SELECT a.*, u.first_name, u.last_name, u.email, j.title AS job_title
                 FROM applications a
                 JOIN users u ON u.id = a.candidate_id
                 JOIN jobs j ON j.id = a.job_id
                 WHERE {$where}
                 ORDER BY a.applied_at DESC",
                $params,
                $page,
                20
            );

            view('hr/candidates/index', [
                'candidates' => $result['data'],
                'pagination' => $result,
                'search'     => $search,
            ], 'hr');
            return;
        }

        if ($path === '/candidates/compare' && $method === 'GET') {
            $ids = array_filter(array_map('intval', explode(',', (string) $req->get('ids', ''))));
            $candidates = [];
            foreach ($ids as $id) {
                $app = $db->fetch(
                    'SELECT a.*, u.first_name, u.last_name, u.email,
                            ai.skills_scores, ai.behavioral_analysis, ai.overall_score, ai.recommendation
                     FROM applications a
                     JOIN users u ON u.id = a.candidate_id
                     LEFT JOIN ai_interviews ai ON ai.application_id = a.id
                     WHERE a.id = ? AND a.tenant_id = ? LIMIT 1',
                    [$id, $tenantId]
                );
                if ($app) { $candidates[] = $app; }
            }
            view('hr/candidates/compare', ['candidates' => $candidates], 'hr');
            return;
        }

        if (preg_match('#^/candidates/(\d+)$#', $path, $m) && $method === 'GET') {
            $applicationId = (int) $m[1];
            $candidate = $db->fetch(
                'SELECT a.*, u.first_name, u.last_name, u.email, u.id AS user_id,
                        j.title AS job_title, j.id AS job_id
                 FROM applications a
                 JOIN users u ON u.id = a.candidate_id
                 JOIN jobs j ON j.id = a.job_id
                 WHERE a.id = ? AND a.tenant_id = ? LIMIT 1',
                [$applicationId, $tenantId]
            );
            if (!$candidate) { self::notFound(); return; }

            $aiInterview = $db->fetch(
                'SELECT * FROM ai_interviews WHERE application_id = ? LIMIT 1',
                [$applicationId]
            );
            $humanInterviews = $db->fetchAll(
                'SELECT * FROM human_interviews WHERE application_id = ? ORDER BY interview_date DESC',
                [$applicationId]
            );
            $offers = $db->fetchAll(
                'SELECT * FROM offers WHERE application_id = ? ORDER BY created_at DESC',
                [$applicationId]
            );

            view('hr/candidates/show', [
                'candidate'       => $candidate,
                'aiInterview'     => $aiInterview,
                'humanInterviews' => $humanInterviews,
                'offers'          => $offers,
            ], 'hr');
            return;
        }

        // ── Interviews ────────────────────────────────────────────────────
        if ($path === '/interviews' && $method === 'GET') {
            $interviews = $db->fetchAll(
                'SELECT ai.*, a.candidate_id, u.first_name, u.last_name, j.title AS job_title
                 FROM ai_interviews ai
                 JOIN applications a ON a.id = ai.application_id
                 JOIN users u ON u.id = a.candidate_id
                 JOIN jobs j ON j.id = a.job_id
                 WHERE a.tenant_id = ?
                 ORDER BY ai.created_at DESC',
                [$tenantId]
            );
            view('hr/interviews/index', ['interviews' => $interviews], 'hr');
            return;
        }

        if (preg_match('#^/interviews/(\d+)/report$#', $path, $m) && $method === 'GET') {
            $interviewId = (int) $m[1];
            $interview = $db->fetch(
                'SELECT ai.*, a.candidate_id, a.job_id, u.first_name, u.last_name, u.email,
                        j.title AS job_title
                 FROM ai_interviews ai
                 JOIN applications a ON a.id = ai.application_id
                 JOIN users u ON u.id = a.candidate_id
                 JOIN jobs j ON j.id = a.job_id
                 WHERE ai.id = ? AND a.tenant_id = ? LIMIT 1',
                [$interviewId, $tenantId]
            );
            if (!$interview) { self::notFound(); return; }

            // Decode JSON fields
            foreach (['transcript', 'skills_scores', 'behavioral_analysis', 'red_flags'] as $field) {
                if (isset($interview[$field]) && is_string($interview[$field])) {
                    $interview[$field] = json_decode($interview[$field], true) ?? [];
                }
            }

            view('hr/interviews/report', ['interview' => $interview], 'hr');
            return;
        }

        // ── Human Interviews ──────────────────────────────────────────────
        if ($path === '/human-interviews' && $method === 'GET') {
            $interviews = $db->fetchAll(
                'SELECT hi.*, u.first_name, u.last_name, j.title AS job_title
                 FROM human_interviews hi
                 JOIN applications a ON a.id = hi.application_id
                 JOIN users u ON u.id = a.candidate_id
                 JOIN jobs j ON j.id = a.job_id
                 WHERE hi.tenant_id = ?
                 ORDER BY hi.interview_date DESC',
                [$tenantId]
            );
            view('hr/human-interviews', ['interviews' => $interviews], 'hr');
            return;
        }

        // ── Offers ────────────────────────────────────────────────────────
        if ($path === '/offers' && $method === 'GET') {
            $offers = $db->fetchAll(
                'SELECT o.*, u.first_name, u.last_name, j.title AS job_title
                 FROM offers o
                 JOIN applications a ON a.id = o.application_id
                 JOIN users u ON u.id = a.candidate_id
                 JOIN jobs j ON j.id = a.job_id
                 WHERE o.tenant_id = ?
                 ORDER BY o.created_at DESC',
                [$tenantId]
            );
            view('hr/offers', ['offers' => $offers], 'hr');
            return;
        }

        // ── Talent Pool ───────────────────────────────────────────────────
        if ($path === '/talent-pool' && $method === 'GET') {
            $pool = $db->fetchAll(
                'SELECT u.*, MAX(a.ai_score) AS best_score, COUNT(a.id) AS application_count
                 FROM users u
                 JOIN applications a ON a.candidate_id = u.id
                 WHERE a.tenant_id = ? AND u.type = ?
                 GROUP BY u.id
                 ORDER BY best_score DESC',
                [$tenantId, 'candidate']
            );
            view('hr/talent-pool', ['pool' => $pool], 'hr');
            return;
        }

        // ── Avatars ───────────────────────────────────────────────────────
        if ($path === '/avatars' && $method === 'GET') {
            $avatars = $db->fetchAll(
                'SELECT * FROM avatars WHERE (tenant_id = ? OR is_global = 1) ORDER BY is_global DESC, name ASC',
                [$tenantId]
            );
            view('hr/avatars', ['avatars' => $avatars], 'hr');
            return;
        }

        // ── AI Analytics ──────────────────────────────────────────────────
        if ($path === '/ai-analytics' && $method === 'GET') {
            $usage = $db->fetchAll(
                'SELECT feature, SUM(total_tokens) AS total_tokens, COUNT(*) AS calls,
                        DATE(created_at) AS date
                 FROM ai_usage_logs
                 WHERE tenant_id = ?
                 GROUP BY feature, DATE(created_at)
                 ORDER BY date DESC',
                [$tenantId]
            );
            $totals = $db->fetch(
                'SELECT SUM(total_tokens) AS total_tokens, SUM(prompt_tokens) AS prompt_tokens,
                        SUM(completion_tokens) AS completion_tokens, COUNT(*) AS total_calls
                 FROM ai_usage_logs WHERE tenant_id = ?',
                [$tenantId]
            );
            view('hr/ai-analytics', ['usage' => $usage, 'totals' => $totals], 'hr');
            return;
        }

        // ── Users ─────────────────────────────────────────────────────────
        if ($path === '/users' && $method === 'GET') {
            Auth::requirePermission('users.view');
            $users = $db->fetchAll(
                'SELECT u.*, r.name AS role_name
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.user_id = u.id
                 LEFT JOIN roles r ON r.id = ur.role_id
                 WHERE u.tenant_id = ?
                 ORDER BY u.created_at DESC',
                [$tenantId]
            );
            view('hr/users', ['users' => $users], 'hr');
            return;
        }

        // ── Roles ─────────────────────────────────────────────────────────
        if ($path === '/roles' && $method === 'GET') {
            Auth::requirePermission('roles.view');
            $roles = $db->fetchAll(
                'SELECT r.*, COUNT(ur.user_id) AS user_count
                 FROM roles r
                 LEFT JOIN user_roles ur ON ur.role_id = r.id
                 WHERE r.tenant_id = ?
                 GROUP BY r.id
                 ORDER BY r.name ASC',
                [$tenantId]
            );
            view('hr/roles', ['roles' => $roles], 'hr');
            return;
        }

        // ── Settings ──────────────────────────────────────────────────────
        if ($path === '/settings' && $method === 'GET') {
            Auth::requirePermission('settings.manage');
            $settings = $db->fetchAll(
                'SELECT * FROM system_settings WHERE tenant_id = ?',
                [$tenantId]
            );
            $settingsMap = [];
            foreach ($settings as $s) {
                $settingsMap[$s['key']] = $s['value'];
            }
            view('hr/settings', ['settings' => $settingsMap], 'hr');
            return;
        }

        // ── Profile ───────────────────────────────────────────────────────
        if ($path === '/profile' && $method === 'GET') {
            $user = $db->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [Auth::id()]);
            view('hr/profile', ['user' => $user], 'hr');
            return;
        }

        self::notFound();
    }

    private static function notFound(): void
    {
        http_response_code(404);
        view('errors/404', [], 'hr');
    }
}
