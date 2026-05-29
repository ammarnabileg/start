<?php
// Variables expected: $post (array), $liked (bool), $community_id (int), $current_user (array|null), $slug (string)
$post_user_name = trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? '')) ?: $post['username'];
$comments_list = db_fetch_all(
    'SELECT c.*, u.username, u.first_name, u.last_name, u.avatar FROM comments c JOIN users u ON u.id = c.user_id WHERE c.post_id = ? ORDER BY c.created_at ASC LIMIT 20',
    [$post['id']]
);
?>
<div class="flex items-start gap-3 mb-3">
  <a href="/platform/profile.php?username=<?= e($post['username']) ?>">
    <img src="<?= get_avatar_url($post['avatar'] ?? null, $post_user_name) ?>"
      class="w-9 h-9 rounded-full object-cover flex-shrink-0">
  </a>
  <div class="flex-1 min-w-0">
    <div class="flex items-center gap-2 flex-wrap">
      <a href="/platform/profile.php?username=<?= e($post['username']) ?>" class="font-semibold text-sm text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-all"><?= e($post_user_name) ?></a>
      <span class="text-xs text-gray-400 dark:text-gray-500">•</span>
      <span class="text-xs text-gray-400 dark:text-gray-500"><?= time_ago($post['created_at']) ?></span>
      <?php if (!empty($post['topic_name'])): ?>
        <span class="text-xs bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 px-2 py-0.5 rounded-full font-medium"># <?= e($post['topic_name']) ?></span>
      <?php endif; ?>
    </div>
    <?php if (!empty($post['title'])): ?>
      <h4 class="font-bold text-gray-900 dark:text-white mt-1 mb-1"><?= e($post['title']) ?></h4>
    <?php endif; ?>
    <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line mt-1"><?= nl2br(e($post['content'])) ?></div>
  </div>
</div>

<!-- Actions -->
<div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-50 dark:border-gray-700/50">
  <?php if ($current_user): ?>
    <button onclick="toggleLike(<?= $post['id'] ?>, this)"
      class="flex items-center gap-1.5 text-sm font-medium transition-all <?= $liked ? 'text-red-500' : 'text-gray-500 dark:text-gray-400 hover:text-red-500' ?>">
      <svg class="w-4 h-4" fill="<?= $liked ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
      </svg>
      <span class="like-count"><?= $post['like_count'] ?></span>
    </button>
    <button onclick="toggleComments(<?= $post['id'] ?>)"
      class="flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
      </svg>
      <span><?= $post['comment_count'] ?></span>
    </button>
  <?php else: ?>
    <span class="flex items-center gap-1.5 text-sm text-gray-400">❤️ <?= $post['like_count'] ?></span>
    <span class="flex items-center gap-1.5 text-sm text-gray-400">💬 <?= $post['comment_count'] ?></span>
  <?php endif; ?>
</div>

<!-- Comments Section -->
<div id="comments-<?= $post['id'] ?>" class="hidden mt-3 border-t border-gray-50 dark:border-gray-700/50 pt-3 space-y-2">
  <?php foreach ($comments_list as $c): ?>
    <div class="flex items-start gap-2">
      <img src="<?= get_avatar_url($c['avatar'] ?? null, ($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?>"
        class="w-7 h-7 rounded-full object-cover flex-shrink-0">
      <div class="flex-1 bg-gray-50 dark:bg-gray-700/50 rounded-xl px-3 py-2">
        <div class="flex items-baseline gap-2">
          <span class="text-xs font-semibold text-gray-900 dark:text-white"><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: $c['username']) ?></span>
          <span class="text-xs text-gray-400 dark:text-gray-500"><?= time_ago($c['created_at']) ?></span>
        </div>
        <p class="text-xs text-gray-700 dark:text-gray-300 mt-0.5"><?= nl2br(e($c['content'])) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if ($current_user): ?>
    <div class="flex items-center gap-2 mt-2">
      <img src="<?= get_avatar_url($current_user['avatar'] ?? null, ($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? '')) ?>"
        class="w-7 h-7 rounded-full object-cover flex-shrink-0">
      <div class="flex-1 flex gap-2">
        <input type="text" id="comment-input-<?= $post['id'] ?>" placeholder="Write a comment..."
          class="flex-1 px-3 py-1.5 text-xs rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:text-gray-200 placeholder-gray-400"
          onkeypress="if(event.key==='Enter')submitComment(<?= $post['id'] ?>, <?= $community_id ?>)">
        <button onclick="submitComment(<?= $post['id'] ?>, <?= $community_id ?>)" class="px-3 py-1.5 bg-primary-600 text-white rounded-xl text-xs font-semibold hover:bg-primary-700 transition-all">Send</button>
      </div>
    </div>
  <?php endif; ?>
</div>
