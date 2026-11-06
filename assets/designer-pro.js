/* المصمم الاحترافي (إضافات على designer.js):
 * خطوط إرشادية ولزق، تكبير بعجلة الماوس وتحريك بالمسطرة، محاذاة وتوزيع وتجميع، تأثيرات النص، فلاتر وأشكال الصور،
 * صفحات متعددة (كاروسيل)، تصميماتي، تصدير PNG/JPG/PDF، مقاس مخصص، ذكاء اصطناعي جوه المصمم، وقوالب إضافية */
(function () {
  var X = window.DZX; if (!X) return; var cv = X.cv, L = X.L, C = X.C, $ = function (id) { return document.getElementById(id); };
  var stage = $('dz-stage'), designId = 0, pages = [null], cur = 0, busy = false;
  function post(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* ---------- 1) خطوط إرشادية ولزق على النص والحواف والعناصر التانية ---------- */
  var guides = [];
  function edges(o) { var b = o.getBoundingRect(true, true); return { l: b.left, c: b.left + b.width / 2, r: b.left + b.width, t: b.top, m: b.top + b.height / 2, b: b.top + b.height, w: b.width, h: b.height }; }
  cv.on('object:moving', function (e) {
    var o = e.target, th = 7 / X.zoom, me = edges(o), W = X.W, H = X.H; guides = [];
    var xs = [0, W / 2, W], ys = [0, H / 2, H];
    cv.getObjects().forEach(function (x) { if (x === o || !x.visible || x.selectable === false || (o.type === 'activeSelection' && o.contains(x))) return; var e2 = edges(x); xs.push(e2.l, e2.c, e2.r); ys.push(e2.t, e2.m, e2.b); });
    var bx = null, by = null;
    [['l', 0], ['c', me.w / 2], ['r', me.w]].forEach(function (k) { xs.forEach(function (v) { var d = Math.abs(me[k[0]] - v); if (d < th && (!bx || d < bx.d)) bx = { d: d, v: v, off: k[1] }; }); });
    [['t', 0], ['m', me.h / 2], ['b', me.h]].forEach(function (k) { ys.forEach(function (v) { var d = Math.abs(me[k[0]] - v); if (d < th && (!by || d < by.d)) by = { d: d, v: v, off: k[1] }; }); });
    if (bx) { o.set('left', o.left + (bx.v - bx.off) - me.l); guides.push({ x: bx.v }); }
    if (by) { o.set('top', o.top + (by.v - by.off) - me.t); guides.push({ y: by.v }); }
    o.setCoords();
  });
  cv.on('mouse:up', function () { if (guides.length) { guides = []; cv.requestRenderAll(); } });
  cv.on('after:render', function () { if (!guides.length) return; var ctx = cv.getSelectionContext ? cv.contextTop : null; ctx = cv.contextTop; if (!ctx) return; cv.clearContext(ctx); var z = X.zoom;
    ctx.save(); ctx.strokeStyle = '#ec4899'; ctx.lineWidth = 1; ctx.setLineDash([4, 3]);
    guides.forEach(function (g) { ctx.beginPath(); if (g.x != null) { ctx.moveTo(g.x * z + 0.5, 0); ctx.lineTo(g.x * z + 0.5, X.H * z); } else { ctx.moveTo(0, g.y * z + 0.5); ctx.lineTo(X.W * z, g.y * z + 0.5); } ctx.stroke(); });
    ctx.restore(); });

  /* ---------- 2) تكبير بعجلة الماوس (Ctrl) وتحريك اللوحة بالمسطرة ---------- */
  stage.addEventListener('wheel', function (e) { if (!(e.ctrlKey || e.metaKey)) return; e.preventDefault(); X.setZoom(Math.max(0.05, Math.min(4, X.zoom * (e.deltaY < 0 ? 1.1 : 0.9)))); }, { passive: false });
  var space = false, pan = null;
  document.addEventListener('keydown', function (e) { if (e.code === 'Space' && !/INPUT|TEXTAREA/.test(document.activeElement.tagName) && !(cv.getActiveObject() || {}).isEditing) { if (!space) { space = true; stage.style.cursor = 'grab'; cv.defaultCursor = 'grab'; cv.selection = false; } e.preventDefault(); } });
  document.addEventListener('keyup', function (e) { if (e.code === 'Space') { space = false; stage.style.cursor = ''; cv.defaultCursor = 'default'; cv.selection = true; } });
  cv.on('mouse:down', function (e) { if (space) { pan = { x: e.e.clientX, y: e.e.clientY, sl: stage.scrollLeft, st: stage.scrollTop }; cv.discardActiveObject(); } });
  cv.on('mouse:move', function (e) { if (pan) { stage.scrollLeft = pan.sl - (e.e.clientX - pan.x); stage.scrollTop = pan.st - (e.e.clientY - pan.y); } });
  cv.on('mouse:up', function () { pan = null; });

  /* ---------- 3) المحاذاة والتوزيع والتجميع ---------- */
  function align(k) {
    var a = cv.getActiveObject(); if (!a) return; var W = X.W, H = X.H;
    if (a.type === 'activeSelection' && /^(left|hcenter|right|top|vcenter|bottom|dh|dv)$/.test(k)) {
      var objs = a.getObjects(), bb = a.getBoundingRect(true, true); cv.discardActiveObject();
      var es = objs.map(function (o) { return { o: o, e: edges(o) }; });
      if (k === 'dh' || k === 'dv') { var h = k === 'dh'; es.sort(function (p, q) { return h ? p.e.l - q.e.l : p.e.t - q.e.t; });
        var tot = es.reduce(function (s, p) { return s + (h ? p.e.w : p.e.h); }, 0), span = h ? bb.width : bb.height, gap = (span - tot) / Math.max(1, es.length - 1), pos = h ? bb.left : bb.top;
        es.forEach(function (p) { if (h) p.o.set('left', p.o.left + pos - p.e.l); else p.o.set('top', p.o.top + pos - p.e.t); pos += (h ? p.e.w : p.e.h) + gap; p.o.setCoords(); }); }
      else es.forEach(function (p) { var e = p.e, o = p.o;
        if (k === 'left') o.set('left', o.left + bb.left - e.l); if (k === 'right') o.set('left', o.left + bb.left + bb.width - e.r); if (k === 'hcenter') o.set('left', o.left + bb.left + bb.width / 2 - e.c);
        if (k === 'top') o.set('top', o.top + bb.top - e.t); if (k === 'bottom') o.set('top', o.top + bb.top + bb.height - e.b); if (k === 'vcenter') o.set('top', o.top + bb.top + bb.height / 2 - e.m); o.setCoords(); });
      cv.setActiveObject(new fabric.ActiveSelection(objs, { canvas: cv }));
    } else { var e = edges(a);
      if (k === 'left') a.set('left', a.left - e.l); if (k === 'right') a.set('left', a.left + W - e.r); if (k === 'hcenter') a.set('left', a.left + W / 2 - e.c);
      if (k === 'top') a.set('top', a.top - e.t); if (k === 'bottom') a.set('top', a.top + H - e.b); if (k === 'vcenter') a.set('top', a.top + H / 2 - e.m); a.setCoords(); }
    cv.requestRenderAll(); X.snap();
  }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-align]'); if (b) align(b.dataset.align); });
  function multi() { var a = cv.getActiveObject(), m = a && a.type === 'activeSelection'; $('dz-multi').hidden = !m; if (m) { $('dz-p').hidden = true; $('dz-none').hidden = true; $('dz-mn').textContent = a.getObjects().length; }
    $('dz-ungroup').classList.toggle('hidden', !(a && a.type === 'group' && a.dzRole !== 'ph'));
    var ls = $('dz-ls'); if (ls && a && a.type === 'textbox') { ls.hidden = X.isAr(a.text); $('pp-cs').value = a.charSpacing || 0; }
    if (a && a.type === 'image') { var f = a.dzF || {}; document.querySelectorAll('[data-flt]').forEach(function (r) { r.value = f[r.dataset.flt] || 0; }); } }
  ['selection:created', 'selection:updated', 'selection:cleared'].forEach(function (ev) { cv.on(ev, function () { setTimeout(multi, 0); }); });

  /* ---------- 4) تأثيرات النص ---------- */
  function fx(k) { var o = cv.getActiveObject(); if (!o || o.type !== 'textbox') return; var col = typeof o.fill === 'string' ? o.fill : X.S;
    o.set({ stroke: null, strokeWidth: 0, backgroundColor: '', padding: 4 }); if (o.dzFill) { o.set('fill', o.dzFill); delete o.dzFill; col = o.fill; }
    if (k === 'outline') o.set({ stroke: X.isAr(o.text) ? '#ffffff' : X.S, strokeWidth: Math.max(2, o.fontSize * 0.06), paintFirst: 'stroke', strokeLineJoin: 'round' });
    if (k === 'box') o.set({ backgroundColor: X.A, padding: o.fontSize * 0.35 });
    if (k === 'grad') { o.dzFill = col; o.set('fill', new fabric.Gradient({ type: 'linear', gradientUnits: 'percentage', coords: { x1: 0, y1: 0, x2: 1, y2: 1 }, colorStops: [{ offset: 0, color: X.P }, { offset: 1, color: X.A }] })); }
    cv.requestRenderAll(); X.snap(); }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-fx]'); if (b) fx(b.dataset.fx); });
  $('pp-cs').addEventListener('input', function () { var o = cv.getActiveObject(); if (o) { o.set('charSpacing', +this.value); cv.requestRenderAll(); } }); $('pp-cs').addEventListener('change', X.snap);

  /* ---------- 5) الصور: فلاتر، شكل، قلب، تبديل ---------- */
  var F = fabric.Image.filters;
  function filters(o) { var f = o.dzF || {}, list = [];
    if (f.br) list.push(new F.Brightness({ brightness: +f.br })); if (f.ct) list.push(new F.Contrast({ contrast: +f.ct })); if (f.sa) list.push(new F.Saturation({ saturation: +f.sa })); if (+f.bl) list.push(new F.Blur({ blur: +f.bl }));
    if (f.p === 'gray') list.push(new F.Grayscale()); if (f.p === 'sepia') list.push(new F.Sepia());
    if (f.p === 'warm') list.push(new F.ColorMatrix({ matrix: [1.1, 0, 0, 0, 0.03, 0, 1, 0, 0, 0.01, 0, 0, 0.85, 0, 0, 0, 0, 0, 1, 0] }));
    if (f.p === 'cool') list.push(new F.ColorMatrix({ matrix: [0.9, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1.12, 0, 0.03, 0, 0, 0, 1, 0] }));
    if (f.p === 'vivid') list.push(new F.Saturation({ saturation: 0.35 }), new F.Contrast({ contrast: 0.12 }));
    o.filters = list; o.applyFilters(); cv.requestRenderAll(); }
  document.querySelectorAll('[data-flt]').forEach(function (r) { r.addEventListener('input', function () { var o = cv.getActiveObject(); if (!o || o.type !== 'image') return; o.dzF = Object.assign({}, o.dzF, { [r.dataset.flt]: +r.value }); filters(o); }); r.addEventListener('change', X.snap); });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-preset]'); var o = cv.getActiveObject();
    if (b && o && o.type === 'image') { o.dzF = b.dataset.preset === 'none' ? {} : Object.assign({}, o.dzF, { p: b.dataset.preset }); filters(o); X.snap(); multi(); return; }
    b = e.target.closest('[data-mask]'); if (b && o && o.type === 'image') { var w = o.width, h = o.height, m = b.dataset.mask;
      o.clipPath = m === 'none' ? null : m === 'circle' ? new fabric.Circle({ radius: Math.min(w, h) / 2, originX: 'center', originY: 'center' }) : new fabric.Rect({ width: w, height: h, rx: Math.min(w, h) * 0.12, ry: Math.min(w, h) * 0.12, originX: 'center', originY: 'center' });
      o.dirty = true; cv.requestRenderAll(); X.snap(); }
  });
  var MORE = {
    flipx: function () { var o = cv.getActiveObject(); if (o) { o.set('flipX', !o.flipX); cv.requestRenderAll(); X.snap(); } },
    flipy: function () { var o = cv.getActiveObject(); if (o) { o.set('flipY', !o.flipY); cv.requestRenderAll(); X.snap(); } },
    replaceimg: function () { var o = cv.getActiveObject(); if (!o || !window.mediaPick) return; window.mediaPick({ accept: 'image', multiple: false }).then(function (fs) { if (!fs || !fs[0]) return;
      o.setSrc(fs[0].url, function () { if (o.dzF) filters(o); cv.requestRenderAll(); X.snap(); }, { crossOrigin: 'anonymous' }); }); },
    group: function () { var a = cv.getActiveObject(); if (a && a.type === 'activeSelection') { a.toGroup(); cv.requestRenderAll(); X.snap(); multi(); X.sync(); } },
    ungroup: function () { var a = cv.getActiveObject(); if (a && a.type === 'group') { a.toActiveSelection(); cv.requestRenderAll(); X.snap(); multi(); } }
  };

  /* ---------- 6) الصفحات (كاروسيل) ---------- */
  function pageThumb() { try { var z = X.zoom; return cv.toDataURL({ format: 'jpeg', quality: 0.6, multiplier: 120 / (X.W * z) }); } catch (e) { return ''; } }
  function storeCur() { pages[cur] = { j: X.json(), t: pageThumb() }; }
  function drawPages() { $('dz-pages').innerHTML = pages.map(function (p, i) { return '<div class="dz-pg' + (i === cur ? ' on' : '') + '" data-pg="' + i + '"><span class="dz-pg-n">' + (i + 1) + '</span>' + (p && p.t ? '<img src="' + p.t + '" alt="">' : '') +
      (pages.length > 1 ? '<button type="button" class="dz-pg-x" data-pgdel="' + i + '" title="' + esc(L.delPage) + '">×</button>' : '') + '<button type="button" class="dz-pg-d" data-pgdup="' + i + '" title="' + esc(L.dupPage) + '">⧉</button></div>'; }).join(''); }
  function go(i) { if (i === cur || busy) return; storeCur(); cur = i; var p = pages[i]; busy = true;
    var done = function () { busy = false; X.resetHist(); drawPages(); };
    if (p && p.j) X.load(p.j, done); else { X.setLock(true); cv.clear(); cv.setBackgroundColor('#ffffff', function () {}); X.setLock(false); cv.requestRenderAll(); done(); } }
  $('dz-pages').addEventListener('click', function (e) {
    var d = e.target.closest('[data-pgdel]'); if (d) { e.stopPropagation(); var i = +d.dataset.pgdel; if (!confirm(L.delPageQ)) return; storeCur(); pages.splice(i, 1); var n = Math.min(cur >= i ? Math.max(0, cur - 1) : cur, pages.length - 1); cur = -1; busy = false; cur = n; if (pages[n] && pages[n].j) X.load(pages[n].j, function () { X.resetHist(); drawPages(); }); else { X.setLock(true); cv.clear(); X.setLock(false); X.resetHist(); drawPages(); } return; }
    var u = e.target.closest('[data-pgdup]'); if (u) { e.stopPropagation(); storeCur(); var k = +u.dataset.pgdup; pages.splice(k + 1, 0, JSON.parse(JSON.stringify(pages[k]))); go(k + 1); return; }
    var p = e.target.closest('[data-pg]'); if (p) go(+p.dataset.pg); });
  MORE.addpage = function () { storeCur(); pages.splice(cur + 1, 0, null); go(cur + 1); };
  cv.on('after:render', function () { clearTimeout(drawPages.t); drawPages.t = setTimeout(function () { if (!busy) { pages[cur] = { j: pages[cur] && pages[cur].j, t: pageThumb() }; var im = document.querySelector('.dz-pg[data-pg="' + cur + '"] img'); if (im) im.src = pages[cur].t; else drawPages(); } }, 700); });

  /* ---------- 7) تصميماتي ---------- */
  function allPages() { storeCur(); return pages.map(function (p) { return p && p.j ? p.j : null; }).filter(Boolean); }
  function saveDesign() { var b = document.querySelector('[data-cmd="dsave"]'); b.disabled = true; var ps = allPages();
    post({ action: 'dsave', id: designId, name: $('dz-name').value, w: X.W, h: X.H, pages: ps.length, data: JSON.stringify({ v: 2, pages: ps }), thumb: (pages[0] && pages[0].t) || '' }).then(function (j) { b.disabled = false;
      if (!j.ok) return X.toast(j.error || L.err, 'error'); designId = j.id; X.toast(L.dsaved); try { localStorage.setItem('dz_last_id', designId); } catch (e) {} mine(); }).catch(function () { b.disabled = false; X.toast(L.err, 'error'); }); }
  function openDesign(id) { post({ action: 'dget', id: id }).then(function (j) { if (!j.ok) return X.toast(j.error || L.err, 'error'); var d = JSON.parse(j.data), ps = d.pages || [d];
    designId = j.id; pages = ps.map(function (s) { return { j: typeof s === 'string' ? s : JSON.stringify(s), t: '' }; }); cur = 0; busy = true;
    X.load(pages[0].j, function () { $('dz-name').value = j.name; busy = false; X.resetHist(); drawPages(); }); }); }
  function mine() { post({ action: 'dlist' }).then(function (j) { if (!j.ok) return; $('dz-mine').innerHTML = j.items.length ? j.items.map(function (d) {
      return '<div class="dz-dcard" data-dopen="' + d.id + '"><div class="dz-dthumb" style="aspect-ratio:' + d.w + '/' + d.h + (d.thumb ? ';background-image:url(' + d.thumb + ')' : '') + '"></div><b>' + esc(d.name) + '</b><small>' + esc(d.at) + (d.pages > 1 ? ' · ' + d.pages + ' ' + esc(L.pagesN) : '') + '</small><button type="button" class="dz-ddel" data-ddel="' + d.id + '" title="' + esc(L.del) + '">×</button></div>'; }).join('')
      : '<p class="col-span-2 text-xs text-ink-400 text-center py-6">' + esc(L.noMine) + '</p>'; }); }
  $('dz-mine').addEventListener('click', function (e) { var d = e.target.closest('[data-ddel]'); if (d) { e.stopPropagation(); if (!confirm(L.delQ)) return; post({ action: 'ddel', id: d.dataset.ddel }).then(function () { if (+d.dataset.ddel === designId) designId = 0; mine(); }); return; }
    var o = e.target.closest('[data-dopen]'); if (o) openDesign(+o.dataset.dopen); });
  MORE.dsave = saveDesign;
  MORE['new'] = function () { if (!confirm(L.newQ)) return; designId = 0; pages = [null]; cur = 0; $('dz-name').value = L.untitled; X.setLock(true); cv.clear(); cv.setBackgroundColor('#ffffff', function () {}); X.setLock(false); cv.requestRenderAll(); X.resetHist(); drawPages(); };

  /* ---------- 8) التصدير: PNG / JPG / كل الصفحات / PDF ---------- */
  // التحميل عن طريق Blob: روابط data الكبيرة المتصفح بيتجاهل اسم الملف معاها
  function dl(url, name) { fetch(url).then(function (r) { return r.blob(); }).then(function (bl) { var u = URL.createObjectURL(bl), a = document.createElement('a'); a.href = u; a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(u); }, 4000); }); }
  function exportJPG() { cv.discardActiveObject(); var z = X.zoom; cv.setZoom(1); cv.setDimensions({ width: X.W, height: X.H }); var u = cv.toDataURL({ format: 'jpeg', quality: 0.92 }); X.setZoom(z); return u; }
  function eachPage(fn, done) { storeCur(); var keep = cur, i = 0, out = [];
    (function next() { if (i >= pages.length) { busy = true; X.load(pages[keep].j, function () { cur = keep; busy = false; X.resetHist(); drawPages(); done(out); }); return; }
      var p = pages[i]; busy = true; X.load(p && p.j ? p.j : pages[keep].j, function () { out.push(fn(i)); i++; next(); }); })(); }
  function exportAs(k) { var nm = ($('dz-name').value || 'design').replace(/[\\/:*?"<>|]+/g, '');
    if (k === 'png') return dl(X.exportURL(), nm + '.png');
    if (k === 'jpg') return dl(exportJPG(), nm + '.jpg');
    if (k === 'all') return eachPage(function () { return X.exportURL(); }, function (urls) { urls.forEach(function (u, i) { setTimeout(function () { dl(u, nm + '-' + (i + 1) + '.png'); }, i * 350); }); });
    if (k === 'pdf') { if (!window.jspdf) return X.toast(L.err, 'error'); var W = X.W, H = X.H;
      eachPage(function () { return exportJPG(); }, function (urls) { var doc = new window.jspdf.jsPDF({ orientation: W > H ? 'l' : 'p', unit: 'px', format: [W, H], hotfixes: ['px_scaling'] });
        urls.forEach(function (u, i) { if (i) doc.addPage([W, H], W > H ? 'l' : 'p'); doc.addImage(u, 'JPEG', 0, 0, W, H); }); doc.save(nm + '.pdf'); }); } }
  $('dz-exp-b').addEventListener('click', function (e) { e.stopPropagation(); $('dz-exp').classList.toggle('hidden'); });
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-exp]'); if (b) { $('dz-exp').classList.add('hidden'); exportAs(b.dataset.exp); } else if (!e.target.closest('#dz-exp')) $('dz-exp').classList.add('hidden'); });

  /* ---------- 9) مقاس مخصص ---------- */
  var sizeSel = $('dz-size'), lastSize = sizeSel.value;
  sizeSel.addEventListener('change', function () { var c = this.value === 'custom'; $('dz-custom').classList.toggle('hidden', !c); $('dz-custom').classList.toggle('flex', c); if (c) { this.value = lastSize; } else lastSize = this.value; }, true);
  MORE.custom = function () { var w = Math.max(50, Math.min(5000, +$('dz-cw').value || 1080)), h = Math.max(50, Math.min(5000, +$('dz-ch').value || 1080)), v = w + 'x' + h;
    if (!sizeSel.querySelector('option[value="' + v + '"]')) { var o = document.createElement('option'); o.value = v; o.textContent = w + ' × ' + h; sizeSel.insertBefore(o, sizeSel.querySelector('option[value="custom"]')); }
    X.setSize(v, true); lastSize = v; X.snap(); $('dz-custom').classList.add('hidden'); $('dz-custom').classList.remove('flex'); };

  /* ---------- 10) الذكاء الاصطناعي جوه المصمم ---------- */
  function btnBusy(b, on) { b.disabled = on; b.classList.toggle('animate-pulse', on); }
  MORE.aidesign = function () { var b = document.querySelector('[data-cmd="aidesign"]'); btnBusy(b, true);
    post({ action: 'ai', mode: 'design', topic: $('dz-ai-topic').value }).then(function (j) { btnBusy(b, false); if (!j.ok) return X.toast(j.error || L.err, 'error');
      var map = { offer: 0, product: 1, quote: 2, edu: 4, event: 7, story: 11 }, i = map[j.kind] != null ? map[j.kind] : 0; C.title = j.title; C.text = j.sub;
      var keep = window.confirm; window.confirm = function () { return true; }; X.useTpl(i); window.confirm = keep; C.title = ''; C.text = '';
      if (j.cta) cv.getObjects().forEach(function (o) { if (o.type === 'group') o.getObjects().forEach(function (t) { if (t.type === 'textbox' && (t.text === L.cta || t.text === L.orderWa)) { t.set('text', j.cta); } }); });
      cv.requestRenderAll(); X.snap(); X.toast(L.aiDone); }).catch(function () { btnBusy(b, false); X.toast(L.err, 'error'); }); };
  MORE.aihead = function () { var o = cv.getActiveObject(), b = document.querySelector('[data-cmd="aihead"]'); if (!o || o.type !== 'textbox') return X.toast(L.pickText, 'error'); btnBusy(b, true);
    post({ action: 'ai', mode: 'headline', topic: $('dz-ai-topic').value, current: o.text }).then(function (j) { btnBusy(b, false); if (!j.ok) return X.toast(j.error || L.err, 'error');
      $('dz-heads').innerHTML = j.items.map(function (h) { return '<button type="button" class="dz-add !mb-0 text-sm" data-head="' + esc(h) + '" dir="auto">' + esc(h) + '</button>'; }).join(''); }).catch(function () { btnBusy(b, false); }); };
  $('dz-heads').addEventListener('click', function (e) { var b = e.target.closest('[data-head]'), o = cv.getActiveObject(); if (!b || !o || o.type !== 'textbox') return; o.set('text', b.dataset.head); o.initDimensions(); cv.requestRenderAll(); X.snap(); });
  MORE.aiimg = function () { var pr = $('dz-ai-img').value.trim(), b = document.querySelector('[data-cmd="aiimg"]'), st = $('dz-ai-st'); if (!pr) return $('dz-ai-img').focus();
    var target = cv.getActiveObject(); btnBusy(b, true); st.textContent = L.genBusy;
    var call = function (d) { var f = new FormData(); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch('index.php?p=ai_task', { method: 'POST', body: f, headers: { 'X-CSRF-Token': C.csrf }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); };
    var ar = X.W === X.H ? '1:1' : X.H > X.W * 1.5 ? '9:16' : X.H > X.W ? '4:5' : '16:9';
    call({ task: 'image', text: pr, aspect: ar }).then(function (d) { if (d.error) { btnBusy(b, false); st.textContent = d.error; return; }
      (function poll(n) { call({ task: 'image_status', id: d.id }).then(function (s) {
        if (s.error) { btnBusy(b, false); st.textContent = s.error; return; }
        if (s.status === 'completed') { btnBusy(b, false); st.textContent = L.genDone; var im = (s.images || [])[0]; if (!im) return; if (target && target.dzRole === 'ph') X.fillPh(target, im.src); else X.addImg(im.src); return; }
        if (n > 80) { btnBusy(b, false); st.textContent = L.err; return; } setTimeout(function () { poll(n + 1); }, 3000); }).catch(function () { setTimeout(function () { poll(n + 1); }, 5000); }); })(0);
    }).catch(function () { btnBusy(b, false); st.textContent = L.err; }); };

  /* ---------- 11) قوالب إضافية (لحد 30) من قاعدة المعرفة ---------- */
  var T = X.T, TC = X.TC, pill = X.pill, R = X.R, circ = X.circ, ph = X.ph, grad = X.grad, P = X.P, S = X.S, A = X.A, K = X.K, SV = X.SVS[0] || {}, SV2 = X.SVS[1] || SV, SV3 = X.SVS[2] || SV2, CO = X.CO, WA = X.WA, FOOT = X.FOOT;
  var lt = 'rgba(255,255,255,.82)', w = function () { return X.W; }, h = function () { return X.H; };
  var MORE_TPL = [
    { n: L.x1, cat: 'story', size: '1080x1920', b: function () { var W = w(), H = h(); return { bg: '#000', objs: [ph(0, 0, W, H), R(0, H * 0.55, W, H * 0.45, '', { fill: new fabric.Gradient({ type: 'linear', coords: { x1: 0, y1: 0, x2: 0, y2: H * 0.45 }, colorStops: [{ offset: 0, color: 'rgba(0,0,0,0)' }, { offset: 1, color: 'rgba(0,0,0,.85)' }] }) }),
      T(C.title || (SV.name ? SV.name : L.h1), { top: H * 0.7, fontSize: W * 0.1, fill: '#fff', fontWeight: 900 }), T(K.tagline || '', { top: H * 0.82, fontSize: W * 0.04, fill: lt }), pill('▶  ' + L.watch, W * 0.08, H * 0.9, A, S, W * 0.035)] }; } },
    { n: L.x2, cat: 'offer', size: '1200x628', b: function () { var W = w(), H = h(); return { bg: '#fff', objs: [ph(W * 0.5, 0, W * 0.5, H), R(0, 0, W * 0.55, H, P), new fabric.Triangle({ left: W * 0.55, top: 0, width: H * 0.25, height: H, fill: P, angle: 0, flipY: true }),
      T(L.offer, { top: H * 0.16, fontSize: H * 0.07, fill: A, fontWeight: 700, left: W * 0.05, width: W * 0.44 }), T(SV.name || L.h1, { top: H * 0.27, fontSize: H * 0.13, fill: '#fff', fontWeight: 900, left: W * 0.05, width: W * 0.44 }),
      T(SV.price || '', { top: H * 0.58, fontSize: H * 0.08, fill: lt, left: W * 0.05, width: W * 0.44 }), pill(WA ? L.orderWa : L.cta, W * 0.05, H * 0.75, A, S, H * 0.05)] }; } },
    { n: L.x3, cat: 'season', size: '1080x1080', b: function () { var W = w(), H = h(), o = [circ(W * 0.72, H * 0.2, W * 0.13, '#fcd34d'), circ(W * 0.77, H * 0.17, W * 0.12, '#0b1a3a')];
      for (var i = 0; i < 9; i++) o.push(TC('✦', { left: W * (0.08 + (i * 37 % 84) / 100) - W * 0.42, top: H * (0.08 + (i * 53 % 40) / 100), fontSize: W * 0.025, fill: '#fde68a', fontFamily: 'Inter' }));
      o.push(TC(L.ramadan, { top: H * 0.42, fontSize: W * 0.12, fill: '#fde68a', fontFamily: 'Amiri', fontWeight: 700 }), TC(L.ramadanSub, { top: H * 0.62, fontSize: W * 0.04, fill: lt }), TC(CO, { top: H * 0.82, fontSize: W * 0.04, fill: '#fde68a', fontWeight: 700 }));
      return { bg: grad(['#0b1a3a', '#1e1b4b']), objs: o }; } },
    { n: L.x4, cat: 'season', size: '1080x1080', b: function () { var W = w(), H = h(); return { bg: grad(['#fff7ed', '#fde68a']), objs: [circ(W * 0.5, H * 0.32, W * 0.22, A, { opacity: 0.25 }), TC('🌙', { top: H * 0.2, fontSize: W * 0.15, fontFamily: 'Inter' }),
      TC(L.eid, { top: H * 0.45, fontSize: W * 0.12, fill: S, fontFamily: 'Amiri', fontWeight: 700 }), TC(L.greetSub, { top: H * 0.64, fontSize: W * 0.04, fill: '#78350f' }), TC(CO, { top: H * 0.82, fontSize: W * 0.04, fill: P, fontWeight: 900 })] }; } },
    { n: L.x5, cat: 'season', size: '1080x1080', b: function () { var W = w(), H = h(); return { bg: '#065f46', objs: [R(0, H * 0.82, W, H * 0.18, '#047857'), circ(W * 0.5, H * 0.3, W * 0.16, '#fff', { opacity: 0.12 }),
      TC('🇸🇦', { top: H * 0.17, fontSize: W * 0.16, fontFamily: 'Twemoji Country Flags, Inter' }), TC(L.national, { top: H * 0.43, fontSize: W * 0.09, fill: '#fff', fontWeight: 900 }), TC(L.nationalSub, { top: H * 0.6, fontSize: W * 0.04, fill: lt }), TC(CO, { top: H * 0.86, fontSize: W * 0.04, fill: '#fff', fontWeight: 700 })] }; } },
    { n: L.x6, cat: 'offer', size: '1080x1080', b: function () { var W = w(), H = h(); return { bg: '#111827', objs: [R(W * 0.06, H * 0.06, W * 0.88, H * 0.88, '', { stroke: A, strokeWidth: 6, strokeDashArray: [24, 16], rx: 30, ry: 30 }),
      TC(L.weekend, { top: H * 0.16, fontSize: W * 0.06, fill: A, fontWeight: 700 }), TC(SV.name || L.off, { top: H * 0.27, fontSize: W * 0.1, fill: '#fff', fontWeight: 900 }), TC(SV.price || '', { top: H * 0.52, fontSize: W * 0.09, fill: A, fontWeight: 900 }),
      TC(L.fri, { top: H * 0.66, fontSize: W * 0.04, fill: lt }), TC(FOOT || CO, { top: H * 0.84, fontSize: W * 0.03, fill: lt })] }; } },
    { n: L.x7, cat: 'edu', size: '1080x1080', b: function () { var W = w(), H = h(); return { bg: '#ecfeff', objs: [TC('🛵', { top: H * 0.1, fontSize: W * 0.16, fontFamily: 'Inter' }), TC(L.delivery, { top: H * 0.32, fontSize: W * 0.08, fontWeight: 900, fill: S }),
      TC((FAQ0() || {}).a || K.tagline || '', { top: H * 0.46, fontSize: W * 0.045, fill: '#155e75', left: W * 0.1, width: W * 0.8 }), pill(WA ? L.orderWa : L.cta, W * 0.5, H * 0.72, P, '#fff', W * 0.035, true), TC(FOOT || CO, { top: H * 0.88, fontSize: W * 0.028, fill: '#64748b' })] }; } },
    { n: L.x8, cat: 'product', size: '1080x1350', b: function () { var W = w(), H = h(), o = [TC(L.combo, { top: H * 0.06, fontSize: W * 0.07, fontWeight: 900, fill: P })];
      [SV, SV2, SV3].forEach(function (s, i) { var x = W * (0.06 + i * 0.31); o.push(ph(x, H * 0.18, W * 0.27, W * 0.27, { circle: true }), TC(s.name || L.item, { left: x - W * 0.02, width: W * 0.31, top: H * 0.43, fontSize: W * 0.032, fontWeight: 700 })); });
      o.push(TC('+', { top: H * 0.24, left: W * 0.24, width: W * 0.2, fontSize: W * 0.08, fill: A, fontWeight: 900, fontFamily: 'Inter' }), TC('+', { top: H * 0.24, left: W * 0.55, width: W * 0.2, fontSize: W * 0.08, fill: A, fontWeight: 900, fontFamily: 'Inter' }),
        R(W * 0.1, H * 0.58, W * 0.8, H * 0.2, S, { rx: 40, ry: 40 }), TC(L.together, { top: H * 0.61, fontSize: W * 0.04, fill: lt }), TC(L.special, { top: H * 0.67, fontSize: W * 0.075, fill: A, fontWeight: 900 }), TC(FOOT || CO, { top: H * 0.9, fontSize: W * 0.028, fill: '#64748b' }));
      return { bg: '#fff', objs: o }; } },
    { n: L.x9, cat: 'quote', size: '1080x1080', b: function () { var W = w(), H = h(); return { bg: P, objs: [R(W * 0.08, H * 0.08, W * 0.84, H * 0.84, '#fff', { rx: 50, ry: 50 }), TC('⭐', { top: H * 0.16, fontSize: W * 0.12, fontFamily: 'Inter' }),
      TC(L.review, { top: H * 0.33, fontSize: W * 0.075, fontWeight: 900 }), TC(L.reviewSub, { top: H * 0.5, fontSize: W * 0.04, fill: '#475569', left: W * 0.16, width: W * 0.68 }), pill(L.reviewCta, W * 0.5, H * 0.72, S, '#fff', W * 0.035, true), TC(CO, { top: H * 0.84, fontSize: W * 0.03, fill: P, fontWeight: 700 })] }; } },
    { n: L.x10, cat: 'edu', size: '1080x1350', b: function () { var W = w(), H = h(), rows = [['📍', K.branch], ['📞', K.phone || K.whatsapp], ['💬', K.whatsapp], ['📷', K.handle ? '@' + K.handle : ''], ['🕛', (K.hours || '').split('\n')[0]]].filter(function (r) { return r[1]; }), o = [R(0, 0, W, H * 0.3, P), TC(CO, { top: H * 0.1, fontSize: W * 0.08, fill: '#fff', fontWeight: 900 }), TC(K.tagline || '', { top: H * 0.2, fontSize: W * 0.032, fill: lt, left: W * 0.1, width: W * 0.8 })];
      rows.forEach(function (r, i) { var y = H * (0.38 + i * 0.11); o.push(circ(W * 0.86, y + W * 0.04, W * 0.05, '#eef2ff'), TC(r[0], { left: W * 0.81, width: W * 0.1, top: y + W * 0.012, fontSize: W * 0.04, fontFamily: 'Inter' }), T(r[1], { top: y + W * 0.015, width: W * 0.68, fontSize: W * 0.04, fontWeight: 700, left: W * 0.08 })); });
      return { bg: '#fff', objs: o }; } },
    { n: L.x11, cat: 'event', size: '1080x1080', b: function () { var W = w(), H = h(); return { bg: grad([A, '#f97316']), objs: [TC('🎉', { top: H * 0.12, fontSize: W * 0.14, fontFamily: 'Inter' }), TC(L.opening, { top: H * 0.32, fontSize: W * 0.11, fill: '#fff', fontWeight: 900 }),
      TC(K.branch || L.place, { top: H * 0.52, fontSize: W * 0.045, fill: '#fff', left: W * 0.1, width: W * 0.8 }), pill(L.date, W * 0.5, H * 0.68, '#fff', S, W * 0.04, true), TC(CO, { top: H * 0.85, fontSize: W * 0.04, fill: '#fff', fontWeight: 900 })] }; } },
    { n: L.x12, cat: 'offer', size: '1080x1080', b: function () { var W = w(), H = h(); return { bg: '#0f172a', objs: [TC(L.endsIn, { top: H * 0.14, fontSize: W * 0.05, fill: A, fontWeight: 700 }),
      R(W * 0.12, H * 0.27, W * 0.22, W * 0.22, '#1e293b', { rx: 24, ry: 24 }), R(W * 0.39, H * 0.27, W * 0.22, W * 0.22, '#1e293b', { rx: 24, ry: 24 }), R(W * 0.66, H * 0.27, W * 0.22, W * 0.22, '#1e293b', { rx: 24, ry: 24 }),
      TC('02', { left: W * 0.66, width: W * 0.22, top: H * 0.3, fontSize: W * 0.1, fill: '#fff', fontWeight: 900, fontFamily: 'Inter' }), TC('14', { left: W * 0.39, width: W * 0.22, top: H * 0.3, fontSize: W * 0.1, fill: '#fff', fontWeight: 900, fontFamily: 'Inter' }), TC('37', { left: W * 0.12, width: W * 0.22, top: H * 0.3, fontSize: W * 0.1, fill: '#fff', fontWeight: 900, fontFamily: 'Inter' }),
      TC(L.days, { left: W * 0.66, width: W * 0.22, top: H * 0.43, fontSize: W * 0.03, fill: lt }), TC(L.hours, { left: W * 0.39, width: W * 0.22, top: H * 0.43, fontSize: W * 0.03, fill: lt }), TC(L.mins, { left: W * 0.12, width: W * 0.22, top: H * 0.43, fontSize: W * 0.03, fill: lt }),
      TC(SV.name ? SV.name + (SV.price ? ' — ' + SV.price : '') : L.off, { top: H * 0.6, fontSize: W * 0.06, fill: '#fff', fontWeight: 900 }), pill(WA ? L.orderWa : L.cta, W * 0.5, H * 0.76, A, S, W * 0.035, true), TC(FOOT || CO, { top: H * 0.9, fontSize: W * 0.026, fill: lt })] }; } },
    { n: L.x13, cat: 'offer', size: '1080x1080', b: function () { var W = w(), H = h(); return { bg: '#fef2f2', objs: [R(W * 0.1, H * 0.25, W * 0.8, H * 0.42, '#fff', { rx: 30, ry: 30, stroke: P, strokeWidth: 4, strokeDashArray: [18, 12] }),
      circ(W * 0.1, H * 0.46, W * 0.05, '#fef2f2'), circ(W * 0.9, H * 0.46, W * 0.05, '#fef2f2'), TC(L.coupon, { top: H * 0.31, fontSize: W * 0.045, fill: '#64748b' }), TC('SAVE20', { top: H * 0.39, fontSize: W * 0.12, fill: P, fontWeight: 900, fontFamily: 'Inter' }),
      TC(L.couponSub, { top: H * 0.56, fontSize: W * 0.035, fill: S }), TC(L.offer, { top: H * 0.1, fontSize: W * 0.065, fontWeight: 900, fill: S }), TC(FOOT || CO, { top: H * 0.82, fontSize: W * 0.03, fill: '#64748b' })] }; } },
    { n: L.x14, cat: 'quote', size: '1080x1080', b: function () { var W = w(), H = h(); return { bg: grad([P, '#7c3aed']), objs: [TC('💛', { top: H * 0.16, fontSize: W * 0.14, fontFamily: 'Inter' }), TC(L.thanks, { top: H * 0.38, fontSize: W * 0.1, fill: '#fff', fontWeight: 900 }),
      TC(L.thanksSub, { top: H * 0.55, fontSize: W * 0.042, fill: lt, left: W * 0.12, width: W * 0.76 }), TC(CO + (FOOT ? '\n' + FOOT : ''), { top: H * 0.78, fontSize: W * 0.032, fill: '#fff', fontWeight: 700 })] }; } }
  ];
  function FAQ0() { return (K.faq || []).filter(function (q) { return /توصيل|delivery/i.test(q.q + q.a); })[0] || (K.faq || [])[0]; }
  MORE_TPL.forEach(function (t) { X.TPL.push(t); }); if (document.querySelector('.dz-tpl')) X.thumbs(); // لو المعاينات اترسمت قبل الإضافة

  /* ---------- الأوامر والبداية ---------- */
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-cmd]'); if (b && MORE[b.dataset.cmd]) { e.stopImmediatePropagation(); MORE[b.dataset.cmd](); } }, true);
  document.addEventListener('keydown', function (e) { var o = cv.getActiveObject(); if (/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) || (o && o.isEditing)) return;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); e.stopImmediatePropagation(); saveDesign(); }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'g') { e.preventDefault(); e.shiftKey ? MORE.ungroup() : MORE.group(); } }, true);
  document.querySelectorAll('.dz-rail [data-tab="mine"]').forEach(function (b) { b.addEventListener('click', mine); });
  drawPages(); mine();
  window.dzPro = { pages: function () { return pages; }, go: go, saveDesign: saveDesign, openDesign: openDesign, exportAs: exportAs, align: align, id: function () { return designId; } };
})();
