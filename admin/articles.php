<?php
pi_require_perm('view_articles');
$action = $_GET['action'] ?? 'list';
$msg = '';

// Add art_body column if not exists
$mysqli->query("ALTER TABLE pi_articles ADD COLUMN IF NOT EXISTS art_body LONGTEXT AFTER art_title");
$mysqli->query("ALTER TABLE pi_articles ADD COLUMN IF NOT EXISTS art_image_upload VARCHAR(500) AFTER art_image");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'save_article') {
        $id     = (int)($_POST['art_id'] ?? 0);
        $p_id   = (int)($_POST['art_p_id'] ?? 0);
        $title  = pi_escape($_POST['art_title'] ?? '');
        $body   = pi_escape($_POST['art_body'] ?? '');
        $source = pi_escape($_POST['art_source'] ?? '');
        $url    = pi_escape($_POST['art_url'] ?? '');
        $image  = pi_escape($_POST['art_image'] ?? '');

        // Handle featured image upload
        if (!empty($_FILES['art_image_file']['name']) && $_FILES['art_image_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['art_image_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $uploads_dir = dirname(__DIR__) . '/uploads/';
                if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
                $fname = 'art_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['art_image_file']['tmp_name'], $uploads_dir . $fname)) {
                    $image = pi_escape('../uploads/' . $fname);
                }
            }
        }

        if ($id) {
            pi_require_perm('edit_article');
            $mysqli->query("UPDATE pi_articles SET art_p_id=$p_id,art_title='$title',art_body='$body',art_source='$source',art_url='$url',art_image='$image' WHERE art_id=$id");
        } else {
            pi_require_perm('add_article');
            $mysqli->query("INSERT INTO pi_articles (art_p_id,art_title,art_body,art_source,art_url,art_image) VALUES ($p_id,'$title','$body','$source','$url','$image')");
        }
        $msg = 'تم الحفظ'; $action = 'list';
    }
    if ($act === 'delete_article') {
        pi_require_perm('delete_article');
        $id = (int)($_POST['art_id'] ?? 0);
        $mysqli->query("UPDATE pi_articles SET art_active=0 WHERE art_id=$id");
        $msg = 'تم الحذف';
    }
}

// Personalities for dropdown
$personalities_list = [];
$r = $mysqli->query("SELECT p_id,p_name_ar FROM pi_personalities WHERE p_active=1 ORDER BY p_name_ar");
if ($r) while ($row=$r->fetch_assoc()) $personalities_list[] = $row;

