<?php
/** إدارة العملاء ← Leads: كل العملاء المحتملين في مكان واحد، قائمة أو لوحة، متابعات، توزيع، دمج المكرر */
$wid = (int)ws('id');
$uid = (int)(current_user()['id'] ?? 0);
$canManage = can('crm.manage');
$stages = crm_stages();
$sources = crm_sources();
$members = crm_members();
$memberIds = array_map('intval', array_column($members, 'id'));
$json = function (array $a) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
$out = function (array $l) {
    return ['id' => (int)$l['id'], 'name' => $l['name'], 'title' => (string)$l['title'], 'company' => (string)$l['company'], 'email' => (string)$l['email'],
        'phone' => (string)$l['phone'], 'pending' => (int)$l['phone_pending'] === 1, 'wa' => crm_wa_digits($l['phone']), 'linkedin' => (string)$l['linkedin'],
        'country' => (string)$l['country'], 'stage' => $l['stage'], 'source' => $l['source'], 'value' => $l['deal_value'] === null ? '' : rtrim(rtrim((string)$l['deal_value'], '0'), '.'),
        'note' => (string)$l['note'], 'tags' => (string)$l['tags'], 'owner' => (int)$l['owner_id'], 'next_at' => $l['next_at'] ? date('Y-m-d\TH:i', strtotime($l['next_at'])) : '',
        'next_note' => (string)$l['next_note'], 'created' => $l['created_at'], 'edit' => crm_can_edit($l)];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || (trim((string)($_POST['email'] ?? '')) === '' && trim((string)($_POST['phone'] ?? '')) === '')) $json(['ok' => false, 'error' => t('اكتب الاسم ورقم أو إيميل على الأقل.')]);
        $l = crm_lead_upsert(['source' => 'manual', 'name' => $name, 'company' => $_POST['company'] ?? '', 'title' => $_POST['title'] ?? '',
            'email' => $_POST['email'] ?? '', 'phone' => $_POST['phone'] ?? '', 'owner_id' => $canManage ? null : $uid]);
        log_activity('crm.add', $name);
        $json(['ok' => true, 'merged' => !empty($l['merged']), 'lead' => $out($l)]);
    }
    $l = crm_lead((int)($_POST['id'] ?? 0));
    if (!$l || !crm_can_edit($l)) $json(['ok' => false, 'error' => t('مش مسموحلك تعدّل العميل ده.')]);
    if ($action === 'stage') {
        $s = (string)($_POST['stage'] ?? '');
        if (!isset($stages[$s])) $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
        db()->prepare('UPDATE crm_leads SET stage = ? WHERE id = ?')->execute([$s, (int)$l['id']]);
        log_activity('crm.stage', $l['id'] . ' ' . $s);
        $json(['ok' => true, 'lead' => $out(crm_lead((int)$l['id']))]);
    }
    if ($action === 'save') {
        $s = isset($stages[$_POST['stage'] ?? '']) ? $_POST['stage'] : $l['stage'];
        $val = trim((string)($_POST['value'] ?? ''));
        $val = $val === '' ? null : max(0, (float)str_replace(',', '.', $val));
        $owner = (int)$l['owner_id'] ?: null;
        if ($canManage) { $o = (int)($_POST['owner'] ?? 0); $owner = in_array($o, $memberIds, true) ? $o : null; }
        $next = trim((string)($_POST['next_at'] ?? ''));
        $next = $next !== '' && strtotime($next) ? date('Y-m-d H:i:s', strtotime($next)) : null;
        $tags = implode(', ', array_slice(array_unique(array_filter(array_map(function ($x) { return mb_substr(trim($x), 0, 30); }, explode(',', (string)($_POST['tags'] ?? ''))))), 0, 8));
        $phone = crm_norm_phone((string)($_POST['phone'] ?? ''));
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $json(['ok' => false, 'error' => t('الإيميل مش صحيح')]);
        db()->prepare('UPDATE crm_leads SET name = ?, company = ?, title = ?, email = ?, phone = ?, phone_pending = IF(? IS NULL, phone_pending, 0), stage = ?, deal_value = ?, note = ?, tags = ?, owner_id = ?, next_at = ?, next_note = ? WHERE id = ?')
            ->execute([mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255) ?: $l['name'], mb_substr(trim((string)($_POST['company'] ?? '')), 0, 255) ?: null,
                mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255) ?: null, $email ?: null, $phone ?: null, $phone ?: null, $s, $val,
                mb_substr(trim((string)($_POST['note'] ?? '')), 0, 2000) ?: null, $tags ?: null, $owner, $next,
                mb_substr(trim((string)($_POST['next_note'] ?? '')), 0, 255) ?: null, (int)$l['id']]);
        log_activity('crm.save', (string)$l['id']);
        $json(['ok' => true, 'lead' => $out(crm_lead((int)$l['id']))]);
    }
    if ($action === 'delete' && $canManage) {
        db()->prepare('DELETE FROM crm_leads WHERE id = ? AND workspace_id = ?')->execute([(int)$l['id'], $wid]);
        log_activity('crm.delete', $l['id'] . ' ' . $l['name']);
        $json(['ok' => true]);
    }
    $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
}

