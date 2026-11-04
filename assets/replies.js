/* الردود المحفوظة: اكتب # في أي خانة فيها data-saved-replies */
(function () {
  var R = window.SAVED_REPLIES; if (!R) return;
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var menu = null, ta = null, idx = 0, list = [], start = -1;
  function post(data) { var fd = new FormData(); for (var k in data) fd.append(k, data[k]); return fetch(location.pathname + location.search.replace(/&.*/, ''), { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }); }
  function esc(t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function close() { if (menu) menu.remove(); menu = null; start = -1; }
  function query() { var v = ta.value.slice(0, ta.selectionStart), m = v.match(/(^|\s)#([^\s#]*)$/); return m ? { q: m[2].toLowerCase(), at: v.length - m[2].length - 1 } : null; }
  function render() {
    if (!menu) {
      menu = document.createElement('div'); menu.className = 'fixed z-[70] w-80 max-h-72 overflow-y-auto rounded-2xl border border-ink-200 bg-white shadow-2xl p-1.5';
      document.body.appendChild(menu);
    }
    var r = ta.getBoundingClientRect(); menu.style.left = Math.max(8, Math.min(r.left, innerWidth - 330)) + 'px'; menu.style.bottom = (innerHeight - r.top + 6) + 'px';
    menu.innerHTML = '';
    if (!list.length) { var e = document.createElement('p'); e.className = 'px-3 py-2 text-xs text-ink-500'; e.textContent = R.empty; menu.appendChild(e); }
    list.forEach(function (it, i) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'w-full text-start rounded-xl px-3 py-2 ' + (i === idx ? 'bg-brand-50' : 'hover:bg-ink-100');
      b.innerHTML = '<div class="text-xs font-semibold ' + (i === idx ? 'text-brand-700' : 'text-ink-900') + '">#' + esc(it.shortcut) + '</div><div class="text-[11px] text-ink-500 line-clamp-2">' + esc(it.body) + '</div>';
      b.onmousedown = function (ev) { ev.preventDefault(); idx = i; pick(); }; menu.appendChild(b);
    });
    var m = document.createElement('button'); m.type = 'button'; m.className = 'w-full text-start rounded-xl px-3 py-2 text-xs text-brand-700 hover:bg-brand-50 border-t border-ink-100 mt-1'; m.textContent = '⚙ ' + R.manage;
    m.onmousedown = function (ev) { ev.preventDefault(); close(); manage(); }; menu.appendChild(m);
    var on = menu.children[idx]; if (on) on.scrollIntoView({ block: 'nearest' });
  }
  function update() {
    var q = query(); if (!q) { close(); return; }
    start = q.at; list = R.items.filter(function (it) { return !q.q || it.shortcut.toLowerCase().indexOf(q.q) > -1 || it.body.toLowerCase().indexOf(q.q) > -1; }).slice(0, 12); idx = 0; render();
  }
  function pick() {
    var it = list[idx]; if (!it) return;
    var end = ta.selectionStart, v = ta.value;
    ta.value = v.slice(0, start) + it.body + v.slice(end); var pos = start + it.body.length; ta.setSelectionRange(pos, pos);
    ta.dispatchEvent(new Event('input', { bubbles: true })); close(); ta.focus();
    post({ action: 'reply_use', id: it.id });
  }
  document.addEventListener('input', function (e) { if (e.target.matches('textarea[data-saved-replies]')) { ta = e.target; update(); } });
  document.addEventListener('keydown', function (e) {
    if (!menu || e.target !== ta) return;
    if (e.key === 'ArrowDown') { idx = (idx + 1) % Math.max(1, list.length); render(); e.preventDefault(); e.stopImmediatePropagation(); }
    else if (e.key === 'ArrowUp') { idx = (idx - 1 + list.length) % Math.max(1, list.length); render(); e.preventDefault(); e.stopImmediatePropagation(); }
    else if ((e.key === 'Enter' || e.key === 'Tab') && list.length) { pick(); e.preventDefault(); e.stopImmediatePropagation(); }
    else if (e.key === 'Escape') { close(); e.preventDefault(); }
  }, true);
  document.addEventListener('click', function (e) { if (menu && !menu.contains(e.target)) close(); });

  // نافذة الإدارة
  function manage() {
    var d = document.createElement('dialog'); d.className = 'w-[min(520px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-14 border-b border-ink-200"><b class="flex-1 text-ink-900"></b><button type="button" class="x p-2 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div>'
      + '<div class="p-5 space-y-3"><div class="grid grid-cols-[8rem_1fr] gap-2"><input class="sc input !mt-0" placeholder="' + esc(R.shortcut) + '" dir="auto"><textarea class="bd input !mt-0 text-sm" rows="2" placeholder="' + esc(R.body) + '"></textarea></div><button type="button" class="add btn-primary !py-1.5 !text-xs">' + esc(R.add) + '</button><div class="lst space-y-2 max-h-80 overflow-y-auto"></div></div>';
    d.querySelector('b').textContent = R.manage; document.body.appendChild(d);
    function draw() {
      var l = d.querySelector('.lst'); l.innerHTML = '';
      R.items.forEach(function (it) {
        var row = document.createElement('div'); row.className = 'flex items-start gap-2 rounded-xl border border-ink-200 p-2.5';
        row.innerHTML = '<div class="flex-1 min-w-0"><div class="text-xs font-semibold text-ink-900">#' + esc(it.shortcut) + '</div><div class="text-xs text-ink-600 whitespace-pre-wrap">' + esc(it.body) + '</div></div><button type="button" class="text-xs text-red-600 hover:underline">' + esc(R.del) + '</button>';
        row.querySelector('button').onclick = function () { post({ action: 'reply_del', id: it.id }).then(function (r) { if (r.items) { R.items = r.items; draw(); } }); };
        l.appendChild(row);
      });
    }
    d.querySelector('.add').onclick = function () {
      var sc = d.querySelector('.sc'), bd = d.querySelector('.bd');
      post({ action: 'reply_save', shortcut: sc.value, body: bd.value }).then(function (r) { if (r.error) { alert(r.error); return; } R.items = r.items; sc.value = ''; bd.value = ''; draw(); sc.focus(); });
    };
    d.querySelector('.x').onclick = function () { d.close(); };
    d.addEventListener('close', function () { d.remove(); });
    draw(); d.showModal();
  }
  window.manageSavedReplies = manage;
})();
