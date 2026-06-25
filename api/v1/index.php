<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$fullPath = $req->path();
$apiPath  = preg_replace('#^/api/v1#', '', $fullPath);
$apiPath  = '/' . trim((string)$apiPath, '/');
$segments = $apiPath === '/' ? [] : explode('/', trim($apiPath, '/'));
$resource = $segments[0] ?? '';
$id       = $segments[1] ?? null;
$sub      = $segments[2] ?? null;
$sub2     = $segments[3] ?? null;

switch ($resource) {
    case 'auth':
        require __DIR__ . '/auth.php';
        break;
    case 'dashboard':
        require __DIR__ . '/dashboard.php';
        break;
    case 'jobs':
        require __DIR__ . '/jobs.php';
        break;
    case 'applications':
        require __DIR__ . '/applications.php';
        break;
    case 'candidates':
        require __DIR__ . '/candidates.php';
        break;
    case 'ai-interviews':
        require __DIR__ . '/ai-interviews.php';
        break;
    case 'human-interviews':
        require __DIR__ . '/human-interviews.php';
        break;
    case 'interview-links':
        require __DIR__ . '/interview-links.php';
        break;
    case 'interview':
        require __DIR__ . '/interview.php';
        break;
    case 'offers':
        require __DIR__ . '/offers.php';
        break;
    case 'talent-pool':
        require __DIR__ . '/talent-pool.php';
        break;
    case 'avatars':
        require __DIR__ . '/avatars.php';
        break;
    case 'users':
        require __DIR__ . '/users.php';
        break;
    case 'roles':
        require __DIR__ . '/roles.php';
        break;
    case 'settings':
        require __DIR__ . '/settings.php';
        break;
    case 'profile':
        require __DIR__ . '/profile.php';
        break;
    case 'notifications':
        require __DIR__ . '/notifications.php';
        break;
    case 'careers':
        require __DIR__ . '/careers.php';
        break;
    case 'candidate':
        require __DIR__ . '/candidate-api.php';
        break;
    case 'ai':
        require __DIR__ . '/ai-copilot.php';
        break;
    case 'super':
        require __DIR__ . '/super.php';
        break;
    default:
        Response::notFound('API endpoint not found');
}
