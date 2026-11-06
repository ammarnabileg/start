/* Ctrl+K: بحث في الصفحات والإجراءات والعملاء — بحث نصي عادي، من غير ذكاء اصطناعي */
(function () {
  var C = window.CK; if (!C) return;
  var box = document.getElementById('ck'), inp = document.getElementById('ck-in'), list = document.getElementById('ck-list'), items = [], idx = 0, tmr = null, seq = 0;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  // تطبيع عربي بسيط: أ/إ/آ → ا، ة → ه، ى → ي، ومن غير تشكيل
  function norm(s) { return String(s || '').toLowerCase().replace(/[ً-ْـ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي'); }
  function score(t, q) { t = norm(t); if (!q) return 1; if (t.indexOf(q) === 0) return 3; if (t.indexOf(' ' + q) > -1) return 2; if (t.indexOf(q) > -1) return 1; var i = 0; for (var j = 0; j < t.length && i < q.length; j++) if (t[j] === q[i]) i++; return i === q.length ? 0.5 : 0; }
  function recent() { try { return JSON.parse(localStorage.getItem('ck-recent') || '[]'); } catch (e) { return []; } }
  function remember(it) { try { var r = recent().filter(function (x) { return x.u !== it.u; }); r.unshift({ l: it.l, u: it.u, g: it.g || '' }); localStorage.setItem('ck-recent', JSON.stringify(r.slice(0, 5))); } catch (e) {} }
  function icon(d) { return '<svg class="w-4 h-4 shrink-0 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' + esc(d) + '"/></svg>'; }
  function render(leads) {
    var q = norm(inp.value.trim()), html = '', groups = [];
    if (!q) { var r = recent(); if (r.length) groups.push([C.tr.recent, r.map(function (x) { return { l: x.l, g: x.g, u: x.u, ic: uic('clock', 'w-4 h-4 text-ink-400') }; })]); }
    var acts = C.acts.map(function (a) { return { l: a.l, u: a.u, ic: uic('zap', 'w-4 h-4 text-amber-500'), s: score(a.l, q) }; }).filter(function (a) { return a.s > 0; }).sort(function (a, b) { return b.s - a.s; });
    var pages = C.pages.map(function (p) { return { l: p.l, g: p.g || p.s, u: p.u, ic: icon(p.i), s: Math.max(score(p.l, q), score(p.g || '', q) * 0.8, score(p.s || '', q) * 0.6) }; }).filter(function (p) { return p.s > 0; }).sort(function (a, b) { return b.s - a.s; });
    if (leads && leads.length) groups.push([C.tr.leads, leads.map(function (l) { return { l: l.name, g: l.sub, u: C.lead + l.id, ic: uic('magnet', 'w-4 h-4 text-lime-600') }; })]);
    if (acts.length) groups.push([C.tr.acts, acts.slice(0, q ? 5 : 6)]);
    if (pages.length) groups.push([C.tr.pages, pages.slice(0, q ? 8 : 40)]);
    items = [];
    groups.forEach(function (g) {
      html += '<div class="px-2 pt-2 pb-1 text-[11px] font-semibold text-ink-400">' + esc(g[0]) + '</div>';
      g[1].forEach(function (it) { var n = items.length; items.push(it); html += '<a href="' + esc(it.u) + '" data-i="' + n + '" class="ck-it flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-ink-800"><span class="w-5 text-center">' + (it.ic && it.ic[0] === '<' ? it.ic : esc(it.ic || '')) + '</span><span class="flex-1 min-w-0 truncate">' + esc(it.l) + (it.g ? ' <span class="text-[11px] text-ink-400">· ' + esc(it.g) + '</span>' : '') + '</span></a>'; });
    });
    list.innerHTML = html || '<p class="p-6 text-center text-sm text-ink-400">' + esc(C.tr.none) + '</p>';
    idx = 0; hl();
  }
  function hl() { Array.prototype.forEach.call(list.querySelectorAll('.ck-it'), function (a) { var on = +a.dataset.i === idx; a.classList.toggle('bg-brand-50', on); a.classList.toggle('text-brand-800', on); if (on) a.scrollIntoView({ block: 'nearest' }); }); }
  function go(it) { if (!it) return; remember(it); close(); location.href = it.u; if (/#/.test(it.u) && it.u.split('#')[0] === location.href.split('#')[0]) location.reload(); }
  function open() { box.classList.remove('hidden'); box.classList.add('flex'); box.setAttribute('aria-hidden', 'false'); inp.value = ''; render(); setTimeout(function () { inp.focus(); }, 10); }
  function close() { box.classList.add('hidden'); box.classList.remove('flex'); box.setAttribute('aria-hidden', 'true'); }
  inp.addEventListener('input', function () {
    render(); clearTimeout(tmr); var q = inp.value.trim(); if (q.length < 2) return;
    var my = ++seq; tmr = setTimeout(function () { fetch(C.search + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (my === seq) render(j.leads); }).catch(function () {}); }, 180);
  });
  inp.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(items.length - 1, idx + 1); hl(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(0, idx - 1); hl(); }
    else if (e.key === 'Enter') { e.preventDefault(); go(items[idx]); }
    else if (e.key === 'Escape') close();
  });
  list.addEventListener('click', function (e) { var a = e.target.closest('.ck-it'); if (!a) return; e.preventDefault(); go(items[+a.dataset.i]); });
  list.addEventListener('mousemove', function (e) { var a = e.target.closest('.ck-it'); if (a && +a.dataset.i !== idx) { idx = +a.dataset.i; hl(); } });
  box.addEventListener('click', function (e) { if (e.target === box) close(); });
  ['ck-open', 'ck-open-m'].forEach(function (id) { var b = document.getElementById(id); if (b) b.addEventListener('click', open); });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); box.classList.contains('hidden') ? open() : close(); }
  });
})();
