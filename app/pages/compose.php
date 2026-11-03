<?php
$err = null;
$z = zernio_or_null($err);
$accounts = [];
$apiError = null;
$old = ['content' => '', 'accounts' => [], 'when' => '', 'mode' => 'schedule'];

if ($z) {
    try {
        $accounts = $z->accounts(zernio_profile());
        $accounts = array_values(array_filter($accounts, function ($a) {
            return ($a['isActive'] ?? true) && empty($a['needsReconnection']);
        }));
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

$allowedMime = [
    'image/jpeg' => 'image', 'image/png' => 'image', 'image/webp' => 'image', 'image/gif' => 'gif',
    'video/mp4' => 'video', 'video/quicktime' => 'video', 'video/webm' => 'video',
    'application/pdf' => 'document',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && !$apiError) {
    $old['content'] = trim((string)($_POST['content'] ?? ''));
    $old['accounts'] = array_map('strval', (array)($_POST['accounts'] ?? []));
    $old['when'] = (string)($_POST['when'] ?? '');
    $old['mode'] = in_array($_POST['mode'] ?? '', ['now', 'schedule', 'draft'], true) ? $_POST['mode'] : 'schedule';

    $byId = [];
    foreach ($accounts as $a) {
        $byId[$a['_id']] = $a;
    }
    $platforms = [];
    foreach ($old['accounts'] as $id) {
        if (isset($byId[$id])) {
            $platforms[] = ['platform' => $byId[$id]['platform'], 'accountId' => $id];
        }
    }

    $errors = [];
    if (!$platforms && $old['mode'] !== 'draft') {
        $errors[] = t('اختار حساب واحد على الأقل.');
    }

    // رفع الصور والفيديوهات
    $media = [];
    if (!$errors && !empty($_FILES['media']['name'][0])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($_FILES['media']['tmp_name'] as $i => $tmp) {
            $code = $_FILES['media']['error'][$i];
            if ($code === UPLOAD_ERR_NO_FILE) continue;
            if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
                $errors[] = t('الملف "%s" أكبر من الحجم المسموح على السيرفر (%s).', $_FILES['media']['name'][$i], ini_get('upload_max_filesize'));
                continue;
            }
            if ($code !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                $errors[] = t('فشل رفع ملف "%s".', $_FILES['media']['name'][$i]);
                continue;
            }
            $mime = $finfo->file($tmp);
            if (!isset($allowedMime[$mime])) {
                $errors[] = t('نوع الملف "%s" غير مدعوم.', $_FILES['media']['name'][$i]);
                continue;
            }
            try {
                $name = preg_replace('/[^\w.\-]+/u', '_', basename($_FILES['media']['name'][$i]));
                $media[] = ['type' => $allowedMime[$mime], 'url' => $z->uploadFile($tmp, $name, $mime)];
            } catch (ZernioException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
    }

    if ($old['content'] === '' && !$media) {
        $errors[] = t('اكتب نص المنشور أو ارفع صورة/فيديو.');
    }

    $payload = ['content' => $old['content'], 'platforms' => $platforms, 'timezone' => site_timezone()];
    if ($media) {
        $payload['mediaItems'] = $media;
    }
    if ($old['mode'] === 'now') {
        $payload['publishNow'] = true;
    } elseif ($old['mode'] === 'draft') {
        $payload['isDraft'] = true;
    } else {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $old['when'], new DateTimeZone(site_timezone()));
        if (!$dt) {
            $errors[] = t('اختار تاريخ ووقت النشر.');
        } elseif ($dt < new DateTime('now', new DateTimeZone(site_timezone()))) {
            $errors[] = t('وقت النشر لازم يكون في المستقبل.');
        } else {
            $payload['scheduledFor'] = $dt->format('Y-m-d\TH:i:s');
        }
    }

    // إعدادات افتراضية لتيك توك (مطلوبة منهم)
    foreach ($platforms as $pl) {
        if ($pl['platform'] === 'tiktok') {
            $payload['tiktokSettings'] = [
                'privacyLevel' => 'PUBLIC_TO_EVERYONE', 'allowComment' => true,
                'allowDuet' => true, 'allowStitch' => true,
                'contentPreviewConfirmed' => true, 'expressConsentGiven' => true,
            ];
        }
    }

    if (!$errors) {
        try {
            $z->createPost($payload);
            log_activity('post.create', $old['mode'] . ' ' . count($platforms) . ' accounts');
            $msg = ['now' => t('تم نشر المنشور.'), 'draft' => t('تم حفظ المسودة.'), 'schedule' => t('تمت جدولة المنشور.')][$old['mode']];
            flash('success', $msg);
            redirect(url('posts'));
        } catch (ZernioException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
    foreach ($errors as $m) {
        flash('error', $m);
    }
}

render(t('منشور جديد'), 'compose', function () use ($z, $accounts, $apiError, $old) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    if (!$accounts) {
        echo '<div class="card p-6">' . e(t('مفيش حسابات مربوطة.')) . ' <a class="link" href="' . e(url('accounts')) . '">' . e(t('اربط حساب الأول')) . '</a></div>';
        return;
    }
    $defaultWhen = (new DateTime('+1 hour', new DateTimeZone(site_timezone())))->format('Y-m-d\TH:00');
    ?>
    <form method="post" enctype="multipart/form-data" class="grid lg:grid-cols-3 gap-6" id="composeForm">
      <?= csrf_field() ?>
      <div class="lg:col-span-2 space-y-6">
        <?= card_open('p-5') ?>
          <label class="block font-bold mb-2"><?= e(t('نص المنشور')) ?></label>
          <textarea name="content" rows="8" id="content" class="w-full border border-slate-300 rounded-lg p-3 focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="<?= e(t('اكتب هنا...')) ?>"><?= e($old['content']) ?></textarea>
          <div class="text-xs text-slate-500 mt-1"><span id="charCount">0</span> <?= e(t('حرف · تويتر (X) بيقبل 280 حرف بس')) ?></div>
        </div>

        <?= card_open('p-5') ?>
          <label class="block font-bold mb-2"><?= e(t('صور أو فيديو (اختياري)')) ?></label>
          <input type="file" name="media[]" multiple accept="image/*,video/*,application/pdf" id="mediaInput"
                 class="block w-full text-sm file:me-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-semibold">
          <div id="mediaPreview" class="flex flex-wrap gap-2 mt-3"></div>
          <p class="text-xs text-slate-500 mt-2"><?= e(t('أقصى حجم للملف على السيرفر بتاعك:')) ?> <?= e(ini_get('upload_max_filesize')) ?></p>
        </div>
      </div>

      <div class="space-y-6">
        <?= card_open('p-5') ?>
          <div class="flex items-center justify-between mb-3">
            <span class="font-bold"><?= e(t('انشر على')) ?></span>
            <button type="button" id="selectAll" class="text-xs text-indigo-700 font-semibold"><?= e(t('اختار الكل')) ?></button>
          </div>
          <div class="space-y-2 max-h-80 overflow-auto">
            <?php foreach ($accounts as $a): $checked = in_array($a['_id'], $old['accounts'], true); ?>
              <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 cursor-pointer">
                <input type="checkbox" name="accounts[]" value="<?= e($a['_id']) ?>" <?= $checked ? 'checked' : '' ?> class="acc w-4 h-4 accent-indigo-600">
                <?= avatar($a['profilePicture'] ?? null, $a['displayName'] ?? $a['username'] ?? '', 'w-8 h-8') ?>
                <span class="text-sm leading-tight">
                  <span class="block font-semibold"><?= e($a['displayName'] ?? $a['username'] ?? '') ?></span>
                  <span class="block text-xs text-slate-500"><?= e(platform_label($a['platform'])) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <?= card_open('p-5 space-y-3') ?>
          <span class="font-bold block"><?= e(t('إمتى؟')) ?></span>
          <?php foreach (['schedule' => t('جدولة لوقت محدد'), 'now' => t('انشر دلوقتي'), 'draft' => t('احفظ كمسودة')] as $k => $l): ?>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="mode" value="<?= $k ?>" <?= $old['mode'] === $k ? 'checked' : '' ?> class="mode accent-indigo-600"> <?= $l ?></label>
          <?php endforeach; ?>
          <div id="whenBox">
            <input type="datetime-local" name="when" value="<?= e($old['when'] ?: $defaultWhen) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2" dir="ltr">
            <p class="text-xs text-slate-500 mt-1"><?= e(t('بتوقيت:')) ?> <?= e(site_timezone()) ?></p>
          </div>
          <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-3 disabled:opacity-60" id="submitBtn"><?= e(t('تنفيذ')) ?></button>
        </div>
      </div>
    </form>
    <?php
});
