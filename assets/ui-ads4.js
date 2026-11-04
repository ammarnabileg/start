/* الحملات — الذكاء الاصطناعي: (39) ملخص الفترة، (40) إعادة توزيع الميزانية بموافقة على كل نقلة،
   (41) شرح المصطلحات بأرقامك (من غير ذكاء اصطناعي: حساب ثابت) */
(function () {
  var Z = window.ADS2, C = window.ADX2; if (!Z || !C) return; var S = C.s, D = Z.D, esc = Z.esc, fmt = Z.fmt, money = Z.money, num = Z.num;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  // ---- (41) شرح المصطلحات على رؤوس الأعمدة
  var G = S.gl || {}, gI = 0, gC = 0, gR = 0; D.forEach(function (d) { gI += d.v[1]; gC += d.v[2]; gR += d.v[5]; });
  var cpcTxt = Object.keys(Z.avg).filter(Boolean).map(function (c) { return c + ' ' + money(Z.avg[c].cpc, c); }).join('، ');
  var tips = { 7: fmt(G.ctr, (gI ? (gC / gI * 100).toFixed(2) : 0) + '%'), 8: fmt(G.cpc, '', cpcTxt || '—'), 5: G.imp, 9: fmt(G.res, num(gR)) };
  var heads = $$('#ads-table thead th');
  heads.forEach(function (th, i) {
    var tip = tips[i] || (th.textContent.indexOf(S.cpm) >= 0 ? G.cpm : th.textContent.indexOf(S.cpr) >= 0 ? G.cpr : null); if (!tip) return;
    var b = document.createElement('button'); b.type = 'button'; b.className = 'ads-gl ms-1 inline-grid place-items-center w-4 h-4 rounded-full bg-ink-200 text-ink-600 text-[10px] font-bold align-middle'; b.textContent = 'i';
    b.setAttribute('aria-label', tip); b.title = tip;
    b.addEventListener('click', function (e) { e.stopPropagation(); var old = $('.ads-glpop'); if (old) { old.remove(); if (old._b === b) return; }
      var p = document.createElement('div'); p.className = 'ads-glpop absolute z-40 mt-2 w-72 card p-3 text-xs font-normal text-ink-700 leading-6 text-start whitespace-normal'; p.textContent = tip; p._b = b;
      th.style.position = 'relative'; th.appendChild(p); });
    th.appendChild(b);
  });
  document.addEventListener('click', function () { var o = $('.ads-glpop'); if (o) o.remove(); });

  // ---- لوحة المستشار
  var anchor = $('#ads-attn'); if (!anchor) return;
  var box = document.createElement('section'); box.className = 'card p-4'; box.id = 'ads-adv';
  box.innerHTML = '<div class="flex flex-wrap items-center gap-2"><b class="text-sm text-ink-900 me-auto">✦ ' + esc(S.adv) + '</b>'
    + '<button type="button" data-m="summary" class="btn-ghost text-sm">' + esc(S.advSum) + '</button>'
    + (C.canSpend ? '<button type="button" data-m="realloc" class="btn-ghost text-sm">' + esc(S.advRe) + '</button>' : '') + '</div>'
    + '<p class="text-[11px] text-ink-500 mt-2">' + esc(S.advHint) + '</p><div class="out mt-3 hidden"></div>';
  anchor.insertAdjacentElement('afterend', box);
  var out = $('.out', box);
  var payload = function () { return D.map(function (d) { return { id: d.id, name: d.name, status: d.st, currency: d.cur, budget: d.b ? d.b[0] : '', budget_type: d.b ? d.b[1] : '', spend: d.v[0], impressions: d.v[1], clicks: d.v[2], ctr: d.v[3], cpc: d.v[4], results: d.v[5], prev_spend: d.p ? d.p[0] : '', prev_results: d.p ? d.p[5] : '', can_edit_budget: d.b && d.b[2] ? 1 : 0 }; }); };
  var byId = {}; D.forEach(function (d) { byId[d.id] = d; });
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-m]'); if (!b) return; var mode = b.dataset.m;
    $$('[data-m]', box).forEach(function (x) { x.disabled = true; });
    out.classList.remove('hidden'); out.innerHTML = '<p class="text-sm text-ink-500">' + esc(S.advWait) + '</p>';
    var fd = new FormData(); fd.append('_csrf', C.csrf); fd.append('action', 'ai_advisor'); fd.append('mode', mode); fd.append('period', C.period); fd.append('data', JSON.stringify(payload()));
    fetch(C.aiUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      $$('[data-m]', box).forEach(function (x) { x.disabled = false; });
      if (!j.ok) { out.innerHTML = '<p class="text-sm text-red-700">' + esc(S.aiErr + ': ' + (j.error || '')) + '</p>'; return; }
      if (mode === 'summary') { out.innerHTML = '<div class="rounded-xl bg-ink-50 p-4 text-sm leading-7 whitespace-pre-line text-ink-800">' + esc(j.text) + '</div>'; return; }
      // تحقق من كل اقتراح قبل ما نعرضه
      var ok = [], bad = [];
      (j.moves || []).forEach(function (m) {
        var f = byId[m.from], t = byId[m.to], a = +m.amount, why = '';
        if (!f || !t || f === t) why = 'id';
        else if (f.cur !== t.cur) why = f.cur + '≠' + t.cur;
        else if (!f.b || !t.b || !f.b[2] || !t.b[2]) why = 'budget level';
        else if (f.b[1] !== t.b[1]) why = 'daily/lifetime';
        else if (f.v[1] < 1000 || t.v[1] < 1000) why = S.advData;
        else if (!(a > 0) || a > f.b[0] * 0.3 + 1e-9) why = money(a, f.cur) + ' > 30%';
        if (why) bad.push(fmt(S.advBad, why)); else ok.push({ f: f, t: t, a: Math.round(a * 100) / 100, why: m.why || '' });
      });
      out.innerHTML = (ok.length ? ok.map(function (m, i) {
        return '<div class="rounded-xl border border-ink-200 p-3 mb-2 text-sm"><b class="text-ink-900">' + esc(fmt(S.advMove, money(m.a, m.f.cur), m.f.name, m.t.name)) + '</b><p class="text-ink-600 text-xs mt-1">' + esc(m.why) + '</p>'
          + '<p class="text-[11px] text-ink-500 mt-1" dir="ltr">' + esc(money(m.f.b[0], m.f.cur) + ' → ' + money(m.f.b[0] - m.a, m.f.cur) + '   |   ' + money(m.t.b[0], m.t.cur) + ' → ' + money(m.t.b[0] + m.a, m.t.cur)) + '</p>'
          + '<button type="button" data-apply="' + i + '" class="btn-primary text-xs mt-2">' + esc(S.advApply) + '</button></div>';
      }).join('') : '<p class="text-sm text-ink-600">' + esc(S.advNone) + '</p>')
        + (j.note ? '<p class="text-xs text-ink-500 mt-1">' + esc(j.note) + '</p>' : '') + bad.map(function (x) { return '<p class="text-[11px] text-amber-700">' + esc(x) + '</p>'; }).join('');
      out.onclick = function (ev) {
        var ap = ev.target.closest('[data-apply]'); if (!ap) return; var m = ok[+ap.dataset.apply], nf = m.f.b[0] - m.a, nt = m.t.b[0] + m.a;
        var dg = Z.dialog(S.advTitle, '<label class="flex items-start gap-2 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-xl p-3"><input type="checkbox" class="cf mt-0.5"><span>' + esc(fmt(S.advConfirm, m.f.name, money(nf, m.f.cur), m.t.name, money(nt, m.t.cur))) + '</span></label>'
          + '<div class="flex justify-end gap-2 mt-4"><button type="button" class="btn-ghost cn">' + esc(S.cancel) + '</button><button type="button" class="btn-primary go" disabled>' + esc(S.save) + '</button></div>');
        $('.cf', dg).onchange = function () { $('.go', dg).disabled = !this.checked; }; $('.cn', dg).onclick = function () { dg.close(); };
        $('.go', dg).onclick = function () { Z.postForm(C.bulkUrl, { action: 'bulk_budget', confirm_spend: '1', 'items[]': [[m.f.id, m.f.pf, nf.toFixed(2), m.f.b[1]].join('|'), [m.t.id, m.t.pf, nt.toFixed(2), m.t.b[1]].join('|')] }); };
      };
    }).catch(function () { $$('[data-m]', box).forEach(function (x) { x.disabled = false; }); out.innerHTML = '<p class="text-sm text-red-700">' + esc(S.aiErr) + '</p>'; });
  });
})();
