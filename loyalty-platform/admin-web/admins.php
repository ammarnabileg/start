<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('admins', 'view'); // can() يسمح فقط لـ Super Admin

$roles = all("select id, name from admin.roles order by is_super desc, name");

if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  if ($act === 'create') {
    $email = trim((string)post('email')); $name = trim((string)post('name')); $pass = (string)post('password');
    if ($email && $name && strlen($pass) >= 8) {
      try {
        q("insert into admin.users (email,name,password_hash,role_id) values (:e,:n,:p,:r)",
          ['e'=>$email,'n'=>$name,'p'=>password_hash($pass,PASSWORD_BCRYPT),'r'=>(string)post('role_id') ?: null]);
        audit('create','admin',null,['email'=>$email]); flash('تم إنشاء المسؤول.');
      } catch (Throwable $ex) { flash('البريد مستخدم بالفعل.', 'error'); }
    } else flash('أكمل الحقول (كلمة مرور ٨+).', 'error');
  } elseif ($act === 'update') {
    q("update admin.users set name=:n, role_id=:r, is_active=:a where id=:id", [
      'n'=>trim((string)post('name')), 'r'=>(string)post('role_id') ?: null,
      'a'=>post('is_active')?'true':'false', 'id'=>(string)post('id')]);
    if (($p = (string)post('password')) !== '' && strlen($p) >= 8)
      q("update admin.users set password_hash=:p where id=:id", ['p'=>password_hash($p,PASSWORD_BCRYPT),'id'=>(string)post('id')]);
    audit('update','admin',(string)post('id')); flash('تم التحديث.');
  } elseif ($act === 'delete') {
    if ((string)post('id') === current_admin()['id']) { flash('لا يمكنك حذف حسابك.', 'error'); }
    else { q("delete from admin.users where id=:id", ['id'=>(string)post('id')]); audit('delete','admin',(string)post('id')); flash('تم الحذف.'); }
  }
  redirect('admins.php');
}

$admins = all("select u.*, r.name role_name from admin.users u left join admin.roles r on r.id=u.role_id order by u.created_at");
$title = 'حسابات المسؤولين';
require __DIR__ . '/partials/header.php';
?>
<div class="grid md:grid-cols-3 gap-6">
  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-3">مسؤول جديد</div>
    <form method="post" class="space-y-3"><?= csrf_field() ?><input type="hidden" name="action" value="create">
      <input name="name" placeholder="الاسم" required class="w-full border rounded-lg px-3 py-2">
      <input name="email" type="email" placeholder="البريد" required class="w-full border rounded-lg px-3 py-2">
      <input name="password" type="password" placeholder="كلمة المرور (٨+)" required class="w-full border rounded-lg px-3 py-2">
      <select name="role_id" class="w-full border rounded-lg px-3 py-2">
        <?php foreach ($roles as $r): ?><option value="<?= e($r['id']) ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg py-2">إنشاء</button>
    </form>
  </div>

  <div class="md:col-span-2 bg-white rounded-xl border overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-500 text-right"><tr>
        <th class="px-4 py-3 font-medium">المسؤول</th><th class="px-4 py-3 font-medium">الدور</th>
        <th class="px-4 py-3 font-medium">الحالة</th><th class="px-4 py-3 font-medium">آخر دخول</th><th class="px-4 py-3 font-medium"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($admins as $a): ?>
        <tr class="border-t align-top">
          <td class="px-4 py-3"><div class="font-bold"><?= e($a['name']) ?></div><div class="text-xs text-gray-400"><?= e($a['email']) ?></div></td>
          <td class="px-4 py-3"><?= badge($a['role_name'] ?: '—','blue') ?></td>
          <td class="px-4 py-3"><?= $a['is_active']?status_badge('active'):badge('موقوف','red') ?></td>
          <td class="px-4 py-3 text-gray-500"><?= dt($a['last_login_at']) ?></td>
          <td class="px-4 py-3">
            <details><summary class="cursor-pointer text-amber-600">تعديل</summary>
              <form method="post" class="mt-2 space-y-2 bg-gray-50 p-3 rounded-lg"><?= csrf_field() ?>
                <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= e($a['id']) ?>">
                <input name="name" value="<?= e($a['name']) ?>" class="w-full border rounded px-2 py-1">
                <select name="role_id" class="w-full border rounded px-2 py-1">
                  <?php foreach ($roles as $r): ?><option value="<?= e($r['id']) ?>" <?= $a['role_id']===$r['id']?'selected':'' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
                </select>
                <input name="password" type="password" placeholder="كلمة مرور جديدة (اختياري)" class="w-full border rounded px-2 py-1">
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" <?= $a['is_active']?'checked':'' ?>> مفعّل</label>
                <div class="flex gap-2">
                  <button class="bg-gray-800 text-white rounded px-3 py-1 text-xs font-bold">حفظ</button>
                  <?php if ($a['id'] !== current_admin()['id']): ?>
                  <button form="del<?= e($a['id']) ?>" class="bg-red-100 text-red-700 rounded px-3 py-1 text-xs font-bold">حذف</button>
                  <?php endif; ?>
                </div>
              </form>
              <form id="del<?= e($a['id']) ?>" method="post" onsubmit="return confirm('حذف المسؤول؟')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($a['id']) ?>"></form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
