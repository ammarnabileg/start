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
    if ($action === 'search') {
        try {
            $r = crm_people_search(['titles' => $_POST['titles'] ?? '', 'seniorities' => (array)($_POST['seniorities'] ?? []), 'locations' => $_POST['locations'] ?? '',
                'sizes' => (array)($_POST['sizes'] ?? []), 'keywords' => $_POST['keywords'] ?? '', 'domains' => $_POST['domains'] ?? ''], (int)($_POST['page'] ?? 1));
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
        $json(['ok' => true] + $r + ['left' => max(0, (int)$prefs['daily_limit'] - crm_reveals_today($uid))]);
    }
    if ($action === 'reveal') {
        if (!$canReveal) $json(['ok' => false, 'error' => t('مالكش صلاحية الكشف.')]);
        $people = array_slice((array)json_decode((string)($_POST['people'] ?? ''), true), 0, 25);
        $done = []; $err = null;
        foreach ($people as $p) {
            $pid = (string)($p['id'] ?? ''); if (!preg_match('/^[\w-]{1,64}$/', $pid)) continue;
            $st = db()->prepare('SELECT id FROM crm_leads WHERE workspace_id = ? AND ext_id = ? LIMIT 1');
            $st->execute([$wid, $pid]);
            if ($id = (int)$st->fetchColumn()) { $done[$pid] = $id; continue; } // مانصرفش كريديت تاني
            if ((int)$prefs['daily_limit'] - crm_reveals_today($uid) <= 0) { $err = t('خلصت حد الكشف بتاعك النهارده (%s). بيتجدد بكرة.', (int)$prefs['daily_limit']); break; }
            try {
                $l = crm_reveal(['id' => 0, 'name' => mb_substr((string)($p['company'] ?? ''), 0, 255), 'country' => (string)($p['country'] ?? '')], $pid, $prefs);
                $done[$pid] = (int)$l['id'];
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
      <form id="fd-form" class="card p-4 space-y-3 self-start lg:sticky lg:top-20">
        <label class="block"><span class="label"><?= e(t('المسمى الوظيفي')) ?></span><input class="input" name="titles" value="<?= e($icp['titles'] ?? '') ?>" placeholder="<?= e(t('مثلاً: Owner, Marketing Manager')) ?>"></label>
        <div><span class="label"><?= e(t('المستوى')) ?></span><div class="flex flex-wrap gap-1.5">
          <?php foreach ($seniorL as $k => $v): ?><label class="cursor-pointer"><input type="checkbox" name="seniorities[]" value="<?= e($k) ?>" class="peer sr-only"><span class="inline-block px-2 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600"><?= e($v) ?></span></label><?php endforeach; ?></div></div>
        <label class="block"><span class="label"><?= e(t('الدولة أو المدينة')) ?></span><input class="input" name="locations" dir="auto" value="<?= e(implode(', ', (array)($icp['locations'] ?? []))) ?>" placeholder="Egypt, Riyadh"></label>
        <div><span class="label"><?= e(t('حجم الشركة')) ?></span><div class="flex flex-wrap gap-1.5">
          <?php foreach (crm_sizes() as $k => $v): ?><label class="cursor-pointer"><input type="checkbox" name="sizes[]" value="<?= e($k) ?>" class="peer sr-only" <?= in_array($k, (array)($icp['sizes'] ?? []), true) ? 'checked' : '' ?>><span class="inline-block px-2 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600" dir="ltr"><?= e($v) ?></span></label><?php endforeach; ?></div></div>
        <label class="block"><span class="label"><?= e(t('مجال أو كلمات')) ?></span><input class="input" name="keywords" value="<?= e($icp['keywords'] ?? '') ?>" placeholder="<?= e(t('مثلاً: restaurants')) ?>"></label>
        <label class="block"><span class="label"><?= e(t('دومينات شركات معينة (اختياري)')) ?></span><input class="input" name="domains" dir="ltr" placeholder="company.com, other.com"></label>
        <button class="btn-primary w-full" id="fd-go">🔎 <?= e(t('ابحث')) ?></button>
        <p class="text-[11px] text-ink-500"><?= e(t('البحث مابيصرفش كريديت. الكريديت بيتصرف لما تكشف رقم أو إيميل.')) ?></p>
      </form>
      <div>
        <div class="flex flex-wrap items-center gap-2 mb-3">
          <span id="fd-total" class="text-sm text-ink-600"></span>
          <span class="text-xs rounded-full bg-ink-100 text-ink-600 px-2.5 py-1 ms-auto"><?= e(t('فاضلك النهارده')) ?>: <b id="fd-left"><?= $left ?></b></span>
          <?php if ($canReveal): ?><button type="button" id="fd-reveal" class="btn-primary" disabled></button><?php endif; ?>
        </div>
        <div id="fd-list" class="card divide-y divide-ink-100 overflow-hidden"><div class="p-10 text-center text-sm text-ink-500"><?= e(t('اختار الفلاتر ودوس ابحث.')) ?></div></div>
        <div class="flex justify-center gap-2 mt-3"><button type="button" id="fd-prev" class="btn-ghost hidden">‹</button><span id="fd-page" class="text-sm text-ink-500 self-center"></span><button type="button" id="fd-next" class="btn-ghost hidden">›</button></div>
      </div>
    </div>
    <script>window.FD = <?= json_encode(['url' => url('crm_find'), 'csrf' => csrf_token(), 'leads' => url('crm_leads'), 'canReveal' => $canReveal,
        'tr' => ['total' => t('%s شخص'), 'none' => t('مفيش نتايج. وسّع الفلاتر.'), 'reveal' => t('اكشف %s وضيفهم للـ Leads'), 'revealing' => t('بنكشف…'), 'inLeads' => t('في الـ Leads'),
            'email' => t('إيميل'), 'phone' => t('رقم'), 'done' => t('اتكشف %s واتضافوا للـ Leads. الأرقام ممكن تاخد دقايق توصل.'), 'err' => t('حصل خطأ، جرب تاني'), 'searching' => t('بندوّر…'),
            'plan' => t('خطة Apollo بتاعتك مش بتسمح بالبحث ده. اتأكد إنك رقّيت الخطة وإن المفتاح Master Key.'), 'emp' => t('موظف'), 'confirm' => t('هيتصرف لحد %s كريديت من Apollo. تكمل؟')]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-crm_find.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
