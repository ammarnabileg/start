<?php
require_once __DIR__ . '/auth.php';
auth_start_session();

$current = basename($_SERVER['PHP_SELF'] ?? '');
$user = auth_user();
?>
<!DOCTYPE html>
<html lang="<?= e(CRM_LOCALE) ?>" dir="<?= CRM_LOCALE === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? CRM_APP_NAME) ?> · <?= e(CRM_APP_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/style.css') ?>">
<script>
tailwind.config = {
  theme: {
    fontFamily: { sans: ['Cairo', 'system-ui', 'sans-serif'] },
    extend: {
      colors: {
        brand: { 50:'#ecfdf5',100:'#d1fae5',500:'#10b981',600:'#059669',700:'#047857',900:'#064e3b' },
      }
    }
  }
};
</script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">
<?php if ($user): ?>
<div class="min-h-screen flex">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="flex-1 flex flex-col min-w-0">
    <header class="bg-white border-b sticky top-0 z-10">
      <div class="px-6 py-3 flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold"><?= e($pageTitle ?? 'لوحة التحكم') ?></h1>
          <?php if (!empty($pageSubtitle)): ?>
            <p class="text-sm text-gray-500"><?= e($pageSubtitle) ?></p>
          <?php endif; ?>
        </div>
        <div class="flex items-center gap-4">
          <?php
            $unread = (int)db_scalar('SELECT COUNT(*) FROM ' . tbl('notifications') . ' WHERE user_id = :u AND read_at IS NULL', ['u' => $user['id']]);
            $myStats = db_one('SELECT level, total_xp, current_streak FROM ' . tbl('user_stats') . ' WHERE user_id = :u', ['u' => $user['id']]);
          ?>
          <?php if ($myStats): ?>
            <a href="<?= url('modules/arena/') ?>" class="flex items-center gap-2 text-sm bg-gradient-to-l from-purple-100 to-emerald-100 px-3 py-1 rounded-full hover:scale-105 transition">
              <span>⚡L<?= (int)$myStats['level'] ?></span>
              <?php if ((int)$myStats['current_streak']): ?><span>🔥<?= (int)$myStats['current_streak'] ?></span><?php endif; ?>
            </a>
          <?php endif; ?>
          <a href="<?= url('modules/notifications/') ?>" class="relative text-xl hover:opacity-70" title="الإشعارات">
            🔔
            <?php if ($unread): ?>
              <span class="absolute -top-1 -left-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?= $unread > 9 ? '9+' : $unread ?></span>
            <?php endif; ?>
          </a>
          <span class="text-sm text-gray-600">مرحبًا، <?= e($user['name']) ?></span>
          <a href="<?= url('logout.php') ?>" class="text-sm text-red-600 hover:underline">خروج</a>
        </div>
      </div>
    </header>

    <main class="flex-1 p-6">
      <?php foreach (flash_get() as $f): ?>
        <div class="mb-4 px-4 py-3 rounded-lg <?= $f['type']==='error' ? 'bg-red-50 text-red-700 border border-red-200' : ($f['type']==='success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200') ?>">
          <?= e($f['message']) ?>
        </div>
      <?php endforeach; ?>
<?php else: ?>
<div>
<?php endif; ?>
