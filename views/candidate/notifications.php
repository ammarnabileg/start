<?php
// Candidate Notifications
$db          = Database::getInstance();
$candidateId = $user['id'] ?? 0;

$notifications = $db->fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
    [$candidateId]
) ?? [];

// Mark all as read
$db->query("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL", [$candidateId]);
?>
<div class="max-w-3xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Notifications</h1>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
      <div class="w-16 h-16 bg-violet-50 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
      </div>
      <h3 class="text-lg font-semibold text-gray-700 mb-2">You're all caught up!</h3>
      <p class="text-gray-400 text-sm">No notifications yet. We'll let you know when something happens.</p>
    </div>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($notifications as $n):
        $icon = match($n['type'] ?? '') {
          'offer_received'     => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'],
          'interview_scheduled'=> ['bg' => 'bg-blue-50',    'text' => 'text-blue-600',    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],
          'stage_changed'      => ['bg' => 'bg-violet-50',  'text' => 'text-violet-600',  'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>'],
          default              => ['bg' => 'bg-gray-50',    'text' => 'text-gray-600',    'svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
        };
        $isUnread = empty($n['read_at']);
        $date = $n['created_at'] ? date('M j, g:i a', strtotime($n['created_at'])) : '';
      ?>
      <div class="bg-white rounded-xl border <?= $isUnread ? 'border-violet-200 shadow-sm' : 'border-gray-100' ?> p-4 flex gap-4 items-start">
        <div class="w-10 h-10 rounded-full <?= $icon['bg'] ?> flex-shrink-0 flex items-center justify-center">
          <svg class="w-5 h-5 <?= $icon['text'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?= $icon['svg'] ?>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2">
            <p class="font-semibold text-gray-800 text-sm <?= $isUnread ? '' : 'font-medium' ?>"><?= htmlspecialchars($n['title'] ?? '') ?></p>
            <?php if ($isUnread): ?>
              <span class="w-2 h-2 bg-violet-500 rounded-full flex-shrink-0 mt-1"></span>
            <?php endif; ?>
          </div>
          <p class="text-gray-500 text-sm mt-0.5"><?= htmlspecialchars($n['message'] ?? '') ?></p>
          <p class="text-gray-400 text-xs mt-1.5"><?= $date ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
