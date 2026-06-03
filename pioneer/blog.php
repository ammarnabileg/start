<?php
require_once 'includes/config.php';

$pageTitle = 'المقالات والأخبار - PioneerIcons';
$total_count = pi_count_personalities() + pi_count_institutions();
$per_page = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$search = pi_escape($_GET['q'] ?? '');

// Count
$count_sql = "SELECT COUNT(*) as c FROM pi_articles a WHERE a.art_active=1";
if ($search) $count_sql .= " AND (a.art_title LIKE '%$search%' OR a.art_source LIKE '%$search%')";
$r = $mysqli->query($count_sql);
$total_articles = $r ? (int)$r->fetch_assoc()['c'] : 0;
$total_pages = max(1, ceil($total_articles / $per_page));

// Fetch
$sql = "SELECT a.*, p.p_name_ar, p.p_photo FROM pi_articles a
        JOIN pi_personalities p ON a.art_p_id=p.p_id
        WHERE a.art_active=1";
if ($search) $sql .= " AND (a.art_title LIKE '%$search%' OR a.art_source LIKE '%$search%')";
$sql .= " ORDER BY a.art_created DESC LIMIT $per_page OFFSET $offset";

$articles = [];
$r = $mysqli->query($sql);
if ($r) while ($row = $r->fetch_assoc()) $articles[] = $row;

include 'includes/header.php';
?>

<!-- HERO / SEARCH -->
<section class="hero-bg py-14 text-white">
  <div class="max-w-3xl mx-auto px-4 text-center">
    <h1 class="text-3xl font-black mb-3">المقالات والأخبار</h1>
    <p class="text-purple-200 mb-8 font-medium">اطّلع على آخر المقالات والأخبار حول الشخصيات العربية</p>
    <form action="blog.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl">
      <div class="flex items-center bg-white px-4"><i class="fa-solid fa-magnifying-glass text-gray-400 text-lg"></i></div>
      <input name="q" type="text" placeholder="ابحث في المقالات..."
        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
        class="flex-1 px-4 py-3.5 text-gray-800 outline-none font-semibold placeholder-gray-400">
      <button type="submit" class="pi-primary-bg px-8 py-3.5 text-white font-bold hover:opacity-90 transition">ابحث</button>
    </form>
  </div>
</section>

<!-- BREADCRUMB -->
<div class="max-w-7xl mx-auto px-4 py-4">
  <nav class="flex items-center gap-2 text-sm text-gray-500">
    <a href="index.php" class="hover:text-purple-600 transition font-semibold">الرئيسية</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <span class="text-gray-800 font-semibold">المقالات</span>
  </nav>
</div>

<!-- ARTICLES GRID -->
<section class="max-w-7xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-8">
    <h2 class="text-2xl font-black text-gray-800 section-dot">
      المقالات
      <span class="text-base font-normal text-gray-400 mr-2">(<?= number_format($total_articles) ?> مقال)</span>
    </h2>
  </div>

  <?php if (empty($articles)): ?>
  <div class="text-center py-20 text-gray-400">
    <i class="fa-solid fa-newspaper text-5xl mb-4"></i>
    <p class="font-semibold text-lg">لا توجد مقالات<?= $search ? ' لهذا البحث' : '' ?></p>
    <?php if ($search): ?>
    <a href="blog.php" class="mt-4 inline-block text-purple-600 font-bold hover:underline">عرض جميع المقالات</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    <?php foreach ($articles as $art): ?>
    <a href="article.php?id=<?= $art['art_id'] ?>" class="bg-white rounded-2xl shadow-sm card-hover overflow-hidden block">
      <?php if (!empty($art['art_image'])): ?>
        <img src="<?= htmlspecialchars($art['art_image']) ?>" alt="<?= htmlspecialchars($art['art_title']) ?>"
          class="w-full h-44 object-cover">
      <?php else: ?>
        <div class="w-full h-44 pi-gradient flex items-center justify-center">
          <i class="fa-solid fa-newspaper text-white text-4xl opacity-60"></i>
        </div>
      <?php endif; ?>
      <div class="p-4">
        <?php if (!empty($art['art_source'])): ?>
          <span class="text-xs text-purple-600 font-bold"><?= htmlspecialchars($art['art_source']) ?></span>
        <?php endif; ?>
        <h3 class="font-bold text-gray-800 text-sm mt-1 mb-2 leading-snug line-clamp-2">
          <?= htmlspecialchars($art['art_title']) ?>
        </h3>
        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-50">
          <?php if (!empty($art['p_photo'])): ?>
            <img src="<?= htmlspecialchars($art['p_photo']) ?>" class="w-7 h-7 rounded-full object-cover">
          <?php else: ?>
            <div class="w-7 h-7 rounded-full pi-gradient flex items-center justify-center text-white text-xs font-bold">
              <?= mb_substr($art['p_name_ar'], 0, 1) ?>
            </div>
          <?php endif; ?>
          <span class="text-xs text-gray-500 font-semibold"><?= htmlspecialchars($art['p_name_ar']) ?></span>
          <span class="text-xs text-gray-300 mr-auto">
            <?= $art['art_created'] ? date('d/m/Y', strtotime($art['art_created'])) : '' ?>
          </span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <div class="flex items-center justify-between mt-10">
    <?php if ($page < $total_pages): ?>
    <a href="blog.php?page=<?= $page+1 ?>&q=<?= urlencode($_GET['q']??'') ?>"
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
      <a href="blog.php?page=<?= $i ?>&q=<?= urlencode($_GET['q']??'') ?>"
        class="w-9 h-9 flex items-center justify-center rounded-full font-bold transition
          <?= $i == $page ? 'pi-primary-bg text-white' : 'bg-white text-gray-600 hover:bg-purple-50 hover:text-purple-600 border border-gray-200' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>
    </div>

    <?php if ($page > 1): ?>
    <a href="blog.php?page=<?= $page-1 ?>&q=<?= urlencode($_GET['q']??'') ?>"
      class="flex items-center gap-2 px-6 py-2.5 bg-white border border-gray-200 rounded-full font-semibold text-gray-700 hover:bg-purple-50 hover:border-purple-300 hover:text-purple-600 transition">
      السابق <i class="fa-solid fa-arrow-left"></i>
    </a>
    <?php else: ?><span></span><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>
