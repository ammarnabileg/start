<?php
/** الشركات المستهدفة: إضافة يدوية، والشركات اللي تواصلت معنا، واقتراحات الذكاء الاصطناعي (بـ Apollo). الضغط على شركة بيفتح أشخاصها */
$wid = (int)ws('id');
$uid = (int)(current_user()['id'] ?? 0);
$canManage = can('crm.manage') || is_ws_owner();
$canReveal = can('crm.reveal');
$hasApollo = crm_has_key();
$J = function (array $a, int $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
$getCo = function (int $id) use ($wid) { $st = db()->prepare('SELECT * FROM crm_companies WHERE id = ? AND workspace_id = ?'); $st->execute([$id, $wid]); return $st->fetch() ?: null; };
$person = function (array $l) { return ['id' => (int)$l['id'], 'name' => (string)$l['name'], 'title' => (string)$l['title'], 'email' => (string)$l['email'], 'phone' => (string)$l['phone'],
    'wa' => function_exists('crm_wa_digits') ? crm_wa_digits($l['phone']) : '', 'source' => (string)$l['source'], 'url' => url('crm_leads', ['lead' => (int)$l['id']])]; };

// بوب أب الشركة: الأشخاص المعروفين + أهم الأشخاص من Apollo
if (($_GET['ajax'] ?? '') === 'company') {
    $co = $getCo((int)($_GET['id'] ?? 0)); if (!$co) $J(['ok' => false, 'error' => t('الشركة مش موجودة.')], 404);
    $st = db()->prepare('SELECT * FROM crm_leads WHERE workspace_id = ? AND company_id = ? ORDER BY (phone IS NULL OR phone = \'\') , (email IS NULL OR email = \'\'), id DESC');
    $st->execute([$wid, (int)$co['id']]); $known = array_map($person, $st->fetchAll());
    $out = ['ok' => true, 'known' => $known, 'apollo' => null];
    if ($hasApollo) {
        try {
            $r = tg_people($co, max(1, (int)($_GET['page'] ?? 1)));
            $ext = db()->prepare('SELECT ext_id FROM crm_leads WHERE workspace_id = ? AND company_id = ? AND ext_id IS NOT NULL'); $ext->execute([$wid, (int)$co['id']]); $have = array_flip($ext->fetchAll(PDO::FETCH_COLUMN));
            $r['people'] = array_values(array_filter($r['people'], function ($p) use ($have) { return !isset($have[$p['id']]); }));
            $out['apollo'] = $r;
        } catch (Throwable $e) { $out['apollo'] = ['people' => [], 'more' => false, 'error' => $e->getMessage()]; }
    }
    $J($out);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'add') {
        try { $r = tg_add((string)($_POST['q'] ?? '')); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()], 400); }
        log_activity('crm.target_add', (string)$_POST['q']);
        $J(['ok' => true, 'id' => $r['id'], 'dup' => $r['dup']]);
    }
    if ($a === 'ai') {
        if (!$canManage) $J(['ok' => false, 'error' => t('مالكش صلاحية.')], 403);
        try { $r = tg_ai_suggest(); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()], 400); }
        $J(['ok' => true, 'added' => $r['added'], 'why' => $r['icp']['why'] ?? '']);
    }
    if ($a === 'reveal') {
        if (!$canReveal) $J(['ok' => false, 'error' => t('مالكش صلاحية الكشف.')], 403);
        $co = $getCo((int)($_POST['id'] ?? 0)); $pid = (string)($_POST['pid'] ?? '');
        if (!$co || !preg_match('/^[\w.-]{1,64}$/', $pid)) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $prefs = crm_prefs();
        if ((int)$prefs['daily_limit'] - crm_reveals_today($uid) <= 0) $J(['ok' => false, 'error' => t('خلصت حد الكشف بتاعك النهارده (%s). بيتجدد بكرة.', (int)$prefs['daily_limit'])], 429);
        try { $l = crm_reveal($co, $pid, $prefs); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()], 400); }
        $J(['ok' => true, 'person' => $person(crm_lead((int)$l['id']))]);
    }
    if ($a === 'remove') {
        $co = $getCo((int)($_POST['id'] ?? 0)); if (!$co) $J(['ok' => false], 404);
        db()->prepare('UPDATE crm_companies SET hidden = 1 WHERE id = ? AND workspace_id = ?')->execute([(int)$co['id'], $wid]);
        $J(['ok' => true]);
    }
    $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
}

