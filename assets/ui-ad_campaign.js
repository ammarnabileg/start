/* تفاصيل الحملة: لوحة تفاعلية — مؤشرات بخطوط صغيرة، رسم بمقاييس، قمع، ملاحظات بأزرار تنفيذ،
   مجموعات وإعلانات بمفاتيح تشغيل/إيقاف وتعديل ميزانية من مكانها (بتأكيد قبل الصرف)، ومعاينة الإعلان على موبايل */
(function () {
  var A = window.ACP; if (!A) return; var T = A.T;
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function f(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }).replace('%%', '%'); }
  function num(v, d) { v = +v || 0; return v.toLocaleString('en-US', { maximumFractionDigits: d == null ? (v >= 100 ? 0 : 2) : d, minimumFractionDigits: d || 0 }); }
  function money(v) { return num(v, (+v || 0) >= 1000 ? 0 : 2) + (A.cur ? ' ' + A.cur : ''); }
  function res(m) { return +(m.conversions || 0) || +(m.engagement || 0) || +(m.results || 0); }
  function ctr(m) { return +m.impressions > 0 ? (+m.clicks || 0) / m.impressions * 100 : (m.ctr != null ? +m.ctr : null); }
  function cpc(m) { return +m.clicks > 0 ? (+m.spend || 0) / m.clicks : null; }
  var SC = { active: '#16a34a', paused: '#d97706', pending_review: '#2563eb', rejected: '#dc2626', ended: '#64748b' };
  function pill(s) { return '<span class="acp-st" style="--c:' + (SC[s] || '#64748b') + '">' + esc(T[s] || s) + '</span>'; }
  function on(s) { return s === 'active' || s === 'pending_review'; }
  function budgetText(b) { if (!b || b.amount == null) return A.cbo ? T.noBudget : T.byAdsets; return money(b.amount) + (b.type === 'lifetime' ? ' ' + T.lifetime : T.perDay); }
  function post(d) { var fd = new FormData(); fd.append('_csrf', A.csrf); fd.append('ajax', '1'); fd.append('platform', A.platform); Object.keys(d).forEach(function (k) { fd.append(k, d[k]); });
    return fetch(A.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
  function countUp(el) { var to = +el.dataset.to, d = +el.dataset.d || 0, suf = el.dataset.suf || '', t0 = performance.now();
    (function s(t) { var k = Math.min(1, (t - t0) / 700), e = 1 - Math.pow(1 - k, 3); el.textContent = num(to * e, d) + suf; if (k < 1) requestAnimationFrame(s); })(t0); }
  function dark() { return document.documentElement.classList.contains('dark'); }

  /* ---------- الهيدر: الميزانية، المفتاح، وتيرة الصرف ---------- */
  var camp = { kind: 'campaign_status', id: '', name: (document.querySelector('.acp-hero h2') || {}).textContent || '', get status() { return A.status; }, set status(v) { A.status = v; } };
  function hero() { var bb = document.querySelector('.acp-budget'); bb.innerHTML = ic('wallet', 'w-4 h-4') + '<span>' + esc(A.cbo ? budgetText(A.budget) : T.byAdsets) + '</span>' + (A.cbo && A.spend ? ic('pencil', 'w-3.5 h-3.5 opacity-70') : '');
    bb.disabled = !(A.cbo && A.spend); swPaint(document.querySelector('.acp-sw.big'), A.status);
    var p = $('acp-pace'), m = A.m || {}, b = A.budget; if (!A.cbo || !b || !b.amount) { p.innerHTML = ''; return; }
    var spent = +m.spend || 0, pct, txt;
    if (b.type === 'lifetime') { pct = spent / b.amount * 100; txt = f(T.paceLife, money(spent), money(b.amount)); } else { var avg = spent / A.days; pct = avg / b.amount * 100; txt = f(T.pace, money(avg), money(b.amount)); }
    p.innerHTML = '<div class="flex items-center justify-between text-xs text-[#fff]/85 mb-1.5"><span>' + esc(txt) + '</span><b class="tabular-nums">' + Math.round(pct) + '%</b></div><div class="acp-pbar"><i style="width:0"></i></div>';
    requestAnimationFrame(function () { p.querySelector('i').style.width = Math.min(100, pct) + '%'; }); }
  function swPaint(b, s) { if (!b) return; b.classList.toggle('on', on(s)); b.classList.toggle('dis', !/^(active|paused|pending_review)$/.test(s)); b.setAttribute('aria-checked', on(s)); b.title = T[s] || s; b.innerHTML = '<i></i><span>' + esc(T[s] || s) + '</span>'; }

  /* ---------- المؤشرات ---------- */
  var D = A.daily || [], M = A.m || {};
  function series(k) { return D.map(function (d) { var x = d.metrics || d; if (k === 'cpc') return +x.clicks > 0 ? x.spend / x.clicks : 0; if (k === 'ctr') return +x.impressions > 0 ? x.clicks / x.impressions * 100 : 0; return +(x[k] || 0); }); }
  function has(k) { return D.some(function (d) { var x = d.metrics || d; return k === 'cpc' ? x.clicks != null && x.spend != null : k === 'ctr' ? x.impressions != null : x[k] != null; }); }
  function spark(v, c) { if (v.length < 2) return ''; var mx = Math.max.apply(null, v) || 1, w = 100, h = 28, pts = v.map(function (y, i) { return (i / (v.length - 1) * w).toFixed(1) + ',' + (h - y / mx * (h - 3) - 1).toFixed(1); });
    return '<svg viewBox="0 0 100 28" preserveAspectRatio="none" class="acp-spark"><polygon points="0,28 ' + pts.join(' ') + ' 100,28" fill="' + c + '" opacity=".14"/><polyline points="' + pts.join(' ') + '" fill="none" stroke="' + c + '" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>'; }
  function kpis() { var r = res(M), c = ctr(M), k = [
      ['spend', T.spend, +M.spend || 0, 2, A.cur ? ' ' + A.cur : '', 'wallet', '#2563eb'], ['impressions', T.imp, +M.impressions || 0, 0, '', 'eye', '#7c3aed'],
      ['clicks', T.clicks, +M.clicks || 0, 0, '', 'pointer', '#0891b2'], ['ctr', T.ctr, c || 0, 2, '%', 'percent', c != null && c < 1 ? '#dc2626' : '#16a34a'],
      ['reach', T.reach, +M.reach || 0, 0, '', 'users', '#0f766e'], ['cpc', T.cpc, cpc(M) || 0, 2, A.cur ? ' ' + A.cur : '', 'tag', '#ea580c'],
      ['res', T.res, r, 0, '', 'target', '#16a34a'], ['cpr', T.cpr, r > 0 ? (+M.spend || 0) / r : 0, 2, A.cur ? ' ' + A.cur : '', 'coins', '#be123c']];
    $('acp-kpi').innerHTML = k.map(function (x, i) { var sk = has(x[0]) ? spark(series(x[0]), x[6]) : '', clk = has(x[0]);
      return '<button type="button" class="acp-kpi' + (clk ? ' clk' : '') + '" style="--c:' + x[6] + ';animation-delay:' + i * 40 + 'ms"' + (clk ? ' data-metric="' + x[0] + '"' : ' tabindex="-1"') + '><span class="acp-kic">' + ic(x[5], 'w-4 h-4') + '</span><small>' + esc(x[1]) + '</small>' +
        '<b dir="ltr" data-to="' + x[2] + '" data-d="' + x[3] + '" data-suf="' + esc(x[4]) + '">0</b>' + (x[0] === 'impressions' && M.reach ? '<em>' + esc(T.freq) + ' ' + num(M.impressions / M.reach, 2) + '</em>' : x[0] === 'cpr' && M.impressions ? '<em>' + esc(T.cpm) + ' ' + money(M.spend / M.impressions * 1000) + '</em>' : '') + sk + '</button>'; }).join('');
    $('acp-kpi').querySelectorAll('b[data-to]').forEach(countUp); }
  $('acp-kpi').addEventListener('click', function (e) { var b = e.target.closest('[data-metric]'); if (b) { metric = b.dataset.metric; chart(); $('acp-chart').scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } });

  /* ---------- الرسم ---------- */
  var MT = [['spend', T.spend, '#2563eb'], ['clicks', T.clicks, '#0891b2'], ['impressions', T.imp, '#7c3aed'], ['cpc', T.cpc, '#ea580c'], ['ctr', T.ctr, '#16a34a'], ['conversions', T.res, '#16a34a']].filter(function (x) { return has(x[0]); });
  var metric = MT[0] ? MT[0][0] : 'spend', ec = null;
  function chart() { var box = $('acp-chart'); $('acp-mt').innerHTML = MT.map(function (x, i) { return '<button type="button" data-m="' + x[0] + '" class="' + (x[0] === metric ? 'on' : '') + '" title="' + (i + 1) + '">' + esc(x[1]) + '</button>'; }).join('');
    $('acp-kpi').querySelectorAll('[data-metric]').forEach(function (b) { b.classList.toggle('sel', b.dataset.metric === metric); });
    if (!D.length || !window.echarts) { box.innerHTML = '<p class="text-sm text-ink-400 text-center pt-20">—</p>'; return; }
    var mt = MT.filter(function (x) { return x[0] === metric; })[0] || MT[0], v = series(mt[0]), avg = v.reduce(function (a, b) { return a + b; }, 0) / (v.length || 1), tc = dark() ? '#94a3b8' : '#64748b', gc = dark() ? '#1e293b' : '#eef2f7';
    var money1 = /spend|cpc/.test(mt[0]), fm = function (x) { return money1 ? money(x) : mt[0] === 'ctr' ? num(x, 2) + '%' : num(x); };
    ec = ec || echarts.init(box, null, { renderer: 'svg' });
    ec.setOption({ animationDuration: 600, grid: { left: 8, right: 8, top: 16, bottom: 24, containLabel: true },
      tooltip: { trigger: 'axis', backgroundColor: dark() ? '#0f172a' : '#fff', borderColor: dark() ? '#334155' : '#e2e8f0', textStyle: { color: dark() ? '#e2e8f0' : '#0f172a' }, formatter: function (p) { return p[0].axisValue + '<br><b>' + fm(p[0].value) + '</b>'; } },
      xAxis: { type: 'category', data: D.map(function (d) { return String(d.date || '').slice(5); }), axisLine: { lineStyle: { color: gc } }, axisLabel: { color: tc, fontSize: 10 }, axisTick: { show: false } },
      yAxis: { type: 'value', splitLine: { lineStyle: { color: gc } }, axisLabel: { color: tc, fontSize: 10, formatter: function (x) { return num(x, 0); } } },
      series: [{ type: 'line', smooth: true, symbol: 'circle', symbolSize: 7, showSymbol: v.length < 20, data: v, lineStyle: { width: 3, color: mt[2] }, itemStyle: { color: mt[2] },
        areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: mt[2] + '55' }, { offset: 1, color: mt[2] + '00' }]) },
        markLine: { silent: true, symbol: 'none', label: { color: tc, fontSize: 10, formatter: function () { return '⌀ ' + fm(avg); } }, lineStyle: { type: 'dashed', color: tc }, data: [{ yAxis: avg }] } }] }, true); }
  $('acp-mt').addEventListener('click', function (e) { var b = e.target.closest('[data-m]'); if (b) { metric = b.dataset.m; chart(); } });
  window.addEventListener('resize', function () { if (ec) ec.resize(); });

  /* ---------- القمع ---------- */
  function funnel() { var imp = +M.impressions || 0, cl = +M.clicks || 0, r = res(M), mx = imp || 1, st = [[T.imp, imp, '#7c3aed'], [T.clicks, cl, '#0891b2'], [T.res, r, '#16a34a']];
    $('acp-funnel').innerHTML = st.map(function (x, i) { var w = imp ? Math.max(8, Math.sqrt(x[1] / mx) * 100) : 0, rate = i && st[i - 1][1] ? num(x[1] / st[i - 1][1] * 100, 2) + '%' : '';
      return (rate ? '<div class="acp-frate">↓ ' + rate + '</div>' : '') + '<div class="acp-fst"><div class="acp-fbar" style="--c:' + x[2] + ';--w:' + w + '%"></div><div class="acp-flab"><span>' + esc(x[0]) + '</span><b>' + num(x[1]) + '</b></div></div>'; }).join('') +
      (r && M.spend ? '<p class="mt-3 text-xs text-ink-500">' + esc(T.cpr) + ': <b class="text-ink-900">' + money(M.spend / r) + '</b></p>' : ''); }

  /* ---------- الملاحظات (بأزرار تنفيذ) ---------- */
  function allAds() { var o = []; A.sets.forEach(function (s) { s.ads.forEach(function (a) { o.push({ a: a, s: s }); }); }); return o; }
  function insights() { var box = $('acp-ins'), out = [], ads = allAds().filter(function (x) { return +x.a.m.impressions > 200; }), tot = +M.spend || 0;
    if (ads.length > 1) { var srt = ads.slice().sort(function (x, y) { return ctr(y.a.m) - ctr(x.a.m); }), best = srt[0], worst = srt[srt.length - 1];
      out.push(['up', '#16a34a', f(T.iBest, best.a.name, num(ctr(best.a.m), 2) + '%'), best.a.id ? '<button type="button" class="acp-ia" data-view="' + esc(best.a.id) + '">' + esc(T.doView) + '</button>' : '']);
      if (ctr(worst.a.m) < ctr(best.a.m) / 2 && +worst.a.m.spend > 0 && on(worst.a.status)) out.push(['down', '#dc2626', f(T.iWorst, worst.a.name, money(worst.a.m.spend), num(ctr(worst.a.m), 2) + '%'), worst.a.id ? '<button type="button" class="acp-ia red" data-qpause="' + esc(worst.a.id) + '">' + esc(T.doPause) + '</button>' : '']); }
    var rej = allAds().filter(function (x) { return x.a.status === 'rejected' || x.a.reject; }); if (rej.length) out.push(['alert', '#dc2626', f(T.iRej, rej.length), '<button type="button" class="acp-ia" data-view="' + esc(rej[0].a.id) + '">' + esc(T.doView) + '</button>']);
    if (A.sets.length > 1 && tot) { var big = A.sets.slice().sort(function (x, y) { return (+y.m.spend || 0) - (+x.m.spend || 0); })[0], sh = (+big.m.spend || 0) / tot; if (sh > .75) out.push(['pie', '#d97706', f(T.iConc, big.name, Math.round(sh * 100) + '%'), '']); }
    var c = ctr(M); if (c != null && +M.impressions > 1000 && c < 1) out.push(['pointer', '#d97706', f(T.iLowCtr, num(c, 2) + '%'), '']);
    if (A.cbo && A.budget && A.budget.type !== 'lifetime' && A.budget.amount && tot) { var pace = tot / A.days / A.budget.amount; if (pace < .6 && on(A.status)) out.push(['wallet', '#2563eb', f(T.iPace, Math.round(pace * 100) + '%'), '']); }
    box.classList.toggle('hidden', !out.length);
    box.innerHTML = '<b class="flex items-center gap-2 text-ink-900 mb-2">' + ic('spark', 'w-4 h-4 text-violet-600') + esc(T.insT) + '</b><div class="grid md:grid-cols-2 gap-2">' + out.map(function (x, i) {
      return '<div class="acp-in" style="--c:' + x[1] + ';animation-delay:' + i * 60 + 'ms"><span>' + ic({ up: 'up', down: 'down', alert: 'alert', pie: 'layers', pointer: 'pointer', wallet: 'wallet' }[x[0]] || 'spark', 'w-4 h-4') + '</span><p>' + esc(x[2]) + '</p>' + x[3] + '</div>'; }).join('') + '</div>'; }
  $('acp-ins').addEventListener('click', function (e) { var b = e.target.closest('[data-view]'); if (b) return preview(b.dataset.view); b = e.target.closest('[data-qpause]'); if (b) { var x = findAd(b.dataset.qpause); if (x) ask(adRef(x.a), b); } });

  /* ---------- المجموعات والإعلانات ---------- */
  var fst = 'all', sortBy = 'spend', q = '', closed = {}; try { closed = JSON.parse(sessionStorage.getItem('acp-closed') || '{}'); } catch (e) {}
  function key(m) { return sortBy === 'ctr' ? (ctr(m) || 0) : sortBy === 'clicks' ? +m.clicks || 0 : sortBy === 'cpc' ? -(cpc(m) || 1e9) : +m.spend || 0; }
  function findAd(id) { var r = null; A.sets.forEach(function (s) { s.ads.forEach(function (a) { if (a.id === id) r = { a: a, s: s }; }); }); return r; }
  function adRef(a) { return { kind: 'ad_status', id: a.id, name: a.name, get status() { return a.status; }, set status(v) { a.status = v; }, extra: { ad_id: a.id } }; }
  function setRef(s) { return { kind: 'adset_status', id: s.id, name: s.name, get status() { return s.status; }, set status(v) { s.status = v; }, extra: { adset_id: s.id } }; }
  function thumb(a) { var h = 0; for (var i = 0; i < a.name.length; i++) h = (h * 31 + a.name.charCodeAt(i)) >>> 0; var g = [['#2563eb', '#06b6d4'], ['#7c3aed', '#ec4899'], ['#059669', '#84cc16'], ['#ea580c', '#f59e0b']][h % 4];
    return a.img ? '<img src="' + esc(a.img) + '" alt="" loading="lazy" referrerpolicy="no-referrer">' : '<span class="acp-ph" style="background:linear-gradient(135deg,' + g[0] + ',' + g[1] + ')">' + esc((a.head || a.name || '?').trim().charAt(0)) + '</span>'; }
  function sets() { var tot = +M.spend || A.sets.reduce(function (x, s) { return x + (+s.m.spend || 0); }, 0) || 1, ql = q.toLowerCase(), cnt = { all: 0, active: 0, paused: 0, rejected: 0 };
    A.sets.forEach(function (s) { s.ads.forEach(function (a) { cnt.all++; var k = a.status === 'pending_review' ? 'active' : a.status; if (cnt[k] != null) cnt[k]++; }); });
    $('acp-fst').innerHTML = ['all', 'active', 'paused', 'rejected'].filter(function (k) { return k === 'all' || cnt[k]; }).map(function (k) { return '<button type="button" data-fs="' + k + '" class="' + (k === fst ? 'on' : '') + '">' + esc(T[k]) + ' <i>' + cnt[k] + '</i></button>'; }).join('');
    if (!A.sets.length) { $('acp-sets').innerHTML = '<p class="text-sm text-ink-500">' + esc(T.noSets) + '</p>'; return; }
    var shown = 0;
    $('acp-sets').innerHTML = A.sets.slice().sort(function (x, y) { return key(y.m) - key(x.m); }).map(function (s) {
      var ads = s.ads.filter(function (a) { var st = a.status === 'pending_review' ? 'active' : a.status; return (fst === 'all' || st === fst) && (!ql || (a.name + ' ' + a.head + ' ' + a.body + ' ' + s.name).toLowerCase().indexOf(ql) >= 0); }).sort(function (x, y) { return key(y.m) - key(x.m); });
      if (!ads.length && (ql || fst !== 'all')) return ''; shown += ads.length;
      var share = (+s.m.spend || 0) / tot * 100, ctrs = s.ads.filter(function (a) { return +a.m.impressions > 0; }).map(function (a) { return ctr(a.m); }), bc = Math.max.apply(null, ctrs.concat([0])), wc = Math.min.apply(null, ctrs.concat([1e9])),
        mxS = Math.max.apply(null, s.ads.map(function (a) { return +a.m.spend || 0; }).concat([1])), ung = !s.id || s.id.indexOf('ungrouped') >= 0, isC = closed[s.id] && !ql;
      return '<article class="acp-set' + (isC ? ' closed' : '') + '" data-set="' + esc(s.id) + '"><header><button type="button" class="acp-chev" data-fold aria-expanded="' + !isC + '">' + ic('chevron-down', 'w-4 h-4') + '</button>' +
        '<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><b class="text-ink-900 truncate" dir="auto">' + esc(s.name) + '</b>' + pill(s.status) + '<span class="text-[11px] text-ink-400">' + esc(f(T.ads, s.ads.length)) + '</span></div>' +
        '<div class="acp-share" title="' + esc(f(T.share, Math.round(share) + '%')) + '"><i style="--w:' + share.toFixed(1) + '%"></i></div>' +
        '<div class="acp-mrow"><span>' + esc(T.spend) + ' <b>' + money(s.m.spend) + '</b></span><span>' + esc(T.clicks) + ' <b>' + num(s.m.clicks) + '</b></span>' + (ctr(s.m) != null ? '<span>' + esc(T.ctr) + ' <b>' + num(ctr(s.m), 2) + '%</b></span>' : '') + (cpc(s.m) ? '<span>' + esc(T.cpc) + ' <b>' + money(cpc(s.m)) + '</b></span>' : '') + '<span class="opacity-70">' + Math.round(share) + '%</span></div></div>' +
        (!A.cbo && !ung ? '<button type="button" class="acp-bchip" data-sbudget="' + esc(s.id) + '"' + (A.spend ? '' : ' disabled') + '>' + ic('wallet', 'w-3.5 h-3.5') + esc(budgetText(s.budget)) + '</button>' : '') +
        (!ung ? '<button type="button" class="acp-sw" data-sset="' + esc(s.id) + '" role="switch"></button>' : '') + '</header>' +
        '<div class="acp-ads">' + (ads.length ? ads.map(function (a, i) { var c = +a.m.impressions > 0 ? ctr(a.m) : null, tag = ctrs.length > 1 && c != null ? (c === bc ? '<em class="best">★ ' + esc(T.best) + '</em>' : c === wc ? '<em class="worst">' + esc(T.worst) + '</em>' : '') : '';
          return '<div class="acp-ad" data-ad="' + esc(a.id) + '" tabindex="0" style="animation-delay:' + i * 30 + 'ms"><div class="acp-th">' + thumb(a) + (a.video ? '<span class="acp-vid">▶</span>' : '') + tag + '</div><div class="min-w-0 flex-1">' +
            '<div class="flex items-start gap-2"><b class="flex-1 min-w-0 text-sm text-ink-900 truncate" dir="auto">' + esc(a.name) + '</b>' + (a.id ? '<button type="button" class="acp-sw sm" data-sad="' + esc(a.id) + '" role="switch"></button>' : '') + '</div>' +
            (a.head || a.body ? '<p class="text-xs text-ink-600 line-clamp-2 mt-0.5" dir="auto">' + (a.head ? '<b>' + esc(a.head) + '</b> — ' : '') + esc(a.body) + '</p>' : '') + (a.reject ? '<p class="text-[11px] text-red-600 mt-1 line-clamp-2">⚠ ' + esc(a.reject) + '</p>' : '') +
            '<div class="acp-amet"><span><small>' + esc(T.spend) + '</small><b>' + money(a.m.spend) + '</b><i style="--w:' + ((+a.m.spend || 0) / mxS * 100).toFixed(0) + '%"></i></span><span><small>' + esc(T.ctr) + '</small><b>' + (c == null ? '—' : num(c, 2) + '%') + '</b><i style="--w:' + (c && bc ? (c / bc * 100).toFixed(0) : 0) + '%;--c:#16a34a"></i></span><span><small>' + esc(T.clicks) + '</small><b>' + num(a.m.clicks) + '</b></span></div></div></div>'; }).join('')
          : '<p class="text-xs text-ink-400 p-3">' + esc(T.noAds) + '</p>') + '</div></article>'; }).join('') || '<p class="text-sm text-ink-500 text-center py-8">' + esc(T.noAds) + '</p>';
    $('acp-sets').querySelectorAll('[data-sset]').forEach(function (b) { swPaint(b, A.sets.filter(function (s) { return s.id === b.dataset.sset; })[0].status); });
    $('acp-sets').querySelectorAll('[data-sad]').forEach(function (b) { swPaint(b, findAd(b.dataset.sad).a.status); });
    requestAnimationFrame(function () { $('acp-sets').querySelectorAll('[style*="--w"]').forEach(function (el) { el.classList.add('go'); }); }); }
  $('acp-fst').addEventListener('click', function (e) { var b = e.target.closest('[data-fs]'); if (b) { fst = b.dataset.fs; sets(); } });
  $('acp-sort').addEventListener('change', function () { sortBy = this.value; sets(); });
  $('acp-q').addEventListener('input', function () { q = this.value.trim(); sets(); });
  $('acp-sets').addEventListener('click', function (e) { var b;
    if ((b = e.target.closest('[data-fold]'))) { var art = b.closest('.acp-set'), id = art.dataset.set; art.classList.toggle('closed'); closed[id] = art.classList.contains('closed') ? 1 : 0; b.setAttribute('aria-expanded', !closed[id]); try { sessionStorage.setItem('acp-closed', JSON.stringify(closed)); } catch (x) {} return; }
    if ((b = e.target.closest('[data-sset]'))) return ask(setRef(A.sets.filter(function (s) { return s.id === b.dataset.sset; })[0]), b);
    if ((b = e.target.closest('[data-sad]'))) { e.stopPropagation(); return ask(adRef(findAd(b.dataset.sad).a), b); }
    if ((b = e.target.closest('[data-sbudget]'))) return budget(A.sets.filter(function (s) { return s.id === b.dataset.sbudget; })[0]);
    if ((b = e.target.closest('[data-ad]'))) preview(b.dataset.ad); });
  $('acp-sets').addEventListener('keydown', function (e) { var b = e.target.closest('[data-ad]'); if (b && e.key === 'Enter' && e.target === b) preview(b.dataset.ad); });

  /* ---------- تشغيل / إيقاف (بتأكيد) ---------- */
  var pop = $('acp-pop');
  pop.addEventListener('click', function (e) { if (e.target === pop || e.target.closest('[data-x]')) pop.close(); });
  function ask(ref, btn) { var st = ref.status, act = on(st);
    if (!act && st !== 'paused') return; if (!act && !A.spend) return toast(T.ownerOnly, 'error');
    var amt = A.cbo ? budgetText(A.budget) : ref.kind === 'adset_status' ? budgetText((A.sets.filter(function (s) { return s.id === ref.id; })[0] || {}).budget) : T.byAdsets;
    pop.innerHTML = '<form method="dialog" class="p-5 space-y-3" data-ask><div class="flex items-center gap-3"><span class="acp-pi" style="--c:' + (act ? '#d97706' : '#16a34a') + '">' + ic(act ? 'pause' : 'play', 'w-5 h-5') + '</span><b class="text-ink-900" dir="auto">' + esc(f(act ? T.pauseQ : T.resumeQ, ref.name)) + '</b></div>' +
      (act ? '<p class="text-sm text-ink-600">' + esc(T.pauseHint) + '</p>' : '<label class="acp-confirm"><input type="checkbox" name="ok" required><span>' + esc(f(T.spendOk, amt)) + '</span></label>') +
      '<div class="flex gap-2"><button class="btn-primary flex-1' + (act ? ' !bg-amber-600' : '') + '"' + (act ? '' : ' disabled') + '>' + esc(act ? T.pause : T.resume) + '</button><button type="button" class="btn-ghost" data-x>' + esc(T.cancel) + '</button></div></form>';
    var fm = pop.querySelector('[data-ask]'), go = fm.querySelector('.btn-primary'); if (!act) fm.ok.addEventListener('change', function () { go.disabled = !this.checked; });
    fm.addEventListener('submit', function (e) { e.preventDefault(); go.disabled = true; go.textContent = T.loading; var to = act ? 'paused' : 'active', d = { action: ref.kind, to: to }; Object.keys(ref.extra || {}).forEach(function (k) { d[k] = ref.extra[k]; }); if (!act) d.confirm_spend = '1';
      if (btn) btn.classList.add('busy');
      post(d).then(function (j) { if (btn) btn.classList.remove('busy'); if (!j.ok) { go.disabled = false; go.textContent = act ? T.pause : T.resume; return toast(j.msg || T.err, 'error'); }
        ref.status = to; pop.close(); toast(j.msg || T.saved); if (ref.kind === 'campaign_status') hero(); sets(); insights(); if ($('acp-prev').open && ref.kind === 'ad_status') preview(ref.id); }).catch(function () { if (btn) btn.classList.remove('busy'); go.disabled = false; toast(T.err, 'error'); }); });
    pop.showModal(); }
  document.querySelector('.acp-sw.big').addEventListener('click', function () { ask(camp, this); });

  /* ---------- الميزانية ---------- */
  function budget(set) { var b = (set ? set.budget : A.budget) || { amount: '', type: 'daily' }, cur0 = +b.amount || 0;
    pop.innerHTML = '<form method="dialog" class="p-5 space-y-3 w-[min(420px,92vw)]" data-bf><b class="block text-ink-900">' + esc(T.budgetT) + (set ? ' · <span dir="auto">' + esc(set.name) + '</span>' : '') + '</b><p class="text-xs text-ink-500">' + esc(f(T.now, budgetText(b))) + '</p>' +
      '<div class="acp-step"><button type="button" data-st="-0.1">−</button><input name="amount" type="number" step="0.01" min="0.01" required value="' + esc(b.amount) + '" dir="ltr"><span>' + esc(A.cur) + '</span><button type="button" data-st="0.1">+</button></div>' +
      '<div class="flex flex-wrap gap-1.5">' + ['-20', '+10', '+20', '+50'].map(function (p) { return '<button type="button" class="acp-q" data-pc="' + p + '">' + p + '%</button>'; }).join('') + '<div class="acp-seg !p-0.5 ms-auto"><label><input type="radio" name="type" value="daily"' + (b.type !== 'lifetime' ? ' checked' : '') + '><span>' + esc(T.daily) + '</span></label><label><input type="radio" name="type" value="lifetime"' + (b.type === 'lifetime' ? ' checked' : '') + '><span>' + esc(T.lifetime) + '</span></label></div></div>' +
      '<p class="acp-delta" data-dl></p><label class="acp-confirm"><input type="checkbox" name="ok" required><span>' + esc(T.budgetOk) + '</span></label><div class="flex gap-2"><button class="btn-primary flex-1" disabled>' + esc(T.save) + '</button><button type="button" class="btn-ghost" data-x>' + esc(T.cancel) + '</button></div></form>';
    var fm = pop.querySelector('[data-bf]'), go = fm.querySelector('.btn-primary');
    function upd() { var v = +fm.amount.value || 0, d = v - cur0, daily = fm.querySelector('[name=type]:checked').value === 'daily';
      fm.querySelector('[data-dl]').innerHTML = (cur0 && d ? '<b class="' + (d > 0 ? 'text-amber-700' : 'text-lime-700') + '">' + (d > 0 ? '+' : '') + esc(f(T.delta, money(d))) + '</b> · ' : '') + (daily && v ? esc(f(T.perMonth, money(v * 30))) : '');
      go.disabled = !(fm.ok.checked && v > 0); }
    fm.addEventListener('click', function (e) { var s = e.target.closest('[data-st]'), p = e.target.closest('[data-pc]'), v = +fm.amount.value || cur0 || 1;
      if (s) fm.amount.value = Math.max(0.01, v * (1 + +s.dataset.st)).toFixed(2); if (p) fm.amount.value = Math.max(0.01, (cur0 || v) * (1 + parseFloat(p.dataset.pc) / 100)).toFixed(2); if (s || p) upd(); });
    fm.addEventListener('input', upd); fm.addEventListener('change', upd); upd();
    fm.addEventListener('submit', function (e) { e.preventDefault(); go.disabled = true; go.textContent = T.loading; var d = { action: 'budget', amount: fm.amount.value, type: fm.querySelector('[name=type]:checked').value, confirm_spend: '1' }; if (set) d.adset_id = set.id;
      post(d).then(function (j) { if (!j.ok) { go.disabled = false; go.textContent = T.save; return toast(j.msg || T.err, 'error'); } var nb = { amount: +d.amount, type: d.type }; if (set) set.budget = nb; else A.budget = nb; pop.close(); toast(j.msg || T.saved); hero(); sets(); insights(); })
        .catch(function () { go.disabled = false; go.textContent = T.save; toast(T.err, 'error'); }); });
    pop.showModal(); fm.amount.focus(); fm.amount.select(); }
  document.querySelector('.acp-budget').addEventListener('click', function () { if (A.cbo && A.spend) budget(null); });

  /* ---------- معاينة الإعلان ---------- */
  var pv = $('acp-prev'); pv.addEventListener('click', function (e) { if (e.target === pv || e.target.closest('[data-x]')) pv.close(); var b = e.target.closest('[data-pvsw]'); if (b) ask(adRef(findAd(b.dataset.pvsw).a), b); });
  function preview(id) { var x = findAd(id); if (!x) return; var a = x.a, c = +a.m.impressions > 0 ? ctr(a.m) : null, dom = ''; try { dom = a.link ? new URL(a.link).hostname.replace(/^www\./, '') : ''; } catch (e) {}
    pv.innerHTML = '<div class="grid md:grid-cols-[320px_minmax(0,1fr)]"><div class="acp-phonebg"><div class="acp-phone"><div class="flex items-center gap-2 p-3"><span class="acp-pav">' + esc((A.acc || 'A').charAt(0)) + '</span><div class="min-w-0"><b class="block text-[13px] text-[#0f172a] truncate">' + esc(A.acc || '') + '</b><small class="text-[11px] text-[#64748b]">' + esc(T.sponsored) + ' · 🌐</small></div></div>' +
      (a.body ? '<p class="px-3 pb-2 text-[13px] text-[#0f172a] whitespace-pre-line" dir="auto">' + esc(a.body) + '</p>' : '') + '<div class="acp-pimg">' + thumb(a) + (a.video ? '<span class="acp-vid big">▶</span>' : '') + '</div>' +
      '<div class="flex items-center gap-2 bg-[#f1f5f9] px-3 py-2"><div class="min-w-0 flex-1"><small class="block text-[10px] uppercase text-[#64748b] truncate">' + esc(dom) + '</small><b class="block text-[13px] text-[#0f172a] truncate" dir="auto">' + esc(a.head || a.name) + '</b></div><span class="rounded-md bg-[#e2e8f0] px-2.5 py-1.5 text-[11px] font-bold text-[#0f172a]">' + esc(T.learn) + '</span></div>' +
      '<div class="flex justify-around border-t border-[#e2e8f0] py-2 text-[11px] text-[#64748b]"><span>👍</span><span>💬</span><span>↗</span></div></div></div>' +
      '<div class="p-5 space-y-4"><div class="flex items-start gap-2"><div class="min-w-0 flex-1"><b class="block text-lg text-ink-900" dir="auto">' + esc(a.name) + '</b><span class="text-xs text-ink-500" dir="auto">' + esc(x.s.name) + '</span></div>' + (a.id ? '<button type="button" class="acp-sw" data-pvsw="' + esc(a.id) + '" role="switch"></button>' : '') + '<button type="button" class="p-2 rounded-xl text-ink-400 hover:bg-ink-100" data-x>' + ic('x', 'w-5 h-5') + '</button></div>' +
      (a.reject ? '<div class="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-800"><b>' + esc(T.reject) + ':</b> ' + esc(a.reject) + '</div>' : '') +
      '<div class="grid grid-cols-2 gap-2">' + [[T.spend, money(a.m.spend)], [T.imp, num(a.m.impressions)], [T.clicks, num(a.m.clicks)], [T.ctr, c == null ? '—' : num(c, 2) + '%'], [T.cpc, cpc(a.m) ? money(cpc(a.m)) : '—'], [T.res, num(res(a.m))]].map(function (k) { return '<div class="rounded-xl bg-ink-50 p-3"><small class="block text-[11px] text-ink-500">' + esc(k[0]) + '</small><b class="text-ink-900 tabular-nums" dir="ltr">' + esc(k[1]) + '</b></div>'; }).join('') + '</div>' +
      '<div class="flex flex-wrap gap-2">' + (a.link ? '<a class="btn-ghost !text-sm" target="_blank" rel="noopener" href="' + esc(a.link) + '">' + ic('globe', 'w-4 h-4') + esc(T.open) + '</a>' : '') + (a.post ? '<a class="btn-ghost !text-sm" target="_blank" rel="noopener" href="' + esc(a.post) + '">' + ic('eye', 'w-4 h-4') + esc(T.post) + '</a>' : '') + '</div></div></div>';
    var sw = pv.querySelector('[data-pvsw]'); if (sw) swPaint(sw, a.status); if (!pv.open) pv.showModal(); }

  /* ---------- كيبورد ---------- */
  document.addEventListener('keydown', function (e) { if (e.ctrlKey || e.metaKey || e.altKey || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || document.querySelector('dialog[open]')) return;
    if (e.key === '/') { e.preventDefault(); $('acp-q').focus(); } else if (e.key === 'b') location.href = A.back; else if (/^[1-6]$/.test(e.key) && MT[+e.key - 1]) { metric = MT[+e.key - 1][0]; chart(); } });

  hero(); kpis(); chart(); funnel(); insights(); sets();
  new MutationObserver(function () { if (ec) { chart(); } }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
})();
