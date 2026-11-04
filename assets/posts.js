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
  var q = $('#ps-q'), pf = null;
  function apply() {
    var v = q ? q.value.trim().toLowerCase() : '', n = 0;
    $$('.ps-card').forEach(function (c) { var hit = (!v || c.dataset.q.indexOf(v) > -1) && (!pf || (' ' + c.dataset.pf + ' ').indexOf(' ' + pf + ' ') > -1); c.style.display = hit ? '' : 'none'; if (hit) n++; });
    var em = $('#ps-empty'); if (em) em.classList.toggle('hidden', n > 0 || !$$('.ps-card').length);
    if (window.__cal) window.__cal();
  }
  if (q) q.addEventListener('input', apply);
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
    function draw() {
      var v = q ? q.value.trim().toLowerCase() : '', by = {};
      X.data.forEach(function (p) { if (v && p.txt.toLowerCase().indexOf(v) < 0) return; if (pf && p.pf.indexOf(pf) < 0) return; var d = new Date(p.at); if (isNaN(d)) return; (by[key(d)] = by[key(d)] || []).push(p); });
      $('#cal-title').textContent = cur.toLocaleDateString(lang, { month: 'long', year: 'numeric' });
      grid.innerHTML = '';
      var first = new Date(cur), off = (first.getDay() - start + 7) % 7; first.setDate(1 - off);
      var today = key(new Date());
      for (var k = 0; k < 42; k++) {
        var d = new Date(first); d.setDate(first.getDate() + k);
        var inM = d.getMonth() === cur.getMonth(), items = (by[key(d)] || []).sort(function (a, b) { return new Date(a.at) - new Date(b.at); }), fut = d >= new Date(new Date().setHours(0, 0, 0, 0));
        var cell = document.createElement('div');
        cell.className = 'group relative min-h-[7.5rem] border-b border-e border-ink-100 p-1.5 transition ' + (inM ? 'bg-white' : 'bg-ink-50/60') + (fut && inM ? ' hover:bg-brand-50/30' : '');
        cell.innerHTML = '<div class="flex items-center justify-between mb-1"><span class="text-xs tabular-nums w-6 h-6 rounded-full flex items-center justify-center ' + (key(d) === today ? 'bg-brand-600 text-[#fff] font-semibold' : inM ? 'text-ink-700' : 'text-ink-300') + '">' + d.getDate() + '</span>' + (fut && inM ? '<a class="opacity-0 group-hover:opacity-100 transition w-6 h-6 rounded-lg flex items-center justify-center text-brand-600 hover:bg-brand-100" title="' + esc(X.newHere) + '" href="' + X.compose + '&when=' + d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T10:00">+</a>' : '') + '</div>';
        items.slice(0, 3).forEach(function (p) {
          var a = document.createElement('a'); a.href = X.edit.replace('__ID__', encodeURIComponent(p.id));
          a.className = 'flex items-center gap-1 rounded-md px-1.5 py-1 mb-0.5 text-[11px] text-ink-700 bg-ink-50 hover:bg-brand-50 hover:text-brand-800 transition';
          var t = new Date(p.at);
          a.innerHTML = '<span class="w-1.5 h-1.5 rounded-full shrink-0 ' + (dot[p.st] || 'bg-ink-300') + '"></span><span class="tabular-nums text-ink-400 shrink-0">' + t.toLocaleTimeString(lang, { hour: 'numeric', minute: '2-digit' }) + '</span><span class="truncate flex-1" dir="auto"></span><span class="flex shrink-0">' + p.pf.slice(0, 2).map(function (x) { return X.icons[x] || ''; }).join('') + '</span>';
          a.querySelector('.truncate').textContent = p.txt || '—'; a.title = p.txt;
          cell.appendChild(a);
        });
        if (items.length > 3) { var mo = document.createElement('button'); mo.type = 'button'; mo.className = 'text-[11px] text-brand-700 px-1.5 hover:underline'; mo.textContent = X.more.replace('%s', items.length - 3);
          mo.onclick = (function (list, c) { return function () { this.remove(); list.slice(3).forEach(function (p) { var a = document.createElement('a'); a.href = X.edit.replace('__ID__', encodeURIComponent(p.id)); a.className = 'flex items-center gap-1 rounded-md px-1.5 py-1 mb-0.5 text-[11px] text-ink-700 bg-ink-50 hover:bg-brand-50'; a.innerHTML = '<span class="w-1.5 h-1.5 rounded-full shrink-0 ' + (dot[p.st] || 'bg-ink-300') + '"></span><span class="truncate" dir="auto"></span>'; a.querySelector('.truncate').textContent = p.txt || '—'; c.appendChild(a); }); }; })(items, cell);
          cell.appendChild(mo); }
        grid.appendChild(cell);
      }
    }
    window.__cal = draw;
    $$('[data-cal]').forEach(function (b) { b.onclick = function () { if (b.dataset.cal === 'today') { cur = new Date(); cur.setDate(1); } else cur.setMonth(cur.getMonth() + (b.dataset.cal === 'next' ? 1 : -1)); draw(); }; });
    document.addEventListener('keydown', function (e) { if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return; if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { var rtl = document.documentElement.dir === 'rtl'; cur.setMonth(cur.getMonth() + ((e.key === 'ArrowLeft') !== rtl ? -1 : 1)); draw(); } });
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
})();
