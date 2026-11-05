/* مكتبة الوسائط: متصفح واحد بيشتغل كصفحة كاملة (Media.page) أو كنافذة اختيار من أي صفحة (Media.pick)
 *   mediaPick({accept: 'image' | 'video' | 'image,video' | 'any', multiple: false, public: true}).then(function (files) { ... })
 *   files = [{id, name, kind, mime, url (للعرض جوه المنصة), public (رابط عام للمنصات لو public:true), w, h}] أو null لو اتقفلت */
(function () {
  var M = window.MEDIA; if (!M || window.Media) return; var S = M.s;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var fmt = function (s) { var a = [].slice.call(arguments, 1); return String(s || '').replace(/%s/g, function () { return a.length ? a.shift() : ''; }); };
  var size = function (b) { return b >= 1073741824 ? (b / 1073741824).toFixed(1) + ' GB' : b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; };
  var toast = function (m, k) { (window.aiToast || function () {})(m, k); };
  var ICON = { doc: '<svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 3H6a1 1 0 00-1 1v16a1 1 0 001 1h12a1 1 0 001-1V8zM14 3v5h5M8 13h8M8 17h5"/></svg>', audio: '<svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 18V5l12-2v13M9 18a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"/></svg>' };
  var RATIOS = [['1:1', 1], ['4:5', 0.8], ['9:16', 0.5625], ['16:9', 1.7778], ['1.91:1', 1.91], ['2:3', 0.6667], ['3:2', 1.5], ['4:3', 1.3333], ['3:4', 0.75]];
  function ratioOf(f) { if (!f.w || !f.h) return null; var r = f.w / f.h, best = null; RATIOS.forEach(function (x) { var d = Math.abs(Math.log(r / x[1])); if (d < 0.03 && (!best || d < best[1])) best = [x[0], d]; }); return best ? best[0] : null; }
  function small(f) { return f.kind === 'image' && f.w && Math.min(f.w, f.h) < 600; }
  function post(f, files) { var fd = new FormData(); fd.append('_csrf', M.csrf); Object.keys(f).forEach(function (k) { [].concat(f[k]).forEach(function (v) { fd.append(k, v); }); }); return fetch(M.post, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

  function Browser(host, opt) {
    opt = opt || {}; var st = { folder: '*', kind: '', q: '', sort: 'new', items: [], folders: {}, sel: [], cur: null, usage: 0 };
    var accept = (opt.accept || 'any').split(',');
    var okKind = function (k) { return accept.indexOf('any') >= 0 || accept.indexOf(k) >= 0; };
    if (accept.indexOf('any') < 0 && accept.length === 1) st.kind = accept[0];
    host.innerHTML = '<div class="md grid gap-4 ' + (opt.picker ? 'lg:grid-cols-[200px_minmax(0,1fr)]' : 'lg:grid-cols-[220px_minmax(0,1fr)_300px]') + ' items-start">'
      + '<aside class="md-side card p-3 text-sm space-y-1"></aside>'
      + '<section class="min-w-0 space-y-3"><div class="md-bar flex flex-wrap items-center gap-2"></div>'
      + '<label class="md-drop block rounded-2xl border-2 border-dashed border-ink-300 bg-ink-50/60 p-4 text-center text-sm text-ink-600 cursor-pointer hover:border-brand-400 transition' + (M.canEdit ? '' : ' hidden') + '"><input type="file" multiple class="hidden md-file"><span class="md-drop-t">' + esc(S.drop) + '</span><span class="block text-[11px] text-ink-400 mt-1">' + esc(fmt(S.maxSize, size(M.max))) + '</span></label>'
      + '<div class="md-prog space-y-1"></div><div class="md-grid grid gap-3 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 ' + (opt.picker ? 'xl:grid-cols-5' : 'xl:grid-cols-5 2xl:grid-cols-6') + '"></div></section>'
      + (opt.picker ? '' : '<aside class="md-info card p-4 text-sm lg:sticky lg:top-4"></aside>') + '</div>';
    var $ = function (s) { return host.querySelector(s); };
    function load() {
      return fetch(M.list + '&folder=' + encodeURIComponent(st.folder) + '&kind=' + encodeURIComponent(st.kind) + '&q=' + encodeURIComponent(st.q), { credentials: 'same-origin' }).then(function (r) { return r.json(); })
        .then(function (j) { st.items = j.items || []; st.folders = j.folders || {}; st.usage = j.usage || 0; st.sel = st.sel.filter(function (id) { return st.items.some(function (x) { return x.id === id; }); }); draw(); });
    }
    function side() {
      var total = Object.keys(st.folders).reduce(function (n, k) { return n + st.folders[k]; }, 0);
      var row = function (key, label, n) { var on = st.folder === key; return '<button type="button" data-fd="' + esc(key) + '" class="w-full flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-start ' + (on ? 'bg-brand-50 text-brand-800 font-medium' : 'hover:bg-ink-50 text-ink-700') + '"><span class="flex-1 truncate">' + esc(label) + '</span><span class="text-[11px] text-ink-400">' + n + '</span></button>'; };
      $('.md-side').innerHTML = row('*', S.allFolders, total) + (st.folders[''] != null ? row('', S.noFolder, st.folders['']) : '')
        + Object.keys(st.folders).filter(Boolean).map(function (k) { return row(k, '📁 ' + k, st.folders[k]); }).join('')
        + (M.canEdit ? '<button type="button" class="md-nf w-full rounded-lg border border-dashed border-ink-300 px-2.5 py-1.5 text-ink-600 hover:bg-ink-50 mt-2">+ ' + esc(S.newFolder) + '</button>' : '')
        + '<p class="text-[11px] text-ink-400 pt-2">' + esc(fmt(S.usage, size(st.usage))) + '</p>';
    }
    function bar() {
      var kinds = [['', S.all], ['image', S.images], ['video', S.videos], ['doc', S.docs]].filter(function (k) { return !k[0] || okKind(k[0]) || accept.indexOf('any') >= 0; });
      $('.md-bar').innerHTML = '<input type="search" class="md-q input !mt-0 !h-9 !py-0 w-56 text-sm" placeholder="' + esc(S.search) + '" value="' + esc(st.q) + '">'
        + '<div class="seg">' + kinds.map(function (k) { return '<a href="#" data-kd="' + k[0] + '" class="' + (st.kind === k[0] ? 'on' : '') + '">' + esc(k[1]) + '</a>'; }).join('') + '</div>'
        + '<select class="md-sort input !mt-0 !h-9 !py-0 !w-auto text-sm"><option value="new">' + esc(S.sortNew) + '</option><option value="name"' + (st.sort === 'name' ? ' selected' : '') + '>' + esc(S.sortName) + '</option><option value="size"' + (st.sort === 'size' ? ' selected' : '') + '>' + esc(S.sortSize) + '</option></select>'
        + '<span class="ms-auto flex items-center gap-2 md-selbar">' + (st.sel.length ? '<span class="text-xs text-ink-600">' + esc(fmt(S.selected, st.sel.length)) + '</span>'
          + (M.canEdit && !opt.picker ? '<button type="button" data-bk="move" class="btn-ghost !h-8 !py-0 text-xs">' + esc(S.move) + '</button><button type="button" data-bk="del" class="btn-ghost !h-8 !py-0 text-xs !text-red-600">' + esc(S.del) + '</button>' : '')
          + (opt.picker ? '<button type="button" data-bk="use" class="btn-primary !h-8 !py-0 text-xs">' + esc(S.useSel) + '</button>' : '') : '') + '</span>';
    }
    function grid() {
      var list = st.items.slice().sort(function (a, b) { return st.sort === 'name' ? a.name.localeCompare(b.name) : st.sort === 'size' ? b.size - a.size : 0; });
      $('.md-grid').innerHTML = list.length ? list.map(function (f) {
        var on = st.sel.indexOf(f.id) >= 0, dim = !okKind(f.kind);
        var rb = ratioOf(f), badge = (rb ? '<span class="absolute bottom-1.5 end-1.5 rounded bg-black/60 text-white text-[10px] px-1.5 py-0.5" dir="ltr">' + rb + '</span>' : '') + (small(f) ? '<span class="absolute top-1.5 end-1.5 rounded bg-amber-500 text-white text-[10px] px-1.5 py-0.5" title="' + esc(S.smallHint) + '">' + esc(S.small) + '</span>' : '');
        var th = f.kind === 'image' ? '<img src="' + esc(f.thumb || f.url) + '" alt="" loading="lazy" class="w-full h-full object-cover">' + badge : f.kind === 'video' ? '<video src="' + esc(f.url) + '#t=0.5" preload="metadata" muted class="w-full h-full object-cover"></video><span class="absolute inset-0 grid place-items-center text-white text-3xl drop-shadow">▶</span>' : '<span class="text-ink-400">' + (ICON[f.kind] || ICON.doc) + '</span><span class="absolute bottom-1.5 inset-x-0 text-center text-[10px] font-bold uppercase text-ink-500">' + esc(f.name.split('.').pop()) + '</span>';
        return '<button type="button" draggable="' + (M.canEdit && !opt.picker ? 'true' : 'false') + '" data-it="' + f.id + '" class="md-it group text-start rounded-xl border overflow-hidden transition ' + (on ? 'border-brand-500 ring-2 ring-brand-500/30' : 'border-ink-200 hover:border-ink-300') + (dim ? ' opacity-40' : '') + '" title="' + esc(f.name) + '">'
          + '<span class="relative block aspect-square bg-ink-100 grid place-items-center overflow-hidden">' + th + '<span class="md-ck absolute top-1.5 start-1.5 w-5 h-5 rounded-md border-2 ' + (on ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white/80 border-ink-300 opacity-0 group-hover:opacity-100') + ' grid place-items-center text-[11px]">' + (on ? '✓' : '') + '</span></span>'
          + '<span class="block px-2 py-1.5"><span class="block text-xs text-ink-800 truncate">' + esc(f.name) + '</span><span class="block text-[10px] text-ink-400">' + esc(size(f.size) + (f.w ? ' · ' + f.w + '×' + f.h : '')) + '</span></span></button>';
      }).join('') : '<p class="col-span-full text-center text-sm text-ink-500 py-10">' + esc(S.empty) + '</p>';
    }
    function info() {
      var box = $('.md-info'); if (!box) return; var f = st.items.filter(function (x) { return x.id === st.cur; })[0];
      if (!f) { box.innerHTML = '<p class="text-xs text-ink-500">' + esc(S.details) + '</p>'; return; }
      var pv = f.kind === 'image' ? '<img src="' + esc(f.url) + '" alt="" class="w-full rounded-lg bg-ink-100">' : f.kind === 'video' ? '<video src="' + esc(f.url) + '" controls class="w-full rounded-lg bg-black"></video>' : f.kind === 'audio' ? '<audio src="' + esc(f.url) + '" controls class="w-full"></audio>' : '<div class="grid place-items-center h-32 rounded-lg bg-ink-100 text-ink-400">' + ICON.doc + '</div>';
      box.innerHTML = pv + '<div class="mt-3 space-y-2"><b class="block text-ink-900 break-all">' + esc(f.name) + '</b>'
        + '<dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs"><dt class="text-ink-500">' + esc(S.size) + '</dt><dd dir="ltr" class="text-start">' + esc(size(f.size)) + '</dd>' + (f.w ? '<dt class="text-ink-500">' + esc(S.dims) + '</dt><dd dir="ltr" class="text-start">' + f.w + '×' + f.h + '</dd>' : '') + '<dt class="text-ink-500">' + esc(S.type) + '</dt><dd dir="ltr" class="text-start">' + esc(f.mime) + '</dd><dt class="text-ink-500">' + esc(S.date) + '</dt><dd>' + esc(f.at) + '</dd></dl>'
        + (M.canEdit ? '<label class="block"><span class="text-xs text-ink-500">' + esc(S.tags) + '</span><input class="md-tags input !mt-1 !py-1.5 text-sm" value="' + esc(f.tags) + '"></label>' : '')
        + (f.kind === 'image' ? '<p class="text-[11px] ' + (small(f) ? 'text-amber-700' : 'text-lime-700') + '">' + esc(small(f) ? S.smallHint : fmt(S.goodFor, ratioOf(f) || (f.w + '×' + f.h))) + '</p>' : '')
        + '<div class="flex flex-wrap gap-2 pt-1">' + (f.kind === 'image' || f.kind === 'video' ? '<button type="button" data-ac="view" class="btn-ghost text-xs">' + esc(S.view) + '</button>' : '') + (f.kind === 'image' && M.canEdit ? '<button type="button" data-ac="crop" class="btn-ghost text-xs">✂ ' + esc(S.crop) + '</button>' : '') + (M.canEdit ? '<button type="button" data-ac="rename" class="btn-ghost text-xs">' + esc(S.rename) + '</button>' : '') + '<a class="btn-ghost text-xs" href="' + esc(f.url) + '&dl=1">' + esc(S.download) + '</a>'
        + ((f.kind === 'image' || f.kind === 'video') ? '<button type="button" data-ac="public" class="btn-ghost text-xs">' + esc(S.copyLink) + '</button>' : '') + (M.canEdit ? '<button type="button" data-ac="del" class="btn-ghost text-xs !text-red-600">' + esc(S.del) + '</button>' : '') + '</div></div>';
    }
    function draw() { side(); bar(); grid(); info(); }

    // رفع بشريط تقدم (XHR)
    function upload(files) {
      files = [].slice.call(files || []); if (!files.length || !M.canEdit) return;
      var big = files.filter(function (f) { return f.size > M.max; }); big.forEach(function (f) { toast(fmt(S.tooBig, f.name, size(M.max)), 'err'); });
      files = files.filter(function (f) { return f.size <= M.max; }); if (!files.length) return;
      var prog = $('.md-prog'), row = document.createElement('div'); row.className = 'rounded-lg bg-ink-50 p-2 text-xs';
      row.innerHTML = '<div class="flex justify-between"><span>' + esc(fmt(S.uploading, files.length)) + '</span><span class="pc">0%</span></div><div class="h-1.5 mt-1 rounded-full bg-ink-200 overflow-hidden"><div class="h-full bg-brand-500 rounded-full" style="width:0"></div></div>';
      prog.appendChild(row);
      var fd = new FormData(); fd.append('_csrf', M.csrf); fd.append('action', 'upload'); fd.append('folder', st.folder === '*' ? '' : st.folder); files.forEach(function (f) { fd.append('files[]', f, f.name); });
      var x = new XMLHttpRequest(); x.open('POST', M.post); x.withCredentials = true;
      x.upload.onprogress = function (e) { if (e.lengthComputable) { var p = Math.round(e.loaded / e.total * 100); row.querySelector('.pc').textContent = p + '%'; row.querySelector('.bg-brand-500').style.width = p + '%'; } };
      x.onload = function () { row.remove(); var j = {}; try { j = JSON.parse(x.responseText); } catch (e) {} (j.errors || []).forEach(function (m) { toast(m, 'err'); }); var dups = (j.items || []).filter(function (x) { return x.dup; }).length; if (dups) toast(fmt(S.dups, dups), 'ok'); if ((j.items || []).length) { if (j.items.length > dups) toast(fmt(S.uploaded, j.items.length - dups), 'ok'); if (opt.picker) st.sel = opt.multiple ? st.sel.concat(j.items.filter(function (f) { return okKind(f.kind); }).map(function (f) { return f.id; })) : (j.items.filter(function (f) { return okKind(f.kind); }).slice(-1).map(function (f) { return f.id; })); st.cur = j.items[0].id; } load(); };
      x.onerror = function () { row.remove(); toast(S.publicErr, 'err'); };
      x.send(fd);
    }
    var drop = $('.md-drop');
    drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('border-brand-500', 'bg-brand-50'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('border-brand-500', 'bg-brand-50'); });
    drop.addEventListener('drop', function (e) { e.preventDefault(); drop.classList.remove('border-brand-500', 'bg-brand-50'); upload(e.dataTransfer.files); });
    $('.md-file').addEventListener('change', function () { upload(this.files); this.value = ''; });
    host.addEventListener('paste', function (e) { var fs = [].filter.call((e.clipboardData || {}).files || [], function () { return true; }); if (fs.length) upload(fs); });

    var qt, lastClick = null;
    host.addEventListener('input', function (e) { if (e.target.classList.contains('md-q')) { clearTimeout(qt); qt = setTimeout(function () { st.q = e.target.value.trim(); load().then(function () { var q = $('.md-q'); q.focus(); q.setSelectionRange(q.value.length, q.value.length); }); }, 300); } });
    host.addEventListener('change', function (e) {
      if (e.target.classList.contains('md-sort')) { st.sort = e.target.value; grid(); }
      if (e.target.classList.contains('md-tags')) post({ action: 'tags', id: st.cur, tags: e.target.value }).then(function () { load(); });
    });
    host.addEventListener('click', function (e) {
      var fd = e.target.closest('[data-fd]'), kd = e.target.closest('[data-kd]'), it = e.target.closest('[data-it]'), bk = e.target.closest('[data-bk]'), ac = e.target.closest('[data-ac]');
      if (fd) { st.folder = fd.dataset.fd; st.sel = []; load(); return; }
      if (kd) { e.preventDefault(); st.kind = kd.dataset.kd; load(); return; }
      if (e.target.closest('.md-nf')) { var n = prompt(S.folderQ); if (n && n.trim()) post({ action: 'folder', name: n.trim() }).then(function (j) { if (j.ok) { st.folder = j.folder; load(); } }); return; }
      if (it) {
        var id = +it.dataset.it, f = st.items.filter(function (x) { return x.id === id; })[0]; if (!f) return;
        if (opt.picker && !okKind(f.kind)) { toast(S.notAllowed, 'err'); return; }
        var multi = !opt.picker || opt.multiple, i = st.sel.indexOf(id);
        if (e.shiftKey && lastClick != null && multi) { var ids = st.items.map(function (x) { return x.id; }), a = ids.indexOf(lastClick), b = ids.indexOf(id); ids.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (x) { if (st.sel.indexOf(x) < 0) st.sel.push(x); }); }
        else if (e.target.closest('.md-ck') || e.ctrlKey || e.metaKey || (opt.picker && multi)) { if (i >= 0) st.sel.splice(i, 1); else st.sel.push(id); }
        else if (opt.picker) { st.sel = [id]; if (e.detail === 2) { finish(); return; } }
        else if (e.detail >= 2) { st.sel = [id]; lastClick = id; st.cur = id; bar(); grid(); info(); return lightbox(id); }
        else st.sel = i >= 0 && st.sel.length === 1 ? [] : [id];
        lastClick = id; st.cur = id; bar(); grid(); info(); return;
      }
      if (bk) {
        if (bk.dataset.bk === 'use') return finish();
        if (bk.dataset.bk === 'del' && confirm(fmt(S.delQ, st.sel.length))) post({ action: 'delete', 'ids[]': st.sel }).then(function () { st.sel = []; st.cur = null; load(); });
        if (bk.dataset.bk === 'move') { var to = prompt(S.moveQ, st.folder === '*' ? '' : st.folder); if (to !== null) post({ action: 'move', folder: to.trim(), 'ids[]': st.sel }).then(function () { load(); }); }
        return;
      }
      if (ac) {
        var cur = st.items.filter(function (x) { return x.id === st.cur; })[0]; if (!cur) return;
        if (ac.dataset.ac === 'view') return lightbox(cur.id);
        if (ac.dataset.ac === 'crop') return cropper(cur);
        if (ac.dataset.ac === 'rename') { var nn = prompt(S.renameQ, cur.name); if (nn && nn.trim()) post({ action: 'rename', id: cur.id, name: nn.trim() }).then(function () { load(); }); }
        if (ac.dataset.ac === 'del' && confirm(fmt(S.delQ, 1))) post({ action: 'delete', id: cur.id }).then(function () { st.cur = null; load(); });
        if (ac.dataset.ac === 'public') { ac.disabled = true; ac.textContent = S.preparing; post({ action: 'public', id: cur.id }).then(function (j) { ac.disabled = false; ac.textContent = S.copyLink; var u = j.ok && j.urls[cur.id]; if (!u) return toast(j.error || S.publicErr, 'err'); (navigator.clipboard ? navigator.clipboard.writeText(u) : Promise.reject()).then(function () { toast(S.copied, 'ok'); }).catch(function () { prompt('URL', u); }); load(); }); }
      }
    });
    host.addEventListener('dblclick', function (e) { var it = e.target.closest('[data-it]'); if (it && !opt.picker) lightbox(+it.dataset.it); });
    host.addEventListener('dragstart', function (e) { var it = e.target.closest('[data-it]'); if (!it) return; var id = +it.dataset.it; var ids = st.sel.indexOf(id) >= 0 ? st.sel.slice() : [id]; e.dataTransfer.setData('text/x-media', JSON.stringify(ids)); e.dataTransfer.effectAllowed = 'move'; });
    host.addEventListener('dragover', function (e) { var fd = e.target.closest('[data-fd]'); if (fd && fd.dataset.fd !== '*' && [].indexOf.call(e.dataTransfer.types, 'text/x-media') >= 0) { e.preventDefault(); fd.classList.add('ring-2', 'ring-brand-400'); } });
    host.addEventListener('dragleave', function (e) { var fd = e.target.closest('[data-fd]'); if (fd) fd.classList.remove('ring-2', 'ring-brand-400'); });
    host.addEventListener('drop', function (e) { var fd = e.target.closest('[data-fd]'); if (!fd) return; var raw = e.dataTransfer.getData('text/x-media'); if (!raw) return; e.preventDefault(); fd.classList.remove('ring-2', 'ring-brand-400'); post({ action: 'move', folder: fd.dataset.fd, 'ids[]': JSON.parse(raw) }).then(function () { toast(S.moved, 'ok'); load(); }); });
    // عرض كبير بتنقل بالأسهم
    function lightbox(id) {
      var vis = st.items.filter(function (x) { return x.kind === 'image' || x.kind === 'video'; }), i = vis.map(function (x) { return x.id; }).indexOf(id); if (i < 0) return;
      var d = document.createElement('dialog'); d.className = 'w-screen h-screen max-w-none max-h-none m-0 p-0 bg-black/95 text-white backdrop:bg-black/80';
      var show = function () { var f = vis[i]; d.innerHTML = '<div class="h-full flex flex-col"><div class="flex items-center gap-3 px-4 h-12 text-sm"><b class="flex-1 truncate">' + esc(f.name) + '</b><span class="text-white/60">' + (i + 1) + ' / ' + vis.length + '</span><button type="button" class="x px-2 text-xl">✕</button></div><div class="flex-1 flex items-center justify-center min-h-0 relative"><button type="button" class="pv absolute start-2 text-4xl px-3 opacity-70 hover:opacity-100">‹</button>' + (f.kind === 'image' ? '<img src="' + esc(f.url) + '" alt="" class="max-w-full max-h-full object-contain">' : '<video src="' + esc(f.url) + '" controls autoplay class="max-w-full max-h-full"></video>') + '<button type="button" class="nx absolute end-2 text-4xl px-3 opacity-70 hover:opacity-100">›</button></div></div>';
        d.querySelector('.x').onclick = function () { d.close(); }; d.querySelector('.pv').onclick = function () { i = (i - 1 + vis.length) % vis.length; show(); }; d.querySelector('.nx').onclick = function () { i = (i + 1) % vis.length; show(); }; };
      d.addEventListener('keydown', function (e) { var rtl = document.documentElement.dir === 'rtl'; if (e.key === 'ArrowRight') { i = (i + (rtl ? -1 : 1) + vis.length) % vis.length; show(); } if (e.key === 'ArrowLeft') { i = (i + (rtl ? 1 : -1) + vis.length) % vis.length; show(); } });
      d.addEventListener('close', function () { d.remove(); }); document.body.appendChild(d); show(); d.showModal();
    }
    // قص لمقاسات الإعلانات (في المتصفح) — بيحفظ نسخة جديدة ومش بيلمس الأصل
    function cropper(f) {
      var d = document.createElement('dialog'); d.className = 'w-[min(860px,96vw)] p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl backdrop:bg-ink-900/50';
      d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm">✂ ' + esc(S.crop) + ' · ' + esc(f.name) + '</b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div>'
        + '<div class="p-4 space-y-3"><div class="flex flex-wrap gap-1.5 rt">' + [['1:1', 1, S.rFeed], ['4:5', 0.8, S.rFeedTall], ['9:16', 0.5625, S.rStory], ['1.91:1', 1.91, S.rLink], ['16:9', 1.7778, S.rWide]].map(function (r, k) { return '<button type="button" data-r="' + r[1] + '" class="rounded-lg border px-3 py-1.5 text-xs ' + (k ? 'border-ink-200' : 'border-brand-500 bg-brand-50') + '"><b dir="ltr">' + r[0] + '</b> <span class="text-ink-500">' + esc(r[2]) + '</span></button>'; }).join('') + '</div>'
        + '<div class="relative bg-ink-900 rounded-xl overflow-hidden grid place-items-center" style="height:min(56vh,460px)"><canvas class="cv max-w-full max-h-full cursor-move touch-none"></canvas></div>'
        + '<div class="flex flex-wrap items-center gap-3"><label class="flex items-center gap-2 text-xs text-ink-600 flex-1 min-w-[12rem]">' + esc(S.zoom) + ' <input type="range" class="zm flex-1" min="1" max="3" step="0.01" value="1"></label><span class="info text-xs text-ink-500" dir="ltr"></span><button type="button" class="btn-primary go">' + esc(S.saveCopy) + '</button></div><p class="text-[11px] text-ink-500">' + esc(S.cropHint) + '</p></div>';
      document.body.appendChild(d); d.addEventListener('close', function () { d.remove(); }); d.querySelector('.x').onclick = function () { d.close(); }; d.showModal();
      var img = new Image(), cv = d.querySelector('.cv'), cx = cv.getContext('2d'), ratio = 1, zoom = 1, ox = 0.5, oy = 0.5, drag = null;
      img.onload = function () { draw(); }; img.src = f.url;
      function geo() { var W = img.naturalWidth, H = img.naturalHeight, cw, ch; if (W / H > ratio) { ch = H / zoom; cw = ch * ratio; } else { cw = W / zoom; ch = cw / ratio; } var sx = Math.max(0, Math.min(W - cw, ox * W - cw / 2)), sy = Math.max(0, Math.min(H - ch, oy * H - ch / 2)); return { sx: sx, sy: sy, cw: cw, ch: ch, W: W, H: H }; }
      function draw() { var g = geo(), maxW = 760, k = Math.min(maxW / g.cw, 440 / g.ch, 1); cv.width = Math.round(g.cw * k); cv.height = Math.round(g.ch * k); cx.drawImage(img, g.sx, g.sy, g.cw, g.ch, 0, 0, cv.width, cv.height);
        var outW = Math.min(1080 * (ratio >= 1 ? ratio : 1), Math.round(g.cw)), outH = Math.round(outW / ratio); d.querySelector('.info').textContent = Math.round(outW) + '×' + outH + (Math.min(g.cw, g.ch) < 600 ? '  ⚠' : ''); }
      d.querySelector('.rt').onclick = function (e) { var b = e.target.closest('[data-r]'); if (!b) return; ratio = +b.dataset.r; [].forEach.call(this.children, function (x) { x.className = 'rounded-lg border px-3 py-1.5 text-xs ' + (x === b ? 'border-brand-500 bg-brand-50' : 'border-ink-200'); }); draw(); };
      d.querySelector('.zm').oninput = function () { zoom = +this.value; draw(); };
      cv.addEventListener('pointerdown', function (e) { drag = { x: e.clientX, y: e.clientY, ox: ox, oy: oy }; cv.setPointerCapture(e.pointerId); });
      cv.addEventListener('pointermove', function (e) { if (!drag) return; var g = geo(), k = cv.width / g.cw; ox = drag.ox - (e.clientX - drag.x) / k / g.W; oy = drag.oy - (e.clientY - drag.y) / k / g.H; ox = Math.max(0, Math.min(1, ox)); oy = Math.max(0, Math.min(1, oy)); draw(); });
      cv.addEventListener('pointerup', function () { drag = null; });
      d.querySelector('.go').onclick = function () {
        var b = this, g = geo(), outW = Math.round(Math.min(1080 * (ratio >= 1 ? ratio : 1), g.cw)), outH = Math.round(outW / ratio), c2 = document.createElement('canvas'); c2.width = outW; c2.height = outH;
        c2.getContext('2d').drawImage(img, g.sx, g.sy, g.cw, g.ch, 0, 0, outW, outH); b.disabled = true; b.textContent = S.saving;
        c2.toBlob(function (blob) {
          var rn = d.querySelector('.rt [data-r].bg-brand-50 b').textContent.replace(':', 'x'), base = f.name.replace(/\.[^.]+$/, ''), fd = new FormData();
          fd.append('_csrf', M.csrf); fd.append('action', 'derive'); fd.append('src', f.id); fd.append('name', base + '-' + rn + '.jpg'); fd.append('file', blob, base + '-' + rn + '.jpg');
          fetch(M.post, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) { b.disabled = false; b.textContent = S.saveCopy; return toast(j.error || S.publicErr, 'err'); } toast(S.cropped, 'ok'); d.close(); st.cur = j.item.id; st.sel = [j.item.id]; load(); });
        }, 'image/jpeg', 0.9);
      };
    }
    host.addEventListener('keydown', function (e) { if (e.key === 'Delete' && st.sel.length && M.canEdit && !opt.picker && !/INPUT|TEXTAREA/.test(e.target.tagName)) { if (confirm(fmt(S.delQ, st.sel.length))) post({ action: 'delete', 'ids[]': st.sel }).then(function () { st.sel = []; load(); }); } });
    var done = opt.onDone || function () {};
    function finish() {
      var out = st.items.filter(function (x) { return st.sel.indexOf(x.id) >= 0 && okKind(x.kind); }); if (!out.length) return;
      if (!opt.public) return done(out);
      var b = host.querySelector('[data-bk=use]'); if (b) { b.disabled = true; b.textContent = S.preparing; }
      post({ action: 'public', 'ids[]': out.map(function (x) { return x.id; }) }).then(function (j) {
        if (!j.ok) { if (b) { b.disabled = false; b.textContent = S.useSel; } return toast(j.error || S.publicErr, 'err'); }
        out.forEach(function (x) { x.public = j.urls[x.id] || x.public; }); done(out);
      }).catch(function () { if (b) { b.disabled = false; b.textContent = S.useSel; } toast(S.publicErr, 'err'); });
    }
    load();
    return { reload: load };
  }

  window.Media = {
    page: function (el) { Browser(el, {}); },
    pick: function (o) {
      return new Promise(function (res) {
        var d = document.createElement('dialog'); d.className = 'w-[min(1100px,96vw)] p-0 rounded-2xl border border-ink-200 bg-ink-50 text-ink-900 shadow-2xl backdrop:bg-ink-900/40';
        d.innerHTML = '<div class="flex items-center gap-2 px-5 h-12 border-b border-ink-200 bg-white"><b class="flex-1 text-sm">' + esc(S.pick) + '</b><a href="' + esc(M.page) + '" target="_blank" class="link text-xs">' + esc(S.open) + ' ↗</a><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100" aria-label="' + esc(S.close) + '">✕</button></div><div class="bd p-4 max-h-[78vh] overflow-y-auto"></div>';
        document.body.appendChild(d); var result = null;
        d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); res(result); });
        Browser(d.querySelector('.bd'), { picker: true, accept: o.accept || 'any', multiple: !!o.multiple, public: o.public !== false, onDone: function (files) { result = files; d.close(); } });
        d.showModal();
      });
    }
  };
})();
