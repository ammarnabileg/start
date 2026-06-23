<?php
// مُثبِّت لمرة واحدة: ينشئ سكيمة admin + أول حساب Super Admin.
require_once __DIR__ . '/lib/boot.php';

$step = 'check'; $err = null;

try {
  db(); // اختبار الاتصال
  // طبّق السكيمة (آمن للتكرار — كله if not exists / on conflict)
  $sql = file_get_contents(__DIR__ . '/sql/admin_schema.sql');
  db()->exec($sql);
  $haveAdmin = (int) scalar("select count(*) from admin.users");
} catch (Throwable $ex) {
  $err = $ex->getMessage();
  $haveAdmin = null;
}

if ($haveAdmin) { $step = 'done'; }

if ($step !== 'done' && !$err && is_post()) {
  csrf_check();
  $email = trim((string)post('email'));
  $name  = trim((string)post('name'));
  $pass  = (string)post('password');
  if (!$email || !$name || strlen($pass) < 8) {
    $err = 'أدخل بريدًا واسمًا وكلمة مرور (٨ أحرف فأكثر).';
  } else {
    $roleId = scalar("select id from admin.roles where is_super limit 1");
    q("insert into admin.users (email,password_hash,name,role_id) values (:e,:p,:n,:r)", [
      'e' => $email, 'p' => password_hash($pass, PASSWORD_BCRYPT), 'n' => $name, 'r' => $roleId,
    ]);
    flash('تم إنشاء حساب المسؤول الأول. سجّل الدخول الآن.');
    redirect('login.php');
  }
}
?>
<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>تثبيت Hatchy Admin</title>
<link rel="stylesheet" href="assets/tailwind.css"></head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8">
  <h1 class="text-2xl font-extrabold mb-1">تثبيت لوحة Hatchy</h1>
  <p class="text-gray-500 text-sm mb-6">إنشاء أول حساب Super Admin.</p>
  <?php if ($err): ?>
    <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 text-red-700 border border-red-200 text-sm"><?= e($err) ?></div>
  <?php endif; ?>
  <?php if ($step === 'done'): ?>
    <div class="px-4 py-3 rounded-lg bg-green-50 text-green-700 border border-green-200">
      التثبيت مكتمل ويوجد مسؤول بالفعل. <a class="font-bold underline" href="login.php">تسجيل الدخول</a>
    </div>
  <?php elseif (!$err): ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input name="name" placeholder="الاسم" required class="w-full border rounded-lg px-4 py-2.5">
      <input name="email" type="email" placeholder="البريد الإلكتروني" required class="w-full border rounded-lg px-4 py-2.5">
      <input name="password" type="password" placeholder="كلمة المرور (٨+)" required class="w-full border rounded-lg px-4 py-2.5">
      <button class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg py-2.5">إنشاء الحساب</button>
    </form>
  <?php endif; ?>
</div></body></html>
