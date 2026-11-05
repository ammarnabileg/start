/* النشر الجماعي: معاينة الـ CSV في جدول قابل للتعديل + فحص الأخطاء قبل الرفع. مفيش حاجة بتتبعت غير بزرار "ارفع". */
(function () {
  var X = window.BKX; if (!X) return;
  var S = X.s;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var form = $('#bk-form'), file = $('#bk-file'), drop = $('#bk-drop'), wrap = $('#bk-grid-wrap'), grid = $('#bk-grid');
  var head = [], rows = [], fname = 'posts.csv', dirty = false;

  /* CSV: بيدعم علامات التنصيص والفواصل والأسطر جوه الخانة */
  function parse(txt) {
    txt = txt.replace(/^﻿/, '');
    var out = [], row = [], cell = '', q = false;
    for (var i = 0; i < txt.length; i++) {
      var c = txt[i];
      if (q) { if (c === '"') { if (txt[i + 1] === '"') { cell += '"'; i++; } else q = false; } else cell += c; }
      else if (c === '"') q = true;
      else if (c === ',') { row.push(cell); cell = ''; }
      else if (c === '\n' || c === '\r') { if (c === '\r' && txt[i + 1] === '\n') i++; row.push(cell); cell = ''; if (row.some(function (x) { return x !== ''; })) out.push(row); row = []; }
      else cell += c;
    }
    row.push(cell); if (row.some(function (x) { return x !== ''; })) out.push(row);
    return out;
  }
  function toCsv() {
    var q = function (v) { v = String(v == null ? '' : v); return /[",\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
    return [head].concat(rows).map(function (r) { return head.map(function (_, i) { return q(r[i]); }).join(','); }).join('\r\n') + '\r\n';
  }

  /* فحص كل سطر */
  function col(n) { return head.indexOf(n); }
  function check(r) {
    var errs = {}, get = function (n) { var i = col(n); return i < 0 ? '' : String(r[i] || '').trim(); };
    ['post_content', 'platforms', 'profiles'].forEach(function (n) { if (col(n) >= 0 && !get(n)) errs[n] = S.req; });
    var pls = get('platforms').toLowerCase().split('|').map(function (x) { return x.trim(); }).filter(Boolean);
    var bad = pls.filter(function (p) { return X.platforms.indexOf(p) < 0; }); if (bad.length) errs.platforms = f(S.plat, bad.join(', '));
    var flag = function (n) { return /^(true|1|yes)$/i.test(get(n)); };
    var st = get('schedule_time');
    if (st) { if (!/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test(st)) errs.schedule_time = S.date; else if (new Date(st.replace(' ', 'T')) < new Date()) errs.schedule_time = S.past; }
    else if (!flag('use_queue') && !flag('publish_now') && !flag('is_draft')) errs.schedule_time = S.when;
    if (pls.indexOf('twitter') > -1 && Array.from(get('custom_content_twitter') || get('post_content')).length > 280) errs.post_content = S.x;
    var mu = get('media_urls'); if (mu && mu.split('|').some(function (u) { return u.trim() && !/^https?:\/\//i.test(u.trim()); })) errs.media_urls = S.url;
    return errs;
  }
  function render() {
    if (!head.length) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';
    var missing = ['post_content', 'platforms', 'profiles'].filter(function (n) { return col(n) < 0; });
    var only = $('#bk-only').checked, okN = 0, badN = 0;
    var body = rows.map(function (r, ri) {
      var e = check(r), isBad = Object.keys(e).length > 0; if (isBad) badN++; else okN++;
      if (only && !isBad) return '';
      return '<tr class="border-t border-ink-100 ' + (isBad ? 'bg-red-50/40' : '') + '" data-r="' + ri + '"><td class="px-2 py-1 text-ink-400 sticky start-0 bg-white">' + (ri + 2) + '</td>'
        + head.map(function (h, ci) { var er = e[h]; return '<td class="px-1 py-0.5 align-top"><div contenteditable="true" dir="auto" data-c="' + ci + '" class="min-w-[6rem] max-w-[18rem] max-h-20 overflow-auto rounded px-1.5 py-1 outline-none focus:ring-2 focus:ring-brand-300 ' + (er ? 'ring-1 ring-red-300 bg-red-50' : 'hover:bg-ink-50') + '" title="' + esc(er || '') + '">' + esc(r[ci]) + '</div>' + (er ? '<div class="text-[10px] text-red-600 px-1">' + esc(er) + '</div>' : '') + '</td>'; }).join('')
        + '<td class="px-1"><button type="button" data-del="' + ri + '" class="w-6 h-6 rounded text-ink-400 hover:bg-red-50 hover:text-red-600" title="' + esc(S.del) + '">×</button></td></tr>';
    }).join('');
    grid.innerHTML = '<thead class="sticky top-0 bg-ink-50 z-[1]"><tr><th class="px-2 py-1.5 text-start text-ink-400">#</th>' + head.map(function (h) { return '<th class="px-2 py-1.5 text-start font-mono text-[11px] text-ink-600 whitespace-nowrap">' + esc(h) + '</th>'; }).join('') + '<th></th></tr></thead><tbody>' + body + '</tbody>';
    $('#bk-sum').innerHTML = '<span class="text-ink-700">' + esc(f(S.rows, rows.length)) + '</span> · <span class="text-lime-700">' + esc(f(S.ok, okN)) + '</span>' + (badN ? ' · <span class="text-red-600 font-medium">' + esc(f(S.bad, badN)) + '</span>' : '')
      + (missing.length ? ' · <span class="text-red-600 font-medium">' + esc(f(S.need, missing.join(', '))) + '</span>' : '') + (rows.length > 5000 ? ' · <span class="text-red-600">' + esc(S.max) + '</span>' : '') + (dirty ? ' <span class="text-amber-700">' + esc(S.edited) + '</span>' : '');
  }
  function load(txt, name) {
    var all = parse(txt); head = (all.shift() || []).map(function (h) { return h.trim(); }); rows = all; fname = name || fname; dirty = false;
    $('#bk-fn').textContent = fname; render();
  }
  file.addEventListener('change', function () { var fl = file.files[0]; if (!fl) return; var rd = new FileReader(); rd.onload = function () { load(rd.result, fl.name); }; rd.readAsText(fl, 'utf-8'); });
  ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('border-brand-500', 'bg-brand-50'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('border-brand-500', 'bg-brand-50'); }); });
  drop.addEventListener('drop', function (e) { var fl = e.dataTransfer.files[0]; if (!fl) return; var dt = new DataTransfer(); dt.items.add(fl); file.files = dt.files; file.dispatchEvent(new Event('change')); });
  $('#bk-new').onclick = function () {
    head = ['post_content', 'platforms', 'profiles', 'schedule_time', 'tz', 'media_urls'];
    var d = new Date(); d.setDate(d.getDate() + 1); d.setHours(10, 0, 0, 0); var z = function (n) { return ('0' + n).slice(-2); };
    rows = [['', 'facebook|instagram', '', d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate()) + ' 10:00', X.tz, '']]; fname = 'posts.csv'; dirty = true;
    $('#bk-fn').textContent = fname; render(); var c = $('[contenteditable]', grid); if (c) c.focus();
  };
  $('#bk-add').onclick = function () { var last = rows[rows.length - 1] || []; rows.push(head.map(function (h, i) { return h === 'post_content' ? '' : (last[i] || ''); })); dirty = true; render(); };
  $('#bk-only').onchange = render;
  grid.addEventListener('input', function (e) { var c = e.target.closest('[contenteditable]'); if (!c) return; var r = +c.closest('tr').dataset.r; rows[r][+c.dataset.c] = c.innerText.replace(/\n$/, ''); dirty = true; });
  grid.addEventListener('focusout', function (e) { if (e.target.closest('[contenteditable]')) { var a = document.activeElement; setTimeout(render, 0); } });
  grid.addEventListener('click', function (e) { var b = e.target.closest('[data-del]'); if (b) { rows.splice(+b.dataset.del, 1); dirty = true; render(); } });
  $('#bk-dl').onclick = function () { var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + toCsv()], { type: 'text/csv' })); a.download = fname.replace(/\.csv$/i, '') + '-edited.csv'; a.click(); };
  /* وقت الإرسال: لو فيه جدول، نبعته هو (بالتعديلات) كملف CSV */
  form.addEventListener('submit', function (e) {
    if (head.length && rows.length) {
      var fl = new File([toCsv()], /\.csv$/i.test(fname) ? fname : fname + '.csv', { type: 'text/csv' });
      var dt = new DataTransfer(); dt.items.add(fl); file.files = dt.files;
    } else if (!file.files.length) { e.preventDefault(); alert(S.pick); }
  });
})();
