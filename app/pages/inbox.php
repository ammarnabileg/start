<?php
$err = null;
$z = zernio_or_null($err);

$folder = (string)($_GET['folder'] ?? 'inbox');
if (!in_array($folder, ['inbox', 'requests', 'comments'], true)) $folder = 'inbox';
$status = (string)($_GET['status'] ?? 'active');
if (!in_array($status, ['active', 'archived'], true)) $status = 'active';
$platform = (string)($_GET['platform'] ?? '');
$acctFilter = (string)($_GET['acct'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$convId = (string)($_GET['c'] ?? '');
$accId = (string)($_GET['a'] ?? '');
$postId = (string)($_GET['post'] ?? '');
$cursor = (string)($_GET['cursor'] ?? '');

$msgPlatforms = ['facebook', 'instagram', 'twitter', 'bluesky', 'reddit', 'telegram', 'whatsapp', 'imessage', 'sms'];
if ($platform !== '' && !in_array($platform, $msgPlatforms, true)) $platform = '';

// Filters kept on every link
$base = array_filter(['folder' => $folder !== 'inbox' ? $folder : null, 'status' => $status !== 'active' ? $status : null, 'platform' => $platform, 'acct' => $acctFilter, 'q' => $q], function ($v) { return $v !== null && $v !== ''; });

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? 'send');
    $msgId = (string)($_POST['message_id'] ?? '');
    $back = url('inbox', $base + ['c' => $convId, 'a' => $accId]);
    try {
        if ($action === 'private_reply') {
            $commentId = (string)($_POST['comment_id'] ?? '');
            $text = trim((string)($_POST['message'] ?? ''));
            $back = url('inbox', ['folder' => 'comments', 'post' => $postId, 'a' => $accId]);
            if ($postId === '' || $commentId === '' || $accId === '') {
                flash('error', t('بيانات التعليق ناقصة.'));
            } elseif ($text === '') {
                flash('error', t('اكتب الرسالة الأول.'));
            } else {
                ip_private_reply($z, $postId, $commentId, $accId, $text);
                log_activity('inbox.private_reply', $commentId);
                flash('success', t('اتبعتت رسالة خاصة لصاحب التعليق.'));
            }
            redirect($back);
        }
        if ($accId === '') {
            flash('error', t('اختار حساب الأول.'));
            redirect($back);
        }
        if ($action === 'label_create') {
            $name = trim((string)($_POST['label_name'] ?? ''));
            if ($name === '') {
                flash('error', t('اكتب اسم التصنيف.'));
            } else {
                ip_create_label($z, $accId, mb_substr($name, 0, 100));
                log_activity('inbox.label_create', $name);
                flash('success', t('تم إنشاء التصنيف.'));
            }
            redirect($back);
        }
        if ($action === 'label_delete') {
            $labelId = (string)($_POST['label_id'] ?? '');
            if (!can('inbox.manage')) {
                flash('error', t('الحذف لصاحب الشركة بس.'));
            } elseif (preg_match('/^\d+$/', $labelId)) {
                ip_delete_label($z, $accId, $labelId);
                log_activity('inbox.label_delete', $labelId);
                flash('success', t('تم حذف التصنيف.'));
            }
            redirect($back);
        }
        if ($convId === '') redirect($back);

        switch ($action) {
            case 'send':
                $text = trim((string)($_POST['message'] ?? ''));
                $attachUrl = null;
                $attachType = null;
                if (!empty($_FILES['attachment']['tmp_name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['attachment']['tmp_name']);
                    $name = preg_replace('/[^\w.\-]+/u', '_', basename($_FILES['attachment']['name']));
                    $attachUrl = $z->uploadFile($_FILES['attachment']['tmp_name'], $name, $mime);
                    $attachType = strpos($mime, 'image/') === 0 ? 'image' : (strpos($mime, 'video/') === 0 ? 'video' : (strpos($mime, 'audio/') === 0 ? 'audio' : 'file'));
                }
                if ($text === '' && !$attachUrl) {
                    flash('error', t('اكتب رسالة الأول.'));
                } else {
                    $z->sendMessage($convId, $accId, $text, $attachUrl, $attachType);
                    log_activity('inbox.reply', $convId);
                }
                $back .= '#bottom';
                break;
            case 'accept':
                $text = trim((string)($_POST['message'] ?? ''));
                if ($text === '') {
                    flash('error', t('لازم تكتب رد عشان تقبل الطلب.'));
                } else {
                    ip_accept_request($z, $convId, $accId, $text);
                    log_activity('inbox.accept', $convId);
                    flash('success', t('تم قبول الطلب والمحادثة اتنقلت للوارد.'));
                    $back = url('inbox', ['c' => $convId, 'a' => $accId]);
                }
                break;
            case 'archive':
            case 'unarchive':
                ip_set_status($z, $convId, $accId, $action === 'archive' ? 'archived' : 'active');
                log_activity('inbox.' . $action, $convId);
                flash('success', $action === 'archive' ? t('المحادثة اتأرشفت.') : t('المحادثة رجعت للنشطة.'));
                $back = url('inbox', $base);
                break;
            case 'delete_conv':
                if (!can('inbox.manage')) {
                    flash('error', t('الحذف لصاحب الشركة بس.'));
                } else {
                    ip_delete_conversation($z, $convId, $accId);
                    log_activity('inbox.delete', $convId);
                    flash('success', t('تم حذف المحادثة.'));
                    $back = url('inbox', $base);
                }
                break;
            case 'react':
                $emoji = (string)($_POST['emoji'] ?? '');
                if ($msgId !== '' && in_array($emoji, ['👍', '❤️', '😂', '😮', '😢', '🙏'], true)) {
                    try {
                        ip_react($z, $convId, $msgId, $accId, $emoji);
                    } catch (ZernioException $rx) {
                        // التفاعلات مش متاحة على كل الأرقام (زي الرقم التجريبي) أو بعد 24 ساعة من آخر رسالة على فيسبوك وانستجرام
                        flash('error', $rx->status === 404
                            ? t('التفاعل مش متاح على المحادثة دي. لو ده الرقم التجريبي، التفاعلات بتشتغل بس على رقمك الحقيقي.')
                            : t('ماقدرناش نضيف التفاعل: %s', $rx->getMessage()));
                    }
                }
                break;
            case 'unreact':
                if ($msgId !== '') ip_unreact($z, $convId, $msgId, $accId);
                break;
            case 'edit_msg':
                $text = trim((string)($_POST['text'] ?? ''));
                if ($msgId !== '' && $text !== '') {
                    ip_edit_message($z, $convId, $msgId, $accId, $text);
                    log_activity('inbox.edit_message', $msgId);
                    flash('success', t('تم تعديل الرسالة.'));
                }
                break;
            case 'delete_msg':
                if ($msgId !== '') {
                    ip_delete_message($z, $convId, $msgId, $accId);
                    log_activity('inbox.delete_message', $msgId);
                    flash('success', t('تم حذف الرسالة.'));
                }
                break;
            case 'label_add':
            case 'label_remove':
                $labelId = (string)($_POST['label_id'] ?? '');
                if (preg_match('/^\d+$/', $labelId)) {
                    if ($action === 'label_add') ip_add_conv_label($z, $convId, $accId, $labelId);
                    else ip_remove_conv_label($z, $convId, $accId, $labelId);
                    log_activity('inbox.' . $action, $convId);
                }
                break;
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect($back);
}

$accounts = [];
$convs = [];
$next = null;
$messages = [];
$current = null;
$apiError = null;
$pageLabels = null;   // null = not loaded / not supported
$convLabels = [];
$labelError = null;
$posts = [];
$comments = [];
$currentPost = null;
if ($z) {
    try {
        $accounts = ip_accounts($z);
        if ($folder === 'comments') {
            $r = $z->commentedPosts(['limit' => 30, 'cursor' => $cursor ?: null, 'profileId' => zernio_profile(), 'accountId' => $acctFilter ?: null]);
            foreach ($r['data'] ?? [] as $p) {
                if (!in_array($p['platform'] ?? '', ['facebook', 'instagram'], true)) continue;
                $posts[] = $p;
                if ($p['id'] === $postId) $currentPost = $p;
            }
            $next = ($r['pagination']['hasMore'] ?? false) ? ($r['pagination']['nextCursor'] ?? null) : null;
            if ($postId && $accId) {
                $comments = $z->comments($postId, $accId)['comments'] ?? [];
            }
        } else {
            if ($q !== '' && mb_strlen($q) >= 2) {
                $r = ip_search_conversations($z, ['query' => mb_substr($q, 0, 200), 'profileId' => zernio_profile(), 'platform' => $platform ?: null, 'accountId' => $acctFilter ?: null, 'limit' => 30, 'cursor' => $cursor ?: null]);
                foreach ($r['data'] ?? [] as $row) {
                    $c = $row['conversation'] ?? [];
                    if (empty($c['id'])) continue;
                    $c['updatedTime'] = $c['lastMessageAt'] ?? null;
                    $c['matchCount'] = $row['matchCount'] ?? 0;
                    $c['matchSnippet'] = $row['matches'][0]['message'] ?? $row['matches'][0]['text'] ?? null;
                    $convs[] = $c;
                }
            } else {
                $f = ['limit' => 30, 'cursor' => $cursor ?: null, 'profileId' => zernio_profile(), 'folder' => $folder, 'platform' => $platform ?: null, 'accountId' => $acctFilter ?: null];
                if ($folder === 'inbox') $f['status'] = $status;
                $r = ip_conversations($z, $f);
                $convs = $r['data'] ?? [];
            }
            $next = ($r['pagination']['hasMore'] ?? false) ? ($r['pagination']['nextCursor'] ?? null) : null;
            foreach ($convs as $c) {
                if ($c['id'] === $convId) $current = $c;
            }
            if ($convId && $accId) {
                if (!$current) {
                    try { $current = ip_conversation($z, $convId, $accId); } catch (ZernioException $ex) { $current = null; }
                }
                $m = $z->messages($convId, $accId);
                $messages = $m['messages'] ?? [];
                if (($m['sortOrderApplied'] ?? 'asc') === 'desc') $messages = array_reverse($messages);
                if ($current && !empty($current['unreadCount'])) $z->markRead($convId, $accId);
                if (($current['platform'] ?? '') === 'facebook') {
                    try {
                        $pageLabels = ip_labels($z, $accId);
                        $convLabels = ip_conversation_labels($z, $convId, $accId);
                    } catch (ZernioException $ex) {
                        $pageLabels = null;
                        $labelError = $ex->getMessage();
                    }
                }
            }
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

function ip_private_reply_block(array $c, string $postId, string $accId, bool $isReply = false): void
{
    $from = $c['from'] ?? [];
    $mine = !empty($from['isOwner']);
    ?>
    <div class="flex gap-3 <?= $isReply ? 'ms-12 mt-3' : '' ?>">
      <?= avatar($from['picture'] ?? null, $from['name'] ?? $from['username'] ?? '', 'w-9 h-9') ?>
      <div class="flex-1 min-w-0">
        <div class="rounded-2xl px-4 py-2 inline-block max-w-full <?= $mine ? 'bg-brand-50' : 'bg-ink-100' ?>">
          <div class="text-xs font-bold"><?= e($from['name'] ?? $from['username'] ?? t('مستخدم')) ?><?= $mine ? ' ' . e(t('(أنت)')) : '' ?></div>
          <p class="text-sm whitespace-pre-line break-words"><?= e($c['message'] ?? '') ?></p>
        </div>
        <div class="text-xs text-ink-500 mt-1 ps-2"><?= e(fmt_date($c['createdTime'] ?? null, 'd/m h:i A')) ?></div>
        <?php if (!$mine && !empty($c['id'])): ?>
          <details class="mt-1 ps-2">
            <summary class="text-xs font-semibold text-brand-700 cursor-pointer"><?= e(t('رد برسالة خاصة')) ?></summary>
            <form method="post" action="<?= e(url('inbox', ['folder' => 'comments', 'post' => $postId, 'a' => $accId])) ?>" class="flex gap-2 mt-2 items-end">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="private_reply">
              <input type="hidden" name="comment_id" value="<?= e($c['id']) ?>">
              <textarea data-ai="comment_to_dm,polish_reply" name="message" rows="2" required class="input flex-1 text-sm" placeholder="<?= e(t('الرسالة هتوصل له في الخاص (مرة واحدة لكل تعليق، خلال 7 أيام)')) ?>"></textarea>
              <button class="btn-primary"><?= e(t('إرسال')) ?></button>
            </form>
          </details>
        <?php endif; ?>
        <?php foreach ($c['replies'] ?? [] as $r) ip_private_reply_block($r, $postId, $accId, true); ?>
      </div>
    </div>
    <?php
}

render(t('الرسائل'), 'inbox', function () use ($z, $folder, $status, $platform, $acctFilter, $q, $base, $accounts, $convs, $next, $messages, $current, $convId, $accId, $apiError, $pageLabels, $convLabels, $labelError, $posts, $comments, $currentPost, $postId, $msgPlatforms) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); }
    $folders = ['inbox' => t('الوارد'), 'requests' => t('طلبات الرسائل'), 'comments' => t('رد خاص على تعليق')];
    $plat = $current['platform'] ?? '';
    // الرقم التجريبي المشترك لواتساب: الإرسال بس، من غير تفاعلات
    $isSandboxConv = false;
    if ($plat === 'whatsapp' && $accId !== '') {
        try {
            foreach ($z->request('GET', '/v1/accounts', ['platform' => 'whatsapp', 'includeSandbox' => 'true'])['accounts'] ?? [] as $wa) {
                if (($wa['_id'] ?? '') === $accId && !empty($wa['metadata']['isSandbox'])) $isSandboxConv = true;
            }
        } catch (Throwable $t) {
        }
    }
    ?>
    <div data-live-root<?= $apiError ? ' data-live-error' : '' ?>>
    <!-- الفلاتر -->
    <div class="flex flex-wrap items-center gap-2 mb-3">
      <div class="flex rounded-lg border border-ink-200 overflow-hidden text-sm">
        <?php foreach ($folders as $k => $lbl): ?>
          <a href="<?= e(url('inbox', $k === 'inbox' ? [] : ['folder' => $k])) ?>" class="px-3 py-1.5 <?= $folder === $k ? 'bg-brand-600 text-white font-semibold' : 'bg-white hover:bg-ink-50' ?>"><?= e($lbl) ?></a>
        <?php endforeach; ?>
      </div>
      <form method="get" class="flex flex-wrap items-center gap-2 flex-1">
        <input type="hidden" name="p" value="inbox">
        <?php if ($folder !== 'inbox'): ?><input type="hidden" name="folder" value="<?= e($folder) ?>"><?php endif; ?>
        <?php if ($folder === 'inbox'): ?>
          <select name="status" class="input w-auto text-sm py-1.5" onchange="this.form.submit()">
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= e(t('النشطة')) ?></option>
            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>><?= e(t('المؤرشفة')) ?></option>
          </select>
        <?php endif; ?>
        <?php if ($folder !== 'comments'): ?>
          <select name="platform" class="input w-auto text-sm py-1.5" onchange="this.form.submit()">
            <option value=""><?= e(t('كل المنصات')) ?></option>
            <?php foreach ($msgPlatforms as $pl): ?><option value="<?= e($pl) ?>" <?= $platform === $pl ? 'selected' : '' ?>><?= e(platform_label($pl)) ?></option><?php endforeach; ?>
          </select>
        <?php endif; ?>
        <select name="acct" class="input w-auto text-sm py-1.5 max-w-[14rem]" onchange="this.form.submit()">
          <option value=""><?= e(t('كل الحسابات')) ?></option>
          <?php foreach ($accounts as $a): ?><option value="<?= e($a['_id']) ?>" <?= $acctFilter === $a['_id'] ? 'selected' : '' ?>><?= e(ip_account_label($a)) ?></option><?php endforeach; ?>
        </select>
        <?php if ($folder === 'inbox'): ?>
          <input type="search" name="q" value="<?= e($q) ?>" minlength="2" class="input flex-1 min-w-[10rem] text-sm py-1.5" placeholder="<?= e(t('دور في الرسائل والأسماء والأرقام...')) ?>">
          <button class="btn-ghost text-sm"><?= e(t('بحث')) ?></button>
          <?php if ($q !== ''): ?><a href="<?= e(url('inbox', array_diff_key($base, ['q' => 1]))) ?>" class="link text-sm"><?= e(t('مسح البحث')) ?></a><?php endif; ?>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($folder !== 'comments'):
        // شريط القنوات: كل منصة بعدد المحادثات وغير المقروء
        $byPlat = [];
        foreach ($convs as $cv) {
            $pp = (string)($cv['platform'] ?? '');
            if ($pp === '') continue;
            $byPlat[$pp]['n'] = ($byPlat[$pp]['n'] ?? 0) + 1;
            $byPlat[$pp]['u'] = ($byPlat[$pp]['u'] ?? 0) + (int)($cv['unreadCount'] ?? 0);
        }
        if (count($byPlat) > 1 || $platform !== ''): ?>
      <div class="flex gap-2 overflow-x-auto pb-1 mb-3" style="scrollbar-width:none">
        <a href="<?= e(url('inbox', array_diff_key($base, ['platform' => 1]))) ?>" class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm whitespace-nowrap <?= $platform === '' ? 'border-brand-600 bg-brand-50 text-brand-700 font-medium' : 'border-ink-200 bg-white text-ink-600 hover:bg-ink-50' ?>"><?= e(t('كل القنوات')) ?></a>
        <?php foreach ($byPlat as $pp => $info): ?>
          <a href="<?= e(url('inbox', ['platform' => $pp] + $base)) ?>" class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm whitespace-nowrap <?= $platform === $pp ? 'border-brand-600 bg-brand-50 text-brand-700 font-medium' : 'border-ink-200 bg-white text-ink-600 hover:bg-ink-50' ?>">
            <?= platform_icon($pp, 'w-4 h-4') ?><?= e(platform_label($pp)) ?>
            <?php if ($info['u']): ?><span class="bg-red-600 text-white text-[10px] rounded-full px-1.5 min-w-[18px] text-center"><?= (int)$info['u'] ?></span><?php else: ?><span class="text-xs text-ink-400"><?= (int)$info['n'] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; endif; ?>

    <?php if ($folder === 'requests'): ?>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('طلبات الرسائل: ناس بعتولك لأول مرة على صفحة فيسبوك. أول رد منك بيقبل الطلب وينقل المحادثة للوارد.')) ?></p>
    <?php elseif ($folder === 'comments'): ?>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('ابعت رسالة خاصة لصاحب تعليق على فيسبوك أو إنستجرام. مسموح برسالة واحدة لكل تعليق خلال 7 أيام من كتابته.')) ?></p>
    <?php endif; ?>

    <?php if ($folder === 'comments'): ?>
    <div class="grid lg:grid-cols-3 gap-4 h-[calc(100vh-19rem)] min-h-[500px]">
      <div data-live="list" class="card overflow-y-auto min-h-0 <?= $postId ? 'hidden lg:block' : '' ?>">
        <?php if (!$posts): ?><p class="p-5 text-sm text-ink-500"><?= e(t('مفيش منشورات عليها تعليقات.')) ?></p><?php endif; ?>
        <?php foreach ($posts as $p): $on = $p['id'] === $postId; ?>
          <a href="<?= e(url('inbox', ['folder' => 'comments', 'acct' => $acctFilter, 'post' => $p['id'], 'a' => $p['accountId']])) ?>" class="flex gap-3 p-4 border-b border-ink-100 hover:bg-ink-50 <?= $on ? 'bg-brand-50' : '' ?>">
            <?php if (!empty($p['picture'])): ?><img src="<?= e($p['picture']) ?>" class="w-12 h-12 rounded-lg object-cover bg-ink-100 shrink-0" loading="lazy" referrerpolicy="no-referrer" alt=""><?php endif; ?>
            <div class="flex-1 min-w-0">
              <p class="text-sm line-clamp-2"><?= e(mb_substr((string)($p['content'] ?? ''), 0, 120)) ?></p>
              <p class="text-[11px] text-ink-500 mt-1"><?= e(platform_label($p['platform'] ?? '')) ?> · <?= e(t('%s تعليق', (int)($p['commentCount'] ?? 0))) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if ($next): ?><a href="<?= e(url('inbox', ['folder' => 'comments', 'acct' => $acctFilter, 'cursor' => $next])) ?>" class="block text-center p-3 text-sm link"><?= e(t('أقدم')) ?></a><?php endif; ?>
      </div>
      <div class="lg:col-span-2 card overflow-y-auto min-h-0 <?= $postId ? '' : 'hidden lg:block' ?>">
        <?php if (!$postId): ?>
          <div class="p-10 text-center text-ink-400 text-sm"><?= e(t('اختار منشور عشان تشوف تعليقاته')) ?></div>
        <?php else: ?>
          <div class="p-4 border-b border-ink-200 flex items-center gap-3">
            <a href="<?= e(url('inbox', ['folder' => 'comments'])) ?>" class="lg:hidden link text-sm"><?= e(t('رجوع')) ?></a>
            <p class="text-sm line-clamp-2 flex-1"><?= e(mb_substr((string)($currentPost['content'] ?? ''), 0, 200)) ?></p>
          </div>
          <div class="p-4 space-y-5" id="thread">
            <?php if (!$comments): ?><p class="text-sm text-ink-500"><?= e(t('مفيش تعليقات.')) ?></p><?php endif; ?>
            <?php foreach ($comments as $c) ip_private_reply_block($c, $postId, $accId); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    </div>
    <?php return; endif; ?>

    <div class="grid lg:grid-cols-3 gap-4 h-[calc(100vh-19rem)] min-h-[500px]">
      <!-- قائمة المحادثات -->
      <div data-live="list" class="card overflow-y-auto min-h-0 <?= $convId ? 'hidden lg:block' : '' ?>">
        <?php $aiLab = (ai_connection() && can('ai.use')) ? ai_labels((int)ws('id')) : null; ?>
        <?php if ($aiLab !== null && $convs): ?>
          <div class="sticky top-0 z-10 bg-white/95 backdrop-blur border-b border-ink-200 px-3 py-2 flex items-center gap-2 text-xs">
            <button type="button" id="ai-classify" class="inline-flex items-center gap-1 rounded-lg bg-brand-50 text-brand-700 border border-brand-200 px-2 py-1 hover:bg-brand-100 disabled:opacity-60">✨ <span><?= e(t('صنّف الآن')) ?></span></button>
            <label class="inline-flex items-center gap-1.5 text-ink-600 cursor-pointer"><input type="checkbox" id="ai-urgent" class="w-3.5 h-3.5"> <?= e(t('العاجل أولًا')) ?></label>
            <label class="inline-flex items-center gap-1.5 text-ink-600 cursor-pointer"><input type="checkbox" id="ai-intent" class="w-3.5 h-3.5"> 🛒 <?= e(t('نية شراء')) ?></label>
          </div>
        <?php endif; ?>
        <?php if (!$convs): ?><p class="p-5 text-sm text-ink-500"><?= e($q !== '' ? t('مفيش نتائج للبحث ده.') : t('مفيش رسائل.')) ?></p><?php endif; ?>
        <?php foreach ($convs as $c): $on = $c['id'] === $convId; $lb = $aiLab[$c['id']] ?? null; ?>
          <a data-urg="<?= (int)($lb['urgency'] ?? 0) ?>" data-intent="<?= (int)($lb['intent'] ?? 0) ?>" href="<?= e(url('inbox', $base + ['c' => $c['id'], 'a' => $c['accountId']])) ?>" class="ai-conv flex gap-3 p-4 border-b border-ink-100 hover:bg-ink-50 transition <?= $on ? 'bg-brand-50 border-s-2 border-s-brand-600' : '' ?>">
            <?= avatar_with_platform($c['participantPicture'] ?? null, $c['participantName'] ?? '', (string)($c['platform'] ?? '')) ?>
            <div class="flex-1 min-w-0">
              <div class="flex justify-between gap-2">
                <span class="font-semibold text-sm truncate"><?= e($c['participantName'] ?? $c['participantUsername'] ?? t('بدون اسم')) ?></span>
                <?php if (!empty($c['unreadCount'])): ?><span class="bg-red-600 text-white text-[11px] rounded-full px-2 py-0.5"><?= (int)$c['unreadCount'] ?></span><?php endif; ?>
                <?php if (!empty($c['matchCount'])): ?><span class="badge"><?= e(t('%s نتيجة', (int)$c['matchCount'])) ?></span><?php endif; ?>
              </div>
              <p class="text-xs text-ink-500 truncate"><?= e($c['matchSnippet'] ?? $c['lastMessage'] ?? '') ?></p>
              <?php if ($lb): $catL = ['inquiry' => t('استفسار'), 'complaint' => t('شكوى'), 'purchase' => t('طلب شراء'), 'support' => t('دعم'), 'spam' => t('سبام'), 'other' => t('أخرى')]; ?>
                <div class="flex flex-wrap gap-1 mt-1" title="<?= e($lb['summary']) ?>">
                  <?php if ((int)$lb['urgency'] === 3): ?><span class="rounded-md bg-red-100 text-red-700 px-1.5 py-0.5 text-[10px] font-medium"><?= e(t('عاجل')) ?></span><?php endif; ?>
                  <?php if ($lb['intent']): ?><span class="rounded-md bg-lime-100 text-lime-700 px-1.5 py-0.5 text-[10px] font-medium">🛒 <?= e(t('نية شراء')) ?></span><?php endif; ?>
                  <span class="rounded-md bg-ink-100 text-ink-600 px-1.5 py-0.5 text-[10px]"><?= e($catL[$lb['category']] ?? $lb['category']) ?></span>
                  <?php if (in_array($lb['sentiment'], ['angry', 'upset'], true)): ?><span class="rounded-md bg-amber-100 text-amber-700 px-1.5 py-0.5 text-[10px]"><?= $lb['sentiment'] === 'angry' ? '😠 ' . e(t('غاضب')) : '😕 ' . e(t('متضايق')) ?></span><?php endif; ?>
                </div>
              <?php endif; ?>
              <p class="text-[11px] text-ink-400 mt-0.5"><?= e(platform_label($c['platform'] ?? '')) ?><?php if (!empty($c['accountUsername'])): ?> · <?= e(trim($c['accountUsername'])) ?><?php endif; ?> · <?= e(fmt_date($c['updatedTime'] ?? null, 'd/m h:i A')) ?><?php if (($c['status'] ?? '') === 'archived'): ?> · <?= e(t('مؤرشفة')) ?><?php endif; ?></p>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if ($next): ?><a href="<?= e(url('inbox', $base + ['cursor' => $next])) ?>" class="block text-center p-3 text-sm link"><?= e(t('أقدم')) ?></a><?php endif; ?>
      </div>
      <?php if ($aiLab !== null): ?>
      <script>
      (function () {
        function list() { return document.querySelector('[data-live=list]'); }
        function apply() {
          var L = list(); if (!L) return; var u = document.getElementById('ai-urgent'), it = document.getElementById('ai-intent');
          var items = [].slice.call(L.querySelectorAll('a.ai-conv'));
          items.forEach(function (a, i) { if (!a.dataset.ord) a.dataset.ord = i; a.classList.toggle('hidden', !!(it && it.checked) && a.dataset.intent !== '1'); });
          items.sort(function (a, b) { return (u && u.checked ? (+b.dataset.urg) - (+a.dataset.urg) : 0) || (+a.dataset.ord) - (+b.dataset.ord); });
          var anchor = L.querySelector('a.ai-conv'); if (!anchor) return; var parent = anchor.parentNode, tail = parent.querySelector('a:not(.ai-conv)');
          items.forEach(function (a) { parent.insertBefore(a, tail); });
        }
        document.addEventListener('change', function (e) { if (e.target.id === 'ai-urgent' || e.target.id === 'ai-intent') { try { localStorage.setItem(e.target.id, e.target.checked ? '1' : ''); } catch (x) {} apply(); } });
        document.addEventListener('click', function (e) {
          var b = e.target.closest('#ai-classify'); if (!b) return;
          b.disabled = true; b.classList.add('animate-pulse');
          var fd = new FormData(); fd.append('task', 'classify_now');
          fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content } }).then(function (r) { return r.json(); })
            .then(function (d) { if (d.error) { window.aiToast ? aiToast(d.error) : alert(d.error); b.disabled = false; b.classList.remove('animate-pulse'); return; } location.reload(); })
            .catch(function () { b.disabled = false; b.classList.remove('animate-pulse'); });
        });
        function restore() { ['ai-urgent', 'ai-intent'].forEach(function (id) { var el = document.getElementById(id); try { if (el && localStorage.getItem(id)) el.checked = true; } catch (x) {} }); apply(); }
        restore();
        new MutationObserver(function () { var L = list(); if (L && !L.dataset.aiSeen) { L.dataset.aiSeen = 1; restore(); } }).observe(document.body, { childList: true, subtree: true });
      })();
      </script>
      <?php endif; ?>

      <!-- المحادثة -->
      <div class="lg:col-span-2 card flex flex-col min-h-0 overflow-hidden <?= $convId ? '' : 'hidden lg:flex' ?>">
        <?php if (!$convId): ?>
          <div class="flex-1 flex items-center justify-center text-ink-400 text-sm"><?= e(t('اختار محادثة من القائمة')) ?></div>
        <?php else: $isRequest = $folder === 'requests' || ($current['folder'] ?? '') === 'requests'; $archived = ($current['status'] ?? '') === 'archived'; ?>
          <div class="p-3 border-b border-ink-200 flex flex-wrap items-center gap-2">
            <a href="<?= e(url('inbox', $base)) ?>" class="lg:hidden link text-sm"><?= e(t('رجوع')) ?></a>
            <span class="font-bold"><?= e($current['participantName'] ?? $current['participantUsername'] ?? t('محادثة')) ?></span>
            <?php if ($plat): ?><span class="inline-flex items-center gap-1.5 text-xs text-ink-500 bg-ink-100 rounded-full px-2 py-0.5"><?= platform_icon($plat, 'w-3.5 h-3.5') ?><?= e(platform_label($plat)) ?><?php if (!empty($current['accountUsername'])): ?> · <?= e(trim($current['accountUsername'])) ?><?php endif; ?></span><?php endif; ?>
            <?php foreach ($convLabels as $l): ?>
              <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="label_remove"><input type="hidden" name="label_id" value="<?= e($l['id']) ?>">
                <button class="badge" title="<?= e(t('شيل التصنيف')) ?>"><?= e($l['name'] ?? '') ?> ×</button></form>
            <?php endforeach; ?>
            <div class="ms-auto flex flex-wrap items-center gap-2">
              <?php if (!$isRequest): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $archived ? 'unarchive' : 'archive' ?>">
                  <button class="btn-ghost text-xs"><?= e($archived ? t('رجّعها للنشطة') : t('أرشفة')) ?></button></form>
              <?php endif; ?>
              <?php if (can('inbox.manage')): ?>
                <form method="post" onsubmit="return confirm('<?= tjs('حذف المحادثة دي وكل رسايلها من Zernio؟ مش هينفع ترجعها.') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_conv">
                  <button class="btn-ghost text-xs text-red-600"><?= e(t('حذف المحادثة')) ?></button></form>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($labelError):
              // فيسبوك محتاج موافقة على شروط التصنيفات مرة واحدة للصفحة: نعرض زرار واضح بدل الخطأ
              $tosUrl = preg_match('#https://www\.facebook\.com/[^\s"]+page_contact_tos/?#', $labelError, $mm) ? $mm[0] : null; ?>
            <?php if ($tosUrl): ?>
              <div class="px-3 py-2 border-b border-ink-100 bg-amber-50 text-amber-800 text-xs flex flex-wrap items-center gap-2">
                <span><?= e(t('عشان تستخدم تصنيفات المحادثات، فيسبوك محتاج توافق على شروطه مرة واحدة للصفحة دي.')) ?></span>
                <a href="<?= e($tosUrl) ?>" target="_blank" rel="noopener" class="btn-ghost !py-1 text-xs"><?= e(t('وافق من فيسبوك ↗')) ?></a>
                <span class="text-amber-700/80"><?= e(t('بعد الموافقة ارجع وحدّث الصفحة.')) ?></span>
              </div>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (is_array($pageLabels)): ?>
            <details class="px-3 py-2 border-b border-ink-100 text-sm">
              <summary class="cursor-pointer text-xs font-semibold text-brand-700"><?= e(t('تصنيفات الصفحة (فيسبوك)')) ?></summary>
              <div class="flex flex-wrap gap-2 mt-2">
                <?php $onIds = array_column($convLabels, 'id'); foreach ($pageLabels as $l): ?>
                  <span class="inline-flex items-center gap-1 border border-ink-200 rounded-full ps-2 pe-1 py-0.5 text-xs">
                    <?= e($l['name'] ?? '') ?>
                    <?php if (!in_array($l['id'], $onIds, true)): ?>
                      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="label_add"><input type="hidden" name="label_id" value="<?= e($l['id']) ?>"><button class="link" title="<?= e(t('حط التصنيف على المحادثة')) ?>">+</button></form>
                    <?php endif; ?>
                    <?php if (can('inbox.manage')): ?>
                      <form method="post" class="inline" onsubmit="return confirm('<?= tjs('حذف التصنيف من الصفحة كلها؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="label_delete"><input type="hidden" name="label_id" value="<?= e($l['id']) ?>"><button class="text-red-600" title="<?= e(t('حذف التصنيف')) ?>">×</button></form>
                    <?php endif; ?>
                  </span>
                <?php endforeach; ?>
                <?php if (!$pageLabels): ?><span class="text-xs text-ink-500"><?= e(t('مفيش تصنيفات لسه.')) ?></span><?php endif; ?>
              </div>
              <form method="post" class="flex gap-2 mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="label_create">
                <input name="label_name" maxlength="100" required class="input text-sm py-1 flex-1" placeholder="<?= e(t('اسم تصنيف جديد')) ?>">
                <button class="btn-ghost text-xs"><?= e(t('إضافة')) ?></button></form>
            </details>
          <?php endif; ?>
          <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3 bg-ink-50" id="thread">
            <?php $lastDay = null; foreach ($messages as $m): $out = ($m['direction'] ?? '') === 'outgoing'; $mid = (string)($m['id'] ?? '');
                $day = fmt_date($m['createdAt'] ?? null, 'Y-m-d');
                if ($day !== $lastDay): $lastDay = $day;
                    $label = $day === (new DateTime('now', new DateTimeZone(site_timezone())))->format('Y-m-d') ? t('النهارده') : ($day === (new DateTime('yesterday', new DateTimeZone(site_timezone())))->format('Y-m-d') ? t('امبارح') : fmt_date($m['createdAt'] ?? null, 'd/m/Y')); ?>
              <div class="flex justify-center my-2"><span class="text-[11px] text-ink-500 bg-white border border-ink-200 rounded-full px-3 py-0.5"><?= e($label) ?></span></div>
            <?php endif; ?>
              <div class="flex <?= $out ? 'justify-start' : 'justify-end' ?>">
                <div class="max-w-[80%] group">
                  <div class="rounded-2xl px-4 py-2 text-sm <?= $out ? 'bg-brand-600 text-white' : 'bg-white border border-ink-200' ?>">
                    <?php if (!empty($m['isDeleted'])): ?><em class="opacity-70"><?= e(t('رسالة محذوفة')) ?></em>
                    <?php else: ?>
                      <?php if (($m['message'] ?? '') !== ''): ?><p class="whitespace-pre-line break-words"><?= e($m['message']) ?></p>
                      <?php elseif (empty($m['attachments'])): ?><p class="italic opacity-60"><?= e(t('(رسالة من غير نص، زي ستيكر أو تفاعل)')) ?></p><?php endif; ?>
                      <?php foreach ($m['attachments'] ?? [] as $att): $u = $att['url'] ?? $att['payload']['url'] ?? null; if (!$u) continue; $at = $att['type'] ?? ''; ?>
                        <?php if ($at === 'image' || $at === 'sticker'): ?><img src="<?= e($u) ?>" class="mt-2 rounded-lg max-h-60" referrerpolicy="no-referrer" alt="">
                        <?php else: ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" class="underline block mt-1"><?= e(t('مرفق (%s)', $at ?: t('ملف'))) ?></a><?php endif; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="text-[10px] mt-1 <?= $out ? 'text-brand-100' : 'text-ink-400' ?>"><?= e(fmt_date($m['createdAt'] ?? null, 'h:i A')) ?><?php if (!empty($m['isEdited'])): ?> · <?= e(t('متعدلة')) ?><?php endif; ?></div>
                  </div>
                  <?php if (!empty($m['reactions'])): ?>
                    <div class="flex gap-1 mt-1 text-sm">
                      <?php foreach ($m['reactions'] as $rx): ?>
                        <?php if (!empty($rx['fromMe'])): ?>
                          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="unreact"><input type="hidden" name="message_id" value="<?= e($mid) ?>"><button class="bg-brand-50 border border-brand-200 rounded-full px-1.5" title="<?= e(t('شيل التفاعل')) ?>"><?= e($rx['emoji'] ?? '') ?></button></form>
                        <?php else: ?><span class="bg-white border border-ink-200 rounded-full px-1.5"><?= e($rx['emoji'] ?? '') ?></span><?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($mid !== '' && empty($m['isDeleted'])): ?>
                    <div class="flex flex-wrap items-center gap-2 mt-1 text-xs text-ink-500 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition <?= $out ? '' : 'justify-end' ?>">
                      <?php if (ip_can_react($plat) && !$isSandboxConv): ?>
                        <form method="post" class="inline-flex gap-0.5"><?= csrf_field() ?><input type="hidden" name="action" value="react"><input type="hidden" name="message_id" value="<?= e($mid) ?>">
                          <?php foreach (['👍', '❤️', '😂', '😮', '😢', '🙏'] as $em): ?><button name="emoji" value="<?= e($em) ?>" class="hover:scale-125 transition" title="<?= e(t('تفاعل')) ?>"><?= $em ?></button><?php endforeach; ?>
                        </form>
                      <?php endif; ?>
                      <?php if ($out && ip_can_edit($plat)): ?>
                        <details class="inline"><summary class="cursor-pointer link"><?= e(t('تعديل')) ?></summary>
                          <form method="post" class="flex gap-1 mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="edit_msg"><input type="hidden" name="message_id" value="<?= e($mid) ?>">
                            <input name="text" required class="input text-xs py-1" value="<?= e($m['message'] ?? '') ?>"><button class="btn-ghost text-xs"><?= e(t('حفظ')) ?></button></form>
                        </details>
                      <?php endif; ?>
                      <?php if ($out && ip_can_delete($plat)): ?>
                        <form method="post" class="inline" onsubmit="return confirm('<?= tjs('حذف الرسالة دي؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_msg"><input type="hidden" name="message_id" value="<?= e($mid) ?>"><button class="text-red-600"><?= e(t('حذف')) ?></button></form>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <div id="bottom"></div>
          </div>
          <?php if ($isRequest): ?>
            <form method="post" class="p-3 border-t border-ink-200 flex gap-2 items-end">
              <?= csrf_field() ?><input type="hidden" name="action" value="accept">
              <textarea data-ai="polish_reply,summarize" name="message" rows="2" required class="input flex-1 text-sm" placeholder="<?= e(t('اكتب ردك عشان تقبل الطلب...')) ?>"></textarea>
              <button class="btn-primary"><?= e(t('قبول ورد')) ?></button>
            </form>
          <?php else: ?>
            <form method="post" enctype="multipart/form-data" data-optimistic class="p-3 border-t border-ink-200 flex gap-2 items-end">
              <?= csrf_field() ?><input type="hidden" name="action" value="send">
              <label class="p-2.5 rounded-lg hover:bg-ink-100 cursor-pointer" title="<?= e(t('إرفاق ملف')) ?>">
                <svg class="w-5 h-5 text-ink-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15.2 7l-6.6 6.6a2 2 0 102.8 2.8l6.4-6.6a4 4 0 00-5.6-5.6l-6.4 6.5a6 6 0 108.5 8.5L20.5 13"/></svg>
                <input type="file" name="attachment" class="hidden" onchange="this.parentNode.classList.add('bg-brand-100')">
              </label>
              <textarea data-ai="polish_reply,summarize,triage,extract,translate_ctx" name="message" rows="2" class="input !mt-0 flex-1 text-sm" placeholder="<?= e($plat ? t('اكتب ردك على %s...', platform_label($plat)) : t('اكتب ردك...')) ?>" data-enter-submit></textarea>
              <?php if (ai_connection() && can('ai.use')): ?><button type="button" data-ai-task="reply" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-2.5 py-2.5 text-xs font-medium text-brand-700 hover:bg-brand-100 transition disabled:opacity-60" title="<?= e(t('اقتراح رد')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg><span><?= e(t('اقتراح رد')) ?></span></button><?php endif; ?>
              <button class="btn-primary"><?= e(t('إرسال')) ?></button>
            </form>
            <p class="px-4 pb-2 -mt-1 text-[11px] text-ink-400"><?= e(t('Enter للإرسال · Shift+Enter لسطر جديد')) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    </div>
    <?php
});
