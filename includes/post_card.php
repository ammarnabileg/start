<?php
// Variables expected: $post (array), $liked (bool), $community_id (int), $current_user (array|null), $slug (string)
$post_user_name = trim(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? '')) ?: ($post['username'] ?? 'Unknown');
$comments_list = db_fetch_all(
 'SELECT c.*, u.username, u.first_name, u.last_name, u.avatar FROM comments c JOIN users u ON u.id = c.user_id WHERE c.post_id = ? ORDER BY c.created_at ASC LIMIT 20',
 [$post['id']]
);
$user_liked = $post['user_liked'] ?? ($liked ?? false);
$like_cnt = (int)($post['like_count'] ?? 0);
$comment_cnt = (int)($post['comment_count'] ?? 0);
?>
<!-- Author row -->
<div class="flex items-start gap-3 mb-4">
 <a href="/profile.php?username=<?= e($post['username'] ?? '') ?>" class="flex-shrink-0">
 <img src="<?= get_avatar_url($post['avatar'] ?? null, $post_user_name) ?>"
 class="w-9 h-9 rounded-full object-cover">
 </a>
 <div class="flex-1 min-w-0">
 <div class="flex items-center gap-2 flex-wrap">
 <a href="/profile.php?username=<?= e($post['username'] ?? '') ?>"
 class="font-semibold text-sm text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
 <?= e($post_user_name) ?>
 </a>
 <span class="text-gray-300 dark:text-gray-700 text-xs">&bull;</span>
 <span class="text-xs text-gray-400 dark:text-gray-500"><?= time_ago($post['created_at']) ?></span>
 <?php if (!empty($post['topic_name'])): ?>
 <span class="text-xs bg-gray-100 dark:bg-white/10 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full"># <?= e($post['topic_name']) ?></span>
 <?php endif; ?>
 </div>
 <?php if (!empty($post['title'])): ?>
 <h4 class="font-bold text-gray-900 dark:text-white mt-1 text-sm leading-snug"><?= e($post['title']) ?></h4>
 <?php endif; ?>
 <div class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line mt-1"><?= nl2br(e($post['content'])) ?></div>
 </div>
</div>

<!-- Actions -->
<div class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-white/10">
 <?php if ($current_user): ?>
 <button
 class="like-btn flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium transition-colors
 <?= $user_liked ? 'text-red-500 bg-red-50 dark:bg-red-900/20' : 'text-gray-500 dark:text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20' ?>"
 data-post-id="<?= $post['id'] ?>"
 data-liked="<?= $user_liked ? '1' : '0' ?>"
 onclick="toggleLike(this)">
 <svg class="w-4 h-4" fill="<?= $user_liked ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
 </svg>
 <span class="like-count"><?= $like_cnt ?></span>
 </button>
 <button onclick="toggleComments(<?= $post['id'] ?>)"
 class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-gray-100 dark:hover:bg-white/10 transition-colors">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
 </svg>
 <span class="comment-count"><?= $comment_cnt ?></span>
 </button>
 <?php else: ?>
 <span class="flex items-center gap-1.5 text-sm text-gray-400 dark:text-gray-600 px-3 py-1.5">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
 <?= $like_cnt ?>
 </span>
 <span class="flex items-center gap-1.5 text-sm text-gray-400 dark:text-gray-600 px-3 py-1.5">
 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
 <?= $comment_cnt ?>
 </span>
 <?php endif; ?>
</div>

<!-- Comments Section -->
<div id="comments-<?= $post['id'] ?>" class="hidden mt-3 pt-3 border-t border-gray-100 dark:border-white/10 space-y-2">
 <div id="comments-list-<?= $post['id'] ?>">
 <?php foreach ($comments_list as $c): ?>
 <div class="flex items-start gap-2">
 <img src="<?= get_avatar_url($c['avatar'] ?? null, ($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?>"
 class="w-7 h-7 rounded-full object-cover flex-shrink-0">
 <div class="flex-1 bg-gray-50 dark:bg-[#2a2a2a] rounded-xl px-3 py-2">
 <div class="flex items-baseline gap-2">
 <span class="text-xs font-semibold text-gray-900 dark:text-white"><?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: $c['username']) ?></span>
 <span class="text-xs text-gray-400 dark:text-gray-600"><?= time_ago($c['created_at']) ?></span>
 </div>
 <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5"><?= nl2br(e($c['content'])) ?></p>
 </div>
 </div>
 <?php endforeach; ?>
 </div>
 <?php if ($current_user): ?>
 <div class="flex items-center gap-2 mt-2">
 <img src="<?= get_avatar_url($current_user['avatar'] ?? null, ($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? '')) ?>"
 class="w-7 h-7 rounded-full object-cover flex-shrink-0">
 <div class="flex-1 flex gap-2">
 <input type="text" id="comment-input-<?= $post['id'] ?>" placeholder="Write a comment..."
 class="flex-1 bg-gray-50 dark:bg-[#2a2a2a] border border-gray-200 dark:border-white/10 rounded-full px-3 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:outline-none focus:ring-1 focus:ring-primary-500"
 onkeypress="if(event.key==='Enter')submitComment(<?= $post['id'] ?>, <?= (int)$community_id ?>)">
 <button onclick="submitComment(<?= $post['id'] ?>, <?= (int)$community_id ?>)"
 class="px-3 py-1.5 bg-gradient-to-r from-primary-600 to-accent-500 text-white rounded-full text-xs font-semibold hover: transition-all">Send</button>
 </div>
 </div>
 <?php endif; ?>
</div>
