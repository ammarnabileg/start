/* أدوات نافذة العميل (اتنقلت من صفحة العملاء القديمة): نتيجة المكالمة، سبب الخسارة، الصفقات، المهام وخطة المتابعة،
   الإيميل المتتبع، البيانات والحقول المخصصة، الملخص الذكي، الملاحظة الصوتية والإملاء، كشف Apollo، الحذف، وطابور الاتصال */
(function () {
  var LP = window.LeadPopup, C = window.LP; if (!LP || !C || !C.X) return;
  var X = C.X, T = X.T, dl = document.getElementById('pr-dlg'), data = null;
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function api(d, url) { var f = d instanceof FormData ? d : new FormData(); if (!(d instanceof FormData)) Object.keys(d).forEach(function (k) { [].concat(d[k]).forEach(function (v) { f.append(k, v); }); });
    f.append('_csrf', C.csrf); return fetch(url || X.api, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
  function lead() { var r = LP.cur(); return r ? LP.jd(r) : {}; }
  function fail(j) { toast((j && j.error) || T.err, 'error'); }
  function num(v) { return Number(v || 0).toLocaleString(document.documentElement.lang || undefined, { maximumFractionDigits: 2 }); }
  function countUp(el, to) { var from = +(el.dataset.v || 0), t0 = performance.now(); el.dataset.v = to; (function f(t) { var p = Math.min(1, (t - t0) / 600), e = 1 - Math.pow(1 - p, 3); el.textContent = num(from + (to - from) * e); if (p < 1) requestAnimationFrame(f); })(t0); }
  function days(ymd) { if (!ymd) return null; var d = new Date(ymd.slice(0, 10) + 'T00:00:00'), n = new Date(); n.setHours(0, 0, 0, 0); return Math.round((d - n) / 864e5); }
  function busy(b, on) { if (!b) return; b.disabled = on; b.classList.toggle('pd-busy', on); }

  /* ---------- التابات ---------- */
  var tab = 'ct'; try { tab = localStorage.getItem('pd-tab') || 'ct'; } catch (e) {}
  function ink() { var b = dl.querySelector('#pd-tabs [data-pt].on'), k = dl.querySelector('.pd-tabs-ink'); if (!b || !k || !b.offsetWidth) return; var p = b.parentNode, br = b.getBoundingClientRect(), pr = p.getBoundingClientRect();
    k.style.width = br.width + 'px'; k.style.left = (br.left - pr.left + p.scrollLeft) + 'px'; }
  function setTab(k) { tab = k; try { localStorage.setItem('pd-tab', k); } catch (e) {}
    dl.querySelectorAll('#pd-tabs [data-pt]').forEach(function (b) { b.classList.toggle('on', b.dataset.pt === k); b.setAttribute('aria-selected', b.dataset.pt === k); }); var cb = dl.querySelector('#pd-tabs [data-pt].on'); if (cb && cb.scrollIntoView && dl.open) cb.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    dl.querySelectorAll('.pd-pane').forEach(function (p) { var on = p.dataset.pane === k; p.classList.toggle('hidden', !on); if (on) { p.classList.remove('pd-in-a'); void p.offsetWidth; p.classList.add('pd-in-a'); } }); ink(); }
  $('pd-tabs').addEventListener('click', function (e) { var b = e.target.closest('[data-pt]'); if (b) setTab(b.dataset.pt); });
  function badge(k, n, hot) { var i = dl.querySelector('[data-ptn="' + k + '"]'); if (!i) return; i.textContent = n || ''; i.classList.toggle('hot', !!hot); }

  /* ---------- نتيجة المكالمة ---------- */
  var OCC = { answered: ['#16a34a', '✓'], no_answer: ['#d97706', '⟳'], busy: ['#d97706', '⏸'], wrong: ['#dc2626', '✕'], later: ['#2563eb', '⏰'] };
  function ocShow() { var o = $('pd-oc'); setTab('ct');
    o.innerHTML = '<div class="flex items-center gap-2 mb-2"><span class="pd-ring">' + ic('phone', 'w-4 h-4') + '</span><b class="text-sm text-ink-900">' + esc(T.how) + '</b><button type="button" class="ms-auto text-ink-400 hover:text-ink-700" data-oc-x>✕</button></div><div class="grid grid-cols-2 sm:grid-cols-5 gap-1.5">' +
      Object.keys(X.oc).map(function (k) { var c = OCC[k] || ['#64748b', '•']; return '<button type="button" class="pd-ocb" data-oc="' + k + '" style="--c:' + c[0] + '"><b>' + c[1] + '</b><span>' + esc(X.oc[k]) + '</span></button>'; }).join('') + '</div>';
    o.classList.remove('hidden'); o.classList.remove('pd-in-a'); void o.offsetWidth; o.classList.add('pd-in-a'); }
  function ocHide() { $('pd-oc').classList.add('hidden'); }
  $('pd-oc').addEventListener('click', function (e) { if (e.target.closest('[data-oc-x]')) return ocHide(); var b = e.target.closest('[data-oc]'); if (!b) return; var ta = $('pd-note-f').b, oc = b.dataset.oc;
    busy(b, true); api({ action: 'log', id: lead().id, kind: 'call', outcome: oc, body: ta.value.trim() }).then(function (j) { busy(b, false); if (!j.ok) return fail(j);
      ta.value = ''; ocHide(); toast(T.saved); LP.touched(LP.cur()); LP.refresh().then(function () { if (Q.on) qAuto(); }); }).catch(function () { busy(b, false); fail(); }); });
  // أي اتصال (زرار المكالمة، كارت الاتصال، أو C) بيسأل عن النتيجة بدل ما يتسجل أعمى
  dl.addEventListener('click', function (e) {
    if (e.target.closest('[data-kind="call"]')) { e.stopImmediatePropagation(); e.preventDefault(); return ocShow(); }
    var a = e.target.closest('#pd-contact a[data-log="call"]'); if (a && !e.target.closest('[data-copy]')) { e.stopPropagation(); setTimeout(ocShow, 300); }
    var s = e.target.closest('[data-stg="lost"]'); if (s && lead().stage !== 'lost') { e.stopImmediatePropagation(); $('pd-lost').classList.remove('hidden'); }
  }, true);

  /* ---------- سبب الخسارة ---------- */
  $('pd-lost').addEventListener('click', function (e) { if (e.target.closest('[data-lr-x]')) return this.classList.add('hidden'); var b = e.target.closest('[data-lr]'); if (!b) return; var p = this;
    busy(b, true); api({ action: 'bulk', op: 'stage', stage: 'lost', lost_reason: b.dataset.lr, 'ids[]': lead().id }).then(function (j) { busy(b, false); if (!j.ok) return fail(j); p.classList.add('hidden'); toast(T.saved); LP.refresh(); }).catch(function () { busy(b, false); fail(); }); });

  /* ---------- الملخص الذكي ---------- */
  function intelPaint(it, d) { var el = $('pd-intel'); if (!it || !it.summary) { el.innerHTML = ''; return; }
    el.innerHTML = '<details class="pd-intel"' + (sessionStorage.getItem('pd-intel') === '0' ? '' : ' open') + '><summary>' + ic('spark', 'w-4 h-4 text-violet-600') + '<b>' + esc(T.intel) + '</b><span class="truncate">' + esc(it.summary) + '</span></summary>' +
      (it.best ? '<p class="mt-2 text-xs text-ink-600">' + ic('clock', 'w-3.5 h-3.5 inline') + ' ' + esc(T.best) + ': <b class="text-ink-900">' + esc(it.best) + '</b></p>' : '') +
      (it.first ? '<div class="mt-2"><span class="text-[11px] text-ink-500">' + esc(T.first) + '</span><p class="pd-first" contenteditable dir="auto">' + esc(it.first) + '</p><div class="flex gap-1.5 mt-1.5">' +
        (d.wa ? '<button type="button" class="pd-mini" data-fm="wa">' + ic('chat', 'w-3.5 h-3.5') + esc(T.useWa) + '</button>' : '') + '<button type="button" class="pd-mini" data-fm="copy">' + ic('copy', 'w-3.5 h-3.5') + esc(T.copy) + '</button></div></div>' : '') + '</details>';
    el.querySelector('details').addEventListener('toggle', function () { try { sessionStorage.setItem('pd-intel', this.open ? '1' : '0'); } catch (e) {} }); }
  $('pd-intel').addEventListener('click', function (e) { var b = e.target.closest('[data-fm]'); if (!b) return; var t = this.querySelector('.pd-first').innerText.trim(), d = lead();
    if (b.dataset.fm === 'copy') { if (navigator.clipboard) navigator.clipboard.writeText(t); toast(T.copied); return; }
    window.open('https://wa.me/' + d.wa + '?text=' + encodeURIComponent(t), '_blank', 'noopener'); api({ action: 'log', id: d.id, kind: 'wa', body: t }).then(function (j) { if (j.ok) LP.refresh(); }); });

  /* ---------- الصفقات ---------- */
  var SC = { open: '#2563eb', won: '#16a34a', lost: '#dc2626' };
  function dealsPaint(ds) { var p = $('pd-p-dl'), tot = ds.filter(function (d) { return d.status !== 'lost'; }).reduce(function (a, d) { return a + d.value; }, 0);
    p.innerHTML = '<div class="pd-total"><span>' + esc(T.total) + '</span><b data-v="0">0</b><button type="button" class="btn-primary !py-1.5 !text-xs" data-dadd>' + ic('plus', 'w-4 h-4') + esc(T.addDeal) + '</button></div>' +
      '<form class="pd-dform hidden" data-dform data-no-ajax><input name="title" class="input !mt-0 text-sm" dir="auto" required placeholder="' + esc(T.dealName) + '"><div class="grid grid-cols-2 gap-1.5"><input name="value" class="input !mt-0 text-sm" inputmode="decimal" dir="ltr" placeholder="' + esc(T.value) + '"><input name="close_at" type="date" class="input !mt-0 text-sm" title="' + esc(T.closeAt) + '"></div><button class="btn-primary !py-1.5 !text-xs">' + esc(T.add) + '</button></form>' +
      (ds.length ? '<div class="space-y-2 mt-3">' + ds.map(function (d) { var n = days(d.close), due = d.status === 'open' && n !== null ? (n < 0 ? '<span class="pd-due late">' + esc(T.late.replace('%s', -n)) + '</span>' : '<span class="pd-due' + (n <= 7 ? ' soon' : '') + '">' + esc(n === 0 ? T.today : T.inDays.replace('%s', n)) + '</span>') : '';
        return '<div class="pd-deal cp-pop" data-deal="' + d.id + '" style="--c:' + SC[d.status] + '"><div class="flex items-start gap-2"><div class="min-w-0 flex-1"><b class="block text-sm text-ink-900 truncate" dir="auto">' + esc(d.title) + '</b>' + due + '</div><input class="pd-dval" data-dval value="' + esc(d.value) + '" inputmode="decimal" dir="ltr" title="' + esc(T.value) + '"><button type="button" class="pd-dx" data-ddel title="' + esc(T.sure) + '">' + ic('trash', 'w-4 h-4') + '</button></div>' +
          '<div class="pd-seg">' + Object.keys(X.dealSt).map(function (k) { return '<button type="button" data-dst="' + k + '"' + (d.status === k ? ' class="on"' : '') + ' style="--c:' + SC[k] + '">' + esc(X.dealSt[k]) + '</button>'; }).join('') + '</div></div>'; }).join('') + '</div>'
        : '<div class="pd-empty">' + ic('wallet', 'w-8 h-8') + '<p>' + esc(T.noDeals) + '</p></div>');
    countUp(p.querySelector('.pd-total b'), tot); badge('dl', ds.filter(function (d) { return d.status === 'open'; }).length); }
  function dealSave(id, patch) { var d = (data.deals || []).filter(function (x) { return x.id === id; })[0] || {}; var o = { action: 'deal_save', id: lead().id, deal: id || 0, title: d.title || '', value: d.value || 0, status: d.status || 'open', close_at: d.close || '' };
    Object.keys(patch).forEach(function (k) { o[k] = patch[k]; }); return api(o).then(function (j) { if (!j.ok) return fail(j);
      if (patch.status === 'won') { LP.confetti(); } LP.refresh(); return j; }); }
  $('pd-p-dl').addEventListener('click', function (e) { var p = this;
    if (e.target.closest('[data-dadd]')) { var f = p.querySelector('[data-dform]'); f.classList.toggle('hidden'); if (!f.classList.contains('hidden')) f.title.focus(); return; }
    var card = e.target.closest('[data-deal]'); if (!card) return; var id = +card.dataset.deal, s = e.target.closest('[data-dst]');
    if (s && !s.classList.contains('on')) { card.querySelectorAll('[data-dst]').forEach(function (x) { x.classList.toggle('on', x === s); }); card.style.setProperty('--c', SC[s.dataset.dst]); dealSave(id, { status: s.dataset.dst }); }
    var x = e.target.closest('[data-ddel]'); if (x) { if (!x.classList.contains('arm')) { x.classList.add('arm'); x.title = T.delSure; setTimeout(function () { x.classList.remove('arm'); }, 2500); return; }
      card.classList.add('pd-out'); api({ action: 'deal_del', id: lead().id, deal: id }).then(function (j) { if (!j.ok) return fail(j); LP.refresh(); }); } });
  $('pd-p-dl').addEventListener('change', function (e) { var v = e.target.closest('[data-dval]'); if (!v) return; var id = +v.closest('[data-deal]').dataset.deal; dealSave(id, { value: v.value }).then(function () { v.classList.add('pd-ok'); }); });
  $('pd-p-dl').addEventListener('submit', function (e) { e.preventDefault(); var f = e.target, b = f.querySelector('button'); busy(b, true);
    api({ action: 'deal_save', id: lead().id, deal: 0, title: f.title.value, value: f.value.value || 0, status: 'open', close_at: f.close_at.value }).then(function (j) { busy(b, false); if (!j.ok) return fail(j); toast(T.saved); LP.refresh(); }).catch(function () { busy(b, false); fail(); }); });

  /* ---------- المهام وخطة المتابعة ---------- */
  var CHI = { email: 'mail', wa: 'chat', call: 'phone', task: 'check' };
  function tkPaint(run, tasks) { var p = $('pd-p-tk'), h = '<section class="pd-card !p-4"><div class="flex items-center gap-2 mb-2">' + ic('refresh', 'w-4 h-4 text-sky-600') + '<b class="text-sm text-ink-900">' + esc(T.plan) + '</b></div>';
    if (run) { h += '<div class="pd-run' + (run.state === 'due' ? ' due' : '') + '"><div class="flex items-center gap-2"><b class="text-ink-900">' + esc(run.plan) + '</b><span class="ms-auto text-[11px] text-ink-500">' + esc(T.step.replace('%s', run.step + 1).replace('%s', run.n)) + '</span></div>' +
        '<div class="pd-steps">' + Array.apply(null, Array(run.n)).map(function (_, i) { return '<i class="' + (i < run.step ? 'done' : i === run.step ? 'now' : '') + '"></i>'; }).join('') + '</div>' +
        '<div class="flex items-start gap-2 mt-2"><span class="pd-chi">' + ic(CHI[run.ch] || 'dots', 'w-4 h-4') + '</span><div class="min-w-0 flex-1 text-sm">' + (run.subject ? '<b class="block text-ink-900">' + esc(run.subject) + '</b>' : '') + '<p class="text-ink-700 whitespace-pre-line break-words" dir="auto">' + esc(run.text) + '</p>' + (run.due ? '<span class="text-[11px] text-ink-500">' + esc(T.dueAt) + ': ' + esc(run.due.slice(0, 16)) + '</span>' : '') + (run.err ? '<p class="text-[11px] text-red-600">' + esc(run.err) + '</p>' : '') + '</div></div>' +
        '<div class="flex flex-wrap gap-1.5 mt-3">' + (run.ch === 'wa' && lead().wa ? '<button type="button" class="btn-primary !py-1.5 !text-xs" data-pl="wa">' + ic('chat', 'w-4 h-4') + esc(T.sendWa) + '</button>' : '<button type="button" class="btn-primary !py-1.5 !text-xs" data-pl="plan_do">' + esc(T.done) + '</button>') +
        '<button type="button" class="btn-ghost !py-1.5 !text-xs" data-pl="plan_skip">' + esc(T.skip) + '</button><button type="button" class="btn-ghost !py-1.5 !text-xs !text-red-600 ms-auto" data-pl="plan_stop">' + esc(T.stop) + '</button></div></div>'; }
    else if (X.plans.length) h += '<p class="text-xs text-ink-500 mb-2">' + esc(T.noPlan) + '</p><div class="grid gap-1.5">' + X.plans.map(function (pl) { return '<button type="button" class="pd-plan" data-pstart="' + pl.id + '"><b>' + esc(pl.name) + '</b><span class="flex gap-1">' + pl.steps.map(function (c) { return '<i title="' + esc(X.chans[c] || c) + '">' + ic(CHI[c] || 'dots', 'w-3 h-3') + '</i>'; }).join('') + '</span><em>' + esc(T.start) + ' ←</em></button>'; }).join('') + '</div>';
    else h += '<p class="text-xs text-ink-500">' + esc(T.noPlans) + ' <a class="link" href="' + esc(X.settings) + '">' + ic('chevron-left', 'w-3 h-3 inline') + '</a></p>';
    h += '</section><section class="pd-card !p-4 mt-3"><div class="flex items-center gap-2 mb-2">' + ic('check', 'w-4 h-4 text-lime-600') + '<b class="text-sm text-ink-900">' + esc(T.tasks) + '</b><span class="text-[11px] text-ink-400">' + (tasks.length || '') + '</span></div>' +
      '<form class="pd-tform" data-tform data-no-ajax><input name="title" class="input !mt-0 text-sm" dir="auto" required placeholder="' + esc(T.taskPh) + '"><div class="flex flex-wrap items-center gap-1.5 mt-1.5">' +
      '<span class="pd-when"><b>' + esc(T.whenL) + '</b>' + [['0', T.today], ['1', T.tomorrow], ['3', T.d3], ['7', T.week], ['', T.noDue]].map(function (x, i) { return '<label class="pd-chipr"><input type="radio" name="due" value="' + x[0] + '"' + (i === 1 ? ' checked' : '') + '><span>' + esc(x[1]) + '</span></label>'; }).join('') + '<input type="datetime-local" name="at" class="pd-at" title="' + esc(T.pick) + '"></span>' +
      (X.manage ? '<select name="user" class="input !mt-0 !py-1 !h-8 text-xs !w-auto">' + Object.keys(X.members).map(function (k) { return '<option value="' + k + '"' + (+k === X.me ? ' selected' : '') + '>' + esc(+k === X.me ? T.me : X.members[k]) + '</option>'; }).join('') + '</select>' : '') +
      '<button class="btn-primary !py-1 !px-3 !text-xs ms-auto">' + esc(T.add) + '</button></div></form><ul class="space-y-1.5 mt-3">' +
      (tasks.length ? tasks.map(function (t) { return '<li class="pd-task cp-pop" data-task="' + t.id + '"><button type="button" class="pd-tick" data-tdone aria-label="✓"></button><div class="min-w-0 flex-1"><span class="block text-sm text-ink-900 break-words" dir="auto">' + esc(t.title) + '</span><small class="' + (t.over ? 'text-red-600 font-semibold' : 'text-ink-500') + '">' + esc([t.due, t.who].filter(Boolean).join(' · ')) + '</small></div></li>'; }).join('') : '<li class="text-xs text-ink-400 py-2">' + esc(T.noTasks) + '</li>') + '</ul></section>';
    // المتابعة أولًا (مثبتة فوق المهام)، والخطة تحت؛ ولو مفيش خطط مابنعرضش كارت فاضي
    var parts = h.split('</section><section class="pd-card !p-4 mt-3">'), planH = parts[0] + '</section>', taskH = '<section class="pd-card !p-4">' + parts[1];
    var L0 = lead(); L0.next = L0.next || (L0.next_at || '').replace('T', ' '); var pin = L0.next && !tasks.some(function (t) { return t.title === L0.next_note; }) ? '<li class="pd-task pd-pin' + (L0.due ? ' over' : '') + '"><button type="button" class="pd-tick" data-nclear aria-label="✓"></button><div class="min-w-0 flex-1"><span class="block text-sm text-ink-900" dir="auto">📌 ' + esc(L0.next_note || T.follow) + '</span><small class="' + (L0.due ? 'text-red-600 font-semibold' : 'text-ink-500') + '">' + esc(L0.next) + '</small></div></li>' : '';
    // حالة العميل جوه نفس الفورم: تختار المرحلة والخطوة الجاية في مكان واحد
    var cs = L0.stage, opts = [].map.call(document.querySelectorAll('#pd-stages [data-stg]'), function (b) { return '<option value="' + b.dataset.stg + '"' + (b.dataset.stg === cs ? ' selected' : '') + '>' + esc(b.textContent) + '</option>'; }).join('');
    taskH = taskH.replace('<form class="pd-tform"', '<label class="pd-stsel"><span>' + ic('layers', 'w-4 h-4') + esc(T.stageL) + '</span><select id="pd-stg-sel" class="' + (cs === 'won' ? 'won' : cs === 'lost' ? 'lost' : '') + '">' + opts + '</select></label><form class="pd-tform"');
    taskH = taskH.replace('<ul class="space-y-1.5 mt-3">', '<ul class="space-y-1.5 mt-3">' + pin); if (pin) taskH = taskH.replace('<li class="text-xs text-ink-400 py-2">' + esc(T.noTasks) + '</li>', '');
    h = taskH + (run || X.plans.length ? planH.replace('<section class="pd-card !p-4">', '<section class="pd-card !p-4 mt-3">') : '<p class="text-[11px] text-ink-400 mt-3 px-1">' + ic('refresh', 'w-3 h-3 inline') + ' <a class="link" href="' + esc(X.settings) + '">' + esc(T.mkPlan) + '</a></p>');
    p.innerHTML = h; badge('tk', tasks.length + (run ? 1 : 0), (run && run.state === 'due') || tasks.some(function (t) { return t.over; })); }
  $('pd-p-tk').addEventListener('click', function (e) { var d = lead(), b;
    if ((b = e.target.closest('[data-pstart]'))) { busy(b, true); return api({ action: 'plan_start', id: d.id, plan: b.dataset.pstart }).then(function (j) { busy(b, false); if (!j.ok) return fail(j); toast(T.saved); LP.refresh(); }); }
    if ((b = e.target.closest('[data-pl]'))) { var a = b.dataset.pl; if (a === 'wa') { window.open('https://wa.me/' + d.wa + '?text=' + encodeURIComponent(data.run.text), '_blank', 'noopener'); a = 'plan_do'; }
      busy(b, true); return api({ action: a, id: d.id }).then(function (j) { busy(b, false); if (!j.ok) return fail(j); toast(T.saved); LP.refresh(); }); }
    if ((b = e.target.closest('[data-nclear]'))) { var li0 = b.closest('li'); li0.classList.add('done'); api({ action: 'next_quick', when: 'none', id: lead().id, lead: lead().id }).then(function () { setTimeout(function () { LP.reload(); }, 300); }); return; }
    if ((b = e.target.closest('[data-tdone]'))) { var li = b.closest('[data-task]'); li.classList.add('done'); api({ action: 'task_done', task: li.dataset.task }).then(function (j) { if (!j.ok) { li.classList.remove('done'); return fail(j); } setTimeout(function () { LP.reload(); }, 450); setTimeout(function () { li.classList.add('pd-out'); setTimeout(LP.reload, 300); }, 500); }); } });
  $('pd-p-tk').addEventListener('change', function (e) { var t = e.target;
    if (t.name === 'due') { var at = t.form.querySelector('[name=at]'); if (at) at.value = ''; }
    if (t.name === 'at' && t.value) [].forEach.call(t.form.querySelectorAll('[name=due]'), function (r) { r.checked = false; });
    if (t.id === 'pd-stg-sel') { var sb = document.querySelector('#pd-stages [data-stg="' + t.value + '"]'); if (sb) sb.click(); t.className = t.value === 'won' ? 'won' : t.value === 'lost' ? 'lost' : ''; } });
  $('pd-p-tk').addEventListener('submit', function (e) { e.preventDefault(); var f = e.target, b = f.querySelector('button'), dv = (f.querySelector('[name=due]:checked') || {}).value, due = '';
    if (f.at && f.at.value) { due = f.at.value; } else if (dv !== '' && dv != null) { var dt = new Date(); dt.setDate(dt.getDate() + +dv); dt.setHours(dv === '0' ? Math.max(dt.getHours() + 1, 16) : 11, 0, 0, 0); var p = function (n) { return (n < 10 ? '0' : '') + n; }; due = dt.getFullYear() + '-' + p(dt.getMonth() + 1) + '-' + p(dt.getDate()) + 'T' + p(dt.getHours()) + ':00'; }
    busy(b, true); api({ action: 'task_add', title: f.title.value, due: due, user: f.user ? f.user.value : X.me, lead: lead().id }).then(function (j) { busy(b, false); if (!j.ok) return fail(j); toast(T.saved); LP.reload(); }).catch(function () { busy(b, false); fail(); }); });

  /* ---------- الإيميل ---------- */
  var VARS = ['{name}', '{company}', '{me}', '{book}'];
  function fill(t) { var d = lead(); return t.replace(/\{name\}/g, (d.name || '').split(/\s+/)[0]).replace(/\{company\}/g, d.company || '').replace(/\{me\}/g, C.L.me || '').replace(/\{book\}/g, C.L.book || ''); }
  function emPaint(mails) { var p = $('pd-p-em'), d = lead(), keep = p.querySelector('[data-ef]'), sv = keep ? { s: keep.subject.value, b: keep.body.value } : null;
    if (!d.email) { p.innerHTML = '<div class="pd-empty">' + ic('mail', 'w-8 h-8') + '<p>' + esc(T.noEmail) + '</p></div>'; badge('em', 0); return; }
    p.innerHTML = (data.unsub ? '<p class="rounded-xl bg-amber-50 text-amber-800 text-xs p-2 mb-2">' + esc(T.unsub) + '</p>' : '') +
      '<form class="pd-ef" data-ef data-no-ajax><div class="flex items-center gap-2 text-xs text-ink-500 mb-1.5">' + ic('mail', 'w-3.5 h-3.5') + '<span dir="ltr" class="truncate">' + esc(d.email) + '</span></div>' +
      '<input name="subject" class="input !mt-0 text-sm" dir="auto" required maxlength="255" placeholder="' + esc(T.subject) + '"><textarea name="body" rows="5" class="input !mt-1.5 text-sm" dir="auto" required placeholder="' + esc(T.body) + '"></textarea>' +
      '<div class="flex flex-wrap items-center gap-1 mt-1.5">' + VARS.map(function (v) { return '<button type="button" class="pd-var" data-var="' + v + '">' + v + '</button>'; }).join('') + '<button type="button" class="pd-var ms-auto" data-prev>' + ic('eye', 'w-3 h-3') + esc(T.preview) + '</button></div>' +
      '<div class="pd-prev hidden" data-pv></div><button class="btn-primary w-full !py-2 !text-sm mt-2">' + ic('mail', 'w-4 h-4') + esc(T.send) + '</button><p class="text-[11px] text-ink-400 mt-1">' + esc(T.smtp) + '</p></form>' +
      (mails.length ? '<b class="block text-sm text-ink-900 mt-4 mb-2">' + esc(T.history) + '</b><ul class="space-y-1.5">' + mails.map(function (m) { return '<li class="pd-mail cp-pop"><div class="min-w-0 flex-1"><b class="block text-sm text-ink-900 truncate" dir="auto">' + esc(m.subject) + '</b><small class="text-ink-500">' + esc(m.at) + '</small></div>' +
        (m.opens ? '<span class="pd-mb ok" title="' + esc(m.opened) + '">' + ic('eye', 'w-3 h-3') + esc(T.opened.replace('%s', m.opens)) + '</span>' : '<span class="pd-mb">' + esc(T.notOpened) + '</span>') + (m.clicks ? '<span class="pd-mb cl">' + esc(T.clicked) + '</span>' : '') + '</li>'; }).join('') + '</ul>' : '');
    if (sv) { p.querySelector('[data-ef]').subject.value = sv.s; p.querySelector('[data-ef]').body.value = sv.b; }
    badge('em', mails.length); }
  var lastTa = null;
  $('pd-p-em').addEventListener('focusin', function (e) { if (e.target.matches('input,textarea')) lastTa = e.target; });
  $('pd-p-em').addEventListener('click', function (e) { var f = this.querySelector('[data-ef]'), b;
    if ((b = e.target.closest('[data-var]'))) { var t = lastTa || f.body, s = t.selectionStart || t.value.length; t.value = t.value.slice(0, s) + b.dataset.var + t.value.slice(t.selectionEnd || s); t.focus(); t.selectionStart = t.selectionEnd = s + b.dataset.var.length; pv(); }
    if (e.target.closest('[data-prev]')) { f.querySelector('[data-pv]').classList.toggle('hidden'); pv(); } });
  function pv() { var f = $('pd-p-em').querySelector('[data-ef]'); if (!f) return; var el = f.querySelector('[data-pv]'); el.innerHTML = '<b dir="auto">' + esc(fill(f.subject.value)) + '</b><p dir="auto">' + esc(fill(f.body.value)) + '</p>'; }
  $('pd-p-em').addEventListener('input', pv);
  $('pd-p-em').addEventListener('submit', function (e) { e.preventDefault(); var f = e.target, b = f.querySelector('button:not([type])'); busy(b, true); b.lastChild.textContent = T.sending;
    api({ action: 'send_email', id: lead().id, subject: f.subject.value, body: f.body.value }).then(function (j) { busy(b, false); b.lastChild.textContent = T.send; if (!j.ok) return fail(j); f.reset(); toast(T.sent); LP.reload(); }).catch(function () { busy(b, false); b.lastChild.textContent = T.send; fail(); }); });

  /* ---------- البيانات ---------- */
  function dtPaint(j) { var p = $('pd-p-dt'), byDeals = (j.deals || []).length > 0, cf = j.cf || {};
    var row = function (lab, inner, k) { return '<label class="pd-fld" data-f="' + k + '"><span>' + esc(lab) + '</span>' + inner + '<i class="pd-tick2">✓</i></label>'; };
    var h = row(T.value, '<input class="input !mt-0 text-sm" data-fv="value" inputmode="decimal" dir="ltr" value="' + esc(j.value) + '"' + (byDeals ? ' disabled title="' + esc(T.byDeals) + '"' : '') + '>' + (byDeals ? '<small class="text-[11px] text-ink-400">' + esc(T.byDeals) + '</small>' : ''), 'value');
    if (Object.keys(X.pipes).length > 1) h += row(T.pipe, '<select class="input !mt-0 text-sm" data-fv="pipeline">' + Object.keys(X.pipes).map(function (k) { return '<option value="' + esc(k) + '"' + (k === j.pl ? ' selected' : '') + '>' + esc(X.pipes[k]) + '</option>'; }).join('') + '</select>', 'pipeline');
    if (X.cf.length) { h += '<b class="block text-xs text-ink-500 mt-3 mb-1">' + esc(T.fields) + '</b>'; X.cf.forEach(function (f) { var v = cf[f.key] || '', k = 'cf.' + f.key;
      h += row(f.label, f.type === 'select' ? '<select class="input !mt-0 text-sm" data-fv="' + esc(k) + '"><option value="">—</option>' + (f.opts || []).map(function (o) { return '<option' + (o === v ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select>'
        : '<input class="input !mt-0 text-sm" data-fv="' + esc(k) + '" value="' + esc(v) + '" ' + (f.type === 'number' ? 'inputmode="decimal" dir="ltr"' : 'dir="auto"') + ' maxlength="200">', k); }); }
    else h += '<p class="text-[11px] text-ink-400 mt-3">' + esc(T.noFields) + ' <a class="link" href="' + esc(X.settings) + '">' + ic('chevron-left', 'w-3 h-3 inline') + '</a></p>';
    p.innerHTML = '<div class="space-y-2">' + h + '</div>'; }
  $('pd-p-dt').addEventListener('change', function (e) { var el = e.target.closest('[data-fv]'); if (!el) return; var lab = el.closest('.pd-fld');
    api({ action: 'field', id: lead().id, f: el.dataset.fv, v: el.value }, C.selfUrl).then(function (j) { if (!j.ok) return fail(j); lab.classList.remove('ok'); void lab.offsetWidth; lab.classList.add('ok'); if (el.dataset.fv === 'value') LP.refresh(); }).catch(function () { fail(); }); });

  /* ---------- كشف Apollo ---------- */
  function enrichPaint(d) { $('pd-enrich').classList.toggle('hidden', !X.apollo || !!(d.phone && d.email)); }
  $('pd-enrich').addEventListener('click', function () { var b = this, old = b.innerHTML; busy(b, true); b.innerHTML = ic('refresh', 'w-3.5 h-3.5 animate-spin') + esc(T.enriching);
    api({ action: 'enrich', id: lead().id }).then(function (j) { busy(b, false); b.innerHTML = old; if (!j.ok) return fail(j); toast(j.got && j.got.length ? T.got : j.pending ? T.pending : T.nothing, j.got && j.got.length ? 'success' : 'info'); LP.refresh(); }).catch(function () { busy(b, false); b.innerHTML = old; fail(); }); });

  /* ---------- حذف ---------- */
  var del = $('pd-del');
  if (del) del.addEventListener('click', function () { var b = this; if (!b.classList.contains('arm')) { b.classList.add('arm'); b.lastChild.textContent = T.delSure; setTimeout(function () { b.classList.remove('arm'); b.lastChild.textContent = b.dataset.l; }, 3000); return; }
    var r = LP.cur(); api({ action: 'delete', id: LP.jd(r).id }).then(function (j) { if (!j.ok) return fail(j); dl.close(); toast(T.deleted); var H = LP.H(); if (H.onDelete) H.onDelete(r); else if (r.isConnected) r.remove(); }); });
  if (del) del.dataset.l = del.lastChild.textContent;

  /* ---------- ملاحظة صوتية + إملاء ---------- */
  var recB = $('pd-rec'), st = $('pd-rec-st'), rec = null, chunks = [], t0 = 0, tm = null, cancel = false;
  recB.addEventListener('click', function () { if (!navigator.mediaDevices || !window.MediaRecorder) return toast(T.noMic, 'error');
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) { chunks = []; cancel = false; rec = new MediaRecorder(s); rec.ondataavailable = function (e) { if (e.data.size) chunks.push(e.data); };
      rec.onstop = function () { s.getTracks().forEach(function (t) { t.stop(); }); clearInterval(tm); st.classList.add('hidden'); if (cancel || !chunks.length) return; var blob = new Blob(chunks, { type: rec.mimeType || 'audio/webm' }), f = new FormData(), r = LP.cur();
        f.append('action', 'voice'); f.append('id', LP.jd(r).id); f.append('sec', Math.round((Date.now() - t0) / 1000)); f.append('body', $('pd-note-f').b.value); f.append('audio', blob, 'note.' + (/mp4/.test(blob.type) ? 'm4a' : /ogg/.test(blob.type) ? 'ogg' : 'webm'));
        api(f).then(function (j) { if (!j.ok) return fail(j); $('pd-note-f').b.value = ''; toast(T.vSaved); LP.reload(); }); };
      rec.start(); t0 = Date.now(); st.classList.remove('hidden'); tm = setInterval(function () { var s2 = Math.round((Date.now() - t0) / 1000); $('pd-rec-t').textContent = Math.floor(s2 / 60) + ':' + ('0' + s2 % 60).slice(-2); }, 250);
    }).catch(function () { toast(T.noMic, 'error'); }); });
  $('pd-rec-stop').addEventListener('click', function () { if (rec && rec.state === 'recording') rec.stop(); });
  $('pd-rec-x').addEventListener('click', function () { cancel = true; if (rec && rec.state === 'recording') rec.stop(); });
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition, dict = $('pd-dict'), sr = null;
  if (SR) { dict.classList.remove('hidden'); dict.addEventListener('click', function () { if (sr) { sr.stop(); return; } var ta = $('pd-note-f').b, base = ta.value ? ta.value + ' ' : '';
    sr = new SR(); sr.lang = T.lang; sr.interimResults = true; sr.continuous = true; dict.classList.add('on');
    sr.onresult = function (e) { var t = ''; for (var i = 0; i < e.results.length; i++) t += e.results[i][0].transcript; ta.value = base + t; };
    sr.onend = function () { sr = null; dict.classList.remove('on'); }; sr.start(); }); }

  /* ---------- طابور الاتصال ---------- */
  var Q = { on: false, list: [], i: 0, tm: null };
  function qBar() { var b = $('pd-queue'); if (!Q.on) { b.classList.add('hidden'); return; }
    b.innerHTML = '<span class="pd-ring">' + ic('phone', 'w-4 h-4') + '</span><b>' + esc(T.queue) + '</b><span class="tabular-nums">' + esc(T.qOf.replace('%s', Q.i + 1).replace('%s', Q.list.length)) + '</span><span class="pd-qprog"><i style="width:' + Math.round((Q.i + 1) / Q.list.length * 100) + '%"></i></span><span data-qmsg class="text-[11px] opacity-90"></span>' +
      '<button type="button" data-qn>' + esc(T.qNext) + ' ←</button><button type="button" data-qx title="' + esc(T.qExit) + '">✕</button>'; b.classList.remove('hidden'); }
  function qGo(i) { clearInterval(Q.tm); if (i >= Q.list.length) { Q.on = false; qBar(); toast(T.qEnd); LP.confetti(); return; } Q.i = i; LP.open(Q.list[i]); qBar(); setTimeout(ocShow, 350); }
  function qAuto() { var n = 3, m = $('pd-queue').querySelector('[data-qmsg]'); clearInterval(Q.tm);
    m.innerHTML = esc(T.qAuto.replace('%s', n)) + ' <button type="button" data-qs>' + esc(T.qStay) + '</button>';
    Q.tm = setInterval(function () { n--; if (n <= 0) { clearInterval(Q.tm); qGo(Q.i + 1); } else m.firstChild.textContent = T.qAuto.replace('%s', n) + ' '; }, 1000); }
  $('pd-queue').addEventListener('click', function (e) { if (e.target.closest('[data-qn]')) qGo(Q.i + 1); if (e.target.closest('[data-qx]')) { clearInterval(Q.tm); Q.on = false; qBar(); }
    if (e.target.closest('[data-qs]')) { clearInterval(Q.tm); this.querySelector('[data-qmsg]').textContent = ''; } });
  dl.addEventListener('close', function () { clearInterval(Q.tm); Q.on = false; qBar(); });
  LP.queue = function (rows) { if (!rows.length) return; Q = { on: true, list: rows, i: 0, tm: null }; qGo(0); };

  /* ---------- ربط بالنافذة ---------- */
  LP.onOpen(function (d) { ocHide(); $('pd-lost').classList.add('hidden'); enrichPaint(d); if (del) { del.classList.remove('arm'); del.lastChild.textContent = del.dataset.l; }
    ['dl', 'tk', 'em', 'dt'].forEach(function (k) { var p = $('pd-p-' + k); if (!p.dataset.keep || p.dataset.keep !== String(d.id)) p.innerHTML = '<div class="h-16 mk-sk rounded-xl"></div><div class="h-16 mk-sk rounded-xl mt-2"></div>'; p.dataset.keep = d.id; });
    setTab(tab); if (Q.on && LP.cur() !== Q.list[Q.i]) { var k = Q.list.indexOf(LP.cur()); if (k < 0) { Q.on = false; } else Q.i = k; qBar(); } });
  LP.onData(function (j, d) { data = j; intelPaint(j.intel, d); dealsPaint(j.deals || []); tkPaint(j.run, j.tasks || []); emPaint(j.mails || []); dtPaint(j); ink(); });
  window.addEventListener('resize', ink);
})();
