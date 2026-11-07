<?php
require_once __DIR__ . '/../partials/lead_popup.php';
/** العملاء المستهدفين: كل حد اتعامل معانا (رسالة، تعليق، فورم، حجز، حملة، إعلان) أو ضفناه بنفسنا (يدوي، استيراد، Apollo، واتساب) */
$wid = (int)ws('id');
$uid = (int)(current_user()['id'] ?? 0);
$canManage = can('crm.manage') || is_ws_owner();
$J = function (array $a, int $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
$getLead = function (int $id) use ($wid, $uid, $canManage) { $st = db()->prepare('SELECT * FROM crm_leads WHERE id = ? AND workspace_id = ?'); $st->execute([$id, $wid]); $l = $st->fetch();
    return $l && ($canManage || !$l['owner_id'] || (int)$l['owner_id'] === $uid) ? $l : null; };
$kinds = crm_act_kinds();
$actOut = function (array $a) use ($kinds) { return ['kind' => $a['kind'], 'label' => $kinds[$a['kind']][1] ?? $a['kind'], 'body' => (string)$a['body'], 'who' => (string)$a['who'], 'at' => fmt_date($a['created_at'], 'd/m/Y H:i')]; };
$nextOut = function (array $l) { return ['next' => $l['next_at'] ? fmt_date($l['next_at'], 'd/m/Y H:i') : '', 'next_note' => (string)$l['next_note'], 'due' => $l['next_at'] && strtotime($l['next_at']) <= strtotime('today 23:59:59') ? 1 : 0]; };
// تنبيهات المتابعة لأي صفحة مفتوحة (كل دقيقة): بيشغّل الفحص وبيرجع تنبيهات المتابعة الجديدة بتاعتي
if (($_GET['ajax'] ?? '') === 'due') {
    try { crm_followup_tick(false); } catch (Throwable $e) {}
    $st = db()->prepare("SELECT id, lead_id, body FROM crm_notifs WHERE workspace_id = ? AND user_id = ? AND kind = 'followup' AND seen = 0 AND id > ? AND created_at > NOW() - INTERVAL 1 DAY ORDER BY id LIMIT 10");
    $st->execute([$wid, $uid, max(0, (int)($_GET['after'] ?? 0))]);
    $J(['ok' => true, 'items' => array_map(function ($n) { return ['id' => (int)$n['id'], 'body' => (string)$n['body'], 'url' => url('prospects', ['lead' => (int)$n['lead_id']])]; }, $st->fetchAll()), 'unseen' => crm_notifs_unseen($wid, $uid)]);
}
// كارت العميل للنافذة المشتركة من أي صفحة
if (($_GET['ajax'] ?? '') === 'card') {
    $l = $getLead((int)($_GET['id'] ?? 0)); if (!$l) $J(['ok' => false], 404);
    $q = db()->prepare('SELECT COUNT(*) FROM crm_acts WHERE lead_id = ?'); $q->execute([(int)$l['id']]);
    $J(['ok' => true, 'd' => crm_lead_card($l, (int)$q->fetchColumn())]);
}
// بوب أب العميل: سجل التواصل والمتابعة
if (($_GET['ajax'] ?? '') === 'lead') {
    $l = $getLead((int)($_GET['id'] ?? 0)); if (!$l) $J(['ok' => false], 404);
    $raw = crm_acts((int)$l['id']); $lid = (int)$l['id'];
    $tq = db()->prepare('SELECT t.id, t.title, t.due_at, t.user_id, u.name who FROM crm_tasks t LEFT JOIN users u ON u.id = t.user_id WHERE t.workspace_id = ? AND t.lead_id = ? AND t.done_at IS NULL ORDER BY (t.due_at IS NULL), t.due_at, t.id DESC LIMIT 30'); $tq->execute([$wid, $lid]);
    $tasks = array_map(function ($t) { return ['id' => (int)$t['id'], 'title' => (string)$t['title'], 'due' => $t['due_at'] ? fmt_date($t['due_at'], 'd/m/Y H:i') : '', 'over' => $t['due_at'] && strtotime($t['due_at']) < time() ? 1 : 0, 'who' => (string)($t['who'] ?? '')]; }, $tq->fetchAll());
    $mails = array_map(function ($m) { return ['subject' => (string)$m['subject'], 'opens' => (int)$m['opens'], 'clicks' => (int)$m['clicks'], 'at' => fmt_date($m['created_at'], 'd/m/Y H:i'), 'opened' => $m['opened_at'] ? fmt_date($m['opened_at'], 'd/m/Y H:i') : '']; }, crm_mails($lid));
    $deals = array_map(function ($d) { return ['id' => (int)$d['id'], 'title' => (string)$d['title'], 'value' => (float)$d['value'], 'status' => (string)$d['status'], 'close' => (string)($d['close_at'] ?? '')]; }, crm_deals($lid));
    $J(['ok' => true, 'acts' => array_map($actOut, array_slice($raw, 0, 30)), 'note' => (string)$l['note'],
        'deals' => $deals, 'mails' => $mails, 'tasks' => $tasks, 'run' => crm_lead_run($lid), 'intel' => crm_intel($l, $raw), 'value' => $l['deal_value'] === null ? '' : rtrim(rtrim((string)$l['deal_value'], '0'), '.'),
        'pl' => (string)($l['pipeline'] ?? 'sales'), 'cf' => json_decode((string)($l['custom'] ?? ''), true) ?: (object)[], 'lost' => (string)($l['lost_reason'] ?? ''), 'unsub' => (int)($l['unsub'] ?? 0),
        'company' => $l['company_id'] ? url('crm', ['open' => (int)$l['company_id']]) : ''] + $nextOut($l));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    // تسجيل تواصل (مكالمة، واتساب، إيميل، اجتماع، ملاحظة)
    if ($a === 'log') {
        $l = $getLead((int)($_POST['id'] ?? 0)); $k = (string)($_POST['kind'] ?? '');
        if (!$l || !isset($kinds[$k]) || $k === 'stage') $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $body = trim((string)($_POST['body'] ?? '')); if ($k === 'note' && $body === '') $J(['ok' => false, 'error' => t('اكتب الملاحظة.')], 400);
        crm_act_add((int)$l['id'], $k, $body);
        if ($k !== 'note') db()->prepare('UPDATE crm_leads SET last_contact_at = NOW() WHERE id = ?')->execute([(int)$l['id']]);
        $J(['ok' => true, 'last' => $k !== 'note' ? fmt_date(date('Y-m-d H:i:s'), 'd/m/Y') : '', 'acts' => array_map($actOut, array_slice(crm_acts((int)$l['id']), 0, 20))]);
    }
    // تعديل سريع من البوب أب: الاسم والمسمى والشركة
    if ($a === 'edit') {
        $l = $getLead((int)($_POST['id'] ?? 0)); if (!$l) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $n = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 190); if ($n === '') $J(['ok' => false, 'error' => t('اكتب الاسم.')], 422);
        $ti = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190); $co = mb_substr(trim((string)($_POST['company'] ?? '')), 0, 190);
        db()->prepare('UPDATE crm_leads SET name = ?, title = ?, company = ? WHERE id = ?')->execute([$n, $ti !== '' ? $ti : null, $co !== '' ? $co : null, (int)$l['id']]);
        $J(['ok' => true, 'name' => $n, 'title' => $ti, 'company' => $co]);
    }
    // حقل واحد من تاب البيانات: قيمة الصفقة، الـ Pipeline، أو حقل مخصص (بيتحفظ لوحده)
    if ($a === 'field') {
        $l = $getLead((int)($_POST['id'] ?? 0)); if (!$l) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $f = (string)($_POST['f'] ?? ''); $v = trim((string)($_POST['v'] ?? '')); $lid = (int)$l['id'];
        if ($f === 'value') { $n = $v === '' ? null : max(0, (float)str_replace(',', '.', $v)); db()->prepare('UPDATE crm_leads SET deal_value = ? WHERE id = ?')->execute([$n, $lid]); }
        elseif ($f === 'pipeline') { if (!isset(crm_pipelines()[$v])) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400); db()->prepare('UPDATE crm_leads SET pipeline = ? WHERE id = ?')->execute([$v, $lid]); }
        elseif (strpos($f, 'cf.') === 0) { $cf = json_decode((string)($l['custom'] ?? ''), true) ?: []; $cf[substr($f, 3)] = $v; $cf = crm_custom_clean($cf);
            db()->prepare('UPDATE crm_leads SET custom = ? WHERE id = ?')->execute([$cf ? json_encode($cf, JSON_UNESCAPED_UNICODE) : null, $lid]); }
        else $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $J(['ok' => true]);
    }
    // شيل تاج
    if ($a === 'untag') {
        $l = $getLead((int)($_POST['id'] ?? 0)); if (!$l) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $rm = mb_strtolower(trim((string)($_POST['tag'] ?? '')));
        $keep = array_values(array_filter(array_map('trim', explode(',', (string)$l['tags'])), function ($x) use ($rm) { return $x !== '' && mb_strtolower($x) !== $rm; }));
        db()->prepare('UPDATE crm_leads SET tags = ? WHERE id = ?')->execute([$keep ? implode(', ', $keep) : null, (int)$l['id']]);
        $J(['ok' => true, 'tags' => implode(', ', $keep)]);
    }
    // إضافة رقم أو إيميل لعميل ناقص بيانات التواصل
    if ($a === 'contact') {
        $l = $getLead((int)($_POST['id'] ?? 0)); if (!$l) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $em = mb_strtolower(trim((string)($_POST['email'] ?? ''))); if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) $J(['ok' => false, 'error' => t('الإيميل مش صحيح.')], 422);
        $ph = trim((string)($_POST['phone'] ?? '')) !== '' ? crm_norm_phone((string)$_POST['phone'], (string)$l['country']) : '';
        if ($ph === '' && $em === '') $J(['ok' => false, 'error' => t('اكتب رقم أو إيميل.')], 422);
        $set = []; $v = []; if ($ph !== '') { $set[] = 'phone = ?'; $v[] = $ph; $set[] = 'phone_pending = 0'; } if ($em !== '') { $set[] = 'email = ?'; $v[] = $em; }
        $v[] = (int)$l['id']; db()->prepare('UPDATE crm_leads SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($v);
        $J(['ok' => true, 'phone' => $ph ?: (string)$l['phone'], 'email' => $em ?: (string)$l['email'], 'wa' => function_exists('crm_wa_digits') ? crm_wa_digits($ph ?: (string)$l['phone']) : '']);
    }
    // أكتر من رقم وأكتر من إيميل: الأساسي في phone/email والباقي في phones/emails
    if ($a === 'contacts') {
        $l = $getLead((int)($_POST['id'] ?? 0)); if (!$l) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $k = (string)($_POST['k'] ?? '') === 'email' ? 'email' : 'phone'; $op = (string)($_POST['op'] ?? 'add'); $v = trim((string)($_POST['v'] ?? ''));
        if ($k === 'email') { $v = mb_strtolower($v); if ($op === 'add' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $J(['ok' => false, 'error' => t('البريد الإلكتروني غير صحيح.')], 422); }
        elseif ($op === 'add') { $v = crm_norm_phone($v, (string)$l['country']); if ($v === '') $J(['ok' => false, 'error' => t('الرقم غير صحيح.')], 422); }
        $all = array_values(array_unique(array_filter(array_merge([(string)$l[$k]], json_decode((string)($l[$k . 's'] ?? ''), true) ?: []))));
        if ($op === 'add') { if (in_array($v, $all, true)) $J(['ok' => false, 'error' => t('موجود بالفعل.')], 422); if (count($all) >= 10) $J(['ok' => false, 'error' => t('الحد الأقصى 10.')], 422); $all[] = $v; }
        elseif ($op === 'del') $all = array_values(array_filter($all, function ($x) use ($v) { return $x !== $v; }));
        elseif ($op === 'main') { if (!in_array($v, $all, true)) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400); $all = array_merge([$v], array_values(array_filter($all, function ($x) use ($v) { return $x !== $v; }))); }
        else $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $main = $all[0] ?? null; $rest = array_slice($all, 1);
        db()->prepare("UPDATE crm_leads SET $k = ?, {$k}s = ?" . ($k === 'phone' && $main ? ', phone_pending = 0' : '') . ' WHERE id = ?')->execute([$main, $rest ? json_encode($rest) : null, (int)$l['id']]);
        $J(['ok' => true, 'list' => $all, 'main' => (string)$main, 'wa' => $k === 'phone' && $main ? crm_wa_digits($main) : '']);
    }
    // المتابعة الجاية: النهارده، بكرة، بعد 3 أيام، الأسبوع الجاي، تاريخ محدد، أو مفيش
    if ($a === 'view_save' || $a === 'view_del') {
        $k = 'crm_views_' . $wid . '_' . $uid; $v = json_decode((string)setting($k, ''), true); $v = is_array($v) ? $v : [];
        if ($a === 'view_del') $v = array_values(array_filter($v, function ($x) { return ($x['id'] ?? '') !== (string)($_POST['id'] ?? ''); }));
        else { $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 40); $st = json_decode((string)($_POST['state'] ?? ''), true);
            if ($name === '' || !is_array($st)) $J(['ok' => false, 'error' => t('اكتب اسم للفلتر.')]);
            $clean = []; foreach (['q', 'stat', 'src', 'stage', 'owner', 'country', 'from', 'to', 'pl', 'sort', 'dir'] as $f) if (isset($st[$f]) && (string)$st[$f] !== '') $clean[$f] = mb_substr((string)$st[$f], 0, 120);
            $v[] = ['id' => substr(bin2hex(random_bytes(4)), 0, 8), 'name' => $name, 's' => $clean]; $v = array_slice($v, -12); }
        save_setting($k, json_encode($v, JSON_UNESCAPED_UNICODE)); $J(['ok' => true, 'views' => $v]);
    }
    if ($a === 'next') {
        $w = (string)($_POST['when'] ?? ''); $tz = new DateTimeZone(site_timezone()); $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255); $n = 0; $last = null;
        $d = null;
        if ($w === 'today') { $d = new DateTime('today 16:00', $tz); if ($d < new DateTime('now', $tz)) $d = (new DateTime('now', $tz))->modify('+1 hour'); }
        elseif (isset(['1d' => 1, '3d' => 1, 'week' => 1][$w])) $d = (new DateTime('today 11:00', $tz))->modify('+' . ['1d' => 1, '3d' => 3, 'week' => 7][$w] . ' days');
        elseif ($w === 'at') { try { $d = new DateTime((string)($_POST['at'] ?? ''), $tz); } catch (Throwable $e) { $J(['ok' => false, 'error' => t('التاريخ مش صحيح.')], 400); } }
        elseif ($w === 'note') { $l = $getLead((int)($_POST['ids'][0] ?? $_POST['id'] ?? 0)); if (!$l) $J(['ok' => false], 404);
            db()->prepare('UPDATE crm_leads SET next_note = ? WHERE id = ? AND next_at IS NOT NULL')->execute([$note !== '' ? $note : null, (int)$l['id']]); $J(['ok' => true, 'n' => 1] + $nextOut($getLead((int)$l['id']))); }
        elseif ($w !== 'none') $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
        $at = $d ? $d->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s') : null;
        foreach (array_slice((array)($_POST['ids'] ?? [$_POST['id'] ?? 0]), 0, 500) as $id) { $l = $getLead((int)$id); if (!$l) continue;
            db()->prepare('UPDATE crm_leads SET next_at = ?, next_note = ? WHERE id = ?')->execute([$at, $at ? ($note !== '' ? $note : $l['next_note']) : null, (int)$l['id']]); $n++; $last = (int)$l['id']; }
        $one = $last ? $getLead($last) : null;
        $J(['ok' => true, 'n' => $n] + ($one ? $nextOut($one) : []));
    }
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

