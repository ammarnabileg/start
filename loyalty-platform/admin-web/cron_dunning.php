<?php
// تذكيرات تجديد تلقائية للاشتراكات المتأخّرة/المنتهية. شغّله يوميًا:
//   0 9 * * *  php /path/admin-web/cron_dunning.php
// لا يُكرّر التذكير لنفس المتجر خلال ٧ أيام.
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';

$rows = all("select s.merchant_id, s.status from public.subscriptions s
   where s.status in ('past_due','expired')
     and not exists (select 1 from admin.dunning_log d
                     where d.merchant_id=s.merchant_id and d.created_at > now()-interval '7 days')");

$merchants = 0; $notified = 0;
foreach ($rows as $r) {
  $owners = all("select user_id from public.merchant_staff
                 where merchant_id=:m and user_id is not null and role in ('merchant_owner','manager')", ['m'=>$r['merchant_id']]);
  foreach ($owners as $o) {
    q("insert into public.notifications (user_id,type,title,body,data)
       values (:u,'subscription','تذكير باشتراكك','اشتراك متجرك يحتاج إلى تجديد لاستمرار الخدمة.', jsonb_build_object('source','admin'))",
      ['u'=>$o['user_id']]);
    $notified++;
  }
  q("insert into admin.dunning_log (merchant_id, sub_status, channel) values (:m,:s,'in_app')", ['m'=>$r['merchant_id'],'s'=>$r['status']]);
  $merchants++;
}
echo "تذكيرات Dunning: $merchants متجر · $notified إشعار.\n";
