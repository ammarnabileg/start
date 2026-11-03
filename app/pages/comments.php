<?php
$err = null;
$z = zernio_or_null($err);
$postId = (string)($_GET['post'] ?? '');
$accId = (string)($_GET['a'] ?? '');
$cursor = (string)($_GET['cursor'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && $postId && $accId) {
    $action = (string)($_POST['action'] ?? 'reply');
    $commentId = (string)($_POST['comment_id'] ?? '');
    try {
        if ($action === 'reply') {
            $text = trim((string)($_POST['message'] ?? ''));
            if ($text === '') {
                flash('error', t('اكتب الرد الأول.'));
            } else {
                $z->replyComment($postId, $accId, $text, $commentId ?: null);
                log_activity('comment.reply', $postId);
                flash('success', t('تم إرسال الرد.'));
            }
        } elseif ($action === 'hide' && $commentId) {
            $z->hideComment($postId, $commentId, $accId);
            flash('success', t('تم إخفاء التعليق.'));
        } elseif ($action === 'delete' && $commentId) {
            $z->deleteComment($postId, $commentId, $accId);
            log_activity('comment.delete', $commentId);
            flash('success', t('تم حذف التعليق.'));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('comments', ['post' => $postId, 'a' => $accId]));
}

$posts = [];
$next = null;
$comments = [];
$current = null;
$apiError = null;
if ($z) {
    try {
        $r = $z->commentedPosts(['limit' => 30, 'cursor' => $cursor ?: null, 'profileId' => zernio_profile()]);
        $posts = $r['data'] ?? [];
        $next = ($r['pagination']['hasMore'] ?? false) ? ($r['pagination']['nextCursor'] ?? null) : null;
        foreach ($posts as $p) {
            if ($p['id'] === $postId) $current = $p;
        }
        if ($postId && $accId) {
            $comments = $z->comments($postId, $accId)['comments'] ?? [];
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

function comment_block(array $c, bool $isReply = false): void
{
    $from = $c['from'] ?? [];
    $mine = !empty($from['isOwner']);
    ?>
    <div class="flex gap-3 <?= $isReply ? 'ms-12 mt-3' : '' ?>">
      <?= avatar($from['picture'] ?? null, $from['name'] ?? $from['username'] ?? '', 'w-9 h-9') ?>
      <div class="flex-1 min-w-0">
        <div class="rounded-2xl px-4 py-2 inline-block max-w-full <?= $mine ? 'bg-indigo-50' : 'bg-slate-100' ?> <?= !empty($c['isHidden']) ? 'opacity-50' : '' ?>">
          <div class="text-xs font-bold"><?= e($from['name'] ?? $from['username'] ?? t('مستخدم')) ?><?= $mine ? ' ' . e(t('(أنت)')) : '' ?></div>
          <p class="text-sm whitespace-pre-line break-words"><?= e($c['message'] ?? '') ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 mt-1 ps-2">
          <span><?= e(fmt_date($c['createdTime'] ?? null, 'd/m h:i A')) ?></span>
          <?php if (!empty($c['isHidden'])): ?><span><?= e(t('مخفي')) ?></span><?php endif; ?>
          <?php if (!$isReply && ($c['canReply'] ?? true)): ?><button type="button" class="font-semibold text-indigo-700" data-reply-to="<?= e($c['id']) ?>" data-reply-name="<?= e($from['name'] ?? '') ?>"><?= e(t('رد')) ?></button><?php endif; ?>
          <?php if (!empty($c['canHide']) && empty($c['isHidden'])): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="hide"><input type="hidden" name="comment_id" value="<?= e($c['id']) ?>"><button><?= e(t('إخفاء')) ?></button></form>
          <?php endif; ?>
          <?php if (!empty($c['canDelete'])): ?>
            <form method="post" class="inline" onsubmit="return confirm('<?= tjs('حذف التعليق نهائياً؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="comment_id" value="<?= e($c['id']) ?>"><button class="text-red-600"><?= e(t('حذف')) ?></button></form>
          <?php endif; ?>
          <?php if (!empty($c['url'])): ?><a href="<?= e($c['url']) ?>" target="_blank" rel="noopener"><?= e(t('فتح ↗')) ?></a><?php endif; ?>
        </div>
        <?php foreach ($c['replies'] ?? [] as $r) comment_block($r, true); ?>
      </div>
    </div>
    <?php
}

render(t('التعليقات'), 'comments', function () use ($z, $posts, $next, $comments, $current, $postId, $accId, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); }
    ?>
    <div class="grid lg:grid-cols-3 gap-4">
      <div class="bg-white border border-slate-200 rounded-xl overflow-y-auto max-h-[calc(100vh-11rem)] <?= $postId ? 'hidden lg:block' : '' ?>">
        <?php if (!$posts): ?><p class="p-5 text-sm text-slate-500"><?= e(t('مفيش منشورات عليها تعليقات.')) ?></p><?php endif; ?>
        <?php foreach ($posts as $p): $on = $p['id'] === $postId; ?>
          <a href="<?= e(url('comments', ['post' => $p['id'], 'a' => $p['accountId']])) ?>" class="flex gap-3 p-4 border-b border-slate-100 hover:bg-slate-50 <?= $on ? 'bg-indigo-50' : '' ?>">
            <?php if (!empty($p['picture'])): ?><img src="<?= e($p['picture']) ?>" class="w-14 h-14 rounded-lg object-cover bg-slate-100 shrink-0" loading="lazy" referrerpolicy="no-referrer" alt=""><?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="text-sm line-clamp-2"><?= e(mb_substr((string)($p['content'] ?? ''), 0, 120)) ?: t('<span class="text-slate-400">(منشور بدون نص)</span>') ?></p>
              <p class="text-[11px] text-slate-500 mt-1"><?= e(platform_label($p['platform'] ?? '')) ?> · <?= (int)($p['commentCount'] ?? 0) ?> <?= e(t('تعليق ·')) ?> <?= e(fmt_date($p['createdTime'] ?? null, 'd/m/Y')) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if ($next): ?><a href="<?= e(url('comments', ['cursor' => $next])) ?>" class="block text-center p-3 text-sm text-indigo-700 font-semibold"><?= e(t('أقدم ←')) ?></a><?php endif; ?>
      </div>

      <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl <?= $postId ? '' : 'hidden lg:block' ?>">
        <?php if (!$postId): ?>
          <div class="p-10 text-center text-slate-400 text-sm"><?= e(t('اختار منشور عشان تشوف تعليقاته')) ?></div>
        <?php else: ?>
          <div class="p-4 border-b border-slate-200 flex items-center justify-between gap-3">
            <a href="<?= e(url('comments')) ?>" class="lg:hidden text-indigo-700 text-sm"><?= e(t('→ رجوع')) ?></a>
            <p class="text-sm line-clamp-2 flex-1"><?= e(mb_substr((string)($current['content'] ?? ''), 0, 200)) ?></p>
            <?php if (!empty($current['permalink'])): ?><a href="<?= e($current['permalink']) ?>" target="_blank" rel="noopener" class="text-xs text-indigo-700 whitespace-nowrap"><?= e(t('فتح المنشور ↗')) ?></a><?php endif; ?>
          </div>
          <div class="p-4 space-y-5">
            <?php if (!$comments): ?><p class="text-sm text-slate-500"><?= e(t('مفيش تعليقات.')) ?></p><?php endif; ?>
            <?php foreach ($comments as $c) comment_block($c); ?>
          </div>
          <form method="post" class="p-3 border-t border-slate-200 sticky bottom-0 bg-white rounded-b-xl" id="replyForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="comment_id" id="replyTo" value="">
            <div id="replyHint" class="hidden text-xs text-indigo-700 mb-2"><?= e(t('بترد على:')) ?> <span></span> <button type="button" id="cancelReply" class="text-slate-500 underline ms-2"><?= e(t('إلغاء')) ?></button></div>
            <div class="flex gap-2 items-end">
              <textarea name="message" rows="2" class="flex-1 border border-slate-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="<?= e(t('اكتب تعليق على المنشور، أو دوس (رد) على تعليق معين')) ?>" data-enter-submit></textarea>
              <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-lg"><?= e(t('إرسال')) ?></button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php
});
