(function () {
  var D = window.FD; if (!D) return;
  var T = D.tr, form = document.getElementById('fd-form'), list = document.getElementById('fd-list'), rv = document.getElementById('fd-reveal'), people = [], page = 1;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function post(fd) { fd.append('_csrf', D.csrf); return fetch(D.url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }); }
  try { var saved = JSON.parse(localStorage.getItem('fd-f') || 'null'); if (saved) Object.keys(saved).forEach(function (k) { var els = form.querySelectorAll('[name="' + k + '"]'); els.forEach(function (el) { if (el.type === 'checkbox') el.checked = saved[k].indexOf(el.value) > -1; else el.value = saved[k][0] || ''; }); }); } catch (e) {}
  function count() { if (!rv) return; var n = list.querySelectorAll('.fd-c:checked').length; rv.disabled = !n; rv.textContent = T.reveal.replace('%s', n); }
  function draw() {
    if (!people.length) { list.innerHTML = '<div class="p-10 text-center text-sm text-ink-500">' + esc(T.none) + '</div>'; count(); return; }
    list.innerHTML = people.map(function (p, i) {
      return '<label class="flex items-center gap-3 px-4 py-3 ' + (p.lead ? 'bg-lime-50/50' : 'hover:bg-ink-50 cursor-pointer') + '">' +
        (D.canReveal && !p.lead ? '<input type="checkbox" class="fd-c rounded" data-i="' + i + '">' : '<span class="w-4"></span>') +
        '<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><span class="font-medium text-ink-900">' + esc(p.name) + '</span><span class="text-xs text-ink-500">' + esc(p.title) + '</span>' +
        (p.lead ? '<a class="text-[11px] text-lime-700 font-semibold hover:underline" href="' + esc(D.leads) + '#lead-' + p.lead + '">🧲 ' + esc(T.inLeads) + ' ↗</a>' : '') + '</div>' +
        '<div class="text-xs text-ink-500 truncate">' + esc([p.company, p.industry, p.size ? p.size.toLocaleString() + ' ' + T.emp : '', p.city].filter(Boolean).join(' · ')) + '</div></div>' +
        '<div class="flex gap-1 shrink-0">' + (p.has_email ? '<span class="text-[10px] px-1.5 py-0.5 rounded bg-sky-100 text-sky-700">✉️ ' + esc(T.email) + '</span>' : '') + (p.has_phone ? '<span class="text-[10px] px-1.5 py-0.5 rounded bg-lime-100 text-lime-700">📞 ' + esc(T.phone) + '</span>' : '') + '</div></label>';
    }).join('');
    count();
  }
  function search(pg) {
    page = pg || 1;
    var fd = new FormData(form); fd.append('action', 'search'); fd.append('page', page);
    try { var o = {}; fd.forEach(function (v, k) { if (k === 'action' || k === 'page') return; (o[k] = o[k] || []).push(v); }); localStorage.setItem('fd-f', JSON.stringify(o)); } catch (e) {}
    list.innerHTML = '<div class="p-10 text-center text-sm text-ink-500 animate-pulse">' + esc(T.searching) + '</div>';
    post(fd).then(function (r) {
      if (!r.ok) { list.innerHTML = '<div class="p-6 text-sm text-red-700 bg-red-50">' + esc(r.plan ? T.plan : (r.error || T.err)) + (r.plan ? '<div class="text-[11px] text-red-500 mt-1">' + esc(r.error) + '</div>' : '') + '</div>'; return; }
      people = r.people; document.getElementById('fd-left').textContent = r.left;
      document.getElementById('fd-total').textContent = r.total ? T.total.replace('%s', r.total.toLocaleString()) : '';
      var pages = Math.ceil((r.total || 0) / 25);
      document.getElementById('fd-prev').classList.toggle('hidden', page <= 1);
      document.getElementById('fd-next').classList.toggle('hidden', page >= pages || people.length < 25);
      document.getElementById('fd-page').textContent = pages > 1 ? page + ' / ' + pages : '';
      draw();
    }).catch(function () { list.innerHTML = '<div class="p-6 text-sm text-red-700">' + esc(T.err) + '</div>'; });
  }
  /* ---------- البحوث المحفوظة ---------- */
  var sv = document.getElementById('fd-saved');
  function fillForm(f) {
    form.reset();
    Object.keys(f || {}).forEach(function (k) { var v = f[k], els = form.querySelectorAll('[name="' + k + '"], [name="' + k + '[]"]'); els.forEach(function (el) { if (el.type === 'checkbox') el.checked = (v || []).indexOf(el.value) > -1; else el.value = v || ''; }); });
  }
  function drawSaved() {
    var l = D.searches || [];
    sv.innerHTML = !l.length ? '' : '<div class="text-xs font-medium text-ink-500 mb-2">☆ ' + esc(T.saved) + '</div><div class="flex flex-wrap gap-2">' + l.map(function (s) {
      return '<div class="inline-flex items-center gap-1.5 rounded-xl border ' + (s.new_count ? 'border-lime-300 bg-lime-50' : 'border-ink-200 bg-white') + ' ps-3 pe-1 py-1 text-sm" data-s="' + s.id + '">' +
        '<button type="button" class="fd-s-load font-medium text-ink-800 hover:underline" title="' + esc(T.load) + (s.run_at ? ' · ' + esc(T.last.replace('%s', s.run_at.slice(0, 16))) : '') + '">' + esc(s.name) + '</button>' +
        (s.new_count ? '<button type="button" class="fd-s-new rounded-full bg-lime-500 text-white text-[11px] font-bold px-2 py-0.5">' + esc(T.newN.replace('%s', s.new_count)) + '</button>' : '<span class="text-[11px] text-ink-400">' + esc(T.noNew) + '</span>') +
        '<button type="button" class="fd-s-run text-ink-400 hover:text-ink-800 px-1" title="' + esc(T.run) + '">↻</button><button type="button" class="fd-s-del text-ink-400 hover:text-red-600 px-1" aria-label="✕">✕</button></div>';
    }).join('') + '</div>';
  }
  function byS(id) { for (var i = 0; i < (D.searches || []).length; i++) if (D.searches[i].id === id) return D.searches[i]; }
  sv.addEventListener('click', function (e) {
    var box = e.target.closest('[data-s]'); if (!box) return; var id = +box.dataset.s, s = byS(id), fd = new FormData();
    if (e.target.closest('.fd-s-load')) { fillForm(s.filters); search(1); return; }
    if (e.target.closest('.fd-s-del')) { if (!confirm(T.del)) return; fd.append('action', 'del_search'); fd.append('id', id); post(fd).then(function (r) { if (r.ok) { D.searches = r.searches; drawSaved(); } }); return; }
    if (e.target.closest('.fd-s-run')) { fd.append('action', 'run_search'); fd.append('id', id); box.classList.add('animate-pulse'); post(fd).then(function (r) { box.classList.remove('animate-pulse'); if (!r.ok) return alert(r.error); D.searches = r.searches; drawSaved(); }); return; }
    if (e.target.closest('.fd-s-new')) {
      fillForm(s.filters); fd.append('action', 'show_new'); fd.append('id', id);
      list.innerHTML = '<div class="p-10 text-center text-sm text-ink-500 animate-pulse">' + esc(T.searching) + '</div>';
      post(fd).then(function (r) {
        if (!r.ok) { list.innerHTML = '<div class="p-6 text-sm text-red-700">' + esc(r.error || T.err) + '</div>'; return; }
        people = r.people; if (r.searches) { D.searches = r.searches; drawSaved(); }
        document.getElementById('fd-total').textContent = T.newOnly.replace('%s', s.name) + ' · ' + people.length;
        ['fd-prev', 'fd-next'].forEach(function (x) { document.getElementById(x).classList.add('hidden'); }); document.getElementById('fd-page').textContent = '';
        draw();
      });
    }
  });
  document.getElementById('fd-save').addEventListener('click', function () {
    var n = prompt(T.askName); if (!n || !n.trim()) return;
    var fd = new FormData(form); fd.append('action', 'save_search'); fd.append('name', n.trim());
    post(fd).then(function (r) { if (!r.ok) return alert(r.error || T.err); D.searches = r.searches; drawSaved(); });
  });
  drawSaved();
  document.getElementById('fd-look').addEventListener('click', function () {
    var fd = new FormData(); fd.append('action', 'lookalike');
    post(fd).then(function (r) {
      if (!r.ok) return alert(T.lookNo);
      fillForm(r.filters);
      document.getElementById('fd-total').textContent = (r.basis === 'won' ? T.lookWon : T.lookWarm).replace('%s', r.n);
    });
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); search(1); });
  document.getElementById('fd-prev').addEventListener('click', function () { search(page - 1); });
  document.getElementById('fd-next').addEventListener('click', function () { search(page + 1); });
  list.addEventListener('change', count);
  if (rv) rv.addEventListener('click', function () {
    var pick = Array.prototype.map.call(list.querySelectorAll('.fd-c:checked'), function (c) { return people[+c.dataset.i]; });
    if (!pick.length || !confirm(T.confirm.replace('%s', pick.length))) return;
    rv.disabled = true; rv.textContent = T.revealing;
    var fd = new FormData(); fd.append('action', 'reveal'); var ln = document.getElementById('fd-list-name'); fd.append('list', ln ? ln.value.trim() : ''); fd.append('people', JSON.stringify(pick.map(function (p) { return { id: p.id, company: p.company, country: p.country }; })));
    post(fd).then(function (r) {
      var n = 0; people.forEach(function (p) { if (r.done && r.done[p.id]) { p.lead = r.done[p.id]; n++; } });
      document.getElementById('fd-left').textContent = r.left; draw();
      if (r.error) alert(r.error); else if (n) alert(T.done.replace('%s', n));
    }).catch(function () { count(); alert(T.err); });
  });
})();
