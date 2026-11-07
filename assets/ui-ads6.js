/* صفحة الحملات: مفتاح تشغيل/إيقاف وتعديل الميزانية من الصف نفسه (من غير تحميل، وبتأكيد قبل الصرف)،
   توزيع الإنفاق بالضغط عليه يوصلك للحملة، ومقارنة 2–4 حملات جنب بعض */
(function () {
  var X = window.ADX6; if (!X) return; var T = X.T, rows = [].slice.call(document.querySelectorAll('tr.ads-row')); if (!rows.length) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function f(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }
  function num(v, d) { v = +v || 0; return v.toLocaleString('en-US', { maximumFractionDigits: d == null ? (v >= 100 ? 0 : 2) : d }); }
  function money(v, cur) { return num(v, (+v || 0) >= 1000 ? 0 : 2) + (cur ? ' ' + cur : ''); }
  function J(r, k) { try { return JSON.parse(r.dataset[k] || 'null'); } catch (e) { return null; } }
  function on(s) { return s === 'active' || s === 'pending_review'; }
  function post(r, d) { var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('ajax', '1'); fd.append('platform', r.dataset.pf); Object.keys(d).forEach(function (k) { fd.append(k, d[k]); });
    return fetch(X.api + '&id=' + encodeURIComponent(r.dataset.id) + '&pf=' + encodeURIComponent(r.dataset.pf) + '&r=' + encodeURIComponent(X.range), { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (x) { return x.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
  function bText(r) { var b = J(r, 'b'); return b ? money(b[0], r.dataset.cur) + (b[1] === 'lifetime' ? ' ' + T.lifetime : T.perDay) : '—'; }
  var SC = { active: '#16a34a', paused: '#d97706', pending_review: '#2563eb', rejected: '#dc2626' };

  /* ---------- الصف: مفتاح + ميزانية + قايمة ---------- */
  function paintRow(r) { var st = r.dataset.st, cell = r.lastElementChild, b = J(r, 'b'), dup = cell.querySelector('a[href*="ad_create"]');
    if (!cell.dataset.x) { cell.dataset.x = 1; cell.dataset.dup = dup ? dup.href : ''; }
    cell.innerHTML = '<div class="flex items-center gap-1.5">' + (/^(active|paused|pending_review)$/.test(st) ? '<button type="button" class="acp-sw sm' + (on(st) ? ' on' : '') + '" data-rsw role="switch" aria-checked="' + on(st) + '" title="' + esc(T[st] || st) + '"><i></i><span></span></button>' : '') +
      '<details class="adx-menu"><summary title="' + esc(T.more) + '">⋯</summary><div><a href="' + esc(r.dataset.detail) + '">' + ic('eye') + esc(T.details) + '</a>' + (cell.dataset.dup ? '<a href="' + esc(cell.dataset.dup) + '">' + ic('copy') + esc(T.dup) + '</a>' : '') + '</div></details></div>';
    var bc = r.querySelector('.ads-budget'); if (bc) { bc.textContent = b ? bText(r) : bc.textContent; var td = bc.closest('td'); td.classList.add('adx-bud'); td.classList.toggle('edit', !!(b && b[2] && X.spend)); td.title = b && b[2] ? (X.spend ? T.budgetT : T.ownerOnly) : (b ? T.bySets : ''); }
    var badge = r.children[2]; if (badge) badge.innerHTML = '<span class="acp-st" style="--c:' + (SC[st] || '#64748b') + '">' + esc(T[st] || st) + '</span>'; }
  rows.forEach(paintRow);
  var pop = document.createElement('dialog'); pop.className = 'acp-pop'; document.body.appendChild(pop);
  pop.addEventListener('click', function (e) { if (e.target === pop || e.target.closest('[data-x]')) pop.close(); });
  function ask(r, btn) { var st = r.dataset.st, act = on(st), nm = r.dataset.name; if (!act && !X.spend) return toast(T.ownerOnly, 'error');
    pop.innerHTML = '<form method="dialog" class="p-5 space-y-3 w-[min(400px,92vw)]"><div class="flex items-center gap-3"><span class="acp-pi" style="--c:' + (act ? '#d97706' : '#16a34a') + '">' + ic(act ? 'pause' : 'play', 'w-5 h-5') + '</span><b class="text-ink-900" dir="auto">' + esc(f(act ? T.pauseQ : T.resumeQ, nm)) + '</b></div>' +
      (act ? '<p class="text-sm text-ink-600">' + esc(T.pauseHint) + '</p>' : '<label class="acp-confirm"><input type="checkbox" name="ok"><span>' + esc(f(T.spendOk, bText(r))) + '</span></label>') +
      '<div class="flex gap-2"><button class="btn-primary flex-1' + (act ? ' !bg-amber-600' : '') + '"' + (act ? '' : ' disabled') + '>' + esc(act ? T.pause : T.resume) + '</button><button type="button" class="btn-ghost" data-x>' + esc(T.cancel) + '</button></div></form>';
    var fm = pop.querySelector('form'), go = fm.querySelector('.btn-primary'); if (fm.ok) fm.ok.addEventListener('change', function () { go.disabled = !this.checked; });
    fm.addEventListener('submit', function (e) { e.preventDefault(); go.disabled = true; go.textContent = T.loading; btn.classList.add('busy'); var to = act ? 'paused' : 'active', d = { action: 'campaign_status', to: to }; if (!act) d.confirm_spend = '1';
      post(r, d).then(function (j) { btn.classList.remove('busy'); if (!j.ok) { go.disabled = false; go.textContent = act ? T.pause : T.resume; return toast(j.msg || T.err, 'error'); } r.dataset.st = to; paintRow(r); pop.close(); toast(j.msg || T.saved); r.classList.add('adx-flash'); setTimeout(function () { r.classList.remove('adx-flash'); }, 1200); })
        .catch(function () { btn.classList.remove('busy'); go.disabled = false; toast(T.err, 'error'); }); });
    pop.showModal(); }
  function budget(r) { var b = J(r, 'b'), cur0 = b[0], cur = r.dataset.cur;
    pop.innerHTML = '<form method="dialog" class="p-5 space-y-3 w-[min(420px,92vw)]"><b class="block text-ink-900">' + esc(T.budgetT) + ' · <span dir="auto">' + esc(r.dataset.name) + '</span></b><p class="text-xs text-ink-500">' + esc(f(T.now, bText(r))) + '</p>' +
      '<div class="acp-step"><button type="button" data-st="-0.1">−</button><input name="amount" type="number" step="0.01" min="0.01" value="' + esc(cur0) + '" dir="ltr"><span>' + esc(cur) + '</span><button type="button" data-st="0.1">+</button></div>' +
      '<div class="flex flex-wrap gap-1.5">' + ['-20', '+10', '+20', '+50'].map(function (p) { return '<button type="button" class="acp-q" data-pc="' + p + '">' + p + '%</button>'; }).join('') + '<div class="acp-seg !p-0.5 ms-auto"><label><input type="radio" name="type" value="daily"' + (b[1] !== 'lifetime' ? ' checked' : '') + '><span>' + esc(T.daily) + '</span></label><label><input type="radio" name="type" value="lifetime"' + (b[1] === 'lifetime' ? ' checked' : '') + '><span>' + esc(T.lifetime) + '</span></label></div></div>' +
      '<p class="acp-delta" data-dl></p><label class="acp-confirm"><input type="checkbox" name="ok"><span>' + esc(T.budgetOk) + '</span></label><div class="flex gap-2"><button class="btn-primary flex-1" disabled>' + esc(T.save) + '</button><button type="button" class="btn-ghost" data-x>' + esc(T.cancel) + '</button></div></form>';
    var fm = pop.querySelector('form'), go = fm.querySelector('.btn-primary');
    function upd() { var v = +fm.amount.value || 0, d = v - cur0, daily = fm.querySelector('[name=type]:checked').value === 'daily';
      fm.querySelector('[data-dl]').innerHTML = (d ? '<b class="' + (d > 0 ? 'text-amber-700' : 'text-lime-700') + '">' + (d > 0 ? '+' : '') + esc(f(T.delta, money(d, cur))) + '</b> · ' : '') + (daily && v ? esc(f(T.perMonth, money(v * 30, cur))) : ''); go.disabled = !(fm.ok.checked && v > 0); }
    fm.addEventListener('click', function (e) { var s = e.target.closest('[data-st]'), p = e.target.closest('[data-pc]'), v = +fm.amount.value || cur0;
      if (s) fm.amount.value = Math.max(0.01, v * (1 + +s.dataset.st)).toFixed(2); if (p) fm.amount.value = Math.max(0.01, cur0 * (1 + parseFloat(p.dataset.pc) / 100)).toFixed(2); if (s || p) upd(); });
    fm.addEventListener('input', upd); fm.addEventListener('change', upd); upd();
    fm.addEventListener('submit', function (e) { e.preventDefault(); go.disabled = true; go.textContent = T.loading; var d = { action: 'budget', amount: fm.amount.value, type: fm.querySelector('[name=type]:checked').value, confirm_spend: '1' };
      post(r, d).then(function (j) { if (!j.ok) { go.disabled = false; go.textContent = T.save; return toast(j.msg || T.err, 'error'); } r.dataset.b = JSON.stringify([+d.amount, d.type, true]); paintRow(r); pop.close(); toast(j.msg || T.saved); })
        .catch(function () { go.disabled = false; go.textContent = T.save; toast(T.err, 'error'); }); });
    pop.showModal(); fm.amount.select(); }
  document.addEventListener('click', function (e) { var sw = e.target.closest('tr.ads-row [data-rsw]'), bd = e.target.closest('tr.ads-row td.adx-bud');
    if (sw) { e.preventDefault(); e.stopPropagation(); return ask(sw.closest('tr'), sw); }
    if (e.target.closest('tr.ads-row .adx-menu')) { e.stopPropagation(); return; }
    if (bd) { e.stopPropagation(); var r = bd.closest('tr'), b = J(r, 'b'); if (!b) return; if (!b[2]) return toast(T.bySets, 'info'); if (!X.spend) return toast(T.ownerOnly, 'error'); budget(r); } }, true);

  /* ---------- التوزيع (إنفاق / نقرات / نتائج) ---------- */
  var host = document.getElementById('ads-st'), COL = ['#2563eb', '#7c3aed', '#0891b2', '#16a34a', '#d97706', '#db2777', '#64748b'], curs = {}; rows.forEach(function (r) { curs[r.dataset.cur] = 1; });
  var oneCur = Object.keys(curs).length === 1, metric = oneCur ? 0 : 2, sec = null;
  function share() { var list = rows.map(function (r) { return [r, (J(r, 'm') || [])[metric] || 0]; }).filter(function (x) { return x[1] > 0; }).sort(function (a, b) { return b[1] - a[1]; }), tot = list.reduce(function (s2, x) { return s2 + x[1]; }, 0);
    if (!sec) { sec = document.createElement('section'); sec.className = 'adx-share'; host.after(sec); }
    var top = list.slice(0, 6), rest = list.slice(6).reduce(function (s2, x) { return s2 + x[1]; }, 0), fv = function (v) { return metric === 0 ? money(v, rows[0].dataset.cur) : num(v); };
    var tabs = [[0, T.spend, oneCur], [2, T.clicks, true], [5, T.res, true]].filter(function (x) { return x[2]; });
    sec.classList.remove('go'); sec.classList.toggle('hidden', !host);
    sec.innerHTML = '<div class="flex flex-wrap items-center gap-2 mb-3"><b class="text-ink-900">' + ic('layers', 'w-4 h-4 inline') + ' ' + esc(T.share) + '</b><span class="text-xs text-ink-500 tabular-nums">' + (tot ? esc(fv(tot)) : '') + '</span>' +
      '<div class="acp-seg !p-0.5 ms-auto">' + tabs.map(function (x) { return '<button type="button" data-sm="' + x[0] + '" class="' + (x[0] === metric ? 'on' : '') + '">' + esc(x[1]) + '</button>'; }).join('') + '</div></div>' +
      (list.length ? '<div class="adx-bar">' + top.map(function (x, i) { return '<button type="button" data-go="' + esc(x[0].dataset.id) + '" style="--w:' + (x[1] / tot * 100) + '%;--c:' + COL[i] + '" title="' + esc(x[0].dataset.name + ' · ' + fv(x[1])) + '"></button>'; }).join('') + (rest ? '<span style="--w:' + (rest / tot * 100) + '%;--c:#cbd5e1"></span>' : '') + '</div>' +
      '<div class="adx-leg">' + top.map(function (x, i) { return '<button type="button" data-go="' + esc(x[0].dataset.id) + '"><i style="background:' + COL[i] + '"></i><span class="truncate" dir="auto">' + esc(x[0].dataset.name) + '</span><b>' + Math.round(x[1] / tot * 100) + '%</b></button>'; }).join('') + (rest ? '<span class="text-ink-400"><i style="background:#cbd5e1"></i>' + esc(T.others) + ' <b>' + Math.round(rest / tot * 100) + '%</b></span>' : '') + '</div>'
        : '<p class="text-xs text-ink-400">—</p>') + '<p class="mt-2 text-[11px] text-ink-400">' + esc(T.shareHint) + (oneCur ? '' : ' · ' + esc(T.mixed)) + '</p>';
    requestAnimationFrame(function () { requestAnimationFrame(function () { sec.classList.add('go'); }); }); }
  if (host && rows.length > 1) { share();
    sec.addEventListener('click', function (e) { var t = e.target.closest('[data-sm]'); if (t) { metric = +t.dataset.sm; return share(); } var b = e.target.closest('[data-go]'); if (!b) return; var r = rows.filter(function (x) { return x.dataset.id === b.dataset.go; })[0]; if (!r) return;
      if (r.offsetParent === null) { var all = document.querySelector('#ads-st [data-mk-stat="_all"], #ads-st button'); if (all) all.click(); }
      r.scrollIntoView({ behavior: 'smooth', block: 'center' }); r.classList.add('adx-flash'); setTimeout(function () { r.classList.remove('adx-flash'); }, 1600); }); }

  /* ---------- المقارنة ---------- */
  var cmpB = document.createElement('button'); cmpB.type = 'button'; cmpB.className = 'adx-cmp hidden'; cmpB.innerHTML = ic('layers', 'w-4 h-4') + '<span></span>'; document.body.appendChild(cmpB);
  function picked() { return rows.filter(function (r) { var c = r.querySelector('.ads-sel'); return c && c.checked; }); }
  function syncCmp() { var n = picked().length; cmpB.classList.toggle('hidden', n < 2); cmpB.querySelector('span').textContent = T.compare + ' (' + n + ')'; cmpB.disabled = n > 4; cmpB.title = n > 4 ? T.pick2 : ''; }
  document.addEventListener('change', function (e) { if (e.target.closest('.ads-sel') || e.target.id === 'ads-all' || e.target.closest('thead')) setTimeout(syncCmp, 0); });
  cmpB.addEventListener('click', function () { var ps = picked().slice(0, 4); if (ps.length < 2) return toast(T.pick2, 'error'); var mixed = ps.some(function (r) { return r.dataset.cur !== ps[0].dataset.cur; });
    var M = ps.map(function (r) { var m = J(r, 'm') || [0, 0, 0, 0, 0, 0]; return { r: r, v: { spend: m[0], imp: m[1], clicks: m[2], ctr: m[3], cpc: m[4], res: m[5], cpr: m[5] ? m[0] / m[5] : 0 } }; });
    var K = [['spend', T.spend, 0, 1], ['imp', T.imp, 1, 0], ['clicks', T.clicks, 1, 0], ['ctr', T.ctr, 1, 0], ['cpc', T.cpc, -1, 1], ['res', T.res, 1, 0], ['cpr', T.cpr, -1, 1]], COL = ['#2563eb', '#7c3aed', '#0891b2', '#d97706'];
    pop.innerHTML = '<div class="p-5 w-[min(720px,94vw)] space-y-4"><div class="flex items-center gap-2"><b class="text-lg text-ink-900 me-auto">' + esc(T.compareT) + '</b><button type="button" class="p-2 rounded-xl text-ink-400 hover:bg-ink-100" data-x>' + ic('x', 'w-5 h-5') + '</button></div>' +
      '<div class="flex flex-wrap gap-2">' + M.map(function (x, i) { return '<span class="adx-cl"><i style="background:' + COL[i] + '"></i><span dir="auto">' + esc(x.r.dataset.name) + '</span></span>'; }).join('') + '</div>' + (mixed ? '<p class="text-xs text-amber-700">' + esc(T.mixed) + '</p>' : '') +
      '<div class="space-y-3">' + K.map(function (k) { var vals = M.map(function (x) { return x.v[k[0]] || 0; }), mx = Math.max.apply(null, vals) || 1, valid = vals.filter(function (v) { return v > 0; }),
          best = k[2] === 0 || !valid.length || (mixed && k[3]) ? -1 : vals.indexOf(k[2] > 0 ? Math.max.apply(null, valid) : Math.min.apply(null, valid));
        return '<div><small class="block text-xs font-semibold text-ink-500 mb-1">' + esc(k[1]) + '</small>' + M.map(function (x, i) { var v = vals[i];
          return '<div class="adx-crow"><span class="adx-cbar"><i style="--w:' + (v / mx * 100) + '%;background:' + COL[i] + '"></i></span><b dir="ltr">' + (k[0] === 'ctr' ? num(v, 2) + '%' : k[3] ? money(v, mixed ? '' : x.r.dataset.cur) : num(v)) + '</b>' + (i === best ? '<em>★ ' + esc(T.winner) + '</em>' : '<em></em>') + '</div>'; }).join('') + '</div>'; }).join('') + '</div></div>';
    pop.showModal(); requestAnimationFrame(function () { pop.querySelectorAll('.adx-cbar i').forEach(function (i) { i.classList.add('go'); }); }); });
})();
