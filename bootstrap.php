<?php
declare(strict_types=1);

/**
 * bootstrap.php - Shared bootstrap for entry points that are not reached
 * through public/index.php (e.g. direct includes of API endpoint files or
 * CLI tooling).
 *
 * It is intentionally idempotent and defensive: if the platform front
 * controller has already loaded the core classes and defined the path
 * constants, this file does almost nothing. Otherwise it loads the global
 * core classes, the environment, and registers a PSR-4 autoloader for the
 * Modules\ namespace so the AI / Interviews / HeyGen modules resolve.
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', __DIR__);
}
if (!defined('VIEWS_PATH')) {
    define('VIEWS_PATH', ROOT_DIR . '/views');
}
if (!defined('CORE_PATH')) {
    define('CORE_PATH', ROOT_DIR . '/core');
}
if (!defined('MODULES_PATH')) {
    define('MODULES_PATH', ROOT_DIR . '/modules');
}

// ----------------------------------------------------------------------
// Autoloader for the Modules\ namespace (maps Modules\Foo\Bar -> modules/Foo/Bar.php)
// ----------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Modules\\', 8) !== 0) {
        return;
    }
    $relative = substr($class, 8);
    $file = MODULES_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ----------------------------------------------------------------------
// Core classes (global namespace) + environment, only if not already loaded.
// ----------------------------------------------------------------------
if (!class_exists('Env', false) && is_file(CORE_PATH . '/Env.php')) {
    require CORE_PATH . '/Env.php';
}
if (class_exists('Env', false) && file_exists(ROOT_DIR . '/.env')) {
    \Env::load(ROOT_DIR . '/.env');
}

foreach (['Database', 'JWT', 'Auth', 'Cache', 'Request', 'Response', 'ApiKeyManager', 'TenantAIProvider'] as $coreClass) {
    if (!class_exists($coreClass, false) && is_file(CORE_PATH . "/{$coreClass}.php")) {
        require CORE_PATH . "/{$coreClass}.php";
    }
}

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    @session_start();
}