crm_country_normalize($wid);
$where = 'workspace_id = ?'; $args = [$wid];
if (!$canManage) { $where .= ' AND (owner_id = ? OR owner_id IS NULL)'; $args[] = $uid; }
// الـ Pipelines: كل واحد قايمته لوحده (تابات فوق الصفحة)
$pipes = crm_pipelines(); $plSel = array_values(array_filter(explode(',', (string)($_GET['pl'] ?? '')), function ($k) use ($pipes) { return isset($pipes[$k]); }));
if (count($plSel) >= count($pipes)) $plSel = []; // الكل
$pl = count($plSel) === 1 ? $plSel[0] : 'sales'; $plCnt = [];
if (count($pipes) > 1) { $st = db()->prepare("SELECT COALESCE(NULLIF(pipeline, ''), 'sales') k, COUNT(*) n FROM crm_leads WHERE $where GROUP BY k"); $st->execute($args); $plCnt = array_column($st->fetchAll(), 'n', 'k');
    if ($plSel) { $where .= " AND COALESCE(NULLIF(pipeline, ''), 'sales') IN (" . implode(',', array_fill(0, count($plSel), '?')) . ')'; array_push($args, ...$plSel); } }
$sources = crm_sources(); $stages = crm_stages();
$members = []; foreach (crm_members() as $m) $members[(int)$m['id']] = $m['name'];
$week = date('Y-m-d', strtotime('-7 days')); $eodS = date('Y-m-d H:i:s', strtotime('today 23:59:59')); $coldS = date('Y-m-d H:i:s', time() - 7 * 86400);
$eod = strtotime('today 23:59:59'); $open = function ($r) { return !in_array($r['stage'], ['won', 'lost'], true); };
$isDue = function ($r) use ($eod, $open) { return $open($r) && $r['next_at'] && strtotime($r['next_at']) <= $eod; };
$isCold = function ($r) use ($open) { return $open($r) && !$r['next_at'] && strtotime($r['last_contact_at'] ?: $r['created_at']) < time() - 7 * 86400; };
// الأرقام والقوايم كلها من SQL (مش بنحمّل كل العملاء في الذاكرة)
$SQ = ['phone' => "phone IS NOT NULL AND phone <> ''", 'email' => "email IS NOT NULL AND email <> ''", 'none' => "(phone IS NULL OR phone = '') AND (email IS NULL OR email = '')",
    'new' => 'created_at >= ' . db()->quote($week), 'due' => "stage NOT IN ('won','lost') AND next_at IS NOT NULL AND next_at <= " . db()->quote($eodS),
    'cold' => "stage NOT IN ('won','lost') AND next_at IS NULL AND COALESCE(last_contact_at, created_at) < " . db()->quote($coldS), 'ads' => "source = 'ads'"];
