<?php
$err = null;
$z = zernio_or_null($err);

$stars = (int)($_GET['stars'] ?? 0);
$replied = (string)($_GET['replied'] ?? '');
if (!in_array($replied, ['', 'yes', 'no'], true)) $replied = '';
$cursor = (string)($_GET['cursor'] ?? '');
$platform = (string)($_GET['platform'] ?? '');
if (!in_array($platform, ['', 'googlebusiness', 'facebook'], true)) $platform = '';

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
$loc = gmb_pick_location($options, true);
$keep = ['loc' => $loc['key'] ?? '', 'stars' => $stars ?: '', 'replied' => $replied, 'platform' => $platform];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    if (in_array($action, ['ai_themes', 'ai_drafts'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        if (!can('ai.use') || !function_exists('ai_connection') || !ai_connection()) { echo json_encode(['ok' => false, 'error' => t('اربط مزود ذكاء اصطناعي الأول من صفحة التكاملات.')], JSON_UNESCAPED_UNICODE); exit; }
        $rows = array_slice(array_filter((array)(json_decode((string)($_POST['reviews'] ?? '[]'), true) ?: []), 'is_array'), 0, $action === 'ai_themes' ? 80 : 12);
        $lines = array_map(function ($r) { return '[' . mb_substr((string)($r['id'] ?? ''), 0, 60) . '] ' . (int)($r['rating'] ?? 0) . '★ ' . mb_substr((string)($r['name'] ?? ''), 0, 40) . ': ' . mb_substr(preg_replace('/\s+/u', ' ', (string)($r['text'] ?? '')), 0, 500); }, $rows);
        if ($action === 'ai_themes') {
            $sys = 'أنت محلل تجربة عملاء. حلّل تقييمات العملاء المعطاة فقط ولا تخترع شيئًا. اكتب بالعربية الفصحى المبسطة. أعد JSON فقط: {"verdict":"سطر يلخص الانطباع العام","praise":[{"theme":"موضوع","count":عدد التقييمات,"quote":"اقتباس قصير حرفي من تقييم"}],"complaints":[{"theme":"","count":0,"quote":"","fix":"إجراء عملي مقترح"}],"actions":["أهم 3 إجراءات لرفع التقييم مرتبة بالأولوية"]}. حتى 5 لكل قائمة، مرتبة بالعدد.';
            $max = 1200;
        } else {
            $sys = 'أنت مسؤول خدمة العملاء للنشاط التجاري (استخدم معلومات الشركة المتاحة لك). اكتب ردًا لكل تقييم: شخصي ويذكر تفصيلة من كلام العميل، قصير (1-3 جمل)، بنفس لغة ولهجة التقييم. للتقييم السلبي: اعتذر بدون جدال واطلب التواصل الخاص لحل المشكلة، ولا تعد بتعويض أو خصم غير موجود. للتقييم الإيجابي: اشكر وادعُه للعودة. لا تخترع أسماء موظفين أو أرقام. أعد JSON فقط: {"drafts":[{"id":"نفس المعرّف بين الأقواس","reply":"الرد"}]}';
            $max = 1800;
        }
        try {
            $r = ai_chat([['role' => 'system', 'content' => $sys . (function_exists('ai_country_note') ? ai_country_note() : '')], ['role' => 'user', 'content' => implode("\n", $lines)]], $max, 'reviews');
            $txt = (string)$r['text']; if (preg_match('/\{.*\}/s', $txt, $m)) $txt = $m[0];
            $j = json_decode($txt, true); if (!is_array($j)) throw new RuntimeException(t('الرد مكانش بالشكل المتوقع، جرّب تاني.'));
            log_activity('review.' . $action, (string)count($rows));
            echo json_encode(['ok' => true, 'r' => $j], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $ex) { echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE); }
        exit;
    }
    $reviewId = (string)($_POST['review_id'] ?? '');
    $accId = (string)($_POST['account_id'] ?? '');
    $isGmb = !empty($_POST['gmb']);
    try {
        if ($reviewId === '' || $accId === '') {
            throw new ZernioException(t('بيانات التقييم ناقصة.'));
        }
        if ($action === 'reply') {
            $text = trim((string)($_POST['message'] ?? ''));
            if ($text === '') {
                flash('error', t('اكتب الرد الأول.'));
            } elseif (mb_strlen($text) > 4000) {
                flash('error', t('الرد طويل أوي (الحد 4000 حرف).'));
            } else {
                $isGmb ? gmb_reply_review($z, $accId, $reviewId, $text) : gmb_inbox_reply($z, $reviewId, $accId, $text);
                log_activity('review.reply', $reviewId);
                flash('success', t('تم حفظ الرد.'));
            }
        } elseif ($action === 'delete_reply') {
            $isGmb ? gmb_delete_review_reply($z, $accId, $reviewId) : gmb_inbox_delete_reply($z, $reviewId, $accId);
            log_activity('review.reply_delete', $reviewId);
            flash('success', t('تم حذف الرد.'));
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('gmb_reviews', array_filter($keep + ['cursor' => $cursor])));
}

$reviews = [];
$next = null;
$summary = ['total' => 0, 'avg' => null];
$failed = [];
$fxd = getenv('UI_FIXTURES'); $fx = $fxd && is_file($fxd . '/gmb_reviews.json') ? json_decode((string)file_get_contents($fxd . '/gmb_reviews.json'), true) : null;
if ($fx) {
    foreach ($fx['reviews'] ?? [] as $rv) { $rv += ['platform' => 'googlebusiness', 'accountId' => 'acc_gmb_demo', 'hasReply' => !empty($rv['reply']), 'photos' => []]; $reviews[] = $rv; }
    $reviews = array_values(array_filter($reviews, function ($x) use ($stars, $replied) { return (!$stars || $x['rating'] === $stars) && ($replied !== 'yes' || $x['hasReply']) && ($replied !== 'no' || !$x['hasReply']); }));
    $summary = $fx['summary'] ?? $summary;
} elseif ($z && !$apiError) {
    try {
        if ($loc) {
            // فرع جوجل معين: نجيب من gmb-reviews ونفلتر هنا
            $r = gmb_reviews($z, $loc['account'], $loc['location'], $cursor ?: null);
            foreach ($r['reviews'] ?? [] as $rv) {
                $reviews[] = gmb_normalize_review($rv, $loc['account'], $loc['location']);
            }
            $summary = ['total' => (int)($r['totalReviewCount'] ?? count($reviews)), 'avg' => $r['averageRating'] ?? null];
            $next = $r['nextPageToken'] ?? null;
            $reviews = array_values(array_filter($reviews, function ($x) use ($stars, $replied) {
                if ($stars && $x['rating'] !== $stars) return false;
                if ($replied === 'yes' && !$x['hasReply']) return false;
                if ($replied === 'no' && $x['hasReply']) return false;
                return true;
            }));
        } else {
            $f = ['limit' => 50, 'cursor' => $cursor ?: null, 'platform' => $platform ?: null, 'sortBy' => 'date', 'sortOrder' => 'desc'];
            if ($stars) { $f['minRating'] = $stars; $f['maxRating'] = $stars; }
            if ($replied !== '') $f['hasReply'] = $replied === 'yes' ? 'true' : 'false';
            $r = gmb_inbox_reviews($z, $f);
            $reviews = $r['data'] ?? [];
            $summary = ['total' => (int)($r['summary']['totalReviews'] ?? count($reviews)), 'avg' => $r['summary']['averageRating'] ?? null];
            $next = ($r['pagination']['hasMore'] ?? false) ? ($r['pagination']['nextCursor'] ?? null) : null;
            $failed = $r['meta']['failedAccounts'] ?? [];
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

// توزيع النجوم في الصفحة الحالية
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$unreplied = 0;
foreach ($reviews as $rv) {
    $n = (int)($rv['rating'] ?? 0);
    if (isset($dist[$n])) $dist[$n]++;
    if (empty($rv['hasReply'])) $unreplied++;
}

render(t('تقييمات العملاء'), 'gmb_reviews', function () use ($z, $accounts, $options, $loc, $keep, $reviews, $next, $summary, $dist, $unreplied, $failed, $apiError, $stars, $replied, $platform) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); echo '<div class="h-4"></div>'; }
    if (!$accounts && !$apiError) {
        echo '<div class="mb-4">';
        gmb_no_account_box();
        echo '</div>';
    }
    gmb_location_picker($options, $loc, 'gmb_reviews', true, ['stars' => $keep['stars'], 'replied' => $replied]);
    foreach ($failed as $fa): ?>
      <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-3 text-sm mb-4"><?= e(t('تعذر جلب تقييمات حساب: %s', ($fa['accountUsername'] ?? $fa['accountId'] ?? '') . (isset($fa['error']) ? ' — ' . (is_string($fa['error']) ? $fa['error'] : '') : ''))) ?></div>
    <?php endforeach;
    $total = max(1, array_sum($dist));
    ?>
    <div class="grid md:grid-cols-3 gap-4 mb-4">
      <div class="card p-5 flex items-center gap-4">
        <div class="text-4xl font-bold text-ink-900"><?= $summary['avg'] !== null ? e(number_format((float)$summary['avg'], 1)) : '—' ?></div>
        <div>
          <?= gmb_stars((int)round((float)$summary['avg']), 'w-5 h-5') ?>
          <p class="text-xs text-ink-500 mt-1"><?= e(t('%s تقييم إجمالي', (int)$summary['total'])) ?></p>
        </div>
      </div>
      <div class="card p-5 md:col-span-2">
        <p class="text-xs text-ink-500 mb-2"><?= e(t('توزيع النجوم (التقييمات المعروضة)')) ?> · <?= e(t('%s من غير رد', $unreplied)) ?></p>
        <?php foreach ($dist as $n => $c): ?>
          <a href="<?= e(url('gmb_reviews', array_filter(['stars' => $n] + $keep, 'strlen'))) ?>" data-star="<?= $n ?>" class="rv-bar flex items-center gap-2 text-xs mb-1 rounded hover:bg-ink-50 px-1 -mx-1">
            <span class="w-4 text-end"><?= $n ?></span>
            <svg class="w-3 h-3 text-amber-500" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8L10 14.9l-5.3 2.7 1-5.8L1.5 7.7l5.9-.9z"/></svg>
            <span class="flex-1 h-2 rounded-full bg-ink-100 overflow-hidden"><span class="block h-full bg-amber-500 rounded-full" style="width: <?= (int)round($c * 100 / $total) ?>%"></span></span>
            <span class="w-8 text-end text-ink-500"><?= $c ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <form method="get" class="flex flex-wrap items-end gap-3 mb-4 text-sm">
      <input type="hidden" name="p" value="gmb_reviews">
      <?php if ($loc): ?><input type="hidden" name="loc" value="<?= e($loc['key']) ?>"><?php endif; ?>
      <label class="block"><span class="label"><?= e(t('النجوم')) ?></span>
        <select name="stars" class="input !w-auto">
          <option value=""><?= e(t('الكل')) ?></option>
          <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= $stars === $i ? 'selected' : '' ?>><?= e(t('%s نجوم', $i)) ?></option><?php endfor; ?>
        </select>
      </label>
      <label class="block"><span class="label"><?= e(t('الرد')) ?></span>
        <select name="replied" class="input !w-auto">
          <option value=""><?= e(t('الكل')) ?></option>
          <option value="no" <?= $replied === 'no' ? 'selected' : '' ?>><?= e(t('من غير رد')) ?></option>
          <option value="yes" <?= $replied === 'yes' ? 'selected' : '' ?>><?= e(t('اترد عليها')) ?></option>
        </select>
      </label>
      <?php if (!$loc): ?>
      <label class="block"><span class="label"><?= e(t('المنصة')) ?></span>
        <select name="platform" class="input !w-auto">
          <option value=""><?= e(t('الكل')) ?></option>
          <option value="googlebusiness" <?= $platform === 'googlebusiness' ? 'selected' : '' ?>><?= e(platform_label('googlebusiness')) ?></option>
          <option value="facebook" <?= $platform === 'facebook' ? 'selected' : '' ?>><?= e(platform_label('facebook')) ?></option>
        </select>
      </label>
      <?php endif; ?>
      <button class="btn-primary"><?= e(t('فلترة')) ?></button>
      <a href="<?= e(url('gmb_reviews', $loc ? ['loc' => $loc['key']] : [])) ?>" class="btn-ghost"><?= e(t('مسح الفلاتر')) ?></a>
    </form>

    <div id="rv-tools"></div>
    <div class="space-y-3" id="rv-list">
      <?php if (!$reviews): ?><div class="card p-8 text-center text-sm text-ink-500"><?= e(t('مفيش تقييمات.')) ?></div><?php endif; ?>
      <?php foreach ($reviews as $rv):
        $isFb = ($rv['platform'] ?? '') === 'facebook';
        $rid = 'rv' . substr(md5((string)$rv['id']), 0, 10);
      ?>
        <div class="rv-card card p-4 transition" data-id="<?= e((string)$rv['id']) ?>" data-form="<?= e($rid) ?>" data-rating="<?= (int)($rv['rating'] ?? 0) ?>" data-replied="<?= empty($rv['hasReply']) ? 0 : 1 ?>" data-photos="<?= empty($rv['photos']) ? 0 : 1 ?>"
          data-name="<?= e(($rv['reviewer']['name'] ?? '') ?: t('عميل')) ?>" data-text="<?= e(mb_substr((string)($rv['text'] ?? ''), 0, 600)) ?>" data-q="<?= e(mb_strtolower(($rv['reviewer']['name'] ?? '') . ' ' . ($rv['text'] ?? '') . ' ' . ($rv['reply']['text'] ?? ''))) ?>" data-at="<?= e((string)($rv['created'] ?? '')) ?>">
          <div class="flex gap-3">
            <?= avatar($rv['reviewer']['profileImage'] ?? null, $rv['reviewer']['name'] ?? '', 'w-10 h-10') ?>
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold text-sm text-ink-900"><?= e(($rv['reviewer']['name'] ?? '') ?: t('عميل')) ?></span>
                <span class="badge"><?= e(platform_label((string)($rv['platform'] ?? ''))) ?></span>
                <?php if ($isFb && !empty($rv['recommendationType'])): ?>
                  <span class="badge <?= $rv['recommendationType'] === 'positive' ? 'badge-green' : 'bg-red-100 text-red-700' ?>"><?= e($rv['recommendationType'] === 'positive' ? t('بيرشح') : t('مش بيرشح')) ?></span>
                <?php endif; ?>
                <?php if (!empty($rv['rating'])): ?><?= gmb_stars((int)$rv['rating']) ?><?php endif; ?>
                <?php if (empty($rv['hasReply'])): ?><span class="badge bg-amber-100 text-amber-800"><?= e(t('محتاج رد')) ?></span><?php endif; ?>
              </div>
              <p class="text-[11px] text-ink-500 mt-0.5">
                <?= e(fmt_date($rv['created'] ?? null, 'd/m/Y h:i A')) ?>
                <?php if (!empty($rv['locationName'])): ?> · <?= e($rv['locationName']) ?><?php endif; ?>
                <?php if (!empty($rv['reviewUrl'])): ?> · <a class="link" href="<?= e($rv['reviewUrl']) ?>" target="_blank" rel="noopener"><?= e(t('فتح ↗')) ?></a><?php endif; ?>
              </p>
              <?php if (($rv['text'] ?? '') !== ''): ?>
                <p class="text-sm mt-2 whitespace-pre-line break-words text-ink-800"><?= e($rv['text']) ?></p>
              <?php else: ?>
                <p class="text-sm mt-2 text-ink-400"><?= e(t('(تقييم بدون تعليق)')) ?></p>
              <?php endif; ?>
              <?php if (!empty($rv['photos'])): ?>
                <div class="flex flex-wrap gap-2 mt-2">
                  <?php foreach (array_slice($rv['photos'], 0, 6) as $ph): ?>
                    <a href="<?= e($ph['url'] ?? '') ?>" target="_blank" rel="noopener"><img src="<?= e($ph['url'] ?? '') ?>" class="w-16 h-16 rounded-lg object-cover bg-ink-100" loading="lazy" referrerpolicy="no-referrer" alt=""></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($rv['reply'])): ?>
                <div class="mt-3 border-s-4 border-brand-300 bg-brand-50 rounded-e-lg p-3">
                  <p class="text-xs font-bold text-brand-700"><?= e(t('ردك')) ?> <span class="font-normal text-ink-500">· <?= e(fmt_date($rv['reply']['created'] ?? null, 'd/m/Y')) ?></span></p>
                  <p class="text-sm whitespace-pre-line break-words mt-1"><?= e($rv['reply']['text'] ?? '') ?></p>
                  <div class="flex gap-3 mt-2 text-xs">
                    <button type="button" class="link" data-toggle="<?= e($rid) ?>"><?= e(t('تعديل الرد')) ?></button>
                    <form method="post" class="inline" onsubmit="return confirm('<?= tjs('حذف الرد على التقييم؟') ?>')">
                      <?= csrf_field() ?><input type="hidden" name="action" value="delete_reply">
                      <input type="hidden" name="review_id" value="<?= e($rv['id']) ?>"><input type="hidden" name="account_id" value="<?= e($rv['accountId'] ?? '') ?>">
                      <?php if (!empty($rv['_gmb'])): ?><input type="hidden" name="gmb" value="1"><?php endif; ?>
                      <button class="text-red-600"><?= e(t('حذف الرد')) ?></button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>

              <form method="post" id="<?= e($rid) ?>" class="mt-3 <?= !empty($rv['reply']) ? 'hidden' : '' ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="reply">
                <input type="hidden" name="review_id" value="<?= e($rv['id']) ?>"><input type="hidden" name="account_id" value="<?= e($rv['accountId'] ?? '') ?>">
                <?php if (!empty($rv['_gmb'])): ?><input type="hidden" name="gmb" value="1"><?php endif; ?>
                <div class="flex gap-2 items-end">
                  <textarea data-ai="reply_review,polish_reply" name="message" rows="2" maxlength="4000" required class="input flex-1 !mt-0" placeholder="<?= e(t('اكتب ردك على التقييم…')) ?>"><?= e($rv['reply']['text'] ?? '') ?></textarea>
                  <button class="btn-primary"><?= e(!empty($rv['reply']) ? t('حفظ') : t('رد')) ?></button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($next): ?>
      <div class="text-center mt-4"><a class="btn-ghost" href="<?= e(url('gmb_reviews', array_filter(['cursor' => $next] + $keep, 'strlen'))) ?>"><?= e(t('تقييمات أقدم')) ?></a></div>
    <?php endif; ?>
    <script>window.RVX = <?= json_encode(['csrf' => csrf_token(), 'url' => url('gmb_reviews'), 'ws' => (int)ws('id'), 'canAi' => can('ai.use') && function_exists('ai_connection') && (bool)ai_connection(), 'country' => ws_country(), 's' => [
      'search' => t('ابحث في التقييمات...'), 'all' => t('الكل'), 'unreplied' => t('محتاج رد'), 'negative' => t('سلبية (1-2★)'), 'positive' => t('إيجابية (4-5★)'), 'photos' => t('فيها صور'), 'empty' => t('مفيش تقييمات مطابقة'),
      'replyRate' => t('نسبة الرد'), 'needReply' => t('محتاج رد'), 'oldest' => t('أقدم تقييم من غير رد: %s'), 'themes' => t('حلّل التقييمات بالذكاء الاصطناعي'), 'drafts' => t('اكتب مسودات ردود لكل اللي من غير رد'), 'working' => t('بيحلل...'), 'writing' => t('بيكتب المسودات...'),
      'praise' => t('العملاء بيحبوا'), 'complaints' => t('العملاء بيشتكوا من'), 'actions' => t('إجراءات مقترحة'), 'times' => t('%s تقييم'), 'draftsDone' => t('اتكتبت %s مسودة. راجع كل رد ودوس "رد" عليه — مفيش حاجة اتبعتت.'), 'aiErr' => t('ما قدرناش نكمل'),
      'tpl' => t('ردود جاهزة'), 'tplSave' => t('احفظ النص ده كرد جاهز'), 'tplSaved' => t('اتحفظ الرد الجاهز'), 'tplNone' => t('مفيش ردود جاهزة لسه. اكتب رد واحفظه من هنا.'), 'tplDel' => t('امسح'), 'tplName' => t('{name} = اسم العميل'), 'nameTok' => t('(الاسم)'),
      'nextUn' => t('التالي من غير رد ↓'), 'keys' => t('j/k تنقل · r رد · n التالي من غير رد · / بحث'), 'draft' => t('مسودة ✦'),
      'defaults' => [5 => t('شكرًا يا {name} على كلامك الجميل! سعداء إن التجربة عجبتك، ومستنيينك تاني قريب.'), 3 => t('شكرًا يا {name} على رأيك. يهمنا نعرف إيه اللي كان ممكن يخلي تجربتك أحسن، ابعتلنا رسالة خاصة.'), 1 => t('آسفين جدًا يا {name} على التجربة دي. ده مش المستوى اللي بنقدمه، ياريت تتواصل معانا في رسالة خاصة عشان نحل المشكلة.')]]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ui-gmb_reviews.js?v=<?= e(APP_VERSION) ?>"></script>
    <script>
      document.querySelectorAll('[data-toggle]').forEach(function (b) {
        b.addEventListener('click', function () { var f = document.getElementById(b.dataset.toggle); f.classList.toggle('hidden'); f.querySelector('textarea').focus(); });
      });
    </script>
    <?php
});
