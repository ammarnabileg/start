<?php
declare(strict_types=1);

// ── Setup must run before bootstrap (no .env yet) ─────────────────────────────
$_earlyPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with($_earlyPath, '/setup')) {
    if (file_exists(__DIR__ . '/setup/index.php')) require __DIR__ . '/setup/index.php';
    else echo 'Setup not available.';
    exit;
}

require __DIR__ . '/bootstrap.php';

$req    = new Request();
$path   = $req->path();
$method = $req->method();

// ── Helper: render view inside a layout ──────────────────────────────────────
function view(string $tpl, array $data = [], string $layout = 'app'): void {
    global $req;
    $data['req']  = $req;
    $data['user'] = Auth::user();
    extract($data, EXTR_SKIP);
    $file = VIEWS_PATH . '/' . str_replace('.', '/', $tpl) . '.php';
    if (!file_exists($file)) {
        http_response_code(404);
        echo "<h1 style='font-family:sans-serif;padding:2rem'>404 — View not found: {$tpl}</h1>";
        return;
    }
    ob_start();
    require $file;
    $content = ob_get_clean();
    $layoutFile = VIEWS_PATH . '/layouts/' . $layout . '.php';
    if (file_exists($layoutFile)) require $layoutFile;
    else echo $content;
}

// ── API ───────────────────────────────────────────────────────────────────────
if (str_starts_with($path, '/api/v1')) {
    require __DIR__ . '/api/v1/index.php';
    exit;
}

// ── Public: Landing ───────────────────────────────────────────────────────────
if ($path === '/' || $path === '') {
    if (Auth::check()) {
        $u = Auth::user();
        match($u['type']) {
            'super_admin' => Response::redirect('/super/dashboard'),
            'candidate'   => Response::redirect('/c/dashboard'),
            default       => Response::redirect('/dashboard'),
        };
    }
    require VIEWS_PATH . '/landing.php';
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
if ($path === '/login') {
    if (Auth::check()) {
        $u = Auth::user();
        match($u['type']) {
            'super_admin' => Response::redirect('/super/dashboard'),
            'candidate'   => Response::redirect('/c/dashboard'),
            default       => Response::redirect('/dashboard'),
        };
    }
    if ($method === 'POST') {
        require MODULES_PATH . '/Auth/AuthController.php';
        AuthController::login($req);
    }
    view('auth/login', ['pageTitle' => 'Sign In'], 'auth');
    exit;
}

if ($path === '/logout') {
    Auth::logout();
    Response::redirect('/login');
}

if ($path === '/register') {
    if ($method === 'POST') {
        require MODULES_PATH . '/Auth/AuthController.php';
        AuthController::register($req);
    }
    view('auth/register', ['pageTitle' => 'Create Account'], 'auth');
    exit;
}

// ── Public: Interview Room (token auth) ───────────────────────────────────────
if (preg_match('#^/interview/([A-Za-z0-9_-]+)$#', $path, $m)) {
    require MODULES_PATH . '/Interviews/InterviewRoomController.php';
    InterviewRoomController::show($m[1], $req);
    exit;
}

// ── Public: Career page ───────────────────────────────────────────────────────
if (str_starts_with($path, '/careers')) {
    require MODULES_PATH . '/Auth/AuthController.php';
    AuthController::careers($path, $req);
    exit;
}

// ── Error pages ───────────────────────────────────────────────────────────────
if ($path === '/403') { http_response_code(403); view('errors/403', ['pageTitle' => 'Access Denied'], 'auth'); exit; }
if ($path === '/404') { http_response_code(404); view('errors/404', ['pageTitle' => 'Not Found'], 'auth'); exit; }

// ── Require login from here ───────────────────────────────────────────────────
Auth::requireAuth('/login');
$user = Auth::user();

// ── Super Admin ───────────────────────────────────────────────────────────────
if (str_starts_with($path, '/super')) {
    Auth::requireSuper();
    require MODULES_PATH . '/SuperAdmin/SuperAdminRouter.php';
    SuperAdminRouter::dispatch($path, $method, $req);
    exit;
}

// ── Candidate Portal ──────────────────────────────────────────────────────────
if (str_starts_with($path, '/c/') || $path === '/c') {
    if (!Auth::isCandidate()) Response::redirect('/dashboard');
    require MODULES_PATH . '/Candidates/CandidateRouter.php';
    CandidateRouter::dispatch($path, $method, $req);
    exit;
}

// ── HR ────────────────────────────────────────────────────────────────────────
if (!Auth::isHR() && !Auth::isSuper()) Response::redirect('/login');
require MODULES_PATH . '/HR/HRRouter.php';
HRRouter::dispatch($path, $method, $req);
