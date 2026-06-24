<?php
/**
 * Application configuration. Reads from environment (.env loaded by bootstrap).
 */
return [
    'name'    => env('APP_NAME', 'AI Recruit'),
    'url'     => env('APP_URL', 'http://localhost'),
    'env'     => env('APP_ENV', 'production'),
    'key'     => env('APP_KEY', ''),
    'debug'   => env('APP_ENV', 'production') !== 'production',

    'jwt' => [
        'secret' => env('JWT_SECRET', 'change-me'),
        'expiry' => (int) env('JWT_EXPIRY', 86400),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'model'   => env('OPENAI_MODEL', 'gpt-4-turbo-preview'),
        'base'    => 'https://api.openai.com/v1',
    ],

    'heygen' => [
        'api_key' => env('HEYGEN_API_KEY', ''),
        'base'    => 'https://api.heygen.com',
    ],

    'mail' => [
        'host'     => env('MAIL_HOST', ''),
        'port'     => (int) env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'from'     => env('MAIL_FROM', 'noreply@example.com'),
    ],

    'storage' => [
        'logs'    => dirname(__DIR__) . '/storage/logs',
        'cache'   => dirname(__DIR__) . '/storage/cache',
        'uploads' => dirname(__DIR__) . '/storage/uploads',
    ],
];
