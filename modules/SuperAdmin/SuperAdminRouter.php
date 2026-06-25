<?php

class SuperAdminRouter
{
    public static function dispatch(string $path, string $method, Request $req): void
    {
        Auth::requireSuper();

        $db = Database::getInstance();

        // Strip trailing slash
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // ── Dashboard ──────────────────────────────────────────────────────
        if ($path === '/super/dashboard' && $method === 'GET') {
            $stats = [
                'total_tenants'      => $db->fetchColumn("SELECT COUNT(*) FROM tenants"),
                'active_tenants'     => $db->fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'active'"),
                'total_users'        => $db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_super_admin = 0"),
                'total_applications' => $db->fetchColumn("SELECT COUNT(*) FROM applications"),
                'total_ai_calls'     => $db->fetchColumn("SELECT COUNT(*) FROM ai_usage_logs"),
                'total_tokens'       => $db->fetchColumn("SELECT COALESCE(SUM(total_tokens), 0) FROM ai_usage_logs"),
            ];

            $recentTenants = $db->fetchAll(
                'SELECT t.*, COUNT(u.id) AS user_count
                 FROM tenants t
                 LEFT JOIN users u ON u.tenant_id = t.id
                 GROUP BY t.id
                 ORDER BY t.created_at DESC LIMIT 10'
            );

            view('super/dashboard', [
                'stats'         => $stats,
                'recentTenants' => $recentTenants,
            ], 'super');
            return;
        }

        // ── Companies ─────────────────────────────────────────────────────
        if ($path === '/super/companies' && $method === 'GET') {
            $page   = max(1, (int) $req->get('page', 1));
            $search = trim((string) $req->get('search', ''));

            $where  = '1=1';
            $params = [];

            if ($search !== '') {
                $where   .= ' AND (t.name LIKE ? OR t.slug LIKE ? OR t.email LIKE ?)';
                $like     = "%{$search}%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $result = $db->paginate(
                "SELECT t.*,
                        COUNT(DISTINCT u.id) AS user_count,
                        COUNT(DISTINCT j.id) AS job_count,
                        COUNT(DISTINCT a.id) AS application_count
                 FROM tenants t
                 LEFT JOIN users u ON u.tenant_id = t.id AND u.is_super_admin = 0
                 LEFT JOIN jobs j ON j.tenant_id = t.id
                 LEFT JOIN applications a ON a.tenant_id = t.id
                 WHERE {$where}
                 GROUP BY t.id
                 ORDER BY t.created_at DESC",
                $params,
                $page,
                20
            );

            view('super/companies', [
                'companies'  => $result['data'],
                'pagination' => $result,
                'search'     => $search,
            ], 'super');
            return;
        }

        // ── Users ─────────────────────────────────────────────────────────
        if ($path === '/super/users' && $method === 'GET') {
            $page   = max(1, (int) $req->get('page', 1));
            $search = trim((string) $req->get('search', ''));

            $where  = 'u.is_super_admin = 0';
            $params = [];

            if ($search !== '') {
                $where   .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
                $like     = "%{$search}%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $result = $db->paginate(
                "SELECT u.*, t.name AS tenant_name
                 FROM users u
                 LEFT JOIN tenants t ON t.id = u.tenant_id
                 WHERE {$where}
                 ORDER BY u.created_at DESC",
                $params,
                $page,
                20
            );

            view('super/users', [
                'users'      => $result['data'],
                'pagination' => $result,
                'search'     => $search,
            ], 'super');
            return;
        }

        // ── AI Usage ──────────────────────────────────────────────────────
        if ($path === '/super/ai-usage' && $method === 'GET') {
            $perTenant = $db->fetchAll(
                'SELECT t.name AS tenant_name, l.tenant_id,
                        SUM(l.total_tokens) AS total_tokens,
                        SUM(l.prompt_tokens) AS prompt_tokens,
                        SUM(l.completion_tokens) AS completion_tokens,
                        COUNT(*) AS total_calls
                 FROM ai_usage_logs l
                 JOIN tenants t ON t.id = l.tenant_id
                 GROUP BY l.tenant_id, t.name
                 ORDER BY total_tokens DESC'
            );

            $perFeature = $db->fetchAll(
                'SELECT feature,
                        SUM(total_tokens) AS total_tokens,
                        COUNT(*) AS total_calls
                 FROM ai_usage_logs
                 GROUP BY feature
                 ORDER BY total_tokens DESC'
            );

            $daily = $db->fetchAll(
                'SELECT DATE(created_at) AS date,
                        SUM(total_tokens) AS total_tokens,
                        COUNT(*) AS total_calls
                 FROM ai_usage_logs
                 GROUP BY DATE(created_at)
                 ORDER BY date DESC LIMIT 30'
            );

            view('super/ai-usage', [
                'perTenant'  => $perTenant,
                'perFeature' => $perFeature,
                'daily'      => $daily,
            ], 'super');
            return;
        }

        // ── Settings ──────────────────────────────────────────────────────
        if ($path === '/super/settings' && $method === 'GET') {
            $settings = $db->fetchAll(
                'SELECT * FROM system_settings WHERE tenant_id IS NULL ORDER BY key ASC'
            );
            $settingsMap = [];
            foreach ($settings as $s) {
                $settingsMap[$s['key']] = $s['value'];
            }

            view('super/settings', ['settings' => $settingsMap], 'super');
            return;
        }

        // Default: redirect to dashboard
        Response::redirect('/super/dashboard');
    }
}
