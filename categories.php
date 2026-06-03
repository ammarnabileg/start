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

// Count and fetch categories
$all_cats = pi_get_categories();
$total_cats = count($all_cats);
$total_pages = max(1, ceil($total_cats / $per_page));
$page_cats = array_slice($all_cats, $offset, $per_page);

// If cat filter is set, load category details
$current_cat = null;
$verified_personalities = [];
$top_visited = [];
$all_personalities_first = [];
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

    // Verified personalities (عضويات موثقة)
    $r = $mysqli->query("SELECT p.* FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id=$cat_filter AND p.p_active=1 AND p.p_verified=1 ORDER BY p.p_views DESC LIMIT 8");
    if ($r) while ($row=$r->fetch_assoc()) $verified_personalities[] = $row;

    // Top visited (الأكثر زيارة)
    $r = $mysqli->query("SELECT p.* FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id=$cat_filter AND p.p_active=1 ORDER BY p.p_views DESC LIMIT 8");
    if ($r) while ($row=$r->fetch_assoc()) $top_visited[] = $row;

    // First batch for الكل (10)
    $r = $mysqli->query("SELECT p.* FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id=$cat_filter AND p.p_active=1 ORDER BY p.p_views DESC LIMIT 10");
    if ($r) while ($row=$r->fetch_assoc()) $all_personalities_first[] = $row;

    // Institutions
    $r = $mysqli->query("SELECT i.* FROM pi_institutions i JOIN pi_institution_categories ic ON i.inst_id=ic.inst_id WHERE ic.cat_id=$cat_filter AND i.inst_active=1 ORDER BY i.inst_views DESC LIMIT 8");
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

<div class="max-w-7xl mx-auto px-4 py-8 space-y-12">

  <!-- ===== SECTION 1: عضويات موثقة ===== -->
  <?php if (!empty($verified_personalities)): ?>
  <section>
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-blue-500 flex items-center justify-center">
          <i class="fa-solid fa-circle-check text-white text-sm"></i>
        </div>
        <h2 class="text-xl font-black text-gray-800">عضويات موثقة</h2>
      </div>
      <span class="text-sm text-gray-400 font-semibold"><?= count($verified_personalities) ?> شخصية</span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-8 gap-4">
      <?php foreach ($verified_personalities as $p): ?>
      <?= render_person_card($p) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===== SECTION 2: الأكثر زيارة ===== -->
  <?php if (!empty($top_visited)): ?>
  <section>
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-orange-500 flex items-center justify-center">
          <i class="fa-solid fa-fire text-white text-sm"></i>
        </div>
        <h2 class="text-xl font-black text-gray-800">الأكثر زيارة</h2>
      </div>
      <span class="text-sm text-gray-400 font-semibold">أعلى <?= count($top_visited) ?> بالزيارات</span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-8 gap-4">
      <?php foreach ($top_visited as $p): ?>
      <?= render_person_card($p) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===== SECTION 3: الكل (infinite scroll) ===== -->
  <section>
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-purple-600 flex items-center justify-center">
          <i class="fa-solid fa-users text-white text-sm"></i>
        </div>
        <h2 class="text-xl font-black text-gray-800">كل الشخصيات</h2>
        <span class="bg-purple-100 text-purple-700 text-xs font-black px-2.5 py-0.5 rounded-full"><?= number_format($cat_total_p) ?></span>
      </div>
    </div>
    <div id="all-grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-8 gap-4">
      <?php foreach ($all_personalities_first as $p): ?>
      <?= render_person_card($p) ?>
      <?php endforeach; ?>
    </div>

    <!-- Load more trigger -->
    <?php if ($cat_total_p > 10): ?>
    <div id="load-more-trigger" class="flex justify-center mt-8">
      <button id="load-more-btn" onclick="loadMore()"
        class="flex items-center gap-2 px-8 py-3 bg-white border-2 border-purple-200 text-purple-600 font-bold rounded-full hover:bg-purple-50 hover:border-purple-400 transition text-sm">
        <i class="fa-solid fa-plus text-xs"></i> عرض المزيد
      </button>
      <div id="load-spinner" class="hidden flex items-center gap-2 text-purple-400 font-semibold text-sm py-3">
        <i class="fa-solid fa-spinner fa-spin"></i> جاري التحميل...
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- ===== SECTION 4: المؤسسات ===== -->
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
  </section>
  <?php endif; ?>

</div>

<script>
var _catId   = <?= $cat_filter ?>;
var _offset  = 10;
var _loading = false;
var _done    = <?= $cat_total_p <= 10 ? 'true' : 'false' ?>;

function buildCard(p) {
  var photo = p.p_photo
    ? '<img src="'+p.p_photo+'" alt="'+esc(p.p_name_ar)+'" class="w-14 h-14 rounded-full mx-auto mb-2 object-cover border-2 border-purple-100">'
    : '<div class="w-14 h-14 rounded-full pi-gradient flex items-center justify-center mx-auto mb-2"><span class="text-white font-black text-lg">'+esc(p.p_name_ar[0])+'</span></div>';
  var badge = (p.p_verified == 1 || p.p_verified === '1') ? '<i class="fa-solid fa-circle-check verified-badge text-xs mr-0.5"></i>' : '';
  var title = p.p_title ? '<p class="text-gray-400 text-xs mt-0.5 truncate">'+esc(p.p_title)+'</p>' : '';
  return '<a href="profile.php?id='+p.p_id+'" class="bg-white rounded-2xl p-3 shadow-sm card-hover text-center block">'+photo+'<h3 class="font-bold text-gray-800 text-xs leading-tight">'+esc(p.p_name_ar)+badge+'</h3>'+title+'</a>';
}
function esc(s){ var d=document.createElement('div');d.textContent=s||'';return d.innerHTML; }

function loadMore() {
  if (_loading || _done) return;
  _loading = true;
  document.getElementById('load-more-btn').classList.add('hidden');
  document.getElementById('load-spinner').classList.remove('hidden');

  fetch('actions/cat_more.php?cat='+_catId+'&offset='+_offset+'&type=personalities')
    .then(r=>r.json())
    .then(data => {
      var grid = document.getElementById('all-grid');
      data.items.forEach(p => {
        var el = document.createElement('div');
        el.innerHTML = buildCard(p);
        grid.appendChild(el.firstChild);
      });
      _offset += data.items.length;
      _loading = false;
      document.getElementById('load-spinner').classList.add('hidden');
      if (data.has_more) {
        document.getElementById('load-more-btn').classList.remove('hidden');
      } else {
        _done = true;
        document.getElementById('load-more-trigger').remove();
      }
    });
}

// Infinite scroll on scroll down
window.addEventListener('scroll', function() {
  if (_done || _loading) return;
  var trigger = document.getElementById('load-more-trigger');
  if (!trigger) return;
  var rect = trigger.getBoundingClientRect();
  if (rect.top < window.innerHeight + 200) loadMore();
});
</script>

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
