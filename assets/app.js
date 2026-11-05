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
    var lbl = { schedule: T.schedule, now: T.now, draft: T.draft, queue: T.queue }[m.value]; if (submitBtn && lbl && !document.getElementById('cp-go-t')) submitBtn.textContent = lbl; // صفحة الإنشاء الجديدة بتغير النص بنفسها
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
  // القوائم المنسدلة: بتتفتح "طايرة" فوق الصفحة، عشان ماتتقصش جوه الجداول أو الكروت اللي ليها سكرول
  function placeMenu(d) {
    var m = d.querySelector(':scope > .menu, :scope > form.menu, :scope > div.menu');
    var sum = d.querySelector('summary');
    if (!m || !sum) return;
    // القايمة اللي في آخر السايد بار بتفتح لفوق ومكانها مظبوط أصلاً
    if (m.classList.contains('bottom-full')) return;
    m.style.position = 'fixed';
    m.style.insetInlineStart = 'auto'; m.style.insetInlineEnd = 'auto'; m.style.left = 'auto'; m.style.right = 'auto';
    m.style.marginTop = '0';
    var r = sum.getBoundingClientRect(), mw = m.offsetWidth, mh = m.offsetHeight, vw = innerWidth, vh = innerHeight, gap = 6;
    var rtl = document.documentElement.dir === 'rtl';
    // نحاذي طرف القايمة مع طرف الزرار (حسب اتجاه اللغة) ونتأكد إنها جوه الشاشة
    var left = rtl ? r.left : r.right - mw;
    if (m.classList.contains('end-0')) left = rtl ? r.left : r.right - mw;
    if (m.classList.contains('start-0') || m.classList.contains('right-0')) left = rtl ? r.right - mw : r.left;
    left = Math.max(8, Math.min(left, vw - mw - 8));
    var top = r.bottom + gap;
    if (top + mh > vh - 8 && r.top - gap - mh > 8) top = r.top - gap - mh; // مفيش مكان تحت: نفتح لفوق
    top = Math.max(8, Math.min(top, vh - mh - 8));
    m.style.left = left + 'px'; m.style.top = top + 'px';
    m.style.maxHeight = (vh - 16) + 'px'; m.style.overflowY = 'auto';
  }
  document.addEventListener('toggle', function (e) {
    var d = e.target;
    if (d.tagName === 'DETAILS' && d.hasAttribute('data-dropdown') && d.open) {
      // قايمة واحدة مفتوحة بس في المرة
      document.querySelectorAll('details[data-dropdown][open]').forEach(function (x) { if (x !== d && !x.contains(d)) x.removeAttribute('open'); });
      placeMenu(d);
    }
  }, true);
  // السكرول أو تغيير حجم الشاشة بيقفل القوايم المفتوحة (عشان ماتفضلش طايرة في مكان غلط)
  function closeFloating(e) {
    document.querySelectorAll('details[data-dropdown][open]').forEach(function (d) {
      var m = d.querySelector('.menu');
      if (m && m.style.position === 'fixed' && !(e && e.target && m.contains(e.target))) d.removeAttribute('open');
    });
  }
  window.addEventListener('resize', closeFloating);
  document.addEventListener('scroll', closeFloating, true);

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

/* مهام الذكاء الاصطناعي: كتابة منشور / اقتراح رد */
function aiToast(msg, kind) { // kind: 'ok' = نجاح (أخضر، 3 ثواني)، غير كده = خطأ (أحمر، 6 ثواني)
  var ok = kind === 'ok', t = document.createElement('div');
  t.className = 'fixed bottom-5 start-1/2 -translate-x-1/2 rtl:translate-x-1/2 z-[70] max-w-md rounded-xl ' + (ok ? 'bg-lime-600' : 'bg-red-600') + ' text-[#fff] text-sm px-4 py-3 shadow-xl';
  t.setAttribute('role', ok ? 'status' : 'alert');
  var prev = document.querySelectorAll('.ai-toast'); [].forEach.call(prev, function (p, i) { p.style.bottom = (20 + 56 * (prev.length - i)) + 'px'; });
  t.classList.add('ai-toast'); t.textContent = msg; document.body.appendChild(t); setTimeout(function () { t.remove(); }, ok ? 3000 : 6000);
}
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-ai-task]'); if (!b || b.disabled) return;
  var task = b.dataset.aiTask, form = b.closest('form');
  var ta = b.dataset.aiTarget ? document.querySelector(b.dataset.aiTarget) : (form && form.querySelector('textarea'));
  if (!ta) return;
  var th = document.getElementById('thread');
  var fd = new FormData(); fd.append('task', task); fd.append('text', ta.value);
  if (task === 'reply' && th) fd.append('context', th.innerText.replace(/\n{3,}/g, '\n\n').slice(-3000));
  var label = b.querySelector('span'), old = label ? label.textContent : '';
  b.disabled = true; b.classList.add('animate-pulse'); if (label) label.textContent = (window.T && T.working) || '...';
  fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': (document.querySelector('meta[name=csrf-token]') || {}).content || '' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.error) { aiToast(d.error); return; }
      ta.value = d.text; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus();
      ta.classList.add('ring-4', 'ring-brand-500/20'); setTimeout(function () { ta.classList.remove('ring-4', 'ring-brand-500/20'); }, 1200);
    })
    .catch(function () { var m = (window.T && T.liveFailed) || 'Error'; aiToast(m); })
    .finally(function () { b.disabled = false; b.classList.remove('animate-pulse'); if (label) label.textContent = old; });
});

