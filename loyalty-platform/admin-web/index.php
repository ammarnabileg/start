<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('dashboard', 'view');

$days = (int) get('days', 30);
if (!in_array($days, [7, 30, 90, 365], true)) $days = 30;
$since = (new DateTime("-{$days} days"))->format('Y-m-d');

$kpi = [
  'users'        => (int) scalar("select count(*) from public.users"),
  'users_new'    => (int) scalar("select count(*) from public.users where created_at >= :s", ['s' => $since]),
  'merchants'    => (int) scalar("select count(*) from public.merchants"),
  'pending'      => (int) scalar("select count(*) from public.merchants where status='pending'"),
  'active_subs'  => (int) scalar("select count(distinct merchant_id) from public.subscriptions where status in ('active','trial')"),
  'reports_open' => (int) scalar("select count(*) from public.reports where status='open'"),
  'points'       => (int) scalar("select coalesce(sum(points) filter (where type='earn'),0) from public.points_transactions"),
  'visits'       => (int) scalar("select count(*) from public.user_visits where visit_date >= :s", ['s' => $since]),
];

$byStatus = [];
foreach (all("select status, count(*) c from public.merchants group by status") as $r) $byStatus[$r['status']] = (int)$r['c'];

$signups = all("select to_char(created_at::date,'MM-DD') d, count(*) c
                from public.users where created_at >= :s group by 1 order by 1", ['s' => $since]);
$maxC = max(1, ...array_map(fn($r) => (int)$r['c'], $signups ?: [['c' => 1]]));

$recent = all("select id, name, phone, email, created_at from public.users order by created_at desc limit 8");

$title = 'لوحة التحكم';
require __DIR__ . '/partials/header.php';

function kpi_card(string $label, $val, string $sub = '', string $color = 'amber'): void {
  echo '<div class="bg-white rounded-xl border p-5">
    <div class="text-gray-500 text-sm">' . e($label) . '</div>
    <div class="text-3xl font-extrabold mt-1 text-' . $color . '-600">' . n($val) . '</div>
    <div class="text-xs text-gray-400 mt-1">' . e($sub) . '</div></div>';
}
?>
<!-- فلتر الفترة -->
<div class="flex gap-2 mb-5">
  <?php foreach ([7=>'٧ أيام',30=>'٣٠ يوم',90=>'٩٠ يوم',365=>'سنة'] as $k=>$lbl):
    $cls=$days===$k?'bg-amber-500 text-white':'bg-white text-gray-600 border'; ?>
    <a href="?days=<?= $k ?>" class="px-4 py-1.5 rounded-lg text-sm font-bold <?= $cls ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <?php
    kpi_card('إجمالي المستخدمين', $kpi['users'], "جديد آخر {$days} يوم: " . n($kpi['users_new']));
    kpi_card('التجار', $kpi['merchants'], n($kpi['pending']) . ' بانتظار الموافقة', 'blue');
    kpi_card('اشتراكات فعّالة', $kpi['active_subs'], 'active / trial', 'green');
    kpi_card('بلاغات مفتوحة', $kpi['reports_open'], 'تحتاج مراجعة', 'red');
    kpi_card('زيارات (الفترة)', $kpi['visits'], '');
    kpi_card('نقاط مُمنوحة', $kpi['points'], 'إجمالي');
  ?>
</div>

<div class="grid md:grid-cols-3 gap-6">
  <!-- مخطط التسجيلات -->
  <div class="bg-white rounded-xl border p-5 md:col-span-2">
    <div class="font-bold mb-4">المستخدمون الجدد (آخر <?= $days ?> يوم)</div>
    <div class="flex items-end gap-1 h-40">
      <?php foreach ($signups as $s): $h = round((int)$s['c'] / $maxC * 100); ?>
        <div class="flex-1 flex flex-col items-center justify-end group" title="<?= e($s['d'].': '.$s['c']) ?>">
          <div class="w-full bg-amber-400 group-hover:bg-amber-500 rounded-t" style="height:<?= max(4,$h) ?>%"></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$signups): ?><div class="text-gray-400 text-sm">لا تسجيلات في الفترة.</div><?php endif; ?>
    </div>
  </div>

  <!-- التجار حسب الحالة -->
  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-4">التجار حسب الحالة</div>
    <?php foreach (['pending'=>'بانتظار','approved'=>'معتمد','suspended'=>'موقوف','rejected'=>'مرفوض'] as $st=>$lbl): ?>
      <div class="flex items-center justify-between py-2 border-b last:border-0">
        <span><?= status_badge($st) ?> <span class="text-sm text-gray-600"><?= e($lbl) ?></span></span>
        <span class="font-bold"><?= n($byStatus[$st] ?? 0) ?></span>
      </div>
    <?php endforeach; ?>
    <a href="merchants.php?status=pending" class="block mt-3 text-center text-sm text-amber-600 font-bold">مراجعة طلبات الموافقة ←</a>
  </div>
</div>

<!-- أحدث المستخدمين -->
<div class="bg-white rounded-xl border mt-6">
  <div class="px-5 py-3 border-b font-bold flex items-center justify-between">
    <span>أحدث المستخدمين</span>
    <a href="users.php" class="text-sm text-amber-600">عرض الكل ←</a>
  </div>
  <table class="w-full text-sm">
    <thead class="text-gray-500 text-right"><tr>
      <th class="px-5 py-2 font-medium">الاسم</th><th class="px-5 py-2 font-medium">الجوال</th>
      <th class="px-5 py-2 font-medium">البريد</th><th class="px-5 py-2 font-medium">تاريخ الانضمام</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($recent as $u): ?>
      <tr class="border-t hover:bg-gray-50">
        <td class="px-5 py-2.5 font-bold"><?= e($u['name']) ?></td>
        <td class="px-5 py-2.5"><?= e($u['phone']) ?></td>
        <td class="px-5 py-2.5 text-gray-500"><?= e($u['email'] ?: '—') ?></td>
        <td class="px-5 py-2.5 text-gray-500"><?= dt($u['created_at']) ?></td>
        <td class="px-5 py-2.5"><?php if (can('users')): ?><a href="user_view.php?id=<?= e($u['id']) ?>" class="text-amber-600">عرض</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
