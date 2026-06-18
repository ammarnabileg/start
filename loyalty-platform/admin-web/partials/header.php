<?php
/** @var string $title */
$admin = current_admin();
$cur = basename($_SERVER['PHP_SELF']);
$brand = cfg()['brand'];
function navlink(string $file, string $res, string $label, string $icon, string $cur): void {
  if (!can($res, 'view')) return;
  $active = str_starts_with($cur, explode('.', $file)[0]);
  $cls = $active ? 'bg-amber-500 text-white' : 'text-gray-300 hover:bg-gray-700/60';
  echo '<a href="' . $file . '" class="flex items-center gap-3 px-4 py-2.5 rounded-lg ' . $cls . '">'
     . '<span class="text-lg w-5 text-center">' . $icon . '</span><span>' . e($label) . '</span></a>';
}
?>
<!doctype html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Hatchy Admin') ?> · <?= e(cfg()['app_name']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
  body{font-family:'Tajawal',sans-serif}
  ::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px}
</style>
<script>tailwind.config={theme:{extend:{colors:{brand:'<?= e($brand) ?>'}}}}</script>
</head>
<body class="bg-gray-100 text-gray-800">
<div class="flex min-h-screen">
  <!-- الشريط الجانبي -->
  <aside class="w-64 bg-gray-900 text-white flex flex-col fixed inset-y-0 right-0 z-20">
    <div class="px-5 py-5 border-b border-gray-700/60">
      <div class="text-2xl font-extrabold" style="color:<?= e($brand) ?>">Hatchy</div>
      <div class="text-xs text-gray-400">لوحة تحكم المنصّة</div>
    </div>
    <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
      <?php
        navlink('index.php','dashboard','لوحة التحكم','▣',$cur);
        navlink('analytics.php','analytics','التحليلات','📈',$cur);
        navlink('merchants.php','merchants','التجار (CRM)','🏪',$cur);
        navlink('finance.php','finance','المالية والاشتراكات','💳',$cur);
        navlink('users.php','users','المستخدمون','👥',$cur);
        navlink('points.php','points','منح/خصم النقاط','⭐',$cur);
        navlink('devices.php','devices','الأجهزة والحظر','🚫',$cur);
        navlink('lists.php','lists','القوائم/الشرائح','◑',$cur);
        navlink('notifications.php','notifications','الإشعارات','🔔',$cur);
        navlink('reports.php','reports','الشكاوى والبلاغات','⚑',$cur);
        navlink('content.php','content','مركز المحتوى','📝',$cur);
        echo '<div class="pt-3 mt-2 border-t border-gray-700/60"></div>';
        navlink('admins.php','admins','حسابات المسؤولين','🛡',$cur);
        navlink('roles.php','roles','الأدوار والصلاحيات','🔑',$cur);
        navlink('audit.php','audit','سجلّ التدقيق','🗒',$cur);
        // أمني شخصي (2FA) — متاح لكل مسؤول
        $sa = str_starts_with($cur, 'security') ? 'bg-amber-500 text-white' : 'text-gray-300 hover:bg-gray-700/60';
        echo '<a href="security.php" class="flex items-center gap-3 px-4 py-2.5 rounded-lg '.$sa.'"><span class="text-lg w-5 text-center">🔐</span><span>الأمان (2FA)</span></a>';
      ?>
    </nav>
    <div class="p-4 border-t border-gray-700/60 text-sm">
      <div class="font-bold"><?= e($admin['name'] ?? '') ?></div>
      <div class="text-gray-400 text-xs mb-2"><?= e($admin['role_name'] ?? '') ?></div>
      <a href="logout.php" class="text-red-300 hover:text-red-200 text-xs">تسجيل الخروج ←</a>
    </div>
  </aside>

  <!-- المحتوى -->
  <main class="flex-1 mr-64">
    <header class="bg-white border-b px-6 py-4 sticky top-0 z-10 flex items-center justify-between">
      <h1 class="text-xl font-extrabold"><?= e($title ?? '') ?></h1>
      <div class="text-sm text-gray-500"><?= date('Y-m-d') ?></div>
    </header>
    <div class="p-6">
      <?php foreach (take_flash() as $f):
        $c = ['success'=>'bg-green-50 text-green-700 border-green-200','error'=>'bg-red-50 text-red-700 border-red-200','info'=>'bg-blue-50 text-blue-700 border-blue-200'][$f['t']] ?? 'bg-gray-50'; ?>
        <div class="mb-4 px-4 py-3 rounded-lg border <?= $c ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>
