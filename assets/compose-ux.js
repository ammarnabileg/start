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
  if (cc) cc.closest('.flex').after(meter);
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
  var bar = document.createElement('div'); bar.className = 'flex flex-wrap items-center gap-1 mt-2';
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
    accs.forEach(function (c) { var l = c.closest('label'); l.className = 'flex items-center gap-3 p-2 rounded-xl border border-transparent cursor-pointer transition hover:bg-ink-50 has-[:checked]:border-brand-300 has-[:checked]:bg-brand-50/70'; });
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
  pv.innerHTML = '<div class="flex items-center gap-2 px-4 h-11 border-b border-ink-100"><b class="text-sm text-ink-900 flex-1"></b><div class="flex gap-1 overflow-x-auto" id="cp-tabs" style="scrollbar-width:none"></div></div><div id="cp-card" class="p-4 bg-ink-50/60"></div>';
  pv.querySelector('b').textContent = P.preview;
  content.closest('.card').after(pv);
  var tab = null;
  function fmt(t) { return esc(t).replace(/(#[\p{L}\p{N}_]+)/gu, '<span class="text-brand-600">$1</span>').replace(/(https?:\/\/[^\s<]+)/g, '<span class="text-brand-600 underline">$1</span>').replace(/(@[\w.]+)/g, '<span class="text-brand-600">$1</span>'); }
  function render() {
    var s = sel(), tabs = $('#cp-tabs'), card = $('#cp-card'); if (!card) return;
    if (!s.length) { tabs.innerHTML = ''; card.innerHTML = '<p class="text-xs text-ink-500 text-center py-6">' + esc(P.pickAcc) + '</p>'; return; }
    if (!tab || !s.some(function (c) { return c.value === tab; })) tab = s[0].value;
    tabs.innerHTML = '';
    s.forEach(function (c) { var b = document.createElement('button'); b.type = 'button'; b.className = 'shrink-0 w-7 h-7 rounded-lg flex items-center justify-center transition ' + (c.value === tab ? 'bg-brand-50 ring-2 ring-brand-500/30' : 'hover:bg-ink-100 opacity-60'); b.innerHTML = P.icons[c.dataset.plat] || ''; b.title = c.dataset.name + ' · ' + (P.plat[c.dataset.plat] || ''); b.onclick = function () { tab = c.value; render(); }; tabs.appendChild(b); });
    var c = s.filter(function (x) { return x.value === tab; })[0], p = c.dataset.plat, txt = textFor(c), fold = FOLD[p], long = fold && Array.from(txt).length > fold;
    var shown = long ? Array.from(txt).slice(0, fold).join('') + '… ' : txt, lim = LIM[p], over = lim && Array.from(txt).length > lim;
    var imgs = files.filter(function (f) { return /^(image|video)\//.test(f.type); }).slice(0, 4);
    var media = imgs.length ? '<div class="grid gap-0.5 ' + (imgs.length > 1 ? 'grid-cols-2' : '') + ' mt-3 -mx-4 overflow-hidden">' + imgs.map(function (f) { var u = URL.createObjectURL(f); return /^video/.test(f.type) ? '<video src="' + u + '" class="w-full aspect-square object-cover bg-ink-900" muted></video>' : '<img src="' + u + '" class="w-full ' + (imgs.length > 1 ? 'aspect-square' : 'max-h-72') + ' object-cover">'; }).join('') + '</div>' : '';
    var av = c.dataset.pic ? '<img src="' + esc(c.dataset.pic) + '" class="w-9 h-9 rounded-full object-cover" referrerpolicy="no-referrer">' : '<span class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-600 to-cyan-500"></span>';
    var head = '<div class="flex items-center gap-2.5">' + av + '<div class="min-w-0 flex-1"><b class="block text-sm text-ink-900 truncate">' + esc(c.dataset.name) + '</b><span class="text-[11px] text-ink-500">' + esc(when()) + ' · ' + (P.icons[p] || '') + '</span></div></div>';
    var body = txt.trim() ? '<p class="text-sm text-ink-800 whitespace-pre-wrap break-words mt-3" dir="auto">' + fmt(shown) + (long ? '<span class="text-ink-500 font-medium">' + esc(P.more) + '</span>' : '') + '</p>' : '<p class="text-sm text-ink-400 italic mt-3">' + esc(P.emptyText) + '</p>';
    var foot = '<div class="flex items-center justify-around mt-3 pt-2 border-t border-ink-100 text-[11px] text-ink-500"><span>♡ ' + esc(P.like) + '</span><span>' + uic('chat', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(P.comment) + '</span><span>↗ ' + esc(P.share) + '</span></div>';
    var custom = textFor(c) !== content.value ? '<p class="mt-2 text-[11px] text-brand-700">' + uic('pencil', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(P.customText) + '</p>' : '';
    card.innerHTML = '<div class="max-w-md mx-auto rounded-2xl bg-white border border-ink-200 shadow-sm p-4">' + head + body + media + foot + '</div>' + custom + (over ? '<p class="mt-2 text-[11px] text-red-600">' + esc(P.tooLong.replace('%s', Array.from(txt).length - lim)) + '</p>' : '');
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
  $$('.mode').forEach(function (r) { var l = r.closest('label'); l.className = 'flex items-center gap-2 text-sm rounded-xl border border-ink-200 px-3 py-2 cursor-pointer transition hover:bg-ink-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800 has-[:checked]:font-medium'; r.classList.add('accent-brand-600'); });

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
    var row = document.createElement('div'); row.className = 'cp-x flex items-center gap-1.5';
    row.innerHTML = '<input type="datetime-local" name="extra_when[]" class="input !mt-0 !h-9 text-sm" dir="ltr"><button type="button" class="cp-x-del w-8 h-8 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" aria-label="✕">✕</button>';
    $('input', row).value = v || ''; xbox.appendChild(row); if (!v) $('input', row).focus(); summary();
  }
  if (xbox) {
    xbox.addEventListener('click', function (e) { var d = e.target.closest('.cp-x-del'); if (d) { d.closest('.cp-x').remove(); summary(); } });
    xbox.addEventListener('input', summary);
    $$('.cp-x-add').forEach(function (b) {
      b.onclick = function () {
        var base = wi && wi.value ? new Date(wi.value) : new Date(), last = $$('input', xbox).map(function (i) { return i.value ? new Date(i.value) : null; }).filter(Boolean).pop() || base;
        var k = b.dataset.add, plus = function (d, days) { var x = new Date(d); x.setDate(x.getDate() + days); return local(x); };
        if (k === 'custom') addExtra(plus(last, 1));
        else if (k === 'd1') addExtra(plus(last, 1));
        else if (k === 'd7') addExtra(plus(last, 7));
        else if (k === 'w4') { for (var i = 1; i <= 4; i++) addExtra(plus(base, 7 * i)); }
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
  all();
})();
