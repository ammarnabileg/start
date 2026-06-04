<?php
require_once 'includes/config.php';
$pageTitle = 'الشكاوي والملاحظات - PioneerIcons';

$success = false;
$errors = [];
$user = pi_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = in_array($_POST['cmp_type']??'', ['complaint','suggestion','feedback','request']) ? pi_escape($_POST['cmp_type']) : 'complaint';
    $subject = pi_escape(trim($_POST['cmp_subject'] ?? ''));
    $message = pi_escape(trim($_POST['cmp_message'] ?? ''));
    $name    = pi_escape(trim($_POST['cmp_name']    ?? ($user['u_name'] ?? '')));
    $email   = pi_escape(trim($_POST['cmp_email']   ?? ($user['u_email'] ?? '')));
    $uid     = $user ? (int)$user['u_id'] : 'NULL';

    if (!$subject) $errors[] = 'عنوان الرسالة مطلوب';
    if (!$message) $errors[] = 'محتوى الرسالة مطلوب';
    if (!$name)    $errors[] = 'الاسم مطلوب';
    if (!$email)   $errors[] = 'البريد الإلكتروني مطلوب';

    if (empty($errors)) {
        $mysqli->query("INSERT INTO pi_complaints(cmp_user_id,cmp_type,cmp_subject,cmp_message,cmp_name,cmp_email) VALUES($uid,'$type','$subject','$message','$name','$email')");
        $success = true;
    }
}

include 'includes/header.php';
?>

<!-- BREADCRUMB -->
<div class="max-w-5xl mx-auto px-4 py-5">
  <nav class="flex items-center gap-2 text-sm text-gray-500">
    <a href="index.php" class="hover:text-purple-600 transition font-semibold">الرئيسية</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <span class="text-gray-800 font-semibold">الشكاوي والملاحظات</span>
  </nav>
</div>

<div class="max-w-4xl mx-auto px-4 pb-16">

  <h1 class="text-3xl font-black text-gray-800 text-center mb-2">الشكاوي و الملاحظات</h1>
  <p class="text-gray-400 text-center mb-10 font-medium">يمكنك التواصل معنا بسهولة من خلال هذه الصفحة</p>

  <?php if ($success): ?>
  <div class="max-w-md mx-auto bg-green-50 border border-green-200 rounded-2xl p-10 text-center">
    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
      <i class="fa-solid fa-circle-check text-green-500 text-3xl"></i>
    </div>
    <h2 class="font-black text-green-800 text-lg mb-2">تم الاستلام!</h2>
    <p class="text-green-600 text-sm mb-5">شكراً لتواصلك معنا. سنرد عليك في أقرب وقت ممكن.</p>
    <a href="index.php" class="inline-block px-6 py-2.5 pi-primary-bg text-white font-bold rounded-xl hover:opacity-90 transition text-sm">العودة للرئيسية</a>
  </div>

  <?php else: ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 max-w-xl mx-auto">
    <h2 class="font-black text-gray-800 text-lg mb-1">يمكنك الآن التواصل معنا بسهولة</h2>
    <p class="text-gray-400 text-sm mb-6">من خلال هذا القسم يمكنك تقديم شكوى أو اقتراح أو ملاحظة ونحن دائماً سنكون على تواصل معك في أقرب وقت ممكن.</p>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
      <?php foreach ($errors as $e): ?>
        <p class="text-red-700 text-sm font-semibold"><i class="fa-solid fa-circle-exclamation ml-2"></i><?= htmlspecialchars($e) ?></p>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-bold text-gray-700 mb-1.5">نوع الرسالة <span class="text-red-500">*</span></label>
        <select name="cmp_type" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition appearance-none">
          <option value="" disabled selected>اختر نوع الرسالة</option>
          <option value="complaint" <?= ($_POST['cmp_type']??'')==='complaint'?'selected':'' ?>>شكوى</option>
          <option value="suggestion" <?= ($_POST['cmp_type']??'')==='suggestion'?'selected':'' ?>>اقتراح</option>
          <option value="feedback" <?= ($_POST['cmp_type']??'')==='feedback'?'selected':'' ?>>ملاحظة</option>
          <option value="request" <?= ($_POST['cmp_type']??'')==='request'?'selected':'' ?>>طلب</option>
        </select>
      </div>
      <?php if (!$user): ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">الاسم <span class="text-red-500">*</span></label>
          <input type="text" name="cmp_name" required value="<?= htmlspecialchars($_POST['cmp_name']??'') ?>"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition"
            placeholder="اسمك الكامل">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">البريد الإلكتروني <span class="text-red-500">*</span></label>
          <input type="email" name="cmp_email" required dir="ltr" value="<?= htmlspecialchars($_POST['cmp_email']??'') ?>"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition"
            placeholder="example@domain.com">
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="cmp_name" value="<?= htmlspecialchars($user['u_name']) ?>">
      <input type="hidden" name="cmp_email" value="<?= htmlspecialchars($user['u_email']) ?>">
      <?php endif; ?>
      <div>
        <label class="block text-sm font-bold text-gray-700 mb-1.5">عنوان الرسالة <span class="text-red-500">*</span></label>
        <input type="text" name="cmp_subject" required value="<?= htmlspecialchars($_POST['cmp_subject']??'') ?>"
          class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition"
          placeholder="أدخل عنوان الرسالة">
      </div>
      <div>
        <label class="block text-sm font-bold text-gray-700 mb-1.5">محتوى الرسالة <span class="text-red-500">*</span></label>
        <textarea name="cmp_message" required rows="5"
          class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition resize-none"
          placeholder="أدخل محتوى الرسالة"><?= htmlspecialchars($_POST['cmp_message']??'') ?></textarea>
      </div>
      <button type="submit"
        class="w-full py-3.5 pi-primary-bg text-white font-black text-base rounded-xl hover:opacity-90 transition flex items-center justify-center gap-2">
        <i class="fa-solid fa-paper-plane"></i> أرسل الآن
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
