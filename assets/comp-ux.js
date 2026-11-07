/* المنافسين: أرقام فوق، كروت بمكانك قدام كل منافس، لوحة جانبية لكل منافس (تغييرات/أسعار/عروض)،
   سجل تغييرات متجمّع بالأيام مع فلاتر، ومقارنة أسعار بشريط مكان؛ كل الإجراءات من غير reload */
(function () {
  var X = window.CX, root = document.getElementById('cx'); if (!X || !root) return; var T = X.T, D = X.d, K = X.kinds;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function f(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function num(v) { return (+v || 0).toLocaleString('en-US'); }
  var KC = { price_up: '#dc2626', price_down: '#16a34a', price: '#d97706', offer_new: '#2563eb', offer_gone: '#64748b', svc_new: '#0891b2', svc_gone: '#64748b', hours: '#64748b', down: '#dc2626', up: '#16a34a' };
  var KI = { price_up: 'trend_up', price_down: 'trend_down', price: 'tag', offer_new: 'star', offer_gone: 'star', svc_new: 'plus', svc_gone: 'trash', hours: 'clock', down: 'alert', up: 'check' };
  var GR = [['#2563eb', '#06b6d4'], ['#7c3aed', '#ec4899'], ['#059669', '#84cc16'], ['#ea580c', '#f59e0b'], ['#0f766e', '#22d3ee'], ['#be123c', '#f97316']];
  function grad(n) { var h = 0; for (var i = 0; i < n.length; i++) h = (h * 31 + n.charCodeAt(i)) >>> 0; var g = GR[h % GR.length]; return 'linear-gradient(135deg,' + g[0] + ',' + g[1] + ')'; }
  function post(d) { var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('ajax', '1'); Object.keys(d).forEach(function (k) { fd.append(k, d[k]); });
    return fetch(X.self, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
  function after(j) { if (!j || !j.ok) return toast(T.err, 'error'); (j.flash || []).forEach(function (m) { toast(m.msg, m.type === 'error' ? 'error' : 'success'); }); D = j.d; paint(); }
  var flt = { kind: '', cid: '' }, onlyCheap = false;

  /* مكانك قدام كل منافس: كام خدمة هو أرخص/أغلى */
  function stand(cid) { var ch = 0, ex = 0; D.prices.forEach(function (r) { var h = r.them[cid]; if (h && h.d != null) { if (h.d < 0) ch++; else if (h.d > 0) ex++; } }); return { ch: ch, ex: ex }; }
  function evRow(e, withName) { var c = KC[e.kind] || '#64748b';
    return '<div class="cx-ev" style="--c:' + c + '"><span class="cx-evi">' + ic(KI[e.kind] || 'dots', 'w-3.5 h-3.5') + '</span><div class="min-w-0 flex-1"><div class="text-sm"><b class="cx-k">' + esc(K[e.kind] || e.kind) + '</b> ' + (withName ? '<b class="text-ink-900">' + esc(e.cname) + '</b> · ' : '') + '<span class="text-ink-700">' + esc(e.title) + '</span></div>' + (e.detail ? '<div class="text-xs text-ink-500" dir="auto">' + esc(e.detail) + '</div>' : '') + '</div><small class="text-[11px] text-ink-400 whitespace-nowrap">' + esc(e.date) + '</small></div>'; }
  function dayLabel(d) { var t = new Date(); var p = function (n) { return (n < 10 ? '0' : '') + n; }; var ds = function (x) { return x.getFullYear() + '-' + p(x.getMonth() + 1) + '-' + p(x.getDate()); };
    if (d === ds(t)) return T.today; t.setDate(t.getDate() - 1); if (d === ds(t)) return T.yday; return d.split('-').reverse().slice(0, 2).join('/'); }

  function addForm() { return '<form class="cx-add" data-add><b>' + esc(T.add) + '</b><label><span>' + esc(T.name) + '</span><input name="name" required maxlength="120" class="input"></label>' +
    '<label><span>' + esc(T.url) + '</span><div class="cx-url"><span class="cx-fav" data-fav>' + ic('link') + '</span><input name="url" required class="input" dir="ltr" placeholder="https://"></div></label>' +
    '<label><span>' + esc(T.links) + ' <small>' + esc(T.linksH) + '</small></span><textarea name="links" rows="2" class="input" dir="ltr"></textarea></label>' +
    '<label><span>' + esc(T.note) + ' <small>' + esc(T.opt) + '</small></span><input name="note" maxlength="500" class="input"></label><button class="btn-primary w-full">' + esc(T.addGo) + '</button><p class="cx-wait hidden"><span class="ai-spin"></span> ' + esc(T.reading) + '</p></form>'; }

  function paint() { var cs = D.comps;
    if (!cs.length) { root.innerHTML = '<div class="cx-empty"><span>' + ic('eye', 'w-8 h-8') + '</span><h2>' + esc(T.emptyT) + '</h2><p>' + esc(T.emptyD) + '</p><button type="button" class="btn-primary" data-open-add>' + ic('plus') + esc(T.first) + '</button></div>' + '<div class="cx-pop hidden" data-pop>' + addForm() + '</div>'; return; }
    var k = D.k;
    var h = '<div class="cx-top"><div class="cx-kpis">' + [[k.n, T.kN, '#2563eb', 'eye'], [k.changes, T.kCh, '#7c3aed', 'refresh'], [k.up, T.kUp, '#dc2626', 'trend_up'], [k.down, T.kDown, '#16a34a', 'trend_down'], [k.cheaper, T.kCheap, '#ea580c', 'tag']].map(function (x) {
      return '<div class="cx-kpi" style="--c:' + x[2] + '"><span>' + ic(x[3]) + '</span><b>' + num(x[0]) + '</b><small>' + esc(x[1]) + '</small></div>'; }).join('') + '</div>' +
      '<button type="button" class="btn-primary" data-open-add>' + ic('plus') + esc(T.add) + '</button></div><div class="cx-pop hidden" data-pop>' + addForm() + '</div>';
    // الكروت
    h += '<section class="cx-cards">' + cs.map(function (c) { var st = stand(c.id), ev = D.events.filter(function (e) { return e.cid === c.id; }), last = ev[0];
      return '<article class="cx-card" data-cid="' + c.id + '" tabindex="0"><div class="flex items-start gap-3"><span class="cx-av" style="background:' + grad(c.name) + '">' + esc(c.name.charAt(0)) + '</span><div class="min-w-0 flex-1"><b class="block truncate text-ink-900">' + esc(c.name) + '</b><a class="text-xs link" dir="ltr" href="' + esc(c.url) + '" target="_blank" rel="noopener" data-stop>' + esc(c.host) + '</a></div>' +
        '<span class="cx-st ' + (!c.checked ? 'never' : c.ok ? 'ok' : 'bad') + '" title="' + esc(c.checked) + '"><i></i>' + esc(!c.checked ? T.never : c.ok ? c.ago : T.down) + '</span></div>' +
        '<div class="cx-mini"><span><b>' + c.services.length + '</b> ' + esc(T.svc) + '</span><span><b>' + c.offers.length + '</b> ' + esc(T.offers) + '</span>' + (st.ch ? '<span class="cheap">' + esc(f(T.cheaperIn, st.ch)) + '</span>' : '') + (st.ex ? '<span class="exp">' + esc(f(T.pricierIn, st.ex)) + '</span>' : '') + '</div>' +
        (last ? evRow(last) : '<p class="text-xs text-ink-400">' + esc(T.noCh) + '</p>') +
        '<div class="cx-acts" data-stop><button type="button" class="btn-ghost !py-1 !text-xs" data-check>' + ic('refresh') + '<span>' + esc(T.check) + '</span></button>' + c.links.map(function (l) { return '<a class="cx-lk" href="' + esc(l) + '" target="_blank" rel="noopener" title="' + esc(l) + '">' + ic('link') + '</a>'; }).join('') +
        '<button type="button" class="cx-del" data-del title="' + esc(T.del) + '">' + ic('trash') + '</button></div></article>'; }).join('') + '</section>';
    // السجل + المقارنة
    var evs = D.events.filter(function (e) { return (!flt.kind || e.kind === flt.kind) && (!flt.cid || e.cid === +flt.cid); }), used = {}; D.events.forEach(function (e) { used[e.kind] = (used[e.kind] || 0) + 1; });
    var days = []; evs.forEach(function (e) { var g = days[days.length - 1]; if (!g || g.d !== e.day) days.push(g = { d: e.day, l: [] }); g.l.push(e); });
    h += '<div class="cx-grid"><section class="cx-box"><div class="cx-bh"><b>' + esc(T.feed) + '</b><select data-fc class="cx-sel"><option value="">' + esc(T.allC) + '</option>' + cs.map(function (c) { return '<option value="' + c.id + '"' + (+flt.cid === c.id ? ' selected' : '') + '>' + esc(c.name) + '</option>'; }).join('') + '</select></div>' +
      (D.events.length ? '<div class="cx-chips"><button type="button" data-fk=""' + (!flt.kind ? ' class="on"' : '') + '>' + esc(T.all) + ' <i>' + D.events.length + '</i></button>' + Object.keys(used).map(function (kk) { return '<button type="button" data-fk="' + kk + '" style="--c:' + (KC[kk] || '#64748b') + '"' + (flt.kind === kk ? ' class="on"' : '') + '>' + esc(K[kk] || kk) + ' <i>' + used[kk] + '</i></button>'; }).join('') + '</div>' : '') +
      '<div class="cx-feed">' + (days.length ? days.map(function (g) { return '<div class="cx-day"><span>' + esc(dayLabel(g.d)) + '</span>' + g.l.map(function (e) { return evRow(e, true); }).join('') + '</div>'; }).join('') : '<p class="text-sm text-ink-400 p-2">' + esc(T.feedNone) + '</p>') + '</div></section>';
    h += '<section class="cx-box cx-cmp"><div class="cx-bh"><b>' + esc(T.cmp) + '</b>' + (D.prices.length ? '<label class="cx-only"><input type="checkbox" data-only' + (onlyCheap ? ' checked' : '') + '> ' + esc(T.onlyCheap) + '</label>' : '') + '</div><p class="text-xs text-ink-500 mb-3">' + esc(T.cmpD) + '</p>';
    if (!D.prices.length) h += '<div class="cx-none">' + esc(T.cmpNone) + ' <a class="link" href="' + esc(X.kb) + '">' + esc(T.kbL) + '</a></div>';
    else { var has = function (r) { return cs.some(function (c) { var x = r.them[c.id]; return x && x.n != null; }); }, lone = D.prices.filter(function (r) { return !has(r); });
      var rows = D.prices.filter(has).filter(function (r) { return !onlyCheap || cs.some(function (c) { var x = r.them[c.id]; return x && x.d != null && x.d < 0; }); });
      h += '<div class="cx-rows">' + rows.map(function (r) { var pts = cs.map(function (c) { var x = r.them[c.id]; return x && x.n != null ? { c: c, x: x } : null; }).filter(Boolean), all = pts.map(function (p) { return p.x.n; }).concat(r.mn != null ? [r.mn] : []),
          lo = Math.min.apply(null, all), hi = Math.max.apply(null, all), pos = function (v) { return hi === lo ? 50 : (v - lo) / (hi - lo) * 100; };
        return '<div class="cx-row"><div class="cx-rn"><b>' + esc(r.name) + '</b><span dir="auto">' + esc(T.mine) + ': ' + esc(r.mine || '—') + '</span></div>' +
          '<div class="cx-bar">' + (all.length > 1 ? '<i class="cx-rng"></i>' : '') + pts.map(function (p) { return '<span class="cx-dot ' + (p.x.d < 0 ? 'ch' : p.x.d > 0 ? 'ex' : '') + '" style="--x:' + pos(p.x.n) + '%" title="' + esc(p.c.name + ': ' + p.x.price + (p.x.d != null ? ' (' + (p.x.d > 0 ? '+' : '') + p.x.d + '%)' : '')) + '">' + esc(p.c.name.charAt(0)) + '</span>'; }).join('') +
          (r.mn != null ? '<span class="cx-me" style="--x:' + pos(r.mn) + '%" title="' + esc(T.mine + ': ' + r.mine) + '">' + esc(T.pos) + '</span>' : '') + '</div>' +
          '<div class="cx-vals">' + pts.map(function (p) { return '<span class="' + (p.x.d < 0 ? 'ch' : p.x.d > 0 ? 'ex' : '') + '"><b>' + esc(p.c.name) + '</b> <span dir="auto">' + esc(p.x.price) + '</span>' + (p.x.d ? ' <em>' + (p.x.d > 0 ? '+' : '') + p.x.d + '%</em>' : '') + '</span>'; }).join('') + '</div></div>'; }).join('') + '</div>' +
        (lone.length ? '<details class="cx-lone"><summary>' + esc(f(T.lone, lone.length)) + '</summary><p>' + lone.map(function (r) { return esc(r.name) + ' <span dir="auto">(' + esc(r.mine || '—') + ')</span>'; }).join('، ') + '</p></details>' : '') +
        '<p class="cx-leg"><span class="ch">●</span> ' + esc(T.cheap) + ' <span class="ex">●</span> ' + esc(T.exp) + '</p>'; }
    root.innerHTML = h + '</section></div>' + sheetHtml(); }

  /* لوحة المنافس */
  var openId = null, sTab = 'ch', sq = '';
  function sheetHtml() { return '<div class="cx-sh' + (openId ? ' open' : '') + '" data-sh><div class="cx-shb" data-shx></div><aside>' + (openId ? sheetBody() : '') + '</aside></div>'; }
  function sheetBody() { var c = D.comps.filter(function (x) { return x.id === openId; })[0]; if (!c) return ''; var ev = D.events.filter(function (e) { return e.cid === c.id; }), st = stand(c.id);
    var head = '<header><span class="cx-av" style="background:' + grad(c.name) + '">' + esc(c.name.charAt(0)) + '</span><div class="min-w-0 flex-1"><b class="block text-lg text-ink-900 truncate">' + esc(c.name) + '</b><a class="text-xs link" dir="ltr" href="' + esc(c.url) + '" target="_blank" rel="noopener">' + esc(c.host) + ' ↗</a>' + (c.note ? '<p class="text-xs text-ink-500 mt-1">' + esc(c.note) + '</p>' : '') + '</div><button type="button" class="cx-x" data-shx>' + ic('x', 'w-5 h-5') + '</button></header>' +
      '<div class="cx-mini px-5">' + (st.ch ? '<span class="cheap">' + esc(f(T.cheaperIn, st.ch)) + '</span>' : '') + (st.ex ? '<span class="exp">' + esc(f(T.pricierIn, st.ex)) + '</span>' : '') + '<span>' + esc(c.checked) + '</span></div>' +
      '<nav class="cx-tabs">' + [['ch', T.tCh, ev.length], ['svc', T.tSvc, c.services.length], ['off', T.tOff, c.offers.length]].map(function (x) { return '<button type="button" data-st="' + x[0] + '"' + (sTab === x[0] ? ' class="on"' : '') + '>' + esc(x[1]) + ' <i>' + x[2] + '</i></button>'; }).join('') + '</nav>';
    var body = '';
    if (sTab === 'ch') body = ev.length ? ev.map(function (e) { return evRow(e); }).join('') : '<p class="text-sm text-ink-400">' + esc(T.noCh) + '</p>';
    if (sTab === 'svc') { var mine = {}; D.prices.forEach(function (r) { var x = r.them[c.id]; if (x) mine[x.price + '|' + r.name] = r; });
      var list = c.services.filter(function (s) { return !sq || s.name.toLowerCase().indexOf(sq) >= 0; });
      body = '<label class="mk-search !w-full !h-9 mb-3"><input type="search" data-sq value="' + esc(sq) + '" placeholder="' + esc(T.find) + '"></label>' + (c.services.length ? '<div class="cx-svcs">' + list.map(function (s) { var r = D.prices.filter(function (p) { var x = p.them[c.id]; return x && x.price === s.price && (p.name === s.name || true) && x.n != null; })[0], x = r ? r.them[c.id] : null;
        return '<div class="cx-svc"><span dir="auto">' + esc(s.name) + '</span><b dir="auto">' + esc(s.price) + '</b>' + (x && x.d ? '<em class="' + (x.d < 0 ? 'ch' : 'ex') + '" title="' + esc(T.vs + ' ' + r.mine) + '">' + (x.d > 0 ? '+' : '') + x.d + '%</em>' : '<em></em>') + '</div>'; }).join('') + '</div>' : '<p class="text-sm text-ink-400">' + esc(T.noSvc) + '</p>'); }
    if (sTab === 'off') body = c.offers.length ? c.offers.map(function (o) { return '<div class="cx-off">' + ic('star') + '<span dir="auto">' + esc(o) + '</span></div>'; }).join('') : '<p class="text-sm text-ink-400">' + esc(T.noOff) + '</p>';
    return head + '<div class="cx-shc">' + body + '</div>'; }
  function openSheet(id) { openId = id; sTab = 'ch'; sq = ''; var sh = root.querySelector('[data-sh]'); sh.querySelector('aside').innerHTML = sheetBody(); sh.classList.add('open'); document.documentElement.classList.add('hub-lock'); }
  function closeSheet() { openId = null; var sh = root.querySelector('[data-sh]'); if (sh) sh.classList.remove('open'); document.documentElement.classList.remove('hub-lock'); }

  root.addEventListener('click', function (e) { var t = e.target, b;
    if (t.closest('[data-open-add]')) { var pp = root.querySelector('[data-pop]'); pp.classList.toggle('hidden'); if (!pp.classList.contains('hidden')) pp.querySelector('[name=name]').focus(); return; }
    if ((b = t.closest('[data-fk]'))) { flt.kind = b.dataset.fk; paint(); return; }
    if ((b = t.closest('[data-st]'))) { sTab = b.dataset.st; root.querySelector('[data-sh] aside').innerHTML = sheetBody(); return; }
    if (t.closest('[data-shx]')) { closeSheet(); return; }
    var card = t.closest('[data-cid]'); if (!card) return; var id = +card.dataset.cid;
    if ((b = t.closest('[data-check]'))) { b.disabled = true; b.classList.add('pd-busy'); b.querySelector('span').textContent = T.checking; post({ action: 'check', id: id }).then(after).catch(function () { toast(T.err, 'error'); paint(); }); return; }
    if ((b = t.closest('[data-del]'))) { if (!b.classList.contains('sure')) { b.classList.add('sure'); b.innerHTML = esc(T.delQ); setTimeout(function () { if (b.isConnected) { b.classList.remove('sure'); b.innerHTML = ic('trash'); } }, 3000); return; }
      card.classList.add('pd-out'); post({ action: 'del', id: id }).then(after); return; }
    if (t.closest('[data-stop]')) return; openSheet(id); });
  root.addEventListener('keydown', function (e) { var c = e.target.closest('[data-cid]'); if (c && e.key === 'Enter' && e.target === c) openSheet(+c.dataset.cid); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && openId) closeSheet(); });
  root.addEventListener('change', function (e) { if (e.target.matches('[data-fc]')) { flt.cid = e.target.value; paint(); } if (e.target.matches('[data-only]')) { onlyCheap = e.target.checked; paint(); } });
  root.addEventListener('input', function (e) { var t = e.target;
    if (t.matches('[data-sq]')) { sq = t.value.trim().toLowerCase(); var a = root.querySelector('[data-sh] aside'); a.innerHTML = sheetBody(); var n = a.querySelector('[data-sq]'); n.focus(); n.setSelectionRange(n.value.length, n.value.length); }
    if (t.name === 'url' && t.closest('[data-add]')) { var v = t.value.trim().replace(/^https?:\/\//, '').split('/')[0], fv = t.closest('[data-add]').querySelector('[data-fav]'); fv.textContent = v ? v.charAt(0).toUpperCase() : ''; if (!v) fv.innerHTML = ic('link'); fv.classList.toggle('on', !!v);
      var nm = t.form.name; if (v && !nm.value) nm.placeholder = v.replace(/^www\./, '').split('.')[0]; } });
  root.addEventListener('submit', function (e) { var fm = e.target.closest('[data-add]'); if (!fm) return; e.preventDefault(); if (!fm.name.value.trim() && fm.name.placeholder) fm.name.value = fm.name.placeholder;
    var bt = fm.querySelector('.btn-primary'); bt.disabled = true; fm.querySelector('.cx-wait').classList.remove('hidden');
    post({ action: 'add', name: fm.name.value, url: fm.url.value, links: fm.links.value, note: fm.note.value }).then(function (j) { after(j); if (j && j.focus) { var c = root.querySelector('[data-cid="' + String(j.focus).replace('c', '') + '"]'); if (c) { c.classList.add('cx-new'); c.scrollIntoView({ block: 'center', behavior: 'smooth' }); } } })
      .catch(function () { bt.disabled = false; fm.querySelector('.cx-wait').classList.add('hidden'); toast(T.err, 'error'); }); });
  paint();
})();
