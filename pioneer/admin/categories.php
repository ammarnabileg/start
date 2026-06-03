<?php
pi_require_perm('view_categories');
$action = $_GET['action'] ?? 'list';
$msg = '';

// Add cat_label_id column if not exists
$mysqli->query("ALTER TABLE pi_categories ADD COLUMN IF NOT EXISTS cat_label_id INT DEFAULT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'save_category') {
        $id       = (int)($_POST['cat_id'] ?? 0);
        $name     = pi_escape($_POST['cat_name'] ?? '');
        $name_en  = pi_escape($_POST['cat_name_en'] ?? '');
        $icon     = pi_escape($_POST['cat_icon'] ?? 'fa-star');
        $label_id = (int)($_POST['cat_label_id'] ?? 0) ?: 'NULL';
        $order    = (int)($_POST['cat_order'] ?? 0);

        if ($id) {
            pi_require_perm('edit_category');
            $mysqli->query("UPDATE pi_categories SET cat_name='$name',cat_name_en='$name_en',cat_icon='$icon',cat_label_id=$label_id,cat_order=$order WHERE cat_id=$id");
        } else {
            pi_require_perm('add_category');
            $mysqli->query("INSERT INTO pi_categories (cat_name,cat_name_en,cat_icon,cat_label_id,cat_order) VALUES ('$name','$name_en','$icon',$label_id,$order)");
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

// Fetch all labels for dropdown
$all_labels = [];
$r = $mysqli->query("SELECT * FROM pi_labels WHERE label_active=1 ORDER BY label_order,label_id");
if ($r) while ($row = $r->fetch_assoc()) $all_labels[] = $row;

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

    <div>
      <label class="form-label">اسم التصنيف (عربي) *</label>
      <input type="text" name="cat_name" required class="form-input" value="<?= htmlspecialchars($ec['cat_name']??'') ?>">
    </div>
    <div>
      <label class="form-label">اسم التصنيف (إنجليزي)</label>
      <input type="text" name="cat_name_en" class="form-input" dir="ltr" value="<?= htmlspecialchars($ec['cat_name_en']??'') ?>">
    </div>
    <div>
      <label class="form-label">أيقونة FontAwesome <span class="text-gray-400 font-normal text-xs">(مثال: fa-star)</span></label>
      <div style="display:flex;gap:10px;align-items:center;">
        <input type="text" name="cat_icon" id="cat_icon_input" class="form-input" dir="ltr"
          value="<?= htmlspecialchars($ec['cat_icon']??'fa-star') ?>"
          oninput="document.getElementById('icon_prev').className='fa-solid '+this.value">
        <div id="icon_prev" style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i id="icon_prev" class="fa-solid <?= htmlspecialchars($ec['cat_icon']??'fa-star') ?>" style="color:#fff;font-size:16px;"></i>
        </div>
      </div>
      <p class="text-xs text-gray-400 mt-1">ابحث في <a href="https://fontawesome.com/icons" target="_blank" class="text-blue-500 underline">fontawesome.com/icons</a></p>
    </div>

    <div>
      <label class="form-label">الليبل <span class="text-gray-400 font-normal text-xs">(اختياري)</span></label>
      <?php if (empty($all_labels)): ?>
        <p class="text-sm text-gray-400 bg-gray-50 rounded-xl p-3">
          لا توجد ليبلات بعد —
          <a href="admin.php?p=labels" class="text-purple-600 font-bold hover:underline">أضف ليبلات من هنا</a>
        </p>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;">
        <!-- No label option -->
        <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:border-color .15s;"
          onmouseover="this.style.borderColor='#a855f7'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='#e5e7eb'"
          id="lbl_none">
          <input type="radio" name="cat_label_id" value="0"
            <?= empty($ec['cat_label_id']) ? 'checked' : '' ?>
            onchange="highlightLabel(0)"
            style="accent-color:#8829C8;">
          <span style="font-size:13px;font-weight:700;color:#6b7280;">بدون ليبل</span>
        </label>
        <?php foreach ($all_labels as $lbl): ?>
        <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:2px solid #e5e7eb;border-radius:10px;cursor:pointer;transition:border-color .15s;"
          id="lbl_<?= $lbl['label_id'] ?>"
          onmouseover="this.style.borderColor='<?= htmlspecialchars($lbl['label_color']) ?>'" onmouseout="if(!this.querySelector('input').checked)this.style.borderColor='#e5e7eb'">
          <input type="radio" name="cat_label_id" value="<?= $lbl['label_id'] ?>"
            <?= ($ec['cat_label_id']??0) == $lbl['label_id'] ? 'checked' : '' ?>
            onchange="highlightLabel(<?= $lbl['label_id'] ?>)"
            style="accent-color:<?= htmlspecialchars($lbl['label_color']) ?>;">
          <span style="display:flex;align-items:center;gap:6px;">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= htmlspecialchars($lbl['label_color']) ?>;flex-shrink:0;"></span>
            <span style="font-size:13px;font-weight:700;color:#374151;"><?= htmlspecialchars($lbl['label_name']) ?></span>
          </span>
        </label>
        <?php endforeach; ?>
      </div>
      <script>
      function highlightLabel(selected) {
        document.querySelectorAll('[id^="lbl_"]').forEach(el => {
          el.style.borderColor = '#e5e7eb';
          el.style.background = '#fff';
        });
        var el = document.getElementById('lbl_' + selected);
        if (el) { el.style.borderColor = '#8829C8'; el.style.background = '#faf5ff'; }
      }
      document.addEventListener('DOMContentLoaded', function() {
        var checked = document.querySelector('[name="cat_label_id"]:checked');
        if (checked) highlightLabel(checked.value);
      });
      </script>
      <?php endif; ?>
    </div>

    <div>
      <label class="form-label">الترتيب</label>
      <input type="number" name="cat_order" class="form-input" value="<?= $ec['cat_order']??0 ?>">
    </div>

    <div class="flex gap-3">
      <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk mr-2"></i>حفظ</button>
      <a href="admin.php?p=categories" class="btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<?php } else {
$cats = [];
$r = $mysqli->query("
  SELECT c.*, l.label_name, l.label_color,
    (SELECT COUNT(*) FROM pi_personality_categories pc WHERE pc.cat_id=c.cat_id) as p_count
  FROM pi_categories c
  LEFT JOIN pi_labels l ON c.cat_label_id = l.label_id
  WHERE c.cat_active=1
  ORDER BY c.cat_order, c.cat_id
");
if ($r) while ($row=$r->fetch_assoc()) $cats[] = $row;
?>
<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm">
  <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">التصنيفات (<?= count($cats) ?>)</h2>
  <?php if (pi_has_perm('add_category')): ?>
  <a href="admin.php?p=categories&action=add" class="btn-primary flex items-center gap-2">
    <i class="fa-solid fa-plus"></i> إضافة تصنيف
  </a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead>
      <tr>
        <th>التصنيف</th>
        <th>الأيقونة</th>
        <th>الليبل</th>
        <th>عدد الشخصيات</th>
        <th>الترتيب</th>
        <th>الإجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($cats as $cat): ?>
      <tr class="hover:bg-gray-50 transition">
        <td>
          <p class="font-bold text-gray-800"><?= htmlspecialchars($cat['cat_name']) ?></p>
          <?php if ($cat['cat_name_en']): ?>
          <p class="text-gray-400 text-xs" dir="ltr"><?= htmlspecialchars($cat['cat_name_en']) ?></p>
          <?php endif; ?>
        </td>
        <td>
          <div style="width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;">
            <i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?>" style="color:#fff;font-size:14px;"></i>
          </div>
        </td>
        <td>
          <?php if ($cat['label_name']): ?>
          <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;background:<?= htmlspecialchars($cat['label_color']) ?>22;color:<?= htmlspecialchars($cat['label_color']) ?>;">
            <span style="width:7px;height:7px;border-radius:50%;background:<?= htmlspecialchars($cat['label_color']) ?>;"></span>
            <?= htmlspecialchars($cat['label_name']) ?>
          </span>
          <?php else: ?>
          <span style="color:#d1d5db;font-size:13px;">—</span>
          <?php endif; ?>
        </td>
        <td class="font-semibold"><?= $cat['p_count'] ?></td>
        <td class="text-gray-500"><?= $cat['cat_order'] ?></td>
        <td>
          <div class="flex gap-2">
            <?php if (pi_has_perm('edit_category')): ?>
            <a href="admin.php?p=categories&action=edit&id=<?= $cat['cat_id'] ?>"
              class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition">
              <i class="fa-solid fa-pen text-xs"></i>
            </a>
            <?php endif; ?>
            <?php if (pi_has_perm('delete_category')): ?>
            <form method="POST" onsubmit="return confirm('حذف التصنيف؟')">
              <input type="hidden" name="action" value="delete_category">
              <input type="hidden" name="cat_id" value="<?= $cat['cat_id'] ?>">
              <button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition">
                <i class="fa-solid fa-trash text-xs"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($cats)): ?>
      <tr><td colspan="6" class="text-center py-12 text-gray-400">
        <i class="fa-solid fa-tags text-4xl mb-3 block"></i>لا توجد تصنيفات
      </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php } ?>
