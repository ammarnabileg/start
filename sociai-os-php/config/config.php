<?php
/**
 * SociAI OS - Master Configuration
 * All application constants and environment settings.
 * In production, load sensitive values from $_ENV / .env file.
 */

declare(strict_types=1);

// ============================================================
// Environment helper
// ============================================================
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string)$value)) {
            'true', '1', 'yes'  => true,
            'false', '0', 'no'  => false,
            'null', 'nil'       => null,
            default             => $value,
        };
    }
}

// ============================================================
// Load .env file if present (simple parser, no dependency)
// ============================================================
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!isset($_ENV[$name])) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
}

// ============================================================
// Application
// ============================================================
define('APP_NAME',    env('APP_NAME',    'SociAI OS'));
define('APP_URL',     rtrim(env('APP_URL', 'http://localhost'), '/'));
define('APP_ENV',     env('APP_ENV',    'production'));   // development | production
define('APP_DEBUG',   env('APP_DEBUG',  false));
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', env('APP_TIMEZONE', 'UTC'));

date_default_timezone_set(APP_TIMEZONE);

// ============================================================
// Database
// ============================================================
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_PORT',    (int) env('DB_PORT', 3306));
define('DB_NAME',    env('DB_NAME',    'admin_sm_manager'));
define('DB_USER',    env('DB_USER',    'sm_manager'));
define('DB_PASS',    env('DB_PASS',    'vi761N7&u'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// ============================================================
// Security
// ============================================================
// Generate strong random keys in production:
//   php -r "echo bin2hex(random_bytes(32));"
define('JWT_SECRET',       env('JWT_SECRET',       'CHANGE_ME_jwt_secret_32_chars_min'));
define('ENCRYPTION_KEY',   env('ENCRYPTION_KEY',   'CHANGE_ME_32_byte_aes_key_here!!'));  // exactly 32 bytes for AES-256
define('SESSION_NAME',     env('SESSION_NAME',     'sociai_sess'));
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 86400));       // 24 hours
define('CSRF_TOKEN_LIFETIME', (int) env('CSRF_TOKEN_LIFETIME', 3600));  // 1 hour

// ============================================================
// AI Providers
// ============================================================
define('OPENAI_API_KEY',   env('OPENAI_API_KEY',   ''));
define('OPENAI_ORG_ID',    env('OPENAI_ORG_ID',    ''));
define('OPENAI_MODEL',     env('OPENAI_MODEL',     'gpt-4o'));

define('ANTHROPIC_API_KEY',   env('ANTHROPIC_API_KEY',   ''));
define('ANTHROPIC_MODEL',     env('ANTHROPIC_MODEL',     'claude-sonnet-4-6'));
define('ANTHROPIC_API_URL',   'https://api.anthropic.com/v1/messages');

define('ELEVENLABS_API_KEY',  env('ELEVENLABS_API_KEY', ''));
define('STABILITY_API_KEY',   env('STABILITY_API_KEY',  ''));

// ============================================================
// Social Platform OAuth Credentials
// ============================================================

// LinkedIn
define('LINKEDIN_CLIENT_ID',     env('LINKEDIN_CLIENT_ID',     ''));
define('LINKEDIN_CLIENT_SECRET', env('LINKEDIN_CLIENT_SECRET', ''));
define('LINKEDIN_REDIRECT_URI',  APP_URL . '/oauth/linkedin/callback');

// Meta (Facebook & Instagram)
define('META_APP_ID',       env('META_APP_ID',       ''));
define('META_APP_SECRET',   env('META_APP_SECRET',   ''));
define('META_REDIRECT_URI', APP_URL . '/oauth/meta/callback');

// TikTok
define('TIKTOK_CLIENT_KEY',    env('TIKTOK_CLIENT_KEY',    ''));
define('TIKTOK_CLIENT_SECRET', env('TIKTOK_CLIENT_SECRET', ''));
define('TIKTOK_REDIRECT_URI',  APP_URL . '/oauth/tiktok/callback');

// Twitter / X
define('TWITTER_API_KEY',       env('TWITTER_API_KEY',       ''));
define('TWITTER_API_SECRET',    env('TWITTER_API_SECRET',    ''));
define('TWITTER_ACCESS_TOKEN',  env('TWITTER_ACCESS_TOKEN',  ''));
define('TWITTER_ACCESS_SECRET', env('TWITTER_ACCESS_SECRET', ''));
define('TWITTER_REDIRECT_URI',  APP_URL . '/oauth/twitter/callback');

// YouTube / Google
define('YOUTUBE_CLIENT_ID',     env('YOUTUBE_CLIENT_ID',     ''));
define('YOUTUBE_CLIENT_SECRET', env('YOUTUBE_CLIENT_SECRET', ''));
define('YOUTUBE_REDIRECT_URI',  APP_URL . '/oauth/youtube/callback');

// Pinterest
define('PINTEREST_APP_ID',     env('PINTEREST_APP_ID',     ''));
define('PINTEREST_APP_SECRET', env('PINTEREST_APP_SECRET', ''));
define('PINTEREST_REDIRECT_URI', APP_URL . '/oauth/pinterest/callback');

// Snapchat
define('SNAPCHAT_CLIENT_ID',     env('SNAPCHAT_CLIENT_ID',     ''));
define('SNAPCHAT_CLIENT_SECRET', env('SNAPCHAT_CLIENT_SECRET', ''));

// Telegram
define('TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN', ''));

// ============================================================
// File Uploads
// ============================================================
define('UPLOAD_DIR',       env('UPLOAD_DIR', dirname(__DIR__) . '/uploads'));
define('UPLOAD_URL',       APP_URL . '/uploads');
define('MAX_UPLOAD_SIZE',  (int) env('MAX_UPLOAD_SIZE', 52428800));  // 50 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4','video/webm','video/quicktime','video/x-msvideo']);

// ============================================================
// Rate Limiting
// ============================================================
define('RATE_LIMIT_DIR', sys_get_temp_dir() . '/sociai_rate_limits');
define('RATE_LIMIT_LOGIN',   ['max' => 5,   'window' => 900]);   // 5 per 15 min
define('RATE_LIMIT_API',     ['max' => 300, 'window' => 3600]);  // 300 per hour
define('RATE_LIMIT_AI',      ['max' => 50,  'window' => 3600]);  // 50 AI calls per hour

// ============================================================
// Paths
// ============================================================
define('ROOT_PATH',        dirname(__DIR__));
define('APP_PATH',         __DIR__ . '/..');
define('VIEWS_PATH',       dirname(__DIR__) . '/views');
define('CACHE_PATH',       dirname(__DIR__) . '/cache');
define('LOG_PATH',         dirname(__DIR__) . '/logs');

// ============================================================
// Email (SMTP)
// ============================================================
define('MAIL_HOST',       env('MAIL_HOST',       'smtp.mailtrap.io'));
define('MAIL_PORT',       (int) env('MAIL_PORT', 587));
define('MAIL_USERNAME',   env('MAIL_USERNAME',   ''));
define('MAIL_PASSWORD',   env('MAIL_PASSWORD',   ''));
define('MAIL_FROM',       env('MAIL_FROM',       'noreply@sociai.os'));
define('MAIL_FROM_NAME',  env('MAIL_FROM_NAME',  APP_NAME));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls'));

// ============================================================
// Runtime setup
// ============================================================
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

ini_set('log_errors', '1');
ini_set('error_log', LOG_PATH . '/php_error.log');

// Create required directories
foreach ([UPLOAD_DIR, CACHE_PATH, LOG_PATH, RATE_LIMIT_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
