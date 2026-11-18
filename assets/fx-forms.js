/* حفظ الفورمز من غير تحميل الصفحة (لكل فورمز POST العادية في المنصة):
   - بيبعت الفورم في الخلفية ويتابع التحويل (redirect) زي المتصفح بالظبط
   - لو رجع لنفس الصفحة: رسالة النجاح/الخطأ كتنبيه، ويتحدث الجزء اللي فيه الفورم بس (+ أي قسم ليه نفس الـ id)
   - لو الخطأ: اللي انت كاتبه بيفضل زي ما هو
   - لو التحويل لصفحة تانية: بينقلك عادي
   استثناء: data-no-ajax، أو فورم جوه [data-live-root] (ليه نظامه)، أو صفحات الدخول والخروج والتبديل */
(function () {
  var SKIP = /[?&]p=(login|logout|register|invite|switch|impersonate_stop|pref|workspaces|account)(&|$)/;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var stack = null;
  function toast(msg, type) {
    if (!msg) return;
    if (!stack) { stack = document.createElement('div'); stack.className = 'fixed bottom-4 inset-x-0 z-[110] flex flex-col items-center gap-2 pointer-events-none px-4'; document.body.appendChild(stack); }
    var ok = type !== 'error', t = document.createElement('div');
    t.className = 'pointer-events-auto flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm shadow-xl fx-in ' + (ok ? 'bg-ink-900 text-ink-50' : 'bg-red-600 text-[#fff]');
    t.setAttribute('role', ok ? 'status' : 'alert');
    t.innerHTML = (window.uic ? uic(ok ? 'check' : 'alert', 'w-4 h-4') : '') + '<span>' + esc(msg) + '</span>';
    stack.appendChild(t); setTimeout(function () { t.style.transition = 'opacity .3s, transform .3s'; t.style.opacity = '0'; t.style.transform = 'translateY(6px)'; setTimeout(function () { t.remove(); }, 320); }, ok ? 5000 : 6000);
    return t;
  }
  window.fxToast = toast;
  var AR = (document.documentElement.lang || 'ar').indexOf('ar') === 0;
  // الوحدة: نافذة تأكيد واحدة من تصميم المنصة بدل confirm() بتاع المتصفح
  // تأكيد قبل إرسال فورم: يمنع الإرسال، يسأل، ولو وافق يعيد الإرسال بنفس الزر
  window.fxGate = function (e, msg, o) { var f = e.target; if (f.dataset.fxOk === '1') { delete f.dataset.fxOk; return true; } e.preventDefault(); var sb = e.submitter; window.fxAsk(msg, o).then(function (ok) { if (!ok) return; f.dataset.fxOk = '1'; if (f.requestSubmit) f.requestSubmit(sb && sb.form === f ? sb : undefined); else f.submit(); }); return false; };
  // تأكيد متزامن الشكل لأي زر: يوقف الضغطة، يسأل بنافذة المنصة، ولو وافق يعيد نفس الضغطة فتكمل الكود زي ما هو
  var dz = function (m) { return /حذف|تحذف|امسح|تمسح|مسح|إزالة|شيل|Delete|delete|Remove|remove|revoke|Revoke|إلغاء الرابط|إلغاء المشاركة/.test(String(m)); };
  window.fxRe = function (msg, o) { var ev = window.event; if (window.__fxPass) return true; if (!ev || ev.type !== 'click' || !ev.target || !window.fxAsk) return confirm(msg); var t = ev.target; ev.preventDefault(); ev.stopImmediatePropagation(); window.fxAsk(msg, o || { danger: dz(msg) }).then(function (ok) { if (!ok) return; window.__fxPass = true; try { t.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window, ctrlKey: ev.ctrlKey, metaKey: ev.metaKey, shiftKey: ev.shiftKey, altKey: ev.altKey, button: ev.button, clientX: ev.clientX, clientY: ev.clientY })); } finally { window.__fxPass = false; } }); return false; };
  window.fxOk = function (msg, fn, o) { (window.fxAsk ? window.fxAsk(msg, o || { danger: dz(msg) }) : Promise.resolve(confirm(msg))).then(function (ok) { if (ok) fn(); }); };
  window.fxAsk = function (msg, o) { o = o || {}; var es = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    return new Promise(function (res) { var d = document.createElement('dialog'), out = o.input != null ? null : false;
      d.className = 'w-[min(440px,92vw)] p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl';
      d.innerHTML = '<form method="dialog" class="p-6"><div class="flex items-start gap-4"><span class="shrink-0 w-11 h-11 rounded-2xl grid place-items-center text-lg font-black ' + (o.danger ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-600') + '">' + (o.icon || (o.danger ? '!' : '?')) + '</span><div class="flex-1 min-w-0">'
        + (o.title ? '<h3 class="text-base font-bold text-ink-900 mb-1">' + es(o.title) + '</h3>' : '') + '<p class="text-sm text-ink-600 leading-7">' + es(msg) + '</p>'
        + (o.input != null ? '<input data-v class="input w-full mt-3" maxlength="80" value="' + es(o.input) + '">' : '') + '</div></div>'
        + '<div class="flex flex-wrap justify-end gap-2 mt-6"><button type="button" class="btn-ghost" data-x>' + es(o.cancel || (AR ? 'إلغاء' : 'Cancel')) + '</button><button type="submit" class="btn-primary' + (o.danger ? ' !bg-none !bg-red-600 hover:!bg-red-700 !border-red-600' : '') + '" data-ok>' + es(o.ok || (o.danger ? (AR ? 'حذف' : 'Delete') : (AR ? 'تأكيد' : 'OK'))) + '</button></div></form>';
      document.body.appendChild(d);
      d.querySelector('[data-x]').onclick = function () { d.close(); };
      d.querySelector('form').onsubmit = function (e) { e.preventDefault(); var v = d.querySelector('[data-v]'); out = v ? v.value.trim() : true; if (v && !out) { v.focus(); return; } d.close(); };
      d.addEventListener('close', function () { d.remove(); res(out); });
      d.showModal(); var f = d.querySelector('[data-v]') || d.querySelector('[data-ok]'); setTimeout(function () { f.focus(); if (f.select) f.select(); }, 30); }); };
  if (!window.dzAsk) window.dzAsk = window.fxAsk;
  // <form data-confirm="..."> أو <button data-confirm="..."> : تأكيد بنافذة المنصة قبل الإرسال
  document.addEventListener('submit', function (e) {
    var f = e.target, b = e.submitter, m = (b && b.getAttribute('data-confirm')) || f.getAttribute('data-confirm') || (f.hasAttribute('data-confirm-q') && f.dataset.q);
    if (!m) return; if (f.dataset.confirmed === '1') { delete f.dataset.confirmed; return; }
    var cond = f.getAttribute('data-confirm-if'); if (cond && !f.querySelector(cond)) return;
    e.preventDefault(); e.stopImmediatePropagation();
    var danger = (b && b.hasAttribute('data-confirm-danger')) || f.hasAttribute('data-confirm-danger') || /حذف|إزالة|delete|remove|فصل|إلغاء/i.test(m);
    window.fxAsk(m, { danger: danger }).then(function (ok) { if (!ok) return; f.dataset.confirmed = '1'; if (f.requestSubmit) f.requestSubmit(b || undefined); else f.submit(); });
  }, true);

  // الوحدة: أي alert قديم في المنصة يطلع بنفس رسالة المنصة بدل نافذة المتصفح
  window.alert = function (m) { toast(String(m == null ? '' : m), 'error'); };
  function pageOf(u) { try { return new URL(u, location.href).searchParams.get('p') || 'dashboard'; } catch (e) { return ''; } }
  function eligible(f) {
    if ((f.getAttribute('method') || 'get').toLowerCase() !== 'post' || f.hasAttribute('data-no-ajax') || f.hasAttribute('data-optimistic') || f.target && f.target !== '_self') return false;
    if (f.closest('[data-live-root]') || f.closest('dialog[data-modal-frame]')) return false;
    if (f.querySelector('input[name=action][value^="connect"], input[name=action][value*="oauth"]')) return false; // الربط بيحوّل لصفحة دخول المنصة (دومين تاني)
    var act = f.getAttribute('action') || location.href; if (SKIP.test(act) || SKIP.test(location.search) && !f.getAttribute('action')) return false;
    try { var u = new URL(act, location.href); if (u.host !== location.host) return false; } catch (e) { return false; }
    return true;
  }
  // الجزء اللي بيتحدث: أقرب منطقة معلّمة، أو section، أو card، أو الفورم نفسه
  function region(el) { return el.closest('[data-fx-region]') || el.closest('section') || el.closest('.card') || el; }
  function pathTo(el, root) { var p = []; while (el && el !== root) { var par = el.parentElement; if (!par) return null; p.unshift(Array.prototype.indexOf.call(par.children, el)); el = par; } return el === root ? p : null; }
  function byPath(root, p) { var el = root; for (var i = 0; i < p.length && el; i++) el = el.children[p[i]]; return el; }
  function noScripts(el) { return !el.querySelector('script'); }

  document.addEventListener('submit', function (e) {
    var f = e.target; if (e.defaultPrevented || !(f instanceof HTMLFormElement) || !eligible(f)) return;
    if (!window.fetch || !window.DOMParser || f.dataset.fxBusy) return;
    e.preventDefault();
    var btn = e.submitter || f.querySelector('button:not([type=button]), [type=submit]'), fd = new FormData(f);
    if (e.submitter && e.submitter.name) fd.append(e.submitter.name, e.submitter.value || '');
    var act = f.getAttribute('action') || location.href, main = document.getElementById('main');
    var reg = region(f), regPath = main ? pathTo(reg, main) : null, yBefore = window.scrollY;
    f.dataset.fxBusy = '1'; var oldHTML = null; if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); oldHTML = btn.innerHTML; btn.style.minWidth = btn.offsetWidth + 'px'; btn.innerHTML = '<span class="inline-block w-4 h-4 rounded-full border-2 border-current border-t-transparent animate-spin align-[-3px]"></span>'; }
    var bar = document.getElementById('nav-bar'); if (bar) { bar.classList.remove('done'); void bar.offsetWidth; bar.classList.add('go'); }
    var finish = function () { delete f.dataset.fxBusy; if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); if (oldHTML !== null) btn.innerHTML = oldHTML; btn.style.minWidth = ''; } if (bar) { bar.classList.remove('go'); bar.classList.add('done'); } };
    fetch(act, { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'follow' }).then(function (r) {
      var ct = r.headers.get('content-type') || '';
      if (/json/.test(ct)) return r.json().then(function (j) { finish(); if (j && (j.error || j.ok === false)) toast(j.error || j.message, 'error'); else toast(j && j.message ? j.message : (window.T && T.saved) || '✓'); });
      return r.text().then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html'), nm = doc.getElementById('main');
        // صفحة تانية خالص (تحويل لمكان تاني، أو تسجيل دخول): نروح لها
        if (!nm || pageOf(r.url) !== pageOf(location.href)) { location.href = r.url; return; }
        var flashes = Array.prototype.map.call(doc.querySelectorAll('[data-flash]'), function (x) { return [x.textContent.trim(), x.dataset.flash]; });
        var hasErr = flashes.some(function (x) { return x[1] === 'error'; });
        finish();
        flashes.forEach(function (x) { toast(x[0], x[1]); });
        if (!flashes.length && r.ok) toast((window.T && T.saved) || '✓');
        if (hasErr || !r.ok) return; // الخطأ: سيب اللي المستخدم كاتبه
        if (f.hasAttribute('data-fx-reload')) { setTimeout(function () { location.href = r.url; }, 450); return; } // إعدادات بتغيّر القايمة أو اللغة
        // تحديث الجزء بتاع الفورم + أي قسم ليه id مطابق ومافيهوش سكربتات
        var nr = reg.id ? nm.querySelector('#' + CSS.escape(reg.id)) : (regPath ? byPath(nm, regPath) : null);
        if (nr && nr.tagName === reg.tagName && noScripts(nr) && noScripts(reg)) reg.replaceWith(document.importNode(nr, true));
        Array.prototype.forEach.call(nm.querySelectorAll('section[id], .card[id], [data-fx-region][id]'), function (n) {
          var o = document.getElementById(n.id); if (!o || o === reg || o.contains(f) || !noScripts(n) || !noScripts(o) || o.innerHTML === n.innerHTML) return;
          o.innerHTML = n.innerHTML;
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-flash]'), function (x) { x.remove(); });
        if (doc.title) document.title = doc.title;
        if (r.url && r.url !== location.href) try { history.replaceState(null, '', r.url); } catch (x) {}
        window.scrollTo(0, yBefore);
        try { document.dispatchEvent(new CustomEvent('fx:swapped')); document.dispatchEvent(new CustomEvent('live:swapped')); } catch (x) {}
      });
    }).catch(function () { finish(); toast((window.T && T.liveFailed) || 'Error', 'error'); });
  });
})();
