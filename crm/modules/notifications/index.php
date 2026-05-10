<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = 'الإشعارات';
$uid = auth_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        db_exec('UPDATE ' . tbl('notifications') . ' SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL', ['u' => $uid]);
    } elseif ($action === 'mark_read' && !empty($_POST['id'])) {
        db_exec('UPDATE ' . tbl('notifications') . ' SET read_at = NOW() WHERE id = :id AND user_id = :u', ['id' => (int)$_POST['id'], 'u' => $uid]);
    } elseif ($action === 'clear_all') {
        db_delete(tbl('notifications'), 'user_id = :u', ['u' => $uid]);
    }
    redirect('modules/notifications/');
}

$notifications = db_all('
    SELECT * FROM ' . tbl('notifications') . '
    WHERE user_id = :u
    ORDER BY created_at DESC
    LIMIT 100
', ['u' => $uid]);

require __DIR__ . '/../../includes/header.php';
?>

<div class="flex justify-between mb-4">
  <form method="post" class="flex gap-2">
    <?= csrf_field() ?>
    <button name="action" value="mark_all_read" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">تحديد الكل مقروء</button>
    <button name="action" value="clear_all" onclick="return confirm('حذف كل الإشعارات؟')" class="px-4 py-2 border rounded-lg text-sm">مسح الكل</button>
  </form>
</div>

<div class="bg-white rounded-xl shadow-sm border divide-y">
  <?php foreach ($notifications as $n): ?>
    <div class="p-4 flex justify-between items-start gap-4 <?= $n['read_at'] ? 'opacity-60' : 'bg-emerald-50/30' ?>">
      <div class="flex gap-3 flex-1 min-w-0">
        <div class="text-2xl"><?= e($n['icon'] ?? '🔔') ?></div>
        <div class="flex-1 min-w-0">
          <div class="font-medium"><?= e($n['title']) ?></div>
          <?php if ($n['body']): ?><div class="text-sm text-gray-600 mt-1"><?= e($n['body']) ?></div><?php endif; ?>
          <div class="flex gap-3 items-center mt-2 text-xs text-gray-400">
            <span><?= time_ago($n['created_at']) ?></span>
            <?php if ($n['link']): ?>
              <a href="<?= e($n['link']) ?>" class="text-emerald-600 hover:underline">فتح ←</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if (!$n['read_at']): ?>
        <form method="post"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $n['id'] ?>">
          <button name="action" value="mark_read" class="text-xs text-gray-500 hover:underline">قراءة</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$notifications): ?>
    <div class="p-12 text-center text-gray-500">لا توجد إشعارات.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
