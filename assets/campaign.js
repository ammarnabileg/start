/* حملة متكاملة: تايملاين للحملة، كروت منشورات بمعاينة حية (منشور أو ستوري)، ذكاء اصطناعي لكل منشور، تكرار/حذف/إضافة، وبعدين إنشاء كل حاجة مرة واحدة */
(function () {
  var C = window.CMP, f = document.getElementById('cmp-f'); if (!C || !f) return;
  var box = document.getElementById('cmp-posts'), go = document.getElementById('cmp-go'), sum = document.getElementById('cmp-sum'), done = document.getElementById('cmp-done'), tl = document.getElementById('cmp-tl'), tmr = null, seq = 0, range = null;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function fd(extra) { var d = new FormData(f); Object.keys(extra || {}).forEach(function (k) { [].concat(extra[k]).forEach(function (v) { d.append(k, v); }); }); d.append('_csrf', C.csrf); return d; }
  function post(d) { return fetch(C.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function cards() { return [].slice.call(box.querySelectorAll('[data-post]')); }
  function img() { var i = document.getElementById('cmp-img'); return i && /^https:\/\//i.test(i.value.trim()) ? i.value.trim() : ''; }
  function accs() { return [].slice.call(f.querySelectorAll('[name="accounts[]"]:checked')); }

  // الرموز بتظهر كـ "شيبس" مفهومة في المعاينة
  function render(t) {
    return esc(t).replace(/\{link\}/g, '<span class="cmp-tok bg-brand-50 text-brand-700">🔗 ' + esc(C.tLink) + '</span>').replace(/\{wa\}/g, '<span class="cmp-tok bg-lime-50 text-lime-800">💬 ' + esc(C.tWa) + '</span>')
      .replace(/XXXX/g, '<span class="cmp-tok bg-amber-50 text-amber-800 font-mono">🎟 ' + esc(C.tCoupon) + '</span>').replace(/(#[^\s#<]+)/g, '<span class="text-brand-600">$1</span>');
  }
  function paint(card) {
    var t = card.querySelector('[data-t]').value, story = card.dataset.type === 'story', pv = card.querySelector('[data-pv]'), im = img();
    var a = accs()[0], nm = a ? (a.dataset.name || '') : C.brand;
    pv.innerHTML = story
      ? '<div class="relative mx-auto aspect-[9/16] w-40 rounded-2xl overflow-hidden bg-gradient-to-br from-fuchsia-600 via-brand-600 to-cyan-500 text-white shadow-lg">' + (im ? '<img src="' + esc(im) + '" class="absolute inset-0 w-full h-full object-cover">' : '') + '<div class="absolute inset-0 bg-gradient-to-b from-black/40 via-transparent to-black/70"></div><span class="absolute top-1.5 inset-x-2 h-0.5 rounded-full bg-white"></span><b class="absolute top-4 start-2 text-[10px]">' + esc(nm) + '</b><p class="absolute bottom-2 inset-x-2 text-[10px] leading-4 line-clamp-5" dir="auto">' + render(t.slice(0, 140)) + '</p>' + (im ? '' : '<p class="absolute inset-0 grid place-items-center text-[10px] text-white/85 px-3 text-center">' + esc(C.needImg) + '</p>') + '</div>'
      : '<div class="rounded-xl border border-ink-200 bg-white overflow-hidden"><div class="flex items-center gap-2 p-2.5"><span class="w-7 h-7 rounded-full bg-gradient-to-br from-brand-600 to-cyan-500"></span><div class="min-w-0"><b class="block text-[12px] text-ink-900 truncate">' + esc(nm) + '</b><span class="text-[10px] text-ink-500">' + esc(fmtWhen(card.querySelector('[data-w]').value)) + '</span></div></div><p class="px-2.5 pb-2.5 text-[12px] leading-5 text-ink-800 whitespace-pre-wrap break-words line-clamp-[10]" dir="auto">' + render(t) + '</p>' + (im ? '<img src="' + esc(im) + '" class="w-full max-h-40 object-cover">' : '') + '</div>';
    var n = Array.from(t).length, cc = card.querySelector('[data-cc]'); cc.textContent = n; cc.className = 'tabular-nums ' + (n > 280 && accs().some(function (x) { return x.dataset.plat === 'twitter'; }) ? 'text-red-600' : 'text-ink-400');
    card.querySelectorAll('[data-ty]').forEach(function (b) { var on = b.dataset.ty === (card.dataset.type || 'post'); b.className = 'rounded-md px-2 py-0.5 transition ' + (on ? 'bg-white shadow-sm text-brand-700 font-medium' : 'text-ink-500 hover:text-ink-800'); });
  }
  function fmtWhen(v) { if (!v) return ''; var d = new Date(v); return isNaN(d) ? v : d.toLocaleString(document.documentElement.lang || undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }); }
  function cardHTML(p) {
    return '<div class="card p-0 overflow-hidden cmp-card cp-pop" data-post data-type="' + esc(p.type || 'post') + '" data-angle="' + esc(p.angle || 'custom') + '">' +
      '<div class="flex flex-wrap items-center gap-2 px-4 py-2.5 border-b border-ink-100 bg-ink-50/60"><span data-n class="w-7 h-7 rounded-full bg-brand-600 text-[#fff] text-xs font-bold grid place-items-center"></span>' +
      '<span class="text-[11px] rounded-full bg-white border border-ink-200 px-2 py-0.5 text-ink-700">' + esc(C.angles[p.angle] || C.custom) + '</span>' +
      '<span class="inline-flex rounded-lg bg-ink-100 p-0.5 text-[11px]"><button type="button" data-ty="post">' + esc(C.kPost) + '</button><button type="button" data-ty="story">' + esc(C.kStory) + '</button></span>' +
      '<input type="datetime-local" class="input !mt-0 !py-1 !h-8 !w-auto text-xs ms-auto" data-w value="' + esc(p.when || '') + '">' +
      '<span class="flex items-center gap-0.5">' + (window.aiRun && window.aiItems && window.aiItems.improve ? '<button type="button" class="cmp-ib text-brand-600" data-ai title="' + esc(window.aiItems.improve) + '">✦</button>' : '') +
      '<button type="button" class="cmp-ib" data-dup title="' + esc(C.dup) + '">' + ic('copy') + '</button><button type="button" class="cmp-ib hover:!text-red-600" data-rm title="' + esc(C.del) + '">' + ic('trash') + '</button></span></div>' +
      '<div class="grid md:grid-cols-[minmax(0,1fr)_230px] gap-4 p-4"><div class="min-w-0"><textarea class="input !mt-0 text-sm leading-6" rows="7" dir="auto" data-t>' + esc(p.text || '') + '</textarea>' +
      '<div class="flex flex-wrap items-center gap-1.5 mt-2 text-[11px]"><span class="text-ink-400">' + esc(C.insert) + '</span><button type="button" class="cmp-chip" data-ins="{link}">🔗 ' + esc(C.tLink) + '</button><button type="button" class="cmp-chip" data-ins="{wa}">💬 ' + esc(C.tWa) + '</button><button type="button" class="cmp-chip" data-ins="XXXX">🎟 ' + esc(C.tCoupon) + '</button><span class="ms-auto" data-cc></span></div></div>' +
      '<div data-pv></div></div></div>';
  }
  function number() { cards().forEach(function (c, i) { c.querySelector('[data-n]').textContent = i + 1; }); }
  // التايملاين: أيام الحملة ونقطة لكل منشور
  function timeline() {
    if (!tl) return; var st = f.start.value, days = Math.max(1, Math.min(60, +f.days.value || 14)); if (!st) { tl.innerHTML = ''; return; }
    var d0 = new Date(st + 'T00:00'), cs = cards(), cells = '';
    for (var i = 0; i < days; i++) {
      var d = new Date(d0); d.setDate(d.getDate() + i); var key = d.toISOString ? (d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2)) : '';
      var hits = cs.map(function (c, k) { return (c.querySelector('[data-w]').value || '').slice(0, 10) === key ? k : -1; }).filter(function (k) { return k > -1; });
      cells += '<div class="flex flex-col items-center gap-1 min-w-[34px]"><span class="text-[10px] text-ink-400">' + d.toLocaleDateString(document.documentElement.lang || undefined, { weekday: 'narrow' }) + '</span><span class="text-[11px] tabular-nums ' + (hits.length ? 'text-ink-900 font-semibold' : 'text-ink-400') + '">' + d.getDate() + '</span><span class="flex flex-col gap-0.5 min-h-[22px]">' +
        hits.map(function (k) { return '<button type="button" data-go="' + k + '" class="w-6 h-6 rounded-full text-[10px] font-bold text-white grid place-items-center hover:scale-110 transition ' + (cs[k].dataset.type === 'story' ? 'bg-gradient-to-br from-fuchsia-500 to-brand-600' : 'bg-brand-600') + '" title="' + esc(fmtWhen(cs[k].querySelector('[data-w]').value)) + '">' + (k + 1) + '</button>'; }).join('') + '</span></div>';
    }
    tl.innerHTML = '<div class="flex gap-1 overflow-x-auto pb-1" style="scrollbar-width:thin">' + cells + '</div>';
  }
  function refresh() { number(); cards().forEach(paint); timeline(); count(); }
  function count() { var n = cards().length, a = accs().length, s = cards().filter(function (c) { return c.dataset.type === 'story'; }).length; sum.innerHTML = '<b class="text-ink-900">' + esc(C.sum.replace('%s', n).replace('%s', a)) + '</b>' + (s ? ' · ' + esc(C.stories.replace('%s', s)) : ''); go.disabled = !n || !a; }
  function preview() {
    var my = ++seq; box.classList.add('opacity-60'); var d = fd({ action: 'preview' });
    post(d).then(function (j) {
      if (my !== seq) return; box.classList.remove('opacity-60');
      if (!j.ok) { box.innerHTML = '<p class="text-sm text-red-600">' + esc(j.error || C.err) + '</p>'; return; }
      box.innerHTML = j.posts.map(cardHTML).join(''); refresh();
    }).catch(function () { box.classList.remove('opacity-60'); });
  }
  // تغيير إعدادات الحملة بيعيد بناء المنشورات، أما الحسابات والصورة بتحدّث المعاينة بس
  var soft = /^(accounts\[\]|img|mode)$/;
  f.addEventListener('input', function (e) { if (soft.test(e.target.name)) return refresh(); clearTimeout(tmr); tmr = setTimeout(preview, 400); });
  f.addEventListener('change', function (e) { if (soft.test(e.target.name)) { paintImg(); return refresh(); } clearTimeout(tmr); tmr = setTimeout(preview, 150); });
  function paintImg() { var t = document.getElementById('cmp-img-pv'), u = img(); if (t) t.innerHTML = u ? '<img src="' + esc(u) + '" class="w-full h-32 object-cover rounded-xl border border-ink-200">' : ''; }
  box.addEventListener('input', function (e) { var c = e.target.closest('[data-post]'); if (c) { paint(c); if (e.target.matches('[data-w]')) timeline(); } });
  box.addEventListener('click', function (e) {
    var c = e.target.closest('[data-post]'); if (!c) return; var b;
    if ((b = e.target.closest('[data-rm]'))) { c.style.transition = 'opacity .2s, transform .2s'; c.style.opacity = '0'; c.style.transform = 'scale(.97)'; setTimeout(function () { c.remove(); refresh(); }, 180); return; }
    if ((b = e.target.closest('[data-dup]'))) { var n = document.createElement('div'); n.innerHTML = cardHTML({ angle: c.dataset.angle, type: c.dataset.type, text: c.querySelector('[data-t]').value, when: shift(c.querySelector('[data-w]').value, 1) }); c.after(n.firstChild); refresh(); return; }
    if ((b = e.target.closest('[data-ty]'))) { c.dataset.type = b.dataset.ty; paint(c); timeline(); count(); return; }
    if ((b = e.target.closest('[data-ins]'))) { var ta = c.querySelector('[data-t]'), s0 = ta.selectionStart || ta.value.length; ta.value = ta.value.slice(0, s0) + b.dataset.ins + ta.value.slice(ta.selectionEnd || s0); ta.focus(); paint(c); return; }
    if ((b = e.target.closest('[data-ai]'))) { var t2 = c.querySelector('[data-t]'); var p = window.aiRun(t2, 'improve', b); if (p && p.then) p.then(function () { paint(c); }); setTimeout(function () { paint(c); }, 4000); }
  });
  tl && tl.addEventListener('click', function (e) { var b = e.target.closest('[data-go]'); if (!b) return; var c = cards()[+b.dataset.go]; c.scrollIntoView({ behavior: 'smooth', block: 'center' }); c.classList.add('ring-4', 'ring-brand-500/25'); setTimeout(function () { c.classList.remove('ring-4', 'ring-brand-500/25'); }, 1200); });
  function shift(v, days) { if (!v) return ''; var d = new Date(v); d.setDate(d.getDate() + days); var p = function (n) { return ('0' + n).slice(-2); }; return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes()); }
  var add = document.getElementById('cmp-add'); if (add) add.addEventListener('click', function () { var last = cards().pop(), n = document.createElement('div'); n.innerHTML = cardHTML({ angle: 'custom', text: '\n\n{link}', when: last ? shift(last.querySelector('[data-w]').value, 1) : '' }); box.appendChild(n.firstChild); refresh(); var t = cards().pop().querySelector('[data-t]'); t.focus(); t.setSelectionRange(0, 0); });

  go.addEventListener('click', function () {
    var texts = [], whens = [], types = []; cards().forEach(function (p) { texts.push(p.querySelector('[data-t]').value); whens.push(p.querySelector('[data-w]').value); types.push(p.dataset.type || 'post'); });
    go.disabled = true; var old = go.innerHTML; go.textContent = C.busy;
    post(fd({ action: 'create', 'text[]': texts, 'when[]': whens, 'type[]': types })).then(function (j) {
      go.innerHTML = old; if (!j.ok) { go.disabled = false; if (window.fxToast) fxToast(j.error || C.err, 'error'); return; }
      var row = function (l, v, icn) { return v ? '<div class="flex items-center gap-3 rounded-xl bg-white border border-lime-200 px-3 py-2"><span class="text-lime-700">' + ic(icn) + '</span><span class="text-xs text-ink-500 w-24 shrink-0">' + esc(l) + '</span><a href="' + esc(/^https?:/.test(v) ? v : '#') + '" target="_blank" class="link text-sm truncate" dir="ltr">' + esc(v) + '</a><button type="button" class="ms-auto text-xs text-ink-500 hover:text-ink-900" data-copy="' + esc(v) + '">' + ic('copy') + '</button></div>' : ''; };
      done.innerHTML = '<div class="card p-5 border-lime-300 bg-gradient-to-br from-lime-50 to-white space-y-2 cp-pop"><div class="flex items-center gap-3"><span class="w-11 h-11 rounded-2xl bg-lime-500 text-white grid place-items-center text-xl">✓</span><b class="text-lg text-ink-900">' + esc(C.done.replace('%s', j.made)) + '</b></div>' +
        row(C.lp, j.lp, 'file') + row(C.link, j.link, 'link') + row(C.wa, j.wa, 'chat') + (j.coupon ? row(C.coupon, j.coupon, 'tag') : '') +
        (j.fail.length ? '<p class="text-xs text-red-600">' + esc(C.fail.replace('%s', j.fail.length)) + ': ' + esc(j.fail[0]) + '</p>' : '') + (j.skipped.length ? '<p class="text-xs text-amber-700">' + esc(C.skip.replace('%s', j.skipped.join('، '))) + '</p>' : '') +
        '<div class="flex gap-2 pt-1"><a href="' + esc(C.calUrl) + '" class="btn-primary !py-1.5 !text-sm">' + esc(C.cal) + '</a><a href="' + esc(C.url) + '#cmp-list" class="btn-ghost !py-1.5 !text-sm" onclick="setTimeout(function(){location.reload()},50)">' + esc(C.report) + '</a></div></div>';
      done.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }).catch(function () { go.disabled = false; go.innerHTML = old; });
  });
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-copy]'); if (!b) return; try { navigator.clipboard.writeText(b.dataset.copy); if (window.fxToast) fxToast(C.copied); } catch (x) {} });
  // حذف حملة من القايمة
  var list = document.getElementById('cmp-list'); if (list) list.addEventListener('click', function (e) { var b = e.target.closest('[data-cdel]'); if (!b || !confirm(C.delQ)) return; var d = new FormData(); d.append('_csrf', C.csrf); d.append('action', 'del'); d.append('id', b.dataset.cdel);
    post(d).then(function (j) { if (j.ok) { var c = b.closest('[data-camp]'); c.style.transition = 'opacity .2s'; c.style.opacity = '0'; setTimeout(function () { c.remove(); }, 200); } }); });
  paintImg(); preview();
})();
