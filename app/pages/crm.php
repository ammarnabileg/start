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
    $pill = ['manual' => 'bg-ink-100 text-ink-700', 'inbound' => 'bg-lime-50 text-lime-800', 'ai' => 'bg-brand-50 text-brand-700', 'apollo' => 'bg-indigo-50 text-indigo-700'];
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <button type="button" data-src="" class="tg-chip on"><?= e(t('الكل')) ?> <span><?= count($rows) ?></span></button>
      <?php foreach ($src as $k => $l) if ($counts[$k]): ?><button type="button" data-src="<?= e($k) ?>" class="tg-chip"><?= e($l) ?> <span><?= (int)$counts[$k] ?></span></button><?php endif; ?>
      <span class="ms-auto"></span>
      <input type="search" id="tg-q" class="input !mt-0 !w-56 text-sm" placeholder="<?= e(t('ابحث بالاسم أو المجال أو المدينة…')) ?>">
      <?php if ($canManage): ?><button type="button" id="tg-ai" class="btn-ghost" <?= $aiOn ? '' : 'data-off="1"' ?>><?= ui_icon('spark', 'w-4 h-4') ?><span><?= e(t('اقترح شركات')) ?></span></button><?php endif; ?>
    </div>

    <form id="tg-add" class="card p-3 mb-4 flex flex-wrap items-center gap-2" data-no-ajax>
      <?= ui_icon('plus', 'w-5 h-5 text-ink-400') ?>
      <input name="q" class="input !mt-0 flex-1 min-w-[220px] text-sm" dir="auto" autocomplete="off" placeholder="<?= e(t('دومين الشركة (مثلًا company.com) أو اسمها لو مالهاش موقع')) ?>">
      <button class="btn-primary !py-2"><?= e(t('أضف')) ?></button>
    </form>

    <?php if (!$rows): ?>
      <div class="card p-8 text-center text-sm text-ink-500"><?= e(t('لسه مفيش شركات. ضيف دومين شركة، أو استنى أول عميل يكلمك من شركة، أو دوس "اقترح شركات".')) ?></div>
    <?php else: ?>
    <div class="card overflow-hidden">
      <div class="hidden md:grid grid-cols-[minmax(0,2.2fr)_minmax(0,1.3fr)_90px_minmax(0,1fr)_130px_120px] gap-3 px-4 py-2.5 text-xs text-ink-500 border-b border-ink-100 bg-ink-50/60">
        <span><?= e(t('الشركة')) ?></span><span><?= e(t('المجال')) ?></span><span><?= e(t('الموظفين')) ?></span><span><?= e(t('المكان')) ?></span><span><?= e(t('الأشخاص')) ?></span><span><?= e(t('المصدر')) ?></span>
      </div>
      <div id="tg-list" class="divide-y divide-ink-100">
      <?php foreach ($rows as $r): $s = $r['source'] ?: 'apollo'; $loc = trim(implode('، ', array_filter([$r['city'], $r['country']]))); ?>
        <button type="button" class="tg-row w-full text-start grid md:grid-cols-[minmax(0,2.2fr)_minmax(0,1.3fr)_90px_minmax(0,1fr)_130px_120px] gap-3 px-4 py-3 items-center hover:bg-brand-50/40 transition"
          data-id="<?= (int)$r['id'] ?>" data-src="<?= e($s) ?>" data-q="<?= e(mb_strtolower($r['name'] . ' ' . $r['domain'] . ' ' . $r['industry'] . ' ' . $loc)) ?>">
          <span class="flex items-center gap-3 min-w-0">
            <?php if ($r['logo']): ?><img src="<?= e($r['logo']) ?>" alt="" class="w-9 h-9 rounded-lg object-contain bg-white border border-ink-100 shrink-0" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'w-9 h-9 rounded-lg bg-ink-100 text-ink-700 grid place-items-center font-bold shrink-0',textContent:<?= e(json_encode(mb_strtoupper(mb_substr($r['name'], 0, 1)), JSON_UNESCAPED_UNICODE)) ?>}))">
            <?php else: ?><span class="w-9 h-9 rounded-lg bg-ink-100 text-ink-700 grid place-items-center font-bold shrink-0"><?= e(mb_strtoupper(mb_substr($r['name'], 0, 1))) ?></span><?php endif; ?>
            <span class="min-w-0"><b class="block text-ink-900 truncate"><?= e($r['name']) ?></b><?php if ($r['domain']): ?><span class="block text-xs text-ink-500 truncate" dir="ltr"><?= e($r['domain']) ?></span><?php endif; ?></span>
          </span>
          <span class="text-sm text-ink-600 truncate"><?= e($r['industry'] ?: '—') ?></span>
          <span class="text-sm text-ink-600"><?= $r['employees'] ? number_format((int)$r['employees']) : '—' ?></span>
          <span class="text-sm text-ink-600 truncate"><?= e($loc ?: '—') ?></span>
          <span class="text-sm flex items-center gap-1.5"><?php if ((int)$r['people_n']): ?><b class="text-ink-900"><?= (int)$r['people_n'] ?></b>
            <?php if ((int)$r['phones']): ?><span class="text-lime-700" title="<?= e(t('أرقام')) ?>"><?= ui_icon('phone', 'w-3.5 h-3.5') ?></span><?php endif; ?><?php if ((int)$r['emails']): ?><span class="text-brand-600" title="<?= e(t('إيميلات')) ?>"><?= ui_icon('mail', 'w-3.5 h-3.5') ?></span><?php endif; ?>
            <?php else: ?><span class="text-ink-400">—</span><?php endif; ?></span>
          <span><span class="text-[11px] rounded-full px-2 py-0.5 <?= $pill[$s] ?>"><?= e($src[$s]) ?></span></span>
        </button>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <dialog id="tg-dlg" class="w-[min(680px,96vw)] max-h-[88vh] rounded-2xl p-0 shadow-2xl backdrop:bg-ink-900/40 backdrop:backdrop-blur-[2px]">
      <div class="flex items-start gap-3 p-5 border-b border-ink-100 sticky top-0 bg-white z-10">
        <div id="tg-dlg-logo"></div><div class="flex-1 min-w-0"><b id="tg-dlg-name" class="block text-lg text-ink-900"></b><span id="tg-dlg-sub" class="text-sm text-ink-500"></span></div>
        <button type="button" data-close class="p-2 -m-1 rounded-xl text-ink-400 hover:text-ink-900 hover:bg-ink-100" aria-label="<?= e(t('إغلاق')) ?>"><?= ui_icon('x', 'w-5 h-5') ?></button>
      </div>
      <div class="p-5 space-y-5">
        <section><h3 class="text-sm font-semibold text-ink-900 mb-2"><?= e(t('أشخاص عندك بياناتهم')) ?></h3><div id="tg-known" class="space-y-2"></div></section>
        <section id="tg-ap-sec"><h3 class="text-sm font-semibold text-ink-900 mb-2"><?= e(t('أهم الأشخاص في الشركة')) ?></h3><div id="tg-ap" class="space-y-2"></div>
          <button type="button" id="tg-more" class="btn-ghost !text-xs w-full mt-2 hidden"><?= e(t('عرض 5 كمان')) ?></button></section>
        <div class="flex justify-between items-center pt-2 border-t border-ink-100"><a id="tg-site" href="#" target="_blank" rel="noopener" class="text-xs link hidden" dir="ltr"></a>
          <button type="button" id="tg-remove" class="text-xs text-ink-400 hover:text-red-600 ms-auto"><?= e(t('شيلها من القايمة')) ?></button></div>
      </div>
    </dialog>
    <style>.tg-chip{font-size:.875rem;border-radius:9999px;border:1px solid rgb(226 232 240);padding:.25rem .75rem;color:rgb(71 85 105)}.tg-chip span{opacity:.6;margin-inline-start:.25rem}.tg-chip.on{background:rgb(15 23 42);border-color:rgb(15 23 42);color:#fff}
      #tg-dlg[open]{animation:tgIn .18s ease-out}@keyframes tgIn{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}</style>
    <script>window.TG = <?= json_encode(['csrf' => csrf_token(), 'url' => url('crm'), 'apollo' => $hasApollo, 'reveal' => $canReveal, 'connectUrl' => url('connectors'),
        'L' => ['none' => t('لسه مفيش أشخاص من الشركة دي عندك.'), 'noApollo' => t('اربط Apollo من التكاملات عشان تشوف أهم الأشخاص في الشركة.'), 'noDomain' => t('الشركة دي مالهاش دومين، فمش هنقدر نلاقي أشخاصها في Apollo.'),
        'nobody' => t('Apollo مالقاش أشخاص تانيين.'), 'reveal' => t('اكشف البيانات'), 'busy' => t('بنكشف…'), 'added' => t('اتضافت ✓'), 'dup' => t('الشركة دي موجودة بالفعل.'), 'err' => t('حصلت مشكلة.'),
        'aiBusy' => t('بنحلل قاعدة المعرفة ونجيب شركات…'), 'aiDone' => t('اتضافت %s شركة'), 'aiOff' => t('اقتراح الشركات محتاج Apollo ومزود ذكاء اصطناعي مربوطين من التكاملات.'), 'confirmRm' => t('تشيل الشركة دي من القايمة؟'),
        'open' => t('افتح'), 'call' => t('اتصال'), 'hasEmail' => t('فيه إيميل'), 'hasPhone' => t('فيه رقم')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/targets.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
