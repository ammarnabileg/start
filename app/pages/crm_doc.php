<?php
/** #98 عرض سعر / فاتورة من الصفقة: صفحة جاهزة للطباعة أو الحفظ PDF، والبنود والضريبة بتتعدل قبل الطباعة */
$wid = (int)ws('id');
$st = db()->prepare('SELECT d.*, l.name lname, l.company lcompany, l.email lemail, l.phone lphone, l.owner_id lowner FROM crm_deals d JOIN crm_leads l ON l.id = d.lead_id WHERE d.id = ? AND d.workspace_id = ?');
$st->execute([(int)($_GET['deal'] ?? 0), $wid]); $d = $st->fetch();
if (!$d || (!can('crm.manage') && (int)$d['lowner'] && (int)$d['lowner'] !== (int)(current_user()['id'] ?? 0))) { http_response_code(404); exit('Not found'); }
$isInv = ($_GET['t'] ?? '') === 'invoice';
$mt = function_exists('crm_mail_tpl') ? crm_mail_tpl() : ['color' => '#2557e0', 'logo' => '', 'footer' => ''];
$col = preg_match('/^#[0-9a-f]{6}$/i', (string)$mt['color']) ? $mt['color'] : '#2557e0';
$tax = (float)setting('crm_tax_' . $wid, '0');
$no = ($isInv ? 'INV-' : 'Q-') . date('Y') . '-' . str_pad((string)$d['id'], 4, '0', STR_PAD_LEFT);
$today = new DateTime('now', new DateTimeZone(site_timezone()));
$valid = (clone $today)->modify('+14 days');
$cur = (string)setting('crm_currency_' . $wid, '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // حفظ الضريبة والعملة الافتراضية من الصفحة نفسها
    save_setting('crm_tax_' . $wid, (string)max(0, min(100, (float)($_POST['tax'] ?? 0))));
    save_setting('crm_currency_' . $wid, mb_substr(trim((string)($_POST['cur'] ?? '')), 0, 8));
    header('Content-Type: application/json'); exit('{"ok":true}');
}
$title = ($isInv ? t('فاتورة') : t('عرض سعر')) . ' ' . $no;
?><!doctype html>
<html lang="<?= is_rtl() ? 'ar' : 'en' ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--c:<?= e($col) ?>}*{box-sizing:border-box}body{margin:0;background:#eef1f6;font-family:Alexandria,Tahoma,Arial,sans-serif;color:#0f172a;font-size:14px}
.bar{position:sticky;top:0;z-index:5;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:center;padding:10px;background:#0f172a;color:#e2e8f0;font-size:13px}
.bar button,.bar a{border:0;border-radius:10px;padding:8px 14px;font:inherit;cursor:pointer;background:#1e293b;color:#e2e8f0;text-decoration:none}.bar .p{background:var(--c);color:#fff;font-weight:600}
.bar input{width:64px;border-radius:8px;border:1px solid #334155;background:#0b1222;color:#fff;padding:6px 8px;font:inherit;text-align:center}
.sheet{max-width:820px;margin:24px auto;background:#fff;border-radius:16px;box-shadow:0 10px 40px -15px rgba(15,23,42,.3);overflow:hidden}
.hd{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:28px 32px;border-top:6px solid var(--c)}
.hd h1{margin:0;font-size:28px;color:var(--c)}.muted{color:#64748b;font-size:12px}.logo{max-height:52px;max-width:180px}
.two{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:0 32px 18px}.box{background:#f8fafc;border-radius:12px;padding:12px 14px}.box b{display:block;margin-bottom:4px}
table{width:calc(100% - 64px);margin:0 32px;border-collapse:collapse}th{background:#f1f5f9;color:#475569;font-weight:600;font-size:12px;padding:10px;text-align:start}td{padding:10px;border-bottom:1px solid #eef2f7;vertical-align:top}
td[contenteditable],span[contenteditable]{outline:none;border-radius:6px}td[contenteditable]:hover,td[contenteditable]:focus,span[contenteditable]:hover,span[contenteditable]:focus{background:#fffbeb;box-shadow:inset 0 0 0 1px #fcd34d}
.num{text-align:end;white-space:nowrap;font-variant-numeric:tabular-nums}.x{border:0;background:none;color:#cbd5e1;cursor:pointer;font-size:16px}.x:hover{color:#dc2626}
.add{margin:8px 32px 0;border:1px dashed #cbd5e1;background:none;border-radius:10px;padding:6px 12px;color:#475569;cursor:pointer;font:inherit}
.tot{margin:18px 32px 0 auto;width:300px;max-width:calc(100% - 64px)}.tot div{display:flex;justify-content:space-between;padding:6px 0}.tot .g{border-top:2px solid var(--c);margin-top:4px;padding-top:10px;font-size:18px;font-weight:700;color:var(--c)}
.ft{padding:22px 32px 28px;color:#64748b;font-size:12px;white-space:pre-line}
@media print{body{background:#fff}.bar,.x,.add{display:none!important}.sheet{box-shadow:none;margin:0;border-radius:0;max-width:none}td[contenteditable]:hover,span[contenteditable]:hover{background:none;box-shadow:none}}
@media (max-width:640px){.two{grid-template-columns:1fr}.hd{flex-direction:column}}
</style></head><body>
<div class="bar">
  <button class="p" onclick="window.print()"><?= e(t('اطبع / احفظ PDF')) ?></button>
  <label><?= e(t('الضريبة %')) ?> <input id="tax" value="<?= e((string)$tax) ?>" inputmode="decimal"></label>
  <label><?= e(t('العملة')) ?> <input id="cur" value="<?= e($cur) ?>" placeholder="SAR"></label>
  <a href="<?= e(url('crm_doc', ['deal' => (int)$d['id'], 't' => $isInv ? 'quote' : 'invoice'])) ?>"><?= e($isInv ? t('اعرضه كعرض سعر') : t('اعرضه كفاتورة')) ?></a>
  <span class="muted" style="color:#94a3b8"><?= e(t('اضغط على أي نص في الورقة عشان تعدّله قبل الطباعة.')) ?></span>
</div>
<div class="sheet">
  <div class="hd">
    <div><?php if (preg_match('~^https://~i', (string)$mt['logo'])): ?><img class="logo" src="<?= e($mt['logo']) ?>" alt=""><?php else: ?><b style="font-size:20px"><?= e((string)(ws('name') ?? '')) ?></b><?php endif; ?>
      <div class="muted" contenteditable="true" style="margin-top:6px;white-space:pre-line"><?= e((string)($mt['footer'] ?? '')) ?></div></div>
    <div style="text-align:end"><h1><?= e($isInv ? t('فاتورة') : t('عرض سعر')) ?></h1>
      <div class="muted"><?= e(t('رقم')) ?>: <span contenteditable="true"><?= e($no) ?></span></div>
      <div class="muted"><?= e(t('التاريخ')) ?>: <span contenteditable="true"><?= e($today->format('d/m/Y')) ?></span></div>
      <?php if (!$isInv): ?><div class="muted"><?= e(t('صالح لحد')) ?>: <span contenteditable="true"><?= e($valid->format('d/m/Y')) ?></span></div><?php endif; ?></div>
  </div>
  <div class="two">
    <div class="box"><b><?= e($isInv ? t('الفاتورة إلى') : t('مقدم إلى')) ?></b><span contenteditable="true"><?= e((string)$d['lname']) ?></span><br><span class="muted" contenteditable="true"><?= e(trim((string)$d['lcompany'] . ' · ' . (string)($d['lemail'] ?: $d['lphone']), ' ·')) ?></span></div>
    <div class="box"><b><?= e(t('بخصوص')) ?></b><span contenteditable="true"><?= e((string)$d['title']) ?></span></div>
  </div>
  <table id="items"><thead><tr><th style="width:50%"><?= e(t('البند')) ?></th><th class="num"><?= e(t('الكمية')) ?></th><th class="num"><?= e(t('السعر')) ?></th><th class="num"><?= e(t('الإجمالي')) ?></th><th style="width:28px"></th></tr></thead>
    <tbody><tr><td contenteditable="true"><?= e((string)$d['title']) ?></td><td class="num" contenteditable="true" data-q>1</td><td class="num" contenteditable="true" data-p><?= e(rtrim(rtrim(number_format((float)$d['value'], 2, '.', ''), '0'), '.')) ?></td><td class="num" data-t></td><td><button class="x" title="✕">✕</button></td></tr></tbody></table>
  <button class="add" id="add">+ <?= e(t('بند')) ?></button>
  <div class="tot"><div><span><?= e(t('المجموع')) ?></span><span id="sub" class="num"></span></div><div><span><?= e(t('الضريبة')) ?> (<span id="taxp"></span>%)</span><span id="taxv" class="num"></span></div><div class="g"><span><?= e(t('الإجمالي المطلوب')) ?></span><span id="grand" class="num"></span></div></div>
  <div class="ft" contenteditable="true"><?= e($isInv ? t("شكرًا لتعاملك معنا.\nطريقة الدفع: تحويل بنكي.") : t("الأسعار صالحة لمدة 14 يوم من تاريخ العرض.\nلأي استفسار تواصل معانا.")) ?></div>
</div>
<script>
(function () {
  var tb = document.querySelector('#items tbody'), tax = document.getElementById('tax'), cur = document.getElementById('cur');
  function n(s) { s = String(s).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }).replace(/[^\d.\-]/g, ''); return parseFloat(s) || 0; }
  function f(x) { return x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (cur.value ? ' ' + cur.value : ''); }
  function calc() { var s = 0; tb.querySelectorAll('tr').forEach(function (r) { var t = n(r.querySelector('[data-q]').textContent) * n(r.querySelector('[data-p]').textContent); r.querySelector('[data-t]').textContent = f(t); s += t; });
    var tp = Math.max(0, n(tax.value)), tv = s * tp / 100; document.getElementById('sub').textContent = f(s); document.getElementById('taxp').textContent = tp; document.getElementById('taxv').textContent = f(tv); document.getElementById('grand').textContent = f(s + tv); }
  tb.addEventListener('input', calc); tax.addEventListener('input', calc); cur.addEventListener('input', calc);
  tb.addEventListener('click', function (e) { if (e.target.closest('.x') && tb.rows.length > 1) { e.target.closest('tr').remove(); calc(); } });
  document.getElementById('add').addEventListener('click', function () { var r = tb.rows[0].cloneNode(true); r.cells[0].textContent = ''; r.querySelector('[data-q]').textContent = '1'; r.querySelector('[data-p]').textContent = '0'; tb.appendChild(r); r.cells[0].focus(); calc(); });
  var save = function () { var fd = new FormData(); fd.append('tax', tax.value); fd.append('cur', cur.value); fd.append('_csrf', <?= json_encode(csrf_token()) ?>); fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin' }); };
  tax.addEventListener('change', save); cur.addEventListener('change', save); // بتتفتكر للمرة الجاية
  calc();
})();
</script>
</body></html>
<?php exit;
