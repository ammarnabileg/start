<?php
error_reporting(E_ALL ^ E_NOTICE);
if (session_status() === PHP_SESSION_NONE) session_start();

$server   = "localhost";
$username = "Abouut_user";
$password = 'hGeFvm0o#Sgjd9_3';
$database = "admin_abouut";

$mysqli = new mysqli($server, $username, $password, $database) or die(mysqli_error($mysqli));
$mysqli->set_charset("utf8mb4");

// ── Site settings (loaded from DB, with fallbacks) ─────────────────────────
function pi_get_settings() {
    global $mysqli;
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [
        'site_name'        => 'PioneerIcons',
        'site_name_ar'     => 'من هم',
        'hero_pill'        => 'المنصة العربية الأولى للحضور الموثق',
        'site_tagline'     => 'السجل العربي الموثق للشخصيات والمؤسسات المؤثرة',
        'site_description' => 'حيث يجد العالم من يبحث عنه في العالم العربي',
        'site_keywords'    => 'شخصيات عربية, مؤسسات, من هم, PioneerIcons',
        'site_logo'        => '',
        'footer_about'     => 'السجل العربي الأول للشخصيات والمؤسسات المؤثرة. وثّقنا آلاف الملفات التي تُعتمد يومياً في الإعلام والأعمال والبحث والتحقق.',
        'social_whatsapp'  => '',
        'social_linkedin'  => '',
        'social_twitter'   => '',
        'primary_color'    => '#8829C8',
        'admin_email'      => 'admin@pioneericons.com',
        'copyright_text'   => 'جميع الحقوق محفوظة لـ PioneerIcons',
        'google_analytics' => '',
        'default_country'  => '0',
    ];
    $r = $mysqli->query("SELECT * FROM pi_settings");
    if ($r) while ($row = $r->fetch_assoc()) $cache[$row['s_key']] = $row['s_value'];
    return $cache;
}

function pi_setting($key, $fallback = '') {
    $s = pi_get_settings();
    return $s[$key] ?? $fallback;
}

