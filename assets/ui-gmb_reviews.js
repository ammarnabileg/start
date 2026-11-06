/* تقييمات العملاء: فلترة وبحث فوري، نسبة الرد، ردود جاهزة بالتقييم، تحليل الذكاء الاصطناعي، مسودات ردود جماعية (مفيش إرسال تلقائي)، تنقل سريع */
(function () {
  var X = window.RVX; if (!X || document.body.dataset.rvUx) return; document.body.dataset.rvUx = '1'; var S = X.s;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var fmt = function (s) { var a = [].slice.call(arguments, 1); return String(s || '').replace(/%s/g, function () { return a.length ? a.shift() : ''; }); };
  var toast = function (m, k) { (window.aiToast || function () {})(m, k); };
  var cards = $$('.rv-card'), tools = $('#rv-tools'); if (!tools) return;
  var state = { f: '', star: 0, q: '' }, focusI = -1;
  var store = { get: function (k, d) { try { var v = localStorage.getItem('rv_' + X.ws + '_' + k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } }, set: function (k, v) { try { localStorage.setItem('rv_' + X.ws + '_' + k, JSON.stringify(v)); } catch (e) {} } };

  // ---- الأرقام
  var replied = cards.filter(function (c) { return c.dataset.replied === '1'; }).length, un = cards.filter(function (c) { return c.dataset.replied === '0'; });
  var oldest = un.map(function (c) { return Date.parse(c.dataset.at) || 0; }).filter(Boolean).sort(function (a, b) { return a - b; })[0];
  var rate = cards.length ? Math.round(replied / cards.length * 100) : 0;
  tools.innerHTML = '<div class="grid sm:grid-cols-3 gap-3 mb-4">'
    + '<div class="card p-4"><div class="text-xs text-ink-500">' + esc(S.replyRate) + '</div><div class="flex items-end gap-2 mt-1"><b class="text-2xl text-ink-900 tabular-nums">' + rate + '%</b><span class="flex-1 h-2 mb-2 rounded-full bg-ink-100 overflow-hidden"><span class="block h-full rounded-full ' + (rate >= 80 ? 'bg-lime-500' : rate >= 50 ? 'bg-amber-500' : 'bg-red-500') + '" style="width:' + rate + '%"></span></span></div></div>'
    + '<button type="button" data-f="un" class="card p-4 text-start hover:border-amber-300"><div class="text-xs text-ink-500">' + esc(S.needReply) + '</div><b class="block text-2xl tabular-nums mt-1 ' + (un.length ? 'text-amber-700' : 'text-lime-700') + '">' + un.length + '</b>' + (oldest ? '<div class="text-[11px] text-ink-500 mt-1">' + esc(fmt(S.oldest, new Date(oldest).toLocaleDateString())) + '</div>' : '') + '</button>'
    + '<div class="card p-4 flex flex-col gap-2 justify-center">' + (X.canAi ? '<button type="button" id="rv-themes" class="w-full rounded-xl border border-brand-200 bg-brand-50 text-brand-800 text-sm py-2 hover:bg-brand-100">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(S.themes) + '</button>'
      + (un.length ? '<button type="button" id="rv-drafts" class="w-full rounded-xl border border-ink-200 text-ink-800 text-sm py-2 hover:bg-ink-50">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(S.drafts) + '</button>' : '') : '') + '</div></div>'
    + '<div id="rv-ai" class="hidden mb-4"></div>'
    + '<div class="sticky top-0 z-10 bg-ink-50/95 backdrop-blur py-2 mb-3 flex flex-wrap items-center gap-2"><input id="rv-q" type="search" class="input !mt-0 !h-9 !py-0 w-64 text-sm" placeholder="' + esc(S.search) + ' (/)" aria-label="' + esc(S.search) + '">'
    + [['', S.all], ['un', S.unreplied], ['neg', S.negative], ['pos', S.positive], ['ph', S.photos]].map(function (c) { return '<button type="button" data-f="' + c[0] + '" class="rv-chip rounded-full text-xs px-3 py-1.5 border"></button>'; }).join('')
    + '<span id="rv-n" class="text-xs text-ink-500 ms-auto"></span><span class="text-[11px] text-ink-400 hidden md:inline">' + esc(S.keys) + '</span></div>';
  var labels = { '': S.all, un: S.unreplied, neg: S.negative, pos: S.positive, ph: S.photos };
  var count = function (f) { return cards.filter(function (c) { return match(c, f, 0, ''); }).length; };
  function match(c, f, star, q) { var r = +c.dataset.rating;
    if (f === 'un' && c.dataset.replied !== '0') return false; if (f === 'neg' && !(r && r <= 2)) return false; if (f === 'pos' && r < 4) return false; if (f === 'ph' && c.dataset.photos !== '1') return false;
    if (star && r !== star) return false; if (q && c.dataset.q.indexOf(q) < 0) return false; return true; }
  function apply() {
    var n = 0; cards.forEach(function (c) { var ok = match(c, state.f, state.star, state.q); c.style.display = ok ? '' : 'none'; if (ok) n++; });
    $$('.rv-chip', tools).forEach(function (b) { var on = b.dataset.f === state.f; b.className = 'rv-chip rounded-full text-xs px-3 py-1.5 border ' + (on ? 'bg-brand-600 text-white border-brand-600' : 'border-ink-200 text-ink-700 bg-white hover:bg-ink-50'); b.textContent = labels[b.dataset.f] + ' ' + count(b.dataset.f); });
    $$('.rv-bar').forEach(function (b) { b.classList.toggle('bg-brand-50', +b.dataset.star === state.star); b.classList.toggle('ring-1', +b.dataset.star === state.star); b.classList.toggle('ring-brand-300', +b.dataset.star === state.star); });
    $('#rv-n').textContent = n + ' / ' + cards.length;
    var e = $('#rv-empty'); if (!e && cards.length) { e = document.createElement('div'); e.id = 'rv-empty'; e.className = 'card p-8 text-center text-sm text-ink-500'; e.textContent = S.empty; $('#rv-list').appendChild(e); } if (e) e.hidden = n > 0;
  }
  document.addEventListener('click', function (e) {
    var f = e.target.closest('[data-f]'), bar = e.target.closest('.rv-bar');
    if (f && tools.contains(f)) { state.f = state.f === f.dataset.f && f.dataset.f ? '' : f.dataset.f; apply(); }
    if (bar) { e.preventDefault(); var n = +bar.dataset.star; state.star = state.star === n ? 0 : n; apply(); }
  });
  $('#rv-q').addEventListener('input', function () { state.q = this.value.trim().toLowerCase(); apply(); });
  apply();

  // ---- ردود جاهزة + زرار "التالي من غير رد" جوه كل فورم
  var tpls = store.get('tpl', null); if (!tpls) { tpls = Object.keys(S.defaults).map(function (k) { return { r: +k, t: S.defaults[k] }; }); store.set('tpl', tpls); }
  var first = function (n) { return String(n || '').split(' ')[0]; };
  cards.forEach(function (c) {
    var form = document.getElementById(c.dataset.form); if (!form) return; var ta = $('textarea', form);
    var bar = document.createElement('div'); bar.className = 'rv-tplbar flex flex-wrap items-center gap-1.5 mt-2'; form.appendChild(bar);
    var drawT = function () { var r = +c.dataset.rating, rel = tpls.map(function (t, i) { return [t, i]; }).sort(function (a, b) { return Math.abs(a[0].r - r) - Math.abs(b[0].r - r); }).slice(0, 4);
      bar.innerHTML = '<span class="text-[11px] text-ink-500">' + esc(S.tpl) + ':</span>' + (rel.length ? rel.map(function (x) { return '<button type="button" data-tpl="' + x[1] + '" class="max-w-[14rem] truncate rounded-full border border-ink-200 bg-white text-[11px] px-2.5 py-1 hover:bg-ink-50" title="' + esc(x[0].t) + '">' + '★'.repeat(Math.max(1, x[0].r)) + ' ' + esc(x[0].t.replace(/\{name\}/g, S.nameTok).slice(0, 40)) + '</button>'; }).join('') : '<span class="text-[11px] text-ink-400">' + esc(S.tplNone) + '</span>')
        + '<button type="button" class="rv-save text-[11px] link ms-auto">+ ' + esc(S.tplSave) + '</button>' + (c.dataset.replied === '0' ? '<button type="button" class="rv-next text-[11px] link">' + esc(S.nextUn) + '</button>' : ''); };
    drawT();
    bar.addEventListener('click', function (e) {
      var t = e.target.closest('[data-tpl]'); if (t) { ta.value = tpls[+t.dataset.tpl].t.replace(/\{name\}/g, first(c.dataset.name)); ta.focus(); ta.dispatchEvent(new Event('input', { bubbles: true })); }
      if (e.target.closest('.rv-save') && ta.value.trim()) { tpls.unshift({ r: +c.dataset.rating || 5, t: ta.value.trim().replace(new RegExp(first(c.dataset.name).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), '{name}') }); tpls = tpls.slice(0, 20); store.set('tpl', tpls); cards.forEach(function (cc) { var b2 = $('.rv-tplbar', document.getElementById(cc.dataset.form) || document.body); }); drawT(); toast(S.tplSaved, 'ok'); }
      if (e.target.closest('.rv-next')) jumpNext(c);
    });
  });
  function jumpNext(from) { var list = cards.filter(function (c) { return c.dataset.replied === '0' && c.style.display !== 'none'; }), i = list.indexOf(from), nx = list[i + 1] || list[0]; if (!nx || nx === from) return; focusCard(cards.indexOf(nx)); var f = document.getElementById(nx.dataset.form); if (f) { f.classList.remove('hidden'); $('textarea', f).focus(); } }
  function focusCard(i) { var vis = cards.filter(function (c) { return c.style.display !== 'none'; }); if (!vis.length) return; focusI = Math.max(0, Math.min(vis.length - 1, i)); cards.forEach(function (c) { c.classList.remove('ring-2', 'ring-brand-400'); }); var c = vis[focusI]; c.classList.add('ring-2', 'ring-brand-400'); c.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey) return;
    var vis = cards.filter(function (c) { return c.style.display !== 'none'; });
    if (e.key === '/') { e.preventDefault(); $('#rv-q').focus(); }
    else if (e.key === 'j') focusCard(focusI + 1); else if (e.key === 'k') focusCard(focusI - 1);
    else if (e.key === 'r' && vis[focusI]) { e.preventDefault(); var f = document.getElementById(vis[focusI].dataset.form); if (f) { f.classList.remove('hidden'); $('textarea', f).focus(); } }
    else if (e.key === 'n') { var nx = vis.filter(function (c, i) { return i > focusI && c.dataset.replied === '0'; })[0] || vis.filter(function (c) { return c.dataset.replied === '0'; })[0]; if (nx) { focusCard(vis.indexOf(nx)); } }
  });

  // ---- الذكاء الاصطناعي
  function post(action, reviews) { var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('action', action); fd.append('reviews', JSON.stringify(reviews)); return fetch(X.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  var pack = function (list) { return list.map(function (c) { return { id: c.dataset.id, rating: +c.dataset.rating, name: c.dataset.name, text: c.dataset.text }; }); };
  var aiBox = $('#rv-ai');
  var th = $('#rv-themes'); if (th) th.onclick = function () {
    th.disabled = true; aiBox.classList.remove('hidden'); aiBox.innerHTML = '<div class="card p-5 text-sm text-ink-500">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(S.working) + '</div>';
    post('ai_themes', pack(cards.filter(function (c) { return c.dataset.text; }))).then(function (j) {
      th.disabled = false; if (!j.ok) { aiBox.innerHTML = '<div class="card p-4 text-sm text-red-700">' + esc(S.aiErr + ': ' + (j.error || '')) + '</div>'; return; }
      var r = j.r || {}, list = function (arr, tone, withFix) { return '<ul class="space-y-2">' + (arr || []).slice(0, 5).map(function (x) { return '<li class="rounded-lg ' + tone + ' p-2.5"><div class="flex justify-between gap-2"><b>' + esc(x.theme) + '</b><span class="text-[11px] opacity-70">' + esc(fmt(S.times, x.count || 0)) + '</span></div>' + (x.quote ? '<p class="text-xs opacity-80 mt-1">«' + esc(x.quote) + '»</p>' : '') + (withFix && x.fix ? '<p class="text-xs mt-1">↳ ' + esc(x.fix) + '</p>' : '') + '</li>'; }).join('') + '</ul>'; };
      aiBox.innerHTML = '<div class="card p-5 space-y-4 text-sm"><p class="font-semibold text-ink-900">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(r.verdict || '') + '</p><div class="grid md:grid-cols-2 gap-4"><div><div class="text-xs font-semibold text-lime-700 mb-2">👍 ' + esc(S.praise) + '</div>' + list(r.praise, 'bg-lime-50 text-lime-900') + '</div><div><div class="text-xs font-semibold text-red-700 mb-2">👎 ' + esc(S.complaints) + '</div>' + list(r.complaints, 'bg-red-50 text-red-900', true) + '</div></div>'
        + ((r.actions || []).length ? '<div><div class="text-xs font-semibold text-ink-700 mb-1">' + esc(S.actions) + '</div><ol class="list-decimal ps-5 space-y-0.5 text-ink-800">' + r.actions.slice(0, 5).map(function (a) { return '<li>' + esc(a) + '</li>'; }).join('') + '</ol></div>' : '') + '</div>';
    }).catch(function () { th.disabled = false; aiBox.innerHTML = '<div class="card p-4 text-sm text-red-700">' + esc(S.aiErr) + '</div>'; });
  };
  var dr = $('#rv-drafts'); if (dr) dr.onclick = function () {
    var todo = cards.filter(function (c) { return c.dataset.replied === '0'; }).slice(0, 12); dr.disabled = true; dr.textContent = '✦ ' + S.writing;
    post('ai_drafts', pack(todo)).then(function (j) {
      dr.disabled = false; dr.textContent = '✦ ' + S.drafts; if (!j.ok) return toast(j.error || S.aiErr, 'err');
      var n = 0; ((j.r || {}).drafts || []).forEach(function (d) { var c = cards.filter(function (x) { return x.dataset.id === String(d.id); })[0]; if (!c || !d.reply) return; var f = document.getElementById(c.dataset.form); if (!f) return; f.classList.remove('hidden'); var ta = $('textarea', f); if (!ta.value.trim()) { ta.value = d.reply; n++; ta.classList.add('ring-2', 'ring-brand-200'); if (!$('.rv-dtag', f)) f.insertAdjacentHTML('afterbegin', '<span class="rv-dtag inline-block mb-1 text-[10px] rounded bg-brand-100 text-brand-800 px-1.5">' + esc(S.draft) + '</span>'); } });
      toast(fmt(S.draftsDone, n), 'ok'); state.f = 'un'; apply();
    }).catch(function () { dr.disabled = false; dr.textContent = '✦ ' + S.drafts; toast(S.aiErr, 'err'); });
  };
})();
