<?php
$err = null;
$z = zernio_or_null($err);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && ($_POST['action'] ?? '') === 'delete') {
    try {
        $z->deletePost((string)$_POST['id']);
        log_activity('post.delete', (string)$_POST['id']);
        flash('success', t('تم حذف المنشور.'));
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('posts', ['status' => $_GET['status'] ?? '']));
}

$status = (string)($_GET['status'] ?? '');
$validStatus = ['', 'scheduled', 'published', 'draft', 'failed', 'partial'];
if (!in_array($status, $validStatus, true)) $status = '';
$pageNo = max(1, (int)($_GET['n'] ?? 1));
$posts = [];
$pages = 1;
$apiError = null;
$accountNames = [];

if ($z) {
    try {
        $r = $z->posts([
            'status' => $status ?: null, 'page' => $pageNo, 'limit' => 20,
            'sortBy' => $status === 'scheduled' ? 'scheduled-asc' : 'created-desc',
            'profileId' => zernio_profile(),
        ]);
        $posts = $r['posts'] ?? [];
        $pages = max(1, (int)($r['pagination']['pages'] ?? 1));
        foreach ($z->accounts(zernio_profile()) as $a) {
            $accountNames[$a['_id']] = $a['displayName'] ?? $a['username'] ?? '';
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('المنشورات'), 'posts', function () use ($z, $posts, $pages, $pageNo, $status, $apiError, $accountNames) {
    if (!$z) { no_api_box(); return; }
    $tabs = ['' => t('الكل'), 'scheduled' => t('مجدول'), 'published' => t('تم النشر'), 'draft' => t('مسودات'), 'failed' => t('فشل')];
    ?>
    <div class="flex flex-wrap gap-2 mb-5">
      <?php foreach ($tabs as $k => $l): ?>
        <a href="<?= e(url('posts', ['status' => $k])) ?>" class="px-4 py-2 rounded-lg text-sm font-semibold <?= $status === $k ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200 hover:bg-slate-50' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($apiError) { error_box($apiError); return; } ?>
    <?= card_open() ?>
    <?php if (!$posts): ?>
      <p class="p-6 text-slate-500 text-sm"><?= e(t('مفيش منشورات هنا.')) ?></p>
    <?php else: foreach ($posts as $p):
        [$label, $cls] = status_label((string)($p['status'] ?? ''));
        $thumb = $p['mediaItems'][0]['url'] ?? null;
        $thumbType = $p['mediaItems'][0]['type'] ?? '';
        ?>
      <div class="p-5 border-b last:border-0 border-slate-100 flex gap-4">
        <?php if ($thumb && $thumbType !== 'video' && $thumbType !== 'document'): ?>
          <img src="<?= e($thumb) ?>" class="w-16 h-16 rounded-lg object-cover bg-slate-100 shrink-0" loading="lazy" alt="">
        <?php elseif ($thumb): ?>
          <div class="w-16 h-16 rounded-lg bg-slate-100 flex items-center justify-center text-xs text-slate-500 shrink-0"><?= $thumbType === 'video' ? t('فيديو') : t('ملف') ?></div>
        <?php endif; ?>
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?= $cls ?>"><?= e($label) ?></span>
            <?php foreach ($p['platforms'] ?? [] as $pl):
                $accId = is_array($pl['accountId'] ?? null) ? ($pl['accountId']['_id'] ?? '') : ($pl['accountId'] ?? '');
                $plUrl = $pl['platformPostUrl'] ?? null; ?>
              <?php if ($plUrl): ?><a href="<?= e($plUrl) ?>" target="_blank" rel="noopener" class="text-xs px-2 py-0.5 rounded-full bg-slate-100 hover:bg-slate-200"><?= e(platform_label($pl['platform'] ?? '')) ?> ↗</a>
              <?php else: ?><span class="text-xs px-2 py-0.5 rounded-full bg-slate-100"><?= e(platform_label($pl['platform'] ?? '')) ?><?= isset($accountNames[$accId]) ? ' · ' . e($accountNames[$accId]) : '' ?></span><?php endif; ?>
              <?php if (($pl['status'] ?? '') === 'failed' && !empty($pl['errorMessage'])): ?>
                <span class="text-xs text-red-700"><?= e(mb_substr($pl['errorMessage'], 0, 150)) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <p class="text-sm whitespace-pre-line line-clamp-3"><?= e(mb_substr((string)($p['content'] ?? ''), 0, 400)) ?></p>
          <p class="text-xs text-slate-500 mt-1">
            <?php if (!empty($p['scheduledFor'])): ?><?= e(t('موعد النشر:')) ?> <?= e(fmt_date($p['scheduledFor'])) ?> · <?php endif; ?>
            <?= e(t('اتعمل:')) ?> <?= e(fmt_date($p['createdAt'] ?? null)) ?>
          </p>
        </div>
        <a href="<?= e(url('post_edit', ['id' => $p['_id']])) ?>" class="text-sm link px-3 py-1.5 whitespace-nowrap"><?= e(t('التفاصيل / تعديل')) ?></a>
        <?php if (($p['status'] ?? '') !== 'publishing'): ?>
        <form method="post" onsubmit="return confirm('<?= tjs('متأكد إنك عاوز تحذف المنشور ده؟') ?>')">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($p['_id']) ?>">
          <button class="text-sm text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg"><?= e(t('حذف')) ?></button>
        </form>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
    </div>
    <?php if ($pages > 1): ?>
      <div class="flex gap-2 justify-center mt-5">
        <?php if ($pageNo > 1): ?><a class="px-4 py-2 bg-white border rounded-lg" href="<?= e(url('posts', ['status' => $status, 'n' => $pageNo - 1])) ?>"><?= e(t('السابق')) ?></a><?php endif; ?>
        <span class="px-4 py-2 text-sm text-slate-500"><?= e(t('صفحة')) ?> <?= $pageNo ?> <?= e(t('من')) ?> <?= $pages ?></span>
        <?php if ($pageNo < $pages): ?><a class="px-4 py-2 bg-white border rounded-lg" href="<?= e(url('posts', ['status' => $status, 'n' => $pageNo + 1])) ?>"><?= e(t('التالي')) ?></a><?php endif; ?>
      </div>
    <?php endif;
});
