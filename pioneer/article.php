<?php
require_once 'includes/config.php';

$art_id = (int)($_GET['id'] ?? 0);
if (!$art_id) { header('Location: blog.php'); exit; }

$r = $mysqli->query("SELECT a.*, p.p_id, p.p_name_ar, p.p_photo, p.p_title AS p_job_title, p.p_verified, p.p_nationality
                     FROM pi_articles a
                     JOIN pi_personalities p ON a.art_p_id=p.p_id
                     WHERE a.art_id=$art_id AND a.art_active=1");
if (!$r || !$r->num_rows) { header('Location: blog.php'); exit; }
$art = $r->fetch_assoc();

$pageTitle = htmlspecialchars($art['art_title']) . ' - PioneerIcons';
$total_count = pi_count_personalities() + pi_count_institutions();

// Related articles from same personality
$related = [];
$r = $mysqli->query("SELECT * FROM pi_articles WHERE art_p_id={$art['art_p_id']} AND art_id!=$art_id AND art_active=1 ORDER BY art_id DESC LIMIT 4");
if ($r) while ($row = $r->fetch_assoc()) $related[] = $row;

include 'includes/header.php';
?>

<!-- HERO SEARCH -->
<section class="hero-bg py-10">
  <div class="max-w-3xl mx-auto px-4">
    <form action="search.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl">
      <div class="flex items-center bg-white px-4"><i class="fa-solid fa-magnifying-glass text-gray-400 text-lg"></i></div>
      <input name="q" type="text" placeholder="ابحث عن <?= number_format($total_count) ?> شخصية ومؤسسة..."
        class="flex-1 px-4 py-3.5 text-gray-800 outline-none font-semibold placeholder-gray-400">
      <span class="bg-white flex items-center px-3 text-gray-500 text-sm font-bold border-r border-gray-200">شخصية · مؤسسة</span>
      <button type="submit" class="pi-primary-bg px-8 py-3.5 text-white font-bold hover:opacity-90 transition">ابحث</button>
    </form>
  </div>
</section>

<!-- BREADCRUMB -->
<div class="max-w-7xl mx-auto px-4 py-4">
  <nav class="flex items-center gap-2 text-sm text-gray-500">
    <a href="index.php" class="hover:text-purple-600 transition font-semibold">الرئيسية</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <a href="blog.php" class="hover:text-purple-600 transition font-semibold">المقالات</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <span class="text-gray-800 font-semibold truncate max-w-xs"><?= htmlspecialchars($art['art_title']) ?></span>
  </nav>
</div>

<!-- MAIN CONTENT -->
<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- LEFT: Article -->
    <div class="lg:col-span-2 space-y-6">

      <!-- Article card -->
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <?php if (!empty($art['art_image'])): ?>
          <img src="<?= htmlspecialchars($art['art_image']) ?>" alt="<?= htmlspecialchars($art['art_title']) ?>"
            class="w-full h-64 object-cover">
        <?php endif; ?>
        <div class="p-6">
          <?php if (!empty($art['art_source'])): ?>
          <span class="inline-block px-3 py-1 bg-purple-100 text-purple-700 text-xs font-bold rounded-full mb-3">
            <?= htmlspecialchars($art['art_source']) ?>
          </span>
          <?php endif; ?>
          <h1 class="text-2xl font-black text-gray-900 leading-snug mb-4">
            <?= htmlspecialchars($art['art_title']) ?>
          </h1>
          <div class="flex items-center gap-3 text-sm text-gray-500 mb-6 pb-6 border-b border-gray-100">
            <i class="fa-solid fa-calendar text-purple-400 text-xs"></i>
            <span><?= $art['art_created'] ? date('d/m/Y', strtotime($art['art_created'])) : '' ?></span>
          </div>
          <?php if (!empty($art['art_content'])): ?>
          <div class="prose prose-sm max-w-none text-gray-700 leading-8">
            <?= nl2br(htmlspecialchars($art['art_content'])) ?>
          </div>
          <?php elseif (!empty($art['art_excerpt'])): ?>
          <div class="text-gray-700 leading-8">
            <?= nl2br(htmlspecialchars($art['art_excerpt'])) ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($art['art_url'])): ?>
          <div class="mt-8 pt-6 border-t border-gray-100">
            <a href="<?= htmlspecialchars($art['art_url']) ?>" target="_blank" rel="noopener nofollow"
              class="inline-flex items-center gap-2 pi-primary-bg text-white font-bold px-6 py-3 rounded-xl hover:opacity-90 transition">
              <i class="fa-solid fa-external-link-alt"></i>
              قراءة المقال كاملاً من المصدر
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Related articles from same personality -->
      <?php if (!empty($related)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h3 class="font-black text-gray-800 mb-4">مقالات أخرى عن <?= htmlspecialchars($art['p_name_ar']) ?></h3>
        <div class="space-y-4">
          <?php foreach ($related as $rel): ?>
          <a href="article.php?id=<?= $rel['art_id'] ?>" class="flex gap-4 p-3 rounded-xl hover:bg-purple-50 transition">
            <?php if (!empty($rel['art_image'])): ?>
              <img src="<?= htmlspecialchars($rel['art_image']) ?>" class="w-20 h-16 rounded-lg object-cover flex-shrink-0">
            <?php else: ?>
              <div class="w-20 h-16 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-newspaper text-white"></i>
              </div>
            <?php endif; ?>
            <div>
              <?php if (!empty($rel['art_source'])): ?>
                <p class="text-xs text-gray-400 font-semibold mb-1"><?= htmlspecialchars($rel['art_source']) ?></p>
              <?php endif; ?>
              <p class="font-bold text-gray-800 text-sm leading-snug"><?= htmlspecialchars($rel['art_title']) ?></p>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- RIGHT SIDEBAR: Personality card -->
    <div class="space-y-6">
      <div class="bg-white rounded-2xl shadow-sm p-6 text-center">
        <p class="text-xs text-gray-400 font-semibold mb-4 uppercase tracking-wide">صاحب المقال</p>
        <a href="profile.php?id=<?= $art['p_id'] ?>">
          <?php if (!empty($art['p_photo'])): ?>
            <img src="<?= htmlspecialchars($art['p_photo']) ?>" alt="<?= htmlspecialchars($art['p_name_ar']) ?>"
              class="w-24 h-24 rounded-full mx-auto mb-3 object-cover border-4 border-purple-100">
          <?php else: ?>
            <div class="w-24 h-24 rounded-full pi-gradient flex items-center justify-center mx-auto mb-3">
              <span class="text-white font-black text-3xl"><?= mb_substr($art['p_name_ar'], 0, 1) ?></span>
            </div>
          <?php endif; ?>
          <h3 class="font-black text-gray-800 text-lg mb-1">
            <?= htmlspecialchars($art['p_name_ar']) ?>
            <?php if (!empty($art['p_verified'])): ?>
              <i class="fa-solid fa-circle-check verified-badge"></i>
            <?php endif; ?>
          </h3>
          <?php if (!empty($art['p_job_title'])): ?>
            <p class="text-gray-500 text-sm mb-2"><?= htmlspecialchars($art['p_job_title']) ?></p>
          <?php endif; ?>
          <?php if (!empty($art['p_nationality'])): ?>
            <p class="text-gray-400 text-xs mb-4">
              <i class="fa-solid fa-flag text-purple-400 text-xs ml-1"></i>
              <?= htmlspecialchars($art['p_nationality']) ?>
            </p>
          <?php endif; ?>
        </a>
        <a href="profile.php?id=<?= $art['p_id'] ?>"
          class="inline-block pi-primary-bg text-white font-bold px-6 py-2.5 rounded-xl hover:opacity-90 transition text-sm">
          عرض الملف الشخصي
        </a>
      </div>

      <!-- Share -->
      <div class="bg-white rounded-2xl shadow-sm p-5">
        <h3 class="font-black text-gray-800 mb-4">مشاركة المقال</h3>
        <div class="flex gap-3 justify-center">
          <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>&text=<?= urlencode($art['art_title']) ?>"
            target="_blank" class="w-10 h-10 bg-gray-900 rounded-full flex items-center justify-center text-white hover:opacity-80 transition">
            <i class="fa-brands fa-x-twitter"></i>
          </a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>"
            target="_blank" class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white hover:opacity-80 transition">
            <i class="fa-brands fa-facebook-f"></i>
          </a>
          <a href="https://api.whatsapp.com/send?text=<?= urlencode($art['art_title'].' - https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>"
            target="_blank" class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white hover:opacity-80 transition">
            <i class="fa-brands fa-whatsapp"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
