<?php
require_once __DIR__ . '/lib/boot.php';
if (current_admin()) redirect('index.php');

$need2fa = !empty($_SESSION['2fa_pending']);

if (is_post()) {
  csrf_check();
  if (!empty($_SESSION['2fa_pending']) && post('code') !== null) {
    if (complete_2fa((string)post('code'))) { audit('login', 'admin', current_admin()['id']); redirect('index.php'); }
    flash('رمز التحقق غير صحيح.', 'error');
    $need2fa = true;
  } else {
    $st = attempt_login((string)post('email'), (string)post('password'));
    if ($st === 'ok')        { audit('login', 'admin', current_admin()['id']); redirect('index.php'); }
    if ($st === 'need_2fa')  { $need2fa = true; }
    elseif ($st === 'locked'){ flash('الحساب مقفل مؤقتًا بعد محاولات فاشلة. حاول بعد ١٥ دقيقة.', 'error'); }
    else                     { flash('بيانات الدخول غير صحيحة أو الحساب موقوف.', 'error'); }
  }
}
?>
<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>دخول · Hatchy Admin</title>
<link rel="stylesheet" href="assets/tailwind.css"></head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-8">
  <div class="text-center mb-6">
    <div class="text-3xl font-extrabold" style="color:<?= e(cfg()['brand']) ?>">Hatchy</div>
    <div class="text-gray-500 text-sm">لوحة تحكم المنصّة</div>
  </div>
  <?php foreach (take_flash() as $f): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm"><?= e($f['m']) ?></div>
  <?php endforeach; ?>
  <form method="post" class="space-y-4"><?= csrf_field() ?>
    <?php if ($need2fa): ?>
      <p class="text-sm text-gray-600">أدخل الرمز من تطبيق المصادقة (Google Authenticator).</p>
      <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="رمز التحقق (٦ أرقام)" required autofocus
             class="w-full border rounded-lg px-4 py-2.5 text-center tracking-widest text-lg">
      <button class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg py-2.5">تأكيد</button>
    <?php else: ?>
      <input name="email" type="email" placeholder="البريد الإلكتروني" required class="w-full border rounded-lg px-4 py-2.5">
      <input name="password" type="password" placeholder="كلمة المرور" required class="w-full border rounded-lg px-4 py-2.5">
      <button class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg py-2.5">دخول</button>
    <?php endif; ?>
  </form>
</div></body></html>
