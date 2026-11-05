/* SMS: أجزاء الرسالة ومعاينتها، فحص قايمة الإلغاء، قوالب محفوظة، وفلترة المحادثات. */
(function () {
  var X = window.SMSX; if (!X) return;
  var S = X.s;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function store(k, v) { try { if (v === undefined) return JSON.parse(localStorage.getItem(k) || 'null'); localStorage.setItem(k, JSON.stringify(v)); } catch (e) { return null; } }

  /* ---------- المحادثات ---------- */
  var ch = '', cq = $('#cv-q');
  function filt() {
    var s = cq ? cq.value.trim().toLowerCase() : '', n = 0;
    $$('.cv').forEach(function (a) { var ok = (!ch || a.dataset.ch === ch) && (!s || a.dataset.q.indexOf(s) > -1 || a.dataset.q.replace(/\D/g, '').indexOf(s.replace(/\D/g, '') || '§') > -1); a.style.display = ok ? '' : 'none'; if (ok) n++; });
    var none = $('#cv-none'); if (none) none.style.display = n || !$$('.cv').length ? 'none' : '';
  }
  if (cq) cq.addEventListener('input', filt);
  var chips = $('#cv-chips');
  if (chips) chips.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return; ch = b.dataset.ch;
    $$('button', chips).forEach(function (x) { x.className = 'rounded-full px-2.5 py-1 ' + (x === b ? 'bg-brand-600 text-white' : 'bg-ink-50 text-ink-700 hover:bg-ink-100'); });
    filt();
  });
  var oq = $('#oo-q');
  if (oq) oq.addEventListener('input', function () { var s = oq.value.replace(/\D/g, ''); $$('.oo').forEach(function (r) { r.style.display = !s || r.dataset.q.indexOf(s) > -1 ? '' : 'none'; }); });

  /* ---------- الإرسال ---------- */
  var form = $('#sms-form'); if (!form) return;
  var ta = $('#smsText'), to = $('#sms-to'), prev = $('#sms-prev'), cnt = $('#smsCount'), segs = $('#sms-segs'), uni = $('#sms-uni'), paid = $('#sms-paid'), opt = $('#sms-opt');
  var GSM = /^[@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&'()*+,\-./0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà^{}\\\[~\]|€]*$/;
  function seg() {
    var t = ta.value, isU = !GSM.test(t), n = isU ? Array.from(t).length : t.length + (t.match(/[\^{}\\\[~\]|€]/g) || []).length;
    var single = isU ? 70 : 160, multi = isU ? 67 : 153, k = n === 0 ? 0 : (n <= single ? 1 : Math.ceil(n / multi));
    cnt.textContent = f(S.count, n, k);
    segs.innerHTML = Array.from({ length: Math.max(1, Math.min(k, 10)) }, function (_, i) { var used = i < k - 1 ? 1 : (k ? (n - (k > 1 ? multi * (k - 1) : 0)) / (k > 1 ? multi : single) : 0); return '<span class="w-6 h-1.5 rounded-full bg-ink-100 overflow-hidden"><span class="block h-full ' + (k > 3 ? 'bg-amber-500' : 'bg-brand-500') + '" style="width:' + Math.min(100, used * 100) + '%"></span></span>'; }).join('');
    uni.style.display = isU && n ? '' : 'none'; uni.textContent = S.uni + (k > 1 ? ' ' + f(S.many, k) : '');
    prev.textContent = t;
  }
  function kind() { var r = $('input[name=from]:checked', form); return r ? r.dataset.kind : 'paid'; }
  function paintFrom() { var k = kind(); paid.style.display = k === 'paid' ? '' : 'none'; $('input', paid).required = k === 'paid'; checkOpt(); }
  function isOpt() { var d = to.value.replace(/\D/g, ''); return d.length > 6 && X.opt.indexOf(d) > -1; }
  // تنبيه بس، مش منع: من الخط بيتبعت عادي، ومن أرقام الشركة زيرنيو هو اللي بيرفض
  function checkOpt() { var o = isOpt(); opt.style.display = o ? '' : 'none'; if (o) opt.textContent = kind() === 'mine' ? S.optMine : S.optPaid; }
  ta.addEventListener('input', seg); to.addEventListener('input', checkOpt); to.addEventListener('change', checkOpt);
  $$('input[name=from]', form).forEach(function (r) { r.addEventListener('change', paintFrom); });
  $('#sms-stop').onclick = function () { if (ta.value.indexOf('STOP') < 0) { ta.value = ta.value.replace(/\s+$/, '') + (ta.value.trim() ? '\n' : '') + S.stop; seg(); ta.focus(); } };
  form.addEventListener('submit', function (e) { if (!confirm((isOpt() ? S.optConfirm + '\n' : '') + (kind() === 'mine' ? S.confirmMine : S.confirmPaid))) e.preventDefault(); });

  /* قوالب */
  var KEY = 'sms_tpl_' + X.ws, tBox = $('#sms-tpl');
  function tpls() { return store(KEY) || []; }
  function paintTpl() {
    tBox.innerHTML = tpls().map(function (t, i) { return '<span class="inline-flex items-center rounded-full bg-ink-50 text-xs"><button type="button" data-i="' + i + '" class="ps-2.5 py-0.5 hover:text-brand-700">' + esc(t.n) + '</button><button type="button" data-d="' + i + '" class="px-1.5 text-ink-400 hover:text-red-600" title="' + esc(S.del) + '">×</button></span>'; }).join('');
  }
  tBox.addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return; var L = tpls();
    if (b.dataset.i != null) { ta.value = L[+b.dataset.i].t; seg(); ta.focus(); }
    if (b.dataset.d != null) { L.splice(+b.dataset.d, 1); store(KEY, L); paintTpl(); }
  });
  $('#sms-save').onclick = function () {
    var t = ta.value.trim(); if (!t) return ta.focus();
    var n = prompt(S.tplName, t.slice(0, 20)); if (!n) return;
    var L = tpls(); L.unshift({ n: n.slice(0, 30), t: t }); store(KEY, L.slice(0, 12)); paintTpl();
    window.aiToast && window.aiToast(S.saved, 'ok');
  };
  paintTpl(); seg(); paintFrom(); checkOpt();
})();
