<?php
pi_require_perm('view_admin_users');

$action = $_GET['action'] ?? 'list';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'save_user') {
        $id      = (int)($_POST['au_id'] ?? 0);
        $name    = pi_escape($_POST['au_name'] ?? '');
        $email   = pi_escape($_POST['au_email'] ?? '');
        $role_id = (int)($_POST['au_role_id'] ?? 0);
        $pass    = $_POST['au_password'] ?? '';

        if ($id) {
            pi_require_perm('edit_admin_user');
            $extra = $pass ? ",au_password='".pi_escape(password_hash($pass,PASSWORD_DEFAULT))."'" : '';
            $mysqli->query("UPDATE pi_admin_users SET au_name='$name',au_email='$email',au_role_id=$role_id$extra WHERE au_id=$id");
        } else {
            pi_require_perm('add_admin_user');
            $hashed = pi_escape(password_hash($pass ?: 'changeme123', PASSWORD_DEFAULT));
            $mysqli->query("INSERT INTO pi_admin_users (au_name,au_email,au_password,au_role_id) VALUES ('$name','$email','$hashed',$role_id)");
        }
        $msg = 'تم الحفظ بنجاح';
        $action = 'list';
    }

    if ($act === 'toggle_active') {
        pi_require_perm('edit_admin_user');
        $id = (int)($_POST['au_id'] ?? 0);
        $mysqli->query("UPDATE pi_admin_users SET au_active=!au_active WHERE au_id=$id");
    }

    if ($act === 'delete_user') {
        pi_require_perm('delete_admin_user');
        $id = (int)($_POST['au_id'] ?? 0);
        $mysqli->query("DELETE FROM pi_admin_users WHERE au_id=$id");
        $msg = 'تم الحذف';
    }
}

// Roles for dropdown
$roles_list = [];
$r = $mysqli->query("SELECT * FROM pi_roles ORDER BY role_id");
if ($r) while ($row=$r->fetch_assoc()) $roles_list[] = $row;

if ($action === 'add' || $action === 'edit') {
    $edit_u = null;
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_admin_users WHERE au_id=$eid");
        if ($r && $r->num_rows) $edit_u = $r->fetch_assoc();
    }
?>
<div class="max-w-xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=admin_users" class="text-gray-400 hover:text-gray-600 transition">
      <i class="fa-solid fa-arrow-right text-lg"></i>
    </a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة مستخدم جديد':'تعديل المستخدم' ?></h2>
  </div>

  <form method="POST" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_user">
    <?php if ($edit_u): ?><input type="hidden" name="au_id" value="<?= $edit_u['au_id'] ?>"><?php endif; ?>

    <div>
      <label class="form-label">الاسم الكامل</label>
      <input type="text" name="au_name" required class="form-input"
        value="<?= htmlspecialchars($edit_u['au_name'] ?? '') ?>">
    </div>
    <div>
      <label class="form-label">البريد الإلكتروني</label>
      <input type="email" name="au_email" required class="form-input" dir="ltr"
        value="<?= htmlspecialchars($edit_u['au_email'] ?? '') ?>">
    </div>
    <div>
      <label class="form-label">الدور</label>
      <select name="au_role_id" class="form-input">
        <option value="">— اختر الدور —</option>
        <?php foreach ($roles_list as $role): ?>
        <option value="<?= $role['role_id'] ?>" <?= ($edit_u['au_role_id']??0)==$role['role_id']?'selected':'' ?>>
          <?= htmlspecialchars($role['role_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label">كلمة المرور <?= $action==='edit'?'(اتركها فارغة لعدم التغيير)':'' ?></label>
      <input type="password" name="au_password" class="form-input" dir="ltr"
        <?= $action==='add'?'required':'' ?> placeholder="••••••••">
    </div>

    <div class="flex gap-3 pt-2">
      <button type="submit" class="btn-primary flex items-center gap-2">
        <i class="fa-solid fa-floppy-disk"></i> حفظ المستخدم
      </button>
      <a href="admin.php?p=admin_users" class="btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<?php } else {
$users = [];
$r = $mysqli->query("SELECT u.*, r.role_name FROM pi_admin_users u LEFT JOIN pi_roles r ON u.au_role_id=r.role_id ORDER BY u.au_id");
if ($r) while ($row=$r->fetch_assoc()) $users[] = $row;
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm flex items-center gap-2">
  <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h2 class="text-xl font-black text-gray-800">مستخدمو الإدارة</h2>
    <p class="text-gray-400 text-sm mt-0.5"><?= count($users) ?> مستخدم</p>
  </div>
  <?php if (pi_has_perm('add_admin_user')): ?>
  <a href="admin.php?p=admin_users&action=add" class="btn-primary flex items-center gap-2">
    <i class="fa-solid fa-user-plus"></i> إضافة مستخدم
  </a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead>
      <tr>
        <th>المستخدم</th>
        <th>البريد الإلكتروني</th>
        <th>الدور</th>
        <th>الحالة</th>
        <th>تاريخ الإنشاء</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr class="hover:bg-gray-50 transition">
        <td>
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-orange-400 to-orange-600 flex items-center justify-center text-white font-bold text-sm">
              <?= mb_substr($u['au_name'],0,1) ?>
            </div>
            <p class="font-bold text-gray-800"><?= htmlspecialchars($u['au_name']) ?></p>
          </div>
        </td>
        <td class="text-gray-500 text-xs" dir="ltr"><?= htmlspecialchars($u['au_email']) ?></td>
        <td>
          <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-bold">
            <?= htmlspecialchars($u['role_name'] ?? 'بدون دور') ?>
          </span>
        </td>
        <td>
          <form method="POST" class="inline">
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="au_id" value="<?= $u['au_id'] ?>">
            <button type="submit" class="<?= $u['au_active']?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?> px-3 py-1 rounded-full text-xs font-bold hover:opacity-80 transition">
              <?= $u['au_active']?'نشط':'معطل' ?>
            </button>
          </form>
        </td>
        <td class="text-gray-400 text-xs"><?= date('d/m/Y', strtotime($u['au_created'])) ?></td>
        <td>
          <div class="flex items-center gap-2">
            <?php if (pi_has_perm('edit_admin_user')): ?>
            <a href="admin.php?p=admin_users&action=edit&id=<?= $u['au_id'] ?>"
              class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-orange-50 hover:text-orange-500 transition">
              <i class="fa-solid fa-pen text-xs"></i>
            </a>
            <?php endif; ?>
            <?php if (pi_has_perm('delete_admin_user') && $u['au_id'] != ($_SESSION['pi_admin_id']??0)): ?>
            <form method="POST" onsubmit="return confirm('حذف المستخدم نهائياً؟')">
              <input type="hidden" name="action" value="delete_user">
              <input type="hidden" name="au_id" value="<?= $u['au_id'] ?>">
              <button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition">
                <i class="fa-solid fa-trash text-xs"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php } ?>
