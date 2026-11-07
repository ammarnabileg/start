/* العملاء المستهدفين: فلترة فورية، استيراد CSV بربط أعمدة تلقائي، وبحث Apollo مع كشف بالجملة */
(function () {
  var C = window.PR; if (!C) return; var L = C.L;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function post(url, d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { [].concat(d[k]).forEach(function (v) { f.append(k, v); }); }); return fetch(url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function $(id) { return document.getElementById(id); }
  function $$(q) { return [].slice.call(document.querySelectorAll(q)); }

  function dlg(id) { var d = $(id); d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) d.close(); }); return d; }
  /* ---------- القايمة: كروت الأرقام، بحث، فلاتر، ترتيب، تحديد، كيبورد ---------- */
  var root = $('pr'); if (!root) return;
  var F = ['pf-src', 'pf-stage', 'pf-owner', 'pf-country', 'pf-from', 'pf-to'].map($).filter(Boolean);
  function srvUrl(kind, st, page) { var u = new URL(location.href); u.searchParams.delete('lead'); u.searchParams.set('ajax', kind); if (page != null) u.searchParams.set('page', page);
    Object.keys(st || {}).forEach(function (k) { u.searchParams.set(k, st[k] == null ? '' : st[k]); });
    var m = { 'pf-src': 'src', 'pf-stage': 'stage', 'pf-owner': 'owner', 'pf-country': 'country', 'pf-from': 'from', 'pf-to': 'to' }; F.forEach(function (el) { u.searchParams.set(m[el.id], el.value.trim()); }); return u.toString(); }
  var list = MKList(root, {
    extra: function (r) { var d = r.dataset, v = {}; F.forEach(function (el) { v[el.id] = el.value.trim(); });
      return (!v['pf-src'] || d.src === v['pf-src']) && (!v['pf-stage'] || d.stage === v['pf-stage']) && (!v['pf-owner'] || d.owner === v['pf-owner']) &&
        (!v['pf-country'] || (d.cc || d.country) === v['pf-country']) && (!v['pf-from'] || d.date >= v['pf-from']) && (!v['pf-to'] || d.date <= v['pf-to']); },
    onOpen: function (r) { open(r); },
    onRows: function (nr, append, j) { if (j && j.stages && !append) C.byStage = j.stages; if (window.PRBoard) PRBoard(); },
    // كتير عملاء؟ الصفحات والبحث والفلاتر من السيرفر
    remote: function (st, page) { return fetch(srvUrl('rows', st, page), { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); },
    _old: function (st, page) { var u = new URL(location.href); u.searchParams.delete('lead'); u.searchParams.set('ajax', 'rows'); u.searchParams.set('page', page);
      Object.keys(st).forEach(function (k) { u.searchParams.set(k, st[k] == null ? '' : st[k]); });
      var m = { 'pf-src': 'src', 'pf-stage': 'stage', 'pf-owner': 'owner', 'pf-country': 'country', 'pf-from': 'from', 'pf-to': 'to' }; F.forEach(function (el) { u.searchParams.set(m[el.id], el.value.trim()); });
      return fetch(u.toString(), { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
  });
  function fcount() { var n = F.filter(function (el) { return el.value.trim(); }).length, b = $('pr-fn'); if (b) { b.textContent = n; b.classList.toggle('hidden', !n); } }
  F.forEach(function (el) { el.addEventListener('input', function () { fcount(); list.apply(); }); el.addEventListener('change', function () { fcount(); list.apply(); }); });
  if ($('pf-reset')) $('pf-reset').addEventListener('click', function () { F.forEach(function (el) { el.value = ''; }); fcount(); list.apply(); });

  // جمع كل اللي اتعاملوا معانا (رسائل، تعليقات، واتساب، إعلانات) في الخلفية
  if (C.syncDue) { var sy = $('pr-sync'); sy.classList.remove('hidden');
    post(C.selfUrl, { action: 'sync' }).then(function (j) { sy.classList.add('hidden'); if (j.ok && j.new > 0) { toast(L.synced.replace('%s', j.new)); setTimeout(function () { location.reload(); }, 1200); } }).catch(function () { sy.classList.add('hidden'); }); }

  /* ---------- نافذة العميل (assets/lead-popup.js مشتركة مع باقي الصفحات) ---------- */
  /* ---------- إجراءات سريعة في الصف: المرحلة والمتابعة من غير ما تفتح العميل ---------- */
  var QT = C.P.T, qm = document.createElement('div'); qm.className = 'pr-qm hidden'; document.body.appendChild(qm); var qRow = null;
  function qClose() { qm.classList.add('hidden'); qRow = null; }
  function qOpen(btn, html) { qm.innerHTML = html; qm.classList.remove('hidden'); var r = btn.getBoundingClientRect(), w = qm.offsetWidth, h = qm.offsetHeight;
    qm.style.top = Math.min(innerHeight - h - 8, r.bottom + 4) + 'px'; qm.style.left = Math.max(8, Math.min(innerWidth - w - 8, document.dir === 'rtl' ? r.right - w : r.left)) + 'px'; }
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-qstage],[data-qnext]'); if (!b) return; e.preventDefault(); e.stopPropagation(); var r = b.closest('[data-mk-row]'); if (qRow === r && !qm.classList.contains('hidden')) return qClose(); qRow = r; var d = jd(r);
    if (b.hasAttribute('data-qstage')) qOpen(b, '<b>' + esc(QT.qStage) + '</b>' + Object.keys(C.stages).map(function (k) { return '<button type="button" data-qs="' + k + '" class="' + (k === d.stage ? 'on' : '') + (k === 'lost' ? ' lost' : '') + '">' + esc(C.stages[k]) + '</button>'; }).join(''));
    else qOpen(b, '<b>' + esc(QT.qNext) + '</b>' + Object.keys(C.nexts || {}).map(function (k) { return '<button type="button" data-qn="' + k + '">' + esc(C.nexts[k]) + '</button>'; }).join('')); }, true);
  qm.addEventListener('click', function (e) { var b = e.target.closest('[data-qs],[data-qn]'), r = qRow; if (!b || !r) return; var d = jd(r); qClose();
    if (b.dataset.qs) { var st = b.dataset.qs; if (st === d.stage) return;
      if (st === 'lost') { open(r); setTimeout(function () { var x = document.querySelector('#pd-stages [data-stg="lost"]'); if (x) x.click(); }, 300); return; }
      bulk([d.id], 'stage', { stage: st }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); upd(r, 'stage', st); r.dataset.stage = st; var p = r.querySelector('[data-stage-pill]'); if (p) p.firstChild.textContent = C.stages[st] || st; r.classList.add('pr-flash'); setTimeout(function () { r.classList.remove('pr-flash'); }, 900); toast(QT.qDone); if (st === 'won' && LPm.confetti) LPm.confetti(); }); return; }
    setNext([d.id], b.dataset.qn).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); rowNext(r, j); r.classList.add('pr-flash'); setTimeout(function () { r.classList.remove('pr-flash'); }, 900); toast(QT.qDone); }); });
  document.addEventListener('click', function (e) { if (!e.target.closest('.pr-qm,[data-qstage],[data-qnext]')) qClose(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') qClose(); }); window.addEventListener('wheel', qClose, { passive: true });

  /* ---------- جديد من آخر زيارة + المسؤول من الصف ---------- */
  if (C.sinceN > 0) { var sc = document.createElement('button'); sc.type = 'button'; sc.className = 'pr-since'; sc.innerHTML = ic('spark', 'w-4 h-4') + ' ' + esc(QT.since.replace('%s', C.sinceN.toLocaleString('en-US')));
    var tb2 = root.querySelector('.mk-table'); if (tb2) tb2.before(sc); sc.addEventListener('click', function () { var on = !sc.classList.contains('on'); root.querySelector('[data-mk-stat="' + (on ? 'since' : '_all') + '"]').click(); sc.classList.toggle('on', on); });
    root.addEventListener('click', function (e) { var st = e.target.closest('[data-mk-stat]'); if (st && st.dataset.mkStat !== 'since') sc.classList.remove('on'); }); }
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-qown]'); if (!b) return; e.preventDefault(); e.stopPropagation(); var r = b.closest('[data-mk-row]'); qRow = r; var d = jd(r);
    qOpen(b, '<b>' + esc(QT.qOwn) + '</b>' + Object.keys(C.members).map(function (k) { return '<button type="button" data-qo="' + k + '" class="' + (+k === +d.owner ? 'on' : '') + '">' + esc(C.members[k]) + '</button>'; }).join('') + '<button type="button" data-qo="0">' + esc(QT.noOwn) + '</button>'); }, true);
  qm.addEventListener('click', function (e) { var b = e.target.closest('[data-qo]'), r = qRow; if (!b || !r) return; var d = jd(r), v = +b.dataset.qo; qClose();
    bulk([d.id], 'owner', { owner: v }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); upd(r, 'owner', v); var ob = r.querySelector('[data-qown]'), nm = C.members[v] || '';
      if (ob) { ob.classList.toggle('none', !nm); ob.title = nm || QT.noOwn; ob.textContent = nm ? nm.charAt(0).toUpperCase() : '+'; } r.classList.add('pr-flash'); setTimeout(function () { r.classList.remove('pr-flash'); }, 900); toast(QT.qDone); }); });

  /* ---------- نتيجة المكالمة من الصف: بعد ما تتصل بيسألك حصل إيه (مردّش = محاولة تانية بكرة لوحدها) ---------- */
  var OCC = { answered: 'check', no_answer: 'refresh', busy: 'pause', wrong: 'x', later: 'clock' };
  root.addEventListener('click', function (e) { var a = e.target.closest('[data-mk-row] a[href^="tel:"]'); if (!a) return; e.stopPropagation(); var r = a.closest('[data-mk-row]');
    setTimeout(function () { qRow = r; qOpen(a, '<b>' + esc(QT.ocQ) + '</b>' + Object.keys(C.oc || {}).map(function (k) { return '<button type="button" data-qoc="' + k + '" class="pr-oc-' + k + '"><i>' + ic(OCC[k] || 'dots', 'w-4 h-4') + '</i>' + esc(C.oc[k]) + '</button>'; }).join('')); }, 400); }, true);
  qm.addEventListener('click', function (e) { var b = e.target.closest('[data-qoc]'), r = qRow; if (!b || !r) return; var d = jd(r); qClose();
    post(C.importUrl, { action: 'log', id: d.id, kind: 'call', outcome: b.dataset.qoc }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); var l = j.lead || {};
      var ag = r.querySelector('[data-c=ago]'); if (ag) ag.textContent = L.today; r.dataset.cold = '0';
      if (l.stage && l.stage !== d.stage) { upd(r, 'stage', l.stage); r.dataset.stage = l.stage; var p = r.querySelector('[data-stage-pill]'); if (p) p.firstChild.textContent = C.stages[l.stage] || l.stage; }
      if (l.next_at) { var x = l.next_at.split('T'), dd = x[0].split('-'); rowNext(r, { next: dd[2] + '/' + dd[1] + '/' + dd[0] + ' ' + (x[1] || ''), next_note: l.next_note || '', due: 0 }); }
      ctxLine(r, 'phone', '[' + (C.oc[b.dataset.qoc] || '') + ']'); r.classList.add('pr-flash'); setTimeout(function () { r.classList.remove('pr-flash'); }, 900); toast(QT.qDone); }); });

  /* ---------- واتساب من الصف: قوالب جاهزة بالاسم + تسجيل في سجل العميل ---------- */
  function ctxLine(r, icon, text) { var n = r.querySelector('[data-c=note]'); if (n) { n.textContent = text; n.title = text; } var d = r.querySelector('[data-c=ago]'), t = new Date(), p2 = function (x) { return (x < 10 ? '0' : '') + x; }; if (d) { d.textContent = p2(t.getDate()) + '/' + p2(t.getMonth() + 1) + '/' + t.getFullYear(); var w = d.closest('.pr-nd'); if (w) w.classList.remove('hidden'); } }
  var LX = (window.LP && window.LP.L) || {};
  function fillT(t, d) { return String(t).replace(/\{name\}/g, (d.name || '').split(/\s+/)[0]).replace(/\{company\}/g, d.company || '').replace(/\{me\}/g, LX.me || '').replace(/\{book\}/g, LX.book || ''); }
  root.addEventListener('click', function (e) { var a = e.target.closest('[data-mk-row] a[href*="wa.me"]'); if (!a) return; var tp = LX.waTpl || []; if (!tp.length) return; e.preventDefault(); e.stopPropagation(); var r = a.closest('[data-mk-row]'), d = jd(r); qRow = r;
    qOpen(a, '<b>' + esc(QT.waQ) + '</b>' + tp.map(function (t, i) { return '<button type="button" data-qwa="' + i + '" class="pr-qwa"><span>' + esc(t.n) + '</span><small dir="auto">' + esc(fillT(t.b, d).slice(0, 70)) + '…</small></button>'; }).join('') + '<button type="button" data-qwa="-1">' + esc(QT.waEmpty) + '</button>' + (LX.editTpl ? '<a class="pr-qm-l" href="' + esc(LX.editTpl) + '">' + esc(QT.waEdit) + '</a>' : '')); }, true);
  qm.addEventListener('click', function (e) { var b = e.target.closest('[data-qwa]'), r = qRow; if (!b || !r) return; var d = jd(r), i = +b.dataset.qwa, t = i >= 0 ? (LX.waTpl || [])[i] : null, txt = t ? fillT(t.b, d) : ''; qClose();
    window.open('https://wa.me/' + d.wa + (txt ? '?text=' + encodeURIComponent(txt) : ''), '_blank', 'noopener');
    post(C.importUrl, { action: 'log', id: d.id, kind: 'wa', body: t ? t.n : '' }).then(function (j) { if (!j.ok) return; var ag = r.querySelector('[data-c=ago]'); if (ag) ag.textContent = L.today; r.dataset.cold = '0'; ctxLine(r, 'chat', t ? t.n : (C.P.T.waDone || 'WhatsApp'));
      var l = j.lead || {}; if (l.stage && l.stage !== d.stage) { upd(r, 'stage', l.stage); r.dataset.stage = l.stage; var p = r.querySelector('[data-stage-pill]'); if (p) p.firstChild.textContent = C.stages[l.stage] || l.stage; } }); });

  /* ---------- تثبيت وتاج من الصف ---------- */
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-qpin]'); if (!b) return; e.preventDefault(); e.stopPropagation(); var r = b.closest('[data-mk-row]'), d = jd(r);
    post(C.importUrl, { action: 'pin', id: d.id }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); var on = !b.classList.contains('on'); b.classList.toggle('on', on); toast(on ? QT.pinned : QT.unpinned);
      var body = r.parentNode; if (on) body.prepend(r); }); }, true);
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-qtag]'); if (!b) return; e.preventDefault(); e.stopPropagation(); var r = b.closest('[data-mk-row]'), d = jd(r), have = (d.tags || '').split(',').map(function (x) { return x.trim(); });
    qRow = r; qOpen(b, '<b>' + esc(QT.tagQ) + '</b><input class="pr-tagin" placeholder="' + esc(QT.tagPh) + '" dir="auto"><div class="pr-tagsug">' + (C.allTags || []).filter(function (t) { return have.indexOf(t) < 0; }).slice(0, 12).map(function (t) { return '<button type="button" data-qt="' + esc(t) + '">#' + esc(t) + '</button>'; }).join('') + '</div>');
    var inp = qm.querySelector('.pr-tagin'); inp.focus(); inp.addEventListener('input', function () { var v = inp.value.trim().toLowerCase(); qm.querySelectorAll('[data-qt]').forEach(function (x) { x.style.display = !v || x.dataset.qt.toLowerCase().indexOf(v) >= 0 ? '' : 'none'; }); });
    inp.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' && inp.value.trim()) { ev.preventDefault(); addTag(inp.value.trim()); } }); }, true);
  function addTag(t) { var r = qRow; if (!r) return; var d = jd(r); qClose();
    bulk([d.id], 'tag', { tag: t }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); upd(r, 'tags', (d.tags ? d.tags + ', ' : '') + t); var box = r.querySelector('.pr-ctx'), ad = box && box.querySelector('[data-qtag]');
      if (ad) { var i = document.createElement('i'); i.textContent = '#' + t; ad.after(i); } if ((C.allTags || []).indexOf(t) < 0) (C.allTags = C.allTags || []).unshift(t); toast(QT.qDone); }); }
  qm.addEventListener('click', function (e) { var b = e.target.closest('[data-qt]'); if (b) addTag(b.dataset.qt); });

  /* ---------- سهم تحت العميل: متابعة سريعة (ملاحظة + ميعاد + مرحلة) وآخر 5 متابعات ---------- */
  var KI = { call: 'phone', wa: 'chat', email: 'mail', meet: 'users', note: 'pencil', stage: 'layers', task: 'check' };
  function expClose(r) { var x = r && r.nextElementSibling; if (x && x.classList.contains('pr-exp')) x.remove(); if (r) r.classList.remove('pr-open'); }
  function expHist(box, id) { box.innerHTML = '<div class="h-10 mk-sk"></div><div class="h-10 mk-sk mt-2"></div>';
    fetch(C.selfUrl + '&ajax=lead&id=' + id, { credentials: 'same-origin' }).then(function (x) { return x.text(); }).then(function (t) { var j = JSON.parse(t.slice(t.indexOf('{'))), a = (j.acts || []).filter(function (x) { return x.kind !== 'stage'; }).slice(0, 5);
      box.innerHTML = a.length ? a.map(function (x) { return '<div class="pr-hi"><p dir="auto">' + esc(x.body || x.label) + '</p><small>' + esc([x.label, x.who, x.at].filter(Boolean).join(' • ')) + '</small></div>'; }).join('') : '<p class="text-xs text-ink-400">' + esc(QT.xNone) + '</p>'; }).catch(function () { box.innerHTML = ''; }); }
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-qexp]'); if (!b) return; e.preventDefault(); e.stopPropagation(); var r = b.closest('[data-mk-row]');
    if (r.classList.contains('pr-open')) return expClose(r); root.querySelectorAll('[data-mk-row].pr-open').forEach(expClose); var d = jd(r); r.classList.add('pr-open');
    var x = document.createElement('div'); x.className = 'pr-exp';
    x.innerHTML = '<div class="pr-xh"><b>' + ic('clock', 'w-4 h-4') + esc(QT.xPrev) + '</b><div data-xh></div></div>';
    r.after(x); expHist(x.querySelector('[data-xh]'), d.id); x.classList.add('pr-exp1');
    x.addEventListener('click', function (ev) { if (ev.target.closest('[data-xopen]')) open(r); }); }, true);
  // فورم التحديث جوه الصف: ملاحظة + ميعاد + مرحلة، حفظ واحد (Enter في الملاحظة بيحفظ)
  root.addEventListener('submit', function (ev) { var f = ev.target.closest('.pr-uf'); if (!f) return; ev.preventDefault(); var r = f.closest('[data-mk-row]'), dd = jd(r), note = f.note.value.trim(), at = f.at.value, st = f.stage.value, jobs = [], btn = f.querySelector('button');
    if (!note && !at && st === dd.stage) { f.note.focus(); return toast(QT.xEmpty, 'error'); }
    if (st !== dd.stage && st === 'lost') { f.stage.value = dd.stage; open(r); setTimeout(function () { var y = document.querySelector('#pd-stages [data-stg="lost"]'); if (y) y.click(); }, 300); }
    else if (st !== dd.stage) jobs.push(bulk([dd.id], 'stage', { stage: st }).then(function (j) { if (j.ok) { upd(r, 'stage', st); r.dataset.stage = st; var p = r.querySelector('[data-stage-pill]'); if (p) p.firstChild.textContent = C.stages[st] || st; } return j; }));
    if (note) jobs.push(post(C.importUrl, { action: 'log', id: dd.id, kind: 'note', body: note }).then(function (j) { if (j.ok) ctxLine(r, 'pencil', note); return j; }));
    if (at) jobs.push(setNext([dd.id], 'at', { at: at, note: note }).then(function (j) { if (j.ok) rowNext(r, j); return j; }));
    if (!jobs.length) return; btn.disabled = true; f.classList.add('busy');
    Promise.all(jobs).then(function (rs) { btn.disabled = false; f.classList.remove('busy'); if (rs.some(function (j) { return !j.ok; })) return toast(L.err, 'error');
      f.note.value = ''; f.at.value = ''; f.classList.add('done'); setTimeout(function () { f.classList.remove('done'); }, 1200); r.classList.add('pr-flash'); setTimeout(function () { r.classList.remove('pr-flash'); }, 900); toast(QT.qDone);
      var x = r.nextElementSibling; if (x && x.classList.contains('pr-exp')) expHist(x.querySelector('[data-xh]'), dd.id); }).catch(function () { btn.disabled = false; f.classList.remove('busy'); toast(L.err, 'error'); }); });
  root.addEventListener('click', function (ev) { if (ev.target.closest('.pr-uf')) ev.stopPropagation(); }, true);

  // أي تحميل جديد للقايمة (بحث/فلتر/ترتيب) بيقفل المفتوح
  new MutationObserver(function () { root.querySelectorAll('.pr-exp').forEach(function (x) { var pr = x.previousElementSibling; if (!pr || !pr.classList.contains('pr-open')) x.remove(); }); }).observe(root.querySelector('[data-mk-body]'), { childList: true });

  // عرض / تعديل
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-qview],[data-qedit]'); if (!b) return; e.preventDefault(); e.stopPropagation(); var r = b.closest('[data-mk-row]'); open(r);
    if (b.hasAttribute('data-qedit')) setTimeout(function () { var t = document.querySelector('[data-pt="dt"]'); if (t) t.click(); }, 250); }, true);

  // أكتر من رقم وأكتر من إيميل
  function cPost(r, k, op, v) { return post(C.selfUrl, { action: 'contacts', id: jd(r).id, k: k, op: op, v: v }); }
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-qcadd]'); if (!b) return; e.preventDefault(); e.stopPropagation(); var r = b.closest('[data-mk-row]'); qRow = r;
    qOpen(b, '<b>' + esc(QT.cAddQ) + '</b><div class="pr-ctyp"><button type="button" class="on" data-ct="phone">' + esc(QT.cPh) + '</button><button type="button" data-ct="email">' + esc(QT.cEm) + '</button></div><form class="pr-cform" data-no-ajax><input dir="ltr" class="pr-tagin" placeholder="' + esc(QT.cPhP) + '"><button class="btn-primary !py-1.5 !text-xs">' + esc(QT.cSave) + '</button></form>');
    var k = 'phone', inp = qm.querySelector('input'); inp.focus();
    qm.querySelectorAll('[data-ct]').forEach(function (x) { x.addEventListener('click', function () { k = x.dataset.ct; qm.querySelectorAll('[data-ct]').forEach(function (y) { y.classList.toggle('on', y === x); }); inp.placeholder = k === 'phone' ? QT.cPhP : QT.cEmP; inp.type = k === 'phone' ? 'tel' : 'email'; inp.focus(); }); });
    qm.querySelector('form').addEventListener('submit', function (ev) { ev.preventDefault(); var v = inp.value.trim(); if (!v) return inp.focus(); var bt = this.querySelector('button'); bt.disabled = true;
      cPost(r, k, 'add', v).then(function (j) { bt.disabled = false; if (!j.ok) return toast(j.error || L.err, 'error'); qClose(); toast(QT.cAdded); list.reload(); }).catch(function () { bt.disabled = false; toast(L.err, 'error'); }); }); }, true);
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-qcdel]'); if (!b) return; e.preventDefault(); e.stopPropagation(); var r = b.closest('[data-mk-row]');
    if (!confirm(QT.cDelQ.replace('%s', b.dataset.v))) return;
    cPost(r, b.dataset.k, 'del', b.dataset.v).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); var ln = b.closest('.pr-cl'); if (ln) ln.remove(); toast(L.saved || QT.qDone); }).catch(function () { toast(L.err, 'error'); }); }, true);

  // كشف البيانات من Apollo من خانة التواصل
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-qreveal]'); if (!b) return; e.preventDefault(); e.stopPropagation(); var r = b.closest('[data-mk-row]'), d = jd(r); b.disabled = true; b.textContent = QT.revealing;
    bulk([d.id], 'enrich', {}).then(function (j) { if (!j.ok) { b.disabled = false; b.textContent = '!'; return toast(j.error || L.err, 'error'); } toast(QT.revealed); list.reload(); }).catch(function () { b.disabled = false; toast(L.err, 'error'); }); }, true);

  /* ---------- الفلاتر المحفوظة ---------- */
  var VT = C.P.T, views = C.views || [], vbar = document.createElement('div'); vbar.className = 'pr-views';
  var tb = root.querySelector('.mk-table'); if (tb) tb.before(vbar);
  function curState() { var st = list.state ? list.state() : {}, m = { 'pf-src': 'src', 'pf-stage': 'stage', 'pf-owner': 'owner', 'pf-country': 'country', 'pf-from': 'from', 'pf-to': 'to' }, o = {};
    if (st.q) o.q = st.q; if (st.stat) o.stat = st.stat; if (st.sort) { o.sort = st.sort; o.dir = st.dir; } F.forEach(function (el) { if (el.value.trim()) o[m[el.id]] = el.value.trim(); }); if (C.plSel) o.pl = C.plSel; return o; }
  function norm(o) { return Object.keys(o || {}).sort().map(function (k) { return k + '=' + String(o[k]); }).join('&'); }
  function same(a, b) { return norm(a) === norm(b); }
  function paintViews() { var cs = curState(); vbar.classList.toggle('hidden', !views.length);
    vbar.innerHTML = '<span>' + ic('star', 'w-3.5 h-3.5') + esc(VT.vTitle) + '</span>' + views.map(function (v) { return '<button type="button" data-vw="' + esc(v.id) + '" class="' + (same(v.s, cs) ? 'on' : '') + '">' + esc(v.name) + '<i data-vdel="' + esc(v.id) + '">×</i></button>'; }).join(''); }
  function applyView(v) { var s = v.s || {};
    if ((s.pl || '') !== (C.plSel || '')) { var u = new URL(location.href); u.searchParams.set('pl', s.pl || ''); u.searchParams.set('view', v.id); location.href = u.toString(); return; }
    var m = { src: 'pf-src', stage: 'pf-stage', owner: 'pf-owner', country: 'pf-country', from: 'pf-from', to: 'pf-to' }; F.forEach(function (el) { el.value = ''; }); Object.keys(m).forEach(function (k) { if (s[k] && $(m[k])) $(m[k]).value = s[k]; }); fcount();
    var q = root.querySelector('[data-mk-q]'); if (q) q.value = s.q || '';
    var sb = root.querySelector('[data-mk-stat="' + (s.stat || '_all') + '"]'); if (sb && !sb.classList.contains('mk-on')) sb.click(); else list.apply();
    if (s.sort) { var h = root.querySelector('[data-mk-sort="' + s.sort + '"]'); if (h) { h.click(); if ((s.dir || 1) < 0) h.click(); } }
    setTimeout(paintViews, 300); }
  vbar.addEventListener('click', function (e) { var d = e.target.closest('[data-vdel]'); if (d) { e.stopPropagation(); if (!confirm(VT.vDel)) return; post(C.selfUrl, { action: 'view_del', id: d.dataset.vdel }).then(function (j) { if (j.ok) { views = j.views; paintViews(); } }); return; }
    var b = e.target.closest('[data-vw]'); if (b) applyView(views.filter(function (v) { return v.id === b.dataset.vw; })[0]); });
  if ($('pf-save')) $('pf-save').addEventListener('click', function () { var st = curState(); if (!Object.keys(st).length) return toast(VT.vEmpty, 'error'); var n = (prompt(VT.vName) || '').trim(); if (!n) return;
    post(C.selfUrl, { action: 'view_save', name: n, state: JSON.stringify(st) }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); views = j.views; paintViews(); toast(VT.vSaved); }); });
  root.addEventListener('input', function () { setTimeout(paintViews, 400); }); root.addEventListener('change', function () { setTimeout(paintViews, 400); }); root.addEventListener('click', function (e) { if (e.target.closest('[data-mk-stat],[data-mk-sort]')) setTimeout(paintViews, 400); });
  paintViews(); var vq = new URLSearchParams(location.search).get('view'); if (vq) { var vv = views.filter(function (v) { return v.id === vq; })[0]; if (vv) setTimeout(function () { applyView(vv); }, 60); }
  // ?stat=ads مثلًا: يفتح الصفحة على كارت الأرقام ده (جاي من نماذج الإعلانات)
  var stq = new URLSearchParams(location.search).get('stat'); if (stq) { var sb = root.querySelector('[data-mk-stat="' + stq.replace(/[^a-z_]/g, '') + '"]'); if (sb) setTimeout(function () { sb.click(); }, 50); }
  window.PRList = list; var LPm = window.LeadPopup; LPm.host({ rows: function () { return list.rows().filter(function (x) { return x.style.display !== 'none'; }); } });
  var open = LPm.open, jd = LPm.jd, upd = LPm.upd, rowNext = LPm.rowNext, setNext = LPm.setNext, bulk = LPm.bulk;
  // فتح عميل من رابط التنبيه (?lead=ID)
  var lm = /[?&]lead=(\d+)/.exec(location.search); if (lm) { var lr = root.querySelector('[data-mk-row][data-id="' + lm[1] + '"]'); if (lr) open(lr); }
  /* ---------- عمليات بالجملة ---------- */
  function ids() { return list.selected().map(function (r) { return jd(r).id; }); }
  var P2 = C.P.T;
  function scope() { if (!list.allMode || !list.allMode()) return Promise.resolve({ ids: ids(), all: false });
    return fetch(srvUrl('ids', list.state()), { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { var j = JSON.parse(t.slice(t.indexOf('{'))); return { ids: j.ids || [], all: true }; }); }
  // دفعات 200: حد السيرفر لكل طلب (وعشان max_input_vars في PHP)
  function batched(all, fn) { var n = all.length, i = 0, last = { ok: true }; if (n > 400) toast(P2.working.replace('%s', 0).replace('%s', n.toLocaleString('en-US')));
    function step() { if (i >= n) return Promise.resolve(last); var part = all.slice(i, i + 200); i += part.length;
      return fn(part).then(function (j) { last = j; if (!j.ok) return j; if (n > 400) toast(P2.working.replace('%s', i.toLocaleString('en-US')).replace('%s', n.toLocaleString('en-US'))); return step(); }); }
    return step(); }
  function run(fn, local) { return scope().then(function (sc) { if (!sc.ids.length) return; if (sc.all && sc.ids.length > 400 && !confirm(P2.bigAsk.replace('%s', sc.ids.length.toLocaleString('en-US')))) return;
    return batched(sc.ids, fn).then(function (j) { done(j, sc.ids.length); if (j.ok) { if (sc.all) list.reload(); else if (local) local(); } }); }); }
  function done(j, n) { if (!j.ok) return toast(j.error || L.err, 'error'); toast(L.done2.replace('%s', n)); }
  $('pb-stage').addEventListener('change', function () { var v = this.value, s0 = list.selected(), el = this; if (!v || !s0.length) return; el.value = '';
    run(function (part) { return bulk(part, 'stage', { stage: v }); }, function () { s0.forEach(function (r) { upd(r, 'stage', v); r.querySelector('[data-stage-pill]').textContent = C.stages[v] || v; }); }); });
  if ($('pb-owner')) $('pb-owner').addEventListener('change', function () { var v = this.value, s0 = list.selected(), el = this; if (!v || !s0.length) return; el.value = '';
    run(function (part) { return bulk(part, 'owner', { owner: v }); }, function () { s0.forEach(function (r) { upd(r, 'owner', +v); }); }); });
  $('pb-next').addEventListener('change', function () { var v = this.value, s0 = list.selected(), el = this, lj = null; if (!v || !s0.length) return; el.value = '';
    run(function (part) { return setNext(part, v).then(function (j) { lj = j; return j; }); }, function () { s0.forEach(function (r) { rowNext(r, lj); }); }); });
  $('pb-tag').addEventListener('click', function () { var s0 = list.selected(), t = (prompt(L.tagAsk) || '').trim(); if (!t || !s0.length) return;
    run(function (part) { return bulk(part, 'tag', { tag: t }); }, function () { s0.forEach(function (r) { var d = jd(r); upd(r, 'tags', (d.tags ? d.tags + ', ' : '') + t); }); }); });
  $('pb-csv').addEventListener('click', function () { var s = list.selected(); if (!s.length) return;
    if (list.allMode && list.allMode()) { location.href = srvUrl('export', list.state()); return; } // كل اللي ماشيين على الفلتر من السيرفر
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
