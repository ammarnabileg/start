<?php
$count_p    = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE p_active=1")->fetch_assoc()['c'];
$count_inst = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_institutions WHERE inst_active=1")->fetch_assoc()['c'];
$count_cats = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_categories WHERE cat_active=1")->fetch_assoc()['c'];
$count_arts = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_articles WHERE art_active=1")->fetch_assoc()['c'];
$count_verified = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE p_verified=1 AND p_active=1")->fetch_assoc()['c'];
$count_exec  = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE p_membership_type='executive' AND p_active=1")->fetch_assoc()['c'];

$stats = [
    ['label'=>'الشخصيات','value'=>$count_p,'icon'=>'fa-users','color'=>'bg-blue-500','link'=>'admin.php?p=personalities'],
    ['label'=>'المؤسسات','value'=>$count_inst,'icon'=>'fa-building','color'=>'bg-indigo-500','link'=>'admin.php?p=institutions'],
    ['label'=>'الموثقون','value'=>$count_verified,'icon'=>'fa-circle-check','color'=>'bg-green-500','link'=>'admin.php?p=personalities&filter=verified'],
    ['label'=>'الرؤساء التنفيذيون','value'=>$count_exec,'icon'=>'fa-crown','color'=>'bg-yellow-500','link'=>'admin.php?p=personalities&filter=executive'],
    ['label'=>'التصنيفات','value'=>$count_cats,'icon'=>'fa-tags','color'=>'bg-purple-500','link'=>'admin.php?p=categories'],
    ['label'=>'المقالات','value'=>$count_arts,'icon'=>'fa-newspaper','color'=>'pi-gradient','link'=>'admin.php?p=articles'],
];

// Recent personalities
$recent_p = [];
$r = $mysqli->query("SELECT * FROM pi_personalities ORDER BY p_created DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $recent_p[] = $row;

// Top viewed
$top_p = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 ORDER BY p_views DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $top_p[] = $row;
?>

<!-- Stats grid -->
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
  <?php foreach ($stats as $s): ?>
  <a href="<?= $s['link'] ?>" class="bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition card-hover">
    <div class="w-11 h-11 <?= $s['color'] ?> rounded-xl flex items-center justify-center mb-3">
      <i class="fa-solid <?= $s['icon'] ?> text-white text-lg"></i>
    </div>
    <p class="text-2xl font-black text-gray-800"><?= number_format($s['value']) ?></p>
    <p class="text-gray-400 text-xs font-semibold mt-0.5"><?= $s['label'] ?></p>
  </a>
  <?php endforeach; ?>
</div>

<!-- Quick actions -->
<div class="bg-white rounded-2xl shadow-sm p-6 mb-8">
  <h3 class="font-black text-gray-800 mb-4">إجراءات سريعة</h3>
  <div class="flex flex-wrap gap-3">
    <?php if (pi_has_perm('add_personality')): ?>
    <a href="admin.php?p=personalities&action=add" class="flex items-center gap-2 px-5 py-2.5 bg-blue-500 text-white font-bold rounded-xl hover:bg-blue-600 transition text-sm">
      <i class="fa-solid fa-user-plus"></i> إضافة شخصية
    </a>
    <?php endif; ?>
    <?php if (pi_has_perm('add_institution')): ?>
    <a href="admin.php?p=institutions&action=add" class="flex items-center gap-2 px-5 py-2.5 bg-indigo-500 text-white font-bold rounded-xl hover:bg-indigo-600 transition text-sm">
      <i class="fa-solid fa-building-circle-arrow-right"></i> إضافة مؤسسة
    </a>
    <?php endif; ?>
    <?php if (pi_has_perm('add_category')): ?>
    <a href="admin.php?p=categories&action=add" class="flex items-center gap-2 px-5 py-2.5 bg-purple-500 text-white font-bold rounded-xl hover:bg-purple-600 transition text-sm">
      <i class="fa-solid fa-tag"></i> إضافة تصنيف
    </a>
    <?php endif; ?>
    <?php if (pi_has_perm('add_role')): ?>
    <a href="admin.php?p=roles&action=add" class="flex items-center gap-2 px-5 py-2.5 pi-gradient text-white font-bold rounded-xl hover:opacity-90 transition text-sm">
      <i class="fa-solid fa-shield-halved"></i> إضافة دور جديد
    </a>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <!-- Recent personalities -->
  <div class="bg-white rounded-2xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-black text-gray-800">أحدث الشخصيات</h3>
      <a href="admin.php?p=personalities" class="text-purple-600 text-sm font-bold hover:underline">عرض الكل</a>
    </div>
    <div class="space-y-3">
      <?php foreach ($recent_p as $rp): ?>
      <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition">
        <?php if ($rp['p_photo']): ?>
          <img src="<?= htmlspecialchars($rp['p_photo']) ?>" class="w-10 h-10 rounded-full object-cover">
        <?php else: ?>
          <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-sm">
            <?= mb_substr($rp['p_name_ar'],0,1) ?>
          </div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-gray-800 text-sm truncate">
            <?= htmlspecialchars($rp['p_name_ar']) ?>
            <?php if ($rp['p_verified']): ?><i class="fa-solid fa-circle-check text-blue-500 text-xs mr-1"></i><?php endif; ?>
          </p>
          <p class="text-gray-400 text-xs"><?= htmlspecialchars($rp['p_title'] ?? '') ?></p>
        </div>
        <span class="text-xs text-gray-400"><?= date('d/m', strtotime($rp['p_created'])) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($recent_p)): ?>
      <p class="text-gray-400 text-center py-6 text-sm">لا توجد شخصيات بعد</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Top viewed -->
  <div class="bg-white rounded-2xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-black text-gray-800">الأكثر زيارة</h3>
      <a href="admin.php?p=personalities" class="text-purple-600 text-sm font-bold hover:underline">عرض الكل</a>
    </div>
    <div class="space-y-3">
      <?php foreach ($top_p as $i=>$tp): ?>
      <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition">
        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black
          <?= $i==0?'bg-yellow-400 text-white':($i==1?'bg-gray-300 text-gray-700':($i==2?'bg-orange-300 text-white':'bg-gray-100 text-gray-500')) ?>">
          <?= $i+1 ?>
        </span>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-gray-800 text-sm truncate"><?= htmlspecialchars($tp['p_name_ar']) ?></p>
          <p class="text-gray-400 text-xs"><?= htmlspecialchars($tp['p_title'] ?? '') ?></p>
        </div>
        <span class="text-xs font-bold text-purple-600"><?= number_format($tp['p_views']) ?> زيارة</span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($top_p)): ?>
      <p class="text-gray-400 text-center py-6 text-sm">لا توجد بيانات</p>
      <?php endif; ?>
    </div>
  </div>
</div>