$st = db()->prepare('SELECT COUNT(*) a, ' . implode(', ', array_map(function ($k, $c) { return "COALESCE(SUM($c), 0) `$k`"; }, array_keys($SQ), $SQ)) . " FROM crm_leads WHERE $where"); $st->execute($args);
$stat = array_map('intval', $st->fetch()); $stat['all'] = $stat['a']; unset($stat['a']);
$cnt = ['src' => [], 'stage' => [], 'country' => []];
foreach (['src' => 'source', 'stage' => 'stage', 'country' => 'country'] as $k => $col) { $st = db()->prepare("SELECT $col v, COUNT(*) n FROM crm_leads WHERE $where AND $col IS NOT NULL AND $col <> '' GROUP BY $col ORDER BY n DESC"); $st->execute($args); foreach ($st->fetchAll() as $x) $cnt[$k][(string)$x['v']] = (int)$x['n']; }
// "جديد من آخر زيارة": آخر مرة المستخدم فتح الصفحة (بيتحدّث بعد ما الصفحة تتعرض)
$lvKey = 'crm_lastvisit_' . $wid . '_' . $uid; $lastVisit = (string)setting(!empty($_GET['ajax']) ? $lvKey . '_p' : $lvKey, ''); if ($lastVisit === '') $lastVisit = date('Y-m-d H:i:s'); // طلبات الصفحة بتستخدم الزيارة اللي قبلها
$SQ['since'] = 'created_at > ' . db()->quote($lastVisit);
$st = db()->prepare("SELECT COUNT(*) FROM crm_leads WHERE $where AND created_at > ?"); $st->execute(array_merge($args, [$lastVisit])); $sinceN = (int)$st->fetchColumn();
$PAGE = 100;
/** فلترة وترتيب وصفحات على السيرفر */
$buildWhere = function (array $g) use ($where, $args, $SQ) {
    $w = $where; $a = $args;
    if (isset($SQ[$g['stat'] ?? ''])) $w .= ' AND ' . $SQ[$g['stat']];
    // البحث بيتجاهل فروق الكتابة: أ/إ/آ=ا، ة=ه، ى=ي، ؤ=و، ئ=ي، والتشكيل؛ والأرقام بتتدوّر في الرقم من غير مسافات
    $q = trim((string)($g['q'] ?? '')); if ($q !== '') { $nq = crm_ar_norm($q); $col = "CONCAT_WS(' ', name, company, title, email, emails, tags)";
        foreach (['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي'] as $f => $r) $col = "REPLACE($col, '$f', '$r')";
        $dg = preg_replace('/\D/', '', $q); $w .= " AND (LOWER($col) LIKE ?" . (strlen($dg) >= 3 ? " OR REPLACE(REPLACE(REPLACE(CONCAT_WS(' ', phone, phones), ' ', ''), '-', ''), '+', '') LIKE ?" : '') . ')';
        $a[] = '%' . mb_strtolower($nq) . '%'; if (strlen($dg) >= 3) $a[] = '%' . ltrim($dg, '0') . '%'; }
    foreach (['src' => 'source', 'stage' => 'stage', 'country' => 'country'] as $k => $col) { $v = trim((string)($g[$k] ?? '')); if ($v !== '') { $w .= " AND $col = ?"; $a[] = $v; } }
    if (($g['owner'] ?? '') !== '') { $w .= ' AND COALESCE(owner_id, 0) = ?'; $a[] = (int)$g['owner']; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($g['from'] ?? ''))) { $w .= ' AND created_at >= ?'; $a[] = $g['from'] . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($g['to'] ?? ''))) { $w .= ' AND created_at <= ?'; $a[] = $g['to'] . ' 23:59:59'; }
    $sorts = ['name' => 'name', 'company' => 'company', 'src' => 'source', 'stage' => 'stage', 'last' => 'COALESCE(last_contact_at, created_at)'];
    $ord = ($g['sort'] ?? '') === 'next' ? 'next_at IS NULL, next_at' . ((int)($g['dir'] ?? 1) < 0 ? ' DESC' : ' ASC') . ', id DESC'
        : (isset($sorts[$g['sort'] ?? '']) ? $sorts[$g['sort']] . ((int)($g['dir'] ?? 1) < 0 ? ' DESC' : ' ASC') . ', id DESC' : 'COALESCE(pinned, 0) DESC, id DESC');
    return [$w, $a, $ord];
};
$fetchRows = function (array $g) use ($buildWhere, $PAGE) {
    [$w, $a, $ord] = $buildWhere($g);
    $st = db()->prepare("SELECT COUNT(*) FROM crm_leads WHERE $w"); $st->execute($a); $total = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT stage, COUNT(*) n, COALESCE(SUM(deal_value), 0) v FROM crm_leads WHERE $w GROUP BY stage"); $st->execute($a); $GLOBALS['pr_by_stage'] = [];
    foreach ($st->fetchAll() as $x) $GLOBALS['pr_by_stage'][(string)$x['stage']] = ['n' => (int)$x['n'], 'v' => (float)$x['v']];
    $off = max(0, (int)($g['page'] ?? 0)) * $PAGE;
    $st = db()->prepare("SELECT * FROM crm_leads WHERE $w ORDER BY $ord LIMIT $PAGE OFFSET $off"); $st->execute($a);
    return [$st->fetchAll(), $total];
};
$cols = 'md:grid-cols-[20px_minmax(140px,1.3fr)_minmax(150px,1.2fr)_100px_minmax(130px,1.3fr)_minmax(220px,1.4fr)_40px]';
$hasApollo = crm_has_key();
$syncAt = explode('|', (string)setting('tg_sync_' . $wid, ''))[0];
$syncDue = $syncAt === '' || strtotime($syncAt) < time() - 20 * 60;
$nexts = ['today' => t('النهارده'), '1d' => t('بكرة'), '3d' => t('بعد 3 أيام'), 'week' => t('الأسبوع الجاي'), 'none' => t('من غير متابعة')];
$srcIcon = ['inbox' => 'chat', 'comments' => 'chat', 'form' => 'file', 'ads' => 'megaphone', 'whatsapp' => 'chat', 'reveal' => 'eye', 'import' => 'upload', 'manual' => 'pencil', 'tender' => 'file'];

