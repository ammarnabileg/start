<?php
$err = null;
$z = zernio_or_null($err);
$id = (string)($_GET['id'] ?? '');
$post = null;
$accounts = [];
$apiError = null;
$back = url('post_edit', ['id' => $id]);

if ($z && $id !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'update') {
            $content = trim((string)($_POST['content'] ?? ''));
            $ids = array_map('strval', (array)($_POST['accounts'] ?? []));
            $byId = [];
            foreach ($z->accounts(zernio_profile()) as $a) $byId[$a['_id']] = $a;
            $platforms = [];
            // نحافظ على إعدادات كل منصة القديمة لو الحساب لسه متختار
            $oldPl = [];
            foreach (pub_get_post($z, $id)['platforms'] ?? [] as $pl) {
                $oldPl[pub_acc_id($pl['accountId'] ?? '')] = $pl;
            }
            foreach ($ids as $aid) {
                if (!isset($byId[$aid])) continue;
                $item = ['platform' => $byId[$aid]['platform'], 'accountId' => $aid];
                foreach (['customContent', 'platformSpecificData'] as $k) {
                    if (!empty($oldPl[$aid][$k])) $item[$k] = $oldPl[$aid][$k];
                }
                $platforms[] = $item;
            }
            $payload = ['content' => $content, 'platforms' => $platforms, 'timezone' => site_timezone()];
            $when = (string)($_POST['when'] ?? '');
            if ($when !== '') {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $when, new DateTimeZone(site_timezone()));
                if (!$dt || $dt < new DateTime('now', new DateTimeZone(site_timezone()))) {
                    throw new ZernioException(t('وقت النشر لازم يكون في المستقبل.'));
                }
                $payload['scheduledFor'] = $dt->format('Y-m-d\TH:i:s');
            }
            if ($content === '') throw new ZernioException(t('النص مينفعش يكون فاضي.'));
            if (!$platforms) throw new ZernioException(t('اختار حساب واحد على الأقل.'));
            pub_update_post($z, $id, $payload);
            log_activity('post.update', $id);
            flash('success', t('تم حفظ التعديلات.'));
        } elseif ($action === 'edit_published') {
            $content = trim((string)($_POST['content'] ?? ''));
            if ($content === '') throw new ZernioException(t('النص مينفعش يكون فاضي.'));
            pub_edit_published($z, $id, (string)$_POST['platform'], $content, (string)($_POST['accountId'] ?? '') ?: null);
            log_activity('post.edit_published', $id . ' ' . $_POST['platform']);
            flash('success', t('تم تعديل المنشور على المنصة.'));
        } elseif ($action === 'retry') {
            pub_retry_post($z, $id);
            log_activity('post.retry', $id);
            flash('success', t('تمت إعادة المحاولة. راجع الحالة بعد شوية.'));
        } elseif ($action === 'unpublish') {
            if (!can('posts.manage')) throw new ZernioException(t('الإجراء ده لصاحب الشركة بس.'));
            pub_unpublish($z, $id, (string)$_POST['platform'], (string)($_POST['accountId'] ?? '') ?: null);
            log_activity('post.unpublish', $id . ' ' . $_POST['platform']);
            flash('success', t('تم مسح المنشور من المنصة.'));
        } elseif ($action === 'duplicate') {
            $p = pub_get_post($z, $id);
            $copy = ['content' => (string)($p['content'] ?? ''), 'isDraft' => true, 'timezone' => site_timezone()];
            if (!empty($p['mediaItems'])) {
                $copy['mediaItems'] = array_map(function ($m) {
                    return array_intersect_key($m, array_flip(['type', 'url', 'altText', 'thumbnail']));
                }, $p['mediaItems']);
            }
            $pls = [];
            foreach ($p['platforms'] ?? [] as $pl) {
                $item = ['platform' => $pl['platform'], 'accountId' => pub_acc_id($pl['accountId'] ?? '')];
                foreach (['customContent', 'platformSpecificData'] as $k) {
                    if (!empty($pl[$k])) $item[$k] = $pl[$k];
                }
                $pls[] = $item;
            }
            if ($pls) $copy['platforms'] = $pls;
            $r = $z->createPost($copy);
            log_activity('post.duplicate', $id);
            flash('success', t('اتعملت نسخة جديدة كمسودة.'));
            $newId = $r['post']['_id'] ?? '';
            if ($newId) redirect(url('post_edit', ['id' => $newId]));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect($back);
}

