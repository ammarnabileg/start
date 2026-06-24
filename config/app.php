<?php
// App configuration - reads from .env
return [
    'name' => $_ENV['APP_NAME'] ?? 'HireAI',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    'language' => $_ENV['APP_LANGUAGE'] ?? 'en',
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'change-this-secret-key',
    'jwt_expiry' => (int)($_ENV['JWT_EXPIRY'] ?? 86400),
    'openai_key' => $_ENV['OPENAI_API_KEY'] ?? '',
    'openai_model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4o',
    'heygen_key' => $_ENV['HEYGEN_API_KEY'] ?? '',
    'upload_path' => dirname(__DIR__) . '/storage/uploads/',
    'log_path' => dirname(__DIR__) . '/storage/logs/',
    'cache_path' => dirname(__DIR__) . '/storage/cache/',
    'upload_max_size' => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 10485760),
    'allowed_extensions' => array_filter(explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'pdf,docx,doc')),
    'installed' => file_exists(dirname(__DIR__) . '/.installed'),
];
