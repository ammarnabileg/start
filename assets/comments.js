/* التعليقات: تحديد، فلاتر، تصنيف، إخفاء، ورد على المحدد على دفعات */
(function () {
  function setText(el, v) { v = String(v); if (el && el.textContent !== v) el.textContent = v; }
  var C = window.CMX; if (!C) return;
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var sel = new Set(), filt = { intent: false, bad: false, unanswered: false };
  function qs() { var u = new URL(location.href); return '?p=comments&post=' + encodeURIComponent(u.searchParams.get('post') || '') + '&a=' + encodeURIComponent(u.searchParams.get('a') || ''); }
  function post(data) { var fd = new FormData(); Object.keys(data).forEach(function (k) { [].concat(data[k]).forEach(function (v) { fd.append(Array.isArray(data[k]) ? k + '[]' : k, v); }); }); return fetch(location.pathname + qs(), { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }); }
  function toast(m, bad) { var t = document.createElement('div'); t.className = 'fixed bottom-5 start-1/2 -translate-x-1/2 rtl:translate-x-1/2 z-[70] rounded-xl ' + (bad ? 'bg-red-600' : 'bg-ink-900') + ' text-[#fff] text-sm px-4 py-2.5 shadow-xl'; t.textContent = m; document.body.appendChild(t); setTimeout(function () { t.remove(); }, 3500); }
  var items = function () { return [].slice.call(document.querySelectorAll('.cm-item')).filter(function (el) { return el.dataset.mine !== '1'; }); };
  function apply() {
    var postKey = new URL(location.href).searchParams.get('post') || '';
    if (apply.post !== postKey) { sel.clear(); apply.post = postKey; }
    items().forEach(function (el) {
      var cb = el.querySelector('.cm-sel'), on = sel.has(el.dataset.id); if (cb) cb.checked = on;
      var hide = (filt.intent && el.dataset.intent !== '1') || (filt.bad && el.dataset.bad !== '1') || (filt.unanswered && el.dataset.answered === '1');
      el.classList.toggle('hidden', hide);
    });
    document.querySelectorAll('[data-cm-filter]').forEach(function (b) { var on = filt[b.dataset.cmFilter]; b.classList.toggle('bg-brand-50', on); b.classList.toggle('text-brand-700', on); });
    var n = sel.size, bar = document.getElementById('cm-bar'); if (bar) { bar.classList.toggle('hidden', !n); bar.classList.toggle('flex', !!n); }
    var ne = document.getElementById('cm-n'); setText(ne, n);
    var cnt = document.getElementById('cm-count'); setText(cnt, n ? n + ' ' + C.sel : C.all);
    var all = document.getElementById('cm-all'), vis = items().filter(function (el) { return !el.classList.contains('hidden'); });
    if (all) { var k = vis.filter(function (el) { return sel.has(el.dataset.id); }).length; all.checked = vis.length > 0 && k === vis.length; all.indeterminate = k > 0 && k < vis.length; }
    document.querySelectorAll('.cm-sel').forEach(function (c) { c.classList.toggle('!opacity-100', n > 0); });
  }
  new MutationObserver(function () { clearTimeout(apply.t); apply.t = setTimeout(apply, 30); }).observe(document.body, { childList: true, subtree: true });
  document.addEventListener('change', function (e) {
    var c = e.target.closest('.cm-sel'); if (c) { var id = c.closest('.cm-item').dataset.id; c.checked ? sel.add(id) : sel.delete(id); apply(); return; }
    if (e.target.id === 'cm-all') { items().forEach(function (el) { if (!el.classList.contains('hidden')) e.target.checked ? sel.add(el.dataset.id) : sel.delete(el.dataset.id); }); apply(); }
  });
  document.addEventListener('click', function (e) {
    var f = e.target.closest('[data-cm-filter]'); if (f) { filt[f.dataset.cmFilter] = !filt[f.dataset.cmFilter]; apply(); return; }
    if (e.target.closest('#cm-clear')) { sel.clear(); apply(); return; }
    var op = e.target.closest('.cm-op'); if (op) { run(op.dataset.cmOp, op); return; }
    var ch = e.target.closest('[data-cp-insight-chat]');
    if (ch) { var panel = document.getElementById('cp'), inp = document.getElementById('cp-input'), pg = document.getElementById('cp-page'); if (!panel || !inp) return; if (panel.classList.contains('pointer-events-none')) document.querySelector('[data-cp-toggle]').click(); if (pg) pg.checked = true; inp.value = ch.dataset.cpInsightChat; setTimeout(function () { document.getElementById('cp-form').requestSubmit(); }, 350); }
  });
  function run(op, btn) {
    var ids = [...sel]; if (!ids.length) { toast(C.pick, true); return; }
    if (op === 'reply') { openReply(); return; }
    if (op === 'classify' && ids.length > 1 && !confirm(C.confirmAi.replace('%s', ids.length))) return;
    if (op === 'hide' && !confirm(C.confirmHide.replace('%s', ids.length))) return;
    btn.disabled = true; btn.classList.add('animate-pulse');
    post({ action: op === 'classify' ? 'c_classify' : 'c_hide', ids: ids }).then(function (d) {
      if (d.error) { toast(d.error, true); return; } toast(C.done.replace('%s', d.done)); location.reload();
    }).catch(function () { toast(C.err, true); }).finally(function () { btn.disabled = false; btn.classList.remove('animate-pulse'); });
  }
  // الرد على المحدد: واحد ورا التاني بفاصل ثانيتين، عشان الحساب مايتحظرش
  var dlg = document.getElementById('cm-dlg'), form = document.getElementById('cm-form'), stop = false;
  function openReply() { document.getElementById('cm-dn').textContent = sel.size; document.getElementById('cm-err').textContent = ''; document.getElementById('cm-prog').classList.add('hidden'); stop = false; dlg.showModal(); }
  if (dlg) {
    dlg.querySelectorAll('[data-cm-close]').forEach(function (b) { b.addEventListener('click', function () { stop = true; dlg.close(); }); });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var ids = [...sel], msg = form.message.value.trim(), vary = form.vary && form.vary.checked ? 1 : '', go = document.getElementById('cm-go');
      if (!msg) { form.message.focus(); return; }
      var prog = document.getElementById('cm-prog'), bar = prog.querySelector('div > div'), lbl = prog.querySelector('p'), ok = 0, bad = 0;
      prog.classList.remove('hidden'); go.disabled = true; stop = false;
      (function next(i) {
        if (stop) return;
        if (i >= ids.length) { lbl.textContent = C.finished.replace('%s', ok).replace('%s', bad); go.disabled = false; sel.clear(); setTimeout(function () { dlg.close(); location.reload(); }, 1200); return; }
        var el = document.querySelector('.cm-item[data-id="' + CSS.escape(ids[i]) + '"]');
        post({ action: 'c_reply', ids: [ids[i]], message: msg, vary: vary, name: el ? el.dataset.name : '', comment: el ? (el.querySelector('p') || {}).textContent || '' : '' })
          .then(function (d) { d && d.ok ? ok++ : bad++; }).catch(function () { bad++; })
          .finally(function () { bar.style.width = Math.round((i + 1) / ids.length * 100) + '%'; lbl.textContent = C.progress.replace('%s', i + 1).replace('%s', ids.length); setTimeout(function () { next(i + 1); }, i + 1 < ids.length ? 2000 : 0); });
      })(0);
    });
  }
  apply();
})();
