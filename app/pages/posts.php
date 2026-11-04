<?php
$err = null;
$z = zernio_or_null($err);

// حذف من غير تحميل الصفحة (JSON)، أو بالطريقة العادية لو الجافاسكربت مش شغال
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z && in_array($_POST['action'] ?? '', ['delete', 'delete_json'], true)) {
    $json = ($_POST['action'] ?? '') === 'delete_json';
    $ids = $json ? array_slice(array_filter(array_map('strval', (array)($_POST['ids'] ?? []))), 0, 50) : [(string)($_POST['id'] ?? '')];
    if (!can('posts.manage')) {
        if ($json) { header('Content-Type: application/json'); http_response_code(403); exit(json_encode(['error' => t('مالكش صلاحية تفتح الصفحة دي. اطلبها من صاحب الشركة.')], JSON_UNESCAPED_UNICODE)); }
        redirect(url('posts'));
    }
    $ok = []; $fail = [];
    foreach ($ids as $id) {
        if (!preg_match('/^[\w-]{1,64}$/', $id)) continue;
        try { $z->deletePost($id); log_activity('post.delete', $id); $ok[] = $id; } catch (ZernioException $ex) { $fail[$id] = $ex->getMessage(); }
    }
    if ($json) { header('Content-Type: application/json; charset=utf-8'); exit(json_encode(['ok' => $ok, 'failed' => $fail], JSON_UNESCAPED_UNICODE)); }
    $fail ? flash('error', reset($fail)) : flash('success', t('تم حذف المنشور.'));
    redirect(url('posts', ['status' => $_GET['status'] ?? '']));
}

$status = (string)($_GET['status'] ?? '');
$validStatus = ['', 'scheduled', 'published', 'draft', 'failed', 'partial'];
if (!in_array($status, $validStatus, true)) $status = '';
$view = ($_GET['view'] ?? '') === 'cal' ? 'cal' : 'list';
$pageNo = max(1, (int)($_GET['n'] ?? 1));
$posts = [];
$pages = 1;
$total = null;
$apiError = null;
$accounts = [];

