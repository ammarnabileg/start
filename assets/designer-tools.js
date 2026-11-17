/* أدوات احترافية في لوحة الخصائص: موضع وحجم ودوران بالأرقام، دمج الطبقات، قص الصورة، ومزامنة الفلاتر مع العنصر المختار */
(function () {
  var X = window.DZX; if (!X) return; var cv = X.cv;
  function act() { return cv.getActiveObject(); }
  function fill() { var o = act(); if (!o) return; var w = Math.round(o.getScaledWidth()), h = Math.round(o.getScaledHeight());
    document.querySelectorAll('[data-geo]').forEach(function (i) { if (document.activeElement === i) return; var k = i.dataset.geo;
      if (k === 'x') i.value = Math.round(o.left); else if (k === 'y') i.value = Math.round(o.top); else if (k === 'w') i.value = w; else if (k === 'h') i.value = h; else if (k === 'r') i.value = Math.round(o.angle || 0); });
    var b = document.getElementById('pp-blend'); if (b) b.value = o.globalCompositeOperation || 'source-over';
    if (o.type === 'image') { var f = o.dzF || {}; document.querySelectorAll('[data-flt]').forEach(function (r) { r.value = f[r.dataset.flt] != null ? f[r.dataset.flt] : (r.dataset.flt === 'px' ? 1 : 0); });
      var c = o.dzCrop || {}; document.querySelectorAll('[data-crop]').forEach(function (r) { r.value = c[r.dataset.crop] || 0; }); } }
  ['selection:created', 'selection:updated', 'object:modified', 'object:moving', 'object:scaling', 'object:rotating'].forEach(function (ev) { cv.on(ev, function () { setTimeout(fill, 0); }); });
  document.addEventListener('change', function (e) { var i = e.target.closest('[data-geo]'); if (!i) return; var o = act(); if (!o || i.dataset.geo === 'lockr') return;
    var v = parseFloat(i.value); if (isNaN(v)) return fill(); var k = i.dataset.geo, lock = (document.querySelector('[data-geo="lockr"]') || {}).value !== '0';
    if (k === 'x') o.set('left', v); else if (k === 'y') o.set('top', v); else if (k === 'r') o.rotate(v % 360);
    else if (k === 'w' && v > 0) { var sx = v / o.width, ra = o.scaleY / o.scaleX; o.set(lock ? { scaleX: sx, scaleY: sx * ra } : { scaleX: sx }); }
    else if (k === 'h' && v > 0) { var sy = v / o.height, rb = o.scaleX / o.scaleY; o.set(lock ? { scaleY: sy, scaleX: sy * rb } : { scaleY: sy }); }
    o.setCoords(); cv.requestRenderAll(); X.snap(); fill(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.closest && e.target.closest('[data-geo]')) e.target.blur(); });
  var bl = document.getElementById('pp-blend'); if (bl) bl.addEventListener('change', function () { var o = act(); if (!o) return; o.set('globalCompositeOperation', this.value); cv.requestRenderAll(); X.snap(); });
  // القص: نسب من أطراف الصورة الأصلية
  document.querySelectorAll('[data-crop]').forEach(function (r) { r.addEventListener('input', function () { var o = act(); if (!o || o.type !== 'image') return; var el = o.getElement(); if (!el) return;
      var c = Object.assign({ l: 0, r: 0, t: 0, b: 0 }, o.dzCrop || {}); c[r.dataset.crop] = +r.value; var ow = el.naturalWidth || el.width, oh = el.naturalHeight || el.height;
      var cx = ow * c.l / 100, cy = oh * c.t / 100; o.set({ cropX: cx, cropY: cy, width: Math.max(10, ow - cx - ow * c.r / 100), height: Math.max(10, oh - cy - oh * c.b / 100) }); o.dzCrop = c;
      if (o.clipPath) { o.clipPath.set(o.clipPath.type === 'circle' ? { radius: Math.min(o.width, o.height) / 2 } : { width: o.width, height: o.height }); }
      o.dirty = true; o.setCoords(); cv.requestRenderAll(); });
    r.addEventListener('change', function () { X.snap(); }); });
})();

/* المصمم بياخد ارتفاع الشاشة بالظبط، والضغط على أي عنصر مايحرّكش الصفحة */
(function () {
  var sh = document.querySelector('.dz-shell'); if (!sh) return;
  function fit() { var top = sh.getBoundingClientRect().top + window.scrollY; var h = Math.max(520, window.innerHeight - top - 16); sh.style.height = h + 'px'; }
  fit(); window.addEventListener('resize', fit); setTimeout(fit, 300);
  var keepY = null;
  // الأماكن اللي بتسبب قفزة: الضغط على اللوحة، تحديد عنصر، الطبقات، تعديل النص (textarea مخفي بيتحط مكان المؤشر)
  document.addEventListener('pointerdown', function (e) { keepY = e.pointerType !== 'touch' && e.target.closest('.dz-shell') ? window.scrollY : null; }, true); window.addEventListener('scroll', function () { if (keepY != null && !document.querySelector('.dz-shell:hover')) keepY = null; }, { passive: true });
  function hold() { if (keepY == null) return; var y = keepY; [0, 50, 150].forEach(function (t) { setTimeout(function () { if (Math.abs(window.scrollY - y) > 2) window.scrollTo({ top: y, behavior: 'instant' }); }, t); }); }
  document.addEventListener('pointerup', function (e) { if (e.target.closest && e.target.closest('.dz-shell')) hold(); else keepY = null; }, true); document.addEventListener('focusin', function (e) { if (e.target.closest('.dz-shell') || e.target.tagName === 'TEXTAREA') hold(); }, true);
  if (window.DZX && window.DZX.cv) ['selection:created', 'selection:updated', 'text:editing:entered'].forEach(function (ev) { window.DZX.cv.on(ev, hold); });
  window.addEventListener('wheel', function () { keepY = null; }, { passive: true }); window.addEventListener('keydown', function (e) { if (/Page|Arrow|Home|End/.test(e.key) && !(document.activeElement && /INPUT|TEXTAREA/.test(document.activeElement.tagName))) keepY = null; });
})();


