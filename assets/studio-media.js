/* استوديو المحتوى: توليد صور وفيديو (Higgsfield) ومعرض النتايج */
(function () {
  var C = window.SM; if (!C) return; var L = C.L;
  var go = document.getElementById('sm-go'), pr = document.getElementById('sm-p'), st = document.getElementById('sm-st'), g = document.getElementById('sm-g'), none = document.getElementById('sm-none'), pending = 0;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function val(n) { var x = document.querySelector('input[name="' + n + '"]:checked'); return x ? x.value : ''; }
  function call(d) { var f = new FormData(); Object.keys(d).forEach(function (k) { f.append(k, d[k]); }); return fetch('index.php?p=ai_task', { method: 'POST', body: f, headers: { 'X-CSRF-Token': C.csrf }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function msg(t, err) { st.textContent = t; st.className = 'text-sm ' + (err ? 'text-red-600' : 'text-brand-700'); }
  function busy(on) { go.disabled = on; go.classList.toggle('animate-pulse', on); }
  function ph() { var d = document.createElement('div'); d.className = 'sm-ph rounded-2xl mk-sk aspect-square'; g.prepend(d); none.classList.add('hidden'); return d; }
  function item(img, slot) {
    var d = document.createElement('div'); d.className = 'sm-it group relative rounded-2xl overflow-hidden bg-ink-100 aspect-square cp-pop';
    d.innerHTML = (img.video ? '<video muted loop playsinline autoplay class="w-full h-full object-cover"></video>' : '<img alt="" class="w-full h-full object-cover">') +
      '<div class="absolute inset-x-0 bottom-0 p-2 flex gap-1.5 bg-gradient-to-t from-black/70 to-transparent">' + (img.video ? '' : '<a class="flex-1 rounded-lg bg-white/95 text-ink-900 text-xs font-medium py-1.5 text-center" href="' + esc(C.designer + '&img=' + encodeURIComponent(img.src)) + '">' + esc(L.design) + '</a>') +
      '<a class="flex-1 rounded-lg bg-brand-600 text-white text-xs font-medium py-1.5 text-center" href="' + esc(C.compose) + '">' + esc(L.post) + '</a></div>';
    d.firstChild.src = img.src; slot.replaceWith(d);
  }
  function poll(id, slot, tries) {
    call({ task: 'image_status', id: id }).then(function (s) {
      if (s.error) { slot.remove(); msg(s.error, true); if (--pending <= 0) busy(false); return; }
      if (s.status === 'completed') { (s.images || []).forEach(function (im, i) { item(im, i ? (function () { var x = ph(); return x; })() : slot); }); msg(L.done); if (--pending <= 0) busy(false); return; }
      if (tries > 80) { slot.remove(); msg(L.slow, true); if (--pending <= 0) busy(false); return; }
      setTimeout(function () { poll(id, slot, tries + 1); }, 3000);
    }).catch(function () { setTimeout(function () { poll(id, slot, tries + 1); }, 5000); });
  }
  go.addEventListener('click', function () {
    if (!pr.value.trim()) { pr.focus(); return; }
    var kind = val('sm-kind') || 'image', two = document.getElementById('sm-two').checked && kind === 'image'; busy(true); msg(kind === 'video' ? L.vbusy : L.busy);
    call({ task: 'image', kind: kind, text: pr.value, aspect: val('sm-ar'), count: two ? 2 : 1 }).then(function (d) {
      if (d.error) { busy(false); return msg(d.error, true); }
      var ids = d.ids || [d.id]; pending = ids.length; ids.forEach(function (id) { poll(id, ph(), kind === 'video' ? -140 : 0); });
    }).catch(function () { busy(false); msg(L.err, true); });
  });
  pr.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) go.click(); });
  g.addEventListener('mouseover', function (e) { var v = e.target.closest('.sm-it') && e.target.closest('.sm-it').querySelector('video'); if (v) v.play().catch(function () {}); });
})();
