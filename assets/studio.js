/* استوديو المحتوى: الكتابة بالذكاء الاصطناعي.
   النتايج: معاينة زي المنصة الحقيقية (Instagram/X/LinkedIn/Facebook/TikTok)، ستوري كإطارات، كاروسيل كشرائح، ريلز كمشاهد؛
   تعديل مباشر جوه الكارت مع عدّاد حد المنصة، "نوّع" (أقصر/أطول/أقوى في البيع/عاطفية)، حفظ، وسجل آخر الكتابات على الجهاز */
(function () {
  var C = window.ST, f = document.getElementById('st-f'); if (!C || !f) return; var L = C.L;
  var res = document.getElementById('st-res'), empty = document.getElementById('st-empty'), go = document.getElementById('st-go'), items = [], meta = { kind: 'post', plat: 'instagram' }, view = 'prev';
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function fm(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function full(it) { return it.text + (it.tags.length ? '\n\n' + it.tags.join(' ') : ''); }
  var LIM = { twitter: 280, instagram: 2200, tiktok: 2200, linkedin: 3000, facebook: 5000 };
  var PN = { instagram: 'Instagram', facebook: 'Facebook', linkedin: 'LinkedIn', twitter: 'X', tiktok: 'TikTok' };
  var store = { get: function (k) { try { return JSON.parse(localStorage.getItem('st_' + k) || '[]'); } catch (e) { return []; } }, set: function (k, v) { try { localStorage.setItem('st_' + k, JSON.stringify(v.slice(0, 30))); } catch (e) {} } };

  f.addEventListener('click', function (e) {
    var b = e.target.closest('[data-n]'); if (b) { var n = document.getElementById('st-n'); n.value = Math.max(1, Math.min(6, +n.value + +b.dataset.n)); return; }
    var t = e.target.closest('[data-topic]'); if (t) { var tp = document.getElementById('st-topic'); tp.value = t.dataset.topic; f.querySelectorAll('[data-topic]').forEach(function (x) { x.classList.toggle('!border-brand-500', x === t); x.classList.toggle('!bg-brand-50', x === t); }); }
  });

  /* أجزاء المحتوى المنظّم */
  function parts(it, re) { return it.text.split('\n').map(function (l) { return l.trim(); }).filter(Boolean).map(function (l) { return l.replace(re, '').trim(); }); }
  function storyHtml(it) { var fr = parts(it, /^\d+\)\s*/); return '<div class="st-frames">' + fr.slice(0, 5).map(function (l, i) { return '<div class="st-frame"><small>' + esc(fm(L.frame, i + 1)) + '</small><p dir="auto">' + esc(l) + '</p><i style="width:' + Math.round((i + 1) / fr.length * 100) + '%"></i></div>'; }).join('') + '</div>'; }
  function carouselHtml(it) { var sl = parts(it, /^(slide|شريحة)\s*\d+\s*[:：-]\s*/i); return '<div class="st-slides">' + sl.slice(0, 10).map(function (l, i) { return '<div class="st-slide' + (i === sl.length - 1 ? ' cta' : '') + '"><span>' + (i + 1) + '/' + sl.length + '</span><p dir="auto">' + esc(l) + '</p></div>'; }).join('') + '</div>'; }
  function reelHtml(it) { return '<div class="st-scenes">' + it.text.split('\n').filter(function (l) { return l.trim(); }).map(function (l) { var m = /^\s*\[([^\]]+)\]\s*(.*)$/.exec(l); return '<div class="st-scene">' + (m ? '<b dir="ltr">' + esc(m[1]) + '</b><p dir="auto">' + esc(m[2]) + '</p>' : '<p dir="auto">' + esc(l) + '</p>') + '</div>'; }).join('') + '</div>'; }

  /* معاينة المنصة */
  function av() { return '<span class="st-av">' + esc((C.ws || 'A').charAt(0)) + '</span>'; }
  function body(it) { return '<div class="st-body" contenteditable="true" spellcheck="false" dir="auto" data-body title="' + esc(L.editHint) + '">' + esc(it.text) + '</div>'; }
  function tags(it) { return it.tags.length ? '<div class="st-tags">' + it.tags.map(function (h, k) { return '<span dir="auto">' + esc(h) + '<button type="button" data-rmtag="' + k + '">×</button></span>'; }).join('') + '</div>' : ''; }
  function visual(it) { return '<div class="st-vis">' + ic('image', 'w-6 h-6') + '<span dir="auto">' + esc(it.visual || L.visual) + '</span></div>'; }
  function preview(it) { var k = meta.kind, p = meta.plat;
    if (k === 'story') return storyHtml(it); if (k === 'carousel') return carouselHtml(it); if (k === 'reel') return reelHtml(it);
    if (k === 'ideas') return body(it);
    if (p === 'twitter') return '<div class="st-pv st-x"><div class="st-ph">' + av() + '<b>' + esc(C.ws) + '</b><small>@' + esc((C.ws || '').replace(/\s+/g, '').toLowerCase()) + ' · ' + esc(L.now) + '</small></div>' + body(it) + tags(it) + '</div>';
    if (p === 'linkedin') return '<div class="st-pv st-li"><div class="st-ph">' + av() + '<div><b>' + esc(C.ws) + '</b><small>' + esc(L.now) + ' · 🌐</small></div></div>' + body(it) + tags(it) + visual(it) + '<div class="st-pf">👍 ❤️ 💡 <span>128</span></div></div>';
    if (p === 'facebook') return '<div class="st-pv st-fb"><div class="st-ph">' + av() + '<div><b>' + esc(C.ws) + '</b><small>' + esc(L.now) + ' · 🌍</small></div></div>' + body(it) + tags(it) + visual(it) + '<div class="st-pf">👍❤️ <span>86</span></div></div>';
    return '<div class="st-pv st-ig"><div class="st-ph">' + av() + '<b>' + esc((C.ws || '').replace(/\s+/g, '').toLowerCase()) + '</b></div>' + visual(it) + '<div class="st-pf">♡ 💬 ↗<span>1,204 ' + esc(L.likes) + '</span></div><div class="st-cap"><b>' + esc((C.ws || '').replace(/\s+/g, '').toLowerCase()) + '</b> ' + body(it) + '</div>' + tags(it) + '</div>'; }
  function count(it) { var n = Array.from(full(it)).length, lim = LIM[meta.plat] || 0, pc = lim ? Math.min(100, n / lim * 100) : 0, over = lim && n > lim;
    return '<span class="st-cnt' + (over ? ' over' : '') + '" title="' + esc(over ? fm(L.over, PN[meta.plat], lim) : '') + '"><i style="--p:' + pc + '%"></i>' + n + (lim ? ' / ' + lim : '') + '</span>'; }
  function card(it, i) { var fav = store.get('fav').some(function (x) { return x.text === it.text; });
    return '<article class="st-card cp-pop" data-i="' + i + '"><header><span class="st-n">' + (i + 1) + '</span><b dir="auto">' + esc(it.title) + '</b><span class="st-pl">' + esc(PN[meta.plat] || '') + '</span>' +
      '<button type="button" class="cmp-ib' + (fav ? ' on' : '') + '" data-a="fav" title="' + esc(L.fav) + '">' + ic('star') + '</button><button type="button" class="cmp-ib" data-a="copy" title="' + esc(L.copy) + '">' + ic('copy') + '</button></header>' +
      '<div class="st-in">' + (view === 'prev' ? preview(it) : body(it) + tags(it) + (it.visual ? '<div class="st-vis sm">' + ic('image') + '<span dir="auto"><b>' + esc(L.visual) + ':</b> ' + esc(it.visual) + '</span></div>' : '')) + '</div>' +
      '<footer>' + count(it) + '<div class="st-var"><button type="button" class="btn-ghost !py-1.5 !text-xs" data-a="var">' + ic('refresh') + esc(L.variant) + ' ▾</button><div class="st-varm hidden">' + ['short', 'long', 'sell', 'emo'].map(function (h) { return '<button type="button" data-how="' + h + '">' + esc(L[h]) + '</button>'; }).join('') + '</div></div><span class="ms-auto"></span>' +
      '<a class="btn-ghost !py-1.5 !text-xs" href="' + esc(C.designer + '&title=' + encodeURIComponent(it.title) + '&text=' + encodeURIComponent(it.text.split('\n')[0].slice(0, 120))) + '">' + ic('palette') + esc(L.design) + '</a>' +
      '<a class="btn-primary !py-1.5 !text-xs" href="' + esc(C.compose + '&dup_text=' + encodeURIComponent(full(it))) + '">' + ic('megaphone') + esc(L.post) + '</a></footer></article>'; }
  function bar() { return '<div class="st-bar"><div class="st-seg">' + [['prev', L.vPrev, 'eye'], ['text', L.vText, 'file']].map(function (x) { return '<button type="button" data-view="' + x[0] + '"' + (view === x[0] ? ' class="on"' : '') + '>' + ic(x[2]) + esc(x[1]) + '</button>'; }).join('') + '</div><span class="ms-auto"></span>' +
    '<button type="button" class="btn-ghost !py-1.5 !text-xs" data-hist>' + ic('clock') + esc(L.hist) + '</button></div>'; }
  function draw() { res.innerHTML = items.length ? bar() + '<div class="st-grid">' + items.map(card).join('') + '</div>' : ''; empty.classList.toggle('hidden', items.length > 0); }
  function redraw(i) { var c = res.querySelector('[data-i="' + i + '"]'); if (!c) return draw(); var n = document.createElement('div'); n.innerHTML = card(items[i], i); c.replaceWith(n.firstChild); }

  /* السجل والمحفوظ (على الجهاز) */
  function remember() { var h = store.get('hist'); h.unshift({ at: new Date().toLocaleString(), meta: meta, items: items }); store.set('hist', h); }
  function histPanel() { var h = store.get('hist'), fav = store.get('fav');
    var box = document.createElement('div'); box.className = 'st-hp'; box.innerHTML = '<div class="st-hpb" data-hx></div><aside><header><b>' + esc(L.hist) + '</b><button type="button" class="cmp-ib" data-hx>' + ic('x') + '</button></header>' +
      (fav.length ? '<h4>⭐ ' + esc(L.saved) + '</h4>' + fav.map(function (x, k) { return '<div class="st-hi"><p dir="auto">' + esc(x.text.slice(0, 140)) + '</p><button type="button" data-rf="' + k + '">' + esc(L.restore) + '</button></div>'; }).join('') : '') +
      '<h4>' + esc(L.hist) + '</h4>' + (h.length ? h.map(function (x, k) { return '<div class="st-hi"><small>' + esc(x.at) + ' · ' + esc(PN[x.meta.plat] || '') + ' · ' + x.items.length + '</small><p dir="auto">' + esc((x.items[0] || {}).text ? x.items[0].text.slice(0, 120) : '') + '</p><button type="button" data-rh="' + k + '">' + esc(L.restore) + '</button></div>'; }).join('') + '<button type="button" class="link text-xs" data-hclear>' + esc(L.clear) + '</button>' : '<p class="text-sm text-ink-400">' + esc(L.noHist) + '</p>') + '</aside>';
    document.body.appendChild(box); requestAnimationFrame(function () { box.classList.add('open'); });
    box.addEventListener('click', function (e) { var b;
      if (e.target.closest('[data-hx]')) { box.remove(); return; }
      if ((b = e.target.closest('[data-rh]'))) { var x = h[+b.dataset.rh]; meta = x.meta; items = x.items; draw(); box.remove(); return; }
      if ((b = e.target.closest('[data-rf]'))) { var y = fav[+b.dataset.rf]; meta = y.meta || meta; items = [y]; draw(); box.remove(); return; }
      if (e.target.closest('[data-hclear]')) { store.set('hist', []); box.remove(); } }); }

  res.addEventListener('click', function (e) { var b = e.target.closest('[data-view]'); if (b) { view = b.dataset.view; draw(); return; }
    if (e.target.closest('[data-hist]')) return histPanel();
    var c = e.target.closest('[data-i]'); if (!c) return; var i = +c.dataset.i, it = items[i];
    if ((b = e.target.closest('[data-rmtag]'))) { it.tags.splice(+b.dataset.rmtag, 1); redraw(i); return; }
    if (!(b = e.target.closest('[data-a],[data-how]'))) return;
    if (b.dataset.a === 'copy') { try { navigator.clipboard.writeText(full(it)); toast(L.copied); } catch (x) {} return; }
    if (b.dataset.a === 'fav') { var fv = store.get('fav'), at = fv.findIndex(function (x) { return x.text === it.text; }); if (at >= 0) fv.splice(at, 1); else { fv.unshift(Object.assign({ meta: meta }, it)); toast(L.faved); } store.set('fav', fv); redraw(i); return; }
    if (b.dataset.a === 'var') { c.querySelector('.st-varm').classList.toggle('hidden'); return; }
    if (b.dataset.how) { c.classList.add('st-busy'); c.querySelector('.st-varm').classList.add('hidden'); var d = new FormData(f); d.append('_csrf', C.csrf); d.set('base', it.text); d.set('how', b.dataset.how); d.set('kind', meta.kind); d.set('plat', meta.plat);
      fetch(C.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { c.classList.remove('st-busy'); if (!j.ok || !j.items[0]) return toast(j.error || L.err, 'error'); items[i] = j.items[0]; redraw(i); remember(); })
        .catch(function () { c.classList.remove('st-busy'); toast(L.err, 'error'); }); } });
  // تعديل مباشر + العداد بيتحدّث وانت بتكتب
  res.addEventListener('input', function (e) { var bd = e.target.closest('[data-body]'); if (!bd) return; var c = bd.closest('[data-i]'), it = items[+c.dataset.i]; it.text = bd.innerText.replace(/\n$/, ''); var cn = c.querySelector('.st-cnt'); if (cn) cn.outerHTML = count(it);
    c.querySelectorAll('footer a').forEach(function (a) { a.href = a.classList.contains('btn-primary') ? C.compose + '&dup_text=' + encodeURIComponent(full(it)) : C.designer + '&title=' + encodeURIComponent(it.title) + '&text=' + encodeURIComponent(it.text.split('\n')[0].slice(0, 120)); }); });
  res.addEventListener('paste', function (e) { if (!e.target.closest('[data-body]')) return; e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData || window.clipboardData).getData('text')); });
  document.addEventListener('click', function (e) { if (!e.target.closest('.st-var')) res.querySelectorAll('.st-varm').forEach(function (m) { m.classList.add('hidden'); }); });

  f.addEventListener('submit', function (e) {
    e.preventDefault(); var sp = go.querySelector('span'), old = sp.textContent; go.disabled = true; sp.textContent = L.busy;
    res.innerHTML = '<div class="st-grid">' + Array(+document.getElementById('st-n').value).fill('<div class="card p-4 space-y-3"><div class="h-5 w-1/2 mk-sk"></div><div class="h-40 mk-sk"></div><div class="h-8 mk-sk"></div></div>').join('') + '</div>'; empty.classList.add('hidden');
    var d = new FormData(f); d.append('_csrf', C.csrf);
    fetch(C.url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      go.disabled = false; sp.textContent = old;
      if (!j.ok) { res.innerHTML = ''; empty.classList.remove('hidden'); return toast(j.error || L.err, 'error'); }
      items = j.items; meta = { kind: j.kind, plat: j.plat }; draw(); remember(); sp.textContent = L.more;
    }).catch(function () { go.disabled = false; sp.textContent = old; res.innerHTML = ''; empty.classList.remove('hidden'); toast(L.err, 'error'); });
  });
  // زرار السجل ظاهر حتى قبل أول كتابة
  if (store.get('hist').length || store.get('fav').length) { var hb = document.createElement('button'); hb.type = 'button'; hb.className = 'btn-ghost !py-1.5 !text-xs mt-3'; hb.innerHTML = ic('clock') + esc(L.hist); hb.addEventListener('click', histPanel); empty.appendChild(hb); }
})();
