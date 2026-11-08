/* المنافسين: أرقام فوق، كروت بمكانك قدام كل منافس، لوحة جانبية لكل منافس (تغييرات/أسعار/عروض)،
   سجل تغييرات متجمّع بالأيام مع فلاتر، ومقارنة أسعار بشريط مكان؛ كل الإجراءات من غير reload */
(function () {
  var X = window.CX, root = document.getElementById('cx'); if (!X || !root) return; var T = X.T, D = X.d, K = X.kinds;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function f(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function num(v) { return (+v || 0).toLocaleString('en-US'); }
  function kfmt(v) { if (v == null) return '—'; var a = Math.abs(v), s = v < 0 ? '-' : ''; return s + (a >= 1e6 ? (a / 1e6).toFixed(a >= 1e7 ? 0 : 1) + 'M' : a >= 1e4 ? (a / 1e3).toFixed(a >= 1e5 ? 0 : 1) + 'K' : a.toLocaleString('en-US')); }
  var KC = { price_up: '#dc2626', price_down: '#16a34a', price: '#d97706', offer_new: '#2563eb', offer_gone: '#64748b', svc_new: '#0891b2', svc_gone: '#64748b', hours: '#64748b', down: '#dc2626', up: '#16a34a', soc_bio: '#db2777', soc_name: '#db2777', soc_link: '#db2777', ad_new: '#7c3aed', ad_stop: '#64748b' };
  var KI = { price_up: 'trend_up', price_down: 'trend_down', price: 'tag', offer_new: 'star', offer_gone: 'star', svc_new: 'plus', svc_gone: 'trash', hours: 'clock', down: 'alert', up: 'check', soc_bio: 'pencil', soc_name: 'pencil', soc_link: 'link', ad_new: 'megaphone', ad_stop: 'megaphone' };
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
        (c.soc && c.soc.followers ? '<div class="cs-cardl">' + ic('users', 'w-3.5 h-3.5') + '<b>' + kfmt(c.soc.followers) + '</b> ' + esc(T.sFol) + (c.soc.g7 != null && c.soc.g7 !== 0 ? ' <em class="' + (c.soc.g7 > 0 ? 'up' : 'dn') + '">' + (c.soc.g7 > 0 ? '+' : '') + kfmt(c.soc.g7) + ' ' + esc(T.sWeek) + '</em>' : '') + '</div>' : '') +
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
      '<nav class="cx-tabs">' + [['ch', T.tCh, ev.length], ['svc', T.tSvc, c.services.length], ['off', T.tOff, c.offers.length], ['soc', T.tSoc, c.soc ? c.soc.n : 0], ['ads', T.tAds, c.ads || 0]].map(function (x) { return '<button type="button" data-st="' + x[0] + '"' + (sTab === x[0] ? ' class="on"' : '') + '>' + esc(x[1]) + ' <i>' + x[2] + '</i></button>'; }).join('') + '</nav>';
    var body = '';
    if (sTab === 'ch') body = ev.length ? ev.map(function (e) { return evRow(e); }).join('') : '<p class="text-sm text-ink-400">' + esc(T.noCh) + '</p>';
    if (sTab === 'svc') { var mine = {}; D.prices.forEach(function (r) { var x = r.them[c.id]; if (x) mine[x.price + '|' + r.name] = r; });
      var list = c.services.filter(function (s) { return !sq || s.name.toLowerCase().indexOf(sq) >= 0; });
      body = '<label class="mk-search !w-full !h-9 mb-3"><input type="search" data-sq value="' + esc(sq) + '" placeholder="' + esc(T.find) + '"></label>' + (c.services.length ? '<div class="cx-svcs">' + list.map(function (s) { var r = D.prices.filter(function (p) { var x = p.them[c.id]; return x && x.price === s.price && (p.name === s.name || true) && x.n != null; })[0], x = r ? r.them[c.id] : null;
        return '<div class="cx-svc"><span dir="auto">' + esc(s.name) + '</span><b dir="auto">' + esc(s.price) + '</b>' + (x && x.d ? '<em class="' + (x.d < 0 ? 'ch' : 'ex') + '" title="' + esc(T.vs + ' ' + r.mine) + '">' + (x.d > 0 ? '+' : '') + x.d + '%</em>' : '<em></em>') + '</div>'; }).join('') + '</div>' : '<p class="text-sm text-ink-400">' + esc(T.noSvc) + '</p>'); }
    if (sTab === 'soc') body = '<div data-soc>' + (SOC[c.id] ? socBody(c, SOC[c.id]) : '<p class="text-sm text-ink-400"><span class="ai-spin"></span> ' + esc(T.sLoading) + '</p>') + '</div>';
    if (sTab === 'ads') body = '<div data-ads>' + (ADS[c.id] ? adsBody(c, ADS[c.id]) : '<p class="text-sm text-ink-400"><span class="ai-spin"></span> ' + esc(T.aLoading) + '</p>') + '</div>';
    if (sTab === 'off') body = c.offers.length ? c.offers.map(function (o) { return '<div class="cx-off">' + ic('star') + '<span dir="auto">' + esc(o) + '</span></div>'; }).join('') : '<p class="text-sm text-ink-400">' + esc(T.noOff) + '</p>';
    return head + '<div class="cx-shc">' + body + '</div>'; }

  /* ---------- السوشيال ---------- */
  var SOC = {}, socF = { pl: '', sort: 'new', q: '', more: 30 }, pollT = null;
  var PL = { instagram: ['#e1306c', 'IG'], tiktok: ['#111827', 'TT'], facebook: ['#1877f2', 'FB'], youtube: ['#ff0000', 'YT'] };
  var TY = { reel: 'Reels', image: 'tImage', carousel: 'tCarousel', video: 'tVideo', short: 'Shorts', text: 'tText' };
  function tyName(t) { var k = TY[t] || t; return T[k] || k; }
  function plBadge(pl) { var x = PL[pl] || ['#64748b', pl.slice(0, 2).toUpperCase()]; return '<i class="cs-pl" style="--c:' + x[0] + '">' + x[1] + '</i>'; }
  function spark(sr) { if (!sr || sr.length < 2) return '<div class="cs-spk none">' + esc(T.sSoon) + '</div>'; var v = sr.map(function (r) { return r[1]; }), lo = Math.min.apply(null, v), hi = Math.max.apply(null, v), w = 160, h = 36;
    var pts = v.map(function (y, i) { return (i / (v.length - 1) * w).toFixed(1) + ',' + (hi === lo ? h / 2 : h - 3 - (y - lo) / (hi - lo) * (h - 6)).toFixed(1); }).join(' ');
    return '<svg class="cs-spk" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none"><polyline points="' + pts + '" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>'; }
  function delta(g) { if (!g) return '<span class="cs-d">—</span>'; return '<span class="cs-d ' + (g.abs > 0 ? 'up' : g.abs < 0 ? 'dn' : '') + '">' + (g.abs > 0 ? '+' : '') + kfmt(g.abs) + (g.pct != null ? ' (' + (g.pct > 0 ? '+' : '') + g.pct + '%)' : '') + '</span>'; }
  function postRow(x, rank) { var m = [x.views != null ? ic('eye', 'w-3 h-3') + kfmt(x.views) : '', ic('heart', 'w-3 h-3') + kfmt(x.likes), ic('chat', 'w-3 h-3') + kfmt(x.comments), x.shares != null ? ic('share', 'w-3 h-3') + kfmt(x.shares) : ''].filter(Boolean).map(function (z) { return '<span>' + z + '</span>'; }).join('');
    return '<a class="cs-post" href="' + esc(x.url || '#') + '" target="_blank" rel="noopener">' + (rank ? '<b class="cs-rk">' + rank + '</b>' : '') + (x.thumb ? '<img src="' + esc(x.thumb) + '" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">' : '') +
      '<div class="min-w-0 flex-1"><div class="cs-pm">' + plBadge(x.platform) + '<span>' + esc(tyName(x.type)) + '</span>' + (x.paid ? '<span class="cs-paid">' + esc(T.sPaid) + '</span>' : '') + '<span class="ms-auto">' + esc(x.at) + '</span></div>' +
      '<p dir="auto">' + esc(x.caption || '—') + '</p><div class="cs-pn">' + m + (x.er != null ? '<span class="cs-er">' + esc(T.sEr) + ' <bdi dir="ltr">' + x.er + '%</bdi></span>' : '') + '</div></div></a>'; }
  function socBody(c, r) {
    var h = '<div class="cs-head"><div>' + (r.accounts.length ? '<b>' + kfmt(r.total) + '</b> ' + esc(T.sFolAll) : esc(T.sNoAcc)) + '</div>' +
      (r.on ? '<button type="button" class="btn-ghost !py-1 !text-xs" data-srefresh' + (r.accounts.some(function (a) { return a.pending; }) ? ' disabled' : '') + '>' + ic('refresh') + '<span>' + esc(r.accounts.some(function (a) { return a.pending; }) ? T.sReading : T.sRefresh) + '</span></button>' : '') + '</div>';
    if (!r.on) h += '<div class="cs-note">' + esc(T.sNoApify) + '</div>';
    if (r.fast) h += '<div class="cs-fast">' + ic('trend_up', 'w-4 h-4') + esc(f(T.sFast, r.fast.platform, (r.fast.pct > 0 ? '+' : '') + r.fast.pct + '%')) + '</div>';
    h += r.accounts.map(function (a) {
      return '<div class="cs-acc' + (a.ok || a.pending ? '' : ' bad') + '"><div class="cs-ah">' + plBadge(a.platform) + '<div class="min-w-0 flex-1"><a class="font-semibold text-ink-900 truncate block" href="' + esc(a.url) + '" target="_blank" rel="noopener" dir="ltr">' + esc(a.name || a.handle) + '</a><small dir="ltr">@' + esc(a.handle.replace(/^@/, '')) + '</small></div>' +
        (a.pending ? '<span class="cs-pend"><span class="ai-spin"></span>' + esc(T.sReading) + '</span>' : a.checked ? '<small class="text-ink-400">' + esc(a.checked) + '</small>' : '') + '</div>' +
        (!a.ok && !a.pending && a.err ? '<p class="cs-err">' + esc(a.err) + '</p>' : '') + (!a.checked && !a.pending ? '<p class="cs-err">' + esc(r.on ? T.sNever : T.sNoApify) + '</p>' : '') +
        (a.followers != null ? '<div class="cs-am"><div><small>' + esc(T.sFol) + '</small><b>' + kfmt(a.followers) + '</b></div><div><small>' + esc(T.s7) + '</small>' + delta(a.g7) + '</div><div><small>' + esc(T.s30) + '</small>' + delta(a.g30) + '</div>' +
          '<div><small>' + esc(T.sPw) + '</small><b>' + a.perWeek + '</b></div><div><small>' + esc(T.sErAvg) + '</small><b><bdi dir="ltr">' + (a.er != null ? a.er + '%' : '—') + '</bdi></b></div></div>' + ((a.reach != null || a.v7) ? '<div class="cs-ax">' + (a.reach != null ? '<span>' + esc(T.sReach) + ': <b><bdi dir="ltr">' + a.reach + '%</bdi></b></span>' : '') + (a.v7 ? '<span>' + esc(T.sV7) + ': <b>' + kfmt(a.v7.avg) + '</b> <small>(' + esc(f(T.sV7n, a.v7.n)) + ')</small></span>' : '') + '</div>' : '') + spark(a.series) + (a.tracked ? '<small class="cs-since">' + esc(f(T.sSince, a.tracked)) + '</small>' : '') : '') + '</div>'; }).join('');
    // روابط
    h += '<details class="cs-links"' + (r.accounts.length ? '' : ' open') + '><summary>' + ic('link', 'w-3.5 h-3.5') + esc(T.sLinks) + '</summary><p>' + esc(T.sLinksH) + '</p><textarea data-slinks rows="3" class="input" dir="ltr" placeholder="https://instagram.com/…">' + esc(c.links.join('\n')) + '</textarea><button type="button" class="btn-primary !py-1.5 !text-xs" data-slsave>' + esc(T.sSave) + '</button></details>';
    if (!r.posts.length) return h + (r.accounts.length ? '<p class="text-sm text-ink-400 mt-3">' + esc(T.sNoPosts) + '</p>' : '');
    // أفضل 10
    if (r.top.length) h += '<section class="cs-sec"><h4>' + esc(T.sTop) + '</h4>' + r.top.map(function (x, i) { return postRow(x, i + 1); }).join('') + '</section>';
    // 13-15: التعامل مع العملاء من التعليقات
    var cm = r.cmt || {}, dur = function (m) { return m == null ? '—' : m < 60 ? f(T.sMin, m) : m < 1440 ? f(Math.round(m / 60) >= 3 && Math.round(m / 60) <= 10 ? T.sHrs : T.sHr, Math.round(m / 60)) : f(T.sDay, Math.round(m / 1440)); };
    var cItem = function (x, extra) { return '<div class="cs-cm">' + plBadge(x.pl) + '<div class="min-w-0 flex-1"><div class="cs-pm"><b dir="auto">' + esc(x.who || '—') + '</b>' + (extra || '') + '<span class="ms-auto">' + esc(x.at) + '</span></div><p dir="auto">' + esc(x.text) + '</p>' + (x.replied ? '<small class="cs-rep">' + esc(T.sReplied) + '</small>' : '<small class="cs-norep">' + esc(T.sNoRep) + '</small>') + '</div></div>'; };
    if (cm.n) {
      h += '<section class="cs-sec"><h4>' + esc(T.sCust) + ' <small>' + esc(f(T.sCustN, cm.n)) + '</small></h4><div class="cs-kp"><div><small>' + esc(T.sRate) + '</small><b><bdi dir="ltr">' + (cm.rate != null ? cm.rate + '%' : '—') + '</bdi></b><i>' + esc(f(T.sRepOf, cm.replied, cm.n)) + '</i></div><div><small>' + esc(T.sSpeed) + '</small><b>' + esc(dur(cm.median)) + '</b><i>' + esc(T.sMedian) + '</i></div>' +
        Object.keys(cm.byPl).map(function (k) { var x = cm.byPl[k]; return '<div><small>' + esc({ instagram: 'Instagram', tiktok: 'TikTok', facebook: 'Facebook', youtube: 'YouTube' }[k] || k) + '</small><b><bdi dir="ltr">' + Math.round(x.r / x.n * 100) + '%</bdi></b><i>' + esc(f(T.sRepOf, x.r, x.n)) + '</i></div>'; }).join('') + '</div></section>';
      h += '<section class="cs-sec"><h4>' + esc(T.sNeg) + ' <small>' + cm.neg.length + '</small></h4>' + (cm.neg.length ? '<div class="cs-chips mb-2">' + Object.keys(cm.negWords).map(function (k) { return '<span class="cs-chip neg">' + esc(k) + ' <i>' + cm.negWords[k] + '</i></span>'; }).join('') + '</div>' + cm.neg.map(function (x) { return cItem(x, '<span class="cs-w">' + esc(x.w) + '</span>'); }).join('') : '<p class="text-xs text-ink-400">' + esc(T.sNegNone) + '</p>') + '</section>';
      var TP = { price: T.qPrice, place: T.qPlace, time: T.qTime, delivery: T.qDel, stock: T.qStock, other: T.qOther };
      if (cm.qs.length) h += '<section class="cs-sec"><h4>' + esc(T.sQs) + '</h4><p class="cs-sub">' + esc(T.sQsH) + '</p><div class="cs-chips mb-2">' + Object.keys(cm.qTopics).map(function (k) { return '<span class="cs-chip">' + esc(TP[k] || k) + ' <i>' + cm.qTopics[k] + '</i></span>'; }).join('') + '</div>' + cm.qs.map(function (x) { return cItem(x, (x.topics || []).map(function (t2) { return '<span class="cs-w q">' + esc(TP[t2] || t2) + '</span>'; }).join('')); }).join('') + '</section>';
    } else if (r.accounts.length && r.posts.length) h += '<section class="cs-sec"><h4>' + esc(T.sCust) + '</h4><p class="text-xs text-ink-400">' + esc(T.sCmtSoon) + '</p></section>';
    // نوع المحتوى
    var mt = 0; Object.keys(r.mix).forEach(function (k) { mt += r.mix[k]; });
    if (mt) h += '<section class="cs-sec"><h4>' + esc(T.sMix) + ' <small>' + esc(f(T.s90, mt)) + '</small></h4><div class="cs-mix">' + Object.keys(r.mix).map(function (k) { var pc = Math.round(r.mix[k] / mt * 100); return '<div><span>' + esc(tyName(k)) + '</span><i><b style="width:' + pc + '%"></b></i><em><bdi dir="ltr">' + pc + '%</bdi></em></div>'; }).join('') + '</div></section>';
    // أوقات النشر
    var mx = 0; r.heat.forEach(function (d) { d.forEach(function (v) { if (v > mx) mx = v; }); });
    if (mx) { var best = [], days = T.sDays.split(','); r.heat.forEach(function (d, di) { d.forEach(function (v, hh) { if (v) best.push([v, di, hh]); }); }); best.sort(function (a, b) { return b[0] - a[0]; });
      h += '<section class="cs-sec"><h4>' + esc(T.sHeat) + '</h4><p class="cs-sub">' + esc(T.sBest) + ': ' + best.slice(0, 3).map(function (b) { return days[b[1]] + ' ' + (b[2] % 12 || 12) + (b[2] < 12 ? T.am : T.pm); }).join('، ') + '</p><div class="cs-heat"><span></span>' + [0, 3, 6, 9, 12, 15, 18, 21].map(function (x) { return '<em style="grid-column:span 3">' + (x % 12 || 12) + (x < 12 ? T.am : T.pm) + '</em>'; }).join('') +
        r.heat.map(function (d, di) { return '<span>' + esc(days[di]) + '</span>' + d.map(function (v, hh) { return '<i style="--o:' + (v ? (0.18 + v / mx * 0.82).toFixed(2) : 0) + '" title="' + esc(days[di] + ' ' + hh + ':00 — ' + v) + '"></i>'; }).join(''); }).join('') + '</div></section>'; }
    // هاشتاجات ومنشن
    var chips = function (o, pre) { return Object.keys(o).map(function (k) { return '<span class="cs-chip" dir="auto">' + pre + esc(k) + ' <i>' + o[k] + '</i></span>'; }).join(''); };
    if (Object.keys(r.tags).length) h += '<section class="cs-sec"><h4>' + esc(T.sTags) + '</h4><div class="cs-chips">' + chips(r.tags, '#') + '</div></section>';
    if (Object.keys(r.ments).length) h += '<section class="cs-sec"><h4>' + esc(T.sMents) + '</h4><div class="cs-chips">' + chips(r.ments, '@') + '</div></section>';
    if (r.paid && r.paid.n) h += '<section class="cs-sec"><h4>' + esc(T.sPaidT) + ' <small>' + esc(f(T.s90n, r.paid.n)) + '</small></h4>' + (Object.keys(r.paid.partners).length ? '<p class="cs-sub">' + esc(T.sPartners) + '</p><div class="cs-chips mb-2">' + chips(r.paid.partners, '@') + '</div>' : '') + r.paid.list.map(function (x) { return postRow(x); }).join('') + '</section>';
    if (r.music && Object.keys(r.music).length) h += '<section class="cs-sec"><h4>' + esc(T.sMusic) + '</h4><div class="cs-chips">' + Object.keys(r.music).map(function (k) { return '<span class="cs-chip" dir="auto">♪ ' + esc(k) + ' <i>' + r.music[k] + '</i></span>'; }).join('') + '</div></section>';
    // كل المنشورات
    var pls = {}; r.posts.forEach(function (x) { pls[x.platform] = 1; });
    var list = r.posts.filter(function (x) { return (!socF.pl || x.platform === socF.pl) && (!socF.q || (x.caption || '').toLowerCase().indexOf(socF.q) >= 0); });
    if (socF.sort === 'eng') list = list.slice().sort(function (a, b) { return b.eng - a.eng; });
    h += '<section class="cs-sec"><h4>' + esc(T.sAll) + ' <small>' + list.length + '</small></h4><div class="cs-ftr"><select data-sfp class="cx-sel"><option value="">' + esc(T.sAllPl) + '</option>' + Object.keys(pls).map(function (k) { return '<option value="' + k + '"' + (socF.pl === k ? ' selected' : '') + '>' + esc((PL[k] ? { instagram: 'Instagram', tiktok: 'TikTok', facebook: 'Facebook', youtube: 'YouTube' }[k] : k)) + '</option>'; }).join('') + '</select>' +
      '<select data-ssort class="cx-sel"><option value="new">' + esc(T.sNew) + '</option><option value="eng"' + (socF.sort === 'eng' ? ' selected' : '') + '>' + esc(T.sMost) + '</option></select><input type="search" data-sq2 class="input !h-8 !text-xs flex-1" placeholder="' + esc(T.sFind) + '" value="' + esc(socF.q) + '"></div>' +
      list.slice(0, socF.more).map(function (x) { return postRow(x); }).join('') + (list.length > socF.more ? '<button type="button" class="btn-ghost w-full !text-xs" data-smore>' + esc(T.sMore) + '</button>' : '') + '</section>';
    return h; }
  function socPaint() { var a = root.querySelector('[data-soc]'); var c = D.comps.filter(function (x) { return x.id === openId; })[0]; if (a && c && SOC[c.id]) a.innerHTML = socBody(c, SOC[c.id]); }
  function socLoad(id, action, extra) { return post(Object.assign({ action: action || 'social', id: id }, extra || {})).then(function (j) { if (!j || !j.ok) { toast((j && j.error) || T.err, 'error'); return j; } SOC[id] = j.r; if (j.links) D.comps.forEach(function (c) { if (c.id === id) c.links = j.links; }); if (openId === id && sTab === 'soc') socPaint(); socWatch(id); return j; }); }
  function socWatch(id) { clearTimeout(pollT); var r = SOC[id]; if (!r || !r.accounts.some(function (a) { return a.pending; }) || openId !== id) return; pollT = setTimeout(function () { socLoad(id, 'social_poll'); }, 8000); }

  /* ---------- الإعلانات ---------- */
  var ADS = {}, adF = { pl: '', q: '', more: 20 }, adT = null;
  var APL = { facebook: ['#1877f2', 'FB'], instagram: ['#e1306c', 'IG'], messenger: ['#0084ff', 'MS'], audience_network: ['#64748b', 'AN'], threads: ['#111827', 'TH'], whatsapp: ['#16a34a', 'WA'] };
  var APN = { facebook: 'Facebook', instagram: 'Instagram', messenger: 'Messenger', audience_network: 'Audience Network', threads: 'Threads', whatsapp: 'WhatsApp' };
  function apl(p) { var x = APL[p] || ['#64748b', p.slice(0, 2).toUpperCase()]; return '<i class="cs-pl" style="--c:' + x[0] + '" title="' + esc(APN[p] || p) + '">' + x[1] + '</i>'; }
  function adCard(a) {
    return '<div class="ca-ad' + (a.active ? '' : ' off') + '">' + (a.thumb ? '<img src="' + esc(a.thumb) + '" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">' : '<span class="ca-noimg">' + ic('megaphone', 'w-5 h-5') + '</span>') +
      '<div class="min-w-0 flex-1"><div class="cs-pm"><span class="ca-days' + (a.days >= 30 ? ' win' : '') + '">' + (a.days >= 30 ? '🏆 ' : '') + esc(f(a.active ? T.aDays : T.aRan, a.days)) + '</span>' + (a.isNew && a.active ? '<span class="ca-new">' + esc(T.aNewB) + '</span>' : '') + '<span class="ca-pls">' + a.platforms.map(apl).join('') + '</span></div>' +
      (a.title ? '<b class="ca-t" dir="auto">' + esc(a.title) + '</b>' : '') + '<p dir="auto">' + esc(a.body || '—') + '</p>' +
      '<div class="ca-meta">' + (a.cta ? '<span class="ca-cta">' + esc(a.cta) + '</span>' : '') + '<span>' + esc(T['f_' + a.format] || a.format) + '</span>' + (a.variants > 1 ? '<span>' + esc(f(T.aVar, a.variants)) + '</span>' : '') + '<span>' + esc(a.start) + (a.end ? ' ← ' + esc(a.end) : '') + '</span>' +
      (a.host ? '<a class="link" href="' + esc(a.link) + '" target="_blank" rel="noopener" dir="ltr">' + esc(a.host) + '</a>' : '') + '<a class="link ms-auto" href="' + esc(a.lib) + '" target="_blank" rel="noopener">' + esc(T.aLib) + ' ↗</a></div></div></div>'; }
  function bars(o, nameF, den) { var t = den || 0; if (!t) Object.keys(o).forEach(function (k) { t += o[k]; }); return '<div class="cs-mix">' + Object.keys(o).map(function (k) { var pc = Math.round(o[k] / t * 100); return '<div><span>' + esc(nameF(k)) + '</span><i><b style="width:' + pc + '%"></b></i><em><bdi dir="ltr">' + o[k] + '</bdi></em></div>'; }).join('') + '</div>'; }
  function adsBody(c, r) {
    var busy = r.pending, h = '<div class="cs-head"><div>' + (r.checked ? '<small class="text-ink-400">' + esc(T.aChecked) + ' ' + esc(r.checked) + '</small>' : '') + '</div>' + (r.on && r.page ? '<button type="button" class="btn-ghost !py-1 !text-xs" data-arefresh' + (busy ? ' disabled' : '') + '>' + ic('refresh') + '<span>' + esc(busy ? T.sReading : T.aRefresh) + '</span></button>' : '') + '</div>';
    if (!r.on) return h + '<div class="cs-note">' + esc(T.aNoApify) + '</div>';
    if (!r.page) return h + '<div class="cs-note">' + esc(T.aNoPage) + '</div>';
    if (r.err) h += '<p class="cs-err">' + esc(r.err) + '</p>';
    if (busy) h += '<p class="cs-pend mb-2"><span class="ai-spin"></span>' + esc(T.aBusy) + '</p>';
    var k = r.k;
    h += '<div class="cs-kp">' + [[k.active, T.aActive, ''], [k.win, T.aWin, T.aWinH], [k.new, T.aNew, T.aNewH], [k.avgDays, T.aAvg, T.aAvgH]].map(function (x) { return '<div><small>' + esc(x[1]) + '</small><b>' + x[0] + '</b>' + (x[2] ? '<i>' + esc(x[2]) + '</i>' : '') + '</div>'; }).join('') + '</div>';
    if (!r.checked && !busy) return h + '<p class="text-sm text-ink-400 mt-3">' + esc(T.aNever) + '</p>';
    if (!k.active) return h + '<p class="text-sm text-ink-400 mt-3">' + esc(T.aNone) + '</p>' + stoppedHtml(r);
    if (Object.keys(r.platforms).length) h += '<section class="cs-sec"><h4>' + esc(T.aPl) + '</h4>' + bars(r.platforms, function (p) { return APN[p] || p; }, r.k.active) + '</section>';
    if (Object.keys(r.formats).length > 1 || Object.keys(r.ctas).length) h += '<section class="cs-sec"><h4>' + esc(T.aFmt) + '</h4>' + bars(r.formats, function (x) { return T['f_' + x] || x; }) + (Object.keys(r.ctas).length ? '<p class="cs-sub" style="margin-top:.8rem">' + esc(T.aCtas) + '</p><div class="cs-chips">' + Object.keys(r.ctas).map(function (x) { return '<span class="cs-chip">' + esc(x) + ' <i>' + r.ctas[x] + '</i></span>'; }).join('') + '</div>' : '') + '</section>';
    if (r.win.length) h += '<section class="cs-sec"><h4>' + esc(T.aWinT) + ' <small>' + r.win.length + '</small></h4><p class="cs-sub">' + esc(T.aWinD) + '</p>' + r.win.map(adCard).join('') + '</section>';
    if (r.new.length) h += '<section class="cs-sec"><h4>' + esc(T.aNewT) + ' <small>' + r.new.length + '</small></h4>' + r.new.map(adCard).join('') + '</section>';
    if (r.landing.length) h += '<section class="cs-sec"><h4>' + esc(T.aLand) + ' <small>' + r.landing.length + '</small></h4>' + r.landing.map(function (l) { return '<div class="ca-lp"><div class="flex items-center gap-2"><a class="link truncate flex-1" dir="ltr" href="' + esc(l.url) + '" target="_blank" rel="noopener">' + esc(l.url.replace(/^https?:\/\/(www\.)?/, '')) + '</a><span class="ca-n">' + esc(f(T.aLpN, l.n)) + '</span></div>' +
      (l.ok === null ? '<small class="text-ink-400">' + esc(T.aLpWait) + '</small>' : l.ok ? ((l.title || l.h1) ? '<b dir="auto">' + esc(l.h1 || l.title) + '</b>' : '') + (l.descr ? '<p dir="auto">' + esc(l.descr) + '</p>' : '') + (l.prices.length ? '<div class="cs-chips">' + l.prices.map(function (x) { return '<span class="cs-chip" dir="auto">' + esc(x) + '</span>'; }).join('') + '</div>' : '') : '<small class="cs-err">' + esc(T.aLpBad) + '</small>') + '</div>'; }).join('') + '</section>';
    var pls = {}; r.active.forEach(function (a) { a.platforms.forEach(function (p) { pls[p] = 1; }); });
    var list = r.active.filter(function (a) { return (!adF.pl || a.platforms.indexOf(adF.pl) >= 0) && (!adF.q || ((a.body || '') + ' ' + (a.title || '')).toLowerCase().indexOf(adF.q) >= 0); });
    h += '<section class="cs-sec"><h4>' + esc(T.aAll) + ' <small>' + list.length + '</small></h4><div class="cs-ftr"><select data-afp class="cx-sel"><option value="">' + esc(T.sAllPl) + '</option>' + Object.keys(pls).map(function (p) { return '<option value="' + p + '"' + (adF.pl === p ? ' selected' : '') + '>' + esc(APN[p] || p) + '</option>'; }).join('') + '</select><input type="search" data-aq class="input !h-8 !text-xs flex-1" placeholder="' + esc(T.aFind) + '" value="' + esc(adF.q) + '"></div>' +
      list.slice(0, adF.more).map(adCard).join('') + (list.length > adF.more ? '<button type="button" class="btn-ghost w-full !text-xs" data-amore>' + esc(T.sMore) + '</button>' : '') + '</section>';
    return h + stoppedHtml(r); }
  function stoppedHtml(r) { return r.stopped.length ? '<details class="cs-links mt-3"><summary>' + esc(f(T.aStopped, r.k.stopped)) + '</summary>' + r.stopped.map(adCard).join('') + '</details>' : ''; }
  function adsPaint() { var a = root.querySelector('[data-ads]'), c = D.comps.filter(function (x) { return x.id === openId; })[0]; if (a && c && ADS[c.id]) a.innerHTML = adsBody(c, ADS[c.id]); }
  function adsLoad(id, action) { return post({ action: action || 'ads', id: id }).then(function (j) { if (!j || !j.ok) { toast((j && j.error) || T.err, 'error'); if (ADS[id]) adsPaint(); return j; } ADS[id] = j.r; D.comps.forEach(function (c) { if (c.id === id) c.ads = j.r.k.active; }); if (openId === id && sTab === 'ads') adsPaint(); clearTimeout(adT); if (j.r.pending && openId === id) adT = setTimeout(function () { adsLoad(id, 'ads_poll'); }, 8000); return j; }); }
  function openSheet(id) { openId = id; sTab = 'ch'; sq = ''; var sh = root.querySelector('[data-sh]'); sh.querySelector('aside').innerHTML = sheetBody(); sh.classList.add('open'); document.documentElement.classList.add('hub-lock'); }
  function closeSheet() { clearTimeout(pollT); clearTimeout(adT); openId = null; var sh = root.querySelector('[data-sh]'); if (sh) sh.classList.remove('open'); document.documentElement.classList.remove('hub-lock'); }

  root.addEventListener('click', function (e) { var t = e.target, b;
    if (t.closest('[data-open-add]')) { var pp = root.querySelector('[data-pop]'); pp.classList.toggle('hidden'); if (!pp.classList.contains('hidden')) pp.querySelector('[name=name]').focus(); return; }
    if ((b = t.closest('[data-fk]'))) { flt.kind = b.dataset.fk; paint(); return; }
    if ((b = t.closest('[data-st]'))) { sTab = b.dataset.st; root.querySelector('[data-sh] aside').innerHTML = sheetBody(); if (sTab === 'soc') { socF = { pl: '', sort: 'new', q: '', more: 30 }; socLoad(openId); } if (sTab === 'ads') { adF = { pl: '', q: '', more: 20 }; adsLoad(openId); } return; }
    if ((b = t.closest('[data-arefresh]'))) { b.disabled = true; b.querySelector('span').textContent = T.sReading; adsLoad(openId, 'ads_refresh').then(function (j) { if (j && j.ok) toast(T.aStarted); }); return; }
    if (t.closest('[data-amore]')) { adF.more += 20; adsPaint(); return; }
    if ((b = t.closest('[data-srefresh]'))) { b.disabled = true; b.querySelector('span').textContent = T.sReading; socLoad(openId, 'social_refresh').then(function (j) { if (j && j.ok) toast(j.started ? f(T.sStarted, j.started) : T.sNoAcc); }); return; }
    if ((b = t.closest('[data-slsave]'))) { var ta = root.querySelector('[data-slinks]'); b.disabled = true; socLoad(openId, 'links', { links: ta.value }).then(function (j) { b.disabled = false; if (j && j.ok) toast(j.started ? f(T.sStarted, j.started) : T.sSaved); }); return; }
    if (t.closest('[data-smore]')) { socF.more += 30; socPaint(); return; }
    if (t.closest('[data-shx]')) { closeSheet(); return; }
    var card = t.closest('[data-cid]'); if (!card) return; var id = +card.dataset.cid;
    if ((b = t.closest('[data-check]'))) { b.disabled = true; b.classList.add('pd-busy'); b.querySelector('span').textContent = T.checking; post({ action: 'check', id: id }).then(after).catch(function () { toast(T.err, 'error'); paint(); }); return; }
    if ((b = t.closest('[data-del]'))) { if (!b.classList.contains('sure')) { b.classList.add('sure'); b.innerHTML = esc(T.delQ); setTimeout(function () { if (b.isConnected) { b.classList.remove('sure'); b.innerHTML = ic('trash'); } }, 3000); return; }
      card.classList.add('pd-out'); post({ action: 'del', id: id }).then(after); return; }
    if (t.closest('[data-stop]')) return; openSheet(id); });
  root.addEventListener('keydown', function (e) { var c = e.target.closest('[data-cid]'); if (c && e.key === 'Enter' && e.target === c) openSheet(+c.dataset.cid); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && openId) closeSheet(); });
  root.addEventListener('change', function (e) { if (e.target.matches('[data-afp]')) { adF.pl = e.target.value; adF.more = 20; adsPaint(); return; } if (e.target.matches('[data-sfp]')) { socF.pl = e.target.value; socF.more = 30; socPaint(); return; } if (e.target.matches('[data-ssort]')) { socF.sort = e.target.value; socPaint(); return; } if (e.target.matches('[data-fc]')) { flt.cid = e.target.value; paint(); } if (e.target.matches('[data-only]')) { onlyCheap = e.target.checked; paint(); } });
  root.addEventListener('input', function (e) { var t = e.target;
    if (t.matches('[data-aq]')) { adF.q = t.value.trim().toLowerCase(); adsPaint(); var n3 = root.querySelector('[data-aq]'); n3.focus(); n3.setSelectionRange(n3.value.length, n3.value.length); return; }
    if (t.matches('[data-sq2]')) { socF.q = t.value.trim().toLowerCase(); socPaint(); var n2 = root.querySelector('[data-sq2]'); n2.focus(); n2.setSelectionRange(n2.value.length, n2.value.length); return; }
    if (t.matches('[data-sq]')) { sq = t.value.trim().toLowerCase(); var a = root.querySelector('[data-sh] aside'); a.innerHTML = sheetBody(); var n = a.querySelector('[data-sq]'); n.focus(); n.setSelectionRange(n.value.length, n.value.length); }
    if (t.name === 'url' && t.closest('[data-add]')) { var v = t.value.trim().replace(/^https?:\/\//, '').split('/')[0], fv = t.closest('[data-add]').querySelector('[data-fav]'); fv.textContent = v ? v.charAt(0).toUpperCase() : ''; if (!v) fv.innerHTML = ic('link'); fv.classList.toggle('on', !!v);
      var nm = t.form.name; if (v && !nm.value) nm.placeholder = v.replace(/^www\./, '').split('.')[0]; } });
  root.addEventListener('submit', function (e) { var fm = e.target.closest('[data-add]'); if (!fm) return; e.preventDefault(); if (!fm.name.value.trim() && fm.name.placeholder) fm.name.value = fm.name.placeholder;
    var bt = fm.querySelector('.btn-primary'); bt.disabled = true; fm.querySelector('.cx-wait').classList.remove('hidden');
    post({ action: 'add', name: fm.name.value, url: fm.url.value, links: fm.links.value, note: fm.note.value }).then(function (j) { after(j); if (j && j.focus) { var c = root.querySelector('[data-cid="' + String(j.focus).replace('c', '') + '"]'); if (c) { c.classList.add('cx-new'); c.scrollIntoView({ block: 'center', behavior: 'smooth' }); } } })
      .catch(function () { bt.disabled = false; fm.querySelector('.cx-wait').classList.add('hidden'); toast(T.err, 'error'); }); });
  paint();
})();
