/* المصادر والإعدادات: قايمة جانبية ثابتة بالأقسام (نظرة عامة / جمع العملاء / الإعلانات / الإعدادات)، والقسم المختار بيتفتح جنبها مباشرة.
   أدوات الصفحة نفسها بتتنقل للمحتوى (بنفس الفورمات)، وصفحات الإعدادات وفورمات الإعلانات بتتفتح embed جوه المحتوى. */
(function () {
  var H = window.HUB, hub = document.getElementById('hub'), dr = document.getElementById('hub-dr'); if (!H || !hub || !dr) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-5 h-5') : ''; }
  var nav = document.getElementById('cap-nav'), grid = nav ? nav.nextElementSibling : null; if (nav) nav.classList.add('hidden');
  var secs = {}; H.tiles.forEach(function (t) { if (t.g === 'cap') { var s = document.getElementById(t.id); if (s) { secs[t.id] = { el: s, home: s.parentNode, next: s.nextSibling }; s.classList.add('hidden'); } } });
  if (grid) grid.classList.add('hidden');
  var ov = document.getElementById('cap-ov'); if (ov) ov.classList.add('hidden');
  var tiles = [{ g: '_', id: 'ov', i: 'chart', c: '#0f172a', n: H.T.ov, d: H.T.ovD }].concat(H.tiles), G = Object.assign({ _: '' }, H.G);
  // الهيكل: قايمة + محتوى
  dr.className = 'hub2-main'; dr.removeAttribute('aria-hidden'); dr.innerHTML = '<header class="hub2-h"><span class="hub-ic"></span><div class="min-w-0"><b></b><small></small></div></header><div class="hub-body"></div>';
  hub.className = 'hub2'; hub.innerHTML = '<nav class="hub2-nav">' + Object.keys(G).map(function (g) { var ts = tiles.filter(function (t) { return t.g === g; }); if (!ts.length) return '';
    return (G[g] ? '<b class="hub2-gt">' + esc(G[g]) + '</b>' : '') + ts.map(function (t) {
      return '<button type="button" class="hub2-i" data-t="' + esc(t.id) + '" style="--c:' + t.c + '"><span class="hub-ic">' + ic(t.i, 'w-4 h-4') + '</span><span class="hub2-n">' + esc(t.n) + '</span>' + (t.m ? '<em>' + esc(t.m) + '</em>' : '') + (t.go ? ic('chevron-left', 'w-3.5 h-3.5 opacity-50') : '') + '</button>'; }).join(''); }).join('') + '</nav>';
  hub.appendChild(dr);
  var body = dr.querySelector('.hub-body'), cur = null;
  function park() { if (cur && secs[cur]) { var s = secs[cur]; s.el.classList.add('hidden'); s.home.insertBefore(s.el, s.next); } if (cur === 'ov' && ov) { ov.classList.add('hidden'); hub.after(ov); } body.innerHTML = ''; }
  function open(id, keepHash) { var t = tiles.filter(function (x) { return x.id === id; })[0]; if (!t) return; if (t.go) { location.href = t.u; return; } park(); cur = id;
    hub.querySelectorAll('.hub2-i').forEach(function (b) { b.classList.toggle('on', b.dataset.t === id); });
    dr.querySelector('.hub2-h b').textContent = t.n; dr.querySelector('.hub2-h small').textContent = t.d || ''; var hi = dr.querySelector('.hub2-h .hub-ic'); hi.innerHTML = ic(t.i); hi.style.setProperty('--c', t.c);
    if (id === 'ov') { if (ov) { ov.classList.remove('hidden'); body.appendChild(ov); } }
    else if (t.u) { body.innerHTML = '<div class="hub-load"><span class="ai-spin"></span></div><iframe src="' + esc(t.u) + '" title="' + esc(t.n) + '"></iframe>'; var fr = body.querySelector('iframe');
      fr.addEventListener('load', function () { var l = body.querySelector('.hub-load'); if (l) l.remove(); try { var fit = function () { fr.style.height = Math.max(420, fr.contentDocument.documentElement.scrollHeight) + 'px'; }; fit(); new ResizeObserver(fit).observe(fr.contentDocument.body); } catch (e) {} }); }
    else if (t.x && window.HubX && HubX[id]) { HubX[id](body); }
    else if (secs[id]) { var s = secs[id].el; s.classList.remove('hidden'); body.appendChild(s); var h = s.querySelector('h2'); if (h) h.classList.add('hidden'); if (window.HubE && HubE[id]) HubE[id](s); }
    dr.classList.remove('hub2-in'); void dr.offsetWidth; dr.classList.add('hub2-in');
    if (!keepHash) try { history.replaceState(null, '', '#' + id); } catch (e) {}
    if (innerWidth < 900) dr.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  hub.addEventListener('click', function (e) { var b = e.target.closest('.hub2-nav [data-t]'); if (b) open(b.dataset.t); });
  function fromHash() { var h = location.hash.replace('#', ''); if (h && h !== cur && tiles.some(function (t) { return t.id === h; })) open(h, true); }
  window.addEventListener('hashchange', fromHash);
  if (location.hash && tiles.some(function (t) { return t.id === location.hash.slice(1); })) fromHash(); else open('ov', true);
})();
