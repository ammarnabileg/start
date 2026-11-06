<?php
/** العملاء المستهدفين: كل الليدز من كل مكان في المنصة، بفلاتر، واستيراد ملف، وبحث Apollo */
$wid = (int)ws('id');
$uid = (int)(current_user()['id'] ?? 0);
$canManage = can('crm.manage') || is_ws_owner();
// ملف Excel: السيرفر بيقرا الصفوف، والمتصفح بيكمّل ربط الأعمدة زي CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'parse_xlsx') {
    header('Content-Type: application/json; charset=utf-8');
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || $f['size'] > 10 * 1048576) { echo json_encode(['ok' => false, 'error' => t('الملف كبير أو مارفعش (الحد 10 ميجا).')], JSON_UNESCAPED_UNICODE); exit; }
    try { $rows = xlsx_rows($f['tmp_name']); } catch (Throwable $e) { echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE); exit; }
    echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE); exit;
}
$where = 'l.workspace_id = ?'; $args = [$wid];
if (!$canManage) { $where .= ' AND (l.owner_id = ? OR l.owner_id IS NULL)'; $args[] = $uid; }
$st = db()->prepare("SELECT l.id, l.name, l.company, l.title, l.email, l.phone, l.source, l.stage, l.owner_id, l.tags, l.country, l.created_at, l.last_contact_at FROM crm_leads l WHERE $where ORDER BY l.id DESC LIMIT 5000");
$st->execute($args); $rows = $st->fetchAll();
$sources = crm_sources(); $stages = crm_stages();
$members = []; foreach (crm_members() as $m) $members[(int)$m['id']] = $m['name'];
$cnt = ['src' => [], 'stage' => [], 'country' => [], 'owner' => []];
foreach ($rows as $r) { foreach (['src' => $r['source'], 'stage' => $r['stage'], 'country' => trim((string)$r['country']), 'owner' => (string)(int)$r['owner_id']] as $k => $v) if ($v !== '') $cnt[$k][$v] = ($cnt[$k][$v] ?? 0) + 1; }
arsort($cnt['country']);
$hasApollo = crm_has_key();

