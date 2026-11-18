/* ملء ملف الشركة من الموقع: قراءة → مراجعة (كل خانة بعلامة، وتقدر تعدّل النص) → حفظ المختار */
(function () {
  var C = window.KBS, dlg = document.getElementById('kbs'); if (!C || !dlg) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function csrf() { var m = document.querySelector('meta[name=csrf-token]'); return m ? m.content : ''; }
  function post(d) { var fd = new FormData(); Object.keys(d).forEach(function (k) { fd.append(k, d[k]); }); fd.append('_csrf', csrf()); return fetch(location.pathname + '?p=knowledge', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  var groups = [], out = $('#kbs-out'), foot = $('#kbs-foot');
  document.getElementById('kbs-open').addEventListener('click', function () { dlg.showModal(); $('#kbs-f [name=url]').focus(); });
  dlg.addEventListener('click', function (e) { if (e.target.closest('[data-kbs-x]') || e.target === dlg) dlg.close(); });
  function count() { var n = $$('#kbs-out input[type=checkbox]:checked').length; $('#kbs-n').textContent = C.sel.replace('%s', n); $('#kbs-apply').disabled = !n; }
  /* قراءة الموقع كله في الخلفية: نبدأ المهمة، ونكمّل خطوات طول ما النافذة مفتوحة، والتشغيل الدوري يكمّل لو اتقفلت */
  var crawl = null, stepping = false;
  function num(n) { return Number(n || 0).toLocaleString(document.documentElement.lang === 'ar' ? 'ar-EG' : 'en-US'); }
  function f2(s) { var a = [].slice.call(arguments, 1), i = 0; return String(s).replace(/%s/g, function () { return a[i++]; }); }
  /* الصفحات المقروءة: قائمة + بحث + عرض النص */
  var pv = null;
  function pagesView(q, off) {
    if (!crawl) return; pv = pv || document.createElement('div'); pv.className = 'mt-3 rounded-2xl border border-ink-200 overflow-hidden';
    if (!pv.isConnected) out.appendChild(pv);
    post({ action: 'kb_crawl_pages', id: crawl.id, q: q || '', off: off || 0 }).then(function (j) {
      if (!j.ok) return;
      var list = j.items.map(function (x) { return '<button type="button" data-kbs-pg="' + x.id + '" class="w-full text-start px-3 py-2 border-b border-ink-100 hover:bg-ink-50 flex items-center gap-2"><span class="min-w-0 flex-1"><span class="block text-sm text-ink-900 truncate">' + esc(x.title || x.url) + '</span><span class="block text-[11px] text-ink-400 truncate" dir="ltr">' + esc(x.url) + '</span></span><span class="text-[11px] text-ink-500 tabular-nums shrink-0">' + esc(f2(C.chars, num(x.chars))) + '</span></button>'; }).join('');
      if (!off) pv.innerHTML = '<div class="p-2 bg-ink-50"><input type="search" data-kbs-q class="input !mt-0 !py-1.5 text-sm" placeholder="' + esc(C.search) + '" value="' + esc(q || '') + '"></div><div data-kbs-list class="max-h-72 overflow-y-auto">' + list + '</div>';
      else pv.querySelector('[data-kbs-list]').insertAdjacentHTML('beforeend', list);
      var m = pv.querySelector('[data-kbs-more]'); if (m) m.remove();
      if ((off || 0) + j.items.length < j.total) pv.querySelector('[data-kbs-list]').insertAdjacentHTML('beforeend', '<button type="button" data-kbs-more="' + ((off || 0) + j.items.length) + '" class="w-full py-2 text-xs link">' + esc(C.more) + ' (' + num(j.total - (off || 0) - j.items.length) + ')</button>');
    });
  }
  out.addEventListener('click', function (e) {
    var b;
    if ((b = e.target.closest('[data-kbs-pages]'))) { pagesView('', 0); return; }
    if ((b = e.target.closest('[data-kbs-more]'))) { var qi = pv.querySelector('[data-kbs-q]'); pagesView(qi ? qi.value : '', +b.dataset.kbsMore); return; }
    if ((b = e.target.closest('[data-kbs-pg]'))) { post({ action: 'kb_crawl_page', id: crawl.id, pid: b.dataset.kbsPg }).then(function (j) { if (!j.ok) return;
      var v = document.createElement('div'); v.className = 'fixed inset-0 z-[95] bg-ink-900/40 grid place-items-center p-4';
      v.innerHTML = '<div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col"><div class="flex items-center gap-2 px-4 py-3 border-b border-ink-100"><b class="flex-1 truncate">' + esc(j.title || j.url) + '</b><span class="text-xs text-ink-500">' + esc(f2(C.chars, num(j.text.length))) + '</span><button type="button" data-x class="btn-ghost !py-1">' + esc(C.back) + '</button></div><a class="px-4 pt-2 text-[11px] link truncate" dir="ltr" target="_blank" rel="noopener" href="' + esc(j.url) + '">' + esc(j.url) + '</a><pre class="p-4 overflow-auto text-sm whitespace-pre-wrap font-sans text-ink-800" dir="auto">' + esc(j.text) + '</pre></div>';
      dlg.appendChild(v); v.addEventListener('click', function (ev) { if (ev.target === v || ev.target.closest('[data-x]')) v.remove(); }); }); }
  });
  out.addEventListener('input', function (e) { if (!e.target.matches('[data-kbs-q]')) return; clearTimeout(pv._t); var q = e.target.value; pv._t = setTimeout(function () { pagesView(q, 0); setTimeout(function () { var i = pv.querySelector('[data-kbs-q]'); if (i) { i.focus(); i.setSelectionRange(q.length, q.length); } }, 50); }, 300); });
  function prog(c) {
    var pct = c.found ? Math.min(100, Math.round((c.done + c.failed) / c.found * 100)) : 0;
    out.innerHTML = '<div class="py-4"><div class="flex items-center gap-2 text-sm text-ink-700 mb-2"><span class="w-4 h-4 rounded-full border-2 border-brand-600 border-t-transparent animate-spin"></span><b>' + esc(C.crawling) + '</b><span class="ms-auto tabular-nums text-ink-500" dir="ltr">' + c.done + ' / ' + c.found + '</span></div>'
      + '<div class="h-2 rounded-full bg-ink-100 overflow-hidden"><div class="h-full bg-brand-600 transition-all" style="width:' + pct + '%"></div></div>'
      + '<p class="text-sm font-semibold text-ink-900 mt-3 tabular-nums">' + esc(f2(C.stats, num(c.done), num(c.chars))) + '</p>'
      + '<p class="text-xs text-ink-500 mt-2">' + esc(C.crawlNote.replace('%s', num(c.cap))) + '</p><p class="text-[11px] text-lime-700 mt-1">' + esc(C.saved) + '</p>'
      + (c.done ? '<button type="button" data-kbs-pages class="text-xs link mt-2 me-4">' + esc(C.viewPages) + '</button>' : '') + (c.failed ? '<p class="text-[11px] text-ink-400 mt-1">' + esc(C.failed.replace('%s', c.failed)) + '</p>' : '')
      + '<button type="button" data-kbs-stop class="text-xs text-red-600 hover:underline mt-3">' + esc(C.stop) + '</button></div>';
  }
  var halted = false;
  function step() {
    if (!crawl || stepping || halted || !dlg.open) return; stepping = true;
    post({ action: 'kb_crawl_step', id: crawl.id }).then(function (j) { stepping = false; if (!j.ok) { out.innerHTML = '<p class="text-sm text-red-600 py-4">' + esc(j.error || C.err) + '</p>'; return; } handle(j.crawl); })
      .catch(function () { stepping = false; setTimeout(step, 3000); });
  }
  function handle(c) {
    crawl = c; var b = $('#kbs-f button'); b.disabled = c.status === 'running';
    if (c.status === 'running') { prog(c); setTimeout(step, 200); return; }
    if (c.status !== 'done' && c.status !== 'stopped') { out.innerHTML = ''; b.disabled = false; return; }
    render({ ok: true, groups: c.groups || [], pages: { length: c.done } });
  }
  $('#kbs-f').addEventListener('submit', function (e) {
    e.preventDefault(); var b = $('#kbs-f button'); b.disabled = true; foot.classList.add('hidden');
    out.innerHTML = '<div class="flex items-center gap-2 text-sm text-ink-500 py-6 justify-center"><span class="w-4 h-4 rounded-full border-2 border-brand-600 border-t-transparent animate-spin"></span>' + esc(C.reading) + '</div>';
    halted = false;
    post({ action: 'kb_crawl_start', url: $('#kbs-f [name=url]').value }).then(function (j) {
      if (!j.ok) { b.disabled = false; out.innerHTML = '<p class="text-sm text-red-600 py-4">' + esc(j.error || C.err) + '</p>'; return; }
      handle(j.crawl);
    }).catch(function () { b.disabled = false; out.innerHTML = '<p class="text-sm text-red-600 py-4">' + esc(C.err) + '</p>'; });
  });
  out.addEventListener('click', function (e) { if (!e.target.closest('[data-kbs-stop]') || !crawl) return; halted = true; e.target.closest('[data-kbs-stop]').disabled = true; post({ action: 'kb_crawl_cancel', id: crawl.id }).then(function (j) { if (j && j.ok && j.crawl) { stepping = true; handle(j.crawl); setTimeout(function () { stepping = false; }, 50); return; } crawl = null; out.innerHTML = ''; $('#kbs-f button').disabled = false; }); });
  // لو فيه قراءة شغالة أو خلصت النهارده: نكمّل منها لما النافذة تتفتح
  document.getElementById('kbs-open').addEventListener('click', function () { post({ action: 'kb_crawl_status' }).then(function (j) { if (j.ok && j.crawl && (j.crawl.status === 'running' || !groups.length)) { var u = $('#kbs-f [name=url]'); if (u && !u.value) u.value = j.crawl.root; handle(j.crawl); } }).catch(function () {}); });
  function render(j) {
    var b = $('#kbs-f button');
    (function () {
      b.disabled = false; if (!j.ok) { out.innerHTML = '<p class="text-sm text-red-600 py-4">' + esc(j.error || C.err) + '</p>'; return; }
      groups = j.groups; if (!groups.length) { out.innerHTML = (crawl && (crawl.status === 'done' || crawl.status === 'stopped') ? '<div class="flex flex-wrap items-center gap-3 text-xs pt-2">' + (crawl.status === 'stopped' ? '<span class="w-full text-amber-700">' + esc(C.stoppedAt) + '</span>' : '<span class="w-full text-lime-700">' + esc(C.addedKb) + '</span>') + '<b class="text-ink-900">' + esc(f2(C.stats, num(crawl.done), num(crawl.chars))) + '</b><button type="button" data-kbs-pages class="link">' + esc(C.viewPages) + '</button></div>' : '') + '<p class="text-sm text-ink-500 py-6 text-center">' + esc(C.none) + '</p>'; return; }
      var h = crawl && (crawl.status === 'done' || crawl.status === 'stopped') ? '<div class="flex flex-wrap items-center gap-3 text-xs">' + (crawl.status === 'stopped' ? '<span class="w-full text-amber-700">' + esc(C.stoppedAt) + '</span>' : '<span class="w-full text-lime-700">' + esc(C.addedKb) + '</span>') + '<b class="text-ink-900">' + esc(f2(C.stats, num(crawl.done), num(crawl.chars))) + '</b><button type="button" data-kbs-pages class="link">' + esc(C.viewPages) + '</button></div>' : '<p class="text-[11px] text-ink-400">' + esc(C.pages.replace('%s', j.pages.length)) + '</p>';
      groups.forEach(function (g, gi) {
        h += '<section class="rounded-2xl border border-ink-200 overflow-hidden"><div class="px-4 py-2 bg-ink-50 font-semibold text-sm text-ink-900 flex items-center gap-2">' + esc(g.label) + (g.type === 'rows' ? '<span class="text-[11px] font-normal text-ink-500">' + esc(C.rows.replace('%s', g.rows.length).replace('%s', g.have)) + '</span>' : '') + '</div><div class="divide-y divide-ink-100">';
        if (g.type === 'fields') g.fields.forEach(function (f, fi) {
          h += '<label class="flex items-start gap-3 px-4 py-2.5 cursor-pointer hover:bg-ink-50/60"><input type="checkbox" class="mt-1 rounded" data-g="' + gi + '" data-f="' + fi + '"' + (f.old ? '' : ' checked') + '>' +
            '<span class="flex-1 min-w-0"><span class="block text-xs font-medium text-ink-700">' + esc(f.label) + '</span>' +
            (f.old ? '<span class="block text-[11px] text-ink-400 line-through truncate" dir="auto">' + esc(C.cur) + ': ' + esc(f.old) + '</span>' : '') +
            '<textarea rows="' + Math.min(5, Math.max(1, Math.ceil(f.new.length / 70))) + '" class="input !mt-1 text-sm" dir="auto" data-v="' + gi + '-' + fi + '">' + esc(f.new) + '</textarea>' +
            (f.src ? '<span class="block text-[10px] text-ink-400 truncate mt-0.5" dir="ltr">' + esc(C.from) + ' ' + esc(f.src.replace(/^https?:\/\//, '')) + '</span>' : '') + '</span></label>';
        });
        else g.rows.forEach(function (r, ri) {
          var keys = Object.keys(g.cols);
          h += '<label class="flex items-start gap-3 px-4 py-2 cursor-pointer hover:bg-ink-50/60"><input type="checkbox" class="mt-1 rounded" data-g="' + gi + '" data-r="' + ri + '" checked><span class="flex-1 min-w-0 grid gap-1" style="grid-template-columns:repeat(' + keys.length + ',minmax(0,1fr))">' +
            keys.map(function (k) { return '<input class="input !mt-0 !py-1 text-sm" dir="auto" placeholder="' + esc(g.cols[k]) + '" value="' + esc(r[k] || '') + '" data-rv="' + gi + '-' + ri + '-' + k + '">'; }).join('') + '</span></label>';
        });
        h += '</div></section>';
      });
      out.innerHTML = '<div class="space-y-3">' + h + '</div>'; foot.classList.remove('hidden'); foot.classList.add('flex'); count();
    })();
  }
  out.addEventListener('change', function (e) { if (e.target.type === 'checkbox') count(); });
  $('#kbs-apply').addEventListener('click', function () {
    var pick = {};
    $$('#kbs-out input[type=checkbox]:checked').forEach(function (c) {
      var g = groups[+c.dataset.g]; pick[g.sec] = pick[g.sec] || (g.type === 'rows' ? { rows: [] } : {});
      if (c.dataset.f != null) { var f = g.fields[+c.dataset.f]; pick[g.sec][f.f] = $('[data-v="' + c.dataset.g + '-' + c.dataset.f + '"]').value; }
      else { var row = {}; Object.keys(g.cols).forEach(function (k) { row[k] = $('[data-rv="' + c.dataset.g + '-' + c.dataset.r + '-' + k + '"]').value; }); pick[g.sec].rows.push(row); }
    });
    var b = this; b.disabled = true;
    post({ action: 'kb_site_apply', pick: JSON.stringify(pick) }).then(function (j) { if (!j.ok) { b.disabled = false; return (window.fxToast ? function (m) { fxToast(m, 'error'); } : alert)(j.error || C.err); } if (window.fxToast) fxToast(C.done.replace('%s', j.done)); setTimeout(function () { location.reload(); }, 700); }).catch(function () { b.disabled = false; });
  });
})();
