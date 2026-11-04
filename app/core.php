<?php
/**
 * الأساسيات: الاتصال بقاعدة البيانات، الجلسة، الحماية، الإعدادات.
 */

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/connectors.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/platforms.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/ai.php';
require_once __DIR__ . '/ai_presets.php';
require_once __DIR__ . '/ai_context.php';
require_once __DIR__ . '/ai_jobs.php';
require_once __DIR__ . '/ai_kb.php';
require_once __DIR__ . '/campaigns.php';
require_once __DIR__ . '/inbox_team.php';
require_once __DIR__ . '/views/ai_settings.php';
require_once __DIR__ . '/mailer.php';

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
    // متخزنة في الذاكرة طول الطلب، وsave_setting بيحدثها عشان القيمة الجديدة تتقري فوراً
    if (!isset($GLOBALS['__settings'])) {
        $GLOBALS['__settings'] = [];
        foreach (db()->query("SELECT name, value FROM settings") as $row) {
            $GLOBALS['__settings'][$row['name']] = $row['value'];
        }
    }
    return array_key_exists($name, $GLOBALS['__settings']) ? $GLOBALS['__settings'][$name] : $default;
}

function save_setting(string $name, ?string $value, ?PDO $pdo = null): void
{
    $pdo = $pdo ?: db();
    $st = $pdo->prepare("INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    $st->execute([$name, $value]);
    if (isset($GLOBALS['__settings'])) $GLOBALS['__settings'][$name] = $value;
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
            $st = db()->prepare("SELECT * FROM users WHERE id = ?");
            $st->execute([$_SESSION['uid']]);
            $user = $st->fetch() ?: null;
            if ($user) {
                unset($user['password_hash']);
                // حساب موقوف: يخرج فوراً
                if (($user['status'] ?? 'active') !== 'active') {
                    $user = null;
                    unset($_SESSION['uid'], $_SESSION['impersonator']);
                }
            }
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
            WHERE wu.user_id = ? AND wu.status = 'active'" . (is_admin() ? '' : " AND COALESCE(w.status, 'active') = 'active'") . " ORDER BY w.name");
        $st->execute([$u['id']]);
        $list = $st->fetchAll();
    }
    return $list;
}

