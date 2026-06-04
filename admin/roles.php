<?php
pi_require_perm('view_roles');

$action = $_GET['action'] ?? 'list';
$msg = '';

// Permission definitions for UI
$permission_groups = [
    'الشخصيات' => [
        1 => 'عرض الشخصيات',
        2 => 'إضافة شخصية',
        3 => 'تعديل شخصية',
        4 => 'حذف شخصية',
    ],
    'المؤسسات' => [
        5 => 'عرض المؤسسات',
        6 => 'إضافة مؤسسة',
        7 => 'تعديل مؤسسة',
        8 => 'حذف مؤسسة',
    ],
    'التصنيفات' => [
        9  => 'عرض التصنيفات',
        10 => 'إضافة تصنيف',
        11 => 'تعديل تصنيف',
        12 => 'حذف تصنيف',
    ],
    'المقالات' => [
        13 => 'عرض المقالات',
        14 => 'إضافة مقال',
        15 => 'تعديل مقال',
        16 => 'حذف مقال',
    ],
    'الأدوار والصلاحيات' => [
        17 => 'عرض الأدوار',
        18 => 'إضافة دور',
        19 => 'تعديل دور',
        20 => 'حذف دور',
    ],
    'مستخدمو الإدارة' => [
        21 => 'عرض المستخدمين',
        22 => 'إضافة مستخدم',
        23 => 'تعديل مستخدم',
        24 => 'حذف مستخدم',
    ],
    'الرعاة والمحتوى' => [
        25 => 'عرض الرعاة',
        26 => 'إدارة الرعاة',
        27 => 'عرض المحطات الزمنية',
        28 => 'إدارة المحطات الزمنية',
    ],
    'إعدادات النظام' => [
        29 => 'إدارة الدول',
        30 => 'إعدادات الموقع',
    ],
    'مستخدمو الموقع' => [
        31 => 'إدارة مستخدمي الموقع',
    ],
    'المحتوى والطلبات' => [
        32 => 'إدارة طلبات الإعلان',
        33 => 'إدارة طلبات العضوية',
        34 => 'عرض الشكاوي والملاحظات',
        35 => 'إدارة مقترحات المستخدمين',
        36 => 'إدارة طلبات التعديل والترقية',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'save_role') {
        $id   = (int)($_POST['role_id'] ?? 0);
        $name = pi_escape($_POST['role_name'] ?? '');
        $perms = implode(',', array_map('intval', $_POST['permissions'] ?? []));

        if ($id) {
            pi_require_perm('edit_role');
            $mysqli->query("UPDATE pi_roles SET role_name='$name',role_permissions='$perms' WHERE role_id=$id");
        } else {
            pi_require_perm('add_role');
            $mysqli->query("INSERT INTO pi_roles (role_name,role_permissions) VALUES ('$name','$perms')");
        }
        $msg = 'تم حفظ الدور بنجاح';
        $action = 'list';
    }

    if ($act === 'delete_role') {
        pi_require_perm('delete_role');
        $id = (int)($_POST['role_id'] ?? 0);
        $mysqli->query("DELETE FROM pi_roles WHERE role_id=$id");
        $msg = 'تم حذف الدور';
    }
}

if ($action === 'add' || $action === 'edit') {
    $edit_role = null;
    $edit_perms = [];
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_roles WHERE role_id=$eid");
        if ($r && $r->num_rows) {
            $edit_role = $r->fetch_assoc();
            $edit_perms = array_map('intval', explode(',', $edit_role['role_permissions'] ?? ''));
        }
    }
?>

<div class="max-w-3xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=roles" class="text-gray-400 hover:text-gray-600 transition">
      <i class="fa-solid fa-arrow-right text-lg"></i>
    </a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إنشاء دور جديد':'تعديل الدور' ?></h2>
  </div>

  <form method="POST" class="space-y-6">
    <input type="hidden" name="action" value="save_role">
    <?php if ($edit_role): ?><input type="hidden" name="role_id" value="<?= $edit_role['role_id'] ?>"><?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm p-6">
      <label class="form-label">اسم الدور <span class="text-red-500">*</span></label>
      <input type="text" name="role_name" required class="form-input max-w-sm"
        placeholder="مثال: محرر محتوى"
        value="<?= htmlspecialchars($edit_role['role_name'] ?? '') ?>">
    </div>

    <!-- Permissions -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <div class="flex items-center justify-between mb-5">
        <h3 class="font-black text-gray-800">الصلاحيات</h3>
        <div class="flex gap-2">
          <button type="button" onclick="document.querySelectorAll('[name=\'permissions[]\']').forEach(c=>c.checked=true)"
            class="text-xs font-bold text-purple-600 hover:underline">تحديد الكل</button>
          <span class="text-gray-300">|</span>
          <button type="button" onclick="document.querySelectorAll('[name=\'permissions[]\']').forEach(c=>c.checked=false)"
            class="text-xs font-bold text-gray-400 hover:underline">إلغاء الكل</button>
        </div>
      </div>

      <div class="space-y-6">
        <?php foreach ($permission_groups as $group_name => $perms): ?>
        <div class="border border-gray-100 rounded-xl p-4">
          <div class="flex items-center gap-3 mb-3">
            <h4 class="font-bold text-gray-700 text-sm"><?= $group_name ?></h4>
            <button type="button" onclick="toggleGroup(this)" class="text-xs text-blue-500 hover:underline font-semibold">تحديد المجموعة</button>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <?php foreach ($perms as $perm_id => $perm_label): ?>
            <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-purple-50 transition">
              <input type="checkbox" name="permissions[]" value="<?= $perm_id ?>"
                class="perm-cb w-4 h-4 accent-purple-500"
                <?= in_array($perm_id, $edit_perms)?'checked':'' ?>>
              <span class="text-sm text-gray-700 font-medium"><?= $perm_label ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="btn-primary flex items-center gap-2">
        <i class="fa-solid fa-shield-halved"></i> حفظ الدور
      </button>
      <a href="admin.php?p=roles" class="btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<script>