/* إزالة الخلفية: سريعة في المتصفح (خلفية سادة) أو بالذكاء الاصطناعي */
(function () {
  var X = window.DZX; if (!X) return; var cv = X.cv, C = X.C || window.DZ || {}, L = (C.L || {});
  function img() { var o = cv.getActiveObject(); return o && o.type === 'image' ? o : null; }
  function st(t) { var e = document.getElementById('dz-rembg-st'); if (e) e.textContent = t || ''; }
  function srcCanvas(o) { var el = o._originalElement || o.getElement(), cx = o.cropX || 0, cy = o.cropY || 0, w = Math.round(o.width), h = Math.round(o.height), c = document.createElement('canvas'); c.width = w; c.height = h; c.getContext('2d').drawImage(el, cx, cy, w, h, 0, 0, w, h); return c; } // الجزء الظاهر بس (بعد القص)
  function put(o, url, cb) { var keep = { left: o.left, top: o.top, sx: o.getScaledWidth(), sy: o.getScaledHeight() };
    o.setSrc(url, function () { o.set({ scaleX: keep.sx / o.width, scaleY: keep.sy / o.height, cropX: 0, cropY: 0 }); o.dzCrop = null; if (o.filters && o.filters.length) o.applyFilters(); o.setCoords(); cv.requestRenderAll(); X.snap(); cb && cb(); }, { crossOrigin: 'anonymous' }); }
  function quick(o) { var c; try { c = srcCanvas(o); var g = c.getContext('2d'), w = c.width, h = c.height, d = g.getImageData(0, 0, w, h), px = d.data; } catch (e) { return X.toast(L.rembgCors || 'تعذر قراءة الصورة. احفظها في المكتبة ثم أعد المحاولة.', 'error'); }
    // لون الخلفية = متوسط الأركان، وبعدين ملء من كل الأطراف للبكسلات القريبة منه
    var cs = [[0, 0], [w - 1, 0], [0, h - 1], [w - 1, h - 1]].map(function (p) { var i = (p[1] * w + p[0]) * 4; return [px[i], px[i + 1], px[i + 2]]; });
    var br = 0, bg = 0, bb = 0; cs.forEach(function (x) { br += x[0]; bg += x[1]; bb += x[2]; }); br /= 4; bg /= 4; bb /= 4;
    var tol = 48, seen = new Uint8Array(w * h), stack = [];
    for (var x = 0; x < w; x++) { stack.push(x, (h - 1) * w + x); } for (var y = 0; y < h; y++) { stack.push(y * w, y * w + w - 1); }
    var near = function (i) { var k = i * 4; return Math.abs(px[k] - br) + Math.abs(px[k + 1] - bg) + Math.abs(px[k + 2] - bb) < tol * 1.6 && px[k + 3] > 10; };
    var n = 0; while (stack.length) { var i = stack.pop(); if (seen[i]) continue; seen[i] = 1; if (!near(i)) continue; px[i * 4 + 3] = 0; n++;
      var cx = i % w, cy = (i / w) | 0; if (cx > 0) stack.push(i - 1); if (cx < w - 1) stack.push(i + 1); if (cy > 0) stack.push(i - w); if (cy < h - 1) stack.push(i + w); }
    if (n < w * h * 0.03) return X.toast(L.rembgNone || 'الخلفية ليست سادة. استخدم الإزالة بالذكاء الاصطناعي.', 'error');
    // حواف أنعم: البكسل الملاصق للشفاف ياخد شفافية جزئية
    for (var j = 0; j < w * h; j++) { if (px[j * 4 + 3] === 0) continue; var cx2 = j % w, cy2 = (j / w) | 0, t = 0; if (cx2 > 0 && px[(j - 1) * 4 + 3] === 0) t++; if (cx2 < w - 1 && px[(j + 1) * 4 + 3] === 0) t++; if (cy2 > 0 && px[(j - w) * 4 + 3] === 0) t++; if (cy2 < h - 1 && px[(j + w) * 4 + 3] === 0) t++; if (t) px[j * 4 + 3] = Math.round(px[j * 4 + 3] * (1 - t * 0.2)); }
    g.putImageData(d, 0, 0); put(o, c.toDataURL('image/png'), function () { X.toast(L.rembgDone || '✓'); }); }
  function ai(o, b) { var c; try { c = srcCanvas(o); c.toDataURL(); } catch (e) { return X.toast(L.rembgCors || 'تعذر قراءة الصورة.', 'error'); }
    b.disabled = true; b.classList.add('animate-pulse'); st(L.rembgBusy || '…');
    c.toBlob(function (blob) { var f = new FormData(); f.append('action', 'rembg'); f.append('_csrf', C.csrf); f.append('file', blob, 'img.png');
      fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-CSRF-Token': C.csrf } }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) { st(''); X.toast(j.error || '✗', 'error'); return; } put(o, j.url, function () { st(''); X.toast(L.rembgDone || '✓'); }); })
      .catch(function () { st(''); X.toast('✗', 'error'); }).finally(function () { b.disabled = false; b.classList.remove('animate-pulse'); }); }, 'image/png'); }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-cmd="rembg-quick"],[data-cmd="rembg-ai"]'); if (!b) return; e.stopImmediatePropagation(); var o = img(); if (!o) return;
    if (b.dataset.cmd === 'rembg-quick') quick(o); else ai(o, b); }, true);
})();
