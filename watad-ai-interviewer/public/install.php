<?php

/**
 * Watad AI Interviewer — Web Installer & Maintenance
 * ------------------------------------------------------------------
 * The complete setup runs from this page:
 *   • requirements check
 *   • database: SQLite (create fresh OR upload a .sqlite file) or MySQL (migrate fresh OR import a .sql dump)
 *   • ALL API subscriptions & integrations (Claude/OpenAI, email/SMTP, S3 storage, video avatar,
 *     WhatsApp, Google Sheets, Reverb real-time) — entered here, written to .env
 *   • writes .env, generates APP_KEY, migrates + seeds, creates the Super Admin
 *   • after install it self-locks (storage/installed.lock)
 *   • a guarded RESET button wipes ALL data and returns to a fresh install page
 *
 * SECURITY: delete public/install.php after go-live, or keep it only on trusted environments —
 * the RESET action is destructive.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');
set_time_limit(0);

$root      = dirname(__DIR__);
$autoload  = $root.'/vendor/autoload.php';
$lockFile  = $root.'/storage/installed.lock';
$envFile   = $root.'/.env';
$envSample = $root.'/.env.example';
$sqlitePath = $root.'/database/database.sqlite';

$errors = [];
$notices = [];
$output = '';
$done = false;
$resetDone = false;
$action = $_POST['action'] ?? null;
$alreadyInstalled = file_exists($lockFile) && ! isset($_GET['force']);

/* ----------------------------- requirements ----------------------------- */
$requirements = [
    'PHP >= 8.3'            => version_compare(PHP_VERSION, '8.3.0', '>='),
    'PDO extension'         => extension_loaded('pdo'),
    'PDO MySQL'             => extension_loaded('pdo_mysql'),
    'PDO SQLite'            => extension_loaded('pdo_sqlite'),
    'Mbstring'              => extension_loaded('mbstring'),
    'OpenSSL'               => extension_loaded('openssl'),
    'cURL'                  => extension_loaded('curl'),
    'Composer dependencies' => file_exists($autoload),
    'storage/ writable'     => is_writable($root.'/storage'),
    '.env writable'         => (file_exists($envFile) && is_writable($envFile)) || is_writable($root),
    'database/ writable'    => is_writable($root.'/database'),
];
$requirementsOk = ! in_array(false, $requirements, true);

// Re-populate fields on validation error (so typed API keys are not lost).
$old = fn (string $k, string $d = '') => htmlspecialchars((string) ($_POST[$k] ?? $d), ENT_QUOTES);
$sel = fn (string $k, string $v, string $d = '') => (($_POST[$k] ?? $d) === $v) ? 'selected' : '';
$chk = fn (string $k) => isset($_POST[$k]) ? 'checked' : '';

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

function boot_kernel(string $root)
{
    require_once $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    return $kernel;
}

function import_sql_dump(string $sql): void
{
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    // Strip comment lines, then run statements split on semicolons at line ends.
    $sql = preg_replace('/^\s*(--|#).*$/m', '', $sql);
    foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]/', $sql))) as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

/* ----------------------------- RESET (wipe everything) ----------------------------- */
if ($action === 'reset' && file_exists($lockFile)) {
    if (($_POST['confirm'] ?? '') !== 'DELETE') {
        $errors[] = 'To reset, type DELETE in the confirmation box.';
    } else {
        try {
            if (file_exists($autoload) && file_exists($envFile)) {
                $kernel = boot_kernel($root);
                $kernel->call('db:wipe', ['--force' => true]);
                $output .= $kernel->output();
            }
            @unlink($lockFile);
            // For SQLite, also blank the file so the next install starts clean.
            if (is_file($sqlitePath)) {
                @file_put_contents($sqlitePath, '');
            }
            $resetDone = true;
            $alreadyInstalled = false;
            $notices[] = 'All data wiped. You can install again below.';
        } catch (\Throwable $e) {
            $errors[] = 'Reset failed: '.$e->getMessage();
        }
    }
}

