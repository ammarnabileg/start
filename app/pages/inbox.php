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

// إجراءات جماعية وحملات الرسائل (JSON): على المحادثات اللي المستخدم حددها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['bulk', 'camp_create', 'camp_status', 'camp_ctl', 'camp_tick'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $J = function (array $d, int $c = 200) { http_response_code($c); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    if (!$z) $J(['error' => $err ?: 'Zernio'], 422);
    $wid = (int)ws('id');
    $act = (string)$_POST['action'];
    // العناصر: "conv|account"
    $items = [];
    foreach ((array)($_POST['items'] ?? []) as $raw) {
        [$cv, $ac] = array_pad(explode('|', (string)$raw, 2), 2, '');
        if (preg_match('/^[\w.:@-]{1,120}$/', $cv) && preg_match('/^[\w.:@-]{1,120}$/', $ac)) $items[$cv] = $ac;
    }
    if ($act === 'bulk') {
        $op = (string)($_POST['op'] ?? '');
        if (!$items) $J(['error' => t('حدد محادثة واحدة على الأقل.')], 422);
        if ($op === 'classify') {
            if (!ai_connection() || !can('ai.use') || !ai_feature_on('classify_btn')) $J(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
            try { $n = ai_classify_conversations($wid, 40, array_keys($items), true); } catch (Throwable $e) { $J(['error' => $e->getMessage()], 502); }
            $J(['ok' => true, 'done' => $n]);
        }
        if (!can('inbox.reply') && !can('inbox.manage')) $J(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
        $ok = 0; $fail = 0;
        foreach ($items as $cv => $ac) {
            $cv = (string)$cv;
            try {
                if ($op === 'read') $z->markRead($cv, $ac);
                elseif ($op === 'archive' || $op === 'unarchive') ip_set_status($z, $cv, $ac, $op === 'archive' ? 'archived' : 'active');
                else $J(['error' => t('طلب غير صحيح.')], 400);
                $ok++;
            } catch (Throwable $e) { $fail++; }
        }
        log_activity('inbox.bulk', $op . ' ' . $ok);
        $J(['ok' => true, 'done' => $ok, 'failed' => $fail]);
    }
    if ($act === 'camp_create') {
        if (!can('inbox.reply')) $J(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
        $message = trim((string)($_POST['message'] ?? ''));
        if (mb_strlen($message) < 2) $J(['error' => t('اكتب نص الرسالة.')], 422);
        $mode = (string)($_POST['mode'] ?? 'selected');
        $limit = max(0, (int)($_POST['limit'] ?? 0));
        $only24 = !empty($_POST['only24']);
        $skipBad = !empty($_POST['skip_bad']);
        // كل المحادثات: بنجيبها صفحة صفحة (حد أقصى 1000)
        $rows = [];
        $cursorC = null; $pages = 0;
        do {
            $res = $z->conversations(array_filter(['limit' => 100, 'cursor' => $cursorC, 'profileId' => zernio_profile(), 'status' => 'active']));
            foreach ($res['data'] ?? [] as $c) $rows[(string)$c['id']] = $c;
            $cursorC = $res['pagination']['nextCursor'] ?? null; $pages++;
        } while ($mode === 'all' && $cursorC && $pages < 10);
        if ($mode !== 'all') $rows = array_intersect_key($rows, $items);
        $rows = array_combine(array_map('strval', array_keys($rows)), array_values($rows)) ?: [];
        $labels = $skipBad ? ai_labels($wid) : [];
        $list = []; $skipped = 0;
        foreach ($rows as $cid => $c) {
            $cid = (string)$cid;
            $ac = (string)($c['accountId'] ?? ($items[$cid] ?? ''));
            if ($ac === '') continue;
            $last = strtotime((string)($c['lastMessageAt'] ?? $c['updatedTime'] ?? '')) ?: 0;
            if ($only24 && $last < time() - 86400) { $skipped++; continue; }
            $lb = $labels[$cid] ?? null;
            if ($lb && (in_array($lb['sentiment'], ['angry', 'upset'], true) || $lb['category'] === 'complaint' || $lb['category'] === 'spam')) { $skipped++; continue; }
            $lm = $c['lastMessage'] ?? ''; $lm = is_array($lm) ? (string)($lm['text'] ?? '') : (string)$lm;
            $list[] = [$cid, $ac, (string)($c['participantName'] ?? ''), (string)($c['platform'] ?? ''), mb_substr($lm, 0, 380)];
            if ($limit && count($list) >= $limit) break;
        }
        // المحددين اللي مش في أول صفحات القائمة: نضيفهم من غير معلومات إضافية
        if ($mode !== 'all') foreach ($items as $cid => $ac) if (!isset($rows[(string)$cid]) && (!$limit || count($list) < $limit) && !$only24) $list[] = [(string)$cid, $ac, '', '', ''];
        if (!$list) $J(['error' => t('مفيش محادثات تنطبق عليها الشروط (%s اتستبعدت).', $skipped)], 422);
        $id = camp_create($wid, trim((string)($_POST['name'] ?? '')) ?: t('حملة %s', fmt_date(gmdate('c'), 'd/m h:i A')), $message, $list, !empty($_POST['personalize']), (int)($_POST['per_minute'] ?? 20));
        $J(['ok' => true, 'id' => $id, 'total' => count($list), 'skipped' => $skipped]);
    }
    if ($act === 'camp_ctl') {
        $c = camp_get($wid, (int)($_POST['id'] ?? 0));
        $to = ['pause' => 'paused', 'resume' => 'running', 'cancel' => 'cancelled'][(string)($_POST['op'] ?? '')] ?? null;
        if (!$c || !$to || in_array($c['status'], ['done', 'cancelled'], true)) $J(['error' => t('طلب غير صحيح.')], 400);
        db()->prepare("UPDATE msg_campaigns SET status = ?" . ($to === 'cancelled' ? ", finished_at = NOW()" : '') . " WHERE id = ?")->execute([$to, $c['id']]);
        if ($to === 'cancelled') db()->prepare("UPDATE msg_campaign_items SET status = 'cancelled' WHERE campaign_id = ? AND status = 'queued'")->execute([$c['id']]);
        $J(['ok' => true]);
    }
    // الصفحة وهي مفتوحة بتبعت دفعة صغيرة كل شوية، عشان الحملة تمشي حتى لو مفيش cron
    if ($act === 'camp_tick') { session_write_close(); try { camp_tick($wid, 4); } catch (Throwable $e) {} }
    $J(['ok' => true, 'campaigns' => array_map('camp_public', camp_list($wid, 8))]);
}

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
      <div class="tb p-1 gap-1 text-sm">
        <?php foreach ($folders as $k => $lbl): ?>
          <a href="<?= e(url('inbox', $k === 'inbox' ? [] : ['folder' => $k])) ?>" class="h-8 px-3 rounded-lg flex items-center whitespace-nowrap transition <?= $folder === $k ? 'bg-brand-600 text-[#fff] font-medium shadow-sm' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900' ?>"><?= e($lbl) ?></a>
        <?php endforeach; ?>
      </div>
      <form method="get" class="flex flex-wrap items-center gap-2 flex-1">
        <input type="hidden" name="p" value="inbox">
        <?php if ($folder !== 'inbox'): ?><input type="hidden" name="folder" value="<?= e($folder) ?>"><?php endif; ?>
        <?php if ($folder === 'inbox'): ?>
          <select name="status" class="input !mt-0 !w-auto !h-10 !py-0 text-sm" onchange="this.form.submit()">
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= e(t('النشطة')) ?></option>
            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>><?= e(t('المؤرشفة')) ?></option>
          </select>
        <?php endif; ?>
        <?php if ($folder !== 'comments'): ?>
          <select name="platform" class="input !mt-0 !w-auto !h-10 !py-0 text-sm" onchange="this.form.submit()">
            <option value=""><?= e(t('كل المنصات')) ?></option>
            <?php foreach ($msgPlatforms as $pl): ?><option value="<?= e($pl) ?>" <?= $platform === $pl ? 'selected' : '' ?>><?= e(platform_label($pl)) ?></option><?php endforeach; ?>
          </select>
        <?php endif; ?>
        <select name="acct" class="input !mt-0 !w-auto !h-10 !py-0 text-sm max-w-[14rem]" onchange="this.form.submit()">
          <option value=""><?= e(t('كل الحسابات')) ?></option>
          <?php foreach ($accounts as $a): ?><option value="<?= e($a['_id']) ?>" <?= $acctFilter === $a['_id'] ? 'selected' : '' ?>><?= e(ip_account_label($a)) ?></option><?php endforeach; ?>
        </select>
        <?php if ($folder === 'inbox'): ?>
          <div class="relative flex-1 min-w-[12rem]"><svg class="w-4 h-4 absolute start-3 top-1/2 -translate-y-1/2 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M11 18a7 7 0 100-14 7 7 0 000 14zM20 20l-4-4"/></svg>
          <input type="search" name="q" value="<?= e($q) ?>" minlength="2" class="input !mt-0 !h-10 !py-0 ps-9 text-sm" placeholder="<?= e(t('دور في الرسائل والأسماء والأرقام...')) ?>  ( / )" id="ibx-q"></div>
          <?php if ($q !== ''): ?><a href="<?= e(url('inbox', array_diff_key($base, ['q' => 1]))) ?>" class="link text-sm"><?= e(t('مسح البحث')) ?></a><?php endif; ?>
        <?php endif; ?>
      </form>
      <?php if ($folder === 'inbox'): ?><button type="button" id="camp-dock-btn" class="tb gap-2 px-3 text-sm"><?= ui_icon('megaphone', 'w-4 h-4') ?><span><?= e(t('الحملات')) ?></span><span id="camp-live" class="hidden w-2 h-2 rounded-full bg-lime-500 animate-pulse"></span></button><?php endif; ?>
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

    <?php
      $aiOn = ai_connection() && can('ai.use');
      $aiLab = ($aiOn && ai_feature_on('classify_btn')) ? ai_labels((int)ws('id')) : [];
      $catL = ['inquiry' => t('استفسار'), 'complaint' => t('شكوى'), 'purchase' => t('طلب شراء'), 'support' => t('دعم'), 'spam' => t('سبام'), 'other' => t('أخرى')];
      $ago = function ($iso) {
          $ts = strtotime((string)$iso); if (!$ts) return '';
          $d = time() - $ts;
          if ($d < 60) return t('الآن'); if ($d < 3600) return t('%s د', (int)($d / 60)); if ($d < 86400) return t('%s س', (int)($d / 3600));
          if ($d < 7 * 86400) return t('%s ي', (int)($d / 86400)); return fmt_date($iso, 'd/m');
      };
      $unreadTotal = array_sum(array_map(function ($c) { return (int)($c['unreadCount'] ?? 0); }, $convs));
    ?>
    <div class="grid lg:grid-cols-[23rem_1fr] 2xl:grid-cols-[23rem_1fr_19rem] gap-4 h-[calc(100vh-13rem)] min-h-[560px]" id="ibx">
      <!-- قائمة المحادثات -->
      <div class="card flex flex-col min-h-0 overflow-hidden <?= $convId ? 'hidden lg:flex' : 'flex' ?>">
        <div class="flex items-center gap-2 px-3 h-12 border-b border-ink-200 shrink-0">
          <label class="flex items-center gap-2 text-xs text-ink-600 cursor-pointer select-none" title="<?= e(t('تحديد الكل')) ?>">
            <input type="checkbox" id="ibx-all" class="w-4 h-4 rounded">
            <span id="ibx-count" class="tabular-nums"><?= e(t('%s محادثة', count($convs))) ?></span>
          </label>
          <?php if ($unreadTotal): ?><span class="rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-[11px] font-medium"><?= e(t('%s غير مقروءة', $unreadTotal)) ?></span><?php endif; ?>
          <div class="ms-auto flex items-center gap-1">
            <?php if ($aiLab !== [] || ($aiOn && ai_feature_on('classify_btn'))): ?>
              <button type="button" data-ibx-filter="urgent" class="ibx-f h-8 px-2.5 rounded-lg text-xs text-ink-600 hover:bg-ink-100 inline-flex items-center gap-1" title="<?= e(t('العاجل أولًا')) ?>"><?= ui_icon('alert', 'w-3.5 h-3.5') ?><span class="hidden sm:inline"><?= e(t('العاجل')) ?></span></button>
              <button type="button" data-ibx-filter="intent" class="ibx-f h-8 px-2.5 rounded-lg text-xs text-ink-600 hover:bg-ink-100 inline-flex items-center gap-1" title="<?= e(t('نية شراء')) ?>"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 4h2l2.4 11h10.2L20 8H6.2M9 20a1 1 0 100-2 1 1 0 000 2zM17 20a1 1 0 100-2 1 1 0 000 2z"/></svg><span class="hidden sm:inline"><?= e(t('شراء')) ?></span></button>
            <?php endif; ?>
            <button type="button" data-ibx-filter="unread" class="ibx-f h-8 px-2.5 rounded-lg text-xs text-ink-600 hover:bg-ink-100" title="<?= e(t('غير المقروءة فقط')) ?>"><?= e(t('غير مقروء')) ?></button>
          </div>
        </div>
        <div data-live="list" class="flex-1 min-h-0 overflow-y-auto">
          <?php if (!$convs): ?>
            <div class="p-10 text-center"><div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-ink-100 text-ink-400 flex items-center justify-center"><?= ui_icon('chat', 'w-6 h-6') ?></div><p class="text-sm text-ink-500"><?= e($q !== '' ? t('مفيش نتائج للبحث ده.') : t('مفيش رسائل.')) ?></p></div>
          <?php endif; ?>
          <?php foreach ($convs as $c): $on = $c['id'] === $convId; $lb = $aiLab[$c['id']] ?? null; $un = (int)($c['unreadCount'] ?? 0); $nm = (string)($c['participantName'] ?? $c['participantUsername'] ?? t('بدون اسم')); ?>
            <div class="ibx-item group relative flex items-stretch border-b border-ink-100 transition <?= $on ? 'bg-brand-50/70' : 'hover:bg-ink-50' ?>" data-key="<?= e($c['id'] . '|' . $c['accountId']) ?>" data-urg="<?= (int)($lb['urgency'] ?? 0) ?>" data-intent="<?= (int)($lb['intent'] ?? 0) ?>" data-unread="<?= $un ? 1 : 0 ?>">
              <?php if ($on): ?><span class="absolute inset-y-0 start-0 w-1 bg-brand-600 rounded-e"></span><?php endif; ?>
              <label class="ibx-cb flex items-center ps-3 pe-1 cursor-pointer"><input type="checkbox" class="ibx-sel w-4 h-4 rounded opacity-0 group-hover:opacity-100 checked:opacity-100 focus:opacity-100 transition" aria-label="<?= e($nm) ?>"></label>
              <a href="<?= e(url('inbox', $base + ['c' => $c['id'], 'a' => $c['accountId']])) ?>" class="flex-1 min-w-0 flex gap-3 py-3 pe-3">
                <?= avatar_with_platform($c['participantPicture'] ?? null, $nm, (string)($c['platform'] ?? '')) ?>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="text-sm truncate <?= $un ? 'font-semibold text-ink-900' : 'font-medium text-ink-800' ?>"><?= e($nm) ?></span>
                    <span class="ms-auto shrink-0 text-[11px] <?= $un ? 'text-brand-700 font-medium' : 'text-ink-400' ?>"><?= e($ago($c['lastMessageAt'] ?? $c['updatedTime'] ?? '')) ?></span>
                  </div>
                  <div class="flex items-center gap-2 mt-0.5">
                    <p class="text-xs truncate flex-1 <?= $un ? 'text-ink-800' : 'text-ink-500' ?>"><?= e($c['matchSnippet'] ?? $c['lastMessage'] ?? '') ?></p>
                    <?php if ($un): ?><span class="shrink-0 min-w-[20px] h-5 px-1.5 rounded-full bg-brand-600 text-[#fff] text-[11px] font-semibold flex items-center justify-center"><?= $un ?></span><?php endif; ?>
                    <?php if (!empty($c['matchCount'])): ?><span class="badge"><?= e(t('%s نتيجة', (int)$c['matchCount'])) ?></span><?php endif; ?>
                  </div>
                  <?php if ($lb): ?>
                    <div class="flex flex-wrap gap-1 mt-1.5" title="<?= e($lb['summary']) ?>">
                      <?php if ((int)$lb['urgency'] === 3): ?><span class="rounded-md bg-red-100 text-red-700 px-1.5 py-0.5 text-[10px] font-medium"><?= e(t('عاجل')) ?></span><?php endif; ?>
                      <?php if ($lb['intent']): ?><span class="rounded-md bg-lime-100 text-lime-700 px-1.5 py-0.5 text-[10px] font-medium"><?= e(t('نية شراء')) ?></span><?php endif; ?>
                      <span class="rounded-md bg-ink-100 text-ink-600 px-1.5 py-0.5 text-[10px]"><?= e($catL[$lb['category']] ?? $lb['category']) ?></span>
                      <?php if (in_array($lb['sentiment'], ['angry', 'upset'], true)): ?><span class="rounded-md bg-amber-100 text-amber-700 px-1.5 py-0.5 text-[10px]"><?= $lb['sentiment'] === 'angry' ? e(t('غاضب')) : e(t('متضايق')) ?></span><?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
          <?php if ($next): ?><a href="<?= e(url('inbox', $base + ['cursor' => $next])) ?>" class="block text-center p-3 text-sm link"><?= e(t('أقدم')) ?></a><?php endif; ?>
        </div>
        <!-- شريط الإجراءات على المحدد -->
        <div id="ibx-bar" class="hidden shrink-0 border-t border-ink-200 bg-white p-2">
          <div class="flex items-center gap-2 px-1 pb-2 text-xs"><b class="text-ink-900" id="ibx-n">0</b><span class="text-ink-500"><?= e(t('محدد')) ?></span><button type="button" id="ibx-clear" class="ms-auto link !text-ink-500"><?= e(t('إلغاء التحديد')) ?></button></div>
          <div class="grid grid-cols-2 gap-1.5">
            <?php if ($aiOn && ai_feature_on('classify_btn')): ?><button type="button" data-op="classify" class="ibx-op btn-ghost !py-1.5 !text-xs !justify-start"><?= ai_icon('spark', 'w-3.5 h-3.5 text-brand-600') ?><?= e(t('تصنيف بالذكاء الاصطناعي')) ?></button><?php endif; ?>
            <button type="button" data-op="read" class="ibx-op btn-ghost !py-1.5 !text-xs !justify-start"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 12l5 5L18 6M12 17l1 1L22 9"/></svg><?= e(t('تعليم كمقروء')) ?></button>
            <button type="button" data-op="<?= $status === 'archived' ? 'unarchive' : 'archive' ?>" class="ibx-op btn-ghost !py-1.5 !text-xs !justify-start"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 5h18v4H3zM5 9v10h14V9M10 13h4"/></svg><?= e($status === 'archived' ? t('رجّعها للنشطة') : t('أرشفة')) ?></button>
            <button type="button" id="ibx-camp-open" class="btn-primary !py-1.5 !text-xs !justify-start"><?= ui_icon('megaphone', 'w-3.5 h-3.5') ?><?= e(t('حملة رسائل')) ?></button>
          </div>
        </div>
      </div>

      <!-- المحادثة -->
      <div class="card flex flex-col min-h-0 overflow-hidden <?= $convId ? 'flex' : 'hidden lg:flex' ?>">
        <?php if (!$convId): ?>
          <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
            <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-brand-600/10 to-cyan-500/10 text-brand-600 flex items-center justify-center mb-4"><?= ui_icon('chat', 'w-8 h-8') ?></div>
            <p class="font-medium text-ink-900"><?= e(t('اختار محادثة من القائمة')) ?></p>
            <p class="text-xs text-ink-500 mt-1"><?= e(t('أو حدد كذا محادثة من المربعات علشان تصنّفها أو تبعت لهم حملة.')) ?></p>
            <p class="text-[11px] text-ink-400 mt-4"><kbd class="px-1.5 py-0.5 rounded border border-ink-200 bg-ink-50">J</kbd> <kbd class="px-1.5 py-0.5 rounded border border-ink-200 bg-ink-50">K</kbd> <?= e(t('للتنقل')) ?> · <kbd class="px-1.5 py-0.5 rounded border border-ink-200 bg-ink-50">X</kbd> <?= e(t('للتحديد')) ?> · <kbd class="px-1.5 py-0.5 rounded border border-ink-200 bg-ink-50">R</kbd> <?= e(t('للرد')) ?></p>
          </div>
        <?php else: $isRequest = $folder === 'requests' || ($current['folder'] ?? '') === 'requests'; $archived = ($current['status'] ?? '') === 'archived'; ?>
          <div class="px-4 h-16 border-b border-ink-200 flex items-center gap-3 shrink-0">
            <a href="<?= e(url('inbox', $base)) ?>" class="lg:hidden p-2 -ms-2 rounded-lg hover:bg-ink-100" aria-label="<?= e(t('رجوع')) ?>"><svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></a>
            <?= avatar_with_platform($current['participantPicture'] ?? null, (string)($current['participantName'] ?? ''), $plat) ?>
            <div class="min-w-0"><div class="font-semibold text-ink-900 truncate"><?= e($current['participantName'] ?? $current['participantUsername'] ?? t('محادثة')) ?></div>
              <div class="text-[11px] text-ink-500 truncate"><?= e(platform_label($plat)) ?><?php if (!empty($current['participantUsername'])): ?> · <span dir="ltr">@<?= e(ltrim((string)$current['participantUsername'], '@')) ?></span><?php endif; ?></div></div>
            <?php foreach ($convLabels as $l): ?>
              <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="label_remove"><input type="hidden" name="label_id" value="<?= e($l['id']) ?>">
                <button class="badge" title="<?= e(t('شيل التصنيف')) ?>"><?= e($l['name'] ?? '') ?> ×</button></form>
            <?php endforeach; ?>
            <div class="ms-auto flex items-center gap-1.5">
              <button type="button" id="ibx-info" class="hidden lg:inline-flex 2xl:hidden tb w-9 h-9 !h-9 justify-center" title="<?= e(t('بيانات العميل')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 11v5M12 8h.01"/></svg></button>
              <?php if (!$isRequest): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $archived ? 'unarchive' : 'archive' ?>">
                  <button class="tb w-9 h-9 !h-9 justify-center" title="<?= e($archived ? t('رجّعها للنشطة') : t('أرشفة')) ?> (E)" data-ibx-archive><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 5h18v4H3zM5 9v10h14V9M10 13h4"/></svg></button></form>
              <?php endif; ?>
              <?php if (can('inbox.manage')): ?>
                <form method="post" onsubmit="return confirm('<?= tjs('حذف المحادثة دي وكل رسايلها من Zernio؟ مش هينفع ترجعها.') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_conv">
                  <button class="tb w-9 h-9 !h-9 justify-center hover:!text-red-600 hover:!border-red-200" title="<?= e(t('حذف المحادثة')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg></button></form>
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
          <div class="flex-1 min-h-0 overflow-y-auto px-4 py-5 space-y-2.5 bg-ink-50/70" id="thread" style="background-image:radial-gradient(rgb(var(--ink-200)/.5) 1px,transparent 1px);background-size:18px 18px">
            <?php $lastDay = null; foreach ($messages as $m): $out = ($m['direction'] ?? '') === 'outgoing'; $mid = (string)($m['id'] ?? '');
                $day = fmt_date($m['createdAt'] ?? null, 'Y-m-d');
                if ($day !== $lastDay): $lastDay = $day;
                    $label = $day === (new DateTime('now', new DateTimeZone(site_timezone())))->format('Y-m-d') ? t('النهارده') : ($day === (new DateTime('yesterday', new DateTimeZone(site_timezone())))->format('Y-m-d') ? t('امبارح') : fmt_date($m['createdAt'] ?? null, 'd/m/Y')); ?>
              <div class="flex justify-center my-2"><span class="text-[11px] text-ink-500 bg-white border border-ink-200 rounded-full px-3 py-0.5"><?= e($label) ?></span></div>
            <?php endif; ?>
              <div class="flex <?= $out ? 'justify-start' : 'justify-end' ?>">
                <div class="max-w-[80%] group">
                  <div class="px-4 py-2.5 text-sm shadow-sm <?= $out ? 'rounded-2xl rounded-ss-md bg-gradient-to-br from-brand-600 to-brand-700 text-[#fff]' : 'rounded-2xl rounded-se-md bg-white border border-ink-200 text-ink-800' ?>">
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
            <?php $dr = ai_drafts((int)ws('id'))[$convId] ?? null; if ($dr && ai_feature_on('auto_reply')): ?>
              <div class="mx-3 mt-2 rounded-xl border border-brand-200 bg-brand-50 p-3 text-sm" id="ai-draft">
                <div class="text-xs text-brand-700 font-medium mb-1 flex items-center gap-1.5"><?= ai_icon('spark', 'w-3.5 h-3.5') ?><?= e(t('رد مقترح جاهز (خارج المواعيد)')) ?></div>
                <p class="text-ink-800 whitespace-pre-wrap" dir="auto"><?= e($dr['text']) ?></p>
                <button type="button" class="btn-ghost !py-1 !px-2.5 !text-xs mt-2" onclick="var t=document.querySelector('#ai-draft ~ form textarea[name=message]');t.value=this.parentNode.querySelector('p').textContent;t.focus();this.parentNode.remove();"><?= e(t('استخدم الرد')) ?></button>
              </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" data-optimistic class="m-3 mt-2 rounded-2xl border border-ink-200 bg-white focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-500/10 transition flex gap-1.5 items-end p-1.5" id="ibx-compose">
              <?= csrf_field() ?><input type="hidden" name="action" value="send">
              <label class="p-2.5 rounded-lg hover:bg-ink-100 cursor-pointer" title="<?= e(t('إرفاق ملف')) ?>">
                <svg class="w-5 h-5 text-ink-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15.2 7l-6.6 6.6a2 2 0 102.8 2.8l6.4-6.6a4 4 0 00-5.6-5.6l-6.4 6.5a6 6 0 108.5 8.5L20.5 13"/></svg>
                <input type="file" name="attachment" class="hidden" onchange="this.parentNode.classList.add('bg-brand-100')">
              </label>
              <textarea data-ai="polish_reply,summarize,triage,extract,translate_ctx" name="message" rows="1" class="input !mt-0 flex-1 text-sm !border-0 !ring-0 !shadow-none max-h-40" placeholder="<?= e($plat ? t('اكتب ردك على %s...', platform_label($plat)) : t('اكتب ردك...')) ?>" data-enter-submit></textarea>
              <?php if (ai_connection() && can('ai.use') && ai_feature_on('reply_btn')): ?><button type="button" data-ai-task="reply" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-2.5 py-2.5 text-xs font-medium text-brand-700 hover:bg-brand-100 transition disabled:opacity-60" title="<?= e(t('اقتراح رد')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg><span><?= e(t('اقتراح رد')) ?></span></button><?php endif; ?>
              <button class="btn-primary"><?= e(t('إرسال')) ?></button>
            </form>
            <p class="px-5 pb-2 -mt-1 text-[10px] text-ink-400"><?= e(t('Enter للإرسال · Shift+Enter لسطر جديد')) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- بيانات العميل -->
      <aside id="ibx-side" class="hidden 2xl:flex card flex-col min-h-0 overflow-y-auto">
        <?php if ($convId && $current): $lb = $aiLab[$convId] ?? null; $nm = (string)($current['participantName'] ?? $current['participantUsername'] ?? ''); ?>
          <div class="p-5 text-center border-b border-ink-100">
            <div class="flex justify-center"><?= str_replace(['w-10 h-10', 'w-11 h-11'], 'w-16 h-16', avatar_with_platform($current['participantPicture'] ?? null, $nm, $plat)) ?></div>
            <div class="font-semibold text-ink-900 mt-3"><?= e($nm ?: t('بدون اسم')) ?></div>
            <?php if (!empty($current['participantUsername'])): ?><div class="text-xs text-ink-500" dir="ltr">@<?= e(ltrim((string)$current['participantUsername'], '@')) ?></div><?php endif; ?>
            <div class="inline-flex items-center gap-1.5 mt-2 rounded-full bg-ink-100 px-2.5 py-1 text-[11px] text-ink-600"><?= platform_icon($plat, 'w-3.5 h-3.5') ?><?= e(platform_label($plat)) ?><?php if (!empty($current['accountUsername'])): ?> · <?= e(trim($current['accountUsername'])) ?><?php endif; ?></div>
          </div>
          <div class="p-4 space-y-4 text-sm">
            <div>
              <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-ink-500"><?= e(t('تحليل الذكاء الاصطناعي')) ?></span>
                <?php if ($aiOn && ai_feature_on('classify_btn')): ?><button type="button" class="ibx-one link text-xs" data-key="<?= e($convId . '|' . $accId) ?>"><?= e($lb ? t('أعد التحليل') : t('حلّل')) ?></button><?php endif; ?></div>
              <?php if ($lb): ?>
                <div class="grid grid-cols-2 gap-2">
                  <div class="rounded-xl bg-ink-50 border border-ink-200 p-2.5"><div class="text-[10px] text-ink-500"><?= e(t('النوع')) ?></div><div class="font-medium text-ink-900"><?= e($catL[$lb['category']] ?? $lb['category']) ?></div></div>
                  <div class="rounded-xl bg-ink-50 border border-ink-200 p-2.5"><div class="text-[10px] text-ink-500"><?= e(t('الإحساس')) ?></div><div class="font-medium <?= in_array($lb['sentiment'], ['angry', 'upset'], true) ? 'text-amber-700' : 'text-ink-900' ?>"><?= e(['positive' => t('راضي'), 'neutral' => t('محايد'), 'upset' => t('متضايق'), 'angry' => t('غاضب')][$lb['sentiment']] ?? $lb['sentiment']) ?></div></div>
                  <div class="rounded-xl bg-ink-50 border border-ink-200 p-2.5"><div class="text-[10px] text-ink-500"><?= e(t('الأهمية')) ?></div><div class="flex gap-1 mt-1"><?php for ($i = 1; $i <= 3; $i++): ?><span class="h-1.5 flex-1 rounded-full <?= $i <= (int)$lb['urgency'] ? ((int)$lb['urgency'] === 3 ? 'bg-red-500' : 'bg-amber-500') : 'bg-ink-200' ?>"></span><?php endfor; ?></div></div>
                  <div class="rounded-xl bg-ink-50 border border-ink-200 p-2.5"><div class="text-[10px] text-ink-500"><?= e(t('نية شراء')) ?></div><div class="font-medium <?= $lb['intent'] ? 'text-lime-700' : 'text-ink-500' ?>"><?= $lb['intent'] ? e(t('أيوه')) : e(t('لأ')) ?></div></div>
                </div>
                <?php if ($lb['summary'] !== ''): ?><p class="text-xs text-ink-600 mt-2 leading-relaxed"><?= e($lb['summary']) ?></p><?php endif; ?>
              <?php else: ?><p class="text-xs text-ink-400"><?= e(t('المحادثة دي لسه ما اتحللتش.')) ?></p><?php endif; ?>
            </div>
            <?php if ($convLabels): ?><div><span class="text-xs font-medium text-ink-500"><?= e(t('التصنيفات')) ?></span><div class="flex flex-wrap gap-1.5 mt-2"><?php foreach ($convLabels as $l): ?><span class="badge"><?= e($l['name'] ?? '') ?></span><?php endforeach; ?></div></div><?php endif; ?>
            <div class="space-y-1.5">
              <span class="text-xs font-medium text-ink-500"><?= e(t('إجراءات سريعة')) ?></span>
              <?php if ($aiOn): ?>
                <button type="button" class="w-full btn-ghost !justify-start !text-xs" data-cp-insight-chat="<?= e(t('لخّص المحادثة دي واقترح الخطوة الجاية.')) ?>"><?= ai_icon('spark', 'w-3.5 h-3.5 text-brand-600') ?><?= e(t('لخّص واقترح الخطوة الجاية')) ?></button>
              <?php endif; ?>
              <button type="button" class="w-full btn-ghost !justify-start !text-xs" data-ibx-camp-one="<?= e($convId . '|' . $accId) ?>"><?= ui_icon('megaphone', 'w-3.5 h-3.5') ?><?= e(t('ضيفه لحملة رسائل')) ?></button>
            </div>
          </div>
        <?php else: ?>
          <div class="flex-1 flex items-center justify-center p-6 text-center text-xs text-ink-400"><?= e(t('بيانات العميل هتظهر هنا لما تفتح محادثة.')) ?></div>
        <?php endif; ?>
      </aside>
    </div>
    </div>

    <!-- نافذة حملة الرسائل -->
    <dialog id="camp-dlg" class="w-[min(560px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40 backdrop:backdrop-blur-[2px]">
      <form method="dialog" id="camp-form" class="flex flex-col max-h-[88vh]">
        <div class="flex items-center gap-3 px-5 h-14 border-b border-ink-200 shrink-0">
          <span class="w-9 h-9 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center"><?= ui_icon('megaphone', 'w-[18px] h-[18px]') ?></span>
          <b class="flex-1 text-ink-900"><?= e(t('حملة رسائل')) ?></b>
          <button type="button" data-camp-close class="p-2 rounded-lg text-ink-400 hover:text-ink-900 hover:bg-ink-100">✕</button>
        </div>
        <div class="p-5 space-y-4 overflow-y-auto">
          <div class="seg w-fit" id="camp-mode"><a href="#" class="on" data-mode="selected"><?= e(t('المحدد')) ?> (<span id="camp-sel-n">0</span>)</a><a href="#" data-mode="all"><?= e(t('كل المحادثات')) ?></a></div>
          <div class="grid grid-cols-2 gap-3">
            <label class="block"><span class="label"><?= e(t('اسم الحملة')) ?></span><input name="name" class="input" placeholder="<?= e(t('مثلاً: عرض نهاية الأسبوع')) ?>"></label>
            <label class="block"><span class="label"><?= e(t('عدد معين (اختياري)')) ?></span><input name="limit" type="number" min="1" class="input" placeholder="<?= e(t('الكل')) ?>"></label>
          </div>
          <label class="block"><span class="label"><?= e(t('الرسالة')) ?></span>
            <textarea name="message" data-ai="improve,shorten,tone_friendly,tone_excited,cta" rows="5" required class="input text-sm" placeholder="<?= e(t('أهلًا {name}! عندنا عرض خاص النهارده...')) ?>"></textarea></label>
          <p class="text-[11px] text-ink-500 -mt-2"><?= e(t('اكتب {name} في أي مكان وهيتبدّل باسم العميل.')) ?></p>
          <div class="space-y-2 text-sm">
            <?php if ($aiOn): ?><label class="flex items-start gap-2.5"><input type="checkbox" name="personalize" value="1" class="mt-1"><span><b class="text-ink-900"><?= e(t('خصّص كل رسالة بالذكاء الاصطناعي')) ?></b><span class="block text-xs text-ink-500"><?= e(t('بيعدّل الرسالة لكل عميل حسب اسمه وآخر رسالة منه. بيستهلك طلب لكل عميل.')) ?></span></span></label><?php endif; ?>
            <label class="flex items-start gap-2.5"><input type="checkbox" name="only24" value="1" class="mt-1" checked><span><b class="text-ink-900"><?= e(t('اللي كلّمونا آخر 24 ساعة بس (موصى به)')) ?></b><span class="block text-xs text-ink-500"><?= e(t('فيسبوك وإنستجرام وواتساب بيمنعوا رسايل ترويجية بعد 24 ساعة من آخر رسالة للعميل، وممكن يقفلوا الحساب.')) ?></span></span></label>
            <label class="flex items-start gap-2.5"><input type="checkbox" name="skip_bad" value="1" class="mt-1" checked><span><b class="text-ink-900"><?= e(t('استبعد الشكاوي والغاضبين والسبام')) ?></b><span class="block text-xs text-ink-500"><?= e(t('حسب تصنيف الذكاء الاصطناعي.')) ?></span></span></label>
          </div>
          <label class="block"><span class="label"><?= e(t('السرعة')) ?></span>
            <select name="per_minute" class="input"><option value="10"><?= e(t('هادية: 10 في الدقيقة')) ?></option><option value="20" selected><?= e(t('عادية: 20 في الدقيقة (موصى به)')) ?></option><option value="40"><?= e(t('سريعة: 40 في الدقيقة')) ?></option></select></label>
          <p class="text-[11px] text-ink-400"><?= e(t('الرسايل بتتبعت من طابور على دفعات، عشان الخدمة ماتقفش. تقدر توقفها أو تلغيها في أي وقت.')) ?></p>
        </div>
        <div class="flex items-center gap-2 px-5 py-3 border-t border-ink-200 shrink-0">
          <span id="camp-err" class="text-xs text-red-600"></span>
          <button type="button" data-camp-close class="ms-auto btn-ghost"><?= e(t('إلغاء')) ?></button>
          <button type="submit" id="camp-go" class="btn-primary"><?= e(t('ابدأ الحملة')) ?></button>
        </div>
      </form>
    </dialog>

    <!-- الحملات الشغالة -->
    <div id="camp-dock" class="hidden fixed bottom-5 start-1/2 -translate-x-1/2 rtl:translate-x-1/2 lg:start-auto lg:end-20 lg:translate-x-0 rtl:lg:translate-x-0 z-40 w-[min(380px,92vw)] rounded-2xl border border-ink-200 bg-white shadow-2xl"></div>
    <script>window.IBX = <?= json_encode(['n' => t('%s محادثة'), 'sel' => t('محدد'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'), 'done' => t('تم: %s'), 'started' => t('بدأت الحملة: %s محادثة'), 'skipped' => t('اتستبعد %s'),
        'campaigns' => t('الحملات'), 'running' => t('شغالة'), 'paused' => t('متوقفة'), 'done2' => t('خلصت'), 'cancelled' => t('اتلغت'), 'pause' => t('إيقاف مؤقت'), 'resume' => t('كمّل'), 'cancel' => t('إلغاء'),
        'sent' => t('اتبعت'), 'failed' => t('فشل'), 'left' => t('فاضل'), 'eta' => t('حوالي %s دقيقة'), 'confirmCancel' => t('إلغاء الحملة؟ الرسايل اللي اتبعتت مش هترجع.'), 'pickFirst' => t('حدد محادثة واحدة على الأقل.')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/inbox.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
