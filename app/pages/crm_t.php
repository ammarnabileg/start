<?php
/* تتبع إيميلات الـ CRM (عام): o=1 → بكسل الفتح، u=… → تسجيل الضغطة وتحويل للرابط الأصلي */
$k = preg_replace('/[^0-9a-f]/', '', (string)($_GET['k'] ?? ''));
$st = strlen($k) === 32 ? db()->prepare('SELECT * FROM crm_mails WHERE token = ?') : null;
$m = null; if ($st) { $st->execute([$k]); $m = $st->fetch(); }
$u = (string)($_GET['u'] ?? '');
// #73 إلغاء الاشتراك
if (!empty($_GET['x'])) {
    if ($m) ai_as_workspace((int)$m['workspace_id'], function () use ($m) {
        db()->prepare('UPDATE crm_leads SET unsub = 1 WHERE id = ? AND workspace_id = ?')->execute([(int)$m['lead_id'], (int)$m['workspace_id']]);
        db()->prepare("UPDATE crm_plan_runs SET state = 'stopped' WHERE lead_id = ? AND workspace_id = ? AND state IN ('wait','due')")->execute([(int)$m['lead_id'], (int)$m['workspace_id']]);
        crm_act_add((int)$m['lead_id'], 'note', '🚫 ' . t('لغى الاشتراك في الإيميلات'));
    });
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><div style="font-family:system-ui,Tahoma,sans-serif;max-width:420px;margin:15vh auto;text-align:center;color:#0f172a" dir="' . (is_rtl() ? 'rtl' : 'ltr') . '"><div style="font-size:40px">✓</div><p>' . e(t('اتلغى اشتراكك. مش هتوصلك إيميلات تاني مننا.')) . '</p></div>');
}
if ($u !== '') {
    if (!preg_match('~^https?://~i', $u)) { http_response_code(400); exit('bad link'); }
    if ($m) {
        db()->prepare('UPDATE crm_mails SET clicks = clicks + 1, clicked_at = COALESCE(clicked_at, NOW()), opens = GREATEST(opens, 1), opened_at = COALESCE(opened_at, NOW()) WHERE id = ?')->execute([(int)$m['id']]);
        if ((int)$m['clicks'] === 0) ai_as_workspace((int)$m['workspace_id'], function () use ($m, $u) {
            crm_act_add((int)$m['lead_id'], 'note', '🔗 ' . t('ضغط على رابط في الإيميل "%s"', $m['subject']) . "\n" . mb_substr($u, 0, 300));
            db()->prepare('UPDATE crm_leads SET next_at = COALESCE(next_at, NOW()), next_note = COALESCE(next_note, ?) WHERE id = ?')->execute([t('ضغط على رابط في إيميلك، كلّمه دلوقتي'), (int)$m['lead_id']]);
        });
    }
    header('Location: ' . $u, true, 302); exit;
}
if ($m) db()->prepare('UPDATE crm_mails SET opens = opens + 1, opened_at = COALESCE(opened_at, NOW()) WHERE id = ?')->execute([(int)$m['id']]);
header('Content-Type: image/gif'); header('Cache-Control: no-store, max-age=0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit;
