<?php
pi_require_any_perm('view_sponsors','manage_sponsors','add_sponsor','edit_sponsor','delete_sponsor');
$action = $_GET['action'] ?? 'list';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // Edit sponsor bonus views directly
    if ($act === 'edit_sp_views') {
        pi_require_any_perm('manage_sponsors','edit_sponsor');
        $sid   = (int)($_POST['sp_id'] ?? 0);
        $views = max(0, (int)($_POST['sp_views'] ?? 0));
        if ($sid) {
            try { $mysqli->query("ALTER TABLE pi_sponsors ADD COLUMN sp_views INT DEFAULT 0"); } catch(Exception $e) {}
            $mysqli->query("UPDATE pi_sponsors SET sp_views=$views WHERE sp_id=$sid");
        }
        $msg = 'تم تحديث عدد المشاهدات';
        $action = 'list';
    }

    // Edit views for a specific list linked to this sponsor
    if ($act === 'edit_views') {
        pi_require_any_perm('manage_sponsors','edit_sponsor');
        $lid   = (int)($_POST['list_id'] ?? 0);
        $views = max(0, (int)($_POST['list_views'] ?? 0));
        if ($lid) {
            $mysqli->query("UPDATE pi_lists SET list_views=$views WHERE list_id=$lid");
            // Sync pi_visit_daily: set today's count to reflect adjustment
            $existing = 0;
            $re = $mysqli->query("SELECT COALESCE(SUM(vd_count),0) c FROM pi_visit_daily WHERE vd_page='list/$lid'");
            if ($re) $existing = (int)$re->fetch_assoc()['c'];
            $diff = $views - $existing;
            if ($diff > 0) {
                $mysqli->query("INSERT INTO pi_visit_daily (vd_page,vd_date,vd_count) VALUES ('list/$lid',CURDATE(),$diff) ON DUPLICATE KEY UPDATE vd_count=vd_count+$diff");
            } elseif ($diff < 0) {
                $abs = abs($diff);
                $mysqli->query("UPDATE pi_visit_daily SET vd_count=GREATEST(1,vd_count-$abs) WHERE vd_page='list/$lid' AND vd_date=CURDATE()");
            }
        }
        $msg = 'تم تحديث عدد المشاهدات';
        $action = 'list';
    }

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
      <div class="pi-upload-zone <?= !empty($es['sp_logo']) ? 'has-preview' : '' ?>" onclick="document.getElementById('sp_logo_file').click()">
        <input type="file" id="sp_logo_file" name="sp_logo_file" accept="image/jpeg,image/png,image/webp" class="hidden" data-preview="sp_logo_prev" data-placeholder="sp_logo_placeholder">
        <div id="sp_logo_placeholder" <?= !empty($es['sp_logo']) ? 'style="display:none"' : '' ?>>
          <div style="width:52px;height:52px;border-radius:14px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;"><i class="fa-solid fa-camera" style="font-size:20px;color:#9ca3af;"></i></div>
          <p style="font-size:13px;font-weight:800;color:#374151;margin-bottom:3px;">اضغط لرفع صورة</p>
          <p style="font-size:11px;color:#9ca3af;">JPG, PNG, WebP — حتى 5MB</p>
        </div>
        <img id="sp_logo_prev" src="<?= htmlspecialchars($es['sp_logo']??'') ?>" class="<?= !empty($es['sp_logo']) ? '' : 'hidden' ?>" style="width:90px;height:90px;object-fit:cover;border-radius:12px;margin:0 auto;display:<?= !empty($es['sp_logo']) ? 'block' : 'none' ?>;border:2px solid #e9d5ff;">
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
    var img = document.getElementById('sp_logo_prev');
    if (img._objUrl) URL.revokeObjectURL(img._objUrl);
    img._objUrl = URL.createObjectURL(input.files[0]);
    img.src = img._objUrl;
    img.style.display = 'block';
    var ph = document.getElementById('sp_logo_placeholder');
    if (ph) ph.style.display = 'none';
    var hint = document.getElementById('sp_logo_hint');
    if (hint) hint.textContent = 'اضغط لتغيير الشعار';
  }
}
</script>

