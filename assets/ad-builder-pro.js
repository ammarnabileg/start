/* منشئ الحملات: شريط خطوات (الحملة ← المجموعات ← الإعلانات ← المراجعة والإطلاق) بحالة كل خطوة،
   أزرار السابق/التالي، ومعاينة حية احترافية حسب المنصة والمكان (فيد / ستوري / ريلز…) مع الكاروسيل وتبديل النسخ */
(function () {
  var X = window.ABX, A = window.AB, root = document.getElementById('ab-app'); if (!X || !A || !root || !A.px) return;
  var D = X.D, S = X.S, P = A.px, esc = X.esc, fmt = X.fmt;
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  var setName = function (si) { return D.adSets[si].name || fmt(S.setN, si + 1); }, adName = function (si, ai) { return D.adSets[si].ads[ai].name || fmt(S.adN, ai + 1); };

  /* ---------- ترتيب الخطوات ---------- */
  function seq() { var o = [{ t: 'campaign', s: 0, a: 0 }]; D.adSets.forEach(function (st, si) { o.push({ t: 'adset', s: si, a: 0 }); st.ads.forEach(function (ad, ai) { o.push({ t: 'ad', s: si, a: ai }); }); }); o.push({ t: 'launch' }); return o; }
  function idx(cur) { var q = seq(); for (var i = 0; i < q.length; i++) if (q[i].t === cur.t && (cur.t === 'campaign' || q[i].s === cur.s) && (cur.t !== 'ad' || q[i].a === cur.a)) return i; return 0; }
  function label(n) { return n.t === 'campaign' ? S.campaign : n.t === 'adset' ? setName(n.s) : n.t === 'ad' ? setName(n.s) + ' › ' + adName(n.s, n.a) : P.review; }
  function go(n) { if (n.t === 'launch') { var l = document.getElementById('ab-launch') || document.getElementById('ab-side'); if (l) { l.scrollIntoView({ behavior: 'smooth', block: 'start' }); l.classList.add('ab-flash'); setTimeout(function () { l.classList.remove('ab-flash'); }, 1400); } return; }
    X.go(n.t, n.s, n.a); window.scrollTo({ top: Math.max(0, root.getBoundingClientRect().top + scrollY - 80), behavior: 'smooth' }); }

  /* ---------- شريط الخطوات ---------- */
  function steps(iss) { var cur = X.sel(), errs = function (t) { return iss.filter(function (x) { return x.lvl === 'err' && (t === 'launch' || x.t === t); }).length; },
      nAds = D.adSets.reduce(function (a, s) { return a + s.ads.length; }, 0),
      st = [['campaign', P.s1, 'target', S.campaign, errs('campaign')], ['adset', P.s2, 'users', fmt(P.nSets, D.adSets.length), errs('adset')], ['ad', P.s3, 'image', fmt(P.nAds, nAds), errs('ad')], ['launch', P.s4, 'spark', P.review, errs('launch')]],
      done = st.filter(function (x) { return !x[4]; }).length, pct = Math.round(done / 4 * 100);
    return '<nav class="ab-steps" aria-label="steps"><div class="ab-ring" style="--p:' + pct + '"><b>' + pct + '%</b></div>' + st.map(function (x, i) { var on = cur.t === x[0], ok = !x[4];
      return '<button type="button" class="ab-step' + (on ? ' on' : '') + (ok ? ' ok' : ' bad') + '" data-step="' + x[0] + '"><span class="ab-si">' + (ok && !on ? '✓' : ic(x[2], 'w-4 h-4')) + '</span><span class="min-w-0 text-start"><small>' + esc(fmt(P.stepN, i + 1)) + '</small><b>' + esc(x[1]) + '</b><em>' + (x[4] ? '<i>' + esc(fmt(P.nErr, x[4])) + '</i>' : esc(x[3])) + '</em></span></button>' + (i < 3 ? '<span class="ab-line' + (ok ? ' ok' : '') + '"></span>' : ''); }).join('') + '</nav>'; }
  function navBar() { var q = seq(), i = idx(X.sel()), pv = q[i - 1], nx = q[i + 1];
    return '<div class="ab-nav">' + (pv ? '<button type="button" class="btn-ghost" data-nav="' + (i - 1) + '">' + ic('chevron-right', 'w-4 h-4') + '<span class="truncate max-w-[22ch]">' + esc(label(pv)) + '</span></button>' : '<span></span>') +
      '<span class="text-[11px] text-ink-400 tabular-nums">' + (i + 1) + ' / ' + q.length + '</span>' +
      (nx ? '<button type="button" class="btn-primary" data-nav="' + (i + 1) + '"><span class="truncate max-w-[26ch]">' + esc(fmt(P.next, label(nx))) + '</span>' + ic('chevron-left', 'w-4 h-4') + '</button>' : '<span></span>') + '</div>'; }
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-step]'), n = e.target.closest('[data-nav]'), v;
    if (n) return go(seq()[+n.dataset.nav]);
    if (!b) return; v = b.dataset.step; var iss = X.issues().filter(function (x) { return x.lvl === 'err' && x.t === v; })[0];
    if (v === 'launch') return go({ t: 'launch' }); if (iss) return go({ t: iss.t, s: iss.s || 0, a: iss.a || 0 }); go({ t: v, s: 0, a: 0 }); });

  /* ---------- المعاينة الحية ---------- */
  var PL = { meta: [['fb', 'Facebook'], ['ig', 'Instagram'], ['story', P.story]], linkedin: [['li', 'LinkedIn']], tiktok: [['tt', 'TikTok']], x: [['x', 'X']], pinterest: [['pin', 'Pinterest']], google: [['gd', 'Google']] };
  var place = null, vi = 0, card = 0;
  function host(u) { try { return new URL(u).hostname.replace(/^www\./, ''); } catch (e) { return ''; } }
  function pick(ad) { var hs = [ad.headline].concat(ad.headlines || []).filter(Boolean), bs = [ad.body].concat(ad.bodies || []).filter(Boolean), n = Math.max(hs.length, bs.length, 1);
    return { n: n, h: hs.length ? hs[vi % hs.length] : '', b: bs.length ? bs[vi % bs.length] : '' }; }
  function media(ad, tall) { var src = ad.format === 'video' ? ad.thumbnailUrl : ad.imageUrl, cls = tall ? 'ab-pm tall' : 'ab-pm';
    if (ad.format === 'carousel') { var cs = (ad.cards || []).length ? ad.cards : [{}];
      return '<div class="ab-car" data-car>' + cs.map(function (c, k) { return '<div class="ab-cc"><div class="ab-pm sq">' + (c.imageUrl ? '<img src="' + esc(c.imageUrl) + '" alt="" onerror="this.remove()">' : '<span class="ab-ph">' + ic('image', 'w-8 h-8') + '</span>') + '</div><div class="ab-cct"><b dir="auto">' + esc(c.headline || P.noHead) + '</b>' + (c.description ? '<small dir="auto">' + esc(c.description) + '</small>' : '') + '</div></div>'; }).join('') + '</div>' +
        (cs.length > 1 ? '<div class="ab-dots">' + cs.map(function (_, k) { return '<i class="' + (k === 0 ? 'on' : '') + '"></i>'; }).join('') + '</div>' : ''); }
    return '<div class="' + cls + '">' + (src ? '<img src="' + esc(src) + '" alt="" onerror="this.remove()">' : '<span class="ab-ph">' + ic(ad.format === 'video' ? 'play' : 'image', 'w-10 h-10') + '<small>' + esc(ad.format === 'video' ? P.noVideo : P.noImg) + '</small></span>') + (ad.format === 'video' ? '<span class="ab-play">▶</span>' : '') + '</div>'; }
  function more(t, n) { t = String(t || ''); return t.length > n ? esc(t.slice(0, n).trim()) + '… <b class="ab-more">' + esc(P.seeMore) + '</b>' : esc(t); }
  function frame(ad, pl) { var c = pick(ad), cta = (S.ctas || {})[ad.cta] || ad.cta, nm = D.accName || A.platformLabel, av = '<span class="ab-av">' + esc((nm || '?').charAt(0)) + '</span>', h = host(ad.linkUrl);
    if (pl === 'story' || pl === 'tt') return '<div class="ab-ph9">' + media(ad, true) + '<div class="ab-sto-top">' + av + '<b>' + esc(nm) + '</b><small>' + esc(P.sponsored) + '</small></div>' +
      '<div class="ab-sto-bot"><p dir="auto">' + more(c.b, 90) + '</p><span class="ab-sto-cta">' + esc(pl === 'tt' ? cta : '⌃ ' + cta) + '</span></div>' + (pl === 'tt' ? '<div class="ab-tt-side"><i>♥</i><i>💬</i><i>↗</i></div>' : '') + '</div>';
    if (pl === 'ig') return '<div class="ab-feed"><div class="ab-fh">' + av + '<div><b>' + esc(nm) + '</b><small>' + esc(P.sponsored) + '</small></div><span class="ms-auto">⋯</span></div>' + media(ad) +
      '<div class="ab-igcta"><span>' + esc(cta) + '</span><span>›</span></div><div class="ab-igic">♡ 💬 ➤<span class="ms-auto">⌑</span></div><p class="ab-fb-t" dir="auto"><b>' + esc(nm) + '</b> ' + more(c.b, 125) + '</p></div>';
    if (pl === 'x') return '<div class="ab-feed p-3"><div class="flex gap-2">' + av + '<div class="min-w-0 flex-1"><b class="text-[13px] text-[#0f172a]">' + esc(nm) + '</b> <small class="text-[#64748b]">· ' + esc(P.promoted) + '</small><p class="text-[13px] text-[#0f172a] mt-0.5" dir="auto">' + more(c.b, 280) + '</p><div class="mt-2 overflow-hidden rounded-2xl border border-[#e2e8f0]">' + media(ad) + '<div class="px-3 py-2 text-[12px] text-[#64748b]">' + esc(h) + '</div></div></div></div></div>';
    var li = pl === 'li';
    return '<div class="ab-feed"><div class="ab-fh">' + av + '<div><b>' + esc(nm) + '</b><small>' + esc(li ? P.promoted : P.sponsored) + ' · 🌐</small></div><span class="ms-auto">⋯</span></div><p class="ab-fb-t" dir="auto">' + more(c.b, li ? 150 : 125) + '</p>' + media(ad) +
      (ad.format === 'carousel' ? '' : '<div class="ab-link"><div class="min-w-0 flex-1">' + (h ? '<small dir="ltr">' + esc(h) + '</small>' : '') + '<b dir="auto">' + esc(c.h || P.noHead) + '</b></div><span class="ab-cta">' + esc(cta) + '</span></div>') +
      '<div class="ab-react">' + (li ? '<span>👍 ' + esc(P.like) + '</span><span>💬 ' + esc(P.comment) + '</span><span>↗ ' + esc(P.share) + '</span>' : '<span>👍</span><span>💬</span><span>↗</span>') + '</div></div>'; }
  function checks(ad, c) { var o = [], lim = { fb: [125, 40], ig: [125, 40], li: [150, 70], x: [280, 70], tt: [100, 40], story: [90, 40] }[place] || [125, 40];
    if (c.b.length > lim[0]) o.push(fmt(P.cutBody, lim[0])); if (c.h.length > lim[1]) o.push(fmt(P.cutHead, lim[1]));
    if (ad.format === 'image' && !ad.imageUrl) o.push(P.noImg); if (!ad.linkUrl && ad.cta !== 'WHATSAPP_MESSAGE') o.push(P.noLink);
    return o; }
  function preview() { var box = document.getElementById('ab-preview'), s = X.sel(); if (!box) return;
    if (s.t !== 'ad') { box.innerHTML = ''; return; } var ad = D.adSets[s.s] && D.adSets[s.s].ads[s.a]; if (!ad) return;
    var pls = PL[D.platform] || [['fb', P.feed]]; if (!place || !pls.some(function (x) { return x[0] === place; })) place = pls[0][0];
    var c = pick(ad); if (vi >= c.n) vi = 0; var w = checks(ad, c);
    box.innerHTML = '<section class="card ab-pv"><div class="flex items-center gap-2 mb-3"><b class="text-sm text-ink-900 me-auto">' + ic('eye', 'w-4 h-4 inline') + ' ' + esc(P.live) + '</b>' +
      (pls.length > 1 ? '<div class="ab-seg">' + pls.map(function (x) { return '<button type="button" data-pl="' + x[0] + '" class="' + (x[0] === place ? 'on' : '') + '">' + esc(x[1]) + '</button>'; }).join('') + '</div>' : '<span class="text-[11px] text-ink-500">' + esc(pls[0][1]) + '</span>') + '</div>' +
      '<div class="ab-stage"><div class="ab-phone' + (place === 'story' || place === 'tt' ? ' tall' : '') + '">' + frame(ad, place) + '</div></div>' +
      (c.n > 1 ? '<div class="ab-var"><button type="button" data-vi="-1">‹</button><span>' + esc(fmt(P.variant, vi + 1, c.n)) + '</span><button type="button" data-vi="1">›</button></div>' : '') +
      (w.length ? '<ul class="ab-warn">' + w.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>' : '<p class="ab-good">✓ ' + esc(P.looksGood) + '</p>') + '</section>';
    var car = box.querySelector('[data-car]'); if (car) car.addEventListener('scroll', function () { var k = Math.round(Math.abs(car.scrollLeft) / (car.firstChild.offsetWidth + 8)); box.querySelectorAll('.ab-dots i').forEach(function (d, j) { d.classList.toggle('on', j === k); }); }); }
  root.addEventListener('click', function (e) { var b = e.target.closest('#ab-preview [data-pl]'), v = e.target.closest('#ab-preview [data-vi]');
    if (b) { place = b.dataset.pl; preview(); } if (v) { var ad = D.adSets[X.sel().s].ads[X.sel().a], n = pick(ad).n; vi = (vi + +v.dataset.vi + n) % n; preview(); } });

  /* ---------- كل رسم ---------- */
  document.addEventListener('ab:render', function (e) { var iss = (e.detail && e.detail.issues) || X.issues();
    var top = root.firstElementChild; if (!top) return; var old = root.querySelector('.ab-steps'); if (old) old.outerHTML = steps(iss); else top.insertAdjacentHTML('afterend', steps(iss));
    var ed = document.getElementById('ab-editor'); if (ed && !(e.detail && e.detail.soft)) { var nb = ed.querySelector('.ab-nav'); if (nb) nb.remove(); ed.insertAdjacentHTML('beforeend', navBar()); }
    preview(); });
  X.rerender();
})();
