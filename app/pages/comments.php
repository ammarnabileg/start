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
      <?= !empty($c['platform']) && !$isReply ? avatar_with_platform($from['picture'] ?? null, $from['name'] ?? $from['username'] ?? '', (string)$c['platform'], 'w-9 h-9') : avatar($from['picture'] ?? null, $from['name'] ?? $from['username'] ?? '', 'w-9 h-9') ?>
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
    <div data-live-root<?= $apiError ? ' data-live-error' : '' ?>>
    <?php
      $pf = preg_replace('/[^a-z]/', '', (string)($_GET['pf'] ?? ''));
      $plats = [];
      foreach ($posts as $pp) { $k = (string)($pp['platform'] ?? ''); if ($k !== '') $plats[$k] = ($plats[$k] ?? 0) + (int)($pp['commentCount'] ?? 0); }
      if (count($plats) > 1 || $pf !== ''): ?>
      <div class="flex gap-2 overflow-x-auto pb-1 mb-3" style="scrollbar-width:none">
        <a href="<?= e(url('comments')) ?>" class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm whitespace-nowrap <?= $pf === '' ? 'border-brand-600 bg-brand-50 text-brand-700 font-medium' : 'border-ink-200 bg-white text-ink-600 hover:bg-ink-50' ?>"><?= e(t('كل القنوات')) ?></a>
        <?php foreach ($plats as $k => $cnt): ?>
          <a href="<?= e(url('comments', ['pf' => $k])) ?>" class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm whitespace-nowrap <?= $pf === $k ? 'border-brand-600 bg-brand-50 text-brand-700 font-medium' : 'border-ink-200 bg-white text-ink-600 hover:bg-ink-50' ?>"><?= platform_icon($k, 'w-4 h-4') ?><?= e(platform_label($k)) ?> <span class="text-xs text-ink-400"><?= (int)$cnt ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endif;
      if ($pf !== '') $posts = array_values(array_filter($posts, function ($pp) use ($pf) { return ($pp['platform'] ?? '') === $pf; })); ?>
    <div class="grid lg:grid-cols-3 gap-4 h-[calc(100vh-15rem)] min-h-[520px]">
      <div data-live="list" class="card overflow-y-auto min-h-0 <?= $postId ? 'hidden lg:block' : '' ?>">
        <?php if (!$posts): ?><p class="p-5 text-sm text-ink-500"><?= e(t('مفيش منشورات عليها تعليقات.')) ?></p><?php endif; ?>
        <?php foreach ($posts as $p): $on = $p['id'] === $postId; ?>
          <a href="<?= e(url('comments', array_filter(['post' => $p['id'], 'a' => $p['accountId'], 'pf' => $pf]))) ?>" class="flex gap-3 p-4 border-b border-ink-100 hover:bg-ink-50 transition <?= $on ? 'bg-brand-50 border-s-2 border-s-brand-600' : '' ?>">
            <span class="relative shrink-0">
              <?php if (!empty($p['picture'])): ?><img src="<?= e($p['picture']) ?>" class="w-12 h-12 rounded-xl object-cover bg-ink-100" loading="lazy" referrerpolicy="no-referrer" alt="">
              <?php else: ?><span class="w-12 h-12 rounded-xl bg-ink-100 flex items-center justify-center"><?= platform_icon((string)($p['platform'] ?? ''), 'w-6 h-6') ?></span><?php endif; ?>
              <span class="absolute -bottom-1 -end-1"><?= platform_icon((string)($p['platform'] ?? ''), 'w-[18px] h-[18px]', 'badge') ?></span>
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-sm line-clamp-2 text-ink-800"><?= ($p['content'] ?? '') !== '' ? e(mb_substr((string)$p['content'], 0, 120)) : '<span class="text-ink-400">' . e(t('(منشور بدون نص)')) . '</span>' ?></p>
              <p class="text-[11px] text-ink-500 mt-1 flex items-center gap-1.5">
                <span><?= e(platform_label($p['platform'] ?? '')) ?></span> ·
                <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 9h10M7 13h6m-9 7l3-3h12a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v15z"/></svg><?= (int)($p['commentCount'] ?? 0) ?></span> ·
                <span><?= e(fmt_date($p['createdTime'] ?? null, 'd/m/Y')) ?></span>
              </p>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if ($next): ?><a href="<?= e(url('comments', ['cursor' => $next])) ?>" class="block text-center p-3 text-sm link"><?= e(t('أقدم')) ?></a><?php endif; ?>
      </div>

      <div class="lg:col-span-2 card flex flex-col min-h-0 overflow-hidden <?= $postId ? '' : 'hidden lg:flex' ?>">
        <?php if (!$postId): ?>
          <div class="flex-1 flex flex-col items-center justify-center gap-2 text-ink-400 text-sm">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M7 9h10M7 13h6m-9 7l3-3h12a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v15z"/></svg>
            <?= e(t('اختار منشور عشان تشوف تعليقاته')) ?>
          </div>
        <?php else: ?>
          <div class="p-4 border-b border-ink-200 flex items-center gap-3">
            <a href="<?= e(url('comments')) ?>" class="lg:hidden link text-sm"><?= e(t('رجوع')) ?></a>
            <?php if (!empty($current['picture'])): ?><img src="<?= e($current['picture']) ?>" class="w-10 h-10 rounded-lg object-cover bg-ink-100" referrerpolicy="no-referrer" alt=""><?php endif; ?>
            <?php if (!empty($current['platform'])): ?><span class="inline-flex items-center gap-1.5 text-xs text-ink-500 bg-ink-100 rounded-full px-2 py-0.5 whitespace-nowrap"><?= platform_icon((string)$current['platform'], 'w-3.5 h-3.5') ?><?= e(platform_label((string)$current['platform'])) ?></span><?php endif; ?>
            <p class="text-sm line-clamp-2 flex-1 text-ink-800"><?= e(mb_substr((string)($current['content'] ?? ''), 0, 200)) ?></p>
            <?php if (!empty($current['permalink'])): ?><a href="<?= e($current['permalink']) ?>" target="_blank" rel="noopener" class="btn-ghost text-xs whitespace-nowrap"><?= e(t('فتح المنشور ↗')) ?></a><?php endif; ?>
          </div>
          <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-5 bg-ink-50" id="thread" data-comments>
            <?php if (!$comments): ?><p class="text-sm text-ink-500"><?= e(t('مفيش تعليقات.')) ?></p><?php endif; ?>
            <?php foreach ($comments as $c) comment_block($c); ?>
          </div>
          <form method="post" class="p-3 border-t border-ink-200 bg-white" id="replyForm" data-optimistic="comment">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="comment_id" id="replyTo" value="">
            <div id="replyHint" class="hidden text-xs text-brand-700 mb-2"><?= e(t('بترد على:')) ?> <span class="font-semibold"></span> <button type="button" id="cancelReply" class="text-ink-500 underline ms-2"><?= e(t('إلغاء')) ?></button></div>
            <div class="flex gap-2 items-end">
              <textarea data-ai="reply_variants,polish_reply,comments_triage,summarize,translate_ctx" name="message" rows="2" class="input !mt-0 flex-1 text-sm" placeholder="<?= e(t('اكتب تعليق على المنشور، أو دوس (رد) على تعليق معين')) ?>" data-enter-submit></textarea>
              <?php if (ai_connection() && can('ai.use') && ai_feature_on('reply_btn')): ?><button type="button" data-ai-task="reply" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-2.5 py-2.5 text-xs font-medium text-brand-700 hover:bg-brand-100 transition disabled:opacity-60" title="<?= e(t('اقتراح رد')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg><span><?= e(t('اقتراح رد')) ?></span></button><?php endif; ?>
              <button class="btn-primary !py-2.5"><?= e(t('إرسال')) ?></button>
            </div>
            <p class="text-[11px] text-ink-400 mt-1.5"><?= e(t('Enter للإرسال · Shift+Enter لسطر جديد')) ?></p>
          </form>
        <?php endif; ?>
      </div>
    </div>
    </div>
    <?php
});
