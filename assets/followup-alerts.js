/* تنبيه بميعاد المتابعة في أي صفحة: كل دقيقة بنسأل السيرفر، ولو فيه ميعاد جه بيظهر كارت تحت + إشعار المتصفح + رقم الجرس */
(function () {
  var C = window.FU; if (!C || !window.fetch) return; var L = C.L, key = 'fu_last_' + C.ws, busy = false, first = null;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  var box = document.createElement('div'); box.id = 'fu-box'; box.className = 'fixed bottom-4 end-4 z-[95] flex flex-col gap-2 w-[min(360px,calc(100vw-32px))]'; document.body.appendChild(box);
  function bell(n) { var b = document.getElementById('tb-bell'); if (!b) return; var s = b.querySelector('span.absolute');
    if (!n) { if (s) s.remove(); return; } if (!s) { s = document.createElement('span'); s.className = 'absolute -top-1.5 -end-1.5 bg-rose-600 text-[#fff] text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center ring-2 ring-white'; b.appendChild(s); } s.textContent = n; }
  function card(it) {
    var el = document.createElement('div'); el.className = 'fu-card rounded-2xl border border-ink-200 bg-white shadow-2xl p-4 flex gap-3 items-start'; el.setAttribute('role', 'alert'); el.dataset.id = it.id;
    el.innerHTML = '<span class="w-9 h-9 rounded-xl bg-red-50 text-red-600 grid place-items-center shrink-0">' + (window.uic ? uic('calendar', 'w-5 h-5') : '⏰') + '</span><div class="flex-1 min-w-0"><b class="block text-sm text-ink-900">' + esc(L.title) + '</b><p class="text-sm text-ink-600 mt-0.5 break-words" dir="auto">' + esc(it.body) + '</p>' +
      '<div class="flex gap-2 mt-2"><a href="' + esc(it.url) + '" class="btn-primary !py-1 !text-xs">' + esc(L.open) + '</a><button type="button" class="btn-ghost !py-1 !text-xs" data-x>' + esc(L.later) + '</button></div></div>';
    el.querySelector('[data-x]').addEventListener('click', function () { el.remove(); }); box.appendChild(el);
    while (box.querySelectorAll('.fu-card').length > 3) box.querySelector('.fu-card').remove();
  }
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
