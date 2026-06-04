<?php
require_once 'includes/config.php';

$pageTitle = 'المؤسسات - PioneerIcons';
$total_count = pi_count_personalities() + pi_count_institutions();
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$search = pi_escape($_GET['q'] ?? '');
$country_id = (int)($_GET['country'] ?? 0);
if ($country_id) $_SESSION['pi_country'] = $country_id;
$cid = pi_current_country();
$sort = in_array($_GET['sort'] ?? '', ['views', 'name', 'new']) ? $_GET['sort'] : 'views';
$cat_filter = (int)($_GET['cat'] ?? 0);

// Build WHERE
if ($cat_filter) {
    $from  = "pi_institutions i JOIN pi_institution_categories ic ON i.inst_id=ic.inst_id";
    $where = "WHERE i.inst_active=1 AND ic.cat_id=$cat_filter";
    if ($search) $where .= " AND (i.inst_name_ar LIKE '%$search%' OR i.inst_name_en LIKE '%$search%' OR i.inst_description LIKE '%$search%')";
    if ($cid)    $where .= " AND i.inst_country_id=$cid";
    $order = match($sort) {
        'name' => 'i.inst_name_ar ASC',
        'new'  => 'i.inst_id DESC',
        default => 'i.inst_views DESC',
    };
    $select = "i.*";
} else {
    $from  = "pi_institutions";
    $where = "WHERE inst_active=1";
    if ($search) $where .= " AND (inst_name_ar LIKE '%$search%' OR inst_name_en LIKE '%$search%' OR inst_description LIKE '%$search%')";
    if ($cid)    $where .= " AND inst_country_id=$cid";
    $order = match($sort) {
        'name' => 'inst_name_ar ASC',
        'new'  => 'inst_id DESC',
        default => 'inst_views DESC',
    };
    $select = "*";
}

// Count
$r = $mysqli->query("SELECT COUNT(*) as c FROM $from $where");
$total = $r ? (int)$r->fetch_assoc()['c'] : 0;
$total_pages = max(1, ceil($total / $per_page));

// Fetch
$institutions = [];
$r = $mysqli->query("SELECT $select FROM $from $where ORDER BY $order LIMIT $per_page OFFSET $offset");
if ($r) while ($row = $r->fetch_assoc()) $institutions[] = $row;

$countries = pi_get_countries();

$cat_name = '';
if ($cat_filter) {
    $rc = $mysqli->query("SELECT cat_name FROM pi_categories WHERE cat_id=$cat_filter AND cat_active=1");
    if ($rc && $rc->num_rows) $cat_name = $rc->fetch_assoc()['cat_name'];
}

include 'includes/header.php';
?>

<!-- HERO / SEARCH -->
<section class="hero-bg py-14 text-white">
  <div class="max-w-3xl mx-auto px-4 text-center">
    <h1 class="text-3xl font-black mb-3"><?= $cat_name ? htmlspecialchars($cat_name) . ' — المؤسسات' : 'المؤسسات' ?></h1>
    <p class="text-purple-200 mb-8 font-medium">تصفّح <?= number_format($total) ?> مؤسسة<?= $cat_name ? ' في هذا التصنيف' : ' عربية موثقة' ?></p>
    <form action="all_institutions.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl">
      <div class="flex items-center bg-white px-4"><i class="fa-solid fa-magnifying-glass text-gray-400 text-lg"></i></div>
      <input name="q" type="text" placeholder="ابحث باسم المؤسسة أو النوع..."
        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
        class="flex-1 px-4 py-3.5 text-gray-800 outline-none font-semibold placeholder-gray-400">
      <?php if ($cid): ?><input type="hidden" name="country" value="<?= $cid ?>"><?php endif; ?>
      <button type="submit" class="pi-primary-bg px-8 py-3.5 text-white font-bold hover:opacity-90 transition">ابحث</button>
    </form>
  </div>
</section>

<!-- BREADCRUMB -->
<div class="max-w-7xl mx-auto px-4 py-4">
  <nav class="flex items-center gap-2 text-sm text-gray-500">
    <a href="index.php" class="hover:text-purple-600 transition font-semibold">الرئيسية</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <?php if ($cat_name): ?>
    <a href="categories.php" class="hover:text-purple-600 transition font-semibold">التصنيفات</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <a href="categories.php?cat=<?= $cat_filter ?>" class="hover:text-purple-600 transition font-semibold"><?= htmlspecialchars($cat_name) ?></a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <span class="text-gray-800 font-semibold">المؤسسات</span>
    <?php else: ?>
    <span class="text-gray-800 font-semibold">المؤسسات</span>
    <?php endif; ?>
  </nav>
</div>

