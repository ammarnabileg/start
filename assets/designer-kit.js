/* المصمم: هوية الشركة (لوجو، ألوان، خطوط العناوين والنص، خطوط مرفوعة، لوجو تلقائي)، تغيير المقاس الذكي (إعادة ترتيب حقيقية)،
 * صور مجانية من Pexels، وأيقونات ملونة (ملونة/مصمتة) وإيموجي */
(function () {
  var X = window.DZX; if (!X) return; var cv = X.cv, L = X.L, C = X.C, $ = function (id) { return document.getElementById(id); };
  var kit = C.kit || { colors: [], head: '', body: '', logo: '', auto: false, fonts: [] };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function post(d, file) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); if (file) f.append('file', file); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

  /* ---------- 1) خطوط الشركة المرفوعة: @font-face + تظهر في كل قوايم الخطوط ---------- */
  var st = document.createElement('style'); document.head.appendChild(st);
  function addFont(f) { st.textContent += "@font-face{font-family:'" + f.name.replace(/'/g, '') + "';src:url('" + f.url + "');font-display:swap}\n";
    if (C.fonts.indexOf(f.name) < 0) C.fonts.unshift(f.name);
    var sel = $('pp-font'); if (sel && !sel.querySelector('option[value="' + f.name + '"]')) { var o = document.createElement('option'); o.value = f.name; o.textContent = f.name; o.style.fontFamily = "'" + f.name + "'"; sel.insertBefore(o, sel.firstChild); } }
  (kit.fonts || []).forEach(addFont);

  /* ---------- 2) محرر الهوية ---------- */
  function fontsSel(id, val) { var s = $(id); s.innerHTML = '<option value="">' + esc(L.defFont) + '</option>' + C.fonts.map(function (f) { return '<option value="' + esc(f) + '"' + (f === val ? ' selected' : '') + ' style="font-family:\'' + esc(f) + '\'">' + esc(f) + '</option>'; }).join(''); }
  function drawKit() {
    $('kit-logo').innerHTML = kit.logo ? '<img src="' + esc(kit.logo) + '" class="max-w-full max-h-full object-contain">' : esc(L.noLogo);
    var cols = kit.colors && kit.colors.length ? kit.colors : [X.P, X.S, X.A];
    $('kit-cols').innerHTML = cols.map(function (c, i) { return '<span class="relative"><input type="color" value="' + esc(c) + '" data-kc="' + i + '" class="w-9 h-9 rounded-lg border border-ink-200 cursor-pointer"><button type="button" data-kcx="' + i + '" class="absolute -top-1.5 -end-1.5 w-4 h-4 rounded-full bg-white border border-ink-200 text-[10px] leading-3 text-ink-500">×</button></span>'; }).join('') +
      (cols.length < 10 ? '<button type="button" data-kcadd class="w-9 h-9 rounded-lg border-2 border-dashed border-ink-300 text-ink-400" title="' + esc(L.addColor) + '">+</button>' : '');
    kit.colors = cols.slice(); fontsSel('kit-head', kit.head); fontsSel('kit-body', kit.body); $('kit-auto').checked = !!kit.auto;
  }
  $('kit-cols').addEventListener('input', function (e) { var i = e.target.dataset.kc; if (i != null) kit.colors[+i] = e.target.value; });
  $('kit-cols').addEventListener('click', function (e) { var x = e.target.closest('[data-kcx]'); if (x) { kit.colors.splice(+x.dataset.kcx, 1); drawKit(); } if (e.target.closest('[data-kcadd]')) { kit.colors.push('#888888'); drawKit(); } });
  $('kit-head').addEventListener('change', function () { kit.head = this.value; }); $('kit-body').addEventListener('change', function () { kit.body = this.value; }); $('kit-auto').addEventListener('change', function () { kit.auto = this.checked; });
  $('kit-font').addEventListener('change', function () { var f = this.files[0]; if (!f) return; post({ action: 'kit_font', name: f.name.replace(/\.\w+$/, '') }, f).then(function (j) { if (!j.ok) return X.toast(j.error || L.err, 'error');
    addFont(j.font); kit.fonts = (kit.fonts || []).concat([j.font]); if (!kit.head) kit.head = j.font.name; drawKit(); X.toast(L.fontUp); }); this.value = ''; });

  /* ---------- 3) تطبيق الهوية: الخطوط + اللوجو (بعد أي قالب أو تصميم بالذكاء الاصطناعي) ---------- */
  function texts(list, out) { list.forEach(function (o) { if (o.type === 'textbox') out.push(o); else if (o.type === 'group') texts(o.getObjects(), out); }); return out; }
  function addLogo(force) { if (!kit.logo) { if (force) X.toast(L.noLogo, 'error'); return; }
    cv.getObjects().filter(function (o) { return o.dzRole === 'logo'; }).forEach(function (o) { cv.remove(o); });
    fabric.Image.fromURL(kit.logo, function (im) { if (!im || !im.width) return; var w = X.W * 0.16, k = Math.min(w / im.width, X.H * 0.1 / im.height); im.set({ scaleX: k, scaleY: k, left: X.W * 0.04, top: X.H * 0.04, dzRole: 'logo' }); X.setLock(true); cv.add(im); X.setLock(false); cv.requestRenderAll(); X.snap(); }, { crossOrigin: 'anonymous' }); }
  function applyKit(withLogo) {
    var ts = texts(cv.getObjects(), []);
    ts.forEach(function (t) { if (/Inter|Playfair|Twemoji/.test(t.fontFamily)) return; var f = t.fontWeight >= 700 ? kit.head : kit.body; if (f) { t.set('fontFamily', f); fabric.util.clearFabricFontCache(f); t.initDimensions && t.initDimensions(); } });
    cv.getObjects().forEach(function (o) { if (o.type === 'group') o.addWithUpdate && o._calcBounds && o.setCoords(); });
    cv.requestRenderAll(); if (withLogo && kit.auto) addLogo(false);
  }
  document.addEventListener('dz:tpl', function () { if (kit.head || kit.body || (kit.auto && kit.logo)) { var fs = [kit.head, kit.body].filter(Boolean).map(function (f) { return document.fonts ? document.fonts.load('700 30px "' + f + '"') : null; }); Promise.all(fs).then(function () { applyKit(true); X.snap(); }); } });

  /* ---------- 4) تغيير المقاس الذكي: ترتيب العناصر من جديد حسب شكل المقاس ---------- */
  function box(o) { var w = (o.width || 0) * Math.abs(o.scaleX || 1), h = (o.height || 0) * Math.abs(o.scaleY || 1), l = o.left || 0, t = o.top || 0;
    if (o.originX === 'center') l -= w / 2; else if (o.originX === 'right') l -= w; if (o.originY === 'center') t -= h / 2; else if (o.originY === 'bottom') t -= h; return { l: l, t: t, w: w, h: h }; }
  function put(o, b, k) { o.scaleX = (o.scaleX || 1) * k; o.scaleY = (o.scaleY || 1) * k; var w = b.w * k, h = b.h * k;
    o.left = b.nl + (o.originX === 'center' ? w / 2 : o.originX === 'right' ? w : 0); o.top = b.nt + (o.originY === 'center' ? h / 2 : o.originY === 'bottom' ? h : 0); }
  function isText(o) { return o.type === 'textbox' || o.type === 'text' || (o.type === 'group' && JSON.stringify(o.objects || []).indexOf('"textbox"') > -1 && o.dzRole !== 'ph'); }
  window.dzSmart = function (src, W, H) {
    var W0 = src.w, H0 = src.h, c = JSON.parse(JSON.stringify(src.c)), r0 = H0 / W0, r = H / W, s = Math.min(W / W0, H / H0), media = [], content = [], other = [];
    (c.objects || []).forEach(function (o) { var b = box(o); o.__b = b;
      if (b.w >= W0 * 0.95 && b.h >= H0 * 0.6) { var f = Math.max(W / b.w, H / b.h); b.nl = (W - b.w * f) / 2; b.nt = (H - b.h * f) / 2; put(o, b, f); } // خلفية كاملة: بتغطي
      else if (b.w >= W0 * 0.95) { var fx = W / b.w, fy = s; o.scaleX = (o.scaleX || 1) * fx; o.scaleY = (o.scaleY || 1) * fy; o.left = 0 + (o.originX === 'center' ? W / 2 : 0); o.top = b.t / H0 * H + (o.originY === 'center' ? b.h * fy / 2 : 0); } // شريط بعرض اللوحة
      else if (o.type === 'image' || o.dzRole === 'ph') media.push(o); else if (isText(o)) content.push(o); else other.push(o); });
    // زخارف: نفس النسبة ومتعلقة بأقرب ركن
    other.forEach(function (o) { var b = o.__b, cx = b.l + b.w / 2, cy = b.t + b.h / 2; b.nl = cx < W0 / 2 ? b.l * s : W - (W0 - b.l) * s; b.nt = (cy < H0 / 2 ? b.t * s : H - (H0 - b.t) * s); if (Math.abs(r / r0 - 1) < 0.15) { b.nl = W / 2 + (b.l - W0 / 2) * s; b.nt = H / 2 + (b.t - H0 / 2) * s; } put(o, b, s); });
    content.sort(function (a, b) { return a.__b.t - b.__b.t; }); var M = Math.min(W, H) * 0.06;
    if (Math.abs(r / r0 - 1) < 0.15) { media.concat(content).forEach(function (o) { var b = o.__b; b.nl = W / 2 + (b.l - W0 / 2) * s; b.nt = H / 2 + (b.t - H0 / 2) * s; put(o, b, s); }); }
    else if (r > r0) { // أطول (ستوري/طولي): الصورة فوق والنصوص تحتها
      var k = W / W0, items = [];
      media.forEach(function (o) { var b = o.__b, km = Math.min((W - 2 * M) / b.w, H * 0.42 / b.h, k * 1.6); items.push({ o: o, k: km, h: b.h * km, w: b.w * km }); });
      content.forEach(function (o) { var b = o.__b; items.push({ o: o, k: k, h: b.h * k, w: b.w * k }); });
      var avail = H - 2 * M, tot = items.reduce(function (a, i) { return a + i.h; }, 0), f = tot > avail * 0.9 ? avail * 0.9 / tot : 1; items.forEach(function (i) { i.k *= f; i.h *= f; i.w *= f; }); tot *= f;
      var gap = Math.min(H * 0.045, (avail - tot) / Math.max(1, items.length + 1)), y = M + (avail - tot - gap * (items.length - 1)) / 2;
      items.forEach(function (i) { var b = i.o.__b, cx = (b.l + b.w / 2) / W0 * W; b.nl = Math.max(M * 0.5, Math.min(W - i.w - M * 0.5, cx - i.w / 2)); b.nt = y; put(i.o, b, i.k); y += i.h + gap; });
    } else { // أعرض: الصورة في عمود والنصوص في العمود التاني
      var hasM = media.length > 0, colX = hasM ? W * 0.5 : W * 0.1, colW = hasM ? W * 0.46 : W * 0.8;
      if (hasM) { var mb = media[0].__b, km2 = Math.min(W * 0.44 / mb.w, (H - 2 * M) / mb.h); mb.nl = W * 0.03 + (W * 0.44 - mb.w * km2) / 2; mb.nt = (H - mb.h * km2) / 2; put(media[0], mb, km2); media.slice(1).forEach(function (o) { var b = o.__b; b.nl = W * 0.03; b.nt = M; put(o, b, km2 * 0.4); }); }
      var maxW = content.reduce(function (a, o) { return Math.max(a, o.__b.w); }, 1), kk = Math.min(colW / maxW, H / H0), tot2 = content.reduce(function (a, o) { return a + o.__b.h * kk; }, 0), av = H - 2 * M;
      if (tot2 > av * 0.92) { kk *= av * 0.92 / tot2; tot2 = av * 0.92; } var g = Math.min(H * 0.05, (av - tot2) / Math.max(1, content.length + 1)), yy = M + (av - tot2 - g * (content.length - 1)) / 2;
      content.forEach(function (o) { var b = o.__b, w = b.w * kk; b.nl = colX + (colW - w) / 2; b.nt = yy; put(o, b, kk); yy += b.h * kk + g; });
    }
    (c.objects || []).forEach(function (o) { delete o.__b; });
    return JSON.stringify({ w: W, h: H, name: src.name, c: c });
  };

  /* ---------- 5) صور مجانية (Pexels) ---------- */
  var sPage = 1, sQ = '';
  function stockDraw(items, more, append) { var g = $('dz-stock'); var h = items.map(function (p) { return '<button type="button" class="dz-stk-i" data-px="' + p.id + '" style="aspect-ratio:' + p.w + '/' + p.h + '" title="' + esc(L.by + ': ' + p.by) + '"><img src="' + esc(p.thumb) + '" alt="' + esc(p.alt) + '" loading="lazy"></button>'; }).join('');
    if (append) g.insertAdjacentHTML('beforeend', h); else g.innerHTML = h || '<p class="col-span-2 text-xs text-ink-400 text-center py-4">' + esc(L.nothing) + '</p>'; $('dz-stock-more').classList.toggle('hidden', !more); }
  function stockLoad(append) { post({ action: 'stock', q: sQ, page: sPage }).then(function (j) { if (!j.ok) { $('dz-stock').innerHTML = '<p class="col-span-2 text-xs text-red-600">' + esc(j.error || L.err) + '</p>'; return; } stockDraw(j.items, j.more, append); }); }
  if (!C.pexels) $('dz-stock-box').innerHTML = '<p class="text-xs text-ink-500">' + esc(L.stockConnect) + ' <a class="link" href="' + esc(C.connectUrl) + '">' + esc(L.stockOpen) + '</a></p>';
  else { $('dz-stock-f').addEventListener('submit', function (e) { e.preventDefault(); sQ = $('dz-stock-q').value.trim(); sPage = 1; stockLoad(false); });
    $('dz-stock-more').addEventListener('click', function () { sPage++; stockLoad(true); });
    document.querySelector('.dz-rail [data-tab="img"]').addEventListener('click', function () { if (!$('dz-stock').children.length) stockLoad(false); });
    $('dz-stock').addEventListener('click', function (e) { var b = e.target.closest('[data-px]'); if (!b) return; var target = cv.getActiveObject(); b.classList.add('animate-pulse'); X.toast(L.stockAdding, 'info');
      post({ action: 'stock_add', id: b.dataset.px }).then(function (j) { b.classList.remove('animate-pulse'); if (!j.ok) return X.toast(j.error || L.err, 'error'); if (target && target.dzRole === 'ph') X.fillPh(target, j.url); else X.addImg(j.url); }); }); }

  /* ---------- 6) أيقونات ملونة وإيموجي ---------- */
  var ist = 'line';
  $('dz-ico-st').addEventListener('click', function (e) { var b = e.target.closest('[data-ist]'); if (!b) return; ist = b.dataset.ist; this.querySelectorAll('[data-ist]').forEach(function (x) { x.classList.toggle('on', x === b); });
    document.querySelectorAll('#dz-ico [data-ico]').forEach(function (x) { x.classList.toggle('dz-duo', ist === 'duo'); x.classList.toggle('dz-solid', ist === 'solid'); }); });
  document.addEventListener('click', function (e) { var b = e.target.closest('#dz-ico [data-ico]'); if (!b || ist === 'line') return; e.stopImmediatePropagation();
    var W = X.W, size = W * 0.16, path = new fabric.Path(C.icons[b.dataset.ico], { fill: '', stroke: ist === 'duo' ? X.P : '#fff', strokeWidth: 1.8, strokeLineCap: 'round', strokeLineJoin: 'round', originX: 'center', originY: 'center', scaleX: size / 40, scaleY: size / 40 });
    var bg = ist === 'duo' ? new fabric.Circle({ radius: size / 2, fill: X.P, opacity: 0.16, originX: 'center', originY: 'center' }) : new fabric.Rect({ width: size, height: size, rx: size * 0.24, ry: size * 0.24, fill: X.P, originX: 'center', originY: 'center' });
    var g = new fabric.Group([bg, path]); cv.add(g); g.center(); g.setCoords(); cv.setActiveObject(g); cv.requestRenderAll(); X.snap(); X.sync(); }, true);
  var EMO = '🔥 ⭐ 💯 ✅ 🎉 🎁 💥 ⚡ ❤️ 👍 👏 🙌 😍 😋 🤩 👌 💡 📣 📍 📞 💬 🕒 🚚 🛵 🛒 💳 🏷️ 💰 🍔 🍕 🍗 🥗 ☕ 🍰 🌙 ☀️ 🌟 🏆 🎯 📈 💼 🏠 ✂️ 💄 🩺 🎓 ⚽ 🇸🇦 🇪🇬'.split(' ');
  $('dz-emo').innerHTML = EMO.map(function (x) { return '<button type="button" class="dz-el !p-1 text-2xl" data-emo="' + x + '" data-q="emoji">' + x + '</button>'; }).join('');
  $('dz-emo').addEventListener('click', function (e) { var b = e.target.closest('[data-emo]'); if (!b) return; var t = new fabric.Text(b.dataset.emo, { fontSize: X.W * 0.14, fontFamily: '"Twemoji Country Flags","Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif' }); cv.add(t); t.center(); t.setCoords(); cv.setActiveObject(t); cv.requestRenderAll(); X.snap(); X.sync(); });

  /* ---------- الأوامر ---------- */
  var MORE = {
    kitlogo: function () { if (!window.mediaPick) return; window.mediaPick({ accept: 'image', multiple: false }).then(function (fs) { if (fs && fs[0]) { kit.logo = fs[0].url; drawKit(); } }); },
    addlogo: function () { addLogo(true); },
    kitsave: function () { post({ action: 'kit_save', colors: JSON.stringify(kit.colors), head: kit.head || '', body: kit.body || '', logo: kit.logo || '', auto: kit.auto ? 1 : '' }).then(function (j) { if (!j.ok) return X.toast(j.error || L.err, 'error'); X.toast(L.kitSaved); }); },
    kitapply: function () { var fs = [kit.head, kit.body].filter(Boolean).map(function (f) { return document.fonts ? document.fonts.load('700 30px "' + f + '"') : null; });
      Promise.all(fs).then(function () { // ألوان الهوية: بتستبدل ألوان التصميم الأساسية بالترتيب
        var map = {}; [X.P, X.S, X.A].forEach(function (c, i) { if (kit.colors[i] && c) map[String(c).toLowerCase()] = kit.colors[i]; });
        var rec = function (o) { ['fill', 'stroke'].forEach(function (k) { if (typeof o[k] === 'string' && map[o[k].toLowerCase()]) o.set(k, map[o[k].toLowerCase()]); }); if (o.type === 'group') o.getObjects().forEach(rec); };
        cv.getObjects().forEach(rec); if (typeof cv.backgroundColor === 'string' && map[cv.backgroundColor.toLowerCase()]) cv.setBackgroundColor(map[cv.backgroundColor.toLowerCase()], function () {});
        applyKit(false); if (kit.logo) addLogo(false); X.snap(); X.toast(L.kitApplied); }); }
  };
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-cmd]'); if (b && MORE[b.dataset.cmd]) { e.stopImmediatePropagation(); MORE[b.dataset.cmd](); } }, true);
  drawKit();
  window.dzKit = { kit: function () { return kit; }, applyKit: applyKit, addLogo: addLogo };
})();
