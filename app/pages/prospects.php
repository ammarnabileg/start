<?php
/** العملاء المستهدفين: كل حد اتعامل معانا (رسالة، تعليق، فورم، حجز، حملة، إعلان) أو ضفناه بنفسنا (يدوي، استيراد، Apollo، واتساب) */
$wid = (int)ws('id');
$uid = (int)(current_user()['id'] ?? 0);
$canManage = can('crm.manage') || is_ws_owner();
$J = function (array $a, int $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    // ملف Excel: السيرفر بيقرا الشيتات، والمتصفح بيكمّل ربط الأعمدة زي CSV
    if ($a === 'parse_xlsx') {
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || $f['size'] > 10 * 1048576) $J(['ok' => false, 'error' => t('الملف كبير أو مارفعش (الحد 10 ميجا).')]);
        try { $J(['ok' => true, 'sheets' => xlsx_sheets($f['tmp_name'])]); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
    }
    // جمع كل اللي اتعاملوا معانا من الرسائل والتعليقات وواتساب والإعلانات
    if ($a === 'sync') {
        $fxd = getenv('UI_FIXTURES'); $z = $fxd && is_file($fxd . '/inbox_fake_z.php') ? require $fxd . '/inbox_fake_z.php' : zernio_or_null();
        if (!$z) $J(['ok' => true, 'new' => 0]);
        try { $n = tg_capture_all($z); } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
        $J(['ok' => true, 'new' => $n]);
    }
    $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
}

$where = 'workspace_id = ?'; $args = [$wid];
if (!$canManage) { $where .= ' AND (owner_id = ? OR owner_id IS NULL)'; $args[] = $uid; }
$st = db()->prepare("SELECT id, name, company, title, email, phone, source, stage, owner_id, tags, country, created_at, last_contact_at FROM crm_leads WHERE $where ORDER BY id DESC LIMIT 5000");
$st->execute($args); $rows = $st->fetchAll();
$sources = crm_sources(); $stages = crm_stages();
$members = []; foreach (crm_members() as $m) $members[(int)$m['id']] = $m['name'];
$cnt = ['src' => [], 'stage' => [], 'country' => []];
foreach ($rows as $r) { foreach (['src' => $r['source'], 'stage' => $r['stage'], 'country' => trim((string)$r['country'])] as $k => $v) if ($v !== '') $cnt[$k][$v] = ($cnt[$k][$v] ?? 0) + 1; }
arsort($cnt['src']); arsort($cnt['country']);
$week = date('Y-m-d', strtotime('-7 days'));
$stat = ['all' => count($rows), 'phone' => 0, 'email' => 0, 'none' => 0, 'new' => 0];
foreach ($rows as $r) { if ($r['phone']) $stat['phone']++; if ($r['email']) $stat['email']++; if (!$r['phone'] && !$r['email']) $stat['none']++; if (substr((string)$r['created_at'], 0, 10) >= $week) $stat['new']++; }
$hasApollo = crm_has_key();
$syncAt = explode('|', (string)setting('tg_sync_' . $wid, ''))[0];
$syncDue = $syncAt === '' || strtotime($syncAt) < time() - 20 * 60;
$srcIcon = ['inbox' => 'chat', 'comments' => 'chat', 'form' => 'file', 'ads' => 'megaphone', 'whatsapp' => 'chat', 'reveal' => 'eye', 'import' => 'upload', 'manual' => 'pencil', 'tender' => 'file'];

