<?php
$pageTitle = 'اقتراح إضافة شركة أو مؤسسة - PioneerIcons';
require_once 'includes/config.php';

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_ar   = trim($_POST['inst_name_ar']     ?? '');
    $name_en   = trim($_POST['inst_name_en']     ?? '');
    $desc      = trim($_POST['inst_description'] ?? '');
    $submitter = trim($_POST['submitter_name']   ?? '');
    $sub_email = trim($_POST['submitter_email']  ?? '');
    $cats      = $_POST['categories'] ?? [];

    $logo = '';
    if (!empty($_FILES['inst_logo_file']['name']) && $_FILES['inst_logo_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['inst_logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'])) {
            $fname = 'inst_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (move_uploaded_file($_FILES['inst_logo_file']['tmp_name'], __DIR__ . '/uploads/' . $fname)) {
                $logo = 'uploads/' . $fname;
            }
        }
    }

    if (!$name_ar) $errors[] = 'اسم المؤسسة بالعربي مطلوب';

    if (empty($errors)) {
        $data   = json_encode(['inst_name_ar'=>$name_ar,'inst_name_en'=>$name_en,
            'inst_description'=>$desc,'inst_logo'=>$logo,'categories'=>$cats]);
        $data_e      = pi_escape($data);
        $submitter_e = pi_escape($submitter);
        $sub_email_e = pi_escape($sub_email);
        $mysqli->query("INSERT INTO pi_submissions (sub_type,sub_data,sub_submitter_name,sub_submitter_email) VALUES ('institution','$data_e','$submitter_e','$sub_email_e')");
        $success = true;
    }
}

