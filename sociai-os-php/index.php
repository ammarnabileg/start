<?php
/**
 * SociAI OS - Front Controller
 * Single entry point for all HTTP requests.
 * PHP 8.1+ required.
 */

declare(strict_types=1);

// ============================================================
// PHP Version Guard
// ============================================================
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    die("SociAI OS requires PHP 8.1 or higher. Current: " . PHP_VERSION);
}

// ============================================================
// Paths & Autoloading
// ============================================================
define('BASE_PATH', __DIR__);

// PSR-4-like autoloader for SociAI namespace
spl_autoload_register(function (string $class): void {
    // Map namespace prefixes to directories
    $map = [
        'SociAI\\Core\\'        => BASE_PATH . '/core/',
        'SociAI\\Models\\'      => BASE_PATH . '/models/',
        'SociAI\\Controllers\\' => BASE_PATH . '/controllers/',
        'SociAI\\Agents\\'      => BASE_PATH . '/agents/',
        'SociAI\\Api\\'         => BASE_PATH . '/api/',
        'SociAI\\Platforms\\'   => BASE_PATH . '/core/platforms/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file     = $dir . str_replace('\\', '/', $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
            return;
        }
    }
});

// ============================================================
// Bootstrap
// ============================================================
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/core/Database.php';
require_once BASE_PATH . '/core/Security.php';
require_once BASE_PATH . '/core/Auth.php';
require_once BASE_PATH . '/core/Request.php';
require_once BASE_PATH . '/core/Response.php';
require_once BASE_PATH . '/core/Router.php';

use SociAI\Core\{Auth, Router, Request, Response, Security, Database};

// Global helper
function abort(int $code, string $message = ''): never
{
    throw new \RuntimeException($message, $code);
}

// Start secure session
Auth::startSession();

// ============================================================
// Request & Response objects
// ============================================================
$request  = new Request();
$response = new Response();

// ============================================================
// Error / Exception handler
// ============================================================
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    if (!(error_reporting() & $errno)) return false;
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function(\Throwable $e) use ($request, $response): void {
    $isApi = $request->isAjax() || str_starts_with($request->uri(), '/api/');
    $code  = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;

    if (APP_DEBUG) {
        $detail = $e->getMessage() . "\n" . $e->getTraceAsString();
    } else {
        $detail = 'An unexpected error occurred.';
        error_log("[SociAI] Unhandled exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    }

    http_response_code($code);
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $detail, 'code' => $code]);
    } else {
        $title = match ($code) {
            404 => '404 - Not Found',
            403 => '403 - Forbidden',
            401 => '401 - Unauthorized',
            default => '500 - Server Error',
        };
        $viewFile = BASE_PATH . "/views/errors/{$code}.php";
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "<!DOCTYPE html><html><head><title>{$title}</title></head>";
            echo "<body><h1>{$title}</h1><pre>" . htmlspecialchars($detail) . "</pre></body></html>";
        }
    }
    exit;
});

// ============================================================
// Router Setup
// ============================================================
$router = new Router();

// --------------------------------------------------------
// Built-in Middleware Definitions
// --------------------------------------------------------
$router->use('auth', function(array $params, callable $next): void {
    Auth::requireAuth();
    $next();
});

$router->use('guest', function(array $params, callable $next): void {
    if (Auth::isLoggedIn()) {
        header('Location: /dashboard');
        exit;
    }
    $next();
});

$router->use('csrf', function(array $params, callable $next): void {
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST','PUT','PATCH','DELETE'], true)) {
        if (!Auth::validateCsrf()) {
            abort(419, 'CSRF token mismatch or expired.');
        }
    }
    $next();
});

$router->use('api', function(array $params, callable $next): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: ' . APP_URL);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // Try JWT bearer token first, then session
    $token = (new Request())->bearerToken();
    if ($token) {
        $payload = Auth::verifyToken($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
            exit;
        }
        // Inject user_id into session-like state
        $_SESSION['user_id']   = $payload['sub'] ?? null;
        $_SESSION['logged_in'] = true;
    } else {
        Auth::requireAuth();
    }
    $next();
});

$router->use('rate_limit_api', function(array $params, callable $next): void {
    $key = 'api_' . Security::getClientIp();
    $rl  = Security::rateLimit($key, ...RATE_LIMIT_API);
    header('X-RateLimit-Limit: ' . RATE_LIMIT_API['max']);
    header('X-RateLimit-Remaining: ' . $rl['remaining']);
    header('X-RateLimit-Reset: ' . $rl['reset_at']);
    if (!$rl['allowed']) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Rate limit exceeded.', 'reset_at' => $rl['reset_at']]);
        exit;
    }
    $next();
});

