<?php

class CandidateRouter
{
    public static function dispatch(string $path, string $method, Request $req): void
    {
        Auth::requireAuth();

        $db          = Database::getInstance();
        $candidateId = Auth::id();

        // Strip trailing slash
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // ── Dashboard ──────────────────────────────────────────────────────
        if ($path === '/c/dashboard' && $method === 'GET') {
            $applications = $db->fetchAll(
                'SELECT a.*, j.title AS job_title, j.location, t.name AS company_name
                 FROM applications a
                 JOIN jobs j ON j.id = a.job_id
                 JOIN tenants t ON t.id = a.tenant_id
                 WHERE a.candidate_id = ?
                 ORDER BY a.applied_at DESC LIMIT 5',
                [$candidateId]
            );

            $stats = [
                'total_applications' => $db->fetchColumn(
                    'SELECT COUNT(*) FROM applications WHERE candidate_id = ?',
                    [$candidateId]
                ),
                'pending_interviews' => $db->fetchColumn(
                    "SELECT COUNT(*) FROM human_interviews hi
                     JOIN applications a ON a.id = hi.application_id
                     WHERE a.candidate_id = ? AND hi.status = 'scheduled'",
                    [$candidateId]
                ),
                'pending_offers' => $db->fetchColumn(
                    "SELECT COUNT(*) FROM offers o
                     JOIN applications a ON a.id = o.application_id
                     WHERE a.candidate_id = ? AND o.status = 'sent'",
                    [$candidateId]
                ),
            ];

            $user = $db->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$candidateId]);

            view('candidate/dashboard', [
                'user'         => $user,
                'applications' => $applications,
                'stats'        => $stats,
            ], 'candidate');
            return;
        }

        // ── Job Listings ──────────────────────────────────────────────────
        if ($path === '/c/jobs' && $method === 'GET') {
            $search   = trim((string) $req->get('search', ''));
            $location = trim((string) $req->get('location', ''));
            $page     = max(1, (int) $req->get('page', 1));

            $where  = "j.status = 'published'";
            $params = [];

            if ($search !== '') {
                $where   .= ' AND (j.title LIKE ? OR j.description LIKE ?)';
                $like     = "%{$search}%";
                $params[] = $like;
                $params[] = $like;
            }

            if ($location !== '') {
                $where   .= ' AND j.location LIKE ?';
                $params[] = "%{$location}%";
            }

            $result = $db->paginate(
                "SELECT j.*, t.name AS company_name, t.logo_url AS company_logo
                 FROM jobs j
                 JOIN tenants t ON t.id = j.tenant_id
                 WHERE {$where}
                 ORDER BY j.created_at DESC",
                $params,
                $page,
                20
            );

            view('candidate/jobs', [
                'jobs'       => $result['data'],
                'pagination' => $result,
                'search'     => $search,
                'location'   => $location,
            ], 'candidate');
            return;
        }

        // ── Applications ──────────────────────────────────────────────────
        if ($path === '/c/applications' && $method === 'GET') {
            $applications = $db->fetchAll(
                'SELECT a.*, j.title AS job_title, j.location, t.name AS company_name, t.logo_url AS company_logo,
                        ai.overall_score AS ai_score_detail, ai.recommendation
                 FROM applications a
                 JOIN jobs j ON j.id = a.job_id
                 JOIN tenants t ON t.id = a.tenant_id
                 LEFT JOIN ai_interviews ai ON ai.application_id = a.id
                 WHERE a.candidate_id = ?
                 ORDER BY a.applied_at DESC',
                [$candidateId]
            );

            view('candidate/applications', ['applications' => $applications], 'candidate');
            return;
        }

        // ── Profile ───────────────────────────────────────────────────────
        if ($path === '/c/profile' && $method === 'GET') {
            $user = $db->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$candidateId]);

            $profile = $db->fetch(
                'SELECT * FROM candidate_profiles WHERE user_id = ? LIMIT 1',
                [$candidateId]
            );

            view('candidate/profile', [
                'user'    => $user,
                'profile' => $profile,
            ], 'candidate');
            return;
        }

        // ── Offers ────────────────────────────────────────────────────────
        if ($path === '/c/offers' && $method === 'GET') {
            $offers = $db->fetchAll(
                'SELECT o.*, j.title AS job_title, t.name AS company_name
                 FROM offers o
                 JOIN applications a ON a.id = o.application_id
                 JOIN jobs j ON j.id = a.job_id
                 JOIN tenants t ON t.id = o.tenant_id
                 WHERE a.candidate_id = ?
                 ORDER BY o.created_at DESC',
                [$candidateId]
            );

            view('candidate/offers', ['offers' => $offers], 'candidate');
            return;
        }

        // ── Notifications ─────────────────────────────────────────────────
        if ($path === '/c/notifications' && $method === 'GET') {
            $notifications = $db->fetchAll(
                'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50',
                [$candidateId]
            );

            // Mark as read
            $db->query(
                'UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL',
                [$candidateId]
            );

            view('candidate/notifications', ['notifications' => $notifications], 'candidate');
            return;
        }

        self::notFound();
    }

    private static function notFound(): void
    {
        http_response_code(404);
        view('errors/404', [], 'candidate');
    }
}
