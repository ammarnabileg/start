/* خطة المحتوى: معالج بمسودة محلية، حلقة توليد تكمل بعد انقطاع النت، ومراجعة واعتماد */
(function () {
  var C = window.CP; if (!C) return; var T = C.t;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function fmt(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }
  function post(d, files) { var fd = new FormData(); fd.append('_csrf', C.csrf); for (var k in d) fd.append(k, d[k]); (files || []).forEach(function (f) { fd.append('files[]', f); });
    return fetch(C.url + (C.plan ? '&plan=' + C.plan.id : ''), { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function ls(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }

  /* ---------- المعالج ---------- */
  var W = $('#cp-wiz');
  if (W) {
    var key = 'cp_draft_' + C.wid, refs = [], prods = [];
    function paintRefs() { $('#cp-refs').innerHTML = refs.map(function (r, i) { return '<span class="relative"><img src="' + esc(r.thumb) + '" class="w-20 h-24 object-cover rounded-xl border border-ink-200" alt=""><button type="button" data-rm="' + i + '" class="absolute -top-1.5 -end-1.5 w-6 h-6 rounded-full bg-white border border-ink-200 text-xs">×</button></span>'; }).join(''); }
    function paintProds() { var el = $('#cp-prods'); if (el) el.innerHTML = prods.map(function (r, i) { return '<span class="relative"><img src="' + esc(r.thumb) + '" class="w-20 h-24 object-cover rounded-xl border border-ink-200" alt=""><button type="button" data-pm="' + i + '" class="absolute -top-1.5 -end-1.5 w-6 h-6 rounded-full bg-white border border-ink-200 text-xs">×</button></span>'; }).join(''); }
    function collect() { var d = { refs: refs.map(function (r) { return r.id; }), products: prods.map(function (r) { return r.id; }), services: $$('[name=services]:checked', W).map(function (x) { return x.value; }) };
      if (W.querySelector('[name=style]:checked')) d.style = W.querySelector('[name=style]:checked').value;
      if (W.querySelector('[name=img_q]:checked')) d.img_q = W.querySelector('[name=img_q]:checked').value;
      if (W.querySelector('[name=aspect]:checked')) d.aspect = W.querySelector('[name=aspect]:checked').value;
      if (W.querySelector('[name=design_mode]:checked')) d.design_mode = W.querySelector('[name=design_mode]:checked').value;
      if (W.querySelector('[name=layout]:checked')) d.layout = W.querySelector('[name=layout]:checked').value;
      ['goal', 'count', 'name', 'tone', 'cta', 'audience', 'about', 'avoid', 'dialect_other', 'lang_all', 'lang_li', 'lang_x', 'lang_img', 'lang_vo', 'dialect_k', 'txt_pos', 'txt_size', 'txt_variant', 'txt_accent'].forEach(function (n) { if (W[n]) d[n] = W[n].value; }); d.video = W.video.checked; d.carousel = W.carousel.checked; d.humanize = W.humanize.checked; d.qc = W.qc ? W.qc.checked : true;
      if (W.font) d.font = (W.querySelector('[name=font]:checked') || {}).value || 'auto';
      if (W.logo_w) { ['logo_x', 'logo_y', 'logo_w', 'logo_op'].forEach(function (n) { d[n] = +W[n].value; }); ['logo_tint', 'logo_plate'].forEach(function (n) { d[n] = (W.querySelector('[name=' + n + ']:checked') || {}).value; }); d.logo_on = W.logo_on.checked; } return d; }
    var dr = ls(key);
    if (dr && (dr.goal || (dr.refsFull || []).length)) {
      ['goal', 'count', 'name', 'tone', 'cta', 'audience', 'about', 'avoid', 'dialect_other', 'lang_all', 'lang_li', 'lang_x', 'lang_img', 'lang_vo', 'dialect_k', 'txt_pos', 'txt_size', 'txt_variant', 'txt_accent'].forEach(function (n) { if (dr[n] != null && W[n]) W[n].value = dr[n]; });
      W.video.checked = dr.video !== false; W.carousel.checked = dr.carousel !== false; W.humanize.checked = dr.humanize !== false;
      if (W.font && dr.font) Array.prototype.slice.call(W.querySelectorAll('[name=font]')).forEach(function (x) { x.checked = x.value === dr.font; });
      if (W.font && dr.font) Array.prototype.slice.call(W.querySelectorAll('[name=font]')).forEach(function (x) { x.checked = x.value === dr.font; });
      if (W.logo_w) { ['logo_x', 'logo_y', 'logo_w', 'logo_op'].forEach(function (n) { if (dr[n] != null) W[n].value = dr[n]; }); ['logo_tint', 'logo_plate'].forEach(function (n) { if (dr[n]) $$('[name=' + n + ']', W).forEach(function (x) { x.checked = x.value === dr[n]; }); }); W.logo_on.checked = dr.logo_on !== false; }
      $$('[name=services]', W).forEach(function (x) { x.checked = (dr.services || []).indexOf(x.value) >= 0; });
      refs = dr.refsFull || []; paintRefs();
      prods = dr.prodsFull || []; paintProds();
      if (dr.style) $$('[name=style]', W).forEach(function (x) { x.checked = x.value === dr.style; });
      if (dr.img_q) $$('[name=img_q]', W).forEach(function (x) { x.checked = x.value === dr.img_q; });
      if (dr.aspect) $$('[name=aspect]', W).forEach(function (x) { x.checked = x.value === dr.aspect; });
      if (dr.design_mode) $$('[name=design_mode]', W).forEach(function (x) { x.checked = x.value === dr.design_mode; });
      if (dr.layout) $$('[name=layout]', W).forEach(function (x) { x.checked = x.value === dr.layout; });
      toast(T.draftRestored, 'info');
    }
    // إظهار/إخفاء اختيار القالب حسب طريقة الإخراج (قوالب فقط)
    function syncDMode() { var lw = $('#cp-layout-wrap'); if (!lw) return; var m = (W.querySelector('[name=design_mode]:checked') || {}).value; lw.style.display = m === 'layout' ? '' : 'none'; }
    $$('[name=design_mode]', W).forEach(function (x) { x.addEventListener('change', syncDMode); }); syncDMode();
    // معاينة اللوجو التفاعلية: سحب، أماكن جاهزة، حجم، شفافية، لون، خلفية. نفس معادلات السيرفر بالظبط (cpl_logo_box)
    var bgIx = 0;
    function lbox(bw, bh, ar) { var w = +W.logo_w.value / 100 * bw, h = w / ar, mh = 0.18 * bh; if (h > mh) { h = mh; w = h * ar; }
      var cx = Math.min(Math.max(+W.logo_x.value / 100 * bw, 0), bw), cy = Math.min(Math.max(+W.logo_y.value / 100 * bh, 0), bh); // يُسمح بالخروج عن الإطار
      return { x: cx - w / 2, y: cy - h / 2, w: w, h: h }; }
    function lprev() { var bx = $('#cp-lprev'), lg = $('#cp-lprev-logo'), pl = $('#cp-lplate'); if (!bx || !lg) return;
      $('#cp-lbgs').innerHTML = refs.map(function (r, i) { return '<button type="button" data-bg="' + i + '" class="w-8 h-10 rounded border-2 ' + (i === bgIx ? 'border-brand-500' : 'border-transparent') + ' bg-cover bg-center" style="background-image:url(\'' + esc(r.thumb) + '\')"></button>'; }).join('');
      if (refs[bgIx] || refs[0]) bx.style.backgroundImage = 'url("' + (refs[bgIx] || refs[0]).thumb + '")';
      var on = W.logo_on.checked, ar = (lg.naturalWidth || 3) / (lg.naturalHeight || 1), b = lbox(bx.clientWidth, bx.clientHeight, ar);
      lg.style.display = pl.style.display = on ? '' : 'none';
      lg.style.left = b.x + 'px'; lg.style.top = b.y + 'px'; lg.style.width = b.w + 'px'; lg.style.height = b.h + 'px';
      lg.style.opacity = +W.logo_op.value / 100;
      var tint = (W.querySelector('[name=logo_tint]:checked') || {}).value, plate = (W.querySelector('[name=logo_plate]:checked') || {}).value;
      lg.style.filter = tint === 'white' ? 'brightness(0) invert(1)' : tint === 'black' ? 'brightness(0)' : '';
      var p = b.h * 0.3; pl.style.left = (b.x - p) + 'px'; pl.style.top = (b.y - p) + 'px'; pl.style.width = (b.w + 2 * p) + 'px'; pl.style.height = (b.h + 2 * p) + 'px';
      pl.style.background = plate === 'light' ? 'rgba(255,255,255,.85)' : plate === 'dark' ? 'rgba(0,0,0,.55)' : 'transparent';
      $('#cp-lw-v').textContent = W.logo_w.value + '%'; $('#cp-lo-v').textContent = W.logo_op.value + '%';
      $$('[data-lp]', W).forEach(function (x) { x.classList.toggle('on', x.dataset.lp === W.logo_x.value + ',' + W.logo_y.value); }); }
    if ($('#cp-lprev')) {
      var lgEl = $('#cp-lprev-logo'), drag = null; lgEl.addEventListener('load', lprev);
      lgEl.addEventListener('pointerdown', function (e) { e.preventDefault(); lgEl.setPointerCapture(e.pointerId); var r = $('#cp-lprev').getBoundingClientRect(); drag = { r: r }; });
      lgEl.addEventListener('pointermove', function (e) { if (!drag) return; var r = drag.r;
        W.logo_x.value = Math.round(Math.min(100, Math.max(0, (e.clientX - r.left) / r.width * 100))); W.logo_y.value = Math.round(Math.min(100, Math.max(0, (e.clientY - r.top) / r.height * 100))); lprev(); });
      lgEl.addEventListener('pointerup', function () { if (drag) { drag = null; saveDraft(); } });
      W.addEventListener('click', function (e) { var b = e.target.closest('[data-lp]'); if (b) { var v = b.dataset.lp.split(','); W.logo_x.value = v[0]; W.logo_y.value = v[1]; lprev(); saveDraft(); }
        var g = e.target.closest('[data-bg]'); if (g) { bgIx = +g.dataset.bg; lprev(); } });
      W.addEventListener('input', function (e) { if (/^logo_/.test(e.target.name || '')) lprev(); });
    }
    // سلايدرز داخل المودال تكتب في الحقول المخفية
    W.addEventListener('input', function (e) { var sl = e.target.closest('[data-logo]'); if (sl && W[sl.dataset.logo]) { W[sl.dataset.logo].value = sl.value; lprev(); } });
    // مصغّر اللوجو في الخطوة (خارج المودال)
    function lthumb() { var t = $('#cp-lthumb-logo'), bx = $('#cp-lthumb'); if (!t || !bx) { return; } if (refs[0]) bx.style.backgroundImage = 'url("' + refs[0].thumb + '")';
      var on = W.logo_on.checked; t.style.display = on ? '' : 'none'; if (!on) return;
      var ar = (t.naturalWidth || 3) / (t.naturalHeight || 1), bw = bx.clientWidth, bh = bx.clientHeight, w = Math.min(0.45, +W.logo_w.value / 100) * bw, h = w / ar;
      var cx = +W.logo_x.value / 100 * bw, cy = +W.logo_y.value / 100 * bh;
      t.style.position = 'absolute'; t.style.left = (cx - w / 2) + 'px'; t.style.top = (cy - h / 2) + 'px'; t.style.width = w + 'px'; t.style.height = h + 'px';
      t.style.opacity = +W.logo_op.value / 100; var tint = (W.querySelector('[name=logo_tint]:checked') || {}).value; t.style.filter = tint === 'white' ? 'brightness(0) invert(1)' : tint === 'black' ? 'brightness(0)' : ''; }
    // المودال: فتح/غلق
    var modal = $('#cp-logo-modal');
    function openLogo() { if (!modal) return; modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; lprev(); }
    function closeLogo() { if (!modal) return; modal.classList.add('hidden'); document.body.style.overflow = ''; lthumb(); saveDraft(); }
    if ($('#cp-logo-open')) $('#cp-logo-open').addEventListener('click', openLogo);
    if ($('#cp-logo-done')) $('#cp-logo-done').addEventListener('click', closeLogo);
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeLogo(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeLogo(); });
    W.addEventListener('change', function () { lprev(); lthumb(); }); setTimeout(lthumb, 60);
    // اللهجة: مثال حي، وخانة «أخرى»، واللهجة بتختفي لو مفيش ولا قناة عربي
    function dial() { var k = W.dialect_k ? W.dialect_k.value : '', ex = $('#cp-dial-ex'); if (!ex) return;
      W.dialect_other.classList.toggle('hidden', k !== 'other');
      var txt = (T.dialEx || {})[k] || ''; ex.textContent = txt; ex.classList.toggle('hidden', !txt); }
    W.addEventListener('change', dial); setTimeout(dial, 0);
    // خطوات المعالج: شاشة واحدة في المرة، والخطوة الحالية بتتحفظ مع المسودة
    var step = 0, nSteps = $$('[data-step]', W).length;
    function show(i) { step = Math.max(0, Math.min(nSteps - 1, i));
      $$('[data-step]', W).forEach(function (x) { x.classList.toggle('hidden', +x.dataset.step !== step); });
      $$('[data-go]', W).forEach(function (b) { var k = +b.dataset.go; b.classList.toggle('on', k === step); b.classList.toggle('done', k < step); b.firstChild.textContent = k < step ? '✓' : k + 1; });
      $('#cp-back').classList.toggle('invisible', !step); $('#cp-next').classList.toggle('hidden', step === nSteps - 1); $('#cp-go').classList.toggle('hidden', step !== nSteps - 1);
      if (step === nSteps - 1) summary(); if (step === 3) lprev(); try { sessionStorage.setItem(key + '_s', step); } catch (e) {}
      var f = $('[data-step="' + step + '"] textarea, [data-step="' + step + '"] input:not([type=hidden]):not(.sr-only)', W); if (f && step < 2) f.focus(); window.scrollTo({ top: W.offsetTop - 80, behavior: 'smooth' }); }
    function okStep() { if (step === 0 && !W.goal.value.trim()) { W.goal.focus(); toast(T.needGoal, 'error'); return false; } return true; }
    $('#cp-next').addEventListener('click', function () { if (okStep()) show(step + 1); });
    $('#cp-back').addEventListener('click', function () { show(step - 1); });
    W.addEventListener('click', function (e) { var b = e.target.closest('[data-go]'); if (b && (+b.dataset.go <= step || okStep())) show(+b.dataset.go); });
    W.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && step < nSteps - 1) { e.preventDefault(); if (okStep()) show(step + 1); } });
    // اللغة: اختيار واحد بيظبط كل القنوات، و«تخصيص» للتفاصيل
    if (W.lang_all) W.lang_all.addEventListener('change', function () {
      var v = W.lang_all.value;
      ['lang_li', 'lang_x', 'lang_img', 'lang_vo'].forEach(function (n) { if (W[n] && [].slice.call(W[n].options).some(function (o) { return o.value === v; })) W[n].value = v; });
      saveDraft(); });
    function summary() { var d = collect(), S = T.sum, L = T.langN, v = function (x) { return x && String(x).trim() ? x : S.none; };
      var langs = [d.lang_li, d.lang_x, d.lang_img, d.lang_vo]; var lg = langs.every(function (x) { return x === langs[0]; }) ? L[langs[0]] : [S.li + ': ' + L[d.lang_li], S.x + ': ' + L[d.lang_x], S.img + ': ' + L[d.lang_img], S.vo + ': ' + L[d.lang_vo]].join(' · ');
      var dsel = W.dialect_k, dn = d.dialect_k === 'other' ? d.dialect_other : (d.dialect_k && dsel ? dsel.options[dsel.selectedIndex].text : '');
      var rows = [[S.goal, v(d.goal), 0], [S.count, d.count, 0], [S.aud, v(d.audience), 1], [S.svc, v((d.services || []).join('، ')), 1], [S.lang, lg, 2], [S.dial, v(dn), 2], [S.tone, v(d.tone), 2], [S.cta, v(d.cta), 2], [S.refs, String((d.refs || []).length), 3], [S.logo, W.logo_w ? (d.logo_on ? S.on : S.off) : S.none, 3]];
      $('#cp-sum').innerHTML = rows.map(function (r) { return '<div class="rounded-xl bg-ink-50 px-3 py-2"><dt class="text-xs text-ink-500 flex justify-between">' + esc(r[0]) + '<button type="button" data-go="' + r[2] + '" class="link text-xs">✎</button></dt><dd class="text-ink-900 break-words" dir="auto">' + esc(r[1]) + '</dd></div>'; }).join(''); }
    var s0 = 0; try { s0 = +(sessionStorage.getItem(key + '_s') || 0); } catch (e) {} show(dr && dr.goal ? s0 : 0);
    function saveDraft() { lprev(); var d = collect(); d.refsFull = refs; d.prodsFull = prods; ls(key, d); }
    W.addEventListener('input', saveDraft); W.addEventListener('change', saveDraft);
    $('#cp-refs').addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (!b) return; refs.splice(+b.dataset.rm, 1); paintRefs(); saveDraft(); });
    // صور المنتج: حذف، مكتبة، رفع (6 كحد أقصى)
    var prodsEl = $('#cp-prods');
    if (prodsEl) prodsEl.addEventListener('click', function (e) { var b = e.target.closest('[data-pm]'); if (!b) return; prods.splice(+b.dataset.pm, 1); paintProds(); saveDraft(); });
    var prodLib = $('#cp-prod-lib');
    if (prodLib && window.mediaPick) prodLib.addEventListener('click', function () {
      window.mediaPick({ accept: 'image', multiple: true }).then(function (items) { if (!items || !items.length) return;
        var room = 6 - prods.length; var have = {}; prods.forEach(function (r) { have[r.id] = 1; });
        items.filter(function (it) { return it && it.id && !have[it.id]; }).slice(0, room).forEach(function (it) { prods.push({ id: it.id, thumb: it.thumb || it.url }); });
        paintProds(); saveDraft(); }).catch(function () {}); });
    else if (prodLib) prodLib.style.display = 'none';
    var prodIn = $('#cp-prod-in');
    if (prodIn) prodIn.addEventListener('change', function () {
      var all = [].slice.call(this.files); this.value = ''; var room = 6 - prods.length; if (room <= 0) return toast(T.refMax, 'error');
      Promise.all(all.slice(0, room).map(shrink)).then(function (fs) { if (!fs.length) return;
        toast(T.uploading, 'info');
        return post({ action: 'ref' }, fs).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); prods = prods.concat(j.items); paintProds(); saveDraft(); toast(T.uploaded); }); }).catch(function () { toast(T.err, 'error'); });
    });
    // PDF مرجعي: كل صفحة تتحول لصورة JPEG في المتصفح، فالذكاء الاصطناعي يشوفها زي الصور بالظبط
    function pdfPages(f, max) { return window.extractKit.loadPdf().then(function (lib) { return f.arrayBuffer().then(function (b) { return lib.getDocument({ data: b }).promise; }); }).then(function (doc) {
      var out = [], n = Math.min(doc.numPages, max);
      return (function pg(i) { if (i > n) return out; return doc.getPage(i).then(function (page) { var v = page.getViewport({ scale: 1 }), sc = Math.min(2, 1400 / Math.max(v.width, v.height)), vp = page.getViewport({ scale: sc }), c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
        return page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise.then(function () { return new Promise(function (ok) { c.toBlob(function (bl) { out.push(new File([bl], f.name.replace(/\.pdf$/i, '') + '-p' + i + '.jpg', { type: 'image/jpeg' })); ok(); }, 'image/jpeg', 0.88); }); }).then(function () { return pg(i + 1); }); }); })(1); }); }
    // صورة كبيرة (كاميرا/تصميم 10MB) بتتصغر في المتصفح لـ 1600 بكسل قبل الرفع: أسرع، ومابتعديش حد الرفع في السيرفر
    function shrink(f) { if (!/^image\/(jpeg|png|webp)$/.test(f.type) || f.size < 1500000) return Promise.resolve(f);
      return new Promise(function (ok) { var u = URL.createObjectURL(f), im = new Image(); im.onload = function () { var k = Math.min(1, 1600 / Math.max(im.width, im.height)), c = document.createElement('canvas'); c.width = Math.round(im.width * k); c.height = Math.round(im.height * k); var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(im, 0, 0, c.width, c.height); URL.revokeObjectURL(u);
        c.toBlob(function (bl) { ok(bl ? new File([bl], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : f); }, 'image/jpeg', 0.88); }; im.onerror = function () { URL.revokeObjectURL(u); ok(f); }; im.src = u; }); }
    var libBtn = $('#cp-ref-lib');
    if (libBtn && window.mediaPick) libBtn.addEventListener('click', function () {
      window.mediaPick({ accept: 'image', multiple: true }).then(function (items) { if (!items || !items.length) return;
        var room = 12 - refs.length; var have = {}; refs.forEach(function (r) { have[r.id] = 1; });
        items.filter(function (it) { return it && it.id && !have[it.id]; }).slice(0, room).forEach(function (it) { refs.push({ id: it.id, thumb: it.thumb || it.url }); });
        paintRefs(); saveDraft(); if (typeof lprev === 'function') lprev(); }).catch(function () {}); });
    else if (libBtn) libBtn.style.display = 'none';
    $('#cp-ref-in').addEventListener('change', function () {
      var all = [].slice.call(this.files); this.value = ''; var room = 12 - refs.length; if (room <= 0) return toast(T.refMax, 'error');
      var pdfs = all.filter(function (f) { return /pdf$/i.test(f.type) || /\.pdf$/i.test(f.name); }), imgs = all.filter(function (f) { return pdfs.indexOf(f) < 0; });
      if (pdfs.length) toast(T.pdfPages, 'info');
      pdfs.reduce(function (pr, f) { return pr.then(function (acc) { return acc.length >= room ? acc : pdfPages(f, room - acc.length).then(function (pp) { return acc.concat(pp); }); }); }, Promise.resolve(imgs.slice(0, room)))
      .then(function (fs) { return Promise.all(fs.slice(0, room).map(shrink)); })
      .then(function (fs) { if (!fs.length) return;
      toast(T.uploading, 'info');
      return post({ action: 'ref' }, fs).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); refs = refs.concat(j.items); paintRefs(); saveDraft(); toast(T.uploaded); }); }).catch(function () { toast(T.err, 'error'); });
    });
    W.addEventListener('submit', function (e) {
      e.preventDefault(); var d = collect(); if (!d.goal.trim()) { W.goal.focus(); return toast(T.needGoal, 'error'); }
      var b = $('button.btn-primary', W); b.disabled = true;
      post({ action: 'create', data: JSON.stringify(d) }).then(function (j) { if (!j.ok) { b.disabled = false; return toast(j.error || T.err, 'error'); } ls(key, null); location.href = j.url; })
        .catch(function () { b.disabled = false; toast(T.offline, 'error'); }); // المسودة لسه محفوظة
    });
    return;
  }

  /* ---------- الخطة: التوليد ---------- */
  var P = C.plan, items = [], sel = {}, filter = '', running = false;
  var prog = $('#cp-prog');
  function paintProg(s, note) {
    P = Object.assign(P, s);
    var pct = P.count ? Math.round(100 * P.ready / P.count) : 0, txt;
    if (P.status === 'generating') txt = P.stage === 'visual' ? T.stVisual : P.stage === 'outline' ? T.stOutline : fmt(T.stPosts, P.ready, P.count);
    else if (P.status === 'paused') txt = T.stPaused; else txt = T.stReview;
    var live = P.status === 'generating', secs = live && P._t0 ? Math.round((Date.now() - P._t0) / 1000) : 0;
    prog.innerHTML = '<div class="flex flex-wrap items-center gap-3">' + (live ? '<span class="w-4 h-4 rounded-full border-2 border-brand-600 border-t-transparent animate-spin"></span>' : '') + '<b class="text-ink-900">' + esc(txt) + '</b>' + (live && secs > 5 ? '<span class="text-xs text-ink-400 tabular-nums">' + esc(fmt(T.elapsed, secs)) + (P.busy ? ' · ' + esc(T.busyBg) : '') + '</span>' : '') + '<span class="flex-1"></span>'
      + (P.status === 'paused' || (P.status === 'generating' && P.error) ? '<button type="button" id="cp-resume" class="btn-primary !py-1.5">' + esc(T.resume) + '</button>' : '') + ((P.status === 'generating' || P.status === 'paused') ? '<button type="button" id="cp-stop" class="btn-ghost !py-1.5 !text-rose-600 hover:!bg-rose-50">' + esc(T.stop) + '</button>' : '') + '</div>'
      + '<div class="mt-2 h-2 rounded-full bg-ink-100 overflow-hidden">' + (live && !pct ? '<div class="h-full w-1/3 bg-brand-600 rounded-full animate-pulse"></div>' : '<div class="h-full bg-brand-600 transition-all" style="width:' + pct + '%"></div>') + '</div>'
      + (P.error ? '<p class="text-xs text-red-600 mt-2">' + esc(P.error) + '</p>' : '')
      + logHtml()
      + (note ? '<p class="text-xs text-amber-700 mt-2">' + esc(note) + '</p>' : (P.status === 'generating' ? '<p class="text-xs text-ink-500 mt-2">' + esc(T.bg) + '</p>' : ''));
    scrollLog();
  }
  function logHtml() { var l = (P.log || []).slice(-60); if (!l.length) return '';
    var c = { ok: 'text-lime-700', err: 'text-red-600', warn: 'text-amber-700', info: 'text-ink-600' };
    return '<details class="mt-3" open><summary class="cursor-pointer text-xs font-bold text-ink-500">' + esc(T.logT) + ' (' + (P.log || []).length + ')</summary><ol id="cp-log" class="mt-1 max-h-56 overflow-y-auto rounded-xl bg-ink-50 p-2 text-xs space-y-0.5">'
      + l.map(function (x) { return '<li class="flex gap-2 ' + (c[x.k] || '') + '"><span class="text-ink-400 tabular-nums shrink-0" dir="ltr">' + esc(x.t) + '</span><span class="shrink-0 text-ink-400">[' + esc(x.src === 'bg' ? T.srcBg : T.srcPage) + ']</span><span class="min-w-0 break-words" dir="auto">' + esc(x.m) + '</span></li>'; }).join('') + '</ol></details>'; }
  function scrollLog() { var o = document.getElementById('cp-log'); if (o) o.scrollTop = o.scrollHeight; }
  function loop() {
    if (running) return; running = true;
    (function next() {
      if (P.status !== 'generating') { running = false; return; }
      P._t0 = P._t0 || Date.now(); var tick = setInterval(function () { paintProg({}); }, 1000), poll = setInterval(function () { post({ action: 'status' }).then(function (s) { if (s.ok) { P.log = s.log; P.ready = s.ready; P.human = s.human; } }).catch(function () {}); }, 4000);
      var stopT = function () { clearInterval(tick); clearInterval(poll); };
      post({ action: 'step' }).then(function (j) {
        stopT(); P._t0 = 0; P.busy = !!j.busy;
        if (!j.ok) { running = false; paintProg({}, j.error || T.err); return; }
        var before = P.ready; paintProg(j);
        if (j.ready !== before || j.human !== P._h || j.topics !== items.length) { P._h = j.human; load(); }
        if (j.status !== 'generating') { running = false; load(); return; }
        setTimeout(next, j.busy ? 4000 : j.error ? 6000 : 300);
      }).catch(function () { stopT(); P._t0 = 0; // رد مش JSON (مهلة السيرفر 504/502) أو النت فصل: نعيد المحاولة لوحدنا، والخطأ بيتسجل على السيرفر
        paintProg({}, T.offline); setTimeout(function () { post({ action: 'status' }).then(function (s) { if (s.ok) paintProg(s); running = false; loop(); }).catch(function () { running = false; setTimeout(loop, 15000); }); }, 5000); });
    })();
  }
  window.addEventListener('online', function () { paintProg({}); loop(); });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) post({ action: 'status' }).then(function (j) { if (j.ok) { paintProg(j); load(); loop(); } }); });
  document.addEventListener('click', function (e) { if (e.target.id === 'cp-resume') post({ action: 'resume' }).then(function () { P.status = 'generating'; P.error = ''; paintProg({}); loop(); }); });
  document.addEventListener('click', function (e) { if (e.target.id === 'cp-stop') { if (!confirm(T.stopAsk)) return; post({ action: 'stop' }).then(function (j) { if (j && j.ok) { running = false; P.status = 'review'; if (j.log) P.log = j.log; paintProg(j); load(); } }); } });

  /* ---------- المراجعة ---------- */
  var list = $('#cp-list'), bar = $('#cp-bar');
  function words(s) { return (String(s || '').trim().match(/\S+/g) || []).length; }
  function block(label, text, field, it, en) {
    if (!text && field !== 'linkedin') return '';
    var meta = field === 'linkedin' ? fmt(T.words, words(text)) : field === 'x' ? fmt(T.chars, [].slice.call(text || '').length) : '';
    return '<section class="rounded-xl border border-ink-200 p-3 min-w-0"><div class="flex items-center gap-2 mb-1"><b class="text-xs text-ink-500">' + esc(label) + '</b><span class="text-[11px] ' + (field === 'x' && [].slice.call(text || '').length > 280 ? 'text-red-600' : 'text-ink-400') + '">' + esc(meta) + '</span><span class="flex-1"></span>'
      + '<button type="button" data-copy="' + field + '" class="text-xs link">' + esc(T.copy) + '</button>' + (it.status === 'draft' || it.status === 'approved' || it.status === 'rejected' ? ' <button type="button" data-edit="' + field + '" class="text-xs link">' + esc(T.edit) + '</button>' : '')
      + ((field === 'image_prompt' || field === 'video_prompt') && ['draft', 'approved', 'rejected'].indexOf(it.status) >= 0 ? ' <button type="button" data-gen1="' + (field === 'image_prompt' ? 'image' : 'video') + '" class="text-xs font-semibold text-brand-700 hover:underline">' + esc(T.genOne) + '</button>' : '') + '</div>'
      + '<div class="cp-txt text-sm whitespace-pre-wrap break-words" ' + (en ? 'dir="ltr"' : 'dir="auto"') + '>' + esc(text) + '</div></section>';
  }
  function card(it) {
    var t = it.topic || {}, st = it.status, ready = it.linkedin != null;
    var badge = st === 'rejected' ? 'badge-red' : st === 'pending' ? 'badge-amber' : st === 'draft' ? '' : 'badge-green';
    var h = '<article class="cb-card card p-4" data-id="' + it.id + '"><header class="flex flex-wrap items-start gap-2 mb-3">'
      + (ready && ['draft', 'approved', 'rejected'].indexOf(st) >= 0 ? '<input type="checkbox" class="cp-sel mt-1.5"' + (sel[it.id] ? ' checked' : '') + '>' : '')
      + '<div class="min-w-0 flex-1"><b class="text-ink-900">' + it.seq + '. ' + esc(t.title || '') + '</b><div class="text-xs text-ink-500">' + esc([t.angle, t.service, t.audience, (T.cats || {})[t.category] || t.category, t.objective].filter(Boolean).join(' · ')) + '</div></div>'
      + (+it.humanized ? '<span class="text-[11px] text-ink-400" title="Humanizer">✓ ' + esc(T.human) + '</span>' : '') + '<span class="badge ' + badge + '">' + esc(T['s_' + st] || st) + '</span></header>';
    if (!ready) return h + '<div class="h-16 rounded-xl bg-ink-50 animate-pulse"></div></article>';
    if ((it.qc || []).length) h += '<div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"><b>' + esc(T.qc) + '</b> ' + it.qc.map(esc).join(' · ') + '</div>';
    h += '<div class="grid lg:grid-cols-2 gap-3">' + block(T.li, it.linkedin, 'linkedin', it) + block(T.x, it.x, 'x', it)
      + block(T.img + (it.headline ? ' — «' + it.headline + '»' : ''), it.image_prompt, 'image_prompt', it, true) + block(T.vid, it.video_prompt, 'video_prompt', it, true) + '</div>';
    if ((it.carousel || []).length) h += '<details class="mt-3 rounded-xl border border-ink-200 p-3"><summary class="cursor-pointer text-xs font-bold text-ink-500 flex items-center gap-2"><span>' + esc(T.car) + '</span>' + (['draft', 'approved', 'rejected'].indexOf(it.status) >= 0 ? '<button type="button" data-gen1="carousel" class="text-xs font-semibold text-brand-700 hover:underline ms-auto">' + esc(T.genOne) + '</button>' : '') + '</summary><ol class="mt-2 grid sm:grid-cols-5 gap-2">'
      + it.carousel.map(function (s, i) { return '<li class="rounded-lg bg-ink-50 p-2 text-sm"><div class="text-[11px] text-ink-400">' + (i + 1) + ' · ' + esc(s.role) + '</div><b class="block">' + esc(s.title) + '</b><div class="text-xs text-ink-600">' + esc(s.body) + '</div><div class="text-[11px] text-ink-400 mt-1" dir="ltr">' + esc(s.visual) + (s.layout ? '<br><i>' + esc(s.layout) + '</i>' : '') + '</div></li>'; }).join('') + '</ol></details>';
    h += mediaHtml(it);
    h += '<footer class="flex flex-wrap gap-2 mt-3">';
    if (st === 'draft' || st === 'rejected') h += '<button type="button" data-st="approved" class="!py-1.5 px-3 rounded-xl font-semibold text-[#fff]" style="background:#16a34a">' + esc(T.toBankOne) + '</button>';
    if (st === 'draft' || st === 'approved') h += '<button type="button" data-st="rejected" class="btn-ghost !py-1.5">' + esc(T.reject) + '</button>';
    if (st === 'approved' || st === 'rejected') h += '<button type="button" data-st="draft" class="btn-ghost !py-1.5">' + esc(T.undo) + '</button>';
    if (['draft', 'rejected', 'approved'].indexOf(st) >= 0) h += '<button type="button" data-regen class="btn-ghost !py-1.5">' + esc(T.regen) + '</button>';
    return h + '</footer></article>';
  }
  var jobs = {};
  function murl(id, th) { return C.media + (C.media.indexOf('?') >= 0 ? '&' : '?') + 'file=' + id + (th ? '&thumb=1' : ''); }
  function designerUrl(mid, head) { return 'index.php?p=designer&img=' + encodeURIComponent('index.php?p=media&file=' + mid) + '&title=' + encodeURIComponent(String(head || '').replace(/[«»]/g, '')); }
  function mediaHtml(it) {
    var js = jobs[it.id] || {}, chips = '';
    ['image', 'video', 'carousel'].forEach(function (k) { var j = js[k]; if (!j) return; var lab = T['k' + k[0].toUpperCase() + k.slice(1)], bad = j.failed && !j.done && !j.busy;
      var can = j.done && !j.busy && it.fmt !== k, on = it.fmt === k;
      chips += '<' + (can ? 'button type="button" data-fmt="' + k + '"' : 'span') + ' class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs ' + (on ? 'ring-2 ring-lime-500 ' : '') + (j.busy ? 'bg-brand-50 text-brand-700' : bad ? 'bg-red-50 text-red-700' : 'bg-lime-50 text-lime-700') + '"' + (j.err ? ' title="' + esc(j.err) + '"' : can ? ' title="' + esc(T.useFmt) + '"' : on ? ' title="' + esc(T.inUse) + '"' : '') + '>'
        + (j.busy ? '<span class="w-3 h-3 rounded-full border-2 border-current border-t-transparent animate-spin"></span>' : bad ? '✕' : '✓') + ' ' + esc(lab) + (j.total > 1 ? ' ' + j.done + '/' + j.total : '') + ' · ' + esc(j.busy ? T.jBusy : bad ? T.jFail : T.jDone) + '</' + (can ? 'button' : 'span') + '>'; });
    var ms = it.media_ids || [], fm = it.fmt ? T['k' + it.fmt[0].toUpperCase() + it.fmt.slice(1)] : T.fmtText;
    if (!chips && !ms.length) return '';
    var canLogo = ms.length && it.fmt && it.fmt !== 'video' && P && P.has_logo;
    var canTxt = ms.length && it.fmt === 'image';
    var rawId = (it.media_raw || [])[0] || ms[0];
    return '<div class="mt-3 rounded-xl border border-ink-200 p-3"><div class="flex flex-wrap items-center gap-2 mb-2"><b class="text-xs text-ink-500">' + esc(fm) + '</b>' + chips
      + (canTxt || canLogo ? '<span class="flex-1"></span>' : '')
      + (canTxt ? '<button type="button" data-itxt-open class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold bg-brand-600 text-[#fff] hover:bg-brand-700">🎨 ' + esc(T.itAdj) + '</button>' : '') + '</div>'
      + (ms.length ? '<div class="flex flex-wrap gap-2">' + ms.map(function (m) { return it.fmt === 'video' ? '<video src="' + esc(murl(m)) + '" controls preload="metadata" playsinline class="w-36 h-48 rounded-lg bg-ink-900 border border-ink-200 object-cover"></video>' : '<a href="' + esc(murl(m)) + '" target="_blank"><img src="' + esc(murl(m, 1)) + '" class="w-24 h-32 object-cover rounded-lg border border-ink-200" alt=""></a>'; }).join('') + '</div>' : '') + '</div>';
  }
  var fl = $('#cp-float');
  function genLoop() { if (genLoop.on) return; genLoop.on = true;
    (function next() { post({ action: 'jobs' }).then(function (j) { if (!j.ok) { genLoop.on = false; return; } jobs = j.jobs.items || {}; if (j.log) { P.log = j.log; paintProg({}); }
      load(); if (j.jobs.pending) setTimeout(next, j.did ? 300 : 5000); else genLoop.on = false; })
      .catch(function () { setTimeout(function () { genLoop.on = false; genLoop(); }, 8000); }); })(); }
  function paint() {
    var vis = items.filter(function (i) { return !filter || (filter === 'qc' ? (i.qc || []).length : i.status === filter); });
    list.innerHTML = vis.length ? vis.map(card).join('') : (items.length ? '<div class="card"><div class="ds-empty"><div class="ds-empty-ico">' + (window.uic ? uic('inbox', 'w-6 h-6') : '') + '</div>' + esc(T.emptyHere || '') + '</div></div>' : '');
    var ready = items.filter(function (i) { return i.linkedin != null; }).length; bar.classList.toggle('hidden', !ready);
    var n = Object.keys(sel).length, ap = items.filter(function (i) { return ['approved', 'scheduled', 'queued', 'published'].indexOf(i.status) >= 0; }).length;
    fl.classList.toggle('hidden', !n); $('#cp-fn').textContent = fmt(T.fSel, n);
    var tb = $('#cp-tobank'); tb.classList.toggle('hidden', !ap); tb.textContent = fmt(T.nApproved, ap) + ' ←';
  }
  function load() { return post({ action: 'items' }).then(function (j) { if (!j.ok) return; items = j.items; jobs = (j.jobs || {}).items || {}; if ((j.jobs || {}).pending) genLoop(); Object.keys(sel).forEach(function (k) { if (!items.some(function (i) { return i.id == k; })) delete sel[k]; }); paint(); }); }
  function setSt(ids, st) { return post({ action: 'set', ids: JSON.stringify(ids), status: st }).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); ids.forEach(function (id) { delete sel[id]; }); load(); }).catch(function () { toast(T.offline, 'error'); }); }
  $('#cp-f').addEventListener('change', function () { filter = this.value; paint(); });
  $('#cp-all').addEventListener('change', function () { var on = this.checked; sel = {}; if (on) items.forEach(function (i) { if (i.linkedin != null && ['draft', 'approved', 'rejected'].indexOf(i.status) >= 0 && (!filter || (filter === 'qc' ? (i.qc || []).length : i.status === filter))) sel[i.id] = 1; }); paint(); });
  fl.addEventListener('click', function (e) {
    var b = e.target.closest('[data-bulk]'), ids = Object.keys(sel).map(Number);
    if (b) { var st = b.dataset.bulk; return setSt(ids, st).then(function () { if (st === 'approved') toast(fmt(T.sent, ids.length)); }); }
    if (e.target.closest('#cp-fx')) { sel = {}; return paint(); }
    var g = e.target.closest('[data-gen]'); if (!g) return; var k = g.dataset.gen, lab = T['k' + k[0].toUpperCase() + k.slice(1)];
    (window.fxAsk ? fxAsk(fmt(T.genAsk, lab, ids.length)) : Promise.resolve(confirm(fmt(T.genAsk, lab, ids.length)))).then(function (ok) { if (!ok) return;
      post({ action: 'gen', ids: JSON.stringify(ids), kind: k }).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); if (!j.n) return toast(T.genNone, 'info'); toast(fmt(T.genAdded, j.n)); jobs = j.jobs.items || {}; paint(); genLoop(); }).catch(function () { toast(T.offline, 'error'); }); });
  });
  list.addEventListener('change', function (e) { if (!e.target.classList.contains('cp-sel')) return; var id = e.target.closest('article').dataset.id; if (e.target.checked) sel[id] = 1; else delete sel[id]; paint(); });
  list.addEventListener('click', function (e) {
    var art = e.target.closest('article'); if (!art) return; var id = +art.dataset.id, it = items.find(function (i) { return i.id === id; }); if (!it) return;
    var b;
    if ((b = e.target.closest('[data-st]'))) return setSt([id], b.dataset.st);
    if ((b = e.target.closest('[data-copy]'))) { navigator.clipboard.writeText(it[b.dataset.copy] || '').then(function () { toast(T.copied); }); return; }
    if (e.target.closest('[data-ilogo-open]')) { iOpen(id); return; }
    if (e.target.closest('[data-itxt-open]')) { tOpen(id); return; }
    if ((b = e.target.closest('[data-fmt]'))) { post({ action: 'usefmt', id: id, kind: b.dataset.fmt }).then(function () { load(); }); return; }
    if ((b = e.target.closest('[data-gen1]'))) { var k = b.dataset.gen1, lab = T['k' + k[0].toUpperCase() + k.slice(1)];
      (window.fxAsk ? fxAsk(fmt(T.genAsk, lab, 1)) : Promise.resolve(confirm(fmt(T.genAsk, lab, 1)))).then(function (ok) { if (!ok) return;
        post({ action: 'gen', ids: JSON.stringify([id]), kind: k }).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); if (!j.n) return toast(T.genNone, 'info'); toast(fmt(T.genAdded, j.n)); jobs = j.jobs.items || {}; paint(); genLoop(); }).catch(function () { toast(T.offline, 'error'); }); }); return; }
    if (e.target.closest('[data-regen]')) { post({ action: 'regen', id: id }).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); P.status = 'generating'; load(); loop(); }); return; }
    if ((b = e.target.closest('[data-edit]'))) {
      var f = b.dataset.edit, box = $('.cp-txt', b.closest('section'));
      box.outerHTML = '<div class="cp-ed"><textarea class="input text-sm" rows="' + (f === 'x' ? 3 : 8) + '" dir="' + (f.indexOf('prompt') >= 0 ? 'ltr' : 'auto') + '">' + esc(it[f]) + '</textarea><div class="flex gap-2 mt-2"><button type="button" data-save="' + f + '" class="btn-primary !py-1">' + esc(T.save) + '</button><button type="button" data-cancel class="btn-ghost !py-1">' + esc(T.cancel) + '</button></div></div>';
      return;
    }
    if (e.target.closest('[data-cancel]')) return paint();
    if ((b = e.target.closest('[data-save]'))) { var o = {}; o[b.dataset.save] = $('textarea', b.closest('.cp-ed')).value; b.disabled = true;
      post({ action: 'save', id: id, f: JSON.stringify(o) }).then(function (j) { if (!j.ok) { b.disabled = false; return toast(j.error || T.err, 'error'); } Object.assign(it, j.item); paint(); toast(T.saved); }).catch(function () { b.disabled = false; toast(T.offline, 'error'); }); }
  });
  $('#cp-del').addEventListener('click', function () { (window.fxAsk ? fxAsk(T.delPlan) : Promise.resolve(confirm(T.delPlan))).then(function (ok) { if (ok) post({ action: 'delete' }).then(function () { location.href = C.url; }); }); });
  var imp = $('#cp-imp'); if (imp) imp.addEventListener('change', function () { var f = this.files[0]; this.value = ''; if (!f) return;
    var fd = new FormData(); fd.append('_csrf', C.csrf); fd.append('action', 'import'); fd.append('file', f);
    fetch(C.url + '&plan=' + P.id, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); toast(fmt(T.imported, j.updated, j.skipped)); load(); }).catch(function () { toast(T.offline, 'error'); }); });
  /* ---------- محرر اللوجو لكل صورة ---------- */
  var iMod = $('#cp-ilogo'), iImg = $('#il-logo'), iBox = $('#il-prev'), iPlate = $('#il-plate'), iState = null, iImgIx = 0, iDrag = null;
  function iGet(n) { return iMod.querySelector('[data-ilogo="' + n + '"]'); }
  function iRadio(n) { return (iMod.querySelector('[name=' + n + ']:checked') || {}).value; }
  function iBoxCalc(bw, bh, ar) { var w = +iGet('logo_w').value / 100 * bw, h = w / ar, mh = 0.18 * bh; if (h > mh) { h = mh; w = h * ar; }
    var cx = Math.min(Math.max(iState.x / 100 * bw, 0), bw), cy = Math.min(Math.max(iState.y / 100 * bh, 0), bh);
    return { x: cx - w / 2, y: cy - h / 2, w: w, h: h }; }
  function iPrev() { if (!iState) return;
    if (iState.images[iImgIx]) iBox.style.backgroundImage = 'url("' + iState.images[iImgIx] + '")';
    $('#il-imgs').innerHTML = iState.images.length > 1 ? iState.images.map(function (u, i) { return '<button type="button" data-ilbg="' + i + '" class="w-8 h-10 rounded border-2 ' + (i === iImgIx ? 'border-brand-500' : 'border-transparent') + ' bg-cover bg-center" style="background-image:url(\'' + esc(u) + '\')"></button>'; }).join('') : '';
    var ar = (iImg.naturalWidth || 3) / (iImg.naturalHeight || 1), b = iBoxCalc(iBox.clientWidth, iBox.clientHeight, ar);
    iImg.style.left = b.x + 'px'; iImg.style.top = b.y + 'px'; iImg.style.width = b.w + 'px'; iImg.style.height = b.h + 'px';
    iImg.style.opacity = +iGet('logo_op').value / 100;
    var tint = iRadio('il_tint'), plate = iRadio('il_plate');
    iImg.style.filter = tint === 'white' ? 'brightness(0) invert(1)' : tint === 'black' ? 'brightness(0)' : '';
    var p = b.h * 0.3; iPlate.style.left = (b.x - p) + 'px'; iPlate.style.top = (b.y - p) + 'px'; iPlate.style.width = (b.w + 2 * p) + 'px'; iPlate.style.height = (b.h + 2 * p) + 'px';
    iPlate.style.background = plate === 'light' ? 'rgba(255,255,255,.85)' : plate === 'dark' ? 'rgba(0,0,0,.55)' : 'transparent';
    $('#il-w-v').textContent = iGet('logo_w').value + '%'; $('#il-o-v').textContent = iGet('logo_op').value + '%';
    $$('[data-ilp]', iMod).forEach(function (x) { x.classList.toggle('on', x.dataset.ilp === Math.round(iState.x) + ',' + Math.round(iState.y)); }); }
  function iClose() { iMod.classList.add('hidden'); document.body.style.overflow = ''; iState = null; }
  function iOpen(id) { post({ action: 'logoinfo', id: id }).then(function (j) {
    if (!j.ok) return toast(T.err, 'error'); if (!j.logo) return toast(T.ilNone, 'info');
    iState = { id: id, x: j.pos.logo_x, y: j.pos.logo_y, images: j.images || [] }; iImgIx = 0;
    iGet('logo_w').value = j.pos.logo_w; iGet('logo_op').value = j.pos.logo_op;
    (iMod.querySelector('[name=il_tint][value="' + (j.pos.logo_tint || 'orig') + '"]') || {}).checked = true;
    (iMod.querySelector('[name=il_plate][value="' + (j.pos.logo_plate || 'none') + '"]') || {}).checked = true;
    iImg.onload = iPrev; iImg.src = j.logo; iMod.classList.remove('hidden'); document.body.style.overflow = 'hidden'; iPrev();
  }).catch(function () { toast(T.offline, 'error'); }); }
  if (iMod) {
    iImg.addEventListener('pointerdown', function (e) { e.preventDefault(); iImg.setPointerCapture(e.pointerId); iDrag = iBox.getBoundingClientRect(); });
    iImg.addEventListener('pointermove', function (e) { if (!iDrag || !iState) return;
      iState.x = Math.min(100, Math.max(0, (e.clientX - iDrag.left) / iDrag.width * 100)); iState.y = Math.min(100, Math.max(0, (e.clientY - iDrag.top) / iDrag.height * 100)); iPrev(); });
    iImg.addEventListener('pointerup', function () { iDrag = null; });
    iMod.addEventListener('click', function (e) { if (e.target === iMod) return iClose();
      var lp = e.target.closest('[data-ilp]'); if (lp) { var v = lp.dataset.ilp.split(','); iState.x = +v[0]; iState.y = +v[1]; return iPrev(); }
      var bg = e.target.closest('[data-ilbg]'); if (bg) { iImgIx = +bg.dataset.ilbg; return iPrev(); } });
    iMod.addEventListener('input', function () { iPrev(); });
    iMod.addEventListener('change', function () { iPrev(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !iMod.classList.contains('hidden')) iClose(); });
    $('#il-save').addEventListener('click', function () { if (!iState) return; var btn = this; btn.disabled = true; var old = btn.textContent; btn.textContent = T.ilBusy;
      var lopts = { logo_x: Math.round(iState.x), logo_y: Math.round(iState.y), logo_w: +iGet('logo_w').value, logo_op: +iGet('logo_op').value, logo_tint: iRadio('il_tint'), logo_plate: iRadio('il_plate') };
      post({ action: 'logo', id: iState.id, logo: JSON.stringify(lopts) }).then(function (j) { btn.disabled = false; btn.textContent = old;
        if (!j.ok) return toast(T.err, 'error'); iClose(); toast(T.ilSaved); load(); }).catch(function () { btn.disabled = false; btn.textContent = old; toast(T.offline, 'error'); }); });
  }
  /* ---------- محرّر التصميم المدمج (Fabric) داخل البوب أب ---------- */
  var tMod = $('#cp-itxt'), fc = null, FW = 1080, FH = 1350, FONTS = ['Cairo', 'Alexandria', 'Tajawal'];
  function fcFit() { if (!fc) return; var wrap = $('#it-cwrap'); var dw = (wrap && wrap.clientWidth) || 420; fc.setZoom(dw / FW); fc.setDimensions({ width: dw, height: Math.round(dw * FH / FW) }); }
  function fcActive() { return fc && fc.getActiveObject(); }
  // لفّ العنوان الطويل لأسطر تملأ عرض المربع (Fabric لا يلفّ العربي RTL تلقائيًا) مع تصغير الحجم إن لزم
  function cpMeasure(px) { var c = cpMeasure._c || (cpMeasure._c = document.createElement('canvas').getContext('2d')); c.font = '900 ' + px + 'px Cairo, Alexandria, sans-serif'; return c; }
  function cpWrap(s, maxW, px) { var ctx = cpMeasure(px), ws = s.split(' '), lines = [], cur = ''; for (var i = 0; i < ws.length; i++) { var t = cur ? cur + ' ' + ws[i] : ws[i]; if (ctx.measureText(t).width <= maxW || !cur) cur = t; else { lines.push(cur); cur = ws[i]; } } if (cur) lines.push(cur); return lines; }
  function layoutHead(tb) { if (!tb || !tb.dzHead || !fc) return; var src = (tb.dzSrc || tb.text || '').replace(/\n/g, ' ').replace(/\s+/g, ' ').trim(); if (!src) return; var bw = tb.dzBw || tb.width, fsz = tb.fontSize, lines;
    for (var g = 0; g < 14; g++) { lines = cpWrap(src, bw, fsz); if ((lines.length <= 4 && fsz * 1.5 * lines.length <= FH * 0.30) || fsz <= 34) break; fsz = Math.max(34, fsz - 5); }
    tb.set({ fontSize: fsz, text: lines.join('\n') }); if (tb.initDimensions) tb.initDimensions();
    var bottom = FH * 0.93; tb.set({ top: Math.max(FH * 0.48, bottom - tb.height) }); if (tb.initDimensions) tb.initDimensions();
    if (tb.dzKickRef) tb.dzKickRef.set({ top: tb.top - FW * 0.045, left: (tb.dzBl || 0) + (tb.dzBw || bw) - (tb.dzKw || FW * 0.14) }); fc.requestRenderAll(); }
  // خانة النص الخارجية (التعديل المباشر بيبوّظ تحديد العربي في Fabric)
  function fcFocusTxt() { var inp = $('#dz-txt'); if (inp && !inp.disabled) { try { inp.focus(); inp.setSelectionRange(inp.value.length, inp.value.length); } catch (e) {} } }
  function fcTxtSync() { var inp = $('#dz-txt'); var o = fcActive(); if (inp) { if (o && o.type === 'textbox') { inp.disabled = false; inp.value = o.text || ''; } else { inp.value = ''; inp.disabled = true; } } var sz = $('#dz-size'), szv = $('#dz-size-v'); if (sz) { var fs = (o && o.type === 'textbox') ? Math.round((o.fontSize || 80) * (o.scaleY || 1)) : 80; sz.value = fs; if (szv) szv.textContent = fs; } var dc = $('#dz-color'); if (dc && o && o.fill && typeof o.fill === 'string' && o.fill[0] === '#') dc.value = o.fill; }
  // Fabric بيرسم العربي مكسور لو الخط لسه ما اتحمّلش؛ نعيد قياس كل نص بعد تحميل الخطوط
  function fcRefreshText() { if (!fc) return; fc.getObjects().forEach(function (o) { if (o.type === 'textbox' || o.type === 'i-text' || o.type === 'text') { o.set('dirty', true); if (o.initDimensions) o.initDimensions(); } }); fc.requestRenderAll(); }
  function fcEnsureFonts() { if (!document.fonts || !document.fonts.load) return Promise.resolve(); return Promise.all(['900 64px Cairo', '700 64px Cairo', '400 64px Cairo', '800 64px Alexandria', '700 64px Tajawal'].map(function (f) { return document.fonts.load(f).catch(function () {}); })); }
  // خصائص المستند القابل للإعادة (بدون مراجع دائرية مثل dzKickRef)
  var DOC_PROPS = ['dzLogo', 'dzBg', 'dzKick', 'dzHead', 'dzBw', 'dzSrc', 'dzBl', 'dzKw', 'selectable', 'evented', 'editable', 'direction', 'splitByGrapheme'];
  var MIN_READ = 30; // أصغر حجم خط مقروء (في فضاء 1080)
  // يحسب الإطار الفعلي لعنصر (بعد المقياس) — زاوية 0 في محررنا
  function fcBBox(o) { var w = (o.width || 0) * (o.scaleX || 1), h = (o.height || 0) * (o.scaleY || 1); var l = o.left || 0, t = o.top || 0; if (o.originX === 'center') l -= w / 2; if (o.originY === 'center') t -= h / 2; return { l: l, t: t, r: l + w, b: t + h, w: w, h: h }; }
  // يعيد ملاءمة كل النصوص داخل حدود الصورة؛ يرجّع { severe:[], warn:[] }
  function fcFitAll() { if (!fc) return { severe: [], warn: [] }; var mg = FW * 0.04, severe = [], warn = [];
    fc.getObjects().forEach(function (o) {
      if (o.dzHead && typeof layoutHead === 'function') layoutHead(o);
      if (o.type !== 'textbox' && o.type !== 'i-text' && o.type !== 'text') return;
      var eff = (o.fontSize || 0) * (o.scaleY || 1);
      // اقنص داخل الحدود
      var bb = fcBBox(o);
      if (bb.w < FW && bb.l < mg) { o.set('left', (o.left || 0) + (mg - bb.l)); }
      bb = fcBBox(o); if (bb.w < FW && bb.r > FW - mg) { o.set('left', (o.left || 0) - (bb.r - (FW - mg))); }
      bb = fcBBox(o); if (bb.h < FH && bb.t < mg) { o.set('top', (o.top || 0) + (mg - bb.t)); }
      bb = fcBBox(o); if (bb.h < FH && bb.b > FH - mg) { o.set('top', (o.top || 0) - (bb.b - (FH - mg))); }
      if (o.setCoords) o.setCoords();
      bb = fcBBox(o);
      var out = bb.l < -2 || bb.t < -2 || bb.r > FW + 2 || bb.b > FH + 2; // خارج حدود الصورة فعلاً = خطأ خطير
      if (out) severe.push(o); else if (eff && eff < MIN_READ) warn.push(o); // داخل الحدود لكن صغير جدًا = تحذير
    });
    fc.requestRenderAll(); return { severe: severe, warn: warn };
  }
  // تصدير التصميم: صورة PNG + مستند JSON، بعد ضمان تحميل الخطوط + فحص التخطيط (يمنع عند خطأ خطير)
  function fcExport(cb) { if (!fc) { cb(null, null, { severe: [], warn: [] }); return; } fc.discardActiveObject(); var z = fc.getZoom(); fc.setZoom(1); fc.setDimensions({ width: FW, height: FH });
    var fin = function () { fcRefreshText(); var issues = fcFitAll(); fc.renderAll(); var data = fc.toDataURL({ format: 'png', multiplier: 1 }); var doc = ''; try { doc = JSON.stringify(fc.toJSON(DOC_PROPS)); } catch (e) {} fc.setZoom(z); fcFit(); cb(data, doc, issues); };
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fin).catch(fin); else fin(); }
  // ====== سجل التراجع/الإعادة + الحفظ التلقائي للمسودة ======
  var hist = [], histIx = -1, histLock = false, draftKey = null, snapT = null;
  function histSnap() { if (!fc || histLock) return; var s; try { s = JSON.stringify(fc.toJSON(DOC_PROPS)); } catch (e) { return; } if (hist[histIx] === s) return; hist = hist.slice(0, histIx + 1); hist.push(s); if (hist.length > 40) hist.shift(); histIx = hist.length - 1; if (hist.length > 1) autosaveDraft(s); }
  // بعد تحميل التصميم: جهّز السجل، واسترجع آخر مسودة غير محفوظة إن وُجدت (بعد انقطاع)
  function tAfterLoad() { histInit(); setTimeout(function () { var d = readDraft(); if (d && d.s) { histApply(d.s); setTimeout(histSnap, 500); toast(T.draftBack || 'تم استرجاع آخر تعديل غير محفوظ', 'info'); } }, 500); }
  function histSnapSoon() { clearTimeout(snapT); snapT = setTimeout(histSnap, 350); }
  function histApply(s) { if (!s) return; histLock = true; fc.loadFromJSON(s, function () { fcFit(); fcEnsureFonts().then(function () { fcRefreshText(); fc.requestRenderAll(); }); fc.requestRenderAll(); histLock = false; }); }
  function histInit() { hist = []; histIx = -1; setTimeout(function () { histSnap(); }, 300); }
  function undo() { if (histIx > 0) { histIx--; histApply(hist[histIx]); } }
  function redo() { if (histIx < hist.length - 1) { histIx++; histApply(hist[histIx]); } }
  function autosaveDraft(s) { if (!draftKey) return; try { localStorage.setItem(draftKey, JSON.stringify({ t: Date.now(), s: s })); } catch (e) {} }
  function clearDraft() { if (!draftKey) return; try { localStorage.removeItem(draftKey); } catch (e) {} }
  function readDraft() { if (!draftKey) return null; try { var d = JSON.parse(localStorage.getItem(draftKey) || 'null'); return d && d.s ? d : null; } catch (e) { return null; } }
  function tOpen(id) {
    if (!tMod) return; if (!window.fabric) { toast(T.err, 'error'); return; }
    var it = items.find(function (i) { return i.id === id; }); if (!it) return;
    tMod.dataset.id = id; tMod.classList.remove('hidden'); document.body.style.overflow = 'hidden';
    draftKey = 'cpdz_' + (C.wid || '') + '_' + id;
    if (!fc) { fc = new fabric.Canvas('it-canvas', { backgroundColor: '#0b1220', preserveObjectStacking: true, allowTouchScrolling: false });
      fc.on('selection:created', fcTxtSync); fc.on('selection:updated', fcTxtSync); fc.on('selection:cleared', fcTxtSync);
      fc.on('object:modified', histSnapSoon); fc.on('object:added', function () { if (!histLock) histSnapSoon(); }); fc.on('object:removed', function () { if (!histLock) histSnapSoon(); });
      // الكتابة المباشرة على العربي بتتكسر في Fabric، فالدبل-كليك على النص يودّي لخانة الكتابة الجانبية
      fc.on('mouse:dblclick', function (e) { var t = e && e.target; if (t && t.type === 'textbox') { fc.setActiveObject(t); fcTxtSync(); var inp = $('#dz-txt'); if (inp) { inp.disabled = false; try { inp.focus(); inp.select(); } catch (x) {} } } }); }
    fc.clear(); fc.setBackgroundImage(null, fc.renderAll.bind(fc)); fcFit();
    // لو في مستند تصميم محفوظ (تعديل يدوي سابق) نفتحه كما هو بكل طبقاته بدل ما نعيد البناء — عشان ما «يرجعش»
    if (it.has_doc) { post({ action: 'getdoc', id: id }).then(function (j) { if (j && j.ok && j.doc) { fc.loadFromJSON(j.doc, function () { fcFit(); fcEnsureFonts().then(function () { fcRefreshText(); fc.requestRenderAll(); }); if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { fcRefreshText(); fc.requestRenderAll(); }); fc.requestRenderAll(); tAfterLoad(); }); } else tBuildFresh(it); }).catch(function () { tBuildFresh(it); }); return; }
    tBuildFresh(it);
  }
  function tBuildFresh(it) {
    var acc = (P && P.accent) || '#10b981';
    var rawId = (it.media_raw || [])[0] || (it.media_ids || [])[0];
    // الصورة كاملة (full-bleed) كخلفية
    if (rawId) fabric.Image.fromURL(murl(rawId), function (im) { if (!im || !im.width) return; var k = Math.max(FW / im.width, FH / im.height); im.set({ scaleX: k, scaleY: k, originX: 'center', originY: 'center', left: FW / 2, top: FH / 2 }); fc.setBackgroundImage(im, fc.renderAll.bind(fc)); });
    // تدرّج داكن من الأسفل لإبراز النص (نفس روح قالب full)
    var scrim = new fabric.Rect({ left: 0, top: FH * 0.40, width: FW, height: FH * 0.60, selectable: false, evented: false, dzBg: true });
    scrim.set('fill', new fabric.Gradient({ type: 'linear', coords: { x1: 0, y1: 0, x2: 0, y2: FH * 0.60 }, colorStops: [{ offset: 0, color: 'rgba(6,10,18,0)' }, { offset: 0.55, color: 'rgba(6,10,18,0.55)' }, { offset: 1, color: 'rgba(6,10,18,0.92)' }] }));
    fc.add(scrim);
    // العنوان أسفل الصورة — قابل للتعديل. العناوين الطويلة تُلفّ لأسطر داخل عرض المربع (Fabric لا يلفّ العربي RTL فنلفّه بأنفسنا)
    var head0 = String(it.headline || '').replace(/[«»]/g, '').replace(/\s+/g, ' ').trim() || 'عنوان';
    var bw = FW * 0.86, bl = FW * 0.07;
    var fs = head0.split(' ').length >= 4 ? 0.075 : 0.088;
    var kw = FW * 0.14;
    var kick = new fabric.Rect({ left: bl + bw - kw, top: FH * 0.70, width: kw, height: Math.round(FW * 0.013), rx: 4, ry: 4, fill: acc, dzKick: true });
    fc.add(kick);
    var tb = new fabric.Textbox(head0, { left: bl, top: FH * 0.72, width: bw, fontSize: Math.round(FW * fs), fill: '#ffffff', fontWeight: '900', fontFamily: 'Cairo', textAlign: 'right', direction: 'rtl', splitByGrapheme: false, editable: false });
    tb.dzHead = true; tb.dzBw = bw; tb.dzSrc = head0; tb.dzKickRef = kick; tb.dzBl = bl; tb.dzKw = kw;
    fc.add(tb); fc.setActiveObject(tb); fcTxtSync(); fc.requestRenderAll();
    setTimeout(fcFocusTxt, 60);
    fcEnsureFonts().then(function () { fcRefreshText(); layoutHead(tb); fc.requestRenderAll(); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { fcRefreshText(); layoutHead(tb); fc.requestRenderAll(); });
    // اللوجو كطبقة قابلة للتحريك/التحجيم مع النص
    if (P && P.logo) fabric.Image.fromURL(P.logo, function (lg) { if (!lg || !lg.width) return; var k = (FW * 0.20) / lg.width; lg.set({ scaleX: k, scaleY: k, left: FW * 0.74, top: FH * 0.035 }); lg.dzLogo = true; fc.add(lg); fc.requestRenderAll(); });
    tAfterLoad();
  }
  function tClose() { if (tMod) { tMod.classList.add('hidden'); document.body.style.overflow = ''; } }
  if (tMod) {
    window.addEventListener('resize', function () { if (!tMod.classList.contains('hidden')) fcFit(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !tMod.classList.contains('hidden')) tClose(); });
    var dzTxt = $('#dz-txt');
    if (dzTxt) dzTxt.addEventListener('input', function () { var o = fcActive(); if (o && o.type === 'textbox') { o.set('text', dzTxt.value); if (o.initDimensions) o.initDimensions(); fc.requestRenderAll(); } });
    tMod.addEventListener('click', function (e) {
      if (e.target === tMod || e.target.closest('[data-tclose]')) return tClose();
      var b = e.target.closest('[data-dz]'); if (!b || !fc) return; var cmd = b.dataset.dz, o = fcActive();
      if (cmd === 'addtext') { var nt = new fabric.Textbox('نص جديد', { left: FW * 0.12, top: FH * 0.42, width: FW * 0.6, fontSize: Math.round(FW * 0.06), fill: ($('#dz-color') || {}).value || '#ffffff', fontFamily: 'Cairo', fontWeight: '700', textAlign: 'right', direction: 'rtl', editable: false }); fc.add(nt); fc.setActiveObject(nt); fcTxtSync(); var inp = $('#dz-txt'); if (inp) inp.select(); fc.requestRenderAll(); }
      else if (cmd === 'del' && o) { fc.remove(o); fcTxtSync(); fc.requestRenderAll(); }
      else if (cmd === 'front' && o) { o.bringToFront(); fc.requestRenderAll(); }
      else if (cmd === 'back' && o) { o.sendToBack(); fc.requestRenderAll(); }
      else if (cmd === 'dup' && o && o.clone) { o.clone(function (c) { c.set({ left: (o.left || 0) + 30, top: (o.top || 0) + 30 }); if (o.dzLogo) c.dzLogo = true; fc.add(c); fc.setActiveObject(c); fcTxtSync(); fc.requestRenderAll(); }); }
      else if (cmd === 'bold' && o && o.set) { o.set('fontWeight', (o.fontWeight === '900' || o.fontWeight === 'bold') ? '400' : '900'); fc.requestRenderAll(); }
      else if (cmd === 'font' && o && o.set) { var i = (FONTS.indexOf(o.fontFamily) + 1) % FONTS.length; o.set('fontFamily', FONTS[i]); fc.requestRenderAll(); toast(FONTS[i], 'info'); if (document.fonts && document.fonts.load) document.fonts.load('700 64px ' + FONTS[i]).then(fcRefreshText).catch(function () {}); }
      else if (cmd === 'al-right' && o && o.set) { o.set('textAlign', 'right'); fc.requestRenderAll(); }
      else if (cmd === 'al-center' && o && o.set) { o.set('textAlign', 'center'); fc.requestRenderAll(); }
      else if (cmd === 'al-left' && o && o.set) { o.set('textAlign', 'left'); fc.requestRenderAll(); }
      else if (cmd === 'undo') { undo(); }
      else if (cmd === 'redo') { redo(); }
      else if (cmd === 'lock' && o && o.set) { var lk = !o.dzLocked; o.dzLocked = lk; o.set({ lockMovementX: lk, lockMovementY: lk, lockScalingX: lk, lockScalingY: lk, lockRotation: lk, hasControls: !lk }); fc.requestRenderAll(); toast(lk ? (T.lkOn || 'مقفول') : (T.lkOff || 'مفتوح'), 'info'); histSnapSoon(); }
      else if (cmd === 'hide' && o && o.set) { o.set('visible', o.visible === false); fc.discardActiveObject(); fcTxtSync(); fc.requestRenderAll(); histSnapSoon(); }
    });
    document.addEventListener('keydown', function (e) { if (tMod.classList.contains('hidden')) return; var k = (e.key || '').toLowerCase(); if ((e.ctrlKey || e.metaKey) && k === 'z') { e.preventDefault(); if (e.shiftKey) redo(); else undo(); } else if ((e.ctrlKey || e.metaKey) && k === 'y') { e.preventDefault(); redo(); } });
    var dc = $('#dz-color'); if (dc) dc.addEventListener('input', function () { var o = fcActive(); if (o && o.set) { o.set('fill', this.value); fc.requestRenderAll(); } });
    var dsz = $('#dz-size'); if (dsz) dsz.addEventListener('input', function () { var o = fcActive(); if (o && o.set && o.type === 'textbox') { o.set('fontSize', +this.value); if (o.initDimensions) o.initDimensions(); fc.requestRenderAll(); var lbl = $('#dz-size-v'); if (lbl) lbl.textContent = this.value; } });
    $('#it-save').addEventListener('click', function () {
      if (!fc) return; var id = +tMod.dataset.id, btn = this; btn.disabled = true; var old = btn.textContent; btn.textContent = T.ilBusy;
      fcExport(function (data, doc, iss) {
        if (iss && iss.severe && iss.severe.length) { btn.disabled = false; btn.textContent = old; toast(T.layoutErr, 'error'); return; }
        if (iss && iss.warn && iss.warn.length) toast(T.layoutWarn, 'info');
        post({ action: 'savecanvas', id: id, img: data, doc: doc }).then(function (j) { btn.disabled = false; btn.textContent = old; if (!j.ok) return toast(T.err, 'error'); clearDraft(); tClose(); toast(T.itSaved); load(); }).catch(function () { btn.disabled = false; btn.textContent = old; toast(T.offline, 'error'); });
      });
    });
    var itAll = $('#it-all');
    if (itAll) itAll.addEventListener('click', function () {
      if (!fc) return; var id = +tMod.dataset.id, btn = this; btn.disabled = true; var old = btn.textContent; btn.textContent = T.ilBusy;
      // اقرأ ستايل العنوان الرئيسي (أكبر textbox) لتطبيقه على باقي المنشورات
      var heads = fc.getObjects().filter(function (o) { return o.type === 'textbox'; });
      var main = heads.sort(function (a, b) { return (b.fontSize || 0) - (a.fontSize || 0); })[0];
      var sty = {};
      if (main) { sty.txt_scale = Math.round(((main.fontSize || 80) * (main.scaleY || 1)) / (FW * 0.085) * 100); if (main.fill && typeof main.fill === 'string') sty.txt_accent = main.fill; sty.txt_align = main.textAlign || 'right'; sty.txt_y = Math.round((main.top || 0) / FH * 100); }
      // الكل أولاً (يعيد تركيب كل الصور بالنمط المشترك)، ثم احفظ تصميم المنشور الحالي يدويًا فوقه (صورة + مستند)
      fcExport(function (data, doc, iss) {
        if (iss && iss.severe && iss.severe.length) { btn.disabled = false; btn.textContent = old; toast(T.layoutErr, 'error'); return; }
        post({ action: 'retxt_all', id: id, txt: JSON.stringify(sty) }).then(function () {
          return post({ action: 'savecanvas', id: id, img: data, doc: doc });
        }).then(function (j) { btn.disabled = false; btn.textContent = old; if (!j || !j.ok) return toast(T.err, 'error'); clearDraft(); tClose(); toast(T.itSaved); load(); }).catch(function () { btn.disabled = false; btn.textContent = old; toast(T.offline, 'error'); });
      });
    });
  }
  paintProg({}); load(); loop();
})();
