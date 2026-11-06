/* الشركات المستهدفة: فلترة فورية، إضافة من غير تحميل، بوب أب الأشخاص، كشف البيانات، وعرض المزيد */
(function () {
  var C = window.TG; if (!C) return; var L = C.L;
  var list = document.getElementById('tg-list'), dlg = document.getElementById('tg-dlg'), cur = null, page = 1;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function post(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch(C.url, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

  var root = document.getElementById('tg'); if (!root) return;
  // فلاتر: المجال، الدولة، الحالة، ومين فيها أشخاص يتوصلّهم
  var F = { ind: document.getElementById('tf-ind'), cty: document.getElementById('tf-cty'), st: document.getElementById('tf-st'), ppl: document.getElementById('tf-ppl') };
  function extra(r) { var d = r.dataset; return (!F.ind.value || d.ind === F.ind.value) && (!F.cty.value || d.cty === F.cty.value) && (!F.st.value || d.st === F.st.value) && (!F.ppl.checked || d.reach === '1'); }
  var mk = MKList(root, { onOpen: function (r) { open(r.dataset.id); }, extra: extra });
  var fpop = document.getElementById('tg-fpop'), fn = document.getElementById('tg-fn');
  document.getElementById('tg-fbtn').addEventListener('click', function (e) { e.stopPropagation(); fpop.classList.toggle('hidden'); });
  document.addEventListener('click', function (e) { if (!fpop.contains(e.target)) fpop.classList.add('hidden'); });
  function fchg() { var n = [F.ind.value, F.cty.value, F.st.value, F.ppl.checked].filter(Boolean).length; fn.textContent = n; fn.classList.toggle('hidden', !n); mk.apply(); }
  [F.ind, F.cty, F.st, F.ppl].forEach(function (x) { x.addEventListener('change', fchg); });
  document.getElementById('tf-reset').addEventListener('click', function () { F.ind.value = F.cty.value = F.st.value = ''; F.ppl.checked = false; fchg(); });

  // تكميل البيانات
  function rowOf(id) { return list.querySelector('[data-mk-row][data-id="' + id + '"]'); }
  function paintRow(r, c) {
    var d = r.dataset; d.name = c.name; d.industry = c.industry; d.ind = (c.industry || '').toLowerCase(); d.cty = (c.country || '').toLowerCase(); d.emp = c.employees || 0; d.loc = c.loc; d.logo = c.logo; d.bare = c.industry ? '0' : '1';
    d.linkedin = c.linkedin; d.phone = c.phone; d.about = c.about;
    d.sub = [c.industry, c.loc, c.employees ? Number(c.employees).toLocaleString() + ' ' + (C.L.emp || '') : ''].filter(Boolean).join(' · ');
    r.querySelector('[data-c=ind]').textContent = c.industry || '—'; r.querySelector('[data-c=emp]').textContent = c.employees ? Number(c.employees).toLocaleString() : '—'; r.querySelector('[data-c=loc]').textContent = c.loc || '—';
    r.querySelector('b').textContent = c.name; r.setAttribute('data-s-ind', d.ind); r.setAttribute('data-s-emp', d.emp);
    if (c.logo) r.querySelector('.mk-av').innerHTML = '<img src="' + esc(c.logo) + '" alt="" class="w-full h-full object-contain bg-white">';
    d.q = (c.name + ' ' + (d.domain || '') + ' ' + (c.industry || '') + ' ' + (c.loc || '')).toLowerCase();
  }
  function enrich(r) { return post({ action: 'enrich', id: r.dataset.id }).then(function (j) { if (j.ok) paintRow(r, j.co); return j; }).catch(function () { return { ok: false }; }); }
  function enrichMsg(j) { if (!j.ok) return toast(j.error || L.err, 'error'); toast(j.changed ? L.enDone : (L.enNone + (L.enApollo ? ' ' + L.enApollo : '')), j.changed ? 'success' : 'info'); }
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-enrich]'); if (!b) return; b.disabled = true; b.classList.add('animate-spin');
    enrich(b.closest('[data-mk-row]')).then(function (j) { b.disabled = false; b.classList.remove('animate-spin'); enrichMsg(j); mk.apply(); }); });
  document.getElementById('tg-en-sel').addEventListener('click', function () { var s = mk.selected().filter(function (r) { return r.dataset.domain; }), n = 0, i = 0; if (!s.length) return toast(L.noDomain, 'error'); toast(L.enBusy, 'info');
    (function next() { if (i >= s.length) { toast(L.enN.replace('%s', n)); mk.apply(); return; } enrich(s[i++]).then(function (j) { if (j.ok && j.changed) n++; next(); }); })(); });
  document.getElementById('tg-csv').addEventListener('click', function () {
    var s = mk.selected(); if (!s.length) return; var q = function (v) { v = String(v == null ? '' : v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
    var lines = [L.csv.map(q).join(',')].concat(s.map(function (r) { var d = r.dataset, c = r.children; return [d.name, d.domain, d.industry, d.emp > 0 ? d.emp : '', d.loc, r.getAttribute('data-s-ppl'), c[6].textContent.trim(), c[7].textContent.trim()].map(q).join(','); }));
    var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\ufeff' + lines.join('\n')], { type: 'text/csv;charset=utf-8' })); a.download = 'target-companies.csv'; a.click(); });
  root.addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (!b) return; var r = b.closest('[data-mk-row]'); if (!confirm(L.confirmRm)) return;
    post({ action: 'remove', id: r.dataset.id }).then(function (j) { if (j.ok) mk.remove(r); }); });
  var rmSel = document.getElementById('tg-rm-sel');
  if (rmSel) rmSel.addEventListener('click', function () { var s = mk.selected(); if (!s.length || !confirm(L.confirmRmN.replace('%s', s.length))) return;
    Promise.all(s.map(function (r) { return post({ action: 'remove', id: r.dataset.id }).then(function (j) { if (j.ok) mk.remove(r); }); })); });

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
    return '<div class="flex items-center gap-3 rounded-xl border border-ink-200 p-3" data-lid="' + esc(p.id) + '"><span class="w-9 h-9 rounded-full bg-lime-50 text-lime-800 grid place-items-center font-bold shrink-0">' + esc((p.name || '?').charAt(0)) + '</span>' +
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
    var row = list && list.querySelector('[data-mk-row][data-id="' + id + '"]'); cur = id; page = 1;
    var lg = document.getElementById('tg-dlg-logo'), d = row ? row.dataset : {};
    lg.innerHTML = d.logo ? '<img src="' + esc(d.logo) + '" alt="" class="w-full h-full object-contain bg-white">' : esc((d.name || '?').charAt(0));
    document.getElementById('tg-dlg-name').textContent = d.name || ''; document.getElementById('tg-dlg-sub').textContent = d.sub || '';
    var site = document.getElementById('tg-site');
    if (d.domain) { site.href = 'https://' + d.domain; site.textContent = d.domain; site.classList.remove('hidden'); } else site.classList.add('hidden');
    info(d); known.innerHTML = '<div class="h-14 mk-sk"></div>'; ap.innerHTML = '<div class="h-16 mk-sk"></div><div class="h-16 mk-sk"></div><div class="h-16 mk-sk"></div>'; more.classList.add('hidden');
    if (!dlg.open) dlg.showModal();
    loadPeople(id, 1).then(function (j) {
      if (!j.ok) { known.innerHTML = '<p class="text-sm text-red-600">' + esc(j.error || L.err) + '</p>'; ap.innerHTML = ''; return; }
      if (j.co && row) { row.dataset.linkedin = j.co.linkedin; row.dataset.phone = j.co.phone; row.dataset.about = j.co.about; info(row.dataset); }
      known.innerHTML = j.known.length ? j.known.map(personRow).join('') : '<p class="text-sm text-ink-400">' + esc(L.none) + '</p>';
      renderAp(j.apollo, false);
    });
  }
  function info(d) {
    var box = document.getElementById('tg-info'), ab = document.getElementById('tg-about');
    var cells = [[L.iInd, d.industry], [L.iEmp, d.emp > 0 ? Number(d.emp).toLocaleString() : ''], [L.iLoc, d.loc], [L.iPhone, d.phone ? '<a dir="ltr" class="link" href="tel:' + esc(d.phone) + '">' + esc(d.phone) + '</a>' : '', 1],
      [L.iIn, d.linkedin ? '<a class="link" target="_blank" rel="noopener" href="' + esc(d.linkedin) + '">LinkedIn ↗</a>' : '', 1]];
    box.innerHTML = cells.map(function (x) { return '<div class="rounded-xl bg-ink-50 px-3 py-2 min-w-0"><dt class="text-[11px] text-ink-500">' + esc(x[0]) + '</dt><dd class="font-medium text-ink-900 truncate">' + (x[1] ? (x[2] ? x[1] : esc(x[1])) : '<span class="text-ink-300">—</span>') + '</dd></div>'; }).join('');
    ab.textContent = d.about || ''; ab.classList.toggle('hidden', !d.about);
    document.getElementById('tg-enrich').classList.toggle('hidden', !d.domain);
  }
  document.getElementById('tg-enrich').addEventListener('click', function () { var b = this, r = rowOf(cur), sp = b.querySelector('span'), old = sp.textContent; if (!r) return; b.disabled = true; sp.textContent = L.enBusy;
    enrich(r).then(function (j) { b.disabled = false; sp.textContent = old; enrichMsg(j); if (j.ok) { var d = r.dataset; info(d); document.getElementById('tg-dlg-name').textContent = d.name; document.getElementById('tg-dlg-sub').textContent = d.sub;
      if (d.logo) document.getElementById('tg-dlg-logo').innerHTML = '<img src="' + esc(d.logo) + '" alt="" class="w-full h-full object-contain bg-white">'; mk.apply(); } }); });
  var pf = document.querySelector('#tg-addp form');
  pf.addEventListener('submit', function (e) { e.preventDefault(); var b = pf.querySelector('button'), o = { action: 'person', id: cur }; ['name', 'title', 'phone', 'email'].forEach(function (k) { o[k] = pf.elements[k].value; }); b.disabled = true;
    post(o).then(function (j) { b.disabled = false; if (!j.ok) return toast(j.error || L.err, 'error'); toast(j.merged ? L.pMerged : L.pAdded); pf.reset(); document.getElementById('tg-addp').open = false;
      if (known.querySelector('p')) known.innerHTML = ''; var ex = known.querySelector('[data-lid="' + j.person.id + '"]'); if (ex) ex.remove(); known.insertAdjacentHTML('afterbegin', personRow(j.person)); }).catch(function () { b.disabled = false; toast(L.err, 'error'); }); });
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
    if (!confirm(L.confirmRm)) return; post({ action: 'remove', id: cur }).then(function (j) { if (j.ok) { var r = list.querySelector('[data-mk-row][data-id="' + cur + '"]'); if (r) mk.remove(r); dlg.close(); } });
  });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
  var m = /[?&]open=(\d+)/.exec(location.search); if (m) open(m[1]);
})();
