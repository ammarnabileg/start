<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('analytics', 'view');

// الاحتفاظ (آخر ٩٠ يوم): نسبة العائدين (≥٢ زيارة) من أصل من زار مرة على الأقل.
$visitors  = (int) scalar("select count(distinct user_id) from public.user_visits where visit_date >= current_date-90");
$returners = (int) scalar("select count(*) from (select user_id from public.user_visits where visit_date >= current_date-90 group by user_id having count(*)>=2) t");
$retention = $visitors ? round($returners / $visitors * 100, 1) : 0;
$avgPoints = (int) scalar("select coalesce(round(avg(p)),0) from (select sum(available_points) p from public.user_stores group by user_id) t");

$topMerchants = all("select m.business_name, count(*) c from public.user_stores us join public.merchants m on m.id=us.merchant_id group by m.business_name order by c desc limit 8");
$maxM = max(1, ...array_map(fn($r)=>(int)$r['c'], $topMerchants ?: [['c'=>1]]));

$levels = all("select coalesce(l.name,'بدون مستوى') name, count(*) c from public.user_stores us
  left join public.loyalty_levels l on l.id=us.current_level_id group by 1 order by c desc limit 10");
$maxL = max(1, ...array_map(fn($r)=>(int)$r['c'], $levels ?: [['c'=>1]]));

$pts = all("select to_char(created_at::date,'MM-DD') d, sum(points) c from public.points_transactions
  where type='earn' and created_at >= now()-interval '30 days' group by 1 order by 1");
$maxP = max(1, ...array_map(fn($r)=>(int)$r['c'], $pts ?: [['c'=>1]]));

$title = 'التحليلات';
require __DIR__ . '/partials/header.php';
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">معدّل الاحتفاظ (٩٠ يوم)</div><div class="text-3xl font-extrabold text-green-600 mt-1"><?= e($retention) ?>%</div><div class="text-xs text-gray-400"><?= n($returners) ?> عائد / <?= n($visitors) ?> زائر</div></div>
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">متوسّط النقاط/مستخدم</div><div class="text-3xl font-extrabold mt-1"><?= n($avgPoints) ?></div></div>
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">إجمالي الزيارات (٩٠ يوم)</div><div class="text-3xl font-extrabold mt-1"><?= n((int)scalar("select count(*) from public.user_visits where visit_date>=current_date-90")) ?></div></div>
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">استبدالات (٣٠ يوم)</div><div class="text-3xl font-extrabold text-amber-600 mt-1"><?= n((int)scalar("select count(*) from public.reward_redemptions where created_at>=now()-interval '30 days'")) ?></div></div>
</div>

<div class="grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-4">أكثر المتاجر عملاءً</div>
    <?php foreach ($topMerchants as $m): ?>
      <div class="mb-2"><div class="flex justify-between text-sm mb-1"><span class="font-bold"><?= e($m['business_name']) ?></span><span class="text-gray-500"><?= n($m['c']) ?></span></div>
        <div class="bg-gray-100 rounded h-2"><div class="bg-amber-400 h-2 rounded" style="width:<?= round($m['c']/$maxM*100) ?>%"></div></div></div>
    <?php endforeach; ?>
    <?php if(!$topMerchants): ?><div class="text-gray-400 text-sm">لا بيانات.</div><?php endif; ?>
  </div>

  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-4">توزيع المستويات</div>
    <?php foreach ($levels as $l): ?>
      <div class="mb-2"><div class="flex justify-between text-sm mb-1"><span class="font-bold"><?= e($l['name']) ?></span><span class="text-gray-500"><?= n($l['c']) ?></span></div>
        <div class="bg-gray-100 rounded h-2"><div class="bg-green-400 h-2 rounded" style="width:<?= round($l['c']/$maxL*100) ?>%"></div></div></div>
    <?php endforeach; ?>
    <?php if(!$levels): ?><div class="text-gray-400 text-sm">لا بيانات.</div><?php endif; ?>
  </div>

  <div class="bg-white rounded-xl border p-5 md:col-span-2">
    <div class="font-bold mb-4">النقاط المُمنوحة يوميًا (٣٠ يوم)</div>
    <div class="flex items-end gap-1 h-40">
      <?php foreach ($pts as $p): ?>
        <div class="flex-1 flex flex-col items-center justify-end group" title="<?= e($p['d'].': '.$p['c']) ?>">
          <div class="w-full bg-amber-400 group-hover:bg-amber-500 rounded-t" style="height:<?= max(4,round($p['c']/$maxP*100)) ?>%"></div></div>
      <?php endforeach; ?>
      <?php if(!$pts): ?><div class="text-gray-400 text-sm">لا بيانات.</div><?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
