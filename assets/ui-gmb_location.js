/* بيانات الفرع على جوجل — طبقة تفاعلية بالكامل في المتصفح.
   مفيش أي طلب لجوجل غير لما المستخدم يدوس "حفظ" بنفسه. */
(function () {
  var X = window.GLX;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function btn(cls, html) { var b = el('button', cls, html); b.type = 'button'; return b; }

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
  if (!X) return;
  var S = X.s;
  var form = function (k) { return $('[data-gl="' + k + '"]'); };
  var F = { basic: form('basic'), hours: form('hours'), special: form('special'), attrs: form('attributes'), services: form('services'), actions: form('actions'), menu: form('menu') };
  if (!F.basic) return;

  /* ---------- رابط التقييم ---------- */
  var cr = $('#gl-copy-review');
  if (cr) cr.onclick = function () {
    (navigator.clipboard ? navigator.clipboard.writeText(X.review) : Promise.reject()).then(function () { window.aiToast && window.aiToast(S.copied, 'ok'); }, function () { prompt('', X.review); });
  };

  /* ================= المواعيد ================= */
  function mins(v) { if (!v) return null; var p = v.split(':'); return (+p[0]) * 60 + (+p[1] || 0); }
  function dayRows() { return F.hours ? $$('.gl-day', F.hours) : []; }
  function rowVal(r) { var i = $$('input', r); return { on: i[0].checked, from: i[1].value, to: i[2].value, cb: i[0], fi: i[1], ti: i[2] }; }
  function paintHours() {
    var total = 0;
    dayRows().forEach(function (r) {
      var v = rowVal(r), a = mins(v.from), b = mins(v.to), full = v.on && a === b && a != null;
      v.fi.disabled = v.ti.disabled = !v.on;
      $('.gl-times', r).style.opacity = v.on ? '' : '.35';
      $('.gl-tag', r).textContent = !v.on ? S.closed : (full ? S.h24 : (b != null && b <= a ? S.overnight : ''));
      var bar = $('.gl-bar', r), segs = [];
      if (v.on && a != null && b != null) {
        if (full) segs.push([0, 1440]); else if (b > a) segs.push([a, b]); else { segs.push([a, 1440]); if (b > 0) segs.push([0, b]); }
        total += full ? 1440 : (b > a ? b - a : 1440 - a + b);
      }
      bar.innerHTML = segs.map(function (s) { return '<span class="absolute inset-y-0 rounded-full bg-brand-500" style="left:' + (s[0] / 14.4) + '%;width:' + ((s[1] - s[0]) / 14.4) + '%"></span>'; }).join('');
    });
    var w = $('.gl-week', F.hours); if (w) w.textContent = f(S.weekHours, Math.round(total / 6) / 10);
  }
  if (F.hours) {
    var pre = $('#gl-presets');
    var presets = {
      p_95: function (d) { return ['SATURDAY', 'SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY'].indexOf(d) > -1 ? ['09:00', '17:00'] : null; },
      p_all: function () { return ['12:00', '00:00']; },
      p_247: function () { return ['00:00', '00:00']; },
      p_fri: function (d, v) { return d === 'FRIDAY' ? null : (v.on ? [v.from, v.to] : null); }
    };
    Object.keys(presets).forEach(function (k) {
      var b = btn('rounded-full border border-ink-200 px-3 py-1 text-xs text-ink-700 hover:border-brand-400 hover:text-brand-700 hover:bg-brand-50 transition', esc(S[k]));
      b.onclick = function () {
        dayRows().forEach(function (r) { var v = rowVal(r), p = presets[k](r.dataset.day, v); v.cb.checked = !!p; if (p) { v.fi.value = p[0]; v.ti.value = p[1]; } });
        changed(F.hours);
      };
      pre.appendChild(b);
    });
    dayRows().forEach(function (r) {
      $('.gl-copy', r).onclick = function () { var s = rowVal(r); dayRows().forEach(function (o) { var v = rowVal(o); v.cb.checked = s.on; v.fi.value = s.from; v.ti.value = s.to; }); changed(F.hours); };
    });
    paintHours();
  }
  var JS_DAY = ['SUNDAY', 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
  function hoursMap() { var m = {}; dayRows().forEach(function (r) { m[r.dataset.day] = rowVal(r); }); return m; }
  function openStatus() {
    if (!F.hours) return null;
    var m = hoursMap(), now = new Date(), di = now.getDay(), t = now.getHours() * 60 + now.getMinutes();
    var prev = m[JS_DAY[(di + 6) % 7]], today = m[JS_DAY[di]];
    if (prev && prev.on) { var pa = mins(prev.from), pb = mins(prev.to); if (pb < pa && t < pb) return { open: true, txt: f(S.closesAt, prev.to) }; }
    if (today && today.on) {
      var a = mins(today.from), b = mins(today.to);
      if (a === b) return { open: true, txt: S.h24 };
      if (t >= a && (b <= a || t < b)) return { open: true, txt: f(S.closesAt, today.to) };
      if (t < a) return { open: false, txt: f(S.opensAt, today.from) };
    }
    for (var i = 1; i <= 7; i++) { var n = m[JS_DAY[(di + i) % 7]]; if (n && n.on) return { open: false, txt: f(S.opensAt, n.from) }; }
    return { open: false, txt: '' };
  }

  /* ================= المواعيد الخاصة ================= */
  var spBox = $('#gl-sp'), spIdx = 0;
  function spRows() { return spBox ? $$('.gl-sp', spBox) : []; }
  function today() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function paintSpecial() {
    var seen = {}, td = today();
    spRows().forEach(function (r) {
      var i = $$('input', r), date = i[0].value, closed = i[1].checked;
      i[2].disabled = i[3].disabled = closed;
      i[2].style.opacity = i[3].style.opacity = closed ? '.35' : '';
      var w = $('.gl-w', r); if (!w) { w = el('span', 'gl-w text-xs'); r.insertBefore(w, $('.gl-sp-del', r)); }
      var msg = !date ? '' : (seen[date] ? S.dup : (date < td ? S.past : ''));
      w.textContent = msg; w.className = 'gl-w text-xs ' + (seen[date] ? 'text-red-600' : 'text-amber-700');
      r.classList.toggle('border-red-300', !!seen[date]);
      if (date) seen[date] = 1;
    });
  }
  function spAdd(date, closed) {
    var rows = spRows(), blank = rows.filter(function (r) { return !$('input', r).value; })[0];
    if (!blank) {
      blank = rows[rows.length - 1].cloneNode(true); spIdx++;
      $$('input', blank).forEach(function (inp) { inp.name = inp.name.replace(/\[\d+\]/, '[' + spIdx + ']'); if (inp.type === 'checkbox') inp.checked = false; else inp.value = ''; inp.disabled = false; });
      spBox.appendChild(blank); bindDel(blank);
    }
    if (date) { $('input', blank).value = date; $$('input', blank)[1].checked = !!closed; }
    changed(F.special); $('input', blank).focus();
  }
  function bindDel(r) {
    $('.gl-sp-del', r).onclick = function () {
      if (spRows().length > 1) r.parentNode.removeChild(r);
      else $$('input', r).forEach(function (i) { if (i.type === 'checkbox') i.checked = false; else i.value = ''; });
      changed(F.special);
    };
  }
  if (spBox) {
    spRows().forEach(function (r) { bindDel(r); var m = /\[(\d+)\]/.exec($('input', r).name); spIdx = Math.max(spIdx, +m[1]); });
    var hol = $('#gl-hol');
    var addB = btn('rounded-full border border-dashed border-brand-300 text-brand-700 px-3 py-1 text-xs hover:bg-brand-50', esc(S.addRow));
    addB.onclick = function () { spAdd(); };
    hol.appendChild(addB);
    (S.hol || []).forEach(function (h) {
      var td = today(), y = +td.slice(0, 4), d = y + '-' + h[0]; if (d < td) d = (y + 1) + '-' + h[0];
      var b = btn('rounded-full bg-ink-50 hover:bg-ink-100 px-3 py-1 text-xs text-ink-700', esc(h[1]));
      b.title = d;
      b.onclick = function () { if (spRows().some(function (r) { return $('input', r).value === d; })) return; spAdd(d, true); };
      hol.appendChild(b);
    });
    paintSpecial();
  }

  /* ================= الوصف ================= */
  var desc = $('textarea[name=description]', F.basic);
  var dHint = el('div', 'text-xs mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1');
  var meter = el('div', 'h-1 rounded-full bg-ink-100 mt-2 overflow-hidden', '<div class="h-full rounded-full transition-all"></div>');
  desc.parentNode.appendChild(meter); desc.parentNode.appendChild(dHint);
  function paintDesc() {
    var v = desc.value, n = v.length, bar = meter.firstChild;
    bar.style.width = Math.min(100, n / 7.5) + '%';
    bar.style.background = n >= 250 ? '#16a34a' : n ? '#d97706' : 'transparent';
    var msgs = ['<span class="text-ink-500">' + esc(f(S.chars, n)) + '</span>'];
    if (n && n < 250) msgs.push('<span class="text-amber-700">' + esc(S.descShort) + '</span>');
    if (/https?:\/\/|www\.|\+?\d[\d\s-]{7,}\d/.test(v)) msgs.push('<span class="text-red-600">' + esc(S.descPhone) + '</span>');
    dHint.innerHTML = msgs.join('');
  }
  paintDesc();

  /* ================= الخصائص ================= */
  var attrBox = $('#gl-attrs');
  function attrCount() { var all = attrBox ? $$('.gl-attr', attrBox) : []; return [all.filter(function (l) { return $('input[type=checkbox]', l).checked; }).length, all.length]; }
  var attrFilt = function () {};
  if (attrBox && $$('.gl-attr', attrBox).length) {
    var ab = el('div', 'flex flex-wrap items-center gap-2 mb-3');
    var aq = el('input', 'input !mt-0 !py-1.5 flex-1 min-w-[10rem] text-sm'); aq.placeholder = S.attrSearch; aq.type = 'search';
    var only = el('label', 'flex items-center gap-1.5 text-xs text-ink-600 cursor-pointer', '<input type="checkbox"> ' + esc(S.onlyOn));
    var cnt = el('span', 'text-xs rounded-full bg-ink-50 px-2.5 py-1 text-ink-600');
    ab.appendChild(aq); ab.appendChild(only); ab.appendChild(cnt);
    attrBox.parentNode.insertBefore(ab, attrBox);
    attrFilt = function () {
      var s = aq.value.trim().toLowerCase(), oo = $('input', only).checked;
      $$('.gl-attr', attrBox).forEach(function (l) { var c = $('input[type=checkbox]', l).checked; l.style.display = (!s || l.textContent.toLowerCase().indexOf(s) > -1) && (!oo || c) ? '' : 'none'; });
      var c = attrCount(); cnt.textContent = f(S.attrOn, c[0], c[1]);
    };
    aq.addEventListener('input', attrFilt); only.addEventListener('change', attrFilt);
    attrFilt();
  }

  /* ================= محرر سطور (خدمات / منيو) بدل التكست إريا ================= */
  function rowEditor(frm) {
    if (!frm) return;
    var ta = $('textarea[data-rows]', frm); if (!ta) return;
    var cols = ta.dataset.rows.split('|'), pi = cols.length - 1;
    ta.style.display = 'none';
    var wrap = el('div', 'space-y-2'), list = el('div', 'space-y-2'), foot = el('div', 'flex items-center gap-3');
    var hdr = el('div', 'hidden sm:grid gap-2 text-xs text-ink-500 px-1', cols.map(function (c) { return '<span>' + esc(c) + '</span>'; }).join('') + '<span></span>');
    var tmpl = 'grid-template-columns:' + cols.map(function (c, i) { return i === pi ? '8rem' : 'minmax(0,1fr)'; }).join(' ') + ' 2rem';
    hdr.style.cssText = tmpl;
    var add = btn('rounded-full border border-dashed border-brand-300 text-brand-700 px-3 py-1 text-xs hover:bg-brand-50', esc(S.addItem));
    var count = el('span', 'text-xs text-ink-500');
    foot.appendChild(add); foot.appendChild(count);
    wrap.appendChild(hdr); wrap.appendChild(list); wrap.appendChild(foot);
    ta.parentNode.insertBefore(wrap, ta);
    var sync = function () {
      var out = [], n = 0;
      $$('.gl-row', list).forEach(function (r) {
        var v = $$('input', r).map(function (i) { return i.value.replace(/\|/g, '/').trim(); });
        var pr = v[pi], num = pr.replace(/[A-Za-z؀-ۿ$£€\s]/g, ''), bad = pr && !/^\d+([.,]\d+)?$/.test(num);
        var pin = $$('input', r)[pi]; pin.classList.toggle('!border-red-400', !!bad); pin.title = bad ? S.badPrice : '';
        if (!v.slice(0, pi).join('').trim()) return;
        n++; while (v.length && v[v.length - 1] === '') v.pop(); out.push(v.join(' | '));
      });
      ta.value = out.join('\n'); count.textContent = f(S.items, n);
    };
    var addRow = function (vals, focus) {
      var r = el('div', 'gl-row grid gap-2 items-center'); r.style.cssText = tmpl;
      cols.forEach(function (c, i) {
        var inp = el('input', 'input !mt-0 !py-1.5' + (i === pi ? ' text-end' : '')); inp.placeholder = c; inp.value = vals[i] || '';
        if (i === pi) { inp.inputMode = 'decimal'; inp.dir = 'ltr'; inp.placeholder = X.cur || c; }
        inp.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') { e.preventDefault(); if (i === pi || e.ctrlKey) addRow(i === 0 || pi === 1 ? [] : [$$('input', r)[0].value], 1); else $$('input', r)[i + 1].focus(); }
          if (e.key === 'Backspace' && !inp.value && i === 0 && $$('.gl-row', list).length > 1 && !$$('input', r).some(function (x) { return x.value; })) { e.preventDefault(); var pr = r.previousElementSibling; r.remove(); sync(); if (pr) $$('input', pr)[0].focus(); }
        });
        r.appendChild(inp);
      });
      var d = btn('w-8 h-8 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600', '✕'); d.title = S.del;
      d.onclick = function () { r.remove(); if (!$$('.gl-row', list).length) addRow([]); sync(); changed(frm); };
      r.appendChild(d); list.appendChild(r);
      if (focus) $$('input', r)[cols.length > 2 && vals[0] ? 1 : 0].focus();
      return r;
    };
    ta.value.split('\n').forEach(function (ln) { if (ln.trim()) addRow(ln.split('|').map(function (x) { return x.trim(); })); });
    if (!$$('.gl-row', list).length) addRow([]);
    add.onclick = function () { addRow([], 1); };
    list.addEventListener('input', sync);
    sync();
  }
  rowEditor(F.services);
  rowEditor(F.menu);

  /* ================= الاكتمال + المعاينة ================= */
  function checks() {
    var hm = hoursMap(), td = today();
    var list = [
      ['verif', 'c_verif', !!X.verified, 12],
      ['basic', 'c_phone', !!$('input[name=phone]', F.basic).value.trim(), 12],
      ['basic', 'c_site', !!$('input[name=website]', F.basic).value.trim(), 10],
      ['basic', 'c_desc', desc.value.trim().length >= 250, 14],
      ['hours', 'c_hours', Object.keys(hm).some(function (k) { return hm[k].on; }), 14],
      ['special', 'c_special', spRows().some(function (r) { var d = $('input', r).value; return d && d >= td; }), 6],
      ['attributes', 'c_attrs', attrBox ? attrCount()[0] >= 3 : true, 10],
      ['services', 'c_services', F.services ? !!$('textarea[name=services]', F.services).value.trim() : true, 8],
      ['action_add', 'c_actions', F.actions ? +F.actions.dataset.count > 0 : true, 8]
    ];
    if (F.menu) list.push(['menu', 'c_menu', !!$('textarea[name=menu]', F.menu).value.trim(), 6]);
    return list;
  }
  var scoreBox = $('#gl-score'), prevBox = $('#gl-preview'), openPill = $('#gl-open');
  function refresh() {
    var L = checks(), tot = 0, got = 0, left = 0;
    L.forEach(function (c) { tot += c[3]; if (c[2]) got += c[3]; else left++; });
    var pct = Math.round(got / tot * 100), col = pct >= 85 ? '#16a34a' : pct >= 60 ? '#d97706' : '#dc2626', C = 2 * Math.PI * 26;
    scoreBox.innerHTML = '<div class="flex items-center gap-4 mb-4">'
      + '<svg class="w-16 h-16 shrink-0 -rotate-90" viewBox="0 0 60 60"><circle cx="30" cy="30" r="26" fill="none" stroke="currentColor" class="text-ink-100" stroke-width="6"/><circle cx="30" cy="30" r="26" fill="none" stroke="' + col + '" stroke-width="6" stroke-linecap="round" stroke-dasharray="' + C + '" stroke-dashoffset="' + (C * (1 - pct / 100)) + '" style="transition:stroke-dashoffset .6s"/></svg>'
      + '<div class="min-w-0"><div class="text-2xl font-bold text-ink-900 leading-none">' + pct + '%</div><div class="text-sm font-medium text-ink-800 mt-1">' + esc(S.score) + '</div><div class="text-xs text-ink-500">' + esc(left ? f(S.left, left) : S.allDone) + '</div></div></div>'
      + '<nav class="space-y-0.5">' + L.map(function (c) {
        return '<a href="#' + c[0] + '" data-k="' + c[0] + '" class="gl-ck flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-ink-50 ' + (c[2] ? 'text-ink-500' : 'text-ink-900 font-medium') + '">'
          + '<span class="w-5 h-5 shrink-0 rounded-full flex items-center justify-center text-[11px] ' + (c[2] ? 'bg-lime-500 text-white' : 'border-2 border-ink-200') + '">' + (c[2] ? '✓' : '') + '</span>'
          + '<span class="flex-1 ' + (c[2] ? 'line-through decoration-ink-300' : '') + '">' + esc(S[c[1]]) + '</span>' + (c[2] ? '' : '<span class="text-ink-300">‹</span>') + '</a>';
      }).join('') + '</nav>'
      + '<p class="text-[11px] text-ink-400 mt-3">' + esc(S.scoreHint) + '</p>';
    var st = openStatus(), site = $('input[name=website]', F.basic).value.trim(), ph = $('input[name=phone]', F.basic).value.trim();
    if (openPill && st) { openPill.classList.remove('hidden'); openPill.className = 'rounded-full text-xs px-2.5 py-1 whitespace-nowrap ' + (st.open ? 'bg-lime-50 text-lime-700' : 'bg-ink-100 text-ink-600'); openPill.textContent = (st.open ? S.openNow : S.closedNow) + (st.txt ? ' · ' + st.txt : ''); }
    prevBox.innerHTML = '<div class="flex items-center justify-between mb-3"><span class="text-sm font-bold text-ink-900">' + esc(S.preview) + '</span></div>'
      + '<div class="rounded-2xl border border-ink-200 overflow-hidden">'
      + '<div class="h-16 bg-gradient-to-br from-ink-100 to-ink-200"></div><div class="p-4">'
      + '<div class="text-lg text-ink-900 leading-tight">' + esc(X.title) + '</div>'
      + '<div class="text-xs text-ink-500 mt-0.5 mb-2">' + esc(X.cat) + '</div>'
      + (st ? '<div class="text-sm mb-3"><b style="color:' + (st.open ? '#16a34a' : '#dc2626') + '">' + esc(st.open ? S.openNow : S.closedNow) + '</b>' + (st.txt ? ' · <span class="text-ink-600">' + esc(st.txt) + '</span>' : '') + '</div>' : '')
      + '<div class="flex gap-3 mb-3">' + [['dir', 1], ['call', ph], ['site', site]].filter(function (k) { return k[1]; }).map(function (k) {
        return '<span class="flex flex-col items-center gap-1 text-[11px] text-brand-700"><span class="w-9 h-9 rounded-full border border-ink-200 flex items-center justify-center">' + { dir: '➤', call: '☎', site: '🌐' }[k[0]] + '</span>' + esc(S[k[0]]) + '</span>';
      }).join('') + '</div>'
      + (ph ? '<div class="text-sm text-ink-700 border-t border-ink-100 pt-2" dir="ltr" style="text-align:start">' + esc(ph) + '</div>' : '')
      + (site ? '<div class="text-sm text-brand-700 truncate" dir="ltr" style="text-align:start">' + esc(site.replace(/^https?:\/\//, '')) + '</div>' : '')
      + (desc.value.trim() ? '<p class="text-xs text-ink-600 mt-2 line-clamp-3">' + esc(desc.value.trim()) + '</p>' : '')
      + '</div></div><p class="text-[11px] text-ink-400 mt-2">' + esc(S.previewNote) + '</p>';
  }

  /* ================= التعديلات اللي مش محفوظة ================= */
  var dirty = [], submitting = false, bar = $('#gl-dirty');
  function title(frm) { var h = $('h2', frm); return h ? h.textContent : ''; }
  function paintDirty() {
    bar.style.display = dirty.length ? '' : 'none';
    $('#gl-dirty-t').textContent = f(S.dirtyN, dirty.map(title).join('، '));
  }
  function changed(frm) {
    if (frm && dirty.indexOf(frm) < 0) { dirty.push(frm); var d = $('.gl-dot', frm); if (d) d.classList.remove('hidden'); frm.classList.add('ring-2', 'ring-amber-200'); paintDirty(); }
    paintHours(); paintSpecial(); paintDesc(); attrFilt(); refresh();
  }
  $$('form[method=post]', $('#gl-main')).forEach(function (frm) {
    if (!frm.dataset.gl) return;
    frm.addEventListener('input', function (e) { if (e.target.type !== 'search') changed(frm); });
    frm.addEventListener('change', function (e) { if (e.target.type !== 'search' && !e.target.closest('label.text-xs')) changed(frm); });
    frm.addEventListener('submit', function () { submitting = true; $$('input:disabled', frm).forEach(function (i) { i.disabled = false; }); });
  });
  $('#gl-dirty-go').onclick = function () { if (dirty[0]) dirty[0].scrollIntoView({ behavior: 'smooth', block: 'start' }); };
  window.addEventListener('beforeunload', function (e) { if (dirty.length && !submitting) { e.preventDefault(); e.returnValue = S.leave; return S.leave; } });

  /* تمييز القسم الحالي في القايمة الجانبية + وميض عند القفز */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('.gl-ck'); if (!a) return;
    var h = document.getElementById(a.dataset.k), c = h && h.closest('.card, #verif'); if (!c) return;
    c.classList.add('ring-2', 'ring-brand-400'); setTimeout(function () { if (dirty.indexOf(c) < 0) c.classList.remove('ring-2'); c.classList.remove('ring-brand-400'); }, 1400);
  });

  refresh();
  setInterval(refresh, 60000);
})();
