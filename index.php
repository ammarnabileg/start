<?php
require_once 'includes/config.php';
$_S = pi_get_settings();
$pageTitle = htmlspecialchars($_S['site_name'] ?? 'PioneerIcons') . ' | ' . htmlspecialchars($_S['site_tagline'] ?? 'منصة الحضور العربي الموثق');

$total_count = pi_count_personalities() + pi_count_institutions();
$cid = pi_current_country();
$country_where_p    = $cid ? " AND p_country_id=$cid" : '';
$country_where_inst = $cid ? " AND inst_country_id=$cid" : '';

// Most visited personalities — country first, then fill from others if needed
$personalities = [];
if ($cid) {
    // Priority: current country first
    $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 AND p_country_id=$cid ORDER BY p_views DESC LIMIT 10");
    if ($r) while ($row=$r->fetch_assoc()) $personalities[] = $row;
    // If less than 10, fill from other countries
    if (count($personalities) < 10) {
        $existing_ids = array_column($personalities, 'p_id');
        $exclude = $existing_ids ? 'AND p_id NOT IN ('.implode(',', $existing_ids).')' : '';
        $limit = 10 - count($personalities);
        $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 $exclude ORDER BY p_views DESC LIMIT $limit");
        if ($r) while ($row=$r->fetch_assoc()) $personalities[] = $row;
    }
} else {
    $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 ORDER BY p_views DESC LIMIT 10");
    if ($r) while ($row=$r->fetch_assoc()) $personalities[] = $row;
}

// Most visited institutions
$institutions = [];
if ($cid) {
    $r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 AND inst_country_id=$cid ORDER BY inst_views DESC LIMIT 5");
    if ($r) while ($row=$r->fetch_assoc()) $institutions[] = $row;
    if (count($institutions) < 5) {
        $exclude_ids = array_column($institutions, 'inst_id');
        $ex = $exclude_ids ? 'AND inst_id NOT IN ('.implode(',', $exclude_ids).')' : '';
        $lim = 5 - count($institutions);
        $r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 $ex ORDER BY inst_views DESC LIMIT $lim");
        if ($r) while ($row=$r->fetch_assoc()) $institutions[] = $row;
    }
} else {
    $r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 ORDER BY inst_views DESC LIMIT 5");
    if ($r) while ($row=$r->fetch_assoc()) $institutions[] = $row;
}

// Featured categories — 2 rows on desktop (5 cols × 2 = 10), 3 rows on mobile (3 cols × 3 = 9 → use 10 to fill)
$categories = pi_get_categories();
$feat_cats  = array_slice($categories, 0, 10);

// Latest articles
$latest_articles = [];
$r = $mysqli->query("SELECT a.*, p.p_name_ar, p.p_photo, p.p_verified FROM pi_articles a LEFT JOIN pi_personalities p ON a.art_p_id=p.p_id WHERE a.art_active=1 ORDER BY a.art_id DESC LIMIT 6");
if ($r) while ($row=$r->fetch_assoc()) $latest_articles[] = $row;

include 'includes/header.php';
?>

