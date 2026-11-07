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
        'country' => (string)$l['country'], 'stage' => $l['stage'], 'source' => $l['source'], 'value' => $l['deal_value'] === null ? '' : rtrim(rtrim((string)$l['deal_value'], '0'), '.'),
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
                'text' => $it['text'] ?? '', 'url' => $it['url'] ?? '', 'where' => $it['where'] ?? '', 'owner_id' => $canManage ? null : $uid]);
            if (!$l) continue;
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
            'email' => $_POST['email'] ?? '', 'phone' => $_POST['phone'] ?? '', 'owner_id' => $canManage ? null : $uid]);
        if (empty($l['merged']) && isset(crm_pipelines()[$_POST['pl'] ?? '']) && $_POST['pl'] !== 'sales') { db()->prepare('UPDATE crm_leads SET pipeline = ? WHERE id = ?')->execute([(string)$_POST['pl'], (int)$l['id']]); $l = crm_lead((int)$l['id']) + ['merged' => false]; }
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
                } elseif ($op === 'plan') {
                    if (!crm_plan_start($id, (int)($_POST['plan'] ?? 0))) { $skip++; continue; }
                } elseif ($op === 'tag') {
                    crm_add_tag($id, (string)($_POST['tag'] ?? ''));
                } elseif ($op === 'enrich') {
                    if ($i >= 25) break;
                    if ((int)$prefs['daily_limit'] - crm_reveals_today($uid) <= 0) { $err = t('خلصت حد الكشف بتاعك النهارده (%s). بيتجدد بكرة.', (int)$prefs['daily_limit']); break; }
                    crm_enrich_lead($l, $prefs);
                } elseif ($op === 'seq') {
                    if (empty($l['email'])) { $skip++; continue; }
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
    if ($action === 'plans_save' && $canManage) {
        $in = json_decode((string)($_POST['plans'] ?? ''), true);
        $keep = [];
        foreach (array_slice((array)$in, 0, 20) as $pl) {
            $name = mb_substr(trim((string)($pl['name'] ?? '')), 0, 120); if ($name === '') continue;
            $steps = [];
            foreach (array_slice((array)($pl['steps'] ?? []), 0, 12) as $s) {
                $ch = isset(crm_plan_channels()[$s['ch'] ?? '']) ? $s['ch'] : 'task'; $tx = mb_substr(trim((string)($s['text'] ?? '')), 0, 2000); if ($tx === '') continue;
                $steps[] = ['day' => max(0, min(365, (int)($s['day'] ?? 0))), 'ch' => $ch, 'text' => $tx, 'subject' => mb_substr(trim((string)($s['subject'] ?? '')), 0, 200)];
            }
            if (!$steps) continue;
            usort($steps, function ($a, $b) { return $a['day'] <=> $b['day']; });
            $id = (int)($pl['id'] ?? 0);
            if ($id && crm_plan($id)) { db()->prepare('UPDATE crm_plans SET name = ?, steps = ? WHERE id = ? AND workspace_id = ?')->execute([$name, json_encode($steps, JSON_UNESCAPED_UNICODE), $id, $wid]); }
            else { db()->prepare('INSERT INTO crm_plans (workspace_id, name, steps) VALUES (?, ?, ?)')->execute([$wid, $name, json_encode($steps, JSON_UNESCAPED_UNICODE)]); $id = (int)db()->lastInsertId(); }
            $keep[] = $id;
        }
        // الخطط اللي اتشالت: بتتمسح وتقف على العملاء
        $old = array_column(crm_plans(), 'id');
        foreach (array_diff($old, $keep) as $gone) { db()->prepare("UPDATE crm_plan_runs SET state = 'stopped' WHERE plan_id = ? AND workspace_id = ? AND state IN ('wait','due')")->execute([$gone, $wid]); db()->prepare('DELETE FROM crm_plans WHERE id = ? AND workspace_id = ?')->execute([$gone, $wid]); }
        $json(['ok' => true, 'plans' => crm_plans()]);
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
        save_setting('crm_book_cfg_' . $wid, json_encode($c, JSON_UNESCAPED_UNICODE));
        save_setting('crm_book_on_' . $wid, !empty($_POST['on']) ? '1' : '0');
        $json(['ok' => true, 'url' => crm_book_url()]);
    }
    if ($action === 'form_save' && $canManage) {
        $c = ['title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120), 'thanks' => mb_substr(trim((string)($_POST['thanks'] ?? '')), 0, 300),
            'company' => !empty($_POST['company']) ? 1 : 0, 'message' => !empty($_POST['message']) ? 1 : 0,
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '#4f46e5'];
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
            $lid = (int)($_POST['lead'] ?? 0); $ll = $lid ? crm_lead($lid) : null;
            db()->prepare('INSERT INTO crm_tasks (workspace_id, user_id, lead_id, title, due_at, created_by) VALUES (?, ?, ?, ?, ?, ?)')->execute([$wid, $to, $ll ? (int)$ll['id'] : null, $title, $due, $uid]);
            if ($to !== $uid) crm_notify($to, $ll ? (int)$ll['id'] : null, 'task', t('%s ضاف لك مهمة: %s', (string)(current_user()['name'] ?? ''), $title));
        } else {
            $tid = (int)($_POST['task'] ?? 0);
            if ($action === 'task_done') db()->prepare('UPDATE crm_tasks SET done_at = IF(done_at IS NULL, NOW(), NULL) WHERE id = ? AND workspace_id = ? AND (user_id = ? OR user_id IS NULL OR ? = 1)')->execute([$tid, $wid, $uid, $canManage ? 1 : 0]);
            else db()->prepare('DELETE FROM crm_tasks WHERE id = ? AND workspace_id = ? AND (user_id = ? OR created_by = ? OR ? = 1)')->execute([$tid, $wid, $uid, $uid, $canManage ? 1 : 0]);
        }
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
    if ($l && $action === 'acts') { $ac = crm_acts((int)$l['id']); $mails = crm_mails((int)$l['id']); $json(['ok' => true, 'acts' => $ac, 'run' => crm_lead_run((int)$l['id']), 'intel' => crm_intel($l, $ac), 'mails' => $mails, 'deals' => crm_deals((int)$l['id']), 'visits' => crm_visits((int)$l['id'])]); }
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
        $pl = (string)($_POST['pipeline'] ?? ''); $cf = crm_custom_clean((array)($_POST['cf'] ?? []));
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
    if (in_array($action, ['plan_start', 'plan_do', 'plan_skip', 'plan_stop'], true)) {
        if ($action === 'plan_start' && !crm_plan_start((int)$l['id'], (int)($_POST['plan'] ?? 0))) $json(['ok' => false, 'error' => t('اختار خطة فيها خطوات.')]);
        if ($action === 'plan_do' || $action === 'plan_skip') { $r = crm_lead_run((int)$l['id']); if ($r) crm_plan_do($r['id'], $action === 'plan_skip'); }
        if ($action === 'plan_stop') crm_plan_stop((int)$l['id']);
        $json(['ok' => true, 'lead' => $out(crm_lead((int)$l['id'])), 'acts' => crm_acts((int)$l['id']), 'run' => crm_lead_run((int)$l['id'])]);
    }
    if ($action === 'deal_save' || $action === 'deal_del') {
        $did = (int)($_POST['deal'] ?? 0);
        if ($action === 'deal_del') db()->prepare('DELETE FROM crm_deals WHERE id = ? AND workspace_id = ? AND lead_id = ?')->execute([$did, $wid, (int)$l['id']]);
        else {
            $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 190); if ($title === '') $json(['ok' => false, 'error' => t('اكتب اسم الصفقة.')]);
            $val = max(0, (float)str_replace(',', '.', (string)($_POST['value'] ?? '0')));
            $stt = isset(crm_deal_statuses()[$_POST['status'] ?? '']) ? $_POST['status'] : 'open';
            $close = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['close_at'] ?? '')) ? $_POST['close_at'] : null;
            if ($did) db()->prepare("UPDATE crm_deals SET title = ?, value = ?, close_at = ?, closed_at = IF(? = 'open', NULL, IF(status = ?, closed_at, NOW())), status = ? WHERE id = ? AND workspace_id = ? AND lead_id = ?")->execute([$title, $val, $close, $stt, $stt, $stt, $did, $wid, (int)$l['id']]);
            else db()->prepare('INSERT INTO crm_deals (workspace_id, lead_id, owner_id, title, value, status, close_at, closed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$wid, (int)$l['id'], (int)$l['owner_id'] ?: ($uid ?: null), $title, $val, $stt, $close, $stt === 'open' ? null : date('Y-m-d H:i:s')]);
            crm_act_add((int)$l['id'], 'note', '💼 ' . $title . ' · ' . number_format($val) . ' · ' . crm_deal_statuses()[$stt]);
            // أول صفقة تتقفل بنجاح بتنقل العميل لـ "بقى عميل"
            if ($stt === 'won' && $l['stage'] !== 'won') { db()->prepare("UPDATE crm_leads SET stage = 'won' WHERE id = ?")->execute([(int)$l['id']]); crm_act_add((int)$l['id'], 'stage', $stages[$l['stage']] . (is_rtl() ? ' ← ' : ' → ') . $stages['won']); }
        }
        crm_deals_rollup((int)$l['id']);
        $json(['ok' => true, 'deals' => crm_deals((int)$l['id']), 'lead' => $out(crm_lead((int)$l['id']))]);
    }
    if ($action === 'track_link') {
        $u = trim((string)($_POST['url'] ?? '')) ?: (string)setting('crm_site_url_' . $wid, '');
        if (!preg_match('~^https?://~i', $u)) $json(['ok' => false, 'error' => t('اكتب رابط يبدأ بـ https://')]);
        $json(['ok' => true, 'link' => crm_track_link($l, $u)]);
    }
    if ($action === 'send_email') {
        try { crm_send_email($l, crm_plan_text((string)($_POST['subject'] ?? ''), $l), crm_plan_text(trim((string)($_POST['body'] ?? '')), $l)); }
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
            db()->prepare('UPDATE crm_leads SET next_at = ?, next_note = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', strtotime('+1 day')), t('محاولة اتصال تانية (%s)', $ocs[$oc]), (int)$l['id']]);
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
try { crm_plan_tick(30); } catch (Throwable $t) { /* الـ cron هيكمّل */ }
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
$st = db()->prepare("SELECT * FROM crm_leads WHERE $where ORDER BY pinned DESC, (next_at IS NULL), next_at, id DESC LIMIT 2000");
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
$runs = crm_runs_map();
$actN = [];
$st = db()->prepare("SELECT lead_id, COUNT(*) FROM crm_acts WHERE workspace_id = ? AND kind IN ('call','wa','email','meet') GROUP BY lead_id"); $st->execute([$wid]);
foreach ($st->fetchAll(PDO::FETCH_NUM) as $r) $actN[(int)$r[0]] = (int)$r[1];
$rowById = []; foreach ($rows as $r) $rowById[(int)$r['id']] = $r;
foreach ($leads as &$ld1) { [$ld1['score'], $ld1['why']] = crm_score($rowById[$ld1['id']], $actN[$ld1['id']] ?? 0); } unset($ld1);
foreach ($leads as &$ld0) $ld0['run'] = $runs[$ld0['id']] ?? ''; unset($ld0);
$adsOn = module_enabled('ads') && can('ads.view');
$dial = (string)(countries()[ws_country()]['dial'] ?? '+');
$lastSync = setting('crm_ads_sync_' . $wid);
render(t('العملاء المحتملين'), 'crm_leads', function () use ($leads, $stages, $sources, $members, $canManage, $uid, $adsOn, $lastSync, $dial, $pipes, $curPl) {
    $now = time();
    $todayEnd = strtotime('tomorrow') - 1;
    $todayStr = (new DateTime('now', new DateTimeZone(site_timezone())))->format('Y-m-d') . 'T23:59'; // next_at جاي بتوقيت الشركة
    $due = count(array_filter($leads, function ($l) use ($todayStr) { return $l['next_at'] && $l['next_at'] <= $todayStr && !in_array($l['stage'], ['won', 'lost'], true); }));
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
    $tagNames = crm_tag_names();
    $stageIcon = ['new' => 'new', 'contacted' => 'chat', 'qualified' => 'star', 'proposal' => 'file', 'won' => 'trophy', 'lost' => 'x'];
    ?>
    <!-- الشريط العلوي: أرقام صغيرة + الأزرار المهمة بس -->
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <button type="button" id="ld-due-tile" class="ld-stat <?= $due ? '!border-amber-300 !bg-amber-50 text-amber-800' : '' ?>" title="<?= e(t('اعرض المستحقين بس')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2"/></svg> <span class="text-ink-500"><?= e(t('مستحقين النهارده')) ?></span> <b><?= $due ?></b></button>
        <span class="ld-stat"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7a2 2 0 012-2h13v4M3 7v10a2 2 0 002 2h15v-5M3 7h17v5h-4a2.5 2.5 0 000 5h4"/></svg> <span class="text-ink-500"><?= e(t('مفتوح')) ?></span> <b><?= e(number_format($pipe)) ?></b></span>
        <?php $fc = crm_forecast(); if ($fc['total'] > 0): ?><span class="ld-stat" title="<?= e(t('مجموع (قيمة كل عميل مفتوح × احتمال مرحلته)')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg> <span class="text-ink-500"><?= e(t('المتوقع')) ?></span> <b class="text-brand-700"><?= e(number_format($fc['total'])) ?></b></span><?php endif; ?>
        <span class="ld-stat"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4h8v5a4 4 0 01-8 0zM8 6H5a3 3 0 003 4M16 6h3a3 3 0 01-3 4M12 13v4M8 21h8M9 17h6"/></svg> <b class="text-lime-700"><?= $won ?></b> <span class="text-ink-400">· <?= count($leads) ? round($won / count($leads) * 100) : 0 ?>%</span></span>
      </div>
      <div class="flex items-center gap-2 ms-auto">
        <button type="button" id="ld-dialer" class="btn-ghost hidden" title="<?= e(t('اتصال ورا اتصال للمستحقين، وتسجيل النتيجة بضغطة')) ?>"><?= ui_icon('phone', 'w-4 h-4') ?><span class="hidden sm:inline"><?= e(t('اتصال متتالي')) ?></span></button>
        <button type="button" id="ld-queue" class="btn-primary hidden shadow-lg shadow-brand-500/20"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4l13 8-13 8z"/></svg> <?= e(t('شغل النهارده')) ?> <span id="ld-queue-n" class="ms-1 rounded-full bg-white/25 px-1.5 text-xs"></span></button>
        <button type="button" id="ld-sug-btn" class="btn-ghost relative"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg> <span class="hidden sm:inline"><?= e(t('عملاء مقترحين')) ?></span><span id="ld-sug-n" class="hidden absolute -top-1.5 -end-1.5 min-w-[18px] h-[18px] rounded-full bg-lime-500 text-white text-[10px] font-bold grid place-items-center px-1 animate-pulse"></span></button>
      <details class="relative" id="ld-tools"><summary class="btn-ghost list-none cursor-pointer select-none !px-2.5" title="<?= e(t('أدوات')) ?>" aria-label="<?= e(t('أدوات')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15a3 3 0 100-6 3 3 0 000 6zM19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 00-2-1.2L14 3h-4l-.6 2.6a7 7 0 00-2 1.2l-2.3-.9-2 3.4 2 1.5A7 7 0 005 12c0 .4 0 .8.1 1.2l-2 1.5 2 3.4 2.3-.9a7 7 0 002 1.2L10 21h4l.6-2.6a7 7 0 002-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z"/></svg> <span class="hidden xl:inline"><?= e(t('أدوات')) ?></span></summary>
        <div class="absolute end-0 mt-1 z-40 w-56 rounded-xl border border-ink-200 bg-white shadow-lg p-1 flex flex-col">
      <?php if ($canManage): ?><button type="button" id="ld-plans-btn" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('خطط المتابعة')) ?></button><?php endif; ?>
      <?php if ($canManage): ?><button type="button" id="ld-sla-btn" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a8 8 0 100-16 8 8 0 000 16zM12 9v4l2 2M10 2h4M12 2v3"/></svg> <?= e(t('سرعة الرد والتوزيع')) ?></button><?php endif; ?>
      <?php if ($canManage): ?><button type="button" id="ld-form-btn" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/></svg> <?= e(t('فورم الموقع')) ?></button><?php endif; ?>
      <button type="button" id="ld-imp-btn" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20V8M7 13l5-5 5 5M4 4h16"/></svg> <?= e(t('استورد عملاء')) ?></button>
      <a class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg" href="<?= e(url('crm_leads', ['export' => 'csv'])) ?>">CSV</a>
      <?php if ($adsOn): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="sync_ads">
        <button class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg" title="<?= e($lastSync ? t('آخر سحب: %s', fmt_date($lastSync)) : t('لسه ما اتسحبش')) ?>" onclick="this.disabled=true;this.form.submit()"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M4 20h16"/></svg> <?= e(t('اسحب من الإعلانات')) ?></button></form><?php endif; ?>
      <?php if (crm_has_key() && can('crm.reveal')): ?><button type="button" id="ld-stale" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg" title="<?= e(t('لحد 25 عميل في المرة، بيصرف كريديت')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg> <?= e(t('كمّل بيانات الناقصين وحدّث القديمة (أكتر من 3 شهور) من Apollo')) ?></button><?php endif; ?>
        </div></details>
        <button type="button" id="ld-add-btn" class="btn-primary">+ <span class="hidden sm:inline"><?= e(t('عميل جديد')) ?></span></button>
      </div>
    </div>



    <!-- المراحل = القمع: عدد كل مرحلة ونسبة اللي عدّوا من اللي قبلها، وبتفلتر بضغطة -->
    <div id="ld-stages" class="ld-funnel flex gap-1.5 mb-4 overflow-x-auto snap-x pb-1 -mx-1 px-1 [&>*]:min-w-[120px] [&>*]:flex-1" role="tablist">
      <button type="button" class="ld-seg on" data-s=""><span class="ld-seg-l"><?= e(t('الكل')) ?></span><b class="ld-seg-n"><?= count($leads) ?></b></button>
      <?php foreach ($stages as $k => $lbl): $ix = array_search($k, $order, true); $prev = $ix ? $reach[$order[$ix - 1]] : 0; ?>
        <button type="button" class="ld-seg" data-s="<?= e($k) ?>" data-stage="<?= e($k) ?>"><span class="ld-seg-l inline-flex items-center gap-1"><?= ui_icon($stageIcon[$k] ?? 'layers', 'w-3.5 h-3.5') ?> <?= e($lbl) ?></span><span class="flex items-baseline gap-1.5"><b class="ld-seg-n" data-cnt="<?= e($k) ?>">0</b><?php if ($ix && $prev): ?><span class="ld-seg-p" title="<?= e(t('النسبة جنب كل مرحلة = كام في المية عدّى من المرحلة اللي قبلها.')) ?>">↗ <?= round($reach[$k] / $prev * 100) ?>%</span><?php endif; ?></span></button>
      <?php endforeach; ?>
    </div>

    <div class="grid min-[1700px]:grid-cols-[minmax(0,1fr)_21rem] gap-4 items-start">
    <div class="min-w-0">
    <!-- البحث + فلتر واحد + الترتيب + طريقة العرض -->
    <div class="flex flex-wrap items-center gap-2 mb-2">
      <div class="relative flex-1 min-w-[200px]"><span class="absolute inset-y-0 start-3 grid place-items-center text-ink-400 pointer-events-none"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 18a7 7 0 100-14 7 7 0 000 14zM20 20l-4-4"/></svg></span>
        <input id="ld-q" type="search" class="input !ps-8" placeholder="<?= e(t('دوّر بالاسم، الشركة، الرقم، الإيميل…')) ?>  ( / )"></div>
      <details class="relative" id="ld-filt"><summary class="btn-ghost list-none cursor-pointer select-none"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg> <?= e(t('فلترة')) ?> <span id="ld-filt-n" class="hidden ms-1 rounded-full bg-brand-600 text-white text-[10px] px-1.5"></span></summary>
        <div class="absolute end-0 mt-1 z-40 w-72 rounded-2xl border border-ink-200 bg-white shadow-xl p-3 space-y-2.5">
          <label class="block"><span class="text-[11px] text-ink-500"><?= e(t('المصدر')) ?></span><select id="ld-src" class="input !py-1.5 text-sm"><option value=""><?= e(t('كل المصادر')) ?></option><?php foreach ($sources as $k => $lbl): if (empty($bySrc[$k])) continue; ?><option value="<?= e($k) ?>"><?= e($lbl) ?> (<?= (int)$bySrc[$k] ?>)</option><?php endforeach; ?></select></label>
          <label class="block"><span class="text-[11px] text-ink-500"><?= e(t('المسؤول')) ?></span><select id="ld-own" class="input !py-1.5 text-sm"><option value=""><?= e(t('كل المسؤولين')) ?></option><option value="<?= $uid ?>"><?= e(t('بتوعي')) ?></option><option value="0"><?= e(t('من غير مسؤول')) ?></option>
            <?php if ($canManage): foreach ($members as $m): if ((int)$m['id'] === $uid) continue; ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; endif; ?></select></label>
          <?php if ($tagNames): ?><label class="block"><span class="text-[11px] text-ink-500"><?= e(t('القايمة')) ?></span><select id="ld-tag" class="input !py-1.5 text-sm"><option value=""><?= e(t('كل القوايم')) ?></option><?php foreach ($tagNames as $tg): ?><option value="<?= e($tg) ?>"><?= e($tg) ?></option><?php endforeach; ?></select></label><?php endif; ?>
          <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" id="ld-due" class="rounded"> <svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2"/></svg> <?= e(t('متابعات مستحقة بس')) ?></label>
          <?php if ($cold): ?><button type="button" id="ld-cold" class="w-full text-start rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-xs text-sky-800 hover:bg-sky-100"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v20M4.9 6.5l14.2 11M19.1 6.5L4.9 17.5M9 4l3 2 3-2M9 20l3-2 3 2"/></svg> <?= e(t('%s عميل محدش كلمه من أسبوع ومالوش متابعة. اعرضهم', $cold)) ?></button><?php endif; ?>
          <button type="button" id="ld-filt-clear" class="text-xs text-ink-500 hover:text-red-600"><?= e(t('امسح الفلاتر')) ?></button>
        </div></details>
      <?php if (count($pipes) > 1): ?><select class="input !w-auto font-semibold text-brand-700" onchange="location.href=this.value" title="Pipeline"><?php foreach ($pipes as $pk => $pv): ?><option value="<?= e(url('crm_leads', ['pl' => $pk])) ?>" <?= $pk === $curPl ? 'selected' : '' ?>><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 19a2 2 0 100-4 2 2 0 000 4zM18 9a2 2 0 100-4 2 2 0 000 4zM6 15V9a4 4 0 014-4h6M18 9v6a4 4 0 01-4 4H8"/></svg> <?= e($pv) ?></option><?php endforeach; ?></select><?php endif; ?>
      <select id="ld-sort" class="input !w-auto"><option value=""><?= e(t('ترتيب: المتابعات الأول')) ?></option><option value="score"><?= e(t('ترتيب: الأهم الأول')) ?></option><option value="new"><?= e(t('ترتيب: الأحدث')) ?></option></select>
      <div class="inline-flex rounded-xl border border-ink-200 bg-white p-0.5" role="group">
        <button type="button" data-v="list" class="ld-view px-2.5 py-1.5 text-sm rounded-lg" title="<?= e(t('قائمة')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></button>
        <button type="button" data-v="board" class="ld-view px-2.5 py-1.5 text-sm rounded-lg" title="<?= e(t('لوحة')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h4v16H4zM10 4h4v10h-4zM16 4h4v7h-4z"/></svg></button>
      </div>
    </div>
    <div id="ld-views" class="flex items-center gap-1 mb-1 overflow-x-auto text-sm" role="tablist">
      <?php foreach (['all' => ['', t('الكل')], 'pin' => ['star', t('المفضلة')], 'mine' => ['user', t('بتوعي')], 'due' => ['clock', t('مستحقين')], 'hot' => ['fire', t('سخنين')], 'cold' => ['snow', t('باردين')], 'fresh' => ['new', t('جداد النهارده')], 'unowned' => ['user_plus', t('من غير مسؤول')]] as $vk => $vv): if ($vk === 'unowned' && !$canManage) continue; ?>
        <button type="button" data-view="<?= $vk ?>" class="ld-vtab shrink-0 px-3 py-1.5 rounded-full text-ink-600 hover:bg-white hover:shadow-sm transition inline-flex items-center gap-1.5"><?= $vv[0] ? ui_icon($vv[0], 'w-4 h-4') : '' ?> <?= e($vv[1]) ?> <span class="ld-vn text-[11px] text-ink-400"></span></button>
      <?php endforeach; ?>
    </div>
    <div id="ld-chips" class="flex flex-wrap gap-1.5 mb-3 min-h-[4px]"></div>

    <?php if (!$leads): ?>
      <div id="ld-empty"><?= an_empty(t('لسه مفيش Leads'), t('اكشف أشخاص من الشركات المستهدفة، أو ضيف Lead بإيدك.'), 'magnet') ?></div>
    <?php endif; ?>
    <div id="ld-bulk" class="hidden sticky top-16 z-30 mb-3 flex flex-wrap items-center gap-2 rounded-2xl border border-brand-200 bg-brand-50 px-3 py-2 shadow-sm">
      <label class="inline-flex items-center gap-1.5 text-sm"><input type="checkbox" id="ld-all" class="rounded"> <b id="ld-bn">0</b> <?= e(t('محدد')) ?></label>
      <button type="button" id="ld-b-merge" class="btn-ghost !py-1 !text-xs hidden"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/></svg> <?= e(t('دمج')) ?></button>
      <button type="button" id="ld-b-mail" class="btn-ghost !py-1 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3zM3 7l9 7 9-7"/></svg> <?= e(t('إيميل جماعي')) ?></button>
      <select id="ld-b-stage" class="input !w-auto !py-1 text-xs"><option value=""><?= e(t('غيّر المرحلة…')) ?></option><?php foreach ($stages as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select>
      <?php if ($canManage): ?><select id="ld-b-owner" class="input !w-auto !py-1 text-xs"><option value=""><?= e(t('وزّع على…')) ?></option><option value="0"><?= e(t('من غير مسؤول')) ?></option><?php foreach ($members as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
      <select id="ld-b-plan" class="input !w-auto !py-1 text-xs"><option value=""><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('ابدأ خطة متابعة…')) ?></option><?php foreach (crm_plans() as $pl): ?><option value="<?= (int)$pl['id'] ?>"><?= e($pl['name']) ?></option><?php endforeach; ?></select>
      <button type="button" id="ld-b-tag" class="btn-ghost !py-1 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12V4h8l10 10-8 8zM7.5 8.5h.01"/></svg> <?= e(t('ضيف لقايمة')) ?></button>
      <?php if (crm_has_key()): ?>
        <?php if (can('crm.reveal')): ?><button type="button" id="ld-b-enrich" class="btn-ghost !py-1 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg> <?= e(t('كمّل من Apollo')) ?></button><?php endif; ?>
        <button type="button" id="ld-b-seq" class="btn-ghost !py-1 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3zM3 7l9 7 9-7"/></svg> Sequence</button>
      <?php endif; ?>
      <button type="button" id="ld-b-clear" class="ms-auto text-xs text-ink-500 hover:text-ink-900">✕</button>
    </div>
    <div id="ld-list" class="card divide-y divide-ink-100 overflow-hidden"></div>
    <div id="ld-board" class="hidden grid grid-flow-col auto-cols-[minmax(250px,1fr)] gap-3 overflow-x-auto pb-3 snap-x"></div>
    <div id="ld-more" class="hidden text-center mt-3"><button type="button" class="btn-ghost"><?= e(t('اعرض أكتر')) ?> <span class="text-ink-400"></span></button></div>
    </div>
    <!-- عمود النهارده: المستحقين والسخنين والتنبيهات، كله بيفتح العميل بضغطة -->
    <details id="ld-side-d" class="order-first min-[1700px]:order-none min-w-0 group/sd" open>
      <summary class="min-[1700px]:hidden list-none cursor-pointer select-none card px-4 py-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm mb-3">
        <b class="text-ink-900"><?= e(t('شغل النهارده')) ?></b>
        <span class="inline-flex items-center gap-1 text-amber-700"><?= ui_icon('clock', 'w-4 h-4') ?><b id="sd-s-due">0</b> <?= e(t('مستحقين')) ?></span>
        <span class="inline-flex items-center gap-1 text-rose-700"><?= ui_icon('fire', 'w-4 h-4') ?><b id="sd-s-hot">0</b> <?= e(t('سخنين')) ?></span>
        <span class="inline-flex items-center gap-1 text-brand-700"><?= ui_icon('inbox', 'w-4 h-4') ?><b id="sd-s-new">0</b> <?= e(t('جداد الأسبوع ده')) ?></span>
        <span class="ms-auto text-xs text-ink-400 inline-flex items-center gap-1"><span class="group-open/sd:hidden"><?= e(t('اعرض')) ?></span><span class="hidden group-open/sd:inline"><?= e(t('اقفل')) ?></span><?= ui_icon('chevron', 'w-4 h-4 rotate-90 group-open/sd:-rotate-90 transition') ?></span>
      </summary>
    <aside id="ld-side" class="grid md:grid-cols-3 min-[1700px]:grid-cols-1 gap-3 mb-3 min-[1700px]:mb-0 min-[1700px]:sticky min-[1700px]:top-20 [&>*]:min-w-0 [&_.card>div]:max-h-56 [&_.card>div]:overflow-y-auto min-[1700px]:[&_.card>div]:max-h-none">
      <div class="card p-3"><div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2"/></svg> <?= e(t('مستحقين النهارده')) ?></b><span id="sd-due-n" class="text-xs text-ink-400"></span><button type="button" data-sd-view="due" class="ms-auto link text-xs"><?= e(t('الكل')) ?></button></div><div id="sd-due" class="space-y-1"></div></div>
      <div class="card p-3"><div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a7 7 0 007-7c0-4-3-6-4-9-1 2-2 3-3 3 0-2-1-4-3-5 0 4-4 6-4 11a7 7 0 007 7zM12 21a3 3 0 003-3c0-2-1.5-3-3-5-1.5 2-3 3-3 5a3 3 0 003 3z"/></svg> <?= e(t('الأسخن دلوقتي')) ?></b><button type="button" data-sd-view="hot" class="ms-auto link text-xs"><?= e(t('الكل')) ?></button></div><div id="sd-hot" class="space-y-1"></div></div>
      <div class="card p-3" id="sd-tasks-card"><div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900 inline-flex items-center gap-1.5"><?= ui_icon('check', 'w-4 h-4') ?><?= e(t('مهامي')) ?></b><span id="sd-tasks-n" class="text-xs text-ink-400"></span></div>
        <div id="sd-tasks" class="space-y-1 mb-2"></div>
        <form id="sd-task-f" class="flex flex-wrap gap-1.5" data-no-ajax><input name="title" required maxlength="255" class="input !mt-0 !py-1.5 text-xs flex-1 min-w-[120px]" placeholder="<?= e(t('مهمة جديدة… (مثلًا: جهّز عرض السعر)')) ?>" dir="auto">
          <input type="datetime-local" name="due" class="input !mt-0 !py-1.5 !w-auto text-xs" title="<?= e(t('الميعاد (اختياري)')) ?>">
          <?php if ($canManage): ?><select name="user" class="input !mt-0 !py-1.5 !w-auto text-xs"><?php foreach ($members as $m): ?><option value="<?= (int)$m['id'] ?>" <?= (int)$m['id'] === $uid ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
          <button class="btn-primary !py-1.5 !px-2.5 !text-xs"><?= ui_icon('plus', 'w-4 h-4') ?></button></form></div>
      <div class="card p-3"><b class="text-sm text-ink-900 block mb-2"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13l3-8h12l3 8v6H3zM3 13h5l1 3h6l1-3h5"/></svg> <?= e(t('جم منين الأسبوع ده')) ?></b><div id="sd-src" class="space-y-1.5"></div></div>
      <?php $nfs = crm_notifs($uid, 20); $nfU = count(array_filter($nfs, function ($x) { return !(int)$x['seen']; })); if ($nfs): ?>
    <details id="notifs" class="card group md:col-span-3 min-[1700px]:col-span-1">
      <summary class="cursor-pointer list-none flex items-center gap-2 px-4 py-2.5 text-sm"><span><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 16V11a6 6 0 1112 0v5l2 2H4zM10 21h4"/></svg> <b><?= e(t('التنبيهات')) ?></b></span><?php if ($nfU): ?><span class="rounded-full bg-rose-600 text-white text-[10px] px-1.5"><?= $nfU ?></span><?php endif; ?>
        <button type="button" id="nf-seen" class="link text-xs ms-auto <?= $nfU ? '' : 'hidden' ?>"><?= e(t('علّمهم مقروءين')) ?></button></summary>
      <div class="divide-y divide-ink-100 border-t border-ink-100 max-h-72 overflow-y-auto"><?php foreach ($nfs as $nf): ?>
        <a href="#lead-<?= (int)$nf['lead_id'] ?>" data-nf-lead="<?= (int)$nf['lead_id'] ?>" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-ink-50 <?= (int)$nf['seen'] ? 'text-ink-500' : 'text-ink-900 font-medium bg-rose-50/40' ?>"><span class="flex-1"><?= e($nf['body']) ?></span><span class="text-[11px] text-ink-400 shrink-0"><?= e(fmt_date($nf['created_at'], 'd/m h:i A')) ?></span></a>
      <?php endforeach; ?></div>
    </details>
    <?php endif; ?>
    </aside>
    </details>
    </div>
    <div id="ld-none" class="hidden text-center py-14"><div class="text-4xl mb-2"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 18a7 7 0 100-14 7 7 0 000 14zM20 20l-4-4"/></svg></div><div class="text-sm text-ink-600"><?= e(t('مفيش عملاء بالشكل ده')) ?></div><button type="button" id="ld-none-clear" class="link text-xs mt-2"><?= e(t('امسح الفلاتر')) ?></button></div>

    <div id="ld-drawer" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
      <div class="ld-fade absolute inset-0 bg-ink-900/40 backdrop-blur-[1px]" data-close></div>
      <form id="ld-form" class="ld-sheet absolute top-0 bottom-0 end-0 w-full sm:w-[600px] bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="ld-d-title">
        <input type="hidden" name="id"><input type="hidden" name="action" value="save">
        <div class="px-4 pt-4 border-b border-ink-100 bg-gradient-to-b from-brand-50/70 to-white">
          <div class="flex items-start gap-3">
            <div id="ld-d-av" class="shrink-0"></div>
            <div class="min-w-0 flex-1"><div class="flex items-center gap-2 min-w-0"><div id="ld-d-title" class="font-bold text-lg text-ink-900 truncate"></div><span id="ld-d-score"></span></div><div id="ld-d-sub" class="text-xs text-ink-500 truncate"></div><div id="ld-d-stage" class="mt-1"></div></div>
            <button type="button" id="ld-q-next" class="hidden btn-primary !py-1.5 !text-xs"></button>
            <button type="button" class="btn-ghost" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button>
          </div>
          <div id="ld-quick" class="flex flex-wrap gap-1.5 mt-3"></div>
          <div class="h-3"></div>
        </div>
        <div id="ld-scroll" class="flex-1 min-h-0 overflow-y-auto divide-y divide-ink-100">
        <details class="ld-panel ld-sec group/sec" data-tab="ov" open>
          <summary class="ld-sec-h"><span class="w-7 h-7 rounded-lg bg-ink-100 text-ink-600 grid place-items-center group-open/sec:bg-brand-600 group-open/sec:text-[#fff] transition"><?= ui_icon('spark', 'w-4 h-4') ?></span><span class="flex-1"><?= e(t('نظرة عامة')) ?></span><span class="ld-tabn text-[11px] text-ink-400"></span><?= ui_icon('chevron', 'w-4 h-4 text-ink-400 rotate-90 group-open/sec:-rotate-90 transition') ?></summary>
          <div class="px-4 pb-4 space-y-4">
          <div id="ld-intel" class="rounded-xl bg-ink-50 border border-ink-200 p-3 text-xs space-y-1"></div>
          <div><span class="label"><?= e(t('المرحلة')) ?></span><div id="ld-stage-pick" class="flex flex-wrap gap-1.5">
            <?php foreach ($stages as $k => $lbl): ?><label class="cursor-pointer"><input type="radio" name="stage" value="<?= e($k) ?>" class="peer sr-only"><span class="inline-block px-2.5 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600 peer-focus-visible:ring-2 ring-brand-300"><?= e($lbl) ?></span></label><?php endforeach; ?>
          </div></div>
          <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2"/></svg> <?= e(t('المتابعة الجاية')) ?></div>
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
        </div></details>
        <details class="ld-panel ld-sec group/sec" data-tab="ct" open>
          <summary class="ld-sec-h"><span class="w-7 h-7 rounded-lg bg-ink-100 text-ink-600 grid place-items-center group-open/sec:bg-brand-600 group-open/sec:text-[#fff] transition"><?= ui_icon('chat', 'w-4 h-4') ?></span><span class="flex-1"><?= e(t('تواصل')) ?></span><span class="ld-tabn text-[11px] text-ink-400"></span><?= ui_icon('chevron', 'w-4 h-4 text-ink-400 rotate-90 group-open/sec:-rotate-90 transition') ?></summary>
          <div class="px-4 pb-4 space-y-4">
          <div id="ld-tpl" class="hidden rounded-xl border border-lime-200 bg-lime-50/40 p-3 space-y-2">
            <div class="flex items-center gap-2"><span class="text-sm font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg> <?= e(t('ابعت رسالة جاهزة')) ?></span>
              <?php if ($canManage): ?><button type="button" id="ld-tpl-edit" class="link text-xs ms-auto"><?= e(t('عدّل القوالب')) ?></button><?php endif; ?></div>
            <div id="ld-tpl-list" class="flex flex-wrap gap-1.5"></div>
            <p class="text-[11px] text-ink-500"><?= e(t('بيفتح واتساب بالرسالة مكتوبة، وبيتسجل في سجل التواصل لوحده.')) ?></p>
          </div>
          <div id="ld-mail" class="hidden rounded-xl border border-ink-200 p-3 space-y-2">
            <div class="flex items-center gap-2"><span class="text-sm font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3zM3 7l9 7 9-7"/></svg> <?= e(t('ابعت إيميل من هنا')) ?></span><button type="button" id="ld-mail-tg" class="link text-xs ms-auto"><?= e(t('اكتب')) ?></button></div>
            <div id="ld-mail-f" class="hidden space-y-1.5"><input id="ld-mail-s" class="input text-sm" maxlength="255" placeholder="<?= e(t('العنوان')) ?>"><textarea id="ld-mail-b" rows="5" class="input text-sm" dir="auto"></textarea>
              <button type="button" id="ld-mail-go" class="btn-primary !py-1.5 !text-xs w-full"><?= e(t('ابعت')) ?></button>
              <p class="text-[11px] text-ink-500"><?= e(t('بيتبعت من SMTP بتاعك. هتعرف لو فتحه أو ضغط على رابط (الفتح تقريبي: بعض برامج الإيميل بتمنع الصور).')) ?></p></div>
            <div id="ld-mails" class="space-y-1"></div>
          </div>
          <div class="rounded-xl border border-ink-200 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2"/></svg> <?= e(t('سجل التواصل')) ?></div>
            <div class="flex flex-wrap gap-1.5" id="ld-log-kinds"><?php foreach (crm_act_kinds() as $k => $v): if ($k === 'stage') continue; ?><button type="button" class="chip text-xs" data-k="<?= e($k) ?>"><?= ui_icon(['call' => 'phone', 'wa' => 'chat', 'email' => 'mail', 'meet' => 'users', 'note' => 'file'][$k] ?? 'file', 'w-3.5 h-3.5 inline-block align-[-2px]') ?> <?= e($v[1]) ?></button><?php endforeach; ?></div>
            <div id="ld-oc" class="hidden flex flex-wrap gap-1.5 items-center"><span class="text-[11px] text-ink-500"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a1 1 0 01-1 1A16 16 0 014 5a1 1 0 011-1z"/></svg> <?= e(t('نتيجة المكالمة؟')) ?></span><?php foreach (crm_call_outcomes() as $k => $v): ?><button type="button" class="chip text-xs" data-oc="<?= e($k) ?>"><?= e($v) ?></button><?php endforeach; ?></div>
            <div id="ld-qr" class="flex flex-wrap gap-1.5"><?php foreach ([t('مردّش'), t('مهتم 🔥'), t('طلب عرض سعر'), t('كلّمه تاني بعدين'), t('مش مهتم')] as $q): ?><button type="button" class="pd-qr" data-qr="<?= e($q) ?>"><?= e($q) ?></button><?php endforeach; ?></div>
            <div class="flex gap-2"><input id="ld-log-body" class="input text-sm" maxlength="2000" placeholder="<?= e(t('حصل إيه؟ (اختياري) واختار النوع')) ?>">
              <button type="button" id="ld-dict" class="btn-ghost !px-2.5 hidden" title="<?= e(t('إملاء بالصوت (في المتصفح، ببلاش)')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a3 3 0 00-3 3v6a3 3 0 006 0V6a3 3 0 00-3-3zM5 11a7 7 0 0014 0M12 18v3"/></svg></button>
              <button type="button" id="ld-rec" class="btn-ghost !px-2.5" title="<?= e(t('سجّل ملاحظة صوتية')) ?>"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a3 3 0 00-3 3v6a3 3 0 006 0V6a3 3 0 00-3-3zM5 11a7 7 0 0014 0M12 18v3"/></svg></button></div>
            <div id="ld-rec-st" class="hidden text-xs text-red-600 flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span><span id="ld-rec-t">0:00</span> <button type="button" id="ld-rec-stop" class="btn-ghost !py-0.5 !text-xs"><?= e(t('وقّف واحفظ')) ?></button></div>
            <div id="ld-tf" class="flex flex-wrap gap-1"></div>
            <ol id="ld-acts" class="space-y-2 max-h-72 overflow-y-auto"></ol>
          </div>
        </div></details>
        <details class="ld-panel ld-sec group/sec" data-tab="dl" open>
          <summary class="ld-sec-h"><span class="w-7 h-7 rounded-lg bg-ink-100 text-ink-600 grid place-items-center group-open/sec:bg-brand-600 group-open/sec:text-[#fff] transition"><?= ui_icon('wallet', 'w-4 h-4') ?></span><span class="flex-1"><?= e(t('صفقات ومتابعة')) ?></span><span class="ld-tabn text-[11px] text-ink-400"></span><?= ui_icon('chevron', 'w-4 h-4 text-ink-400 rotate-90 group-open/sec:-rotate-90 transition') ?></summary>
          <div class="px-4 pb-4 space-y-4">
          <div id="ld-deals" class="rounded-xl border border-ink-200 p-3 space-y-2">
            <div class="flex items-center gap-2"><span class="text-sm font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7a2 2 0 012-2h13v4M3 7v10a2 2 0 002 2h15v-5M3 7h17v5h-4a2.5 2.5 0 000 5h4"/></svg> <?= e(t('الصفقات')) ?></span><button type="button" id="ld-deal-add" class="link text-xs ms-auto">+ <?= e(t('صفقة')) ?></button></div>
            <div id="ld-deal-list" class="space-y-1.5"></div>
          </div>
          <div id="ld-plan" class="rounded-xl border border-sky-200 bg-sky-50/40 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('خطة المتابعة')) ?></div>
            <div id="ld-plan-body" class="text-xs"></div>
          </div>
        </div></details>
        <details class="ld-panel ld-sec group/sec" data-tab="dt" open>
          <summary class="ld-sec-h"><span class="w-7 h-7 rounded-lg bg-ink-100 text-ink-600 grid place-items-center group-open/sec:bg-brand-600 group-open/sec:text-[#fff] transition"><?= ui_icon('layers', 'w-4 h-4') ?></span><span class="flex-1"><?= e(t('البيانات')) ?></span><span class="ld-tabn text-[11px] text-ink-400"></span><?= ui_icon('chevron', 'w-4 h-4 text-ink-400 rotate-90 group-open/sec:-rotate-90 transition') ?></summary>
          <div class="px-4 pb-4 space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <label class="block col-span-2"><span class="label"><?= e(t('الاسم')) ?></span><input class="input" name="name" required maxlength="255"></label>
            <label class="block"><span class="label"><?= e(t('الشركة')) ?></span><input class="input" name="company" maxlength="255"></label>
            <label class="block"><span class="label"><?= e(t('المسمى الوظيفي')) ?></span><input class="input" name="title" maxlength="255"></label>
            <label class="block"><span class="label"><?= e(t('الرقم')) ?></span><input class="input" name="phone" dir="ltr" maxlength="40"></label>
            <label class="block"><span class="label"><?= e(t('الإيميل')) ?></span><input class="input" name="email" type="email" dir="ltr" maxlength="255"></label>
            <label class="block"><span class="label"><?= e(t('قيمة الصفقة')) ?></span><input class="input" name="value" inputmode="decimal" dir="ltr"></label>
            <label class="block"><span class="label"><?= e(t('المسؤول')) ?></span><select class="input" name="owner" <?= $canManage ? '' : 'disabled' ?>><option value="0"><?= e(t('من غير مسؤول')) ?></option>
              <?php foreach ($members as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select></label>
            <label class="block col-span-2 hidden" id="ld-handoff"><span class="label"><?= e(t('ملاحظة التسليم (هتوصل للمسؤول الجديد)')) ?></span><textarea class="input" name="handoff" rows="2" maxlength="500" dir="auto" placeholder="<?= e(t('اتكلمنا على إيه، ومستني إيه، وأحسن وقت يتكلم فيه')) ?>"></textarea></label>
            <?php if (count(crm_pipelines()) > 1): ?><label class="block col-span-2"><span class="label">Pipeline</span><select class="input" name="pipeline"><?php foreach (crm_pipelines() as $pk => $pv): ?><option value="<?= e($pk) ?>"><?= e($pv) ?></option><?php endforeach; ?></select></label><?php endif; ?>
            <?php foreach (crm_custom_fields() as $cf): ?><label class="block"><span class="label"><?= e($cf['label']) ?></span>
              <?php if ($cf['type'] === 'select'): ?><select class="input" name="cf[<?= e($cf['key']) ?>]" data-cf="<?= e($cf['key']) ?>"><option value="">—</option><?php foreach ($cf['opts'] as $o): ?><option><?= e($o) ?></option><?php endforeach; ?></select>
              <?php else: ?><input class="input" name="cf[<?= e($cf['key']) ?>]" data-cf="<?= e($cf['key']) ?>" <?= $cf['type'] === 'number' ? 'inputmode="decimal" dir="ltr"' : 'dir="auto"' ?> maxlength="200"><?php endif; ?></label><?php endforeach; ?>
            <label class="block col-span-2"><span class="label"><?= e(t('تاجات (افصل بفاصلة)')) ?></span><input class="input" name="tags" maxlength="255" placeholder="<?= e(t('VIP، مطاعم')) ?>"></label>
            <label class="block col-span-2"><span class="label"><?= e(t('ملاحظات')) ?></span><textarea class="input" name="note" rows="4" maxlength="2000"></textarea></label>
          </div>
          <?php if (crm_has_key()): ?>
          <div id="ld-apollo" class="rounded-xl border border-violet-200 bg-violet-50/40 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg> Apollo</div>
            <div class="flex flex-wrap gap-1.5">
              <?php if (can('crm.reveal')): ?><button type="button" id="ld-enrich" class="btn-ghost !py-1 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg> <?= e(t('كمّل بياناته من Apollo')) ?></button><?php endif; ?>
              <button type="button" id="ld-seq-open" class="btn-ghost !py-1 !text-xs"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3zM3 7l9 7 9-7"/></svg> <?= e(t('ضيفه لـ Sequence')) ?></button>
            </div>
            <div id="ld-seq-box" class="hidden space-y-1.5"><select id="ld-seq" class="input text-xs"></select><select id="ld-seq-acc" class="input text-xs"></select>
              <button type="button" id="ld-seq-go" class="btn-primary !py-1 !text-xs w-full"><?= e(t('ابدأ الـ Sequence')) ?></button></div>
            <div id="ld-ap-msg" class="text-[11px]"></div>
          </div>
          <?php endif; ?>
          <div id="ld-visits" class="rounded-xl border border-ink-200 p-3 space-y-2">
            <div class="flex items-center gap-2"><span class="text-sm font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/></svg> <?= e(t('زياراته لموقعك')) ?></span><button type="button" id="ld-tl" class="link text-xs ms-auto"><?= e(t('اعمل رابط متتبع')) ?></button></div>
            <div id="ld-tl-box" class="hidden flex gap-1.5"><input id="ld-tl-u" class="input !py-1 text-xs" dir="ltr" placeholder="https://" value="<?= e((string)setting('crm_site_url_' . (int)ws('id'), '')) ?>"><button type="button" id="ld-tl-go" class="btn-primary !py-1 !text-xs">⧉</button></div>
            <div id="ld-visit-list" class="space-y-1"></div>
          </div>
          <div id="ld-meta" class="text-xs text-ink-400"></div>
        </div></details>
        </div>
        <div class="shrink-0 p-3 border-t border-ink-100 bg-white/95 backdrop-blur flex items-center gap-2">
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
        <h3 id="ld-imp-t" class="font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20V8M7 13l5-5 5 5M4 4h16"/></svg> <?= e(t('استورد عملاء')) ?></h3>
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
        <div class="flex items-center gap-2 p-4 border-b border-ink-100"><h3 id="ld-sug-h" class="font-semibold text-ink-900 flex-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg> <?= e(t('عملاء مقترحين')) ?></h3><button type="button" class="btn-ghost !px-2 !py-1" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button></div>
        <p class="px-4 pt-3 text-xs text-ink-500"><?= e(t('ناس كلّموك أو علّقوا على منشوراتك وباين عليهم مهتمين: الذكاء الاصطناعي لقى عندهم نية شراء، أو كتبوا رقمهم، أو سألوا عن السعر. اللي في الـ Leads مش بيظهروا هنا.')) ?></p>
        <div class="px-4 pt-2 flex flex-wrap items-center gap-2 text-xs">
          <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" id="ld-sug-all" class="rounded"> <?= e(t('تحديد الكل')) ?></label>
          <button type="button" id="ld-sug-ai" class="link hidden"><?= e(t('حلّل المحادثات الجديدة بالذكاء الاصطناعي ✦')) ?></button>
          <span id="ld-sug-msg" class="ms-auto text-ink-500"></span>
        </div>
        <?php if ($canManage): ?>
        <label class="mx-4 mt-2 flex items-center gap-2 text-xs"><span class="text-ink-500 shrink-0"><?= e(t('كلمات الدعوة في منشوراتك:')) ?></span><input id="ld-cta" class="input !py-1 text-xs" dir="auto" value="<?= e(implode(', ', crm_cta_words())) ?>"></label>
        <?php endif; ?>
        <?php if ($canManage): $al = explode('|', (string)setting('crm_auto_last_' . (int)ws('id'), '')); ?>
        <label class="mx-4 mt-2 flex items-start gap-2 rounded-xl border border-lime-200 bg-lime-50/50 p-2.5 text-xs cursor-pointer">
          <input type="checkbox" id="ld-auto" class="rounded mt-0.5" <?= crm_auto_capture_on((int)ws('id')) ? 'checked' : '' ?>>
          <span><b class="text-ink-900"><?= e(t('ضيفهم أوتوماتيك')) ?></b> · <?= e(t('كل 6 ساعات: اللي عنده نية شراء أو كتب رقمه أو رد بكلمة الدعوة بيتضاف لوحده، بيتوزع على الفريق، وبتتحطله متابعة النهارده.')) ?>
            <?php if (!empty($al[0])): ?><span class="block text-ink-500 mt-0.5"><?= e(t('آخر مرة: %s · اتضاف %s', fmt_date($al[0], 'd/m h:i A'), (int)($al[1] ?? 0))) ?></span><?php endif; ?></span>
        </label>
        <?php endif; ?>
        <div id="ld-sug-list" class="flex-1 overflow-y-auto p-4 space-y-2"></div>
        <div class="p-4 border-t border-ink-100"><button type="button" id="ld-sug-go" class="btn-primary w-full" disabled><?= e(t('ضيف المحددين للـ Leads')) ?></button></div>
      </div>
    </div>
    <?php if ($canManage): $fc = (json_decode((string)setting('crm_form_cfg_' . (int)ws('id'), ''), true) ?: []) + ['title' => '', 'thanks' => '', 'company' => 1, 'message' => 1, 'color' => '#4f46e5']; $furl = crm_form_url(); ?>
    <div id="ld-fdlg" class="fixed inset-0 z-[60] hidden overflow-y-auto p-4" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <form id="ld-fform" class="relative card p-5 w-full max-w-2xl mx-auto space-y-3" role="dialog" aria-modal="true">
        <h3 class="font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/></svg> <?= e(t('فورم الموقع')) ?></h3>
        <p class="text-xs text-ink-500"><?= e(t('حط الكود ده في أي صفحة على موقعك. أي حد يملاه بيدخل الـ Leads على طول، بيتوزع على الفريق، وبتتحطله متابعة النهارده. من غير أي اشتراك.')) ?></p>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="on" class="rounded" <?= setting('crm_form_on_' . (int)ws('id'), '1') === '1' ? 'checked' : '' ?>> <?= e(t('الفورم شغال')) ?></label>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block"><span class="label"><?= e(t('عنوان الفورم')) ?></span><input class="input" name="title" maxlength="120" value="<?= e($fc['title']) ?>" placeholder="<?= e(t('اطلب عرض سعر')) ?>"></label>
          <label class="block"><span class="label"><?= e(t('اللون')) ?></span><input class="input !h-10 !p-1" type="color" name="color" value="<?= e($fc['color']) ?>"></label>
          <label class="block sm:col-span-2"><span class="label"><?= e(t('رسالة الشكر')) ?></span><input class="input" name="thanks" maxlength="300" value="<?= e($fc['thanks']) ?>" placeholder="<?= e(t('شكرًا! هنكلمك قريب.')) ?>"></label>
        </div>
        <div class="flex flex-wrap gap-4 text-sm"><label class="inline-flex items-center gap-2"><input type="checkbox" name="company" class="rounded" <?= $fc['company'] ? 'checked' : '' ?>> <?= e(t('خانة الشركة')) ?></label><label class="inline-flex items-center gap-2"><input type="checkbox" name="message" class="rounded" <?= $fc['message'] ? 'checked' : '' ?>> <?= e(t('خانة الرسالة')) ?></label></div>
        <div><span class="label"><?= e(t('1) كود يتحط في الموقع (iframe)')) ?></span><textarea readonly rows="3" class="input text-xs font-mono" dir="ltr" data-code="iframe"></textarea></div>
        <div><span class="label"><?= e(t('2) أو رابط تبعته مباشرة (واتساب، بايو إنستجرام…)')) ?></span><input readonly class="input text-xs font-mono" dir="ltr" data-code="link"></div>
        <div><span class="label"><?= e(t('3) أو Webhook لأدوات الفورمز (Elementor، WPForms، Typeform، Zapier)')) ?></span><input readonly class="input text-xs font-mono" dir="ltr" data-code="hook">
          <p class="text-[11px] text-ink-500 mt-1"><?= e(t('ابعت POST بخانات اسمها name و phone و email (أو بالعربي: الاسم، رقم، الإيميل). بنفهم الأسماء الشائعة لوحدنا.')) ?></p></div>
        <div class="flex flex-wrap items-center gap-2"><button class="btn-primary"><?= e(t('احفظ')) ?></button><a class="btn-ghost" target="_blank" rel="noopener" data-code="open"><?= e(t('جرّب الفورم')) ?> ↗</a>
          <label class="inline-flex items-center gap-1.5 text-xs text-ink-500 ms-auto"><input type="checkbox" name="regen" class="rounded"> <?= e(t('غيّر الرابط (القديم هيبطل)')) ?></label><button type="button" class="btn-ghost" data-close><?= e(t('اقفل')) ?></button></div>
        <span id="ld-fmsg" class="text-xs"></span>
      </form>
      <?php $sl = crm_sla_cfg(); ?>
      <form id="ld-slaform" class="relative card p-5 w-full max-w-2xl mx-auto space-y-3 mt-3">
        <h3 class="font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a8 8 0 100-16 8 8 0 000 16zM12 9v4l2 2M10 2h4M12 2v3"/></svg> <?= e(t('سرعة الرد على العملاء الجداد')) ?></h3>
        <p class="text-xs text-ink-500"><?= e(t('أسرع رد = عملاء أكتر. لو عميل جديد عدّى الوقت ده ومحدش كلّمه، المسؤول بيوصله تنبيه في الجرس (ولو مالوش مسؤول، المديرين). ولو عدّى وقت النقل، بيتحوّل لأقل موظف عنده شغل.')) ?></p>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block"><span class="label"><?= e(t('نبّه بعد (دقيقة)')) ?></span><input type="number" min="0" max="1440" name="mins" class="input" value="<?= (int)$sl['mins'] ?>"><span class="text-[11px] text-ink-400"><?= e(t('0 = من غير تنبيه')) ?></span></label>
          <label class="block"><span class="label"><?= e(t('انقله لحد تاني بعد (ساعة)')) ?></span><input type="number" min="0" max="72" name="move_hours" class="input" value="<?= (int)$sl['move_hours'] ?>"><span class="text-[11px] text-ink-400"><?= e(t('0 = مايتنقلش')) ?></span></label>
        </div>
        <div class="pt-2 border-t border-ink-100"><div class="font-medium text-ink-900 text-sm"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z"/></svg> <?= e(t('قواعد التوزيع')) ?></div>
          <p class="text-[11px] text-ink-500 mb-2"><?= e(t('العميل الجديد بيروح لأول قاعدة تنطبق عليه. لو مفيش، بيتوزع بالتناوب.')) ?></p>
          <div id="ld-rules" class="space-y-1.5"><?php $rr = crm_route_rules(); $rr[] = ['field' => 'source', 'value' => '', 'owner' => 0]; foreach ($rr as $ri => $r): ?>
            <div class="grid grid-cols-[110px_1fr_1fr] gap-1.5" data-rule><select class="input !py-1 text-xs" name="rule[<?= $ri ?>][field]"><?php foreach (['source' => t('المصدر'), 'country' => t('الدولة'), 'phone' => t('الرقم بيبدأ بـ'), 'keyword' => t('كلمة في الشركة/الوظيفة')] as $fk => $fl): ?><option value="<?= $fk ?>" <?= ($r['field'] ?? '') === $fk ? 'selected' : '' ?>><?= e($fl) ?></option><?php endforeach; ?></select>
              <input class="input !py-1 text-xs" name="rule[<?= $ri ?>][value]" value="<?= e((string)($r['value'] ?? '')) ?>" placeholder="<?= e(t('مثلاً: ads أو SA أو 966 أو مطعم')) ?>">
              <select class="input !py-1 text-xs" name="rule[<?= $ri ?>][owner]"><option value="0">—</option><?php foreach ($members as $mm): ?><option value="<?= (int)$mm['id'] ?>" <?= (int)($r['owner'] ?? 0) === (int)$mm['id'] ? 'selected' : '' ?>><?= e($mm['name']) ?></option><?php endforeach; ?></select></div>
          <?php endforeach; ?></div>
          <p class="text-[10px] text-ink-400 mt-1"><?= e(t('قيم المصدر: %s', implode('، ', array_map(function ($k, $v) { return $k . '=' . $v; }, array_keys($sources), $sources)))) ?></p>
        </div>
        <div class="pt-2 border-t border-ink-100"><div class="font-medium text-ink-900 text-sm"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg> <?= e(t('احتمال الإغلاق لكل مرحلة (لتوقع الإيراد)')) ?></div>
          <div class="grid grid-cols-4 gap-2 mt-1"><?php $pb = crm_stage_probs(); foreach (['new', 'contacted', 'qualified', 'proposal'] as $k): ?><label class="block text-[11px] text-ink-500"><?= e($stages[$k]) ?><input type="number" min="0" max="100" class="input !py-1 text-sm" name="prob[<?= $k ?>]" value="<?= (int)$pb[$k] ?>"></label><?php endforeach; ?></div></div>
        <div class="flex gap-2"><button class="btn-primary"><?= e(t('احفظ')) ?></button><span id="ld-slamsg" class="text-xs self-center"></span></div>
      </form>
      <?php $tc = crm_track_cfg((int)ws('id')); ?>
      <form id="ld-tform" class="relative card p-5 w-full max-w-2xl mx-auto space-y-3 mt-3">
        <h3 class="font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-12a7 7 0 0114 0c0 5.8-7 12-7 12zM12 11a2 2 0 100-4 2 2 0 000 4z"/></svg> <?= e(t('كود الموقع: تتبع الزيارات + زرار عائم')) ?></h3>
        <p class="text-xs text-ink-500"><?= e(t('حط السطر ده مرة واحدة في كل صفحات موقعك (قبل </body>). لما تبعت لعميل رابط متتبع (من صفحة العميل أو {site} في القوالب)، كل صفحة يفتحها بعد كده بتتسجل عنده، ولو فتح صفحة أسعار بيوصلك تنبيه "كلّمه دلوقتي". الزوار العاديين مابيتسجلوش.')) ?></p>
        <textarea readonly rows="2" class="input text-xs font-mono" dir="ltr" data-scode><?= e(crm_site_script()) ?></textarea>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block"><span class="label"><?= e(t('رابط موقعك')) ?></span><input class="input" name="site" dir="ltr" placeholder="https://yoursite.com" value="<?= e((string)setting('crm_site_url_' . (int)ws('id'), '')) ?>"></label>
          <label class="block"><span class="label"><?= e(t('كلمات الصفحات المهمة (في الرابط)')) ?></span><input class="input" name="hot" dir="auto" value="<?= e($tc['hot']) ?>"></label>
        </div>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="btn" class="rounded" <?= $tc['btn'] ? 'checked' : '' ?>> <?= e(t('اعرض زرار تواصل عائم على الموقع (واتساب + الفورم)')) ?></label>
        <div class="grid sm:grid-cols-3 gap-3">
          <label class="block"><span class="label"><?= e(t('رقم الواتساب')) ?></span><input class="input" name="wa" dir="ltr" value="<?= e($tc['wa']) ?>" placeholder="+20…"></label>
          <label class="block"><span class="label"><?= e(t('كلمة الزرار')) ?></span><input class="input" name="label" maxlength="40" value="<?= e($tc['label']) ?>" placeholder="<?= e(t('تواصل معانا')) ?>"></label>
          <label class="block"><span class="label"><?= e(t('اللون')) ?></span><input class="input !h-10 !p-1" type="color" name="color" value="<?= e($tc['color']) ?>"></label>
        </div>
        <div class="flex gap-2"><button class="btn-primary"><?= e(t('احفظ')) ?></button><span id="ld-tmsg" class="text-xs self-center"></span></div>
      </form>
      <?php $bc = crm_book_cfg(); ?>
      <form id="ld-bform" class="relative card p-5 w-full max-w-2xl mx-auto space-y-3 mt-3" role="dialog" aria-modal="true">
        <h3 class="font-semibold text-ink-900"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1zM4 10h16M8 3v4M16 3v4"/></svg> <?= e(t('صفحة حجز المواعيد')) ?></h3>
        <p class="text-xs text-ink-500"><?= e(t('زي Calendly ببلاش: العميل بيختار ميعاد فاضي، وبيدخل الـ Leads ومعاه اجتماع متسجل ومتابعة في ميعاده. مفيش حجزين في نفس الميعاد. حط {book} في أي قالب رسالة عشان يتبعت اللينك.')) ?></p>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="on" class="rounded" <?= setting('crm_book_on_' . (int)ws('id'), '0') === '1' ? 'checked' : '' ?>> <?= e(t('الحجز شغال')) ?></label>
        <div class="flex flex-wrap gap-1.5"><?php foreach ([0 => t('الأحد'), 1 => t('الاتنين'), 2 => t('التلات'), 3 => t('الأربع'), 4 => t('الخميس'), 5 => t('الجمعة'), 6 => t('السبت')] as $k => $v): ?><label class="cursor-pointer"><input type="checkbox" name="days[]" value="<?= $k ?>" class="peer sr-only" <?= in_array($k, (array)$bc['days'], true) ? 'checked' : '' ?>><span class="inline-block px-2.5 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:bg-brand-600 peer-checked:text-white"><?= e($v) ?></span></label><?php endforeach; ?></div>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
          <label class="block"><span class="label"><?= e(t('من')) ?></span><input type="time" class="input" name="from" value="<?= e($bc['from']) ?>"></label>
          <label class="block"><span class="label"><?= e(t('لحد')) ?></span><input type="time" class="input" name="to" value="<?= e($bc['to']) ?>"></label>
          <label class="block"><span class="label"><?= e(t('مدة الاجتماع (دقيقة)')) ?></span><input type="number" min="15" max="240" class="input" name="len" value="<?= (int)$bc['len'] ?>"></label>
          <label class="block"><span class="label"><?= e(t('متاح لكام يوم قدام')) ?></span><input type="number" min="1" max="60" class="input" name="ahead" value="<?= (int)$bc['ahead'] ?>"></label>
          <label class="block"><span class="label"><?= e(t('أقل وقت قبل الحجز (ساعة)')) ?></span><input type="number" min="0" max="72" class="input" name="notice" value="<?= (int)$bc['notice'] ?>"></label>
        </div>
        <label class="block"><span class="label"><?= e(t('العنوان')) ?></span><input class="input" name="title" maxlength="120" value="<?= e($bc['title']) ?>" placeholder="<?= e(t('احجز مكالمة استشارة مجانية')) ?>"></label>
        <input readonly class="input text-xs font-mono" dir="ltr" data-bcode>
        <div class="flex gap-2"><button class="btn-primary"><?= e(t('احفظ')) ?></button><a class="btn-ghost" target="_blank" rel="noopener" data-bopen><?= e(t('افتح الصفحة')) ?> ↗</a><span id="ld-bmsg" class="text-xs self-center"></span></div>
      </form>
    </div>
    <script>window.LDF = <?= json_encode(['url' => $furl, 'book' => crm_book_url()], JSON_UNESCAPED_UNICODE) ?>;</script>
    <?php endif; ?>
    <?php if ($canManage): ?>
    <div id="ld-pdlg" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <div class="absolute top-0 bottom-0 end-0 w-full sm:w-[640px] bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true">
        <div class="flex items-center gap-2 p-4 border-b border-ink-100"><h3 class="font-semibold text-ink-900 flex-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 00-14.9-4M4 4v4h4M4 13a8 8 0 0014.9 4M20 20v-4h-4"/></svg> <?= e(t('خطط المتابعة')) ?></h3><button type="button" class="btn-ghost !px-2 !py-1" data-close>✕</button></div>
        <p class="px-4 pt-3 text-xs text-ink-500"><?= e(t('كل خطة = خطوات بأيام. الإيميل بيتبعت لوحده من SMTP بتاعك (من التكاملات). الواتساب والمكالمة بيبقوا مهمة للمسؤول في يومها، وضغطة واحدة بتفتح واتساب والرسالة جاهزة. الخطة بتقف لوحدها لما العميل يبقى "بقى عميل" أو "مش مناسب". استخدم {name} و{company} و{me}.')) ?></p>
        <?php if (!smtp_config((int)ws('id'))): ?><p class="mx-4 mt-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800"><?= e(t('مفيش SMTP متظبط، فخطوات الإيميل هتبقى مهام لحد ما تربطه (Gmail ببلاش).')) ?> <a class="link" href="<?= e(url('connectors')) ?>"><?= e(t('التكاملات')) ?> ↗</a></p><?php endif; ?>
        <div id="ld-plans" class="flex-1 overflow-y-auto p-4 space-y-4"></div>
        <div class="p-4 border-t border-ink-100 flex gap-2"><button type="button" id="ld-plan-add" class="btn-ghost">+ <?= e(t('خطة جديدة')) ?></button><button type="button" id="ld-plans-save" class="btn-primary ms-auto"><?= e(t('احفظ')) ?></button></div>
      </div>
    </div>
    <?php endif; ?>
    <div id="ld-tpl-dlg" class="fixed inset-0 z-[60] hidden grid place-items-center p-4" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <div class="relative card p-5 w-full max-w-lg max-h-[92vh] overflow-y-auto space-y-3" role="dialog" aria-modal="true">
        <h3 class="font-semibold text-ink-900"><?= e(t('قوالب الرسائل')) ?></h3>
        <p class="text-xs text-ink-500"><?= e(t('استخدم {name} لاسم العميل و{company} لشركته و{me} لاسمك.')) ?> <?= e(t('و{book} للينك حجز المواعيد.')) ?> <?= e(t('و{site} لرابط موقعك المتتبع.')) ?></p>
        <div id="ld-tpl-rows" class="space-y-3"></div>
        <div class="flex gap-2"><button type="button" id="ld-tpl-add" class="btn-ghost">+ <?= e(t('قالب')) ?></button><button type="button" id="ld-tpl-save" class="btn-primary ms-auto"><?= e(t('احفظ')) ?></button><button type="button" class="btn-ghost" data-close><?= e(t('إلغاء')) ?></button></div>
      </div>
    </div>
    <script>window.LD = <?= json_encode([
        'plans' => crm_plans(), 'chs' => crm_plan_channels(),
        'docUrl' => url('crm_doc'), 'docT' => ['quote' => t('عرض سعر PDF'), 'invoice' => t('فاتورة PDF')],
        'tasks' => function_exists('crm_tasks_open') ? crm_tasks_open($uid) : [], 'ocs' => crm_call_outcomes(), 'mem' => array_values(array_map(function ($m) { return ['id' => (int)$m['id'], 'n' => (string)$m['name']]; }, $members)),
        'tk' => ['none' => t('مفيش مهام. ضيف أول مهمة.'), 'late' => t('متأخرة'), 'pin' => t('ثبّت فوق'), 'unpin' => t('شيل التثبيت'), 'dial' => t('اتصال متتالي'), 'dialNone' => t('مفيش عملاء مستحقين عندهم أرقام.'), 'call' => t('اتصل'), 'skip' => t('تخطي'), 'of' => t('%s من %s'), 'noteP' => t('ملاحظة سريعة (اختياري)'), 'dialDone' => t('خلصت قايمة الاتصال 👏'), 'stop' => t('إنهاء')],
        'today' => (new DateTime('now', new DateTimeZone(site_timezone())))->format('Y-m-d'), 'tz' => site_timezone(), 'qNext' => t('التالي ← %s من %s'), 'qDone' => t('خلصت شغل النهارده 👏'), 'qLast' => t('آخر واحد ✓'), 'tpl' => crm_templates(), 'bookUrl' => setting('crm_book_on_' . (int)ws('id'), '0') === '1' ? crm_book_url() : '', 'meName' => (string)(current_user()['name'] ?? ''), 'kinds' => crm_act_kinds(),
        'stageColors' => crm_stage_colors(), 'pipes' => $pipes, 'pl' => $curPl, 'fields' => crm_custom_fields(),
        'mg' => ['t' => t('دمج عميلين'), 'keep' => t('يفضل'), 'go' => t('ادمج'), 'acts' => t('%s نشاط'), 'hint' => t('اختار القيمة اللي تفضل في كل خانة. كل النشاطات والصفقات والخطط بتتنقل للعميل اللي هيفضل، والتاني بيتمسح.'), 'done' => t('اتدمجوا ✓'), 'need2' => t('حدد عميلين بالظبط.'),
          'f' => ['name' => t('الاسم'), 'company' => t('الشركة'), 'title' => t('المسمى الوظيفي'), 'email' => t('الإيميل'), 'phone' => t('الرقم'), 'stage' => t('المرحلة'), 'value' => t('قيمة الصفقة'), 'owner' => t('المسؤول'), 'note' => t('ملاحظات')]],
        'vc' => ['url' => url('crm_leads') . '&voice=', 'rec' => t('بيسجّل…'), 'saved' => t('اتحفظت الملاحظة الصوتية ✓'), 'noMic' => t('المتصفح مش سامح بالمايك.'), 'lang' => is_rtl() ? 'ar-EG' : 'en-US'],
        'bm' => ['t' => t('إيميل جماعي'), 'sub' => t('العنوان'), 'body' => t('الرسالة'), 'hint' => t('{name} اسمه الأول، {company} شركته، {book} لينك الحجز. اللي مالوش إيميل أو لغى الاشتراك بيتخطى، وكل إيميل فيه رابط إلغاء اشتراك.'), 'go' => t('ابعت'), 'prog' => t('اتبعت %s من %s'), 'done' => t('خلص: اتبعت %s، واتخطى %s، وفشل %s'), 'none' => t('ولا واحد من المحددين عنده إيميل.')],
        'leads' => $leads, 'stages' => $stages, 'sources' => $sources, 'members' => array_column($members, 'name', 'id'), 'me' => $uid, 'url' => url('crm_leads'), 'csrf' => csrf_token(),
        'now' => fmt_date(date('Y-m-d H:i:s'), 'Y-m-d\TH:i'), 'canManage' => $canManage,
        'tr' => ['sdNone' => t('مفيش متابعات مستحقة. شغلك خلص.'), 'noowner' => t('من غير مسؤول'), 'overdue' => t('متأخرة'), 'today' => t('النهارده'), 'saved' => t('اتحفظ ✓'), 'err' => t('حصل خطأ، جرب تاني'),
            'merged' => t('كان موجود، دمجناه في نفس العميل'), 'del' => t('متأكد إنك عاوز تمسح العميل ده؟'), 'pending' => t('الرقم جاي'), 'call' => t('اتصل'),
            'email' => t('إيميل'), 'from' => t('المصدر: %s · اتضاف %s'), 'drop' => t('اسحب هنا'), 'ro' => t('العميل ده مع موظف تاني، مش هتقدر تعدّله.'),
            'noacts' => t('لسه مفيش تواصل مسجّل'), 'later' => t('أجّلها لبكرة'), 'lostQ' => t('ليه مش مناسب؟'), 'lostR' => crm_lost_reasons(), 'undo' => t('تراجع'), 'stageTo' => t('اتنقل لـ %s'), 'deleted' => t('اتمسح %s'), 'nToday' => t('النهارده 4 م'), 'nTom' => t('بكرة 11 ص'), 'n3' => t('بعد 3 أيام'), 'nWeek' => t('بعد أسبوع'), 'nNone' => t('مفيش متابعة'), 'nSet' => t('اتظبطت المتابعة ✓'), 'more' => t('اعرض أكتر (%s فاضلين)'), 'hName' => t('العميل'), 'hStage' => t('المرحلة'), 'hNext' => t('المتابعة'), 'hContact' => t('التواصل'), 'hOwner' => t('المسؤول'), 'hValue' => t('القيمة'), 'laterDone' => t('المتابعة اتأجلت لبكرة 11 الصبح'), 'mine' => t('بتوعي'), 'dueOnly' => t('متابعات مستحقة بس'), 'logged' => t('اتسجّل ✓'), 'impRows' => t('%s صف جاهز'), 'impDone' => t('اتضاف %s جديد، واتدمج %s مكرر، واتساب %s من غير رقم ولا إيميل'),
            'impNeed' => t('لازم عمود رقم أو إيميل'), 'skipCol' => t('— تجاهل —'), 'cold' => t('محدش كلمه من أسبوع'), 'tplN' => t('اسم القالب'), 'tplB' => t('نص الرسالة'), 'rm' => t('شيل'),
            'chat' => t('افتح المحادثة'), 'hot' => t('صفحة مهمة'), 'noVisits' => t('مفيش زيارات. ابعتله رابط متتبع (أو حط {site} في القالب) والكود على موقعك.'), 'linkCopied' => t('الرابط اتنسخ. ابعتهوله.'), 'dStatus' => crm_deal_statuses(), 'dTitle' => t('اسم الصفقة (مثلاً: باقة سنوية)'), 'dValue' => t('القيمة'), 'dClose' => t('متوقع تتقفل'), 'dNone' => t('مفيش صفقات. ضيف صفقة لكل حاجة العميل ممكن يشتريها.'), 'dDel' => t('تمسح الصفقة؟'),
            'mOpen' => t('اتفتح %s مرة'), 'mNotOpen' => t('لسه ما اتفتحش'), 'mClick' => t('ضغط على رابط'), 'sent' => t('اتبعت ✓'), 'score' => t('الأهمية'), 'best' => t('أحسن وقت تكلّمه'), 'firstMsg' => t('أول رسالة مقترحة'), 'useWa' => t('ابعتها واتساب'), 'useMail' => t('ابعتها إيميل'), 'copy' => t('انسخ'), 'copied' => t('اتنسخت ✓'),
            'pNone' => t('مفيش خطة شغالة على العميل ده.'), 'pStart' => t('ابدأ'), 'pPick' => t('اختار خطة…'), 'pNoPlans' => t('لسه مفيش خطط. المدير يعملها من زرار "خطط المتابعة".'),
            'pStep' => t('الخطوة %s من %s'), 'pDue' => t('مستحقة دلوقتي'), 'pWait' => t('ميعادها %s'), 'pDo' => t('نفّذ'), 'pSkip' => t('عدّي'), 'pStop' => t('وقّف الخطة'),
            'pDay' => t('بعد كام يوم'), 'pSubj' => t('عنوان الإيميل'), 'pText' => t('الرسالة'), 'pName' => t('اسم الخطة'), 'pAddStep' => t('+ خطوة'), 'pDel' => t('امسح الخطة'), 'pDelQ' => t('تمسح الخطة دي؟ هتقف على كل العملاء اللي عليها.'),
            'pSample' => t('متابعة عميل جديد'),
            'pS1' => t('أهلاً {name}، معاك {me}. شكرًا لاهتمامك! ممكن أعرف محتاج إيه بالظبط؟'), 'pS2' => t('كلّم {name} واعرف احتياجه'), 'pS3' => t('أهلاً {name}، بعتلك تفاصيل العرض. لو حابب نتكلم أنا موجود.'), 'pS4' => t('أهلاً {name}، لسه مهتم؟ عندنا عرض لحد آخر الأسبوع.'),
            'enrGot' => t('اتكملت: %s'), 'es' => array_map(function ($x) { return $x; }, crm_email_statuses()), 'bDone' => t('اتعمل على %s، واتساب %s'), 'askTag' => t('اسم القايمة؟'),
            'bConfirm' => t('هيتصرف لحد %s كريديت من Apollo. تكمل؟'), 'pickSeq' => t('اختار الـ Sequence'), 'enrNone' => t('Apollo مالقاش بيانات جديدة.'), 'enrPend' => t('الرقم جاي من Apollo خلال دقايق.'), 'loading' => t('بنحمّل…'),
            'noSeq' => t('مفيش Sequences في حسابك على Apollo. اعمل واحد من apollo.io الأول.'), 'noAcc' => t('اربط إيميل في Apollo عشان الـ Sequence يبعت منه.'), 'seqDone' => t('اتضاف للـ Sequence ✓'), 'needEmail' => t('العميل ده مالوش إيميل. كمّل بياناته الأول.'),
            'fl' => ['title' => t('المسمى الوظيفي'), 'linkedin' => 'LinkedIn', 'company' => t('الشركة'), 'email' => t('الإيميل'), 'phone' => t('الرقم'), 'country' => t('الدولة')], 'sugLoading' => t('بندوّر في الرسائل والتعليقات…'), 'sugNone' => t('مفيش مقترحين دلوقتي. لما حد يسأل عن السعر أو يكتب رقمه هيظهر هنا.'),
            'sugGo' => t('ضيف %s للـ Leads'), 'sugDone' => t('اتضاف %s عميل جديد'), 'whyAi' => t('نية شراء'), 'whyContact' => t('فيه رقم أو إيميل'), 'whyPrice' => t('سؤال عن السعر'), 'whyCta' => t('رد على دعوة المنشور'),
            'autoOn' => t('اتفعّل: اتضاف %s دلوقتي، والباقي كل 6 ساعات.'), 'autoOff' => t('الإضافة الأوتوماتيك اتقفلت.'), 'scanning' => t('بنحلّل…'), 'scanned' => t('اتحلّل %s محادثة'), 'cmt' => t('تعليق'), 'msgK' => t('رسالة'), 'f' => ['name' => t('الاسم'), 'phone' => t('الرقم'), 'email' => t('الإيميل'), 'company' => t('الشركة'), 'title' => t('المسمى الوظيفي'), 'country' => t('الدولة'), 'tags' => t('تاجات'), 'note' => t('ملاحظات')]],
    ], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-crm_leads.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