$ago = function ($d) { if (!$d) return ''; $n = (int)floor((strtotime('today') - strtotime(substr($d, 0, 10))) / 86400); return $n <= 0 ? t('النهارده') : ($n === 1 ? t('امبارح') : t('من %s يوم', $n)); };
$canReveal = $hasApollo && can('crm.reveal');
$rowsHtml = function (array $rows) use ($canReveal, $cols, $isDue, $isCold, $ago, $sources, $stages, $srcIcon, $wid, $lastVisit, $members, $canManage, $kinds) {
    if (!$rows) return '';
    $actN = []; $ids = array_map(function ($r) { return (int)$r['id']; }, $rows); $q = db()->prepare('SELECT lead_id, COUNT(*) FROM crm_acts WHERE workspace_id = ? AND lead_id IN (' . implode(',', $ids) . ') GROUP BY lead_id'); $q->execute([$wid]); foreach ($q->fetchAll(PDO::FETCH_NUM) as $x) $actN[(int)$x[0]] = (int)$x[1];
    $lastAct = []; $q = db()->prepare("SELECT a.lead_id, a.kind, a.body, a.created_at FROM crm_acts a JOIN (SELECT lead_id, MAX(id) mid FROM crm_acts WHERE workspace_id = ? AND kind <> 'stage' AND lead_id IN (" . implode(',', $ids) . ") GROUP BY lead_id) m ON m.mid = a.id"); $q->execute([$wid]);
    foreach ($q->fetchAll() as $x) $lastAct[(int)$x['lead_id']] = ['kind' => (string)$x['kind'], 'body' => trim(preg_replace('/\s+/u', ' ', (string)$x['body'])), 'created_at' => (string)$x['created_at']];
    ob_start(); ?>
      <?php foreach ($rows as $r): $has = $r['phone'] || $r['email']; $wa = $r['phone'] ? crm_wa_digits($r['phone']) : '';
          $j = crm_lead_card($r, $actN[(int)$r['id']] ?? 0); ?>
        <div class="mk-row <?= $cols ?>" data-mk-row data-id="<?= (int)$r['id'] ?>" data-phone="<?= $r['phone'] ? 1 : 0 ?>" data-email="<?= $r['email'] ? 1 : 0 ?>" data-none="<?= $has ? 0 : 1 ?>" data-new="<?= substr((string)$r['created_at'], 0, 10) >= date('Y-m-d', strtotime('-7 days')) ? 1 : 0 ?>"
          data-src="<?= e($r['source']) ?>" data-stage="<?= e($r['stage']) ?>" data-owner="<?= (int)$r['owner_id'] ?>" data-country="<?= e(crm_country_iso((string)$r['country']) ?: trim((string)$r['country'])) ?>" data-date="<?= e(substr((string)$r['created_at'], 0, 10)) ?>" data-due="<?= $isDue($r) ? 1 : 0 ?>" data-cold="<?= $isCold($r) ? 1 : 0 ?>" data-s-last="<?= e((string)($r['last_contact_at'] ?: '0')) ?>"
          data-s-name="<?= e(mb_strtolower((string)$r['name'])) ?>" data-s-company="<?= e(mb_strtolower((string)$r['company'])) ?>" data-s-src="<?= e($r['source']) ?>" data-s-stage="<?= e($r['stage']) ?>" data-s-date="<?= e((string)$r['created_at']) ?>"
          data-q="<?= e(mb_strtolower($r['name'] . ' ' . $r['company'] . ' ' . $r['title'] . ' ' . $r['phone'] . ' ' . $r['email'] . ' ' . $r['tags'])) ?>" data-j="<?= e(json_encode($j, JSON_UNESCAPED_UNICODE)) ?>">
          <input type="checkbox" data-mk-check aria-label="<?= e(t('تحديد')) ?>">
          <?php $la = $lastAct[(int)$r['id']] ?? null; $due = $isDue($r); $tg = array_slice(array_filter(array_map('trim', explode(',', (string)$r['tags']))), 0, 2); $sub = array_filter([(string)$r['company'], $sources[$r['source']] ?? $r['source']]); ?>
          <span class="pr-cn min-w-0">
            <b class="pr-nm <?= $has ? '' : 'dim' ?>"><button type="button" class="pr-pin<?= !empty($r['pinned']) ? ' on' : '' ?>" data-qpin data-mk-stop title="<?= e(t('ثبّته فوق')) ?>"><?= ui_icon('star', 'w-3.5 h-3.5') ?></button><?php if ((string)$r['created_at'] > $lastVisit): ?><i class="pr-newdot" title="<?= e(t('جديد من آخر زيارة')) ?>"></i><?php endif; ?><?= e($r['name']) ?><span class="pr-ownw"><?php if ($canManage && $members): $on = (int)$r['owner_id']; $onm = $members[$on] ?? ''; ?><button type="button" class="pr-own<?= $onm ? '' : ' none' ?>" data-qown data-mk-stop title="<?= e($onm ?: t('عيّن مسؤول')) ?>"><?= $onm ? e(mb_strtoupper(mb_substr($onm, 0, 1))) : ui_icon('user_plus', 'w-3.5 h-3.5') ?></button><?php endif; ?></span><?php if ($r['company']): ?><small class="pr-co"><?= ui_icon('building', 'w-3.5 h-3.5') ?><?= e($r['company']) ?></small><?php endif; ?><small class="pr-sub"><?= e(implode(' · ', array_filter([(string)$r['title'], $sources[$r['source']] ?? $r['source']]))) ?></small></b>
            <span data-c="next" class="<?= $r['next_at'] ? ($due ? 'pr-late' : 'pr-soon') : '' ?>"><?php if ($r['next_at']): ?><?= ui_icon($due ? 'alert' : 'calendar', 'w-3 h-3 inline -mt-0.5') ?> <?= e($due ? t('متأخرة') : fmt_date($r['next_at'], 'd/m')) ?><?php endif; ?></span>
            <span class="pr-ctx"><?php foreach ($tg as $x): ?><i>#<?= e($x) ?></i><?php endforeach; ?><button type="button" class="pr-tagadd" data-qtag data-mk-stop title="<?= e(t('ضيف تاج')) ?>">+ <?= e(t('تاج')) ?></button></span>
          </span>
          <span class="pr-cct"><?php $phs = array_values(array_filter(array_merge([(string)$r['phone']], json_decode((string)($r['phones'] ?? ''), true) ?: []))); $ems = array_values(array_filter(array_merge([(string)$r['email']], json_decode((string)($r['emails'] ?? ''), true) ?: []))); ?>
            <?php foreach ($phs as $i => $ph): $w = crm_wa_digits($ph); ?><span class="pr-cl" dir="ltr"><span class="pr-num<?= $i ? ' x' : '' ?>"><?= e($ph) ?></span><a class="mk-act" data-log="call" href="tel:<?= e($ph) ?>" title="<?= e(t('اتصال')) ?>"><?= ui_icon('phone', 'w-3.5 h-3.5') ?></a><?php if ($w): ?><a class="mk-act" data-log="wa" href="https://wa.me/<?= e($w) ?>" target="_blank" rel="noopener" title="<?= e(t('واتساب')) ?>"><?= ui_icon('chat', 'w-3.5 h-3.5') ?></a><?php endif; ?><?php if ($i): ?><button type="button" class="pr-cdel pr-cmk" data-qcmain data-k="phone" data-v="<?= e($ph) ?>" data-mk-stop title="<?= e(t('اجعله الأساسي')) ?>"><?= ui_icon('star', 'w-3 h-3') ?></button><?php endif; ?><button type="button" class="pr-cdel" data-qcdel data-k="phone" data-v="<?= e($ph) ?>" data-mk-stop title="<?= e(t('حذف')) ?>"><?= ui_icon('x', 'w-3 h-3') ?></button></span><?php endforeach; ?>
            <?php if (!$phs && !empty($r['phone_pending'])): ?><span class="pr-cl pend"><?= ui_icon('refresh', 'w-3.5 h-3.5') ?><?= e(t('بنجيب الرقم من Apollo…')) ?></span><?php endif; ?>
            <?php foreach ($ems as $i => $em): ?><span class="pr-cl" dir="ltr"><?= ui_icon('mail', 'w-3.5 h-3.5') ?><a class="truncate pr-em" data-log="email" href="mailto:<?= e($em) ?>" title="<?= e($em) ?>"><?= e($em) ?></a><?php if (!$i && ($r['email_status'] ?? '') === 'verified'): ?><i class="pr-ver" title="<?= e(t('متأكد منه (Apollo)')) ?>"><?= ui_icon('check', 'w-3 h-3') ?></i><?php endif; ?><?php if ($i): ?><button type="button" class="pr-cdel pr-cmk" data-qcmain data-k="email" data-v="<?= e($em) ?>" data-mk-stop title="<?= e(t('اجعله الأساسي')) ?>"><?= ui_icon('star', 'w-3 h-3') ?></button><?php endif; ?><button type="button" class="pr-cdel" data-qcdel data-k="email" data-v="<?= e($em) ?>" data-mk-stop title="<?= e(t('حذف')) ?>"><?= ui_icon('x', 'w-3 h-3') ?></button></span><?php endforeach; ?>
            <?php if ($r['linkedin']): ?><a class="pr-cl pr-li" href="<?= e($r['linkedin']) ?>" target="_blank" rel="noopener" data-mk-stop>in · LinkedIn</a><?php endif; ?>
            <span class="pr-cbtns"><button type="button" class="pr-cadd" data-qcadd data-mk-stop><?= ui_icon('plus', 'w-3.5 h-3.5') ?><?= e(t('رقم أو إيميل')) ?></button><?php if ((!$phs || !$ems) && empty($r['phone_pending']) && $canReveal): ?><button type="button" class="pr-reveal" data-qreveal data-mk-stop title="<?= e(t('اكشف البيانات من Apollo')) ?>"><?= ui_icon('spark', 'w-3.5 h-3.5') ?><?= e(!$phs && !$ems ? t('اكشف') : (!$phs ? t('اكشف الرقم') : t('اكشف الإيميل'))) ?></button><?php endif; ?></span>
          </span>
          <span><button type="button" class="pr-st pr-st-<?= e($r['stage']) ?>" data-stage-pill data-qstage data-mk-stop title="<?= e(t('غيّر المرحلة')) ?>"><?= e($stages[$r['stage']] ?? $r['stage']) ?></button></span>
          <span class="pr-notec"><span class="pr-note" data-c="note" dir="auto" title="<?= e($la['body'] ?? '') ?>"><?= $la ? e($la['body'] !== '' ? $la['body'] : ($kinds[$la['kind']][1] ?? $la['kind'])) : '<em>' . e(t('لسه مفيش')) . '</em>' ?></span><?php if ($la || $r['last_contact_at']): ?><small class="pr-nd"><?= ui_icon('calendar', 'w-3.5 h-3.5') ?><span data-c="ago"><?= $la ? e(fmt_date($la['created_at'], 'd/m/Y')) : e(fmt_date($r['last_contact_at'], 'd/m/Y')) ?></span></small><?php else: ?><small class="pr-nd hidden"><?= ui_icon('calendar', 'w-3.5 h-3.5') ?><span data-c="ago"></span></small><?php endif; ?></span>
                    <form class="pr-uf" data-no-ajax data-mk-stop><input name="note" dir="auto" autocomplete="off" placeholder="<?= e(t('ملاحظة سريعة…')) ?>"><div><input type="datetime-local" name="at" title="<?= e(t('المتابعة الجاية')) ?>"><select name="stage" title="<?= e(t('المرحلة')) ?>"><?php foreach ($stages as $sk => $sl): ?><option value="<?= e($sk) ?>"<?= $sk === $r['stage'] ? ' selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select></div><button><?= ui_icon('check', 'w-4 h-4') ?><?= e(t('حفظ')) ?></button></form>
          <span class="pr-acts2"><button type="button" class="pr-bview" data-qview data-mk-stop title="<?= e(t('عرض')) ?>"><?= ui_icon('eye', 'w-4 h-4') ?></button><button type="button" class="pr-bedit" data-qedit data-mk-stop title="<?= e(t('تعديل')) ?>"><?= ui_icon('pencil', 'w-4 h-4') ?></button>
            <span class="pr-bsm"><button type="button" class="pr-bexp" data-qexp data-mk-stop title="<?= e(t('المتابعات السابقة')) ?>"><?= ui_icon('chevron-down', 'w-4 h-4') ?></button></span></span>
        </div>
      <?php endforeach; ?>
<?php return ob_get_clean(); };
// صفحات القايمة (بحث/فلاتر/ترتيب/تمرير)
if (($_GET['ajax'] ?? '') === 'rows') { [$rs, $tot] = $fetchRows($_GET); $J(['ok' => true, 'html' => $rowsHtml($rs), 'total' => $tot, 'n' => count($rs), 'stages' => $GLOBALS['pr_by_stage'] ?? []]); }
// "تحديد الكل": كل الـ ids اللي ماشية على الفلتر الحالي (للعمليات بالجملة)
if (($_GET['ajax'] ?? '') === 'ids') { [$w, $a, $ord] = $buildWhere($_GET); $st = db()->prepare("SELECT id FROM crm_leads WHERE $w ORDER BY $ord LIMIT 50000"); $st->execute($a); $J(['ok' => true, 'ids' => array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN))]); }
// تصدير كل اللي ماشيين على الفلتر (CSV بيتكتب على طول من غير ما يتحمّل في الذاكرة)
if (($_GET['ajax'] ?? '') === 'export') {
    [$w, $a, $ord] = $buildWhere($_GET); $st = db()->prepare("SELECT * FROM crm_leads WHERE $w ORDER BY $ord"); $st->execute($a);
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"'); $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, [t('الاسم'), t('الرقم'), t('الإيميل'), t('أرقام إضافية'), t('إيميلات إضافية'), t('الشركة'), t('المسمى'), t('الدولة'), t('المصدر'), t('المرحلة'), t('التاجات'), t('القيمة'), t('اتضاف'), t('آخر تواصل'), t('المتابعة الجاية')]);
    while ($r = $st->fetch()) fputcsv($o, [$r['name'], $r['phone'], $r['email'], implode(' | ', json_decode((string)($r['phones'] ?? ''), true) ?: []), implode(' | ', json_decode((string)($r['emails'] ?? ''), true) ?: []), $r['company'], $r['title'], crm_country_label((string)$r['country']), $sources[$r['source']] ?? $r['source'], $stages[$r['stage']] ?? $r['stage'], $r['tags'], $r['deal_value'], $r['created_at'], $r['last_contact_at'], $r['next_at']]);
    fclose($o); log_activity('crm.export', (string)$st->rowCount()); exit;
}
// طابور الاتصال: كل اللي ليهم رقم ومش مقفولين (مش بس اللي اتحمّلوا في الصفحة)
if (($_GET['ajax'] ?? '') === 'queue') {
    $st = db()->prepare("SELECT * FROM crm_leads WHERE $where AND phone IS NOT NULL AND phone <> '' AND stage NOT IN ('won','lost') ORDER BY (next_at IS NOT NULL AND next_at <= ?) DESC, next_at IS NULL, next_at, id DESC LIMIT 300");
    $st->execute(array_merge($args, [$eodS])); $J(['ok' => true, 'leads' => array_map(function ($r) { return crm_lead_card($r); }, $st->fetchAll())]);
}
// المكررين على كل العملاء: نفس آخر 9 أرقام في الرقم أو نفس الإيميل
if (($_GET['ajax'] ?? '') === 'dups') {
    $pairs = []; $ids = [];
    $st = db()->prepare("SELECT id, phone, phones, email, emails FROM crm_leads WHERE $where AND (phone <> '' OR email <> '' OR phones IS NOT NULL OR emails IS NOT NULL) ORDER BY id"); $st->execute($args);
    $by = [];
    while ($r = $st->fetch()) { $id = (int)$r['id'];
        foreach (array_merge([(string)$r['phone']], json_decode((string)($r['phones'] ?? ''), true) ?: []) as $p) { $d = substr(preg_replace('/\D/', '', (string)$p), -9); if (strlen($d) >= 6) $by['p' . $d][$id] = 1; }
        foreach (array_merge([(string)$r['email']], json_decode((string)($r['emails'] ?? ''), true) ?: []) as $e) { $e = mb_strtolower(trim((string)$e)); if (strlen($e) >= 6) $by['e' . $e][$id] = 1; } }
    foreach ($by as $g) { if (count($g) < 2) continue; $x = array_keys($g);
        for ($i = 1; $i < count($x) && count($pairs) < 100; $i++) { $pk = $x[0] . '-' . $x[$i]; if (!isset($pairs[$pk])) { $pairs[$pk] = [$x[0], $x[$i]]; $ids[$x[0]] = 1; $ids[$x[$i]] = 1; } }
        if (count($pairs) >= 100) break; }
    $leads = []; if ($ids) { $st = db()->prepare('SELECT * FROM crm_leads WHERE workspace_id = ? AND id IN (' . implode(',', array_keys($ids)) . ')'); $st->execute([$wid]); foreach ($st->fetchAll() as $r) $leads[(int)$r['id']] = crm_lead_card($r); }
    $J(['ok' => true, 'pairs' => array_values($pairs), 'leads' => $leads]);
}
[$rows, $total] = $fetchRows([]); if (empty($_GET['ajax'])) { save_setting($lvKey . '_p', $lastVisit); save_setting($lvKey, date('Y-m-d H:i:s')); } $byStage = $GLOBALS['pr_by_stage'] ?? [];
$st = db()->prepare("SELECT COUNT(*) FROM crm_leads WHERE $where AND phone IS NOT NULL AND phone <> '' AND stage NOT IN ('won','lost')"); $st->execute($args); $qN = (int)$st->fetchColumn();

