<?php
declare(strict_types=1);

if (!defined('ROOT_DIR')) define('ROOT_DIR', __DIR__);
if (!defined('VIEWS_PATH')) define('VIEWS_PATH', ROOT_DIR . '/views');
if (!defined('CORE_PATH')) define('CORE_PATH', ROOT_DIR . '/core');
if (!defined('MODULES_PATH')) define('MODULES_PATH', ROOT_DIR . '/modules');
if (!defined('STORAGE_PATH')) define('STORAGE_PATH', ROOT_DIR . '/storage');
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', ROOT_DIR . '/storage/uploads');

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Modules\\', 8) !== 0) return;
    $file = MODULES_PATH . '/' . str_replace('\\', '/', substr($class, 8)) . '.php';
    if (is_file($file)) require $file;
});

$coreClasses = ['Env','Database','JWT','Auth','Cache','Request','Response','Validator','RBAC','Tenant','Audit','ApiKeyManager'];
foreach ($coreClasses as $c) {
    if (!class_exists($c, false) && is_file(CORE_PATH . "/{$c}.php")) {
        require CORE_PATH . "/{$c}.php";
    }
}

if (class_exists('Env', false) && file_exists(ROOT_DIR . '/.env')) {
    Env::load(ROOT_DIR . '/.env');
}

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    @session_start();
}
