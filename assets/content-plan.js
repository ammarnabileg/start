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
    var key = 'cp_draft_' + C.wid, refs = [];
    function paintRefs() { $('#cp-refs').innerHTML = refs.map(function (r, i) { return '<span class="relative"><img src="' + esc(r.thumb) + '" class="w-20 h-24 object-cover rounded-xl border border-ink-200" alt=""><button type="button" data-rm="' + i + '" class="absolute -top-1.5 -end-1.5 w-6 h-6 rounded-full bg-white border border-ink-200 text-xs">×</button></span>'; }).join(''); }
    function collect() { var d = { refs: refs.map(function (r) { return r.id; }), services: $$('[name=services]:checked', W).map(function (x) { return x.value; }) };
      ['goal', 'count', 'name', 'lang', 'dialect', 'tone', 'cta', 'audience', 'avoid'].forEach(function (n) { d[n] = W[n].value; }); d.video = W.video.checked; d.carousel = W.carousel.checked; d.humanize = W.humanize.checked; return d; }
    var dr = ls(key);
    if (dr && (dr.goal || (dr.refsFull || []).length)) {
      ['goal', 'count', 'name', 'lang', 'dialect', 'tone', 'cta', 'audience', 'avoid'].forEach(function (n) { if (dr[n] != null) W[n].value = dr[n]; });
      W.video.checked = dr.video !== false; W.carousel.checked = dr.carousel !== false; W.humanize.checked = dr.humanize !== false;
      $$('[name=services]', W).forEach(function (x) { x.checked = (dr.services || []).indexOf(x.value) >= 0; });
      refs = dr.refsFull || []; paintRefs(); toast(T.draftRestored, 'info');
    }
    function saveDraft() { var d = collect(); d.refsFull = refs; ls(key, d); }
    W.addEventListener('input', saveDraft); W.addEventListener('change', saveDraft);
    $('#cp-refs').addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (!b) return; refs.splice(+b.dataset.rm, 1); paintRefs(); saveDraft(); });
    // PDF مرجعي: كل صفحة تتحول لصورة JPEG في المتصفح، فالذكاء الاصطناعي يشوفها زي الصور بالظبط
    function pdfPages(f, max) { return window.extractKit.loadPdf().then(function (lib) { return f.arrayBuffer().then(function (b) { return lib.getDocument({ data: b }).promise; }); }).then(function (doc) {
      var out = [], n = Math.min(doc.numPages, max);
      return (function pg(i) { if (i > n) return out; return doc.getPage(i).then(function (page) { var v = page.getViewport({ scale: 1 }), sc = Math.min(2, 1400 / Math.max(v.width, v.height)), vp = page.getViewport({ scale: sc }), c = document.createElement('canvas'); c.width = vp.width; c.height = vp.height;
        return page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise.then(function () { return new Promise(function (ok) { c.toBlob(function (bl) { out.push(new File([bl], f.name.replace(/\.pdf$/i, '') + '-p' + i + '.jpg', { type: 'image/jpeg' })); ok(); }, 'image/jpeg', 0.88); }); }).then(function () { return pg(i + 1); }); }); })(1); }); }
    // صورة كبيرة (كاميرا/تصميم 10MB) بتتصغر في المتصفح لـ 1600 بكسل قبل الرفع: أسرع، ومابتعديش حد الرفع في السيرفر
    function shrink(f) { if (!/^image\/(jpeg|png|webp)$/.test(f.type) || f.size < 1500000) return Promise.resolve(f);
      return new Promise(function (ok) { var u = URL.createObjectURL(f), im = new Image(); im.onload = function () { var k = Math.min(1, 1600 / Math.max(im.width, im.height)), c = document.createElement('canvas'); c.width = Math.round(im.width * k); c.height = Math.round(im.height * k); var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(im, 0, 0, c.width, c.height); URL.revokeObjectURL(u);
        c.toBlob(function (bl) { ok(bl ? new File([bl], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : f); }, 'image/jpeg', 0.88); }; im.onerror = function () { URL.revokeObjectURL(u); ok(f); }; im.src = u; }); }
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
      + (P.status === 'paused' || (P.status === 'generating' && P.error) ? '<button type="button" id="cp-resume" class="btn-primary !py-1.5">' + esc(T.resume) + '</button>' : '') + '</div>'
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

  /* ---------- المراجعة ---------- */
  var list = $('#cp-list'), bar = $('#cp-bar');
  function words(s) { return (String(s || '').trim().match(/\S+/g) || []).length; }
  function block(label, text, field, it, en) {
    if (!text && field !== 'linkedin') return '';
    var meta = field === 'linkedin' ? fmt(T.words, words(text)) : field === 'x' ? fmt(T.chars, [].slice.call(text || '').length) : '';
    return '<section class="rounded-xl border border-ink-200 p-3 min-w-0"><div class="flex items-center gap-2 mb-1"><b class="text-xs text-ink-500">' + esc(label) + '</b><span class="text-[11px] ' + (field === 'x' && [].slice.call(text || '').length > 280 ? 'text-red-600' : 'text-ink-400') + '">' + esc(meta) + '</span><span class="flex-1"></span>'
      + '<button type="button" data-copy="' + field + '" class="text-xs link">' + esc(T.copy) + '</button>' + (it.status === 'draft' || it.status === 'approved' || it.status === 'rejected' ? ' <button type="button" data-edit="' + field + '" class="text-xs link">' + esc(T.edit) + '</button>' : '') + '</div>'
      + '<div class="cp-txt text-sm whitespace-pre-wrap break-words" ' + (en ? 'dir="ltr"' : 'dir="auto"') + '>' + esc(text) + '</div></section>';
  }
  function card(it) {
    var t = it.topic || {}, st = it.status, ready = it.linkedin != null;
    var badge = st === 'rejected' ? 'badge-red' : st === 'pending' ? 'badge-amber' : st === 'draft' ? '' : 'badge-green';
    var h = '<article class="card p-4" data-id="' + it.id + '"><header class="flex flex-wrap items-start gap-2 mb-3">'
      + (ready && ['draft', 'approved', 'rejected'].indexOf(st) >= 0 ? '<input type="checkbox" class="cp-sel mt-1.5"' + (sel[it.id] ? ' checked' : '') + '>' : '')
      + '<div class="min-w-0 flex-1"><b class="text-ink-900">' + it.seq + '. ' + esc(t.title || '') + '</b><div class="text-xs text-ink-500">' + esc([t.angle, t.service, t.audience, (T.cats || {})[t.category] || t.category, t.objective].filter(Boolean).join(' · ')) + '</div></div>'
      + (+it.humanized ? '<span class="text-[11px] text-ink-400" title="Humanizer">✓ ' + esc(T.human) + '</span>' : '') + '<span class="badge ' + badge + '">' + esc(T['s_' + st] || st) + '</span></header>';
    if (!ready) return h + '<div class="h-16 rounded-xl bg-ink-50 animate-pulse"></div></article>';
    if ((it.qc || []).length) h += '<div class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"><b>' + esc(T.qc) + '</b> ' + it.qc.map(esc).join(' · ') + '</div>';
    h += '<div class="grid lg:grid-cols-2 gap-3">' + block(T.li, it.linkedin, 'linkedin', it) + block(T.x, it.x, 'x', it)
      + block(T.img + (it.headline ? ' — «' + it.headline + '»' : ''), it.image_prompt, 'image_prompt', it, true) + block(T.vid, it.video_prompt, 'video_prompt', it, true) + '</div>';
    if ((it.carousel || []).length) h += '<details class="mt-3 rounded-xl border border-ink-200 p-3"><summary class="cursor-pointer text-xs font-bold text-ink-500">' + esc(T.car) + '</summary><ol class="mt-2 grid sm:grid-cols-5 gap-2">'
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
  function mediaHtml(it) {
    var js = jobs[it.id] || {}, chips = '';
    ['image', 'video', 'carousel'].forEach(function (k) { var j = js[k]; if (!j) return; var lab = T['k' + k[0].toUpperCase() + k.slice(1)], bad = j.failed && !j.done && !j.busy;
      var can = j.done && !j.busy && it.fmt !== k, on = it.fmt === k;
      chips += '<' + (can ? 'button type="button" data-fmt="' + k + '"' : 'span') + ' class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs ' + (on ? 'ring-2 ring-lime-500 ' : '') + (j.busy ? 'bg-brand-50 text-brand-700' : bad ? 'bg-red-50 text-red-700' : 'bg-lime-50 text-lime-700') + '"' + (j.err ? ' title="' + esc(j.err) + '"' : can ? ' title="' + esc(T.useFmt) + '"' : on ? ' title="' + esc(T.inUse) + '"' : '') + '>'
        + (j.busy ? '<span class="w-3 h-3 rounded-full border-2 border-current border-t-transparent animate-spin"></span>' : bad ? '✕' : '✓') + ' ' + esc(lab) + (j.total > 1 ? ' ' + j.done + '/' + j.total : '') + ' · ' + esc(j.busy ? T.jBusy : bad ? T.jFail : T.jDone) + '</' + (can ? 'button' : 'span') + '>'; });
    var ms = it.media_ids || [], fm = it.fmt ? T['k' + it.fmt[0].toUpperCase() + it.fmt.slice(1)] : T.fmtText;
    if (!chips && !ms.length) return '';
    return '<div class="mt-3 rounded-xl border border-ink-200 p-3"><div class="flex flex-wrap items-center gap-2 mb-2"><b class="text-xs text-ink-500">' + esc(fm) + '</b>' + chips + '</div>'
      + (ms.length ? '<div class="flex flex-wrap gap-2">' + ms.map(function (m) { return it.fmt === 'video' ? '<a href="' + esc(murl(m)) + '" target="_blank" class="w-24 h-32 rounded-lg bg-ink-900 text-[#fff] grid place-items-center text-xs">▶ ' + esc(T.kVideo) + '</a>' : '<a href="' + esc(murl(m)) + '" target="_blank"><img src="' + esc(murl(m, 1)) + '" class="w-24 h-32 object-cover rounded-lg border border-ink-200" alt=""></a>'; }).join('') + '</div>' : '') + '</div>';
  }
  var fl = $('#cp-float');
  function genLoop() { if (genLoop.on) return; genLoop.on = true;
    (function next() { post({ action: 'jobs' }).then(function (j) { if (!j.ok) { genLoop.on = false; return; } jobs = j.jobs.items || {}; if (j.log) { P.log = j.log; paintProg({}); }
      load(); if (j.jobs.pending) setTimeout(next, j.did ? 300 : 5000); else genLoop.on = false; })
      .catch(function () { setTimeout(function () { genLoop.on = false; genLoop(); }, 8000); }); })(); }
  function paint() {
    var vis = items.filter(function (i) { return !filter || (filter === 'qc' ? (i.qc || []).length : i.status === filter); });
    list.innerHTML = vis.map(card).join('');
    var ready = items.filter(function (i) { return i.linkedin != null; }).length; bar.classList.toggle('hidden', !ready);
    var n = Object.keys(sel).length, ap = items.filter(function (i) { return ['approved', 'scheduled', 'queued', 'published'].indexOf(i.status) >= 0; }).length;
    fl.classList.toggle('hidden', !n); $('#cp-fn').textContent = fmt(T.fSel, n);
    var tb = $('#cp-tobank'); tb.classList.toggle('hidden', !ap); tb.textContent = fmt(T.nApproved, ap) + ' ←';
  }
  function load() { return post({ action: 'items' }).then(function (j) { if (!j.ok) return; items = j.items; jobs = (j.jobs || {}).items || {}; if ((j.jobs || {}).pending) genLoop(); Object.keys(sel).forEach(function (k) { if (!items.some(function (i) { return i.id == k; })) delete sel[k]; }); paint(); }); }
  function setSt(ids, st) { return post({ action: 'set', ids: JSON.stringify(ids), status: st }).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); ids.forEach(function (id) { delete sel[id]; }); load(); }).catch(function () { toast(T.offline, 'error'); }); }
  $('#cp-f').addEventListener('change', function () { filter = this.value; paint(); });
  $('#cp-all').addEventListener('change', function () { var on = this.checked; sel = {}; if (on) items.forEach(function (i) { if (i.linkedin != null && ['draft', 'approved', 'rejected'].indexOf(i.status) >= 0 && (!filter || i.status === filter)) sel[i.id] = 1; }); paint(); });
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
    if ((b = e.target.closest('[data-fmt]'))) { post({ action: 'usefmt', id: id, kind: b.dataset.fmt }).then(function () { load(); }); return; }
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
  paintProg({}); load(); loop();
})();