/* ----------------------------- INSTALL ----------------------------- */
if ($action === 'install' && ! $alreadyInstalled && $requirementsOk) {
    $f = fn (string $k, string $d = '') => trim((string) ($_POST[$k] ?? $d));

    $driver   = $f('db_driver', 'sqlite');           // sqlite | mysql
    $dbSetup  = $f('db_setup', 'fresh');             // fresh | import
    $useRedis = isset($_POST['use_redis']);
    $loadDemo = isset($_POST['load_demo']);

    foreach (['app_name', 'app_url', 'admin_name', 'admin_email', 'admin_password'] as $req) {
        if ($f($req) === '') {
            $errors[] = "Field “{$req}” is required.";
        }
    }
    if ($driver === 'mysql') {
        foreach (['db_database', 'db_username'] as $req) {
            if ($f($req) === '') {
                $errors[] = "MySQL field “{$req}” is required.";
            }
        }
    }
    if (strlen($f('admin_password')) < 8) {
        $errors[] = 'Admin password must be at least 8 characters.';
    }
    if (! filter_var($f('admin_email'), FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Admin email is invalid.';
    }

    if (empty($errors)) {
        try {
            // 1) Handle an uploaded SQLite database file (import).
            if ($driver === 'sqlite') {
                if ($dbSetup === 'import' && ! empty($_FILES['sqlite_file']['tmp_name'])) {
                    move_uploaded_file($_FILES['sqlite_file']['tmp_name'], $sqlitePath);
                } elseif (! is_file($sqlitePath)) {
                    file_put_contents($sqlitePath, '');
                }
            }

            // 2) Write .env.
            $content = file_exists($envSample) ? file_get_contents($envSample) : "APP_NAME=Watad\n";
            $cacheDriver = $useRedis ? 'redis' : 'database';

            $storageDisk    = $f('filesystem_disk', 'local');   // local | s3
            $aiProvider     = $f('ai_provider', 'claude');       // claude | openai
            $videoProvider  = $f('video_provider', 'none');      // none | tavus | heygen
            $enableRealtime = isset($_POST['enable_reverb']);
            $sheetsEnabled  = isset($_POST['sheets_enabled']);

            $map = [
                'APP_NAME'  => $f('app_name'), 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
                'APP_URL'   => $f('app_url'),
                'DB_CONNECTION' => $driver,
                'CACHE_STORE' => $cacheDriver, 'QUEUE_CONNECTION' => $cacheDriver, 'SESSION_DRIVER' => $cacheDriver,
                'BROADCAST_CONNECTION' => $enableRealtime ? 'reverb' : 'null',
                'FILESYSTEM_DISK' => $storageDisk,
                'REDIS_HOST' => $f('redis_host', '127.0.0.1'), 'REDIS_PORT' => $f('redis_port', '6379'),
                'WATAD_AI_PROVIDER'    => $aiProvider,
                'WATAD_VIDEO_PROVIDER' => $videoProvider,
                'WATAD_SHEETS_ENABLED' => $sheetsEnabled ? 'true' : 'false',
            ];
            if ($driver === 'sqlite') {
                $map['DB_DATABASE'] = $sqlitePath;
            } else {
                $map += [
                    'DB_HOST' => $f('db_host', '127.0.0.1'), 'DB_PORT' => $f('db_port', '3306'),
                    'DB_DATABASE' => $f('db_database'), 'DB_USERNAME' => $f('db_username'), 'DB_PASSWORD' => $f('db_password'),
                ];
            }

            // All API subscriptions & integrations — only the keys actually filled are persisted,
            // so blanks never clobber sane .env.example defaults.
            $optional = [
                // AI providers
                'ANTHROPIC_API_KEY'            => $f('anthropic_api_key'),
                'OPENAI_API_KEY'               => $f('openai_api_key'),
                'WATAD_AI_CONVERSATION_MODEL'  => $f('ai_conversation_model'),
                'WATAD_AI_ANALYSIS_MODEL'      => $f('ai_analysis_model'),
                // Email (SMTP)
                'MAIL_MAILER'        => $f('mail_mailer'),
                'MAIL_HOST'          => $f('mail_host'),
                'MAIL_PORT'          => $f('mail_port'),
                'MAIL_USERNAME'      => $f('mail_username'),
                'MAIL_PASSWORD'      => $f('mail_password'),
                'MAIL_ENCRYPTION'    => $f('mail_encryption'),
                'MAIL_FROM_ADDRESS'  => $f('mail_from_address'),
                'MAIL_FROM_NAME'     => $f('mail_from_name'),
                // Storage (S3 / S3-compatible)
                'AWS_ACCESS_KEY_ID'     => $f('aws_key'),
                'AWS_SECRET_ACCESS_KEY' => $f('aws_secret'),
                'AWS_DEFAULT_REGION'    => $f('aws_region'),
                'AWS_BUCKET'            => $f('aws_bucket'),
                'AWS_ENDPOINT'          => $f('aws_endpoint'),
                // Video avatar
                'TAVUS_API_KEY'      => $f('tavus_api_key'),
                'HEYGEN_API_KEY'     => $f('heygen_api_key'),
                'LIVEKIT_URL'        => $f('livekit_url'),
                'LIVEKIT_API_KEY'    => $f('livekit_key'),
                'LIVEKIT_API_SECRET' => $f('livekit_secret'),
                // WhatsApp
                'WHATSAPP_TOKEN'     => $f('whatsapp_token'),
                'WHATSAPP_PHONE_ID'  => $f('whatsapp_phone_id'),
                // Google Sheets
                'WATAD_SHEETS_SPREADSHEET_ID'    => $f('sheets_spreadsheet_id'),
                'WATAD_SHEETS_TAB'               => $f('sheets_tab'),
                'GOOGLE_APPLICATION_CREDENTIALS' => $f('google_credentials'),
                // Real-time (Reverb)
                'REVERB_APP_ID'      => $f('reverb_app_id'),
                'REVERB_APP_KEY'     => $f('reverb_app_key'),
                'REVERB_APP_SECRET'  => $f('reverb_app_secret'),
                'REVERB_HOST'        => $f('reverb_host'),
                'REVERB_PORT'        => $f('reverb_port'),
            ];
            foreach ($optional as $k => $v) {
                if ($v !== '') {
                    $map[$k] = $v;
                }
            }

            // SQLite with cache/session on "database" needs tables; if importing a foreign DB, fall back to file/array.
            if ($dbSetup === 'import') {
                $content = env_set($content, 'CACHE_STORE', 'file');
                $content = env_set($content, 'SESSION_DRIVER', 'file');
                $content = env_set($content, 'QUEUE_CONNECTION', 'sync');
            }
            foreach ($map as $k => $v) {
                $content = env_set($content, $k, $v);
            }
            file_put_contents($envFile, $content);

            // 3) Boot framework with the new .env.
            $kernel = boot_kernel($root);
            $run = function (string $cmd, array $params = []) use ($kernel, &$output) {
                $kernel->call($cmd, $params);
                $output .= '$ artisan '.$cmd."\n".$kernel->output()."\n";
            };

            if (empty(getenv('APP_KEY')) && ! preg_match('/^APP_KEY=base64:/m', $content)) {
                $run('key:generate', ['--force' => true]);
            }

            // 4) Database initialization.
            if ($dbSetup === 'import' && $driver === 'mysql' && ! empty($_FILES['sql_dump']['tmp_name'])) {
                import_sql_dump((string) file_get_contents($_FILES['sql_dump']['tmp_name']));
                $output .= "Imported SQL dump.\n";
                // Ensure schema is complete even after an import.
                $run('migrate', ['--force' => true]);
            } elseif ($dbSetup === 'import' && $driver === 'sqlite') {
                $run('migrate', ['--force' => true]); // top up any missing tables in the uploaded DB
            } else {
                $run('migrate', ['--force' => true]);
                $run('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);
                $run('db:seed', ['--class' => 'Database\\Seeders\\AvatarSeeder', '--force' => true]);
                $run('db:seed', ['--class' => 'Database\\Seeders\\PipelineSeeder', '--force' => true]);
                if ($loadDemo) {
                    $run('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);
                }
            }

            // 5) Super Admin (full control).
            if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
                \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);
            }
            $admin = \App\Models\User::updateOrCreate(
                ['email' => $f('admin_email')],
                ['name' => $f('admin_name'), 'password' => \Illuminate\Support\Facades\Hash::make($f('admin_password')),
                 'is_active' => true, 'email_verified_at' => now()],
            );
            if ($role = \App\Models\Role::where('slug', 'super_admin')->first()) {
                $admin->roles()->syncWithoutDetaching([$role->id]);
            }

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
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · Watad AI Interviewer</title>
<style>
    :root { --brand:#2563eb; --brand-dark:#1d4ed8; }
    * { box-sizing:border-box; font-family:Inter,system-ui,sans-serif; }
    body { margin:0; background:#f8fafc; color:#1e293b; }
    .wrap { max-width:760px; margin:40px auto; padding:0 16px; }
    .brand { display:flex; align-items:center; gap:10px; margin-bottom:20px; }
    .logo { width:40px;height:40px;border-radius:12px;background:var(--brand);color:#fff;display:grid;place-items:center;font-weight:700;font-size:20px; }
    .card { background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;margin-bottom:20px; }
    h1 { font-size:22px;margin:0; } h2 { font-size:16px;margin:0 0 14px; }
    .muted { color:#64748b;font-size:14px; }
    label { display:block;font-size:13px;color:#475569;margin:12px 0 4px; }
    input[type=text],input[type=url],input[type=email],input[type=password],input[type=number],input[type=file],select {
        width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff; }
    input:focus,select:focus { outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(37,99,235,.15); }
    .grid { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
    .btn { display:inline-flex;align-items:center;gap:8px;background:var(--brand);color:#fff;border:0;padding:11px 18px;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer; }
    .btn:hover { background:var(--brand-dark); }
    .btn-danger { background:#dc2626; } .btn-danger:hover { background:#b91c1c; }
    .req { display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #f1f5f9;font-size:14px; }
    .ok { color:#059669; } .bad { color:#dc2626; }
    .alert { border-radius:9px;padding:12px 14px;font-size:14px;margin-bottom:14px; }
    .alert-error { background:#fef2f2;color:#b91c1c;border:1px solid #fecaca; }
    .alert-ok { background:#ecfdf5;color:#047857;border:1px solid #a7f3d0; }
    .alert-info { background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe; }
    .check { display:flex;align-items:center;gap:8px;margin-top:12px;font-size:14px;color:#475569; }
    pre { background:#0f172a;color:#e2e8f0;padding:14px;border-radius:9px;font-size:12px;overflow:auto;max-height:240px; }
    a { color:var(--brand); }
    .seg { display:flex;gap:8px;flex-wrap:wrap;margin-top:6px; }
    .seg label { display:flex;align-items:center;gap:6px;border:1px solid #cbd5e1;border-radius:8px;padding:8px 12px;margin:0;cursor:pointer; }
    details { border:1px solid #e2e8f0;border-radius:9px;padding:8px 14px;margin-top:10px;background:#fafbfc; }
    details[open] { background:#fff;padding-bottom:14px; }
    summary { cursor:pointer;font-weight:600;font-size:14px;color:#334155;user-select:none;padding:4px 0; }
    summary::marker { color:var(--brand); }
</style>
</head>
<body>
<div class="wrap">
    <div class="brand"><div class="logo">W</div><div><h1>Watad AI Interviewer</h1><div class="muted">System installer & maintenance</div></div></div>

    <?php foreach ($notices as $n): ?><div class="alert alert-info"><?= htmlspecialchars($n) ?></div><?php endforeach; ?>

    <?php if ($alreadyInstalled): ?>
        <div class="card">
            <div class="alert alert-ok">✓ The system is installed.</div>
            <p><a href="login">→ Go to login</a> &nbsp;·&nbsp; Delete <code>public/install.php</code> for production safety.</p>
        </div>
        <div class="card">
            <h2 style="color:#b91c1c">⚠ Reset / Reinstall</h2>
            <p class="muted">This <strong>permanently deletes ALL data</strong> (drops every table) and returns to a fresh install page.</p>
            <form method="POST" onsubmit="return confirm('This will DELETE ALL DATA. Continue?');">
                <input type="hidden" name="action" value="reset">
                <label>Type <code>DELETE</code> to confirm</label>
                <input type="text" name="confirm" placeholder="DELETE" autocomplete="off">
                <div style="margin-top:14px"><button class="btn btn-danger" type="submit">Wipe everything & reinstall</button></div>
            </form>
            <?php foreach ($errors as $e): ?><div class="alert alert-error" style="margin-top:12px"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
    <?php elseif ($done): ?>
        <div class="card">
            <div class="alert alert-ok">🎉 Installation complete! Your Super Admin account is ready (full control).</div>
            <p class="muted">Next: start the queue worker (<code>php artisan queue:work</code>) for AI analysis jobs, and delete <code>public/install.php</code>.</p>
            <p><a href="login">→ Go to login</a></p>
            <details><summary class="muted">Setup output</summary><pre><?= htmlspecialchars($output) ?></pre></details>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>1 · Requirements</h2>
            <?php foreach ($requirements as $name => $pass): ?>
                <div class="req"><span><?= htmlspecialchars($name) ?></span><span class="<?= $pass ? 'ok' : 'bad' ?>"><?= $pass ? '✓ OK' : '✗ Missing' ?></span></div>
            <?php endforeach; ?>
            <?php if (! $requirementsOk): ?><p class="muted" style="margin-top:12px">Resolve the items above (e.g. <code>composer install</code>, fix permissions) then reload.</p><?php endif; ?>
        </div>

        <?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

        <form method="POST" enctype="multipart/form-data" class="card"
              onsubmit="this.querySelector('button[type=submit]').innerText='Installing…';">
            <input type="hidden" name="action" value="install">

            <h2>2 · Site</h2>
            <div class="grid">
                <div><label>Site name</label><input type="text" name="app_name" value="<?= $old('app_name', 'Watad AI Interviewer') ?>"></div>
                <div><label>Site URL</label><input type="url" name="app_url" value="<?= $old('app_url', 'http://localhost:8000') ?>"></div>
            </div>

            <h2 style="margin-top:22px">3 · Database</h2>
            <div id="db" data-driver="sqlite">
                <label>Database engine</label>
                <div class="seg">
                    <label><input type="radio" name="db_driver" value="sqlite" checked onclick="dbDriver('sqlite')"> SQLite (simplest — no server)</label>
                    <label><input type="radio" name="db_driver" value="mysql" onclick="dbDriver('mysql')"> MySQL</label>
                </div>

                <label style="margin-top:14px">Initialization</label>
                <div class="seg">
                    <label><input type="radio" name="db_setup" value="fresh" checked onclick="dbSetup('fresh')"> Fresh install (create tables + seed)</label>
                    <label><input type="radio" name="db_setup" value="import" onclick="dbSetup('import')"> Import existing database</label>
                </div>

                <div id="mysql-fields" style="display:none">
                    <div class="grid">
                        <div><label>DB host</label><input type="text" name="db_host" value="127.0.0.1"></div>
                        <div><label>DB port</label><input type="number" name="db_port" value="3306"></div>
                        <div><label>DB name</label><input type="text" name="db_database" value="watad"></div>
                        <div><label>DB user</label><input type="text" name="db_username" value="root"></div>
                        <div><label>DB password</label><input type="password" name="db_password"></div>
                    </div>
                    <div id="sql-dump" style="display:none"><label>Upload .sql dump (optional)</label><input type="file" name="sql_dump" accept=".sql"></div>
                </div>

                <div id="sqlite-upload" style="display:none"><label>Upload SQLite database file (.sqlite)</label><input type="file" name="sqlite_file" accept=".sqlite,.db,.sqlite3"></div>

                <label class="check"><input type="checkbox" name="use_redis"> Use Redis for cache/queue/session (fresh installs)</label>
                <label class="check"><input type="checkbox" name="load_demo"> Load demo data (sample job + interview link)</label>
            </div>

            <h2 style="margin-top:22px">4 · Administrator (full control)</h2>
            <div class="grid">
                <div><label>Full name</label><input type="text" name="admin_name" value="<?= $old('admin_name') ?>"></div>
                <div><label>Email</label><input type="email" name="admin_email" value="<?= $old('admin_email') ?>"></div>
                <div><label>Password (min 8)</label><input type="password" name="admin_password"></div>
            </div>

            <h2 style="margin-top:22px">5 · API subscriptions &amp; integrations
                <span class="muted" style="font-weight:400">— enter everything you use; all optional</span></h2>

            <details open>
                <summary>🤖 AI providers (Claude / OpenAI)</summary>
                <label>Primary AI provider</label>
                <select name="ai_provider">
                    <option value="claude" <?= $sel('ai_provider', 'claude', 'claude') ?>>Claude — Anthropic (recommended)</option>
                    <option value="openai" <?= $sel('ai_provider', 'openai') ?>>OpenAI</option>
                </select>
                <label>Anthropic API key (Claude)</label>
                <input type="text" name="anthropic_api_key" placeholder="sk-ant-..." value="<?= $old('anthropic_api_key') ?>">
                <label>OpenAI API key (alternative provider)</label>
                <input type="text" name="openai_api_key" placeholder="sk-..." value="<?= $old('openai_api_key') ?>">
                <div class="grid">
                    <div><label>Conversation model</label><input type="text" name="ai_conversation_model" placeholder="claude-sonnet-4-6" value="<?= $old('ai_conversation_model') ?>"></div>
                    <div><label>Analysis model</label><input type="text" name="ai_analysis_model" placeholder="claude-opus-4-8" value="<?= $old('ai_analysis_model') ?>"></div>
                </div>
            </details>

            <details>
                <summary>✉️ Email (SMTP — invitations, offers, notifications)</summary>
                <div class="grid">
                    <div><label>Mailer</label><input type="text" name="mail_mailer" placeholder="smtp" value="<?= $old('mail_mailer') ?>"></div>
                    <div><label>Host</label><input type="text" name="mail_host" placeholder="smtp.gmail.com" value="<?= $old('mail_host') ?>"></div>
                    <div><label>Port</label><input type="number" name="mail_port" placeholder="587" value="<?= $old('mail_port') ?>"></div>
                    <div><label>Encryption</label><input type="text" name="mail_encryption" placeholder="tls" value="<?= $old('mail_encryption') ?>"></div>
                    <div><label>Username</label><input type="text" name="mail_username" value="<?= $old('mail_username') ?>"></div>
                    <div><label>Password</label><input type="password" name="mail_password"></div>
                    <div><label>From address</label><input type="email" name="mail_from_address" placeholder="hr@watad.com" value="<?= $old('mail_from_address') ?>"></div>
                    <div><label>From name</label><input type="text" name="mail_from_name" placeholder="Watad HR" value="<?= $old('mail_from_name') ?>"></div>
                </div>
            </details>

            <details>
                <summary>🗄️ File storage (CVs, reports — Amazon S3 / compatible)</summary>
                <label>Storage disk</label>
                <select name="filesystem_disk">
                    <option value="local" <?= $sel('filesystem_disk', 'local', 'local') ?>>Local disk (default)</option>
                    <option value="s3" <?= $sel('filesystem_disk', 's3') ?>>Amazon S3 / S3-compatible</option>
                </select>
                <div class="grid">
                    <div><label>Access key ID</label><input type="text" name="aws_key" value="<?= $old('aws_key') ?>"></div>
                    <div><label>Secret access key</label><input type="password" name="aws_secret"></div>
                    <div><label>Region</label><input type="text" name="aws_region" placeholder="us-east-1" value="<?= $old('aws_region') ?>"></div>
                    <div><label>Bucket</label><input type="text" name="aws_bucket" value="<?= $old('aws_bucket') ?>"></div>
                    <div style="grid-column:1/-1"><label>Endpoint (S3-compatible only, optional)</label><input type="url" name="aws_endpoint" value="<?= $old('aws_endpoint') ?>"></div>
                </div>
            </details>

            <details>
                <summary>🎥 Video avatar (live video interviews)</summary>
                <label>Video provider</label>
                <select name="video_provider">
                    <option value="none" <?= $sel('video_provider', 'none', 'none') ?>>None — text / voice only</option>
                    <option value="tavus" <?= $sel('video_provider', 'tavus') ?>>Tavus</option>
                    <option value="heygen" <?= $sel('video_provider', 'heygen') ?>>HeyGen</option>
                </select>
                <div class="grid">
                    <div><label>Tavus API key</label><input type="text" name="tavus_api_key" value="<?= $old('tavus_api_key') ?>"></div>
                    <div><label>HeyGen API key</label><input type="text" name="heygen_api_key" value="<?= $old('heygen_api_key') ?>"></div>
                    <div><label>LiveKit URL</label><input type="url" name="livekit_url" value="<?= $old('livekit_url') ?>"></div>
                    <div><label>LiveKit API key</label><input type="text" name="livekit_key" value="<?= $old('livekit_key') ?>"></div>
                    <div><label>LiveKit API secret</label><input type="password" name="livekit_secret"></div>
                </div>
            </details>

            <details>
                <summary>💬 WhatsApp (candidate notifications)</summary>
                <div class="grid">
                    <div><label>WhatsApp token</label><input type="text" name="whatsapp_token" value="<?= $old('whatsapp_token') ?>"></div>
                    <div><label>Phone number ID</label><input type="text" name="whatsapp_phone_id" value="<?= $old('whatsapp_phone_id') ?>"></div>
                </div>
            </details>

            <details>
                <summary>📊 Google Sheets (export candidate results)</summary>
                <label class="check"><input type="checkbox" name="sheets_enabled" <?= $chk('sheets_enabled') ?>> Push results to Google Sheets</label>
                <div class="grid">
                    <div><label>Spreadsheet ID</label><input type="text" name="sheets_spreadsheet_id" value="<?= $old('sheets_spreadsheet_id') ?>"></div>
                    <div><label>Tab name</label><input type="text" name="sheets_tab" placeholder="Candidates" value="<?= $old('sheets_tab') ?>"></div>
                    <div style="grid-column:1/-1"><label>Service-account credentials path (JSON)</label><input type="text" name="google_credentials" placeholder="/path/to/credentials.json" value="<?= $old('google_credentials') ?>"></div>
                </div>
            </details>

            <details>
                <summary>⚡ Real-time streaming (WebSockets / Reverb)</summary>
                <label class="check"><input type="checkbox" name="enable_reverb" <?= $chk('enable_reverb') ?>> Enable live interview streaming (Reverb)</label>
                <div class="grid">
                    <div><label>App ID</label><input type="text" name="reverb_app_id" placeholder="watad" value="<?= $old('reverb_app_id') ?>"></div>
                    <div><label>App key</label><input type="text" name="reverb_app_key" value="<?= $old('reverb_app_key') ?>"></div>
                    <div><label>App secret</label><input type="password" name="reverb_app_secret"></div>
                    <div><label>Host</label><input type="text" name="reverb_host" placeholder="localhost" value="<?= $old('reverb_host') ?>"></div>
                    <div><label>Port</label><input type="number" name="reverb_port" placeholder="8080" value="<?= $old('reverb_port') ?>"></div>
                </div>
            </details>

            <div style="margin-top:20px"><button class="btn" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>Install Watad →</button></div>
        </form>
    <?php endif; ?>

    <p class="muted" style="text-align:center">The Super Admin gets every permission. Other roles are managed from <strong>Roles &amp; Permissions</strong> after install.</p>
</div>
<script>
    function dbDriver(d){ document.getElementById('mysql-fields').style.display = d==='mysql'?'block':'none';
        document.getElementById('sqlite-upload').style.display = (d==='sqlite' && current_setup==='import')?'block':'none';
        document.getElementById('sql-dump').style.display = (d==='mysql' && current_setup==='import')?'block':'none'; }
    var current_setup='fresh';
    function dbSetup(s){ current_setup=s;
        var d = document.querySelector('input[name=db_driver]:checked').value; dbDriver(d); }
</script>
</body>
</html>
