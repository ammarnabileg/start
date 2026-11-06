/* المنشورات: بحث وفلترة بالمنصة، وقت نسبي، تحديد وحذف من غير تحميل، نسخ النص، تقويم شهري، اختصارات */
(function () {
  var X = window.PSX; if (!X) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var toast = function (m, ok) { if (window.aiToast) aiToast(m, ok ? 'ok' : undefined); };
  var lang = document.documentElement.lang || undefined;

  /* ---------- وقت نسبي ---------- */
  function rel(at) {
    var d = new Date(at); if (isNaN(d)) return ''; var m = Math.round((d - new Date()) / 60000), a = Math.abs(m);
    var s = a < 1 ? null : a < 60 ? X.min.replace('%s', a) : a < 1440 ? X.hr.replace('%s', Math.round(a / 60)) : X.day.replace('%s', Math.round(a / 1440));
    return s === null ? X.now : (m > 0 ? X.future : X.past).replace('%s', s);
  }
  function paintTimes() { $$('.ps-when').forEach(function (el) { var r = rel(el.dataset.at); if (r) { el.textContent = r; el.classList.toggle('text-brand-700', el.dataset.st === 'scheduled'); el.classList.toggle('font-medium', el.dataset.st === 'scheduled'); } }); }
  paintTimes(); setInterval(paintTimes, 60000);

  /* ---------- بحث + فلترة بالمنصة ---------- */
  var q = $('#ps-q'), pf = null, ftag = null, facc = null;
  function apply() {
    var v = q ? q.value.trim().toLowerCase() : '', n = 0;
    $$('.ps-card').forEach(function (c) { var hit = (!v || c.dataset.q.indexOf(v) > -1) && (!pf || (' ' + c.dataset.pf + ' ').indexOf(' ' + pf + ' ') > -1)
      && (!ftag || ('|' + (c.dataset.tags || '') + '|').indexOf('|' + ftag + '|') > -1) && (!facc || (' ' + (c.dataset.acc || '') + ' ').indexOf(' ' + facc + ' ') > -1);
      c.style.display = hit ? '' : 'none'; if (hit) n++; });
    var em = $('#ps-empty'); if (em) em.classList.toggle('hidden', n > 0 || !$$('.ps-card').length);
    // عنوان اليوم بيختفي لو مفيش ولا منشور ظاهر تحته
    $$('.ps-day').forEach(function (h) { var e = h.nextElementSibling, any = false; while (e && !e.classList.contains('ps-day')) { if (e.classList.contains('ps-card') && e.style.display !== 'none') { any = true; break; } e = e.nextElementSibling; } h.style.display = any ? '' : 'none'; });
    if (window.__cal) window.__cal();
  }
  if (q) q.addEventListener('input', apply);
  function chipPaint(sel, attr, val) { $$(sel).forEach(function (x) { var on = x.dataset[attr] === val; x.classList.toggle('ring-2', on); x.classList.toggle('ring-brand-400', on); }); }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('#ps-af [data-tag]'); if (t) { ftag = ftag === t.dataset.tag ? null : t.dataset.tag; chipPaint('#ps-af [data-tag]', 'tag', ftag); apply(); return; }
    var a = e.target.closest('#ps-af [data-acc]'); if (a) { facc = facc === a.dataset.acc ? null : a.dataset.acc; chipPaint('#ps-af [data-acc]', 'acc', facc); apply(); }
  });
  $$('#ps-pf [data-pf]').forEach(function (b) { b.addEventListener('click', function () { pf = pf === b.dataset.pf ? null : b.dataset.pf; $$('#ps-pf [data-pf]').forEach(function (x) { var on = x.dataset.pf === pf; x.classList.toggle('border-brand-500', on); x.classList.toggle('bg-brand-50', on); x.classList.toggle('text-brand-700', on); }); apply(); }); });

  /* ---------- تحديد + حذف ---------- */
  var sel = new Set();
  function bar() { var b = $('#ps-bar'); if (!b) return; b.classList.toggle('hidden', !sel.size); $('#ps-n').textContent = sel.size; $$('.ps-sel').forEach(function (c) { c.classList.toggle('!opacity-100', sel.size > 0); }); $$('.ps-card').forEach(function (c) { c.classList.toggle('ring-2', sel.has(c.dataset.id)); c.classList.toggle('ring-brand-500', sel.has(c.dataset.id)); }); }
  document.addEventListener('change', function (e) { var c = e.target.closest('.ps-sel'); if (!c) return; var id = c.closest('.ps-card').dataset.id; c.checked ? sel.add(id) : sel.delete(id); bar(); });
  var clr = $('#ps-clear'); if (clr) clr.onclick = function () { sel.clear(); $$('.ps-sel').forEach(function (c) { c.checked = false; }); bar(); };
  function del(ids) {
    var fd = new FormData(); fd.append('action', 'delete_json'); ids.forEach(function (i) { fd.append('ids[]', i); });
    return fetch(location.pathname + '?p=posts', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.error) throw new Error(d.error);
      (d.ok || []).forEach(function (id) { var c = $('.ps-card[data-id="' + CSS.escape(id) + '"]'); sel.delete(id); if (c) { c.style.transition = 'all .3s'; c.style.opacity = '0'; c.style.transform = 'scale(.95)'; setTimeout(function () { c.remove(); apply(); }, 300); } });
      var f = Object.values(d.failed || {}); if (f.length) toast(f[0]); else toast(X.deleted, true); bar();
    }).catch(function (er) { toast(er.message || X.err); });
  }
  document.addEventListener('click', function (e) {
    var d = e.target.closest('.ps-del'); if (d) { if (confirm(X.del)) { d.disabled = true; del([d.closest('.ps-card').dataset.id]).finally(function () { d.disabled = false; }); } return; }
    var c = e.target.closest('.ps-copy'); if (c) { var t = c.closest('.ps-card').querySelector('.ps-txt'); navigator.clipboard && navigator.clipboard.writeText(t ? t.textContent : ''); var o = c.textContent; c.textContent = '✓ ' + X.copied; setTimeout(function () { c.textContent = o; }, 1200); }
  });
  function psQuick(op, ids, extra) {
    var fd = new FormData(); fd.append('action', 'quick'); fd.append('op', op); ids.forEach(function (i) { fd.append('ids[]', i); });
    Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
    return fetch(location.pathname + '?p=posts', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }).then(function (d) { if (d.error) throw new Error(d.error); return d; });
  }
  function pad2(n) { return (n < 10 ? '0' : '') + n; }
  function localDT(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) + 'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes()); }
  // الوقت بتوقيت الشركة (مش توقيت جهاز المستخدم) — السيرفر بيفهم المواعيد بتوقيت الشركة
  function siteParts(d) {
    try {
      var o = {}; new Intl.DateTimeFormat('en-GB', { timeZone: X.tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(d).forEach(function (p) { o[p.type] = p.value; });
      return { y: +o.year, m: +o.month, d: +o.day, h: +o.hour % 24, mi: +o.minute };
    } catch (e) { return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate(), h: d.getHours(), mi: d.getMinutes() }; }
  }
  function siteDT(d) { var p = siteParts(d); return p.y + '-' + pad2(p.m) + '-' + pad2(p.d) + 'T' + pad2(p.h) + ':' + pad2(p.mi); }
  window.__siteDT = siteDT;
  var bd = $('#ps-bulkdel'); if (bd) bd.onclick = function () { if (sel.size && confirm(X.delN.replace('%s', sel.size))) del(Array.from(sel)); };

  /* ---------- التقويم ---------- */
  var grid = $('#cal-grid');
  if (grid && X.view === 'cal') {
    var cur = new Date(); cur.setDate(1); cur.setHours(0, 0, 0, 0);
    var dow = $('#cal-dow'), start = X.weekStart; // أول يوم في الأسبوع حسب دولة الشركة
    for (var i = 0; i < 7; i++) { var dd = new Date(2024, 0, 7 + ((start + i) % 7)); var c = document.createElement('div'); c.className = 'px-2 py-2 text-center'; c.textContent = dd.toLocaleDateString(lang, { weekday: 'short' }); dow.appendChild(c); }
    var dot = { scheduled: 'bg-brand-500', published: 'bg-lime-500', failed: 'bg-red-500', partial: 'bg-amber-500', draft: 'bg-ink-400' };
    var key = function (d) { return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate(); };
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var calV = 'month'; try { calV = localStorage.getItem('cal-v') || 'month'; } catch (e) {}
    var wk = new Date(); wk.setHours(0, 0, 0, 0); wk.setDate(wk.getDate() - ((wk.getDay() - start + 7) % 7));
    var weekBox = $('#cal-week');
    // الفترة المتحمّلة من السيرفر؛ لو اللي قدامك برّاها بنجيبها ونضيفها
    var loaded = X.calRange ? [X.calRange[0], X.calRange[1]] : null, loading = false;
    function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function need() {
      var a, b;
      if (calV === 'week') { a = new Date(wk); b = new Date(wk); b.setDate(b.getDate() + 6); }
      else { a = new Date(cur); a.setDate(1 - ((a.getDay() - start + 7) % 7)); b = new Date(a); b.setDate(b.getDate() + 41); }
      return [ymd(a), ymd(b)];
    }
    function ensure() {
      if (!loaded || loading) return;
      var n = need(); if (n[0] >= loaded[0] && n[1] <= loaded[1]) return;
      var from = n[0] < loaded[0] ? n[0] : loaded[1], to = n[1] > loaded[1] ? n[1] : loaded[0];
      if (from > to) { var x = from; from = to; to = x; }
      if (loaded[0] === '2000-01-01') { from = n[0]; to = n[1]; }
      loading = true; var gb = $('#cal-gaps'); if (gb) gb.textContent = (X.q || {}).calLoading || '…';
      fetch(X.calUrl + '&from=' + from + '&to=' + to, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        loading = false; if (!j.ok) return;
        var byId = {}; X.data.forEach(function (p, i) { byId[p.id] = i; });
        // المجدول في الفترة دي بيتبدّل باللي جاي من Zernio (الأحدث)
        X.data = X.data.filter(function (p) { if (p.st !== 'scheduled') return true; var k = siteDT(new Date(p.at)).slice(0, 10); return k < j.from || k > j.to; }).concat(j.items);
        if (loaded[0] === '2000-01-01') loaded = [j.from, j.to]; else { if (j.from < loaded[0]) loaded[0] = j.from; if (j.to > loaded[1]) loaded[1] = j.to; }
        draw();
      }).catch(function () { loading = false; });
    }
    function draw() {
      ensure();
      $$('.cal-v').forEach(function (b) { var on = b.dataset.calv === calV; b.classList.toggle('bg-white', on); b.classList.toggle('shadow-sm', on); b.classList.toggle('font-medium', on); b.setAttribute('aria-pressed', on); });
      var w = calV === 'week' && weekBox; grid.classList.toggle('hidden', w); dow.classList.toggle('hidden', w); if (weekBox) weekBox.classList.toggle('hidden', !w);
      return w ? drawWeek() : drawMonth();
    }
    var cAcc = '';
    function filtered() { var v = q ? q.value.trim().toLowerCase() : ''; return X.data.filter(function (p) { if (v && p.txt.toLowerCase().indexOf(v) < 0) return false; if (pf && p.pf.indexOf(pf) < 0) return false; if (cAcc && (p.ac || []).indexOf(cAcc) < 0) return false; return !isNaN(new Date(p.at)); }); }
    function drawWeek() {
      var days = []; for (var i = 0; i < 7; i++) { var d = new Date(wk); d.setDate(wk.getDate() + i); days.push(d); }
      var end = days[6];
      $('#cal-title').textContent = days[0].toLocaleDateString(lang, { day: 'numeric', month: 'short' }) + ' – ' + end.toLocaleDateString(lang, { day: 'numeric', month: 'short', year: 'numeric' });
      var by = {}; filtered().forEach(function (p) { var sp = siteParts(new Date(p.at)), k = sp.y + '-' + sp.m + '-' + sp.d + '@' + sp.h; (by[k] = by[k] || []).push(p); });
      var today = key(new Date()), now = new Date(), rtl = document.documentElement.dir === 'rtl', order = rtl ? days.slice().reverse() : days;
      var h = '<table class="w-full table-fixed text-[11px] border-collapse"><thead class="sticky top-0 z-[2] bg-ink-50"><tr>' + (rtl ? '' : '<th class="w-14"></th>') +
        order.map(function (d) { return '<th class="py-2 font-medium ' + (key(d) === today ? 'text-brand-700' : 'text-ink-500') + '" dir="auto">' + esc(d.toLocaleDateString(lang, { weekday: 'short' })) + ' <span class="tabular-nums">' + d.getDate() + '</span></th>'; }).join('') + (rtl ? '<th class="w-14"></th>' : '') + '</tr></thead><tbody>';
      for (var hr = 0; hr < 24; hr++) {
        var lbl = '<td class="align-top text-end pe-2 text-[10px] text-ink-400 tabular-nums border-t border-ink-100">' + pad(hr) + ':00</td>';
        h += '<tr class="h-12">' + (rtl ? '' : lbl) + order.map(function (d) {
          var k = key(d) + '@' + hr, items = (by[k] || []).sort(function (a, b) { return new Date(a.at) - new Date(b.at); }), slot = new Date(d); slot.setHours(hr + 1);
          return '<td data-wday="' + d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate() + '" data-hour="' + hr + '" class="align-top border-t border-s border-ink-100 p-0.5 ' + (slot < now ? 'bg-ink-50/50' : 'hover:bg-brand-50/40') + '">' +
            items.map(function (p) { return '<a href="' + esc(X.edit.replace('__ID__', encodeURIComponent(p.id))) + '" ' + (p.mv && X.canDel ? 'draggable="true" data-pid="' + esc(p.id) + '" ' : '') + 'class="block truncate rounded px-1 py-0.5 mb-0.5 text-ink-700 bg-white border border-ink-200 hover:border-brand-300' + (p.mv && X.canDel ? ' cursor-grab' : '') + '" title="' + esc(p.txt) + '" dir="auto"><span class="inline-block w-1.5 h-1.5 rounded-full me-1 ' + (dot[p.st] || 'bg-ink-300') + '"></span>' + esc(p.txt || '—') + '</a>'; }).join('') + '</td>';
        }).join('') + (rtl ? lbl : '') + '</tr>';
      }
      weekBox.innerHTML = h + '</tbody></table>';
      if (!weekBox.dataset.scrolled) { weekBox.scrollTop = 8 * 48; weekBox.dataset.scrolled = '1'; }
    }
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function drawMonth() {
      var v = q ? q.value.trim().toLowerCase() : '', by = {};
      X.data.forEach(function (p) { if (v && p.txt.toLowerCase().indexOf(v) < 0) return; if (pf && p.pf.indexOf(pf) < 0) return; if (cAcc && (p.ac || []).indexOf(cAcc) < 0) return; var d = new Date(p.at); if (isNaN(d)) return; var sp = siteParts(d), kk = sp.y + '-' + sp.m + '-' + sp.d; (by[kk] = by[kk] || []).push(p); });
      $('#cal-title').textContent = cur.toLocaleDateString(lang, { month: 'long', year: 'numeric' });
      // أيام فاضية في الأسبوعين الجايين (مفيش ولا منشور مجدول)
      var gapSet = {}, gapN = 0, t0 = new Date(); t0.setHours(0, 0, 0, 0);
      for (var gi = 0; gi < 14; gi++) { var gd = new Date(t0); gd.setDate(t0.getDate() + gi); var gk = key(gd); var has = (by[gk] || []).some(function (p) { return p.st === 'scheduled'; }); if (!has) { gapSet[gk] = 1; gapN++; } }
      var gb = $('#cal-gaps'); if (!gb) { gb = document.createElement('span'); gb.id = 'cal-gaps'; gb.className = 'text-[11px] rounded-full px-2 py-0.5'; $('#cal-title').after(gb); }
      gb.textContent = gapN ? (X.q.gaps || '').replace('%s', gapN) : (X.q.noGaps || ''); gb.className = 'text-[11px] rounded-full px-2 py-0.5 ' + (gapN ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-lime-50 text-lime-700 border border-lime-200');
      grid.innerHTML = '';
      var first = new Date(cur), off = (first.getDay() - start + 7) % 7; first.setDate(1 - off);
      var today = key(new Date());
      for (var k = 0; k < 42; k++) {
        var d = new Date(first); d.setDate(first.getDate() + k);
        var inM = d.getMonth() === cur.getMonth(), items = (by[key(d)] || []).sort(function (a, b) { return new Date(a.at) - new Date(b.at); }), fut = d >= new Date(new Date().setHours(0, 0, 0, 0));
        var cell = document.createElement('div'); cell.dataset.day = d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
        cell.className = 'group relative min-h-[7.5rem] border-b border-e border-ink-100 p-1.5 transition ' + (inM ? 'bg-white' : 'bg-ink-50/60') + (fut && inM ? ' hover:bg-brand-50/30' : '');
        cell.innerHTML = '<div class="flex items-center justify-between mb-1"><span class="text-xs tabular-nums w-6 h-6 rounded-full flex items-center justify-center ' + (key(d) === today ? 'bg-brand-600 text-[#fff] font-semibold' : inM ? 'text-ink-700' : 'text-ink-300') + '">' + d.getDate() + '</span>' + (fut && inM ? '<a class="opacity-0 group-hover:opacity-100 transition w-6 h-6 rounded-lg flex items-center justify-center text-brand-600 hover:bg-brand-100" title="' + esc(X.newHere) + '" href="' + X.compose + '&when=' + d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T10:00">+</a>' : '') + '</div>';
        items.slice(0, 3).forEach(function (p) {
          var a = document.createElement('a'); a.href = X.edit.replace('__ID__', encodeURIComponent(p.id));
          a.className = 'flex items-center gap-1 rounded-md px-1.5 py-1 mb-0.5 text-[11px] text-ink-700 bg-ink-50 hover:bg-brand-50 hover:text-brand-800 transition' + (p.mv && X.canDel ? ' cursor-grab active:cursor-grabbing' : '');
          if (p.mv && X.canDel) { a.draggable = true; a.dataset.pid = p.id; }
          var t = new Date(p.at);
          a.innerHTML = '<span class="w-1.5 h-1.5 rounded-full shrink-0 ' + (dot[p.st] || 'bg-ink-300') + '"></span><span class="tabular-nums text-ink-400 shrink-0">' + t.toLocaleTimeString(lang, { hour: 'numeric', minute: '2-digit', timeZone: X.tz }) + '</span><span class="truncate flex-1" dir="auto"></span><span class="flex shrink-0">' + p.pf.slice(0, 2).map(function (x) { return X.icons[x] || ''; }).join('') + '</span>';
          a.querySelector('.truncate').textContent = p.txt || '—'; a.title = p.txt;
          cell.appendChild(a);
        });
        if (items.length > 3) { var mo = document.createElement('button'); mo.type = 'button'; mo.className = 'text-[11px] text-brand-700 px-1.5 hover:underline'; mo.textContent = X.more.replace('%s', items.length - 3);
          mo.onclick = (function (list, c) { return function () { this.remove(); list.slice(3).forEach(function (p) { var a = document.createElement('a'); a.href = X.edit.replace('__ID__', encodeURIComponent(p.id)); a.className = 'flex items-center gap-1 rounded-md px-1.5 py-1 mb-0.5 text-[11px] text-ink-700 bg-ink-50 hover:bg-brand-50'; a.innerHTML = '<span class="w-1.5 h-1.5 rounded-full shrink-0 ' + (dot[p.st] || 'bg-ink-300') + '"></span><span class="truncate" dir="auto"></span>'; a.querySelector('.truncate').textContent = p.txt || '—'; c.appendChild(a); }); }; })(items, cell);
          cell.appendChild(mo); }
        if (gapSet[key(d)] && inM && !items.some(function (p) { return p.st === 'scheduled'; })) { var gp = document.createElement('a'); gp.href = X.compose + (X.compose.indexOf('?') < 0 ? '?' : '&') + 'when=' + d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T19:00'; gp.className = 'mt-1 flex items-center justify-center rounded-md border border-dashed border-amber-300 text-[10px] text-amber-700 py-1 hover:bg-amber-50'; gp.textContent = '＋ ' + ((X.q || {}).gapDay || ''); cell.appendChild(gp); }
        grid.appendChild(cell);
      }
    }
    window.__cal = draw;
    /* ---------- صحة الجدول: نصوص متكررة + أيام زحمة ---------- */
    var Hq = X.q || {}, dupGroups = [], dupSet = {};
    function normT(t) { return String(t || '').toLowerCase().replace(/\s+/g, ' ').replace(/[#@]\S+/g, '').trim(); }
    function health() {
      var box = $('#cal-health'); if (!box) return;
      var sch = X.data.filter(function (p) { return p.st === 'scheduled' && new Date(p.at) > new Date(); });
      var g = {}; sch.forEach(function (p) { var k = normT(p.txt); if (k.length < 12) return; (g[k] = g[k] || []).push(p); });
      dupGroups = Object.keys(g).map(function (k) { return g[k]; }).filter(function (l) { return l.length > 1; }).sort(function (a, b) { return b.length - a.length; });
      dupSet = {}; dupGroups.forEach(function (l) { l.forEach(function (p) { dupSet[p.id] = 1; }); });
      // الزحمة بتتحسب لكل حساب لوحده: اليوم زحمة لو أي حساب عليه أكتر من الحد
      try { var bn = +localStorage.getItem('cal-busyN'); if (bn > 0) Hq.busyN = bn; } catch (e) {}
      var pa = {}, per = {}; sch.forEach(function (p) { var k = siteDT(new Date(p.at)).slice(0, 10); (p.ac && p.ac.length ? p.ac : ['_']).forEach(function (a) { var kk = k + '|' + a; pa[kk] = (pa[kk] || 0) + 1; if (pa[kk] > (per[k] || 0)) per[k] = pa[kk]; }); });
      var busy = Object.keys(per).filter(function (k) { return per[k] > Hq.busyN; });
      var nd = Object.keys(dupSet).length;
      box.classList.remove('hidden');
      box.innerHTML = '<span class="font-semibold text-ink-700">' + uic('alert', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Hq.hTitle) + ':</span>' +
        (nd ? '<button type="button" id="cal-dup-open" class="rounded-full bg-orange-100 text-orange-800 border border-orange-200 px-2.5 py-0.5 hover:bg-orange-200">' + uic('refresh', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Hq.hDup.replace('%s', nd).replace('%s', dupGroups.length)) + ' · ' + esc(Hq.hShow) + '</button>' : '') +
        (busy.length ? '<span class="rounded-full bg-red-50 text-red-700 border border-red-200 px-2.5 py-0.5">' + uic('fire', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Hq.hBusy.replace('%s', busy.length).replace('%s', Hq.busyN)) + '</span>' : '') +
        (!nd && !busy.length ? '<span class="text-lime-700">✓ ' + esc(Hq.hOk) + '</span>' : '') +
        '<label class="ms-auto inline-flex items-center gap-1.5 text-ink-500">' + esc(Hq.hLimit) + ' <input id="cal-busyN" type="number" min="1" max="50" value="' + Hq.busyN + '" class="input !w-16 !py-0.5 text-xs"></label>';
      var bi = $('#cal-busyN'); if (bi) bi.onchange = function () { var v = Math.max(1, Math.min(50, +bi.value || 6)); try { localStorage.setItem('cal-busyN', v); } catch (e) {} Hq.busyN = v; draw(); };
      var ob = $('#cal-dup-open'); if (ob) ob.onclick = openDups; goalsBar();
      // علامات على التقويم
      $$('#cal-grid a[href*="post_edit"], #cal-week a[href*="post_edit"]').forEach(function (a) { var m = /[?&]id=([^&]+)/.exec(a.getAttribute('href')); if (m && dupSet[decodeURIComponent(m[1])]) { a.classList.add('ring-2', 'ring-orange-300', 'bg-orange-50'); a.title = '🔁 ' + a.title; } });
      $$('#cal-grid [data-day]').forEach(function (c) { var pr = c.dataset.day.split('-'), k = pr[0] + '-' + pad(+pr[1]) + '-' + pad(+pr[2]); if (per[k] > Hq.busyN && !c.querySelector('.cal-busy')) { var b = document.createElement('span'); b.className = 'cal-busy absolute top-1.5 end-9 rounded-full bg-red-500 text-white text-[9px] px-1.5'; b.textContent = per[k]; c.appendChild(b); } });
    }
    /* ---------- #14 المواسم: علامة على اليوم + "مواسم جاية" فوق التقويم ---------- */
    function seasons() {
      var list = (X.q && X.q.seasons) || [], box = $('#cal-seasons'); if (!box) return;
      var today = siteDT(new Date()).slice(0, 10), soon = list.filter(function (s) { return s.d >= today; }).slice(0, 4);
      var dupUrl = function (s) { return X.q.compose + (X.q.compose.indexOf('?') > -1 ? '&' : '?') + 'dup_text=' + encodeURIComponent(s.idea); };
      box.classList.remove('hidden');
      box.innerHTML = '<span class="font-semibold text-ink-700">🎉 ' + esc(X.q.sSoon) + ':</span>' + soon.map(function (s) {
        var days = Math.round((new Date(s.d) - new Date(today)) / 864e5);
        return '<a href="' + esc(dupUrl(s)) + '" class="rounded-full border px-2.5 py-0.5 hover:bg-brand-50 hover:border-brand-300 ' + (days <= 14 ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-ink-200 text-ink-600') + '" title="' + esc(X.q.sPrep + (s.approx ? ' · ' + X.q.sApprox : '')) + '">' + esc(s.n) + ' · ' + esc(s.d.slice(5).split('-').reverse().join('/')) + (days >= 0 ? ' (' + days + ')' : '') + ' ✎</a>';
      }).join('') + (X.q.canAi ? '<button type="button" id="cal-ideas" class="ms-auto btn-ghost !py-1 !text-xs">' + uic('bulb', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(X.q.ideas) + '</button>' : '');
      var ib = $('#cal-ideas'); if (ib) ib.onclick = weekIdeas;
      $$('#cal-grid [data-day]').forEach(function (c) {
        var pr = c.dataset.day.split('-'), k = pr[0] + '-' + pad(+pr[1]) + '-' + pad(+pr[2]);
        list.forEach(function (s) { if (s.d === k && !c.querySelector('.cal-season')) { var a = document.createElement('a'); a.className = 'cal-season block mt-0.5 truncate rounded bg-violet-50 border border-violet-200 text-violet-800 text-[10px] px-1 hover:bg-violet-100'; a.href = dupUrl(s); a.title = s.idea + (s.approx ? ' · ' + X.q.sApprox : ''); a.textContent = '🎉 ' + s.n; a.addEventListener('click', function (e) { e.stopPropagation(); }); c.appendChild(a); } });
      });
    }
    function weekIdeas() {
      var ib = $('#cal-ideas'); ib.disabled = true; var o = ib.textContent; ib.textContent = X.q.thinking;
      var fd = new FormData(); fd.append('action', 'week_ideas');
      fetch(location.pathname + '?p=posts', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }).then(function (j) {
        ib.disabled = false; ib.textContent = o; if (!j.ok) return toast(j.error || X.err);
        dmb.innerHTML = (j.kb ? '' : '<p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-2">' + esc(X.q.noKb) + '</p>') + j.ideas.map(function (it, i) {
          return '<div class="rounded-xl border border-ink-200 p-3"><div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-100 text-brand-700 text-xs font-bold grid place-items-center">' + (i + 1) + '</span><b class="text-sm text-ink-900">' + esc(it.t) + '</b>' +
            '<a class="btn-ghost !py-1 !text-xs ms-auto" href="' + esc(X.q.compose + (X.q.compose.indexOf('?') > -1 ? '&' : '?') + 'dup_text=' + encodeURIComponent(it.b)) + '">' + esc(X.q.mkPost) + ' ' + uic('pencil', 'w-4 h-4 inline-block align-[-3px]') + '</a></div><p class="text-sm text-ink-700 whitespace-pre-line mt-2" dir="auto">' + esc(it.b) + '</p></div>';
        }).join('');
        $('#cal-dups-h').textContent = '💡 ' + X.q.ideasT; dm.classList.remove('hidden'); dm.setAttribute('aria-hidden', 'false');
      }).catch(function () { ib.disabled = false; ib.textContent = o; toast(X.err); });
    }
    /* ---------- #23 ألوان حسب التاج + ♻️ Evergreen على العناصر ---------- */
    var TAGC = ['bg-violet-500', 'bg-amber-500', 'bg-cyan-500', 'bg-rose-500', 'bg-emerald-500', 'bg-fuchsia-500', 'bg-orange-500', 'bg-teal-500'];
    function tagColor(t) { var h = 0; for (var i = 0; i < t.length; i++) h = (h * 31 + t.charCodeAt(i)) >>> 0; return TAGC[h % TAGC.length]; }
    function decorate() {
      var byId = {}; X.data.forEach(function (p) { byId[p.id] = p; });
      $$('#cal-grid a[href*="post_edit"], #cal-week a[href*="post_edit"]').forEach(function (a) {
        var m = /[?&]id=([^&]+)/.exec(a.getAttribute('href')), p = m && byId[decodeURIComponent(m[1])]; if (!p || a.querySelector('.cal-tg')) return;
        if (p.tg && p.tg.length) { var s = document.createElement('span'); s.className = 'cal-tg inline-block w-1.5 h-3 rounded-sm me-1 align-middle ' + tagColor(p.tg[0]); s.title = '#' + p.tg.join(' #'); a.insertBefore(s, a.firstChild); }
        if (p.eg) { var e2 = document.createElement('span'); e2.className = 'cal-tg'; e2.textContent = '♻️'; e2.title = X.q.egOn.replace('%s', p.eg); a.appendChild(e2); }
      });
    }
    /* ---------- #24 تقويم لحساب واحد ---------- */
    var ca = $('#cal-acc'); if (ca) ca.addEventListener('change', function () { cAcc = ca.value; draw(); });
    /* ---------- أدوات التقويم: #17 #21 #22 #20 ---------- */
    var ctl = $('#cal-tools');
    function sched() { return X.data.filter(function (p) { return p.st === 'scheduled' && new Date(p.at) > new Date(); }); }
    function runSeq(ids, op, extraFn, doneMsg) {
      var ok = 0, bad = 0, i = 0;
      (function next() {
        if (i >= ids.length) { toast(doneMsg.replace('%s', ok).replace('%s', bad), true); setTimeout(function () { location.reload(); }, 1100); return; }
        var id = ids[i++]; psQuick(op, [id], extraFn ? extraFn(id) : {}).then(function (d) { (d.ok || []).length ? ok++ : bad++; next(); }).catch(function () { bad++; next(); });
      })();
    }
    function inBlack(d) { var r = X.q.blackout || [], dw = +siteDT(d).slice(0, 10).split('-').length && new Date(siteDT(d).slice(0, 10) + 'T12:00').getDay(), hm = siteDT(d).slice(11, 16); return r.some(function (b) { return b.days.indexOf(dw) > -1 && hm >= b.from && hm < b.to; }); }
    window.__psInBlack = inBlack;
    if (ctl) ctl.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ct]'); if (!b) return; ctl.open = false; var k = b.dataset.ct;
      if (k === 'pause') {
        var f = prompt(X.q.pFrom, siteDT(new Date()).slice(0, 10)); if (!f) return; var t = prompt(X.q.pTo, f); if (!t) return; var n = parseInt(prompt(X.q.pDays, '1'), 10); if (!n) return;
        var ids = sched().filter(function (p) { var d = siteDT(new Date(p.at)).slice(0, 10); return d >= f && d <= t; }).map(function (p) { return p.id; });
        if (!ids.length) return toast(X.q.pNone);
        return runSeq(ids, 'shift', function () { return { n: n, unit: 'd' }; }, X.q.pDone);
      }
      if (k === 'copyw') {
        var ws0 = calV === 'week' ? new Date(wk) : (function () { var d = new Date(); d.setHours(0, 0, 0, 0); d.setDate(d.getDate() - d.getDay()); return d; })(), we = new Date(ws0); we.setDate(we.getDate() + 7);
        var ids2 = sched().filter(function (p) { var d = new Date(p.at); return d >= ws0 && d < we; }).map(function (p) { return p.id; });
        if (!ids2.length) return toast(X.q.cwNone); if (!confirm(X.q.cwQ.replace('%s', ids2.length))) return;
        return runSeq(ids2, 'copyshift', function () { return { n: 7 }; }, X.q.cwDone);
      }
      if (k === 'gaps') {
        var drafts = X.data.filter(function (p) { return p.st === 'draft'; }), busy = {};
        sched().forEach(function (p) { busy[siteDT(new Date(p.at)).slice(0, 10)] = 1; });
        var plan = [], day = new Date();
        for (var i = 1; i <= 14 && plan.length < drafts.length; i++) {
          var d = new Date(day.getTime() + i * 864e5), key2 = siteDT(d).slice(0, 10); if (busy[key2]) continue;
          var when = key2 + 'T' + pad(X.q.gHour) + ':00', probe = new Date(when);
          if (inBlack(probe)) { when = key2 + 'T' + pad((X.q.gHour + 3) % 24) + ':00'; if (inBlack(new Date(when))) continue; }
          plan.push({ id: drafts[plan.length].id, when: when, txt: drafts[plan.length].txt });
        }
        if (!plan.length) return toast(X.q.gNone);
        if (!confirm(X.q.gQ.replace('%s', plan.length) + '\n\n' + plan.map(function (x) { return x.when.replace('T', ' ') + ' — ' + x.txt.slice(0, 40); }).join('\n'))) return;
        var byW = {}; plan.forEach(function (x) { byW[x.id] = x.when; });
        return runSeq(plan.map(function (x) { return x.id; }), 'from_draft', function (id) { return { when: byW[id] }; }, X.q.gDone);
      }
      if (k === 'black') return blackDlg();
      if (k === 'goals') return goalsDlg();
    });
    /* ---------- #49 أهداف نشر أسبوعية ---------- */
    function weekStart() { var d = new Date(siteDT(new Date()).slice(0, 10) + 'T00:00'); d.setDate(d.getDate() - ((d.getDay() + 1) % 7)); return d; }
    function goalsBar() {
      var g = X.q.goals || {}, ks = Object.keys(g), box = $('#cal-health'); if (!ks.length || !box) return;
      var s = weekStart(), e = new Date(s), cnt = {}; e.setDate(e.getDate() + 7);
      X.data.forEach(function (p) { if (['scheduled', 'published'].indexOf(p.st) < 0) return; var d = new Date(siteDT(new Date(p.at)).slice(0, 16)); if (d < s || d >= e) return; (p.ac || []).forEach(function (a) { cnt[a] = (cnt[a] || 0) + 1; }); });
      var old = $('#cal-goals'); if (old) old.remove();
      var w = document.createElement('div'); w.id = 'cal-goals'; w.className = 'basis-full flex flex-wrap items-center gap-2 pt-1';
      w.innerHTML = '<span class="font-semibold text-ink-700">' + esc(X.q.gLbl) + ':</span>' + ks.map(function (a) { var n = cnt[a] || 0, ok = n >= g[a]; return '<span class="rounded-full border px-2.5 py-0.5 ' + (ok ? 'bg-lime-50 border-lime-200 text-lime-800' : 'bg-amber-50 border-amber-200 text-amber-800') + '">' + esc((X.q.accs || {})[a] || a) + ' ' + n + '/' + g[a] + (ok ? ' ✓' : '') + '</span>'; }).join('');
      box.classList.add('flex-wrap'); box.appendChild(w);
    }
    function goalsDlg() {
      var g = X.q.goals || {}, A = X.q.accs || {};
      dmb.innerHTML = '<p class="text-xs text-ink-500">' + esc(X.q.gHint) + '</p><div class="space-y-1.5">' + Object.keys(A).map(function (a) { return '<label class="flex items-center gap-2 text-sm"><span class="flex-1 truncate">' + esc(A[a]) + '</span><input type="number" min="0" max="100" data-ga="' + esc(a) + '" value="' + (g[a] || '') + '" class="input !mt-0 !py-1 !w-20 text-sm"></label>'; }).join('') + '</div><div class="flex"><button type="button" class="btn-primary !py-1 !text-xs ms-auto" id="gl-save">✓</button></div>';
      $('#cal-dups-h').textContent = '🎯 ' + X.q.gT; dm.classList.remove('hidden'); dm.setAttribute('aria-hidden', 'false');
      $('#gl-save').onclick = function () {
        var out = {}; $$('[data-ga]', dmb).forEach(function (i) { if (+i.value > 0) out[i.dataset.ga] = +i.value; });
        var fd = new FormData(); fd.append('action', 'goals'); fd.append('goals', JSON.stringify(out));
        fetch(location.pathname + '?p=posts', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }).then(function (j) { if (j.ok) { X.q.goals = j.goals; toast(X.q.gSaved, true); dm.classList.add('hidden'); draw(); } else toast(j.error || X.err); });
      };
    }
    function blackDlg() {
      var rules = (X.q.blackout || []).slice(); if (!rules.length) rules.push({ days: [5], from: '11:00', to: '13:30' });
      function rowH(r, i) { return '<div class="rounded-xl border border-ink-200 p-2 space-y-1.5" data-br="' + i + '"><div class="flex flex-wrap gap-1">' + X.q.dows.map(function (n, d) { return '<label class="cursor-pointer"><input type="checkbox" class="peer sr-only" value="' + d + '"' + (r.days.indexOf(d) > -1 ? ' checked' : '') + '><span class="inline-block px-2 py-0.5 rounded-lg border border-ink-200 text-[11px] peer-checked:bg-brand-600 peer-checked:text-white">' + esc(n) + '</span></label>'; }).join('') + '</div>' +
        '<div class="flex items-center gap-2 text-xs"><input type="time" class="input !mt-0 !py-1 text-xs" data-k="from" value="' + r.from + '"> → <input type="time" class="input !mt-0 !py-1 text-xs" data-k="to" value="' + r.to + '"><button type="button" class="ms-auto text-ink-400 hover:text-red-600" data-brx>✕</button></div></div>'; }
      dmb.innerHTML = '<p class="text-xs text-ink-500">' + esc(X.q.bHint) + '</p><div id="br-list" class="space-y-2">' + rules.map(rowH).join('') + '</div><div class="flex gap-2"><button type="button" class="btn-ghost !py-1 !text-xs" id="br-add">' + esc(X.q.bAdd) + '</button><button type="button" class="btn-primary !py-1 !text-xs ms-auto" id="br-save">' + esc(X.q.applyV ? X.saved || '✓' : '✓') + '</button></div>';
      $('#cal-dups-h').textContent = '⛔ ' + X.q.bT; dm.classList.remove('hidden'); dm.setAttribute('aria-hidden', 'false');
      $('#br-add').onclick = function () { $('#br-list').insertAdjacentHTML('beforeend', rowH({ days: [], from: '00:00', to: '07:00' }, Date.now())); };
      $('#br-list').addEventListener('click', function (e) { if (e.target.closest('[data-brx]')) e.target.closest('[data-br]').remove(); });
      $('#br-save').onclick = function () {
        var out = $$('#br-list [data-br]').map(function (r) { return { days: $$('input[type=checkbox]:checked', r).map(function (c) { return +c.value; }), from: $('[data-k=from]', r).value, to: $('[data-k=to]', r).value }; });
        var fd = new FormData(); fd.append('action', 'blackout'); fd.append('rules', JSON.stringify(out));
        fetch(location.pathname + '?p=posts', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }).then(function (j) { if (j.ok) { X.q.blackout = j.rules; toast(X.q.bSaved, true); dm.classList.add('hidden'); } });
      };
    }
    /* ---------- #25 اسحب ملف Excel/CSV على التقويم → الرفع الجماعي ---------- */
    var pc = $('#ps-cal');
    if (pc && X.q.bulkUrl) {
      pc.addEventListener('dragover', function (e) { if (e.dataTransfer && [].indexOf.call(e.dataTransfer.types || [], 'Files') > -1) { e.preventDefault(); pc.classList.add('ring-4', 'ring-brand-300'); } });
      pc.addEventListener('dragleave', function (e) { if (!pc.contains(e.relatedTarget)) pc.classList.remove('ring-4', 'ring-brand-300'); });
      pc.addEventListener('drop', function (e) {
        var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]; pc.classList.remove('ring-4', 'ring-brand-300');
        if (!f || !/\.(csv|xlsx|xls|tsv|txt)$/i.test(f.name)) return; e.preventDefault();
        var rd = new FileReader(); rd.onload = function () { try { sessionStorage.setItem('bk-drop', JSON.stringify({ name: f.name, type: f.type, data: rd.result })); } catch (x) { return toast(X.err); } location.href = X.q.bulkUrl; }; rd.readAsDataURL(f);
      });
    }
    var _d0 = draw; draw = function () { _d0(); health(); seasons(); decorate(); }; window.__cal = draw;
    var dm = $('#cal-dups'), dmb = $('#cal-dups-body');
    function closeDups() { dm.classList.add('hidden'); dm.setAttribute('aria-hidden', 'true'); }
    if (dm) { $$('[data-close]', dm).forEach(function (x) { x.onclick = closeDups; }); document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !dm.classList.contains('hidden')) closeDups(); }); }
    function when(p) { return new Date(p.at).toLocaleString(lang, { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit', timeZone: X.tz }); }
    function openDups() {
      if (!dm) return;
      dmb.innerHTML = dupGroups.map(function (l, gi) {
        return '<div class="rounded-xl border border-orange-200 p-3" data-g="' + gi + '"><div class="flex items-center gap-2"><span class="rounded-full bg-orange-100 text-orange-800 text-[10px] px-2 py-0.5">' + esc(Hq.gN.replace('%s', l.length)) + '</span>' +
          '<button type="button" class="cal-vary btn-ghost !py-1 !text-xs ms-auto" data-mode="fast" title="' + esc(Hq.fastT) + '">⚡ ' + esc(Hq.fast) + '</button>' +
          (Hq.canAi ? '<button type="button" class="cal-vary btn-ghost !py-1 !text-xs">' + esc(Hq.vary) + '</button>' : '') + '</div>' +
          '<p class="text-sm text-ink-800 mt-2 line-clamp-3" dir="auto">' + esc(l[0].txt) + '</p>' +
          '<div class="cal-vlist mt-2 space-y-1">' + l.map(function (p) { return '<div class="text-[11px] text-ink-500 flex items-center gap-2" data-id="' + esc(p.id) + '"><span>' + uic('calendar', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(when(p)) + '</span><a class="link ms-auto" href="' + esc(X.edit.replace('__ID__', encodeURIComponent(p.id))) + '">' + uic('pencil', 'w-4 h-4 inline-block align-[-3px]') + '</a></div>'; }).join('') + '</div></div>';
      }).join('');
      dm.classList.remove('hidden'); dm.setAttribute('aria-hidden', 'false');
    }
    if (dmb) dmb.addEventListener('click', function (e) {
      var vb = e.target.closest('.cal-vary'), ab = e.target.closest('.cal-apply');
      if (vb) {
        var box = vb.closest('[data-g]'), l = dupGroups[+box.dataset.g]; vb.disabled = true; var o = vb.textContent; vb.textContent = Hq.varying;
        var fd = new FormData(); fd.append('action', 'vary'); fd.append('mode', vb.dataset.mode || ''); l.slice(0, 12).forEach(function (p) { fd.append('ids[]', p.id); });
        fetch(location.pathname + '?p=posts', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }).then(function (j) {
          vb.disabled = false; vb.textContent = o; if (!j.ok) return toast(j.error || X.err);
          var byId = {}; l.forEach(function (p) { byId[p.id] = p; });
          $('.cal-vlist', box).innerHTML = '<p class="text-[11px] text-ink-500">' + esc(Hq.varyNote) + '</p>' + j.items.map(function (it) { return '<label class="block"><span class="text-[11px] text-ink-500">' + uic('calendar', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(when(byId[it.id] || { at: '' })) + '</span><textarea data-id="' + esc(it.id) + '" rows="4" class="input text-sm mt-0.5" dir="auto">' + esc(it.text) + '</textarea></label>'; }).join('') +
            '<button type="button" class="cal-apply btn-primary !text-xs w-full mt-2">' + esc(Hq.applyV) + '</button>';
        }).catch(function () { vb.disabled = false; vb.textContent = o; toast(X.err); });
        return;
      }
      if (ab) {
        var tas = $$('textarea[data-id]', ab.closest('[data-g]')), i = 0, ok = 0; ab.disabled = true;
        (function next() {
          if (i >= tas.length) { toast(Hq.varied.replace('%s', ok), true); setTimeout(function () { location.reload(); }, 900); return; }
          var t = tas[i++]; psQuick('text', [t.dataset.id], { content: t.value }).then(function (d) { if ((d.ok || []).length) ok++; else { var f = Object.values(d.failed || {}); if (f.length) toast(f[0]); } next(); }).catch(function () { next(); });
        })();
      }
    });
    // سحب منشور مجدول ليوم تاني = نفس الساعة في اليوم الجديد
    var dragId = null, Q = X.q || {};
    [weekBox].forEach(function (box) {
      if (!box) return;
      box.addEventListener('dragstart', function (e) { var a = e.target.closest('[data-pid]'); if (!a) return; dragId = a.dataset.pid; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', dragId); } catch (er) {} a.classList.add('opacity-50'); });
      box.addEventListener('dragend', function () { dragId = null; $$('[data-wday]', box).forEach(function (c) { c.classList.remove('ring-2', 'ring-inset', 'ring-brand-400'); }); });
      box.addEventListener('dragover', function (e) { var c = e.target.closest('[data-wday]'); if (!c || !dragId) return; e.preventDefault(); $$('[data-wday]', box).forEach(function (x) { var on = x === c; x.classList.toggle('ring-2', on); x.classList.toggle('ring-inset', on); x.classList.toggle('ring-brand-400', on); }); });
      box.addEventListener('drop', function (e) {
        var c = e.target.closest('[data-wday]'); if (!c || !dragId) return; e.preventDefault();
        var p = X.data.filter(function (x) { return x.id === dragId; })[0]; if (!p) return;
        var old = new Date(p.at), op = siteParts(old), pr = c.dataset.wday.split('-'), hr = +c.dataset.hour;
        var when = pr[0] + '-' + pad(+pr[1]) + '-' + pad(+pr[2]) + 'T' + pad(hr) + ':' + pad(op.mi); // اليوم والساعة الجداد، نفس الدقايق
        if (when === siteDT(old)) return draw();
        var nd = new Date(old.getTime() + (Date.UTC(+pr[0], +pr[1] - 1, +pr[2], hr, op.mi) - Date.UTC(op.y, op.m - 1, op.d, op.h, op.mi)));
        if (nd < new Date()) { toast(Q.pastDay); return draw(); }
        var prev = p.at; p.at = nd.toISOString(); draw(); toast(Q.moving, true);
        psQuick('resched', [p.id], { when: when }).then(function (d) {
          var f = Object.values(d.failed || {}); if (f.length) { p.at = prev; draw(); toast(f[0]); return; }
          toast(Q.moved.replace('%s', when.replace('T', ' ')), true);
        }).catch(function (er) { p.at = prev; draw(); toast(er.message || X.err); });
      });
    });
    grid.addEventListener('dragstart', function (e) { var a = e.target.closest('[data-pid]'); if (!a) return; dragId = a.dataset.pid; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', dragId); } catch (er) {} a.classList.add('opacity-50'); });
    grid.addEventListener('dragend', function () { dragId = null; $$('[data-day]', grid).forEach(function (c) { c.classList.remove('ring-2', 'ring-inset', 'ring-brand-400', 'bg-brand-50'); }); });
    grid.addEventListener('dragover', function (e) { var c = e.target.closest('[data-day]'); if (!c || !dragId) return; e.preventDefault(); $$('[data-day]', grid).forEach(function (x) { var on = x === c; x.classList.toggle('ring-2', on); x.classList.toggle('ring-inset', on); x.classList.toggle('ring-brand-400', on); x.classList.toggle('bg-brand-50', on); }); });
    grid.addEventListener('drop', function (e) {
      var c = e.target.closest('[data-day]'); if (!c || !dragId) return; e.preventDefault();
      var p = X.data.filter(function (x) { return x.id === dragId; })[0]; if (!p) return;
      var old = new Date(p.at), pr = c.dataset.day.split('-'), op = siteParts(old);
      var when = pr[0] + '-' + pad2(+pr[1]) + '-' + pad2(+pr[2]) + 'T' + pad2(op.h) + ':' + pad2(op.mi); // نفس الساعة بتوقيت الشركة
      var nd = new Date(old.getTime() + (Date.UTC(+pr[0], +pr[1] - 1, +pr[2]) - Date.UTC(op.y, op.m - 1, op.d)));
      if (when.slice(0, 10) === siteDT(old).slice(0, 10)) return draw();
      if (nd < new Date()) { toast(Q.pastDay); return draw(); }
      var prev = p.at; p.at = nd.toISOString(); draw(); toast(Q.moving, true);
      psQuick('resched', [p.id], { when: when }).then(function (d) {
        var f = Object.values(d.failed || {}); if (f.length) { p.at = prev; draw(); toast(f[0]); return; }
        toast(Q.moved.replace('%s', nd.toLocaleString(lang, { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit', timeZone: X.tz })), true);
      }).catch(function (er) { p.at = prev; draw(); toast(er.message || X.err); });
    });
    $$('[data-cal]').forEach(function (b) { b.onclick = function () {
      var k = b.dataset.cal;
      if (calV === 'week') { if (k === 'today') { wk = new Date(); wk.setHours(0, 0, 0, 0); wk.setDate(wk.getDate() - ((wk.getDay() - start + 7) % 7)); } else wk.setDate(wk.getDate() + (k === 'next' ? 7 : -7)); }
      else if (k === 'today') { cur = new Date(); cur.setDate(1); } else cur.setMonth(cur.getMonth() + (k === 'next' ? 1 : -1));
      draw(); }; });
    $$('.cal-v').forEach(function (b) { b.onclick = function () { calV = b.dataset.calv; try { localStorage.setItem('cal-v', calV); } catch (e) {} if (calV === 'week') { var t = new Date(cur.getFullYear(), cur.getMonth(), 1), n = new Date(); if (n.getMonth() === cur.getMonth() && n.getFullYear() === cur.getFullYear()) t = n; t.setHours(0, 0, 0, 0); t.setDate(t.getDate() - ((t.getDay() - start + 7) % 7)); wk = t; } else { cur = new Date(wk); cur.setDate(1); } draw(); }; });
    document.addEventListener('keydown', function (e) { if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return; if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { var rtl = document.documentElement.dir === 'rtl'; var dir = (e.key === 'ArrowLeft') !== rtl ? -1 : 1; if (calV === 'week') wk.setDate(wk.getDate() + 7 * dir); else cur.setMonth(cur.getMonth() + dir); draw(); } });
    draw();
  }

  /* ---------- اختصارات ---------- */
  function help() {
    var d = document.createElement('dialog'); d.className = 'w-[min(440px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm text-ink-900"></b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div><div class="p-4 grid gap-1.5 text-sm"></div>';
    d.querySelector('b').textContent = X.keysTitle;
    X.keys.forEach(function (k) { var r = document.createElement('div'); r.className = 'flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-ink-50'; r.innerHTML = '<span class="text-ink-700"></span><span class="flex gap-1">' + k[0].split(' ').map(function (x) { return '<kbd class="min-w-[1.6rem] text-center rounded-md border border-ink-300 border-b-2 bg-ink-50 px-1.5 py-0.5 text-[11px] font-mono text-ink-700">' + esc(x) + '</kbd>'; }).join('') + '</span>'; r.querySelector('span').textContent = k[1]; d.querySelector('.grid').appendChild(r); });
    d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); }); document.body.appendChild(d); d.showModal();
  }
  var hb = $('#ps-help'); if (hb) hb.onclick = help;
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key === 'n') location.href = X.compose;
    else if (e.key === '/' && q) { e.preventDefault(); q.focus(); }
    else if (e.key === 'v') { var o = $$('#ps-view a').filter(function (a) { return !a.classList.contains('bg-white'); })[0]; if (o) o.click(); }
    else if (e.key === '?') { e.preventDefault(); help(); }
  });

  /* كروت / مضغوط (بيتفتكر) */
  (function () {
    var g = document.getElementById('ps-grid'); if (!g) return;
    var K = 'ps-dens', d = 'cards'; try { d = localStorage.getItem(K) || 'cards'; } catch (e) {}
    function apply() {
      var rows = d === 'rows';
      g.className = rows ? 'flex flex-col gap-2' : 'grid sm:grid-cols-2 xl:grid-cols-3 gap-4';
      g.querySelectorAll('.ps-media').forEach(function (m) { m.classList.toggle('hidden', rows); });
      g.querySelectorAll('.ps-txt').forEach(function (t) { t.classList.toggle('line-clamp-4', !rows); t.classList.toggle('line-clamp-2', rows); });
      g.querySelectorAll('.ps-card > .p-4').forEach(function (c) { c.classList.toggle('!py-3', rows); });
      document.querySelectorAll('.ps-dens').forEach(function (b) { var on = b.dataset.dens === d; b.classList.toggle('bg-white', on); b.classList.toggle('shadow-sm', on); b.classList.toggle('text-ink-900', on); b.classList.toggle('text-ink-500', !on); b.setAttribute('aria-pressed', on); });
    }
    document.querySelectorAll('.ps-dens').forEach(function (b) { b.addEventListener('click', function () { d = b.dataset.dens; try { localStorage.setItem(K, d); } catch (e) {} apply(); }); });
    window.__psDens = apply;
    apply();
  })();

  /* ---------- عرض سريع + إجراءات (ميعاد جديد، إعادة محاولة، نسخة، تأجيل جماعي) ---------- */
  (function () {
    var P = X.posts || {}, Q = X.q || {}, dr = $('#ps-dr'); if (!dr) return;
    var body = $('#ps-dr-body'), acts = $('#ps-dr-acts'), cur = null, last = null;
    function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function local(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
    function quick(op, ids, extra) {
      var fd = new FormData(); fd.append('action', 'quick'); fd.append('op', op); ids.forEach(function (i) { fd.append('ids[]', i); });
      Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
      return fetch(location.pathname + '?p=posts', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }).then(function (d) { if (d.error) throw new Error(d.error); return d; });
    }
    function report(d, okMsg) {
      var f = Object.values(d.failed || {}), n = (d.ok || []).length;
      if (f.length && !n) toast(f[0]); else if (f.length) toast(Q.some.replace('%s', n).replace('%s', f.length) + ' — ' + f[0]); else toast(okMsg.replace('%s', n), true);
      if (n) { toast(Q.reload, true); setTimeout(function () { location.reload(); }, 900); }
    }
    // التاجات بتتحدث في الصفحة من غير تحميل
    function setTags(map) {
      Object.keys(map).forEach(function (id) {
        var tags = map[id] || []; if (P[id]) P[id].tags = tags;
        var c = $('.ps-card[data-id="' + CSS.escape(id) + '"]'); if (!c) return;
        c.dataset.tags = tags.join('|'); var box = $('.ps-tags', c);
        if (box) { box.classList.toggle('hidden', !tags.length); box.innerHTML = tags.map(function (t) { return '<span class="rounded-full bg-violet-50 text-violet-700 border border-violet-200 text-[10px] px-2 py-0.5">#' + esc(t) + '</span>'; }).join(''); }
      });
    }
    function notesHtml(list) {
      return (list || []).map(function (n) { var at = n.at ? new Date(n.at.replace(' ', 'T')) : null; return '<div class="rounded-lg bg-white border border-amber-100 px-2.5 py-1.5"><div class="text-sm text-ink-800 whitespace-pre-wrap break-words" dir="auto">' + esc(n.b) + '</div><div class="text-[10px] text-ink-400 mt-0.5">' + esc(n.by || '—') + (at && !isNaN(at) ? ' · ' + esc(at.toLocaleString(lang, { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })) : '') + '</div></div>'; }).join('');
    }
    function visibleIds() { return $$('.ps-card').filter(function (c) { return c.style.display !== 'none'; }).map(function (c) { return c.dataset.id; }); }
    function open(id) {
      var d = P[id]; if (!d) return false;
      cur = id; last = document.activeElement;
      var when = d.at ? new Date(d.at) : null;
      var media = (d.media || []).map(function (m) { return m.t === 'video' ? '<video src="' + esc(m.u) + '" controls class="w-full rounded-xl bg-black max-h-72"></video>' : m.t === 'document' ? '' : '<img src="' + esc(m.u) + '" alt="" class="w-full rounded-xl object-cover max-h-72 bg-ink-100" referrerpolicy="no-referrer">'; }).join('');
      var pls = (d.pl || []).map(function (p) {
        var stc = p.st === 'published' ? 'bg-lime-100 text-lime-800' : p.st === 'failed' ? 'bg-red-100 text-red-700' : 'bg-ink-100 text-ink-600';
        return '<div class="rounded-xl border border-ink-200 p-3"><div class="flex items-center gap-2"><span class="shrink-0">' + (X.icons[p.pf] || '') + '</span><span class="text-sm font-medium text-ink-900">' + esc((X.plab || {})[p.pf] || p.pf) + '</span><span class="text-xs text-ink-500 truncate">' + esc(p.acc) + '</span>' +
          '<span class="ms-auto text-[10px] rounded-md px-1.5 py-0.5 ' + stc + '">' + esc((Q.st || {})[p.st] || p.st || '—') + '</span></div>' +
          (p.err ? '<div class="text-xs text-red-700 mt-2 break-words"><b>' + esc(Q.reason) + ':</b> ' + esc(p.err) + (p.hint ? '<div class="mt-1 text-emerald-800">' + uic('bulb', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(p.hint) + '</div>' : '') + '</div>' : '') +
          (p.url ? '<a class="link text-xs mt-1.5 inline-block" href="' + esc(p.url) + '" target="_blank" rel="noopener">' + esc(Q.open) + ' ' + uic('share', 'w-4 h-4 inline-block align-[-3px]') + '</a>' : '') + '</div>';
      }).join('');
      var canRes = X.canDel && (d.st === 'scheduled' || d.st === 'draft');
      body.innerHTML = '<div class="flex flex-wrap items-center gap-2 text-xs"><span class="rounded-md px-1.5 py-0.5 bg-ink-100 text-ink-700 font-medium">' + esc(d.stl) + '</span>' + (when ? '<span class="text-ink-600" dir="auto">' + esc(when.toLocaleString(document.documentElement.lang || undefined, { weekday: 'long', day: 'numeric', month: 'long', hour: 'numeric', minute: '2-digit' })) + '</span>' : '') + '</div>' +
        '<div class="text-sm text-ink-900 whitespace-pre-wrap break-words leading-relaxed" dir="auto">' + (d.txt ? esc(d.txt).replace(/(#[\p{L}\p{N}_]+)/gu, '<span class="text-brand-700">$1</span>') : '<span class="text-ink-400 italic">—</span>') + '</div>' +
        (media ? '<div class="space-y-2">' + media + '</div>' : '') +
        (canRes ? '<details class="rounded-xl border border-ink-200 p-3"><summary class="cursor-pointer text-xs font-semibold text-ink-800">' + uic('pencil', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Q.editT) + '</summary><textarea id="ps-dr-txt" rows="6" class="input mt-2 text-sm" dir="auto">' + esc(d.txt) + '</textarea><div class="flex justify-end mt-2"><button type="button" id="ps-dr-tsave" class="btn-primary !text-xs">' + esc(Q.save) + '</button></div></details>' : '') +
        (canRes ? '<div class="rounded-xl border border-brand-200 bg-brand-50/50 p-3"><div class="text-xs font-semibold text-ink-800 mb-2">' + uic('calendar', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Q.resched) + '</div><div class="flex gap-2"><input type="datetime-local" id="ps-dr-when" class="input !mt-0 !h-9 text-sm flex-1" dir="ltr" value="' + (d.sched ? (window.__siteDT ? window.__siteDT(new Date(d.sched)) : local(new Date(d.sched))) : '') + '"><button type="button" id="ps-dr-save" class="btn-primary !text-xs">' + esc(Q.save) + '</button></div></div>' : '') +
        (X.canDup ? '<div class="rounded-xl border border-lime-200 bg-lime-50/40 p-3"><div class="text-xs font-semibold text-ink-800 mb-2">' + uic('refresh', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Q.repost) + '</div><div class="flex gap-2"><input type="datetime-local" id="ps-dr-rp" class="input !mt-0 !h-9 text-sm flex-1" dir="ltr"><button type="button" id="ps-dr-rpgo" class="btn-ghost !text-xs">' + esc(Q.repost) + '</button></div></div>' : '') +
        (Q.canEg && d.st === 'published' ? '<div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3"><div class="text-xs font-semibold text-ink-800 mb-2">' + esc(Q.egT) + '</div><div class="flex items-center gap-2 text-xs"><span>' + esc(Q.egEvery) + '</span><input type="number" min="3" max="365" id="ps-dr-eg" class="input !mt-0 !h-9 !w-20 text-sm" value="' + (d.eg || 30) + '"><button type="button" id="ps-dr-egon" class="btn-ghost !text-xs">' + esc(d.eg ? Q.egSaved.replace(' ✓', '') + ' ✓' : Q.egOn2) + '</button>' + (d.eg ? '<button type="button" id="ps-dr-egoff" class="btn-ghost !text-xs text-red-600">' + esc(Q.egOff) + '</button>' : '') + '</div></div>' : '') +
        (Q.canNote ? '<div class="rounded-xl border border-amber-200 bg-amber-50/40 p-3"><div class="text-xs font-semibold text-ink-800 mb-2">📝 ' + esc(Q.notesL) + '</div><div id="ps-dr-notes" class="space-y-1.5 mb-2">' + notesHtml(d.notes) + '</div><div class="flex gap-2"><input id="ps-dr-note" class="input !mt-0 !h-9 text-sm flex-1" dir="auto" maxlength="1000" placeholder="' + esc(Q.notePh) + '"><button type="button" id="ps-dr-ngo" class="btn-ghost !text-xs">' + esc(Q.noteAdd) + '</button></div></div>' : '') +
        (Q.canTag ? '<div class="rounded-xl border border-violet-200 bg-violet-50/40 p-3"><div class="text-xs font-semibold text-ink-800 mb-2">🏷 ' + esc(Q.tagsL) + '</div><div class="flex gap-2"><input id="ps-dr-tags" class="input !mt-0 !h-9 text-sm flex-1" list="ps-taglist" placeholder="' + esc(Q.tagsPh) + '" value="' + esc((d.tags || []).join('، ')) + '"><button type="button" id="ps-dr-tsv" class="btn-ghost !text-xs">' + esc(Q.save) + '</button></div></div>' : '') +
        (d.m ? '<div class="rounded-xl border border-ink-200 p-3"><div class="text-xs font-semibold text-ink-500 mb-2">' + uic('trend_up', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Q.perf) + '</div><div class="grid grid-cols-4 gap-2 text-center">' +
          [[Q.mImp, d.m.imp], [Q.mLikes, d.m.likes], [Q.mCom, d.m.comments], [Q.mSh, d.m.shares]].map(function (x) { return '<div><div class="text-lg font-bold text-ink-900 tabular-nums">' + Number(x[1] || 0).toLocaleString() + '</div><div class="text-[10px] text-ink-500">' + esc(x[0]) + '</div></div>'; }).join('') +
          '</div>' + (d.m.rate !== null && d.m.rate !== undefined ? '<div class="text-xs text-ink-600 mt-2 text-center">' + esc(Q.mRate) + ': <b>' + d.m.rate + '%</b></div>' : '') + '</div>' : '') +
        (pls ? '<div class="space-y-2"><div class="text-xs font-semibold text-ink-500">' + esc(Q.pfL) + '</div>' + pls + '</div>' : '');
      acts.innerHTML = '<a class="btn-primary !text-xs" href="' + esc(X.edit.replace('__ID__', encodeURIComponent(id))) + '">' + uic('pencil', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Q.edit) + '</a>' +
        ((d.st === 'failed' || d.st === 'partial') && X.canDel ? '<button type="button" data-a="retry" class="btn-ghost !text-xs text-red-700">' + uic('refresh', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Q.retry) + '</button>' : '') +
        (X.canDup ? '<button type="button" data-a="dup" class="btn-ghost !text-xs">⧉ ' + esc(Q.dup) + '</button>' : '') +
        (X.canDel && d.st !== 'publishing' ? '<button type="button" data-a="del" class="btn-ghost !text-xs text-red-600 ms-auto">' + uic('trash', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(Q.del) + '</button>' : '');
      dr.classList.remove('hidden'); dr.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
      var ids = visibleIds(), i = ids.indexOf(id); $('#ps-dr-prev').disabled = i <= 0; $('#ps-dr-next').disabled = i < 0 || i >= ids.length - 1;
      var rpIn = $('#ps-dr-rp'); if (rpIn) { var dd = new Date(); dd.setDate(dd.getDate() + 7); dd.setMinutes(0, 0, 0); rpIn.value = window.__siteDT ? window.__siteDT(dd) : ''; }
      var rpGo = $('#ps-dr-rpgo'); if (rpGo) rpGo.onclick = function () { var v = rpIn.value; if (!v) return toast(Q.pickT); rpGo.disabled = true; quick('repost', [id], { when: v, gap: 0 }).then(function (r) { rpGo.disabled = false; report(r, Q.reposted); }).catch(function (er) { rpGo.disabled = false; toast(er.message || X.err); }); };
      function egSet(n) { var fd = new FormData(); fd.append('action', 'evergreen'); fd.append('id', id); fd.append('every', n); return fetch(location.pathname + '?p=posts', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }); }
      var egOn = $('#ps-dr-egon'); if (egOn) egOn.onclick = function () { var n = Math.max(3, +$('#ps-dr-eg').value || 30); egSet(n).then(function (r) { if (r.ok) { d.eg = n; toast(Q.egSaved, true); } else toast(r.error || X.err); }); };
      var egOff = $('#ps-dr-egoff'); if (egOff) egOff.onclick = function () { egSet(0).then(function (r) { if (r.ok) { d.eg = 0; toast(Q.egSaved, true); egOff.remove(); } }); };
      var nGo = $('#ps-dr-ngo'); if (nGo) nGo.onclick = function () {
        var inp = $('#ps-dr-note'), v = inp.value.trim(); if (!v) return inp.focus(); nGo.disabled = true;
        quick('note', [id], { body: v }).then(function (r) {
          nGo.disabled = false; inp.value = ''; if (P[id]) P[id].notes = r.notes || []; $('#ps-dr-notes').innerHTML = notesHtml(r.notes);
          var c = $('.ps-card[data-id="' + CSS.escape(id) + '"] .ps-nc'); if (c) { c.classList.toggle('hidden', !(r.notes || []).length); $('b', c).textContent = (r.notes || []).length; }
        }).catch(function (er) { nGo.disabled = false; toast(er.message || X.err); });
      };
      var tg = $('#ps-dr-tsv'); if (tg) tg.onclick = function () { tg.disabled = true; quick('tags', [id], { tags: $('#ps-dr-tags').value }).then(function (r) { tg.disabled = false; setTags(r.tags || {}); toast(Q.tagSaved, true); }).catch(function (er) { tg.disabled = false; toast(er.message || X.err); }); };
      var ts = $('#ps-dr-tsave'); if (ts) ts.onclick = function () { var v = $('#ps-dr-txt').value.trim(); if (!v) return; ts.disabled = true; quick('text', [id], { content: v }).then(function (r) { ts.disabled = false; report(r, Q.done); }).catch(function (er) { ts.disabled = false; toast(er.message || X.err); }); };
      var sv = $('#ps-dr-save'); if (sv) sv.onclick = function () { var v = $('#ps-dr-when').value; if (!v) return; sv.disabled = true; quick('resched', [id], { when: v }).then(function (r) { sv.disabled = false; report(r, Q.done); }).catch(function (er) { sv.disabled = false; toast(er.message || X.err); }); };
      $('[data-close].btn-ghost', dr).focus();
      return true;
    }
    function close() { dr.classList.add('hidden'); dr.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; if (last) last.focus(); }
    $$('[data-close]', dr).forEach(function (x) { x.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (dr.classList.contains('hidden')) return; if (e.key === 'Escape') close(); });
    function step(k) { var ids = visibleIds(), i = ids.indexOf(cur) + k; if (i >= 0 && i < ids.length) open(ids[i]); }
    $('#ps-dr-prev').onclick = function () { step(-1); }; $('#ps-dr-next').onclick = function () { step(1); };
    document.addEventListener('click', function (e) {
      var o = e.target.closest('.ps-open'); if (o && !e.ctrlKey && !e.metaKey && !e.shiftKey && e.button === 0) { if (open(o.dataset.open)) e.preventDefault(); return; }
      var r = e.target.closest('.ps-retry'); if (r) { r.disabled = true; quick('retry', [r.closest('.ps-card').dataset.id]).then(function (d) { report(d, Q.retried); }).catch(function (er) { r.disabled = false; toast(er.message || X.err); }); return; }
      var a = e.target.closest('#ps-dr-acts [data-a]'); if (!a) return;
      if (a.dataset.a === 'retry') quick('retry', [cur]).then(function (d) { report(d, Q.retried); }).catch(function (er) { toast(er.message || X.err); });
      else if (a.dataset.a === 'dup') quick('dup', [cur]).then(function (d) { report(d, Q.duped); }).catch(function (er) { toast(er.message || X.err); });
      else if (a.dataset.a === 'del' && confirm(X.del)) { del([cur]); close(); }
    });
    var cb = $('#ps-csv'); if (cb) cb.onclick = function () {
      var q = function (v) { v = String(v == null ? '' : v); return /[",\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
      var rows = [Q.csvH].concat(visibleIds().map(function (id) { var d = P[id] || {}; return [id, d.stl || d.st, d.at ? (window.__siteDT ? window.__siteDT(new Date(d.at)).replace('T', ' ') : new Date(d.at).toLocaleString()) : '', (d.pl || []).map(function (x) { return x.pf; }).join('|'), d.txt, (d.pl || []).map(function (x) { return x.url; }).filter(Boolean).join(' '), (d.pl || []).map(function (x) { return x.err; }).filter(Boolean).join(' | '), d.m ? d.m.imp : '', d.m ? d.m.likes : '', d.m ? d.m.comments : '', d.m ? d.m.shares : '', d.m && d.m.rate !== null ? d.m.rate : '']; }));
      var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\uFEFF' + rows.map(function (r) { return r.map(q).join(','); }).join('\r\n')], { type: 'text/csv' })); a.download = 'posts-' + new Date().toISOString().slice(0, 10) + '.csv'; a.click();
    };
    var srt = $('#ps-sort'), pg = $('#ps-grid');
    if (srt && pg) {
      var orig = Array.prototype.slice.call(pg.children);
      try { srt.value = localStorage.getItem('ps-sort') || ''; } catch (e) {}
      var doSort = function () {
        var v = srt.value; try { localStorage.setItem('ps-sort', v); } catch (e) {}
        if (!v) { var extra = Array.prototype.slice.call(pg.children).filter(function (el) { return orig.indexOf(el) < 0; }); orig.forEach(function (el) { pg.appendChild(el); }); extra.forEach(function (el) { pg.appendChild(el); }); }
        else {
          var cards = $$('.ps-card', pg).sort(function (a, b) {
            if (v === 'eng') return (+b.dataset.eng) - (+a.dataset.eng);
            var ta = new Date(a.dataset.t).getTime() || 0, tb = new Date(b.dataset.t).getTime() || 0; return v === 'old' ? ta - tb : tb - ta;
          });
          cards.forEach(function (c) { pg.appendChild(c); });
        }
        $$('.ps-day', pg).forEach(function (h) { h.classList.toggle('hidden', !!v); });
      };
      srt.addEventListener('change', doSort); if (srt.value) doSort();
    }
    // جماعي
    var all = $('#ps-all'); if (all) all.onchange = function () { visibleIds().forEach(function (id) { if (all.checked) sel.add(id); else sel.delete(id); }); $$('.ps-card').forEach(function (c) { var cb = $('.ps-sel', c); if (cb) cb.checked = sel.has(c.dataset.id); }); bar(); };
    var sh = $('#ps-shift'); if (sh) sh.onclick = function () {
      if (!sel.size) return toast(Q.noSel); var n = +$('#ps-sh-n').value || 0, u = $('#ps-sh-u').value; if (!n) return;
      if (!confirm(Q.shiftQ.replace('%s', sel.size).replace('%s', n + ' ' + $('#ps-sh-u').selectedOptions[0].textContent))) return;
      sh.disabled = true; quick('shift', Array.from(sel), { n: n, unit: u }).then(function (d) { sh.disabled = false; report(d, Q.shifted); }).catch(function (er) { sh.disabled = false; toast(er.message || X.err); });
    };
    var br = $('#ps-bretry'); if (br) br.onclick = function () {
      var ids = Array.from(sel).filter(function (id) { return P[id] && (P[id].st === 'failed' || P[id].st === 'partial'); });
      if (!ids.length) return toast(Q.noSel); if (!confirm(Q.retryQ.replace('%s', ids.length))) return;
      quick('retry', ids).then(function (d) { report(d, Q.retried); }).catch(function (er) { toast(er.message || X.err); });
    };
    var rm = $('#ps-rep'), rb = $('#ps-brep');
    if (rm && rb) {
      var rInfo = function () {
        var f = $('#ps-rep-f').value, ids = Array.from(sel), hit = ids.filter(function (id) { var d = P[id]; return d && f && (d.st === 'scheduled' || d.st === 'draft') && String(d.txt || '').indexOf(f) > -1; });
        $('#ps-rep-info').textContent = !f ? '' : hit.length ? Q.repInfo.replace('%s', hit.length).replace('%s', ids.length) : Q.repNone;
        $('#ps-rep-go').disabled = !hit.length; return hit;
      };
      var rClose = function () { rm.classList.add('hidden'); rm.setAttribute('aria-hidden', 'true'); };
      $$('[data-close]', rm).forEach(function (x) { x.onclick = rClose; });
      rb.onclick = function () { if (!sel.size) return toast(Q.noSel); rm.classList.remove('hidden'); rm.setAttribute('aria-hidden', 'false'); $('#ps-rep-f').focus(); rInfo(); };
      $('#ps-rep-f').addEventListener('input', rInfo);
      $('#ps-rep-go').onclick = function () {
        var hit = rInfo(); if (!hit.length || !confirm(Q.repQ.replace('%s', hit.length))) return;
        var go = this; go.disabled = true;
        quick('replace', hit, { find: $('#ps-rep-f').value, repl: $('#ps-rep-r').value }).then(function (d) { go.disabled = false; rClose(); report(d, Q.replaced); }).catch(function (er) { go.disabled = false; toast(er.message || X.err); });
      };
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !rm.classList.contains('hidden')) rClose(); });
    }
    var rpAt = $('#ps-rp-at'), rpB = $('#ps-rp-go');
    if (rpAt && rpB) {
      var d0 = new Date(); d0.setDate(d0.getDate() + 1); d0.setHours(d0.getHours(), 0, 0, 0); rpAt.value = window.__siteDT ? window.__siteDT(d0) : '';
      rpB.onclick = function () {
        if (!sel.size) return toast(Q.noSel); if (!rpAt.value) return rpAt.focus();
        var gap = Math.max(0, +$('#ps-rp-gap').value || 0), ids = visibleIds().filter(function (i) { return sel.has(i); });
        if (!confirm(Q.rpQ.replace('%s', ids.length).replace('%s', rpAt.value.replace('T', ' ')).replace('%s', gap))) return;
        rpB.disabled = true; quick('repost', ids, { when: rpAt.value, gap: gap }).then(function (r) { rpB.disabled = false; report(r, Q.reposted); }).catch(function (er) { rpB.disabled = false; toast(er.message || X.err); });
      };
    }
    var btg = $('#ps-btag-go'); if (btg) btg.onclick = function () { var v = $('#ps-btag').value.trim(); if (!sel.size) return toast(Q.noSel); if (!v) return $('#ps-btag').focus(); btg.disabled = true; quick('tag_add', Array.from(sel), { tags: v }).then(function (r) { btg.disabled = false; setTags(r.tags || {}); toast(Q.tagged.replace('%s', (r.ok || []).length), true); $('#ps-btag').value = ''; }).catch(function (er) { btg.disabled = false; toast(er.message || X.err); }); };
    // "اعرض أكتر": الصفحة الجاية بتتضاف تحت اللي ظاهر (الفلاتر والترتيب بيشتغلوا عليها)
    var more = $('#ps-more'), pager = $('.ps-pager');
    if (more) {
      if (pager) pager.classList.add('hidden');
      more.onclick = function () {
        var o = more.innerHTML; more.disabled = true; more.textContent = Q.loading;
        fetch(more.dataset.next, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html'), g = $('#ps-grid'), src = doc.getElementById('ps-grid');
          var m = html.match(/window\.PSX = (\{[\s\S]*?\});<\/script>/);
          if (m) { try { Object.assign(P, JSON.parse(m[1]).posts || {}); } catch (e) {} }
          if (src && g) { Array.prototype.slice.call(src.children).forEach(function (el) { if (el.classList.contains('ps-card') && $('.ps-card[data-id="' + CSS.escape(el.dataset.id) + '"]', g)) return; g.appendChild(document.importNode(el, true)); }); }
          var pg2 = +more.dataset.page + 1, pages = +more.dataset.pages;
          if (pg2 >= pages) more.parentNode.remove();
          else { more.dataset.page = pg2; more.dataset.next = more.dataset.next.replace(/([?&]n=)\d+/, '$1' + (pg2 + 1)); more.disabled = false; more.innerHTML = o.replace(/\d+(\D+)\d+(\)?)\s*<\/span>/, (pg2 + 1) + '$1' + pages + '$2</span>'); }
          apply(); var sr = $('#ps-sort'); if (sr && sr.value) sr.dispatchEvent(new Event('change'));
          if (window.__psDens) window.__psDens();
        }).catch(function () { more.disabled = false; more.innerHTML = o; toast(X.err); });
      };
    }
    // j / k / Enter / x
    var kIdx = -1;
    document.addEventListener('keydown', function (e) {
      if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey || !dr.classList.contains('hidden')) return;
      var cards = $$('.ps-card').filter(function (c) { return c.style.display !== 'none'; }); if (!cards.length) return;
      if (e.key === 'j' || e.key === 'k') {
        e.preventDefault(); kIdx = Math.max(0, Math.min(cards.length - 1, kIdx + (e.key === 'j' ? 1 : -1)));
        cards.forEach(function (c, i) { c.classList.toggle('ring-4', i === kIdx); c.classList.toggle('ring-brand-500/40', i === kIdx); });
        cards[kIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      } else if (e.key === 'Enter' && kIdx > -1 && cards[kIdx]) { e.preventDefault(); open(cards[kIdx].dataset.id); }
      else if (e.key === 'x' && kIdx > -1 && cards[kIdx]) { var cb = $('.ps-sel', cards[kIdx]); if (cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change', { bubbles: true })); } }
    });
    var bdup = $('#ps-bdup'); if (bdup) bdup.onclick = function () { if (!sel.size) return toast(Q.noSel); bdup.disabled = true; quick('dup', Array.from(sel)).then(function (d) { bdup.disabled = false; report(d, Q.duped); }).catch(function (er) { bdup.disabled = false; toast(er.message || X.err); }); };
  })();
})();
