/* الرفع بالجملة: ملف أو لصق من Excel أو كتابة أو ذكاء اصطناعي → جدول بيتصلح لوحده → أدوات سريعة وتحديد وتراجع →
   فحص/رفع على دفعات 500 سطر. مفيش حاجة بتتبعت لـ Zernio غير بزرار "ابدأ". الشغل بيتحفظ في المتصفح لحد ما يترفع. */
(function () {
  var X = window.BKX; if (!X) return;
  var S = X.s, CHUNK = 500, LIM = X.lim || {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function fmt(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
  function digits(s) { return String(s).replace(/[٠-٩]/g, function (c) { return c.charCodeAt(0) - 0x660; }).replace(/[۰-۹]/g, function (c) { return c.charCodeAt(0) - 0x6F0; }); }
  var loc = X.lang === 'ar' ? 'ar-EG' : 'en';

  var form = $('#bk-form'), file = $('#bk-file'), drop = $('#bk-drop'), wrap = $('#bk-grid-wrap'), grid = $('#bk-grid'), send = $('#bk-send'), resBox = $('#bk-res');
  var head = [], rows = [], ignored = {}, srvErr = {}, sel = {}, fname = 'posts.csv', dirty = false, filter = '', q = '', notes = [], undo = [];
  var lib = {}; // id → {name, desc, kind} من مكتبة الوسائط
  function libUrl(id, thumb) { return (thumb ? X.media.thumb : X.media.file).replace('__ID__', id); }
  function mediaList(v) { return String(v || '').split('|').map(function (x) { return x.trim(); }).filter(Boolean); }

  /* ---------- قراءة الملف ---------- */
  function sniff(line) {
    var c = { ',': 0, ';': 0, '\t': 0 }, inq = false;
    for (var i = 0; i < line.length; i++) { var ch = line[i]; if (ch === '"') inq = !inq; else if (!inq && ch in c) c[ch]++; }
    return c['\t'] >= c[','] && c['\t'] >= c[';'] && c['\t'] ? '\t' : (c[';'] > c[','] ? ';' : ',');
  }
  function parse(txt) {
    txt = txt.replace(/^﻿/, '');
    var sep = sniff(txt.split(/\r?\n/)[0] || '');
    var out = [], row = [], cell = '', inq = false;
    for (var i = 0; i < txt.length; i++) {
      var c = txt[i];
      if (inq) { if (c === '"') { if (txt[i + 1] === '"') { cell += '"'; i++; } else inq = false; } else cell += c; }
      else if (c === '"' && cell === '') inq = true;
      else if (c === sep) { row.push(cell); cell = ''; }
      else if (c === '\n' || c === '\r') { if (c === '\r' && txt[i + 1] === '\n') i++; row.push(cell); cell = ''; if (row.some(function (x) { return x.trim() !== ''; })) out.push(row); row = []; }
      else cell += c;
    }
    row.push(cell); if (row.some(function (x) { return x.trim() !== ''; })) out.push(row);
    return out;
  }
  function toCsv(h, rs) {
    var qv = function (v) { v = String(v == null ? '' : v); return /[",\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
    return [h].concat(rs).map(function (r) { return r.map(qv).join(','); }).join('\r\n') + '\r\n';
  }

  /* ---------- فهم الأعمدة ---------- */
  var KNOWN = ['post_content', 'platforms', 'schedule_time', 'tz', 'media_urls', 'title', 'use_queue', 'publish_now', 'is_draft'];
  var ALIAS = {
    post_content: ['post_content', 'content', 'text', 'caption', 'post', 'message', 'body', 'النص', 'نص', 'المحتوى', 'المنشور', 'نص المنشور', 'الكابشن', 'الكلام', 'البوست'],
    platforms: ['platforms', 'platform', 'channels', 'channel', 'network', 'المنصات', 'المنصة', 'منصة', 'منصات', 'القنوات', 'السوشيال'],
    schedule_time: ['schedule_time', 'schedule', 'datetime', 'date_time', 'publish_at', 'scheduled_at', 'publish_time', 'الميعاد', 'الموعد', 'ميعاد النشر', 'موعد النشر', 'وقت النشر', 'التاريخ والوقت', 'التاريخ و الوقت'],
    __date: ['date', 'day', 'التاريخ', 'اليوم', 'تاريخ النشر'],
    __time: ['time', 'hour', 'الوقت', 'الساعة', 'ساعة النشر'],
    media_urls: ['media_urls', 'media_url', 'media', 'image', 'images', 'image_url', 'photo', 'photos', 'video', 'الصورة', 'صورة', 'الصور', 'الوسائط', 'رابط الصورة', 'الفيديو', 'فيديو', 'الميديا'],
    tz: ['tz', 'timezone', 'time_zone', 'المنطقة الزمنية', 'التوقيت'],
    title: ['title', 'العنوان', 'عنوان'],
    use_queue: ['use_queue', 'queue', 'الطابور'],
    publish_now: ['publish_now', 'now', 'انشر فورا', 'فوري'],
    is_draft: ['is_draft', 'draft', 'مسودة'],
  };
  function norm(h) { return String(h).trim().toLowerCase().replace(/[ـ]/g, '').replace(/[أإآ]/g, 'ا').replace(/\s+/g, ' '); }
  var aliasMap = {};
  Object.keys(ALIAS).forEach(function (k) { ALIAS[k].forEach(function (a) { aliasMap[norm(a)] = k; }); });
  function mapHeader(h) {
    var n = norm(h);
    if (/^(custom_content|schedule_time)_[a-z]+$/.test(n)) return n;
    if (n === 'profiles' || n === 'profile' || n === 'البروفايل') return '__drop';
    return aliasMap[n] || aliasMap[n.replace(/_/g, ' ')] || null;
  }

  /* ---------- تصليح القيم ---------- */
  var PL = { 'fb': 'facebook', 'face': 'facebook', 'فيسبوك': 'facebook', 'فيس بوك': 'facebook', 'فيس': 'facebook', 'ig': 'instagram', 'insta': 'instagram', 'انستجرام': 'instagram', 'انستقرام': 'instagram', 'انستا': 'instagram', 'انستغرام': 'instagram',
    'x': 'twitter', 'تويتر': 'twitter', 'اكس': 'twitter', 'twitter/x': 'twitter', 'لينكدان': 'linkedin', 'لينكد ان': 'linkedin', 'li': 'linkedin', 'تيك توك': 'tiktok', 'تيكتوك': 'tiktok', 'tt': 'tiktok',
    'يوتيوب': 'youtube', 'yt': 'youtube', 'ثريدز': 'threads', 'بنترست': 'pinterest', 'جوجل': 'googlebusiness', 'google': 'googlebusiness', 'gmb': 'googlebusiness', 'google business': 'googlebusiness' };
  function fixPlatforms(v) {
    var raw = String(v || '').trim(); if (!raw) return raw;
    if (/^(all|الكل|كل المنصات)$/i.test(raw) && X.connected.length) return X.connected.join('|');
    return raw.split(/\s*(?:\||,|،|\+|\/| و |;)\s*/).map(function (p) { p = norm(p); return PL[p] || PL[p.replace(/\s/g, '')] || p; }).filter(Boolean).filter(function (p, i, a) { return a.indexOf(p) === i; }).join('|');
  }
  var lastNoTime = 0;
  function fixDate(v) {
    var s = digits(String(v || '')).trim(); if (!s) return s;
    var m;
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(s)) return s;
    if (/^\d{5}(\.\d+)?$/.test(s)) { var n = parseFloat(s); var d0 = new Date(Date.UTC(1899, 11, 30) + Math.round(n * 86400000)); return d0.getUTCFullYear() + '-' + pad(d0.getUTCMonth() + 1) + '-' + pad(d0.getUTCDate()) + ' ' + pad(d0.getUTCHours()) + ':' + pad(d0.getUTCMinutes()); }
    var pm = /(pm|م|مساء|مساءً)\s*$/i.test(s), am = /(am|ص|صباحا|صباحًا|صباح)\s*$/i.test(s);
    s = s.replace(/\s*(am|pm|ص|م|صباحا|صباحًا|صباح|مساء|مساءً)\s*$/i, '');
    var Y, M, D, h = null, mi = 0;
    if ((m = s.match(/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:[ T]+(\d{1,2})(?::(\d{2}))?(?::\d{2})?)?/))) { Y = +m[1]; M = +m[2]; D = +m[3]; if (m[4] != null) { h = +m[4]; mi = +(m[5] || 0); } }
    else if ((m = s.match(/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})(?:[ T]+(\d{1,2})(?::(\d{2}))?(?::\d{2})?)?/))) {
      var a = +m[1], b = +m[2]; Y = +m[3]; if (Y < 100) Y += 2000;
      if (b > 12 && a <= 12) { M = a; D = b; } else { D = a; M = b; } // اليوم الأول (زي مصر والسعودية) إلا لو مستحيل
      if (m[4] != null) { h = +m[4]; mi = +(m[5] || 0); }
    } else return v;
    if (h === null) { h = 10; mi = 0; lastNoTime++; }
    if (pm && h < 12) h += 12; if (am && h === 12) h = 0;
    if (M < 1 || M > 12 || D < 1 || D > 31 || h > 23 || mi > 59) return v;
    return Y + '-' + pad(M) + '-' + pad(D) + ' ' + pad(h) + ':' + pad(mi);
  }
  function truthy(v) { return /^(true|1|yes|y|نعم|اه|أيوه|ايوه|ايوة|✓)$/i.test(String(v || '').trim()); }

  /* ---------- تراجع + حفظ في المتصفح ---------- */
  function snap() { undo.push(JSON.stringify([head, rows, ignored, srvErr])); if (undo.length > 40) undo.shift(); $('#bk-undo').disabled = false; }
  function doUndo() { if (!undo.length) return; var s = JSON.parse(undo.pop()); head = s[0]; rows = s[1]; ignored = s[2]; srvErr = s[3]; sel = {}; dirty = true; $('#bk-undo').disabled = !undo.length; renderMap(); render(); }
  var DK = 'bk-draft-' + X.ws, saveT = null;
  function save() { clearTimeout(saveT); saveT = setTimeout(function () { try { if (!rows.length) localStorage.removeItem(DK); else localStorage.setItem(DK, JSON.stringify({ head: head, rows: rows, ignored: ignored, fname: fname, lib: lib, t: Date.now() })); } catch (e) {} }, 400); }
  function clearDraft() { try { localStorage.removeItem(DK); } catch (e) {} }
  (function () {
    var d = null; try { d = JSON.parse(localStorage.getItem(DK) || 'null'); } catch (e) {}
    if (!d || !d.rows || !d.rows.length) return;
    $('#bk-draft').classList.remove('hidden');
    $('#bk-draft-t').textContent = f(S.draft, d.rows.length, d.fname, new Date(d.t).toLocaleString(loc, { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }));
    $('#bk-draft-go').onclick = function () { head = d.head; rows = d.rows; ignored = d.ignored || {}; fname = d.fname; lib = d.lib || {}; notes = []; dirty = true; show(); };
    $('#bk-draft-x').onclick = function () { clearDraft(); $('#bk-draft').classList.add('hidden'); };
  })();

  /* ---------- تحميل ---------- */
  function load(all, name) {
    var raw = (all.shift() || []).map(function (h) { return h.trim(); });
    notes = []; ignored = {}; srvErr = {}; sel = {}; undo = []; lastNoTime = 0;
    var mapped = raw.map(mapHeader), shown = [];
    var di = mapped.indexOf('__date'), ti = mapped.indexOf('__time');
    if (di > -1 && mapped.indexOf('schedule_time') < 0) {
      all.forEach(function (r) { r[di] = (r[di] || '').trim() + (ti > -1 && (r[ti] || '').trim() ? ' ' + r[ti].trim() : ''); });
      mapped[di] = 'schedule_time'; if (ti > -1) { mapped[ti] = '__drop'; notes.push(S.merged); }
    } else if (ti > -1) mapped[ti] = null;
    var keep = [];
    head = [];
    mapped.forEach(function (k, i) {
      if (k === '__drop') { if (/profile/i.test(raw[i])) notes.push(S.dropped); return; }
      if (k && head.indexOf(k) > -1) k = null;
      if (k && k !== norm(raw[i])) shown.push(raw[i] + ' → ' + k);
      keep.push(i); head.push(k || raw[i]); if (!k) ignored[raw[i]] = true;
    });
    rows = all.map(function (r) { return keep.map(function (i) { return r[i] == null ? '' : r[i]; }); });
    if (shown.length) notes.unshift(f(S.mapped, shown.join(' · ')));
    var pi = col('platforms'), si = col('schedule_time'), nd = 0, np = 0;
    rows.forEach(function (r) {
      if (pi > -1) { var a = fixPlatforms(r[pi]); if (a !== r[pi]) { r[pi] = a; np++; } }
      if (si > -1 && r[si]) { var b = fixDate(r[si]); if (b !== r[si]) { r[si] = b; nd++; } }
      ['use_queue', 'publish_now', 'is_draft'].forEach(function (k) { var i = col(k); if (i > -1 && r[i] && truthy(r[i]) && r[i] !== 'true') r[i] = 'true'; });
    });
    if (nd) notes.push(f(S.fixdates, nd)); if (lastNoTime) notes.push(f(S.notime, lastNoTime)); if (np) notes.push(f(S.fixpl, np));
    fname = name || fname; dirty = false;
    show();
  }
  function col(n) { return head.indexOf(n); }
  function show() {
    $('#bk-start').style.display = head.length ? 'none' : '';
    wrap.style.display = head.length ? '' : 'none';
    send.style.display = head.length ? '' : 'none';
    step(head.length ? 2 : 1);
    $('#bk-fname').textContent = fname;
    $('#bk-undo').disabled = !undo.length;
    renderMap(); render();
  }
  function step(n) {
    $$('#bk-steps .bk-step').forEach(function (li) {
      var s = +li.dataset.s, on = s === n, done = s < n, dot = $('.bk-dot', li);
      li.className = 'bk-step flex items-center gap-2 ' + (on ? 'text-brand-700 font-semibold' : done ? 'text-lime-700' : 'text-ink-400');
      dot.className = 'bk-dot w-7 h-7 rounded-full grid place-items-center text-xs font-bold border ' + (on ? 'border-brand-600 bg-brand-600 text-white' : done ? 'border-lime-600 bg-lime-600 text-white' : 'border-ink-200 bg-white');
      dot.textContent = done ? '✓' : s;
      if (on) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
    });
  }
  function renderMap() {
    var box = $('#bk-map'), unk = head.filter(function (h) { return ignored[h]; });
    $('#bk-notes').innerHTML = notes.map(function (n) { return '<div class="text-xs rounded-lg bg-lime-50 border border-lime-200 text-lime-800 px-2.5 py-1.5">✓ ' + esc(n) + '</div>'; }).join('');
    if (!unk.length) { box.classList.add('hidden'); return; }
    box.classList.remove('hidden');
    var opts = '<option value="">' + esc(S.ignore) + '</option>' + KNOWN.filter(function (k) { return col(k) < 0; }).map(function (k) { return '<option value="' + k + '">' + k + '</option>'; }).join('');
    box.innerHTML = '<div class="text-xs font-semibold text-amber-900 mb-2">' + esc(S.unknown) + '</div><div class="flex flex-wrap gap-2">' +
      unk.map(function (h) { return '<label class="inline-flex items-center gap-1.5 text-xs bg-white rounded-lg border border-amber-200 px-2 py-1"><b dir="auto">' + esc(h) + '</b> → <select class="bk-mapsel input !h-7 !py-0 text-xs !w-auto" data-h="' + esc(h) + '">' + opts + '</select></label>'; }).join('') + '</div>';
  }
  $('#bk-map').addEventListener('change', function (e) {
    var s = e.target.closest('.bk-mapsel'); if (!s || !s.value) return;
    var i = head.indexOf(s.dataset.h); if (i < 0) return;
    snap(); delete ignored[s.dataset.h]; head[i] = s.value;
    if (s.value === 'platforms') rows.forEach(function (r) { r[i] = fixPlatforms(r[i]); });
    if (s.value === 'schedule_time') rows.forEach(function (r) { if (r[i]) r[i] = fixDate(r[i]); });
    dirty = true; renderMap(); render();
  });

  /* ---------- الفحص المحلي ---------- */
  var dupMap = {};
  function check(r) {
    var errs = {}, get = function (n) { var i = col(n); return i < 0 ? '' : String(r[i] || '').trim(); };
    ['post_content', 'platforms'].forEach(function (n) { if (col(n) >= 0 && !get(n)) errs[n] = S.req; });
    var pls = get('platforms').toLowerCase().split('|').map(function (x) { return x.trim(); }).filter(Boolean);
    var bad = pls.filter(function (p) { return X.platforms.indexOf(p) < 0; });
    if (bad.length) errs.platforms = f(S.plat, bad.join(', '));
    else if (X.connected.length) { var nc = pls.filter(function (p) { return X.connected.indexOf(p) < 0; }); if (nc.length) errs.platforms = f(S.noacc, nc.join(', ')); }
    var st = get('schedule_time');
    if (st) { if (!/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test(st)) errs.schedule_time = S.date; else if (new Date(st.replace(' ', 'T')) < new Date()) errs.schedule_time = S.past; }
    else if (!truthy(get('use_queue')) && !truthy(get('publish_now')) && !truthy(get('is_draft'))) errs.schedule_time = S.when;
    // حد الحروف لكل منصة (النسخة الخاصة بالمنصة لو موجودة)
    pls.some(function (p) { var lim = LIM[p], txt = get('custom_content_' + p) || get('post_content'); if (lim && Array.from(txt).length > lim) { errs[get('custom_content_' + p) ? 'custom_content_' + p : 'post_content'] = f(S.lim, p, lim); return true; } return false; });
    var mu = get('media_urls'); if (mu && mu.split('|').some(function (u) { return u.trim() && !/^(https?:\/\/|lib:\d+$)/i.test(u.trim()); })) errs.media_urls = S.url;
    return errs;
  }
  function textKey(r) { var i = col('post_content'); return i < 0 ? '' : String(r[i] || '').trim().replace(/\s+/g, ' ').toLowerCase(); }

  /* ---------- الجدول ---------- */
  function visible() {
    var out = [];
    rows.forEach(function (r, ri) {
      var isBad = Object.keys(check(r)).length > 0 || !!srvErr[ri];
      if (filter === 'bad' && !isBad) return; if (filter === 'srv' && !srvErr[ri]) return;
      if (q && String(r[col('post_content')] || '').toLowerCase().indexOf(q) < 0) return;
      out.push(ri);
    });
    return out;
  }
  function render() {
    if (!head.length) return;
    dupMap = {}; rows.forEach(function (r) { var k = textKey(r); if (k) dupMap[k] = (dupMap[k] || 0) + 1; });
    var missing = ['post_content', 'platforms'].filter(function (n) { return col(n) < 0; });
    var okN = 0, badN = 0, srvN = 0, dupN = 0, body = '', vis = visible(), visSet = {};
    vis.forEach(function (i) { visSet[i] = 1; });
    rows.forEach(function (r, ri) {
      var e = check(r), se = srvErr[ri], isBad = Object.keys(e).length > 0 || !!se, dup = dupMap[textKey(r)] > 1;
      if (isBad) badN++; else okN++; if (se) srvN++; if (dup) dupN++;
    });
    vis.slice(0, 600).forEach(function (ri) {
      var r = rows[ri], e = check(r), se = srvErr[ri], isBad = Object.keys(e).length > 0 || !!se, dup = dupMap[textKey(r)] > 1;
      body += '<tr class="border-t border-ink-100 ' + (sel[ri] ? 'bg-brand-50/70' : isBad ? 'bg-red-50/40' : '') + '" data-r="' + ri + '">'
        + '<td class="px-1.5 py-1 align-top sticky left-0 z-[1] border-e border-ink-100 ' + (sel[ri] ? 'bg-brand-50' : isBad ? 'bg-red-50' : 'bg-white') + '"><label class="flex items-center gap-1 text-ink-400"><input type="checkbox" class="bk-sel" ' + (sel[ri] ? 'checked' : '') + ' aria-label="' + (ri + 1) + '">' + (ri + 1) + '</label></td>'
        + head.map(function (h, ci) {
          var er = e[h], ig = ignored[h], warn = h === 'post_content' && dup && !er;
          var extra = '';
          if (h === 'media_urls') {
            var ms = mediaList(r[ci]);
            extra = '<div class="flex flex-wrap items-center gap-1 px-1 pb-1">' + ms.slice(0, 4).map(function (u) {
              var m = /^lib:(\d+)$/.exec(u), src = m ? libUrl(m[1], true) : (/^https?:\/\//i.test(u) && !/\.(mp4|mov|webm)(\?|$)/i.test(u) ? u : '');
              return src ? '<img src="' + esc(src) + '" alt="" loading="lazy" class="w-9 h-9 rounded object-cover bg-ink-100 border border-ink-200" onerror="this.replaceWith(Object.assign(document.createElement(\'span\'),{className:\'w-9 h-9 rounded bg-ink-100 grid place-items-center text-[10px]\',textContent:\'🎬\'}))">' : '<span class="w-9 h-9 rounded bg-ink-100 grid place-items-center text-[10px]">🎬</span>';
            }).join('') + (ms.length > 4 ? '<span class="text-[10px] text-ink-500">+' + (ms.length - 4) + '</span>' : '') +
              '<button type="button" data-lib="' + ri + '" class="h-9 px-2 rounded border border-dashed border-sky-300 text-sky-700 text-[11px] hover:bg-sky-50" title="' + esc(S.pickrow) + '">🖼 +</button></div>';
          }
          return '<td class="px-1 py-0.5 align-top ' + (ig ? 'opacity-40' : '') + '"><div contenteditable="true" dir="auto" data-c="' + ci + '" class="min-w-[6rem] max-w-[18rem] max-h-20 overflow-auto rounded px-1.5 py-1 outline-none focus:ring-2 focus:ring-brand-300 ' + (h === 'media_urls' && r[ci] ? 'text-[10px] text-ink-400 ' : '') + (er ? 'ring-1 ring-red-300 bg-red-50' : warn ? 'ring-1 ring-amber-300 bg-amber-50' : 'hover:bg-ink-50') + '" title="' + esc(er || (warn ? S.dup : '')) + '">' + esc(r[ci]) + '</div>' + extra + (er ? '<div class="text-[10px] text-red-600 px-1" dir="auto">' + esc(er) + '</div>' : warn ? '<div class="text-[10px] text-amber-700 px-1" dir="auto">' + esc(S.dup) + '</div>' : '') + '</td>';
        }).join('')
        + '<td class="px-1 py-1 align-top text-[10px] text-red-700 ' + (se ? 'min-w-[12rem] max-w-[16rem]' : '') + '" dir="auto">' + (se ? esc(f(S.srv, se.join(' · '))) : '') + '</td>'
        + '<td class="px-1 align-top whitespace-nowrap"><button type="button" data-pv="' + ri + '" class="w-6 h-6 rounded text-ink-400 hover:bg-brand-50 hover:text-brand-700" title="' + esc(S.pv) + '" aria-label="' + esc(S.pv) + '">👁</button><button type="button" data-del="' + ri + '" class="w-6 h-6 rounded text-ink-400 hover:bg-red-50 hover:text-red-600" title="' + esc(S.del) + '" aria-label="' + esc(S.del) + '">×</button></td></tr>';
    });
    var allSel = vis.length && vis.every(function (i) { return sel[i]; });
    grid.innerHTML = '<thead class="sticky top-0 bg-ink-50 z-[1]"><tr><th class="px-1.5 py-1.5 text-start text-ink-400 sticky left-0 z-[2] bg-ink-50"><input type="checkbox" id="bk-selall" ' + (allSel ? 'checked' : '') + ' aria-label="all"></th>' + head.map(function (h) { return '<th class="px-2 py-1.5 text-start font-mono text-[11px] whitespace-nowrap ' + (ignored[h] ? 'text-ink-400 line-through' : 'text-ink-600') + '">' + esc(h) + '</th>'; }).join('') + '<th></th><th></th></tr></thead><tbody>' + body + '</tbody>';
    $('#bk-sum').innerHTML = '<span class="text-ink-700">' + esc(f(S.rows, rows.length)) + '</span> · <span class="text-lime-700">' + esc(f(S.ok, okN)) + '</span>' + (badN ? ' · <span class="text-red-600 font-medium">' + esc(f(S.bad, badN)) + '</span>' : '')
      + (dupN ? ' · <span class="text-amber-700">' + esc(f(S.dups, dupN)) + '</span>' : '')
      + (missing.length ? ' · <span class="text-red-600 font-medium">' + esc(f(S.need, missing.join(', '))) + '</span>' : '') + (rows.length > 5000 ? ' · <span class="text-red-600">' + esc(S.max) + '</span>' : '') + (dirty ? ' <span class="text-amber-700">' + esc(S.edited) + '</span>' : '');
    $('#bk-filter [data-f=srv]').classList.toggle('hidden', !srvN);
    var batches = Math.max(1, Math.ceil(rows.length / CHUNK));
    $('#bk-go-hint').textContent = rows.length ? f(mode() === 'create' ? S.goCreate : S.goCheck, rows.length, batches) : S.empty;
    $('#bk-go').disabled = !rows.length || missing.length > 0 || rows.length > 5000;
    selBar(); days(); save();
  }
  function selBar() {
    var n = Object.keys(sel).length;
    $('#bk-selbar').classList.toggle('hidden', !n);
    $('#bk-seln').textContent = f(S.sel, n); $('#bk-selc').textContent = n;
  }
  function days() {
    var si = col('schedule_time'), box = $('#bk-days'), per = {}, qn = 0, nn = 0, dn = 0;
    rows.forEach(function (r) {
      if (truthy(r[col('use_queue')])) qn++; else if (truthy(r[col('publish_now')])) nn++; else if (truthy(r[col('is_draft')])) dn++;
      var v = si > -1 ? String(r[si] || '') : ''; if (/^\d{4}-\d{2}-\d{2}/.test(v)) { var k = v.slice(0, 10); per[k] = (per[k] || 0) + 1; }
    });
    var keys = Object.keys(per).sort();
    if (!keys.length && !qn && !nn && !dn) { box.classList.add('hidden'); return; }
    box.classList.remove('hidden');
    var max = Math.max.apply(null, keys.map(function (k) { return per[k]; }).concat([1]));
    var bars = keys.slice(0, 60).map(function (k) {
      var d = new Date(k + 'T00:00'), n = per[k], hgt = Math.max(8, Math.round(n / max * 48));
      return '<div class="flex flex-col items-center gap-0.5 shrink-0 w-7" title="' + esc(k + ': ' + n) + '"><span class="text-[9px] text-ink-500">' + n + '</span><div class="w-4 rounded-t ' + (n > 6 ? 'bg-amber-500' : 'bg-brand-500') + '" style="height:' + hgt + 'px"></div><span class="text-[9px] text-ink-400 leading-none">' + d.getDate() + '</span><span class="text-[8px] text-ink-400 leading-none">' + esc(d.toLocaleDateString(loc, { month: 'short' })) + '</span></div>';
    }).join('');
    var extra = [qn ? f(S.queue, qn) : '', nn ? f(S.now, nn) : '', dn ? f(S.draft, dn) : ''].filter(Boolean).join(' · ');
    box.innerHTML = '<div class="rounded-xl border border-ink-200 p-3"><div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-ink-700 mb-2">📅 ' + esc(S.days) + (extra ? '<span class="font-normal text-ink-500 ms-auto">' + esc(extra) + '</span>' : '') + '</div>' +
      (bars ? '<div class="flex items-end gap-1 overflow-x-auto pb-1" dir="ltr">' + bars + '</div>' : '') + '</div>';
  }
  function mode() { var m = $('input[name=mode]:checked', form); return m ? m.value : 'check'; }

  /* ---------- مصادر الإدخال ---------- */
  function readFile(fl) { var rd = new FileReader(); rd.onload = function () { load(parse(String(rd.result)), fl.name); }; rd.readAsText(fl, 'utf-8'); }
  file.addEventListener('change', function () { if (file.files[0]) readFile(file.files[0]); });
  ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('border-brand-500', 'bg-brand-50'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('border-brand-500', 'bg-brand-50'); }); });
  drop.addEventListener('drop', function (e) { var fl = e.dataTransfer.files[0]; if (fl) readFile(fl); });
  $('#bk-paste-btn').onclick = function () { $('#bk-paste').classList.remove('hidden'); $('#bk-paste-txt').focus(); };
  $('#bk-paste-x').onclick = function () { $('#bk-paste').classList.add('hidden'); };
  $('#bk-paste-go').onclick = function () { var t = $('#bk-paste-txt').value; if (!t.trim()) return; load(parse(t), 'pasted.csv'); dirty = true; render(); };
  document.addEventListener('paste', function (e) {
    if (head.length || e.target.closest('input,textarea,[contenteditable]')) return;
    var t = (e.clipboardData || window.clipboardData).getData('text'); if (!t || t.indexOf('\n') < 0) return;
    e.preventDefault(); load(parse(t), 'pasted.csv'); dirty = true; render();
  });
  function blank() {
    var d = new Date(); d.setDate(d.getDate() + 1); d.setHours(10, 0, 0, 0);
    head = ['post_content', 'platforms', 'schedule_time', 'media_urls']; ignored = {}; srvErr = {}; sel = {}; notes = []; undo = [];
    rows = []; fname = 'posts.csv'; dirty = true;
    return d;
  }
  $('#bk-new').onclick = function () {
    var d = blank(); rows = [['', X.connected.length ? X.connected.slice(0, 2).join('|') : 'facebook', fmt(d), '']];
    show(); var c = $('[contenteditable]', grid); if (c) c.focus();
  };
  $('#bk-reset').onclick = function () { if (dirty && !confirm(S.reset)) return; head = []; rows = []; srvErr = {}; sel = {}; undo = []; file.value = ''; resBox.innerHTML = ''; clearDraft(); $('#bk-draft').classList.add('hidden'); show(); };
  $('#bk-add').onclick = function () { snap(); var last = rows[rows.length - 1] || []; rows.push(head.map(function (h, i) { return h === 'post_content' || h === 'media_urls' || /^custom_content_/.test(h) ? '' : (last[i] || ''); })); dirty = true; render(); var t = grid.querySelector('tbody tr:last-child [contenteditable]'); if (t) t.focus(); };
  $('#bk-dl').onclick = function () { var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + toCsv(head, rows)], { type: 'text/csv' })); a.download = fname.replace(/\.(csv|txt|tsv)$/i, '') + '-edited.csv'; a.click(); };
  $('#bk-undo').onclick = doUndo;
  document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey && head.length && !e.target.closest('input,textarea,[contenteditable]')) { e.preventDefault(); doUndo(); } });
  $$('#bk-filter .chip').forEach(function (b) { b.onclick = function () { $$('#bk-filter .chip').forEach(function (x) { x.classList.toggle('on', x === b); }); filter = b.dataset.f; render(); }; });
  $('#bk-q').addEventListener('input', function () { q = this.value.trim().toLowerCase(); render(); });
  $$('input[name=mode]', form).forEach(function (r) { r.addEventListener('change', render); });
  var editSnap = false;
  grid.addEventListener('focusin', function (e) { if (e.target.closest('[contenteditable]')) editSnap = false; });
  grid.addEventListener('input', function (e) { var c = e.target.closest('[contenteditable]'); if (!c) return; if (!editSnap) { snap(); editSnap = true; } var r = +c.closest('tr').dataset.r; rows[r][+c.dataset.c] = c.innerText.replace(/\n$/, ''); delete srvErr[r]; dirty = true; save(); });
  grid.addEventListener('focusout', function (e) {
    var c = e.target.closest('[contenteditable]'); if (!c) return;
    var r = +c.closest('tr').dataset.r, ci = +c.dataset.c;
    if (head[ci] === 'schedule_time' && rows[r][ci]) rows[r][ci] = fixDate(rows[r][ci]);
    if (head[ci] === 'platforms') rows[r][ci] = fixPlatforms(rows[r][ci]);
    setTimeout(function () { if (!grid.contains(document.activeElement)) render(); }, 0);
  });
  function removeRows(set) {
    var nr = [], ns = {}, map = {};
    rows.forEach(function (r, i) { if (!set[i]) { map[i] = nr.length; nr.push(r); } });
    Object.keys(srvErr).forEach(function (i) { if (map[i] != null) ns[map[i]] = srvErr[i]; });
    rows = nr; srvErr = ns; sel = {};
    return map;
  }
  grid.addEventListener('click', function (e) {
    var b = e.target.closest('[data-del]'); if (b) { snap(); var o = {}; o[+b.dataset.del] = 1; removeRows(o); dirty = true; render(); return; }
    var p = e.target.closest('[data-pv]'); if (p) { preview(+p.dataset.pv); return; }
    var lb = e.target.closest('[data-lib]'); if (lb) { openLib(+lb.dataset.lib); return; }
  });
  grid.addEventListener('change', function (e) {
    if (e.target.id === 'bk-selall') { var on = e.target.checked; visible().forEach(function (i) { if (on) sel[i] = 1; else delete sel[i]; }); render(); return; }
    var c = e.target.closest('.bk-sel'); if (!c) return;
    var ri = +c.closest('tr').dataset.r; if (c.checked) sel[ri] = 1; else delete sel[ri];
    var td = c.closest('td'); td.classList.toggle('bg-brand-50', c.checked); td.classList.toggle('bg-white', !c.checked); c.closest('tr').classList.toggle('bg-brand-50/70', c.checked); selBar();
  });
  $('#bk-sel-x').onclick = function () { sel = {}; render(); };
  $('#bk-sel-del').onclick = function () { snap(); removeRows(sel); dirty = true; render(); };
  $('#bk-sel-dup').onclick = function () { snap(); var add = Object.keys(sel).map(Number).sort(function (a, b) { return a - b; }).map(function (i) { return rows[i].slice(); }); rows = rows.concat(add); sel = {}; dirty = true; render(); };
  $$('.bk-copy').forEach(function (b) { b.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(b.dataset.v); var sp = b.querySelector('span') || b, t = sp.textContent; sp.textContent = S.copied; setTimeout(function () { sp.textContent = t; }, 900); }); });

  /* ---------- الأدوات السريعة ---------- */
  function ensure(k) { var i = col(k); if (i > -1) return i; head.push(k); rows.forEach(function (r) { r.push(''); }); return head.length - 1; }
  function flash(btn, msg) { var o = btn.textContent; btn.textContent = msg; setTimeout(function () { btn.textContent = o; }, 1600); }
  function scope() { var s = $('input[name=bk-scope]:checked'); return s ? s.value : 'all'; }
  function targets(ci) {
    var sc = scope(), out = [];
    if (sc === 'sel' && !Object.keys(sel).length) { alert(S.nosel); return null; }
    rows.forEach(function (r, i) { if (sc === 'sel' && !sel[i]) return; if (sc === 'empty' && String(r[ci] || '').trim()) return; out.push(i); });
    return out;
  }
  $('#bk-pl-apply').onclick = function () {
    var v = $$('#bk-pl-pick input:checked').map(function (x) { return x.value; }); if (!v.length) return alert(S.plpick);
    snap(); var i = ensure('platforms'), t = targets(i); if (!t) return undo.pop();
    t.forEach(function (ri) { rows[ri][i] = v.join('|'); });
    dirty = true; render(); flash(this, f(S.applied, t.length));
  };
  var spStart = $('#bk-sp-start'), spMode = 'gap';
  (function () { var d = new Date(); d.setDate(d.getDate() + 1); d.setHours(10, 0, 0, 0); spStart.value = fmt(d).replace(' ', 'T'); })();
  var bestTxt = (X.best || []).map(function (b) { var d = new Date(2024, 0, 7 + b.dow); return d.toLocaleDateString(loc, { weekday: 'short' }) + ' ' + pad(b.hour) + ':00'; }).join(X.rtl ? '، ' : ', ');
  $$('.bk-spm').forEach(function (b) {
    b.onclick = function () {
      if (b.disabled) return; spMode = b.dataset.m;
      $$('.bk-spm').forEach(function (x) { var on = x === b; x.classList.toggle('bg-ink-900', on); x.classList.toggle('text-white', on); x.classList.toggle('text-ink-600', !on); });
      $$('.bk-gap').forEach(function (x) { x.classList.toggle('hidden', spMode === 'best'); });
      $('#bk-best-txt').classList.toggle('hidden', spMode !== 'best'); $('#bk-best-txt').textContent = f(S.bestt, bestTxt);
    };
  });
  function slotsFrom(start, count) {
    var allowed = $$('#bk-sp-days input:checked').map(function (x) { return +x.value; }), out = [], t = new Date(start);
    if (!allowed.length) return out;
    if (spMode === 'best') {
      var best = (X.best || []).slice().sort(function (a, b) { return a.hour - b.hour; });
      for (var d = 0; d < 730 && out.length < count; d++) {
        var day = new Date(start); day.setHours(0, 0, 0, 0); day.setDate(day.getDate() + d);
        if (allowed.indexOf(day.getDay()) < 0) continue;
        best.forEach(function (b) { if (b.dow !== day.getDay() || out.length >= count) return; var c = new Date(day); c.setHours(b.hour, 0, 0, 0); if (c >= start) out.push(c); });
      }
      return out;
    }
    var n = Math.max(1, +$('#bk-sp-n').value || 1), unit = $('#bk-sp-u').value, from = +$('#bk-sp-from').value, to = +$('#bk-sp-to').value;
    if (to <= from) to = 24;
    var ok = function (x) { return allowed.indexOf(x.getDay()) > -1 && x.getHours() >= from && x.getHours() < to; };
    var fit = function (x) { for (var g = 0; g < 800 && !ok(x); g++) { if (x.getHours() < from) x.setHours(from, 0, 0, 0); else { x.setDate(x.getDate() + 1); x.setHours(from, 0, 0, 0); } } return x; };
    while (out.length < count) { fit(t); out.push(new Date(t)); if (unit === 'h') t.setHours(t.getHours() + n); else t.setDate(t.getDate() + n); }
    return out;
  }
  $('#bk-sp-apply').onclick = function () {
    if (!spStart.value) return alert(S.spstart);
    snap(); var i = ensure('schedule_time'), t = targets(i); if (!t) return undo.pop();
    t = t.filter(function (ri) { var r = rows[ri]; return !(truthy(r[col('use_queue')]) || truthy(r[col('publish_now')]) || truthy(r[col('is_draft')])); });
    var sl = slotsFrom(new Date(spStart.value), t.length);
    if (sl.length < t.length) { undo.pop(); return alert(f(S.nofit, t.length)); }
    t.forEach(function (ri, k) { rows[ri][i] = fmt(sl[k]); });
    dirty = true; render(); flash(this, f(S.applied, t.length));
  };

  /* ---------- الذكاء الاصطناعي ---------- */
  var ai = $('#bk-ai');
  function openM(m) { m.classList.remove('hidden'); m.setAttribute('aria-hidden', 'false'); }
  function closeM(m) { m.classList.add('hidden'); m.setAttribute('aria-hidden', 'true'); }
  $$('#bk-ai [data-close], #bk-pv [data-close], #bk-lib [data-close], #bk-cap [data-close]').forEach(function (x) { x.onclick = function () { closeM(x.closest('.fixed')); }; });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') [ai, $('#bk-pv'), $('#bk-lib'), $('#bk-cap')].forEach(function (m) { if (m && !m.classList.contains('hidden')) closeM(m); }); });
  function openAi() { $('#bk-ai-msg').textContent = ''; openM(ai); $('#bk-ai-topic').focus(); }
  if ($('#bk-ai-btn')) $('#bk-ai-btn').onclick = openAi;
  if ($('#bk-ai-add')) $('#bk-ai-add').onclick = openAi;
  if ($('#bk-ai-go')) $('#bk-ai-go').onclick = function () {
    var topic = $('#bk-ai-topic').value.trim(), msg = $('#bk-ai-msg'), btn = this;
    if (!topic) { msg.className = 'text-xs text-red-600'; msg.textContent = S.aitopic; return; }
    btn.disabled = true; msg.className = 'text-xs text-violet-700 animate-pulse'; msg.textContent = S.aiwait;
    var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('ajax', 'gen'); fd.append('topic', topic);
    fd.append('count', ($('input[name=bk-ai-n]:checked') || {}).value || 10); fd.append('tone', $('#bk-ai-tone').value); fd.append('lang', $('#bk-ai-lang').value);
    if ($('#bk-ai-emoji').checked) fd.append('emoji', '1'); if ($('#bk-ai-tags').checked) fd.append('tags', '1');
    fetch(X.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      btn.disabled = false;
      if (!j.ok) { msg.className = 'text-xs text-red-600'; msg.textContent = j.error; return; }
      var start = null;
      if (!head.length) { start = blank(); fname = 'ai-posts.csv'; } else snap();
      var ci = ensure('post_content'), pi = ensure('platforms'), plv = $$('#bk-pl-pick input:checked').map(function (x) { return x.value; }).join('|') || X.connected.slice(0, 2).join('|') || 'facebook';
      var base = rows.length;
      j.posts.forEach(function (p) { var r = head.map(function () { return ''; }); r[ci] = p; r[pi] = plv; rows.push(r); });
      sel = {}; for (var k = base; k < rows.length; k++) sel[k] = 1; // المنشورات الجديدة متحددة عشان توزّع مواعيدها على طول
      var sc = $('input[name=bk-scope][value=sel]'); if (sc) sc.checked = true;
      notes = notes.concat([f(S.aidone, j.posts.length)]); dirty = true;
      closeM(ai); show(); $('#bk-tools').open = true; $('#bk-tools').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }).catch(function () { btn.disabled = false; msg.className = 'text-xs text-red-600'; msg.textContent = 'error'; });
  };

  /* ---------- مكتبة الوسائط ---------- */
  var L = { row: null, kind: 'image', folder: '', q: '', off: 0, items: [], pick: [], last: -1, foldersLoaded: false };
  var libBox = $('#bk-lib'), libGrid = $('#bk-lib-grid');
  function openLib(row) {
    L.row = row == null ? null : row; L.pick = [];
    if (row != null) { mediaList(rows[row][col('media_urls')]).forEach(function (u) { var m = /^lib:(\d+)$/.exec(u); if (m) L.pick.push(+m[1]); }); }
    $$('#bk-lib-acts [data-a]').forEach(function (b) { var a = b.dataset.a; b.classList.toggle('hidden', row != null ? a !== 'row' : (a === 'row' || (!head.length && a !== 'new'))); });
    openM(libBox); loadLib(true);
  }
  function loadLib(reset) {
    if (reset) { L.off = 0; L.items = []; libGrid.innerHTML = '<div class="col-span-full text-center text-xs text-ink-400 py-10 animate-pulse">…</div>'; }
    var qs = L.folder !== '' ? '&loc=folder&f=' + encodeURIComponent(L.folder) : '&loc=' + L.kind;
    fetch(X.media.list + qs + '&q=' + encodeURIComponent(L.q) + '&offset=' + L.off + '&limit=80', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) return;
      if (j.folders && !L.foldersLoaded) { L.foldersLoaded = true; var fs = $('#bk-lib-folder'); Object.keys(j.folders).forEach(function (f) { if (f === '') return; var o = document.createElement('option'); o.value = f; o.textContent = f + ' (' + j.folders[f] + ')'; fs.appendChild(o); }); }
      var add = (j.items || []).filter(function (it) { return L.folder === '' || it.kind === L.kind; });
      L.items = L.items.concat(add); L.off += (j.items || []).length;
      L.items.forEach(function (it) { lib[it.id] = { name: it.name, desc: it.desc || '', kind: it.kind }; });
      drawLib(); $('#bk-lib-more').classList.toggle('hidden', L.off >= (j.total || 0));
    });
  }
  function drawLib() {
    $('#bk-lib-empty').classList.toggle('hidden', L.items.length > 0);
    libGrid.innerHTML = L.items.map(function (it, i) {
      var k = L.pick.indexOf(it.id), on = k > -1;
      return '<button type="button" class="bk-li relative aspect-square rounded-xl overflow-hidden border-2 ' + (on ? 'border-sky-500 ring-2 ring-sky-200' : 'border-transparent hover:border-ink-300') + ' bg-ink-100" data-i="' + i + '" title="' + esc(it.name + (it.desc ? ' — ' + it.desc : '')) + '" aria-pressed="' + on + '">' +
        (it.kind === 'image' ? '<img src="' + esc(it.thumb) + '" alt="" loading="lazy" class="w-full h-full object-cover">' : '<span class="absolute inset-0 grid place-items-center text-2xl">🎬</span>') +
        (on ? '<span class="absolute top-1 start-1 w-6 h-6 rounded-full bg-sky-600 text-white text-[11px] font-bold grid place-items-center">' + (k + 1) + '</span>' : '') +
        '<span class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/60 to-transparent text-white text-[10px] px-1.5 py-1 truncate text-start">' + esc(it.name) + '</span></button>';
    }).join('');
    $('#bk-lib-n').textContent = L.pick.length;
  }
  libGrid.addEventListener('click', function (e) {
    var b = e.target.closest('.bk-li'); if (!b) return;
    var i = +b.dataset.i, id = L.items[i].id;
    if (e.shiftKey && L.last > -1) { var a = Math.min(L.last, i), z = Math.max(L.last, i); for (var k = a; k <= z; k++) if (L.pick.indexOf(L.items[k].id) < 0) L.pick.push(L.items[k].id); }
    else { var p = L.pick.indexOf(id); if (p > -1) L.pick.splice(p, 1); else L.pick.push(id); }
    L.last = i; drawLib();
  });
  $$('#bk-lib-kind button').forEach(function (b) { b.onclick = function () { L.kind = b.dataset.k; $$('#bk-lib-kind button').forEach(function (x) { var on = x === b; x.classList.toggle('bg-ink-900', on); x.classList.toggle('text-white', on); x.classList.toggle('text-ink-600', !on); }); loadLib(true); }; });
  $('#bk-lib-folder').onchange = function () { L.folder = this.value; loadLib(true); };
  var lqT; $('#bk-lib-q').addEventListener('input', function () { var v = this.value.trim(); clearTimeout(lqT); lqT = setTimeout(function () { L.q = v; loadLib(true); }, 300); });
  $('#bk-lib-more button').onclick = function () { loadLib(false); };
  $('#bk-lib-all').onclick = function () { var all = L.items.every(function (it) { return L.pick.indexOf(it.id) > -1; }); L.pick = all ? [] : L.items.map(function (it) { return it.id; }); drawLib(); };
  function upload(files) {
    files = Array.prototype.slice.call(files || []).filter(function (f) { return /^(image|video)\//.test(f.type); }); if (!files.length || !X.media.canUp) return;
    var msg = $('#bk-lib-msg'), done = 0, newIds = [], i = 0;
    msg.textContent = f(S.libup, files.length);
    (function nextUp() {
      if (i >= files.length) { msg.textContent = f(S.libdone, done); L.pick = L.pick.concat(newIds.filter(function (x) { return L.pick.indexOf(x) < 0; })); loadLib(true); return; }
      var part = files.slice(i, i + 5); i += 5; // 5 في المرة
      var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('action', 'upload'); fd.append('folder', L.folder || '');
      part.forEach(function (fl) { fd.append('files[]', fl); });
      fetch(X.media.post, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        (j.items || []).forEach(function (it) { newIds.push(it.id); lib[it.id] = { name: it.name, desc: it.desc || '', kind: it.kind }; done++; });
        if (j.errors && j.errors.length) msg.textContent = j.errors.join(' · ');
        msg.textContent = f(S.libup, files.length - done) ; nextUp();
      }).catch(nextUp);
    })();
  }
  if ($('#bk-lib-up')) $('#bk-lib-up').onchange = function () { upload(this.files); this.value = ''; };
  var dz = $('#bk-lib-dz'), dzBox = $('#bk-lib-drop');
  dzBox.addEventListener('dragover', function (e) { if (!X.media.canUp) return; e.preventDefault(); dz.classList.remove('hidden'); });
  dzBox.addEventListener('dragleave', function (e) { if (!dzBox.contains(e.relatedTarget)) dz.classList.add('hidden'); });
  dzBox.addEventListener('drop', function (e) { e.preventDefault(); dz.classList.add('hidden'); upload(e.dataTransfer.files); });
  function defaultPl() { return $$('#bk-pl-pick input:checked').map(function (x) { return x.value; }).join('|') || X.connected.slice(0, 2).join('|') || 'facebook'; }
  $('#bk-lib-acts').addEventListener('click', function (e) {
    var b = e.target.closest('[data-a]'); if (!b) return;
    if (!L.pick.length) return alert(S.libpick);
    var a = b.dataset.a, toks = L.pick.map(function (id) { return 'lib:' + id; });
    if (a === 'row') { snap(); var ci = ensure('media_urls'); rows[L.row][ci] = toks.join('|'); }
    else if (a === 'new') {
      if (!head.length) { blank(); fname = 'media-posts.csv'; } else snap();
      var c1 = ensure('post_content'), c2 = ensure('platforms'), c3 = ensure('media_urls'), pl = defaultPl(), base = rows.length;
      toks.forEach(function (t) { var r = head.map(function () { return ''; }); r[c2] = pl; r[c3] = t; rows.push(r); });
      sel = {}; for (var k = base; k < rows.length; k++) sel[k] = 1;
      var sc = $('input[name=bk-scope][value=sel]'); if (sc) sc.checked = true;
      notes = notes.concat([f(S.libnew, toks.length)]);
    } else {
      snap(); var ci2 = ensure('media_urls'), t = targets(ci2); if (!t) { undo.pop(); return; }
      if (a === 'spread') { t.forEach(function (ri, k) { rows[ri][ci2] = toks[k % toks.length]; }); notes = notes.concat([f(S.libspread, t.length)].concat(toks.length < t.length ? [S.libcycle] : [])); }
      else { t.forEach(function (ri) { rows[ri][ci2] = toks.join('|'); }); notes = notes.concat([f(S.libsame, t.length)]); }
    }
    dirty = true; closeM(libBox); show();
    if (a === 'new') { $('#bk-tools').open = true; $('#bk-tools').scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  });
  $('#bk-lib-start').onclick = function () { openLib(null); };
  $('#bk-lib-add').onclick = function () { openLib(null); };

  /* ---------- نص لكل سطر فاضي (ذكاء اصطناعي على دفعات) ---------- */
  var cap = $('#bk-cap');
  function emptyRows() { var ci = col('post_content'); return rows.map(function (r, i) { return i; }).filter(function (i) { return ci < 0 || !String(rows[i][ci] || '').trim(); }); }
  function hintFor(r) {
    var parts = [], mi = col('media_urls');
    mediaList(mi > -1 ? r[mi] : '').forEach(function (u) { var m = /^lib:(\d+)$/.exec(u); if (m && lib[m[1]]) parts.push(lib[m[1]].desc || lib[m[1]].name.replace(/\.[a-z0-9]+$/i, '').replace(/[-_]+/g, ' ')); });
    ['title', 'tags'].forEach(function (k) { var i = col(k); if (i > -1 && r[i]) parts.push(r[i]); });
    return parts.join(' — ') || '—';
  }
  function imgOf(r) {
    var mi = col('media_urls'), out = {};
    mediaList(mi > -1 ? r[mi] : '').some(function (u) {
      var m = /^lib:(\d+)$/.exec(u);
      if (m && (!lib[m[1]] || lib[m[1]].kind !== 'video')) { out.lib = +m[1]; return true; }
      if (/^https:\/\/\S+\.(jpe?g|png|webp|gif)(\?\S*)?$/i.test(u)) { out.url = u; return true; }
      return false;
    });
    return out;
  }
  if ($('#bk-cap-btn')) $('#bk-cap-btn').onclick = function () {
    var er = emptyRows(), n = er.length, withImg = er.filter(function (i) { var o = imgOf(rows[i]); return o.lib || o.url; }).length;
    $('#bk-cap-info').textContent = n ? f(S.capinfo, n) : S.capnone; $('#bk-cap-go').disabled = !n;
    $('#bk-cap-vis').checked = withImg > 0; $('#bk-cap-vis').disabled = !withImg;
    $('#bk-cap-vis-t').textContent = withImg ? f(S.visinfo, withImg) : S.visnone;
    $('#bk-cap-vis-l').classList.toggle('opacity-60', !withImg);
    $('#bk-cap-msg').textContent = ''; $('#bk-cap-prog').classList.add('hidden'); openM(cap);
  };
  if ($('#bk-cap-go')) $('#bk-cap-go').onclick = function () {
    var idx = emptyRows(), btn = this, msg = $('#bk-cap-msg'), bar = $('#bk-cap-bar'), done = 0, k = 0, vis = $('#bk-cap-vis').checked && !$('#bk-cap-vis').disabled, per = vis ? 5 : 20, saw = 0;
    if (!idx.length) return;
    snap(); var ci = ensure('post_content'); btn.disabled = true; $('#bk-cap-prog').classList.remove('hidden');
    (function next() {
      if (k >= idx.length) { btn.disabled = false; msg.className = 'text-xs text-lime-700'; var dm = f(S.capdone, done) + (saw ? ' · ' + f(S.saw, saw) : ''); msg.textContent = dm; notes = notes.concat([dm]); dirty = true; renderMap(); render(); setTimeout(function () { closeM(cap); }, 700); return; }
      var part = idx.slice(k, k + per);
      msg.className = 'text-xs text-violet-700 animate-pulse'; msg.textContent = f(S.capwait, Math.min(k + part.length, idx.length), idx.length);
      var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('ajax', 'caption'); fd.append('lang', $('#bk-cap-lang').value); fd.append('brief', $('#bk-cap-brief').value);
      if (vis) fd.append('vision', '1');
      fd.append('items', JSON.stringify(part.map(function (ri) { var o = vis ? imgOf(rows[ri]) : {}; return { i: ri, hint: hintFor(rows[ri]), lib: o.lib || 0, url: o.url || '' }; })));
      fetch(X.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) throw new Error(j.error || 'error');
        saw += +j.saw || 0;
        j.posts.forEach(function (p) { if (rows[p.i] && !String(rows[p.i][ci] || '').trim()) { rows[p.i][ci] = p.text; done++; } });
        k += part.length; bar.style.width = Math.round(k / idx.length * 100) + '%'; render(); next();
      }).catch(function (err) { btn.disabled = false; msg.className = 'text-xs text-red-600'; msg.textContent = err.message; render(); });
    })();
  };

  /* ---------- المعاينة ---------- */
  var pvi = 0;
  function preview(ri) {
    pvi = ri; var r = rows[ri]; if (!r) return;
    var get = function (n) { var i = col(n); return i < 0 ? '' : String(r[i] || '').trim(); };
    var pls = get('platforms').split('|').filter(Boolean), media = mediaList(get('media_urls'))[0] || '';
    var lm = /^lib:(\d+)$/.exec(media); if (lm) media = (lib[lm[1]] && lib[lm[1]].kind === 'video') ? libUrl(lm[1]) + '#.mp4' : libUrl(lm[1]);
    var when = truthy(get('publish_now')) ? S.nowL : truthy(get('use_queue')) ? S.queueL : truthy(get('is_draft')) ? S.draftL : get('schedule_time');
    var ic = function (p) { var d = X.icons[p]; return d ? '<svg viewBox="0 0 24 24" class="w-4 h-4" fill="' + esc(d[0]) + '" aria-hidden="true"><path d="' + esc(d[1]) + '"/></svg>' : ''; };
    var txt = (pls[0] && get('custom_content_' + pls[0])) || get('post_content');
    $('#bk-pv-body').innerHTML = '<div class="p-4 flex items-center gap-3"><div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-500 to-cyan-500 text-white grid place-items-center font-bold">' + esc((X.brand || '?').charAt(0)) + '</div><div class="min-w-0"><div class="font-semibold text-ink-900 truncate">' + esc(X.brand) + '</div><div class="text-xs text-ink-500" dir="auto">' + esc(when || '—') + '</div></div><div class="ms-auto flex gap-1">' + pls.map(ic).join('') + '</div></div>' +
      '<div class="px-4 pb-3 text-sm text-ink-900 whitespace-pre-wrap break-words max-h-72 overflow-auto" dir="auto">' + esc(txt).replace(/(#[\p{L}\p{N}_]+)/gu, '<span class="text-brand-700">$1</span>') + '</div>' +
      (media ? (/\.(mp4|mov|webm)(\?|$)/i.test(media) ? '<video src="' + esc(media) + '" class="w-full max-h-72 bg-black" controls></video>' : '<img src="' + esc(media) + '" alt="" class="w-full max-h-72 object-cover bg-ink-100" onerror="this.style.display=\'none\'">') : '');
    $('#bk-pv-n').textContent = f(S.pvof, ri + 1, rows.length);
    openM($('#bk-pv'));
  }
  $('#bk-pv-prev').onclick = function () { if (pvi > 0) preview(pvi - 1); };
  $('#bk-pv-next').onclick = function () { if (pvi < rows.length - 1) preview(pvi + 1); };

  /* ---------- الرفع على دفعات ---------- */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!rows.length) return alert(S.pick);
    var m = mode();
    if (m === 'create') { var now = rows.filter(function (r) { return truthy(r[col('publish_now')]); }).length; if (!confirm(f(S.confirm, rows.length, now))) return; }
    run(m);
  });
  function run(m) {
    var sendHead = head.filter(function (h) { return !ignored[h]; }), idx = head.map(function (h, i) { return ignored[h] ? -1 : i; }).filter(function (i) { return i > -1; });
    var chunks = []; for (var o = 0; o < rows.length; o += CHUNK) chunks.push(o);
    var agg = { total: 0, valid: 0, invalid: 0, groups: {}, explain: {}, created: [], warnings: {} }, okRows = [];
    srvErr = {};
    var go = $('#bk-go'), prog = $('#bk-prog'), bar = $('#bk-prog-bar');
    go.disabled = true; prog.classList.remove('hidden'); resBox.innerHTML = ''; step(3);
    var k = 0;
    function next() {
      if (k >= chunks.length) return finish();
      var off = chunks[k], part = rows.slice(off, off + CHUNK).map(function (r) { return idx.map(function (i) { return r[i]; }); });
      $('#bk-prog-t').textContent = f(S.batch, k + 1, chunks.length); $('#bk-prog-p').textContent = Math.round(k / chunks.length * 100) + '%';
      bar.style.width = Math.round(k / chunks.length * 100) + '%';
      var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('ajax', '1'); fd.append('mode', m); fd.append('fname', fname); fd.append('csv_text', toCsv(sendHead, part));
      fetch(X.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) throw new Error(j.error || 'error');
        var d = j.data || {};
        Object.assign(agg.explain, j.explain || {});
        agg.total += +d.total || 0; agg.valid += +d.valid || 0; agg.invalid += +d.invalid || 0;
        (d.warnings || []).forEach(function (w) { if (!/^rows_exceed_advisory_limit/.test(w)) agg.warnings[w] = 1; });
        (d.results || []).forEach(function (row) {
          var gi = off + (+row.rowIndex || 0) - 1;
          if (row.ok) { okRows.push(gi); if (row.createdPostId) agg.created.push({ row: gi + 1, id: row.createdPostId }); return; }
          (row.errors || []).forEach(function (er) { (agg.groups[er] = agg.groups[er] || []).push(gi); });
          srvErr[gi] = (row.errors || []).map(function (er) { return (j.explain[er] || [er])[0]; });
        });
        k++; next();
      }).catch(function (err) { finish(f(S.stopped, k + 1, err.message)); });
    }
    function finish(stopMsg) {
      bar.style.width = '100%'; $('#bk-prog-p').textContent = stopMsg ? '' : '100%'; $('#bk-prog-t').textContent = stopMsg || S.done;
      go.disabled = false;
      var createdN = 0;
      if (m === 'create' && okRows.length) {
        // السطور اللي اتعملت بتتشال عشان ماتتكررش لو رفعت تاني
        var del = {}; okRows.forEach(function (i) { del[i] = 1; }); createdN = okRows.length;
        var map = removeRows(del);
        Object.keys(agg.groups).forEach(function (g) { agg.groups[g] = agg.groups[g].map(function (i) { return map[i]; }).filter(function (i) { return i != null; }); });
        undo = []; $('#bk-undo').disabled = true; dirty = true;
        if (!rows.length) clearDraft();
      }
      results(m, agg, createdN, stopMsg);
      render();
      resBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    next();
  }
  function results(m, a, createdN, stopMsg) {
    var g = Object.keys(a.groups).sort(function (x, y) { return a.groups[y].length - a.groups[x].length; });
    var tile = function (lbl, n, cls) { return '<div class="rounded-xl border p-3 ' + cls + '"><div class="text-xs text-ink-500">' + esc(lbl) + '</div><div class="text-xl font-bold">' + n + '</div></div>'; };
    var h = '<div class="card p-5 space-y-4"><h2 class="font-bold text-ink-900">' + esc(m === 'create' ? S.resC : S.res) + ' · <span class="text-sm text-ink-500" dir="ltr">' + esc(fname) + '</span></h2>';
    if (stopMsg) h += '<div class="rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm p-3">' + esc(stopMsg) + '</div>';
    h += '<div class="grid grid-cols-3 gap-2">' + tile(S.tot, a.total, 'border-ink-200 text-ink-900') + tile(S.okL, a.valid, 'border-lime-200 bg-lime-50/60 text-lime-700') + tile(S.badL, a.invalid, a.invalid ? 'border-red-200 bg-red-50/60 text-red-600' : 'border-ink-200 text-ink-900') + '</div>';
    Object.keys(a.warnings).forEach(function (w) { var ex = a.explain[w] || [w, '']; h += '<div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm"><div class="font-medium text-amber-900">⚠️ ' + esc(ex[0]) + '</div>' + (ex[1] ? '<div class="text-xs text-amber-800 mt-0.5">' + esc(ex[1]) + '</div>' : '') + '</div>'; });
    if (createdN) h += '<div class="rounded-xl bg-lime-50 border border-lime-200 p-3 text-sm text-lime-800 flex flex-wrap items-center gap-2">✅ ' + esc(f(S.created, createdN)) + '<a class="link ms-auto" href="' + esc(X.cal) + '">' + esc(S.opencal) + '</a></div>';
    if (!g.length && !stopMsg && m === 'check' && a.total) h += '<div class="rounded-xl bg-lime-50 border border-lime-200 p-3 text-sm text-lime-800">' + esc(S.allok) + '</div>';
    if (g.length) {
      h += '<div><h3 class="font-semibold text-ink-900 mb-2">' + esc(S.probs) + '</h3><div class="space-y-2">';
      g.forEach(function (code) {
        var ex = a.explain[code] || [code, ''], n = a.groups[code].length;
        h += '<div class="rounded-xl border border-red-200 bg-red-50/40 p-3"><div class="flex flex-wrap items-center gap-2"><span class="font-medium text-red-800">' + esc(ex[0]) + '</span><span class="ms-auto text-xs rounded-full bg-red-100 text-red-700 px-2 py-0.5">' + esc(f(S.nrows, n)) + '</span></div>' +
          (ex[1] ? '<div class="text-sm text-ink-700 mt-1">✅ ' + esc(ex[1]) + '</div>' : '') + '<button type="button" class="bk-showsrv link text-xs mt-1.5">' + esc(S.show) + ' ↑</button></div>';
      });
      h += '</div></div>';
      if (Object.keys(srvErr).length) h += '<div class="text-xs text-ink-500">' + esc(f(S.left, Object.keys(srvErr).length)) + '</div>';
    }
    resBox.innerHTML = h + '</div>';
    $$('.bk-showsrv', resBox).forEach(function (b) { b.onclick = function () { var c = $('#bk-filter [data-f=srv]'); c.classList.remove('hidden'); c.click(); wrap.scrollIntoView({ behavior: 'smooth' }); }; });
  }
})();
