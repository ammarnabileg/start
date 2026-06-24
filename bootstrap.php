<?php
/**
 * Application bootstrap: PSR-4 autoloader, .env loader, helper functions.
 * Every entry point (public/index.php, api/v1/index.php, setup) includes this.
 */

define('BASE_PATH', __DIR__);

// ---------------------------------------------------------------------------
// PSR-4 autoloader for App\ => src/ and App\Core\ => core/, App\Modules => modules/
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $relative = substr($class, 4); // strip "App\"
    $relative = str_replace('\\', '/', $relative);

    $candidates = [
        BASE_PATH . '/src/' . $relative . '.php',
        BASE_PATH . '/core/' . substr($relative, strlen('Core/')) . '.php', // App\Core\X
        BASE_PATH . '/modules/' . substr($relative, strlen('Modules/')) . '.php', // App\Modules\X
    ];

    // Core: App\Core\Foo -> core/Foo.php
    if (strncmp($relative, 'Core/', 5) === 0) {
        $path = BASE_PATH . '/core/' . substr($relative, 5) . '.php';
        if (file_exists($path)) { require $path; return; }
    }
    // Modules: App\Modules\Jobs\JobService -> modules/Jobs/JobService.php
    if (strncmp($relative, 'Modules/', 8) === 0) {
        $path = BASE_PATH . '/modules/' . substr($relative, 8) . '.php';
        if (file_exists($path)) { require $path; return; }
    }
    foreach ($candidates as $path) {
        if (file_exists($path)) { require $path; return; }
    }
});

// ---------------------------------------------------------------------------
// .env loader
// ---------------------------------------------------------------------------
if (!function_exists('load_env')) {
    function load_env(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip surrounding quotes.
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            'empty' => '',
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(string $file): array
    {
        static $cache = [];
        if (!isset($cache[$file])) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            $cache[$file] = file_exists($path) ? require $path : [];
        }
        return $cache[$file];
    }
}

if (!function_exists('app_lang')) {
    /**
     * Translate a key using lang/{locale}/messages.php. Falls back to the key.
     */
    function app_lang(string $key, ?string $locale = null): string
    {
        static $cache = [];
        $locale = $locale ?? ($_COOKIE['lang'] ?? 'en');
        $locale = in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
        if (!isset($cache[$locale])) {
            $path = BASE_PATH . '/lang/' . $locale . '/messages.php';
            $cache[$locale] = file_exists($path) ? require $path : [];
        }
        return $cache[$locale][$key] ?? $key;
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('logger')) {
    function logger(string $message, string $level = 'info'): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), strtoupper($level), $message, PHP_EOL);
        @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}

// Load environment now.
load_env(BASE_PATH . '/.env');

// Surface PHP errors into the log instead of the browser in production.
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    logger("$message in $file:$line", 'error');
    return false;
});
