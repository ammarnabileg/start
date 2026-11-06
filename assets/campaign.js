/* حملة متكاملة: معاينة حية للمنشورات (من القوالب) وتعديلها، وبعدين إنشاء كل حاجة مرة واحدة */
(function () {
  var C = window.CMP, f = document.getElementById('cmp-f'); if (!C || !f) return;
  var box = document.getElementById('cmp-posts'), go = document.getElementById('cmp-go'), sum = document.getElementById('cmp-sum'), done = document.getElementById('cmp-done'), tmr = null, seq = 0;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fd(extra) { var d = new FormData(f); Object.keys(extra || {}).forEach(function (k) { [].concat(extra[k]).forEach(function (v) { d.append(k, v); }); }); d.append('_csrf', C.csrf); return d; }
  function post(d) { return fetch(C.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function count() { var n = box.querySelectorAll('[data-post]').length, a = f.querySelectorAll('[name="accounts[]"]:checked').length; sum.textContent = C.sum.replace('%s', n).replace('%s', a); go.disabled = !n || !a; }
  function preview() {
    var my = ++seq; box.style.opacity = '.55'; var d = fd({ action: 'preview' });
    post(d).then(function (j) {
      if (my !== seq) return; box.style.opacity = '';
      if (!j.ok) { box.innerHTML = '<p class="text-sm text-red-600">' + esc(j.error || C.err) + '</p>'; return; }
      box.innerHTML = j.posts.map(function (p, i) {
        return '<div class="card p-3" data-post><div class="flex items-center gap-2 mb-2"><span class="w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs grid place-items-center">' + (i + 1) + '</span><span class="text-[11px] rounded-full bg-ink-100 px-2 py-0.5 text-ink-600">' + esc(C.angles[p.angle] || p.angle) + '</span>' +
          '<input type="datetime-local" class="input !mt-0 !py-1 !w-auto text-xs ms-auto" data-w value="' + esc(p.when) + '"><button type="button" class="text-ink-400 hover:text-red-600" data-rm title="' + esc(C.del) + '">' + (window.uic ? uic('trash', 'w-4 h-4') : '✕') + '</button></div>' +
          '<textarea class="input text-sm" rows="' + Math.min(9, p.text.split('\n').length + 1) + '" dir="auto" data-t>' + esc(p.text) + '</textarea></div>';
      }).join('');
      count();
    }).catch(function () { box.style.opacity = ''; });
  }
  f.addEventListener('input', function (e) { if (e.target.name === 'accounts[]') return count(); clearTimeout(tmr); tmr = setTimeout(preview, 350); });
  f.addEventListener('change', function (e) { if (e.target.name === 'accounts[]') count(); else { clearTimeout(tmr); tmr = setTimeout(preview, 150); } });
  box.addEventListener('click', function (e) { var r = e.target.closest('[data-rm]'); if (r) { r.closest('[data-post]').remove(); Array.prototype.forEach.call(box.querySelectorAll('[data-post] .rounded-full.bg-brand-600'), function (b, i) { b.textContent = i + 1; }); count(); } });
  go.addEventListener('click', function () {
    var texts = [], whens = []; Array.prototype.forEach.call(box.querySelectorAll('[data-post]'), function (p) { texts.push(p.querySelector('[data-t]').value); whens.push(p.querySelector('[data-w]').value); });
    go.disabled = true; var old = go.innerHTML; go.textContent = C.busy;
    post(fd({ action: 'create', 'text[]': texts, 'when[]': whens })).then(function (j) {
      go.innerHTML = old; if (!j.ok) { go.disabled = false; if (window.fxToast) fxToast(j.error || C.err, 'error'); return; }
      var row = function (l, v, mono) { return v ? '<div class="flex flex-wrap items-center gap-2 text-sm"><span class="text-ink-500 w-28">' + esc(l) + '</span><a href="' + esc(/^https?:/.test(v) ? v : '#') + '" target="_blank" class="' + (mono ? 'font-mono ' : '') + 'link break-all" dir="ltr">' + esc(v) + '</a></div>' : ''; };
      done.innerHTML = '<div class="card p-4 border-lime-300 bg-lime-50/60 space-y-1.5"><b class="text-lime-800 inline-flex items-center gap-1.5">' + (window.uic ? uic('check', 'w-5 h-5') : '') + esc(C.done.replace('%s', j.made)) + '</b>' +
        row(C.lp, j.lp) + row(C.link, j.link) + row(C.wa, j.wa) + (j.coupon ? '<div class="text-sm"><span class="text-ink-500 w-28 inline-block">' + esc(C.coupon) + '</span><b class="font-mono">' + esc(j.coupon) + '</b></div>' : '') +
        (j.fail.length ? '<p class="text-xs text-red-600">' + esc(C.fail.replace('%s', j.fail.length)) + ': ' + esc(j.fail[0]) + '</p>' : '') + (j.skipped.length ? '<p class="text-xs text-amber-700">' + esc(C.skip.replace('%s', j.skipped.join('، '))) + '</p>' : '') +
        '<a href="' + esc(C.calUrl) + '" class="btn-primary !py-1.5 !text-xs mt-2 inline-flex">' + esc(C.cal) + '</a></div>';
      done.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }).catch(function () { go.disabled = false; go.innerHTML = old; });
  });
  preview();
})();
