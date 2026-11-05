/* بيانات الفرع على جوجل: اكتمال الملف، معاينة، مواعيد ذكية، تنبيهات قبل الحفظ.
   كل ده في المتصفح بس — الحفظ لجوجل بيحصل فقط لما المستخدم يدوس "حفظ". */
(function () {
  var X = window.GLX; if (!X) return;
  var S = X.s;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return str.replace(/%s/g, function () { return a[i++]; }); }
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var form = function (k) { return $('[data-gl="' + k + '"]'); };
  var F = { basic: form('basic'), hours: form('hours'), special: form('special'), attrs: form('attributes'), services: form('services'), actions: form('actions'), menu: form('menu') };
  if (!F.basic) return;
  var grid = F.basic.parentNode;

  /* ---------- لوحة علوية: اكتمال + معاينة ---------- */
  var top = el('div', 'grid lg:grid-cols-2 gap-4 mb-4');
  var scoreCard = el('div', 'card p-5');
  var prevCard = el('div', 'card p-5');
  top.appendChild(scoreCard); top.appendChild(prevCard);
  grid.parentNode.insertBefore(top, grid);

  /* ---------- شريط تنقل ثابت ---------- */
  var nav = el('div', 'sticky top-0 z-20 -mx-1 px-1 py-2 mb-3 bg-white/95 backdrop-blur border-b border-ink-100 flex flex-wrap gap-1 text-xs');
  [['basic', F.basic], ['hours', F.hours], ['special', F.special], ['attributes', F.attrs], ['services', F.services], ['action_add', F.actions], ['menu', F.menu]].forEach(function (p) {
    if (!p[1]) return;
    var h = $('h2', p[1]); var b = el('a', 'gl-nav px-2.5 py-1 rounded-full bg-ink-50 hover:bg-ink-100 text-ink-700 cursor-pointer', esc(h ? h.textContent : p[0]));
    b.href = '#' + p[0]; b.dataset.k = p[0]; nav.appendChild(b);
  });
  var dirtyBadge = el('span', 'ms-auto px-2.5 py-1 rounded-full bg-amber-100 text-amber-800', esc(S.unsaved)); dirtyBadge.style.display = 'none';
  nav.appendChild(dirtyBadge);
  grid.parentNode.insertBefore(nav, grid);

  /* ---------- المواعيد ---------- */
  function mins(v) { if (!v) return null; var p = v.split(':'); return (+p[0]) * 60 + (+p[1] || 0); }
  function dayRows() { return $$('.gl-day', F.hours); }
  function rowVal(r) {
    var i = $$('input', r); // checkbox, from, to
    return { on: i[0].checked, from: i[1].value, to: i[2].value, cb: i[0], fi: i[1], ti: i[2] };
  }
  function span(v) { var a = mins(v.from), b = mins(v.to); if (a == null || b == null) return 0; if (b <= a) b += 1440; return b - a; }
  function paintHours() {
    var total = 0;
    dayRows().forEach(function (r) {
      var v = rowVal(r);
      v.fi.disabled = v.ti.disabled = !v.on;
      r.style.opacity = v.on ? '' : '.55';
      var tag = $('.gl-tag', r); if (!tag) { tag = el('span', 'gl-tag text-[11px] text-ink-500'); r.appendChild(tag); }
      var a = mins(v.from), b = mins(v.to);
      tag.textContent = !v.on ? S.closed : (a === b ? S.h24 : (b < a || v.to === '00:00' ? S.overnight : ''));
      if (v.on) total += (a === b ? 1440 : span(v));
    });
    var w = $('.gl-week', F.hours); if (w) w.textContent = f(S.weekHours, Math.round(total / 6) / 10);
  }
  if (F.hours) {
    var bar = el('div', 'flex flex-wrap items-center gap-1 mb-3 text-xs');
    bar.appendChild(el('span', 'text-ink-500', esc(S.presets)));
    var presets = {
      p_95: function (d) { return ['SATURDAY', 'SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY'].indexOf(d) > -1 ? ['09:00', '17:00'] : null; },
      p_all: function () { return ['12:00', '00:00']; },
      p_247: function () { return ['00:00', '00:00']; },
      p_fri: function (d, v) { return d === 'FRIDAY' ? null : (v.on ? [v.from, v.to] : null); }
    };
    Object.keys(presets).forEach(function (k) {
      var b = el('button', 'px-2 py-1 rounded bg-ink-50 hover:bg-brand-50 hover:text-brand-700', esc(S[k])); b.type = 'button';
      b.onclick = function () {
        dayRows().forEach(function (r) { var v = rowVal(r), p = presets[k](r.dataset.day, v); v.cb.checked = !!p; if (p) { v.fi.value = p[0]; v.ti.value = p[1]; } });
        paintHours(); markDirty(F.hours); refresh();
      };
      bar.appendChild(b);
    });
    bar.appendChild(el('span', 'gl-week ms-auto text-ink-500'));
    var h2 = $('h2', F.hours); h2.parentNode.insertBefore(bar, h2.nextSibling);
    dayRows().forEach(function (r) {
      var c = el('button', 'text-[11px] link', esc(S.copyAll)); c.type = 'button'; c.title = S.copyAll;
      c.onclick = function () { var src = rowVal(r); dayRows().forEach(function (o) { var v = rowVal(o); v.cb.checked = src.on; v.fi.value = src.from; v.ti.value = src.to; }); paintHours(); markDirty(F.hours); refresh(); };
      r.appendChild(c);
    });
    F.hours.addEventListener('input', function () { paintHours(); refresh(); });
    F.hours.addEventListener('change', function () { paintHours(); refresh(); });
    paintHours();
  }
  var JS_DAY = ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
  function hoursMap() { var m = {}; if (F.hours) dayRows().forEach(function (r) { m[r.dataset.day] = rowVal(r); }); return m; }
  function openStatus() {
    if (!F.hours) return null;
    var m = hoursMap(), now = new Date(), d = JS_DAY[now.getDay()], y = JS_DAY[(now.getDay() + 6) % 7], t = now.getHours() * 60 + now.getMinutes();
    var today = m[d], prev = m[y];
    if (prev && prev.on) { var pa = mins(prev.from), pb = mins(prev.to); if (pb <= pa && pa !== pb && t < pb) return { open: true, txt: f(S.closesAt, prev.to) }; }
    if (today && today.on) {
      var a = mins(today.from), b = mins(today.to);
      if (a === b) return { open: true, txt: S.h24 };
      if (t >= a && (b <= a || t < b)) return { open: true, txt: f(S.closesAt, today.to) };
      if (t < a) return { open: false, txt: f(S.opensAt, today.from) };
    }
    for (var i = 1; i <= 7; i++) { var n = m[JS_DAY[(now.getDay() + i) % 7]]; if (n && n.on) return { open: false, txt: f(S.opensAt, n.from) }; }
    return { open: false, txt: '' };
  }

  /* ---------- المواعيد الخاصة ---------- */
  function spRows() { return $$('.gl-sp', F.special); }
  function paintSpecial() {
    var seen = {}, today = new Date().toISOString().slice(0, 10);
    spRows().forEach(function (r) {
      var i = $$('input', r), date = i[0].value, closed = i[1].checked;
      i[2].disabled = i[3].disabled = closed;
      var w = $('.gl-w', r); if (!w) { w = el('span', 'gl-w text-[11px] text-amber-700'); r.appendChild(w); }
      w.textContent = !date ? '' : (seen[date] ? S.dup : (date < today ? S.past : ''));
      if (date) seen[date] = 1;
    });
  }
  if (F.special) {
    var add = el('button', 'text-xs link mt-2', esc(S.addRow)); add.type = 'button';
    add.onclick = function () {
      var rows = spRows(), last = rows[rows.length - 1], n = last.cloneNode(true), idx = rows.length;
      $$('input', n).forEach(function (inp) { inp.name = inp.name.replace(/\[\d+\]/, '[' + idx + ']'); if (inp.type === 'checkbox') inp.checked = false; else inp.value = ''; inp.disabled = false; });
      var w = $('.gl-w', n); if (w) w.textContent = '';
      last.parentNode.appendChild(n); $('input', n).focus();
    };
    $('#gl-sp').parentNode.insertBefore(add, $('#gl-sp').nextSibling);
    F.special.addEventListener('input', function () { paintSpecial(); refresh(); });
    F.special.addEventListener('change', function () { paintSpecial(); refresh(); });
    paintSpecial();
  }

  /* ---------- الوصف ---------- */
  var desc = $('textarea[name=description]', F.basic);
  var dHint = el('div', 'text-[11px] mt-1 flex flex-wrap gap-x-3');
  desc.parentNode.appendChild(dHint);
  function paintDesc() {
    var v = desc.value, n = v.length, msgs = ['<span class="' + (n > 700 ? 'text-amber-700' : 'text-ink-500') + '">' + esc(f(S.chars, n)) + '</span>'];
    if (n && n < 250) msgs.push('<span class="text-amber-700">' + esc(S.descShort) + '</span>');
    if (/https?:\/\/|www\.|\+?\d[\d\s-]{7,}\d/.test(v)) msgs.push('<span class="text-red-600">' + esc(S.descPhone) + '</span>');
    dHint.innerHTML = msgs.join('');
  }
  desc.addEventListener('input', function () { paintDesc(); refresh(); });
  paintDesc();
  $$('input[name=phone],input[name=website]', F.basic).forEach(function (i) { i.addEventListener('input', refresh); });

  /* ---------- الخصائص ---------- */
  var attrBox = $('#gl-attrs');
  function attrCount() { var all = $$('.gl-attr', attrBox), on = all.filter(function (l) { return $('input[type=checkbox]', l).checked; }); return [on.length, all.length]; }
  if (attrBox && $$('.gl-attr', attrBox).length) {
    var ab = el('div', 'flex flex-wrap items-center gap-2 mb-2 text-xs');
    var q = el('input', 'input !mt-0 !py-1 flex-1 min-w-[10rem] text-sm'); q.placeholder = S.attrSearch; q.type = 'search';
    var only = el('label', 'flex items-center gap-1', '<input type="checkbox"> ' + esc(S.onlyOn));
    var cnt = el('span', 'text-ink-500');
    ab.appendChild(q); ab.appendChild(only); ab.appendChild(cnt);
    attrBox.parentNode.insertBefore(ab, attrBox);
    var filt = function () {
      var s = q.value.trim().toLowerCase(), oo = $('input', only).checked;
      $$('.gl-attr', attrBox).forEach(function (l) { var c = $('input[type=checkbox]', l).checked; l.style.display = (!s || l.textContent.toLowerCase().indexOf(s) > -1) && (!oo || c) ? '' : 'none'; });
      var c = attrCount(); cnt.textContent = f(S.attrOn, c[0], c[1]);
    };
    q.addEventListener('input', filt); only.addEventListener('change', filt);
    attrBox.addEventListener('change', function () { filt(); refresh(); });
    filt();
  }

  /* ---------- الخدمات والمنيو: تحقق من السطور ---------- */
  function lineCheck(frm, name, priceIdx) {
    if (!frm) return;
    var ta = $('textarea[name=' + name + ']', frm), out = el('div', 'text-[11px] mt-1');
    ta.parentNode.insertBefore(out, ta.nextSibling);
    var run = function () {
      var ok = 0, bad = [];
      ta.value.split('\n').forEach(function (ln, i) {
        if (!ln.trim()) return;
        var p = ln.split('|').map(function (x) { return x.trim(); }), pr = p[priceIdx];
        var num = (pr || '').replace(/[A-Za-z؀-ۿ$£€\s]/g, '');
        if (pr && !/^\d+([.,]\d+)?$/.test(num)) bad.push(i + 1); else ok++;
      });
      out.innerHTML = '<span class="text-ink-500">' + esc(f(S.lines, ok)) + '</span>' + bad.slice(0, 3).map(function (n) { return ' · <span class="text-red-600">' + esc(f(S.badLine, n)) + '</span>'; }).join('');
    };
    ta.addEventListener('input', function () { run(); refresh(); }); run();
  }
  lineCheck(F.services, 'services', 1);
  lineCheck(F.menu, 'menu', 2);

  /* ---------- رابط التقييم ---------- */
  if (X.review) {
    var row = $$('a.link').filter(function (a) { return a.href === X.review; })[0];
    if (row) {
      var cp = el('button', 'link text-sm', esc(S.copyLink)); cp.type = 'button';
      cp.onclick = function () { (navigator.clipboard ? navigator.clipboard.writeText(X.review) : Promise.reject()).then(function () { window.aiToast && window.aiToast(S.copied, 'ok'); }, function () { prompt('', X.review); }); };
      row.parentNode.insertBefore(cp, row.nextSibling);
    }
  }

  /* ---------- الاكتمال + المعاينة ---------- */
  function checks() {
    var hm = hoursMap(), anyHours = Object.keys(hm).some(function (k) { return hm[k].on; });
    var today = new Date().toISOString().slice(0, 10), future = F.special && spRows().some(function (r) { var d = $('input', r).value; return d && d >= today; });
    var list = [
      ['verif', 'c_verif', !!X.verified, 12],
      ['basic', 'c_phone', !!$('input[name=phone]', F.basic).value.trim(), 12],
      ['basic', 'c_site', !!$('input[name=website]', F.basic).value.trim(), 10],
      ['basic', 'c_desc', desc.value.trim().length >= 250, 14],
      ['hours', 'c_hours', anyHours, 14],
      ['special', 'c_special', !!future, 6],
      ['attributes', 'c_attrs', attrBox ? attrCount()[0] >= 3 : true, 10],
      ['services', 'c_services', F.services ? !!$('textarea', F.services).value.trim() : true, 8],
      ['action_add', 'c_actions', F.actions ? +F.actions.dataset.count > 0 : true, 8]
    ];
    if (F.menu) list.push(['menu', 'c_menu', !!$('textarea[name=menu]', F.menu).value.trim(), 6]);
    return list;
  }
  function refresh() {
    var L = checks(), tot = 0, got = 0;
    L.forEach(function (c) { tot += c[3]; if (c[2]) got += c[3]; });
    var pct = Math.round(got / tot * 100), col = pct >= 85 ? '#16a34a' : pct >= 60 ? '#d97706' : '#dc2626';
    scoreCard.innerHTML = '<div class="flex items-center gap-4 mb-3">'
      + '<div class="relative w-16 h-16 shrink-0 rounded-full" style="background:conic-gradient(' + col + ' ' + pct * 3.6 + 'deg,#eef0f3 0)"><div class="absolute inset-1.5 rounded-full bg-white flex items-center justify-center font-bold text-ink-900">' + pct + '%</div></div>'
      + '<div><div class="font-bold text-ink-900">' + esc(S.score) + '</div><div class="text-xs text-ink-500">' + esc(S.scoreHint) + '</div></div></div>'
      + '<div class="grid sm:grid-cols-2 gap-1 text-sm">' + L.map(function (c) {
        return '<a href="#' + c[0] + '" class="flex items-center gap-2 rounded px-1.5 py-0.5 hover:bg-ink-50 ' + (c[2] ? 'text-ink-500' : 'text-ink-900') + '">'
          + '<span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] text-white" style="background:' + (c[2] ? '#16a34a' : '#d1d5db') + '">' + (c[2] ? '✓' : '') + '</span>' + esc(S[c[1]]) + '</a>';
      }).join('') + '</div>';
    var st = openStatus(), site = $('input[name=website]', F.basic).value.trim(), ph = $('input[name=phone]', F.basic).value.trim();
    prevCard.innerHTML = '<div class="flex items-center justify-between mb-2"><span class="font-bold text-ink-900">' + esc(S.preview) + '</span><span class="text-[11px] text-ink-400">' + esc(S.previewNote) + '</span></div>'
      + '<div class="rounded-xl border border-ink-200 p-4">'
      + '<div class="text-lg text-ink-900">' + esc(X.title) + '</div>'
      + '<div class="text-xs text-ink-500 mb-2">' + esc(X.cat) + '</div>'
      + (st ? '<div class="text-sm mb-2"><b style="color:' + (st.open ? '#16a34a' : '#dc2626') + '">' + esc(st.open ? S.openNow : S.closedNow) + '</b>' + (st.txt ? ' · <span class="text-ink-600">' + esc(st.txt) + '</span>' : '') + '</div>' : '')
      + '<div class="flex gap-2 mb-3 text-xs">'
      + ['dir', ph ? 'call' : '', site ? 'site' : ''].filter(Boolean).map(function (k) { return '<span class="px-3 py-1.5 rounded-full border border-ink-200 text-brand-700">' + esc(S[k]) + '</span>'; }).join('') + '</div>'
      + (ph ? '<div class="text-sm text-ink-700" dir="ltr" style="text-align:start">' + esc(ph) + '</div>' : '')
      + (site ? '<div class="text-sm text-brand-700 truncate" dir="ltr" style="text-align:start">' + esc(site.replace(/^https?:\/\//, '')) + '</div>' : '')
      + (desc.value.trim() ? '<p class="text-sm text-ink-600 mt-2 line-clamp-3">' + esc(desc.value.trim()) + '</p>' : '')
      + '</div>';
  }

  /* ---------- تعديلات مش محفوظة ---------- */
  var dirty = new Set(), submitting = false;
  function markDirty(frm) { dirty.add(frm); frm.classList.add('ring-2', 'ring-amber-300'); dirtyBadge.style.display = ''; }
  $$('form[method=post]', grid).forEach(function (frm) {
    frm.addEventListener('input', function () { markDirty(frm); });
    frm.addEventListener('change', function (e) { if (e.target.type !== 'search') markDirty(frm); });
    frm.addEventListener('submit', function () { submitting = true; $$('input:disabled', frm).forEach(function (i) { i.disabled = false; }); });
  });
  dirtyBadge.style.cursor = 'pointer';
  dirtyBadge.onclick = function () { var first = dirty.values().next().value; if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' }); };
  window.addEventListener('beforeunload', function (e) { if (dirty.size && !submitting) { e.preventDefault(); e.returnValue = S.leave; return S.leave; } });

  /* ---------- تمييز القسم الحالي في الشريط ---------- */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (en) { if (!en.isIntersecting) return; var id = $('h2', en.target).id; $$('.gl-nav', nav).forEach(function (b) { var on = b.dataset.k === id; b.classList.toggle('bg-brand-600', on); b.classList.toggle('text-white', on); b.classList.toggle('bg-ink-50', !on); }); });
    }, { rootMargin: '-30% 0px -60% 0px' });
    Object.keys(F).forEach(function (k) { if (F[k] && $('h2', F[k])) io.observe(F[k]); });
  }

  refresh();
  setInterval(refresh, 60000);
})();
