<?php
/**
 * One-time installer.
 * 1) Creates all CRM tables (idempotent).
 * 2) Seeds default roles + admin user.
 *
 * Usage: visit /crm/install.php in browser, follow steps, then DELETE this file.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/permissions.php';

$step = $_GET['step'] ?? '1';
$P = CRM_TBL_PREFIX;

function done(string $msg) {
    echo "<div style='padding:8px 12px;background:#dcfce7;color:#166534;border-radius:6px;margin:6px 0;'>✓ $msg</div>";
}
function fail(string $msg) {
    echo "<div style='padding:8px 12px;background:#fee2e2;color:#991b1b;border-radius:6px;margin:6px 0;'>✗ $msg</div>";
}
function info(string $msg) {
    echo "<div style='padding:8px 12px;background:#dbeafe;color:#1e40af;border-radius:6px;margin:6px 0;'>ℹ $msg</div>";
}

?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تثبيت <?= htmlspecialchars(CRM_APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family: Cairo, sans-serif; background: #f0fdf4; padding: 40px; max-width: 800px; margin: auto; }
  h1 { color: #047857; }
  .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
  .btn { display: inline-block; background: #059669; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; border: 0; cursor: pointer; font-family: inherit; font-size: 15px; }
  .btn:hover { background: #047857; }
  input { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; margin-top: 4px; font-family: inherit; }
  label { display: block; margin-top: 12px; font-weight: 600; color: #374151; }
  pre { background: #f3f4f6; padding: 12px; border-radius: 6px; overflow:auto; font-size: 12px; }
</style>
</head>
<body>
<div class="card">
<h1>⚡ تثبيت <?= htmlspecialchars(CRM_APP_NAME) ?></h1>

<?php
try {
    $pdo = db();
} catch (Throwable $e) {
    fail('فشل الاتصال بقاعدة البيانات. عدّل <code>config.php</code> أولًا.<br>' . htmlspecialchars($e->getMessage()));
    exit;
}

if ($step === '1') {
    info('سيقوم المثبّت بإنشاء الجداول وإضافة بيانات افتراضية. يمكن تشغيله أكثر من مرة بأمان.');
    echo "<p style='margin-top:20px'><a class='btn' href='?step=2'>ابدأ التثبيت ←</a></p>";
}

elseif ($step === '2') {
    $sql = [
        "CREATE TABLE IF NOT EXISTS {$P}roles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            permissions JSON NOT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL UNIQUE,
            phone VARCHAR(40) NULL,
            password_hash VARCHAR(255) NOT NULL,
            role_id INT UNSIGNED NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            avatar_url VARCHAR(255) NULL,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (role_id),
            CONSTRAINT fk_{$P}users_role FOREIGN KEY (role_id) REFERENCES {$P}roles(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}clients (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(180) NOT NULL,
            type ENUM('company','individual','partner') NOT NULL DEFAULT 'company',
            industry VARCHAR(120) NULL,
            country VARCHAR(80) NULL,
            city VARCHAR(80) NULL,
            phone VARCHAR(40) NULL,
            email VARCHAR(160) NULL,
            website VARCHAR(255) NULL,
            owner_id INT UNSIGNED NOT NULL,
            stage ENUM('lead','qualified','active','closed','lost') NOT NULL DEFAULT 'lead',
            value DECIMAL(14,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (owner_id), INDEX (stage),
            CONSTRAINT fk_{$P}clients_owner FOREIGN KEY (owner_id) REFERENCES {$P}users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}contacts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            role VARCHAR(120) NULL,
            phone VARCHAR(40) NULL,
            email VARCHAR(160) NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (client_id),
            CONSTRAINT fk_{$P}contacts_client FOREIGN KEY (client_id) REFERENCES {$P}clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}deals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id INT UNSIGNED NOT NULL,
            title VARCHAR(200) NOT NULL,
            stage ENUM('lead','qualified','proposal','negotiation','won','lost') NOT NULL DEFAULT 'lead',
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            currency VARCHAR(8) NOT NULL DEFAULT 'SAR',
            probability TINYINT UNSIGNED NOT NULL DEFAULT 50,
            expected_close_at DATE NULL,
            actual_close_at DATE NULL,
            owner_id INT UNSIGNED NOT NULL,
            lost_reason VARCHAR(255) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (client_id), INDEX (owner_id), INDEX (stage),
            CONSTRAINT fk_{$P}deals_client FOREIGN KEY (client_id) REFERENCES {$P}clients(id) ON DELETE CASCADE,
            CONSTRAINT fk_{$P}deals_owner FOREIGN KEY (owner_id) REFERENCES {$P}users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}tasks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(220) NOT NULL,
            description TEXT NULL,
            assignee_id INT UNSIGNED NOT NULL,
            related_type ENUM('client','deal','none') NOT NULL DEFAULT 'none',
            related_id INT UNSIGNED NULL,
            priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
            status ENUM('open','in_progress','done','cancelled') NOT NULL DEFAULT 'open',
            due_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (assignee_id), INDEX (status), INDEX (due_at),
            CONSTRAINT fk_{$P}tasks_user FOREIGN KEY (assignee_id) REFERENCES {$P}users(id),
            CONSTRAINT fk_{$P}tasks_creator FOREIGN KEY (created_by) REFERENCES {$P}users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}activities (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(40) NULL,
            entity_id INT UNSIGNED NULL,
            details JSON NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id), INDEX (entity_type, entity_id), INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS {$P}settings (
            `key` VARCHAR(80) PRIMARY KEY,
            `value` TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($sql as $q) {
        try {
            $pdo->exec($q);
            preg_match('/CREATE TABLE IF NOT EXISTS (\S+)/', $q, $m);
            done('جدول جاهز: ' . htmlspecialchars($m[1] ?? '?'));
        } catch (Throwable $e) {
            fail('فشل: ' . htmlspecialchars($e->getMessage()));
        }
    }

    // Seed roles
    foreach (crm_default_roles() as $r) {
        $exists = $pdo->prepare("SELECT id FROM {$P}roles WHERE `key` = ?");
        $exists->execute([$r['key']]);
        if (!$exists->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO {$P}roles (`key`,name,permissions,is_system) VALUES (?,?,?,1)");
            $stmt->execute([$r['key'], $r['name'], json_encode($r['permissions'], JSON_UNESCAPED_UNICODE)]);
            done('دور أُضيف: ' . htmlspecialchars($r['name']));
        } else {
            info('دور موجود: ' . htmlspecialchars($r['name']));
        }
    }

    echo "<p style='margin-top:20px'><a class='btn' href='?step=3'>التالي ←</a></p>";
}

elseif ($step === '3') {
    $hasAdmin = (int)$pdo->query("SELECT COUNT(*) FROM {$P}users")->fetchColumn() > 0;
    if ($hasAdmin && empty($_POST)) {
        info('يوجد بالفعل مستخدمون في النظام. يمكنك تخطي هذه الخطوة.');
        echo "<p style='margin-top:20px'><a class='btn' href='?step=4'>تخطي ←</a></p>";
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name  = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $pass  = $_POST['password'] ?? '';

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
                fail('بيانات غير صحيحة. الاسم مطلوب، الإيميل صحيح، وكلمة المرور 8 أحرف على الأقل.');
            } else {
                $admin = $pdo->query("SELECT id FROM {$P}roles WHERE `key`='admin' LIMIT 1")->fetch();
                if (!$admin) { fail('دور admin غير موجود. ارجع للخطوة 2.'); exit; }
                $stmt = $pdo->prepare("INSERT INTO {$P}users (name,email,password_hash,role_id,status) VALUES (?,?,?,?, 'active')");
                $stmt->execute([
                    $name, $email,
                    password_hash($pass, CRM_PASSWORD_ALGO),
                    $admin['id']
                ]);
                done('تم إنشاء حساب المدير: ' . htmlspecialchars($email));
                echo "<p style='margin-top:20px'><a class='btn' href='?step=4'>التالي ←</a></p>";
                exit;
            }
        }
        ?>
        <h2 style='margin-top:24px'>إنشاء حساب المدير الأول</h2>
        <form method="post">
            <label>الاسم الكامل</label>
            <input name="name" required>
            <label>البريد الإلكتروني</label>
            <input name="email" type="email" required>
            <label>كلمة المرور (8+ أحرف)</label>
            <input name="password" type="password" minlength="8" required>
            <p style='margin-top:20px'><button type="submit" class="btn">إنشاء الحساب ←</button></p>
        </form>
        <?php
    }
}

elseif ($step === '4') {
    done('تم تثبيت النظام بنجاح! 🎉');
    info('<strong>مهم جدًا:</strong> احذف الآن ملف <code>install.php</code> من السيرفر للأمان.');
    echo "<p style='margin-top:20px'><a class='btn' href='" . htmlspecialchars(CRM_BASE_URL) . "/login.php'>تسجيل الدخول →</a></p>";
}
?>

</div>
</body>
</html>
