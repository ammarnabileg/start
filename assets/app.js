(function () {
  var T = window.T || {};
  // القائمة الجانبية على الموبايل
  var sb = document.getElementById('sidebar'), bd = document.getElementById('backdrop'), btn = document.getElementById('menuBtn');
  function toggle(open) { if (!sb) return; sb.classList.toggle('sb-open', open); bd.classList.toggle('hidden', !open); }
  if (btn) btn.addEventListener('click', function () { toggle(!sb.classList.contains('sb-open')); });
  if (bd) bd.addEventListener('click', function () { toggle(false); });

  // منع الضغط مرتين على أي زرار إرسال (الفورمز اللي بتتبعت من غير ريفرش ليها معاملة خاصة في live.js)
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (f.closest('[data-live-root]')) return;
    var b = ev.submitter || f.querySelector('button:not([type=button])');
    if (!b) return;
    setTimeout(function () { if (ev.defaultPrevented) return; b.disabled = true; b.dataset.label = b.textContent; b.textContent = T.working; }, 0);
  });

  // Enter يبعت، و Shift+Enter سطر جديد
  document.addEventListener('keydown', function (e) {
    var t = e.target;
    if (!t.matches || !t.matches('textarea[data-enter-submit]')) return;
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); if (t.value.trim() || (t.form.querySelector('input[type=file]') || {}).value) t.form.requestSubmit(); }
  });

  // نزّل لآخر المحادثة
  var thread = document.getElementById('thread');
  if (thread) thread.scrollTop = thread.scrollHeight;

  // الرد على تعليق معين
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-reply-to]');
    if (b) {
      var replyTo = document.getElementById('replyTo'), hint = document.getElementById('replyHint');
      if (!replyTo || !hint) return;
      replyTo.value = b.dataset.replyTo;
      hint.classList.remove('hidden');
      hint.querySelector('span').textContent = b.dataset.replyName || T.comment;
      var ta = document.querySelector('#replyForm textarea'); if (ta) ta.focus();
      return;
    }
    if (e.target.closest && e.target.closest('#cancelReply')) {
      document.getElementById('replyTo').value = '';
      document.getElementById('replyHint').classList.add('hidden');
    }
  });

  // صفحة المنشور الجديد
  var content = document.getElementById('content'), count = document.getElementById('charCount');
  if (content && count) {
    var upd = function () { count.textContent = content.value.length; count.parentNode.classList.toggle('text-red-600', content.value.length > 280); };
    content.addEventListener('input', upd); upd();
  }
  var modes = document.querySelectorAll('.mode'), whenBox = document.getElementById('whenBox'), submitBtn = document.getElementById('submitBtn');
  function syncMode() {
    var m = document.querySelector('.mode:checked'); if (!m) return;
    if (whenBox) whenBox.classList.toggle('hidden', m.value !== 'schedule');
    var lbl = { schedule: T.schedule, now: T.now, draft: T.draft, queue: T.queue }[m.value]; if (submitBtn && lbl) submitBtn.textContent = lbl;
  }
  modes.forEach(function (r) { r.addEventListener('change', syncMode); }); if (modes.length) syncMode();

  var all = document.getElementById('selectAll');
  if (all) all.addEventListener('click', function () {
    var boxes = document.querySelectorAll('.acc'), on = Array.prototype.some.call(boxes, function (b) { return !b.checked; });
    boxes.forEach(function (b) { b.checked = on; b.dispatchEvent(new Event('change')); });
  });

  var mi = document.getElementById('mediaInput'), mp = document.getElementById('mediaPreview');
  if (mi) mi.addEventListener('change', function () {
    mp.innerHTML = '';
    Array.prototype.forEach.call(mi.files, function (f) {
      var el;
      if (f.type.indexOf('image/') === 0) { el = document.createElement('img'); el.src = URL.createObjectURL(f); el.className = 'w-20 h-20 object-cover rounded-lg'; }
      else { el = document.createElement('div'); el.className = 'w-20 h-20 rounded-lg bg-slate-100 text-xs flex items-center justify-center p-1 text-center break-all'; el.textContent = f.name; }
      mp.appendChild(el);
    });
  });
  // قفل القوايم المنسدلة لما تدوس برا
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details[data-dropdown][open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  // أزرار النسخ
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-copy]');
    if (!b) return;
    var el = document.querySelector(b.dataset.copy), v = el.value;
    var done = function () { var t = b.textContent; b.textContent = T.copied; setTimeout(function () { b.textContent = t; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(v).then(done);
    else { var ta = document.createElement('textarea'); ta.value = v; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); done(); }
  });

  // النوافذ المنبثقة: أي عنصر فيه data-modal="رابط" بيفتح الصفحة دي جوه نافذة
  var modal = document.getElementById('modal'), frame = document.getElementById('modalFrame'), reloadOnClose = false;
  document.querySelectorAll('[data-modal]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!modal || !modal.showModal) return; // متصفح قديم: يفتح الصفحة عادي
      e.preventDefault();
      var u = el.getAttribute('data-modal');
      frame.src = u + (u.indexOf('?') < 0 ? '?' : '&') + 'embed=1';
      document.getElementById('modalTitle').textContent = el.getAttribute('data-modal-title') || el.textContent.trim();
      reloadOnClose = el.hasAttribute('data-modal-reload');
      modal.showModal();
    });
  });
  if (modal) {
    modal.addEventListener('close', function () { frame.src = 'about:blank'; if (reloadOnClose) location.reload(); });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.close(); });
    document.querySelectorAll('[data-modal-close]').forEach(function (b) { b.addEventListener('click', function () { modal.close(); }); });
  }
})();
