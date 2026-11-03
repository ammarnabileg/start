<?php
/** صفحة التقرير للطباعة / الحفظ PDF (A4، من غير القائمة الجانبية) */
$wid = (int)ws('id');
$kind = in_array($_GET['period'] ?? '', ['last_week', 'last_month', 'custom'], true) ? (string)$_GET['period'] : 'last_week';
$per = rp_period($kind, rp_tz($wid), (string)($_GET['from'] ?? ''), (string)($_GET['to'] ?? ''));
$acc = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_GET['a'] ?? ''));

$R = rp_build($wid, $per, $acc);
if ($acc !== '') {
    foreach (rp_accounts($wid) as $a) {
        if ((string)($a['_id'] ?? '') === $acc) $R['accName'] = rp_acc_name($a) . ' · ' . platform_label((string)($a['platform'] ?? ''));
    }
}
$body = rp_render($R, false);
log_activity('reports.view', $per['from'] . ' ' . $per['to']);
?><!doctype html>
<?= html_open() ?>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($R['ws'] . ' – ' . t('تقرير الأداء')) ?></title>
<style>
  :root { --bg: #e2e8f0; --bar: #ffffff; --bar-text: #0f172a; --bar-border: #cbd5e1; }
  html.dark { --bg: #0a1020; --bar: #101a33; --bar-text: #f2f5fb; --bar-border: #2b3a5e; }
  body { margin: 0; background: var(--bg); }
  .bar { position: sticky; top: 0; z-index: 2; display: flex; gap: 8px; align-items: center; justify-content: space-between; padding: 10px 16px; background: var(--bar); color: var(--bar-text); border-bottom: 1px solid var(--bar-border); font-family: system-ui, sans-serif; font-size: 14px; }
  .bar a { color: inherit; }
  .bar button { background: #3b6ff0; color: #fff; border: 0; border-radius: 10px; padding: 8px 16px; font-weight: 600; cursor: pointer; font-size: 14px; }
  .sheet { background: #fff; width: 210mm; max-width: 100%; min-height: 297mm; margin: 20px auto; padding: 14mm; box-sizing: border-box; box-shadow: 0 4px 24px rgba(15, 23, 42, .15); }
  @media (max-width: 800px) { .sheet { padding: 16px; margin: 0; min-height: 0; } }
  @page { size: A4; margin: 12mm; }
  @media print {
    body { background: #fff; }
    .bar { display: none; }
    .sheet { box-shadow: none; margin: 0; padding: 0; width: auto; min-height: 0; }
    tr, td { page-break-inside: avoid; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
  <div class="bar">
    <a href="<?= e(url('reports')) ?>"><?= is_rtl() ? '→' : '←' ?> <?= e(t('رجوع للتقارير')) ?></a>
    <button type="button" onclick="window.print()"><?= e(t('طباعة / حفظ PDF')) ?></button>
  </div>
  <div class="sheet"><?= $body ?></div>
</body>
</html>
<?php exit;
