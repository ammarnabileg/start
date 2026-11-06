/* العملاء المستهدفين: فلترة فورية، استيراد CSV بربط أعمدة تلقائي، وبحث Apollo مع كشف بالجملة */
(function () {
  var C = window.PR; if (!C) return; var L = C.L;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function post(url, d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { [].concat(d[k]).forEach(function (v) { f.append(k, v); }); }); return fetch(url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function $(id) { return document.getElementById(id); }

  /* ---------- الفلاتر ---------- */
  var rows = [].slice.call(document.querySelectorAll('.pr-row')), has = '';
  var F = ['pf-src', 'pf-stage', 'pf-owner', 'pf-country', 'pf-from', 'pf-to', 'pf-tag'].map($).filter(Boolean);
  function apply() {
    var q = ($('pr-q').value || '').trim().toLowerCase(), v = {}; F.forEach(function (el) { v[el.id] = el.value.trim(); }); var n = 0, act = 0;
    F.forEach(function (el) { if (el.value.trim()) act++; });
    rows.forEach(function (r) { var d = r.dataset, ok =
      (!has || (has === 'phone' && d.phone === '1') || (has === 'email' && d.email === '1') || (has === 'none' && d.phone === '0' && d.email === '0')) &&
      (!q || d.q.indexOf(q) >= 0) && (!v['pf-src'] || d.src === v['pf-src']) && (!v['pf-stage'] || d.stage === v['pf-stage']) &&
      (v['pf-owner'] === undefined || !v['pf-owner'] || d.owner === v['pf-owner']) && (!v['pf-country'] || d.country === v['pf-country']) &&
      (!v['pf-from'] || d.date >= v['pf-from']) && (!v['pf-to'] || d.date <= v['pf-to']) && (!v['pf-tag'] || d.q.indexOf(v['pf-tag'].toLowerCase()) >= 0);
      r.style.display = ok ? '' : 'none'; if (ok) n++; });
    var fn = $('pr-fn'); if (fn) { fn.textContent = act; fn.classList.toggle('hidden', !act); }
    var pc = $('pf-count'); if (pc) pc.textContent = L.shown.replace('%s', n).replace('%s', rows.length);
    var em = $('pr-empty'); if (em) em.classList.toggle('hidden', n > 0);
  }
  if ($('pr-q')) $('pr-q').addEventListener('input', apply);
  F.forEach(function (el) { el.addEventListener('input', apply); el.addEventListener('change', apply); });
  if ($('pf-reset')) $('pf-reset').addEventListener('click', function () { F.forEach(function (el) { el.value = ''; }); apply(); });
  document.querySelectorAll('[data-has]').forEach(function (b) { b.addEventListener('click', function () { document.querySelectorAll('[data-has]').forEach(function (x) { x.classList.toggle('on', x === b); }); has = b.dataset.has; apply(); }); });
  var body = $('pr-body'); if (body) body.addEventListener('click', function (e) { if (e.target.closest('a')) return; var r = e.target.closest('.pr-row'); if (r) location.href = r.dataset.href; });
  apply();

  function dlg(id) { var d = $(id); d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) d.close(); }); return d; }

  /* ---------- استيراد ملف ---------- */
  var imp = dlg('pr-import'), map = {};
  $('pr-import-btn').addEventListener('click', function () { imp.showModal(); });
  function parseCSV(t) {
    t = t.replace(/^﻿/, ''); var sep = (t.split('\n')[0].match(/;/g) || []).length > (t.split('\n')[0].match(/,/g) || []).length ? ';' : ',';
    var out = [], row = [], cell = '', q = false;
    for (var i = 0; i < t.length; i++) { var c = t[i];
      if (q) { if (c === '"') { if (t[i + 1] === '"') { cell += '"'; i++; } else q = false; } else cell += c; }
      else if (c === '"') q = true; else if (c === sep) { row.push(cell); cell = ''; } else if (c === '\n' || c === '\r') { if (c === '\r' && t[i + 1] === '\n') i++; row.push(cell); cell = ''; if (row.join('').trim()) out.push(row); row = []; } else cell += c; }
    row.push(cell); if (row.join('').trim()) out.push(row); return out;
  }
  var guess = { name: /^(name|full ?name|الاسم|اسم|الإسم)/i, phone: /(phone|mobile|tel|whats|موبايل|جوال|تليفون|هاتف|رقم)/i, email: /(mail|إيميل|ايميل|البريد)/i, company: /(company|organization|شركة|الشركة|المؤسسة)/i,
    title: /(title|position|job|المسمى|الوظيفة)/i, country: /(country|الدولة|البلد)/i, tags: /(tag|تاج|وسم)/i, note: /(note|ملاحظ)/i };
  // sheets = [{name, rows}] — الربط باسم العمود (مش برقمه) عشان الشيتات ممكن ترتيب أعمدتها يختلف
  var sheets = [];
  function clean(rows) { return rows.map(function (r) { return r.map(function (v) { return v == null ? '' : (typeof v === 'number' && Number.isInteger(v) ? v.toFixed(0) : String(v)).trim(); }); }).filter(function (r) { return r.join('').trim(); }); }
  function picked() { if (sheets.length < 2 || !document.getElementById('pr-sheets')) return sheets; // أول رسم: كل الشيتات
    var on = [].map.call(document.querySelectorAll('#pr-sheets input:checked'), function (i) { return +i.value; }); return sheets.filter(function (_, i) { return on.indexOf(i) >= 0; }); }
  function heads() { var h = []; picked().forEach(function (sh) { (sh.rows[0] || []).forEach(function (x) { x = String(x).trim(); if (x && h.indexOf(x) < 0) h.push(x); }); }); return h; }
  function sample(hd) { var sh = picked()[0]; if (!sh) return ''; var i = sh.rows[0].map(function (x) { return String(x).trim(); }).indexOf(hd); return i >= 0 && sh.rows[1] ? sh.rows[1][i] || '' : ''; }
  function count() { return picked().reduce(function (n, sh) { return n + Math.max(0, sh.rows.length - 1); }, 0); }
  function showMap() {
    var m = $('pr-map'), keep = map; map = {}; var head = heads();
    head.forEach(function (h) { for (var k in guess) if (!(k in map) && guess[k].test(h)) { map[k] = h; break; } });
    Object.keys(keep).forEach(function (k) { if (head.indexOf(keep[k]) >= 0) map[k] = keep[k]; });
    var sh = sheets.length > 1 ? '<div id="pr-sheets" class="rounded-xl border border-ink-200 p-2.5 space-y-1"><b class="text-xs text-ink-700">' + esc(L.sheets) + '</b>' + sheets.map(function (x, i) {
      var on = !document.querySelector('#pr-sheets') || document.querySelector('#pr-sheets input[value="' + i + '"]:checked');
      return '<label class="flex items-center gap-2 text-sm"><input type="checkbox" value="' + i + '"' + (on ? ' checked' : '') + '><span class="flex-1">' + esc(x.name) + '</span><span class="text-xs text-ink-400">' + esc(L.sheetRows.replace('%s', Math.max(0, x.rows.length - 1))) + '</span></label>'; }).join('') + '</div>' : '';
    m.innerHTML = sh + '<p class="text-xs text-ink-500">' + esc(L.rows.replace('%s', count())) + '</p>' + Object.keys(L.cols).map(function (k) {
      return '<label class="flex items-center gap-2"><span class="w-24 text-xs text-ink-600">' + esc(L.cols[k]) + '</span><select data-col="' + k + '" class="input !mt-0 !py-1 text-sm flex-1"><option value="">' + esc(L.skip) + '</option>' +
        head.map(function (h) { return '<option value="' + esc(h) + '"' + (map[k] === h ? ' selected' : '') + '>' + esc(h) + ' — ' + esc(sample(h)) + '</option>'; }).join('') + '</select></label>'; }).join('');
    m.classList.remove('hidden'); var go = $('pr-import-go'); go.textContent = L.go.replace('%s', count()); go.classList.remove('hidden'); go.disabled = !count();
    m.querySelectorAll('select').forEach(function (s) { s.addEventListener('change', function () { if (s.value === '') delete map[s.dataset.col]; else map[s.dataset.col] = s.value; }); });
    m.querySelectorAll('#pr-sheets input').forEach(function (c) { c.addEventListener('change', showMap); });
  }
  function ready(list) { sheets = list.map(function (x) { return { name: x.name, rows: clean(x.rows) }; }).filter(function (x) { return x.rows.length > 1; }); map = {};
    var old = document.getElementById('pr-sheets'); if (old) old.remove();
    if (!sheets.length) { $('pr-map').classList.add('hidden'); $('pr-import-go').classList.add('hidden'); return toast(L.noRows, 'error'); } showMap(); }
  function readFile(f) { if (!f) return; $('pr-file-l').textContent = f.name;
    if (/\.xls$/i.test(f.name)) { // Excel القديم (97-2003): بيتقري في المتصفح بمكتبة SheetJS اللي بتتحمّل وقت الحاجة بس
      $('pr-file-l').textContent = L.reading;
      var go = function () { var fr = new FileReader(); fr.onload = function () { var list = [];
        try { var wb = XLSX.read(new Uint8Array(fr.result), { type: 'array' });
          list = wb.SheetNames.slice(0, 30).map(function (n) { return { name: n, rows: XLSX.utils.sheet_to_json(wb.Sheets[n], { header: 1, raw: true, defval: '', blankrows: false }) }; });
        } catch (e) { list = []; }
        $('pr-file-l').textContent = f.name; ready(list); }; fr.readAsArrayBuffer(f); };
      if (window.XLSX) go(); else { var sc = document.createElement('script'); sc.src = C.xlsLib; sc.onload = go; sc.onerror = function () { $('pr-file-l').textContent = f.name; toast(L.err, 'error'); }; document.head.appendChild(sc); }
      return; }
    if (/\.xlsx$/i.test(f.name)) { $('pr-file-l').textContent = L.reading; var fd = new FormData(); fd.append('_csrf', C.csrf); fd.append('action', 'parse_xlsx'); fd.append('file', f);
      fetch(C.selfUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { $('pr-file-l').textContent = f.name;
        if (!j.ok) { $('pr-map').classList.add('hidden'); $('pr-import-go').classList.add('hidden'); return toast(j.error || L.err, 'error'); } ready(j.sheets || []); }).catch(function () { toast(L.err, 'error'); }); return; }
    var r = new FileReader(); r.onload = function () { ready([{ name: f.name, rows: parseCSV(String(r.result)) }]); }; r.readAsText(f, 'utf-8'); }
  $('pr-file').addEventListener('change', function () { readFile(this.files[0]); });
  var drop = $('pr-file').closest('label'); ['dragover', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); if (ev === 'drop') readFile(e.dataTransfer.files[0]); }); });
  $('pr-import-go').addEventListener('click', function () {
    var b = this; b.disabled = true; b.textContent = L.busy;
    if (!count()) { b.disabled = false; return toast(L.noSheet, 'error'); }
    var out = []; picked().forEach(function (sh) { var h = sh.rows[0].map(function (x) { return String(x).trim(); });
      sh.rows.slice(1).forEach(function (r) { var o = {}; for (var k in map) { var i = h.indexOf(map[k]); if (i >= 0) o[k] = String(r[i] || '').trim(); } out.push(o); }); });
    post(C.importUrl, { action: 'import', rows: JSON.stringify(out) }).then(function (j) {
      if (!j.ok) { b.disabled = false; b.textContent = L.go.replace('%s', count()); return toast(j.error || L.err, 'error'); }
      toast(L.done.replace('%s', j.new).replace('%s', j.merged).replace('%s', j.skip)); setTimeout(function () { location.reload(); }, 1100);
    }).catch(function () { b.disabled = false; toast(L.err, 'error'); });
  });

  /* ---------- بحث Apollo ---------- */
  var fd = dlg('pr-find'); $('pr-find-btn').addEventListener('click', function () { fd.showModal(); var i = fd.querySelector('input'); if (i) i.focus(); });
  var ff = $('pr-find-f'); if (!ff) return;
  var res = $('pr-find-res'), bar = $('pr-find-bar'), page = 1, people = {};
  function personRow(p) {
    return '<label class="flex items-center gap-3 rounded-xl border border-ink-200 p-3 cursor-pointer has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50/50">' +
      (p.lead ? '<span class="text-[11px] rounded-full bg-lime-50 text-lime-800 px-2 py-0.5">' + esc(L.have) + '</span>' : '<input type="checkbox" value="' + esc(p.id) + '">') +
      '<div class="flex-1 min-w-0"><b class="block text-ink-900 truncate">' + esc(p.name) + '</b><span class="block text-xs text-ink-500 truncate">' + esc([p.title, p.company].filter(Boolean).join(' · ')) + '</span></div>' +
      '<span class="text-[11px] text-ink-400 flex gap-2">' + (p.has_phone ? '<span class="inline-flex items-center gap-1">' + ic('phone', 'w-3 h-3') + esc(L.hasPhone) + '</span>' : '') + (p.has_email ? '<span class="inline-flex items-center gap-1">' + ic('mail', 'w-3 h-3') + esc(L.hasEmail) + '</span>' : '') + '</span>' +
      '<span class="text-xs text-ink-500 hidden sm:block w-32 truncate">' + esc(p.city || '') + '</span></label>';
  }
  function search(append) {
    var d = { action: 'search', page: page }; new FormData(ff).forEach(function (v, k) { d[k] = v; });
    if (!append) res.innerHTML = '<p class="text-sm text-ink-500">' + esc(L.searching) + '</p>';
    post(C.findUrl, d).then(function (j) {
      if (!j.ok) { res.innerHTML = '<p class="text-sm text-red-600">' + esc(j.error || L.err) + '</p>'; bar.classList.add('hidden'); return; }
      j.people.forEach(function (p) { people[p.id] = p; });
      var h = j.people.map(personRow).join('');
      if (append) res.insertAdjacentHTML('beforeend', h); else res.innerHTML = h || '<p class="text-sm text-ink-500">' + esc(L.nothing) + '</p>';
      $('pr-find-n').textContent = L.found.replace('%s', j.total || j.people.length); bar.classList.toggle('hidden', !j.people.length && !append);
      $('pr-find-more').classList.toggle('hidden', j.people.length < 25); sync();
    }).catch(function () { res.innerHTML = '<p class="text-sm text-red-600">' + esc(L.err) + '</p>'; });
  }
  function sync() { $('pr-reveal').disabled = !res.querySelector('input:checked'); }
  ff.addEventListener('submit', function (e) { e.preventDefault(); page = 1; people = {}; search(false); });
  $('pr-find-more').addEventListener('click', function () { page++; search(true); });
  res.addEventListener('change', sync);
  $('pr-reveal').addEventListener('click', function () {
    var b = this, picked = [].map.call(res.querySelectorAll('input:checked'), function (i) { return people[i.value]; }); b.disabled = true; var old = b.textContent; b.textContent = L.revealing;
    post(C.findUrl, { action: 'reveal', people: JSON.stringify(picked) }).then(function (j) {
      b.textContent = old; if (!j.ok) { b.disabled = false; return toast(j.error || L.err, 'error'); }
      if (j.error) toast(j.error, 'error'); var n = Object.keys(j.done || {}).length; if (n) { toast(L.revealed.replace('%s', n)); setTimeout(function () { location.reload(); }, 1100); } else b.disabled = false;
    }).catch(function () { b.disabled = false; b.textContent = old; toast(L.err, 'error'); });
  });
})();