/* زرار ✨ على أي خانة فيها data-ai="preset1,preset2" */
(function () {
  var P = window.AI_PRESETS; if (!P) return;
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var menu = null;
  function closeMenu() { if (menu) { menu.remove(); menu = null; } }
  document.addEventListener('click', function (e) { if (menu && !menu.contains(e.target) && !e.target.closest('.ai-spark')) closeMenu(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });

  function ctxOf(ta) {
    var el = ta.closest('[data-ai-context]') || document.getElementById('thread') || (ta.form && ta.form.parentElement) || ta.form;
    return el ? el.innerText.replace(/\n{3,}/g, '\n\n').slice(-5000) : '';
  }
  function panel(ta, html) {
    var old = ta.parentNode.parentNode.querySelector('.ai-result'); if (old) old.remove();
    var d = document.createElement('div');
    d.className = 'ai-result mt-2 rounded-2xl border border-brand-200 bg-brand-50/60 p-3 text-sm text-ink-800';
    d.innerHTML = html; ta.parentNode.after(d); return d;
  }
  function run(ta, key, btn) {
    var p = P.items[key]; closeMenu();
    var fd = new FormData(); fd.append('task', 'preset'); fd.append('preset', key); fd.append('text', ta.value); fd.append('context', ctxOf(ta)); fd.append('stream', '1');
    btn.disabled = true; btn.classList.add('animate-pulse');
    var mode = (P.modes || {})[key] || 'replace', orig = ta.value, acc = '', live = null;
    // الرد كلمة كلمة: للاستبدال/الإضافة بيتكتب جوه الخانة، وللعرض بيتكتب في صندوق تحت
    function onDelta(d) {
      acc += d;
      if (mode === 'show') {
        if (!live) { live = panel(ta, '<div class="flex items-center justify-between gap-2 mb-2"><b class="text-brand-700 text-xs"></b></div><div class="ai-body whitespace-pre-wrap leading-relaxed"></div>'); live.querySelector('b').textContent = p; }
        live.querySelector('.ai-body').textContent = acc;
      } else {
        ta.value = mode === 'append' ? (orig.replace(/\s+$/, '') + (orig ? '\n\n' : '') + acc) : acc;
        ta.scrollTop = ta.scrollHeight;
      }
    }
    fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) {
      var ct = r.headers.get('content-type') || '';
      if (ct.indexOf('text/event-stream') < 0 || !r.body || !window.TextDecoder) return r.json();
      var reader = r.body.getReader(), dec = new TextDecoder(), buf = '', fin = null;
      function pump() {
        return reader.read().then(function (x) {
          if (x.done) return fin || { error: (window.T && T.liveFailed) || 'Error' };
          buf += dec.decode(x.value, { stream: true }); var parts = buf.split('\n\n'); buf = parts.pop();
          parts.forEach(function (ev) { ev.split('\n').forEach(function (l) { if (l.indexOf('data:') !== 0) return; try { var o = JSON.parse(l.slice(5)); } catch (e) { return; } if (o.d != null) onDelta(o.d); else fin = o; }); });
          return pump();
        });
      }
      return pump();
    }).then(function (d) {
      if (d.error && mode !== 'show') ta.value = orig;
      if (live) live.remove();
      if (d.error) { window.aiToast ? aiToast(d.error) : alert(d.error); return; }
      var cost = d.cost != null ? ' · $' + (+d.cost).toFixed(5).replace(/0+$/, '').replace(/\.$/, '') : '';
      if (d.mode === 'show') {
        var box = panel(ta, '<div class="flex items-center justify-between gap-2 mb-2"><b class="text-brand-700 text-xs"></b><button type="button" class="ai-x text-ink-400 hover:text-ink-900">✕</button></div><div class="ai-body whitespace-pre-wrap leading-relaxed"></div><div class="flex flex-wrap gap-2 mt-3"><button type="button" class="ai-use btn-ghost !py-1 !px-2.5 !text-xs"></button><button type="button" class="ai-copy btn-ghost !py-1 !px-2.5 !text-xs"></button><span class="ms-auto text-[10px] text-ink-400 self-center" dir="ltr"></span></div>');
        box.querySelector('b').textContent = p;
        box.querySelector('.ai-body').textContent = d.text;
        box.querySelector('.ai-use').textContent = P.use; box.querySelector('.ai-copy').textContent = P.copy;
        box.querySelector('span[dir]').textContent = (d.model || '') + cost;
        box.querySelector('.ai-x').onclick = function () { box.remove(); };
        box.querySelector('.ai-copy').onclick = function () { navigator.clipboard && navigator.clipboard.writeText(d.text); this.textContent = '✓'; };
        box.querySelector('.ai-use').onclick = function () { var sel = (window.getSelection() + '').trim(); ta.value = sel && d.text.indexOf(sel) > -1 ? sel : d.text; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus(); };
        if (!ta.value && !/review|score/.test(key)) {} // النتيجة بتتعرض بس، المستخدم يختار
      } else {
        ta.value = d.mode === 'append' ? (ta.value.replace(/\s+$/, '') + (ta.value ? '\n\n' : '') + d.text) : d.text;
        ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus();
        ta.classList.add('ring-4', 'ring-brand-500/20'); setTimeout(function () { ta.classList.remove('ring-4', 'ring-brand-500/20'); }, 1200);
      }
    }).catch(function () { var m = (window.T && T.liveFailed) || 'Error'; window.aiToast ? aiToast(m) : alert(m); })
      .finally(function () { btn.disabled = false; btn.classList.remove('animate-pulse'); });
  }
  function openMenu(ta, btn) {
    closeMenu();
    var keys = ta.dataset.ai.split(',').map(function (k) { return k.trim(); }).filter(function (k) { return P.items[k]; });
    menu = document.createElement('div');
    menu.className = 'fixed z-[60] w-60 max-h-80 overflow-y-auto rounded-2xl border border-ink-200 bg-white p-1.5 shadow-2xl shadow-ink-900/15';
    keys.forEach(function (k) {
      var it = document.createElement('button'); it.type = 'button';
      it.className = 'flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-ink-700 hover:bg-brand-50 hover:text-brand-700 text-start';
      it.textContent = P.items[k]; it.onclick = function () { run(ta, k, btn); }; menu.appendChild(it);
    });
    document.body.appendChild(menu);
    var r = btn.getBoundingClientRect(), mw = 240, mh = menu.offsetHeight;
    var left = Math.min(Math.max(8, (document.dir === 'rtl' ? r.left : r.right - mw)), innerWidth - mw - 8);
    var top = r.bottom + 6 + mh > innerHeight ? Math.max(8, r.top - mh - 6) : r.bottom + 6;
    menu.style.left = left + 'px'; menu.style.top = top + 'px';
  }
  function init(root) {
    (root.querySelectorAll ? root : document).querySelectorAll('textarea[data-ai]:not([data-ai-ready])').forEach(function (ta) {
      ta.dataset.aiReady = '1';
      var w = document.createElement('div'); w.className = 'relative' + (ta.classList.contains('flex-1') ? ' flex-1 min-w-0' : '');
      ta.parentNode.insertBefore(w, ta); w.appendChild(ta);
      if (ta.classList.contains('flex-1')) ta.classList.add('w-full');
      ta.classList.add('pe-10');
      var b = document.createElement('button'); b.type = 'button'; b.title = P.title;
      b.className = 'ai-spark absolute end-2 top-2 ' + (ta.classList.contains('!mt-0') ? '' : 'mt-1.5 ') + 'w-7 h-7 rounded-lg flex items-center justify-center text-brand-600 bg-brand-50 hover:bg-brand-100 border border-brand-200 transition disabled:opacity-60';
      b.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg>';
      b.onclick = function (e) { e.preventDefault(); menu ? closeMenu() : openMenu(ta, b); };
      w.appendChild(b);
    });
  }
  init(document);
  new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) init(n.matches && n.matches('textarea') ? n.parentNode : n); }); }); }).observe(document.body, { childList: true, subtree: true });
})();