<!-- HERO SECTION -->
<section class="hero-bg py-24 text-white">
  <div class="hero-glow"></div>
  <div class="hero-glow-2"></div>
  <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
    <div class="hero-animate-1 inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-5 py-2 text-sm font-semibold mb-6 text-purple-200 backdrop-blur-sm">
      <i class="fa-solid fa-circle-check text-purple-300 text-xs"></i>
      <?= htmlspecialchars($_S['hero_pill'] ?? 'المنصة العربية الأولى للحضور الموثق') ?>
    </div>
    <h1 class="hero-animate-2 text-4xl md:text-6xl font-black mb-5 leading-tight tracking-tight pi-shimmer-text">
      <?= htmlspecialchars($_S['hero_title'] ?? $_S['site_tagline'] ?? 'السجل العربي الأول للشخصيات والمؤسسات المؤثرة') ?>
    </h1>
    <p class="hero-animate-3 text-lg text-purple-200 mb-10 font-medium max-w-xl mx-auto leading-relaxed"><?= htmlspecialchars($_S['hero_subtitle'] ?? $_S['site_description'] ?? 'حيث يجد الإعلام والأعمال والباحثون من يبحثون عنه في العالم العربي — بمعلومات موثّقة ودقيقة') ?></p>

    <div class="hero-animate-4">
    <form action="search.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl max-w-2xl mx-auto border border-white/10 backdrop-blur-sm">
      <?php if ($cid): ?><input type="hidden" name="country" value="<?= $cid ?>"><?php endif; ?>
      <input name="q" type="text"
        placeholder="ابحث عن <?= number_format($total_count) ?> شخصية ومؤسسة..."
        class="flex-1 px-4 py-4 text-gray-800 text-base outline-none font-semibold placeholder-gray-400"
        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
      <button type="submit" class="pi-primary-bg px-8 py-4 font-bold text-white hover:opacity-90 transition whitespace-nowrap rounded-l-2xl">
        ابحث الآن
      </button>
    </form>
    </div>

    <div class="hero-animate-5 flex items-center justify-center gap-8 mt-10 text-purple-300 text-sm font-semibold">
      <span class="flex items-center gap-2"><i class="fa-solid fa-users"></i> <?= number_format(pi_count_personalities()) ?> شخصية</span>
      <span class="w-px h-4 bg-white/20"></span>
      <span class="flex items-center gap-2"><i class="fa-solid fa-building"></i> <?= number_format(pi_count_institutions()) ?> مؤسسة</span>
      <span class="w-px h-4 bg-white/20"></span>
      <span class="flex items-center gap-2"><i class="fa-solid fa-globe-asia"></i> أكثر من 17 دولة</span>
    </div>
  </div>
</section>

