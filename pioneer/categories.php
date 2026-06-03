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

include 'includes/header.php';
?>

<!-- HERO / SEARCH -->
<section class="hero-bg py-12">
  <div class="max-w-3xl mx-auto px-4">
    <form action="categories.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl">
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
      <button type="submit" class="pi-orange-bg px-8 py-4 text-white font-bold hover:opacity-90 transition whitespace-nowrap">
        ابحث
      </button>
    </form>
  </div>
</section>

<!-- BREADCRUMB -->
<div class="max-w-7xl mx-auto px-4 py-4">
  <nav class="flex items-center gap-2 text-sm text-gray-500">
    <a href="index.php" class="hover:text-orange-500 transition font-semibold">الرئيسية</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <span class="text-gray-800 font-semibold">التصنيفات</span>
  </nav>
</div>

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
      $badge_colors = ['orange'=>'bg-orange-400','blue'=>'bg-blue-500','purple'=>'bg-purple-500',
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
        class="inline-block px-4 py-1.5 pi-orange-bg text-white text-xs font-bold rounded-full hover:opacity-90 transition">
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
      class="flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200 rounded-full font-semibold text-gray-700 hover:bg-orange-50 hover:border-orange-300 hover:text-orange-500 transition">
      <i class="fa-solid fa-arrow-right"></i> التالي
    </a>
    <?php else: ?><span></span><?php endif; ?>

    <div class="flex items-center gap-2">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
      <a href="categories.php?page=<?=$i?>&q=<?=urlencode($_GET['q']??'')?>"
        class="w-9 h-9 flex items-center justify-center rounded-full font-bold transition
          <?= $i==$page ? 'pi-orange-bg text-white' : 'bg-white text-gray-600 hover:bg-orange-50 hover:text-orange-500 border border-gray-200' ?>">
        <?=$i?>
      </a>
      <?php endfor; ?>
    </div>

    <?php if ($page > 1): ?>
    <a href="categories.php?page=<?= $page-1 ?>&q=<?= urlencode($_GET['q']??'') ?>"
      class="flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200 rounded-full font-semibold text-gray-700 hover:bg-orange-50 hover:border-orange-300 hover:text-orange-500 transition">
      السابق <i class="fa-solid fa-arrow-left"></i>
    </a>
    <?php else: ?><span></span><?php endif; ?>
  </div>
  <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
