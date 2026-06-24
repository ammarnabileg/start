<?php
/**
 * Installation engine. Handles AJAX actions from the setup wizard:
 *   action=requirements  -> system requirement checks
 *   action=test_db       -> test a database connection
 *   action=install       -> write .env, run schema, seed data, create super admin
 *   action=terminal      -> run a whitelisted diagnostic during setup
 *
 * Always returns JSON: {success: bool, ...}
 */
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
header('Content-Type: application/json; charset=utf-8');

define('ROOT', dirname(__DIR__));

/** Read a JSON or form POST body into an array. */
function input(): array
{
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
    return $_POST;
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$data = input();
$action = $_GET['action'] ?? ($data['action'] ?? '');

switch ($action) {
    case 'requirements':
        respond(['success' => true, 'checks' => checkRequirements()]);
        // no break (respond exits)

    case 'test_db':
        respond(testDatabase($data));

    case 'install':
        respond(runInstall($data));

    case 'terminal':
        respond(runTerminal((string) ($data['command'] ?? '')));

    default:
        respond(['success' => false, 'error' => 'Unknown action'], 400);
}

// ---------------------------------------------------------------------------
// Requirement checks
// ---------------------------------------------------------------------------
function checkRequirements(): array
{
    $checks = [];

    $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
    $checks[] = ['label' => 'PHP >= 8.1', 'ok' => $phpOk, 'value' => PHP_VERSION];

    foreach (['pdo_mysql', 'mbstring', 'json', 'curl', 'openssl'] as $ext) {
        $checks[] = ['label' => 'Extension: ' . $ext, 'ok' => extension_loaded($ext), 'value' => extension_loaded($ext) ? 'loaded' : 'missing'];
    }

    $paths = [
        'storage/logs'    => ROOT . '/storage/logs',
        'storage/cache'   => ROOT . '/storage/cache',
        'storage/uploads' => ROOT . '/storage/uploads',
        'project root (.env writable)' => ROOT,
    ];
    foreach ($paths as $label => $path) {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $writable = is_writable($path);
        $checks[] = ['label' => 'Writable: ' . $label, 'ok' => $writable, 'value' => $writable ? 'yes' : 'no'];
    }

    return $checks;
}

// ---------------------------------------------------------------------------
// Database test
// ---------------------------------------------------------------------------
function testDatabase(array $d): array
{
    $host = $d['db_host'] ?? '127.0.0.1';
    $port = (int) ($d['db_port'] ?? 3306);
    $name = $d['db_database'] ?? '';
    $user = $d['db_username'] ?? 'root';
    $pass = $d['db_password'] ?? '';

    try {
        // Connect without selecting the db first, to allow creating it.
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();

        $dbExists = false;
        if ($name !== '') {
            $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $stmt->execute([$name]);
            $dbExists = (bool) $stmt->fetchColumn();
        }
        return [
            'success'    => true,
            'version'    => $version,
            'db_exists'  => $dbExists,
            'message'    => 'Connection successful (MySQL ' . $version . ')' . ($name && !$dbExists ? ' — database will be created.' : ''),
        ];
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
    }
}

// ---------------------------------------------------------------------------
// Full install
// ---------------------------------------------------------------------------
function runInstall(array $d): array
{
    $steps = [];
    try {
        $host = $d['db_host'] ?? '127.0.0.1';
        $port = (int) ($d['db_port'] ?? 3306);
        $name = $d['db_database'] ?? 'airecruitment';
        $user = $d['db_username'] ?? 'root';
        $pass = $d['db_password'] ?? '';

        // 1) Connect & create database if needed.
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");
        $steps[] = 'Database ready';

        // 2) Run schema.
        $schema = file_get_contents(ROOT . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('schema.sql not found');
        }
        runSqlScript($pdo, $schema);
        $steps[] = 'Schema installed';

        // 3) Seed permissions + roles.
        seedPermissions($pdo);
        $steps[] = 'Permissions seeded';
        $roleIds = seedSystemRoles($pdo);
        $steps[] = 'System roles created';

        // 4) Create the super admin user.
        $adminName = trim((string) ($d['admin_name'] ?? 'Super Admin'));
        $parts = preg_split('/\s+/', $adminName, 2);
        $first = $parts[0] ?? 'Super';
        $last = $parts[1] ?? 'Admin';
        $email = strtolower(trim((string) ($d['admin_email'] ?? '')));
        $password = (string) ($d['admin_password'] ?? '');
        if ($email === '' || strlen($password) < 8) {
            throw new RuntimeException('A valid admin email and a password of at least 8 characters are required.');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND tenant_id IS NULL LIMIT 1');
        $stmt->execute([$email]);
        $existingId = $stmt->fetchColumn();
        if ($existingId) {
            $pdo->prepare('UPDATE users SET password_hash = ?, first_name = ?, last_name = ?, is_super_admin = 1, status = "active" WHERE id = ?')
                ->execute([$hash, $first, $last, $existingId]);
            $adminId = (int) $existingId;
        } else {
            $pdo->prepare('INSERT INTO users (tenant_id, email, password_hash, first_name, last_name, status, is_super_admin) VALUES (NULL, ?, ?, ?, ?, "active", 1)')
                ->execute([$email, $hash, $first, $last]);
            $adminId = (int) $pdo->lastInsertId();
        }
        // Attach the platform super_admin role.
        if (!empty($roleIds['super_admin'])) {
            $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$adminId, $roleIds['super_admin']]);
        }
        $steps[] = 'Super admin created';

        // 5) Write .env.
        writeEnv($d, $name, $host, $port, $user, $pass);
        $steps[] = '.env written';

        // 6) Ensure storage dirs.
        foreach (['storage/logs', 'storage/cache', 'storage/uploads', 'public/uploads'] as $dir) {
            if (!is_dir(ROOT . '/' . $dir)) {
                @mkdir(ROOT . '/' . $dir, 0775, true);
            }
        }
        $steps[] = 'Storage prepared';

        return ['success' => true, 'steps' => $steps, 'redirect' => '/login'];
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'steps' => $steps];
    }
}

