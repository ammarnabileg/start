/* الجماهير: سلّم الجماهير (دافي ← مشابه ← واسع) باقتراح الخطوة الجاية بضغطة، حجم حقيقي بمقياس لوغاريتمي وحكم عليه،
   "اعمل مشابه منه" من أي جمهور، ترتيب، وفورم بأشرطة للأيام ونسبة المشابه وكروت للحساب */
(function () {
  var U = window.AUDU; if (!U) return; var form = document.getElementById('audForm'), rows = [].slice.call(document.querySelectorAll('.aud-row'));
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-5 h-5') : ''; }
  function f(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }
  function fire(el) { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }
  function short(n) { n = +n || 0; return n >= 1e6 ? (n / 1e6).toFixed(n >= 1e7 ? 0 : 1) + 'M' : n >= 1e3 ? Math.round(n / 1e3) + 'K' : String(n); }
  var TI = { meta_engagement: ['heart', '#db2777', 'warm'], engagement: ['heart', '#db2777', 'warm'], website: ['globe', '#2563eb', 'warm'], website_retargeting: ['globe', '#2563eb', 'warm'], customer_list: ['users', '#16a34a', 'warm'], company_list: ['building', '#0891b2', 'warm'], lookalike: ['layers', '#7c3aed', 'look'], saved_targeting: ['target', '#d97706', 'broad'] };
  var A = (U.list || []).map(function (a) { return { id: a.id, name: a.name, type: a.type, size: +a.size || 0, status: a.status || '' }; });
  function fit(n) { return n < 1000 ? ['bad', U.fSmall] : n < 20000 ? ['niche', U.fNiche] : n < 2e6 ? ['good', U.fGood] : ['broad', U.fBroad]; }

  /* ---------- الصفوف ---------- */
  rows.forEach(function (r, i) { var a = A[i]; if (!a) return; var t = TI[a.type] || ['dots', '#64748b'], fz = fit(a.size); r.classList.add('aud2');
    r.insertAdjacentHTML('afterbegin', '<span class="aud-ic" style="--c:' + t[1] + '">' + ic(t[0]) + '</span>');
    var bar = r.querySelector('.aud-bar'); if (bar) { var pct = a.size > 0 ? Math.min(100, Math.max(4, Math.log10(a.size) / 7.5 * 100)) : 0; bar.dataset.w = pct; bar.style.width = pct + '%'; bar.parentNode.title = f(U.people, a.size.toLocaleString('en-US')); }
    var meta = r.querySelector('.flex-1'); if (meta && a.size) meta.insertAdjacentHTML('beforeend', '<span class="aud-fit ' + fz[0] + '">' + esc(short(a.size)) + ' · ' + esc(fz[1]) + '</span>');
    var act = document.createElement('div'); act.className = 'flex items-center gap-1';
    act.innerHTML = (U.canLook && a.type !== 'lookalike' && a.status !== 'too_small' && a.id ? '<button type="button" class="aud-act" data-look="' + esc(a.id) + '">' + ic('layers', 'w-4 h-4') + esc(U.makeLook) + '</button>' : '') + (a.id ? '<button type="button" class="aud-act ghost" data-cp="' + esc(a.id) + '" title="' + esc(U.copyId) + '">' + ic('copy', 'w-4 h-4') + '</button>' : '');
    var del = r.querySelector('form'); if (del) { del.before(act); act.appendChild(del); var db = del.querySelector('button'); db.className = 'aud-act ghost danger'; db.innerHTML = ic('trash', 'w-4 h-4'); db.title = U.del; } else r.appendChild(act); });
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-cp]'); if (b) { if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.cp); if (window.fxToast) fxToast(U.copied, 'success'); }
    b = e.target.closest('[data-look]'); if (b) prefill('lookalike', { source_audience: b.dataset.look, name: f(U.lookName, (A.filter(function (x) { return x.id === b.dataset.look; })[0] || {}).name || '') }); });
  // ترتيب
  var head = document.getElementById('aud-q'); if (head && rows.length > 1) { var so = document.createElement('select'); so.className = 'input !mt-0 !py-1.5 !h-9 text-sm !w-auto'; so.innerHTML = '<option value="">' + esc(U.sortDef) + '</option><option value="size">' + esc(U.sortSize) + '</option><option value="name">' + esc(U.sortName) + '</option>'; head.before(so);
    so.addEventListener('change', function () { var list = rows.slice(), par = rows[0].parentNode, empty = document.getElementById('aud-empty');
      if (so.value === 'size') list.sort(function (x, y) { return (A[rows.indexOf(y)] || {}).size - (A[rows.indexOf(x)] || {}).size; }); else if (so.value === 'name') list.sort(function (x, y) { return (A[rows.indexOf(x)].name || '').localeCompare(A[rows.indexOf(y)].name || '', 'ar'); });
      list.forEach(function (r) { par.insertBefore(r, empty); }); }); }

  /* ---------- سلّم الجماهير ---------- */
  var host = document.querySelector('.grid.lg\\:grid-cols-\\[minmax\\(0\\,1fr\\)_400px\\]');
  if (host) { var warm = A.filter(function (a) { return (TI[a.type] || [])[2] === 'warm' && a.status !== 'too_small'; }), look = A.filter(function (a) { return a.type === 'lookalike'; }), broad = A.filter(function (a) { return a.type === 'saved_targeting'; });
    var big = warm.slice().sort(function (x, y) { return y.size - x.size; })[0], next;
    if (!warm.length) next = [U.nWarm, 'warm']; else if (!look.length && U.canLook) next = [f(U.nLook, big.name), 'look', big.id]; else next = [U.nScale, 'scale'];
    var tier = function (k, label, d, list, c) { return '<div class="aud-tier' + (list.length ? ' has' : '') + '" style="--c:' + c + '"><b>' + esc(label) + '</b><small>' + esc(d) + '</small><span>' + (list.length ? esc(f(U.nHave, list.length)) : esc(U.none)) + '</span></div>'; };
    var lad = document.createElement('section'); lad.className = 'aud-lad';
    lad.innerHTML = '<div class="flex flex-wrap items-center gap-2 mb-3"><b class="text-ink-900">' + ic('layers', 'w-4 h-4 inline') + ' ' + esc(U.ladT) + '</b><small class="text-xs text-ink-500">' + esc(U.ladD) + '</small></div><div class="aud-tiers">' +
      tier('warm', U.tWarm, U.tWarmD, warm, '#db2777') + '<i>←</i>' + tier('look', U.tLook, U.tLookD, look, '#7c3aed') + '<i>←</i>' + tier('broad', U.tBroad, U.tBroadD, broad, '#d97706') + '</div>' +
      '<div class="aud-next"><span>💡</span><p>' + esc(next[0]) + '</p>' + (next[1] === 'look' ? '<button type="button" class="btn-primary !py-1.5 !text-xs" data-look="' + esc(next[2]) + '">' + esc(U.doIt) + '</button>' : next[1] === 'warm' ? '<button type="button" class="btn-primary !py-1.5 !text-xs" data-warm>' + esc(U.doIt) + '</button>' : '<a class="btn-ghost !py-1.5 !text-xs" href="' + esc(U.builder) + '">' + esc(U.toBuilder) + '</a>') + '</div>';
    host.before(lad); lad.addEventListener('click', function (e) { if (e.target.closest('[data-warm]')) prefill(U.isMeta ? 'meta_engagement' : 'website', { name: U.warmName, days: '90' }); }); }

  /* ---------- الفورم ---------- */
  function prefill(type, vals) { if (!form) return; var t = form.querySelector('#audType'); if (![].some.call(t.options, function (o) { return o.value === type; })) return; t.value = type; fire(t);
    var cards = document.querySelectorAll('#aud-types button'); cards.forEach(function (b) { b.click && b.dataset.v === type && b.click(); });
    Object.keys(vals || {}).forEach(function (k) { var el = form.elements[k]; if (el) { el.value = vals[k]; fire(el); } });
    var sec = document.getElementById('aud-new'); sec.scrollIntoView({ behavior: 'smooth', block: 'start' }); sec.classList.add('ab-flash'); setTimeout(function () { sec.classList.remove('ab-flash'); }, 1400); form.elements.name.focus(); }
  if (form) {
    var days = form.elements.days; if (days) { var w = document.createElement('div'); w.className = 'aud-sl'; days.after(w); days.classList.add('abx-hide');
      w.innerHTML = '<div class="flex items-center justify-between"><input type="range" min="1" max="180" value="' + (+days.value || 30) + '" class="flex-1 accent-brand-600"><b class="w-16 text-end tabular-nums"></b></div><div class="abx-quick">' + [7, 30, 90, 180].map(function (d) { return '<button type="button" data-d="' + d + '">' + esc(f(U.dN, d)) + '</button>'; }).join('') + '</div>';
      var r = w.querySelector('input'), lb = w.querySelector('b'), sync = function (v) { days.value = v; r.value = v; lb.textContent = f(U.dN, v); fire(days); };
      r.addEventListener('input', function () { sync(r.value); }); w.addEventListener('click', function (e) { var b = e.target.closest('[data-d]'); if (b) sync(b.dataset.d); }); lb.textContent = f(U.dN, r.value); }
    var ra = form.elements.ratio; if (ra) { var w2 = document.createElement('div'); w2.className = 'aud-sl col-span-full'; var cell = ra.closest('div'); cell.after(w2); cell.classList.add('abx-hide');
      w2.innerHTML = '<div class="flex items-center justify-between text-xs text-ink-500 mb-1"><span>' + esc(U.precise) + '</span><b class="text-sm text-ink-900 tabular-nums"></b><span>' + esc(U.wide) + '</span></div><input type="range" min="1" max="10" value="' + (+ra.value || 1) + '" class="w-full accent-violet-600"><p class="text-[11px] text-ink-500 mt-1"></p>';
      var r2 = w2.querySelector('input'), b2 = w2.querySelector('b'), p2 = w2.querySelector('p'), s2 = function () { ra.value = r2.value; b2.textContent = r2.value + '%'; p2.textContent = +r2.value <= 2 ? U.rTip1 : +r2.value <= 5 ? U.rTip2 : U.rTip3; fire(ra); };
      r2.addEventListener('input', s2); s2(); }
  }
  // الحساب كروت
  var acc = document.querySelector('form[method=get] select[name=t]'); if (acc && acc.options.length > 1) { var cb = document.createElement('div'); cb.className = 'adq-cards acc';
    cb.innerHTML = [].map.call(acc.options, function (o) { var p = o.text.split(' — '); return '<button type="button" data-v="' + esc(o.value) + '" class="adq-card' + (o.selected ? ' on' : '') + '"><b>' + esc(p[0]) + '</b><small>' + esc(p[1] || '') + '</small></button>'; }).join('');
    acc.classList.add('abx-hide'); acc.after(cb); cb.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (!b || b.classList.contains('on')) return; acc.value = b.dataset.v; cb.querySelectorAll('.adq-card').forEach(function (x) { x.classList.toggle('on', x === b); }); acc.form.submit(); }); }
})();
