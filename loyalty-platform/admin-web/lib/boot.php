<?php
// نقطة تحميل موحّدة لكل الصفحات.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/export.php';
require_once __DIR__ . '/xlsx.php';
require_once __DIR__ . '/smartlist.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/audit.php';
boot_session();

// تسجيل الأخطاء غير المُلتقَطة في admin.error_log (للوحة صحة النظام).
set_exception_handler(function (Throwable $ex) {
  try {
    q("insert into admin.error_log (context, message) values ('php', :m)",
      ['m' => substr($ex->getMessage() . ' @ ' . basename($ex->getFile()) . ':' . $ex->getLine(), 0, 2000)]);
  } catch (Throwable $e) { /* تجاهل */ }
  http_response_code(500);
  echo 'حدث خطأ غير متوقّع وسُجِّل للمراجعة.';
});
