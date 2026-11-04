/* الـ Workflows: بحث وفلاتر، تشغيل/إيقاف فوري، منشئ تفاعلي (حساب، تشغيل، كلمات chips، رسالة بمتغيرات، تجربة ومعاينة حية)، اختصارات */
(function () {
  var W = window.WFX; if (!W) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var toast = function (m, ok) { if (window.aiToast) aiToast(m, ok ? 'ok' : undefined); };
  var badgeCls = { active: 'bg-lime-100 text-lime-700', paused: 'bg-amber-100 text-amber-800', draft: 'bg-ink-100 text-ink-600' };
  function badge(st) { return '<span class="wf-badge rounded-md px-1.5 py-0.5 text-[10px] font-medium ' + (badgeCls[st] || badgeCls.draft) + '">' + esc(W.st[st] || st) + '</span>'; }

  /* ---------- القائمة ---------- */
  var filt = 'all', q = $('#wf-q');
  function listApply() {
    var v = q ? q.value.trim().toLowerCase() : '', n = 0, all = $$('.wf-item');
    all.forEach(function (it) { var hit = (!v || it.dataset.q.indexOf(v) > -1) && (filt === 'all' || it.dataset.st === filt); it.style.display = hit ? '' : 'none'; if (hit) n++; });
    var em = $('#wf-empty'); if (em) em.classList.toggle('hidden', n > 0 || !all.length);
    var vis = $('#wf-vis'); if (vis) vis.textContent = all.length ? n + '/' + all.length : '';
    var c = { all: all.length, active: 0, paused: 0, draft: 0 }; all.forEach(function (it) { if (c[it.dataset.st] != null) c[it.dataset.st]++; });
    $$('.wf-n').forEach(function (s) { s.textContent = c[s.dataset.n]; });
  }
  if (q) q.addEventListener('input', listApply);
  $$('#wf-f [data-f]').forEach(function (b) { b.addEventListener('click', function () { filt = b.dataset.f; $$('#wf-f [data-f]').forEach(function (x) { var on = x === b; ['on', 'bg-white', 'shadow-sm', 'text-ink-900', 'font-medium'].forEach(function (k) { x.classList.toggle(k, on); }); }); listApply(); }); });
  listApply();

  function setStatus(id, on, done) {
    var fd = new FormData(); fd.append('action', 'toggle_json'); fd.append('id', id); fd.append('on', on ? '1' : '');
    return fetch('index.php?p=workflows', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.error) throw new Error(d.error);
      var st = on ? 'active' : 'paused', it = $('.wf-item[data-id="' + CSS.escape(id) + '"]');
      if (it) { it.dataset.st = st; var b = it.querySelector('.wf-badge'); if (b) b.outerHTML = badge(st); var tg = it.querySelector('.wf-toggle'); if (tg) tg.checked = on; var ic = it.querySelector('a > span'); if (ic) { ic.classList.toggle('bg-brand-50', on); ic.classList.toggle('text-brand-600', on); ic.classList.toggle('bg-ink-100', !on); ic.classList.toggle('text-ink-400', !on); } }
      var rb = $('#wf-run'); if (rb && rb.dataset.id === id) { rb.dataset.st = st; rb.textContent = on ? W.stop : W.run; rb.className = (on ? 'btn-ghost' : 'btn-primary') + ' !py-1.5 !text-xs'; var sb = $('#wf-stbadge'); if (sb) sb.innerHTML = badge(st); }
      listApply(); toast(on ? W.on : W.off, true); if (done) done(true);
    }).catch(function (er) { toast(er.message || W.err); if (done) done(false); });
  }
  document.addEventListener('change', function (e) {
    var c = e.target.closest('.wf-toggle'); if (!c) return; var it = c.closest('.wf-item'), on = c.checked; c.disabled = true;
    setStatus(it.dataset.id, on, function (ok) { c.disabled = false; if (!ok) c.checked = !on; });
  });
  var rb = $('#wf-run'); if (rb) rb.addEventListener('click', function () { rb.disabled = true; setStatus(rb.dataset.id, rb.dataset.st !== 'active', function () { rb.disabled = false; }); });

  // التشغيلات الفاشلة بس
  var of = $('#wf-onlyfail'); if (of) of.addEventListener('click', function () { var on = of.classList.toggle('ring-2'); of.classList.toggle('ring-red-300', on); $$('.wf-runrow').forEach(function (r) { r.style.display = on && r.dataset.bad !== '1' ? 'none' : ''; }); });

  /* ---------- المنشئ ---------- */
  var form = $('#wf-form');
  if (form) {
    var kws = [], kwH = $('#wf-kw'), chips = $('#wf-chips'), kwIn = $('#wf-kwin'), trig = $('#wf-trig'), mt = $('#wf-mt'), txt = form.elements['text'];
    function plat() { var r = $('#wf-accs input:checked'); return r ? r.dataset.plat : ''; }
    function drawChips() {
      $$('.wf-chip', chips).forEach(function (c) { c.remove(); });
      kws.forEach(function (k, i) {
        var c = document.createElement('span'); c.className = 'wf-chip inline-flex items-center gap-1 rounded-lg bg-brand-50 border border-brand-200 text-brand-800 ps-2 pe-1 py-0.5 text-xs';
        c.innerHTML = '<span></span><button type="button" class="w-4 h-4 rounded text-brand-500 hover:bg-brand-100 hover:text-red-600 leading-none">×</button>'; c.firstChild.textContent = k;
        c.querySelector('button').onclick = function (e) { e.stopPropagation(); kws.splice(i, 1); sync(); kwIn.focus(); };
        chips.insertBefore(c, kwIn);
      });
      kwH.value = kws.join(', '); $('#wf-kwhint').textContent = kws.length ? W.kwN.replace('%s', kws.length) : W.kwAny;
      $('#wf-mts').classList.toggle('opacity-50', !kws.length);
    }
    function add(v) { v.split(/[,،\n]+/).map(function (x) { return x.trim(); }).filter(Boolean).forEach(function (x) { if (kws.map(function (k) { return k.toLowerCase(); }).indexOf(x.toLowerCase()) < 0) kws.push(x); }); sync(); }
    chips.addEventListener('click', function () { kwIn.focus(); });
    kwIn.addEventListener('keydown', function (e) {
      if ((e.key === 'Enter' || e.key === ',' || e.key === '،') && kwIn.value.trim()) { e.preventDefault(); add(kwIn.value); kwIn.value = ''; }
      else if (e.key === 'Enter') e.preventDefault();
      else if (e.key === 'Backspace' && !kwIn.value && kws.length) { kws.pop(); sync(); }
    });
    kwIn.addEventListener('blur', function () { if (kwIn.value.trim()) { add(kwIn.value); kwIn.value = ''; } });
    // التشغيل والمطابقة
    function drawPick(box, attr, val) { $$('[data-' + attr + ']', box).forEach(function (b) { var on = b.dataset[attr] === val; ['border-brand-500', 'bg-brand-50', 'ring-2', 'ring-brand-500/20'].forEach(function (k) { b.classList.toggle(k, on); }); }); }
    $$('#wf-trigs [data-t]').forEach(function (b) { b.onclick = function () { if (b.disabled) return; trig.value = b.dataset.t; sync(); }; });
    $$('#wf-mts [data-m]').forEach(function (b) { b.onclick = function () { mt.value = b.dataset.m; sync(); }; });
    function platUi() { var ok = W.commentPlats.indexOf(plat()) > -1, cb = $('#wf-trigs [data-t=comment]'); cb.disabled = !ok; if (!ok && trig.value === 'comment') trig.value = 'inbound_message'; }
    $$('#wf-accs input').forEach(function (r) { r.addEventListener('change', function () { platUi(); sync(); }); });
    // المتغيرات
    $$('[data-var]').forEach(function (b) { b.onclick = function () { var v = b.dataset.var, s = txt.selectionStart || txt.value.length, en = txt.selectionEnd || s; txt.value = txt.value.slice(0, s) + v + txt.value.slice(en); txt.focus(); txt.setSelectionRange(s + v.length, s + v.length); txt.dispatchEvent(new Event('input', { bubbles: true })); }; });
    // التجربة والمعاينة
    function norm(t) { return String(t).toLowerCase().replace(/[ً-ْـ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ى/g, 'ي').replace(/ة/g, 'ه').trim(); }
    function matches(t) { if (!kws.length) return { any: true }; t = norm(t); for (var i = 0; i < kws.length; i++) { var k = norm(kws[i]); if (mt.value === 'exact' ? t === k : t.indexOf(k) > -1) return { kw: kws[i] }; } return null; }
    var tryIn = $('#wf-try'), res = $('#wf-res');
    function test() {
      if (!tryIn) return; var v = tryIn.value.trim(); if (!v) { res.classList.add('hidden'); return; }
      var r = matches(v); res.className = 'mt-2 rounded-xl px-3 py-2 text-xs ' + (r ? 'bg-lime-50 border border-lime-200 text-lime-800' : 'bg-red-50 border border-red-200 text-red-700');
      res.textContent = r ? (r.any ? W.any : W.match.replace('%s', r.kw)) : W.nomatch;
    }
    function preview() {
      var i = $('#pv-in'), o = $('#pv-out'); if (!i) return;
      i.textContent = (tryIn && tryIn.value.trim()) || (kws[0] ? kws[0] : W.sampleIn);
      var m = txt.value.trim().replace(/\{\{\s*contact\.name\s*\}\}/g, W.sampleName); o.textContent = m || W.emptyOut; o.classList.toggle('opacity-60', !m);
      var ok = !tryIn || !tryIn.value.trim() || !!matches(tryIn.value); o.parentNode.classList.toggle('hidden', !ok);
    }
    if (tryIn) tryIn.addEventListener('input', function () { test(); preview(); });
    function cnt() { var c = $('.wf-cnt'); c.textContent = txt.value.length + ' / 1000'; }
    var snap = '', sending = false;
    function state() { return new URLSearchParams(new FormData(form)).toString(); }
    function dirty() { var d = state() !== snap; $('#wf-dirty').classList.toggle('hidden', !d); return d; }
    function sync() { drawChips(); drawPick($('#wf-trigs'), 't', trig.value); drawPick($('#wf-mts'), 'm', mt.value); test(); preview(); cnt(); dirty(); }
    form.addEventListener('input', function () { preview(); cnt(); dirty(); });
    form.addEventListener('submit', function (e) {
      if (kwIn.value.trim()) { add(kwIn.value); kwIn.value = ''; }
      var err = !form.elements['name'].value.trim() || !txt.value.trim() ? W.needName : (trig.value === 'comment' && W.commentPlats.indexOf(plat()) < 0 ? W.noComment : '');
      $('#wf-err').textContent = err; if (err) { e.preventDefault(); return; } sending = true;
    });
    window.addEventListener('beforeunload', function (e) { if (!sending && dirty()) { e.preventDefault(); e.returnValue = W.leave; return W.leave; } });
    document.addEventListener('click', function (e) { var a = e.target.closest('a[href]'); if (a && !sending && dirty() && !a.target && !confirm(W.leave)) e.preventDefault(); }, true);
    document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.submit(); } });
    platUi(); sync(); snap = state(); dirty(); form.elements['name'].focus();
  }

  /* ---------- أرقام بتعد + اختصارات ---------- */
  $$('[data-count]').forEach(function (el) { var to = +el.dataset.count, t0 = null; if (!to) { el.textContent = '0'; return; } (function f(ts) { t0 = t0 || ts; var p = Math.min(1, (ts - t0) / 700); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(f); })(performance.now()); });
  function help() {
    var d = document.createElement('dialog'); d.className = 'w-[min(440px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm text-ink-900"></b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div><div class="p-4 grid gap-1.5 text-sm"></div>';
    d.querySelector('b').textContent = W.keysTitle;
    W.keys.forEach(function (k) { var r = document.createElement('div'); r.className = 'flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-ink-50'; r.innerHTML = '<span class="text-ink-700"></span><span class="flex gap-1">' + k[0].split(' ').map(function (x) { return '<kbd class="min-w-[1.6rem] text-center rounded-md border border-ink-300 border-b-2 bg-ink-50 px-1.5 py-0.5 text-[11px] font-mono text-ink-700">' + esc(x) + '</kbd>'; }).join('') + '</span>'; r.querySelector('span').textContent = k[1]; d.querySelector('.grid').appendChild(r); });
    d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); }); document.body.appendChild(d); d.showModal();
  }
  var hb = $('#wf-help'); if (hb) hb.onclick = help;
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey) return;
    var items = $$('.wf-item').filter(function (x) { return x.style.display !== 'none'; }), id = new URLSearchParams(location.search).get('id'), cur = items.findIndex(function (x) { return x.dataset.id === id; });
    if (e.key === 'j' || e.key === 'k') { var n = items[Math.max(0, Math.min(items.length - 1, cur + (e.key === 'j' ? 1 : -1)))]; if (n) n.querySelector('a').click(); }
    else if (e.key === 'n') location.href = 'index.php?p=workflows&new=1';
    else if (e.key === '/' && q) { e.preventDefault(); q.focus(); }
    else if (e.key === 't') { if (rb) rb.click(); else if (items[cur]) items[cur].querySelector('.wf-toggle').click(); }
    else if (e.key === '?') { e.preventDefault(); help(); }
  });
})();
