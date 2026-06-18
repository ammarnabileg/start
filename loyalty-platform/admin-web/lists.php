<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('lists', 'view');

if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  if ($act === 'create') {
    require_perm('lists', 'create');
    $name = trim((string)post('name'));
    if ($name !== '') {
      $smart = post('is_smart') ? true : false;
      $criteria = null;
      if ($smart) {
        $c = [];
        foreach (['min_points','max_points','inactive_days','active_days'] as $k)
          if (($v = trim((string)post($k))) !== '') $c[$k] = (int)$v;
        if (($jd = trim((string)post('joined_after'))) !== '') $c['joined_after'] = $jd;
        if (post('shared') === 'yes') $c['shared'] = true;
        if (post('shared') === 'no')  $c['shared'] = false;
        $criteria = json_encode($c, JSON_UNESCAPED_UNICODE);
      }
      q("insert into admin.user_lists (name, description, created_by, is_smart, criteria)
         values (:n,:d,:c,:s,:cr)",
        ['n'=>$name,'d'=>trim((string)post('description')) ?: null,'c'=>current_admin()['id'],
         's'=>$smart?'true':'false','cr'=>$criteria]);
      audit('create','list',null,['name'=>$name,'smart'=>$smart]); flash('تم إنشاء القائمة.');
    }
  } elseif ($act === 'delete') {
    require_perm('lists', 'delete');
    q("delete from admin.user_lists where id=:id", ['id'=>(string)post('id')]);
    audit('delete','list',(string)post('id')); flash('تم حذف القائمة.');
  }
  redirect('lists.php');
}

$lists = all("select * from admin.user_lists order by created_at desc");

$title = 'القوائم / الشرائح';
require __DIR__ . '/partials/header.php';
?>
<div class="grid md:grid-cols-3 gap-6">
  <?php if (can('lists','create')): ?>
  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-3">قائمة جديدة</div>
    <form method="post" class="space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <input name="name" placeholder="اسم القائمة" required class="w-full border rounded-lg px-3 py-2">
      <textarea name="description" placeholder="وصف (اختياري)" class="w-full border rounded-lg px-3 py-2" rows="2"></textarea>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_smart" onclick="document.getElementById('sm').style.display=this.checked?'block':'none'"> قائمة ذكية (تتحدّث تلقائيًا)</label>
      <div id="sm" style="display:none" class="space-y-2 bg-amber-50 border border-amber-200 rounded-lg p-3">
        <div class="grid grid-cols-2 gap-2">
          <input name="min_points" type="number" placeholder="نقاط ≥" class="border rounded-lg px-2 py-1.5 text-sm">
          <input name="max_points" type="number" placeholder="نقاط ≤" class="border rounded-lg px-2 py-1.5 text-sm">
          <input name="inactive_days" type="number" placeholder="غير نشِط منذ (يوم)" class="border rounded-lg px-2 py-1.5 text-sm">
          <input name="active_days" type="number" placeholder="نشِط خلال (يوم)" class="border rounded-lg px-2 py-1.5 text-sm">
        </div>
        <label class="block text-xs text-gray-500">انضمّ بعد <input name="joined_after" type="date" class="mt-1 w-full border rounded-lg px-2 py-1.5 text-sm"></label>
        <select name="shared" class="w-full border rounded-lg px-2 py-1.5 text-sm">
          <option value="">المشاركة: الكل</option><option value="yes">يشارك بياناته</option><option value="no">مخفي</option>
        </select>
      </div>
      <button class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg py-2">إنشاء</button>
    </form>
    <p class="text-xs text-gray-400 mt-3">القائمة الثابتة: تضيف لها يدويًا من «المستخدمون». الذكية: تُحسب تلقائيًا من المعايير.</p>
  </div>
  <?php endif; ?>

  <div class="<?= can('lists','create')?'md:col-span-2':'md:col-span-3' ?> bg-white rounded-xl border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-500 text-right"><tr>
        <th class="px-4 py-3 font-medium">القائمة</th><th class="px-4 py-3 font-medium">الأعضاء</th>
        <th class="px-4 py-3 font-medium">أُنشئت</th><th class="px-4 py-3 font-medium"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($lists as $l): ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="px-4 py-3"><div class="font-bold"><?= e($l['name']) ?> <?= $l['is_smart']?badge('ذكية','blue'):'' ?></div><div class="text-xs text-gray-400"><?= e($l['description']) ?></div></td>
          <td class="px-4 py-3"><b><?= n(list_count($l)) ?></b></td>
          <td class="px-4 py-3 text-gray-500"><?= d($l['created_at']) ?></td>
          <td class="px-4 py-3">
            <div class="flex gap-1">
              <?php if (can('notifications','create')): ?><a href="notifications.php?list=<?= e($l['id']) ?>" class="px-2.5 py-1 rounded bg-blue-100 text-blue-700 text-xs font-bold">إشعار</a><?php endif; ?>
              <?php if (can('lists','delete')): ?>
              <form method="post" class="inline" onsubmit="return confirm('حذف القائمة؟')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($l['id']) ?>">
                <button class="px-2.5 py-1 rounded bg-red-100 text-red-700 text-xs font-bold">حذف</button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lists): ?><tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">لا قوائم بعد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
