/* نافذة الحملة السريعة: إيقاف/تشغيل وتعديل الميزانية من غير ما تسيب القايمة */
(function () {
  var C = window.ADX2, L = window.ADX5; if (!C || !L) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function send(data) { var f = new FormData(); f.append('_csrf', C.csrf); Object.keys(data).forEach(function (k) { [].concat(data[k]).forEach(function (v) { f.append(k, v); }); });
    return fetch(C.aiUrl, { method: 'POST', body: f, credentials: 'same-origin' }); }
  window.adsDrawerActions = function (dr, d) {
    var active = d.st === 'active', can = C.canSpend, bar = document.createElement('div');
    bar.className = 'px-5 py-3 border-b border-ink-200 bg-ink-50/60 space-y-3';
    var st = active ? '<button type="button" class="btn-ghost !py-1.5 !text-sm" data-a="pause">⏸ ' + esc(L.pause) + '</button>'
      : (d.st === 'paused' ? (can ? '<button type="button" class="btn-primary !py-1.5 !text-sm" data-a="resume">▶ ' + esc(L.resume) + '</button>' : '<span class="text-xs text-ink-500">' + esc(L.ownerOnly) + '</span>') : '');
    var bud = d.b && can ? '<div class="flex flex-wrap items-center gap-2 text-sm"><span class="text-ink-600">' + esc(L.budget) + '</span><input type="number" min="1" step="0.01" class="input !mt-0 !py-1 !h-9 !w-28 text-sm" data-bud value="' + esc(d.b[0]) + '" dir="ltr">' +
      '<select class="input !mt-0 !py-0 !h-9 !w-auto text-sm" data-bt><option value="daily"' + (d.b[1] === 'daily' ? ' selected' : '') + '>' + esc(L.daily) + '</option><option value="lifetime"' + (d.b[1] === 'lifetime' ? ' selected' : '') + '>' + esc(L.lifetime) + '</option></select>' +
      '<span class="text-xs text-ink-500" dir="ltr">' + esc(d.cur || '') + '</span><button type="button" class="btn-primary !py-1.5 !text-sm hidden" data-a="budget">' + esc(L.save) + '</button></div>' : '';
    if (!st && !bud) return;
    bar.innerHTML = '<div class="flex flex-wrap items-center gap-2">' + st + '<span class="text-[11px] text-ink-500 ms-auto" data-msg></span></div>' + bud +
      (can ? '<label class="hidden items-center gap-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5" data-conf><input type="checkbox" data-ok> ' + esc(L.confirm) + '</label>' : '');
    var head = dr.firstElementChild; head.after(bar);
    var b = bar.querySelector('[data-bud]'), bt = bar.querySelector('[data-bt]'), save = bar.querySelector('[data-a="budget"]'), conf = bar.querySelector('[data-conf]'), msg = bar.querySelector('[data-msg]');
    function needConf(on) { if (conf) { conf.classList.toggle('hidden', !on); conf.classList.toggle('flex', on); } }
    function changed() { var c = b && (+b.value !== +d.b[0] || bt.value !== d.b[1]); if (save) save.classList.toggle('hidden', !c); needConf(!!c || !!bar.querySelector('[data-a="resume"]:focus')); }
    if (b) { b.addEventListener('input', changed); bt.addEventListener('change', changed); }
    bar.addEventListener('click', function (e) {
      var x = e.target.closest('[data-a]'); if (!x) return; var a = x.dataset.a, ok = conf && conf.querySelector('[data-ok]').checked;
      if ((a === 'resume' || a === 'budget') && !ok) { needConf(true); msg.textContent = L.needConf; msg.className = 'text-[11px] text-amber-700 ms-auto'; return; }
      if (a === 'budget' && !(+b.value > 0)) return;
      x.disabled = true; msg.textContent = L.busy; msg.className = 'text-[11px] text-ink-500 ms-auto';
      var req = a === 'budget' ? { action: 'bulk_budget', 'items[]': [d.id + '|' + d.pf + '|' + b.value + '|' + bt.value], confirm_spend: 1 } : { action: 'bulk_status', to: a === 'pause' ? 'paused' : 'active', 'ids[]': [d.id + '|' + d.pf], confirm_spend: ok ? 1 : '' };
      send(req).then(function (r) { if (!r.ok && r.status >= 400) throw 0; msg.textContent = L.done; msg.className = 'text-[11px] text-lime-700 ms-auto'; setTimeout(function () { location.reload(); }, 700); })
        .catch(function () { x.disabled = false; msg.textContent = L.err; msg.className = 'text-[11px] text-red-600 ms-auto'; });
    });
    var rs = bar.querySelector('[data-a="resume"]'); if (rs) rs.addEventListener('mouseenter', function () { needConf(true); });
  };
})();