$all_cats = pi_get_categories();
include 'includes/header.php';
?>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
.add-form-input {
  width: 100%;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  padding: 11px 14px;
  font-size: 14px;
  font-family: 'Cairo', sans-serif;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
  background: #fff;
  color: #111827;
  box-sizing: border-box;
}
.add-form-input:focus { border-color: #8829C8; box-shadow: 0 0 0 3px rgba(136,41,200,.1); }
.add-form-label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: #374151;
  margin-bottom: 7px;
}
.upload-zone {
  border: 2px dashed #d1d5db;
  border-radius: 14px;
  padding: 24px 16px;
  text-align: center;
  cursor: pointer;
  transition: border-color .2s, background .2s;
  background: #fafafa;
}
.upload-zone:hover { border-color: #8829C8; background: #f5f0ff; }
.ql-toolbar { direction: ltr; text-align: left; border-radius: 10px 10px 0 0 !important; border-color: #e5e7eb !important; }
.ql-container { border-radius: 0 0 10px 10px !important; border-color: #e5e7eb !important; font-family: 'Cairo', sans-serif !important; font-size: 14px !important; }
.ql-editor { direction: rtl; text-align: right; min-height: 140px; }
.ql-editor.ql-blank::before { right: 15px; left: auto; }
</style>

<section class="hero-bg py-10">
  <div class="max-w-2xl mx-auto px-4 text-center text-white relative z-10">
    <div class="w-14 h-14 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center mx-auto mb-4">
      <i class="fa-solid fa-building text-white text-xl"></i>
    </div>
    <h1 class="text-3xl font-black mb-2">اقتراح إضافة شركة أو مؤسسة</h1>
    <p class="text-purple-200 text-sm">سيتم مراجعة اقتراحك من فريقنا قبل النشر</p>
  </div>
</section>

<div class="max-w-2xl mx-auto px-4 py-10">
<?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 rounded-2xl p-8 text-center">
    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
      <i class="fa-solid fa-circle-check text-green-500 text-3xl"></i>
    </div>
    <h2 class="text-xl font-black text-green-800 mb-2">تم إرسال الاقتراح بنجاح!</h2>
    <p class="text-green-600 mb-5">سيراجع فريقنا المعلومات ويتواصل معك قريباً</p>
    <a href="index.php" class="inline-block px-6 py-2.5 pi-primary-bg text-white font-bold rounded-xl hover:opacity-90 transition">العودة للرئيسية</a>
  </div>
<?php else: ?>

<?php if (!empty($errors)): ?>
  <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
    <?php foreach ($errors as $e): ?>
    <p class="text-red-700 text-sm font-semibold"><i class="fa-solid fa-circle-exclamation ml-2"></i><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

  <div class="bg-purple-50 border border-purple-200 rounded-xl p-4 mb-6 text-sm text-purple-700 font-semibold">
    <i class="fa-solid fa-circle-info ml-2"></i>
    يعمل هذا النموذج مثل ويكيبيديا — يمكن لأي شخص اقتراح إضافة. سيراجع فريقنا المقترح قبل النشر.
  </div>

  <form method="POST" enctype="multipart/form-data" id="institution-submit-form">

    <!-- بطاقة المعلومات الأساسية -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-5">
      <h2 class="font-black text-gray-800 text-base mb-5 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg pi-gradient flex items-center justify-center"><i class="fa-solid fa-building text-white text-xs"></i></span>
        معلومات الشركة / المؤسسة
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="add-form-label">الاسم بالعربي <span class="text-red-500">*</span></label>
          <input type="text" name="inst_name_ar" required class="add-form-input" value="<?= htmlspecialchars($_POST['inst_name_ar']??'') ?>">
        </div>
        <div>
          <label class="add-form-label">الاسم بالإنجليزي</label>
          <input type="text" name="inst_name_en" class="add-form-input" dir="ltr" value="<?= htmlspecialchars($_POST['inst_name_en']??'') ?>">
        </div>

        <!-- شعار - رفع فقط -->
        <div class="md:col-span-2">
          <label class="add-form-label">شعار الشركة / المؤسسة <span class="text-gray-400 font-normal text-xs">(اختياري)</span></label>
          <div class="upload-zone" onclick="document.getElementById('logo_file_inst').click()">
            <input type="file" id="logo_file_inst" name="inst_logo_file" accept="image/*" class="hidden" data-preview="inst_logo_prev" data-placeholder="inst_logo_placeholder">
            <img id="inst_logo_prev" class="hidden w-20 h-20 rounded-xl object-contain mx-auto mb-3">
            <div id="inst_logo_placeholder">
              <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-2">
                <i class="fa-solid fa-building text-gray-400 text-lg"></i>
              </div>
              <p class="text-sm font-bold text-gray-600">اضغط لرفع الشعار</p>
              <p class="text-xs text-gray-400 mt-1">JPG, PNG, SVG, WebP — حتى 5MB</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- بطاقة الوصف -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-5">
      <h2 class="font-black text-gray-800 text-base mb-4 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg pi-gradient flex items-center justify-center"><i class="fa-solid fa-align-right text-white text-xs"></i></span>
        نبذة عن المؤسسة
      </h2>
      <div id="inst_desc_editor"></div>
      <textarea name="inst_description" id="inst_desc_hidden" class="hidden"><?= htmlspecialchars($_POST['inst_description']??'') ?></textarea>
    </div>

    <!-- بطاقة التصنيفات -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-5">
      <h2 class="font-black text-gray-800 text-base mb-4 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg pi-gradient flex items-center justify-center"><i class="fa-solid fa-tags text-white text-xs"></i></span>
        التصنيفات
      </h2>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <?php foreach ($all_cats as $cat): ?>
        <label class="flex items-center gap-2 cursor-pointer p-2 rounded-lg hover:bg-purple-50 transition">
          <input type="checkbox" name="categories[]" value="<?= $cat['cat_id'] ?>" class="accent-purple-600 w-4 h-4">
          <span class="text-sm text-gray-700 font-semibold"><?= htmlspecialchars($cat['cat_name']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- بطاقة مقدم الاقتراح -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
      <h2 class="font-black text-gray-800 text-base mb-4 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center"><i class="fa-solid fa-circle-user text-gray-500 text-xs"></i></span>
        معلومات مقدم الاقتراح <span class="text-gray-400 font-normal text-sm">(اختياري)</span>
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="add-form-label">اسمك</label>
          <input type="text" name="submitter_name" class="add-form-input" value="<?= htmlspecialchars($_POST['submitter_name']??'') ?>">
        </div>
        <div>
          <label class="add-form-label">بريدك الإلكتروني</label>
          <input type="email" name="submitter_email" class="add-form-input" dir="ltr" value="<?= htmlspecialchars($_POST['submitter_email']??'') ?>">
        </div>
      </div>
    </div>

    <button type="submit" class="w-full py-4 pi-primary-bg text-white font-black text-base rounded-2xl hover:opacity-90 transition flex items-center justify-center gap-2 shadow-lg">
      <i class="fa-solid fa-paper-plane"></i> إرسال الاقتراح للمراجعة
    </button>
  </form>
<?php endif; ?>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var descQuill = new Quill('#inst_desc_editor', {
  theme: 'snow',
  modules: {
    toolbar: [
      [{ header: [2, 3, false] }],
      ['bold', 'italic', 'underline', 'strike'],
      [{ list: 'ordered' }, { list: 'bullet' }],
      ['blockquote'],
      ['clean']
    ]
  },
  placeholder: 'اكتب وصفاً مختصراً عن المؤسسة ونشاطها...'
});
descQuill.root.setAttribute('dir', 'rtl');
var ed = document.getElementById('inst_desc_hidden').value;
if (ed) try { descQuill.root.innerHTML = ed; } catch(e) {}
document.getElementById('institution-submit-form').addEventListener('submit', function() {
  document.getElementById('inst_desc_hidden').value = descQuill.root.innerHTML;
});

function previewInstLogo(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById('inst_logo_prev');
      img.src = e.target.result;
      img.classList.remove('hidden');
      document.getElementById('inst_logo_placeholder').classList.add('hidden');
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php include 'includes/footer.php'; ?>
