<?php
$pageTitle = 'اقتراح إضافة شخصية - PioneerIcons';
require_once 'includes/config.php';

$success = false;
$errors  = [];
$_cur_user = pi_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_ar   = trim($_POST['p_name_ar']   ?? '');
    $name_en   = trim($_POST['p_name_en']   ?? '');
    $title     = trim($_POST['p_title']     ?? '');
    $national  = trim($_POST['p_nationality']?? '');
    $residence = trim($_POST['p_residence'] ?? '');
    $bio       = trim($_POST['p_bio']       ?? '');
    $submitter = $_cur_user ? $_cur_user['u_name']  : trim($_POST['submitter_name']  ?? '');
    $sub_email = $_cur_user ? $_cur_user['u_email'] : trim($_POST['submitter_email'] ?? '');
    $cats      = $_POST['categories'] ?? [];

    $photo = '';
    if (!empty($_FILES['p_photo_file']['name']) && $_FILES['p_photo_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['p_photo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $fname = 'p_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (move_uploaded_file($_FILES['p_photo_file']['tmp_name'], __DIR__ . '/uploads/' . $fname)) {
                $photo = 'uploads/' . $fname;
            }
        }
    }

    if (!$name_ar) $errors[] = 'الاسم بالعربي مطلوب';
    if (!$title)   $errors[] = 'المسمى الوظيفي مطلوب';

    if (empty($errors)) {
        $mysqli->query("CREATE TABLE IF NOT EXISTS pi_submissions (
            sub_id INT AUTO_INCREMENT PRIMARY KEY,
            sub_type ENUM('personality','institution') DEFAULT 'personality',
            sub_data TEXT,
            sub_status ENUM('pending','approved','rejected') DEFAULT 'pending',
            sub_submitter_name VARCHAR(200),
            sub_submitter_email VARCHAR(200),
            sub_note TEXT,
            sub_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $data   = json_encode(['p_name_ar'=>$name_ar,'p_name_en'=>$name_en,'p_title'=>$title,
            'p_nationality'=>$national,'p_residence'=>$residence,'p_bio'=>$bio,
            'p_photo'=>$photo,'categories'=>$cats]);
        $_cs = $mysqli->query("SHOW COLUMNS FROM pi_submissions LIKE 'sub_user_id'");
        if ($_cs && $_cs->num_rows === 0) $mysqli->query("ALTER TABLE pi_submissions ADD COLUMN sub_user_id INT DEFAULT NULL");
        $data_e      = pi_escape($data);
        $submitter_e = pi_escape($submitter);
        $sub_email_e = pi_escape($sub_email);
        $uid_val     = $_cur_user ? (int)$_cur_user['u_id'] : 'NULL';
        $mysqli->query("INSERT INTO pi_submissions (sub_type,sub_data,sub_submitter_name,sub_submitter_email,sub_user_id) VALUES ('personality','$data_e','$submitter_e','$sub_email_e',$uid_val)");
        $success = true;
    }
}

