/* أرقام التليفون: نسخ، فلترة، ومعالج شراء تفاعلي.
   الشراء نفسه بيحصل بس لما صاحب الشركة يعلّم على التأكيد ويدوس شراء ويوافق على رسالة التأكيد. */
(function () {
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var X = window.PHX || {}, B = window.PHB;

  /* نسخ الرقم */
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.ph-copy'); if (!b) return;
    var v = b.dataset.v;
    (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { window.aiToast && window.aiToast(X.copied, 'ok'); }, function () { prompt('', v); });
  });

  /* فلترة أرقامك */
  var flt = $('#ph-filter');
  if (flt) flt.addEventListener('input', function () {
    var s = flt.value.toLowerCase().replace(/[\s+()-]/g, '');
    $$('.ph-card').forEach(function (c) { c.style.display = !s || c.dataset.q.replace(/\s/g, '').indexOf(s) > -1 || c.dataset.q.indexOf(s) > -1 ? '' : 'none'; });
  });

  if (!B) return;
  var S = B.s;
  function money(c) { return '$' + (c / 100).toFixed(2); }

  /* ---------- الدولة: بحث ---------- */
  var cq = $('#ph-cq');
  if (cq) cq.addEventListener('input', function () {
    var s = cq.value.trim().toLowerCase();
    $$('.ph-c').forEach(function (l) { l.style.display = !s || l.dataset.q.indexOf(s) > -1 ? '' : 'none'; });
  });
  var checked = $('.ph-c input:checked'); if (checked) checked.closest('.ph-c').scrollIntoView({ block: 'nearest' });

  /* ---------- النوع: أزرار حسب الدولة ---------- */
  var tBox = $('#ph-types'), tNote = $('#ph-tnote');
  function paintTypes() {
    var cc = ($('.ph-c input:checked') || {}).value, c = B.countries[cc]; if (!tBox) return;
    var cur = tBox.dataset.cur || '', ts = c ? c.types : [];
    if (cur && !ts.some(function (t) { return t.t === cur; })) cur = '';
    var opts = [{ t: '', cents: c ? c.cents : 0, label: S.def }].concat(ts.map(function (t) { return { t: t.t, cents: t.cents, sms: t.sms, kyc: t.kyc, label: B.types[t.t] || t.t }; }));
    tBox.innerHTML = opts.map(function (o) {
      return '<label class="cursor-pointer"><input type="radio" name="numberType" value="' + esc(o.t) + '" class="peer sr-only"' + (o.t === cur ? ' checked' : '') + '>'
        + '<span class="inline-flex flex-col rounded-xl border border-ink-200 px-3 py-2 text-sm hover:border-brand-400 peer-checked:border-brand-600 peer-checked:bg-brand-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-300 transition">'
        + '<span class="font-medium">' + esc(o.label) + '</span><span class="text-[11px] opacity-75" dir="ltr">' + esc(f(S.perMonth, money(o.cents))) + '</span></span></label>';
    }).join('');
    var note = function () {
      var v = ($('input[name=numberType]:checked', tBox) || {}).value || '', t = ts.filter(function (x) { return x.t === v; })[0] || ts[0];
      var parts = [];
      if (t && !t.sms) parts.push('<span class="text-ink-500">' + esc(S.noSms) + '</span>');
      if ((t && t.kyc) || (c && c.kyc)) parts.push('<span class="text-amber-700">' + esc(S.kyc) + '</span>');
      tNote.innerHTML = parts.join(' · ');
      tBox.dataset.cur = v;
    };
    tBox.onchange = note; note();
  }
  $$('.ph-c input').forEach(function (i) { i.addEventListener('change', paintTypes); });
  paintTypes();

  /* ---------- النتايج: أرقام مميزة + بحث بالأرقام ---------- */
  function nice(d) {
    // بنبص على آخر 4 أرقام بس (اللي قبلها كود المنطقة والسنترال)
    var e = d.slice(-4);
    return /(\d)\1\1/.test(e) || /00$/.test(e) || /^(0123|1234|2345|3456|4567|5678|6789|9876|8765|7654|6543|5432|4321)$/.test(e) || /^(\d)(\d)\1\2$/.test(e) || /^(\d)(\d)\2\1$/.test(e);
  }
  var cards = $$('.ph-n'), nq = $('#ph-nq'), onlyNice = $('#ph-nice');
  cards.forEach(function (l) { if (nice(l.dataset.num)) { l.dataset.nice = 1; $('.ph-badge', l).innerHTML = '<span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[10px]">★ ' + esc(S.nice) + '</span>'; } });
  function filterRes() {
    var s = nq ? nq.value.replace(/\D/g, '') : '', on = onlyNice && onlyNice.checked, shown = 0;
    cards.forEach(function (l) {
      var ok = (!s || l.dataset.num.indexOf(s) > -1) && (!on || l.dataset.nice);
      l.style.display = ok ? '' : 'none'; if (ok) shown++;
      var dig = $('.ph-dig', l), raw = $('input', l).value;
      if (s && ok) {
        // نعلّم الجزء المطابق جوه الرقم
        var out = '', digits = 0, start = l.dataset.num.indexOf(s);
        for (var i = 0; i < raw.length; i++) { var ch = raw[i], isD = /\d/.test(ch); if (isD) { if (digits === start) out += '<mark class="bg-amber-200 rounded px-0.5">'; } out += esc(ch); if (isD) { digits++; if (digits === start + s.length) out += '</mark>'; } }
        dig.innerHTML = out;
      } else dig.textContent = raw;
    });
    var none = $('#ph-none'); if (none) none.style.display = shown ? 'none' : '';
  }
  if (nq) nq.addEventListener('input', filterRes);
  if (onlyNice) onlyNice.addEventListener('change', filterRes);

  /* ---------- ملخص الشراء ---------- */
  var form = $('#ph-buyform'), sel = $('#ph-sel'), ok = $('#ph-ok'), btn = $('#ph-buybtn');
  if (form && sel) {
    var paintSel = function () {
      var r = $('input[name=phoneNumber]:checked', form);
      sel.textContent = r ? r.value : S.pick;
      if (btn) btn.disabled = !(r && ok.checked);
      var kg = $('#ph-kyc-go'); if (kg) kg.href = kg.dataset.base + (r ? '&phoneNumber=' + encodeURIComponent(r.value) : '');
    };
    form.addEventListener('change', paintSel);
    form.addEventListener('submit', function (e) {
      var r = $('input[name=phoneNumber]:checked', form);
      if (!r || !ok.checked || !confirm(f(S.confirm, r.value, B.price))) { e.preventDefault(); return; }
      if (btn) btn.disabled = true; // منع الضغط مرتين
    });
    paintSel();
  }
})();
