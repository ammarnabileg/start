<?php
/**
 * REST API front controller. All /api/v1/* requests route through here.
 * Responses are always JSON following {success, data|error}.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Database;
use App\Core\Response;
use App\Core\Tenant;

// ---- CORS ----------------------------------------------------------------
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-ID, X-CSRF-Token, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Boot DB + tenant ----------------------------------------------------
try {
    Database::instance(require dirname(__DIR__, 2) . '/config/database.php');
    (new Tenant())->resolve();
} catch (\Throwable $e) {
    Response::error('Service unavailable: ' . $e->getMessage(), 503);
    exit;
}

// ---- Parse the route -----------------------------------------------------
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = preg_replace('#^/api/v1#', '', $uri);
$uri = '/' . trim($uri, '/');
$segments = $uri === '/' ? [] : explode('/', trim($uri, '/'));
$resource = $segments[0] ?? '';

// Map top-level resource to its handler file.
$map = [
    'auth'         => 'auth.php',
    'jobs'         => 'jobs.php',
    'candidates'   => 'candidates.php',
    'interviews'   => 'interviews.php',
    'ai'           => 'ai.php',
    'admin'        => 'admin.php',
    'avatars'      => 'avatars.php',
    'offers'       => 'offers.php',
    'talent-pools' => 'talent-pools.php',
    'users'        => 'users.php',
];

// Expose parsed pieces to the included handler.
$GLOBALS['__api'] = [
    'method'    => strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    'uri'       => $uri,
    'segments'  => $segments,
    'resource'  => $resource,
    'sub'       => $segments[1] ?? null,   // e.g. /jobs/{id} or /auth/login
    'sub2'      => $segments[2] ?? null,
    'sub3'      => $segments[3] ?? null,
];

if (!isset($map[$resource])) {
    Response::error('Unknown API resource: ' . $resource, 404);
    exit;
}

$handlerFile = __DIR__ . '/' . $map[$resource];
if (!file_exists($handlerFile)) {
    Response::error('Handler not implemented', 501);
    exit;
}

try {
    require $handlerFile;
} catch (\Throwable $e) {
    logger('API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), 'error');
    $debug = (config('app')['debug'] ?? false);
    Response::error($debug ? $e->getMessage() : 'Internal server error', 500);
}
