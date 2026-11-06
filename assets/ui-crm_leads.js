(function () {
  var D = window.LD; if (!D) return;
  var T = D.tr, leads = D.leads;
  var list = document.getElementById('ld-list'), board = document.getElementById('ld-board'), none = document.getElementById('ld-none');
  var f = { stage: '', src: '', own: '', due: false, q: '', cold: false, tag: '', sort: '' }, sel = {};
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
  // تواريخ السيرفر متخزنة UTC ("2026-11-06 04:12:00")، فلازم نقراها UTC ونعرضها بتوقيت الشركة
  function srvDate(s) { s = String(s || ''); return new Date(/[zZ]|[+-]\d\d:?\d\d$/.test(s) ? s : s.replace(' ', 'T') + 'Z'); }
  function isCold(l) { return !closed(l) && !l.next_at && (Date.now() - srvDate(l.last || l.created).getTime()) > 7 * 864e5; }
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
    if (f.cold && !isCold(l)) return false;
    if (f.tag && (', ' + (l.tags || '') + ',').indexOf(', ' + f.tag + ',') < 0 && (l.tags || '').split(',').map(function (x) { return x.trim(); }).indexOf(f.tag) < 0) return false;
    if (f.q) { var h = (l.name + ' ' + l.company + ' ' + l.email + ' ' + l.phone + ' ' + l.tags + ' ' + l.title).toLowerCase(); if (h.indexOf(f.q) < 0) return false; }
    return true;
  }
  function nextBadge(l) {
    var s = dueState(l); if (!s) return '';
    var cls = s === 'over' ? 'bg-red-50 text-red-700 border-red-200' : s === 'today' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-ink-50 text-ink-600 border-ink-200';
    return '<span class="inline-flex items-center gap-1 text-[11px] px-1.5 py-0.5 rounded-md border ' + cls + '" title="' + esc(l.next_note) + '">⏰ ' + (s === 'over' ? T.overdue + ' · ' : '') + esc(fmtNext(l)) + '</span>';
  }
  function esBadge(l) { var s = T.es && T.es[l.es]; return s ? ' <span class="text-[9px] px-1 rounded ' + s[1] + '" title="' + esc(s[0]) + '">' + (s[1].indexOf('lime') > -1 ? '✓' : s[1].indexOf('amber') > -1 ? '?' : '✕') + '</span>' : ''; }
  function ownerName(l) { return l.owner ? (D.members[l.owner] || '—') : T.noowner; }
  function avatar(l, sz) {
    var i = (l.name || '?').trim().split(/\s+/).map(function (w) { return w.charAt(0); }).slice(0, 2).join('').toUpperCase();
    return '<div class="' + (sz || 'w-9 h-9') + ' rounded-full bg-gradient-to-br from-brand-500 to-cyan-500 text-white text-xs font-bold grid place-items-center shrink-0">' + esc(i) + '</div>';
  }
  function row(l) {
    return '<div class="ld-row flex items-center gap-3 px-4 py-3 hover:bg-ink-50 cursor-pointer' + (sel[l.id] ? ' bg-brand-50/60' : '') + '" data-id="' + l.id + '" tabindex="0" role="button">' +
      (l.edit ? '<input type="checkbox" class="ld-sel rounded" data-sid="' + l.id + '"' + (sel[l.id] ? ' checked' : '') + ' aria-label="' + esc(l.name) + '">' : '<span class="w-4"></span>') + avatar(l) +
      '<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><span class="font-medium text-ink-900">' + esc(l.name) + '</span>' + scoreBadge(l) +
      '<span class="text-[11px] px-1.5 py-0.5 rounded-md ' + stageColor[l.stage] + '">' + esc(D.stages[l.stage]) + '</span>' + nextBadge(l) +
      (l.run ? '<span class="text-[10px] px-1.5 py-0.5 rounded bg-sky-100 text-sky-800" title="' + esc(T.pStep.replace('%s', l.run.split('/')[0]).replace('%s', l.run.split('/')[1].replace('!', ''))) + '">🔁 ' + esc(l.run.replace('!', '')) + (l.run.indexOf('!') > -1 ? ' ⚡' : '') + '</span>' : '') + '</div>' +
      '<div class="text-xs text-ink-500 truncate">' + esc([l.title, l.company].filter(Boolean).join(' · ') || '—') + '</div></div>' +
      '<div class="hidden md:block text-xs text-ink-500 w-44 truncate" dir="ltr">' + esc(l.phone || (l.pending ? '⏳' : '')) + (l.email ? '<div class="truncate">' + esc(l.email) + esBadge(l) + '</div>' : '') + '</div>' +
      '<div class="hidden lg:block text-xs text-ink-500 w-28 truncate">' + esc(D.sources[l.source] || '') + '</div>' +
      '<div class="hidden sm:block text-xs text-ink-500 w-28 truncate">' + esc(ownerName(l)) + '</div>' +
      (l.value ? '<div class="text-sm font-semibold text-ink-900 w-20 text-end">' + esc(Number(l.value).toLocaleString()) + '</div>' : '<div class="w-20"></div>') + '</div>';
  }
  function card(l) {
    return '<div class="ld-card rounded-xl border border-ink-200 bg-white p-3 cursor-pointer hover:border-brand-300 hover:shadow-sm" draggable="' + (l.edit ? 'true' : 'false') + '" data-id="' + l.id + '" tabindex="0" role="button">' +
      '<div class="flex items-center gap-2">' + avatar(l, 'w-7 h-7') + '<div class="min-w-0"><div class="text-sm font-medium text-ink-900 truncate">' + esc(l.name) + ' ' + scoreBadge(l) + '</div><div class="text-[11px] text-ink-500 truncate">' + esc(l.company || l.title || '—') + '</div></div></div>' +
      '<div class="flex flex-wrap items-center gap-1.5 mt-2">' + nextBadge(l) + (l.value ? '<span class="text-xs font-semibold text-ink-900 ms-auto">' + esc(Number(l.value).toLocaleString()) + '</span>' : '') + '</div></div>';
  }
  function scoreBadge(l) { if (!l.score) return ''; var c = l.score >= 60 ? 'bg-lime-500 text-white' : l.score >= 35 ? 'bg-amber-400 text-ink-900' : 'bg-ink-200 text-ink-600'; return '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full ' + c + '" title="' + esc(T.score + ': ' + (l.why || []).join('، ')) + '">' + l.score + '</span>'; }
  function draw() {
    var shown = leads.filter(match);
    if (f.sort === 'score') shown.sort(function (a, b) { return b.score - a.score; });
    else if (f.sort === 'new') shown.sort(function (a, b) { return b.id - a.id; });
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
  document.getElementById('ld-sort').addEventListener('change', function () { f.sort = this.value; try { localStorage.setItem('ld-sort', f.sort); } catch (e) {} draw(); });
  try { f.sort = localStorage.getItem('ld-sort') || ''; document.getElementById('ld-sort').value = f.sort; } catch (e) {}
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
  document.addEventListener('keydown', function (e) { if (e.key !== 'Escape') return; [dr, document.getElementById('ld-add'), document.getElementById('ld-imp'), document.getElementById('ld-tpl-dlg'), document.getElementById('ld-sug'), document.getElementById('ld-fdlg'), document.getElementById('ld-pdlg')].filter(Boolean).forEach(function (m) { if (!m.classList.contains('hidden')) closeModal(m); }); });
  function quick(l) {
    var b = [];
    if (l.phone) b.push('<a class="btn-ghost text-sm" href="tel:' + esc(l.phone) + '">📞 ' + T.call + '</a>');
    if (l.wa) b.push('<a class="btn-ghost text-sm" target="_blank" rel="noopener" href="https://wa.me/' + esc(l.wa) + '">💬 WhatsApp</a>');
    if (l.email) b.push('<a class="btn-ghost text-sm" href="mailto:' + esc(l.email) + '">✉️ ' + T.email + '</a>');
    if (l.src_url) b.push('<a class="btn-ghost text-sm" data-nolog href="' + esc(l.src_url) + '">🗨️ ' + esc(T.chat) + '</a>');
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
    drawTpl(l); loadActs(l); apReset(l); drawPlan(l, undefined);
    openModal(dr);
    form.elements.name.focus();
  }
  [list, board].forEach(function (el) {
    el.addEventListener('click', function (e) { if (e.target.closest('.ld-sel')) return; var r = e.target.closest('[data-id]'); if (r) open(+r.dataset.id); });
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

  /* ---------- رسائل جاهزة + سجل التواصل ---------- */
  var tplBox = document.getElementById('ld-tpl'), acts = document.getElementById('ld-acts');
  function fill(b, l) { return b.replace(/\{name\}/g, (l.name || '').split(/\s+/)[0]).replace(/\{company\}/g, l.company || '').replace(/\{me\}/g, D.meName || '').replace(/\{book\}/g, D.bookUrl || ''); }
  function drawTpl(l) {
    tplBox.classList.toggle('hidden', !l.wa || !l.edit);
    document.getElementById('ld-tpl-list').innerHTML = (D.tpl || []).map(function (t, i) { return '<button type="button" class="chip text-xs" data-t="' + i + '" title="' + esc(fill(t.b, l)) + '">' + esc(t.n) + '</button>'; }).join('');
  }
  function when(s) { var o = { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }; if (D.tz) o.timeZone = D.tz; try { return srvDate(s).toLocaleString(document.documentElement.lang || undefined, o); } catch (e) { delete o.timeZone; return srvDate(s).toLocaleString(undefined, o); } }
  function drawActs(a) {
    acts.innerHTML = a.length ? a.map(function (x) { var k = D.kinds[x.kind] || ['•', x.kind]; return '<li class="flex gap-2 text-xs"><span class="w-6 h-6 rounded-full bg-ink-100 grid place-items-center shrink-0">' + k[0] + '</span><div class="min-w-0"><div class="text-ink-500">' + esc(k[1]) + ' · ' + esc(x.who || '—') + ' · ' + esc(when(x.created_at)) + '</div>' + (x.body ? '<div class="text-ink-800 whitespace-pre-line break-words" dir="auto">' + esc(x.body) + '</div>' : '') + '</div></li>'; }).join('')
      : '<li class="text-xs text-ink-400">' + esc(T.noacts) + '</li>';
  }
  var intel = document.getElementById('ld-intel');
  function drawIntel(l, it) {
    if (!it) { intel.innerHTML = ''; return; }
    intel.innerHTML = '<div class="flex items-center gap-2">' + scoreBadge(l) + '<span class="text-ink-700">' + esc(it.summary) + '</span></div>' +
      (l.why && l.why.length ? '<div class="text-[11px] text-ink-500">' + esc(T.score) + ': ' + esc(l.why.join('، ')) + '</div>' : '') +
      (it.best ? '<div class="text-ink-600">🕐 ' + esc(T.best) + ': <b>' + esc(it.best) + '</b></div>' : '') +
      (l.edit ? '<div class="pt-1"><div class="text-ink-500 mb-1">✍️ ' + esc(T.firstMsg) + '</div><textarea id="ld-first" rows="3" class="input text-xs" dir="auto">' + esc(it.first) + '</textarea><div class="flex flex-wrap gap-1.5 mt-1.5">' +
        (l.wa ? '<button type="button" class="btn-ghost !py-1 !text-xs" data-fm="wa">💬 ' + esc(T.useWa) + '</button>' : '') + (l.email ? '<button type="button" class="btn-ghost !py-1 !text-xs" data-fm="email">✉️ ' + esc(T.useMail) + '</button>' : '') +
        '<button type="button" class="btn-ghost !py-1 !text-xs" data-fm="copy">⧉ ' + esc(T.copy) + '</button></div></div>' : '');
  }
  intel.addEventListener('click', function (e) {
    var b = e.target.closest('[data-fm]'); if (!b || !cur) return; var t = document.getElementById('ld-first').value;
    if (b.dataset.fm === 'wa') { window.open('https://wa.me/' + cur.wa + '?text=' + encodeURIComponent(t), '_blank', 'noopener'); logAct('wa', t); }
    else if (b.dataset.fm === 'email') { location.href = 'mailto:' + cur.email + '?body=' + encodeURIComponent(t); logAct('email', t); }
    else { try { navigator.clipboard.writeText(t); } catch (x) {} b.textContent = T.copied; }
  });
  var mailBox = document.getElementById('ld-mail'), mailF = document.getElementById('ld-mail-f');
  function drawMails(l, ms) {
    mailBox.classList.toggle('hidden', !l.email || !l.edit); mailF.classList.add('hidden');
    document.getElementById('ld-mails').innerHTML = (ms || []).map(function (m) {
      return '<div class="flex items-center gap-2 text-[11px]"><span class="truncate flex-1 text-ink-700" dir="auto">' + esc(m.subject) + '</span><span class="text-ink-400">' + esc(when(m.created_at)) + '</span>' +
        (+m.opens ? '<span class="rounded bg-lime-100 text-lime-800 px-1">👁 ' + esc(T.mOpen.replace('%s', m.opens)) + '</span>' : '<span class="rounded bg-ink-100 text-ink-500 px-1">' + esc(T.mNotOpen) + '</span>') +
        (+m.clicks ? '<span class="rounded bg-violet-100 text-violet-800 px-1">🔗 ' + esc(T.mClick) + '</span>' : '') + '</div>';
    }).join('');
  }
  document.getElementById('ld-mail-tg').addEventListener('click', function () { mailF.classList.toggle('hidden'); var fm = document.getElementById('ld-first'); var b = document.getElementById('ld-mail-b'); if (!b.value && fm) b.value = fm.value; });
  document.getElementById('ld-mail-go').addEventListener('click', function () {
    if (!cur) return; var l = cur, bt = this; bt.disabled = true;
    post({ action: 'send_email', id: l.id, subject: document.getElementById('ld-mail-s').value, body: document.getElementById('ld-mail-b').value }).then(function (r) {
      bt.disabled = false; if (!r.ok) return alert(r.error || T.err);
      Object.assign(l, r.lead); drawActs(r.acts); drawMails(l, r.mails); document.getElementById('ld-mail-s').value = ''; document.getElementById('ld-mail-b').value = ''; draw();
    }).catch(function () { bt.disabled = false; alert(T.err); });
  });
  var dl = document.getElementById('ld-deal-list'), dAdd = document.getElementById('ld-deal-add');
  function dealForm(d) {
    d = d || { id: 0, title: '', value: '', status: 'open', close_at: '' };
    return '<div class="rounded-lg border border-brand-200 bg-brand-50/40 p-2 space-y-1.5" data-dform="' + d.id + '"><input class="input !py-1 text-xs" data-k="title" maxlength="190" placeholder="' + esc(T.dTitle) + '" value="' + esc(d.title) + '">' +
      '<div class="grid grid-cols-3 gap-1.5"><input class="input !py-1 text-xs" data-k="value" inputmode="decimal" dir="ltr" placeholder="' + esc(T.dValue) + '" value="' + esc(d.value || '') + '"><select class="input !py-1 text-xs" data-k="status">' + Object.keys(T.dStatus).map(function (k) { return '<option value="' + k + '"' + (k === d.status ? ' selected' : '') + '>' + esc(T.dStatus[k]) + '</option>'; }).join('') + '</select>' +
      '<input type="date" class="input !py-1 text-xs" data-k="close_at" title="' + esc(T.dClose) + '" value="' + esc(d.close_at || '') + '"></div><div class="flex gap-1.5"><button type="button" class="btn-primary !py-1 !text-xs flex-1" data-dsave>' + esc(T.saved.replace(' ✓', '') || 'OK') + '</button>' + (d.id ? '<button type="button" class="btn-ghost !py-1 !text-xs text-red-600" data-ddel>✕</button>' : '') + '</div></div>';
  }
  function drawDeals(l, ds) {
    dAdd.classList.toggle('hidden', !l.edit); l._deals = ds || [];
    var dc = { open: 'bg-sky-100 text-sky-800', won: 'bg-lime-100 text-lime-800', lost: 'bg-ink-100 text-ink-500' };
    dl.innerHTML = l._deals.length ? l._deals.map(function (d) { return '<div class="flex items-center gap-2 text-xs ' + (l.edit ? 'cursor-pointer hover:bg-ink-50' : '') + ' rounded-lg px-1.5 py-1" data-deal="' + d.id + '"><span class="flex-1 truncate text-ink-800">' + esc(d.title) + '</span><b class="text-ink-900">' + Number(d.value).toLocaleString() + '</b><span class="px-1.5 rounded ' + dc[d.status] + '">' + esc(T.dStatus[d.status]) + '</span></div>'; }).join('')
      : '<p class="text-[11px] text-ink-400">' + esc(T.dNone) + '</p>';
  }
  dAdd.addEventListener('click', function () { if (!dl.querySelector('[data-dform="0"]')) dl.insertAdjacentHTML('afterbegin', dealForm()); });
  dl.addEventListener('click', function (e) {
    if (!cur) return; var l = cur;
    var row = e.target.closest('[data-deal]'); if (row && l.edit) { var d = (l._deals || []).filter(function (x) { return x.id === +row.dataset.deal; })[0]; row.outerHTML = dealForm(d); return; }
    var f0 = e.target.closest('[data-dform]'); if (!f0) return;
    var del = e.target.closest('[data-ddel]'), sv = e.target.closest('[data-dsave]'); if (!del && !sv) return;
    if (del && !confirm(T.dDel)) return;
    var data = { action: del ? 'deal_del' : 'deal_save', id: l.id, deal: f0.dataset.dform }; f0.querySelectorAll('[data-k]').forEach(function (i) { data[i.dataset.k] = i.value; });
    post(data).then(function (r) { if (!r.ok) return alert(r.error || T.err); Object.assign(l, r.lead); if (cur === l) { drawDeals(l, r.deals); form.elements.value.value = l.value || ''; form.querySelectorAll('[name=stage]').forEach(function (x) { x.checked = x.value === l.stage; }); } draw(); });
  });
  var vl = document.getElementById('ld-visit-list');
  function drawVisits(l, vs) {
    document.getElementById('ld-tl').classList.toggle('hidden', !l.edit); document.getElementById('ld-tl-box').classList.add('hidden');
    vl.innerHTML = (vs || []).length ? vs.map(function (v) { var u = v.url.replace(/^https?:\/\//, ''); return '<div class="flex items-center gap-2 text-[11px]"><span class="truncate flex-1 ' + (+v.hot ? 'text-red-700 font-semibold' : 'text-ink-700') + '" dir="ltr" title="' + esc(v.url) + '">' + (+v.hot ? '🔥 ' : '') + esc(u) + '</span><span class="text-ink-400 shrink-0">' + esc(when(v.created_at)) + '</span></div>'; }).join('')
      : '<p class="text-[11px] text-ink-400">' + esc(T.noVisits) + '</p>';
  }
  document.getElementById('ld-tl').addEventListener('click', function () { document.getElementById('ld-tl-box').classList.toggle('hidden'); });
  document.getElementById('ld-tl-go').addEventListener('click', function () {
    if (!cur) return;
    post({ action: 'track_link', id: cur.id, url: document.getElementById('ld-tl-u').value }).then(function (r) {
      if (!r.ok) return alert(r.error || T.err); try { navigator.clipboard.writeText(r.link); } catch (e) {} prompt(T.linkCopied, r.link);
    });
  });
  function loadActs(l) { acts.innerHTML = ''; drawIntel(l, null); drawMails(l, []); drawDeals(l, []); drawVisits(l, []); post({ action: 'acts', id: l.id }).then(function (r) { if (r.ok && cur === l) { drawActs(r.acts); drawPlan(l, r.run); drawIntel(l, r.intel); drawMails(l, r.mails); drawDeals(l, r.deals); drawVisits(l, r.visits); } }); }
  function logAct(kind, body, outcome) {
    if (!cur) return Promise.resolve();
    var l = cur;
    return post({ action: 'log', id: l.id, kind: kind, body: body || '', outcome: outcome || '' }).then(function (r) {
      if (!r.ok) return alert(r.error || T.err);
      Object.assign(l, r.lead); if (cur === l) { drawActs(r.acts); form.querySelectorAll('[name=stage]').forEach(function (x) { x.checked = x.value === l.stage; }); }
      draw();
    });
  }
  document.getElementById('ld-tpl-list').addEventListener('click', function (e) {
    var b = e.target.closest('[data-t]'); if (!b || !cur) return;
    var txt = fill(D.tpl[+b.dataset.t].b, cur);
    window.open('https://wa.me/' + cur.wa + '?text=' + encodeURIComponent(txt), '_blank', 'noopener');
    logAct('wa', txt);
  });
  // أي ضغطة اتصال/واتساب/إيميل من الدرج بتتسجّل
  document.getElementById('ld-quick').addEventListener('click', function (e) {
    var a = e.target.closest('a'); if (!a || !cur || !cur.edit || a.hasAttribute('data-nolog')) return;
    var h = a.getAttribute('href') || '';
    if (h.indexOf('tel:') === 0) { ocBox.classList.remove('hidden'); ocBox.classList.add('flex'); return; }
    logAct(h.indexOf('mailto:') === 0 ? 'email' : h.indexOf('wa.me') > -1 ? 'wa' : 'note', '');
  });
  var ocBox = document.getElementById('ld-oc');
  document.getElementById('ld-log-kinds').addEventListener('click', function (e) {
    var b = e.target.closest('[data-k]'); if (!b) return;
    if (b.dataset.k === 'call') { ocBox.classList.remove('hidden'); ocBox.classList.add('flex'); return; } // المكالمة محتاجة نتيجتها
    var inp = document.getElementById('ld-log-body');
    logAct(b.dataset.k, inp.value.trim()).then(function () { inp.value = ''; });
  });
  ocBox.addEventListener('click', function (e) {
    var b = e.target.closest('[data-oc]'); if (!b) return; var inp = document.getElementById('ld-log-body');
    logAct('call', inp.value.trim(), b.dataset.oc).then(function () { inp.value = ''; ocBox.classList.add('hidden'); ocBox.classList.remove('flex'); if (cur) { form.elements.next_at.value = cur.next_at || ''; form.elements.next_note.value = cur.next_note || ''; } });
  });
  var coldB = document.getElementById('ld-cold');
  if (coldB) coldB.addEventListener('click', function () { f.cold = !f.cold; coldB.classList.toggle('ring-2', f.cold); coldB.classList.toggle('ring-sky-400', f.cold); draw(); });

  /* ---------- القوالب ---------- */
  var td = document.getElementById('ld-tpl-dlg'), rowsEl = document.getElementById('ld-tpl-rows');
  function tplRow(t) { return '<div class="rounded-xl border border-ink-200 p-2 space-y-1.5" data-row><div class="flex gap-2"><input class="input text-sm" data-n maxlength="40" placeholder="' + esc(T.tplN) + '" value="' + esc(t.n) + '"><button type="button" class="btn-ghost text-red-600 !px-2" data-rm aria-label="' + esc(T.rm) + '">✕</button></div><textarea class="input text-sm" rows="3" data-b maxlength="1000" dir="auto" placeholder="' + esc(T.tplB) + '">' + esc(t.b) + '</textarea></div>'; }
  var te = document.getElementById('ld-tpl-edit');
  if (te) {
    [td].forEach(function (m) { m.querySelectorAll('[data-close]').forEach(function (x) { x.addEventListener('click', function () { m.classList.add('hidden'); m.setAttribute('aria-hidden', 'true'); }); }); });
    te.addEventListener('click', function () { rowsEl.innerHTML = (D.tpl || []).map(tplRow).join(''); td.classList.remove('hidden'); td.setAttribute('aria-hidden', 'false'); });
    document.getElementById('ld-tpl-add').addEventListener('click', function () { rowsEl.insertAdjacentHTML('beforeend', tplRow({ n: '', b: '' })); });
    rowsEl.addEventListener('click', function (e) { var r = e.target.closest('[data-rm]'); if (r) r.closest('[data-row]').remove(); });
    document.getElementById('ld-tpl-save').addEventListener('click', function () {
      var t = Array.prototype.map.call(rowsEl.querySelectorAll('[data-row]'), function (r) { return { n: r.querySelector('[data-n]').value, b: r.querySelector('[data-b]').value }; });
      post({ action: 'tpl_save', tpl: JSON.stringify(t) }).then(function (r) { if (!r.ok) return alert(r.error || T.err); D.tpl = r.tpl; if (cur) drawTpl(cur); td.classList.add('hidden'); });
    });
  }

  /* ---------- خطط المتابعة ---------- */
  var pb = document.getElementById('ld-plan-body');
  function drawPlan(l, run) {
    if (!pb) return;
    if (run === undefined) { pb.innerHTML = '<span class="text-ink-400">…</span>'; return; }
    if (!l.edit) { pb.innerHTML = run ? esc(run.plan) + ' · ' + esc(T.pStep.replace('%s', run.step + 1).replace('%s', run.n)) : esc(T.pNone); return; }
    if (!run) {
      pb.innerHTML = D.plans.length ? '<div class="flex gap-2"><select id="ld-plan-sel" class="input !py-1 text-xs flex-1"><option value="">' + esc(T.pPick) + '</option>' + D.plans.map(function (p) { return '<option value="' + p.id + '">' + esc(p.name) + ' (' + p.steps.length + ')</option>'; }).join('') + '</select><button type="button" class="btn-primary !py-1 !text-xs" data-pa="plan_start">' + esc(T.pStart) + '</button></div>'
        : '<span class="text-ink-500">' + esc(T.pNoPlans) + '</span>';
      return;
    }
    var ch = D.chs[run.ch] || ['✅', ''], due = run.state === 'due';
    var doBtn = '';
    if (due) {
      if (run.ch === 'wa' && l.wa) doBtn = '<a class="btn-primary !py-1 !text-xs" data-pa="plan_do" target="_blank" rel="noopener" href="https://wa.me/' + esc(l.wa) + '?text=' + encodeURIComponent(run.text) + '">💬 ' + esc(T.pDo) + '</a>';
      else if (run.ch === 'call' && l.phone) doBtn = '<a class="btn-primary !py-1 !text-xs" data-pa="plan_do" href="tel:' + esc(l.phone) + '">📞 ' + esc(T.pDo) + '</a>';
      else if (run.ch === 'email' && l.email) doBtn = '<a class="btn-primary !py-1 !text-xs" data-pa="plan_do" href="mailto:' + esc(l.email) + '?subject=' + encodeURIComponent(run.subject || '') + '&body=' + encodeURIComponent(run.text) + '">✉️ ' + esc(T.pDo) + '</a>';
      else doBtn = '<button type="button" class="btn-primary !py-1 !text-xs" data-pa="plan_do">✓ ' + esc(T.pDo) + '</button>';
    }
    pb.innerHTML = '<div class="flex items-center gap-2"><b class="text-ink-800">' + esc(run.plan) + '</b><span class="text-ink-500">' + esc(T.pStep.replace('%s', run.step + 1).replace('%s', run.n)) + '</span>' +
      '<span class="ms-auto ' + (due ? 'text-amber-700 font-semibold' : 'text-ink-500') + '">' + (due ? '⚡ ' + esc(T.pDue) : esc(T.pWait.replace('%s', when(run.due)))) + '</span></div>' +
      '<div class="mt-1.5 rounded-lg bg-white border border-ink-200 p-2 text-ink-700 whitespace-pre-line" dir="auto">' + ch[0] + ' ' + esc(run.text) + '</div>' +
      (run.err ? '<div class="text-[11px] text-amber-700 mt-1">' + esc(run.err) + '</div>' : '') +
      '<div class="flex flex-wrap gap-1.5 mt-2">' + doBtn + '<button type="button" class="btn-ghost !py-1 !text-xs" data-pa="plan_skip">' + esc(T.pSkip) + '</button><button type="button" class="btn-ghost !py-1 !text-xs text-red-600 ms-auto" data-pa="plan_stop">' + esc(T.pStop) + '</button></div>';
  }
  if (pb) pb.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pa]'); if (!b || !cur) return; var l = cur, a = b.dataset.pa, data = { action: a, id: l.id };
    if (a === 'plan_start') { data.plan = (document.getElementById('ld-plan-sel') || {}).value; if (!data.plan) return; }
    if (b.tagName !== 'A') e.preventDefault();
    post(data).then(function (r) {
      if (!r.ok) return alert(r.error || T.err);
      Object.assign(l, r.lead); l.run = r.run ? (r.run.step + 1) + '/' + r.run.n + (r.run.state === 'due' ? '!' : '') : '';
      if (cur === l) { drawActs(r.acts); drawPlan(l, r.run); form.querySelectorAll('[name=stage]').forEach(function (x) { x.checked = x.value === l.stage; }); form.elements.next_at.value = l.next_at || ''; form.elements.next_note.value = l.next_note || ''; }
      draw();
    });
  });
  var bp = document.getElementById('ld-b-plan'); if (bp) bp.addEventListener('change', function () { if (this.value) bulk({ op: 'plan', plan: this.value }); });
  var pdl = document.getElementById('ld-pdlg');
  if (pdl) {
    var pl = document.getElementById('ld-plans');
    var chOpts = function (v) { return Object.keys(D.chs).map(function (k) { return '<option value="' + k + '"' + (k === v ? ' selected' : '') + '>' + D.chs[k][0] + ' ' + esc(D.chs[k][1]) + '</option>'; }).join(''); };
    var stepRow = function (s) { return '<div class="grid grid-cols-[70px,1fr,auto] gap-2 items-start" data-step><label class="text-[10px] text-ink-500">' + esc(T.pDay) + '<input type="number" min="0" max="365" class="input !py-1 text-xs" data-k="day" value="' + (+s.day || 0) + '"></label>' +
      '<div class="space-y-1"><select class="input !py-1 text-xs" data-k="ch">' + chOpts(s.ch) + '</select><input class="input !py-1 text-xs' + (s.ch === 'email' ? '' : ' hidden') + '" data-k="subject" placeholder="' + esc(T.pSubj) + '" value="' + esc(s.subject || '') + '"><textarea class="input text-xs" rows="2" data-k="text" dir="auto" placeholder="' + esc(T.pText) + '">' + esc(s.text || '') + '</textarea></div>' +
      '<button type="button" class="text-ink-400 hover:text-red-600 pt-5" data-rmstep>✕</button></div>'; };
    var planBox = function (p) { return '<div class="rounded-xl border border-ink-200 p-3 space-y-2" data-plan="' + (p.id || 0) + '"><div class="flex gap-2"><input class="input text-sm font-medium" data-k="name" placeholder="' + esc(T.pName) + '" value="' + esc(p.name || '') + '"><button type="button" class="text-xs text-red-600" data-rmplan>' + esc(T.pDel) + '</button></div>' +
      '<div class="space-y-2" data-steps>' + (p.steps || []).map(stepRow).join('') + '</div><button type="button" class="link text-xs" data-addstep>' + esc(T.pAddStep) + '</button></div>'; };
    var sample = function () { return { name: T.pSample, steps: [{ day: 0, ch: 'wa', text: T.pS1 }, { day: 1, ch: 'call', text: T.pS2 }, { day: 3, ch: 'email', subject: T.pSample, text: T.pS3 }, { day: 7, ch: 'wa', text: T.pS4 }] }; };
    pdl.querySelectorAll('[data-close]').forEach(function (x) { x.addEventListener('click', function () { closeModal(pdl); }); });
    document.getElementById('ld-plans-btn').addEventListener('click', function () { pl.innerHTML = (D.plans.length ? D.plans : [sample()]).map(planBox).join(''); openModal(pdl); });
    document.getElementById('ld-plan-add').addEventListener('click', function () { pl.insertAdjacentHTML('beforeend', planBox({ name: '', steps: [{ day: 0, ch: 'wa', text: '' }] })); });
    pl.addEventListener('change', function (e) { if (e.target.dataset.k === 'ch') e.target.parentNode.querySelector('[data-k=subject]').classList.toggle('hidden', e.target.value !== 'email'); });
    pl.addEventListener('click', function (e) {
      if (e.target.closest('[data-addstep]')) { var st = e.target.closest('[data-plan]').querySelector('[data-steps]'); var last = st.querySelectorAll('[data-step]'); var d = last.length ? +last[last.length - 1].querySelector('[data-k=day]').value + 2 : 0; st.insertAdjacentHTML('beforeend', stepRow({ day: d, ch: 'wa', text: '' })); }
      if (e.target.closest('[data-rmstep]')) e.target.closest('[data-step]').remove();
      if (e.target.closest('[data-rmplan]') && confirm(T.pDelQ)) e.target.closest('[data-plan]').remove();
    });
    document.getElementById('ld-plans-save').addEventListener('click', function () {
      var plans = Array.prototype.map.call(pl.querySelectorAll('[data-plan]'), function (b) {
        return { id: +b.dataset.plan, name: b.querySelector('[data-k=name]').value, steps: Array.prototype.map.call(b.querySelectorAll('[data-step]'), function (s) { var o = {}; s.querySelectorAll('[data-k]').forEach(function (i) { o[i.dataset.k] = i.value; }); return o; }) };
      });
      post({ action: 'plans_save', plans: JSON.stringify(plans) }).then(function (r) { if (!r.ok) return alert(r.error || T.err); D.plans = r.plans; closeModal(pdl); location.reload(); });
    });
  }

  /* ---------- فورم الموقع ---------- */
  var fdl = document.getElementById('ld-fdlg');
  if (fdl) {
    var ff = document.getElementById('ld-fform');
    function codes(u) {
      ff.querySelector('[data-code=iframe]').value = '<iframe src="' + u + '" style="width:100%;max-width:460px;height:430px;border:0" loading="lazy" title="form"></iframe>';
      ff.querySelector('[data-code=link]').value = u; ff.querySelector('[data-code=hook]').value = u + '&format=json'; ff.querySelector('[data-code=open]').href = u;
    }
    codes(window.LDF.url);
    var tf = document.getElementById('ld-tform');
    tf.querySelector('[data-scode]').addEventListener('focus', function () { this.select(); });
    tf.addEventListener('submit', function (e) { e.preventDefault(); var fd = new FormData(tf); fd.append('action', 'track_save'); post(fd).then(function (r) { var m = document.getElementById('ld-tmsg'); m.className = 'text-xs self-center ' + (r.ok ? 'text-lime-700' : 'text-red-600'); m.textContent = r.ok ? T.saved : (r.error || T.err); }); });
    var bf = document.getElementById('ld-bform'); bf.querySelector('[data-bcode]').value = window.LDF.book; bf.querySelector('[data-bopen]').href = window.LDF.book;
    bf.addEventListener('submit', function (e) {
      e.preventDefault(); var fd = new FormData(bf); fd.append('action', 'book_save');
      post(fd).then(function (r) { var m = document.getElementById('ld-bmsg'); if (!r.ok) { m.className = 'text-xs text-red-600'; m.textContent = r.error || T.err; return; } D.bookUrl = bf.elements.on.checked ? r.url : ''; m.className = 'text-xs text-lime-700 self-center'; m.textContent = T.saved; });
    });
    fdl.querySelectorAll('[data-close]').forEach(function (x) { x.addEventListener('click', function () { closeModal(fdl); }); });
    ff.querySelectorAll('[data-code]').forEach(function (x) { if (x.tagName !== 'A') x.addEventListener('focus', function () { x.select(); }); });
    document.getElementById('ld-form-btn').addEventListener('click', function () { openModal(fdl); });
    ff.addEventListener('submit', function (e) {
      e.preventDefault(); var fd = new FormData(ff); fd.append('action', 'form_save');
      post(fd).then(function (r) { var m = document.getElementById('ld-fmsg'); if (!r.ok) { m.className = 'text-xs text-red-600'; m.textContent = r.error || T.err; return; } codes(r.url); ff.elements.regen.checked = false; m.className = 'text-xs text-lime-700'; m.textContent = T.saved; });
    });
  }

  /* ---------- الاستيراد ---------- */
  var im = document.getElementById('ld-imp'), itx = document.getElementById('ld-imp-txt'), imap = document.getElementById('ld-imp-map'), igo = document.getElementById('ld-imp-go'), imsg = document.getElementById('ld-imp-msg'), parsed = null;
  im.querySelectorAll('[data-close]').forEach(function (x) { x.addEventListener('click', function () { closeModal(im); }); });
  document.getElementById('ld-imp-btn').addEventListener('click', function () { imsg.textContent = ''; openModal(im); itx.focus(); });
  function parse(text) {
    text = text.replace(/^\uFEFF/, '').replace(/\r\n?/g, '\n').trim(); if (!text) return null;
    var first = text.split('\n')[0], sep = first.indexOf('\t') > -1 ? '\t' : (first.split(';').length > first.split(',').length ? ';' : ',');
    var rows = [], row = [], cell = '', q = false;
    for (var i = 0; i < text.length; i++) {
      var c = text[i];
      if (q) { if (c === '"') { if (text[i + 1] === '"') { cell += '"'; i++; } else q = false; } else cell += c; continue; }
      if (c === '"' && cell === '') q = true; else if (c === sep) { row.push(cell); cell = ''; } else if (c === '\n') { row.push(cell); rows.push(row); row = []; cell = ''; } else cell += c;
    }
    row.push(cell); rows.push(row);
    rows = rows.filter(function (r) { return r.some(function (x) { return x.trim(); }); });
    return rows.length > 1 ? { head: rows[0].map(function (h) { return h.trim(); }), body: rows.slice(1) } : null;
  }
  var guess = { name: /^(name|full ?name|الاسم|اسم|الإسم|العميل|contact)/i, phone: /(phone|mobile|tel|whats|رقم|موبايل|تليفون|هاتف|جوال|واتس)/i, email: /(mail|إيميل|ايميل|البريد)/i,
    company: /(company|organi[sz]ation|الشركة|شركة|المؤسسة)/i, title: /(title|job|position|role|المسمى|الوظيفة|المنصب)/i, country: /(country|الدولة|البلد)/i, tags: /(tag|تاج|تصنيف)/i, note: /(note|ملاحظ|comment)/i };
  function drawMap() {
    parsed = parse(itx.value); imap.classList.toggle('hidden', !parsed); igo.disabled = !parsed;
    if (!parsed) { imsg.textContent = ''; return; }
    var used = {};
    imap.innerHTML = '<div class="grid sm:grid-cols-2 gap-2">' + parsed.head.map(function (h, i) {
      var g = ''; Object.keys(guess).some(function (k) { if (!used[k] && guess[k].test(h)) { g = k; used[k] = 1; return true; } });
      return '<label class="flex items-center gap-2 text-xs"><span class="w-28 truncate text-ink-600" dir="auto" title="' + esc(h) + '">' + esc(h || '#' + (i + 1)) + '</span><select class="input !py-1 text-xs" data-col="' + i + '"><option value="">' + esc(T.skipCol) + '</option>' +
        Object.keys(T.f).map(function (k) { return '<option value="' + k + '"' + (k === g ? ' selected' : '') + '>' + esc(T.f[k]) + '</option>'; }).join('') + '</select></label>';
    }).join('') + '</div>';
    imsg.className = 'text-xs text-ink-500'; imsg.textContent = T.impRows.replace('%s', parsed.body.length);
  }
  itx.addEventListener('input', drawMap);
  document.getElementById('ld-imp-file').addEventListener('change', function () { var fl = this.files[0]; if (!fl) return; var rd = new FileReader(); rd.onload = function () { itx.value = rd.result; drawMap(); }; rd.readAsText(fl); });
  igo.addEventListener('click', function () {
    if (!parsed) return;
    var map = {}; imap.querySelectorAll('[data-col]').forEach(function (s) { if (s.value) map[s.value] = +s.dataset.col; });
    if (map.phone == null && map.email == null) { imsg.className = 'text-xs text-red-600'; imsg.textContent = T.impNeed; return; }
    var rows = parsed.body.map(function (r) { var o = {}; Object.keys(map).forEach(function (k) { o[k] = (r[map[k]] || '').trim(); }); return o; });
    igo.disabled = true;
    post({ action: 'import', rows: JSON.stringify(rows) }).then(function (r) {
      igo.disabled = false;
      if (!r.ok) { imsg.className = 'text-xs text-red-600'; imsg.textContent = r.error || T.err; return; }
      imsg.className = 'text-xs text-lime-700'; imsg.textContent = T.impDone.replace('%s', r.new).replace('%s', r.merged).replace('%s', r.skip);
      setTimeout(function () { location.reload(); }, 1600);
    }).catch(function () { igo.disabled = false; imsg.textContent = T.err; });
  });

  /* ---------- Apollo: كمّل البيانات + Sequence ---------- */
  var apBox = document.getElementById('ld-apollo'), apMsg = document.getElementById('ld-ap-msg'), seqData = null;
  function apSay(t, ok) { if (apMsg) { apMsg.className = 'text-[11px] ' + (ok ? 'text-lime-700' : 'text-red-600'); apMsg.textContent = t; } }
  function apReset(l) { if (!apBox) return; apBox.classList.toggle('hidden', !l.edit); apSay(''); document.getElementById('ld-seq-box').classList.add('hidden'); }
  function refill(l) { ['name', 'company', 'title', 'phone', 'email'].forEach(function (k) { form.elements[k].value = l[k] == null ? '' : l[k]; }); document.getElementById('ld-quick').innerHTML = quick(l); draw(); }
  var enr = document.getElementById('ld-enrich');
  if (enr) enr.addEventListener('click', function () {
    if (!cur) return; var l = cur; enr.disabled = true; apSay(T.loading, true);
    post({ action: 'enrich', id: l.id }).then(function (r) {
      enr.disabled = false; if (!r.ok) return apSay(r.error || T.err);
      Object.assign(l, r.lead); if (cur === l) refill(l);
      var got = r.got.map(function (k) { return T.fl[k] || k; }).join('، ');
      apSay((got ? T.enrGot.replace('%s', got) : T.enrNone) + (r.pending ? ' ' + T.enrPend : ''), !!got || r.pending);
    }).catch(function () { enr.disabled = false; apSay(T.err); });
  });
  var so = document.getElementById('ld-seq-open');
  if (so) {
    so.addEventListener('click', function () {
      if (!cur) return; if (!cur.email) return apSay(T.needEmail);
      var bx = document.getElementById('ld-seq-box');
      function show() {
        if (!seqData.seqs.length) return apSay(T.noSeq); if (!seqData.accounts.length) return apSay(T.noAcc);
        document.getElementById('ld-seq').innerHTML = seqData.seqs.map(function (s) { return '<option value="' + esc(s.id) + '">' + esc(s.name) + (s.active ? '' : ' (off)') + '</option>'; }).join('');
        document.getElementById('ld-seq-acc').innerHTML = seqData.accounts.map(function (a) { return '<option value="' + esc(a.id) + '">' + esc(a.email) + '</option>'; }).join('');
        bx.classList.remove('hidden'); apSay('');
      }
      if (seqData) return show();
      apSay(T.loading, true);
      post({ action: 'seqs', id: cur.id }).then(function (r) { if (!r.ok) return apSay(r.error || T.err); seqData = r; show(); }).catch(function () { apSay(T.err); });
    });
    document.getElementById('ld-seq-go').addEventListener('click', function () {
      if (!cur) return; var l = cur, s = document.getElementById('ld-seq'), b = this; b.disabled = true;
      post({ action: 'seq_add', id: l.id, seq: s.value, seq_name: s.options[s.selectedIndex].text, acc: document.getElementById('ld-seq-acc').value }).then(function (r) {
        b.disabled = false; if (!r.ok) return apSay(r.error || T.err);
        Object.assign(l, r.lead); if (cur === l) { drawActs(r.acts); form.querySelectorAll('[name=stage]').forEach(function (x) { x.checked = x.value === l.stage; }); }
        draw(); apSay(T.seqDone, true); document.getElementById('ld-seq-box').classList.add('hidden');
      }).catch(function () { b.disabled = false; apSay(T.err); });
    });
  }

  /* ---------- عملاء مقترحين ---------- */
  var sg = document.getElementById('ld-sug'), sgl = document.getElementById('ld-sug-list'), sgo = document.getElementById('ld-sug-go'), sgm = document.getElementById('ld-sug-msg'), sgN = document.getElementById('ld-sug-n'), sItems = null;
  sg.querySelectorAll('[data-close]').forEach(function (x) { x.addEventListener('click', function () { closeModal(sg); }); });
  function sugCount() { var n = sgl.querySelectorAll('.sg-c:checked').length; sgo.disabled = !n; sgo.textContent = T.sugGo.replace('%s', n); }
  function sugDraw() {
    if (!sItems.length) { sgl.innerHTML = '<p class="text-sm text-ink-500 text-center py-10">' + esc(T.sugNone) + '</p>'; sugCount(); return; }
    var wl = { ai: ['bg-lime-100 text-lime-800', T.whyAi], contact: ['bg-sky-100 text-sky-800', T.whyContact], price: ['bg-amber-100 text-amber-800', T.whyPrice] };
    sgl.innerHTML = sItems.map(function (it, i) {
      return '<label class="flex gap-3 rounded-xl border border-ink-200 p-3 hover:border-brand-300 cursor-pointer"><input type="checkbox" class="sg-c rounded mt-1" data-i="' + i + '"' + (it.why.indexOf('ai') > -1 || it.why.indexOf('contact') > -1 ? ' checked' : '') + '>' +
        '<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-1.5"><span class="font-medium text-ink-900 text-sm">' + esc(it.name || '—') + '</span><span class="text-[10px] text-ink-500">' + (it.source === 'comments' ? '💬 ' + esc(T.cmt) : '✉️ ' + esc(T.msgK)) + ' · ' + esc(it.where) + '</span>' +
        it.why.map(function (w) { return '<span class="text-[10px] px-1.5 py-0.5 rounded ' + wl[w][0] + '">' + esc(wl[w][1]) + '</span>'; }).join('') + '</div>' +
        '<p class="text-xs text-ink-700 mt-1 line-clamp-2" dir="auto">' + esc(it.summary || it.text) + '</p>' +
        ((it.phone || it.email) ? '<div class="text-[11px] text-ink-500 mt-1" dir="ltr">' + esc([it.phone, it.email].filter(Boolean).join(' · ')) + '</div>' : '') +
        '</div><a href="' + esc(it.url) + '" class="link text-xs self-start" target="_blank" rel="noopener" onclick="event.stopPropagation()">↗</a></label>';
    }).join('');
    sugCount();
  }
  function sugLoad(silent) {
    if (!silent) sgl.innerHTML = '<p class="text-sm text-ink-500 text-center py-10 animate-pulse">' + esc(T.sugLoading) + '</p>';
    return fetch(D.url + (D.url.indexOf('?') > -1 ? '&' : '?') + 'ajax=suggest' + (silent ? '' : '&fresh=1'), { headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(function (r) {
      if (!r.ok) { if (!silent) sgl.innerHTML = '<p class="text-sm text-red-600 text-center py-10">' + esc(r.error || T.err) + '</p>'; return; }
      sItems = r.items; document.getElementById('ld-sug-ai').classList.toggle('hidden', !r.ai);
      sgN.textContent = sItems.length; sgN.classList.toggle('hidden', !sItems.length); sgN.classList.toggle('grid', !!sItems.length);
      if (!silent) sugDraw();
    }).catch(function () { if (!silent) sgl.innerHTML = '<p class="text-sm text-red-600 text-center py-10">' + esc(T.err) + '</p>'; });
  }
  document.getElementById('ld-sug-btn').addEventListener('click', function () { openModal(sg); sgm.textContent = ''; if (sItems) sugDraw(); else sugLoad(); });
  sgl.addEventListener('change', sugCount);
  document.getElementById('ld-sug-all').addEventListener('change', function () { var on = this.checked; sgl.querySelectorAll('.sg-c').forEach(function (c) { c.checked = on; }); sugCount(); });
  document.getElementById('ld-sug-ai').addEventListener('click', function () {
    var b = this; b.disabled = true; sgm.textContent = T.scanning;
    post({ action: 'ai_scan' }).then(function (r) { b.disabled = false; if (!r.ok) { sgm.textContent = r.error || T.err; return; } sgm.textContent = T.scanned.replace('%s', r.n); sugLoad(); }).catch(function () { b.disabled = false; sgm.textContent = T.err; });
  });
  sgo.addEventListener('click', function () {
    var pick = Array.prototype.map.call(sgl.querySelectorAll('.sg-c:checked'), function (c) { return sItems[+c.dataset.i]; });
    if (!pick.length) return; sgo.disabled = true;
    post({ action: 'social_bulk', items: JSON.stringify(pick) }).then(function (r) {
      if (!r.ok) { sgo.disabled = false; sgm.textContent = r.error || T.err; return; }
      sgm.className = 'ms-auto text-lime-700'; sgm.textContent = T.sugDone.replace('%s', r.new);
      setTimeout(function () { location.reload(); }, 1200);
    }).catch(function () { sgo.disabled = false; sgm.textContent = T.err; });
  });
  var au = document.getElementById('ld-auto');
  if (au) au.addEventListener('change', function () {
    au.disabled = true;
    post({ action: 'auto_capture', on: au.checked ? 1 : '' }).then(function (r) {
      au.disabled = false; if (!r.ok) { au.checked = !au.checked; return; }
      sgm.className = 'ms-auto text-lime-700'; sgm.textContent = r.on ? T.autoOn.replace('%s', r.n) : T.autoOff;
      if (r.on && r.n) setTimeout(function () { location.reload(); }, 1500);
    }).catch(function () { au.disabled = false; au.checked = !au.checked; });
  });
  setTimeout(function () { sugLoad(true); }, 400);

  /* ---------- تحديد متعدد + عمليات بالجملة ---------- */
  var bb = document.getElementById('ld-bulk'), tagSel = document.getElementById('ld-tag');
  if (tagSel) tagSel.addEventListener('change', function () { f.tag = this.value; draw(); });
  function selIds() { return Object.keys(sel).filter(function (k) { return sel[k]; }).map(Number); }
  function bbDraw() { var n = selIds().length; bb.classList.toggle('hidden', !n); bb.classList.toggle('flex', !!n); document.getElementById('ld-bn').textContent = n; }
  list.addEventListener('change', function (e) { var c = e.target.closest('.ld-sel'); if (!c) return; sel[+c.dataset.sid] = c.checked; c.closest('.ld-row').classList.toggle('bg-brand-50/60', c.checked); bbDraw(); });
  document.getElementById('ld-all').addEventListener('change', function () { var on = this.checked; leads.filter(match).forEach(function (l) { if (l.edit) sel[l.id] = on; }); draw(); bbDraw(); });
  document.getElementById('ld-b-clear').addEventListener('click', function () { sel = {}; document.getElementById('ld-all').checked = false; draw(); bbDraw(); });
  function bulk(data, after) {
    var fd = new FormData(); fd.append('action', 'bulk'); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); selIds().forEach(function (id) { fd.append('ids[]', id); });
    bb.classList.add('opacity-60', 'pointer-events-none');
    return post(fd).then(function (r) {
      bb.classList.remove('opacity-60', 'pointer-events-none');
      if (!r.ok) return alert(r.error || T.err);
      alert(T.bDone.replace('%s', r.done).replace('%s', r.skip) + (r.error ? '\n' + r.error : ''));
      location.reload();
    }).catch(function () { bb.classList.remove('opacity-60', 'pointer-events-none'); alert(T.err); });
  }
  document.getElementById('ld-b-stage').addEventListener('change', function () { if (this.value) bulk({ op: 'stage', stage: this.value }); });
  var bo = document.getElementById('ld-b-owner'); if (bo) bo.addEventListener('change', function () { if (this.value !== '') bulk({ op: 'owner', owner: this.value }); });
  document.getElementById('ld-b-tag').addEventListener('click', function () { var t = prompt(T.askTag); if (t && t.trim()) bulk({ op: 'tag', tag: t.trim() }); });
  var be = document.getElementById('ld-b-enrich'); if (be) be.addEventListener('click', function () { var n = Math.min(25, selIds().length); if (confirm(T.bConfirm.replace('%s', n))) bulk({ op: 'enrich' }); });
  var bs = document.getElementById('ld-b-seq'); if (bs) bs.addEventListener('click', function () {
    function go() {
      if (!seqData.seqs.length) return alert(T.noSeq); if (!seqData.accounts.length) return alert(T.noAcc);
      var names = seqData.seqs.map(function (s, i) { return (i + 1) + '. ' + s.name; }).join('\n');
      var k = parseInt(prompt(T.pickSeq + '\n' + names, '1'), 10); var s = seqData.seqs[k - 1]; if (!s) return;
      bulk({ op: 'seq', seq: s.id, seq_name: s.name, acc: seqData.accounts[0].id });
    }
    if (seqData) return go();
    post({ action: 'seqs', id: selIds()[0] }).then(function (r) { if (!r.ok) return alert(r.error || T.err); seqData = r; go(); });
  });
  var stl = document.getElementById('ld-stale'); if (stl) stl.addEventListener('click', function () {
    if (!confirm(T.bConfirm.replace('%s', 25))) return; stl.disabled = true;
    post({ action: 'enrich_stale' }).then(function (r) { stl.disabled = false; if (!r.ok) return alert(r.error || T.err); alert(T.bDone.replace('%s', r.done).replace('%s', r.skip) + (r.error ? '\n' + r.error : '')); if (r.done) location.reload(); });
  });

  // قايمة الأدوات بتتقفل لما تختار حاجة أو تدوس بره
  var tools = document.getElementById('ld-tools');
  if (tools) { tools.addEventListener('click', function (e) { if (e.target.closest('button,a') && !e.target.closest('summary')) tools.open = false; }); document.addEventListener('click', function (e) { if (!tools.contains(e.target)) tools.open = false; }); }

  draw();
  var m = location.hash.match(/^#lead-(\d+)$/); if (m) open(+m[1]);
})();
