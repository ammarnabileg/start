<?php

/**
 * Watad AI Interviewer — Web Installer
 * ------------------------------------------------------------------
 * One-file setup wizard. Open /install.php in the browser after `composer install`.
 * It will: check requirements → collect site/DB/AI/admin settings → write .env →
 * generate APP_KEY → migrate → seed roles/permissions/avatars/pipeline → create the
 * Super Admin account (full control) → lock itself.
 *
 * SECURITY: after a successful install a storage/installed.lock file is written and the
 * installer refuses to run again. Delete this file (public/install.php) once installed.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');

$root     = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';
$lockFile = $root.'/storage/installed.lock';
$envFile  = $root.'/.env';
$envSample = $root.'/.env.example';

$errors  = [];
$output  = '';
$done    = false;
$alreadyInstalled = file_exists($lockFile) && ! isset($_GET['force']);

/* ----------------------------- requirements ----------------------------- */
$requirements = [
    'PHP >= 8.3'            => version_compare(PHP_VERSION, '8.3.0', '>='),
    'PDO MySQL extension'   => extension_loaded('pdo_mysql'),
    'Mbstring extension'    => extension_loaded('mbstring'),
    'OpenSSL extension'     => extension_loaded('openssl'),
    'cURL extension'        => extension_loaded('curl'),
    'Composer dependencies' => file_exists($autoload),
    'storage/ writable'     => is_writable($root.'/storage'),
    '.env writable'         => (file_exists($envFile) && is_writable($envFile)) || is_writable($root),
];
$requirementsOk = ! in_array(false, $requirements, true);

/* ----------------------------- helpers ----------------------------- */
function env_set(string $content, string $key, ?string $value): string
{
    $value = (string) $value;
    $quoted = preg_match('/\s|#|"/', $value) ? '"'.str_replace('"', '\"', $value).'"' : $value;
    $line = $key.'='.$quoted;
    if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $content)) {
        return preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $content);
    }
    return rtrim($content)."\n".$line."\n";
}

