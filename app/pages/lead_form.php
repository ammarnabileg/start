<?php
/**
 * فورم الموقع (عام، من غير تسجيل دخول): index.php?p=lead_form&w=ID&t=TOKEN
 * GET  → فورم صغير يتحط في iframe على موقعك (أو يتفتح كصفحة لوحده)
 * POST → يضيف Lead (فورم عادي، أو JSON من أي أداة فورمز زي Elementor/WPForms/Zapier)
 * الحماية: توكن سري لكل شركة + خانة فخ للروبوتات + حد للطلبات من نفس الـ IP.
 */
$wid = (int)($_GET['w'] ?? 0);
$tok = (string)($_GET['t'] ?? '');
$real = $wid ? (string)setting('crm_form_token_' . $wid, '') : '';
$isJson = stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'json') !== false || ($_GET['format'] ?? '') === 'json';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
$fail = function (int $code, string $msg) use ($isJson) {
    http_response_code($code);
    if ($isJson || $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['_ajax'])) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE); }
    else echo '<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif;color:#b91c1c">' . e($msg) . '</p>';
    exit;
};
if ($real === '' || $tok === '' || !hash_equals($real, $tok) || setting('crm_form_on_' . $wid, '1') !== '1') $fail(403, 'Form is not available.');
$cfg = json_decode((string)setting('crm_form_cfg_' . $wid, ''), true) ?: [];
$cfg += ['title' => '', 'thanks' => '', 'company' => 1, 'message' => 1, 'color' => '#4f46e5'];
$color = preg_match('/^#[0-9a-f]{6}$/i', (string)$cfg['color']) ? $cfg['color'] : '#4f46e5';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = $_POST;
    if ($isJson) { $in = json_decode((string)file_get_contents('php://input'), true); if (!is_array($in)) $fail(400, 'bad json'); }
    // أدوات الفورمز بتبعت أسماء خانات مختلفة؛ بندوّر بالأسماء الشائعة
    $pick = function (array $keys) use ($in) {
        $norm = []; foreach ($in as $k => $v) if (!is_array($v) && substr((string)$k, 0, 1) !== '_') $norm[mb_strtolower(preg_replace('/[\s_\-\[\]]+/u', '', (string)$k))] = trim((string)$v);
        foreach ($keys as $want) if (isset($norm[$want]) && $norm[$want] !== '') return $norm[$want]; // اسم الخانة بالظبط الأول
        foreach ($norm as $kk => $v) { if ($v === '' || ($keys[0] === 'name' && preg_match('/company|org|شركة|user/u', $kk))) continue; foreach ($keys as $want) if (mb_strpos($kk, $want) !== false) return $v; }
        return '';
    };
    if (trim((string)($in['website_url'] ?? '')) !== '') $fail(400, 'spam'); // خانة الفخ
    $name = mb_substr($pick(['name', 'fullname', 'الاسم', 'اسم']), 0, 255);
    $phone = mb_substr($pick(['phone', 'mobile', 'tel', 'whatsapp', 'رقم', 'موبايل', 'هاتف', 'جوال']), 0, 40);
    $email = mb_substr($pick(['email', 'mail', 'ايميل', 'إيميل', 'البريد']), 0, 255);
    $company = mb_substr($pick(['company', 'organization', 'الشركة', 'شركة']), 0, 255);
    $msg = mb_substr($pick(['message', 'msg', 'comment', 'notes', 'details', 'رسالة', 'ملاحظات', 'تفاصيل']), 0, 1500);
    $page = mb_substr((string)($in['_page'] ?? $_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
    if ($phone === '' && $email === '') $fail(422, t('اكتب رقمك أو إيميلك.'));
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $st = db()->prepare('SELECT COUNT(*) FROM crm_form_log WHERE workspace_id = ? AND ip = ? AND created_at > ?');
    $st->execute([$wid, $ip, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int)$st->fetchColumn() >= 8) $fail(429, t('طلبات كتير. جرّب كمان شوية.'));
    db()->prepare('INSERT INTO crm_form_log (workspace_id, ip) VALUES (?, ?)')->execute([$wid, $ip]);
    $utm = [];
    parse_str((string)parse_url($page, PHP_URL_QUERY), $q);
    foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $u) if (!empty($q[$u]) || !empty($in[$u])) $utm[] = $u . '=' . mb_substr((string)($in[$u] ?? $q[$u]), 0, 60);
    if (strpos($page, 'p=lead_form') !== false) $page = ''; // الفورم اتفتح لوحده: مانسجلش رابطه (فيه التوكن)
    $lead = ai_as_workspace($wid, function () use ($name, $phone, $email, $company, $msg, $page, $utm) {
        $l = crm_lead_upsert(['source' => 'form', 'name' => $name !== '' ? $name : ($email ?: $phone), 'phone' => $phone, 'email' => $email, 'company' => $company, 'owner_id' => null]);
        if (!$l) return null;
        if ($page !== '' && empty($l['src_url'])) db()->prepare('UPDATE crm_leads SET src_url = ? WHERE id = ?')->execute([$page, (int)$l['id']]);
        db()->prepare('UPDATE crm_leads SET next_at = COALESCE(next_at, NOW()), next_note = COALESCE(next_note, ?) WHERE id = ?')->execute([t('ملا فورم الموقع، كلّمه'), (int)$l['id']]);
        crm_act_add((int)$l['id'], 'note', trim('🌐 ' . t('فورم الموقع') . ($msg !== '' ? ': “' . $msg . '”' : '') . ($page !== '' ? "\n" . $page : '') . ($utm ? "\n" . implode(' · ', $utm) : '')));
        return $l;
    });
    if (!$lead) $fail(500, 'error');
    $thanks = trim((string)$cfg['thanks']) ?: t('شكرًا! هنكلمك قريب.');
    if ($isJson || !empty($in['_ajax'])) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => true, 'message' => $thanks], JSON_UNESCAPED_UNICODE); exit; }
    $done = $thanks;
}
$lang = is_rtl() ? 'ar' : 'en';
?><!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($cfg['title'] ?: t('تواصل معانا')) ?></title>
<style>
:root{--c:<?= e($color) ?>}*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:transparent;color:#111827}
form,.done{max-width:440px;margin:0 auto;padding:16px;display:grid;gap:10px}h2{margin:0 0 4px;font-size:18px}
input,textarea{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;font:inherit;font-size:15px;background:#fff}input:focus,textarea:focus{outline:2px solid var(--c);border-color:transparent}
button{border:0;border-radius:10px;padding:11px;font:inherit;font-weight:600;color:#fff;background:var(--c);cursor:pointer}button:disabled{opacity:.6}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}.err{color:#b91c1c;font-size:13px;min-height:1em}.done{text-align:center;font-size:16px;padding:32px 16px}
</style></head><body>
<?php if (!empty($done)): ?><div class="done">✓ <?= e($done) ?></div><?php else: ?>
<form method="post" id="f">
  <?php if (trim((string)$cfg['title']) !== ''): ?><h2><?= e($cfg['title']) ?></h2><?php endif; ?>
  <input name="name" required maxlength="255" placeholder="<?= e(t('الاسم')) ?>" autocomplete="name">
  <input name="phone" type="tel" maxlength="40" dir="ltr" placeholder="<?= e(t('رقم الموبايل / واتساب')) ?>" autocomplete="tel">
  <input name="email" type="email" maxlength="255" dir="ltr" placeholder="<?= e(t('الإيميل')) ?>" autocomplete="email">
  <?php if (!empty($cfg['company'])): ?><input name="company" maxlength="255" placeholder="<?= e(t('الشركة (اختياري)')) ?>" autocomplete="organization"><?php endif; ?>
  <?php if (!empty($cfg['message'])): ?><textarea name="message" rows="3" maxlength="1500" placeholder="<?= e(t('محتاج إيه؟ (اختياري)')) ?>"></textarea><?php endif; ?>
  <div class="hp" aria-hidden="true"><input name="website_url" tabindex="-1" autocomplete="off"></div>
  <input type="hidden" name="_page" id="pg">
  <button><?= e(t('ابعت')) ?></button><div class="err" id="er"></div>
</form>
<script>
(function(){var f=document.getElementById('f'),pg=document.getElementById('pg');try{pg.value=document.referrer||location.href;}catch(e){}
f.addEventListener('submit',function(e){e.preventDefault();var b=f.querySelector('button'),er=document.getElementById('er');er.textContent='';
if(!f.phone.value.trim()&&!f.email.value.trim()){er.textContent=<?= json_encode(t('اكتب رقمك أو إيميلك.'), JSON_UNESCAPED_UNICODE) ?>;return;}
var fd=new FormData(f);fd.append('_ajax','1');b.disabled=true;
fetch(location.href,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){if(!r.ok){b.disabled=false;er.textContent=r.error||'Error';return;}
f.outerHTML='<div class="done">✓ '+String(r.message).replace(/[<>&]/g,'')+'</div>';try{parent.postMessage({aipushLead:true},'*');}catch(e){}}).catch(function(){b.disabled=false;er.textContent='Error';});});})();
</script>
<?php endif; ?>
</body></html>
<?php exit;