// ============================================================
// PUBLIC ROUTES (no auth required)
// ============================================================

// --- Setup Wizard (runs before .env exists, no auth, no DB) ---
$router->get('/setup',                  'SetupController@index');
$router->post('/setup/test-db',         'SetupController@testDB');
$router->post('/setup/test-openai',     'SetupController@testOpenAI');
$router->post('/setup/test-anthropic',  'SetupController@testAnthropic');
$router->post('/setup/save',            'SetupController@save');
$router->get('/setup/check',            'SetupController@check');

// --- Landing ---
$router->get('/', function(): void {
    require BASE_PATH . '/views/landing.php';
});

// --- Auth ---
$router->group('/auth', function(Router $r): void {
    $r->get('/login',    'AuthController@showLogin',    ['guest']);
    $r->post('/login',   'AuthController@login',        ['guest','csrf']);
    $r->get('/register', 'AuthController@showRegister', ['guest']);
    $r->post('/register','AuthController@register',     ['guest','csrf']);
    $r->post('/logout',  'AuthController@logout',       ['auth','csrf']);
    $r->get('/verify/{token}', 'AuthController@verifyEmail');
    $r->get('/forgot-password','AuthController@showForgot',  ['guest']);
    $r->post('/forgot-password','AuthController@forgotPassword', ['guest','csrf']);
    $r->get('/reset-password/{token}', 'AuthController@showReset');
    $r->post('/reset-password', 'AuthController@resetPassword', ['csrf']);
    $r->get('/2fa',      'AuthController@show2FA');
    $r->post('/2fa',     'AuthController@verify2FA',    ['csrf']);
});

// --- OAuth callbacks ---
$router->group('/oauth', function(Router $r): void {
    $r->get('/{platform}/connect',  'OAuthController@connect',  ['auth']);
    $r->get('/{platform}/callback', 'OAuthController@callback');
    $r->post('/{platform}/disconnect','OAuthController@disconnect',['auth','csrf']);
});

