/* المصادر والإعدادات: كل أدوات جمع العملاء والإعدادات وفورمات الإعلانات ككروت؛ الكارت بيفتح محتواه في لوحة جانبية.
   أدوات الصفحة نفسها بتتنقل للوحة (بنفس الفورمات والأزرار)، والإعدادات وفورمات الإعلانات بتفتح صفحتها جوه اللوحة (embed). */
(function () {
  var H = window.HUB, hub = document.getElementById('hub'), dr = document.getElementById('hub-dr'); if (!H || !hub || !dr) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-5 h-5') : ''; }
  var nav = document.getElementById('cap-nav'), grid = nav ? nav.nextElementSibling : null; if (nav) nav.classList.add('hidden');
  // أقسام الأدوات بتستخبى وبتظهر جوه اللوحة بس
  var secs = {}; H.tiles.forEach(function (t) { if (t.g === 'cap') { var s = document.getElementById(t.id); if (s) { secs[t.id] = { el: s, home: s.parentNode, next: s.nextSibling }; s.classList.add('hidden'); } } });
  if (grid) grid.classList.add('hidden');
  hub.innerHTML = Object.keys(H.G).map(function (g) { var ts = H.tiles.filter(function (t) { return t.g === g; }); if (!ts.length) return '';
    return '<section class="hub-g"><b class="hub-gt">' + esc(H.G[g]) + '</b><div class="hub-grid">' + ts.map(function (t, i) {
      return '<button type="button" class="hub-t" data-t="' + esc(t.id) + '" style="--c:' + t.c + ';animation-delay:' + i * 30 + 'ms"><span class="hub-ic">' + ic(t.i) + '</span><b>' + esc(t.n) + '</b><small>' + esc(t.d) + '</small>' + (t.m ? '<em>' + esc(t.m) + '</em>' : '') + '<i class="hub-go">' + ic('chevron-left', 'w-4 h-4') + '</i></button>'; }).join('') + '</div></section>'; }).join('');
  var body = dr.querySelector('.hub-body'), cur = null;
  function close() { if (!dr.classList.contains('open')) return; dr.classList.remove('open'); dr.setAttribute('aria-hidden', 'true'); document.documentElement.classList.remove('hub-lock');
    if (cur && secs[cur]) { var s = secs[cur]; s.el.classList.add('hidden'); s.home.insertBefore(s.el, s.next); }
    body.innerHTML = ''; cur = null; try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {} }
  function open(id) { var t = H.tiles.filter(function (x) { return x.id === id; })[0]; if (!t) return; if (cur) close();
    cur = id; dr.querySelector('header b').textContent = t.n; var hi = dr.querySelector('header .hub-ic'); hi.innerHTML = ic(t.i); hi.style.setProperty('--c', t.c);
    if (t.u) { body.innerHTML = '<div class="hub-load"><span class="ai-spin"></span></div><iframe src="' + esc(t.u) + '" title="' + esc(t.n) + '"></iframe>'; var fr = body.querySelector('iframe'); fr.addEventListener('load', function () { var l = body.querySelector('.hub-load'); if (l) l.remove(); }); }
    else if (secs[id]) { var s = secs[id].el; s.classList.remove('hidden'); body.appendChild(s); var h = s.querySelector('h2'); if (h) h.classList.add('hidden'); }
    dr.classList.add('open'); dr.setAttribute('aria-hidden', 'false'); document.documentElement.classList.add('hub-lock');
    try { history.replaceState(null, '', '#' + id); } catch (e) {} }
  hub.addEventListener('click', function (e) { var b = e.target.closest('[data-t]'); if (b) open(b.dataset.t); });
  dr.addEventListener('click', function (e) { if (e.target.closest('[data-hx]')) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dr.classList.contains('open') && !document.querySelector('dialog[open]')) close(); });
  // بعد حفظ فورم الصفحة بترجع على #lp مثلًا: نفتح نفس الأداة
  function fromHash() { var h = location.hash.replace('#', ''); if (h && h !== cur && H.tiles.some(function (t) { return t.id === h; })) open(h); }
  window.addEventListener('hashchange', fromHash); fromHash();
})();
