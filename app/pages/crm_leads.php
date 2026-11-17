<?php
/** إدارة العملاء ← Leads: كل العملاء المحتملين في مكان واحد، قائمة أو لوحة، متابعات، توزيع، دمج المكرر */
$wid = (int)ws('id');
$uid = (int)(current_user()['id'] ?? 0);
$canManage = can('crm.manage');
$stages = crm_stages();
$sources = crm_sources();
$members = crm_members();
$memberIds = array_map('intval', array_column($members, 'id'));
$json = function (array $a) { crm_sync_won(); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
$out = function (array $l) {
    return ['id' => (int)$l['id'], 'name' => $l['name'], 'title' => (string)$l['title'], 'company' => (string)$l['company'], 'email' => (string)$l['email'],
        'phone' => (string)$l['phone'], 'pending' => (int)$l['phone_pending'] === 1, 'wa' => crm_wa_digits($l['phone']), 'linkedin' => (string)$l['linkedin'],
        'country' => crm_country_label((string)$l['country']), 'cc' => crm_country_iso((string)$l['country']), 'stage' => $l['stage'], 'source' => $l['source'], 'value' => $l['deal_value'] === null ? '' : rtrim(rtrim((string)$l['deal_value'], '0'), '.'),
        'note' => (string)$l['note'], 'tags' => (string)$l['tags'], 'owner' => (int)$l['owner_id'], 'next_at' => $l['next_at'] ? fmt_date($l['next_at'], 'Y-m-d\TH:i') : '', // بتوقيت الشركة (اللي الموظف شايفه)
        'next_note' => (string)$l['next_note'], 'created' => $l['created_at'], 'last' => (string)($l['last_contact_at'] ?? ''), 'src_url' => (string)($l['src_url'] ?? ''), 'es' => (string)($l['email_status'] ?? ''), 'edit' => crm_can_edit($l),
        'pl' => (string)($l['pipeline'] ?? 'sales'), 'pin' => (int)($l['pinned'] ?? 0), 'cf' => json_decode((string)($l['custom'] ?? ''), true) ?: (object)[], 'unsub' => (int)($l['unsub'] ?? 0)];
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
                'text' => $it['text'] ?? '', 'url' => $it['url'] ?? '', 'where' => $it['where'] ?? '', 'owner_id' => $canManage ? null : $uid, 'respect_owner' => 1]);
            if (!$l) continue;
            if (!empty($l['locked'])) continue; // عميل زميل: يتخطى
            if ($l['merged']) $m++; else $n++;
        }
        log_activity('crm.suggest_add', "$n new");
          unset($_SESSION['crm_sug_' . $wid]);
        $json(['ok' => true, 'new' => $n, 'merged' => $m]);
    }
    if ($action === 'cta_save' && $canManage) {
        save_setting('crm_cta_words_' . $wid, mb_substr(trim((string)($_POST['words'] ?? '')), 0, 300));
        unset($_SESSION['crm_sug_' . $wid]);
        $json(['ok' => true, 'words' => crm_cta_words()]);
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
        if (!$z && !getenv('UI_FIXTURES')) { flash('error', $zerr ?: t('اربط Zernio الأول.')); redirect(url('prospects')); }
        try {
            [$n, $m] = crm_sync_ads($z ?? new Zernio('fixture'), 10);
            log_activity('crm.sync_ads', $n . ' new');
            flash('success', $n ? t('اتضاف %s Lead جديد من الإعلانات.', $n) : t('مفيش Leads جديدة في الإعلانات.'));
        } catch (ZernioException $ex) {
            flash('error', $ex->getMessage());
        }
        redirect(url('prospects'));
    }
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || (trim((string)($_POST['email'] ?? '')) === '' && trim((string)($_POST['phone'] ?? '')) === '')) $json(['ok' => false, 'error' => t('اكتب الاسم ورقم أو إيميل على الأقل.')]);
        $l = crm_lead_upsert(['source' => 'manual', 'name' => $name, 'company' => $_POST['company'] ?? '', 'title' => $_POST['title'] ?? '',
            'email' => $_POST['email'] ?? '', 'phone' => $_POST['phone'] ?? '', 'owner_id' => $canManage ? null : $uid, 'respect_owner' => 1]);
        if (!empty($l['locked'])) $json(['ok' => false, 'error' => t('هذا الشخص مسجّل بالفعل كعميل لزميل آخر.')]);
        if (empty($l['merged']) && isset(crm_pipelines()[$_POST['pl'] ?? '']) && $_POST['pl'] !== 'sales') { db()->prepare('UPDATE crm_leads SET pipeline = ? WHERE id = ?')->execute([(string)$_POST['pl'], (int)$l['id']]); $l = crm_lead((int)$l['id']) + ['merged' => false]; }
        log_activity('crm.add', $name);
        $json(['ok' => true, 'merged' => !empty($l['merged']), 'lead' => $out($l)]);
    }
    if ($action === 'social') {
        // من صفحة الرسائل أو التعليقات
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' && trim((string)($_POST['ext'] ?? '')) === '') $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
        $l = crm_from_social(['source' => $_POST['source'] ?? '', 'ext' => $_POST['ext'] ?? '', 'name' => $name, 'phone' => $_POST['phone'] ?? '', 'email' => $_POST['email'] ?? '',
            'text' => $_POST['text'] ?? '', 'url' => $_POST['url'] ?? '', 'where' => $_POST['where'] ?? '', 'owner_id' => $canManage ? null : $uid, 'respect_owner' => 1]);
        if (!$l) $json(['ok' => false, 'error' => t('حصل خطأ، جرب تاني')]);
        if (!empty($l['locked'])) $json(['ok' => false, 'error' => t('هذا الشخص مسجّل بالفعل كعميل لزميل آخر.')]);
        log_activity('crm.from_social', (string)$l['id']);
        $json(['ok' => true, 'merged' => $l['merged'], 'id' => (int)$l['id'], 'phone' => (string)$l['phone'], 'email' => (string)$l['email'], 'url' => url('prospects', ['lead' => (int)$l['id']])]);
    }
    if ($action === 'bulk' || $action === 'enrich_stale') {
        // عمليات على عملاء كتير مرة واحدة
        $op = (string)($_POST['op'] ?? ($action === 'enrich_stale' ? 'enrich' : ''));
        if ($action === 'enrich_stale') {
            $st = db()->prepare("SELECT id FROM crm_leads WHERE workspace_id = ? AND stage NOT IN ('won','lost') AND (email IS NOT NULL OR company IS NOT NULL OR (ext_id IS NOT NULL AND ext_id NOT LIKE 'ib:%' AND ext_id NOT LIKE 'cm:%' AND ext_id NOT LIKE 'dom:%'))
                AND (enriched_at IS NULL AND (title IS NULL OR email IS NULL OR phone IS NULL) OR enriched_at < ?) " . ($canManage ? '' : 'AND owner_id = ' . $uid) . " ORDER BY enriched_at IS NOT NULL, enriched_at, id DESC LIMIT 25");
            $st->execute([$wid, date('Y-m-d H:i:s', time() - 90 * 86400)]);
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } else $ids = array_slice(array_map('intval', (array)($_POST['ids'] ?? [])), 0, 200);
        $ok = 0; $err = null; $skip = 0;
        if (in_array($op, ['enrich', 'seq'], true) && !crm_has_key()) $json(['ok' => false, 'error' => t('اربط حساب Apollo.io من التكاملات الأول.')]);
        if ($op === 'enrich' && !can('crm.reveal')) $json(['ok' => false, 'error' => t('مالكش صلاحية الكشف.')]);
        $prefs = crm_prefs();
        foreach ($ids as $i => $id) {
            $l = crm_lead($id);
            if (!$l || !crm_can_edit($l)) { $skip++; continue; }
            try {
                if ($op === 'stage' && isset($stages[$_POST['stage'] ?? ''])) {
                    $s = $_POST['stage'];
                    if ($s === 'lost') crm_set_lost_reason($id, (string)($_POST['lost_reason'] ?? ''));
                    if ($s !== $l['stage']) { db()->prepare('UPDATE crm_leads SET stage = ? WHERE id = ?')->execute([$s, $id]); crm_act_add($id, 'stage', $stages[$l['stage']] . (is_rtl() ? ' ← ' : ' → ') . $stages[$s]); crm_push_ad_stage(crm_lead($id)); }
                } elseif ($op === 'owner' && $canManage) {
                    $o = (int)($_POST['owner'] ?? 0); db()->prepare('UPDATE crm_leads SET owner_id = ? WHERE id = ?')->execute([in_array($o, $memberIds, true) ? $o : null, $id]);
                } elseif ($op === 'tag') {
                    crm_add_tag($id, (string)($_POST['tag'] ?? ''));
                } elseif ($op === 'enrich') {
                    if ($i >= 25) break;
                    if ((int)$prefs['daily_limit'] - crm_reveals_today($uid) <= 0) { $err = t('خلصت حد الكشف بتاعك النهارده (%s). بيتجدد بكرة.', (int)$prefs['daily_limit']); break; }
                    crm_enrich_lead($l, $prefs);
                } elseif ($op === 'seq') {
                    if (empty($l['email']) || !empty($l['unsub'])) { $skip++; continue; }
                    crm_add_to_sequence($l, (string)($_POST['seq'] ?? ''), (string)($_POST['acc'] ?? ''), mb_substr((string)($_POST['seq_name'] ?? ''), 0, 120));
                } else { $skip++; continue; }
                $ok++;
            } catch (CrmPlanException $ex) { $err = t('خطة Apollo بتاعتك مش بتسمح بالعملية دي.') . ' (' . $ex->getMessage() . ')'; break; }
            catch (CrmException $ex) { $skip++; $err = $ex->getMessage(); }
        }
        log_activity('crm.bulk_' . $op, $ok . '/' . count($ids));
        $json(['ok' => true, 'done' => $ok, 'skip' => $skip, 'error' => $err]);
    }
    if ($action === 'notifs_seen') {
        db()->prepare('UPDATE crm_notifs SET seen = 1 WHERE workspace_id = ? AND user_id = ?')->execute([$wid, $uid]);
        $json(['ok' => true]);
    }
    if ($action === 'sla_save' && $canManage) {
        save_setting('crm_sla_' . $wid, json_encode(['mins' => max(0, min(1440, (int)($_POST['mins'] ?? 60))), 'move_hours' => max(0, min(72, (int)($_POST['move_hours'] ?? 0)))]));
        $rules = [];
        foreach ((array)($_POST['rule'] ?? []) as $r) { if (!is_array($r)) continue; $v = mb_substr(trim((string)($r['value'] ?? '')), 0, 60); $o = (int)($r['owner'] ?? 0); $f = (string)($r['field'] ?? '');
            if ($v !== '' && $o && in_array($f, ['source', 'country', 'phone', 'keyword'], true) && in_array($o, $memberIds, true)) $rules[] = ['field' => $f, 'value' => $v, 'owner' => $o]; }
        $rules = array_values(array_unique(array_map('json_encode', $rules))); $rules = array_map(function ($x) { return json_decode($x, true); }, $rules);
        save_setting('crm_routes_' . $wid, json_encode(array_slice($rules, 0, 20), JSON_UNESCAPED_UNICODE));
        $pr = []; foreach (['new', 'contacted', 'qualified', 'proposal'] as $k) $pr[$k] = max(0, min(100, (int)($_POST['prob'][$k] ?? crm_stage_probs()[$k])));
        save_setting('crm_probs_' . $wid, json_encode($pr));
        $json(['ok' => true]);
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
            $l = crm_lead_upsert(['respect_owner' => 1, 'source' => 'import', 'name' => $nm !== '' ? $nm : ($em ?: $ph), 'company' => $r['company'] ?? '', 'title' => $r['title'] ?? '',
                'email' => $em, 'phone' => $ph, 'country' => $r['country'] ?? '', 'owner_id' => $canManage ? null : $uid]);
            if (!$l) { $skip++; continue; }
            $tg = trim((string)($r['tags'] ?? '')); $nt = trim((string)($r['note'] ?? ''));
            if (!empty($l['merged'])) { $merged++; // عميل موجود: التاجات بتتضاف والملاحظة بتتلحق (لو ليك صلاحية عليه)
                if (($tg !== '' || $nt !== '') && crm_can_edit($l)) db()->prepare("UPDATE crm_leads SET tags = LEFT(TRIM(BOTH ', ' FROM CONCAT_WS(', ', NULLIF(tags, ''), NULLIF(?, ''))), 255), note = LEFT(TRIM(CONCAT_WS('\n', NULLIF(note, ''), NULLIF(?, ''))), 2000) WHERE id = ?")->execute([$tg, $nt, (int)$l['id']]);
                continue; }
            $new++;
            if ($tg !== '' || $nt !== '') db()->prepare("UPDATE crm_leads SET tags = LEFT(TRIM(BOTH ', ' FROM CONCAT_WS(', ', NULLIF(tags, ''), NULLIF(?, ''))), 255), note = COALESCE(?, note) WHERE id = ?")->execute([mb_substr($tg, 0, 255), mb_substr($nt, 0, 2000) ?: null, (int)$l['id']]);
        }
        log_activity('crm.import', "$new new, $merged merged");
        $json(['ok' => true, 'new' => $new, 'merged' => $merged, 'skip' => $skip + max(0, count($rows) - 2000)]); // اللي بعد 2000 بيتعدّوا ضمن المتخطّى
    }
    if ($action === 'track_save' && $canManage) {
        save_setting('crm_track_cfg_' . $wid, json_encode(['hot' => mb_substr(trim((string)($_POST['hot'] ?? '')), 0, 300), 'wa' => preg_replace('/[^\d+]/', '', (string)($_POST['wa'] ?? '')),
            'btn' => !empty($_POST['btn']) ? 1 : 0, 'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '#4f46e5', 'label' => mb_substr(trim((string)($_POST['label'] ?? '')), 0, 40)], JSON_UNESCAPED_UNICODE));
        $site = trim((string)($_POST['site'] ?? ''));
        save_setting('crm_site_url_' . $wid, preg_match('~^https?://~i', $site) ? mb_substr($site, 0, 300) : '');
        $json(['ok' => true]);
    }
    if ($action === 'book_save' && $canManage) {
        $days = array_values(array_intersect(array_map('intval', (array)($_POST['days'] ?? [])), range(0, 6)));
        $tm = function ($v, $d) { return preg_match('/^\d{2}:\d{2}$/', (string)$v) ? $v : $d; };
        $c = ['title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120), 'days' => $days, 'from' => $tm($_POST['from'] ?? '', '10:00'), 'to' => $tm($_POST['to'] ?? '', '17:00'),
            'len' => max(15, min(240, (int)($_POST['len'] ?? 30))), 'ahead' => max(1, min(60, (int)($_POST['ahead'] ?? 14))), 'notice' => max(0, min(72, (int)($_POST['notice'] ?? 3))), 'color' => crm_book_cfg()['color']];
        // مواعيد مستحيلة كانت بتقفل الحجز من غير ما حد يعرف
        if (!$c['days']) $json(['ok' => false, 'error' => t('اختر يومًا واحدًا على الأقل للحجز.')]);
        if (strtotime('2000-01-01 ' . $c['from'] . ' +' . $c['len'] . ' minutes') > strtotime('2000-01-01 ' . $c['to'])) $json(['ok' => false, 'error' => t('وقت البداية لازم يكون قبل النهاية بمدة موعد واحد على الأقل.')]);
        save_setting('crm_book_cfg_' . $wid, json_encode($c, JSON_UNESCAPED_UNICODE));
        save_setting('crm_book_on_' . $wid, !empty($_POST['on']) ? '1' : '0');
        $json(['ok' => true, 'url' => crm_book_url()]);
    }
    if ($action === 'form_save' && $canManage) {
        $c = (json_decode((string)setting('crm_form_cfg_' . $wid, ''), true) ?: []); // نحافظ على أسئلة التأهيل والميزانيات (نفس الإعداد)
        $c = ['title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120), 'thanks' => mb_substr(trim((string)($_POST['thanks'] ?? '')), 0, 300),
            'company' => !empty($_POST['company']) ? 1 : 0, 'message' => !empty($_POST['message']) ? 1 : 0,
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '#4f46e5'] + $c;
        save_setting('crm_form_cfg_' . $wid, json_encode($c, JSON_UNESCAPED_UNICODE));
        save_setting('crm_form_on_' . $wid, !empty($_POST['on']) ? '1' : '0');
        $json(['ok' => true, 'url' => crm_form_url(!empty($_POST['regen']))]);
    }
    if ($action === 'tpl_save' && $canManage) {
        $t = json_decode((string)($_POST['tpl'] ?? ''), true);
        $clean = [];
        foreach ((array)$t as $x) { $n = mb_substr(trim((string)($x['n'] ?? '')), 0, 40); $b = mb_substr(trim((string)($x['b'] ?? '')), 0, 1000); if ($n !== '' && $b !== '') $clean[] = ['n' => $n, 'b' => $b]; }
        save_setting('crm_tpl_' . $wid, json_encode(array_slice($clean, 0, 10), JSON_UNESCAPED_UNICODE));
        $json(['ok' => true, 'tpl' => crm_templates()]);
    }
    // #83 المهام
    if (in_array($action, ['task_add', 'task_done', 'task_del'], true)) {
        if ($action === 'task_add') {
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255); if ($title === '') $json(['ok' => false, 'error' => t('اكتب المهمة.')]);
            $due = trim((string)($_POST['due'] ?? '')); try { $due = $due !== '' ? (new DateTime($due, new DateTimeZone(site_timezone())))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s') : null; } catch (Exception $e) { $due = null; }
            $to = (int)($_POST['user'] ?? 0); if (!$canManage || !in_array($to, $memberIds, true)) $to = $uid;
            $lid = (int)($_POST['lead'] ?? 0); $ll = $lid ? crm_lead($lid) : null; if ($ll && !crm_can_edit($ll)) $ll = null; // عميل زميل: المهمة تتعمل من غير ربط
            $lid = $ll ? (int)$ll['id'] : 0;
            db()->prepare('INSERT INTO crm_tasks (workspace_id, user_id, lead_id, title, due_at, created_by) VALUES (?, ?, ?, ?, ?, ?)')->execute([$wid, $to, $ll ? (int)$ll['id'] : null, $title, $due, $uid]);
            if ($to !== $uid) crm_notify($to, $ll ? (int)$ll['id'] : null, 'task', t('%s ضاف لك مهمة: %s', (string)(current_user()['name'] ?? ''), $title));
        } else {
            $tid = (int)($_POST['task'] ?? 0); $st = db()->prepare('SELECT lead_id, due_at, done_at, user_id, created_by FROM crm_tasks WHERE id = ? AND workspace_id = ?'); $st->execute([$tid, $wid]); $tk = $st->fetch() ?: []; if (!$tk || (!$canManage && (int)$tk['user_id'] !== $uid && !($action === 'task_done' ? $tk['user_id'] === null : (int)$tk['created_by'] === $uid))) $json(['ok' => false, 'error' => t('مالكش صلاحية.')]); $lid = (int)($tk['lead_id'] ?? 0); $doneDue = empty($tk['done_at']) ? ($tk['due_at'] ?? null) : null;
            if ($action === 'task_done') db()->prepare('UPDATE crm_tasks SET done_at = IF(done_at IS NULL, NOW(), NULL) WHERE id = ? AND workspace_id = ? AND (user_id = ? OR user_id IS NULL OR ? = 1)')->execute([$tid, $wid, $uid, $canManage ? 1 : 0]);
            else db()->prepare('DELETE FROM crm_tasks WHERE id = ? AND workspace_id = ? AND (user_id = ? OR created_by = ? OR ? = 1)')->execute([$tid, $wid, $uid, $uid, $canManage ? 1 : 0]);
        }
        // المهام والمتابعة حاجة واحدة: المتابعة الجاية = أقرب مهمة مفتوحة ليها ميعاد
        if (!empty($lid)) crm_sync_next_from_tasks((int)$lid, $doneDue ?? null);
        $json(['ok' => true, 'tasks' => crm_tasks_open($uid)]);
    }
    if ($action === 'merge_pair' || $action === 'merge') {
        $a = crm_lead((int)($_POST['a'] ?? 0)); $b = crm_lead((int)($_POST['b'] ?? 0));
        if (!$a || !$b || !crm_can_edit($a) || !crm_can_edit($b)) $json(['ok' => false, 'error' => t('مش مسموحلك تعدّل العميل ده.')]);
        if ($action === 'merge_pair') $json(['ok' => true, 'a' => $out($a), 'b' => $out($b), 'acts' => [count(crm_acts((int)$a['id'])), count(crm_acts((int)$b['id']))]]);
        $keep = (int)($_POST['keep'] ?? 0) === (int)$b['id'] ? $b : $a; $drop = $keep === $b ? $a : $b;
        crm_merge_leads((int)$keep['id'], (int)$drop['id'], array_map('strval', (array)($_POST['pick'] ?? [])));
        log_activity('crm.merge', $keep['id'] . ' <- ' . $drop['id']);
        $json(['ok' => true, 'lead' => $out(crm_lead((int)$keep['id'])), 'gone' => (int)$drop['id']]);
    }
    $l = crm_lead((int)($_POST['id'] ?? 0));
    if ($l && $action === 'acts' && crm_can_edit($l)) { $ac = crm_acts((int)$l['id']); $mails = crm_mails((int)$l['id']); $json(['ok' => true, 'acts' => $ac, 'run' => null, 'intel' => crm_intel($l, $ac), 'mails' => $mails, 'deals' => crm_deals((int)$l['id']), 'visits' => crm_visits((int)$l['id'])]); }
    if (!$l || !crm_can_edit($l)) $json(['ok' => false, 'error' => t('مش مسموحلك تعدّل العميل ده.')]);
    if ($action === 'stage') {
        $s = (string)($_POST['stage'] ?? '');
        if (!isset($stages[$s])) $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
        db()->prepare('UPDATE crm_leads SET stage = ? WHERE id = ?')->execute([$s, (int)$l['id']]);
        if ($s !== $l['stage']) crm_act_add((int)$l['id'], 'stage', $stages[$l['stage']] . (is_rtl() ? ' ← ' : ' → ') . $stages[$s]);
        if ($s === 'lost') crm_set_lost_reason((int)$l['id'], (string)($_POST['lost_reason'] ?? ''));
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
        // الموظف بيكتب الميعاد بتوقيت الشركة؛ بنخزنه بتوقيت السيرفر زي باقي التواريخ
        try { $next = $next !== '' ? (new DateTime($next, new DateTimeZone(site_timezone())))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s') : null; } catch (Exception $e) { $next = null; }
        $tags = implode(', ', array_slice(array_unique(array_filter(array_map(function ($x) { return mb_substr(trim($x), 0, 30); }, explode(',', (string)($_POST['tags'] ?? ''))))), 0, 8));
        $phone = crm_norm_phone((string)($_POST['phone'] ?? ''), (string)$l['country']);
        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $json(['ok' => false, 'error' => t('الإيميل مش صحيح')]);
        db()->prepare('UPDATE crm_leads SET name = ?, company = ?, title = ?, email = ?, phone = ?, phone_pending = IF(? IS NULL, phone_pending, 0), stage = ?, deal_value = ?, note = ?, tags = ?, owner_id = ?, next_at = ?, next_note = ? WHERE id = ?')
            ->execute([mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255) ?: $l['name'], mb_substr(trim((string)($_POST['company'] ?? '')), 0, 255) ?: null,
                mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255) ?: null, $email ?: null, $phone ?: null, $phone ?: null, $s, $val,
                mb_substr(trim((string)($_POST['note'] ?? '')), 0, 2000) ?: null, $tags ?: null, $owner, $next,
                mb_substr(trim((string)($_POST['next_note'] ?? '')), 0, 255) ?: null, (int)$l['id']]);
        if ($owner !== ((int)$l['owner_id'] ?: null) && $owner) {
            $hn = mb_substr(trim((string)($_POST['handoff'] ?? '')), 0, 500); $toName = (string)(array_column($members, 'name', 'id')[$owner] ?? '');
            crm_act_add((int)$l['id'], 'note', '🤝 ' . t('اتسلّم لـ %s', $toName) . ($hn !== '' ? ': ' . $hn : ''));
            if ($owner !== $uid) crm_notify($owner, (int)$l['id'], 'handoff', t('%s سلّمك العميل %s', (string)(current_user()['name'] ?? ''), (string)$l['name']) . ($hn !== '' ? ' — ' . $hn : ''));
        }
        if ($s !== $l['stage']) crm_act_add((int)$l['id'], 'stage', ($stages[$l['stage']] ?? $l['stage']) . (is_rtl() ? ' ← ' : ' → ') . $stages[$s]);
        if ($s === 'lost' && $l['stage'] !== 'lost') crm_set_lost_reason((int)$l['id'], (string)($_POST['lost_reason'] ?? ''));
        $pl = (string)($_POST['pipeline'] ?? ''); $oldCf = json_decode((string)($l['custom'] ?? ''), true) ?: []; $cf = crm_custom_clean((array)($_POST['cf'] ?? []) + array_diff_key($oldCf, array_flip(array_column(crm_custom_fields(), 'key')))); // بيانات حقول اتمسحت تفضل
        db()->prepare('UPDATE crm_leads SET pipeline = ?, custom = ? WHERE id = ?')->execute([isset(crm_pipelines()[$pl]) ? $pl : (string)($l['pipeline'] ?? 'sales'), $cf ? json_encode($cf, JSON_UNESCAPED_UNICODE) : null, (int)$l['id']]);
        crm_auto_tag(crm_lead((int)$l['id']));
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
    if ($action === 'deal_save' || $action === 'deal_del') {
        $did = (int)($_POST['deal'] ?? 0);
        if ($action === 'deal_del') { db()->prepare('DELETE FROM crm_deals WHERE id = ? AND workspace_id = ? AND lead_id = ?')->execute([$did, $wid, (int)$l['id']]); $dn = db()->prepare('SELECT COUNT(*) FROM crm_deals WHERE workspace_id = ? AND lead_id = ?'); $dn->execute([$wid, (int)$l['id']]); if (!(int)$dn->fetchColumn()) db()->prepare('UPDATE crm_leads SET deal_value = NULL WHERE id = ? AND workspace_id = ?')->execute([(int)$l['id'], $wid]); } // آخر صفقة اتمسحت
        else {
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190); if ($title === '') $json(['ok' => false, 'error' => t('اكتب اسم الصفقة.')]);
            $val = max(0, (float)str_replace(',', '.', (string)($_POST['value'] ?? '0')));
            $stt = isset(crm_deal_statuses()[$_POST['status'] ?? '']) ? $_POST['status'] : 'open';
            $close = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['close_at'] ?? '')) ? $_POST['close_at'] : null;
            if ($did) db()->prepare("UPDATE crm_deals SET title = ?, value = ?, close_at = ?, closed_at = IF(? = 'open', NULL, IF(status = ?, closed_at, NOW())), status = ? WHERE id = ? AND workspace_id = ? AND lead_id = ?")->execute([$title, $val, $close, $stt, $stt, $stt, $did, $wid, (int)$l['id']]);
            else db()->prepare('INSERT INTO crm_deals (workspace_id, lead_id, owner_id, title, value, status, close_at, closed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$wid, (int)$l['id'], (int)$l['owner_id'] ?: ($uid ?: null), $title, $val, $stt, $close, $stt === 'open' ? null : date('Y-m-d H:i:s')]);
            crm_act_add((int)$l['id'], 'note', '💼 ' . $title . ' · ' . number_format($val) . ' · ' . crm_deal_statuses()[$stt]);
            // أول صفقة تتقفل بنجاح بتنقل العميل لـ "بقى عميل"
            if ($stt === 'won' && $l['stage'] !== 'won') { db()->prepare("UPDATE crm_leads SET stage = 'won' WHERE id = ?")->execute([(int)$l['id']]); $pushWon = true; crm_act_add((int)$l['id'], 'stage', $stages[$l['stage']] . (is_rtl() ? ' ← ' : ' → ') . $stages['won']); }
        }
        crm_deals_rollup((int)$l['id']);
        if ((!empty($pushWon) || $l['stage'] === 'won') && function_exists('crm_push_ad_stage') && ($fresh = crm_lead((int)$l['id']))) { try { crm_push_ad_stage($fresh); } catch (Throwable $e) {} } // منصة الإعلانات تعرف إنه اشترى، بالقيمة الجديدة
        $json(['ok' => true, 'deals' => crm_deals((int)$l['id']), 'lead' => $out(crm_lead((int)$l['id']))]);
    }
    if ($action === 'track_link') {
        $u = trim((string)($_POST['url'] ?? '')) ?: (string)setting('crm_site_url_' . $wid, '');
        if (!preg_match('~^https?://~i', $u)) $json(['ok' => false, 'error' => t('اكتب رابط يبدأ بـ https://')]);
        $json(['ok' => true, 'link' => crm_track_link($l, $u)]);
    }
    if ($action === 'send_email') {
        try { crm_send_email($l, crm_fill_vars((string)($_POST['subject'] ?? ''), $l), crm_fill_vars(trim((string)($_POST['body'] ?? '')), $l)); }
        catch (Throwable $ex) { $json(['ok' => false, 'error' => $ex->getMessage()]); }
        log_activity('crm.email', (string)$l['id']);
        $json(['ok' => true, 'lead' => $out(crm_lead((int)$l['id'])), 'acts' => crm_acts((int)$l['id']), 'mails' => crm_mails((int)$l['id'])]);
    }
    if ($action === 'next_quick') {
        // "⏰ بعدين": المتابعة بكرة الساعة 11 بتوقيت الشركة
        $w = (string)($_POST['when'] ?? ''); $tz = new DateTimeZone(site_timezone());
        if ($w === 'none') db()->prepare('UPDATE crm_leads SET next_at = NULL WHERE id = ?')->execute([(int)$l['id']]);
        else {
            if ($w === 'today') { $d = new DateTime('today 16:00', $tz); if ($d < new DateTime('now', $tz)) $d = (new DateTime('now', $tz))->modify('+1 hour'); }
            else { $days = $w === '3d' ? 3 : ($w === 'week' ? 7 : max(1, min(30, (int)($_POST['days'] ?? 1)))); $d = (new DateTime('today 11:00', $tz))->modify("+$days days"); }
            db()->prepare('UPDATE crm_leads SET next_at = ? WHERE id = ?')->execute([$d->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'), (int)$l['id']]);
        }
        $json(['ok' => true, 'lead' => $out(crm_lead((int)$l['id']))]);
    }
    // #76 ملاحظة صوتية
    if ($action === 'voice') {
        $f = $_FILES['audio'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || $f['size'] > 6 * 1024 * 1024) $json(['ok' => false, 'error' => t('التسجيل كبير أو مااترفعش (الحد 6 ميجا).')]);
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'video/mp4' => 'm4a', 'audio/mpeg' => 'mp3', 'audio/x-m4a' => 'm4a'][$mime] ?? '';
        if ($ext === '') $json(['ok' => false, 'error' => t('نوع الملف مش صوت.')]);
        $dir = media_dir($wid) . '/voice'; if (!is_dir($dir)) @mkdir($dir, 0750, true);
        $name = (int)$l['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) $json(['ok' => false, 'error' => t('حصلت مشكلة في الحفظ.')]);
        $sec = max(0, min(3600, (int)($_POST['sec'] ?? 0)));
        crm_act_add((int)$l['id'], 'note', '🎙 voice:' . $name . ' ' . gmdate($sec >= 3600 ? 'H:i:s' : 'i:s', $sec) . (trim((string)($_POST['body'] ?? '')) !== '' ? "\n" . mb_substr(trim((string)$_POST['body']), 0, 1500) : ''));
        $json(['ok' => true, 'acts' => crm_acts((int)$l['id'])]);
    }
    if ($action === 'pin') { db()->prepare('UPDATE crm_leads SET pinned = 1 - pinned WHERE id = ? AND workspace_id = ?')->execute([(int)$l['id'], $wid]); $json(['ok' => true, 'lead' => $out(crm_lead((int)$l['id']))]); }
    if ($action === 'log') {
        // تسجيل تواصل؛ أول تواصل مع عميل جديد بينقله لـ "اتكلمنا معاه" لوحده
        $k = (string)($_POST['kind'] ?? '');
        if (!isset(crm_act_kinds()[$k]) || $k === 'stage') $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
        $oc = (string)($_POST['outcome'] ?? ''); $ocs = crm_call_outcomes();
        $body = trim((isset($ocs[$oc]) ? '[' . $ocs[$oc] . '] ' : '') . (string)($_POST['body'] ?? ''));
        crm_act_add((int)$l['id'], $k, $body);
        if ($k === 'note' && function_exists('crm_mentions')) crm_mentions($body, (int)$l['id']);
        if ($k === 'call' && in_array($oc, ['no_answer', 'busy'], true)) {
            // محاولة تانية بكرة في نفس الوقت تقريبًا
            $ra = date('Y-m-d H:i:s', strtotime('+1 day')); db()->prepare('UPDATE crm_leads SET next_note = IF(next_at IS NULL OR next_at > ?, ?, next_note), next_at = IF(next_at IS NULL OR next_at > ?, ?, next_at) WHERE id = ?')->execute([$ra, t('محاولة اتصال تانية (%s)', $ocs[$oc]), $ra, $ra, (int)$l['id']]); // متابعة أقرب (زي اجتماع) تفضل
        }
        if ($k === 'call' && $oc === 'wrong') db()->prepare('UPDATE crm_leads SET phone = NULL WHERE id = ?')->execute([(int)$l['id']]);
        if ($k === 'call' && function_exists('crm_on_call_outcome')) crm_on_call_outcome($l, $oc);
        if ($k !== 'note' && $l['stage'] === 'new' && !in_array($oc, ['no_answer', 'busy', 'wrong'], true)) {
            db()->prepare("UPDATE crm_leads SET stage = 'contacted' WHERE id = ?")->execute([(int)$l['id']]);
            crm_act_add((int)$l['id'], 'stage', $stages['new'] . (is_rtl() ? ' ← ' : ' → ') . $stages['contacted']);
            crm_push_ad_stage(crm_lead((int)$l['id']));
        }
        db()->prepare('UPDATE crm_leads SET last_contact_at = NOW() WHERE id = ?')->execute([(int)$l['id']]);
        $json(['ok' => true, 'lead' => $out(crm_lead((int)$l['id'])), 'acts' => crm_acts((int)$l['id'])]);
    }
    if ($action === 'delete' && $canManage) {
        db()->prepare('DELETE FROM crm_acts WHERE lead_id = ? AND workspace_id = ?')->execute([(int)$l['id'], $wid]);
        try { db()->prepare('DELETE FROM crm_bookings WHERE lead_id = ? AND workspace_id = ? AND start_at > NOW()')->execute([(int)$l['id'], $wid]); } catch (Throwable $e) {}
        foreach (['crm_deals', 'crm_tasks', 'crm_visits', 'crm_notifs', 'crm_mails'] as $tb) { try { db()->prepare("DELETE FROM $tb WHERE lead_id = ? AND workspace_id = ?")->execute([(int)$l['id'], $wid]); } catch (Throwable $e) {} } // مايفضلش يتحسب في التقارير
        db()->prepare('DELETE FROM crm_leads WHERE id = ? AND workspace_id = ?')->execute([(int)$l['id'], $wid]);
        log_activity('crm.delete', $l['id'] . ' ' . $l['name']);
        $json(['ok' => true]);
    }
    $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
}

// تشغيل الملاحظة الصوتية (للي بيشوف العميل بس)
if (preg_match('/^(\d+)_[0-9a-f]{16}\.(webm|ogg|m4a|mp3)$/', (string)($_GET['voice'] ?? ''), $vm)) {
    $vl = crm_lead((int)$vm[1]); $vf = media_dir($wid) . '/voice/' . $_GET['voice'];
    if (!$vl || (!$canManage && (int)$vl['owner_id'] && (int)$vl['owner_id'] !== $uid) || !is_file($vf)) { http_response_code(404); exit; }
    header('Content-Type: ' . ['webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'mp3' => 'audio/mpeg'][$vm[2]]); header('Content-Length: ' . filesize($vf)); header('Cache-Control: private, max-age=86400');
    readfile($vf); exit;
}
try { crm_fix_stored_phones(); } catch (Throwable $t) { /* مش مهم */ }

try { crm_sla_tick(); } catch (Throwable $t) { /* الـ cron هيكمّل */ }
try { crm_meeting_reminders(); } catch (Throwable $t) { /* الـ cron هيكمّل */ }
// الموظف العادي بيشوف بتوعه واللي من غير مسؤول، والمدير بيشوف الكل
$where = 'workspace_id = ?';
$args = [$wid];
if (!$canManage) { $where .= ' AND (owner_id = ? OR owner_id IS NULL)'; $args[] = $uid; }
// الصفحة اتدمجت في "العملاء المستهدفين": أي رابط قديم (#lead-ID أو ?lead= أو طابور الاتصال) بيفتح نفس العميل هناك
if (($_GET['export'] ?? '') !== 'csv') {
    $to = url('prospects'); header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><script>var u=' . json_encode($to) . ',m=location.hash.match(/^#lead-(\\d+)/),q=new URLSearchParams(location.search);'
        . 'if(m)u+="&lead="+m[1];else if(q.get("lead"))u+="&lead="+encodeURIComponent(q.get("lead"));if(q.get("go")==="queue")u+="&queue=1";location.replace(u);</script><noscript><a href="' . e($to) . '">' . e(t('العملاء المستهدفين')) . '</a></noscript>';
    exit;
}
$pipes = crm_pipelines(); $curPl = isset($pipes[$_GET['pl'] ?? '']) ? (string)$_GET['pl'] : 'sales';
if (count($pipes) > 1) { $where .= ' AND pipeline = ?'; $args[] = $curPl; }
$st = db()->prepare("SELECT * FROM crm_leads WHERE $where ORDER BY pinned DESC, (next_at IS NULL), next_at, id DESC LIMIT " . (($_GET['export'] ?? '') === 'csv' ? 200000 : 2000)); // التصدير كامل (من غير حد 2000 الصامت)
$st->execute($args);
$rows = $st->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, ['name', 'title', 'company', 'email', 'phone', 'country', 'stage', 'source', 'value', 'owner', 'next_follow_up', 'tags', 'note', 'created']);
    $names = array_column($members, 'name', 'id');
    $cell = function ($v) { $v = (string)$v; return $v !== '' && strpos("=+-@\t\r", $v[0]) !== false && !is_numeric($v) ? "'" . $v : $v; };
    foreach ($rows as $r) fputcsv($f, array_map($cell, [$r['name'], $r['title'], $r['company'], $r['email'], $r['phone'], $r['country'], $stages[$r['stage']] ?? $r['stage'], $sources[$r['source']] ?? $r['source'], $r['deal_value'], $names[$r['owner_id']] ?? '', $r['next_at'], $r['tags'], $r['note'], $r['created_at']]));
    exit;
}

// واجهة الصفحة القديمة اتشالت: الصفحة دي بقت API لنافذة العميل + تصدير CSV بس، والعرض في صفحة العملاء (prospects)
