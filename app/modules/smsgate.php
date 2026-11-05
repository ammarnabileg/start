<?php
/**
 * رقمي من موبايل أندرويد: تكامل مع تطبيق SMS Gateway for Android المفتوح المصدر (Apache-2.0)
 * https://github.com/capcom6/android-sms-gateway — الموبايل اللي فيه الخط بيبعت ويستقبل الرسايل،
 * والمنصة بتكلمه عن طريق سحابة التطبيق (api.sms-gate.app) أو سيرفر خاص.
 */

class SmsGateException extends Exception {}

function smsgate_conf(): ?array
{
    $c = connector('sms_gate');
    return $c ? $c['credentials'] + ['_id' => (int)$c['id'], '_settings' => $c['settings']] : null;
}

function smsgate_base(array $c): string
{
    if (($c['server'] ?? 'cloud') === 'private') {
        $u = rtrim((string)($c['url'] ?? ''), '/');
        if (!preg_match('#^https://#', $u)) throw new SmsGateException(t('رابط السيرفر الخاص لازم يبدأ بـ https://'));
        return $u . '/api/3rdparty/v1';
    }
    return 'https://api.sms-gate.app/3rdparty/v1';
}

/** طلب للسيرفر — يرجع JSON متفكوك */
function smsgate_request(array $c, string $method, string $path, $body = null)
{
    if (trim((string)($c['user'] ?? '')) === '' || (string)($c['pass'] ?? '') === '') throw new SmsGateException(t('اكتب اسم المستخدم وكلمة السر من التطبيق.'));
    $ch = curl_init(smsgate_base($c) . $path);
    $h = ['Accept: application/json'];
    if ($body !== null) $h[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERPWD => trim((string)$c['user']) . ':' . (string)$c['pass'], CURLOPT_HTTPHEADER => $h,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new SmsGateException(t('مش قادرين نوصل لسيرفر الرسايل: %s', $cerr));
    $j = json_decode((string)$raw, true);
    if ($code === 401 || $code === 403) throw new SmsGateException(t('اسم المستخدم أو كلمة السر غلط. انسخهم تاني من التطبيق.'));
    if ($code >= 400) throw new SmsGateException(t('سيرفر الرسايل رد بخطأ (%s): %s', $code, is_array($j) ? (string)($j['message'] ?? $j['error'] ?? '') : mb_substr((string)$raw, 0, 200)));
    return is_array($j) ? $j : [];
}

/** يبعت SMS من رقمك — يرجع الـ id بتاع الرسالة عند التطبيق */
function smsgate_send(array $c, string $to, string $text): array
{
    $body = ['textMessage' => ['text' => $text], 'phoneNumbers' => [$to], 'withDeliveryReport' => true];
    $sim = (int)($c['sim'] ?? 0);
    if ($sim >= 1 && $sim <= 3) $body['simNumber'] = $sim;
    return smsgate_request($c, 'POST', '/messages', $body);
}

/** رابط الويبهوك الخاص بالشركة دي (بتوكن سري) */
function smsgate_hook_url(array $c): string
{
    $tok = (string)($c['_settings']['hook_token'] ?? '');
    if ($tok === '') {
        $tok = bin2hex(random_bytes(16));
        $set = $c['_settings']; $set['hook_token'] = $tok;
        db()->prepare('UPDATE connectors SET settings = ? WHERE id = ? AND workspace_id = ?')->execute([json_encode($set), (int)$c['_id'], (int)ws('id')]);
    }
    return base_url() . '/index.php?' . http_build_query(['p' => 'smsgate_hook', 'w' => (int)ws('id'), 't' => $tok]);
}

/** يسجّل الويبهوكات على التطبيق (استقبال + حالة الإرسال). بيمسح القديم اللي على نفس الرابط الأول */
function smsgate_register_hooks(array $c): int
{
    $url = smsgate_hook_url($c);
    foreach ((array)smsgate_request($c, 'GET', '/webhooks') as $w) {
        if (is_array($w) && strpos((string)($w['url'] ?? ''), 'p=smsgate_hook') !== false && !empty($w['id'])) smsgate_request($c, 'DELETE', '/webhooks/' . rawurlencode((string)$w['id']));
    }
    $n = 0;
    foreach (['sms:received', 'sms:sent', 'sms:delivered', 'sms:failed'] as $ev) { smsgate_request($c, 'POST', '/webhooks', ['url' => $url, 'event' => $ev]); $n++; }
    return $n;
}

function smsgate_norm(string $phone): string
{
    $p = preg_replace('/[^\d+]/', '', $phone);
    return $p === '' ? '' : ($p[0] === '+' ? $p : (strlen($p) > 9 ? '+' . $p : $p));
}

function smsgate_log(int $wid, string $dir, string $phone, string $body, string $ext = '', string $state = '', ?int $sim = null, ?int $by = null, ?string $at = null): int
{
    db()->prepare('INSERT INTO sms_gate_messages (workspace_id, direction, phone, body, ext_id, state, sim, sent_by, seen, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$wid, $dir, smsgate_norm($phone), mb_substr($body, 0, 2000), $ext ?: null, $state, $sim, $by, $dir === 'out' ? 1 : 0, $at ?: date('Y-m-d H:i:s')]);
    return (int)db()->lastInsertId();
}

/** المحادثات: آخر رسالة لكل رقم + عدد غير المقروء */
function smsgate_threads(int $wid, int $limit = 200): array
{
    $st = db()->prepare("SELECT m.phone, m.body, m.direction, m.state, m.created_at, x.unread, x.total FROM sms_gate_messages m
        JOIN (SELECT phone, MAX(id) mid, SUM(direction = 'in' AND seen = 0) unread, COUNT(*) total FROM sms_gate_messages WHERE workspace_id = ? GROUP BY phone) x ON x.mid = m.id
        ORDER BY m.id DESC LIMIT " . (int)$limit);
    $st->execute([$wid]);
    return $st->fetchAll();
}

function smsgate_thread(int $wid, string $phone, int $limit = 300): array
{
    $st = db()->prepare('SELECT * FROM (SELECT * FROM sms_gate_messages WHERE workspace_id = ? AND phone = ? ORDER BY id DESC LIMIT ' . (int)$limit . ') t ORDER BY id');
    $st->execute([$wid, smsgate_norm($phone)]);
    db()->prepare("UPDATE sms_gate_messages SET seen = 1 WHERE workspace_id = ? AND phone = ? AND direction = 'in' AND seen = 0")->execute([$wid, smsgate_norm($phone)]);
    return $st->fetchAll();
}
