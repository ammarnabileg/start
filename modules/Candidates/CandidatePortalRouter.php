<?php
class CandidatePortalRouter {
    public static function dispatch(string $path, string $method, Request $request): void {
        match(true) {
            $path === '/c/dashboard'    => self::render('candidate/dashboard', ['pageTitle'=>'My Dashboard']),
            $path === '/c/jobs'         => self::render('candidate/jobs', ['pageTitle'=>'Available Jobs']),
            $path === '/c/applications' => self::render('candidate/applications', ['pageTitle'=>'My Applications']),
            $path === '/c/profile'      => self::render('candidate/profile', ['pageTitle'=>'My Profile']),
            $path === '/c/offers'       => self::render('candidate/offers', ['pageTitle'=>'My Offers']),
            default => Response::redirect('/c/dashboard')
        };
    }
    private static function render(string $view, array $data): void {
        global $request;
        $data['request'] = $request; $data['user'] = Auth::user(); extract($data);
        $viewFile = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';
        ob_start();
        if (file_exists($viewFile)) require $viewFile;
        else echo "<p class='p-8 text-gray-500'>View coming soon: {$view}</p>";
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/candidate.php';
    }
}
