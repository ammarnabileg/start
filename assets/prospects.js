/* العملاء المستهدفين: فلترة فورية، استيراد CSV بربط أعمدة تلقائي، وبحث Apollo مع كشف بالجملة */
(function () {
  var C = window.PR; if (!C) return; var L = C.L;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function post(url, d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { [].concat(d[k]).forEach(function (v) { f.append(k, v); }); }); return fetch(url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function $(id) { return document.getElementById(id); }

  function dlg(id) { var d = $(id); d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) d.close(); }); return d; }
  /* ---------- القايمة: كروت الأرقام، بحث، فلاتر، ترتيب، تحديد، كيبورد ---------- */
  var root = $('pr'); if (!root) return;
  var F = ['pf-src', 'pf-stage', 'pf-owner', 'pf-country', 'pf-from', 'pf-to'].map($).filter(Boolean);
  var list = MKList(root, {
    extra: function (r) { var d = r.dataset, v = {}; F.forEach(function (el) { v[el.id] = el.value.trim(); });
      return (!v['pf-src'] || d.src === v['pf-src']) && (!v['pf-stage'] || d.stage === v['pf-stage']) && (!v['pf-owner'] || d.owner === v['pf-owner']) &&
        (!v['pf-country'] || d.country === v['pf-country']) && (!v['pf-from'] || d.date >= v['pf-from']) && (!v['pf-to'] || d.date <= v['pf-to']); },
    onOpen: function (r) { open(r); }
  });
  function fcount() { var n = F.filter(function (el) { return el.value.trim(); }).length, b = $('pr-fn'); if (b) { b.textContent = n; b.classList.toggle('hidden', !n); } }
  F.forEach(function (el) { el.addEventListener('input', function () { fcount(); list.apply(); }); el.addEventListener('change', function () { fcount(); list.apply(); }); });
  if ($('pf-reset')) $('pf-reset').addEventListener('click', function () { F.forEach(function (el) { el.value = ''; }); fcount(); list.apply(); });

  // جمع كل اللي اتعاملوا معانا (رسائل، تعليقات، واتساب، إعلانات) في الخلفية
  if (C.syncDue) { var sy = $('pr-sync'); sy.classList.remove('hidden');
    post(C.selfUrl, { action: 'sync' }).then(function (j) { sy.classList.add('hidden'); if (j.ok && j.new > 0) { toast(L.synced.replace('%s', j.new)); setTimeout(function () { location.reload(); }, 1200); } }).catch(function () { sy.classList.add('hidden'); }); }

  /* ---------- بوب أب العميل ---------- */
  var dl = dlg('pr-dlg'), cur = null;
  function jd(r) { try { return JSON.parse(r.dataset.j); } catch (e) { return {}; } }
  function open(r) {
    cur = r; var d = jd(r);
    $('pd-av').textContent = (d.name || '?').charAt(0); $('pd-name').textContent = d.name || '—'; $('pd-sub').textContent = [d.title, d.company].filter(Boolean).join(' · ');
    $('pd-contact').innerHTML = (d.phone ? '<a class="btn-ghost !py-1.5" href="tel:' + esc(d.phone) + '" dir="ltr">' + ic('phone') + esc(d.phone) + '</a>' : '') +
      (d.wa ? '<a class="btn-ghost !py-1.5 !text-lime-700" href="https://wa.me/' + esc(d.wa) + '" target="_blank" rel="noopener">' + ic('chat') + esc(L.wa) + '</a>' : '') +
      (d.email ? '<a class="btn-ghost !py-1.5" href="mailto:' + esc(d.email) + '" dir="ltr">' + ic('mail') + esc(d.email) + '</a>' : '') || '<span class="text-sm text-ink-400">' + esc(L.none) + '</span>';
    $('pd-stage').value = d.stage; if ($('pd-owner')) $('pd-owner').value = String(d.owner || 0);
    $('pd-meta').innerHTML = [[L.src, d.src], [L.added, d.added], [L.last, d.last || '—'], [L.country, d.country || '—']].map(function (x) { return '<div class="rounded-xl bg-ink-50 px-3 py-2"><dt class="text-[11px] text-ink-500">' + esc(x[0]) + '</dt><dd class="font-medium text-ink-900 truncate">' + esc(x[1]) + '</dd></div>'; }).join('');
    tags(d.tags); $('pd-open').href = C.leadUrl + '&lead=' + d.id; $('pd-co').classList.add('hidden');
    nextShow(d.next, false); $('pd-next-note').value = d.next_note || ''; $('pd-next-at').value = ''; $('pd-acts').innerHTML = '<li class="h-10 mk-sk"></li><li class="h-10 mk-sk"></li>'; dl.showModal();
    fetch(C.selfUrl + '&ajax=lead&id=' + d.id, { credentials: 'same-origin' }).then(function (x) { return x.json(); }).then(function (j) { if (!j.ok || cur !== r) return;
      acts(j.acts); nextShow(j.next, j.due); if (j.company) { $('pd-co').href = j.company; $('pd-co').classList.remove('hidden'); } }); }
  // المتابعة وسجل التواصل
  var AIC = { call: 'phone', wa: 'chat', email: 'mail', meet: 'users', note: 'pencil', stage: 'refresh' };
  function acts(a) { $('pd-acts').innerHTML = a.length ? a.map(function (x) { return '<li class="flex gap-2 rounded-xl bg-ink-50 px-3 py-2 text-sm"><span class="text-ink-500 mt-0.5">' + ic(AIC[x.kind] || 'dots', 'w-4 h-4') + '</span><div class="min-w-0 flex-1"><div class="flex gap-2 text-[11px] text-ink-500"><b class="text-ink-700">' + esc(x.label) + '</b><span class="tabular-nums">' + esc(x.at) + '</span>' + (x.who ? '<span>· ' + esc(x.who) + '</span>' : '') + '</div>' + (x.body ? '<p class="text-ink-800 break-words" dir="auto">' + esc(x.body) + '</p>' : '') + '</div></li>'; }).join('') : '<li class="text-sm text-ink-400">' + esc(L.noActs) + '</li>'; }
  function nextShow(n, due) { var el = $('pd-next'); el.textContent = n || L.noNext; el.className = 'tabular-nums ' + (due ? 'text-red-600 font-medium' : 'text-ink-600'); }
  function rowNext(r, j) { upd(r, 'next', j.next || ''); upd(r, 'next_note', j.next_note || ''); var c = r.querySelector('[data-c=next]'); r.dataset.due = j.due && r.dataset.stage !== 'won' && r.dataset.stage !== 'lost' ? '1' : '0';
    r.dataset.cold = '0'; c.className = 'block truncate tabular-nums ' + (r.dataset.due === '1' ? 'text-red-600 font-medium' : 'text-ink-400'); c.innerHTML = j.next ? ic('calendar', 'w-3 h-3 inline') + ' ' + esc(j.next.slice(0, 5)) : ''; }
  function setNext(idsArr, when, extra) { var d = { action: 'next', when: when, 'ids[]': idsArr }; Object.keys(extra || {}).forEach(function (k) { d[k] = extra[k]; }); return post(C.selfUrl, d); }
  $('pd-next-b').addEventListener('click', function (e) { var b = e.target.closest('[data-when]'); if (!b || !cur) return; var r = cur;
    setNext([jd(r).id], b.dataset.when, { note: $('pd-next-note').value }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); nextShow(j.next, j.due); rowNext(r, j); toast(L.saved); }); });
  $('pd-next-at').addEventListener('change', function () { var v = this.value, r = cur; if (!v) return;
    setNext([jd(r).id], 'at', { at: v, note: $('pd-next-note').value }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); nextShow(j.next, j.due); rowNext(r, j); toast(L.saved); }); });
  $('pd-next-note').addEventListener('change', function () { var r = cur, d = jd(r); if (!d.next) return; setNext([d.id], 'note', { note: this.value }).then(function (j) { if (j.ok) upd(r, 'next_note', j.next_note); }); });
  function logged(r, j) { acts(j.acts); if (j.last) { upd(r, 'last', j.last); var a = r.querySelector('[data-c=ago]'); a.textContent = L.today; r.setAttribute('data-s-last', new Date().toISOString().slice(0, 19).replace('T', ' ')); r.dataset.cold = '0'; } }
  function log(r, kind, body) { return post(C.selfUrl, { action: 'log', id: jd(r).id, kind: kind, body: body || '' }); }
  dl.addEventListener('click', function (e) { var b = e.target.closest('[data-kind]'); if (!b || !cur) return; var r = cur, f = $('pd-note-f');
    log(r, b.dataset.kind, f.b.value).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); f.b.value = ''; logged(r, j); toast(L.logged); }); });
  $('pd-note-f').addEventListener('submit', function (e) { e.preventDefault(); var r = cur, f = this; if (!f.b.value.trim()) return;
    log(r, 'note', f.b.value).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); f.b.value = ''; acts(j.acts); toast(L.logged); }); });
  // الضغط على اتصال/واتساب/إيميل في الصف أو البوب أب بيتسجل لوحده
  document.addEventListener('click', function (e) { var a = e.target.closest('#pr [data-log], #pd-contact a'); if (!a) return; var r = a.closest('[data-mk-row]') || cur; if (!r) return;
    var k = a.dataset.log || (/^tel:/.test(a.href) ? 'call' : /wa\.me/.test(a.href) ? 'wa' : 'email'); log(r, k).then(function (j) { if (j.ok) { logged(r, j); } }).catch(function () {}); });
  function tags(t) { $('pd-tags').innerHTML = String(t || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean).map(function (x) { return '<span class="mk-pill bg-ink-100 text-ink-700">#' + esc(x) + '</span>'; }).join(''); }
  function upd(r, k, v) { var d = jd(r); d[k] = v; r.dataset.j = JSON.stringify(d); if (k === 'stage' || k === 'owner') r.dataset[k] = String(v); }
  function bulk(ids, op, extra) { var d = { action: 'bulk', op: op, 'ids[]': ids }; Object.keys(extra).forEach(function (k) { d[k] = extra[k]; }); return post(C.importUrl, d); }
  $('pd-stage').addEventListener('change', function () { var v = this.value, r = cur; bulk([jd(r).id], 'stage', { stage: v }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error');
    upd(r, 'stage', v); r.querySelector('[data-stage-pill]').textContent = C.stages[v] || v; r.dataset.sStage = v; toast(L.saved); }); });
  if ($('pd-owner')) $('pd-owner').addEventListener('change', function () { var v = this.value, r = cur; bulk([jd(r).id], 'owner', { owner: v }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); upd(r, 'owner', +v); toast(L.saved); }); });
  $('pd-tag-f').addEventListener('submit', function (e) { e.preventDefault(); var t = this.t.value.trim(), r = cur, f = this; if (!t) return;
    bulk([jd(r).id], 'tag', { tag: t }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); var d = jd(r), nt = (d.tags ? d.tags + ', ' : '') + t; upd(r, 'tags', nt); r.dataset.q += ' ' + t.toLowerCase(); tags(nt); f.t.value = ''; }); });

  /* ---------- عمليات بالجملة ---------- */
  function ids() { return list.selected().map(function (r) { return jd(r).id; }); }
  function done(j, n) { if (!j.ok) return toast(j.error || L.err, 'error'); toast(L.done2.replace('%s', n)); }
  $('pb-stage').addEventListener('change', function () { var v = this.value, s = list.selected(), el = this; if (!v || !s.length) return;
    bulk(ids(), 'stage', { stage: v }).then(function (j) { done(j, s.length); if (j.ok) s.forEach(function (r) { upd(r, 'stage', v); r.querySelector('[data-stage-pill]').textContent = C.stages[v] || v; }); el.value = ''; }); });
  if ($('pb-owner')) $('pb-owner').addEventListener('change', function () { var v = this.value, s = list.selected(), el = this; if (!v || !s.length) return;
    bulk(ids(), 'owner', { owner: v }).then(function (j) { done(j, s.length); if (j.ok) s.forEach(function (r) { upd(r, 'owner', +v); }); el.value = ''; }); });
  $('pb-next').addEventListener('change', function () { var v = this.value, s = list.selected(), el = this; if (!v || !s.length) return;
    setNext(ids(), v).then(function (j) { done(j, s.length); if (j.ok) s.forEach(function (r) { rowNext(r, j); }); el.value = ''; list.apply(); }); });
  $('pb-tag').addEventListener('click', function () { var s = list.selected(), t = (prompt(L.tagAsk) || '').trim(); if (!t || !s.length) return;
    bulk(ids(), 'tag', { tag: t }).then(function (j) { done(j, s.length); if (j.ok) s.forEach(function (r) { var d = jd(r); upd(r, 'tags', (d.tags ? d.tags + ', ' : '') + t); }); }); });
  $('pb-csv').addEventListener('click', function () { var s = list.selected(); if (!s.length) return;
    var q = function (v) { v = String(v == null ? '' : v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
    var lines = [[L.cols.name, L.cols.phone, L.cols.email, L.cols.company, L.cols.title, L.cols.country, L.src, L.added].map(q).join(',')].concat(s.map(function (r) { var d = jd(r);
      return [d.name, d.phone, d.email, d.company, d.title, d.country, d.src, d.added].map(q).join(','); }));
    var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8' })); a.download = 'leads-' + new Date().toISOString().slice(0, 10) + '.csv'; a.click(); });

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