function toggleGroup(btn) {
  const grid = btn.closest('.border').querySelectorAll('.perm-cb');
  const allChecked = [...grid].every(c=>c.checked);
  grid.forEach(c=>c.checked=!allChecked);
  btn.textContent = allChecked ? 'تحديد المجموعة' : 'إلغاء المجموعة';
}
</script>

<?php } else { // LIST
$roles = [];
$r = $mysqli->query("SELECT r.*, (SELECT COUNT(*) FROM pi_admin_users WHERE au_role_id=r.role_id AND au_active=1) as user_count FROM pi_roles r ORDER BY r.role_id");
if ($r) while ($row=$r->fetch_assoc()) $roles[] = $row;
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm flex items-center gap-2">
  <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h2 class="text-xl font-black text-gray-800">الأدوار والصلاحيات</h2>
    <p class="text-gray-400 text-sm mt-0.5">إنشاء وإدارة أدوار المستخدمين وصلاحياتهم</p>
  </div>
  <?php if (pi_has_perm('add_role')): ?>
  <a href="admin.php?p=roles&action=add" class="btn-primary flex items-center gap-2">
    <i class="fa-solid fa-plus"></i> إضافة دور جديد
  </a>
  <?php endif; ?>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
  <?php foreach ($roles as $role): ?>
  <?php
  $role_perms = array_filter(array_map('intval', explode(',', $role['role_permissions'] ?? '')));
  $all_perms_flat = [];
  foreach ($permission_groups as $pg) $all_perms_flat += $pg;
  $role_perm_labels = array_intersect_key($all_perms_flat, array_flip($role_perms));
  ?>
  <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-col">
    <div class="flex items-start justify-between mb-4">
      <div>
        <h3 class="font-black text-gray-800 text-base"><?= htmlspecialchars($role['role_name']) ?></h3>
        <p class="text-gray-400 text-xs mt-0.5">
          <i class="fa-solid fa-users text-xs mr-1"></i>
          <?= $role['user_count'] ?> مستخدم
          &bull;
          <?= count($role_perms) ?> صلاحية
        </p>
      </div>
      <div class="flex gap-1">
        <?php if (pi_has_perm('edit_role')): ?>
        <a href="admin.php?p=roles&action=edit&id=<?= $role['role_id'] ?>"
          class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition">
          <i class="fa-solid fa-pen text-xs"></i>
        </a>
        <?php endif; ?>
        <?php if (pi_has_perm('delete_role') && $role['role_id'] != 1): ?>
        <form method="POST" onsubmit="return confirm('حذف الدور نهائياً؟')">
          <input type="hidden" name="action" value="delete_role">
          <input type="hidden" name="role_id" value="<?= $role['role_id'] ?>">
          <button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition">
            <i class="fa-solid fa-trash text-xs"></i>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Permission tags -->
    <div class="flex flex-wrap gap-1.5 flex-1">
      <?php foreach ($permission_groups as $group_name => $group_perms): ?>
        <?php $matched = array_intersect_key($group_perms, array_flip($role_perms)); ?>
        <?php if (!empty($matched)): ?>
        <div class="w-full">
          <p class="text-xs text-gray-400 font-semibold mb-1"><?= $group_name ?></p>
          <div class="flex flex-wrap gap-1">
            <?php foreach ($matched as $pid => $plabel): ?>
            <span class="px-2 py-0.5 bg-purple-50 text-purple-800 text-xs font-semibold rounded-full"><?= $plabel ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (empty($role_perms)): ?>
      <p class="text-gray-300 text-sm italic">لا توجد صلاحيات</p>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($roles)): ?>
  <div class="col-span-3 text-center py-16 text-gray-400">
    <i class="fa-solid fa-shield-halved text-5xl mb-4 block"></i>
    لا توجد أدوار بعد
  </div>
  <?php endif; ?>
</div>
<?php } ?>
