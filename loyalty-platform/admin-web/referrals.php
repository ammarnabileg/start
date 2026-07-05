<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('merchants', 'view');

$merchant = (string) get('merchant', '');
$where = []; $params = [];
if ($merchant !== '') { $where[] = 'r.merchant_id = :m'; $params['m'] = $merchant; }
$wsql = $where ? ('where ' . implode(' and ', $where)) : '';

$total = (int) scalar("select count(*) from public.merchant_referrals r $wsql", $params);
$page = page_num(); $off = ($page - 1) * per_page();
$rows = all("select r.counted_at, m.business_name,
    ru.name referrer_name, ru.phone referrer_phone,
    eu.name referee_name, eu.phone referee_phone
  from public.merchant_referrals r
  left join public.merchants m on m.id = r.merchant_id
  left join public.users ru on ru.id = r.referrer_id
  left join public.users eu on eu.id = r.referee_id
  $wsql order by r.counted_at desc limit " . per_page() . " offset $off", $params);

$programs = all("select rp.merchant_id, rp.enabled, rp.referee_reward_points,
    jsonb_array_length(rp.milestones) ms, m.business_name,
    (select count(*) from public.merchant_referrals x where x.merchant_id=rp.merchant_id) refs
  from public.referral_programs rp left join public.merchants m on m.id=rp.merchant_id
  order by rp.enabled desc, m.business_name");
$merchants = all("select id, business_name from public.merchants order by business_name");

$title = 'الإحالات';
require __DIR__ . '/partials/header.php';
?>
<h2 class="text-xl font-extrabold mb-3">الإحالات — البرامج والتحويلات</h2>

<div class="bg-white rounded-xl border p-4 mb-4">
  <div class="font-bold mb-2 text-gray-700">برامج الإحالة لدى التجار</div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead><tr class="text-gray-400 text-right">
        <th class="py-1">المتجر</th><th>الحالة</th><th>المراحل</th><th>ترحيب</th><th>إحالات محتسبة</th>
      </tr></thead>
      <tbody>
      <?php foreach ($programs as $p): ?>
        <tr class="border-t">
          <td class="py-1.5"><b><?= e($p['business_name']) ?></b></td>
          <td><?= $p['enabled'] ? '<span class="text-emerald-600 font-bold">مفعّل</span>' : '<span class="text-gray-400">متوقّف</span>' ?></td>
          <td><?= n($p['ms']) ?></td>
          <td><?= n($p['referee_reward_points']) ?> نقطة</td>
          <td><?= n($p['refs']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$programs): ?><tr><td colspan="5" class="text-gray-400 py-3 text-center">لا توجد برامج بعد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<form method="get" class="flex items-center gap-2 mb-3">
  <select name="merchant" onchange="this.form.submit()" class="border rounded-lg px-3 py-1.5 text-sm bg-white">
    <option value="">كل المتاجر</option>
    <?php foreach ($merchants as $mr): ?>
      <option value="<?= e($mr['id']) ?>" <?= $merchant === $mr['id'] ? 'selected' : '' ?>><?= e($mr['business_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="text-sm text-gray-500 mr-auto"><?= n($total) ?> إحالة محتسبة</span>
</form>

<div class="space-y-2">
  <?php foreach ($rows as $r): ?>
    <div class="bg-white rounded-xl border p-3 flex items-center justify-between gap-3">
      <div class="text-sm">
        <b><?= e($r['referrer_name'] ?: 'مستخدم') ?></b> <span class="text-gray-400"><?= e($r['referrer_phone']) ?></span>
        <span class="text-gray-400 mx-1">←</span>
        <b><?= e($r['referee_name'] ?: 'مستخدم') ?></b> <span class="text-gray-400"><?= e($r['referee_phone']) ?></span>
        <?php if ($r['business_name']): ?><span class="text-emerald-700">· <?= e($r['business_name']) ?></span><?php endif; ?>
      </div>
      <span class="text-[11px] text-gray-400 whitespace-nowrap"><?= dt($r['counted_at']) ?></span>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?><div class="bg-white rounded-xl border p-10 text-center text-gray-400">لا توجد إحالات محتسبة.</div><?php endif; ?>
</div>
<?= pager($total, $page, http_build_query(array_filter(['merchant' => $merchant]))) ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
