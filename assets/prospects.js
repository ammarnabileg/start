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
  var GR = [['#2563eb', '#06b6d4'], ['#7c3aed', '#ec4899'], ['#059669', '#84cc16'], ['#ea580c', '#f59e0b'], ['#0f766e', '#22d3ee'], ['#be123c', '#f97316']];
  function grad(n) { var h = 0; for (var i = 0; i < n.length; i++) h = (h * 31 + n.charCodeAt(i)) >>> 0; var g = GR[h % GR.length]; return 'linear-gradient(135deg,' + g[0] + ',' + g[1] + ')'; }
  function stagesPaint(v) { var keys = Object.keys(C.stages), at = keys.indexOf(v);
    $$('#pd-stages [data-stg]').forEach(function (b, i) { b.classList.toggle('on', b.dataset.stg === v); b.classList.toggle('done', v !== 'lost' && i < at && b.dataset.stg !== 'lost'); b.classList.toggle('lost', b.dataset.stg === 'lost'); });
    $('pd-stage-pill').textContent = C.stages[v] || v; }
  function contactPaint(d) {
    var it = [['phone', 'call', d.phone, 'tel:' + d.phone, L.call], ['chat', 'wa', d.wa, 'https://wa.me/' + d.wa, L.wa], ['mail', 'email', d.email, 'mailto:' + d.email, L.mail]];
    $('pd-contact').innerHTML = it.map(function (x) { return '<a class="pd-act' + (x[2] ? '' : ' off') + '" href="' + (x[2] ? esc(x[3]) : '#') + '"' + (x[1] === 'wa' ? ' target="_blank" rel="noopener"' : '') + ' data-log="' + x[1] + '">' + ic(x[0], 'w-5 h-5') + '<span>' + esc(x[4]) + '</span><small dir="ltr">' + esc(x[1] === 'wa' ? (d.wa ? '+' + d.wa : '—') : (x[2] || '—')) + '</small>' + (x[2] ? '<button type="button" class="pd-cp" data-copy="' + esc(x[1] === 'wa' ? '+' + d.wa : x[2]) + '" title="' + esc(L.copy) + '">' + ic('copy', 'w-3 h-3') + '</button>' : '') + '</a>'; }).join('');
    $('pd-addc').classList.toggle('hidden', !!(d.phone && d.email)); }
  function pd(t) { var m = /(\d{2})\/(\d{2})\/(\d{4})/.exec(t || ''); return m ? new Date(+m[3], m[2] - 1, +m[1]) : null; }
  function heat(d) { var keys = Object.keys(C.stages).filter(function (k) { return k !== 'lost'; }), i = keys.indexOf(d.stage);
    if (d.stage === 'lost') return 0; if (d.stage === 'won') return 100;
    var v = 15 + Math.round(45 * Math.max(0, i) / Math.max(1, keys.length - 1)), l = pd(d.last);
    if (l) { var g = (Date.now() - l) / 864e5; v += g < 3 ? 25 : g < 7 ? 15 : g < 30 ? 5 : -10; } else v -= 5;
    if (d.phone || d.wa) v += 8; if (d.email) v += 5; if (d.next) v += d.due ? -5 : 7; return Math.max(0, Math.min(99, v)); }
  function heatPaint(d) { var v = heat(d), el = $('pd-heat'), c = v >= 70 ? '#ef4444' : v >= 40 ? '#f59e0b' : '#38bdf8';
    el.style.setProperty('--v', v); el.style.setProperty('--c', c); el.textContent = v; el.title = L.heat.replace('%s', v) + ' · ' + (v >= 70 ? L.hot : v >= 40 ? L.warm : L.cold); }
  function vis() { return list.rows().filter(function (x) { return x.style.display !== 'none'; }); }
  function posPaint() { var v = vis(), i = v.indexOf(cur); $('pd-pos').textContent = i > -1 ? (i + 1) + ' / ' + v.length : ''; }
  function loadActs(r, d) {
    $('pd-acts').innerHTML = '<li class="h-12 mk-sk"></li><li class="h-12 mk-sk"></li><li class="h-12 mk-sk"></li>'; $('pd-acts-n').textContent = '';
    fetch(C.selfUrl + '&ajax=lead&id=' + d.id, { credentials: 'same-origin' }).then(function (x) { if (!x.ok) throw new Error('HTTP ' + x.status); return x.text(); })
      .then(function (t) { var j; try { j = JSON.parse(t.slice(t.indexOf('{'))); } catch (e) { throw new Error('bad json'); } // لو السيرفر طبع تحذير قبل الرد
        if (cur !== r) return; if (!j.ok) throw new Error('not ok');
        acts(j.acts); nextShow(j.next, j.due); if (j.company) { $('pd-co').href = j.company; $('pd-co').classList.remove('hidden'); } })
      .catch(function (e) { if (cur !== r) return; if (window.console) console.warn('lead acts', e);
        $('pd-acts').innerHTML = '<li class="!ps-0 text-sm text-ink-500">' + esc(L.loadErr) + ' <button type="button" class="link" data-retry>' + esc(L.retry) + '</button></li>'; }); }
  function open(r) {
    cur = r; var d = jd(r);
    var av = $('pd-av'); av.textContent = (d.name || '?').trim().charAt(0).toUpperCase(); av.style.background = grad(d.name || '?');
    $('pd-name').textContent = d.name || '—'; $('pd-sub').textContent = [d.title, d.company].filter(Boolean).join(' · ') || L.noTitle;
    $('pd-src-pill').textContent = d.src || ''; contactPaint(d); $('pd-addc').reset();
    $('pd-stage').value = d.stage; stagesPaint(d.stage); if ($('pd-owner')) $('pd-owner').value = String(d.owner || 0);
    $('pd-meta').innerHTML = [[L.added, d.added, 'clock'], [L.last, d.last || '—', 'chat'], [L.country, d.country || '—', 'globe'], [L.src, d.src, 'layers']].map(function (x) { return '<div class="rounded-xl bg-ink-50 px-3 py-2.5 min-w-0"><dt class="text-[11px] text-ink-500 flex items-center gap-1">' + ic(x[2], 'w-3.5 h-3.5') + esc(x[0]) + '</dt><dd class="font-semibold text-ink-900 truncate mt-0.5">' + esc(x[1]) + '</dd></div>'; }).join('');
    tags(d.tags); $('pd-open').href = C.leadUrl + '&lead=' + d.id; $('pd-co').classList.add('hidden');
    nextShow(d.next, d.due); $('pd-next-note').value = d.next_note || ''; $('pd-next-at').value = '';
    heatPaint(d); idEdit(false); popsClose(); tf = '';
    var bx = dl.querySelector('.pd-hero'); bx.classList.remove('pd-in-a'); void bx.offsetWidth; bx.classList.add('pd-in-a');
    posPaint(); loadActs(r, d); if (!dl.open) dl.showModal();
    $$('[data-mk-row]').forEach(function (x) { x.classList.toggle('mk-focus', x === r); }); r.scrollIntoView({ block: 'nearest' });
  }
  function nav(step) { var v = vis(), i = v.indexOf(cur), n = v[i + step]; if (n) open(n); }
  dl.addEventListener('click', function (e) { var b = e.target.closest('[data-nav]'); if (b) return nav(+b.dataset.nav); if (e.target.closest('[data-retry]') && cur) loadActs(cur, jd(cur));
    var s = e.target.closest('[data-stg]'); if (s && cur && s.dataset.stg !== $('pd-stage').value) { $('pd-stage').value = s.dataset.stg; stagesPaint(s.dataset.stg); $('pd-stage').dispatchEvent(new Event('change')); } });
  dl.addEventListener('keydown', function (e) { if (/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) return; var rtl = document.documentElement.dir === 'rtl';
    if (e.key === 'ArrowLeft') { e.preventDefault(); nav(rtl ? 1 : -1); } if (e.key === 'ArrowRight') { e.preventDefault(); nav(rtl ? -1 : 1); } });
  $('pd-addc').addEventListener('submit', function (e) { e.preventDefault(); var f = this, r = cur, b = f.querySelector('button'); if (!f.phone.value.trim() && !f.email.value.trim()) return; b.disabled = true;
    post(C.selfUrl, { action: 'contact', id: jd(r).id, phone: f.phone.value, email: f.email.value }).then(function (j) { b.disabled = false; if (!j.ok) return toast(j.error || L.err, 'error');
      upd(r, 'phone', j.phone); upd(r, 'email', j.email); upd(r, 'wa', j.wa); r.dataset.phone = j.phone ? 1 : 0; r.dataset.email = j.email ? 1 : 0; r.dataset.none = j.phone || j.email ? 0 : 1; contactPaint(jd(r)); toast(L.saved); }).catch(function () { b.disabled = false; toast(L.err, 'error'); }); });
  // المتابعة وسجل التواصل
  var AIC = { call: 'phone', wa: 'chat', email: 'mail', meet: 'users', note: 'pencil', stage: 'refresh' };
  var KC = { call: '#16a34a', wa: '#22c55e', email: '#2563eb', meet: '#d97706', note: '#64748b', stage: '#7c3aed' };
  function ago(t) { var m = /(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})/.exec(t || ''); if (!m) return t; var d = new Date(+m[3], m[2] - 1, +m[1], +m[4], +m[5]), s = (Date.now() - d) / 1000;
    return s < 90 ? L.now : s < 3600 ? L.minAgo.replace('%s', Math.round(s / 60)) : s < 86400 ? L.hrAgo.replace('%s', Math.round(s / 3600)) : s < 86400 * 30 ? L.dayAgo.replace('%s', Math.round(s / 86400)) : t; }
  var lastActs = [], tf = '';
  function acts(a) { lastActs = a; var cnt = {}; a.forEach(function (x) { cnt[x.kind] = (cnt[x.kind] || 0) + 1; }); var ks = Object.keys(cnt);
    $('pd-tf').innerHTML = ks.length > 1 ? ['<button type="button" class="pd-tfb' + (tf ? '' : ' on') + '" data-tf="">' + esc(L.all) + '</button>'].concat(ks.map(function (k) { return '<button type="button" class="pd-tfb' + (tf === k ? ' on' : '') + '" data-tf="' + k + '" style="--k:' + (KC[k] || '#64748b') + '">' + ic(AIC[k] || 'dots', 'w-3 h-3') + cnt[k] + '</button>'; })).join('') : '';
    if (tf) a = a.filter(function (x) { return x.kind === tf; }); actsList(a); }
  function actsList(a) { $('pd-acts-n').textContent = lastActs.length ? lastActs.length : ''; $('pd-acts').innerHTML = a.length ? a.map(function (x) { return '<li class="cp-pop"><span class="pd-dot text-white" style="background:' + (KC[x.kind] || '#64748b') + '">' + ic(AIC[x.kind] || 'dots', 'w-2.5 h-2.5') + '</span><div class="rounded-xl border border-ink-100 bg-white px-3 py-2"><div class="flex items-center gap-2 text-[11px] text-ink-500"><b class="text-ink-800 text-xs">' + esc(x.label) + '</b><span title="' + esc(x.at) + '">' + esc(ago(x.at)) + '</span>' + (x.who ? '<span class="ms-auto">' + esc(x.who) + '</span>' : '') + '</div>' + (x.body ? '<p class="text-sm text-ink-800 break-words mt-0.5" dir="auto">' + esc(x.body) + '</p>' : '') + '</div></li>'; }).join('') : '<li class="!ps-0 text-center py-8 text-sm text-ink-400">' + ic('chat', 'w-6 h-6 mx-auto mb-2 opacity-50') + esc(L.noActs) + '</li>'; }
  function nextShow(n, due) { var el = $('pd-next'); el.textContent = n || L.noNext; el.className = 'ms-auto tabular-nums text-xs ' + (due ? 'text-red-600 font-semibold' : 'text-ink-600'); var p = $('pd-due-pill'); if (p) { p.classList.toggle('hidden', !n); p.textContent = (due ? '⏰ ' : '📅 ') + (n || ''); p.style.background = due ? 'rgba(220,38,38,.85)' : ''; } }
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
  function tags(t) { $('pd-tags').innerHTML = String(t || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean).map(function (x) { return '<span class="pd-tag cp-pop">#' + esc(x) + '<button type="button" data-untag="' + esc(x) + '" title="' + esc(L.untag) + '">×</button></span>'; }).join(''); }
  function upd(r, k, v) { var d = jd(r); d[k] = v; r.dataset.j = JSON.stringify(d); if (k === 'stage' || k === 'owner') r.dataset[k] = String(v); if (r === cur) heatPaint(d); }
  function bulk(ids, op, extra) { var d = { action: 'bulk', op: op, 'ids[]': ids }; Object.keys(extra).forEach(function (k) { d[k] = extra[k]; }); return post(C.importUrl, d); }
  $('pd-stage').addEventListener('change', function () { var v = this.value, r = cur; bulk([jd(r).id], 'stage', { stage: v }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error');
    upd(r, 'stage', v); r.querySelector('[data-stage-pill]').textContent = C.stages[v] || v; r.dataset.sStage = v; if (v === 'won') { confetti(); toast(L.won); } else toast(L.saved); loadActs(r, jd(r)); }); });
  if ($('pd-owner')) $('pd-owner').addEventListener('change', function () { var v = this.value, r = cur; bulk([jd(r).id], 'owner', { owner: v }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); upd(r, 'owner', +v); toast(L.saved); }); });
  $('pd-tag-f').addEventListener('submit', function (e) { e.preventDefault(); var t = this.t.value.trim(), r = cur, f = this; if (!t) return;
    bulk([jd(r).id], 'tag', { tag: t }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); var d = jd(r), nt = (d.tags ? d.tags + ', ' : '') + t; upd(r, 'tags', nt); r.dataset.q += ' ' + t.toLowerCase(); tags(nt); f.t.value = ''; }); });

  /* ---------- تفاعل إضافي في البوب أب ---------- */
  function idEdit(on) { $('pd-idv').classList.toggle('hidden', on); $('pd-sub').classList.toggle('hidden', on); $('pd-idf').classList.toggle('hidden', !on);
    if (on && cur) { var d = jd(cur), f = $('pd-idf'); f.name.value = d.name || ''; f.title.value = d.title || ''; f.company.value = d.company || ''; f.name.focus(); f.name.select(); } }
  $('pd-edit').addEventListener('click', function () { idEdit(true); });
  $('pd-idf').addEventListener('click', function (e) { if (e.target.closest('[data-idx]')) idEdit(false); });
  $('pd-idf').addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); idEdit(false); } });
  $('pd-idf').addEventListener('submit', function (e) { e.preventDefault(); var f = this, r = cur;
    post(C.selfUrl, { action: 'edit', id: jd(r).id, name: f.name.value, title: f.title.value, company: f.company.value }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error');
      upd(r, 'name', j.name); upd(r, 'title', j.title); upd(r, 'company', j.company); $('pd-name').textContent = j.name; $('pd-sub').textContent = [j.title, j.company].filter(Boolean).join(' · ') || L.noTitle;
      $('pd-av').textContent = j.name.trim().charAt(0).toUpperCase(); var nb = r.querySelector('b'); if (nb) nb.textContent = j.name; idEdit(false); toast(L.saved); }).catch(function () { toast(L.err, 'error'); }); });
  // نسخ الرقم/الإيميل
  dl.addEventListener('click', function (e) { var c = e.target.closest('[data-copy]'); if (!c) return; e.preventDefault(); e.stopPropagation();
    var v = c.dataset.copy, ok = function () { toast(L.copied); c.classList.add('ok'); setTimeout(function () { c.classList.remove('ok'); }, 1200); };
    if (navigator.clipboard) navigator.clipboard.writeText(v).then(ok, ok); else ok(); }, true);
  // شيل تاج (مع تراجع)
  dl.addEventListener('click', function (e) { var b = e.target.closest('[data-untag]'); if (!b || !cur) return; var r = cur, t = b.dataset.untag, old = jd(r).tags;
    post(C.selfUrl, { action: 'untag', id: jd(r).id, tag: t }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); upd(r, 'tags', j.tags); tags(j.tags);
      undoBar(t, function () { bulk([jd(r).id], 'tag', { tag: t }).then(function (k) { if (k.ok) { upd(r, 'tags', old); if (cur === r) tags(old); } }); }); }); });
  function undoBar(label, fn) { var u = document.createElement('div'); u.className = 'pd-undo cp-pop'; u.innerHTML = '<span>#' + esc(label) + ' ✕</span><button type="button">' + esc(L.undo) + '</button>';
    dl.appendChild(u); var tm = setTimeout(function () { u.remove(); }, 5000); u.querySelector('button').onclick = function () { clearTimeout(tm); u.remove(); fn(); }; }
  // نتائج سريعة بتتكتب في الملاحظة
  $('pd-quick').addEventListener('click', function (e) { var b = e.target.closest('[data-qr]'); if (!b) return; var ta = $('pd-note-f').b, v = b.dataset.qr;
    ta.value = ta.value.trim() ? ta.value.trim() + ' · ' + v : v; b.classList.add('on'); setTimeout(function () { b.classList.remove('on'); }, 600); ta.focus(); });
  $('pd-note-f').b.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); $('pd-note-f').requestSubmit(); } });
  // فلتر السجل
  $('pd-tf').addEventListener('click', function (e) { var b = e.target.closest('[data-tf]'); if (!b) return; tf = b.dataset.tf; acts(lastActs); });
  // رسائل واتساب الجاهزة
  function popsClose() { ['pd-wa', 'pd-keys'].forEach(function (i) { $(i).classList.add('hidden'); }); }
  function fill(t) { var d = jd(cur); return t.replace(/\{name\}/g, (d.name || '').split(' ')[0]).replace(/\{me\}/g, L.me || ''); }
  function waOpen() { var d = jd(cur); if (!d.wa) return; var p = $('pd-wa');
    $('pd-wa-l').innerHTML = L.waT.map(function (t, i) { return '<button type="button" class="pd-wat" data-wat="' + i + '"><b>' + esc(L.waN[i]) + '</b><span>' + esc(fill(t)) + '</span></button>'; }).join('');
    $('pd-wa-t').value = fill(L.waT[0]); p.classList.remove('hidden'); $('pd-keys').classList.add('hidden'); }
  $('pd-wa-l').addEventListener('click', function (e) { var b = e.target.closest('[data-wat]'); if (!b) return; $('pd-wa-t').value = fill(L.waT[+b.dataset.wat]);
    [].forEach.call(this.children, function (x) { x.classList.toggle('on', x === b); }); });
  $('pd-wa-x').addEventListener('click', popsClose);
  $('pd-wa-go').addEventListener('click', function () { var r = cur, d = jd(r), t = $('pd-wa-t').value.trim();
    window.open('https://wa.me/' + d.wa + (t ? '?text=' + encodeURIComponent(t) : ''), '_blank', 'noopener'); popsClose();
    log(r, 'wa', t).then(function (j) { if (j.ok) { logged(r, j); toast(L.logged); } }).catch(function () {}); });
  // الواتساب في الكروت بيفتح الرسائل الجاهزة بدل الفتح المباشر
  $('pd-contact').addEventListener('click', function (e) { var a = e.target.closest('a[data-log="wa"]'); if (!a || e.target.closest('[data-copy]')) return; e.preventDefault(); e.stopImmediatePropagation(); waOpen(); }, true);
  $('pd-keys-b').addEventListener('click', function () { var k = $('pd-keys'); k.classList.toggle('hidden'); $('pd-wa').classList.add('hidden'); });
  // احتفال بالصفقة
  function confetti() { var fx = $('pd-fx'), cs = ['#22c55e', '#2563eb', '#f59e0b', '#ec4899', '#06b6d4', '#a855f7'];
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    for (var i = 0; i < 70; i++) { var p = document.createElement('i'); p.className = 'pd-cf'; p.style.left = Math.random() * 100 + '%'; p.style.background = cs[i % cs.length];
      p.style.animationDelay = Math.random() * .4 + 's'; p.style.animationDuration = 1.4 + Math.random() * 1.2 + 's'; p.style.transform = 'rotate(' + Math.random() * 360 + 'deg)'; fx.appendChild(p); }
    setTimeout(function () { fx.innerHTML = ''; }, 3200); }
  // اختصارات الكيبورد
  dl.addEventListener('keydown', function (e) { if (/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) || e.ctrlKey || e.metaKey || e.altKey || !cur) return;
    var k = e.key.toLowerCase(), stg = $$('#pd-stages [data-stg]'), go = function (fn) { e.preventDefault(); fn(); };
    if (/^[1-9]$/.test(k) && stg[+k - 1]) return go(function () { stg[+k - 1].click(); });
    if (k === 'n') return go(function () { $('pd-note-f').b.focus(); });
    if (k === 'r') return go(function () { idEdit(true); });
    if (k === 't') return go(function () { $('pd-tag-f').t.focus(); });
    if (k === 'f') return go(function () { var b = $('pd-next-b').querySelector('[data-when="1d"]'); if (b) b.click(); });
    if (k === 'w') return go(waOpen);
    if (k === 'c' || k === 'e') return go(function () { var a = $('pd-contact').querySelector('a[data-log="' + (k === 'c' ? 'call' : 'email') + '"]:not(.off)'); if (a) a.click(); });
    if (k === '?' || (e.shiftKey && k === '/')) return go(function () { $('pd-keys-b').click(); });
    if (k === 'escape' && !$('pd-wa').classList.contains('hidden') + !$('pd-keys').classList.contains('hidden')) return go(popsClose); });
  dl.addEventListener('cancel', function (e) { if (!$('pd-wa').classList.contains('hidden') || !$('pd-keys').classList.contains('hidden') || !$('pd-idf').classList.contains('hidden')) { e.preventDefault(); popsClose(); idEdit(false); } });

  // فتح عميل من رابط التنبيه (?lead=ID)
  var lm = /[?&]lead=(\d+)/.exec(location.search); if (lm) { var lr = root.querySelector('[data-mk-row][data-id="' + lm[1] + '"]'); if (lr) open(lr); }
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