render(t('العملاء المستهدفين'), 'prospects', function () use ($rows, $sources, $stages, $members, $cnt, $hasApollo, $canManage) {
    $stagePill = function ($s) use ($stages) { return '<span class="text-[11px] rounded-full bg-ink-100 text-ink-700 px-2 py-0.5 whitespace-nowrap">' . e($stages[$s] ?? $s) . '</span>'; };
    $sel = function (string $id, string $label, array $opts) { $h = '<label class="block text-xs text-ink-600">' . e($label) . '<select id="' . $id . '" class="input !mt-1 text-sm"><option value="">' . e(t('الكل')) . '</option>';
        foreach ($opts as $v => $l) $h .= '<option value="' . e((string)$v) . '">' . e($l) . '</option>'; return $h . '</select></label>'; };
    $withPhone = count(array_filter($rows, function ($r) { return $r['phone']; })); $withEmail = count(array_filter($rows, function ($r) { return $r['email']; }));
    $none = count(array_filter($rows, function ($r) { return !$r['phone'] && !$r['email']; }));
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <?php foreach (['' => [t('الكل'), count($rows)], 'phone' => [t('عندهم رقم'), $withPhone], 'email' => [t('عندهم إيميل'), $withEmail], 'none' => [t('من غير بيانات تواصل'), $none]] as $k => [$l, $n]): ?>
        <button type="button" data-has="<?= e($k) ?>" class="tg-chip<?= $k === '' ? ' on' : '' ?>"><?= e($l) ?> <span><?= (int)$n ?></span></button>
      <?php endforeach; ?>
      <span class="ms-auto"></span>
      <input type="search" id="pr-q" class="input !mt-0 !w-44 text-sm" placeholder="<?= e(t('اسم، شركة، رقم، إيميل…')) ?>">
      <button type="button" popovertarget="pr-filters" class="btn-ghost"><?= ui_icon('filter', 'w-4 h-4') ?><?= e(t('فلاتر')) ?><span id="pr-fn" class="hidden rounded-full bg-brand-600 text-[#fff] text-[11px] px-1.5"></span></button>
      <button type="button" id="pr-import-btn" class="btn-ghost"><?= ui_icon('upload', 'w-4 h-4') ?><?= e(t('استيراد ملف')) ?></button>
      <button type="button" id="pr-find-btn" class="btn-primary"><?= ui_icon('search', 'w-4 h-4') ?><?= e(t('ابحث عن شخص')) ?></button>
    </div>

    <div id="pr-filters" popover class="m-0 w-[min(560px,94vw)] rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl">
      <div class="grid sm:grid-cols-2 gap-3">
        <?= $sel('pf-src', t('المصدر'), array_map(function ($k) use ($sources, $cnt) { return ($sources[$k] ?? $k) . ' (' . $cnt['src'][$k] . ')'; }, array_combine(array_keys($cnt['src']), array_keys($cnt['src'])))) ?>
        <?= $sel('pf-stage', t('المرحلة'), array_map(function ($k) use ($stages, $cnt) { return ($stages[$k] ?? $k) . ' (' . $cnt['stage'][$k] . ')'; }, array_combine(array_keys($cnt['stage']), array_keys($cnt['stage'])))) ?>
        <?php if ($canManage): ?><?= $sel('pf-owner', t('المسؤول'), ['0' => t('من غير مسؤول')] + $members) ?><?php endif; ?>
        <?= $sel('pf-country', t('الدولة'), array_combine(array_keys($cnt['country']), array_keys($cnt['country']))) ?>
        <label class="block text-xs text-ink-600"><?= e(t('اتضاف من')) ?><input type="date" id="pf-from" class="input !mt-1 text-sm"></label>
        <label class="block text-xs text-ink-600"><?= e(t('لحد')) ?><input type="date" id="pf-to" class="input !mt-1 text-sm"></label>
        <label class="block text-xs text-ink-600 sm:col-span-2"><?= e(t('تاج أو شركة')) ?><input id="pf-tag" class="input !mt-1 text-sm" dir="auto"></label>
      </div>
      <div class="flex justify-between items-center mt-3"><button type="button" id="pf-reset" class="text-xs link"><?= e(t('امسح الفلاتر')) ?></button><span id="pf-count" class="text-xs text-ink-500"></span></div>
    </div>

    <?php if (!$rows): ?>
      <div class="card p-8 text-center text-sm text-ink-500"><?= e(t('لسه مفيش عملاء. أي حد يكلمك من الرسائل أو التعليقات أو الفورم أو الإعلانات هيظهر هنا لوحده، أو استورد ملف، أو دوّر على شخص.')) ?></div>
    <?php else: ?>
    <div class="card overflow-x-auto">
      <table class="w-full text-sm" id="pr-table" data-no-search>
        <thead><tr class="text-xs text-ink-500 border-b border-ink-100 bg-ink-50/60">
          <th class="text-start font-medium px-4 py-2.5"><?= e(t('الاسم')) ?></th><th class="text-start font-medium px-3 py-2.5"><?= e(t('الشركة')) ?></th>
          <th class="text-start font-medium px-3 py-2.5"><?= e(t('التواصل')) ?></th><th class="text-start font-medium px-3 py-2.5"><?= e(t('المصدر')) ?></th>
          <th class="text-start font-medium px-3 py-2.5"><?= e(t('المرحلة')) ?></th><?php if ($canManage): ?><th class="text-start font-medium px-3 py-2.5"><?= e(t('المسؤول')) ?></th><?php endif; ?>
          <th class="text-start font-medium px-3 py-2.5"><?= e(t('اتضاف')) ?></th></tr></thead>
        <tbody id="pr-body" class="divide-y divide-ink-100">
        <?php foreach ($rows as $r): $has = $r['phone'] || $r['email']; ?>
          <tr class="pr-row cursor-pointer hover:bg-brand-50/40 <?= $has ? '' : 'text-ink-500' ?>" data-href="<?= e(url('crm_leads', ['lead' => (int)$r['id']])) ?>"
            data-src="<?= e($r['source']) ?>" data-stage="<?= e($r['stage']) ?>" data-owner="<?= (int)$r['owner_id'] ?>" data-country="<?= e(trim((string)$r['country'])) ?>" data-date="<?= e(substr((string)$r['created_at'], 0, 10)) ?>"
            data-phone="<?= $r['phone'] ? 1 : 0 ?>" data-email="<?= $r['email'] ? 1 : 0 ?>" data-q="<?= e(mb_strtolower($r['name'] . ' ' . $r['company'] . ' ' . $r['title'] . ' ' . $r['phone'] . ' ' . $r['email'] . ' ' . $r['tags'])) ?>">
            <td class="px-4 py-2.5"><span class="flex items-center gap-2.5"><span class="w-8 h-8 rounded-full grid place-items-center text-xs font-bold shrink-0 <?= $has ? 'bg-lime-50 text-lime-800' : 'bg-ink-100 text-ink-500' ?>"><?= e(mb_strtoupper(mb_substr((string)$r['name'], 0, 1))) ?></span>
              <span class="min-w-0"><b class="block truncate <?= $has ? 'text-ink-900' : 'font-medium text-ink-600' ?>"><?= e($r['name']) ?></b><?php if ($r['title']): ?><span class="block text-xs text-ink-500 truncate"><?= e($r['title']) ?></span><?php endif; ?></span></span></td>
            <td class="px-3 py-2.5 truncate max-w-[200px]"><?= e($r['company'] ?: '—') ?></td>
            <td class="px-3 py-2.5"><span class="flex flex-col gap-0.5 text-xs" dir="ltr"><?php if ($r['phone']): ?><span class="text-lime-700 inline-flex items-center gap-1"><?= ui_icon('phone', 'w-3.5 h-3.5') ?><?= e($r['phone']) ?></span><?php endif; ?><?php if ($r['email']): ?><span class="text-brand-700 inline-flex items-center gap-1 truncate max-w-[220px]"><?= ui_icon('mail', 'w-3.5 h-3.5') ?><?= e($r['email']) ?></span><?php endif; ?><?php if (!$has): ?><span class="text-ink-400">—</span><?php endif; ?></span></td>
            <td class="px-3 py-2.5 whitespace-nowrap text-xs"><?= e($sources[$r['source']] ?? $r['source']) ?></td>
            <td class="px-3 py-2.5"><?= $stagePill($r['stage']) ?></td>
            <?php if ($canManage): ?><td class="px-3 py-2.5 text-xs whitespace-nowrap"><?= e($members[(int)$r['owner_id']] ?? '—') ?></td><?php endif; ?>
            <td class="px-3 py-2.5 text-xs whitespace-nowrap" data-sort="<?= e((string)$r['created_at']) ?>"><?= e(fmt_date($r['created_at'], 'd/m/Y')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p id="pr-empty" class="hidden p-6 text-center text-sm text-ink-500"><?= e(t('مفيش عملاء بالفلاتر دي.')) ?></p>
    </div>
    <?php endif; ?>

    <dialog id="pr-import" class="w-[min(620px,96vw)] rounded-2xl p-0 shadow-2xl backdrop:bg-ink-900/40">
      <div class="p-5 border-b border-ink-100 flex items-center"><b class="flex-1 text-ink-900"><?= e(t('استيراد عملاء من ملف')) ?></b><button type="button" data-close class="p-2 rounded-xl text-ink-400 hover:bg-ink-100"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
      <div class="p-5 space-y-3 text-sm">
        <p class="text-xs text-ink-500"><?= e(t('ملف Excel (.xlsx أو .xls) أو CSV فيه سطر للعناوين. الأعمدة بتتعرف لوحدها: الاسم، الموبايل، الإيميل، الشركة، المسمى، الدولة، تاجات، ملاحظة. أي صف من غير رقم ولا إيميل بيتشال، والمكرر بيتدمج.')) ?></p>
        <label class="block rounded-xl border-2 border-dashed border-ink-200 p-6 text-center cursor-pointer hover:border-brand-400 hover:bg-brand-50/40"><input type="file" id="pr-file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" class="hidden"><?= ui_icon('upload', 'w-6 h-6 mx-auto text-ink-400') ?><span class="block mt-2 text-ink-600" id="pr-file-l"><?= e(t('اختار الملف أو اسحبه هنا')) ?></span></label>
        <div id="pr-map" class="hidden space-y-2"></div>
        <button type="button" id="pr-import-go" class="btn-primary w-full hidden"></button>
      </div>
    </dialog>

    <dialog id="pr-find" class="w-[min(760px,96vw)] max-h-[88vh] rounded-2xl p-0 shadow-2xl backdrop:bg-ink-900/40">
      <div class="p-5 border-b border-ink-100 flex items-center sticky top-0 bg-white z-10"><b class="flex-1 text-ink-900"><?= e(t('ابحث عن شخص في Apollo')) ?></b><button type="button" data-close class="p-2 rounded-xl text-ink-400 hover:bg-ink-100"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
      <div class="p-5 space-y-3 text-sm">
        <?php if (!$hasApollo): ?><p class="text-ink-500"><?= e(t('اربط Apollo من التكاملات عشان تدوّر على أشخاص.')) ?> <a href="<?= e(url('connectors')) ?>" class="link"><?= e(t('فتح التكاملات')) ?></a></p>
        <?php else: ?>
        <form id="pr-find-f" class="grid sm:grid-cols-3 gap-2" data-no-ajax>
          <input name="titles" class="input !mt-0 text-sm" dir="auto" placeholder="<?= e(t('المسمى (مثلًا: مدير تسويق)')) ?>">
          <input name="keywords" class="input !mt-0 text-sm" dir="auto" placeholder="<?= e(t('الاسم أو الشركة أو المجال')) ?>">
          <input name="locations" class="input !mt-0 text-sm" dir="auto" placeholder="<?= e(t('الدولة أو المدينة')) ?>">
          <button class="btn-primary sm:col-span-3"><?= ui_icon('search', 'w-4 h-4') ?><?= e(t('ابحث')) ?></button>
        </form>
        <div id="pr-find-res" class="space-y-2"></div>
        <div id="pr-find-bar" class="hidden sticky bottom-0 bg-white pt-2 flex items-center gap-2 border-t border-ink-100"><span id="pr-find-n" class="text-xs text-ink-500 flex-1"></span><button type="button" id="pr-find-more" class="btn-ghost !text-xs"><?= e(t('نتايج أكتر')) ?></button><button type="button" id="pr-reveal" class="btn-primary !text-xs" disabled><?= e(t('اكشف المحددين وضيفهم')) ?></button></div>
        <?php endif; ?>
      </div>
    </dialog>
    <style>.tg-chip{font-size:.8125rem;border-radius:9999px;border:1px solid rgb(226 232 240);padding:.2rem .6rem;color:rgb(71 85 105)}.tg-chip span{opacity:.6;margin-inline-start:.25rem}.tg-chip.on{background:rgb(15 23 42);border-color:rgb(15 23 42);color:#fff}dialog[open]{animation:prIn .18s ease-out}@keyframes prIn{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}</style>
    <script>window.PR = <?= json_encode(['csrf' => csrf_token(), 'importUrl' => url('crm_leads'), 'selfUrl' => url('prospects'), 'xlsLib' => 'assets/vendor/xlsx-0.20.3.full.min.js?v=' . APP_VERSION, 'findUrl' => url('crm_find'),
        'L' => ['shown' => t('%s من %s'), 'pick' => t('اختار الملف أو اسحبه هنا'), 'rows' => t('%s صف في الملف'), 'go' => t('استورد %s عميل'), 'busy' => t('بنستورد…'), 'done' => t('اتضاف %s جديد، واتدمج %s، واتشال %s'),
        'cols' => ['name' => t('الاسم'), 'phone' => t('الموبايل'), 'email' => t('الإيميل'), 'company' => t('الشركة'), 'title' => t('المسمى'), 'country' => t('الدولة'), 'tags' => t('تاجات'), 'note' => t('ملاحظة')], 'skip' => t('— تجاهل —'),
        'noRows' => t('الملف فاضي أو مش CSV ولا Excel.'), 'reading' => t('بنقرا الملف…'), 'searching' => t('بندوّر…'), 'found' => t('%s نتيجة'), 'nothing' => t('مفيش نتايج. جرّب كلمات تانية.'), 'have' => t('عندك'), 'revealing' => t('بنكشف…'), 'revealed' => t('اتضاف %s عميل'), 'err' => t('حصلت مشكلة.'), 'hasEmail' => t('فيه إيميل'), 'hasPhone' => t('فيه رقم')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/prospects.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
