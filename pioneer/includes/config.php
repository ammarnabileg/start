<?php
error_reporting(E_ALL ^ E_NOTICE);
session_start();

$server   = "localhost";
$username = "Start_Main";
$password = 'dacZ4350$';
$database = "Start_Main";

$mysqli = new mysqli($server, $username, $password, $database) or die(mysqli_error($mysqli));
$mysqli->set_charset("utf8mb4");

define('BASE_URL', '/pioneer');
define('SITE_NAME', 'PioneerIcons');
define('SITE_NAME_AR', 'من هم');

// Permissions map
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
    $r = $mysqli->query("SELECT COUNT(*) as c FROM pi_personalities WHERE p_active=1");
    return $r ? $r->fetch_assoc()['c'] : 0;
}

function pi_count_institutions() {
    global $mysqli;
    $r = $mysqli->query("SELECT COUNT(*) as c FROM pi_institutions WHERE inst_active=1");
    return $r ? $r->fetch_assoc()['c'] : 0;
}

function pi_escape($str) {
    global $mysqli;
    return $mysqli->real_escape_string($str);
}

function pi_get_categories() {
    global $mysqli;
    $r = $mysqli->query("SELECT * FROM pi_categories WHERE cat_active=1 ORDER BY cat_order,cat_id");
    $cats = [];
    while ($row = $r->fetch_assoc()) $cats[] = $row;
    return $cats;
}

function pi_badge_class($color) {
    $map = [
        'orange'  => 'bg-orange-100 text-orange-700',
        'blue'    => 'bg-blue-100 text-blue-700',
        'purple'  => 'bg-purple-100 text-purple-700',
        'cyan'    => 'bg-cyan-100 text-cyan-700',
        'red'     => 'bg-red-100 text-red-700',
        'green'   => 'bg-green-100 text-green-700',
        'gold'    => 'bg-yellow-100 text-yellow-700',
        'navy'    => 'bg-indigo-100 text-indigo-700',
        'teal'    => 'bg-teal-100 text-teal-700',
        'brown'   => 'bg-amber-100 text-amber-800',
        'gray'    => 'bg-gray-100 text-gray-700',
        'darkblue'=> 'bg-blue-200 text-blue-900',
    ];
    return $map[$color] ?? 'bg-gray-100 text-gray-700';
}
