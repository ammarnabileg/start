/* الردود التلقائية: كلمات كـ chips، تجربة تعليق مباشرة، معاينة حية، تتبع التعديلات، تشغيل/إيقاف فوري، اختصارات */
(function () {
  var A = window.AUX; if (!A) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var toast = function (m, ok) { if (window.aiToast) aiToast(m, ok ? 'ok' : undefined); };

  /* ---------- القائمة: بحث + فلتر + تشغيل/إيقاف فوري ---------- */
  var filt = 'all', q = $('#au-q');
  function listApply() {
    var v = q ? q.value.trim().toLowerCase() : '', n = 0;
    $$('.au-item').forEach(function (it) {
      var hit = (!v || it.dataset.q.indexOf(v) > -1) && (filt === 'all' || (filt === 'on') === (it.dataset.on === '1'));
      it.style.display = hit ? '' : 'none'; if (hit) n++;
    });
    var em = $('#au-empty'); if (em) em.classList.toggle('hidden', n > 0 || !$$('.au-item').length);
    var vis = $('#au-vis'); if (vis) vis.textContent = $$('.au-item').length ? n + '/' + $$('.au-item').length : '';
  }
  if (q) q.addEventListener('input', listApply);
  $$('#au-f [data-f]').forEach(function (b) {
    b.addEventListener('click', function () { filt = b.dataset.f; $$('#au-f [data-f]').forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.classList.toggle('bg-white', on); x.classList.toggle('shadow-sm', on); x.classList.toggle('text-ink-900', on); x.classList.toggle('font-medium', on); }); listApply(); });
  });
  var first = $('#au-f .on'); if (first) first.click();
  function counts() { var on = $$('.au-item[data-on="1"]').length, all = $$('.au-item').length; var a = $('#au-non'), b = $('#au-noff'); if (a) a.textContent = on; if (b) b.textContent = all - on; }
  document.addEventListener('change', function (e) {
    var c = e.target.closest('.au-toggle'); if (!c) return;
    var it = c.closest('.au-item'), on = c.checked; c.disabled = true;
    var fd = new FormData(); fd.append('action', 'toggle_json'); fd.append('id', it.dataset.id); fd.append('on', on ? '1' : '');
    fetch('index.php?p=automations', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.error) throw new Error(d.error);
      it.dataset.on = on ? '1' : '0'; var dot = it.querySelector('.au-dot'); if (dot) { dot.className = 'au-dot w-1.5 h-1.5 rounded-full shrink-0 ' + (on ? 'bg-lime-500 animate-pulse' : 'bg-ink-300'); }
      var ic = it.querySelector('a > span'); if (ic) { ic.classList.toggle('bg-brand-50', on); ic.classList.toggle('text-brand-600', on); ic.classList.toggle('bg-ink-100', !on); ic.classList.toggle('text-ink-400', !on); }
      counts(); listApply(); toast(on ? A.toggled_on : A.toggled_off, true);
      var edOn = $('#au-form [name=is_active]'); if (edOn && $('#au-form [name=id]') && $('#au-form [name=id]').value === it.dataset.id) { edOn.checked = on; onLbl(); }
    }).catch(function (er) { c.checked = !on; toast(er.message || A.err); }).finally(function () { c.disabled = false; });
  });

  /* ---------- المحرر ---------- */
  var form = $('#au-form');
  if (form) {
    var kwH = $('#au-kw'), chips = $('#au-chips'), kwIn = $('#au-kwin'), modeH = $('#au-mode');
    var kws = kwH.value.split(/[,،\n]+/).map(function (x) { return x.trim(); }).filter(Boolean);
    function plat() { var r = $('#au-accs input:checked'); return r ? r.dataset.plat : A.plat; }
    function canDm() { return A.dm.indexOf(plat()) > -1; }
    function drawChips() {
      $$('.au-chip', chips).forEach(function (c) { c.remove(); });
      kws.forEach(function (k, i) {
        var c = document.createElement('span'); c.className = 'au-chip inline-flex items-center gap-1 rounded-lg bg-brand-50 border border-brand-200 text-brand-800 ps-2 pe-1 py-0.5 text-xs';
        c.innerHTML = '<span></span><button type="button" class="w-4 h-4 rounded text-brand-500 hover:bg-brand-100 hover:text-red-600 leading-none">×</button>'; c.firstChild.textContent = k;
        c.querySelector('button').onclick = function (e) { e.stopPropagation(); kws.splice(i, 1); sync(); kwIn.focus(); };
        chips.insertBefore(c, kwIn);
      });
      kwH.value = kws.join(', ');
      $('#au-kwhint').textContent = kws.length ? A.kwN.replace('%s', kws.length) : A.kwAny;
      var sg = $('#au-sugg'); sg.innerHTML = '';
      A.sugg.filter(function (s) { return kws.map(function (k) { return k.toLowerCase(); }).indexOf(s.toLowerCase()) < 0; }).slice(0, 6).forEach(function (s) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'rounded-full border border-dashed border-ink-300 px-2.5 py-0.5 text-[11px] text-ink-600 hover:border-brand-400 hover:text-brand-700 hover:bg-brand-50 transition'; b.textContent = '+ ' + s;
        b.onclick = function () { add(s); }; sg.appendChild(b);
      });
    }
    function add(v) { v.split(/[,،\n]+/).map(function (x) { return x.trim(); }).filter(Boolean).forEach(function (x) { if (kws.map(function (k) { return k.toLowerCase(); }).indexOf(x.toLowerCase()) < 0) kws.push(x); }); sync(); }
    chips.addEventListener('click', function () { kwIn.focus(); });
    kwIn.addEventListener('keydown', function (e) {
      if ((e.key === 'Enter' || e.key === ',' || e.key === '،') && kwIn.value.trim()) { e.preventDefault(); add(kwIn.value); kwIn.value = ''; }
      else if (e.key === 'Enter') e.preventDefault();
      else if (e.key === 'Backspace' && !kwIn.value && kws.length) { kws.pop(); sync(); }
    });
    kwIn.addEventListener('blur', function () { if (kwIn.value.trim()) { add(kwIn.value); kwIn.value = ''; } });
    kwIn.addEventListener('paste', function () { setTimeout(function () { if (/[,،\n]/.test(kwIn.value)) { add(kwIn.value); kwIn.value = ''; } }, 0); });
    // طريقة المطابقة
    function drawModes() { $$('#au-modes [data-mode]').forEach(function (b) { var on = b.dataset.mode === modeH.value; b.classList.toggle('border-brand-500', on); b.classList.toggle('bg-brand-50', on); b.classList.toggle('ring-2', on); b.classList.toggle('ring-brand-500/20', on); }); }
    $$('#au-modes [data-mode]').forEach(function (b) { b.onclick = function () { modeH.value = b.dataset.mode; sync(); }; });
    // المطابقة زي السيرفر: contains / word / exact
    function norm(t) { return String(t).toLowerCase().replace(/[ً-ْـ]/g, '').replace(/[أإآ]/g, 'ا').replace(/ى/g, 'ي').replace(/ة/g, 'ه').trim(); }
    function matches(text) {
      if (!kws.length) return { any: true };
      var t = norm(text), m = modeH.value;
      for (var i = 0; i < kws.length; i++) {
        var k = norm(kws[i]); if (!k) continue;
        if (m === 'exact' && t === k) return { kw: kws[i] };
        if (m === 'contains' && t.indexOf(k) > -1) return { kw: kws[i] };
        if (m === 'word' && new RegExp('(^|[^\\p{L}\\p{N}])' + k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '($|[^\\p{L}\\p{N}])', 'u').test(t)) return { kw: kws[i] };
      }
      return null;
    }
    var tryIn = $('#au-try'), res = $('#au-res');
    function test() {
      if (!tryIn) return; var v = tryIn.value.trim(); if (!v) { res.classList.add('hidden'); return; }
      var r = matches(v); res.classList.remove('hidden');
      res.className = 'mt-2 rounded-xl px-3 py-2 text-xs ' + (r ? 'bg-lime-50 border border-lime-200 text-lime-800' : 'bg-red-50 border border-red-200 text-red-700');
      res.textContent = r ? (r.any ? A.any : A.match.replace('%s', r.kw)) : A.nomatch;
    }
    if (tryIn) tryIn.addEventListener('input', function () { test(); preview(); });
    // المعاينة
    function preview() {
      var c = $('#pv-c'); if (!c) return;
      c.textContent = (tryIn && tryIn.value.trim()) || (kws[0] ? A.sampleC.replace(/بكام/, kws[0]) : A.sampleC);
      var rp = form.comment_reply.value.trim(), dm = form.dm_message ? form.dm_message.value.trim() : '';
      $('#pv-r').textContent = rp || A.emptyR; $('#pv-rw').classList.toggle('opacity-50', !rp);
      var dw = $('#pv-dw'); dw.classList.toggle('hidden', !canDm()); $('#pv-d').textContent = dm || A.emptyD; $('#pv-d').classList.toggle('opacity-60', !dm);
    }
    // الرسالة الخاصة حسب المنصة
    function platUi() {
      var dm = canDm(); $('#au-dmwrap').classList.toggle('hidden', !dm); $('#au-nodm').classList.toggle('hidden', dm || !plat());
      $('#au-reqlbl').textContent = dm ? A.optional : A.required;
    }
    $$('#au-accs input').forEach(function (r) { r.addEventListener('change', function () { platUi(); preview(); }); });
    // عدّاد الحروف
    function cnts() { $$('.au-cnt').forEach(function (s) { var f = form[s.dataset.for]; if (f) { s.textContent = f.value.length + ' / 1000'; s.classList.toggle('text-amber-600', f.value.length > 900); } }); }
    function onLbl() { var c = form.is_active, l = $('.au-onlbl'); if (l) { l.textContent = c.checked ? A.on : A.off; l.className = 'au-onlbl ' + (c.checked ? 'text-lime-700 font-medium' : 'text-ink-500'); } }
    form.is_active.addEventListener('change', onLbl);
    // تتبع التعديلات
    var snap = '';
    function state() { return new URLSearchParams(new FormData(form)).toString(); }
    function dirty() { var d = state() !== snap; $('#au-dirty').classList.toggle('hidden', !d); return d; }
    function sync() { drawChips(); drawModes(); test(); preview(); cnts(); dirty(); }
    form.addEventListener('input', function () { cnts(); preview(); dirty(); });
    form.addEventListener('change', dirty);
    // تحقق قبل الإرسال
    var sending = false;
    form.addEventListener('submit', function (e) {
      if (kwIn.value.trim()) { add(kwIn.value); kwIn.value = ''; }
      var err = '', dm = form.dm_message ? form.dm_message.value.trim() : '';
      if (!form.elements['name'].value.trim()) err = A.needName; else if (canDm() && !dm) err = A.needDm; else if (!canDm() && !form.comment_reply.value.trim()) err = A.needReply;
      $('#au-err').textContent = err; if (err) { e.preventDefault(); return; }
      sending = true;
    });
    window.addEventListener('beforeunload', function (e) { if (!sending && dirty()) { e.preventDefault(); e.returnValue = A.leave; return A.leave; } });
    document.addEventListener('click', function (e) { var a = e.target.closest('a[href]'); if (a && !sending && dirty() && !a.target && !confirm(A.leave)) e.preventDefault(); }, true);
    document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); if (form.requestSubmit) form.requestSubmit(); else form.submit(); } });
    drawChips(); drawModes(); platUi(); preview(); cnts(); onLbl(); snap = state(); dirty();
    if (!form.elements['name'].value) form.elements['name'].focus();
  }

  /* ---------- أرقام الإحصائيات بتعد ---------- */
  $$('[data-count]').forEach(function (el) { var to = +el.dataset.count, t0 = null; if (!to) { el.textContent = '0'; return; } (function f(ts) { t0 = t0 || ts; var p = Math.min(1, (ts - t0) / 700); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(f); })(performance.now()); });

  /* ---------- الاختصارات ---------- */
  function help() {
    var d = document.createElement('dialog'); d.className = 'w-[min(440px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm text-ink-900"></b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div><div class="p-4 grid gap-1.5 text-sm"></div>';
    d.querySelector('b').textContent = A.keysTitle;
    A.keys.forEach(function (k) { var r = document.createElement('div'); r.className = 'flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-ink-50'; r.innerHTML = '<span class="text-ink-700"></span><span class="flex gap-1">' + k[0].split(' ').map(function (x) { return '<kbd class="min-w-[1.6rem] text-center rounded-md border border-ink-300 border-b-2 bg-ink-50 px-1.5 py-0.5 text-[11px] font-mono text-ink-700">' + esc(x) + '</kbd>'; }).join('') + '</span>'; r.querySelector('span').textContent = k[1]; d.querySelector('.grid').appendChild(r); });
    d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); }); document.body.appendChild(d); d.showModal();
  }
  var hb = $('#au-help'); if (hb) hb.onclick = help;
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey) return;
    var items = $$('.au-item').filter(function (x) { return x.style.display !== 'none'; }), id = new URLSearchParams(location.search).get('id'), cur = items.findIndex(function (x) { return x.dataset.id === id; });
    if (e.key === 'j' || e.key === 'k') { var n = items[Math.max(0, Math.min(items.length - 1, cur + (e.key === 'j' ? 1 : -1)))]; if (n) n.querySelector('a').click(); }
    else if (e.key === 'n') location.href = 'index.php?p=automations&new=1';
    else if (e.key === '/' && q) { e.preventDefault(); q.focus(); }
    else if (e.key === 't' && items[cur]) items[cur].querySelector('.au-toggle').click();
    else if (e.key === '?') { e.preventDefault(); help(); }
  });
})();
