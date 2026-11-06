/* أدوات التعليقات: ردود المنشور (#40)، الأكثر تفاعلًا (#41)، سحب على هدية (#44)، إخفاء تلقائي (#39) */
(function () {
  var C = window.CMT; if (!C) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function csrf() { var m = document.querySelector('meta[name=csrf-token]'); if (m) return m.content; var i = document.querySelector('input[name=_csrf]'); return i ? i.value : ''; }
  function post(d) {
    var fd = new FormData(); Object.keys(d).forEach(function (k) { [].concat(d[k]).forEach(function (v) { fd.append(Array.isArray(d[k]) ? k + '[]' : k, v); }); }); fd.append('_csrf', csrf());
    var q = '?p=comments' + (C.post ? '&post=' + encodeURIComponent(C.post) + '&a=' + encodeURIComponent(C.a) : '');
    return fetch(location.pathname + q, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); });
  }
  function note(m) { if (window.toast) return window.toast(m); var d = document.createElement('div'); d.className = 'fixed bottom-4 inset-x-0 mx-auto w-fit z-50 rounded-xl bg-ink-900 text-white px-4 py-2 text-sm'; d.textContent = m; document.body.appendChild(d); setTimeout(function () { d.remove(); }, 2600); }
  var btn = $('#cm-tools-btn'), box = $('#cm-tools'); if (!btn || !box) return;
  function tab(k) { $$('.cmt-tab', box).forEach(function (b) { var on = b.dataset.cmt === k; b.classList.toggle('bg-brand-50', on); b.classList.toggle('text-brand-700', on); }); $$('[data-cmp]', box).forEach(function (p) { p.classList.toggle('hidden', p.dataset.cmp !== k); }); try { localStorage.setItem('cmt-tab', k); } catch (e) {} }
  btn.onclick = function () { var o = box.classList.toggle('hidden'); btn.setAttribute('aria-expanded', !o); };
  box.addEventListener('click', function (e) { var b = e.target.closest('.cmt-tab'); if (b) tab(b.dataset.cmt); });
  var t0 = 'rep'; try { t0 = localStorage.getItem('cmt-tab') || 'rep'; } catch (e) {} tab(t0);

  /* #40 */
  var list = (C.preps || []).slice();
  function drawP() {
    var w = $('#cm-preps'); if (!w) return;
    w.innerHTML = list.length ? list.map(function (t, i) { return '<span class="inline-flex items-center gap-1 rounded-full border border-ink-200 bg-ink-50 ps-2.5 pe-1 py-1 text-xs"><button type="button" data-pi="' + i + '" class="max-w-[260px] truncate text-start" dir="auto">' + esc(t) + '</button><button type="button" data-px="' + i + '" class="w-5 h-5 rounded-full text-ink-400 hover:bg-red-50 hover:text-red-600">✕</button></span>'; }).join('') : '<span class="text-xs text-ink-400">' + esc(C.empty) + '</span>';
  }
  function saveP() { post({ action: 'c_preps', list: list }).then(function (j) { if (j.ok) { list = j.list; drawP(); } else note(j.error || C.err); }).catch(function () { note(C.err); }); }
  drawP();
  $('#cm-preps') && $('#cm-preps').addEventListener('click', function (e) {
    var a = e.target.closest('[data-pi]'), x = e.target.closest('[data-px]');
    if (x) { list.splice(+x.dataset.px, 1); return saveP(); }
    if (a) { var ta = $('#replyForm textarea'); if (ta) { ta.value = list[+a.dataset.pi]; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus(); } }
  });
  var pa = $('#cm-prep-add'), pin = $('#cm-prep-in');
  if (pa) { pa.onclick = function () { var v = pin.value.trim(); if (!v) return; list.push(v); pin.value = ''; saveP(); }; pin.onkeydown = function (e) { if (e.key === 'Enter') { e.preventDefault(); pa.onclick(); } }; }

  /* #41 */
  var fg = $('#cm-fans-go');
  if (fg) fg.onclick = function () {
    fg.disabled = true; fg.textContent = C.loading;
    post({ action: 'c_fans' }).then(function (j) {
      fg.disabled = false; fg.textContent = '↻';
      if (!j.ok) return note(j.error || C.err);
      var w = $('#cm-fans'); if (!j.fans.length) { w.innerHTML = '<p class="text-xs text-ink-400 py-2">' + esc(C.noFans) + '</p>'; return; }
      w.innerHTML = j.fans.map(function (f, i) {
        var ext = 'cm:' + (f.pf || '') + ':' + f.key;
        return '<div class="flex items-center gap-2 py-1.5"><b class="w-5 text-ink-400 text-xs">' + (i + 1) + '</b><span class="flex-1 min-w-0"><b class="text-ink-900">' + esc(f.name) + '</b> <span class="text-[11px] text-ink-500">' + esc(C.cm.replace('%s', f.n)) + ' · ' + esc(C.onp.replace('%s', f.posts)) + (f.price ? ' · <span class="text-lime-700 font-semibold">' + esc(C.price.replace('%s', f.price)) + '</span>' : '') + '</span>' +
          (f.last ? '<span class="block text-[11px] text-ink-400 truncate" dir="auto">“' + esc(f.last) + '”</span>' : '') + '</span>' +
          (C.crm ? '<span data-tolead data-source="comments" data-ext="' + esc(ext) + '" data-name="' + esc(f.name) + '" data-text="' + esc(f.last) + '" data-where="⭐"><button type="button" data-go class="text-xs font-semibold text-lime-700 hover:underline">🧲 ' + esc(C.lead) + '</button></span>' : '') + '</div>';
      }).join('');
    }).catch(function () { fg.disabled = false; fg.textContent = '↻'; note(C.err); });
  };

  /* #44 سحب عادل: شخص واحد = فرصة واحدة، عشوائية من crypto */
  var gv = $('#gv-go');
  if (gv) gv.onclick = function () {
    var kw = $('#gv-kw').value.trim().toLowerCase(), tag = $('#gv-tag').checked, n = Math.max(1, Math.min(20, +$('#gv-n').value || 1));
    var pool = (C.pool || []).filter(function (p) { return p.m.some(function (m) { var l = String(m).toLowerCase(); return (!kw || l.indexOf(kw) > -1) && (!tag || /@\S+/.test(m)); }); });
    var out = $('#gv-out'); if (!pool.length) { out.innerHTML = '<p class="text-xs text-ink-400">' + esc(C.none) + '</p>'; return; }
    var a = pool.slice(), win = [];
    for (var i = 0; i < n && a.length; i++) { var r = new Uint32Array(1); crypto.getRandomValues(r); win.push(a.splice(r[0] % a.length, 1)[0]); }
    out.innerHTML = '<div class="rounded-xl border border-lime-200 bg-lime-50 p-3"><div class="text-xs text-lime-800 mb-1">🏆 ' + esc(win.length > 1 ? C.wins : C.win) + ' · ' + esc(C.of.replace('%s', pool.length)) + ' · ' + new Date().toLocaleString() + '</div>' +
      win.map(function (w) { return '<div class="font-semibold text-ink-900">' + esc(w.n) + ' <span class="text-[11px] font-normal text-ink-500" dir="auto">“' + esc(w.m[0]) + '”</span></div>'; }).join('') + '</div>';
  };

  /* #39 */
  var ss = $('#sp-save'), sr = $('#sp-run');
  function cfg() { return { action: 'c_spamcfg', on: $('#sp-on').checked ? 1 : '', links: $('#sp-links').checked ? 1 : '', words: $('#sp-words').value }; }
  if (ss) ss.onclick = function () { post(cfg()).then(function (j) { note(j.ok ? C.saved : (j.error || C.err)); }).catch(function () { note(C.err); }); };
  if (sr) sr.onclick = function () {
    if (!confirm(C.confirmRun)) return; sr.disabled = true;
    post(cfg()).then(function () { return post({ action: 'c_spamrun' }); }).then(function (j) { sr.disabled = false; note(j.ok ? C.hidden.replace('%s', j.done) : (j.error || C.err)); if (j.ok && j.done) setTimeout(function () { location.reload(); }, 900); }).catch(function () { sr.disabled = false; note(C.err); });
  };
})();
