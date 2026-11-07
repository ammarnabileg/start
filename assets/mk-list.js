/* قوائم صفحات السوق (المناقصات، الشركات المستهدفة، العملاء المستهدفين): نفس السلوك في التلاتة
 * - كروت الأرقام فوق بتفلتر (data-mk-stat="key" + data-mk-test="attr=value|attr=value")
 * - بحث فوري (data-q على الصف)، و"/" بيروح للبحث
 * - ترتيب بالضغط على عنوان العمود (data-mk-sort="attr" ، والصف فيه data-s-attr)
 * - تحديد صفوف + شريط عمليات بالجملة تحت
 * - كيبورد: ↑↓ أو j/k للتنقل، Enter يفتح، x يحدد، Esc يلغي التحديد
 * الاستخدام: MKList(rootEl, { onOpen(row), extra(row) => bool, onSelect(rows) })
 */
window.MKList = function (root, o) {
  o = o || {};
  var body = root.querySelector('[data-mk-body]'), rows = [].slice.call(body ? body.querySelectorAll('[data-mk-row]') : []);
  var q = root.querySelector('[data-mk-q]'), stat = '', statTest = null, sortKey = '', sortDir = 1, cur = -1;
  var bar = root.querySelector('[data-mk-bar]'), nSel = root.querySelector('[data-mk-nsel]'), shown = root.querySelector('[data-mk-shown]'), empty = root.querySelector('[data-mk-empty]');
  function test(r, t) { return t.split('|').some(function (c) { var p = c.split('='); return (r.getAttribute('data-' + p[0]) || '') === (p[1] || ''); }); }
  function visible() { return rows.filter(function (r) { return r.style.display !== 'none'; }); }
  // وضع السيرفر: القايمة بتتحمّل صفحات (بحث/فلاتر/ترتيب على السيرفر) + تمرير لا نهائي
  var R = o.remote, total = +(root.dataset.mkTotal || rows.length), page = 0, busy = false, seq = 0, tmr = null, sent = null;
  function remoteState() { return { q: (q && q.value || '').trim(), stat: stat, sort: sortKey, dir: sortDir }; }
  function paintShown() { if (shown) shown.textContent = rows.length >= total ? total.toLocaleString('en-US') : rows.length.toLocaleString('en-US') + ' / ' + total.toLocaleString('en-US'); if (empty) empty.classList.toggle('hidden', total > 0); }
  function load(append) { var my = ++seq; busy = true; root.classList.add('mk-loading');
    return R(remoteState(), append ? page + 1 : 0).then(function (j) { if (my !== seq) return; busy = false; root.classList.remove('mk-loading'); if (!j || !j.ok) return;
      var t = document.createElement('div'); t.innerHTML = j.html; var nr = [].slice.call(t.querySelectorAll('[data-mk-row]'));
      if (!append) { body.innerHTML = ''; rows = []; page = 0; allMode = false; if (bar) bar.classList.remove('mk-bar-on'); } else page++;
      if (append && allMode) setTimeout(function () { nr.forEach(function (r) { var c = r.querySelector('[data-mk-check]'); if (c) c.checked = true; }); syncSel(); });
      nr.forEach(function (r) { body.appendChild(r); }); rows = rows.concat(nr); total = j.total; paintShown(); if (o.onRows) o.onRows(nr, append, j); })
      .catch(function () { if (my === seq) { busy = false; root.classList.remove('mk-loading'); } }); }
  if (R && body) { sent = document.createElement('div'); sent.className = 'mk-more'; body.after(sent);
    new IntersectionObserver(function (es) { if (es[0].isIntersecting && !busy && rows.length < total) load(true); }, { rootMargin: '600px' }).observe(sent); }
  function apply() {
    if (R) { clearTimeout(tmr); tmr = setTimeout(function () { load(false); }, 220); cur = -1; return; }
    var v = (q && q.value || '').trim().toLowerCase(), n = 0;
    rows.forEach(function (r) { var ok = (!statTest || test(r, statTest)) && (!v || (r.dataset.q || '').indexOf(v) >= 0) && (!o.extra || o.extra(r)); r.style.display = ok ? '' : 'none'; if (ok) n++; });
    if (shown) shown.textContent = n === rows.length ? rows.length : n + ' / ' + rows.length;
    if (empty) empty.classList.toggle('hidden', n > 0);
    cur = -1; focus(-1);
  }
  // كروت الأرقام
  root.querySelectorAll('[data-mk-stat]').forEach(function (b) { b.addEventListener('click', function () {
    var on = stat !== b.dataset.mkStat || !b.dataset.mkTest; stat = on ? b.dataset.mkStat : ''; statTest = on ? (b.dataset.mkTest || null) : null;
    root.querySelectorAll('[data-mk-stat]').forEach(function (x) { x.classList.toggle('mk-on', x.dataset.mkStat === (stat || '_all')); });
    if (!stat) { var all = root.querySelector('[data-mk-stat="_all"]'); if (all) all.classList.add('mk-on'); }
    apply(); }); });
  if (q) q.addEventListener('input', apply);
  // ترتيب
  root.querySelectorAll('[data-mk-sort]').forEach(function (h) { h.addEventListener('click', function () {
    var k = h.dataset.mkSort; sortDir = sortKey === k ? -sortDir : 1; sortKey = k;
    root.querySelectorAll('[data-mk-sort]').forEach(function (x) { x.dataset.dir = x === h ? (sortDir > 0 ? 'asc' : 'desc') : ''; });
    if (R) { apply(); return; }
    rows.sort(function (a, b) { var x = a.getAttribute('data-s-' + k) || '', y = b.getAttribute('data-s-' + k) || ''; var nx = parseFloat(x), ny = parseFloat(y);
      var c = (!isNaN(nx) && !isNaN(ny) && /^-?[\d.]+$/.test(x) && /^-?[\d.]+$/.test(y)) ? nx - ny : x.localeCompare(y, 'ar'); return c * sortDir; });
    rows.forEach(function (r) { body.appendChild(r); }); }); });
  // تحديد
  function sel() { return rows.filter(function (r) { var c = r.querySelector('[data-mk-check]'); return c && c.checked; }); }
  // "اختار الكل" في وضع السيرفر: يشمل كل اللي ماشيين على الفلتر حتى اللي ماتحمّلوش
  var allMode = false, selAll = root.querySelector('[data-mk-selall]');
  function paintAll() { if (!selAll) return; var vis = visible(), allLoaded = vis.length > 0 && vis.every(function (r) { var c = r.querySelector('[data-mk-check]'); return c && c.checked; });
    var show = !!R && allLoaded && total > rows.length; selAll.classList.toggle('hidden', !show && !allMode); var f = function (t) { return t.replace('%s', total.toLocaleString('en-US')); };
    selAll.textContent = allMode ? f(selAll.dataset.tOn) : f(selAll.dataset.tAll); selAll.classList.toggle('on', allMode); if (allMode && nSel) nSel.textContent = total.toLocaleString('en-US'); }
  if (selAll) selAll.addEventListener('click', function () { if (allMode) { allMode = false; rows.forEach(function (r) { var c = r.querySelector('[data-mk-check]'); if (c) c.checked = false; }); syncSel(); return; } allMode = true; paintAll(); });
  function syncSel() { var s = sel(); if (allMode && s.length < rows.length) allMode = false; rows.forEach(function (r) { var c = r.querySelector('[data-mk-check]'); r.classList.toggle('mk-sel', !!(c && c.checked)); });
    if (bar) { bar.classList.toggle('mk-bar-on', s.length > 0); if (nSel) nSel.textContent = s.length; } paintAll();
    var all = root.querySelector('[data-mk-all]'); if (all) { var vis = visible(); all.checked = vis.length > 0 && vis.every(function (r) { var c = r.querySelector('[data-mk-check]'); return c && c.checked; }); }
    if (o.onSelect) o.onSelect(s); }
  root.addEventListener('change', function (e) { if (e.target.matches('[data-mk-check]')) syncSel();
    if (e.target.matches('[data-mk-all]')) { visible().forEach(function (r) { var c = r.querySelector('[data-mk-check]'); if (c) c.checked = e.target.checked; }); syncSel(); } });
  var clr = root.querySelector('[data-mk-clear]'); if (clr) clr.addEventListener('click', function () { rows.forEach(function (r) { var c = r.querySelector('[data-mk-check]'); if (c) c.checked = false; }); syncSel(); });
  // فتح الصف
  if (body) body.addEventListener('click', function (e) { if (e.target.closest('a, button, input, label, select, [data-mk-stop]')) return; var r = e.target.closest('[data-mk-row]'); if (r && o.onOpen) o.onOpen(r); });
  // كيبورد
  function focus(i) { rows.forEach(function (r) { r.classList.remove('mk-focus'); }); var v = visible(); if (i >= 0 && v[i]) { v[i].classList.add('mk-focus'); v[i].scrollIntoView({ block: 'nearest' }); } }
  document.addEventListener('keydown', function (e) {
    if (document.querySelector('dialog[open]') || e.metaKey || e.ctrlKey || e.altKey) return;
    var typing = /INPUT|TEXTAREA|SELECT/.test((document.activeElement || {}).tagName || '');
    if (e.key === '/' && !typing && q) { e.preventDefault(); q.focus(); return; }
    if (typing) { if (e.key === 'Escape') document.activeElement.blur(); return; }
    var v = visible();
    if (e.key === 'ArrowDown' || e.key === 'j') { e.preventDefault(); cur = Math.min(v.length - 1, cur + 1); focus(cur); }
    else if (e.key === 'ArrowUp' || e.key === 'k') { e.preventDefault(); cur = Math.max(0, cur - 1); focus(cur); }
    else if (e.key === 'Enter' && v[cur] && o.onOpen) { e.preventDefault(); o.onOpen(v[cur]); }
    else if (e.key === 'x' && v[cur]) { var c = v[cur].querySelector('[data-mk-check]'); if (c) { c.checked = !c.checked; syncSel(); } }
    else if (e.key === 'Escape') { rows.forEach(function (r) { var c = r.querySelector('[data-mk-check]'); if (c) c.checked = false; }); syncSel(); }
  });
  if (R) paintShown(); else apply();
  return { allMode: function () { return allMode; }, state: remoteState, total: function () { return total; }, reload: function () { load(false); }, more: function () { return rows.length < total && !busy ? load(true) : Promise.resolve(); }, apply: apply, selected: sel, rows: function () { return rows; }, remove: function (r) { rows = rows.filter(function (x) { return x !== r; }); r.remove(); apply(); syncSel(); }, clear: function () { if (clr) clr.click(); } };
};
