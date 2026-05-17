<?php

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\Response;

/**
 * SetupController — interactive web-based setup wizard.
 * Accessible WITHOUT authentication so it works before the .env exists.
 * Once setup is complete (APP_CONFIGURED=true in .env), the wizard is locked.
 */
class SetupController
{
    private string $envPath;

    public function __construct()
    {
        $this->envPath = BASE_PATH . '/.env';
    }

    // ----------------------------------------------------------------
    // Guard: block wizard once already configured
    // ----------------------------------------------------------------
    private function isConfigured(): bool
    {
        if (!file_exists($this->envPath)) return false;
        $contents = file_get_contents($this->envPath);
        return str_contains($contents, 'APP_CONFIGURED=true');
    }

    private function blockIfConfigured(): void
    {
        if ($this->isConfigured()) {
            // Only the logged-in admin can re-enter setup
            if (empty($_SESSION['user_id'])) {
                http_response_code(403);
                require BASE_PATH . '/views/setup/locked.php';
                exit;
            }
        }
    }

    // ----------------------------------------------------------------
    // GET /setup — show wizard
    // ----------------------------------------------------------------
    public function index(): void
    {
        $this->blockIfConfigured();
        $configured = $this->isConfigured();
        require BASE_PATH . '/views/setup/index.php';
    }

