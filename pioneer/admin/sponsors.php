<?php
pi_require_perm('view_sponsors');
$action = $_GET['action'] ?? 'list';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'save_sponsor') {
        pi_require_perm('manage_sponsors');
        $id    = (int)($_POST['sp_id'] ?? 0);
        $name  = pi_escape($_POST['sp_name'] ?? '');
        $logo  = pi_escape($_POST['sp_logo'] ?? '');
        $url   = pi_escape($_POST['sp_url'] ?? '');
        $order = (int)($_POST['sp_order'] ?? 0);
        if ($id) $mysqli->query("UPDATE pi_sponsors SET sp_name='$name',sp_logo='$logo',sp_url='$url',sp_order=$order WHERE sp_id=$id");
        else $mysqli->query("INSERT INTO pi_sponsors (sp_name,sp_logo,sp_url,sp_order) VALUES ('$name','$logo','$url',$order)");
        $msg = 'تم الحفظ'; $action = 'list';
    }
    if ($act === 'delete_sponsor') {
        pi_require_perm('manage_sponsors');
        $id = (int)($_POST['sp_id'] ?? 0);
        $mysqli->query("DELETE FROM pi_sponsors WHERE sp_id=$id");
        $msg = 'تم الحذف';
    }
    if ($act === 'toggle_sponsor') {
        pi_require_perm('manage_sponsors');
        $id = (int)($_POST['sp_id'] ?? 0);
        $mysqli->query("UPDATE pi_sponsors SET sp_active=!sp_active WHERE sp_id=$id");
    }
}

if ($action === 'add' || $action === 'edit') {
    $es = null;
    if ($action === 'edit') { $eid=(int)($_GET['id']??0); $r=$mysqli->query("SELECT * FROM pi_sponsors WHERE sp_id=$eid"); if($r&&$r->num_rows) $es=$r->fetch_assoc(); }
?>
<div class="max-w-xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=sponsors" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-right text-lg"></i></a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة راعي':'تعديل الراعي' ?></h2>
  </div>
  <form method="POST" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_sponsor">
    <?php if ($es): ?><input type="hidden" name="sp_id" value="<?= $es['sp_id'] ?>"><?php endif; ?>
    <div><label class="form-label">اسم الشركة *</label><input type="text" name="sp_name" required class="form-input" value="<?= htmlspecialchars($es['sp_name']??'') ?>"></div>
    <div><label class="form-label">رابط الشعار</label><input type="url" name="sp_logo" class="form-input" dir="ltr" value="<?= htmlspecialchars($es['sp_logo']??'') ?>"></div>
    <div><label class="form-label">رابط الموقع</label><input type="url" name="sp_url" class="form-input" dir="ltr" value="<?= htmlspecialchars($es['sp_url']??'') ?>"></div>
    <div><label class="form-label">الترتيب</label><input type="number" name="sp_order" class="form-input" value="<?= $es['sp_order']??0 ?>"></div>
    <div class="flex gap-3"><button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk mr-2"></i>حفظ</button><a href="admin.php?p=sponsors" class="btn-secondary">إلغاء</a></div>
  </form>
</div>
<?php } else {
$list = [];
$r = $mysqli->query("SELECT * FROM pi_sponsors ORDER BY sp_order,sp_id");
if ($r) while ($row=$r->fetch_assoc()) $list[] = $row;
?>
<?php if ($msg): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">الرعاة (<?= count($list) ?>)</h2>
  <?php if (pi_has_perm('manage_sponsors')): ?>
  <a href="admin.php?p=sponsors&action=add" class="btn-primary flex items-center gap-2"><i class="fa-solid fa-plus"></i> إضافة راعي</a>
  <?php endif; ?>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
  <?php foreach ($list as $sp): ?>
  <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-col gap-3">
    <?php if ($sp['sp_logo']): ?><img src="<?= htmlspecialchars($sp['sp_logo']) ?>" class="h-12 object-contain"><?php else: ?><div class="h-12 flex items-center"><i class="fa-solid fa-handshake text-gray-300 text-3xl"></i></div><?php endif; ?>
    <p class="font-bold text-gray-800"><?= htmlspecialchars($sp['sp_name']) ?></p>
    <div class="flex gap-2 mt-auto">
      <form method="POST" class="inline"><input type="hidden" name="action" value="toggle_sponsor"><input type="hidden" name="sp_id" value="<?= $sp['sp_id'] ?>">
        <button type="submit" class="<?= $sp['sp_active']?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?> px-3 py-1 rounded-full text-xs font-bold hover:opacity-80 transition"><?= $sp['sp_active']?'نشط':'معطل' ?></button>
      </form>
      <?php if (pi_has_perm('manage_sponsors')): ?>
      <a href="admin.php?p=sponsors&action=edit&id=<?= $sp['sp_id'] ?>" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition"><i class="fa-solid fa-pen text-xs"></i></a>
      <form method="POST" onsubmit="return confirm('حذف؟')"><input type="hidden" name="action" value="delete_sponsor"><input type="hidden" name="sp_id" value="<?= $sp['sp_id'] ?>"><button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-trash text-xs"></i></button></form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($list)): ?><div class="col-span-3 text-center py-16 text-gray-400"><i class="fa-solid fa-handshake text-5xl mb-4 block"></i>لا يوجد رعاة بعد</div><?php endif; ?>
</div>
<?php } ?>