<!-- FILTERS & GRID -->
<section class="max-w-7xl mx-auto px-4 py-6">

  <!-- Filter bar -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-7 overflow-hidden">
    <div class="flex flex-wrap items-stretch divide-x divide-x-reverse divide-gray-100">

      <!-- Sort group -->
      <div class="flex items-center gap-1 px-5 py-3 flex-1 flex-wrap">
        <span class="text-xs font-black text-gray-400 ml-2 whitespace-nowrap">ترتيب حسب:</span>
        <?php
        $sorts = [
          'views' => ['الأكثر زيارة', 'fa-fire',          'text-orange-500'],
          'new'   => ['الأحدث',        'fa-clock',         'text-blue-500'],
          'name'  => ['أبجدي',          'fa-arrow-down-a-z','text-green-600'],
        ];
        foreach ($sorts as $sv => [$sl, $si, $ic]):
          $active = $sort === $sv;
          $params = array_merge(array_filter(['q'=>$_GET['q']??'','country'=>$cid?:'','cat'=>$cat_filter?:'']), ['sort'=>$sv,'page'=>1]);
        ?>
        <a href="all_institutions.php?<?= http_build_query($params) ?>"
          class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-sm font-bold transition <?= $active
            ? 'bg-purple-600 text-white shadow-sm'
            : 'text-gray-600 hover:bg-gray-100' ?>">
          <i class="fa-solid <?= $si ?> text-xs <?= $active ? 'text-white' : $ic ?>"></i>
          <?= $sl ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Country filter -->
      <?php if (!empty($countries)): ?>
      <form method="GET" action="all_institutions.php" class="flex items-center gap-2 px-5 py-3">
        <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"><?php endif; ?>
        <?php if ($sort && $sort !== 'views'): ?><input type="hidden" name="sort" value="<?= $sort ?>"><?php endif; ?>
        <?php if ($cat_filter): ?><input type="hidden" name="cat" value="<?= $cat_filter ?>"><?php endif; ?>
        <select name="country" onchange="this.form.submit()"
          class="border-0 bg-transparent text-xs font-bold text-gray-600 focus:outline-none cursor-pointer">
          <option value="0" <?= !$cid?'selected':'' ?>>كل الدول</option>
          <?php foreach ($countries as $c): ?>
          <option value="<?= $c['c_id'] ?>" <?= $cid==$c['c_id']?'selected':'' ?>>
            <?= htmlspecialchars(($c['c_flag']??'').' '.($c['c_name_ar']??$c['c_name'])) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>

      <!-- Count badge -->
      <div class="flex items-center px-5 py-3 bg-gray-50">
        <span class="text-sm font-black text-purple-700"><?= number_format($total) ?></span>
        <span class="text-xs font-semibold text-gray-400 mr-1">مؤسسة</span>
      </div>

    </div>
  </div>

  <?php if (empty($institutions)): ?>
  <div class="text-center py-20 text-gray-400">
    <i class="fa-solid fa-building text-5xl mb-4"></i>
    <p class="font-semibold text-lg">لا توجد مؤسسات<?= $search ? ' لهذا البحث' : '' ?></p>
    <?php if ($search): ?>
    <a href="all_institutions.php" class="mt-4 inline-block text-purple-600 font-bold hover:underline">عرض جميع المؤسسات</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    <?php foreach ($institutions as $inst): ?>
    <a href="institution.php?id=<?= $inst['inst_id'] ?>" class="bg-white rounded-2xl p-5 shadow-sm card-hover block">
      <div class="flex items-center gap-4 mb-3">
        <?php if (!empty($inst['inst_logo'])): ?>
          <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" alt="<?= htmlspecialchars($inst['inst_name_ar']) ?>"
            class="w-14 h-14 rounded-xl object-cover border-2 border-gray-100 flex-shrink-0">
        <?php else: ?>
          <div class="w-14 h-14 rounded-xl pi-gradient flex items-center justify-center flex-shrink-0">
            <span class="text-white font-black text-xl"><?= mb_substr($inst['inst_name_ar'], 0, 1) ?></span>
          </div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
          <h3 class="font-bold text-gray-800 text-sm leading-tight truncate">
            <?= htmlspecialchars($inst['inst_name_ar']) ?>
            <?php if (!empty($inst['inst_verified'])): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
          </h3>
          <?php if (!empty($inst['inst_name_en'])): ?>
            <p class="text-gray-400 text-xs mt-0.5 dir-ltr"><?= htmlspecialchars($inst['inst_name_en']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php $plain_desc = trim(strip_tags($inst['inst_description'] ?? '')); if ($plain_desc): ?>
        <p class="text-gray-500 text-xs leading-relaxed line-clamp-2">
          <?= htmlspecialchars(mb_substr($plain_desc, 0, 120)) ?>
        </p>
      <?php endif; ?>
      <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-50 text-xs text-gray-400">
        <?php if (!empty($inst['inst_country'])): ?>
          <span><i class="fa-solid fa-flag text-purple-400 ml-1"></i><?= htmlspecialchars($inst['inst_country']) ?></span>
        <?php endif; ?>
        <span><i class="fa-solid fa-eye ml-1"></i><?= number_format($inst['inst_views'] ?? 0) ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <?php $q_params = array_filter(['q' => $_GET['q'] ?? '', 'country' => $cid ?: '']); ?>
  <div class="flex items-center justify-between mt-10">
    <?php if ($page < $total_pages): ?>
    <a href="all_institutions.php?<?= http_build_query(array_merge($q_params, ['page' => $page+1])) ?>"
      class="flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200 rounded-full font-semibold text-gray-700 hover:bg-purple-50 hover:border-purple-300 hover:text-purple-600 transition">
      <i class="fa-solid fa-arrow-right"></i> التالي
    </a>
    <?php else: ?><span></span><?php endif; ?>

    <div class="flex items-center gap-2">
      <?php
      $start = max(1, $page - 2);
      $end = min($total_pages, $page + 2);
      for ($i = $start; $i <= $end; $i++):
      ?>
      <a href="all_institutions.php?<?= http_build_query(array_merge($q_params, ['page' => $i])) ?>"
        class="w-9 h-9 flex items-center justify-center rounded-full font-bold transition
          <?= $i == $page ? 'pi-primary-bg text-white' : 'bg-white text-gray-600 hover:bg-purple-50 hover:text-purple-600 border border-gray-200' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>
    </div>

    <?php if ($page > 1): ?>
    <a href="all_institutions.php?<?= http_build_query(array_merge($q_params, ['page' => $page-1])) ?>"
      class="flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200 rounded-full font-semibold text-gray-700 hover:bg-purple-50 hover:border-purple-300 hover:text-purple-600 transition">
      السابق <i class="fa-solid fa-arrow-left"></i>
    </a>
    <?php else: ?><span></span><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
