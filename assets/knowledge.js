/* قاعدة المعرفة: تنقل بين الأقسام، حفظ تلقائي، رفع ملفات، وتجربة الرد */
(function () {
  var K = window.KB, root = document.getElementById('kb'); if (!root || !K) return;
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  function post(fd) { return fetch('index.php?p=knowledge', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }); }

  // التنقل
  function go(k) {
    root.querySelectorAll('[data-kb]').forEach(function (s) { s.classList.toggle('hidden', s.dataset.kb !== k); });
    root.querySelectorAll('.kb-tab').forEach(function (b) { var on = b.dataset.kbGo === k; b.classList.toggle('bg-brand-50', on); b.classList.toggle('text-brand-700', on); b.classList.toggle('font-medium', on); });
    history.replaceState(null, '', '#' + k);
  }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-kb-go]'); if (b) { go(b.dataset.kbGo); if (innerWidth < 1024) root.scrollIntoView({ behavior: 'smooth' }); } });
  var start = location.hash.slice(1); if (!start || !root.querySelector('[data-kb="' + start + '"]')) start = 'files'; go(start);

  function progress() {
    var dots = root.querySelectorAll('.kb-dot'), done = 0; dots.forEach(function (d) { if (d.classList.contains('bg-lime-500')) done++; });
    document.getElementById('kb-done').textContent = done;
    document.getElementById('kb-ring').setAttribute('stroke-dasharray', (97.4 * done / K.total).toFixed(1) + ' 97.4');
  }
  function dot(k, ok) { var d = root.querySelector('[data-dot="' + k + '"]'); if (d) { d.classList.toggle('bg-lime-500', ok); d.classList.toggle('bg-ink-300', !ok); progress(); } }

  // الحفظ التلقائي (بعد ما يبطّل كتابة بثانية)
  function collect(form) {
    var fd = new FormData(form);
    form.querySelectorAll('.kb-row').forEach(function (row, i) { row.querySelectorAll('[data-col]').forEach(function (el) { fd.append('d[rows][' + i + '][' + el.dataset.col + ']', el.value); }); });
    fd.append('action', 'kb_save'); fd.append('section', form.dataset.section); return fd;
  }
  var timers = {};
  function save(form) {
    clearTimeout(timers[form.dataset.section]);
    timers[form.dataset.section] = setTimeout(function () {
      var badge = form.querySelector('.kb-saved');
      post(collect(form)).then(function (d) {
        if (d.error) { window.aiToast ? aiToast(d.error) : alert(d.error); return; }
        badge.classList.remove('opacity-0'); setTimeout(function () { badge.classList.add('opacity-0'); }, 1400);
        dot(form.dataset.section, !!d.filled);
      }).catch(function () { window.aiToast ? aiToast(K.err) : alert(K.err); });
    }, 700);
  }
  root.querySelectorAll('.kb-form').forEach(function (f) {
    f.addEventListener('input', function () { save(f); }); f.addEventListener('change', function () { save(f); });
    f.addEventListener('submit', function (e) { e.preventDefault(); });
  });
  // صفوف الخدمات والأسئلة
  root.addEventListener('click', function (e) {
    var add = e.target.closest('.kb-add');
    if (add) {
      var form = add.closest('form'), rows = form.querySelector('.kb-rows'), last = rows.querySelector('.kb-row:last-child');
      var n = last.cloneNode(true); n.querySelectorAll('[data-col]').forEach(function (x) { x.value = ''; }); rows.appendChild(n); n.querySelector('[data-col]').focus(); return;
    }
    var del = e.target.closest('.kb-del');
    if (del) { var f = del.closest('form'), row = del.closest('.kb-row'); if (f.querySelectorAll('.kb-row').length > 1) row.remove(); else row.querySelectorAll('[data-col]').forEach(function (x) { x.value = ''; }); save(f); }
  });
  // تكبير خانة التفاصيل مع الكتابة
  root.addEventListener('input', function (e) { if (e.target.matches('textarea[data-col]')) { e.target.style.height = 'auto'; e.target.style.height = e.target.scrollHeight + 'px'; } });

  // ألوان الهوية: دواير من الأكواد المكتوبة
  var colors = root.querySelector('[name="d[colors]"]'), sw = document.getElementById('kb-swatches');
  function swatches() { if (!sw) return; sw.innerHTML = ''; (colors.value.match(/#[0-9a-f]{3,8}\b/gi) || []).forEach(function (c) { var s = document.createElement('span'); s.className = 'w-8 h-8 rounded-full border border-ink-200 shadow-sm'; s.style.background = c; s.title = c; sw.appendChild(s); }); }
  if (colors) { colors.addEventListener('input', swatches); swatches(); }

  // الملفات
  var input = document.getElementById('kb-file'), drop = document.getElementById('kb-drop'), list = document.getElementById('kb-files');
  function upload(files) {
    [].slice.call(files).forEach(function (file) {
      var ph = document.createElement('div'); ph.className = 'rounded-xl border border-brand-200 bg-brand-50/50 p-3 text-sm flex items-center gap-3';
      ph.innerHTML = '<svg class="w-4 h-4 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg><span class="flex-1 truncate"></span><span class="text-xs text-ink-500"></span>';
      ph.querySelector('span').textContent = file.name; ph.querySelectorAll('span')[1].textContent = K.uploading; list.prepend(ph);
      var fd = new FormData(); fd.append('action', 'kb_upload'); fd.append('file', file);
      post(fd).then(function (d) {
        if (d.error) { ph.className = 'rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700'; ph.textContent = d.error; setTimeout(function () { ph.remove(); }, 8000); return; }
        location.hash = 'files'; location.reload();
      }).catch(function () { ph.textContent = K.err; });
    });
  }
  if (input) input.addEventListener('change', function () { upload(input.files); input.value = ''; });
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('border-brand-500', 'bg-brand-50'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('border-brand-500', 'bg-brand-50'); }); });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) upload(e.dataTransfer.files); });
  }
  if (list) {
    list.addEventListener('click', function (e) {
      var row = e.target.closest('.kb-file'); if (!row) return;
      if (e.target.closest('.kb-view')) { row.querySelector('.kb-prev').classList.toggle('hidden'); return; }
      if (e.target.closest('.kb-rm')) { if (!confirm(K.del)) return; var fd = new FormData(); fd.append('action', 'kb_file_delete'); fd.append('id', row.dataset.id); post(fd).then(function () { row.remove(); if (!list.querySelector('.kb-file')) dot('files', false); }); }
    });
    list.addEventListener('change', function (e) {
      var c = e.target.closest('.kb-on'); if (!c) return; var row = c.closest('.kb-file');
      var fd = new FormData(); fd.append('action', 'kb_file_toggle'); fd.append('id', row.dataset.id); fd.append('on', c.checked ? '1' : '0');
      post(fd).then(function () { row.classList.toggle('opacity-60', !c.checked); });
    });
  }

  // جرّب الرد
  var ch = 'message', msg = document.getElementById('kb-msg'), out = document.getElementById('kb-out'), run = document.getElementById('kb-run');
  document.querySelectorAll('#kb-ch [data-ch]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); ch = a.dataset.ch; document.querySelectorAll('#kb-ch a').forEach(function (x) { x.classList.toggle('on', x === a); }); }); });
  document.querySelectorAll('#kb-samples button').forEach(function (b) { b.addEventListener('click', function () { msg.value = b.textContent; msg.focus(); }); });
  if (run) run.addEventListener('click', function () {
    if (!msg.value.trim()) { msg.focus(); return; }
    run.disabled = true; out.innerHTML = '<div class="rounded-2xl border border-ink-200 bg-ink-50 p-4 text-sm text-ink-500 animate-pulse">…</div>';
    var fd = new FormData(); fd.append('action', 'kb_test'); fd.append('message', msg.value); fd.append('channel', ch);
    post(fd).then(function (d) {
      if (d.error) { out.innerHTML = '<p class="text-sm text-red-600"></p>'; out.firstChild.textContent = d.error; return; }
      var hand = d.action === 'handoff';
      out.innerHTML = '<div class="rounded-2xl border p-4 ' + (hand ? 'border-amber-200 bg-amber-50' : 'border-lime-200 bg-lime-50') + '">'
        + '<div class="flex flex-wrap items-center gap-2 text-sm font-semibold ' + (hand ? 'text-amber-800' : 'text-lime-800') + '"><span class="kb-act"></span><span class="ms-auto text-xs font-normal text-ink-500 kb-conf"></span></div>'
        + '<p class="kb-reason text-xs text-ink-600 mt-1"></p>'
        + '<div class="mt-3 rounded-xl bg-white border border-ink-200 p-3 text-sm whitespace-pre-wrap kb-reply" dir="auto"></div>'
        + '<p class="text-[10px] text-ink-400 mt-2 kb-meta" dir="ltr"></p></div>';
      out.querySelector('.kb-act').textContent = hand ? K.handoff : K.reply;
      out.querySelector('.kb-conf').textContent = K.conf + ': ' + Math.round((d.confidence || 0) * 100) + '%';
      out.querySelector('.kb-reason').textContent = d.reason ? K.reason + ': ' + d.reason : '';
      out.querySelector('.kb-reply').textContent = d.reply || '—';
      out.querySelector('.kb-meta').textContent = (d.model || '') + (d.cost != null ? ' · $' + (+d.cost).toFixed(5) : '');
    }).catch(function () { out.textContent = K.err; }).finally(function () { run.disabled = false; });
  });
})();
