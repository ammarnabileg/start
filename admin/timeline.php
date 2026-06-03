<?php
pi_require_perm('view_timeline');
$action = $_GET['action'] ?? 'list';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'save_timeline') {
        $id    = (int)($_POST['tl_id'] ?? 0);
        $p_id  = (int)($_POST['tl_p_id'] ?? 0);
        $type  = pi_escape($_POST['tl_type'] ?? 'work');
        $title = pi_escape($_POST['tl_title'] ?? '');
        $inst  = pi_escape($_POST['tl_institution'] ?? '');
        $inst_id = (int)($_POST['tl_institution_id'] ?? 0);
        $y_start = pi_escape($_POST['tl_year_start'] ?? '');
        $y_end   = pi_escape($_POST['tl_year_end'] ?? '');
        $order   = (int)($_POST['tl_order'] ?? 0);

        if ($id) {
            pi_require_perm('manage_timeline');
            $mysqli->query("UPDATE pi_timeline SET tl_p_id=$p_id,tl_type='$type',tl_title='$title',tl_institution='$inst',tl_institution_id=".($inst_id?:0).",tl_year_start='$y_start',tl_year_end='$y_end',tl_order=$order WHERE tl_id=$id");
        } else {
            pi_require_perm('manage_timeline');
            $mysqli->query("INSERT INTO pi_timeline (tl_p_id,tl_type,tl_title,tl_institution,tl_institution_id,tl_year_start,tl_year_end,tl_order) VALUES ($p_id,'$type','$title','$inst',".($inst_id?:0).",'$y_start','$y_end',$order)");
        }
        $msg = 'تم الحفظ'; $action = 'list';
    }
    if ($act === 'delete_timeline') {
        pi_require_perm('manage_timeline');
        $id = (int)($_POST['tl_id'] ?? 0);
        $mysqli->query("DELETE FROM pi_timeline WHERE tl_id=$id");
        $msg = 'تم الحذف';
    }
}

$personalities_list = [];
$r = $mysqli->query("SELECT p_id,p_name_ar FROM pi_personalities WHERE p_active=1 ORDER BY p_name_ar");
if ($r) while ($row=$r->fetch_assoc()) $personalities_list[] = $row;

if ($action === 'add' || $action === 'edit') {
    $et = null;
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_timeline WHERE tl_id=$eid");
        if ($r && $r->num_rows) $et = $r->fetch_assoc();
    }
?>
<div class="max-w-xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=timeline" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-right text-lg"></i></a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة محطة زمنية':'تعديل المحطة' ?></h2>
  </div>
  <form method="POST" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_timeline">
    <?php if ($et): ?><input type="hidden" name="tl_id" value="<?= $et['tl_id'] ?>"><?php endif; ?>
    <div><label class="form-label">الشخصية</label>
    <select name="tl_p_id" class="form-input">
      <option value="">— اختر شخصية —</option>
      <?php foreach ($personalities_list as $pl): ?>
      <option value="<?= $pl['p_id'] ?>" <?= ($et['tl_p_id']??0)==$pl['p_id']?'selected':'' ?>><?= htmlspecialchars($pl['p_name_ar']) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div><label class="form-label">النوع</label>
    <select name="tl_type" class="form-input">
      <option value="education" <?= ($et['tl_type']??'')==='education'?'selected':'' ?>>تعليم</option>
      <option value="work" <?= ($et['tl_type']??'work')==='work'?'selected':'' ?>>عمل</option>
    </select></div>
    <div><label class="form-label">العنوان (الشهادة / المنصب) *</label>
    <input type="text" name="tl_title" required class="form-input" value="<?= htmlspecialchars($et['tl_title']??'') ?>"></div>
    <div><label class="form-label">المؤسسة / الجهة</label>
    <input type="text" name="tl_institution" class="form-input" value="<?= htmlspecialchars($et['tl_institution']??'') ?>"></div>
    <div class="grid grid-cols-2 gap-4">
      <div><label class="form-label">سنة البداية</label>
      <input type="text" name="tl_year_start" class="form-input" placeholder="1990" value="<?= htmlspecialchars($et['tl_year_start']??'') ?>"></div>
      <div><label class="form-label">سنة الانتهاء</label>
      <input type="text" name="tl_year_end" class="form-input" placeholder="الآن" value="<?= htmlspecialchars($et['tl_year_end']??'') ?>"></div>
    </div>
    <div><label class="form-label">الترتيب</label>
    <input type="number" name="tl_order" class="form-input" value="<?= $et['tl_order']??0 ?>"></div>
    <div class="flex gap-3"><button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk mr-2"></i>حفظ</button>
    <a href="admin.php?p=timeline" class="btn-secondary">إلغاء</a></div>
  </form>
</div>
<?php } else {
$list = [];
$r = $mysqli->query("SELECT t.*,p.p_name_ar FROM pi_timeline t LEFT JOIN pi_personalities p ON t.tl_p_id=p.p_id ORDER BY p.p_name_ar,t.tl_type,t.tl_year_start DESC LIMIT 100");
if ($r) while ($row=$r->fetch_assoc()) $list[] = $row;
?>
<?php if ($msg): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">المحطات الزمنية (<?= count($list) ?>)</h2>
  <?php if (pi_has_perm('manage_timeline')): ?>
  <a href="admin.php?p=timeline&action=add" class="btn-primary flex items-center gap-2"><i class="fa-solid fa-plus"></i> إضافة محطة</a>
  <?php endif; ?>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead><tr><th>الشخصية</th><th>النوع</th><th>العنوان</th><th>المؤسسة</th><th>السنوات</th><th>الإجراءات</th></tr></thead>
    <tbody>
      <?php foreach ($list as $tl): ?>
      <tr class="hover:bg-gray-50 transition">
        <td class="font-semibold text-gray-800"><?= htmlspecialchars($tl['p_name_ar']??'—') ?></td>
        <td><span class="px-2 py-0.5 <?= $tl['tl_type']==='education'?'bg-blue-100 text-blue-700':'bg-purple-100 text-purple-800' ?> rounded-full text-xs font-bold"><?= $tl['tl_type']==='education'?'تعليم':'عمل' ?></span></td>
        <td class="text-gray-700"><?= htmlspecialchars($tl['tl_title']) ?></td>
        <td class="text-gray-500 text-xs"><?= htmlspecialchars($tl['tl_institution']??'—') ?></td>
        <td class="text-gray-400 text-xs"><?= $tl['tl_year_start'] ?><?= $tl['tl_year_end']?' — '.$tl['tl_year_end']:'' ?></td>
        <td><div class="flex gap-2">
          <?php if (pi_has_perm('manage_timeline')): ?>
          <a href="admin.php?p=timeline&action=edit&id=<?= $tl['tl_id'] ?>" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition"><i class="fa-solid fa-pen text-xs"></i></a>
          <form method="POST" onsubmit="return confirm('حذف؟')"><input type="hidden" name="action" value="delete_timeline"><input type="hidden" name="tl_id" value="<?= $tl['tl_id'] ?>"><button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-trash text-xs"></i></button></form>
          <?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?><tr><td colspan="6" class="text-center py-12 text-gray-400"><i class="fa-solid fa-timeline text-4xl mb-3 block"></i>لا توجد محطات زمنية</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php } ?>
