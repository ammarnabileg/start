<?php
require_once 'includes/config.php';

$art_id = (int)($_GET['id'] ?? 0);
if (!$art_id) { header('Location: blog.php'); exit; }

$r = $mysqli->query("SELECT a.*, p.p_id, p.p_name_ar, p.p_photo, p.p_title AS p_job_title, p.p_verified, p.p_nationality
                     FROM pi_articles a
                     LEFT JOIN pi_personalities p ON a.art_p_id=p.p_id
                     WHERE a.art_id=$art_id AND a.art_active=1");
if (!$r || !$r->num_rows) { header('Location: blog.php'); exit; }
$art = $r->fetch_assoc();
$has_author = !empty($art['p_id']);

$pageTitle = htmlspecialchars($art['art_title']) . ' - PioneerIcons';
$total_count = pi_count_personalities() + pi_count_institutions();

// Related articles from same personality
$related = [];
if ($has_author) {
    $r = $mysqli->query("SELECT * FROM pi_articles WHERE art_p_id={$art['art_p_id']} AND art_id!=$art_id AND art_active=1 ORDER BY art_id DESC LIMIT 4");
    if ($r) while ($row = $r->fetch_assoc()) $related[] = $row;
}

// "قد تهمك" — other articles excluding current
$suggested = [];
$exc = $has_author ? "AND (art_p_id!={$art['art_p_id']} OR art_p_id IS NULL)" : '';
$r = $mysqli->query("SELECT a.*, p.p_name_ar FROM pi_articles a LEFT JOIN pi_personalities p ON a.art_p_id=p.p_id WHERE a.art_active=1 AND a.art_id!=$art_id $exc ORDER BY a.art_id DESC LIMIT 6");
if ($r) while ($row = $r->fetch_assoc()) $suggested[] = $row;

// Sidebar: verified personalities
$sidebar_verified = [];
$r = $mysqli->query("SELECT p_id,p_name_ar,p_photo,p_title,p_verified FROM pi_personalities WHERE p_active=1 AND p_verified=1 ORDER BY p_views DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $sidebar_verified[] = $row;

// Sidebar: executive personalities
$sidebar_exec = [];
$r = $mysqli->query("SELECT p_id,p_name_ar,p_photo,p_title FROM pi_personalities WHERE p_active=1 AND p_membership_type='executive' ORDER BY p_views DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $sidebar_exec[] = $row;

// Sidebar: verified institutions
$sidebar_inst = [];
$r = $mysqli->query("SELECT inst_id,inst_name_ar,inst_logo,inst_verified FROM pi_institutions WHERE inst_active=1 AND inst_verified=1 ORDER BY inst_views DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $sidebar_inst[] = $row;

include 'includes/header.php';
?>

<!-- HERO SEARCH -->
<section class="hero-bg py-10">
  <div class="max-w-3xl mx-auto px-4">
    <form action="search.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl max-w-2xl mx-auto border border-white/10 backdrop-blur-sm">
      <input name="q" type="text" placeholder="ابحث عن <?= number_format($total_count) ?> شخصية ومؤسسة..."
        class="flex-1 px-4 py-4 text-gray-800 text-base outline-none font-semibold placeholder-gray-400">
      <button type="submit" class="pi-primary-bg px-8 py-4 font-bold text-white hover:opacity-90 transition whitespace-nowrap rounded-l-2xl">
        ابحث الآن
      </button>
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
          <?php if (!empty($art['art_body'])): ?>
          <div class="pi-rich-content text-gray-700 leading-8">
            <?= $art['art_body'] ?>
          </div>
          <?php elseif (!empty($art['art_content'])): ?>
          <div class="pi-rich-content text-gray-700 leading-8">
            <?= $art['art_content'] ?>
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
        <div class="space-y-3">
          <?php foreach ($related as $rel): ?>
          <a href="article.php?id=<?= $rel['art_id'] ?>" class="flex gap-4 p-3 rounded-xl hover:bg-purple-50 transition">
            <?php if (!empty($rel['art_image'])): ?>
              <img src="<?= htmlspecialchars($rel['art_image']) ?>" class="w-20 h-14 rounded-lg object-cover flex-shrink-0">
            <?php else: ?>
              <div class="w-20 h-14 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-newspaper text-white text-sm"></i>
              </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <?php if (!empty($rel['art_source'])): ?>
                <p class="text-xs text-purple-600 font-bold mb-1"><?= htmlspecialchars($rel['art_source']) ?></p>
              <?php endif; ?>
              <p class="font-bold text-gray-800 text-sm leading-snug line-clamp-2"><?= htmlspecialchars($rel['art_title']) ?></p>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Suggested articles -->
      <?php if (!empty($suggested)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h3 class="font-black text-gray-800 mb-5">مقالات قد تهمك</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <?php foreach ($suggested as $sg): ?>
          <a href="article.php?id=<?= $sg['art_id'] ?>" class="flex gap-3 p-3 rounded-xl hover:bg-gray-50 border border-gray-100 hover:border-purple-100 transition group">
            <?php if (!empty($sg['art_image'])): ?>
              <img src="<?= htmlspecialchars($sg['art_image']) ?>" class="w-16 h-14 rounded-lg object-cover flex-shrink-0">
            <?php else: ?>
              <div class="w-16 h-14 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-newspaper text-white text-xs"></i>
              </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <?php if (!empty($sg['art_source'])): ?>
                <p class="text-xs text-purple-600 font-bold mb-1"><?= htmlspecialchars($sg['art_source']) ?></p>
              <?php endif; ?>
              <p class="font-bold text-gray-800 text-xs leading-snug line-clamp-2 group-hover:text-purple-700 transition"><?= htmlspecialchars($sg['art_title']) ?></p>
              <?php if (!empty($sg['p_name_ar'])): ?>
                <p class="text-gray-400 text-xs mt-1"><?= htmlspecialchars($sg['p_name_ar']) ?></p>
              <?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- RIGHT SIDEBAR -->
    <div class="space-y-5">

      <!-- Author card — only if article has a linked personality -->
      <?php if ($has_author): ?>
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
      <?php endif; ?>

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

      <!-- Verified personalities -->
      <?php if (!empty($sidebar_verified)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-black text-gray-800 text-sm">شخصيات موثقة</h3>
          <a href="personalities.php" class="text-xs text-purple-600 font-bold hover:underline">عرض الكل</a>
        </div>
        <div class="space-y-3">
          <?php foreach ($sidebar_verified as $sp): ?>
          <a href="profile.php?id=<?= $sp['p_id'] ?>" class="flex items-center gap-3 hover:bg-purple-50 rounded-xl p-2 transition group">
            <?php if (!empty($sp['p_photo'])): ?>
              <img src="<?= htmlspecialchars($sp['p_photo']) ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0 border-2 border-purple-100">
            <?php else: ?>
              <div class="w-10 h-10 rounded-full pi-gradient flex items-center justify-center flex-shrink-0">
                <span class="text-white font-black text-sm"><?= mb_substr($sp['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-gray-800 text-xs leading-snug group-hover:text-purple-700 transition truncate">
                <?= htmlspecialchars($sp['p_name_ar']) ?>
                <i class="fa-solid fa-circle-check verified-badge text-xs"></i>
              </p>
              <?php if (!empty($sp['p_title'])): ?>
                <p class="text-gray-400 text-xs truncate"><?= htmlspecialchars($sp['p_title']) ?></p>
              <?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Executive personalities -->
      <?php if (!empty($sidebar_exec)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-black text-gray-800 text-sm">شخصيات تنفيذية</h3>
          <a href="personalities.php" class="text-xs text-purple-600 font-bold hover:underline">عرض الكل</a>
        </div>
        <div class="space-y-3">
          <?php foreach ($sidebar_exec as $ep): ?>
          <a href="profile.php?id=<?= $ep['p_id'] ?>" class="flex items-center gap-3 hover:bg-amber-50 rounded-xl p-2 transition group">
            <?php if (!empty($ep['p_photo'])): ?>
              <img src="<?= htmlspecialchars($ep['p_photo']) ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0 border-2 border-amber-100">
            <?php else: ?>
              <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                <span class="text-white font-black text-sm"><?= mb_substr($ep['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-gray-800 text-xs leading-snug group-hover:text-amber-700 transition truncate flex items-center gap-1">
                <i class="fa-solid fa-crown text-amber-500 text-xs flex-shrink-0"></i>
                <?= htmlspecialchars($ep['p_name_ar']) ?>
              </p>
              <?php if (!empty($ep['p_title'])): ?>
                <p class="text-gray-400 text-xs truncate"><?= htmlspecialchars($ep['p_title']) ?></p>
              <?php endif; ?>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Verified institutions -->
      <?php if (!empty($sidebar_inst)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-black text-gray-800 text-sm">مؤسسات موثقة</h3>
          <a href="all_institutions.php" class="text-xs text-purple-600 font-bold hover:underline">عرض الكل</a>
        </div>
        <div class="space-y-3">
          <?php foreach ($sidebar_inst as $inst): ?>
          <a href="institution.php?id=<?= $inst['inst_id'] ?>" class="flex items-center gap-3 hover:bg-blue-50 rounded-xl p-2 transition group">
            <?php if (!empty($inst['inst_logo'])): ?>
              <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" class="w-10 h-10 rounded-lg object-contain flex-shrink-0 border border-gray-100 bg-gray-50 p-1">
            <?php else: ?>
              <div class="w-10 h-10 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-building text-white text-xs"></i>
              </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-gray-800 text-xs group-hover:text-blue-700 transition truncate">
                <?= htmlspecialchars($inst['inst_name_ar']) ?>
                <?php if (!empty($inst['inst_verified'])): ?>
                  <i class="fa-solid fa-circle-check verified-badge text-xs"></i>
                <?php endif; ?>
              </p>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
