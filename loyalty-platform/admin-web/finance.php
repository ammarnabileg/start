<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('finance', 'view');

$prices = setting_get('plan_prices', ['trial'=>0,'monthly'=>99,'yearly'=>990,'currency'=>'SAR']);

if (is_post()) {
  csrf_check();
  require_perm('finance', 'edit');
  setting_set('plan_prices', [
    'trial'    => 0,
    'monthly'  => max(0, (int)post('monthly')),
    'yearly'   => max(0, (int)post('yearly')),
    'currency' => trim((string)post('currency')) ?: 'SAR',
  ]);
  audit('update', 'plan_prices'); flash('تم حفظ الأسعار.'); redirect('finance.php');
}

$cur = $prices['currency'] ?? 'SAR';
$byPlan = [];
foreach (all("select plan, count(*) c from public.subscriptions where status='active' group by plan") as $r) $byPlan[$r['plan']] = (int)$r['c'];
$activeMonthly = $byPlan['monthly'] ?? 0;
$activeYearly  = $byPlan['yearly'] ?? 0;
$mrr = $activeMonthly * (int)$prices['monthly'] + $activeYearly * (int)$prices['yearly'] / 12;
$arr = $mrr * 12;

$statusCounts = [];
foreach (all("select status, count(*) c from public.subscriptions group by status") as $r) $statusCounts[$r['status']] = (int)$r['c'];

$trialsEnding = all("select s.*, m.business_name from public.subscriptions s join public.merchants m on m.id=s.merchant_id
  where s.status='trial' and s.trial_ends_at is not null and s.trial_ends_at between now() and now()+interval '7 days'
  order by s.trial_ends_at");
$renewals = all("select s.*, m.business_name from public.subscriptions s join public.merchants m on m.id=s.merchant_id
  where s.status='active' and s.current_period_end is not null and s.current_period_end between now() and now()+interval '30 days'
  order by s.current_period_end");

$title = 'المالية والاشتراكات';
require __DIR__ . '/partials/header.php';
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">إيراد شهري متوقّع (MRR)</div><div class="text-3xl font-extrabold text-green-600 mt-1"><?= n(round($mrr)) ?></div><div class="text-xs text-gray-400"><?= e($cur) ?></div></div>
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">إيراد سنوي متوقّع (ARR)</div><div class="text-3xl font-extrabold mt-1"><?= n(round($arr)) ?></div><div class="text-xs text-gray-400"><?= e($cur) ?></div></div>
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">اشتراكات مدفوعة</div><div class="text-3xl font-extrabold text-amber-600 mt-1"><?= n($activeMonthly+$activeYearly) ?></div><div class="text-xs text-gray-400"><?= n($activeMonthly) ?> شهري · <?= n($activeYearly) ?> سنوي</div></div>
  <div class="bg-white rounded-xl border p-5"><div class="text-gray-500 text-sm">تجارب نشطة</div><div class="text-3xl font-extrabold text-blue-600 mt-1"><?= n($statusCounts['trial'] ?? 0) ?></div><div class="text-xs text-gray-400"><?= n(count($trialsEnding)) ?> تنتهي خلال ٧ أيام</div></div>
</div>

<div class="grid md:grid-cols-3 gap-6">
  <div class="md:col-span-2 space-y-6">
    <div class="bg-white rounded-xl border">
      <div class="px-5 py-3 border-b font-bold">تجارب على وشك الانتهاء (٧ أيام)</div>
      <table class="w-full text-sm"><tbody>
        <?php foreach ($trialsEnding as $s): ?>
          <tr class="border-t"><td class="px-5 py-2.5 font-bold"><a href="merchant_view.php?id=<?= e($s['merchant_id']) ?>" class="hover:text-amber-600"><?= e($s['business_name']) ?></a></td>
            <td class="px-5 py-2.5 text-gray-500">تنتهي: <?= d($s['trial_ends_at']) ?></td><td class="px-5 py-2.5"><?= status_badge('trial') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$trialsEnding): ?><tr><td class="px-5 py-4 text-gray-400">لا تجارب تنتهي قريبًا.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="bg-white rounded-xl border">
      <div class="px-5 py-3 border-b font-bold">تجديدات قادمة (٣٠ يوم)</div>
      <table class="w-full text-sm"><tbody>
        <?php foreach ($renewals as $s): ?>
          <tr class="border-t"><td class="px-5 py-2.5 font-bold"><a href="merchant_view.php?id=<?= e($s['merchant_id']) ?>" class="hover:text-amber-600"><?= e($s['business_name']) ?></a></td>
            <td class="px-5 py-2.5 text-gray-500"><?= e($s['plan']) ?> · تجديد <?= d($s['current_period_end']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$renewals): ?><tr><td class="px-5 py-4 text-gray-400">لا تجديدات قريبة.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>

  <div class="space-y-6">
    <div class="bg-white rounded-xl border p-5">
      <div class="font-bold mb-3">الاشتراكات حسب الحالة</div>
      <?php foreach (['active'=>'فعّال','trial'=>'تجربة','past_due'=>'متأخّر','expired'=>'منتهٍ','canceled'=>'ملغى'] as $k=>$v): ?>
        <div class="flex justify-between py-1.5 border-b last:border-0 text-sm"><span><?= status_badge($k) ?> <?= e($v) ?></span><b><?= n($statusCounts[$k]??0) ?></b></div>
      <?php endforeach; ?>
    </div>
    <div class="bg-white rounded-xl border p-5">
      <div class="font-bold mb-3">أسعار الباقات</div>
      <form method="post" class="space-y-3"><?= csrf_field() ?>
        <label class="block text-sm">شهري<input name="monthly" type="number" min="0" value="<?= e($prices['monthly']) ?>" <?= can('finance','edit')?'':'disabled' ?> class="mt-1 w-full border rounded-lg px-3 py-2"></label>
        <label class="block text-sm">سنوي<input name="yearly" type="number" min="0" value="<?= e($prices['yearly']) ?>" <?= can('finance','edit')?'':'disabled' ?> class="mt-1 w-full border rounded-lg px-3 py-2"></label>
        <label class="block text-sm">العملة<input name="currency" value="<?= e($cur) ?>" <?= can('finance','edit')?'':'disabled' ?> class="mt-1 w-full border rounded-lg px-3 py-2"></label>
        <?php if (can('finance','edit')): ?><button class="w-full bg-gray-800 text-white font-bold rounded-lg py-2">حفظ الأسعار</button><?php endif; ?>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
