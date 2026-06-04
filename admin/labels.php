<?php
pi_require_any_perm('view_categories','view_labels','add_label','edit_label','delete_label');

// Ensure table exists
$mysqli->query("CREATE TABLE IF NOT EXISTS pi_labels (
  label_id INT AUTO_INCREMENT PRIMARY KEY,
  label_name VARCHAR(100) NOT NULL,
  label_color VARCHAR(20) DEFAULT '#8829C8',
  label_order INT DEFAULT 0,
  label_active TINYINT(1) DEFAULT 1,
  label_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? 'list';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'save_label') {
        $id    = (int)($_POST['label_id'] ?? 0);
        $name  = pi_escape($_POST['label_name'] ?? '');
        $color = pi_escape($_POST['label_color'] ?? '#8829C8');
        $order = (int)($_POST['label_order'] ?? 0);

        if ($id) {
            pi_require_perm('edit_category');
            $mysqli->query("UPDATE pi_labels SET label_name='$name',label_color='$color',label_order=$order WHERE label_id=$id");
        } else {
            pi_require_perm('add_category');
            $mysqli->query("INSERT INTO pi_labels (label_name,label_color,label_order) VALUES ('$name','$color',$order)");
        }
        $msg = 'تم حفظ الليبل';
        $action = 'list';
    }

    if ($act === 'delete_label') {
        pi_require_perm('delete_category');
        $id = (int)($_POST['label_id'] ?? 0);
        $mysqli->query("UPDATE pi_labels SET label_active=0 WHERE label_id=$id");
        $msg = 'تم الحذف';
    }
}

if ($action === 'add' || $action === 'edit') {
    $el = null;
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_labels WHERE label_id=$eid");
        if ($r && $r->num_rows) $el = $r->fetch_assoc();
    }
?>
<div class="max-w-lg">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=labels" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-right text-lg"></i></a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة ليبل جديد':'تعديل الليبل' ?></h2>
  </div>
  <form method="POST" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_label">
    <?php if ($el): ?><input type="hidden" name="label_id" value="<?= $el['label_id'] ?>"><?php endif; ?>

    <div>
      <label class="form-label">اسم الليبل *</label>
      <input type="text" name="label_name" required class="form-input" placeholder="مثال: رائد أعمال، مبتكر..." value="<?= htmlspecialchars($el['label_name']??'') ?>">
    </div>

    <div>
      <label class="form-label">لون الليبل</label>
      <div class="flex items-center gap-3">
        <input type="color" name="label_color" id="lbl_color" value="<?= htmlspecialchars($el['label_color']??'#8829C8') ?>"
          class="w-14 h-11 border border-gray-200 rounded-xl p-1 cursor-pointer">
        <input type="text" id="lbl_hex" class="form-input flex-1" dir="ltr" value="<?= htmlspecialchars($el['label_color']??'#8829C8') ?>" readonly>
        <!-- Live preview -->
        <span id="lbl_preview" class="px-4 py-1.5 rounded-full text-white text-sm font-bold" style="background:<?= htmlspecialchars($el['label_color']??'#8829C8') ?>">
          <?= htmlspecialchars($el['label_name']??'معاينة') ?>
        </span>
      </div>
    </div>

    <div>
      <label class="form-label">الترتيب</label>
      <input type="number" name="label_order" class="form-input" value="<?= $el['label_order']??0 ?>">
    </div>

    <div class="flex gap-3">
      <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk mr-2"></i>حفظ</button>
      <a href="admin.php?p=labels" class="btn-secondary">إلغاء</a>
    </div>
  </form>
</div>
<script>
const lc = document.getElementById('lbl_color');
const lh = document.getElementById('lbl_hex');
const lp = document.getElementById('lbl_preview');
const ln = document.querySelector('[name=label_name]');
lc.addEventListener('input', function() {
  lh.value = this.value;
  lp.style.background = this.value;
});
ln?.addEventListener('input', function() { lp.textContent = this.value || 'معاينة'; });
</script>

<?php } else {
$labels = [];
$r = $mysqli->query("SELECT * FROM pi_labels WHERE label_active=1 ORDER BY label_order,label_id");
if ($r) while ($row=$r->fetch_assoc()) $labels[] = $row;
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm">
  <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h2 class="text-xl font-black text-gray-800">إعدادات الليبلات</h2>
    <p class="text-gray-400 text-sm mt-0.5">أنشئ ليبلات ملونة وأضفها للتصنيفات والشخصيات</p>
  </div>
  <?php if (pi_has_perm('add_category')): ?>
  <a href="admin.php?p=labels&action=add" class="btn-primary flex items-center gap-2">
    <i class="fa-solid fa-plus"></i> إضافة ليبل
  </a>
  <?php endif; ?>
</div>

<?php if (empty($labels)): ?>
<div class="bg-white rounded-2xl shadow-sm p-12 text-center text-gray-400">
  <i class="fa-solid fa-tags text-5xl mb-4 block"></i>
  <p class="font-bold text-lg mb-2">لا توجد ليبلات بعد</p>
  <p class="text-sm mb-5">الليبلات بتساعدك تصنّف الشخصيات والتصنيفات بشكل أوضح</p>
  <a href="admin.php?p=labels&action=add" class="btn-primary inline-flex items-center gap-2">
    <i class="fa-solid fa-plus"></i> أضف أول ليبل
  </a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
  <?php foreach ($labels as $lbl): ?>
  <div class="bg-white rounded-2xl shadow-sm p-5 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <span class="px-4 py-1.5 rounded-full text-white text-sm font-bold"
        style="background:<?= htmlspecialchars($lbl['label_color']) ?>">
        <?= htmlspecialchars($lbl['label_name']) ?>
      </span>
      <span class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($lbl['label_color']) ?></span>
    </div>
    <div class="flex gap-1">
      <?php if (pi_has_perm('edit_category')): ?>
      <a href="admin.php?p=labels&action=edit&id=<?= $lbl['label_id'] ?>"
        class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition">
        <i class="fa-solid fa-pen text-xs"></i>
      </a>
      <?php endif; ?>
      <?php if (pi_has_perm('delete_category')): ?>
      <form method="POST" onsubmit="return confirm('حذف الليبل؟')">
        <input type="hidden" name="action" value="delete_label">
        <input type="hidden" name="label_id" value="<?= $lbl['label_id'] ?>">
        <button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition">
          <i class="fa-solid fa-trash text-xs"></i>
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php } ?>
