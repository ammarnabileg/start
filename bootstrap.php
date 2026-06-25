<?php
declare(strict_types=1);

define('ROOT_DIR',    __DIR__);
define('VIEWS_PATH',  ROOT_DIR . '/views');
define('CORE_PATH',   ROOT_DIR . '/core');
define('MODULES_PATH',ROOT_DIR . '/modules');
define('STORAGE_PATH',ROOT_DIR . '/storage');

require ROOT_DIR . '/core/Env.php';
Env::load(ROOT_DIR . '/.env');

require ROOT_DIR . '/core/Database.php';
require ROOT_DIR . '/core/JWT.php';
require ROOT_DIR . '/core/Auth.php';
require ROOT_DIR . '/core/Request.php';
require ROOT_DIR . '/core/Response.php';

// Autoload modules
spl_autoload_register(function(string $class): void {
    $path = MODULES_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) require_once $path;
});

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path'     => '/',
        'secure'   => (($_ENV['APP_URL'] ?? '') !== '' && str_starts_with($_ENV['APP_URL'], 'https')),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
