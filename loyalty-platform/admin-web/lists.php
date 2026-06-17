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
      q("insert into admin.user_lists (name, description, created_by) values (:n,:d,:c)",
        ['n'=>$name,'d'=>trim((string)post('description')) ?: null,'c'=>current_admin()['id']]);
      audit('create','list',null,['name'=>$name]); flash('تم إنشاء القائمة.');
    }
  } elseif ($act === 'delete') {
    require_perm('lists', 'delete');
    q("delete from admin.user_lists where id=:id", ['id'=>(string)post('id')]);
    audit('delete','list',(string)post('id')); flash('تم حذف القائمة.');
  }
  redirect('lists.php');
}

$lists = all("select l.*, (select count(*) from admin.list_members m where m.list_id=l.id) members
   from admin.user_lists l order by l.created_at desc");

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
      <button class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg py-2">إنشاء</button>
    </form>
    <p class="text-xs text-gray-400 mt-3">أضف المستخدمين للقوائم من صفحة «المستخدمون» (تحديد متعدّد) أو من ملف كل مستخدم.</p>
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
          <td class="px-4 py-3"><div class="font-bold"><?= e($l['name']) ?></div><div class="text-xs text-gray-400"><?= e($l['description']) ?></div></td>
          <td class="px-4 py-3"><b><?= n($l['members']) ?></b></td>
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
