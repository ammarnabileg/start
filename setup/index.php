<?php
/**
 * HireAI - Setup Wizard
 * Access at: yourdomain.com/setup/
 */

session_start();

define('SETUP_DIR', __DIR__);
define('ROOT_DIR', dirname(__DIR__));
define('LOCK_FILE', SETUP_DIR . '/.locked');
define('INSTALLED_FILE', ROOT_DIR . '/.installed');
define('ENV_FILE', ROOT_DIR . '/.env');
define('SCHEMA_FILE', ROOT_DIR . '/database/schema.sql');

// ──────────────────────────────────────────
// Security: check if locked
// ──────────────────────────────────────────
if (file_exists(LOCK_FILE)) {
    if (!isset($_SESSION['setup_unlocked'])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_password'])) {
            $stored = file_get_contents(LOCK_FILE);
            if (password_verify($_POST['unlock_password'], trim($stored))) {
                $_SESSION['setup_unlocked'] = true;
            } else {
                $lockError = 'Incorrect password. Please try again.';
            }
        }
        if (!isset($_SESSION['setup_unlocked'])) {
            showLockScreen($lockError ?? null);
            exit;
        }
    }
}

// ──────────────────────────────────────────
// Handle AJAX actions
// ──────────────────────────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'test_db') {
        $host = $_POST['db_host'] ?? 'localhost';
        $port = $_POST['db_port'] ?? '3306';
        $name = $_POST['db_name'] ?? '';
        $user = $_POST['db_user'] ?? '';
        $pass = $_POST['db_pass'] ?? '';
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 5]);
            echo json_encode(['ok' => true, 'msg' => 'Connection successful! MySQL ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Connection failed: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'test_openai') {
        $key = $_POST['openai_key'] ?? '';
        if (empty($key)) { echo json_encode(['ok' => false, 'msg' => 'API key is required']); exit; }
        $ch = curl_init('https://api.openai.com/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$key}"],
            CURLOPT_TIMEOUT => 10,
        ]);
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http === 200) {
            echo json_encode(['ok' => true, 'msg' => 'OpenAI key is valid!']);
        } else {
            $err = json_decode($res, true);
            echo json_encode(['ok' => false, 'msg' => $err['error']['message'] ?? 'Invalid API key']);
        }
        exit;
    }

    if ($action === 'test_heygen') {
        $key = $_POST['heygen_key'] ?? '';
        if (empty($key)) { echo json_encode(['ok' => false, 'msg' => 'API key is required']); exit; }
        $ch = curl_init('https://api.heygen.com/v2/avatars');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["X-Api-Key: {$key}"],
            CURLOPT_TIMEOUT => 10,
        ]);
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http === 200) {
            echo json_encode(['ok' => true, 'msg' => 'HeyGen key is valid!']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Invalid HeyGen key (HTTP ' . $http . ')']);
        }
        exit;
    }

    if ($action === 'install') {
        installSystem();
        exit;
    }

    if ($action === 'delete_setup') {
        $pass = $_POST['confirm_password'] ?? '';
        if (file_exists(ENV_FILE)) {
            $env = parse_ini_file(ENV_FILE);
            // Verify using stored super admin (just check env exists as a basic guard)
        }
        // Delete setup directory recursively
        function deleteDir($dir) {
            if (!is_dir($dir)) return;
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $f) {
                $path = "$dir/$f";
                is_dir($path) ? deleteDir($path) : unlink($path);
            }
            rmdir($dir);
        }
        deleteDir(SETUP_DIR);
        echo json_encode(['ok' => true, 'msg' => 'Setup files deleted successfully.']);
        exit;
    }

    if ($action === 'lock_setup') {
        $pass = $_POST['lock_password'] ?? '';
        if (strlen($pass) < 6) {
            echo json_encode(['ok' => false, 'msg' => 'Password must be at least 6 characters']);
            exit;
        }
        file_put_contents(LOCK_FILE, password_hash($pass, PASSWORD_DEFAULT));
        echo json_encode(['ok' => true, 'msg' => 'Setup locked successfully.']);
        exit;
    }

    if ($action === 'save_settings') {
        // Edit settings after install
        $data = $_POST;
        if (!file_exists(ENV_FILE)) {
            echo json_encode(['ok' => false, 'msg' => '.env file not found']);
            exit;
        }
        $env = [];
        foreach (file(ENV_FILE) as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') { $env[] = $line; continue; }
            if (strpos($line, '=') !== false) {
                [$k] = explode('=', $line, 2);
                $k = trim($k);
                if (isset($data['APP_NAME']) && $k === 'APP_NAME') {
                    $env[] = "APP_NAME=" . $data['APP_NAME'];
                } elseif (isset($data['OPENAI_API_KEY']) && $k === 'OPENAI_API_KEY') {
                    $env[] = "OPENAI_API_KEY=" . $data['OPENAI_API_KEY'];
                } elseif (isset($data['HEYGEN_API_KEY']) && $k === 'HEYGEN_API_KEY') {
                    $env[] = "HEYGEN_API_KEY=" . $data['HEYGEN_API_KEY'];
                } else {
                    $env[] = $line;
                }
            } else {
                $env[] = $line;
            }
        }
        file_put_contents(ENV_FILE, implode("\n", $env));
        echo json_encode(['ok' => true, 'msg' => 'Settings updated successfully.']);
        exit;
    }

    if ($action === 'terminal') {
        runTerminalCommand($_POST['cmd'] ?? '');
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
    exit;
}

// ──────────────────────────────────────────
// Main: show wizard or already-installed page
// ──────────────────────────────────────────
$isInstalled = file_exists(INSTALLED_FILE);
$tab = $_GET['tab'] ?? ($isInstalled ? 'settings' : 'install');

