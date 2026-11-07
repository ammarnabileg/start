/* الحملات (باقي الأفكار): درجة الصحة، أعمدة محسوبة، اختيار الأعمدة، تجميع، عرض كروت، تصدير CSV،
   ميزانية جماعية بالنسبة، إيقاف طوارئ، إجمالي بعملة واحدة */
(function () {
  var Z = window.ADS2, C = window.ADX2; if (!Z || !C) return; var S = C.s, D = Z.D, esc = Z.esc, fmt = Z.fmt, money = Z.money, num = Z.num;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var store = { get: function (k, d) { try { var v = localStorage.getItem('ads_' + k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } }, set: function (k, v) { try { localStorage.setItem('ads_' + k, JSON.stringify(v)); } catch (e) {} } };
  var table = $('#ads-table'), tb = table.tBodies[0], headRow = table.tHead.rows[0];

  // ---- (2) درجة الصحة — نسبة الضغط مقارنة بكل الحملات (مش مرتبطة بالعملة)، وتكلفة الضغطة بنفس العملة بس
  var gI = 0, gC = 0; D.forEach(function (d) { gI += d.v[1]; gC += d.v[2]; }); var gCtr = gI ? gC / gI * 100 : 0;
  D.forEach(function (d) {
    var a = Z.avg[d.cur], sc, same = D.filter(function (x) { return x.cur === d.cur && x.v[2] > 0; }).length;
    if (d.st === 'rejected' || d.st === 'error') sc = 0;
    else if (d.v[1] < 500) sc = null; // بيانات قليلة
    else {
      sc = 60;
      if (gCtr) sc += Math.max(-25, Math.min(20, (d.v[3] / gCtr - 1) * 40));
      if (same > 1 && a.cpc && d.v[4]) sc += Math.max(-20, Math.min(15, (1 - d.v[4] / a.cpc) * 30));
      sc += d.v[5] > 0 ? 10 : -20;
      sc = Math.round(Math.max(0, Math.min(100, sc)));
    }
    d.score = sc;
    var cls = sc == null ? 'bg-ink-100 text-ink-500' : sc >= 70 ? 'bg-lime-100 text-lime-800' : sc >= 45 ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-700';
    d.r.children[1].insertAdjacentHTML('beforeend', '<span class="ads-score inline-block mt-1 text-[11px] font-semibold rounded px-1.5 py-0.5 tabular-nums ' + cls + '" title="' + esc(S.scoreTip) + '">' + esc(S.score) + ': ' + (sc == null ? '—' : sc) + '</span>');
  });

  // ---- (8) أعمدة محسوبة
  var extra = [[S.cpm, function (d) { return d.v[1] ? d.v[0] / d.v[1] * 1000 : null; }, 1], [S.cpr, function (d) { return d.v[5] ? d.v[0] / d.v[5] : null; }, 1],
    [S.use, function (d) { return d.b && d.b[1] === 'lifetime' && d.b[0] ? d.v[0] / d.b[0] * 100 : null; }, 0]];
  var actTh = headRow.lastElementChild;
  extra.forEach(function (x, k) {
    var th = document.createElement('th'); th.className = 'text-end p-3 whitespace-nowrap'; th.textContent = x[0]; headRow.insertBefore(th, actTh);
    D.forEach(function (d) { var v = x[1](d), td = document.createElement('td'); td.className = 'p-3 text-end whitespace-nowrap tabular-nums'; td.dir = 'ltr'; td.style.textAlign = 'end';
      td.textContent = v == null ? '—' : x[2] ? money(v, d.cur) : Math.round(v) + '%'; d['x' + k] = v; d.r.insertBefore(td, d.r.lastElementChild); });
  });

  // ---- شريط أدوات
  var hdr = table.closest('section').querySelector('.p-4.border-b');
  var bar = document.createElement('div'); bar.className = 'w-full flex flex-wrap items-center gap-2 mt-3 text-sm'; hdr.classList.add('flex-wrap', 'gap-2'); hdr.appendChild(bar);
  bar.innerHTML = '<label class="flex items-center gap-1.5 text-ink-600">' + esc(S.group) + ' <select id="ads-grp" class="input !h-8 !py-0 !w-auto text-sm"><option value="">' + esc(S.gNone) + '</option><option value="accn">' + esc(S.gAcc) + '</option><option value="pf">' + esc(S.gPf) + '</option><option value="st">' + esc(S.gSt) + '</option></select></label>'
    + '<div class="inline-flex rounded-lg border border-ink-200 overflow-hidden" role="group" aria-label="' + esc(S.view) + '"><button type="button" data-view="table" class="px-3 h-8">' + esc(S.vTable) + '</button><button type="button" data-view="cards" class="px-3 h-8 border-s border-ink-200">' + esc(S.vCards) + '</button></div>'
    + '<div class="relative"><button type="button" id="ads-colbtn" class="btn-ghost !h-8 !py-0 text-sm">' + esc(S.colsBtn) + ' ▾</button><div id="ads-cols" class="hidden absolute z-30 mt-1 w-56 card p-2 space-y-1"></div></div>'
    + '<button type="button" id="ads-csv" class="btn-ghost !h-8 !py-0 text-sm">⤓ ' + esc(S.csv) + '</button>'
    + '<button type="button" id="ads-stop" class="ms-auto rounded-lg border border-red-300 text-red-700 hover:bg-red-50 px-3 h-8 text-sm font-semibold">■ ' + esc(S.stopAll) + '</button>';

  // ---- (7) اختيار الأعمدة
  var heads = [].slice.call(headRow.children), hidden = store.get('cols', []);
  var colBox = $('#ads-cols');
  heads.forEach(function (th, i) { if (i < 2 || i === heads.length - 1) return; colBox.insertAdjacentHTML('beforeend', '<label class="flex items-center gap-2 px-2 py-1 rounded hover:bg-ink-50"><input type="checkbox" data-col="' + i + '"' + (hidden.indexOf(i) < 0 ? ' checked' : '') + '> ' + esc(th.textContent.replace(/[↕↑↓]/g, '').trim()) + '</label>'); });
  function applyCols() { $$('tr', table).forEach(function (tr) { [].forEach.call(tr.children, function (c, i) { if (tr.classList.contains('ads-grow')) return; c.style.display = hidden.indexOf(i) >= 0 ? 'none' : ''; }); }); }
  colBox.addEventListener('change', function (e) { var i = +e.target.dataset.col; hidden = hidden.filter(function (x) { return x !== i; }); if (!e.target.checked) hidden.push(i); store.set('cols', hidden); applyCols(); });
  $('#ads-colbtn').onclick = function (e) { e.stopPropagation(); colBox.classList.toggle('hidden'); };
  document.addEventListener('click', function (e) { if (!e.target.closest('#ads-cols')) colBox.classList.add('hidden'); });
  applyCols();

  // ---- (5) تجميع
  var grp = $('#ads-grp'); grp.value = store.get('grp', '');
  var stLabel = function (d) { var b = d.r.children[2]; return b ? b.textContent.trim() : d.st; };
  function applyGroup() {
    $$('.ads-grow', tb).forEach(function (r) { r.remove(); });
    var k = grp.value; store.set('grp', k);
    if (!k) return;
    var groups = {}, order = [];
    $$('.ads-row', tb).forEach(function (r) { var d = D.filter(function (x) { return x.r === r; })[0]; var key = k === 'st' ? stLabel(d) : k === 'pf' ? (d.r.children[1].querySelector('.text-xs') || {}).textContent.split('·')[0].trim() : (d.accn || '—'); if (!groups[key]) { groups[key] = []; order.push(key); } groups[key].push(d); });
    order.forEach(function (key) {
      var ds = groups[key], tot = {}; ds.forEach(function (d) { tot[d.cur] = (tot[d.cur] || 0) + d.v[0]; });
      var tr = document.createElement('tr'); tr.className = 'ads-grow bg-ink-50/70';
      tr.innerHTML = '<td colspan="' + headRow.children.length + '" class="px-3 py-2 text-xs font-semibold text-ink-700">' + esc(key) + ' <span class="text-ink-500 font-normal">· ' + ds.length + ' · ' + Object.keys(tot).map(function (c) { return esc(money(tot[c], c)); }).join(' + ') + '</span></td>';
      tb.appendChild(tr); ds.forEach(function (d) { tb.appendChild(d.r); });
    });
  }
  grp.addEventListener('change', applyGroup); applyGroup();
  // إعادة التجميع بعد الترتيب
  $$('#ads-table [data-sort]').forEach(function (th) { th.addEventListener('click', function () { setTimeout(applyGroup, 0); }); });

  // ---- (6) عرض كروت
  var cards = document.createElement('div'); cards.id = 'ads-cards'; cards.className = 'hidden p-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3';
  table.parentNode.insertAdjacentElement('afterend', cards);
  function drawCards() {
    cards.innerHTML = D.filter(function (d) { return d.r.style.display !== 'none'; }).map(function (d) {
      var i = D.indexOf(d), sel = $('.ads-sel', d.r).checked;
      return '<div class="ads-card rounded-2xl border ' + (sel ? 'border-brand-500 bg-brand-50' : 'border-ink-200') + ' p-4 cursor-pointer hover:shadow-md transition" data-i="' + i + '">'
        + '<div class="flex items-start gap-2"><input type="checkbox" class="ads-csel mt-1" data-i="' + i + '"' + (sel ? ' checked' : '') + ' aria-label="' + esc(d.name) + '"><div class="min-w-0 flex-1"><b class="block truncate text-ink-900">' + esc(d.name) + '</b><div class="text-xs text-ink-500 truncate">' + esc(d.accn) + '</div></div>' + d.r.children[2].innerHTML + '</div>'
        + '<div class="grid grid-cols-3 gap-2 mt-3 text-center"><div><div class="text-[11px] text-ink-500">' + esc(S.cols[0]) + '</div><b class="text-sm tabular-nums" dir="ltr">' + esc(money(d.v[0], d.cur)) + '</b></div><div><div class="text-[11px] text-ink-500">CTR</div><b class="text-sm tabular-nums" dir="ltr">' + num(d.v[3]) + '%</b></div><div><div class="text-[11px] text-ink-500">' + esc(S.cols[5]) + '</div><b class="text-sm tabular-nums">' + num(d.v[5]) + '</b></div></div>'
        + '<div class="mt-3 flex items-center justify-between text-xs"><span class="text-ink-600">' + esc(d.r.children[3].textContent.trim()) + '</span>' + ($('.ads-score', d.r) ? $('.ads-score', d.r).outerHTML : '') + '</div></div>';
    }).join('');
  }
  cards.addEventListener('click', function (e) {
    var cb = e.target.closest('.ads-csel'), c = e.target.closest('.ads-card'); if (!c) return; var d = D[+c.dataset.i];
    if (cb) { $('.ads-sel', d.r).checked = cb.checked; $('.ads-sel', d.r).dispatchEvent(new Event('change', { bubbles: true })); drawCards(); return; }
    Z.quick(d);
  });
  function setView(v) {
    store.set('view', v); var cv = v === 'cards';
    table.parentNode.classList.toggle('hidden', cv); cards.classList.toggle('hidden', !cv); grp.disabled = cv;
    $$('[data-view]', bar).forEach(function (b) { var on = b.dataset.view === v; b.className = b.className.replace(/ ?(bg-brand-600 text-white|text-ink-700)/g, '') + (on ? ' bg-brand-600 text-white' : ' text-ink-700'); b.setAttribute('aria-pressed', on); });
    if (cv) drawCards();
  }
  $$('[data-view]', bar).forEach(function (b) { b.onclick = function () { setView(b.dataset.view); }; });
  setView(store.get('view', innerWidth < 768 ? 'cards' : 'table'));
  var q = $('#ads-q'); if (q) q.addEventListener('input', function () { if (!cards.classList.contains('hidden')) drawCards(); });

  // ---- (11) تصدير CSV للي ظاهر
  $('#ads-csv').onclick = function () {
    var cols = ['name', 'account', 'platform', 'status', 'currency', 'budget', 'budget_type', 'spend', 'impressions', 'clicks', 'ctr', 'cpc', 'results', 'cpm', 'cost_per_result', 'health_score'];
    var rows = D.filter(function (d) { return d.r.style.display !== 'none'; }).map(function (d) {
      return [d.name, d.accn, d.pf, d.st, d.cur, d.b ? d.b[0] : '', d.b ? d.b[1] : '', d.v[0], d.v[1], d.v[2], d.v[3], d.v[4], d.v[5], d.x0 == null ? '' : d.x0.toFixed(2), d.x1 == null ? '' : d.x1.toFixed(2), d.score == null ? '' : d.score];
    });
    var csv = '﻿' + [cols].concat(rows).map(function (r) { return r.map(function (v) { v = String(v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }).join(','); }).join('\n');
    var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = 'campaigns-' + new Date().toISOString().slice(0, 10) + '.csv'; document.body.appendChild(a); a.click(); a.remove();
  };

  // ---- (26) إيقاف طوارئ
  $('#ads-stop').onclick = function () {
    var d = Z.dialog(S.stopTitle, '<p class="text-sm text-red-800 bg-red-50 border border-red-200 rounded-xl p-3">' + esc(S.stopText) + '</p><input class="input mt-3 st" dir="ltr" placeholder="STOP" autocomplete="off"><div class="flex justify-end gap-2 mt-4"><button type="button" class="btn-ghost cn">' + esc(S.cancel) + '</button><button type="button" class="go rounded-xl bg-red-600 text-white font-semibold px-4 py-2 disabled:opacity-40" disabled>' + esc(S.stopAll) + '</button></div>');
    var st = $('.st', d), go = $('.go', d); st.oninput = function () { go.disabled = st.value !== 'STOP'; }; $('.cn', d).onclick = function () { d.close(); }; st.focus();
    go.onclick = function () { Z.postForm(C.bulkUrl, { action: 'stop_all', confirm_text: st.value }); };
  };

  // ---- (18) ميزانية جماعية بالنسبة — زرار في شريط الاختيار
  var bulk = $('#ads-bulk');
  new MutationObserver(function () {
    if (!C.canSpend || bulk.classList.contains('hidden') || $('[data-b=bud]', bulk)) return;
    var cmp = $('[data-b=cmp]', bulk); if (!cmp) return;
    cmp.insertAdjacentHTML('beforebegin', '<button type="button" data-b="bud" class="rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5">' + esc(S.bBudget) + '</button>');
  }).observe(bulk, { childList: true });
  bulk.addEventListener('click', function (e) {
    if (!e.target.closest('[data-b=bud]')) return;
    var sel = D.filter(function (d) { return $('.ads-sel', d.r).checked; }), ok = sel.filter(function (d) { return d.b && d.b[2]; }), skip = sel.length - ok.length, pct = 10;
    var dg = Z.dialog(S.bBudTitle, (skip ? '<p class="text-xs text-amber-800 bg-amber-50 rounded-lg p-2 mb-3">' + esc(fmt(S.bBudSkip, skip)) + '</p>' : '')
      + '<div class="flex flex-wrap gap-1.5 mb-3">' + [-30, -20, -10, 10, 20, 30].map(function (p) { return '<button type="button" data-p="' + p + '" class="rounded-full border px-3 py-1 text-sm" dir="ltr">' + (p > 0 ? '+' : '') + p + '%</button>'; }).join('') + '</div>'
      + '<ul class="lst text-sm space-y-1 max-h-60 overflow-y-auto"></ul>'
      + '<label class="mt-3 flex items-start gap-2 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-xl p-3"><input type="checkbox" class="cf mt-0.5"><span>' + esc(fmt(S.bBudConfirm, ok.length)) + '</span></label>'
      + '<div class="flex justify-end gap-2 mt-4"><button type="button" class="btn-ghost cn">' + esc(S.cancel) + '</button><button type="button" class="btn-primary go" disabled>' + esc(S.save) + '</button></div>');
    var draw = function () {
      $$('[data-p]', dg).forEach(function (b) { b.className = 'rounded-full border px-3 py-1 text-sm ' + (+b.dataset.p === pct ? 'bg-brand-600 text-white border-brand-600' : 'border-ink-200'); });
      $('.lst', dg).innerHTML = ok.map(function (d) { var n = d.b[0] * (1 + pct / 100); return '<li class="flex justify-between gap-3"><span class="truncate">' + esc(d.name) + '</span><span class="tabular-nums whitespace-nowrap" dir="ltr">' + esc(money(d.b[0], d.cur)) + ' → <b>' + esc(money(n, d.cur)) + '</b></span></li>'; }).join('');
      $('.cf', dg).checked = false; $('.go', dg).disabled = true;
    };
    $$('[data-p]', dg).forEach(function (b) { b.onclick = function () { pct = +b.dataset.p; draw(); }; });
    $('.cf', dg).onchange = function () { $('.go', dg).disabled = !this.checked || !ok.length; }; $('.cn', dg).onclick = function () { dg.close(); };
    $('.go', dg).onclick = function () { Z.postForm(C.bulkUrl, { action: 'bulk_budget', confirm_spend: '1', 'items[]': ok.map(function (d) { return [d.id, d.pf, (d.b[0] * (1 + pct / 100)).toFixed(2), d.b[1]].join('|'); }) }); };
    draw();
  });

  // ---- (15) الإجمالي بعملة واحدة
  var curs = Object.keys(Z.avg).filter(Boolean), base = C.myCur;
  var totCard = document.getElementById('ads-totals'); // كارت الأرقام بكل عملة (مش كروت الملخص اللي فوق)
  if (totCard && curs.length > 1) {
    var rates = store.get('fx', {}), box = document.createElement('div'); box.className = 'border-t border-ink-100 p-4 text-sm';
    totCard.appendChild(box);
    var drawFx = function () {
      var miss = false, total = 0;
      curs.forEach(function (c) { var r = c === base ? 1 : +rates[c]; if (!r) miss = true; else total += Z.avg[c].s * r; });
      box.innerHTML = '<div class="font-semibold text-ink-900 mb-2">' + esc(S.fxTitle) + ' (' + esc(base) + ')</div><div class="flex flex-wrap gap-3">'
        + curs.filter(function (c) { return c !== base; }).map(function (c) { return '<label class="flex items-center gap-1.5 text-ink-600">' + esc(fmt(S.fxRate, c, base)) + ' <input type="number" step="any" min="0" data-fx="' + c + '" value="' + (rates[c] || '') + '" class="input !h-8 !py-0 !w-24" dir="ltr"></label>'; }).join('')
        + '</div>' + (miss ? '' : '<div class="mt-2"><span class="text-ink-500">' + esc(S.fxTotal) + ':</span> <b class="text-lg tabular-nums" dir="ltr">' + esc(money(total, base)) + '</b> <span class="text-[11px] text-ink-500">' + esc(S.fxNote) + '</span></div>');
    };
    box.addEventListener('change', function (e) { if (e.target.dataset.fx) { rates[e.target.dataset.fx] = parseFloat(e.target.value) || 0; store.set('fx', rates); drawFx(); } });
    drawFx();
  }
})();
