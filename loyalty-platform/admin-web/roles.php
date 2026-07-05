<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('roles', 'view'); // Super Admin فقط

if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  if ($act === 'create') {
    $name = trim((string)post('name'));
    if ($name !== '') {
      try { q("insert into admin.roles (name, permissions) values (:n, '{}'::jsonb)", ['n'=>$name]);
        audit('create','role',null,['name'=>$name]); flash('تم إنشاء الدور.'); }
      catch (Throwable $ex) { flash('اسم الدور مستخدم.', 'error'); }
    }
  } elseif ($act === 'save_perms') {
    $id = (string) post('id');
    $role = one("select * from admin.roles where id=:id", ['id'=>$id]);
    if ($role && !$role['is_super']) {
      $perm = [];
      foreach ((array) post('perm', []) as $res => $acts) {
        $clean = array_values(array_intersect((array)$acts, array_keys(ACTIONS)));
        // «مشاهدة» = عرض فقط، تتنافى مع الكتابة. لو فيه أي كتابة نُسقط علم العرض-فقط
        // (المشاهدة مضمّنة ضمنيًا في can() لأي صلاحية كتابة).
        if (array_intersect($clean, WRITE_ACTIONS)) {
          $clean = array_values(array_diff($clean, ['view']));
        }
        if ($clean) $perm[$res] = $clean;
      }
      q("update admin.roles set permissions=:p where id=:id", ['p'=>json_encode($perm, JSON_UNESCAPED_UNICODE),'id'=>$id]);
      audit('update','role',$id); flash('تم حفظ صلاحيات الدور.');
    }
  } elseif ($act === 'delete') {
    $role = one("select * from admin.roles where id=:id", ['id'=>(string)post('id')]);
    if ($role && !$role['is_super']) {
      q("delete from admin.roles where id=:id", ['id'=>(string)post('id')]);
      audit('delete','role',(string)post('id')); flash('تم حذف الدور.');
    } else flash('لا يمكن حذف دور Super Admin.', 'error');
  }
  redirect('roles.php');
}

$roles = all("select r.*, (select count(*) from admin.users u where u.role_id=r.id) members from admin.roles r order by is_super desc, name");
$title = 'الأدوار والصلاحيات';
require __DIR__ . '/partials/header.php';
?>
<div class="bg-white rounded-xl border p-4 mb-6">
  <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <input name="name" placeholder="اسم دور جديد" required class="flex-1 border rounded-lg px-4 py-2">
    <button class="bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg px-6 py-2">إنشاء دور</button>
  </form>
</div>

<?php foreach ($roles as $role):
  $perms = $role['permissions'] ? json_decode($role['permissions'], true) : []; ?>
  <div class="bg-white rounded-xl border mb-4">
    <div class="px-5 py-3 border-b flex items-center justify-between">
      <div class="font-bold"><?= e($role['name']) ?>
        <?php if ($role['is_super']): ?><?= badge('صلاحية كاملة','green') ?><?php endif; ?>
        <span class="text-xs text-gray-400 font-normal"><?= n($role['members']) ?> مسؤول</span>
      </div>
      <?php if (!$role['is_super']): ?>
      <form method="post" onsubmit="return confirm('حذف الدور؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($role['id']) ?>">
        <button class="text-red-600 text-sm font-bold">حذف</button></form>
      <?php endif; ?>
    </div>
    <?php if ($role['is_super']): ?>
      <div class="p-5 text-gray-500 text-sm">هذا الدور يملك كل الصلاحيات على كل الموارد تلقائيًا.</div>
    <?php else: ?>
    <form method="post" class="p-5"><?= csrf_field() ?><input type="hidden" name="action" value="save_perms"><input type="hidden" name="id" value="<?= e($role['id']) ?>">
      <table class="w-full text-sm">
        <thead class="text-gray-500 text-right"><tr><th class="py-2">المورد</th>
          <?php foreach (ACTIONS as $a=>$al): ?><th class="py-2 text-center"><?= e($al) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach (RESOURCES as $res=>$rl):
          if (in_array($res, ['admins','roles'], true)) continue; // مقصورة على Super
          $has = $perms[$res] ?? []; ?>
          <tr class="border-t"><td class="py-2 font-bold"><?= e($rl) ?></td>
            <?php foreach (ACTIONS as $a=>$al):
              $applicable = $a!=='approve' || $res==='merchants';
              $applicable = $applicable && !($a==='create' && in_array($res,['users','reports','audit','dashboard'])); ?>
              <td class="py-2 text-center">
                <?php if ($applicable): ?>
                  <input type="checkbox" name="perm[<?= e($res) ?>][]" value="<?= e($a) ?>" onchange="permSync(this)" <?= in_array($a,$has,true)?'checked':'' ?>>
                <?php else: ?><span class="text-gray-200">—</span><?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <button class="mt-4 bg-gray-800 text-white font-bold rounded-lg px-6 py-2">حفظ صلاحيات «<?= e($role['name']) ?>»</button>
    </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<p class="text-xs text-gray-400 mt-2">«مشاهدة» = عرض فقط (يُلغى تلقائيًا عند اختيار أي صلاحية كتابة). أي صلاحية كتابة (إضافة/تعديل/حذف/اعتماد) تتضمّن المشاهدة ضمنيًا.</p>
<script>
// تنافٍ متبادل ضمن نفس الصف: «مشاهدة» (عرض فقط) × صلاحيات الكتابة.
function permSync(cb){
  var row = cb.closest('tr'); if(!row) return;
  var boxes = row.querySelectorAll('input[type=checkbox]');
  var view=null, writes=[];
  boxes.forEach(function(b){ if(b.value==='view') view=b; else writes.push(b); });
  if(!view) return;
  if(cb.value==='view'){
    if(cb.checked) writes.forEach(function(b){ b.checked=false; });   // عرض فقط → ألغِ كل الكتابة
  } else if(cb.checked){
    view.checked=false;                                               // اخترت كتابة → ألغِ «عرض فقط»
  }
}
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
