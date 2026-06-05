<?php
require_once 'includes/config.php';
pi_load_user();

$p = $_GET['p'] ?? 'dashboard';

// Handle admin login POST before any output
if ($p === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = pi_escape($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $r = $mysqli->query("SELECT * FROM pi_admin_users WHERE au_email='$email' AND au_active=1");
    if ($r && $r->num_rows) {
        $user = $r->fetch_assoc();
        if (password_verify($password, $user['au_password'])) {
            $_SESSION['pi_admin_id'] = $user['au_id'];
            header('Location: admin.php?p=dashboard');
            exit;
        }
    }
    $login_error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
}

// Handle user impersonation before any output
if ($p === 'users' && isset($_GET['impersonate'])) {
    pi_require_login();
    $iid = (int)$_GET['impersonate'];
    $ir  = $mysqli->query("SELECT u_id FROM pi_users WHERE u_id=$iid AND u_active=1");
    if ($ir && $ir->num_rows) {
        $_SESSION['pi_impersonate_admin_id'] = $_SESSION['pi_admin_id'];
        $_SESSION['pi_user_id'] = $iid;
        header('Location: account.php');
        exit;
    }
    header('Location: admin.php?p=users');
    exit;
}

// Redirect logged-in users away from login page
if ($p === 'login' && !empty($_SESSION['pi_admin_id'])) {
    header('Location: admin.php?p=dashboard');
    exit;
}

if ($p !== 'login' && $p !== 'logout') {
    pi_require_login();
}

// Handle AJAX requests before any HTML output
if (!empty($_GET['ajax'])) {
    $ajax_page = __DIR__ . "/admin/{$p}.php";
    if (file_exists($ajax_page)) {
        require $ajax_page;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'page not found']);
    }
    exit;
}