$all_cats = pi_get_categories();
$all_countries = pi_get_countries();
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
/* Fix Quill RTL toolbar */
.ql-toolbar { direction: ltr; text-align: left; border-radius: 10px 10px 0 0 !important; border-color: #e5e7eb !important; }
.ql-container { border-radius: 0 0 10px 10px !important; border-color: #e5e7eb !important; font-family: 'Cairo', sans-serif !important; font-size: 14px !important; }
.ql-editor { direction: rtl; text-align: right; min-height: 140px; }
.ql-editor.ql-blank::before { right: 15px; left: auto; }
</style>

<section class="hero-bg py-10">
  <div class="max-w-2xl mx-auto px-4 text-center text-white relative z-10">
    <div class="w-14 h-14 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center mx-auto mb-4">
      <i class="fa-solid fa-user-plus text-white text-xl"></i>
    </div>
    <h1 class="text-3xl font-black mb-2">اقتراح إضافة شخصية</h1>
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

  <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-700 font-semibold">
    <i class="fa-solid fa-circle-info ml-2"></i>
    يعمل هذا النموذج مثل ويكيبيديا — يمكن لأي شخص اقتراح إضافة. سيراجع فريقنا المقترح قبل النشر.
  </div>

  <form method="POST" enctype="multipart/form-data" id="personality-submit-form">
    <!-- بطاقة المعلومات الأساسية -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-5">
      <h2 class="font-black text-gray-800 text-base mb-5 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg pi-gradient flex items-center justify-center"><i class="fa-solid fa-user text-white text-xs"></i></span>
        معلومات الشخصية
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="add-form-label">الاسم بالعربي <span class="text-red-500">*</span></label>
          <input type="text" name="p_name_ar" required class="add-form-input" value="<?= htmlspecialchars($_POST['p_name_ar']??'') ?>">
        </div>
        <div>
          <label class="add-form-label">الاسم بالإنجليزي</label>
          <input type="text" name="p_name_en" class="add-form-input" dir="ltr" value="<?= htmlspecialchars($_POST['p_name_en']??'') ?>">
        </div>
        <div>
          <label class="add-form-label">المسمى الوظيفي / التعريف <span class="text-red-500">*</span></label>
          <input type="text" name="p_title" required class="add-form-input" placeholder="مثال: رجل أعمال، كاتب، سياسي" value="<?= htmlspecialchars($_POST['p_title']??'') ?>">
        </div>
        <div>
          <label class="add-form-label">الجنسية</label>
          <select name="p_nationality" class="add-form-input">
            <option value="">— اختر الجنسية —</option>
            <?php foreach ($all_countries as $cn): ?>
            <option value="<?= htmlspecialchars($cn['c_name']) ?>" <?= ($_POST['p_nationality']??'')===$cn['c_name']?'selected':'' ?>>
              <?= htmlspecialchars($cn['c_flag'].' '.$cn['c_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="add-form-label">بلد الإقامة</label>
          <input type="text" name="p_residence" class="add-form-input" placeholder="مثال: دبي، لندن، القاهرة..." value="<?= htmlspecialchars($_POST['p_residence']??'') ?>">
        </div>

        <!-- صورة شخصية - رفع فقط -->
        <div>
          <label class="add-form-label">الصورة الشخصية <span class="text-gray-400 font-normal text-xs">(اختياري)</span></label>
          <div class="upload-zone" onclick="document.getElementById('photo_file').click()">
            <input type="file" id="photo_file" name="p_photo_file" accept="image/*" class="hidden" data-preview="photo_prev" data-placeholder="photo_placeholder">
            <img id="photo_prev" class="hidden w-20 h-20 rounded-full object-cover mx-auto mb-3">
            <div id="photo_placeholder">
              <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-2">
                <i class="fa-solid fa-camera text-gray-400 text-lg"></i>
              </div>
              <p class="text-sm font-bold text-gray-600">اضغط لرفع صورة</p>
              <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP — حتى 5MB</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- بطاقة النبذة -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-5">
      <h2 class="font-black text-gray-800 text-base mb-4 flex items-center gap-2">
        <span class="w-7 h-7 rounded-lg pi-gradient flex items-center justify-center"><i class="fa-solid fa-align-right text-white text-xs"></i></span>
        نبذة أو سيرة ذاتية
      </h2>
      <div id="bio_editor"></div>
      <textarea name="p_bio" id="p_bio_hidden" class="hidden"><?= htmlspecialchars($_POST['p_bio']??'') ?></textarea>
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
    <?php if ($_cur_user): ?>
    <div class="bg-purple-50 border border-purple-200 rounded-2xl p-4 mb-6 flex items-center gap-3">
      <div class="w-9 h-9 rounded-full pi-gradient flex items-center justify-center flex-shrink-0">
        <i class="fa-solid fa-circle-check text-white text-sm"></i>
      </div>
      <div>
        <p class="font-black text-purple-800 text-sm">سيُربط الاقتراح بحسابك تلقائياً</p>
        <p class="text-purple-600 text-xs"><?= htmlspecialchars($_cur_user['u_name']) ?> — <?= htmlspecialchars($_cur_user['u_email']) ?></p>
      </div>
    </div>
    <?php else: ?>
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
    <?php endif; ?>

    <button type="submit" class="w-full py-4 pi-primary-bg text-white font-black text-base rounded-2xl hover:opacity-90 transition flex items-center justify-center gap-2 shadow-lg">
      <i class="fa-solid fa-paper-plane"></i> إرسال الاقتراح للمراجعة
    </button>
  </form>
<?php endif; ?>
</div>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var bioQuill = new Quill('#bio_editor', {
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
  placeholder: 'اكتب نبذة مختصرة عن الشخصية...'
});
bioQuill.root.setAttribute('dir', 'rtl');
var eb = document.getElementById('p_bio_hidden').value;
if (eb) try { bioQuill.root.innerHTML = eb; } catch(e) {}
document.getElementById('personality-submit-form').addEventListener('submit', function() {
  document.getElementById('p_bio_hidden').value = bioQuill.root.innerHTML;
});

function previewPhoto(input) {
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById('photo_prev');
      img.src = e.target.result;
      img.classList.remove('hidden');
      document.getElementById('photo_placeholder').classList.add('hidden');
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php include 'includes/footer.php'; ?>
