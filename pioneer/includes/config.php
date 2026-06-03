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

function pi_get_categories() {
    global $mysqli;
    $r = $mysqli->query("SELECT c.*, l.label_name, l.label_color FROM pi_categories c LEFT JOIN pi_labels l ON c.cat_label_id=l.label_id WHERE c.cat_active=1 ORDER BY c.cat_order,c.cat_id");
    $cats = [];
    if ($r) while ($row = $r->fetch_assoc()) $cats[] = $row;
    return $cats;
}
