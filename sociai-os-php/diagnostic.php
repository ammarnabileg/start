<?php
/**
 * SociAI OS - Diagnostic Tool
 * Upload this file to your server root and visit it to diagnose issues.
 * DELETE this file after fixing the problem!
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$checks = [];
$pass   = '✅';
$fail   = '❌';
$warn   = '⚠️';

// PHP Version
$phpVer = PHP_VERSION;
$phpOk  = version_compare($phpVer, '8.1.0', '>=');
$checks[] = [$phpOk ? $pass : $fail, 'PHP Version', $phpVer . ($phpOk ? '' : ' — Need 8.1+')];

// Required extensions
foreach (['pdo', 'pdo_mysql', 'openssl', 'curl', 'mbstring', 'fileinfo', 'json', 'session'] as $ext) {
    $ok = extension_loaded($ext);
    $checks[] = [$ok ? $pass : $fail, "Extension: $ext", $ok ? 'Loaded' : 'MISSING'];
}

// .env file
$envPath = __DIR__ . '/.env';
$envExists = file_exists($envPath);
$checks[] = [$envExists ? $pass : $fail, '.env file', $envExists ? 'Found' : 'MISSING — copy .env.example to .env'];

// config.php
$configPath = __DIR__ . '/config/config.php';
$configExists = file_exists($configPath);
$checks[] = [$configExists ? $pass : $fail, 'config/config.php', $configExists ? 'Found' : 'MISSING'];

// Load config if exists
if ($configExists) {
    try {
        require_once $configPath;
        $checks[] = [$pass, 'Config loaded', 'OK'];
    } catch (Throwable $e) {
        $checks[] = [$fail, 'Config load error', $e->getMessage()];
    }
}

// Database connection
if (defined('DB_HOST')) {
    try {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset='.DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $checks[] = [$pass, 'Database connection', 'Connected to ' . DB_NAME . ' on ' . DB_HOST];

        // Check tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $required = ['users', 'brands', 'content_pieces', 'platform_accounts', 'campaigns', 'scheduled_posts', 'agent_tasks', 'notifications'];
        $missing  = array_diff($required, $tables);
        if (empty($missing)) {
            $checks[] = [$pass, 'Database tables', count($tables) . ' tables found'];
        } else {
            $checks[] = [$fail, 'Database tables', 'Missing: ' . implode(', ', $missing) . ' — Run schema_no_createdb.sql'];
        }
    } catch (PDOException $e) {
        $checks[] = [$fail, 'Database connection', $e->getMessage()];
    }
} else {
    $checks[] = [$warn, 'Database', 'Config not loaded yet'];
}

// Directory permissions
$dirs = [
    'uploads/'         => __DIR__ . '/uploads',
    'cache/'           => __DIR__ . '/cache',
    'logs/'            => __DIR__ . '/logs',
    'uploads/strategy' => __DIR__ . '/uploads/strategy',
];
foreach ($dirs as $label => $path) {
    $exists   = is_dir($path);
    $writable = $exists && is_writable($path);
    if (!$exists) {
        $checks[] = [$warn, "Dir: $label", 'Missing — will try to create'];
        @mkdir($path, 0755, true);
    } elseif (!$writable) {
        $checks[] = [$fail, "Dir: $label", 'NOT writable — run: chmod 775 ' . $label];
    } else {
        $checks[] = [$pass, "Dir: $label", 'OK (writable)'];
    }
}

// mod_rewrite / .htaccess
$htaccess = __DIR__ . '/.htaccess';
$checks[] = [file_exists($htaccess) ? $pass : $fail, '.htaccess', file_exists($htaccess) ? 'Found' : 'MISSING'];

// index.php
$checks[] = [file_exists(__DIR__ . '/index.php') ? $pass : $fail, 'index.php', 'Front controller'];

// Curl to self
$selfUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
$ch = curl_init($selfUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$result = curl_exec($ch);
$code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);
if ($code >= 200 && $code < 500) {
    $checks[] = [$pass, 'HTTP self-check', "Got HTTP $code from $selfUrl"];
} else {
    $checks[] = [$warn, 'HTTP self-check', $err ?: "Got HTTP $code"];
}

// Count pass/fail
$failCount = count(array_filter($checks, fn($c) => $c[0] === $fail));
$warnCount = count(array_filter($checks, fn($c) => $c[0] === $warn));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SociAI OS — Diagnostic</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #0A0B1A; color: #e2e8f0; padding: 2rem; }
  h1 { color: #3B82F6; margin-bottom: 0.5rem; }
  .subtitle { color: #94a3b8; margin-bottom: 2rem; font-size: 0.9rem; }
  .summary { display: flex; gap: 1rem; margin-bottom: 2rem; }
  .badge { padding: 0.5rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; }
  .ok { background: rgba(16,185,129,0.2); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
  .err { background: rgba(239,68,68,0.2); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
  .wrn { background: rgba(245,158,11,0.2); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
  table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.03); border-radius: 12px; overflow: hidden; }
  th { text-align: left; padding: 0.75rem 1rem; background: rgba(255,255,255,0.05); color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
  td { padding: 0.75rem 1rem; border-top: 1px solid rgba(255,255,255,0.06); font-size: 0.875rem; }
  td:first-child { font-size: 1.1rem; width: 40px; }
  td:nth-child(2) { color: #e2e8f0; font-weight: 500; width: 220px; }
  td:nth-child(3) { color: #94a3b8; font-family: monospace; font-size: 0.8rem; }
  .delete-warning { margin-top: 2rem; padding: 1rem; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; color: #fca5a5; font-size: 0.875rem; }
</style>
</head>
<body>
<h1>🔍 SociAI OS — Server Diagnostic</h1>
<p class="subtitle">PHP <?= PHP_VERSION ?> | <?= PHP_OS ?> | <?= date('Y-m-d H:i:s') ?></p>

<div class="summary">
  <span class="badge <?= $failCount === 0 ? 'ok' : 'err' ?>">
    <?= $failCount === 0 ? '✅ All checks passed' : "❌ $failCount error(s) found" ?>
  </span>
  <?php if ($warnCount > 0): ?>
  <span class="badge wrn">⚠️ <?= $warnCount ?> warning(s)</span>
  <?php endif; ?>
</div>

<table>
  <tr><th></th><th>Check</th><th>Result</th></tr>
  <?php foreach ($checks as $c): ?>
  <tr><td><?= $c[0] ?></td><td><?= htmlspecialchars($c[1]) ?></td><td><?= htmlspecialchars($c[2]) ?></td></tr>
  <?php endforeach; ?>
</table>

<?php if ($failCount > 0): ?>
<div style="margin-top:2rem">
  <h3 style="color:#f87171;margin-bottom:1rem">🔧 How to fix:</h3>
  <?php foreach ($checks as $c): if ($c[0] !== '❌') continue; ?>
  <div style="padding:0.75rem 1rem;background:rgba(239,68,68,0.08);border-left:3px solid #ef4444;margin-bottom:0.5rem;border-radius:0 8px 8px 0">
    <strong style="color:#fca5a5"><?= htmlspecialchars($c[1]) ?></strong><br>
    <span style="color:#94a3b8;font-size:0.85rem"><?= htmlspecialchars($c[2]) ?></span>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="delete-warning">
  ⚠️ <strong>Security Warning:</strong> Delete <code>diagnostic.php</code> from your server after fixing the issues!
</div>
</body>
</html>
