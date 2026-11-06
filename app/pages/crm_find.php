<?php
/** إدارة العملاء ← ابحث عن أشخاص: بحث مباشر في قاعدة Apollo بفلاتر، وكشف بالجملة يضيف للـ Leads. بيظهر بس لو Apollo مربوط */
if (!crm_has_key()) redirect(url(can('crm.manage') ? 'crm' : 'crm_leads'));
$wid = (int)ws('id');
$uid = (int)(current_user()['id'] ?? 0);
$prefs = crm_prefs();
$canReveal = can('crm.reveal');
$json = function (array $a) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
$seniorL = ['owner' => t('مالك'), 'founder' => t('مؤسس'), 'c_suite' => 'C-Level', 'partner' => t('شريك'), 'vp' => 'VP', 'head' => t('رئيس قسم'), 'director' => t('مدير إدارة'), 'manager' => t('مدير')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $filters = ['titles' => (string)($_POST['titles'] ?? ''), 'seniorities' => array_map('strval', (array)($_POST['seniorities'] ?? [])), 'locations' => (string)($_POST['locations'] ?? ''),
        'sizes' => array_map('strval', (array)($_POST['sizes'] ?? [])), 'keywords' => (string)($_POST['keywords'] ?? ''), 'domains' => (string)($_POST['domains'] ?? ''),
        'rev_min' => (string)($_POST['rev_min'] ?? ''), 'rev_max' => (string)($_POST['rev_max'] ?? '')];
    if ($action === 'lookalike') $json(crm_lookalike() + ['ok' => false]);
    if ($action === 'save_search') {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        if ($name === '') $json(['ok' => false, 'error' => t('اكتب اسم للبحث.')]);
        db()->prepare('INSERT INTO crm_searches (workspace_id, user_id, name, filters) VALUES (?, ?, ?, ?)')->execute([$wid, $uid ?: null, $name, json_encode($filters, JSON_UNESCAPED_UNICODE)]);
        $id = (int)db()->lastInsertId();
        try { crm_search_run($id, true); } catch (CrmException $ex) { $json(['ok' => false, 'error' => $ex->getMessage()]); }
        $json(['ok' => true, 'searches' => crm_searches()]);
    }
    if ($action === 'del_search') {
        db()->prepare('DELETE FROM crm_searches WHERE id = ? AND workspace_id = ?')->execute([(int)($_POST['id'] ?? 0), $wid]);
        $json(['ok' => true, 'searches' => crm_searches()]);
    }
    if ($action === 'run_search') {
        try { $r = crm_search_run((int)($_POST['id'] ?? 0)); } catch (CrmException $ex) { $json(['ok' => false, 'error' => $ex->getMessage()]); }
        $json(['ok' => true, 'new' => $r['new'], 'searches' => crm_searches()]);
    }
    $onlyNew = null;
    if ($action === 'show_new') {
        // الجداد بس من بحث محفوظ، وبعدها بيتعلّموا إنهم اتشافوا
        $st = db()->prepare('SELECT filters, new_ids FROM crm_searches WHERE id = ? AND workspace_id = ?'); $st->execute([(int)($_POST['id'] ?? 0), $wid]);
        $s = $st->fetch(); if (!$s) $json(['ok' => false, 'error' => t('البحث ده مش موجود.')]);
        $filters = json_decode((string)$s['filters'], true) ?: [];
        $onlyNew = array_flip((array)json_decode((string)$s['new_ids'], true));
        db()->prepare('UPDATE crm_searches SET new_ids = NULL, new_count = 0 WHERE id = ?')->execute([(int)$_POST['id']]);
        $action = 'search';
    }
    if ($action === 'search') {
        try {
            $r = crm_people_search($filters, (int)($_POST['page'] ?? 1));
            if ($onlyNew !== null) {
                $more = (count($r['people']) >= 25) ? crm_people_search($filters, 2)['people'] : [];
                $r['people'] = array_values(array_filter(array_merge($r['people'], $more), function ($p) use ($onlyNew) { return isset($onlyNew[$p['id']]); }));
                $r['total'] = count($r['people']); $r['onlyNew'] = true;
            }
        } catch (CrmException $ex) {
            $json(['ok' => false, 'error' => $ex->getMessage(), 'plan' => $ex instanceof CrmPlanException]);
        }
        // مين اتكشف قبل كده
        $ids = array_column($r['people'], 'id');
        $known = [];
        if ($ids) {
            $st = db()->prepare('SELECT id, ext_id FROM crm_leads WHERE workspace_id = ? AND ext_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $st->execute(array_merge([$wid], $ids));
            foreach ($st->fetchAll() as $x) $known[$x['ext_id']] = (int)$x['id'];
        }
        foreach ($r['people'] as &$p) $p['lead'] = $known[$p['id']] ?? 0; unset($p);
        $json(['ok' => true] + $r + ['left' => max(0, (int)$prefs['daily_limit'] - crm_reveals_today($uid)), 'searches' => $onlyNew !== null ? crm_searches() : null, 'filters' => $filters]);
    }
    if ($action === 'reveal') {
        if (!$canReveal) $json(['ok' => false, 'error' => t('مالكش صلاحية الكشف.')]);
        $people = array_slice((array)json_decode((string)($_POST['people'] ?? ''), true), 0, 25);
        $list = mb_substr(trim((string)($_POST['list'] ?? '')), 0, 30);
        $done = []; $err = null;
        foreach ($people as $p) {
            $pid = (string)($p['id'] ?? ''); if (!preg_match('/^[\w-]{1,64}$/', $pid)) continue;
            $st = db()->prepare('SELECT id FROM crm_leads WHERE workspace_id = ? AND ext_id = ? LIMIT 1');
            $st->execute([$wid, $pid]);
            if ($id = (int)$st->fetchColumn()) { $done[$pid] = $id; if ($list !== '') crm_add_tag($id, $list); continue; } // مانصرفش كريديت تاني
            if ((int)$prefs['daily_limit'] - crm_reveals_today($uid) <= 0) { $err = t('خلصت حد الكشف بتاعك النهارده (%s). بيتجدد بكرة.', (int)$prefs['daily_limit']); break; }
            try {
                $l = crm_reveal(['id' => 0, 'name' => mb_substr((string)($p['company'] ?? ''), 0, 255), 'country' => (string)($p['country'] ?? '')], $pid, $prefs);
                $done[$pid] = (int)$l['id'];
                if ($list !== '') crm_add_tag((int)$l['id'], $list);
            } catch (CrmException $ex) { $err = $ex->getMessage(); break; }
        }
        $json(['ok' => true, 'done' => $done, 'error' => $err, 'left' => max(0, (int)$prefs['daily_limit'] - crm_reveals_today($uid))]);
    }
    $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
}

$icp = $prefs['icp'];
render(t('ابحث عن أشخاص'), 'crm_find', function () use ($icp, $seniorL, $canReveal, $prefs, $uid) {
    $left = max(0, (int)$prefs['daily_limit'] - crm_reveals_today($uid));
    ?>
    <div class="grid lg:grid-cols-[300px,1fr] gap-5">
      <form id="fd-form" class="card p-4 space-y-3 self-start lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-400">👤 <?= e(t('مين؟')) ?></div>
        <label class="block"><span class="label"><?= e(t('المسمى الوظيفي')) ?></span><input class="input" name="titles" value="<?= e($icp['titles'] ?? '') ?>" placeholder="<?= e(t('مثلاً: Owner, Marketing Manager')) ?>"></label>
        <div><span class="label"><?= e(t('المستوى')) ?></span><div class="flex flex-wrap gap-1.5">
          <?php foreach ($seniorL as $k => $v): ?><label class="cursor-pointer"><input type="checkbox" name="seniorities[]" value="<?= e($k) ?>" class="peer sr-only"><span class="inline-block px-2 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600"><?= e($v) ?></span></label><?php endforeach; ?></div></div>
        <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-400 pt-2 border-t border-ink-100">📍 <?= e(t('فين؟')) ?></div>
        <label class="block"><span class="label"><?= e(t('الدولة أو المدينة')) ?></span><input class="input" name="locations" dir="auto" value="<?= e(implode(', ', (array)($icp['locations'] ?? []))) ?>" placeholder="Egypt, Riyadh"></label>
        <div class="text-[11px] font-semibold uppercase tracking-wide text-ink-400 pt-2 border-t border-ink-100">🏢 <?= e(t('الشركة')) ?></div>
        <div><span class="label"><?= e(t('حجم الشركة')) ?></span><div class="flex flex-wrap gap-1.5">
          <?php foreach (crm_sizes() as $k => $v): ?><label class="cursor-pointer"><input type="checkbox" name="sizes[]" value="<?= e($k) ?>" class="peer sr-only" <?= in_array($k, (array)($icp['sizes'] ?? []), true) ? 'checked' : '' ?>><span class="inline-block px-2 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600" dir="ltr"><?= e($v) ?></span></label><?php endforeach; ?></div></div>
        <div><span class="label"><?= e(t('إيرادات الشركة السنوية ($)')) ?></span><div class="grid grid-cols-2 gap-2"><input class="input" name="rev_min" inputmode="numeric" dir="ltr" placeholder="<?= e(t('من')) ?>"><input class="input" name="rev_max" inputmode="numeric" dir="ltr" placeholder="<?= e(t('لحد')) ?>"></div></div>
        <label class="block"><span class="label"><?= e(t('مجال أو كلمات')) ?></span><input class="input" name="keywords" value="<?= e($icp['keywords'] ?? '') ?>" placeholder="<?= e(t('مثلاً: restaurants')) ?>"></label>
        <label class="block"><span class="label"><?= e(t('دومينات شركات معينة (اختياري)')) ?></span><input class="input" name="domains" dir="ltr" placeholder="company.com, other.com"></label>
        <button type="button" id="fd-look" class="btn-ghost w-full text-sm" title="<?= e(t('بيبص على العملاء اللي كسبتهم ويملا الفلاتر بأكتر حاجات متكررة فيهم')) ?>">✨ <?= e(t('هاتلي ناس شبه أحسن عملائي')) ?></button>
        <div class="flex gap-2"><button class="btn-primary flex-1" id="fd-go">🔎 <?= e(t('ابحث')) ?></button><button type="button" id="fd-save" class="btn-ghost" title="<?= e(t('احفظ البحث ده وهيتعاد كل يوم')) ?>">☆ <?= e(t('احفظ')) ?></button></div>
        <p class="text-[11px] text-ink-500"><?= e(t('البحث مابيصرفش كريديت. الكريديت بيتصرف لما تكشف رقم أو إيميل.')) ?></p>
      </form>
      <div>
        <div id="fd-saved" class="mb-4"></div>
        <div id="fd-bar" class="sticky top-16 z-20 flex flex-wrap items-center gap-2 mb-3 rounded-2xl border border-ink-200 bg-white/95 backdrop-blur px-3 py-2 shadow-sm">
          <label class="inline-flex items-center gap-1.5 text-sm"><input type="checkbox" id="fd-all" class="rounded"><span id="fd-total" class="text-ink-700 font-medium"></span></label>
          <span class="text-xs rounded-full bg-ink-100 text-ink-600 px-2.5 py-1 ms-auto"><?= e(t('فاضلك النهارده')) ?>: <b id="fd-left"><?= $left ?></b></span>
          <?php if ($canReveal): ?><input id="fd-list-name" list="fd-lists" class="input !w-40 !py-1.5 text-sm" maxlength="30" placeholder="<?= e(t('ضيفهم لقايمة (اختياري)')) ?>"><datalist id="fd-lists"><?php foreach (crm_tag_names() as $tg): ?><option value="<?= e($tg) ?>"><?php endforeach; ?></datalist>
          <button type="button" id="fd-reveal" class="btn-primary" disabled></button><?php endif; ?>
        </div>
        <div id="fd-list" class="card divide-y divide-ink-100 overflow-hidden"><div class="p-12 text-center"><div class="text-4xl mb-2">🔎</div><div class="text-sm text-ink-600"><?= e(t('اختار الفلاتر ودوس ابحث.')) ?></div><div class="text-[11px] text-ink-400 mt-1"><?= e(t('أو جرّب "هاتلي ناس شبه أحسن عملائي"')) ?></div></div></div>
        <div class="flex justify-center gap-2 mt-3"><button type="button" id="fd-prev" class="btn-ghost hidden">‹</button><span id="fd-page" class="text-sm text-ink-500 self-center"></span><button type="button" id="fd-next" class="btn-ghost hidden">›</button></div>
      </div>
    </div>
    <script>window.FD = <?= json_encode(['searches' => crm_searches(), 'url' => url('crm_find'), 'csrf' => csrf_token(), 'leads' => url('crm_leads'), 'canReveal' => $canReveal,
        'tr' => ['hPerson' => t('الشخص'), 'hCompany' => t('الشركة'), 'hPlace' => t('المكان'), 'hData' => t('البيانات المتاحة'), 'total' => t('%s شخص'), 'none' => t('مفيش نتايج. وسّع الفلاتر.'), 'reveal' => t('اكشف %s وضيفهم للـ Leads'), 'revealing' => t('بنكشف…'), 'inLeads' => t('في الـ Leads'),
            'email' => t('إيميل'), 'phone' => t('رقم'), 'done' => t('اتكشف %s واتضافوا للـ Leads. الأرقام ممكن تاخد دقايق توصل.'), 'err' => t('حصل خطأ، جرب تاني'), 'searching' => t('بندوّر…'),
            'plan' => t('خطة Apollo بتاعتك مش بتسمح بالبحث ده. اتأكد إنك رقّيت الخطة وإن المفتاح Master Key.'), 'emp' => t('موظف'), 'confirm' => t('هيتصرف لحد %s كريديت من Apollo. تكمل؟'),
            'lookNo' => t('محتاجين عميلين على الأقل "بقوا عملاء" أو "مهتمين" عشان نعرف شبههم.'), 'lookWon' => t('الفلاتر اتملت من %s عميل كسبتهم. عدّلها لو حابب ودوس ابحث.'), 'lookWarm' => t('الفلاتر اتملت من %s عميل مهتمين (لسه مفيش عملاء كفاية كسبتهم).'),
            'saved' => t('بحوث محفوظة · بتتعاد كل يوم وتطلّعلك الجداد'), 'newN' => t('%s جديد'), 'noNew' => t('مفيش جداد'), 'askName' => t('اسم للبحث ده؟ (مثلاً: مديرين تسويق في الرياض)'),
            'del' => t('امسح البحث ده؟'), 'run' => t('حدّث'), 'newOnly' => t('الجداد بس من: %s'), 'last' => t('آخر تحديث: %s'), 'load' => t('افتح الفلاتر')]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-crm_find.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
