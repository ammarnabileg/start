<?php
pi_require_any_perm('view_sponsors','manage_sponsors','add_sponsor','edit_sponsor','delete_sponsor');
$action = $_GET['action'] ?? 'list';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'save_sponsor') {
        pi_require_any_perm('manage_sponsors','add_sponsor','edit_sponsor');
        $id    = (int)($_POST['sp_id'] ?? 0);
        $name  = pi_escape($_POST['sp_name'] ?? '');
        $url   = pi_escape($_POST['sp_url'] ?? '');
        $order = (int)($_POST['sp_order'] ?? 0);

        // Handle logo upload
        $logo = pi_escape($_POST['sp_logo_current'] ?? '');
        if (!empty($_FILES['sp_logo_file']['name']) && $_FILES['sp_logo_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['sp_logo_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'])) {
                $uploads_dir = dirname(__DIR__) . '/uploads/';
                if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
                $fname = 'sp_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploads_dir . $fname)) {
                    // Resize to max 300x300 if GD available and not SVG
                    if ($ext !== 'svg' && function_exists('imagecreatefromstring')) {
                        $src_path = $uploads_dir . $fname;
                        $img_data = file_get_contents($src_path);
                        $src = @imagecreatefromstring($img_data);
                        if ($src) {
                            $ow = imagesx($src); $oh = imagesy($src);
                            $max = 300;
                            if ($ow > $max || $oh > $max) {
                                $ratio = min($max/$ow, $max/$oh);
                                $nw = (int)round($ow * $ratio);
                                $nh = (int)round($oh * $ratio);
                                $dst = imagecreatetruecolor($nw, $nh);
                                // Preserve transparency
                                imagealphablending($dst, false);
                                imagesavealpha($dst, true);
                                $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                                imagefilledrectangle($dst, 0, 0, $nw, $nh, $trans);
                                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
                                if (in_array($ext, ['png','webp'])) imagepng($dst, $src_path);
                                else imagejpeg($dst, $src_path, 92);
                                imagedestroy($dst);
                            }
                            imagedestroy($src);
                        }
                    }
                    $logo = pi_escape('uploads/' . $fname);
                }
            }
        }

        $sp_user_id = (int)($_POST['sp_user_id'] ?? 0);
        $sp_user_id_sql = $sp_user_id ? $sp_user_id : 'NULL';
        if ($id) $mysqli->query("UPDATE pi_sponsors SET sp_name='$name',sp_logo='$logo',sp_url='$url',sp_order=$order,sp_user_id=$sp_user_id_sql WHERE sp_id=$id");
        else      $mysqli->query("INSERT INTO pi_sponsors (sp_name,sp_logo,sp_url,sp_order,sp_user_id) VALUES ('$name','$logo','$url',$order,$sp_user_id_sql)");
        $msg = 'تم الحفظ'; $action = 'list';
    }
    if ($act === 'delete_sponsor') {
        pi_require_any_perm('manage_sponsors','delete_sponsor');
        $id = (int)($_POST['sp_id'] ?? 0);
        $mysqli->query("DELETE FROM pi_sponsors WHERE sp_id=$id");
        $msg = 'تم الحذف';
    }
    if ($act === 'toggle_sponsor') {
        pi_require_any_perm('manage_sponsors','edit_sponsor');
        $id = (int)($_POST['sp_id'] ?? 0);
        $mysqli->query("UPDATE pi_sponsors SET sp_active=!sp_active WHERE sp_id=$id");
    }
}

if ($action === 'add' || $action === 'edit') {
    $es = null;
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_sponsors WHERE sp_id=$eid");
        if ($r && $r->num_rows) $es = $r->fetch_assoc();
    }