// ============================================================
// AUTHENTICATED ROUTES
// ============================================================
$router->group('', function(Router $r): void {

    // --- Dashboard ---
    $r->get('/dashboard',             'DashboardController@index',       ['auth']);
    $r->get('/dashboard/content',     'DashboardController@content',     ['auth']);
    $r->get('/dashboard/strategy',    'DashboardController@strategy',    ['auth']);
    $r->get('/dashboard/copywriting', 'DashboardController@copywriting', ['auth']);
    $r->get('/dashboard/analytics',   'DashboardController@analytics',   ['auth']);
    $r->get('/dashboard/campaigns',   'DashboardController@campaigns',   ['auth']);
    $r->get('/dashboard/community',   'DashboardController@community',   ['auth']);
    $r->get('/dashboard/trends',      'DashboardController@trends',      ['auth']);
    $r->get('/dashboard/agents',      'DashboardController@agents',      ['auth']);
    $r->get('/dashboard/team',        'DashboardController@team',        ['auth']);
    $r->get('/dashboard/settings',    'DashboardController@settings',    ['auth']);

    // --- Brands ---
    $r->get('/brands',              'BrandController@index',     ['auth']);
    $r->get('/brands/create',       'BrandController@create',    ['auth']);
    $r->post('/brands',             'BrandController@store',     ['auth','csrf']);
    $r->get('/brands/{slug}',       'BrandController@show',      ['auth']);
    $r->get('/brands/{slug}/edit',  'BrandController@edit',      ['auth']);
    $r->post('/brands/{slug}',      'BrandController@update',    ['auth','csrf']);
    $r->post('/brands/{slug}/delete','BrandController@delete',   ['auth','csrf']);

    // --- Team ---
    $r->get('/brands/{slug}/team',          'TeamController@index',   ['auth']);
    $r->post('/brands/{slug}/team/invite',  'TeamController@invite',  ['auth','csrf']);
    $r->post('/brands/{slug}/team/{userId}/role', 'TeamController@updateRole', ['auth','csrf']);
    $r->post('/brands/{slug}/team/{userId}/remove','TeamController@remove',   ['auth','csrf']);

    // --- Campaigns ---
    $r->get('/brands/{slug}/campaigns',          'CampaignController@index',  ['auth']);
    $r->get('/brands/{slug}/campaigns/create',   'CampaignController@create', ['auth']);
    $r->post('/brands/{slug}/campaigns',         'CampaignController@store',  ['auth','csrf']);
    $r->get('/brands/{slug}/campaigns/{id}',     'CampaignController@show',   ['auth']);
    $r->post('/brands/{slug}/campaigns/{id}',    'CampaignController@update', ['auth','csrf']);

    // --- Content ---
    $r->get('/brands/{slug}/content',             'ContentController@index',   ['auth']);
    $r->get('/brands/{slug}/content/create',      'ContentController@create',  ['auth']);
    $r->post('/brands/{slug}/content',            'ContentController@store',   ['auth','csrf']);
    $r->get('/brands/{slug}/content/{id}',        'ContentController@show',    ['auth']);
    $r->get('/brands/{slug}/content/{id}/edit',   'ContentController@edit',    ['auth']);
    $r->post('/brands/{slug}/content/{id}',       'ContentController@update',  ['auth','csrf']);
    $r->post('/brands/{slug}/content/{id}/approve','ContentController@approve',['auth','csrf']);
    $r->post('/brands/{slug}/content/{id}/reject', 'ContentController@reject', ['auth','csrf']);
    $r->post('/brands/{slug}/content/{id}/schedule','ContentController@schedule',['auth','csrf']);

    // --- Content Calendar ---
    $r->get('/brands/{slug}/calendar', 'CalendarController@index', ['auth']);

    // --- Analytics ---
    $r->get('/brands/{slug}/analytics',           'AnalyticsController@index',    ['auth']);
    $r->get('/brands/{slug}/analytics/platforms', 'AnalyticsController@platforms', ['auth']);
    $r->get('/brands/{slug}/analytics/posts',     'AnalyticsController@posts',    ['auth']);

    // --- Community ---
    $r->get('/brands/{slug}/community',              'CommunityController@index',  ['auth']);
    $r->post('/brands/{slug}/community/{id}/reply',  'CommunityController@reply',  ['auth','csrf']);
    $r->post('/brands/{slug}/community/{id}/ignore', 'CommunityController@ignore', ['auth','csrf']);

    // --- Strategy ---
    $r->get('/brands/{slug}/strategy',        'StrategyController@index',  ['auth']);
    $r->post('/brands/{slug}/strategy',       'StrategyController@store',  ['auth','csrf']);
    $r->get('/brands/{slug}/strategy/{id}',   'StrategyController@show',   ['auth']);

    // --- AI Agents ---
    $r->get('/brands/{slug}/agents',               'AgentController@index',        ['auth']);
    $r->post('/brands/{slug}/agents/generate',     'AgentController@generateContent',['auth','csrf']);
    $r->post('/brands/{slug}/agents/strategy',     'AgentController@extractStrategy',['auth','csrf']);
    $r->post('/brands/{slug}/agents/trends',       'AgentController@huntTrends',   ['auth','csrf']);
    $r->post('/brands/{slug}/agents/reply',        'AgentController@suggestReplies',['auth','csrf']);
    $r->post('/brands/{slug}/agents/viral-score',  'AgentController@scoreContent', ['auth','csrf']);
    $r->get('/brands/{slug}/agents/tasks',         'AgentController@taskList',     ['auth']);
    $r->get('/brands/{slug}/agents/tasks/{id}',    'AgentController@taskStatus',   ['auth']);

    // --- User Profile ---
    $r->get('/profile',           'ProfileController@show',   ['auth']);
    $r->post('/profile',          'ProfileController@update', ['auth','csrf']);
    $r->post('/profile/password', 'ProfileController@changePassword', ['auth','csrf']);
    $r->post('/profile/2fa/enable',  'ProfileController@enable2FA',  ['auth','csrf']);
    $r->post('/profile/2fa/disable', 'ProfileController@disable2FA', ['auth','csrf']);
    $r->get('/profile/sessions',     'ProfileController@sessions',   ['auth']);

    // --- Notifications ---
    $r->get('/notifications',        'NotificationController@index',   ['auth']);
    $r->post('/notifications/read',  'NotificationController@markRead',['auth','csrf']);

});

