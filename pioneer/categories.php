<?php
$pageTitle = 'التصنيفات - PioneerIcons';
require_once 'includes/config.php';

$total_count = pi_count_personalities() + pi_count_institutions();
$per_page = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$search = pi_escape($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);

// Count and fetch categories
$all_cats = pi_get_categories();
$total_cats = count($all_cats);
$total_pages = max(1, ceil($total_cats / $per_page));
$page_cats = array_slice($all_cats, $offset, $per_page);

// If cat filter is set, load category details + personalities + institutions
$current_cat = null;
$cat_personalities = [];
$cat_institutions = [];
if ($cat_filter) {
    $r = $mysqli->query("SELECT * FROM pi_categories WHERE cat_id=$cat_filter AND cat_active=1");
    if ($r && $r->num_rows) $current_cat = $r->fetch_assoc();

    $r = $mysqli->query("SELECT p.* FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id=$cat_filter AND p.p_active=1 ORDER BY p.p_views DESC LIMIT 20");
    if ($r) while ($row = $r->fetch_assoc()) $cat_personalities[] = $row;

    $r = $mysqli->query("SELECT i.* FROM pi_institutions i JOIN pi_institution_categories ic ON i.inst_id=ic.inst_id WHERE ic.cat_id=$cat_filter AND i.inst_active=1 ORDER BY i.inst_views DESC LIMIT 20");
    if ($r) while ($row = $r->fetch_assoc()) $cat_institutions[] = $row;

    if (!$current_cat) { header('Location: categories.php'); exit; }
    $pageTitle = htmlspecialchars($current_cat['cat_name']) . ' - التصنيفات - PioneerIcons';
}

include 'includes/header.php';
?>

<!-- HERO / SEARCH -->
<section class="hero-bg py-12">
  <div class="max-w-3xl mx-auto px-4">
    <form action="search.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl">
      <div class="flex items-center bg-white px-4 gap-2">
        <i class="fa-solid fa-magnifying-glass text-gray-400 text-lg"></i>
      </div>
      <input name="q" type="text"
        placeholder="ابحث عن <?= number_format($total_count) ?> شخصية ومؤسسة..."
        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
        class="flex-1 px-4 py-4 text-gray-800 outline-none font-semibold placeholder-gray-400">
      <span class="bg-white flex items-center px-4 text-gray-500 text-sm font-bold border-r border-gray-200">
        شخصية · مؤسسة
      </span>
      <button type="submit" class="pi-primary-bg px-8 py-4 text-white font-bold hover:opacity-90 transition whitespace-nowrap">
        ابحث
      </button>
    </form>
  </div>
</section>

<!-- BREADCRUMB -->
<div class="max-w-7xl mx-auto px-4 py-4">
  <nav class="flex items-center gap-2 text-sm text-gray-500">
    <a href="index.php" class="hover:text-purple-600 transition font-semibold">الرئيسية</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <a href="categories.php" class="hover:text-purple-600 transition font-semibold">التصنيفات</a>
    <?php if ($current_cat): ?>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <span class="text-gray-800 font-semibold"><?= htmlspecialchars($current_cat['cat_name']) ?></span>
    <?php endif; ?>
  </nav>
</div>

