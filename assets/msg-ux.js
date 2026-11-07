/* الرسائل والتعليقات: لوحة اليوم مكان الشاشة الفاضية (عدادات بتفتح أول محادثة/منشور مطابق، و"ابدأ بالأهم")،
   وفي التعليقات: أزرار واضحة لكل تعليق، و"التالي من غير رد" اللي بيوديك على التعليق ويفتح الرد عليه */
(function () {
  var U = window.MSGU; if (!U) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-5 h-5') : ''; }
  function f(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }

  var nb = null, idx = -1;
  function init() {
  /* ---------- الرسائل ---------- */
  var ie = document.getElementById('ibx-empty');
  if (ie && !ie.dataset.ux) { ie.dataset.ux = 1; var items = [].slice.call(document.querySelectorAll('.ibx-item')), href = function (it) { var a = it.querySelector('a[href*="c="]'); return a ? a.href : ''; },
      open = function (x) { return x.dataset.state !== 'closed'; }, rank = function (x) { return (+x.dataset.urg ? 8 : 0) + (+x.dataset.intent ? 4 : 0) + (+x.dataset.unread ? 2 : 0) + (+x.dataset.mine ? 1 : 0); },
      cats = [['urg', U.urgent, 'alert', '#dc2626', function (x) { return +x.dataset.urg; }], ['intent', U.intent, 'wallet', '#16a34a', function (x) { return +x.dataset.intent; }], ['unread', U.unread, 'chat', '#2563eb', function (x) { return +x.dataset.unread; }], ['mine', U.mine, 'user', '#7c3aed', function (x) { return +x.dataset.mine; }]];
    var live = items.filter(open), best = live.slice().sort(function (a, b) { return rank(b) - rank(a); })[0];
    ie.innerHTML = '<div class="msg-ov"><div class="msg-hi">' + ic('chat', 'w-7 h-7') + '</div><b class="block text-lg text-ink-900">' + esc(f(U.hello, live.length)) + '</b><p class="text-xs text-ink-500 mt-1">' + esc(U.sub) + '</p>' +
      '<div class="msg-grid">' + cats.map(function (c) { var l = live.filter(c[4]); return '<button type="button" class="msg-k' + (l.length ? '' : ' zero') + '" style="--c:' + c[3] + '" data-go="' + esc(l[0] ? href(l[0]) : '') + '"' + (l.length ? '' : ' disabled') + '><span>' + ic(c[2], 'w-4 h-4') + '</span><b>' + l.length + '</b><small>' + esc(c[1]) + '</small></button>'; }).join('') + '</div>' +
      (best ? '<a class="btn-primary mt-4" href="' + esc(href(best)) + '">' + ic('chevron-left', 'w-4 h-4') + esc(U.start) + '<small class="opacity-80 ms-1">· ' + esc((best.querySelector('.truncate') || {}).textContent || '') + '</small></a>' : '<p class="text-sm text-lime-700 mt-4">✓ ' + esc(U.clear) + '</p>') +
      '<p class="text-[11px] text-ink-400 mt-4"><kbd class="msg-kbd">J</kbd> <kbd class="msg-kbd">K</kbd> ' + esc(U.nav) + '</p></div>';
    ie.addEventListener('click', function (e) { var b = e.target.closest('[data-go]'); if (b && b.dataset.go) location.href = b.dataset.go; }); }

  /* ---------- التعليقات: الشاشة الفاضية ---------- */
  var ce = document.getElementById('cm-empty');
  if (ce && !ce.dataset.ux) { ce.dataset.ux = 1; var posts = [].slice.call(document.querySelectorAll('a.cm-post')), tot = posts.reduce(function (s, a) { return s + (+a.dataset.cc || 0); }, 0), leads = posts.reduce(function (s, a) { return s + (+a.dataset.ln || 0); }, 0), top = posts.slice().sort(function (a, b) { return (+b.dataset.cc || 0) - (+a.dataset.cc || 0); }).slice(0, 3);
    ce.innerHTML = '<div class="msg-ov"><div class="msg-hi">' + ic('chat', 'w-7 h-7') + '</div><b class="block text-lg text-ink-900">' + esc(f(U.cHello, tot, posts.length)) + '</b><p class="text-xs text-ink-500 mt-1">' + esc(U.cSub) + '</p>' +
      '<div class="msg-grid !grid-cols-2"><div class="msg-k" style="--c:#2563eb"><span>' + ic('chat', 'w-4 h-4') + '</span><b>' + tot + '</b><small>' + esc(U.comments) + '</small></div><div class="msg-k" style="--c:#16a34a"><span>' + ic('users', 'w-4 h-4') + '</span><b>' + leads + '</b><small>' + esc(U.leads) + '</small></div></div>' +
      (top.length ? '<div class="msg-top"><small>' + esc(U.hot) + '</small>' + top.map(function (a) { var t = (a.querySelector('p') || {}).textContent || ''; return '<a href="' + esc(a.href) + '"><span class="truncate" dir="auto">' + esc(t.trim().slice(0, 70)) + '</span><b>' + esc(f(U.nC, a.dataset.cc)) + '</b></a>'; }).join('') + '</div>' : '') + '</div>'; }

  /* ---------- التعليقات: أزرار الأفعال ---------- */
  document.querySelectorAll('.cm-acts:not(.msg-acts)').forEach(function (a) { a.classList.add('msg-acts'); var r = a.querySelector('[data-reply-to]'); if (r && !r.querySelector('svg')) r.innerHTML = ic('chat', 'w-3.5 h-3.5') + esc(r.textContent); });
  paint();
  }
  /* ---------- التالي من غير رد ---------- */
  function cms() { return [].slice.call(document.querySelectorAll('.cm-item')); }
  function pending() { return cms().filter(function (c) { return c.dataset.answered === '0' && c.dataset.mine === '0' && c.dataset.hidden !== '1' && c.offsetParent !== null && c.querySelector('[data-reply-to]'); }); }
  function paint() { if (!nb) { nb = document.createElement('button'); nb.type = 'button'; nb.className = 'msg-next hidden'; document.body.appendChild(nb);
      nb.addEventListener('click', function () { var p = pending(); if (!p.length) return paint(); idx = (idx + 1) % p.length; var c = p[idx];
        c.scrollIntoView({ behavior: 'smooth', block: 'center' }); c.classList.add('msg-flash'); setTimeout(function () { c.classList.remove('msg-flash'); }, 1500); var r = c.querySelector('[data-reply-to]'); if (r) setTimeout(function () { r.click(); }, 350); }); }
    var n = pending().length; nb.classList.toggle('hidden', !n); nb.innerHTML = ic('chevron-left', 'w-4 h-4') + '<span>' + esc(U.next) + '</span><b>' + n + '</b>'; }
  document.addEventListener('keydown', function (e) { if (e.key === 'n' && nb && !nb.classList.contains('hidden') && !/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) && !e.ctrlKey && !e.metaKey && !document.querySelector('dialog[open]')) { e.preventDefault(); nb.click(); } });
  var t; new MutationObserver(function () { clearTimeout(t); t = setTimeout(init, 120); }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-answered'] });
  init();
})();