render(t('العملاء المستهدفين'), 'prospects', function () use ($sinceN, $uid, $wid, $byStage, $qN, $rowsHtml, $total, $PAGE, $cols, $pipes, $pl, $plSel, $plCnt, $isDue, $isCold, $ago, $rows, $sources, $stages, $members, $cnt, $stat, $hasApollo, $canManage, $syncDue, $srcIcon, $nexts, $kinds) {
    $sel = function (string $id, string $label, array $opts) { $h = '<label class="block text-xs text-ink-600">' . e($label) . '<select id="' . $id . '" class="input !mt-1 text-sm"><option value="">' . e(t('الكل')) . '</option>';
        foreach ($opts as $v => $l) $h .= '<option value="' . e((string)$v) . '">' . e($l) . '</option>'; return $h . '</select></label>'; };
    ?>
    <div id="pr" data-mk data-mk-total="<?= (int)$total ?>" data-mk-page="<?= (int)$PAGE ?>">
    <?php if (count($pipes) > 1): $plAll = array_sum(array_map('intval', $plCnt)); $plLbl = $plSel ? implode('، ', array_map(function ($k) use ($pipes) { return $pipes[$k]; }, $plSel)) : t('كل الـ Pipelines'); ?>
    <div class="pr-plw"><button type="button" popovertarget="pr-plm" class="pr-plb"><?= ui_icon('route', 'w-4 h-4') ?><span><?= e($plLbl) ?></span><b><?= number_format($plSel ? array_sum(array_map(function ($k) use ($plCnt) { return (int)($plCnt[$k] ?? 0); }, $plSel)) : $plAll) ?></b><?= ui_icon('chevron-down', 'w-4 h-4 opacity-60') ?></button>
      <div id="pr-plm" popover class="pr-menu pr-plm"><form method="get" data-no-ajax><input type="hidden" name="p" value="prospects">
        <label class="pr-pla"><input type="checkbox" data-plall <?= $plSel ? '' : 'checked' ?>><span><?= e(t('الكل')) ?></span><b><?= number_format($plAll) ?></b></label>
        <?php foreach ($pipes as $k => $v): ?><label><input type="checkbox" value="<?= e($k) ?>" data-plk <?= !$plSel || in_array($k, $plSel, true) ? 'checked' : '' ?>><span><?= e($v) ?></span><b><?= number_format((int)($plCnt[$k] ?? 0)) ?></b></label><?php endforeach; ?>
        <input type="hidden" name="pl" value="<?= e(implode(',', $plSel)) ?>"><div class="flex gap-2 mt-2"><button class="btn-primary !py-1.5 !text-xs flex-1"><?= e(t('عرض')) ?></button><?php if ($canManage): ?><a href="<?= e(url('crm_capture')) ?>#s-stages" class="btn-ghost !py-1.5 !text-xs" title="<?= e(t('إدارة الـ Pipelines')) ?>"><?= ui_icon('sliders', 'w-4 h-4') ?></a><?php endif; ?></div></form></div></div>
    <?php endif; ?>
    <button type="button" class="hidden" data-mk-stat="since" data-mk-test="" aria-hidden="true"></button>
    <div class="mk-stats">
      <?php foreach ([['_all', '', t('الكل'), $stat['all'], 'users', 'bg-ink-100 text-ink-700'], ['phone', 'phone=1', t('لديهم رقم'), $stat['phone'], 'phone', 'bg-lime-50 text-lime-700'],
          ['email', 'email=1', t('لديهم بريد'), $stat['email'], 'mail', 'bg-brand-50 text-brand-700'], ['none', 'none=1', t('دون بيانات تواصل'), $stat['none'], 'user', 'bg-amber-50 text-amber-700'],
          ['new', 'new=1', t('جدد آخر 7 أيام'), $stat['new'], 'new', 'bg-cyan-50 text-cyan-700'],
          ['due', 'due=1', t('متابعة النهارده'), $stat['due'], 'calendar', 'bg-red-50 text-red-600'], ['cold', 'cold=1', t('محتاجين اهتمام'), $stat['cold'], 'alert', 'bg-orange-50 text-orange-700'], ['ads', 'src=ads', t('من الإعلانات'), $stat['ads'], 'megaphone', 'bg-amber-50 text-amber-700']] as [$k, $test, $l, $n, $ic, $cl]): ?>
        <button type="button" class="mk-stat<?= $k === '_all' ? ' mk-on' : '' ?>" data-mk-stat="<?= $k ?>" data-mk-test="<?= e($test) ?>"><span class="mk-ic <?= $cl ?>"><?= ui_icon($ic, 'w-5 h-5') ?></span><span><b><?= number_format($n) ?></b><small><?= e($l) ?></small></span></button>
      <?php endforeach; ?>
    </div>

    <div class="mk-tools">
      <label class="mk-search"><?= ui_icon('search') ?><input type="search" data-mk-q placeholder="<?= e(t('اسم، شركة، رقم، إيميل، تاج…')) ?>"><kbd>/</kbd></label>
      <button type="button" popovertarget="pr-filters" class="btn-ghost"><?= ui_icon('filter', 'w-4 h-4') ?><?= e(t('فلاتر')) ?><span id="pr-fn" class="hidden rounded-full bg-brand-600 text-[#fff] text-[11px] px-1.5"></span></button>
      <span class="text-xs text-ink-500 tabular-nums" title="<?= e(t('معروض')) ?>"><span data-mk-shown></span></span>
      <span id="pr-sync" class="text-xs text-ink-400 hidden"><?= ui_icon('refresh', 'w-3.5 h-3.5 inline animate-spin') ?> <?= e(t('بنجيب الجديد…')) ?></span>
      <span class="ms-auto"></span>
      <button type="button" id="pr-dups" class="pr-pill hidden"><?= ui_icon('users', 'w-4 h-4') ?><span></span></button>
      <div class="pr-view" role="group"><button type="button" data-view="list" class="on" title="<?= e(t('قايمة')) ?>"><?= ui_icon('list', 'w-4 h-4') ?><span><?= e(t('قايمة')) ?></span></button><button type="button" data-view="board" title="<?= e(t('لوحة المراحل')) ?>"><?= ui_icon('grid', 'w-4 h-4') ?><span><?= e(t('لوحة')) ?></span></button></div>
      <button type="button" id="pr-queue" class="btn-ghost"><?= ui_icon('phone', 'w-4 h-4') ?><?= e(t('طابور الاتصال')) ?><b class="pr-qn"></b></button>
      <button type="button" popovertarget="pr-add-menu" class="btn-ghost"><?= ui_icon('plus', 'w-4 h-4') ?><?= e(t('عميل جديد')) ?><?= ui_icon('chevron-down', 'w-3.5 h-3.5 opacity-60') ?></button>
      <div id="pr-add-menu" popover class="pr-menu"><button type="button" id="pr-add-btn"><span style="--c:#4f46e5"><?= ui_icon('plus', 'w-5 h-5') ?></span><b><?= e(t('إضافة يدوي')) ?><small><?= e(t('اكتب الاسم والرقم')) ?></small></b></button>
        <button type="button" id="pr-card-btn"><span style="--c:#ea580c"><?= ui_icon('image', 'w-5 h-5') ?></span><b><?= e(t('من بطاقة عمل')) ?><small><?= e(t('صوّرها وإحنا نقراها')) ?></small></b></button>
        <button type="button" id="pr-import-btn"><span style="--c:#059669"><?= ui_icon('upload', 'w-5 h-5') ?></span><b><?= e(t('استيراد ملف')) ?><small><?= e(t('Excel أو CSV، كذا عميل مرة واحدة')) ?></small></b></button></div>
      <form method="post" action="<?= e(url('crm_leads')) ?>" class="m-0" data-no-ajax><?= csrf_field() ?><input type="hidden" name="action" value="sync_ads"><button class="btn-ghost" title="<?= e(t('هات عملاء فورمات الإعلانات الجداد')) ?>"><?= ui_icon('refresh', 'w-4 h-4') ?><?= e(t('مزامنة الإعلانات')) ?></button></form>
      <button type="button" id="pr-find-btn" class="btn-primary"><?= ui_icon('search', 'w-4 h-4') ?><?= e(t('ابحث عن شخص')) ?></button>
    </div>

    <div id="pr-filters" popover class="m-0 w-[min(560px,94vw)] rounded-2xl border border-ink-200 bg-white p-4 shadow-2xl">
      <div class="grid sm:grid-cols-2 gap-3">
        <?= $sel('pf-src', t('المصدر'), array_combine(array_keys($cnt['src']), array_map(function ($k) use ($sources, $cnt) { return ($sources[$k] ?? $k) . ' (' . $cnt['src'][$k] . ')'; }, array_keys($cnt['src'])))) ?>
        <?= $sel('pf-stage', t('المرحلة'), array_combine(array_keys($cnt['stage']), array_map(function ($k) use ($stages, $cnt) { return ($stages[$k] ?? $k) . ' (' . $cnt['stage'][$k] . ')'; }, array_keys($cnt['stage'])))) ?>
        <?php if ($canManage): ?><?= $sel('pf-owner', t('المسؤول'), ['0' => t('من غير مسؤول')] + $members) ?><?php endif; ?>
        <?= $sel('pf-country', t('الدولة'), array_combine(array_map('strval', array_keys($cnt['country'])), array_map(function ($k) use ($cnt) { return crm_country_label((string)$k) . ' (' . $cnt['country'][$k] . ')'; }, array_keys($cnt['country'])))) ?>
        <label class="block text-xs text-ink-600"><?= e(t('اتضاف من')) ?><input type="date" id="pf-from" class="input !mt-1 text-sm"></label>
        <label class="block text-xs text-ink-600"><?= e(t('لحد')) ?><input type="date" id="pf-to" class="input !mt-1 text-sm"></label>
      </div>
      <div class="flex justify-between items-center mt-3"><button type="button" id="pf-reset" class="text-xs link"><?= e(t('امسح الفلاتر')) ?></button><button type="button" id="pf-save" class="btn-ghost !py-1 !text-xs"><?= ui_icon('star', 'w-4 h-4') ?><?= e(t('احفظ الفلتر ده')) ?></button></div>
    </div>

    <div class="mk-table">
      <div class="mk-head <?= $cols ?>"><input type="checkbox" data-mk-all aria-label="<?= e(t('تحديد الكل')) ?>">
        <span data-mk-sort="name"><?= e(t('العميل')) ?></span><span><?= e(t('التواصل')) ?></span><span data-mk-sort="stage"><?= e(t('الحالة')) ?></span><span data-mk-sort="last"><?= e(t('آخر ملاحظة')) ?></span><span data-mk-sort="next" title="<?= e(t('رتّب بالمتابعة الجاية')) ?>"><?= e(t('متابعة سريعة')) ?></span><span></span></div>
      <div data-mk-body>
      <?= $rowsHtml($rows) ?>
      </div>
      <p data-mk-empty class="hidden p-8 text-center text-sm text-ink-500"><?= e($rows ? t('مفيش عملاء بالفلاتر دي.') : t('لسه مفيش عملاء. أي حد يكلمك من الرسائل أو التعليقات أو الفورم أو الإعلانات هيظهر هنا لوحده، أو استورد ملف، أو دوّر على شخص.')) ?></p>
    </div>
    <div id="pr-board" class="pr-board hidden"></div>

    <div class="mk-bar" data-mk-bar>
      <span class="text-sm"><b data-mk-nsel>0</b> <?= e(t('محدد')) ?></span>
      <button type="button" data-mk-selall class="mk-selall hidden" data-t-all="<?= e(t('اختار كل الـ %s')) ?>" data-t-on="<?= e(t('كل الـ %s متحددين — إلغاء')) ?>"></button>
      <select id="pb-stage" aria-label="<?= e(t('المرحلة')) ?>"><option value=""><?= e(t('غيّر المرحلة…')) ?></option><?php foreach ($stages as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
      <?php if ($canManage): ?><select id="pb-owner" aria-label="<?= e(t('المسؤول')) ?>"><option value=""><?= e(t('عيّن مسؤول…')) ?></option><?php foreach ($members as $k => $l): ?><option value="<?= (int)$k ?>"><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?>
      <select id="pb-next" aria-label="<?= e(t('المتابعة')) ?>"><option value=""><?= e(t('حدد متابعة…')) ?></option><?php foreach ($nexts as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
      <button type="button" id="pb-tag"><?= ui_icon('hash', 'w-4 h-4 inline') ?> <?= e(t('تاج')) ?></button>
      <?php if ($hasApollo && can('crm.reveal')): ?><button type="button" id="pb-enrich"><?= ui_icon('spark', 'w-4 h-4 inline') ?> <?= e(t('اكشف بياناتهم')) ?></button><button type="button" id="pb-seq"><?= ui_icon('mail', 'w-4 h-4 inline') ?> Sequence</button><?php endif; ?>
      <button type="button" id="pb-csv"><?= ui_icon('download', 'w-4 h-4 inline') ?> <?= e(t('تصدير')) ?></button>
      <button type="button" data-mk-clear class="!px-2" title="<?= e(t('إلغاء التحديد')) ?>"><?= ui_icon('x', 'w-4 h-4') ?></button>
    </div>
    </div>

    <?= lead_popup() ?>

    <dialog id="pr-add" class="mk-dlg !w-[min(520px,96vw)]">
      <div class="mk-dlg-head"><b class="flex-1 text-ink-900"><?= e(t('عميل جديد')) ?></b><button type="button" data-close class="p-2 rounded-xl text-ink-400 hover:bg-ink-100"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
      <form id="pr-add-f" class="p-5 grid grid-cols-2 gap-3 text-sm" data-no-ajax>
        <label class="col-span-2 pr-fl"><input name="name" required maxlength="255" placeholder=" " dir="auto"><span><?= e(t('الاسم')) ?> *</span></label>
        <label class="pr-fl"><input name="phone" maxlength="40" placeholder=" " dir="ltr" inputmode="tel"><span><?= e(t('الرقم')) ?></span></label>
        <label class="pr-fl"><input name="email" type="email" maxlength="255" placeholder=" " dir="ltr"><span><?= e(t('الإيميل')) ?></span></label>
        <label class="pr-fl"><input name="company" maxlength="255" placeholder=" " dir="auto"><span><?= e(t('الشركة')) ?></span></label>
        <label class="pr-fl"><input name="title" maxlength="255" placeholder=" " dir="auto"><span><?= e(t('المسمى')) ?></span></label>
        <p id="pr-add-dup" class="col-span-2 hidden rounded-xl bg-amber-50 p-2 text-xs text-amber-800"></p>
        <p class="col-span-2 text-[11px] text-ink-500"><?= e(t('لازم رقم أو إيميل. لو العميل موجود هيتدمج معاه بدل ما يتكرر.')) ?></p>
        <button class="btn-primary col-span-2"><?= ui_icon('plus', 'w-4 h-4') ?><?= e(t('أضف وافتح ملفه')) ?></button>
      </form>
    </dialog>

    <dialog id="pr-card" class="mk-dlg !w-[min(860px,96vw)]">
      <div class="mk-dlg-head"><b class="flex-1 text-ink-900"><?= e(t('عميل من بطاقة عمل')) ?></b><span id="pc-n" class="text-xs text-ink-500 me-2"></span><button type="button" data-close class="p-2 rounded-xl text-ink-400 hover:bg-ink-100"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
      <div id="pc-body" class="p-5"></div>
    </dialog>
    <dialog id="pr-merge" class="mk-dlg !w-[min(760px,96vw)]">
      <div class="mk-dlg-head"><b class="flex-1 text-ink-900"><?= e(t('دمج العملاء المكررين')) ?></b><span id="pr-mg-pos" class="text-xs text-ink-500 tabular-nums" dir="ltr"></span><button type="button" data-close class="p-2 rounded-xl text-ink-400 hover:bg-ink-100"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
      <div class="p-5"><p class="text-xs text-ink-500 mb-3"><?= e(t('اختار النسخة اللي هتفضل، واضغط على أي قيمة من التانية عشان تاخدها. السجل والصفقات والمهام بتتجمع في عميل واحد.')) ?></p><div id="pr-mg"></div>
        <div class="flex items-center gap-2 mt-4"><button type="button" id="pr-mg-skip" class="btn-ghost"><?= e(t('مش مكررين، اللي بعده')) ?></button><button type="button" id="pr-mg-go" class="btn-primary ms-auto"><?= ui_icon('users', 'w-4 h-4') ?><?= e(t('ادمجهم')) ?></button></div></div>
    </dialog>

    <dialog id="pr-seq" class="mk-dlg !w-[min(480px,96vw)]">
      <div class="mk-dlg-head"><b class="flex-1 text-ink-900"><?= e(t('ضيفهم لـ Sequence في Apollo')) ?></b><button type="button" data-close class="p-2 rounded-xl text-ink-400 hover:bg-ink-100"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
      <div class="p-5 space-y-3 text-sm"><div id="pr-seq-l" class="space-y-1.5"></div><label class="block text-xs text-ink-600"><?= e(t('هيتبعت من')) ?><select id="pr-seq-acc" class="input !mt-1 text-sm"></select></label>
        <p class="text-[11px] text-ink-500"><?= e(t('اللي مالهوش إيميل هيتشال لوحده.')) ?></p><button type="button" id="pr-seq-go" class="btn-primary w-full" disabled><?= e(t('ضيفهم')) ?></button></div>
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
    <script>window.PR = <?= json_encode(['csrf' => csrf_token(), 'allTags' => (function () use ($wid) { $st = db()->prepare("SELECT tags FROM crm_leads WHERE workspace_id = ? AND tags IS NOT NULL AND tags <> '' ORDER BY id DESC LIMIT 1500"); $st->execute([$wid]); $c = []; foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) foreach (explode(',', $x) as $tg) { $tg = trim($tg); if ($tg !== '') $c[$tg] = ($c[$tg] ?? 0) + 1; } arsort($c); return array_slice(array_keys($c), 0, 30); })(), 'oc' => crm_call_outcomes(), 'sinceN' => $sinceN, 'members' => (object)$members, 'nexts' => $nexts, 'stages' => $stages, 'views' => (function () use ($wid, $uid) { $v = json_decode((string)setting('crm_views_' . $wid . '_' . $uid, ''), true); return is_array($v) ? $v : []; })(), 'plSel' => implode(',', $plSel), 'pl' => $pl, 'byStage' => (object)$byStage, 'qN' => $qN, 'importUrl' => url('crm_leads'), 'P' => ['api' => url('crm_leads'), 'stages' => $stages, 'queue' => !empty($_GET['queue']), 'T' => ['due' => t('متابعة النهارده'), 'drop' => t('اسحب هنا'), 'empty' => t('مفيش'), 'qNone' => t('مفيش عملاء ليهم رقم في القايمة المعروضة.'), 'dups' => t('%s مكررين'), 'more' => t('حمّل %s كمان'), 'ocQ' => t('المكالمة كانت إزاي؟'), 'cAddQ' => t('أضف رقمًا أو بريدًا إلكترونيًا'), 'cPh' => t('رقم'), 'cEm' => t('إيميل'), 'cPhP' => t('مثال: 01001234567'), 'cEmP' => t('name@company.com'), 'cDelQ' => t('حذف %s؟'), 'cAdded' => t('تمت الإضافة ✓'), 'cMkDone' => t('تم تعيينه أساسيًا ✓'), 'cSave' => t('إضافة'), 'revealing' => t('بنكشف من Apollo…'), 'revealed' => t('اتكشفت البيانات ✓'), 'xNote' => t('ملاحظة سريعة…'), 'xWhen' => t('المتابعة الجاية'), 'xStage' => t('المرحلة'), 'xSave' => t('حفظ'), 'xHist' => t('آخر المتابعات'), 'xPrev' => t('المتابعات السابقة'), 'xNone' => t('لسه مفيش متابعات.'), 'xOpen' => t('افتح ملفه'), 'xEmpty' => t('اكتب ملاحظة أو حدد ميعاد أو غيّر المرحلة'), 'tagQ' => t('ضيف تاج'), 'tagPh' => t('اكتب تاج واضغط Enter'), 'pinned' => t('اتثبّت فوق'), 'unpinned' => t('اتشال التثبيت'), 'waQ' => t('ابعت رسالة'), 'waEmpty' => t('رسالة فاضية'), 'waEdit' => t('عدّل القوالب'), 'waDone' => t('واتساب'), 'since' => t('%s جديد من آخر زيارة'), 'qOwn' => t('المسؤول'), 'noOwn' => t('من غير مسؤول'), 'qNext' => t('المتابعة'), 'qStage' => t('المرحلة'), 'qDone' => t('اتحدّث ✓'), 'vName' => t('اسم الفلتر (مثلًا: مهتمين من الإعلانات)'), 'vSaved' => t('اتحفظ الفلتر ⭐'), 'vDel' => t('امسح الفلتر ده؟'), 'vEmpty' => t('اختار فلتر أو اكتب بحث الأول'), 'vTitle' => t('فلاتر محفوظة'), 'working' => t('بنشتغل… %s / %s'), 'bigAsk' => t('هتتطبق على %s عميل. متأكد؟'),
        'keep' => t('دي اللي هتفضل'), 'merged' => t('اتدمج ✓'), 'mgDone' => t('خلصنا الدمج'), 'dupFound' => t('موجود قبل كده: %s — هيتدمج معاه.'), 'seqLoad' => t('بنجيب الـ Sequences…'), 'noSeq' => t('مفيش Sequences في Apollo.'), 'enriched' => t('اتكشف %s'), 'added' => t('اتضاف ✓'), 'f' => ['name' => t('الاسم'), 'phone' => t('الرقم'), 'email' => t('الإيميل'), 'company' => t('الشركة'), 'title' => t('المسمى'), 'stage' => t('المرحلة'), 'country' => t('الدولة')]]], 'selfUrl' => url('prospects'), 'findUrl' => url('crm_find'), 'leadUrl' => url('crm_leads'), 'syncDue' => $syncDue,
        'xlsLib' => 'assets/vendor/xlsx-0.20.3.full.min.js?v=' . APP_VERSION, 'stages' => $stages, 'members' => $members,
        'L' => lead_popup_L()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/mk-list.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/lead-popup.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/prospects.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/extract.js?v=<?= e(APP_VERSION) ?>"></script>
    <script>window.PCARD = <?= json_encode(['api' => url('crm_capture'), 'csrf' => csrf_token(), 'self' => url('prospects'), 'T' => ['dropT' => t('اسحب صورة البطاقة هنا'), 'dropD' => t('أو اضغط تصوّرها بالموبايل، أو الصق صورة (Ctrl+V)'), 'reading' => t('بنقرا البطاقة…'), 'none' => t('مالقيناش كلام واضح. جرّب صورة أوضح وفي إضاءة كويسة.'),
      'lines' => t('الكلام اللي اتقرا — اضغط على سطر وبعدين على الخانة اللي يروح لها'), 'pick' => t('اختار الخانة دلوقتي'), 'name' => t('الاسم'), 'phone' => t('الرقم'), 'email' => t('الإيميل'), 'company' => t('الشركة'), 'title' => t('المسمى'), 'site' => t('الموقع'), 'event' => t('اتقابلنا فين؟'), 'eventP' => t('معرض، اجتماع، مناسبة…'),
      'keep' => t('افتكر المكان للبطاقات الجاية'), 'save' => t('احفظ وافتح ملفه'), 'saveNext' => t('احفظ وصوّر اللي بعدها'), 'retake' => t('صورة تانية'), 'dup' => t('موجود قبل كده: %s — هيتحدّث بدل ما يتكرر'), 'need' => t('لازم رقم أو إيميل'), 'saved' => t('اتضاف ✓'), 'merged' => t('كان موجود واتحدّث ✓'), 'count' => t('%s بطاقة النهارده'), 'err' => t('حصلت مشكلة. جرّب تاني.'), 'wa' => t('واتساب'), 'call' => t('اتصال')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/pr-card.js?v=<?= e(APP_VERSION) ?>"></script>
    <script src="assets/prospects-pro.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
