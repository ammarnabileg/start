<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('users', 'view');

$id = (string) get('id');
$u  = one("select * from public.users where id=:id", ['id'=>$id]);
if (!$u) { http_response_code(404); exit('المستخدم غير موجود'); }

if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  if ($act === 'save') {
    require_perm('users', 'edit');
    // الخصوصية (leaderboard_opt_in / share_profile_with_merchants) يتحكّم بها
    // العميل وحده — لا يجوز للإدارة إلغاء إخفاءه. لا نلمس هذين العمودين هنا.
    q("update public.users set name=:n, email=:e where id=:id", [
      'n'=>trim((string)post('name')), 'e'=>trim((string)post('email')) ?: null,
      'id'=>$id,
    ]);
    audit('update','user',$id); flash('تم حفظ بيانات المستخدم.');
  } elseif ($act === 'delete') {
    require_perm('users','delete');
    q("delete from public.users where id=:id", ['id'=>$id]);
    audit('delete','user',$id); flash('تم حذف المستخدم.'); redirect('users.php');
  } elseif ($act === 'add_list') {
    require_perm('lists','edit');
    q("insert into admin.list_members (list_id,user_id) values (:l,:u) on conflict do nothing", ['l'=>(string)post('list_id'),'u'=>$id]);
    audit('add_to_list','user',$id); flash('تمت الإضافة للقائمة.');
  } elseif ($act === 'remove_list') {
    require_perm('lists','edit');
    q("delete from admin.list_members where list_id=:l and user_id=:u", ['l'=>(string)post('list_id'),'u'=>$id]);
    flash('تمت الإزالة من القائمة.');
  }
  redirect('user_view.php?id=' . urlencode($id));
}

