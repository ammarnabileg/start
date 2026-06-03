<?php
$pageTitle = 'اقتراح إضافة شخصية - PioneerIcons';
require_once 'includes/config.php';

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_ar   = trim($_POST['p_name_ar'] ?? '');
    $name_en   = trim($_POST['p_name_en'] ?? '');
    $title     = trim($_POST['p_title'] ?? '');
    $national  = trim($_POST['p_nationality'] ?? '');
    $residence = trim($_POST['p_residence'] ?? '');
    $bio       = trim($_POST['p_bio'] ?? '');
    $photo     = trim($_POST['p_photo'] ?? '');
    $cats      = $_POST['categories'] ?? [];
    $submitter = trim($_POST['submitter_name'] ?? '');
    $sub_email = trim($_POST['submitter_email'] ?? '');

    if (!$name_ar) $errors[] = 'الاسم بالعربي مطلوب';
    if (!$title)   $errors[] = 'المسمى الوظيفي مطلوب';

    if (empty($errors)) {
        $name_ar_e   = pi_escape($name_ar);
        $name_en_e   = pi_escape($name_en);
        $title_e     = pi_escape($title);
        $national_e  = pi_escape($national);
        $residence_e = pi_escape($residence);
        $bio_e       = pi_escape($bio);
        $photo_e     = pi_escape($photo);
        $submitter_e = pi_escape($submitter);
        $sub_email_e = pi_escape($sub_email);
        $cats_json   = pi_escape(json_encode($cats));

        // Store as pending submission
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

        $data = json_encode([
            'p_name_ar'=>$name_ar,'p_name_en'=>$name_en,'p_title'=>$title,
            'p_nationality'=>$national,'p_residence'=>$residence,'p_bio'=>$bio,
            'p_photo'=>$photo,'categories'=>$cats
        ]);
        $data_e = pi_escape($data);

        $mysqli->query("INSERT INTO pi_submissions (sub_type,sub_data,sub_submitter_name,sub_submitter_email) VALUES ('personality','$data_e','$submitter_e','$sub_email_e')");
        $success = true;
    }
}

$all_cats = pi_get_categories();
include 'includes/header.php';
?>

<section class="hero-bg py-10">
  <div class="max-w-2xl mx-auto px-4 text-center text-white">
    <h1 class="text-3xl font-black mb-2">اقتراح إضافة شخصية</h1>
    <p class="text-blue-100">سيتم مراجعة اقتراحك من فريقنا قبل النشر</p>
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

  <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-sm text-blue-700 font-semibold">
    <i class="fa-solid fa-circle-info mr-2"></i>
    يعمل هذا النموذج مثل ويكيبيديا — يمكن لأي شخص اقتراح إضافة أو تعديل. سيراجع فريقنا المقترح قبل النشر.
  </div>

  <form method="POST" class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
    <h2 class="font-black text-gray-800 text-lg border-b border-gray-100 pb-4">معلومات الشخصية</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="form-label">الاسم بالعربي <span class="text-red-500">*</span></label>
        <input type="text" name="p_name_ar" required class="form-input" value="<?= htmlspecialchars($_POST['p_name_ar']??'') ?>">
      </div>
      <div>
        <label class="form-label">الاسم بالإنجليزي</label>
        <input type="text" name="p_name_en" class="form-input" dir="ltr" value="<?= htmlspecialchars($_POST['p_name_en']??'') ?>">
      </div>
      <div>
        <label class="form-label">المسمى الوظيفي / التعريف <span class="text-red-500">*</span></label>
        <input type="text" name="p_title" required class="form-input" placeholder="مثال: رجل أعمال، كاتب، سياسي" value="<?= htmlspecialchars($_POST['p_title']??'') ?>">
      </div>
      <div>
        <label class="form-label">الجنسية</label>
        <input type="text" name="p_nationality" class="form-input" value="<?= htmlspecialchars($_POST['p_nationality']??'') ?>">
      </div>
      <div>
        <label class="form-label">بلد الإقامة</label>
        <input type="text" name="p_residence" class="form-input" value="<?= htmlspecialchars($_POST['p_residence']??'') ?>">
      </div>
      <div>
        <label class="form-label">رابط الصورة الشخصية</label>
        <input type="url" name="p_photo" class="form-input" dir="ltr" placeholder="https://..." value="<?= htmlspecialchars($_POST['p_photo']??'') ?>">
      </div>
    </div>

    <div>
      <label class="form-label">نبذة أو سيرة ذاتية</label>
      <textarea name="p_bio" rows="5" class="form-input resize-y" placeholder="اكتب نبذة مختصرة عن الشخصية..."><?= htmlspecialchars($_POST['p_bio']??'') ?></textarea>
    </div>

    <div>
      <label class="form-label">التصنيفات</label>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-2 p-4 border border-gray-200 rounded-xl">
        <?php foreach ($all_cats as $cat): ?>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="categories[]" value="<?= $cat['cat_id'] ?>" class="accent-orange-500">
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

    <button type="submit" class="w-full py-3.5 pi-primary-bg text-white font-black text-base rounded-xl hover:opacity-90 transition flex items-center justify-center gap-2">
      <i class="fa-solid fa-paper-plane"></i> إرسال الاقتراح للمراجعة
    </button>
  </form>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
