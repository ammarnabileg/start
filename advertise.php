<?php
$pageTitle = 'أعلن معنا - PioneerIcons';
require_once 'includes/config.php';

$mysqli->query("CREATE TABLE IF NOT EXISTS pi_advertise (
  adv_id INT AUTO_INCREMENT PRIMARY KEY,
  adv_company VARCHAR(200) NOT NULL,
  adv_contact VARCHAR(200) NOT NULL,
  adv_phone VARCHAR(50) NOT NULL,
  adv_email VARCHAR(200) NOT NULL,
  adv_note TEXT,
  adv_status ENUM('new','contacted','done') DEFAULT 'new',
  adv_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company = trim($_POST['adv_company'] ?? '');
    $contact = trim($_POST['adv_contact'] ?? '');
    $phone   = trim($_POST['adv_phone'] ?? '');
    $email   = trim($_POST['adv_email'] ?? '');
    $note    = trim($_POST['adv_note'] ?? '');
    if (!$company) $errors[] = 'اسم الشركة مطلوب';
    if (!$contact) $errors[] = 'اسم المسؤول مطلوب';
    if (!$phone)   $errors[] = 'رقم الهاتف مطلوب';
    if (!$email)   $errors[] = 'البريد الإلكتروني مطلوب';
    if (empty($errors)) {
        $c=pi_escape($company);$cn=pi_escape($contact);$p=pi_escape($phone);$e=pi_escape($email);$n=pi_escape($note);
        $mysqli->query("INSERT INTO pi_advertise(adv_company,adv_contact,adv_phone,adv_email,adv_note)VALUES('$c','$cn','$p','$e','$n')");
        $success = true;
    }
}
include 'includes/header.php';
?>
<section class="hero-bg py-16 text-white">
  <div class="hero-glow"></div>
  <div class="max-w-3xl mx-auto px-4 text-center relative z-10">
    <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-5 py-2 text-sm font-semibold mb-5 text-purple-200">
      <i class="fa-solid fa-bullhorn text-purple-300 text-xs"></i> وصولك إلى صُنّاع القرار العرب
    </div>
    <h1 class="text-4xl md:text-5xl font-black mb-4">أعلن معنا</h1>
    <p class="text-purple-200 text-lg font-medium">اعرض علامتك التجارية أمام آلاف الشخصيات والمؤسسات العربية</p>
  </div>
</section>
<section class="bg-white py-10 border-b border-gray-100">
  <div class="max-w-4xl mx-auto px-4">
    <div class="grid grid-cols-3 gap-8 text-center">
      <div><p class="text-3xl font-black text-purple-700">+<?= number_format(pi_count_personalities()) ?></p><p class="text-gray-500 text-sm font-semibold mt-1">شخصية موثقة</p></div>
      <div><p class="text-3xl font-black text-purple-700">+<?= number_format(pi_count_institutions()) ?></p><p class="text-gray-500 text-sm font-semibold mt-1">مؤسسة وشركة</p></div>
      <div><p class="text-3xl font-black text-purple-700">17+</p><p class="text-gray-500 text-sm font-semibold mt-1">دولة عربية</p></div>
    </div>
  </div>
</section>
<div class="max-w-2xl mx-auto px-4 py-12">
  <?php if ($success): ?>
  <div class="bg-green-50 border border-green-200 rounded-2xl p-10 text-center">
    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fa-solid fa-circle-check text-green-500 text-3xl"></i></div>
    <h2 class="text-xl font-black text-green-800 mb-2">تم استلام طلبك!</h2>
    <p class="text-green-600 mb-5">سيتواصل معك فريقنا خلال 24 ساعة</p>
    <a href="index.php" class="inline-block px-8 py-3 pi-primary-bg text-white font-bold rounded-xl hover:opacity-90 transition">العودة للرئيسية</a>
  </div>
  <?php else: ?>
  <?php if (!empty($errors)): ?><div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6"><?php foreach($errors as $e): ?><p class="text-red-700 text-sm font-semibold"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?></div><?php endif; ?>
  <div class="bg-white rounded-2xl shadow-sm p-8">
    <h2 class="font-black text-gray-800 text-xl mb-1">أرسل طلبك الآن</h2>
    <p class="text-gray-400 text-sm mb-6">وسيتواصل معك فريقنا في أقرب وقت</p>
    <form method="POST" class="space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div><label class="block text-sm font-bold text-gray-700 mb-1.5">اسم الشركة <span class="text-red-500">*</span></label><input type="text" name="adv_company" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['adv_company']??'') ?>"></div>
        <div><label class="block text-sm font-bold text-gray-700 mb-1.5">اسم المسؤول <span class="text-red-500">*</span></label><input type="text" name="adv_contact" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['adv_contact']??'') ?>"></div>
        <div><label class="block text-sm font-bold text-gray-700 mb-1.5">رقم الهاتف <span class="text-red-500">*</span></label><input type="tel" name="adv_phone" required dir="ltr" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['adv_phone']??'') ?>"></div>
        <div><label class="block text-sm font-bold text-gray-700 mb-1.5">البريد الإلكتروني <span class="text-red-500">*</span></label><input type="email" name="adv_email" required dir="ltr" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['adv_email']??'') ?>"></div>
      </div>
      <div><label class="block text-sm font-bold text-gray-700 mb-1.5">ملاحظات أو متطلبات إضافية</label><textarea name="adv_note" rows="4" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition resize-y" placeholder="أخبرنا عن شركتك وأهدافك الإعلانية..."><?= htmlspecialchars($_POST['adv_note']??'') ?></textarea></div>
      <button type="submit" class="w-full py-4 pi-primary-bg text-white font-black text-base rounded-xl hover:opacity-90 transition flex items-center justify-center gap-2"><i class="fa-solid fa-paper-plane"></i> إرسال طلب الإعلان</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>
