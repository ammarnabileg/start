/**
 * الرسائل والتعليقات من غير ريفرش.
 * أي صفحة فيها [data-live-root]: الروابط والفورمز جواها بتشتغل في الخلفية،
 * والجزء اللي اتغير بس هو اللي بيتحدث. ولو الـ JavaScript وقع، الصفحة بتشتغل عادي.
 *
 * - [data-live="list"]   : القائمة (محادثات/منشورات) بتتحدث لوحدها كل شوية
 * - #thread              : الرسائل/التعليقات المفتوحة، بتتحدث لوحدها
 * - form[data-optimistic]: رسالتك بتظهر فوراً قبل ما السيرفر يرد
 */
(function () {
  var root = document.querySelector('[data-live-root]');
  if (!root || !window.fetch || !window.DOMParser) return;
  var T = window.T || {};
  var busy = false, sending = false, navCtl = null, pollTimer = null, POLL_MS = 20000, backoff = 1;

  /* ---------- رسائل صغيرة (toast) ---------- */
  var box = document.createElement('div');
  box.className = 'fixed bottom-5 inset-x-0 z-[60] flex flex-col items-center gap-2 pointer-events-none px-4';
  document.body.appendChild(box);
  function toast(msg, type) {
    var el = document.createElement('div');
    el.className = 'pointer-events-auto max-w-md w-full sm:w-auto rounded-xl px-4 py-3 text-sm shadow-lg border transition duration-300 ' +
      (type === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-white text-ink-900 border-ink-200');
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.textContent = msg;
    box.appendChild(el);
    setTimeout(function () { el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 300); }, type === 'error' ? 7000 : 3000);
  }
  // الرسايل اللي جت مع تحميل الصفحة نفسها
  document.querySelectorAll('[data-flash]').forEach(function (f) { toast(f.textContent.trim(), f.dataset.flash); f.remove(); });

  /* ---------- مؤشر تحميل رفيع فوق ---------- */
  var bar = document.createElement('div');
  bar.className = 'fixed top-0 inset-x-0 h-0.5 z-[70] bg-gradient-to-l from-brand-600 to-cyan-500 origin-left scale-x-0 transition-transform duration-500';
  document.body.appendChild(bar);
  function loading(on) { bar.style.transform = on ? 'scaleX(.7)' : 'scaleX(1)'; if (!on) setTimeout(function () { bar.style.transform = 'scaleX(0)'; }, 250); root.setAttribute('aria-busy', on ? 'true' : 'false'); }

  /* ---------- أدوات ---------- */
  function sameScreen(u) {
    try {
      var a = new URL(u, location.href);
      var cur = new URLSearchParams(location.search).get('p') || 'dashboard';
      return a.origin === location.origin && a.pathname === location.pathname && (a.searchParams.get('p') || 'dashboard') === cur;
    } catch (e) { return false; }
  }
  function atBottom(el) { return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80; }
  function toBottom() {
    var t = document.getElementById('thread');
    if (!t || t.hasAttribute('data-comments')) return; // التعليقات بتبدأ من فوق
    var go = function () { t.scrollTop = t.scrollHeight; };
    go(); requestAnimationFrame(go);
    // الصور بتتحمل بعدين وبتطوّل المحادثة: نفضل تحت لحد ما تخلص
    t.querySelectorAll('img').forEach(function (img) { if (!img.complete) img.addEventListener('load', function () { if (atBottom(t) || t.dataset.stick !== '0') go(); }, { once: true }); });
  }

  function load(u, opts) {
    opts = opts || {};
    if (!opts.silent) loading(true);
    return fetch(u, { method: opts.method || 'GET', body: opts.body, signal: opts.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) {
        if (!r.ok && r.status !== 302) throw new Error(r.status);
        return r.text().then(function (html) { return { html: html, url: r.url }; });
      })
      .finally(function () { if (!opts.silent) loading(false); });
  }

  function parse(html) { return new DOMParser().parseFromString(html, 'text/html'); }

  function flashesFrom(doc) {
    doc.querySelectorAll('[data-flash]').forEach(function (f) { toast(f.textContent.trim(), f.dataset.flash); });
  }

  /** يبدّل محتوى الصفحة بالجديد مع الحفاظ على مكان السكرول والكتابة */
  function swap(doc, mode) {
    var next = doc.querySelector('[data-live-root]');
    if (!next) { location.reload(); return; }
    var list = root.querySelector('[data-live="list"]'), listTop = list ? list.scrollTop : 0;
    var thread = document.getElementById('thread'), wasBottom = atBottom(thread);
    if (mode === 'poll') {
      // التحديث التلقائي: القائمة والرسائل بس، من غير ما نلمس اللي بتكتبه
      var nl = next.querySelector('[data-live="list"]');
      if (list && nl && list.innerHTML !== nl.innerHTML) { list.innerHTML = nl.innerHTML; list.scrollTop = listTop; }
      var nt = next.querySelector('#thread');
      if (thread && nt && thread.innerHTML !== nt.innerHTML) {
        var top = thread.scrollTop;
        thread.innerHTML = nt.innerHTML;
        if (wasBottom) toBottom(); else thread.scrollTop = top;
      }
      return;
    }
    root.innerHTML = next.innerHTML;
    var l2 = root.querySelector('[data-live="list"]');
    if (l2 && mode !== 'nav-reset') l2.scrollTop = listTop;
    var t2 = document.getElementById('thread');
    if (t2) toBottom();
    var title = doc.querySelector('title'); if (title) document.title = title.textContent;
    var focus = root.querySelector('textarea[data-enter-submit], #replyForm textarea');
    if (focus && mode === 'submit' && window.innerWidth >= 1024) focus.focus();
  }

  /* ---------- التنقل بالروابط ---------- */
  root.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || a.target || a.hasAttribute('download') || a.hasAttribute('data-modal')) return;
    if (!sameScreen(a.href)) return;
    e.preventDefault();
    navigate(a.href, true);
  });

  function navigate(u, push) {
    if (sending) return;
    if (navCtl) navCtl.abort(); // ضغطة جديدة بتلغي اللي قبلها
    var ctl = navCtl = (window.AbortController ? new AbortController() : null);
    busy = true;
    // نعلّم العنصر المختار فوراً عشان الإحساس يبقى سريع
    root.querySelectorAll('[data-live="list"] a').forEach(function (x) { x.classList.toggle('bg-brand-50', x.href === u); });
    load(u, { signal: ctl ? ctl.signal : undefined }).then(function (res) {
      if (ctl !== navCtl) return;
      var doc = parse(res.html);
      swap(doc, 'nav');
      flashesFrom(doc);
      if (push) history.pushState({ live: 1 }, '', res.url || u);
    }).catch(function (err) { if (err && err.name === 'AbortError') return; location.href = u; })
      .finally(function () { if (ctl === navCtl) { busy = false; navCtl = null; } });
  }
  window.addEventListener('popstate', function () { if (sameScreen(location.href)) navigate(location.href, false); });

  /* ---------- الفورمز ---------- */
  root.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || f.hasAttribute('data-no-live')) return; // confirm() اتلغى، أو فورم عايز ريفرش
    e.preventDefault();
    if (sending) return;
    var method = (f.getAttribute('method') || 'get').toUpperCase();
    var action = f.getAttribute('action') || location.href;
    var fd = new FormData(f);
    if (e.submitter && e.submitter.name) fd.append(e.submitter.name, e.submitter.value);

    if (method === 'GET') {
      var u = new URL(action, location.href);
      u.search = new URLSearchParams(fd).toString();
      navigate(u.toString(), true);
      return;
    }

    if (navCtl) { navCtl.abort(); navCtl = null; } // الإرسال أهم من تحميل صفحة
    sending = busy = true;
    var btns = f.querySelectorAll('button');
    btns.forEach(function (b) { b.disabled = true; });
    var pending = optimistic(f, fd);

    load(action, { method: 'POST', body: fd }).then(function (res) {
      var doc = parse(res.html);
      swap(doc, 'submit');
      flashesFrom(doc);
      if (res.url && res.url !== location.href && sameScreen(res.url)) history.replaceState({ live: 1 }, '', res.url);
    }).catch(function () {
      if (pending) {
        pending.classList.add('opacity-60');
        var meta = pending.querySelector('[data-meta]');
        if (meta) meta.textContent = T.notSent || '!';
        (pending.querySelector('.rounded-2xl') || pending).classList.add('ring-2', 'ring-red-400');
        // نرجّع النص للخانة عشان ميضيعش
        var ta = f.querySelector('textarea');
        if (ta && !ta.value) ta.value = pending.dataset.text || '';
      }
      toast(T.liveFailed || 'Failed. Try again.', 'error');
      btns.forEach(function (b) { b.disabled = false; });
    }).finally(function () { sending = busy = false; });
  });

  /** رسالتك أو ردك يظهر فوراً */
  function optimistic(f, fd) {
    if (!f.hasAttribute('data-optimistic')) return null;
    var text = (fd.get('message') || '').toString().trim();
    var thread = document.getElementById('thread');
    if (!text || !thread) return null;
    var row = document.createElement('div');
    row.dataset.text = text;
    if (f.dataset.optimistic === 'comment') {
      row.className = 'flex gap-3 opacity-70';
      row.innerHTML = '<div class="w-9 h-9 rounded-full bg-brand-100 shrink-0"></div><div class="rounded-2xl px-4 py-2 bg-brand-50 text-sm"></div>';
      row.lastChild.textContent = text;
      var list = thread.querySelector('[data-comments]') || thread;
      list.appendChild(row);
    } else {
      row.className = 'flex justify-start';
      row.innerHTML = '<div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm bg-brand-600 text-white opacity-80"><p class="whitespace-pre-line break-words"></p><div data-meta class="text-[10px] mt-1 text-brand-100"></div></div>';
      row.querySelector('p').textContent = text;
      row.querySelector('[data-meta]').textContent = T.sending || '...';
      var bottom = document.getElementById('bottom');
      thread.insertBefore(row, bottom || null);
    }
    f.querySelectorAll('textarea').forEach(function (t) { t.value = ''; });
    toBottom();
    return row;
  }

  /* ---------- تحديث تلقائي ---------- */
  function poll() {
    clearTimeout(pollTimer);
    pollTimer = setTimeout(function () {
      if (document.hidden) return; // التاب مش ظاهر: نوقف لحد ما يرجع
      if (busy) { poll(); return; }
      load(location.href, { silent: true }).then(function (res) {
        var doc = parse(res.html), next = doc.querySelector('[data-live-root]');
        // لو الخدمة رجعت خطأ (زي كتر الطلبات) منمسحش اللي قدامك، ونستنى أكتر المرة الجاية
        if (!next || next.hasAttribute('data-live-error')) { backoff = Math.min(backoff * 2, 8); return; }
        backoff = 1;
        if (!busy) swap(doc, 'poll');
      }).catch(function () { backoff = Math.min(backoff * 2, 8); }).finally(poll);
    }, POLL_MS * backoff);
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  poll();
  toBottom();
})();
