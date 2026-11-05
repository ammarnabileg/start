/* مبدّل فروع جوجل بيزنس — مشترك بين صفحات الفروع */
(function () {
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  /* ---------- مبدّل الفروع (شغال حتى لو مفيش بيانات للفرع) ---------- */
  var sw = $('#gl-sw');
  if (sw) {
    var swBtn = $('#gl-sw-btn'), pop = $('#gl-sw-pop'), q = $('#gl-sw-q'), items = $$('.gl-sw-it', pop), act = -1;
    var vis = function () { return items.filter(function (a) { return a.style.display !== 'none'; }); };
    var hi = function (i) { var v = vis(); act = Math.max(0, Math.min(i, v.length - 1)); items.forEach(function (a) { a.classList.remove('ring-2', 'ring-brand-300'); }); if (v[act]) { v[act].classList.add('ring-2', 'ring-brand-300'); v[act].scrollIntoView({ block: 'nearest' }); } };
    var open = function (on) { pop.style.display = on ? '' : 'none'; swBtn.setAttribute('aria-expanded', on); if (on) { q.value = ''; filter(); q.focus(); } };
    var filter = function () {
      var s = q.value.trim().toLowerCase();
      items.forEach(function (a) { a.style.display = !s || a.dataset.q.indexOf(s) > -1 ? '' : 'none'; });
      $('#gl-sw-none').style.display = vis().length ? 'none' : ''; act = -1;
    };
    swBtn.onclick = function (e) { e.stopPropagation(); open(pop.style.display === 'none'); };
    q.addEventListener('input', filter);
    q.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); hi(act + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); hi(act - 1); }
      else if (e.key === 'Enter') { var v = vis()[act < 0 ? 0 : act]; if (v) { e.preventDefault(); location.href = v.href; } }
      else if (e.key === 'Escape') open(false);
    });
    document.addEventListener('click', function (e) { if (!sw.contains(e.target)) open(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'b' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { e.preventDefault(); open(true); } });
  }
})();
