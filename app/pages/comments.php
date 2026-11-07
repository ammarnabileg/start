<?php
$err = null;
$z = zernio_or_null($err);
$fxd = getenv('UI_FIXTURES');
if ($fxd && is_file($fxd . '/inbox_fake_z.php')) $z = require $fxd . '/inbox_fake_z.php'; // اختبارات بس
$postId = (string)($_GET['post'] ?? '');
$accId = (string)($_GET['a'] ?? '');
$cursor = (string)($_GET['cursor'] ?? '');

// أدوات الفريق (ردود محفوظة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos((string)($_POST['action'] ?? ''), 'reply_') === 0) team_post((string)$_POST['action']);

// #39 #40 #41: إعدادات الإخفاء التلقائي، ردود المنشور، الأكثر تفاعلًا
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['c_spamcfg', 'c_spamrun', 'c_preps', 'c_fans'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $J = function (array $d, int $c = 200) { http_response_code($c); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    $act = (string)$_POST['action'];
    if ($act === 'c_fans') {
        if (!$z) $J(['error' => $err ?: t('طلب غير صحيح.')], 422);
        $by = []; $r = $z->commentedPosts(['limit' => 8, 'profileId' => zernio_profile()]);
        foreach ((array)($r['data'] ?? []) as $p) { try { $by[(string)$p['id']] = array_map(function ($c) use ($p) { return $c + ['platform' => $p['platform'] ?? '']; }, (array)($z->comments((string)$p['id'], (string)$p['accountId'])['comments'] ?? [])); } catch (Throwable $e) {} }
        $J(['ok' => true, 'fans' => cm_fans($by), 'posts' => count($by)]);
    }
    if (!can('comments.reply')) $J(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
    if ($act === 'c_preps') { if ($postId === '') $J(['error' => t('طلب غير صحيح.')], 422); post_replies_save($postId, (array)($_POST['list'] ?? [])); $J(['ok' => true, 'list' => post_replies($postId)]); }
    if ($act === 'c_spamcfg') {
        save_setting('cm_spam_' . (int)ws('id'), json_encode(['on' => !empty($_POST['on']) ? 1 : 0, 'links' => !empty($_POST['links']) ? 1 : 0, 'words' => mb_substr((string)($_POST['words'] ?? ''), 0, 2000)], JSON_UNESCAPED_UNICODE));
        log_activity('comment.autohide_cfg', !empty($_POST['on']) ? 'on' : 'off');
        $J(['ok' => true]);
    }
    if (!$z) $J(['error' => $err ?: t('طلب غير صحيح.')], 422);
    $cfg = cm_spam_cfg(); $cfg['on'] = 1;
    $GLOBALS['__settings']['cm_spam_' . (int)ws('id')] = json_encode($cfg);
    try { $n = cm_autohide_tick($z, 8); } catch (Throwable $e) { $J(['error' => $e->getMessage()], 502); }
    $J(['ok' => true, 'done' => $n]);
}

// إجراءات على تعليقات محددة (JSON): تصنيف، إخفاء، رد على كل واحد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['c_classify', 'c_hide', 'c_reply'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $J = function (array $d, int $c = 200) { http_response_code($c); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    if (!$z || !$postId || !$accId) $J(['error' => $err ?: t('طلب غير صحيح.')], 422);
    $wid = (int)ws('id');
    $ids = array_values(array_filter(array_map('strval', (array)($_POST['ids'] ?? [])), function ($x) { return preg_match('/^[\w.:@-]{1,160}$/', $x); }));
    $act = (string)$_POST['action'];
    if ($act === 'c_classify') {
        if (!ai_connection() || !can('ai.use') || !ai_feature_on('classify_btn')) $J(['error' => t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')], 403);
        $all = [];
        $walk = function ($list) use (&$walk, &$all) { foreach ($list as $c) { $all[(string)$c['id']] = $c; $walk($c['replies'] ?? []); } };
        $walk($z->comments($postId, $accId)['comments'] ?? []);
        $todo = array_slice(array_values(array_filter(array_intersect_key($all, array_flip($ids)), function ($c) { return empty($c['from']['isOwner']) && trim((string)($c['message'] ?? '')) !== ''; })), 0, 40);
        if (!$todo) $J(['ok' => true, 'done' => 0]);
        $lines = [];
        foreach ($todo as $i => $c) $lines[] = ($i + 1) . '. ' . str_replace("\n", ' ', mb_substr((string)$c['message'], 0, 300));
        try {
            $r = ai_chat([['role' => 'system', 'content' => 'Classify each public social-media comment. Return ONLY a JSON array, one object per comment in order: {"n":number,"category":"inquiry|complaint|purchase|support|spam|other","sentiment":"positive|neutral|upset|angry","urgency":1-3,"intent":true|false,"summary":"max 6 words in the comment language"}. intent = asks price/availability or wants to buy. spam = ads, links, abuse.'],
                ['role' => 'user', 'content' => implode("\n", $lines)]], 1200, 'classify');
        } catch (Throwable $e) { $J(['error' => $e->getMessage()], 502); }
        $j = $r['text']; if (preg_match('/\[.*\]/s', $j, $m)) $j = $m[0];
        $arr = json_decode($j, true) ?: [];
        $st = db()->prepare("INSERT INTO ai_labels (workspace_id, item_key, category, sentiment, urgency, intent, summary, src_hash, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, '', NOW()) ON DUPLICATE KEY UPDATE category = VALUES(category), sentiment = VALUES(sentiment), urgency = VALUES(urgency), intent = VALUES(intent), summary = VALUES(summary), updated_at = NOW()");
        $n = 0;
        foreach ($arr as $k => $o) {
            $idx = (int)($o['n'] ?? ($k + 1)) - 1; if (!isset($todo[$idx])) continue;
            $st->execute([$wid, 'c:' . $todo[$idx]['id'], in_array($o['category'] ?? '', ['inquiry', 'complaint', 'purchase', 'support', 'spam', 'other'], true) ? $o['category'] : 'other',
                in_array($o['sentiment'] ?? '', ['positive', 'neutral', 'upset', 'angry'], true) ? $o['sentiment'] : 'neutral', max(1, min(3, (int)($o['urgency'] ?? 1))), !empty($o['intent']) ? 1 : 0, mb_substr((string)($o['summary'] ?? ''), 0, 250)]);
            $n++;
        }
        $J(['ok' => true, 'done' => $n]);
    }
    if (!can('comments.reply')) $J(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], 403);
    if ($act === 'c_hide') {
        $ok = 0; foreach ($ids as $cid) { try { $z->hideComment($postId, $cid, $accId); $ok++; } catch (Throwable $e) {} }
        log_activity('comment.hide_bulk', $postId . ' ' . $ok);
        $J(['ok' => true, 'done' => $ok]);
    }
    // رد على تعليق واحد (الواجهة بتنادي ده لكل تعليق محدد ورا بعض، بفاصل زمني)
    $cid = $ids[0] ?? '';
    $text = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 2000);
    if ($cid === '' || $text === '') $J(['error' => t('اكتب الرد الأول.')], 422);
    $name = trim(explode(' ', trim((string)($_POST['name'] ?? '')))[0] ?? '');
    $text = trim(str_replace(['{name}', '{الاسم}'], $name, $text));
    if (!empty($_POST['vary']) && ai_connection()) {
        try {
            $r = ai_chat([['role' => 'system', 'content' => 'Rewrite this public reply to a social media comment with different wording so repeated replies do not look automated. Keep the meaning, facts, links and roughly the same length. Address the commenter naturally. Same language/dialect. Return ONLY the reply.'],
                ['role' => 'user', 'content' => "Reply:\n" . $text . "\n\nTheir comment:\n" . mb_substr((string)($_POST['comment'] ?? ''), 0, 400)]], 300, 'reply');
            $text = trim($r['text'], " \n\"'«»") ?: $text;
        } catch (Throwable $e) {}
    }
    try { $z->replyComment($postId, $accId, $text, $cid); } catch (Throwable $e) { $J(['error' => $e->getMessage()], 502); }
    log_activity('comment.reply_bulk', $cid);
    $J(['ok' => true, 'text' => $text]);
}

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

/** تعليق واحد: مربع تحديد، صورة، الاسم والوقت، النص، علامات الذكاء الاصطناعي، والإجراءات */
function comment_block(array $c, array $labels, bool $isReply = false): void
{
    $from = $c['from'] ?? [];
    $mine = !empty($from['isOwner']);
    $lb = $labels['c:' . ($c['id'] ?? '')] ?? null;
    $nm = (string)($from['name'] ?? $from['username'] ?? t('مستخدم'));
    $answered = (bool)array_filter($c['replies'] ?? [], function ($r) { return !empty($r['from']['isOwner']); });
    $catL = ['inquiry' => t('استفسار'), 'complaint' => t('شكوى'), 'purchase' => t('طلب شراء'), 'support' => t('دعم'), 'spam' => t('سبام'), 'other' => t('أخرى')];
    ?>
    <div class="cm-item group flex gap-2.5 <?= $isReply ? 'ms-11 mt-2.5' : '' ?>" data-id="<?= e($c['id'] ?? '') ?>" data-name="<?= e($nm) ?>" data-mine="<?= $mine ? 1 : 0 ?>" data-hidden="<?= !empty($c['isHidden']) ? 1 : 0 ?>" data-answered="<?= $answered || $isReply ? 1 : 0 ?>"
      data-cat="<?= e($lb['category'] ?? '') ?>" data-intent="<?= (int)($lb['intent'] ?? 0) ?>" data-bad="<?= $lb && (in_array($lb['sentiment'], ['angry', 'upset'], true) || in_array($lb['category'], ['complaint', 'spam'], true)) ? 1 : 0 ?>">
      <?php if (!$mine): ?><label class="pt-2.5 cursor-pointer"><input type="checkbox" class="cm-sel w-4 h-4 rounded opacity-0 group-hover:opacity-100 checked:opacity-100 focus:opacity-100 transition" aria-label="<?= e($nm) ?>"></label><?php else: ?><span class="w-4"></span><?php endif; ?>
      <?= !empty($c['platform']) && !$isReply ? avatar_with_platform($from['picture'] ?? null, $nm, (string)$c['platform'], 'w-9 h-9') : avatar($from['picture'] ?? null, $nm, 'w-8 h-8') ?>
      <div class="flex-1 min-w-0">
        <div class="inline-block max-w-full rounded-2xl rounded-ss-md px-3.5 py-2 shadow-sm <?= $mine ? 'bg-brand-50 border border-brand-200' : 'bg-white border border-ink-200' ?> <?= !empty($c['isHidden']) ? 'opacity-50' : '' ?>">
          <div class="flex items-center gap-2 text-xs"><b class="text-ink-900"><?= e($nm) ?></b><?php if ($mine): ?><span class="rounded-md bg-brand-600 text-[#fff] px-1.5 text-[10px]"><?= e(t('أنت')) ?></span><?php endif; ?><span class="text-ink-400"><?= e(fmt_date($c['createdTime'] ?? null, 'd/m h:i A')) ?></span></div>
          <p class="text-sm text-ink-800 whitespace-pre-line break-words mt-0.5" dir="auto"><?= e($c['message'] ?? '') ?></p>
        </div>
        <?php if ($lb || !empty($c['isHidden'])): ?>
          <div class="flex flex-wrap gap-1 mt-1.5 ps-1" title="<?= e($lb['summary'] ?? '') ?>">
            <?php if (!empty($c['isHidden'])): ?><span class="rounded-md bg-ink-100 text-ink-500 px-1.5 py-0.5 text-[10px]"><?= e(t('مخفي')) ?></span><?php endif; ?>
            <?php if ($lb): ?>
              <?php if ($lb['intent']): ?><span class="rounded-md bg-lime-100 text-lime-700 px-1.5 py-0.5 text-[10px] font-medium"><?= e(t('نية شراء')) ?></span><?php endif; ?>
              <span class="rounded-md px-1.5 py-0.5 text-[10px] <?= $lb['category'] === 'spam' ? 'bg-red-100 text-red-700' : ($lb['category'] === 'complaint' ? 'bg-amber-100 text-amber-700' : 'bg-ink-100 text-ink-600') ?>"><?= e($catL[$lb['category']] ?? $lb['category']) ?></span>
              <?php if (in_array($lb['sentiment'], ['angry', 'upset'], true)): ?><span class="rounded-md bg-amber-100 text-amber-700 px-1.5 py-0.5 text-[10px]"><?= $lb['sentiment'] === 'angry' ? e(t('غاضب')) : e(t('متضايق')) ?></span><?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="flex flex-wrap items-center gap-3 text-[11px] text-ink-500 mt-1 ps-1 opacity-70 group-hover:opacity-100 transition">
          <?php if (!$isReply && ($c['canReply'] ?? true)): ?><button type="button" class="font-semibold text-brand-700 hover:underline" data-reply-to="<?= e($c['id']) ?>" data-reply-name="<?= e($from['name'] ?? '') ?>"><?= e(t('رد')) ?></button><?php endif; ?>
          <?php if (!empty($c['canHide']) && empty($c['isHidden'])): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="hide"><input type="hidden" name="comment_id" value="<?= e($c['id']) ?>"><button class="hover:text-ink-900"><?= e(t('إخفاء')) ?></button></form>
          <?php endif; ?>
          <?php if (!empty($c['canDelete'])): ?>
            <form method="post" class="inline" onsubmit="return confirm('<?= tjs('حذف التعليق نهائياً؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="comment_id" value="<?= e($c['id']) ?>"><button class="hover:text-red-600"><?= e(t('حذف')) ?></button></form>
          <?php endif; ?>
          <?php if (!empty($c['url'])): ?><a href="<?= e($c['url']) ?>" target="_blank" rel="noopener" class="hover:text-ink-900"><?= e(t('فتح ↗')) ?></a><?php endif; ?>
          <?php if (!$mine && module_enabled('crm') && can('crm.view')): $cext = 'cm:' . (string)($c['platform'] ?? $GLOBALS['__cm_pf'] ?? '') . ':' . (string)($from['id'] ?? $from['username'] ?? $nm); $cl = crm_lead_by_ext($cext); ?>
            <?php if ($cl): ?><a class="text-lime-700 hover:underline" data-lead-pop="<?= (int)$cl['id'] ?>" href="<?= e(url('prospects', ['lead' => (int)$cl['id']])) ?>"><?= ui_icon('magnet', 'w-3.5 h-3.5 inline-block align-[-2px]') ?> <?= e(t('في الـ Leads')) ?></a>
            <?php else: ?><span data-tolead data-source="comments" data-ext="<?= e($cext) ?>" data-name="<?= e($nm) ?>" data-text="<?= e(mb_substr((string)($c['message'] ?? ''), 0, 600)) ?>" data-where="<?= e(t('تعليق')) ?>" data-url="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>"><button type="button" data-go class="font-semibold <?= !empty($lb['intent']) ? 'text-lime-700' : 'hover:text-ink-900' ?>"><?= ui_icon('magnet', 'w-3.5 h-3.5 inline-block align-[-2px]') ?> <?= e(t('ضيفه Lead')) ?></button></span><?php endif; ?>
          <?php endif; ?>
        </div>
        <?php foreach ($c['replies'] ?? [] as $r) comment_block($r, $labels, true); ?>
      </div>
    </div>
    <?php
}

render(t('التعليقات'), 'comments', function () use ($z, $posts, $next, $comments, $current, $postId, $accId, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); }
    $aiOn = ai_connection() && can('ai.use');
    $labels = ($aiOn && ai_feature_on('classify_btn')) ? ai_labels((int)ws('id')) : [];
    $pf = preg_replace('/[^a-z]/', '', (string)($_GET['pf'] ?? ''));
    $plats = [];
    foreach ($posts as $pp) { $k = (string)($pp['platform'] ?? ''); if ($k !== '') $plats[$k] = ($plats[$k] ?? 0) + (int)($pp['commentCount'] ?? 0); }
    if ($pf !== '') $posts = array_values(array_filter($posts, function ($pp) use ($pf) { return ($pp['platform'] ?? '') === $pf; }));
    $count = function ($list) use (&$count) { $n = 0; foreach ($list as $c) { $n++; $n += $count($c['replies'] ?? []); } return $n; };
    $totalC = $count($comments);
    $hiddenC = count(array_filter($comments, function ($c) { return !empty($c['isHidden']); }));
    $stats = ['intent' => 0, 'complaint' => 0, 'spam' => 0, 'done' => 0];
    $walk = function ($list) use (&$walk, &$stats, $labels) { foreach ($list as $c) { $lb = $labels['c:' . ($c['id'] ?? '')] ?? null; if ($lb) { $stats['done']++; if ($lb['intent']) $stats['intent']++; if ($lb['category'] === 'complaint') $stats['complaint']++; if ($lb['category'] === 'spam') $stats['spam']++; } $walk($c['replies'] ?? []); } };
    $walk($comments);
    // #43 سؤال السعر + #44 قايمة المعلّقين للسحب
    $priceN = 0; $pool = [];
    $pw = function ($list) use (&$pw, &$priceN, &$pool) { foreach ($list as $c) { if (empty($c['from']['isOwner'])) { if (cm_is_price_q((string)($c['message'] ?? ''))) $priceN++; $k = (string)($c['from']['id'] ?? $c['from']['username'] ?? $c['from']['name'] ?? ''); if ($k !== '' && !isset($pool[$k])) $pool[$k] = ['k' => $k, 'n' => (string)($c['from']['name'] ?? $c['from']['username'] ?? ''), 'm' => []]; if ($k !== '') $pool[$k]['m'][] = mb_substr((string)($c['message'] ?? ''), 0, 300); } $pw($c['replies'] ?? []); } };
    $pw($comments);
    $preps = $postId ? post_replies($postId) : []; $spam = cm_spam_cfg();
    ?>
    <div data-live-root<?= $apiError ? ' data-live-error' : '' ?>>
    <div class="grid lg:grid-cols-[23rem_1fr] 2xl:grid-cols-[23rem_1fr_19rem] gap-4 h-[calc(100vh-11rem)] min-h-[560px]" id="cmx">
      <!-- المنشورات -->
      <div class="card flex flex-col min-h-0 overflow-hidden <?= $postId ? 'hidden lg:flex' : 'flex' ?>">
        <div class="flex items-center gap-2 px-3 h-12 border-b border-ink-200 shrink-0">
          <b class="text-sm text-ink-900"><?= e(t('المنشورات')) ?></b><span class="text-xs text-ink-400"><?= count($posts) ?></span>
          <?php if (count($plats) > 1 || $pf !== ''): ?>
            <div class="ms-auto flex gap-1 overflow-x-auto" style="scrollbar-width:none">
              <a href="<?= e(url('comments')) ?>" class="h-8 px-2.5 rounded-lg text-xs flex items-center whitespace-nowrap <?= $pf === '' ? 'bg-brand-50 text-brand-700 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>"><?= e(t('الكل')) ?></a>
              <?php foreach ($plats as $k => $cnt): ?><a href="<?= e(url('comments', ['pf' => $k])) ?>" class="h-8 px-2 rounded-lg flex items-center gap-1 text-xs <?= $pf === $k ? 'bg-brand-50 text-brand-700 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>" title="<?= e(platform_label($k)) ?>"><?= platform_icon($k, 'w-4 h-4') ?><span><?= (int)$cnt ?></span></a><?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div data-live="list" class="flex-1 min-h-0 overflow-y-auto">
          <?php if (!$posts): ?><div class="p-10 text-center"><div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-ink-100 text-ink-400 flex items-center justify-center"><?= ui_icon('chat', 'w-6 h-6') ?></div><p class="text-sm text-ink-500"><?= e(t('مفيش منشورات عليها تعليقات.')) ?></p></div><?php endif; ?>
          <?php $lpp = module_enabled('crm') && can('crm.view') ? crm_leads_per_post() : []; foreach ($posts as $p): $on = $p['id'] === $postId; $cc = (int)($p['commentCount'] ?? 0); $ln = (int)($lpp[(string)$p['id']] ?? 0); ?>
            <a href="<?= e(url('comments', array_filter(['post' => $p['id'], 'a' => $p['accountId'], 'pf' => $pf]))) ?>" class="relative flex gap-3 p-3 border-b border-ink-100 transition <?= $on ? 'bg-brand-50/70' : 'hover:bg-ink-50' ?>">
              <?php if ($on): ?><span class="absolute inset-y-0 start-0 w-1 bg-brand-600 rounded-e"></span><?php endif; ?>
              <span class="relative shrink-0">
                <?php if (!empty($p['picture'])): ?><img src="<?= e($p['picture']) ?>" class="w-14 h-14 rounded-xl object-cover bg-ink-100" loading="lazy" referrerpolicy="no-referrer" alt="">
                <?php else: ?><span class="w-14 h-14 rounded-xl bg-ink-100 flex items-center justify-center"><?= platform_icon((string)($p['platform'] ?? ''), 'w-6 h-6') ?></span><?php endif; ?>
                <span class="absolute -bottom-1 -end-1"><?= platform_icon((string)($p['platform'] ?? ''), 'w-[18px] h-[18px]', 'badge') ?></span>
              </span>
              <div class="flex-1 min-w-0">
                <p class="text-sm line-clamp-2 <?= $on ? 'text-ink-900 font-medium' : 'text-ink-800' ?>" dir="auto"><?= ($p['content'] ?? '') !== '' ? e(mb_substr((string)$p['content'], 0, 140)) : '<span class="text-ink-400">' . e(t('(منشور بدون نص)')) . '</span>' ?></p>
                <div class="flex items-center gap-2 mt-1.5 text-[11px] text-ink-500">
                  <span class="inline-flex items-center gap-1 rounded-md bg-ink-100 px-1.5 py-0.5 text-ink-700 font-medium"><?= ui_icon('chat', 'w-3 h-3') ?><?= $cc ?></span>
                  <?php if ($ln): ?><span class="rounded-md bg-lime-100 text-lime-800 px-1.5 py-0.5 font-semibold" title="<?= e(t('عملاء محتملين جم من المنشور ده')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3v8a6 6 0 0012 0V3h-4v8a2 2 0 01-4 0V3zM6 7h4M14 7h4"/></svg> <?= $ln ?></span><?php endif; ?>
                  <span><?= e(fmt_date($p['createdTime'] ?? null, 'd/m/Y')) ?></span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if ($next): ?><a href="<?= e(url('comments', ['cursor' => $next])) ?>" class="block text-center p-3 text-sm link"><?= e(t('أقدم')) ?></a><?php endif; ?>
        </div>
      </div>

      <!-- التعليقات -->
      <div class="card flex flex-col min-h-0 overflow-hidden <?= $postId ? 'flex' : 'hidden lg:flex' ?>">
        <?php if (!$postId): ?>
          <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
            <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-brand-600/10 to-cyan-500/10 text-brand-600 flex items-center justify-center mb-4"><?= ui_icon('chat', 'w-8 h-8') ?></div>
            <p class="font-medium text-ink-900"><?= e(t('اختار منشور عشان تشوف تعليقاته')) ?></p>
            <p class="text-xs text-ink-500 mt-1"><?= e(t('تقدر تحدد كذا تعليق وتخفيهم أو ترد عليهم مرة واحدة.')) ?></p>
          </div>
        <?php else: ?>
          <div class="px-4 h-16 border-b border-ink-200 flex items-center gap-3 shrink-0">
            <a href="<?= e(url('comments')) ?>" class="lg:hidden p-2 -ms-2 rounded-lg hover:bg-ink-100"><svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></a>
            <?php if (!empty($current['picture'])): ?><img src="<?= e($current['picture']) ?>" class="w-10 h-10 rounded-lg object-cover bg-ink-100" referrerpolicy="no-referrer" alt=""><?php endif; ?>
            <div class="min-w-0 flex-1"><p class="text-sm font-medium text-ink-900 line-clamp-1" dir="auto"><?= e(mb_substr((string)($current['content'] ?? ''), 0, 160) ?: t('(منشور بدون نص)')) ?></p>
              <p class="text-[11px] text-ink-500"><?= e(platform_label((string)($current['platform'] ?? ''))) ?> · <?= e(t('%s تعليق', $totalC)) ?><?php if ($priceN): ?> · <span class="rounded-md bg-lime-100 text-lime-800 px-1.5 font-semibold" title="<?= e(t('تعليقات بتسأل عن السعر')) ?>"><?= ui_icon('money', 'w-3.5 h-3.5 inline-block align-[-2px]') ?> <?= e(t('%s سألوا عن السعر', $priceN)) ?></span><?php endif; ?></p></div>
            <div class="flex items-center gap-1">
              <?php foreach (['rep' => ['chat', t('ردود المنشور')], 'fans' => ['star', t('الأكثر تفاعلًا')], 'give' => ['trophy', t('سحب على هدية')], 'spam' => ['ban', t('إخفاء تلقائي')]] as $tk => [$ti, $tl]): ?>
                <button type="button" popovertarget="cmp-<?= $tk ?>" class="tb w-9 !h-9 justify-center <?= $tk === 'spam' && !empty($spam['on']) ? '!text-lime-700 !border-lime-300' : '' ?>" title="<?= e($tl) ?>" aria-label="<?= e($tl) ?>"><?= ui_icon($ti, 'w-4 h-4') ?></button>
              <?php endforeach; ?>
              <?php if ($aiOn): ?><button type="button" class="tb w-9 !h-9 justify-center text-brand-600" title="<?= e(t('لخّص في 3 سطور')) ?>" data-cp-insight-chat="<?= e(t('لخّص التعليقات على المنشور ده: الناس قالت إيه، مين عايز يشتري، وإيه الشكاوي.')) ?>"><?= ui_icon('spark', 'w-4 h-4') ?></button><?php endif; ?>
            </div>
            <?php if (!empty($current['permalink'])): ?><a href="<?= e($current['permalink']) ?>" target="_blank" rel="noopener" class="tb w-9 !h-9 justify-center" title="<?= e(t('فتح المنشور ↗')) ?>"><svg class="w-4 h-4 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1h5"/></svg></a><?php endif; ?>
          </div>
          <script type="application/json" id="cmt-json"><?= json_encode(['preps' => $preps, 'pool' => array_values($pool), 'post' => $postId, 'a' => $accId], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
          <div id="cmp-rep" popover class="m-0 w-[min(440px,94vw)] max-h-[70vh] overflow-y-auto rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl text-sm space-y-2">
            <div class="flex items-center gap-2 font-semibold text-ink-900"><span class="w-7 h-7 rounded-lg bg-brand-600 text-[#fff] grid place-items-center"><?= ui_icon('chat', 'w-4 h-4') ?></span><?= e(t('ردود المنشور')) ?></div>
            <p class="text-[11px] text-ink-500"><?= e(t('ردود جاهزة للمنشور ده بس (السعر، العنوان، طريقة الطلب). دوس على الرد يتحط في خانة الكتابة.')) ?></p>
                <div id="cm-preps" class="flex flex-wrap gap-1.5"></div>
                <div class="flex gap-1.5"><input id="cm-prep-in" class="input !mt-0 !py-1.5 text-sm flex-1" placeholder="<?= e(t('رد جديد للمنشور ده…')) ?>" dir="auto"><button type="button" id="cm-prep-add" class="btn-ghost !py-1.5 !text-xs">+ <?= e(t('إضافة')) ?></button></div></div>
          <div id="cmp-fans" popover class="m-0 w-[min(440px,94vw)] max-h-[70vh] overflow-y-auto rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl text-sm space-y-2">
            <div class="flex items-center gap-2 font-semibold text-ink-900"><span class="w-7 h-7 rounded-lg bg-brand-600 text-[#fff] grid place-items-center"><?= ui_icon('star', 'w-4 h-4') ?></span><?= e(t('الأكثر تفاعلًا')) ?></div>
            <p class="text-[11px] text-ink-500"><?= e(t('من آخر 8 منشورات عليها تعليقات: مين علّق أكتر، على كام منشور، وسأل عن السعر كام مرة.')) ?></p>
                <button type="button" id="cm-fans-go" class="btn-ghost !py-1.5 !text-xs"><?= e(t('هات القايمة')) ?></button><div id="cm-fans" class="divide-y divide-ink-100"></div></div>
          <div id="cmp-give" popover class="m-0 w-[min(440px,94vw)] max-h-[70vh] overflow-y-auto rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl text-sm space-y-2">
            <div class="flex items-center gap-2 font-semibold text-ink-900"><span class="w-7 h-7 rounded-lg bg-brand-600 text-[#fff] grid place-items-center"><?= ui_icon('trophy', 'w-4 h-4') ?></span><?= e(t('سحب على هدية')) ?></div>
            <p class="text-[11px] text-ink-500"><?= e(t('سحب عشوائي عادل من الناس اللي علّقوا هنا (كل شخص فرصة واحدة مهما علّق). تقدر تشترط كلمة أو منشن.')) ?></p>
                <div class="flex flex-wrap items-center gap-2 text-xs"><input id="gv-kw" class="input !mt-0 !py-1.5 !w-44 text-xs" placeholder="<?= e(t('لازم التعليق يكون فيه… (اختياري)')) ?>" dir="auto"><label class="flex items-center gap-1"><input type="checkbox" id="gv-tag"> <?= e(t('لازم يعمل منشن لحد')) ?></label><label class="flex items-center gap-1"><?= e(t('عدد الفايزين')) ?> <input type="number" id="gv-n" min="1" max="20" value="1" class="input !mt-0 !py-1 !w-16 text-xs"></label><button type="button" id="gv-go" class="btn-primary !py-1.5 !text-xs"><?= ui_icon('trophy', 'w-3.5 h-3.5 inline-block align-[-2px]') ?> <?= e(t('اسحب')) ?></button></div>
                <div id="gv-out" class="text-sm"></div></div>
          <div id="cmp-spam" popover class="m-0 w-[min(440px,94vw)] max-h-[70vh] overflow-y-auto rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl text-sm space-y-2">
            <div class="flex items-center gap-2 font-semibold text-ink-900"><span class="w-7 h-7 rounded-lg bg-brand-600 text-[#fff] grid place-items-center"><?= ui_icon('ban', 'w-4 h-4') ?></span><?= e(t('إخفاء تلقائي')) ?></div>
            <p class="text-[11px] text-ink-500"><?= e(t('كل ساعة بنلف على آخر المنشورات ونخفي التعليقات اللي فيها شتايم أو لينكات سبام. التعليق بيتخفي مش بيتمسح، وتقدر ترجّعه من المنصة.')) ?></p>
                <label class="flex items-center gap-2"><input type="checkbox" id="sp-on" <?= !empty($spam['on']) ? 'checked' : '' ?>> <b><?= e(t('شغّل الإخفاء التلقائي')) ?></b></label>
                <label class="flex items-center gap-2 text-xs"><input type="checkbox" id="sp-links" <?= !empty($spam['links']) ? 'checked' : '' ?>> <?= e(t('اخفي التعليقات اللي فيها لينكات (تليجرام، واتساب، bit.ly…)')) ?></label>
                <textarea id="sp-words" rows="2" class="input text-xs" dir="auto" placeholder="<?= e(t('كلمات زيادة تتخفي، مفصولة بفاصلة')) ?>"><?= e((string)$spam['words']) ?></textarea>
                <div class="flex gap-2"><button type="button" id="sp-save" class="btn-ghost !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button><button type="button" id="sp-run" class="btn-ghost !py-1.5 !text-xs"><?= ui_icon('play', 'w-3.5 h-3.5 inline-block align-[-2px]') ?> <?= e(t('طبّق دلوقتي')) ?></button></div></div>
          <div class="flex items-center gap-2 px-3 h-11 border-b border-ink-100 shrink-0 text-xs">
            <label class="flex items-center gap-2 text-ink-600 cursor-pointer select-none"><input type="checkbox" id="cm-all" class="w-4 h-4 rounded"><span id="cm-count"><?= e(t('تحديد الكل')) ?></span></label>
            <div class="ms-auto flex items-center gap-1">
              <?php if ($labels !== [] || ($aiOn && ai_feature_on('classify_btn'))): ?>
                <button type="button" data-cm-filter="intent" class="cm-f h-8 px-2.5 rounded-lg text-ink-600 hover:bg-ink-100"><?= e(t('نية شراء')) ?></button>
                <button type="button" data-cm-filter="bad" class="cm-f h-8 px-2.5 rounded-lg text-ink-600 hover:bg-ink-100"><?= e(t('شكاوي وسبام')) ?></button>
              <?php endif; ?>
              <button type="button" data-cm-filter="unanswered" class="cm-f h-8 px-2.5 rounded-lg text-ink-600 hover:bg-ink-100"><?= e(t('من غير رد')) ?></button>
            </div>
          </div>
          <div class="flex-1 min-h-0 overflow-y-auto px-4 py-4 space-y-4 bg-ink-50/70" id="thread" data-comments style="background-image:radial-gradient(rgb(var(--ink-200)/.5) 1px,transparent 1px);background-size:18px 18px">
            <?php if (!$comments): ?><p class="text-sm text-ink-500 text-center py-8"><?= e(t('مفيش تعليقات.')) ?></p><?php endif; ?>
            <?php foreach ($comments as $c) comment_block($c, $labels); ?>
          </div>
          <!-- شريط الإجراءات على المحدد -->
          <div id="cm-bar" class="hidden shrink-0 border-t border-ink-200 bg-white px-3 py-2 flex flex-wrap items-center gap-1.5">
            <b class="text-xs text-ink-900" id="cm-n">0</b><span class="text-xs text-ink-500 me-2"><?= e(t('محدد')) ?></span>
            <?php if ($aiOn && ai_feature_on('classify_btn')): ?><button type="button" data-cm-op="classify" class="cm-op btn-ghost !py-1.5 !text-xs"><?= ai_icon('spark', 'w-3.5 h-3.5 text-brand-600') ?><?= e(t('تصنيف')) ?></button><?php endif; ?>
            <button type="button" data-cm-op="hide" class="cm-op btn-ghost !py-1.5 !text-xs"><?= e(t('إخفاء')) ?></button>
            <button type="button" data-cm-op="reply" class="cm-op btn-primary !py-1.5 !text-xs"><?= e(t('رد على المحدد')) ?></button>
            <button type="button" id="cm-clear" class="ms-auto link text-xs !text-ink-500"><?= e(t('إلغاء التحديد')) ?></button>
          </div>
          <form method="post" class="m-3 mt-2 rounded-2xl border border-ink-200 bg-white focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-500/10 transition p-1.5" id="replyForm" data-optimistic="comment">
            <?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="comment_id" id="replyTo" value="">
            <div id="replyHint" class="hidden text-xs text-brand-700 px-2 pt-1"><?= e(t('بترد على:')) ?> <span class="font-semibold"></span> <button type="button" id="cancelReply" class="text-ink-500 underline ms-2"><?= e(t('إلغاء')) ?></button></div>
            <div class="flex gap-1.5 items-end">
              <textarea data-saved-replies data-ai="reply_variants,polish_reply,comments_triage,summarize,translate_ctx" name="message" rows="1" class="input !mt-0 flex-1 text-sm !border-0 !ring-0 !shadow-none max-h-40" placeholder="<?= e(t('اكتب تعليق على المنشور، أو دوس (رد) على تعليق معين')) ?>" data-enter-submit></textarea>
              <?php if ($aiOn && ai_feature_on('reply_btn')): ?><button type="button" data-ai-task="reply" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl border border-brand-200 bg-brand-50 px-2.5 py-2.5 text-xs font-medium text-brand-700 hover:bg-brand-100 transition disabled:opacity-60" title="<?= e(t('اقتراح رد')) ?>"><?= ai_icon('spark', 'w-4 h-4') ?><span><?= e(t('اقتراح رد')) ?></span></button><?php endif; ?>
              <button class="btn-primary !py-2.5"><?= e(t('إرسال')) ?></button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <!-- تفاصيل المنشور -->
      <aside class="hidden 2xl:flex card flex-col min-h-0 overflow-y-auto">
        <?php if ($postId && $current): ?>
          <?php if (!empty($current['picture'])): ?><img src="<?= e($current['picture']) ?>" class="w-full aspect-video object-cover bg-ink-100" referrerpolicy="no-referrer" alt=""><?php endif; ?>
          <div class="p-4 space-y-4 text-sm">
            <div class="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-2.5 py-1 text-[11px] text-ink-600"><?= platform_icon((string)($current['platform'] ?? ''), 'w-3.5 h-3.5') ?><?= e(platform_label((string)($current['platform'] ?? ''))) ?> · <?= e(fmt_date($current['createdTime'] ?? null, 'd/m/Y')) ?></div>
            <p class="text-ink-800 whitespace-pre-line leading-relaxed line-clamp-[10]" dir="auto"><?= e((string)($current['content'] ?? '')) ?></p>
            <div class="grid grid-cols-2 gap-2">
              <div class="rounded-xl bg-ink-50 border border-ink-200 p-2.5"><div class="text-[10px] text-ink-500"><?= e(t('التعليقات')) ?></div><div class="font-semibold text-ink-900"><?= $totalC ?></div></div>
              <div class="rounded-xl bg-ink-50 border border-ink-200 p-2.5"><div class="text-[10px] text-ink-500"><?= e(t('مخفي')) ?></div><div class="font-semibold text-ink-900"><?= $hiddenC ?></div></div>
              <?php if ($stats['done']): ?>
                <div class="rounded-xl bg-lime-50 border border-lime-200 p-2.5"><div class="text-[10px] text-lime-700"><?= e(t('نية شراء')) ?></div><div class="font-semibold text-lime-800"><?= $stats['intent'] ?></div></div>
                <div class="rounded-xl bg-amber-50 border border-amber-200 p-2.5"><div class="text-[10px] text-amber-700"><?= e(t('شكاوي وسبام')) ?></div><div class="font-semibold text-amber-800"><?= $stats['complaint'] + $stats['spam'] ?></div></div>
              <?php endif; ?>
            </div>
            <?php if (!$stats['done'] && $comments && $aiOn): ?><p class="text-xs text-ink-400"><?= e(t('حدد التعليقات واضغط "تصنيف" عشان تشوف نية الشراء والشكاوي.')) ?></p><?php endif; ?>
            <?php if ($aiOn): ?>
              <div class="space-y-1.5"><span class="text-xs font-medium text-ink-500"><?= e(t('إجراءات سريعة')) ?></span>
                <button type="button" class="w-full btn-ghost !justify-start !text-xs" data-cp-insight-chat="<?= e(t('لخّص التعليقات على المنشور ده: الناس قالت إيه، مين عايز يشتري، وإيه الشكاوي.')) ?>"><?= ai_icon('spark', 'w-3.5 h-3.5 text-brand-600') ?><?= e(t('لخّص التعليقات')) ?></button>
              </div>
            <?php endif; ?>
            <?php if (!empty($current['permalink'])): ?><a href="<?= e($current['permalink']) ?>" target="_blank" rel="noopener" class="w-full btn-ghost !text-xs"><?= e(t('فتح المنشور ↗')) ?></a><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="flex-1 flex items-center justify-center p-6 text-center text-xs text-ink-400"><?= e(t('تفاصيل المنشور هتظهر هنا.')) ?></div>
        <?php endif; ?>
      </aside>
    </div>
    </div>

    <!-- رد على المحدد -->
    <dialog id="cm-dlg" class="w-[min(520px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40">
      <form method="dialog" id="cm-form" class="flex flex-col">
        <div class="flex items-center px-5 h-14 border-b border-ink-200"><b class="flex-1 text-ink-900"><?= e(t('رد على المحدد')) ?> (<span id="cm-dn">0</span>)</b><button type="button" data-cm-close class="p-2 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div>
        <div class="p-5 space-y-3">
          <textarea name="message" data-saved-replies data-ai="improve,shorten,tone_friendly" rows="4" required class="input text-sm" placeholder="<?= e(t('أهلًا {name}، شكرًا لتعليقك! بعتنالك التفاصيل في الخاص.')) ?>"></textarea>
          <p class="text-[11px] text-ink-500 -mt-1"><?= e(t('اكتب {name} في أي مكان وهيتبدّل باسم العميل.')) ?></p>
          <?php if ($aiOn): ?><label class="flex items-start gap-2.5 text-sm"><input type="checkbox" name="vary" value="1" class="mt-1" checked><span><b class="text-ink-900"><?= e(t('صياغة مختلفة لكل تعليق')) ?></b><span class="block text-xs text-ink-500"><?= e(t('عشان الردود ماتبانش آلية، وفيسبوك مايعتبرهاش سبام. بيستهلك طلب لكل تعليق.')) ?></span></span></label><?php endif; ?>
          <div id="cm-prog" class="hidden"><div class="h-2 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-l from-brand-600 to-lime-500 transition-all" style="width:0"></div></div><p class="text-xs text-ink-500 mt-1.5"></p></div>
        </div>
        <div class="flex items-center gap-2 px-5 py-3 border-t border-ink-200"><span id="cm-err" class="text-xs text-red-600"></span><button type="button" data-cm-close class="ms-auto btn-ghost"><?= e(t('إلغاء')) ?></button><button type="submit" id="cm-go" class="btn-primary"><?= e(t('ابدأ الرد')) ?></button></div>
      </form>
    </dialog>
    <script>window.CMX = <?= json_encode(['sel' => t('محدد'), 'all' => t('تحديد الكل'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'), 'pick' => t('حدد تعليق واحد على الأقل.'), 'done' => t('تم: %s'), 'confirmAi' => t('هيتم تحليل %s تعليق بالذكاء الاصطناعي. تكمل؟'), 'confirmHide' => t('إخفاء %s تعليق؟'), 'progress' => t('اترد على %s من %s'), 'finished' => t('خلص: اترد على %s، وفشل %s')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script>window.SAVED_REPLIES = <?= json_encode(['items' => saved_replies((int)ws('id')), 'manage' => t('إدارة الردود المحفوظة'), 'empty' => t('مفيش ردود محفوظة. اكتب # واختصار جديد عشان تضيف.'), 'add' => t('إضافة رد محفوظ'), 'shortcut' => t('الاختصار'), 'body' => t('نص الرد'), 'save' => t('حفظ'), 'del' => t('حذف'), 'hint' => t('اكتب # للردود المحفوظة')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/replies.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/comments.js?v=<?= e(APP_VERSION) ?>"></script>
    <script>window.CMXU = <?= json_encode(['ws' => (int)ws('id'),
      'chars' => t('حرف'), 'draft' => t('مسودة'), 'draftSaved' => t('المسودة محفوظة'), 'emoji' => t('إيموجي'), 'searchPosts' => t('ابحث في المنشورات'), 'instant' => t('%s منشور مطابق في القائمة الحالية'),
      'copy' => t('نسخ'), 'copied' => t('اتنسخ'), 'quote' => t('اقتباس في الرد'), 'latest' => t('آخر تعليق'), 'newC' => t('%s تعليق جديد'), 'findPh' => t('ابحث في التعليقات...'), 'findIn' => t('بحث في التعليقات'),
      'nextUn' => t('التعليق التالي من غير رد'), 'allAnswered' => t('كل التعليقات متردّ عليها'),
      'keysTitle' => t('اختصارات الكيبورد'), 'keys' => [['j k', t('المنشور التالي / السابق')], ['u', t('التعليق التالي من غير رد')], ['r', t('اكتب رد')], ['/', t('ابحث في المنشورات')], ['Ctrl F', t('بحث في التعليقات')], ['g', t('انزل لآخر تعليق')], ['#', t('الردود المحفوظة')], ['?', t('الاختصارات')]]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/comments-ux.js?v=<?= e(APP_VERSION) ?>"></script>
    <script>window.CMT = <?= json_encode(['preps' => $preps, 'pool' => array_values($pool), 'post' => $postId, 'a' => $accId, 'crm' => module_enabled('crm') && can('crm.view'),
      'none' => t('مفيش حد مطابق للشروط.'), 'win' => t('الفايز'), 'wins' => t('الفايزين'), 'of' => t('من %s مشارك'), 'saved' => t('اتحفظ ✓'), 'hidden' => t('اتخفى %s تعليق'), 'empty' => t('لسه مفيش ردود للمنشور ده.'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'),
      'cm' => t('%s تعليق'), 'onp' => t('على %s منشور'), 'price' => t('💰 سأل عن السعر %s'), 'lead' => t('ضيفه Lead'), 'loading' => t('بنحمّل…'), 'noFans' => t('مفيش تعليقات كفاية.'), 'confirmRun' => t('هنلف على آخر 8 منشورات ونخفي المطابق. تكمل؟')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/comments-tools.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php if (module_enabled('crm') && can('crm.view')): require_once __DIR__ . '/../partials/lead_popup.php'; ?><?= lead_popup() ?><script src="assets/lead-popup.js?v=<?= e(APP_VERSION) ?>"></script><?php endif; ?>
    <?php if (module_enabled('crm') && can('crm.view')) to_lead_script(); ?>
    <?php
}, ['full' => true]);