// ============================================================
// SIMPLE API ROUTES (/api/...)
// ============================================================
$router->group('/api', function(Router $r): void {
    // Copywriting
    $r->post('/copywriting/generate', 'CopywritingController@generate', ['api', 'auth']);

    // Analytics
    $r->get('/analytics/data',              'AnalyticsController@index',     ['api', 'auth']);
    $r->get('/analytics/top-posts',         'AnalyticsController@posts',     ['api', 'auth']);
    $r->get('/analytics/platform-breakdown','AnalyticsController@platforms', ['api', 'auth']);

    // Agents
    $r->get('/agents/statuses',       'AgentsController@taskHistory',  ['api', 'auth']);
    $r->post('/agents/run-workflow',  'AgentsController@runWorkflow',  ['api', 'auth']);
    $r->post('/agents/{agent}/run',   'AgentsController@runTask',      ['api', 'auth']);

    // Community
    $r->get('/community/comments',        'CommunityController@getQueue',  ['api', 'auth']);
    $r->post('/community/reply',          'CommunityController@reply',     ['api', 'auth']);
    $r->post('/community/mark-spam',      'CommunityController@markSpam',  ['api', 'auth']);
    $r->post('/community/auto-reply-all', 'CommunityController@bulkReply', ['api', 'auth']);
    $r->post('/community/sync',           'CommunityController@syncNow',   ['api', 'auth']);

    // Trends
    $r->post('/trends/scan',     'TrendsController@scanTrends',       ['api', 'auth']);
    $r->get('/trends/hashtags',  'TrendsController@getHashtags',      ['api', 'auth']);
    $r->get('/trends/sounds',    'TrendsController@getViralSounds',   ['api', 'auth']);

    // Content
    $r->get('/content/list',              'ContentController@index',       ['api', 'auth']);
    $r->post('/content/approve',          'ContentController@approve',     ['api', 'auth']);
    $r->post('/content/reject',           'ContentController@reject',      ['api', 'auth']);
    $r->post('/content/schedule',         'ContentController@schedule',    ['api', 'auth']);
    $r->post('/content/generate',         'ContentController@generateAI',  ['api', 'auth']);
    $r->post('/content/generate-image',   'ContentController@generateImage',['api', 'auth']);
    $r->post('/content/publish/{id}',     'ContentController@publishNow',  ['api', 'auth']);
});

// ============================================================
// API ROUTES (/api/v1/...)
// ============================================================
$router->group('/api/v1', function(Router $r): void {

    // Auth
    $r->post('/auth/login',          'Api\AuthApiController@login');
    $r->post('/auth/register',       'Api\AuthApiController@register');
    $r->post('/auth/refresh',        'Api\AuthApiController@refresh',  ['api']);
    $r->post('/auth/logout',         'Api\AuthApiController@logout',   ['api']);

    // Brands
    $r->get('/brands',               'Api\BrandApiController@index',   ['api','rate_limit_api']);
    $r->post('/brands',              'Api\BrandApiController@store',   ['api','rate_limit_api']);
    $r->get('/brands/{id}',          'Api\BrandApiController@show',    ['api','rate_limit_api']);
    $r->put('/brands/{id}',          'Api\BrandApiController@update',  ['api','rate_limit_api']);
    $r->delete('/brands/{id}',       'Api\BrandApiController@delete',  ['api','rate_limit_api']);

    // Content
    $r->get('/brands/{brandId}/content',          'Api\ContentApiController@index',    ['api','rate_limit_api']);
    $r->post('/brands/{brandId}/content',         'Api\ContentApiController@store',    ['api','rate_limit_api']);
    $r->get('/brands/{brandId}/content/{id}',     'Api\ContentApiController@show',     ['api','rate_limit_api']);
    $r->put('/brands/{brandId}/content/{id}',     'Api\ContentApiController@update',   ['api','rate_limit_api']);
    $r->delete('/brands/{brandId}/content/{id}',  'Api\ContentApiController@delete',   ['api','rate_limit_api']);
    $r->post('/brands/{brandId}/content/{id}/approve','Api\ContentApiController@approve',['api','rate_limit_api']);

    // Analytics
    $r->get('/brands/{brandId}/analytics/dashboard',  'Api\AnalyticsApiController@dashboard',  ['api','rate_limit_api']);
    $r->get('/brands/{brandId}/analytics/platforms',  'Api\AnalyticsApiController@platforms',  ['api','rate_limit_api']);
    $r->get('/brands/{brandId}/analytics/top-posts',  'Api\AnalyticsApiController@topPosts',   ['api','rate_limit_api']);

    // AI
    $r->post('/ai/generate',         'Api\AIApiController@generate',    ['api','rate_limit_api']);
    $r->post('/ai/image',            'Api\AIApiController@generateImage',['api','rate_limit_api']);
    $r->post('/ai/viral-score',      'Api\AIApiController@viralScore',   ['api','rate_limit_api']);
    $r->get('/ai/tasks/{id}',        'Api\AIApiController@taskStatus',   ['api','rate_limit_api']);

    // Trends
    $r->get('/trends',               'Api\TrendApiController@index',     ['api','rate_limit_api']);

    // Health check (no auth required)
    $r->get('/health', function(): void {
        $db = null;
        $dbOk = false;
        try {
            Database::getInstance()->fetchOne("SELECT 1");
            $dbOk = true;
        } catch (\Throwable) {}
        $data = [
            'status'    => $dbOk ? 'healthy' : 'degraded',
            'version'   => APP_VERSION,
            'timestamp' => date('c'),
            'checks'    => ['database' => $dbOk ? 'ok' : 'error'],
        ];
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    });

}, ['api']);

// ============================================================
// Dispatch
// ============================================================
$router->dispatch();
