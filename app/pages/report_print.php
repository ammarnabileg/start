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
  .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; padding: 10px 16px; background: var(--bar); color: var(--bar-text); border-bottom: 1px solid var(--bar-border); font-family: system-ui, sans-serif; font-size: 14px; }
  .bar a { color: inherit; }
  .bar button { background: #3b6ff0; color: #fff; border: 0; border-radius: 10px; padding: 8px 16px; font-weight: 600; cursor: pointer; font-size: 14px; }
  .bar button.ghost { background: transparent; color: inherit; border: 1px solid var(--bar-border); }
  .bar .secs { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 12px; opacity: .85; flex: 1; justify-content: center; }
  .bar .secs label { cursor: pointer; white-space: nowrap; }
  [contenteditable][data-ph]:empty:before { content: attr(data-ph); color: #94a3b8; }
  @media print { #rp-note [contenteditable]:empty { display: none; } }
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
    <span id="rp-secs" class="secs"></span>
    <span style="display:flex;gap:8px">
      <button type="button" class="ghost" id="rp-note-btn"><?= e(t('+ ملاحظة للمدير')) ?></button>
      <button type="button" class="ghost" id="rp-copy"><?= e(t('انسخ الملخص')) ?></button>
      <button type="button" onclick="window.print()"><?= e(t('طباعة / حفظ PDF')) ?></button>
    </span>
  </div>
  <div class="sheet"><?= $body ?></div>
  <script>
  (function () {
    var T = <?= json_encode(['note' => t('ملاحظات'), 'ph' => t('اكتب هنا ملخصك أو توصياتك… بتتطبع مع التقرير'), 'copied' => t('اتنسخ'), 'hide' => t('اخفي/اظهر الأقسام قبل الطباعة:')], JSON_UNESCAPED_UNICODE) ?>;
    var sheet = document.querySelector('.sheet'), root = sheet.firstElementChild && sheet.children.length === 1 ? sheet.firstElementChild : sheet;
    // نجمع كل عنوان قسم مع اللي بعده لحد العنوان اللي بعده
    var heads = [].filter.call(root.children, function (el) { return /font-size:\s*15px/.test(el.getAttribute('style') || '') && /font-weight:\s*700/.test(el.getAttribute('style') || ''); });
    var bar = document.getElementById('rp-secs'), key = 'rp_hide_' + <?= (int)$wid ?>, hidden = {};
    try { hidden = JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) {}
    if (heads.length) bar.appendChild(document.createTextNode(T.hide + ' '));
    heads.forEach(function (h) {
      var sec = document.createElement('section'); h.parentNode.insertBefore(sec, h);
      var n = h; while (n && (n === h || heads.indexOf(n) < 0)) { var nx = n.nextElementSibling; sec.appendChild(n); n = nx; }
      var name = h.textContent.trim(), lab = document.createElement('label'), cb = document.createElement('input');
      cb.type = 'checkbox'; cb.checked = !hidden[name]; sec.style.display = cb.checked ? '' : 'none';
      cb.onchange = function () { sec.style.display = cb.checked ? '' : 'none'; hidden[name] = !cb.checked; try { localStorage.setItem(key, JSON.stringify(hidden)); } catch (e) {} };
      lab.appendChild(cb); lab.appendChild(document.createTextNode(' ' + name)); bar.appendChild(lab);
    });
    // ملاحظة بتتطبع تحت عنوان التقرير
    var nk = 'rp_note_' + <?= (int)$wid ?> + '_' + <?= json_encode($per['from'] . '_' . $per['to']) ?>;
    function addNote(txt) {
      var box = document.getElementById('rp-note'); if (box) return box;
      box = document.createElement('div'); box.id = 'rp-note';
      box.innerHTML = '<div style="font-size:13px;font-weight:700;color:#0f172a;margin-bottom:6px">' + T.note + '</div><div contenteditable="true" style="min-height:48px;font-size:13px;line-height:1.7;color:#334155;outline:none;white-space:pre-wrap" data-ph="' + T.ph + '"></div>';
      box.style.cssText = 'border:1px solid #cbd5e1;border-radius:12px;padding:12px 14px;margin:0 0 16px;background:#f8fafc';
      var first = root.firstElementChild; root.insertBefore(box, first ? first.nextElementSibling : null);
      var ed = box.lastElementChild; ed.textContent = txt || '';
      ed.addEventListener('input', function () { try { localStorage.setItem(nk, ed.innerText); } catch (e) {} });
      return box;
    }
    var saved = ''; try { saved = localStorage.getItem(nk) || ''; } catch (e) {}
    if (saved) addNote(saved);
    document.getElementById('rp-note-btn').onclick = function () { addNote('').lastElementChild.focus(); };
    document.getElementById('rp-copy').onclick = function () {
      var cards = [].map.call(root.querySelectorAll('td[style*="vertical-align:top"]'), function (td) { return td.innerText.replace(/\s*\n\s*/g, ' ').trim(); }).filter(Boolean);
      var txt = (root.querySelector('td') ? root.querySelector('td').innerText.trim() + '\n' : '') + cards.map(function (c) { return '• ' + c; }).join('\n');
      (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () { var b = document.getElementById('rp-copy'), o = b.textContent; b.textContent = T.copied; setTimeout(function () { b.textContent = o; }, 1500); }, function () { prompt('', txt); });
    };
  })();
  </script>
</body>
</html>
<?php exit;
