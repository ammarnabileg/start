/* المصمم (زي كانفا) مبني على Fabric.js: مقاسات، قوالب بألوان الشركة، نص/أشكال/صور، خصائص، طبقات، تراجع، حفظ تلقائي، تصدير وحفظ في المكتبة */
(function () {
  var C = window.DZ; if (!C || !window.fabric) return; var L = C.L;
  var KEY = 'dz_draft_' + (location.host || ''), W = 1080, H = 1080, zoom = 1, hist = [], fut = [], lock = false, saveT = null;
  var B = C.brand.concat(['#0f172a', '#ffffff']), P = B[0], S = B[1] || '#0f172a', A = B[2] || '#f59e0b';
  var cv = new fabric.Canvas('dz-c', { preserveObjectStacking: true, backgroundColor: '#ffffff', selectionColor: 'rgba(37,99,235,.08)', selectionBorderColor: '#2563eb' });
  fabric.Object.prototype.set({ transparentCorners: false, cornerColor: '#fff', cornerStrokeColor: '#2563eb', borderColor: '#2563eb', cornerSize: 11, cornerStyle: 'circle', padding: 4 });
  var $ = function (id) { return document.getElementById(id); }, stage = $('dz-stage');
  function isAr(t) { return /[؀-ۿ]/.test(t || ''); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }

  /* ---------- المقاس والتكبير ---------- */
  function fit() { var r = stage.getBoundingClientRect(); zoom = Math.max(0.05, Math.min((r.width - 64) / W, (r.height - 64) / H, 2)); apply(); }
  function apply() { cv.setZoom(zoom); cv.setDimensions({ width: W * zoom, height: H * zoom }); $('dz-zoom').textContent = Math.round(zoom * 100) + '%'; }
  function setSize(v, keep) { var p = v.split('x'); var ow = W, oh = H; W = +p[0]; H = +p[1]; $('dz-size').value = v;
    if (keep && (ow !== W || oh !== H)) cv.getObjects().forEach(function (o) { o.set({ left: o.left * W / ow, top: o.top * H / oh }); o.setCoords(); });
    fit(); cv.requestRenderAll(); }
  window.addEventListener('resize', fit);
  $('dz-size').addEventListener('change', function () { setSize(this.value, true); snap(); });

  /* ---------- التاريخ والحفظ التلقائي ---------- */
  function json() { return JSON.stringify({ w: W, h: H, name: $('dz-name').value, c: cv.toJSON(['name', 'lockMovementX', 'lockMovementY', 'selectable', 'evented', 'dzRole', 'dzCircle', 'dzRx', 'dzF', 'dzFill']) }); }
  function snap() { if (lock) return; hist.push(json()); if (hist.length > 60) hist.shift(); fut = []; layers(); clearTimeout(saveT); saveT = setTimeout(function () { try { localStorage.setItem(KEY, hist[hist.length - 1]); $('dz-saved').textContent = '✓ ' + L.draft; } catch (e) {} }, 600); }
  function load(s, cb) { var d = JSON.parse(s); lock = true; W = d.w; H = d.h; $('dz-size').value = W + 'x' + H; if (d.name) $('dz-name').value = d.name;
    cv.loadFromJSON(d.c, function () { fit(); cv.requestRenderAll(); lock = false; layers(); if (cb) cb(); }); }
  function undo() { if (hist.length < 2) return; fut.push(hist.pop()); load(hist[hist.length - 1]); }
  function redo() { if (!fut.length) return; var s = fut.pop(); hist.push(s); load(s); }
  cv.on('object:modified', snap); cv.on('object:added', function () { if (!lock) snap(); }); cv.on('object:removed', function () { if (!lock) snap(); });
  cv.on('text:changed', function () { clearTimeout(saveT); saveT = setTimeout(snap, 500); });

  /* ---------- إضافة عناصر ---------- */
  function center(o) { cv.add(o); if (o.left == null || o.dzCenter) { o.center(); } o.setCoords(); cv.setActiveObject(o); cv.requestRenderAll(); return o; }
  function text(t, o) { var ar = isAr(t); return new fabric.Textbox(t, Object.assign({ width: W * 0.8, fontFamily: ar ? 'Cairo' : 'Inter', fontSize: 64, fill: S, textAlign: ar ? 'right' : 'left', direction: ar ? 'rtl' : 'ltr', lineHeight: 1.2, splitByGrapheme: false }, o || {})); }
  var ADD = {
    h1: function () { return text(L.h1, { fontSize: Math.round(W * 0.09), fontWeight: 900, dzCenter: 1, textAlign: 'center' }); },
    h2: function () { return text(L.h2, { fontSize: Math.round(W * 0.055), fontWeight: 700, dzCenter: 1, textAlign: 'center' }); },
    p: function () { return text(L.p, { fontSize: Math.round(W * 0.035), dzCenter: 1, textAlign: 'center', width: W * 0.7 }); },
    badge: function () { return group([new fabric.Circle({ radius: W * 0.11, fill: '#dc2626', originX: 'center', originY: 'center' }), text('-30%', { fontSize: W * 0.07, fontWeight: 900, fill: '#fff', originX: 'center', originY: 'center', width: W * 0.2, textAlign: 'center', fontFamily: 'Inter' })]); },
    cta: function () { var t = text(L.cta, { fontSize: W * 0.04, fontWeight: 700, fill: '#fff', originX: 'center', originY: 'center', textAlign: 'center', width: W * 0.4 }); return group([new fabric.Rect({ width: W * 0.42, height: W * 0.1, rx: W * 0.025, ry: W * 0.025, fill: S, originX: 'center', originY: 'center' }), t]); },
    quote: function () { return group([text('“', { fontSize: W * 0.2, fill: P, fontFamily: 'Playfair Display', originX: 'center', top: -W * 0.12, textAlign: 'center', width: W * 0.2 }), text(L.quote, { fontSize: W * 0.045, fontWeight: 700, originX: 'center', textAlign: 'center', width: W * 0.75 }), text(L.quoteBy, { fontSize: W * 0.03, fill: '#64748b', originX: 'center', top: W * 0.13, textAlign: 'center', width: W * 0.6 })]); },
    brandname: function () { return text(C.company || L.company, { fontSize: W * 0.05, fontWeight: 900, fill: P, dzCenter: 1, textAlign: 'center' }); },
    tagline: function () { return text(C.tagline, { fontSize: W * 0.032, fill: S, dzCenter: 1, textAlign: 'center' }); },
    rect: function () { return new fabric.Rect({ width: W * 0.4, height: W * 0.25, fill: P, dzCenter: 1 }); },
    round: function () { return new fabric.Rect({ width: W * 0.4, height: W * 0.25, rx: W * 0.04, ry: W * 0.04, fill: P, dzCenter: 1 }); },
    circle: function () { return new fabric.Circle({ radius: W * 0.15, fill: A, dzCenter: 1 }); },
    tri: function () { return new fabric.Triangle({ width: W * 0.3, height: W * 0.26, fill: P, dzCenter: 1 }); },
    line: function () { return new fabric.Line([0, 0, W * 0.5, 0], { stroke: S, strokeWidth: 8, dzCenter: 1 }); },
    star: function () { var pts = []; for (var i = 0; i < 10; i++) { var r = i % 2 ? W * 0.06 : W * 0.14, a = Math.PI / 5 * i - Math.PI / 2; pts.push({ x: r * Math.cos(a), y: r * Math.sin(a) }); } return new fabric.Polygon(pts, { fill: A, dzCenter: 1 }); },
    arrow: function () { return new fabric.Path('M0 20 H120 M95 0 L125 20 L95 40', { stroke: S, strokeWidth: 10, fill: '', strokeLineCap: 'round', strokeLineJoin: 'round', scaleX: W / 700, scaleY: W / 700, dzCenter: 1 }); },
    blob: function () { return new fabric.Path('M60 70c30-40 100-30 120 10s10 90-40 100-110 0-110-50 10-30 30-60z', { fill: P, opacity: 0.9, scaleX: W / 400, scaleY: W / 400, dzCenter: 1 }); },
    frame: function () { return new fabric.Rect({ width: W * 0.9, height: H * 0.9, fill: '', stroke: P, strokeWidth: W * 0.012, rx: W * 0.02, ry: W * 0.02, dzCenter: 1 }); }
  };
  function group(objs) { var g = new fabric.Group(objs, { dzCenter: 1 }); return g; }
  document.querySelectorAll('[data-add]').forEach(function (b) { b.addEventListener('click', function () { var o = ADD[b.dataset.add](); center(o); }); });

  /* ---------- الصور ---------- */
  function addImg(url) { fabric.Image.fromURL(url, function (im) { if (!im || !im.width) return toast(L.err, 'error'); var k = Math.min(W * 0.8 / im.width, H * 0.8 / im.height, 1); im.set({ scaleX: k, scaleY: k }); center(im); }, { crossOrigin: 'anonymous' }); }
  function addFile(f) { if (!/^image\//.test(f.type)) return; var o = cv.getActiveObject(), r = new FileReader(); r.onload = function () { if (o && o.dzRole === 'ph') fillPh(o, r.result); else addImg(r.result); }; r.readAsDataURL(f); }
  $('dz-up').addEventListener('change', function () { [].forEach.call(this.files, addFile); this.value = ''; });
  ['dragenter', 'dragover'].forEach(function (ev) { stage.addEventListener(ev, function (e) { e.preventDefault(); $('dz-drop').classList.remove('hidden'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { stage.addEventListener(ev, function (e) { e.preventDefault(); $('dz-drop').classList.add('hidden'); if (ev === 'drop') [].forEach.call(e.dataTransfer.files || [], addFile); }); });
  document.addEventListener('paste', function (e) { if (/INPUT|TEXTAREA/.test(document.activeElement.tagName) || (cv.getActiveObject() && cv.getActiveObject().isEditing)) return; [].forEach.call((e.clipboardData || {}).files || [], addFile); });

  /* ---------- الخلفية والألوان ---------- */
  var SW = B.concat(['#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4', '#8b5cf6', '#ec4899', '#f8fafc', '#e2e8f0', '#64748b', '#000000']).filter(function (v, i, a) { return a.indexOf(v) === i; }).slice(0, 18);
  var GR = [[P, A], ['#0f172a', '#334155'], ['#f97316', '#ec4899'], ['#06b6d4', '#2563eb'], ['#22c55e', '#0d9488'], ['#fde68a', '#f59e0b'], ['#e0e7ff', '#fce7f3'], ['#111827', P]];
  $('dz-bgc').innerHTML = SW.map(function (c) { return '<button type="button" class="dz-sw" data-bg="' + c + '" style="background:' + c + '"></button>'; }).join('');
  $('dz-bgg').innerHTML = GR.map(function (g, i) { return '<button type="button" class="dz-sw !h-12" data-gr="' + i + '" style="background:linear-gradient(135deg,' + g[0] + ',' + g[1] + ')"></button>'; }).join('');
  $('dz-brand').innerHTML = B.slice(0, -2).map(function (c) { return '<button type="button" class="dz-sw !w-10 !h-10" data-col="' + c + '" style="background:' + c + '" title="' + c + '"></button>'; }).join('');
  $('pp-sw').innerHTML = SW.slice(0, 10).map(function (c) { return '<button type="button" class="dz-sw !w-5 !h-5" data-col="' + c + '" style="background:' + c + '"></button>'; }).join('');
  function grad(g) { return new fabric.Gradient({ type: 'linear', gradientUnits: 'pixels', coords: { x1: 0, y1: 0, x2: W, y2: H }, colorStops: [{ offset: 0, color: g[0] }, { offset: 1, color: g[1] }] }); }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-bg]'); if (b) { cv.setBackgroundColor(b.dataset.bg, function () { cv.requestRenderAll(); snap(); }); return; }
    b = e.target.closest('[data-gr]'); if (b) { cv.setBackgroundColor(grad(GR[+b.dataset.gr]), function () { cv.requestRenderAll(); snap(); }); return; }
    b = e.target.closest('[data-col]'); if (b) { var o = cv.getActiveObject(); if (!o) return; setFill(o, b.dataset.col); return; }
  });
  $('dz-bgx').addEventListener('input', function () { cv.setBackgroundColor(this.value, function () { cv.requestRenderAll(); }); }); $('dz-bgx').addEventListener('change', snap);
  function setFill(o, c) { if (o.type === 'group') o.getObjects().forEach(function (x) { if (x.type !== 'textbox') x.set('fill', c); }); else if (o.type === 'line' || (o.type === 'path' && !o.fill)) o.set('stroke', c); else o.set('fill', c); cv.requestRenderAll(); snap(); sync(); }

  /* ---------- القوالب الاحترافية: كل قالب بيرجع {size, bg, objs} بألوان الشركة ونصوصها، والمعاينة بتترسم فعلًا ---------- */
  var K = C.kb || {}, SVS = (K.svcs || []).filter(function (s) { return s.name; }), SV = SVS[0] || {}, SV2 = SVS[1] || SV, CO = K.name || L.company, FAQ = K.faq || [];
  var LTR = function (x) { return x ? '\u2066' + x + '\u2069' : ''; }; // عزل اتجاه الأرقام واليوزر عشان @ وعلامة + يفضلوا في مكانهم
  var WA = LTR(K.whatsapp || K.phone || ''), HANDLE = K.handle ? LTR('@' + K.handle) : '', FOOT = [HANDLE, WA].filter(Boolean).join('   ·   ');
  var OFFER = K.focus || (SV.name ? SV.name + (SV.price ? ' — ' + SV.price : '') : L.off);
  function ph(x, y, w, h, o) { // مكان صورة: يتبدل بصورة حقيقية ويتقص على نفس الشكل
    var shape = (o && o.circle) ? new fabric.Circle({ radius: w / 2, originX: 'center', originY: 'center' }) : new fabric.Rect({ width: w, height: h, rx: (o && o.rx) || 0, ry: (o && o.rx) || 0, originX: 'center', originY: 'center' });
    shape.set({ fill: new fabric.Gradient({ type: 'linear', coords: { x1: -w / 2, y1: -h / 2, x2: w / 2, y2: h / 2 }, colorStops: [{ offset: 0, color: '#e2e8f0' }, { offset: 1, color: '#cbd5e1' }] }) });
    var t = new fabric.Textbox('📷  ' + L.ph, { fontSize: Math.max(18, w * 0.06), fill: '#64748b', fontFamily: 'Cairo', width: w * 0.8, textAlign: 'center', originX: 'center', originY: 'center' });
    return new fabric.Group([shape, t], { left: x, top: y, dzRole: 'ph', dzCircle: !!(o && o.circle), dzRx: (o && o.rx) || 0 });
  }
  function T(t, o) { var ar = isAr(t); return new fabric.Textbox(t, Object.assign({ fontFamily: ar ? 'Cairo' : 'Inter', fontSize: W * 0.05, fill: S, width: W * 0.84, left: W * 0.08, textAlign: ar ? 'right' : 'left', direction: ar ? 'rtl' : 'ltr', lineHeight: 1.15 }, o || {})); }
  function TC(t, o) { return T(t, Object.assign({ textAlign: 'center' }, o || {})); }
  function pill(t, x, y, bg, fg, fs, mid) { var tt = new fabric.Textbox(t, { fontSize: fs, fontWeight: 800, fill: fg, fontFamily: isAr(t) ? 'Cairo' : 'Inter', textAlign: 'center', originX: 'center', originY: 'center', width: fs * Math.max(4, t.length * 0.62) }); var r = new fabric.Rect({ width: tt.width + fs * 1.6, height: fs * 2.1, rx: fs * 1.05, ry: fs * 1.05, fill: bg, originX: 'center', originY: 'center' }); return new fabric.Group([r, tt], { left: x, top: y, originX: mid ? 'center' : 'left' }); }
  function R(x, y, w, h, fill, o) { return new fabric.Rect(Object.assign({ left: x, top: y, width: w, height: h, fill: fill }, o || {})); }
  function circ(x, y, r, fill, o) { return new fabric.Circle(Object.assign({ left: x, top: y, radius: r, fill: fill, originX: 'center', originY: 'center' }, o || {})); }
  var light = 'rgba(255,255,255,.82)';
  var TPL = [
    { n: L.t1, cat: 'offer', size: '1080x1080', b: function () { return { bg: S, objs: [
      new fabric.Polygon([{ x: 0, y: 0 }, { x: W, y: 0 }, { x: W, y: H * 0.42 }, { x: 0, y: H * 0.62 }], { fill: P }),
      circ(W * 0.84, H * 0.22, W * 0.14, A), TC(SV.price || '%', { left: W * 0.7, width: W * 0.28, top: H * 0.18, fontSize: W * 0.06, fontWeight: 900, fill: S }),
      T(L.offer, { top: H * 0.12, fontSize: W * 0.045, fill: light, fontWeight: 700, width: W * 0.6 }),
      T(C.title || K.focus || SV.name || L.h1, { top: H * 0.2, fontSize: W * 0.085, fill: '#fff', fontWeight: 900, width: W * 0.6 }),
      T(K.tagline || L.limited, { top: H * 0.66, fontSize: W * 0.036, fill: light }), pill(WA ? L.orderWa : L.cta, W * 0.08, H * 0.78, A, S, W * 0.032),
      T(CO + (FOOT ? '   ·   ' + FOOT : ''), { top: H * 0.92, fontSize: W * 0.026, fill: light, fontWeight: 700 })] }; } },
    { n: L.t2, cat: 'product', size: '1080x1080', b: function () { return { bg: '#f8fafc', objs: [
      circ(W * 0.5, H * 0.42, W * 0.34, P, { opacity: 0.12 }), ph(W * 0.22, H * 0.14, W * 0.56, W * 0.56, { circle: true }),
      TC(SV.name || L.h1, { top: H * 0.72, fontSize: W * 0.07, fontWeight: 900 }), pill(SV.price || L.cta, W * 0.5, H * 0.84, P, '#fff', W * 0.036, true),
      T(CO, { top: H * 0.05, fontSize: W * 0.03, fontWeight: 700, fill: P })] }; } },
    { n: L.t3, cat: 'quote', size: '1080x1080', b: function () { return { bg: '#fffdf7', objs: [
      TC('“', { top: H * 0.06, fontSize: W * 0.32, fill: P, fontFamily: 'Playfair Display', lineHeight: 1 }),
      TC(C.text || K.tagline || L.quote, { top: H * 0.34, fontSize: W * 0.06, fontWeight: 700, fontFamily: 'Amiri', lineHeight: 1.35 }),
      R(W * 0.42, H * 0.7, W * 0.16, 4, A), TC(K.branch || L.quoteBy, { top: H * 0.74, fontSize: W * 0.032, fill: '#64748b' }), TC(CO, { top: H * 0.9, fontSize: W * 0.026, fill: P, fontWeight: 700 })] }; } },
    { n: L.t4, cat: 'quote', size: '1080x1350', b: function () { return { bg: P, objs: [
      R(W * 0.07, H * 0.12, W * 0.86, H * 0.68, '#fff', { rx: W * 0.04, ry: W * 0.04, shadow: new fabric.Shadow({ color: 'rgba(0,0,0,.25)', blur: 40, offsetY: 20 }) }),
      ph(W * 0.41, H * 0.06, W * 0.18, W * 0.18, { circle: true }), TC(L.why, { top: H * 0.24, fontSize: W * 0.045, fill: P, fontWeight: 700 }),
      TC(K.pod || K.tagline || L.quote, { top: H * 0.32, fontSize: W * 0.052, fontWeight: 700, left: W * 0.14, width: W * 0.72 }), TC(K.rtb || K.about || '', { top: H * 0.58, fontSize: W * 0.03, fill: '#64748b', left: W * 0.14, width: W * 0.72 }),
      TC(CO, { top: H * 0.88, fontSize: W * 0.035, fill: '#fff', fontWeight: 900 })] }; } },
    { n: L.t5, cat: 'edu', size: '1080x1350', b: function () { var o = [R(0, 0, W, H * 0.24, S), T(L.tip, { top: H * 0.05, fontSize: W * 0.04, fill: A, fontWeight: 700 }), T(C.title || L.faqT, { top: H * 0.1, fontSize: W * 0.065, fill: '#fff', fontWeight: 900 })], qs = FAQ.length ? FAQ.slice(0, 3) : [{ q: L.s1, a: L.sub }, { q: L.s2, a: L.sub }, { q: L.s3, a: L.sub }];
      for (var i = 0; i < qs.length; i++) { var y = H * (0.32 + i * 0.19); o.push(circ(W * 0.86, y + W * 0.05, W * 0.055, P), TC(String(i + 1), { left: W * 0.805, width: W * 0.11, top: y + W * 0.015, fontSize: W * 0.06, fill: '#fff', fontWeight: 900, fontFamily: 'Inter' }), T(qs[i].q, { top: y, width: W * 0.68, fontSize: W * 0.04, fontWeight: 700 }), T(qs[i].a, { top: y + W * 0.07, width: W * 0.68, fontSize: W * 0.03, fill: '#64748b' })); }
      o.push(T(CO + (FOOT ? '   ·   ' + FOOT : ''), { top: H * 0.93, fontSize: W * 0.026, fill: P, fontWeight: 700 })); return { bg: '#fff', objs: o }; } },
    { n: L.t6, cat: 'edu', size: '1080x1080', b: function () { return { bg: grad([S, '#1e293b']), objs: [
      TC('🕛', { top: H * 0.1, fontSize: W * 0.11, fontFamily: 'Inter' }), TC(L.hoursT, { top: H * 0.26, fontSize: W * 0.06, fill: A, fontWeight: 900 }),
      TC(K.hours || L.date, { top: H * 0.38, fontSize: W * 0.055, fill: '#fff', fontWeight: 700, left: W * 0.1, width: W * 0.8 }), TC(K.branch ? '📍  ' + K.branch : '', { top: H * 0.56, fontSize: W * 0.035, fill: light, left: W * 0.1, width: W * 0.8 }),
      R(W * 0.45, H * 0.7, W * 0.1, 5, P), TC(WA ? '📞  ' + WA : CO, { top: H * 0.76, fontSize: W * 0.045, fill: '#fff', fontWeight: 700, fontFamily: 'Inter' }), TC(CO, { top: H * 0.9, fontSize: W * 0.028, fill: light })] }; } },
    { n: L.t7, cat: 'product', size: '1080x1080', b: function () { return { bg: '#fff', objs: [
      ph(0, 0, W / 2, H), ph(W / 2, 0, W / 2, H), R(W / 2 - 3, 0, 6, H, '#fff'),
      pill(L.before, W * 0.25, H * 0.88, 'rgba(15,23,42,.8)', '#fff', W * 0.03, true), pill(L.after, W * 0.75, H * 0.88, P, '#fff', W * 0.03, true),
      circ(W / 2, H / 2, W * 0.06, '#fff', { shadow: new fabric.Shadow({ color: 'rgba(0,0,0,.2)', blur: 20 }) }), TC('⇆', { left: W * 0.44, width: W * 0.12, top: H * 0.465, fontSize: W * 0.05, fill: S, fontFamily: 'Inter' })] }; } },
    { n: L.t8, cat: 'event', size: '1080x1350', b: function () { return { bg: '#0b1020', objs: [
      circ(W * 0.9, H * 0.08, W * 0.4, P, { opacity: 0.35 }), circ(W * 0.05, H * 0.95, W * 0.35, A, { opacity: 0.25 }),
      R(W * 0.08, H * 0.1, W * 0.22, W * 0.24, A, { rx: 18, ry: 18 }), TC('15', { left: W * 0.08, width: W * 0.22, top: H * 0.115, fontSize: W * 0.1, fill: S, fontWeight: 900, fontFamily: 'Inter' }), TC(L.month, { left: W * 0.08, width: W * 0.22, top: H * 0.205, fontSize: W * 0.032, fill: S, fontWeight: 700 }),
      T(L.event, { top: H * 0.36, fontSize: W * 0.042, fill: A, fontWeight: 700 }), T(C.title || L.evTitle, { top: H * 0.42, fontSize: W * 0.085, fill: '#fff', fontWeight: 900 }),
      T('🕗  ' + L.date + '     📍  ' + (K.branch || L.place), { top: H * 0.72, fontSize: W * 0.035, fill: light }), pill(L.register, W * 0.08, H * 0.82, '#fff', S, W * 0.034), T(CO, { top: H * 0.94, fontSize: W * 0.026, fill: 'rgba(255,255,255,.55)' })] }; } },
    { n: L.t9, cat: 'event', size: '1080x1080', b: function () { return { bg: '#fff', objs: [
      R(0, 0, W, H, '', { stroke: P, strokeWidth: W * 0.035 }), TC(L.hiring, { top: H * 0.14, fontSize: W * 0.13, fontWeight: 900, fill: P }),
      TC(C.title || L.role, { top: H * 0.38, fontSize: W * 0.06, fontWeight: 700 }), pill(L.full, W * 0.34, H * 0.56, '#eef2ff', P, W * 0.028, true), pill(K.city || L.city, W * 0.66, H * 0.56, '#eef2ff', P, W * 0.028, true),
      pill(L.apply, W * 0.5, H * 0.72, S, '#fff', W * 0.036, true), TC(CO, { top: H * 0.88, fontSize: W * 0.03, fill: '#64748b', fontWeight: 700 })] }; } },
    { n: L.t10, cat: 'product', size: '1080x1350', b: function () { return { bg: '#f1f5f9', objs: [
      ph(W * 0.08, H * 0.08, W * 0.84, H * 0.55, { rx: W * 0.05 }), pill(L.new, W * 0.13, H * 0.1, A, S, W * 0.032),
      T(SV2.name || L.h1, { top: H * 0.68, fontSize: W * 0.075, fontWeight: 900 }), T(SV2.price ? L.from + ' ' + SV2.price : (K.tagline || L.sub), { top: H * 0.79, fontSize: W * 0.042, fill: P, fontWeight: 700 }),
      T(CO + '  →', { top: H * 0.91, fontSize: W * 0.03, fill: '#64748b' })] }; } },
    { n: L.t11, cat: 'offer', size: '1080x1080', b: function () { return { bg: '#fff', objs: [
      R(0, 0, W, H * 0.13, P), T('📣  ' + L.news, { top: H * 0.035, fontSize: W * 0.04, fill: '#fff', fontWeight: 700 }),
      T(C.title || K.focus || CO, { top: H * 0.22, fontSize: W * 0.08, fontWeight: 900 }), R(W * 0.8, H * 0.45, W * 0.12, 6, A),
      T(C.text || K.about || K.tagline || L.p, { top: H * 0.5, fontSize: W * 0.036, fill: '#475569', lineHeight: 1.45 }), T(CO, { top: H * 0.9, fontSize: W * 0.03, fill: P, fontWeight: 900 })] }; } },
    { n: L.t12, cat: 'story', size: '1080x1920', b: function () { return { bg: grad([P, '#0f172a']), objs: [
      ph(W * 0.1, H * 0.12, W * 0.8, W * 0.8, { rx: W * 0.06 }), pill(L.offer, W * 0.5, H * 0.58, A, S, W * 0.04, true),
      TC(SV.name || L.off, { top: H * 0.63, fontSize: W * 0.1, fill: '#fff', fontWeight: 900 }), TC(SV.price || L.limited, { top: H * 0.75, fontSize: W * 0.055, fill: light }),
      TC(WA ? '💬  ' + L.orderWa + '\n' + WA : '⌃\n' + L.swipe, { top: H * 0.86, fontSize: W * 0.04, fill: '#fff', fontWeight: 700 })] }; } },
    { n: L.t13, cat: 'story', size: '1080x1920', b: function () { return { bg: grad(['#fdf2f8', '#eef2ff']), objs: [
      TC(L.qday, { top: H * 0.16, fontSize: W * 0.05, fill: P, fontWeight: 700 }), TC(C.title || (SVS.length > 1 ? L.which : L.question), { top: H * 0.24, fontSize: W * 0.09, fontWeight: 900, left: W * 0.1, width: W * 0.8 }),
      R(W * 0.12, H * 0.5, W * 0.76, H * 0.075, '#fff', { rx: H * 0.0375, ry: H * 0.0375, stroke: '#e2e8f0', strokeWidth: 3 }), TC(SVS.length > 1 ? SV.name : '👍  ' + L.yes, { top: H * 0.515, fontSize: W * 0.05, fontWeight: 700 }),
      R(W * 0.12, H * 0.61, W * 0.76, H * 0.075, '#fff', { rx: H * 0.0375, ry: H * 0.0375, stroke: '#e2e8f0', strokeWidth: 3 }), TC(SVS.length > 1 ? SV2.name : '🤔  ' + L.no, { top: H * 0.625, fontSize: W * 0.05, fontWeight: 700 }),
      TC(CO, { top: H * 0.9, fontSize: W * 0.035, fill: '#64748b', fontWeight: 700 })] }; } },
    { n: L.t14, cat: 'carousel', size: '1080x1350', b: function () { return { bg: A, objs: [
      T('01', { top: H * 0.08, fontSize: W * 0.06, fontWeight: 900, fill: S, fontFamily: 'Inter' }), T(C.title || (SV.name ? L.whySvc.replace('%s', SV.name) : L.hook), { top: H * 0.25, fontSize: W * 0.11, fontWeight: 900, fill: S, lineHeight: 1.05 }),
      R(W * 0.08, H * 0.66, W * 0.84, 6, S), T(L.swipeSide + '  ←', { top: H * 0.72, fontSize: W * 0.045, fill: S, fontWeight: 700 }), T(CO + (HANDLE ? '   ' + HANDLE : ''), { top: H * 0.92, fontSize: W * 0.03, fill: S })] }; } },
    { n: L.t15, cat: 'season', size: '1080x1080', b: function () { var o = [R(W * 0.06, H * 0.06, W * 0.88, H * 0.88, '', { stroke: A, strokeWidth: 3, rx: 30, ry: 30 }), R(W * 0.08, H * 0.08, W * 0.84, H * 0.84, '', { stroke: A, strokeWidth: 1, rx: 24, ry: 24, opacity: 0.6 })];
      for (var i = 0; i < 7; i++) o.push(TC('✦', { left: W * (0.12 + i * 0.12) - W * 0.4, top: H * 0.17, fontSize: W * 0.03, fill: A, fontFamily: 'Inter' }));
      o.push(TC(C.title || L.greet, { top: H * 0.33, fontSize: W * 0.11, fill: '#fff', fontFamily: 'Amiri', fontWeight: 700 }), TC(L.greetSub, { top: H * 0.56, fontSize: W * 0.04, fill: light }), TC(CO, { top: H * 0.78, fontSize: W * 0.04, fill: A, fontWeight: 700 }));
      return { bg: grad(['#0b1a3a', S]), objs: o }; } },
    { n: L.t16, cat: 'offer', size: '1080x1350', b: function () { var o = [TC(L.menu, { top: H * 0.07, fontSize: W * 0.08, fontWeight: 900, fill: P }), R(W * 0.42, H * 0.17, W * 0.16, 5, A)], sv = SVS.slice(0, 5);
      if (!sv.length) sv = [{ name: L.item + ' 1', price: '45' }, { name: L.item + ' 2', price: '60' }, { name: L.item + ' 3', price: '85' }];
      sv.forEach(function (s, i) { var y = H * (0.25 + i * 0.12); o.push(T(s.name, { top: y, fontSize: W * 0.045, fontWeight: 700, width: W * 0.6, left: W * 0.32 }), T(String(s.price || ''), { top: y, fontSize: W * 0.045, fontWeight: 900, fill: P, width: W * 0.24, left: W * 0.08, textAlign: 'left' }), R(W * 0.08, y + W * 0.075, W * 0.84, 2, '#e2e8f0')); });
      o.push(TC(CO, { top: H * 0.92, fontSize: W * 0.03, fill: '#64748b', fontWeight: 700 })); return { bg: '#fffaf3', objs: o }; } }
  ];
  function build(t) { var p = t.size.split('x'), ow = W, oh = H; W = +p[0]; H = +p[1]; var r = t.b(); W = ow; H = oh; return r; }
  function bgOf(v, w, h) { return Array.isArray(v) ? grad(v) : v; }
  function useTpl(i) {
    if (cv.getObjects().length && hist.length > 1 && !confirm(L.replace)) return;
    var t = TPL[i]; lock = true; cv.clear(); setSize(t.size); var r = t.b();
    cv.setBackgroundColor(r.bg, function () {}); r.objs.forEach(function (o) { cv.add(o); });
    lock = false; cv.discardActiveObject(); cv.requestRenderAll(); document.dispatchEvent(new CustomEvent('dz:tpl')); snap();
  }
  // معاينات حقيقية للقوالب (بتترسم مرة واحدة بعد ما الخطوط تحمل)
  var CATS = [['all', L.cAll], ['offer', L.cOffer], ['product', L.cProduct], ['quote', L.cQuote], ['edu', L.cEdu], ['event', L.cEvent], ['story', L.cStory], ['carousel', L.cCarousel], ['season', L.cSeason]];
  function thumbs() {
    $('dz-tcat').innerHTML = CATS.map(function (c, i) { return '<button type="button" data-cat="' + c[0] + '" class="dz-cat' + (i ? '' : ' on') + '">' + c[1] + '</button>'; }).join('');
    $('dz-tpls').innerHTML = TPL.map(function (t, i) { var p = t.size.split('x'); return '<button type="button" class="dz-tpl" data-tpl="' + i + '" data-cat="' + t.cat + '" style="aspect-ratio:' + p[0] + '/' + p[1] + '"><span>' + t.n + '</span></button>'; }).join('');
    TPL.forEach(function (t, i) { setTimeout(function () { try {
      var p = t.size.split('x'), ow = W, oh = H; W = +p[0]; H = +p[1]; var r = t.b(), sc = new fabric.StaticCanvas(null, { width: W, height: H, renderOnAddRemove: false });
      sc.setBackgroundColor(r.bg, function () {}); r.objs.forEach(function (o) { sc.add(o); }); sc.renderAll(); var u = sc.toDataURL({ format: 'jpeg', quality: 0.8, multiplier: 240 / W }); W = ow; H = oh; sc.dispose();
      var b = document.querySelector('.dz-tpl[data-tpl="' + i + '"]'); if (b) { b.style.backgroundImage = 'url(' + u + ')'; b.classList.add('ready'); }
    } catch (e) {} }, 30 * i); });
  }
  $('dz-tpls').addEventListener('click', function (e) { var b = e.target.closest('[data-tpl]'); if (b) useTpl(+b.dataset.tpl); });
  $('dz-tcat').addEventListener('click', function (e) { var b = e.target.closest('[data-cat]'); if (!b) return; document.querySelectorAll('.dz-cat').forEach(function (x) { x.classList.toggle('on', x === b); }); document.querySelectorAll('.dz-tpl').forEach(function (t) { t.hidden = b.dataset.cat !== 'all' && t.dataset.cat !== b.dataset.cat; }); });
  // تبديل مكان الصورة بصورة حقيقية مقصوصة على نفس الشكل
  function fillPh(g, url) { fabric.Image.fromURL(url, function (im) { if (!im || !im.width) return toast(L.err, 'error');
    var w = g.width * g.scaleX, h = g.height * g.scaleY, k = Math.max(w / im.width, h / im.height), cx = g.left + w / 2, cy = g.top + h / 2;
    im.set({ scaleX: k, scaleY: k, originX: 'center', originY: 'center', left: cx, top: cy });
    im.clipPath = g.dzCircle ? new fabric.Circle({ radius: Math.min(w, h) / 2 / k, originX: 'center', originY: 'center' }) : new fabric.Rect({ width: w / k, height: h / k, rx: g.dzRx / k, ry: g.dzRx / k, originX: 'center', originY: 'center' });
    var idx = cv.getObjects().indexOf(g); lock = true; cv.remove(g); cv.insertAt(im, idx); lock = false; cv.setActiveObject(im); cv.requestRenderAll(); snap(); sync(); }, { crossOrigin: 'anonymous' }); }
  window.dzFillPh = fillPh;

  /* ---------- لوحة التابات ---------- */
  document.querySelectorAll('.dz-rail [data-tab]').forEach(function (b) { b.addEventListener('click', function () { document.querySelectorAll('.dz-rail [data-tab]').forEach(function (x) { x.classList.toggle('on', x === b); }); document.querySelectorAll('.dz-panel [data-pane]').forEach(function (p) { p.hidden = p.dataset.pane !== b.dataset.tab; }); }); });

  /* ---------- الخصائص ---------- */
  function kindOf(o) { if (!o) return []; var k = ['any', 'fill']; if (o.type === 'textbox') k.push('text'); else if (o.type !== 'image') k.push('shape'); if (o.type === 'rect') k.push('round'); if (o.type === 'image') { k.splice(1, 1); k.push('image'); } return k; }
  function sync() {
    var o = cv.getActiveObject(); $('dz-none').hidden = !!o; $('dz-p').hidden = !o; if (!o) { layers(); return; }
    $('dz-phbox').hidden = o.dzRole !== 'ph'; var k = o.dzRole === 'ph' ? ['any'] : kindOf(o); document.querySelectorAll('#dz-p [data-for]').forEach(function (r) { r.hidden = k.indexOf(r.dataset.for) < 0; });
    if (o.type === 'textbox') { $('pp-font').value = o.fontFamily; $('pp-size').value = Math.round(o.fontSize); $('pp-size-v').textContent = Math.round(o.fontSize); $('pp-lh').value = o.lineHeight;
      document.querySelectorAll('[data-tg]').forEach(function (b) { var on = b.dataset.tg === 'bold' ? o.fontWeight >= 700 : b.dataset.tg === 'italic' ? o.fontStyle === 'italic' : !!o.underline; b.classList.toggle('on', on); });
      document.querySelectorAll('[data-al]').forEach(function (b) { b.classList.toggle('on', b.dataset.al === o.textAlign); }); }
    var f = o.type === 'group' ? (o.getObjects()[0] || {}).fill : (o.type === 'line' ? o.stroke : o.fill); if (typeof f === 'string' && /^#/.test(f)) $('pp-fill').value = f.length === 4 ? '#' + f[1] + f[1] + f[2] + f[2] + f[3] + f[3] : f.slice(0, 7);
    if (typeof o.stroke === 'string' && /^#/.test(o.stroke)) $('pp-stroke').value = o.stroke.slice(0, 7); $('pp-sw2').value = o.strokeWidth || 0; $('pp-rx').value = o.rx || 0; $('pp-op').value = o.opacity; $('pp-shadow').checked = !!o.shadow;
    layers();
  }
  cv.on('mouse:dblclick', function (e) { if (e.target && e.target.dzRole === 'ph') CMD.lib(); });
  cv.on('selection:created', sync); cv.on('selection:updated', sync); cv.on('selection:cleared', sync); cv.on('object:modified', sync);
  function prop(k, v, live) { var o = cv.getActiveObject(); if (!o) return; o.set(k, v); if (o.type === 'textbox') o.initDimensions && o.initDimensions(); cv.requestRenderAll(); if (!live) snap(); }
  $('pp-font').addEventListener('change', function () { var f = this.value, o = cv.getActiveObject(); (document.fonts ? document.fonts.load('700 40px "' + f + '"') : Promise.resolve()).then(function () { if (o) { o.set('fontFamily', f); fabric.util.clearFabricFontCache(f); o.initDimensions(); cv.requestRenderAll(); snap(); } }); });
  $('pp-size').addEventListener('input', function () { $('pp-size-v').textContent = this.value; prop('fontSize', +this.value, true); }); $('pp-size').addEventListener('change', snap);
  $('pp-lh').addEventListener('input', function () { prop('lineHeight', +this.value, true); }); $('pp-lh').addEventListener('change', snap);
  $('pp-fill').addEventListener('input', function () { var o = cv.getActiveObject(); if (o) setFill(o, this.value); });
  $('pp-stroke').addEventListener('input', function () { prop('stroke', this.value, true); if (!(cv.getActiveObject() || {}).strokeWidth) prop('strokeWidth', 6, true); }); $('pp-stroke').addEventListener('change', snap);
  $('pp-sw2').addEventListener('input', function () { prop('strokeWidth', +this.value, true); }); $('pp-sw2').addEventListener('change', snap);
  $('pp-rx').addEventListener('input', function () { prop('rx', +this.value, true); prop('ry', +this.value, true); }); $('pp-rx').addEventListener('change', snap);
  $('pp-op').addEventListener('input', function () { prop('opacity', +this.value, true); }); $('pp-op').addEventListener('change', snap);
  $('pp-shadow').addEventListener('change', function () { prop('shadow', this.checked ? new fabric.Shadow({ color: 'rgba(0,0,0,.35)', blur: 30, offsetX: 0, offsetY: 12 }) : null); });
  document.querySelectorAll('[data-tg]').forEach(function (b) { b.addEventListener('click', function () { var o = cv.getActiveObject(); if (!o || o.type !== 'textbox') return; var t = b.dataset.tg;
    if (t === 'bold') prop('fontWeight', o.fontWeight >= 700 ? 400 : 700); else if (t === 'italic') prop('fontStyle', o.fontStyle === 'italic' ? 'normal' : 'italic'); else prop('underline', !o.underline); sync(); }); });
  document.querySelectorAll('[data-al]').forEach(function (b) { b.addEventListener('click', function () { prop('textAlign', b.dataset.al); sync(); }); });

  /* ---------- الطبقات ---------- */
  function label(o) { return o.dzRole === 'ph' ? '📷 ' + L.ph : o.type === 'textbox' ? '“' + (o.text || '').slice(0, 22) + '”' : o.type === 'image' ? '🖼' : o.type === 'group' ? '▣' : o.type === 'circle' ? '●' : o.type === 'textbox' ? 'T' : '■'; }
  function layers() { var a = cv.getActiveObject(), objs = cv.getObjects().slice().reverse();
    $('dz-layers').innerHTML = objs.map(function (o, i) { return '<li><button type="button" data-ly="' + (objs.length - 1 - i) + '" class="w-full text-start truncate rounded-lg px-2 py-1.5 ' + (o === a ? 'bg-brand-50 text-brand-800 font-medium' : 'hover:bg-ink-100 text-ink-700') + '" dir="auto">' + (o.selectable === false ? '🔒 ' : '') + label(o).replace(/</g, '&lt;') + '</button></li>'; }).join(''); }
  $('dz-layers').addEventListener('click', function (e) { var b = e.target.closest('[data-ly]'); if (!b) return; var o = cv.getObjects()[+b.dataset.ly]; if (o.selectable === false) { o.set({ selectable: true, evented: true }); } cv.setActiveObject(o); cv.requestRenderAll(); sync(); });

  /* ---------- الأوامر ---------- */
  // التصدير بالمقاس الحقيقي بالظبط (من غير تقريب التكبير)
  function exportURL() { cv.discardActiveObject(); var z = zoom; cv.setZoom(1); cv.setDimensions({ width: W, height: H }); var u = cv.toDataURL({ format: 'png' }); zoom = z; apply(); cv.requestRenderAll(); return u; }
  function blob(cb) { fetch(exportURL()).then(function (r) { return r.blob(); }).then(cb); }
  function save(then) { var s = document.querySelector('[data-cmd="save"]'); s.disabled = true; $('dz-saved').textContent = L.saving;
    blob(function (b) { var d = new FormData(); d.append('_csrf', C.csrf); d.append('action', 'save'); d.append('name', $('dz-name').value); d.append('file', b, 'design.png');
      fetch(C.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { s.disabled = false;
        if (!j.ok) { $('dz-saved').textContent = ''; return toast(j.error || L.err, 'error'); } $('dz-saved').textContent = L.saved; toast(L.saved); if (then) then(j); }).catch(function () { s.disabled = false; toast(L.err, 'error'); }); }); }
  var CMD = {
    undo: undo, redo: redo, 'zoom+': function () { zoom = Math.min(3, zoom * 1.2); apply(); }, 'zoom-': function () { zoom = Math.max(0.05, zoom / 1.2); apply(); },
    png: function () { fetch(exportURL()).then(function (r) { return r.blob(); }).then(function (bl) { var u = URL.createObjectURL(bl), a = document.createElement('a'); a.href = u; a.download = ($('dz-name').value || 'design') + '.png'; a.click(); setTimeout(function () { URL.revokeObjectURL(u); }, 4000); }); },
    save: function () { save(); }, post: function () { save(function (j) { location.href = j.compose; }); },
    lib: function () { if (!window.mediaPick) return; var o = cv.getActiveObject(); window.mediaPick({ accept: 'image', multiple: false }).then(function (fs) { if (!fs || !fs[0]) return; if (o && o.dzRole === 'ph') fillPh(o, fs[0].url); else addImg(fs[0].url); }); },
    phlib: function () { CMD.lib(); },
    del: function () { cv.getActiveObjects().forEach(function (o) { cv.remove(o); }); cv.discardActiveObject(); cv.requestRenderAll(); sync(); },
    dup: function () { var o = cv.getActiveObject(); if (!o) return; o.clone(function (c) { c.set({ left: o.left + 30, top: o.top + 30 }); cv.add(c); cv.setActiveObject(c); cv.requestRenderAll(); sync(); }); },
    front: function () { var o = cv.getActiveObject(); if (o) { o.bringToFront(); snap(); } }, back: function () { var o = cv.getActiveObject(); if (o) { o.sendToBack(); snap(); } },
    center: function () { var o = cv.getActiveObject(); if (o) { cv.centerObject(o); o.setCoords(); cv.requestRenderAll(); snap(); } },
    lock: function () { var o = cv.getActiveObject(); if (o) { o.set({ selectable: false, evented: false }); cv.discardActiveObject(); cv.requestRenderAll(); snap(); sync(); } }
  };
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-cmd]'); if (b && CMD[b.dataset.cmd]) CMD[b.dataset.cmd](); });
  document.addEventListener('keydown', function (e) {
    var o = cv.getActiveObject(), typing = /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) || (o && o.isEditing); if (typing) return;
    var k = e.key.toLowerCase(), mod = e.ctrlKey || e.metaKey;
    if (mod && k === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); } else if (mod && k === 'y') { e.preventDefault(); redo(); } else if (mod && k === 'd') { e.preventDefault(); CMD.dup(); }
    else if (mod && k === 's') { e.preventDefault(); save(); }
    else if ((k === 'delete' || k === 'backspace') && o) { e.preventDefault(); CMD.del(); }
    else if (o && /^arrow/.test(k)) { e.preventDefault(); var st = e.shiftKey ? 20 : 2; o.set({ left: o.left + (k === 'arrowleft' ? -st : k === 'arrowright' ? st : 0), top: o.top + (k === 'arrowup' ? -st : k === 'arrowdown' ? st : 0) }); o.setCoords(); cv.requestRenderAll(); clearTimeout(saveT); saveT = setTimeout(snap, 400); }
  });
  $('dz-name').addEventListener('change', snap);

  /* ---------- البداية ---------- */
  var draft = null; try { draft = localStorage.getItem(KEY); } catch (e) {}
  function start() {
    if (C.img) { setSize('1080x1080'); addImg(C.img); }
    else if (C.title || C.text) { useTpl(10); }
    else if (draft && confirm(L.restore)) { load(draft, function () { hist = [draft]; }); return; }
    else { useTpl(0); }
    snap();
  }
  (document.fonts && document.fonts.load ? Promise.all(['900 40px Cairo', '700 40px Cairo', '400 40px Cairo', '900 40px Inter', '700 40px "Playfair Display"'].map(function (f) { return document.fonts.load(f); })).catch(function () {}) : Promise.resolve()).then(function () { fit(); start(); sync(); thumbs(); });
  window.dzCanvas = cv; // للاختبارات
  // واجهة للإضافات (designer-pro.js): الأدوات الاحترافية والصفحات وتصميماتي والذكاء الاصطناعي
  window.DZX = { cv: cv, CMD: CMD, snap: snap, sync: sync, load: load, json: json, setSize: setSize, fillPh: fillPh, useTpl: useTpl, exportURL: exportURL, addImg: addImg, layers: layers, fit: fit, apply: apply,
    T: T, TC: TC, pill: pill, R: R, circ: circ, ph: ph, grad: grad, TPL: TPL, thumbs: thumbs, isAr: isAr, toast: toast, P: P, S: S, A: A, K: K, SVS: SVS, CO: CO, WA: WA, HANDLE: HANDLE, FOOT: FOOT, L: L, C: C,
    get W() { return W; }, get H() { return H; }, get zoom() { return zoom; }, setZoom: function (z) { zoom = z; apply(); },
    setLock: function (v) { lock = v; }, resetHist: function () { hist = []; fut = []; snap(); }, setBuilderSize: function (w, h) { W = w; H = h; } };
  document.dispatchEvent(new CustomEvent('dz:ready'));
})();
