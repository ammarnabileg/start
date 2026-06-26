<?php
declare(strict_types=1);

require_once MODULES_PATH . '/SuperAdmin/SuperAdminController.php';

class SuperAdminRouter
{
    public static function dispatch(Request $r, string $path, string $method): void
    {
        if (!Auth::isSuper()) {
            http_response_code(403);
            renderView('errors/403', [], 'app');
            return;
        }

        if ($method === 'GET' && $path === '/super/dashboard') { SuperAdminController::dashboard($r); return; }
        if ($method === 'GET' && $path === '/super/companies') { SuperAdminController::companies($r); return; }
        if ($method === 'POST' && $path === '/super/companies/create') { SuperAdminController::createCompany($r); return; }
        if (preg_match('#^/super/companies/(\d+)/suspend$#', $path, $m) && $method === 'POST') { SuperAdminController::suspendCompany($r, (int)$m[1]); return; }
        if (preg_match('#^/super/companies/(\d+)$#', $path, $m) && $method === 'GET') { SuperAdminController::showCompany($r, (int)$m[1]); return; }
        if ($method === 'GET' && $path === '/super/users') { SuperAdminController::users($r); return; }
        if ($method === 'POST' && $path === '/super/users/create') { SuperAdminController::createUser($r); return; }
        if ($method === 'GET' && $path === '/super/analytics') { SuperAdminController::analytics($r); return; }
        if ($method === 'GET' && $path === '/super/ai-analytics') { SuperAdminController::aiAnalytics($r); return; }
        if ($path === '/super/settings') { SuperAdminController::platformSettings($r); return; }

        // Fallback to dashboard
        if ($path === '/super' || $path === '/super/') { SuperAdminController::dashboard($r); return; }

        http_response_code(404);
        renderView('errors/404', [], 'app');
    }
}