// ── Country helpers ────────────────────────────────────────────────────────
function pi_get_countries() {
    global $mysqli;
    // Auto-seed all countries if table has fewer than 20 rows
    $cnt_r = $mysqli->query("SELECT COUNT(*) c FROM pi_countries");
    if ($cnt_r && (int)$cnt_r->fetch_assoc()['c'] < 20) {
        $mysqli->query("INSERT IGNORE INTO `pi_countries` (`c_id`,`c_name`,`c_flag`,`c_code`,`c_active`,`c_order`) VALUES
          (1,'السعودية','🇸🇦','sa',1,1),(2,'مصر','🇪🇬','eg',1,2),(3,'الإمارات','🇦🇪','ae',1,3),
          (4,'الكويت','🇰🇼','kw',1,4),(5,'البحرين','🇧🇭','bh',1,5),(6,'عمان','🇴🇲','om',1,6),
          (7,'قطر','🇶🇦','qa',1,7),(8,'سوريا','🇸🇾','sy',1,8),(9,'العراق','🇮🇶','iq',1,9),
          (10,'الأردن','🇯🇴','jo',1,10),(11,'لبنان','🇱🇧','lb',1,11),(12,'المغرب','🇲🇦','ma',1,12),
          (13,'تونس','🇹🇳','tn',1,13),(14,'الجزائر','🇩🇿','dz',1,14),(15,'ليبيا','🇱🇾','ly',1,15),
          (16,'اليمن','🇾🇪','ye',1,16),(17,'السودان','🇸🇩','sd',1,17),(18,'فلسطين','🇵🇸','ps',1,18),
          (19,'موريتانيا','🇲🇷','mr',1,19),(20,'الصومال','🇸🇴','so',1,20),(21,'جيبوتي','🇩🇯','dj',1,21),
          (22,'جزر القمر','🇰🇲','km',1,22),(23,'تركيا','🇹🇷','tr',1,30),(24,'باكستان','🇵🇰','pk',1,31),
          (25,'إيران','🇮🇷','ir',1,32),(26,'أفغانستان','🇦🇫','af',1,33),(27,'المملكة المتحدة','🇬🇧','gb',1,40),
          (28,'الولايات المتحدة','🇺🇸','us',1,41),(29,'فرنسا','🇫🇷','fr',1,42),(30,'ألمانيا','🇩🇪','de',1,43),
          (31,'كندا','🇨🇦','ca',1,44),(32,'أستراليا','🇦🇺','au',1,45)");
    }
    $r = $mysqli->query("SELECT * FROM pi_countries WHERE c_active=1 ORDER BY c_order,c_id");
    $c = [];
    if ($r) while ($row=$r->fetch_assoc()) $c[] = $row;
    return $c;
}

function pi_current_country() {
    // Returns the active country_id (0 = all countries)
    if (isset($_GET['country'])) {
        $_SESSION['pi_country'] = (int)$_GET['country'];
        return (int)$_GET['country'];
    }
    if (isset($_SESSION['pi_country'])) {
        return (int)$_SESSION['pi_country'];
    }
    // Fall back to admin default country setting
    $s = pi_get_settings();
    return (int)($s['default_country'] ?? 0);
}

function pi_country_where($table_prefix = 'p') {
    // Returns SQL fragment to filter by country (empty string = no filter)
    $cid = pi_current_country();
    if (!$cid) return '';
    return " AND {$table_prefix}.p_country_id = $cid ";
}

function pi_inst_country_where() {
    $cid = pi_current_country();
    if (!$cid) return '';
    return " AND inst_country_id = $cid ";
}

// ── Permissions ────────────────────────────────────────────────────────────
define('PERM', [
    'view_personalities'  => 1,
    'add_personality'     => 2,
    'edit_personality'    => 3,
    'delete_personality'  => 4,
    'view_institutions'   => 5,
    'add_institution'     => 6,
    'edit_institution'    => 7,
    'delete_institution'  => 8,
    'view_categories'     => 9,
    'add_category'        => 10,
    'edit_category'       => 11,
    'delete_category'     => 12,
    'view_articles'       => 13,
    'add_article'         => 14,
    'edit_article'        => 15,
    'delete_article'      => 16,
    'view_roles'          => 17,
    'add_role'            => 18,
    'edit_role'           => 19,
    'delete_role'         => 20,
    'view_admin_users'    => 21,
    'add_admin_user'      => 22,
    'edit_admin_user'     => 23,
    'delete_admin_user'   => 24,
    'view_sponsors'       => 25,
    'manage_sponsors'     => 26,
    'view_timeline'       => 27,
    'manage_timeline'     => 28,
    'manage_countries'    => 29,
    'manage_settings'     => 30,
    'manage_users'        => 31,
    'manage_advertise'    => 32,
    'manage_memberships'  => 33,
    'manage_complaints'   => 34,
    'manage_submissions'  => 35,
    'manage_edit_requests'=> 36,
    'manage_lists'        => 37,
    // Granular additions
    'add_sponsor'         => 38,
    'edit_sponsor'        => 39,
    'delete_sponsor'      => 40,
    'add_timeline'        => 41,
    'edit_timeline'       => 42,
    'delete_timeline'     => 43,
    'view_countries'      => 44,
    'add_country'         => 45,
    'edit_country'        => 46,
    'delete_country'      => 47,
    'view_labels'         => 48,
    'add_label'           => 49,
    'edit_label'          => 50,
    'delete_label'        => 51,
    'view_lists'          => 52,
    'add_list'            => 53,
    'edit_list'           => 54,
    'delete_list'         => 55,
    'view_users'          => 56,
    'view_advertise'      => 57,
    'view_memberships'    => 58,
    'view_complaints'     => 59,
    'view_submissions'    => 60,
    'view_edit_requests'  => 61,
]);

function pi_has_perm($perm_key) {
    global $pi_user_permissions;
    $perm_id = PERM[$perm_key] ?? 0;
    return in_array($perm_id, $pi_user_permissions ?? []);
}

// Returns true if user has ANY of the given permission keys
function pi_has_any_perm(...$keys) {
    foreach ($keys as $k) { if (pi_has_perm($k)) return true; }
    return false;
}

function pi_require_perm($perm_key) {
    if (!pi_has_perm($perm_key)) {
        header('Location: admin.php?p=dashboard');
        exit;
    }
}

function pi_require_any_perm(...$keys) {
    if (!pi_has_any_perm(...$keys)) {
        header('Location: admin.php?p=dashboard');
        exit;
    }
}

function pi_require_login() {
    if (empty($_SESSION['pi_admin_id'])) {
        header('Location: admin.php?p=login');
        exit;
    }
}

function pi_load_user() {
    global $mysqli, $pi_user, $pi_user_permissions;
    if (!empty($_SESSION['pi_admin_id'])) {
        $id = (int)$_SESSION['pi_admin_id'];
        $r  = $mysqli->query("SELECT au.*, r.role_permissions FROM pi_admin_users au LEFT JOIN pi_roles r ON au.au_role_id=r.role_id WHERE au.au_id=$id AND au.au_active=1");
        if ($r && $r->num_rows) {
            $pi_user = $r->fetch_assoc();
            $pi_user_permissions = array_map('intval', explode(',', $pi_user['role_permissions'] ?? ''));
        } else {
            session_destroy();
            header('Location: admin.php?p=login');
            exit;
        }
    }
}

function pi_count_personalities() {
    global $mysqli;
    $cid = pi_current_country();
    $where = $cid ? " AND p_country_id=$cid" : '';
    $r = $mysqli->query("SELECT COUNT(*) as c FROM pi_personalities WHERE p_active=1$where");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

function pi_count_institutions() {
    global $mysqli;
    $cid = pi_current_country();
    $where = $cid ? " AND inst_country_id=$cid" : '';
    $r = $mysqli->query("SELECT COUNT(*) as c FROM pi_institutions WHERE inst_active=1$where");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

function pi_escape($str) {
    global $mysqli;
    return $mysqli->real_escape_string($str);
}

// ── Visit tracking ─────────────────────────────────────────────────────────
function pi_track_visit() {
    global $mysqli;
    // Skip admin, bots, CLI
    if (defined('DOING_ADMIN') || php_sapi_name() === 'cli') return;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/bot|crawl|spider|slurp|mediapartners|facebookexternalhit/i', $ua)) return;

    // Ensure pi_visits table still exists (backward compat, no longer inserted into)
    $mysqli->query("CREATE TABLE IF NOT EXISTS pi_visits (
        v_id INT AUTO_INCREMENT PRIMARY KEY,
        v_page VARCHAR(255),
        v_ip VARCHAR(45),
        v_user_id INT DEFAULT NULL,
        v_ref VARCHAR(500),
        v_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (v_created),
        INDEX idx_page (v_page(50))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure aggregated daily table exists
    $mysqli->query("CREATE TABLE IF NOT EXISTS pi_visit_daily (
        vd_page VARCHAR(255) NOT NULL,
        vd_date DATE NOT NULL,
        vd_count INT DEFAULT 1,
        PRIMARY KEY (vd_page(100), vd_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Unique visitors table (IP per day)
    $mysqli->query("CREATE TABLE IF NOT EXISTS pi_unique_daily (
        ud_ip   VARCHAR(45) NOT NULL,
        ud_date DATE        NOT NULL,
        PRIMARY KEY (ud_ip(45), ud_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $page = mb_substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', 0, 200);

    // Session-based dedup: only count if not visited in last 30 minutes
    $sess_key = 'pi_visited_' . md5($page);
    $now = time();
    if (!empty($_SESSION[$sess_key]) && ($now - $_SESSION[$sess_key]) < 1800) {
        return; // already counted recently
    }
    $_SESSION[$sess_key] = $now;

    $page_esc = pi_escape($page);
    $mysqli->query("INSERT INTO pi_visit_daily (vd_page, vd_date, vd_count) VALUES ('$page_esc', CURDATE(), 1) ON DUPLICATE KEY UPDATE vd_count=vd_count+1");

    // Track unique visitor by IP (INSERT IGNORE keeps one row per IP per day)
    $ip = pi_escape($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $ip = pi_escape(trim(explode(',', $ip)[0]));
    if ($ip) {
        $mysqli->query("INSERT IGNORE INTO pi_unique_daily (ud_ip, ud_date) VALUES ('$ip', CURDATE())");
    }

    // Auto-purge old records with 1% probability
    if (rand(1, 100) === 1) {
        $mysqli->query("DELETE FROM pi_visit_daily WHERE vd_date < DATE_SUB(CURDATE(), INTERVAL 365 DAY)");
        $mysqli->query("DELETE FROM pi_unique_daily WHERE ud_date < DATE_SUB(CURDATE(), INTERVAL 365 DAY)");
    }
}

// ── User auth ──────────────────────────────────────────────────────────────
function pi_create_user_tables() {
    global $mysqli;
    $mysqli->query("CREATE TABLE IF NOT EXISTS pi_users (
        u_id INT AUTO_INCREMENT PRIMARY KEY,
        u_name VARCHAR(200) NOT NULL,
        u_email VARCHAR(200) NOT NULL UNIQUE,
        u_password VARCHAR(255) NOT NULL,
        u_phone VARCHAR(50),
        u_nationality VARCHAR(100),
        u_company VARCHAR(200),
        u_birthdate DATE,
        u_job VARCHAR(200),
        u_gender ENUM('male','female','') DEFAULT '',
        u_photo VARCHAR(500),
        u_plan ENUM('free','verified','executive') DEFAULT 'free',
        u_active TINYINT DEFAULT 1,
        u_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $mysqli->query("CREATE TABLE IF NOT EXISTS pi_complaints (
        cmp_id INT AUTO_INCREMENT PRIMARY KEY,
        cmp_user_id INT DEFAULT NULL,
        cmp_type ENUM('complaint','suggestion','feedback','request') DEFAULT 'complaint',
        cmp_subject VARCHAR(300) NOT NULL,
        cmp_message TEXT NOT NULL,
        cmp_name VARCHAR(200),
        cmp_email VARCHAR(200),
        cmp_status ENUM('new','read','resolved') DEFAULT 'new',
        cmp_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
pi_create_user_tables();
// Add user tracking columns if missing (safe for all MySQL/MariaDB versions)
$cols = $mysqli->query("SHOW COLUMNS FROM pi_personalities LIKE 'p_added_by_user'");
if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_personalities ADD COLUMN p_added_by_user INT DEFAULT NULL");
$cols = $mysqli->query("SHOW COLUMNS FROM pi_institutions LIKE 'inst_added_by_user'");
if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_institutions ADD COLUMN inst_added_by_user INT DEFAULT NULL");
// Ensure cmp_status column exists (may be missing on older installs)
$cols = $mysqli->query("SHOW COLUMNS FROM pi_complaints LIKE 'cmp_status'");
if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_complaints ADD COLUMN cmp_status ENUM('new','read','resolved') DEFAULT 'new'");
// Ensure mem_type column exists in pi_memberships
$cols = $mysqli->query("SHOW TABLES LIKE 'pi_memberships'");
if ($cols && $cols->num_rows) {
    $cols = $mysqli->query("SHOW COLUMNS FROM pi_memberships LIKE 'mem_type'");
    if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_memberships ADD COLUMN mem_type ENUM('verified','executive') DEFAULT 'verified' AFTER mem_id");
}
// Ensure p_bio_platform column exists in pi_personalities (may be missing on older installs)
$cols = $mysqli->query("SHOW COLUMNS FROM pi_personalities LIKE 'p_bio_platform'");
if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_personalities ADD COLUMN p_bio_platform TEXT DEFAULT NULL");
// Ensure p_residence column exists in pi_personalities
$cols = $mysqli->query("SHOW COLUMNS FROM pi_personalities LIKE 'p_residence'");
if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_personalities ADD COLUMN p_residence VARCHAR(100) DEFAULT NULL");
// Ensure inst_membership_type column exists in pi_institutions (used in edit_requests approval)
$cols = $mysqli->query("SHOW TABLES LIKE 'pi_institutions'");
if ($cols && $cols->num_rows) {
    $cols = $mysqli->query("SHOW COLUMNS FROM pi_institutions LIKE 'inst_membership_type'");
    if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_institutions ADD COLUMN inst_membership_type ENUM('standard','verified','executive') DEFAULT 'standard'");
    // Ensure inst_country column exists in pi_institutions (display field)
    $cols = $mysqli->query("SHOW COLUMNS FROM pi_institutions LIKE 'inst_country'");
    if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_institutions ADD COLUMN inst_country VARCHAR(100) DEFAULT NULL");
}
// Ensure li_order and li_custom_data columns exist in pi_list_items
$cols = $mysqli->query("SHOW TABLES LIKE 'pi_list_items'");
if ($cols && $cols->num_rows) {
    $cols = $mysqli->query("SHOW COLUMNS FROM pi_list_items LIKE 'li_order'");
    if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_list_items ADD COLUMN li_order INT DEFAULT 0");
    $cols = $mysqli->query("SHOW COLUMNS FROM pi_list_items LIKE 'li_custom_data'");
    if ($cols && $cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_list_items ADD COLUMN li_custom_data TEXT DEFAULT NULL");
}

function pi_user_logged_in() {
    return !empty($_SESSION['pi_user_id']);
}

function pi_current_user() {
    global $mysqli;
    if (empty($_SESSION['pi_user_id'])) return null;
    static $cache = null;
    if ($cache) return $cache;
    $id = (int)$_SESSION['pi_user_id'];
    $r = $mysqli->query("SELECT * FROM pi_users WHERE u_id=$id AND u_active=1");
    $cache = ($r && $r->num_rows) ? $r->fetch_assoc() : null;
    if (!$cache) { unset($_SESSION['pi_user_id']); }
    return $cache;
}

function pi_require_user() {
    if (!pi_user_logged_in()) {
        header('Location: user_login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function pi_get_categories() {
    global $mysqli;
    $r = $mysqli->query("SELECT c.*, l.label_name, l.label_color FROM pi_categories c LEFT JOIN pi_labels l ON c.cat_label_id=l.label_id WHERE c.cat_active=1 ORDER BY c.cat_order,c.cat_id");
    $cats = [];
    if ($r) while ($row = $r->fetch_assoc()) $cats[] = $row;
    return $cats;
}

// ── Edit requests table ────────────────────────────────────────────────────
$mysqli->query("CREATE TABLE IF NOT EXISTS pi_edit_requests (
    er_id INT AUTO_INCREMENT PRIMARY KEY,
    er_user_id INT NOT NULL,
    er_entity_type ENUM('personality','institution') DEFAULT 'personality',
    er_entity_id INT NOT NULL,
    er_req_type ENUM('edit','upgrade') NOT NULL DEFAULT 'edit',
    er_upgrade_to ENUM('verified','executive','') DEFAULT '',
    er_edit_data TEXT,
    er_notes TEXT,
    er_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    er_admin_note TEXT,
    er_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_er_user (er_user_id),
    INDEX idx_er_status (er_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Add missing columns to pi_edit_requests if table already exists without them
$_er_cols = $mysqli->query("SHOW COLUMNS FROM pi_edit_requests LIKE 'er_req_type'");
if ($_er_cols && $_er_cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_edit_requests ADD COLUMN er_req_type ENUM('edit','upgrade') NOT NULL DEFAULT 'edit'");
$_er_cols = $mysqli->query("SHOW COLUMNS FROM pi_edit_requests LIKE 'er_upgrade_to'");
if ($_er_cols && $_er_cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_edit_requests ADD COLUMN er_upgrade_to ENUM('verified','executive','') DEFAULT ''");
$_er_cols = $mysqli->query("SHOW COLUMNS FROM pi_edit_requests LIKE 'er_notes'");
if ($_er_cols && $_er_cols->num_rows === 0) $mysqli->query("ALTER TABLE pi_edit_requests ADD COLUMN er_notes TEXT");

// ── Lists feature tables ───────────────────────────────────────────────────
function pi_create_list_tables() {
    global $mysqli;
    $mysqli->query("CREATE TABLE IF NOT EXISTS pi_lists (
        list_id INT AUTO_INCREMENT PRIMARY KEY,
        list_title VARCHAR(300) NOT NULL,
        list_title_en VARCHAR(300) DEFAULT '',
        list_slug VARCHAR(200) UNIQUE,
        list_description TEXT,
        list_cover VARCHAR(500) DEFAULT '',
        list_logo VARCHAR(500) DEFAULT '',
        list_year VARCHAR(10) DEFAULT '',
        list_columns TEXT,
        list_active TINYINT DEFAULT 1,
        list_order INT DEFAULT 0,
        list_views INT DEFAULT 0,
        list_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_active (list_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $mysqli->query("CREATE TABLE IF NOT EXISTS pi_list_items (
        li_id INT AUTO_INCREMENT PRIMARY KEY,
        li_list_id INT NOT NULL,
        li_entity_type ENUM('personality','institution') NOT NULL,
        li_entity_id INT NOT NULL,
        li_rank INT DEFAULT 0,
        li_data TEXT,
        li_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_list (li_list_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $mysqli->query("CREATE TABLE IF NOT EXISTS pi_list_blocks (
        lb_id INT AUTO_INCREMENT PRIMARY KEY,
        lb_list_id INT NOT NULL,
        lb_type ENUM('text','image','video') DEFAULT 'text',
        lb_content LONGTEXT,
        lb_order INT DEFAULT 0,
        INDEX idx_list (lb_list_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
pi_create_list_tables();

// ── Add sp_user_id and sp_views to pi_sponsors if missing ─────────────────
$_sc = $mysqli->query("SHOW COLUMNS FROM pi_sponsors LIKE 'sp_user_id'");
if ($_sc && $_sc->num_rows === 0) $mysqli->query("ALTER TABLE pi_sponsors ADD COLUMN sp_user_id INT DEFAULT NULL");
$_sc = $mysqli->query("SHOW COLUMNS FROM pi_sponsors LIKE 'sp_views'");
if ($_sc && $_sc->num_rows === 0) $mysqli->query("ALTER TABLE pi_sponsors ADD COLUMN sp_views INT DEFAULT 0");

// ── Lists: add missing columns one-by-one (safe for all versions) ─────────
$_lc = function($col, $def) use ($mysqli) {
    $r = $mysqli->query("SHOW COLUMNS FROM pi_lists LIKE '$col'");
    if ($r && $r->num_rows === 0) $mysqli->query("ALTER TABLE pi_lists ADD COLUMN $col $def");
};
$_lc('list_sponsor_id',  'INT DEFAULT NULL');
$_lc('list_sponsor_img', "VARCHAR(500) DEFAULT ''");
$_lc('list_sponsor_url', "VARCHAR(500) DEFAULT ''");
$_lc('list_sponsor_name',"VARCHAR(300) DEFAULT ''");
$_lc('list_spotlight',   'TEXT DEFAULT NULL');
$_lc('list_criteria',    'TEXT DEFAULT NULL');
unset($_lc);
