<?php
$err = null;
$z = zernio_or_null($err);
$accounts = [];
$boards = [];
$apiError = null;
$profileId = null;
$validation = null;
$old = ['content' => '', 'accounts' => [], 'when' => '', 'mode' => 'schedule', 'opt' => [], 'rec' => []];
// تكرار منشور من صفحة المنشورات: النص والحسابات بس، والمستخدم يراجع قبل النشر
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['dup_text'])) {
    $old['content'] = mb_substr((string)$_GET['dup_text'], 0, 10000);
    $old['accounts'] = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['dup_acc'] ?? ''))), function ($x) { return preg_match('/^[\w-]{1,64}$/', $x); }));
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string)($_GET['when'] ?? ''))) $old['when'] = (string)$_GET['when'];

if ($z) {
    try {
        $profileId = current_profile_id($z);
        $accounts = array_values(array_filter($z->accounts(zernio_profile()), function ($a) {
            return ($a['isActive'] ?? true) && empty($a['needsReconnection']);
        }));
        foreach ($accounts as $a) {
            if ($a['platform'] === 'pinterest') {
                try {
                    $boards[$a['_id']] = pub_pinterest_boards($z, $a['_id']);
                } catch (ZernioException $ex) {
                    $boards[$a['_id']] = [];
                }
            }
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

/** يبني إعدادات كل منصة من الفورم */
function cpro_platform_item(array $acc, array $o): array
{
    $p = $acc['platform'];
    $item = ['platform' => $p, 'accountId' => $acc['_id']];
    $custom = trim((string)($o['custom'] ?? ''));
    if ($custom !== '') $item['customContent'] = $custom;
    $d = [];
    $s = function ($k) use ($o) { return trim((string)($o[$k] ?? '')); };
    switch ($p) {
        case 'tiktok':
            $priv = in_array($s('privacy'), ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'], true) ? $s('privacy') : 'PUBLIC_TO_EVERYONE';
            $d = ['privacyLevel' => $priv, 'allowComment' => !empty($o['comment']), 'allowDuet' => !empty($o['duet']), 'allowStitch' => !empty($o['stitch']),
                  'contentPreviewConfirmed' => true, 'expressConsentGiven' => true];
            break;
        case 'youtube':
            if ($s('title') !== '') $d['title'] = mb_substr($s('title'), 0, 100);
            $d['visibility'] = in_array($s('visibility'), ['public', 'private', 'unlisted'], true) ? $s('visibility') : 'public';
            $d['madeForKids'] = !empty($o['kids']);
            if ($s('first_comment') !== '') $d['firstComment'] = $s('first_comment');
            break;
        case 'pinterest':
            if ($s('board') !== '') $d['boardId'] = $s('board');
            if ($s('title') !== '') $d['title'] = mb_substr($s('title'), 0, 100);
            if ($s('link') !== '') $d['link'] = $s('link');
            break;
        case 'instagram':
            if ($s('type') === 'story') $d['contentType'] = 'story';
            if ($s('type') === 'reel_only') $d['shareToFeed'] = false;
            if ($s('first_comment') !== '' && $s('type') !== 'story') $d['firstComment'] = $s('first_comment');
            break;
        case 'facebook':
            if (in_array($s('type'), ['story', 'reel'], true)) $d['contentType'] = $s('type');
            if ($s('first_comment') !== '' && $s('type') !== 'story') $d['firstComment'] = $s('first_comment');
            break;
        case 'linkedin':
            if ($s('first_comment') !== '') $d['firstComment'] = $s('first_comment');
            if (!empty($o['no_preview'])) $d['disableLinkPreview'] = true;
            break;
    }
    if ($d) $item['platformSpecificData'] = $d;
    return $item;
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
    $old['mode'] = in_array($_POST['mode'] ?? '', ['now', 'schedule', 'draft', 'queue'], true) ? $_POST['mode'] : 'schedule';
    $old['opt'] = (array)($_POST['opt'] ?? []);
    $old['rec'] = (array)($_POST['rec'] ?? []);
    $onlyCheck = ($_POST['action'] ?? '') === 'validate';

    $byId = [];
    foreach ($accounts as $a) $byId[$a['_id']] = $a;
    $platforms = [];
    foreach ($old['accounts'] as $id) {
        if (isset($byId[$id])) $platforms[] = cpro_platform_item($byId[$id], (array)($old['opt'][$id] ?? []));
    }

    $errors = [];
    if (!$platforms && $old['mode'] !== 'draft') $errors[] = t('اختار حساب واحد على الأقل.');

    // فحص المحتوى عند Zernio قبل أي حاجة
    if ($platforms) {
        try {
            $validation = pub_validate_post($z, ['content' => $old['content'], 'platforms' => $platforms]);
            if (empty($validation['valid'])) $errors[] = t('خدمة النشر لقت مشاكل في المنشور، راجعها تحت.');
        } catch (ZernioException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if ($onlyCheck) {
        if (!$errors) flash('success', t('المنشور سليم وجاهز.'));
        foreach ($errors as $m) flash('error', $m);
    } else {
        $media = [];
        if (!$errors && !empty($_FILES['media']['name'][0])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            foreach ($_FILES['media']['tmp_name'] as $i => $tmp) {
                $code = $_FILES['media']['error'][$i];
                if ($code === UPLOAD_ERR_NO_FILE) continue;
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
        // صور Higgsfield: بننزلها ونرفعها لأن روابطها بتنتهي بعد 7 أيام
        // صور من مكتبة الذكاء الاصطناعي بتاعة الشركة
        foreach (array_slice(array_unique((array)($_POST['ai_media'] ?? [])), 0, 4) as $n) {
            $n = (string)$n;
            $path = hf_lib_dir((int)ws('id')) . '/' . $n;
            if ($errors || !hf_lib_valid($n) || !is_file($path)) continue;
            try {
                $mime = (string)mime_content_type($path);
                $media[] = ['type' => substr($n, -4) === '.mp4' ? 'video' : 'image', 'url' => $z->uploadFile($path, 'ai-' . $n, $mime)];
            } catch (Throwable $ex) { $errors[] = $ex->getMessage(); }
        }
        if ($old['content'] === '' && !$media) $errors[] = t('اكتب نص المنشور أو ارفع صورة/فيديو.');

        $payload = ['content' => $old['content'], 'platforms' => $platforms, 'timezone' => site_timezone()];
        if ($media) $payload['mediaItems'] = $media;
        if ($old['mode'] === 'now') {
            $payload['publishNow'] = true;
        } elseif ($old['mode'] === 'draft') {
            $payload['isDraft'] = true;
        } elseif ($old['mode'] === 'queue') {
            $payload['queuedFromProfile'] = $profileId;
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
        if (!empty($old['rec']['on'])) {
            $gap = max(1, (int)($old['rec']['gap'] ?? 1));
            $rec = ['enabled' => true, 'gap' => $gap, 'gapFreq' => ($old['rec']['freq'] ?? '') === 'week' ? 'week' : 'month'];
            if ((int)($old['rec']['count'] ?? 0) > 0) $rec['expireCount'] = (int)$old['rec']['count'];
            $payload['recycling'] = $rec;
        }

        if (!$errors) {
            try {
                $r = $z->createPost($payload);
                log_activity('post.create_pro', $old['mode'] . ' ' . count($platforms) . ' accounts');
                $msg = ['now' => t('تم نشر المنشور.'), 'draft' => t('تم حفظ المسودة.'), 'schedule' => t('تمت جدولة المنشور.'), 'queue' => t('المنشور اتحط في الطابور.')][$old['mode']];
                flash('success', $msg);
                $newId = $r['post']['_id'] ?? '';
                redirect($newId ? url('post_edit', ['id' => $newId]) : url('posts'));
            } catch (ZernioException $ex) {
                $errors[] = $ex->getMessage();
            }
        }
        foreach ($errors as $m) flash('error', $m);
    }
}

render(t('منشور جديد'), 'compose', function () use ($z, $accounts, $boards, $apiError, $old, $validation) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); return; }
    if (!$accounts) {
        echo '<div class="card p-6">' . e(t('مفيش حسابات مربوطة.')) . ' <a class="link" href="' . e(url('accounts')) . '">' . e(t('اربط حساب الأول')) . '</a></div>';
        return;
    }
    $defaultWhen = (new DateTime('+1 hour', new DateTimeZone(site_timezone())))->format('Y-m-d\TH:00');
    $o = function ($acc, $k, $def = '') use ($old) { return $old['opt'][$acc][$k] ?? $def; };
    $posted = $_SERVER['REQUEST_METHOD'] === 'POST';
    $chk = function ($acc, $k, $def = true) use ($old, $posted) { return ($posted ? !empty($old['opt'][$acc][$k]) : $def) ? 'checked' : ''; };
    ?>
    <?php if ($validation && (!empty($validation['errors']) || !empty($validation['warnings']))): ?>
      <div class="card p-4 mb-5 space-y-1 text-sm">
        <?php foreach ($validation['errors'] ?? [] as $v): ?><p class="text-red-700">✕ <b><?= e(platform_label((string)($v['platform'] ?? ''))) ?>:</b> <?= e($v['error'] ?? '') ?></p><?php endforeach; ?>
        <?php foreach ($validation['warnings'] ?? [] as $v): ?><p class="text-amber-700">! <b><?= e(platform_label((string)($v['platform'] ?? ''))) ?>:</b> <?= e($v['warning'] ?? '') ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="grid lg:grid-cols-3 gap-6">
      <?= csrf_field() ?>
      <div class="lg:col-span-2 space-y-6">
        <div class="card p-5">
          <label class="block"><span class="label"><?= e(t('نص المنشور')) ?></span>
            <textarea data-ai="from_idea,img_prompt,improve,hashtags,cta,hook,shorten,tone_formal,tone_friendly,tone_excited,tone_funny,translate_en,translate_ar,variants,three,thread,review,score" name="content" id="content" rows="7" class="input" placeholder="<?= e(t('اكتب هنا...')) ?>"><?= e($old['content']) ?></textarea></label>
          <div class="flex flex-wrap items-center justify-between gap-2 mt-1"><div class="text-xs text-ink-500"><span id="charCount">0</span> <?= e(t('حرف · تويتر (X) بيقبل 280 حرف بس')) ?></div></div>
          <label class="block mt-4"><span class="label"><?= e(t('صور أو فيديو (اختياري)')) ?></span>
            <input type="file" name="media[]" id="mediaInput" multiple accept="image/*,video/*,application/pdf" class="input"></label>
          <div id="mediaPreview" class="flex flex-wrap gap-2 mt-3"></div>
          <?php if (ai_connection() && can('ai.use') && ai_feature_on('vision')): ?>
          <div class="flex flex-wrap items-center gap-2 mt-2" id="vis-box">
            <?php foreach (['from_image', 'alt_text'] as $vk): if (!ai_feature_on($vk)) continue; ?>
              <button type="button" data-vis="<?= $vk ?>" class="inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100 disabled:opacity-60"><?= ai_icon('spark', 'w-3.5 h-3.5') ?> <?= e(ai_presets()[$vk]['label']) ?></button>
            <?php endforeach; ?>
            <span class="text-[11px] text-ink-400"><?= e(t('يستخدم أول صورة مختارة. يحتاج موديلًا يدعم الصور.')) ?></span>
          </div>
          <script>
          (function () {
            var box = document.getElementById('vis-box'); if (!box) return;
            function shrink(file) {
              return new Promise(function (ok, bad) {
                var img = new Image(), url = URL.createObjectURL(file);
                img.onload = function () { var k = Math.min(1, 1024 / Math.max(img.width, img.height)), c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k); c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url); ok(c.toDataURL('image/jpeg', 0.85)); };
                img.onerror = bad; img.src = url;
              });
            }
            box.addEventListener('click', function (e) {
              var b = e.target.closest('[data-vis]'); if (!b) return;
              var f = [].slice.call(document.getElementById('mediaInput').files || []).filter(function (x) { return /^image\//.test(x.type); })[0];
              if (!f) { (window.aiToast || alert)(<?= json_encode(t('اختر صورة أولًا (أقل من 2 ميجا).'), JSON_UNESCAPED_UNICODE) ?>); return; }
              b.disabled = true; b.classList.add('animate-pulse');
              shrink(f).then(function (data) {
                var fd = new FormData(), c = document.getElementById('content'); fd.append('task', 'preset'); fd.append('preset', b.dataset.vis); fd.append('text', c.value); fd.append('image', data);
                return fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content } }).then(function (r) { return r.json(); }).then(function (d) {
                  if (d.error) { (window.aiToast || alert)(d.error); return; }
                  if (d.mode === 'show') { var p = box.querySelector('.vis-out') || document.createElement('div'); p.className = 'vis-out w-full rounded-xl border border-brand-200 bg-brand-50/60 p-3 text-sm'; p.textContent = d.text; box.appendChild(p); }
                  else { c.value = d.text; c.dispatchEvent(new Event('input', { bubbles: true })); c.focus(); }
                });
              }).catch(function () { (window.aiToast || alert)((window.T && T.liveFailed) || 'Error'); }).finally(function () { b.disabled = false; b.classList.remove('animate-pulse'); });
            });
          })();
          </script>
          <?php endif; ?>
          <?php if (connector('higgsfield') && ai_feature_on('image')): ?>
          <div class="mt-4 rounded-2xl border border-dashed border-ink-300 p-4" id="hf-box">
            <div class="flex items-center gap-2 mb-2"><span class="w-6 h-6 rounded-lg bg-[#0B0B0F] text-[#fff] text-xs font-semibold flex items-center justify-center">H</span><b class="text-sm text-ink-900"><?= e(t('توليد صورة بالذكاء الاصطناعي')) ?></b></div>
            <div class="flex flex-wrap items-center gap-2 mb-2 text-xs">
<?php if (ai_feature_on('video')): ?><select id="hf-kind" class="input !mt-0 !w-auto !py-1.5 text-xs"><option value="image"><?= e(t('صورة')) ?></option><option value="video"><?= e(t('فيديو (أغلى)')) ?></option></select><?php endif; ?>
              <select id="hf-aspect" class="input !mt-0 !w-auto !py-1.5 text-xs"><option value=""><?= e(t('المقاس الافتراضي')) ?></option><option value="1:1"><?= e(t('مربع 1:1')) ?></option><option value="4:5"><?= e(t('بوست 4:5')) ?></option><option value="9:16"><?= e(t('ستوري 9:16')) ?></option><option value="16:9"><?= e(t('عرضي 16:9')) ?></option></select>
              <label class="inline-flex items-center gap-1.5 text-ink-600 cursor-pointer"><input type="checkbox" id="hf-two" class="w-3.5 h-3.5"> <?= e(t('نسختين')) ?></label>
              <button type="button" id="hf-from-text" class="ms-auto link"><?= e(t('اكتب الوصف من نص المنشور')) ?></button>
            </div>
            <div class="flex gap-2 items-start">
              <textarea id="hf-prompt" rows="2" class="input !mt-0 flex-1 text-sm" placeholder="<?= e(t('صف الصورة: مثلاً كوب قهوة على طاولة خشب بإضاءة صباحية...')) ?>"></textarea>
              <button type="button" id="hf-go" class="btn-primary shrink-0"><?= e(t('توليد')) ?></button>
            </div>
            <p id="hf-status" class="text-xs text-ink-500 mt-2 hidden"></p>
            <div id="hf-results" class="grid grid-cols-3 sm:grid-cols-6 gap-2 mt-3">
              <?php foreach (hf_lib_list((int)ws('id'), 12) as $n): $isV = substr($n, -4) === '.mp4'; ?><div class="relative group"><button type="button" class="hf-img relative w-full rounded-xl overflow-hidden border-2 border-transparent hover:border-brand-400 transition aspect-square bg-ink-100" data-name="<?= e($n) ?>"><?php if ($isV): ?><video muted preload="metadata" class="w-full h-full object-cover" src="<?= e(url('ai_task', ['img' => $n])) ?>"></video><span class="absolute bottom-1 start-1 text-[10px] bg-black/70 text-[#fff] rounded px-1">▶</span><?php else: ?><img loading="lazy" class="w-full h-full object-cover" src="<?= e(url('ai_task', ['img' => $n])) ?>" alt=""><?php endif; ?><span class="absolute top-1.5 end-1.5 hidden w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs items-center justify-center">✓</span></button><?php if (!$isV && ai_feature_on('video') && filemtime(hf_lib_dir((int)ws('id')) . '/' . $n) > time() - 6.5 * 86400): ?><button type="button" class="hf-tovideo absolute bottom-1 end-1 hidden group-hover:flex rounded-lg bg-black/70 text-[#fff] text-[10px] px-1.5 py-0.5" data-src="<?= e($n) ?>" title="<?= e(t('تحويل لفيديو')) ?>"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 6h11v12H4zM15 10l5-3v10l-5-3"/></svg></button><?php endif; ?></div><?php endforeach; ?>
            </div>
            <p class="text-[11px] text-ink-400 mt-2"><?= e(t('اضغط على الصورة لإضافتها للمنشور. التوليد يستهلك من رصيد Higgsfield.')) ?></p>
          </div>
          <script>
          (function () {
            var go = document.getElementById('hf-go'), pr = document.getElementById('hf-prompt'), st = document.getElementById('hf-status'), res = document.getElementById('hf-results'), form = go.closest('form');
            var csrf = document.querySelector('meta[name=csrf-token]').content, label = go.textContent, pending = 0;
            function call(data) { var fd = new FormData(); for (var k in data) fd.append(k, data[k]); return fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf } }).then(function (r) { return r.json(); }); }
            function msg(t, err) { st.textContent = t; st.classList.remove('hidden'); st.classList.toggle('text-red-600', !!err); }
            function done() { if (--pending <= 0) { pending = 0; go.disabled = false; go.textContent = label; } }
            function toggle(b) {
              var on = !b.dataset.on; b.dataset.on = on ? '1' : ''; var n = b.dataset.name;
              b.classList.toggle('border-brand-600', on); b.lastChild.classList.toggle('hidden', !on); b.lastChild.classList.toggle('flex', on);
              var inp = form.querySelector('input[name="ai_media[]"][value="' + CSS.escape(n) + '"]');
              if (on && !inp) { inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'ai_media[]'; inp.value = n; form.appendChild(inp); }
              if (!on && inp) inp.remove();
            }
            res.addEventListener('click', function (e) {
              var v = e.target.closest('.hf-tovideo');
              if (v) { if (!pr.value.trim()) { pr.placeholder = <?= json_encode(t('صف الحركة: مثلاً الكاميرا تقترب ببطء والبخار يتصاعد'), JSON_UNESCAPED_UNICODE) ?>; pr.focus(); return; } start({ task: 'image', kind: 'video', text: pr.value, src: v.dataset.src }); return; }
              var b = e.target.closest('.hf-img'); if (b) toggle(b);
            });
            function add(img) {
              var b = document.createElement('button'); b.type = 'button'; b.dataset.name = img.name;
              b.className = 'hf-img relative rounded-xl overflow-hidden border-2 border-transparent hover:border-brand-400 transition aspect-square bg-ink-100';
              b.innerHTML = (img.video ? '<video muted preload="metadata" class="w-full h-full object-cover"></video>' : '<img class="w-full h-full object-cover" alt="">') + '<span class="absolute top-1.5 end-1.5 hidden w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs items-center justify-center">✓</span>';
              b.firstChild.src = img.src; res.prepend(b); toggle(b);
            }
            function poll(id, tries) {
              call({ task: 'image_status', id: id }).then(function (s) {
                if (s.error) { msg(s.error, true); done(); return; }
                if (s.status === 'completed') { (s.images || []).forEach(add); if (pending <= 1) st.classList.add('hidden'); done(); return; }
                if (tries > 60) { msg(<?= json_encode(t('التوليد أخذ وقتًا أطول من المعتاد. جرّب مرة أخرى.'), JSON_UNESCAPED_UNICODE) ?>, true); done(); return; }
                setTimeout(function () { poll(id, tries + 1); }, 3000);
              }).catch(function () { setTimeout(function () { poll(id, tries + 1); }, 5000); });
            }
            go.addEventListener('click', function () {
              if (!pr.value.trim()) { pr.focus(); return; }
              go.disabled = true; go.textContent = (window.T && T.working) || '...'; msg(<?= json_encode(t('جاري التوليد... غالبًا يأخذ من 10 إلى 60 ثانية.'), JSON_UNESCAPED_UNICODE) ?>);
              var kind = document.getElementById('hf-kind');
              start({ task: 'image', kind: kind ? kind.value : 'image', text: pr.value, aspect: document.getElementById('hf-aspect').value, count: document.getElementById('hf-two').checked ? 2 : 1 });
            });
            function start(data) {
              go.disabled = true; go.textContent = (window.T && T.working) || '...'; msg(data.kind === 'video' ? <?= json_encode(t('جاري توليد الفيديو... قد يأخذ عدة دقائق.'), JSON_UNESCAPED_UNICODE) ?> : <?= json_encode(t('جاري التوليد... غالبًا يأخذ من 10 إلى 60 ثانية.'), JSON_UNESCAPED_UNICODE) ?>);
              call(data).then(function (d) {
                if (d.error) { msg(d.error, true); go.disabled = false; go.textContent = label; return; }
                var ids = d.ids || [d.id]; pending = ids.length; ids.forEach(function (id) { poll(id, data.kind === 'video' ? -140 : 0); });
              }).catch(function () { msg((window.T && T.liveFailed) || 'Error', true); go.disabled = false; go.textContent = label; });
            }
            document.getElementById('hf-from-text').addEventListener('click', function () {
              var c = document.getElementById('content'); if (!c || !c.value.trim()) { c && c.focus(); return; }
              var b = this; b.classList.add('animate-pulse');
              call({ task: 'preset', preset: 'img_prompt', text: c.value }).then(function (d) { if (d.error) msg(d.error, true); else { pr.value = d.text; pr.focus(); } }).finally(function () { b.classList.remove('animate-pulse'); });
            });
          })();
          </script>
          <?php endif; ?>
          <p class="text-xs text-ink-500 mt-1"><?= e(t('أقصى حجم للملف على السيرفر بتاعك:')) ?> <?= e(ini_get('upload_max_filesize')) ?></p>
        </div>

        <div class="space-y-4" id="platformPanels">
          <p class="text-sm text-ink-500" id="noPanels"><?= e(t('اختار الحسابات اللي هتنشر عليها، وتقدر تخصص النص والإعدادات لكل حساب هنا.')) ?></p>
          <?php foreach ($accounts as $a): $id = $a['_id']; $p = $a['platform']; $n = 'opt[' . $id . ']'; ?>
            <details class="card hidden panel group" data-acc="<?= e($id) ?>" <?= ($o($id, 'custom') !== '' || $o($id, 'first_comment') !== '' || $o($id, 'title') !== '') ? 'open' : '' ?>>
              <summary class="flex items-center gap-2 p-4 cursor-pointer select-none">
                <?= avatar($a['profilePicture'] ?? null, $a['displayName'] ?? '', 'w-7 h-7') ?>
                <b class="text-sm"><?= e($a['displayName'] ?? $a['username'] ?? '') ?></b>
                <span class="badge"><?= e(platform_label($p)) ?></span>
                <span class="ms-auto text-xs text-ink-500"><?= e(t('تخصيص (اختياري)')) ?></span>
                <svg class="w-4 h-4 text-ink-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
              </summary>
              <div class="px-5 pb-5 space-y-3 border-t border-ink-100 pt-4">
              <label class="block"><span class="label"><?= e(t('نص خاص بالحساب ده (اختياري)')) ?></span>
                <textarea data-ai="improve,shorten,hashtags,translate_en,translate_ar" name="<?= e($n) ?>[custom]" rows="3" class="input" placeholder="<?= e(t('سيبه فاضي عشان يستخدم النص الأساسي')) ?>"><?= e($o($id, 'custom')) ?></textarea></label>

              <?php if ($p === 'tiktok'): ?>
                <label class="block"><span class="label"><?= e(t('مين يشوف الفيديو؟')) ?></span>
                  <select name="<?= e($n) ?>[privacy]" class="input">
                    <?php foreach (['PUBLIC_TO_EVERYONE' => t('الكل'), 'MUTUAL_FOLLOW_FRIENDS' => t('الأصحاب بس'), 'FOLLOWER_OF_CREATOR' => t('المتابعين بس'), 'SELF_ONLY' => t('أنا بس')] as $k => $l): ?>
                      <option value="<?= $k ?>" <?= $o($id, 'privacy') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <div class="flex flex-wrap gap-4 text-sm">
                  <label class="flex items-center gap-2"><input type="checkbox" name="<?= e($n) ?>[comment]" value="1" <?= $chk($id, 'comment') ?>> <?= e(t('اسمح بالتعليقات')) ?></label>
                  <label class="flex items-center gap-2"><input type="checkbox" name="<?= e($n) ?>[duet]" value="1" <?= $chk($id, 'duet') ?>> <?= e(t('اسمح بالـ Duet')) ?></label>
                  <label class="flex items-center gap-2"><input type="checkbox" name="<?= e($n) ?>[stitch]" value="1" <?= $chk($id, 'stitch') ?>> <?= e(t('اسمح بالـ Stitch')) ?></label>
                </div>
              <?php elseif ($p === 'youtube'): ?>
                <label class="block"><span class="label"><?= e(t('عنوان الفيديو')) ?></span>
                  <input name="<?= e($n) ?>[title]" maxlength="100" class="input" value="<?= e($o($id, 'title')) ?>"></label>
                <label class="block"><span class="label"><?= e(t('مين يشوف الفيديو؟')) ?></span>
                  <select name="<?= e($n) ?>[visibility]" class="input">
                    <?php foreach (['public' => t('عام للكل'), 'unlisted' => t('باللينك بس'), 'private' => t('خاص')] as $k => $l): ?>
                      <option value="<?= $k ?>" <?= $o($id, 'visibility') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="<?= e($n) ?>[kids]" value="1" <?= $chk($id, 'kids', false) ?>> <?= e(t('الفيديو معمول للأطفال')) ?></label>
                <label class="block"><span class="label"><?= e(t('أول تعليق (اختياري)')) ?></span>
                  <input name="<?= e($n) ?>[first_comment]" class="input" value="<?= e($o($id, 'first_comment')) ?>"></label>
              <?php elseif ($p === 'pinterest'): ?>
                <label class="block"><span class="label"><?= e(t('اللوحة (Board)')) ?></span>
                  <select name="<?= e($n) ?>[board]" class="input">
                    <option value=""><?= e(t('أول لوحة (تلقائي)')) ?></option>
                    <?php foreach ($boards[$id] ?? [] as $b): ?>
                      <option value="<?= e($b['id']) ?>" <?= $o($id, 'board') === $b['id'] ? 'selected' : '' ?>><?= e($b['name'] ?? $b['id']) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <label class="block"><span class="label"><?= e(t('عنوان الـ Pin')) ?></span>
                  <input name="<?= e($n) ?>[title]" maxlength="100" class="input" value="<?= e($o($id, 'title')) ?>"></label>
                <label class="block"><span class="label"><?= e(t('رابط الـ Pin (اختياري)')) ?></span>
                  <input name="<?= e($n) ?>[link]" type="url" class="input" dir="ltr" value="<?= e($o($id, 'link')) ?>"></label>
              <?php elseif ($p === 'instagram' || $p === 'facebook'): ?>
                <label class="block"><span class="label"><?= e(t('نوع المنشور')) ?></span>
                  <select name="<?= e($n) ?>[type]" class="input">
                    <?php $types = $p === 'instagram'
                        ? ['' => t('منشور عادي أو Reel (حسب الميديا)'), 'reel_only' => t('Reel في تبويب الريلز بس'), 'story' => t('ستوري (24 ساعة)')]
                        : ['' => t('منشور عادي'), 'reel' => t('Reel'), 'story' => t('ستوري (24 ساعة)')];
                    foreach ($types as $k => $l): ?>
                      <option value="<?= $k ?>" <?= $o($id, 'type') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <label class="block"><span class="label"><?= e(t('أول تعليق (اختياري، مش للستوري)')) ?></span>
                  <input name="<?= e($n) ?>[first_comment]" class="input" value="<?= e($o($id, 'first_comment')) ?>"></label>
              <?php elseif ($p === 'linkedin'): ?>
                <label class="block"><span class="label"><?= e(t('أول تعليق (اختياري)')) ?></span>
                  <input name="<?= e($n) ?>[first_comment]" class="input" value="<?= e($o($id, 'first_comment')) ?>"></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="<?= e($n) ?>[no_preview]" value="1" <?= $chk($id, 'no_preview', false) ?>> <?= e(t('متعرضش معاينة للروابط')) ?></label>
              <?php endif; ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="space-y-6">
        <div class="card p-5">
          <div class="flex items-center justify-between mb-3"><span class="font-bold"><?= e(t('انشر على')) ?></span>
            <button type="button" id="selectAll" class="text-xs link"><?= e(t('اختار الكل')) ?></button></div>
          <div class="space-y-2 max-h-80 overflow-auto">
            <?php foreach ($accounts as $a): ?>
              <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-ink-50 cursor-pointer">
                <input type="checkbox" name="accounts[]" value="<?= e($a['_id']) ?>" <?= in_array($a['_id'], $old['accounts'], true) ? 'checked' : '' ?> class="acc w-4 h-4" data-plat="<?= e($a['platform'] ?? '') ?>" data-name="<?= e($a['displayName'] ?? $a['username'] ?? '') ?>" data-user="<?= e($a['username'] ?? '') ?>" data-pic="<?= e($a['profilePicture'] ?? '') ?>">
                <?= avatar($a['profilePicture'] ?? null, $a['displayName'] ?? $a['username'] ?? '', 'w-8 h-8') ?>
                <span class="text-sm leading-tight"><span class="block font-semibold"><?= e($a['displayName'] ?? $a['username'] ?? '') ?></span>
                  <span class="block text-xs text-ink-500"><?= e(platform_label($a['platform'])) ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card p-5 space-y-3">
          <span class="font-bold block"><?= e(t('إمتى؟')) ?></span>
          <?php foreach (['schedule' => t('جدولة لوقت محدد'), 'queue' => t('ضيف للطابور (أقرب ميعاد فاضي)'), 'now' => t('انشر دلوقتي'), 'draft' => t('احفظ كمسودة')] as $k => $l): ?>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="mode" value="<?= $k ?>" <?= $old['mode'] === $k ? 'checked' : '' ?> class="mode"> <?= e($l) ?></label>
          <?php endforeach; ?>
          <div id="whenBox">
            <input type="datetime-local" name="when" id="whenInput" value="<?= e($old['when'] ?: $defaultWhen) ?>" class="input" dir="ltr">
            <p class="text-xs text-ink-500 mt-1"><?= e(t('بتوقيت:')) ?> <?= e(site_timezone()) ?></p>
            <?php $nb = (module_enabled('analytics') && function_exists('next_best_time')) ? next_best_time($z) : null; if ($nb): ?>
              <button type="button" class="mt-2 w-full inline-flex items-center justify-center gap-2 rounded-xl border border-lime-300 bg-lime-50 text-lime-700 px-3 py-2 text-sm hover:bg-lime-100 transition"
                onclick="document.getElementById('whenInput').value='<?= e($nb->format('Y-m-d\TH:i')) ?>'">
                <?= ui_icon('zap', 'w-4 h-4 text-amber-600 inline') ?> <?= e(t('انشر في أحسن وقت: %s', weekday_name((int)$nb->format('w')) . ' ' . $nb->format('h:i A'))) ?>
              </button>
              <p class="text-[11px] text-ink-400 mt-1"><?= e(t('محسوب من تفاعل منشوراتك اللي فاتت.')) ?></p>
            <?php endif; ?>
          </div>
          <p class="text-xs text-ink-500"><?= e(t('الطابور: مواعيد ثابتة في الأسبوع، والمنشور بياخد أقرب ميعاد فاضي.')) ?>
            <a class="link" href="<?= e(url('queue')) ?>" data-modal="<?= e(url('queue')) ?>" data-modal-title="<?= e(t('مواعيد الطابور')) ?>"><?= e(t('إدارة المواعيد')) ?></a></p>
        </div>

        <details class="card group" <?= !empty($old['rec']['on']) ? 'open' : '' ?>>
          <summary class="flex items-center justify-between p-5 cursor-pointer select-none font-bold"><?= e(t('خيارات إضافية')) ?>
            <svg class="w-4 h-4 text-ink-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></summary>
          <div class="px-5 pb-5 space-y-3">
          <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="rec[on]" value="1" <?= !empty($old['rec']['on']) ? 'checked' : '' ?>> <?= e(t('إعادة النشر تلقائياً')) ?></label>
          <p class="text-xs text-ink-500"><?= e(t('بعد النشر، المنشور هيتنشر تاني كل فترة. مش متاح ليوتيوب وتيك توك.')) ?></p>
          <div class="flex gap-2 items-center text-sm">
            <span><?= e(t('كل')) ?></span>
            <input type="number" min="1" name="rec[gap]" value="<?= e($old['rec']['gap'] ?? '1') ?>" class="input !mt-0 w-20">
            <select name="rec[freq]" class="input !mt-0 w-28">
              <option value="week" <?= ($old['rec']['freq'] ?? '') === 'week' ? 'selected' : '' ?>><?= e(t('أسبوع')) ?></option>
              <option value="month" <?= ($old['rec']['freq'] ?? 'month') === 'month' ? 'selected' : '' ?>><?= e(t('شهر')) ?></option>
            </select>
          </div>
          <label class="block text-sm"><?= e(t('يقف بعد كام مرة؟ (فاضي = مفيش حد)')) ?>
            <input type="number" min="1" name="rec[count]" value="<?= e($old['rec']['count'] ?? '') ?>" class="input"></label>
          </div>
        </details>

        <div class="space-y-2">
          <button name="action" value="validate" class="btn-ghost w-full"><?= e(t('افحص المنشور بس')) ?></button>
          <button name="action" value="submit" id="submitBtn" class="btn-primary w-full !py-3" onclick="var m=this.form.querySelector('.mode:checked');return !m||m.value!=='now'||confirm('<?= tjs('المنشور هيتنشر فوراً على كل الحسابات المختارة. تكمل؟') ?>')"><?= e(t('تنفيذ')) ?></button>
          <p class="text-xs text-ink-500"><?= e(t('قبل التنفيذ بنفحص المنشور عند خدمة النشر، ولو فيه مشكلة مش هيتنشر.')) ?></p>
        </div>
      </div>
    </form>
    <?php $cpPlats = array_values(array_unique(array_map(function ($a) { return (string)($a['platform'] ?? ''); }, $accounts))); ?>
    <script>window.CPX = <?= json_encode(['ws' => (int)ws('id'),
      'icons' => array_combine($cpPlats, array_map(function ($p) { return platform_icon($p, 'w-3.5 h-3.5'); }, $cpPlats)),
      'plat' => array_combine($cpPlats, array_map('platform_label', $cpPlats)),
      'tooLong' => t('أطول من المسموح بـ %s حرف'), 'tags' => t('%s هاشتاج'), 'emoji' => t('إيموجي'), 'hashtag' => t('هاشتاج'), 'line' => t('سطر جديد'),
      'searchAcc' => t('ابحث في الحسابات...'), 'remove' => t('حذف'), 'drop' => t('اسحب الصور أو الفيديو هنا، أو اضغط للاختيار'), 'dropSub' => t('تقدر كمان تلصق صورة في خانة النص (Ctrl+V)'),
      'xMax' => t('X بيقبل 4 صور بس'), 'needVideo' => t('تيك توك ويوتيوب محتاجين فيديو'), 'igMedia' => t('إنستجرام محتاج صورة أو فيديو'),
      'preview' => t('معاينة'), 'pickAcc' => t('اختار حساب عشان تشوف المعاينة'), 'more' => t('المزيد'), 'emptyText' => t('اكتب نص المنشور أو ضيف صورة'), 'like' => t('إعجاب'), 'comment' => t('تعليق'), 'share' => t('مشاركة'), 'customText' => t('الحساب ده بيستخدم نص خاص بيه'),
      'modes' => ['now' => t('هيتنشر دلوقتي'), 'draft' => t('هيتحفظ كمسودة'), 'queue' => t('أقرب ميعاد فاضي في الطابور'), 'schedule' => t('مجدول')],
      'past' => t('الميعاد ده فات'), 'inMin' => t('بعد %s دقيقة'), 'inHr' => t('بعد %s ساعة'), 'inDay' => t('بعد %s يوم'),
      'quick' => [[t('بعد ساعة'), 'h1'], [t('بعد 3 ساعات'), 'h3'], [t('النهارده 8 م'), '0:20'], [t('بكرة 10 ص'), '1:10'], [t('بكرة 8 م'), '1:20']],
      'onN' => t('هيتنشر على %s حساب'), 'draftSaved' => t('المسودة محفوظة'), 'restore' => t('عندك مسودة لسه ماتنشرتش:'), 'restoreBtn' => t('استرجاع'), 'discard' => t('تجاهل')], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/compose-ux.js?v=<?= e(APP_VERSION) ?>"></script>
    <script>
      (function () {
        function sync() {
          var any = false;
          document.querySelectorAll('.acc').forEach(function (c) {
            var p = document.querySelector('.panel[data-acc="' + c.value + '"]');
            if (p) p.classList.toggle('hidden', !c.checked);
            if (c.checked) any = true;
          });
          document.getElementById('noPanels').classList.toggle('hidden', any);
          var m = document.querySelector('.mode:checked');
          document.getElementById('whenBox').classList.toggle('hidden', !m || m.value !== 'schedule');
        }
        document.querySelectorAll('.acc,.mode').forEach(function (el) { el.addEventListener('change', sync); });
        sync();
      })();
    </script>
    <?php
});
