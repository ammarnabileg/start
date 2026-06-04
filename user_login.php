<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'includes/config.php';
$pageTitle = 'تسجيل الدخول - PioneerIcons';

if (pi_user_logged_in()) { header('Location: account.php'); exit; }

$error = '';
$redirect = pi_escape($_GET['redirect'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $red   = $_POST['redirect'] ?? '';

    if (!$email || !$pass) {
        $error = 'يرجى إدخال البريد الإلكتروني وكلمة المرور';
    } else {
        $em = pi_escape($email);
        $r  = $mysqli->query("SELECT * FROM pi_users WHERE u_email='$em' AND u_active=1");
        $user = $r && $r->num_rows ? $r->fetch_assoc() : null;
        if ($user && password_verify($pass, $user['u_password'])) {
            $_SESSION['pi_user_id'] = $user['u_id'];
            $dest = ($red && strpos($red, 'http') === false) ? $red : 'account.php';
            header('Location: ' . $dest);
            exit;
        } else {
            $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
        }
    }
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
  <div class="w-full max-w-md">

    <div class="text-center mb-8">
      <a href="index.php" class="inline-flex items-center gap-2">
        <div class="w-10 h-10 rounded-xl pi-gradient flex items-center justify-center">
          <i class="fa-solid fa-star text-purple-200 text-sm"></i>
        </div>
        <span class="font-black text-xl text-gray-800"><?= htmlspecialchars(pi_setting('site_name')) ?></span>
      </a>
      <h1 class="text-2xl font-black text-gray-800 mt-6 mb-1">تسجيل الدخول</h1>
      <p class="text-gray-400 text-sm">أهلاً بك — أدخل بياناتك للمتابعة</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">

      <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
        <p class="text-red-700 text-sm font-semibold"><i class="fa-solid fa-circle-exclamation ml-2"></i><?= htmlspecialchars($error) ?></p>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">البريد الإلكتروني</label>
          <input type="email" name="email" required dir="ltr" autocomplete="email"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition"
            placeholder="example@domain.com">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">كلمة المرور</label>
          <input type="password" name="password" required dir="ltr" autocomplete="current-password"
            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition"
            placeholder="••••••••">
        </div>
        <button type="submit"
          class="w-full py-3.5 text-white font-black text-base rounded-xl hover:opacity-90 transition flex items-center justify-center gap-2"
          style="background:linear-gradient(135deg,#8829C8,#5B1494)">
          <i class="fa-solid fa-right-to-bracket"></i> دخول
        </button>
      </form>

      <p class="text-center text-sm text-gray-400 mt-6">
        ليس لديك حساب؟
        <a href="register.php" class="text-purple-600 font-bold hover:underline">إنشاء حساب</a>
      </p>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
