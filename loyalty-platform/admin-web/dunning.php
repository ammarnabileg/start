<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('finance', 'view');

function send_dunning(string $merchantId, string $subStatus, ?string $by): int {
  // إشعار داخل التطبيق لأصحاب/مديري المتجر (حسابات Auth)
  $owners = all("select user_id from public.merchant_staff
                 where merchant_id=:m and user_id is not null and role in ('merchant_owner','manager')", ['m'=>$merchantId]);
  $sent = 0;
  foreach ($owners as $o) {
    q("insert into public.notifications (user_id,type,title,body,data)
       values (:u,'subscription','تذكير باشتراكك','اشتراك متجرك يحتاج إلى تجديد لاستمرار الخدمة.', jsonb_build_object('source','admin'))",
      ['u'=>$o['user_id']]);
    $sent++;
  }
  q("insert into admin.dunning_log (merchant_id, sub_status, channel, sent_by) values (:m,:s,'in_app',:b)",
    ['m'=>$merchantId,'s'=>$subStatus,'b'=>$by]);
  return $sent;
}

if (is_post()) {
  csrf_check();
  require_perm('finance', 'edit');
  $act = (string) post('action');
  if ($act === 'remind') {
    $n = send_dunning((string)post('merchant_id'), (string)post('sub_status'), current_admin()['id']);
    audit('dunning', 'merchant', (string)post('merchant_id'), ['notified'=>$n]);
    flash("تم إرسال تذكير إلى $n مسؤول بالمتجر.");
  } elseif ($act === 'remind_all') {
    $rows = all("select s.merchant_id, s.status from public.subscriptions s where s.status in ('past_due','expired')");
    $tot = 0; foreach ($rows as $r) $tot += send_dunning($r['merchant_id'], $r['status'], current_admin()['id']);
    audit('dunning_all', 'subscriptions', null, ['merchants'=>count($rows),'notified'=>$tot]);
    flash('تم إرسال تذكيرات لكل المتأخّرين (' . count($rows) . ' متجر).');
  }
  redirect('dunning.php');
}

$rows = all("select s.*, m.business_name,
   greatest(0, extract(day from now() - s.current_period_end)::int) overdue,
   (select max(created_at) from admin.dunning_log d where d.merchant_id=s.merchant_id) last_reminder
   from public.subscriptions s join public.merchants m on m.id=s.merchant_id
   where s.status in ('past_due','expired')
   order by s.current_period_end nulls last");

$title = 'استرجاع الإيرادات (Dunning)';
require __DIR__ . '/partials/header.php';
?>
<div class="flex items-center justify-between mb-4">
  <p class="text-gray-500 text-sm">اشتراكات متأخّرة/منتهية تحتاج تذكير تجديد. (التذكير التلقائي عبر <code>cron_dunning.php</code>.)</p>
  <?php if (can('finance','edit') && $rows): ?>
    <form method="post" onsubmit="return confirm('إرسال تذكير لكل المتأخّرين؟')"><?= csrf_field() ?><input type="hidden" name="action" value="remind_all">
      <button class="bg-amber-500 hover:bg-amber-600 text-white rounded-lg px-5 py-2 font-bold text-sm">تذكير الكل</button></form>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl border overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-gray-500 text-right"><tr>
      <th class="px-4 py-3 font-medium">المتجر</th><th class="px-4 py-3 font-medium">الحالة</th>
      <th class="px-4 py-3 font-medium">متأخّر (يوم)</th><th class="px-4 py-3 font-medium">الدفع</th>
      <th class="px-4 py-3 font-medium">آخر تذكير</th><th class="px-4 py-3 font-medium"></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr class="border-t hover:bg-gray-50">
        <td class="px-4 py-3 font-bold"><a href="merchant_view.php?id=<?= e($s['merchant_id']) ?>" class="hover:text-amber-600"><?= e($s['business_name']) ?></a></td>
        <td class="px-4 py-3"><?= status_badge($s['status']) ?></td>
        <td class="px-4 py-3 font-bold text-red-600"><?= n($s['overdue']) ?></td>
        <td class="px-4 py-3"><?= $s['gateway_ref'] ? badge('مربوط','green') : badge('غير مربوط','gray') ?></td>
        <td class="px-4 py-3 text-gray-500"><?= dt($s['last_reminder']) ?></td>
        <td class="px-4 py-3">
          <?php if (can('finance','edit')): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="remind">
            <input type="hidden" name="merchant_id" value="<?= e($s['merchant_id']) ?>"><input type="hidden" name="sub_status" value="<?= e($s['status']) ?>">
            <button class="bg-blue-100 text-blue-700 rounded px-3 py-1 text-xs font-bold">إرسال تذكير</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">لا اشتراكات متأخّرة 🎉</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
