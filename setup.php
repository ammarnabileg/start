<?php
/**
 * صفحة التسطيب والتحديث.
 * - أول مرة: بتسأل عن قاعدة البيانات، بعدين بيانات الموقع والمدير.
 * - بعد كده: بتطلب إيميل وكلمة سر المدير، وتحدّث قاعدة البيانات.
 */
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/views/head.php';

date_default_timezone_set('UTC');
start_session();

$mode = is_installed() ? 'update' : 'install';
$step = (int)($_SESSION['setup_step'] ?? 1);
$errors = [];
$done = null;

function post(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

/* ======================= التسطيب لأول مرة ======================= */
if ($mode === 'install') {
    // التأكد إن السيرفر مناسب
    $checks = [
        t('نسخة PHP 7.4 أو أحدث') => version_compare(PHP_VERSION, '7.4.0', '>='),
        t('إضافة PDO MySQL') => extension_loaded('pdo_mysql'),
        t('إضافة cURL') => extension_loaded('curl'),
        t('إضافة mbstring') => extension_loaded('mbstring'),
        t('إضافة fileinfo') => extension_loaded('fileinfo'),
        t('صلاحية الكتابة في مجلد الموقع') => is_writable(__DIR__),
    ];
    $serverOk = !in_array(false, $checks, true);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $serverOk) {
        check_csrf();
        $action = post('action');

        if ($action === 'back') {
            $_SESSION['setup_step'] = 1;
            header('Location: setup.php');
            exit;
        }

        if ($action === 'db') {
            $db = ['site' => post('site_name'), 'host' => post('db_host') ?: 'localhost', 'name' => post('db_name'), 'user' => post('db_user'), 'pass' => (string)($_POST['db_pass'] ?? '')];
            $port = 3306;
            if (strpos($db['host'], ':') !== false) {
                [$db['host'], $p] = explode(':', $db['host'], 2);
                $port = (int)$p ?: 3306;
            }
            $db['port'] = $port;
            if ($db['site'] === '') {
                $errors[] = t('اكتب اسم الموقع.');
            } elseif ($db['name'] === '' || $db['user'] === '') {
                $errors[] = t('اكتب اسم قاعدة البيانات واسم المستخدم.');
            } else {
                try {
                    make_pdo($db['host'], $db['name'], $db['user'], $db['pass'], $port);
                    $_SESSION['setup_db'] = $db;
                    $_SESSION['setup_step'] = 2;
                    header('Location: setup.php');
                    exit;
                } catch (PDOException $ex) {
                    $errors[] = t('مقدرناش نوصل لقاعدة البيانات. اتأكد من الاسم واسم المستخدم والباسورد، وإن المستخدم متضاف للقاعدة بكل الصلاحيات.');
                    $errors[] = t('تفاصيل فنية: ') . $ex->getMessage();
                }
            }
        }

        if ($action === 'site' && !empty($_SESSION['setup_db'])) {
            $site = (string)$_SESSION['setup_db']['site'];
            $name = post('admin_name');
            $email = strtolower(post('admin_email'));
            $pass = (string)($_POST['admin_pass'] ?? '');
            $pass2 = (string)($_POST['admin_pass2'] ?? '');

            if ($site === '') $errors[] = t('اكتب اسم الموقع.');
            if ($name === '') $errors[] = t('اكتب اسمك.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('الإيميل مش صحيح.');
            if (strlen($pass) < 8) $errors[] = t('كلمة السر لازم تكون 8 حروف على الأقل.');
            if ($pass !== $pass2) $errors[] = t('كلمتين السر مش متطابقين.');

            if (!$errors) {
                $db = $_SESSION['setup_db'];
                try {
                    $pdo = make_pdo($db['host'], $db['name'], $db['user'], $db['pass'], $db['port']);
                    $GLOBALS['__app_key'] = new_app_key(); // مفتاح تشفير المفاتيح السرية
                    run_migrations($pdo);

                    $exists = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $exists->execute([$email]);
                    if ($id = $exists->fetchColumn()) {
                        $pdo->prepare("UPDATE users SET name = ?, password_hash = ?, role = 'admin' WHERE id = ?")
                            ->execute([$name, password_hash($pass, PASSWORD_DEFAULT), $id]);
                    } else {
                        $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')")
                            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                    }
                    save_setting('site_name', $site, $pdo);
                    save_setting('app_version', APP_VERSION, $pdo);

                    write_config([
                        'db_host' => $db['host'], 'db_port' => $db['port'], 'db_name' => $db['name'],
                        'db_user' => $db['user'], 'db_pass' => $db['pass'],
                        'app_key' => $GLOBALS['__app_key'],
                        'installed_at' => gmdate('c'),
                    ]);

                    unset($_SESSION['setup_db'], $_SESSION['setup_step']);
                    $done = 'install';
                } catch (Throwable $ex) {
                    $errors[] = t('حصلت مشكلة: ') . $ex->getMessage();
                }
            }
        }
    }
    if (empty($_SESSION['setup_db'])) {
        $step = 1;
    } else {
        $step = (int)($_SESSION['setup_step'] ?? 1);
    }
}

/* ======================= التحديث ======================= */
$updateResult = null;
if ($mode === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $pdo = db();
        // التحديثات بتتشغل بس بعد التأكد إن ده مدير النظام
        if (too_many_attempts($pdo)) {
            $errors[] = t('محاولات كتير غلط. استنى 15 دقيقة وجرب تاني.');
        } else {
            $st = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
            $st->execute([strtolower(post('email'))]);
            $admin = $st->fetch();
            if (!$admin || !password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
                record_failed_attempt($pdo);
                $errors[] = t('الإيميل أو كلمة السر غلط، أو الحساب ده مش مدير نظام.');
            } else {
                clear_attempts($pdo);
                $before = setting('app_version', t('غير معروف'));
                // النسخ القديمة مكانش فيها مفتاح تشفير: نضيفه لـ config.php
                if (empty(app_config()['app_key'])) {
                    $GLOBALS['__app_key'] = new_app_key();
                    write_config(app_config() + ['app_key' => $GLOBALS['__app_key']]);
                }
                $ran = run_migrations($pdo);
                save_setting('app_version', APP_VERSION, $pdo);
                // تنظيف ملفات قديمة لو موجودة في قائمة الحذف
                $removed = [];
                $obsolete = is_file(__DIR__ . '/app/obsolete.php') ? (require __DIR__ . '/app/obsolete.php') : [];
                foreach ($obsolete as $rel) {
                    $path = realpath(__DIR__ . '/' . $rel);
                    if ($path && strpos($path, __DIR__ . DIRECTORY_SEPARATOR) === 0 && is_file($path) && @unlink($path)) {
                        $removed[] = $rel;
                    }
                }
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$admin['id'];
                $pdo->prepare("INSERT INTO activity_log (user_id, action, details) VALUES (?, 'system.update', ?)")
                    ->execute([$admin['id'], $before . ' -> ' . APP_VERSION]);
                $updateResult = ['from' => $before, 'to' => APP_VERSION, 'ran' => $ran, 'removed' => $removed];
                $done = 'update';
            }
        }
    } catch (Throwable $ex) {
        $errors[] = t('حصلت مشكلة: ') . $ex->getMessage();
    }
}

