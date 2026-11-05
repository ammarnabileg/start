/* صور فرع جوجل بيزنس: تغطية الأقسام، رفع جماعي بالسحب، معرض وعارض كبير.
   الرفع والحذف بيحصلوا بس لما المستخدم يدوس بنفسه. */
(function () {
  var X = window.GMX; if (!X) return;
  var S = X.s, CATS = X.cats;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function toast(m, k) { if (window.aiToast) window.aiToast(m, k); }
  var items = X.items.slice(), filter = '', sort = 'new', view = [];

  /* ================= التغطية ================= */
  var food = items.some(function (i) { return i.cat === 'FOOD_AND_DRINK' || i.cat === 'MENU'; });
  var targets = {}; Object.keys(X.targets).forEach(function (k) { if ((food && k === 'PRODUCT') || (!food && k === 'FOOD_AND_DRINK')) return; targets[k] = X.targets[k]; });
  function counts() { var c = {}; items.forEach(function (i) { c[i.cat] = (c[i.cat] || 0) + 1; }); return c; }
  function paintCov() {
    var c = counts(), keys = Object.keys(targets), done = keys.filter(function (k) { return (c[k] || 0) >= targets[k]; }).length;
    var pct = Math.round(done / keys.length * 100), col = pct >= 85 ? '#16a34a' : pct >= 50 ? '#d97706' : '#dc2626';
    $('#gm-cov').innerHTML = '<div class="flex items-center justify-between mb-1"><span class="font-bold text-ink-900">' + esc(S.cov) + '</span><span class="text-sm font-bold" style="color:' + col + '">' + esc(f(S.covDone, done, keys.length)) + '</span></div>'
      + '<p class="text-[11px] text-ink-500 mb-4">' + esc(S.covHint) + '</p><div class="space-y-3">'
      + keys.map(function (k) {
        var n = c[k] || 0, t = targets[k], ok = n >= t;
        return '<div class="group"><div class="flex items-center gap-2 text-sm mb-1">'
          + '<span class="w-4 h-4 shrink-0 rounded-full flex items-center justify-center text-[10px] ' + (ok ? 'bg-lime-500 text-white' : 'border-2 border-ink-200') + '">' + (ok ? '✓' : '') + '</span>'
          + '<button type="button" data-f="' + k + '" class="flex-1 text-start hover:text-brand-700 ' + (ok ? 'text-ink-500' : 'text-ink-900 font-medium') + '">' + esc(CATS[k] || k) + '</button>'
          + '<span class="text-xs text-ink-500">' + Math.min(n, 99) + '/' + t + '</span>'
          + (ok ? '' : '<button type="button" data-up="' + k + '" class="text-xs text-brand-700 hover:underline">' + esc(S.uploadHere) + '</button>') + '</div>'
          + '<div class="h-1.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full ' + (ok ? 'bg-lime-500' : 'bg-amber-500') + '" style="width:' + Math.min(100, n / t * 100) + '%"></div></div></div>';
      }).join('') + '</div>';
  }
  $('#gm-cov').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    if (b.dataset.f) { filter = b.dataset.f; paintGrid(); $('#gm-grid').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    if (b.dataset.up) { defCat = b.dataset.up; fileIn.click(); }
  });

  /* ================= المعرض ================= */
  function paintChips() {
    var c = counts(), keys = Object.keys(c).sort(function (a, b) { return c[b] - c[a]; });
    $('#gm-chips').innerHTML = [['', S.all, items.length]].concat(keys.map(function (k) { return [k, CATS[k] || k, c[k]]; })).map(function (x) {
      var on = filter === x[0];
      return '<button type="button" data-c="' + x[0] + '" class="rounded-full px-3 py-1 text-sm transition ' + (on ? 'bg-brand-600 text-white' : 'bg-ink-50 text-ink-700 hover:bg-ink-100') + '">' + esc(x[1]) + ' <span class="opacity-70">' + x[2] + '</span></button>';
    }).join('');
  }
  $('#gm-chips').addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { filter = b.dataset.c; paintGrid(); } });
  $('#gm-sort').addEventListener('change', function () { sort = this.value; paintGrid(); });
  function paintGrid() {
    paintChips(); paintCov();
    view = items.filter(function (i) { return !filter || i.cat === filter; });
    view.sort(function (a, b) { return sort === 'views' ? (b.views || 0) - (a.views || 0) : (sort === 'old' ? (a.at > b.at ? 1 : -1) : (a.at < b.at ? 1 : -1)); });
    var g = $('#gm-grid'), em = $('#gm-empty');
    em.style.display = view.length ? 'none' : ''; em.textContent = items.length ? S.empty : S.emptyAll;
    g.innerHTML = view.map(function (i, n) {
      return '<button type="button" data-n="' + n + '" class="gm-it group relative aspect-square rounded-xl overflow-hidden bg-ink-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">'
        + (i.src ? '<img src="' + esc(i.src) + '" loading="lazy" referrerpolicy="no-referrer" alt="" class="w-full h-full object-cover transition duration-300 group-hover:scale-105">' : '')
        + (i.video ? '<span class="absolute top-2 end-2 rounded-full bg-black/60 text-white text-xs px-2 py-0.5">▶</span>' : '')
        + '<span class="absolute inset-x-0 bottom-0 p-2 pt-8 bg-gradient-to-t from-black/70 to-transparent text-white text-start opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100 transition">'
        + '<span class="block text-xs font-medium truncate">' + esc(CATS[i.cat] || i.cat || '') + '</span>'
        + '<span class="block text-[11px] opacity-80">' + esc(i.date) + (i.views != null ? ' · ' + esc(f(S.views, i.views.toLocaleString())) : '') + '</span></span></button>';
    }).join('');
  }
  $('#gm-grid').addEventListener('click', function (e) { var b = e.target.closest('.gm-it'); if (b) openLb(+b.dataset.n); });

  /* ================= العارض الكبير ================= */
  var lb = $('#gm-lb'), cur = 0;
  document.body.appendChild(lb); // بره أي stacking context عشان يغطي الهيدر
  if (document.documentElement.dir === 'rtl') { $('#gm-lb-prev').textContent = '›'; $('#gm-lb-next').textContent = '‹'; }
  function showLb() {
    var i = view[cur]; if (!i) return closeLb();
    $('#gm-lb-img').src = i.big || i.src;
    $('#gm-lb-i').textContent = (cur + 1) + ' / ' + view.length;
    $('#gm-lb-cat').textContent = CATS[i.cat] || i.cat || '';
    $('#gm-lb-open').href = i.big || i.src;
    $('#gm-lb-meta').innerHTML = [i.date, i.views != null ? f(S.views, i.views.toLocaleString()) : '', i.w ? i.w + '×' + i.h : '', i.desc].filter(Boolean).map(esc).join(' · ') + '<div class="text-[11px] opacity-50 mt-1">' + esc(S.k) + '</div>';
  }
  function openLb(n) { cur = n; lb.style.display = ''; document.body.style.overflow = 'hidden'; showLb(); $('#gm-lb-x').focus(); }
  function closeLb() { lb.style.display = 'none'; document.body.style.overflow = ''; }
  function step(d) { cur = (cur + d + view.length) % view.length; showLb(); }
  var rtl = document.documentElement.dir === 'rtl';
  $('#gm-lb-x').onclick = closeLb;
  $('#gm-lb-prev').onclick = function () { step(-1); };
  $('#gm-lb-next').onclick = function () { step(1); };
  $('#gm-lb-img').parentNode.addEventListener('click', function (e) { if (e.target === this) closeLb(); });
  document.addEventListener('keydown', function (e) {
    if (lb.style.display === 'none') return;
    if (e.key === 'Escape') closeLb();
    else if (e.key === 'ArrowLeft') step(rtl ? 1 : -1);
    else if (e.key === 'ArrowRight') step(rtl ? -1 : 1);
    else if ((e.key === 'Delete' || e.key === 'Backspace') && X.canDel) { e.preventDefault(); del(); }
  });
  var tx = null;
  lb.addEventListener('touchstart', function (e) { tx = e.touches[0].clientX; }, { passive: true });
  lb.addEventListener('touchend', function (e) { if (tx == null) return; var dx = e.changedTouches[0].clientX - tx; if (Math.abs(dx) > 50) step((dx < 0) !== rtl ? 1 : -1); tx = null; });
  function post(fd) {
    fd.append('_csrf', X.csrf); fd.append('loc', X.loc); fd.append('ajax', '1');
    return fetch(X.post, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function del() {
    var i = view[cur]; if (!i || !confirm(S.confirmDel)) return;
    var fd = new FormData(); fd.append('action', 'delete'); fd.append('media_id', i.id);
    post(fd).then(function (r) {
      if (!r.ok) return toast(r.error || S.failed);
      items = items.filter(function (x) { return x !== i; });
      toast(S.deleted, 'ok'); paintGrid();
      if (!view.length) closeLb(); else { cur = Math.min(cur, view.length - 1); showLb(); }
    }, function () { toast(S.failed); });
  }
  var delB = $('#gm-lb-del'); if (delB) delB.onclick = del;

  /* ================= طابور الرفع ================= */
  var drop = $('#gm-drop'), fileIn = $('#gm-file'), qBox = $('#gm-q'), qFoot = $('#gm-qfoot'), goB = $('#gm-go');
  var Q = [], busy = false, defCat = 'ADDITIONAL';
  function catSelect(v) { return '<select class="gm-cat input !mt-0 !py-1.5 !w-auto text-sm">' + Object.keys(CATS).map(function (k) { return '<option value="' + k + '"' + (k === v ? ' selected' : '') + '>' + esc(CATS[k]) + '</option>'; }).join('') + '</select>'; }
  function hints(q) {
    var h = [];
    if (q.w && q.cat === 'COVER' && q.w / q.h < 1.5) h.push(S.cover16);
    if (q.w && q.cat === 'LOGO' && Math.abs(q.w / q.h - 1) > 0.15) h.push(S.logoSq);
    if (q.w && !q.err && Math.min(q.w, q.h) < 720) h.push(S.lowRes);
    return h;
  }
  function paintRow(q) {
    var r = q.row, st = $('.gm-st', r), h = hints(q);
    st.className = 'gm-st text-xs ' + (q.err || q.state === 'fail' ? 'text-red-600' : q.state === 'done' ? 'text-lime-700' : 'text-ink-500');
    st.textContent = q.err || (q.state === 'sending' ? S.sending : q.state === 'done' ? (q.branches > 1 ? f(S.doneN, q.branches) : S.done) : q.state === 'fail' ? (S.failed + (q.msg ? ': ' + q.msg : '')) : (q.w ? q.w + '×' + q.h + ' · ' + Math.round(q.size / 1024) + ' KB' : ''));
    $('.gm-h', r).textContent = q.partial ? f(S.partial, q.partial) : h.join(' · ');
    $('.gm-h', r).className = 'gm-h text-[11px] ' + (q.partial ? 'text-red-600' : 'text-amber-700');
    $('.gm-bar', r).style.width = q.state === 'done' ? '100%' : (q.pct || 0) + '%';
    $('.gm-bar', r).parentNode.style.display = q.state === 'sending' || q.state === 'done' ? '' : 'none';
    r.style.opacity = q.err ? '.6' : '';
    $$('select,input,button.gm-rm', r).forEach(function (x) { x.disabled = busy || q.state === 'done'; });
  }
  function paintQ() {
    qBox.style.display = qFoot.style.display = Q.length ? '' : 'none';
    var ready = Q.filter(function (q) { return !q.err && q.state === 'wait'; }).length, bad = Q.filter(function (q) { return q.err; }).length;
    var br = $$('#gm-also input:checked').length;
    $('#gm-qinfo').textContent = f(S.qinfo, ready, bad);
    var n = $('#gm-also-n'); if (n) { n.style.display = br ? '' : 'none'; n.textContent = br; }
    goB.textContent = br ? f(S.goN, ready, br + 1) : f(S.go, ready);
    goB.disabled = busy || !ready;
    $('#gm-clear').disabled = busy;
    Q.forEach(paintRow);
  }
  function addRow(q) {
    q.row = el('div', 'flex items-center gap-3 rounded-xl border border-ink-200 p-2.5');
    q.row.innerHTML = '<div class="w-16 h-16 shrink-0 rounded-lg bg-ink-100 overflow-hidden"><img class="w-full h-full object-cover" alt=""></div>'
      + '<div class="min-w-0 flex-1 space-y-1.5"><div class="flex flex-wrap items-center gap-2"><span class="text-sm font-medium text-ink-900 truncate max-w-[14rem]"></span><span class="gm-st"></span></div>'
      + '<div class="flex flex-wrap items-center gap-2">' + catSelect(q.cat) + '<input class="gm-desc input !mt-0 !py-1.5 flex-1 min-w-[8rem] text-sm" maxlength="300" placeholder="' + esc(S.desc) + '"></div>'
      + '<div class="gm-h"></div><div class="h-1 rounded-full bg-ink-100 overflow-hidden" style="display:none"><div class="gm-bar h-full bg-brand-500 transition-all" style="width:0"></div></div></div>'
      + '<button type="button" class="gm-rm w-8 h-8 shrink-0 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" title="' + esc(S.remove) + '">✕</button>';
    $('img', q.row).src = q.thumb; $('.text-sm.font-medium', q.row).textContent = q.name;
    $('.gm-cat', q.row).onchange = function () { q.cat = this.value; paintRow(q); };
    $('.gm-desc', q.row).oninput = function () { q.desc = this.value; };
    $('.gm-rm', q.row).onclick = function () { Q.splice(Q.indexOf(q), 1); q.row.remove(); if (q.url) URL.revokeObjectURL(q.url); paintQ(); };
    qBox.appendChild(q.row);
  }
  function addFiles(list) {
    Array.prototype.forEach.call(list, function (file) {
      var q = { file: file, name: file.name, size: file.size, cat: defCat, desc: '', state: 'wait', w: 0, h: 0 };
      if (!/^image\/(jpeg|png)$/.test(file.type)) q.err = S.badType;
      else if (file.size < 10240) q.err = S.tooSmall;
      else if (file.size > 5 * 1024 * 1024) q.err = S.tooBig;
      q.url = URL.createObjectURL(file); q.thumb = q.url;
      Q.push(q); addRow(q);
      var img = new Image();
      img.onload = function () { q.w = img.naturalWidth; q.h = img.naturalHeight; if (!q.err && (q.w < 250 || q.h < 250)) q.err = S.tooFew; paintQ(); };
      img.onerror = function () { if (!q.err) q.err = S.badType; paintQ(); };
      img.src = q.url;
    });
    defCat = 'ADDITIONAL'; paintQ();
  }
  $('#gm-pick').onclick = function (e) { e.stopPropagation(); fileIn.click(); };
  drop.addEventListener('click', function (e) { if (!e.target.closest('button')) fileIn.click(); });
  fileIn.onchange = function () { addFiles(fileIn.files); fileIn.value = ''; };
  ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('border-brand-500', 'bg-brand-50'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('border-brand-500', 'bg-brand-50'); }); });
  drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) addFiles(e.dataTransfer.files); });
  document.addEventListener('paste', function (e) { var fs = e.clipboardData && e.clipboardData.files; if (fs && fs.length && !/INPUT|TEXTAREA/.test(document.activeElement.tagName)) addFiles(fs); });
  $('#gm-lib').onclick = function (e) {
    e.stopPropagation();
    if (!window.mediaPick) return;
    window.mediaPick({ accept: 'image', multiple: true, public: false, ctx: 'gmb' }).then(function (files) {
      (files || []).forEach(function (m) {
        var q = { lib: m.id, name: m.name, size: 0, cat: defCat, desc: '', state: 'wait', w: m.w || 0, h: m.h || 0, thumb: m.thumb || m.url };
        if (m.mime && !/^image\/(jpeg|png)$/.test(m.mime)) q.err = S.badType;
        else if (q.w && (q.w < 250 || q.h < 250)) q.err = S.tooFew;
        Q.push(q); addRow(q);
      });
      defCat = 'ADDITIONAL'; paintQ();
    });
  };
  var also = $('#gm-also'); if (also) also.addEventListener('change', paintQ);
  $('#gm-clear').onclick = function () { Q.forEach(function (q) { q.row.remove(); if (q.url) URL.revokeObjectURL(q.url); }); Q = []; paintQ(); };
  function send(q) {
    return new Promise(function (res) {
      var fd = new FormData();
      fd.append('_csrf', X.csrf); fd.append('loc', X.loc); fd.append('ajax', '1'); fd.append('action', 'upload');
      fd.append('category', q.cat); fd.append('description', q.desc || '');
      $$('#gm-also input:checked').forEach(function (i) { fd.append('also[]', i.value); });
      if (q.lib) fd.append('lib_photo', q.lib); else fd.append('photo', q.file, q.name);
      var x = new XMLHttpRequest(); x.open('POST', X.post);
      x.upload.onprogress = function (e) { if (e.lengthComputable) { q.pct = Math.round(e.loaded / e.total * 90); paintRow(q); } };
      x.onload = function () {
        var r = null; try { r = JSON.parse(x.responseText); } catch (e) {}
        if (r && r.ok) { q.state = 'done'; q.branches = r.branches; if (r.fail && r.fail.length) q.partial = r.fail.join(' · '); }
        else { q.state = 'fail'; q.msg = r && r.error || ''; }
        res();
      };
      x.onerror = function () { q.state = 'fail'; res(); };
      q.state = 'sending'; q.pct = 0; paintRow(q); x.send(fd);
    });
  }
  goB.onclick = function () {
    var todo = Q.filter(function (q) { return !q.err && q.state === 'wait'; }); if (!todo.length) return;
    busy = true; paintQ();
    todo.reduce(function (p, q) { return p.then(function () { return send(q); }); }, Promise.resolve()).then(function () {
      busy = false; paintQ();
      var ok = todo.filter(function (q) { return q.state === 'done'; }).length;
      toast(f(S.allDone, ok, todo.length - ok), ok === todo.length ? 'ok' : undefined);
    });
  };
  window.addEventListener('beforeunload', function (e) { if (busy) { e.preventDefault(); e.returnValue = S.leave; return S.leave; } });

  paintGrid();
})();
