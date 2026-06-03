<?php
require_once 'includes/config.php';
$_S = pi_get_settings();
$pageTitle = htmlspecialchars($_S['site_name'] ?? 'PioneerIcons') . ' | ' . htmlspecialchars($_S['site_tagline'] ?? 'منصة الحضور العربي الموثق');

$total_count = pi_count_personalities() + pi_count_institutions();
$cid = pi_current_country();
$country_where_p    = $cid ? " AND p_country_id=$cid" : '';
$country_where_inst = $cid ? " AND inst_country_id=$cid" : '';

// Most visited personalities — country first, then fill from others if needed
$personalities = [];
if ($cid) {
    // Priority: current country first
    $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 AND p_country_id=$cid ORDER BY p_views DESC LIMIT 10");
    if ($r) while ($row=$r->fetch_assoc()) $personalities[] = $row;
    // If less than 10, fill from other countries
    if (count($personalities) < 10) {
        $existing_ids = array_column($personalities, 'p_id');
        $exclude = $existing_ids ? 'AND p_id NOT IN ('.implode(',', $existing_ids).')' : '';
        $limit = 10 - count($personalities);
        $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 $exclude ORDER BY p_views DESC LIMIT $limit");
        if ($r) while ($row=$r->fetch_assoc()) $personalities[] = $row;
    }
} else {
    $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 ORDER BY p_views DESC LIMIT 10");
    if ($r) while ($row=$r->fetch_assoc()) $personalities[] = $row;
}

// Most visited institutions
$institutions = [];
if ($cid) {
    $r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 AND inst_country_id=$cid ORDER BY inst_views DESC LIMIT 5");
    if ($r) while ($row=$r->fetch_assoc()) $institutions[] = $row;
    if (count($institutions) < 5) {
        $exclude_ids = array_column($institutions, 'inst_id');
        $ex = $exclude_ids ? 'AND inst_id NOT IN ('.implode(',', $exclude_ids).')' : '';
        $lim = 5 - count($institutions);
        $r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 $ex ORDER BY inst_views DESC LIMIT $lim");
        if ($r) while ($row=$r->fetch_assoc()) $institutions[] = $row;
    }
} else {
    $r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 ORDER BY inst_views DESC LIMIT 5");
    if ($r) while ($row=$r->fetch_assoc()) $institutions[] = $row;
}

// Featured categories
$categories = pi_get_categories();
$feat_cats  = array_slice($categories, 0, 10);

include 'includes/header.php';
?>

<!-- HERO SECTION -->
<section class="hero-bg py-24 text-white">
  <div class="hero-glow"></div>
  <div class="hero-glow-2"></div>
  <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
    <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-5 py-2 text-sm font-semibold mb-6 text-purple-200 backdrop-blur-sm">
      <i class="fa-solid fa-circle-check text-purple-300 text-xs"></i>
      المنصة العربية الأولى للحضور الموثق
    </div>
    <h1 class="text-4xl md:text-6xl font-black mb-5 leading-tight tracking-tight">
      <?= htmlspecialchars($_S['site_tagline'] ?? 'منصة الحضور العربي الموثق') ?>
    </h1>
    <p class="text-lg text-purple-200 mb-10 font-medium max-w-xl mx-auto leading-relaxed"><?= htmlspecialchars($_S['site_description'] ?? 'تحكم بما يعرفه الناس عنك') ?></p>

    <form action="search.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl max-w-2xl mx-auto border border-white/10 backdrop-blur-sm">
      <?php if ($cid): ?><input type="hidden" name="country" value="<?= $cid ?>"><?php endif; ?>
      <div class="flex items-center bg-white px-4 rounded-r-2xl">
        <i class="fa-solid fa-magnifying-glass text-gray-400 text-lg"></i>
      </div>
      <input name="q" type="text"
        placeholder="ابحث عن <?= number_format($total_count) ?> شخصية ومؤسسة..."
        class="flex-1 px-4 py-4 text-gray-800 text-base outline-none font-semibold placeholder-gray-400"
        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
      <button type="submit" class="pi-primary-bg px-8 py-4 font-bold text-white hover:opacity-90 transition whitespace-nowrap rounded-l-2xl">
        ابحث الآن
      </button>
    </form>

    <div class="flex items-center justify-center gap-8 mt-10 text-purple-300 text-sm font-semibold">
      <span class="flex items-center gap-2"><i class="fa-solid fa-users"></i> <?= number_format(pi_count_personalities()) ?> شخصية</span>
      <span class="w-px h-4 bg-white/20"></span>
      <span class="flex items-center gap-2"><i class="fa-solid fa-building"></i> <?= number_format(pi_count_institutions()) ?> مؤسسة</span>
      <span class="w-px h-4 bg-white/20"></span>
      <span class="flex items-center gap-2"><i class="fa-solid fa-globe-asia"></i> أكثر من 17 دولة</span>
    </div>
  </div>
</section>