/** Execute a multi-statement SQL script, tolerating MySQL directives. */
function runSqlScript(PDO $pdo, string $sql): void
{
    // Strip line comments.
    $sql = preg_replace('/^--.*$/m', '', $sql);
    // Split on semicolons at line ends (schema has no procedures/delimiters).
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql)));
    foreach ($statements as $statement) {
        $statement = trim(rtrim($statement, ';'));
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

function seedPermissions(PDO $pdo): void
{
    $modules = require ROOT . '/config/permissions.php';
    $stmt = $pdo->prepare(
        'INSERT INTO permissions (name, display_name, module) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), module = VALUES(module)'
    );
    foreach ($modules as $module => $perms) {
        foreach ($perms as $name => $display) {
            $stmt->execute([$name, $display, $module]);
        }
    }
}

/**
 * Create system roles (tenant_id NULL = template/platform roles) and attach
 * permissions. Returns ['super_admin'=>id, 'admin'=>id, ...].
 */
function seedSystemRoles(PDO $pdo): array
{
    $allPerms = [];
    $modules = require ROOT . '/config/permissions.php';
    foreach ($modules as $perms) {
        foreach ($perms as $name => $_) {
            $allPerms[] = $name;
        }
    }

    $operational = array_values(array_filter($allPerms, fn($p) => strpos($p, 'platform.') !== 0));
    $hiringManager = array_values(array_filter($allPerms, function ($p) {
        return in_array($p, [
            'dashboard.view', 'jobs.view', 'candidates.view', 'candidates.compare',
            'interviews.view', 'interviews.report', 'pipeline.view', 'offers.view',
            'offers.create', 'talent_pool.view', 'ai.use',
        ], true);
    }));

    $definitions = [
        'super_admin'    => ['Super Administrator', $allPerms],
        'admin'          => ['Company Administrator', $operational],
        'recruiter'      => ['Recruiter', $operational],
        'hiring_manager' => ['Hiring Manager', $hiringManager],
    ];

    $roleIds = [];
    foreach ($definitions as $name => [$display, $perms]) {
        // Find existing system role by name with NULL tenant.
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = ? AND tenant_id IS NULL LIMIT 1');
        $stmt->execute([$name]);
        $roleId = $stmt->fetchColumn();
        if (!$roleId) {
            $pdo->prepare('INSERT INTO roles (tenant_id, name, display_name, is_system) VALUES (NULL, ?, ?, 1)')
                ->execute([$name, $display]);
            $roleId = (int) $pdo->lastInsertId();
        }
        $roleId = (int) $roleId;
        $roleIds[$name] = $roleId;

        // Attach permissions.
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
        if (!empty($perms)) {
            $in = implode(',', array_fill(0, count($perms), '?'));
            $pstmt = $pdo->prepare('SELECT id FROM permissions WHERE name IN (' . $in . ')');
            $pstmt->execute($perms);
            $ins = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($pstmt->fetchAll(PDO::FETCH_COLUMN) as $pid) {
                $ins->execute([$roleId, $pid]);
            }
        }
    }
    return $roleIds;
}

function writeEnv(array $d, string $name, string $host, int $port, string $user, string $pass): void
{
    $appKey = base64_encode(random_bytes(32));
    $jwtSecret = bin2hex(random_bytes(32));
    $appUrl = rtrim((string) ($d['app_url'] ?? 'http://localhost'), '/');
    $appName = (string) ($d['app_name'] ?? 'AI Recruit');
    $openai = (string) ($d['openai_api_key'] ?? '');
    $openaiModel = (string) ($d['openai_model'] ?? 'gpt-4-turbo-preview');
    $heygen = (string) ($d['heygen_api_key'] ?? '');

    $env = <<<ENV
APP_NAME="{$appName}"
APP_URL={$appUrl}
APP_ENV=production
APP_KEY={$appKey}

DB_HOST={$host}
DB_PORT={$port}
DB_DATABASE={$name}
DB_USERNAME={$user}
DB_PASSWORD={$pass}

OPENAI_API_KEY={$openai}
OPENAI_MODEL={$openaiModel}

HEYGEN_API_KEY={$heygen}

JWT_SECRET={$jwtSecret}
JWT_EXPIRY=86400

MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM=noreply@example.com

ENV;

    if (file_put_contents(ROOT . '/.env', $env) === false) {
        throw new RuntimeException('Could not write .env file. Check root directory permissions.');
    }
}

// ---------------------------------------------------------------------------
// Whitelisted terminal during setup (safe diagnostics only)
// ---------------------------------------------------------------------------
function runTerminal(string $key): array
{
    $output = '';
    switch ($key) {
        case 'php_version':
            $output = trim(shell_exec('php -v 2>&1') ?: ('PHP ' . PHP_VERSION));
            break;
        case 'php_modules':
            $output = implode("\n", get_loaded_extensions());
            break;
        case 'php_info':
            ob_start(); phpinfo(INFO_GENERAL | INFO_CONFIGURATION); $info = ob_get_clean() ?: '';
            $output = trim(strip_tags($info));
            $output = implode("\n", array_slice(explode("\n", $output), 0, 60));
            break;
        case 'disk':
            $output = trim(shell_exec('df -h 2>&1') ?: 'df unavailable');
            break;
        case 'memory':
            $output = trim(shell_exec('free -m 2>&1') ?: 'free unavailable');
            break;
        case 'clear_cache':
            $n = 0;
            foreach (glob(ROOT . '/storage/cache/*') ?: [] as $f) {
                if (is_file($f)) { @unlink($f); $n++; }
            }
            $output = "Cleared {$n} cache file(s).";
            break;
        case 'permissions':
            $lines = [];
            foreach (['storage/logs', 'storage/cache', 'storage/uploads', '.env (root)'] as $p) {
                $path = $p === '.env (root)' ? ROOT : ROOT . '/' . $p;
                $lines[] = sprintf('%-22s %s', $p, is_writable($path) ? 'writable' : 'NOT writable');
            }
            $output = implode("\n", $lines);
            break;
        case 'db_test':
            $output = 'Use the "Test Connection" button on the database step.';
            break;
        default:
            return ['success' => false, 'error' => 'Command not allowed'];
    }
    return ['success' => true, 'output' => $output, 'command' => $key];
}
