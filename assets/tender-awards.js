/* المنافسات المرساة: بحث، متابعة التشغيلات، تفاصيل كل ترسية (المتقدمين، المطابقة، التواصل)، وعميل + عرض سعر */
(function () {
  var C = window.AW; if (!C) return; var T = C.t;
  function $(s, r) { return (r || document).querySelector(s); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function num(n) { return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 0 }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function post(d) { var fd = new FormData(); fd.append('_csrf', C.csrf); for (var k in d) fd.append(k, d[k]); return fetch(C.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  var f = $('#aw-f');
  function cost() { if (!f) return; var o = f.actor.selectedOptions[0]; $('#aw-cost').textContent = T.cost.replace('%s', (+o.dataset.price * +f.max.value).toFixed(2)); }
  if (f) { f.addEventListener('change', cost); cost();
    f.addEventListener('submit', function (e) { e.preventDefault(); var b = f.querySelector('button'); b.disabled = true;
      post({ action: 'search', words: f.words.value, days: f.days.value, max: f.max.value, actor: f.actor.value }).then(function (j) { b.disabled = false; if (!j.ok) return toast(j.error || T.err, 'error'); toast(T.started.replace('%s', j.runs)); $('#aw-status').classList.remove('hidden'); poll(); })
        .catch(function () { b.disabled = false; toast(T.err, 'error'); }); }); }
  function poll() { post({ action: 'poll' }).then(function (j) { if (!j.ok) return; if (j.error) toast(j.error, 'error'); if (j.running) return setTimeout(poll, 8000); $('#aw-status').classList.add('hidden'); if (j.new) location.reload(); }).catch(function () { setTimeout(poll, 15000); }); }
  if (C.running) poll();

  function contactHtml(c) {
    if (!c) return '';
    var rows = [[T.phone, c.phone ? '<a class="link" dir="ltr" href="tel:' + esc(c.phone) + '">' + esc(c.phone) + '</a>' : ''], [T.site, c.website ? '<a class="link" dir="ltr" target="_blank" rel="noopener" href="' + esc(/^https?:/.test(c.website) ? c.website : 'https://' + c.website) + '">' + esc(c.website) + '</a>' : ''],
      [T.addr, esc(c.address || '')], [T.maps, c.maps ? '<a class="link" target="_blank" rel="noopener" href="' + esc(c.maps) + '">' + esc(c.place || T.maps) + '</a>' : ''], ['Apollo', c.apollo ? esc([c.apollo.domain, c.apollo.emp ? T.emp.replace('%s', c.apollo.emp) : '', c.apollo.phone].filter(Boolean).join(' · ')) + (c.apollo.linkedin ? ' · <a class="link" target="_blank" rel="noopener" href="' + esc(c.apollo.linkedin) + '">LinkedIn</a>' : '') : '']].filter(function (r) { return r[1]; });
    return rows.length ? '<dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">' + rows.map(function (r) { return '<dt class="text-ink-500">' + esc(r[0]) + '</dt><dd class="min-w-0 truncate">' + r[1] + '</dd>'; }).join('') + '</dl>' : '<p class="text-sm text-ink-500">' + esc(T.noContact) + '</p>';
  }
  function render(box, row, j) {
    var name = $('.aw-open .font-medium', row).textContent, link = row.dataset.url || '';
    var bid = (j.bidders || []).slice().sort(function (a, b) { return (a.v || 0) - (b.v || 0); });
    box.innerHTML = '<div class="grid lg:grid-cols-3 gap-4 pt-1">'
      + '<section class="rounded-2xl border border-ink-200 p-3"><h4 class="text-xs font-bold text-ink-500 mb-2">' + esc(T.scope) + '</h4><p class="text-sm text-ink-900">' + esc(name) + '</p><p class="text-[11px] text-ink-400 mt-2">' + esc(T.booklet) + '</p>'
        + '<h4 class="text-xs font-bold text-ink-500 mt-3 mb-1">' + esc(T.match) + '</h4>' + ((j.match || []).length ? '<ul class="text-sm space-y-1">' + j.match.map(function (m) { return '<li class="flex gap-2"><span class="flex-1">✓ ' + esc(m.name) + '</span><span class="text-ink-500" dir="ltr">' + esc(m.price) + '</span></li>'; }).join('') + '</ul>' : '<p class="text-xs text-ink-500">' + esc(T.noMatch) + '</p>') + '</section>'
      + '<section class="rounded-2xl border border-ink-200 p-3"><h4 class="text-xs font-bold text-ink-500 mb-2">' + esc(T.bidders) + ' (' + bid.length + ')</h4><ul class="text-sm divide-y divide-ink-100">' + bid.map(function (b, i) { return '<li class="py-1.5 flex gap-2"><span class="w-5 text-ink-400">' + (i + 1) + '</span><span class="flex-1 min-w-0 truncate">' + esc(b.s) + (b.r ? ' <span class="text-[11px] text-ink-400">' + esc(b.r) + '</span>' : '') + '</span><span class="tabular-nums" dir="ltr">' + num(b.v) + '</span></li>'; }).join('') + '</ul></section>'
      + '<section class="rounded-2xl border border-ink-200 p-3"><h4 class="text-xs font-bold text-ink-500 mb-2">' + esc(T.contact) + '</h4><div class="aw-c">' + (j.contact ? contactHtml(j.contact) : (C.mgr ? '<button type="button" data-aw="contact" class="btn-ghost !py-1.5 w-full">' + esc(T.getContact) + '</button>' : '')) + '</div>'
        + '<div class="flex flex-wrap gap-2 mt-3"><button type="button" data-aw="lead" class="btn-primary !py-1.5">' + esc(T.toLead) + '</button></div></section>'
      + '</div>';
  }
  document.addEventListener('click', function (e) {
    var o = e.target.closest('.aw-open');
    if (o) { var row = o.closest('.aw-row'), box = $('.aw-d', row); box.classList.toggle('hidden'); if (box.dataset.loaded) return; box.dataset.loaded = '1'; box.innerHTML = '<p class="text-sm text-ink-500">' + esc(T.loading) + '</p>';
      post({ action: 'detail', id: row.dataset.id }).then(function (j) { if (!j.ok) { box.textContent = j.error || T.err; return; } render(box, row, j); }).catch(function () { box.textContent = T.err; }); return; }
    var b = e.target.closest('[data-aw]'); if (!b) return; var r = b.closest('.aw-row'); b.disabled = true;
    if (b.dataset.aw === 'contact') post({ action: 'contact', id: r.dataset.id }).then(function (j) { if (!j.ok) { b.disabled = false; return toast(j.error || T.err, 'error'); } $('.aw-c', r).innerHTML = contactHtml(j.contact); }).catch(function () { b.disabled = false; toast(T.err, 'error'); });
    if (b.dataset.aw === 'lead') post({ action: 'lead', id: r.dataset.id }).then(function (j) { if (!j.ok) { b.disabled = false; return toast(j.error || T.err, 'error'); }
      b.outerHTML = '<a class="btn-ghost !py-1.5" href="' + esc(j.lead) + '">' + esc(T.openLead) + '</a><a class="btn-primary !py-1.5" target="_blank" href="' + esc(j.quote) + '">' + esc(T.quote) + '</a>'; }).catch(function () { b.disabled = false; toast(T.err, 'error'); });
  });
})();
