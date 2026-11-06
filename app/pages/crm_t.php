<?php
/* تتبع إيميلات الـ CRM (عام): o=1 → بكسل الفتح، u=… → تسجيل الضغطة وتحويل للرابط الأصلي */
$k = preg_replace('/[^0-9a-f]/', '', (string)($_GET['k'] ?? ''));
$st = strlen($k) === 32 ? db()->prepare('SELECT * FROM crm_mails WHERE token = ?') : null;
$m = null; if ($st) { $st->execute([$k]); $m = $st->fetch(); }
$u = (string)($_GET['u'] ?? '');
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
