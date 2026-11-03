<?php
if (current_user()) {
    redirect(url('dashboard'));
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $email = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (too_many_attempts($pdo)) {
        $error = 'محاولات كتير غلط. استنى 15 دقيقة وجرب تاني.';
    } else {
        $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            clear_attempts($pdo);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            log_activity('login');
            redirect(url('dashboard'));
        }
        record_failed_attempt($pdo);
        $error = 'الإيميل أو كلمة السر غلط.';
    }
}
$siteName = setting('site_name', 'لوحة النشر');
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول · <?= e($siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Cairo','sans-serif']}}}}</script>
</head>
<body class="bg-slate-50 font-sans min-h-screen flex items-center justify-center p-4">
  <form method="post" class="w-full max-w-sm bg-white border border-slate-200 rounded-2xl p-8 shadow-sm space-y-5">
    <?= csrf_field() ?>
    <div class="text-center">
      <h1 class="text-2xl font-bold text-indigo-700"><?= e($siteName) ?></h1>
      <p class="text-slate-500 text-sm mt-1">سجّل دخولك</p>
    </div>
    <?php if ($error): ?><div class="bg-red-50 text-red-800 border border-red-200 rounded-lg p-3 text-sm"><?= e($error) ?></div><?php endif; ?>
    <label class="block"><span class="text-sm font-semibold">الإيميل</span>
      <input type="email" name="email" value="<?= e($email) ?>" required autofocus class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none" dir="ltr"></label>
    <label class="block"><span class="text-sm font-semibold">كلمة السر</span>
      <input type="password" name="password" required class="mt-1 w-full border border-slate-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-indigo-500 outline-none" dir="ltr"></label>
    <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-3">دخول</button>
  </form>
</body>
</html>
<?php exit;
