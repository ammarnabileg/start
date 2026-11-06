/* المنافسات والمناقصات: قايمة بكروت أرقام، بوب أب التفاصيل، وحفظ/تقديم/صفقة/إخفاء لصف أو لكذا صف */
(function () {
  var C = window.TND, root = document.getElementById('tnd'); if (!C || !root) return; var L = C.L;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function jd(r) { try { return JSON.parse(r.dataset.j); } catch (e) { return {}; } }
  function post(a, id) { var d = new FormData(); d.append('_csrf', C.csrf); d.append('action', a); d.append('id', id); return fetch(C.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  var dlg = document.getElementById('tnd-dlg'), cur = null;
  var mk = MKList(root, { onOpen: open });
  var first = root.querySelector('[data-mk-stat="match"]'); if (first) { first.classList.remove('mk-on'); first.click(); } // الافتراضي: تناسبك
  // الأرقام فوق بتتحدث مع كل تغيير
  function recount() { var n = { match: 0, soon: 0, fav: 0, applied: 0, all: 0 };
    mk.rows().forEach(function (r) { var d = r.dataset; if (d.match === '1') n.match++; if (d.soon === '1') n.soon++; if (d.mark === 'fav') n.fav++; if (d.mark === 'applied') n.applied++; if (d.act === '1') n.all++; });
    Object.keys(n).forEach(function (k) { var b = root.querySelector('[data-cnt="' + k + '"]'); if (b) b.textContent = n[k].toLocaleString(); }); }
  function setMark(r, m) {
    var hid = m === 'hidden'; r.dataset.mark = m || '';
    if (hid) { r.dataset.match = '0'; r.dataset.soon = '0'; r.dataset.act = '0'; }
    r.querySelectorAll('.mk-act[data-a]').forEach(function (b) { b.classList.toggle('on', b.dataset.a === m); });
    var p = r.querySelector('[data-mark-pill]'); if (p) { p.textContent = C.marks[m] || ''; p.classList.toggle('hidden', !(m === 'fav' || m === 'applied')); }
    recount(); mk.apply();
  }
  function act(r, a) {
    var cur = r.dataset.mark; if (a !== 'deal' && cur === a) a = 'clear';
    return post(a, r.dataset.id).then(function (j) {
      if (!j.ok) { toast(j.error || L.err, 'error'); return j; }
      setMark(r, j.status || '');
      if (j.lead) { var d = jd(r); d.lead = j.url; r.dataset.j = JSON.stringify(d); toast(L.deal); } else if (j.status) toast(L.saved);
      return j;
    }).catch(function () { toast(L.err, 'error'); });
  }
  root.addEventListener('click', function (e) { var b = e.target.closest('.mk-act[data-a]'); if (!b) return; b.disabled = true; act(b.closest('[data-mk-row]'), b.dataset.a).then(function () { b.disabled = false; }); });
  root.querySelectorAll('[data-bulk]').forEach(function (b) { b.addEventListener('click', function () {
    var s = mk.selected(), a = b.dataset.bulk; if (!s.length) return;
    Promise.all(s.map(function (r) { return r.dataset.mark === a ? null : act(r, a); })).then(function () { toast(L.done.replace('%s', s.length)); mk.clear(); }); }); });
  // بوب أب التفاصيل
  function open(r) {
    cur = r; var d = jd(r);
    document.getElementById('td-name').textContent = d.name; document.getElementById('td-sub').textContent = [d.agency, d.branch !== d.agency ? d.branch : ''].filter(Boolean).join(' · ');
    document.getElementById('td-hits').innerHTML = d.hits && d.hits.length ? '<span class="text-xs text-ink-500">' + esc(L.matched) + '</span>' + d.hits.map(function (h) { return '<span class="mk-pill bg-amber-50 text-amber-800 border border-amber-200">' + esc(h) + '</span>'; }).join('') : '';
    document.getElementById('td-meta').innerHTML = [[L.dl, d.deadline + (d.days != null && d.days >= 0 ? ' (' + d.days + ')' : '')], [L.inq, d.inquiry], [L.pub, d.published], [L.act, d.activity], [L.type, d.type], [L.bk, d.booklet], [L.ref, d.ref]]
      .filter(function (x) { return x[1]; }).map(function (x) { return '<div class="rounded-xl bg-ink-50 px-3 py-2 min-w-0"><dt class="text-[11px] text-ink-500">' + esc(x[0]) + '</dt><dd class="font-medium text-ink-900 break-words" dir="auto">' + esc(x[1]) + '</dd></div>'; }).join('');
    document.getElementById('td-url').href = d.url;
    var lead = document.getElementById('td-lead'); lead.classList.toggle('hidden', !d.lead); if (d.lead) lead.href = d.lead;
    paint(); dlg.showModal();
  }
  function paint() { var m = cur ? cur.dataset.mark : ''; document.querySelectorAll('#td-acts [data-a]').forEach(function (b) { b.classList.toggle('!bg-amber-50', b.dataset.a === m); b.classList.toggle('!text-amber-700', b.dataset.a === m); }); }
  document.getElementById('td-acts').addEventListener('click', function (e) { var b = e.target.closest('[data-a]'); if (!b || !cur) return; b.disabled = true;
    act(cur, b.dataset.a).then(function (j) { b.disabled = false; paint(); var d = jd(cur), lead = document.getElementById('td-lead'); if (d.lead) { lead.href = d.lead; lead.classList.remove('hidden'); } }); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-close]')) dlg.close(); });
})();