if ($action === 'add' || $action === 'edit') {
    $ea = null;
    if ($action === 'edit') {
        $eid = (int)($_GET['id'] ?? 0);
        $r = $mysqli->query("SELECT * FROM pi_articles WHERE art_id=$eid");
        if ($r && $r->num_rows) $ea = $r->fetch_assoc();
    }
?>

<!-- Quill Editor -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
#art_body_quill { min-height:400px; font-family:'Cairo',sans-serif; font-size:15px; direction:rtl; text-align:right; line-height:1.8; }
#art_body_quill .ql-editor { min-height:380px; }
.ql-toolbar.ql-snow { direction:ltr; text-align:left; border-radius:12px 12px 0 0; border-color:#e5e7eb !important; background:#fafafa; }
.ql-container.ql-snow { border-radius:0 0 12px 12px; border-color:#e5e7eb !important; }
.ql-editor { direction:rtl; text-align:right; }
.ql-editor.ql-blank::before { right:15px; left:auto; }
</style>

<script>
function insertYoutubeQuill() {
  var url = prompt('أدخل رابط YouTube:');
  if (!url) return;
  var match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/);
  if (!match) return;
  var html = '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;"><iframe src="https://www.youtube.com/embed/' + match[1] + '" style="position:absolute;top:0;right:0;width:100%;height:100%;border:0;" allowfullscreen></iframe></div>';
  var range = artQuill.getSelection(true);
  artQuill.clipboard.dangerouslyPasteHTML(range ? range.index : artQuill.getLength(), html);
}
function uploadImageToQuill() {
  var input = document.createElement('input');
  input.type = 'file'; input.accept = 'image/*';
  input.onchange = function() {
    var fd = new FormData(); fd.append('file', input.files[0]);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../actions/upload_image.php');
    xhr.onload = function() {
      var json = JSON.parse(xhr.responseText);
      if (json.location) {
        var range = artQuill.getSelection(true);
        artQuill.insertEmbed(range ? range.index : 0, 'image', json.location);
      }
    };
    xhr.send(fd);
  };
  input.click();
}
</script>

<div style="max-width:900px;">
  <div class="flex items-center gap-3 mb-6">
    <a href="admin.php?p=articles" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-arrow-right text-lg"></i></a>
    <h2 class="text-xl font-black text-gray-800"><?= $action==='add'?'إضافة مقال':'تعديل المقال' ?></h2>
  </div>

  <form method="POST" enctype="multipart/form-data" class="space-y-5">
    <input type="hidden" name="action" value="save_article">
    <?php if ($ea): ?><input type="hidden" name="art_id" value="<?= $ea['art_id'] ?>"><?php endif; ?>

    <!-- Title -->
    <div class="bg-white rounded-2xl shadow-sm p-5">
      <label class="form-label">عنوان المقال <span style="color:#ef4444">*</span></label>
      <input type="text" name="art_title" required class="form-input text-lg font-bold"
        placeholder="اكتب عنوان المقال هنا..."
        value="<?= htmlspecialchars($ea['art_title']??'') ?>">
    </div>

    <!-- Rich Body Editor -->
    <div class="bg-white rounded-2xl shadow-sm p-5">
      <div class="flex items-center justify-between mb-3">
        <label class="form-label mb-0">محتوى المقال</label>
        <div style="display:flex;gap:8px;">
          <button type="button" onclick="uploadImageToQuill()"
            style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#e0f2fe;color:#0369a1;border:none;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;">
            <i class="fa-solid fa-image"></i> صورة
          </button>
          <button type="button" onclick="insertYoutubeQuill()"
            style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#fee2e2;color:#dc2626;border:none;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;">
            <i class="fa-brands fa-youtube"></i> يوتيوب
          </button>
        </div>
      </div>
      <div id="art_body_quill"></div>
      <textarea id="art_body_hidden" name="art_body" class="hidden"></textarea>
    </div>

    <!-- Meta info + featured image -->
    <div class="bg-white rounded-2xl shadow-sm p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="form-label">الشخصية المرتبطة</label>
        <select name="art_p_id" class="form-input">
          <option value="">— اختر شخصية —</option>
          <?php foreach ($personalities_list as $pl): ?>
          <option value="<?= $pl['p_id'] ?>" <?= ($ea['art_p_id']??0)==$pl['p_id']?'selected':'' ?>><?= htmlspecialchars($pl['p_name_ar']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">المصدر</label>
        <input type="text" name="art_source" class="form-input" value="<?= htmlspecialchars($ea['art_source']??'') ?>">
      </div>
      <div>
        <label class="form-label">رابط المقال الخارجي</label>
        <input type="url" name="art_url" class="form-input" dir="ltr" value="<?= htmlspecialchars($ea['art_url']??'') ?>">
      </div>
      <div>
        <label class="form-label">الصورة المميزة</label>
        <div class="pi-upload-zone" onclick="document.getElementById('art_img_file').click()">
          <input type="file" id="art_img_file" name="art_image_file" accept="image/*" class="hidden" data-preview="art_img_prev" data-placeholder="art_img_ph">
          <img id="art_img_prev" src="<?= htmlspecialchars($ea['art_image']??'') ?>"
            style="<?= ($ea['art_image']??'')?'':'display:none;' ?>width:100%;max-height:120px;object-fit:cover;border-radius:10px;margin-bottom:8px;">
          <div id="art_img_ph" <?= ($ea['art_image']??'')?'style="display:none"':'' ?>>
            <i class="fa-solid fa-image" style="font-size:20px;color:#9ca3af;display:block;margin-bottom:6px;"></i>
            <p style="font-size:12px;color:#9ca3af;">اضغط لرفع صورة مميزة</p>
          </div>
        </div>
        <p style="font-size:11px;color:#9ca3af;text-align:center;margin-top:4px;">— أو —</p>
        <input type="url" name="art_image" class="form-input mt-1" dir="ltr" placeholder="رابط صورة خارجي" value="<?= htmlspecialchars($ea['art_image']??'') ?>">
      </div>
    </div>

    <div class="flex gap-3 pb-6">
      <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> نشر المقال</button>
      <a href="admin.php?p=articles" class="btn-secondary">إلغاء</a>
    </div>
  </form>
</div>

<script>
function previewArtImg(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('art_img_prev');
      img.src = e.target.result;
      img.classList.remove('hidden');
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var artQuill = new Quill('#art_body_quill', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ header: [1, 2, 3, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      [{ color: [] }, { background: [] }],
      [{ align: [] }],
      [{ list: 'ordered' }, { list: 'bullet' }],
      ['blockquote', 'link'],
      ['clean']
    ]
  },
  placeholder: 'اكتب محتوى المقال هنا...'
});
artQuill.root.setAttribute('dir', 'rtl');
var _artBodyRaw = <?= json_encode($ea['art_body'] ?? '') ?>;
if (_artBodyRaw) artQuill.root.innerHTML = _artBodyRaw;
document.querySelector('form').addEventListener('submit', function() {
  document.getElementById('art_body_hidden').value = artQuill.root.innerHTML;
});
</script>

<?php } else {
$list = [];
$r = $mysqli->query("SELECT a.*,p.p_name_ar FROM pi_articles a LEFT JOIN pi_personalities p ON a.art_p_id=p.p_id WHERE a.art_active=1 ORDER BY a.art_id DESC LIMIT 100");
if ($r) while ($row=$r->fetch_assoc()) $list[] = $row;
?>
<?php if ($msg): ?><div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="flex items-center justify-between mb-6">
  <h2 class="text-xl font-black text-gray-800">المقالات (<?= count($list) ?>)</h2>
  <?php if (pi_has_perm('add_article')): ?>
  <a href="admin.php?p=articles&action=add" class="btn-primary flex items-center gap-2"><i class="fa-solid fa-plus"></i> إضافة مقال</a>
  <?php endif; ?>
</div>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
  <table class="w-full">
    <thead><tr><th>المقال</th><th>الشخصية</th><th>المصدر</th><th>الإجراءات</th></tr></thead>
    <tbody>
      <?php foreach ($list as $art): ?>
      <tr class="hover:bg-gray-50 transition">
        <td style="max-width:300px;">
          <?php if ($art['art_image']): ?>
          <img src="<?= htmlspecialchars($art['art_image']) ?>" style="width:48px;height:36px;object-fit:cover;border-radius:8px;float:right;margin-left:10px;">
          <?php endif; ?>
          <p class="font-bold text-gray-800 text-sm" style="overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= htmlspecialchars($art['art_title']) ?></p>
          <?php if ($art['art_body']??''): ?><p style="font-size:11px;color:#9ca3af;margin-top:2px;">يحتوي على محتوى غني</p><?php endif; ?>
        </td>
        <td class="text-gray-500 text-sm"><?= htmlspecialchars($art['p_name_ar']??'—') ?></td>
        <td class="text-gray-400 text-xs"><?= htmlspecialchars($art['art_source']??'—') ?></td>
        <td><div class="flex gap-2">
          <?php if ($art['art_url']): ?><a href="<?= htmlspecialchars($art['art_url']) ?>" target="_blank" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-blue-50 hover:text-blue-500 transition"><i class="fa-solid fa-arrow-up-right-from-square text-xs"></i></a><?php endif; ?>
          <?php if (pi_has_perm('edit_article')): ?><a href="admin.php?p=articles&action=edit&id=<?= $art['art_id'] ?>" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-purple-50 hover:text-purple-600 transition"><i class="fa-solid fa-pen text-xs"></i></a><?php endif; ?>
          <?php if (pi_has_perm('delete_article')): ?><form method="POST" onsubmit="return confirm('حذف؟')"><input type="hidden" name="action" value="delete_article"><input type="hidden" name="art_id" value="<?= $art['art_id'] ?>"><button type="submit" class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-500 transition"><i class="fa-solid fa-trash text-xs"></i></button></form><?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($list)): ?><tr><td colspan="4" class="text-center py-12 text-gray-400"><i class="fa-solid fa-newspaper text-4xl mb-3 block"></i>لا توجد مقالات</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php } ?>
