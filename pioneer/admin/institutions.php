<?php
pi_require_perm('view_institutions');
$action = $_GET['action'] ?? 'list';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'save_institution') {
        $id       = (int)($_POST['inst_id'] ?? 0);
        $name_ar  = pi_escape($_POST['inst_name_ar'] ?? '');
        $name_en  = pi_escape($_POST['inst_name_en'] ?? '');
        $logo     = pi_escape($_POST['inst_logo'] ?? '');
        if (!empty($_FILES['inst_logo_file']['name']) && $_FILES['inst_logo_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['inst_logo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'])) {
                $udir = dirname(__DIR__).'/uploads/';
                if (!is_dir($udir)) mkdir($udir,0755,true);
                $fname = 'inst_'.time().'_'.rand(100,999).'.'.$ext;
                if (move_uploaded_file($_FILES['inst_logo_file']['tmp_name'], $udir.$fname))
                    $logo = pi_escape('uploads/'.$fname);
            }
        }
        $desc     = pi_escape($_POST['inst_description'] ?? '');
        $verified   = (int)($_POST['inst_verified'] ?? 0);
        $country_id = (int)($_POST['inst_country_id'] ?? 0);

        if ($id) {
            pi_require_perm('edit_institution');
            $mysqli->query("UPDATE pi_institutions SET inst_name_ar='$name_ar',inst_name_en='$name_en',inst_logo='$logo',inst_description='$desc',inst_verified=$verified,inst_country_id=$country_id WHERE inst_id=$id");
        } else {
            pi_require_perm('add_institution');
            $mysqli->query("INSERT INTO pi_institutions (inst_name_ar,inst_name_en,inst_logo,inst_description,inst_verified,inst_country_id) VALUES ('$name_ar','$name_en','$logo','$desc',$verified,$country_id)");
        }
        $msg = 'تم الحفظ'; $action = 'list';
    }
    if ($act === 'delete_institution') {
        pi_require_perm('delete_institution');
        $id = (int)($_POST['inst_id'] ?? 0);
        $mysqli->query("UPDATE pi_institutions SET inst_active=0 WHERE inst_id=$id");
        $msg = 'تم الحذف';
    }
}

if ($action === 'add' || $action === 'edit') {
    $ei = null;
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_id=$eid");
        if ($r && $r->num_rows) $ei = $r->fetch_assoc();
    }
