<?php
/**
 * HireAI - Setup AJAX Endpoint
 * ---------------------------------------------------------------------------
 * Standalone JSON endpoint used by the setup wizard and the web terminal.
 * Self-contained (no dependency on core/ or a configured database).
 *
 * Actions (via ?action= or POST action):
 *   - test_db       : verify MySQL credentials / database
 *   - test_openai   : validate an OpenAI API key (GET /v1/models)
 *   - test_heygen   : validate a HeyGen API key
 *   - run_command   : execute a whitelisted, server-side diagnostic command
 *
 * All responses are JSON: { ok: bool, msg: string, ... }.
 * Blocked once setup is locked (setup/.locked) unless unlocked in session.
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('hireai_setup');
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('SETUP_BASE', dirname(__DIR__));
define('SETUP_DIR', __DIR__);

// ---------------------------------------------------------------------------
// Security gate: refuse if locked (and not unlocked) for everything except
// nothing (lock handling lives in index.php). Refuse all if fully installed
// AND locked.
// ---------------------------------------------------------------------------
if (is_file(SETUP_DIR . '/.locked') && empty($_SESSION['setup_unlocked'])) {
    echo json_encode(['ok' => false, 'msg' => 'Setup is locked.']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'test_db':
        ajax_test_db();
        break;
    case 'test_openai':
        ajax_test_openai();
        break;
    case 'test_heygen':
        ajax_test_heygen();
        break;
    case 'run_command':
        ajax_run_command();
        break;
    default:
        echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
}
exit;

// ===========================================================================
// Handlers
// ===========================================================================

function ajax_test_db(): void
{
    $host = trim((string) ($_POST['db_host'] ?? 'localhost')) ?: 'localhost';
    $port = (int) ($_POST['db_port'] ?? 3306) ?: 3306;
    $name = trim((string) ($_POST['db_name'] ?? ''));
    $user = trim((string) ($_POST['db_user'] ?? ''));
    $pass = (string) ($_POST['db_pass'] ?? '');

    if ($user === '') {
        echo json_encode(['ok' => false, 'msg' => 'Database username is required.']);
        return;
    }

    try {
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $version = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        $dbNote = '';
        if ($name !== '') {
            $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([$name]);
            $exists = (bool) $stmt->fetchColumn();
            $dbNote = $exists
                ? " Database \"{$name}\" exists."
                : " Database \"{$name}\" will be created during install.";
        }

        echo json_encode(['ok' => true, 'msg' => "Connection successful! MySQL {$version}.{$dbNote}", 'version' => $version]);
    } catch (\PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => 'Connection failed: ' . $e->getMessage()]);
    }
}

function ajax_test_openai(): void
{
    $key = trim((string) ($_POST['openai_key'] ?? ($_POST['key'] ?? '')));
    if ($key === '') {
        echo json_encode(['ok' => false, 'msg' => 'OpenAI API key is required.']);
        return;
    }

    [$status, $body, $err] = setup_ajax_http_get('https://api.openai.com/v1/models', [
        'Authorization: Bearer ' . $key,
    ]);

    if ($err !== null) {
        echo json_encode(['ok' => false, 'msg' => 'Request error: ' . $err]);
        return;
    }
    if ($status === 200) {
        $json = json_decode($body, true);
        $count = is_array($json['data'] ?? null) ? count($json['data']) : 0;
        echo json_encode(['ok' => true, 'msg' => "OpenAI key is valid. {$count} models available."]);
        return;
    }
    if ($status === 401) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid API key (401 Unauthorized).']);
        return;
    }
    if ($status === 429) {
        echo json_encode(['ok' => false, 'msg' => 'Key recognized but quota exceeded / rate-limited (429).']);
        return;
    }
    $json = json_decode($body, true);
    $msg = $json['error']['message'] ?? "Unexpected response (HTTP {$status}).";
    echo json_encode(['ok' => false, 'msg' => $msg]);
}

function ajax_test_heygen(): void
{
    $key = trim((string) ($_POST['heygen_key'] ?? ($_POST['key'] ?? '')));
    if ($key === '') {
        echo json_encode(['ok' => false, 'msg' => 'HeyGen API key is required.']);
        return;
    }

    [$status, $body, $err] = setup_ajax_http_get('https://api.heygen.com/v2/user/remaining_quota', [
        'X-Api-Key: ' . $key,
        'Accept: application/json',
    ]);

    if ($err !== null) {
        echo json_encode(['ok' => false, 'msg' => 'Request error: ' . $err]);
        return;
    }
    if ($status === 200) {
        echo json_encode(['ok' => true, 'msg' => 'HeyGen key is valid!']);
        return;
    }
    if (in_array($status, [401, 403], true)) {
        echo json_encode(['ok' => false, 'msg' => "Invalid HeyGen key (HTTP {$status})."]);
        return;
    }
    echo json_encode(['ok' => false, 'msg' => "Unexpected response (HTTP {$status})."]);
}

/**
 * Execute a whitelisted diagnostic command. The whitelist is enforced
 * server-side; there is NO shell execution of arbitrary input.
 */
