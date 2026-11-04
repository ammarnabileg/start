<?php
/** أدوات الفريق في الرسائل والتعليقات: إسناد المحادثات، حالتها، ملاحظات داخلية، وردود محفوظة */

function conv_states(): array
{
    return ['open' => t('مفتوحة'), 'pending' => t('بانتظار العميل'), 'closed' => t('مغلقة')];
}

/** [conv_id => ['assignee_id','state']] لكل محادثات الشركة */
function conv_meta_all(int $wid): array
{
    $st = db()->prepare("SELECT conv_id, assignee_id, state FROM conv_meta WHERE workspace_id = ?");
    $st->execute([$wid]);
    $o = [];
    foreach ($st->fetchAll() as $r) $o[(string)$r['conv_id']] = $r;
    return $o;
}

function conv_meta_set(int $wid, string $conv, array $vals): void
{
    $cur = conv_meta_all($wid)[$conv] ?? ['assignee_id' => null, 'state' => 'open'];
    $a = array_key_exists('assignee_id', $vals) ? ($vals['assignee_id'] ?: null) : $cur['assignee_id'];
    $s = isset($vals['state'], conv_states()[$vals['state']]) ? $vals['state'] : $cur['state'];
    db()->prepare("INSERT INTO conv_meta (workspace_id, conv_id, assignee_id, state) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE assignee_id = VALUES(assignee_id), state = VALUES(state)")
        ->execute([$wid, $conv, $a, $s]);
}

/** أعضاء الشركة النشطين [id => name] */
function ws_members(int $wid): array
{
    $st = db()->prepare("SELECT u.id, u.name FROM workspace_users wu JOIN users u ON u.id = wu.user_id WHERE wu.workspace_id = ? AND wu.status = 'active' ORDER BY u.name");
    $st->execute([$wid]);
    $o = [];
    foreach ($st->fetchAll() as $r) $o[(int)$r['id']] = (string)$r['name'];
    return $o;
}

function conv_notes(int $wid, string $conv): array
{
    $st = db()->prepare("SELECT n.id, n.body, n.created_at, n.user_id, u.name FROM conv_notes n LEFT JOIN users u ON u.id = n.user_id WHERE n.workspace_id = ? AND n.conv_id = ? ORDER BY n.id");
    $st->execute([$wid, $conv]);
    return $st->fetchAll();
}

function saved_replies(int $wid): array
{
    $st = db()->prepare("SELECT id, shortcut, body FROM saved_replies WHERE workspace_id = ? ORDER BY uses DESC, shortcut");
    $st->execute([$wid]);
    return $st->fetchAll();
}

/** إجراءات الفريق (JSON) — مشتركة بين صفحة الرسائل والتعليقات */
function team_post(string $act): void
{
    header('Content-Type: application/json; charset=utf-8');
    $J = function (array $d, int $c = 200) { http_response_code($c); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    $wid = (int)ws('id');
    $conv = mb_substr(trim((string)($_POST['conv'] ?? '')), 0, 120);
    $uid = (int)(current_user()['id'] ?? 0);
    if ($act === 'team_assign' || $act === 'team_state') {
        if ($conv === '') $J(['error' => t('طلب غير صحيح.')], 400);
        if ($act === 'team_assign') {
            $to = (int)($_POST['user'] ?? 0);
            if ($to && !isset(ws_members($wid)[$to])) $J(['error' => t('طلب غير صحيح.')], 400);
            conv_meta_set($wid, $conv, ['assignee_id' => $to]);
            log_activity('inbox.assign', $conv . ' → ' . $to);
        } else {
            conv_meta_set($wid, $conv, ['state' => (string)($_POST['state'] ?? 'open')]);
        }
        $J(['ok' => true]);
    }
    if ($act === 'team_note') {
        $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 2000);
        if ($conv === '' || $body === '') $J(['error' => t('اكتب الملاحظة أولًا.')], 422);
        db()->prepare("INSERT INTO conv_notes (workspace_id, conv_id, user_id, body) VALUES (?, ?, ?, ?)")->execute([$wid, $conv, $uid ?: null, $body]);
        $J(['ok' => true, 'id' => (int)db()->lastInsertId(), 'name' => (string)(current_user()['name'] ?? ''), 'time' => fmt_date(gmdate('c'), 'd/m h:i A')]);
    }
    if ($act === 'team_note_del') {
        db()->prepare("DELETE FROM conv_notes WHERE id = ? AND workspace_id = ? AND (user_id = ? OR ? = 1)")->execute([(int)($_POST['id'] ?? 0), $wid, $uid, is_ws_owner() ? 1 : 0]);
        $J(['ok' => true]);
    }
    if ($act === 'reply_save') {
        $sc = preg_replace('/[^\p{L}\p{N}_-]+/u', '', (string)($_POST['shortcut'] ?? ''));
        $body = mb_substr(trim((string)($_POST['body'] ?? '')), 0, 2000);
        if ($sc === '' || $body === '') $J(['error' => t('اكتب الاختصار والرد.')], 422);
        db()->prepare("INSERT INTO saved_replies (workspace_id, shortcut, body) VALUES (?, ?, ?)")->execute([$wid, mb_substr($sc, 0, 40), $body]);
        $J(['ok' => true, 'items' => saved_replies($wid)]);
    }
    if ($act === 'reply_del') {
        db()->prepare("DELETE FROM saved_replies WHERE id = ? AND workspace_id = ?")->execute([(int)($_POST['id'] ?? 0), $wid]);
        $J(['ok' => true, 'items' => saved_replies($wid)]);
    }
    if ($act === 'reply_use') {
        db()->prepare("UPDATE saved_replies SET uses = uses + 1 WHERE id = ? AND workspace_id = ?")->execute([(int)($_POST['id'] ?? 0), $wid]);
        $J(['ok' => true]);
    }
    $J(['error' => t('طلب غير صحيح.')], 400);
}