// ──────────────────────────────────────────
// INSTALL SYSTEM FUNCTION
// ──────────────────────────────────────────
function installSystem() {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $steps = [];

    try {
        // Step 1: Validate
        $steps[] = ['step' => 'Validating inputs...', 'ok' => true];

        $dbHost = $data['db_host'] ?? 'localhost';
        $dbPort = $data['db_port'] ?? '3306';
        $dbName = $data['db_name'] ?? '';
        $dbUser = $data['db_user'] ?? '';
        $dbPass = $data['db_pass'] ?? '';
        $appName = $data['app_name'] ?? 'HireAI';
        $adminName = $data['admin_name'] ?? '';
        $adminEmail = $data['admin_email'] ?? '';
        $adminPass = $data['admin_pass'] ?? '';
        $openaiKey = $data['openai_key'] ?? '';
        $heygenKey = $data['heygen_key'] ?? '';

        if (!$dbName || !$dbUser || !$adminEmail || !$adminPass || !$openaiKey) {
            echo json_encode(['ok' => false, 'steps' => $steps, 'msg' => 'Required fields missing']);
            return;
        }

        // Step 2: DB connection
        $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $steps[] = ['step' => 'Database connection established', 'ok' => true];

        // Step 3: Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        $steps[] = ['step' => "Database '{$dbName}' ready", 'ok' => true];

        // Step 4: Run schema
        $schema = file_get_contents(ROOT_DIR . '/database/schema.sql');
        // Split by semicolons and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
        $steps[] = ['step' => 'Database tables created', 'ok' => true];

        // Step 5: Run seeds
        $seedDir = ROOT_DIR . '/database/seeds';
        foreach (['permissions.sql', 'roles.sql'] as $seed) {
            $file = $seedDir . '/' . $seed;
            if (file_exists($file)) {
                $sql = file_get_contents($file);
                $stmts = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($stmts as $s) {
                    if (!empty($s)) $pdo->exec($s);
                }
            }
        }
        $steps[] = ['step' => 'Permissions and roles seeded', 'ok' => true];

        // Step 6: Create super admin (prepared statements; no string escaping).
        $passHash = password_hash($adminPass, PASSWORD_DEFAULT);
        $adminEmail = strtolower(trim($adminEmail));

        // Upsert the super admin user (tenant_id NULL = platform-level).
        $ins = $pdo->prepare(
            "INSERT INTO users (tenant_id, full_name, email, password_hash, type, status, email_verified_at, created_at)
             VALUES (NULL, ?, ?, ?, 'super_admin', 'active', NOW(), NOW())
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name), type = 'super_admin', status = 'active'"
        );
        $ins->execute([$adminName, $adminEmail, $passHash]);

        // Resolve the user id (lastInsertId is 0 on the UPDATE branch).
        $adminId = (int) $pdo->lastInsertId();
        if ($adminId === 0) {
            $sel = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id IS NULL LIMIT 1");
            $sel->execute([$adminEmail]);
            $adminId = (int) $sel->fetchColumn();
        }

        // Link the super_admin role so Auth::isSuper()/can() work after login.
        $roleStmt = $pdo->query("SELECT id FROM roles WHERE slug = 'super_admin' ORDER BY (tenant_id IS NULL) DESC, id ASC LIMIT 1");
        $roleId = (int) ($roleStmt ? $roleStmt->fetchColumn() : 0);
        if ($adminId > 0 && $roleId > 0) {
            $link = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
            $link->execute([$adminId, $roleId]);
        }
        $steps[] = ['step' => 'Super admin account created', 'ok' => true];

        // Step 7: Generate JWT secret
        $jwtSecret = bin2hex(random_bytes(32));
        $appUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        // Step 8: Write .env
        $env = <<<ENV
APP_NAME={$appName}
APP_URL={$appUrl}
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_LANGUAGE=en

DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_NAME={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPass}

JWT_SECRET={$jwtSecret}
JWT_EXPIRY=86400

OPENAI_API_KEY={$openaiKey}
OPENAI_MODEL=gpt-4o

HEYGEN_API_KEY={$heygenKey}

UPLOAD_MAX_SIZE=10485760
ALLOWED_EXTENSIONS=pdf,docx,doc
ENV;
        file_put_contents(ENV_FILE, $env);
        $steps[] = ['step' => '.env configuration file created', 'ok' => true];

        // Step 9: Create storage dirs
        $dirs = [
            ROOT_DIR . '/storage/logs',
            ROOT_DIR . '/storage/cache',
            ROOT_DIR . '/storage/uploads/cvs',
            ROOT_DIR . '/storage/uploads/avatars',
            ROOT_DIR . '/storage/uploads/logos',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) mkdir($dir, 0755, true);
        }
        file_put_contents(ROOT_DIR . '/storage/logs/.gitkeep', '');
        $steps[] = ['step' => 'Storage directories created', 'ok' => true];

        // Step 10: Write .htaccess for public
        $htaccess = <<<HT
Options -Indexes
RewriteEngine On

# Block direct access to sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>
<Files ".installed">
    Order allow,deny
    Deny from all
</Files>

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Route to public/index.php
RewriteCond %{REQUEST_URI} !^/setup/
RewriteCond %{REQUEST_URI} !^/public/assets/
RewriteCond %{REQUEST_URI} !^/storage/uploads/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /public/index.php [QSA,L]
HT;
        file_put_contents(ROOT_DIR . '/.htaccess', $htaccess);
        $steps[] = ['step' => '.htaccess configured', 'ok' => true];

        // Step 11: Write system settings to DB
        $settings = [
            ['platform_name', $appName, 'string'],
            ['platform_url', $appUrl, 'string'],
            ['installed_at', date('Y-m-d H:i:s'), 'string'],
            ['default_ai_model', 'gpt-4o', 'string'],
        ];
        foreach ($settings as [$k, $v, $t]) {
            $pdo->exec("INSERT INTO system_settings (`key`, `value`, `type`) VALUES ('" . addslashes($k) . "', '" . addslashes($v) . "', '$t')
                ON DUPLICATE KEY UPDATE `value` = '" . addslashes($v) . "'");
        }

        // Step 12: Write installed marker
        file_put_contents(INSTALLED_FILE, date('Y-m-d H:i:s') . "\nInstalled by: {$adminEmail}");
        $steps[] = ['step' => 'Installation completed successfully!', 'ok' => true];

        echo json_encode(['ok' => true, 'steps' => $steps, 'msg' => 'Installation complete!']);

    } catch (Exception $e) {
        $steps[] = ['step' => 'Error: ' . $e->getMessage(), 'ok' => false];
        echo json_encode(['ok' => false, 'steps' => $steps, 'msg' => $e->getMessage()]);
    }
}