?>
<div class="max-w-xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=sponsors" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-right text-lg"></i></a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة راعي':'تعديل الراعي' ?></h2>
  </div>
  <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_sponsor">
    <input type="hidden" name="sp_logo_current" value="<?= htmlspecialchars($es['sp_logo']??'') ?>">
    <?php if ($es): ?><input type="hidden" name="sp_id" value="<?= $es['sp_id'] ?>"><?php endif; ?>

    <div>
      <label class="form-label">اسم الشركة *</label>
      <input type="text" name="sp_name" required class="form-input" value="<?= htmlspecialchars($es['sp_name']??'') ?>">
    </div>

    <div>
      <label class="form-label">شعار الشركة <span class="text-gray-400 font-normal text-xs">(حد أقصى 300×300 بكسل)</span></label>
      <div style="border:2px dashed #e5e7eb;border-radius:14px;padding:20px;text-align:center;cursor:pointer;background:#fafafa;transition:border-color .2s,background .2s;"
        id="sp_drop_zone" onclick="document.getElementById('sp_logo_file').click()"
        onmouseover="this.style.borderColor='#8829C8';this.style.background='#f5f0ff'"
        onmouseout="this.style.borderColor='#e5e7eb';this.style.background='#fafafa'">
        <input type="file" id="sp_logo_file" name="sp_logo_file" accept="image/*" class="hidden" data-preview="sp_logo_prev" data-placeholder="sp_logo_placeholder">
        <?php if (!empty($es['sp_logo'])): ?>
          <img id="sp_logo_prev" src="<?= htmlspecialchars($es['sp_logo']) ?>"
            style="max-width:200px;max-height:120px;object-fit:contain;margin:0 auto 10px;display:block;border-radius:10px;">
          <p id="sp_logo_hint" style="font-size:12px;color:#9ca3af;">اضغط لتغيير الشعار</p>
        <?php else: ?>
          <img id="sp_logo_prev" style="max-width:200px;max-height:120px;object-fit:contain;margin:0 auto 10px;display:none;border-radius:10px;">
          <div id="sp_logo_placeholder">
            <div style="width:48px;height:48px;border-radius:12px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
              <i class="fa-solid fa-image" style="color:#9ca3af;font-size:20px;"></i>
            </div>
            <p style="font-size:14px;font-weight:700;color:#6b7280;">اضغط لرفع الشعار</p>
            <p style="font-size:12px;color:#9ca3af;margin-top:4px;">PNG, JPG, SVG, WebP</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <label class="form-label">رابط الموقع</label>
      <input type="url" name="sp_url" class="form-input" dir="ltr" value="<?= htmlspecialchars($es['sp_url']??'') ?>">
    </div>
    <div>
      <label class="form-label">الترتيب</label>
      <input type="number" name="sp_order" class="form-input" value="<?= $es['sp_order']??0 ?>">
    </div>
    <div>
      <label class="form-label">ربط بمستخدم <span class="text-gray-400 font-normal text-xs">(اختياري)</span></label>
      <?php
      $sp_users = [];
      $spur = $mysqli->query("SELECT u_id, u_name, u_email FROM pi_users ORDER BY u_name LIMIT 200");
      if ($spur) while ($row=$spur->fetch_assoc()) $sp_users[] = $row;
      ?>
      <select name="sp_user_id" class="form-input">
        <option value="0">— بدون ربط —</option>
        <?php foreach ($sp_users as $pu): ?>
        <option value="<?= $pu['u_id'] ?>" <?= ($es['sp_user_id']??0)==$pu['u_id']?'selected':'' ?>>
          <?= htmlspecialchars($pu['u_name'].' ('.$pu['u_email'].')') ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex gap-3">
      <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk mr-2"></i>حفظ</button>
      <a href="admin.php?p=sponsors" class="btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<script>
function previewSpLogo(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById('sp_logo_prev');
      img.src = e.target.result;
      img.style.display = 'block';
      var ph = document.getElementById('sp_logo_placeholder');
      if (ph) ph.style.display = 'none';
      var hint = document.getElementById('sp_logo_hint');
      if (hint) hint.textContent = 'اضغط لتغيير الشعار';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php } else {
$list = [];
$r = $mysqli->query("SELECT s.*, u.u_name AS linked_user_name FROM pi_sponsors s LEFT JOIN pi_users u ON s.sp_user_id=u.u_id ORDER BY s.sp_order,s.sp_id");
if ($r) while ($row=$r->fetch_assoc()) $list[] = $row;
?>
<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm">
  <i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">الرعاة (<?= count($list) ?>)</h2>
  <?php if (pi_has_any_perm('manage_sponsors','add_sponsor','edit_sponsor','delete_sponsor')): ?>
  <a href="admin.php?p=sponsors&action=add" class="btn-primary flex items-center gap-2"><i class="fa-solid fa-plus"></i> إضافة راعي</a>
  <?php endif; ?>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
  <?php foreach ($list as $sp): ?>
  <div class="bg-white rounded-2xl shadow-sm p-5 flex flex-col gap-3">
    <?php if ($sp['sp_logo']): ?>
      <img src="<?= htmlspecialchars($sp['sp_logo']) ?>"
        style="max-width:300px;max-height:300px;width:auto;height:auto;object-fit:contain;display:block;">
    <?php else: ?>
      <div style="height:48px;display:flex;align-items:center;">
        <i class="fa-solid fa-handshake text-gray-300 text-3xl"></i>
      </div>
    <?php endif; ?>
    <p class="font-bold text-gray-800"><?= htmlspecialchars($sp['sp_name']) ?></p>
    <?php if (!empty($sp['linked_user_name'])): ?>
    <p class="text-xs text-purple-600 font-semibold"><i class="fa-solid fa-user-link ml-1"></i><?= htmlspecialchars($sp['linked_user_name']) ?></p>
    <?php endif; ?>
    <div class="flex gap-2 mt-auto">
      <form method="POST" class="inline">
        <input type="hidden" name="action" value="toggle_sponsor">
        <input type="hidden" name="sp_id" value="<?= $sp['sp_id'] ?>">
        <button type="submit" class="<?= $sp['sp_active']?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?> px-3 py-1 rounded-full text-xs font-bold hover:opacity-80 transition">
          <?= $sp['sp_active']?'نشط':'معطل' ?>
        </button>
      </form>
      <?php if (pi_has_any_perm('manage_sponsors','add_sponsor','edit_sponsor','delete_sponsor')): ?>
      <a href="admin.php?p=sponsors&action=edit&id=<?= $sp['sp_id'] ?>"
        class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition">
        <i class="fa-solid fa-pen text-xs"></i>
      </a>
      <form method="POST" onsubmit="return confirm('حذف؟')">
        <input type="hidden" name="action" value="delete_sponsor">
        <input type="hidden" name="sp_id" value="<?= $sp['sp_id'] ?>">
        <button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition">
          <i class="fa-solid fa-trash text-xs"></i>
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($list)): ?>
  <div class="col-span-3 text-center py-16 text-gray-400">
    <i class="fa-solid fa-handshake text-5xl mb-4 block"></i>لا يوجد رعاة بعد
  </div>
  <?php endif; ?>
</div>
<?php } ?>
