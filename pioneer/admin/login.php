<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = pi_escape($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $r = $mysqli->query("SELECT * FROM pi_admin_users WHERE au_email='$email' AND au_active=1");
    if ($r && $r->num_rows) {
        $user = $r->fetch_assoc();
        if (password_verify($password, $user['au_password'])) {
            $_SESSION['pi_admin_id'] = $user['au_id'];
            header('Location: admin.php?p=dashboard');
            exit;
        }
    }
    $login_error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تسجيل الدخول - PioneerIcons</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>* { font-family: 'Cairo', sans-serif; } .pi-gradient { background: linear-gradient(135deg, #1a3a6b 0%, #0f2548 100%); }</style>
</head>
<body class="min-h-screen pi-gradient flex items-center justify-center p-4">
  <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8">
    <div class="text-center mb-8">
      <div class="w-16 h-16 rounded-2xl bg-orange-500 flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-star text-white text-2xl"></i>
      </div>
      <h1 class="text-2xl font-black text-gray-800">تسجيل الدخول</h1>
      <p class="text-gray-400 text-sm mt-1">لوحة تحكم PioneerIcons</p>
    </div>

    <?php if (!empty($login_error)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm font-semibold">
      <i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($login_error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-4">
        <label class="block text-sm font-bold text-gray-700 mb-1.5">البريد الإلكتروني</label>
        <input type="email" name="email" required
          class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-orange-400 transition"
          placeholder="admin@pioneericons.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-6">
        <label class="block text-sm font-bold text-gray-700 mb-1.5">كلمة المرور</label>
        <input type="password" name="password" required
          class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-orange-400 transition"
          placeholder="••••••••">
      </div>
      <button type="submit"
        class="w-full bg-orange-500 text-white font-black py-3.5 rounded-xl hover:bg-orange-600 transition text-base">
        دخول <i class="fa-solid fa-arrow-left mr-2"></i>
      </button>
    </form>

    <div class="mt-6 text-center">
      <a href="index.php" class="text-sm text-gray-400 hover:text-orange-500 transition">
        <i class="fa-solid fa-arrow-right text-xs mr-1"></i> العودة للموقع
      </a>
    </div>
  </div>
</body>
</html>
<?php exit; ?>