$stores = all("select us.*, m.business_name from public.user_stores us
   join public.merchants m on m.id=us.merchant_id where us.user_id=:id order by us.first_linked_at desc", ['id'=>$id]);
$memberOf = all("select l.id,l.name from admin.list_members lm join admin.user_lists l on l.id=lm.list_id where lm.user_id=:id", ['id'=>$id]);
$allLists = all("select id,name from admin.user_lists order by name");
$visits = (int) scalar("select count(*) from public.user_visits where user_id=:id", ['id'=>$id]);
$canEdit = can('users','edit');

$title = 'مستخدم: ' . $u['name'];
require __DIR__ . '/partials/header.php';
?>
<a href="users.php" class="text-sm text-amber-600">← رجوع</a>
<div class="grid md:grid-cols-3 gap-6 mt-3">
  <div class="md:col-span-2 bg-white rounded-xl border p-5">
    <div class="flex items-center justify-between mb-4">
      <div class="font-bold">الملف الشخصي</div>
      <div class="flex gap-3 items-center">
      <?php if (can('points','create')): ?><a href="points.php?user=<?= e($id) ?>" class="text-amber-600 text-sm font-bold">⭐ منح/خصم نقاط</a><?php endif; ?>
      <?php if (can('users','delete')): ?>
      <form method="post" onsubmit="return confirm('حذف المستخدم نهائيًا؟')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="delete"><button class="text-red-600 text-sm font-bold">حذف المستخدم</button></form>
      <?php endif; ?>
      </div>
    </div>
    <form method="post" class="grid grid-cols-2 gap-4">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <label class="block"><span class="text-xs text-gray-500">الاسم</span>
        <input name="name" value="<?= e($u['name']) ?>" <?= $canEdit?'':'disabled' ?> class="mt-1 w-full border rounded-lg px-3 py-2"></label>
      <label class="block"><span class="text-xs text-gray-500">الجوال</span>
        <input value="<?= e($u['phone']) ?>" disabled class="mt-1 w-full border rounded-lg px-3 py-2 bg-gray-50"></label>
      <label class="block col-span-2"><span class="text-xs text-gray-500">البريد</span>
        <input name="email" value="<?= e($u['email']) ?>" <?= $canEdit?'':'disabled' ?> class="mt-1 w-full border rounded-lg px-3 py-2"></label>
      <label class="block col-span-2"><span class="text-xs text-gray-500">تاريخ الميلاد</span>
        <input value="<?= e($u['date_of_birth'] ?: '—') ?>" disabled class="mt-1 w-full border rounded-lg px-3 py-2 bg-gray-50"></label>
      <!-- خصوصية يتحكّم بها العميل (قراءة فقط للإدارة — لا يمكن إلغاء إخفائه). -->
      <div class="inline-flex items-center gap-2 text-sm"><span class="text-gray-500">الظهور في الصدارة:</span>
        <?= $u['leaderboard_opt_in']?badge('ظاهر','green'):badge('مخفي باختياره','gray') ?></div>
      <div class="inline-flex items-center gap-2 text-sm"><span class="text-gray-500">مشاركة البيانات مع المتاجر:</span>
        <?= $u['share_profile_with_merchants']?badge('يشارك','green'):badge('مخفي باختياره','gray') ?></div>
      <?php if ($canEdit): ?><div class="col-span-2"><button class="bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg px-6 py-2">حفظ</button></div><?php endif; ?>
    </form>
  </div>
  <div class="space-y-6">
    <div class="bg-white rounded-xl border p-5">
      <div class="font-bold mb-3">إحصاءات</div>
      <div class="flex justify-between py-1 text-sm"><span class="text-gray-500">المتاجر</span><b><?= n(count($stores)) ?></b></div>
      <div class="flex justify-between py-1 text-sm"><span class="text-gray-500">الزيارات</span><b><?= n($visits) ?></b></div>
      <div class="flex justify-between py-1 text-sm"><span class="text-gray-500">رمز الإحالة</span><b><?= e($u['referral_code']) ?></b></div>
      <div class="flex justify-between py-1 text-sm"><span class="text-gray-500">انضمّ</span><b><?= d($u['created_at']) ?></b></div>
    </div>
    <!-- القوائم -->
    <div class="bg-white rounded-xl border p-5">
      <div class="font-bold mb-3">القوائم</div>
      <div class="flex flex-wrap gap-2 mb-3">
        <?php foreach ($memberOf as $l): ?>
          <span class="inline-flex items-center gap-1 bg-amber-100 text-amber-800 rounded-full px-3 py-1 text-xs">
            <?= e($l['name']) ?>
            <?php if (can('lists','edit')): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="remove_list">
              <input type="hidden" name="list_id" value="<?= e($l['id']) ?>"><button class="text-red-600">×</button></form>
            <?php endif; ?>
          </span>
        <?php endforeach; ?>
        <?php if (!$memberOf): ?><span class="text-gray-400 text-sm">غير مضاف لأي قائمة.</span><?php endif; ?>
      </div>
      <?php if (can('lists','edit') && $allLists): ?>
      <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="add_list">
        <select name="list_id" class="flex-1 border rounded-lg px-3 py-2 text-sm">
          <?php foreach ($allLists as $l): ?><option value="<?= e($l['id']) ?>"><?= e($l['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="bg-gray-800 text-white rounded-lg px-4 text-sm font-bold">أضف</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="bg-white rounded-xl border mt-6">
  <div class="px-5 py-3 border-b font-bold">المحافظ (المتاجر)</div>
  <table class="w-full text-sm"><tbody>
    <?php foreach ($stores as $s): ?>
      <tr class="border-t"><td class="px-5 py-2.5 font-bold"><?= e($s['business_name']) ?></td>
        <td class="px-5 py-2.5">متاحة: <b><?= n($s['available_points']) ?></b></td>
        <td class="px-5 py-2.5">مدى الحياة: <b><?= n($s['lifetime_points']) ?></b></td>
        <td class="px-5 py-2.5"><?= $s['visible']?badge('ظاهر','green'):badge('مخفي بالمتجر','gray') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$stores): ?><tr><td class="px-5 py-4 text-gray-400">لا متاجر مرتبطة.</td></tr><?php endif; ?>
  </tbody></table>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
