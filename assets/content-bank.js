/* مخزون المحتوى: فلترة، تحديد، إرفاق وسائط، نسخ برومبتات، نشر/طابور/جدولة فردية أو موزعة */
(function () {
  var C = window.CB; if (!C || !document.getElementById('cb-list')) return; var T = C.t, items = C.items, sel = {};
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
  function card(i) {
    var t = i.topic || {}, can = i.status !== 'published';
    var st = i.status === 'scheduled' ? fmt(T.s_scheduled, String(i.scheduled_at || '').slice(0, 16).replace('T', ' ')) : T['s_' + i.status];
    return '<article class="card p-3 flex flex-col gap-2 min-w-0" data-id="' + i.id + '"><header class="flex items-start gap-2">' + (can ? '<input type="checkbox" class="cb-sel mt-1"' + (sel[i.id] ? ' checked' : '') + '>' : '')
      + '<div class="min-w-0 flex-1"><b class="block truncate">' + esc(t.title || '') + '</b><span class="text-[11px] text-ink-400">' + esc(C.plans[i.plan_id] || '') + '</span></div><span class="badge ' + (i.status === 'published' ? 'badge-green' : i.status === 'approved' ? '' : 'badge-amber') + '">' + esc(st) + '</span></header>'
      + '<div class="flex gap-3"><div class="shrink-0 w-24">' + ((i.media || []).length ? '<div class="flex flex-wrap gap-1">' + i.media.map(function (m) { return '<div class="relative"><img src="' + esc(m.thumb) + '" class="w-[46px] h-14 object-cover rounded-lg border border-ink-200" alt="">' + (can ? '<button type="button" data-rmid="' + m.id + '" class="absolute -top-1.5 -end-1.5 w-4 h-4 rounded-full bg-rose-600 text-[#fff] text-[11px] leading-none grid place-items-center shadow" title="' + esc(T.remove) + '" aria-label="' + esc(T.remove) + '">×</button>' : '') + '</div>'; }).join('') + '</div>' : '<div class="w-24 h-28 rounded-xl border border-dashed border-ink-300 grid place-items-center text-[11px] text-ink-400 text-center p-1">' + esc(i.headline || '') + '</div>')
      + (can ? '<label class="block text-center text-xs link mt-1 cursor-pointer"><input type="file" accept="image/*,video/*" multiple class="hidden cb-file">' + esc((i.media || []).length ? T.more : T.attach) + '</label>' : '') + '</div>'
      + '<p class="text-sm text-ink-700 whitespace-pre-wrap break-words line-clamp-6 min-w-0" dir="auto">' + esc(i.linkedin) + '</p></div>'
      + (i.pub_error ? '<p class="text-xs text-red-600">' + esc(i.pub_error) + '</p>' : '')
      + '<footer class="flex flex-wrap gap-x-3 gap-y-1 text-xs mt-auto pt-1 border-t border-ink-100">' + [['linkedin', T.li], ['x', T.x], ['image_prompt', T.img], ['video_prompt', T.vid], ['carousel', T.car]].filter(function (f) { return f[0] === 'carousel' ? (i.carousel || []).length : i[f[0]]; })
        .map(function (f) { return '<button type="button" class="link" data-copy="' + f[0] + '">' + esc(T.copy + ' ' + f[1]) + '</button>'; }).join('') + '</footer></article>';
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
    if ((b = e.target.closest('[data-copy]'))) { var f = b.dataset.copy, v = f === 'carousel' ? it.carousel.map(function (s, k) { return (k + 1) + '. ' + s.role + ': ' + s.title + '\n' + s.body + (s.visual ? '\n[' + s.visual + (s.layout ? ' | ' + s.layout : '') + ']' : ''); }).join('\n\n') : it[f]; navigator.clipboard.writeText(v || '').then(function () { toast(T.copied); }); }
    if ((b = e.target.closest('[data-rmid]')) && it.media.length) { e.preventDefault(); post({ action: 'media', id: id, remove: +b.dataset.rmid }).then(function (j) { if (j.ok) { it.media = j.media; it.media_id = (j.media[0] || {}).id || null; paint(); } }); }
  });
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
    var s = $('#cb-start').value; if (!s) return Promise.reject(new Error(T.err));
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