// ──────────────────────────────────────────
// TERMINAL COMMAND RUNNER
// ──────────────────────────────────────────
function runTerminalCommand(string $cmd): void {
    header('Content-Type: application/json');

    $whitelist = [
        'php_version'       => 'php_version',
        'php_extensions'    => 'php_extensions',
        'disk_space'        => 'disk_space',
        'memory_info'       => 'memory_info',
        'file_permissions'  => 'file_permissions',
        'clear_cache'       => 'clear_cache',
        'view_logs'         => 'view_logs',
        'test_db'           => 'test_db_conn',
        'mysql_version'     => 'mysql_version',
        'check_writable'    => 'check_writable',
        'list_uploads'      => 'list_uploads',
        'installed_date'    => 'installed_date',
        'env_check'         => 'env_check',
    ];

    if (!isset($whitelist[$cmd])) {
        echo json_encode(['ok' => false, 'output' => 'Command not allowed: ' . htmlspecialchars($cmd)]);
        return;
    }

    $output = '';
    switch ($cmd) {
        case 'php_version':
            $output = 'PHP Version: ' . PHP_VERSION . "\nSAPI: " . php_sapi_name();
            break;
        case 'php_extensions':
            $exts = get_loaded_extensions();
            sort($exts);
            $output = 'Loaded Extensions (' . count($exts) . "):\n" . implode(', ', $exts);
            break;
        case 'disk_space':
            $free = disk_free_space(ROOT_DIR);
            $total = disk_total_space(ROOT_DIR);
            $output = sprintf("Disk Total: %s\nDisk Free: %s\nDisk Used: %s (%.1f%%)",
                formatBytes($total), formatBytes($free),
                formatBytes($total - $free),
                (($total - $free) / $total) * 100
            );
            break;
        case 'memory_info':
            $output = 'Memory Limit: ' . ini_get('memory_limit') . "\nMemory Used: " . formatBytes(memory_get_usage(true));
            break;
        case 'file_permissions':
            $paths = [ROOT_DIR . '/storage', ROOT_DIR . '/storage/logs', ROOT_DIR . '/storage/cache', ROOT_DIR . '/storage/uploads', ROOT_DIR . '/.env'];
            $lines = [];
            foreach ($paths as $p) {
                if (file_exists($p)) {
                    $perms = substr(sprintf('%o', fileperms($p)), -4);
                    $writable = is_writable($p) ? '✓ writable' : '✗ not writable';
                    $lines[] = "{$perms} {$writable}  " . basename($p);
                } else {
                    $lines[] = '[missing]  ' . basename($p);
                }
            }
            $output = implode("\n", $lines);
            break;
        case 'clear_cache':
            $cacheDir = ROOT_DIR . '/storage/cache';
            $count = 0;
            if (is_dir($cacheDir)) {
                foreach (glob($cacheDir . '/*') as $f) {
                    if (is_file($f)) { unlink($f); $count++; }
                }
            }
            $output = "Cache cleared. {$count} file(s) deleted.";
            break;
        case 'view_logs':
            $logFile = ROOT_DIR . '/storage/logs/app.log';
            if (!file_exists($logFile)) {
                $output = '[Log file empty or not found]';
            } else {
                $lines = file($logFile);
                $last = array_slice($lines, -50);
                $output = implode('', $last);
            }
            break;
        case 'test_db_conn':
            if (!file_exists(ENV_FILE)) { $output = '.env not found'; break; }
            $env = parse_ini_file(ENV_FILE);
            try {
                $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
                $pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_TIMEOUT => 5]);
                $v = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                $output = "✓ Database connection OK\nMySQL Version: {$v}\nDatabase: {$env['DB_NAME']}";
            } catch (Exception $e) {
                $output = '✗ Connection failed: ' . $e->getMessage();
            }
            break;
        case 'mysql_version':
            if (!file_exists(ENV_FILE)) { $output = '.env not found'; break; }
            $env = parse_ini_file(ENV_FILE);
            try {
                $pdo = new PDO("mysql:host={$env['DB_HOST']};port={$env['DB_PORT']}", $env['DB_USERNAME'], $env['DB_PASSWORD']);
                $output = 'MySQL Version: ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            } catch (Exception $e) {
                $output = 'Error: ' . $e->getMessage();
            }
            break;
        case 'check_writable':
            $dirs = ['storage', 'storage/logs', 'storage/cache', 'storage/uploads'];
            $lines = [];
            foreach ($dirs as $d) {
                $path = ROOT_DIR . '/' . $d;
                $lines[] = (is_writable($path) ? '✓' : '✗') . ' ' . $d;
            }
            $output = implode("\n", $lines);
            break;
        case 'list_uploads':
            $uploadDir = ROOT_DIR . '/storage/uploads';
            $count = 0;
            $size = 0;
            if (is_dir($uploadDir)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir)) as $f) {
                    if ($f->isFile()) { $count++; $size += $f->getSize(); }
                }
            }
            $output = "Upload files: {$count}\nTotal size: " . formatBytes($size);
            break;
        case 'installed_date':
            if (file_exists(INSTALLED_FILE)) {
                $output = 'Installation info:' . "\n" . file_get_contents(INSTALLED_FILE);
            } else {
                $output = 'Not installed yet.';
            }
            break;
        case 'env_check':
            if (!file_exists(ENV_FILE)) { $output = '.env not found'; break; }
            $env = parse_ini_file(ENV_FILE);
            $keys = ['APP_NAME', 'DB_HOST', 'DB_NAME', 'OPENAI_API_KEY', 'HEYGEN_API_KEY', 'JWT_SECRET'];
            $lines = [];
            foreach ($keys as $k) {
                $val = $env[$k] ?? '';
                $masked = strlen($val) > 8 ? substr($val, 0, 4) . '****' . substr($val, -4) : (empty($val) ? '[empty]' : '****');
                $status = !empty($val) ? '✓' : '✗';
                $lines[] = "{$status} {$k}: {$masked}";
            }
            $output = implode("\n", $lines);
            break;
    }

    echo json_encode(['ok' => true, 'output' => $output]);
}

function formatBytes(float $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function showLockScreen(?string $error): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup - Locked</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center">
<div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-10 w-full max-w-md text-center">
  <div class="w-16 h-16 bg-violet-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
    <svg class="w-8 h-8 text-violet-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
  </div>
  <h1 class="text-2xl font-bold text-gray-900 mb-2">Setup is Locked</h1>
  <p class="text-gray-500 mb-8">Enter your setup password to continue.</p>
  <?php if ($error): ?>
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-6 text-sm"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <input type="password" name="unlock_password" placeholder="Setup password"
      class="w-full border border-gray-300 rounded-xl px-4 py-3 mb-4 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-center text-lg tracking-widest"
      autofocus required>
    <button type="submit" class="w-full bg-violet-700 hover:bg-violet-800 text-white rounded-full py-3 font-semibold transition-colors">Unlock Setup</button>
  </form>
</div>
</body>
</html>
<?php }

