<?php
$pageTitle = 'اقتراح إضافة شركة أو مؤسسة - PioneerIcons';
require_once 'includes/config.php';

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_ar  = trim($_POST['inst_name_ar'] ?? '');
    $name_en  = trim($_POST['inst_name_en'] ?? '');
    $desc     = trim($_POST['inst_description'] ?? '');
    $logo = trim($_POST['inst_logo'] ?? '');
    if (!empty($_FILES['inst_logo_file']['name']) && $_FILES['inst_logo_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['inst_logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg'])) {
            $fname = 'inst_' . time() . '_' . rand(100,999) . '.' . $ext;
            if (move_uploaded_file($_FILES['inst_logo_file']['tmp_name'], __DIR__ . '/uploads/' . $fname)) {
                $logo = 'uploads/' . $fname;
            }
        }
    }
    $cats     = $_POST['categories'] ?? [];
    $submitter  = trim($_POST['submitter_name'] ?? '');
    $sub_email  = trim($_POST['submitter_email'] ?? '');

    if (!$name_ar) $errors[] = 'اسم المؤسسة بالعربي مطلوب';

    if (empty($errors)) {
        $data = json_encode([
            'inst_name_ar'=>$name_ar,'inst_name_en'=>$name_en,
            'inst_description'=>$desc,'inst_logo'=>$logo,'categories'=>$cats
        ]);
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

<section class="hero-bg py-10">
  <div class="max-w-2xl mx-auto px-4 text-center text-white relative z-10">
    <h1 class="text-3xl font-black mb-2">اقتراح إضافة شركة أو مؤسسة</h1>
    <p class="text-purple-200">سيتم مراجعة اقتراحك من فريقنا قبل النشر</p>
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
    <p class="text-red-700 text-sm font-semibold"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="bg-purple-50 border border-purple-200 rounded-xl p-4 mb-6 text-sm text-purple-700 font-semibold">
    <i class="fa-solid fa-circle-info mr-2"></i>
    يعمل هذا النموذج مثل ويكيبيديا — يمكن لأي شخص اقتراح إضافة أو تعديل. سيراجع فريقنا المقترح قبل النشر.
  </div>

  <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <h2 class="font-black text-gray-800 text-lg border-b border-gray-100 pb-4">معلومات الشركة / المؤسسة</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="form-label">الاسم بالعربي <span class="text-red-500">*</span></label>
        <input type="text" name="inst_name_ar" required class="form-input" value="<?= htmlspecialchars($_POST['inst_name_ar']??'') ?>">
      </div>
      <div>
        <label class="form-label">الاسم بالإنجليزي</label>
        <input type="text" name="inst_name_en" class="form-input" dir="ltr" value="<?= htmlspecialchars($_POST['inst_name_en']??'') ?>">
      </div>
      <div class="md:col-span-2">
        <label class="form-label">شعار الشركة / المؤسسة <span class="text-gray-400 font-normal">(اختياري)</span></label>
        <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 hover:border-purple-400 transition cursor-pointer text-center" onclick="document.getElementById('logo_file_inst').click()">
          <input type="file" id="logo_file_inst" name="inst_logo_file" accept="image/*" class="hidden" onchange="previewInstLogo(this)">
          <img id="inst_logo_prev" class="hidden w-16 h-16 rounded-xl object-contain mx-auto mb-2">
          <i class="fa-solid fa-building text-gray-400 text-2xl mb-1"></i>
          <p class="text-sm text-gray-500 font-semibold">اضغط لرفع الشعار</p>
          <p class="text-xs text-gray-400">JPG, PNG, SVG, WebP</p>
        </div>
        <p class="text-xs text-gray-400 text-center mt-1">— أو —</p>
        <input type="url" name="inst_logo" class="form-input mt-1" dir="ltr" placeholder="https://... رابط خارجي" value="<?= htmlspecialchars($_POST['inst_logo']??'') ?>">
      </div>
    </div>

    <div>
      <label class="form-label">نبذة عن المؤسسة</label>
      <div id="inst_desc_editor" style="min-height:160px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;font-family:'Cairo',sans-serif;"></div>
      <textarea name="inst_description" id="inst_desc_hidden" class="hidden"><?= htmlspecialchars($_POST['inst_description']??'') ?></textarea>
    </div>

    <div>
      <label class="form-label">التصنيفات</label>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-2 p-4 border border-gray-200 rounded-xl">
        <?php foreach ($all_cats as $cat): ?>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="categories[]" value="<?= $cat['cat_id'] ?>" class="accent-purple-500">
          <span class="text-sm text-gray-700"><?= htmlspecialchars($cat['cat_name']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="border-t border-gray-100 pt-5">
      <h3 class="font-bold text-gray-700 mb-4">معلومات مقدم الاقتراح (اختياري)</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
          <label class="form-label">اسمك</label>
          <input type="text" name="submitter_name" class="form-input" value="<?= htmlspecialchars($_POST['submitter_name']??'') ?>">
        </div>
        <div>
          <label class="form-label">بريدك الإلكتروني</label>
          <input type="email" name="submitter_email" class="form-input" dir="ltr" value="<?= htmlspecialchars($_POST['submitter_email']??'') ?>">
        </div>
      </div>
    </div>

    <!-- Quill Rich Text Editor -->
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <script>
    var descQuill = new Quill('#inst_desc_editor', {
      theme: 'snow',
      direction: 'rtl',
      modules: {
        toolbar: [
          [{ header: [2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote', 'code-block'],
          [{ align: [] }],
          ['clean']
        ]
      },
      placeholder: 'اكتب وصفاً مختصراً عن المؤسسة ونشاطها...'
    });
    var existingDesc = document.getElementById('inst_desc_hidden').value;
    if (existingDesc) {
      try { descQuill.root.innerHTML = existingDesc; } catch(e) {}
    }
    document.querySelector('form').addEventListener('submit', function() {
      document.getElementById('inst_desc_hidden').value = descQuill.root.innerHTML;
    });
    </script>
    <script>
    function previewInstLogo(input) {
      if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
          const img = document.getElementById('inst_logo_prev');
          img.src = e.target.result; img.classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
      }
    }
    </script>
    <button type="submit" class="w-full py-3.5 pi-primary-bg text-white font-black text-base rounded-xl hover:opacity-90 transition flex items-center justify-center gap-2">
      <i class="fa-solid fa-paper-plane"></i> إرسال الاقتراح للمراجعة
    </button>
  </form>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
