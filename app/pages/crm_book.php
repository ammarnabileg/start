<?php
/**
 * حجز مواعيد (عام): index.php?p=crm_book&w=ID&t=TOKEN — زي Calendly ببلاش.
 * العميل بيختار ميعاد فاضي من المواعيد اللي حددتها، وبيدخل الـ Leads ومعاه اجتماع متسجل ومتابعة في ميعاده.
 */
$wid = (int)($_GET['w'] ?? 0);
$tok = (string)($_GET['t'] ?? '');
$real = $wid ? (string)setting('crm_form_token_' . $wid, '') : '';
if ($real === '' || $tok === '' || !hash_equals($real, $tok) || setting('crm_book_on_' . $wid, '0') !== '1') { http_response_code(403); exit('<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif">Booking is not available.</p>'); }
ai_as_workspace($wid, function () use ($wid) {
    $cfg = crm_book_cfg();
    $tz = new DateTimeZone(site_timezone());
    $srvTz = new DateTimeZone(date_default_timezone_get());
    $slots = crm_book_slots($cfg);
    $err = ''; $done = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $slot = (string)($_POST['slot'] ?? '');
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255);
        $phone = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 40);
        $email = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 255);
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 1000);
        if (trim((string)($_POST['website_url'] ?? '')) !== '') { http_response_code(400); exit('spam'); }
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $st = db()->prepare('SELECT COUNT(*) FROM crm_form_log WHERE workspace_id = ? AND ip = ? AND created_at > ?'); $st->execute([$wid, $ip, date('Y-m-d H:i:s', time() - 3600)]);
        if ((int)$st->fetchColumn() >= 8) $err = t('طلبات كتير. جرّب كمان شوية.');
        elseif ($name === '' || ($phone === '' && $email === '')) $err = t('اكتب اسمك ورقمك أو إيميلك.');
        elseif (!in_array($slot, array_merge(...array_values($slots) ?: [[]]), true)) $err = t('الميعاد ده اتحجز أو مش متاح. اختار ميعاد تاني.');
        else {
            db()->prepare('INSERT INTO crm_form_log (workspace_id, ip) VALUES (?, ?)')->execute([$wid, $ip]);
            $start = (new DateTime($slot, $tz))->setTimezone($srvTz)->format('Y-m-d H:i:s');
            try {
                db()->prepare('INSERT INTO crm_bookings (workspace_id, start_at) VALUES (?, ?)')->execute([$wid, $start]); // UNIQUE بيمنع حجزين في نفس الميعاد
                $bid = (int)db()->lastInsertId();
                $l = crm_lead_upsert(['source' => 'form', 'name' => $name, 'phone' => $phone, 'email' => $email, 'owner_id' => null]);
                db()->prepare('UPDATE crm_bookings SET lead_id = ? WHERE id = ?')->execute([(int)$l['id'], $bid]);
                $when = (new DateTime($slot, $tz))->format('d/m h:i A');
                db()->prepare('UPDATE crm_leads SET next_at = ?, next_note = ? WHERE id = ?')->execute([$start, mb_substr('🤝 ' . t('اجتماع محجوز %s', $when), 0, 255), (int)$l['id']]);
                crm_act_add((int)$l['id'], 'meet', '📅 ' . t('حجز اجتماع: %s (%s دقيقة)', $when, $cfg['len']) . ($note !== '' ? "\n“" . $note . '”' : ''));
                db()->prepare("UPDATE crm_leads SET stage = IF(stage IN ('new','contacted'), 'qualified', stage) WHERE id = ?")->execute([(int)$l['id']]);
                $done = $when;
            } catch (PDOException $e) { $err = t('الميعاد ده اتحجز حالًا. اختار ميعاد تاني.'); $slots = crm_book_slots($cfg); }
        }
    }
    $color = preg_match('/^#[0-9a-f]{6}$/i', (string)$cfg['color']) ? $cfg['color'] : '#4f46e5';
    $loc = is_rtl() ? 'ar' : 'en';
    ?><!doctype html>
