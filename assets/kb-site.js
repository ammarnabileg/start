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
  $('#kbs-f').addEventListener('submit', function (e) {
    e.preventDefault(); var b = $('#kbs-f button'); b.disabled = true; foot.classList.add('hidden');
    out.innerHTML = '<div class="flex items-center gap-2 text-sm text-ink-500 py-6 justify-center"><span class="w-4 h-4 rounded-full border-2 border-brand-600 border-t-transparent animate-spin"></span>' + esc(C.reading) + '</div>';
    post({ action: 'kb_site', url: $('#kbs-f [name=url]').value }).then(function (j) {
      b.disabled = false; if (!j.ok) { out.innerHTML = '<p class="text-sm text-red-600 py-4">' + esc(j.error || C.err) + '</p>'; return; }
      groups = j.groups; if (!groups.length) { out.innerHTML = '<p class="text-sm text-ink-500 py-6 text-center">' + esc(C.none) + '</p>'; return; }
      var h = '<p class="text-[11px] text-ink-400">' + esc(C.pages.replace('%s', j.pages.length)) + '</p>';
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
    }).catch(function () { b.disabled = false; out.innerHTML = '<p class="text-sm text-red-600 py-4">' + esc(C.err) + '</p>'; });
  });
  out.addEventListener('change', function (e) { if (e.target.type === 'checkbox') count(); });
  $('#kbs-apply').addEventListener('click', function () {
    var pick = {};
    $$('#kbs-out input[type=checkbox]:checked').forEach(function (c) {
      var g = groups[+c.dataset.g]; pick[g.sec] = pick[g.sec] || (g.type === 'rows' ? { rows: [] } : {});
      if (c.dataset.f != null) { var f = g.fields[+c.dataset.f]; pick[g.sec][f.f] = $('[data-v="' + c.dataset.g + '-' + c.dataset.f + '"]').value; }
      else { var row = {}; Object.keys(g.cols).forEach(function (k) { row[k] = $('[data-rv="' + c.dataset.g + '-' + c.dataset.r + '-' + k + '"]').value; }); pick[g.sec].rows.push(row); }
    });
    var b = this; b.disabled = true;
    post({ action: 'kb_site_apply', pick: JSON.stringify(pick) }).then(function (j) { if (!j.ok) { b.disabled = false; return alert(j.error || C.err); } if (window.fxToast) fxToast(C.done.replace('%s', j.done)); setTimeout(function () { location.reload(); }, 700); }).catch(function () { b.disabled = false; });
  });
})();
