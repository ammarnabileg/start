/* ردود سريعة من ملف الشركة جنب أي خانة رد (الرسائل والتعليقات والعملاء…): السعر، المواعيد، العنوان، التوصيل، الدفع، الأسئلة الشائعة */
(function () {
  var K = window.KBP; if (!K || !K.items || !K.items.length) return;
  var ico = { price: 'money', hours: 'clock', place: 'pin', delivery: 'route', pay: 'wallet', contact: 'phone', faq: 'chat' };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var pop = document.createElement('div'); pop.setAttribute('popover', ''); pop.id = 'kbp-pop'; pop.className = 'm-0 w-[min(340px,92vw)] max-h-[60vh] overflow-y-auto rounded-2xl border border-ink-200 bg-white p-1.5 shadow-2xl text-sm';
  pop.innerHTML = '<input type="search" class="input !mt-0 !py-1.5 text-sm mb-1" placeholder="⌕" data-kq>' + K.items.map(function (it, i) { return '<button type="button" data-kp="' + i + '" class="w-full flex items-start gap-2 rounded-xl px-2.5 py-2 text-start hover:bg-ink-50"><span class="mt-0.5 text-ink-400">' + (window.uic ? uic(ico[it.k] || 'file', 'w-4 h-4') : '') + '</span><span class="min-w-0"><span class="block font-medium text-ink-900 truncate" dir="auto">' + esc(it.l) + '</span><span class="block text-[11px] text-ink-500 truncate" dir="auto">' + esc(it.t.split('\n')[0]) + '</span></span></button>'; }).join('') +
    '<a href="' + esc(K.url) + '" class="block text-center text-[11px] link py-1.5">' + esc(K.edit) + '</a>';
  document.body.appendChild(pop);
  var target = null;
  function attach(ta) {
    if (ta.dataset.kbp) return; ta.dataset.kbp = '1';
    var b = document.createElement('button'); b.type = 'button'; b.className = 'kbp-btn inline-flex items-center gap-1 rounded-lg border border-lime-200 bg-lime-50 px-2 py-1 text-[11px] font-medium text-lime-800 hover:bg-lime-100 mb-1';
    b.innerHTML = (window.uic ? uic('layers', 'w-3.5 h-3.5') : '') + esc(K.btn); b.setAttribute('popovertarget', 'kbp-pop');
    b.addEventListener('click', function () { target = ta; });
    ta.parentNode.insertBefore(b, ta);
  }
  function scan() { Array.prototype.forEach.call(document.querySelectorAll('textarea[data-saved-replies], textarea[data-kb-snips], #ld-log-body'), attach); }
  scan(); document.addEventListener('live:swapped', scan); new MutationObserver(function () { scan(); }).observe(document.body, { childList: true, subtree: true });
  pop.addEventListener('input', function (e) { if (!e.target.matches('[data-kq]')) return; var q = e.target.value.trim().toLowerCase(); Array.prototype.forEach.call(pop.querySelectorAll('[data-kp]'), function (b) { var it = K.items[+b.dataset.kp]; b.style.display = !q || (it.l + ' ' + it.t).toLowerCase().indexOf(q) > -1 ? '' : 'none'; }); });
  pop.addEventListener('click', function (e) {
    var b = e.target.closest('[data-kp]'); if (!b || !target) return; var t = K.items[+b.dataset.kp].t;
    var s = target.selectionStart != null ? target.selectionStart : target.value.length, v = target.value, pre = v.slice(0, s), sep = pre && !/\s$/.test(pre) ? (target.tagName === 'INPUT' ? ' ' : '\n') : '';
    target.value = pre + sep + t + v.slice(target.selectionEnd || s); target.dispatchEvent(new Event('input', { bubbles: true })); target.focus();
    try { pop.hidePopover(); } catch (x) {}
  });
})();
