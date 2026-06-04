<?php
require_once 'includes/config.php';

$pageTitle = 'الشخصيات - PioneerIcons';
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
$join = $cat_filter ? " JOIN pi_personality_categories pc ON p.p_id=pc.p_id" : "";
$where = "WHERE p.p_active=1";
if ($cat_filter) $where .= " AND pc.cat_id=$cat_filter";
if ($search) $where .= " AND (p.p_name_ar LIKE '%$search%' OR p.p_title LIKE '%$search%')";
if ($cid) $where .= " AND p.p_country_id=$cid";

// ORDER
$order = match($sort) {
    'name' => 'p.p_name_ar ASC',
    'new'  => 'p.p_id DESC',
    default => 'p.p_views DESC',
};

// Count
$r = $mysqli->query("SELECT COUNT(*) as c FROM pi_personalities p$join $where");
$total = $r ? (int)$r->fetch_assoc()['c'] : 0;
$total_pages = max(1, ceil($total / $per_page));

// Fetch
$personalities = [];
$r = $mysqli->query("SELECT p.* FROM pi_personalities p$join $where ORDER BY $order LIMIT $per_page OFFSET $offset");
if ($r) while ($row = $r->fetch_assoc()) $personalities[] = $row;

$countries = pi_get_countries();

// Load cat name if filtered
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
    <h1 class="text-3xl font-black mb-3"><?= $cat_name ? htmlspecialchars($cat_name) . ' — الشخصيات' : 'الشخصيات' ?></h1>
    <p class="text-purple-200 mb-8 font-medium">تصفّح <?= number_format($total) ?> شخصية<?= $cat_name ? ' في هذا التصنيف' : ' عربية موثقة' ?></p>
    <form action="personalities.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl">
      <div class="flex items-center bg-white px-4"><i class="fa-solid fa-magnifying-glass text-gray-400 text-lg"></i></div>
      <input name="q" type="text" placeholder="ابحث باسم الشخصية أو المنصب..."
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
    <span class="text-gray-800 font-semibold">الشخصيات</span>
    <?php else: ?>
    <span class="text-gray-800 font-semibold">الشخصيات</span>
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
          'views' => ['الأكثر زيارة', 'fa-fire',         'text-orange-500'],
          'new'   => ['الأحدث',        'fa-clock',        'text-blue-500'],
          'name'  => ['أبجدي',          'fa-arrow-down-a-z','text-green-600'],
        ];
        foreach ($sorts as $sv => [$sl, $si, $ic]):
          $active = $sort === $sv;
          $params = array_merge(array_filter(['q'=>$_GET['q']??'','country'=>$cid?:'','cat'=>$cat_filter?:'']), ['sort'=>$sv,'page'=>1]);
        ?>
        <a href="personalities.php?<?= http_build_query($params) ?>"
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
      <form method="GET" action="personalities.php" class="flex items-center gap-2 px-5 py-3">
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
        <span class="text-xs font-semibold text-gray-400 mr-1">شخصية</span>
      </div>

    </div>
  </div>

  <?php if (empty($personalities)): ?>
  <div class="text-center py-20 text-gray-400">
    <i class="fa-solid fa-users text-5xl mb-4"></i>
    <p class="font-semibold text-lg">لا توجد شخصيات<?= $search ? ' لهذا البحث' : '' ?></p>
    <?php if ($search): ?>
    <a href="personalities.php" class="mt-4 inline-block text-purple-600 font-bold hover:underline">عرض جميع الشخصيات</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
    <?php foreach ($personalities as $p): ?>
    <a href="profile.php?id=<?= $p['p_id'] ?>" class="bg-white rounded-2xl p-4 shadow-sm card-hover text-center block">
      <?php if (!empty($p['p_photo'])): ?>
        <img src="<?= htmlspecialchars($p['p_photo']) ?>" alt="<?= htmlspecialchars($p['p_name_ar']) ?>"
          class="w-20 h-20 rounded-full mx-auto mb-3 object-cover border-2 border-purple-100">
      <?php else: ?>
        <div class="w-20 h-20 rounded-full pi-gradient flex items-center justify-center mx-auto mb-3">
          <span class="text-white font-black text-2xl"><?= mb_substr($p['p_name_ar'], 0, 1) ?></span>
        </div>
      <?php endif; ?>
      <h3 class="font-bold text-gray-800 text-sm mb-0.5 leading-tight">
        <?= htmlspecialchars($p['p_name_ar']) ?>
        <?php if (!empty($p['p_verified'])): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
      </h3>
      <?php if (!empty($p['p_title'])): ?>
        <p class="text-gray-400 text-xs leading-tight"><?= htmlspecialchars($p['p_title']) ?></p>
      <?php endif; ?>
      <p class="text-gray-300 text-xs mt-2"><i class="fa-solid fa-eye text-xs ml-1"></i><?= number_format($p['p_views'] ?? 0) ?></p>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <?php
  $q_params = array_filter(['q' => $_GET['q'] ?? '', 'country' => $cid ?: '', 'sort' => $sort != 'views' ? $sort : '']);
  ?>
  <div class="flex items-center justify-between mt-10">
    <?php if ($page < $total_pages): ?>
    <a href="personalities.php?<?= http_build_query(array_merge($q_params, ['page' => $page+1])) ?>"
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
      <a href="personalities.php?<?= http_build_query(array_merge($q_params, ['page' => $i])) ?>"
        class="w-9 h-9 flex items-center justify-center rounded-full font-bold transition
          <?= $i == $page ? 'pi-primary-bg text-white' : 'bg-white text-gray-600 hover:bg-purple-50 hover:text-purple-600 border border-gray-200' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>
    </div>

    <?php if ($page > 1): ?>
    <a href="personalities.php?<?= http_build_query(array_merge($q_params, ['page' => $page-1])) ?>"
      class="flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200 rounded-full font-semibold text-gray-700 hover:bg-purple-50 hover:border-purple-300 hover:text-purple-600 transition">
      السابق <i class="fa-solid fa-arrow-left"></i>
    </a>
    <?php else: ?><span></span><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
