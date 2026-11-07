/* المصمم: تجربة زي كانفا (شريط عائم، قائمة بالزرار اليمين، نسخ/لصق، خطوط بمعاينة حية، طبقات بالسحب)،
 * عناصر (ملصقات، إطارات، فواصل، نقوش، أيقونات، أنماط نصوص)، وذكاء أعمق (تصميم من جملة، كاروسيل من موضوع، كل المقاسات) */
(function () {
  var X = window.DZX, PRO = window.dzPro; if (!X || !PRO) return; var cv = X.cv, L = X.L, C = X.C, $ = function (id) { return document.getElementById(id); };
  var P = X.P, S = X.S, A = X.A, K = X.K, clip = null;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function post(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function add(o) { if (o.left == null || o.dzC) { cv.add(o); o.center(); } else cv.add(o); o.setCoords(); cv.setActiveObject(o); cv.requestRenderAll(); X.sync(); return o; }

  /* ---------- 1) الشريط العائم فوق العنصر المحدد ---------- */
  var fl = document.createElement('div'); fl.id = 'dz-float'; fl.className = 'dz-float hidden'; document.body.appendChild(fl);
  var fontBox = document.createElement('div'); fontBox.className = 'dz-fonts hidden'; document.body.appendChild(fontBox);
  function ib(cmd, html, title) { return '<button type="button" class="dz-fb" data-fl="' + cmd + '" title="' + esc(title || '') + '">' + html + '</button>'; }
  function floatHTML(o) {
    var t = o.type === 'textbox', h = '';
    if (t) h += '<button type="button" class="dz-fb !w-auto px-2 text-xs max-w-[120px] truncate" data-fl="font" style="font-family:\'' + esc(o.fontFamily) + '\'">' + esc(o.fontFamily) + ' ▾</button>' + ib('sz-', '−') + '<span class="text-xs tabular-nums w-8 text-center">' + Math.round(o.fontSize) + '</span>' + ib('sz+', '+') + '<span class="dz-fsep"></span>' + ib('bold', '<b>B</b>') + ib('al', o.textAlign === 'center' ? '≡' : o.textAlign === 'left' ? '⇤' : '⇥');
    if (o.type !== 'image') h += '<label class="dz-fb relative" title="' + esc(L.font) + '"><span class="w-4 h-4 rounded-full border border-black/10" style="background:' + esc(typeof o.fill === 'string' ? o.fill : P) + '"></span><input type="color" class="absolute inset-0 opacity-0 cursor-pointer" data-flc></label>';
    return h + '<span class="dz-fsep"></span>' + ib('dup', '⧉', L.dup) + ib('lock', '🔒', L.lock) + ib('del', '🗑', L.del2) + ib('menu', '⋯', L.more);
  }
  function placeFloat() {
    var o = cv.getActiveObject(); if (!o || o.isEditing || o.type === 'activeSelection' && false) { fl.classList.add('hidden'); return; }
    var r = cv.upperCanvasEl.getBoundingClientRect(), b = o.getBoundingRect(), z = 1; // getBoundingRect بعد التكبير بالفعل
    var top = r.top + b.top - 50, left = r.left + b.left + b.width / 2;
    if (top < 70) top = r.top + b.top + b.height + 10;
    fl.style.top = Math.max(70, top) + 'px'; fl.style.left = Math.min(window.innerWidth - 20, Math.max(20, left)) + 'px'; fl.classList.remove('hidden');
  }
  function showFloat() { var o = cv.getActiveObject(); if (!o || o.type === 'activeSelection') { fl.classList.add('hidden'); return; } fl.innerHTML = floatHTML(o); placeFloat(); }
  ['selection:created', 'selection:updated'].forEach(function (ev) { cv.on(ev, function () { setTimeout(showFloat, 0); }); });
  cv.on('selection:cleared', function () { fl.classList.add('hidden'); fontBox.classList.add('hidden'); });
  ['object:moving', 'object:scaling', 'object:rotating'].forEach(function (ev) { cv.on(ev, function () { fl.classList.add('hidden'); }); });
  cv.on('object:modified', function () { setTimeout(showFloat, 0); }); cv.on('text:editing:entered', function () { fl.classList.add('hidden'); }); cv.on('text:editing:exited', showFloat);
  $('dz-stage').addEventListener('scroll', function () { if (!fl.classList.contains('hidden')) placeFloat(); }); window.addEventListener('resize', placeFloat);
  var zr = new MutationObserver(function () { if (cv.getActiveObject()) setTimeout(placeFloat, 30); }); zr.observe($('dz-zoom'), { childList: true });
  fl.addEventListener('input', function (e) { if (e.target.matches('[data-flc]')) { var o = cv.getActiveObject(); if (!o) return; if (o.type === 'group') o.getObjects().forEach(function (x) { if (x.type !== 'textbox') x.set('fill', e.target.value); }); else o.set(o.type === 'line' ? 'stroke' : 'fill', e.target.value); cv.requestRenderAll(); } });
  fl.addEventListener('change', function (e) { if (e.target.matches('[data-flc]')) { X.snap(); showFloat(); } });
  fl.addEventListener('click', function (e) { var b = e.target.closest('[data-fl]'); if (!b) return; var o = cv.getActiveObject(); if (!o) return; var k = b.dataset.fl;
    if (k === 'font') return openFonts(b);
    if (k === 'sz-' || k === 'sz+') { o.set('fontSize', Math.max(8, Math.round(o.fontSize * (k === 'sz+' ? 1.1 : 0.9)))); o.initDimensions(); }
    if (k === 'bold') o.set('fontWeight', o.fontWeight >= 700 ? 400 : 800);
    if (k === 'al') o.set('textAlign', o.textAlign === 'right' ? 'center' : o.textAlign === 'center' ? 'left' : 'right');
    if (k === 'dup') return X.CMD.dup(); if (k === 'del') { X.CMD.del(); fl.classList.add('hidden'); return; } if (k === 'lock') { X.CMD.lock(); fl.classList.add('hidden'); return; }
    if (k === 'menu') { var r = b.getBoundingClientRect(); return openMenu(r.left, r.bottom + 4, o); }
    cv.requestRenderAll(); X.snap(); X.sync(); showFloat(); });

  /* ---------- 2) اختيار الخط بمعاينة حية ---------- */
  var orig = null;
  function openFonts(btn) { var o = cv.getActiveObject(); orig = o.fontFamily; var r = btn.getBoundingClientRect();
    fontBox.innerHTML = C.fonts.map(function (f) { return '<button type="button" data-font="' + esc(f) + '" style="font-family:\'' + esc(f) + '\'" class="' + (f === o.fontFamily ? 'on' : '') + '">' + esc(X.isAr(o.text) ? 'أبجد هوز ' + f : 'Aa ' + f) + '</button>'; }).join('');
    fontBox.style.top = (r.bottom + 6) + 'px'; fontBox.style.left = Math.max(10, r.left) + 'px'; fontBox.classList.remove('hidden'); }
  function setFont(f, commit) { var o = cv.getActiveObject(); if (!o || o.type !== 'textbox') return; (document.fonts ? document.fonts.load('700 30px "' + f + '"') : Promise.resolve()).then(function () { o.set('fontFamily', f); fabric.util.clearFabricFontCache(f); o.initDimensions(); cv.requestRenderAll(); if (commit) { X.snap(); X.sync(); showFloat(); } }); }
  fontBox.addEventListener('mouseover', function (e) { var b = e.target.closest('[data-font]'); if (b) setFont(b.dataset.font, false); });
  fontBox.addEventListener('mouseleave', function () { if (orig) setFont(orig, false); });
  fontBox.addEventListener('click', function (e) { var b = e.target.closest('[data-font]'); if (!b) return; orig = null; setFont(b.dataset.font, true); fontBox.classList.add('hidden'); });
  document.addEventListener('mousedown', function (e) { if (!fontBox.contains(e.target) && !e.target.closest('[data-fl="font"]') && !fontBox.classList.contains('hidden')) { if (orig) setFont(orig, false); orig = null; fontBox.classList.add('hidden'); } });

  /* ---------- 3) قائمة الزرار اليمين + نسخ ولصق ---------- */
  var menu = document.createElement('div'); menu.className = 'dz-menu hidden'; document.body.appendChild(menu);
  function copy() { var o = cv.getActiveObject(); if (!o) return; o.clone(function (c) { clip = c; }, ['dzRole', 'dzCircle', 'dzRx', 'dzF']); }
  function paste() { if (!clip) return; clip.clone(function (c) { cv.discardActiveObject(); c.set({ left: c.left + 24, top: c.top + 24, evented: true });
      if (c.type === 'activeSelection') { c.canvas = cv; c.forEachObject(function (x) { cv.add(x); }); c.setCoords(); } else cv.add(c);
      clip.left += 24; clip.top += 24; cv.setActiveObject(c); cv.requestRenderAll(); X.snap(); }, ['dzRole', 'dzCircle', 'dzRx', 'dzF']); }
  function openMenu(x, y, o) {
    var items = o ? [['copy', L.copy, 'Ctrl+C'], ['paste', L.paste, 'Ctrl+V'], ['dup', L.dup, 'Ctrl+D'], ['-'], ['front', L.front], ['back', L.back], ['center', L.center], ['-'],
      o.type === 'activeSelection' ? ['group', L.group, 'Ctrl+G'] : o.type === 'group' && o.dzRole !== 'ph' ? ['ungroup', L.ungroup] : null, ['lock', L.lock], ['-'], ['del', L.del2, 'Del']] : [['paste', L.paste, 'Ctrl+V']];
    menu.innerHTML = items.filter(Boolean).map(function (i) { return i[0] === '-' ? '<hr>' : '<button type="button" data-mn="' + i[0] + '"><span>' + esc(i[1]) + '</span>' + (i[2] ? '<kbd>' + i[2] + '</kbd>' : '') + '</button>'; }).join('');
    menu.style.left = Math.min(window.innerWidth - 220, x) + 'px'; menu.style.top = Math.min(window.innerHeight - 320, y) + 'px'; menu.classList.remove('hidden'); fl.classList.add('hidden'); }
  cv.upperCanvasEl.addEventListener('contextmenu', function (e) { e.preventDefault(); var t = cv.findTarget(e, false); var a = cv.getActiveObject();
    if (t && (!a || (a !== t && !(a.type === 'activeSelection' && a.contains(t))))) { cv.setActiveObject(t); cv.requestRenderAll(); X.sync(); }
    openMenu(e.clientX, e.clientY, cv.getActiveObject()); });
  document.addEventListener('mousedown', function (e) { if (!menu.contains(e.target)) menu.classList.add('hidden'); });
  menu.addEventListener('click', function (e) { var b = e.target.closest('[data-mn]'); if (!b) return; var k = b.dataset.mn; menu.classList.add('hidden');
    if (k === 'copy') return copy(); if (k === 'paste') return paste();
    var map = { dup: 'dup', front: 'front', back: 'back', center: 'center', lock: 'lock', del: 'del' }; if (map[k]) return X.CMD[map[k]]();
    var g = document.querySelector('[data-cmd="' + k + '"]'); if (g) g.click(); });
  document.addEventListener('keydown', function (e) { var o = cv.getActiveObject(); if (/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) || (o && o.isEditing)) return;
    var k = e.key.toLowerCase(); if ((e.ctrlKey || e.metaKey) && k === 'c') { copy(); } if ((e.ctrlKey || e.metaKey) && k === 'v' && clip) { e.preventDefault(); paste(); } if (k === 'escape') { menu.classList.add('hidden'); cv.discardActiveObject(); cv.requestRenderAll(); } });

  /* ---------- 4) الطبقات: سحب لإعادة الترتيب + إظهار/إخفاء + قفل ---------- */
  var ly = $('dz-layers'), drawing = false;
  function name(o) { return o.dzRole === 'ph' ? '📷 ' + L.ph : o.type === 'textbox' ? '“' + (o.text || '').slice(0, 20) + '”' : o.type === 'image' ? '🖼 ' : o.type === 'group' ? '▣ ' : '■ ' + (o.type || ''); }
  function drawLayers() { if (drawing) return; drawing = true; var a = cv.getActiveObject(), objs = cv.getObjects(), n = objs.length;
    ly.innerHTML = objs.slice().reverse().map(function (o, i) { var idx = n - 1 - i, on = o === a || (a && a.type === 'activeSelection' && a.contains(o));
      return '<li draggable="true" data-li="' + idx + '" class="dz-ly' + (on ? ' on' : '') + (o.visible === false ? ' off' : '') + '"><span class="cursor-grab text-ink-300">⋮⋮</span><button type="button" class="flex-1 text-start truncate" data-ly="' + idx + '" dir="auto">' + esc(name(o)) + '</button>' +
        '<button type="button" data-vis="' + idx + '" title="' + esc(o.visible === false ? L.show : L.hide) + '">' + (o.visible === false ? '◌' : '👁') + '</button><button type="button" data-lk="' + idx + '" title="' + esc(o.selectable === false ? L.unlock : L.lock) + '">' + (o.selectable === false ? '🔒' : '🔓') + '</button></li>'; }).join('');
    drawing = false; }
  new MutationObserver(function () { if (!drawing && !ly.querySelector('[data-li]')) drawLayers(); }).observe(ly, { childList: true });
  ['object:added', 'object:removed', 'selection:created', 'selection:updated', 'selection:cleared', 'object:modified'].forEach(function (ev) { cv.on(ev, function () { setTimeout(drawLayers, 0); }); });
  ly.addEventListener('click', function (e) { var b = e.target.closest('[data-vis],[data-lk]'); if (!b) return; e.stopPropagation(); var o = cv.getObjects()[+(b.dataset.vis || b.dataset.lk)];
    if (b.dataset.vis) { o.set('visible', o.visible === false); if (!o.visible) cv.discardActiveObject(); } else { var lk = o.selectable !== false; o.set({ selectable: !lk, evented: !lk }); if (lk) cv.discardActiveObject(); }
    cv.requestRenderAll(); X.snap(); drawLayers(); }, true);
  var dragI = -1;
  ly.addEventListener('dragstart', function (e) { var li = e.target.closest('[data-li]'); if (li) { dragI = +li.dataset.li; e.dataTransfer.effectAllowed = 'move'; li.classList.add('opacity-50'); } });
  ly.addEventListener('dragover', function (e) { if (dragI > -1) { e.preventDefault(); var li = e.target.closest('[data-li]'); ly.querySelectorAll('.drop').forEach(function (x) { x.classList.remove('drop'); }); if (li) li.classList.add('drop'); } });
  ly.addEventListener('drop', function (e) { e.preventDefault(); var li = e.target.closest('[data-li]'); if (dragI < 0 || !li) return; var o = cv.getObjects()[dragI]; o.moveTo(+li.dataset.li); dragI = -1; cv.requestRenderAll(); X.snap(); drawLayers(); });
  ly.addEventListener('dragend', function () { dragI = -1; drawLayers(); });

  /* ---------- 5) العناصر ---------- */
  function badge(t, kind) { var fs = X.W * 0.04, tx = new fabric.Textbox(t, { fontSize: fs, fontWeight: 900, fill: kind === 2 ? S : '#fff', fontFamily: X.isAr(t) ? 'Cairo' : 'Inter', textAlign: 'center', originX: 'center', originY: 'center', width: fs * Math.max(4, t.length * 0.62) });
    var bg;
    if (kind === 0) { var pts = []; for (var i = 0; i < 24; i++) { var r = i % 2 ? tx.width * 0.62 : tx.width * 0.74, a = Math.PI / 12 * i; pts.push({ x: r * Math.cos(a), y: r * Math.sin(a) }); } bg = new fabric.Polygon(pts, { fill: '#dc2626', originX: 'center', originY: 'center' }); }
    else if (kind === 1) bg = new fabric.Rect({ width: tx.width + fs * 1.6, height: fs * 2, rx: fs, ry: fs, fill: P, originX: 'center', originY: 'center' });
    else if (kind === 2) bg = new fabric.Rect({ width: tx.width + fs * 1.6, height: fs * 2, rx: 6, ry: 6, fill: A, originX: 'center', originY: 'center', angle: -4 });
    else bg = new fabric.Path('M0 0 H' + (tx.width + fs * 2) + ' L' + (tx.width + fs * 1.2) + ' ' + fs + ' L' + (tx.width + fs * 2) + ' ' + fs * 2 + ' H0 L' + fs * 0.8 + ' ' + fs + ' Z', { fill: S, originX: 'center', originY: 'center' });
    return new fabric.Group([bg, tx], { dzC: 1 }); }
  var STK = L.stk.map(function (t, i) { return { t: t, k: i % 4 }; });
  $('dz-stk').innerHTML = STK.map(function (s, i) { var st = ['background:#dc2626;color:#fff;border-radius:999px', 'background:' + P + ';color:#fff;border-radius:999px', 'background:' + A + ';color:' + S + ';transform:rotate(-4deg)', 'background:' + S + ';color:#fff;clip-path:polygon(0 0,100% 0,90% 50%,100% 100%,0 100%,8% 50%)'][s.k];
    return '<button type="button" class="dz-el" data-stk="' + i + '" data-q="' + esc(s.t) + '"><span style="' + st + ';padding:.35rem 1.1rem;white-space:nowrap;font-weight:800;font-size:12px;display:inline-block">' + esc(s.t) + '</span></button>'; }).join('');
  var FRM = [['polaroid', '▯'], ['circle', '◯'], ['round', '▢'], ['arch', '⌒'], ['stack', '❏'], ['ring', '◎']];
  $('dz-frm').innerHTML = FRM.map(function (f) { return '<button type="button" class="dz-el !h-16 text-2xl" data-frm="' + f[0] + '" data-q="frame ' + f[0] + '">' + f[1] + '</button>'; }).join('');
  function frame(k) { var W = X.W, s = W * 0.45, g;
    if (k === 'polaroid') { var r = new fabric.Rect({ width: s + 40, height: s + 120, fill: '#fff', shadow: new fabric.Shadow({ color: 'rgba(0,0,0,.25)', blur: 30, offsetY: 12 }) }); var p = X.ph(20, 20, s, s); return [r, p].map(function (o, i) { o.set({ left: W * 0.27 + (i ? 20 : 0), top: X.H * 0.2 + (i ? 20 : 0), angle: -3 }); return o; }); }
    if (k === 'circle') return [X.ph(W * 0.275, X.H * 0.2, s, s, { circle: true })];
    if (k === 'ring') return [new fabric.Circle({ radius: s / 2 + 18, fill: '', stroke: P, strokeWidth: 10, left: W * 0.275 - 18, top: X.H * 0.2 - 18 }), X.ph(W * 0.275, X.H * 0.2, s, s, { circle: true })];
    if (k === 'round') return [X.ph(W * 0.275, X.H * 0.2, s, s * 1.2, { rx: W * 0.05 })];
    if (k === 'arch') return [X.ph(W * 0.275, X.H * 0.2, s, s * 1.3, { rx: s / 2 })];
    return [new fabric.Rect({ left: W * 0.3, top: X.H * 0.22, width: s, height: s, fill: P, opacity: 0.25, angle: 6, rx: 20, ry: 20 }), X.ph(W * 0.275, X.H * 0.2, s, s, { rx: 20 })]; }
  var LNS = [['M0 0 H300', 'line'], ['M0 0 H120 M180 0 H300', 'dash'], ['M0 0 Q75 -30 150 0 T300 0', 'wave'], ['M0 0 H280 M255 -18 L280 0 L255 18', 'arrow'], ['M0 20 C80 -20 220 -20 300 20', 'curve'], ['M0 0 H300 M0 12 H300', 'double']];
  $('dz-lns').innerHTML = LNS.map(function (l, i) { return '<button type="button" class="dz-el !h-12" data-ln="' + i + '" data-q="line ' + l[1] + '"><svg viewBox="-10 -30 320 60" class="w-full h-6" fill="none" stroke="currentColor" stroke-width="10" stroke-linecap="round"><path d="' + l[0] + '"/></svg></button>'; }).join('');
  function pattern(k, col, bg) { var c = document.createElement('canvas'), n = 40; c.width = c.height = n; var x = c.getContext('2d'); x.fillStyle = bg; x.fillRect(0, 0, n, n); x.fillStyle = col; x.strokeStyle = col; x.lineWidth = 3;
    if (k === 0) { x.beginPath(); x.arc(n / 2, n / 2, 4, 0, 7); x.fill(); } else if (k === 1) { x.beginPath(); x.moveTo(0, n); x.lineTo(n, 0); x.stroke(); } else if (k === 2) { x.strokeRect(0, 0, n, n); } else { x.beginPath(); x.moveTo(0, n * 0.6); x.quadraticCurveTo(n / 4, n * 0.4, n / 2, n * 0.6); x.quadraticCurveTo(n * 0.75, n * 0.8, n, n * 0.6); x.stroke(); } return c; }
  var PAT = []; [[P, '#ffffff'], [A, S], ['#ffffff', P], [S, '#f8fafc']].forEach(function (cc) { for (var k = 0; k < 4; k++) PAT.push([k, cc[0], cc[1]]); });
  $('dz-pat').innerHTML = PAT.slice(0, 12).map(function (p, i) { return '<button type="button" class="dz-el !h-12 !p-0 overflow-hidden" data-pat="' + i + '" data-q="pattern"><img src="' + pattern(p[0], p[1] + '55', p[2]).toDataURL() + '" class="w-full h-full object-cover"></button>'; }).join('');
  var ICONS = Object.keys(C.icons || {});
  $('dz-ico').innerHTML = ICONS.map(function (k) { return '<button type="button" class="dz-el !h-11" data-ico="' + esc(k) + '" data-q="' + esc(k) + '" title="' + esc(k) + '"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="' + esc(C.icons[k]) + '"/></svg></button>'; }).join('');
  $('dz-tsty').innerHTML = L.tsty.map(function (t, i) { var f = [['Amiri', 'Cairo'], ['Cairo', 'Tajawal'], ['Lalezar', 'Cairo'], ['Playfair Display', 'Inter']][i]; return '<button type="button" class="dz-add !mb-0" data-ts="' + i + '"><b style="font-family:\'' + f[0] + '\';font-size:20px;display:block">' + esc(t[0]) + '</b><span style="font-family:\'' + f[1] + '\';font-size:12px;color:#64748b">' + esc(t[1]) + '</span></button>'; }).join('');
  document.addEventListener('click', function (e) { var b, W = X.W;
    if ((b = e.target.closest('[data-stk]'))) { var s = STK[+b.dataset.stk]; add(badge(s.t, s.k)); }
    else if ((b = e.target.closest('[data-frm]'))) { var objs = frame(b.dataset.frm); objs.forEach(function (o) { cv.add(o); }); cv.setActiveObject(objs[objs.length - 1]); cv.requestRenderAll(); X.snap(); X.sync(); }
    else if ((b = e.target.closest('[data-ln]'))) { var l = LNS[+b.dataset.ln]; add(new fabric.Path(l[0], { fill: '', stroke: S, strokeWidth: 8, strokeLineCap: 'round', scaleX: W / 700, scaleY: W / 700, dzC: 1 })); }
    else if ((b = e.target.closest('[data-pat]'))) { var p = PAT[+b.dataset.pat]; cv.setBackgroundColor(new fabric.Pattern({ source: pattern(p[0], p[1] + '55', p[2]), repeat: 'repeat' }), function () { cv.requestRenderAll(); X.snap(); }); }
    else if ((b = e.target.closest('[data-ico]'))) { add(new fabric.Path(C.icons[b.dataset.ico], { fill: '', stroke: P, strokeWidth: 1.6, strokeLineCap: 'round', strokeLineJoin: 'round', scaleX: W / 160, scaleY: W / 160, dzC: 1 })); }
    else if ((b = e.target.closest('[data-ts]'))) { var i = +b.dataset.ts, t = L.tsty[i], f = [['Amiri', 'Cairo'], ['Cairo', 'Tajawal'], ['Lalezar', 'Cairo'], ['Playfair Display', 'Inter']][i];
      var h1 = X.TC(t[0], { fontFamily: f[0], fontSize: W * 0.09, fontWeight: 700, top: 0, left: 0, width: W * 0.8 }), h2 = X.TC(t[1], { fontFamily: f[1], fontSize: W * 0.035, fill: '#64748b', top: W * 0.13, left: 0, width: W * 0.8 });
      add(new fabric.Group([h1, h2], { dzC: 1 })); }
  });
  $('dz-el-q').addEventListener('input', function () { var v = this.value.trim().toLowerCase(), n = 0; document.querySelectorAll('[data-pane="el"] [data-q]').forEach(function (b) { var ok = !v || b.dataset.q.toLowerCase().indexOf(v) > -1; b.hidden = !ok; if (ok) n++; }); });

  /* ---------- 6) تصميم من جملة: الذكاء الاصطناعي بيختار التخطيط والمود والنصوص والصورة ---------- */
  var MOODS = { bold: { bg: P, fg: '#fff', ac: A, sub: 'rgba(255,255,255,.85)', tf: 'Cairo' }, elegant: { bg: '#faf7f2', fg: '#1c1917', ac: '#b08d57', sub: '#57534e', tf: 'Amiri' }, fresh: { bg: '#ecfdf5', fg: '#064e3b', ac: '#10b981', sub: '#047857', tf: 'Tajawal' },
    warm: { bg: '#fff7ed', fg: '#431407', ac: '#ea580c', sub: '#9a3412', tf: 'Changa' }, dark: { bg: '#0b1020', fg: '#fff', ac: A, sub: 'rgba(255,255,255,.75)', tf: 'Cairo' } };
  function layout(d) { var W = X.W, H = X.H, m = MOODS[d.mood] || MOODS.bold, T = X.T, TC = X.TC, o = [], title = d.title || X.CO, sub = d.sub || K.tagline || '', cta = d.cta || L.cta, foot = X.FOOT ? X.CO + '   ·   ' + X.FOOT : X.CO;
    var tt = { fontFamily: m.tf, fontWeight: m.tf === 'Amiri' ? 700 : 900, fill: m.fg };
    if (d.layout === 'hero') { o.push(X.ph(0, 0, W, H), X.R(0, H * 0.45, W, H * 0.55, '', { fill: new fabric.Gradient({ type: 'linear', coords: { x1: 0, y1: 0, x2: 0, y2: H * 0.55 }, colorStops: [{ offset: 0, color: 'rgba(0,0,0,0)' }, { offset: 1, color: 'rgba(0,0,0,.85)' }] }) }),
      T(title, Object.assign({}, tt, { fill: '#fff', top: H * 0.62, fontSize: W * 0.085 })), T(sub, { top: H * 0.78, fontSize: W * 0.036, fill: 'rgba(255,255,255,.85)' }), X.pill(cta, W * 0.08, H * 0.88, m.ac, m.bg === '#0b1020' ? '#0b1020' : '#fff', W * 0.03)); return { bg: '#000', objs: o }; }
    if (d.layout === 'split') { o.push(X.ph(W / 2, 0, W / 2, H), T(title, Object.assign({}, tt, { top: H * 0.22, fontSize: W * 0.075, left: W * 0.05, width: W * 0.42 })), T(sub, { top: H * 0.5, fontSize: W * 0.032, fill: m.sub, left: W * 0.05, width: W * 0.42 }), X.pill(cta, W * 0.05, H * 0.72, m.ac, '#fff', W * 0.03), T(X.CO, { top: H * 0.9, fontSize: W * 0.024, fill: m.sub, left: W * 0.05, width: W * 0.42 })); return { bg: m.bg, objs: o }; }
    if (d.layout === 'card') { o.push(X.circ(W * 0.85, H * 0.12, W * 0.25, m.ac, { opacity: 0.25 }), X.R(W * 0.08, H * 0.08, W * 0.84, H * 0.56, '#fff', { rx: W * 0.04, ry: W * 0.04, shadow: new fabric.Shadow({ color: 'rgba(0,0,0,.18)', blur: 40, offsetY: 18 }) }), X.ph(W * 0.11, H * 0.11, W * 0.78, H * 0.5, { rx: W * 0.03 }),
      X.TC(title, Object.assign({}, tt, { top: H * 0.68, fontSize: W * 0.07 })), X.TC(sub, { top: H * 0.8, fontSize: W * 0.032, fill: m.sub }), X.TC(foot, { top: H * 0.92, fontSize: W * 0.024, fill: m.sub })); return { bg: m.bg, objs: o }; }
    if (d.layout === 'badge') { o.push(X.circ(W * 0.5, H * 0.38, W * 0.3, m.ac, { opacity: 0.18 }), X.ph(W * 0.25, H * 0.13, W * 0.5, W * 0.5, { circle: true }), badge(d.badge || L.off, 0),
      X.TC(title, Object.assign({}, tt, { top: H * 0.66, fontSize: W * 0.075 })), X.TC(sub, { top: H * 0.78, fontSize: W * 0.032, fill: m.sub }), X.pill(cta, W * 0.5, H * 0.87, m.ac, '#fff', W * 0.03, true)); o[2].set({ left: W * 0.66, top: H * 0.1, dzC: 0 }); return { bg: m.bg, objs: o }; }
    if (d.layout === 'minimal') { o.push(X.R(W * 0.46, H * 0.3, W * 0.08, 4, m.ac), X.TC(title, Object.assign({}, tt, { top: H * 0.36, fontSize: W * 0.08 })), X.TC(sub, { top: H * 0.55, fontSize: W * 0.032, fill: m.sub, left: W * 0.15, width: W * 0.7 }), X.TC(X.CO, { top: H * 0.86, fontSize: W * 0.026, fill: m.sub, fontWeight: 700 })); return { bg: m.bg, objs: o }; }
    o.push(X.circ(W * 0.1, H * 0.1, W * 0.2, m.ac, { opacity: 0.3 }), X.circ(W * 0.92, H * 0.9, W * 0.26, m.ac, { opacity: 0.2 }), X.TC(title, Object.assign({}, tt, { top: H * 0.3, fontSize: W * 0.1, left: W * 0.08, width: W * 0.84 })), X.TC(sub, { top: H * 0.55, fontSize: W * 0.036, fill: m.sub, left: W * 0.1, width: W * 0.8 }), X.pill(cta, W * 0.5, H * 0.7, m.ac, m.bg === '#0b1020' ? '#0b1020' : '#fff', W * 0.034, true), X.TC(foot, { top: H * 0.9, fontSize: W * 0.024, fill: m.sub }));
    return { bg: m.bg, objs: o }; }
  function paint(r, noKit) { X.setLock(true); cv.clear(); cv.setBackgroundColor(r.bg, function () {}); r.objs.forEach(function (o) { cv.add(o); }); X.setLock(false); cv.discardActiveObject(); cv.requestRenderAll(); if (!noKit) document.dispatchEvent(new CustomEvent('dz:tpl')); }
  function busy(b, on, txt) { b.disabled = on; var sp = b.querySelector('span'); if (sp) { if (on) { sp.dataset.o = sp.textContent; sp.textContent = txt; } else if (sp.dataset.o) sp.textContent = sp.dataset.o; } b.classList.toggle('animate-pulse', on); }
  function genInto(prompt, ar) { var st = $('dz-ai-st'); st.textContent = L.imgWait;
    var call = function (d) { var f = new FormData(); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch('index.php?p=ai_task', { method: 'POST', body: f, headers: { 'X-CSRF-Token': C.csrf }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); };
    call({ task: 'image', text: prompt, aspect: ar }).then(function (d) { if (d.error) { st.textContent = d.error; return; }
      (function poll(n) { call({ task: 'image_status', id: d.id }).then(function (s) { if (s.error) { st.textContent = s.error; return; }
        if (s.status === 'completed') { var im = (s.images || [])[0], ph = cv.getObjects().filter(function (o) { return o.dzRole === 'ph'; })[0]; st.textContent = L.genDone; if (im && ph) X.fillPh(ph, im.src); return; }
        if (n < 80) setTimeout(function () { poll(n + 1); }, 3000); }).catch(function () { setTimeout(function () { poll(n + 1); }, 5000); }); })(0); }); }
  var MORE = {
    ailayout: function () { var b = document.querySelector('[data-cmd="ailayout"]'); busy(b, true, L.aiBusy);
      post({ action: 'ai', mode: 'layout', topic: $('dz-ai-brief').value }).then(function (d) { busy(b, false); if (!d.ok) return X.toast(d.error || L.err, 'error');
        paint(layout(d)); X.snap(); X.toast(L.aiDone);
        if (d.image_prompt && C.hf && $('dz-ai-withimg').checked && cv.getObjects().some(function (o) { return o.dzRole === 'ph'; })) genInto(d.image_prompt, X.W === X.H ? '1:1' : X.H > X.W * 1.5 ? '9:16' : X.H > X.W ? '4:5' : '16:9');
      }).catch(function () { busy(b, false); X.toast(L.err, 'error'); }); },
    aicarousel: function () { var b = document.querySelector('[data-cmd="aicarousel"]'); busy(b, true, L.carBusy);
      post({ action: 'ai', mode: 'carousel', topic: $('dz-ai-car').value }).then(function (d) { busy(b, false); if (!d.ok) return X.toast(d.error || L.err, 'error');
        if (X.W !== 1080 || X.H !== 1350) X.setSize('1080x1350'); var W = X.W, H = X.H, n = d.slides.length, out = [];
        d.slides.forEach(function (s, i) { var first = i === 0, last = i === n - 1, objs = [];
          if (first) objs.push(X.circ(W * 0.9, H * 0.08, W * 0.4, A, { opacity: 0.35 }), X.T(s.title, { top: H * 0.28, fontSize: W * 0.1, fill: '#fff', fontWeight: 900, lineHeight: 1.1 }), X.T(s.body, { top: H * 0.62, fontSize: W * 0.04, fill: 'rgba(255,255,255,.85)' }), X.T(L.swipeSide + '  ←', { top: H * 0.85, fontSize: W * 0.04, fill: A, fontWeight: 700 }));
          else if (last) objs.push(X.TC(s.title, { top: H * 0.3, fontSize: W * 0.085, fontWeight: 900, fill: S }), X.TC(s.body, { top: H * 0.48, fontSize: W * 0.04, fill: '#475569', left: W * 0.12, width: W * 0.76 }), X.pill(X.WA ? L.orderWa : L.cta, W * 0.5, H * 0.7, P, '#fff', W * 0.036, true), X.TC(X.CO + (X.FOOT ? '\n' + X.FOOT : ''), { top: H * 0.84, fontSize: W * 0.03, fill: '#64748b' }));
          else objs.push(X.T(String(i).padStart(2, '0'), { top: H * 0.1, fontSize: W * 0.16, fontWeight: 900, fill: A, fontFamily: 'Inter' }), X.R(W * 0.08, H * 0.3, W * 0.12, 6, P), X.T(s.title, { top: H * 0.34, fontSize: W * 0.075, fontWeight: 900, fill: S }), X.T(s.body, { top: H * 0.55, fontSize: W * 0.042, fill: '#334155', lineHeight: 1.45 }));
          objs.push(X.T((i + 1) + ' / ' + n, { top: H * 0.94, fontSize: W * 0.026, fill: first ? 'rgba(255,255,255,.6)' : '#94a3b8', fontFamily: 'Inter', textAlign: 'left', direction: 'ltr' }));
          paint({ bg: first ? X.grad([P, S]) : last ? '#f8fafc' : '#ffffff', objs: objs }); out.push(X.json()); });
        PRO.loadPages(out, $('dz-ai-car').value || L.t14); X.toast(L.carDone.replace('%s', n));
      }).catch(function () { busy(b, false); X.toast(L.err, 'error'); }); },
    resizeall: function () { var b = document.querySelector('[data-cmd="resizeall"]'); busy(b, true, L.aiBusy);
      var src = JSON.parse(X.json()), W0 = src.w, H0 = src.h, base = $('dz-name').value, sizes = Object.keys(L.sizes).filter(function (k) { return k !== W0 + 'x' + H0; }), done = 0;
      sizes.forEach(function (sz) { var p = sz.split('x'), W = +p[0], H = +p[1], s = Math.min(W / W0, H / H0), c = JSON.parse(JSON.stringify(src.c));
        (c.objects || []).forEach(function (o) {
          var sx = o.scaleX || 1, sy = o.scaleY || 1, ow = o.width * sx, oh = o.height * sy;
          if (ow >= W0 * 0.95 && oh >= H0 * 0.6) { // صورة أو خلفية بعرض اللوحة: بتغطي المقاس الجديد كله
            var f = Math.max(W / ow, H / oh); o.scaleX = sx * f; o.scaleY = sy * f; o.left = (W - ow * f) / 2; o.top = (H - oh * f) / 2; return; }
          var cx = o.left - W0 / 2, cy = o.top - H0 / 2; o.left = W / 2 + cx * s; o.top = H / 2 + cy * s; o.scaleX = sx * s; o.scaleY = sy * s; }); // الباقي بنفس النسبة حوالين النص
        var j = window.dzSmart ? window.dzSmart({ w: W0, h: H0, name: base + ' — ' + L.sizes[sz], c: src.c }, W, H) : JSON.stringify({ w: W, h: H, name: base + ' — ' + L.sizes[sz], c: c }); // ترتيب ذكي لو متاح
        post({ action: 'dsave', id: 0, name: base + ' — ' + L.sizes[sz], w: W, h: H, pages: 1, data: JSON.stringify({ v: 2, pages: [j] }), thumb: '' }).then(function () { if (++done === sizes.length) { busy(b, false); X.toast(L.resizeDone.replace('%s', done)); PRO.mine(); } }); });
    }
  };
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-cmd]'); if (b && MORE[b.dataset.cmd]) { e.stopImmediatePropagation(); MORE[b.dataset.cmd](); } }, true);
  window.dzPlus = { layout: layout, paint: paint, copy: copy, paste: paste, showFloat: showFloat };
  drawLayers();
})();
