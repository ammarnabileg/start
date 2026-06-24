<?php
class SuperAdminRouter {
    public static function dispatch(string $path, string $method, Request $request): void {
        match(true) {
            $path === '/super/dashboard' => self::render('super-admin/dashboard', ['pageTitle'=>'Super Admin Dashboard']),
            $path === '/super/companies' => self::render('super-admin/companies', ['pageTitle'=>'Companies']),
            $path === '/super/users'     => self::render('super-admin/users', ['pageTitle'=>'All Users']),
            $path === '/super/terminal'  => self::render('super-admin/terminal', ['pageTitle'=>'Terminal']),
            $path === '/super/ai-usage'  => self::render('super-admin/ai-analytics', ['pageTitle'=>'AI Analytics']),
            $path === '/super/settings'  => self::render('super-admin/settings', ['pageTitle'=>'System Settings']),
            default => Response::redirect('/super/dashboard')
        };
    }
    private static function render(string $view, array $data): void {
        global $request;
        $data['request'] = $request;
        $data['user'] = Auth::user();
        extract($data);
        $viewFile = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';
        ob_start();
        if (file_exists($viewFile)) require $viewFile;
        else echo "<p class='p-8 text-gray-500'>View coming soon: {$view}</p>";
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }
}