// الموظف العادي بيشوف بتوعه واللي من غير مسؤول، والمدير بيشوف الكل
$where = 'workspace_id = ?';
$args = [$wid];
if (!$canManage) { $where .= ' AND (owner_id = ? OR owner_id IS NULL)'; $args[] = $uid; }
$st = db()->prepare("SELECT * FROM crm_leads WHERE $where ORDER BY (next_at IS NULL), next_at, id DESC LIMIT 2000");
$st->execute($args);
$rows = $st->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, ['name', 'title', 'company', 'email', 'phone', 'country', 'stage', 'source', 'value', 'owner', 'next_follow_up', 'tags', 'note', 'created']);
    $names = array_column($members, 'name', 'id');
    foreach ($rows as $r) fputcsv($f, [$r['name'], $r['title'], $r['company'], $r['email'], $r['phone'], $r['country'], $stages[$r['stage']] ?? $r['stage'], $sources[$r['source']] ?? $r['source'], $r['deal_value'], $names[$r['owner_id']] ?? '', $r['next_at'], $r['tags'], $r['note'], $r['created_at']]);
    exit;
}

$leads = array_map($out, $rows);
render('Leads', 'crm_leads', function () use ($leads, $stages, $sources, $members, $canManage, $uid) {
    $now = time();
    $todayEnd = strtotime('tomorrow') - 1;
    $due = count(array_filter($leads, function ($l) use ($todayEnd) { return $l['next_at'] && strtotime($l['next_at']) <= $todayEnd && !in_array($l['stage'], ['won', 'lost'], true); }));
    $open = array_filter($leads, function ($l) { return !in_array($l['stage'], ['won', 'lost'], true); });
    $pipe = array_sum(array_map(function ($l) { return (float)$l['value']; }, $open));
    $bySrc = array_count_values(array_column($leads, 'source'));
    $won = count(array_filter($leads, function ($l) { return $l['stage'] === 'won'; }));
    ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('كل الـ Leads')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= count($leads) ?></div>
        <div class="flex flex-wrap gap-1 mt-2"><?php foreach ($sources as $k => $lbl): if (empty($bySrc[$k])) continue; ?><span class="text-[10px] px-1.5 py-0.5 rounded bg-ink-100 text-ink-600"><?= e($lbl) ?> <?= (int)$bySrc[$k] ?></span><?php endforeach; ?></div></div>
      <button type="button" id="ld-due-tile" class="text-start rounded-2xl border p-4 transition <?= $due ? 'border-amber-300 bg-amber-50 hover:border-amber-400' : 'border-ink-200 bg-white' ?>"><div class="text-xs text-ink-500"><?= e(t('متابعات النهارده والمتأخرة')) ?></div><div class="text-2xl font-bold mt-1 <?= $due ? 'text-amber-700' : 'text-ink-900' ?>"><?= $due ?></div></button>
      <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-4"><div class="text-xs text-ink-500"><?= e(t('قيمة الصفقات المفتوحة')) ?></div><div class="text-2xl font-bold text-brand-700 mt-1"><?= e(number_format($pipe)) ?></div></div>
      <div class="rounded-2xl border border-lime-200 bg-lime-50/60 p-4"><div class="text-xs text-ink-500"><?= e(t('بقوا عملاء')) ?></div><div class="text-2xl font-bold text-lime-700 mt-1"><?= $won ?><span class="text-sm text-ink-400 font-medium"> · <?= count($leads) ? round($won / count($leads) * 100) : 0 ?>%</span></div></div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-3">
      <div class="inline-flex rounded-xl border border-ink-200 bg-white p-0.5" role="group">
        <button type="button" data-v="list" class="ld-view px-3 py-1.5 text-sm rounded-lg"><?= e(t('قائمة')) ?></button>
        <button type="button" data-v="board" class="ld-view px-3 py-1.5 text-sm rounded-lg"><?= e(t('لوحة')) ?></button>
      </div>
      <select id="ld-src" class="input w-auto"><option value=""><?= e(t('كل المصادر')) ?></option><?php foreach ($sources as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select>
      <select id="ld-own" class="input w-auto"><option value=""><?= e(t('كل المسؤولين')) ?></option><option value="<?= $uid ?>"><?= e(t('بتوعي')) ?></option><option value="0"><?= e(t('من غير مسؤول')) ?></option>
        <?php if ($canManage): foreach ($members as $m): if ((int)$m['id'] === $uid) continue; ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; endif; ?></select>
      <label class="inline-flex items-center gap-1.5 text-sm text-ink-700 cursor-pointer"><input type="checkbox" id="ld-due" class="rounded"><?= e(t('متابعات مستحقة بس')) ?></label>
      <input id="ld-q" type="search" class="input w-full sm:w-52 ms-auto" placeholder="<?= e(t('اسم، شركة، رقم، إيميل…')) ?>">
      <a class="btn-ghost" href="<?= e(url('crm_leads', ['export' => 'csv'])) ?>">CSV</a>
      <button type="button" id="ld-add-btn" class="btn-primary">+ <?= e(t('Lead جديد')) ?></button>
    </div>
    <div id="ld-stages" class="flex flex-wrap gap-1.5 mb-4">
      <button type="button" class="chip on" data-s=""><?= e(t('الكل')) ?></button>
      <?php foreach ($stages as $k => $lbl): ?><button type="button" class="chip" data-s="<?= e($k) ?>"><?= e($lbl) ?> <span class="text-ink-400" data-cnt="<?= e($k) ?>">0</span></button><?php endforeach; ?>
    </div>

    <?php if (!$leads): ?>
      <div id="ld-empty"><?= an_empty(t('لسه مفيش Leads'), t('اكشف أشخاص من الشركات المستهدفة، أو ضيف Lead بإيدك.'), '🧲') ?></div>
    <?php endif; ?>
    <div id="ld-list" class="card divide-y divide-ink-100 overflow-hidden"></div>
    <div id="ld-board" class="hidden grid grid-flow-col auto-cols-[minmax(240px,1fr)] gap-3 overflow-x-auto pb-3"></div>
    <div id="ld-none" class="hidden text-center text-sm text-ink-500 py-10"><?= e(t('مفيش Leads تطابق الفلاتر')) ?></div>

    <div id="ld-drawer" class="fixed inset-0 z-50 hidden" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <form id="ld-form" class="absolute top-0 bottom-0 end-0 w-full sm:w-[460px] bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="ld-d-title">
        <input type="hidden" name="id"><input type="hidden" name="action" value="save">
        <div class="flex items-center gap-3 p-4 border-b border-ink-100">
          <div class="min-w-0 flex-1"><div id="ld-d-title" class="font-semibold text-ink-900 truncate"></div><div id="ld-d-sub" class="text-xs text-ink-500 truncate"></div></div>
          <button type="button" class="btn-ghost" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-4">
          <div id="ld-quick" class="flex flex-wrap gap-2"></div>
          <div><span class="label"><?= e(t('المرحلة')) ?></span><div id="ld-stage-pick" class="flex flex-wrap gap-1.5">
            <?php foreach ($stages as $k => $lbl): ?><label class="cursor-pointer"><input type="radio" name="stage" value="<?= e($k) ?>" class="peer sr-only"><span class="inline-block px-2.5 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600 peer-focus-visible:ring-2 ring-brand-300"><?= e($lbl) ?></span></label><?php endforeach; ?>
          </div></div>
          <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900">⏰ <?= e(t('المتابعة الجاية')) ?></div>
            <div class="flex flex-wrap gap-1.5" id="ld-next-quick">
              <button type="button" class="chip text-xs" data-d="0" data-h="16"><?= e(t('النهارده')) ?></button>
              <button type="button" class="chip text-xs" data-d="1" data-h="11"><?= e(t('بكرة')) ?></button>
              <button type="button" class="chip text-xs" data-d="3" data-h="11"><?= e(t('بعد 3 أيام')) ?></button>
              <button type="button" class="chip text-xs" data-d="7" data-h="11"><?= e(t('بعد أسبوع')) ?></button>
              <button type="button" class="chip text-xs" data-d="-1"><?= e(t('مفيش')) ?></button>
            </div>
            <input class="input" type="datetime-local" name="next_at">
            <input class="input" name="next_note" maxlength="255" placeholder="<?= e(t('هتعمل إيه؟ (مثلاً: ابعتله عرض السعر)')) ?>">
          </div>
          <div class="grid grid-cols-2 gap-3">
            <label class="block col-span-2"><span class="label"><?= e(t('الاسم')) ?></span><input class="input" name="name" required maxlength="255"></label>
            <label class="block"><span class="label"><?= e(t('الشركة')) ?></span><input class="input" name="company" maxlength="255"></label>
            <label class="block"><span class="label"><?= e(t('المسمى الوظيفي')) ?></span><input class="input" name="title" maxlength="255"></label>
            <label class="block"><span class="label"><?= e(t('الرقم')) ?></span><input class="input" name="phone" dir="ltr" maxlength="40"></label>
            <label class="block"><span class="label"><?= e(t('الإيميل')) ?></span><input class="input" name="email" type="email" dir="ltr" maxlength="255"></label>
            <label class="block"><span class="label"><?= e(t('قيمة الصفقة')) ?></span><input class="input" name="value" inputmode="decimal" dir="ltr"></label>
            <label class="block"><span class="label"><?= e(t('المسؤول')) ?></span><select class="input" name="owner" <?= $canManage ? '' : 'disabled' ?>><option value="0"><?= e(t('من غير مسؤول')) ?></option>
              <?php foreach ($members as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></label>
            <label class="block col-span-2"><span class="label"><?= e(t('تاجات (افصل بفاصلة)')) ?></span><input class="input" name="tags" maxlength="255" placeholder="<?= e(t('VIP، مطاعم')) ?>"></label>
            <label class="block col-span-2"><span class="label"><?= e(t('ملاحظات')) ?></span><textarea class="input" name="note" rows="4" maxlength="2000"></textarea></label>
          </div>
          <div id="ld-meta" class="text-xs text-ink-400"></div>
        </div>
        <div class="p-4 border-t border-ink-100 flex items-center gap-2">
          <button class="btn-primary flex-1" id="ld-save"><?= e(t('احفظ')) ?></button>
          <?php if ($canManage): ?><button type="button" id="ld-del" class="btn-ghost text-red-600"><?= e(t('امسح')) ?></button><?php endif; ?>
          <span id="ld-msg" class="text-xs"></span>
        </div>
      </form>
    </div>

    <div id="ld-add" class="fixed inset-0 z-50 hidden grid place-items-center p-4" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <form id="ld-add-form" class="relative card p-5 w-full max-w-md space-y-3" role="dialog" aria-modal="true" aria-labelledby="ld-add-t">
        <input type="hidden" name="action" value="add">
        <h3 id="ld-add-t" class="font-semibold text-ink-900"><?= e(t('Lead جديد')) ?></h3>
        <input class="input" name="name" required placeholder="<?= e(t('الاسم')) ?>" maxlength="255">
        <div class="grid grid-cols-2 gap-3"><input class="input" name="company" placeholder="<?= e(t('الشركة')) ?>"><input class="input" name="title" placeholder="<?= e(t('المسمى الوظيفي')) ?>"></div>
        <div class="grid grid-cols-2 gap-3"><input class="input" name="phone" dir="ltr" placeholder="+20…"><input class="input" name="email" type="email" dir="ltr" placeholder="name@company.com"></div>
        <p class="text-xs text-ink-500"><?= e(t('لو الرقم أو الإيميل موجود قبل كده، هندمجهم في نفس العميل بدل ما يتكرر.')) ?></p>
        <div class="flex gap-2"><button class="btn-primary flex-1"><?= e(t('ضيف')) ?></button><button type="button" class="btn-ghost" data-close><?= e(t('إلغاء')) ?></button></div>
        <div id="ld-add-msg" class="text-xs text-red-600"></div>
      </form>
    </div>
    <script>window.LD = <?= json_encode([
        'leads' => $leads, 'stages' => $stages, 'sources' => $sources, 'members' => array_column($members, 'name', 'id'), 'me' => $uid, 'url' => url('crm_leads'), 'csrf' => csrf_token(),
        'now' => date('Y-m-d\TH:i'), 'canManage' => $canManage,
        'tr' => ['noowner' => t('من غير مسؤول'), 'overdue' => t('متأخرة'), 'today' => t('النهارده'), 'saved' => t('اتحفظ ✓'), 'err' => t('حصل خطأ، جرب تاني'),
            'merged' => t('كان موجود، دمجناه في نفس العميل'), 'del' => t('متأكد إنك عاوز تمسح العميل ده؟'), 'pending' => t('الرقم جاي'), 'call' => t('اتصل'),
            'email' => t('إيميل'), 'from' => t('المصدر: %s · اتضاف %s'), 'drop' => t('اسحب هنا'), 'ro' => t('العميل ده مع موظف تاني، مش هتقدر تعدّله.')],
    ], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-crm_leads.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
