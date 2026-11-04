/* صفحة الرسائل: تحديد محادثات، إجراءات جماعية، حملات رسائل، واختصارات الكيبورد */
(function () {
  var X = window.IBX; if (!X) return;
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var sel = new Set(), filt = { urgent: false, intent: false, unread: false, mine: false, state: '' };
  try { var sv = JSON.parse(sessionStorage.getItem('ibx-sel') || '[]'); sv.forEach(function (k) { sel.add(k); }); filt = Object.assign(filt, JSON.parse(localStorage.getItem('ibx-filt') || '{}')); } catch (e) {}
  function persist() { try { sessionStorage.setItem('ibx-sel', JSON.stringify([...sel])); localStorage.setItem('ibx-filt', JSON.stringify(filt)); } catch (e) {} }
  function post(data) {
    var fd = new FormData(); Object.keys(data).forEach(function (k) { [].concat(data[k]).forEach(function (v) { fd.append(Array.isArray(data[k]) ? k + '[]' : k, v); }); });
    return fetch(location.pathname + '?p=inbox', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); });
  }
  function toast(m, bad) { if (window.aiToast && bad) return aiToast(m); var t = document.createElement('div'); t.className = 'fixed bottom-5 start-1/2 -translate-x-1/2 rtl:translate-x-1/2 z-[70] rounded-xl ' + (bad ? 'bg-red-600' : 'bg-ink-900') + ' text-[#fff] text-sm px-4 py-2.5 shadow-xl'; t.textContent = m; document.body.appendChild(t); setTimeout(function () { t.remove(); }, 3500); }
  var items = function () { return [].slice.call(document.querySelectorAll('.ibx-item')); };

  // يطبّق التحديد والفلاتر على القائمة (بعد كل تحديث تلقائي كمان)
  function apply() {
    var list = document.querySelector('[data-live="list"]'); if (!list) return;
    var its = items();
    its.forEach(function (el, i) {
      if (!el.dataset.ord) el.dataset.ord = i;
      var cb = el.querySelector('.ibx-sel'), on = sel.has(el.dataset.key);
      if (cb) cb.checked = on; el.classList.toggle('bg-brand-50/40', on);
      el.classList.toggle('hidden', (filt.intent && el.dataset.intent !== '1') || (filt.unread && el.dataset.unread !== '1') || (filt.mine && el.dataset.mine !== '1') || (filt.state && el.dataset.state !== filt.state));
    });
    if (filt.urgent) {
      var sorted = its.slice().sort(function (a, b) { return (+b.dataset.urg) - (+a.dataset.urg) || (+a.dataset.ord) - (+b.dataset.ord); });
      var tail = list.querySelector(':scope > a'); sorted.forEach(function (el) { list.insertBefore(el, tail); });
    }
    document.querySelectorAll('[data-ibx-filter]').forEach(function (b) { var on = filt[b.dataset.ibxFilter]; b.classList.toggle('bg-brand-50', on); b.classList.toggle('text-brand-700', on); });
    var stSel = document.getElementById('ibx-state'); if (stSel && stSel.value !== filt.state) stSel.value = filt.state;
    bar();
  }
  function bar() {
    var n = sel.size, b = document.getElementById('ibx-bar'), all = document.getElementById('ibx-all'), vis = items().filter(function (el) { return !el.classList.contains('hidden'); });
    if (b) b.classList.toggle('hidden', !n);
    var nEl = document.getElementById('ibx-n'); if (nEl) nEl.textContent = n;
    var cnt = document.getElementById('ibx-count'); if (cnt) cnt.textContent = n ? n + ' ' + X.sel : X.n.replace('%s', vis.length);
    if (all) { var onVis = vis.filter(function (el) { return sel.has(el.dataset.key); }).length; all.checked = vis.length > 0 && onVis === vis.length; all.indeterminate = onVis > 0 && onVis < vis.length; }
    document.querySelectorAll('.ibx-sel').forEach(function (c) { c.classList.toggle('!opacity-100', n > 0); });
    var cs = document.getElementById('camp-sel-n'); if (cs) cs.textContent = n;
  }
  new MutationObserver(function () { clearTimeout(apply.t); apply.t = setTimeout(apply, 30); }).observe(document.body, { childList: true, subtree: true });

  document.addEventListener('change', function (e) {
    var c = e.target.closest('.ibx-sel');
    if (c) { var k = c.closest('.ibx-item').dataset.key; c.checked ? sel.add(k) : sel.delete(k); persist(); apply(); return; }
    if (e.target.id === 'ibx-state') { filt.state = e.target.value; persist(); apply(); return; }
    if (e.target.id === 'ibx-assign') { team({ action: 'team_assign', user: e.target.value }); return; }
    if (e.target.id === 'ibx-all') { items().forEach(function (el) { if (el.classList.contains('hidden')) return; e.target.checked ? sel.add(el.dataset.key) : sel.delete(el.dataset.key); }); persist(); apply(); }
  });
  // Shift+click: تحديد مجموعة ورا بعض
  var lastIdx = null;
  document.addEventListener('click', function (e) {
    var c = e.target.closest('.ibx-sel'); if (!c) return;
    var its = items(), i = its.indexOf(c.closest('.ibx-item'));
    if (e.shiftKey && lastIdx !== null) { var a = Math.min(lastIdx, i), b = Math.max(lastIdx, i); for (var j = a; j <= b; j++) if (!its[j].classList.contains('hidden')) sel.add(its[j].dataset.key); persist(); setTimeout(apply); }
    lastIdx = i;
  }, true);

  document.addEventListener('click', function (e) {
    var f = e.target.closest('[data-ibx-filter]'); if (f) { filt[f.dataset.ibxFilter] = !filt[f.dataset.ibxFilter]; persist(); apply(); return; }
    if (e.target.closest('#ibx-clear')) { sel.clear(); persist(); apply(); return; }
    var op = e.target.closest('.ibx-op'); if (op) { bulk(op.dataset.op, [...sel], op); return; }
    var one = e.target.closest('.ibx-one'); if (one) { bulk('classify', [one.dataset.key], one); return; }
    if (e.target.closest('#ibx-camp-open')) { openCamp(); return; }
    var co = e.target.closest('[data-ibx-camp-one]'); if (co) { sel.clear(); sel.add(co.dataset.ibxCampOne); persist(); apply(); openCamp(); return; }
    if (e.target.closest('#ibx-info')) { var sd = document.getElementById('ibx-side'), g = document.getElementById('ibx'); sd.classList.toggle('hidden'); sd.classList.toggle('flex'); g.classList.toggle('lg:grid-cols-[23rem_1fr_19rem]'); return; }
    var ch = e.target.closest('[data-cp-insight-chat]');
    if (ch) { // يفتح لوحة ✨ ويسأل عن المحادثة دي
      var panel = document.getElementById('cp'), inp = document.getElementById('cp-input'), pg = document.getElementById('cp-page');
      if (!panel || !inp) return;
      if (panel.classList.contains('pointer-events-none')) document.querySelector('[data-cp-toggle]').click();
      if (pg) pg.checked = true; inp.value = ch.dataset.cpInsightChat; setTimeout(function () { document.getElementById('cp-form').requestSubmit(); }, 350);
    }
  });

  // أدوات الفريق على المحادثة المفتوحة
  function team(data) {
    var box = document.getElementById('ibx-team'); if (!box) return Promise.resolve({});
    data.conv = box.dataset.conv;
    return post(data).then(function (d) { if (d.error) toast(d.error, true); return d; }).catch(function () { toast(X.err, true); });
  }
  document.addEventListener('click', function (e) {
    var st = e.target.closest('#ibx-states [data-state]');
    if (st) {
      document.querySelectorAll('#ibx-states [data-state]').forEach(function (b) { var on = b === st; b.classList.toggle('bg-brand-600', on); b.classList.toggle('text-[#fff]', on); b.classList.toggle('font-medium', on); b.classList.toggle('text-ink-600', !on); });
      team({ action: 'team_state', state: st.dataset.state }); return;
    }
    if (e.target.closest('#ibx-note-add')) { addNote(); return; }
    var nd = e.target.closest('.ibx-note-del'); if (nd) { var n = nd.closest('.ibx-note'); team({ action: 'team_note_del', id: n.dataset.id }).then(function () { n.remove(); }); }
  });
  document.addEventListener('keydown', function (e) { if (e.target.id === 'ibx-note-in' && e.key === 'Enter') { e.preventDefault(); addNote(); } });
  function addNote() {
    var inp = document.getElementById('ibx-note-in'); if (!inp || !inp.value.trim()) return;
    var body = inp.value.trim(); inp.value = '';
    team({ action: 'team_note', body: body }).then(function (d) {
      if (!d || !d.ok) return;
      var n = document.createElement('div'); n.className = 'ibx-note group rounded-xl bg-amber-50 border border-amber-200 p-2.5 text-xs'; n.dataset.id = d.id;
      n.innerHTML = '<div class="flex items-center gap-1.5 text-[10px] text-amber-800 mb-1"><b></b><span></span><button type="button" class="ibx-note-del ms-auto opacity-0 group-hover:opacity-100 text-amber-700 hover:text-red-600">✕</button></div><div class="text-ink-800 whitespace-pre-wrap" dir="auto"></div>';
      n.querySelector('b').textContent = d.name; n.querySelector('span').textContent = '· ' + d.time; n.lastChild.textContent = body;
      document.getElementById('ibx-notes').appendChild(n);
    });
  }

  function bulk(op, keys, btn) {
    if (!keys.length) { toast(X.pickFirst, true); return; }
    // التحليل بيستهلك رصيد: تأكيد بعدد المحادثات قبل ما يبدأ
    if (op === 'classify' && keys.length > 1 && !confirm(X.confirmAi.replace('%s', keys.length))) return;
    btn.disabled = true; btn.classList.add('animate-pulse');
    post({ action: 'bulk', op: op, items: keys }).then(function (d) {
      if (d.error) { toast(d.error, true); return; }
      toast(X.done.replace('%s', d.done));
      if (op !== 'classify') { sel.clear(); persist(); }
      location.reload();
    }).catch(function () { toast(X.err, true); }).finally(function () { btn.disabled = false; btn.classList.remove('animate-pulse'); });
  }

  /* ---------- حملة الرسائل ---------- */
  var dlg = document.getElementById('camp-dlg'), form = document.getElementById('camp-form'), mode = 'selected';
  function openCamp() { if (!dlg) return; mode = sel.size ? 'selected' : 'all'; setMode(mode); document.getElementById('camp-err').textContent = ''; dlg.showModal(); setTimeout(function () { form.message.focus(); }, 50); }
  function setMode(m) { mode = m; document.querySelectorAll('#camp-mode [data-mode]').forEach(function (a) { a.classList.toggle('on', a.dataset.mode === m); }); }
  if (dlg) {
    document.querySelectorAll('#camp-mode [data-mode]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); setMode(a.dataset.mode); }); });
    dlg.querySelectorAll('[data-camp-close]').forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var err = document.getElementById('camp-err'), go = document.getElementById('camp-go');
      if (mode === 'selected' && !sel.size) { err.textContent = X.pickFirst; return; }
      go.disabled = true; err.textContent = '';
      post({ action: 'camp_create', mode: mode, items: mode === 'selected' ? [...sel] : [], name: form.name.value, limit: form.limit.value, message: form.message.value,
        personalize: form.personalize && form.personalize.checked ? 1 : '', only24: form.only24.checked ? 1 : '', skip_bad: form.skip_bad.checked ? 1 : '', per_minute: form.per_minute.value })
        .then(function (d) {
          if (d.error) { err.textContent = d.error; return; }
          dlg.close(); form.reset(); sel.clear(); persist(); apply();
          toast(X.started.replace('%s', d.total) + (d.skipped ? ' · ' + X.skipped.replace('%s', d.skipped) : ''));
          dock(true); tick();
        }).catch(function () { err.textContent = X.err; }).finally(function () { go.disabled = false; });
    });
  }

  // لوحة الحملات: تقدم مباشر، وبتشغّل الطابور وهي مفتوحة
  var dockEl = document.getElementById('camp-dock'), dockOpen = false, timer = null;
  function dock(open) { if (!dockEl) return; dockOpen = open == null ? !dockOpen : open; dockEl.classList.toggle('hidden', !dockOpen); if (dockOpen) tick(true); }
  var db = document.getElementById('camp-dock-btn'); if (db) db.addEventListener('click', function () { dock(); });
  function render(list) {
    var live = list.some(function (c) { return c.status === 'running'; }), dot = document.getElementById('camp-live');
    if (dot) dot.classList.toggle('hidden', !live);
    if (!dockEl || !dockOpen) return live;
    var st = { running: [X.running, 'bg-lime-100 text-lime-700'], paused: [X.paused, 'bg-amber-100 text-amber-700'], done: [X.done2, 'bg-ink-100 text-ink-600'], cancelled: [X.cancelled, 'bg-ink-100 text-ink-500'] };
    dockEl.innerHTML = '<div class="flex items-center gap-2 px-4 h-12 border-b border-ink-200"><b class="text-sm text-ink-900 flex-1"></b><button type="button" class="p-1.5 rounded-lg text-ink-400 hover:text-ink-900 hover:bg-ink-100" data-dock-x>✕</button></div><div class="max-h-[60vh] overflow-y-auto divide-y divide-ink-100"></div>';
    dockEl.querySelector('b').textContent = X.campaigns;
    var box = dockEl.lastChild;
    if (!list.length) box.innerHTML = '<p class="p-5 text-sm text-ink-500 text-center">—</p>';
    list.forEach(function (c) {
      var pct = c.total ? Math.round((c.sent + c.failed) / c.total * 100) : 0, s = st[c.status] || [c.status, 'bg-ink-100'];
      var row = document.createElement('div'); row.className = 'p-4';
      row.innerHTML = '<div class="flex items-center gap-2"><b class="text-sm text-ink-900 truncate flex-1"></b><span class="rounded-md px-1.5 py-0.5 text-[10px] font-medium ' + s[1] + '">' + s[0] + '</span></div>'
        + '<div class="mt-2 h-2 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-l from-brand-600 to-lime-500 transition-all duration-700" style="width:' + pct + '%"></div></div>'
        + '<div class="flex flex-wrap gap-x-3 gap-y-1 mt-2 text-[11px] text-ink-500"><span>' + X.sent + ': <b class="text-ink-900">' + c.sent + '</b>/' + c.total + '</span>' + (c.failed ? '<span class="text-red-600">' + X.failed + ': ' + c.failed + '</span>' : '') + '<span>' + X.left + ': ' + c.left + '</span>' + (c.eta_min ? '<span>' + X.eta.replace('%s', c.eta_min) + '</span>' : '') + '<span class="ms-auto">' + c.created + '</span></div>'
        + '<div class="flex gap-1.5 mt-2.5 ctl"></div>';
      row.querySelector('b').textContent = c.name;
      var ctl = row.querySelector('.ctl'), btn = function (op, label, cls) { var b = document.createElement('button'); b.type = 'button'; b.className = 'btn-ghost !py-1 !px-2.5 !text-xs ' + (cls || ''); b.textContent = label; b.onclick = function () { if (op === 'cancel' && !confirm(X.confirmCancel)) return; post({ action: 'camp_ctl', id: c.id, op: op }).then(function () { tick(true); }); }; ctl.appendChild(b); };
      if (c.status === 'running') btn('pause', X.pause);
      if (c.status === 'paused') btn('resume', X.resume);
      if (c.status === 'running' || c.status === 'paused') btn('cancel', X.cancel, '!text-red-600');
      box.appendChild(row);
    });
    dockEl.querySelector('[data-dock-x]').onclick = function () { dock(false); };
    return live;
  }
  // كل 5 ثواني لو فيه حملة شغالة (بتبعت دفعة)، وإلا كل 30 ثانية تحديث بس
  function tick(statusOnly) {
    clearTimeout(timer);
    post({ action: statusOnly ? 'camp_status' : 'camp_tick' }).then(function (d) { var live = render(d.campaigns || []); timer = setTimeout(function () { tick(!live); }, live ? 5000 : 30000); })
      .catch(function () { timer = setTimeout(function () { tick(true); }, 30000); });
  }
  if (dockEl) tick(true);

  /* ---------- اختصارات الكيبورد ---------- */
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey || /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) || document.querySelector('dialog[open]')) return;
    var its = items().filter(function (el) { return !el.classList.contains('hidden'); }), cur = its.findIndex(function (el) { return el.querySelector('.absolute.bg-brand-600'); });
    if (e.key === 'j' || e.key === 'k') { var n = its[Math.max(0, Math.min(its.length - 1, cur + (e.key === 'j' ? 1 : -1)))]; if (n) { n.querySelector('a').click(); n.scrollIntoView({ block: 'nearest' }); } e.preventDefault(); }
    if (e.key === 'x' && its[cur]) { var k = its[cur].dataset.key; sel.has(k) ? sel.delete(k) : sel.add(k); persist(); apply(); }
    if (e.key === 'r') { var t = document.querySelector('#ibx-compose textarea'); if (t) { t.focus(); e.preventDefault(); } }
    if (e.key === 'e') { var a = document.querySelector('[data-ibx-archive]'); if (a) a.click(); }
    if (e.key === '/') { var q = document.getElementById('ibx-q'); if (q) { q.focus(); e.preventDefault(); } }
  });
  // خانة الرد بتكبر مع الكتابة
  document.addEventListener('input', function (e) { if (e.target.matches('#ibx-compose textarea')) { e.target.style.height = 'auto'; e.target.style.height = Math.min(e.target.scrollHeight, 160) + 'px'; } });
  apply();
})();
