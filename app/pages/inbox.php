<?php
$err = null;
$z = zernio_or_null($err);
$convId = (string)($_GET['c'] ?? '');
$accId = (string)($_GET['a'] ?? '');
$cursor = (string)($_GET['cursor'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && $convId && $accId) {
    $text = trim((string)($_POST['message'] ?? ''));
    $attachUrl = null;
    $attachType = null;
    try {
        if (!empty($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['attachment']['tmp_name']);
            $name = preg_replace('/[^\w.\-]+/u', '_', basename($_FILES['attachment']['name']));
            $attachUrl = $z->uploadFile($_FILES['attachment']['tmp_name'], $name, $mime);
            $attachType = strpos($mime, 'image/') === 0 ? 'image' : (strpos($mime, 'video/') === 0 ? 'video' : (strpos($mime, 'audio/') === 0 ? 'audio' : 'file'));
        }
        if ($text === '' && !$attachUrl) {
            flash('error', 'اكتب رسالة الأول.');
        } else {
            $z->sendMessage($convId, $accId, $text, $attachUrl, $attachType);
            log_activity('inbox.reply', $convId);
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect(url('inbox', ['c' => $convId, 'a' => $accId]) . '#bottom');
}

$convs = [];
$next = null;
$messages = [];
$current = null;
$apiError = null;
if ($z) {
    try {
        $r = $z->conversations(['limit' => 30, 'cursor' => $cursor ?: null, 'profileId' => ws('profile_id') ?: null]);
        $convs = $r['data'] ?? [];
        $next = ($r['pagination']['hasMore'] ?? false) ? ($r['pagination']['nextCursor'] ?? null) : null;
        foreach ($convs as $c) {
            if ($c['id'] === $convId) $current = $c;
        }
        if ($convId && $accId) {
            $m = $z->messages($convId, $accId);
            $messages = $m['messages'] ?? [];
            if (($m['sortOrderApplied'] ?? 'asc') === 'desc') $messages = array_reverse($messages);
            if ($current && !empty($current['unreadCount'])) $z->markRead($convId, $accId);
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render('الرسائل', 'inbox', function () use ($z, $convs, $next, $messages, $current, $convId, $accId, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); }
    ?>
    <div class="grid lg:grid-cols-3 gap-4 h-[calc(100vh-11rem)] min-h-[500px]">
      <!-- قائمة المحادثات -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-y-auto min-h-0 <?= $convId ? 'hidden lg:block' : '' ?>">
        <?php if (!$convs): ?><p class="p-5 text-sm text-slate-500">مفيش رسائل.</p><?php endif; ?>
        <?php foreach ($convs as $c): $on = $c['id'] === $convId; ?>
          <a href="<?= e(url('inbox', ['c' => $c['id'], 'a' => $c['accountId']])) ?>" class="flex gap-3 p-4 border-b border-slate-100 hover:bg-slate-50 <?= $on ? 'bg-indigo-50' : '' ?>">
            <?= avatar($c['participantPicture'] ?? null, $c['participantName'] ?? '') ?>
            <div class="flex-1 min-w-0">
              <div class="flex justify-between gap-2">
                <span class="font-semibold text-sm truncate"><?= e($c['participantName'] ?? 'بدون اسم') ?></span>
                <?php if (!empty($c['unreadCount'])): ?><span class="bg-rose-600 text-white text-[11px] rounded-full px-2 py-0.5"><?= (int)$c['unreadCount'] ?></span><?php endif; ?>
              </div>
              <p class="text-xs text-slate-500 truncate"><?= e($c['lastMessage'] ?? '') ?></p>
              <p class="text-[11px] text-slate-400 mt-0.5"><?= e(platform_label($c['platform'] ?? '')) ?> · <?= e(trim($c['accountUsername'] ?? '')) ?> · <?= e(fmt_date($c['updatedTime'] ?? null, 'd/m h:i A')) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if ($next): ?><a href="<?= e(url('inbox', ['cursor' => $next])) ?>" class="block text-center p-3 text-sm text-indigo-700 font-semibold">أقدم ←</a><?php endif; ?>
      </div>

      <!-- المحادثة -->
      <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl flex flex-col min-h-0 overflow-hidden <?= $convId ? '' : 'hidden lg:flex' ?>">
        <?php if (!$convId): ?>
          <div class="flex-1 flex items-center justify-center text-slate-400 text-sm">اختار محادثة من القائمة</div>
        <?php else: ?>
          <div class="p-4 border-b border-slate-200 flex items-center gap-3">
            <a href="<?= e(url('inbox')) ?>" class="lg:hidden text-indigo-700 text-sm">→ رجوع</a>
            <span class="font-bold"><?= e($current['participantName'] ?? 'محادثة') ?></span>
            <?php if ($current): ?><span class="text-xs text-slate-500"><?= e(platform_label($current['platform'] ?? '')) ?></span><?php endif; ?>
          </div>
          <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3 bg-slate-50" id="thread">
            <?php foreach ($messages as $m): $out = ($m['direction'] ?? '') === 'outgoing'; ?>
              <div class="flex <?= $out ? 'justify-start' : 'justify-end' ?>">
                <div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm <?= $out ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-200' ?>">
                  <?php if (!empty($m['isDeleted'])): ?><em class="opacity-70">رسالة محذوفة</em>
                  <?php else: ?>
                    <?php if (($m['message'] ?? '') !== ''): ?><p class="whitespace-pre-line break-words"><?= e($m['message']) ?></p><?php endif; ?>
                    <?php foreach ($m['attachments'] ?? [] as $att): $u = $att['url'] ?? $att['payload']['url'] ?? null; if (!$u) continue;
                        $t = $att['type'] ?? ''; ?>
                      <?php if ($t === 'image' || $t === 'sticker'): ?><img src="<?= e($u) ?>" class="mt-2 rounded-lg max-h-60" referrerpolicy="no-referrer" loading="lazy" alt="">
                      <?php else: ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" class="underline block mt-1">مرفق (<?= e($t ?: 'ملف') ?>)</a><?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <div class="text-[10px] mt-1 <?= $out ? 'text-indigo-200' : 'text-slate-400' ?>"><?= e(fmt_date($m['createdAt'] ?? null, 'd/m h:i A')) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
            <div id="bottom"></div>
          </div>
          <form method="post" enctype="multipart/form-data" class="p-3 border-t border-slate-200 flex gap-2 items-end">
            <?= csrf_field() ?>
            <label class="p-2.5 rounded-lg hover:bg-slate-100 cursor-pointer" title="إرفاق ملف">
              <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15.2 7l-6.6 6.6a2 2 0 102.8 2.8l6.4-6.6a4 4 0 00-5.6-5.6l-6.4 6.5a6 6 0 108.5 8.5L20.5 13"/></svg>
              <input type="file" name="attachment" class="hidden" onchange="this.parentNode.classList.add('bg-indigo-100')">
            </label>
            <textarea name="message" rows="2" class="flex-1 border border-slate-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="اكتب ردك..." data-enter-submit></textarea>
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-lg">إرسال</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php
});
