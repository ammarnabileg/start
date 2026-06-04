<?php
require_once 'includes/config.php';
$pageTitle = 'القوائم | ' . pi_setting('site_name_ar');
$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'ar';
include 'includes/header.php';

$r = $mysqli->query("SELECT l.*, (SELECT COUNT(*) FROM pi_list_items WHERE li_list_id=l.list_id) as items_count FROM pi_lists l WHERE list_active=1 ORDER BY list_order ASC, list_id DESC");
$lists = [];
if ($r) while ($row = $r->fetch_assoc()) $lists[] = $row;
?>
<style>
.lists-hero { background: linear-gradient(160deg, #0B0B1F 0%, #130B2B 50%, #1A0D35 100%); position:relative; overflow:hidden; }
.lists-hero::before { content:''; position:absolute; inset:0; background-image: radial-gradient(circle, rgba(255,255,255,.4) 1px, transparent 1px); background-size:36px 36px; opacity:.05; pointer-events:none; }
.list-card { transition: transform .25s, box-shadow .25s; }
.list-card:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(136,41,200,.13); }
.list-card .cover-img { transition: transform .5s; }
.list-card:hover .cover-img { transform: scale(1.05); }
</style>

<!-- Hero -->
<section class="lists-hero py-20">
  <div class="max-w-5xl mx-auto px-4 text-center relative z-10">
    <div class="inline-flex items-center gap-2 bg-white/10 text-purple-200 px-4 py-1.5 rounded-full text-sm font-bold mb-6 border border-white/20">
      <i class="fa-solid fa-list-ol text-xs"></i>
      <?= $lang === 'en' ? count($lists).' Lists Available' : count($lists).' قائمة متاحة' ?>
    </div>
    <h1 class="text-4xl md:text-5xl font-black text-white mb-4 leading-tight">
      <?= $lang === 'en' ? htmlspecialchars(pi_setting('site_name')).' <span class="text-purple-300">Lists</span>' : 'قوائم <span class="text-purple-300">'.htmlspecialchars(pi_setting('site_name_ar')).'</span>' ?>
    </h1>
    <p class="text-gray-300 text-lg max-w-2xl mx-auto leading-relaxed mb-6">
      <?= $lang === 'en'
        ? 'Documented rankings of the most influential Arab personalities and institutions, ranked with precision and regularly updated.'
        : 'قوائم موثقة لأبرز الشخصيات والمؤسسات العربية، مرتبة وفق معايير دقيقة ومحدثة.' ?>
    </p>
    <!-- Language toggle -->
    <div class="flex items-center justify-center gap-2 mt-2">
      <a href="lists.php<?= $lang==='en'?'':'?lang=en' ?>"
        class="px-4 py-1.5 rounded-full text-xs font-bold border transition <?= $lang==='en'?'bg-white text-purple-700 border-white':'bg-transparent text-white/60 border-white/30 hover:border-white/60' ?>">
        English
      </a>
      <a href="lists.php<?= $lang==='ar'?'':'?lang=ar' ?>"
        class="px-4 py-1.5 rounded-full text-xs font-bold border transition <?= $lang==='ar'?'bg-white text-purple-700 border-white':'bg-transparent text-white/60 border-white/30 hover:border-white/60' ?>">
        العربية
      </a>
    </div>
  </div>
</section>

<!-- Lists grid -->
<section class="py-14 bg-gray-50">
  <div class="max-w-7xl mx-auto px-4">
    <?php if (empty($lists)): ?>
    <div class="text-center py-20 text-gray-400">
      <i class="fa-solid fa-list-ol text-6xl mb-5 opacity-20"></i>
      <p class="text-xl font-bold mb-2"><?= $lang==='en'?'No lists published yet':'لا توجد قوائم منشورة' ?></p>
      <p class="text-sm"><?= $lang==='en'?'Check back soon':'تابعنا لمعرفة آخر القوائم' ?></p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-7">
      <?php foreach ($lists as $lst):
        $cover = $lst['list_cover'] ?? '';
        $logo  = $lst['list_logo']  ?? '';
        $year  = $lst['list_year']  ?? '';
        $desc  = strip_tags($lst['list_description'] ?? '');
        $title = $lang === 'en' && !empty($lst['list_title_en']) ? $lst['list_title_en'] : $lst['list_title'];
      ?>
      <a href="list.php?id=<?= $lst['list_id'] ?><?= $lang==='en'?'&lang=en':'' ?>"
        class="list-card group bg-white rounded-2xl overflow-hidden border border-gray-100 block">
        <!-- Cover -->
        <div class="relative h-48 overflow-hidden bg-gray-100">
          <?php if ($cover): ?>
          <img src="<?= htmlspecialchars($cover) ?>" alt="" class="cover-img w-full h-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
          <?php else: ?>
          <div class="w-full h-full bg-gradient-to-br from-purple-700 via-purple-600 to-indigo-900 flex items-center justify-center">
            <i class="fa-solid fa-list-ol text-white/15 text-7xl"></i>
          </div>
          <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
          <?php endif; ?>

          <?php if ($year): ?>
          <div class="absolute top-3 <?= $lang==='en'?'right-3':'left-3' ?> bg-white/90 text-purple-700 text-xs font-black px-3 py-1 rounded-full shadow-sm">
            <?= htmlspecialchars($year) ?>
          </div>
          <?php endif; ?>

          <?php if ($logo): ?>
          <div class="absolute bottom-3 <?= $lang==='en'?'left-3':'right-3' ?>">
            <img src="<?= htmlspecialchars($logo) ?>" alt=""
              class="h-10 max-w-24 object-contain bg-white/90 rounded-lg p-1 shadow">
          </div>
          <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="p-5">
          <h2 class="font-black text-gray-900 text-lg leading-tight mb-2 group-hover:text-purple-700 transition line-clamp-2">
            <?= htmlspecialchars($title) ?>
          </h2>
          <?php if ($desc): ?>
          <p class="text-gray-500 text-sm leading-relaxed mb-4 line-clamp-2"
             style="-webkit-line-clamp:2;display:-webkit-box;-webkit-box-orient:vertical;overflow:hidden">
            <?= htmlspecialchars(mb_substr($desc, 0, 150)) ?>
          </p>
          <?php endif; ?>
          <div class="flex items-center justify-between pt-3 border-t border-gray-50">
            <div class="flex items-center gap-3 text-xs text-gray-400">
              <span><i class="fa-solid fa-users ml-1"></i><?= (int)$lst['items_count'] ?></span>
              <span><i class="fa-solid fa-eye ml-1"></i><?= number_format((int)$lst['list_views']) ?></span>
            </div>
            <span class="flex items-center gap-1 text-purple-600 font-bold text-sm group-hover:gap-2 transition-all">
              <?= $lang==='en' ? 'Browse List' : 'تصفح القائمة' ?>
              <i class="fa-solid fa-arrow-<?= $lang==='en'?'right':'left' ?> text-xs"></i>
            </span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