// Read current .env values for settings tab
$currentSettings = [];
if (file_exists(ENV_FILE)) {
    $currentSettings = parse_ini_file(ENV_FILE) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isInstalled ? 'Setup - ' . ($currentSettings['APP_NAME'] ?? 'HireAI') : 'Install HireAI' ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { font-family: 'Inter', sans-serif; }
  .terminal { font-family: 'Courier New', 'Consolas', monospace; }
  .tab-active { border-bottom: 2px solid #7C3AED; color: #7C3AED; }
  .tab-inactive { border-bottom: 2px solid transparent; color: #6B7280; }
  .progress-step.done { background: #7C3AED; color: white; }
  .progress-step.active { background: #7C3AED; color: white; box-shadow: 0 0 0 4px #EDE9FE; }
  .progress-step.pending { background: #E5E7EB; color: #9CA3AF; }
  .progress-line.done { background: #7C3AED; }
  .progress-line.pending { background: #E5E7EB; }
  .log-ok { color: #10B981; }
  .log-err { color: #EF4444; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .spin { animation: spin 1s linear infinite; }
  @keyframes blink { 50% { opacity: 0; } }
  .cursor-blink { animation: blink 1s step-end infinite; }
  @keyframes fadeIn { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }
  .fade-in { animation: fadeIn 0.3s ease; }
</style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Header -->
<div class="bg-gradient-to-r from-violet-900 via-violet-700 to-purple-700 text-white">
  <div class="max-w-5xl mx-auto px-6 py-5 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2M9 9h6"/></svg>
      </div>
      <div>
        <div class="font-bold text-lg leading-tight"><?= $isInstalled ? htmlspecialchars($currentSettings['APP_NAME'] ?? 'HireAI') : 'HireAI Setup' ?></div>
        <div class="text-xs text-violet-200"><?= $isInstalled ? 'Settings & Management' : 'Installation Wizard' ?></div>
      </div>
    </div>
    <?php if ($isInstalled): ?>
    <a href="/login" class="text-sm bg-white/20 hover:bg-white/30 text-white rounded-full px-4 py-1.5 transition-colors font-medium">→ Go to Platform</a>
    <?php endif; ?>
  </div>

  <!-- Tabs -->
  <div class="max-w-5xl mx-auto px-6">
    <div class="flex gap-1">
      <?php
      $tabs = $isInstalled
        ? [['id'=>'settings','label'=>'⚙ Settings'], ['id'=>'terminal','label'=>'⬛ Terminal'], ['id'=>'security','label'=>'🔒 Security']]
        : [['id'=>'install','label'=>'🚀 Install'], ['id'=>'terminal','label'=>'⬛ Terminal']];
      foreach ($tabs as $t): ?>
      <a href="?tab=<?= $t['id'] ?>"
        class="px-5 py-3 text-sm font-medium transition-colors <?= $tab === $t['id'] ? 'bg-white/20 text-white border-b-2 border-white' : 'text-violet-200 hover:text-white' ?>">
        <?= $t['label'] ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="max-w-5xl mx-auto px-6 py-8">

<!-- ═══════════════════════════════════════════════════ -->
<!-- TAB: INSTALL -->
<!-- ═══════════════════════════════════════════════════ -->
<?php if ($tab === 'install' && !$isInstalled): ?>

<!-- Progress Steps -->
<div class="flex items-center mb-10" id="progressBar">
  <?php
  $steps = ['Platform Info', 'Database', 'AI Settings', 'Install'];
  for ($i = 0; $i < count($steps); $i++):
    $cls = 'pending';
    if ($i === 0) $cls = 'active';
  ?>
  <div class="flex items-center <?= $i < count($steps)-1 ? 'flex-1' : '' ?>">
    <div class="flex flex-col items-center">
      <div id="step-circle-<?= $i ?>" class="progress-step <?= $cls ?> w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300">
        <span id="step-num-<?= $i ?>"><?= $i+1 ?></span>
      </div>
      <div class="text-xs mt-2 font-medium text-gray-600 whitespace-nowrap"><?= $steps[$i] ?></div>
    </div>
    <?php if ($i < count($steps)-1): ?>
    <div id="step-line-<?= $i ?>" class="progress-line pending h-0.5 flex-1 mx-3 transition-all duration-500"></div>
    <?php endif; ?>
  </div>
  <?php endfor; ?>
</div>

<!-- Step Forms -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

  <!-- Step 1: Platform Info -->
  <div id="step-0" class="p-8 fade-in">
    <h2 class="text-xl font-bold text-gray-900 mb-1">Platform Information</h2>
    <p class="text-gray-500 text-sm mb-8">Set up your platform name and create the super admin account.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Platform Name <span class="text-red-500">*</span></label>
        <input type="text" id="app_name" value="HireAI" placeholder="e.g. HireAI, TalentPro, RecruitHub"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Your Full Name <span class="text-red-500">*</span></label>
        <input type="text" id="admin_name" placeholder="e.g. Ahmed Mohamed"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Admin Email <span class="text-red-500">*</span></label>
        <input type="email" id="admin_email" placeholder="admin@yourdomain.com"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Admin Password <span class="text-red-500">*</span></label>
        <div class="relative">
          <input type="password" id="admin_pass" placeholder="Min. 8 characters"
            class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-12 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
          <button type="button" onclick="togglePass('admin_pass')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Confirm Password <span class="text-red-500">*</span></label>
        <input type="password" id="admin_pass_confirm" placeholder="Repeat password"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
      </div>
    </div>
    <div id="step0-error" class="hidden mt-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm"></div>
    <div class="flex justify-end mt-8">
      <button onclick="nextStep(0)" class="bg-violet-700 hover:bg-violet-800 text-white rounded-full px-8 py-3 font-semibold transition-colors flex items-center gap-2">
        Continue <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>
  </div>

  <!-- Step 2: Database -->
  <div id="step-1" class="p-8 fade-in hidden">
    <h2 class="text-xl font-bold text-gray-900 mb-1">Database Configuration</h2>
    <p class="text-gray-500 text-sm mb-8">Enter your MySQL database credentials. The database will be created automatically if it doesn't exist.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Database Host <span class="text-red-500">*</span></label>
        <input type="text" id="db_host" value="localhost"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Port</label>
        <input type="text" id="db_port" value="3306"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Database Name <span class="text-red-500">*</span></label>
        <input type="text" id="db_name" placeholder="e.g. hireai_db"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Username <span class="text-red-500">*</span></label>
        <input type="text" id="db_user" placeholder="e.g. root"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Password</label>
        <div class="relative">
          <input type="password" id="db_pass" placeholder="Leave empty if no password"
            class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-12 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
          <button type="button" onclick="togglePass('db_pass')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>
        </div>
      </div>
    </div>

    <div id="db-test-result" class="hidden mt-4 rounded-xl px-4 py-3 text-sm font-medium"></div>

    <div class="flex items-center justify-between mt-8">
      <button onclick="prevStep(1)" class="text-gray-500 hover:text-gray-700 rounded-full px-6 py-3 font-medium transition-colors flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back
      </button>
      <div class="flex items-center gap-3">
        <button onclick="testDB()" class="border border-violet-700 text-violet-700 hover:bg-violet-50 rounded-full px-6 py-3 font-medium transition-colors flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Test Connection
        </button>
        <button onclick="nextStep(1)" class="bg-violet-700 hover:bg-violet-800 text-white rounded-full px-8 py-3 font-semibold transition-colors flex items-center gap-2">
          Continue <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>
    </div>
  </div>

  <!-- Step 3: AI Settings -->
  <div id="step-2" class="p-8 fade-in hidden">
    <h2 class="text-xl font-bold text-gray-900 mb-1">AI Configuration</h2>
    <p class="text-gray-500 text-sm mb-8">Connect your OpenAI account to power all AI features. HeyGen is optional (for video interviews only).</p>

    <div class="space-y-6">
      <div class="bg-violet-50 border border-violet-200 rounded-2xl p-6">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 bg-violet-700 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          </div>
          <div class="flex-1">
            <label class="block text-sm font-bold text-gray-900 mb-1">OpenAI API Key <span class="text-red-500">*</span></label>
            <p class="text-xs text-gray-500 mb-3">Used for AI interviews, CV analysis, candidate matching, and all AI features. Get yours at platform.openai.com</p>
            <div class="flex gap-3">
              <div class="relative flex-1">
                <input type="password" id="openai_key" placeholder="sk-proj-..."
                  class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-12 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white text-gray-900">
                <button type="button" onclick="togglePass('openai_key')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
              </div>
              <button onclick="testOpenAI()" class="border border-violet-700 text-violet-700 hover:bg-violet-700 hover:text-white rounded-xl px-4 py-3 font-medium transition-colors text-sm whitespace-nowrap">
                Validate Key
              </button>
            </div>
            <div id="openai-result" class="hidden mt-2 text-sm font-medium rounded-lg px-3 py-2"></div>
          </div>
        </div>
      </div>

      <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6">
        <div class="flex items-start gap-4">
          <div class="w-10 h-10 bg-gray-700 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <label class="block text-sm font-bold text-gray-900">HeyGen API Key</label>
              <span class="text-xs bg-gray-200 text-gray-600 rounded-full px-2 py-0.5">Optional</span>
            </div>
            <p class="text-xs text-gray-500 mb-3">Only needed for video avatar interviews. Text and voice interviews work without it.</p>
            <div class="flex gap-3">
              <div class="relative flex-1">
                <input type="password" id="heygen_key" placeholder="Leave empty to skip video interviews"
                  class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-12 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none bg-white text-gray-900">
                <button type="button" onclick="togglePass('heygen_key')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
              </div>
              <button onclick="testHeyGen()" class="border border-gray-400 text-gray-600 hover:bg-gray-100 rounded-xl px-4 py-3 font-medium transition-colors text-sm whitespace-nowrap">
                Validate Key
              </button>
            </div>
            <div id="heygen-result" class="hidden mt-2 text-sm font-medium rounded-lg px-3 py-2"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="flex items-center justify-between mt-8">
      <button onclick="prevStep(2)" class="text-gray-500 hover:text-gray-700 rounded-full px-6 py-3 font-medium transition-colors flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back
      </button>
      <button onclick="nextStep(2)" class="bg-violet-700 hover:bg-violet-800 text-white rounded-full px-8 py-3 font-semibold transition-colors flex items-center gap-2">
        Continue <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>
  </div>

  <!-- Step 4: Install -->
  <div id="step-3" class="p-8 fade-in hidden">
    <div id="pre-install">
      <h2 class="text-xl font-bold text-gray-900 mb-1">Ready to Install</h2>
      <p class="text-gray-500 text-sm mb-8">Review your settings and click Install to set up your platform.</p>

      <div class="bg-gray-50 rounded-2xl border border-gray-200 p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Installation Summary</h3>
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div class="text-gray-500">Platform Name</div><div id="sum-app" class="font-medium text-gray-900"></div>
          <div class="text-gray-500">Admin Email</div><div id="sum-email" class="font-medium text-gray-900"></div>
          <div class="text-gray-500">Database</div><div id="sum-db" class="font-medium text-gray-900"></div>
          <div class="text-gray-500">OpenAI</div><div id="sum-openai" class="font-medium text-gray-900"></div>
          <div class="text-gray-500">HeyGen</div><div id="sum-heygen" class="font-medium text-gray-900"></div>
        </div>
      </div>

      <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800 mb-6 flex items-start gap-2">
        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        This will create the database tables, configure the platform, and create your admin account.
      </div>

      <div class="flex items-center justify-between">
        <button onclick="prevStep(3)" class="text-gray-500 hover:text-gray-700 rounded-full px-6 py-3 font-medium transition-colors flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg> Back
        </button>
        <button onclick="runInstall()" class="bg-amber-400 hover:bg-amber-500 text-gray-900 rounded-full px-10 py-3 font-bold transition-colors flex items-center gap-2 shadow-sm">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          Install Now
        </button>
      </div>
    </div>

    <!-- Install Progress -->
    <div id="install-progress" class="hidden">
      <div class="text-center mb-8">
        <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center">
          <svg id="install-spinner" class="w-12 h-12 text-violet-600 spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          <svg id="install-done" class="w-12 h-12 text-emerald-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h2 id="install-title" class="text-xl font-bold text-gray-900">Installing...</h2>
        <p id="install-subtitle" class="text-gray-500 text-sm mt-1">Please wait, do not close this page.</p>
      </div>
      <div id="install-log" class="bg-gray-950 rounded-2xl p-5 terminal text-sm space-y-1.5 max-h-64 overflow-y-auto"></div>
    </div>

    <!-- Install Success -->
    <div id="install-success" class="hidden text-center py-6">
      <div class="text-6xl mb-4">🎉</div>
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Platform Installed Successfully!</h2>
      <p class="text-gray-500 mb-8">Your AI recruitment platform is ready. What would you like to do?</p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="/login" class="bg-violet-700 hover:bg-violet-800 text-white rounded-full px-8 py-3 font-semibold transition-colors inline-flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
          Go to Admin Panel
        </a>
        <button onclick="showSecurityOptions()" class="border border-red-300 text-red-600 hover:bg-red-50 rounded-full px-8 py-3 font-semibold transition-colors inline-flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          Secure Setup
        </button>
      </div>
      <div id="security-options" class="hidden mt-8 bg-gray-50 border border-gray-200 rounded-2xl p-6 text-left">
        <h3 class="font-semibold text-gray-900 mb-4">Security Options</h3>
        <div class="space-y-3">
          <button onclick="deleteSetup()" class="w-full flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-xl hover:bg-red-100 transition-colors text-left">
            <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            <div>
              <div class="font-semibold text-red-700 text-sm">Delete Setup Files (Recommended)</div>
              <div class="text-xs text-red-500 mt-0.5">Permanently removes the /setup folder. Most secure option.</div>
            </div>
          </button>
          <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
            <div class="font-semibold text-amber-700 text-sm mb-2 flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              Lock Setup with Password
            </div>
            <div class="flex gap-2">
              <input type="password" id="lock-pass" placeholder="Set lock password"
                class="flex-1 border border-amber-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none">
              <button onclick="lockSetup()" class="bg-amber-400 hover:bg-amber-500 text-gray-900 rounded-xl px-4 py-2 text-sm font-semibold transition-colors">Lock</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php elseif ($tab === 'settings' && $isInstalled): ?>
<!-- ═══════════════════════════════════════════════════ -->
<!-- TAB: SETTINGS (Edit after install) -->
<!-- ═══════════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <h3 class="font-semibold text-gray-900 mb-1">Platform Settings</h3>
    <p class="text-sm text-gray-500 mb-6">Update your platform name and API keys.</p>
    <div class="space-y-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Platform Name</label>
        <input type="text" id="edit_app_name" value="<?= htmlspecialchars($currentSettings['APP_NAME'] ?? '') ?>"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">OpenAI API Key</label>
        <div class="relative">
          <input type="password" id="edit_openai" value="<?= htmlspecialchars($currentSettings['OPENAI_API_KEY'] ?? '') ?>"
            class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-12 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
          <button type="button" onclick="togglePass('edit_openai')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">HeyGen API Key</label>
        <div class="relative">
          <input type="password" id="edit_heygen" value="<?= htmlspecialchars($currentSettings['HEYGEN_API_KEY'] ?? '') ?>"
            class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-12 focus:ring-2 focus:ring-violet-500 focus:border-transparent outline-none text-gray-900">
          <button type="button" onclick="togglePass('edit_heygen')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          </button>
        </div>
      </div>
      <div id="settings-msg" class="hidden rounded-xl px-4 py-3 text-sm font-medium"></div>
      <button onclick="saveSettings()" class="w-full bg-violet-700 hover:bg-violet-800 text-white rounded-full py-3 font-semibold transition-colors">
        Save Settings
      </button>
    </div>
  </div>

  <div class="space-y-6">
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-1">Database Info</h3>
      <div class="space-y-2 text-sm mt-4">
        <?php foreach (['DB_HOST'=>'Host','DB_PORT'=>'Port','DB_NAME'=>'Database','DB_USERNAME'=>'Username'] as $k=>$l): ?>
        <div class="flex justify-between py-2 border-b border-gray-50">
          <span class="text-gray-500"><?= $l ?></span>
          <span class="font-medium text-gray-900"><?= htmlspecialchars($currentSettings[$k] ?? '-') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <button onclick="runCmd('test_db')" class="mt-4 w-full border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl py-2 text-sm font-medium transition-colors">Test Connection</button>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
      <h3 class="font-semibold text-gray-900 mb-4">Quick Actions</h3>
      <div class="space-y-2">
        <button onclick="runCmd('clear_cache');showToast('Cache cleared!','success')" class="w-full text-left flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl text-sm transition-colors">
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          Clear Cache
        </button>
        <a href="?tab=terminal" class="w-full text-left flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl text-sm transition-colors">
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          Open Terminal
        </a>
        <a href="?tab=security" class="w-full text-left flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl text-sm transition-colors">
          <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          Security Settings
        </a>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'security'): ?>
<!-- ═══════════════════════════════════════════════════ -->
<!-- TAB: SECURITY -->
<!-- ═══════════════════════════════════════════════════ -->
<div class="max-w-2xl space-y-6">
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <h3 class="font-semibold text-gray-900 mb-1">Setup Security</h3>
    <p class="text-sm text-gray-500 mb-6">Control access to this setup page.</p>
    <div class="space-y-4">
      <div class="p-4 bg-red-50 border border-red-200 rounded-xl">
        <div class="font-semibold text-red-700 mb-2 flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          Delete Setup Files
        </div>
        <p class="text-sm text-red-600 mb-4">Permanently deletes this setup wizard. You can still manage settings from the admin panel. This cannot be undone.</p>
        <button onclick="deleteSetup()" class="bg-red-600 hover:bg-red-700 text-white rounded-xl px-5 py-2.5 text-sm font-semibold transition-colors">Delete Setup Files</button>
      </div>

      <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl">
        <div class="font-semibold text-amber-700 mb-2 flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          Password Lock Setup
        </div>
        <p class="text-sm text-amber-700 mb-3">Protect setup with a password instead of deleting it.</p>
        <div class="flex gap-2">
          <input type="password" id="lock-pass-tab" placeholder="Choose a lock password"
            class="flex-1 border border-amber-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-amber-400 outline-none bg-white">
          <button onclick="lockSetupTab()" class="bg-amber-400 hover:bg-amber-500 text-gray-900 rounded-xl px-5 py-2.5 text-sm font-semibold transition-colors">Lock</button>
        </div>
        <?php if (file_exists(LOCK_FILE)): ?>
        <div class="mt-2 text-xs text-amber-600 flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          Setup is currently locked.
          <button onclick="unlockSetup()" class="underline ml-1">Remove lock</button>
        </div>
        <?php endif; ?>
      </div>

      <div id="security-msg" class="hidden rounded-xl px-4 py-3 text-sm font-medium"></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════ -->
<!-- TAB: TERMINAL (always available) -->
<!-- ═══════════════════════════════════════════════════ -->
<?php if ($tab === 'terminal'): ?>
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
  <!-- Quick Commands -->
  <div class="lg:col-span-1 space-y-2">
    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Quick Commands</div>
    <?php
    $cmds = [
      ['php_version','PHP Version','M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
      ['php_extensions','PHP Extensions','M4 6h16M4 10h16M4 14h16M4 18h16'],
      ['disk_space','Disk Space','M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4'],
      ['memory_info','Memory Info','M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18'],
      ['file_permissions','File Permissions','M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
      ['check_writable','Writable Dirs','M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'],
      ['clear_cache','Clear Cache','M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
      ['view_logs','View Logs','M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
      ['test_db','Test DB','M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4'],
      ['env_check','Check .env','M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
      ['installed_date','Install Info','M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ];
    foreach ($cmds as [$id, $label, $icon]): ?>
    <button onclick="runCmd('<?= $id ?>')"
      class="w-full text-left flex items-center gap-2.5 px-3 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white rounded-xl text-sm transition-colors terminal">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?= $icon ?>"/></svg>
      <?= $label ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- Terminal Output -->
  <div class="lg:col-span-3">
    <div class="bg-gray-950 rounded-2xl overflow-hidden border border-gray-800 shadow-2xl">
      <!-- Terminal title bar -->
      <div class="flex items-center gap-2 px-4 py-3 bg-gray-900 border-b border-gray-800">
        <div class="w-3 h-3 rounded-full bg-red-500"></div>
        <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
        <div class="w-3 h-3 rounded-full bg-green-500"></div>
        <span class="ml-3 text-xs text-gray-400 terminal">HireAI Terminal</span>
        <button onclick="clearTerminal()" class="ml-auto text-xs text-gray-500 hover:text-gray-300 transition-colors">Clear</button>
      </div>

      <!-- Output -->
      <div id="terminal-output" class="terminal text-sm p-5 h-96 overflow-y-auto space-y-1">
        <div class="text-green-400">Welcome to HireAI Terminal</div>
        <div class="text-gray-500">Select a command from the left panel or type below.</div>
        <div class="text-gray-500">──────────────────────────────────────</div>
      </div>

      <!-- Input -->
      <div class="border-t border-gray-800 flex items-center px-5 py-3 gap-3">
        <span class="text-green-400 terminal text-sm">$</span>
        <div class="text-gray-400 terminal text-sm cursor-blink flex-1">_</div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

</div><!-- /container -->

<!-- Toast -->
<div id="toast" class="fixed top-5 right-5 z-50 hidden">
  <div id="toast-inner" class="rounded-xl shadow-lg px-5 py-3 text-sm font-medium flex items-center gap-3 min-w-64"></div>
</div>

<!-- Delete Confirm Modal -->
<div id="delete-modal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full">
    <div class="text-center">
      <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
      </div>
      <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Setup Files?</h3>
      <p class="text-gray-500 text-sm mb-6">This will permanently delete the <code class="bg-gray-100 px-1 rounded">/setup</code> folder. This cannot be undone. You can manage settings from the admin panel at <code class="bg-gray-100 px-1 rounded">/super/settings</code>.</p>
      <div class="flex gap-3 justify-center">
        <button onclick="document.getElementById('delete-modal').classList.add('hidden')" class="border border-gray-300 text-gray-700 hover:bg-gray-50 rounded-full px-6 py-2.5 font-medium text-sm transition-colors">Cancel</button>
        <button onclick="confirmDelete()" class="bg-red-600 hover:bg-red-700 text-white rounded-full px-6 py-2.5 font-semibold text-sm transition-colors">Yes, Delete Everything</button>
      </div>
    </div>
  </div>
</div>

<script>
let currentStep = 0;

function nextStep(from) {
  if (!validateStep(from)) return;
  const next = from + 1;
  document.getElementById('step-' + from).classList.add('hidden');
  document.getElementById('step-' + next).classList.remove('hidden');
  document.getElementById('step-' + next).classList.add('fade-in');
  updateProgress(next);
  if (next === 3) updateSummary();
  currentStep = next;
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function prevStep(from) {
  const prev = from - 1;
  document.getElementById('step-' + from).classList.add('hidden');
  document.getElementById('step-' + prev).classList.remove('hidden');
  document.getElementById('step-' + prev).classList.add('fade-in');
  updateProgress(prev);
  currentStep = prev;
}

function validateStep(step) {
  const errEl = document.getElementById('step' + step + '-error');
  if (errEl) errEl.classList.add('hidden');

  if (step === 0) {
    const name = document.getElementById('app_name').value.trim();
    const email = document.getElementById('admin_email').value.trim();
    const pass = document.getElementById('admin_pass').value;
    const confirm = document.getElementById('admin_pass_confirm').value;
    if (!name || !email || !pass) {
      showStepError(0, 'Please fill all required fields.');
      return false;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showStepError(0, 'Please enter a valid email address.');
      return false;
    }
    if (pass.length < 8) {
      showStepError(0, 'Password must be at least 8 characters.');
      return false;
    }
    if (pass !== confirm) {
      showStepError(0, 'Passwords do not match.');
      return false;
    }
  }
  if (step === 1) {
    const dbName = document.getElementById('db_name').value.trim();
    const dbUser = document.getElementById('db_user').value.trim();
    if (!dbName || !dbUser) {
      showToast('Please enter database name and username.', 'error');
      return false;
    }
  }
  if (step === 2) {
    const key = document.getElementById('openai_key').value.trim();
    if (!key) {
      showToast('OpenAI API key is required.', 'error');
      return false;
    }
  }
  return true;
}

function showStepError(step, msg) {
  const el = document.getElementById('step' + step + '-error');
  if (el) { el.textContent = msg; el.classList.remove('hidden'); }
  else showToast(msg, 'error');
}

function updateProgress(active) {
  for (let i = 0; i <= 3; i++) {
    const circle = document.getElementById('step-circle-' + i);
    const num = document.getElementById('step-num-' + i);
    if (!circle) continue;
    circle.className = 'progress-step w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300 ';
    if (i < active) {
      circle.className += 'done';
      num.innerHTML = '✓';
    } else if (i === active) {
      circle.className += 'active';
      num.textContent = i + 1;
    } else {
      circle.className += 'pending';
      num.textContent = i + 1;
    }
    if (i < 3) {
      const line = document.getElementById('step-line-' + i);
      if (line) {
        line.className = 'progress-line h-0.5 flex-1 mx-3 transition-all duration-500 ';
        line.className += i < active ? 'done' : 'pending';
      }
    }
  }
}

function updateSummary() {
  document.getElementById('sum-app').textContent = document.getElementById('app_name').value;
  document.getElementById('sum-email').textContent = document.getElementById('admin_email').value;
  document.getElementById('sum-db').textContent = (document.getElementById('db_name').value || '-') + ' @ ' + (document.getElementById('db_host').value || 'localhost');
  const ok = document.getElementById('openai_key').value ? '✓ Configured' : '✗ Not set';
  const hok = document.getElementById('heygen_key').value ? '✓ Configured' : '○ Skipped (optional)';
  document.getElementById('sum-openai').textContent = ok;
  document.getElementById('sum-heygen').textContent = hok;
}

async function testDB() {
  const btn = event.target;
  const orig = btn.innerHTML;
  btn.innerHTML = '<svg class="w-4 h-4 spin inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Testing...';
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('db_host', document.getElementById('db_host').value);
    fd.append('db_port', document.getElementById('db_port').value);
    fd.append('db_name', document.getElementById('db_name').value);
    fd.append('db_user', document.getElementById('db_user').value);
    fd.append('db_pass', document.getElementById('db_pass').value);
    const r = await fetch('?action=test_db', {method:'POST', body: fd});
    const d = await r.json();
    const el = document.getElementById('db-test-result');
    el.className = 'mt-4 rounded-xl px-4 py-3 text-sm font-medium ' + (d.ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200');
    el.textContent = (d.ok ? '✓ ' : '✗ ') + d.msg;
    el.classList.remove('hidden');
  } catch(e) { showToast('Test failed: ' + e.message, 'error'); }
  btn.innerHTML = orig; btn.disabled = false;
}

async function testOpenAI() {
  const key = document.getElementById('openai_key').value.trim();
  if (!key) { showToast('Enter OpenAI key first', 'error'); return; }
  const btn = event.target; btn.disabled = true; btn.textContent = 'Validating...';
  try {
    const fd = new FormData(); fd.append('openai_key', key);
    const r = await fetch('?action=test_openai', {method:'POST', body: fd});
    const d = await r.json();
    const el = document.getElementById('openai-result');
    el.className = 'mt-2 text-sm font-medium rounded-lg px-3 py-2 ' + (d.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700');
    el.textContent = (d.ok ? '✓ ' : '✗ ') + d.msg;
    el.classList.remove('hidden');
  } catch(e) {}
  btn.disabled = false; btn.textContent = 'Validate Key';
}

async function testHeyGen() {
  const key = document.getElementById('heygen_key').value.trim();
  if (!key) { showToast('Enter HeyGen key first', 'error'); return; }
  const btn = event.target; btn.disabled = true; btn.textContent = 'Validating...';
  try {
    const fd = new FormData(); fd.append('heygen_key', key);
    const r = await fetch('?action=test_heygen', {method:'POST', body: fd});
    const d = await r.json();
    const el = document.getElementById('heygen-result');
    el.className = 'mt-2 text-sm font-medium rounded-lg px-3 py-2 ' + (d.ok ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700');
    el.textContent = (d.ok ? '✓ ' : '✗ ') + d.msg;
    el.classList.remove('hidden');
  } catch(e) {}
  btn.disabled = false; btn.textContent = 'Validate Key';
}

async function runInstall() {
  document.getElementById('pre-install').classList.add('hidden');
  document.getElementById('install-progress').classList.remove('hidden');

  const log = document.getElementById('install-log');
  function addLog(text, ok = true) {
    const div = document.createElement('div');
    div.className = ok ? 'log-ok' : 'log-err';
    div.textContent = (ok ? '✓ ' : '✗ ') + text;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
  }

  addLog('Starting installation...', true);

  try {
    const payload = {
      app_name: document.getElementById('app_name').value,
      admin_name: document.getElementById('admin_name').value,
      admin_email: document.getElementById('admin_email').value,
      admin_pass: document.getElementById('admin_pass').value,
      db_host: document.getElementById('db_host').value,
      db_port: document.getElementById('db_port').value,
      db_name: document.getElementById('db_name').value,
      db_user: document.getElementById('db_user').value,
      db_pass: document.getElementById('db_pass').value,
      openai_key: document.getElementById('openai_key').value,
      heygen_key: document.getElementById('heygen_key').value,
    };

    const r = await fetch('?action=install', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });
    const d = await r.json();

    if (d.steps) d.steps.forEach(s => addLog(s.step, s.ok));

    if (d.ok) {
      document.getElementById('install-spinner').classList.add('hidden');
      document.getElementById('install-done').classList.remove('hidden');
      document.getElementById('install-title').textContent = 'Installation Complete!';
      document.getElementById('install-subtitle').textContent = 'Your platform is ready.';
      setTimeout(() => {
        document.getElementById('install-progress').classList.add('hidden');
        document.getElementById('install-success').classList.remove('hidden');
      }, 1000);
    } else {
      addLog('Installation failed: ' + d.msg, false);
      document.getElementById('install-title').textContent = 'Installation Failed';
      document.getElementById('install-subtitle').textContent = 'Please check the errors above.';
    }
  } catch(e) {
    addLog('Fatal error: ' + e.message, false);
  }
}

function showSecurityOptions() {
  document.getElementById('security-options').classList.remove('hidden');
}

function deleteSetup() {
  document.getElementById('delete-modal').classList.remove('hidden');
}

async function confirmDelete() {
  document.getElementById('delete-modal').classList.add('hidden');
  try {
    const r = await fetch('?action=delete_setup', {method:'POST'});
    const d = await r.json();
    if (d.ok) {
      showToast('Setup files deleted. Redirecting...', 'success');
      setTimeout(() => { window.location.href = '/login'; }, 2000);
    } else {
      showToast('Error: ' + d.msg, 'error');
    }
  } catch(e) { showToast('Failed to delete files.', 'error'); }
}

async function lockSetup() {
  const pass = document.getElementById('lock-pass')?.value || '';
  if (!pass || pass.length < 6) { showToast('Password must be at least 6 characters', 'error'); return; }
  const fd = new FormData(); fd.append('lock_password', pass);
  const r = await fetch('?action=lock_setup', {method:'POST', body: fd});
  const d = await r.json();
  if (d.ok) showToast('Setup locked successfully!', 'success');
  else showToast('Error: ' + d.msg, 'error');
}

async function lockSetupTab() {
  const pass = document.getElementById('lock-pass-tab')?.value || '';
  if (!pass || pass.length < 6) { showToast('Password must be at least 6 characters', 'error'); return; }
  const fd = new FormData(); fd.append('lock_password', pass);
  const r = await fetch('?action=lock_setup', {method:'POST', body: fd});
  const d = await r.json();
  const el = document.getElementById('security-msg');
  el.className = 'rounded-xl px-4 py-3 text-sm font-medium ' + (d.ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200');
  el.textContent = d.ok ? '✓ ' + d.msg : '✗ ' + d.msg;
  el.classList.remove('hidden');
}

async function saveSettings() {
  const fd = new FormData();
  fd.append('APP_NAME', document.getElementById('edit_app_name').value);
  fd.append('OPENAI_API_KEY', document.getElementById('edit_openai').value);
  fd.append('HEYGEN_API_KEY', document.getElementById('edit_heygen').value);
  const r = await fetch('?action=save_settings', {method:'POST', body: fd});
  const d = await r.json();
  const el = document.getElementById('settings-msg');
  el.className = 'rounded-xl px-4 py-3 text-sm font-medium ' + (d.ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200');
  el.textContent = d.ok ? '✓ ' + d.msg : '✗ ' + d.msg;
  el.classList.remove('hidden');
  setTimeout(() => el.classList.add('hidden'), 4000);
}

async function runCmd(cmd) {
  const output = document.getElementById('terminal-output');
  const div = document.createElement('div');
  div.className = 'text-yellow-400';
  div.textContent = '$ ' + cmd.replace(/_/g, ' ');
  output.appendChild(div);

  const loading = document.createElement('div');
  loading.className = 'text-gray-500';
  loading.textContent = 'Running...';
  output.appendChild(loading);
  output.scrollTop = output.scrollHeight;

  try {
    const fd = new FormData();
    fd.append('cmd', cmd);
    const r = await fetch('?action=terminal', {method:'POST', body: fd});
    const d = await r.json();
    loading.remove();

    const result = document.createElement('pre');
    result.className = (d.ok ? 'text-green-300' : 'text-red-400') + ' whitespace-pre-wrap text-sm';
    result.textContent = d.output;
    output.appendChild(result);

    const sep = document.createElement('div');
    sep.className = 'text-gray-700';
    sep.textContent = '──────────────────────────────────────';
    output.appendChild(sep);
    output.scrollTop = output.scrollHeight;
  } catch(e) {
    loading.textContent = 'Error: ' + e.message;
    loading.className = 'text-red-400';
  }
}

function clearTerminal() {
  const output = document.getElementById('terminal-output');
  output.innerHTML = '<div class="text-green-400">Terminal cleared.</div>';
}

function togglePass(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}

let toastTimer;
function showToast(msg, type = 'info') {
  clearTimeout(toastTimer);
  const toast = document.getElementById('toast');
  const inner = document.getElementById('toast-inner');
  const colors = {success:'bg-emerald-600 text-white', error:'bg-red-600 text-white', info:'bg-gray-900 text-white'};
  inner.className = 'rounded-xl shadow-lg px-5 py-3 text-sm font-medium flex items-center gap-3 min-w-64 ' + (colors[type]||colors.info);
  inner.textContent = msg;
  toast.classList.remove('hidden');
  toastTimer = setTimeout(() => toast.classList.add('hidden'), 4000);
}
</script>
</body>
</html>