$pageTitle = 'لوحة التحكم - ' . pi_setting('site_name');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    * { font-family: 'Cairo', sans-serif; }
    [x-cloak] { display: none !important; }

    /* Sidebar */
    .sidebar { background: linear-gradient(160deg, #6B21A8 0%, #4C1D95 100%); }

    /* Sidebar scrollbar */
    #admin-sidebar nav::-webkit-scrollbar { width: 4px; }
    #admin-sidebar nav::-webkit-scrollbar-track { background: transparent; }
    #admin-sidebar nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.25); border-radius: 99px; }
    #admin-sidebar nav::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.45); }
    #admin-sidebar nav { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.25) transparent; }

    /* Close button: desktop hidden, mobile only */
    #sidebar-close-btn { display: none; }
    @media (max-width: 767px) { #sidebar-close-btn { display: flex; } }

    .nav-link {
      display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important;
      align-items: center; gap: 10px; width: 100%;
      padding: 10px 14px; border-radius: 12px;
      color: rgba(255,255,255,0.7); font-weight: 600; font-size: 14px;
      text-decoration: none; transition: all .15s; white-space: nowrap; box-sizing: border-box;
    }
    .nav-link:hover { background: rgba(255,255,255,0.12); color: #fff; }
    .nav-link.active { background: rgba(255,255,255,0.2); color: #fff; }
    .nav-link i { width: 18px; text-align: center; flex-shrink: 0; }
    .nav-section { border-top: 1px solid rgba(255,255,255,0.1); margin: 8px 0; }

    /* Buttons */
    .btn-primary {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 9px 18px; border-radius: 12px; font-weight: 700; font-size: 14px;
      color: #fff; border: none; cursor: pointer; transition: opacity .15s;
      background: linear-gradient(135deg, #8829C8 0%, #5B1494 100%);
    }
    .btn-primary:hover { opacity: .88; }
    .btn-secondary {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 9px 18px; border-radius: 12px; font-weight: 700; font-size: 14px;
      color: #374151; background: #fff; border: 1px solid #e5e7eb; cursor: pointer; transition: background .15s;
    }
    .btn-secondary:hover { background: #f9fafb; }
    .btn-danger {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 14px; border-radius: 12px; font-weight: 700; font-size: 14px;
      color: #dc2626; background: #fef2f2; border: none; cursor: pointer; transition: background .15s;
    }
    .btn-danger:hover { background: #fee2e2; }

    /* Forms */
    .form-input {
      width: 100%; border: 1px solid #e5e7eb; border-radius: 12px;
      padding: 10px 14px; font-size: 14px; outline: none; transition: border .15s;
      font-family: 'Cairo', sans-serif;
    }
    .form-input:focus { border-color: #a855f7; }
    .form-label { display: block; font-size: 14px; font-weight: 700; color: #374151; margin-bottom: 6px; }

    /* Table */
    table { width: 100%; border-collapse: collapse; }
    table th { padding: 12px 16px; text-align: right; font-size: 12px; font-weight: 700; color: #6b7280; background: #f9fafb; }
    table td { padding: 12px 16px; font-size: 14px; color: #374151; border-top: 1px solid #f3f4f6; }
    table tr:hover td { background: #fafafa; }

    /* Card */
    .card { background: #fff; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px; }

    /* Shared gradient helpers */
    .pi-primary-bg { background: linear-gradient(135deg, #8829C8 0%, #5B1494 100%); }
    .pi-gradient   { background: linear-gradient(135deg, #8829C8 0%, #5B1494 100%); }

    /* Upload zone */
    .pi-upload-zone {
      border: 2px dashed #e5e7eb;
      border-radius: 16px;
      padding: 24px 16px;
      text-align: center;
      cursor: pointer;
      background: #fafafa;
      transition: border-color .2s, background .2s;
      position: relative;
    }
    .pi-upload-zone:hover { border-color: #8829C8; background: #f5f0ff; }
    .pi-upload-zone.drag-over { border-color: #8829C8; background: #ede9fe; transform: scale(1.01); }
    .pi-upload-zone.has-preview { border-style: solid; border-color: #c4b5fd; background: #faf5ff; padding: 12px; }

    /* Quill editor improvements */
    .ql-toolbar.ql-snow {
      border-radius: 12px 12px 0 0 !important; border-color: #e5e7eb !important;
      background: #f9fafb; direction: ltr; text-align: left;
      padding: 8px 10px !important;
    }
    .ql-container.ql-snow {
      border-radius: 0 0 12px 12px !important; border-color: #e5e7eb !important;
      font-family: 'Cairo', sans-serif !important; font-size: 14px !important;
    }
    .ql-editor { direction: rtl; text-align: right; min-height: 140px; padding: 14px 16px !important; }
    .ql-editor.ql-blank::before { right: 16px; left: auto; font-style: normal; color: #9ca3af; }
    .ql-container:focus-within { border-color: #a855f7 !important; }
    .ql-toolbar:focus-within { border-color: #a855f7 !important; }
    .ql-snow .ql-picker { font-family: 'Cairo', sans-serif; }
  </style>
</head>
<style>
  /* Sidebar responsive behaviour — no Tailwind needed */
  #admin-sidebar {
    flex-shrink: 0;
    width: 240px;
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  #mobile-overlay  { display: none; }
  #mobile-hamburger { display: none; }

  @media (max-width: 767px) {
    /* Hide sidebar from layout flow */
    #admin-sidebar { display: none; }
    /* When open — fixed overlay */
    #admin-sidebar.mob-open {
      display: flex;
      position: fixed;
      top: 0; right: 0;
      width: 240px;
      height: 100vh;
      z-index: 50;
    }
    #mobile-overlay.mob-open {
      display: block;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.5);
      z-index: 40;
    }
    #mobile-hamburger { display: flex; }
  }
</style>

<body style="background:#f1f5f9;" x-data="{}">

<?php if ($p === 'login'): include 'admin/login.php'; ?>
<?php elseif ($p === 'logout'): include 'admin/logout.php'; ?>
<?php else: ?>

<!-- Mobile overlay -->
<div id="mobile-overlay" onclick="closeMobileSidebar()"></div>

<div style="display:flex;flex-direction:row;height:100vh;overflow:hidden;min-width:0;">

  <!-- Sidebar -->
  <aside id="admin-sidebar" class="sidebar">

    <!-- Logo -->
    <div style="padding:16px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;flex-shrink:0;">
      <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa-solid fa-star" style="color:#e9d5ff;font-size:14px;"></i>
      </div>
      <span style="font-weight:800;color:#fff;font-size:16px;white-space:nowrap;"><?= pi_setting('site_name') ?></span>
    </div>

    <!-- Nav — flex:1 + overflow-y:auto = scrollable -->
    <nav style="flex:1;overflow-y:auto;overflow-x:hidden;padding:12px 8px 24px;display:flex;flex-direction:column;gap:2px;">

      <a href="admin.php?p=dashboard" class="nav-link <?= $p=='dashboard'?'active':'' ?>">
        <i class="fa-solid fa-gauge-high"></i>
        <span>لوحة التحكم</span>
      </a>

      <?php if (pi_has_perm('view_personalities')): ?>
      <a href="admin.php?p=personalities" class="nav-link <?= $p=='personalities'?'active':'' ?>">
        <i class="fa-solid fa-users"></i>
        <span>الشخصيات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_institutions')): ?>
      <a href="admin.php?p=institutions" class="nav-link <?= $p=='institutions'?'active':'' ?>">
        <i class="fa-solid fa-building"></i>
        <span>المؤسسات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_categories')): ?>
      <a href="admin.php?p=categories" class="nav-link <?= $p=='categories'?'active':'' ?>">
        <i class="fa-solid fa-tags"></i>
        <span>التصنيفات</span>
      </a>
      <a href="admin.php?p=labels" class="nav-link <?= $p=='labels'?'active':'' ?>" style="padding-right:28px;">
        <i class="fa-solid fa-circle-dot" style="font-size:10px;"></i>
        <span>الليبلات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_articles')): ?>
      <a href="admin.php?p=articles" class="nav-link <?= $p=='articles'?'active':'' ?>">
        <i class="fa-regular fa-newspaper"></i>
        <span>المقالات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_sponsors')): ?>
      <a href="admin.php?p=sponsors" class="nav-link <?= $p=='sponsors'?'active':'' ?>">
        <i class="fa-solid fa-handshake"></i>
        <span>الرعاة</span>
      </a>
      <?php endif; ?>

      <div class="nav-section"></div>

      <?php if (pi_has_perm('view_roles')): ?>
      <a href="admin.php?p=roles" class="nav-link <?= $p=='roles'?'active':'' ?>">
        <i class="fa-solid fa-shield-halved"></i>
        <span>الأدوار والصلاحيات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_users')): ?>
      <a href="admin.php?p=users" class="nav-link <?= $p=='users'?'active':'' ?>">
        <i class="fa-solid fa-user-group"></i>
        <span>مستخدمو الموقع</span>
        <?php
        $usr_r = $mysqli->query("SHOW TABLES LIKE 'pi_users'");
        if ($usr_r && $usr_r->num_rows) {
            $usr_new_r = $mysqli->query("SELECT COUNT(*) c FROM pi_users WHERE u_active=1 AND u_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $usr_new = ($usr_new_r) ? (int)$usr_new_r->fetch_assoc()['c'] : 0;
            if ($usr_new > 0) echo '<span style="background:#8b5cf6;color:#fff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;margin-right:auto;">+'.$usr_new.'</span>';
        }
        ?>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_admin_users')): ?>
      <a href="admin.php?p=admin_users" class="nav-link <?= $p=='admin_users'?'active':'' ?>">
        <i class="fa-solid fa-user-gear"></i>
        <span>مستخدمو الإدارة</span>
      </a>
      <?php endif; ?>

      <div class="nav-section"></div>

      <?php if (pi_has_perm('manage_advertise')): ?>
      <a href="admin.php?p=advertise" class="nav-link <?= $p=='advertise'?'active':'' ?>">
        <i class="fa-solid fa-bullhorn"></i>
        <span>طلبات الإعلان</span>
        <?php
        $adv_rc = $mysqli->query("SHOW TABLES LIKE 'pi_advertise'");
        if ($adv_rc && $adv_rc->num_rows) {
            $adv_r2 = $mysqli->query("SELECT COUNT(*) c FROM pi_advertise WHERE adv_status='new'"); $adv_new = $adv_r2 ? (int)$adv_r2->fetch_assoc()['c'] : 0;
            if ($adv_new > 0) echo '<span style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;margin-right:auto;">'.$adv_new.'</span>';
        }
        ?>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_memberships')): ?>
      <a href="admin.php?p=memberships" class="nav-link <?= $p=='memberships'?'active':'' ?>">
        <i class="fa-solid fa-crown"></i>
        <span>طلبات العضوية</span>
        <?php
        $mem_rc = $mysqli->query("SHOW TABLES LIKE 'pi_memberships'");
        if ($mem_rc && $mem_rc->num_rows) {
            $mem_r2 = $mysqli->query("SELECT COUNT(*) c FROM pi_memberships WHERE mem_status='pending'"); $mem_new = $mem_r2 ? (int)$mem_r2->fetch_assoc()['c'] : 0;
            if ($mem_new > 0) echo '<span style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;margin-right:auto;">'.$mem_new.'</span>';
        }
        ?>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_complaints')): ?>
      <a href="admin.php?p=complaints" class="nav-link <?= $p=='complaints'?'active':'' ?>">
        <i class="fa-solid fa-pen-to-square"></i>
        <span>الشكاوي والملاحظات</span>
        <?php
        $cmp_rc = $mysqli->query("SHOW TABLES LIKE 'pi_complaints'");
        if ($cmp_rc && $cmp_rc->num_rows) {
            $cmp_r = $mysqli->query("SELECT COUNT(*) c FROM pi_complaints WHERE cmp_status='new'");
            if ($cmp_r) { $cn=(int)$cmp_r->fetch_assoc()['c']; if($cn>0) echo '<span style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;margin-right:auto;">'.$cn.'</span>'; }
        }
        ?>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_submissions')): ?>
      <a href="admin.php?p=submissions" class="nav-link <?= $p=='submissions'?'active':'' ?>">
        <i class="fa-solid fa-inbox"></i>
        <span>مقترحات المستخدمين</span>
        <?php
        $pending_count = 0;
        $rc = $mysqli->query("SHOW TABLES LIKE 'pi_submissions'");
        if ($rc && $rc->num_rows) {
            $rc2 = $mysqli->query("SELECT COUNT(*) c FROM pi_submissions WHERE sub_status='pending'");
            if ($rc2) $pending_count = (int)$rc2->fetch_assoc()['c'];
        }
        if ($pending_count > 0): ?>
        <span style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;margin-right:auto;"><?= $pending_count ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_lists')): ?>
      <a href="admin.php?p=lists" class="nav-link <?= $p=='lists'?'active':'' ?>">
        <i class="fa-solid fa-list-ol"></i>
        <span>القوائم</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_edit_requests')): ?>
      <a href="admin.php?p=edit_requests" class="nav-link <?= $p=='edit_requests'?'active':'' ?>">
        <i class="fa-solid fa-pen-ruler"></i>
        <span>طلبات التعديل</span>
        <?php
        $er_rc = $mysqli->query("SHOW TABLES LIKE 'pi_edit_requests'");
        if ($er_rc && $er_rc->num_rows) {
            $er_r = $mysqli->query("SELECT COUNT(*) c FROM pi_edit_requests WHERE er_status='pending' AND er_req_type='edit'");
            if ($er_r) { $ec=(int)$er_r->fetch_assoc()['c']; if($ec>0) echo '<span style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;margin-right:auto;">'.$ec.'</span>'; }
        }
        ?>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_countries')): ?>
      <a href="admin.php?p=countries" class="nav-link <?= $p=='countries'?'active':'' ?>">
        <i class="fa-solid fa-globe"></i>
        <span>الدول</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_settings')): ?>
      <a href="admin.php?p=settings" class="nav-link <?= $p=='settings'?'active':'' ?>">
        <i class="fa-solid fa-gear"></i>
        <span>إعدادات الموقع</span>
      </a>
      <?php endif; ?>

      <div class="nav-section"></div>

      <a href="index.php" target="_blank" class="nav-link">
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        <span>عرض الموقع</span>
      </a>

      <a href="admin.php?p=logout" class="nav-link" style="color:rgba(252,165,165,.9);">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>تسجيل الخروج</span>
      </a>
    </nav>

    <!-- Close button: mobile only (hidden on desktop via CSS) -->
    <button id="sidebar-close-btn" onclick="closeMobileSidebar()"
      style="padding:14px;border-top:1px solid rgba(255,255,255,.1);background:none;border:none;color:rgba(255,255,255,.6);cursor:pointer;align-items:center;justify-content:center;gap:8px;flex-shrink:0;">
      <i class="fa-solid fa-xmark" style="font-size:14px;"></i>
      <span style="font-size:13px;font-weight:700;">إغلاق القائمة</span>
    </button>
  </aside>

  <!-- Main -->
  <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0;">

    <!-- Top bar -->
    <header style="background:#fff;border-bottom:1px solid #e5e7eb;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;gap:12px;">
      <!-- Mobile hamburger (hidden on desktop via CSS) -->
      <button id="mobile-hamburger" onclick="openMobileSidebar()"
        style="width:36px;height:36px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;">
        <i class="fa-solid fa-bars" style="color:#6b7280;font-size:14px;"></i>
      </button>
      <h1 style="font-weight:800;color:#111827;font-size:18px;margin:0;flex:1;">
        <?php
        $titles = [
          'dashboard'=>'لوحة التحكم','personalities'=>'الشخصيات','institutions'=>'المؤسسات',
          'categories'=>'التصنيفات','articles'=>'المقالات','timeline'=>'المحطات الزمنية',
          'sponsors'=>'الرعاة','roles'=>'الأدوار والصلاحيات','admin_users'=>'مستخدمو الإدارة',
          'submissions'=>'مقترحات المستخدمين','countries'=>'إدارة الدول','settings'=>'إعدادات الموقع','labels'=>'إعدادات الليبلات','advertise'=>'طلبات الإعلان','memberships'=>'طلبات العضوية','users'=>'مستخدمو الموقع','edit_requests'=>'طلبات التعديل','lists'=>'إدارة القوائم',
        ];
        echo $titles[$p] ?? 'لوحة التحكم';
        ?>
      </h1>
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="text-align:right;">
          <p style="font-size:14px;font-weight:700;color:#111827;margin:0;"><?= htmlspecialchars($pi_user['au_name'] ?? '') ?></p>
          <p style="font-size:12px;color:#9ca3af;margin:0;"><?= htmlspecialchars($pi_user['au_email'] ?? '') ?></p>
        </div>
        <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:15px;flex-shrink:0;">
          <?= mb_substr($pi_user['au_name'] ?? 'A', 0, 1) ?>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main style="flex:1;overflow-y:auto;padding:24px;">
      <?php
      if ($p === 'dashboard')         include 'admin/dashboard.php';
      elseif ($p === 'personalities') include 'admin/personalities.php';
      elseif ($p === 'institutions')  include 'admin/institutions.php';
      elseif ($p === 'categories')    include 'admin/categories.php';
      elseif ($p === 'articles')      include 'admin/articles.php';
      elseif ($p === 'timeline')      include 'admin/timeline.php';
      elseif ($p === 'sponsors')      include 'admin/sponsors.php';
      elseif ($p === 'roles')         include 'admin/roles.php';
      elseif ($p === 'admin_users')   include 'admin/admin_users.php';
      elseif ($p === 'submissions')   include 'admin/submissions.php';
      elseif ($p === 'countries')     include 'admin/countries.php';
      elseif ($p === 'labels')        include 'admin/labels.php';
      elseif ($p === 'advertise')     include 'admin/advertise.php';
      elseif ($p === 'settings')      include 'admin/settings.php';
      elseif ($p === 'memberships')   include 'admin/memberships.php';
      elseif ($p === 'complaints')    include 'admin/complaints.php';
      elseif ($p === 'users')         include 'admin/users.php';
      elseif ($p === 'edit_requests') include 'admin/edit_requests.php';
      elseif ($p === 'lists')         include 'admin/lists.php';
      else include 'admin/dashboard.php';
      ?>
    </main>
  </div>
</div>

<?php endif; ?>
<script>
function openMobileSidebar() {
  document.getElementById('admin-sidebar').classList.add('mob-open');
  document.getElementById('mobile-overlay').classList.add('mob-open');
}
function closeMobileSidebar() {
  document.getElementById('admin-sidebar').classList.remove('mob-open');
  document.getElementById('mobile-overlay').classList.remove('mob-open');
}

// Global image preview for all .pi-upload-zone file inputs
document.addEventListener('change', function(e) {
  var inp = e.target;
  if (inp.type !== 'file' || !inp.dataset.preview) return;
  var file = inp.files[0];
  if (!file || !file.type.startsWith('image/')) return;
  var prevId = inp.dataset.preview;
  var phId   = inp.dataset.placeholder;
  var reader = new FileReader();
  reader.onload = function(ev) {
    var img = document.getElementById(prevId);
    var ph  = phId ? document.getElementById(phId) : null;
    if (img) { img.src = ev.target.result; img.classList.remove('hidden'); }
    if (ph)  { ph.style.display = 'none'; }
  };
  reader.readAsDataURL(file);
});
</script>
</body>
</html>
