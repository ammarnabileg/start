<?php
$pageTitle = 'التصنيفات - PioneerIcons';
require_once 'includes/config.php';

function render_person_card($p) {
    ob_start(); ?>
    <a href="profile.php?id=<?= $p['p_id'] ?>" class="bg-white rounded-2xl p-3 shadow-sm card-hover text-center block">
      <?php if (!empty($p['p_photo'])): ?>
        <img src="<?= htmlspecialchars($p['p_photo']) ?>" alt="<?= htmlspecialchars($p['p_name_ar']) ?>"
          class="w-14 h-14 rounded-full mx-auto mb-2 object-cover border-2 border-purple-100">
      <?php else: ?>
        <div class="w-14 h-14 rounded-full pi-gradient flex items-center justify-center mx-auto mb-2">
          <span class="text-white font-black text-lg"><?= mb_substr($p['p_name_ar'], 0, 1) ?></span>
        </div>
      <?php endif; ?>
      <h3 class="font-bold text-gray-800 text-xs leading-tight">
        <?= htmlspecialchars($p['p_name_ar']) ?>
        <?php if (!empty($p['p_verified'])): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
      </h3>
      <?php if (!empty($p['p_title'])): ?>
        <p class="text-gray-400 text-xs mt-0.5 truncate"><?= htmlspecialchars($p['p_title']) ?></p>
      <?php endif; ?>
    </a>
    <?php return ob_get_clean();
}

