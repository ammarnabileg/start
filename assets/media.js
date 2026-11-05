/* مكتبة الوسائط — مدير ملفات بشكل الكمبيوتر: شريط أدوات (رجوع/تقدم/لفوق)، مسار، وصول سريع وشجرة فولدرات،
 * 3 طرق عرض، تحديد بالسحب، كليك يمين، تسمية جوه الاسم (F2)، اختصارات كيبورد، شريط حالة، لوحة معاينة.
 *   Media.page(el)              صفحة كاملة
 *   mediaPick({accept: 'image'|'video'|'image,video'|'any', multiple, public}) → Promise([{id,name,kind,mime,url,thumb,public,w,h}] | null) */
(function () {
  var M = window.MEDIA; if (!M || window.Media) return; var S = M.s;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var fmt = function (s) { var a = [].slice.call(arguments, 1); return String(s || '').replace(/%s/g, function () { return a.length ? a.shift() : ''; }); };
  var size = function (b) { return b >= 1073741824 ? (b / 1073741824).toFixed(1) + ' GB' : b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; };
  var toast = function (m, k) { (window.aiToast || function () {})(m, k); };
  var store = { get: function (k, d) { try { var v = localStorage.getItem('md_' + k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } }, set: function (k, v) { try { localStorage.setItem('md_' + k, JSON.stringify(v)); } catch (e) {} } };
  var rtl = function () { return document.documentElement.dir === 'rtl'; };
  var IC = {
    folder: '<svg viewBox="0 0 48 40" class="w-full h-full"><path d="M4 6a3 3 0 013-3h11l4 5h19a3 3 0 013 3v23a3 3 0 01-3 3H7a3 3 0 01-3-3z" fill="#F5B83D"/><path d="M4 13h40v21a3 3 0 01-3 3H7a3 3 0 01-3-3z" fill="#FFCD5C"/></svg>',
    doc: '<svg viewBox="0 0 40 48" class="w-full h-full"><path d="M6 2h20l10 10v32a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" fill="#fff" stroke="#C3CDDF" stroke-width="2"/><path d="M26 2v10h10" fill="#EDF1F8" stroke="#C3CDDF" stroke-width="2"/></svg>',
    back: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>', up: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19V5M6 11l6-6 6 6"/></svg>',
    grid: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>', gridS: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h4v4H4zM10 4h4v4h-4zM16 4h4v4h-4zM4 10h4v4H4zM10 10h4v4h-4zM16 10h4v4h-4zM4 16h4v4H4zM10 16h4v4h-4zM16 16h4v4h-4z"/></svg>',
    list: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></svg>', pane: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4h18v16H3zM15 4v16"/></svg>',
    upl: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 16V4M6 10l6-6 6 6M4 20h16"/></svg>', newf: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7a2 2 0 012-2h5l2 2h7a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2zM12 11v5M9.5 13.5h5"/></svg>',
    ref: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 12a8 8 0 11-2.3-5.7M20 4v5h-5"/></svg>'
  };
  var RATIOS = [['1:1', 1], ['4:5', 0.8], ['9:16', 0.5625], ['16:9', 1.7778], ['1.91:1', 1.91], ['2:3', 0.6667], ['3:2', 1.5], ['4:3', 1.3333], ['3:4', 0.75]];
  function ratioOf(f) { if (!f.w || !f.h) return null; var r = f.w / f.h, best = null; RATIOS.forEach(function (x) { var d = Math.abs(Math.log(r / x[1])); if (d < 0.03 && (!best || d < best[1])) best = [x[0], d]; }); return best ? best[0] : null; }
  var small = function (f) { return f.kind === 'image' && f.w && Math.min(f.w, f.h) < 600; };
  var ext = function (n) { var m = /\.([a-z0-9]{1,5})$/i.exec(n); return m ? m[1].toUpperCase() : ''; };
  var typeName = function (f) { return f.kind === 'image' ? S.tImage + ' ' + ext(f.name) : f.kind === 'video' ? S.tVideo + ' ' + ext(f.name) : f.kind === 'audio' ? S.tAudio : ext(f.name) + ' ' + S.tFile; };
  var when = function (s) { var d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? s : d.toLocaleDateString(M.lang === 'ar' ? 'ar-EG' : 'en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString(M.lang === 'ar' ? 'ar-EG' : 'en-GB', { hour: '2-digit', minute: '2-digit' }); };
  function post(f) { var fd = new FormData(); fd.append('_csrf', M.csrf); Object.keys(f).forEach(function (k) { [].concat(f[k]).forEach(function (v) { fd.append(k, v); }); }); return fetch(M.post, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

  function Explorer(host, opt) {
    opt = opt || {};
    var accept = (opt.accept || 'any').split(','), anyOk = accept.indexOf('any') >= 0;
    var okKind = function (k) { return anyOk || accept.indexOf(k) >= 0; };
    var st = { loc: opt.picker ? { t: 'all', f: '' } : store.get('loc', { t: 'all', f: '' }), hist: [], fwd: [], view: store.get('view', 'icons'), sort: store.get('sort', ['date', -1]), pane: store.get('pane', !opt.picker), q: '', all: [], trash: [], folders: {}, usage: 0, sel: [], focus: null, clip: null, zoom: store.get('zoom', 1) };
    if (opt.picker && !anyOk && accept.length === 1) st.loc = { t: accept[0], f: '' };
    host.innerHTML = '<div class="mx-win flex flex-col rounded-2xl border border-ink-200 bg-white shadow-sm overflow-hidden select-none text-[13px] outline-none" style="height:' + (opt.picker ? 'min(74vh,660px)' : 'calc(100vh - 170px)') + ';min-height:440px" tabindex="0">'
      + '<div class="mx-tb flex flex-wrap items-center gap-1.5 px-3 py-2 border-b border-ink-200 bg-ink-50"></div>'
      + '<div class="relative flex flex-1 min-h-0"><nav class="mx-nav w-52 shrink-0 border-e border-ink-200 bg-ink-50/60 overflow-y-auto py-2 hidden sm:block"></nav>'
      + '<div class="mx-main relative flex-1 min-w-0 overflow-auto"><div class="mx-items p-3 min-h-full"></div><div class="mx-band hidden absolute border border-brand-500 bg-brand-500/15 pointer-events-none"></div><div class="mx-dropov hidden absolute inset-2 rounded-xl border-2 border-dashed border-brand-500 bg-brand-50/80 grid place-items-center text-brand-800 font-semibold pointer-events-none">' + esc(S.dropHere) + '</div></div>'
      + '<aside class="mx-pane w-72 shrink-0 border-s border-ink-200 overflow-y-auto hidden"></aside></div>'
      + '<div class="mx-prog"></div><div class="mx-sb flex items-center gap-3 px-3 h-7 border-t border-ink-200 bg-ink-50 text-[11px] text-ink-500"></div>'
      + (opt.picker ? '<div class="mx-foot flex flex-wrap items-center gap-2 px-3 py-2.5 border-t border-ink-200 bg-white"></div>' : '') + '<input type="file" multiple class="hidden mx-file"></div>';
    var win = host.querySelector('.mx-win'), $ = function (s) { return win.querySelector(s); };

    var PAGE = 200, reqId = 0, loadingMore = false;
    function qs(off) { var L = st.loc; return M.list + '&loc=' + encodeURIComponent(L.t) + '&f=' + encodeURIComponent(L.f || '') + '&q=' + encodeURIComponent(st.q) + '&sort=' + st.sort[0] + '&dir=' + st.sort[1] + '&offset=' + off + '&limit=' + PAGE; }
    function load() { // الصفحة الأولى للمكان الحالي + الأعداد والفولدرات
      var my = ++reqId; if (!st.all.length) skeleton();
      return fetch(qs(0), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        if (my !== reqId) return; st.all = j.items || []; st.total = j.total || 0; st.folders = j.folders || {}; st.counts = j.counts || {}; st.usage = j.usage || 0; if (j.max) M.max = j.max;
        st.trash = st.loc.t === 'trash' ? st.all : []; st.sel = st.sel.filter(function (k) { return k[0] === 'd' || st.all.some(function (x) { return 'f' + x.id === k; }); }); draw(); fillIfShort();
      });
    }
    function loadMore() { // الباقي وانت بتنزل لتحت
      if (loadingMore || st.all.length >= st.total) return; loadingMore = true; var my = reqId;
      fetch(qs(st.all.length), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        loadingMore = false; if (my !== reqId) return; var seen = {}; st.all.forEach(function (f) { seen[f.id] = 1; }); (j.items || []).forEach(function (f) { if (!seen[f.id]) st.all.push(f); }); st.total = j.total || st.total;
        if (st.loc.t === 'trash') st.trash = st.all; items(); paintSel(); status(); fillIfShort();
      }).catch(function () { loadingMore = false; });
    }
    function fillIfShort() { var m = win.querySelector('.mx-main'); if (m && m.scrollHeight <= m.clientHeight + 40) loadMore(); }
    function skeleton() { var el = win.querySelector('.mx-items'); if (el) el.innerHTML = '<div class="flex flex-wrap gap-1">' + Array(18).join('<div class="w-28 p-1.5"><div class="h-20 rounded-md bg-ink-100 animate-pulse"></div><div class="h-2.5 mt-2 rounded bg-ink-100 animate-pulse"></div></div>') + '</div>'; }
    function subfolders(path) { var out = {}; Object.keys(st.folders).forEach(function (f) { if (!f) return; if (path === '' || f.indexOf(path + '/') === 0) { var rest = path === '' ? f : f.slice(path.length + 1), name = rest.split('/')[0]; if (name) out[name] = (out[name] || 0) + (st.folders[f] || 0); } }); return out; }
    function current() {
      var L = st.loc;
      if (L.t === 'trash') return st.all.map(function (f) { return { key: 'f' + f.id, f: f, trashed: true }; });
      var files = st.all, showDirs = (L.t === 'folder' || L.t === 'all') && !st.q, base = L.t === 'folder' ? L.f : '', subs = showDirs ? subfolders(base) : {};
      return Object.keys(subs).sort(function (a, b) { return a.localeCompare(b); }).map(function (n) { return { dir: true, key: 'd' + (base ? base + '/' : '') + n, path: (base ? base + '/' : '') + n, name: n, n: subs[n] }; }).concat(files.map(function (f) { return { key: 'f' + f.id, f: f }; }));
    }
    var locName = function (L) { return L.t === 'folder' ? (L.f.split('/').pop() || S.noFolder) : { all: S.allFolders, recent: S.recent, star: S.starred, trash: S.trash, image: S.images, video: S.videos, doc: S.docs }[L.t]; };
    function go(L, noHist) { if (!noHist) { st.hist.push(st.loc); st.fwd = []; } st.loc = L; st.sel = []; st.focus = null; st.all = []; st.total = 0; if (!opt.picker) store.set('loc', L); toolbar(); nav(); load(); var m = win.querySelector('.mx-main'); if (m) m.scrollTop = 0; }

    function draw() { toolbar(); nav(); items(); pane(); status(); foot(); }
    function toolbar() {
      var L = st.loc, sep = '<span class="text-ink-400">' + (rtl() ? '‹' : '›') + '</span>', crumbs = '<button type="button" data-go="all" class="rounded px-1.5 py-0.5 hover:bg-ink-200/70">' + esc(S.title) + '</button>';
      if (L.t === 'folder') { var acc = ''; (L.f ? L.f.split('/') : ['']).forEach(function (p) { acc = acc ? acc + '/' + p : p; crumbs += sep + '<button type="button" data-gof="' + esc(acc) + '" class="rounded px-1.5 py-0.5 hover:bg-ink-200/70">' + esc(p || S.noFolder) + '</button>'; }); }
      else if (L.t !== 'all') crumbs += sep + '<span class="px-1.5">' + esc(locName(L)) + '</span>';
      var btn = function (cls, ic, title, dis, on, extra) { return '<button type="button" ' + (extra || '') + ' class="' + cls + ' w-8 h-8 grid place-items-center rounded-lg ' + (on ? 'bg-white shadow-sm text-brand-700' : 'text-ink-600 hover:bg-ink-200/60') + ' disabled:opacity-30" title="' + esc(title) + '" aria-label="' + esc(title) + '"' + (dis ? ' disabled' : '') + '>' + ic + '</button>'; };
      var flip = function (ic, f) { return '<span style="display:inline-block;transform:scaleX(' + f + ')">' + ic + '</span>'; };
      $('.mx-tb').innerHTML = btn('mx-navt sm:hidden', '☰', S.foldersT) + btn('mx-back', flip(IC.back, rtl() ? -1 : 1), S.back, !st.hist.length) + btn('mx-fwd', flip(IC.back, rtl() ? 1 : -1), S.fwd, !st.fwd.length) + btn('mx-up', IC.up, S.up, L.t !== 'folder')
        + '<div class="flex-1 min-w-[10rem] flex items-center gap-0.5 h-8 rounded-lg border border-ink-200 bg-white px-2 overflow-x-auto whitespace-nowrap">' + crumbs + '</div>'
        + '<input type="search" class="mx-q input !mt-0 !h-8 !py-0 w-48 text-[13px]" placeholder="' + esc(S.search) + '" value="' + esc(st.q) + '">'
        + '<span class="w-px h-6 bg-ink-200 mx-1"></span>' + (M.canEdit ? btn('mx-nf', IC.newf, S.newFolder) + btn('mx-upl', IC.upl, S.upload) : '') + btn('mx-ref', IC.ref, S.refresh)
        + '<span class="w-px h-6 bg-ink-200 mx-1"></span><div class="flex rounded-lg bg-ink-100 p-0.5">' + btn('mx-v', IC.grid, S.vBig, false, st.view === 'big', 'data-v="big"') + btn('mx-v', IC.gridS, S.vIcons, false, st.view === 'icons', 'data-v="icons"') + btn('mx-v', IC.list, S.vList, false, st.view === 'list', 'data-v="list"') + '</div>'
        + btn('mx-pn hidden lg:grid', IC.pane, S.pane, false, st.pane)
        + (!opt.picker && M.canEdit && (L.t === 'folder' || L.t === 'all') ? btn('mx-share', '🔗', S.shareT) : '') + (M.canAi && M.canEdit && L.t !== 'trash' ? btn('mx-ai', '✦', S.aiOrg) : '') + btn('mx-help', '?', S.helpT);
    }
    function nav() {
      var L = st.loc, row = function (attrs, ic, label, n, on, lvl) { return '<button type="button" ' + attrs + ' class="w-full flex items-center gap-2 py-1.5 text-start rounded-md ' + (on ? 'bg-brand-100/70 text-brand-900 font-medium' : 'text-ink-700 hover:bg-ink-200/50') + '" style="padding-inline:' + (8 + (lvl || 0) * 14) + 'px 8px"><span class="w-4 h-4 shrink-0 grid place-items-center">' + ic + '</span><span class="flex-1 truncate">' + esc(label) + '</span>' + (n != null ? '<span class="text-[10px] text-ink-400">' + n + '</span>' : '') + '</button>'; };
      var counts = st.counts || {};
      var h = '<div class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wide text-ink-400">' + esc(S.quick) + '</div><div class="px-1.5 space-y-px">'
        + [['all', '🗂', S.allFolders], ['recent', '🕘', S.recent], ['star', '⭐', S.starred], ['image', '🖼', S.images], ['video', '🎬', S.videos], ['doc', '📄', S.docs]].filter(function (x) { return ['all', 'recent', 'star'].indexOf(x[0]) >= 0 || okKind(x[0]); }).map(function (x) { return row('data-go="' + x[0] + '"', x[1], x[2], counts[x[0]], L.t === x[0]); }).join('') + '</div>'
        + '<div class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-wide text-ink-400 flex items-center">' + esc(S.foldersT) + (M.canEdit ? '<button type="button" class="mx-nf ms-auto text-ink-500 hover:text-ink-900 text-sm leading-none" title="' + esc(S.newFolder) + '">+</button>' : '') + '</div><div class="px-1.5 space-y-px">';
      var seen = {};
      Object.keys(st.folders).filter(Boolean).sort(function (a, b) { return a.localeCompare(b); }).forEach(function (p) { var acc = ''; p.split('/').forEach(function (part, i) { var parent = acc; acc = acc ? acc + '/' + part : part; if (seen[acc]) return; if (i > 0 && !(L.t === 'folder' && (L.f === parent || L.f.indexOf(parent + '/') === 0))) return; seen[acc] = 1; var open = L.t === 'folder' && (L.f === acc || L.f.indexOf(acc + '/') === 0); h += row('data-gof="' + esc(acc) + '" data-drop="' + esc(acc) + '"', open ? '📂' : '📁', part, st.folders[acc] || null, L.t === 'folder' && L.f === acc, i); }); });
      if (st.folders[''] != null) h += row('data-gof="" data-drop=""', '📁', S.noFolder, st.folders[''], L.t === 'folder' && L.f === '');
      if (!opt.picker) h += '</div><div class="px-1.5 pt-3">' + row('data-go="trash"', '🗑', S.trash, counts.trash || null, L.t === 'trash');
      var max = Math.max(st.usage * 1.25, 104857600);
      h += '</div><div class="px-3 pt-5 text-[11px] text-ink-500"><div class="h-1.5 rounded-full bg-ink-200 overflow-hidden"><div class="h-full bg-brand-500" style="width:' + Math.min(100, st.usage / max * 100).toFixed(1) + '%"></div></div><div class="mt-1">' + esc(fmt(S.usage, size(st.usage))) + '</div><div class="text-ink-400">' + esc(fmt(S.maxSize, size(M.max))) + '</div></div>';
      $('.mx-nav').innerHTML = h;
    }
    function thumb(f, big) {
      if (f.kind === 'image') return '<img src="' + esc(f.thumb || f.url) + '" alt="" loading="lazy" decoding="async" draggable="false" class="w-full h-full object-contain drop-shadow-sm">';
      if (f.kind === 'video') return '<span class="relative block w-full h-full"><video data-src="' + esc(f.url) + '#t=0.5" preload="none" muted playsinline class="w-full h-full object-cover rounded-md bg-ink-800"></video><span class="absolute inset-0 grid place-items-center text-white text-xl drop-shadow">▶</span></span>';
      return '<span class="relative block ' + (big ? 'w-16 h-20' : 'w-10 h-12') + '">' + IC.doc + '<span class="absolute bottom-1.5 inset-x-0 text-center text-[9px] font-bold text-ink-500">' + esc(ext(f.name)) + '</span></span>';
    }
    function items() {
      var list = current(), el = $('.mx-items'), isSel = function (k) { return st.sel.indexOf(k) >= 0; };
      if (!list.length) { el.innerHTML = '<div class="min-h-[18rem] grid place-items-center text-center text-ink-500"><div><div class="text-4xl mb-2">🗂</div><p>' + esc(st.q ? S.noRes : S.empty) + '</p>' + (M.canEdit ? '<button type="button" class="mx-upl btn-ghost text-xs mt-3">' + esc(S.upload) + '</button>' : '') + '</div></div>'; return; }
      if (st.view === 'list') {
        var hd = function (k, l, cls) { var on = st.sort[0] === k; return '<th class="' + cls + ' font-medium px-3 py-1.5 cursor-pointer hover:bg-ink-100" data-sort="' + k + '">' + esc(l) + (on ? ' <span class="text-brand-600">' + (st.sort[1] > 0 ? '▲' : '▼') + '</span>' : '') + '</th>'; };
        el.innerHTML = '<table class="w-full"><thead class="sticky top-0 bg-white text-ink-500 text-[11px] border-b border-ink-200"><tr>' + hd('name', S.name, 'text-start') + hd('date', S.date, 'text-start hidden md:table-cell') + hd('type', S.type, 'text-start hidden sm:table-cell') + hd('size', S.size, 'text-end') + '</tr></thead><tbody>'
          + list.map(function (x) {
            var on = isSel(x.key), dim = x.f && !okKind(x.f.kind);
            var ic = x.dir ? '<span class="w-5 h-4 inline-block">' + IC.folder + '</span>' : x.f.kind === 'image' ? '<img src="' + esc(x.f.thumb || x.f.url) + '" alt="" loading="lazy" decoding="async" draggable="false" class="w-5 h-5 rounded object-cover">' : x.f.kind === 'video' ? '🎬' : '<span class="w-4 h-5 inline-block">' + IC.doc + '</span>';
            return '<tr data-k="' + esc(x.key) + '" draggable="' + (M.canEdit && !x.trashed ? 'true' : 'false') + '" class="mx-it' + ((st.clip && st.clip.keys || []).indexOf(x.key) >= 0 ? ' opacity-50' : '') + ' ' + (on ? 'bg-brand-100/80' : 'hover:bg-ink-50') + (dim ? ' opacity-40' : '') + (st.focus === x.key ? ' outline outline-1 outline-brand-400 -outline-offset-1' : '') + '"' + (x.dir ? ' data-drop="' + esc(x.path) + '"' : '') + '>'
              + '<td class="px-3 py-1.5"><span class="flex items-center gap-2 min-w-0"><span class="shrink-0 w-5 grid place-items-center">' + ic + '</span><span class="mx-nm truncate">' + esc(x.dir ? x.name : x.f.name) + '</span>' + (x.f && x.f.star ? '⭐' : '') + (x.f && small(x.f) ? '<span class="text-[9px] rounded bg-amber-100 text-amber-800 px-1">' + esc(S.small) + '</span>' : '') + '</span></td>'
              + '<td class="px-3 py-1.5 text-ink-500 hidden md:table-cell whitespace-nowrap">' + (x.f ? esc(when(x.trashed ? x.f.del : x.f.at)) : '') + '</td><td class="px-3 py-1.5 text-ink-500 hidden sm:table-cell whitespace-nowrap">' + esc(x.dir ? S.folderT : typeName(x.f)) + (x.f && x.f.w ? ' <span dir="ltr">' + x.f.w + '×' + x.f.h + '</span>' : '') + '</td>'
              + '<td class="px-3 py-1.5 text-ink-500 text-end whitespace-nowrap" dir="ltr">' + (x.dir ? (x.n || 0) + ' ' + esc(S.itemsW) : esc(size(x.f.size))) + '</td></tr>';
          }).join('') + '</tbody></table>';
        return;
      }
      var big = st.view === 'big', z = st.zoom || 1, cw = Math.round((big ? 160 : 112) * z), bh = Math.round((big ? 128 : 80) * z), cut = st.clip && st.clip.keys || [];
      var groupOn = st.sort[0] === 'date' && st.loc.t !== 'trash', lastG = null, now = new Date(), d0 = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
      var gOf = function (f) { var t = new Date(String(f.at).replace(' ', 'T')).getTime(), days = (d0 - t) / 864e5; return t >= d0 ? S.gToday : days < 1 ? S.gYesterday : days < 7 ? S.gWeek : days < 30 ? S.gMonth : S.gOlder; };
      el.innerHTML = '<div class="flex flex-wrap gap-1 content-start">' + list.map(function (x) {
        var head = ''; if (groupOn) { var g = x.dir ? S.foldersT : gOf(x.f); if (g !== lastG) { lastG = g; head = '<div class="w-full flex items-center gap-2 pt-3 pb-1 text-[11px] font-semibold text-ink-500 first:pt-0"><span>' + esc(g) + '</span><span class="flex-1 h-px bg-ink-200"></span></div>'; } }
        var on = isSel(x.key), dim = x.f && !okKind(x.f.kind), rb = x.f ? ratioOf(x.f) : null;
        return head + '<div data-k="' + esc(x.key) + '" style="width:' + cw + 'px" draggable="' + (M.canEdit && !x.trashed ? 'true' : 'false') + '" class="mx-it' + (cut.indexOf(x.key) >= 0 ? ' opacity-50' : '') + ' relative rounded-lg p-1.5 flex flex-col items-center text-center cursor-default ' + (on ? 'bg-brand-100/80 ring-1 ring-brand-300' : 'hover:bg-ink-100/70') + (dim ? ' opacity-40' : '') + (st.focus === x.key ? ' outline outline-1 outline-brand-400' : '') + '"' + (x.dir ? ' data-drop="' + esc(x.path) + '"' : '') + ' title="' + esc(x.dir ? x.name : x.f.name + ' · ' + size(x.f.size) + (x.f.w ? ' · ' + x.f.w + '×' + x.f.h : '')) + '">'
          + '<span class="relative w-full grid place-items-center overflow-hidden" style="height:' + bh + 'px">' + (x.dir ? '<span class="' + (big ? 'w-24 h-20' : 'w-14 h-12') + '">' + IC.folder + '</span>' : thumb(x.f, big))
          + (x.f && big && rb ? '<span class="absolute bottom-0.5 end-0.5 rounded bg-black/60 text-white text-[9px] px-1" dir="ltr">' + rb + '</span>' : '') + (x.f && small(x.f) ? '<span class="absolute top-0.5 end-0.5 rounded bg-amber-500 text-white text-[9px] px-1">' + esc(S.small) + '</span>' : '') + (x.f && x.f.star ? '<span class="absolute top-0.5 start-0.5 text-[13px] drop-shadow">⭐</span>' : '') + '</span>'
          + '<span class="mx-nm mt-1 w-full text-[12px] leading-4 break-words line-clamp-2 ' + (on ? 'text-brand-900' : 'text-ink-800') + '">' + esc(x.dir ? x.name : x.f.name) + '</span>'
          + '<span class="mx-tick absolute top-1 start-1 w-5 h-5 rounded-md border-2 grid place-items-center text-[11px] ' + (on ? 'bg-brand-600 border-brand-600 text-white' : 'bg-white/90 border-ink-300') + ' [@media(hover:hover)]:hidden">' + (on ? '✓' : '') + '</span></div>';
      }).join('') + '</div>';
    }
    function selFiles() { var src = st.all; return st.sel.filter(function (k) { return k[0] === 'f'; }).map(function (k) { var id = +k.slice(1); return src.filter(function (f) { return f.id === id; })[0]; }).filter(Boolean); }
    function pane() {
      var p = $('.mx-pane'); p.classList.toggle('lg:block', !!st.pane); if (!st.pane) return;
      var fs = selFiles();
      if (fs.length !== 1) { p.innerHTML = '<div class="p-4 text-ink-500 text-center"><div class="text-4xl mb-2">' + (fs.length ? '🗂' : 'ℹ') + '</div>' + esc(fs.length ? fmt(S.selected, fs.length) + ' · ' + size(fs.reduce(function (n, f) { return n + f.size; }, 0)) : S.paneHint) + '</div>'; return; }
      var f = fs[0], pv = f.kind === 'image' ? '<img src="' + esc(f.url) + '" alt="" class="w-full rounded-lg bg-ink-100">' : f.kind === 'video' ? '<video src="' + esc(f.url) + '" controls class="w-full rounded-lg bg-black"></video>' : f.kind === 'audio' ? '<audio src="' + esc(f.url) + '" controls class="w-full"></audio>' : '<div class="grid place-items-center h-40 rounded-lg bg-ink-50"><span class="w-16 h-20">' + IC.doc + '</span></div>';
      var row = function (k, v) { return '<dt class="text-ink-500">' + esc(k) + '</dt><dd class="text-ink-800 break-all" dir="auto">' + esc(v) + '</dd>'; };
      p.innerHTML = '<div class="p-4 space-y-3">' + pv + '<b class="block text-ink-900 break-all">' + esc(f.name) + '</b><dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-[12px]">' + row(S.type, typeName(f)) + row(S.size, size(f.size)) + (f.w ? row(S.dims, f.w + ' × ' + f.h + (ratioOf(f) ? '  (' + ratioOf(f) + ')' : '')) : '') + row(S.date, when(f.at)) + row(S.where, f.folder || S.noFolder) + '</dl>'
        + (f.kind === 'image' ? '<p class="text-[11px] rounded-lg px-2 py-1.5 ' + (small(f) ? 'bg-amber-50 text-amber-800' : 'bg-lime-50 text-lime-800') + '">' + esc(small(f) ? S.smallHint : fmt(S.goodFor, ratioOf(f) || (f.w + '×' + f.h))) + '</p>' : '')
        + (f.desc ? '<p class="text-[12px] text-ink-700 bg-ink-50 rounded-lg px-2 py-1.5">✦ ' + esc(f.desc) + '</p>' : '')
        + (f.tags ? '<div class="flex flex-wrap gap-1">' + f.tags.split(',').map(function (t2) { t2 = t2.trim(); return t2 ? '<button type="button" data-tag="' + esc(t2) + '" class="rounded-full bg-brand-50 text-brand-800 text-[11px] px-2 py-0.5 hover:bg-brand-100">#' + esc(t2) + '</button>' : ''; }).join('') + '</div>' : '')
        + (f.kind === 'image' && M.canAi && M.canEdit ? '<button type="button" data-cmd="ai" class="w-full rounded-lg border border-brand-200 bg-brand-50 text-brand-800 text-xs py-1.5 hover:bg-brand-100">✦ ' + esc(f.ai ? S.aiRedo : S.aiTag) + '</button>' : '')
        + '<div class="mx-uses text-[11px]"></div>'
        + (M.canEdit ? '<label class="block"><span class="text-[11px] text-ink-500">' + esc(S.tags) + '</span><input class="mx-tags input !mt-1 !py-1 text-[13px]" value="' + esc(f.tags) + '"></label>' : '')
        + '<div class="grid grid-cols-2 gap-1.5">' + (f.kind === 'image' || f.kind === 'video' ? '<button type="button" data-cmd="open" class="btn-ghost !py-1 text-xs">' + esc(S.view) + '</button>' : '') + (f.kind === 'image' && M.canEdit ? '<button type="button" data-cmd="crop" class="btn-ghost !py-1 text-xs">✂ ' + esc(S.crop) + '</button><button type="button" data-cmd="edit" class="btn-ghost !py-1 text-xs">🎨 ' + esc(S.edit) + '</button>' : '') + '<a class="btn-ghost !py-1 text-xs" href="' + esc(f.url) + '&dl=1">' + esc(S.download) + '</a>' + ((f.kind === 'image' || f.kind === 'video') ? '<button type="button" data-cmd="link" class="btn-ghost !py-1 text-xs">' + esc(S.copyLink) + '</button>' : '') + '</div></div>';
    }
    var usesCache = {};
    function loadUses(f) {
      var box = win.querySelector('.mx-uses'); if (!box) return;
      var paint = function (u) { box.innerHTML = '<div class="text-ink-500 mb-1">' + esc(S.usedIn) + '</div>' + (u.length ? '<ul class="space-y-0.5">' + u.slice(0, 8).map(function (x) { return '<li class="flex gap-2"><span class="text-ink-800">' + esc(x.what) + '</span><span class="flex-1 truncate text-ink-400" dir="auto">' + esc(x.ref) + '</span><span class="text-ink-400">' + esc(when(x.at).split(' ').slice(0, 3).join(' ')) + '</span></li>'; }).join('') + '</ul>' : '<p class="text-ink-400">' + esc(S.notUsed) + '</p>'); };
      if (usesCache[f.id]) return paint(usesCache[f.id]);
      fetch(M.page + '&ajax=uses&id=' + f.id, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { usesCache[f.id] = j.uses || []; if (selFiles()[0] === f) paint(usesCache[f.id]); }).catch(function () {});
    }
    function status() {
      var list = current(), fs = selFiles();
      var dirsN = list.filter(function (x) { return x.dir; }).length;
      $('.mx-sb').innerHTML = '<span>' + esc(fmt(S.nItems, dirsN + (st.total || 0))) + (st.all.length < (st.total || 0) ? ' <span class="text-ink-400">(' + esc(fmt(S.loadedN, st.all.length)) + ')</span>' : '') + '</span>' + (st.sel.length ? '<span>' + esc(fmt(S.selected, st.sel.length)) + (fs.length ? ' · ' + esc(size(fs.reduce(function (n, f) { return n + f.size; }, 0))) : '') + '</span>' : '') + (st.clip ? '<span class="text-brand-700">✂ ' + esc(fmt(S.cutN, st.clip.keys.length)) + '</span>' : '') + '<span class="ms-auto hidden lg:inline truncate">' + esc(S.kbdHint) + '</span>'
        + (st.loc.t === 'trash' && (st.counts || {}).trash && M.canEdit ? '<button type="button" data-cmd="emptytrash" class="text-red-600 hover:underline">' + esc(S.emptyTrash) + '</button>' : '')
        + (st.view !== 'list' ? '<label class="flex items-center gap-1.5" title="' + esc(S.zoomT) + '">🔍<input type="range" class="mx-zoom w-20 accent-brand-600" min="0.7" max="2" step="0.05" value="' + (st.zoom || 1) + '"></label>' : '');
    }
    function foot() {
      var ft = $('.mx-foot'); if (!ft) return; var fs = selFiles().filter(function (f) { return okKind(f.kind); });
      ft.innerHTML = '<span class="text-ink-500 text-xs">' + esc(S.fileName) + '</span><div class="flex-1 min-w-[10rem] h-8 rounded-lg border border-ink-200 px-2 flex items-center truncate text-ink-800">' + esc(fs.map(function (f) { return f.name; }).join('، ')) + '</div>'
        + '<span class="text-[11px] text-ink-400">' + esc(anyOk ? S.allTypes : accept.map(function (a) { return { image: S.images, video: S.videos, doc: S.docs }[a] || a; }).join(' + ')) + '</span>'
        + '<button type="button" data-cmd="cancel" class="btn-ghost">' + esc(S.cancel) + '</button><button type="button" data-cmd="choose" class="btn-primary"' + (fs.length ? '' : ' disabled') + '>' + esc(S.choose) + '</button>';
    }
    function paintSel() { // تحديث ألوان التحديد من غير ما نعيد رسم العناصر (عشان الدبل كليك يفضل شغال)
      var list = st.view === 'list';
      [].forEach.call(win.querySelectorAll('.mx-items [data-k]'), function (n) { var on = st.sel.indexOf(n.dataset.k) >= 0, fo = st.focus === n.dataset.k;
        n.classList.toggle('bg-brand-100/80', on); n.classList.toggle(list ? 'hover:bg-ink-50' : 'hover:bg-ink-100/70', !on);
        if (!list) { n.classList.toggle('ring-1', on); n.classList.toggle('ring-brand-300', on); var nm = n.querySelector('.mx-nm'); if (nm) { nm.classList.toggle('text-brand-900', on); nm.classList.toggle('text-ink-800', !on); } }
        n.classList.toggle('outline', fo); n.classList.toggle('outline-1', fo); n.classList.toggle('outline-brand-400', fo);
        var tk = n.querySelector('.mx-tick'); if (tk) { tk.textContent = on ? '✓' : ''; tk.classList.toggle('bg-brand-600', on); tk.classList.toggle('border-brand-600', on); tk.classList.toggle('text-white', on); tk.classList.toggle('bg-white/90', !on); tk.classList.toggle('border-ink-300', !on); } });
    }
    function refreshSel() { paintSel(); pane(); status(); foot(); var one = selFiles(); if (one.length === 1 && st.pane) loadUses(one[0]); }

    function itemOf(k) { if (k[0] === 'd') return { dir: true, path: k.slice(1) }; var id = +k.slice(1); return { f: st.all.filter(function (f) { return f.id === id; })[0] }; }
    function openKey(k) { if (st.loc.t === 'trash') { var tf = st.all.filter(function (f) { return 'f' + f.id === k; })[0]; if (tf && (tf.kind === 'image' || tf.kind === 'video')) window.open(tf.url, '_blank'); return; } var it = itemOf(k); if (it.dir) return go({ t: 'folder', f: it.path }); if (!it.f) return; if (opt.picker) { if (okKind(it.f.kind)) { st.sel = [k]; finish(); } else toast(S.notAllowed, 'err'); return; } if (it.f.kind === 'image' || it.f.kind === 'video') lightbox(it.f.id); else window.open(it.f.url, '_blank'); }
    function renameKey(k) {
      var node = win.querySelector('[data-k="' + CSS.escape(k) + '"] .mx-nm'), it = itemOf(k); if (!node || !M.canEdit) return;
      var old = it.dir ? it.path.split('/').pop() : it.f.name, inp = document.createElement('input'); inp.value = old; inp.className = 'w-full rounded border border-brand-400 px-1 py-0.5 text-[12px] text-center bg-white text-ink-900';
      node.replaceWith(inp); inp.focus(); var dot = old.lastIndexOf('.'); inp.setSelectionRange(0, !it.dir && dot > 0 ? dot : old.length);
      var done = false, commit = function (ok) { if (done) return; done = true; var v = inp.value.trim(); if (!ok || !v || v === old) { items(); win.focus(); return; }
        if (it.dir) { var parent = it.path.indexOf('/') >= 0 ? it.path.slice(0, it.path.lastIndexOf('/')) + '/' : ''; post({ action: 'folder_rename', from: it.path, to: parent + v.replace(/\//g, '') }).then(function () { load(); win.focus(); }); }
        else post({ action: 'rename', id: it.f.id, name: v }).then(function () { load(); win.focus(); }); };
      inp.addEventListener('keydown', function (e) { e.stopPropagation(); if (e.key === 'Enter') commit(true); if (e.key === 'Escape') commit(false); }); inp.addEventListener('blur', function () { commit(true); }); inp.addEventListener('mousedown', function (e) { e.stopPropagation(); });
    }
    // توست فيه زرار تراجع
    function undoToast(msg, undo) {
      var t = document.createElement('div'); t.className = 'fixed bottom-5 start-1/2 -translate-x-1/2 rtl:translate-x-1/2 z-[96] flex items-center gap-4 rounded-xl bg-ink-900 text-[#fff] text-sm px-4 py-3 shadow-xl';
      t.innerHTML = '<span></span><button type="button" class="font-semibold text-brand-300 hover:text-[#fff]">' + esc(S.undo) + '</button>'; t.firstChild.textContent = msg;
      var gone = false, kill = function () { if (!gone) { gone = true; t.remove(); } }; t.querySelector('button').onclick = function () { kill(); undo(); }; document.body.appendChild(t); setTimeout(kill, 8000);
    }
    function del(keys, hard) {
      var files = keys.filter(function (k) { return k[0] === 'f'; }).map(function (k) { return +k.slice(1); }), dirs = keys.filter(function (k) { return k[0] === 'd'; }).map(function (k) { return k.slice(1); });
      if (!files.length && !dirs.length) return;
      if (st.loc.t === 'trash' || hard) { if (!confirm(fmt(S.purgeQ, files.length))) return; return post({ action: 'purge', 'ids[]': files }).then(function () { st.sel = []; load(); }); }
      if (dirs.length && !confirm(fmt(S.delMixed, files.length, dirs.length))) return;
      Promise.all([files.length ? post({ action: 'delete', 'ids[]': files }) : null].concat(dirs.map(function (d) { return post({ action: 'folder_delete', folder: d }); }))).then(function () {
        st.sel = []; load(); if (files.length) undoToast(fmt(S.trashed, files.length), function () { post({ action: 'restore', 'ids[]': files }).then(function () { toast(S.restored, 'ok'); load(); }); });
      });
    }
    function restore(keys) { var ids = keys.filter(function (k) { return k[0] === 'f'; }).map(function (k) { return +k.slice(1); }); if (ids.length) post({ action: 'restore', 'ids[]': ids }).then(function () { toast(S.restored, 'ok'); st.sel = []; load(); }); }
    function star(keys) { var fs = selFiles(), on = !fs.every(function (f) { return f.star; }); post({ action: 'star', on: on ? 1 : '', 'ids[]': fs.map(function (f) { return f.id; }) }).then(load); }
    function zip(keys) { var ids = keys.filter(function (k) { return k[0] === 'f'; }).map(function (k) { return k.slice(1); }); if (ids.length) location.href = M.page + '&zip=' + ids.join(','); }
    // نافذة "نقل إلى" بشجرة الفولدرات
    function moveDialog(keys) {
      var ids = keys.filter(function (k) { return k[0] === 'f'; }); if (!ids.length) return;
      var d = document.createElement('dialog'), chosen = st.loc.t === 'folder' ? st.loc.f : ''; d.className = 'w-[min(420px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl backdrop:bg-ink-900/40';
      var paths = Object.keys(st.folders).filter(Boolean).sort(function (a, b) { return a.localeCompare(b); }), all = {}; paths.forEach(function (p2) { var acc = ''; p2.split('/').forEach(function (x) { acc = acc ? acc + '/' + x : x; all[acc] = 1; }); });
      var draw2 = function () { d.querySelector('.tr').innerHTML = [''].concat(Object.keys(all).sort(function (a, b) { return a.localeCompare(b); })).map(function (f) { var lvl = f ? f.split('/').length - 1 : 0, on = f === chosen; return '<button type="button" data-to="' + esc(f) + '" class="w-full flex items-center gap-2 py-1.5 rounded-md text-start text-[13px] ' + (on ? 'bg-brand-100 text-brand-900 font-medium' : 'hover:bg-ink-50') + '" style="padding-inline:' + (8 + lvl * 16) + 'px 8px">' + (f ? '📁 ' + esc(f.split('/').pop()) : '🗂 ' + esc(S.noFolder)) + '</button>'; }).join(''); };
      d.innerHTML = '<div class="px-5 h-12 flex items-center border-b border-ink-200"><b class="flex-1 text-sm">' + esc(fmt(S.moveN, ids.length)) + '</b></div><div class="tr p-2 max-h-80 overflow-y-auto"></div><div class="flex items-center gap-2 p-3 border-t border-ink-200"><button type="button" class="nf btn-ghost text-xs">+ ' + esc(S.newFolder) + '</button><span class="flex-1"></span><button type="button" class="cn btn-ghost">' + esc(S.cancel) + '</button><button type="button" class="go btn-primary">' + esc(S.moveHere) + '</button></div>';
      document.body.appendChild(d); draw2(); d.addEventListener('close', function () { d.remove(); win.focus(); }); d.showModal();
      d.querySelector('.tr').onclick = function (e) { var b = e.target.closest('[data-to]'); if (b) { chosen = b.dataset.to; draw2(); } };
      d.querySelector('.tr').ondblclick = function (e) { if (e.target.closest('[data-to]')) d.querySelector('.go').click(); };
      d.querySelector('.nf').onclick = function () { var n = prompt(S.folderQ); if (!n || !n.trim()) return; var f = (chosen ? chosen + '/' : '') + n.trim().replace(/\//g, ''); post({ action: 'folder', name: f }).then(function (j) { if (j.ok) { all[j.folder] = 1; st.folders[j.folder] = st.folders[j.folder] || 0; chosen = j.folder; draw2(); } }); };
      d.querySelector('.cn').onclick = function () { d.close(); }; d.querySelector('.go').onclick = function () { d.close(); moveTo(ids, chosen); };
    }
    function del_old(keys) {
      var files = keys.filter(function (k) { return k[0] === 'f'; }).map(function (k) { return +k.slice(1); }), dirs = keys.filter(function (k) { return k[0] === 'd'; }).map(function (k) { return k.slice(1); });
      if (!files.length && !dirs.length) return; if (!confirm(dirs.length ? fmt(S.delMixed, files.length, dirs.length) : fmt(S.delQ, files.length))) return;
      Promise.all([files.length ? post({ action: 'delete', 'ids[]': files }) : null].concat(dirs.map(function (d) { return post({ action: 'folder_delete', folder: d }); }))).then(function () { st.sel = []; load(); });
    }
    function moveTo(keys, folder) { var ids = keys.filter(function (k) { return k[0] === 'f'; }).map(function (k) { return +k.slice(1); }); if (!ids.length) return; post({ action: 'move', folder: folder, 'ids[]': ids }).then(function () { toast(S.moved, 'ok'); load(); }); }
    function newFolder() {
      if (!['folder', 'all'].includes(st.loc.t)) go({ t: 'all', f: '' });
      var base = st.loc.t === 'folder' ? st.loc.f : '', host2 = win.querySelector('.mx-items'), names = Object.keys(subfolders(base)), nm = S.newFolderName, k = 2; while (names.indexOf(nm) >= 0) nm = S.newFolderName + ' ' + k++;
      var tile = document.createElement('div'); tile.className = st.view === 'list' ? 'flex items-center gap-2 px-3 py-1.5 bg-brand-50 rounded' : 'inline-flex flex-col items-center gap-1 rounded-lg p-1.5 bg-brand-50 align-top m-0.5'; if (st.view !== 'list') tile.style.width = Math.round((st.view === 'big' ? 160 : 112) * (st.zoom || 1)) + 'px';
      tile.innerHTML = '<span class="' + (st.view === 'list' ? 'w-5 h-4' : 'w-14 h-12') + '">' + IC.folder + '</span><input class="w-full rounded border border-brand-400 px-1 py-0.5 text-[12px] text-center bg-white text-ink-900">';
      host2.insertBefore(tile, host2.firstChild); var inp = tile.querySelector('input'); inp.value = nm; inp.focus(); inp.select();
      var done = false, commit = function (ok) { if (done) return; done = true; var v = inp.value.trim().replace(/\//g, ''); tile.remove(); if (!ok || !v) return win.focus();
        post({ action: 'folder', name: (base ? base + '/' : '') + v }).then(function (j) { if (j.ok) { st.folders[j.folder] = st.folders[j.folder] || 0; load().then(function () { st.sel = ['d' + j.folder]; refreshSel(); win.focus(); }); } }); };
      inp.addEventListener('keydown', function (e) { e.stopPropagation(); if (e.key === 'Enter') commit(true); if (e.key === 'Escape') commit(false); }); inp.addEventListener('blur', function () { commit(true); }); inp.addEventListener('mousedown', function (e) { e.stopPropagation(); });
    }
    function copyLink(f) { toast(S.preparing, 'ok'); post({ action: 'public', id: f.id }).then(function (j) { var u = j.ok && j.urls[f.id]; if (!u) return toast(j.error || S.publicErr, 'err'); (navigator.clipboard ? navigator.clipboard.writeText(u) : Promise.reject()).then(function () { toast(S.copied, 'ok'); }).catch(function () { prompt('URL', u); }); load(); }); }
    function selectAll() {
      var done2 = function () { st.sel = current().map(function (x) { return x.key; }); refreshSel(); };
      if (st.all.length >= (st.total || 0) || st.all.length >= 2000) return done2();
      fetch(qs(0).replace('&limit=' + PAGE, '&limit=500').replace('&offset=0', '&offset=' + st.all.length), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { var seen = {}; st.all.forEach(function (f) { seen[f.id] = 1; }); (j.items || []).forEach(function (f) { if (!seen[f.id]) st.all.push(f); }); items(); selectAll(); });
    }
    function upFolder() { if (st.loc.t !== 'folder') return; var f = st.loc.f; go(f.indexOf('/') >= 0 ? { t: 'folder', f: f.slice(0, f.lastIndexOf('/')) } : { t: 'all', f: '' }); }

    var queue = [], active = 0, qbox = null, batch = { ok: 0, dup: 0, keys: [] };
    function qPanel() {
      if (!qbox) { qbox = document.createElement('div'); qbox.className = 'mx-q-panel border-t border-ink-200 bg-white max-h-44 overflow-y-auto'; $('.mx-prog').appendChild(qbox); }
      return qbox;
    }
    function pump() {
      while (active < 2 && queue.length) (function (job) {
        active++; var fd = new FormData(); fd.append('_csrf', M.csrf); fd.append('action', 'upload'); fd.append('folder', job.folder); fd.append('files[]', job.file, job.file.name);
        var x = job.x = new XMLHttpRequest(); x.open('POST', M.post); x.withCredentials = true;
        x.upload.onprogress = function (e) { if (e.lengthComputable) { var pc = Math.round(e.loaded / e.total * 100); job.row.querySelector('.bar').style.width = pc + '%'; job.row.querySelector('.pc').textContent = pc + '%'; } };
        var end = function (msg, okk) { active--; job.row.querySelector('.pc').textContent = msg; job.row.querySelector('.x').remove(); job.row.classList.add(okk ? 'text-lime-700' : 'text-red-600'); setTimeout(function () { job.row.remove(); if (qbox && !qbox.children.length) { qbox.remove(); qbox = null; } }, okk ? 1500 : 6000); pump(); if (!active && !queue.length) finishBatch(); };
        x.onload = function () { var j = {}; try { j = JSON.parse(x.responseText); } catch (e) {} var it = (j.items || [])[0]; if (it) { if (it.dup) batch.dup++; else batch.ok++; if (okKind(it.kind)) batch.keys.push('f' + it.id); end(it.dup ? S.dupShort : '✓', true); } else end((j.errors || [S.publicErr])[0], false); };
        x.onerror = function () { end(S.publicErr, false); }; x.onabort = function () { end(S.canceled, false); };
        x.send(fd);
      })(queue.shift());
    }
    function finishBatch() { var newImgs = batch.keys.map(function (k) { return +k.slice(1); }); if (batch.ok && M.canAi && store.get('autoai', false)) setTimeout(function () { aiTag(st.all.filter(function (f) { return newImgs.indexOf(f.id) >= 0 && f.kind === 'image' && !f.ai; }).map(function (f) { return f.id; })); }, 900);
      if (batch.ok) toast(fmt(S.uploaded, batch.ok), 'ok'); if (batch.dup) toast(fmt(S.dups, batch.dup), 'ok'); if (batch.keys.length) st.sel = opt.picker && !opt.multiple ? batch.keys.slice(-1) : batch.keys; batch = { ok: 0, dup: 0, keys: [] }; load(); }
    function upload(files) {
      files = [].slice.call(files || []); if (!files.length || !M.canEdit) return;
      if (st.loc.t === 'trash') go({ t: 'all', f: '' });
      files.filter(function (f) { return f.size > M.max; }).forEach(function (f) { toast(fmt(S.tooBig, f.name, size(M.max)), 'err'); });
      files.filter(function (f) { return f.size <= M.max; }).forEach(function (f) {
        var row = document.createElement('div'); row.className = 'flex items-center gap-3 px-3 py-1.5 text-[11px] text-ink-700 border-b border-ink-100';
        row.innerHTML = '<span class="w-7 h-7 shrink-0 rounded bg-ink-100 overflow-hidden grid place-items-center">' + (/^image\//.test(f.type) ? '<img alt="" class="w-full h-full object-cover">' : '📄') + '</span><span class="w-40 truncate"></span><span class="flex-1 h-1.5 rounded-full bg-ink-100 overflow-hidden"><span class="bar block h-full bg-brand-500" style="width:0"></span></span><span class="pc w-24 text-end truncate">0%</span><button type="button" class="x text-ink-400 hover:text-red-600" aria-label="' + esc(S.cancel) + '">✕</button>';
        row.children[1].textContent = f.name + ' · ' + size(f.size); var im = row.querySelector('img'); if (im) { im.src = URL.createObjectURL(f); im.onload = function () { URL.revokeObjectURL(im.src); }; }
        var job = { file: f, row: row, folder: st.loc.t === 'folder' ? st.loc.f : '' }; row.querySelector('.x').onclick = function () { var i = queue.indexOf(job); if (i >= 0) { queue.splice(i, 1); row.remove(); } else if (job.x) job.x.abort(); };
        qPanel().appendChild(row); queue.push(job);
      });
      pump();
    }
    function upload_old(files) {
      files = [].slice.call(files || []); if (!files.length || !M.canEdit) return;
      files.filter(function (f) { return f.size > M.max; }).forEach(function (f) { toast(fmt(S.tooBig, f.name, size(M.max)), 'err'); });
      files = files.filter(function (f) { return f.size <= M.max; }); if (!files.length) return;
      var row = document.createElement('div'); row.className = 'flex items-center gap-3 px-3 py-1.5 border-t border-ink-200 bg-brand-50 text-[11px] text-brand-900';
      row.innerHTML = '<span>' + esc(fmt(S.uploading, files.length)) + '</span><span class="flex-1 h-1.5 rounded-full bg-brand-100 overflow-hidden"><span class="bar block h-full bg-brand-500" style="width:0"></span></span><span class="pc w-9 text-end">0%</span>'; $('.mx-prog').appendChild(row);
      var fd = new FormData(); fd.append('_csrf', M.csrf); fd.append('action', 'upload'); fd.append('folder', st.loc.t === 'folder' ? st.loc.f : ''); files.forEach(function (f) { fd.append('files[]', f, f.name); });
      var x = new XMLHttpRequest(); x.open('POST', M.post); x.withCredentials = true;
      x.upload.onprogress = function (e) { if (e.lengthComputable) { var pc = Math.round(e.loaded / e.total * 100); row.querySelector('.pc').textContent = pc + '%'; row.querySelector('.bar').style.width = pc + '%'; } };
      x.onload = function () { row.remove(); var j = {}; try { j = JSON.parse(x.responseText); } catch (e) {} (j.errors || []).forEach(function (m) { toast(m, 'err'); });
        var its = j.items || [], dups = its.filter(function (i) { return i.dup; }).length; if (dups) toast(fmt(S.dups, dups), 'ok'); if (its.length > dups) toast(fmt(S.uploaded, its.length - dups), 'ok');
        var keys = its.filter(function (i) { return okKind(i.kind); }).map(function (i) { return 'f' + i.id; }); if (keys.length) st.sel = opt.picker && !opt.multiple ? keys.slice(-1) : keys; load(); };
      x.onerror = function () { row.remove(); toast(S.publicErr, 'err'); }; x.send(fd);
    }
    $('.mx-file').addEventListener('change', function () { upload(this.files); this.value = ''; });

    // ---- الماوس: تحديد، فتح، تحديد بالسحب
    var main = $('.mx-main'), band = $('.mx-band'), anchor = null, bandStart = null;
    main.addEventListener('mousedown', function (e) {
      if (e.button !== 0 || e.target.closest('input, button, a, video, audio, th')) return; var it = e.target.closest('[data-k]');
      if (it) { var k = it.dataset.k;
        if (e.target.closest('.mx-tick')) { var ti = st.sel.indexOf(k); if (ti >= 0) st.sel.splice(ti, 1); else st.sel.push(k); anchor = k; st.focus = k; refreshSel(); return; }
        if (e.shiftKey && anchor) { var keys = current().map(function (x) { return x.key; }), a = keys.indexOf(anchor), b = keys.indexOf(k); st.sel = keys.slice(Math.min(a, b), Math.max(a, b) + 1); }
        else if (e.ctrlKey || e.metaKey) { var i = st.sel.indexOf(k); if (i >= 0) st.sel.splice(i, 1); else st.sel.push(k); anchor = k; }
        else if (st.sel.indexOf(k) < 0 || (opt.picker && !opt.multiple)) { st.sel = [k]; anchor = k; }
        st.focus = k; refreshSel(); return; }
      var r = main.getBoundingClientRect(); bandStart = { x: e.clientX - r.left + main.scrollLeft, y: e.clientY - r.top + main.scrollTop, add: e.ctrlKey || e.metaKey, base: st.sel.slice(), moved: false };
      e.preventDefault(); win.focus();
    });
    function onMove(e) {
      if (!bandStart) return; if (!main.isConnected) { bandStart = null; return; } var r = main.getBoundingClientRect(), x = e.clientX - r.left + main.scrollLeft, y = e.clientY - r.top + main.scrollTop;
      var L = Math.min(x, bandStart.x), T = Math.min(y, bandStart.y), W = Math.abs(x - bandStart.x), H = Math.abs(y - bandStart.y); if (W < 4 && H < 4) return; bandStart.moved = true;
      band.classList.remove('hidden'); band.style.cssText = 'left:' + L + 'px;top:' + T + 'px;width:' + W + 'px;height:' + H + 'px';
      var hit = []; [].forEach.call(main.querySelectorAll('[data-k]'), function (n) { var b = n.getBoundingClientRect(), nl = b.left - r.left + main.scrollLeft, nt = b.top - r.top + main.scrollTop; if (nl < L + W && nl + b.width > L && nt < T + H && nt + b.height > T) hit.push(n.dataset.k); });
      if (opt.picker && !opt.multiple) hit = hit.slice(0, 1);
      st.sel = bandStart.add ? bandStart.base.concat(hit.filter(function (k) { return bandStart.base.indexOf(k) < 0; })) : hit;
      [].forEach.call(main.querySelectorAll('[data-k]'), function (n) { n.classList.toggle('bg-brand-100/80', st.sel.indexOf(n.dataset.k) >= 0); });
    }
    function onUp() { if (!bandStart) return; var moved = bandStart.moved, add = bandStart.add; bandStart = null; band.classList.add('hidden'); if (!main.isConnected) return; if (!moved && !add) st.sel = []; refreshSel(); }
    document.addEventListener('mousemove', onMove); document.addEventListener('mouseup', onUp);
    main.addEventListener('scroll', function () { if (main.scrollTop + main.clientHeight > main.scrollHeight - 600) loadMore(); }, { passive: true });
    main.addEventListener('dblclick', function (e) { var it = e.target.closest('[data-k]'); if (it) openKey(it.dataset.k); });

    // ---- كليك يمين
    var menu;
    function closeMenu() { if (menu) { menu.remove(); menu = null; } }
    function showMenu(x, y, entries) {
      closeMenu(); menu = document.createElement('div'); menu.className = 'fixed z-[95] min-w-[13rem] rounded-xl border border-ink-200 bg-white p-1 shadow-2xl text-[13px]'; menu.setAttribute('role', 'menu');
      menu.innerHTML = entries.map(function (en) { return en === '-' ? '<div class="my-1 h-px bg-ink-100"></div>' : '<button type="button" role="menuitem" data-mi="' + en[0] + '" class="w-full flex items-center gap-2 rounded-lg px-3 py-1.5 text-start ' + (en[3] ? 'text-red-600 hover:bg-red-50' : 'text-ink-800 hover:bg-brand-50') + '"' + (en[4] ? ' disabled style="opacity:.4"' : '') + '><span class="flex-1">' + esc(en[1]) + '</span>' + (en[2] ? '<kbd class="text-[10px] text-ink-400" dir="ltr">' + esc(en[2]) + '</kbd>' : '') + '</button>'; }).join('');
      (win.closest('dialog') || document.body).appendChild(menu); var b = menu.getBoundingClientRect(); menu.style.left = Math.max(8, Math.min(x, innerWidth - b.width - 8)) + 'px'; menu.style.top = Math.max(8, Math.min(y, innerHeight - b.height - 8)) + 'px';
      menu.addEventListener('click', function (e) { var mi = e.target.closest('[data-mi]'); if (!mi || mi.disabled) return; closeMenu(); run(mi.dataset.mi); });
    }
    function run(cmd) {
      var fs = selFiles(), one = fs.length === 1 ? fs[0] : null;
      if (cmd === 'open') return st.sel[0] && openKey(st.sel[0]); if (cmd === 'rename') return st.sel[0] && renameKey(st.sel[0]); if (cmd === 'del') return del(st.sel.slice());
      if (cmd === 'link' && one) return copyLink(one); if (cmd === 'crop' && one) return cropper(one); if (cmd === 'dl' && one) { location.href = one.url + '&dl=1'; return; }
      if (cmd === 'move') return moveDialog(st.sel.slice());
      if (cmd === 'restore') return restore(st.sel.slice()); if (cmd === 'purge') return del(st.sel.slice(), true); if (cmd === 'emptytrash') { if (confirm(fmt(S.purgeQ, (st.counts || {}).trash || st.all.length))) post({ action: 'empty_trash' }).then(load); return; }
      if (cmd === 'edit' && one) return editor(one); if (cmd === 'share') return shareDialog(st.sel.length === 1 && st.sel[0][0] === 'd' ? st.sel[0].slice(1) : (st.loc.t === 'folder' ? st.loc.f : ''));
      if (cmd === 'ai') return aiTag(selFiles().filter(function (f) { return f.kind === 'image'; }).map(function (f) { return f.id; }));
      if (cmd === 'autoai') { store.set('autoai', !store.get('autoai', false)); return toast(store.get('autoai', false) ? S.autoOn : S.autoOff, 'ok'); }
      if (cmd === 'star') return star(st.sel.slice()); if (cmd === 'zip') return zip(st.sel.slice());
      if (cmd === 'cut') { st.clip = { keys: st.sel.filter(function (k) { return k[0] === 'f'; }) }; items(); status(); return toast(fmt(S.cutN, st.clip.keys.length), 'ok'); }
      if (cmd === 'paste') { if (!st.clip) return; var dest = st.loc.t === 'folder' ? st.loc.f : ''; moveTo(st.clip.keys, dest); st.clip = null; return; }
      if (cmd === 'newf') return newFolder(); if (cmd === 'upl') return $('.mx-file').click(); if (cmd === 'all') return selectAll(); if (cmd === 'ref') return load();
      if (cmd === 'choose') return finish(); if (cmd === 'cancel') return opt.onCancel && opt.onCancel();
      if (/^v-/.test(cmd)) { st.view = cmd.slice(2); store.set('view', st.view); toolbar(); items(); }
    }
    main.addEventListener('contextmenu', function (e) {
      e.preventDefault(); var it = e.target.closest('[data-k]');
      if (it) {
        if (st.sel.indexOf(it.dataset.k) < 0) { st.sel = [it.dataset.k]; refreshSel(); }
        var fs = selFiles(), one = fs.length === 1 && st.sel.length === 1 ? fs[0] : null, isDir = st.sel.length === 1 && st.sel[0][0] === 'd';
        if (st.loc.t === 'trash') return showMenu(e.clientX, e.clientY, [['restore', S.restore], '-', ['purge', S.purge, 'Del', true]]);
        showMenu(e.clientX, e.clientY, [['open', opt.picker && !isDir ? S.choose : S.open, 'Enter'], '-'].concat(one && one.kind === 'image' && M.canEdit ? [['crop', '✂ ' + S.crop]] : []).concat(one && one.kind === 'image' && M.canEdit ? [['edit', '🎨 ' + S.edit]] : []).concat(M.canAi && M.canEdit && fs.some(function (f) { return f.kind === 'image'; }) ? [['ai', '✦ ' + S.aiTag]] : []).concat(isDir && M.canEdit && !opt.picker ? [['share', '🔗 ' + S.shareT]] : []).concat(one && (one.kind === 'image' || one.kind === 'video') ? [['link', S.copyLink]] : []).concat(one ? [['dl', S.download]] : []).concat(fs.length > 1 ? [['zip', fmt(S.zipN, fs.length)]] : []).concat(fs.length && M.canEdit && !opt.picker ? [['star', fs.every(function (f) { return f.star; }) ? S.unstar : S.star]] : [])
          .concat(M.canEdit ? ['-', ['rename', S.rename, 'F2', false, st.sel.length !== 1], ['cut', S.cut, 'Ctrl+X', false, !fs.length], ['move', S.move, '', false, !fs.length], ['del', S.del, 'Del', true]] : []));
      } else showMenu(e.clientX, e.clientY, (M.canEdit && st.loc.t !== 'trash' ? [['newf', S.newFolder, 'Ctrl+Shift+N'], ['upl', S.upload]].concat(st.clip ? [['paste', fmt(S.pasteN, st.clip.keys.length), 'Ctrl+V']] : []) : []).concat(M.canAi && M.canEdit ? ['-', ['autoai', (store.get('autoai', false) ? '☑ ' : '☐ ') + S.autoAi]] : []).concat(['-', ['v-big', S.vBig], ['v-icons', S.vIcons], ['v-list', S.vList], '-', ['all', S.selAll, 'Ctrl+A'], ['ref', S.refresh, 'F5']]));
    });
    document.addEventListener('mousedown', function (e) { if (menu && !menu.contains(e.target)) closeMenu(); });

    // ---- الكيبورد
    win.addEventListener('keydown', function (e) {
      if (e.target.tagName === 'INPUT') { if (e.key === 'Escape') { e.target.blur(); win.focus(); } return; }
      var list = current(), keys = list.map(function (x) { return x.key; }), i = keys.indexOf(st.focus), ctrl = e.ctrlKey || e.metaKey;
      if (ctrl && e.key.toLowerCase() === 'a') { e.preventDefault(); return selectAll(); }
      if (ctrl && e.shiftKey && e.key.toLowerCase() === 'n' && M.canEdit) { e.preventDefault(); return newFolder(); }
      if (ctrl && e.key.toLowerCase() === 'f') { e.preventDefault(); return $('.mx-q').focus(); }
      if (ctrl && e.key.toLowerCase() === 'x' && M.canEdit && !opt.picker) { e.preventDefault(); return run('cut'); }
      if (ctrl && e.key.toLowerCase() === 'v' && st.clip && M.canEdit) { e.preventDefault(); return run('paste'); }
      if (!ctrl && !e.altKey && e.key.length === 1 && e.key !== ' ' && e.key !== '?' && /\S/.test(e.key)) { // كتابة حرف بتنقل لأول ملف بيبدأ بيه
        clearTimeout(win._ta); win._tq = (win._tq || '') + e.key.toLowerCase(); win._ta = setTimeout(function () { win._tq = ''; }, 700);
        var hit = list.filter(function (x) { return (x.dir ? x.name : x.f.name).toLowerCase().indexOf(win._tq) === 0; })[0];
        if (hit) { st.sel = [hit.key]; st.focus = hit.key; anchor = hit.key; refreshSel(); var hn = main.querySelector('[data-k="' + CSS.escape(hit.key) + '"]'); if (hn) hn.scrollIntoView({ block: 'nearest' }); } return; }
      if (e.key === 'F5') { e.preventDefault(); return load(); }
      if (e.key === '?') { e.preventDefault(); return helpDialog(); }
      if (e.key === 'Escape') { if (menu) return closeMenu(); if (st.sel.length) { e.preventDefault(); st.sel = []; return refreshSel(); } return; }
      if (e.key === 'Delete' && st.sel.length && M.canEdit) return del(st.sel.slice());
      if (e.key === 'F2' && st.sel.length === 1) { e.preventDefault(); return renameKey(st.sel[0]); }
      if (e.key === 'Enter' && st.sel.length) { e.preventDefault(); return opt.picker && selFiles().length > 1 ? finish() : openKey(st.sel[0]); }
      if (e.key === 'Backspace' || (e.altKey && e.key === 'ArrowUp')) { e.preventDefault(); return upFolder(); }
      if (e.key === ' ' && st.sel.length === 1 && st.sel[0][0] === 'f') { e.preventDefault(); return lightbox(+st.sel[0].slice(1)); }
      var mv = { ArrowDown: 1, ArrowUp: -1, ArrowRight: rtl() ? -1 : 1, ArrowLeft: rtl() ? 1 : -1 }[e.key]; if (!mv || !keys.length) return; e.preventDefault();
      var perRow = 1; if (st.view !== 'list' && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { var n = main.querySelectorAll('[data-k]'); if (n.length > 1) { var top0 = n[0].offsetTop; perRow = [].filter.call(n, function (x) { return x.offsetTop === top0; }).length; } }
      var j = Math.max(0, Math.min(keys.length - 1, i < 0 ? 0 : i + mv * perRow)); st.focus = keys[j];
      if (e.shiftKey && anchor) { var a = keys.indexOf(anchor); st.sel = keys.slice(Math.min(a, j), Math.max(a, j) + 1); } else { st.sel = [keys[j]]; anchor = keys[j]; }
      refreshSel(); var el = main.querySelector('[data-k="' + CSS.escape(keys[j]) + '"]'); if (el) el.scrollIntoView({ block: 'nearest' });
    });

    // ---- الأزرار والتنقل
    win.addEventListener('click', function (e) {
      var tg = e.target.closest('[data-tag]'); if (tg) { st.q = tg.dataset.tag; var qi = $('.mx-q'); if (qi) qi.value = st.q; return load(); }
      var g = e.target.closest('[data-go]'), gf = e.target.closest('[data-gof]'), sort = e.target.closest('[data-sort]'), cmd = e.target.closest('[data-cmd]'), c = e.target.closest('button');
      if ((g || gf) && innerWidth < 640) { var nv2 = $('.mx-nav'); if (!nv2.classList.contains('hidden') && nv2.classList.contains('absolute')) { nv2.classList.add('hidden'); nv2.classList.remove('absolute', 'z-30', 'inset-y-0', 'start-0', '!bg-white', 'shadow-2xl'); } }
      if (g) return go({ t: g.dataset.go, f: '' }); if (gf) return go({ t: 'folder', f: gf.dataset.gof });
      if (sort) { var k = sort.dataset.sort; st.sort = st.sort[0] === k ? [k, -st.sort[1]] : [k, k === 'name' || k === 'type' ? 1 : -1]; store.set('sort', st.sort); return load(); }
      if (cmd) return run(cmd.dataset.cmd);
      if (!c) return;
      if (c.classList.contains('mx-back') && st.hist.length) { st.fwd.push(st.loc); go(st.hist.pop(), true); }
      else if (c.classList.contains('mx-fwd') && st.fwd.length) { st.hist.push(st.loc); go(st.fwd.pop(), true); }
      else if (c.classList.contains('mx-up')) upFolder();
      else if (c.classList.contains('mx-nf')) newFolder();
      else if (c.classList.contains('mx-upl')) $('.mx-file').click();
      else if (c.classList.contains('mx-ref')) load();
      else if (c.classList.contains('mx-share')) run('share');
      else if (c.classList.contains('mx-ai')) { var un = current().filter(function (x) { return x.f && x.f.kind === 'image' && !x.f.ai; }).map(function (x) { return x.f.id; }); if (!un.length) toast(S.aiNone, 'ok'); else if (confirm(fmt(S.aiOrgQ, un.length))) aiTag(un); }
      else if (c.classList.contains('mx-help')) helpDialog();
      else if (c.classList.contains('mx-navt')) { var nv = $('.mx-nav'); ['hidden', 'absolute', 'z-30', 'inset-y-0', 'start-0', '!bg-white', 'shadow-2xl'].forEach(function (k) { nv.classList.toggle(k); }); }
      else if (c.classList.contains('mx-pn')) { st.pane = !st.pane; store.set('pane', st.pane); toolbar(); pane(); }
      else if (c.dataset.v) { st.view = c.dataset.v; store.set('view', st.view); toolbar(); items(); }
    });
    var qt; win.addEventListener('input', function (e) { if (e.target.classList.contains('mx-q')) { clearTimeout(qt); qt = setTimeout(function () { st.q = e.target.value.trim(); load().then(function () { var q = $('.mx-q'); q.focus(); q.setSelectionRange(q.value.length, q.value.length); }); }, 280); } });
    var setZoom = function (z) { st.zoom = Math.max(0.7, Math.min(2, Math.round(z * 100) / 100)); store.set('zoom', st.zoom); items(); var r2 = win.querySelector('.mx-zoom'); if (r2) r2.value = st.zoom; };
    win.addEventListener('input', function (e) { if (e.target.classList.contains('mx-zoom')) setZoom(+e.target.value); });
    main.addEventListener('wheel', function (e) { if ((e.ctrlKey || e.metaKey) && st.view !== 'list') { e.preventDefault(); setZoom((st.zoom || 1) * (e.deltaY < 0 ? 1.1 : 0.9)); } }, { passive: false });
    // فيديو بيشتغل وانت واقف عليه
    main.addEventListener('mouseover', function (e) { var v = e.target.closest('[data-k]'); v = v && v.querySelector('video'); if (v && v.paused) { if (!v.src && v.dataset.src) { v.src = v.dataset.src; v.preload = 'auto'; } v.muted = true; v.play().catch(function () {}); } });
    main.addEventListener('mouseout', function (e) { var v = e.target.closest('[data-k]'); v = v && v.querySelector('video'); if (v && !v.paused && !(e.relatedTarget && v.closest('[data-k]').contains(e.relatedTarget))) { v.pause(); try { v.currentTime = 0.5; } catch (er) {} } });
    win.addEventListener('change', function (e) { if (e.target.classList.contains('mx-tags')) { var f = selFiles()[0]; if (f) post({ action: 'tags', id: f.id, tags: e.target.value }).then(load); } });

    // ---- سحب وإفلات: ملفات من الكمبيوتر، أو عناصر على فولدر
    var dropOv = $('.mx-dropov'), dragDepth = 0, hasType = function (e, t) { return [].indexOf.call(e.dataTransfer.types || [], t) >= 0; };
    win.addEventListener('dragstart', function (e) { var it = e.target.closest('[data-k]'); if (!it) return; if (st.sel.indexOf(it.dataset.k) < 0) st.sel = [it.dataset.k]; e.dataTransfer.setData('text/x-media', JSON.stringify(st.sel)); e.dataTransfer.effectAllowed = 'move'; });
    win.addEventListener('dragenter', function (e) { if (hasType(e, 'Files') && M.canEdit) { dragDepth++; dropOv.classList.remove('hidden'); } });
    win.addEventListener('dragleave', function (e) { if (hasType(e, 'Files')) { dragDepth = Math.max(0, dragDepth - 1); if (!dragDepth) dropOv.classList.add('hidden'); } var t = e.target.closest('[data-drop]'); if (t) t.classList.remove('ring-2', 'ring-brand-400'); });
    win.addEventListener('dragover', function (e) { var t = e.target.closest('[data-drop]'); if (hasType(e, 'Files') || (t && hasType(e, 'text/x-media'))) { e.preventDefault(); if (t && !hasType(e, 'Files')) t.classList.add('ring-2', 'ring-brand-400'); } });
    win.addEventListener('drop', function (e) {
      e.preventDefault(); dragDepth = 0; dropOv.classList.add('hidden'); var t = e.target.closest('[data-drop]');
      if (e.dataTransfer.files && e.dataTransfer.files.length) return upload(e.dataTransfer.files);
      var raw = e.dataTransfer.getData('text/x-media'); if (raw && t) { t.classList.remove('ring-2', 'ring-brand-400'); moveTo(JSON.parse(raw), t.dataset.drop); }
    });
    win.addEventListener('paste', function (e) { var fs = (e.clipboardData || {}).files; if (fs && fs.length) upload(fs); });

    // ---- عرض كبير + قص
    function lightbox(id) {
      var vis = current().filter(function (x) { return x.f && (x.f.kind === 'image' || x.f.kind === 'video'); }).map(function (x) { return x.f; }), i = vis.map(function (x) { return x.id; }).indexOf(id); if (i < 0) return;
      var d = document.createElement('dialog'); d.className = 'w-screen h-screen max-w-none max-h-none m-0 p-0 bg-black/95 text-white backdrop:bg-black/80';
      var show = function () { var f = vis[i]; d.innerHTML = '<div class="h-full flex flex-col"><div class="flex items-center gap-3 px-4 h-12 text-sm"><b class="flex-1 truncate">' + esc(f.name) + '</b><span class="text-white/60" dir="ltr">' + (i + 1) + ' / ' + vis.length + (f.w ? ' · ' + f.w + '×' + f.h : '') + '</span><button type="button" class="x px-2 text-xl" aria-label="✕">✕</button></div><div class="flex-1 flex items-center justify-center min-h-0 relative"><button type="button" class="pv absolute start-2 text-4xl px-3 opacity-70 hover:opacity-100">' + (rtl() ? '›' : '‹') + '</button>' + (f.kind === 'image' ? '<img src="' + esc(f.url) + '" alt="" class="max-w-full max-h-full object-contain">' : '<video src="' + esc(f.url) + '" controls autoplay class="max-w-full max-h-full"></video>') + '<button type="button" class="nx absolute end-2 text-4xl px-3 opacity-70 hover:opacity-100">' + (rtl() ? '‹' : '›') + '</button></div></div>';
        d.querySelector('.x').onclick = function () { d.close(); }; d.querySelector('.pv').onclick = function () { i = (i - 1 + vis.length) % vis.length; show(); }; d.querySelector('.nx').onclick = function () { i = (i + 1) % vis.length; show(); }; };
      d.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { var nx = (e.key === 'ArrowRight') !== rtl(); i = (i + (nx ? 1 : -1) + vis.length) % vis.length; show(); } if (e.key === ' ') { e.preventDefault(); d.close(); } });
      d.addEventListener('close', function () { d.remove(); win.focus(); }); document.body.appendChild(d); show(); d.showModal();
    }
    function cropper(f) {
      var d = document.createElement('dialog'); d.className = 'w-[min(860px,96vw)] p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl backdrop:bg-ink-900/50';
      d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm">✂ ' + esc(S.crop) + ' · ' + esc(f.name) + '</b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div>'
        + '<div class="p-4 space-y-3"><div class="flex flex-wrap gap-1.5 rt">' + [['1:1', 1, S.rFeed], ['4:5', 0.8, S.rFeedTall], ['9:16', 0.5625, S.rStory], ['1.91:1', 1.91, S.rLink], ['16:9', 1.7778, S.rWide]].map(function (r, k) { return '<button type="button" data-r="' + r[1] + '" class="rounded-lg border px-3 py-1.5 text-xs ' + (k ? 'border-ink-200' : 'border-brand-500 bg-brand-50') + '"><b dir="ltr">' + r[0] + '</b> <span class="text-ink-500">' + esc(r[2]) + '</span></button>'; }).join('') + '</div>'
        + '<div class="relative bg-ink-900 rounded-xl overflow-hidden grid place-items-center" style="height:min(56vh,460px)"><canvas class="cv max-w-full max-h-full cursor-move touch-none"></canvas></div>'
        + '<div class="flex flex-wrap items-center gap-3"><label class="flex items-center gap-2 text-xs text-ink-600 flex-1 min-w-[12rem]">' + esc(S.zoom) + ' <input type="range" class="zm flex-1" min="1" max="3" step="0.01" value="1"></label><span class="info text-xs text-ink-500" dir="ltr"></span><button type="button" class="btn-primary go">' + esc(S.saveCopy) + '</button></div><p class="text-[11px] text-ink-500">' + esc(S.cropHint) + '</p></div>';
      document.body.appendChild(d); d.addEventListener('close', function () { d.remove(); }); d.querySelector('.x').onclick = function () { d.close(); }; d.showModal();
      var img = new Image(), cv = d.querySelector('.cv'), cx = cv.getContext('2d'), ratio = 1, zoom = 1, ox = 0.5, oy = 0.5, drag = null, label = '1x1';
      img.onload = function () { draw2(); }; img.src = f.url;
      function geo() { var W = img.naturalWidth, H = img.naturalHeight, cw, ch; if (W / H > ratio) { ch = H / zoom; cw = ch * ratio; } else { cw = W / zoom; ch = cw / ratio; } return { sx: Math.max(0, Math.min(W - cw, ox * W - cw / 2)), sy: Math.max(0, Math.min(H - ch, oy * H - ch / 2)), cw: cw, ch: ch, W: W, H: H }; }
      function outSize(g) { var w = Math.round(Math.min(1080 * (ratio >= 1 ? ratio : 1), g.cw)); return [w, Math.round(w / ratio)]; }
      function draw2() { var g = geo(), k = Math.min(760 / g.cw, 440 / g.ch, 1); cv.width = Math.round(g.cw * k); cv.height = Math.round(g.ch * k); cx.drawImage(img, g.sx, g.sy, g.cw, g.ch, 0, 0, cv.width, cv.height); var o = outSize(g); d.querySelector('.info').textContent = o[0] + '×' + o[1] + (Math.min(g.cw, g.ch) < 600 ? '  ⚠' : ''); }
      d.querySelector('.rt').onclick = function (e) { var b = e.target.closest('[data-r]'); if (!b) return; ratio = +b.dataset.r; label = b.querySelector('b').textContent.replace(':', 'x'); [].forEach.call(this.children, function (x) { x.className = 'rounded-lg border px-3 py-1.5 text-xs ' + (x === b ? 'border-brand-500 bg-brand-50' : 'border-ink-200'); }); draw2(); };
      d.querySelector('.zm').oninput = function () { zoom = +this.value; draw2(); };
      cv.addEventListener('pointerdown', function (e) { drag = { x: e.clientX, y: e.clientY, ox: ox, oy: oy }; cv.setPointerCapture(e.pointerId); });
      cv.addEventListener('pointermove', function (e) { if (!drag) return; var g = geo(), k = cv.width / g.cw; ox = Math.max(0, Math.min(1, drag.ox - (e.clientX - drag.x) / k / g.W)); oy = Math.max(0, Math.min(1, drag.oy - (e.clientY - drag.y) / k / g.H)); draw2(); });
      cv.addEventListener('pointerup', function () { drag = null; });
      d.querySelector('.go').onclick = function () {
        var b = this, g = geo(), o = outSize(g), c2 = document.createElement('canvas'); c2.width = o[0]; c2.height = o[1]; c2.getContext('2d').drawImage(img, g.sx, g.sy, g.cw, g.ch, 0, 0, o[0], o[1]); b.disabled = true; b.textContent = S.saving;
        c2.toBlob(function (blob) { var base = f.name.replace(/\.[^.]+$/, ''), fd = new FormData(); fd.append('_csrf', M.csrf); fd.append('action', 'derive'); fd.append('src', f.id); fd.append('name', base + '-' + label + '.jpg'); fd.append('file', blob, base + '-' + label + '.jpg');
          fetch(M.post, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) { b.disabled = false; b.textContent = S.saveCopy; return toast(j.error || S.publicErr, 'err'); } toast(S.cropped, 'ok'); d.close(); st.sel = ['f' + j.item.id]; load(); }); }, 'image/jpeg', 0.9);
      };
    }

    function aiTag(ids) {
      if (!ids.length) return; var total = ids.length, done = 0, fail = 0, row = document.createElement('div'); row.className = 'flex items-center gap-3 px-3 py-1.5 border-t border-ink-200 bg-brand-50 text-[11px] text-brand-900';
      row.innerHTML = '<span>✦ ' + esc(S.aiWorking) + '</span><span class="flex-1 h-1.5 rounded-full bg-brand-100 overflow-hidden"><span class="bar block h-full bg-brand-500" style="width:0"></span></span><span class="pc w-14 text-end">0/' + total + '</span><button type="button" class="x text-ink-400 hover:text-red-600">✕</button>'; $('.mx-prog').appendChild(row);
      var stop = false; row.querySelector('.x').onclick = function () { stop = true; };
      var next = function () {
        if (stop || !ids.length) { row.remove(); toast(fmt(S.aiDone, done) + (fail ? ' · ' + fmt(S.aiFail, fail) : ''), fail && !done ? 'err' : 'ok'); return load(); }
        var batch = ids.splice(0, 3);
        post({ action: 'ai_tag', rename: 1, 'ids[]': batch }).then(function (j) { done += (j.items || []).length; fail += (j.errors || []).length; if (j.errors && j.errors.length && !done && fail === batch.length && /اربط|connect/i.test(j.errors[0] || j.error || '')) { stop = true; toast(j.errors[0] || j.error, 'err'); }
          if (!j.ok && j.error) { stop = true; toast(j.error, 'err'); }
          (j.items || []).forEach(function (it) { var k = st.all.map(function (x) { return x.id; }).indexOf(it.id); if (k >= 0) st.all[k] = it; });
          row.querySelector('.pc').textContent = (done + fail) + '/' + total; row.querySelector('.bar').style.width = ((done + fail) / total * 100) + '%'; items(); paintSel(); pane(); next(); })
          .catch(function () { fail += batch.length; next(); });
      };
      next();
    }
    function helpDialog() {
      var d = document.createElement('dialog'); d.className = 'w-[min(520px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl backdrop:bg-ink-900/40';
      var keys = [['Enter', S.hOpen], ['Space', S.hPreview], ['F2', S.rename], ['Del', S.hDel], ['Ctrl+A', S.selAll], ['Ctrl+X / Ctrl+V', S.hCut], ['Ctrl+F', S.hSearch], ['Ctrl+Shift+N', S.newFolder], ['Backspace', S.up], ['← → ↑ ↓', S.hArrows], ['Shift + ' + S.hClick, S.hRange], ['Ctrl + ' + S.hClick, S.hToggle], ['Ctrl + ' + S.hWheel, S.zoomT], ['F5', S.refresh], ['?', S.helpT]];
      d.innerHTML = '<div class="px-5 h-12 flex items-center border-b border-ink-200"><b class="flex-1 text-sm">⌨ ' + esc(S.helpT) + '</b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div><div class="p-4 grid sm:grid-cols-2 gap-x-6 gap-y-1.5 text-[13px]">' + keys.map(function (k) { return '<div class="flex items-center justify-between gap-3"><span class="text-ink-700">' + esc(k[1]) + '</span><kbd class="shrink-0 rounded-md border border-ink-300 border-b-2 bg-ink-50 px-1.5 py-0.5 text-[11px] font-mono" dir="ltr">' + esc(k[0]) + '</kbd></div>'; }).join('') + '</div><p class="px-5 pb-4 text-[11px] text-ink-500">' + esc(S.hTips) + '</p>';
      document.body.appendChild(d); d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); win.focus(); }); d.showModal();
    }
    function shareDialog(folder) {
      var d = document.createElement('dialog'); d.className = 'w-[min(520px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl backdrop:bg-ink-900/40';
      d.innerHTML = '<div class="px-5 h-12 flex items-center border-b border-ink-200"><b class="flex-1 text-sm truncate">🔗 ' + esc(fmt(S.shareOf, folder || S.title)) + '</b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div>'
        + '<div class="p-5 space-y-4 text-sm"><p class="text-xs text-ink-600">' + esc(S.shareHint) + '</p><div class="flex flex-wrap items-end gap-3"><label class="block"><span class="text-xs text-ink-500">' + esc(S.validFor) + '</span><select class="dy input !mt-1 !py-1.5 !w-auto"><option value="1">' + esc(S.d1) + '</option><option value="7" selected>' + esc(S.d7) + '</option><option value="30">' + esc(S.d30) + '</option><option value="0">' + esc(S.d0) + '</option></select></label>'
        + '<label class="flex items-center gap-2 text-xs pb-2"><input type="checkbox" class="dl" checked> ' + esc(S.allowDl) + '</label><button type="button" class="mk btn-primary ms-auto">' + esc(S.makeLink) + '</button></div><div class="ls space-y-2"></div></div>';
      document.body.appendChild(d); d.addEventListener('close', function () { d.remove(); win.focus(); }); d.querySelector('.x').onclick = function () { d.close(); }; d.showModal();
      var list = function () { fetch(M.page + '&ajax=shares&folder=' + encodeURIComponent(folder), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        d.querySelector('.ls').innerHTML = (j.shares || []).map(function (x) { return '<div class="rounded-xl border ' + (x.expired ? 'border-ink-200 opacity-60' : 'border-brand-200 bg-brand-50/50') + ' p-3"><div class="flex items-center gap-2"><input readonly class="flex-1 min-w-0 rounded-lg border border-ink-200 bg-white px-2 py-1 text-[11px]" dir="ltr" value="' + esc(x.url) + '"><button type="button" data-cp="' + esc(x.url) + '" class="btn-ghost !py-1 text-xs">' + esc(S.copy) + '</button><button type="button" data-rv="' + x.id + '" class="text-xs text-red-600 hover:underline">' + esc(S.revoke) + '</button></div>'
          + '<p class="text-[11px] text-ink-500 mt-1.5">' + esc([x.expired ? S.expired : x.exp ? fmt(S.until, when(x.exp)) : S.d0, x.dl ? S.allowDl : S.viewOnly, fmt(S.views, x.views)].join(' · ')) + '</p></div>'; }).join('') || '<p class="text-xs text-ink-400">' + esc(S.noLinks) + '</p>'; }); };
      d.querySelector('.mk').onclick = function () { post({ action: 'share_create', folder: folder, days: d.querySelector('.dy').value, dl: d.querySelector('.dl').checked ? 1 : '' }).then(function (j) { if (!j.ok) return toast(S.publicErr, 'err'); (navigator.clipboard ? navigator.clipboard.writeText(j.url) : Promise.reject()).then(function () { toast(S.linkCopied, 'ok'); }).catch(function () {}); list(); }); };
      d.querySelector('.ls').onclick = function (e) { var cp = e.target.closest('[data-cp]'), rv = e.target.closest('[data-rv]'); if (cp) (navigator.clipboard ? navigator.clipboard.writeText(cp.dataset.cp) : Promise.reject()).then(function () { toast(S.copied, 'ok'); }).catch(function () {}); if (rv && confirm(S.revokeQ)) post({ action: 'share_revoke', sid: rv.dataset.rv }).then(list); };
      list();
    }
    // محرر الصور: تدوير/قلب/سطوع/تباين/تشبع/مقاس/جودة — بيحفظ نسخة جديدة
    function editor(f) {
      var d = document.createElement('dialog'); d.className = 'w-[min(980px,97vw)] p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl backdrop:bg-ink-900/50';
      var sl = function (k, l, mn, mx, v, st2) { return '<label class="block"><span class="flex justify-between text-[11px] text-ink-600"><span>' + esc(l) + '</span><span class="v-' + k + '" dir="ltr">' + v + '</span></span><input type="range" data-k="' + k + '" min="' + mn + '" max="' + mx + '" step="' + (st2 || 1) + '" value="' + v + '" class="w-full accent-brand-600"></label>'; };
      d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm truncate">🎨 ' + esc(S.edit) + ' · ' + esc(f.name) + '</b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div>'
        + '<div class="grid md:grid-cols-[minmax(0,1fr)_260px]"><div class="bg-ink-900 grid place-items-center p-3" style="min-height:min(60vh,480px)"><canvas class="cv max-w-full max-h-[60vh]"></canvas></div>'
        + '<div class="p-4 space-y-3 text-[13px] border-s border-ink-200"><div class="grid grid-cols-4 gap-1.5">' + [['rl', '⟲', S.rotL], ['rr', '⟳', S.rotR], ['fh', '⇋', S.flipH], ['fv', '⇅', S.flipV]].map(function (b) { return '<button type="button" data-t="' + b[0] + '" title="' + esc(b[2]) + '" class="btn-ghost !px-0 !py-1.5 text-base">' + b[1] + '</button>'; }).join('') + '</div>'
        + sl('br', S.bright, 50, 150, 100) + sl('ct', S.contrast, 50, 150, 100) + sl('sa', S.satur, 0, 200, 100)
        + '<label class="block"><span class="text-[11px] text-ink-600">' + esc(S.maxW) + '</span><select class="mw input !mt-1 !py-1.5"><option value="0">' + esc(S.orig) + '</option><option value="2048">2048</option><option value="1440">1440</option><option value="1080" selected>1080</option><option value="720">720</option></select></label>'
        + sl('q', S.quality, 50, 95, 85) + '<label class="block"><span class="text-[11px] text-ink-600">' + esc(S.format) + '</span><select class="fm input !mt-1 !py-1.5"><option value="image/jpeg">JPG</option><option value="image/webp">WebP</option><option value="image/png">PNG</option></select></label>'
        + '<p class="est text-[11px] text-ink-500" dir="auto"></p><div class="flex gap-2"><button type="button" class="rs btn-ghost flex-1">' + esc(S.reset) + '</button><button type="button" class="go btn-primary flex-1">' + esc(S.saveCopy) + '</button></div></div></div>';
      document.body.appendChild(d); d.addEventListener('close', function () { d.remove(); win.focus(); }); d.querySelector('.x').onclick = function () { d.close(); }; d.showModal();
      var img = new Image(), cv = d.querySelector('.cv'), S2 = { rot: 0, fh: 1, fv: 1, br: 100, ct: 100, sa: 100, q: 85 }, et;
      var render = function (target, maxW) { var w0 = img.naturalWidth, h0 = img.naturalHeight, sw = S2.rot % 180 ? h0 : w0, sh = S2.rot % 180 ? w0 : h0, k = maxW && sw > maxW ? maxW / sw : 1;
        target.width = Math.round(sw * k); target.height = Math.round(sh * k); var c = target.getContext('2d'); c.filter = 'brightness(' + S2.br + '%) contrast(' + S2.ct + '%) saturate(' + S2.sa + '%)';
        c.translate(target.width / 2, target.height / 2); c.rotate(S2.rot * Math.PI / 180); c.scale(S2.fh, S2.fv); var dw = (S2.rot % 180 ? target.height : target.width), dh = (S2.rot % 180 ? target.width : target.height); c.drawImage(img, -dw / 2, -dh / 2, dw, dh); };
      var out = function () { return +d.querySelector('.mw').value; };
      var redraw = function () { render(cv, 900); clearTimeout(et); et = setTimeout(function () { var c2 = document.createElement('canvas'); render(c2, out()); c2.toBlob(function (b) { if (b) d.querySelector('.est').textContent = c2.width + '×' + c2.height + ' · ' + size(b.size) + ' (' + S.wasSize + ' ' + size(f.size) + ')'; }, d.querySelector('.fm').value, S2.q / 100); }, 250); };
      img.onload = redraw; img.src = f.url;
      d.addEventListener('input', function (e) { var k = e.target.dataset.k; if (!k) return; S2[k] = +e.target.value; d.querySelector('.v-' + k).textContent = e.target.value; redraw(); });
      d.addEventListener('change', function (e) { if (e.target.matches('.mw, .fm')) redraw(); });
      d.querySelector('.grid-cols-4').onclick = function (e) { var b = e.target.closest('[data-t]'); if (!b) return; var t = b.dataset.t; if (t === 'rl') S2.rot = (S2.rot + 270) % 360; if (t === 'rr') S2.rot = (S2.rot + 90) % 360; if (t === 'fh') S2.fh *= -1; if (t === 'fv') S2.fv *= -1; redraw(); };
      d.querySelector('.rs').onclick = function () { S2 = { rot: 0, fh: 1, fv: 1, br: 100, ct: 100, sa: 100, q: 85 }; [].forEach.call(d.querySelectorAll('input[type=range]'), function (r) { r.value = S2[r.dataset.k]; d.querySelector('.v-' + r.dataset.k).textContent = r.value; }); redraw(); };
      d.querySelector('.go').onclick = function () {
        var b = this, c2 = document.createElement('canvas'), mime = d.querySelector('.fm').value, ex2 = { 'image/jpeg': 'jpg', 'image/webp': 'webp', 'image/png': 'png' }[mime]; render(c2, out()); b.disabled = true; b.textContent = S.saving;
        c2.toBlob(function (blob) { var nm = f.name.replace(/\.[^.]+$/, '') + '-' + S.editedSuffix + '.' + ex2, fd = new FormData(); fd.append('_csrf', M.csrf); fd.append('action', 'derive'); fd.append('src', f.id); fd.append('name', nm); fd.append('file', blob, nm);
          fetch(M.post, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) { b.disabled = false; b.textContent = S.saveCopy; return toast(j.error || S.publicErr, 'err'); } toast(S.edited, 'ok'); d.close(); st.sel = ['f' + j.item.id]; load(); }); }, mime, S2.q / 100);
      };
    }
    function finish() {
      var out = selFiles().filter(function (f) { return okKind(f.kind); }); if (!out.length) return; if (!opt.multiple) out = out.slice(0, 1);
      if (!opt.public) return opt.onDone && opt.onDone(out);
      var b = win.querySelector('[data-cmd=choose]'); if (b) { b.disabled = true; b.textContent = S.preparing; }
      post({ action: 'public', ctx: opt.ctx || 'link', ref: opt.ref || '', 'ids[]': out.map(function (x) { return x.id; }) }).then(function (j) { if (!j.ok) { if (b) { b.disabled = false; b.textContent = S.choose; } return toast(j.error || S.publicErr, 'err'); } out.forEach(function (x) { x.public = j.urls[x.id] || x.public; }); opt.onDone && opt.onDone(out); })
        .catch(function () { if (b) { b.disabled = false; b.textContent = S.choose; } toast(S.publicErr, 'err'); });
    }
    load(); setTimeout(function () { win.focus(); }, 50);
    return { reload: load, destroy: function () { document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp); closeMenu(); } };
  }

  window.Media = {
    page: function (el) { Explorer(el, {}); },
    pick: function (o) {
      return new Promise(function (res) {
        var d = document.createElement('dialog'), result = null, ex; d.className = 'w-[min(1180px,97vw)] p-0 rounded-2xl bg-transparent backdrop:bg-ink-900/45 overflow-visible';
        d.innerHTML = '<div class="bd"></div>'; document.body.appendChild(d);
        d.addEventListener('close', function () { if (ex) ex.destroy(); d.remove(); res(result); });
        ex = Explorer(d.querySelector('.bd'), { picker: true, ctx: o.ctx, ref: o.ref, accept: o.accept || 'any', multiple: !!o.multiple, public: o.public !== false, onDone: function (files) { result = files; d.close(); }, onCancel: function () { d.close(); } });
        d.showModal();
      });
    }
  };
})();
