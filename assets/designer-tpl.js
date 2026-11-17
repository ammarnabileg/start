/* قوالب المصمم الاحترافية: نظام تصميم واحد (هوامش، تدرّج خطوط، ألوان الهوية)، والنصوص بتترص تحت بعض حسب طولها الفعلي فمفيش تداخل */
(function () {
  var X = window.DZX; if (!X || !window.fabric) return;
  var L = X.L, T = X.T, R = X.R, circ = X.circ, ph = X.ph, pill = X.pill, K = X.K || {}, SVS = X.SVS || [], CO = X.CO, WA = X.WA, HANDLE = X.HANDLE;
  var LT = (X.C && X.C.tl) || {}; function tl(k, d) { return LT[k] || d; }
  var SV = SVS[0] || {}, P = X.P, S = X.S, A = X.A;
  var RTL = X.isAr(CO + (SV.name || '') + (L.offer || '')) || document.documentElement.dir === 'rtl';
  var SHORT = function (s, n) { s = String(s || '').replace(/\s+/g, ' ').trim(); if (s.length <= n) return s; s = s.slice(0, n); var i = s.lastIndexOf(' '); return (i > n * 0.5 ? s.slice(0, i) : s) + '…'; };
  var FIRST = function (s, n) { s = String(s || '').replace(/\s+/g, ' ').trim(); var m = s.split(/[.!?؟؛;\n]/)[0].trim(); var c = m.indexOf(':'); if (c > -1 && c < 28 && m.length - c > 8) m = m.slice(c + 1).trim(); return SHORT(m || s, n); };
  var POINTS = function (n) { var src = [K.rtb, K.pod, K.about].filter(Boolean).join('. '), out = String(src).split(/[.!?؟؛;\n•\-–]+/).map(function (x) { return x.trim(); }).filter(function (x) { return x.length > 8; }); if (out.length < n) SVS.forEach(function (s) { if (out.length < n) out.push(s.name); }); return out.slice(0, n).map(function (x) { return SHORT(x, 70); }); };
  var CONTACT = WA || HANDLE || '';
  var HEAD = FIRST(X.C.title || K.focus || SV.name || L.h1, 60);
  var SUB = FIRST(K.tagline || K.pod || K.about || '', 120);
  function refresh() { HEAD = FIRST(X.C.title || K.focus || SV.name || L.h1, 60); SUB = FIRST(X.C.text || K.tagline || K.pod || K.about || '', 120); } // العنوان من الذكاء الاصطناعي بيوصل للقالب

  /* ---------- نظام التصميم ---------- */
  function g() { var W = X.W, H = X.H; return { W: W, H: H, M: Math.round(W * 0.08), u: W / 100 }; }
  function txt(t, y, o) { var G = g(); o = o || {}; var w = o.w || (G.W - 2 * G.M), x = o.x != null ? o.x : G.M;
    var tb = T(String(t || ''), Object.assign({ left: x, top: y, width: w, fontSize: G.u * 3.4, fill: '#334155', lineHeight: 1.35, textAlign: o.align || (RTL ? 'right' : 'left') }, o)); delete tb.w; return tb; }
  function hgt(tb) { return tb ? (tb.height || tb.calcTextHeight && tb.calcTextHeight() || 0) : 0; }
  function brow(t, y, col, objs) { var G = g(), fs = G.u * 2.6, bw = G.u * 6, gap = G.u * 1.6;
    objs.push(R(RTL ? G.W - G.M - bw : G.M, y + fs * 0.55, bw, Math.max(4, G.u * 0.55), col, { rx: 3, ry: 3 }));
    var tb = txt(t, y, { x: RTL ? G.M : G.M + bw + gap, w: G.W - 2 * G.M - bw - gap, fontSize: fs, fontWeight: 700, fill: col, maxLines: 1 }); objs.push(tb); return y + hgt(tb); }
  function foot(objs, dark) { var G = g(), y = G.H - G.M * 1.25, c = dark ? 'rgba(255,255,255,.22)' : 'rgba(15,23,42,.10)', tc = dark ? '#ffffff' : S, mc = dark ? 'rgba(255,255,255,.7)' : '#64748b', half = (G.W - 2 * G.M) / 2;
    objs.push(R(G.M, y - G.u * 2.4, G.W - 2 * G.M, 2, c));
    objs.push(txt(CO, y - G.u * 0.4, { x: RTL ? G.M + half : G.M, w: half, fontSize: G.u * 2.5, fontWeight: 800, fill: tc, maxLines: 1, align: RTL ? 'right' : 'left' }));
    if (CONTACT) objs.push(txt(CONTACT, y - G.u * 0.4, { x: RTL ? G.M : G.M + half, w: half, fontSize: G.u * 2.4, fontWeight: 600, fill: mc, maxLines: 1, align: RTL ? 'left' : 'right', fontFamily: 'Inter' })); }
  function card(x, y, w, h, fill, o) { return R(x, y, w, h, fill || '#fff', Object.assign({ rx: g().u * 2.4, ry: g().u * 2.4, shadow: new fabric.Shadow({ color: 'rgba(15,23,42,.10)', blur: 40, offsetY: 14 }) }, o || {})); }
  function num(n, cx, cy, r, bg, fg, objs) { objs.push(circ(cx, cy, r, bg)); objs.push(T(String(n), { left: cx - r, width: r * 2, top: cy - r * 0.62, fontSize: r * 1.05, fill: fg, fontWeight: 800, fontFamily: 'Inter', textAlign: 'center', maxLines: 1 })); }
  function cta(t, y, bg, fg, objs) { var G = g(), fs = G.u * 3, p = pill(t, RTL ? G.W - G.M : G.M, y, bg, fg, fs); if (RTL) { p.set({ originX: 'right' }); } objs.push(p); return y + fs * 2.1; }
  function blob(x, y, r, col, op) { return circ(x, y, r, col, { opacity: op }); }
  function dots(x, y, cols, rows, gap, col, op, objs) { for (var i = 0; i < cols; i++) for (var j = 0; j < rows; j++) objs.push(circ(x + i * gap, y + j * gap, gap * 0.12, col, { opacity: op })); }

  var TPLS = [
    /* 1) عرض رئيسي: خلفية داكنة، عنوان قوي، سعر في بطاقة، زر */
    { n: tl('a1', 'عرض رئيسي'), cat: 'offer', size: '1080x1080', b: function () { var G = g(), o = [], y = G.M * 1.3;
      o.push(blob(RTL ? G.W * 0.08 : G.W * 0.92, G.H * 0.1, G.W * 0.34, P, 0.35), blob(RTL ? G.W * 0.95 : G.W * 0.05, G.H * 0.98, G.W * 0.28, A, 0.18)); dots(RTL ? G.M : G.W - G.M - G.u * 16, G.H * 0.62, 5, 4, G.u * 4, '#fff', 0.25, o);
      y = brow(tl('offer', 'عرض خاص'), y, A, o) + G.u * 3;
      var h = txt(SV.name ? FIRST(SV.name, 50) : HEAD, y, { fontSize: G.u * 8, fontWeight: 900, fill: '#fff', lineHeight: 1.18, maxLines: 3 }); o.push(h); y += hgt(h) + G.u * 3;
      if (SUB) { var s = txt(SUB, y, { fontSize: G.u * 3.3, fill: 'rgba(255,255,255,.78)', maxLines: 3, w: (G.W - 2 * G.M) * 0.82, x: RTL ? G.M + (G.W - 2 * G.M) * 0.18 : G.M }); o.push(s); y += hgt(s) + G.u * 4; }
      if (SV.price) { var pw = G.u * 30, px = RTL ? G.W - G.M - pw : G.M; o.push(R(px, y, pw, G.u * 12, A, { rx: G.u * 2, ry: G.u * 2 }), T(SV.price, { left: px, width: pw, top: y + G.u * 2.6, fontSize: G.u * 5.4, fontWeight: 900, fill: S, textAlign: 'center', maxLines: 1 })); y += G.u * 16; }
      cta(WA ? L.orderWa : tl('learnMore', 'اعرف المزيد'), Math.min(y, G.H - G.M * 2.9), '#fff', S, o); foot(o, true); return { bg: S, objs: o }; } },

    /* 2) تسليط الضوء على خدمة: صورة كبيرة فوق ونص منظم تحت */
    { n: tl('a2', 'خدمة مميزة'), cat: 'product', size: '1080x1350', b: function () { var G = g(), o = [], ih = G.H * 0.5;
      o.push(ph(G.M, G.M, G.W - 2 * G.M, ih, { rx: G.u * 3 }));
      o.push(R(RTL ? G.W - G.M - G.u * 24 : G.M + G.u * 3, G.M + G.u * 3, G.u * 21, G.u * 6, P, { rx: G.u * 3, ry: G.u * 3 }), T(tl('service', 'خدماتنا'), { left: RTL ? G.W - G.M - G.u * 24 : G.M + G.u * 3, width: G.u * 21, top: G.M + G.u * 4.2, fontSize: G.u * 2.5, fontWeight: 700, fill: '#fff', textAlign: 'center', maxLines: 1 }));
      var y = G.M + ih + G.u * 6, h = txt(FIRST(SV.name || HEAD, 60), y, { fontSize: G.u * 6.4, fontWeight: 900, fill: S, lineHeight: 1.2, maxLines: 2 }); o.push(h); y += hgt(h) + G.u * 2.5;
      var d = txt(SHORT(SV.details || SUB || K.about, 150), y, { fontSize: G.u * 3.1, fill: '#475569', maxLines: 3 }); o.push(d); y += hgt(d) + G.u * 4;
      cta(SV.price ? SV.price : tl('learnMore', 'اعرف المزيد'), Math.min(y, G.H - G.M * 2.8), P, '#fff', o); foot(o, false); return { bg: '#ffffff', objs: o }; } },

    /* 3) لماذا نحن: 3 نقاط مرقّمة في بطاقات */
    { n: tl('a3', 'لماذا نحن'), cat: 'edu', size: '1080x1350', b: function () { var G = g(), o = [], y = G.M * 1.2;
      o.push(R(0, 0, G.W, G.H * 0.34, S), blob(RTL ? 0 : G.W, 0, G.W * 0.3, P, 0.45));
      y = brow(CO, y, A, o) + G.u * 3; var h = txt(tl('whyUs', 'لماذا يختارنا عملاؤنا؟'), y, { fontSize: G.u * 7, fontWeight: 900, fill: '#fff', maxLines: 2, lineHeight: 1.2 }); o.push(h);
      var pts = POINTS(3), cy = G.H * 0.38, gap = G.u * 3.5, ch = Math.min(G.u * 26, (G.H - cy - G.M * 2.3 - gap * 2) / 3);
      pts.forEach(function (p, i) { var yy = cy + i * (ch + gap), r = G.u * 4.4; o.push(card(G.M, yy, G.W - 2 * G.M, ch, '#fff'));
        var cx = RTL ? G.W - G.M - G.u * 4 - r : G.M + G.u * 4 + r; num(i + 1, cx, yy + ch / 2, r, P, '#fff', o);
        var tw = G.W - 2 * G.M - G.u * 8 - r * 2 - G.u * 3; o.push(T(p, { left: RTL ? G.M + G.u * 4 : G.M + G.u * 7 + r * 2, width: tw, top: yy + ch / 2 - G.u * 3.6, fontSize: G.u * 3.2, fontWeight: 700, fill: S, lineHeight: 1.3, maxLines: 2, textAlign: RTL ? 'right' : 'left' })); });
      foot(o, false); return { bg: '#f4f6fb', objs: o }; } },

    /* 4) حلولنا: شبكة 2×2 للخدمات */
    { n: tl('a4', 'حلولنا'), cat: 'product', size: '1080x1350', b: function () { var G = g(), o = [], y = G.M * 1.2;
      y = brow(CO, y, P, o) + G.u * 2.5; var h = txt(tl('solutions', 'حلول متكاملة لأعمالك'), y, { fontSize: G.u * 7, fontWeight: 900, fill: S, maxLines: 2, lineHeight: 1.2 }); o.push(h); y += hgt(h) + G.u * 5;
      var list = SVS.length ? SVS.slice(0, 4) : [{ name: L.item + ' 1' }, { name: L.item + ' 2' }, { name: L.item + ' 3' }, { name: L.item + ' 4' }];
      var gw = (G.W - 2 * G.M - G.u * 4) / 2, gh = Math.min(G.u * 40, (G.H - y - G.M * 2.3 - G.u * 4) / 2);
      list.forEach(function (s, i) { var col = i % 2, row = Math.floor(i / 2), x = RTL ? G.W - G.M - gw - col * (gw + G.u * 4) : G.M + col * (gw + G.u * 4), yy = y + row * (gh + G.u * 4), dark = i === 0;
        o.push(card(x, yy, gw, gh, dark ? P : '#fff')); o.push(R(RTL ? x + gw - G.u * 4 - G.u * 7 : x + G.u * 4, yy + G.u * 4, G.u * 7, G.u * 0.8, dark ? '#fff' : A, { rx: 3, ry: 3 }));
        o.push(T(String(i + 1).padStart(2, '0'), { left: x + G.u * 4, width: gw - G.u * 8, top: yy + G.u * 6.5, fontSize: G.u * 4.4, fontWeight: 900, fill: dark ? 'rgba(255,255,255,.5)' : 'rgba(15,23,42,.18)', fontFamily: 'Inter', textAlign: RTL ? 'right' : 'left', maxLines: 1 }));
        o.push(T(SHORT(s.name, 60), { left: x + G.u * 4, width: gw - G.u * 8, top: yy + G.u * 13, fontSize: G.u * 3.8, fontWeight: 800, fill: dark ? '#fff' : S, lineHeight: 1.3, maxLines: 3, textAlign: RTL ? 'right' : 'left' })); });
      foot(o, false); return { bg: '#f4f6fb', objs: o }; } },

    /* 5) رسالة الشركة / قيمة: اقتباس كبير على لون أساسي */
    { n: tl('a5', 'رسالتنا'), cat: 'quote', size: '1080x1080', b: function () { var G = g(), o = [];
      o.push(blob(RTL ? G.W : 0, G.H, G.W * 0.45, '#fff', 0.07), T('“', { left: G.M, width: G.W - 2 * G.M, top: G.M * 0.6, fontSize: G.u * 26, fill: A, fontFamily: 'Playfair Display', lineHeight: 1, textAlign: RTL ? 'right' : 'left', maxLines: 1 }));
      var q = txt(FIRST(K.pod || K.tagline || K.about || L.quote, 150), G.H * 0.3, { fontSize: G.u * 5.6, fontWeight: 800, fill: '#fff', lineHeight: 1.35, maxLines: 4 }); o.push(q);
      o.push(R(RTL ? G.W - G.M - G.u * 10 : G.M, G.H * 0.3 + hgt(q) + G.u * 5, G.u * 10, G.u * 0.7, A)); foot(o, true); return { bg: P, objs: o }; } },

    /* 6) رأي عميل */
    { n: tl('a6', 'رأي عميل'), cat: 'quote', size: '1080x1080', b: function () { var G = g(), o = [], cw = G.W - 2 * G.M, chh = G.H * 0.62, cy = G.H * 0.12;
      o.push(R(0, 0, G.W, G.H * 0.45, S), card(G.M, cy, cw, chh, '#fff'));
      o.push(T('★★★★★', { left: G.M + G.u * 6, width: cw - G.u * 12, top: cy + G.u * 6, fontSize: G.u * 4.4, fill: A, textAlign: RTL ? 'right' : 'left', fontFamily: 'Inter', maxLines: 1 }));
      var q = T(tl('clientQuote', 'تعاملنا معهم كان تجربة مختلفة: احترافية في التنفيذ، والتزام بالمواعيد، ونتائج واضحة.'), { left: G.M + G.u * 6, width: cw - G.u * 12, top: cy + G.u * 14, fontSize: G.u * 4, fontWeight: 700, fill: S, lineHeight: 1.45, maxLines: 4, textAlign: RTL ? 'right' : 'left' }); o.push(q);
      o.push(R(RTL ? G.W - G.M - G.u * 6 - G.u * 8 : G.M + G.u * 6, cy + chh - G.u * 14, G.u * 8, G.u * 0.6, P), T(tl('clientName', 'اسم العميل · المنصب، الشركة'), { left: G.M + G.u * 6, width: cw - G.u * 12, top: cy + chh - G.u * 11, fontSize: G.u * 2.8, fontWeight: 700, fill: '#64748b', textAlign: RTL ? 'right' : 'left', maxLines: 1 }));
      foot(o, false); return { bg: '#f4f6fb', objs: o }; } },

    /* 7) معلومة / نصيحة */
    { n: tl('a7', 'معلومة تهمّك'), cat: 'edu', size: '1080x1080', b: function () { var G = g(), o = [], y = G.M * 1.3;
      o.push(R(RTL ? G.W - G.u * 2 : 0, 0, G.u * 2, G.H, P)); dots(RTL ? G.M : G.W - G.M - G.u * 12, G.M, 4, 4, G.u * 4, P, 0.25, o);
      y = brow(tl('insight', 'معلومة تهمّك'), y, P, o) + G.u * 4;
      var h = txt(HEAD, y, { fontSize: G.u * 7, fontWeight: 900, fill: S, lineHeight: 1.2, maxLines: 3 }); o.push(h); y += hgt(h) + G.u * 4;
      var b = txt(SHORT(K.about || SUB, 200), y, { fontSize: G.u * 3.4, fill: '#475569', lineHeight: 1.55, maxLines: 5 }); o.push(b);
      foot(o, false); return { bg: '#ffffff', objs: o }; } },

    /* 8) أسئلة شائعة: بطاقات سؤال وجواب */
    { n: tl('a8', 'أسئلة شائعة'), cat: 'edu', size: '1080x1350', b: function () { var G = g(), o = [], y = G.M * 1.2, F = (K.faq || []).slice(0, 3);
      if (!F.length) F = [{ q: L.s1 || '?', a: L.sub || '' }, { q: L.s2 || '?', a: L.sub || '' }, { q: L.s3 || '?', a: L.sub || '' }];
      y = brow(tl('faq', 'أسئلة شائعة'), y, P, o) + G.u * 2.5; var h = txt(tl('faqH', 'إجابات سريعة على أهم أسئلتكم'), y, { fontSize: G.u * 6, fontWeight: 900, fill: S, maxLines: 2, lineHeight: 1.2 }); o.push(h); y += hgt(h) + G.u * 4;
      var ch = (G.H - y - G.M * 2.2 - G.u * 3 * (F.length - 1)) / F.length;
      F.forEach(function (f, i) { var yy = y + i * (ch + G.u * 3); o.push(card(G.M, yy, G.W - 2 * G.M, ch, '#fff'), R(RTL ? G.W - G.M - G.u * 1.2 : G.M, yy + G.u * 3, G.u * 1.2, ch - G.u * 6, A, { rx: 3, ry: 3 }));
        var q = T(SHORT(f.q, 80), { left: G.M + G.u * 5, width: G.W - 2 * G.M - G.u * 10, top: yy + G.u * 3.5, fontSize: G.u * 3.3, fontWeight: 800, fill: S, maxLines: 2, lineHeight: 1.3, textAlign: RTL ? 'right' : 'left' }); o.push(q);
        o.push(T(SHORT(f.a, 150), { left: G.M + G.u * 5, width: G.W - 2 * G.M - G.u * 10, top: yy + G.u * 4.5 + hgt(q), fontSize: G.u * 2.7, fill: '#64748b', maxLines: 2, lineHeight: 1.4, textAlign: RTL ? 'right' : 'left' })); });
      foot(o, false); return { bg: '#f4f6fb', objs: o }; } },

    /* 9) ندوة / فعالية */
    { n: tl('a9', 'ندوة أو فعالية'), cat: 'event', size: '1080x1350', b: function () { var G = g(), o = [], y = G.M * 1.3;
      o.push(blob(RTL ? 0 : G.W, G.H * 0.15, G.W * 0.45, P, 0.55), blob(RTL ? G.W : 0, G.H * 0.85, G.W * 0.3, A, 0.2));
      y = brow(tl('webinar', 'ندوة عبر الإنترنت'), y, A, o) + G.u * 4;
      var h = txt(HEAD, y, { fontSize: G.u * 8, fontWeight: 900, fill: '#fff', lineHeight: 1.18, maxLines: 3 }); o.push(h); y += hgt(h) + G.u * 3;
      if (SUB) { var s = txt(SUB, y, { fontSize: G.u * 3.2, fill: 'rgba(255,255,255,.75)', maxLines: 3 }); o.push(s); y += hgt(s) + G.u * 5; }
      var bw = G.W - 2 * G.M, bh = G.u * 15; o.push(R(G.M, y, bw, bh, 'rgba(255,255,255,.08)', { rx: G.u * 2.4, ry: G.u * 2.4, stroke: 'rgba(255,255,255,.2)', strokeWidth: 2 }));
      o.push(T('🗓  ' + tl('eventDate', 'الخميس 10 أكتوبر · 7:00 مساءً'), { left: G.M + G.u * 4, width: bw - G.u * 8, top: y + G.u * 2.6, fontSize: G.u * 3.4, fontWeight: 800, fill: '#fff', maxLines: 1, textAlign: RTL ? 'right' : 'left' }), T('📍  ' + tl('eventWhere', 'أونلاين عبر Zoom · الدخول مجاني'), { left: G.M + G.u * 4, width: bw - G.u * 8, top: y + G.u * 8.4, fontSize: G.u * 2.8, fill: 'rgba(255,255,255,.75)', maxLines: 1, textAlign: RTL ? 'right' : 'left' }));
      y += bh + G.u * 5; cta(tl('register', 'سجّل الآن'), Math.min(y, G.H - G.M * 2.9), A, S, o); foot(o, true); return { bg: S, objs: o }; } },

    /* 10) نوظّف */
    { n: tl('a10', 'نحن نوظّف'), cat: 'event', size: '1080x1350', b: function () { var G = g(), o = [], y = G.M * 1.3;
      o.push(R(0, G.H * 0.62, G.W, G.H * 0.38, P), blob(RTL ? G.W * 0.1 : G.W * 0.9, G.H * 0.62, G.W * 0.18, A, 0.9));
      y = brow(CO, y, P, o) + G.u * 3; var h = txt(tl('hiring', 'نحن نوظّف'), y, { fontSize: G.u * 11, fontWeight: 900, fill: S, maxLines: 1 }); o.push(h); y += hgt(h) + G.u * 2;
      var s = txt(tl('joinTeam', 'انضم إلى فريق يصنع الفرق. نبحث عن كفاءات شغوفة بالتميّز.'), y, { fontSize: G.u * 3.4, fill: '#475569', maxLines: 3 }); o.push(s); y += hgt(s) + G.u * 4;
      var roles = SVS.length ? [] : []; [tl('role1', 'مهندس أمن سيبراني'), tl('role2', 'أخصائي مبيعات B2B'), tl('role3', 'مدير مشاريع')].forEach(function (r, i) { var yy = y + i * G.u * 7.5; o.push(circ(RTL ? G.W - G.M - G.u * 1.2 : G.M + G.u * 1.2, yy + G.u * 2.2, G.u * 1.2, A), T(r, { left: RTL ? G.M : G.M + G.u * 4, width: G.W - 2 * G.M - G.u * 4, top: yy, fontSize: G.u * 3.4, fontWeight: 700, fill: S, maxLines: 1, textAlign: RTL ? 'right' : 'left' })); });
      cta(tl('apply', 'قدّم الآن'), G.H * 0.7, '#fff', P, o); foot(o, true); return { bg: '#ffffff', objs: o }; } },

    /* 11) ستوري: صورة كاملة ولوحة نص */
    { n: tl('a11', 'ستوري احترافية'), cat: 'story', size: '1080x1920', b: function () { var G = g(), o = [ph(0, 0, G.W, G.H * 0.6)];
      o.push(R(0, G.H * 0.56, G.W, G.H * 0.44, S, { rx: G.u * 5, ry: G.u * 5 }), R(0, G.H * 0.62, G.W, G.H * 0.38, S));
      var y = G.H * 0.6; y = brow(tl('offer', 'عرض خاص'), y, A, o) + G.u * 3;
      var h = txt(SV.name ? FIRST(SV.name, 50) : HEAD, y, { fontSize: G.u * 8.4, fontWeight: 900, fill: '#fff', lineHeight: 1.18, maxLines: 3 }); o.push(h); y += hgt(h) + G.u * 3;
      if (SUB) { var s = txt(SUB, y, { fontSize: G.u * 3.6, fill: 'rgba(255,255,255,.75)', maxLines: 3 }); o.push(s); y += hgt(s) + G.u * 5; }
      cta(WA ? L.orderWa : tl('learnMore', 'اعرف المزيد'), Math.min(y, G.H - G.M * 3.2), A, S, o); foot(o, true); return { bg: S, objs: o }; } },

    /* 12) غلاف كاروسيل */
    { n: tl('a12', 'غلاف كاروسيل'), cat: 'carousel', size: '1080x1350', b: function () { var G = g(), o = [], y = G.H * 0.2;
      o.push(blob(RTL ? 0 : G.W, 0, G.W * 0.5, '#fff', 0.08), blob(RTL ? G.W : 0, G.H, G.W * 0.4, A, 0.25));
      y = brow(CO, y, A, o) + G.u * 4; var h = txt(HEAD, y, { fontSize: G.u * 9.4, fontWeight: 900, fill: '#fff', lineHeight: 1.15, maxLines: 4 }); o.push(h); y += hgt(h) + G.u * 4;
      if (SUB) o.push(txt(SUB, y, { fontSize: G.u * 3.4, fill: 'rgba(255,255,255,.8)', maxLines: 2 }));
      o.push(T((RTL ? '← ' : '') + tl('swipe', 'اسحب للمزيد') + (RTL ? '' : ' →'), { left: G.M, width: G.W - 2 * G.M, top: G.H - G.M * 1.6, fontSize: G.u * 3, fontWeight: 800, fill: A, textAlign: RTL ? 'left' : 'right', maxLines: 1 }), T('01', { left: G.M, width: G.W - 2 * G.M, top: G.H - G.M * 1.6, fontSize: G.u * 3, fontWeight: 800, fill: 'rgba(255,255,255,.6)', fontFamily: 'Inter', textAlign: RTL ? 'right' : 'left', maxLines: 1 }));
      return { bg: P, objs: o }; } },

    /* 13) شريحة محتوى للكاروسيل */
    { n: tl('a13', 'شريحة كاروسيل'), cat: 'carousel', size: '1080x1350', b: function () { var G = g(), o = [], y = G.M * 1.4, p = POINTS(1)[0] || HEAD;
      o.push(T('02', { left: G.M, width: G.W - 2 * G.M, top: y, fontSize: G.u * 20, fontWeight: 900, fill: P, opacity: 0.14, fontFamily: 'Inter', textAlign: RTL ? 'right' : 'left', maxLines: 1 })); y += G.u * 22;
      var h = txt(p, y, { fontSize: G.u * 6.6, fontWeight: 900, fill: S, lineHeight: 1.22, maxLines: 3 }); o.push(h); y += hgt(h) + G.u * 4;
      o.push(txt(SHORT(K.rtb || K.about || SUB, 220), y, { fontSize: G.u * 3.4, fill: '#475569', lineHeight: 1.6, maxLines: 6 }));
      for (var i = 0; i < 5; i++) o.push(R(G.W / 2 - G.u * 9 + i * G.u * 4, G.H - G.M * 1.2, i === 1 ? G.u * 3 : G.u * 1.4, G.u * 1.2, i === 1 ? P : '#cbd5e1', { rx: 6, ry: 6 }));
      return { bg: '#ffffff', objs: o }; } },

    /* 14) رقم / إنجاز */
    { n: tl('a14', 'رقم وإنجاز'), cat: 'offer', size: '1080x1080', b: function () { var G = g(), o = [], y = G.H * 0.18;
      o.push(R(G.M, G.M, G.W - 2 * G.M, G.H - 2 * G.M, 'rgba(0,0,0,0)', { stroke: 'rgba(255,255,255,.15)', strokeWidth: 2, rx: G.u * 3, ry: G.u * 3 }));
      o.push(T('+250', { left: G.M, width: G.W - 2 * G.M, top: y, fontSize: G.u * 26, fontWeight: 900, fill: A, fontFamily: 'Inter', textAlign: 'center', lineHeight: 1, maxLines: 1 }));
      o.push(T(tl('stat', 'مشروع ناجح لعملاء في القطاعين الحكومي والخاص'), { left: G.M * 1.6, width: G.W - G.M * 3.2, top: y + G.u * 30, fontSize: G.u * 4.6, fontWeight: 800, fill: '#fff', textAlign: 'center', lineHeight: 1.3, maxLines: 3 }));
      foot(o, true); return { bg: S, objs: o }; } },

    /* 15) بانر لينكدإن / رابط عرضي */
    { n: tl('a15', 'بانر لينكدإن'), cat: 'offer', size: '1200x628', b: function () { var G = g(), o = [], half = G.W * 0.52, M = G.H * 0.12;
      o.push(ph(RTL ? 0 : G.W - G.W * 0.44, 0, G.W * 0.44, G.H), R(RTL ? G.W * 0.44 : 0, 0, G.W * 0.56, G.H, S));
      var x = RTL ? G.W * 0.44 + M : M, w = G.W * 0.56 - 2 * M, y = M;
      o.push(T(CO, { left: x, width: w, top: y, fontSize: G.H * 0.045, fontWeight: 700, fill: A, maxLines: 1, textAlign: RTL ? 'right' : 'left' })); y += G.H * 0.11;
      var h = T(SV.name ? FIRST(SV.name, 50) : HEAD, { left: x, width: w, top: y, fontSize: G.H * 0.1, fontWeight: 900, fill: '#fff', lineHeight: 1.18, maxLines: 3, textAlign: RTL ? 'right' : 'left' }); o.push(h); y += hgt(h) + G.H * 0.05;
      var p = pill(tl('learnMore', 'اعرف المزيد'), RTL ? x + w : x, Math.min(y, G.H - M - G.H * 0.1), A, S, G.H * 0.04); if (RTL) p.set({ originX: 'right' }); o.push(p);
      return { bg: S, objs: o }; } },

    /* 16) تهنئة بمناسبة */
    { n: tl('a16', 'تهنئة بمناسبة'), cat: 'season', size: '1080x1080', b: function () { var G = g(), o = [];
      o.push(circ(G.W / 2, G.H * 0.42, G.W * 0.34, P, { opacity: 0.12 }), circ(G.W / 2, G.H * 0.42, G.W * 0.26, P, { opacity: 0.12 }), T('✦', { left: 0, width: G.W, top: G.H * 0.18, fontSize: G.u * 10, fill: A, textAlign: 'center', fontFamily: 'Inter', maxLines: 1 }));
      o.push(T(tl('greet', 'كل عام وأنتم بخير'), { left: G.M, width: G.W - 2 * G.M, top: G.H * 0.34, fontSize: G.u * 8.4, fontWeight: 900, fill: S, textAlign: 'center', maxLines: 2, lineHeight: 1.2 }), T(tl('greetSub', 'نتمنى لكم ولعائلاتكم أيامًا مليئة بالخير والنجاح'), { left: G.M * 1.5, width: G.W - G.M * 3, top: G.H * 0.56, fontSize: G.u * 3.4, fill: '#64748b', textAlign: 'center', maxLines: 2 }));
      foot(o, false); return { bg: '#fffdf8', objs: o }; } },
    /* 17) نصف صورة ونصف لون (قطري) */
    { n: tl('a17', 'إعلان بصورة'), cat: 'offer', size: '1080x1350', b: function () { var G = g(), o = [ph(0, 0, G.W, G.H * 0.55)];
      o.push(new fabric.Polygon([{ x: 0, y: G.H * 0.47 }, { x: G.W, y: G.H * 0.4 }, { x: G.W, y: G.H }, { x: 0, y: G.H }], { fill: P }));
      var y = G.H * 0.52; y = brow(tl('offer', 'عرض خاص'), y, A, o) + G.u * 3; var h = txt(SV.name ? FIRST(SV.name, 50) : HEAD, y, { fontSize: G.u * 7.6, fontWeight: 900, fill: '#fff', lineHeight: 1.18, maxLines: 3 }); o.push(h); y += hgt(h) + G.u * 3;
      if (SUB) { var s = txt(SUB, y, { fontSize: G.u * 3.2, fill: 'rgba(255,255,255,.8)', maxLines: 2 }); o.push(s); y += hgt(s) + G.u * 4; }
      cta(WA ? L.orderWa : tl('learnMore', 'اعرف المزيد'), Math.min(y, G.H - G.M * 2.9), A, S, o); foot(o, true); return { bg: P, objs: o }; } },

    /* 18) مزايا المنتج بعلامات صح */
    { n: tl('a18', 'مزايا الخدمة'), cat: 'product', size: '1080x1080', b: function () { var G = g(), o = [], r = G.W * 0.2, cx = RTL ? G.M + r : G.W - G.M - r;
      o.push(circ(cx, G.H * 0.44, r * 1.18, P, { opacity: 0.1 }), ph(cx - r, G.H * 0.44 - r, r * 2, r * 2, { circle: true }));
      var tw = G.W - 2 * G.M - r * 2.6, tx = RTL ? G.W - G.M - tw : G.M, y = G.M * 1.3; o.push(R(RTL ? G.W - G.M - G.u * 6 : G.M, y + G.u * 1.4, G.u * 6, G.u * 0.6, A), T(tl('features', 'لماذا هذه الخدمة؟'), { left: tx, width: tw - G.u * 8, top: y, fontSize: G.u * 2.6, fontWeight: 700, fill: P, textAlign: RTL ? 'right' : 'left', maxLines: 1 })); y += G.u * 6;
      var h = T(FIRST(SV.name || HEAD, 50), { left: tx, width: tw, top: y, fontSize: G.u * 6, fontWeight: 900, fill: S, lineHeight: 1.2, maxLines: 3, textAlign: RTL ? 'right' : 'left' }); o.push(h); y += hgt(h) + G.u * 5;
      POINTS(3).forEach(function (pt) { o.push(circ(RTL ? G.W - G.M - G.u * 2 : G.M + G.u * 2, y + G.u * 2, G.u * 2, A), T('✓', { left: RTL ? G.W - G.M - G.u * 4 : G.M, width: G.u * 4, top: y + G.u * 0.2, fontSize: G.u * 2.6, fontWeight: 900, fill: S, textAlign: 'center', fontFamily: 'Inter', maxLines: 1 }));
        var t = T(pt, { left: RTL ? tx : tx + G.u * 6, width: tw - G.u * 6, top: y, fontSize: G.u * 2.9, fontWeight: 600, fill: '#334155', lineHeight: 1.35, maxLines: 2, textAlign: RTL ? 'right' : 'left' }); o.push(t); y += Math.max(hgt(t), G.u * 4) + G.u * 3; });
      foot(o, false); return { bg: '#ffffff', objs: o }; } },

    /* 19) خطوات العمل 1-2-3 */
    { n: tl('a19', 'خطوات العمل'), cat: 'edu', size: '1080x1080', b: function () { var G = g(), o = [], y = G.M * 1.2;
      y = brow(CO, y, A, o) + G.u * 2.5; var h = txt(tl('steps', 'كيف نعمل معك؟'), y, { fontSize: G.u * 7, fontWeight: 900, fill: '#fff', maxLines: 2 }); o.push(h); y += hgt(h) + G.u * 7;
      var st = [tl('st1', 'نفهم احتياجك'), tl('st2', 'نصمم الحل'), tl('st3', 'ننفذ ونتابع')], cw = (G.W - 2 * G.M - G.u * 6) / 3;
      o.push(R(G.M + cw / 2, y + G.u * 6, G.W - 2 * G.M - cw, 3, 'rgba(255,255,255,.25)'));
      st.forEach(function (t, i) { var k = RTL ? 2 - i : i, x = G.M + k * (cw + G.u * 3), cx = x + cw / 2; num(i + 1, cx, y + G.u * 6, G.u * 6, i === 0 ? A : '#fff', S, o);
        o.push(T(t, { left: x, width: cw, top: y + G.u * 15, fontSize: G.u * 3.4, fontWeight: 800, fill: '#fff', textAlign: 'center', maxLines: 2, lineHeight: 1.3 })); });
      cta(WA ? L.orderWa : tl('contact', 'تواصل معنا'), G.H - G.M * 2.9, A, S, o); foot(o, true); return { bg: S, objs: o }; } },

    /* 20) كلمة المدير */
    { n: tl('a20', 'كلمة القيادة'), cat: 'quote', size: '1080x1350', b: function () { var G = g(), o = [], r = G.W * 0.15;
      o.push(R(0, 0, G.W, G.H * 0.36, P), blob(RTL ? 0 : G.W, 0, G.W * 0.35, '#fff', 0.08), circ(G.W / 2, G.H * 0.36, r + G.u * 1.6, '#fff'), ph(G.W / 2 - r, G.H * 0.36 - r, r * 2, r * 2, { circle: true }));
      o.push(T('“', { left: 0, width: G.W, top: G.H * 0.47, fontSize: G.u * 14, fill: A, textAlign: 'center', fontFamily: 'Playfair Display', lineHeight: 1, maxLines: 1 }));
      var q = T(FIRST(K.pod || K.about || L.quote, 170), { left: G.M * 1.3, width: G.W - G.M * 2.6, top: G.H * 0.56, fontSize: G.u * 4.4, fontWeight: 700, fill: S, textAlign: 'center', lineHeight: 1.5, maxLines: 4 }); o.push(q);
      o.push(T(tl('ceo', 'الرئيس التنفيذي'), { left: G.M, width: G.W - 2 * G.M, top: G.H * 0.56 + hgt(q) + G.u * 4, fontSize: G.u * 2.8, fontWeight: 800, fill: P, textAlign: 'center', maxLines: 1 }));
      foot(o, false); return { bg: '#ffffff', objs: o }; } },

    /* 21) عدّ تنازلي */
    { n: tl('a21', 'عدّ تنازلي'), cat: 'event', size: '1080x1080', b: function () { var G = g(), o = [], y = G.H * 0.16;
      y = brow(tl('soon', 'قريبًا'), y, A, o) + G.u * 3; var h = txt(HEAD, y, { fontSize: G.u * 6.6, fontWeight: 900, fill: '#fff', maxLines: 2, lineHeight: 1.2 }); o.push(h); y += hgt(h) + G.u * 6;
      var lab = [tl('days', 'يوم'), tl('hours', 'ساعة'), tl('mins', 'دقيقة')], val = ['07', '12', '30'], bw = (G.W - 2 * G.M - G.u * 6) / 3;
      lab.forEach(function (l, i) { var k = RTL ? 2 - i : i, x = G.M + k * (bw + G.u * 3); o.push(R(x, y, bw, G.u * 26, 'rgba(255,255,255,.07)', { rx: G.u * 2.4, ry: G.u * 2.4, stroke: 'rgba(255,255,255,.15)', strokeWidth: 2 }), T(val[i], { left: x, width: bw, top: y + G.u * 3, fontSize: G.u * 12, fontWeight: 900, fill: i === 0 ? A : '#fff', fontFamily: 'Inter', textAlign: 'center', maxLines: 1 }), T(l, { left: x, width: bw, top: y + G.u * 18, fontSize: G.u * 3, fill: 'rgba(255,255,255,.7)', textAlign: 'center', maxLines: 1 })); });
      foot(o, true); return { bg: S, objs: o }; } },

    /* 22) إعلان ستوري بعنوان كبير */
    { n: tl('a22', 'إعلان ستوري'), cat: 'story', size: '1080x1920', b: function () { var G = g(), o = [];
      o.push(blob(RTL ? 0 : G.W, G.H * 0.1, G.W * 0.55, '#fff', 0.08), blob(RTL ? G.W : 0, G.H * 0.92, G.W * 0.5, A, 0.25)); dots(RTL ? G.W - G.M - G.u * 16 : G.M, G.H * 0.12, 5, 5, G.u * 4, '#fff', 0.3, o);
      var y = G.H * 0.34; y = brow(tl('news', 'جديدنا'), y, A, o) + G.u * 4; var h = txt(HEAD, y, { fontSize: G.u * 11, fontWeight: 900, fill: '#fff', lineHeight: 1.15, maxLines: 4 }); o.push(h); y += hgt(h) + G.u * 5;
      if (SUB) { var s = txt(SUB, y, { fontSize: G.u * 4, fill: 'rgba(255,255,255,.8)', maxLines: 3 }); o.push(s); y += hgt(s) + G.u * 6; }
      cta(tl('learnMore', 'اعرف المزيد'), Math.min(y, G.H - G.M * 3.4), '#fff', P, o); foot(o, true); return { bg: P, objs: o }; } },

    /* 23) مناسبة: رمضان / عيد بقوس وهلال */
    { n: tl('a23', 'رمضان والعيد'), cat: 'season', size: '1080x1350', b: function () { var G = g(), o = [], aw = G.W * 0.64, ax = (G.W - aw) / 2, ay = G.H * 0.14;
      o.push(new fabric.Path('M 0 ' + aw * 0.5 + ' A ' + aw / 2 + ' ' + aw / 2 + ' 0 0 1 ' + aw + ' ' + aw * 0.5 + ' L ' + aw + ' ' + G.H * 0.62 + ' L 0 ' + G.H * 0.62 + ' Z', { left: ax, top: ay, fill: 'rgba(255,255,255,.06)', stroke: A, strokeWidth: 3 }));
      o.push(circ(G.W / 2, ay + G.u * 18, G.u * 8, A), circ(G.W / 2 + G.u * 3.4, ay + G.u * 15.5, G.u * 7.2, S));
      dots(G.M, G.M, 3, 3, G.u * 3, A, 0.5, o); dots(G.W - G.M - G.u * 6, G.M, 3, 3, G.u * 3, A, 0.5, o);
      o.push(T(tl('ramadan', 'رمضان كريم'), { left: ax, width: aw, top: ay + G.u * 32, fontSize: G.u * 9, fontWeight: 900, fill: '#fff', textAlign: 'center', maxLines: 2, lineHeight: 1.2 }), T(tl('ramadanSub', 'أعاده الله علينا وعليكم بالخير واليُمن والبركات'), { left: ax + G.u * 4, width: aw - G.u * 8, top: ay + G.u * 52, fontSize: G.u * 3.2, fill: 'rgba(255,255,255,.8)', textAlign: 'center', maxLines: 3, lineHeight: 1.5 }));
      foot(o, true); return { bg: S, objs: o }; } },

    /* 24) إعلان بسيط أنيق (أبيض) */
    { n: tl('a24', 'إعلان أنيق'), cat: 'offer', size: '1080x1080', b: function () { var G = g(), o = [], y = G.H * 0.26;
      o.push(R(G.M * 0.6, G.M * 0.6, G.W - G.M * 1.2, G.H - G.M * 1.2, 'rgba(0,0,0,0)', { stroke: S, strokeWidth: 3 }), R(RTL ? G.W - G.M * 0.6 - G.u * 18 : G.M * 0.6, G.M * 0.6, G.u * 18, G.u * 1.4, P));
      y = brow(tl('announce', 'إعلان'), y, P, o) + G.u * 4; var h = txt(HEAD, y, { fontSize: G.u * 8.4, fontWeight: 900, fill: S, lineHeight: 1.18, maxLines: 3 }); o.push(h); y += hgt(h) + G.u * 4;
      if (SUB) { var s = txt(SUB, y, { fontSize: G.u * 3.3, fill: '#64748b', maxLines: 3 }); o.push(s); y += hgt(s) + G.u * 5; }
      cta(tl('learnMore', 'اعرف المزيد'), Math.min(y, G.H - G.M * 2.9), S, '#fff', o); foot(o, false); return { bg: '#ffffff', objs: o }; } },

    /* 25) المشكلة والحل */
    { n: tl('a25', 'المشكلة والحل'), cat: 'edu', size: '1080x1350', b: function () { var G = g(), o = [], y = G.M * 1.2, cw = G.W - 2 * G.M;
      y = brow(CO, y, P, o) + G.u * 2.5; var h = txt(tl('psH', 'من التحدي إلى النتيجة'), y, { fontSize: G.u * 6.6, fontWeight: 900, fill: S, maxLines: 2 }); o.push(h); y += hgt(h) + G.u * 5;
      var ch = (G.H - y - G.M * 2.4 - G.u * 6) / 2;
      [[tl('problem', 'التحدي'), tl('problemT', 'أنظمة متفرقة، ومخاطر غير مرئية، وقرارات بلا بيانات واضحة.'), '#fff', '#ef4444', S], [tl('solution', 'الحل'), FIRST(SV.details || K.pod || SUB || tl('solutionT', 'منصة موحّدة تمنحك رؤية كاملة وتحكمًا فوريًا.'), 130), P, A, '#fff']].forEach(function (c, i) { var yy = y + i * (ch + G.u * 6);
        o.push(card(G.M, yy, cw, ch, c[2])); o.push(R(RTL ? G.W - G.M - G.u * 5 - G.u * 14 : G.M + G.u * 5, yy + G.u * 5, G.u * 14, G.u * 5, c[3], { rx: G.u * 2.5, ry: G.u * 2.5 }), T(c[0], { left: RTL ? G.W - G.M - G.u * 5 - G.u * 14 : G.M + G.u * 5, width: G.u * 14, top: yy + G.u * 5.8, fontSize: G.u * 2.5, fontWeight: 800, fill: '#fff', textAlign: 'center', maxLines: 1 }));
        o.push(T(c[1], { left: G.M + G.u * 5, width: cw - G.u * 10, top: yy + G.u * 14, fontSize: G.u * 4, fontWeight: 800, fill: c[4], lineHeight: 1.4, maxLines: 3, textAlign: RTL ? 'right' : 'left' })); if (i === 0) o.push(T('↓', { left: 0, width: G.W, top: yy + ch + G.u * 0.5, fontSize: G.u * 5, fill: P, textAlign: 'center', fontFamily: 'Inter', maxLines: 1 })); });
      foot(o, false); return { bg: '#f4f6fb', objs: o }; } },

    /* 26) الشريحة الأخيرة في الكاروسيل */
    { n: tl('a26', 'ختام كاروسيل'), cat: 'carousel', size: '1080x1350', b: function () { var G = g(), o = [], y = G.H * 0.3;
      o.push(blob(G.W / 2, G.H * 0.3, G.W * 0.42, P, 0.08)); o.push(T(tl('likedIt', 'أعجبك المحتوى؟'), { left: G.M, width: G.W - 2 * G.M, top: y, fontSize: G.u * 8, fontWeight: 900, fill: S, textAlign: 'center', maxLines: 2 }));
      o.push(T(tl('follow', 'تابعنا لمزيد من الحلول والنصائح، وشاركه مع فريقك.'), { left: G.M * 1.5, width: G.W - G.M * 3, top: y + G.u * 13, fontSize: G.u * 3.4, fill: '#64748b', textAlign: 'center', maxLines: 2 }));
      ['♡', '💬', '↗', '🔖'].forEach(function (ic, i) { var cx = G.W / 2 + (i - 1.5) * G.u * 14; o.push(circ(cx, y + G.u * 30, G.u * 5, i === 0 ? P : '#eef2ff'), T(ic, { left: cx - G.u * 5, width: G.u * 10, top: y + G.u * 26.8, fontSize: G.u * 4, fill: i === 0 ? '#fff' : P, textAlign: 'center', fontFamily: 'Inter', maxLines: 1 })); });
      var p = pill(HANDLE || CO, G.W / 2, y + G.u * 44, S, '#fff', G.u * 3, true); o.push(p);
      return { bg: '#ffffff', objs: o }; } },
  ];
  /* ---------- نسخ من كل قالب: ألوان الهوية مقلوبة، فاتح/داكن، ومقاس تاني (أكتر من 100 قالب بنفس الجودة) ---------- */
  function lc(c) { return typeof c === 'string' ? c.toLowerCase().replace(/\s+/g, '') : c; }
  function recolor(map) { return function (r) { var f = function (c) { if (typeof c !== 'string') return c; var k = lc(c); if (map[k] !== undefined) return map[k];
        var m = /^rgba\((255,255,255|15,23,42),([\d.]+)\)$/.exec(k); if (m && map['rgba:' + m[1]]) return 'rgba(' + map['rgba:' + m[1]] + ',' + m[2] + ')'; return c; };
      var walk = function (o) { ['fill', 'stroke'].forEach(function (p) { if (typeof o[p] === 'string') o.set(p, f(o[p])); }); if (o.type === 'group' && o.dzRole !== 'ph') o.getObjects().forEach(walk); };
      r.objs.forEach(walk); if (typeof r.bg === 'string') r.bg = f(r.bg); return r; }; }
  var SW = {}; SW[lc(P)] = S; SW[lc(S)] = P;
  var INV = { '#ffffff': S, '#fff': S, '#f4f6fb': '#0b1220', '#fffdf8': S, '#475569': 'rgba(255,255,255,.75)', '#64748b': 'rgba(255,255,255,.65)', '#334155': 'rgba(255,255,255,.85)', '#cbd5e1': 'rgba(255,255,255,.25)', 'rgba:255,255,255': '15,23,42', 'rgba:15,23,42': '255,255,255' }; INV[lc(S)] = '#ffffff';
  var NEXT = { '1080x1080': '1080x1350', '1080x1350': '1080x1920', '1080x1920': '1080x1350', '1200x628': '1200x628' };
  var VAR = [];
  TPLS = TPLS.map(function (t) { var b0 = t.b; return Object.assign({}, t, { b: function () { refresh(); return b0(); } }); });
  TPLS.forEach(function (base) { var bg0 = ''; try { bg0 = lc(base.b().bg); } catch (e) {} var vs = [['swap', tl('vSwap', 'ألوان معكوسة')], [bg0 === lc(P) ? 'both' : 'inv', tl('vInv', 'فاتح / داكن')], [base.size === '1200x628' ? 'both' : 'size', base.size === '1200x628' ? tl('vAlt', 'نسخة بديلة') : (NEXT[base.size] === '1080x1920' ? tl('vStory', 'ستوري') : tl('vTall', 'طولي'))]];
    vs.forEach(function (v) { var fx = v[0] === 'swap' ? recolor(SW) : v[0] === 'inv' ? recolor(INV) : v[0] === 'both' ? function (r) { return recolor(INV)(recolor(SW)(r)); } : function (r) { return r; };
      VAR.push({ n: base.n + ' · ' + v[1], cat: base.cat, size: v[0] === 'size' ? NEXT[base.size] : base.size, b: function () { return fx(base.b()); } }); }); });
  TPLS = TPLS.concat(VAR);
  X.TPL.length = 0; TPLS.forEach(function (t) { X.TPL.push(t); }); if (document.querySelector('#dz-tpls')) X.thumbs();
})();