/* زرار الوضع: تبديل فوري من غير تحميل الصفحة، والحفظ في الخلفية */
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-theme-toggle]');
  if (b) {
    var html = document.documentElement, dark = !html.classList.contains('dark');
    html.classList.add('theme-anim');
    html.classList.toggle('dark', dark);
    var meta = document.querySelector('meta[name=color-scheme]'); if (meta) meta.content = dark ? 'dark' : 'light';
    document.querySelectorAll('[data-theme-toggle]').forEach(function (x) { x.setAttribute('aria-checked', dark ? 'true' : 'false'); });
    clearTimeout(window.__ttT); window.__ttT = setTimeout(function () { html.classList.remove('theme-anim'); }, 450);
    fetch(b.dataset[dark ? 'urlDark' : 'urlLight'], { credentials: 'same-origin', redirect: 'manual' }).catch(function () {});
    return;
  }
  // اللغة: المؤشر يتحرك الأول، وبعدين الصفحة تتحمل باللغة الجديدة
  var a = e.target.closest('.lang-seg [data-lang]');
  if (a && !e.metaKey && !e.ctrlKey) {
    var seg = a.closest('.lang-seg'), pill = seg.querySelector('.lang-pill');
    if (pill.dataset.on === a.dataset.lang) { e.preventDefault(); return; }
    e.preventDefault();
    pill.dataset.on = a.dataset.lang;
    seg.querySelectorAll('[data-lang]').forEach(function (x) { x.classList.toggle('text-ink-500', x !== a); x.classList.toggle('text-[#fff]', x === a); });
    document.body.style.transition = 'opacity .2s'; setTimeout(function () { document.body.style.opacity = '.55'; }, 120);
    setTimeout(function () { location.href = a.href; }, 260);
  }
});
