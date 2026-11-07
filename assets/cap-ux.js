/* جمع العملاء: نظرة عامة (مصادر العملاء آخر 30 يوم مقارنة باللي قبلها، زيارات الصفحات، ضغطات واتساب وتحويلها، الإحالات)،
   شريط الأقسام ثابت ومتعلّم عليه القسم الحالي، والقوايم الطويلة بتتختصر لأفضل 5 مع بحث وشريط أداء */
(function () {
  var U = window.CAPU; if (!U) return; var T = U.T;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-5 h-5') : ''; }
  function f(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }
  function num(v) { return (+v || 0).toLocaleString('en-US'); }
  var COL = { form: '#2563eb', inbox: '#7c3aed', comments: '#db2777', ads: '#ea580c', whatsapp: '#16a34a', reveal: '#0891b2', import: '#64748b', manual: '#94a3b8', tender: '#d97706' };

  /* ---------- النظرة العامة ---------- */
  var ov = document.getElementById('cap-ov');
  if (ov) { var src = U.src.slice().sort(function (a, b) { return b.a - a.a; }), A = src.reduce(function (s, x) { return s + x.a; }, 0), B = src.reduce(function (s, x) { return s + x.b; }, 0), mx = Math.max.apply(null, src.map(function (x) { return x.a; }).concat([1]));
    var d = B ? Math.round((A - B) / B * 100) : null, conv = U.waClicks ? Math.round(U.waLeads / U.waClicks * 100) + '%' : '—';
    var k = function (v, l, sub, c, i) { return '<div class="cap-k" style="--c:' + c + '"><span>' + ic(i, 'w-4 h-4') + '</span><b>' + esc(v) + '</b><small>' + esc(l) + '</small>' + (sub ? '<em>' + sub + '</em>' : '') + '</div>'; };
    ov.innerHTML = '<section class="cap-ov"><div class="grid grid-cols-2 lg:grid-cols-4 gap-3">' +
      k(num(A), T.new, d == null ? '' : '<i class="' + (d >= 0 ? 'up' : 'down') + '">' + (d >= 0 ? '▲ ' : '▼ ') + Math.abs(d) + '%</i> ' + esc(f(T.vs, '')).trim(), '#2563eb', 'users') +
      k(num(U.lpViews), T.lp, esc(U.lpN + ' ×'), '#7c3aed', 'file') + k(num(U.waClicks), T.wa, esc(f(T.waConv, num(U.waLeads), conv)), '#16a34a', 'chat') + k(num(U.ref), T.ref, '', '#d97706', 'heart') + '</div>' +
      '<div class="mt-4"><div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900">' + esc(T.title) + '</b>' + (src[0] && src[0].a ? '<span class="text-xs text-ink-500">· ' + esc(f(T.best, src[0].l)) + '</span>' : '') + '<a class="link text-xs ms-auto" href="' + esc(U.prospects) + '">' + esc(T.open) + '</a></div>' +
      (A || B ? '<div class="space-y-1.5">' + src.filter(function (x) { return x.a || x.b; }).map(function (x) { var dd = x.b ? Math.round((x.a - x.b) / x.b * 100) : null;
        return '<div class="cap-src"><span class="truncate">' + esc(x.l) + '</span><span class="cap-bar"><i style="--w:' + (x.a / mx * 100) + '%;--c:' + (COL[x.k] || '#64748b') + '"></i></span><b>' + num(x.a) + '</b><em class="' + (dd == null ? '' : dd >= 0 ? 'up' : 'down') + '">' + (dd == null ? (x.a && B ? esc(T.fresh) : '') : (dd >= 0 ? '▲' : '▼') + Math.abs(dd) + '%') + '</em></div>'; }).join('') + '</div>'
        : '<p class="text-sm text-ink-500">' + esc(T.none) + '</p>') + '</div></section>';
    requestAnimationFrame(function () { requestAnimationFrame(function () { ov.querySelectorAll('.cap-bar i').forEach(function (i) { i.classList.add('go'); }); }); }); }

  /* ---------- شريط الأقسام ---------- */
  var nav = document.getElementById('cap-nav');
  if (nav) { nav.classList.add('cap-nav'); var links = [].slice.call(nav.querySelectorAll('a[href^="#"]'));
    var spy = function () { var y = 140, cur = null; links.forEach(function (a) { var s = document.querySelector(a.getAttribute('href')); if (s && s.getBoundingClientRect().top - y <= 0) cur = a; }); links.forEach(function (a) { a.classList.toggle('on', a === cur); }); };
    window.addEventListener('scroll', spy, { passive: true }); spy(); }

  /* ---------- القوايم الطويلة ---------- */
  ['#lp', '#wa'].forEach(function (sel) { var sec = document.querySelector(sel); if (!sec) return; var rows = [].slice.call(sec.querySelectorAll('.cap-row')); if (!rows.length) return;
    var par = rows[0].parentNode, mx = Math.max.apply(null, rows.map(function (r) { return +r.dataset.v || 0; }).concat([1])), open = false, q = '';
    rows.sort(function (a, b) { return ((+b.dataset.l || 0) * 1000 + (+b.dataset.v || 0)) - ((+a.dataset.l || 0) * 1000 + (+a.dataset.v || 0)); }).forEach(function (r) { par.appendChild(r);
      r.insertAdjacentHTML('beforeend', '<span class="cap-mini" title="' + num(r.dataset.v) + '"><i style="width:' + Math.max(3, (+r.dataset.v || 0) / mx * 100) + '%"></i></span>'); });
    if (rows.length <= 5) return;
    var tools = document.createElement('div'); tools.className = 'flex items-center gap-2 mt-2';
    tools.innerHTML = '<label class="mk-search !w-56 !h-9"><input type="search" placeholder="' + esc(T.find) + '"></label><button type="button" class="link text-xs ms-auto"></button>'; par.after(tools);
    var btn = tools.querySelector('button'), inp = tools.querySelector('input');
    var paint = function () { var n = 0; rows.forEach(function (r) { var hit = !q || (r.dataset.q || '').indexOf(q) >= 0; r.style.display = hit && (open || q || n < 5) ? '' : 'none'; if (hit) n++; }); btn.textContent = open ? T.less : f(T.all, rows.length); btn.style.display = q ? 'none' : ''; };
    btn.addEventListener('click', function () { open = !open; paint(); }); inp.addEventListener('input', function () { q = inp.value.trim().toLowerCase(); paint(); }); paint(); });
})();
