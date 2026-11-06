/* زرار "ضيفه Lead" في الرسائل والتعليقات: بيبعت للـ CRM ويحوّل الزرار لرابط */
(function () {
  var X = window.TOLEAD; if (!X) return;
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-tolead] [data-go]'); if (!b) return;
    var box = b.closest('[data-tolead]'), fd = new FormData();
    fd.append('action', 'social'); fd.append('_csrf', X.csrf);
    ['source', 'ext', 'name', 'text', 'url', 'where'].forEach(function (k) { fd.append(k, box.dataset[k] || ''); });
    box.querySelectorAll('[data-f]').forEach(function (i) { fd.append(i.dataset.f, i.value.trim()); });
    b.disabled = true;
    fetch(X.url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(function (r) {
      if (!r.ok) { b.disabled = false; return alert(r.error || X.err); }
      var a = document.createElement('a'); a.href = r.url; a.className = 'text-lime-700 text-xs font-semibold hover:underline';
      a.textContent = '🧲 ' + (r.merged ? X.merged : X.added) + ' · ' + X.open + ' ↗';
      box.innerHTML = ''; box.appendChild(a);
    }).catch(function () { b.disabled = false; alert(X.err); });
  });
})();
