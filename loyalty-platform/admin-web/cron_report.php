<?php
// تقرير مجدول بالبريد (XLSX مرفق). شغّله عبر cron:
//   0 7 * * 1  php /path/admin-web/cron_report.php
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/export.php';
require __DIR__ . '/lib/xlsx.php';
require __DIR__ . '/lib/settings.php';

$recipients = setting_get('report_recipients', []);
if (!is_array($recipients) || !$recipients) { fwrite(STDERR, "لا مستلمين (admin.settings.report_recipients)\n"); exit(0); }

$since = date('Y-m-d', strtotime('-7 days'));
$kpi = [
  ['إجمالي المستخدمين', (int) scalar("select count(*) from public.users")],
  ['مستخدمون جدد (٧ أيام)', (int) scalar("select count(*) from public.users where created_at>=:s", ['s'=>$since])],
  ['إجمالي التجار', (int) scalar("select count(*) from public.merchants")],
  ['بانتظار الموافقة', (int) scalar("select count(*) from public.merchants where status='pending'")],
  ['اشتراكات فعّالة', (int) scalar("select count(distinct merchant_id) from public.subscriptions where status in ('active','trial')")],
  ['بلاغات مفتوحة', (int) scalar("select count(*) from public.reports where status='open'")],
  ['زيارات (٧ أيام)', (int) scalar("select count(*) from public.user_visits where visit_date>=:s", ['s'=>$since])],
  ['نقاط مُمنوحة (٧ أيام)', (int) scalar("select coalesce(sum(points),0) from public.points_transactions where type='earn' and created_at>=:s", ['s'=>$since])],
];
$xlsx = xlsx_bytes(['المؤشّر', 'القيمة'], $kpi);
if ($xlsx === '') { fwrite(STDERR, "تعذّر توليد XLSX (ZipArchive غير متاح)\n"); exit(1); }

$subject = '=?UTF-8?B?' . base64_encode('تقرير Hatchy الأسبوعي ' . date('Y-m-d')) . '?=';
$boundary = 'b' . md5((string) time());
$headers  = "From: Hatchy Admin <no-reply@hatchy>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
$body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nمرفق تقرير المنصّة الأسبوعي.\r\n\r\n";
$body .= "--$boundary\r\nContent-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; name=\"hatchy-report.xlsx\"\r\n";
$body .= "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"hatchy-report.xlsx\"\r\n\r\n";
$body .= chunk_split(base64_encode($xlsx)) . "--$boundary--";

$ok = 0;
foreach ($recipients as $to) if (@mail($to, $subject, $body, $headers)) $ok++;
echo "أُرسل التقرير إلى $ok/" . count($recipients) . " مستلم.\n";
if ($ok === 0) {
  try { q("insert into admin.error_log (context,message) values ('cron_report','mail() فشل — تحقّق من إعداد SMTP')"); } catch (Throwable $e) {}
}
