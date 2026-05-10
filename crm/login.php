<?php
require_once __DIR__ . '/includes/auth.php';
auth_start_session();

if (auth_check()) redirect('dashboard.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $error = 'الرجاء إدخال البريد وكلمة المرور.';
    } elseif (auth_login($email, $pass)) {
        $next = $_GET['next'] ?? url('dashboard.php');
        header('Location: ' . $next);
        exit;
    } else {
        $error = 'بيانات الدخول غير صحيحة.';
    }
}
$pageTitle = 'تسجيل الدخول';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسجيل الدخول · <?= e(CRM_APP_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>body{font-family:Cairo,sans-serif}</style>
</head>
<body class="bg-gradient-to-br from-emerald-700 to-emerald-900 min-h-screen flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
    <div class="text-center mb-8">
      <div class="text-5xl mb-2">⚡</div>
      <h1 class="text-2xl font-bold text-gray-800"><?= e(CRM_APP_NAME) ?></h1>
      <p class="text-sm text-gray-500 mt-1">منصة التشغيل الذكية</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
        <input type="email" name="email" required autofocus
               value="<?= e($_POST['email'] ?? '') ?>"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
        <input type="password" name="password" required
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
      </div>
      <button type="submit" class="w-full bg-emerald-600 text-white py-2.5 rounded-lg hover:bg-emerald-700 font-medium transition">
        تسجيل الدخول
      </button>
    </form>

    <p class="text-center text-xs text-gray-400 mt-6">
      © <?= date('Y') ?> · جميع الحقوق محفوظة
    </p>
  </div>
</body>
</html>