<html lang="<?= e($loc) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($cfg['title'] ?: t('احجز ميعاد')) ?></title>
<style>:root{--c:<?= e($color) ?>}*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;background:#f8fafc;color:#111827}
main{max-width:560px;margin:0 auto;padding:20px 16px}h1{font-size:20px;margin:0 0 4px}.sub{color:#6b7280;font-size:14px;margin:0 0 16px}
.days{display:flex;gap:8px;overflow-x:auto;padding-bottom:6px}.day{flex:0 0 auto;border:1px solid #e5e7eb;background:#fff;border-radius:12px;padding:8px 12px;text-align:center;cursor:pointer;font-size:13px}
.day.on{border-color:var(--c);box-shadow:0 0 0 2px var(--c) inset}.day b{display:block;font-size:16px}
.slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:8px;margin:14px 0}.slots label{border:1px solid #e5e7eb;background:#fff;border-radius:10px;padding:9px;text-align:center;cursor:pointer;font-size:14px}
.slots input{display:none}.slots input:checked+span{color:#fff}.slots label:has(input:checked){background:var(--c);border-color:var(--c);color:#fff}
input[type=text],input[type=tel],input[type=email],textarea{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;font:inherit;font-size:15px;background:#fff;margin-bottom:8px}
button{width:100%;border:0;border-radius:10px;padding:12px;font:inherit;font-weight:600;color:#fff;background:var(--c);cursor:pointer}.err{color:#b91c1c;font-size:14px;margin:8px 0}
.done{background:#fff;border-radius:16px;padding:28px;text-align:center;font-size:16px;border:1px solid #e5e7eb}.hp{position:absolute;left:-9999px}.none{color:#6b7280;font-size:14px;padding:12px 0}</style></head><body><main>
<?php if ($done): ?><div class="done">✓ <?= e(t('اتحجز ميعادك: %s', $done)) ?><br><span style="color:#6b7280;font-size:14px"><?= e(t('هنكلمك في الميعاد. لو عاوز تغيّره ابعتلنا.')) ?></span></div>
<?php else: ?>
  <h1><?= e($cfg['title'] ?: t('احجز ميعاد')) ?></h1>
  <p class="sub"><?= e(t('%s دقيقة · بتوقيت %s', $cfg['len'], site_timezone())) ?></p>
  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
  <?php if (!$slots): ?><p class="none"><?= e(t('مفيش مواعيد فاضية دلوقتي. جرّب بعدين.')) ?></p><?php else: ?>
  <form method="post">
    <div class="days" id="days"><?php $i = 0; foreach ($slots as $d => $list): $dt = new DateTime($d, $tz); ?><div class="day<?= $i++ ? '' : ' on' ?>" data-d="<?= e($d) ?>"><?= e(fmt_date_loc($dt, 'D')) ?><b><?= e($dt->format('d')) ?></b><?= e(fmt_date_loc($dt, 'M')) ?></div><?php endforeach; ?></div>
    <?php $i = 0; foreach ($slots as $d => $list): ?><div class="slots" data-for="<?= e($d) ?>" <?= $i++ ? 'hidden' : '' ?>><?php foreach ($list as $s): ?><label><input type="radio" name="slot" value="<?= e($s) ?>" required><span><?= e((new DateTime($s, $tz))->format('h:i A')) ?></span></label><?php endforeach; ?></div><?php endforeach; ?>
    <input type="text" name="name" required maxlength="255" placeholder="<?= e(t('الاسم')) ?>">
    <input type="tel" name="phone" dir="ltr" maxlength="40" placeholder="<?= e(t('رقم الموبايل / واتساب')) ?>">
    <input type="email" name="email" dir="ltr" maxlength="255" placeholder="<?= e(t('الإيميل')) ?>">
    <textarea name="note" rows="2" maxlength="1000" placeholder="<?= e(t('عاوز نتكلم في إيه؟ (اختياري)')) ?>"></textarea>
    <div class="hp" aria-hidden="true"><input name="website_url" tabindex="-1" autocomplete="off"></div>
    <button><?= e(t('احجز')) ?></button>
  </form>
  <script>document.getElementById('days').addEventListener('click',function(e){var d=e.target.closest('.day');if(!d)return;document.querySelectorAll('.day').forEach(function(x){x.classList.toggle('on',x===d);});document.querySelectorAll('.slots').forEach(function(s){s.hidden=s.dataset.for!==d.dataset.d;});});</script>
  <?php endif; ?>
<?php endif; ?>
</main></body></html>
<?php
});
exit;