<!-- MOST VISITED PERSONALITIES -->
<section class="max-w-7xl mx-auto px-4 py-16">
  <div class="flex items-center justify-between mb-8">
    <div>
      <h2 class="pi-reveal pi-section-head text-2xl font-black text-gray-800 section-dot">
        الشخصيات الأكثر زيارة
      </h2>
      <?php if ($cid): ?><p class="text-sm text-gray-400 mt-1">مع أولوية الدولة المختارة</p><?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
    <?php if (empty($personalities)): ?>
      <?php for ($i=0; $i<10; $i++): ?>
      <div class="bg-white rounded-2xl shadow-sm overflow-hidden animate-pulse">
        <div class="w-full bg-gray-200" style="aspect-ratio:3/4"></div>
        <div class="p-3"><div class="h-4 bg-gray-200 rounded w-3/4 mb-1"></div><div class="h-3 bg-gray-100 rounded w-1/2"></div></div>
      </div>
      <?php endfor; ?>
    <?php else: ?>
      <?php foreach ($personalities as $i => $p):
        $delays = ['','pi-delay-1','pi-delay-2','pi-delay-3','pi-delay-4','pi-delay-5'];
        $dl = $delays[$i % 6];
      ?>
      <a href="profile.php?id=<?= $p['p_id'] ?><?= $cid ? '&country='.$cid : '' ?>"
        class="pi-reveal <?= $dl ?> bg-white rounded-2xl shadow-sm card-hover block relative overflow-hidden">
        <?php if ($cid && ($p['p_country_id']??0) == $cid): ?>
        <span class="absolute top-2 left-2 w-2 h-2 bg-purple-500 rounded-full z-10" title="من الدولة المختارة"></span>
        <?php endif; ?>
        <!-- Portrait photo -->
        <div style="aspect-ratio:3/4;overflow:hidden;background:#f3f4f6;">
          <?php if ($p['p_photo']): ?>
            <img src="<?= htmlspecialchars($p['p_photo']) ?>" alt="<?= htmlspecialchars($p['p_name_ar']) ?>"
              style="width:100%;height:100%;object-fit:cover;object-position:top;filter:grayscale(20%);transition:filter .3s,transform .3s;"
              onmouseover="this.style.filter='grayscale(0)';this.style.transform='scale(1.03)'"
              onmouseout="this.style.filter='grayscale(20%)';this.style.transform='scale(1)'">
          <?php else: ?>
            <div style="width:100%;height:100%;background:linear-gradient(135deg,#6d28d9,#1d4ed8);display:flex;align-items:center;justify-content:center;">
              <span style="color:#fff;font-weight:900;font-size:2.5rem;"><?= mb_substr($p['p_name_ar'],0,1) ?></span>
            </div>
          <?php endif; ?>
        </div>
        <!-- Info -->
        <div class="p-3">
          <h3 class="font-bold text-gray-800 text-sm leading-snug mb-0.5">
            <?= htmlspecialchars($p['p_name_ar']) ?>
            <?php if ($p['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
          </h3>
          <p class="text-gray-400 text-xs leading-snug"><?= htmlspecialchars($p['p_title'] ?? '') ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="text-center mt-8">
    <a href="personalities.php<?= $cid ? '?country='.$cid : '' ?>"
      class="inline-flex items-center gap-2 px-8 py-3 border-2 border-purple-400 text-purple-600 font-bold rounded-full hover:bg-purple-50 transition">
      تصفح كل الشخصيات <i class="fa-solid fa-arrow-left"></i>
    </a>
  </div>
</section>

<!-- WHO USES THE PLATFORM -->
<section class="bg-white border-t border-gray-100 py-16">
  <div class="max-w-7xl mx-auto px-4">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
      <div>
        <p class="pi-reveal text-xs font-black text-purple-600 tracking-widest uppercase mb-3">من يستخدم المنصة</p>
        <h2 class="pi-reveal pi-delay-1 text-3xl font-black text-gray-900 leading-tight mb-6">
          جهات وأفراد يعتمدون علينا<br>
          <span style="color:#8829C8">كل يوم</span>
        </h2>
        <p class="pi-reveal pi-delay-2 text-gray-600 text-base leading-8 mb-5">
          تستخدم منصتنا عدد كبير من المؤسسات الإعلامية، المؤسسات الحكومية، ولجان الترشيح للجوائز — حيث تساعدهم المعلومات الموثقة على التحقق من هوية الشخصيات واتخاذ قرارات أكثر ثقة.
        </p>
        <p class="pi-reveal pi-delay-3 text-gray-600 text-base leading-8">
          وعلى المستوى الدولي، تلجأ مؤسسات التحقق <strong>KYC</strong> ومراكز البحث إلى ملفاتنا للحصول على معلومات دقيقة وموثوقة عن الشخصيات والمؤسسات العربية — في ثوانٍ لا ساعات.
        </p>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <?php
        $use_cases = [
          ['fa-newspaper','text-purple-600','bg-purple-50','border-purple-100','الإعلام والصحافة','التحقق من الشخصيات قبل المقابلات والتغطيات'],
          ['fa-landmark','text-blue-600','bg-blue-50','border-blue-100','المؤسسات الحكومية','مرجع رسمي للملفات الموثقة لأصحاب المناصب'],
          ['fa-trophy','text-amber-600','bg-amber-50','border-amber-100','لجان الجوائز','التحقق من أهلية المرشحين بمعلومات موثقة'],
          ['fa-shield-halved','text-green-600','bg-green-50','border-green-100','KYC والامتثال','التحقق من الهوية للمؤسسات المالية والدولية'],
        ];
        foreach ($use_cases as $i => list($icon,$tc,$bg,$bc,$title,$desc)):
          $dl = ['','pi-delay-1','pi-delay-2','pi-delay-3'][$i];
        ?>
        <div class="pi-reveal <?= $dl ?> <?= $bg ?> border <?= $bc ?> rounded-2xl p-5">
          <div class="w-10 h-10 rounded-xl <?= $bg ?> flex items-center justify-center mb-3 border border-white shadow-sm">
            <i class="fa-solid <?= $icon ?> <?= $tc ?> text-lg"></i>
          </div>
          <h3 class="font-black text-gray-800 text-sm mb-1"><?= $title ?></h3>
          <p class="text-gray-500 text-xs leading-relaxed"><?= $desc ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- MOST VISITED INSTITUTIONS -->
<section class="bg-gray-50 py-16 border-t border-gray-100">
  <div class="max-w-7xl mx-auto px-4">
    <h2 class="pi-reveal pi-section-head text-2xl font-black text-gray-800 mb-8 section-dot">المؤسسات الأكثر زيارة</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
      <?php if (empty($institutions)): ?>
        <?php for ($i=0; $i<5; $i++): ?>
        <div class="bg-white rounded-2xl p-5 shadow-sm text-center animate-pulse">
          <div class="w-16 h-16 rounded-xl bg-gray-200 mx-auto mb-3"></div>
          <div class="h-4 bg-gray-200 rounded w-3/4 mx-auto"></div>
        </div>
        <?php endfor; ?>
      <?php else: ?>
        <?php foreach ($institutions as $ii => $inst):
          $delays2 = ['','pi-delay-1','pi-delay-2','pi-delay-3','pi-delay-4'];
          $dl2 = $delays2[$ii % 5];
        ?>
        <a href="institution.php?id=<?= $inst['inst_id'] ?><?= $cid ? '&country='.$cid : '' ?>"
          class="pi-reveal <?= $dl2 ?> bg-white rounded-2xl p-5 shadow-sm card-hover text-center block">
          <?php if ($inst['inst_logo']): ?>
            <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" class="w-16 h-16 rounded-xl mx-auto mb-3 object-contain">
          <?php else: ?>
            <div class="w-16 h-16 rounded-xl mx-auto mb-3 pi-gradient flex items-center justify-center">
              <i class="fa-solid fa-building text-white text-xl"></i>
            </div>
          <?php endif; ?>
          <h3 class="font-bold text-gray-800 text-sm">
            <?= htmlspecialchars($inst['inst_name_ar']) ?>
            <?php if ($inst['inst_verified']): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
          </h3>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="text-center mt-8">
      <a href="all_institutions.php<?= $cid ? '?country='.$cid : '' ?>"
        class="inline-flex items-center gap-2 px-8 py-3 border-2 border-purple-400 text-purple-600 font-bold rounded-full hover:bg-purple-50 transition">
        تصفح كل المؤسسات <i class="fa-solid fa-arrow-left"></i>
      </a>
    </div>
  </div>
</section>

<!-- CATEGORIES -->
<section class="max-w-7xl mx-auto px-4 py-16">
  <h2 class="pi-reveal pi-section-head text-2xl font-black text-gray-800 mb-8 text-center section-dot">استكشف التصنيفات</h2>

  <!-- grid: mobile 3 cols (3 rows of ~3), desktop 5 cols (2 rows of 5) — show up to 10 -->
  <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-4 mb-8">
    <?php foreach ($feat_cats as $ci => $cat):
      $label_hex  = $cat['label_color'] ?? null;
      $label_name = $cat['label_name']  ?? null;
      $delays3 = ['','pi-delay-1','pi-delay-2','pi-delay-3','pi-delay-4','pi-delay-5'];
      $dl3 = $delays3[$ci % 6];
    ?>
    <div class="pi-reveal <?= $dl3 ?> bg-white rounded-2xl p-4 shadow-sm card-hover text-center relative">
      <?php if ($label_hex && $label_name): ?>
      <div class="absolute top-2 left-2">
        <span style="background:<?= htmlspecialchars($label_hex) ?>" class="text-white text-xs font-bold px-2 py-0.5 rounded-full leading-none">
          <?= htmlspecialchars($label_name) ?>
        </span>
      </div>
      <?php endif; ?>
      <div class="w-12 h-12 rounded-xl mx-auto mb-3 mt-3 flex items-center justify-center"
        style="background:<?= htmlspecialchars($label_hex ?? '#8829C8') ?>">
        <i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?> text-white text-lg"></i>
      </div>
      <h3 class="font-bold text-gray-800 text-xs mb-3 leading-tight"><?= htmlspecialchars($cat['cat_name']) ?></h3>
      <a href="categories.php?cat=<?= $cat['cat_id'] ?><?= $cid ? '&country='.$cid : '' ?>"
        class="inline-block px-3 py-1 pi-primary-bg text-white text-xs font-bold rounded-full hover:opacity-90 transition">
        عرض
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="text-center">
    <a href="categories.php<?= $cid ? '?country='.$cid : '' ?>"
      class="inline-flex items-center gap-2 px-8 py-3 border-2 border-purple-500 text-purple-600 font-black rounded-2xl hover:bg-purple-50 transition text-sm">
      <i class="fa-solid fa-grid-2 text-xs"></i> تصفح كل التصنيفات
    </a>
  </div>
</section>

<?php if (!empty($latest_articles)): ?>
<!-- LATEST ARTICLES -->
<section class="bg-gray-50 border-t border-gray-100 py-16">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center justify-between mb-8">
      <h2 class="pi-reveal pi-section-head text-2xl font-black text-gray-800 section-dot">آخر المقالات</h2>
      <a href="blog.php" class="flex items-center gap-2 text-sm font-bold text-purple-600 hover:text-purple-800 transition">
        عرض الكل <i class="fa-solid fa-arrow-left text-xs"></i>
      </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($latest_articles as $ai => $art):
        $delays4 = ['','pi-delay-1','pi-delay-2'];
        $dl4 = $delays4[$ai % 3];
      ?>
      <a href="article.php?id=<?= $art['art_id'] ?>" class="pi-reveal <?= $dl4 ?> bg-white rounded-2xl shadow-sm card-hover overflow-hidden block group">
        <?php if (!empty($art['art_image'])): ?>
          <div class="h-44 overflow-hidden">
            <img src="<?= htmlspecialchars($art['art_image']) ?>" alt="<?= htmlspecialchars($art['art_title']) ?>"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
          </div>
        <?php else: ?>
          <div class="h-44 pi-gradient flex items-center justify-center">
            <i class="fa-solid fa-newspaper text-white text-4xl opacity-40"></i>
          </div>
        <?php endif; ?>
        <div class="p-5">
          <?php if (!empty($art['art_source'])): ?>
            <span class="inline-block text-xs font-bold text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full mb-2">
              <?= htmlspecialchars($art['art_source']) ?>
            </span>
          <?php endif; ?>
          <h3 class="font-black text-gray-800 text-sm leading-snug mb-3 line-clamp-2">
            <?= htmlspecialchars($art['art_title']) ?>
          </h3>
          <?php if (!empty($art['p_name_ar'])): ?>
          <div class="flex items-center gap-2 mt-auto">
            <?php if (!empty($art['p_photo'])): ?>
              <img src="<?= htmlspecialchars($art['p_photo']) ?>" class="w-6 h-6 rounded-full object-cover border border-purple-100">
            <?php else: ?>
              <div class="w-6 h-6 rounded-full pi-gradient flex items-center justify-center flex-shrink-0">
                <span class="text-white font-black" style="font-size:9px"><?= mb_substr($art['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <span class="text-xs text-gray-500 font-semibold"><?= htmlspecialchars($art['p_name_ar']) ?></span>
            <?php if (!empty($art['p_verified'])): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
          </div>
          <?php endif; ?>
          <p class="text-xs text-gray-400 font-semibold mt-2">
            <i class="fa-solid fa-calendar text-purple-300 ml-1"></i>
            <?= $art['art_created'] ? date('d/m/Y', strtotime($art['art_created'])) : '' ?>
          </p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