?>
<div class="max-w-xl">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=institutions" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-right text-lg"></i></a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة مؤسسة':'تعديل المؤسسة' ?></h2>
  </div>
  <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <input type="hidden" name="action" value="save_institution">
    <?php if ($ei): ?><input type="hidden" name="inst_id" value="<?= $ei['inst_id'] ?>"><?php endif; ?>
    <div><label class="form-label">اسم المؤسسة (عربي) *</label>
    <input type="text" name="inst_name_ar" required class="form-input" value="<?= htmlspecialchars($ei['inst_name_ar']??'') ?>"></div>
    <div><label class="form-label">اسم المؤسسة (إنجليزي)</label>
    <input type="text" name="inst_name_en" class="form-input" dir="ltr" value="<?= htmlspecialchars($ei['inst_name_en']??'') ?>"></div>
    <div>
      <label class="form-label">شعار المؤسسة</label>
      <div class="pi-upload-zone" onclick="document.getElementById('inst_logo_file').click()">
        <input type="file" id="inst_logo_file" name="inst_logo_file" accept="image/*" class="hidden" data-preview="inst_logo_prev" data-placeholder="inst_logo_ph">
        <img id="inst_logo_prev" src="<?= htmlspecialchars($ei['inst_logo']??'') ?>"
          class="<?= ($ei['inst_logo']??'')?'':'hidden' ?> w-20 h-20 rounded-xl object-contain mx-auto mb-2">
        <div id="inst_logo_ph" <?= ($ei['inst_logo']??'')?'style="display:none"':'' ?>>
          <i class="fa-solid fa-building" style="font-size:24px;color:#9ca3af;display:block;margin-bottom:6px;"></i>
          <p style="font-size:13px;font-weight:700;color:#6b7280;">اضغط لرفع الشعار</p>
          <p style="font-size:11px;color:#9ca3af;margin-top:3px;">PNG, JPG, SVG, WebP</p>
        </div>
      </div>
      <input type="hidden" name="inst_logo" value="<?= htmlspecialchars($ei['inst_logo']??'') ?>">
    </div>
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <div><label class="form-label">الوصف</label>
    <div id="inst_desc_editor" style="min-height:160px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;font-family:'Cairo',sans-serif;"></div>
    <textarea name="inst_description" id="inst_desc_hidden" class="hidden"></textarea></div>
    <?php $inst_countries = pi_get_countries(); ?>
    <div>
      <label class="form-label">الدولة</label>
      <select name="inst_country_id" class="form-input">
        <option value="0">— اختر الدولة —</option>
        <?php foreach ($inst_countries as $cn): ?>
        <option value="<?= $cn['c_id'] ?>" <?= ($ei['inst_country_id']??0)==$cn['c_id']?'selected':'' ?>>
          <?= htmlspecialchars($cn['c_flag'].' '.$cn['c_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-center gap-2">
      <input type="checkbox" name="inst_verified" value="1" id="inst_v" <?= ($ei['inst_verified']??0)?'checked':'' ?> class="w-5 h-5 accent-blue-500">
      <label for="inst_v" class="font-bold text-gray-700 text-sm"><i class="fa-solid fa-circle-check text-blue-500 mr-1"></i> موثقة</label>
    </div>
    <div class="flex gap-3"><button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk mr-2"></i>حفظ</button>
    <a href="admin.php?p=institutions" class="btn-secondary">إلغاء</a></div>
  </form>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var instDescQuill = new Quill('#inst_desc_editor', {
  theme: 'snow', direction: 'rtl',
  modules: { toolbar: [[{header:[2,3,false]}],['bold','italic','underline','strike'],[{list:'ordered'},{list:'bullet'}],['blockquote'],['link'],['clean']] }
});
var _instDescRaw = <?= json_encode($ei['inst_description'] ?? '') ?>;
if (_instDescRaw) instDescQuill.root.innerHTML = _instDescRaw;
document.querySelector('form').addEventListener('submit', function() {
  document.getElementById('inst_desc_hidden').value = instDescQuill.root.innerHTML;
});
</script>

<?php } else {
$list = [];
$r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 ORDER BY inst_created DESC");
if ($r) while ($row=$r->fetch_assoc()) $list[] = $row;
?>
<?php if ($msg): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">المؤسسات (<?= count($list) ?>)</h2>
  <?php if (pi_has_perm('add_institution')): ?>
  <a href="admin.php?p=institutions&action=add" class="btn-primary flex items-center gap-2"><i class="fa-solid fa-plus"></i> إضافة مؤسسة</a>
  <?php endif; ?>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead><tr><th>المؤسسة</th><th>التوثيق</th><th>الزيارات</th><th>الإجراءات</th></tr></thead>
    <tbody>
      <?php foreach ($list as $inst): ?>
      <tr class="hover:bg-gray-50 transition">
        <td><div class="flex items-center gap-3">
          <?php if ($inst['inst_logo']): ?><img src="<?= htmlspecialchars($inst['inst_logo']) ?>" class="w-9 h-9 rounded-lg object-contain"><?php else: ?><div class="w-9 h-9 rounded-lg bg-indigo-500 flex items-center justify-center text-white text-xs font-bold"><?= mb_substr($inst['inst_name_ar'],0,1) ?></div><?php endif; ?>
          <div><p class="font-bold text-gray-800"><?= htmlspecialchars($inst['inst_name_ar']) ?></p><p class="text-gray-400 text-xs" dir="ltr"><?= htmlspecialchars($inst['inst_name_en']??'') ?></p></div>
        </div></td>
        <td><?php if ($inst['inst_verified']): ?><i class="fa-solid fa-circle-check text-blue-500 text-lg"></i><?php else: ?><i class="fa-solid fa-circle-xmark text-gray-300 text-lg"></i><?php endif; ?></td>
        <td class="font-semibold"><?= number_format($inst['inst_views']) ?></td>
        <td><div class="flex gap-2">
          <?php if (pi_has_perm('edit_institution')): ?><a href="admin.php?p=institutions&action=edit&id=<?= $inst['inst_id'] ?>" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition"><i class="fa-solid fa-pen text-xs"></i></a><?php endif; ?>
          <?php if (pi_has_perm('delete_institution')): ?><form method="POST" onsubmit="return confirm('حذف؟')"><input type="hidden" name="action" value="delete_institution"><input type="hidden" name="inst_id" value="<?= $inst['inst_id'] ?>"><button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-trash text-xs"></i></button></form><?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?><tr><td colspan="4" class="text-center py-12 text-gray-400"><i class="fa-solid fa-building text-4xl mb-3 block"></i>لا توجد مؤسسات</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php } ?>
