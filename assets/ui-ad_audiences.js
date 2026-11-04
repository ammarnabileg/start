// الجماهير: بحث، فلترة بالنوع، أشرطة الحجم، كروت أنواع، نصائح، اختصارات
(function () {
  var X = window.AUDX || {};
  if (document.body.dataset.audUx) return; document.body.dataset.audUx = '1';
  var rows = [].slice.call(document.querySelectorAll('.aud-row')), q = document.getElementById('aud-q'), chips = document.getElementById('aud-chips'), cur = '';
  requestAnimationFrame(function () { [].forEach.call(document.querySelectorAll('.aud-bar'), function (b) { b.style.transition = 'width .6s'; b.style.width = b.dataset.w + '%'; }); });
  function apply() {
    var v = q ? q.value.trim().toLowerCase() : '', n = 0;
    rows.forEach(function (r) { var ok = (!cur || r.dataset.type === cur) && (!v || r.dataset.q.indexOf(v) >= 0); r.style.display = ok ? '' : 'none'; if (ok) n++; });
    var c = document.getElementById('aud-count'); if (c) c.textContent = n;
    var e = document.getElementById('aud-empty'); if (e) e.hidden = n > 0;
  }
  if (chips && rows.length) {
    var types = {}; rows.forEach(function (r) { types[r.dataset.type] = types[r.dataset.type] || { l: r.dataset.tl, n: 0 }; types[r.dataset.type].n++; });
    var draw = function () {
      chips.innerHTML = [['', X.all + ' ' + rows.length]].concat(Object.keys(types).map(function (k) { return [k, types[k].l + ' ' + types[k].n]; })).map(function (c) {
        return '<button type="button" data-t="' + c[0] + '" class="rounded-full text-xs px-2.5 py-1 border ' + (c[0] === cur ? 'bg-brand-600 text-white border-brand-600' : 'border-ink-200 text-ink-700 hover:bg-ink-50') + '">' + c[1].replace(/</g, '&lt;') + '</button>';
      }).join('');
    };
    chips.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; cur = b.dataset.t; draw(); apply(); });
    draw();
  }
  if (q) q.addEventListener('input', apply);
  // نوع الجمهور كروت + نصيحة
  var sel = document.getElementById('audType');
  if (sel && sel.options.length) {
    var box = document.createElement('div'); box.className = 'grid gap-2 mt-1'; box.id = 'aud-types';
    var hint = document.createElement('p'); hint.className = 'text-xs text-brand-700 bg-brand-50 rounded-lg px-3 py-2 mt-2';
    sel.style.display = 'none'; sel.parentNode.appendChild(box); sel.parentNode.appendChild(hint);
    var drawT = function () {
      box.innerHTML = [].map.call(sel.options, function (o) { var on = o.value === sel.value; return '<button type="button" data-v="' + o.value + '" class="text-start rounded-xl border px-3 py-2 text-sm ' + (on ? 'border-brand-500 bg-brand-50 text-brand-800 font-semibold' : 'border-ink-200 text-ink-700 hover:bg-ink-50') + '" aria-pressed="' + on + '">' + o.textContent.replace(/</g, '&lt;') + '</button>'; }).join('');
      hint.textContent = (X.hints || {})[sel.value] || ''; hint.hidden = !hint.textContent;
    };
    box.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; sel.value = b.dataset.v; sel.dispatchEvent(new Event('change')); drawT(); });
    sel.parentNode.parentNode.parentNode.classList.add('aud-typed'); drawT();
  }
  var help = function () {
    var d = document.createElement('dialog'); d.className = 'rounded-2xl p-5 w-80 bg-white text-ink-900';
    d.innerHTML = '<div class="font-bold mb-3">' + X.keys + '</div><div class="space-y-2 text-sm">' + [['/', X.kSearch], ['n', X.kNew]].map(function (k) { return '<div class="flex justify-between"><span>' + k[1] + '</span><kbd class="px-2 rounded bg-ink-100" dir="ltr">' + k[0] + '</kbd></div>'; }).join('') + '</div>';
    document.body.appendChild(d); d.addEventListener('close', function () { d.remove(); }); d.addEventListener('click', function (e) { if (e.target === d) d.close(); }); d.showModal();
  };
  var hb = document.getElementById('aud-help'); if (hb) hb.addEventListener('click', help);
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || document.querySelector('dialog[open]')) return;
    if (e.key === '/' && q) { e.preventDefault(); q.focus(); } else if (e.key === 'n') { var f = document.querySelector('#audForm [name=name]'); if (f) { f.scrollIntoView({ block: 'center' }); f.focus(); } } else if (e.key === '?') help();
  });
})();