if ($z && $id !== '') {
    try {
        $post = pub_get_post($z, $id);
        $accounts = array_values(array_filter($z->accounts(zernio_profile()), function ($a) {
            return ($a['isActive'] ?? true) && empty($a['needsReconnection']);
        }));
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('تفاصيل المنشور'), 'posts', function () use ($z, $id, $post, $accounts, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($id === '') { error_box(t('مفيش منشور محدد.')); return; }
    if ($apiError) { error_box($apiError); return; }
    $status = (string)($post['status'] ?? '');
    [$label, $cls] = status_label($status);
    $editable = in_array($status, ['draft', 'scheduled', 'failed', 'cancelled', 'pending_approval'], true);
    $accNames = [];
    foreach ($accounts as $a) $accNames[$a['_id']] = $a['displayName'] ?? $a['username'] ?? '';
    $selected = [];
    $hasFailed = false;
    foreach ($post['platforms'] ?? [] as $pl) {
        $selected[] = pub_acc_id($pl['accountId'] ?? '');
        if (($pl['status'] ?? '') === 'failed') $hasFailed = true;
    }
    $whenVal = '';
    if (!empty($post['scheduledFor'])) {
        try {
            $whenVal = (new DateTime($post['scheduledFor']))->setTimezone(new DateTimeZone(site_timezone()))->format('Y-m-d\TH:i');
        } catch (Throwable $t) {}
    }
    ?>
    <div class="flex flex-wrap items-center gap-3 mb-5">
      <a class="link text-sm" href="<?= e(url('posts')) ?>">&larr; <?= e(t('رجوع للمنشورات')) ?></a>
      <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?= $cls ?>"><?= e($label) ?></span>
      <?php if (!empty($post['scheduledFor'])): ?><span class="text-sm text-ink-600"><?= e(t('موعد النشر:')) ?> <?= e(fmt_date($post['scheduledFor'], 'd/m h:i A')) ?></span><?php endif; ?>
      <div class="ms-auto flex flex-wrap gap-2">
        <form method="post" onsubmit="return confirm('<?= tjs('هيتعمل نسخة جديدة من المنشور ده كمسودة. تكمل؟') ?>')">
          <?= csrf_field() ?><input type="hidden" name="action" value="duplicate">
          <button class="btn-ghost text-sm"><?= e(t('اعمل نسخة كمسودة')) ?></button>
        </form>
        <?php if ($hasFailed): ?>
        <form method="post" onsubmit="return confirm('<?= tjs('هنحاول ننشر تاني على المنصات اللي فشلت. تكمل؟') ?>')">
          <?= csrf_field() ?><input type="hidden" name="action" value="retry">
          <button class="btn-primary text-sm"><?= e(t('أعد المحاولة للمنصات اللي فشلت')) ?></button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <?php if ($editable): ?>
        <form method="post" class="card p-5 space-y-4">
          <?= csrf_field() ?><input type="hidden" name="action" value="update">
          <h2 class="font-bold"><?= e(t('تعديل المنشور قبل نشره')) ?></h2>
          <label class="block"><span class="label"><?= e(t('نص المنشور')) ?></span>
            <textarea name="content" rows="7" class="input"><?= e($post['content'] ?? '') ?></textarea></label>
          <label class="block"><span class="label"><?= e(t('موعد النشر')) ?> (<?= e(site_timezone()) ?>)</span>
            <input type="datetime-local" name="when" value="<?= e($whenVal) ?>" class="input" dir="ltr"></label>
          <p class="text-xs text-ink-500"><?= e(t('سيبه فاضي لو مش عاوز تغير الموعد.')) ?></p>
          <div>
            <span class="label"><?= e(t('الحسابات')) ?></span>
            <div class="grid sm:grid-cols-2 gap-2 mt-2">
              <?php foreach ($accounts as $a): ?>
                <label class="flex items-center gap-2 p-2 rounded-lg border border-ink-200 cursor-pointer">
                  <input type="checkbox" name="accounts[]" value="<?= e($a['_id']) ?>" <?= in_array($a['_id'], $selected, true) ? 'checked' : '' ?>>
                  <?= avatar($a['profilePicture'] ?? null, $a['displayName'] ?? '', 'w-7 h-7') ?>
                  <span class="text-sm"><?= e($a['displayName'] ?? $a['username'] ?? '') ?> · <span class="text-ink-500"><?= e(platform_label($a['platform'])) ?></span></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <button class="btn-primary"><?= e(t('احفظ التعديلات')) ?></button>
        </form>
        <?php else: ?>
        <div class="card p-5">
          <h2 class="font-bold mb-2"><?= e(t('نص المنشور')) ?></h2>
          <p class="text-sm whitespace-pre-line"><?= e($post['content'] ?? '') ?></p>
          <?php if ($status === 'publishing'): ?><p class="text-sm text-amber-700 mt-3"><?= e(t('المنشور بيتنشر دلوقتي، استنى لحد ما يخلص.')) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($post['mediaItems'])): ?>
        <div class="card p-5">
          <h2 class="font-bold mb-3"><?= e(t('الصور والفيديو')) ?></h2>
          <div class="flex flex-wrap gap-2">
            <?php foreach ($post['mediaItems'] as $m): ?>
              <?php if (($m['type'] ?? '') === 'image' || ($m['type'] ?? '') === 'gif'): ?>
                <a href="<?= e($m['url'] ?? '') ?>" target="_blank" rel="noopener"><img src="<?= e($m['url'] ?? '') ?>" class="w-24 h-24 rounded-lg object-cover bg-ink-100" alt="" loading="lazy"></a>
              <?php else: ?>
                <a href="<?= e($m['url'] ?? '') ?>" target="_blank" rel="noopener" class="w-24 h-24 rounded-lg bg-ink-100 flex items-center justify-center text-xs"><?= e(($m['type'] ?? '') === 'video' ? t('فيديو') : t('ملف')) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="space-y-4">
        <h2 class="font-bold"><?= e(t('الحالة على كل منصة')) ?></h2>
        <?php foreach ($post['platforms'] ?? [] as $i => $pl):
            $plat = (string)($pl['platform'] ?? '');
            $accId = pub_acc_id($pl['accountId'] ?? '');
            $plStatus = (string)($pl['status'] ?? $status);
            [$pLabel, $pCls] = status_label($plStatus);
            $isPub = $plStatus === 'published';
            ?>
          <div class="card p-4 space-y-2">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-semibold text-sm"><?= e(platform_label($plat)) ?></span>
              <?php if (isset($accNames[$accId])): ?><span class="text-xs text-ink-500"><?= e($accNames[$accId]) ?></span><?php endif; ?>
              <span class="ms-auto text-xs px-2 py-0.5 rounded-full font-semibold <?= $pCls ?>"><?= e($pLabel) ?></span>
            </div>
            <?php if (!empty($pl['platformPostUrl'])): ?><a class="link text-sm" href="<?= e($pl['platformPostUrl']) ?>" target="_blank" rel="noopener"><?= e(t('افتح المنشور على المنصة')) ?> ↗</a><?php endif; ?>
            <?php if (!empty($pl['publishedAt'])): ?><p class="text-xs text-ink-500"><?= e(t('اتنشر:')) ?> <?= e(fmt_date($pl['publishedAt'], 'd/m h:i A')) ?></p><?php endif; ?>
            <?php if (!empty($pl['errorMessage'])): ?><p class="text-xs text-red-700 bg-red-50 rounded-lg p-2"><?= e($pl['errorMessage']) ?></p><?php endif; ?>
            <?php if (!empty($pl['customContent'])): ?><p class="text-xs text-ink-600"><?= e(t('نص خاص بالمنصة دي:')) ?> <?= e(mb_substr($pl['customContent'], 0, 200)) ?></p><?php endif; ?>

            <?php if ($isPub && in_array($plat, pub_editable_platforms(), true)): ?>
              <details>
                <summary class="text-sm link cursor-pointer"><?= e(t('عدّل النص المنشور')) ?></summary>
                <form method="post" class="mt-2 space-y-2" onsubmit="return confirm('<?= tjs('التعديل هيظهر على المنصة فوراً. تكمل؟') ?>')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="edit_published">
                  <input type="hidden" name="platform" value="<?= e($plat) ?>"><input type="hidden" name="accountId" value="<?= e($accId) ?>">
                  <textarea name="content" rows="4" class="input"><?= e($pl['customContent'] ?? $post['content'] ?? '') ?></textarea>
                  <?php if ($plat === 'twitter'): ?><p class="text-xs text-amber-700"><?= e(t('في X التعديل بيعمل منشور جديد برقم جديد.')) ?></p><?php endif; ?>
                  <button class="btn-primary text-sm"><?= e(t('احفظ على المنصة')) ?></button>
                </form>
              </details>
            <?php endif; ?>
            <?php if ($isPub && can('posts.manage') && in_array($plat, pub_unpublishable_platforms(), true)): ?>
              <form method="post" onsubmit="return confirm('<?= tjs('تحذير: المنشور هيتمسح نهائياً من المنصة دي ومش هينفع يرجع. متأكد؟') ?>')">
                <?= csrf_field() ?><input type="hidden" name="action" value="unpublish">
                <input type="hidden" name="platform" value="<?= e($plat) ?>"><input type="hidden" name="accountId" value="<?= e($accId) ?>">
                <button class="text-sm text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg"><?= e(t('امسح من المنصة')) ?></button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (empty($post['platforms'])): ?><p class="text-sm text-ink-500"><?= e(t('المنشور ده مش متحدد له حسابات لسه.')) ?></p><?php endif; ?>
      </div>
    </div>
    <?php
});
