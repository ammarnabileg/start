/* استوديو المحتوى: الكتابة بالذكاء الاصطناعي ونتايج كروت تتعدل وتتنشر أو تتصمم */
(function () {
  var C = window.ST, f = document.getElementById('st-f'); if (!C || !f) return; var L = C.L;
  var res = document.getElementById('st-res'), empty = document.getElementById('st-empty'), go = document.getElementById('st-go'), items = [];
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function full(it) { return it.text + (it.tags.length ? '\n\n' + it.tags.join(' ') : ''); }
  f.addEventListener('click', function (e) {
    var b = e.target.closest('[data-n]'); if (b) { var n = document.getElementById('st-n'); n.value = Math.max(1, Math.min(6, +n.value + +b.dataset.n)); return; }
    var t = e.target.closest('[data-topic]'); if (t) { var tp = document.getElementById('st-topic'); tp.value = t.dataset.topic; f.querySelectorAll('[data-topic]').forEach(function (x) { x.classList.toggle('!border-brand-500', x === t); x.classList.toggle('!bg-brand-50', x === t); }); }
  });
  function card(it, i) {
    return '<div class="card p-0 overflow-hidden flex flex-col cp-pop" data-i="' + i + '"><div class="flex items-center gap-2 px-4 py-3 border-b border-ink-100"><span class="w-7 h-7 rounded-lg bg-brand-50 text-brand-700 text-xs font-bold grid place-items-center">' + (i + 1) + '</span><b class="text-sm text-ink-900 flex-1 truncate">' + esc(it.title) + '</b>' +
      '<button type="button" class="cmp-ib" data-a="copy" title="' + esc(L.copy) + '">' + ic('copy') + '</button><button type="button" class="cmp-ib" data-a="edit" title="' + esc(L.edit) + '">' + ic('pencil') + '</button></div>' +
      '<div class="p-4 flex-1"><p class="text-sm leading-7 text-ink-800 whitespace-pre-wrap break-words" dir="auto" data-body>' + esc(it.text) + '</p>' +
      (it.tags.length ? '<div class="flex flex-wrap gap-1 mt-3">' + it.tags.map(function (h) { return '<span class="text-xs text-brand-600" dir="auto">' + esc(h) + '</span>'; }).join('') + '</div>' : '') +
      (it.visual ? '<div class="mt-3 flex gap-2 rounded-xl bg-amber-50/70 border border-amber-100 p-2.5 text-xs text-amber-900">' + ic('image', 'w-4 h-4 shrink-0 mt-0.5') + '<span dir="auto"><b>' + esc(L.visual) + ':</b> ' + esc(it.visual) + '</span></div>' : '') + '</div>' +
      '<div class="flex items-center gap-2 px-4 py-3 border-t border-ink-100 bg-ink-50/50"><span class="text-[11px] text-ink-400 tabular-nums">' + Array.from(full(it)).length + ' ' + esc(L.chars) + '</span><span class="ms-auto"></span>' +
      '<a class="btn-ghost !py-1.5 !text-xs" href="' + esc(C.designer + '&title=' + encodeURIComponent(it.title) + '&text=' + encodeURIComponent(it.text.split('\n')[0].slice(0, 120))) + '">' + ic('palette', 'w-4 h-4') + esc(L.design) + '</a>' +
      '<a class="btn-primary !py-1.5 !text-xs" href="' + esc(C.compose + '&dup_text=' + encodeURIComponent(full(it))) + '">' + ic('megaphone', 'w-4 h-4') + esc(L.post) + '</a></div></div>';
  }
  function draw() { res.innerHTML = items.map(card).join(''); empty.classList.toggle('hidden', items.length > 0); }
  res.addEventListener('click', function (e) {
    var b = e.target.closest('[data-a]'); if (!b) return; var c = b.closest('[data-i]'), it = items[+c.dataset.i];
    if (b.dataset.a === 'copy') { try { navigator.clipboard.writeText(full(it)); toast(L.copied); } catch (x) {} return; }
    if (b.dataset.a === 'edit') { var body = c.querySelector('[data-body]'); if (body.tagName === 'TEXTAREA') return;
      var ta = document.createElement('textarea'); ta.className = 'input !mt-0 text-sm leading-7'; ta.rows = Math.min(14, it.text.split('\n').length + 2); ta.dir = 'auto'; ta.value = it.text; ta.setAttribute('data-body', ''); body.replaceWith(ta); ta.focus();
      ta.addEventListener('blur', function () { it.text = ta.value; var i = +c.dataset.i, n = document.createElement('div'); n.innerHTML = card(it, i); c.replaceWith(n.firstChild); }); }
  });
  f.addEventListener('submit', function (e) {
    e.preventDefault(); var sp = go.querySelector('span'), old = sp.textContent; go.disabled = true; sp.textContent = L.busy;
    res.innerHTML = Array(+document.getElementById('st-n').value).fill('<div class="card p-4 space-y-3"><div class="h-5 w-1/2 mk-sk"></div><div class="h-24 mk-sk"></div><div class="h-8 mk-sk"></div></div>').join(''); empty.classList.add('hidden');
    var d = new FormData(f); d.append('_csrf', C.csrf);
    fetch(C.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      go.disabled = false; sp.textContent = old;
      if (!j.ok) { res.innerHTML = ''; empty.classList.remove('hidden'); return toast(j.error || L.err, 'error'); }
      items = j.items; draw(); sp.textContent = L.more;
    }).catch(function () { go.disabled = false; sp.textContent = old; res.innerHTML = ''; empty.classList.remove('hidden'); toast(L.err, 'error'); });
  });
})();
