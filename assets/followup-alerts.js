/* تنبيه بميعاد المتابعة في أي صفحة: كل دقيقة بنسأل السيرفر، ولو فيه ميعاد جه بيظهر كارت تحت + إشعار المتصفح + رقم الجرس */
(function () {
  var C = window.FU; if (!C || !window.fetch) return; var L = C.L, key = 'fu_last_' + C.ws, busy = false, first = null;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  var box = document.createElement('div'); box.id = 'fu-box'; box.className = 'fixed top-20 end-4 z-[95] flex flex-col gap-2 w-[min(360px,calc(100vw-32px))]'; document.body.appendChild(box);
  function bell(n) { var b = document.getElementById('tb-bell'); if (!b) return; var s = b.querySelector('span.absolute');
    if (!n) { if (s) s.remove(); return; } if (!s) { s = document.createElement('span'); s.className = 'absolute -top-1.5 -end-1.5 bg-rose-600 text-[#fff] text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center ring-2 ring-white'; b.appendChild(s); } s.textContent = n; }
  // أكتر من تنبيه في نفس الوقت = كارت واحد مجمّع بدل كروت فوق بعض
  var items = [], tmr = null;
  function card(it) { items.push(it); items = items.slice(-20); render(); clearTimeout(tmr); tmr = setTimeout(function () { var c = box.querySelector('.fu-card'); if (c) { c.style.transition = 'opacity .4s'; c.style.opacity = '0'; setTimeout(function () { items = []; render(); }, 400); } }, 25000); } // بتختفي لوحدها، والتنبيه بيفضل في الجرس
  function render() { box.querySelectorAll('.fu-card').forEach(function (x) { x.remove(); }); if (!items.length) return;
    var one = items.length === 1, it = items[items.length - 1], el = document.createElement('div'); el.className = 'fu-card rounded-2xl border border-ink-200 bg-white shadow-2xl p-4 flex gap-3 items-start'; el.setAttribute('role', 'alert');
    var body = one ? '<p class="fu-b text-xs text-ink-600 mt-0.5 break-words">' + esc(it.body) + '</p>' : '<p class="fu-b text-xs text-ink-600 mt-0.5">' + esc(items.slice(-3).reverse().map(function (x) { return x.who || x.body; }).join('، ')) + (items.length > 3 ? ' …' : '') + '</p>';
    el.innerHTML = '<span class="fu-ic w-9 h-9 rounded-xl bg-red-50 text-red-600 grid place-items-center shrink-0">' + (window.uic ? uic('calendar', 'w-5 h-5') : '⏰') + '</span><div class="flex-1 min-w-0"><b class="block text-sm text-ink-900">' + esc(one ? L.title : L.many.replace('%s', items.length)) + '</b>' + body +
      '<div class="flex gap-2 mt-2"><a href="' + esc(one ? it.url : C.dueUrl) + '" class="btn-primary !py-1 !text-xs">' + esc(one ? L.open : L.openAll) + '</a><button type="button" class="btn-ghost !py-1 !text-xs" data-x>' + esc(L.later) + '</button></div></div>';
    el.querySelector('[data-x]').addEventListener('click', function () { items = []; render(); }); box.prepend(el); }
  function native(it) { try { if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return; var n = new Notification(L.title, { body: it.body, tag: 'fu-' + it.id }); n.onclick = function () { window.focus(); location.href = it.url; }; } catch (e) {} }
  function ask() { if (!('Notification' in window) || Notification.permission !== 'default' || get('fu_perm_asked') || box.querySelector('[data-perm]')) return;
    var el = document.createElement('div'); el.dataset.perm = 1; el.className = 'rounded-2xl border border-ink-200 bg-white shadow-xl p-3 text-xs text-ink-600 flex items-center gap-2';
    el.innerHTML = '<span class="flex-1">' + esc(L.perm) + '</span><button type="button" class="btn-primary !py-1 !text-xs">' + esc(L.permOn) + '</button><button type="button" class="text-ink-400 px-1" data-x>✕</button>';
    el.querySelector('.btn-primary').addEventListener('click', function () { set('fu_perm_asked', 1); el.remove(); Notification.requestPermission(); });
    el.querySelector('[data-x]').addEventListener('click', function () { set('fu_perm_asked', 1); el.remove(); }); box.appendChild(el); }
  function tick() {
    if (busy) return; busy = true; var last = +(get(key) || 0);
    fetch(C.url + '&after=' + last, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(function (j) { busy = false; if (!j || !j.ok) return;
      j.items.forEach(function (it) { if (it.id > last) last = it.id; card(it); native(it); }); set(key, String(last)); if (first === null) first = j.unseen; var b0 = +((document.getElementById('tb-bell') || { dataset: {} }).dataset.n || 0); bell(Math.max(0, b0 + j.unseen - first));
      if (j.items.length) ask(); }).catch(function () { busy = false; });
  }
  setTimeout(tick, 1500); setInterval(tick, 60000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
})();
