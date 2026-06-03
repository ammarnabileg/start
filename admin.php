<?php
require_once 'includes/config.php';
pi_load_user();

$p = $_GET['p'] ?? 'dashboard';

// Redirect logged-in users away from login page
if ($p === 'login' && !empty($_SESSION['pi_admin_id'])) {
    header('Location: admin.php?p=dashboard');
    exit;
}

if ($p !== 'login' && $p !== 'logout') {
    pi_require_login();
}

$pageTitle = 'لوحة التحكم - PioneerIcons';
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

    /* Upload zone */
    .pi-upload-zone {
      border: 2px dashed #e5e7eb; border-radius: 14px; padding: 20px;
      text-align: center; cursor: pointer; transition: border .2s, background .2s;
      background: #fafafa; position: relative; overflow: hidden;
    }
    .pi-upload-zone:hover { border-color: #a855f7; background: #faf5ff; }
    .pi-upload-zone .preview-img {
      width: 90px; height: 90px; border-radius: 12px; object-fit: cover;
      margin: 0 auto 10px; display: block; border: 2px solid #e9d5ff;
    }
    .pi-upload-zone .preview-img.rounded-full { border-radius: 50%; }
    .pi-upload-zone .preview-label {
      font-size: 12px; font-weight: 600; color: #9ca3af; margin-top: 6px;
    }

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
<body style="background:#f1f5f9;" x-data="{ sidebarOpen: true }">

<?php if ($p === 'login'): include 'admin/login.php'; ?>
<?php elseif ($p === 'logout'): include 'admin/logout.php'; ?>
<?php else: ?>

<div style="display:flex; flex-direction:row; height:100vh; overflow:hidden; min-width:0;">

  <!-- Sidebar -->
  <aside class="sidebar" :style="sidebarOpen ? 'width:240px' : 'width:58px'"
    style="flex-shrink:0; transition:width .25s; display:flex; flex-direction:column; overflow-x:hidden; overflow-y:auto;">

    <!-- Logo -->
    <div style="padding:16px; border-bottom:1px solid rgba(255,255,255,.1); display:flex; align-items:center; gap:10px;">
      <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="fa-solid fa-star" style="color:#e9d5ff;font-size:14px;"></i>
      </div>
      <span x-show="sidebarOpen" x-cloak style="font-weight:800;color:#fff;font-size:16px;white-space:nowrap;">PioneerIcons</span>
    </div>

    <!-- Nav -->
    <nav style="flex:1;padding:12px 8px 24px;display:flex;flex-direction:column;flex-wrap:nowrap;gap:2px;min-width:0;">

      <a href="admin.php?p=dashboard" class="nav-link <?= $p=='dashboard'?'active':'' ?>">
        <i class="fa-solid fa-gauge-high"></i>
        <span x-show="sidebarOpen" x-cloak>لوحة التحكم</span>
      </a>

      <?php if (pi_has_perm('view_personalities')): ?>
      <a href="admin.php?p=personalities" class="nav-link <?= $p=='personalities'?'active':'' ?>">
        <i class="fa-solid fa-users"></i>
        <span x-show="sidebarOpen" x-cloak>الشخصيات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_institutions')): ?>
      <a href="admin.php?p=institutions" class="nav-link <?= $p=='institutions'?'active':'' ?>">
        <i class="fa-solid fa-building"></i>
        <span x-show="sidebarOpen" x-cloak>المؤسسات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_categories')): ?>
      <a href="admin.php?p=categories" class="nav-link <?= $p=='categories'?'active':'' ?>">
        <i class="fa-solid fa-tags"></i>
        <span x-show="sidebarOpen" x-cloak>التصنيفات</span>
      </a>
      <a href="admin.php?p=labels" class="nav-link <?= $p=='labels'?'active':'' ?>" style="padding-right:28px;">
        <i class="fa-solid fa-circle-dot" style="font-size:10px;"></i>
        <span x-show="sidebarOpen" x-cloak>الليبلات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_articles')): ?>
      <a href="admin.php?p=articles" class="nav-link <?= $p=='articles'?'active':'' ?>">
        <i class="fa-regular fa-newspaper"></i>
        <span x-show="sidebarOpen" x-cloak>المقالات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_sponsors')): ?>
      <a href="admin.php?p=sponsors" class="nav-link <?= $p=='sponsors'?'active':'' ?>">
        <i class="fa-solid fa-handshake"></i>
        <span x-show="sidebarOpen" x-cloak>الرعاة</span>
      </a>
      <?php endif; ?>

      <div class="nav-section"></div>

      <?php if (pi_has_perm('view_roles')): ?>
      <a href="admin.php?p=roles" class="nav-link <?= $p=='roles'?'active':'' ?>">
        <i class="fa-solid fa-shield-halved"></i>
        <span x-show="sidebarOpen" x-cloak>الأدوار والصلاحيات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_admin_users')): ?>
      <a href="admin.php?p=admin_users" class="nav-link <?= $p=='admin_users'?'active':'' ?>">
        <i class="fa-solid fa-user-gear"></i>
        <span x-show="sidebarOpen" x-cloak>مستخدمو الإدارة</span>
      </a>
      <?php endif; ?>

      <div class="nav-section"></div>

      <a href="admin.php?p=advertise" class="nav-link <?= $p=='advertise'?'active':'' ?>">
        <i class="fa-solid fa-bullhorn"></i>
        <span x-show="sidebarOpen" x-cloak>طلبات الإعلان</span>
        <?php
        $adv_rc = $mysqli->query("SHOW TABLES LIKE 'pi_advertise'");
        if ($adv_rc && $adv_rc->num_rows) {
            $adv_new = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_advertise WHERE adv_status='new'")->fetch_assoc()['c'];
            if ($adv_new > 0) echo '<span style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;margin-right:auto;">'.$adv_new.'</span>';
        }
        ?>
      </a>

      <a href="admin.php?p=memberships" class="nav-link <?= $p=='memberships'?'active':'' ?>">
        <i class="fa-solid fa-crown"></i>
        <span x-show="sidebarOpen" x-cloak>طلبات العضوية</span>
        <?php
        $mem_rc = $mysqli->query("SHOW TABLES LIKE 'pi_memberships'");
        if ($mem_rc && $mem_rc->num_rows) {
            $mem_new = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_memberships WHERE mem_status='pending'")->fetch_assoc()['c'];
            if ($mem_new > 0) echo '<span style="background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:999px;margin-right:auto;">'.$mem_new.'</span>';
        }
        ?>
      </a>

      <a href="admin.php?p=submissions" class="nav-link <?= $p=='submissions'?'active':'' ?>" style="position:relative;">
        <i class="fa-solid fa-inbox"></i>
        <span x-show="sidebarOpen" x-cloak>مقترحات المستخدمين</span>
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

      <?php if (pi_has_perm('manage_countries')): ?>
      <a href="admin.php?p=countries" class="nav-link <?= $p=='countries'?'active':'' ?>">
        <i class="fa-solid fa-globe"></i>
        <span x-show="sidebarOpen" x-cloak>الدول</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_settings')): ?>
      <a href="admin.php?p=settings" class="nav-link <?= $p=='settings'?'active':'' ?>">
        <i class="fa-solid fa-gear"></i>
        <span x-show="sidebarOpen" x-cloak>إعدادات الموقع</span>
      </a>
      <?php endif; ?>

      <div class="nav-section"></div>

      <a href="index.php" target="_blank" class="nav-link">
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        <span x-show="sidebarOpen" x-cloak>عرض الموقع</span>
      </a>

      <a href="admin.php?p=logout" class="nav-link" style="color:rgba(252,165,165,.9);">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span x-show="sidebarOpen" x-cloak>تسجيل الخروج</span>
      </a>
    </nav>

    <!-- Toggle -->
    <button @click="sidebarOpen=!sidebarOpen"
      style="padding:14px;border-top:1px solid rgba(255,255,255,.1);background:none;border-left:none;border-right:none;border-bottom:none;color:rgba(255,255,255,.5);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:color .15s;"
      onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.5)'">
      <i :class="sidebarOpen ? 'fa-chevron-right' : 'fa-chevron-left'" class="fa-solid" style="font-size:11px;"></i>
    </button>
  </aside>

  <!-- Main -->
  <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;">

    <!-- Top bar -->
    <header style="background:#fff;border-bottom:1px solid #e5e7eb;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
      <h1 style="font-weight:800;color:#111827;font-size:18px;margin:0;">
        <?php
        $titles = [
          'dashboard'=>'لوحة التحكم','personalities'=>'الشخصيات','institutions'=>'المؤسسات',
          'categories'=>'التصنيفات','articles'=>'المقالات','timeline'=>'المحطات الزمنية',
          'sponsors'=>'الرعاة','roles'=>'الأدوار والصلاحيات','admin_users'=>'مستخدمو الإدارة',
          'submissions'=>'مقترحات المستخدمين','countries'=>'إدارة الدول','settings'=>'إعدادات الموقع','labels'=>'إعدادات الليبلات','advertise'=>'طلبات الإعلان','memberships'=>'طلبات العضوية',
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
      else include 'admin/dashboard.php';
      ?>
    </main>
  </div>
</div>

<?php endif; ?>
<script>
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
