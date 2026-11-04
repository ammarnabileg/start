// تفاصيل الحملة: طي المجموعات، نصيب الصرف، أفضل إعلان، بحث، عدادات، اختصارات
(function () {
  var X = window.ACX || {}, sets = [].slice.call(document.querySelectorAll('.acx-set'));
  if (!sets.length || document.body.dataset.acxUx) return; document.body.dataset.acxUx = '1';
  var fmt = function (s, v) { return String(s).replace('%s', v); };
  // نصيب كل مجموعة من الصرف
  var total = sets.reduce(function (a, s) { return a + (parseFloat(s.dataset.spend) || 0); }, 0);
  sets.forEach(function (s) {
    var pct = total > 0 ? Math.round((parseFloat(s.dataset.spend) || 0) / total * 100) : 0, bar = s.querySelector('.acx-share');
    if (bar) { bar.title = fmt(X.share, pct + '%'); bar.setAttribute('aria-label', bar.title); requestAnimationFrame(function () { bar.firstElementChild.style.transition = 'width .6s'; bar.firstElementChild.style.width = pct + '%'; }); }
  });
  // أفضل إعلان حسب نسبة الضغط + شارة CTR لكل إعلان
  var ads = [].slice.call(document.querySelectorAll('.acx-ad')), best = null;
  ads.forEach(function (a) {
    var c = parseFloat(a.dataset.ctr); if (c < 0) return;
    var nm = a.querySelector('.font-medium'); if (nm) { var b = document.createElement('span'); b.className = 'acx-ctr text-[11px] rounded px-1.5 py-0.5 bg-ink-100 text-ink-700 tabular-nums'; b.textContent = fmt(X.ctr, c + '%'); nm.parentNode.appendChild(b); a._ctr = b; }
    if (!best || c > parseFloat(best.dataset.ctr)) best = a;
  });
  if (best && ads.length > 1 && best._ctr) { best._ctr.className = best._ctr.className.replace('bg-ink-100 text-ink-700', 'bg-lime-100 text-lime-800'); best._ctr.textContent = '★ ' + X.best + ' · ' + best.dataset.ctr + '%'; }
  // طي / فتح
  function setOpen(s, o) { var b = s.querySelector('.acx-body'), ch = s.querySelector('.acx-chev'); if (b) b.hidden = !o; if (ch) ch.style.transform = o ? '' : 'rotate(-90deg)'; s.querySelector('.acx-head').setAttribute('aria-expanded', o); }
  sets.forEach(function (s) { setOpen(s, true); s.querySelector('.acx-head').addEventListener('click', function () { setOpen(s, s.querySelector('.acx-body').hidden); }); });
  var tg = document.getElementById('acx-toggle');
  function toggleAll() { var anyOpen = sets.some(function (s) { return !s.querySelector('.acx-body').hidden; }); sets.forEach(function (s) { setOpen(s, !anyOpen); }); tg.textContent = anyOpen ? X.expand : X.collapse; }
  if (tg) tg.addEventListener('click', toggleAll);
  // بحث
  var q = document.getElementById('acx-q'), empty = document.createElement('p'); empty.className = 'text-sm text-ink-500 text-center py-6'; empty.textContent = X.empty; empty.hidden = true;
  sets[sets.length - 1].parentNode.appendChild(empty);
  if (q) q.addEventListener('input', function () {
    var v = q.value.trim().toLowerCase(), shown = 0;
    sets.forEach(function (s) {
      var n = 0; [].forEach.call(s.querySelectorAll('.acx-ad'), function (a) { var ok = !v || a.dataset.q.indexOf(v) >= 0; a.style.display = ok ? '' : 'none'; if (ok) n++; });
      var nameHit = v && s.querySelector('.acx-head').textContent.toLowerCase().indexOf(v) >= 0;
      if (nameHit) [].forEach.call(s.querySelectorAll('.acx-ad'), function (a) { a.style.display = ''; });
      s.hidden = v && !n && !nameHit; if (!s.hidden) { shown++; if (v) setOpen(s, true); }
    });
    empty.hidden = shown > 0;
  });
  // عدادات
  [].forEach.call(document.querySelectorAll('.ads-num'), function (el) {
    var txt = el.textContent, m = txt.match(/[\d.,]+/); if (!m) return; var n = parseFloat(m[0].replace(/,/g, '')); if (!(n > 0)) return;
    var dec = (m[0].split('.')[1] || '').length, t0 = performance.now();
    (function step(t) { var k = Math.min(1, (t - t0) / 700); el.textContent = txt.replace(m[0], (n * k).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec })); if (k < 1) requestAnimationFrame(step); else el.textContent = txt; })(t0);
  });
  // اختصارات
  var help = function () {
    var d = document.createElement('dialog'); d.className = 'rounded-2xl p-5 w-80 bg-white dark:bg-ink-900 text-ink-900';
    d.innerHTML = '<div class="font-bold mb-3">' + X.keys + '</div><div class="space-y-2 text-sm">' + [['/', X.kSearch], ['c', X.kToggle], ['b', X.kBack]].map(function (k) { return '<div class="flex justify-between gap-3"><span>' + k[1] + '</span><kbd class="px-2 rounded bg-ink-100" dir="ltr">' + k[0] + '</kbd></div>'; }).join('') + '</div>';
    document.body.appendChild(d); d.addEventListener('close', function () { d.remove(); }); d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); d.showModal();
  };
  var hb = document.getElementById('acx-help'); if (hb) hb.addEventListener('click', help);
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || document.querySelector('dialog[open]')) return;
    if (e.key === '/') { e.preventDefault(); q && q.focus(); } else if (e.key === 'c') toggleAll(); else if (e.key === 'b') location.href = X.back; else if (e.key === '?') help();
  });
})();
