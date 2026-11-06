/* الشركات المستهدفة: فلترة فورية، إضافة من غير تحميل، بوب أب الأشخاص، كشف البيانات، وعرض المزيد */
(function () {
  var C = window.TG; if (!C) return; var L = C.L;
  var list = document.getElementById('tg-list'), dlg = document.getElementById('tg-dlg'), cur = null, page = 1;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function post(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

  // فلترة: المصدر + البحث
  var src = '', q = document.getElementById('tg-q');
  function apply() { if (!list) return; var v = (q && q.value || '').trim().toLowerCase();
    list.querySelectorAll('.tg-row').forEach(function (r) { r.style.display = (!src || r.dataset.src === src) && (!v || r.dataset.q.indexOf(v) >= 0) ? '' : 'none'; }); }
  document.querySelectorAll('.tg-chip').forEach(function (b) { b.addEventListener('click', function () { document.querySelectorAll('.tg-chip').forEach(function (x) { x.classList.toggle('on', x === b); }); src = b.dataset.src; apply(); }); });
  if (q) q.addEventListener('input', apply);

  // إضافة
  var add = document.getElementById('tg-add');
  if (add) add.addEventListener('submit', function (e) {
    e.preventDefault(); var inp = add.querySelector('[name=q]'), btn = add.querySelector('button'); if (!inp.value.trim()) return; btn.disabled = true;
    post({ action: 'add', q: inp.value }).then(function (j) { btn.disabled = false;
      if (!j.ok) return toast(j.error || L.err, 'error');
      toast(j.dup ? L.dup : L.added, j.dup ? 'info' : 'success'); inp.value = '';
      location.href = C.url + '&open=' + j.id;
    }).catch(function () { btn.disabled = false; toast(L.err, 'error'); });
  });

  // اقتراح بالذكاء الاصطناعي
  var ai = document.getElementById('tg-ai');
  if (ai) ai.addEventListener('click', function () {
    if (ai.dataset.off) return toast(L.aiOff, 'error');
    var sp = ai.querySelector('span'), old = sp.textContent; ai.disabled = true; sp.textContent = L.aiBusy;
    post({ action: 'ai' }).then(function (j) { ai.disabled = false; sp.textContent = old;
      if (!j.ok) return toast(j.error || L.err, 'error');
      toast(L.aiDone.replace('%s', j.added) + (j.why ? ' — ' + j.why : '')); setTimeout(function () { location.reload(); }, 1200);
    }).catch(function () { ai.disabled = false; sp.textContent = old; toast(L.err, 'error'); });
  });

  // البوب أب
  function personRow(p) {
    var contact = (p.phone ? '<a href="tel:' + esc(p.phone) + '" class="inline-flex items-center gap-1 text-lime-700 hover:underline" dir="ltr">' + ic('phone', 'w-3.5 h-3.5') + esc(p.phone) + '</a>' : '') +
      (p.email ? '<a href="mailto:' + esc(p.email) + '" class="inline-flex items-center gap-1 text-brand-700 hover:underline" dir="ltr">' + ic('mail', 'w-3.5 h-3.5') + esc(p.email) + '</a>' : '');
    return '<div class="flex items-center gap-3 rounded-xl border border-ink-200 p-3"><span class="w-9 h-9 rounded-full bg-lime-50 text-lime-800 grid place-items-center font-bold shrink-0">' + esc((p.name || '?').charAt(0)) + '</span>' +
      '<div class="flex-1 min-w-0"><b class="block text-ink-900 truncate">' + esc(p.name) + '</b><span class="block text-xs text-ink-500 truncate">' + esc(p.title || '') + '</span>' +
      '<div class="flex flex-wrap gap-x-3 gap-y-1 text-xs mt-1">' + contact + '</div></div><a href="' + esc(p.url) + '" class="btn-ghost !py-1 !text-xs">' + esc(L.open) + '</a></div>';
  }
  function apRow(p) {
    return '<div class="flex items-center gap-3 rounded-xl border border-dashed border-ink-200 p-3" data-pid="' + esc(p.id) + '"><span class="w-9 h-9 rounded-full bg-ink-100 text-ink-600 grid place-items-center font-bold shrink-0">' + esc((p.name || '?').charAt(0)) + '</span>' +
      '<div class="flex-1 min-w-0"><b class="block text-ink-900 truncate">' + esc(p.name) + '</b><span class="block text-xs text-ink-500 truncate">' + esc(p.title || '') + '</span>' +
      '<div class="flex gap-2 text-[11px] text-ink-400 mt-1">' + (p.has_phone ? '<span class="inline-flex items-center gap-1">' + ic('phone', 'w-3 h-3') + esc(L.hasPhone) + '</span>' : '') + (p.has_email ? '<span class="inline-flex items-center gap-1">' + ic('mail', 'w-3 h-3') + esc(L.hasEmail) + '</span>' : '') + '</div></div>' +
      (C.reveal ? '<button type="button" class="btn-primary !py-1 !text-xs" data-reveal>' + ic('eye', 'w-3.5 h-3.5') + esc(L.reveal) + '</button>' : '') + '</div>';
  }
  var known = document.getElementById('tg-known'), ap = document.getElementById('tg-ap'), more = document.getElementById('tg-more');
  function loadPeople(id, pg) {
    return fetch(C.url + '&ajax=company&id=' + id + '&page=' + pg, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function open(id) {
    var row = list && list.querySelector('.tg-row[data-id="' + id + '"]'); cur = id; page = 1;
    var logo = row ? row.querySelector('img, span > span.rounded-lg, span.w-9') : null;
    document.getElementById('tg-dlg-logo').innerHTML = logo ? logo.outerHTML : '';
    document.getElementById('tg-dlg-name').textContent = row ? row.querySelector('b').textContent : '';
    var sub = row ? [row.children[1].textContent.trim(), row.children[3].textContent.trim()].filter(function (x) { return x && x !== '—'; }).join(' · ') : '';
    document.getElementById('tg-dlg-sub').textContent = sub;
    var dom = row && row.querySelector('[dir=ltr]'), site = document.getElementById('tg-site');
    if (dom) { site.href = 'https://' + dom.textContent.trim(); site.textContent = dom.textContent.trim(); site.classList.remove('hidden'); } else site.classList.add('hidden');
    known.innerHTML = '<div class="h-14 rounded-xl bg-ink-100 animate-pulse"></div>'; ap.innerHTML = '<div class="h-14 rounded-xl bg-ink-100 animate-pulse"></div><div class="h-14 rounded-xl bg-ink-100 animate-pulse"></div>'; more.classList.add('hidden');
    if (!dlg.open) dlg.showModal();
    loadPeople(id, 1).then(function (j) {
      if (!j.ok) { known.innerHTML = '<p class="text-sm text-red-600">' + esc(j.error || L.err) + '</p>'; ap.innerHTML = ''; return; }
      known.innerHTML = j.known.length ? j.known.map(personRow).join('') : '<p class="text-sm text-ink-400">' + esc(L.none) + '</p>';
      renderAp(j.apollo, false);
    });
  }
  function renderAp(a, append) {
    if (!C.apollo || !a) { ap.innerHTML = '<p class="text-sm text-ink-500">' + esc(L.noApollo) + ' <a class="link" href="' + esc(C.connectUrl) + '">→</a></p>'; more.classList.add('hidden'); return; }
    if (a.reason === 'nodomain') { ap.innerHTML = '<p class="text-sm text-ink-400">' + esc(L.noDomain) + '</p>'; more.classList.add('hidden'); return; }
    if (a.error) { ap.innerHTML = '<p class="text-sm text-red-600">' + esc(a.error) + '</p>'; more.classList.add('hidden'); return; }
    var seen = {}; [].forEach.call(ap.querySelectorAll('[data-pid]'), function (x) { seen[x.dataset.pid] = 1; });
    var h = a.people.filter(function (p) { return !append || !seen[p.id]; }).map(apRow).join('');
    if (append) ap.insertAdjacentHTML('beforeend', h); else ap.innerHTML = h || '<p class="text-sm text-ink-400">' + esc(L.nobody) + '</p>';
    more.classList.toggle('hidden', !a.more);
  }
  more.addEventListener('click', function () { more.disabled = true; page++; loadPeople(cur, page).then(function (j) { more.disabled = false; if (j.ok) renderAp(j.apollo, true); }); });
  ap.addEventListener('click', function (e) {
    var b = e.target.closest('[data-reveal]'); if (!b) return; var row = b.closest('[data-pid]'); b.disabled = true; b.textContent = L.busy;
    post({ action: 'reveal', id: cur, pid: row.dataset.pid }).then(function (j) {
      if (!j.ok) { b.disabled = false; b.textContent = L.reveal; return toast(j.error || L.err, 'error'); }
      row.remove(); if (known.querySelector('p')) known.innerHTML = ''; known.insertAdjacentHTML('afterbegin', personRow(j.person));
      var n = known.firstElementChild; n.style.background = 'rgb(236 252 203)'; setTimeout(function () { n.style.transition = 'background 1s'; n.style.background = ''; }, 50);
    }).catch(function () { b.disabled = false; b.textContent = L.reveal; toast(L.err, 'error'); });
  });
  document.getElementById('tg-remove').addEventListener('click', function () {
    if (!confirm(L.confirmRm)) return; post({ action: 'remove', id: cur }).then(function (j) { if (j.ok) { var r = list.querySelector('.tg-row[data-id="' + cur + '"]'); if (r) r.remove(); dlg.close(); } });
  });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
  if (list) list.addEventListener('click', function (e) { var r = e.target.closest('.tg-row'); if (r) open(r.dataset.id); });
  var m = /[?&]open=(\d+)/.exec(location.search); if (m) open(m[1]);
})();
