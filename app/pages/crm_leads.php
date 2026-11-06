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
        'next_note' => (string)$l['next_note'], 'created' => $l['created_at'], 'last' => (string)($l['last_contact_at'] ?? ''), 'src_url' => (string)($l['src_url'] ?? ''), 'es' => (string)($l['email_status'] ?? ''), 'edit' => crm_can_edit($l)];
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
    $l = crm_lead((int)($_POST['id'] ?? 0));
    if ($l && $action === 'acts') { $ac = crm_acts((int)$l['id']); $mails = crm_mails((int)$l['id']); $json(['ok' => true, 'acts' => $ac, 'run' => crm_lead_run((int)$l['id']), 'intel' => crm_intel($l, $ac), 'mails' => $mails, 'deals' => crm_deals((int)$l['id']), 'visits' => crm_visits((int)$l['id'])]); }
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
        try { crm_send_email($l, (string)($_POST['subject'] ?? ''), trim((string)($_POST['body'] ?? ''))); }
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
    if ($action === 'log') {
        // تسجيل تواصل؛ أول تواصل مع عميل جديد بينقله لـ "اتكلمنا معاه" لوحده
        $k = (string)($_POST['kind'] ?? '');
        if (!isset(crm_act_kinds()[$k]) || $k === 'stage') $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
        $oc = (string)($_POST['outcome'] ?? ''); $ocs = crm_call_outcomes();
        $body = trim((isset($ocs[$oc]) ? '[' . $ocs[$oc] . '] ' : '') . (string)($_POST['body'] ?? ''));
        crm_act_add((int)$l['id'], $k, $body);
        if ($k === 'call' && in_array($oc, ['no_answer', 'busy'], true)) {
            // محاولة تانية بكرة في نفس الوقت تقريبًا
            db()->prepare('UPDATE crm_leads SET next_at = ?, next_note = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', strtotime('+1 day')), t('محاولة اتصال تانية (%s)', $ocs[$oc]), (int)$l['id']]);
        }
        if ($k === 'call' && $oc === 'wrong') db()->prepare('UPDATE crm_leads SET phone = NULL WHERE id = ?')->execute([(int)$l['id']]);
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

