/* الإعلانات: بحث فوري، ترتيب الأعمدة، تمييز أعلى/أضعف نسبة ضغط، أرقام بتعد، اختصارات */
(function () {
  var A = window.ADX; if (!A) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var rows = $$('.ads-row'), tb = rows[0] && rows[0].parentNode;

  // بحث
  var q = $('#ads-q');
  if (q) q.addEventListener('input', function () {
    var v = q.value.trim().toLowerCase(), n = 0;
    rows.forEach(function (r) { var hit = !v || r.dataset.q.indexOf(v) > -1; r.style.display = hit ? '' : 'none'; if (hit) n++; });
    $('#ads-empty').classList.toggle('hidden', n > 0);
  });

  // ترتيب
  var cur = null, dir = -1;
  $$('#ads-table [data-sort]').forEach(function (th) {
    th.insertAdjacentHTML('beforeend', ' <span class="ads-arr text-ink-300">↕</span>');
    th.addEventListener('click', function () {
      var i = +th.dataset.sort - 3; dir = cur === i ? -dir : -1; cur = i;
      rows.sort(function (a, b) { return (JSON.parse(a.dataset.v)[i] - JSON.parse(b.dataset.v)[i]) * dir; }).forEach(function (r) { tb.appendChild(r); });
      $$('#ads-table .ads-arr').forEach(function (s) { s.textContent = '↕'; s.className = 'ads-arr text-ink-300'; });
      var s = th.querySelector('.ads-arr'); s.textContent = dir < 0 ? '↓' : '↑'; s.className = 'ads-arr text-brand-600';
    });
  });

  // أعلى وأضعف نسبة ضغط (بين الحملات اللي ليها ظهور)
  var ctrs = $$('.ads-ctr').filter(function (s) { return +s.dataset.ctr > 0; });
  if (ctrs.length > 1) {
    var vals = ctrs.map(function (s) { return +s.dataset.ctr; }), mx = Math.max.apply(null, vals), mn = Math.min.apply(null, vals);
    ctrs.forEach(function (s) { var v = +s.dataset.ctr; if (v === mx) { s.classList.add('bg-lime-100', 'text-lime-700', 'font-semibold'); s.title = A.best; } else if (v === mn) { s.classList.add('bg-red-50', 'text-red-600'); s.title = A.low; } });
  }

  // أرقام بتعد
  $$('.ads-num').forEach(function (el) {
    var raw = el.textContent.trim(), m = raw.match(/^(\D*?)([\d,]+(?:\.\d+)?)(\D*)$/); if (!m) return;
    var to = parseFloat(m[2].replace(/,/g, '')), dec = (m[2].split('.')[1] || '').length, t0 = null; if (!to) return;
    (function f(ts) { t0 = t0 || ts; var p = Math.min(1, (ts - t0) / 800), v = to * (1 - Math.pow(1 - p, 3)); el.textContent = m[1] + v.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + m[3]; if (p < 1) requestAnimationFrame(f); else el.textContent = raw; })(performance.now());
  });

  // ظل لشريط الفلاتر وهو ثابت
  var bar = $('#ads-bar'); if (bar) { var sh = function () { bar.firstElementChild.classList.toggle('shadow-lg', scrollY > 120); }; addEventListener('scroll', sh, { passive: true }); }

  // اختصارات
  function help() {
    var d = document.createElement('dialog'); d.className = 'w-[min(420px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm text-ink-900"></b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div><div class="p-4 grid gap-1.5 text-sm"></div>';
    d.querySelector('b').textContent = A.keysTitle;
    A.keys.forEach(function (k) { var r = document.createElement('div'); r.className = 'flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-ink-50'; r.innerHTML = '<span class="text-ink-700"></span><kbd class="min-w-[1.6rem] text-center rounded-md border border-ink-300 border-b-2 bg-ink-50 px-1.5 py-0.5 text-[11px] font-mono text-ink-700">' + esc(k[0]) + '</kbd>'; r.querySelector('span').textContent = k[1]; d.querySelector('.grid').appendChild(r); });
    d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); }); document.body.appendChild(d); d.showModal();
  }
  var hb = $('#ads-help'); if (hb) hb.onclick = help;
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key === '/' && q) { e.preventDefault(); q.focus(); }
    else if (e.key === 'n') location.href = A.create;
    else if (e.key === 'a') { var d = $('#ads-accs'); if (d) { d.open = !d.open; d.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
    else if (e.key === '?') { e.preventDefault(); help(); }
  });
})();
