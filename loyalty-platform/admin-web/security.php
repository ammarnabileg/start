<?php
require_once __DIR__ . '/lib/boot.php';
$me = require_login();

if (is_post()) {
  csrf_check();
  $act = (string) post('action');
  if ($act === 'enable_init') {
    $_SESSION['2fa_new'] = totp_secret();
  } elseif ($act === 'enable_confirm') {
    $sec = $_SESSION['2fa_new'] ?? '';
    if ($sec && totp_verify($sec, (string)post('code'))) {
      q("update admin.users set totp_secret=:s where id=:id", ['s'=>$sec,'id'=>$me['id']]);
      unset($_SESSION['2fa_new']); audit('2fa_enable','admin',$me['id']); flash('تم تفعيل المصادقة الثنائية.');
    } else flash('رمز غير صحيح، حاول مجددًا.', 'error');
  } elseif ($act === 'disable') {
    if (!empty($me['totp_secret']) && totp_verify($me['totp_secret'], (string)post('code'))) {
      q("update admin.users set totp_secret=null where id=:id", ['id'=>$me['id']]);
      audit('2fa_disable','admin',$me['id']); flash('تم تعطيل المصادقة الثنائية.');
    } else flash('رمز غير صحيح.', 'error');
  }
  redirect('security.php');
}

$me = current_admin(); // refresh
$pending = $_SESSION['2fa_new'] ?? null;
$logs = !empty($me['is_super'])
  ? all("select * from admin.login_log order by created_at desc limit 30")
  : all("select * from admin.login_log where admin_id=:id order by created_at desc limit 30", ['id'=>$me['id']]);

$title = 'الأمان (المصادقة الثنائية)';
require __DIR__ . '/partials/header.php';
?>
<div class="grid md:grid-cols-2 gap-6">
  <div class="bg-white rounded-xl border p-5">
    <div class="font-bold mb-3">المصادقة الثنائية (TOTP)</div>
    <?php if (!empty($me['totp_secret'])): ?>
      <div class="px-4 py-3 rounded-lg bg-green-50 text-green-700 border border-green-200 mb-4">مفعّلة على حسابك ✓</div>
      <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="disable">
        <input name="code" placeholder="رمز التحقق للتعطيل" class="flex-1 border rounded-lg px-3 py-2">
        <button class="bg-red-100 text-red-700 rounded-lg px-4 font-bold">تعطيل</button></form>
    <?php elseif ($pending): ?>
      <p class="text-sm text-gray-600 mb-3">امسح الرمز بتطبيق المصادقة ثم أدخل الرمز للتأكيد:</p>
      <div class="flex flex-col items-center gap-2 mb-4">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode(totp_uri($pending, $me['email'])) ?>" alt="QR" class="rounded-lg border">
        <code class="text-xs bg-gray-100 px-2 py-1 rounded ltr"><?= e($pending) ?></code>
      </div>
      <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="enable_confirm">
        <input name="code" placeholder="رمز ٦ أرقام" class="flex-1 border rounded-lg px-3 py-2 text-center tracking-widest">
        <button class="bg-amber-500 hover:bg-amber-600 text-white rounded-lg px-4 font-bold">تفعيل</button></form>
    <?php else: ?>
      <p class="text-sm text-gray-600 mb-3">أضف طبقة حماية ثانية عند تسجيل الدخول.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="enable_init">
        <button class="bg-gray-800 text-white rounded-lg px-5 py-2 font-bold">تفعيل المصادقة الثنائية</button></form>
    <?php endif; ?>
  </div>

  <div class="bg-white rounded-xl border overflow-hidden">
    <div class="px-5 py-3 border-b font-bold">سجلّ الدخول <?= !empty($me['is_super'])?'(كل المسؤولين)':'' ?></div>
    <table class="w-full text-sm"><tbody>
      <?php foreach ($logs as $l): ?>
        <tr class="border-t"><td class="px-4 py-2 text-gray-500 whitespace-nowrap"><?= dt($l['created_at']) ?></td>
          <td class="px-4 py-2"><?= e($l['email']) ?></td>
          <td class="px-4 py-2 text-xs text-gray-400 ltr"><?= e($l['ip']) ?></td>
          <td class="px-4 py-2"><?= $l['ok']?badge('نجح','green'):badge('فشل','red') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td class="px-4 py-6 text-gray-400">لا سجلات.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
