<?php
class HRRouter {
    public static function dispatch(string $path, string $method, Request $request): void {
        $tenant = null;
        if ($tenantSlug = ($_SESSION['user']['tenant_slug'] ?? null)) {
            $db = Database::getInstance();
            $tenant = $db->fetch("SELECT * FROM tenants WHERE slug = ? AND status = 'active'", [$tenantSlug]);
            if ($tenant) $db->setTenantId((int)$tenant['id']);
        }

        match(true) {
            $path === '/dashboard'               => self::render('hr/dashboard', ['pageTitle'=>'Dashboard', 'tenant'=>$tenant]),
            $path === '/jobs'                    => self::render('hr/jobs/index', ['pageTitle'=>'Jobs', 'tenant'=>$tenant]),
            $path === '/jobs/create'             => self::render('hr/jobs/create', ['pageTitle'=>'New Job', 'tenant'=>$tenant]),
            preg_match('#^/jobs/(\d+)$#', $path, $m) => self::render('hr/jobs/show', ['pageTitle'=>'Job', 'id'=>(int)$m[1], 'tenant'=>$tenant]),
            $path === '/candidates/compare'      => self::render('hr/candidates/compare', ['pageTitle'=>'Compare Candidates', 'tenant'=>$tenant]),
            $path === '/pipeline'                => self::render('hr/pipeline', ['pageTitle'=>'Pipeline', 'tenant'=>$tenant]),
            $path === '/candidates'              => self::render('hr/candidates/index', ['pageTitle'=>'Candidates', 'tenant'=>$tenant]),
            $path === '/ai-interviews'           => self::render('hr/interviews/index', ['pageTitle'=>'AI Interviews', 'tenant'=>$tenant]),
            $path === '/human-interviews'        => self::render('hr/human-interviews', ['pageTitle'=>'Human Interviews', 'tenant'=>$tenant]),
            $path === '/offers'                  => self::render('hr/offers', ['pageTitle'=>'Offers', 'tenant'=>$tenant]),
            $path === '/talent-pool'             => self::render('hr/talent-pool', ['pageTitle'=>'Talent Pool', 'tenant'=>$tenant]),
            $path === '/avatars'                 => self::render('hr/avatars', ['pageTitle'=>'Avatars', 'tenant'=>$tenant]),
            $path === '/ai-analytics'            => self::render('hr/ai-analytics', ['pageTitle'=>'AI Analytics', 'tenant'=>$tenant]),
            $path === '/users'                   => self::render('hr/users', ['pageTitle'=>'Users', 'tenant'=>$tenant]),
            $path === '/roles'                   => self::render('hr/roles', ['pageTitle'=>'Roles & Permissions', 'tenant'=>$tenant]),
            $path === '/settings'                => self::render('hr/settings', ['pageTitle'=>'Settings', 'tenant'=>$tenant]),
            $path === '/profile'                 => self::render('hr/profile', ['pageTitle'=>'My Profile', 'tenant'=>$tenant]),
            preg_match('#^/candidates/(\d+)$#', $path, $m) => self::render('hr/candidates/show', ['pageTitle'=>'Candidate', 'id'=>(int)$m[1], 'tenant'=>$tenant]),
            preg_match('#^/ai-interviews/(\d+)$#', $path, $m) => self::render('hr/interviews/report', ['pageTitle'=>'Interview Report', 'id'=>(int)$m[1], 'tenant'=>$tenant]),
            default => (function() { http_response_code(404); echo "Page not found"; })()
        };
    }

    private static function render(string $view, array $data): void {
        global $request;
        $data['request'] = $request;
        $data['user'] = Auth::user();
        extract($data);
        $viewFile = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            $content = "<p class='p-8 text-gray-500'>View coming soon: {$view}</p>";
            require VIEWS_PATH . '/layouts/app.php';
        }
    }
}
