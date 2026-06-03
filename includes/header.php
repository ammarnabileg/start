<?php
// Load settings & countries
$_S  = pi_get_settings();
$_countries = pi_get_countries();
$_active_cid = pi_current_country();
$_default_cid = (int)($_S['default_country'] ?? 0);
// Show country selector only when admin has a default country configured
$_show_country_selector = !empty($_countries) && $_default_cid > 0;

// Find active country info
$_active_country = ['c_flag'=>'🌍','c_name'=>'كل الدول'];
foreach ($_countries as $c) {
    if ($c['c_id'] == $_active_cid) {
        $_active_country = $c;
        break;
    }
}

$_site_name = $_S['site_name'] ?? 'PioneerIcons';
$_primary   = $_S['primary_color'] ?? '#8829C8';
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
    .pi-gradient { background: linear-gradient(135deg, #8829C8 0%, #5B1494 100%); }
    .pi-primary { color: var(--pi-primary); }
    .pi-primary-bg { background: linear-gradient(135deg, var(--pi-primary) 0%, #5B1494 100%); }
    .pi-primary-bg-solid { background-color: var(--pi-primary); }
    .pi-primary-border { border-color: var(--pi-primary); }
    .card-hover { transition: transform .2s, box-shadow .2s; }
    .card-hover:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(136,41,200,.15); }
    .hero-bg {
      background: linear-gradient(160deg, #0B0B1F 0%, #130B2B 50%, #1A0D35 100%);
      position: relative;
      overflow: hidden;
    }
    .hero-bg::before {
      content: '';
      position: absolute; inset: 0;
      background-image:
        radial-gradient(circle, rgba(255,255,255,.7) 1px, transparent 1px),
        radial-gradient(circle, rgba(255,255,255,.4) 1px, transparent 1px);
      background-size: 60px 60px, 30px 30px;
      background-position: 0 0, 15px 15px;
      opacity: .12;
      pointer-events: none;
    }
    .hero-glow {
      position: absolute;
      width: 500px; height: 500px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(136,41,200,.35) 0%, transparent 70%);
      top: -150px; right: -150px;
      pointer-events: none;
    }
    .hero-glow-2 {
      position: absolute;
      width: 300px; height: 300px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(91,20,148,.4) 0%, transparent 70%);
      bottom: -100px; left: 10%;
      pointer-events: none;
    }
    .daily-bg { background: linear-gradient(135deg, #8829C8 0%, #5B1494 100%); }
    .section-dot::before {
      content: '';
      display: inline-block;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #8829C8;
      margin-left: 8px;
      vertical-align: middle;
    }
    .section-dot::after {
      content: '';
      display: inline-block;
      width: 5px; height: 5px;
      border-radius: 50%;
      background: #E53E3E;
      margin-right: 4px;
      vertical-align: middle;
    }
    [x-cloak] { display: none !important; }
    /* Global upload zone */
    .pi-upload-zone {
      border: 2px dashed #d1d5db;
      border-radius: 14px;
      padding: 20px 16px;
      text-align: center;
      cursor: pointer;
      background: #fafafa;
      transition: border-color .2s, background .2s;
    }
    .pi-upload-zone:hover { border-color: #8829C8; background: #f5f0ff; }
    .pi-upload-zone.drag-over { border-color: #8829C8; background: #ede9fe; }
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
            <i class="fa-solid fa-star text-purple-300 text-sm"></i>
          </div>
          <span class="font-bold text-xl text-gray-800"><?= htmlspecialchars($_site_name) ?></span>
        <?php endif; ?>
      </a>

      <!-- Nav links (desktop) -->
      <div class="hidden md:flex items-center gap-1 flex-1 justify-center">

        <!-- أضف -->
        <div class="relative" x-data="{open:false}">
          <button @click="open=!open" @click.outside="open=false"
            class="flex items-center gap-1 px-4 py-2 text-gray-700 hover:text-purple-600 font-semibold rounded-lg hover:bg-purple-50 transition">
            أضف <i class="fa-solid fa-chevron-down text-xs mt-0.5"></i>
          </button>
          <div x-show="open" x-cloak x-transition
            class="absolute top-full right-0 mt-1 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
            <a href="add_personality.php" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition">
              <i class="fa-solid fa-user-plus w-5 text-purple-500"></i> أضف شخصية
            </a>
            <a href="add_institution.php" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition">
              <i class="fa-solid fa-building w-5 text-purple-500"></i> أضف شركة
            </a>
          </div>
        </div>

        <!-- عضويات -->
        <div class="relative" x-data="{open:false}">
          <button @click="open=!open" @click.outside="open=false"
            class="flex items-center gap-1 px-4 py-2 text-gray-700 hover:text-purple-600 font-semibold rounded-lg hover:bg-purple-50 transition">
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

        <a href="categories.php" class="px-4 py-2 text-gray-700 hover:text-purple-600 font-semibold rounded-lg hover:bg-purple-50 transition">
          التصنيفات
        </a>

        <a href="blog.php" class="px-4 py-2 text-gray-700 hover:text-purple-600 font-semibold rounded-lg hover:bg-purple-50 transition">
          المقالات
        </a>
      </div>

      <!-- Right side: country + login -->
      <div class="flex items-center gap-2">
        <?php if ($_show_country_selector): ?>
        <div class="relative" x-data="{ open: false }">
          <button @click="open=!open" @click.outside="open=false"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border <?= $_active_cid ? 'border-purple-300 bg-purple-50' : 'border-gray-200 bg-white' ?> hover:border-purple-400 transition text-sm font-semibold">
            <span class="text-base"><?= htmlspecialchars($_active_country['c_flag'] ?? '🌍') ?></span>
            <span class="hidden sm:inline <?= $_active_cid ? 'text-purple-700' : 'text-gray-600' ?> max-w-24 truncate"><?= htmlspecialchars($_active_country['c_name'] ?? 'كل الدول') ?></span>
            <i class="fa-solid fa-chevron-down text-xs <?= $_active_cid ? 'text-purple-400' : 'text-gray-400' ?>"></i>
          </button>
          <div x-show="open" x-cloak x-transition
            class="absolute top-full left-0 mt-1 w-52 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50 max-h-80 overflow-y-auto">
            <?php foreach ($_countries as $c): ?>
            <a href="?country=<?= $c['c_id'] ?>"
              class="flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition text-sm <?= $c['c_id']==$_active_cid?'bg-purple-50 font-bold text-purple-600':'' ?>">
              <span class="text-base"><?= htmlspecialchars($c['c_flag']) ?></span>
              <span><?= htmlspecialchars($c['c_name']) ?></span>
              <?php if ($c['c_id']==$_active_cid): ?><i class="fa-solid fa-check text-purple-500 mr-auto text-xs"></i><?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <a href="admin.php?p=login"
          class="px-5 py-2 pi-primary-bg text-white rounded-full font-bold hover:opacity-90 transition text-sm whitespace-nowrap">
          دخول
        </a>
      </div>

    </div>
  </div>
</nav>

<?php if ($_active_cid && $_active_country && isset($_active_country['c_id'])): ?>
<!-- Country filter bar -->
<div class="bg-purple-50 border-b border-purple-100 py-2">
  <div class="max-w-7xl mx-auto px-4 flex items-center gap-2 text-sm text-purple-700">
    <i class="fa-solid fa-filter text-xs"></i>
    <span class="font-semibold">يتم عرض نتائج: <?= htmlspecialchars($_active_country['c_flag'].' '.$_active_country['c_name']) ?></span>
  </div>
</div>
<?php endif; ?>
