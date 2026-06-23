<?php
/**
 * Watad AI Interviewer — Web Installer (Simplified)
 * MySQL · OpenAI · HeyGen · Local Storage
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');
set_time_limit(300);

$root     = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
$lockFile = $root . '/storage/installed.lock';
$envFile  = $root . '/.env';
$envSample= $root . '/.env.example';

$errors  = [];
$notices = [];
$output  = '';
$done    = false;
$action  = $_POST['action'] ?? null;
$alreadyInstalled = file_exists($lockFile) && !isset($_GET['force']);

/* ══════════════════ DATABASE BACKUP DOWNLOAD ══════════════════════ */
if (isset($_GET['download']) && $_GET['download'] === 'backup'
    && file_exists($lockFile) && file_exists($envFile)) {
    $env  = parse_env_file($envFile);
    $host = $env['DB_HOST']     ?? '127.0.0.1';
    $port = $env['DB_PORT']     ?? '3306';
    $db   = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    if ($db && $user) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="watad-backup-' . date('Y-m-d_H-i-s') . '.sql"');
        $pw  = $pass !== '' ? '-p' . escapeshellarg($pass) : '';
        passthru(sprintf(
            'mysqldump -h %s -P %s -u %s %s %s 2>/dev/null',
            escapeshellarg($host), escapeshellarg($port),
            escapeshellarg($user), $pw, escapeshellarg($db)
        ));
        exit;
    }
    die('تعذّر قراءة بيانات قاعدة البيانات من .env');
}

/* ══════════════════ VENDOR RECOVERY (zip / split parts) ════════════ */
/** Locate split parts (vendor.zip.part00…) in project root or /public. */
function find_vendor_parts(string $root): array {
    $parts = glob($root . '/vendor.zip.part*') ?: [];
    if (!$parts) $parts = glob($root . '/public/vendor.zip.part*') ?: [];
    sort($parts);
    return $parts;
}
/** Extract a zip into $root; returns true on success. */
function extract_vendor_zip(string $zipPath, string $root, array &$notices, array &$errors): bool {
    if (!class_exists('ZipArchive')) {
        $errors[] = 'امتداد ZipArchive غير مفعّل على السيرفر. فعّله ثم أعد المحاولة.';
        return false;
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        $errors[] = 'فشل فتح vendor.zip — قد يكون تالفاً أو ناقص الأجزاء.';
        return false;
    }
    $zip->extractTo($root);
    $zip->close();
    $notices[] = '✓ تم استخراج مجلد vendor/ بنجاح!';
    return true;
}

// (1) Upload a single vendor.zip through the form.
if ($action === 'extract_vendor') {
    if (empty($_FILES['vendor_zip']['tmp_name']) || $_FILES['vendor_zip']['error'] !== 0) {
        $errors[] = 'لم يتم رفع الملف أو يتجاوز حد الرفع (upload_max_filesize). ارفعه عبر File Manager بدلاً من ذلك.';
    } else {
        extract_vendor_zip($_FILES['vendor_zip']['tmp_name'], $root, $notices, $errors);
    }
}

// (2) A vendor.zip already sitting on disk (uploaded via File Manager).
if ($action === 'extract_disk_vendor') {
    $zp = file_exists($root . '/vendor.zip') ? $root . '/vendor.zip'
        : (file_exists($root . '/public/vendor.zip') ? $root . '/public/vendor.zip' : null);
    if (!$zp) {
        $errors[] = 'لم يتم العثور على vendor.zip في مجلد المشروع.';
    } elseif (extract_vendor_zip($zp, $root, $notices, $errors)) {
        @unlink($zp);
    }
}