$needsUpdate = false;
if ($mode === 'update' && !$done) {
    try {
        $needsUpdate = setting('app_version') !== APP_VERSION;
    } catch (Throwable $t) {
        $needsUpdate = true;
    }
}

$input = 'input';
?><!doctype html>
<?= html_open() ?>
<head>
<?php page_head($mode === 'install' ? t('تسطيب البرنامج') : t('تحديث البرنامج')); ?>
<meta name="robots" content="noindex">
</head>
<body class="min-h-screen flex items-start sm:items-center justify-center p-4 bg-gradient-to-b from-brand-50 via-ink-50 to-cyan-50">
<div class="fixed top-4 end-4 flex gap-2"><?= pref_switch() ?></div>
<div class="w-full max-w-lg card p-6 sm:p-8 my-6">

<?php if ($done === 'install'): ?>
  <div class="text-center space-y-4">
    <div class="text-5xl">✅</div>
    <h1 class="text-2xl font-bold"><?= e(t('تم التسطيب بنجاح')) ?></h1>
    <p class="text-slate-600"><?= e(t('ادخل بالإيميل وكلمة السر اللي لسه كاتبهم، وبعدها اعمل أول شركة واربطها من صفحة التكاملات.')) ?></p>
    <a href="index.php" class="btn-primary !px-8 !py-3"><?= e(t('ادخل للموقع')) ?></a>
  </div>