function ajax_run_command(): void
{
    $cmd = trim((string) ($_POST['cmd'] ?? ($_POST['command'] ?? '')));

    $allowed = [
        'php_version', 'php_extensions', 'check_extensions', 'file_permissions',
        'check_writable', 'view_error_log', 'clear_cache', 'system_info',
        'test_db', 'mysql_version', 'disk_space', 'memory_info', 'env_check',
        'list_uploads', 'installed_date',
    ];

    // Normalize a couple of friendly aliases.
    $aliases = [
        'check_extensions' => 'php_extensions',
        'view_logs'        => 'view_error_log',
    ];
    $cmd = $aliases[$cmd] ?? $cmd;

    if (!in_array($cmd, $allowed, true)) {
        echo json_encode(['ok' => false, 'output' => 'Command not allowed: ' . htmlspecialchars($cmd)]);
        return;
    }

    $output = setup_ajax_command_output($cmd);
    echo json_encode(['ok' => true, 'output' => $output]);
}

// ===========================================================================
// Command implementations
// ===========================================================================

function setup_ajax_command_output(string $cmd): string
{
    switch ($cmd) {
        case 'php_version':
            return 'PHP Version: ' . PHP_VERSION . "\nSAPI: " . php_sapi_name()
                . "\nServer: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown');

        case 'php_extensions':
            $exts = get_loaded_extensions();
            sort($exts);
            return 'Loaded Extensions (' . count($exts) . "):\n" . implode(', ', $exts);

        case 'file_permissions':
        case 'check_writable':
            $paths = [
                'storage'         => SETUP_BASE . '/storage',
                'storage/logs'    => SETUP_BASE . '/storage/logs',
                'storage/cache'   => SETUP_BASE . '/storage/cache',
                'storage/uploads' => SETUP_BASE . '/storage/uploads',
                '.env'            => SETUP_BASE . '/.env',
                'project root'    => SETUP_BASE,
            ];
            $lines = [];
            foreach ($paths as $label => $p) {
                if (file_exists($p)) {
                    $perms = substr(sprintf('%o', fileperms($p)), -4);
                    $w = is_writable($p) ? '[writable]    ' : '[NOT writable]';
                    $lines[] = "{$perms} {$w} {$label}";
                } else {
                    $lines[] = "---- [missing]      {$label}";
                }
            }
            return implode("\n", $lines);

        case 'view_error_log':
            $candidates = [
                SETUP_BASE . '/storage/logs/php-error.log',
                SETUP_BASE . '/storage/logs/app.log',
                SETUP_BASE . '/storage/logs/error.log',
                SETUP_BASE . '/install.log',
            ];
            foreach ($candidates as $logFile) {
                if (is_file($logFile)) {
                    $lines = file($logFile) ?: [];
                    $last = array_slice($lines, -50);
                    return "Tail of " . basename($logFile) . " (last 50 lines):\n"
                        . str_repeat('-', 40) . "\n" . implode('', $last);
                }
            }
            return '[No log files found in storage/logs]';

        case 'clear_cache':
            $cacheDir = SETUP_BASE . '/storage/cache';
            $count = 0;
            if (is_dir($cacheDir)) {
                foreach (glob($cacheDir . '/*') ?: [] as $f) {
                    if (is_file($f) && @unlink($f)) {
                        $count++;
                    }
                }
            }
            return "Cache cleared. {$count} file(s) deleted.";

        case 'system_info':
            $envFile = SETUP_BASE . '/.env';
            $mysql = '(not configured)';
            if (is_file($envFile)) {
                $env = setup_ajax_parse_env($envFile);
                try {
                    $pdo = new PDO(
                        "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']}",
                        $env['DB_USERNAME'] ?? '',
                        $env['DB_PASSWORD'] ?? '',
                        [PDO::ATTR_TIMEOUT => 4]
                    );
                    $mysql = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                } catch (\Throwable $e) {
                    $mysql = 'error: ' . $e->getMessage();
                }
            }
            $total = @disk_total_space(SETUP_BASE) ?: 0;
            $free = @disk_free_space(SETUP_BASE) ?: 0;
            return "System Information\n" . str_repeat('-', 40) . "\n"
                . 'PHP:           ' . PHP_VERSION . "\n"
                . 'MySQL:         ' . $mysql . "\n"
                . 'OS:            ' . PHP_OS . "\n"
                . 'Server:        ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n"
                . 'Memory limit:  ' . ini_get('memory_limit') . "\n"
                . 'Memory used:   ' . setup_ajax_bytes(memory_get_usage(true)) . "\n"
                . 'Disk total:    ' . setup_ajax_bytes($total) . "\n"
                . 'Disk free:     ' . setup_ajax_bytes($free) . "\n"
                . 'Installed:     ' . (is_file(SETUP_BASE . '/.installed') ? 'yes' : 'no');

        case 'disk_space':
            $total = @disk_total_space(SETUP_BASE) ?: 0;
            $free = @disk_free_space(SETUP_BASE) ?: 0;
            $used = $total - $free;
            $pct = $total > 0 ? ($used / $total) * 100 : 0;
            return sprintf(
                "Disk Total: %s\nDisk Used:  %s (%.1f%%)\nDisk Free:  %s",
                setup_ajax_bytes($total),
                setup_ajax_bytes($used),
                $pct,
                setup_ajax_bytes($free)
            );

        case 'memory_info':
            return 'Memory Limit:     ' . ini_get('memory_limit') . "\n"
                . 'Memory Used:      ' . setup_ajax_bytes(memory_get_usage(true)) . "\n"
                . 'Peak Memory:      ' . setup_ajax_bytes(memory_get_peak_usage(true));

        case 'test_db':
        case 'mysql_version':
            $envFile = SETUP_BASE . '/.env';
            if (!is_file($envFile)) {
                return '.env not found (run the installer first).';
            }
            $env = setup_ajax_parse_env($envFile);
            try {
                $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
                $pdo = new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_TIMEOUT => 5]);
                $v = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                if ($cmd === 'mysql_version') {
                    return 'MySQL Version: ' . $v;
                }
                $count = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
                return "Database connection OK\nMySQL Version: {$v}\nDatabase: {$env['DB_NAME']}\nTables: {$count}";
            } catch (\Throwable $e) {
                return 'Connection failed: ' . $e->getMessage();
            }

        case 'env_check':
            $envFile = SETUP_BASE . '/.env';
            if (!is_file($envFile)) {
                return '.env not found.';
            }
            $env = setup_ajax_parse_env($envFile);
            $lines = [];
            foreach (['APP_NAME', 'APP_URL', 'APP_ENV', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USERNAME', 'OPENAI_MODEL'] as $k) {
                $lines[] = sprintf('%-14s = %s', $k, $env[$k] ?? '(unset)');
            }
            // Mask secrets.
            foreach (['DB_PASSWORD', 'JWT_SECRET', 'OPENAI_API_KEY', 'HEYGEN_API_KEY'] as $k) {
                $val = $env[$k] ?? '';
                $lines[] = sprintf('%-14s = %s', $k, $val === '' ? '(unset)' : setup_ajax_mask($val));
            }
            return implode("\n", $lines);

        case 'list_uploads':
            $dir = SETUP_BASE . '/storage/uploads';
            if (!is_dir($dir)) {
                return 'storage/uploads does not exist.';
            }
            $files = [];
            $size = 0;
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $files[] = str_replace($dir . '/', '', $f->getPathname());
                    $size += $f->getSize();
                }
            }
            $list = $files ? implode("\n", array_slice($files, 0, 50)) : '(empty)';
            return 'Uploads (' . count($files) . ' files, ' . setup_ajax_bytes($size) . "):\n" . $list;

        case 'installed_date':
            $f = SETUP_BASE . '/.installed';
            return is_file($f) ? ('Installed marker:\n' . trim((string) file_get_contents($f))) : 'Not installed yet.';
    }

    return 'No output.';
}

// ===========================================================================
// Utilities
// ===========================================================================

/**
 * @return array{0:int,1:string,2:?string} [status, body, error]
 */
function setup_ajax_http_get(string $url, array $headers = []): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);
        return [$status, is_string($body) ? $body : '', $error];
    }

    $context = stream_context_create([
        'http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 12, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return $body === false ? [$status, '', 'HTTP request failed'] : [$status, $body, null];
}

function setup_ajax_parse_env(string $path): array
{
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $out[$k] = $v;
    }
    return $out;
}

function setup_ajax_bytes(float $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $pow = (int) min($pow, count($units) - 1);
    $bytes /= (1024 ** $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function setup_ajax_mask(string $value): string
{
    $value = trim($value);
    if (strlen($value) <= 8) {
        return str_repeat('*', max(0, strlen($value)));
    }
    return substr($value, 0, 4) . str_repeat('*', 6) . substr($value, -4);
}
