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
        'site_tagline'     => 'منصة الحضور العربي الموثق',
        'site_description' => 'تحكم بما يعرفه الناس عنك',
        'site_keywords'    => 'شخصيات عربية, مؤسسات, من هم, PioneerIcons',
        'site_logo'        => '',
        'footer_about'     => 'منصة الحضور العربي الموثق — تحكم بما يعرفه الناس عنك. نوثق الشخصيات والمؤسسات العربية الرائدة في مختلف المجالات.',
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
]);

function pi_has_perm($perm_key) {
    global $pi_user_permissions;
    $perm_id = PERM[$perm_key] ?? 0;
    return in_array($perm_id, $pi_user_permissions ?? []);
}

function pi_require_perm($perm_key) {
    if (!pi_has_perm($perm_key)) {
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

    $page    = pi_escape(mb_substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', 0, 200));
    $ip      = pi_escape($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $ip      = pi_escape(explode(',', $ip)[0]);
    $uid     = !empty($_SESSION['pi_user_id']) ? (int)$_SESSION['pi_user_id'] : 'NULL';
    $ref     = pi_escape(mb_substr($_SERVER['HTTP_REFERER'] ?? '', 0, 400));

    $mysqli->query("INSERT INTO pi_visits (v_page,v_ip,v_user_id,v_ref) VALUES ('$page','$ip',$uid,'$ref')");
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
// Add user tracking columns if missing
$mysqli->query("ALTER TABLE pi_personalities ADD COLUMN IF NOT EXISTS p_added_by_user INT DEFAULT NULL");
$mysqli->query("ALTER TABLE pi_institutions ADD COLUMN IF NOT EXISTS inst_added_by_user INT DEFAULT NULL");

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
        list_columns JSON,
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
        li_data JSON,
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

// ── Lists: add sponsor + spotlight columns if missing ──────────────────────
$mysqli->query("ALTER TABLE pi_lists ADD COLUMN IF NOT EXISTS list_sponsor_id INT DEFAULT NULL");
$mysqli->query("ALTER TABLE pi_lists ADD COLUMN IF NOT EXISTS list_sponsor_img VARCHAR(500) DEFAULT ''");
$mysqli->query("ALTER TABLE pi_lists ADD COLUMN IF NOT EXISTS list_sponsor_url VARCHAR(500) DEFAULT ''");
$mysqli->query("ALTER TABLE pi_lists ADD COLUMN IF NOT EXISTS list_sponsor_name VARCHAR(300) DEFAULT ''");
$mysqli->query("ALTER TABLE pi_lists ADD COLUMN IF NOT EXISTS list_spotlight JSON");
$mysqli->query("ALTER TABLE pi_lists ADD COLUMN IF NOT EXISTS list_criteria TEXT DEFAULT ''");
