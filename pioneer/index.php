<?php
$pageTitle = 'PioneerIcons - من هم | منصة الحضور العربي الموثق';
require_once 'includes/config.php';

$total_count = pi_count_personalities() + pi_count_institutions();

// Most visited personalities
$personalities = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 ORDER BY p_views DESC LIMIT 10");
if ($r) while ($row = $r->fetch_assoc()) $personalities[] = $row;

// Most visited institutions
$institutions = [];
$r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 ORDER BY inst_views DESC LIMIT 5");
if ($r) while ($row = $r->fetch_assoc()) $institutions[] = $row;

// Featured categories (8)
$categories = pi_get_categories();
$feat_cats = array_slice($categories, 0, 8);

// Daily personality
$daily = null;
$r = $mysqli->query("SELECT p.* FROM pi_personalities p JOIN pi_daily_personality dp ON p.p_id=dp.dp_p_id WHERE dp.dp_date=CURDATE() AND p.p_active=1 LIMIT 1");
if ($r && $r->num_rows) $daily = $r->fetch_assoc();
if (!$daily) {
    $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 AND p_verified=1 ORDER BY RAND() LIMIT 1");
    if ($r && $r->num_rows) $daily = $r->fetch_assoc();
}

// Executive personalities
$execs = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_membership_type='executive' AND p_active=1 LIMIT 4");
if ($r) while ($row = $r->fetch_assoc()) $execs[] = $row;

include 'includes/header.php';
?>

<!-- HERO SECTION -->
<section class="hero-bg py-20 text-white">
  <div class="max-w-4xl mx-auto px-4 text-center">
    <h1 class="text-4xl md:text-5xl font-black mb-4 leading-tight">
      منصة الحضور العربي الموثق
      <i class="fa-solid fa-circle-check text-blue-400 text-3xl mr-2 align-middle"></i>
    </h1>
    <p class="text-lg text-blue-100 mb-10 font-medium">تحكم بما يعرفه الناس عنك</p>

    <!-- Search bar -->
    <form action="search.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl max-w-2xl mx-auto">
      <div class="flex items-center bg-white px-4">
        <i class="fa-solid fa-magnifying-glass text-gray-400 text-lg"></i>
      </div>
      <input name="q" type="text" placeholder="ابحث عن <?= number_format($total_count) ?> شخصية ومؤسسة..."
        class="flex-1 px-4 py-4 text-gray-800 text-base outline-none font-semibold placeholder-gray-400"
        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
      <button type="submit"
        class="pi-orange-bg px-8 py-4 font-bold text-white hover:opacity-90 transition whitespace-nowrap">
        ابحث
      </button>
    </form>
  </div>
</section>

<!-- MOST VISITED PERSONALITIES -->
<section class="max-w-7xl mx-auto px-4 py-14">
  <div class="flex items-center justify-between mb-8">
    <h2 class="text-2xl font-black text-gray-800">الشخصيات الأكثر زيارة الآن</h2>
    <span class="text-gray-400 text-sm">يتم تحديثها بشكل مستمر</span>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
    <?php if (empty($personalities)): ?>
      <?php for ($i = 0; $i < 10; $i++): ?>
      <div class="bg-white rounded-2xl p-4 shadow-sm card-hover text-center animate-pulse">
        <div class="w-20 h-20 rounded-full bg-gray-200 mx-auto mb-3"></div>
        <div class="h-4 bg-gray-200 rounded w-3/4 mx-auto mb-2"></div>
        <div class="h-3 bg-gray-100 rounded w-1/2 mx-auto"></div>
      </div>
      <?php endfor; ?>
    <?php else: ?>
      <?php foreach ($personalities as $p): ?>
      <a href="profile.php?id=<?= $p['p_id'] ?>" class="bg-white rounded-2xl p-4 shadow-sm card-hover text-center block">
        <?php if ($p['p_photo']): ?>
          <img src="<?= htmlspecialchars($p['p_photo']) ?>" alt="<?= htmlspecialchars($p['p_name_ar']) ?>"
            class="w-20 h-20 rounded-full mx-auto mb-3 object-cover border-2 border-orange-100">
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
    <a href="categories.php"
      class="inline-flex items-center gap-2 px-8 py-3 border-2 border-orange-400 text-orange-500 font-bold rounded-full hover:bg-orange-50 transition">
      تصفح المزيد <i class="fa-solid fa-arrow-left"></i>
    </a>
  </div>
</section>

<!-- MOST VISITED INSTITUTIONS -->
<section class="bg-gray-100 py-14">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center justify-between mb-8">
      <h2 class="text-2xl font-black text-gray-800">المؤسسات الأكثر زيارة الآن</h2>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <?php if (empty($institutions)): ?>
        <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="bg-white rounded-2xl p-5 shadow-sm card-hover text-center animate-pulse">
          <div class="w-16 h-16 rounded-xl bg-gray-200 mx-auto mb-3"></div>
          <div class="h-4 bg-gray-200 rounded w-3/4 mx-auto"></div>
        </div>
        <?php endfor; ?>
      <?php else: ?>
        <?php foreach ($institutions as $inst): ?>
        <a href="institution.php?id=<?= $inst['inst_id'] ?>" class="bg-white rounded-2xl p-5 shadow-sm card-hover text-center block">
          <?php if ($inst['inst_logo']): ?>
            <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" alt="<?= htmlspecialchars($inst['inst_name_ar']) ?>"
              class="w-16 h-16 rounded-xl mx-auto mb-3 object-contain">
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
      <a href="categories.php?type=institutions"
        class="inline-flex items-center gap-2 px-8 py-3 border-2 border-blue-400 text-blue-600 font-bold rounded-full hover:bg-blue-50 transition">
        عرض الكل <i class="fa-solid fa-arrow-left"></i>
      </a>
    </div>
  </div>
</section>

<!-- CATEGORIES SECTION -->
<section class="max-w-7xl mx-auto px-4 py-14">
  <h2 class="text-2xl font-black text-gray-800 mb-8 text-center">استكشف التصنيفات</h2>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-4">
    <?php foreach (array_slice($feat_cats, 0, 5) as $cat): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm card-hover text-center">
      <div class="w-14 h-14 rounded-xl mx-auto mb-3 pi-gradient flex items-center justify-center">
        <i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?> text-white text-xl"></i>
      </div>
      <h3 class="font-bold text-gray-800 text-sm mb-3"><?= htmlspecialchars($cat['cat_name']) ?></h3>
      <a href="categories.php?cat=<?= $cat['cat_id'] ?>"
        class="inline-block px-4 py-1.5 pi-orange-bg text-white text-xs font-bold rounded-full hover:opacity-90 transition">
        عرض الكل
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
    <?php foreach (array_slice($feat_cats, 5, 5) as $cat): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm card-hover text-center">
      <div class="w-14 h-14 rounded-xl mx-auto mb-3 pi-gradient flex items-center justify-center">
        <i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?> text-white text-xl"></i>
      </div>
      <h3 class="font-bold text-gray-800 text-sm mb-3"><?= htmlspecialchars($cat['cat_name']) ?></h3>
      <a href="categories.php?cat=<?= $cat['cat_id'] ?>"
        class="inline-block px-4 py-1.5 pi-orange-bg text-white text-xs font-bold rounded-full hover:opacity-90 transition">
        عرض الكل
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