<?php } else {
$list = [];
try { $mysqli->query("ALTER TABLE pi_sponsors ADD COLUMN sp_views INT DEFAULT 0"); } catch(Exception $e) {}
$r = $mysqli->query("SELECT s.*, u.u_name AS linked_user_name,
    COALESCE(s.sp_views,0) AS total_views
    FROM pi_sponsors s LEFT JOIN pi_users u ON s.sp_user_id=u.u_id ORDER BY s.sp_order,s.sp_id");
if ($r) while ($row=$r->fetch_assoc()) {
    // Fetch linked lists for this sponsor
    $row['_lists'] = [];
    $lr = $mysqli->query("SELECT list_id, list_title, list_views FROM pi_lists WHERE list_sponsor_id=".(int)$row['sp_id']." ORDER BY list_id");
    if ($lr) while ($lr_row=$lr->fetch_assoc()) $row['_lists'][] = $lr_row;
    $list[] = $row;
}
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
<div class="space-y-4">
  <?php foreach ($list as $sp): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Sponsor Header -->
    <div class="flex items-center gap-4 p-5">
      <?php if ($sp['sp_logo']): ?>
        <img src="<?= htmlspecialchars($sp['sp_logo']) ?>" style="width:64px;height:64px;object-fit:contain;border-radius:14px;border:1px solid #f0f0f0;flex-shrink:0;background:#fafafa;">
      <?php else: ?>
        <div style="width:64px;height:64px;border-radius:14px;background:linear-gradient(135deg,#7c3aed,#4c1d95);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fa-solid fa-handshake text-white text-2xl"></i>
        </div>
      <?php endif; ?>

      <div class="flex-1 min-w-0">
        <p class="font-black text-gray-800 text-base"><?= htmlspecialchars($sp['sp_name']) ?></p>
        <?php if (!empty($sp['linked_user_name'])): ?>
        <p class="text-xs text-purple-600 font-semibold mt-1"><i class="fa-solid fa-user-link ml-1"></i><?= htmlspecialchars($sp['linked_user_name']) ?></p>
        <?php endif; ?>
      </div>

      <!-- View stats -->
      <div class="flex gap-3 items-center" x-data="{editing:false}">
        <div class="text-center px-4 py-2 bg-purple-50 rounded-xl">
          <p class="text-xl font-black text-purple-700"><?= number_format((int)$sp['total_views']) ?></p>
          <p class="text-xs text-purple-400 font-semibold">إجمالي</p>
        </div>
        <div class="text-center px-4 py-2 bg-blue-50 rounded-xl">
          <p class="text-xl font-black text-blue-700"><?= number_format((int)$sp['views_30d']) ?></p>
          <p class="text-xs text-blue-400 font-semibold">30 يوم</p>
        </div>
        <!-- Inline views editor -->
        <div>
          <button type="button" @click="editing=!editing"
            class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-purple-50 hover:text-purple-600 text-gray-400 flex items-center justify-center transition"
            title="تعديل المشاهدات">
            <i class="fa-solid fa-pen text-xs"></i>
          </button>
          <div x-show="editing" x-cloak class="absolute z-20 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg p-4 w-56">
            <p class="text-xs font-black text-gray-600 mb-2">تعديل مشاهدات الراعي</p>
            <form method="POST" class="flex gap-2">
              <input type="hidden" name="action" value="edit_sp_views">
              <input type="hidden" name="sp_id" value="<?= $sp['sp_id'] ?>">
              <input type="number" name="sp_views" value="<?= (int)($sp['sp_views']??0) ?>" min="0"
                class="flex-1 border border-gray-200 rounded-lg px-2 py-1.5 text-sm font-bold text-center outline-none focus:border-purple-400">
              <button type="submit" class="px-3 py-1.5 bg-purple-600 text-white text-xs font-bold rounded-lg hover:bg-purple-700 transition">حفظ</button>
            </form>
            <p class="text-xs text-gray-400 mt-2">هذا الرقم يُضاف لمشاهدات القوائم</p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-2 items-center">
        <form method="POST" class="inline">
          <input type="hidden" name="action" value="toggle_sponsor">
          <input type="hidden" name="sp_id" value="<?= $sp['sp_id'] ?>">
          <button type="submit" class="<?= $sp['sp_active']?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500' ?> px-3 py-1.5 rounded-xl text-xs font-bold hover:opacity-80 transition">
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

    <!-- Linked lists with editable views -->
    <?php if (!empty($sp['_lists'])): ?>
    <div class="border-t border-gray-50 px-5 pb-4">
      <p class="text-xs font-black text-gray-400 uppercase tracking-widest py-3">القوائم المرتبطة</p>
      <div class="space-y-2">
        <?php foreach ($sp['_lists'] as $lst): ?>
        <div class="flex items-center gap-3 bg-gray-50 rounded-xl px-4 py-3">
          <i class="fa-solid fa-list text-gray-300 text-sm flex-shrink-0"></i>
          <a href="list.php?id=<?= $lst['list_id'] ?>" target="_blank"
            class="flex-1 text-sm font-bold text-gray-700 hover:text-purple-600 transition truncate">
            <?= htmlspecialchars($lst['list_title']) ?>
          </a>
          <form method="POST" class="flex items-center gap-2">
            <input type="hidden" name="action" value="edit_views">
            <input type="hidden" name="list_id" value="<?= $lst['list_id'] ?>">
            <i class="fa-solid fa-eye text-gray-400 text-xs"></i>
            <input type="number" name="list_views" value="<?= (int)$lst['list_views'] ?>" min="0"
              style="width:80px;border:1.5px solid #e5e7eb;border-radius:8px;padding:4px 8px;font-size:13px;font-weight:800;text-align:center;font-family:inherit;outline:none;"
              onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e5e7eb'">
            <button type="submit"
              style="padding:4px 12px;background:#f3e8ff;color:#7c3aed;border:none;border-radius:8px;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;transition:background .15s;"
              onmouseover="this.style.background='#ede9fe'" onmouseout="this.style.background='#f3e8ff'">
              حفظ
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (empty($list)): ?>
  <div class="text-center py-16 text-gray-400">
    <i class="fa-solid fa-handshake text-5xl mb-4 block opacity-20"></i>
    <p class="font-semibold">لا يوجد رعاة بعد</p>
  </div>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="fixed bottom-6 left-6 bg-green-600 text-white px-5 py-3 rounded-2xl shadow-xl font-bold text-sm z-50 flex items-center gap-2">
  <i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($msg) ?>
</div>
<script>setTimeout(function(){ document.querySelector('.fixed.bottom-6')?.remove(); }, 3000);</script>
<?php endif; ?>
<?php } ?>