<?php if ($cat_filter && $current_cat): ?>
<!-- CATEGORY DETAIL VIEW -->
<div class="max-w-7xl mx-auto px-4 py-6">

  <!-- Category header -->
  <div class="bg-white rounded-2xl shadow-sm p-6 mb-8 flex items-center gap-5">
    <?php
    $badge_colors = ['orange'=>'bg-orange-500','blue'=>'bg-blue-500','purple'=>'bg-purple-500',
      'cyan'=>'bg-cyan-500','red'=>'bg-red-500','green'=>'bg-green-500','gold'=>'bg-yellow-500',
      'navy'=>'bg-indigo-600','teal'=>'bg-teal-500','brown'=>'bg-amber-600','gray'=>'bg-gray-500','darkblue'=>'bg-blue-800'];
    $bc = $badge_colors[$current_cat['cat_badge_color']] ?? 'bg-gray-400';
    ?>
    <div class="w-16 h-16 rounded-2xl <?= $bc ?> flex items-center justify-center flex-shrink-0">
      <i class="fa-solid <?= htmlspecialchars($current_cat['cat_icon']) ?> text-white text-2xl"></i>
    </div>
    <div>
      <h1 class="text-2xl font-black text-gray-800"><?= htmlspecialchars($current_cat['cat_name']) ?></h1>
      <?php if (!empty($current_cat['cat_description'])): ?>
        <p class="text-gray-500 text-sm mt-1"><?= htmlspecialchars($current_cat['cat_description']) ?></p>
      <?php endif; ?>
      <div class="flex gap-4 mt-2 text-sm text-gray-400 font-semibold">
        <span><i class="fa-solid fa-users text-purple-400 ml-1"></i><?= count($cat_personalities) ?> شخصية</span>
        <span><i class="fa-solid fa-building text-purple-400 ml-1"></i><?= count($cat_institutions) ?> مؤسسة</span>
      </div>
    </div>
  </div>

  <!-- Tabs using Alpine.js -->
  <div x-data="{ tab: 'personalities' }">
    <div class="flex border-b border-gray-200 mb-6">
      <button @click="tab='personalities'"
        :class="tab==='personalities' ? 'border-b-2 border-purple-500 text-purple-700 font-bold' : 'text-gray-500 hover:text-gray-700'"
        class="px-6 py-3.5 text-sm font-semibold transition flex items-center gap-2">
        <i class="fa-solid fa-users text-sm"></i>
        الشخصيات
        <span class="bg-purple-100 text-purple-700 text-xs font-black px-2 py-0.5 rounded-full"><?= count($cat_personalities) ?></span>
      </button>
      <button @click="tab='institutions'"
        :class="tab==='institutions' ? 'border-b-2 border-purple-500 text-purple-700 font-bold' : 'text-gray-500 hover:text-gray-700'"
        class="px-6 py-3.5 text-sm font-semibold transition flex items-center gap-2">
        <i class="fa-solid fa-building text-sm"></i>
        المؤسسات
        <span class="bg-purple-100 text-purple-700 text-xs font-black px-2 py-0.5 rounded-full"><?= count($cat_institutions) ?></span>
      </button>
    </div>

    <!-- Personalities tab -->
    <div x-show="tab==='personalities'">
      <?php if (empty($cat_personalities)): ?>
      <div class="text-center py-16 text-gray-400">
        <i class="fa-solid fa-users text-4xl mb-3"></i>
        <p class="font-semibold">لا توجد شخصيات في هذا التصنيف</p>
      </div>
      <?php else: ?>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
        <?php foreach ($cat_personalities as $p): ?>
        <a href="profile.php?id=<?= $p['p_id'] ?>" class="bg-white rounded-2xl p-4 shadow-sm card-hover text-center block">
          <?php if (!empty($p['p_photo'])): ?>
            <img src="<?= htmlspecialchars($p['p_photo']) ?>" alt="<?= htmlspecialchars($p['p_name_ar']) ?>"
              class="w-18 h-18 rounded-full mx-auto mb-3 object-cover border-2 border-purple-100 w-16 h-16">
          <?php else: ?>
            <div class="w-16 h-16 rounded-full pi-gradient flex items-center justify-center mx-auto mb-3">
              <span class="text-white font-black text-xl"><?= mb_substr($p['p_name_ar'], 0, 1) ?></span>
            </div>
          <?php endif; ?>
          <h3 class="font-bold text-gray-800 text-sm mb-0.5 leading-tight">
            <?= htmlspecialchars($p['p_name_ar']) ?>
            <?php if (!empty($p['p_verified'])): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
          </h3>
          <?php if (!empty($p['p_title'])): ?>
            <p class="text-gray-400 text-xs"><?= htmlspecialchars($p['p_title']) ?></p>
          <?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Institutions tab -->
    <div x-show="tab==='institutions'" x-cloak>
      <?php if (empty($cat_institutions)): ?>
      <div class="text-center py-16 text-gray-400">
        <i class="fa-solid fa-building text-4xl mb-3"></i>
        <p class="font-semibold">لا توجد مؤسسات في هذا التصنيف</p>
      </div>
      <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach ($cat_institutions as $inst): ?>
        <a href="institution.php?id=<?= $inst['inst_id'] ?>" class="bg-white rounded-2xl p-5 shadow-sm card-hover block">
          <div class="flex items-center gap-4 mb-3">
            <?php if (!empty($inst['inst_logo'])): ?>
              <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" alt="<?= htmlspecialchars($inst['inst_name_ar']) ?>"
                class="w-12 h-12 rounded-xl object-cover border-2 border-gray-100 flex-shrink-0">
            <?php else: ?>
              <div class="w-12 h-12 rounded-xl pi-gradient flex items-center justify-center flex-shrink-0">
                <span class="text-white font-black text-lg"><?= mb_substr($inst['inst_name_ar'], 0, 1) ?></span>
              </div>
            <?php endif; ?>
            <div>
              <h3 class="font-bold text-gray-800 text-sm leading-tight">
                <?= htmlspecialchars($inst['inst_name_ar']) ?>
                <?php if (!empty($inst['inst_verified'])): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
              </h3>
              <?php if (!empty($inst['inst_name_en'])): ?>
                <p class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($inst['inst_name_en']) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php else: ?>
