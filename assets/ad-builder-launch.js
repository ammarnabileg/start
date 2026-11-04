/* منشئ الحملات — الفحص على المنصة والإطلاق خطوة بخطوة (كل حاجة بتتعمل متوقفة) */
(function () {
  var X = window.ABX; if (!X) return; var A = X.A, S = X.S, C = X.C, D = X.D, esc = X.esc, fmt = X.fmt, L = S.launch || {};
  var prog = A.progress || { done: [], errors: {}, inflight: null, ids: {} }, running = false, vres = null;
  function plan() {
    var st = []; if (C.campaignsEndpoint) st.push({ k: 'campaign' });
    D.adSets.forEach(function (s, si) {
      if (C.multiAds === 'creatives') { st.push({ k: 'set', si: si, ads: s.ads.length }); return; }
      s.ads.forEach(function (a, ai) { if (C.multiAds === 'none' && ai > 0) return; st.push(ai === 0 ? { k: 'set', si: si, ai: 0 } : { k: 'ad', si: si, ai: ai }); });
    });
    return st;
  }
  var nm = function (si) { return D.adSets[si].name || fmt(S.setN, si + 1); };
  function label(s) { return s.k === 'campaign' ? fmt(L.stCamp, D.campaign.name) : s.k === 'set' ? (s.ads ? fmt(L.stSetAds, nm(s.si), s.ads) : fmt(L.stSet, nm(s.si))) : fmt(L.stAd, D.adSets[s.si].ads[s.ai].name || fmt(S.adN, s.ai + 1), nm(s.si)); }
  function draw(iss) {
    var box = document.getElementById('ab-launch'); if (!box) return;
    var P = plan(), errs = (iss || X.issues()).filter(function (x) { return x.lvl === 'err'; }).length, done = prog.done || [], fin = done.length === P.length && P.length;
    var skipped = C.multiAds === 'none' ? D.adSets.reduce(function (n, s) { return n + Math.max(0, s.ads.length - 1); }, 0) : 0;
    var h = '<section class="card p-4 space-y-3"><b class="block text-sm text-ink-900">' + esc(L.title) + '</b>'
      + '<ol class="space-y-1 text-xs">' + P.map(function (s, i) {
        var st = done.indexOf(i) >= 0 ? ['✓', 'text-lime-700'] : (prog.errors || {})[i] ? ['✕', 'text-red-600'] : prog.inflight === i ? ['?', 'text-amber-700'] : ['○', 'text-ink-400'];
        var vr = vres && vres.filter(function (r) { return r.i === i; })[0];
        return '<li class="flex gap-2"><span class="' + st[1] + ' font-bold w-3">' + st[0] + '</span><span class="flex-1">' + esc(label(s)) + ((prog.errors || {})[i] ? '<span class="block text-red-700">' + esc(prog.errors[i]) + '</span>' : '') + (vr ? '<span class="block ' + (vr.ok ? 'text-lime-700' : 'text-red-700') + '">' + esc(vr.ok ? L.vOk : vr.error) + '</span>' : '') + '</span></li>';
      }).join('') + '</ol>'
      + (skipped ? '<p class="text-[11px] text-amber-800 bg-amber-50 rounded-lg p-2">' + esc(fmt(L.skipped, skipped)) + '</p>' : '')
      + '<p class="text-[11px] text-ink-500">' + esc(L.pausedNote) + '</p>';
    if (fin) h += '<p class="text-sm text-lime-800 bg-lime-50 rounded-lg p-3">' + esc(L.done) + ' <a class="link" href="' + esc(A.campaignsUrl) + '">' + esc(L.openCamps) + '</a></p>';
    else if (!A.canSpend) h += '<p class="text-xs text-ink-500">' + esc(L.noPerm) + '</p>';
    else {
      if (prog.inflight !== null && prog.inflight !== undefined) h += '<div class="text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-lg p-2">' + esc(L.unknown) + ' <button type="button" id="ab-inflight" class="underline font-semibold">' + esc(L.markRetry) + '</button></div>';
      if (C.validate && !done.length) h += '<button type="button" id="ab-validate" class="btn-ghost w-full text-sm"' + (errs || running ? ' disabled' : '') + '>' + esc(L.validate) + '</button>';
      h += '<label class="flex items-start gap-2 text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-xl p-2.5"><input type="checkbox" id="ab-cf" class="mt-0.5"' + (errs ? ' disabled' : '') + '><span>' + esc(fmt(L.confirm, P.length)) + '</span></label>'
        + '<button type="button" id="ab-go" class="btn-primary w-full" disabled>' + esc(done.length ? L.resume : L.go) + '</button>'
        + (errs ? '<p class="text-[11px] text-red-700">' + esc(L.fixFirst) + '</p>' : '');
    }
    box.innerHTML = h + '</section>';
    var cf = document.getElementById('ab-cf'), go = document.getElementById('ab-go');
    if (cf) cf.onchange = function () { go.disabled = !cf.checked || running; };
    if (go) go.onclick = launch;
    var v = document.getElementById('ab-validate'); if (v) v.onclick = validate;
    var r = document.getElementById('ab-inflight'); if (r) r.onclick = function () { if (!confirm(L.markRetryQ)) return; post({ action: 'reset_inflight' }).then(function () { prog.inflight = null; draw(); }); };
  }
  function post(f) { var fd = new FormData(); fd.append('_csrf', A.csrf); fd.append('id', A.id); Object.keys(f).forEach(function (k) { fd.append(k, f[k]); }); return fetch(A.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function validate() {
    running = true; var b = document.getElementById('ab-validate'); b.disabled = true; b.textContent = L.validating; X.save(true);
    setTimeout(function () { post({ action: 'validate' }).then(function (j) { running = false; if (!j.ok) { alert(j.error); draw(); return; } vres = j.results; draw(); }).catch(function () { running = false; draw(); }); }, 900);
  }
  function launch() {
    if (!confirm(L.lastQ)) return; running = true; X.save(true);
    var step = function () {
      post({ action: 'launch_step', confirm_spend: '1' }).then(function (j) {
        if (j.progress) prog = j.progress;
        if (!j.ok) { running = false; if (j.unknown) prog.inflight = j.step; else if (j.step != null) { prog.errors = prog.errors || {}; prog.errors[j.step] = j.error; } else alert(j.error); draw(); return; }
        if (j.finished) { running = false; A.state = 'launched'; draw(); return; }
        draw(); setTimeout(step, 400);
      }).catch(function () { running = false; prog.inflight = prog.inflight == null ? -1 : prog.inflight; alert(L.netErr); location.reload(); });
    };
    setTimeout(step, 900);
  }
  document.addEventListener('ab:render', function (e) { draw(e.detail && e.detail.issues); });
  draw();
})();