// (3) Split parts on disk → concatenate then extract (no manual merge needed).
if ($action === 'merge_vendor') {
    $parts = find_vendor_parts($root);
    if (!$parts) {
        $errors[] = 'لم يتم العثور على أجزاء vendor.zip.part* في مجلد المشروع.';
    } else {
        $zipPath = $root . '/vendor.zip';
        @unlink($zipPath);
        $out = @fopen($zipPath, 'wb');
        if (!$out) {
            $errors[] = 'تعذّر إنشاء vendor.zip — تأكد أن المجلد الجذر قابل للكتابة.';
        } else {
            foreach ($parts as $p) {
                $in = fopen($p, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
            fclose($out);
            if (extract_vendor_zip($zipPath, $root, $notices, $errors)) {
                @unlink($zipPath);
                foreach ($parts as $p) @unlink($p);
                $notices[] = '✓ تم حذف الملفات المؤقتة. المكتبات جاهزة الآن.';
            }
        }
    }
}

/* ══════════════════ HELPERS ════════════════════════════════════════ */
$old = fn(string $k, string $d = '') => htmlspecialchars((string)($_POST[$k] ?? $d), ENT_QUOTES);

function parse_env_file(string $file): array {
    $env = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $env[trim($k)] = trim($v, " \"'\t\n\r");
    }
    return $env;
}

function env_set(string $content, string $key, string $value): string {
    $quoted = preg_match('/[\s#"]/', $value)
        ? '"' . str_replace('"', '\"', $value) . '"'
        : $value;
    $line = $key . '=' . $quoted;
    return preg_match('/^' . preg_quote($key, '/') . '=/m', $content)
        ? preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $line, $content)
        : rtrim($content) . "\n" . $line . "\n";
}

function boot_kernel(string $root) {
    require_once $root . '/vendor/autoload.php';
    $app    = require $root . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    return $kernel;
}

/* ══════════════════ REQUIREMENTS ═══════════════════════════════════ */
$vendorOk = file_exists($autoload);
$requirements = [
    'PHP 8.3+'                    => version_compare(PHP_VERSION, '8.3.0', '>='),
    'PDO'                         => extension_loaded('pdo'),
    'PDO MySQL'                   => extension_loaded('pdo_mysql'),
    'Mbstring'                    => extension_loaded('mbstring'),
    'OpenSSL'                     => extension_loaded('openssl'),
    'cURL'                        => extension_loaded('curl'),
    'ZipArchive'                  => class_exists('ZipArchive'),
    'مكتبات المشروع (vendor/)'    => $vendorOk,
    'مجلد storage/ قابل للكتابة'  => is_writable($root . '/storage'),
    'المجلد الجذر قابل للكتابة'   => is_writable($root),
];
$requirementsOk = !in_array(false, $requirements, true);

/* ══════════════════ RESET ══════════════════════════════════════════ */
if ($action === 'reset' && file_exists($lockFile)) {
    if (($_POST['confirm'] ?? '') !== 'DELETE') {
        $errors[] = 'يجب كتابة DELETE للتأكيد.';
    } else {
        try {
            if (file_exists($autoload) && file_exists($envFile)) {
                boot_kernel($root)->call('db:wipe', ['--force' => true]);
            }
            @unlink($lockFile);
            $alreadyInstalled = false;
            $notices[] = 'تم مسح كل البيانات. يمكنك إعادة التثبيت الآن.';
        } catch (\Throwable $e) {
            $errors[] = 'فشل: ' . $e->getMessage();
        }
    }
}

