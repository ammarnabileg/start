<?php
$pageTitle = 'نتائج البحث - PioneerIcons';
require_once 'includes/config.php';

$q = pi_escape($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'all';
$page_num = max(1,(int)($_GET['page']??1));
$per_page = 12;
$offset = ($page_num-1)*$per_page;
$total_count = pi_count_personalities() + pi_count_institutions();

$personalities = [];
$institutions  = [];
$p_total = 0;
$i_total = 0;

if ($q) {
    if ($type !== 'institutions') {
        $r = $mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE p_active=1 AND (p_name_ar LIKE '%$q%' OR p_name_en LIKE '%$q%' OR p_title LIKE '%$q%')");
        $p_total = $r ? (int)$r->fetch_assoc()['c'] : 0;
        $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 AND (p_name_ar LIKE '%$q%' OR p_name_en LIKE '%$q%' OR p_title LIKE '%$q%') ORDER BY p_views DESC LIMIT $offset,$per_page");
        if ($r) while ($row=$r->fetch_assoc()) $personalities[] = $row;
    }
    if ($type !== 'personalities') {
        $r = $mysqli->query("SELECT COUNT(*) c FROM pi_institutions WHERE inst_active=1 AND (inst_name_ar LIKE '%$q%' OR inst_name_en LIKE '%$q%')");
        $i_total = $r ? (int)$r->fetch_assoc()['c'] : 0;
        $r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 AND (inst_name_ar LIKE '%$q%' OR inst_name_en LIKE '%$q%') ORDER BY inst_views DESC LIMIT $offset,$per_page");
        if ($r) while ($row=$r->fetch_assoc()) $institutions[] = $row;
    }
}

$grand_total = $p_total + $i_total;
$total_pages = max(1, ceil($grand_total / $per_page));

include 'includes/header.php';
?>

<section class="hero-bg py-10">
  <div class="max-w-3xl mx-auto px-4">
    <form action="search.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl">
      <div class="flex items-center bg-white px-4"><i class="fa-solid fa-magnifying-glass text-gray-400 text-lg"></i></div>
      <input name="q" type="text" value="<?= htmlspecialchars($_GET['q']??'') ?>"
        placeholder="ابحث عن <?= number_format($total_count) ?> شخصية ومؤسسة..."
        class="flex-1 px-4 py-4 text-gray-800 outline-none font-semibold placeholder-gray-400">
      <button type="submit" class="pi-primary-bg px-8 py-4 text-white font-bold hover:opacity-90 transition">ابحث</button>
    </form>
  </div>
</section>

<div class="max-w-7xl mx-auto px-4 py-10">
  <?php if ($q): ?>
  <div class="flex flex-wrap items-center gap-4 mb-8">
    <h2 class="text-xl font-black text-gray-800">
      نتائج البحث عن: <span class="text-purple-600"><?= htmlspecialchars($_GET['q']??'') ?></span>
      <span class="text-base font-normal text-gray-400 mr-2">(<?= number_format($grand_total) ?> نتيجة)</span>
    </h2>
    <div class="flex gap-2 mr-auto">
      <a href="search.php?q=<?= urlencode($_GET['q']??'') ?>&type=all" class="px-4 py-1.5 text-sm font-bold rounded-full transition <?= $type!=='personalities'&&$type!=='institutions'?'pi-primary-bg text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-purple-50' ?>">الكل (<?= $grand_total ?>)</a>
      <a href="search.php?q=<?= urlencode($_GET['q']??'') ?>&type=personalities" class="px-4 py-1.5 text-sm font-bold rounded-full transition <?= $type==='personalities'?'bg-blue-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-blue-50' ?>">شخصيات (<?= $p_total ?>)</a>
      <a href="search.php?q=<?= urlencode($_GET['q']??'') ?>&type=institutions" class="px-4 py-1.5 text-sm font-bold rounded-full transition <?= $type==='institutions'?'bg-indigo-500 text-white':'bg-white border border-gray-200 text-gray-600 hover:bg-indigo-50' ?>">مؤسسات (<?= $i_total ?>)</a>
    </div>
  </div>

  <?php if (!empty($personalities)): ?>
  <h3 class="font-black text-gray-700 text-lg mb-4"><i class="fa-solid fa-users text-blue-400 mr-2"></i>الشخصيات</h3>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 mb-8">
    <?php foreach ($personalities as $p): ?>
    <a href="profile.php?id=<?= $p['p_id'] ?>" class="bg-white rounded-2xl shadow-sm card-hover block overflow-hidden">
      <div style="aspect-ratio:3/4;overflow:hidden;background:#f3f4f6;">
        <?php if ($p['p_photo']): ?>
          <img src="<?= htmlspecialchars($p['p_photo']) ?>"
            style="width:100%;height:100%;object-fit:cover;object-position:top;filter:grayscale(20%);transition:filter .3s,transform .3s;"
            onmouseover="this.style.filter='grayscale(0)';this.style.transform='scale(1.03)'"
            onmouseout="this.style.filter='grayscale(20%)';this.style.transform='scale(1)'">
        <?php else: ?>
          <div style="width:100%;height:100%;background:linear-gradient(135deg,#6d28d9,#1d4ed8);display:flex;align-items:center;justify-content:center;">
            <span style="color:#fff;font-weight:900;font-size:2rem;"><?= mb_substr($p['p_name_ar'],0,1) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <div class="p-2.5">
        <p class="font-bold text-gray-800 text-xs leading-snug">
          <?= htmlspecialchars($p['p_name_ar']) ?>
          <?php if ($p['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
        </p>
        <p class="text-gray-400 text-xs mt-0.5 leading-snug"><?= htmlspecialchars($p['p_title']??'') ?></p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($institutions)): ?>
  <h3 class="font-black text-gray-700 text-lg mb-4"><i class="fa-solid fa-building text-indigo-400 mr-2"></i>المؤسسات</h3>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-8">
    <?php foreach ($institutions as $inst): ?>
    <a href="institution.php?id=<?= $inst['inst_id'] ?>" class="bg-white rounded-2xl p-4 shadow-sm card-hover text-center block">
      <?php if ($inst['inst_logo']): ?>
        <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" class="w-14 h-14 rounded-xl mx-auto mb-2 object-contain">
      <?php else: ?>
        <div class="w-14 h-14 rounded-xl mx-auto mb-2 pi-gradient flex items-center justify-center"><i class="fa-solid fa-building text-white text-xl"></i></div>
      <?php endif; ?>
      <p class="font-bold text-gray-800 text-sm">
        <?= htmlspecialchars($inst['inst_name_ar']) ?>
        <?php if ($inst['inst_verified']): ?><i class="fa-solid fa-circle-check verified-badge text-xs"></i><?php endif; ?>
      </p>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($grand_total === 0): ?>
  <div class="text-center py-20 text-gray-400">
    <i class="fa-solid fa-magnifying-glass text-5xl mb-4 block"></i>
    <p class="text-lg font-bold mb-2">لا توجد نتائج لـ "<?= htmlspecialchars($_GET['q']??'') ?>"</p>
    <p class="text-sm">جرّب كلمات بحث مختلفة</p>
  </div>
  <?php endif; ?>

  <?php else: ?>
  <div class="text-center py-20 text-gray-400">
    <i class="fa-solid fa-magnifying-glass text-6xl mb-5 block"></i>
    <p class="text-xl font-bold mb-2">ابحث عن شخصية أو مؤسسة</p>
    <p class="text-sm">اكتب اسم الشخصية أو المؤسسة في خانة البحث</p>
  </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
