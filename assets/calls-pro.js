/* المكالمات التفاعلية: لوحة اتصال جانبية + تفاصيل المكالمة جنب السجل + تشغيل التسجيل في الصفحة.
   الاتصال الحقيقي بيتعمل عن طريق Zernio: بيتصل بالعميل من رقم الشركة، ولما يرد بيوصله بجوالك. */
(function () {
  var C = window.CLP; if (!C) return;
  var T = C.t || {};
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function f(s) { var a = [].slice.call(arguments, 1), i = 0; return String(s).replace(/%s/g, function () { return a[i++]; }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function get(q) { return fetch(C.url + '&' + q, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }); }
  function post(d) { var fd = new FormData(); fd.append('_csrf', C.csrf); for (var k in d) fd.append(k, d[k]); return fetch(C.url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }); }
  var LS = { g: function (k) { try { return localStorage.getItem(k) || ''; } catch (e) { return ''; } }, s: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };
  var ic = {
    x: '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    ph: '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a1 1 0 01-1 1A16 16 0 014 5a1 1 0 011-1z"/></svg>',
    bld: '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 20V6l8-3 8 3v14M9 20v-5h6v5M8 9h1M15 9h1M8 12h1M15 12h1"/></svg>',
    usr: '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>',
    mob: '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>',
    kp: '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="6" cy="5" r="1.6"/><circle cx="12" cy="5" r="1.6"/><circle cx="18" cy="5" r="1.6"/><circle cx="6" cy="11" r="1.6"/><circle cx="12" cy="11" r="1.6"/><circle cx="18" cy="11" r="1.6"/><circle cx="6" cy="17" r="1.6"/><circle cx="12" cy="17" r="1.6"/><circle cx="18" cy="17" r="1.6"/></svg>',
    chev: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>'
  };

  /* ===== الدرج الجانبي ===== */
  var dr = null, body = null, onClose = null;
  function open(html, cls) {
    if (!dr) {
      dr = document.createElement('div'); dr.className = 'fixed inset-0 z-[90] cq-dr';
      dr.innerHTML = '<div class="cq-bg absolute inset-0 bg-ink-900/40 opacity-0 transition-opacity"></div><aside class="cq-pn absolute top-0 bottom-0 end-0 w-full sm:w-[440px] bg-white dark:bg-ink-900 shadow-2xl flex flex-col transition-transform duration-200" role="dialog" aria-modal="true"></aside>';
      document.body.appendChild(dr);
      $('.cq-bg', dr).onclick = close;
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && dr && !dr.hidden) close(); });
    }
    dr.hidden = false; body = $('.cq-pn', dr); body.className = 'cq-pn absolute top-0 bottom-0 end-0 w-full ' + (cls || 'sm:w-[440px]') + ' bg-white dark:bg-ink-900 shadow-2xl flex flex-col transition-transform duration-200';
    body.style.transform = 'translateX(' + (document.dir === 'rtl' ? '-' : '') + '100%)'; body.innerHTML = html;
    requestAnimationFrame(function () { $('.cq-bg', dr).style.opacity = '1'; body.style.transform = 'none'; });
    document.documentElement.style.overflow = 'hidden';
    return body;
  }
  function close() {
    if (!dr || dr.hidden) return; if (onClose) { onClose(); onClose = null; }
    $('.cq-bg', dr).style.opacity = '0'; body.style.transform = 'translateX(' + (document.dir === 'rtl' ? '-' : '') + '100%)';
    setTimeout(function () { dr.hidden = true; body.innerHTML = ''; }, 200);
    document.documentElement.style.overflow = ''; $$('.cl-row.cq-sel').forEach(function (r) { r.classList.remove('cq-sel'); });
  }
  function head(title, sub) {
    return '<div class="flex items-start gap-3 px-5 py-4 border-b border-ink-100"><div class="flex-1 min-w-0"><div class="font-bold text-lg text-ink-900">' + esc(title) + '</div>' + (sub ? '<div class="text-xs text-ink-500 mt-0.5">' + sub + '</div>' : '') + '</div><button type="button" class="cq-x w-10 h-10 -m-2 grid place-items-center rounded-xl text-ink-500 hover:bg-ink-100" aria-label="' + esc(T.close) + '">' + ic.x + '</button></div>';
  }

  /* ===== لوحة الاتصال ===== */
  function dialer(to) {
    if (!C.canCall) { toast(T.noPerm, 'error'); return; }
    if (!C.voice.length) { location.href = C.url + '&settings=1'; return; }
    var from = LS.g('cx_from'); if (!C.voice.some(function (n) { return n.n === from; })) from = C.voice[0].n;
    var fwd = LS.g('cx_fwd') || (C.voice.filter(function (n) { return n.n === from; })[0] || {}).fwd || '';
    var p = open(head(T.newCall, esc(T.howSub)) +
      '<div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">' +
      /* المسار */
      '<div class="cq-flow grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-1.5 text-center">' +
        '<div class="cq-st min-w-0 rounded-2xl bg-brand-50 text-brand-800 p-2.5"><div class="mx-auto w-9 h-9 grid place-items-center rounded-full bg-white text-brand-600 shadow-sm">' + ic.bld + '</div><div class="text-[11px] mt-1 font-semibold">' + esc(T.stepCo) + '</div><div class="cq-v-from text-[11px] text-brand-700/80 truncate" dir="ltr"></div></div>' +
        '<div class="text-ink-300 cq-arr">' + ic.chev + '</div>' +
        '<div class="cq-st min-w-0 rounded-2xl bg-lime-50 text-lime-800 p-2.5"><div class="mx-auto w-9 h-9 grid place-items-center rounded-full bg-white text-lime-600 shadow-sm">' + ic.usr + '</div><div class="text-[11px] mt-1 font-semibold">' + esc(T.stepCust) + '</div><div class="cq-v-to text-[11px] text-lime-700/80 truncate" dir="ltr">—</div></div>' +
        '<div class="text-ink-300 cq-arr">' + ic.chev + '</div>' +
        '<div class="cq-st min-w-0 rounded-2xl bg-amber-50 text-amber-800 p-2.5"><div class="mx-auto w-9 h-9 grid place-items-center rounded-full bg-white text-amber-600 shadow-sm">' + ic.mob + '</div><div class="text-[11px] mt-1 font-semibold">' + esc(T.stepYou) + '</div><div class="cq-v-fwd text-[11px] text-amber-700/80 truncate" dir="ltr">—</div></div>' +
      '</div>' +
      '<p class="text-xs text-ink-500 -mt-2 leading-relaxed">' + esc(T.flowNote) + '</p>' +
      /* من */
      '<div><div class="label mb-1.5">' + esc(T.from) + '</div><div class="cq-from flex flex-wrap gap-2">' + C.voice.map(function (n) {
        return '<button type="button" data-n="' + esc(n.n) + '" class="cq-chip rounded-xl border px-3 py-2 text-sm font-medium" dir="ltr">' + (n.flag || '') + ' ' + esc(n.n) + '</button>'; }).join('') + '</div></div>' +
      /* العميل */
      '<div class="relative"><div class="flex items-center justify-between mb-1.5"><span class="label !mb-0">' + esc(T.to) + '</span><button type="button" class="cq-kpt text-xs text-brand-600 inline-flex items-center gap-1">' + ic.kp + ' ' + esc(T.keypad) + '</button></div>' +
        '<input class="cq-to input !text-lg !py-3 tracking-wide" dir="ltr" inputmode="tel" autocomplete="off" placeholder="' + esc(T.toPh) + '">' +
        '<div class="cq-lead mt-1.5 text-xs"></div>' +
        '<div class="cq-ac absolute inset-x-0 top-full mt-1 z-10 bg-white dark:bg-ink-800 border border-ink-200 rounded-xl shadow-xl overflow-hidden hidden"></div>' +
        '<div class="cq-kp hidden grid-cols-3 gap-2 mt-3" dir="ltr">' + ['1', '2', '3', '4', '5', '6', '7', '8', '9', '+', '0', '⌫'].map(function (k) { return '<button type="button" data-k="' + k + '" class="h-12 rounded-2xl bg-ink-50 hover:bg-ink-100 active:scale-95 transition text-lg font-semibold text-ink-800">' + k + '</button>'; }).join('') + '</div>' +
      '</div>' +
      /* جوالك */
      '<label class="block"><span class="label">' + esc(T.fwd) + '</span><input class="cq-fwd input" dir="ltr" inputmode="tel" placeholder="' + esc(C.phEx) + '"><span class="text-[11px] text-ink-500">' + esc(T.fwdHint) + '</span></label>' +
      /* التكلفة */
      '<div class="cq-est rounded-2xl border border-ink-200 p-3 text-sm"><div class="flex items-center gap-2"><span class="text-ink-500">' + esc(T.cost) + '</span><b class="cq-pm ms-auto text-ink-900" dir="ltr">—</b></div>' +
        '<div class="flex items-center gap-1.5 mt-2 text-xs"><span class="text-ink-500 me-auto">' + esc(T.ifLasts) + '</span>' + [1, 3, 5, 10].map(function (m, i) { return '<button type="button" data-m="' + m + '" class="cq-mn rounded-full px-2.5 py-1 ' + (i === 1 ? 'bg-ink-900 text-white' : 'bg-ink-100 text-ink-700') + '">' + f(T.min, m) + '</button>'; }).join('') + '</div>' +
        '<div class="cq-tot text-xs text-ink-600 mt-2"></div></div>' +
      /* ترحيب */
      '<details class="rounded-2xl border border-ink-200"><summary class="cursor-pointer px-3 py-2.5 text-sm font-medium text-ink-700">' + esc(T.greet) + '</summary><div class="px-3 pb-3"><textarea class="cq-gr input text-sm" rows="2" maxlength="1000" data-ai="auto_msg,improve,shorten" placeholder="' + esc(T.greetPh) + '"></textarea></div></details>' +
      '<label class="flex items-start gap-2 text-sm p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900"><input type="checkbox" class="cq-ok mt-1"><span>' + esc(T.agree) + '</span></label>' +
      '</div>' +
      '<div class="px-5 py-4 border-t border-ink-100"><button type="button" class="cq-go w-full h-12 rounded-2xl bg-lime-600 hover:bg-lime-700 disabled:bg-ink-200 disabled:text-ink-400 text-white font-bold inline-flex items-center justify-center gap-2 transition">' + ic.ph + '<span>' + esc(T.callNow) + '</span></button><p class="cq-why text-[11px] text-ink-500 text-center mt-2"></p></div>');

    var inTo = $('.cq-to', p), inFwd = $('.cq-fwd', p), ok = $('.cq-ok', p), go = $('.cq-go', p), why = $('.cq-why', p), ac = $('.cq-ac', p), leadBox = $('.cq-lead', p);
    var mins = 3, perMin = null, lead = null, estT = null, acT = null;
    $('.cq-x', p).onclick = close;
    inTo.value = to || ''; inFwd.value = fwd;
    function norm(v) { v = String(v || '').replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }).replace(/[^\d+]/g, ''); if (/^00/.test(v)) v = '+' + v.slice(2); return v; }
    function valid(v) { return /^\+\d{8,15}$/.test(norm(v)); }
    function paintFrom() { $$('.cq-chip', p).forEach(function (b) { var on = b.dataset.n === from; b.className = 'cq-chip rounded-xl border px-3 py-2 text-sm font-medium transition ' + (on ? 'border-brand-500 bg-brand-50 text-brand-800 ring-4 ring-brand-100' : 'border-ink-200 text-ink-700 hover:border-ink-300'); }); $('.cq-v-from', p).textContent = from; }
    function state() {
      $('.cq-v-to', p).textContent = norm(inTo.value) || '—'; $('.cq-v-fwd', p).textContent = norm(inFwd.value) || '—';
      var r = !valid(inTo.value) ? T.needTo : !valid(inFwd.value) ? T.needFwd : !ok.checked ? T.needOk : '';
      go.disabled = !!r; why.textContent = r;
      $('.cq-tot', p).textContent = perMin != null ? f(T.total, mins, '$' + (perMin * mins).toFixed(2)) : '';
    }
    function est() {
      clearTimeout(estT); $('.cq-pm', p).textContent = '…';
      if (!valid(inTo.value)) { perMin = null; $('.cq-pm', p).textContent = '—'; state(); return; }
      estT = setTimeout(function () {
        get('ajax=estimate&to=' + encodeURIComponent(norm(inTo.value)) + '&from=' + encodeURIComponent(from)).then(function (j) {
          if (!j.ok) { perMin = null; $('.cq-pm', p).textContent = '—'; $('.cq-tot', p).textContent = j.error || ''; return; }
          perMin = j.perMin; $('.cq-pm', p).textContent = '$' + perMin.toFixed(3) + ' / ' + T.perMin + (j.country ? ' · ' + j.country : ''); state();
        }).catch(function () { $('.cq-pm', p).textContent = '—'; });
      }, 450);
    }
    function findLead() {
      var v = norm(inTo.value); if (!valid(v)) { leadBox.innerHTML = ''; return; }
      get('ajax=lead&phone=' + encodeURIComponent(v)).then(function (j) { lead = j.lead; leadBox.innerHTML = lead ? '<a class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 text-brand-800 px-2.5 py-1" href="' + esc(lead.url) + '" target="_blank">' + ic.usr.replace('w-5 h-5', 'w-3.5 h-3.5') + '<b>' + esc(lead.name) + '</b>' + (lead.co ? ' · ' + esc(lead.co) : '') + '</a> <span class="text-ink-500">' + esc(T.leadLog) + '</span>' : ''; }).catch(function () {});
    }
    function suggest() {
      clearTimeout(acT); var q = inTo.value.trim();
      if (q.length < 2 || valid(q)) { ac.classList.add('hidden'); return; }
      acT = setTimeout(function () {
        get('ajax=leads&q=' + encodeURIComponent(q)).then(function (j) {
          var it = (j.items || []); if (!it.length) { ac.classList.add('hidden'); return; }
          ac.innerHTML = it.map(function (x) { return '<button type="button" data-ph="' + esc(x.phone) + '" class="w-full text-start px-3 py-2.5 hover:bg-ink-50 flex items-center gap-2 border-b border-ink-100 last:border-0"><span class="w-8 h-8 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-xs font-bold">' + esc((x.name || '?').trim().charAt(0)) + '</span><span class="min-w-0 flex-1"><span class="block text-sm font-medium text-ink-900 truncate">' + esc(x.name) + '</span><span class="block text-xs text-ink-500 truncate">' + esc(x.co) + '</span></span><span class="text-xs text-ink-600" dir="ltr">' + esc(x.phone) + '</span></button>'; }).join('');
          ac.classList.remove('hidden');
        }).catch(function () {});
      }, 250);
    }
    ac.addEventListener('click', function (e) { var b = e.target.closest('[data-ph]'); if (!b) return; inTo.value = b.dataset.ph; ac.classList.add('hidden'); est(); findLead(); state(); });
    inTo.addEventListener('input', function () { suggest(); est(); findLead(); state(); });
    inTo.addEventListener('blur', function () { setTimeout(function () { ac.classList.add('hidden'); }, 200); });
    inFwd.addEventListener('input', state);
    ok.addEventListener('change', state);
    $('.cq-from', p).addEventListener('click', function (e) { var b = e.target.closest('[data-n]'); if (!b) return; from = b.dataset.n; LS.s('cx_from', from); if (!inFwd.value.trim()) { var n = C.voice.filter(function (x) { return x.n === from; })[0]; if (n && n.fwd) inFwd.value = n.fwd; } paintFrom(); est(); state(); });
    $('.cq-kpt', p).onclick = function () { var k = $('.cq-kp', p); k.classList.toggle('hidden'); k.classList.toggle('grid'); };
    $('.cq-kp', p).addEventListener('click', function (e) { var b = e.target.closest('[data-k]'); if (!b) return; var k = b.dataset.k; inTo.value = k === '⌫' ? inTo.value.slice(0, -1) : inTo.value + k; inTo.dispatchEvent(new Event('input')); });
    $$('.cq-mn', p).forEach(function (b) { b.onclick = function () { mins = +b.dataset.m; $$('.cq-mn', p).forEach(function (x) { x.className = 'cq-mn rounded-full px-2.5 py-1 ' + (x === b ? 'bg-ink-900 text-white' : 'bg-ink-100 text-ink-700'); }); state(); }; });
    go.onclick = function () {
      if (go.disabled) return; go.disabled = true; $('span', go).textContent = T.calling;
      LS.s('cx_fwd', norm(inFwd.value));
      post({ action: 'call_json', to: norm(inTo.value), from: from, forward: norm(inFwd.value), greeting: $('.cq-gr', p).value, confirm: '1' }).then(function (j) {
        if (!j.ok) { go.disabled = false; $('span', go).textContent = T.callNow; toast(j.error || T.err, 'error'); return; }
        live(norm(inTo.value), norm(inFwd.value), j.at, lead);
      }).catch(function () { go.disabled = false; $('span', go).textContent = T.callNow; toast(T.err, 'error'); });
    };
    paintFrom(); state(); if (to) { est(); findLead(); }
    setTimeout(function () { (to ? inFwd.value ? ok : inFwd : inTo).focus(); }, 220);
  }

  /* ===== بعد الطلب: متابعة حية ===== */
  function live(to, fwd, at, lead) {
    var p = open(head(T.liveTitle, '<span dir="ltr">' + esc(to) + '</span>') +
      '<div class="flex-1 overflow-y-auto px-5 py-8 text-center">' +
      '<div class="relative mx-auto w-28 h-28"><span class="absolute inset-0 rounded-full bg-lime-400/30 animate-ping"></span><span class="relative w-28 h-28 rounded-full bg-lime-600 text-white grid place-items-center">' + ic.ph.replace('w-5 h-5', 'w-10 h-10') + '</span></div>' +
      '<div class="cq-ls mt-6 font-bold text-lg text-ink-900">' + esc(T.lsDialing) + '</div>' +
      (lead ? '<div class="text-sm text-ink-500 mt-1">' + esc(lead.name) + (lead.co ? ' · ' + esc(lead.co) : '') + '</div>' : '') +
      '<ol class="cq-steps text-start max-w-xs mx-auto mt-6 space-y-3 text-sm">' +
        [[T.ls1, ''], [f(T.ls2, fwd), ''], [T.ls3, '']].map(function (s, i) { return '<li data-i="' + i + '" class="flex items-start gap-3"><span class="cq-dot mt-0.5 w-6 h-6 shrink-0 rounded-full grid place-items-center text-xs font-bold ' + (i ? 'bg-ink-100 text-ink-500' : 'bg-lime-600 text-white') + '">' + (i + 1) + '</span><span class="' + (i ? 'text-ink-500' : 'text-ink-900 font-medium') + '">' + esc(s[0]) + '</span></li>'; }).join('') +
      '</ol>' +
      '<p class="text-xs text-ink-500 mt-6 max-w-xs mx-auto">' + esc(T.liveNote) + '</p></div>' +
      '<div class="px-5 py-4 border-t border-ink-100 flex gap-2"><button type="button" class="cq-again btn-ghost flex-1">' + esc(T.another) + '</button><button type="button" class="cq-done btn-primary flex-1">' + esc(T.done) + '</button></div>');
    $('.cq-x', p).onclick = close; $('.cq-done', p).onclick = function () { close(); location.reload(); }; $('.cq-again', p).onclick = function () { dialer(''); };
    var n = 0, tm = setInterval(tick, 4000); onClose = function () { clearInterval(tm); };
    function step(i) { $$('.cq-steps li', p).forEach(function (li) { var k = +li.dataset.i, d = $('.cq-dot', li); d.className = 'cq-dot mt-0.5 w-6 h-6 shrink-0 rounded-full grid place-items-center text-xs font-bold ' + (k < i ? 'bg-lime-100 text-lime-700' : k === i ? 'bg-lime-600 text-white' : 'bg-ink-100 text-ink-500'); if (k < i) d.textContent = '✓'; li.lastChild.className = k <= i ? 'text-ink-900 font-medium' : 'text-ink-500'; }); }
    function tick() {
      if (++n > 30 || !p.isConnected) { clearInterval(tm); return; }
      get('ajax=latest&to=' + encodeURIComponent(to) + '&since=' + encodeURIComponent(at)).then(function (j) {
        var c = j.call; if (!c) return; var ls = $('.cq-ls', p);
        if (c.status === 'completed' || c.end) { clearInterval(tm); step(3); ls.textContent = c.dur ? f(T.lsEnded, Math.floor(c.dur / 60) + ':' + ('0' + c.dur % 60).slice(-2)) : T.lsNoAns; $('.animate-ping', p) && $('.animate-ping', p).remove(); }
        else if (c.status === 'in_progress' || c.status === 'answered') { step(2); ls.textContent = T.lsTalking; }
        else { step(1); ls.textContent = T.lsRinging; }
      }).catch(function () {});
    }
  }

  /* ===== تفاصيل المكالمة جنب السجل ===== */
  function detail(row, href) {
    $$('.cl-row.cq-sel').forEach(function (r) { r.classList.remove('cq-sel'); }); if (row) row.classList.add('cq-sel');
    var p = open(head(T.details, '') + '<div class="cq-db flex-1 overflow-y-auto px-5 py-4"><div class="space-y-3 animate-pulse"><div class="h-16 rounded-2xl bg-ink-100"></div><div class="h-24 rounded-2xl bg-ink-100"></div><div class="h-40 rounded-2xl bg-ink-100"></div></div></div>' +
      '<div class="px-5 py-3 border-t border-ink-100 flex items-center gap-2 text-sm"><button type="button" class="cq-prev btn-ghost !py-1.5">' + esc(T.prev) + '</button><button type="button" class="cq-next btn-ghost !py-1.5">' + esc(T.next) + '</button><a class="cq-full link ms-auto text-xs" href="' + esc(href) + '">' + esc(T.fullPage) + '</a></div>', 'sm:w-[560px]');
    $('.cq-x', p).onclick = close;
    var vis = $$('#cl-list .cl-row').filter(function (r) { return r.style.display !== 'none'; }), i = vis.indexOf(row);
    var nav = function (d) { var r = vis[i + d]; if (r) detail(r, $('a[href*="id="]', r).href); };
    $('.cq-prev', p).disabled = i <= 0; $('.cq-next', p).disabled = i < 0 || i >= vis.length - 1;
    $('.cq-prev', p).onclick = function () { nav(-1); }; $('.cq-next', p).onclick = function () { nav(1); };
    fetch(href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) {
      var d = new DOMParser().parseFromString(h, 'text/html'), card = d.querySelector('main .card') || d.querySelector('.card');
      var db = $('.cq-db', p); if (!card) { db.textContent = T.err; return; }
      card.className = card.className.replace(/\bcard\b|\bp-6\b|\bmt-3\b/g, '');
      db.innerHTML = ''; db.appendChild(document.importNode(card, true));
      /* تشغيل التسجيل داخل اللوحة */
      $$('a[href*="rec="]', db).forEach(function (a) {
        var id = (a.getAttribute('href').match(/rec=([^&]+)/) || [])[1]; if (!id) return;
        var w = document.createElement('div'); w.className = 'mt-4 rounded-2xl bg-ink-50 p-3';
        w.innerHTML = '<div class="text-xs text-ink-500 mb-2">' + esc(T.rec) + '</div><button type="button" class="btn-ghost !py-1.5 w-full">' + esc(T.loadRec) + '</button>';
        a.replaceWith(w);
        $('button', w).onclick = function () { var b = this; b.disabled = true; b.textContent = '…';
          get('ajax=rec&id=' + id).then(function (j) { if (!j.ok) { b.disabled = false; b.textContent = T.loadRec; toast(j.error, 'error'); return; } w.lastChild.replaceWith(Object.assign(document.createElement('audio'), { controls: true, autoplay: true, src: j.url, className: 'w-full' })); })
            .catch(function () { b.disabled = false; b.textContent = T.loadRec; toast(T.err, 'error'); }); };
      });
      /* العميل المرتبط */
      var num = ($('.ph-copy', db) || {}).dataset; num = num && num.v;
      if (num) get('ajax=lead&phone=' + encodeURIComponent(num)).then(function (j) { if (!j.lead) return; var a = document.createElement('a'); a.href = j.lead.url; a.className = 'mt-4 flex items-center gap-2 rounded-2xl border border-brand-200 bg-brand-50/50 p-3 text-sm hover:bg-brand-50'; a.innerHTML = ic.usr + '<span class="flex-1"><b>' + esc(j.lead.name) + '</b>' + (j.lead.co ? ' · ' + esc(j.lead.co) : '') + '<span class="block text-xs text-ink-500">' + esc(T.openLead) + '</span></span>' + ic.chev; db.firstChild.appendChild(a); }).catch(function () {});
      /* بحث نص المكالمة */
      var tq = $('#tr-q', db); if (tq) tq.addEventListener('input', function () { var s = tq.value.trim().toLowerCase(); $$('.tr-seg', db).forEach(function (g) { g.style.opacity = !s || g.textContent.toLowerCase().indexOf(s) > -1 ? '' : '.35'; }); });
    }).catch(function () { $('.cq-db', p).textContent = T.err; });
  }
  /* ملخص الذكاء داخل اللوحة (السكريبت المضمّن في الصفحة مابيشتغلش بعد النقل) */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.cq-pn #call-ai [data-mode]'); if (!b) return;
    var box = b.closest('#call-ai'), out = $('.call-out', box); b.disabled = true; out.classList.remove('hidden'); out.textContent = '…';
    var fd = new FormData(); fd.append('task', 'call'); fd.append('id', box.dataset.id); fd.append('mode', b.dataset.mode);
    fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': C.csrf } }).then(function (r) { return r.json(); })
      .then(function (d) { if (d.error) out.textContent = d.error; else out.innerHTML = d.html; }).catch(function () { out.textContent = T.err; }).finally(function () { b.disabled = false; });
  });

  /* ===== ربط الأزرار الموجودة ===== */
  document.addEventListener('click', function (e) {
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button) return;
    var a = e.target.closest('a[href]'); if (!a) return; var h = a.getAttribute('href') || '';
    if (/[?&]p=calls\b/.test(h) && /[?&]dial=1/.test(h)) { e.preventDefault(); var m = h.match(/[?&]to=([^&]+)/), mf = h.match(/[?&]from=([^&]+)/); if (mf) LS.s('cx_from', decodeURIComponent(mf[1])); dialer(m ? decodeURIComponent(m[1]) : ''); return; }
    var row = a.closest('#cl-list .cl-row');
    if (row && /[?&]id=/.test(h) && !/rec=/.test(h)) { e.preventDefault(); detail(row, a.href); }
  });
  /* بطاقات الأرقام: الضغط بيفلتر السجل */
  document.addEventListener('click', function (e) {
    var k = e.target.closest('[data-kpi]'); if (!k) return; var b = $('#cl-chips [data-f="' + k.dataset.kpi + '"]'); if (b) { b.click(); var l = $('#cl-q'); if (l) l.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  });
  if (/[?&]dial=1/.test(location.search) && C.canCall && C.voice.length) { var m0 = location.search.match(/[?&]to=([^&]+)/), mf0 = location.search.match(/[?&]from=([^&]+)/); if (mf0) LS.s('cx_from', decodeURIComponent(mf0[1])); history.replaceState(null, '', location.pathname + location.search.replace(/[?&](dial|to|from)=[^&]*/g, '').replace(/^&/, '?')); dialer(m0 ? decodeURIComponent(m0[1]) : ''); }
  var q0 = location.search.match(/[?&]q=([^&]+)/), qi = $('#cl-q'); if (q0 && qi) { qi.value = decodeURIComponent(q0[1]).replace(/^\+/, ''); qi.dispatchEvent(new Event('input')); }
  window.cxDial = dialer;
})();
