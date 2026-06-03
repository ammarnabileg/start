<?php
require_once 'includes/config.php';

$inst_id = (int)($_GET['id'] ?? 0);
if (!$inst_id) { header('Location: index.php'); exit; }

$r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_id=$inst_id AND inst_active=1");
if (!$r || !$r->num_rows) { header('Location: index.php'); exit; }
$inst = $r->fetch_assoc();

// Increment views
$mysqli->query("UPDATE pi_institutions SET inst_views=inst_views+1 WHERE inst_id=$inst_id");

$pageTitle = htmlspecialchars($inst['inst_name_ar']) . ' - PioneerIcons';
$total_count = pi_count_personalities() + pi_count_institutions();

// Social links
$socials = [];
$r = $mysqli->query("SELECT * FROM pi_social_links WHERE sl_entity_type='institution' AND sl_entity_id=$inst_id");
if ($r) while ($row = $r->fetch_assoc()) $socials[$row['sl_platform']] = $row['sl_url'];

// Categories
$inst_cats = [];
$cat_ids = [];
$r = $mysqli->query("SELECT c.*, l.label_name, l.label_color FROM pi_categories c JOIN pi_institution_categories ic ON c.cat_id=ic.cat_id LEFT JOIN pi_labels l ON c.cat_label_id=l.label_id WHERE ic.inst_id=$inst_id");
if ($r) while ($row = $r->fetch_assoc()) { $inst_cats[] = $row; $cat_ids[] = (int)$row['cat_id']; }

// Related personalities (same categories)
$related_personalities = [];
if (!empty($cat_ids)) {
    $cat_ids_str = implode(',', $cat_ids);
    $r = $mysqli->query("SELECT DISTINCT p.* FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id IN ($cat_ids_str) AND p.p_active=1 ORDER BY p.p_views DESC LIMIT 6");
    if ($r) while ($row = $r->fetch_assoc()) $related_personalities[] = $row;
}

$social_icons = [
    'facebook'  => ['fa-brands fa-facebook-f', 'bg-blue-600'],
    'instagram' => ['fa-brands fa-instagram', 'bg-pink-500'],
    'youtube'   => ['fa-brands fa-youtube', 'bg-red-600'],
    'twitter'   => ['fa-brands fa-x-twitter', 'bg-gray-900'],
    'linkedin'  => ['fa-brands fa-linkedin-in', 'bg-blue-700'],
    'website'   => ['fa-solid fa-globe', 'bg-teal-500'],
];

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
    <a href="all_institutions.php" class="hover:text-purple-600 transition font-semibold">المؤسسات</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <span class="text-gray-800 font-semibold"><?= htmlspecialchars($inst['inst_name_ar']) ?></span>
  </nav>
</div>

