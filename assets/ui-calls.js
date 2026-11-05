/* سجل المكالمات: تجميع بالأيام، فلترة وبحث فوري، رسم بياني، ونسخ الرقم. مفيش أي اتصال بيتعمل من هنا. */
(function () {
  var X = window.CLX || {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.ph-copy'); if (!b) return;
    var v = b.dataset.v;
    (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { window.aiToast && window.aiToast(X.copied, 'ok'); }, function () { prompt('', v); });
  });

  /* بحث في نص المكالمة */
  var tq = $('#tr-q');
  if (tq) tq.addEventListener('input', function () {
    var s = tq.value.trim().toLowerCase();
    $$('.tr-seg').forEach(function (g) {
      var t = $('.tr-t', g), raw = t.textContent, i = s ? raw.toLowerCase().indexOf(s) : -1;
      g.style.opacity = !s || i > -1 ? '' : '.35';
      t.innerHTML = i > -1 ? esc(raw.slice(0, i)) + '<mark class="bg-amber-200 text-ink-900 rounded px-0.5">' + esc(raw.slice(i, i + s.length)) + '</mark>' + esc(raw.slice(i + s.length)) : esc(raw);
    });
  });

  var list = $('#cl-list'); if (!list) return;
  var rows = $$('.cl-row', list);
  function dayKey(iso) { var d = new Date(iso); return isNaN(d) ? '' : d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate(); }
  function dayLabel(iso) {
    var d = new Date(iso), n = new Date(), y = new Date(); y.setDate(n.getDate() - 1);
    if (dayKey(iso) === dayKey(n.toISOString())) return X.today;
    if (dayKey(iso) === dayKey(y.toISOString())) return X.yday;
    try { return d.toLocaleDateString(X.lang === 'ar' ? 'ar-EG' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long' }); } catch (e) { return d.toDateString(); }
  }
  /* عناوين الأيام */
  var last = '';
  rows.forEach(function (r) {
    var k = dayKey(r.dataset.at);
    if (k && k !== last) { var h = document.createElement('div'); h.className = 'cl-day sticky top-0 z-[1] bg-ink-50/95 backdrop-blur px-4 py-1.5 text-xs font-medium text-ink-600 border-b border-ink-100'; h.dataset.k = k; h.textContent = dayLabel(r.dataset.at); list.insertBefore(h, r); last = k; }
    r.dataset.k = k;
  });

  /* "من قد إيه" */
  $$('[data-ago]').forEach(function (e) {
    var m = Math.round((Date.now() - new Date(e.dataset.ago)) / 60000), A = X.ago || [];
    if (isNaN(m) || !A.length) return;
    e.textContent = m < 1 ? A[0] : m < 60 ? f(A[1], m) : m < 1440 ? f(A[2], Math.round(m / 60)) : f(A[3], Math.round(m / 1440));
  });

  /* فلترة */
  var q = $('#cl-q'), mode = '';
  function apply() {
    var s = q ? q.value.trim().toLowerCase().replace(/[\s+()-]/g, '') : '', shown = {}, n = 0;
    rows.forEach(function (r) {
      var d = r.dataset, ok = (!s || d.q.replace(/\s/g, '').indexOf(s) > -1 || d.q.indexOf(q.value.trim().toLowerCase()) > -1)
        && (mode !== 'missed' || d.miss === '1') && (mode !== 'vm' || d.vm === '1') && (mode !== 'rec' || d.rec === '1') && (mode !== 'long' || +d.dur > 300);
      r.style.display = ok ? '' : 'none'; if (ok) { shown[d.k] = 1; n++; }
    });
    $$('.cl-day', list).forEach(function (h) { h.style.display = shown[h.dataset.k] ? '' : 'none'; });
    $('#cl-none').style.display = n ? 'none' : '';
  }
  if (q) q.addEventListener('input', apply);
  var chips = $('#cl-chips');
  if (chips) chips.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return; mode = b.dataset.f;
    $$('button', chips).forEach(function (x) { var on = x === b; x.className = 'rounded-full px-3 py-1 ' + (on ? 'bg-brand-600 text-white' : 'bg-ink-50 text-ink-700 hover:bg-ink-100'); });
    apply();
  });
  document.addEventListener('keydown', function (e) { if (e.key === '/' && q && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { e.preventDefault(); q.focus(); } });

  /* رسم: آخر 14 يوم (من المكالمات اللي في الصفحة) */
  var ch = $('#cl-chart');
  if (ch) {
    var days = [], map = {}, now = new Date();
    for (var i = 13; i >= 0; i--) { var d = new Date(now); d.setDate(now.getDate() - i); var k = dayKey(d.toISOString()); days.push({ k: k, d: d, i: 0, o: 0, m: 0 }); map[k] = days[days.length - 1]; }
    rows.forEach(function (r) { var x = map[r.dataset.k]; if (!x) return; if (r.dataset.miss === '1') x.m++; else if (r.dataset.in === '1') x.i++; else x.o++; });
    var max = Math.max.apply(null, days.map(function (x) { return x.i + x.o + x.m; }).concat([1]));
    var leg = function (c, l) { return '<span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:' + c + '"></span>' + esc(l) + '</span>'; };
    ch.innerHTML = '<div class="flex flex-wrap items-center gap-3 mb-4"><span class="font-bold text-ink-900 text-sm me-auto">' + esc(X.chart) + '</span><span class="flex gap-3 text-xs text-ink-500">' + leg('#65a30d', X['in']) + leg('#2563eb', X.out) + leg('#dc2626', X.miss) + '</span></div>'
      + '<div class="flex items-end gap-1.5 h-28" dir="ltr">' + days.map(function (x) {
        var t = x.i + x.o + x.m, h = function (v) { return (v / max * 100) + '%'; };
        return '<div class="flex-1 h-full flex flex-col justify-end group relative" title="' + esc(x.d.toLocaleDateString()) + ': ' + t + '">'
          + '<div class="w-full rounded-t-sm" style="height:' + h(x.m) + ';background:#dc2626"></div><div class="w-full" style="height:' + h(x.o) + ';background:#2563eb"></div><div class="w-full rounded-b-sm" style="height:' + h(x.i) + ';background:#65a30d"></div>'
          + (t ? '' : '<div class="w-full h-0.5 bg-ink-100 rounded"></div>') + '</div>';
      }).join('') + '</div>'
      + '<div class="flex gap-1.5 mt-1 text-[10px] text-ink-400" dir="ltr">' + days.map(function (x, i) { return '<span class="flex-1 text-center">' + (i % 2 ? '' : x.d.getDate()) + '</span>'; }).join('') + '</div>';
  }
})();
