<?php
define('DOING_ADMIN', true);

// ── Period ───────────────────────────────────────────────────────────────────
$period = in_array($_GET['period'] ?? '', ['7','30','90','365']) ? (int)$_GET['period'] : 30;
$period_label = ['7'=>'آخر 7 أيام','30'=>'آخر 30 يوم','90'=>'آخر 3 شهور','365'=>'آخر سنة'][$period];

// ── Ensure visits table ──────────────────────────────────────────────────────
$mysqli->query("CREATE TABLE IF NOT EXISTS pi_visits (
    v_id INT AUTO_INCREMENT PRIMARY KEY,
    v_page VARCHAR(255),
    v_ip VARCHAR(45),
    v_user_id INT DEFAULT NULL,
    v_ref VARCHAR(500),
    v_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (v_created),
    INDEX idx_page (v_page(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Visit stats ───────────────────────────────────────────────────────────────
$v_total  = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_visits WHERE v_created >= DATE_SUB(NOW(), INTERVAL {$period} DAY)")->fetch_assoc()['c'];
$v_unique = (int)$mysqli->query("SELECT COUNT(DISTINCT v_ip) c FROM pi_visits WHERE v_created >= DATE_SUB(NOW(), INTERVAL {$period} DAY)")->fetch_assoc()['c'];
$v_today  = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_visits WHERE DATE(v_created)=CURDATE()")->fetch_assoc()['c'];
$v_yesterday = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_visits WHERE DATE(v_created)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetch_assoc()['c'];
$v_trend = $v_yesterday > 0 ? round((($v_today - $v_yesterday) / $v_yesterday) * 100) : ($v_today > 0 ? 100 : 0);

// prev period comparison
$v_prev = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_visits WHERE v_created >= DATE_SUB(NOW(), INTERVAL ".($period*2)." DAY) AND v_created < DATE_SUB(NOW(), INTERVAL {$period} DAY)")->fetch_assoc()['c'];
$v_growth = $v_prev > 0 ? round((($v_total - $v_prev) / $v_prev) * 100) : ($v_total > 0 ? 100 : 0);

// ── Chart data: visits per day ────────────────────────────────────────────────
$chart_days = min($period, 30);
$chart_labels = [];
$chart_visits = [];
$chart_unique = [];
for ($i = $chart_days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chart_labels[] = date('d/m', strtotime($d));
    $rv = $mysqli->query("SELECT COUNT(*) c, COUNT(DISTINCT v_ip) u FROM pi_visits WHERE DATE(v_created)='$d'");
    $row = $rv ? $rv->fetch_assoc() : ['c'=>0,'u'=>0];
    $chart_visits[] = (int)$row['c'];
    $chart_unique[] = (int)$row['u'];
}

// ── Top pages ─────────────────────────────────────────────────────────────────
$top_pages = [];
$rp = $mysqli->query("SELECT v_page, COUNT(*) c FROM pi_visits WHERE v_created >= DATE_SUB(NOW(), INTERVAL {$period} DAY) GROUP BY v_page ORDER BY c DESC LIMIT 8");
if ($rp) while ($row=$rp->fetch_assoc()) $top_pages[] = $row;

// ── Content counts ────────────────────────────────────────────────────────────
$count_p       = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE p_active=1")->fetch_assoc()['c'];
$count_inst    = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_institutions WHERE inst_active=1")->fetch_assoc()['c'];
$count_cats    = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_categories WHERE cat_active=1")->fetch_assoc()['c'];
$count_arts    = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_articles WHERE art_active=1")->fetch_assoc()['c'];
$count_verified= (int)$mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE p_verified=1 AND p_active=1")->fetch_assoc()['c'];
$count_exec    = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE p_membership_type='executive' AND p_active=1")->fetch_assoc()['c'];

// new this period
$new_p    = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_personalities WHERE p_created >= DATE_SUB(NOW(), INTERVAL {$period} DAY)")->fetch_assoc()['c'];
$new_inst = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_institutions WHERE inst_created >= DATE_SUB(NOW(), INTERVAL {$period} DAY)")->fetch_assoc()['c'];

// ── Users ─────────────────────────────────────────────────────────────────────
$count_users   = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_users WHERE u_active=1")->fetch_assoc()['c'];
$new_users     = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_users WHERE u_created >= DATE_SUB(NOW(), INTERVAL {$period} DAY)")->fetch_assoc()['c'];
$blocked_users = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_users WHERE u_active=0")->fetch_assoc()['c'];

// ── Pending actions ───────────────────────────────────────────────────────────
$pending_subs = 0; $r = $mysqli->query("SHOW TABLES LIKE 'pi_submissions'"); if ($r&&$r->num_rows) $pending_subs = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_submissions WHERE sub_status='pending'")->fetch_assoc()['c'];
$new_cmps     = 0; $r = $mysqli->query("SHOW TABLES LIKE 'pi_complaints'");  if ($r&&$r->num_rows) $new_cmps     = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_complaints WHERE cmp_status='new'")->fetch_assoc()['c'];
$pending_mems = 0; $r = $mysqli->query("SHOW TABLES LIKE 'pi_memberships'"); if ($r&&$r->num_rows) $pending_mems = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_memberships WHERE mem_status='pending'")->fetch_assoc()['c'];
$new_advs     = 0; $r = $mysqli->query("SHOW TABLES LIKE 'pi_advertise'");   if ($r&&$r->num_rows) $new_advs     = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_advertise WHERE adv_status='new'")->fetch_assoc()['c'];

// ── Top viewed personalities & institutions ───────────────────────────────────
$top_p = [];
$r = $mysqli->query("SELECT p_name_ar,p_title,p_photo,p_views,p_id FROM pi_personalities WHERE p_active=1 ORDER BY p_views DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $top_p[] = $row;

$top_inst = [];
$r = $mysqli->query("SELECT inst_name_ar,inst_logo,inst_id,0 as inst_views FROM pi_institutions WHERE inst_active=1 LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $top_inst[] = $row;

// ── Recent activity ───────────────────────────────────────────────────────────
$recent_p = [];
$r = $mysqli->query("SELECT p_name_ar,p_title,p_photo,p_created FROM pi_personalities ORDER BY p_created DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $recent_p[] = $row;

$recent_users = [];
$r = $mysqli->query("SELECT u_name,u_email,u_plan,u_created FROM pi_users ORDER BY u_created DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $recent_users[] = $row;

// ── Plan distribution ─────────────────────────────────────────────────────────
$plan_free     = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_users WHERE u_plan='free'")->fetch_assoc()['c'];
$plan_verified = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_users WHERE u_plan='verified'")->fetch_assoc()['c'];
$plan_exec     = (int)$mysqli->query("SELECT COUNT(*) c FROM pi_users WHERE u_plan='executive'")->fetch_assoc()['c'];

// ── Hourly visits today (for bar chart) ──────────────────────────────────────
$hourly = array_fill(0, 24, 0);
$rh = $mysqli->query("SELECT HOUR(v_created) h, COUNT(*) c FROM pi_visits WHERE DATE(v_created)=CURDATE() GROUP BY h");
if ($rh) while ($row=$rh->fetch_assoc()) $hourly[(int)$row['h']] = (int)$row['c'];
?>

<!-- Period selector -->
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-black text-gray-800">لوحة التحكم</h1>
    <p class="text-gray-400 text-sm mt-0.5"><?= date('l, d F Y') ?></p>
  </div>
  <div class="flex gap-1.5 bg-white border border-gray-200 rounded-xl p-1">
    <?php foreach (['7'=>'7 أيام','30'=>'30 يوم','90'=>'3 شهور','365'=>'سنة'] as $pv=>$pl): ?>
    <a href="admin.php?p=dashboard&period=<?= $pv ?>"
      class="px-4 py-1.5 rounded-lg text-sm font-bold transition <?= $period==(int)$pv ? 'pi-primary-bg text-white' : 'text-gray-500 hover:bg-gray-100' ?>">
      <?= $pl ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── KPI row ── -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">

  <!-- Total visits -->
  <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-bold text-gray-400 mb-1">إجمالي الزيارات</p>
        <p class="text-3xl font-black text-gray-800"><?= number_format($v_total) ?></p>
        <p class="text-xs mt-1 font-semibold <?= $v_growth>=0?'text-green-500':'text-red-400' ?>">
          <i class="fa-solid fa-<?= $v_growth>=0?'arrow-trend-up':'arrow-trend-down' ?> ml-1"></i>
          <?= abs($v_growth) ?>% مقارنةً بالفترة السابقة
        </p>
      </div>
      <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
        <i class="fa-solid fa-chart-line text-purple-600"></i>
      </div>
    </div>
  </div>

  <!-- Unique visitors -->
  <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-bold text-gray-400 mb-1">زوار فريدون</p>
        <p class="text-3xl font-black text-gray-800"><?= number_format($v_unique) ?></p>
        <p class="text-xs mt-1 text-gray-400 font-semibold"><?= $period_label ?></p>
      </div>
      <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
        <i class="fa-solid fa-users text-blue-600"></i>
      </div>
    </div>
  </div>

  <!-- Today -->
  <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-bold text-gray-400 mb-1">زيارات اليوم</p>
        <p class="text-3xl font-black text-gray-800"><?= number_format($v_today) ?></p>
        <p class="text-xs mt-1 font-semibold <?= $v_trend>=0?'text-green-500':'text-red-400' ?>">
          <i class="fa-solid fa-<?= $v_trend>=0?'arrow-up':'arrow-down' ?> ml-1"></i>
          <?= abs($v_trend) ?>% عن أمس
        </p>
      </div>
      <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
        <i class="fa-solid fa-eye text-green-600"></i>
      </div>
    </div>
  </div>

  <!-- New users -->
  <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-bold text-gray-400 mb-1">مستخدمون جدد</p>
        <p class="text-3xl font-black text-gray-800"><?= number_format($new_users) ?></p>
        <p class="text-xs mt-1 text-gray-400 font-semibold"><?= number_format($count_users) ?> إجمالي نشطون</p>
      </div>
      <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
        <i class="fa-solid fa-user-plus text-amber-600"></i>
      </div>
    </div>
  </div>
</div>

<!-- ── Charts row ── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

  <!-- Visits line chart -->
  <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-black text-gray-800">الزيارات — <?= $period_label ?></h3>
      <div class="flex items-center gap-4 text-xs font-semibold text-gray-400">
        <span class="flex items-center gap-1.5"><span class="w-3 h-1 rounded-full bg-purple-500 inline-block"></span>إجمالي</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-1 rounded-full bg-blue-400 inline-block"></span>فريدون</span>
      </div>
    </div>
    <canvas id="visitsChart" height="100"></canvas>
  </div>

  <!-- Hourly bar today -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-black text-gray-800 mb-4">توزيع زيارات اليوم بالساعة</h3>
    <canvas id="hourlyChart" height="190"></canvas>
  </div>
</div>

<!-- ── Pending actions alert row ── -->
<?php if ($pending_subs || $new_cmps || $pending_mems || $new_advs): ?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
  <?php if ($pending_subs): ?>
  <a href="admin.php?p=submissions" class="flex items-center gap-3 bg-yellow-50 border border-yellow-200 rounded-2xl p-4 hover:bg-yellow-100 transition">
    <div class="w-9 h-9 bg-yellow-400 rounded-xl flex items-center justify-center flex-shrink-0">
      <i class="fa-solid fa-inbox text-white text-sm"></i>
    </div>
    <div>
      <p class="font-black text-yellow-800 text-lg leading-none"><?= $pending_subs ?></p>
      <p class="text-yellow-700 text-xs font-semibold mt-0.5">مقترحات بانتظار المراجعة</p>
    </div>
  </a>
  <?php endif; ?>
  <?php if ($new_cmps): ?>
  <a href="admin.php?p=complaints" class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-2xl p-4 hover:bg-red-100 transition">
    <div class="w-9 h-9 bg-red-500 rounded-xl flex items-center justify-center flex-shrink-0">
      <i class="fa-solid fa-message text-white text-sm"></i>
    </div>
    <div>
      <p class="font-black text-red-800 text-lg leading-none"><?= $new_cmps ?></p>
      <p class="text-red-700 text-xs font-semibold mt-0.5">شكاوي جديدة</p>
    </div>
  </a>
  <?php endif; ?>
  <?php if ($pending_mems): ?>
  <a href="admin.php?p=memberships" class="flex items-center gap-3 bg-purple-50 border border-purple-200 rounded-2xl p-4 hover:bg-purple-100 transition">
    <div class="w-9 h-9 bg-purple-500 rounded-xl flex items-center justify-center flex-shrink-0">
      <i class="fa-solid fa-crown text-white text-sm"></i>
    </div>
    <div>
      <p class="font-black text-purple-800 text-lg leading-none"><?= $pending_mems ?></p>
      <p class="text-purple-700 text-xs font-semibold mt-0.5">طلبات عضوية</p>
    </div>
  </a>
  <?php endif; ?>
  <?php if ($new_advs): ?>
  <a href="admin.php?p=advertise" class="flex items-center gap-3 bg-blue-50 border border-blue-200 rounded-2xl p-4 hover:bg-blue-100 transition">
    <div class="w-9 h-9 bg-blue-500 rounded-xl flex items-center justify-center flex-shrink-0">
      <i class="fa-solid fa-bullhorn text-white text-sm"></i>
    </div>
    <div>
      <p class="font-black text-blue-800 text-lg leading-none"><?= $new_advs ?></p>
      <p class="text-blue-700 text-xs font-semibold mt-0.5">طلبات إعلان جديدة</p>
    </div>
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Content summary ── -->
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
  <?php
  $content_cards = [
    ['label'=>'الشخصيات','val'=>$count_p,'new'=>$new_p,'icon'=>'fa-users','color'=>'blue','link'=>'personalities'],
    ['label'=>'المؤسسات','val'=>$count_inst,'new'=>$new_inst,'icon'=>'fa-building','color'=>'indigo','link'=>'institutions'],
    ['label'=>'الموثقون','val'=>$count_verified,'new'=>null,'icon'=>'fa-circle-check','color'=>'green','link'=>'personalities'],
    ['label'=>'التنفيذيون','val'=>$count_exec,'new'=>null,'icon'=>'fa-crown','color'=>'amber','link'=>'personalities'],
    ['label'=>'التصنيفات','val'=>$count_cats,'new'=>null,'icon'=>'fa-tags','color'=>'purple','link'=>'categories'],
    ['label'=>'المقالات','val'=>$count_arts,'new'=>null,'icon'=>'fa-newspaper','color'=>'rose','link'=>'articles'],
  ];
  foreach ($content_cards as $cc): ?>
  <a href="admin.php?p=<?= $cc['link'] ?>" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition group">
    <div class="w-9 h-9 bg-<?= $cc['color'] ?>-100 rounded-xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
      <i class="fa-solid <?= $cc['icon'] ?> text-<?= $cc['color'] ?>-600 text-sm"></i>
    </div>
    <p class="text-2xl font-black text-gray-800"><?= number_format($cc['val']) ?></p>
    <p class="text-gray-400 text-xs font-semibold"><?= $cc['label'] ?></p>
    <?php if ($cc['new'] !== null && $cc['new'] > 0): ?>
    <p class="text-<?= $cc['color'] ?>-500 text-xs font-bold mt-1">+<?= $cc['new'] ?> جديد</p>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── Bottom grid ── -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

  <!-- Top visited pages -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-black text-gray-800 mb-4 flex items-center gap-2">
      <i class="fa-solid fa-fire text-orange-400"></i> أكثر الصفحات زيارةً
    </h3>
    <?php if (empty($top_pages)): ?>
    <p class="text-gray-400 text-sm text-center py-8">لا توجد بيانات بعد</p>
    <?php else:
      $max_p = max(array_column($top_pages, 'c')) ?: 1;
      foreach ($top_pages as $tp):
        $name = basename($tp['v_page'] ?: '/index');
        $pct  = round(($tp['c'] / $max_p) * 100);
    ?>
    <div class="mb-3">
      <div class="flex items-center justify-between text-xs mb-1">
        <span class="font-semibold text-gray-700 truncate max-w-32"><?= htmlspecialchars($name) ?></span>
        <span class="font-black text-gray-600"><?= number_format($tp['c']) ?></span>
      </div>
      <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
        <div class="h-full rounded-full" style="width:<?= $pct ?>%;background:linear-gradient(90deg,#8829C8,#5B1494)"></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Users plan doughnut + recent users -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-black text-gray-800 mb-4 flex items-center gap-2">
      <i class="fa-solid fa-user-group text-purple-500"></i> توزيع المستخدمين
    </h3>
    <div class="flex items-center gap-4 mb-4">
      <canvas id="planChart" width="100" height="100"></canvas>
      <div class="space-y-2 flex-1">
        <div class="flex items-center justify-between">
          <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-600"><span class="w-2.5 h-2.5 rounded-full bg-gray-300 inline-block"></span>مجاني</span>
          <span class="font-black text-gray-800 text-sm"><?= number_format($plan_free) ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-600"><span class="w-2.5 h-2.5 rounded-full bg-blue-400 inline-block"></span>موثق</span>
          <span class="font-black text-gray-800 text-sm"><?= number_format($plan_verified) ?></span>
        </div>
        <div class="flex items-center justify-between">
          <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-600"><span class="w-2.5 h-2.5 rounded-full bg-purple-600 inline-block"></span>تنفيذي</span>
          <span class="font-black text-gray-800 text-sm"><?= number_format($plan_exec) ?></span>
        </div>
        <?php if ($blocked_users): ?>
        <div class="flex items-center justify-between">
          <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-600"><span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block"></span>محظور</span>
          <span class="font-black text-red-600 text-sm"><?= number_format($blocked_users) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <a href="admin.php?p=users" class="block w-full py-2 text-center text-xs font-black text-purple-600 border border-purple-200 rounded-xl hover:bg-purple-50 transition">
      إدارة المستخدمين ←
    </a>
  </div>

  <!-- Top personalities -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <h3 class="font-black text-gray-800 mb-4 flex items-center gap-2">
      <i class="fa-solid fa-trophy text-amber-400"></i> الشخصيات الأكثر زيارة
    </h3>
    <div class="space-y-2">
      <?php foreach ($top_p as $i => $tp): ?>
      <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition">
        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-black flex-shrink-0
          <?= $i==0?'bg-amber-400 text-white':($i==1?'bg-gray-300 text-gray-700':($i==2?'bg-orange-300 text-white':'bg-gray-100 text-gray-500')) ?>">
          <?= $i+1 ?>
        </span>
        <?php if ($tp['p_photo']): ?>
        <img src="../<?= htmlspecialchars($tp['p_photo']) ?>" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
        <?php else: ?>
        <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-xs font-black flex-shrink-0">
          <?= mb_substr($tp['p_name_ar'],0,1) ?>
        </div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-gray-800 text-xs truncate"><?= htmlspecialchars($tp['p_name_ar']) ?></p>
          <p class="text-gray-400 text-xs truncate"><?= htmlspecialchars($tp['p_title'] ?? '') ?></p>
        </div>
        <span class="text-xs font-black text-purple-600 flex-shrink-0"><?= number_format($tp['p_views']) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($top_p)): ?>
      <p class="text-gray-400 text-sm text-center py-6">لا توجد بيانات</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Recent activity row ── -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">

  <!-- Recent personalities -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-black text-gray-800">أحدث الشخصيات المضافة</h3>
      <a href="admin.php?p=personalities" class="text-purple-600 text-xs font-bold hover:underline">عرض الكل ←</a>
    </div>
    <div class="space-y-2">
      <?php foreach ($recent_p as $rp): ?>
      <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition">
        <?php if ($rp['p_photo']): ?>
        <img src="../<?= htmlspecialchars($rp['p_photo']) ?>" class="w-9 h-9 rounded-full object-cover flex-shrink-0">
        <?php else: ?>
        <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-black text-sm flex-shrink-0">
          <?= mb_substr($rp['p_name_ar'],0,1) ?>
        </div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-gray-800 text-sm truncate"><?= htmlspecialchars($rp['p_name_ar']) ?></p>
          <p class="text-gray-400 text-xs truncate"><?= htmlspecialchars($rp['p_title'] ?? '') ?></p>
        </div>
        <span class="text-xs text-gray-400 flex-shrink-0"><?= date('d/m/Y', strtotime($rp['p_created'])) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($recent_p)): ?><p class="text-gray-400 text-sm text-center py-6">لا يوجد</p><?php endif; ?>
    </div>
  </div>

  <!-- Recent users -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-black text-gray-800">أحدث المستخدمين المسجلين</h3>
      <a href="admin.php?p=users" class="text-purple-600 text-xs font-bold hover:underline">عرض الكل ←</a>
    </div>
    <div class="space-y-2">
      <?php
      $plan_colors = ['free'=>'bg-gray-100 text-gray-600','verified'=>'bg-blue-100 text-blue-700','executive'=>'bg-purple-100 text-purple-700'];
      $plan_names  = ['free'=>'مجاني','verified'=>'موثق','executive'=>'تنفيذي'];
      foreach ($recent_users as $ru): ?>
      <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition">
        <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-black text-sm flex-shrink-0">
          <?= mb_substr($ru['u_name'],0,1) ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-gray-800 text-sm truncate"><?= htmlspecialchars($ru['u_name']) ?></p>
          <p class="text-gray-400 text-xs truncate"><?= htmlspecialchars($ru['u_email']) ?></p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <span class="text-xs px-2 py-0.5 rounded-full font-bold <?= $plan_colors[$ru['u_plan']] ?>">
            <?= $plan_names[$ru['u_plan']] ?>
          </span>
          <span class="text-xs text-gray-400"><?= date('d/m', strtotime($ru['u_created'])) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($recent_users)): ?><p class="text-gray-400 text-sm text-center py-6">لا يوجد</p><?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Quick actions ── -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
  <h3 class="font-black text-gray-800 mb-4">إجراءات سريعة</h3>
  <div class="flex flex-wrap gap-3">
    <?php if (pi_has_perm('add_personality')): ?>
    <a href="admin.php?p=personalities&action=add" class="flex items-center gap-2 px-5 py-2.5 bg-blue-500 text-white font-bold rounded-xl hover:bg-blue-600 transition text-sm">
      <i class="fa-solid fa-user-plus"></i> شخصية جديدة
    </a>
    <?php endif; ?>
    <?php if (pi_has_perm('add_institution')): ?>
    <a href="admin.php?p=institutions&action=add" class="flex items-center gap-2 px-5 py-2.5 bg-indigo-500 text-white font-bold rounded-xl hover:bg-indigo-600 transition text-sm">
      <i class="fa-solid fa-building-circle-arrow-right"></i> مؤسسة جديدة
    </a>
    <?php endif; ?>
    <?php if (pi_has_perm('add_article')): ?>
    <a href="admin.php?p=articles&action=add" class="flex items-center gap-2 px-5 py-2.5 bg-rose-500 text-white font-bold rounded-xl hover:bg-rose-600 transition text-sm">
      <i class="fa-solid fa-newspaper"></i> مقال جديد
    </a>
    <?php endif; ?>
    <?php if (pi_has_perm('manage_users')): ?>
    <a href="admin.php?p=users" class="flex items-center gap-2 px-5 py-2.5 bg-purple-500 text-white font-bold rounded-xl hover:bg-purple-600 transition text-sm">
      <i class="fa-solid fa-user-group"></i> المستخدمون
    </a>
    <?php endif; ?>
    <?php if (pi_has_perm('manage_settings')): ?>
    <a href="admin.php?p=settings" class="flex items-center gap-2 px-5 py-2.5 bg-gray-600 text-white font-bold rounded-xl hover:bg-gray-700 transition text-sm">
      <i class="fa-solid fa-gear"></i> الإعدادات
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Cairo', sans-serif";
Chart.defaults.color = '#9ca3af';

// ── Visits line chart
new Chart(document.getElementById('visitsChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chart_labels) ?>,
    datasets: [
      {
        label: 'إجمالي الزيارات',
        data: <?= json_encode($chart_visits) ?>,
        borderColor: '#8829C8',
        backgroundColor: 'rgba(136,41,200,0.08)',
        borderWidth: 2.5,
        pointBackgroundColor: '#8829C8',
        pointRadius: 3,
        fill: true,
        tension: 0.4
      },
      {
        label: 'زوار فريدون',
        data: <?= json_encode($chart_unique) ?>,
        borderColor: '#60a5fa',
        backgroundColor: 'rgba(96,165,250,0.05)',
        borderWidth: 2,
        pointBackgroundColor: '#60a5fa',
        pointRadius: 3,
        fill: true,
        tension: 0.4
      }
    ]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: false },
      tooltip: { rtl: true, bodyFont: { family: "'Cairo', sans-serif" }, titleFont: { family: "'Cairo', sans-serif" } }
    },
    scales: {
      x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
      y: { grid: { color: '#f3f4f6' }, beginAtZero: true, ticks: { precision: 0 } }
    }
  }
});

// ── Hourly bar chart
new Chart(document.getElementById('hourlyChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(fn($h)=>$h.':00', range(0,23))) ?>,
    datasets: [{
      label: 'زيارات',
      data: <?= json_encode(array_values($hourly)) ?>,
      backgroundColor: 'rgba(136,41,200,0.7)',
      borderRadius: 4,
      borderSkipped: false
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false }, tooltip: { rtl: true } },
    scales: {
      x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } },
      y: { grid: { color: '#f3f4f6' }, beginAtZero: true, ticks: { precision: 0 } }
    }
  }
});

// ── Plan doughnut
new Chart(document.getElementById('planChart'), {
  type: 'doughnut',
  data: {
    labels: ['مجاني', 'موثق', 'تنفيذي'],
    datasets: [{
      data: [<?= $plan_free ?>, <?= $plan_verified ?>, <?= $plan_exec ?>],
      backgroundColor: ['#d1d5db','#60a5fa','#8829C8'],
      borderWidth: 0,
      hoverOffset: 4
    }]
  },
  options: {
    responsive: false,
    cutout: '70%',
    plugins: {
      legend: { display: false },
      tooltip: { rtl: true, callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.raw } }
    }
  }
});
</script>