/* ══════════════════ INSTALL ════════════════════════════════════════ */
if ($action === 'install' && !$alreadyInstalled && $requirementsOk) {
    $f = fn(string $k, string $d = '') => trim((string)($_POST[$k] ?? $d));

    foreach (['app_name','app_url','db_database','db_username','admin_name','admin_email','admin_password'] as $req) {
        if ($f($req) === '') $errors[] = "الحقل «{$req}» مطلوب.";
    }
    if ($f('openai_api_key') === '')    $errors[] = 'OpenAI API Key مطلوب.';
    if (strlen($f('admin_password')) < 8) $errors[] = 'كلمة المرور يجب ألا تقل عن 8 أحرف.';
    if (!filter_var($f('admin_email'), FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صحيح.';

    if (empty($errors)) {
        try {
            /* resolve model (handle "custom" option) */
            $convModel  = $f('ai_conversation_model') === 'custom'
                ? ($f('conv_custom') ?: 'gpt-4o')
                : ($f('ai_conversation_model') ?: 'gpt-4o');
            $analModel  = $f('ai_analysis_model') === 'custom'
                ? ($f('anal_custom') ?: 'gpt-4o')
                : ($f('ai_analysis_model') ?: 'gpt-4o');

            $heygenKey = $f('heygen_api_key');
            $videoProv = $heygenKey !== '' ? 'heygen' : 'none';

            /* write .env */
            $content = file_exists($envSample) ? file_get_contents($envSample) : "APP_NAME=Watad\n";
            foreach ([
                'APP_NAME'   => $f('app_name'),
                'APP_ENV'    => 'production',
                'APP_DEBUG'  => 'false',
                'APP_URL'    => rtrim($f('app_url'), '/'),

                'DB_CONNECTION' => 'mysql',
                'DB_HOST'       => $f('db_host', '127.0.0.1'),
                'DB_PORT'       => $f('db_port', '3306'),
                'DB_DATABASE'   => $f('db_database'),
                'DB_USERNAME'   => $f('db_username'),
                'DB_PASSWORD'   => $f('db_password'),

                'FILESYSTEM_DISK'      => 'local',
                'CACHE_STORE'          => 'database',
                'QUEUE_CONNECTION'     => 'database',
                'SESSION_DRIVER'       => 'database',
                'BROADCAST_CONNECTION' => 'null',

                /* email disabled — tracking is on-platform only */
                'MAIL_MAILER' => 'log',

                'WATAD_AI_PROVIDER'           => 'openai',
                'OPENAI_API_KEY'              => $f('openai_api_key'),
                'WATAD_AI_CONVERSATION_MODEL' => $convModel,
                'WATAD_AI_ANALYSIS_MODEL'     => $analModel,

                'WATAD_VIDEO_PROVIDER' => $videoProv,
                'HEYGEN_API_KEY'       => $heygenKey,

                'WATAD_SHEETS_ENABLED' => 'false',
            ] as $k => $v) {
                $content = env_set($content, $k, $v);
            }
            file_put_contents($envFile, $content);

            /* boot Laravel */
            $kernel = boot_kernel($root);
            $run = function(string $cmd, array $p = []) use ($kernel, &$output) {
                $kernel->call($cmd, $p);
                $output .= "$ artisan {$cmd}\n" . $kernel->output() . "\n";
            };

            $run('key:generate', ['--force' => true]);
            $run('migrate',      ['--force' => true]);
            $run('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true]);
            $run('db:seed', ['--class' => 'Database\\Seeders\\AvatarSeeder',         '--force' => true]);
            $run('db:seed', ['--class' => 'Database\\Seeders\\PipelineSeeder',       '--force' => true]);

            /* super admin */
            if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\RolePermissionSeeder', '--force' => true,
                ]);
            }
            $admin = \App\Models\User::updateOrCreate(
                ['email' => $f('admin_email')],
                ['name'  => $f('admin_name'),
                 'password'          => \Illuminate\Support\Facades\Hash::make($f('admin_password')),
                 'is_active'         => true,
                 'email_verified_at' => now()]
            );
            if ($role = \App\Models\Role::where('slug', 'super_admin')->first()) {
                $admin->roles()->syncWithoutDetaching([$role->id]);
            }

            file_put_contents($lockFile, 'Installed: ' . date('c'));
            $done = true;

        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تثبيت · Watad AI Interviewer</title>
<style>
:root{--brand:#1a6f3c;--brand-d:#115a2e;--brand-l:#e8f4ed;}
*{box-sizing:border-box;font-family:'Segoe UI',Tahoma,Arial,sans-serif;margin:0;padding:0}
body{background:#f4f6f8;color:#1e293b;padding-bottom:60px}
.wrap{max-width:700px;margin:36px auto;padding:0 16px}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:24px}
.logo{width:48px;height:48px;border-radius:12px;background:var(--brand);color:#fff;display:grid;place-items:center;font-weight:700;font-size:24px;flex-shrink:0}
.brand h1{font-size:22px;color:#0f172a}
.brand p{font-size:13px;color:#64748b;margin-top:2px}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:24px;margin-bottom:16px}
h2{font-size:14px;font-weight:700;color:var(--brand);border-bottom:1.5px solid var(--brand-l);padding-bottom:8px;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.num{display:inline-flex;width:26px;height:26px;background:var(--brand);color:#fff;border-radius:50%;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
label{display:block;font-size:13px;color:#475569;font-weight:500;margin-bottom:5px;margin-top:14px}
label:first-child{margin-top:0}
input[type=text],input[type=url],input[type=email],input[type=password],input[type=number],input[type=file],select{
    width:100%;padding:10px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;transition:border-color .15s,box-shadow .15s}
input:focus,select:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(26,111,60,.12)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--brand);color:#fff;border:0;padding:12px 24px;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;transition:background .15s}
.btn:hover{background:var(--brand-d)}
.btn:disabled{opacity:.45;cursor:not-allowed}
.btn-danger{background:#dc2626}.btn-danger:hover{background:#b91c1c}
.btn-outline{background:#fff;color:var(--brand);border:1.5px solid var(--brand)}.btn-outline:hover{background:var(--brand-l)}
.btn-sm{padding:8px 14px;font-size:13px}
.req-row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:14px}
.req-row:last-child{border:0}
.ok{color:#059669;font-weight:700} .bad{color:#dc2626;font-weight:700}
.alert{border-radius:9px;padding:13px 16px;font-size:14px;margin-bottom:12px;line-height:1.7}
.alert-error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.alert-ok{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.alert-info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.alert-warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
pre{background:#0f172a;color:#e2e8f0;padding:14px;border-radius:9px;font-size:12px;overflow:auto;max-height:220px;margin-top:10px}
.hint{font-size:12px;color:#94a3b8;margin-top:5px;line-height:1.5}
code{background:#f1f5f9;border-radius:4px;padding:1px 6px;font-size:12.5px;font-family:monospace}
.path-box{background:#0f172a;color:#7dd3fc;padding:10px 14px;border-radius:8px;font-size:12px;font-family:monospace;margin:8px 0;word-break:break-all}
.box{border:1px solid #e2e8f0;border-radius:10px;padding:16px;background:#f8fafc;margin-top:14px}
.box-title{font-weight:700;font-size:14px;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.badge{display:inline-flex;font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px}
.badge-req{background:#fef2f2;color:#b91c1c}
.badge-opt{background:#fefce8;color:#92400e}
.badge-ok{background:#d1fae5;color:#065f46}
.full{grid-column:1/-1}
details{border:1px solid #e2e8f0;border-radius:9px;overflow:hidden;margin-top:10px}
summary{padding:10px 14px;cursor:pointer;font-size:13px;font-weight:600;color:#475569;background:#f8fafc;user-select:none}
details[open] summary{background:#fff;border-bottom:1px solid #e2e8f0}
details[open]>*:not(summary){padding:14px}
</style>
</head>
<body>
<div class="wrap">

<div class="brand">
    <div class="logo">W</div>
    <div>
        <h1>Watad AI Interviewer</h1>
        <p>صفحة التثبيت — MySQL · OpenAI · تخزين محلي</p>
    </div>
</div>

<?php foreach ($notices as $n): ?>
    <div class="alert alert-info"><?= htmlspecialchars($n) ?></div>
<?php endforeach; ?>

<?php /* ═══════════════ ALREADY INSTALLED ═══════════════ */ ?>
<?php if ($alreadyInstalled): ?>
    <div class="card">
        <div class="alert alert-ok" style="margin-bottom:14px">✓ المنصة مثبّتة وتعمل.</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="login" class="btn">← الدخول للمنصة</a>
            <a href="?download=backup" class="btn btn-outline btn-sm" style="align-self:center">
                ⬇ تحميل نسخة احتياطية SQL
            </a>
        </div>
        <p class="hint" style="margin-top:12px">
            للأمان: احذف ملف <code>public/install.php</code> بعد الانتهاء من الإعداد.
        </p>
    </div>

    <div class="card">
        <h2><span style="color:#b91c1c">⚠</span> إعادة التثبيت (مسح كامل)</h2>
        <p style="font-size:14px;color:#475569;margin-bottom:14px">
            يمسح <strong>جميع البيانات نهائياً</strong> ويعيدك لصفحة التثبيت من الصفر.
        </p>
        <form method="POST" onsubmit="return confirm('سيتم مسح كل البيانات نهائياً. متأكد؟');">
            <input type="hidden" name="action" value="reset">
            <label>اكتب <code>DELETE</code> للتأكيد</label>
            <input type="text" name="confirm" placeholder="DELETE" autocomplete="off" style="max-width:220px">
            <div style="margin-top:14px">
                <button class="btn btn-danger" type="submit">مسح كل شيء وإعادة التثبيت</button>
            </div>
        </form>
        <?php foreach ($errors as $e): ?>
            <div class="alert alert-error" style="margin-top:12px"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>

<?php /* ═══════════════ DONE ═══════════════════════════ */ ?>
<?php elseif ($done): ?>
    <div class="card">
        <div class="alert alert-ok">🎉 تم التثبيت بنجاح! حساب المدير جاهز.</div>
        <p style="font-size:14px;color:#475569;line-height:1.9;margin:14px 0">
            <strong>خطوة مهمة — شغّل معالج الطابور على السيرفر:</strong><br>
            <code>php artisan queue:work --daemon</code><br>
            هذا ضروري لتشغيل مقابلات الذكاء الاصطناعي وإنتاج التقارير.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a href="login" class="btn">← الدخول للمنصة</a>
            <a href="?download=backup" class="btn btn-outline btn-sm" style="align-self:center">
                ⬇ تحميل نسخة احتياطية SQL
            </a>
        </div>
        <details style="margin-top:14px">
            <summary>تفاصيل التثبيت (سجل الأوامر)</summary>
            <pre><?= htmlspecialchars($output) ?></pre>
        </details>
    </div>

<?php /* ═══════════════ INSTALL FORM ══════════════════ */ ?>
<?php else: ?>

<!-- ❶ Requirements -->
<div class="card">
    <h2><span class="num">1</span> متطلبات النظام</h2>
    <?php foreach ($requirements as $name => $pass): ?>
        <div class="req-row">
            <span><?= htmlspecialchars($name) ?></span>
            <span class="<?= $pass ? 'ok' : 'bad' ?>"><?= $pass ? '✓ موجود' : '✗ مفقود' ?></span>
        </div>
    <?php endforeach; ?>

    <?php if (!$vendorOk):
        $diskParts = find_vendor_parts($root);
        $diskZip   = file_exists($root . '/vendor.zip') || file_exists($root . '/public/vendor.zip');
    ?>
    <div class="alert alert-warn" style="margin-top:16px">
        <strong>مشكلة: مكتبات PHP غير موجودة (vendor/)</strong><br>
        المسار المطلوب:
        <div class="path-box"><?= htmlspecialchars($root) ?>/vendor/autoload.php</div>

        <?php if ($diskParts): ?>
            <!-- BEST CASE: split parts already uploaded to the server -->
            <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:14px;margin:6px 0">
                <strong style="color:#047857">✓ تم العثور على <?= count($diskParts) ?> أجزاء على السيرفر.</strong><br>
                اضغط الزر وسيتم دمجها واستخراج المكتبات تلقائياً (بدون أي خطوة يدوية):
                <form method="POST" style="margin-top:12px">
                    <input type="hidden" name="action" value="merge_vendor">
                    <button class="btn" type="submit">⚙ ادمج الأجزاء واستخرج المكتبات</button>
                </form>
            </div>
        <?php elseif ($diskZip): ?>
            <!-- vendor.zip already on disk -->
            <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:14px;margin:6px 0">
                <strong style="color:#047857">✓ تم العثور على vendor.zip على السيرفر.</strong>
                <form method="POST" style="margin-top:12px">
                    <input type="hidden" name="action" value="extract_disk_vendor">
                    <button class="btn" type="submit">⚙ استخرج vendor.zip</button>
                </form>
            </div>
        <?php endif; ?>

        <details<?= $diskParts || $diskZip ? '' : ' open' ?> style="margin-top:8px">
            <summary>الطريقة الموصى بها عبر File Manager (Plesk) — اضغط للتفاصيل</summary>
            <div style="font-size:13px;line-height:1.9">
                <strong>الأسهل والأضمن (بدون أي أوامر):</strong>
                <ol style="margin:8px 22px 0 0;padding:0">
                    <li>افتح Plesk ← <strong>File Manager</strong> ← ادخل مجلد <code>httpdocs</code>.</li>
                    <li>ارفع ملفات الأجزاء الثلاثة <code>vendor.zip.partaa</code>، <code>vendor.zip.partab</code>، <code>vendor.zip.partac</code> هناك (سحب وإفلات).</li>
                    <li>ارجع لهذه الصفحة واضغط <strong>تحديث/إعادة تحميل</strong> — سيظهر زر «ادمج الأجزاء».</li>
                </ol>
                <p style="margin-top:10px;color:#64748b">
                    كل جزء حوالي 6MB فيمر دون مشاكل حدود الرفع.
                    لا ترفعها داخل مجلد <code>public</code> بل في <code>httpdocs</code> مباشرةً.
                </p>
            </div>
        </details>

        <details style="margin-top:8px">
            <summary>أو ارفع vendor.zip المدموج مباشرةً من هنا</summary>
            <div>
                <p style="font-size:13px;color:#64748b;margin-bottom:8px">
                    ادمج الأجزاء على جهازك أولاً
                    (<code>copy /b vendor.zip.partaa+vendor.zip.partab+vendor.zip.partac vendor.zip</code> على Windows،
                    أو <code>cat vendor.zip.part* &gt; vendor.zip</code> على Mac/Linux) ثم ارفع الناتج:
                </p>
                <form method="POST" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <input type="hidden" name="action" value="extract_vendor">
                    <input type="file" name="vendor_zip" accept=".zip" style="flex:1;min-width:200px">
                    <button class="btn btn-sm" type="submit">رفع واستخراج</button>
                </form>
            </div>
        </details>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="POST" enctype="multipart/form-data"
      onsubmit="
        var b=this.querySelector('.install-btn');
        b.innerText='جارٍ التثبيت… (قد يستغرق دقيقة)';
        b.disabled=true;">
    <input type="hidden" name="action" value="install">

    <!-- ❷ Site -->
    <div class="card">
        <h2><span class="num">2</span> إعدادات الموقع</h2>
        <div class="grid">
            <div>
                <label>اسم الموقع</label>
                <input type="text" name="app_name" value="<?= $old('app_name','Watad AI Interviewer') ?>">
            </div>
            <div>
                <label>رابط الموقع (URL)</label>
                <input type="url" name="app_url"
                       value="<?= $old('app_url','https://') ?>"
                       placeholder="https://yourdomain.com">
            </div>
        </div>
    </div>

    <!-- ❸ Database -->
    <div class="card">
        <h2><span class="num">3</span> قاعدة البيانات (MySQL)</h2>
        <p class="hint" style="margin-top:0;margin-bottom:4px">
            أدخل بيانات قاعدة البيانات التي أنشأتها على السيرفر (من cPanel مثلاً)
        </p>
        <div class="grid">
            <div>
                <label>اسم قاعدة البيانات</label>
                <input type="text" name="db_database"
                       value="<?= $old('db_database') ?>" placeholder="watad_db">
            </div>
            <div>
                <label>اسم المستخدم</label>
                <input type="text" name="db_username"
                       value="<?= $old('db_username') ?>" placeholder="watad_user">
            </div>
            <div>
                <label>كلمة مرور قاعدة البيانات</label>
                <input type="password" name="db_password">
            </div>
            <div>
                <label>هوست (لا تغيّره عادةً)</label>
                <input type="text" name="db_host"
                       value="<?= $old('db_host','127.0.0.1') ?>">
            </div>
        </div>
    </div>

    <!-- ❹ Admin -->
    <div class="card">
        <h2><span class="num">4</span> حساب المدير (Super Admin)</h2>
        <div class="grid">
            <div>
                <label>الاسم الكامل</label>
                <input type="text" name="admin_name" value="<?= $old('admin_name') ?>">
            </div>
            <div>
                <label>البريد الإلكتروني</label>
                <input type="email" name="admin_email" value="<?= $old('admin_email') ?>">
            </div>
            <div class="full">
                <label>كلمة المرور (8 أحرف على الأقل)</label>
                <input type="password" name="admin_password">
            </div>
        </div>
    </div>

    <!-- ❺ API Keys -->
    <div class="card">
        <h2><span class="num">5</span> مفاتيح API</h2>

        <!-- OpenAI -->
        <div class="box">
            <div class="box-title">
                🤖 OpenAI
                <span class="badge badge-req">مطلوب</span>
            </div>

            <label>OpenAI API Key</label>
            <input type="text" name="openai_api_key"
                   placeholder="sk-proj-…" value="<?= $old('openai_api_key') ?>">
            <p class="hint">احصل عليه من: platform.openai.com → API Keys</p>

            <div class="grid" style="margin-top:14px">
                <div>
                    <label>موديل المقابلة (المحادثة)</label>
                    <select name="ai_conversation_model" id="convSel"
                            onchange="toggleCustom('convSel','convCustom')">
                        <option value="gpt-4o"<?= selOpt('ai_conversation_model','gpt-4o',true,$_POST) ?>>
                            gpt-4o — الأفضل توازناً (موصى)</option>
                        <option value="gpt-4o-mini"<?= selOpt('ai_conversation_model','gpt-4o-mini',false,$_POST) ?>>
                            gpt-4o-mini — أسرع وأقل تكلفة</option>
                        <option value="gpt-4-turbo"<?= selOpt('ai_conversation_model','gpt-4-turbo',false,$_POST) ?>>
                            gpt-4-turbo — موثوق ومستقر</option>
                        <option value="custom"<?= selOpt('ai_conversation_model','custom',false,$_POST) ?>>
                            أكتب اسم الموديل بنفسي…</option>
                    </select>
                    <input type="text" id="convCustom" name="conv_custom"
                           placeholder="اسم الموديل"
                           style="display:none;margin-top:6px"
                           value="<?= $old('conv_custom') ?>">
                </div>
                <div>
                    <label>موديل التحليل (التقييم والتقارير)</label>
                    <select name="ai_analysis_model" id="analSel"
                            onchange="toggleCustom('analSel','analCustom')">
                        <option value="gpt-4o"<?= selOpt('ai_analysis_model','gpt-4o',true,$_POST) ?>>
                            gpt-4o — موصى للتحليل</option>
                        <option value="o4-mini"<?= selOpt('ai_analysis_model','o4-mini',false,$_POST) ?>>
                            o4-mini — تفكير عميق (الأحدث)</option>
                        <option value="o3-mini"<?= selOpt('ai_analysis_model','o3-mini',false,$_POST) ?>>
                            o3-mini — تفكير عميق</option>
                        <option value="gpt-4-turbo"<?= selOpt('ai_analysis_model','gpt-4-turbo',false,$_POST) ?>>
                            gpt-4-turbo — موثوق ومستقر</option>
                        <option value="custom"<?= selOpt('ai_analysis_model','custom',false,$_POST) ?>>
                            أكتب اسم الموديل بنفسي…</option>
                    </select>
                    <input type="text" id="analCustom" name="anal_custom"
                           placeholder="اسم الموديل"
                           style="display:none;margin-top:6px"
                           value="<?= $old('anal_custom') ?>">
                </div>
            </div>
        </div>

        <!-- HeyGen -->
        <div class="box" style="margin-top:14px">
            <div class="box-title">
                🎥 HeyGen (أفاتار الفيديو)
                <span class="badge badge-opt">اختياري</span>
            </div>
            <label>HeyGen API Key</label>
            <input type="text" name="heygen_api_key"
                   placeholder="اتركه فارغاً إذا لم تشترك بعد"
                   value="<?= $old('heygen_api_key') ?>">
            <p class="hint">
                إذا تركته فارغاً تعمل المقابلات بالنص/الصوت فقط (بدون أفاتار).<br>
                يمكنك إضافته لاحقاً من ملف <code>.env</code> في جذر المشروع.
            </p>
        </div>
    </div>

    <!-- Install Button -->
    <button class="btn install-btn" type="submit"
            <?= $requirementsOk ? '' : 'disabled' ?>
            style="width:100%;padding:15px;font-size:16px;border-radius:10px">
        تثبيت Watad AI Interviewer ←
    </button>
    <?php if (!$requirementsOk): ?>
        <p style="text-align:center;color:#dc2626;font-size:13px;margin-top:10px">
            حل المشكلات المُشار إليها أعلاه ثم أعد تحميل الصفحة
        </p>
    <?php endif; ?>

</form>

<?php endif; ?>
</div><!-- /wrap -->

<?php
/* PHP helper used in HTML — defined here to avoid "called before definition" in older PHP */
function selOpt(string $key, string $val, bool $default, array $post): string {
    $current = $post[$key] ?? ($default ? $val : '');
    return $current === $val ? ' selected' : '';
}
?>

<script>
function toggleCustom(selId, inputId) {
    var sel = document.getElementById(selId);
    var inp = document.getElementById(inputId);
    var show = sel.value === 'custom';
    inp.style.display = show ? 'block' : 'none';
    inp.required = show;
    if (!show) inp.value = '';
}
// restore state on page reload (validation error)
window.addEventListener('DOMContentLoaded', function() {
    toggleCustom('convSel','convCustom');
    toggleCustom('analSel','analCustom');
});
</script>
</body>
</html>