try { crm_fix_stored_phones(); } catch (Throwable $t) { /* مش مهم */ }
try { crm_plan_tick(30); } catch (Throwable $t) { /* الـ cron هيكمّل */ }
try { crm_sla_tick(); } catch (Throwable $t) { /* الـ cron هيكمّل */ }
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
render(t('العملاء المحتملين'), 'crm_leads', function () use ($leads, $stages, $sources, $members, $canManage, $uid, $adsOn, $lastSync, $dial) {
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
    $stageIcon = ['new' => '🆕', 'contacted' => '💬', 'qualified' => '⭐', 'proposal' => '📄', 'won' => '🏆', 'lost' => '✕'];
    ?>
    <!-- الشريط العلوي: أرقام صغيرة + الأزرار المهمة بس -->
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="ld-stat"><span class="text-ink-500"><?= e(t('العملاء')) ?></span> <b><?= count($leads) ?></b></span>
        <button type="button" id="ld-due-tile" class="ld-stat <?= $due ? '!border-amber-300 !bg-amber-50 text-amber-800' : '' ?>" title="<?= e(t('اعرض المستحقين بس')) ?>">⏰ <span class="text-ink-500"><?= e(t('مستحقين النهارده')) ?></span> <b><?= $due ?></b></button>
        <span class="ld-stat">💰 <span class="text-ink-500"><?= e(t('مفتوح')) ?></span> <b><?= e(number_format($pipe)) ?></b></span>
        <span class="ld-stat">🏆 <b class="text-lime-700"><?= $won ?></b> <span class="text-ink-400">· <?= count($leads) ? round($won / count($leads) * 100) : 0 ?>%</span></span>
      </div>
      <div class="flex items-center gap-2 ms-auto">
        <button type="button" id="ld-queue" class="btn-primary hidden shadow-lg shadow-brand-500/20">▶ <?= e(t('شغل النهارده')) ?> <span id="ld-queue-n" class="ms-1 rounded-full bg-white/25 px-1.5 text-xs"></span></button>
        <button type="button" id="ld-sug-btn" class="btn-ghost relative">✨ <span class="hidden sm:inline"><?= e(t('عملاء مقترحين')) ?></span><span id="ld-sug-n" class="hidden absolute -top-1.5 -end-1.5 min-w-[18px] h-[18px] rounded-full bg-lime-500 text-white text-[10px] font-bold grid place-items-center px-1 animate-pulse"></span></button>
      <details class="relative" id="ld-tools"><summary class="btn-ghost list-none cursor-pointer select-none !px-2.5" title="<?= e(t('أدوات')) ?>" aria-label="<?= e(t('أدوات')) ?>">⚙️ <span class="hidden xl:inline"><?= e(t('أدوات')) ?></span></summary>
        <div class="absolute end-0 mt-1 z-40 w-56 rounded-xl border border-ink-200 bg-white shadow-lg p-1 flex flex-col">
      <?php if ($canManage): ?><button type="button" id="ld-plans-btn" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg">🔁 <?= e(t('خطط المتابعة')) ?></button><?php endif; ?>
      <?php if ($canManage): ?><button type="button" id="ld-sla-btn" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg">⏱ <?= e(t('سرعة الرد')) ?></button><?php endif; ?>
      <?php if ($canManage): ?><button type="button" id="ld-form-btn" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg">🌐 <?= e(t('فورم الموقع')) ?></button><?php endif; ?>
      <button type="button" id="ld-imp-btn" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg">⇪ <?= e(t('استورد عملاء')) ?></button>
      <a class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg" href="<?= e(url('crm_leads', ['export' => 'csv'])) ?>">CSV</a>
      <?php if ($adsOn): ?><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="sync_ads">
        <button class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg" title="<?= e($lastSync ? t('آخر سحب: %s', fmt_date($lastSync)) : t('لسه ما اتسحبش')) ?>" onclick="this.disabled=true;this.form.submit()">⇣ <?= e(t('اسحب من الإعلانات')) ?></button></form><?php endif; ?>
      <?php if (crm_has_key() && can('crm.reveal')): ?><button type="button" id="ld-stale" class="w-full text-start px-3 py-2 text-sm hover:bg-ink-50 rounded-lg" title="<?= e(t('لحد 25 عميل في المرة، بيصرف كريديت')) ?>">✦ <?= e(t('كمّل بيانات الناقصين وحدّث القديمة (أكتر من 3 شهور) من Apollo')) ?></button><?php endif; ?>
        </div></details>
        <button type="button" id="ld-add-btn" class="btn-primary">+ <span class="hidden sm:inline"><?= e(t('عميل جديد')) ?></span></button>
      </div>
    </div>

    <?php if ($canManage && !crm_has_key()): ?>
    <div class="rounded-xl border border-dashed border-ink-200 px-3 py-2 mb-3 text-xs text-ink-500 flex items-center gap-2">🔎 <?= e(t('اربط Apollo عشان تظهر "الشركات المستهدفة" وتكشف أرقام صناع القرار.')) ?> <a class="link ms-auto" href="<?= e(url('crm')) ?>"><?= e(t('اربطه')) ?> ↗</a></div>
    <?php endif; ?>

    <?php $nfs = crm_notifs($uid, 20); $nfU = count(array_filter($nfs, function ($x) { return !(int)$x['seen']; })); if ($nfs): ?>
    <details id="notifs" class="card mb-4 group" <?= $nfU ? 'open' : '' ?>>
      <summary class="cursor-pointer list-none flex items-center gap-2 px-4 py-2.5 text-sm"><span>🔔 <b><?= e(t('تنبيهات العملاء')) ?></b></span><?php if ($nfU): ?><span class="rounded-full bg-rose-600 text-white text-[10px] px-1.5"><?= $nfU ?></span><?php endif; ?>
        <button type="button" id="nf-seen" class="link text-xs ms-auto <?= $nfU ? '' : 'hidden' ?>"><?= e(t('علّمهم مقروءين')) ?></button></summary>
      <div class="divide-y divide-ink-100 border-t border-ink-100 max-h-64 overflow-y-auto"><?php foreach ($nfs as $nf): ?>
        <a href="#lead-<?= (int)$nf['lead_id'] ?>" data-nf-lead="<?= (int)$nf['lead_id'] ?>" class="flex items-center gap-3 px-4 py-2 text-sm hover:bg-ink-50 <?= (int)$nf['seen'] ? 'text-ink-500' : 'text-ink-900 font-medium bg-rose-50/40' ?>"><span class="flex-1"><?= e($nf['body']) ?></span><span class="text-[11px] text-ink-400 shrink-0"><?= e(fmt_date($nf['created_at'], 'd/m h:i A')) ?></span></a>
      <?php endforeach; ?></div>
    </details>
    <?php endif; ?>

    <!-- المراحل = القمع: عدد كل مرحلة ونسبة اللي عدّوا من اللي قبلها، وبتفلتر بضغطة -->
    <div id="ld-stages" class="ld-funnel flex sm:grid sm:grid-cols-7 gap-1.5 mb-4 overflow-x-auto snap-x pb-1 -mx-1 px-1" role="tablist">
      <button type="button" class="ld-seg on" data-s=""><span class="ld-seg-l"><?= e(t('الكل')) ?></span><b class="ld-seg-n"><?= count($leads) ?></b></button>
      <?php foreach ($stages as $k => $lbl): $ix = array_search($k, $order, true); $prev = $ix ? $reach[$order[$ix - 1]] : 0; ?>
        <button type="button" class="ld-seg" data-s="<?= e($k) ?>" data-stage="<?= e($k) ?>"><span class="ld-seg-l"><?= $stageIcon[$k] ?? '' ?> <?= e($lbl) ?></span><span class="flex items-baseline gap-1.5"><b class="ld-seg-n" data-cnt="<?= e($k) ?>">0</b><?php if ($ix && $prev): ?><span class="ld-seg-p" title="<?= e(t('النسبة جنب كل مرحلة = كام في المية عدّى من المرحلة اللي قبلها.')) ?>">↗ <?= round($reach[$k] / $prev * 100) ?>%</span><?php endif; ?></span></button>
      <?php endforeach; ?>
    </div>

    <!-- البحث + فلتر واحد + الترتيب + طريقة العرض -->
    <div class="flex flex-wrap items-center gap-2 mb-2">
      <div class="relative flex-1 min-w-[200px]"><span class="absolute inset-y-0 start-3 grid place-items-center text-ink-400 pointer-events-none">⌕</span>
        <input id="ld-q" type="search" class="input !ps-8" placeholder="<?= e(t('دوّر بالاسم، الشركة، الرقم، الإيميل…')) ?>  ( / )"></div>
      <details class="relative" id="ld-filt"><summary class="btn-ghost list-none cursor-pointer select-none">⚲ <?= e(t('فلترة')) ?> <span id="ld-filt-n" class="hidden ms-1 rounded-full bg-brand-600 text-white text-[10px] px-1.5"></span></summary>
        <div class="absolute end-0 mt-1 z-40 w-72 rounded-2xl border border-ink-200 bg-white shadow-xl p-3 space-y-2.5">
          <label class="block"><span class="text-[11px] text-ink-500"><?= e(t('المصدر')) ?></span><select id="ld-src" class="input !py-1.5 text-sm"><option value=""><?= e(t('كل المصادر')) ?></option><?php foreach ($sources as $k => $lbl): if (empty($bySrc[$k])) continue; ?><option value="<?= e($k) ?>"><?= e($lbl) ?> (<?= (int)$bySrc[$k] ?>)</option><?php endforeach; ?></select></label>
          <label class="block"><span class="text-[11px] text-ink-500"><?= e(t('المسؤول')) ?></span><select id="ld-own" class="input !py-1.5 text-sm"><option value=""><?= e(t('كل المسؤولين')) ?></option><option value="<?= $uid ?>"><?= e(t('بتوعي')) ?></option><option value="0"><?= e(t('من غير مسؤول')) ?></option>
            <?php if ($canManage): foreach ($members as $m): if ((int)$m['id'] === $uid) continue; ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; endif; ?></select></label>
          <?php if ($tagNames): ?><label class="block"><span class="text-[11px] text-ink-500"><?= e(t('القايمة')) ?></span><select id="ld-tag" class="input !py-1.5 text-sm"><option value=""><?= e(t('كل القوايم')) ?></option><?php foreach ($tagNames as $tg): ?><option value="<?= e($tg) ?>"><?= e($tg) ?></option><?php endforeach; ?></select></label><?php endif; ?>
          <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" id="ld-due" class="rounded"> ⏰ <?= e(t('متابعات مستحقة بس')) ?></label>
          <?php if ($cold): ?><button type="button" id="ld-cold" class="w-full text-start rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-xs text-sky-800 hover:bg-sky-100">🧊 <?= e(t('%s عميل محدش كلمه من أسبوع ومالوش متابعة. اعرضهم', $cold)) ?></button><?php endif; ?>
          <button type="button" id="ld-filt-clear" class="text-xs text-ink-500 hover:text-red-600"><?= e(t('امسح الفلاتر')) ?></button>
        </div></details>
      <select id="ld-sort" class="input !w-auto"><option value=""><?= e(t('ترتيب: المتابعات الأول')) ?></option><option value="score"><?= e(t('ترتيب: الأهم الأول')) ?></option><option value="new"><?= e(t('ترتيب: الأحدث')) ?></option></select>
      <div class="inline-flex rounded-xl border border-ink-200 bg-white p-0.5" role="group">
        <button type="button" data-v="list" class="ld-view px-2.5 py-1.5 text-sm rounded-lg" title="<?= e(t('قائمة')) ?>">☰</button>
        <button type="button" data-v="board" class="ld-view px-2.5 py-1.5 text-sm rounded-lg" title="<?= e(t('لوحة')) ?>">▦</button>
      </div>
    </div>
    <div id="ld-views" class="flex items-center gap-1 mb-2 overflow-x-auto text-sm" role="tablist">
      <?php foreach (['all' => ['', t('الكل')], 'mine' => ['👤', t('بتوعي')], 'due' => ['⏰', t('مستحقين')], 'hot' => ['🔥', t('سخنين')], 'cold' => ['🧊', t('باردين')], 'fresh' => ['🆕', t('جداد النهارده')], 'unowned' => ['🙋', t('من غير مسؤول')]] as $vk => $vv): if ($vk === 'unowned' && !$canManage) continue; ?>
        <button type="button" data-view="<?= $vk ?>" class="ld-vtab shrink-0 px-3 py-1.5 rounded-full text-ink-600 hover:bg-white hover:shadow-sm transition"><?= $vv[0] ?> <?= e($vv[1]) ?> <span class="ld-vn text-[11px] text-ink-400"></span></button>
      <?php endforeach; ?>
    </div>
    <div id="ld-chips" class="flex flex-wrap gap-1.5 mb-3 min-h-[4px]"></div>

    <?php if (!$leads): ?>
      <div id="ld-empty"><?= an_empty(t('لسه مفيش Leads'), t('اكشف أشخاص من الشركات المستهدفة، أو ضيف Lead بإيدك.'), '🧲') ?></div>
    <?php endif; ?>
    <div id="ld-bulk" class="hidden sticky top-16 z-30 mb-3 flex flex-wrap items-center gap-2 rounded-2xl border border-brand-200 bg-brand-50 px-3 py-2 shadow-sm">
      <label class="inline-flex items-center gap-1.5 text-sm"><input type="checkbox" id="ld-all" class="rounded"> <b id="ld-bn">0</b> <?= e(t('محدد')) ?></label>
      <select id="ld-b-stage" class="input !w-auto !py-1 text-xs"><option value=""><?= e(t('غيّر المرحلة…')) ?></option><?php foreach ($stages as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select>
      <?php if ($canManage): ?><select id="ld-b-owner" class="input !w-auto !py-1 text-xs"><option value=""><?= e(t('وزّع على…')) ?></option><option value="0"><?= e(t('من غير مسؤول')) ?></option><?php foreach ($members as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
      <select id="ld-b-plan" class="input !w-auto !py-1 text-xs"><option value="">🔁 <?= e(t('ابدأ خطة متابعة…')) ?></option><?php foreach (crm_plans() as $pl): ?><option value="<?= (int)$pl['id'] ?>"><?= e($pl['name']) ?></option><?php endforeach; ?></select>
      <button type="button" id="ld-b-tag" class="btn-ghost !py-1 !text-xs">🏷 <?= e(t('ضيف لقايمة')) ?></button>
      <?php if (crm_has_key()): ?>
        <?php if (can('crm.reveal')): ?><button type="button" id="ld-b-enrich" class="btn-ghost !py-1 !text-xs">✦ <?= e(t('كمّل من Apollo')) ?></button><?php endif; ?>
        <button type="button" id="ld-b-seq" class="btn-ghost !py-1 !text-xs">📧 Sequence</button>
      <?php endif; ?>
      <button type="button" id="ld-b-clear" class="ms-auto text-xs text-ink-500 hover:text-ink-900">✕</button>
    </div>
    <div id="ld-list" class="card divide-y divide-ink-100 overflow-hidden"></div>
    <div id="ld-board" class="hidden grid grid-flow-col auto-cols-[minmax(250px,1fr)] gap-3 overflow-x-auto pb-3 snap-x"></div>
    <div id="ld-more" class="hidden text-center mt-3"><button type="button" class="btn-ghost"><?= e(t('اعرض أكتر')) ?> <span class="text-ink-400"></span></button></div>
    <div id="ld-none" class="hidden text-center py-14"><div class="text-4xl mb-2">🔍</div><div class="text-sm text-ink-600"><?= e(t('مفيش عملاء بالشكل ده')) ?></div><button type="button" id="ld-none-clear" class="link text-xs mt-2"><?= e(t('امسح الفلاتر')) ?></button></div>

    <div id="ld-drawer" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
      <div class="ld-fade absolute inset-0 bg-ink-900/40 backdrop-blur-[1px]" data-close></div>
      <form id="ld-form" class="ld-sheet absolute top-0 bottom-0 end-0 w-full sm:w-[520px] bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="ld-d-title">
        <input type="hidden" name="id"><input type="hidden" name="action" value="save">
        <div class="px-4 pt-4 border-b border-ink-100 bg-gradient-to-b from-brand-50/70 to-white">
          <div class="flex items-start gap-3">
            <div id="ld-d-av" class="shrink-0"></div>
            <div class="min-w-0 flex-1"><div class="flex items-center gap-2 min-w-0"><div id="ld-d-title" class="font-bold text-lg text-ink-900 truncate"></div><span id="ld-d-score"></span></div><div id="ld-d-sub" class="text-xs text-ink-500 truncate"></div><div id="ld-d-stage" class="mt-1"></div></div>
            <button type="button" id="ld-q-next" class="hidden btn-primary !py-1.5 !text-xs"></button>
            <button type="button" class="btn-ghost" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button>
          </div>
          <div id="ld-quick" class="flex flex-wrap gap-1.5 mt-3"></div>
          <nav class="flex gap-1 mt-3 -mb-px overflow-x-auto" role="tablist" id="ld-tabs">
            <?php foreach (['ov' => ['✨', t('نظرة عامة')], 'ct' => ['💬', t('تواصل')], 'dl' => ['💼', t('صفقات ومتابعة')], 'dt' => ['🗂', t('البيانات')]] as $tk => $tv): ?>
              <button type="button" role="tab" data-tabbtn="<?= $tk ?>" class="ld-tabbtn shrink-0 px-3 py-2 text-sm border-b-2 border-transparent text-ink-500 hover:text-ink-900 transition"><?= $tv[0] ?> <?= e($tv[1]) ?><span class="ld-tabn ms-1 text-[10px] text-ink-400"></span></button>
            <?php endforeach; ?>
          </nav>
        </div>
        <div class="ld-panel flex-1 min-h-0 overflow-y-auto p-4 space-y-4 hidden" data-tab="ov" role="tabpanel">
          <div id="ld-intel" class="rounded-xl bg-ink-50 border border-ink-200 p-3 text-xs space-y-1"></div>
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
        </div>
        <div class="ld-panel flex-1 min-h-0 overflow-y-auto p-4 space-y-4 hidden" data-tab="ct" role="tabpanel">
          <div id="ld-tpl" class="hidden rounded-xl border border-lime-200 bg-lime-50/40 p-3 space-y-2">
            <div class="flex items-center gap-2"><span class="text-sm font-semibold text-ink-900">💬 <?= e(t('ابعت رسالة جاهزة')) ?></span>
              <?php if ($canManage): ?><button type="button" id="ld-tpl-edit" class="link text-xs ms-auto"><?= e(t('عدّل القوالب')) ?></button><?php endif; ?></div>
            <div id="ld-tpl-list" class="flex flex-wrap gap-1.5"></div>
            <p class="text-[11px] text-ink-500"><?= e(t('بيفتح واتساب بالرسالة مكتوبة، وبيتسجل في سجل التواصل لوحده.')) ?></p>
          </div>
          <div id="ld-mail" class="hidden rounded-xl border border-ink-200 p-3 space-y-2">
            <div class="flex items-center gap-2"><span class="text-sm font-semibold text-ink-900">✉️ <?= e(t('ابعت إيميل من هنا')) ?></span><button type="button" id="ld-mail-tg" class="link text-xs ms-auto"><?= e(t('اكتب')) ?></button></div>
            <div id="ld-mail-f" class="hidden space-y-1.5"><input id="ld-mail-s" class="input text-sm" maxlength="255" placeholder="<?= e(t('العنوان')) ?>"><textarea id="ld-mail-b" rows="5" class="input text-sm" dir="auto"></textarea>
              <button type="button" id="ld-mail-go" class="btn-primary !py-1.5 !text-xs w-full"><?= e(t('ابعت')) ?></button>
              <p class="text-[11px] text-ink-500"><?= e(t('بيتبعت من SMTP بتاعك. هتعرف لو فتحه أو ضغط على رابط (الفتح تقريبي: بعض برامج الإيميل بتمنع الصور).')) ?></p></div>
            <div id="ld-mails" class="space-y-1"></div>
          </div>
          <div class="rounded-xl border border-ink-200 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900">🕓 <?= e(t('سجل التواصل')) ?></div>
            <div class="flex flex-wrap gap-1.5" id="ld-log-kinds"><?php foreach (crm_act_kinds() as $k => $v): if ($k === 'stage') continue; ?><button type="button" class="chip text-xs" data-k="<?= e($k) ?>"><?= $v[0] ?> <?= e($v[1]) ?></button><?php endforeach; ?></div>
            <div id="ld-oc" class="hidden flex flex-wrap gap-1.5 items-center"><span class="text-[11px] text-ink-500">📞 <?= e(t('نتيجة المكالمة؟')) ?></span><?php foreach (crm_call_outcomes() as $k => $v): ?><button type="button" class="chip text-xs" data-oc="<?= e($k) ?>"><?= e($v) ?></button><?php endforeach; ?></div>
            <div class="flex gap-2"><input id="ld-log-body" class="input text-sm" maxlength="2000" placeholder="<?= e(t('حصل إيه؟ (اختياري) واختار النوع')) ?>"></div>
            <ol id="ld-acts" class="space-y-2 max-h-72 overflow-y-auto"></ol>
          </div>
        </div>
        <div class="ld-panel flex-1 min-h-0 overflow-y-auto p-4 space-y-4 hidden" data-tab="dl" role="tabpanel">
          <div id="ld-deals" class="rounded-xl border border-ink-200 p-3 space-y-2">
            <div class="flex items-center gap-2"><span class="text-sm font-semibold text-ink-900">💼 <?= e(t('الصفقات')) ?></span><button type="button" id="ld-deal-add" class="link text-xs ms-auto">+ <?= e(t('صفقة')) ?></button></div>
            <div id="ld-deal-list" class="space-y-1.5"></div>
          </div>
          <div id="ld-plan" class="rounded-xl border border-sky-200 bg-sky-50/40 p-3 space-y-2">
            <div class="text-sm font-semibold text-ink-900">🔁 <?= e(t('خطة المتابعة')) ?></div>
            <div id="ld-plan-body" class="text-xs"></div>
          </div>
        </div>
        <div class="ld-panel flex-1 min-h-0 overflow-y-auto p-4 space-y-4 hidden" data-tab="dt" role="tabpanel">
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
          <div id="ld-visits" class="rounded-xl border border-ink-200 p-3 space-y-2">
            <div class="flex items-center gap-2"><span class="text-sm font-semibold text-ink-900">🌐 <?= e(t('زياراته لموقعك')) ?></span><button type="button" id="ld-tl" class="link text-xs ms-auto"><?= e(t('اعمل رابط متتبع')) ?></button></div>
            <div id="ld-tl-box" class="hidden flex gap-1.5"><input id="ld-tl-u" class="input !py-1 text-xs" dir="ltr" placeholder="https://" value="<?= e((string)setting('crm_site_url_' . (int)ws('id'), '')) ?>"><button type="button" id="ld-tl-go" class="btn-primary !py-1 !text-xs">⧉</button></div>
            <div id="ld-visit-list" class="space-y-1"></div>
          </div>
          <div id="ld-meta" class="text-xs text-ink-400"></div>
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
        <h3 class="font-semibold text-ink-900">🌐 <?= e(t('فورم الموقع')) ?></h3>
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
        <h3 class="font-semibold text-ink-900">⏱ <?= e(t('سرعة الرد على العملاء الجداد')) ?></h3>
        <p class="text-xs text-ink-500"><?= e(t('أسرع رد = عملاء أكتر. لو عميل جديد عدّى الوقت ده ومحدش كلّمه، المسؤول بيوصله تنبيه في الجرس (ولو مالوش مسؤول، المديرين). ولو عدّى وقت النقل، بيتحوّل لأقل موظف عنده شغل.')) ?></p>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block"><span class="label"><?= e(t('نبّه بعد (دقيقة)')) ?></span><input type="number" min="0" max="1440" name="mins" class="input" value="<?= (int)$sl['mins'] ?>"><span class="text-[11px] text-ink-400"><?= e(t('0 = من غير تنبيه')) ?></span></label>
          <label class="block"><span class="label"><?= e(t('انقله لحد تاني بعد (ساعة)')) ?></span><input type="number" min="0" max="72" name="move_hours" class="input" value="<?= (int)$sl['move_hours'] ?>"><span class="text-[11px] text-ink-400"><?= e(t('0 = مايتنقلش')) ?></span></label>
        </div>
        <div class="flex gap-2"><button class="btn-primary"><?= e(t('احفظ')) ?></button><span id="ld-slamsg" class="text-xs self-center"></span></div>
      </form>
      <?php $tc = crm_track_cfg((int)ws('id')); ?>
      <form id="ld-tform" class="relative card p-5 w-full max-w-2xl mx-auto space-y-3 mt-3">
        <h3 class="font-semibold text-ink-900">📍 <?= e(t('كود الموقع: تتبع الزيارات + زرار عائم')) ?></h3>
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
        <h3 class="font-semibold text-ink-900">📅 <?= e(t('صفحة حجز المواعيد')) ?></h3>
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
        <div class="flex items-center gap-2 p-4 border-b border-ink-100"><h3 class="font-semibold text-ink-900 flex-1">🔁 <?= e(t('خطط المتابعة')) ?></h3><button type="button" class="btn-ghost !px-2 !py-1" data-close>✕</button></div>
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
        'tz' => site_timezone(), 'qNext' => t('التالي ← %s من %s'), 'qDone' => t('خلصت شغل النهارده 👏'), 'qLast' => t('آخر واحد ✓'), 'tpl' => crm_templates(), 'bookUrl' => setting('crm_book_on_' . (int)ws('id'), '0') === '1' ? crm_book_url() : '', 'meName' => (string)(current_user()['name'] ?? ''), 'kinds' => crm_act_kinds(),
        'leads' => $leads, 'stages' => $stages, 'sources' => $sources, 'members' => array_column($members, 'name', 'id'), 'me' => $uid, 'url' => url('crm_leads'), 'csrf' => csrf_token(),
        'now' => fmt_date(date('Y-m-d H:i:s'), 'Y-m-d\TH:i'), 'canManage' => $canManage,
        'tr' => ['noowner' => t('من غير مسؤول'), 'overdue' => t('متأخرة'), 'today' => t('النهارده'), 'saved' => t('اتحفظ ✓'), 'err' => t('حصل خطأ، جرب تاني'),
            'merged' => t('كان موجود، دمجناه في نفس العميل'), 'del' => t('متأكد إنك عاوز تمسح العميل ده؟'), 'pending' => t('الرقم جاي'), 'call' => t('اتصل'),
            'email' => t('إيميل'), 'from' => t('المصدر: %s · اتضاف %s'), 'drop' => t('اسحب هنا'), 'ro' => t('العميل ده مع موظف تاني، مش هتقدر تعدّله.'),
            'noacts' => t('لسه مفيش تواصل مسجّل'), 'later' => t('أجّلها لبكرة'), 'undo' => t('تراجع'), 'stageTo' => t('اتنقل لـ %s'), 'deleted' => t('اتمسح %s'), 'nToday' => t('النهارده 4 م'), 'nTom' => t('بكرة 11 ص'), 'n3' => t('بعد 3 أيام'), 'nWeek' => t('بعد أسبوع'), 'nNone' => t('مفيش متابعة'), 'nSet' => t('اتظبطت المتابعة ✓'), 'more' => t('اعرض أكتر (%s فاضلين)'), 'hName' => t('العميل'), 'hStage' => t('المرحلة'), 'hNext' => t('المتابعة'), 'hContact' => t('التواصل'), 'hOwner' => t('المسؤول'), 'hValue' => t('القيمة'), 'laterDone' => t('المتابعة اتأجلت لبكرة 11 الصبح'), 'mine' => t('بتوعي'), 'dueOnly' => t('متابعات مستحقة بس'), 'logged' => t('اتسجّل ✓'), 'impRows' => t('%s صف جاهز'), 'impDone' => t('اتضاف %s جديد، واتدمج %s مكرر، واتساب %s من غير رقم ولا إيميل'),
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
