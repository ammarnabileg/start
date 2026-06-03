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

// Build WHERE
$where = "WHERE p.p_active=1";
if ($search) $where .= " AND (p.p_name_ar LIKE '%$search%' OR p.p_title LIKE '%$search%')";
if ($cid) $where .= " AND p.p_country_id=$cid";

// ORDER
$order = match($sort) {
    'name' => 'p.p_name_ar ASC',
    'new'  => 'p.p_id DESC',
    default => 'p.p_views DESC',
};

// Count
$r = $mysqli->query("SELECT COUNT(*) as c FROM pi_personalities p $where");
$total = $r ? (int)$r->fetch_assoc()['c'] : 0;
$total_pages = max(1, ceil($total / $per_page));

// Fetch
$personalities = [];
$r = $mysqli->query("SELECT p.* FROM pi_personalities p $where ORDER BY $order LIMIT $per_page OFFSET $offset");
if ($r) while ($row = $r->fetch_assoc()) $personalities[] = $row;

$countries = pi_get_countries();

include 'includes/header.php';
?>

<!-- HERO / SEARCH -->
<section class="hero-bg py-14 text-white">
  <div class="max-w-3xl mx-auto px-4 text-center">
    <h1 class="text-3xl font-black mb-3">الشخصيات</h1>
    <p class="text-purple-200 mb-8 font-medium">تصفّح <?= number_format(pi_count_personalities()) ?> شخصية عربية موثقة</p>
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
    <span class="text-gray-800 font-semibold">الشخصيات</span>
  </nav>
</div>

<!-- FILTERS & GRID -->
<section class="max-w-7xl mx-auto px-4 py-6">
  <!-- Filter bar -->
  <div class="flex flex-wrap items-center gap-3 mb-6">
    <span class="text-gray-700 font-bold text-sm">فلتر:</span>
    <!-- Country filter -->
    <form method="GET" action="personalities.php" class="flex items-center gap-2">
      <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"><?php endif; ?>
      <select name="country" onchange="this.form.submit()"
        class="border border-gray-200 rounded-xl px-4 py-2 text-sm font-semibold text-gray-700 focus:outline-none focus:border-purple-400">
        <option value="0" <?= !$cid ? 'selected' : '' ?>>كل الدول</option>
        <?php foreach ($countries as $c): ?>
        <option value="<?= $c['c_id'] ?>" <?= $cid == $c['c_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['c_name_ar'] ?? $c['c_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
    <!-- Sort -->
    <div class="flex items-center gap-2 mr-auto">
      <span class="text-gray-500 text-sm">ترتيب:</span>
      <a href="personalities.php?<?= http_build_query(array_merge($_GET, ['sort'=>'views', 'page'=>1])) ?>"
        class="px-4 py-1.5 rounded-full text-sm font-bold transition <?= $sort=='views' ? 'pi-primary-bg text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-purple-300' ?>">
        الأكثر زيارة
      </a>
      <a href="personalities.php?<?= http_build_query(array_merge($_GET, ['sort'=>'new', 'page'=>1])) ?>"
        class="px-4 py-1.5 rounded-full text-sm font-bold transition <?= $sort=='new' ? 'pi-primary-bg text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-purple-300' ?>">
        الأحدث
      </a>
      <a href="personalities.php?<?= http_build_query(array_merge($_GET, ['sort'=>'name', 'page'=>1])) ?>"
        class="px-4 py-1.5 rounded-full text-sm font-bold transition <?= $sort=='name' ? 'pi-primary-bg text-white' : 'bg-white border border-gray-200 text-gray-600 hover:border-purple-300' ?>">
        أبجدي
      </a>
    </div>
  </div>

  <div class="flex items-center justify-between mb-6">
    <h2 class="text-xl font-black text-gray-800 section-dot">
      الشخصيات
      <span class="text-sm font-normal text-gray-400 mr-2">(<?= number_format($total) ?>)</span>
    </h2>
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
