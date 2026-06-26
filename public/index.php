<?php
declare(strict_types=1);

define('ROOT_DIR',    dirname(__DIR__));
define('VIEWS_PATH',  ROOT_DIR . '/views');
define('CORE_PATH',   ROOT_DIR . '/core');
define('MODULES_PATH',ROOT_DIR . '/modules');
define('STORAGE_PATH',ROOT_DIR . '/storage');
define('UPLOAD_PATH', ROOT_DIR . '/storage/uploads');

// Load core
foreach (['Env','Database','JWT','Auth','Cache','Request','Response','Validator','RBAC','Tenant','Audit','ApiKeyManager'] as $c) {
    require CORE_PATH . "/{$c}.php";
}

// Load view helpers
require VIEWS_PATH . '/partials/helpers.php';

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Modules\\', 8) !== 0) return;
    $f = MODULES_PATH . '/' . str_replace('\\', '/', substr($class, 8)) . '.php';
    if (is_file($f)) require $f;
});

// Check installation
if (!file_exists(ROOT_DIR . '/.installed')) {
    header('Location: /setup/'); exit;
}

// Load environment
Env::load(ROOT_DIR . '/.env');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>86400,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

$request = new Request();
$path    = $request->path();
$method  = $request->method();

// ── Render helper ──────────────────────────────────────────
function renderView(string $view, array $data = [], string $layout = 'app'): void
{
    global $request;
    $data['request']  = $request;
    $data['user']     = Auth::user();
    $data['pageTitle'] = $data['pageTitle'] ?? 'AI Recruitment';
    extract($data);
    $viewFile = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';
    if (!file_exists($viewFile)) {
        http_response_code(404);
        echo "<h1>View not found: {$view}</h1>";
        return;
    }
    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    $layoutFile = VIEWS_PATH . '/layouts/' . $layout . '.php';
    if ($layoutFile && file_exists($layoutFile)) require $layoutFile;
    else echo $content;
}

// ── Require module ────────────────────────────────────────
function loadModule(string $path): void
{
    $f = MODULES_PATH . '/' . $path . '.php';
    if (file_exists($f)) require $f;
    else { http_response_code(500); echo "Module not found: {$path}"; exit; }
}

// ═══════════════════════════════════════════════════════════
//  ROUTING
// ═══════════════════════════════════════════════════════════

// ── Auth ────────────────────────────────────────────────────
if ($path === '/login' || $path === '') {
    if ($method === 'POST') {
        loadModule('Auth/AuthController');
        AuthController::login($request);
    } else {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user['type'] === 'super_admin')    { header('Location: /super/dashboard'); exit; }
            if ($user['type'] === 'candidate')       { header('Location: /c/dashboard');    exit; }
            header('Location: /dashboard'); exit;
        }
        renderView('auth/login', ['pageTitle' => 'Sign In'], 'auth');
    }
    exit;
}

if ($path === '/logout') {
    Auth::logout();
    header('Location: /login'); exit;
}

if ($path === '/forgot-password') {
    loadModule('Auth/AuthController');
    AuthController::forgotPassword($request);
    exit;
}

if (preg_match('#^/reset-password/([a-zA-Z0-9]+)$#', $path, $m)) {
    loadModule('Auth/AuthController');
    AuthController::resetPassword($request, $m[1]);
    exit;
}

// ── Onboarding ──────────────────────────────────────────────
if ($path === '/onboarding') {
    Auth::requireAuth();
    loadModule('Auth/AuthController');
    AuthController::onboarding($request);
    exit;
}

// ── Career Page (public) ────────────────────────────────────
if (preg_match('#^/careers/([a-z0-9\-]+)$#', $path, $m)) {
    loadModule('Company/CareerController');
    CareerController::index($request, $m[1]);
    exit;
}

if (preg_match('#^/careers/([a-z0-9\-]+)/apply/(\d+)$#', $path, $m)) {
    loadModule('Company/CareerController');
    CareerController::apply($request, $m[1], (int)$m[2]);
    exit;
}

// ── Interview Room (public) ──────────────────────────────────
if (preg_match('#^/interview/([a-zA-Z0-9]+)$#', $path, $m)) {
    loadModule('Interviews/InterviewRoomController');
    InterviewRoomController::show($request, $m[1]);
    exit;
}

if (preg_match('#^/interview/([a-zA-Z0-9]+)/guest$#', $path, $m)) {
    loadModule('Interviews/InterviewRoomController');
    InterviewRoomController::saveGuestInfo($request, $m[1]);
    exit;
}

if (preg_match('#^/interview/([a-zA-Z0-9]+)/start$#', $path, $m)) {
    loadModule('Interviews/InterviewRoomController');
    InterviewRoomController::start($request, $m[1]);
    exit;
}

if (preg_match('#^/interview/([a-zA-Z0-9]+)/message$#', $path, $m)) {
    loadModule('Interviews/InterviewRoomController');
    InterviewRoomController::sendMessage($request, $m[1]);
    exit;
}

// ── Career page with /jobs/{id} pattern ────────────────────
if (preg_match('#^/careers/([a-z0-9\-]+)/jobs/(\d+)/apply$#', $path, $m)) {
    loadModule('Company/CareerController');
    CareerController::apply($request, $m[1], (int)$m[2]);
    exit;
}

// ── Candidate Portal ─────────────────────────────────────────
if (str_starts_with($path, '/c/') || $path === '/c') {
    Auth::requireAuth();
    if (!Auth::isCandidate()) { header('Location: /dashboard'); exit; }
    loadModule('Candidates/CandidatePortalRouter');
    CandidatePortalRouter::dispatch($request, $path, $method);
    exit;
}

// ── Candidate Registration / Login ───────────────────────────
if ($path === '/register') {
    loadModule('Auth/CandidateAuthController');
    CandidateAuthController::register($request);
    exit;
}

// ── Super Admin ───────────────────────────────────────────────
if (str_starts_with($path, '/super/') || $path === '/super') {
    Auth::requireAuth();
    if (!Auth::isSuper()) { header('Location: /dashboard'); exit; }
    loadModule('SuperAdmin/SuperAdminRouter');
    SuperAdminRouter::dispatch($request, $path, $method);
    exit;
}

// ── HR / Company ──────────────────────────────────────────────
if (in_array($path, ['/dashboard','/jobs','/candidates','/pipeline','/ai-interviews',
    '/human-interviews','/offers','/talent-pool','/avatars','/users','/roles','/settings',
    '/analytics','/reports','/comparisons']) || preg_match('#^/(dashboard|jobs|candidates|pipeline|ai-interviews|human-interviews|offers|talent-pool|avatars|users|roles|settings|analytics|reports|comparisons)(/.*)?$#', $path)) {
    Auth::requireAuth();
    if (Auth::isCandidate()) { header('Location: /c/dashboard'); exit; }
    loadModule('HR/HRRouter');
    HRRouter::dispatch($request, $path, $method);
    exit;
}

// ── API ───────────────────────────────────────────────────────
if (str_starts_with($path, '/api/v1')) {
    require ROOT_DIR . '/api/v1/index.php';
    exit;
}

// ── 404 ────────────────────────────────────────────────────────
http_response_code(404);
renderView('errors/404', ['pageTitle' => '404 Not Found'], Auth::check() ? 'app' : 'auth');
