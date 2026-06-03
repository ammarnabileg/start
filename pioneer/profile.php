<?php
require_once 'includes/config.php';

$p_id = (int)($_GET['id'] ?? 0);
if (!$p_id) { header('Location: index.php'); exit; }

$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_id=$p_id AND p_active=1");
if (!$r || !$r->num_rows) { header('Location: index.php'); exit; }
$p = $r->fetch_assoc();

// Increment views
$mysqli->query("UPDATE pi_personalities SET p_views=p_views+1 WHERE p_id=$p_id");

$pageTitle = htmlspecialchars($p['p_name_ar']) . ' - PioneerIcons';
$total_count = pi_count_personalities() + pi_count_institutions();

// Social links
$socials = [];
$r = $mysqli->query("SELECT * FROM pi_social_links WHERE sl_entity_type='personality' AND sl_entity_id=$p_id");
if ($r) while ($row=$r->fetch_assoc()) $socials[$row['sl_platform']] = $row['sl_url'];

// Categories
$p_cats = [];
$r = $mysqli->query("SELECT c.* FROM pi_categories c JOIN pi_personality_categories pc ON c.cat_id=pc.cat_id WHERE pc.p_id=$p_id");
if ($r) while ($row=$r->fetch_assoc()) $p_cats[] = $row;

// Timeline
$timeline_edu = [];
$r = $mysqli->query("SELECT * FROM pi_timeline WHERE tl_p_id=$p_id AND tl_type='education' ORDER BY tl_year_start DESC");
if ($r) while ($row=$r->fetch_assoc()) $timeline_edu[] = $row;

$timeline_work = [];
$r = $mysqli->query("SELECT * FROM pi_timeline WHERE tl_p_id=$p_id AND tl_type='work' ORDER BY tl_year_start DESC");
if ($r) while ($row=$r->fetch_assoc()) $timeline_work[] = $row;

// Related personalities
$related = [];
$r = $mysqli->query("SELECT p.* FROM pi_personalities p JOIN pi_related_personalities rp ON p.p_id=rp.related_p_id WHERE rp.p_id=$p_id AND p.p_active=1 LIMIT 3");
if ($r) while ($row=$r->fetch_assoc()) $related[] = $row;

// Articles
$articles = [];
$r = $mysqli->query("SELECT * FROM pi_articles WHERE art_p_id=$p_id AND art_active=1 ORDER BY art_id DESC LIMIT 6");
if ($r) while ($row=$r->fetch_assoc()) $articles[] = $row;

// Sponsors
$sponsors = [];
$r = $mysqli->query("SELECT * FROM pi_sponsors WHERE sp_active=1 ORDER BY sp_order LIMIT 4");
if ($r) while ($row=$r->fetch_assoc()) $sponsors[] = $row;

// Daily personality
$daily = null;
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 AND p_verified=1 AND p_id!=$p_id ORDER BY RAND() LIMIT 1");
if ($r && $r->num_rows) $daily = $r->fetch_assoc();

// Executive personalities
$execs = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_membership_type='executive' AND p_active=1 AND p_id!=$p_id LIMIT 4");
if ($r) while ($row=$r->fetch_assoc()) $execs[] = $row;

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
      <button type="submit" class="pi-orange-bg px-8 py-3.5 text-white font-bold hover:opacity-90 transition">ابحث</button>
    </form>
  </div>
</section>

<!-- SPONSOR SECTION -->
<?php if (!empty($sponsors)): ?>
<section class="bg-gray-50 border-b border-gray-200 py-5">
  <div class="max-w-7xl mx-auto px-4 flex flex-wrap items-center gap-4">
    <div class="ml-auto">
      <p class="text-gray-500 text-xs font-semibold mb-1">تحت الضوء</p>
      <a href="advertise.php"
        class="inline-block px-5 py-2 pi-orange-bg text-white text-sm font-bold rounded-full hover:opacity-90 transition">
        اعلن عن شركتك هنا
      </a>
    </div>
    <div class="flex items-center gap-4 flex-wrap">
      <?php foreach ($sponsors as $sp): ?>
      <a href="<?= htmlspecialchars($sp['sp_url'] ?? '#') ?>" class="card-hover">
        <?php if ($sp['sp_logo']): ?>
          <img src="<?= htmlspecialchars($sp['sp_logo']) ?>" alt="<?= htmlspecialchars($sp['sp_name']) ?>"
            class="h-10 object-contain grayscale hover:grayscale-0 transition">
        <?php else: ?>
          <div class="px-4 py-2 bg-white rounded-lg border border-gray-200 text-gray-600 text-sm font-bold">
            <?= htmlspecialchars($sp['sp_name']) ?>
          </div>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- MAIN CONTENT -->
