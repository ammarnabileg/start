<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('merchants', 'view');

$id = (string) get('id');
$m  = one("select * from public.merchants where id=:id", ['id' => $id]);
if (!$m) { http_response_code(404); exit('التاجر غير موجود'); }

if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  if ($act === 'save') {
    require_perm('merchants', 'edit');
    q("update public.merchants set business_name=:bn, business_type=:bt, phone=:ph, email=:em, address=:ad where id=:id", [
      'bn' => trim((string)post('business_name')), 'bt' => trim((string)post('business_type')),
      'ph' => trim((string)post('phone')), 'em' => trim((string)post('email')),
      'ad' => trim((string)post('address')), 'id' => $id,
    ]);
    audit('update', 'merchant', $id);
    flash('تم حفظ بيانات التاجر.');
  } elseif ($act === 'sub') {
    require_perm('merchants', 'edit');
    $plan = (string)post('plan'); $st = (string)post('sub_status');
    if (scalar("select 1 from public.subscriptions where merchant_id=:id", ['id'=>$id])) {
      q("update public.subscriptions set plan=:p, status=:s where merchant_id=:id", ['p'=>$plan,'s'=>$st,'id'=>$id]);
    } else {
      q("insert into public.subscriptions (merchant_id, plan, status) values (:id,:p,:s)", ['id'=>$id,'p'=>$plan,'s'=>$st]);
    }
    audit('update', 'subscription', $id, ['plan'=>$plan,'status'=>$st]);
    flash('تم تحديث الاشتراك.');
  }
  redirect('merchant_view.php?id=' . urlencode($id));
}

$branches = all("select * from public.branches where merchant_id=:id order by created_at", ['id'=>$id]);
$staff    = all("select * from public.merchant_staff where merchant_id=:id order by created_at", ['id'=>$id]);
$sub      = one("select * from public.subscriptions where merchant_id=:id order by created_at desc limit 1", ['id'=>$id]);
$customers= (int) scalar("select count(*) from public.user_stores where merchant_id=:id", ['id'=>$id]);
$canEdit  = can('merchants', 'edit');

$title = 'تاجر: ' . $m['business_name'];
require __DIR__ . '/partials/header.php';
?>
<a href="merchants.php" class="text-sm text-amber-600">← رجوع للقائمة</a>
<div class="flex items-center gap-3 mt-2 mb-5">
  <h2 class="text-2xl font-extrabold"><?= e($m['business_name']) ?></h2>
  <?= status_badge($m['status']) ?>
</div>

<div class="grid md:grid-cols-3 gap-6">
  <!-- بيانات -->
  <div class="md:col-span-2 bg-white rounded-xl border p-5">
    <div class="font-bold mb-4">بيانات النشاط</div>
    <form method="post" class="grid grid-cols-2 gap-4">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <?php
        $f = fn($name,$lbl,$val)=>'<label class="block"><span class="text-xs text-gray-500">'.e($lbl).'</span>'
          .'<input name="'.$name.'" value="'.e($val).'" '.($canEdit?'':'disabled').' class="mt-1 w-full border rounded-lg px-3 py-2"></label>';
        echo $f('business_name','اسم النشاط',$m['business_name']);
        echo $f('business_type','نوع النشاط',$m['business_type']);
        echo $f('phone','الجوال',$m['phone']);
        echo $f('email','البريد',$m['email']);
      ?>
      <label class="block col-span-2"><span class="text-xs text-gray-500">العنوان</span>
        <input name="address" value="<?= e($m['address']) ?>" <?= $canEdit?'':'disabled' ?> class="mt-1 w-full border rounded-lg px-3 py-2"></label>
      <?php if ($canEdit): ?>
        <div class="col-span-2"><button class="bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg px-6 py-2">حفظ</button></div>
      <?php endif; ?>
    </form>
  </div>

  <!-- ملخّص + اشتراك -->
  <div class="space-y-6">
    <div class="bg-white rounded-xl border p-5">
      <div class="font-bold mb-3">ملخّص</div>
      <div class="flex justify-between py-1 text-sm"><span class="text-gray-500">العملاء</span><b><?= n($customers) ?></b></div>
      <div class="flex justify-between py-1 text-sm"><span class="text-gray-500">الفروع</span><b><?= n(count($branches)) ?></b></div>
      <div class="flex justify-between py-1 text-sm"><span class="text-gray-500">الموظفون</span><b><?= n(count($staff)) ?></b></div>
      <div class="flex justify-between py-1 text-sm"><span class="text-gray-500">انضمّ</span><b><?= d($m['created_at']) ?></b></div>
    </div>
    <div class="bg-white rounded-xl border p-5">
      <div class="font-bold mb-3">الاشتراك</div>
      <form method="post" class="space-y-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="sub">
        <select name="plan" <?= $canEdit?'':'disabled' ?> class="w-full border rounded-lg px-3 py-2">
          <?php foreach (['trial'=>'تجربة','monthly'=>'شهري','yearly'=>'سنوي'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($sub['plan']??'')===$k?'selected':'' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="sub_status" <?= $canEdit?'':'disabled' ?> class="w-full border rounded-lg px-3 py-2">
          <?php foreach (['trial','active','past_due','canceled','expired'] as $k): ?>
            <option value="<?= $k ?>" <?= ($sub['status']??'')===$k?'selected':'' ?>><?= e($k) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($canEdit): ?><button class="w-full bg-gray-800 text-white font-bold rounded-lg py-2">تحديث الاشتراك</button><?php endif; ?>
      </form>
    </div>
  </div>
</div>

<!-- الفروع -->
<div class="bg-white rounded-xl border mt-6">
  <div class="px-5 py-3 border-b font-bold">الفروع (<?= count($branches) ?>)</div>
  <table class="w-full text-sm"><tbody>
    <?php foreach ($branches as $b): ?>
      <tr class="border-t"><td class="px-5 py-2.5 font-bold"><?= e($b['name']) ?></td>
        <td class="px-5 py-2.5 text-gray-500"><?= e($b['address'] ?: '—') ?></td>
        <td class="px-5 py-2.5"><?= $b['active']?status_badge('active'):badge('موقوف','gray') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$branches): ?><tr><td class="px-5 py-4 text-gray-400">لا فروع.</td></tr><?php endif; ?>
  </tbody></table>
</div>

<!-- الموظفون -->
<div class="bg-white rounded-xl border mt-6">
  <div class="px-5 py-3 border-b font-bold">الموظفون (<?= count($staff) ?>)</div>
  <table class="w-full text-sm"><tbody>
    <?php foreach ($staff as $s): ?>
      <tr class="border-t"><td class="px-5 py-2.5 font-bold"><?= e($s['name']) ?></td>
        <td class="px-5 py-2.5 text-gray-500"><?= e($s['phone'] ?: '—') ?></td>
        <td class="px-5 py-2.5"><?= badge($s['role'],'blue') ?></td>
        <td class="px-5 py-2.5"><?= $s['status']==='active'?status_badge('active'):badge('موقوف','gray') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$staff): ?><tr><td class="px-5 py-4 text-gray-400">لا موظفين.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
