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
    var pl = $('#cb-plan') ? $('#cb-plan').value : '', q = $('#cb-q').value.trim().toLowerCase(), ty = $('#cb-type') ? $('#cb-type').value : '';
    return items.filter(function (i) {
      if (pl && i.plan_id != pl) return false;
      var media = i.media || [], car = (i.carousel || []).length;
      var hasImg = media.some(function (m) { return (m.kind || 'image') === 'image'; });
      var hasVid = media.some(function (m) { return m.kind === 'video'; });
      if (ty === 'img' && !hasImg) return false;
      if (ty === 'noimg' && media.length) return false;
      if (ty === 'vid' && !hasVid) return false;
      if (ty === 'car' && !car) return false;
      if (ty === 'text' && (media.length || car)) return false;
      if (q && ((i.linkedin || '') + ' ' + (i.x || '') + ' ' + ((i.topic || {}).title || '') + ' ' + (i.headline || '')).toLowerCase().indexOf(q) < 0) return false;
      return true;
    });
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
  function card(i) {
    var t = i.topic || {}, can = i.status !== 'published';
    var st = i.status === 'scheduled' ? fmt(T.s_scheduled, String(i.scheduled_at || '').slice(0, 16).replace('T', ' ')) : T['s_' + i.status];
    var pen = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4"/></svg>';
    var head = '<header class="flex items-start gap-2">' + (can ? '<input type="checkbox" class="cb-sel mt-1"' + (sel[i.id] ? ' checked' : '') + '>' : '')
      + '<button type="button" class="cb-view min-w-0 flex-1 text-start" title="' + esc(T.viewBtn) + '"><b class="block truncate">' + esc(t.title || '') + '</b><span class="text-[11px] text-ink-400">' + esc(C.plans[i.plan_id] || '') + '</span></button>'
      + '<span class="badge ' + (i.status === 'published' ? 'badge-green' : i.status === 'approved' ? '' : 'badge-amber') + '">' + esc(st) + '</span>'
      + (can ? '<button type="button" class="cb-editbtn shrink-0 text-ink-400 hover:text-brand-600 p-1 -m-1 rounded-lg" title="' + esc(T.editBtn) + '" aria-label="' + esc(T.editBtn) + '">' + pen + '</button>' : '') + '</header>';
    var err = i.pub_error ? '<p class="text-xs text-red-600">' + esc(i.pub_error) + '</p>' : '';
    return '<article class="cb-card card p-3 flex flex-col gap-2 min-w-0" data-id="' + i.id + '">' + head
      + '<div class="flex gap-3">' + mediaBlock(i, can) + '<p class="cb-view cursor-pointer text-sm text-ink-700 whitespace-pre-wrap break-words line-clamp-6 min-w-0" dir="auto">' + esc(i.linkedin || i.headline || '') + '</p></div>'
      + err + footer(i) + '</article>';
  }
  // نافذة منبثقة واحدة: عرض (قراءة منسّقة) + تعديل. تفتح على العرض، وزرار «تعديل» بيحوّلها.
  function openPost(it, edit) {
    if (!it) return; edit = !!edit && it.status !== 'published';
    var cp = '<svg fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M8 8h11v12H8zM5 16V4h11"/></svg>';
    var carBlk = function () { return (it.carousel || []).length ? '<div><div class="text-xs text-ink-500 mb-1">' + esc(T.car) + '</div>' + it.carousel.map(function (s, k) { return '<div class="text-xs text-ink-600 border-s-2 border-ink-200 ps-2 mb-1"><b>' + (k + 1) + '. ' + esc(s.role || '') + ':</b> ' + esc(s.title || '') + (s.body ? '<br>' + esc(s.body) : '') + '</div>'; }).join('') + '</div>' : ''; };
    var carEdit = function () { return (it.carousel || []).length ? '<div class="space-y-2"><div class="text-xs text-ink-500">' + esc(T.car) + '</div>' + it.carousel.map(function (s, k) { return '<div class="rounded-xl border border-ink-200 p-2 space-y-1" data-car="' + k + '"><div class="text-[11px] text-ink-400">' + (k + 1) + '. ' + esc(s.role || '') + '</div><input data-carf="title" value="' + esc(s.title || '') + '" maxlength="300" dir="auto" class="input !mt-0 !py-1 text-sm w-full" placeholder="' + esc(T.hl) + '"><textarea data-carf="body" rows="2" maxlength="2000" dir="auto" class="input !mt-0 text-sm w-full">' + esc(s.body || '') + '</textarea></div>'; }).join('') + '</div>' : ''; };
    var mediaView = function () { return (it.media || []).length ? '<div class="flex flex-wrap gap-2">' + it.media.map(function (m) { return '<img src="' + esc(m.thumb) + '" class="w-20 h-24 object-cover rounded-xl border border-ink-200" alt="">'; }).join('') + '</div>' : ''; };
    var d = document.createElement('dialog');
    d.className = 'cb-dlg w-[min(640px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl';
    document.body.appendChild(d);
    var close = function () { try { d.close(); } catch (e) {} d.remove(); };
    function editBody() {
      var fld = function (key, label, rows, max) { return '<label class="block"><span class="text-xs text-ink-500">' + esc(label) + '</span><textarea data-field="' + key + '" rows="' + rows + '" maxlength="' + max + '" dir="auto" class="input !mt-1 text-sm w-full">' + esc(it[key] || '') + '</textarea></label>'; };
      return fld('headline', T.hl, 1, 120) + fld('linkedin', T.li, 8, 5000) + fld('x', T.x, 3, 600) + fld('image_prompt', T.img, 3, 4000) + fld('video_prompt', T.vid, 3, 4000) + carEdit();
    }
    function viewBody() {
      var row = function (label, val) { return val ? '<div><div class="text-xs text-ink-500 mb-0.5">' + esc(label) + '</div><p class="text-sm text-ink-800 whitespace-pre-wrap break-words leading-relaxed" dir="auto">' + esc(val) + '</p></div>' : ''; };
      var chips = [['linkedin', T.li], ['x', T.x], ['image_prompt', T.img], ['video_prompt', T.vid], ['carousel', T.car]].filter(function (f) { return f[0] === 'carousel' ? (it.carousel || []).length : it[f[0]]; })
        .map(function (f) { return '<button type="button" class="cb-chip" data-dcopy="' + f[0] + '">' + cp + '<span>' + esc(f[1]) + '</span></button>'; }).join('');
      return mediaView() + row(T.hl, it.headline) + row(T.li, it.linkedin) + row(T.x, it.x) + row(T.img, it.image_prompt) + row(T.vid, it.video_prompt) + carBlk()
        + (chips ? '<div class="flex flex-wrap gap-1.5 pt-2 border-t border-ink-100">' + chips + '</div>' : '');
    }
    function foot() {
      if (edit) return '<span class="cb-msg text-xs text-lime-600 flex-1"></span><button type="button" class="btn-ghost cb-cancel">' + esc(T.cancel) + '</button><button type="button" class="btn-primary cb-save">' + esc(T.save2) + '</button>';
      return '<span class="flex-1"></span>' + (it.status !== 'published' ? '<button type="button" class="btn-ghost cb-toedit">' + esc(T.editBtn) + '</button>' : '') + '<button type="button" class="btn-primary cb-x">' + esc(T.close) + '</button>';
    }
    function render() {
      d.innerHTML = '<div class="flex flex-col max-h-[88vh]">'
        + '<div class="flex items-center gap-2 px-5 py-3.5 border-b border-ink-100"><b class="truncate flex-1">' + esc((it.topic || {}).title || (edit ? T.editBtn : '')) + '</b><button type="button" class="cb-x text-ink-400 hover:text-ink-700 w-8 h-8 rounded-lg grid place-items-center text-xl leading-none" aria-label="' + esc(T.close) + '">×</button></div>'
        + '<div class="p-5 space-y-3 overflow-y-auto">' + (edit ? editBody() : viewBody()) + '</div>'
        + '<div class="flex items-center gap-2 px-5 py-3.5 border-t border-ink-100">' + foot() + '</div></div>';
      $$('.cb-x', d).forEach(function (b) { b.onclick = close; });
      var ce = $('.cb-cancel', d); if (ce) ce.onclick = close;
      var te = $('.cb-toedit', d); if (te) te.onclick = function () { edit = true; render(); var f0 = $('textarea', d); if (f0) f0.focus(); };
      var sv = $('.cb-save', d); if (sv) sv.onclick = function () {
        var data = { action: 'edit', id: it.id }, changed = false, newCar = null;
        $$('textarea[data-field]', d).forEach(function (ta) { var k = ta.dataset.field; if ((it[k] || '') !== ta.value) { data[k] = ta.value; changed = true; } });
        var cars = $$('[data-car]', d);
        if (cars.length) {
          newCar = (it.carousel || []).map(function (s, k) { var box = cars[k]; if (!box) return s; var ti = $('[data-carf=title]', box), bo = $('[data-carf=body]', box); var o = {}; for (var kk in s) o[kk] = s[kk]; if (ti) o.title = ti.value; if (bo) o.body = bo.value; return o; });
          if (JSON.stringify(newCar) !== JSON.stringify(it.carousel || [])) { data.carousel = JSON.stringify(newCar); changed = true; }
        }
        if (!changed) { edit = false; render(); return; }
        sv.disabled = true;
        post(data).then(function (j) { sv.disabled = false; if (j && j.ok) { for (var k in data) if (k !== 'action' && k !== 'id' && k !== 'carousel') it[k] = data[k]; if (data.carousel && newCar) it.carousel = newCar; toast(T.saved); paint(); edit = false; render(); } else toast((j && j.error) || T.err, 'error'); }).catch(function () { sv.disabled = false; toast(T.offline, 'error'); });
      };
      $$('[data-dcopy]', d).forEach(function (b) { b.onclick = function () { var f = b.dataset.dcopy, v = f === 'carousel' ? it.carousel.map(function (s, k) { return (k + 1) + '. ' + s.role + ': ' + s.title + '\n' + s.body; }).join('\n\n') : it[f]; navigator.clipboard.writeText(v || '').then(function () { toast(T.copied); var sp = b.querySelector('span'), o = sp ? sp.textContent : null; b.classList.add('copied'); if (sp) sp.textContent = T.copied; setTimeout(function () { b.classList.remove('copied'); if (sp && o != null) sp.textContent = o; }, 1400); }); }; });
    }
    d.addEventListener('cancel', function (e) { e.preventDefault(); close(); });
    d.addEventListener('click', function (e) { if (e.target === d) close(); });
    render(); d.showModal(); if (edit) { var f0 = $('textarea', d); if (f0) f0.focus(); }
  }
  function paint() { var v = vis(); list.innerHTML = v.length ? v.map(card).join('') : '<p class="text-sm text-ink-500 p-6 col-span-full text-center">' + esc(T.empty) + '</p>'; bars(); }
  function ids() { return Object.keys(sel).map(Number); }
  function bars() { var n = ids().length; bar.classList.toggle('hidden', !n); $('#cb-n').textContent = fmt(T.sel, n); $('#cb-go').textContent = fmt(T.confirm, n); if (!n) $('#cb-sch').classList.add('hidden'); var ab = document.getElementById('cb-all'); if (ab) { var v = vis().filter(function (x) { return x.status !== 'published'; }); ab.textContent = (v.length && v.every(function (x) { return sel[x.id]; })) ? T.clearSel : T.all; } }
  ['cb-plan', 'cb-q', 'cb-type'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener(id === 'cb-q' ? 'input' : 'change', paint); });
  // تحديد الكل: بيحدّد/يلغي كل العناصر الظاهرة (حسب الفلتر الحالي) القابلة للتعديل
  var allBtn = document.getElementById('cb-all');
  if (allBtn) allBtn.onclick = function () {
    var v = vis().filter(function (x) { return x.status !== 'published'; });
    var allSel = v.length && v.every(function (x) { return sel[x.id]; });
    v.forEach(function (x) { if (allSel) delete sel[x.id]; else sel[x.id] = 1; });
    paint();
  };
  list.addEventListener('change', function (e) {
    var art = e.target.closest('article'); if (!art) return; var id = +art.dataset.id, it = items.find(function (x) { return x.id === id; });
    if (e.target.classList.contains('cb-sel')) { if (e.target.checked) sel[id] = 1; else delete sel[id]; return bars(); }
    if (e.target.classList.contains('cb-file') && e.target.files.length) { var fl = [].slice.call(e.target.files); upSeq(fl.map(function (f) { return [it, f]; })); }
  });
  list.addEventListener('click', function (e) {
    var art = e.target.closest('article'); if (!art) return; var id = +art.dataset.id, it = items.find(function (x) { return x.id === id; }), b;
    if ((b = e.target.closest('[data-copy]'))) { var f = b.dataset.copy, v = f === 'carousel' ? it.carousel.map(function (s, k) { return (k + 1) + '. ' + s.role + ': ' + s.title + '\n' + s.body + (s.visual ? '\n[' + s.visual + (s.layout ? ' | ' + s.layout : '') + ']' : ''); }).join('\n\n') : it[f]; var bb = b; navigator.clipboard.writeText(v || '').then(function () { toast(T.copied); var sp = bb.querySelector('span'), o = sp ? sp.textContent : null; bb.classList.add('copied'); if (sp) sp.textContent = T.copied; setTimeout(function () { bb.classList.remove('copied'); if (sp && o != null) sp.textContent = o; }, 1400); }); return; }
    if ((b = e.target.closest('[data-rmid]')) && it.media.length) { e.preventDefault(); post({ action: 'media', id: id, remove: +b.dataset.rmid }).then(function (j) { if (j.ok) { it.media = j.media; it.media_id = (j.media[0] || {}).id || null; paint(); } }); return; }
    if (e.target.closest('.cb-editbtn')) { openPost(it, true); return; } // تعديل في بوب اب
    if (e.target.closest('.cb-view')) { openPost(it, false); return; } // عرض في بوب اب
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
