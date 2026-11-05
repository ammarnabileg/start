/* الرفع بالجملة: ملف أو لصق من Excel أو كتابة → جدول بيتصلح لوحده (أعمدة عربي، مواعيد، منصات) → أدوات سريعة →
   فحص/رفع على دفعات 500 سطر. مفيش حاجة بتتبعت غير بزرار "ابدأ". */
(function () {
  var X = window.BKX; if (!X) return;
  var S = X.s, CHUNK = 500;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function fmt(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
  function digits(s) { return String(s).replace(/[٠-٩]/g, function (c) { return c.charCodeAt(0) - 0x660; }).replace(/[۰-۹]/g, function (c) { return c.charCodeAt(0) - 0x6F0; }); }

  var form = $('#bk-form'), file = $('#bk-file'), drop = $('#bk-drop'), wrap = $('#bk-grid-wrap'), grid = $('#bk-grid'), send = $('#bk-send'), resBox = $('#bk-res');
  var head = [], rows = [], ignored = {}, srvErr = {}, fname = 'posts.csv', dirty = false, filter = '', q = '', notes = [];

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
    'x': 'twitter', 'تويتر': 'twitter', 'اكس': 'twitter', 'twitter/x': 'twitter', 'لينكدان': 'linkedin', 'لينكد ان': 'linkedin', 'لينكدإن': 'linkedin', 'li': 'linkedin', 'تيك توك': 'tiktok', 'تيكتوك': 'tiktok', 'tt': 'tiktok',
    'يوتيوب': 'youtube', 'yt': 'youtube', 'ثريدز': 'threads', 'بنترست': 'pinterest', 'جوجل': 'googlebusiness', 'google': 'googlebusiness', 'gmb': 'googlebusiness', 'google business': 'googlebusiness' };
  function fixPlatforms(v) {
    var raw = String(v || '').trim(); if (!raw) return raw;
    if (/^(all|الكل|كل المنصات)$/i.test(raw) && X.connected.length) return X.connected.join('|');
    return raw.split(/\s*(?:\||,|،|\+|\/| و |;)\s*/).map(function (p) { p = norm(p).replace(/ا/g, 'ا'); return PL[p] || PL[p.replace(/\s/g, '')] || p; }).filter(Boolean).filter(function (p, i, a) { return a.indexOf(p) === i; }).join('|');
  }
  var lastNoTime = 0;
  function fixDate(v) {
    var s = digits(String(v || '')).trim(); if (!s) return s;
    var m;
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(s)) return s;
    // تاريخ Excel (رقم أيام)
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

  /* ---------- تحميل ---------- */
  function load(all, name) {
    var raw = (all.shift() || []).map(function (h) { return h.trim(); });
    notes = []; ignored = {}; srvErr = {}; lastNoTime = 0;
    var mapped = raw.map(mapHeader), shown = [];
    // تاريخ ووقت في عمودين → ميعاد واحد
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
    // تصليح القيم
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
    var opts = function (cur) { return '<option value="">' + esc(S.ignore) + '</option>' + KNOWN.filter(function (k) { return col(k) < 0; }).map(function (k) { return '<option value="' + k + '">' + k + '</option>'; }).join(''); };
    box.innerHTML = '<div class="text-xs font-semibold text-amber-900 mb-2">' + esc(S.unknown) + '</div><div class="flex flex-wrap gap-2">' +
      unk.map(function (h) { return '<label class="inline-flex items-center gap-1.5 text-xs bg-white rounded-lg border border-amber-200 px-2 py-1"><b dir="auto">' + esc(h) + '</b> → <select class="bk-mapsel input !h-7 !py-0 text-xs !w-auto" data-h="' + esc(h) + '">' + opts() + '</select></label>'; }).join('') + '</div>';
  }
  $('#bk-map').addEventListener('change', function (e) {
    var s = e.target.closest('.bk-mapsel'); if (!s || !s.value) return;
    var i = head.indexOf(s.dataset.h); if (i < 0) return;
    delete ignored[s.dataset.h]; head[i] = s.value;
    if (s.value === 'platforms') rows.forEach(function (r) { r[i] = fixPlatforms(r[i]); });
    if (s.value === 'schedule_time') rows.forEach(function (r) { if (r[i]) r[i] = fixDate(r[i]); });
    dirty = true; renderMap(); render();
  });

  /* ---------- الفحص المحلي ---------- */
  function check(r, ri) {
    var errs = {}, get = function (n) { var i = col(n); return i < 0 ? '' : String(r[i] || '').trim(); };
    ['post_content', 'platforms'].forEach(function (n) { if (col(n) >= 0 && !get(n)) errs[n] = S.req; });
    var pls = get('platforms').toLowerCase().split('|').map(function (x) { return x.trim(); }).filter(Boolean);
    var bad = pls.filter(function (p) { return X.platforms.indexOf(p) < 0; });
    if (bad.length) errs.platforms = f(S.plat, bad.join(', '));
    else if (X.connected.length) { var nc = pls.filter(function (p) { return X.connected.indexOf(p) < 0; }); if (nc.length) errs.platforms = f(S.noacc, nc.join(', ')); }
    var st = get('schedule_time');
    if (st) { if (!/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test(st)) errs.schedule_time = S.date; else if (new Date(st.replace(' ', 'T')) < new Date()) errs.schedule_time = S.past; }
    else if (!truthy(get('use_queue')) && !truthy(get('publish_now')) && !truthy(get('is_draft'))) errs.schedule_time = S.when;
    if (pls.indexOf('twitter') > -1 && Array.from(get('custom_content_twitter') || get('post_content')).length > 280) errs.post_content = S.x;
    var mu = get('media_urls'); if (mu && mu.split('|').some(function (u) { return u.trim() && !/^https?:\/\//i.test(u.trim()); })) errs.media_urls = S.url;
    return errs;
  }

  /* ---------- الجدول ---------- */
  function render() {
    if (!head.length) return;
    var missing = ['post_content', 'platforms'].filter(function (n) { return col(n) < 0; });
    var okN = 0, badN = 0, srvN = 0, body = '', shown = 0;
    rows.forEach(function (r, ri) {
      var e = check(r, ri), se = srvErr[ri], isBad = Object.keys(e).length > 0 || !!se;
      if (isBad) badN++; else okN++; if (se) srvN++;
      if (filter === 'bad' && !isBad) return; if (filter === 'srv' && !se) return;
      if (q && String(r[col('post_content')] || '').toLowerCase().indexOf(q) < 0) return;
      if (++shown > 600) return; // الجدول الكبير: أول 600 بس على الشاشة
      body += '<tr class="border-t border-ink-100 ' + (isBad ? 'bg-red-50/40' : '') + '" data-r="' + ri + '"><td class="px-2 py-1 text-ink-400 sticky start-0 bg-white align-top">' + (ri + 1) + '</td>'
        + head.map(function (h, ci) {
          var er = e[h], ig = ignored[h];
          return '<td class="px-1 py-0.5 align-top ' + (ig ? 'opacity-40' : '') + '"><div contenteditable="true" dir="auto" data-c="' + ci + '" class="min-w-[6rem] max-w-[18rem] max-h-20 overflow-auto rounded px-1.5 py-1 outline-none focus:ring-2 focus:ring-brand-300 ' + (er ? 'ring-1 ring-red-300 bg-red-50' : 'hover:bg-ink-50') + '" title="' + esc(er || '') + '">' + esc(r[ci]) + '</div>' + (er ? '<div class="text-[10px] text-red-600 px-1">' + esc(er) + '</div>' : '') + '</td>';
        }).join('')
        + '<td class="px-1 py-1 align-top text-[10px] text-red-700 ' + (se ? 'min-w-[12rem] max-w-[16rem]' : '') + '" dir="auto">' + (se ? esc(f(S.srv, se.join(' · '))) : '') + '</td>'
        + '<td class="px-1 align-top"><button type="button" data-del="' + ri + '" class="w-6 h-6 rounded text-ink-400 hover:bg-red-50 hover:text-red-600" title="' + esc(S.del) + '" aria-label="' + esc(S.del) + '">×</button></td></tr>';
    });
    grid.innerHTML = '<thead class="sticky top-0 bg-ink-50 z-[1]"><tr><th class="px-2 py-1.5 text-start text-ink-400">#</th>' + head.map(function (h) { return '<th class="px-2 py-1.5 text-start font-mono text-[11px] whitespace-nowrap ' + (ignored[h] ? 'text-ink-400 line-through' : 'text-ink-600') + '">' + esc(h) + '</th>'; }).join('') + '<th></th><th></th></tr></thead><tbody>' + body + '</tbody>';
    $('#bk-sum').innerHTML = '<span class="text-ink-700">' + esc(f(S.rows, rows.length)) + '</span> · <span class="text-lime-700">' + esc(f(S.ok, okN)) + '</span>' + (badN ? ' · <span class="text-red-600 font-medium">' + esc(f(S.bad, badN)) + '</span>' : '')
      + (missing.length ? ' · <span class="text-red-600 font-medium">' + esc(f(S.need, missing.join(', '))) + '</span>' : '') + (rows.length > 5000 ? ' · <span class="text-red-600">' + esc(S.max) + '</span>' : '') + (dirty ? ' <span class="text-amber-700">' + esc(S.edited) + '</span>' : '');
    $('#bk-filter [data-f=srv]').classList.toggle('hidden', !srvN);
    var batches = Math.max(1, Math.ceil(rows.length / CHUNK));
    $('#bk-go-hint').textContent = rows.length ? f(mode() === 'create' ? S.goCreate : S.goCheck, rows.length, batches) : S.empty;
    $('#bk-go').disabled = !rows.length || missing.length > 0 || rows.length > 5000;
    days();
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
    var loc = X.lang === 'ar' ? 'ar-EG' : 'en';
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
  $('#bk-paste-go').onclick = function () { var t = $('#bk-paste-txt').value; if (!t.trim()) return; fname = 'pasted.csv'; load(parse(t), 'pasted.csv'); dirty = true; render(); };
  // لصق في أي حتة في الصفحة وإحنا في الخطوة الأولى
  document.addEventListener('paste', function (e) {
    if (head.length || e.target.closest('input,textarea,[contenteditable]')) return;
    var t = (e.clipboardData || window.clipboardData).getData('text'); if (!t || t.indexOf('\n') < 0) return;
    e.preventDefault(); load(parse(t), 'pasted.csv'); dirty = true; render();
  });
  $('#bk-new').onclick = function () {
    var d = new Date(); d.setDate(d.getDate() + 1); d.setHours(10, 0, 0, 0);
    head = ['post_content', 'platforms', 'schedule_time', 'media_urls']; ignored = {}; srvErr = {}; notes = [];
    rows = [['', X.connected.length ? X.connected.slice(0, 2).join('|') : 'facebook', fmt(d), '']]; fname = 'posts.csv'; dirty = true;
    show(); var c = $('[contenteditable]', grid); if (c) c.focus();
  };
  $('#bk-reset').onclick = function () { if (dirty && !confirm(S.reset)) return; head = []; rows = []; srvErr = {}; file.value = ''; resBox.innerHTML = ''; show(); };
  $('#bk-add').onclick = function () { var last = rows[rows.length - 1] || []; rows.push(head.map(function (h, i) { return h === 'post_content' ? '' : (last[i] || ''); })); dirty = true; render(); var t = grid.querySelector('tbody tr:last-child [contenteditable]'); if (t) t.focus(); };
  $('#bk-dl').onclick = function () { var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + toCsv(head, rows)], { type: 'text/csv' })); a.download = fname.replace(/\.(csv|txt|tsv)$/i, '') + '-edited.csv'; a.click(); };
  $$('#bk-filter .chip').forEach(function (b) { b.onclick = function () { $$('#bk-filter .chip').forEach(function (x) { x.classList.toggle('on', x === b); }); filter = b.dataset.f; render(); }; });
  $('#bk-q').addEventListener('input', function () { q = this.value.trim().toLowerCase(); render(); });
  $$('input[name=mode]', form).forEach(function (r) { r.addEventListener('change', render); });
  grid.addEventListener('input', function (e) { var c = e.target.closest('[contenteditable]'); if (!c) return; var r = +c.closest('tr').dataset.r; rows[r][+c.dataset.c] = c.innerText.replace(/\n$/, ''); delete srvErr[r]; dirty = true; });
  grid.addEventListener('focusout', function (e) {
    var c = e.target.closest('[contenteditable]'); if (!c) return;
    var r = +c.closest('tr').dataset.r, ci = +c.dataset.c;
    if (head[ci] === 'schedule_time' && rows[r][ci]) rows[r][ci] = fixDate(rows[r][ci]);
    if (head[ci] === 'platforms') rows[r][ci] = fixPlatforms(rows[r][ci]);
    setTimeout(function () { if (!grid.contains(document.activeElement)) render(); }, 0);
  });
  grid.addEventListener('click', function (e) { var b = e.target.closest('[data-del]'); if (b) { var i = +b.dataset.del; rows.splice(i, 1); var ns = {}; Object.keys(srvErr).forEach(function (k) { k = +k; if (k < i) ns[k] = srvErr[k]; else if (k > i) ns[k - 1] = srvErr[k]; }); srvErr = ns; dirty = true; render(); } });
  $$('.bk-copy').forEach(function (b) { b.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(b.dataset.v); var sp = b.querySelector('span') || b, t = sp.textContent; sp.textContent = S.copied; setTimeout(function () { sp.textContent = t; }, 900); }); });

  /* ---------- الأدوات السريعة ---------- */
  function ensure(k) { var i = col(k); if (i > -1) return i; head.push(k); rows.forEach(function (r) { r.push(''); }); return head.length - 1; }
  function flash(btn, msg) { var o = btn.textContent; btn.textContent = msg; setTimeout(function () { btn.textContent = o; }, 1400); }
  $('#bk-pl-apply').onclick = function () {
    var v = $$('#bk-pl-pick input:checked').map(function (x) { return x.value; }); if (!v.length) return alert(S.plpick);
    var i = ensure('platforms'), only = $('#bk-pl-empty').checked, n = 0;
    rows.forEach(function (r) { if (!only || !String(r[i] || '').trim()) { r[i] = v.join('|'); n++; } });
    dirty = true; render(); flash(this, f(S.applied, n));
  };
  var spStart = $('#bk-sp-start');
  (function () { var d = new Date(); d.setDate(d.getDate() + 1); d.setHours(10, 0, 0, 0); spStart.value = fmt(d).replace(' ', 'T'); })();
  $('#bk-sp-apply').onclick = function () {
    if (!spStart.value) return alert(S.spstart);
    var t = new Date(spStart.value), n = Math.max(1, +$('#bk-sp-n').value || 1), unit = $('#bk-sp-u').value, only = $('#bk-sp-empty').checked;
    var i = ensure('schedule_time'), cnt = 0;
    rows.forEach(function (r) {
      if (only && String(r[i] || '').trim()) return;
      if (truthy(r[col('use_queue')]) || truthy(r[col('publish_now')]) || truthy(r[col('is_draft')])) return;
      r[i] = fmt(t); cnt++;
      if (unit === 'h') t.setHours(t.getHours() + n); else t.setDate(t.getDate() + n);
    });
    dirty = true; render(); flash(this, f(S.applied, cnt));
  };

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
        var ns = {}, nr = [], map = {};
        rows.forEach(function (r, i) { if (!del[i]) { map[i] = nr.length; nr.push(r); } });
        Object.keys(srvErr).forEach(function (i) { if (map[i] != null) ns[map[i]] = srvErr[i]; });
        Object.keys(agg.groups).forEach(function (g) { agg.groups[g] = agg.groups[g].map(function (i) { return map[i]; }).filter(function (i) { return i != null; }); });
        rows = nr; srvErr = ns; dirty = true;
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
