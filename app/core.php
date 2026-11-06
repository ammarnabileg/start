<?php
/**
 * الأساسيات: الاتصال بقاعدة البيانات، الجلسة، الحماية، الإعدادات.
 */

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/net.php';
require_once __DIR__ . '/connectors.php';
require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/platforms.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/countries.php';
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
    ksort($all, SORT_STRING); // دايمًا بالترتيب (001، 002، …) حتى لو اتكتبوا في الملف بترتيب تاني
    $ran = [];
    foreach ($all as $name => $statements) {
        if (in_array($name, $done, true)) {
            continue;
        }
        foreach ($statements as $sql) {
            if ($sql instanceof Closure) {
                $sql($pdo); // تحديث محتاج كود PHP (زي تشفير البيانات)
            } else {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    // لو تحديث وقف في النص قبل كده: العمود/الفهرس/الجدول موجود خلاص، فنكمّل بدل ما نقف
                    $code = (int)($e->errorInfo[1] ?? 0);
                    if (!in_array($code, [1060, 1061, 1050, 1091], true)) throw $e;
                }
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
    $p = ui_icon_paths();
    $name = $alias[$name] ?? $name;
    return '<svg class="' . $cls . ' shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="' . ($p[$name] ?? $p['chart']) . '"/></svg>';
}
/** نفس أيقونات القايمة الجانبية (خطوط 1.6). بتتبعت للـ JS كمان عن طريق window.UIC */
function ui_icon_paths(): array
{
    static $p = [
        'fire' => 'M12 21a7 7 0 007-7c0-4-3-6-4-9-1 2-2 3-3 3 0-2-1-4-3-5 0 4-4 6-4 11a7 7 0 007 7zM12 21a3 3 0 003-3c0-2-1.5-3-3-5-1.5 2-3 3-3 5a3 3 0 003 3z',
        'snow' => 'M12 2v20M4.9 6.5l14.2 11M19.1 6.5L4.9 17.5M9 4l3 2 3-2M9 20l3-2 3 2',
        'trophy' => 'M8 4h8v5a4 4 0 01-8 0zM8 6H5a3 3 0 003 4M16 6h3a3 3 0 01-3 4M12 13v4M8 21h8M9 17h6',
        'play' => 'M7 4l13 8-13 8z',
        'gear' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 00-2-1.2L14 3h-4l-.6 2.6a7 7 0 00-2 1.2l-2.3-.9-2 3.4 2 1.5A7 7 0 005 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-.9a7 7 0 002 1.2L10 21h4l.6-2.6a7 7 0 002-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z',
        'plus' => 'M12 5v14M5 12h14',
        'user' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21a8 8 0 0116 0',
        'user_plus' => 'M10 12a4 4 0 100-8 4 4 0 000 8zM2 21a8 8 0 0113-6.3M19 14v6M16 17h6',
        'inbox' => 'M3 13l3-8h12l3 8v6H3zM3 13h5l1 3h6l1-3h5',
        'mail' => 'M3 6h18v12H3zM3 7l9 7 9-7',
        'star' => 'M12 3l2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z',
        'file' => 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6',
        'list' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
        'grid' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'kanban' => 'M4 4h4v16H4zM10 4h4v10h-4zM16 4h4v7h-4z',
        'filter' => 'M3 5h18l-7 8v6l-4 2v-8z',
        'sort' => 'M7 4v16M3 8l4-4 4 4M17 20V4M13 16l4 4 4-4',
        'refresh' => 'M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4',
        'link' => 'M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1',
        'mic' => 'M12 3a3 3 0 00-3 3v6a3 3 0 006 0V6a3 3 0 00-3-3zM5 11a7 7 0 0014 0M12 18v3',
        'download' => 'M12 4v12M7 11l5 5 5-5M4 20h16',
        'upload' => 'M12 20V8M7 13l5-5 5 5M4 4h16',
        'magnet' => 'M6 3v8a6 6 0 0012 0V3h-4v8a2 2 0 01-4 0V3zM6 7h4M14 7h4',
        'pin' => 'M12 21s-7-6.2-7-12a7 7 0 0114 0c0 5.8-7 12-7 12zM12 11a2 2 0 100-4 2 2 0 000 4z',
        'route' => 'M6 19a2 2 0 100-4 2 2 0 000 4zM18 9a2 2 0 100-4 2 2 0 000 4zM6 15V9a4 4 0 014-4h6M18 9v6a4 4 0 01-4 4H8',
        'eye' => 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z',
        'click' => 'M9 9l11 4-5 2-2 5zM4 4l2.5 2.5M9 2v3M2 9h3',
        'heart' => 'M12 20s-8-5-8-11a4.5 4.5 0 018-2.8A4.5 4.5 0 0120 9c0 6-8 11-8 11z',
        'hash' => 'M5 9h15M4 15h15M10 3L8 21M16 3l-2 18',
        'flask' => 'M9 3h6M10 3v6L4 19a1 1 0 001 2h14a1 1 0 001-2l-6-10V3M7 15h10',
        'layers' => 'M12 3l9 5-9 5-9-5zM3 13l9 5 9-5M3 17l9 5 9-5',
        'chevron' => 'M9 6l6 6-6 6',
        'dots' => 'M12 6h.01M12 12h.01M12 18h.01',
        'wallet' => 'M3 7a2 2 0 012-2h13v4M3 7v10a2 2 0 002 2h15v-5M3 7h17v5h-4a2.5 2.5 0 000 5h4',
        'new' => 'M12 3l1.5 3.5L17 8l-3.5 1.5L12 13l-1.5-3.5L7 8l3.5-1.5zM5 15l.8 2.2L8 18l-2.2.8L5 21l-.8-2.2L2 18l2.2-.8zM19 13l.7 1.8 1.8.7-1.8.7L19 18l-.7-1.8-1.8-.7 1.8-.7z',
        'folder' => 'M3 6a1 1 0 011-1h5l2 2h9a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1z',
        'image' => 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M15 9.5h.01',
        'camera' => 'M4 8h3l2-3h6l2 3h3v11H4zM12 17a4 4 0 100-8 4 4 0 000 8z',
        'key' => 'M14 10a4 4 0 11-1.2-2.8L21 15v3h-3v-2h-2v-2h-2l-1.2-1.2A4 4 0 0114 10zM8.5 9.5h.01',
        'sun' => 'M12 17a5 5 0 100-10 5 5 0 000 10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
        'scissors' => 'M6 9a3 3 0 100-6 3 3 0 000 6zM6 21a3 3 0 100-6 3 3 0 000 6zM20 4L8.1 15.9M14.5 14.5L20 20M8.1 8.1L12 12',
        'palette' => 'M12 21a9 9 0 110-18c5 0 9 3.6 9 8 0 2.5-2 4-4.5 4H15a2 2 0 00-1.4 3.4A1.5 1.5 0 0112 21zM7.5 11h.01M10 7.5h.01M14.5 7.5h.01M17 11h.01',
        'sliders' => 'M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0M16 4v4M8 10v4M16 16v4',
        'id' => 'M3 5h18v14H3zM8 12a2 2 0 100-4 2 2 0 000 4zM5 16a3 3 0 016 0M14 9h4M14 13h4',
        'pencil' => 'M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4',
        'trash' => 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6',
        'copy' => 'M8 8h11v12H8zM5 16V4h11',
        'pause' => 'M8 5v14M16 5v14',
        'ban' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM5.6 5.6l12.8 12.8',
        'share' => 'M14 5h5v5M19 5l-8 8M18 14v5H5V6h5',
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
    return $p;
}