    // ----------------------------------------------------------------
    // POST /setup/test-db — test database connection
    // ----------------------------------------------------------------
    public function testDB(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = $this->jsonInput();
        $host  = trim($input['db_host'] ?? '127.0.0.1');
        $port  = (int)($input['db_port'] ?? 3306);
        $name  = trim($input['db_name'] ?? '');
        $user  = trim($input['db_user'] ?? '');
        $pass  = $input['db_pass'] ?? '';

        if (!$name || !$user) {
            echo json_encode(['success' => false, 'message' => 'Database name and user are required.']);
            return;
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT            => 5,
            ]);
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            echo json_encode([
                'success' => true,
                'message' => "Connected successfully! MySQL {$version}",
            ]);
        } catch (\PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ]);
        }
    }

    // ----------------------------------------------------------------
    // POST /setup/test-openai — verify OpenAI API key
    // ----------------------------------------------------------------
    public function testOpenAI(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input  = $this->jsonInput();
        $apiKey = trim($input['openai_api_key'] ?? '');

        if (!$apiKey) {
            echo json_encode(['success' => false, 'message' => 'API key is required.']);
            return;
        }
        if (!str_starts_with($apiKey, 'sk-')) {
            echo json_encode(['success' => false, 'message' => 'Invalid key format — must start with sk-']);
            return;
        }

        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            echo json_encode(['success' => false, 'message' => 'cURL error: ' . $err]);
            return;
        }

        $data = json_decode($body, true);

        if ($http === 200 && isset($data['data'])) {
            $models = array_column(array_slice($data['data'], 0, 5), 'id');
            echo json_encode([
                'success' => true,
                'message' => 'OpenAI key valid! Available models: ' . implode(', ', $models),
            ]);
        } elseif ($http === 401) {
            echo json_encode(['success' => false, 'message' => 'Invalid API key — authentication failed.']);
        } else {
            $msg = $data['error']['message'] ?? "HTTP {$http}";
            echo json_encode(['success' => false, 'message' => 'OpenAI error: ' . $msg]);
        }
    }

    // ----------------------------------------------------------------
    // POST /setup/test-anthropic — verify Anthropic API key
    // ----------------------------------------------------------------
    public function testAnthropic(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input  = $this->jsonInput();
        $apiKey = trim($input['anthropic_api_key'] ?? '');

        if (!$apiKey || !str_starts_with($apiKey, 'sk-ant-')) {
            echo json_encode(['success' => false, 'message' => 'Invalid key format — must start with sk-ant-']);
            return;
        }

        $payload = json_encode([
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 10,
            'messages'   => [['role' => 'user', 'content' => 'hi']],
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($body, true);
        if ($http === 200) {
            echo json_encode(['success' => true, 'message' => 'Anthropic API key is valid!']);
        } elseif ($http === 401) {
            echo json_encode(['success' => false, 'message' => 'Invalid Anthropic API key.']);
        } else {
            $msg = $data['error']['message'] ?? "HTTP {$http}";
            echo json_encode(['success' => false, 'message' => 'Anthropic error: ' . $msg]);
        }
    }

    // ----------------------------------------------------------------
    // POST /setup/save — write .env and run migrations
    // ----------------------------------------------------------------
    public function save(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = $this->jsonInput();

        // Required fields
        $required = ['db_host', 'db_name', 'db_user', 'app_url'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                echo json_encode(['success' => false, 'message' => "Field '{$field}' is required."]);
                return;
            }
        }

        // Generate strong secrets if not provided
        $jwtSecret     = $input['jwt_secret']      ?? bin2hex(random_bytes(32));
        $encryptionKey = $input['encryption_key']  ?? substr(bin2hex(random_bytes(32)), 0, 32) . '!!';

        $appUrl = rtrim(trim($input['app_url'] ?? 'http://localhost'), '/');

        $lines = [
            'APP_NAME="SociAI OS"',
            'APP_URL=' . $appUrl,
            'APP_ENV=production',
            'APP_DEBUG=false',
            'APP_TIMEZONE=' . ($input['timezone'] ?? 'UTC'),
            'APP_CONFIGURED=true',
            '',
            '# Database',
            'DB_HOST='   . ($input['db_host'] ?? '127.0.0.1'),
            'DB_PORT='   . ($input['db_port'] ?? '3306'),
            'DB_NAME='   . ($input['db_name'] ?? ''),
            'DB_USER='   . ($input['db_user'] ?? ''),
            'DB_PASS='   . ($input['db_pass'] ?? ''),
            '',
            '# Security',
            'JWT_SECRET='      . $jwtSecret,
            'ENCRYPTION_KEY='  . $encryptionKey,
            '',
            '# AI Providers',
            'OPENAI_API_KEY='    . ($input['openai_api_key']    ?? ''),
            'OPENAI_MODEL='      . ($input['openai_model']      ?? 'gpt-4o'),
            'ANTHROPIC_API_KEY=' . ($input['anthropic_api_key'] ?? ''),
            'ANTHROPIC_MODEL=claude-sonnet-4-6',
            '',
            '# Meta (Facebook + Instagram)',
            'META_APP_ID='     . ($input['meta_app_id']     ?? ''),
            'META_APP_SECRET=' . ($input['meta_app_secret'] ?? ''),
            '',
            '# Twitter / X',
            'TWITTER_API_KEY='      . ($input['twitter_api_key']      ?? ''),
            'TWITTER_API_SECRET='   . ($input['twitter_api_secret']   ?? ''),
            'TWITTER_CLIENT_ID='    . ($input['twitter_client_id']    ?? ''),
            'TWITTER_CLIENT_SECRET='. ($input['twitter_client_secret'] ?? ''),
            'TWITTER_ACCESS_TOKEN=' . ($input['twitter_access_token'] ?? ''),
            'TWITTER_ACCESS_SECRET='. ($input['twitter_access_secret'] ?? ''),
            '',
            '# LinkedIn',
            'LINKEDIN_CLIENT_ID='    . ($input['linkedin_client_id']    ?? ''),
            'LINKEDIN_CLIENT_SECRET='. ($input['linkedin_client_secret'] ?? ''),
            '',
            '# TikTok',
            'TIKTOK_CLIENT_ID='    . ($input['tiktok_client_id']    ?? ''),
            'TIKTOK_CLIENT_KEY='   . ($input['tiktok_client_key']   ?? ''),
            'TIKTOK_CLIENT_SECRET='. ($input['tiktok_client_secret'] ?? ''),
            '',
            '# YouTube / Google',
            'YOUTUBE_CLIENT_ID='    . ($input['youtube_client_id']    ?? ''),
            'YOUTUBE_CLIENT_SECRET='. ($input['youtube_client_secret'] ?? ''),
            'GOOGLE_CLIENT_ID='     . ($input['google_client_id']     ?? ''),
            'GOOGLE_CLIENT_SECRET=' . ($input['google_client_secret']  ?? ''),
            '',
            '# Pinterest',
            'PINTEREST_APP_ID='     . ($input['pinterest_app_id']     ?? ''),
            'PINTEREST_APP_SECRET=' . ($input['pinterest_app_secret'] ?? ''),
            '',
            '# Snapchat',
            'SNAPCHAT_CLIENT_ID='    . ($input['snapchat_client_id']    ?? ''),
            'SNAPCHAT_CLIENT_SECRET='. ($input['snapchat_client_secret'] ?? ''),
            '',
            '# Telegram',
            'TELEGRAM_BOT_TOKEN=' . ($input['telegram_bot_token'] ?? ''),
            '',
            '# Email',
            'MAIL_HOST='       . ($input['mail_host']       ?? 'smtp.mailtrap.io'),
            'MAIL_PORT='       . ($input['mail_port']       ?? '587'),
            'MAIL_USERNAME='   . ($input['mail_username']   ?? ''),
            'MAIL_PASSWORD='   . ($input['mail_password']   ?? ''),
            'MAIL_FROM='       . ($input['mail_from']       ?? 'noreply@example.com'),
            'MAIL_FROM_NAME="SociAI OS"',
            'MAIL_ENCRYPTION=' . ($input['mail_encryption'] ?? 'tls'),
            '',
            '# File Uploads',
            'UPLOAD_DIR=' . BASE_PATH . '/uploads',
            'MAX_UPLOAD_SIZE=52428800',
        ];

        $envContent = implode("\n", $lines) . "\n";

        // Write .env file
        if (file_put_contents($this->envPath, $envContent) === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Could not write .env file. Check directory permissions: chmod 755 ' . dirname($this->envPath),
            ]);
            return;
        }

        // Run database migrations
        $migrationResult = $this->runMigrations($input);

        echo json_encode([
            'success'    => true,
            'message'    => '.env saved successfully!',
            'migrations' => $migrationResult,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /setup/run-migrations — run SQL schema
    // ----------------------------------------------------------------
    public function runMigrations(array $dbConfig = []): array
    {
        $results = ['success' => false, 'message' => ''];

        if (empty($dbConfig)) {
            $dbConfig = [
                'db_host' => defined('DB_HOST') ? DB_HOST : '127.0.0.1',
                'db_port' => defined('DB_PORT') ? DB_PORT : 3306,
                'db_name' => defined('DB_NAME') ? DB_NAME : '',
                'db_user' => defined('DB_USER') ? DB_USER : '',
                'db_pass' => defined('DB_PASS') ? DB_PASS : '',
            ];
        }

        // Try sql/ directory first (project default), then database/
        $sqlFile = file_exists(BASE_PATH . '/sql/schema.sql')
            ? BASE_PATH . '/sql/schema.sql'
            : BASE_PATH . '/database/schema.sql';
        if (!file_exists($sqlFile)) {
            $results['message'] = 'schema.sql not found — skipped migrations.';
            return $results;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $dbConfig['db_host'] ?? '127.0.0.1',
                (int)($dbConfig['db_port'] ?? 3306),
                $dbConfig['db_name'] ?? ''
            );
            $pdo = new \PDO($dsn, $dbConfig['db_user'] ?? '', $dbConfig['db_pass'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $sql = file_get_contents($sqlFile);
            // Split on semicolons, ignore empty statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => $s !== '' && !preg_match('/^--/', $s)
            );

            $count = 0;
            foreach ($statements as $stmt) {
                $pdo->exec($stmt);
                $count++;
            }

            $results['success'] = true;
            $results['message'] = "Migrations complete — {$count} statements executed.";
        } catch (\Throwable $e) {
            $results['message'] = 'Migration error: ' . $e->getMessage();
        }

        return $results;
    }

    // ----------------------------------------------------------------
    // POST /setup/check — return current setup status as JSON
    // ----------------------------------------------------------------
    public function check(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'configured' => $this->isConfigured(),
            'env_exists' => file_exists($this->envPath),
            'php_version'=> PHP_VERSION,
            'extensions' => [
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'curl'      => extension_loaded('curl'),
                'mbstring'  => extension_loaded('mbstring'),
                'json'      => extension_loaded('json'),
                'openssl'   => extension_loaded('openssl'),
            ],
            'writable'   => is_writable(BASE_PATH),
        ]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }
        return $_POST;
    }
}
