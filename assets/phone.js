/* أرقام التليفون (libphonenumber-js): أي input فيه data-phone
 *   data-phone="mobile"  لازم موبايل (واتساب / رسائل)   ·   data-phone="any"  أي رقم
 *   data-phone-req       يمنع الإرسال لو الرقم غلط (الخانة الفاضية مسموحة لو مش required)
 * الدولة الافتراضية = دولة الشركة (window.PHONE.cc). الرقم بيتحول للصيغة الدولية E.164 لما تسيب الخانة.
 */
(function () {
  var C = window.PHONE || {}, L = window.libphonenumber; if (!L) return;
  var T = C.t || {}, fmt = function (s) { var a = [].slice.call(arguments, 1); return String(s || '').replace(/%s/g, function () { return a.shift(); }); };
  var digits = function (s) { return String(s || '').replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }); };
  function parse(v) {
    v = digits(v).trim(); if (!v) return null;
    if (/^00/.test(v)) v = '+' + v.slice(2);
    try { return L.parsePhoneNumberFromString(v, v.charAt(0) === '+' ? undefined : C.cc); } catch (e) { return null; }
  }
  function check(inp) {
    var p = parse(inp.value), need = inp.getAttribute('data-phone'), r = { ok: true, msg: '', cls: 'text-ink-500' };
    if (!inp.value.trim()) return r;
    if (!p || !p.isPossible()) return { ok: false, msg: T.bad, cls: 'text-red-600' };
    var cc = p.country || '', name = (C.names || {})[cc] || cc, flag = (C.flags || {})[cc] || '', type = p.getType();
    if (!p.isValid()) return { ok: false, msg: fmt(T.invalidFor, name || ('+' + p.countryCallingCode)), cls: 'text-red-600', p: p };
    var tl = type === 'MOBILE' ? T.mobile : type === 'FIXED_LINE' ? T.fixed : type === 'FIXED_LINE_OR_MOBILE' ? T.either : type === 'TOLL_FREE' ? T.tollFree : '';
    r = { ok: true, p: p, cls: 'text-lime-700', msg: (flag ? flag + ' ' : '') + name + (tl ? ' · ' + tl : '') + ' · ' + p.formatInternational() };
    if (need === 'mobile' && (type === 'FIXED_LINE' || type === 'TOLL_FREE')) { r.ok = false; r.cls = 'text-red-600'; r.msg = fmt(T.notMobile, p.formatInternational()); }
    return r;
  }
  function enhance(inp) {
    if (inp.dataset.phoneOn) return; inp.dataset.phoneOn = '1';
    inp.setAttribute('dir', 'ltr'); inp.setAttribute('inputmode', 'tel'); inp.setAttribute('autocomplete', inp.getAttribute('autocomplete') || 'tel');
    if (!inp.placeholder && C.example) inp.placeholder = C.example;
    var st = document.createElement('div'); st.className = 'phone-st mt-1 text-xs min-h-[1rem]'; st.setAttribute('aria-live', 'polite');
    inp.insertAdjacentElement('afterend', st);
    var paint = function () { var r = check(inp); st.textContent = r.msg; st.className = 'phone-st mt-1 text-xs min-h-[1rem] ' + r.cls; inp.setAttribute('aria-invalid', r.ok ? 'false' : 'true'); inp.classList.toggle('!border-red-400', !r.ok); return r; };
    inp.addEventListener('input', paint);
    inp.addEventListener('blur', function () {
      var r = paint(); if (r.ok && r.p && inp.value !== r.p.number) { inp.value = r.p.number; inp.dispatchEvent(new Event('input', { bubbles: true })); inp.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    if (inp.form && !inp.form.dataset.phoneGuard) {
      inp.form.dataset.phoneGuard = '1';
      inp.form.addEventListener('submit', function (e) {
        var bad = [].filter.call(inp.form.querySelectorAll('[data-phone][data-phone-req]'), function (x) { var r = check(x); if (r.ok && r.p) x.value = r.p.number; return !r.ok; });
        if (bad.length) { e.preventDefault(); e.stopImmediatePropagation(); bad[0].focus(); (window.aiToast || alert)(T.fix); }
      }, true);
    }
    if (inp.value) paint();
  }
  function scan() { [].forEach.call(document.querySelectorAll('input[data-phone]:not([data-phone-on])'), enhance); }
  scan();
  var t; new MutationObserver(function () { clearTimeout(t); t = setTimeout(scan, 150); }).observe(document.body, { childList: true, subtree: true });
  window.phoneCheck = function (v) { var p = parse(v); return p && p.isValid() ? p.number : null; };
})();
