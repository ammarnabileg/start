/* طبقة التفاعل العامة لكل صفحات المنصة (من غير ذكاء اصطناعي ومن غير مكتبات):
   شريط تحميل، أرقام بتعد، كروت بتظهر ورا بعض، تلميحات، جداول بتترتب وفيها بحث، وصفوف بتتفتح بضغطة */
(function () {
  var main = document.getElementById('main'); if (!main) return;
  var rtl = document.documentElement.dir === 'rtl';
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  function txt(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim(); }
  // أرقام عربي ← إنجليزي عشان الترتيب والعدّ
  function num(s) { s = String(s).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }).replace(/[٬,\s]/g, '').replace('٫', '.'); var m = /^[-+]?\d+(\.\d+)?/.exec(s.replace(/^[^\d+-]+/, '')); return m ? parseFloat(m[0]) : NaN; }

  /* 1) شريط التحميل وانت بتتنقل */
  var bar = document.createElement('div'); bar.id = 'nav-bar'; document.body.appendChild(bar);
  function go() { bar.classList.remove('done'); void bar.offsetWidth; bar.classList.add('go'); }
  function done() { bar.classList.remove('go'); bar.classList.add('done'); }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]'); if (!a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var h = a.getAttribute('href'); if (!h || h[0] === '#' || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-modal') || !/^https?:$/.test(a.protocol) || a.host !== location.host) return;
    if (a.pathname === location.pathname && a.search === location.search && a.hash) return; // نفس الصفحة
    setTimeout(function () { if (!e.defaultPrevented) go(); }, 0);
  });
  document.addEventListener('submit', function (e) { setTimeout(function () { if (!e.defaultPrevented && !e.target.hasAttribute('data-optimistic') && (e.target.getAttribute('method') || 'get').toLowerCase() !== 'dialog') go(); }, 0); });
  window.addEventListener('pageshow', done); window.addEventListener('pagehide', done);

  /* 2) الكروت بتظهر ورا بعض (أول مرة بس) */
  if (!reduce) {
    var cards = main.querySelectorAll(':scope > div > .card, :scope > div > div > .card, :scope > div > .grid > .card, :scope > div > .grid > a, :scope > div > div > .grid > .card');
    Array.prototype.slice.call(cards, 0, 14).forEach(function (c, i) { c.style.setProperty('--i', i); c.classList.add('fx-in'); c.addEventListener('animationend', function () { c.classList.remove('fx-in'); }, { once: true }); });
  }

  /* 3) الأرقام الكبيرة بتعد لحد قيمتها لما تظهر */
  if (!reduce && 'IntersectionObserver' in window) {
    var big = Array.prototype.filter.call(main.querySelectorAll('.text-2xl, .text-3xl, .text-xl'), function (el) {
      if (el.children.length || el.closest('table, form, [contenteditable], h1')) return false;
      var t = txt(el); return /^[+-]?[\d٠-٩][\d٠-٩٬,.]*\s*(%|٪|K|M)?$/.test(t) && num(t) > 1;
    });
    var io = new IntersectionObserver(function (es) { es.forEach(function (en) {
      if (!en.isIntersecting) return; io.unobserve(en.target); var el = en.target, final = txt(el), v = num(final), suf = final.replace(/^[^]*?[\d٠-٩.,٬]+/, ''), dec = (final.split(/[.٫]/)[1] || '').replace(/\D/g, '').length, t0 = performance.now(), dur = 700;
      var fmt = function (x) { var s = x.toFixed(dec); if (/[,٬]/.test(final)) s = Number(s).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }); return (final[0] === '+' ? '+' : '') + s + suf; };
      (function step(now) { var p = Math.min(1, Math.max(0, (now - t0) / dur)), e2 = 1 - Math.pow(1 - p, 3); el.textContent = p < 1 ? fmt(v * e2) : final; if (p < 1) requestAnimationFrame(step); })(t0);
    }); }, { threshold: .4 });
    big.forEach(function (el) { io.observe(el); });
  }

  /* 4) تلميحات بشكل المنصة */
  var tip = document.createElement('div'); tip.id = 'fx-tip'; tip.setAttribute('role', 'tooltip'); document.body.appendChild(tip); var tipFor = null, tipT = null;
  function hideTip() { clearTimeout(tipT); tip.classList.remove('on'); tipFor = null; }
  document.addEventListener('mouseover', function (e) {
    var el = e.target.closest && e.target.closest('[title], [data-tip]'); if (!el || el === tipFor || el.closest('#fx-tip, svg title, [popover]:not(:popover-open)')) return;
    if (el.hasAttribute('title')) { var t0 = el.getAttribute('title'); if (!t0) return; el.setAttribute('data-tip', t0); el.removeAttribute('title'); if (!el.hasAttribute('aria-label') && !txt(el)) el.setAttribute('aria-label', t0); }
    var t = el.getAttribute('data-tip'); if (!t) return; tipFor = el; clearTimeout(tipT);
    tipT = setTimeout(function () { if (tipFor !== el) return; tip.textContent = t; var r = el.getBoundingClientRect(); tip.classList.add('on'); var w = tip.offsetWidth, h = tip.offsetHeight;
      var x = Math.max(8, Math.min(innerWidth - w - 8, r.left + r.width / 2 - w / 2)), y = r.top - h - 8; if (y < 8) y = r.bottom + 8; tip.style.left = x + 'px'; tip.style.top = y + 'px'; }, 260);
  });
  document.addEventListener('mouseout', function (e) { if (tipFor && (!e.relatedTarget || !tipFor.contains(e.relatedTarget))) hideTip(); });
  ['scroll', 'mousedown', 'keydown'].forEach(function (ev) { window.addEventListener(ev, hideTip, true); });

  /* 5) الجداول: ترتيب بالضغط على العنوان، وبحث لو الصفوف كتير، والصف كله بيفتح لو فيه لينك واحد */
  function enhance(tb) {
    if (tb.dataset.fx || tb.hasAttribute('data-no-sort') || !tb.tHead || !tb.tBodies[0]) return; tb.dataset.fx = '1';
    var body = tb.tBodies[0], rows = function () { return Array.prototype.slice.call(body.rows); };
    if (rows().length < 3 || rows().some(function (r) { return r.cells.length === 1 && r.cells[0].colSpan > 1; })) return;
    tb.classList.add('fx-sort'); var ths = tb.tHead.rows[tb.tHead.rows.length - 1].cells;
    Array.prototype.forEach.call(ths, function (th, ci) {
      if (!txt(th) || th.querySelector('input,button,select')) return; th.setAttribute('data-sortable', ''); th.setAttribute('role', 'button'); th.tabIndex = 0;
      var sort = function () {
        var dir = th.dataset.dir === 'asc' ? 'desc' : 'asc'; Array.prototype.forEach.call(ths, function (o) { delete o.dataset.dir; }); th.dataset.dir = dir;
        var rs = rows(), isNum = rs.every(function (r) { var c = r.cells[ci]; var t = c ? txt(c) : ''; return t === '' || t === '—' || !isNaN(num(t)); });
        rs.sort(function (a, b) { var x = a.cells[ci] ? txt(a.cells[ci]) : '', y = b.cells[ci] ? txt(b.cells[ci]) : ''; var r = isNum ? ((isNaN(num(x)) ? -Infinity : num(x)) - (isNaN(num(y)) ? -Infinity : num(y))) : x.localeCompare(y, rtl ? 'ar' : 'en', { numeric: true }); return dir === 'asc' ? r : -r; });
        rs.forEach(function (r) { body.appendChild(r); });
      };
      th.addEventListener('click', sort); th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sort(); } });
    });
    if (rows().length >= 10 && !tb.hasAttribute('data-no-search')) {
      var wrap = document.createElement('div'); wrap.className = 'relative mb-2 max-w-xs';
      wrap.innerHTML = '<span class="absolute inset-y-0 start-3 grid place-items-center text-ink-400 pointer-events-none">' + (window.uic ? uic('search', 'w-4 h-4') : '⌕') + '</span><input type="search" class="input !mt-0 !h-9 !py-0 ps-9 text-sm" placeholder="' + (rtl ? 'ابحث في الجدول…' : 'Search this table…') + '"><span class="absolute inset-y-0 end-3 grid place-items-center text-[11px] text-ink-400 pointer-events-none" data-n></span>';
      var host = tb.closest('.overflow-x-auto') || tb; host.parentNode.insertBefore(wrap, host);
      var inp = wrap.querySelector('input'), cnt = wrap.querySelector('[data-n]');
      inp.addEventListener('input', function () { var q = inp.value.trim().toLowerCase(), n = 0; rows().forEach(function (r) { var ok = !q || txt(r).toLowerCase().indexOf(q) > -1; r.style.display = ok ? '' : 'none'; if (ok) n++; r.classList.toggle('fx-hit', !!q && ok); }); cnt.textContent = q ? n + '/' + rows().length : ''; });
    }
    rows().forEach(function (r) { var ls = r.querySelectorAll('a[href]:not([href^="#"]):not([target=_blank])'); if (ls.length !== 1 || r.querySelector('button, input, select, form')) return; r.style.cursor = 'pointer';
      r.addEventListener('click', function (e) { if (e.target.closest('a, button, input, select, label, textarea') || getSelection().toString()) return; ls[0].click(); }); });
  }
  function scan() { Array.prototype.forEach.call(main.querySelectorAll('table'), enhance); }
  scan();
  document.addEventListener('live:swapped', scan);
  if ('MutationObserver' in window) { var mt = null; new MutationObserver(function () { clearTimeout(mt); mt = setTimeout(scan, 300); }).observe(main, { childList: true, subtree: true }); }
})();
