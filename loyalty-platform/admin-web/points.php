<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('points', 'view');

$merchants = all("select id, business_name from public.merchants where status='approved' order by business_name");
$lists     = all("select * from admin.user_lists order by name");
$preUser   = (string) get('user', '');
$preRow    = $preUser ? one("select id,name,phone from public.users where id=:id", ['id'=>$preUser]) : null;
$qstr      = trim((string) get('q', ''));
$found     = $qstr !== '' ? all("select id,name,phone from public.users where name ilike :q or phone ilike :q or email ilike :q limit 10", ['q'=>'%'.$qstr.'%']) : [];

if (is_post()) {
  csrf_check();
  require_perm('points', 'create');
  $merchant = (string) post('merchant');
  $dir      = post('direction') === 'deduct' ? -1 : 1;
  $amount   = max(0, (int) post('amount'));
  $reason   = trim((string) post('reason'));
  $target   = (string) post('target');

  if (!$merchant || $amount <= 0 || $reason === '') { flash('اختر المتجر وأدخل مبلغًا وسببًا.', 'error'); redirect('points.php'); }

  $uids = [];
  if ($target === 'user') {
    $uid = (string) post('user_id');
    if ($uid) $uids = [$uid];
  } elseif ($target === 'list') {
    $list = one("select * from admin.user_lists where id=:id", ['id'=>(string)post('list_id')]);
    if ($list) $uids = list_user_ids($list);
  }
  if (!$uids) { flash('لا مستخدمين مستهدفين.', 'error'); redirect('points.php'); }

  $applied = (int) scalar("select admin.bulk_adjust_points(:m, :ids::uuid[], :ad, :ld, :r)", [
    'm'  => $merchant, 'ids' => uuid_array($uids),
    'ad' => $dir * $amount, 'ld' => $dir > 0 ? $amount : 0, 'r' => $reason,
  ]);
  audit('points_adjust', 'points', null,
    ['merchant'=>$merchant, 'delta'=>$dir*$amount, 'targets'=>count($uids), 'applied'=>$applied, 'reason'=>$reason]);
  flash(($dir > 0 ? 'تم منح ' : 'تم خصم ') . n($amount) . ' نقطة لـ ' . n($applied) . ' مستخدم.');
  redirect('points.php');
}

$title = 'منح / خصم النقاط';
require __DIR__ . '/partials/header.php';
?>
<div class="grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-4">تعديل نقاط</div>
    <?php if (!can('points','create')): ?><div class="text-gray-400">لديك صلاحية العرض فقط.</div><?php else: ?>
    <form method="post" class="space-y-4"><?= csrf_field() ?>
      <div>
        <label class="block text-sm text-gray-500 mb-1">المستهدف</label>
        <div class="flex gap-4">
          <label class="inline-flex items-center gap-2"><input type="radio" name="target" value="user" <?= $preRow?'checked':'' ?> onclick="u.style.display='block';l.style.display='none'"> مستخدم واحد</label>
          <label class="inline-flex items-center gap-2"><input type="radio" name="target" value="list" <?= $preRow?'':'checked' ?> onclick="u.style.display='none';l.style.display='block'"> قائمة</label>
        </div>
      </div>
      <div id="u" style="display:<?= $preRow?'block':'none' ?>">
        <?php if ($preRow): ?>
          <input type="hidden" name="user_id" value="<?= e($preRow['id']) ?>">
          <div class="px-3 py-2 bg-gray-50 rounded-lg border">المستخدم: <b><?= e($preRow['name']) ?></b> · <?= e($preRow['phone']) ?></div>
        <?php else: ?>
          <input name="user_id" placeholder="معرّف المستخدم (UUID) — أو ابحث يسارًا" class="w-full border rounded-lg px-3 py-2">
        <?php endif; ?>
      </div>
      <div id="l" style="display:<?= $preRow?'none':'block' ?>">
        <select name="list_id" class="w-full border rounded-lg px-3 py-2">
          <?php foreach ($lists as $l): ?>
            <option value="<?= e($l['id']) ?>"><?= e($l['name']) ?> <?= $l['is_smart']?'(ذكية)':'' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <select name="merchant" required class="w-full border rounded-lg px-3 py-2">
        <option value="">— اختر المتجر (المحفظة) —</option>
        <?php foreach ($merchants as $m): ?><option value="<?= e($m['id']) ?>"><?= e($m['business_name']) ?></option><?php endforeach; ?>
      </select>
      <div class="flex gap-3">
        <select name="direction" class="border rounded-lg px-3 py-2">
          <option value="grant">منح (+)</option><option value="deduct">خصم (−)</option>
        </select>
        <input name="amount" type="number" min="1" placeholder="عدد النقاط" required class="flex-1 border rounded-lg px-3 py-2">
      </div>
      <input name="reason" placeholder="السبب (يُسجَّل في points_transactions)" required class="w-full border rounded-lg px-3 py-2">
      <button onclick="return confirm('تأكيد تعديل النقاط؟')" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg py-2.5">تطبيق</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-3">بحث عن مستخدم</div>
    <form class="flex gap-2 mb-3"><input name="q" value="<?= e($qstr) ?>" placeholder="اسم / جوال / بريد" class="flex-1 border rounded-lg px-3 py-2">
      <button class="bg-gray-800 text-white rounded-lg px-4 font-bold">بحث</button></form>
    <?php foreach ($found as $f): ?>
      <a href="points.php?user=<?= e($f['id']) ?>" class="flex justify-between py-2 border-b hover:bg-amber-50 px-2 rounded">
        <b><?= e($f['name']) ?></b><span class="text-gray-500 text-sm"><?= e($f['phone']) ?></span></a>
    <?php endforeach; ?>
    <p class="text-xs text-gray-400 mt-3">المنح ذرّي عبر <code>wallet_apply</code> ويُعيد حساب المستوى، ويُسجَّل كـ <code>adjust</code> بالسبب. الخصم يتخطّى من لا يملك رصيدًا كافيًا.</p>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
