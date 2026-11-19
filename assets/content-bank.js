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
    var st = $('#cb-st').value, pl = $('#cb-plan') ? $('#cb-plan').value : '', q = $('#cb-q').value.trim().toLowerCase(), ni = $('#cb-noimg').checked;
    return items.filter(function (i) { return (st === '' || (st === 'open' ? i.status !== 'published' : i.status === st)) && (!pl || i.plan_id == pl) && (!ni || !i.media_id)
      && (!q || ((i.linkedin || '') + ' ' + (i.x || '') + ' ' + ((i.topic || {}).title || '')).toLowerCase().indexOf(q) >= 0); });
  }
  function card(i) {
    var t = i.topic || {}, can = i.status !== 'published';
    var st = i.status === 'scheduled' ? fmt(T.s_scheduled, String(i.scheduled_at || '').slice(0, 16)) : T['s_' + i.status];
    return '<article class="card p-3 flex flex-col gap-2 min-w-0" data-id="' + i.id + '"><header class="flex items-start gap-2">' + (can ? '<input type="checkbox" class="cb-sel mt-1"' + (sel[i.id] ? ' checked' : '') + '>' : '')
      + '<div class="min-w-0 flex-1"><b class="block truncate">' + esc(t.title || '') + '</b><span class="text-[11px] text-ink-400">' + esc(C.plans[i.plan_id] || '') + '</span></div><span class="badge ' + (i.status === 'published' ? 'badge-green' : i.status === 'approved' ? '' : 'badge-amber') + '">' + esc(st) + '</span></header>'
      + '<div class="flex gap-3"><div class="shrink-0 w-24">' + (i.thumb ? '<img src="' + esc(i.thumb) + '" class="w-24 h-28 object-cover rounded-xl border border-ink-200" alt="">' : '<div class="w-24 h-28 rounded-xl border border-dashed border-ink-300 grid place-items-center text-[11px] text-ink-400 text-center p-1">' + esc(i.headline || '') + '</div>')
      + (can ? '<label class="block text-center text-xs link mt-1 cursor-pointer"><input type="file" accept="image/*,video/*" class="hidden cb-file">' + esc(i.thumb ? T.change : T.attach) + '</label>' + (i.thumb ? '<button type="button" data-rm class="block w-full text-center text-[11px] text-ink-400">' + esc(T.remove) + '</button>' : '') : '') + '</div>'
      + '<p class="text-sm text-ink-700 whitespace-pre-wrap break-words line-clamp-6 min-w-0" dir="auto">' + esc(i.linkedin) + '</p></div>'
      + (i.pub_error ? '<p class="text-xs text-red-600">' + esc(i.pub_error) + '</p>' : '')
      + '<footer class="flex flex-wrap gap-x-3 gap-y-1 text-xs mt-auto pt-1 border-t border-ink-100">' + [['linkedin', T.li], ['x', T.x], ['image_prompt', T.img], ['video_prompt', T.vid], ['carousel', T.car]].filter(function (f) { return f[0] === 'carousel' ? (i.carousel || []).length : i[f[0]]; })
        .map(function (f) { return '<button type="button" class="link" data-copy="' + f[0] + '">' + esc(T.copy + ' ' + f[1]) + '</button>'; }).join('') + '</footer></article>';
  }
  function paint() { var v = vis(); list.innerHTML = v.length ? v.map(card).join('') : '<p class="text-sm text-ink-500 p-6 col-span-full text-center">' + esc(T.empty) + '</p>'; bars(); }
  function ids() { return Object.keys(sel).map(Number); }
  function bars() { var n = ids().length; bar.classList.toggle('hidden', !n); $('#cb-n').textContent = fmt(T.sel, n); $('#cb-go').textContent = fmt(T.confirm, n); if (!n) $('#cb-sch').classList.add('hidden'); }
  ['cb-st', 'cb-plan', 'cb-q', 'cb-noimg'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener(id === 'cb-q' ? 'input' : 'change', paint); });
  list.addEventListener('change', function (e) {
    var art = e.target.closest('article'); if (!art) return; var id = +art.dataset.id, it = items.find(function (x) { return x.id === id; });
    if (e.target.classList.contains('cb-sel')) { if (e.target.checked) sel[id] = 1; else delete sel[id]; return bars(); }
    if (e.target.classList.contains('cb-file') && e.target.files[0]) post({ action: 'media', id: id }, e.target.files[0]).then(function (j) { if (!j.ok) return toast(j.error || T.err, 'error'); it.media_id = j.media_id; it.thumb = j.thumb; paint(); }).catch(function () { toast(T.offline, 'error'); });
  });
  list.addEventListener('click', function (e) {
    var art = e.target.closest('article'); if (!art) return; var id = +art.dataset.id, it = items.find(function (x) { return x.id === id; }), b;
    if ((b = e.target.closest('[data-copy]'))) { var f = b.dataset.copy, v = f === 'carousel' ? it.carousel.map(function (s, k) { return (k + 1) + '. ' + s.role + ': ' + s.title + '\n' + s.body + (s.visual ? '\n[' + s.visual + ']' : ''); }).join('\n\n') : it[f]; navigator.clipboard.writeText(v || '').then(function () { toast(T.copied); }); }
    if (e.target.closest('[data-rm]')) post({ action: 'media', id: id, media_id: 0 }).then(function (j) { if (j.ok) { it.media_id = null; it.thumb = null; paint(); } });
  });
  function accs() { return $$('.cb-acc:checked').map(function (x) { return x.value; }); }
  function run(type, times) {
    var a = accs(), list2 = ids(); if (!a.length) return toast(T.noAcc, 'error');
    $$('button', bar).forEach(function (b) { b.disabled = true; });
    return post({ action: 'publish', type: type, ids: JSON.stringify(list2), accounts: JSON.stringify(a), times: JSON.stringify(times || []) }).then(function (j) {
      $$('button', bar).forEach(function (b) { b.disabled = false; });
      if (!j.ok) return toast(j.error || T.err, 'error');
      var ok = 0, bad = 0; j.results.forEach(function (r) { var it = items.find(function (x) { return x.id === r.id; }); if (!it) return;
        if (r.ok) { ok++; it.status = r.status; it.pub_error = null; if (times) it.scheduled_at = (times[list2.indexOf(r.id)] || '').replace('T', ' '); delete sel[r.id]; } else { bad++; it.pub_error = r.error; } });
      toast(fmt(T.done, ok, bad), bad ? 'error' : 'success'); paint();
    }).catch(function () { $$('button', bar).forEach(function (b) { b.disabled = false; }); toast(T.offline, 'error'); });
  }
  function noImgWarn() { return ids().some(function (id) { var it = items.find(function (x) { return x.id === id; }); return it && !it.media_id; }); }
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
  var dt = new Date(Date.now() + 3600e3); dt.setMinutes(0, 0, 0); $('#cb-start').value = new Date(dt - dt.getTimezoneOffset() * 6e4).toISOString().slice(0, 16);
  paint();
})();
