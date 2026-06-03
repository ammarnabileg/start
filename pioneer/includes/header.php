<?php
// Load settings & countries
$_S  = pi_get_settings();
$_countries = pi_get_countries();
$_active_cid = pi_current_country();

// Find active country info
$_active_country = ['c_flag'=>'🌍','c_name'=>'كل الدول'];
foreach ($_countries as $c) {
    if ($c['c_id'] == $_active_cid) {
        $_active_country = $c;
        break;
    }
}

$_site_name = $_S['site_name'] ?? 'PioneerIcons';
$_primary   = $_S['primary_color'] ?? '#f97316';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? ($_S['site_name_ar']??'من هم') . ' | ' . ($_S['site_tagline']??'منصة الحضور العربي الموثق')) ?></title>
  <meta name="description" content="<?= htmlspecialchars($_S['site_description'] ?? '') ?>">
  <meta name="keywords" content="<?= htmlspecialchars($_S['site_keywords'] ?? '') ?>">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? $_S['site_name'] ?? 'PioneerIcons') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($_S['site_description'] ?? '') ?>">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <?php if ($_S['google_analytics']): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($_S['google_analytics']) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= htmlspecialchars($_S['google_analytics']) ?>');</script>
  <?php endif; ?>
  <style>
    * { font-family: 'Cairo', sans-serif; }
    :root { --pi-primary: <?= htmlspecialchars($_primary) ?>; }
    .verified-badge { color: #1d9bf0; }
    .gold-badge { color: #D4AF37; }
    .pi-gradient { background: linear-gradient(135deg, #1a3a6b 0%, #0f2548 100%); }
    .pi-primary { color: var(--pi-primary); }
    .pi-primary-bg { background-color: var(--pi-primary); }
    .pi-primary-border { border-color: var(--pi-primary); }
    .card-hover { transition: transform .2s, box-shadow .2s; }
    .card-hover:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,.12); }
    .hero-bg { background: linear-gradient(135deg, #0f2548 0%, #1a3a6b 50%, #1e4080 100%); }
    .daily-bg { background: linear-gradient(135deg, #1d9bf0 0%, #0f7dc5 100%); }
    [x-cloak] { display: none !important; }
  </style>
</head>
<body class="bg-gray-50">

<!-- NAVBAR -->
<nav class="bg-white shadow-md sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center justify-between h-16">

      <!-- Logo -->
      <a href="index.php<?= $_active_cid ? '?country='.$_active_cid : '' ?>" class="flex items-center gap-2 flex-shrink-0">
        <?php if ($_S['site_logo']): ?>
          <img src="<?= htmlspecialchars($_S['site_logo']) ?>" class="h-9 object-contain">
        <?php else: ?>
          <div class="w-9 h-9 rounded-lg pi-gradient flex items-center justify-center">
            <i class="fa-solid fa-star text-orange-400 text-sm"></i>
          </div>
          <span class="font-bold text-xl text-gray-800"><?= htmlspecialchars($_site_name) ?></span>
        <?php endif; ?>
      </a>

      <!-- Nav links (desktop) -->
      <div class="hidden md:flex items-center gap-1 flex-1 justify-center">

        <!-- أضف -->
        <div class="relative" x-data="{open:false}">
          <button @click="open=!open" @click.outside="open=false"
            class="flex items-center gap-1 px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition">
            أضف <i class="fa-solid fa-chevron-down text-xs mt-0.5"></i>
          </button>
          <div x-show="open" x-cloak x-transition
            class="absolute top-full right-0 mt-1 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
            <a href="add_personality.php" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
              <i class="fa-solid fa-user-plus w-5 text-orange-400"></i> أضف شخصية
            </a>
            <a href="add_institution.php" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
              <i class="fa-solid fa-building w-5 text-orange-400"></i> أضف شركة
            </a>
          </div>
        </div>

        <!-- عضويات -->
        <div class="relative" x-data="{open:false}">
          <button @click="open=!open" @click.outside="open=false"
            class="flex items-center gap-1 px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition">
            عضويات <i class="fa-solid fa-chevron-down text-xs mt-0.5"></i>
          </button>
          <div x-show="open" x-cloak x-transition
            class="absolute top-full right-0 mt-1 w-56 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
            <a href="membership.php?type=verified" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
              <i class="fa-solid fa-circle-check w-5 text-blue-500"></i> العضوية الموثقة
            </a>
            <a href="membership.php?type=executive" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 transition">
              <i class="fa-solid fa-crown w-5 text-yellow-500"></i> عضوية الرؤساء التنفيذيين
            </a>
          </div>
        </div>

        <a href="appointments.php" class="px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition whitespace-nowrap">
          نشرة تعيينات السعودية
        </a>
        <a href="categories.php" class="px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition">
          التصنيفات
        </a>
        <a href="lists.php" class="px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition">
          القوائم
        </a>
      </div>

      <!-- Right side: country + login -->
      <div class="flex items-center gap-2">

        <!-- Country selector -->
        <div class="relative" x-data="{ open: false }">
          <button @click="open=!open" @click.outside="open=false"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 hover:border-orange-300 transition text-sm font-semibold">
            <span class="text-base"><?= htmlspecialchars($_active_country['c_flag'] ?? '🌍') ?></span>
            <span class="hidden sm:inline text-gray-700 max-w-20 truncate"><?= htmlspecialchars($_active_country['c_name'] ?? 'كل الدول') ?></span>
            <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
          </button>
          <div x-show="open" x-cloak x-transition
            class="absolute top-full left-0 mt-1 w-52 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50 max-h-80 overflow-y-auto">
            <!-- All countries option -->
            <a href="?country=0" class="flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition text-sm <?= !$_active_cid?'bg-orange-50 font-bold text-orange-600':'' ?>">
              🌍 كل الدول
            </a>
            <div class="border-t border-gray-100 my-1"></div>
            <?php foreach ($_countries as $c): ?>
            <a href="?country=<?= $c['c_id'] ?>"
              class="flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition text-sm <?= $c['c_id']==$_active_cid?'bg-orange-50 font-bold text-orange-600':'' ?>">
              <span class="text-base"><?= htmlspecialchars($c['c_flag']) ?></span>
              <span><?= htmlspecialchars($c['c_name']) ?></span>
              <?php if ($c['c_id']==$_active_cid): ?><i class="fa-solid fa-check text-orange-500 mr-auto text-xs"></i><?php endif; ?>
            </a>
            <?php endforeach; ?>
            <?php if (empty($_countries)): ?>
            <p class="px-4 py-3 text-gray-400 text-xs text-center">لا توجد دول مضافة بعد</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Active country badge -->
        <?php if ($_active_cid): ?>
        <a href="?country=0" class="hidden sm:flex items-center gap-1 px-2 py-1 bg-orange-50 border border-orange-200 rounded-lg text-xs text-orange-600 font-semibold hover:bg-orange-100 transition">
          <i class="fa-solid fa-xmark text-xs"></i>
          إلغاء الفلتر
        </a>
        <?php endif; ?>

        <a href="admin.php?p=login"
          class="px-5 py-2 pi-primary-bg text-white rounded-full font-bold hover:opacity-90 transition text-sm whitespace-nowrap">
          تسجيل الدخول
        </a>
      </div>

    </div>
  </div>
</nav>

<?php if ($_active_cid && $_active_country): ?>
<!-- Country filter bar -->
<div class="bg-orange-50 border-b border-orange-100 py-2">
  <div class="max-w-7xl mx-auto px-4 flex items-center gap-2 text-sm text-orange-700">
    <i class="fa-solid fa-filter text-xs"></i>
    <span class="font-semibold">يتم عرض نتائج: <?= htmlspecialchars($_active_country['c_flag'].' '.$_active_country['c_name']) ?></span>
    <a href="?country=0" class="mr-auto flex items-center gap-1 text-orange-500 hover:text-orange-700 font-bold transition">
      <i class="fa-solid fa-xmark text-xs"></i> عرض كل الدول
    </a>
  </div>
</div>
<?php endif; ?>
