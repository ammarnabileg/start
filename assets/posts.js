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
    function draw() {
      $$('.cal-v').forEach(function (b) { var on = b.dataset.calv === calV; b.classList.toggle('bg-white', on); b.classList.toggle('shadow-sm', on); b.classList.toggle('font-medium', on); b.setAttribute('aria-pressed', on); });
      var w = calV === 'week' && weekBox; grid.classList.toggle('hidden', w); dow.classList.toggle('hidden', w); if (weekBox) weekBox.classList.toggle('hidden', !w);
      return w ? drawWeek() : drawMonth();
    }
    function filtered() { var v = q ? q.value.trim().toLowerCase() : ''; return X.data.filter(function (p) { if (v && p.txt.toLowerCase().indexOf(v) < 0) return false; if (pf && p.pf.indexOf(pf) < 0) return false; return !isNaN(new Date(p.at)); }); }
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
      X.data.forEach(function (p) { if (v && p.txt.toLowerCase().indexOf(v) < 0) return; if (pf && p.pf.indexOf(pf) < 0) return; var d = new Date(p.at); if (isNaN(d)) return; var sp = siteParts(d), kk = sp.y + '-' + sp.m + '-' + sp.d; (by[kk] = by[kk] || []).push(p); });
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
        toast(Q.moved.replace('%s', nd.toLocaleString(lang, { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })), true);
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
          (p.err ? '<div class="text-xs text-red-700 mt-2 break-words"><b>' + esc(Q.reason) + ':</b> ' + esc(p.err) + '</div>' : '') +
          (p.url ? '<a class="link text-xs mt-1.5 inline-block" href="' + esc(p.url) + '" target="_blank" rel="noopener">' + esc(Q.open) + ' ↗</a>' : '') + '</div>';
      }).join('');
      var canRes = X.canDel && (d.st === 'scheduled' || d.st === 'draft');
      body.innerHTML = '<div class="flex flex-wrap items-center gap-2 text-xs"><span class="rounded-md px-1.5 py-0.5 bg-ink-100 text-ink-700 font-medium">' + esc(d.stl) + '</span>' + (when ? '<span class="text-ink-600" dir="auto">' + esc(when.toLocaleString(document.documentElement.lang || undefined, { weekday: 'long', day: 'numeric', month: 'long', hour: 'numeric', minute: '2-digit' })) + '</span>' : '') + '</div>' +
        '<div class="text-sm text-ink-900 whitespace-pre-wrap break-words leading-relaxed" dir="auto">' + (d.txt ? esc(d.txt).replace(/(#[\p{L}\p{N}_]+)/gu, '<span class="text-brand-700">$1</span>') : '<span class="text-ink-400 italic">—</span>') + '</div>' +
        (media ? '<div class="space-y-2">' + media + '</div>' : '') +
        (canRes ? '<details class="rounded-xl border border-ink-200 p-3"><summary class="cursor-pointer text-xs font-semibold text-ink-800">✎ ' + esc(Q.editT) + '</summary><textarea id="ps-dr-txt" rows="6" class="input mt-2 text-sm" dir="auto">' + esc(d.txt) + '</textarea><div class="flex justify-end mt-2"><button type="button" id="ps-dr-tsave" class="btn-primary !text-xs">' + esc(Q.save) + '</button></div></details>' : '') +
        (canRes ? '<div class="rounded-xl border border-brand-200 bg-brand-50/50 p-3"><div class="text-xs font-semibold text-ink-800 mb-2">🗓 ' + esc(Q.resched) + '</div><div class="flex gap-2"><input type="datetime-local" id="ps-dr-when" class="input !mt-0 !h-9 text-sm flex-1" dir="ltr" value="' + (d.sched ? (window.__siteDT ? window.__siteDT(new Date(d.sched)) : local(new Date(d.sched))) : '') + '"><button type="button" id="ps-dr-save" class="btn-primary !text-xs">' + esc(Q.save) + '</button></div></div>' : '') +
        (Q.canTag ? '<div class="rounded-xl border border-violet-200 bg-violet-50/40 p-3"><div class="text-xs font-semibold text-ink-800 mb-2">🏷 ' + esc(Q.tagsL) + '</div><div class="flex gap-2"><input id="ps-dr-tags" class="input !mt-0 !h-9 text-sm flex-1" list="ps-taglist" placeholder="' + esc(Q.tagsPh) + '" value="' + esc((d.tags || []).join('، ')) + '"><button type="button" id="ps-dr-tsv" class="btn-ghost !text-xs">' + esc(Q.save) + '</button></div></div>' : '') +
        (d.m ? '<div class="rounded-xl border border-ink-200 p-3"><div class="text-xs font-semibold text-ink-500 mb-2">📈 ' + esc(Q.perf) + '</div><div class="grid grid-cols-4 gap-2 text-center">' +
          [[Q.mImp, d.m.imp], [Q.mLikes, d.m.likes], [Q.mCom, d.m.comments], [Q.mSh, d.m.shares]].map(function (x) { return '<div><div class="text-lg font-bold text-ink-900 tabular-nums">' + Number(x[1] || 0).toLocaleString() + '</div><div class="text-[10px] text-ink-500">' + esc(x[0]) + '</div></div>'; }).join('') +
          '</div>' + (d.m.rate !== null && d.m.rate !== undefined ? '<div class="text-xs text-ink-600 mt-2 text-center">' + esc(Q.mRate) + ': <b>' + d.m.rate + '%</b></div>' : '') + '</div>' : '') +
        (pls ? '<div class="space-y-2"><div class="text-xs font-semibold text-ink-500">' + esc(Q.pfL) + '</div>' + pls + '</div>' : '');
      acts.innerHTML = '<a class="btn-primary !text-xs" href="' + esc(X.edit.replace('__ID__', encodeURIComponent(id))) + '">✎ ' + esc(Q.edit) + '</a>' +
        ((d.st === 'failed' || d.st === 'partial') && X.canDel ? '<button type="button" data-a="retry" class="btn-ghost !text-xs text-red-700">↻ ' + esc(Q.retry) + '</button>' : '') +
        (X.canDup ? '<button type="button" data-a="dup" class="btn-ghost !text-xs">⧉ ' + esc(Q.dup) + '</button>' : '') +
        (X.canDel && d.st !== 'publishing' ? '<button type="button" data-a="del" class="btn-ghost !text-xs text-red-600 ms-auto">🗑 ' + esc(Q.del) + '</button>' : '');
      dr.classList.remove('hidden'); dr.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
      var ids = visibleIds(), i = ids.indexOf(id); $('#ps-dr-prev').disabled = i <= 0; $('#ps-dr-next').disabled = i < 0 || i >= ids.length - 1;
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
