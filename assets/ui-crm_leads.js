(function () {
  var D = window.LD; if (!D) return;
  var T = D.tr, leads = D.leads;
  var list = document.getElementById('ld-list'), board = document.getElementById('ld-board'), none = document.getElementById('ld-none');
  var f = { stage: '', src: '', own: '', due: false, q: '', cold: false, tag: '', sort: '' }, sel = {};
  var view = 'list', limit = 50, vMode = 'all';
  try { view = localStorage.getItem('ld-view') || 'list'; } catch (e) {}
  var stageColor = D.stageColors || { new: 'bg-sky-100 text-sky-700', contacted: 'bg-violet-100 text-violet-700', qualified: 'bg-amber-100 text-amber-800', proposal: 'bg-orange-100 text-orange-800', won: 'bg-lime-100 text-lime-800', lost: 'bg-ink-100 text-ink-500' };
  function toast(m, ms, act) {
    var old = document.querySelector('.ld-toast'); if (old) { if (old._done) old._done(); old.remove(); }
    var t = document.createElement('div'); t.className = 'ld-toast'; t.setAttribute('role', 'status');
    var sp = document.createElement('span'); sp.textContent = m; t.appendChild(sp);
    var undone = false;
    if (act) { var b = document.createElement('button'); b.type = 'button'; b.className = 'font-semibold text-brand-300 hover:text-white underline underline-offset-4'; b.textContent = act.label; b.onclick = function () { undone = true; act.undo(); t.remove(); }; t.appendChild(b); }
    t._done = function () { if (act && !undone && act.commit) { act.commit(); act.commit = null; } };
    document.body.appendChild(t);
    setTimeout(function () { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; setTimeout(function () { t._done(); t.remove(); }, 300); }, ms || (act ? 6000 : 3200));
  }
  window.ldToast = toast;
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
    return '<span class="inline-flex items-center gap-1 text-[11px] px-1.5 py-0.5 rounded-md border ' + cls + '" title="' + esc(l.next_note) + '">' + uic('clock', 'w-4 h-4 inline-block align-[-3px]') + '' + (s === 'over' ? T.overdue + ' · ' : '') + esc(fmtNext(l)) + '</span>';
  }
  function esBadge(l) { var s = T.es && T.es[l.es]; return s ? ' <span class="text-[9px] px-1 rounded ' + s[1] + '" title="' + esc(s[0]) + '">' + (s[1].indexOf('lime') > -1 ? '✓' : s[1].indexOf('amber') > -1 ? '?' : '✕') + '</span>' : ''; }
  function ownerName(l) { return l.owner ? (D.members[l.owner] || '—') : T.noowner; }
  function avatar(l, sz) {
    var i = (l.name || '?').trim().split(/\s+/).map(function (w) { return w.charAt(0); }).slice(0, 2).join('').toUpperCase();
    return '<div class="' + (sz || 'w-9 h-9') + ' rounded-full bg-gradient-to-br from-brand-500 to-cyan-500 text-white text-xs font-bold grid place-items-center shrink-0">' + esc(i) + '</div>';
  }
  // صف بأعمدة ثابتة زي الجدول: الاسم | المرحلة | المتابعة | التواصل | المسؤول | القيمة | أزرار
  var GRID = 'grid grid-cols-[20px_minmax(0,1fr)_auto] md:grid-cols-[20px_minmax(200px,2.6fr)_104px_128px_minmax(130px,1.3fr)_96px_76px_128px] items-center gap-x-3';
  function nextCell(l) {
    var s = dueState(l); if (!s) return '<span class="text-ink-300">—</span>';
    var c = s === 'over' ? 'text-red-600 font-medium' : s === 'today' ? 'text-amber-700 font-medium' : 'text-ink-600';
    return '<span class="' + c + '" title="' + esc(l.next_note) + '">' + (s === 'over' ? '● ' : s === 'today' ? '◐ ' : '') + esc(fmtNext(l)) + '</span>';
  }
  function row(l) {
    var acts = '<div class="ld-acts hidden md:flex items-center justify-end gap-1">' +
      (l.wa ? '<a class="ld-act" data-qa="wa" href="https://wa.me/' + esc(l.wa) + '" target="_blank" rel="noopener" title="WhatsApp">' + uic('chat', 'w-4 h-4 inline-block align-[-3px]') + '</a>' : '') +
      (l.phone ? '<a class="ld-act" data-qa="call" href="tel:' + esc(l.phone) + '" title="' + esc(T.call) + '">' + uic('phone', 'w-4 h-4 inline-block align-[-3px]') + '</a>' : '') +
      (l.email ? '<a class="ld-act" data-qa="email" href="mailto:' + esc(l.email) + '" title="' + esc(T.email) + '">' + uic('mail', 'w-4 h-4 inline-block align-[-3px]') + '</a>' : '') +
      '<button type="button" class="ld-act' + (l.pin ? ' !text-amber-500 !opacity-100' : '') + '" data-qa="pin" title="' + esc(l.pin ? D.tk.unpin : D.tk.pin) + '">' + uic('star', 'w-4 h-4 inline-block align-[-3px]') + '</button>' +
      (l.edit && !closed(l) ? '<button type="button" class="ld-act" data-qa="later" title="' + esc(T.later) + '">' + uic('clock', 'w-4 h-4 inline-block align-[-3px]') + '</button>' : '') + '</div>';
    var run = l.run ? ' <span class="text-[10px] text-sky-700" title="' + esc(T.pStep.replace('%s', l.run.split('/')[0]).replace('%s', l.run.split('/')[1].replace('!', ''))) + '">' + uic('refresh', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(l.run.replace('!', '')) + '</span>' : '';
    return '<div class="ld-row ' + GRID + ' px-4 py-2.5 hover:bg-ink-50/80 cursor-pointer' + (sel[l.id] ? ' bg-brand-50/60' : '') + '" data-id="' + l.id + '" tabindex="0" role="button">' +
      (l.edit ? '<input type="checkbox" class="ld-sel rounded" data-sid="' + l.id + '"' + (sel[l.id] ? ' checked' : '') + ' aria-label="' + esc(l.name) + '">' : '<span></span>') +
      '<div class="flex items-center gap-3 min-w-0"><div class="relative shrink-0">' + avatar(l) + (l.score ? '<span class="absolute -bottom-1 -end-1">' + scoreBadge(l) + '</span>' : '') + '</div>' +
        '<div class="min-w-0"><div class="font-medium text-ink-900 truncate">' + esc(l.name) + run + '</div><div class="text-xs text-ink-500 truncate">' + esc([l.title, l.company].filter(Boolean).join(' · ') || (D.sources[l.source] || '')) + '</div></div></div>' +
      '<div class="md:hidden"><span class="text-[11px] px-2 py-0.5 rounded-full ' + stageColor[l.stage] + '">' + esc(D.stages[l.stage]) + '</span></div>' +
      '<div class="hidden md:block"><span class="text-[11px] px-2 py-0.5 rounded-full ' + stageColor[l.stage] + (l.edit ? ' ld-edit" data-pop="stage"' : '"') + '>' + esc(D.stages[l.stage]) + (l.edit ? ' ▾' : '') + '</span></div>' +
      '<div class="hidden md:block text-xs"><span class="' + (l.edit ? 'ld-edit px-1 -mx-1 py-0.5" data-pop="next"' : '"') + '>' + nextCell(l) + '</span></div>' +
      '<div class="hidden md:block text-xs text-ink-600 min-w-0"><div class="truncate" dir="ltr">' + (l.phone ? esc(l.phone) : (l.pending ? uic('timer', 'w-4 h-4 inline-block align-[-3px] text-ink-400') : '')) + '</div>' + (l.email ? '<div class="truncate text-ink-400" dir="ltr">' + esc(l.email) + esBadge(l) + '</div>' : '') + '</div>' +
      '<div class="hidden md:block text-xs text-ink-500 truncate">' + esc(ownerName(l)) + '</div>' +
      '<div class="hidden md:block text-sm font-semibold text-ink-900 text-end">' + (l.value ? esc(Number(l.value).toLocaleString()) : '') + '</div>' +
      acts + '</div>';
  }
  function listHead() {
    return '<div class="' + GRID + ' hidden md:grid px-4 py-2 bg-ink-50/70 text-[11px] font-medium text-ink-500 sticky top-0 z-10"><span></span><span>' + esc(T.hName) + '</span><span>' + esc(T.hStage) + '</span><span>' + esc(T.hNext) + '</span><span>' + esc(T.hContact) + '</span><span>' + esc(T.hOwner) + '</span><span class="text-end">' + esc(T.hValue) + '</span><span></span></div>';
  }
  function card(l) {
    var c = '<div class="ld-card group relative rounded-xl border border-ink-200 bg-white p-3 cursor-pointer transition duration-150 hover:-translate-y-0.5 hover:shadow-md hover:border-brand-300' + (l.edit ? ' active:cursor-grabbing' : '') + '" draggable="' + (l.edit ? 'true' : 'false') + '" data-id="' + l.id + '" tabindex="0" role="button">' +
      '<div class="flex items-start gap-2.5"><div class="relative shrink-0">' + avatar(l, 'w-8 h-8') + (l.score ? '<span class="absolute -bottom-1 -end-1 scale-90">' + scoreBadge(l) + '</span>' : '') + '</div>' +
      '<div class="min-w-0 flex-1"><div class="text-sm font-semibold text-ink-900 truncate">' + esc(l.name) + '</div><div class="text-[11px] text-ink-500 truncate">' + esc(l.company || l.title || D.sources[l.source] || '—') + '</div></div></div>' +
      '<div class="flex items-center gap-2 mt-2.5 text-[11px]">' + (dueState(l) ? nextCell(l) : '<span class="text-ink-300">' + esc(T.nNone) + '</span>') +
        (l.value ? '<b class="ms-auto text-ink-900 text-xs">' + esc(Number(l.value).toLocaleString()) + '</b>' : '') + '</div>' +
      '<div class="absolute top-2 end-2 hidden group-hover:flex gap-1">' +
        (l.wa ? '<a class="ld-act !w-7 !h-7 !text-xs" data-qa="wa" href="https://wa.me/' + esc(l.wa) + '" target="_blank" rel="noopener">' + uic('chat', 'w-4 h-4 inline-block align-[-3px]') + '</a>' : '') +
        (l.phone ? '<a class="ld-act !w-7 !h-7 !text-xs" data-qa="call" href="tel:' + esc(l.phone) + '">' + uic('phone', 'w-4 h-4 inline-block align-[-3px]') + '</a>' : '') + '</div></div>';
    return c;
  }
  function scoreBadge(l) { if (!l.score) return ''; var c = l.score >= 60 ? 'bg-lime-500 text-white' : l.score >= 35 ? 'bg-amber-400 text-ink-900' : 'bg-ink-200 text-ink-600'; return '<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full ' + c + '" title="' + esc(T.score + ': ' + (l.why || []).join('، ')) + '">' + l.score + '</span>'; }
  /* ---------- عمود النهارده: مستحقين + أسخن عملاء + المصادر — كله من نفس البيانات ومن غير طلبات زيادة ---------- */
  function sideRow(l, right) {
    return '<button type="button" data-sd-open="' + l.id + '" class="w-full flex items-center gap-2 rounded-lg px-1.5 py-1 text-start hover:bg-ink-50 transition"><span class="shrink-0">' + avatar(l, 'w-7 h-7') + '</span><span class="min-w-0 flex-1"><span class="block text-xs font-medium text-ink-900 truncate">' + esc(l.name) + '</span><span class="block text-[10px] text-ink-500 truncate">' + esc(l.next_note || l.company || D.sources[l.source] || '') + '</span></span>' + right + '</button>';
  }
  (function () { var d = document.getElementById('ld-side-d'); if (!d) return; var k = 'ld-side-open'; var wide = function () { return window.innerWidth >= 1700; };
    try { var v = localStorage.getItem(k); d.open = wide() || v === '1'; } catch (e) { d.open = wide(); }
    d.addEventListener('toggle', function () { if (!wide()) try { localStorage.setItem(k, d.open ? '1' : '0'); } catch (e) {} });
    window.addEventListener('resize', function () { if (wide()) d.open = true; }); })();
  function sideDraw() {
    var box = document.getElementById('ld-side'); if (!box) return;
    var today = D.today || new Date().toISOString().slice(0, 10);
    var due = leads.filter(function (l) { return !closed(l) && l.next_at && l.next_at.slice(0, 10) <= today; }).sort(function (a, b) { return a.next_at < b.next_at ? -1 : 1; });
    document.getElementById('sd-due-n').textContent = due.length ? '(' + due.length + ')' : '';
    document.getElementById('sd-due').innerHTML = due.length ? due.slice(0, 6).map(function (l) { return sideRow(l, nextBadge(l)); }).join('') : '<p class="text-xs text-lime-700 px-1.5 inline-flex items-center gap-1">' + uic('check', 'w-4 h-4') + ' ' + esc(T.sdNone || '') + '</p>';
    var hot = leads.filter(function (l) { return !closed(l) && l.score; }).sort(function (a, b) { return b.score - a.score; }).slice(0, 5);
    document.getElementById('sd-hot').innerHTML = hot.map(function (l) { return sideRow(l, scoreBadge(l)); }).join('') || '<p class="text-xs text-ink-400 px-1.5">—</p>';
    var wk = Date.now() - 7 * 864e5, by = {}, tot = 0; leads.forEach(function (l) { if (srvDate(l.created).getTime() >= wk) { by[l.source] = (by[l.source] || 0) + 1; tot++; } });
    var ks = Object.keys(by).sort(function (a, b) { return by[b] - by[a]; }), mx = ks.length ? by[ks[0]] : 1;
    var st = function (id, v) { var x = document.getElementById(id); if (x) x.textContent = v; }; st('sd-s-due', due.length); st('sd-s-hot', leads.filter(function (l) { return !closed(l) && l.score >= 60; }).length); st('sd-s-new', tot);
    document.getElementById('sd-src').innerHTML = ks.length ? ks.map(function (k) { return '<button type="button" data-sd-src="' + esc(k) + '" class="w-full grid grid-cols-[1fr_auto] gap-x-2 items-center text-start text-xs hover:text-brand-700"><span class="truncate">' + esc(D.sources[k] || k) + '</span><b>' + by[k] + '</b><span class="col-span-2 h-1.5 rounded-full bg-ink-100 overflow-hidden"><span class="block h-full rounded-full bg-brand-500" style="width:' + Math.max(6, by[k] / mx * 100) + '%"></span></span></button>'; }).join('') : '<p class="text-xs text-ink-400">—</p>';
  }
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-sd-open]'); if (o) { open(+o.dataset.sdOpen); return; }
    var v = e.target.closest('[data-sd-view]'); if (v) { var t = document.querySelector('[data-view="' + v.dataset.sdView + '"]'); if (t) { t.click(); t.scrollIntoView({ block: 'nearest' }); } return; }
    var sc = e.target.closest('[data-sd-src]'); if (sc) { var se = document.getElementById('ld-src'); if (se) { se.value = sc.dataset.sdSrc; se.dispatchEvent(new Event('change', { bubbles: true })); } }
  });
  function draw() {
    sideDraw();
    var shown = leads.filter(match);
    if (f.sort === 'score') shown.sort(function (a, b) { return b.score - a.score; });
    else if (f.sort === 'new') shown.sort(function (a, b) { return b.id - a.id; });
    var cnt = {}; leads.forEach(function (l) { if (match(Object.assign({}, l, { stage: f.stage || l.stage }))) cnt[l.stage] = (cnt[l.stage] || 0) + 1; });
    document.querySelectorAll('[data-cnt]').forEach(function (s) { s.textContent = cnt[s.dataset.cnt] || 0; });
    document.querySelectorAll('.ld-view').forEach(function (b) { var on = b.dataset.v === view; b.classList.toggle('bg-brand-600', on); b.classList.toggle('text-white', on); b.classList.toggle('text-ink-600', !on); b.setAttribute('aria-pressed', on); });
    list.classList.toggle('hidden', view !== 'list' || !shown.length);
    board.classList.toggle('hidden', view !== 'board' || !leads.length);
    none.classList.toggle('hidden', shown.length > 0 || !leads.length || view === 'board');
    var more = document.getElementById('ld-more');
    if (view === 'list') {
      list.innerHTML = listHead() + shown.slice(0, limit).map(row).join('');
      more.classList.toggle('hidden', shown.length <= limit);
      more.querySelector('span').textContent = '(' + (shown.length - limit) + ')';
    } else {
      more.classList.add('hidden');
      board.innerHTML = Object.keys(D.stages).filter(function (s) { return !f.stage || s === f.stage; }).map(function (s) {
      var col = shown.filter(function (l) { return l.stage === s; });
      var sum = col.reduce(function (a, l) { return a + (+l.value || 0); }, 0);
      var icons = {}; var ICN = { new: 'new', contacted: 'chat', qualified: 'star', proposal: 'file', won: 'trophy', lost: 'x' }; Object.keys(D.stages).forEach(function (k) { icons[k] = uic(ICN[k] || 'layers', 'w-4 h-4'); });
      return '<div class="ld-col rounded-2xl bg-ink-50/80 border border-ink-200 p-2 min-h-[260px] flex flex-col transition" data-s="' + s + '">' +
        '<div class="flex items-center gap-2 px-1.5 py-1.5 mb-1"><span class="text-sm">' + (icons[s] || '') + '</span><span class="text-sm font-semibold text-ink-800">' + esc(D.stages[s]) + '</span><span class="text-[11px] rounded-full bg-white border border-ink-200 px-1.5 text-ink-500">' + col.length + '</span>' + (sum ? '<span class="text-[11px] text-ink-500 ms-auto">' + sum.toLocaleString() + '</span>' : '') + '</div>' +
        '<div class="h-1 rounded-full mx-1.5 mb-2 ' + stageColor[s].split(' ')[0] + '"></div>' +
        '<div class="space-y-2 flex-1">' + col.slice(0, 60).map(card).join('') + (col.length > 60 ? '<div class="text-center text-[11px] text-ink-400 py-1">+' + (col.length - 60) + '</div>' : '') +
        (col.length ? '' : '<div class="ld-drop-empty grid place-items-center h-24 rounded-xl border-2 border-dashed border-ink-200 text-[11px] text-ink-400">' + esc(T.drop) + '</div>') + '</div></div>';
    }).join('');
    }
  }

  document.querySelectorAll('.ld-view').forEach(function (b) { b.addEventListener('click', function () { view = b.dataset.v; try { localStorage.setItem('ld-view', view); } catch (e) {} draw(); }); });
  function setStage(s) { f.stage = s; document.querySelectorAll('#ld-stages .ld-seg').forEach(function (x) { x.classList.toggle('on', x.dataset.s === s); }); draw(); }
  document.querySelectorAll('#ld-stages .ld-seg').forEach(function (b) { b.addEventListener('click', function () { setStage(b.dataset.s); }); });
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
  board.addEventListener('dragend', function () { dragId = null; board.querySelectorAll('.ld-col').forEach(function (c) { c.classList.remove('ring-2', 'ring-brand-400', 'bg-brand-50'); }); board.querySelectorAll('.opacity-50').forEach(function (x) { x.classList.remove('opacity-50'); }); });
  board.addEventListener('dragover', function (e) { var c = e.target.closest('.ld-col'); if (!c || dragId === null) return; e.preventDefault(); board.querySelectorAll('.ld-col').forEach(function (x) { x.classList.toggle('ring-2', x === c); x.classList.toggle('ring-brand-400', x === c); x.classList.toggle('bg-brand-50', x === c); }); });
  board.addEventListener('drop', function (e) {
    var c = e.target.closest('.ld-col'); if (!c || dragId === null) return; e.preventDefault();
    var l = byId(dragId); dragId = null; if (!l || l.stage === c.dataset.s) return draw();
    changeStage(l, c.dataset.s);
  });
  function byId(id) { for (var i = 0; i < leads.length; i++) if (leads[i].id === id) return leads[i]; return null; }

  // الدرج
  var dr = document.getElementById('ld-drawer'), form = document.getElementById('ld-form'), msg = document.getElementById('ld-msg'), cur = null, lastFocus = null;
  function openModal(m) {
    if (m.classList.contains('hidden')) lastFocus = document.activeElement;
    if (m === dr && m.classList.contains('hidden')) { m.classList.add('ld-closed'); m.classList.remove('hidden'); requestAnimationFrame(function () { requestAnimationFrame(function () { m.classList.remove('ld-closed'); }); }); }
    else m.classList.remove('hidden');
    m.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
  }
  function closeModal(m) {
    m.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; if (lastFocus) try { lastFocus.focus(); } catch (e) {}
    if (m === dr) { m.classList.add('ld-closed'); setTimeout(function () { if (m.classList.contains('ld-closed')) m.classList.add('hidden'); }, 240); }
    else m.classList.add('hidden');
  }
  [dr, document.getElementById('ld-add')].forEach(function (m) { m.querySelectorAll('[data-close]').forEach(function (x) { x.addEventListener('click', function () { closeModal(m); }); }); });
  document.addEventListener('keydown', function (e) { if (e.key !== 'Escape') return; [dr, document.getElementById('ld-add'), document.getElementById('ld-imp'), document.getElementById('ld-tpl-dlg'), document.getElementById('ld-sug'), document.getElementById('ld-fdlg'), document.getElementById('ld-pdlg')].filter(Boolean).forEach(function (m) { if (!m.classList.contains('hidden')) closeModal(m); }); });
  function quick(l) {
    var b = [];
    if (l.phone) b.push('<a class="btn-ghost text-sm" href="tel:' + esc(l.phone) + '">' + uic('phone', 'w-4 h-4 inline-block align-[-3px]') + '' + T.call + '</a>');
    if (l.wa) b.push('<a class="btn-ghost text-sm" target="_blank" rel="noopener" href="https://wa.me/' + esc(l.wa) + '">' + uic('chat', 'w-4 h-4 inline-block align-[-3px]') + 'WhatsApp</a>');
    if (l.email) b.push('<a class="btn-ghost text-sm" href="mailto:' + esc(l.email) + '">' + uic('mail', 'w-4 h-4 inline-block align-[-3px]') + '' + T.email + '</a>');
    if (l.src_url) b.push('<a class="btn-ghost text-sm" data-nolog href="' + esc(l.src_url) + '">' + uic('chat', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.chat) + '</a>');
    if (l.linkedin) b.push('<a class="btn-ghost text-sm" target="_blank" rel="noopener" href="' + esc(l.linkedin) + '">in</a>');
    if (l.pending && !l.phone) b.push('<span class="text-xs text-ink-500 self-center animate-pulse">' + uic('timer', 'w-4 h-4 inline-block align-[-3px]') + '' + T.pending + '</span>');
    return b.join('');
  }
  function open(id) {
    var l = byId(id); if (!l) return; cur = l;
    document.getElementById('ld-d-title').textContent = l.name;
    document.getElementById('ld-d-sub').textContent = [l.title, l.company].filter(Boolean).join(' · ') || (D.sources[l.source] || '');
    document.getElementById('ld-d-av').innerHTML = avatar(l, 'w-12 h-12 text-base');
    document.getElementById('ld-d-score').innerHTML = scoreBadge(l);
    document.getElementById('ld-d-stage').innerHTML = '<span class="text-[11px] px-2 py-0.5 rounded-md ' + stageColor[l.stage] + '">' + esc(D.stages[l.stage]) + '</span> ' + nextBadge(l);
    curTab = 'ov';
    document.getElementById('ld-quick').innerHTML = quick(l);
    ['id', 'name', 'company', 'title', 'phone', 'email', 'value', 'tags', 'note', 'next_at', 'next_note'].forEach(function (k) { form.elements[k].value = l[k] == null ? '' : l[k]; });
    if (form.elements.pipeline) form.elements.pipeline.value = l.pl || 'sales';
    Array.prototype.forEach.call(form.querySelectorAll('[data-cf]'), function (i) { i.value = (l.cf && l.cf[i.dataset.cf]) || ''; });
    form.elements.owner.value = String(l.owner || 0);
    form.querySelectorAll('[name=stage]').forEach(function (r) { r.checked = r.value === l.stage; });
    form.querySelectorAll('input,select,textarea,button:not([data-close]):not([data-tabbtn]):not(#ld-q-next)').forEach(function (x) { if (x.name !== 'owner' || D.canManage) x.disabled = !l.edit; });
    document.getElementById('ld-meta').textContent = T.from.replace('%s', D.sources[l.source] || l.source).replace('%s', (l.created || '').slice(0, 10)) + (l.edit ? '' : ' · ' + T.ro);
    msg.textContent = '';
    drawTpl(l); loadActs(l); apReset(l); drawPlan(l, undefined);
    openModal(dr);
    var sc = document.getElementById('ld-scroll'); if (sc) sc.scrollTop = 0;
  }
  /* تابات الدرج: نظرة عامة / تواصل / صفقات / بيانات */
  var curTab = 'ov'; try { curTab = localStorage.getItem('ld-tab') || 'ov'; } catch (e) {}
  /* الدرج بقى أقسام تحت بعض (من غير تابات): كل قسم بيتفتح/يتقفل، والحالة بتتفتكر */
  var secOpen = {}; try { secOpen = JSON.parse(localStorage.getItem('ld-secs') || '{}'); } catch (e) {}
  dr.querySelectorAll('.ld-sec').forEach(function (d) { if (secOpen[d.dataset.tab] === 0) d.open = false; d.addEventListener('toggle', function () { secOpen[d.dataset.tab] = d.open ? 1 : 0; try { localStorage.setItem('ld-secs', JSON.stringify(secOpen)); } catch (e) {} }); });
  function setTab(k) { // بيفتح القسم وبينزل له (اللي كان "تاب" قبل كده)
    curTab = k; var d = dr.querySelector('.ld-sec[data-tab="' + k + '"]'); if (!d) return; if (!d.open) d.open = true;
    if (k !== 'ov') setTimeout(function () { d.scrollIntoView({ block: 'start', behavior: 'smooth' }); }, 30);
  }
  function tabN(k, n) { var b = dr.querySelector('.ld-sec[data-tab="' + k + '"] .ld-tabn'); if (b) b.textContent = n ? n : ''; }
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
    var fd = new FormData(form); var sv = document.getElementById('ld-save');
    if (fd.get('stage') === 'lost' && cur.stage !== 'lost' && !form.dataset.lr) { askLost().then(function (r) { if (!r) return; form.dataset.lr = r; form.requestSubmit(); }); return; }
    if (form.dataset.lr) { fd.append('lost_reason', form.dataset.lr); delete form.dataset.lr; }
    sv.disabled = true;
    post(fd).then(function (r) {
      sv.disabled = false;
      if (!r.ok) { msg.className = 'text-xs text-red-600'; msg.textContent = r.error || T.err; return; }
      Object.assign(cur, r.lead); msg.className = 'text-xs text-lime-700'; msg.textContent = T.saved; toast(T.saved);
      document.getElementById('ld-quick').innerHTML = quick(cur); draw();
      setTimeout(function () { closeModal(dr); }, 500);
    }).catch(function () { sv.disabled = false; msg.className = 'text-xs text-red-600'; msg.textContent = T.err; });
  });
  var del = document.getElementById('ld-del');
  if (del) del.addEventListener('click', function () {
    if (!cur) return;
    var gone = cur, ix = leads.indexOf(gone);
    leads = leads.filter(function (l) { return l !== gone; }); closeModal(dr); draw();
    toast(T.deleted.replace('%s', gone.name), 0, { label: T.undo,
      undo: function () { leads.splice(ix, 0, gone); draw(); },
      commit: function () { post({ action: 'delete', id: gone.id }).then(function (r) { if (!r.ok) { leads.splice(ix, 0, gone); draw(); toast(r.error || T.err); } }); } });
    window.addEventListener('beforeunload', function () { var t = document.querySelector('.ld-toast'); if (t && t._done) t._done(); });
  });

  // إضافة
  var add = document.getElementById('ld-add'), af = document.getElementById('ld-add-form');
  document.getElementById('ld-add-btn').addEventListener('click', function () { af.reset(); document.getElementById('ld-add-msg').textContent = ''; openModal(add); af.elements.name.focus(); });
  af.addEventListener('submit', function (e) {
    e.preventDefault();
    var afd = new FormData(af); afd.append('pl', D.pl || 'sales');
    post(afd).then(function (r) {
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
    tabN('ct', (a || []).filter(function (x) { return x.kind !== 'stage'; }).length);
    acts.innerHTML = a.length ? a.map(function (x) { var k = D.kinds[x.kind] || ['•', x.kind]; k = [uic({ call: 'phone', wa: 'chat', email: 'mail', meet: 'users', note: 'file', stage: 'route' }[x.kind] || 'file', 'w-3.5 h-3.5'), k[1]]; return '<li class="flex gap-2 text-xs"><span class="w-6 h-6 text-ink-600 rounded-full bg-ink-100 grid place-items-center shrink-0">' + k[0] + '</span><div class="min-w-0"><div class="text-ink-500">' + esc(k[1]) + ' · ' + esc(x.who || '—') + ' · ' + esc(when(x.created_at)) + '</div>' + (x.body ? voiceBody(x.body) : '') + '</div></li>'; }).join('')
      : '<li class="text-xs text-ink-400">' + esc(T.noacts) + '</li>';
  }
  var intel = document.getElementById('ld-intel');
  function drawIntel(l, it) {
    if (!it) { intel.innerHTML = ''; return; }
    intel.innerHTML = '<div class="flex items-center gap-2">' + scoreBadge(l) + '<span class="text-ink-700">' + esc(it.summary) + '</span></div>' +
      (l.why && l.why.length ? '<div class="text-[11px] text-ink-500">' + esc(T.score) + ': ' + esc(l.why.join('، ')) + '</div>' : '') +
      (it.best ? '<div class="text-ink-600">' + uic('clock', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.best) + ': <b>' + esc(it.best) + '</b></div>' : '') +
      (l.edit ? '<div class="pt-1"><div class="text-ink-500 mb-1">' + uic('file', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.firstMsg) + '</div><textarea id="ld-first" rows="3" class="input text-xs" dir="auto">' + esc(it.first) + '</textarea><div class="flex flex-wrap gap-1.5 mt-1.5">' +
        (l.wa ? '<button type="button" class="btn-ghost !py-1 !text-xs" data-fm="wa">' + uic('chat', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.useWa) + '</button>' : '') + (l.email ? '<button type="button" class="btn-ghost !py-1 !text-xs" data-fm="email">' + uic('mail', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.useMail) + '</button>' : '') +
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
        (+m.opens ? '<span class="rounded bg-lime-100 text-lime-800 px-1">' + uic('eye', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.mOpen.replace('%s', m.opens)) + '</span>' : '<span class="rounded bg-ink-100 text-ink-500 px-1">' + esc(T.mNotOpen) + '</span>') +
        (+m.clicks ? '<span class="rounded bg-violet-100 text-violet-800 px-1">' + uic('link', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.mClick) + '</span>' : '') + '</div>';
    }).join('');
  }
  document.getElementById('ld-mail-tg').addEventListener('click', function () { mailF.classList.toggle('hidden'); var fm = document.getElementById('ld-first'); var b = document.getElementById('ld-mail-b'); if (!b.value && fm) b.value = fm.value; });
  document.getElementById('ld-mail-go').addEventListener('click', function () {
    if (!cur) return; var l = cur, bt = this; bt.disabled = true;
    post({ action: 'send_email', id: l.id, subject: document.getElementById('ld-mail-s').value, body: document.getElementById('ld-mail-b').value }).then(function (r) {
      bt.disabled = false; if (!r.ok) return toast(r.error || T.err);
      Object.assign(l, r.lead); drawActs(r.acts); drawMails(l, r.mails); document.getElementById('ld-mail-s').value = ''; document.getElementById('ld-mail-b').value = ''; draw();
    }).catch(function () { bt.disabled = false; toast(T.err); });
  });
  var dl = document.getElementById('ld-deal-list'), dAdd = document.getElementById('ld-deal-add');
  function dealForm(d) {
    d = d || { id: 0, title: '', value: '', status: 'open', close_at: '' };
    return '<div class="rounded-lg border border-brand-200 bg-brand-50/40 p-2 space-y-1.5" data-dform="' + d.id + '"><input class="input !py-1 text-xs" data-k="title" maxlength="190" placeholder="' + esc(T.dTitle) + '" value="' + esc(d.title) + '">' +
      '<div class="grid grid-cols-3 gap-1.5"><input class="input !py-1 text-xs" data-k="value" inputmode="decimal" dir="ltr" placeholder="' + esc(T.dValue) + '" value="' + esc(d.value || '') + '"><select class="input !py-1 text-xs" data-k="status">' + Object.keys(T.dStatus).map(function (k) { return '<option value="' + k + '"' + (k === d.status ? ' selected' : '') + '>' + esc(T.dStatus[k]) + '</option>'; }).join('') + '</select>' +
      '<input type="date" class="input !py-1 text-xs" data-k="close_at" title="' + esc(T.dClose) + '" value="' + esc(d.close_at || '') + '"></div><div class="flex gap-1.5"><button type="button" class="btn-primary !py-1 !text-xs flex-1" data-dsave>' + esc(T.saved.replace(' ✓', '') || 'OK') + '</button>' + (d.id ? '<button type="button" class="btn-ghost !py-1 !text-xs text-red-600" data-ddel>✕</button>' : '') + '</div></div>';
  }
  function drawDeals(l, ds) {
    tabN('dl', (ds || []).length);
    dAdd.classList.toggle('hidden', !l.edit); l._deals = ds || [];
    var dc = { open: 'bg-sky-100 text-sky-800', won: 'bg-lime-100 text-lime-800', lost: 'bg-ink-100 text-ink-500' };
    dl.innerHTML = l._deals.length ? l._deals.map(function (d) { return '<div class="flex items-center gap-2 text-xs ' + (l.edit ? 'cursor-pointer hover:bg-ink-50' : '') + ' rounded-lg px-1.5 py-1" data-deal="' + d.id + '"><span class="flex-1 truncate text-ink-800">' + esc(d.title) + '</span><b class="text-ink-900">' + Number(d.value).toLocaleString() + '</b><span class="px-1.5 rounded ' + dc[d.status] + '">' + esc(T.dStatus[d.status]) + '</span>' +
        '<a class="ld-act !w-7 !h-7" target="_blank" rel="noopener" href="' + esc(D.docUrl + '&deal=' + d.id + '&t=quote') + '" title="' + esc(D.docT.quote) + '">' + uic('file', 'w-3.5 h-3.5') + '</a>' +
        '<a class="ld-act !w-7 !h-7" target="_blank" rel="noopener" href="' + esc(D.docUrl + '&deal=' + d.id + '&t=invoice') + '" title="' + esc(D.docT.invoice) + '">' + uic('wallet', 'w-3.5 h-3.5') + '</a></div>'; }).join('')
      : '<p class="text-[11px] text-ink-400">' + esc(T.dNone) + '</p>';
  }
  dAdd.addEventListener('click', function () { if (!dl.querySelector('[data-dform="0"]')) dl.insertAdjacentHTML('afterbegin', dealForm()); });
  dl.addEventListener('click', function (e) {
    if (!cur || e.target.closest('a[href]')) return; var l = cur;
    var row = e.target.closest('[data-deal]'); if (row && l.edit) { var d = (l._deals || []).filter(function (x) { return x.id === +row.dataset.deal; })[0]; row.outerHTML = dealForm(d); return; }
    var f0 = e.target.closest('[data-dform]'); if (!f0) return;
    var del = e.target.closest('[data-ddel]'), sv = e.target.closest('[data-dsave]'); if (!del && !sv) return;
    if (del && !confirm(T.dDel)) return;
    var data = { action: del ? 'deal_del' : 'deal_save', id: l.id, deal: f0.dataset.dform }; f0.querySelectorAll('[data-k]').forEach(function (i) { data[i.dataset.k] = i.value; });
    post(data).then(function (r) { if (!r.ok) return toast(r.error || T.err); Object.assign(l, r.lead); if (cur === l) { drawDeals(l, r.deals); form.elements.value.value = l.value || ''; form.querySelectorAll('[name=stage]').forEach(function (x) { x.checked = x.value === l.stage; }); } draw(); });
  });
  var vl = document.getElementById('ld-visit-list');
  function drawVisits(l, vs) {
    document.getElementById('ld-tl').classList.toggle('hidden', !l.edit); document.getElementById('ld-tl-box').classList.add('hidden');
    vl.innerHTML = (vs || []).length ? vs.map(function (v) { var u = v.url.replace(/^https?:\/\//, ''); return '<div class="flex items-center gap-2 text-[11px]"><span class="truncate flex-1 ' + (+v.hot ? 'text-red-700 font-semibold' : 'text-ink-700') + '" dir="ltr" title="' + esc(v.url) + '">' + (+v.hot ? '' + uic('fire', 'w-4 h-4 inline-block align-[-3px]') + '' : '') + esc(u) + '</span><span class="text-ink-400 shrink-0">' + esc(when(v.created_at)) + '</span></div>'; }).join('')
      : '<p class="text-[11px] text-ink-400">' + esc(T.noVisits) + '</p>';
  }
  document.getElementById('ld-tl').addEventListener('click', function () { document.getElementById('ld-tl-box').classList.toggle('hidden'); });
  document.getElementById('ld-tl-go').addEventListener('click', function () {
    if (!cur) return;
    post({ action: 'track_link', id: cur.id, url: document.getElementById('ld-tl-u').value }).then(function (r) {
      if (!r.ok) return toast(r.error || T.err); try { navigator.clipboard.writeText(r.link); } catch (e) {} prompt(T.linkCopied, r.link);
    });
  });
  function loadActs(l) { acts.innerHTML = ''; drawIntel(l, null); drawMails(l, []); drawDeals(l, []); drawVisits(l, []); post({ action: 'acts', id: l.id }).then(function (r) { if (r.ok && cur === l) { drawActs(r.acts); drawPlan(l, r.run); drawIntel(l, r.intel); drawMails(l, r.mails); drawDeals(l, r.deals); drawVisits(l, r.visits); } }); }
  function logAct(kind, body, outcome) {
    if (!cur) return Promise.resolve();
    var l = cur;
    return post({ action: 'log', id: l.id, kind: kind, body: body || '', outcome: outcome || '' }).then(function (r) {
      if (!r.ok) return toast(r.error || T.err);
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
      post({ action: 'tpl_save', tpl: JSON.stringify(t) }).then(function (r) { if (!r.ok) return toast(r.error || T.err); D.tpl = r.tpl; if (cur) drawTpl(cur); td.classList.add('hidden'); });
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
      if (run.ch === 'wa' && l.wa) doBtn = '<a class="btn-primary !py-1 !text-xs" data-pa="plan_do" target="_blank" rel="noopener" href="https://wa.me/' + esc(l.wa) + '?text=' + encodeURIComponent(run.text) + '">' + uic('chat', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.pDo) + '</a>';
      else if (run.ch === 'call' && l.phone) doBtn = '<a class="btn-primary !py-1 !text-xs" data-pa="plan_do" href="tel:' + esc(l.phone) + '">' + uic('phone', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.pDo) + '</a>';
      else if (run.ch === 'email' && l.email) doBtn = '<a class="btn-primary !py-1 !text-xs" data-pa="plan_do" href="mailto:' + esc(l.email) + '?subject=' + encodeURIComponent(run.subject || '') + '&body=' + encodeURIComponent(run.text) + '">' + uic('mail', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.pDo) + '</a>';
      else doBtn = '<button type="button" class="btn-primary !py-1 !text-xs" data-pa="plan_do">✓ ' + esc(T.pDo) + '</button>';
    }
    pb.innerHTML = '<div class="flex items-center gap-2"><b class="text-ink-800">' + esc(run.plan) + '</b><span class="text-ink-500">' + esc(T.pStep.replace('%s', run.step + 1).replace('%s', run.n)) + '</span>' +
      '<span class="ms-auto ' + (due ? 'text-amber-700 font-semibold' : 'text-ink-500') + '">' + (due ? '' + uic('zap', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.pDue) : esc(T.pWait.replace('%s', when(run.due)))) + '</span></div>' +
      '<div class="mt-1.5 rounded-lg bg-white border border-ink-200 p-2 text-ink-700 whitespace-pre-line" dir="auto">' + ch[0] + ' ' + esc(run.text) + '</div>' +
      (run.err ? '<div class="text-[11px] text-amber-700 mt-1">' + esc(run.err) + '</div>' : '') +
      '<div class="flex flex-wrap gap-1.5 mt-2">' + doBtn + '<button type="button" class="btn-ghost !py-1 !text-xs" data-pa="plan_skip">' + esc(T.pSkip) + '</button><button type="button" class="btn-ghost !py-1 !text-xs text-red-600 ms-auto" data-pa="plan_stop">' + esc(T.pStop) + '</button></div>';
  }
  if (pb) pb.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pa]'); if (!b || !cur) return; var l = cur, a = b.dataset.pa, data = { action: a, id: l.id };
    if (a === 'plan_start') { data.plan = (document.getElementById('ld-plan-sel') || {}).value; if (!data.plan) return; }
    if (b.tagName !== 'A') e.preventDefault();
    post(data).then(function (r) {
      if (!r.ok) return toast(r.error || T.err);
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
      post({ action: 'plans_save', plans: JSON.stringify(plans) }).then(function (r) { if (!r.ok) return toast(r.error || T.err); D.plans = r.plans; closeModal(pdl); location.reload(); });
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
    var sf = document.getElementById('ld-slaform');
    sf.addEventListener('submit', function (e) { e.preventDefault(); var fd = new FormData(sf); fd.append('action', 'sla_save'); post(fd).then(function (r) { var m = document.getElementById('ld-slamsg'); m.className = 'text-xs self-center ' + (r.ok ? 'text-lime-700' : 'text-red-600'); m.textContent = r.ok ? T.saved : (r.error || T.err); }); });
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
    var slb = document.getElementById('ld-sla-btn'); if (slb) slb.addEventListener('click', function () { openModal(fdl); setTimeout(function () { var f = document.getElementById('ld-slaform'); f.scrollIntoView({ block: 'center', behavior: 'smooth' }); f.classList.add('ring-2', 'ring-brand-300'); setTimeout(function () { f.classList.remove('ring-2', 'ring-brand-300'); }, 1200); }, 50); });
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
    var wl = { ai: ['bg-lime-100 text-lime-800', T.whyAi], contact: ['bg-sky-100 text-sky-800', T.whyContact], price: ['bg-amber-100 text-amber-800', T.whyPrice], cta: ['bg-violet-100 text-violet-800', T.whyCta] };
    sgl.innerHTML = sItems.map(function (it, i) {
      return '<label class="flex gap-3 rounded-xl border border-ink-200 p-3 hover:border-brand-300 cursor-pointer"><input type="checkbox" class="sg-c rounded mt-1" data-i="' + i + '"' + (it.why.indexOf('ai') > -1 || it.why.indexOf('contact') > -1 || it.why.indexOf('cta') > -1 ? ' checked' : '') + '>' +
        '<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-1.5"><span class="font-medium text-ink-900 text-sm">' + esc(it.name || '—') + '</span><span class="text-[10px] text-ink-500">' + (it.source === 'comments' ? '' + uic('chat', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.cmt) : '' + uic('mail', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.msgK)) + ' · ' + esc(it.where) + '</span>' +
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
  var ctaIn = document.getElementById('ld-cta');
  if (ctaIn) ctaIn.addEventListener('change', function () { post({ action: 'cta_save', words: ctaIn.value }).then(function (r) { if (r.ok) { ctaIn.value = r.words.join(', '); sugLoad(); } }); });
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
  function bbDraw() { var n = selIds().length; bb.classList.toggle('hidden', !n); bb.classList.toggle('flex', !!n); document.getElementById('ld-bn').textContent = n; var mb = document.getElementById('ld-b-merge'); if (mb) mb.classList.toggle('hidden', n !== 2); }
  /* ---------- #73 إيميل جماعي ---------- */
  var bmb = document.getElementById('ld-b-mail');
  if (bmb) bmb.addEventListener('click', function () {
    var B = D.bm, list = leads.filter(function (l) { return sel[l.id]; }), ok = list.filter(function (l) { return l.email && !l.unsub; });
    if (!ok.length) return toast(B.none);
    var w = document.createElement('div'); w.className = 'fixed inset-0 z-[70] flex items-center justify-center bg-ink-900/40 p-4';
    w.innerHTML = '<form class="w-[min(560px,96vw)] rounded-2xl bg-white shadow-2xl"><div class="flex items-center px-5 h-14 border-b border-ink-200"><b class="flex-1">' + uic('mail', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(B.t) + ' (' + ok.length + '/' + list.length + ')</b><button type="button" data-x class="p-2 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div>' +
      '<div class="p-5 space-y-2"><input name="subject" required maxlength="200" class="input text-sm" placeholder="' + esc(B.sub) + '" dir="auto"><textarea name="body" required rows="7" class="input text-sm" placeholder="' + esc(B.body) + '" dir="auto"></textarea><p class="text-[11px] text-ink-500">' + esc(B.hint) + '</p>' +
      '<div class="hidden" data-pg><div class="h-2 rounded-full bg-ink-100 overflow-hidden"><div class="h-full bg-brand-600 transition-all" style="width:0"></div></div><p class="text-xs text-ink-500 mt-1"></p></div></div>' +
      '<div class="flex justify-end gap-2 px-5 py-3 border-t border-ink-200"><button class="btn-primary">' + esc(B.go) + '</button></div></form>';
    document.body.appendChild(w);
    var stop = false; w.addEventListener('click', function (e) { if (e.target === w || e.target.closest('[data-x]')) { stop = true; w.remove(); } });
    w.querySelector('form').addEventListener('submit', function (e) {
      e.preventDefault(); var f = e.target, i = 0, sent = 0, fail = 0, pg = f.querySelector('[data-pg]'); f.querySelector('.btn-primary').disabled = true; pg.classList.remove('hidden');
      (function next() {
        if (stop) return;
        if (i >= ok.length) { pg.querySelector('p').textContent = B.done.replace('%s', sent).replace('%s', list.length - ok.length).replace('%s', fail); draw(); return; }
        var l = ok[i++];
        post({ action: 'send_email', id: l.id, subject: f.subject.value, body: f.body.value }).then(function (r) { if (r.ok) { sent++; Object.assign(l, r.lead); } else fail++; }).catch(function () { fail++; }).then(function () {
          pg.querySelector('div > div').style.width = Math.round(i / ok.length * 100) + '%'; pg.querySelector('p').textContent = B.prog.replace('%s', i).replace('%s', ok.length);
          setTimeout(next, 1200); // بالراحة عشان الإيميل مايتعلمش سبام
        });
      })();
    });
  });
  /* ---------- #76 ملاحظة صوتية + #77 إملاء بالصوت ---------- */
  function voiceBody(b) {
    var m = /^🎙 voice:(\S+)\s*(\S*)\n?([\s\S]*)$/.exec(b);
    if (!m) return '<div class="text-ink-800 whitespace-pre-line break-words" dir="auto">' + esc(b) + '</div>';
    return '<audio controls preload="none" class="h-8 mt-1 max-w-full" src="' + esc(D.vc.url + encodeURIComponent(m[1])) + '"></audio><span class="text-[10px] text-ink-400 ms-1">' + esc(m[2]) + '</span>' + (m[3] ? '<div class="text-ink-800 whitespace-pre-line break-words" dir="auto">' + esc(m[3]) + '</div>' : '');
  }
  var recB = document.getElementById('ld-rec'), recSt = document.getElementById('ld-rec-st'), rec = null, chunks = [], t0 = 0, tm = null;
  if (recB) recB.addEventListener('click', function () {
    if (!navigator.mediaDevices || !window.MediaRecorder) return toast(D.vc.noMic);
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
      chunks = []; rec = new MediaRecorder(s); rec.ondataavailable = function (e) { if (e.data.size) chunks.push(e.data); };
      rec.onstop = function () {
        s.getTracks().forEach(function (x) { x.stop(); }); clearInterval(tm); recSt.classList.add('hidden'); recB.disabled = false;
        var blob = new Blob(chunks, { type: rec.mimeType || 'audio/webm' }), fd = new FormData(), id = form.elements.id.value;
        fd.append('action', 'voice'); fd.append('id', id); fd.append('sec', Math.round((Date.now() - t0) / 1000)); fd.append('body', document.getElementById('ld-log-body').value); fd.append('audio', blob, 'note.' + (/mp4/.test(blob.type) ? 'm4a' : /ogg/.test(blob.type) ? 'ogg' : 'webm'));
        post(fd).then(function (r) { if (!r.ok) return toast(r.error || T.err); document.getElementById('ld-log-body').value = ''; toast(D.vc.saved); if (window.__ldActs) window.__ldActs(r.acts); else open(+id); });
      };
      rec.start(); t0 = Date.now(); recB.disabled = true; recSt.classList.remove('hidden');
      tm = setInterval(function () { var s2 = Math.round((Date.now() - t0) / 1000); document.getElementById('ld-rec-t').textContent = Math.floor(s2 / 60) + ':' + ('0' + s2 % 60).slice(-2); if (s2 >= 300) rec.stop(); }, 500);
    }).catch(function () { toast(D.vc.noMic); });
  });
  var rs = document.getElementById('ld-rec-stop'); if (rs) rs.addEventListener('click', function () { if (rec && rec.state === 'recording') rec.stop(); });
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition, dict = document.getElementById('ld-dict');
  if (SR && dict) {
    dict.classList.remove('hidden'); var sr = null;
    dict.addEventListener('click', function () {
      if (sr) { sr.stop(); return; }
      var inp = document.getElementById('ld-log-body'), base = inp.value ? inp.value + ' ' : '';
      sr = new SR(); sr.lang = D.vc.lang; sr.interimResults = true; sr.continuous = true; dict.classList.add('bg-red-50', 'text-red-600');
      sr.onresult = function (e) { var txt = ''; for (var i = 0; i < e.results.length; i++) txt += e.results[i][0].transcript; inp.value = base + txt; };
      sr.onend = function () { sr = null; dict.classList.remove('bg-red-50', 'text-red-600'); };
      sr.start();
    });
  }
  /* ---------- #83 مهامي ---------- */
  var tasks = D.tasks || [];
  function tasksDraw() {
    var box = document.getElementById('sd-tasks'); if (!box) return; var now = new Date();
    document.getElementById('sd-tasks-n').textContent = tasks.length ? '(' + tasks.length + ')' : '';
    box.innerHTML = tasks.length ? tasks.map(function (t) { var late = t.due && new Date(t.due) < now;
      return '<div class="group flex items-start gap-2 rounded-lg px-1.5 py-1 hover:bg-ink-50"><input type="checkbox" class="mt-0.5 rounded" data-tdone="' + t.id + '"><span class="flex-1 min-w-0"><span class="block text-xs text-ink-900 break-words" dir="auto">' + esc(t.title) + '</span>' +
        '<span class="block text-[10px] ' + (late ? 'text-red-600 font-semibold' : 'text-ink-400') + '">' + (t.due ? esc((late ? D.tk.late + ' · ' : '') + when(t.due.replace('T', ' '))) : '') + (t.lead ? ' · <button type="button" class="link" data-sd-open="' + t.lead + '">' + esc(t.lead_name) + '</button>' : '') + '</span></span>' +
        '<button type="button" class="opacity-0 group-hover:opacity-100 text-ink-400 hover:text-red-600" data-tdel="' + t.id + '">' + uic('trash', 'w-3.5 h-3.5') + '</button></div>'; }).join('') : '<p class="text-[11px] text-ink-400 px-1.5">' + esc(D.tk.none) + '</p>';
  }
  tasksDraw();
  var tf = document.getElementById('sd-task-f');
  if (tf) tf.addEventListener('submit', function (e) { e.preventDefault(); var fd = new FormData(tf); fd.append('action', 'task_add'); if (cur && dr.getAttribute('aria-hidden') === 'false') fd.append('lead', cur.id);
    post(fd).then(function (r) { if (!r.ok) return toast(r.error || T.err); tasks = r.tasks; tf.reset(); tasksDraw(); }); });
  document.addEventListener('click', function (e) {
    var d = e.target.closest('[data-tdone]'), x = e.target.closest('[data-tdel]');
    if (d) { var row = d.closest('div'); row.style.opacity = '.4'; post({ action: 'task_done', task: d.dataset.tdone }).then(function (r) { if (r.ok) { tasks = r.tasks; setTimeout(tasksDraw, 250); } }); }
    if (x) post({ action: 'task_del', task: x.dataset.tdel }).then(function (r) { if (r.ok) { tasks = r.tasks; tasksDraw(); } });
  });
  /* ---------- #89 ملاحظة التسليم لما المسؤول يتغير ---------- */
  var ownSel = form.elements.owner, hand = document.getElementById('ld-handoff');
  if (ownSel && hand) ownSel.addEventListener('change', function () { hand.classList.toggle('hidden', !cur || +ownSel.value === +(cur.owner || 0) || !+ownSel.value); });
  /* ---------- #88 منشن @ في الملاحظات: اقتراح أسماء الفريق ---------- */
  (function () {
    var inp = document.getElementById('ld-log-body'); if (!inp || !(D.mem || []).length) return;
    var pop = document.createElement('div'); pop.className = 'hidden absolute z-50 mt-1 w-48 rounded-xl border border-ink-200 bg-white shadow-xl p-1 text-sm'; inp.parentNode.style.position = 'relative'; inp.parentNode.appendChild(pop);
    inp.addEventListener('input', function () { var m = /(^|\s)@([\p{L}\d_]*)$/u.exec(inp.value.slice(0, inp.selectionStart)); if (!m) { pop.classList.add('hidden'); return; }
      var q = m[2].toLowerCase(), list = D.mem.filter(function (x) { return x.id !== D.me && x.n.toLowerCase().indexOf(q) > -1; }).slice(0, 6); if (!list.length) { pop.classList.add('hidden'); return; }
      pop.innerHTML = list.map(function (x) { return '<button type="button" class="w-full text-start px-2.5 py-1.5 rounded-lg hover:bg-ink-50" data-mn="' + esc(x.n.split(/\s+/)[0]) + '">@' + esc(x.n) + '</button>'; }).join(''); pop.classList.remove('hidden'); });
    pop.addEventListener('mousedown', function (e) { var b = e.target.closest('[data-mn]'); if (!b) return; e.preventDefault(); var pos = inp.selectionStart, before = inp.value.slice(0, pos).replace(/@[\p{L}\d_]*$/u, '@' + b.dataset.mn + ' '); inp.value = before + inp.value.slice(pos); inp.focus(); pop.classList.add('hidden'); });
    inp.addEventListener('blur', function () { setTimeout(function () { pop.classList.add('hidden'); }, 150); });
  })();
  /* ---------- #85 اتصال متتالي (Power dialer) ---------- */
  var dial = document.getElementById('ld-dialer');
  function dialList() { var today = D.today; return leads.filter(function (l) { return !closed(l) && l.phone && l.edit && (!l.next_at || l.next_at.slice(0, 10) <= today); }).sort(function (a, b) { return (b.score || 0) - (a.score || 0); }); }
  if (dial) { if (dialList().length) dial.classList.remove('hidden');
    dial.addEventListener('click', function () {
      var q = dialList(); if (!q.length) return toast(D.tk.dialNone); var i = 0;
      var w = document.createElement('div'); w.className = 'fixed inset-0 z-[80] flex items-end sm:items-center justify-center bg-ink-900/50 p-0 sm:p-4'; document.body.appendChild(w);
      function show() {
        if (i >= q.length) { w.innerHTML = '<div class="w-full sm:w-[440px] rounded-t-3xl sm:rounded-3xl bg-white p-8 text-center"><div class="text-lime-600 grid place-items-center mb-2">' + uic('check', 'w-12 h-12') + '</div><b>' + esc(D.tk.dialDone) + '</b><div class="mt-4"><button type="button" class="btn-primary" data-dx>' + esc(D.tk.stop) + '</button></div></div>'; return; }
        var l = q[i];
        w.innerHTML = '<div class="w-full sm:w-[460px] rounded-t-3xl sm:rounded-3xl bg-white shadow-2xl overflow-hidden"><div class="flex items-center gap-2 px-5 pt-4 text-xs text-ink-500"><span>' + esc(D.tk.of.replace('%s', i + 1).replace('%s', q.length)) + '</span><div class="flex-1 h-1.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full bg-brand-600" style="width:' + Math.round(i / q.length * 100) + '%"></div></div><button type="button" class="text-ink-400 hover:text-ink-900" data-dx>' + uic('x', 'w-5 h-5') + '</button></div>' +
          '<div class="p-5 text-center"><div class="mx-auto mb-2 w-fit">' + avatar(l, 'w-16 h-16') + '</div><b class="block text-lg text-ink-900">' + esc(l.name) + '</b><div class="text-sm text-ink-500">' + esc([l.title, l.company].filter(Boolean).join(' · ')) + '</div>' +
          (l.next_note ? '<div class="mt-2 text-xs rounded-lg bg-amber-50 text-amber-800 px-3 py-1.5 inline-block" dir="auto">' + esc(l.next_note) + '</div>' : '') +
          '<a href="tel:' + esc(l.phone) + '" class="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-lime-600 text-[#fff] py-3 text-lg font-bold" dir="ltr" data-dcall>' + uic('phone', 'w-5 h-5') + esc(l.phone) + '</a>' +
          '<input class="input mt-3 text-sm" id="dl-note" placeholder="' + esc(D.tk.noteP) + '" dir="auto">' +
          '<div class="grid grid-cols-2 gap-2 mt-3">' + Object.keys(D.ocs).map(function (k) { return '<button type="button" class="btn-ghost !justify-center" data-doc="' + k + '">' + esc(D.ocs[k]) + '</button>'; }).join('') + '<button type="button" class="btn-ghost !justify-center text-ink-500" data-dskip>' + esc(D.tk.skip) + '</button></div></div></div>';
      }
      show();
      w.addEventListener('click', function (e) {
        if (e.target === w || e.target.closest('[data-dx]')) { w.remove(); draw(); return; }
        if (e.target.closest('[data-dskip]')) { i++; show(); return; }
        var b = e.target.closest('[data-doc]'); if (!b) return; var l = q[i]; b.disabled = true;
        post({ action: 'log', id: l.id, kind: 'call', outcome: b.dataset.doc, body: (document.getElementById('dl-note') || {}).value || '' }).then(function (r) { if (r.ok) Object.assign(l, r.lead); i++; show(); }).catch(function () { b.disabled = false; toast(T.err); });
      });
    });
  }
  /* ---------- #63 دمج عميلين بشاشة مقارنة ---------- */
  var mbtn = document.getElementById('ld-b-merge');
  if (mbtn) mbtn.addEventListener('click', function () {
    var ids = selIds(); if (ids.length !== 2) return toast(D.mg.need2);
    post({ action: 'merge_pair', a: ids[0], b: ids[1] }).then(function (r) {
      if (!r.ok) return toast(r.error || T.err);
      var A = r.a, B = r.b, M = D.mg, keys = ['name', 'company', 'title', 'email', 'phone', 'stage', 'value', 'owner', 'note'];
      var col = { stage: 'stage', value: 'deal_value', owner: 'owner_id' };
      function show(l, k) { var v = l[k]; if (k === 'stage') v = D.stages[v] || v; if (k === 'owner') v = (D.members || {})[v] || '—'; return v === '' || v == null || v === 0 ? '—' : v; }
      var w = document.createElement('div'); w.className = 'fixed inset-0 z-[70] flex items-center justify-center bg-ink-900/40 p-4';
      w.innerHTML = '<form class="w-[min(720px,96vw)] max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl"><div class="flex items-center px-5 h-14 border-b border-ink-200"><b class="flex-1">' + uic('link', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(M.t) + '</b><button type="button" data-x class="p-2 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div>' +
        '<div class="p-5 space-y-3"><p class="text-xs text-ink-500">' + esc(M.hint) + '</p><div class="grid grid-cols-[110px_1fr_1fr] gap-2 text-sm items-center">' +
        '<span></span>' + [A, B].map(function (l, i) { return '<label class="flex items-center gap-2 rounded-xl border border-ink-200 p-2 font-semibold"><input type="radio" name="keep" value="' + l.id + '"' + (i === 0 ? ' checked' : '') + '> ' + esc(M.keep) + ' <span class="text-[11px] font-normal text-ink-500">' + esc(M.acts.replace('%s', r.acts[i])) + '</span></label>'; }).join('') +
        keys.map(function (k) { var same = String(show(A, k)) === String(show(B, k)); return '<span class="text-xs text-ink-500">' + esc(M.f[k]) + '</span>' + [A, B].map(function (l, i) { return '<label class="flex items-start gap-2 rounded-lg px-2 py-1 ' + (same ? 'opacity-60' : 'hover:bg-ink-50') + '"><input type="radio" name="pick_' + k + '" value="' + (i ? 'b' : 'a') + '"' + ((i === 0 && show(A, k) !== '—') || (i === 1 && show(A, k) === '—' && show(B, k) !== '—') ? ' checked' : '') + (same ? ' disabled' : '') + '><span class="break-words min-w-0" dir="auto">' + esc(show(l, k)) + '</span></label>'; }).join(''); }).join('') +
        '</div></div><div class="flex justify-end gap-2 px-5 py-3 border-t border-ink-200"><button type="button" data-x class="btn-ghost">' + esc(T.cancel || '✕') + '</button><button class="btn-primary">' + esc(M.go) + '</button></div></form>';
      document.body.appendChild(w);
      w.addEventListener('click', function (e) { if (e.target === w || e.target.closest('[data-x]')) w.remove(); });
      w.querySelector('form').addEventListener('submit', function (e) {
        e.preventDefault(); var f = e.target, keep = +f.keep.value, fd = new FormData(); fd.append('action', 'merge'); fd.append('a', A.id); fd.append('b', B.id); fd.append('keep', keep);
        keys.forEach(function (k) { var c = f.querySelector('[name=pick_' + k + ']:checked'); if (!c) return; var fromB = c.value === 'b', isDrop = (keep === A.id) ? fromB : !fromB; fd.append('pick[' + (col[k] || k) + ']', isDrop ? 'drop' : 'keep'); });
        post(fd).then(function (j) {
          if (!j.ok) return toast(j.error || T.err);
          leads = leads.filter(function (x) { return x.id !== j.gone; }); D.leads = leads; leads.forEach(function (x, i) { if (x.id === j.lead.id) leads[i] = Object.assign(x, j.lead); });
          sel = {}; bbDraw(); w.remove(); draw(); toast(M.done);
        });
      });
    });
  });
  list.addEventListener('change', function (e) { var c = e.target.closest('.ld-sel'); if (!c) return; sel[+c.dataset.sid] = c.checked; c.closest('.ld-row').classList.toggle('bg-brand-50/60', c.checked); bbDraw(); });
  document.getElementById('ld-all').addEventListener('change', function () { var on = this.checked; leads.filter(match).forEach(function (l) { if (l.edit) sel[l.id] = on; }); draw(); bbDraw(); });
  document.getElementById('ld-b-clear').addEventListener('click', function () { sel = {}; document.getElementById('ld-all').checked = false; draw(); bbDraw(); });
  function bulk(data, after) {
    var fd = new FormData(); fd.append('action', 'bulk'); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); selIds().forEach(function (id) { fd.append('ids[]', id); });
    bb.classList.add('opacity-60', 'pointer-events-none');
    return post(fd).then(function (r) {
      bb.classList.remove('opacity-60', 'pointer-events-none');
      if (!r.ok) return toast(r.error || T.err);
      toast(T.bDone.replace('%s', r.done).replace('%s', r.skip) + (r.error ? '\n' + r.error : ''));
      location.reload();
    }).catch(function () { bb.classList.remove('opacity-60', 'pointer-events-none'); toast(T.err); });
  }
  document.getElementById('ld-b-stage').addEventListener('change', function () { var v = this.value, el = this; if (!v) return; if (v === 'lost') { askLost().then(function (r) { if (r) bulk({ op: 'stage', stage: v, lost_reason: r }); else el.value = ''; }); return; } bulk({ op: 'stage', stage: v }); });
  var bo = document.getElementById('ld-b-owner'); if (bo) bo.addEventListener('change', function () { if (this.value !== '') bulk({ op: 'owner', owner: this.value }); });
  document.getElementById('ld-b-tag').addEventListener('click', function () { var t = prompt(T.askTag); if (t && t.trim()) bulk({ op: 'tag', tag: t.trim() }); });
  var be = document.getElementById('ld-b-enrich'); if (be) be.addEventListener('click', function () { var n = Math.min(25, selIds().length); if (confirm(T.bConfirm.replace('%s', n))) bulk({ op: 'enrich' }); });
  var bs = document.getElementById('ld-b-seq'); if (bs) bs.addEventListener('click', function () {
    function go() {
      if (!seqData.seqs.length) return toast(T.noSeq); if (!seqData.accounts.length) return toast(T.noAcc);
      var names = seqData.seqs.map(function (s, i) { return (i + 1) + '. ' + s.name; }).join('\n');
      var k = parseInt(prompt(T.pickSeq + '\n' + names, '1'), 10); var s = seqData.seqs[k - 1]; if (!s) return;
      bulk({ op: 'seq', seq: s.id, seq_name: s.name, acc: seqData.accounts[0].id });
    }
    if (seqData) return go();
    post({ action: 'seqs', id: selIds()[0] }).then(function (r) { if (!r.ok) return toast(r.error || T.err); seqData = r; go(); });
  });
  var stl = document.getElementById('ld-stale'); if (stl) stl.addEventListener('click', function () {
    if (!confirm(T.bConfirm.replace('%s', 25))) return; stl.disabled = true;
    post({ action: 'enrich_stale' }).then(function (r) { stl.disabled = false; if (!r.ok) return toast(r.error || T.err); toast(T.bDone.replace('%s', r.done).replace('%s', r.skip) + (r.error ? '\n' + r.error : '')); if (r.done) location.reload(); });
  });

  /* ---------- شغل النهارده: المستحقين بالترتيب (الأهم الأول)، وزرار "التالي" ---------- */
  var qBtn = document.getElementById('ld-queue'), qNext = document.getElementById('ld-q-next'), queue = [], qi = -1;
  function buildQueue() { return leads.filter(function (l) { return l.edit && ['over', 'today'].indexOf(dueState(l)) > -1; }).sort(function (a, b) { return (b.score - a.score) || (dueState(a) === 'over' ? -1 : 1); }).map(function (l) { return l.id; }); }
  function qDraw() { var n = buildQueue().length; qBtn.classList.toggle('hidden', !n); document.getElementById('ld-queue-n').textContent = n; }
  function qShow() {
    if (qi < 0 || qi >= queue.length) { qNext.classList.add('hidden'); return; }
    qNext.classList.remove('hidden'); qNext.textContent = qi + 1 < queue.length ? D.qNext.replace('%s', qi + 2).replace('%s', queue.length) : D.qLast;
  }
  qBtn.addEventListener('click', function () { queue = buildQueue(); qi = 0; if (!queue.length) return; open(queue[0]); qShow(); });
  qNext.addEventListener('click', function () {
    qi++;
    if (qi >= queue.length) { qi = -1; qNext.classList.add('hidden'); closeModal(dr); toast(D.qDone); qDraw(); return; }
    open(queue[qi]); qShow();
  });
  dr.querySelectorAll('[data-close]').forEach(function (x) { x.addEventListener('click', function () { qi = -1; qNext.classList.add('hidden'); qDraw(); }); });
  var _draw0 = draw; draw = function () { _draw0(); qDraw(); };

  /* ---------- الأزرار السريعة في الصف ---------- */
  list.addEventListener('click', function (e) {
    var q = e.target.closest('[data-qa]'); if (!q) return;
    e.stopPropagation(); var l = byId(+q.closest('[data-id]').dataset.id); if (!l) return;
    if (q.dataset.qa === 'pin') { e.preventDefault(); post({ action: 'pin', id: l.id }).then(function (r) { if (r.ok) { Object.assign(l, r.lead); leads.sort(function (a, b) { return (b.pin || 0) - (a.pin || 0); }); draw(); } }); return; }
    if (q.dataset.qa === 'later') {
      e.preventDefault();
      post({ action: 'next_quick', id: l.id, days: 1 }).then(function (r) { if (!r.ok) return toast(r.error || T.err); Object.assign(l, r.lead); draw(); toast(T.laterDone); });
      return;
    }
    if (!l.edit) return;
    if (q.dataset.qa === 'call') { setTimeout(function () { open(l.id); setTab('ct'); var o = document.getElementById('ld-oc'); o.classList.remove('hidden'); o.classList.add('flex'); }, 300); return; }
    post({ action: 'log', id: l.id, kind: q.dataset.qa, body: '' }).then(function (r) { if (r.ok) { Object.assign(l, r.lead); draw(); toast(T.logged); } });
  }, true);

  board.addEventListener('click', function (e) {
    var q = e.target.closest('[data-qa]'); if (!q) return; e.stopPropagation();
    var l = byId(+q.closest('[data-id]').dataset.id); if (!l || !l.edit) return;
    if (q.dataset.qa === 'call') return;
    post({ action: 'log', id: l.id, kind: q.dataset.qa, body: '' }).then(function (r) { if (r.ok) { Object.assign(l, r.lead); draw(); toast(T.logged); } });
  }, true);

  /* ---------- شرايح الفلاتر النشطة ---------- */
  var chips = document.getElementById('ld-chips'), filt = document.getElementById('ld-filt');
  function drawChips() {
    var c = [];
    if (f.src) c.push(['src', D.sources[f.src]]);
    if (f.own !== '') c.push(['own', f.own === '0' ? T.noowner : (+f.own === D.me ? T.mine : (D.members[f.own] || ''))]);
    if (f.tag) c.push(['tag', '🏷 ' + f.tag]);
    if (f.due) c.push(['due', '⏰ ' + T.dueOnly]);
    if (f.cold) c.push(['cold', '🧊 ' + T.cold]);
    if (f.q) c.push(['q', '⌕ ' + f.q]);
    chips.innerHTML = c.map(function (x) { return '<span class="ld-chip">' + esc(x[1]) + '<button type="button" data-unf="' + x[0] + '" class="text-brand-500 hover:text-red-600" aria-label="✕">✕</button></span>'; }).join('');
    var n = c.filter(function (x) { return x[0] !== 'q'; }).length, fn = document.getElementById('ld-filt-n');
    fn.textContent = n; fn.classList.toggle('hidden', !n);
  }
  function unf(k) {
    if (k === 'src') { f.src = ''; document.getElementById('ld-src').value = ''; }
    if (k === 'own') { f.own = ''; document.getElementById('ld-own').value = ''; }
    if (k === 'tag') { f.tag = ''; var tg = document.getElementById('ld-tag'); if (tg) tg.value = ''; }
    if (k === 'due') { f.due = false; dueBox.checked = false; }
    if (k === 'cold') { f.cold = false; var cb = document.getElementById('ld-cold'); if (cb) cb.classList.remove('ring-2', 'ring-sky-400'); }
    if (k === 'q') { f.q = ''; document.getElementById('ld-q').value = ''; }
  }
  chips.addEventListener('click', function (e) { var b = e.target.closest('[data-unf]'); if (b) { unf(b.dataset.unf); draw(); } });
  document.getElementById('ld-filt-clear').addEventListener('click', function () { ['src', 'own', 'tag', 'due', 'cold'].forEach(unf); filt.open = false; draw(); });
  document.addEventListener('click', function (e) { if (filt.open && !filt.contains(e.target)) filt.open = false; });
  var _draw1 = draw; draw = function () { _draw1(); drawChips(); };

  /* ---------- اختصارات الكيبورد: / بحث، n عميل جديد، j/k التالي والسابق جوه الدرج ---------- */
  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase(); if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.metaKey || e.ctrlKey || e.altKey) return;
    if (e.key === '/') { e.preventDefault(); document.getElementById('ld-q').focus(); }
    else if (e.key === 'n' && dr.classList.contains('hidden')) { e.preventDefault(); document.getElementById('ld-add-btn').click(); }
    else if ((e.key === 'j' || e.key === 'k') && !dr.classList.contains('hidden') && cur) {
      var ids = leads.filter(match).map(function (l) { return l.id; }), ix = ids.indexOf(cur.id);
      var nx = ids[ix + (e.key === 'j' ? 1 : -1)]; if (nx) open(nx);
    }
  });

  /* ---------- العروض الجاهزة (الكل / بتوعي / مستحقين / سخنين / باردين / جداد) ---------- */
  var todayKey = (D.now || '').slice(0, 10);
  var views = {
    all: function () { return true; },
    mine: function (l) { return l.owner === D.me; },
    due: function (l) { return ['over', 'today'].indexOf(dueState(l)) > -1; },
    hot: function (l) { return !closed(l) && l.score >= 60; },
    cold: function (l) { return isCold(l); },
    fresh: function (l) { return srvDate(l.created).toLocaleDateString('en-CA', D.tz ? { timeZone: D.tz } : {}) === todayKey; },
    unowned: function (l) { return !l.owner && !closed(l); },
    pin: function (l) { return !!l.pin; }
  };
  var _match0 = match; match = function (l) { return views[vMode](l) && _match0(l); };
  function setView(k) {
    vMode = views[k] ? k : 'all'; limit = 50;
    try { localStorage.setItem('ld-vmode', vMode); } catch (e) {}
    document.querySelectorAll('.ld-vtab').forEach(function (b) { b.classList.toggle('on', b.dataset.view === vMode); });
    draw();
  }
  document.querySelectorAll('.ld-vtab').forEach(function (b) { b.addEventListener('click', function () { setView(b.dataset.view); }); });
  function vCounts() { document.querySelectorAll('.ld-vtab').forEach(function (b) { var fn = views[b.dataset.view]; var n = leads.filter(function (l) { return fn(l); }).length; b.querySelector('.ld-vn').textContent = n; }); }
  var _draw2 = draw; draw = function () { _draw2(); vCounts(); };
  document.querySelector('#ld-more button').addEventListener('click', function () { limit += 100; draw(); });
  document.getElementById('ld-none-clear').addEventListener('click', function () { ['src', 'own', 'tag', 'due', 'cold', 'q'].forEach(unf); setStage(''); setView('all'); });

  /* ---------- تعديل من الصف: المرحلة والمتابعة (بوب أب صغير) ---------- */
  var pop = null;
  function closePop() { if (pop) { pop.remove(); pop = null; } }
  function askLost() {
    return new Promise(function (res) {
      var w = document.createElement('div'); w.className = 'fixed inset-0 z-[90] grid place-items-center bg-ink-900/40 p-4';
      w.innerHTML = '<div class="card p-4 w-full max-w-sm space-y-2"><div class="font-semibold text-ink-900">✕ ' + esc(T.lostQ) + '</div><div class="grid grid-cols-2 gap-1.5">' + Object.keys(T.lostR).map(function (k) { return '<button type="button" class="chip text-xs justify-center" data-lr="' + k + '">' + esc(T.lostR[k]) + '</button>'; }).join('') + '</div><button type="button" class="text-xs text-ink-500 w-full" data-lr="">' + esc(T.undo) + '</button></div>';
      document.body.appendChild(w);
      w.addEventListener('click', function (e) { var b = e.target.closest('[data-lr]'); if (!b && e.target !== w) return; w.remove(); res(b ? b.dataset.lr : ''); });
    });
  }
  window.ldAskLost = askLost;
  function changeStage(l, s, reason) {
    var old = l.stage; if (old === s) return;
    if (s === 'lost' && reason === undefined) { askLost().then(function (r) { if (r) changeStage(l, s, r); else draw(); }); return; }
    l.stage = s; draw();
    post({ action: 'stage', id: l.id, stage: s, lost_reason: reason || '' }).then(function (r) {
      if (!r.ok) { l.stage = old; draw(); return toast(r.error || T.err); }
      Object.assign(l, r.lead); draw();
      toast(T.stageTo.replace('%s', D.stages[s]), 0, { label: T.undo, undo: function () { post({ action: 'stage', id: l.id, stage: old }).then(function (r2) { if (r2.ok) { Object.assign(l, r2.lead); draw(); } }); } });
    }).catch(function () { l.stage = old; draw(); });
  }
  list.addEventListener('click', function (e) {
    var t = e.target.closest('[data-pop]'); if (!t) return;
    e.stopPropagation(); closePop();
    var l = byId(+t.closest('[data-id]').dataset.id); if (!l) return;
    pop = document.createElement('div'); pop.className = 'ld-pop';
    if (t.dataset.pop === 'stage') pop.innerHTML = Object.keys(D.stages).map(function (k) { return '<button type="button" data-ps="' + k + '"><span class="w-2 h-2 rounded-full ' + stageColor[k].split(' ')[0] + '"></span>' + esc(D.stages[k]) + (k === l.stage ? ' ✓' : '') + '</button>'; }).join('');
    else pop.innerHTML = [['today', T.nToday], ['tomorrow', T.nTom], ['3d', T.n3], ['week', T.nWeek], ['none', T.nNone]].map(function (x) { return '<button type="button" data-pn="' + x[0] + '">' + (x[0] === 'none' ? '✕ ' : '' + uic('clock', 'w-4 h-4 inline-block align-[-3px]') + '') + esc(x[1]) + '</button>'; }).join('');
    var r = t.getBoundingClientRect(); pop.style.position = 'fixed'; pop.style.left = Math.max(8, Math.min(innerWidth - 190, r.left)) + 'px'; pop.style.top = '-999px';
    document.body.appendChild(pop);
    var ph = pop.offsetHeight; pop.style.top = (r.bottom + 4 + ph > innerHeight - 8 ? Math.max(8, r.top - ph - 4) : r.bottom + 4) + 'px'; // لو مفيش مكان تحت، يفتح لفوق
    pop.addEventListener('click', function (ev) {
      var b = ev.target.closest('button'); if (!b) return; closePop();
      if (b.dataset.ps) return changeStage(l, b.dataset.ps);
      post({ action: 'next_quick', id: l.id, when: b.dataset.pn, days: 1 }).then(function (r2) { if (!r2.ok) return toast(r2.error || T.err); Object.assign(l, r2.lead); draw(); toast(T.nSet); });
    });
  }, true);
  document.addEventListener('click', function (e) { if (pop && !pop.contains(e.target)) closePop(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePop(); });
  window.addEventListener('scroll', closePop, true);

  /* ---------- Shift + ضغطة = تحديد مجموعة ---------- */
  var lastSel = null;
  list.addEventListener('click', function (e) {
    var c = e.target.closest('.ld-sel'); if (!c) return;
    var id = +c.dataset.sid;
    if (e.shiftKey && lastSel !== null) {
      var ids = leads.filter(match).slice(0, limit).map(function (l) { return l.id; }), a = ids.indexOf(lastSel), b = ids.indexOf(id);
      if (a > -1 && b > -1) { ids.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (x) { var L = byId(x); if (L && L.edit) sel[x] = true; }); draw(); bbDraw(); }
    }
    lastSel = id;
  });

  try { var vm = localStorage.getItem('ld-vmode'); if (vm) vMode = views[vm] ? vm : 'all'; } catch (e) {}
  document.querySelectorAll('.ld-vtab').forEach(function (b) { b.classList.toggle('on', b.dataset.view === vMode); });

  /* ---------- تنبيهات العملاء ---------- */
  var nfs = document.getElementById('notifs');
  if (nfs) {
    var ns = document.getElementById('nf-seen');
    if (ns) ns.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); post({ action: 'notifs_seen' }).then(function () { nfs.querySelectorAll('a').forEach(function (a) { a.classList.remove('font-medium', 'bg-rose-50/40', 'text-ink-900'); a.classList.add('text-ink-500'); }); ns.classList.add('hidden'); var b = nfs.querySelector('.bg-rose-600'); if (b) b.remove(); }); });
    nfs.addEventListener('click', function (e) { var a = e.target.closest('[data-nf-lead]'); if (!a) return; e.preventDefault(); var id = +a.dataset.nfLead; if (byId(id)) open(id); });
    if (location.hash === '#notifs') nfs.open = true;
  }

  // قايمة الأدوات بتتقفل لما تختار حاجة أو تدوس بره
  var tools = document.getElementById('ld-tools');
  if (tools) { tools.addEventListener('click', function (e) { if (e.target.closest('button,a') && !e.target.closest('summary')) tools.open = false; }); document.addEventListener('click', function (e) { if (!tools.contains(e.target)) tools.open = false; }); }

  draw();
  if (/[?&]go=queue/.test(location.search)) setTimeout(function () { var qb = document.getElementById('ld-queue'); if (qb && !qb.classList.contains('hidden')) qb.click(); }, 300);
  var m = location.hash.match(/^#lead-(\d+)$/); if (m) open(+m[1]);
  if (location.hash === '#add') { var ab = document.getElementById('ld-add-btn'); if (ab) ab.click(); }
  if (location.hash === '#due') { var vt = document.querySelector('[data-view=due]'); if (vt) vt.click(); }
})();