render(t('العملاء المستهدفين'), 'prospects', function () use ($rows, $sources, $stages, $members, $cnt, $stat, $hasApollo, $canManage, $syncDue, $srcIcon) {
    $cols = 'md:grid-cols-[22px_minmax(0,2fr)_minmax(0,1.3fr)_minmax(0,1.7fr)_minmax(0,1fr)_110px_84px_116px]';
    $sel = function (string $id, string $label, array $opts) { $h = '<label class="block text-xs text-ink-600">' . e($label) . '<select id="' . $id . '" class="input !mt-1 text-sm"><option value="">' . e(t('الكل')) . '</option>';
        foreach ($opts as $v => $l) $h .= '<option value="' . e((string)$v) . '">' . e($l) . '</option>'; return $h . '</select></label>'; };
    ?>
    <div id="pr" data-mk>
    <div class="mk-stats">
      <?php foreach ([['_all', '', t('الكل'), $stat['all'], 'users', 'bg-ink-100 text-ink-700'], ['phone', 'phone=1', t('لديهم رقم'), $stat['phone'], 'phone', 'bg-lime-50 text-lime-700'],
          ['email', 'email=1', t('لديهم بريد'), $stat['email'], 'mail', 'bg-brand-50 text-brand-700'], ['none', 'none=1', t('دون بيانات تواصل'), $stat['none'], 'user', 'bg-amber-50 text-amber-700'],
          ['new', 'new=1', t('جدد آخر 7 أيام'), $stat['new'], 'new', 'bg-cyan-50 text-cyan-700']] as [$k, $test, $l, $n, $ic, $cl]): ?>
        <button type="button" class="mk-stat<?= $k === '_all' ? ' mk-on' : '' ?>" data-mk-stat="<?= $k ?>" data-mk-test="<?= e($test) ?>"><span class="mk-ic <?= $cl ?>"><?= ui_icon($ic, 'w-5 h-5') ?></span><span><b><?= number_format($n) ?></b><small><?= e($l) ?></small></span></button>
      <?php endforeach; ?>
    </div>

    <div class="mk-tools">
      <label class="mk-search"><?= ui_icon('search') ?><input type="search" data-mk-q placeholder="<?= e(t('اسم، شركة، رقم، إيميل، تاج…')) ?>"><kbd>/</kbd></label>
      <button type="button" popovertarget="pr-filters" class="btn-ghost"><?= ui_icon('filter', 'w-4 h-4') ?><?= e(t('فلاتر')) ?><span id="pr-fn" class="hidden rounded-full bg-brand-600 text-[#fff] text-[11px] px-1.5"></span></button>
      <span class="text-xs text-ink-500 tabular-nums" title="<?= e(t('معروض')) ?>"><span data-mk-shown></span></span>
      <span id="pr-sync" class="text-xs text-ink-400 hidden"><?= ui_icon('refresh', 'w-3.5 h-3.5 inline animate-spin') ?> <?= e(t('بنجيب الجديد…')) ?></span>
      <span class="ms-auto"></span>
      <button type="button" id="pr-import-btn" class="btn-ghost"><?= ui_icon('upload', 'w-4 h-4') ?><?= e(t('استيراد ملف')) ?></button>
      <button type="button" id="pr-find-btn" class="btn-primary"><?= ui_icon('search', 'w-4 h-4') ?><?= e(t('ابحث عن شخص')) ?></button>
    </div>

    <div id="pr-filters" popover class="m-0 w-[min(560px,94vw)] rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl">
      <div class="grid sm:grid-cols-2 gap-3">
        <?= $sel('pf-src', t('المصدر'), array_combine(array_keys($cnt['src']), array_map(function ($k) use ($sources, $cnt) { return ($sources[$k] ?? $k) . ' (' . $cnt['src'][$k] . ')'; }, array_keys($cnt['src'])))) ?>
        <?= $sel('pf-stage', t('المرحلة'), array_combine(array_keys($cnt['stage']), array_map(function ($k) use ($stages, $cnt) { return ($stages[$k] ?? $k) . ' (' . $cnt['stage'][$k] . ')'; }, array_keys($cnt['stage'])))) ?>
        <?php if ($canManage): ?><?= $sel('pf-owner', t('المسؤول'), ['0' => t('من غير مسؤول')] + $members) ?><?php endif; ?>
        <?= $sel('pf-country', t('الدولة'), array_combine(array_keys($cnt['country']), array_keys($cnt['country']))) ?>
        <label class="block text-xs text-ink-600"><?= e(t('اتضاف من')) ?><input type="date" id="pf-from" class="input !mt-1 text-sm"></label>
        <label class="block text-xs text-ink-600"><?= e(t('لحد')) ?><input type="date" id="pf-to" class="input !mt-1 text-sm"></label>
      </div>
      <div class="flex justify-between items-center mt-3"><button type="button" id="pf-reset" class="text-xs link"><?= e(t('امسح الفلاتر')) ?></button></div>
    </div>

    <div class="mk-table">
      <div class="mk-head <?= $cols ?>"><input type="checkbox" data-mk-all aria-label="<?= e(t('تحديد الكل')) ?>">
        <span data-mk-sort="name"><?= e(t('الاسم')) ?></span><span data-mk-sort="company"><?= e(t('الشركة')) ?></span><span><?= e(t('التواصل')) ?></span>
        <span data-mk-sort="src"><?= e(t('المصدر')) ?></span><span data-mk-sort="stage"><?= e(t('المرحلة')) ?></span><span data-mk-sort="date"><?= e(t('اتضاف')) ?></span><span></span></div>
      <div data-mk-body>
      <?php foreach ($rows as $r): $has = $r['phone'] || $r['email']; $wa = $r['phone'] ? crm_wa_digits($r['phone']) : '';
          $j = ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'title' => (string)$r['title'], 'company' => (string)$r['company'], 'phone' => (string)$r['phone'], 'email' => (string)$r['email'], 'wa' => $wa,
            'src' => $sources[$r['source']] ?? $r['source'], 'stage' => $r['stage'], 'owner' => (int)$r['owner_id'], 'tags' => (string)$r['tags'], 'country' => (string)$r['country'],
            'added' => fmt_date($r['created_at'], 'd/m/Y'), 'last' => $r['last_contact_at'] ? fmt_date($r['last_contact_at'], 'd/m/Y') : '']; ?>
        <div class="mk-row <?= $cols ?>" data-mk-row data-id="<?= (int)$r['id'] ?>" data-phone="<?= $r['phone'] ? 1 : 0 ?>" data-email="<?= $r['email'] ? 1 : 0 ?>" data-none="<?= $has ? 0 : 1 ?>" data-new="<?= substr((string)$r['created_at'], 0, 10) >= date('Y-m-d', strtotime('-7 days')) ? 1 : 0 ?>"
          data-src="<?= e($r['source']) ?>" data-stage="<?= e($r['stage']) ?>" data-owner="<?= (int)$r['owner_id'] ?>" data-country="<?= e(trim((string)$r['country'])) ?>" data-date="<?= e(substr((string)$r['created_at'], 0, 10)) ?>"
          data-s-name="<?= e(mb_strtolower((string)$r['name'])) ?>" data-s-company="<?= e(mb_strtolower((string)$r['company'])) ?>" data-s-src="<?= e($r['source']) ?>" data-s-stage="<?= e($r['stage']) ?>" data-s-date="<?= e((string)$r['created_at']) ?>"
          data-q="<?= e(mb_strtolower($r['name'] . ' ' . $r['company'] . ' ' . $r['title'] . ' ' . $r['phone'] . ' ' . $r['email'] . ' ' . $r['tags'])) ?>" data-j="<?= e(json_encode($j, JSON_UNESCAPED_UNICODE)) ?>">
          <input type="checkbox" data-mk-check aria-label="<?= e(t('تحديد')) ?>">
          <span class="flex items-center gap-2.5 min-w-0"><span class="mk-av <?= $has ? '!bg-lime-50 !text-lime-800' : '' ?>"><?= e(mb_strtoupper(mb_substr((string)$r['name'], 0, 1))) ?></span>
            <span class="min-w-0"><b class="block truncate <?= $has ? 'text-ink-900' : 'font-medium text-ink-500' ?>"><?= e($r['name']) ?></b><?php if ($r['title']): ?><span class="block text-xs text-ink-500 truncate"><?= e($r['title']) ?></span><?php endif; ?></span></span>
          <span class="text-sm text-ink-600 truncate"><?= e($r['company'] ?: '—') ?></span>
          <span class="flex flex-col gap-0.5 text-xs min-w-0" dir="ltr"><?php if ($r['phone']): ?><span class="text-lime-700 inline-flex items-center gap-1 truncate"><?= ui_icon('phone', 'w-3.5 h-3.5 shrink-0') ?><?= e($r['phone']) ?></span><?php endif; ?><?php if ($r['email']): ?><span class="text-brand-700 inline-flex items-center gap-1 truncate"><?= ui_icon('mail', 'w-3.5 h-3.5 shrink-0') ?><?= e($r['email']) ?></span><?php endif; ?><?php if (!$has): ?><span class="text-ink-300">—</span><?php endif; ?></span>
          <span><span class="mk-pill bg-ink-100 text-ink-700"><?= ui_icon($srcIcon[$r['source']] ?? 'dots', 'w-3 h-3') ?><?= e($sources[$r['source']] ?? $r['source']) ?></span></span>
          <span><span class="mk-pill bg-brand-50 text-brand-700" data-stage-pill><?= e($stages[$r['stage']] ?? $r['stage']) ?></span></span>
          <span class="text-xs text-ink-500 tabular-nums"><?= e(fmt_date($r['created_at'], 'd/m/y')) ?></span>
          <span class="mk-acts">
            <?php if ($r['phone']): ?><a class="mk-act" href="tel:<?= e($r['phone']) ?>" title="<?= e(t('اتصال')) ?>"><?= ui_icon('phone', 'w-4 h-4') ?></a><?php endif; ?>
            <?php if ($wa): ?><a class="mk-act" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" title="<?= e(t('واتساب')) ?>"><?= ui_icon('chat', 'w-4 h-4') ?></a><?php endif; ?>
            <?php if ($r['email']): ?><a class="mk-act" href="mailto:<?= e($r['email']) ?>" title="<?= e(t('إيميل')) ?>"><?= ui_icon('mail', 'w-4 h-4') ?></a><?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
      </div>
      <p data-mk-empty class="hidden p-8 text-center text-sm text-ink-500"><?= e($rows ? t('مفيش عملاء بالفلاتر دي.') : t('لسه مفيش عملاء. أي حد يكلمك من الرسائل أو التعليقات أو الفورم أو الإعلانات هيظهر هنا لوحده، أو استورد ملف، أو دوّر على شخص.')) ?></p>
    </div>

    <div class="mk-bar" data-mk-bar>
      <span class="text-sm"><b data-mk-nsel>0</b> <?= e(t('محدد')) ?></span>
      <select id="pb-stage" aria-label="<?= e(t('المرحلة')) ?>"><option value=""><?= e(t('غيّر المرحلة…')) ?></option><?php foreach ($stages as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
      <?php if ($canManage): ?><select id="pb-owner" aria-label="<?= e(t('المسؤول')) ?>"><option value=""><?= e(t('عيّن مسؤول…')) ?></option><?php foreach ($members as $k => $l): ?><option value="<?= (int)$k ?>"><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?>
      <button type="button" id="pb-tag"><?= ui_icon('hash', 'w-4 h-4 inline') ?> <?= e(t('تاج')) ?></button>
      <button type="button" id="pb-csv"><?= ui_icon('download', 'w-4 h-4 inline') ?> <?= e(t('تصدير')) ?></button>
      <button type="button" data-mk-clear class="!px-2" title="<?= e(t('إلغاء التحديد')) ?>"><?= ui_icon('x', 'w-4 h-4') ?></button>
    </div>
    </div>

    <dialog id="pr-dlg" class="mk-dlg">
      <div class="mk-dlg-head"><span id="pd-av" class="mk-av !w-12 !h-12 !text-lg !bg-lime-50 !text-lime-800"></span><div class="flex-1 min-w-0"><b id="pd-name" class="block text-lg text-ink-900 truncate"></b><span id="pd-sub" class="text-sm text-ink-500"></span></div>
        <button type="button" data-close class="p-2 -m-1 rounded-xl text-ink-400 hover:text-ink-900 hover:bg-ink-100" aria-label="<?= e(t('إغلاق')) ?>"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
      <div class="p-5 space-y-4">
        <div id="pd-contact" class="flex flex-wrap gap-2"></div>
        <div class="grid sm:grid-cols-2 gap-3 text-sm">
          <label class="block text-xs text-ink-600"><?= e(t('المرحلة')) ?><select id="pd-stage" class="input !mt-1 text-sm"><?php foreach ($stages as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
          <?php if ($canManage): ?><label class="block text-xs text-ink-600"><?= e(t('المسؤول')) ?><select id="pd-owner" class="input !mt-1 text-sm"><option value="0"><?= e(t('من غير مسؤول')) ?></option><?php foreach ($members as $k => $l): ?><option value="<?= (int)$k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label><?php endif; ?>
        </div>
        <dl id="pd-meta" class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm"></dl>
        <div><span class="text-xs text-ink-600"><?= e(t('التاجات')) ?></span><div class="flex flex-wrap items-center gap-1.5 mt-1"><span id="pd-tags" class="contents"></span><form id="pd-tag-f" class="inline-flex m-0" data-no-ajax><input name="t" class="input !mt-0 !py-1 !w-32 text-xs" dir="auto" placeholder="<?= e(t('+ تاج')) ?>"></form></div></div>
        <div class="flex items-center justify-end pt-3 border-t border-ink-100"><a id="pd-open" href="#" class="btn-primary !py-1.5 !text-sm"><?= e(t('افتح ملفه الكامل')) ?></a></div>
      </div>
    </dialog>

    <dialog id="pr-import" class="mk-dlg !w-[min(620px,96vw)]">
      <div class="mk-dlg-head"><b class="flex-1 text-ink-900"><?= e(t('استيراد عملاء من ملف')) ?></b><button type="button" data-close class="p-2 rounded-xl text-ink-400 hover:bg-ink-100"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
      <div class="p-5 space-y-3 text-sm">
        <p class="text-xs text-ink-500"><?= e(t('ملف Excel (.xlsx أو .xls) أو CSV فيه سطر للعناوين. الأعمدة بتتعرف لوحدها: الاسم، الموبايل، الإيميل، الشركة، المسمى، الدولة، تاجات، ملاحظة. أي صف من غير رقم ولا إيميل بيتشال، والمكرر بيتدمج.')) ?></p>
        <label class="block rounded-2xl border-2 border-dashed border-ink-200 p-6 text-center cursor-pointer hover:border-brand-400 hover:bg-brand-50/40 transition"><input type="file" id="pr-file" accept=".csv,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" class="hidden"><?= ui_icon('upload', 'w-6 h-6 mx-auto text-ink-400') ?><span class="block mt-2 text-ink-600" id="pr-file-l"><?= e(t('اختار الملف أو اسحبه هنا')) ?></span></label>
        <div id="pr-map" class="hidden space-y-2"></div>
        <button type="button" id="pr-import-go" class="btn-primary w-full hidden"></button>
      </div>
    </dialog>

    <dialog id="pr-find" class="mk-dlg !w-[min(780px,96vw)]">
      <div class="mk-dlg-head"><b class="flex-1 text-ink-900"><?= e(t('ابحث عن شخص في Apollo')) ?></b><button type="button" data-close class="p-2 rounded-xl text-ink-400 hover:bg-ink-100"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
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
    <script>window.PR = <?= json_encode(['csrf' => csrf_token(), 'importUrl' => url('crm_leads'), 'selfUrl' => url('prospects'), 'findUrl' => url('crm_find'), 'leadUrl' => url('crm_leads'), 'syncDue' => $syncDue,
        'xlsLib' => 'assets/vendor/xlsx-0.20.3.full.min.js?v=' . APP_VERSION, 'stages' => $stages, 'members' => $members,
        'L' => ['pick' => t('اختار الملف أو اسحبه هنا'), 'rows' => t('%s صف في الملف'), 'sheets' => t('الشيتات'), 'sheetRows' => t('%s صف'), 'noSheet' => t('اختار شيت واحد على الأقل.'), 'go' => t('استورد %s عميل'), 'busy' => t('بنستورد…'), 'done' => t('اتضاف %s جديد، واتدمج %s، واتشال %s'),
        'cols' => ['name' => t('الاسم'), 'phone' => t('الموبايل'), 'email' => t('الإيميل'), 'company' => t('الشركة'), 'title' => t('المسمى'), 'country' => t('الدولة'), 'tags' => t('تاجات'), 'note' => t('ملاحظة')], 'skip' => t('— تجاهل —'),
        'noRows' => t('الملف فاضي أو مش CSV ولا Excel.'), 'reading' => t('بنقرا الملف…'), 'searching' => t('بندوّر…'), 'found' => t('%s نتيجة'), 'nothing' => t('مفيش نتايج. جرّب كلمات تانية.'), 'have' => t('عندك'), 'revealing' => t('بنكشف…'), 'revealed' => t('اتضاف %s عميل'),
        'err' => t('حصلت مشكلة.'), 'hasEmail' => t('فيه إيميل'), 'hasPhone' => t('فيه رقم'), 'synced' => t('اتضاف %s شخص جديد اتعامل معاك'), 'saved' => t('اتحفظ ✓'), 'tagAsk' => t('اكتب التاج'), 'done2' => t('اتعمل على %s'),
        'call' => t('اتصال'), 'wa' => t('واتساب'), 'mail' => t('إيميل'), 'src' => t('المصدر'), 'added' => t('اتضاف'), 'last' => t('آخر تواصل'), 'country' => t('الدولة'), 'none' => t('مفيش بيانات تواصل لسه')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/mk-list.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/prospects.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