<?php elseif ($done === 'update'): ?>
  <div class="text-center space-y-4">
    <div class="text-5xl">✅</div>
    <h1 class="text-2xl font-bold"><?= e(t('تم التحديث بنجاح')) ?></h1>
    <p class="text-slate-600"><?= e(t('من الإصدار')) ?> <b><?= e($updateResult['from']) ?></b> <?= e(t('إلى')) ?> <b><?= e($updateResult['to']) ?></b></p>
    <p class="text-sm text-slate-500"><?= $updateResult['ran'] ? t('تحديثات قاعدة البيانات: ') . count($updateResult['ran']) : t('قاعدة البيانات كانت محدثة.') ?></p>
    <a href="index.php" class="btn-primary !px-8 !py-3"><?= e(t('ارجع للموقع')) ?></a>
  </div>

<?php elseif ($mode === 'install'): ?>
  <div class="mb-6">
    <h1 class="text-2xl font-bold"><?= e(t('تسطيب البرنامج')) ?></h1>
    <div class="flex gap-2 mt-4">
      <div class="h-1.5 flex-1 rounded-full bg-indigo-600"></div>
      <div class="h-1.5 flex-1 rounded-full <?= $step === 2 ? 'bg-indigo-600' : 'bg-slate-200' ?>"></div>
    </div>
    <p class="text-sm text-slate-500 mt-2"><?= e(t('الخطوة')) ?> <?= $step ?> <?= e(t('من 2')) ?></p>
  </div>

  <?php if (!$serverOk): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm space-y-1 mb-4">
      <p class="font-bold text-red-800 mb-2"><?= e(t('السيرفر محتاج شوية حاجات قبل التسطيب. ابعت الصفحة دي للدعم الفني بتاع الاستضافة:')) ?></p>
      <?php foreach ($checks as $label => $ok): ?><div><?= $ok ? '✅' : '❌' ?> <?= e($label) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php foreach ($errors as $er): ?><div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm mb-3 break-words"><?= e($er) ?></div><?php endforeach; ?>

  <?php if ($step === 1): $d = $_SESSION['setup_db'] ?? []; ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?><input type="hidden" name="action" value="db">
      <label class="block"><span class="label"><?= e(t('اسم الموقع')) ?></span>
        <input name="site_name" required value="<?= e($_POST['site_name'] ?? $d['site'] ?? '') ?>" placeholder="<?= e(t('مثلاً: لوحة وتد للنشر')) ?>" class="<?= $input ?>"></label>
      <h2 class="font-bold pt-2"><?= e(t('بيانات قاعدة البيانات')) ?></h2>
      <p class="text-sm text-slate-500 -mt-2"><?= e(t('بتعملها من لوحة الاستضافة (cPanel ← MySQL Databases). اعمل قاعدة ومستخدم، وضيف المستخدم للقاعدة بكل الصلاحيات.')) ?></p>
      <label class="block"><span class="label"><?= e(t('اسم قاعدة البيانات')) ?></span>
        <input name="db_name" required value="<?= e($_POST['db_name'] ?? $d['name'] ?? '') ?>" class="<?= $input ?>" dir="ltr"></label>
      <label class="block"><span class="label"><?= e(t('اسم المستخدم')) ?></span>
        <input name="db_user" required value="<?= e($_POST['db_user'] ?? $d['user'] ?? '') ?>" class="<?= $input ?>" dir="ltr"></label>
      <label class="block"><span class="label"><?= e(t('كلمة السر')) ?></span>
        <input name="db_pass" type="password" class="<?= $input ?>" dir="ltr"></label>
      <details class="text-sm">
        <summary class="cursor-pointer text-slate-500"><?= e(t('إعدادات متقدمة (سيبها زي ما هي)')) ?></summary>
        <label class="block mt-2"><span class="label"><?= e(t('السيرفر (Host)')) ?></span>
          <input name="db_host" value="<?= e($_POST['db_host'] ?? $d['host'] ?? 'localhost') ?>" class="<?= $input ?>" dir="ltr"></label>
      </details>
      <button <?= $serverOk ? '' : 'disabled' ?> class="btn-primary w-full !py-3"><?= e(t('التالي ←')) ?></button>
    </form>

  <?php else: ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?><input type="hidden" name="action" value="site">
      <h2 class="font-bold"><?= e(t('حسابك (مدير النظام)')) ?></h2>
      <label class="block"><span class="label"><?= e(t('اسمك')) ?></span>
        <input name="admin_name" required value="<?= e($_POST['admin_name'] ?? '') ?>" class="<?= $input ?>"></label>
      <label class="block"><span class="label"><?= e(t('إيميلك (هتدخل بيه)')) ?></span>
        <input name="admin_email" type="email" required value="<?= e($_POST['admin_email'] ?? '') ?>" class="<?= $input ?>" dir="ltr"></label>
      <label class="block"><span class="label"><?= e(t('كلمة السر (8 حروف على الأقل)')) ?></span>
        <input name="admin_pass" type="password" minlength="8" required class="<?= $input ?>" dir="ltr"></label>
      <label class="block"><span class="label"><?= e(t('اكتب كلمة السر تاني')) ?></span>
        <input name="admin_pass2" type="password" minlength="8" required class="<?= $input ?>" dir="ltr"></label>
      <div class="flex gap-3">
        <button name="action" value="back" formnovalidate class="btn-ghost"><?= e(t('→ رجوع')) ?></button>
        <button class="btn-primary flex-1 !py-3"><?= e(t('تسطيب')) ?></button>
      </div>
    </form>
  <?php endif; ?>

<?php else: /* update */ ?>
  <h1 class="text-2xl font-bold mb-1"><?= e(t('تحديث البرنامج')) ?></h1>
  <p class="text-sm text-slate-500 mb-5">
    <?= e(t('الإصدار الجديد:')) ?> <b><?= e(APP_VERSION) ?></b>.
    <?= $needsUpdate ? t('فيه تحديث محتاج يتطبق.') : t('البرنامج محدث، بس تقدر تشغل التحديث تاني بأمان.') ?>
  </p>
  <?php foreach ($errors as $er): ?><div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm mb-3 break-words"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post" class="space-y-4">
    <?= csrf_field() ?>
    <p class="label"><?= e(t('ادخل بحساب مدير النظام:')) ?></p>
    <label class="block"><span class="label"><?= e(t('الإيميل')) ?></span>
      <input name="email" type="email" required class="<?= $input ?>" dir="ltr"></label>
    <label class="block"><span class="label"><?= e(t('كلمة السر')) ?></span>
      <input name="password" type="password" required class="<?= $input ?>" dir="ltr"></label>
    <button class="btn-primary w-full !py-3"><?= e(t('تحديث البيانات')) ?></button>
  </form>
<?php endif; ?>

</div>
</body>
</html>
