/* مخزون المحتوى: فلترة، تحديد، إرفاق وسائط، نسخ برومبتات، نشر/طابور/جدولة فردية أو موزعة */
(function () {
  var C = window.CB; if (!C || !document.getElementById('cb-list')) return; var T = C.t, items = C.items, sel = {}, exp = {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fmt(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function post(d, file) { var fd = new FormData(); fd.append('_csrf', C.csrf); for (var k in d) fd.append(k, d[k]); if (file) fd.append('file', file); return fetch(C.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function ask(m) { return window.fxAsk ? fxAsk(m) : Promise.resolve(confirm(m)); }
  var list = $('#cb-list'), bar = $('#cb-bar');
  function vis() {
    var pl = $('#cb-plan') ? $('#cb-plan').value : '', q = $('#cb-q').value.trim().toLowerCase(), ni = $('#cb-noimg').checked;
    return items.filter(function (i) { return (!pl || i.plan_id == pl) && (!ni || !(i.media || []).length)
      && (!q || ((i.linkedin || '') + ' ' + (i.x || '') + ' ' + ((i.topic || {}).title || '')).toLowerCase().indexOf(q) >= 0); });
  }
  function mediaBlock(i, can) { // عمود الوسائط فقط (مغلق بذاته)
    return '<div class="shrink-0 w-24">' + ((i.media || []).length ? '<div class="flex flex-wrap gap-1">' + i.media.map(function (m) { return '<div class="relative"><img src="' + esc(m.thumb) + '" class="cb-thumb w-[46px] h-14 object-cover rounded-lg border border-ink-200" alt="">' + (can ? '<button type="button" data-rmid="' + m.id + '" class="absolute -top-1.5 -end-1.5 w-4 h-4 rounded-full bg-rose-600 text-[#fff] text-[11px] leading-none grid place-items-center shadow" title="' + esc(T.remove) + '" aria-label="' + esc(T.remove) + '">×</button>' : '') + '</div>'; }).join('') + '</div>' : '<div class="w-24 h-28 rounded-xl border border-dashed border-ink-300 grid place-items-center text-[11px] text-ink-400 text-center p-1">' + esc(i.headline || '') + '</div>')
      + (can ? '<label class="block text-center text-xs link mt-1 cursor-pointer"><input type="file" accept="image/*,video/*" multiple class="hidden cb-file">' + esc((i.media || []).length ? T.more : T.attach) + '</label>' : '') + '</div>';
  }
  function footer(i) {
    var cp = '<svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M8 8h11v12H8zM5 16V4h11"/></svg>';
    var btns = [['linkedin', T.li], ['x', T.x], ['image_prompt', T.img], ['video_prompt', T.vid], ['carousel', T.car]].filter(function (f) { return f[0] === 'carousel' ? (i.carousel || []).length : i[f[0]]; })
      .map(function (f) { return '<button type="button" class="cb-chip" data-copy="' + f[0] + '">' + cp + '<span>' + esc(f[1]) + '</span></button>'; }).join('');
    return btns ? '<footer class="flex flex-wrap gap-1.5 mt-auto pt-2 border-t border-ink-100">' + btns + '</footer>' : '';
  }
  function field(i, key, label, rows, max, can) {
    if (!can) return (i[key] ? '<div><div class="text-[11px] text-ink-500 mb-0.5">' + esc(label) + '</div><p class="text-sm text-ink-700 whitespace-pre-wrap break-words" dir="auto">' + esc(i[key]) + '</p></div>' : '');
    return '<label class="block"><span class="text-[11px] text-ink-500">' + esc(label) + '</span>'
      + '<textarea data-field="' + key + '" rows="' + rows + '" maxlength="' + max + '" dir="auto" class="input !mt-0.5 text-sm w-full" placeholder="—">' + esc(i[key] || '') + '</textarea></label>';
  }
  function card(i) {
    var t = i.topic || {}, can = i.status !== 'published', open = !!exp[i.id];
    var st = i.status === 'scheduled' ? fmt(T.s_scheduled, String(i.scheduled_at || '').slice(0, 16).replace('T', ' ')) : T['s_' + i.status];
    var head = '<header class="flex items-start gap-2">' + (can ? '<input type="checkbox" class="cb-sel mt-1"' + (sel[i.id] ? ' checked' : '') + '>' : '')
      + '<button type="button" class="cb-exp min-w-0 flex-1 text-start" aria-expanded="' + open + '" title="' + esc(open ? T.collapse : T.expand) + '"><b class="block truncate">' + esc(t.title || '') + '</b><span class="text-[11px] text-ink-400">' + esc(C.plans[i.plan_id] || '') + '</span></button>'
      + '<span class="badge ' + (i.status === 'published' ? 'badge-green' : i.status === 'approved' ? '' : 'badge-amber') + '">' + esc(st) + '</span>'
      + '<button type="button" class="cb-exp text-ink-400 hover:text-ink-700 shrink-0" aria-label="' + esc(open ? T.collapse : T.expand) + '"><svg class="w-4 h-4 transition-transform ' + (open ? 'rotate-180' : '') + '" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></button></header>';
    var err = i.pub_error ? '<p class="text-xs text-red-600">' + esc(i.pub_error) + '</p>' : '';
    if (!open) {
      return '<article class="cb-card card p-3 flex flex-col gap-2 min-w-0" data-id="' + i.id + '">' + head
        + '<div class="flex gap-3">' + mediaBlock(i, can) + '<p class="text-sm text-ink-700 whitespace-pre-wrap break-words line-clamp-6 min-w-0" dir="auto">' + esc(i.linkedin || i.headline || '') + '</p></div>'
        + err + footer(i) + '</article>';
    }
    // موسّع: تعديل inline
    var fields = field(i, 'headline', T.hl, 1, 120, can) + field(i, 'linkedin', T.li, 6, 5000, can) + field(i, 'x', T.x, 3, 600, can)
      + field(i, 'image_prompt', T.img, 2, 4000, can) + field(i, 'video_prompt', T.vid, 2, 4000, can);
    var car = (i.carousel || []).length ? '<div><div class="text-[11px] text-ink-500 mb-0.5">' + esc(T.car) + '</div>' + i.carousel.map(function (s, k) { return '<div class="text-xs text-ink-600 border-s-2 border-ink-200 ps-2 mb-1"><b>' + (k + 1) + '. ' + esc(s.role || '') + ':</b> ' + esc(s.title || '') + (s.body ? '<br>' + esc(s.body) : '') + '</div>'; }).join('') + '</div>' : '';
    return '<article class="cb-card is-open card p-3 flex flex-col gap-2 min-w-0" data-id="' + i.id + '">' + head
      + '<div class="flex gap-3">' + mediaBlock(i, can) + '<div class="flex-1 min-w-0 space-y-2">' + (can ? '<p class="text-[11px] text-ink-400">' + esc(T.editHint) + '</p>' : '') + fields + car
      + '<div class="cb-saved text-[11px] text-lime-600 h-3 leading-3"></div></div></div>'
      + err + footer(i) + '</article>';
  }
  function paint() { var v = vis(); list.innerHTML = v.length ? v.map(card).join('') : '<p class="text-sm text-ink-500 p-6 col-span-full text-center">' + esc(T.empty) + '</p>'; bars(); }
  function ids() { return Object.keys(sel).map(Number); }
  function bars() { var n = ids().length; bar.classList.toggle('hidden', !n); $('#cb-n').textContent = fmt(T.sel, n); $('#cb-go').textContent = fmt(T.confirm, n); if (!n) $('#cb-sch').classList.add('hidden'); }
  ['cb-plan', 'cb-q', 'cb-noimg'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener(id === 'cb-q' ? 'input' : 'change', paint); });
  list.addEventListener('change', function (e) {
    var art = e.target.closest('article'); if (!art) return; var id = +art.dataset.id, it = items.find(function (x) { return x.id === id; });
    if (e.target.classList.contains('cb-sel')) { if (e.target.checked) sel[id] = 1; else delete sel[id]; return bars(); }
    if (e.target.classList.contains('cb-file') && e.target.files.length) { var fl = [].slice.call(e.target.files); upSeq(fl.map(function (f) { return [it, f]; })); }
  });
  list.addEventListener('click', function (e) {
    var art = e.target.closest('article'); if (!art) return; var id = +art.dataset.id, it = items.find(function (x) { return x.id === id; }), b;
    if (e.target.closest('.cb-exp')) { exp[id] = !exp[id]; paint(); return; }
    if ((b = e.target.closest('[data-copy]'))) { var f = b.dataset.copy, v = f === 'carousel' ? it.carousel.map(function (s, k) { return (k + 1) + '. ' + s.role + ': ' + s.title + '\n' + s.body + (s.visual ? '\n[' + s.visual + (s.layout ? ' | ' + s.layout : '') + ']' : ''); }).join('\n\n') : it[f]; var bb = b; navigator.clipboard.writeText(v || '').then(function () { toast(T.copied); var sp = bb.querySelector('span'), o = sp ? sp.textContent : null; bb.classList.add('copied'); if (sp) sp.textContent = T.copied; setTimeout(function () { bb.classList.remove('copied'); if (sp && o != null) sp.textContent = o; }, 1400); }); }
    if ((b = e.target.closest('[data-rmid]')) && it.media.length) { e.preventDefault(); post({ action: 'media', id: id, remove: +b.dataset.rmid }).then(function (j) { if (j.ok) { it.media = j.media; it.media_id = (j.media[0] || {}).id || null; paint(); } }); }
  });
  // تعديل inline: يتحفظ لما تسيب الخانة (blur مابيعملش bubble فبنستخدم capture)
  list.addEventListener('blur', function (e) {
    var ta = e.target && e.target.matches && e.target.matches('textarea[data-field]') ? e.target : null; if (!ta) return;
    var art = ta.closest('article'); if (!art) return; var id = +art.dataset.id, it = items.find(function (x) { return x.id === id; }); if (!it) return;
    var f = ta.dataset.field, v = ta.value; if ((it[f] || '') === v) return;
    var d = { action: 'edit', id: id }; d[f] = v;
    post(d).then(function (j) { if (j && j.ok) { it[f] = v; var s = art.querySelector('.cb-saved'); if (s) { s.textContent = T.saved; setTimeout(function () { if (s) s.textContent = ''; }, 1600); } } else { toast((j && j.error) || T.err, 'error'); } }).catch(function () { toast(T.offline, 'error'); });
  }, true);
  // رفع متتابع (واحد ورا التاني): لو النت فصل في النص، اللي اترفع بيفضل محفوظ
  function upSeq(pairs) { var n = 0; (function next() { var pr = pairs.shift(); if (!pr) { paint(); if (n) toast(fmt(T.bulkDone, n)); return; }
    post({ action: 'media', id: pr[0].id }, pr[1]).then(function (j) { if (!j.ok) { toast(j.error || T.err, 'error'); return next(); } n++; pr[0].media = j.media; pr[0].media_id = (j.media[0] || {}).id || null; paint(); next(); }).catch(function () { paint(); toast(T.offline, 'error'); }); })(); }
  document.getElementById('cb-bulk').addEventListener('change', function () { var fs = [].slice.call(this.files), sl = items.filter(function (x) { return sel[x.id]; }); this.value = ''; if (!fs.length) return;
    if (!sl.length) { toast(T.bulkNone, 'error'); return; }
    var go = function () { upSeq(fs.slice(0, sl.length).map(function (f, k) { return [sl[k], f]; })); };
    (fs.length > sl.length ? ask(fmt(T.bulkExtra, fs.length, sl.length)) : Promise.resolve(true)).then(function (ok) { if (ok) go(); }); });
  function accs() { return $$('.cb-acc:checked').map(function (x) { return x.value; }); }
  function run(type, times) {
    var a = accs(), list2 = ids(); if (!a.length) return toast(T.noAcc, 'error');
    $$('button', bar).forEach(function (b) { b.disabled = true; });
    return post({ action: 'publish', type: type, ids: JSON.stringify(list2), accounts: JSON.stringify(a), times: JSON.stringify(times || []) }).then(function (j) {
      $$('button', bar).forEach(function (b) { b.disabled = false; });
      if (!j.ok) return toast(j.error || T.err, 'error');
      var ok = 0, bad = 0, gone = {}; j.results.forEach(function (r) { var it = items.find(function (x) { return x.id === r.id; }); if (!it) return;
        // نجح: اتجدول/اتحط في الطابور/اتنشر ⇒ يسيب المخزون ويبقى منشور عادي
        if (r.ok) { ok++; gone[r.id] = 1; delete sel[r.id]; } else { bad++; it.pub_error = r.error; } });
      items = items.filter(function (x) { return !gone[x.id]; });
      toast(fmt(T.done, ok, bad), bad ? 'error' : 'success'); paint();
    }).catch(function () { $$('button', bar).forEach(function (b) { b.disabled = false; }); toast(T.offline, 'error'); });
  }
  function noImgWarn() { return ids().some(function (id) { var it = items.find(function (x) { return x.id === id; }); return it && !(it.media || []).length; }); }
  bar.addEventListener('click', function (e) {
    var b = e.target.closest('[data-do]'); if (!b) return; var d = b.dataset.do;
    if (d === 'back') return post({ action: 'back', ids: JSON.stringify(ids()) }).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); items = items.filter(function (x) { return !sel[x.id] || x.status !== 'approved'; }); sel = {}; paint(); });
    if (d === 'sched') { $('#cb-sch').classList.toggle('hidden'); return; }
    var pre = noImgWarn() ? T.noImg + '\n\n' : '';
    if (d === 'now') return ask(pre + fmt(T.askNow, ids().length, accs().length)).then(function (ok) { if (ok) run('now'); });
    if (d === 'queue') return (pre ? ask(pre) : Promise.resolve(true)).then(function (ok) { if (ok) run('queue'); });
  });
  $$('[name=cbm]').forEach(function (r) { r.addEventListener('change', function () { $$('.cb-spread').forEach(function (x) { x.classList.toggle('hidden', r.value !== 'spread' || !r.checked); }); $('#cb-prev').textContent = ''; }); });
  function mode() { return ($$('[name=cbm]').find(function (r) { return r.checked; }) || {}).value; }
  function times() {
    var s = $('#cb-start').value; if (!s) { var el = $('#cb-start'); if (el) el.focus(); return Promise.reject(new Error(T.needStart || T.err)); }
    if (mode() === 'one') return Promise.resolve(ids().map(function () { return s.slice(0, 16) + ':00'; }));
    return post({ action: 'spread', ids: JSON.stringify(ids()), start: s, every: (+$('#cb-every').value || 1) * (+$('#cb-unit').value), from: $('#cb-from').value, to: $('#cb-to').value })
      .then(function (j) { if (!j.ok) throw new Error(j.error); return j.times; });
  }
  ['cb-start', 'cb-every', 'cb-unit', 'cb-from', 'cb-to'].forEach(function (id) { $('#' + id).addEventListener('change', function () { if (mode() !== 'spread' || !$('#cb-start').value) return;
    times().then(function (ts) { $('#cb-prev').textContent = T.preview + ': ' + ts.map(function (x) { return x.slice(5, 16).replace('T', ' '); }).join(' · '); }).catch(function (e) { $('#cb-prev').textContent = e.message; }); }); });
  $('#cb-go').addEventListener('click', function () { times().then(function (ts) { var pre = noImgWarn() ? T.noImg : ''; return (pre ? ask(pre) : Promise.resolve(true)).then(function (ok) { if (ok) return run('schedule', ts); }); }).catch(function (e) { toast(e.message || T.err, 'error'); }); });
  // الموعد الافتراضي محسوب بتوقيت مساحة العمل (CB.tz) لأن السيرفر بيفسّر الموعد بنفس التوقيت
  (function () { var el = $('#cb-start'); if (!el) return; var v;
    try { var p = new Intl.DateTimeFormat('en-CA', { timeZone: CB.tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date(Date.now() + 3600e3)).reduce(function (o, x) { o[x.type] = x.value; return o; }, {});
      v = p.year + '-' + p.month + '-' + p.day + 'T' + (p.hour === '24' ? '00' : p.hour) + ':00'; }
    catch (e) { var dt = new Date(Date.now() + 3600e3); dt.setMinutes(0, 0, 0); v = new Date(dt - dt.getTimezoneOffset() * 6e4).toISOString().slice(0, 16); }
    el.value = v; })();
  paint();
})();
