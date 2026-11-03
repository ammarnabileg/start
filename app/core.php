<?php
/**
 * الأساسيات: الاتصال بقاعدة البيانات، الجلسة، الحماية، الإعدادات.
 */

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/connectors.php';

define('ROOT_DIR', dirname(__DIR__));
define('CONFIG_FILE', ROOT_DIR . '/config.php');

function is_installed(): bool
{
    return is_file(CONFIG_FILE);
}

function app_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = is_installed() ? (require CONFIG_FILE) : [];
    }
    return $cfg;
}

function make_pdo(string $host, string $name, string $user, string $pass, int $port = 3306): PDO
{
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = app_config();
        $pdo = make_pdo($c['db_host'], $c['db_name'], $c['db_user'], $c['db_pass'], (int)($c['db_port'] ?? 3306));
    }
    return $pdo;
}

/* ---------- تحديثات قاعدة البيانات ---------- */

function run_migrations(PDO $pdo): array
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        name VARCHAR(100) PRIMARY KEY,
        ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $done = $pdo->query("SELECT name FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    $all = require __DIR__ . '/migrations.php';
    $ran = [];
    foreach ($all as $name => $statements) {
        if (in_array($name, $done, true)) {
            continue;
        }
        foreach ($statements as $sql) {
            if ($sql instanceof Closure) {
                $sql($pdo); // تحديث محتاج كود PHP (زي تشفير البيانات)
            } else {
                $pdo->exec($sql);
            }
        }
        $st = $pdo->prepare("INSERT INTO migrations (name) VALUES (?)");
        $st->execute([$name]);
        $ran[] = $name;
    }
    return $ran;
}

/* ---------- الإعدادات ---------- */

function setting(string $name, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query("SELECT name, value FROM settings") as $row) {
            $cache[$row['name']] = $row['value'];
        }
    }
    return array_key_exists($name, $cache) ? $cache[$name] : $default;
}

function save_setting(string $name, ?string $value, ?PDO $pdo = null): void
{
    $pdo = $pdo ?: db();
    $st = $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    $st->execute([$name, $value]);
}

/* ---------- الجلسة والحماية ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('ZSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit(t('انتهت صلاحية الصفحة. ارجع وحدّث الصفحة وحاول تاني.'));
    }
}

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 64);
}

/** يمنع تخمين كلمة السر: 5 محاولات فاشلة كل 15 دقيقة لكل IP */
function too_many_attempts(PDO $pdo): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)");
    $st->execute([client_ip()]);
    return (int)$st->fetchColumn() >= 5;
}

function record_failed_attempt(PDO $pdo): void
{
    $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)")->execute([client_ip()]);
    $pdo->exec("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
}

function clear_attempts(PDO $pdo): void
{
    $pdo->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([client_ip()]);
}

/* ---------- المستخدمين ---------- */

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
            $st->execute([$_SESSION['uid']]);
            $user = $st->fetch() ?: null;
        }
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function log_activity(string $action, string $details = ''): void
{
    try {
        $u = current_user();
        $w = current_workspace();
        db()->prepare("INSERT INTO activity_log (user_id, workspace_id, action, details) VALUES (?, ?, ?, ?)")
            ->execute([$u['id'] ?? null, $w['id'] ?? null, $action, mb_substr($details, 0, 2000)]);
    } catch (Throwable $t) {
        // لا نوقف البرنامج بسبب السجل
    }
}

/* ---------- الشركات (Workspaces) ---------- */

