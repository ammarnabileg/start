/* رقمي: محادثة SMS من خط الموبايل — إرسال، عدّاد الأجزاء، تحديث تلقائي للرسايل والحالة. */
(function () {
  var X = window.SMX; if (!X) return;
  var S = X.s;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  var q = $('#sm-q');
  if (q) q.addEventListener('input', function () { var s = q.value.trim().toLowerCase(); $$('.sm-th').forEach(function (a) { a.style.display = !s || a.dataset.q.indexOf(s) > -1 ? '' : 'none'; }); });

  var box = $('#sm-msgs'); if (!box || !X.to) return;
  var last = 0, seen = {};
  function tick(st) { return { pending: '🕓', processed: '✓', sent: '✓', delivered: '✓✓', failed: '!' }[st] || ''; }
  function bubble(m) {
    var out = m.direction === 'out', el = document.createElement('div');
    el.className = 'flex ' + (out ? 'justify-end' : 'justify-start'); el.dataset.id = m.id;
    el.innerHTML = '<div class="max-w-[78%] rounded-2xl px-3.5 py-2 text-sm ' + (out ? (m.state === 'failed' ? 'bg-red-600 text-white' : 'bg-brand-600 text-white') + ' rounded-se-sm' : 'bg-white border border-ink-200 text-ink-900 rounded-ss-sm') + '">'
      + '<div class="whitespace-pre-wrap break-words" dir="auto">' + esc(m.body) + '</div>'
      + '<div class="sm-meta text-[10px] mt-1 opacity-70 flex items-center gap-1 justify-end">' + esc(m.time || (m.created_at || '').slice(11, 16)) + (out ? ' <span class="sm-st" title="' + esc(S[m.state] || m.state) + '">' + tick(m.state) + '</span>' : '') + '</div>'
      + (m.state === 'failed' && m.error ? '<div class="text-[11px] mt-1">' + esc(m.error) + '</div>' : '') + '</div>';
    return el;
  }
  function add(rows) {
    var stick = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
    rows.forEach(function (m) { if (seen[m.id]) return; seen[m.id] = 1; box.appendChild(bubble(m)); last = Math.max(last, +m.id); });
    if (stick || rows.length) box.scrollTop = box.scrollHeight;
  }
  add(X.msgs);
  box.scrollTop = box.scrollHeight;

  function poll() {
    fetch(X.url + '&ajax=1&to=' + encodeURIComponent(X.to) + '&after=' + last, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      add(d.rows || []);
      (d.states || []).forEach(function (s) { var el = box.querySelector('[data-id="' + s.id + '"] .sm-st'); if (el) { el.textContent = tick(s.state); el.title = S[s.state] || s.state; } });
    }).catch(function () {});
  }
  setInterval(function () { if (!document.hidden) poll(); }, 8000);
  $('#sm-live').textContent = '● ' + S.live;

  var ta = $('#sm-text'), cnt = $('#sm-count'), form = $('#sm-form'), btn = $('#sm-send');
  function count() {
    var t = ta.value, uni = /[^\x00-\x7F£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¡ÄÖÑÜ§¿äöñüà€^{}\[~\]|\\]/.test(t), n = t.length;
    var per = uni ? (n > 70 ? 67 : 70) : (n > 160 ? 153 : 160), seg = n ? Math.ceil(n / per) : 0;
    cnt.textContent = f(S.seg, n, seg) + (uni ? ' · ' + S.unicode : '');
    cnt.className = 'text-[11px] mt-1 ' + (seg > 3 ? 'text-amber-700' : 'text-ink-400');
  }
  ta.addEventListener('input', count); count();
  ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = ta.value.trim(); if (!text || btn.disabled) return;
    btn.disabled = true;
    var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('ajax', '1'); fd.append('action', 'send'); fd.append('to', X.to); fd.append('text', text);
    fetch(X.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) { window.aiToast && window.aiToast(d.error || S.err); return; }
      ta.value = ''; count(); poll();
    }).catch(function () { window.aiToast && window.aiToast(S.err); }).finally(function () { btn.disabled = false; ta.focus(); });
  });
})();