<!-- CATEGORIES GRID -->
<section class="max-w-7xl mx-auto px-4 py-6">
  <h2 class="text-2xl font-black text-gray-800 mb-8">
    التصنيفات
    <span class="text-base font-normal text-gray-400 mr-2">(<?= $total_cats ?> تصنيف)</span>
  </h2>

  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
    <?php foreach ($page_cats as $cat): ?>
    <div class="bg-white rounded-2xl p-5 shadow-sm card-hover text-center relative">
      <?php
      $badge_colors = ['orange'=>'bg-purple-500','blue'=>'bg-blue-500','purple'=>'bg-purple-500',
        'cyan'=>'bg-cyan-500','red'=>'bg-red-500','green'=>'bg-green-500','gold'=>'bg-yellow-500',
        'navy'=>'bg-indigo-600','teal'=>'bg-teal-500','brown'=>'bg-amber-600','gray'=>'bg-gray-500','darkblue'=>'bg-blue-800'];
      $bc = $badge_colors[$cat['cat_badge_color']] ?? 'bg-gray-400';
      ?>
      <div class="absolute top-3 left-3">
        <span class="<?= $bc ?> text-white text-xs font-bold px-2 py-0.5 rounded-full">
          <?= ucfirst($cat['cat_badge_color']) ?>
        </span>
      </div>
      <div class="w-14 h-14 rounded-xl mx-auto mb-3 mt-4 pi-gradient flex items-center justify-center">
        <i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?> text-white text-xl"></i>
      </div>
      <h3 class="font-bold text-gray-800 text-sm mb-3 leading-tight"><?= htmlspecialchars($cat['cat_name']) ?></h3>
      <a href="categories.php?cat=<?= $cat['cat_id'] ?>"
        class="inline-block px-4 py-1.5 pi-primary-bg text-white text-xs font-bold rounded-full hover:opacity-90 transition">
        عرض الكل
      </a>
    </div>
    <?php endforeach; ?>

    <?php if (empty($page_cats)): ?>
    <div class="col-span-5 text-center py-16 text-gray-400">
      <i class="fa-solid fa-folder-open text-5xl mb-4"></i>
      <p class="font-semibold">لا توجد تصنيفات بعد</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="flex items-center justify-between mt-10">
    <?php if ($page < $total_pages): ?>
    <a href="categories.php?page=<?= $page+1 ?>&q=<?= urlencode($_GET['q']??'') ?>"
      class="flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200 rounded-full font-semibold text-gray-700 hover:bg-purple-50 hover:border-purple-300 hover:text-purple-600 transition">
      <i class="fa-solid fa-arrow-right"></i> التالي
    </a>
    <?php else: ?><span></span><?php endif; ?>

    <div class="flex items-center gap-2">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
      <a href="categories.php?page=<?=$i?>&q=<?=urlencode($_GET['q']??'')?>"
        class="w-9 h-9 flex items-center justify-center rounded-full font-bold transition
          <?= $i==$page ? 'pi-primary-bg text-white' : 'bg-white text-gray-600 hover:bg-purple-50 hover:text-purple-600 border border-gray-200' ?>">
        <?=$i?>
      </a>
      <?php endfor; ?>
    </div>

    <?php if ($page > 1): ?>
    <a href="categories.php?page=<?= $page-1 ?>&q=<?= urlencode($_GET['q']??'') ?>"
      class="flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200 rounded-full font-semibold text-gray-700 hover:bg-purple-50 hover:border-purple-300 hover:text-purple-600 transition">
      السابق <i class="fa-solid fa-arrow-left"></i>
    </a>
    <?php else: ?><span></span><?php endif; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