<!-- MOST VISITED PERSONALITIES -->
<section class="max-w-7xl mx-auto px-4 py-16">
  <div class="flex items-center justify-between mb-8">
    <div>
      <h2 class="text-2xl font-black text-gray-800 section-dot">
        الشخصيات الأكثر زيارة
      </h2>
      <?php if ($cid): ?><p class="text-sm text-gray-400 mt-1">مع أولوية الدولة المختارة</p><?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
    <?php if (empty($personalities)): ?>
      <?php for ($i=0; $i<10; $i++): ?>
      <div class="bg-white rounded-2xl p-4 shadow-sm text-center animate-pulse">
        <div class="w-20 h-20 rounded-full bg-gray-200 mx-auto mb-3"></div>
        <div class="h-4 bg-gray-200 rounded w-3/4 mx-auto mb-2"></div>
        <div class="h-3 bg-gray-100 rounded w-1/2 mx-auto"></div>
      </div>
      <?php endfor; ?>
    <?php else: ?>
      <?php foreach ($personalities as $i => $p): ?>
      <a href="profile.php?id=<?= $p['p_id'] ?><?= $cid ? '&country='.$cid : '' ?>"
        class="bg-white rounded-2xl p-4 shadow-sm card-hover text-center block relative">
        <?php if ($cid && ($p['p_country_id']??0) == $cid): ?>
        <span class="absolute top-2 left-2 w-2 h-2 bg-purple-500 rounded-full" title="من الدولة المختارة"></span>
        <?php endif; ?>
        <?php if ($p['p_photo']): ?>
          <img src="<?= htmlspecialchars($p['p_photo']) ?>" alt="<?= htmlspecialchars($p['p_name_ar']) ?>"
            class="w-20 h-20 rounded-full mx-auto mb-3 object-cover border-2 border-purple-100">
        <?php else: ?>
          <div class="w-20 h-20 rounded-full mx-auto mb-3 bg-gradient-to-br from-blue-400 to-blue-700 flex items-center justify-center">
            <span class="text-white font-bold text-2xl"><?= mb_substr($p['p_name_ar'],0,1) ?></span>
          </div>
        <?php endif; ?>
        <h3 class="font-bold text-gray-800 text-sm leading-tight mb-1">
          <?= htmlspecialchars($p['p_name_ar']) ?>
          <?php if ($p['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
        </h3>
        <p class="text-gray-400 text-xs"><?= htmlspecialchars($p['p_title'] ?? '') ?></p>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="text-center mt-8">
    <a href="categories.php<?= $cid ? '?country='.$cid : '' ?>"
      class="inline-flex items-center gap-2 px-8 py-3 border-2 border-purple-400 text-purple-600 font-bold rounded-full hover:bg-purple-50 transition">
      تصفح المزيد <i class="fa-solid fa-arrow-left"></i>
    </a>
  </div>
</section>

<!-- MOST VISITED INSTITUTIONS -->
<section class="bg-gray-50 py-16 border-t border-gray-100">
  <div class="max-w-7xl mx-auto px-4">
    <h2 class="text-2xl font-black text-gray-800 mb-8 section-dot">المؤسسات الأكثر زيارة</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <?php if (empty($institutions)): ?>
        <?php for ($i=0; $i<5; $i++): ?>
        <div class="bg-white rounded-2xl p-5 shadow-sm text-center animate-pulse">
          <div class="w-16 h-16 rounded-xl bg-gray-200 mx-auto mb-3"></div>
          <div class="h-4 bg-gray-200 rounded w-3/4 mx-auto"></div>
        </div>
        <?php endfor; ?>
      <?php else: ?>
        <?php foreach ($institutions as $inst): ?>
        <a href="institution.php?id=<?= $inst['inst_id'] ?><?= $cid ? '&country='.$cid : '' ?>"
          class="bg-white rounded-2xl p-5 shadow-sm card-hover text-center block">
          <?php if ($inst['inst_logo']): ?>
            <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" class="w-16 h-16 rounded-xl mx-auto mb-3 object-contain">
          <?php else: ?>
            <div class="w-16 h-16 rounded-xl mx-auto mb-3 pi-gradient flex items-center justify-center">
              <i class="fa-solid fa-building text-white text-xl"></i>
            </div>
          <?php endif; ?>
          <h3 class="font-bold text-gray-800 text-sm">
            <?= htmlspecialchars($inst['inst_name_ar']) ?>
            <?php if ($inst['inst_verified']): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
          </h3>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="text-center mt-8">
      <a href="categories.php?type=institutions<?= $cid ? '&country='.$cid : '' ?>"
        class="inline-flex items-center gap-2 px-8 py-3 border-2 border-purple-400 text-purple-600 font-bold rounded-full hover:bg-purple-50 transition">
        عرض الكل <i class="fa-solid fa-arrow-left"></i>
      </a>
    </div>
  </div>
</section>

<!-- CATEGORIES -->
<section class="max-w-7xl mx-auto px-4 py-16">
  <h2 class="text-2xl font-black text-gray-800 mb-8 text-center section-dot">استكشف التصنيفات</h2>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-4">
    <?php foreach (array_slice($feat_cats, 0, 5) as $cat): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm card-hover text-center">
      <div class="w-14 h-14 rounded-xl mx-auto mb-3 pi-gradient flex items-center justify-center">
        <i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?> text-white text-xl"></i>
      </div>
      <h3 class="font-bold text-gray-800 text-sm mb-3"><?= htmlspecialchars($cat['cat_name']) ?></h3>
      <a href="categories.php?cat=<?= $cat['cat_id'] ?><?= $cid ? '&country='.$cid : '' ?>"
        class="inline-block px-4 py-1.5 pi-primary-bg text-white text-xs font-bold rounded-full hover:opacity-90 transition">
        عرض الكل
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($feat_cats) > 5): ?>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
    <?php foreach (array_slice($feat_cats, 5, 5) as $cat): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm card-hover text-center">
      <div class="w-14 h-14 rounded-xl mx-auto mb-3 pi-gradient flex items-center justify-center">
        <i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?> text-white text-xl"></i>
      </div>
      <h3 class="font-bold text-gray-800 text-sm mb-3"><?= htmlspecialchars($cat['cat_name']) ?></h3>
      <a href="categories.php?cat=<?= $cat['cat_id'] ?><?= $cid ? '&country='.$cid : '' ?>"
        class="inline-block px-4 py-1.5 pi-primary-bg text-white text-xs font-bold rounded-full hover:opacity-90 transition">
        عرض الكل
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
