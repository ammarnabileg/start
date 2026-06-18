<?php
require_once __DIR__ . '/lib/boot.php';
require_perm('analytics', 'view');

$days  = (int) get('days', 30); if (!in_array($days,[7,30,90,365],true)) $days = 30;
$since = (new DateTime("-{$days} days"))->format('Y-m-d');

$k = [
  'users'      => (int) scalar("select count(*) from public.users"),
  'users_new'  => (int) scalar("select count(*) from public.users where created_at>=:s", ['s'=>$since]),
  'merchants'  => (int) scalar("select count(*) from public.merchants"),
  'pending'    => (int) scalar("select count(*) from public.merchants where status='pending'"),
  'subs'       => (int) scalar("select count(distinct merchant_id) from public.subscriptions where status in ('active','trial')"),
  'reports'    => (int) scalar("select count(*) from public.reports where status='open'"),
  'visits'     => (int) scalar("select count(*) from public.user_visits where visit_date>=:s", ['s'=>$since]),
  'points'     => (int) scalar("select coalesce(sum(points),0) from public.points_transactions where type='earn' and created_at>=:s", ['s'=>$since]),
];
$top = all("select m.business_name, count(*) c from public.user_stores us join public.merchants m on m.id=us.merchant_id group by 1 order by c desc limit 10");
?>
<!doctype html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
<title>تقرير المنصّة · <?= date('Y-m-d') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;800&display=swap" rel="stylesheet">
<style>
  *{font-family:'Tajawal',sans-serif;box-sizing:border-box}
  body{margin:0;padding:32px;color:#222;background:#fff}
  .bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;border-bottom:3px solid #F4B400;padding-bottom:12px}
  .brand{font-size:28px;font-weight:800;color:#F4B400}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0}
  .card{border:1px solid #e5e7eb;border-radius:10px;padding:14px}
  .card .v{font-size:26px;font-weight:800}.card .l{color:#666;font-size:13px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{text-align:right;padding:8px;border-bottom:1px solid #eee}
  th{color:#666;font-weight:700}
  .noprint{margin-bottom:16px}
  @media print{.noprint{display:none}body{padding:0}}
  button{background:#F4B400;border:0;color:#fff;font-weight:700;padding:10px 22px;border-radius:8px;cursor:pointer}
</style></head>
<body>
  <div class="noprint"><button onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
    <a href="index.php" style="margin-right:12px">رجوع</a></div>
  <div class="bar"><div class="brand">Hatchy</div>
    <div style="text-align:left"><div style="font-weight:800">تقرير المنصّة</div>
      <div style="color:#666;font-size:13px">آخر <?= $days ?> يوم · <?= date('Y-m-d H:i') ?></div></div></div>

  <div class="grid">
    <div class="card"><div class="l">إجمالي المستخدمين</div><div class="v"><?= n($k['users']) ?></div></div>
    <div class="card"><div class="l">مستخدمون جدد</div><div class="v"><?= n($k['users_new']) ?></div></div>
    <div class="card"><div class="l">التجار</div><div class="v"><?= n($k['merchants']) ?></div></div>
    <div class="card"><div class="l">بانتظار الموافقة</div><div class="v"><?= n($k['pending']) ?></div></div>
    <div class="card"><div class="l">اشتراكات فعّالة</div><div class="v"><?= n($k['subs']) ?></div></div>
    <div class="card"><div class="l">بلاغات مفتوحة</div><div class="v"><?= n($k['reports']) ?></div></div>
    <div class="card"><div class="l">زيارات الفترة</div><div class="v"><?= n($k['visits']) ?></div></div>
    <div class="card"><div class="l">نقاط مُمنوحة</div><div class="v"><?= n($k['points']) ?></div></div>
  </div>

  <h3>أكثر المتاجر عملاءً</h3>
  <table><thead><tr><th>#</th><th>المتجر</th><th>العملاء</th></tr></thead><tbody>
    <?php foreach ($top as $i => $m): ?>
      <tr><td><?= $i+1 ?></td><td><?= e($m['business_name']) ?></td><td><?= n($m['c']) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <p style="color:#999;font-size:12px;margin-top:24px">تقرير مُولّد آليًا من لوحة Hatchy.</p>
</body></html>
