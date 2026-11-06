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
        'next_note' => (string)$l['next_note'], 'created' => $l['created_at'], 'last' => (string)($l['last_contact_at'] ?? ''), 'src_url' => (string)($l['src_url'] ?? ''), 'edit' => crm_can_edit($l)];
};

$sugZ = function () {
    $fxd = getenv('UI_FIXTURES');
    if ($fxd && is_file($fxd . '/inbox_fake_z.php')) return require $fxd . '/inbox_fake_z.php'; // اختبارات بس
    $e = null; return zernio_or_null($e);
};
if (($_GET['ajax'] ?? '') === 'suggest') {
    $z = $sugZ();
    if (!$z) $json(['ok' => false, 'error' => t('اربط Zernio الأول.')]);
    // كاش 10 دقايق في الجلسة عشان العداد مايعملش طلبات لـ Zernio مع كل فتحة صفحة
    $ck = 'crm_sug_' . $wid; $c = $_SESSION[$ck] ?? null;
    if (empty($_GET['fresh']) && $c && $c[0] > time() - 600) $items = $c[1];
    else { $items = crm_suggestions($z); $_SESSION[$ck] = [time(), $items]; }
    session_write_close();
    $json(['ok' => true, 'items' => $items, 'ai' => (bool)ai_connection() && can('ai.use')]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'social_bulk') {
        $items = json_decode((string)($_POST['items'] ?? ''), true);
        $n = 0; $m = 0;
        foreach (array_slice((array)$items, 0, 100) as $it) {
            if (!is_array($it) || trim((string)($it['ext'] ?? '')) === '') continue;
            $l = crm_from_social(['source' => $it['source'] ?? '', 'ext' => $it['ext'], 'name' => $it['name'] ?? '', 'phone' => $it['phone'] ?? '', 'email' => $it['email'] ?? '',
                'text' => $it['text'] ?? '', 'url' => $it['url'] ?? '', 'where' => $it['where'] ?? '', 'owner_id' => $canManage ? null : $uid]);
            if (!$l) continue;
            if ($l['merged']) $m++; else $n++;
        }
        log_activity('crm.suggest_add', "$n new");
          unset($_SESSION['crm_sug_' . $wid]);
        $json(['ok' => true, 'new' => $n, 'merged' => $m]);
    }
    if ($action === 'auto_capture' && $canManage) {
        $on = !empty($_POST['on']);
        save_setting('crm_auto_capture_' . $wid, $on ? '1' : '0');
        $n = 0;
        if ($on) { $z = $sugZ(); if ($z) { try { $n = crm_auto_capture($z); } catch (Throwable $e) {} } unset($_SESSION['crm_sug_' . $wid]); }
        $json(['ok' => true, 'on' => $on, 'n' => $n]);
    }
    if ($action === 'ai_scan') {
        if (!can('ai.use') || !ai_connection()) $json(['ok' => false, 'error' => t('مالكش صلاحية استخدام الذكاء الاصطناعي.')]);
        session_write_close();
        try { $n = ai_classify_conversations($wid, 40); } catch (Throwable $e) { $json(['ok' => false, 'error' => $e->getMessage()]); }
        $json(['ok' => true, 'n' => $n]);
    }
    if ($action === 'sync_ads') {
        $zerr = null;
        $z = zernio_or_null($zerr);
        if (!$z && !getenv('UI_FIXTURES')) { flash('error', $zerr ?: t('اربط Zernio الأول.')); redirect(url('crm_leads')); }
        try {
            [$n, $m] = crm_sync_ads($z ?? new Zernio('fixture'), 10);
            log_activity('crm.sync_ads', $n . ' new');
            flash('success', $n ? t('اتضاف %s Lead جديد من الإعلانات.', $n) : t('مفيش Leads جديدة في الإعلانات.'));
        } catch (ZernioException $ex) {
            flash('error', $ex->getMessage());
        }
        redirect(url('crm_leads'));
    }
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || (trim((string)($_POST['email'] ?? '')) === '' && trim((string)($_POST['phone'] ?? '')) === '')) $json(['ok' => false, 'error' => t('اكتب الاسم ورقم أو إيميل على الأقل.')]);
        $l = crm_lead_upsert(['source' => 'manual', 'name' => $name, 'company' => $_POST['company'] ?? '', 'title' => $_POST['title'] ?? '',
            'email' => $_POST['email'] ?? '', 'phone' => $_POST['phone'] ?? '', 'owner_id' => $canManage ? null : $uid]);
        log_activity('crm.add', $name);
        $json(['ok' => true, 'merged' => !empty($l['merged']), 'lead' => $out($l)]);
    }
    if ($action === 'social') {
        // من صفحة الرسائل أو التعليقات
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' && trim((string)($_POST['ext'] ?? '')) === '') $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
        $l = crm_from_social(['source' => $_POST['source'] ?? '', 'ext' => $_POST['ext'] ?? '', 'name' => $name, 'phone' => $_POST['phone'] ?? '', 'email' => $_POST['email'] ?? '',
            'text' => $_POST['text'] ?? '', 'url' => $_POST['url'] ?? '', 'where' => $_POST['where'] ?? '', 'owner_id' => $canManage ? null : $uid]);
        if (!$l) $json(['ok' => false, 'error' => t('حصل خطأ، جرب تاني')]);
        log_activity('crm.from_social', (string)$l['id']);
        $json(['ok' => true, 'merged' => $l['merged'], 'id' => (int)$l['id'], 'phone' => (string)$l['phone'], 'email' => (string)$l['email'], 'url' => url('crm_leads') . '#lead-' . (int)$l['id']]);
    }
    if ($action === 'import') {
        // صفوف جاية من المتصفح بعد ربط الأعمدة: [{name, phone, email, company, title, country, tags, note}]
        $rows = json_decode((string)($_POST['rows'] ?? ''), true);
        if (!is_array($rows) || !$rows) $json(['ok' => false, 'error' => t('مفيش صفوف')]);
        $new = 0; $merged = 0; $skip = 0;
        foreach (array_slice($rows, 0, 2000) as $r) {
            if (!is_array($r)) { $skip++; continue; }
            $nm = trim((string)($r['name'] ?? ''));
            $ph = trim((string)($r['phone'] ?? '')); $em = trim((string)($r['email'] ?? ''));
            if ($ph === '' && $em === '') { $skip++; continue; }
            $l = crm_lead_upsert(['source' => 'import', 'name' => $nm !== '' ? $nm : ($em ?: $ph), 'company' => $r['company'] ?? '', 'title' => $r['title'] ?? '',
                'email' => $em, 'phone' => $ph, 'country' => $r['country'] ?? '', 'owner_id' => $canManage ? null : $uid]);
            if (!$l) { $skip++; continue; }
            if (!empty($l['merged'])) { $merged++; continue; }
            $new++;
            $tg = trim((string)($r['tags'] ?? '')); $nt = trim((string)($r['note'] ?? ''));
            if ($tg !== '' || $nt !== '') db()->prepare('UPDATE crm_leads SET tags = ?, note = ? WHERE id = ?')->execute([mb_substr($tg, 0, 255) ?: null, mb_substr($nt, 0, 2000) ?: null, (int)$l['id']]);
        }
        log_activity('crm.import', "$new new, $merged merged");
        $json(['ok' => true, 'new' => $new, 'merged' => $merged, 'skip' => $skip]);
    }
    if ($action === 'tpl_save' && $canManage) {
        $t = json_decode((string)($_POST['tpl'] ?? ''), true);
        $clean = [];
        foreach ((array)$t as $x) { $n = mb_substr(trim((string)($x['n'] ?? '')), 0, 40); $b = mb_substr(trim((string)($x['b'] ?? '')), 0, 1000); if ($n !== '' && $b !== '') $clean[] = ['n' => $n, 'b' => $b]; }
        save_setting('crm_tpl_' . $wid, json_encode(array_slice($clean, 0, 10), JSON_UNESCAPED_UNICODE));
        $json(['ok' => true, 'tpl' => crm_templates()]);
    }
    $l = crm_lead((int)($_POST['id'] ?? 0));
    if ($l && $action === 'acts') $json(['ok' => true, 'acts' => crm_acts((int)$l['id'])]);
    if (!$l || !crm_can_edit($l)) $json(['ok' => false, 'error' => t('مش مسموحلك تعدّل العميل ده.')]);
    if ($action === 'stage') {
        $s = (string)($_POST['stage'] ?? '');
        if (!isset($stages[$s])) $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
        db()->prepare('UPDATE crm_leads SET stage = ? WHERE id = ?')->execute([$s, (int)$l['id']]);
        if ($s !== $l['stage']) crm_act_add((int)$l['id'], 'stage', $stages[$l['stage']] . (is_rtl() ? ' ← ' : ' → ') . $stages[$s]);
        log_activity('crm.stage', $l['id'] . ' ' . $s);
        $nl = crm_lead((int)$l['id']);
        crm_push_ad_stage($nl);
        $json(['ok' => true, 'lead' => $out($nl)]);
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
        $phone = crm_norm_phone((string)($_POST['phone'] ?? ''), (string)$l['country']);
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $json(['ok' => false, 'error' => t('الإيميل مش صحيح')]);
        db()->prepare('UPDATE crm_leads SET name = ?, company = ?, title = ?, email = ?, phone = ?, phone_pending = IF(? IS NULL, phone_pending, 0), stage = ?, deal_value = ?, note = ?, tags = ?, owner_id = ?, next_at = ?, next_note = ? WHERE id = ?')
            ->execute([mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255) ?: $l['name'], mb_substr(trim((string)($_POST['company'] ?? '')), 0, 255) ?: null,
                mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255) ?: null, $email ?: null, $phone ?: null, $phone ?: null, $s, $val,
                mb_substr(trim((string)($_POST['note'] ?? '')), 0, 2000) ?: null, $tags ?: null, $owner, $next,
                mb_substr(trim((string)($_POST['next_note'] ?? '')), 0, 255) ?: null, (int)$l['id']]);
        if ($s !== $l['stage']) crm_act_add((int)$l['id'], 'stage', $stages[$l['stage']] . (is_rtl() ? ' ← ' : ' → ') . $stages[$s]);
        log_activity('crm.save', (string)$l['id']);
        $nl = crm_lead((int)$l['id']);
        crm_push_ad_stage($nl);
        $json(['ok' => true, 'lead' => $out($nl)]);
    }
    if (in_array($action, ['enrich', 'seqs', 'seq_add'], true)) {
        if (!crm_has_key()) $json(['ok' => false, 'error' => t('اربط حساب Apollo.io من التكاملات الأول.')]);
        try {
            if ($action === 'enrich') {
                if (!can('crm.reveal')) $json(['ok' => false, 'error' => t('مالكش صلاحية الكشف.')]);
                $prefs = crm_prefs();
                if ((int)$prefs['daily_limit'] - crm_reveals_today($uid) <= 0) $json(['ok' => false, 'error' => t('خلصت حد الكشف بتاعك النهارده (%s). بيتجدد بكرة.', (int)$prefs['daily_limit'])]);
                $r = crm_enrich_lead($l, $prefs);
                $json(['ok' => true, 'got' => $r['got'], 'pending' => $r['pending'], 'lead' => $out(crm_lead((int)$l['id']))]);
            }
            if ($action === 'seqs') $json(['ok' => true] + crm_sequences());
            $seq = (string)($_POST['seq'] ?? ''); $acc = (string)($_POST['acc'] ?? '');
            if (!preg_match('/^[\w-]{1,64}$/', $seq) || !preg_match('/^[\w-]{1,64}$/', $acc)) $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
            crm_add_to_sequence($l, $seq, $acc, mb_substr((string)($_POST['seq_name'] ?? ''), 0, 120));
            $json(['ok' => true, 'lead' => $out(crm_lead((int)$l['id'])), 'acts' => crm_acts((int)$l['id'])]);
        } catch (CrmException $ex) {
            $json(['ok' => false, 'error' => $ex instanceof CrmPlanException ? t('خطة Apollo بتاعتك مش بتسمح بالعملية دي. الـ Sequences محتاجة خطة مدفوعة و Master Key.') . ' (' . $ex->getMessage() . ')' : $ex->getMessage()]);
        }
    }
    if ($action === 'log') {
        // تسجيل تواصل؛ أول تواصل مع عميل جديد بينقله لـ "اتكلمنا معاه" لوحده
        $k = (string)($_POST['kind'] ?? '');
        if (!isset(crm_act_kinds()[$k]) || $k === 'stage') $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
        crm_act_add((int)$l['id'], $k, (string)($_POST['body'] ?? ''));
        if ($k !== 'note' && $l['stage'] === 'new') {
            db()->prepare("UPDATE crm_leads SET stage = 'contacted' WHERE id = ?")->execute([(int)$l['id']]);
            crm_act_add((int)$l['id'], 'stage', $stages['new'] . (is_rtl() ? ' ← ' : ' → ') . $stages['contacted']);
            crm_push_ad_stage(crm_lead((int)$l['id']));
        }
        db()->prepare('UPDATE crm_leads SET last_contact_at = NOW() WHERE id = ?')->execute([(int)$l['id']]);
        $json(['ok' => true, 'lead' => $out(crm_lead((int)$l['id'])), 'acts' => crm_acts((int)$l['id'])]);
    }
    if ($action === 'delete' && $canManage) {
        db()->prepare('DELETE FROM crm_acts WHERE lead_id = ? AND workspace_id = ?')->execute([(int)$l['id'], $wid]);
        db()->prepare('DELETE FROM crm_leads WHERE id = ? AND workspace_id = ?')->execute([(int)$l['id'], $wid]);
        log_activity('crm.delete', $l['id'] . ' ' . $l['name']);
        $json(['ok' => true]);
    }
    $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
}

