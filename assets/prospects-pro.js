/* العملاء المستهدفين: لوحة المراحل (سحب وإفلات)، طابور الاتصال، عميل جديد، دمج المكررين، Apollo بالجملة */
(function () {
  var C = window.PR, LP = window.LeadPopup, root = document.getElementById('pr'); if (!C || !C.P || !LP || !root || !window.PRList) return;
  var P = C.P, T = P.T, list = window.PRList, L = C.L;
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function api(d) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(d).forEach(function (k) { [].concat(d[k]).forEach(function (v) { f.append(k, v); }); });
    return fetch(P.api, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
  function jd(r) { return LP.jd(r); }
  function vis() { return list.rows().filter(function (r) { return r.style.display !== 'none'; }); }
  function dlg(id) { var d = $(id); d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) d.close(); }); return d; }
  var GR = [['#2563eb', '#06b6d4'], ['#7c3aed', '#ec4899'], ['#059669', '#84cc16'], ['#ea580c', '#f59e0b'], ['#0f766e', '#22d3ee'], ['#be123c', '#f97316']];
  function grad(n) { var h = 0; for (var i = 0; i < n.length; i++) h = (h * 31 + n.charCodeAt(i)) >>> 0; var g = GR[h % GR.length]; return 'linear-gradient(135deg,' + g[0] + ',' + g[1] + ')'; }
  function num(v) { return Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }); }

  /* ---------- لوحة المراحل ---------- */
  var board = $('pr-board'), table = root.querySelector('.mk-table'), view = 'list';
  try { view = localStorage.getItem('pr-view') || 'list'; } catch (e) {}
  var SCOL = { new: '#64748b', contacted: '#2563eb', qualified: '#7c3aed', proposal: '#d97706', negotiation: '#ea580c', won: '#16a34a', lost: '#dc2626' };
  function card(r) { var d = jd(r), heat = d.stage === 'won' ? 100 : d.stage === 'lost' ? 0 : +(d.score || 0), hc = heat >= 70 ? '#ef4444' : heat >= 40 ? '#f59e0b' : '#38bdf8';
    return '<article class="pr-card" draggable="true" data-id="' + d.id + '" tabindex="0"><div class="flex items-center gap-2"><span class="pr-av" style="background:' + grad(d.name || '?') + '">' + esc((d.name || '?').trim().charAt(0).toUpperCase()) + '</span>' +
      '<div class="min-w-0 flex-1"><b class="block truncate text-sm text-ink-900" dir="auto">' + esc(d.name) + '</b><small class="block truncate text-[11px] text-ink-500" dir="auto">' + esc(d.company || d.title || d.src || '') + '</small></div><span class="pr-heat" style="--c:' + hc + '">' + heat + '</span></div>' +
      '<div class="flex flex-wrap items-center gap-1 mt-2">' + (d.next ? '<span class="pr-tag' + (d.due ? ' due' : '') + '">' + ic('calendar', 'w-3 h-3') + esc(d.next.slice(0, 5)) + '</span>' : '') + (d.value ? '<span class="pr-tag val">' + esc(num(d.value)) + '</span>' : '') +
      '<span class="ms-auto flex gap-0.5">' + (d.phone ? '<a class="pr-ca" href="tel:' + esc(d.phone) + '" data-blog="call">' + ic('phone', 'w-3.5 h-3.5') + '</a>' : '') + (d.wa ? '<a class="pr-ca" href="https://wa.me/' + esc(d.wa) + '" target="_blank" rel="noopener" data-blog="wa">' + ic('chat', 'w-3.5 h-3.5') + '</a>' : '') + '</span></div></article>'; }
  function paint() { if (view !== 'board') return; var rows = vis(), by = {}; Object.keys(P.stages).forEach(function (k) { by[k] = []; }); rows.forEach(function (r) { var s = jd(r).stage; (by[s] = by[s] || []).push(r); });
    var sx = board.scrollLeft;
    board.innerHTML = Object.keys(P.stages).map(function (k) { var sum = by[k].reduce(function (a, r) { return a + (+jd(r).value || 0); }, 0);
      return '<section class="pr-col" data-col="' + k + '" style="--c:' + (SCOL[k] || '#64748b') + '"><header><i></i><b>' + esc(P.stages[k]) + '</b><span>' + by[k].length + '</span>' + (sum ? '<em>' + esc(num(sum)) + '</em>' : '') + '</header><div class="pr-colb">' +
        (by[k].length ? by[k].slice(0, 150).map(card).join('') : '<p class="pr-drop">' + esc(T.drop) + '</p>') + '</div></section>'; }).join('');
    board.scrollLeft = sx; }
  function setView(v) { view = v; try { localStorage.setItem('pr-view', v); } catch (e) {} root.querySelectorAll('.pr-view [data-view]').forEach(function (b) { b.classList.toggle('on', b.dataset.view === v); });
    table.classList.toggle('hidden', v === 'board'); board.classList.toggle('hidden', v !== 'board'); paint(); }
  root.querySelector('.pr-view').addEventListener('click', function (e) { var b = e.target.closest('[data-view]'); if (b) setView(b.dataset.view); });
  var pt; function later() { clearTimeout(pt); pt = setTimeout(function () { paint(); qCount(); dups(); }, 60); }
  root.addEventListener('input', later); root.addEventListener('change', later); root.addEventListener('click', function (e) { if (e.target.closest('[data-mk-stat], #pf-reset')) later(); });
  document.addEventListener('change', function (e) { if (e.target.closest('#pr-filters')) later(); });
  function rowOf(id) { return root.querySelector('[data-mk-row][data-id="' + id + '"]'); }
  board.addEventListener('click', function (e) { var a = e.target.closest('a[data-blog]'); if (a) { var f = new FormData(), r0 = rowOf(a.closest('.pr-card').dataset.id); f.append('_csrf', C.csrf); f.append('action', 'log'); f.append('id', jd(r0).id); f.append('kind', a.dataset.blog);
      fetch(C.selfUrl, { method: 'POST', body: f, credentials: 'same-origin' }).catch(function () {}); return; } if (e.target.closest('a')) return; var c = e.target.closest('.pr-card'); if (c) LP.open(rowOf(c.dataset.id)); });
  board.addEventListener('keydown', function (e) { var c = e.target.closest('.pr-card'); if (c && e.key === 'Enter') LP.open(rowOf(c.dataset.id)); });
  // سحب وإفلات بين المراحل
  var drag = null;
  board.addEventListener('dragstart', function (e) { var c = e.target.closest('.pr-card'); if (!c) return; drag = c; c.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', c.dataset.id); } catch (x) {} });
  board.addEventListener('dragend', function () { if (drag) drag.classList.remove('dragging'); drag = null; board.querySelectorAll('.pr-col.over').forEach(function (c) { c.classList.remove('over'); }); });
  board.addEventListener('dragover', function (e) { var col = e.target.closest('.pr-col'); if (!col || !drag) return; e.preventDefault(); board.querySelectorAll('.pr-col.over').forEach(function (c) { if (c !== col) c.classList.remove('over'); }); col.classList.add('over'); });
  board.addEventListener('drop', function (e) { var col = e.target.closest('.pr-col'); if (!col || !drag) return; e.preventDefault(); col.classList.remove('over'); var st = col.dataset.col, r = rowOf(drag.dataset.id), d = jd(r); if (d.stage === st) return;
    if (st === 'lost') { LP.open(r); setTimeout(function () { var b = document.querySelector('#pd-stages [data-stg="lost"]'); if (b) b.click(); }, 250); return; }
    col.querySelector('.pr-colb').prepend(drag); var ph = col.querySelector('.pr-drop'); if (ph) ph.remove(); drag.classList.add('pr-land');
    LP.bulk([d.id], 'stage', { stage: st }).then(function (j) { if (!j.ok) { toast(j.error || L.err, 'error'); return paint(); } LP.upd(r, 'stage', st); var pill = r.querySelector('[data-stage-pill]'); if (pill) pill.textContent = P.stages[st]; r.dataset.sStage = st;
      if (st === 'won') LP.confetti(); toast(P.stages[st] + ' ✓'); paint(); }); });
  LP.host({ rows: vis, onChange: function () { later(); }, onDelete: function (r) { list.remove(r); later(); } });
  setView(view);

  /* ---------- طابور الاتصال ---------- */
  function qRows() { return vis().filter(function (r) { var d = jd(r); return d.phone && d.stage !== 'won' && d.stage !== 'lost'; }).sort(function (a, b) { var x = jd(a), y = jd(b); return (y.due || 0) - (x.due || 0) || (y.score || 0) - (x.score || 0); }); }
  function qCount() { var n = qRows().length, b = root.querySelector('.pr-qn'); b.textContent = n || ''; $('pr-queue').disabled = !n; }
  $('pr-queue').addEventListener('click', function () { var q = qRows(); if (!q.length) return toast(T.qNone, 'error'); LP.queue(q); });
  qCount(); if (P.queue) setTimeout(function () { $('pr-queue').click(); }, 400);

  /* ---------- عميل جديد ---------- */
  var ad = dlg('pr-add'), af = $('pr-add-f');
  $('pr-add-btn').addEventListener('click', function () { af.reset(); $('pr-add-dup').classList.add('hidden'); ad.showModal(); af.name.focus(); });
  function norm(p) { return String(p || '').replace(/\D/g, '').slice(-9); }
  af.addEventListener('input', function () { var ph = norm(af.phone.value), em = af.email.value.trim().toLowerCase(), hit = null;
    if (ph.length >= 8 || em.indexOf('@') > 0) list.rows().some(function (r) { var d = jd(r); if ((ph.length >= 8 && norm(d.phone) === ph) || (em && (d.email || '').toLowerCase() === em)) { hit = d; return true; } });
    var p = $('pr-add-dup'); p.classList.toggle('hidden', !hit); if (hit) p.textContent = T.dupFound.replace('%s', hit.name); });
  af.addEventListener('submit', function (e) { e.preventDefault(); var b = af.querySelector('button'); b.disabled = true;
    api({ action: 'add', pl: window.PR && PR.pl || 'sales', name: af.name.value, phone: af.phone.value, email: af.email.value, company: af.company.value, title: af.title.value }).then(function (j) { b.disabled = false; if (!j.ok) return toast(j.error || L.err, 'error');
      toast(T.added); location.href = C.selfUrl + (window.PR && PR.pl && PR.pl !== 'sales' ? '&pl=' + encodeURIComponent(PR.pl) : '') + '&lead=' + j.lead.id; }).catch(function () { b.disabled = false; toast(L.err, 'error'); }); });

  /* ---------- دمج المكررين ---------- */
  var pairs = [], pi = 0, mergedAny = false, mg = dlg('pr-merge'), keep = 'a', take = {};
  function dups() { var seen = {}, out = []; list.rows().forEach(function (r) { var d = jd(r); [d.phone ? 'p' + norm(d.phone) : '', d.email ? 'e' + d.email.toLowerCase() : ''].forEach(function (k) { if (!k || k.length < 6) return;
      if (seen[k] && seen[k] !== r && !out.some(function (p) { return (p[0] === seen[k] && p[1] === r) || (p[1] === seen[k] && p[0] === r); })) out.push([seen[k], r]); else if (!seen[k]) seen[k] = r; }); });
    pairs = out; var b = $('pr-dups'); b.classList.toggle('hidden', !out.length); b.querySelector('span').textContent = T.dups.replace('%s', out.length); }
  var FL = ['name', 'phone', 'email', 'company', 'title', 'stage', 'country'];
  function mgPaint() { var p = pairs[pi]; if (!p) { mg.close(); toast(T.mgDone); if (mergedAny) location.reload(); return; } var a = jd(p[0]), b = jd(p[1]); take = {};
    $('pr-mg-pos').textContent = (pi + 1) + ' / ' + pairs.length;
    var side = function (d, k) { return '<div class="pr-mgc' + (keep === k ? ' keep' : '') + '" data-side="' + k + '"><button type="button" class="pr-mgk" data-keep="' + k + '">' + (keep === k ? '✓ ' : '') + esc(T.keep) + '</button>' +
      FL.map(function (f) { var v = f === 'stage' ? (P.stages[d.stage] || d.stage) : d[f], o = (k === 'a' ? b : a)[f], diff = String(v || '') !== String(o || '');
        return '<div class="pr-mgf' + (diff ? ' diff' : '') + (take[f] === k || (!take[f] && keep === k) ? ' pick' : '') + '" data-f="' + f + '" data-from="' + k + '"><small>' + esc(T.f[f]) + '</small><span dir="auto">' + esc(v || '—') + '</span></div>'; }).join('') + '</div>'; };
    $('pr-mg').innerHTML = '<div class="grid grid-cols-2 gap-3">' + side(a, 'a') + side(b, 'b') + '</div>'; }
  $('pr-mg').addEventListener('click', function (e) { var k = e.target.closest('[data-keep]'); if (k) { keep = k.dataset.keep; take = {}; return mgPaint(); }
    var f = e.target.closest('.pr-mgf.diff'); if (!f) return; take[f.dataset.f] = f.dataset.from;
    this.querySelectorAll('.pr-mgf[data-f="' + f.dataset.f + '"]').forEach(function (x) { x.classList.toggle('pick', x === f); }); });
  $('pr-dups').addEventListener('click', function () { if (!pairs.length) return; pi = 0; keep = 'a'; mgPaint(); mg.showModal(); });
  $('pr-mg-skip').addEventListener('click', function () { pi++; keep = 'a'; mgPaint(); });
  $('pr-mg-go').addEventListener('click', function () { var p = pairs[pi], a = jd(p[0]), b = jd(p[1]), kid = keep === 'a' ? a.id : b.id, btn = this, d = { action: 'merge', a: a.id, b: b.id, keep: kid };
    Object.keys(take).forEach(function (f) { if (take[f] !== keep) d['pick[' + (f === 'stage' ? 'stage' : f) + ']'] = 'drop'; });
    btn.disabled = true; api(d).then(function (j) { btn.disabled = false; if (!j.ok) return toast(j.error || L.err, 'error'); mergedAny = true; toast(T.merged);
      var gone = rowOf(j.gone); if (gone) list.remove(gone); pairs = pairs.filter(function (x) { return x[0] !== gone && x[1] !== gone; }); keep = 'a'; mgPaint(); }).catch(function () { btn.disabled = false; toast(L.err, 'error'); }); });
  dups();

  /* ---------- Apollo بالجملة ---------- */
  function ids() { return list.selected().map(function (r) { return jd(r).id; }); }
  if ($('pb-enrich')) $('pb-enrich').addEventListener('click', function () { var s = ids(), b = this; if (!s.length) return; b.disabled = true; b.classList.add('pd-busy');
    LP.bulk(s.slice(0, 25), 'enrich', {}).then(function (j) { b.disabled = false; b.classList.remove('pd-busy'); if (!j.ok) return toast(j.error || L.err, 'error'); toast(T.enriched.replace('%s', j.done != null ? j.done : s.length)); setTimeout(function () { location.reload(); }, 1200); }); });
  var sq = $('pr-seq') ? dlg('pr-seq') : null, seqPick = null;
  if ($('pb-seq')) $('pb-seq').addEventListener('click', function () { var s = ids(); if (!s.length) return; seqPick = null; $('pr-seq-go').disabled = true; $('pr-seq-l').innerHTML = '<p class="text-xs text-ink-500">' + esc(T.seqLoad) + '</p>'; sq.showModal();
    api({ action: 'seqs', id: s[0] }).then(function (j) { if (!j.ok) { $('pr-seq-l').innerHTML = '<p class="text-xs text-red-600">' + esc(j.error || L.err) + '</p>'; return; }
      $('pr-seq-l').innerHTML = (j.seqs || []).length ? j.seqs.map(function (q) { return '<button type="button" class="pd-plan w-full" data-seq="' + esc(q.id) + '" data-n="' + esc(q.name) + '"><b>' + esc(q.name) + '</b><span class="text-[10px] ' + (q.active ? 'text-lime-700' : 'text-ink-400') + '">' + (q.active ? '●' : '○') + '</span></button>'; }).join('') : '<p class="text-xs text-ink-500">' + esc(T.noSeq) + '</p>';
      $('pr-seq-acc').innerHTML = (j.accounts || []).map(function (a) { return '<option value="' + esc(a.id) + '">' + esc(a.email) + '</option>'; }).join(''); }); });
  if (sq) { $('pr-seq-l').addEventListener('click', function (e) { var b = e.target.closest('[data-seq]'); if (!b) return; seqPick = b; this.querySelectorAll('[data-seq]').forEach(function (x) { x.classList.toggle('ring-2', x === b); x.classList.toggle('ring-brand-500', x === b); }); $('pr-seq-go').disabled = !$('pr-seq-acc').value; });
    $('pr-seq-go').addEventListener('click', function () { var b = this; b.disabled = true; LP.bulk(ids(), 'seq', { seq: seqPick.dataset.seq, acc: $('pr-seq-acc').value, seq_name: seqPick.dataset.n }).then(function (j) { b.disabled = false; if (!j.ok) return toast(j.error || L.err, 'error'); sq.close(); toast(L.done2.replace('%s', j.done != null ? j.done : ids().length)); }); }); }
})();
