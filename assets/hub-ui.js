/* المصادر والإعدادات — جوه اللوحة: كروت جديدة بتتبني هنا (التقاط من الرسائل، تتبع الموقع، خطط المتابعة، رسائل واتساب الجاهزة، السرعة والتوزيع)،
   وتحسين الأدوات الموجودة (QR ظاهر، إعدادات الفورم والحجز، مفتاح ورقائق، معاينات حية، منطقة سحب وإفلات، خطوات). كله بيحفظ من غير تحميل الصفحة. */
(function () {
  var X = window.HUBX; if (!X) return; var T = X.T || {};
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function f(s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function api(d) { var fd = new FormData(); fd.append('_csrf', X.csrf); Object.keys(d).forEach(function (k) { [].concat(d[k]).forEach(function (v) { fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v); }); });
    return fetch(X.api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
  function save(btn, p, done) { btn.disabled = true; btn.classList.add('pd-busy'); return p.then(function (j) { btn.disabled = false; btn.classList.remove('pd-busy'); if (!j || !j.ok) return toast((j && j.error) || T.err, 'error'); toast(T.saved); if (done) done(j); })
    .catch(function () { btn.disabled = false; btn.classList.remove('pd-busy'); toast(T.err, 'error'); }); }
  function sw(name, on, label, sub) { return '<label class="hx-sw"><input type="checkbox" name="' + name + '"' + (on ? ' checked' : '') + '><i></i><span><b>' + esc(label) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span></label>'; }
  function chips(name, list, ph) { return '<div class="hx-chips" data-chips="' + name + '">' + list.map(function (x) { return '<span class="hx-chip">' + esc(x) + '<button type="button" data-rm>×</button></span>'; }).join('') + '<input placeholder="' + esc(ph || T.addEnter) + '"></div>'; }
  function chipVals(box) { return [].map.call(box.querySelectorAll('.hx-chip'), function (c) { return c.firstChild.textContent; }); }
  document.addEventListener('keydown', function (e) { var inp = e.target.closest && e.target.closest('.hx-chips input'); if (!inp || (e.key !== 'Enter' && e.key !== ',' && e.key !== '،')) return; e.preventDefault(); var v = inp.value.trim(); if (!v) return;
    inp.insertAdjacentHTML('beforebegin', '<span class="hx-chip">' + esc(v) + '<button type="button" data-rm>×</button></span>'); inp.value = ''; inp.closest('.hx-chips').dispatchEvent(new Event('change', { bubbles: true })); });
  document.addEventListener('click', function (e) { var b = e.target.closest('.hx-chip [data-rm]'); if (!b) return; var box = b.closest('.hx-chips'); b.parentNode.remove(); box.dispatchEvent(new Event('change', { bubbles: true })); });
  var RO = !X.manage ? ' disabled' : '';
  function qr(el, u) { if (!window.qrcode) return; try { var q = qrcode(0, 'M'); q.addData(u); q.make(); el.innerHTML = q.createSvgTag({ cellSize: 3, margin: 0 }); } catch (e) {} }
  var DAYS = T.days || ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  var B = window.HubX = {};
  /* ---------- التقاط من الرسائل ---------- */
  B.auto = function (body) { body.innerHTML = '<div class="hx-card space-y-4">' + sw('on', X.auto, T.autoT, T.autoD) + '<div><b class="hx-l">' + esc(T.ctaT) + '</b><p class="hx-d">' + esc(T.ctaD) + '</p>' + chips('cta', X.cta) + '</div>' +
      '<div class="hx-demo"><small>' + esc(T.demo) + '</small><p></p></div></div>';
    var on = body.querySelector('[name=on]'), box = body.querySelector('[data-chips]'), demo = body.querySelector('.hx-demo p');
    var paint = function () { var w = chipVals(box); demo.innerHTML = esc(T.demoMsg).replace(/\{w\}/g, '<mark>' + esc(w[0] || '…') + '</mark>') + ' → <b class="text-lime-700">✓ ' + esc(T.captured) + '</b>'; }; paint();
    on.addEventListener('change', function () { api({ action: 'auto_capture', on: on.checked ? 1 : '' }).then(function (j) { if (!j.ok) { on.checked = !on.checked; return toast(j.error || T.err, 'error'); } toast(on.checked ? f(T.autoOn, j.n || 0) : T.saved); }); });
    box.addEventListener('change', function () { paint(); clearTimeout(box._t); box._t = setTimeout(function () { api({ action: 'cta_save', words: chipVals(box).join(', ') }).then(function (j) { if (j.ok) toast(T.saved); }); }, 600); }); };
  /* ---------- تتبع الموقع ---------- */
  B.track = function (body) { var c = X.track;
    body.innerHTML = '<form class="hx-card space-y-4" data-no-ajax><ol class="hx-steps"><li class="' + (X.site ? 'done' : 'on') + '"><b>1</b>' + esc(T.tr1) + '</li><li class="' + (X.site ? 'on' : '') + '"><b>2</b>' + esc(T.tr2) + '</li><li><b>3</b>' + esc(T.tr3) + '</li></ol>' +
      '<label class="hx-l">' + esc(T.site) + '<input name="site" class="input" dir="ltr" placeholder="https://" value="' + esc(X.site) + '"' + RO + '></label>' +
      '<div><b class="hx-l">' + esc(T.snippet) + '</b><div class="hx-code" dir="ltr"><code>' + esc(X.trackJs) + '</code><button type="button" class="btn-ghost !py-1 !text-xs" data-cp>' + ic('copy') + esc(T.copy) + '</button></div><p class="hx-d">' + esc(T.snipD) + '</p></div>' +
      '<div><b class="hx-l">' + esc(T.hot) + '</b><p class="hx-d">' + esc(T.hotD) + '</p>' + chips('hot', String(c.hot || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean)) + '</div>' +
      sw('btn', !!+c.btn, T.waBtn, T.waBtnD) + '<div class="grid grid-cols-[1fr_1fr_70px] gap-2"><label class="hx-l">' + esc(T.waNum) + '<input name="wa" class="input" dir="ltr" value="' + esc(c.wa) + '"' + RO + '></label><label class="hx-l">' + esc(T.btnLabel) + '<input name="label" class="input" dir="auto" value="' + esc(c.label) + '"' + RO + '></label><label class="hx-l">' + esc(T.color) + '<input type="color" name="color" class="input !h-10 !p-1" value="' + esc(c.color) + '"' + RO + '></label></div>' +
      '<div class="hx-site"><span>' + esc(T.yourSite) + '</span><i data-wab style="background:' + esc(c.color) + '">💬 <b>' + esc(c.label || T.chatUs) + '</b></i></div>' + (X.manage ? '<button class="btn-primary w-full">' + esc(T.save) + '</button>' : '') + '</form>';
    var fm = body.querySelector('form'), wab = fm.querySelector('[data-wab]');
    fm.addEventListener('input', function () { wab.style.background = fm.color.value; wab.querySelector('b').textContent = fm.label.value || T.chatUs; wab.style.display = fm.btn.checked ? '' : 'none'; });
    fm.querySelector('[data-cp]').onclick = function () { if (navigator.clipboard) navigator.clipboard.writeText(X.trackJs); toast(T.copied); };
    fm.addEventListener('submit', function (e) { e.preventDefault(); save(fm.querySelector('.btn-primary'), api({ action: 'track_save', site: fm.site.value, hot: chipVals(fm.querySelector('[data-chips]')).join(', '), btn: fm.btn.checked ? 1 : '', wa: fm.wa.value, label: fm.label.value, color: fm.color.value }), function () { X.site = fm.site.value; }); }); };
  /* ---------- خطط المتابعة ---------- */
  var CH = { email: ['mail', '#2563eb'], wa: ['chat', '#16a34a'], call: ['phone', '#ea580c'], task: ['check', '#7c3aed'] };
  function rulesForm() { var r = X.rules || {};
    return '<form class="hx-card space-y-4" data-rules data-no-ajax>' + sw('hours', !!+r.hours, T.rHours, T.rHoursD) + '<div class="flex flex-wrap gap-1">' + DAYS.map(function (d, i) { return '<label class="pd-chipr"><input type="checkbox" name="days[]" value="' + i + '"' + ((r.days || []).indexOf(i) >= 0 ? ' checked' : '') + RO + '><span>' + esc(d) + '</span></label>'; }).join('') + '</div>' +
      '<div class="grid grid-cols-2 gap-2"><label class="hx-l">' + esc(T.from) + '<input type="time" name="from" class="input" value="' + esc(r.from || '09:00') + '"' + RO + '></label><label class="hx-l">' + esc(T.to) + '<input type="time" name="to" class="input" value="' + esc(r.to || '18:00') + '"' + RO + '></label></div>' +
      sw('stop_reply', !!+r.stop_reply, T.rStop, T.rStopD) + '<div><b class="hx-l">' + esc(T.rNa) + '</b><div class="flex items-center gap-2 mt-1"><input type="number" min="0" max="10" name="na_n" class="input !mt-0 !w-20" value="' + (+r.na_n || 0) + '"' + RO + '><span class="text-xs text-ink-500">' + esc(T.rTimes) + '</span><select name="na_plan" class="input !mt-0 flex-1"' + RO + '><option value="0">' + esc(T.none) + '</option>' + (X.plans || []).map(function (p) { return '<option value="' + p.id + '"' + (+r.na_plan === +p.id ? ' selected' : '') + '>' + esc(p.name) + '</option>'; }).join('') + '</select></div></div>' +
      (X.manage ? '<button class="btn-primary w-full">' + esc(T.save) + '</button>' : '') + '</form>'; }
  document.addEventListener('submit', function (e) { var fm = e.target.closest('[data-rules]'); if (!fm) return; e.preventDefault(); var fd = new FormData(fm); fd.append('_csrf', X.csrf); fd.append('action', 'plans');
    var b = fm.querySelector('.btn-primary'); save(b, fetch(X.setUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return { ok: r.ok }; }), function () { X.rules = { hours: fm.hours.checked ? 1 : 0, days: fd.getAll('days[]').map(Number), from: fm.from.value, to: fm.to.value, stop_reply: fm.stop_reply.checked ? 1 : 0, na_n: +fm.na_n.value, na_plan: +fm.na_plan.value }; }); });
  var planTab = 0;
  B.plans = function (body) { var plans = JSON.parse(JSON.stringify(X.plans || [])), cur = plans.length ? 0 : -1;
    function paint() { var p = plans[cur];
      var top = '<div class="hx-tabs hx-tabs-lg mb-3"><button type="button" data-pt2="0" class="' + (planTab ? '' : 'on') + '">' + esc(T.tPlans) + '</button><button type="button" data-pt2="1" class="' + (planTab ? 'on' : '') + '">' + esc(T.tRules) + '</button></div>';
      if (planTab) { body.innerHTML = top + rulesForm(); return; }
      body.innerHTML = top + '<div class="hx-card"><div class="hx-tabs">' + plans.map(function (x, i) { return '<button type="button" data-p="' + i + '" class="' + (i === cur ? 'on' : '') + '">' + esc(x.name || T.untitled) + ' <i>' + (x.steps || []).length + '</i></button>'; }).join('') + (X.manage ? '<button type="button" data-new>+ ' + esc(T.newPlan) + '</button>' : '') + '</div>' +
        (p ? '<div class="mt-4 space-y-3"><input class="input text-lg font-bold" data-name value="' + esc(p.name) + '" placeholder="' + esc(T.planName) + '"' + RO + '>' +
          '<ol class="hx-tl">' + (p.steps || []).map(function (s, i) { var c = CH[s.ch] || CH.task; return '<li style="--c:' + c[1] + '"><span class="hx-dot">' + ic(c[0]) + '</span><div class="hx-step"><div class="flex flex-wrap items-center gap-2"><span class="hx-day">' + esc(T.dayN) + ' <input type="number" min="0" max="365" data-k="day" data-i="' + i + '" value="' + (+s.day || 0) + '"' + RO + '></span>' +
            '<div class="abx-seg !mt-0">' + Object.keys(CH).map(function (k) { return '<button type="button" data-ch="' + k + '" data-i="' + i + '" class="' + (s.ch === k ? 'on' : '') + '">' + esc((X.chans[k] || [k, k])[1] || k) + '</button>'; }).join('') + '</div>' + (X.manage ? '<button type="button" class="ms-auto text-ink-400 hover:text-red-600" data-del="' + i + '">' + ic('trash') + '</button>' : '') + '</div>' +
            (s.ch === 'email' ? '<input class="input text-sm mt-2" data-k="subject" data-i="' + i + '" placeholder="' + esc(T.subject) + '" value="' + esc(s.subject || '') + '"' + RO + '>' : '') + '<textarea class="input text-sm mt-2" rows="2" dir="auto" data-k="text" data-i="' + i + '" placeholder="' + esc(T.stepText) + '"' + RO + '>' + esc(s.text || '') + '</textarea></div></li>'; }).join('') +
          (X.manage ? '<li class="add"><span class="hx-dot">+</span><button type="button" class="btn-ghost !text-xs" data-add>' + esc(T.addStep) + '</button></li>' : '') + '</ol>' +
          '<p class="hx-d">' + esc(T.vars) + ' <code>{name}</code> <code>{company}</code> <code>{me}</code> <code>{book}</code></p>' + (X.manage ? '<div class="flex gap-2"><button type="button" class="btn-primary flex-1" data-save>' + esc(T.save) + '</button><button type="button" class="btn-ghost !text-red-600" data-rmplan>' + esc(T.delPlan) + '</button></div>' : '') + '</div>'
          : '<p class="hx-empty">' + esc(T.noPlans) + '</p>') + '</div>'; }
    paint();
    body.addEventListener('click', function (e) { var b;
      if ((b = e.target.closest('[data-pt2]'))) { planTab = +b.dataset.pt2; return paint(); }
      if ((b = e.target.closest('[data-p]'))) { cur = +b.dataset.p; return paint(); }
      if (e.target.closest('[data-new]')) { plans.push({ id: 0, name: '', steps: [{ day: 0, ch: 'wa', text: T.s1 }, { day: 2, ch: 'call', text: T.s2 }, { day: 5, ch: 'email', subject: T.s3s, text: T.s3 }] }); cur = plans.length - 1; return paint(); }
      if ((b = e.target.closest('[data-ch]'))) { plans[cur].steps[+b.dataset.i].ch = b.dataset.ch; return paint(); }
      if ((b = e.target.closest('[data-del]'))) { plans[cur].steps.splice(+b.dataset.del, 1); return paint(); }
      if (e.target.closest('[data-add]')) { var st = plans[cur].steps, last = st[st.length - 1]; st.push({ day: last ? (+last.day || 0) + 2 : 0, ch: 'task', text: '' }); return paint(); }
      if (e.target.closest('[data-rmplan]')) { if (!confirm(T.delPlanQ)) return; plans.splice(cur, 1); cur = plans.length ? 0 : -1; return persist(e.target.closest('[data-rmplan]')); }
      if ((b = e.target.closest('[data-save]'))) persist(b); });
    body.addEventListener('input', function (e) { var el = e.target; if (el.matches('[data-name]')) plans[cur].name = el.value; if (el.dataset.k) plans[cur].steps[+el.dataset.i][el.dataset.k] = el.dataset.k === 'day' ? +el.value : el.value; });
    function persist(btn) { save(btn, api({ action: 'plans_save', plans: JSON.stringify(plans) }), function (j) { if (j.plans) { plans = j.plans; X.plans = j.plans; if (cur >= plans.length) cur = plans.length - 1; } paint(); }); } };
  /* ---------- رسائل واتساب الجاهزة ---------- */
  B.tpl = function (body) { var list = JSON.parse(JSON.stringify(X.tpl || [])), cur = 0;
    function fill(t) { return String(t || '').replace(/\{name\}/g, T.sampleName).replace(/\{company\}/g, T.sampleCo).replace(/\{me\}/g, T.sampleMe).replace(/\{book\}/g, 'https://…/book'); }
    function paint() { var x = list[cur];
      body.innerHTML = '<div class="grid md:grid-cols-[minmax(0,1fr)_260px] gap-4"><div class="hx-card space-y-3"><div class="hx-tabs">' + list.map(function (t, i) { return '<button type="button" data-t="' + i + '" class="' + (i === cur ? 'on' : '') + '">' + esc(t.n || T.untitled) + '</button>'; }).join('') + (X.manage && list.length < 10 ? '<button type="button" data-new>+</button>' : '') + '</div>' +
        (x ? '<input class="input font-bold" data-n value="' + esc(x.n) + '" maxlength="40" placeholder="' + esc(T.tplName) + '"' + RO + '><textarea class="input" rows="6" dir="auto" data-b maxlength="1000"' + RO + '>' + esc(x.b) + '</textarea><div class="flex flex-wrap gap-1">' + ['{name}', '{company}', '{me}', '{book}'].map(function (v) { return '<button type="button" class="pd-var" data-v="' + v + '">' + v + '</button>'; }).join('') + '</div>' +
          (X.manage ? '<div class="flex gap-2"><button type="button" class="btn-primary flex-1" data-save>' + esc(T.save) + '</button><button type="button" class="btn-ghost !text-red-600" data-rm>' + ic('trash') + '</button></div>' : '') : '<p class="hx-empty">' + esc(T.noTpl) + '</p>') + '</div>' +
        '<div class="hx-wa"><div class="hx-wa-top">' + ic('chat') + ' ' + esc(T.sampleName) + '</div><div class="hx-wa-body"><p class="hx-bub" dir="auto">' + esc(fill(x ? x.b : '')) + '</p></div></div></div>'; }
    paint();
    body.addEventListener('input', function (e) { var x = list[cur]; if (!x) return; if (e.target.matches('[data-n]')) x.n = e.target.value; if (e.target.matches('[data-b]')) { x.b = e.target.value; body.querySelector('.hx-bub').textContent = fill(x.b); } });
    body.addEventListener('click', function (e) { var b;
      if ((b = e.target.closest('[data-t]'))) { cur = +b.dataset.t; return paint(); }
      if (e.target.closest('[data-new]')) { list.push({ n: '', b: T.tplNew }); cur = list.length - 1; return paint(); }
      if ((b = e.target.closest('[data-v]'))) { var ta = body.querySelector('[data-b]'), s = ta.selectionStart || ta.value.length; ta.value = ta.value.slice(0, s) + b.dataset.v + ta.value.slice(ta.selectionEnd || s); ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus(); return; }
      if (e.target.closest('[data-rm]') && !e.target.closest('.hx-chip')) { list.splice(cur, 1); cur = 0; persist(e.target.closest('[data-rm]')); return; }
      if ((b = e.target.closest('[data-save]'))) persist(b); });
    function persist(btn) { save(btn, api({ action: 'tpl_save', tpl: JSON.stringify(list) }), function (j) { if (j.tpl) { list = j.tpl; X.tpl = j.tpl; } paint(); }); } };
  /* ---------- السرعة والتوزيع ---------- */
  B.sla = function (body) { var s = X.sla, rules = (X.routes || []).slice(), F = T.routeF || {};
    var mem = function (v) { return Object.keys(X.members).map(function (k) { return '<option value="' + k + '"' + (+k === +v ? ' selected' : '') + '>' + esc(X.members[k]) + '</option>'; }).join(''); };
    function valIn(r, i) { var a = ' data-r="value" data-i="' + i + '"' + RO;
      if (r.field === 'country') { var cc = X.ccs || [], v = r.value || X.wsCc; var top = cc.filter(function (c) { return c[0] === X.wsCc; });
        return '<select' + a + '>' + top.concat(cc.filter(function (c) { return c[0] !== X.wsCc; })).map(function (c) { return '<option value="' + c[0] + '"' + (c[0] === v ? ' selected' : '') + '>' + esc(c[1]) + '</option>'; }).join('') + '</select>'; }
      if (r.field === 'source') { var sr = X.srcs || {}, v2 = r.value || Object.keys(sr)[0]; return '<select' + a + '>' + Object.keys(sr).map(function (k) { return '<option value="' + esc(k) + '"' + (k === v2 ? ' selected' : '') + '>' + esc(sr[k]) + '</option>'; }).join('') + '</select>'; }
      return '<input' + a + ' value="' + esc(r.value) + '" dir="' + (r.field === 'phone' ? 'ltr' : 'auto') + '" placeholder="' + esc(r.field === 'phone' ? '+966' : '') + '">'; }
    function rulesHtml() { return rules.map(function (r, i) { return '<div class="hx-rule">' + esc(T.ifW) + ' <select data-r="field" data-i="' + i + '"' + RO + '>' + Object.keys(F).map(function (k) { return '<option value="' + k + '"' + (r.field === k ? ' selected' : '') + '>' + esc(F[k]) + '</option>'; }).join('') + '</select> = ' + valIn(r, i) + ' ← <select data-r="owner" data-i="' + i + '"' + RO + '>' + mem(r.owner) + '</select>' + (X.manage ? '<button type="button" data-rr="' + i + '">' + ic('trash') + '</button>' : '') + '</div>'; }).join(''); }
    body.innerHTML = '<form class="space-y-4" data-no-ajax><div class="hx-card"><b class="hx-l">' + esc(T.slaT) + '</b><p class="hx-d">' + esc(T.slaD) + '</p><div class="hx-range"><input type="range" name="mins" min="5" max="1440" step="5" value="' + (+s.mins || 60) + '"' + RO + '><b data-mv></b></div>' +
      '<div class="abx-quick">' + [15, 30, 60, 240, 1440].map(function (m) { return '<button type="button" data-m="' + m + '">' + esc(m >= 60 ? f(T.hrs, m / 60) : f(T.mins, m)) + '</button>'; }).join('') + '</div>' +
      '<label class="hx-l mt-3">' + esc(T.moveT) + '<div class="flex items-center gap-2"><input type="number" name="move_hours" min="0" max="72" class="input !w-24" value="' + (+s.move_hours || 0) + '"' + RO + '><span class="text-xs text-ink-500">' + esc(T.moveD) + '</span></div></label></div>' +
      '<div class="hx-card"><b class="hx-l">' + esc(T.routeT) + '</b><p class="hx-d">' + esc(T.routeD) + '</p><div data-rules class="space-y-2">' + rulesHtml() + '</div>' + (X.manage ? '<button type="button" class="btn-ghost !text-xs mt-2" data-ar>+ ' + esc(T.addRule) + '</button>' : '') + '</div>' +
      '<div class="hx-card"><b class="hx-l">' + esc(T.probT) + '</b><p class="hx-d">' + esc(T.probD) + '</p>' + ['new', 'contacted', 'qualified', 'proposal'].map(function (k) { return '<div class="hx-prob"><span>' + esc(X.stages[k] || k) + '</span><input type="range" min="0" max="100" name="prob[' + k + ']" value="' + (X.probs[k] || 0) + '"' + RO + '><b>' + (X.probs[k] || 0) + '%</b></div>'; }).join('') + '</div>' +
      (X.manage ? '<button class="btn-primary w-full">' + esc(T.save) + '</button>' : '') + '</form>';
    var fm = body.querySelector('form'), mv = fm.querySelector('[data-mv]'), fmt = function (m) { m = +m; return m >= 60 ? f(T.hrs, Math.round(m / 6) / 10) : f(T.mins, m); };
    var p = function () { mv.textContent = fmt(fm.mins.value); fm.querySelectorAll('.hx-prob').forEach(function (r) { r.querySelector('b').textContent = r.querySelector('input').value + '%'; }); }; p();
    fm.addEventListener('input', function (e) { p(); var el = e.target; if (el.dataset.r) rules[+el.dataset.i][el.dataset.r] = el.value; });
    fm.addEventListener('change', function (e) { var el = e.target; if (!el.dataset.r) return; var r = rules[+el.dataset.i]; r[el.dataset.r] = el.value;
      if (el.dataset.r === 'field') { r.value = ''; var box = fm.querySelector('[data-rules]'); box.innerHTML = rulesHtml(); var nv = box.querySelector('[data-r="value"][data-i="' + el.dataset.i + '"]'); if (nv.tagName === 'SELECT') r.value = nv.value; else nv.focus(); } });
    rules.forEach(function (r) { if (!r.value && r.field === 'country') r.value = X.wsCc; });
    fm.addEventListener('click', function (e) { var b; if ((b = e.target.closest('[data-m]'))) { fm.mins.value = b.dataset.m; p(); }
      if (e.target.closest('[data-ar]')) { rules.push({ field: 'source', value: Object.keys(X.srcs || {})[0] || '', owner: Object.keys(X.members)[0] || 0 }); fm.querySelector('[data-rules]').innerHTML = rulesHtml(); }
      if ((b = e.target.closest('[data-rr]'))) { rules.splice(+b.dataset.rr, 1); fm.querySelector('[data-rules]').innerHTML = rulesHtml(); } });
    fm.addEventListener('submit', function (e) { e.preventDefault(); var d = { action: 'sla_save', mins: fm.mins.value, move_hours: fm.move_hours.value };
      ['new', 'contacted', 'qualified', 'proposal'].forEach(function (k) { d['prob[' + k + ']'] = fm.elements['prob[' + k + ']'].value; });
      rules.forEach(function (r, i) { d['rule[' + i + '][field]'] = r.field; d['rule[' + i + '][value]'] = r.value; d['rule[' + i + '][owner]'] = r.owner; });
      save(fm.querySelector('.btn-primary'), api(d)); }); };

  /* ---------- تحسين الأدوات الموجودة ---------- */
  var E = window.HubE = {};
  E.links = function (sec) { if (sec.dataset.hx) return; sec.dataset.hx = 1; var rows = sec.querySelectorAll('.rounded-xl.border'); var L = [X.links.form, X.links.call, X.links.book];
    rows.forEach(function (r, i) { r.classList.add('hx-link'); var q = document.createElement('span'); q.className = 'hx-qr'; r.prepend(q); qr(q, L[i] || ''); var wa = document.createElement('a'); wa.className = 'btn-ghost !py-1 !text-xs'; wa.target = '_blank'; wa.href = 'https://wa.me/?text=' + encodeURIComponent(L[i] || ''); wa.innerHTML = ic('chat') + ' ' + esc(T.share); r.appendChild(wa); });
    var fc = X.form || {}, bc = X.book || {};
    sec.insertAdjacentHTML('beforeend', '<form class="hx-card mt-4 space-y-3" data-ff data-no-ajax><b class="hx-l">' + esc(T.formT) + '</b>' + sw('on', X.formOn, T.formOn) + '<div class="grid grid-cols-[1fr_70px] gap-2"><label class="hx-l">' + esc(T.fTitle) + '<input name="title" class="input" dir="auto" value="' + esc(fc.title || '') + '"' + RO + '></label><label class="hx-l">' + esc(T.color) + '<input type="color" name="color" class="input !h-10 !p-1" value="' + esc(fc.color || '#4f46e5') + '"' + RO + '></label></div>' +
      '<label class="hx-l">' + esc(T.thanks) + '<input name="thanks" class="input" dir="auto" value="' + esc(fc.thanks || '') + '"' + RO + '></label><div class="flex flex-wrap gap-4">' + sw('company', !!+fc.company, T.askCo) + sw('message', !!+fc.message, T.askMsg) + '</div>' + (X.manage ? '<button class="btn-primary w-full">' + esc(T.save) + '</button>' : '') + '</form>' +
      '<form class="hx-card mt-4 space-y-3" data-bf data-no-ajax><b class="hx-l">' + esc(T.bookT) + '</b>' + sw('on', X.bookOn, T.bookOn) + '<label class="hx-l">' + esc(T.fTitle) + '<input name="title" class="input" dir="auto" value="' + esc(bc.title || '') + '"' + RO + '></label>' +
      '<div class="flex flex-wrap gap-1">' + DAYS.map(function (d, i) { return '<label class="pd-chipr"><input type="checkbox" name="days" value="' + i + '"' + ((bc.days || []).indexOf(i) >= 0 ? ' checked' : '') + RO + '><span>' + esc(d) + '</span></label>'; }).join('') + '</div>' +
      '<div class="grid grid-cols-2 sm:grid-cols-5 gap-2"><label class="hx-l">' + esc(T.from) + '<input type="time" name="from" class="input" value="' + esc(bc.from) + '"' + RO + '></label><label class="hx-l">' + esc(T.to) + '<input type="time" name="to" class="input" value="' + esc(bc.to) + '"' + RO + '></label><label class="hx-l">' + esc(T.len) + '<input type="number" name="len" min="15" max="240" class="input" value="' + esc(bc.len) + '"' + RO + '></label><label class="hx-l">' + esc(T.ahead) + '<input type="number" name="ahead" min="1" max="60" class="input" value="' + esc(bc.ahead) + '"' + RO + '></label><label class="hx-l">' + esc(T.notice) + '<input type="number" name="notice" min="0" max="72" class="input" value="' + esc(bc.notice) + '"' + RO + '></label></div>' +
      '<div class="hx-slots" data-slots></div>' + (X.manage ? '<button class="btn-primary w-full">' + esc(T.save) + '</button>' : '') + '</form>');
    var ff = sec.querySelector('[data-ff]'), bf = sec.querySelector('[data-bf]');
    var linksBox = sec.querySelector('.space-y-2') || rows[0].parentNode, qsec = document.getElementById('qualify'), qp = document.createElement('div'); qp.className = 'hx-card';
    if (qsec) { var qf = qsec.querySelector('form'), qd = qsec.querySelector('p'); if (qd) qp.appendChild(qd); if (qf) qp.appendChild(qf); E.qualify(qp); }
    var panes = [[T.tLinks, linksBox], [T.tForm, ff], [T.tQual, qp], [T.tBook, bf]], bar = document.createElement('div'); bar.className = 'hx-tabs hx-tabs-lg mb-3';
    bar.innerHTML = panes.map(function (x, i) { return '<button type="button" data-pane="' + i + '" class="' + (i ? '' : 'on') + '">' + esc(x[0]) + '</button>'; }).join('');
    sec.prepend(bar); panes.forEach(function (x, i) { if (x[1] !== linksBox) sec.appendChild(x[1]); x[1].classList.add('hx-pane'); x[1].classList.toggle('hidden', i > 0); x[1].classList.remove('mt-4'); });
    var intro = sec.querySelector(':scope > p'); if (intro) linksBox.prepend(intro);
    var goPane = function (i) { bar.querySelectorAll('button').forEach(function (b) { b.classList.toggle('on', +b.dataset.pane === i); }); panes.forEach(function (x, k) { x[1].classList.toggle('hidden', k !== i); }); };
    bar.addEventListener('click', function (e) { var b = e.target.closest('[data-pane]'); if (b) goPane(+b.dataset.pane); });
    if (!X.bookOn && rows[2]) { rows[2].insertAdjacentHTML('beforeend', '<button type="button" class="hx-warn" data-gobook>⚠ ' + esc(T.bookOff) + '</button>'); rows[2].querySelector('[data-gobook]').onclick = function () { goPane(3); bf.classList.add('ab-flash'); var o = bf.querySelector('[name=on]'); if (o && !o.checked) o.checked = true; }; }
    var slots = function () { var a = bf.from.value.split(':'), b = bf.to.value.split(':'), s = +a[0] * 60 + +a[1], e2 = +b[0] * 60 + +b[1], L2 = +bf.len.value || 30, o = [];
      for (var m = s; m + L2 <= e2 && o.length < 12; m += L2) o.push(('0' + Math.floor(m / 60)).slice(-2) + ':' + ('0' + m % 60).slice(-2)); bf.querySelector('[data-slots]').innerHTML = '<small>' + esc(T.slotsP) + '</small>' + o.map(function (x) { return '<i>' + x + '</i>'; }).join(''); };
    bf.addEventListener('input', slots); slots();
    ff.addEventListener('submit', function (e) { e.preventDefault(); save(ff.querySelector('.btn-primary'), api({ action: 'form_save', on: ff.on.checked ? 1 : '', title: ff.title.value, thanks: ff.thanks.value, color: ff.color.value, company: ff.company.checked ? 1 : '', message: ff.message.checked ? 1 : '' })); });
    bf.addEventListener('submit', function (e) { e.preventDefault(); var d = { action: 'book_save', on: bf.on.checked ? 1 : '', title: bf.title.value, from: bf.from.value, to: bf.to.value, len: bf.len.value, ahead: bf.ahead.value, notice: bf.notice.value, 'days[]': [].filter.call(bf.querySelectorAll('[name=days]'), function (c) { return c.checked; }).map(function (c) { return c.value; }) };
      save(bf.querySelector('.btn-primary'), api(d), function () { X.bookOn = bf.on.checked; var w = sec.querySelector('[data-gobook]'); if (w && X.bookOn) w.remove(); }); }); };
  E.qualify = function (sec) { if (sec.dataset.hx) return; sec.dataset.hx = 1; var fm = sec.querySelector('form'); if (!fm) return; var cb = fm.querySelector('[name=qualify]'), bud = fm.querySelector('[name=budgets]'); if (!cb || !bud) return;
    var lb = cb.closest('label'); lb.outerHTML = sw('qualify', cb.checked, T.qOn, T.qOnD); bud.type = 'hidden';
    bud.insertAdjacentHTML('afterend', chips('bud', X.budgets, T.addBudget)); var box = fm.querySelector('[data-chips]');
    fm.insertAdjacentHTML('beforeend', '<div class="hx-form-prev"><small>' + esc(T.preview) + '</small><b>' + esc(T.qBudget) + '</b><div data-bp></div><b class="mt-2">' + esc(T.qWhen) + '</b><div>' + (T.whens || []).map(function (w) { return '<i>' + esc(w) + '</i>'; }).join('') + '</div></div>');
    var p = function () { bud.value = chipVals(box).join('، '); fm.querySelector('[data-bp]').innerHTML = chipVals(box).map(function (w) { return '<i>' + esc(w) + '</i>'; }).join(''); fm.querySelector('.hx-form-prev').style.opacity = fm.querySelector('[name=qualify]').checked ? 1 : .4; };
    box.addEventListener('change', p); fm.addEventListener('change', p); p(); };
  E.wa = function (sec) { if (sec.dataset.hx) return; sec.dataset.hx = 1; var msg = sec.querySelector('#wa-msg'), ph = sec.querySelector('#wa-ph'); if (!msg) return;
    msg.parentNode.insertAdjacentHTML('afterend', '<div class="hx-wa mt-3"><div class="hx-wa-top">' + ic('chat') + ' <span data-pn>+…</span></div><div class="hx-wa-body"><p class="hx-bub out" dir="auto"></p></div></div>');
    var p = function () { sec.querySelector('[data-pn]').textContent = ph.value ? '+' + ph.value.replace(/\D/g, '') : '+…'; sec.querySelector('.hx-bub').textContent = msg.value + ' #Ab12Cd3'; }; msg.addEventListener('input', p); ph.addEventListener('input', p); p(); };
  E.lp = function (sec) { if (sec.dataset.hx) return; sec.dataset.hx = 1; var fm = sec.querySelector('#lp-form'); if (!fm) return;
    var pv = document.createElement('div'); pv.className = 'hx-lp md:col-span-2'; fm.appendChild(pv);
    var p = function () { var c = fm.color.value || '#4f46e5'; pv.innerHTML = '<small>' + esc(T.preview) + '</small><div class="hx-lp-pg"><div class="hx-lp-img"' + (fm.img.value ? ' style="background-image:url(' + esc(fm.img.value) + ')"' : '') + '></div><b dir="auto">' + esc(fm.title.value || T.lpTitle) + '</b><p dir="auto">' + esc(fm.sub.value) + '</p><i style="background:' + esc(c) + '">' + esc(fm.cta.value || T.lpCta) + '</i></div>'; };
    fm.addEventListener('input', p); new MutationObserver(p).observe(fm, { attributes: true, attributeFilter: ['class'] }); p(); };
  E.card = function (sec) { if (sec.dataset.hx) return; sec.dataset.hx = 1; var inp = sec.querySelector('#card-f'); if (!inp) return; var lb = inp.closest('label'); lb.className = 'hx-drop'; lb.insertAdjacentHTML('afterbegin', '<span class="hx-drop-ic">' + ic('image', 'w-8 h-8') + '</span><b>' + esc(T.dropT) + '</b><small>' + esc(T.dropD) + '</small>');
    ['dragover', 'dragleave', 'drop'].forEach(function (ev) { lb.addEventListener(ev, function (e) { e.preventDefault(); lb.classList.toggle('over', ev === 'dragover'); if (ev === 'drop' && e.dataTransfer.files[0]) { var dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]); inp.files = dt.files; inp.dispatchEvent(new Event('change', { bubbles: true })); } }); }); };
  E.sheet = function (sec) { if (sec.dataset.hx) return; sec.dataset.hx = 1; var ol = sec.querySelector('ol, .text-\\[11px\\]'); var on = !!(X.sheet);
    var h = sec.querySelector('p'); if (h) h.insertAdjacentHTML('afterend', '<ol class="hx-steps"><li class="on"><b>1</b>' + esc(T.sh1) + '</li><li><b>2</b>' + esc(T.sh2) + '</li><li><b>3</b>' + esc(T.sh3) + '</li></ol>'); };
  E.ref = function (sec) { if (sec.dataset.hx) return; sec.dataset.hx = 1; var s = sec.querySelector('#ref-l'); if (!s) return; var dl = document.createElement('input'); dl.className = 'input !mt-0 text-sm mb-2'; dl.placeholder = T.findLead; s.before(dl);
    dl.addEventListener('input', function () { var q = dl.value.trim().toLowerCase(), first = null; [].forEach.call(s.options, function (o) { var hit = !q || o.text.toLowerCase().indexOf(q) >= 0 || !o.value; o.hidden = !hit; if (hit && o.value && !first) first = o; }); if (first) s.value = first.value; }); };

  /* ---------- برنامج الإحالة ---------- */
  B.ref = function (body) { var D = X.ref || {}, L = D.T || {}, C = D.cfg || {}, paid = D.paid || {}, A = D.amb || [], st = {}, tab = 'amb', flt = 'all', q = '', sent = {};
    var SG = D.stages || {}; Object.keys(SG).forEach(function (k) { var x = SG[k]; st[k] = typeof x === 'string' ? x : (x.label || x.name || x[0] || k); });
    function capi(d) { var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('ajax', '1'); Object.keys(d).forEach(function (k) { fd.append(k, d[k]); });
      return fetch(X.self, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
    function earned(a) { return C.when === 'lead' ? a.n : a.w; }
    function due(a) { return Math.max(0, earned(a) - (+paid[a.id] || 0)); }
    function msg(a) { return String(C.msg || '').replace(/\{name\}/g, (a.name || '').split(/\s+/)[0]).replace(/\{link\}/g, a.link).replace(/\{give\}/g, C.give || '').replace(/\{get\}/g, C.get || ''); }
    function pct(o) { return o.a ? Math.round(o.w / o.a * 100) : 0; }
    var rev = A.reduce(function (s, a) { return s + a.v; }, 0), p1 = pct(D.all || {}), p2 = pct(D.oth || {});
    function kpis() { return '<div class="grid grid-cols-2 lg:grid-cols-4 gap-2 mb-3">' + [[(D.all || {}).a || 0, L.k1, '#db2777'], [p1 + '%', L.k2, '#16a34a'], [p2 + '%', L.k3, '#64748b'], [Math.round(rev).toLocaleString('en-US'), L.k4, '#d97706']].map(function (k) {
      return '<div class="cap-k" style="--c:' + k[2] + '"><b>' + esc(k[0]) + '</b><small>' + esc(k[1]) + '</small></div>'; }).join('') + '</div>' + (p2 && p1 > p2 ? '<p class="hx-note mb-3">🚀 ' + esc(f(L.better, (p1 / p2).toFixed(1))) + '</p>' : ''); }
    function row(a) { var d = due(a);
      return '<div class="hx-amb" data-id="' + a.id + '"><span class="hx-av">' + esc((a.name || '?').charAt(0)) + '</span><div class="min-w-0 flex-1"><b class="truncate block">' + esc(a.name) + '</b><small>' + esc(st[a.stage] || a.stage) + (a.co ? ' · ' + esc(a.co) : '') + '</small>' +
        '<div class="hx-amb-m"><i>' + esc(f(L.ref, a.n)) + '</i><i>' + esc(f(L.won, a.w)) + '</i>' + (a.v ? '<i>' + Math.round(a.v).toLocaleString('en-US') + '</i>' : '') + (d ? '<em>🎁 ' + esc(L.due) + ' ×' + d + '</em>' : '') + (sent[a.id] ? '<i>✓</i>' : '') + '</div></div>' +
        '<div class="flex gap-1 shrink-0">' + (d ? '<button type="button" class="hx-paid" data-paid>🎁 ' + esc(L.gave) + '</button>' : '') + '<button type="button" class="btn-ghost !py-1.5 !px-2" data-cp title="' + esc(L.copy) + '">' + ic('copy') + '</button>' +
        (a.wa ? '<a class="btn-primary !py-1.5 !text-xs" data-wa target="_blank" rel="noopener" href="https://wa.me/' + esc(a.wa) + '?text=' + encodeURIComponent(msg(a)) + '">' + esc(L.send) + '</a>' : '<span class="text-xs text-ink-400 self-center">' + esc(L.noWa) + '</span>') + '</div></div>'; }
    function list() { var r = A.filter(function (a) { return (!q || (a.name + ' ' + a.co).toLowerCase().indexOf(q) >= 0) && (flt === 'all' || (flt === 'due' ? due(a) > 0 : !sent[a.id] && !a.n)); });
      return r.length ? r.map(row).join('') : '<p class="text-sm text-ink-500 p-4">' + esc(L.none) + '</p>'; }
    function amb() { return kpis() + '<div class="flex flex-wrap items-center gap-2 mb-2"><label class="mk-search !w-56 !h-9"><input type="search" data-q placeholder="' + esc(L.find) + '" value="' + esc(q) + '"></label>' +
      '<div class="hx-seg">' + [['all', L.fAll], ['due', L.fDue], ['new', L.fNew]].map(function (x) { return '<button type="button" data-f="' + x[0] + '"' + (flt === x[0] ? ' class="on"' : '') + '>' + esc(x[1]) + '</button>'; }).join('') + '</div>' +
      '<button type="button" class="btn-ghost !text-xs ms-auto whitespace-nowrap" data-queue>' + esc(L.queue) + '</button></div><div class="hx-ambs" data-list>' + list() + '</div>' +
      ((D.feed || []).length ? '<b class="block text-sm mt-4 mb-2">' + esc(L.feed) + '</b><ul class="hx-feed">' + D.feed.map(function (x) { return '<li><b>' + esc(x.n) + '</b> <small>' + esc(f(L.by, x.by)) + ' · ' + esc(st[x.st] || x.st) + ' · ' + esc(x.at) + '</small></li>'; }).join('') + '</ul>' : ''); }
    function set() { var ex = A[0] || { name: 'أحمد', link: 'https://…' };
      return '<form data-rf class="space-y-3">' + sw('on', +C.on, L.on, L.onS) + '<div class="grid sm:grid-cols-2 gap-3"><label class="block"><span class="label">' + esc(L.give) + '</span><input class="input" name="give" value="' + esc(C.give) + '" placeholder="' + esc(L.giveP) + '"></label>' +
        '<label class="block"><span class="label">' + esc(L.get) + '</span><input class="input" name="get" value="' + esc(C.get) + '" placeholder="' + esc(L.getP) + '"></label></div>' +
        '<div><span class="label">' + esc(L.when) + '</span><div class="hx-seg">' + [['lead', L.wLead], ['won', L.wWon]].map(function (x) { return '<label><input type="radio" name="when" value="' + x[0] + '"' + (C.when === x[0] ? ' checked' : '') + ' hidden><span>' + esc(x[1]) + '</span></label>'; }).join('') + '</div></div>' +
        '<label class="block"><span class="label">' + esc(L.msg) + ' <small class="text-ink-400">{name} {link} {give} {get}</small></span><textarea class="input" rows="4" name="msg">' + esc(C.msg) + '</textarea></label>' +
        '<div><span class="label">' + esc(L.prev) + '</span><div class="hx-bub out" data-pv style="white-space:pre-wrap">' + esc(msg(ex)) + '</div></div><button class="btn-primary">' + esc(L.save) + '</button></form>'; }
    function paint() { body.innerHTML = '<div class="hx-tabs hx-tabs-lg mb-3">' + [['amb', L.tAmb + ' (' + A.length + ')'], ['set', L.tSet]].map(function (x) { return '<button type="button" data-tb="' + x[0] + '"' + (tab === x[0] ? ' class="on"' : '') + '>' + esc(x[1]) + '</button>'; }).join('') + '</div><div data-pane>' + (tab === 'amb' ? amb() : set()) + '</div>'; }
    function relist() { var l = body.querySelector('[data-list]'); if (l) l.innerHTML = list(); }
    var qi = -1;
    function queue() { var r = A.filter(function (a) { return a.wa && !sent[a.id]; }); qi++; if (qi >= r.length || !r.length) { qi = -1; return toast(L.qDone); } var a = r[0]; sent[a.id] = 1; window.open('https://wa.me/' + a.wa + '?text=' + encodeURIComponent(msg(a)), '_blank'); relist(); }
    body.addEventListener('click', function (e) { var t = e.target, b;
      if ((b = t.closest('[data-tb]'))) { tab = b.dataset.tb; paint(); return; }
      if ((b = t.closest('[data-f]'))) { flt = b.dataset.f; body.querySelectorAll('[data-f]').forEach(function (x) { x.classList.toggle('on', x === b); }); relist(); return; }
      if (t.closest('[data-queue]')) { queue(); return; }
      var r = t.closest('[data-id]'); if (!r) return; var a = A.filter(function (x) { return x.id === +r.dataset.id; })[0];
      if (t.closest('[data-wa]')) { sent[a.id] = 1; setTimeout(relist, 50); }
      if (t.closest('[data-cp]')) { navigator.clipboard && navigator.clipboard.writeText(a.link); toast(L.copied); }
      if ((b = t.closest('[data-paid]'))) { var n = (+paid[a.id] || 0) + due(a); save(b, capi({ action: 'ref_paid', lead: a.id, n: n }), function (j) { paid[a.id] = j.n; relist(); }); } });
    body.addEventListener('input', function (e) { if (e.target.matches('[data-q]')) { q = e.target.value.trim().toLowerCase(); relist(); }
      var fm = e.target.closest('[data-rf]'); if (fm) { C = Object.assign({}, C, { give: fm.give.value, get: fm.get.value, msg: fm.msg.value }); fm.querySelector('[data-pv]').textContent = msg(A[0] || { name: 'أحمد', link: 'https://…' }); } });
    body.addEventListener('change', function (e) { var fm = e.target.closest('[data-rf]'); if (fm && e.target.name === 'when') C.when = e.target.value; });
    body.addEventListener('submit', function (e) { var fm = e.target.closest('[data-rf]'); if (!fm) return; e.preventDefault();
      var d = { action: 'ref_cfg', give: fm.give.value, get: fm.get.value, msg: fm.msg.value, when: (fm.querySelector('[name=when]:checked') || {}).value || 'won' }; if (fm.on.checked) d.on = 1;
      save(fm.querySelector('.btn-primary'), capi(d), function () { C = Object.assign(C, d, { on: d.on ? 1 : 0 }); }); });
    paint(); };
})();