/* ----------------------------- process ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $alreadyInstalled && $requirementsOk) {
    $f = fn (string $k, string $d = '') => trim((string) ($_POST[$k] ?? $d));

    $useRedis = isset($_POST['use_redis']);
    $loadDemo = isset($_POST['load_demo']);

    // Basic validation
    foreach (['app_name', 'app_url', 'db_database', 'db_username', 'admin_name', 'admin_email', 'admin_password'] as $req) {
        if ($f($req) === '') {
            $errors[] = "Field “{$req}” is required.";
        }
    }
    if ($f('admin_password') !== '' && strlen($f('admin_password')) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    }
    if (! filter_var($f('admin_email'), FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Admin email is invalid.';
    }

    if (empty($errors)) {
        try {
            // 1) Build .env from the sample and apply settings.
            $content = file_exists($envFile) ? file_get_contents($envFile)
                : (file_exists($envSample) ? file_get_contents($envSample) : "APP_NAME=Watad\n");

            $driver = $useRedis ? 'redis' : 'database';
            $map = [
                'APP_NAME'            => $f('app_name'),
                'APP_ENV'             => 'production',
                'APP_DEBUG'           => 'false',
                'APP_URL'             => $f('app_url'),
                'DB_CONNECTION'       => 'mysql',
                'DB_HOST'             => $f('db_host', '127.0.0.1'),
                'DB_PORT'             => $f('db_port', '3306'),
                'DB_DATABASE'         => $f('db_database'),
                'DB_USERNAME'         => $f('db_username'),
                'DB_PASSWORD'         => $f('db_password'),
                'CACHE_STORE'         => $driver,
                'QUEUE_CONNECTION'    => $driver,
                'SESSION_DRIVER'      => $driver,
                'BROADCAST_CONNECTION' => 'null',
                'FILESYSTEM_DISK'     => 'local',
                'REDIS_HOST'          => $f('redis_host', '127.0.0.1'),
                'REDIS_PORT'          => $f('redis_port', '6379'),
                'ANTHROPIC_API_KEY'   => $f('anthropic_api_key'),
                'WATAD_AI_PROVIDER'   => 'claude',
            ];
            foreach ($map as $k => $v) {
                $content = env_set($content, $k, $v);
            }
            file_put_contents($envFile, $content);

            // 2) Boot the framework with the fresh .env and run setup commands.
            require $autoload;
            /** @var \Illuminate\Foundation\Application $app */
            $app = require $root.'/bootstrap/app.php';
            $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();

            $run = function (string $cmd, array $params = []) use ($kernel, &$output) {
                $kernel->call($cmd, $params);
                $output .= '$ artisan '.$cmd."\n".$kernel->output()."\n";
            };

            $run('key:generate', ['--force' => true]);
            $run('config:clear');
            $run('migrate', ['--force' => true]);
            $run('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);
            $run('db:seed', ['--class' => 'Database\\Seeders\\AvatarSeeder', '--force' => true]);
            $run('db:seed', ['--class' => 'Database\\Seeders\\PipelineSeeder', '--force' => true]);
            if ($loadDemo) {
                $run('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
            }

            // 3) Create the Super Admin (full control over everything).
            $admin = \App\Models\User::updateOrCreate(
                ['email' => $f('admin_email')],
                [
                    'name'              => $f('admin_name'),
                    'password'          => \Illuminate\Support\Facades\Hash::make($f('admin_password')),
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ],
            );
            $superRole = \App\Models\Role::where('slug', 'super_admin')->first();
            if ($superRole) {
                $admin->roles()->syncWithoutDetaching([$superRole->id]);
            }

            // 4) Lock the installer.
            file_put_contents($lockFile, 'Installed at '.date('c'));
            $done = true;
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · Watad AI Interviewer</title>
<style>
    :root { --brand:#2563eb; --brand-dark:#1d4ed8; }
    * { box-sizing: border-box; font-family: Inter, system-ui, sans-serif; }
    body { margin:0; background:#f8fafc; color:#1e293b; }
    .wrap { max-width: 720px; margin: 40px auto; padding: 0 16px; }
    .brand { display:flex; align-items:center; gap:10px; margin-bottom:20px; }
    .logo { width:40px; height:40px; border-radius:12px; background:var(--brand); color:#fff; display:grid; place-items:center; font-weight:700; font-size:20px; }
    .card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; margin-bottom:20px; }
    h1 { font-size:22px; margin:0; } h2 { font-size:16px; margin:0 0 14px; }
    .muted { color:#64748b; font-size:14px; }
    label { display:block; font-size:13px; color:#475569; margin:12px 0 4px; }
    input[type=text], input[type=url], input[type=email], input[type=password], input[type=number] {
        width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; }
    input:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 3px rgba(37,99,235,.15); }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .btn { display:inline-flex; align-items:center; gap:8px; background:var(--brand); color:#fff; border:0;
        padding:11px 18px; border-radius:9px; font-size:14px; font-weight:600; cursor:pointer; }
    .btn:hover { background:var(--brand-dark); }
    .req { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #f1f5f9; font-size:14px; }
    .ok { color:#059669; } .bad { color:#dc2626; }
    .alert { border-radius:9px; padding:12px 14px; font-size:14px; margin-bottom:14px; }
    .alert-error { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
    .alert-ok { background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; }
    .check { display:flex; align-items:center; gap:8px; margin-top:12px; font-size:14px; color:#475569; }
    pre { background:#0f172a; color:#e2e8f0; padding:14px; border-radius:9px; font-size:12px; overflow:auto; max-height:260px; }
    a { color:var(--brand); }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand"><div class="logo">W</div><div><h1>Watad AI Interviewer</h1><div class="muted">System installer</div></div></div>

    <?php if ($alreadyInstalled): ?>
        <div class="card">
            <div class="alert alert-ok">✓ The system is already installed.</div>
            <p class="muted">For security, delete <code>public/install.php</code>. To re-run anyway, append <code>?force=1</code> to the URL.</p>
            <p><a href="<?= htmlspecialchars(($_POST['app_url'] ?? '/').'/login') ?>">→ Go to login</a></p>
        </div>
    <?php elseif ($done): ?>
        <div class="card">
            <div class="alert alert-ok">🎉 Installation complete! The Super Admin account was created with full control.</div>
            <p class="muted"><strong>Important:</strong> delete <code>public/install.php</code> now, and start the queue worker
               (<code>php artisan queue:work</code>) for AI analysis jobs.</p>
            <p><a href="login">→ Go to login</a></p>
            <details><summary class="muted">Setup output</summary><pre><?= htmlspecialchars($output) ?></pre></details>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>1 · Requirements</h2>
            <?php foreach ($requirements as $name => $pass): ?>
                <div class="req"><span><?= htmlspecialchars($name) ?></span><span class="<?= $pass ? 'ok' : 'bad' ?>"><?= $pass ? '✓ OK' : '✗ Missing' ?></span></div>
            <?php endforeach; ?>
            <?php if (! $requirementsOk): ?>
                <p class="muted" style="margin-top:12px">Resolve the items above (e.g. run <code>composer install</code>, fix permissions) then reload.</p>
            <?php endif; ?>
        </div>

        <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

        <form method="POST" class="card">
            <h2>2 · Site & database</h2>
            <div class="grid">
                <div><label>Site name</label><input type="text" name="app_name" value="<?= htmlspecialchars($_POST['app_name'] ?? 'Watad AI Interviewer') ?>"></div>
                <div><label>Site URL</label><input type="url" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? 'http://localhost:8080') ?>"></div>
                <div><label>DB host</label><input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') ?>"></div>
                <div><label>DB port</label><input type="number" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>"></div>
                <div><label>DB name</label><input type="text" name="db_database" value="<?= htmlspecialchars($_POST['db_database'] ?? 'watad') ?>"></div>
                <div><label>DB user</label><input type="text" name="db_username" value="<?= htmlspecialchars($_POST['db_username'] ?? 'root') ?>"></div>
                <div><label>DB password</label><input type="password" name="db_password" value=""></div>
                <div><label>Anthropic API key (Claude)</label><input type="text" name="anthropic_api_key" value="<?= htmlspecialchars($_POST['anthropic_api_key'] ?? '') ?>" placeholder="sk-ant-..."></div>
            </div>
            <label class="check"><input type="checkbox" name="use_redis"> Use Redis for cache/queue/session (otherwise database)</label>
            <div class="grid">
                <div><label>Redis host</label><input type="text" name="redis_host" value="127.0.0.1"></div>
                <div><label>Redis port</label><input type="number" name="redis_port" value="6379"></div>
            </div>

            <h2 style="margin-top:22px">3 · Administrator account (full control)</h2>
            <div class="grid">
                <div><label>Full name</label><input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>"></div>
                <div><label>Email</label><input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"></div>
                <div><label>Password (min 8)</label><input type="password" name="admin_password" value=""></div>
            </div>
            <label class="check"><input type="checkbox" name="load_demo"> Load demo data (sample job, template & interview link)</label>

            <div style="margin-top:20px">
                <button class="btn" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>Install Watad →</button>
            </div>
        </form>
    <?php endif; ?>

    <p class="muted" style="text-align:center">The Super Admin gets every permission (View / Create / Edit / Delete for all resources). Manage other roles from <strong>Roles &amp; Permissions</strong> after install.</p>
</div>
</body>
</html>
