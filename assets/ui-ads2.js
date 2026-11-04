/* الحملات (الدفعة الأولى): محتاج انتباهك، نصيب الصرف، مقارنة بالفترة السابقة، اختيار جماعي وإيقاف/تشغيل،
   مقارنة حملات، نظرة سريعة جانبية، تحليل بالذكاء الاصطناعي، تعديل الميزانية من الجدول مع تحذير المبالغ الكبيرة */
(function () {
  var C = window.ADX2; if (!C) return; var S = C.s;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var fmt = function (s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.length ? a.shift() : ''; }); };
  var rows = $$('.ads-row'); if (!rows.length) return;
  var D = rows.map(function (r) { return { r: r, id: r.dataset.id, pf: r.dataset.pf, st: r.dataset.st, cur: r.dataset.cur, name: r.dataset.name, accn: r.dataset.accn, v: JSON.parse(r.dataset.v), p: JSON.parse(r.dataset.p || 'null'), b: JSON.parse(r.dataset.b || 'null'), href: r.dataset.href }; });
  var money = function (n, cur) { try { return new Intl.NumberFormat('en-US', { style: cur ? 'currency' : 'decimal', currency: cur || undefined, maximumFractionDigits: 2 }).format(n); } catch (e) { return (+n).toFixed(2) + ' ' + (cur || ''); } };
  var num = function (n) { return (+n).toLocaleString('en-US', { maximumFractionDigits: 2 }); };
  function dialog(title, html, wide) {
    var d = document.createElement('dialog'); d.className = (wide ? 'w-[min(760px,96vw)]' : 'w-[min(440px,94vw)]') + ' p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm">' + esc(title) + '</b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100" aria-label="✕">✕</button></div><div class="p-5">' + html + '</div>';
    d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); }); d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
    document.body.appendChild(d); d.showModal(); return d;
  }
  function postForm(url, fields) {
    var f = document.createElement('form'); f.method = 'post'; f.action = url; f.hidden = true;
    fields._csrf = C.csrf;
    Object.keys(fields).forEach(function (k) { [].concat(fields[k]).forEach(function (v) { var i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i); }); });
    document.body.appendChild(f); f.submit();
  }

  // ---- متوسطات لكل عملة
  var avg = {};
  D.forEach(function (d) { var a = avg[d.cur] = avg[d.cur] || { s: 0, i: 0, c: 0, n: 0 }; a.s += d.v[0]; a.i += d.v[1]; a.c += d.v[2]; if (d.v[0] > 0) a.n++; });
  Object.keys(avg).forEach(function (k) { var a = avg[k]; a.ctr = a.i ? a.c / a.i * 100 : 0; a.cpc = a.c ? a.s / a.c : 0; });

  // ---- (3) نصيب الصرف + (10) مقارنة بالفترة اللي قبلها
  D.forEach(function (d) {
    var tds = d.r.children, a = avg[d.cur], pct = a.s ? Math.round(d.v[0] / a.s * 100) : 0, spendTd = tds[4];
    if (spendTd && d.v[0] > 0 && a.n > 1) {
      spendTd.insertAdjacentHTML('beforeend', '<div class="mt-1.5 h-1 rounded-full bg-ink-100 overflow-hidden ms-auto w-20" title="' + esc(fmt(S.share, pct + '%', d.cur)) + '"><div class="h-full bg-brand-500 rounded-full ads-shr" style="width:0" data-w="' + pct + '"></div></div>');
    }
    if (d.p) [0, 1, 2, 5].forEach(function (i, k) {
      var td = tds[[4, 5, 6, 9][k]], was = d.p[i], now = d.v[i]; if (!td || !was) return;
      var ch = Math.round((now - was) / was * 100); if (!isFinite(ch) || ch === 0) return;
      var good = i === 0 ? null : ch > 0;
      td.insertAdjacentHTML('beforeend', '<div class="text-[11px] tabular-nums ' + (good === null ? 'text-ink-500' : good ? 'text-lime-700' : 'text-red-600') + '" title="' + esc(S.vsPrev) + '" dir="ltr">' + (ch > 0 ? '▲ ' : '▼ ') + Math.abs(ch) + '%</div>');
    });
  });
  requestAnimationFrame(function () { $$('.ads-shr').forEach(function (b) { b.style.transition = 'width .6s'; b.style.width = b.dataset.w + '%'; }); });

  // ---- (1) محتاج انتباهك
  var issues = [];
  D.forEach(function (d) {
    var a = avg[d.cur];
    if (d.st === 'rejected') issues.push([d, 'red', S.rejected]);
    else if (d.st === 'pending_review') issues.push([d, 'amber', S.review]);
    if (d.v[1] >= 1000 && a.ctr && d.v[3] < a.ctr * 0.6) issues.push([d, 'amber', fmt(S.lowCtr, num(d.v[3]) + '%', num(a.ctr) + '%')]);
    if (d.v[2] >= 20 && a.cpc && d.v[4] > a.cpc * 1.5) issues.push([d, 'amber', fmt(S.highCpc, money(d.v[4], d.cur), Math.round((d.v[4] / a.cpc - 1) * 100) + '%')]);
    if (d.v[0] > 0 && !d.v[5] && d.st !== 'rejected') issues.push([d, 'red', fmt(S.noRes, money(d.v[0], d.cur))]);
  });
  var at = $('#ads-attn');
  if (at) {
    at.classList.remove('hidden');
    at.innerHTML = issues.length
      ? '<div class="flex items-center gap-2 mb-3"><span class="w-2 h-2 rounded-full bg-amber-500"></span><b class="text-sm text-ink-900">' + esc(S.attn) + '</b><span class="text-xs text-ink-500">(' + issues.length + ')</span></div><div class="grid gap-2 md:grid-cols-2">'
        + issues.map(function (x, i) { return '<button type="button" data-i="' + i + '" class="ads-iss text-start rounded-xl border px-3 py-2 text-sm hover:shadow-sm transition ' + (x[1] === 'red' ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-900') + '"><b class="block truncate">' + esc(x[0].name) + '</b><span class="text-xs">' + esc(x[2]) + '</span></button>'; }).join('') + '</div>'
      : '<div class="flex items-center gap-2 text-sm text-lime-800"><span class="w-2 h-2 rounded-full bg-lime-500"></span>' + esc(S.attnNone) + '</div>';
    at.addEventListener('click', function (e) { var b = e.target.closest('.ads-iss'); if (b) quick(issues[+b.dataset.i][0]); });
  }

  // ---- (16) اختيار جماعي + (4) مقارنة
  var bulk = $('#ads-bulk'), all = $('#ads-all');
  function sel() { return D.filter(function (d) { var c = $('.ads-sel', d.r); return c && c.checked; }); }
  function drawBulk() {
    var s = sel(); if (all) { all.checked = s.length === D.length; all.indeterminate = s.length > 0 && s.length < D.length; }
    D.forEach(function (d) { d.r.classList.toggle('bg-brand-50', $('.ads-sel', d.r).checked); });
    if (!s.length) { bulk.classList.add('hidden'); return; }
    bulk.classList.remove('hidden');
    bulk.innerHTML = '<b class="me-auto">' + esc(fmt(S.selected, s.length)) + '</b>'
      + '<button type="button" data-b="pause" class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5">' + esc(S.pause) + '</button>'
      + (C.canSpend ? '<button type="button" data-b="act" class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5">' + esc(S.activate) + '</button>' : '')
      + '<button type="button" data-b="cmp" class="rounded-lg bg-white text-brand-700 font-semibold px-3 py-1.5">' + esc(S.compare) + '</button>'
      + '<button type="button" data-b="clr" class="rounded-lg px-2 py-1.5 hover:bg-white/15">' + esc(S.clear) + '</button>';
  }
  document.addEventListener('change', function (e) {
    if (e.target === all) { D.forEach(function (d) { if (d.r.style.display !== 'none') $('.ads-sel', d.r).checked = all.checked; }); drawBulk(); }
    else if (e.target.classList && e.target.classList.contains('ads-sel')) drawBulk();
  });
  bulk.addEventListener('click', function (e) {
    var b = e.target.closest('[data-b]'); if (!b) return; var s = sel(), ids = s.map(function (d) { return d.id + '|' + d.pf; });
    if (b.dataset.b === 'clr') { $$('.ads-sel').forEach(function (c) { c.checked = false; }); drawBulk(); }
    else if (b.dataset.b === 'pause') { if (confirm(fmt(S.pauseQ, s.length))) postForm(C.bulkUrl, { action: 'bulk_status', to: 'paused', 'ids[]': ids }); }
    else if (b.dataset.b === 'act') {
      var d = dialog(S.actTitle, '<ul class="text-sm text-ink-700 mb-3 space-y-1">' + s.map(function (x) { return '<li>• ' + esc(x.name) + (x.b ? ' <span class="text-ink-500">(' + esc(money(x.b[0], x.cur)) + ')</span>' : '') + '</li>'; }).join('') + '</ul>'
        + '<label class="flex items-start gap-2 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-xl p-3"><input type="checkbox" class="cf mt-0.5"><span>' + esc(fmt(S.actConfirm, s.length)) + '</span></label>'
        + '<div class="flex justify-end gap-2 mt-4"><button type="button" class="btn-ghost cn">' + esc(S.cancel) + '</button><button type="button" class="btn-primary go" disabled>' + esc(S.run) + '</button></div>');
      var cf = $('.cf', d), go = $('.go', d); cf.onchange = function () { go.disabled = !cf.checked; }; $('.cn', d).onclick = function () { d.close(); };
      go.onclick = function () { postForm(C.bulkUrl, { action: 'bulk_status', to: 'active', confirm_spend: '1', 'ids[]': ids }); };
    } else if (b.dataset.b === 'cmp') compare(s);
  });
  function compare(s) {
    if (s.length < 2 || s.length > 4) { (window.aiToast || alert)(S.cmpMax); return; }
    var metrics = S.cols.map(function (l, i) { return [l, i]; }).concat([[S.costRes, 'cr']]);
    var val = function (d, i) { return i === 'cr' ? (d.v[5] ? d.v[0] / d.v[5] : null) : d.v[i]; };
    var lowerBetter = { 4: 1, cr: 1 }, show = function (d, i, x) { return x == null ? '—' : (i === 0 || i === 4 || i === 'cr') ? money(x, d.cur) : i === 3 ? num(x) + '%' : num(x); };
    var sameCur = s.every(function (d) { return d.cur === s[0].cur; });
    var html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-xs text-ink-500"><th class="text-start p-2">' + esc(S.metric) + '</th>' + s.map(function (d) { return '<th class="text-start p-2 font-semibold text-ink-900">' + esc(d.name) + '<div class="font-normal text-ink-500">' + esc(d.accn) + '</div></th>'; }).join('') + '</tr></thead><tbody class="divide-y divide-ink-100">'
      + metrics.map(function (m) {
        var xs = s.map(function (d) { return val(d, m[1]); }), ok = xs.filter(function (x) { return x != null && x > 0; });
        var best = (sameCur || m[1] === 1 || m[1] === 2 || m[1] === 3 || m[1] === 5) && ok.length > 1 ? (lowerBetter[m[1]] ? Math.min.apply(null, ok) : Math.max.apply(null, ok)) : null;
        return '<tr><td class="p-2 text-ink-500">' + esc(m[0]) + '</td>' + s.map(function (d, k) { var b = best !== null && xs[k] === best && m[1] !== 0; return '<td class="p-2 tabular-nums ' + (b ? 'font-bold text-lime-700' : 'text-ink-900') + '" dir="ltr" style="text-align:start">' + (b ? '★ ' : '') + esc(show(d, m[1], xs[k])) + '</td>'; }).join('') + '</tr>';
      }).join('') + '</tbody></table></div>';
    dialog(S.cmpTitle, html, true);
  }

  // ---- (12) نظرة سريعة + (38) تحليل بالذكاء الاصطناعي
  var drawer;
  function quick(d) {
    if (drawer) drawer.remove();
    drawer = document.createElement('aside');
    drawer.className = 'ads-drawer fixed inset-y-0 end-0 z-[90] w-[min(560px,100vw)] bg-white border-s border-ink-200 shadow-2xl flex flex-col';
    drawer.setAttribute('role', 'dialog'); drawer.setAttribute('aria-label', d.name);
    drawer.innerHTML = '<div class="flex items-center gap-2 px-5 h-14 border-b border-ink-200"><div class="min-w-0 flex-1"><div class="text-[11px] text-ink-500">' + esc(S.quick) + '</div><b class="block truncate text-ink-900">' + esc(d.name) + '</b></div><a class="link text-sm" href="' + esc(d.href) + '">' + esc(S.open) + '</a><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100" aria-label="✕">✕</button></div>'
      + '<div class="flex-1 overflow-y-auto p-5 space-y-4"><button type="button" class="ai w-full rounded-xl border border-brand-200 bg-brand-50 text-brand-800 font-semibold text-sm py-2.5 hover:bg-brand-100">✦ ' + esc(S.ai) + '</button><div class="aiout hidden rounded-xl border border-ink-200 p-4 text-sm leading-7 whitespace-pre-line"></div><div class="body text-sm text-ink-500">' + esc(S.loading) + '</div></div>';
    document.body.appendChild(drawer);
    var close = function () { drawer.remove(); drawer = null; document.removeEventListener('keydown', onEsc); };
    var onEsc = function (e) { if (e.key === 'Escape' && !document.querySelector('dialog[open]')) close(); };
    document.addEventListener('keydown', onEsc); $('.x', drawer).onclick = close; $('.x', drawer).focus();
    var box = $('.body', drawer), dr = drawer;
    fetch(d.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) {
      if (dr !== drawer) return;
      var doc = new DOMParser().parseFromString(h, 'text/html'), kpi = doc.querySelector('.ads-num'), sets = doc.querySelector('.acx-set');
      box.innerHTML = ''; box.className = 'body space-y-4';
      if (kpi) { var g = kpi.closest('.grid'); g.className = 'grid grid-cols-2 gap-2'; box.appendChild(g); }
      if (sets) { var wrap = sets.parentNode; $$('form, details, #acx-q, #acx-toggle, #acx-help', wrap).forEach(function (x) { x.remove(); }); box.appendChild(wrap); }
      if (!kpi && !sets) box.textContent = S.loadErr;
    }).catch(function () { box.textContent = S.loadErr; });
    $('.ai', drawer).onclick = function () {
      var b = this, out = $('.aiout', drawer), a = avg[d.cur]; b.disabled = true; out.classList.remove('hidden'); out.textContent = S.aiWait;
      var data = { name: d.name, platform: d.pf, status: d.st, currency: d.cur, budget: d.b ? d.b[0] + ' ' + d.b[1] : '', spend: d.v[0], impressions: d.v[1], clicks: d.v[2], ctr: d.v[3], cpc: d.v[4], results: d.v[5], account_avg_ctr: a.ctr.toFixed(2), account_avg_cpc: a.cpc.toFixed(2), period: C.period };
      if (d.p) { data.prev_spend = d.p[0]; data.prev_clicks = d.p[2]; data.prev_results = d.p[5]; }
      var fd = new FormData(); fd.append('_csrf', C.csrf); fd.append('action', 'ai_analyze'); fd.append('data', JSON.stringify(data));
      fetch(C.aiUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        out.textContent = j.ok ? j.text : (S.aiErr + ': ' + (j.error || '')); b.disabled = false;
      }).catch(function () { out.textContent = S.aiErr; b.disabled = false; });
    };
  }
  D.forEach(function (d) {
    d.r.addEventListener('click', function (e) { if (e.target.closest('a, button, input, form, details, label, .ads-budget')) return; quick(d); });
  });

  // ---- (17) تعديل الميزانية من الجدول + (49) تحذير المبالغ الكبيرة
  D.forEach(function (d) {
    var span = $('.ads-budget', d.r); if (!span || !d.b) return;
    span.className = 'ads-budget border-b border-dashed border-ink-400 cursor-pointer hover:text-brand-700'; span.tabIndex = 0; span.setAttribute('role', 'button'); span.title = S.budget;
    var open = function () {
      if (!C.canSpend) { (window.aiToast || alert)(S.budOwner); return; }
      if (!d.b[2]) { (window.aiToast || alert)(S.noCbo); return; }
      var dg = dialog(S.budget + ' · ' + d.name, '<label class="label">' + esc(S.amount) + ' (' + esc(d.cur) + ')</label><input type="number" step="0.01" min="1" class="input am" value="' + d.b[0] + '">'
        + '<div class="flex gap-2 mt-3">' + ['daily', 'lifetime'].map(function (t) { return '<label class="flex-1 flex items-center gap-2 rounded-xl border border-ink-200 px-3 py-2 text-sm"><input type="radio" name="bt" value="' + t + '"' + (d.b[1] === t ? ' checked' : '') + '> ' + esc(S[t]) + '</label>'; }).join('') + '</div>'
        + '<div class="flex flex-wrap gap-1.5 mt-3">' + [-20, -10, 10, 20, 50].map(function (p) { return '<button type="button" data-pct="' + p + '" class="rounded-full border border-ink-200 text-xs px-2.5 py-1 hover:bg-ink-50" dir="ltr">' + (p > 0 ? '+' : '') + p + '%</button>'; }).join('') + '</div>'
        + '<p class="warn hidden mt-3 text-sm font-semibold text-red-700 bg-red-50 border border-red-200 rounded-xl p-3"></p>'
        + '<label class="mt-3 flex items-start gap-2 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-xl p-3"><input type="checkbox" class="cf mt-0.5"><span class="cft"></span></label>'
        + '<div class="flex justify-end gap-2 mt-4"><button type="button" class="btn-ghost cn">' + esc(S.cancel) + '</button><button type="button" class="btn-primary go" disabled>' + esc(S.save) + '</button></div>');
      var am = $('.am', dg), cf = $('.cf', dg), go = $('.go', dg), warn = $('.warn', dg);
      var sync = function () {
        var v = parseFloat(am.value) || 0, x = d.b[0] ? v / d.b[0] : 0;
        $('.cft', dg).textContent = fmt(S.budConfirm, money(v, d.cur));
        warn.classList.toggle('hidden', !(x >= 3)); warn.textContent = fmt(S.budWarn, num(x));
        cf.checked = false; go.disabled = true;
      };
      am.addEventListener('input', sync); dg.addEventListener('change', function (e) { if (e.target === cf) go.disabled = !cf.checked || !(parseFloat(am.value) > 0); });
      $$('[data-pct]', dg).forEach(function (b) { b.onclick = function () { am.value = (d.b[0] * (1 + b.dataset.pct / 100)).toFixed(2); sync(); }; });
      $('.cn', dg).onclick = function () { dg.close(); }; sync(); am.focus(); am.select();
      go.onclick = function () { postForm(C.campUrl + '&id=' + encodeURIComponent(d.id) + '&pf=' + encodeURIComponent(d.pf) + '&r=' + encodeURIComponent(C.range), { action: 'budget', platform: d.pf, amount: am.value, type: $('[name=bt]:checked', dg).value, confirm_spend: '1', 'return': 'ads' }); };
    };
    span.addEventListener('click', open); span.addEventListener('keydown', function (e) { if (e.key === 'Enter') open(); });
  });

  // اختصارات إضافية
  if (window.ADX && window.ADX.keys) window.ADX.keys.splice(window.ADX.keys.length - 1, 0, ['x', S.kSel], ['Enter', S.kQuick]);
  var focusI = -1;
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey || document.querySelector('dialog[open]')) return;
    var vis = D.filter(function (d) { return d.r.style.display !== 'none'; }); if (!vis.length) return;
    if (e.key === 'j' || e.key === 'k') { focusI = Math.max(0, Math.min(vis.length - 1, focusI + (e.key === 'j' ? 1 : -1))); vis.forEach(function (d, i) { d.r.classList.toggle('ring-2', i === focusI); d.r.classList.toggle('ring-brand-400', i === focusI); }); vis[focusI].r.scrollIntoView({ block: 'nearest' }); }
    else if (e.key === 'x' && vis[focusI]) { var c = $('.ads-sel', vis[focusI].r); c.checked = !c.checked; drawBulk(); }
    else if (e.key === 'Enter' && vis[focusI] && !drawer) quick(vis[focusI]);
  });
})();