/** الشركات اللي المستخدم عضو فيها (موافق على دعوتها). كل شركة فيها my_role. */
function user_workspaces(bool $fresh = false): array
{
    static $list = null;
    if ($list === null || $fresh) {
        $u = current_user();
        if (!$u) {
            return $list = [];
        }
        $st = db()->prepare("SELECT w.*, wu.role AS my_role FROM workspaces w
            JOIN workspace_users wu ON wu.workspace_id = w.id
            WHERE wu.user_id = ? AND wu.status = 'active' ORDER BY w.name");
        $st->execute([$u['id']]);
        $list = $st->fetchAll();
    }
    return $list;
}

/** الشركة المفتوحة دلوقتي. دايماً من ضمن شركات المستخدم، فمستحيل يشوف شركة مش بتاعته. */
function current_workspace(): ?array
{
    $list = user_workspaces();
    if (!$list) {
        return null;
    }
    $id = (int)($_SESSION['ws'] ?? 0);
    foreach ($list as $w) {
        if ((int)$w['id'] === $id) {
            return $w;
        }
    }
    $_SESSION['ws'] = (int)$list[0]['id'];
    return $list[0];
}

function is_ws_owner(): bool
{
    return ws('my_role') === 'owner';
}

/** الدعوات اللي مستنية رد المستخدم */
function pending_invites(): array
{
    $u = current_user();
    if (!$u) {
        return [];
    }
    $st = db()->prepare("SELECT w.id, w.name, wu.role, inv.name AS invited_by_name FROM workspace_users wu
        JOIN workspaces w ON w.id = wu.workspace_id
        LEFT JOIN users inv ON inv.id = wu.invited_by
        WHERE wu.user_id = ? AND wu.status = 'pending' ORDER BY wu.created_at");
    $st->execute([$u['id']]);
    return $st->fetchAll();
}

/** دعوات بالإيميل اتعملت قبل ما الشخص يسجل: تتحول لإشعارات أول ما يعمل حساب */
function claim_email_invites(int $userId, string $email): void
{
    $pdo = db();
    $st = $pdo->prepare("SELECT * FROM workspace_invites WHERE email = ? AND expires_at > NOW()");
    $st->execute([strtolower($email)]);
    foreach ($st->fetchAll() as $inv) {
        $pdo->prepare("INSERT IGNORE INTO workspace_users (workspace_id, user_id, role, status, invited_by) VALUES (?, ?, ?, 'pending', ?)")
            ->execute([$inv['workspace_id'], $userId, $inv['role'], $inv['created_by']]);
    }
    $pdo->prepare("DELETE FROM workspace_invites WHERE email = ?")->execute([strtolower($email)]);
}

function owners_count(int $workspaceId): int
{
    $st = db()->prepare("SELECT COUNT(*) FROM workspace_users WHERE workspace_id = ? AND role = 'owner' AND status = 'active'");
    $st->execute([$workspaceId]);
    return (int)$st->fetchColumn();
}

function role_label(string $r): string
{
    return $r === 'owner' ? t('صاحب الشركة') : t('محرر');
}

function ws(string $field)
{
    $w = current_workspace();
    return $w[$field] ?? null;
}

/* ---------- مساعدات ---------- */

function base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function url(string $page, array $params = []): string
{
    $params = array_merge(['p' => $page], $params);
    return 'index.php?' . http_build_query($params);
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function site_timezone(): string
{
    $tz = (string)(ws('timezone') ?: 'Africa/Cairo');
    return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'Africa/Cairo';
}

/** يعرض تاريخ من Zernio (UTC) بتوقيت الموقع */
function fmt_date(?string $iso, string $format = 'Y-m-d h:i A'): string
{
    if (!$iso) {
        return '—';
    }
    try {
        $d = new DateTime($iso);
        $d->setTimezone(new DateTimeZone(site_timezone()));
        return $d->format($format);
    } catch (Throwable $t) {
        return e($iso);
    }
}

function platform_label(string $p): string
{
    $map = [
        'twitter' => t('X (تويتر)'), 'instagram' => t('انستجرام'), 'facebook' => t('فيسبوك'),
        'linkedin' => t('لينكدإن'), 'tiktok' => t('تيك توك'), 'youtube' => t('يوتيوب'),
        'pinterest' => t('بنترست'), 'reddit' => t('ريديت'), 'bluesky' => t('بلوسكاي'),
        'threads' => t('ثريدز'), 'googlebusiness' => t('جوجل بيزنس'), 'snapchat' => t('سناب شات'),
        'whatsapp' => t('واتساب'), 'telegram' => t('تليجرام'), 'discord' => t('ديسكورد'),
    ];
    return $map[$p] ?? $p;
}

function status_label(string $s): array
{
    $map = [
        'draft' => [t('مسودة'), 'bg-gray-100 text-gray-700'],
        'pending_approval' => [t('في انتظار الموافقة'), 'bg-amber-100 text-amber-800'],
        'scheduled' => [t('مجدول'), 'bg-blue-100 text-blue-800'],
        'publishing' => [t('جاري النشر'), 'bg-indigo-100 text-indigo-800'],
        'published' => [t('تم النشر'), 'bg-green-100 text-green-800'],
        'failed' => [t('فشل'), 'bg-red-100 text-red-800'],
        'partial' => [t('نُشر جزئياً'), 'bg-orange-100 text-orange-800'],
        'cancelled' => [t('ملغي'), 'bg-gray-100 text-gray-600'],
    ];
    return $map[$s] ?? [$s, 'bg-gray-100 text-gray-700'];
}