$total_count = pi_count_personalities() + pi_count_institutions();
$per_page = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$search = pi_escape($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$sort = in_array($_GET['sort'] ?? '', ['views', 'name', 'new']) ? $_GET['sort'] : 'views';

// Count and fetch categories
$all_cats = pi_get_categories();
$total_cats = count($all_cats);
$total_pages = max(1, ceil($total_cats / $per_page));
$page_cats = array_slice($all_cats, $offset, $per_page);

// If cat filter is set, load category details
$current_cat = null;
$verified_personalities = [];
$top_visited = [];
$cat_institutions = [];
$cat_total_p = 0;
$cat_total_i = 0;

if ($cat_filter) {
    $r = $mysqli->query("SELECT c.*, l.label_name, l.label_color FROM pi_categories c LEFT JOIN pi_labels l ON c.cat_label_id=l.label_id WHERE c.cat_id=$cat_filter AND c.cat_active=1");
    if ($r && $r->num_rows) $current_cat = $r->fetch_assoc();
    if (!$current_cat) { header('Location: categories.php'); exit; }

    // Counts
    $rr = $mysqli->query("SELECT COUNT(*) c FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id=$cat_filter AND p.p_active=1");
    if ($rr) $cat_total_p = (int)$rr->fetch_assoc()['c'];
    $rr = $mysqli->query("SELECT COUNT(*) c FROM pi_institutions i JOIN pi_institution_categories ic ON i.inst_id=ic.inst_id WHERE ic.cat_id=$cat_filter AND i.inst_active=1");
    if ($rr) $cat_total_i = (int)$rr->fetch_assoc()['c'];

    $p_search_sql = $search ? " AND (p.p_name_ar LIKE '%$search%' OR p.p_title LIKE '%$search%')" : "";
    $i_search_sql = $search ? " AND (i.inst_name_ar LIKE '%$search%' OR i.inst_description LIKE '%$search%')" : "";

    $order_p = ($sort === 'name') ? 'p.p_name_ar ASC' : (($sort === 'new') ? 'p.p_id DESC' : 'p.p_views DESC');
    $order_i = ($sort === 'name') ? 'i.inst_name_ar ASC' : (($sort === 'new') ? 'i.inst_id DESC' : 'i.inst_views DESC');

    // Verified personalities (عضويات موثقة)
    $r = $mysqli->query("SELECT p.* FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id=$cat_filter AND p.p_active=1 AND p.p_verified=1$p_search_sql ORDER BY p.p_views DESC LIMIT 10");
    if ($r) while ($row=$r->fetch_assoc()) $verified_personalities[] = $row;

    // Top visited (الأكثر زيارة)
    $r = $mysqli->query("SELECT p.* FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id=$cat_filter AND p.p_active=1$p_search_sql ORDER BY $order_p LIMIT 10");
    if ($r) while ($row=$r->fetch_assoc()) $top_visited[] = $row;

    // Institutions
    $r = $mysqli->query("SELECT i.* FROM pi_institutions i JOIN pi_institution_categories ic ON i.inst_id=ic.inst_id WHERE ic.cat_id=$cat_filter AND i.inst_active=1$i_search_sql ORDER BY $order_i LIMIT 8");
    if ($r) while ($row=$r->fetch_assoc()) $cat_institutions[] = $row;

    $pageTitle = htmlspecialchars($current_cat['cat_name']) . ' - التصنيفات - PioneerIcons';
}

include 'includes/header.php';
?>

<!-- HERO / SEARCH -->
<section class="hero-bg py-12">
  <div class="max-w-3xl mx-auto px-4">
    <form action="search.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl border border-white/10">
      <input name="q" type="text"
        placeholder="ابحث عن <?= number_format($total_count) ?> شخصية ومؤسسة..."
        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
        style="flex:1;min-width:0;padding:16px 12px;background:#fff;color:#111827;font-size:15px;font-weight:600;outline:none;border:none;font-family:inherit;">
      <button type="submit" class="pi-primary-bg px-8 py-4 text-white font-bold hover:opacity-90 transition whitespace-nowrap flex-shrink-0">
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
<?php $bc_hex = $current_cat['label_color'] ?? '#8829C8'; ?>

<!-- CATEGORY BANNER -->
<div style="background:<?= htmlspecialchars($bc_hex) ?>18;border-bottom:1px solid <?= htmlspecialchars($bc_hex) ?>30;" class="py-8">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center gap-5">
      <div class="w-16 h-16 rounded-2xl flex items-center justify-center flex-shrink-0" style="background:<?= htmlspecialchars($bc_hex) ?>">
        <i class="fa-solid <?= htmlspecialchars($current_cat['cat_icon']) ?> text-white text-2xl"></i>
      </div>
      <div>
        <h1 class="text-3xl font-black text-gray-800"><?= htmlspecialchars($current_cat['cat_name']) ?></h1>
        <?php if (!empty($current_cat['cat_description'])): ?>
          <p class="text-gray-500 text-sm mt-1"><?= htmlspecialchars($current_cat['cat_description']) ?></p>
        <?php endif; ?>
        <div class="flex gap-4 mt-2 text-sm text-gray-400 font-semibold">
          <span><i class="fa-solid fa-users text-purple-400 ml-1"></i><?= number_format($cat_total_p) ?> شخصية</span>
          <span><i class="fa-solid fa-building text-purple-400 ml-1"></i><?= number_format($cat_total_i) ?> مؤسسة</span>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Build "عرض الكل" base params
$view_all_params = ['cat' => $cat_filter];
if ($sort && $sort !== 'views') $view_all_params['sort'] = $sort;
if ($search) $view_all_params['q'] = $_GET['q'] ?? '';
$sorts_meta = [
    'views' => ['الأكثر زيارة', 'fa-fire',          'text-orange-500'],
    'new'   => ['الأحدث',        'fa-clock',         'text-blue-500'],
    'name'  => ['أبجدي',          'fa-arrow-down-a-z','text-green-600'],
];
?>

<!-- FILTER BAR -->
<div class="max-w-7xl mx-auto px-4 pt-4 pb-2">
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="flex flex-wrap items-stretch divide-x divide-x-reverse divide-gray-100">

      <!-- Sort pills -->
      <div class="flex items-center gap-1 px-5 py-3 flex-1 flex-wrap">
        <span class="text-xs font-black text-gray-400 ml-2 whitespace-nowrap">ترتيب حسب:</span>
        <?php foreach ($sorts_meta as $sv => [$sl, $si, $ic]):
          $active = $sort === $sv;
          $sp = array_filter(['cat' => $cat_filter, 'q' => $_GET['q'] ?? '', 'sort' => $sv]);
        ?>
        <a href="categories.php?<?= http_build_query($sp) ?>"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-sm font-bold transition <?= $active ? 'bg-purple-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100' ?>">
          <i class="fa-solid <?= $si ?> text-xs <?= $active ? 'text-white' : $ic ?>"></i>
          <?= $sl ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Search -->
      <form method="GET" action="categories.php" class="flex items-center gap-2 px-5 py-3">
        <input type="hidden" name="cat" value="<?= $cat_filter ?>">
        <?php if ($sort !== 'views'): ?><input type="hidden" name="sort" value="<?= $sort ?>"><?php endif; ?>
        <i class="fa-solid fa-magnifying-glass text-purple-400 text-sm"></i>
        <input name="q" type="text" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
          placeholder="بحث في التصنيف..."
          class="border-0 bg-transparent text-sm font-semibold text-gray-700 focus:outline-none w-40 placeholder-gray-400">
      </form>

      <!-- Count -->
      <div class="flex items-center gap-3 px-5 py-3 bg-gray-50">
        <span class="text-sm font-black text-purple-700"><?= number_format($cat_total_p) ?></span>
        <span class="text-xs font-semibold text-gray-400">شخصية</span>
        <span class="text-gray-200">|</span>
        <span class="text-sm font-black text-indigo-700"><?= number_format($cat_total_i) ?></span>
        <span class="text-xs font-semibold text-gray-400">مؤسسة</span>
      </div>

    </div>
  </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-10">

  <!-- ===== SECTION 1: عضويات موثقة ===== -->
  <?php if (!empty($verified_personalities)): ?>
  <section>
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-blue-500 flex items-center justify-center">
          <i class="fa-solid fa-circle-check text-white text-sm"></i>
        </div>
        <h2 class="text-xl font-black text-gray-800">عضويات موثقة</h2>
        <span class="bg-blue-100 text-blue-700 text-xs font-black px-2.5 py-0.5 rounded-full"><?= count($verified_personalities) ?></span>
      </div>
      <a href="personalities.php?<?= http_build_query(array_merge($view_all_params, ['sort' => 'views'])) ?>"
        class="flex items-center gap-1.5 text-sm font-bold text-purple-600 hover:text-purple-800 transition">
        عرض الكل <i class="fa-solid fa-arrow-left text-xs"></i>
      </a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <?php foreach ($verified_personalities as $p): ?>
      <?= render_person_card($p) ?>
      <?php endforeach; ?>
    </div>
    <?php if ($cat_total_p > 10): ?>
    <div class="mt-5 text-center">
      <a href="personalities.php?<?= http_build_query($view_all_params) ?>"
        class="inline-flex items-center gap-2 px-8 py-2.5 bg-white border-2 border-purple-200 text-purple-600 font-bold rounded-full hover:bg-purple-50 hover:border-purple-400 transition text-sm">
        <i class="fa-solid fa-users text-xs"></i> عرض كل الشخصيات (<?= number_format($cat_total_p) ?>)
      </a>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- ===== SECTION 2: الشخصيات ===== -->
  <?php if (!empty($top_visited)): ?>
  <section>
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <?php
        $icon_meta = ($sort === 'new') ? ['bg-blue-500', 'fa-clock'] : (($sort === 'name') ? ['bg-green-600', 'fa-arrow-down-a-z'] : ['bg-orange-500', 'fa-fire']);
        ?>
        <div class="w-8 h-8 rounded-xl <?= $icon_meta[0] ?> flex items-center justify-center">
          <i class="fa-solid <?= $icon_meta[1] ?> text-white text-sm"></i>
        </div>
        <h2 class="text-xl font-black text-gray-800">الشخصيات</h2>
        <span class="bg-purple-100 text-purple-700 text-xs font-black px-2.5 py-0.5 rounded-full"><?= number_format($cat_total_p) ?></span>
      </div>
      <a href="personalities.php?<?= http_build_query($view_all_params) ?>"
        class="flex items-center gap-1.5 text-sm font-bold text-purple-600 hover:text-purple-800 transition">
        عرض الكل <i class="fa-solid fa-arrow-left text-xs"></i>
      </a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <?php foreach ($top_visited as $p): ?>
      <?= render_person_card($p) ?>
      <?php endforeach; ?>
    </div>
    <?php if ($cat_total_p > 10): ?>
    <div class="mt-5 text-center">
      <a href="personalities.php?<?= http_build_query($view_all_params) ?>"
        class="inline-flex items-center gap-2 px-8 py-2.5 bg-white border-2 border-purple-200 text-purple-600 font-bold rounded-full hover:bg-purple-50 hover:border-purple-400 transition text-sm">
        <i class="fa-solid fa-users text-xs"></i> عرض كل الشخصيات (<?= number_format($cat_total_p) ?>)
      </a>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- ===== SECTION 3: المؤسسات ===== -->
  <?php if (!empty($cat_institutions)): ?>
  <section>
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center">
          <i class="fa-solid fa-building text-white text-sm"></i>
        </div>
        <h2 class="text-xl font-black text-gray-800">المؤسسات</h2>
        <span class="bg-indigo-100 text-indigo-700 text-xs font-black px-2.5 py-0.5 rounded-full"><?= number_format($cat_total_i) ?></span>
      </div>
      <a href="all_institutions.php?<?= http_build_query($view_all_params) ?>"
        class="flex items-center gap-1.5 text-sm font-bold text-indigo-600 hover:text-indigo-800 transition">
        عرض الكل <i class="fa-solid fa-arrow-left text-xs"></i>
      </a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
      <?php foreach ($cat_institutions as $inst): ?>
      <a href="institution.php?id=<?= $inst['inst_id'] ?>" class="bg-white rounded-2xl p-5 shadow-sm card-hover block">
        <div class="flex items-center gap-4">
          <?php if (!empty($inst['inst_logo'])): ?>
            <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" alt="<?= htmlspecialchars($inst['inst_name_ar']) ?>"
              class="w-12 h-12 rounded-xl object-cover border-2 border-gray-100 flex-shrink-0">
          <?php else: ?>
            <div class="w-12 h-12 rounded-xl pi-gradient flex items-center justify-center flex-shrink-0">
              <span class="text-white font-black text-lg"><?= mb_substr($inst['inst_name_ar'], 0, 1) ?></span>
            </div>
          <?php endif; ?>
          <div class="flex-1 min-w-0">
            <h3 class="font-bold text-gray-800 text-sm leading-tight truncate">
              <?= htmlspecialchars($inst['inst_name_ar']) ?>
              <?php if (!empty($inst['inst_verified'])): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
            </h3>
            <?php if (!empty($inst['inst_name_en'])): ?>
              <p class="text-gray-400 text-xs mt-0.5 truncate"><?= htmlspecialchars($inst['inst_name_en']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if ($cat_total_i > 8): ?>
    <div class="mt-5 text-center">
      <a href="all_institutions.php?<?= http_build_query($view_all_params) ?>"
        class="inline-flex items-center gap-2 px-8 py-2.5 bg-white border-2 border-indigo-200 text-indigo-600 font-bold rounded-full hover:bg-indigo-50 hover:border-indigo-400 transition text-sm">
        <i class="fa-solid fa-building text-xs"></i> عرض كل المؤسسات (<?= number_format($cat_total_i) ?>)
      </a>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if (empty($verified_personalities) && empty($top_visited) && empty($cat_institutions)): ?>
  <div class="text-center py-20 text-gray-400">
    <i class="fa-solid fa-folder-open text-5xl mb-4"></i>
    <p class="font-semibold text-lg">لا توجد نتائج<?= $search ? ' لهذا البحث' : '' ?></p>
    <?php if ($search): ?>
    <a href="categories.php?cat=<?= $cat_filter ?>" class="mt-4 inline-block text-purple-600 font-bold hover:underline">عرض الكل</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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
      <?php $label_hex = $cat['label_color'] ?? null; ?>
      <?php if ($label_hex && $cat['label_name']): ?>
      <div class="absolute top-3 left-3">
        <span style="background:<?= htmlspecialchars($label_hex) ?>" class="text-white text-xs font-bold px-2 py-0.5 rounded-full">
          <?= htmlspecialchars($cat['label_name']) ?>
        </span>
      </div>
      <?php endif; ?>
      <div class="w-14 h-14 rounded-xl mx-auto mb-3 mt-4 flex items-center justify-center" style="background:<?= htmlspecialchars($label_hex ?? '#8829C8') ?>">
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
