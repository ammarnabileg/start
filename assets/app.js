(function () {
  // القائمة الجانبية على الموبايل
  var sb = document.getElementById('sidebar'), bd = document.getElementById('backdrop'), btn = document.getElementById('menuBtn');
  function toggle(open) { if (!sb) return; sb.classList.toggle('translate-x-full', !open); bd.classList.toggle('hidden', !open); }
  if (btn) btn.addEventListener('click', function () { toggle(sb.classList.contains('translate-x-full')); });
  if (bd) bd.addEventListener('click', function () { toggle(false); });

  // منع الضغط مرتين على أي زرار إرسال
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      var b = ev.submitter || f.querySelector('button:not([type=button])');
      if (!b) return;
      setTimeout(function () { if (ev.defaultPrevented) return; b.disabled = true; b.dataset.label = b.textContent; b.textContent = 'جاري التنفيذ...'; }, 0);
    });
  });

  // Enter يبعت، و Shift+Enter سطر جديد
  document.querySelectorAll('textarea[data-enter-submit]').forEach(function (t) {
    t.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); if (t.value.trim()) t.form.requestSubmit(); }
    });
  });

  // نزّل لآخر المحادثة
  var thread = document.getElementById('thread');
  if (thread) thread.scrollTop = thread.scrollHeight;

  // الرد على تعليق معين
  var replyTo = document.getElementById('replyTo'), hint = document.getElementById('replyHint');
  document.querySelectorAll('[data-reply-to]').forEach(function (b) {
    b.addEventListener('click', function () {
      replyTo.value = b.dataset.replyTo;
      hint.classList.remove('hidden');
      hint.querySelector('span').textContent = b.dataset.replyName || 'تعليق';
      var ta = document.querySelector('#replyForm textarea'); ta.focus();
    });
  });
  var cancel = document.getElementById('cancelReply');
  if (cancel) cancel.addEventListener('click', function () { replyTo.value = ''; hint.classList.add('hidden'); });

  // صفحة المنشور الجديد
  var content = document.getElementById('content'), count = document.getElementById('charCount');
  if (content && count) {
    var upd = function () { count.textContent = content.value.length; count.parentNode.classList.toggle('text-red-600', content.value.length > 280); };
    content.addEventListener('input', upd); upd();
  }
  var modes = document.querySelectorAll('.mode'), whenBox = document.getElementById('whenBox'), submitBtn = document.getElementById('submitBtn');
  function syncMode() {
    var m = document.querySelector('.mode:checked'); if (!m) return;
    whenBox.classList.toggle('hidden', m.value !== 'schedule');
    submitBtn.textContent = { schedule: 'جدولة المنشور', now: 'انشر دلوقتي', draft: 'حفظ المسودة' }[m.value];
  }
  modes.forEach(function (r) { r.addEventListener('change', syncMode); }); if (modes.length) syncMode();

  var all = document.getElementById('selectAll');
  if (all) all.addEventListener('click', function () {
    var boxes = document.querySelectorAll('.acc'), on = Array.prototype.some.call(boxes, function (b) { return !b.checked; });
    boxes.forEach(function (b) { b.checked = on; });
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
})();
