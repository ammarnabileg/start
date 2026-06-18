<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('merchants', 'view');

// ---- إجراءات ----
if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  $id  = (string) post('id');
  $m   = one("select * from public.merchants where id=:id", ['id' => $id]);
  if (!$m) { flash('التاجر غير موجود.', 'error'); redirect('merchants.php'); }

  if (in_array($act, ['approve', 'suspend', 'reject', 'reactivate'], true)) {
    require_perm('merchants', 'approve');
    $map = ['approve'=>'approved','suspend'=>'suspended','reject'=>'rejected','reactivate'=>'approved'];
    $new = $map[$act];
    q("update public.merchants set status=:s, approved_at=case when :s2='approved' then coalesce(approved_at,now()) else approved_at end where id=:id",
      ['s' => $new, 's2' => $new, 'id' => $id]);
    // عند الاعتماد لأول مرة: أنشئ اشتراك تجربة إن لم يوجد
    if ($act === 'approve' && !scalar("select 1 from public.subscriptions where merchant_id=:id", ['id'=>$id])) {
      q("insert into public.subscriptions (merchant_id, plan, status, trial_ends_at)
         values (:id,'trial','trial', now()+interval '14 days')", ['id' => $id]);
    }
    audit($act, 'merchant', $id, ['business' => $m['business_name']]);
    flash('تم تحديث حالة التاجر إلى: ' . $new);
  } elseif ($act === 'delete') {
    require_perm('merchants', 'delete');
    q("delete from public.merchants where id=:id", ['id' => $id]);
    audit('delete', 'merchant', $id, ['business' => $m['business_name']]);
    flash('تم حذف التاجر وكل بياناته المرتبطة.');
  }
  redirect('merchants.php?' . http_build_query(array_filter(['status'=>get('status'),'q'=>get('q')])));
}

// ---- استعلام القائمة ----
$status = (string) get('status', '');
$qstr   = trim((string) get('q', ''));
$where  = []; $params = [];
if ($status !== '' && in_array($status, ['pending','approved','suspended','rejected'], true)) {
  $where[] = 'm.status = :st'; $params['st'] = $status;
}
if ($qstr !== '') {
  $where[] = '(m.business_name ilike :q or m.phone ilike :q or m.email ilike :q)';
  $params['q'] = '%' . $qstr . '%';
}
$wsql  = $where ? ('where ' . implode(' and ', $where)) : '';
$total = (int) scalar("select count(*) from public.merchants m $wsql", $params);
$page  = page_num(); $off = ($page - 1) * per_page();

$rows = all("select m.*,
   (select count(*) from public.branches b where b.merchant_id=m.id) branches,
   (select count(*) from public.user_stores us where us.merchant_id=m.id) customers,
   (select status from public.subscriptions s where s.merchant_id=m.id order by created_at desc limit 1) sub_status
   from public.merchants m $wsql order by m.created_at desc limit " . per_page() . " offset $off", $params);

$title = 'التجار (CRM)';
require __DIR__ . '/partials/header.php';
?>
<!-- فلاتر -->
<form class="flex flex-wrap gap-2 mb-4">
  <input name="q" value="<?= e($qstr) ?>" placeholder="بحث: اسم النشاط / جوال / بريد" class="border rounded-lg px-4 py-2 flex-1 min-w-[220px]">
  <select name="status" class="border rounded-lg px-4 py-2">
    <option value="">كل الحالات</option>
    <?php foreach (['pending'=>'بانتظار الموافقة','approved'=>'معتمد','suspended'=>'موقوف','rejected'=>'مرفوض'] as $k=>$v): ?>
      <option value="<?= $k ?>" <?= $status===$k?'selected':'' ?>><?= e($v) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="bg-gray-800 text-white rounded-lg px-5 py-2 font-bold">تصفية</button>
  <a href="export.php?type=merchants" class="bg-green-600 text-white rounded-lg px-5 py-2 font-bold flex items-center">⬇ CSV</a>
</form>

<div class="bg-white rounded-xl border overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 text-gray-500 text-right"><tr>
      <th class="px-4 py-3 font-medium">النشاط</th><th class="px-4 py-3 font-medium">تواصل</th>
      <th class="px-4 py-3 font-medium">الحالة</th><th class="px-4 py-3 font-medium">الاشتراك</th>
      <th class="px-4 py-3 font-medium">فروع/عملاء</th><th class="px-4 py-3 font-medium">انضمّ</th>
      <th class="px-4 py-3 font-medium">إجراءات</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $m): ?>
      <tr class="border-t hover:bg-gray-50">
        <td class="px-4 py-3">
          <div class="font-bold"><?= e($m['business_name']) ?></div>
          <div class="text-xs text-gray-400"><?= e($m['business_type'] ?: '—') ?></div>
        </td>
        <td class="px-4 py-3 text-gray-600"><?= e($m['phone'] ?: '—') ?><br><span class="text-xs text-gray-400"><?= e($m['email'] ?: '') ?></span></td>
        <td class="px-4 py-3"><?= status_badge($m['status']) ?></td>
        <td class="px-4 py-3"><?= $m['sub_status'] ? status_badge($m['sub_status']) : '<span class="text-gray-300">—</span>' ?></td>
        <td class="px-4 py-3 text-gray-600"><?= n($m['branches']) ?> / <?= n($m['customers']) ?></td>
        <td class="px-4 py-3 text-gray-500"><?= d($m['created_at']) ?></td>
        <td class="px-4 py-3">
          <div class="flex flex-wrap gap-1">
            <a href="merchant_view.php?id=<?= e($m['id']) ?>" class="px-2.5 py-1 rounded bg-gray-100 hover:bg-gray-200 text-xs">عرض</a>
            <?php if (can('merchants','approve')): ?>
              <?php if ($m['status']==='pending'): ?>
                <?= action_btn('approve',$m['id'],'اعتماد','bg-green-100 text-green-700') ?>
                <?= action_btn('reject',$m['id'],'رفض','bg-red-100 text-red-700') ?>
              <?php elseif ($m['status']==='approved'): ?>
                <?= action_btn('suspend',$m['id'],'إيقاف','bg-amber-100 text-amber-700') ?>
              <?php else: ?>
                <?= action_btn('reactivate',$m['id'],'تفعيل','bg-green-100 text-green-700') ?>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (can('merchants','delete')): ?>
              <?= action_btn('delete',$m['id'],'حذف','bg-red-100 text-red-700','تأكيد حذف التاجر نهائيًا؟') ?>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">لا نتائج.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?= pager($total, $page, http_build_query(array_filter(['status'=>$status,'q'=>$qstr]))) ?>
<?php
require __DIR__ . '/partials/footer.php';

function action_btn(string $act, string $id, string $label, string $cls, ?string $confirm = null): string {
  $c = $confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '';
  return '<form method="post" class="inline"' . $c . '>' . csrf_field()
    . '<input type="hidden" name="action" value="' . e($act) . '">'
    . '<input type="hidden" name="id" value="' . e($id) . '">'
    . '<button class="px-2.5 py-1 rounded text-xs font-bold ' . $cls . '">' . e($label) . '</button></form>';
}