try { crm_fix_stored_phones(); } catch (Throwable $t) { /* مش مهم */ }
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
$adsOn = module_enabled('ads') && can('ads.view');
$dial = (string)(countries()[ws_country()]['dial'] ?? '+');
$lastSync = setting('crm_ads_sync_' . $wid);
render('Leads', 'crm_leads', function () use ($leads, $stages, $sources, $members, $canManage, $uid, $adsOn, $lastSync, $dial) {
    $now = time();
    $todayEnd = strtotime('tomorrow') - 1;
    $due = count(array_filter($leads, function ($l) use ($todayEnd) { return $l['next_at'] && strtotime($l['next_at']) <= $todayEnd && !in_array($l['stage'], ['won', 'lost'], true); }));
    $open = array_filter($leads, function ($l) { return !in_array($l['stage'], ['won', 'lost'], true); });
    $pipe = array_sum(array_map(function ($l) { return (float)$l['value']; }, $open));
    $bySrc = array_count_values(array_column($leads, 'source'));
    $won = count(array_filter($leads, function ($l) { return $l['stage'] === 'won'; }));
    // القمع: العميل اللي في مرحلة متقدمة عدّى على اللي قبلها
    $order = ['new', 'contacted', 'qualified', 'proposal', 'won'];
    $reach = array_fill_keys($order, 0);
    foreach ($leads as $l) { $ix = array_search($l['stage'], $order, true); if ($ix === false) { $reach['new']++; continue; } for ($i = 0; $i <= $ix; $i++) $reach[$order[$i]]++; }
    $srcRows = [];
    foreach ($sources as $k => $lbl) {
        $ls = array_filter($leads, function ($l) use ($k) { return $l['source'] === $k; }); if (!$ls) continue;
        $w = count(array_filter($ls, function ($l) { return $l['stage'] === 'won'; }));
        $c = count(array_filter($ls, function ($l) { return $l['stage'] !== 'new'; }));
        $srcRows[] = [$lbl, count($ls), $c, $w];
    }
    $cold = count(array_filter($leads, function ($l) use ($now) { return !in_array($l['stage'], ['won', 'lost'], true) && !$l['next_at'] && strtotime($l['last'] ?: $l['created']) < $now - 7 * 86400; }));
    ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('كل الـ Leads')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= count($leads) ?></div>
        <div class="flex flex-wrap gap-1 mt-2"><?php foreach ($sources as $k => $lbl): if (empty($bySrc[$k])) continue; ?><span class="text-[10px] px-1.5 py-0.5 rounded bg-ink-100 text-ink-600"><?= e($lbl) ?> <?= (int)$bySrc[$k] ?></span><?php endforeach; ?></div></div>
      <button type="button" id="ld-due-tile" class="text-start rounded-2xl border p-4 transition <?= $due ? 'border-amber-300 bg-amber-50 hover:border-amber-400' : 'border-ink-200 bg-white' ?>"><div class="text-xs text-ink-500"><?= e(t('متابعات النهارده والمتأخرة')) ?></div><div class="text-2xl font-bold mt-1 <?= $due ? 'text-amber-700' : 'text-ink-900' ?>"><?= $due ?></div></button>
      <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-4"><div class="text-xs text-ink-500"><?= e(t('قيمة الصفقات المفتوحة')) ?></div><div class="text-2xl font-bold text-brand-700 mt-1"><?= e(number_format($pipe)) ?></div></div>
      <div class="rounded-2xl border border-lime-200 bg-lime-50/60 p-4"><div class="text-xs text-ink-500"><?= e(t('بقوا عملاء')) ?></div><div class="text-2xl font-bold text-lime-700 mt-1"><?= $won ?><span class="text-sm text-ink-400 font-medium"> · <?= count($leads) ? round($won / count($leads) * 100) : 0 ?>%</span></div></div>
    </div>

    <?php if ($canManage && !crm_has_key()): ?>
    <div class="rounded-xl border border-dashed border-brand-300 bg-brand-50/40 px-4 py-2.5 mb-4 text-xs text-ink-700 flex flex-wrap items-center gap-2">🔎 <?= e(t('اربط Apollo عشان تظهر "الشركات المستهدفة" وتكشف أرقام صناع القرار.')) ?>
      <a class="link ms-auto" href="<?= e(url('crm')) ?>"><?= e(t('اربطه')) ?> ↗</a></div>
    <?php endif; ?>
    <?php if (count($leads) >= 3): ?>
    <details class="card p-4 mb-4 group" id="ld-funnel" <?= count($leads) >= 10 ? 'open' : '' ?>>
      <summary class="cursor-pointer font-semibold text-ink-900 text-sm">📊 <?= e(t('قمع المبيعات ومصادر العملاء')) ?></summary>
      <div class="grid lg:grid-cols-2 gap-5 mt-3">
        <div class="space-y-1.5"><?php $top = max(1, $reach['new']); foreach ($order as $i => $k): $pc = round($reach[$k] / $top * 100); $prev = $i ? $reach[$order[$i - 1]] : 0; ?>
          <div class="flex items-center gap-2 text-xs"><span class="w-24 text-ink-600 shrink-0"><?= e($stages[$k]) ?></span>
            <div class="flex-1 h-6 rounded-md bg-ink-50 overflow-hidden"><div class="h-full bg-brand-500/80 rounded-md flex items-center px-2 text-white font-semibold" style="width:<?= max(6, $pc) ?>%"><?= (int)$reach[$k] ?></div></div>
            <span class="w-14 text-end text-ink-500"><?= $i && $prev ? round($reach[$k] / $prev * 100) . '%' : '' ?></span></div>
        <?php endforeach; ?>
          <p class="text-[11px] text-ink-400 pt-1"><?= e(t('النسبة جنب كل مرحلة = كام في المية عدّى من المرحلة اللي قبلها.')) ?></p></div>
        <div><table class="w-full text-xs"><thead><tr class="text-ink-500 text-start"><th class="text-start font-medium py-1"><?= e(t('المصدر')) ?></th><th class="font-medium"><?= e(t('عدد')) ?></th><th class="font-medium"><?= e(t('اتواصلنا')) ?></th><th class="font-medium"><?= e(t('بقوا عملاء')) ?></th><th class="font-medium"><?= e(t('التحويل')) ?></th></tr></thead><tbody>
          <?php foreach ($srcRows as $r): ?><tr class="border-t border-ink-100"><td class="py-1.5 text-ink-800"><?= e($r[0]) ?></td><td class="text-center"><?= $r[1] ?></td><td class="text-center"><?= $r[2] ?></td><td class="text-center font-semibold text-lime-700"><?= $r[3] ?></td><td class="text-center"><?= round($r[3] / $r[1] * 100) ?>%</td></tr><?php endforeach; ?>
        </tbody></table>
        <?php if ($cold): ?><button type="button" id="ld-cold" class="mt-3 w-full text-start rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800 hover:bg-sky-100">🧊 <?= e(t('%s عميل محدش كلمه من أسبوع ومالوش متابعة. اعرضهم', $cold)) ?></button><?php endif; ?></div>
      </div>
    </details>
    <?php endif; ?>

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
      <?php if ($adsOn): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="sync_ads">
        <button class="btn-ghost" title="<?= e($lastSync ? t('آخر سحب: %s', fmt_date($lastSync)) : t('لسه ما اتسحبش')) ?>" onclick="this.disabled=true;this.form.submit()">⇣ <?= e(t('اسحب من الإعلانات')) ?></button></form><?php endif; ?>
      <a class="btn-ghost" href="<?= e(url('crm_leads', ['export' => 'csv'])) ?>">CSV</a>
      <button type="button" id="ld-sug-btn" class="btn-ghost relative">✨ <?= e(t('عملاء مقترحين')) ?><span id="ld-sug-n" class="hidden absolute -top-1.5 -end-1.5 min-w-[18px] h-[18px] rounded-full bg-lime-500 text-white text-[10px] font-bold grid place-items-center px-1"></span></button>
      <button type="button" id="ld-imp-btn" class="btn-ghost">⇪ <?= e(t('استورد عملاء')) ?></button>
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

    <div id="ld-drawer" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <form id="ld-form" class="absolute top-0 bottom-0 end-0 w-full sm:w-[460px] bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="ld-d-title">
        <input type="hidden" name="id"><input type="hidden" name="action" value="save">
        <div class="flex items-center gap-3 p-4 border-b border-ink-100">
          <div class="min-w-0 flex-1"><div id="ld-d-title" class="font-semibold text-ink-900 truncate"></div><div id="ld-d-sub" class="text-xs text-ink-500 truncate"></div></div>
          <button type="button" class="btn-ghost" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-4">
          <div id="ld-quick" class="flex flex-wrap gap-2"></div>
          <?php if (crm_has_key()): ?>
          <div id="ld-apollo" class="rounded-xl border border-violet-200 bg-violet-50/40 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900">🚀 Apollo</div>
            <div class="flex flex-wrap gap-1.5">
              <?php if (can('crm.reveal')): ?><button type="button" id="ld-enrich" class="btn-ghost !py-1 !text-xs">✦ <?= e(t('كمّل بياناته من Apollo')) ?></button><?php endif; ?>
              <button type="button" id="ld-seq-open" class="btn-ghost !py-1 !text-xs">📧 <?= e(t('ضيفه لـ Sequence')) ?></button>
            </div>
            <div id="ld-seq-box" class="hidden space-y-1.5"><select id="ld-seq" class="input text-xs"></select><select id="ld-seq-acc" class="input text-xs"></select>
              <button type="button" id="ld-seq-go" class="btn-primary !py-1 !text-xs w-full"><?= e(t('ابدأ الـ Sequence')) ?></button></div>
            <div id="ld-ap-msg" class="text-[11px]"></div>
          </div>
          <?php endif; ?>
          <div id="ld-tpl" class="hidden rounded-xl border border-lime-200 bg-lime-50/40 p-3 space-y-2">
            <div class="flex items-center gap-2"><span class="text-sm font-semibold text-ink-900">💬 <?= e(t('ابعت رسالة جاهزة')) ?></span>
              <?php if ($canManage): ?><button type="button" id="ld-tpl-edit" class="link text-xs ms-auto"><?= e(t('عدّل القوالب')) ?></button><?php endif; ?></div>
            <div id="ld-tpl-list" class="flex flex-wrap gap-1.5"></div>
            <p class="text-[11px] text-ink-500"><?= e(t('بيفتح واتساب بالرسالة مكتوبة، وبيتسجل في سجل التواصل لوحده.')) ?></p>
          </div>
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
          <div class="rounded-xl border border-ink-200 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900">🕓 <?= e(t('سجل التواصل')) ?></div>
            <div class="flex flex-wrap gap-1.5" id="ld-log-kinds"><?php foreach (crm_act_kinds() as $k => $v): if ($k === 'stage') continue; ?><button type="button" class="chip text-xs" data-k="<?= e($k) ?>"><?= $v[0] ?> <?= e($v[1]) ?></button><?php endforeach; ?></div>
            <div class="flex gap-2"><input id="ld-log-body" class="input text-sm" maxlength="2000" placeholder="<?= e(t('حصل إيه؟ (اختياري) واختار النوع')) ?>"></div>
            <ol id="ld-acts" class="space-y-2 max-h-72 overflow-y-auto"></ol>
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

    <div id="ld-add" class="fixed inset-0 z-[60] hidden grid place-items-center p-4" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <form id="ld-add-form" class="relative card p-5 w-full max-w-md space-y-3" role="dialog" aria-modal="true" aria-labelledby="ld-add-t">
        <input type="hidden" name="action" value="add">
        <h3 id="ld-add-t" class="font-semibold text-ink-900"><?= e(t('Lead جديد')) ?></h3>
        <input class="input" name="name" required placeholder="<?= e(t('الاسم')) ?>" maxlength="255">
        <div class="grid grid-cols-2 gap-3"><input class="input" name="company" placeholder="<?= e(t('الشركة')) ?>"><input class="input" name="title" placeholder="<?= e(t('المسمى الوظيفي')) ?>"></div>
        <div class="grid grid-cols-2 gap-3"><input class="input" name="phone" dir="ltr" placeholder="<?= e($dial) ?>…"><input class="input" name="email" type="email" dir="ltr" placeholder="name@company.com"></div>
        <p class="text-xs text-ink-500"><?= e(t('لو كتبت الرقم من غير كود الدولة هنضيف %s (دولة الشركة من الإعدادات).', $dial)) ?> <?= e(t('لو الرقم أو الإيميل موجود قبل كده، هندمجهم في نفس العميل بدل ما يتكرر.')) ?></p>
        <div class="flex gap-2"><button class="btn-primary flex-1"><?= e(t('ضيف')) ?></button><button type="button" class="btn-ghost" data-close><?= e(t('إلغاء')) ?></button></div>
        <div id="ld-add-msg" class="text-xs text-red-600"></div>
      </form>
    </div>
    <div id="ld-imp" class="fixed inset-0 z-[60] hidden grid place-items-center p-4" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <div class="relative card p-5 w-full max-w-2xl max-h-[92vh] overflow-y-auto space-y-3" role="dialog" aria-modal="true" aria-labelledby="ld-imp-t">
        <h3 id="ld-imp-t" class="font-semibold text-ink-900">⇪ <?= e(t('استورد عملاء')) ?></h3>
        <p class="text-xs text-ink-500"><?= e(t('من Excel: علّم الجدول بالعناوين وانسخه والزقه هنا، أو ارفع ملف CSV. المكرر بيتدمج، وكود الدولة بيتضاف لوحده.')) ?></p>
        <input type="file" id="ld-imp-file" accept=".csv,.tsv,.txt" class="block text-sm">
        <textarea id="ld-imp-txt" rows="6" class="input text-xs font-mono" dir="auto" placeholder="<?= e(t('الاسم	الرقم	الإيميل	الشركة')) ?>"></textarea>
        <div id="ld-imp-map" class="hidden"></div>
        <div class="flex items-center gap-2"><button type="button" id="ld-imp-go" class="btn-primary" disabled><?= e(t('استورد')) ?></button><button type="button" class="btn-ghost" data-close><?= e(t('إلغاء')) ?></button><span id="ld-imp-msg" class="text-xs"></span></div>
      </div>
    </div>
    <div id="ld-sug" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <div class="absolute top-0 bottom-0 end-0 w-full sm:w-[560px] bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="ld-sug-h">
        <div class="flex items-center gap-2 p-4 border-b border-ink-100"><h3 id="ld-sug-h" class="font-semibold text-ink-900 flex-1">✨ <?= e(t('عملاء مقترحين')) ?></h3><button type="button" class="btn-ghost !px-2 !py-1" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button></div>
        <p class="px-4 pt-3 text-xs text-ink-500"><?= e(t('ناس كلّموك أو علّقوا على منشوراتك وباين عليهم مهتمين: الذكاء الاصطناعي لقى عندهم نية شراء، أو كتبوا رقمهم، أو سألوا عن السعر. اللي في الـ Leads مش بيظهروا هنا.')) ?></p>
        <div class="px-4 pt-2 flex flex-wrap items-center gap-2 text-xs">
          <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" id="ld-sug-all" class="rounded"> <?= e(t('تحديد الكل')) ?></label>
          <button type="button" id="ld-sug-ai" class="link hidden"><?= e(t('حلّل المحادثات الجديدة بالذكاء الاصطناعي ✦')) ?></button>
          <span id="ld-sug-msg" class="ms-auto text-ink-500"></span>
        </div>
        <?php if ($canManage): $al = explode('|', (string)setting('crm_auto_last_' . (int)ws('id'), '')); ?>
        <label class="mx-4 mt-2 flex items-start gap-2 rounded-xl border border-lime-200 bg-lime-50/50 p-2.5 text-xs cursor-pointer">
          <input type="checkbox" id="ld-auto" class="rounded mt-0.5" <?= crm_auto_capture_on((int)ws('id')) ? 'checked' : '' ?>>
          <span><b class="text-ink-900"><?= e(t('ضيفهم أوتوماتيك')) ?></b> · <?= e(t('كل 6 ساعات: اللي عنده نية شراء أو كتب رقمه بيتضاف لوحده، بيتوزع على الفريق، وبتتحطله متابعة النهارده.')) ?>
            <?php if (!empty($al[0])): ?><span class="block text-ink-500 mt-0.5"><?= e(t('آخر مرة: %s · اتضاف %s', fmt_date($al[0], 'd/m h:i A'), (int)($al[1] ?? 0))) ?></span><?php endif; ?></span>
        </label>
        <?php endif; ?>
        <div id="ld-sug-list" class="flex-1 overflow-y-auto p-4 space-y-2"></div>
        <div class="p-4 border-t border-ink-100"><button type="button" id="ld-sug-go" class="btn-primary w-full" disabled><?= e(t('ضيف المحددين للـ Leads')) ?></button></div>
      </div>
    </div>
    <div id="ld-tpl-dlg" class="fixed inset-0 z-[60] hidden grid place-items-center p-4" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <div class="relative card p-5 w-full max-w-lg max-h-[92vh] overflow-y-auto space-y-3" role="dialog" aria-modal="true">
        <h3 class="font-semibold text-ink-900"><?= e(t('قوالب الرسائل')) ?></h3>
        <p class="text-xs text-ink-500"><?= e(t('استخدم {name} لاسم العميل و{company} لشركته و{me} لاسمك.')) ?></p>
        <div id="ld-tpl-rows" class="space-y-3"></div>
        <div class="flex gap-2"><button type="button" id="ld-tpl-add" class="btn-ghost">+ <?= e(t('قالب')) ?></button><button type="button" id="ld-tpl-save" class="btn-primary ms-auto"><?= e(t('احفظ')) ?></button><button type="button" class="btn-ghost" data-close><?= e(t('إلغاء')) ?></button></div>
      </div>
    </div>
    <script>window.LD = <?= json_encode([
        'tpl' => crm_templates(), 'meName' => (string)(current_user()['name'] ?? ''), 'kinds' => crm_act_kinds(),
        'leads' => $leads, 'stages' => $stages, 'sources' => $sources, 'members' => array_column($members, 'name', 'id'), 'me' => $uid, 'url' => url('crm_leads'), 'csrf' => csrf_token(),
        'now' => date('Y-m-d\TH:i'), 'canManage' => $canManage,
        'tr' => ['noowner' => t('من غير مسؤول'), 'overdue' => t('متأخرة'), 'today' => t('النهارده'), 'saved' => t('اتحفظ ✓'), 'err' => t('حصل خطأ، جرب تاني'),
            'merged' => t('كان موجود، دمجناه في نفس العميل'), 'del' => t('متأكد إنك عاوز تمسح العميل ده؟'), 'pending' => t('الرقم جاي'), 'call' => t('اتصل'),
            'email' => t('إيميل'), 'from' => t('المصدر: %s · اتضاف %s'), 'drop' => t('اسحب هنا'), 'ro' => t('العميل ده مع موظف تاني، مش هتقدر تعدّله.'),
            'noacts' => t('لسه مفيش تواصل مسجّل'), 'logged' => t('اتسجّل ✓'), 'impRows' => t('%s صف جاهز'), 'impDone' => t('اتضاف %s جديد، واتدمج %s مكرر، واتساب %s من غير رقم ولا إيميل'),
            'impNeed' => t('لازم عمود رقم أو إيميل'), 'skipCol' => t('— تجاهل —'), 'cold' => t('محدش كلمه من أسبوع'), 'tplN' => t('اسم القالب'), 'tplB' => t('نص الرسالة'), 'rm' => t('شيل'),
            'chat' => t('افتح المحادثة'), 'enrGot' => t('اتكملت: %s'), 'enrNone' => t('Apollo مالقاش بيانات جديدة.'), 'enrPend' => t('الرقم جاي من Apollo خلال دقايق.'), 'loading' => t('بنحمّل…'),
            'noSeq' => t('مفيش Sequences في حسابك على Apollo. اعمل واحد من apollo.io الأول.'), 'noAcc' => t('اربط إيميل في Apollo عشان الـ Sequence يبعت منه.'), 'seqDone' => t('اتضاف للـ Sequence ✓'), 'needEmail' => t('العميل ده مالوش إيميل. كمّل بياناته الأول.'),
            'fl' => ['title' => t('المسمى الوظيفي'), 'linkedin' => 'LinkedIn', 'company' => t('الشركة'), 'email' => t('الإيميل'), 'phone' => t('الرقم'), 'country' => t('الدولة')], 'sugLoading' => t('بندوّر في الرسائل والتعليقات…'), 'sugNone' => t('مفيش مقترحين دلوقتي. لما حد يسأل عن السعر أو يكتب رقمه هيظهر هنا.'),
            'sugGo' => t('ضيف %s للـ Leads'), 'sugDone' => t('اتضاف %s عميل جديد'), 'whyAi' => t('نية شراء'), 'whyContact' => t('فيه رقم أو إيميل'), 'whyPrice' => t('سؤال عن السعر'),
            'autoOn' => t('اتفعّل: اتضاف %s دلوقتي، والباقي كل 6 ساعات.'), 'autoOff' => t('الإضافة الأوتوماتيك اتقفلت.'), 'scanning' => t('بنحلّل…'), 'scanned' => t('اتحلّل %s محادثة'), 'cmt' => t('تعليق'), 'msgK' => t('رسالة'), 'f' => ['name' => t('الاسم'), 'phone' => t('الرقم'), 'email' => t('الإيميل'), 'company' => t('الشركة'), 'title' => t('المسمى الوظيفي'), 'country' => t('الدولة'), 'tags' => t('تاجات'), 'note' => t('ملاحظات')]],
    ], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-crm_leads.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
