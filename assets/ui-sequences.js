/* السلاسل التلقائية: بنّاء خطوات بمعاينة زمنية زي شات واتساب، وفلترة المسجلين واختيار جهات الاتصال. */
(function () {
  var X = window.SQX || {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* ---------- تفاصيل: المسجلين ---------- */
  var enQ = $('#en-q'), mode = '';
  function enFilt() { var s = enQ ? enQ.value.trim().toLowerCase() : ''; $$('.en').forEach(function (r) { r.style.display = (!mode || r.dataset.s === mode) && (!s || r.dataset.q.indexOf(s) > -1) ? '' : 'none'; }); }
  if (enQ) enQ.addEventListener('input', enFilt);
  var chips = $('#en-chips');
  if (chips) chips.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; mode = b.dataset.s; $$('button', chips).forEach(function (x) { x.className = 'rounded-full px-2.5 py-1 ' + (x === b ? 'bg-brand-600 text-white' : 'bg-ink-50 text-ink-700 hover:bg-ink-100'); }); enFilt(); });
  var enr = $('#enroll');
  if (enr) {
    var q = $('#enr-q'), go = $('#enr-go'), n = $('#enr-n');
    var paint = function () { var k = $$('input[name="contacts[]"]:checked', enr).length; n.textContent = k ? f(X.n, k) : ''; go.disabled = !k; go.textContent = k ? f(X.go, k) : go.dataset.l || (go.dataset.l = go.textContent); if (!k) go.textContent = go.dataset.l; };
    go.dataset.l = go.textContent;
    q.addEventListener('input', function () { var s = q.value.trim().toLowerCase(); $$('.enr', enr).forEach(function (l) { l.style.display = !s || l.dataset.q.indexOf(s) > -1 ? '' : 'none'; }); });
    $('#enr-all').onclick = function () { var vis = $$('.enr', enr).filter(function (l) { return l.style.display !== 'none'; }), all = vis.every(function (l) { return $('input', l).checked; }); vis.forEach(function (l) { $('input', l).checked = !all; }); paint(); };
    enr.addEventListener('change', paint); paint();
  }

  /* ---------- سلسلة جديدة ---------- */
  var form = $('#sq-new'); if (!form || !X.new) return;
  var S = X.s, steps = $$('.sq-step', form), tl = $('#sq-tl'), tot = $('#sq-total');
  var MUL = { m: 1, h: 60, d: 1440 };
  function vis() { return steps.filter(function (s) { return s.style.display !== 'none'; }); }
  function human(min) {
    if (min <= 0) return S.now;
    var d = Math.floor(min / 1440), h = Math.floor(min % 1440 / 60), m = min % 60, parts = [];
    if (d) parts.push(d + ' ' + X.u.d);
    if (h) parts.push(h + ' ' + X.u.h);
    if (m) parts.push(m + ' ' + X.u.m);
    return parts.join(X.and);
  }
  function paint() {
    var cum = 0, out = [], used = 0;
    vis().forEach(function (st, i) {
      var sel = $('select.wa-tpl', st), d = Math.max(0, +$('.sq-d', st).value || 0) * MUL[$('.sq-u', st).value];
      cum += d;
      var opt = sel.options[sel.selectedIndex], body = opt && opt.value ? (opt.dataset.body || opt.textContent) : '';
      if (opt && opt.value) used++;
      out.push('<div class="text-center"><span class="inline-block rounded-full bg-white/80 text-[10px] text-ink-600 px-2 py-0.5">' + esc(i === 0 && cum === 0 ? S.now : f(S.after, human(cum))) + '</span></div>'
        + '<div class="flex justify-end"><div class="max-w-[85%] rounded-xl rounded-se-sm px-3 py-2 text-[13px] shadow-sm ' + (body ? 'bg-[#d9fdd3] text-ink-900' : 'bg-white/60 text-ink-400 italic') + ' whitespace-pre-line" dir="auto">'
        + esc(body || S.pick) + '<div class="text-[9px] text-ink-400 text-end mt-0.5">' + (i + 1) + ' ✓✓</div></div></div>');
    });
    tl.innerHTML = used ? out.join('') : '<p class="text-xs text-ink-600 text-center py-6">' + esc(S.empty) + '</p>';
    tot.textContent = used ? f(S.total, used, human(cum)) : '';
    $('#sq-add').style.display = vis().length >= X.max ? 'none' : '';
  }
  $('#sq-add').onclick = function () { var next = steps.filter(function (s) { return s.style.display === 'none'; })[0]; if (next) { next.style.display = ''; $('select.wa-tpl', next).focus(); paint(); } };
  steps.forEach(function (st) {
    var rm = $('.sq-rm', st);
    if (rm) rm.onclick = function () {
      // نشيل آخر خطوة ظاهرة ونزحلق القيم لفوق عشان مايبقاش فيه فراغ في النص
      var v = vis(), idx = v.indexOf(st);
      for (var i = idx; i < v.length - 1; i++) {
        var a = v[i], b = v[i + 1];
        $('select.wa-tpl', a).value = $('select.wa-tpl', b).value; $('select.wa-tpl', a).dispatchEvent(new Event('change', { bubbles: true }));
        $('.sq-d', a).value = $('.sq-d', b).value; $('.sq-u', a).value = $('.sq-u', b).value;
      }
      var last = v[v.length - 1];
      $('select.wa-tpl', last).value = ''; $('select.wa-tpl', last).dispatchEvent(new Event('change', { bubbles: true }));
      last.style.display = 'none'; paint();
    };
  });
  $$('[data-preset]', form).forEach(function (b) {
    b.onclick = function () {
      var p = b.dataset.preset.split(',').map(Number), unitOf = function (m) { return m % 1440 === 0 && m ? ['d', m / 1440] : (m % 60 === 0 && m ? ['h', m / 60] : ['m', m]); };
      // المواعيد في الزرار من وقت التسجيل → نحولها لفرق بين كل خطوة واللي قبلها
      var mins = p.map(function (m, i) { return i ? m - p[i - 1] : m; });
      steps.forEach(function (st, i) {
        if (i < mins.length) { st.style.display = ''; var u = unitOf(mins[i]); $('.sq-u', st).value = mins[i] ? u[0] : 'd'; $('.sq-d', st).value = mins[i] ? u[1] : 0; }
      });
      paint();
    };
  });
  form.addEventListener('input', paint); form.addEventListener('change', paint);
  paint();
})();
