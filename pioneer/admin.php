<?php
require_once 'includes/config.php';
pi_load_user();

$p = $_GET['p'] ?? 'dashboard';

// Allow login page without auth
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
    .pi-gradient { background: linear-gradient(135deg, #8829C8 0%, #5B1494 100%); }
    [x-cloak] { display: none !important; }
    .nav-link { @apply flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-300 hover:text-white hover:bg-white/10 transition font-semibold text-sm; }
    .nav-link.active { @apply text-white bg-white/20; }
    .card { @apply bg-white rounded-2xl shadow-sm p-6; }
    .btn-primary { @apply px-5 py-2.5 text-white font-bold rounded-xl hover:opacity-90 transition text-sm; background: linear-gradient(135deg, #8829C8 0%, #5B1494 100%); }
    .btn-secondary { @apply px-5 py-2.5 border border-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition text-sm; }
    .btn-danger { @apply px-4 py-2 bg-red-50 text-red-600 font-bold rounded-xl hover:bg-red-100 transition text-sm; }
    .form-input { @apply w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-purple-400 transition; }
    .form-label { @apply block text-sm font-bold text-gray-700 mb-1.5; }
    table th { @apply px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider bg-gray-50; }
    table td { @apply px-4 py-3 text-sm text-gray-700 border-t border-gray-100; }
  </style>
</head>
<body class="bg-gray-100" x-data="{ sidebarOpen: true }">

<?php if ($p === 'login'): include 'admin/login.php'; ?>
<?php elseif ($p === 'logout'): include 'admin/logout.php'; ?>
<?php else: ?>

<div class="flex h-screen overflow-hidden">
  <!-- Sidebar -->
  <aside :class="sidebarOpen ? 'w-64' : 'w-16'" class="pi-gradient flex-shrink-0 transition-all duration-300 flex flex-col overflow-hidden">
    <!-- Logo -->
    <div class="flex items-center gap-3 p-4 border-b border-white/10">
      <div class="w-9 h-9 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0">
        <i class="fa-solid fa-star text-white text-sm"></i>
      </div>
      <span x-show="sidebarOpen" x-cloak class="font-bold text-white text-base whitespace-nowrap">PioneerIcons</span>
    </div>

    <!-- Nav -->
    <nav class="flex-1 p-3 overflow-y-auto space-y-1">
      <a href="admin.php?p=dashboard" class="nav-link <?= $p=='dashboard'?'active':'' ?>">
        <i class="fa-solid fa-gauge-high w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>لوحة التحكم</span>
      </a>

      <?php if (pi_has_perm('view_personalities')): ?>
      <a href="admin.php?p=personalities" class="nav-link <?= $p=='personalities'?'active':'' ?>">
        <i class="fa-solid fa-users w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>الشخصيات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_institutions')): ?>
      <a href="admin.php?p=institutions" class="nav-link <?= $p=='institutions'?'active':'' ?>">
        <i class="fa-solid fa-building w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>المؤسسات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_categories')): ?>
      <a href="admin.php?p=categories" class="nav-link <?= $p=='categories'?'active':'' ?>">
        <i class="fa-solid fa-tags w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>التصنيفات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_articles')): ?>
      <a href="admin.php?p=articles" class="nav-link <?= $p=='articles'?'active':'' ?>">
        <i class="fa-regular fa-newspaper w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>المقالات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_timeline')): ?>
      <a href="admin.php?p=timeline" class="nav-link <?= $p=='timeline'?'active':'' ?>">
        <i class="fa-solid fa-timeline w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>المحطات الزمنية</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_sponsors')): ?>
      <a href="admin.php?p=sponsors" class="nav-link <?= $p=='sponsors'?'active':'' ?>">
        <i class="fa-solid fa-handshake w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>الرعاة</span>
      </a>
      <?php endif; ?>

      <div class="border-t border-white/10 my-2"></div>

      <?php if (pi_has_perm('view_roles')): ?>
      <a href="admin.php?p=roles" class="nav-link <?= $p=='roles'?'active':'' ?>">
        <i class="fa-solid fa-shield-halved w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>الأدوار والصلاحيات</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('view_admin_users')): ?>
      <a href="admin.php?p=admin_users" class="nav-link <?= $p=='admin_users'?'active':'' ?>">
        <i class="fa-solid fa-user-gear w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>مستخدمو الإدارة</span>
      </a>
      <?php endif; ?>

      <div class="border-t border-white/10 my-2"></div>

      <a href="admin.php?p=submissions" class="nav-link <?= $p=='submissions'?'active':'' ?> relative">
        <i class="fa-solid fa-inbox w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>مقترحات المستخدمين</span>
        <?php
        $pending_count = 0;
        $rc = $mysqli->query("SHOW TABLES LIKE 'pi_submissions'");
        if ($rc && $rc->num_rows) {
            $rc2 = $mysqli->query("SELECT COUNT(*) c FROM pi_submissions WHERE sub_status='pending'");
            if ($rc2) $pending_count = (int)$rc2->fetch_assoc()['c'];
        }
        if ($pending_count > 0): ?>
        <span class="bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full"><?= $pending_count ?></span>
        <?php endif; ?>
      </a>

      <?php if (pi_has_perm('manage_countries')): ?>
      <a href="admin.php?p=countries" class="nav-link <?= $p=='countries'?'active':'' ?>">
        <i class="fa-solid fa-globe w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>الدول</span>
      </a>
      <?php endif; ?>

      <?php if (pi_has_perm('manage_settings')): ?>
      <a href="admin.php?p=settings" class="nav-link <?= $p=='settings'?'active':'' ?>">
        <i class="fa-solid fa-gear w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>إعدادات الموقع</span>
      </a>
      <?php endif; ?>

      <div class="border-t border-white/10 my-2"></div>

      <a href="index.php" target="_blank" class="nav-link">
        <i class="fa-solid fa-arrow-up-right-from-square w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>عرض الموقع</span>
      </a>

      <a href="admin.php?p=logout" class="nav-link text-red-300 hover:text-red-200">
        <i class="fa-solid fa-right-from-bracket w-5 text-center"></i>
        <span x-show="sidebarOpen" x-cloak>تسجيل الخروج</span>
      </a>
    </nav>

    <!-- Toggle sidebar -->
    <button @click="sidebarOpen=!sidebarOpen"
      class="p-4 border-t border-white/10 text-gray-400 hover:text-white transition flex items-center justify-center">
      <i :class="sidebarOpen ? 'fa-chevron-right' : 'fa-chevron-left'" class="fa-solid text-xs"></i>
    </button>
  </aside>

  <!-- Main content -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <!-- Top bar -->
    <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <h1 class="font-black text-gray-800 text-lg">
          <?php
          $titles = [
            'dashboard'=>'لوحة التحكم','personalities'=>'الشخصيات','institutions'=>'المؤسسات',
            'categories'=>'التصنيفات','articles'=>'المقالات','timeline'=>'المحطات الزمنية',
            'sponsors'=>'الرعاة','roles'=>'الأدوار والصلاحيات','admin_users'=>'مستخدمو الإدارة',
            'submissions'=>'مقترحات المستخدمين','countries'=>'إدارة الدول','settings'=>'إعدادات الموقع',
          ];
          echo $titles[$p] ?? 'لوحة التحكم';
          ?>
        </h1>
      </div>
      <div class="flex items-center gap-3">
        <div class="text-right">
          <p class="text-sm font-bold text-gray-800"><?= htmlspecialchars($pi_user['au_name'] ?? '') ?></p>
          <p class="text-xs text-gray-400"><?= htmlspecialchars($pi_user['au_email'] ?? '') ?></p>
        </div>
        <div class="w-9 h-9 rounded-full pi-gradient flex items-center justify-center text-white font-bold text-sm">
          <?= mb_substr($pi_user['au_name'] ?? 'A', 0, 1) ?>
        </div>
      </div>
    </header>

    <!-- Page content -->
    <main class="flex-1 overflow-y-auto p-6">
      <?php
      if ($p === 'dashboard')       include 'admin/dashboard.php';
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
      elseif ($p === 'settings')      include 'admin/settings.php';
      else include 'admin/dashboard.php';
      ?>
    </main>
  </div>
</div>

<?php endif; ?>
</body>
</html>
