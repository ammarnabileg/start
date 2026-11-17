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
  document.addEventListener('pointerdown', function (e) { if (e.target.closest('.dz-shell')) keepY = window.scrollY; }, true);
  function hold() { if (keepY == null) return; var y = keepY; [0, 50, 150].forEach(function (t) { setTimeout(function () { if (Math.abs(window.scrollY - y) > 2) window.scrollTo({ top: y, behavior: 'instant' }); }, t); }); }
  document.addEventListener('pointerup', hold, true); document.addEventListener('focusin', function (e) { if (e.target.closest('.dz-shell') || e.target.tagName === 'TEXTAREA') hold(); }, true);
  if (window.DZX && window.DZX.cv) ['selection:created', 'selection:updated', 'text:editing:entered'].forEach(function (ev) { window.DZX.cv.on(ev, hold); });
  window.addEventListener('wheel', function () { keepY = null; }, { passive: true }); window.addEventListener('keydown', function (e) { if (/Page|Arrow|Home|End/.test(e.key) && !(document.activeElement && /INPUT|TEXTAREA/.test(document.activeElement.tagName))) keepY = null; });
})();
