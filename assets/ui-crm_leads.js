(function () {
  var D = window.LD; if (!D) return;
  var T = D.tr, leads = D.leads;
  var list = document.getElementById('ld-list'), board = document.getElementById('ld-board'), none = document.getElementById('ld-none');
  var f = { stage: '', src: '', own: '', due: false, q: '' };
  var view = 'list';
  try { view = localStorage.getItem('ld-view') || 'list'; } catch (e) {}
  var stageColor = { new: 'bg-sky-100 text-sky-700', contacted: 'bg-violet-100 text-violet-700', qualified: 'bg-amber-100 text-amber-800', proposal: 'bg-orange-100 text-orange-800', won: 'bg-lime-100 text-lime-800', lost: 'bg-ink-100 text-ink-500' };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function post(data) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) for (var k in data) fd.append(k, data[k]);
    fd.append('_csrf', D.csrf);
    return fetch(D.url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); });
  }
  function closed(l) { return l.stage === 'won' || l.stage === 'lost'; }
  function dueState(l) {
    if (!l.next_at || closed(l)) return '';
    var t = new Date(l.next_at), n = new Date();
    if (t < n) return 'over';
    var end = new Date(); end.setHours(23, 59, 59, 999);
    return t <= end ? 'today' : 'later';
  }
  function fmtNext(l) {
    if (!l.next_at) return '';
    var d = new Date(l.next_at);
    return d.toLocaleDateString(document.documentElement.lang || undefined, { day: 'numeric', month: 'short' }) + ' ' + d.toLocaleTimeString(document.documentElement.lang || undefined, { hour: 'numeric', minute: '2-digit' });
  }
  function match(l) {
    if (f.stage && l.stage !== f.stage) return false;
    if (f.src && l.source !== f.src) return false;
    if (f.own !== '' && String(l.owner) !== f.own) return false;
    if (f.due && ['over', 'today'].indexOf(dueState(l)) < 0) return false;
    if (f.q) { var h = (l.name + ' ' + l.company + ' ' + l.email + ' ' + l.phone + ' ' + l.tags + ' ' + l.title).toLowerCase(); if (h.indexOf(f.q) < 0) return false; }
    return true;
  }
  function nextBadge(l) {
    var s = dueState(l); if (!s) return '';
    var cls = s === 'over' ? 'bg-red-50 text-red-700 border-red-200' : s === 'today' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-ink-50 text-ink-600 border-ink-200';
    return '<span class="inline-flex items-center gap-1 text-[11px] px-1.5 py-0.5 rounded-md border ' + cls + '" title="' + esc(l.next_note) + '">⏰ ' + (s === 'over' ? T.overdue + ' · ' : '') + esc(fmtNext(l)) + '</span>';
  }
  function ownerName(l) { return l.owner ? (D.members[l.owner] || '—') : T.noowner; }
  function avatar(l, sz) {
    var i = (l.name || '?').trim().split(/\s+/).map(function (w) { return w.charAt(0); }).slice(0, 2).join('').toUpperCase();
    return '<div class="' + (sz || 'w-9 h-9') + ' rounded-full bg-gradient-to-br from-brand-500 to-cyan-500 text-white text-xs font-bold grid place-items-center shrink-0">' + esc(i) + '</div>';
  }
  function row(l) {
    return '<div class="ld-row flex items-center gap-3 px-4 py-3 hover:bg-ink-50 cursor-pointer" data-id="' + l.id + '" tabindex="0" role="button">' + avatar(l) +
      '<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><span class="font-medium text-ink-900">' + esc(l.name) + '</span>' +
      '<span class="text-[11px] px-1.5 py-0.5 rounded-md ' + stageColor[l.stage] + '">' + esc(D.stages[l.stage]) + '</span>' + nextBadge(l) + '</div>' +
      '<div class="text-xs text-ink-500 truncate">' + esc([l.title, l.company].filter(Boolean).join(' · ') || '—') + '</div></div>' +
      '<div class="hidden md:block text-xs text-ink-500 w-44 truncate" dir="ltr">' + esc(l.phone || (l.pending ? '⏳' : '')) + (l.email ? '<div class="truncate">' + esc(l.email) + '</div>' : '') + '</div>' +
      '<div class="hidden lg:block text-xs text-ink-500 w-28 truncate">' + esc(D.sources[l.source] || '') + '</div>' +
      '<div class="hidden sm:block text-xs text-ink-500 w-28 truncate">' + esc(ownerName(l)) + '</div>' +
      (l.value ? '<div class="text-sm font-semibold text-ink-900 w-20 text-end">' + esc(Number(l.value).toLocaleString()) + '</div>' : '<div class="w-20"></div>') + '</div>';
  }
  function card(l) {
    return '<div class="ld-card rounded-xl border border-ink-200 bg-white p-3 cursor-pointer hover:border-brand-300 hover:shadow-sm" draggable="' + (l.edit ? 'true' : 'false') + '" data-id="' + l.id + '" tabindex="0" role="button">' +
      '<div class="flex items-center gap-2">' + avatar(l, 'w-7 h-7') + '<div class="min-w-0"><div class="text-sm font-medium text-ink-900 truncate">' + esc(l.name) + '</div><div class="text-[11px] text-ink-500 truncate">' + esc(l.company || l.title || '—') + '</div></div></div>' +
      '<div class="flex flex-wrap items-center gap-1.5 mt-2">' + nextBadge(l) + (l.value ? '<span class="text-xs font-semibold text-ink-900 ms-auto">' + esc(Number(l.value).toLocaleString()) + '</span>' : '') + '</div></div>';
  }
  function draw() {
    var shown = leads.filter(match);
    var cnt = {}; leads.forEach(function (l) { if (match(Object.assign({}, l, { stage: f.stage || l.stage }))) cnt[l.stage] = (cnt[l.stage] || 0) + 1; });
    document.querySelectorAll('[data-cnt]').forEach(function (s) { s.textContent = cnt[s.dataset.cnt] || 0; });
    document.querySelectorAll('.ld-view').forEach(function (b) { var on = b.dataset.v === view; b.classList.toggle('bg-brand-600', on); b.classList.toggle('text-white', on); b.classList.toggle('text-ink-600', !on); b.setAttribute('aria-pressed', on); });
    list.classList.toggle('hidden', view !== 'list' || !shown.length);
    board.classList.toggle('hidden', view !== 'board' || !leads.length);
    none.classList.toggle('hidden', shown.length > 0 || !leads.length || view === 'board');
    if (view === 'list') list.innerHTML = shown.map(row).join('');
    else board.innerHTML = Object.keys(D.stages).filter(function (s) { return !f.stage || s === f.stage; }).map(function (s) {
      var col = shown.filter(function (l) { return l.stage === s; });
      var sum = col.reduce(function (a, l) { return a + (+l.value || 0); }, 0);
      return '<div class="ld-col rounded-2xl bg-ink-50 border border-ink-200 p-2 min-h-[200px] flex flex-col" data-s="' + s + '"><div class="flex items-center gap-2 px-1 py-1.5"><span class="text-[11px] px-1.5 py-0.5 rounded-md ' + stageColor[s] + '">' + esc(D.stages[s]) + '</span><span class="text-xs text-ink-500">' + col.length + '</span>' + (sum ? '<span class="text-xs text-ink-500 ms-auto">' + sum.toLocaleString() + '</span>' : '') + '</div>' +
        '<div class="space-y-2 flex-1">' + col.map(card).join('') + '</div></div>';
    }).join('');
  }

  document.querySelectorAll('.ld-view').forEach(function (b) { b.addEventListener('click', function () { view = b.dataset.v; try { localStorage.setItem('ld-view', view); } catch (e) {} draw(); }); });
  document.querySelectorAll('#ld-stages .chip').forEach(function (b) { b.addEventListener('click', function () { document.querySelectorAll('#ld-stages .chip').forEach(function (x) { x.classList.toggle('on', x === b); }); f.stage = b.dataset.s; draw(); }); });
  document.getElementById('ld-src').addEventListener('change', function () { f.src = this.value; draw(); });
  document.getElementById('ld-own').addEventListener('change', function () { f.own = this.value; draw(); });
  var dueBox = document.getElementById('ld-due');
  dueBox.addEventListener('change', function () { f.due = this.checked; draw(); });
  document.getElementById('ld-due-tile').addEventListener('click', function () { dueBox.checked = !dueBox.checked; f.due = dueBox.checked; draw(); });
  document.getElementById('ld-q').addEventListener('input', function () { f.q = this.value.trim().toLowerCase(); draw(); });

  // السحب بين المراحل
  var dragId = null;
  board.addEventListener('dragstart', function (e) { var c = e.target.closest('.ld-card'); if (!c) return; dragId = +c.dataset.id; e.dataTransfer.effectAllowed = 'move'; c.classList.add('opacity-50'); });
  board.addEventListener('dragend', function () { dragId = null; board.querySelectorAll('.ld-col').forEach(function (c) { c.classList.remove('ring-2', 'ring-brand-400'); }); });
  board.addEventListener('dragover', function (e) { var c = e.target.closest('.ld-col'); if (!c || dragId === null) return; e.preventDefault(); board.querySelectorAll('.ld-col').forEach(function (x) { x.classList.toggle('ring-2', x === c); x.classList.toggle('ring-brand-400', x === c); }); });
  board.addEventListener('drop', function (e) {
    var c = e.target.closest('.ld-col'); if (!c || dragId === null) return; e.preventDefault();
    var l = byId(dragId), s = c.dataset.s; if (!l || l.stage === s) return draw();
    var old = l.stage; l.stage = s; draw();
    post({ action: 'stage', id: l.id, stage: s }).then(function (r) { if (!r.ok) { l.stage = old; draw(); alert(r.error || T.err); } }).catch(function () { l.stage = old; draw(); });
  });
  function byId(id) { for (var i = 0; i < leads.length; i++) if (leads[i].id === id) return leads[i]; return null; }

  // الدرج
  var dr = document.getElementById('ld-drawer'), form = document.getElementById('ld-form'), msg = document.getElementById('ld-msg'), cur = null, lastFocus = null;
  function openModal(m) { lastFocus = document.activeElement; m.classList.remove('hidden'); m.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
  function closeModal(m) { m.classList.add('hidden'); m.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; if (lastFocus) lastFocus.focus(); }
  [dr, document.getElementById('ld-add')].forEach(function (m) { m.querySelectorAll('[data-close]').forEach(function (x) { x.addEventListener('click', function () { closeModal(m); }); }); });
  document.addEventListener('keydown', function (e) { if (e.key !== 'Escape') return; [dr, document.getElementById('ld-add')].forEach(function (m) { if (!m.classList.contains('hidden')) closeModal(m); }); });
  function quick(l) {
    var b = [];
    if (l.phone) b.push('<a class="btn-ghost text-sm" href="tel:' + esc(l.phone) + '">📞 ' + T.call + '</a>');
    if (l.wa) b.push('<a class="btn-ghost text-sm" target="_blank" rel="noopener" href="https://wa.me/' + esc(l.wa) + '">💬 WhatsApp</a>');
    if (l.email) b.push('<a class="btn-ghost text-sm" href="mailto:' + esc(l.email) + '">✉️ ' + T.email + '</a>');
    if (l.linkedin) b.push('<a class="btn-ghost text-sm" target="_blank" rel="noopener" href="' + esc(l.linkedin) + '">in</a>');
    if (l.pending && !l.phone) b.push('<span class="text-xs text-ink-500 self-center animate-pulse">⏳ ' + T.pending + '</span>');
    return b.join('');
  }
  function open(id) {
    var l = byId(id); if (!l) return; cur = l;
    document.getElementById('ld-d-title').textContent = l.name;
    document.getElementById('ld-d-sub').textContent = [l.title, l.company].filter(Boolean).join(' · ');
    document.getElementById('ld-quick').innerHTML = quick(l);
    ['id', 'name', 'company', 'title', 'phone', 'email', 'value', 'tags', 'note', 'next_at', 'next_note'].forEach(function (k) { form.elements[k].value = l[k] == null ? '' : l[k]; });
    form.elements.owner.value = String(l.owner || 0);
    form.querySelectorAll('[name=stage]').forEach(function (r) { r.checked = r.value === l.stage; });
    form.querySelectorAll('input,select,textarea,button:not([data-close])').forEach(function (x) { if (x.name !== 'owner' || D.canManage) x.disabled = !l.edit; });
    document.getElementById('ld-meta').textContent = T.from.replace('%s', D.sources[l.source] || l.source).replace('%s', (l.created || '').slice(0, 10)) + (l.edit ? '' : ' · ' + T.ro);
    msg.textContent = '';
    openModal(dr);
    form.elements.name.focus();
  }
  [list, board].forEach(function (el) {
    el.addEventListener('click', function (e) { var r = e.target.closest('[data-id]'); if (r) open(+r.dataset.id); });
    el.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.dataset.id) { e.preventDefault(); open(+e.target.dataset.id); } });
  });
  document.getElementById('ld-next-quick').addEventListener('click', function (e) {
    var b = e.target.closest('[data-d]'); if (!b) return;
    if (+b.dataset.d < 0) { form.elements.next_at.value = ''; return; }
    var d = new Date(); d.setDate(d.getDate() + +b.dataset.d); d.setHours(+b.dataset.h, 0, 0, 0);
    if (d < new Date()) d = new Date(Date.now() + 3600e3);
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    form.elements.next_at.value = d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault(); if (!cur) return;
    var fd = new FormData(form); var sv = document.getElementById('ld-save'); sv.disabled = true;
    post(fd).then(function (r) {
      sv.disabled = false;
      if (!r.ok) { msg.className = 'text-xs text-red-600'; msg.textContent = r.error || T.err; return; }
      Object.assign(cur, r.lead); msg.className = 'text-xs text-lime-700'; msg.textContent = T.saved;
      document.getElementById('ld-quick').innerHTML = quick(cur); draw();
      setTimeout(function () { closeModal(dr); }, 500);
    }).catch(function () { sv.disabled = false; msg.className = 'text-xs text-red-600'; msg.textContent = T.err; });
  });
  var del = document.getElementById('ld-del');
  if (del) del.addEventListener('click', function () {
    if (!cur || !confirm(T.del)) return;
    post({ action: 'delete', id: cur.id }).then(function (r) { if (!r.ok) return alert(r.error || T.err); leads = leads.filter(function (l) { return l !== cur; }); closeModal(dr); draw(); });
  });

  // إضافة
  var add = document.getElementById('ld-add'), af = document.getElementById('ld-add-form');
  document.getElementById('ld-add-btn').addEventListener('click', function () { af.reset(); document.getElementById('ld-add-msg').textContent = ''; openModal(add); af.elements.name.focus(); });
  af.addEventListener('submit', function (e) {
    e.preventDefault();
    post(new FormData(af)).then(function (r) {
      if (!r.ok) { document.getElementById('ld-add-msg').textContent = r.error || T.err; return; }
      var ex = byId(r.lead.id);
      if (ex) Object.assign(ex, r.lead); else leads.unshift(r.lead);
      var em = document.getElementById('ld-empty'); if (em) em.remove();
      closeModal(add); draw(); open(r.lead.id);
      if (r.merged) { msg.className = 'text-xs text-amber-700'; msg.textContent = T.merged; }
    });
  });

  draw();
  var m = location.hash.match(/^#lead-(\d+)$/); if (m) open(+m[1]);
})();