/** الشركة المفتوحة دلوقتي. دايماً من ضمن شركات المستخدم، فمستحيل يشوف شركة مش بتاعته. */
function current_workspace(): ?array
{
    // التشغيل الدوري (cron) بيشتغل باسم شركة معينة من غير مستخدم
    if (isset($GLOBALS['__ws_override'])) return $GLOBALS['__ws_override'];
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
    $o = function_exists('role_options') ? role_options() : [];
    return $o[$r] ?? ($r === 'owner' ? t('صاحب الشركة') : t('محرر'));
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

/** الصفحة مفتوحة جوه نافذة منبثقة (popup) */
function is_embed(): bool
{
    return !empty($_GET['embed']);
}

function url(string $page, array $params = []): string
{
    $params = array_merge(['p' => $page], $params);
    if (is_embed() && !isset($params['embed'])) {
        $params['embed'] = 1; // الروابط جوه النافذة المنبثقة تفضل جواها
    }
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
        'slack' => 'Slack', 'metaads' => 'Meta Ads', 'googleads' => 'Google Ads', 'linkedinads' => 'LinkedIn Ads', 'tiktokads' => 'TikTok Ads',
        'xads' => 'X Ads', 'pinterestads' => 'Pinterest Ads', 'redditads' => 'Reddit Ads', 'microsoftads' => 'Microsoft Ads', 'openaiads' => 'OpenAI Ads',
        'shopify' => 'Shopify', 'wordpress' => 'WordPress', 'wix' => 'Wix', 'webflow' => 'Webflow', 'hubspot' => 'HubSpot',
        'googleanalytics' => 'Google Analytics', 'google' => t('جوجل (بريد وتقويم)'), 'microsoft' => t('مايكروسوفت (بريد وتقويم)'),
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

/**
 * أيقونات الموقع بنفس ستايل السايد بار (خط رفيع 1.6). بتقبل اسم أو إيموجي قديم (بيتحول لأيقونة مرسومة).
 */
function ui_icon(string $name, string $cls = 'w-5 h-5'): string
{
    static $alias = ['📊' => 'chart', '👥' => 'users', '⏱️' => 'timer', '⏱' => 'timer', '⏳' => 'timer', '🕒' => 'clock', '💬' => 'chat', '📣' => 'megaphone',
        '🔔' => 'bell', '🚀' => 'trend_up', '📉' => 'trend_down', '🔌' => 'plug', '⚡' => 'zap', '🏷️' => 'tag', '🏷' => 'tag', '👍' => 'thumb', '🔎' => 'search', '🔍' => 'search',
        '🏢' => 'building', '📅' => 'calendar', '💡' => 'bulb', '🧩' => 'puzzle', '💰' => 'money', '🎬' => 'film', '🎯' => 'target', '☎' => 'phone', '⚠️' => 'alert', '⚠' => 'alert',
        '✅' => 'check', '❌' => 'x', '👌' => 'check'];
    static $p = [
        'chart' => 'M4 20V10M10 20V4M16 20v-8M21 20H3',
        'users' => 'M9 11a4 4 0 100-8 4 4 0 000 8zM2 21v-1a6 6 0 0112 0v1M16 3.5a4 4 0 010 7.5M22 21v-1a6 6 0 00-4-5.6',
        'timer' => 'M12 21a8 8 0 100-16 8 8 0 000 16zM12 9v4l2 2M10 2h4M12 2v3',
        'clock' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2',
        'chat' => 'M4 5h16v11H9l-5 4z',
        'megaphone' => 'M3 11v2a1 1 0 001 1h2l5 4V6L6 10H4a1 1 0 00-1 1zM15 8a5 5 0 010 8M18 5a9 9 0 010 14',
        'bell' => 'M6 16V11a6 6 0 1112 0v5l2 2H4zM10 21h4',
        'trend_up' => 'M3 17l6-6 4 4 8-8M15 7h6v6',
        'trend_down' => 'M3 7l6 6 4-4 8 8M15 17h6v-6',
        'plug' => 'M9 3v5M15 3v5M6 8h12v3a6 6 0 01-12 0zM12 17v4',
        'zap' => 'M13 2L4 14h7l-1 8 9-12h-7z',
        'tag' => 'M3 12V4h8l10 10-8 8zM7.5 8.5h.01',
        'thumb' => 'M7 10v11H4V10zM7 10l4-7a2 2 0 012 2v4h6a2 2 0 012 2.3l-1.2 7A2 2 0 0117.8 21H7',
        'search' => 'M11 18a7 7 0 100-14 7 7 0 000 14zM20 20l-4-4',
        'building' => 'M4 21V5a1 1 0 011-1h9a1 1 0 011 1v16M15 9h4a1 1 0 011 1v11M3 21h18M8 8h3M8 12h3M8 16h3',
        'calendar' => 'M4 6a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1zM4 10h16M8 3v4M16 3v4',
        'bulb' => 'M9 18h6M10 21h4M12 3a6 6 0 00-4 10.5c.7.7 1 1.5 1 2.5h6c0-1 .3-1.8 1-2.5A6 6 0 0012 3z',
        'puzzle' => 'M4 4h6v6H4zM14 14h6v6h-6zM10 7h4a3 3 0 013 3v4M7 10v4a3 3 0 003 3h4',
        'money' => 'M3 7a2 2 0 012-2h13v4M3 7v10a2 2 0 002 2h15v-5M3 7h17v5h-4a2.5 2.5 0 000 5h4',
        'film' => 'M4 5h16v14H4zM8 5v14M16 5v14M4 9h4M4 15h4M16 9h4M16 15h4',
        'target' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z',
        'phone' => 'M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a1 1 0 01-1 1A16 16 0 014 5a1 1 0 011-1z',
        'alert' => 'M12 4l9 16H3zM12 10v4M12 17.5v.01',
        'check' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM8 12l3 3 5-6',
        'x' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM9 9l6 6M15 9l-6 6',
        'spark' => 'M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z',
    ];
    $name = $alias[$name] ?? $name;
    return '<svg class="' . $cls . ' shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="' . ($p[$name] ?? $p['chart']) . '"/></svg>';
}
