/* نافذة العميل المشتركة: نفس النافذة في العملاء المستهدفين والرسائل والتعليقات وأي صفحة.
   LeadPopup.open(rowEl) لصف موجود، أو LeadPopup.openId(id) من أي مكان، أو أي عنصر عليه data-lead-pop="ID". */
(function () {
  var C = window.LP; if (!C || !document.getElementById('pr-dlg')) return; var L = C.L, H = {};
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function post(url, d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { [].concat(d[k]).forEach(function (v) { f.append(k, v); }); }); return fetch(url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
  function $(id) { return document.getElementById(id); }
  function $$(q) { return [].slice.call(document.querySelectorAll(q)); }
  function q(r, s) { return (r && r.querySelector && r.querySelector(s)) || document.createElement('span'); }
  function dlg(id) { var d = $(id); d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) d.close(); }); return d; }
  /* ---------- بوب أب العميل ---------- */
  var dl = dlg('pr-dlg'), cur = null;
  function jd(r) { try { return JSON.parse(r.dataset.j); } catch (e) { return {}; } }
  var GR = [['#2563eb', '#06b6d4'], ['#7c3aed', '#ec4899'], ['#059669', '#84cc16'], ['#ea580c', '#f59e0b'], ['#0f766e', '#22d3ee'], ['#be123c', '#f97316']];
  function grad(n) { var h = 0; for (var i = 0; i < n.length; i++) h = (h * 31 + n.charCodeAt(i)) >>> 0; var g = GR[h % GR.length]; return 'linear-gradient(135deg,' + g[0] + ',' + g[1] + ')'; }
  function stagesPaint(v) { var ss = document.getElementById('pd-stg-sel'); if (ss && ss.value !== v) ss.value = v; var keys = Object.keys(C.stages), at = keys.indexOf(v);
    $$('#pd-stages [data-stg]').forEach(function (b, i) { i = b.dataset.i != null ? +b.dataset.i : i; b.classList.toggle('on', b.dataset.stg === v); b.classList.toggle('done', v !== 'lost' && i < at && b.dataset.stg !== 'lost'); b.classList.toggle('lost', b.dataset.stg === 'lost'); });
    $('pd-stage-pill').textContent = C.stages[v] || v; }
  function contactPaint(d) {
    var ph = d.phones && d.phones.length ? d.phones : (d.phone ? [d.phone] : []), em = d.emails && d.emails.length ? d.emails : (d.email ? [d.email] : []);
    function wa(p) { var x = String(p).replace(/\D/g, ''); return x.length >= 8 ? x : ''; }
    function line(k, v, i) { var w = k === 'phone' ? wa(v) : '', main = i === 0 && (k === 'phone' ? ph.length > 1 : em.length > 1);
      return '<span class="pd-cl' + (main ? ' main' : '') + '">' + ic(k === 'phone' ? 'phone' : 'mail', 'w-3.5 h-3.5 pd-cli') + '<b dir="ltr" title="' + esc(main ? L.cMain : '') + '">' + esc(v) + '</b><span class="pd-cla">' +
        (k === 'phone' ? '<a href="tel:' + esc(v) + '" data-log="call" title="' + esc(L.call) + '">' + ic('phone', 'w-3.5 h-3.5') + '</a>' + (w ? '<a href="https://wa.me/' + w + '" target="_blank" rel="noopener" data-log="wa" title="' + esc(L.wa) + '">' + ic('chat', 'w-3.5 h-3.5') + '</a>' : '') : '<a href="mailto:' + esc(v) + '" data-log="email" title="' + esc(L.mail) + '">' + ic('mail', 'w-3.5 h-3.5') + '</a>') +
        '<button type="button" data-copy="' + esc(v) + '" title="' + esc(L.copy) + '">' + ic('copy', 'w-3 h-3') + '</button>' + (i > 0 ? '<button type="button" class="mk" data-cmain data-k="' + k + '" data-v="' + esc(v) + '" title="' + esc(L.cMk) + '">' + ic('star', 'w-3 h-3') + '</button>' : '') +
        '<button type="button" class="del" data-cdel data-k="' + k + '" data-v="' + esc(v) + '" title="' + esc(L.cDel) + '">' + ic('x', 'w-3 h-3') + '</button></span></span>'; }
    $('pd-contact').innerHTML = ph.map(function (v, i) { return line('phone', v, i); }).join('') + em.map(function (v, i) { return line('email', v, i); }).join('') + '<button type="button" class="pd-cadd" data-caddo>' + ic('plus', 'w-3.5 h-3.5') + esc(L.cAdd) + '</button>';
    var af = $('pd-addc'); af.classList.add('hidden'); if (!ph.length) af.k.value = 'phone'; else if (!em.length) af.k.value = 'email'; }
  function pd(t) { var m = /(\d{2})\/(\d{2})\/(\d{4})/.exec(t || ''); return m ? new Date(+m[3], m[2] - 1, +m[1]) : null; }
  function heat(d) { return d.stage === 'won' ? 100 : d.stage === 'lost' ? 0 : +(d.score || 0); }
  function heatPaint(d) { var v = heat(d), el = $('pd-heat'), c = v >= 70 ? '#ef4444' : v >= 40 ? '#f59e0b' : '#38bdf8';
    el.style.setProperty('--v', v); el.style.setProperty('--c', c); el.textContent = v; el.title = L.heat.replace('%s', v) + ' · ' + (v >= 70 ? L.hot : v >= 40 ? L.warm : L.cold) + (d.why && d.why.length ? '\n' + d.why.join('، ') : ''); }
  function vis() { var v = H.rows ? H.rows() : []; return v.indexOf(cur) > -1 ? v : [cur]; }
  function posPaint() { var v = vis(), i = v.indexOf(cur); $('pd-pos').textContent = v.length > 1 ? (i + 1) + ' / ' + v.length : ''; $$('#pr-dlg [data-nav]').forEach(function (b) { b.classList.toggle('hidden', v.length < 2); }); }
  function loadActs(r, d) {
    $('pd-acts').innerHTML = '<li class="h-12 mk-sk"></li><li class="h-12 mk-sk"></li><li class="h-12 mk-sk"></li>'; $('pd-acts-n').textContent = '';
    fetch(C.selfUrl + '&ajax=lead&id=' + d.id, { credentials: 'same-origin' }).then(function (x) { if (!x.ok) throw new Error('HTTP ' + x.status); return x.text(); })
      .then(function (t) { var j; try { j = JSON.parse(t.slice(t.indexOf('{'))); } catch (e) { throw new Error('bad json'); } // لو السيرفر طبع تحذير قبل الرد
        if (cur !== r) return; if (!j.ok) throw new Error('not ok');
        acts(j.acts); nextShow(j.next, j.due); var dd = jd(r); dd.next = j.next || ''; dd.due = j.due || 0; dd.next_at = ''; if (j.next_note !== undefined) dd.next_note = j.next_note; r.dataset.j = JSON.stringify(dd); HK.data.forEach(function (fn) { try { fn(j, d, r); } catch (e) { if (window.console) console.error(e); } }); if (j.company) { $('pd-co').href = j.company; $('pd-co').classList.remove('hidden'); } })
      .catch(function (e) { if (cur !== r) return; if (window.console) console.warn('lead acts', e);
        $('pd-acts').innerHTML = '<li class="!ps-0 text-sm text-ink-500">' + esc(L.loadErr) + ' <button type="button" class="link" data-retry>' + esc(L.retry) + '</button></li>'; }); }
  (function sheet() { var hero = document.querySelector('#pr-dlg .pd-hero'), side = document.querySelector('#pr-dlg .pd-side'); if (!hero || !side || hero.querySelector('.pd-info')) return;
    var info = document.createElement('div'); info.className = 'pd-info'; var meta = document.getElementById('pd-meta'), own = document.getElementById('pd-owner'), tg = document.getElementById('pd-tags');
    if (meta) info.appendChild(meta); if (own) { var w = document.createElement('label'); w.className = 'pd-own'; w.innerHTML = (window.uic ? uic('user', 'w-3.5 h-3.5') : ''); w.appendChild(own); info.appendChild(w); }
    if (tg) { var tw = tg.parentNode; tw.classList.add('pd-tagw'); info.appendChild(tw); }
    var ct = document.getElementById('pd-contact'); ct.parentNode.insertBefore(info, ct); side.classList.add('hidden'); document.querySelector('#pr-dlg .pd-body').classList.add('pd-one'); })();
  function open(r) {
    cur = r; var d = jd(r);
    var av = $('pd-av'); av.textContent = (d.name || '?').trim().charAt(0).toUpperCase(); av.style.background = grad(d.name || '?');
    $('pd-name').textContent = d.name || '—'; $('pd-sub').textContent = [d.title, d.company].filter(Boolean).join(' · ') || L.noTitle;
    $('pd-src-pill').textContent = d.src || ''; contactPaint(d); $('pd-addc').reset();
    $('pd-stage').value = d.stage; stagesPaint(d.stage); if ($('pd-owner')) $('pd-owner').value = String(d.owner || 0);
    $('pd-meta').innerHTML = [[L.added, d.added, 'clock'], [L.last, d.last || '—', 'chat'], [L.country, d.country || '—', 'globe'], [L.src, d.src, 'layers']].map(function (x) { return x[1] && x[1] !== '—' ? '<div title="' + esc(x[0]) + '"><dt>' + ic(x[2], 'w-3.5 h-3.5') + '</dt><dd>' + esc(x[1]) + '</dd></div>' : ''; }).join('');
    tags(d.tags); $('pd-open').href = C.leadUrl + '#lead-' + d.id; $('pd-co').classList.add('hidden');
    nextShow(d.next, d.due); $('pd-next-note').value = d.next_note || ''; $('pd-next-at').value = '';
    heatPaint(d); idEdit(false); popsClose(); tf = '';
    var bx = dl.querySelector('.pd-hero'); bx.classList.remove('pd-in-a'); void bx.offsetWidth; bx.classList.add('pd-in-a');
    posPaint(); loadActs(r, d); if (!dl.open) dl.showModal(); HK.open.forEach(function (fn) { fn(d, r); });
    $$('[data-mk-row]').forEach(function (x) { x.classList.toggle('mk-focus', x === r); }); if (r.isConnected) r.scrollIntoView({ block: 'nearest' });
  }
  function nav(step) { var v = vis(), i = v.indexOf(cur), n = v[i + step]; if (n) open(n); }
  dl.addEventListener('click', function (e) { var b = e.target.closest('[data-nav]'); if (b) return nav(+b.dataset.nav); if (e.target.closest('[data-retry]') && cur) loadActs(cur, jd(cur));
    var s = e.target.closest('[data-stg]'); if (s && cur && s.dataset.stg !== $('pd-stage').value) { $('pd-stage').value = s.dataset.stg; stagesPaint(s.dataset.stg); $('pd-stage').dispatchEvent(new Event('change')); } });
  dl.addEventListener('keydown', function (e) { if (/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) return; var rtl = document.documentElement.dir === 'rtl';
    if (e.key === 'ArrowLeft') { e.preventDefault(); nav(rtl ? 1 : -1); } if (e.key === 'ArrowRight') { e.preventDefault(); nav(rtl ? -1 : 1); } });
  function cApply(r, k, j) { var d = jd(r); d[k + 's'] = j.list; upd(r, k + 's', j.list); upd(r, k, j.main); if (k === 'phone') upd(r, 'wa', j.wa); d = jd(r);
    r.dataset.phone = d.phone ? 1 : 0; r.dataset.email = d.email ? 1 : 0; r.dataset.none = d.phone || d.email ? 0 : 1; contactPaint(d); cDirty = true; }
  var cDirty = false; dl.addEventListener('close', function () { if (cDirty && window.PRList) { cDirty = false; window.PRList.reload(); } });
  $('pd-addc').addEventListener('submit', function (e) { e.preventDefault(); var f = this, r = cur, b = f.querySelector('button'), k = f.k.value, v = f.v.value.trim(); if (!v) return f.v.focus(); b.disabled = true;
    post(C.selfUrl, { action: 'contacts', id: jd(r).id, k: k, op: 'add', v: v }).then(function (j) { b.disabled = false; if (!j.ok) return toast(j.error || L.err, 'error'); f.v.value = ''; cApply(r, k, j); toast(L.saved); }).catch(function () { b.disabled = false; toast(L.err, 'error'); }); });
  $('pd-contact').addEventListener('click', function (e) { if (!e.target.closest('[data-caddo]')) return; var af = $('pd-addc'); af.classList.toggle('hidden'); if (!af.classList.contains('hidden')) af.v.focus(); });
  /* الهيدر تفاعلي: الاسم يتعدّل، الحالة والموعد بقايمة صغيرة */
  (function () { var inf = dl.querySelector('.pd-info'), ct = $('pd-contact'), ad = $('pd-addc'); if (inf && ct) { inf.appendChild(ct); if (ad) inf.appendChild(ad); } })();
  var hm = document.createElement('div'); hm.className = 'pd-hm hidden'; dl.appendChild(hm);
  function hmClose() { hm.classList.add('hidden'); }
  function hmOpen(anchor, html) { hm.innerHTML = html; hm.classList.remove('hidden'); var r = anchor.getBoundingClientRect(), dr = dl.getBoundingClientRect(); hm.style.top = (r.bottom - dr.top + 6) + 'px'; var x = r.left - dr.left; hm.style.left = Math.max(8, Math.min(x, dr.width - hm.offsetWidth - 8)) + 'px'; }
  dl.addEventListener('click', function (e) { if (!e.target.closest('.pd-hm') && !e.target.closest('#pd-stage-pill,#pd-due-pill,[data-hnext]')) hmClose(); });
  $('pd-name').addEventListener('click', function () { $('pd-edit').click(); }); $('pd-name').title = L.hEdit;
  $('pd-stage-pill').addEventListener('click', function () { var v = $('pd-stage').value;
    hmOpen(this, '<b>' + esc(L.hStage) + '</b>' + Object.keys(C.stages).map(function (k) { return '<button type="button" data-hs="' + k + '" class="' + (k === v ? 'on' : '') + '">' + esc(C.stages[k]) + '</button>'; }).join('')); });
  function nextMenu(anchor) { var nb = $('pd-next-b');
    hmOpen(anchor, '<b>' + esc(L.hNext) + '</b>' + [].map.call(nb.querySelectorAll('[data-when]'), function (x) { return '<button type="button" data-hw="' + esc(x.dataset.when) + '">' + esc(x.textContent) + '</button>'; }).join('') + '<label>' + esc(L.hAt) + '<input type="datetime-local" data-hat></label>'); }
  $('pd-due-pill').addEventListener('click', function () { nextMenu(this); });
  hm.addEventListener('click', function (e) { var b = e.target.closest('[data-hs]'); if (b) { hmClose(); var s2 = document.querySelector('#pd-stages [data-stg="' + b.dataset.hs + '"]'); if (s2) s2.click(); return; }
    b = e.target.closest('[data-hw]'); if (b) { hmClose(); var w2 = document.querySelector('#pd-next-b [data-when="' + b.dataset.hw + '"]'); if (w2) w2.click(); } });
  hm.addEventListener('change', function (e) { if (!e.target.matches('[data-hat]') || !e.target.value) return; var i = $('pd-next-at'); i.value = e.target.value; i.dispatchEvent(new Event('change')); hmClose(); });
  $('pd-contact').addEventListener('click', function (e) { var b = e.target.closest('[data-cmain]'); if (!b) return; e.preventDefault(); var r = cur, k = b.dataset.k; b.disabled = true;
    post(C.selfUrl, { action: 'contacts', id: jd(r).id, k: k, op: 'main', v: b.dataset.v }).then(function (j) { if (!j.ok) { b.disabled = false; return toast(j.error || L.err, 'error'); } cApply(r, k, j); toast(L.cMkDone); }).catch(function () { b.disabled = false; toast(L.err, 'error'); }); });
  $('pd-contact').addEventListener('click', function (e) { var b = e.target.closest('[data-cdel]'); if (!b) return; e.preventDefault(); var r = cur, k = b.dataset.k; if (!confirm((L.cDelQ || '%s').replace('%s', b.dataset.v))) return;
    post(C.selfUrl, { action: 'contacts', id: jd(r).id, k: k, op: 'del', v: b.dataset.v }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); cApply(r, k, j); toast(L.saved); }).catch(function () { toast(L.err, 'error'); }); });
  // المتابعة وسجل التواصل
  var AIC = { call: 'phone', wa: 'chat', email: 'mail', meet: 'users', note: 'pencil', stage: 'refresh' };
  var KC = { call: '#16a34a', wa: '#22c55e', email: '#2563eb', meet: '#d97706', note: '#64748b', stage: '#7c3aed' };
  function ago(t) { var m = /(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})/.exec(t || ''); if (!m) return t; var d = new Date(+m[3], m[2] - 1, +m[1], +m[4], +m[5]), s = (Date.now() - d) / 1000;
    return s < 90 ? L.now : s < 3600 ? L.minAgo.replace('%s', Math.round(s / 60)) : s < 86400 ? L.hrAgo.replace('%s', Math.round(s / 3600)) : s < 86400 * 30 ? L.dayAgo.replace('%s', Math.round(s / 86400)) : t; }
  var lastActs = [], tf = '';
  function acts(a) { lastActs = a; var cnt = {}; a.forEach(function (x) { cnt[x.kind] = (cnt[x.kind] || 0) + 1; }); var ks = Object.keys(cnt);
    $('pd-tf').innerHTML = ks.length > 1 ? ['<button type="button" class="pd-tfb' + (tf ? '' : ' on') + '" data-tf="">' + esc(L.all) + '</button>'].concat(ks.map(function (k) { return '<button type="button" class="pd-tfb' + (tf === k ? ' on' : '') + '" data-tf="' + k + '" style="--k:' + (KC[k] || '#64748b') + '">' + ic(AIC[k] || 'dots', 'w-3 h-3') + cnt[k] + '</button>'; })).join('') : '';
    if (tf) a = a.filter(function (x) { return x.kind === tf; }); actsList(a); }
  function actsList(a) { $('pd-acts-n').textContent = lastActs.length ? lastActs.length : ''; $('pd-acts').innerHTML = a.length ? a.map(function (x) { return '<li class="cp-pop"><span class="pd-dot text-white" style="background:' + (KC[x.kind] || '#64748b') + '">' + ic(AIC[x.kind] || 'dots', 'w-2.5 h-2.5') + '</span><div class="rounded-xl border border-ink-100 bg-white px-3 py-2"><div class="flex items-center gap-2 text-[11px] text-ink-500"><b class="text-ink-800 text-xs">' + esc(x.label) + '</b><span title="' + esc(x.at) + '">' + esc(ago(x.at)) + '</span>' + (x.who ? '<span class="ms-auto">' + esc(x.who) + '</span>' : '') + '</div>' + (x.body ? bodyHtml(x.body) : '') + '</div></li>'; }).join('') : '<li class="!ps-0 text-center py-8 text-sm text-ink-400">' + ic('chat', 'w-6 h-6 mx-auto mb-2 opacity-50') + esc(L.noActs) + '</li>'; }
  function nextShow(n, due) { var el = $('pd-next'); el.textContent = n || L.noNext; el.className = 'ms-auto tabular-nums text-xs ' + (due ? 'text-red-600 font-semibold' : 'text-ink-600'); var p = $('pd-due-pill'); if (p) { p.classList.remove('hidden'); p.classList.toggle('none', !n); p.innerHTML = ic(due ? 'alert' : 'calendar', 'w-3 h-3 inline -mt-0.5') + ' ' + esc(n || L.hNone); p.style.background = due ? 'rgba(220,38,38,.85)' : ''; } }
  function rowNext(r, j) { upd(r, 'next', j.next || ''); upd(r, 'next_note', j.next_note || ''); var c = q(r, '[data-c=next]'); r.dataset.due = j.due && r.dataset.stage !== 'won' && r.dataset.stage !== 'lost' ? '1' : '0';
    r.dataset.cold = '0'; c.className = 'block truncate tabular-nums ' + (r.dataset.due === '1' ? 'text-red-600 font-medium' : 'text-ink-400'); c.innerHTML = j.next ? ic('calendar', 'w-3 h-3 inline') + ' ' + esc(j.next.slice(0, 5)) : ''; }
  function setNext(idsArr, when, extra) { var d = { action: 'next', when: when, 'ids[]': idsArr }; Object.keys(extra || {}).forEach(function (k) { d[k] = extra[k]; }); return post(C.selfUrl, d); }
  $('pd-next-b').addEventListener('click', function (e) { var b = e.target.closest('[data-when]'); if (!b || !cur) return; var r = cur;
    setNext([jd(r).id], b.dataset.when, { note: $('pd-next-note').value }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); nextShow(j.next, j.due); rowNext(r, j); toast(L.saved); }); });
  $('pd-next-at').addEventListener('change', function () { var v = this.value, r = cur; if (!v) return;
    setNext([jd(r).id], 'at', { at: v, note: $('pd-next-note').value }).then(function (j) { if (!j.ok) return toast(j.error || L.err, 'error'); nextShow(j.next, j.due); rowNext(r, j); toast(L.saved); }); });
  $('pd-next-note').addEventListener('change', function () { var r = cur, d = jd(r); if (!d.next) return; setNext([d.id], 'note', { note: this.value }).then(function (j) { if (j.ok) upd(r, 'next_note', j.next_note); }); });
  function logged(r, j) { acts(j.acts); if (j.last) { upd(r, 'last', j.last); var a = q(r, '[data-c=ago]'); a.textContent = L.today; r.setAttribute('data-s-last', new Date().toISOString().slice(0, 19).replace('T', ' ')); r.dataset.cold = '0'; } }
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
    upd(r, 'stage', v); q(r, '[data-stage-pill]').textContent = C.stages[v] || v; r.dataset.sStage = v; if (v === 'won') { confetti(); toast(L.won); } else toast(L.saved); loadActs(r, jd(r)); }); });
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
      $('pd-av').textContent = j.name.trim().charAt(0).toUpperCase(); var nb = q(r, 'b'); if (nb) nb.textContent = j.name; idEdit(false); toast(L.saved); }).catch(function () { toast(L.err, 'error'); }); });
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
  function fill(t) { var d = jd(cur); return t.replace(/\{name\}/g, (d.name || '').split(/\s+/)[0]).replace(/\{company\}/g, d.company || '').replace(/\{me\}/g, L.me || '').replace(/\{book\}/g, L.book || ''); }
  function waOpen() { var d = jd(cur); if (!d.wa) return; var p = $('pd-wa');
    var T = L.waTpl || []; $('pd-wa-l').innerHTML = T.map(function (t, i) { return '<button type="button" class="pd-wat" data-wat="' + i + '"><b>' + esc(t.n) + '</b><span>' + esc(fill(t.b)) + '</span></button>'; }).join('') + (L.editTpl ? '<a class="link text-[11px] px-2" href="' + esc(L.editTpl + d.id) + '">' + esc(L.editTplL) + '</a>' : '');
    $('pd-wa-t').value = T[0] ? fill(T[0].b) : ''; p.classList.remove('hidden'); $('pd-keys').classList.add('hidden'); }
  $('pd-wa-l').addEventListener('click', function (e) { var b = e.target.closest('[data-wat]'); if (!b) return; $('pd-wa-t').value = fill(L.waTpl[+b.dataset.wat].b);
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
    if (k === 'f') return go(function () { var t = document.querySelector('[data-pt="ct"]'); if (t) t.click(); var tk = document.getElementById('pd-p-tk'); if (tk) tk.scrollIntoView({ block: 'start', behavior: 'smooth' }); setTimeout(function () { var i = document.querySelector('[data-tform] [name=title]'); if (i) i.focus(); }, 150); });
    if (k === 'w') return go(waOpen);
    if (k === 'c' || k === 'e') return go(function () { var a = $('pd-contact').querySelector('a[data-log="' + (k === 'c' ? 'call' : 'email') + '"]:not(.off)'); if (a) a.click(); });
    if (k === '?' || (e.shiftKey && k === '/')) return go(function () { $('pd-keys-b').click(); });
    if (k === 'escape' && !$('pd-wa').classList.contains('hidden') + !$('pd-keys').classList.contains('hidden')) return go(popsClose); });
  dl.addEventListener('cancel', function (e) { if (!$('pd-wa').classList.contains('hidden') || !$('pd-keys').classList.contains('hidden') || !$('pd-idf').classList.contains('hidden')) { e.preventDefault(); popsClose(); idEdit(false); } });


  // ملاحظة صوتية في السجل بتتشغل مكانها
  function bodyHtml(b) { var m = /^🎙 voice:(\S+)\s*(\S*)\n?([\s\S]*)$/.exec(b); if (!m || !C.X) return '<p class="text-sm text-ink-800 break-words mt-0.5" dir="auto">' + esc(b) + '</p>';
    return '<div class="pd-voice"><audio controls preload="none" src="' + esc(C.X.voice + encodeURIComponent(m[1])) + '"></audio><small>' + esc(m[2]) + '</small></div>' + (m[3] ? '<p class="text-sm text-ink-800 break-words mt-0.5" dir="auto">' + esc(m[3]) + '</p>' : ''); }
  var HK = { data: [], open: [] };
  // تحديث العميل بعد أي عملية من الأدوات: بيانات الكارت من السيرفر + الصف في القايمة
  function refresh() { var r = cur; if (!r) return Promise.resolve(); return fetch(C.selfUrl + '&ajax=card&id=' + jd(r).id, { credentials: 'same-origin' }).then(function (x) { return x.text(); }).then(function (t) { var j = JSON.parse(t.slice(t.indexOf('{'))); if (!j.ok) return;
      r.dataset.j = JSON.stringify(j.d); r.dataset.stage = j.d.stage; q(r, '[data-stage-pill]').textContent = C.stages[j.d.stage] || j.d.stage; if (cur === r) open(r); if (H.onChange) H.onChange(r, j.d); }); }
  function rowFor(d) { var e = document.createElement('div'); e.dataset.j = JSON.stringify(d); e.dataset.id = d.id; e.dataset.stage = d.stage; e.dataset.q = ''; return e; }
  function openId(id) { return fetch(C.selfUrl + '&ajax=card&id=' + id, { credentials: 'same-origin' }).then(function (x) { return x.text(); }).then(function (t) { var j = JSON.parse(t.slice(t.indexOf('{')));
    if (!j.ok) throw new Error('no lead'); var ex = document.querySelector('[data-mk-row][data-id="' + id + '"]'); if (ex) { ex.dataset.j = JSON.stringify(j.d); open(ex); } else open(rowFor(j.d)); }).catch(function () { toast(L.err, 'error'); }); }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-lead-pop]'); if (!b) return; e.preventDefault(); openId(+b.dataset.leadPop); });
  dl.addEventListener('close', function () { if (H.onClose && cur) H.onClose(jd(cur)); });
  function touched(r) { q(r, '[data-c=ago]').textContent = L.today; r.setAttribute('data-s-last', new Date().toISOString().slice(0, 19).replace('T', ' ')); r.dataset.cold = '0'; }
  window.LeadPopup = { log: log, touched: touched, cur: function () { return cur; }, refresh: refresh, reload: function () { if (cur) loadActs(cur, jd(cur)); }, onData: function (f) { HK.data.push(f); }, onOpen: function (f) { HK.open.push(f); }, confetti: confetti, hostRows: function () { return vis(); }, H: function () { return H; }, open: open, openId: openId, jd: jd, upd: upd, rowNext: rowNext, setNext: setNext, bulk: bulk, host: function (h) { H = h || {}; } };
  var lm = /[?&]lead=(\d+)/.exec(location.search); if (lm && !/[?&]p=crm_leads/.test(location.search)) openId(+lm[1]);
})();