<!-- MAIN CONTENT -->
<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- LEFT: Institution Info -->
    <div class="lg:col-span-2 space-y-8">

      <!-- Institution card -->
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex flex-col sm:flex-row gap-6">
          <!-- Logo -->
          <div class="flex-shrink-0">
            <?php if (!empty($inst['inst_logo'])): ?>
              <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" alt="<?= htmlspecialchars($inst['inst_name_ar']) ?>"
                class="w-32 h-32 rounded-2xl object-cover border-4 border-purple-100">
            <?php else: ?>
              <div class="w-32 h-32 rounded-2xl pi-gradient flex items-center justify-center">
                <span class="text-white font-black text-4xl"><?= mb_substr($inst['inst_name_ar'], 0, 1) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div class="flex-1">
            <h1 class="text-2xl font-black text-gray-900 mb-1">
              <?= htmlspecialchars($inst['inst_name_ar']) ?>
              <?php if (!empty($inst['inst_verified'])): ?>
                <i class="fa-solid fa-circle-check verified-badge text-lg mr-1"></i>
              <?php endif; ?>
            </h1>
            <?php if (!empty($inst['inst_name_en'])): ?>
              <p class="text-gray-600 font-semibold mb-2"><?= htmlspecialchars($inst['inst_name_en']) ?></p>
            <?php endif; ?>
            <?php if (!empty($inst['inst_name_en'])): ?>
              <p class="text-gray-400 text-sm mb-2" dir="ltr"><?= htmlspecialchars($inst['inst_name_en']) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap gap-3 text-sm text-gray-600 mb-4">
              <?php if (!empty($inst['inst_country'])): ?>
              <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-flag text-purple-500 text-xs"></i>
                <?= htmlspecialchars($inst['inst_country']) ?>
              </span>
              <?php endif; ?>
              <?php if (!empty($inst['inst_founded'])): ?>
              <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-calendar text-purple-500 text-xs"></i>
                تأسست <?= htmlspecialchars($inst['inst_founded']) ?>
              </span>
              <?php endif; ?>
              <?php if (!empty($inst['inst_website'])): ?>
              <a href="<?= htmlspecialchars($inst['inst_website']) ?>" target="_blank" rel="noopener nofollow"
                class="flex items-center gap-1.5 text-purple-600 hover:underline">
                <i class="fa-solid fa-globe text-xs"></i>
                الموقع الرسمي
              </a>
              <?php endif; ?>
            </div>

            <!-- Social links -->
            <?php if (!empty($socials)): ?>
            <div class="flex flex-wrap gap-2 mb-4">
              <?php foreach ($socials as $platform => $url): ?>
              <?php $ico = $social_icons[$platform] ?? ['fa-solid fa-link', 'bg-gray-500']; ?>
              <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="nofollow noopener"
                class="w-8 h-8 <?= $ico[1] ?> rounded-full flex items-center justify-center text-white text-sm hover:opacity-80 transition">
                <i class="<?= $ico[0] ?>"></i>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="flex flex-wrap gap-3">
              <button onclick="navigator.share ? navigator.share({title:'<?= addslashes(htmlspecialchars($inst['inst_name_ar'])) ?>',url:location.href}) : alert('تم نسخ الرابط')"
                class="w-9 h-9 border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-purple-600 hover:border-purple-300 transition">
                <i class="fa-solid fa-share-nodes"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Description -->
      <?php if (!empty($inst['inst_description'])): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h2 class="text-lg font-black text-gray-800 mb-4">عن <?= htmlspecialchars($inst['inst_name_ar']) ?></h2>
        <div class="text-gray-700 leading-8 text-sm space-y-4">
          <?php foreach (explode("\n\n", $inst['inst_description']) as $para): ?>
          <p><?= nl2br(htmlspecialchars($para)) ?></p>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Categories -->
      <?php if (!empty($inst_cats)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h3 class="font-black text-gray-800 mb-4">تصنيفات ذات صلة</h3>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($inst_cats as $cat): ?>
          <a href="categories.php?cat=<?= $cat['cat_id'] ?>"
            style="background:<?= htmlspecialchars($cat['label_color'] ?? '#f3f4f6') ?>;color:<?= ($cat['label_color'] ?? '') ? '#fff' : '#374151' ?>"
            class="flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold hover:opacity-80 transition">
            <i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?> text-xs"></i>
            <?= htmlspecialchars($cat['cat_name']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- RIGHT SIDEBAR -->
    <div class="space-y-6">

      <!-- Stats card -->
      <div class="bg-white rounded-2xl shadow-sm p-5">
        <h3 class="font-black text-gray-800 mb-4">إحصائيات</h3>
        <div class="space-y-3">
          <div class="flex items-center justify-between py-2 border-b border-gray-50">
            <span class="text-gray-500 text-sm">عدد المشاهدات</span>
            <span class="font-bold text-gray-800"><?= number_format($inst['inst_views'] ?? 0) ?></span>
          </div>
          <?php if (!empty($inst['inst_founded'])): ?>
          <div class="flex items-center justify-between py-2 border-b border-gray-50">
            <span class="text-gray-500 text-sm">سنة التأسيس</span>
            <span class="font-bold text-gray-800"><?= htmlspecialchars($inst['inst_founded']) ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Related personalities -->
      <?php if (!empty($related_personalities)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-5">
        <h3 class="font-black text-gray-800 mb-4">شخصيات ذات صلة</h3>
        <div class="space-y-3">
          <?php foreach ($related_personalities as $rp): ?>
          <a href="profile.php?id=<?= $rp['p_id'] ?>"
            class="flex items-center gap-3 p-3 rounded-xl hover:bg-purple-50 transition">
            <?php if (!empty($rp['p_photo'])): ?>
              <img src="<?= htmlspecialchars($rp['p_photo']) ?>" class="w-11 h-11 rounded-full object-cover">
            <?php else: ?>
              <div class="w-11 h-11 rounded-full pi-gradient flex items-center justify-center text-white font-bold text-sm">
                <?= mb_substr($rp['p_name_ar'], 0, 1) ?>
              </div>
            <?php endif; ?>
            <div>
              <p class="font-bold text-gray-800 text-sm">
                <?= htmlspecialchars($rp['p_name_ar']) ?>
                <?php if (!empty($rp['p_verified'])): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
              </p>
              <p class="text-gray-400 text-xs"><?= htmlspecialchars($rp['p_title'] ?? '') ?></p>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <a href="personalities.php" class="block text-center text-purple-600 text-sm font-bold mt-3 hover:underline">
          عرض جميع الشخصيات
        </a>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
