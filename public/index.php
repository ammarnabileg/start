<?php
declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));
define('VIEWS_PATH', ROOT_DIR . '/views');
define('CORE_PATH', ROOT_DIR . '/core');
define('MODULES_PATH', ROOT_DIR . '/modules');

// Load core files
require ROOT_DIR . '/core/Env.php';
Env::load(ROOT_DIR . '/.env');

// Check installation
if (!file_exists(ROOT_DIR . '/.installed')) {
    header('Location: /setup/');
    exit;
}

require ROOT_DIR . '/core/Database.php';
require ROOT_DIR . '/core/JWT.php';
require ROOT_DIR . '/core/Auth.php';
require ROOT_DIR . '/core/Cache.php';
require ROOT_DIR . '/core/Request.php';
require ROOT_DIR . '/core/Response.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>86400,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

$request = new Request();
$path = $request->path();
$method = $request->method();

// ── Routing ──────────────────────────────────────────────

// API routes
if (str_starts_with($path, '/api/v1')) {
    $apiPath = ROOT_DIR . '/api/v1/index.php';
    if (file_exists($apiPath)) { require $apiPath; exit; }
    Response::error('API not found', 404);
}

// Render a view with the app layout
function renderView(string $view, array $data = [], string $layout = 'app'): void {
    global $request;
    $data['request'] = $request;
    $data['user'] = Auth::user();
    extract($data);
    $viewFile = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';
    if (!file_exists($viewFile)) { http_response_code(404); echo "View not found: {$view}"; return; }
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    $layoutFile = VIEWS_PATH . '/layouts/' . $layout . '.php';
    if (file_exists($layoutFile)) require $layoutFile;
    else echo $content;
}

// ── Auth Routes ──────────────────────────────────────────
if ($path === '/login' || $path === '') {
    if ($method === 'POST') {
        require MODULES_PATH . '/Auth/AuthController.php';
        AuthController::login($request);
    } else {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user['type'] === 'super_admin') { header('Location: /super/dashboard'); exit; }
            if ($user['type'] === 'candidate') { header('Location: /c/dashboard'); exit; }
            header('Location: /dashboard'); exit;
        }
        renderView('auth/login', ['pageTitle' => 'Sign In'], 'auth');
    }
    exit;
}

if ($path === '/logout') {
    Auth::logout();
    header('Location: /login');
    exit;
}

if ($path === '/register') {
    require MODULES_PATH . '/Auth/CandidateAuthController.php';
    if ($method === 'POST') CandidateAuthController::register($request);
    else renderView('candidate/register', ['pageTitle' => 'Create Account'], 'auth');
    exit;
}

// ── Interview Room (public with token) ───────────────────
if (preg_match('#^/interview/([a-zA-Z0-9_-]+)$#', $path, $m)) {
    $token = $m[1];
    require MODULES_PATH . '/Interviews/InterviewRoomController.php';
    InterviewRoomController::show($token, $request);
    exit;
}

// ── Career Page (public) ─────────────────────────────────
if (str_starts_with($path, '/careers')) {
    require MODULES_PATH . '/Company/CareerController.php';
    CareerController::handle($path, $request);
    exit;
}

// ── Auth Required from here ───────────────────────────────
Auth::requireAuth('/login');
$user = Auth::user();

// ── Super Admin Routes ────────────────────────────────────
if (str_starts_with($path, '/super')) {
    Auth::requireSuper();
    require MODULES_PATH . '/SuperAdmin/SuperAdminRouter.php';
    SuperAdminRouter::dispatch($path, $method, $request);
    exit;
}

// ── Candidate Routes ──────────────────────────────────────
if (str_starts_with($path, '/c/')) {
    if (!Auth::isCandidate()) { header('Location: /dashboard'); exit; }
    require MODULES_PATH . '/Candidates/CandidatePortalRouter.php';
    CandidatePortalRouter::dispatch($path, $method, $request);
    exit;
}

// ── HR / Company Routes ───────────────────────────────────
require MODULES_PATH . '/HR/HRRouter.php';
HRRouter::dispatch($path, $method, $request);