tg_sync_inbound();
$rows = tg_list();
$src = tg_sources();
$counts = array_fill_keys(array_keys($src), 0); foreach ($rows as $r) $counts[$r['source'] ?: 'apollo']++;
$aiOn = $hasApollo && function_exists('ai_connection') && ai_connection();

render(t('الشركات المستهدفة'), 'crm', function () use ($rows, $src, $counts, $hasApollo, $aiOn, $canManage, $canReveal) {
    $cols = 'md:grid-cols-[22px_minmax(0,2.2fr)_minmax(0,1.3fr)_90px_minmax(0,1fr)_120px_130px_84px]';
    $pill = ['manual' => 'bg-ink-100 text-ink-700', 'inbound' => 'bg-lime-50 text-lime-800', 'ai' => 'bg-brand-50 text-brand-700', 'apollo' => 'bg-indigo-50 text-indigo-700'];
    $ics = ['manual' => ['pencil', 'bg-ink-100 text-ink-700'], 'inbound' => ['chat', 'bg-lime-50 text-lime-700'], 'ai' => ['spark', 'bg-brand-50 text-brand-700'], 'apollo' => ['grid', 'bg-indigo-50 text-indigo-700']];
    ?>
    <div id="tg" data-mk>
    <div class="mk-stats">
      <button type="button" class="mk-stat mk-on" data-mk-stat="_all" data-mk-test=""><span class="mk-ic bg-ink-100 text-ink-700"><?= ui_icon('layers', 'w-5 h-5') ?></span><span><b><?= count($rows) ?></b><small><?= e(t('كل الشركات')) ?></small></span></button>
      <?php foreach ($src as $k => $l): ?>
        <button type="button" class="mk-stat" data-mk-stat="<?= e($k) ?>" data-mk-test="src=<?= e($k) ?>"><span class="mk-ic <?= $ics[$k][1] ?>"><?= ui_icon($ics[$k][0], 'w-5 h-5') ?></span><span><b><?= (int)$counts[$k] ?></b><small><?= e($l) ?></small></span></button>
      <?php endforeach; ?>
    </div>

    <div class="mk-tools">
      <label class="mk-search"><?= ui_icon('search') ?><input type="search" data-mk-q placeholder="<?= e(t('ابحث بالاسم أو المجال أو المدينة…')) ?>"><kbd>/</kbd></label>
      <span class="text-xs text-ink-500 tabular-nums"><span data-mk-shown></span></span>
      <span class="ms-auto"></span>
      <form id="tg-add" class="flex items-center gap-1.5 m-0" data-no-ajax>
        <input name="q" class="input !mt-0 !py-2 !w-72 text-sm" dir="auto" autocomplete="off" placeholder="<?= e(t('دومين الشركة أو اسمها')) ?>" title="<?= e(t('دومين الشركة (مثلًا company.com) أو اسمها لو مالهاش موقع')) ?>">
        <button class="btn-ghost"><?= ui_icon('plus', 'w-4 h-4') ?><?= e(t('أضف')) ?></button>
      </form>
      <?php if ($canManage): ?><button type="button" id="tg-ai" class="btn-primary" <?= $aiOn ? '' : 'data-off="1"' ?>><?= ui_icon('spark', 'w-4 h-4') ?><span><?= e(t('اقترح شركات')) ?></span></button><?php endif; ?>
    </div>

    <div class="mk-table">
      <div class="mk-head <?= $cols ?>"><input type="checkbox" data-mk-all aria-label="<?= e(t('تحديد الكل')) ?>">
        <span data-mk-sort="name"><?= e(t('الشركة')) ?></span><span data-mk-sort="ind"><?= e(t('المجال')) ?></span><span data-mk-sort="emp"><?= e(t('الموظفين')) ?></span>
        <span data-mk-sort="loc"><?= e(t('المكان')) ?></span><span data-mk-sort="ppl"><?= e(t('الأشخاص')) ?></span><span data-mk-sort="src"><?= e(t('المصدر')) ?></span><span></span></div>
      <div id="tg-list" data-mk-body>
      <?php foreach ($rows as $r): $s = $r['source'] ?: 'apollo'; $loc = trim(implode('، ', array_filter([$r['city'], $r['country']]))); $ini = mb_strtoupper(mb_substr($r['name'], 0, 1)); ?>
        <div class="mk-row tg-row <?= $cols ?>" data-mk-row data-id="<?= (int)$r['id'] ?>" data-src="<?= e($s) ?>" data-q="<?= e(mb_strtolower($r['name'] . ' ' . $r['domain'] . ' ' . $r['industry'] . ' ' . $loc)) ?>"
          data-s-name="<?= e(mb_strtolower($r['name'])) ?>" data-s-ind="<?= e(mb_strtolower((string)$r['industry'])) ?>" data-s-emp="<?= (int)$r['employees'] ?>" data-s-loc="<?= e(mb_strtolower($loc)) ?>" data-s-ppl="<?= (int)$r['people_n'] ?>" data-s-src="<?= e($s) ?>"
          data-name="<?= e($r['name']) ?>" data-domain="<?= e((string)$r['domain']) ?>" data-logo="<?= e((string)$r['logo']) ?>" data-sub="<?= e(implode(' · ', array_filter([$r['industry'], $loc, $r['employees'] ? number_format((int)$r['employees']) . ' ' . t('موظف') : '']))) ?>">
          <input type="checkbox" data-mk-check aria-label="<?= e(t('تحديد')) ?>">
          <span class="flex items-center gap-3 min-w-0">
            <span class="mk-av"><?php if ($r['logo']): ?><img src="<?= e($r['logo']) ?>" alt="" class="w-full h-full object-contain bg-white" loading="lazy" onerror="this.replaceWith(document.createTextNode(<?= e(json_encode($ini, JSON_UNESCAPED_UNICODE)) ?>))"><?php else: ?><?= e($ini) ?><?php endif; ?></span>
            <span class="min-w-0"><b class="block text-ink-900 truncate"><?= e($r['name']) ?></b><?php if ($r['domain']): ?><span class="block text-xs text-ink-500 truncate" dir="ltr"><?= e($r['domain']) ?></span><?php endif; ?></span>
          </span>
          <span class="text-sm text-ink-600 truncate"><?= e($r['industry'] ?: '—') ?></span>
          <span class="text-sm text-ink-600 tabular-nums"><?= $r['employees'] ? number_format((int)$r['employees']) : '—' ?></span>
          <span class="text-sm text-ink-600 truncate"><?= e($loc ?: '—') ?></span>
          <span class="text-sm flex items-center gap-1.5"><?php if ((int)$r['people_n']): ?><b class="text-ink-900 tabular-nums"><?= (int)$r['people_n'] ?></b>
            <?php if ((int)$r['phones']): ?><span class="text-lime-700" title="<?= e(t('أرقام')) ?>"><?= ui_icon('phone', 'w-3.5 h-3.5') ?></span><?php endif; ?><?php if ((int)$r['emails']): ?><span class="text-brand-600" title="<?= e(t('إيميلات')) ?>"><?= ui_icon('mail', 'w-3.5 h-3.5') ?></span><?php endif; ?>
            <?php else: ?><span class="text-ink-300">—</span><?php endif; ?></span>
          <span><span class="mk-pill <?= $pill[$s] ?>"><?= e($src[$s]) ?></span></span>
          <span class="mk-acts"><?php if ($r['domain']): ?><a class="mk-act" href="https://<?= e($r['domain']) ?>" target="_blank" rel="noopener" title="<?= e(t('الموقع')) ?>"><?= ui_icon('link', 'w-4 h-4') ?></a><?php endif; ?>
            <button type="button" class="mk-act" data-rm title="<?= e(t('شيلها من القايمة')) ?>"><?= ui_icon('trash', 'w-4 h-4') ?></button></span>
        </div>
      <?php endforeach; ?>
      </div>
      <p data-mk-empty class="hidden p-8 text-center text-sm text-ink-500"><?= e($rows ? t('مفيش شركات بالفلاتر دي.') : t('لسه مفيش شركات. ضيف دومين شركة، أو استنى أول عميل يكلمك من شركة، أو دوس "اقترح شركات".')) ?></p>
    </div>

    <div class="mk-bar" data-mk-bar>
      <span class="text-sm"><b data-mk-nsel>0</b> <?= e(t('محدد')) ?></span>
      <button type="button" id="tg-rm-sel"><?= ui_icon('trash', 'w-4 h-4 inline') ?> <?= e(t('شيلها من القايمة')) ?></button>
      <button type="button" data-mk-clear class="!px-2" title="<?= e(t('إلغاء التحديد')) ?>"><?= ui_icon('x', 'w-4 h-4') ?></button>
    </div>
    </div>

    <dialog id="tg-dlg" class="mk-dlg">
      <div class="mk-dlg-head">
        <span id="tg-dlg-logo" class="mk-av !w-12 !h-12 !text-lg"></span><div class="flex-1 min-w-0"><b id="tg-dlg-name" class="block text-lg text-ink-900 truncate"></b><span id="tg-dlg-sub" class="text-sm text-ink-500"></span></div>
        <a id="tg-site" href="#" target="_blank" rel="noopener" class="btn-ghost !py-1 !text-xs hidden" dir="ltr"></a>
        <button type="button" data-close class="p-2 -m-1 rounded-xl text-ink-400 hover:text-ink-900 hover:bg-ink-100" aria-label="<?= e(t('إغلاق')) ?>"><?= ui_icon('x', 'w-5 h-5') ?></button>
      </div>
      <div class="p-5 space-y-5">
        <section><h3 class="text-sm font-semibold text-ink-900 mb-2 flex items-center gap-1.5"><?= ui_icon('user', 'w-4 h-4 text-lime-700') ?><?= e(t('أشخاص عندك بياناتهم')) ?></h3><div id="tg-known" class="space-y-2"></div></section>
        <section id="tg-ap-sec"><h3 class="text-sm font-semibold text-ink-900 mb-2 flex items-center gap-1.5"><?= ui_icon('users', 'w-4 h-4 text-brand-600') ?><?= e(t('أهم الأشخاص في الشركة')) ?></h3><div id="tg-ap" class="space-y-2"></div>
          <button type="button" id="tg-more" class="btn-ghost !text-xs w-full mt-2 hidden"><?= e(t('عرض 5 كمان')) ?></button></section>
        <div class="flex justify-end pt-2 border-t border-ink-100"><button type="button" id="tg-remove" class="text-xs text-ink-400 hover:text-red-600"><?= e(t('شيلها من القايمة')) ?></button></div>
      </div>
    </dialog>
    <script>window.TG = <?= json_encode(['csrf' => csrf_token(), 'url' => url('crm'), 'apollo' => $hasApollo, 'reveal' => $canReveal, 'connectUrl' => url('connectors'),
        'L' => ['none' => t('لسه مفيش أشخاص من الشركة دي عندك.'), 'noApollo' => t('اربط Apollo من التكاملات عشان تشوف أهم الأشخاص في الشركة.'), 'noDomain' => t('الشركة دي مالهاش دومين، فمش هنقدر نلاقي أشخاصها في Apollo.'),
        'nobody' => t('Apollo مالقاش أشخاص تانيين.'), 'reveal' => t('اكشف البيانات'), 'busy' => t('بنكشف…'), 'added' => t('اتضافت ✓'), 'dup' => t('الشركة دي موجودة بالفعل.'), 'err' => t('حصلت مشكلة.'),
        'aiBusy' => t('بنحلل قاعدة المعرفة ونجيب شركات…'), 'aiDone' => t('اتضافت %s شركة'), 'aiOff' => t('اقتراح الشركات محتاج Apollo ومزود ذكاء اصطناعي مربوطين من التكاملات.'), 'confirmRm' => t('تشيل الشركة دي من القايمة؟'),
        'confirmRmN' => t('تشيل %s شركة من القايمة؟'), 'open' => t('افتح'), 'hasEmail' => t('فيه إيميل'), 'hasPhone' => t('فيه رقم')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/mk-list.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/targets.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
