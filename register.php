<?php
require_once 'includes/config.php';
$pageTitle = 'إنشاء حساب - PioneerIcons';

// Redirect if already logged in
if (pi_user_logged_in()) { header('Location: account.php'); exit; }

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password']   ?? '';
    $pass2 = $_POST['password2']  ?? '';

    if (!$name)  $errors[] = 'الاسم الكامل مطلوب';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'البريد الإلكتروني غير صحيح';
    if (strlen($pass) < 6) $errors[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    if ($pass !== $pass2) $errors[] = 'كلمتا المرور غير متطابقتين';

    if (empty($errors)) {
        $em = pi_escape($email);
        $chk = $mysqli->query("SELECT u_id FROM pi_users WHERE u_email='$em'");
        if ($chk && $chk->num_rows) {
            $errors[] = 'هذا البريد الإلكتروني مسجل مسبقاً';
        } else {
            $nm = pi_escape($name);
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $hp = pi_escape($hash);
            $mysqli->query("INSERT INTO pi_users (u_name,u_email,u_password) VALUES ('$nm','$em','$hp')");
            $_SESSION['pi_user_id'] = $mysqli->insert_id;
            header('Location: account.php');
            exit;
        }
    }
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
  <div class="w-full max-w-md">

    <!-- Logo -->
    <div class="text-center mb-8">
      <a href="index.php" class="inline-flex items-center gap-2">
        <div class="w-10 h-10 rounded-xl pi-gradient flex items-center justify-center">
          <i class="fa-solid fa-star text-purple-200 text-sm"></i>
        </div>
        <span class="font-black text-xl text-gray-800"><?= htmlspecialchars(pi_setting('site_name')) ?></span>
      </a>
      <h1 class="text-2xl font-black text-gray-800 mt-6 mb-1">إنشاء حساب جديد</h1>
      <p class="text-gray-400 text-sm">سجّل حسابك وتحكم في ملفك الشخصي</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">

      <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
        <?php foreach ($errors as $e): ?>
          <p class="text-red-700 text-sm font-semibold"><i class="fa-solid fa-circle-exclamation ml-2"></i><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">الاسم الكامل <span class="text-red-500">*</span></label>
          <input type="text" name="name" required autocomplete="name"
            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition"
            placeholder="أدخل اسمك الكامل">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">البريد الإلكتروني <span class="text-red-500">*</span></label>
          <input type="email" name="email" required dir="ltr" autocomplete="email"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition"
            placeholder="example@domain.com">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">كلمة المرور <span class="text-red-500">*</span></label>
          <input type="password" name="password" required dir="ltr" autocomplete="new-password"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition"
            placeholder="6 أحرف على الأقل">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">تأكيد كلمة المرور <span class="text-red-500">*</span></label>
          <input type="password" name="password2" required dir="ltr" autocomplete="new-password"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition"
            placeholder="أعد كتابة كلمة المرور">
        </div>
        <button type="submit"
          class="w-full py-3.5 text-white font-black text-base rounded-xl hover:opacity-90 transition flex items-center justify-center gap-2"
          style="background:linear-gradient(135deg,#8829C8,#5B1494)">
          <i class="fa-solid fa-user-plus"></i> إنشاء الحساب
        </button>
      </form>

      <p class="text-center text-sm text-gray-400 mt-6">
        لديك حساب؟
        <a href="user_login.php" class="text-purple-600 font-bold hover:underline">تسجيل الدخول</a>
      </p>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
