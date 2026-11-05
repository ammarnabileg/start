<?php
$err = null;
$z = zernio_or_null($err);
$pageToken = (string)($_GET['page'] ?? '');

$accounts = [];
$options = [];
$apiError = null;
if ($z) {
    try {
        $accounts = gmb_accounts($z);
        $options = gmb_location_options($z, $accounts);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}
$loc = gmb_pick_location($options);
$cats = gmb_media_categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && $loc) {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'upload') {
            $f = media_lib_file('photo') ?: ($_FILES['photo'] ?? null);
            $cat = (string)($_POST['category'] ?? 'ADDITIONAL');
            if (!isset($cats[$cat])) $cat = 'ADDITIONAL';
            if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new ZernioException(t('اختار صورة الأول.'));
            }
            $mime = (string)(function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : ($f['type'] ?? ''));
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                throw new ZernioException(t('الصورة لازم تكون JPG أو PNG.'));
            }
            if ($f['size'] < 10240 || $f['size'] > 5 * 1024 * 1024) {
                throw new ZernioException(t('حجم الصورة لازم يكون بين 10 كيلوبايت و 5 ميجا.'));
            }
            $info = @getimagesize($f['tmp_name']);
            if ($info && ($info[0] < 250 || $info[1] < 250)) {
                throw new ZernioException(t('أقل مقاس للصورة 250×250 بكسل.'));
            }
            $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string)$f['name'])) ?: 'photo.jpg';
            $url = $z->uploadFile($f['tmp_name'], $name, $mime);
            if (empty($f['lib'])) media_capture($f['tmp_name'], (string)$f['name'], 'Google Business'); else media_log_use((int)ws('id'), (int)$f['lib_id'], 'gmb', (string)($loc['location'] ?? ''));
            gmb_add_media($z, $loc['account'], $loc['location'], $url, $cat, trim((string)($_POST['description'] ?? '')));
            log_activity('gmb.media_add', $cat);
            flash('success', t('تم رفع الصورة. ممكن تاخد شوية وقت لحد ما تظهر على جوجل.'));
        } elseif ($action === 'delete' && can('gmb.manage')) {
            $mediaId = (string)($_POST['media_id'] ?? '');
            if ($mediaId === '') throw new ZernioException(t('الصورة مش موجودة.'));
            gmb_delete_media($z, $loc['account'], $loc['location'], $mediaId);
            log_activity('gmb.media_delete', $mediaId);
            flash('success', t('تم حذف الصورة.'));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('gmb_media', ['loc' => $loc['key']]));
}

$items = [];
$next = null;
$total = 0;
$filter = (string)($_GET['cat'] ?? '');
if ($z && $loc) {
    try {
        $r = gmb_media($z, $loc['account'], $loc['location'], $pageToken ?: null);
        $items = $r['mediaItems'] ?? [];
        $next = $r['nextPageToken'] ?? null;
        $total = (int)($r['totalMediaItemsCount'] ?? count($items));
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('صور جوجل بيزنس'), 'gmb_media', function () use ($z, $accounts, $options, $loc, $items, $next, $total, $cats, $filter, $apiError) {
    if (!$z) { no_api_box(); return; }
    if (!$accounts && !$apiError) { gmb_no_account_box(); return; }
    if ($apiError) { error_box($apiError); echo '<div class="h-4"></div>'; }
    gmb_location_picker($options, $loc, 'gmb_media');
    if (!$loc) return;
    $used = [];
    foreach ($items as $m) {
        $c = (string)($m['locationAssociation']['category'] ?? '');
        if ($c !== '') $used[$c] = ($used[$c] ?? 0) + 1;
    }
    ?>
    <form method="post" enctype="multipart/form-data" class="card p-5 mb-4 flex flex-wrap items-end gap-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="loc" value="<?= e($loc['key']) ?>">
      <label class="block"><span class="label"><?= e(t('صورة جديدة (JPG أو PNG)')) ?></span>
        <input type="file" name="photo" accept="image/jpeg,image/png" required data-lib="image" class="input"></label>
      <label class="block"><span class="label"><?= e(t('مكانها')) ?></span>
        <select name="category" class="input !w-auto">
          <?php foreach ($cats as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
        </select></label>
      <label class="block flex-1 min-w-[12rem]"><span class="label"><?= e(t('وصف (اختياري)')) ?></span>
        <input name="description" class="input" maxlength="300"></label>
      <button class="btn-primary"><?= e(t('رفع')) ?></button>
    </form>

    <div class="flex flex-wrap items-center gap-2 mb-3 text-sm">
      <span class="text-ink-500"><?= e(t('%s صورة', $total)) ?></span>
      <a href="<?= e(url('gmb_media', ['loc' => $loc['key']])) ?>" class="badge <?= $filter === '' ? 'badge-green' : '' ?>"><?= e(t('الكل')) ?></a>
      <?php foreach ($used as $c => $n): ?>
        <a href="<?= e(url('gmb_media', ['loc' => $loc['key'], 'cat' => $c])) ?>" class="badge <?= $filter === $c ? 'badge-green' : '' ?>"><?= e($cats[$c] ?? $c) ?> (<?= $n ?>)</a>
      <?php endforeach; ?>
    </div>

    <?php if (!$items): ?><div class="card p-8 text-center text-sm text-ink-500"><?= e(t('مفيش صور على الفرع ده.')) ?></div><?php endif; ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
      <?php foreach ($items as $m):
        $c = (string)($m['locationAssociation']['category'] ?? '');
        if ($filter !== '' && $c !== $filter) continue;
        $id = basename((string)($m['name'] ?? ''));
        $src = $m['thumbnailUrl'] ?? $m['googleUrl'] ?? '';
      ?>
        <div class="card overflow-hidden">
          <a href="<?= e($m['googleUrl'] ?? $src) ?>" target="_blank" rel="noopener" class="block aspect-square bg-ink-100">
            <?php if ($src): ?><img src="<?= e($src) ?>" class="w-full h-full object-cover" loading="lazy" referrerpolicy="no-referrer" alt=""><?php endif; ?>
          </a>
          <div class="p-2 flex items-center gap-1 text-[11px]">
            <span class="flex-1 min-w-0 truncate text-ink-600"><?= e($cats[$c] ?? ($c ?: (($m['mediaFormat'] ?? '') === 'VIDEO' ? t('فيديو') : t('صورة')))) ?> · <?= e(fmt_date($m['createTime'] ?? null, 'd/m/Y')) ?></span>
            <?php if (can('gmb.manage') && $id !== ''): ?>
              <form method="post" onsubmit="return confirm('<?= tjs('حذف الصورة من جوجل؟') ?>')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="loc" value="<?= e($loc['key']) ?>"><input type="hidden" name="media_id" value="<?= e($id) ?>">
                <button class="text-red-600" title="<?= e(t('حذف')) ?>"><?= e(t('حذف')) ?></button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($next): ?>
      <div class="text-center mt-4"><a class="btn-ghost" href="<?= e(url('gmb_media', ['loc' => $loc['key'], 'page' => $next])) ?>"><?= e(t('صور أكتر')) ?></a></div>
    <?php endif; ?>
    <?php
});