$fixture = getenv('POSTS_FIXTURE'); // بيئة الاختبار فقط
if ($z && $fixture && is_file($fixture)) {
    $fx = json_decode((string)file_get_contents($fixture), true) ?: [];
    foreach ($fx['posts'] ?? [] as $p) if ($status === '' || ($p['status'] ?? '') === $status) $posts[] = $p;
    foreach ($fx['accounts'] ?? [] as $acc) $accounts[$acc['_id']] = ['name' => $acc['displayName'] ?? '', 'pic' => '', 'plat' => $acc['platform'] ?? ''];
    $total = count($posts);
} elseif ($z) {
    try {
        if ($view === 'cal') {
            // التقويم: المجدول (الجاي) + آخر المنشور، لحد 100 من كل نوع
            $a = $z->posts(['status' => 'scheduled', 'limit' => 100, 'sortBy' => 'scheduled-asc', 'profileId' => zernio_profile()])['posts'] ?? [];
            $b = $status === 'scheduled' ? [] : ($z->posts(['status' => $status ?: null, 'limit' => 100, 'sortBy' => 'created-desc', 'profileId' => zernio_profile()])['posts'] ?? []);
            $seen = [];
            foreach (array_merge($a, $b) as $p) { $id = (string)($p['_id'] ?? ''); if ($id === '' || isset($seen[$id])) continue; $seen[$id] = 1; if ($status === '' || ($p['status'] ?? '') === $status) $posts[] = $p; }
        } else {
            $r = $z->posts(['status' => $status ?: null, 'page' => $pageNo, 'limit' => 24, 'sortBy' => $status === 'scheduled' ? 'scheduled-asc' : 'created-desc', 'profileId' => zernio_profile()]);
            $posts = $r['posts'] ?? [];
            $pages = max(1, (int)($r['pagination']['pages'] ?? 1));
            $total = isset($r['pagination']['total']) ? (int)$r['pagination']['total'] : null;
        }
        foreach ($z->accounts(zernio_profile()) as $acc) $accounts[$acc['_id']] = ['name' => $acc['displayName'] ?? $acc['username'] ?? '', 'pic' => $acc['profilePicture'] ?? '', 'plat' => $acc['platform'] ?? ''];
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render($view === 'cal' ? t('التقويم') : t('المنشورات'), $view === 'cal' ? 'calendar' : 'posts', function () use ($z, $posts, $pages, $pageNo, $status, $view, $total, $apiError, $accounts) {
    if (!$z) { no_api_box(); return; }
    $tabs = ['' => t('الكل'), 'scheduled' => t('مجدول'), 'published' => t('تم النشر'), 'draft' => t('مسودات'), 'failed' => t('فشل')];
    $stCls = ['scheduled' => 'bg-brand-50 text-brand-700', 'published' => 'bg-lime-100 text-lime-700', 'draft' => 'bg-ink-100 text-ink-600', 'failed' => 'bg-red-100 text-red-700', 'partial' => 'bg-amber-100 text-amber-800', 'publishing' => 'bg-cyan-100 text-cyan-700', 'pending_approval' => 'bg-amber-100 text-amber-800', 'cancelled' => 'bg-ink-100 text-ink-500'];
    $plats = []; foreach ($posts as $p) foreach ($p['platforms'] ?? [] as $pl) { $k = (string)($pl['platform'] ?? ''); if ($k !== '') $plats[$k] = ($plats[$k] ?? 0) + 1; }
    $data = [];
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <div class="tb p-0.5 gap-0.5 overflow-x-auto" style="scrollbar-width:none">
        <?php foreach ($tabs as $k => $l): ?>
          <a href="<?= e(url('posts', array_filter(['status' => $k, 'view' => $view === 'cal' ? 'cal' : '']))) ?>" class="h-9 px-3 rounded-lg text-xs flex items-center whitespace-nowrap <?= $status === $k ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>"><?= e($l) ?><?php if ($status === $k && $total !== null): ?> <span class="ms-1 tabular-nums text-ink-400"><?= $total ?></span><?php endif; ?></a>
        <?php endforeach; ?>
      </div>
      <div class="relative flex-1 min-w-[12rem] max-w-sm">
        <svg class="w-4 h-4 absolute top-1/2 -translate-y-1/2 start-3 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/></svg>
        <input type="search" id="ps-q" class="input !mt-0 !h-10 !py-0 ps-9 text-sm" placeholder="<?= e(t('ابحث في المنشورات...')) ?> (/)" dir="auto">
      </div>
      <div class="tb p-0.5 gap-0.5" id="ps-view">
        <a href="<?= e(url('posts', array_filter(['status' => $status]))) ?>" class="h-9 px-2.5 rounded-lg flex items-center gap-1.5 text-xs <?= $view === 'list' ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>" title="V"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 5h16v6H4zM4 15h16v4H4z"/></svg><?= e(t('قائمة')) ?></a>
        <a href="<?= e(url('posts', array_filter(['status' => $status, 'view' => 'cal']))) ?>" class="h-9 px-2.5 rounded-lg flex items-center gap-1.5 text-xs <?= $view === 'cal' ? 'bg-white shadow-sm text-ink-900 font-medium' : 'text-ink-600 hover:bg-ink-100' ?>" title="V"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 6h16v14H4zM4 10h16M9 3v4M15 3v4"/></svg><?= e(t('تقويم')) ?></a>
      </div>
      <button type="button" id="ps-help" class="tb w-10 justify-center text-sm font-semibold text-ink-500" title="<?= e(t('اختصارات الكيبورد')) ?> (?)">?</button>
      <div class="ms-auto flex gap-2">
        <a href="<?= e(url('bulk')) ?>" data-modal="<?= e(url('bulk')) ?>" data-modal-reload class="btn-ghost !text-xs"><?= e(t('رفع منشورات من ملف CSV')) ?></a>
        <a href="<?= e(url('queue')) ?>" data-modal="<?= e(url('queue')) ?>" class="btn-ghost !text-xs"><?= e(t('مواعيد الطابور')) ?></a>
        <a href="<?= e(url('compose')) ?>" class="btn-primary"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg><?= e(t('منشور جديد')) ?> <kbd class="hidden sm:inline text-[10px] opacity-70">N</kbd></a>
      </div>
    </div>
    <?php if (count($plats) > 1): ?>
      <div class="flex flex-wrap items-center gap-1.5 mb-4" id="ps-pf">
        <?php foreach ($plats as $pk => $pn): ?><button type="button" data-pf="<?= e($pk) ?>" class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-600 hover:border-brand-300 transition"><?= platform_icon($pk, 'w-3.5 h-3.5') ?><?= e(platform_label($pk)) ?> <span class="tabular-nums text-ink-400"><?= $pn ?></span></button><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($apiError) { error_box($apiError); return; } ?>

    <?php if (!$posts): ?>
      <div class="card p-12 text-center">
        <div class="w-16 h-16 mx-auto mb-4 rounded-3xl bg-gradient-to-br from-brand-600/10 to-cyan-500/10 text-brand-600 flex items-center justify-center"><svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg></div>
        <p class="font-medium text-ink-900"><?= e(t('مفيش منشورات هنا.')) ?></p>
        <a href="<?= e(url('compose')) ?>" class="btn-primary mt-4"><?= e(t('منشور جديد')) ?></a>
      </div>
    <?php else: ?>

    <?php if ($view === 'list'): ?>
      <div id="ps-bar" class="hidden sticky top-20 z-20 mb-3 flex items-center gap-3 rounded-2xl border border-brand-200 bg-white/95 backdrop-blur shadow-lg px-4 py-2.5">
        <b class="text-sm text-ink-900" id="ps-n">0</b><span class="text-xs text-ink-500"><?= e(t('محدد')) ?></span>
        <?php if (can('posts.manage')): ?><button type="button" id="ps-bulkdel" class="btn-ghost !py-1.5 !text-xs text-red-600"><?= e(t('حذف المحدد')) ?></button><?php endif; ?>
        <button type="button" id="ps-clear" class="ms-auto link text-xs !text-ink-500"><?= e(t('إلغاء التحديد')) ?></button>
      </div>
      <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4" id="ps-grid">
        <?php foreach ($posts as $p):
            $st = (string)($p['status'] ?? ''); [$label] = status_label($st);
            $m = $p['mediaItems'][0] ?? null; $mt = $m['type'] ?? '';
            $when = $p['scheduledFor'] ?? $p['publishedAt'] ?? $p['createdAt'] ?? null;
            $pls = $p['platforms'] ?? []; $accIds = []; $errs = [];
            foreach ($pls as $pl) { $aid = is_array($pl['accountId'] ?? null) ? ($pl['accountId']['_id'] ?? '') : ($pl['accountId'] ?? ''); $accIds[] = $aid; if (($pl['status'] ?? '') === 'failed' && !empty($pl['errorMessage'])) $errs[] = platform_label($pl['platform'] ?? '') . ': ' . $pl['errorMessage']; }
            $pf = implode(' ', array_unique(array_map(function ($pl) { return (string)($pl['platform'] ?? ''); }, $pls)));
            ?>
          <article class="ps-card group relative card p-0 overflow-hidden flex flex-col transition hover:shadow-lg hover:-translate-y-0.5" data-id="<?= e($p['_id']) ?>" data-pf="<?= e($pf) ?>" data-q="<?= e(mb_strtolower(mb_substr((string)($p['content'] ?? ''), 0, 1000))) ?>">
            <label class="absolute top-3 start-3 z-10 cursor-pointer"><input type="checkbox" class="ps-sel w-4 h-4 rounded opacity-0 group-hover:opacity-100 checked:opacity-100 focus:opacity-100 transition shadow" aria-label="<?= e(t('تحديد')) ?>"></label>
            <?php if ($m && !empty($m['url'])): ?>
              <a href="<?= e(url('post_edit', ['id' => $p['_id']])) ?>" class="relative block aspect-[16/9] bg-ink-100 overflow-hidden">
                <?php if ($mt === 'video'): ?><video src="<?= e($m['url']) ?>" class="w-full h-full object-cover" muted preload="metadata"></video><span class="absolute inset-0 flex items-center justify-center"><span class="w-10 h-10 rounded-full bg-ink-900/60 text-[#fff] flex items-center justify-center">▶</span></span>
                <?php elseif ($mt === 'document'): ?><span class="w-full h-full flex items-center justify-center text-sm text-ink-500"><?= e(t('ملف')) ?></span>
                <?php else: ?><img src="<?= e($m['url']) ?>" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" loading="lazy" alt="" referrerpolicy="no-referrer"><?php endif; ?>
                <?php if (count($p['mediaItems']) > 1): ?><span class="absolute bottom-2 end-2 rounded-md bg-ink-900/70 text-[#fff] text-[10px] px-1.5 py-0.5">+<?= count($p['mediaItems']) - 1 ?></span><?php endif; ?>
              </a>
            <?php endif; ?>
            <div class="p-4 flex-1 flex flex-col">
              <div class="flex items-center gap-2">
                <span class="rounded-md px-1.5 py-0.5 text-[10px] font-medium <?= $stCls[$st] ?? 'bg-ink-100 text-ink-600' ?>"><?= e($label) ?></span>
                <span class="ps-when text-[11px] text-ink-500 tabular-nums" data-at="<?= e((string)$when) ?>" data-st="<?= e($st) ?>" title="<?= e(fmt_date($when)) ?>"><?= e(fmt_date($when)) ?></span>
                <div class="ms-auto flex -space-x-1 rtl:space-x-reverse">
                  <?php foreach ($pls as $pl): $u = $pl['platformPostUrl'] ?? null; $ic = platform_icon((string)($pl['platform'] ?? ''), 'w-[18px] h-[18px]', 'badge'); ?>
                    <?php if ($u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener" class="rounded-full ring-2 ring-white hover:scale-110 transition" title="<?= e(t('فتح على %s', platform_label((string)($pl['platform'] ?? '')))) ?>"><?= $ic ?></a>
                    <?php else: ?><span class="rounded-full ring-2 ring-white <?= ($pl['status'] ?? '') === 'failed' ? 'opacity-40' : '' ?>" title="<?= e(platform_label((string)($pl['platform'] ?? ''))) ?>"><?= $ic ?></span><?php endif; ?>
                  <?php endforeach; ?>
                </div>
              </div>
              <a href="<?= e(url('post_edit', ['id' => $p['_id']])) ?>" class="mt-2.5 block flex-1"><p class="ps-txt text-sm text-ink-800 whitespace-pre-line line-clamp-4" dir="auto"><?= ($p['content'] ?? '') !== '' ? e(mb_substr((string)$p['content'], 0, 600)) : '<span class="text-ink-400 italic">' . e(t('(بدون نص)')) . '</span>' ?></p></a>
              <?php if ($errs): ?><details class="mt-2 rounded-xl bg-red-50 border border-red-200 px-3 py-2"><summary class="cursor-pointer text-[11px] font-medium text-red-700"><?= e(t('سبب الفشل')) ?></summary><?php foreach ($errs as $er): ?><p class="text-[11px] text-red-700 mt-1 break-words"><?= e(mb_substr($er, 0, 300)) ?></p><?php endforeach; ?></details><?php endif; ?>
              <div class="flex items-center gap-1 mt-3 pt-3 border-t border-ink-100 text-xs">
                <a href="<?= e(url('post_edit', ['id' => $p['_id']])) ?>" class="rounded-lg px-2 py-1 text-brand-700 hover:bg-brand-50 font-medium"><?= e(t('التفاصيل / تعديل')) ?></a>
                <a href="<?= e(url('compose', ['dup_text' => mb_substr((string)($p['content'] ?? ''), 0, 4000), 'dup_acc' => implode(',', array_filter($accIds))])) ?>" class="rounded-lg px-2 py-1 text-ink-600 hover:bg-ink-100" title="<?= e(t('منشور جديد بنفس النص والحسابات')) ?>"><?= e(t('تكرار')) ?></a>
                <button type="button" class="ps-copy rounded-lg px-2 py-1 text-ink-600 hover:bg-ink-100"><?= e(t('نسخ النص')) ?></button>
                <?php if ($st !== 'publishing' && can('posts.manage')): ?><button type="button" class="ps-del ms-auto rounded-lg px-2 py-1 text-red-600 hover:bg-red-50"><?= e(t('حذف')) ?></button><?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <div id="ps-empty" class="hidden card p-8 text-center text-sm text-ink-500"><?= e(t('مفيش نتائج.')) ?></div>
      <?php if ($pages > 1): ?>
        <div class="flex items-center justify-center gap-1 mt-6">
          <?php if ($pageNo > 1): ?><a class="tb px-3 text-sm" href="<?= e(url('posts', array_filter(['status' => $status, 'n' => $pageNo - 1]))) ?>">‹ <?= e(t('السابق')) ?></a><?php endif; ?>
          <?php for ($i = max(1, $pageNo - 2); $i <= min($pages, $pageNo + 2); $i++): ?><a class="tb w-9 justify-center text-sm <?= $i === $pageNo ? '!bg-brand-600 !text-[#fff]' : '' ?>" href="<?= e(url('posts', array_filter(['status' => $status, 'n' => $i]))) ?>"><?= $i ?></a><?php endfor; ?>
          <?php if ($pageNo < $pages): ?><a class="tb px-3 text-sm" href="<?= e(url('posts', array_filter(['status' => $status, 'n' => $pageNo + 1]))) ?>"><?= e(t('التالي')) ?> ›</a><?php endif; ?>
        </div>
      <?php endif; ?>

    <?php else: // التقويم
        foreach ($posts as $p) { $w = $p['scheduledFor'] ?? $p['publishedAt'] ?? $p['createdAt'] ?? null; if (!$w) continue;
            $data[] = ['id' => $p['_id'], 'at' => (string)$w, 'st' => (string)($p['status'] ?? ''), 'txt' => mb_substr((string)($p['content'] ?? ''), 0, 120), 'pf' => array_values(array_unique(array_map(function ($pl) { return (string)($pl['platform'] ?? ''); }, $p['platforms'] ?? []))), 'img' => (($p['mediaItems'][0]['type'] ?? '') === 'image') ? ($p['mediaItems'][0]['url'] ?? '') : '']; }
        ?>
      <div class="card p-0 overflow-hidden" id="ps-cal">
        <div class="flex items-center gap-2 px-4 h-14 border-b border-ink-200">
          <button type="button" data-cal="prev" class="tb w-9 justify-center"><svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></button>
          <b class="text-base text-ink-900 min-w-[10rem] text-center" id="cal-title"></b>
          <button type="button" data-cal="next" class="tb w-9 justify-center"><svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button>
          <button type="button" data-cal="today" class="tb px-3 text-xs"><?= e(t('النهارده')) ?></button>
          <div class="ms-auto flex flex-wrap gap-3 text-[11px] text-ink-500">
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-brand-500"></span><?= e(t('مجدول')) ?></span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-lime-500"></span><?= e(t('تم النشر')) ?></span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span><?= e(t('فشل')) ?></span>
            <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-ink-400"></span><?= e(t('مسودة')) ?></span>
          </div>
        </div>
        <div class="grid grid-cols-7 border-b border-ink-100 bg-ink-50 text-[11px] font-medium text-ink-500" id="cal-dow"></div>
        <div class="grid grid-cols-7" id="cal-grid"></div>
      </div>
      <p class="text-[11px] text-ink-400 mt-2"><?= e(t('التقويم بيعرض لحد 100 منشور مجدول و100 من آخر المنشورات. اضغط على يوم فاضي في المستقبل عشان تعمل منشور فيه.')) ?></p>
    <?php endif; ?>
    <?php endif; ?>
    <script>window.PSX = <?= json_encode(['view' => $view, 'weekStart' => in_array('sun', (array)cx('weekend'), true) ? 1 : 6, 'data' => $data, 'tz' => site_timezone(), 'icons' => array_combine(array_keys($plats) ?: [], array_map(function ($p) { return platform_icon($p, 'w-3 h-3'); }, array_keys($plats))) ?: new stdClass(),
      'compose' => url('compose'), 'edit' => url('post_edit', ['id' => '__ID__']), 'canDel' => can('posts.manage'),
      'past' => t('من %s'), 'future' => t('بعد %s'), 'min' => t('%s دقيقة'), 'hr' => t('%s ساعة'), 'day' => t('%s يوم'), 'now' => t('دلوقتي'),
      'del' => t('متأكد إنك عاوز تحذف المنشور ده؟'), 'delN' => t('حذف %s منشور؟ مش هتقدر ترجعهم.'), 'deleted' => t('تم حذف المنشور.'), 'copied' => t('اتنسخ'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'),
      'more' => t('+%s كمان'), 'newHere' => t('منشور جديد في اليوم ده'), 'keysTitle' => t('اختصارات الكيبورد'),
      'keys' => [['n', t('منشور جديد')], ['/', t('البحث')], ['v', t('قائمة / تقويم')], ['← →', t('الشهر السابق / التالي (في التقويم)')], ['?', t('الاختصارات')]]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/posts.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
