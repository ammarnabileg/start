/* منشور جديد: معاينة حية لكل منصة، حدود الحروف، اختيار حسابات أسرع، سحب وإفلات للوسائط، مواعيد سريعة، مسودة تلقائية، ملخص قبل النشر */
(function () {
  var P = window.CPX, form = document.getElementById('content') && document.getElementById('content').form; if (!P || !form) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch (e) {} } };
  var content = $('#content'), mi = $('#mediaInput'), accs = $$('.acc');
  var LIM = { twitter: 280, threads: 500, bluesky: 300, instagram: 2200, tiktok: 2200, linkedin: 3000, pinterest: 500, youtube: 5000, facebook: 63206, telegram: 4096, googlebusiness: 1500, reddit: 40000, snapchat: 250 };
  var FOLD = { facebook: 480, instagram: 125, linkedin: 210, twitter: 280, threads: 500, tiktok: 150 };
  var sel = function () { return accs.filter(function (c) { return c.checked; }); };
  var textFor = function (c) { var t = form.querySelector('[name="opt[' + c.value + '][custom]"]'); return t && t.value.trim() ? t.value : content.value; };

  /* ---------- 1) حدود الحروف لكل منصة ---------- */
  var cc = $('#charCount'), meter = document.createElement('div'); meter.className = 'flex flex-wrap gap-1.5 mt-2'; meter.id = 'cp-limits';
  var ed = $('.cp-editor'); if (ed) ed.after(meter); else if (cc) cc.closest('.flex').after(meter);
  function limits() {
    var seen = {}; meter.innerHTML = '';
    sel().forEach(function (c) {
      var p = c.dataset.plat, lim = LIM[p]; if (!lim) return; var n = Array.from(textFor(c)).length, key = p + (textFor(c) === content.value ? '' : c.value);
      if (seen[key]) return; seen[key] = 1;
      var pct = Math.min(100, 100 * n / lim), bad = n > lim, warn = !bad && pct > 90;
      var b = document.createElement('span'); b.className = 'inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-[11px] tabular-nums ' + (bad ? 'border-red-300 bg-red-50 text-red-700' : warn ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-ink-200 bg-white text-ink-600');
      b.innerHTML = (P.icons[p] || '') + '<span>' + n + ' / ' + lim + '</span><span class="w-10 h-1 rounded-full bg-ink-100 overflow-hidden"><span class="block h-full rounded-full ' + (bad ? 'bg-red-500' : warn ? 'bg-amber-500' : 'bg-lime-500') + '" style="width:' + pct + '%"></span></span>';
      b.title = (P.plat[p] || p) + (bad ? ' — ' + P.tooLong.replace('%s', n - lim) : ''); meter.appendChild(b);
    });
    if (cc) { var hint = cc.parentNode; hint.classList.toggle('hidden', sel().length > 0); }
    // عدد الهاشتاجات
    var tags = (content.value.match(/#[\p{L}\p{N}_]+/gu) || []).length, ht = $('#cp-tags'); if (ht) ht.textContent = tags ? P.tags.replace('%s', tags) : '';
  }

  /* ---------- 2) أدوات النص: إيموجي + هاشتاج ---------- */
  var bar = document.createElement('div'); bar.className = 'flex flex-wrap items-center gap-1 border-t border-ink-100 px-2 py-1.5';
  bar.innerHTML = '<button type="button" data-tl="emoji" class="relative inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-ink-600 hover:bg-ink-100"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 007 0M9 9.5h.01M15 9.5h.01"/></svg>' + esc(P.emoji) + '</button>'
    + '<button type="button" data-tl="#" class="rounded-lg px-2 py-1 text-xs text-ink-600 hover:bg-ink-100"># ' + esc(P.hashtag) + '</button>'
    + '<button type="button" data-tl="nl" class="rounded-lg px-2 py-1 text-xs text-ink-600 hover:bg-ink-100">↵ ' + esc(P.line) + '</button>'
    + '<span id="cp-tags" class="ms-auto text-[11px] text-ink-500"></span><span id="cp-draft" class="text-[11px] text-amber-700"></span>';
  content.closest('label').after(bar);
  function insert(v) { var s = content.selectionStart, e = content.selectionEnd; content.value = content.value.slice(0, s) + v + content.value.slice(e); content.setSelectionRange(s + v.length, s + v.length); content.focus(); content.dispatchEvent(new Event('input', { bubbles: true })); }
  var pop = null;
  bar.addEventListener('click', function (e) {
    var b = e.target.closest('[data-tl]'); if (!b) return; e.stopPropagation();
    if (b.dataset.tl === '#') insert((content.value && !/\s$/.test(content.value.slice(0, content.selectionStart)) ? ' ' : '') + '#');
    else if (b.dataset.tl === 'nl') insert('\n\n');
    else {
      if (pop) { pop.remove(); pop = null; return; }
      pop = document.createElement('div'); pop.className = 'absolute top-full mt-2 start-0 z-30 w-72 grid grid-cols-8 gap-1 rounded-2xl border border-ink-200 bg-white p-2 shadow-xl';
      '😊 😍 🔥 ✨ 🎉 💯 👏 🙌 ❤️ 💙 💚 ⭐ ✅ 👉 👇 📣 🎁 🛒 💳 🚚 📦 📍 ⏰ 📞 💬 📸 🎥 🌙 ☀️ 🏆 🚀 💡'.split(' ').forEach(function (x) { var q = document.createElement('button'); q.type = 'button'; q.className = 'h-7 rounded-lg hover:bg-ink-100 text-lg leading-none'; q.textContent = x; q.onclick = function (ev) { ev.stopPropagation(); insert(x); }; pop.appendChild(q); });
      b.appendChild(pop);
    }
  });
  document.addEventListener('click', function () { if (pop) { pop.remove(); pop = null; } });

  /* ---------- 3) الحسابات: بطاقات + اختيار حسب المنصة + بحث ---------- */
  var accBox = accs[0] && accs[0].closest('.space-y-2');
  if (accBox) {
    accs.forEach(function (c) { var l = c.closest('label'); l.classList.add('cp-acc'); });
    var plats = {}; accs.forEach(function (c) { plats[c.dataset.plat] = (plats[c.dataset.plat] || 0) + 1; });
    var chips = document.createElement('div'); chips.className = 'flex flex-wrap gap-1.5 mb-3';
    Object.keys(plats).forEach(function (p) {
      var b = document.createElement('button'); b.type = 'button'; b.dataset.pf = p; b.className = 'inline-flex items-center gap-1 rounded-full border border-ink-200 px-2 py-0.5 text-[11px] text-ink-600 hover:border-brand-300 transition';
      b.innerHTML = (P.icons[p] || '') + '<span>' + plats[p] + '</span>'; b.title = P.plat[p] || p;
      b.onclick = function () { var group = accs.filter(function (c) { return c.dataset.plat === p; }), on = group.some(function (c) { return !c.checked; }); group.forEach(function (c) { c.checked = on; c.dispatchEvent(new Event('change', { bubbles: true })); }); };
      chips.appendChild(b);
    });
    if (Object.keys(plats).length > 1) accBox.before(chips);
    if (accs.length > 6) { var s = document.createElement('input'); s.type = 'search'; s.className = 'input !mt-0 !h-9 !py-0 text-sm mb-2'; s.placeholder = P.searchAcc; s.dir = 'auto'; accBox.before(s); s.oninput = function () { var v = s.value.trim().toLowerCase(); accs.forEach(function (c) { c.closest('label').style.display = !v || (c.dataset.name + ' ' + c.dataset.user + ' ' + (P.plat[c.dataset.plat] || '')).toLowerCase().indexOf(v) > -1 ? '' : 'none'; }); }; }
    var head = accBox.previousElementSibling && accBox.parentNode.querySelector('.font-bold'); var cnt = document.createElement('span'); cnt.className = 'ms-2 rounded-full bg-brand-600 text-[#fff] text-[10px] px-1.5 py-0.5 tabular-nums hidden'; if (head) head.appendChild(cnt);
    var paintChips = function () { var n = sel().length; cnt.textContent = n; cnt.classList.toggle('hidden', !n); $$('[data-pf]', chips).forEach(function (b) { var g = accs.filter(function (c) { return c.dataset.plat === b.dataset.pf; }), all = g.every(function (c) { return c.checked; }), some = g.some(function (c) { return c.checked; }); b.className = 'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] transition ' + (all ? 'border-brand-500 bg-brand-50 text-brand-700' : some ? 'border-brand-300 text-brand-700' : 'border-ink-200 text-ink-600 hover:border-brand-300'); }); };
  }

  /* ---------- 4) الوسائط: سحب وإفلات + حذف + معلومات ---------- */
  var files = [];
  function setFiles(list) { files = list; try { var dt = new DataTransfer(); files.forEach(function (f) { dt.items.add(f); }); mi.files = dt.files; } catch (e) {} drawMedia(); render(); }
  function drawMedia() {
    var mp = $('#mediaPreview'); if (!mp) return; mp.innerHTML = '';
    files.forEach(function (f, i) {
      var box = document.createElement('div'); box.className = 'relative group w-24 h-24 rounded-xl overflow-hidden border border-ink-200 bg-ink-50';
      var inner = /^image\//.test(f.type) ? '<img src="' + URL.createObjectURL(f) + '" class="w-full h-full object-cover">' : /^video\//.test(f.type) ? '<video src="' + URL.createObjectURL(f) + '" class="w-full h-full object-cover" muted></video><span class="absolute top-1 start-1 rounded bg-ink-900/70 text-[#fff] text-[10px] px-1">▶</span>' : '<div class="p-2 text-[10px] break-all">' + esc(f.name) + '</div>';
      box.innerHTML = inner + '<span class="absolute bottom-0 inset-x-0 bg-ink-900/60 text-[#fff] text-[10px] px-1 py-0.5 tabular-nums">' + (f.size / 1048576).toFixed(1) + ' MB</span><button type="button" class="absolute top-1 end-1 w-6 h-6 rounded-full bg-ink-900/70 text-[#fff] text-xs opacity-0 group-hover:opacity-100 transition" title="' + esc(P.remove) + '">✕</button>';
      box.querySelector('button').onclick = function () { setFiles(files.filter(function (_, k) { return k !== i; })); };
      mp.appendChild(box);
    });
    var w = $('#cp-mwarn'); if (!w) { w = document.createElement('p'); w.id = 'cp-mwarn'; w.className = 'text-[11px] text-amber-700 mt-1'; $('#mediaPreview').after(w); }
    var msgs = [], imgs = files.filter(function (f) { return /^image\//.test(f.type); }).length, vids = files.filter(function (f) { return /^video\//.test(f.type); }).length, ps = sel().map(function (c) { return c.dataset.plat; });
    if (ps.indexOf('twitter') > -1 && imgs > 4) msgs.push(P.xMax);
    if ((ps.indexOf('tiktok') > -1 || ps.indexOf('youtube') > -1) && !vids) msgs.push(P.needVideo);
    if (ps.indexOf('instagram') > -1 && !files.length) msgs.push(P.igMedia);
    w.textContent = msgs.join(' · ');
  }
  if (mi) {
    mi.addEventListener('change', function () { files = [].slice.call(mi.files); setTimeout(drawMedia, 0); render(); });
    var lab = mi.closest('label'); mi.classList.add('hidden');
    var dz = document.createElement('div'); dz.className = 'mt-1 flex flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-ink-300 bg-ink-50 px-4 py-5 text-center cursor-pointer hover:border-brand-400 hover:bg-brand-50/40 transition';
    dz.innerHTML = '<svg class="w-6 h-6 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/></svg><b class="text-sm text-ink-900"></b><span class="text-[11px] text-ink-500"></span>';
    dz.querySelector('b').textContent = P.drop; dz.querySelector('span').textContent = P.dropSub;
    lab.appendChild(dz); dz.onclick = function (e) { e.preventDefault(); mi.click(); };
    var card = content.closest('.card');
    ['dragenter', 'dragover'].forEach(function (ev) { card.addEventListener(ev, function (e) { if (e.dataTransfer && [].indexOf.call(e.dataTransfer.types, 'Files') > -1) { e.preventDefault(); dz.classList.add('border-brand-500', 'bg-brand-50'); } }); });
    ['dragleave', 'drop'].forEach(function (ev) { card.addEventListener(ev, function (e) { dz.classList.remove('border-brand-500', 'bg-brand-50'); }); });
    card.addEventListener('drop', function (e) { if (!e.dataTransfer || !e.dataTransfer.files.length) return; e.preventDefault(); setFiles(files.concat([].slice.call(e.dataTransfer.files).filter(function (f) { return /^(image|video)\//.test(f.type) || f.type === 'application/pdf'; }))); });
    content.addEventListener('paste', function (e) { var it = [].slice.call((e.clipboardData || {}).files || []).filter(function (f) { return /^image\//.test(f.type); }); if (it.length) { e.preventDefault(); setFiles(files.concat(it)); } });
  }

  /* ---------- 5) معاينة حية لكل حساب ---------- */
  var right = accBox && accBox.closest('.card'), pv = document.createElement('div'); pv.className = 'card p-0 overflow-hidden'; pv.id = 'cp-pv';
  pv.innerHTML = '<div class="flex items-center gap-2 px-4 h-11 border-b border-ink-100"><b class="text-sm text-ink-900 flex-1"></b><div class="flex gap-1 overflow-x-auto" id="cp-tabs" style="scrollbar-width:none"></div></div><div class="p-4 bg-gradient-to-b from-ink-50 to-white"><div class="mx-auto max-w-[340px] rounded-[2.2rem] border-[10px] border-[#0b0f19] ring-1 ring-ink-200 bg-ink-100 shadow-2xl overflow-hidden"><div class="h-5 flex justify-center bg-[#0b0f19]"><span class="w-20 h-3.5 rounded-b-xl bg-[#0b0f19]"></span></div><div id="cp-card" class="p-3 max-h-[60vh] overflow-y-auto" style="scrollbar-width:thin"></div></div></div>';
  pv.querySelector('b').textContent = P.preview;
  var rc = $('#cp-right'); if (rc) { pv.className += ' lg:sticky lg:top-4'; rc.appendChild(pv); } else content.closest('.card').after(pv);
  var tab = null;
  function fmt(t) { return esc(t).replace(/(#[\p{L}\p{N}_]+)/gu, '<span class="text-brand-600">$1</span>').replace(/(https?:\/\/[^\s<]+)/g, '<span class="text-brand-600 underline">$1</span>').replace(/(@[\w.]+)/g, '<span class="text-brand-600">$1</span>'); }
  function render() {
    var s = sel(), tabs = $('#cp-tabs'), card = $('#cp-card'); if (!card) return;
    if (!s.length) { tabs.innerHTML = ''; card.innerHTML = '<p class="text-xs text-ink-500 text-center py-6">' + esc(P.pickAcc) + '</p>'; return; }
    if (!tab || !s.some(function (c) { return c.value === tab; })) tab = s[0].value;
    tabs.innerHTML = '';
    s.forEach(function (c) { var b = document.createElement('button'); b.type = 'button'; b.className = 'shrink-0 w-7 h-7 rounded-lg flex items-center justify-center transition ' + (c.value === tab ? 'bg-brand-50 ring-2 ring-brand-500/30' : 'hover:bg-ink-100 opacity-60'); b.innerHTML = P.icons[c.dataset.plat] || ''; b.title = c.dataset.name + ' · ' + (P.plat[c.dataset.plat] || ''); b.onclick = function () { tab = c.value; render(); }; tabs.appendChild(b); });
    var c = s.filter(function (x) { return x.value === tab; })[0], p = c.dataset.plat, txt = textFor(c), kind = (($('input[name="cp_kind"]:checked') || {}).value) || 'post';
    var lim = LIM[p], over = lim && Array.from(txt).length > lim, fold = FOLD[p], long = fold && Array.from(txt).length > fold, shown = long ? Array.from(txt).slice(0, fold).join('') + '… ' : txt;
    var med = files.filter(function (f) { return /^(image|video)\//.test(f.type); }).map(function (f) { return { u: URL.createObjectURL(f), v: /^video/.test(f.type) }; })
      .concat($$('#libMedia > div').map(function (w) { var m = $('img, video', w); return m ? { u: m.src, v: m.tagName === 'VIDEO' } : null; }).filter(Boolean))
      .concat($$('.hf-img[data-on="1"]').map(function (w) { var m = $('img, video', w); return m ? { u: m.src, v: m.tagName === 'VIDEO' } : null; }).filter(Boolean)).slice(0, 4);
    var nm = esc(c.dataset.name || ''), un = esc(c.dataset.user || c.dataset.name || '');
    var av = function (z) { return c.dataset.pic ? '<img src="' + esc(c.dataset.pic) + '" class="' + z + ' rounded-full object-cover shrink-0" referrerpolicy="no-referrer">' : '<span class="' + z + ' rounded-full bg-gradient-to-br from-brand-600 to-cyan-500 shrink-0"></span>'; };
    var mtag = function (m, cls) { return m.v ? '<video src="' + esc(m.u) + '" class="' + cls + ' object-cover bg-ink-900" muted playsinline></video>' : '<img src="' + esc(m.u) + '" class="' + cls + ' object-cover">'; };
    var grid = function () { return med.length ? '<div class="grid gap-0.5 ' + (med.length > 1 ? 'grid-cols-2' : '') + '">' + med.map(function (m) { return mtag(m, 'w-full ' + (med.length > 1 ? 'aspect-square' : 'max-h-80')); }).join('') + '</div>' : ''; };
    var body = function (cls, pre) { return txt.trim() ? '<p class="' + cls + ' whitespace-pre-wrap break-words" dir="auto">' + (pre || '') + fmt(shown) + (long ? '<span class="text-ink-500 font-medium">' + esc(P.more) + '</span>' : '') + '</p>' : '<p class="' + cls + ' text-ink-400 italic">' + esc(P.emptyText) + '</p>'; };
    var t = esc(when());
    var I = function (d) { return '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' + d + '"/></svg>'; };
    var HEART = 'M12 20s-7-4.4-7-10a4 4 0 017-2.6A4 4 0 0119 10c0 5.6-7 10-7 10z', CMT = 'M21 12a8 8 0 01-11.6 7.1L4 20l1-4.6A8 8 0 1121 12z', SEND = 'M22 3L11 13M22 3l-7 18-4-8-8-4z', SAVE = 'M6 3h12v18l-6-4-6 4z', SHR = 'M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7M16 6l-4-4-4 4M12 2v13', RT = 'M17 2l4 4-4 4M3 11V9a4 4 0 014-4h14M7 22l-4-4 4-4M21 13v2a4 4 0 01-4 4H3';
    var act = function (list) { return list.map(function (x) { return '<span class="flex items-center gap-1">' + I(x[0]) + esc(x[1]) + '</span>'; }).join(''); };
    var html;
    if (kind !== 'post' && /instagram|facebook|tiktok|youtube/.test(p)) {
      var m0 = med[0];
      html = '<div class="relative mx-auto aspect-[9/16] w-full max-w-[300px] rounded-2xl overflow-hidden bg-gradient-to-br from-fuchsia-600 via-brand-600 to-cyan-500 text-white shadow-lg">' + (m0 ? mtag(m0, 'absolute inset-0 w-full h-full') : '') +
        '<div class="absolute inset-0 bg-gradient-to-b from-black/40 via-transparent to-black/70"></div>' +
        (kind === 'story' ? '<div class="absolute top-2 inset-x-2 flex gap-1">' + (med.length > 1 ? med : [1]).map(function (_, i) { return '<span class="h-0.5 flex-1 rounded-full ' + (i ? 'bg-white/40' : 'bg-white') + '"></span>'; }).join('') + '</div>' : '') +
        '<div class="absolute top-5 start-3 end-3 flex items-center gap-2">' + av('w-8 h-8 ring-2 ring-white') + '<b class="text-xs">' + un + '</b><span class="text-[10px] text-white/70">' + esc(kind === 'story' ? P.kStory : P.kReel) + '</span></div>' +
        (kind === 'reel' ? '<div class="absolute end-2 bottom-20 flex flex-col items-center gap-4">' + [HEART, CMT, SEND].map(I).join('') + '</div>' : '') +
        '<div class="absolute bottom-3 start-3 end-12 text-xs leading-5 line-clamp-4" dir="auto">' + (txt.trim() ? fmt(Array.from(txt).slice(0, 160).join('')) : '') + '</div>' +
        (!m0 ? '<div class="absolute inset-0 grid place-items-center text-xs text-white/85 px-6 text-center">' + esc(P.needMedia) + '</div>' : '') + '</div>';
    } else if (p === 'instagram') {
      html = '<div class="bg-white rounded-xl overflow-hidden border border-ink-200"><div class="flex items-center gap-2 px-3 py-2"><span class="p-[2px] rounded-full bg-gradient-to-tr from-amber-400 via-pink-500 to-purple-600">' + av('w-7 h-7 ring-2 ring-white') + '</span><b class="text-xs text-ink-900 flex-1">' + un + '</b><span class="text-ink-500">•••</span></div>' +
        (med.length ? mtag(med[0], 'w-full aspect-square') : '<div class="w-full aspect-square bg-ink-100 grid place-items-center text-xs text-ink-400 px-6 text-center">' + esc(P.igMedia) + '</div>') +
        '<div class="flex items-center gap-3 px-3 pt-2 text-ink-900">' + I(HEART) + I(CMT) + I(SEND) + '<span class="ms-auto">' + I(SAVE) + '</span></div>' + body('px-3 py-2 text-[13px] text-ink-900', '<b>' + un + '</b> ') + '<div class="px-3 pb-2 text-[10px] text-ink-400">' + t + '</div></div>';
    } else if (/twitter|threads|bluesky/.test(p)) {
      html = '<div class="bg-white rounded-xl border border-ink-200 p-3 flex gap-2.5">' + av('w-9 h-9') + '<div class="min-w-0 flex-1"><div class="flex items-center gap-1 text-[13px]"><b class="text-ink-900 truncate">' + nm + '</b><span class="text-ink-500 truncate" dir="ltr">@' + un + '</span></div>' +
        body('text-[14px] text-ink-900 mt-0.5') + (med.length ? '<div class="mt-2 rounded-2xl overflow-hidden border border-ink-200">' + grid() + '</div>' : '') + '<div class="flex justify-between mt-2 text-ink-500 max-w-[260px]">' + [CMT, RT, HEART, SHR].map(I).join('') + '</div></div></div>';
    } else if (p === 'linkedin') {
      html = '<div class="bg-white rounded-xl border border-ink-200"><div class="flex gap-2 p-3">' + av('w-11 h-11') + '<div class="min-w-0"><b class="text-[13px] text-ink-900 block truncate">' + nm + '</b><span class="text-[11px] text-ink-500 block">' + t + '</span></div></div>' + body('px-3 pb-2 text-[13px] text-ink-900') + grid() +
        '<div class="flex justify-around border-t border-ink-100 py-1.5 text-[11px] font-medium text-ink-600">' + act([[HEART, P.like], [CMT, P.comment], [RT, P.repost], [SEND, P.send]]) + '</div></div>';
    } else {
      html = '<div class="bg-white rounded-xl border border-ink-200"><div class="flex items-center gap-2.5 p-3">' + av('w-10 h-10') + '<div class="min-w-0 flex-1"><b class="block text-[13px] text-ink-900 truncate">' + nm + '</b><span class="text-[11px] text-ink-500">' + t + '</span></div><span class="text-ink-400">•••</span></div>' + body('px-3 pb-3 text-[14px] text-ink-900') + grid() +
        '<div class="flex justify-around border-t border-ink-100 mx-3 py-1.5 text-[12px] font-medium text-ink-600">' + act([[HEART, P.like], [CMT, P.comment], [SHR, P.share]]) + '</div></div>';
    }
    var custom = textFor(c) !== content.value ? '<p class="mt-2 text-[11px] text-brand-700">' + uic('pencil', 'w-4 h-4 inline-block align-[-3px]') + esc(P.customText) + '</p>' : '';
    card.innerHTML = '<div class="cp-pop">' + html + '</div>' + custom + (over ? '<p class="mt-2 text-[11px] text-red-600">' + esc(P.tooLong.replace('%s', Array.from(txt).length - lim)) + '</p>' : '');
  }

  /* ---------- 6) المواعيد: اختيار سريع + "بعد قد إيه" ---------- */
  var wi = $('#whenInput'), wb = $('#whenBox');
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function local(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
  function when() {
    var m = $('.mode:checked'); m = m ? m.value : 'schedule';
    if (m !== 'schedule') return P.modes[m] || '';
    if (!wi || !wi.value) return '';
    var d = new Date(wi.value), diff = Math.round((d - new Date()) / 60000);
    if (isNaN(diff)) return '';
    var rel = diff < 0 ? P.past : diff < 60 ? P.inMin.replace('%s', diff) : diff < 1440 ? P.inHr.replace('%s', Math.round(diff / 60)) : P.inDay.replace('%s', Math.round(diff / 1440));
    return d.toLocaleString(document.documentElement.lang || undefined, { weekday: 'long', hour: 'numeric', minute: '2-digit' }) + ' · ' + rel;
  }
  if (wi && wb) {
    var q = document.createElement('div'); q.className = 'flex flex-wrap gap-1.5 mt-2';
    P.quick.forEach(function (o) { var b = document.createElement('button'); b.type = 'button'; b.className = 'rounded-full border border-ink-200 px-2.5 py-1 text-[11px] text-ink-700 hover:border-brand-400 hover:bg-brand-50 transition'; b.textContent = o[0];
      b.onclick = function () { var d = new Date(); if (o[1] === 'h1') d.setHours(d.getHours() + 1, 0, 0, 0); else if (o[1] === 'h3') d.setHours(d.getHours() + 3, 0, 0, 0); else { var a = o[1].split(':'); d.setDate(d.getDate() + (+a[0])); d.setHours(+a[1], 0, 0, 0); if (d < new Date()) d.setDate(d.getDate() + 1); } wi.value = local(d); wi.dispatchEvent(new Event('input', { bubbles: true })); wi.classList.add('ring-4', 'ring-brand-500/20'); setTimeout(function () { wi.classList.remove('ring-4', 'ring-brand-500/20'); }, 900); };
      q.appendChild(b); });
    wi.after(q);
    var rel = document.createElement('p'); rel.className = 'text-xs font-medium text-brand-700 mt-2'; q.after(rel);
    var paintRel = function () { rel.textContent = when(); var d = new Date(wi.value); rel.classList.toggle('!text-red-600', d < new Date()); };
    wi.addEventListener('input', paintRel); wi.addEventListener('change', paintRel); setInterval(paintRel, 30000); paintRel();
  }
  // أنماط النشر كبطاقات
  $$('.mode').forEach(function (r) { var l = r.closest('label'); l.classList.add('cp-mode'); r.classList.add('accent-brand-600'); });

  /* ---------- 7) ملخص قبل التنفيذ ---------- */
  var sb = $('#submitBtn'), sum = $('#cp-sum') || document.createElement('div');
  if (!$('#cp-sum')) { sum.className = 'rounded-xl bg-ink-50 border border-ink-200 p-3 text-xs text-ink-600 space-y-1'; if (sb) sb.parentNode.insertBefore(sum, sb.parentNode.firstChild); }
  function extras() { return $$('#cp-extra input').filter(function (i) { return i.value; }).length; }
  function steps(s, m, issues) {
    var okAcc = s.length > 0 || (m && m.value === 'draft'), okTxt = !!(content.value.trim() || files.length || $$('input[name="lib_media[]"]').length), okWhen = !(m && m.value === 'schedule' && wi && (!wi.value || new Date(wi.value) < new Date()));
    var st = { acc: okAcc, txt: okTxt && !issues.some(function (x) { return x !== P.past && x !== P.emptyText; }), when: okWhen };
    $$('#cp-steps .cp-st').forEach(function (b) {
      var ok = st[b.dataset.go];
      b.className = 'cp-st inline-flex items-center gap-1 rounded-full border px-2.5 py-1 transition ' + (ok ? 'border-lime-300 bg-lime-50 text-lime-800' : 'border-ink-200 text-ink-500 hover:border-brand-300');
      $('.cp-ic', b).textContent = ok ? '✓' : ({ acc: 1, txt: 2, when: 3 })[b.dataset.go];
    });
    var t = $('#cp-go-t'); if (t && m) { var n = 1 + (m.value === 'schedule' ? extras() : 0); t.textContent = m.value === 'schedule' && n > 1 ? P.goN.replace('%s', n) : (P.goT[m.value] || t.textContent); }
    return st.acc && st.txt && st.when;
  }
  $$('#cp-steps .cp-st').forEach(function (b) {
    b.onclick = function () { var el = b.dataset.go === 'acc' ? accBox : b.dataset.go === 'txt' ? content : (wi || $('.mode')); if (!el) return; el.scrollIntoView({ behavior: 'smooth', block: 'center' }); setTimeout(function () { (b.dataset.go === 'txt' ? content : el).focus && (b.dataset.go === 'txt' ? content : el).focus({ preventScroll: true }); }, 350); var card = el.closest('.card'); if (card) { card.classList.add('ring-4', 'ring-brand-500/20'); setTimeout(function () { card.classList.remove('ring-4', 'ring-brand-500/20'); }, 1200); } };
  });
  function summary() {
    var s = sel(), m = $('.mode:checked'), issues = [];
    s.forEach(function (c) { var l = LIM[c.dataset.plat]; if (l && Array.from(textFor(c)).length > l) issues.push((P.plat[c.dataset.plat] || '') + ': ' + P.tooLong.replace('%s', Array.from(textFor(c)).length - l)); });
    if (!content.value.trim() && !files.length) issues.push(P.emptyText);
    if (m && m.value === 'schedule' && wi && new Date(wi.value) < new Date()) issues.push(P.past);
    steps(s, m, issues);
    var ex = m && m.value === 'schedule' ? extras() : 0;
    sum.innerHTML = '<div class="flex items-center gap-1 flex-wrap"><b class="text-ink-900">' + esc(s.length ? P.onN.replace('%s', s.length) : P.pickAcc) + '</b>' + s.map(function (c) { return P.icons[c.dataset.plat] || ''; }).join('') + (m ? '<span class="text-ink-400 mx-1">·</span><span>' + esc(when() || P.modes[m.value] || '') + '</span>' : '') + (ex ? '<span class="rounded-full bg-brand-50 text-brand-700 px-1.5 text-[10px]">' + esc(P.extraN.replace('%s', ex)) + '</span>' : '') + '</div>' + issues.map(function (x) { return '<div class="text-red-600">' + uic('alert', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(x) + '</div>'; }).join('');
  }

  /* ---------- 8) مسودة تلقائية + Ctrl+Enter + تحذير الخروج ---------- */
  var DK = 'cp_draft_' + (P.ws || ''), sending = false, tm = null;
  function saveDraft() {
    clearTimeout(tm); tm = setTimeout(function () {
      if (!content.value.trim()) { store.set(DK, ''); $('#cp-draft').textContent = ''; return; }
      store.set(DK, JSON.stringify({ t: content.value, a: sel().map(function (c) { return c.value; }), at: Date.now() })); $('#cp-draft').textContent = '✎ ' + P.draftSaved;
    }, 500);
  }
  var saved = null; try { saved = JSON.parse(store.get(DK) || 'null'); } catch (e) {}
  if (saved && saved.t && !content.value.trim() && Date.now() - saved.at < 7 * 864e5) {
    var bn = document.createElement('div'); bn.className = 'mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm';
    bn.innerHTML = '<span class="flex-1 text-amber-900"></span><button type="button" class="r btn-primary !py-1.5 !text-xs"></button><button type="button" class="x btn-ghost !py-1.5 !text-xs"></button>';
    bn.querySelector('span').textContent = P.restore + ' «' + saved.t.slice(0, 60) + (saved.t.length > 60 ? '…' : '') + '»'; bn.querySelector('.r').textContent = P.restoreBtn; bn.querySelector('.x').textContent = P.discard;
    form.before(bn);
    bn.querySelector('.r').onclick = function () { content.value = saved.t; accs.forEach(function (c) { if (saved.a.indexOf(c.value) > -1 && !c.checked) { c.checked = true; c.dispatchEvent(new Event('change', { bubbles: true })); } }); content.dispatchEvent(new Event('input', { bubbles: true })); bn.remove(); content.focus(); };
    bn.querySelector('.x').onclick = function () { store.set(DK, ''); bn.remove(); };
  }
  var LK = 'cp_last_acc_' + (P.ws || '');
  form.addEventListener('submit', function (e) {
    var a = e.submitter, m = $('.mode:checked');
    if ((!a || a.value !== 'validate') && m && m.value === 'now' && !confirm(P.confirmNow)) { e.preventDefault(); return; }
    if (!a || a.value !== 'validate') { sending = true; store.set(DK, ''); store.set(LK, JSON.stringify(sel().map(function (c) { return c.value; }))); }
  });
  // الحسابات اللي استخدمتها آخر مرة بتتعلم لوحدها (لو الصفحة فاضية)
  (function () {
    if (sel().length) return;
    var last = null; try { last = JSON.parse(store.get(LK) || 'null'); } catch (e) {}
    if (!last || !last.length) return;
    var hit = accs.filter(function (c) { return last.indexOf(c.value) > -1; });
    if (!hit.length) return;
    hit.forEach(function (c) { c.checked = true; c.dispatchEvent(new Event('change', { bubbles: true })); });
    var n = document.createElement('div'); n.className = 'flex items-center gap-2 text-[11px] text-ink-500 mb-2';
    n.innerHTML = '<span>✓ ' + esc(P.lastAcc) + '</span><button type="button" class="link ms-auto">' + esc(P.clearAcc) + '</button>';
    n.querySelector('button').onclick = function () { hit.forEach(function (c) { c.checked = false; c.dispatchEvent(new Event('change', { bubbles: true })); }); n.remove(); };
    if (accBox) accBox.before(n);
  })();
  // "منشور تاني بنفس الحسابات" بعد النجاح
  var again = $('#cp-again'); if (again) again.onclick = function () { $('#cp-done').remove(); content.value = ''; content.dispatchEvent(new Event('input', { bubbles: true })); content.focus(); history.replaceState(null, '', location.pathname + '?p=compose'); };

  /* ---------- مواعيد إضافية لنفس المنشور ---------- */
  var xbox = $('#cp-extra');
  function addExtra(v) {
    if (!xbox || $$('input', xbox).length >= 60) return;
    if (v && (v === (wi && wi.value) || $$('input', xbox).some(function (i) { return i.value === v; }))) return; // مفيش ميعاد مكرر
    var row = document.createElement('div'); row.className = 'cp-x flex items-center gap-1.5';
    row.innerHTML = '<input type="datetime-local" name="extra_when[]" class="input !mt-0 !h-9 text-sm" dir="ltr"><button type="button" class="cp-x-del w-8 h-8 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" aria-label="✕">✕</button>';
    $('input', row).value = v || ''; xbox.appendChild(row); if (!v) $('input', row).focus(); summary();
  }
  if (xbox) {
    xbox.addEventListener('click', function (e) { var d = e.target.closest('.cp-x-del'); if (d) { d.closest('.cp-x').remove(); summary(); } });
    xbox.addEventListener('input', summary);
    $$('.cp-x-add').forEach(function (b) {
      b.onclick = function () {
        var base = wi && wi.value ? new Date(wi.value) : new Date(), last = $$('input', xbox).map(function (i) { return i.value ? new Date(i.value) : null; }).filter(Boolean).reduce(function (a, d) { return d > a ? d : a; }, base); // آخر ميعاد (مش آخر صف)
        var k = b.dataset.add, plus = function (d, days) { var x = new Date(d); x.setDate(x.getDate() + days); return local(x); };
        if (k === 'custom') addExtra(plus(last, 1));
        else if (k === 'd1') addExtra(plus(last, 1));
        else if (k === 'd7') addExtra(plus(last, 7));
        else if (k === 'w4') { for (var i = 1; i <= 4; i++) addExtra(plus(last, 7 * i)); } // بيكمّل بعد آخر ميعاد، فالضغطة التانية بتجيب الشهر اللي بعده
        b.classList.add('!border-brand-500', '!bg-brand-50'); setTimeout(function () { b.classList.remove('!border-brand-500', '!bg-brand-50'); }, 600);
      };
    });
    var rg = $('#cp-rep-go'), rgT = rg ? rg.textContent : '', rk = $('#cp-rep-k');
    if (rk) rk.addEventListener('change', function () { var d = rk.value === 'd'; $('#cp-rep-n').classList.toggle('hidden', !d); $('#cp-rep-u').classList.toggle('hidden', !d); });
    if (rg) rg.onclick = function () {
      var n = Math.max(1, Math.min(365, +$('#cp-rep-n').value || 1)), c = Math.max(1, Math.min(60, +$('#cp-rep-c').value || 1));
      var base = wi && wi.value ? new Date(wi.value) : new Date();
      xbox.innerHTML = ''; // التكرار بيبدل المواعيد الإضافية
      var kind = ($('#cp-rep-k') || {}).value || 'd';
      for (var i = 1; i < c; i++) {
        var x = new Date(base);
        if (kind === 'w') x.setDate(x.getDate() + 7 * i);
        else if (kind === 'm') { var dd = base.getDate(); x.setDate(1); x.setMonth(base.getMonth() + i); x.setDate(Math.min(dd, new Date(x.getFullYear(), x.getMonth() + 1, 0).getDate())); }
        else if (kind === 'mf') { x.setDate(1); x.setMonth(base.getMonth() + i); }
        else x.setDate(x.getDate() + n * i);
        addExtra(local(x));
      }
      summary(); rg.textContent = '✓'; setTimeout(function () { rg.textContent = rgT; }, 1200);
    };
  }
  window.addEventListener('beforeunload', function (e) { if (!sending && content.value.trim()) { e.preventDefault(); e.returnValue = ''; } });
  document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && sb) { e.preventDefault(); sb.click(); } });

  /* ---------- تشغيل ---------- */
  function all() { limits(); render(); summary(); if (typeof paintChips === 'function') paintChips(); drawMediaWarn(); }
  function drawMediaWarn() { if ($('#mediaPreview') && files.length) drawMedia(); }
  form.addEventListener('input', function (e) { if (e.target === content || /\[custom\]/.test(e.target.name || '')) { limits(); render(); summary(); saveDraft(); } if (e.target === wi) { render(); summary(); } });
  form.addEventListener('change', function (e) { if (e.target.classList.contains('acc') || e.target.classList.contains('mode')) { all(); saveDraft(); } });
  setInterval(function () { render(); summary(); }, 30000);
  // المعاينة بتتحدث مع صور المكتبة والصور المتولدة كمان
  ['libMedia', 'hf-results'].forEach(function (id) { var el = document.getElementById(id); if (el) new MutationObserver(function () { render(); }).observe(el, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-on'] }); });
  all();
})();

/* منشور جديد: أزرار ذكاء اصطناعي ظاهرة، دايرة الحروف، الحسابات المختارة فوق النص، والخانة بتكبر مع الكتابة */
(function () {
  var ta = document.getElementById('content'), ed = document.querySelector('.cp-editor'); if (!ta || !ed) return;
  var P = window.CPX || {}, bar = ed.querySelector('.border-t');
  var LIM = { twitter: 280, threads: 500, bluesky: 300, instagram: 2200, tiktok: 2200, linkedin: 3000, pinterest: 500, youtube: 5000, facebook: 63206, googlebusiness: 1500 };
  var ring = document.createElement('span'); ring.className = 'inline-flex items-center gap-1 text-[11px] tabular-nums text-ink-500'; ring.id = 'cp-ring';
  ring.innerHTML = '<svg viewBox="0 0 36 36" class="w-6 h-6 -rotate-90"><circle cx="18" cy="18" r="15" fill="none" stroke="currentColor" stroke-width="3" class="text-ink-100"/><circle cx="18" cy="18" r="15" fill="none" stroke-width="3" stroke-linecap="round" stroke-dasharray="94.25" stroke-dashoffset="94.25" class="cp-r" style="transition:stroke-dashoffset .3s,stroke .3s"/></svg><span class="cp-rn"></span>';
  if (bar) bar.appendChild(ring);
  function paintRing() {
    var acc = [].slice.call(document.querySelectorAll('.acc:checked')), lim = 0, pl = '';
    acc.forEach(function (c) { var l = LIM[c.dataset.plat]; if (l && (!lim || l < lim)) { lim = l; pl = c.dataset.plat; } });
    if (!lim) { ring.classList.add('hidden'); return; } ring.classList.remove('hidden');
    var n = Array.from(ta.value).length, left = lim - n, pct = Math.min(1, n / lim), c = ring.querySelector('.cp-r');
    c.setAttribute('stroke-dashoffset', String(94.25 * (1 - pct))); c.setAttribute('stroke', left < 0 ? '#dc2626' : pct > .9 ? '#d97706' : '#2563eb');
    ring.querySelector('.cp-rn').textContent = left < 50 ? left : ''; ring.title = ((P.plat && P.plat[pl]) || pl) + ': ' + n + ' / ' + lim;
  }
  var stack = document.getElementById('cp-stack');
  function q(v) { return String(v || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function paintStack() { if (!stack) return; var acc = [].slice.call(document.querySelectorAll('.acc:checked'));
    stack.innerHTML = acc.slice(0, 6).map(function (c) { var pic = c.dataset.pic ? '<img src="' + q(c.dataset.pic) + '" referrerpolicy="no-referrer" class="w-full h-full object-cover">' : '<span class="w-full h-full grid place-items-center text-[10px] font-bold text-brand-700 bg-brand-50">' + q((c.dataset.name || '?').charAt(0)) + '</span>';
      return '<span class="relative w-7 h-7 rounded-full ring-2 ring-white overflow-hidden bg-white cp-pop" title="' + q(c.dataset.name) + '">' + pic + '</span>'; }).join('') + (acc.length > 6 ? '<span class="w-7 h-7 rounded-full ring-2 ring-white bg-ink-100 text-[10px] grid place-items-center text-ink-600">+' + (acc.length - 6) + '</span>' : ''); }
  function aiRow() { if (!(window.aiRun && window.aiItems) || document.getElementById('cp-aiq')) return;
    var keys = ['improve', 'hook', 'shorten', 'hashtags', 'cta', 'translate_en'].filter(function (k) { return window.aiItems[k]; });
    if (keys.length) { var row = document.createElement('div'); row.className = 'flex flex-wrap items-center gap-1.5 px-3 pb-2.5'; row.id = 'cp-aiq';
      row.innerHTML = '<span class="text-[11px] font-bold bg-gradient-to-l from-brand-600 to-fuchsia-600 bg-clip-text text-transparent">✦ AI</span>' + keys.map(function (k) { return '<button type="button" data-aiq="' + k + '" class="rounded-full border border-brand-200 bg-brand-50 px-2.5 py-1 text-[11px] font-medium text-brand-700 hover:shadow-sm hover:-translate-y-px transition disabled:opacity-60">' + q(window.aiItems[k]) + '</button>'; }).join('');
      row.addEventListener('click', function (e) { var b = e.target.closest('[data-aiq]'); if (!b) return; if (!ta.value.trim()) { ta.focus(); ta.classList.add('animate-pulse'); setTimeout(function () { ta.classList.remove('animate-pulse'); }, 700); return; } window.aiRun(ta, b.dataset.aiq, b); });
      ed.appendChild(row); } }
  if (document.readyState === 'complete') aiRow(); else window.addEventListener('load', aiRow);
  function grow() { ta.style.height = 'auto'; ta.style.height = Math.min(520, Math.max(180, ta.scrollHeight + 2)) + 'px'; }
  ta.addEventListener('input', function () { paintRing(); grow(); });
  document.addEventListener('change', function (e) { if (e.target.classList && e.target.classList.contains('acc')) { paintRing(); paintStack(); } });
  setTimeout(function () { paintRing(); paintStack(); grow(); }, 50);
})();

/* نوع المنشور: منشور / ستوري / ريلز → بيتطبق على إعدادات كل حساب مختار */
(function () {
  var box = document.getElementById('cp-kind'); if (!box) return; var P = window.CPX || {}, note = document.getElementById('cp-kind-note');
  var MAP = { instagram: { post: '', story: 'story', reel: 'reel_only' }, facebook: { post: '', story: 'story', reel: 'reel' } };
  function apply() {
    var k = (box.querySelector('input:checked') || {}).value || 'post', bad = [];
    document.querySelectorAll('.acc:checked').forEach(function (c) {
      var m = MAP[c.dataset.plat], s = document.querySelector('select[name="opt[' + c.value + '][type]"]');
      if (m && s) s.value = m[k];
      else if (k !== 'post' && !(k === 'reel' && /tiktok|youtube/.test(c.dataset.plat))) { var n = (P.plat && P.plat[c.dataset.plat]) || c.dataset.plat; if (bad.indexOf(n) < 0) bad.push(n); }
    });
    note.textContent = bad.length ? (P.kNo || '').replace('%s', bad.join('، ')) : '';
    document.getElementById('content').dispatchEvent(new Event('input', { bubbles: true }));
  }
  box.addEventListener('change', apply);
  document.addEventListener('change', function (e) { if (e.target.classList && e.target.classList.contains('acc')) setTimeout(apply, 0); });
})();
