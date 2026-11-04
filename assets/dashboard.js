/* الرئيسية: أرقام بتعد، أوقات نسبية، منشور سريع، وتخصيص الأقسام (إخفاء + ترتيب بالسحب) */
(function () {
  var D = window.DSX; if (!D) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch (e) {} } };

  /* ---------- أرقام بتعد ---------- */
  $$('[data-count]').forEach(function (el) {
    var to = +el.dataset.count, t0 = null, fmt = function (n) { return n.toLocaleString(document.documentElement.lang || undefined); };
    if (!to) return; el.textContent = '0';
    (function f(ts) { t0 = t0 || ts; var p = Math.min(1, (ts - t0) / 800); el.textContent = fmt(Math.round(to * (1 - Math.pow(1 - p, 3)))); if (p < 1) requestAnimationFrame(f); })(performance.now());
  });

  /* ---------- أوقات نسبية ---------- */
  var t0 = Date.now();
  function ago(sec) { sec = Math.max(0, sec); var m = Math.round(sec / 60); return m < 1 ? D.now : D.past.replace('%s', m < 60 ? D.min.replace('%s', m) : m < 1440 ? D.hr.replace('%s', Math.round(m / 60)) : D.day.replace('%s', Math.round(m / 1440))); }
  function paint() {
    var extra = (Date.now() - t0) / 1000;
    $$('.dash-rel').forEach(function (el) {
      if (!el.title) el.title = el.textContent;
      if (el.dataset.ago != null && el.dataset.ago !== '') el.textContent = ago(+el.dataset.ago + extra);
      else if (el.dataset.at) { var d = new Date(el.dataset.at); if (!isNaN(d)) el.textContent = ago((Date.now() - d) / 1000); }
    });
  }
  paint(); setInterval(paint, 60000);

  /* ---------- منشور سريع ---------- */
  var q = $('#dash-quick');
  if (q) {
    var ta = q.querySelector('textarea'), n = q.querySelector('.dq-n'), DK = 'dash_quick_' + D.ws;
    ta.value = store.get(DK) || '';
    var upd = function () { n.textContent = ta.value.length ? ta.value.length + ' ' + D.chars : ''; store.set(DK, ta.value.trim() ? ta.value : ''); };
    ta.addEventListener('input', upd); upd();
    ta.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); q.requestSubmit ? q.requestSubmit() : q.submit(); } });
    q.addEventListener('submit', function (e) { if (!ta.value.trim()) { e.preventDefault(); ta.focus(); return; } store.set(DK, ''); });
  }

  /* ---------- تخصيص: إخفاء وترتيب ---------- */
  var grid = $('#dash-grid'), btn = $('#dash-edit'), KK = 'dash_layout_' + D.ws;
  var top = function () { return $$('main [data-w]').filter(function (w) { return !grid.contains(w); }); };
  var lay = null; try { lay = JSON.parse(store.get(KK) || 'null'); } catch (e) {}
  lay = lay || { order: [], hidden: [] };
  function applyLayout() {
    if (grid && lay.order.length) lay.order.forEach(function (id) { var w = grid.querySelector(':scope > [data-w="' + id + '"]'); if (w) grid.appendChild(w); });
    $$('main [data-w]').forEach(function (w) { w.classList.toggle('dash-off', lay.hidden.indexOf(w.dataset.w) > -1); });
  }
  var css = document.createElement('style');
  css.textContent = '.dash-off{display:none!important}body.dash-editing .dash-off{display:block!important;opacity:.35;filter:grayscale(1)}body.dash-editing .dash-off.grid{display:grid!important}'
    + 'body.dash-editing main [data-w]{position:relative;outline:2px dashed rgb(var(--brand-400));outline-offset:4px;border-radius:1rem}'
    + 'body.dash-editing #dash-grid>[data-w]{cursor:grab}body.dash-editing #dash-grid>[data-w].dragging{opacity:.4}'
    + '.dash-tg{position:absolute;top:-.9rem;inset-inline-end:-.6rem;z-index:20}';
  document.head.appendChild(css);
  applyLayout();
  function save() { if (grid) lay.order = $$(':scope > [data-w]', grid).map(function (w) { return w.dataset.w; }); store.set(KK, JSON.stringify(lay)); }
  function editOn(on) {
    document.body.classList.toggle('dash-editing', on);
    btn.querySelector('span') && (btn.querySelector('span').textContent = on ? D.done : D.edit);
    btn.classList.toggle('!bg-brand-600', on); btn.classList.toggle('!text-[#fff]', on);
    var bar = $('#dash-editbar');
    if (on && !bar) {
      bar = document.createElement('div'); bar.id = 'dash-editbar'; bar.className = 'mb-4 flex flex-wrap items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-4 py-2.5 text-sm text-brand-800';
      bar.innerHTML = '<span class="flex-1"></span><button type="button" class="link text-xs"></button>';
      bar.querySelector('span').textContent = D.hint; bar.querySelector('button').textContent = D.reset;
      bar.querySelector('button').onclick = function () { store.set(KK, ''); location.reload(); };
      var anchor = top()[0] || grid; anchor.parentNode.insertBefore(bar, anchor);
    } else if (!on && bar) bar.remove();
    $$('main [data-w]').forEach(function (w) {
      var tg = w.querySelector(':scope > .dash-tg');
      if (on && !tg) {
        tg = document.createElement('button'); tg.type = 'button'; tg.className = 'dash-tg w-8 h-8 rounded-lg bg-white border border-ink-200 shadow flex items-center justify-center text-ink-600 hover:text-brand-700';
        var paintTg = function () { var hid = lay.hidden.indexOf(w.dataset.w) > -1; tg.title = (D.names[w.dataset.w] || '') + (hid ? ' · ' + D.hidden : ''); tg.innerHTML = hid ? '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.1A9.8 9.8 0 0112 5c5 0 9 5 9 7a9.6 9.6 0 01-2.2 3.2M6.6 6.6C4.4 8 3 10.4 3 12c0 2 4 7 9 7a9.4 9.4 0 004.3-1"/></svg>' : '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M3 12c0-2 4-7 9-7s9 5 9 7-4 7-9 7-9-5-9-7z"/><circle cx="12" cy="12" r="3"/></svg>'; };
        tg.onclick = function (e) { e.preventDefault(); e.stopPropagation(); var i = lay.hidden.indexOf(w.dataset.w); i > -1 ? lay.hidden.splice(i, 1) : lay.hidden.push(w.dataset.w); applyLayout(); paintTg(); save(); };
        paintTg(); if (getComputedStyle(w).position === 'static') w.style.position = 'relative'; w.appendChild(tg);
      } else if (!on && tg) tg.remove();
      if (grid.contains(w)) w.draggable = on;
    });
  }
  // السحب والإفلات جوه الشبكة
  if (grid) {
    var drag = null;
    grid.addEventListener('dragstart', function (e) { var w = e.target.closest && e.target.closest('#dash-grid > [data-w]'); if (!w || !document.body.classList.contains('dash-editing')) return; drag = w; w.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', w.dataset.w); } catch (x) {} });
    grid.addEventListener('dragend', function () { if (drag) drag.classList.remove('dragging'); drag = null; save(); });
    grid.addEventListener('dragover', function (e) {
      if (!drag) return; e.preventDefault();
      var over = e.target.closest && e.target.closest('#dash-grid > [data-w]'); if (!over || over === drag) return;
      var r = over.getBoundingClientRect(), before = (e.clientY - r.top) < r.height / 2;
      grid.insertBefore(drag, before ? over : over.nextSibling);
    });
    grid.addEventListener('click', function (e) { if (document.body.classList.contains('dash-editing') && e.target.closest('a')) e.preventDefault(); }, true);
  }
  if (btn) btn.onclick = function () { editOn(!document.body.classList.contains('dash-editing')); };
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.body.classList.contains('dash-editing')) editOn(false); });
})();
