<?php
pi_require_perm('view_categories');
$action = $_GET['action'] ?? 'list';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'save_category') {
        $id     = (int)($_POST['cat_id'] ?? 0);
        $name   = pi_escape($_POST['cat_name'] ?? '');
        $name_en= pi_escape($_POST['cat_name_en'] ?? '');
        $icon   = pi_escape($_POST['cat_icon'] ?? 'fa-star');
        $color  = pi_escape($_POST['cat_badge_color'] ?? 'blue');
        $order  = (int)($_POST['cat_order'] ?? 0);

        if ($id) {
            pi_require_perm('edit_category');
            $mysqli->query("UPDATE pi_categories SET cat_name='$name',cat_name_en='$name_en',cat_icon='$icon',cat_badge_color='$color',cat_order=$order WHERE cat_id=$id");
        } else {
            pi_require_perm('add_category');
            $mysqli->query("INSERT INTO pi_categories (cat_name,cat_name_en,cat_icon,cat_badge_color,cat_order) VALUES ('$name','$name_en','$icon','$color',$order)");
        }
        $msg = 'تم حفظ التصنيف';
        $action = 'list';
    }

    if ($act === 'delete_category') {
        pi_require_perm('delete_category');
        $id = (int)($_POST['cat_id'] ?? 0);
        $mysqli->query("UPDATE pi_categories SET cat_active=0 WHERE cat_id=$id");
        $msg = 'تم الحذف';
    }
}

$colors = ['orange','blue','purple','cyan','red','green','gold','navy','teal','brown','gray','darkblue'];

if ($action === 'add' || $action === 'edit') {
    $ec = null;
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_categories WHERE cat_id=$eid");
        if ($r && $r->num_rows) $ec = $r->fetch_assoc();
    }
?>
<div class="max-w-xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=categories" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-right text-lg"></i></a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة تصنيف':'تعديل التصنيف' ?></h2>
  </div>
  <form method="POST" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_category">
    <?php if ($ec): ?><input type="hidden" name="cat_id" value="<?= $ec['cat_id'] ?>"><?php endif; ?>
    <div><label class="form-label">اسم التصنيف (عربي) *</label>
    <input type="text" name="cat_name" required class="form-input" value="<?= htmlspecialchars($ec['cat_name']??'') ?>"></div>
    <div><label class="form-label">اسم التصنيف (إنجليزي)</label>
    <input type="text" name="cat_name_en" class="form-input" dir="ltr" value="<?= htmlspecialchars($ec['cat_name_en']??'') ?>"></div>
    <div><label class="form-label">أيقونة FontAwesome (مثال: fa-star)</label>
    <input type="text" name="cat_icon" class="form-input" dir="ltr" value="<?= htmlspecialchars($ec['cat_icon']??'fa-star') ?>">
    <p class="text-xs text-gray-400 mt-1">ابحث في <a href="https://fontawesome.com/icons" target="_blank" class="text-blue-500 underline">fontawesome.com/icons</a></p></div>
    <div><label class="form-label">لون الـ Badge</label>
    <select name="cat_badge_color" class="form-input">
      <?php foreach ($colors as $c): ?>
      <option value="<?=$c?>" <?= ($ec['cat_badge_color']??'blue')===$c?'selected':'' ?>><?=$c?></option>
      <?php endforeach; ?>
    </select></div>
    <div><label class="form-label">الترتيب</label>
    <input type="number" name="cat_order" class="form-input" value="<?= $ec['cat_order']??0 ?>"></div>
    <div class="flex gap-3"><button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk mr-2"></i>حفظ</button>
    <a href="admin.php?p=categories" class="btn-secondary">إلغاء</a></div>
  </form>
</div>
<?php } else {
$cats = [];
$r = $mysqli->query("SELECT c.*, (SELECT COUNT(*) FROM pi_personality_categories pc WHERE pc.cat_id=c.cat_id) as p_count FROM pi_categories c WHERE c.cat_active=1 ORDER BY c.cat_order,c.cat_id");
if ($r) while ($row=$r->fetch_assoc()) $cats[] = $row;
?>
<?php if ($msg): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">التصنيفات (<?= count($cats) ?>)</h2>
  <?php if (pi_has_perm('add_category')): ?>
  <a href="admin.php?p=categories&action=add" class="btn-primary flex items-center gap-2"><i class="fa-solid fa-plus"></i> إضافة تصنيف</a>
  <?php endif; ?>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead><tr><th>التصنيف</th><th>الأيقونة</th><th>اللون</th><th>عدد الشخصيات</th><th>الترتيب</th><th>الإجراءات</th></tr></thead>
    <tbody>
      <?php foreach ($cats as $cat): ?>
      <tr class="hover:bg-gray-50 transition">
        <td><p class="font-bold text-gray-800"><?= htmlspecialchars($cat['cat_name']) ?></p><p class="text-gray-400 text-xs" dir="ltr"><?= htmlspecialchars($cat['cat_name_en']??'') ?></p></td>
        <td><i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?> text-purple-500 text-lg"></i></td>
        <td><span class="px-2 py-0.5 <?= pi_badge_class($cat['cat_badge_color']) ?> rounded-full text-xs font-bold"><?= $cat['cat_badge_color'] ?></span></td>
        <td class="font-semibold"><?= $cat['p_count'] ?></td>
        <td><?= $cat['cat_order'] ?></td>
        <td><div class="flex gap-2">
          <?php if (pi_has_perm('edit_category')): ?>
          <a href="admin.php?p=categories&action=edit&id=<?= $cat['cat_id'] ?>" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition"><i class="fa-solid fa-pen text-xs"></i></a>
          <?php endif; ?>
          <?php if (pi_has_perm('delete_category')): ?>
          <form method="POST" onsubmit="return confirm('حذف التصنيف؟')">
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="cat_id" value="<?= $cat['cat_id'] ?>">
            <button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-trash text-xs"></i></button>
          </form>
          <?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php } ?>