<div class="max-w-7xl mx-auto px-4 py-10">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- LEFT: Profile + Bio -->
    <div class="lg:col-span-2 space-y-8">

      <!-- Profile card -->
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex flex-col sm:flex-row gap-6">
          <!-- Photo -->
          <div class="flex-shrink-0">
            <?php if ($p['p_photo']): ?>
              <img src="<?= htmlspecialchars($p['p_photo']) ?>" alt="<?= htmlspecialchars($p['p_name_ar']) ?>"
                class="w-32 h-32 rounded-2xl object-cover border-4 border-orange-100">
            <?php else: ?>
              <div class="w-32 h-32 rounded-2xl pi-gradient flex items-center justify-center">
                <span class="text-white font-black text-4xl"><?= mb_substr($p['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div class="flex-1">
            <h1 class="text-2xl font-black text-gray-900 mb-1">
              <?= htmlspecialchars($p['p_name_ar']) ?>
              <?php if ($p['p_verified']): ?>
                <i class="fa-solid fa-circle-check verified-badge text-lg mr-1"></i>
              <?php endif; ?>
              <?php if ($p['p_membership_type']=='executive'): ?>
                <i class="fa-solid fa-crown gold-badge text-base mr-1"></i>
              <?php endif; ?>
            </h1>
            <p class="text-gray-600 font-semibold mb-2"><?= htmlspecialchars($p['p_title'] ?? '') ?></p>
            <?php if ($p['p_name_en']): ?>
              <p class="text-gray-400 text-sm mb-2" dir="ltr"><?= htmlspecialchars($p['p_name_en']) ?></p>
            <?php endif; ?>
            <div class="flex flex-wrap gap-3 text-sm text-gray-600 mb-4">
              <?php if ($p['p_nationality']): ?>
              <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-flag text-orange-400 text-xs"></i>
                <?= htmlspecialchars($p['p_nationality']) ?>
              </span>
              <?php endif; ?>
              <?php if ($p['p_residence']): ?>
              <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-location-dot text-orange-400 text-xs"></i>
                <?= htmlspecialchars($p['p_residence']) ?>
              </span>
              <?php endif; ?>
            </div>

            <!-- Social links -->
            <?php if (!empty($socials)): ?>
            <div class="flex flex-wrap gap-2 mb-4">
              <?php foreach ($socials as $platform => $url): ?>
              <?php $ico = $social_icons[$platform] ?? ['fa-solid fa-link','bg-gray-500']; ?>
              <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="nofollow noopener"
                class="w-8 h-8 <?= $ico[1] ?> rounded-full flex items-center justify-center text-white text-sm hover:opacity-80 transition">
                <i class="<?= $ico[0] ?>"></i>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="flex flex-wrap gap-3">
              <button onclick="navigator.share ? navigator.share({title:'<?= addslashes($p['p_name_ar']) ?>',url:location.href}) : alert('تم نسخ الرابط')"
                class="w-9 h-9 border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-orange-500 hover:border-orange-300 transition">
                <i class="fa-solid fa-share-nodes"></i>
              </button>
              <a href="admin.php?p=personalities&action=manage&id=<?= $p_id ?>"
                class="flex items-center gap-2 px-5 py-2 pi-gradient text-white text-sm font-bold rounded-full hover:opacity-90 transition">
                <i class="fa-solid fa-gear"></i> إدارة الصفحة
              </a>
              <a href="vcard.php?id=<?= $p_id ?>"
                class="flex items-center gap-2 px-5 py-2 border border-orange-400 text-orange-500 text-sm font-bold rounded-full hover:bg-orange-50 transition">
                <i class="fa-solid fa-download"></i> تحميل البطاقة التعريفية
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Section tabs -->
      <div class="bg-white rounded-2xl shadow-sm" x-data="{ tab: 'bio' }">
        <div class="flex border-b border-gray-100">
          <button @click="tab='bio'"
            :class="tab=='bio' ? 'border-b-2 border-orange-500 text-orange-600 font-bold' : 'text-gray-500 hover:text-gray-700'"
            class="px-6 py-3.5 text-sm font-semibold transition">
            السيرة الذاتية
          </button>
          <div class="relative" x-data="{open:false}">
            <button @click="open=!open" @click.outside="open=false"
              class="flex items-center gap-1 px-6 py-3.5 text-sm text-gray-500 hover:text-gray-700 font-semibold transition">
              عرض الأقسام <i class="fa-solid fa-chevron-down text-xs"></i>
            </button>
            <div x-show="open" x-cloak x-transition
              class="absolute top-full right-0 bg-white shadow-xl rounded-xl border border-gray-100 py-2 z-10 w-44">
              <button @click="tab='bio';open=false" class="block w-full text-right px-4 py-2 text-sm hover:bg-orange-50 hover:text-orange-500 transition">السيرة الذاتية</button>
              <button @click="tab='related';open=false" class="block w-full text-right px-4 py-2 text-sm hover:bg-orange-50 hover:text-orange-500 transition">شخصيات متعلقة</button>
              <button @click="tab='timeline';open=false" class="block w-full text-right px-4 py-2 text-sm hover:bg-orange-50 hover:text-orange-500 transition">المحطات</button>
              <button @click="tab='articles';open=false" class="block w-full text-right px-4 py-2 text-sm hover:bg-orange-50 hover:text-orange-500 transition">مقالات تهمك</button>
            </div>
          </div>
        </div>

        <!-- Bio tab -->
        <div x-show="tab=='bio'" class="p-6">
          <h2 class="text-lg font-black text-gray-800 mb-4">سيرة <?= htmlspecialchars($p['p_name_ar']) ?></h2>
          <?php if ($p['p_bio_platform']): ?>
          <div class="bg-gray-50 rounded-xl p-4 mb-4 text-sm text-gray-600 leading-7 border-r-4 border-orange-400">
            <?= nl2br(htmlspecialchars($p['p_bio_platform'])) ?>
          </div>
          <?php endif; ?>
          <?php if ($p['p_bio']): ?>
          <div class="text-gray-700 leading-8 text-sm space-y-4">
            <?php foreach (explode("\n\n", $p['p_bio']) as $para): ?>
            <p><?= nl2br(htmlspecialchars($para)) ?></p>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p class="text-gray-400 text-center py-8">لم تتم إضافة سيرة ذاتية بعد</p>
          <?php endif; ?>
        </div>

        <!-- Related tab -->
        <div x-show="tab=='related'" x-cloak class="p-6">
          <h2 class="text-lg font-black text-gray-800 mb-4">شخصيات متعلقة</h2>
          <?php if (empty($related)): ?>
          <p class="text-gray-400 text-center py-8">لا توجد شخصيات متعلقة</p>
          <?php else: ?>
          <div class="space-y-4">
            <?php foreach ($related as $rel): ?>
            <a href="profile.php?id=<?= $rel['p_id'] ?>" class="flex items-center gap-4 p-3 rounded-xl hover:bg-orange-50 transition">
              <?php if ($rel['p_photo']): ?>
                <img src="<?= htmlspecialchars($rel['p_photo']) ?>" class="w-12 h-12 rounded-full object-cover">
              <?php else: ?>
                <div class="w-12 h-12 rounded-full pi-gradient flex items-center justify-center text-white font-bold">
                  <?= mb_substr($rel['p_name_ar'],0,1) ?>
                </div>
              <?php endif; ?>
              <div>
                <p class="font-bold text-gray-800 text-sm">
                  <?= htmlspecialchars($rel['p_name_ar']) ?>
                  <?php if ($rel['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
                </p>
                <p class="text-gray-400 text-xs"><?= htmlspecialchars($rel['p_title'] ?? '') ?></p>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Timeline tab -->
        <div x-show="tab=='timeline'" x-cloak class="p-6">
          <h2 class="text-lg font-black text-gray-800 mb-6">محطات في حياة <?= htmlspecialchars($p['p_name_ar']) ?> في العمل والتعليم</h2>
          <?php if (!empty($timeline_edu)): ?>
          <h3 class="text-base font-bold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-graduation-cap text-orange-400"></i> التعليم
          </h3>
          <div class="space-y-4 mb-8 relative">
            <div class="absolute right-4 top-0 bottom-0 w-0.5 bg-orange-100"></div>
            <?php foreach ($timeline_edu as $tl): ?>
            <div class="flex gap-4 relative">
              <div class="w-8 h-8 rounded-full pi-orange-bg flex items-center justify-center flex-shrink-0 z-10">
                <i class="fa-solid fa-graduation-cap text-white text-xs"></i>
              </div>
              <div class="bg-gray-50 rounded-xl p-4 flex-1">
                <?php if ($tl['tl_institution_id']): ?>
                  <a href="institution.php?id=<?= $tl['tl_institution_id'] ?>" class="flex items-center gap-2 mb-1 hover:text-orange-500">
                    <span class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($tl['tl_title']) ?></span>
                  </a>
                <?php else: ?>
                  <p class="font-bold text-gray-800 text-sm mb-1"><?= htmlspecialchars($tl['tl_title']) ?></p>
                <?php endif; ?>
                <?php if ($tl['tl_institution']): ?>
                  <p class="text-gray-500 text-xs"><?= htmlspecialchars($tl['tl_institution']) ?></p>
                <?php endif; ?>
                <p class="text-orange-400 text-xs font-semibold mt-1">
                  <?= $tl['tl_year_start'] ?>
                  <?= $tl['tl_year_end'] ? ' — ' . $tl['tl_year_end'] : '' ?>
                </p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($timeline_work)): ?>
          <h3 class="text-base font-bold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-briefcase text-blue-400"></i> العمل
          </h3>
          <div class="space-y-4 relative">
            <div class="absolute right-4 top-0 bottom-0 w-0.5 bg-blue-100"></div>
            <?php foreach ($timeline_work as $tl): ?>
            <div class="flex gap-4 relative">
              <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0 z-10">
                <i class="fa-solid fa-briefcase text-white text-xs"></i>
              </div>
              <div class="bg-gray-50 rounded-xl p-4 flex-1">
                <p class="font-bold text-gray-800 text-sm mb-1"><?= htmlspecialchars($tl['tl_title']) ?></p>
                <?php if ($tl['tl_institution']): ?>
                  <p class="text-gray-500 text-xs"><?= htmlspecialchars($tl['tl_institution']) ?></p>
                <?php endif; ?>
                <p class="text-blue-400 text-xs font-semibold mt-1">
                  <?= $tl['tl_year_start'] ?>
                  <?= $tl['tl_year_end'] ? ' — ' . $tl['tl_year_end'] : ' — الآن' ?>
                </p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (empty($timeline_edu) && empty($timeline_work)): ?>
          <p class="text-gray-400 text-center py-8">لم تتم إضافة محطات بعد</p>
          <?php endif; ?>
        </div>

        <!-- Articles tab -->
        <div x-show="tab=='articles'" x-cloak class="p-6">
          <h2 class="text-lg font-black text-gray-800 mb-4">مقالات تهمك</h2>
          <?php if (empty($articles)): ?>
          <p class="text-gray-400 text-center py-8">لا توجد مقالات</p>
          <?php else: ?>
          <div class="space-y-4" x-data="{showAll: false}">
            <template x-for="(art, i) in <?= json_encode($articles) ?>" :key="art.art_id">
              <div x-show="showAll || i < 3"
                class="flex gap-4 p-4 bg-gray-50 rounded-xl hover:bg-orange-50 transition">
                <template x-if="art.art_image">
                  <img :src="art.art_image" class="w-20 h-16 rounded-lg object-cover flex-shrink-0">
                </template>
                <template x-if="!art.art_image">
                  <div class="w-20 h-16 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-newspaper text-white"></i>
                  </div>
                </template>
                <div class="flex-1">
                  <p x-text="art.art_source" class="text-xs text-gray-400 font-semibold mb-1"></p>
                  <a :href="art.art_url || '#'" target="_blank"
                    x-text="art.art_title"
                    class="font-bold text-gray-800 text-sm hover:text-orange-500 transition leading-tight block mb-2"></a>
                </div>
              </div>
            </template>
            <?php if (count($articles) > 3): ?>
            <div class="text-center">
              <button @click="showAll=!showAll"
                class="px-6 py-2 pi-orange-bg text-white text-sm font-bold rounded-full hover:opacity-90 transition">
                <span x-text="showAll ? 'عرض أقل' : 'عرض المزيد'"></span>
              </button>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Categories -->
      <?php if (!empty($p_cats)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h3 class="font-black text-gray-800 mb-4">تصنيفات ذات صلة</h3>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($p_cats as $cat): ?>
          <a href="categories.php?cat=<?= $cat['cat_id'] ?>"
            class="flex items-center gap-1.5 px-4 py-2 <?= pi_badge_class($cat['cat_badge_color']) ?> rounded-full text-sm font-semibold hover:opacity-80 transition">
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

      <!-- Daily personality -->
      <?php if ($daily): ?>
      <div class="daily-bg rounded-2xl p-6 text-white text-center">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-sm">شخصية اليوم</h3>
          <i class="fa-solid fa-circle-check text-lg"></i>
        </div>
        <a href="profile.php?id=<?= $daily['p_id'] ?>">
          <?php if ($daily['p_photo']): ?>
            <img src="<?= htmlspecialchars($daily['p_photo']) ?>" class="w-20 h-20 rounded-full mx-auto mb-3 object-cover border-3 border-white border-opacity-50">
          <?php else: ?>
            <div class="w-20 h-20 rounded-full mx-auto mb-3 bg-white bg-opacity-20 flex items-center justify-center">
              <span class="font-black text-3xl"><?= mb_substr($daily['p_name_ar'],0,1) ?></span>
            </div>
          <?php endif; ?>
          <p class="font-black text-base mb-0.5">
            <?= htmlspecialchars($daily['p_name_ar']) ?>
            <i class="fa-solid fa-circle-check text-sm"></i>
          </p>
          <p class="text-blue-100 text-xs mb-4"><?= htmlspecialchars($daily['p_title'] ?? '') ?></p>
        </a>
        <a href="admin.php?p=personalities&action=verify"
          class="inline-flex items-center gap-1.5 px-5 py-2 bg-white text-blue-600 text-sm font-bold rounded-full hover:opacity-90 transition">
          <i class="fa-solid fa-circle-check"></i>
          وثق ملفك لتظهر هنا
        </a>
      </div>
      <?php endif; ?>

      <!-- Executive personalities -->
      <?php if (!empty($execs)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-5" x-data="{showMore: false}">
        <h3 class="font-black text-gray-800 mb-4">رؤساء تنفيذيون</h3>
        <div class="space-y-3">
          <?php foreach (array_slice($execs, 0, 2) as $exec): ?>
          <a href="profile.php?id=<?= $exec['p_id'] ?>"
            class="flex items-center gap-3 p-3 rounded-xl bg-yellow-50 border border-yellow-100 hover:bg-yellow-100 transition">
            <?php if ($exec['p_photo']): ?>
              <img src="<?= htmlspecialchars($exec['p_photo']) ?>" class="w-12 h-12 rounded-full object-cover border-2 border-yellow-300">
            <?php else: ?>
              <div class="w-12 h-12 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center text-white font-bold">
                <?= mb_substr($exec['p_name_ar'],0,1) ?>
              </div>
            <?php endif; ?>
            <div>
              <p class="font-bold text-gray-800 text-sm">
                <?= htmlspecialchars($exec['p_name_ar']) ?>
                <i class="fa-solid fa-crown gold-badge text-xs mr-1"></i>
              </p>
              <p class="text-gray-500 text-xs"><?= htmlspecialchars($exec['p_title'] ?? '') ?></p>
            </div>
          </a>
          <?php endforeach; ?>

          <?php if (count($execs) > 2): ?>
          <div x-show="showMore">
            <?php foreach (array_slice($execs, 2) as $exec): ?>
            <a href="profile.php?id=<?= $exec['p_id'] ?>"
              class="flex items-center gap-3 p-3 rounded-xl bg-yellow-50 border border-yellow-100 hover:bg-yellow-100 transition mb-3">
              <?php if ($exec['p_photo']): ?>
                <img src="<?= htmlspecialchars($exec['p_photo']) ?>" class="w-12 h-12 rounded-full object-cover border-2 border-yellow-300">
              <?php else: ?>
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-yellow-400 to-orange-500 flex items-center justify-center text-white font-bold">
                  <?= mb_substr($exec['p_name_ar'],0,1) ?>
                </div>
              <?php endif; ?>
              <div>
                <p class="font-bold text-gray-800 text-sm">
                  <?= htmlspecialchars($exec['p_name_ar']) ?>
                  <i class="fa-solid fa-crown gold-badge text-xs mr-1"></i>
                </p>
                <p class="text-gray-500 text-xs"><?= htmlspecialchars($exec['p_title'] ?? '') ?></p>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <button @click="showMore=!showMore"
            class="w-full py-2 text-sm font-bold text-orange-500 hover:text-orange-600 transition">
            <span x-text="showMore ? 'عرض أقل' : 'عرض المزيد'"></span>
            <i :class="showMore ? 'fa-chevron-up' : 'fa-chevron-down'" class="fa-solid text-xs mr-1"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
