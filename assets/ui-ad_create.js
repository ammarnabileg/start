// حملة جديدة: معاينة حية + حاسبة ميزانية + ملخص + دول كشرائح + حماية من الخروج
(function () {
  var X = window.ADC || {}, form = document.getElementById('adcForm');
  if (!form || form.dataset.adcUx) return; form.dataset.adcUx = '1';
  var el = form.elements, esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var fmt = function (s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); };
  var val = function (n) { var f = el[n]; if (!f) return ''; if (f.length && !f.tagName) { for (var i = 0; i < f.length; i++) if (f[i].checked) return f[i].value; return ''; } return f.value; };

  // تخطيط: الفورم + عمود جانبي ثابت
  var grid = document.createElement('div'); grid.className = 'grid gap-5 lg:grid-cols-[minmax(0,1fr)_380px] items-start';
  form.parentNode.insertBefore(grid, form); grid.appendChild(form);
  var side = document.createElement('aside'); side.className = 'space-y-4 lg:sticky lg:top-4'; side.id = 'adcSide';
  side.innerHTML = '<div class="card p-4"><div class="text-xs font-semibold text-ink-500 mb-3">' + esc(X.preview) + '</div><div id="adcPrev"></div></div>'
    + '<div class="card p-4"><div class="text-xs font-semibold text-ink-500 mb-2">' + esc(X.budget) + '</div><div id="adcBud"></div></div>'
    + '<div class="card p-4"><div class="text-xs font-semibold text-ink-500 mb-2">' + esc(X.summary) + '</div><div id="adcSum" class="space-y-1.5 text-sm"></div></div>';
  grid.appendChild(side);

  // الدول كشرائح
  var cIn = document.getElementById('adcCountries'), cBox = document.createElement('div');
  if (cIn) {
    cBox.id = 'adcCChips'; cBox.className = 'flex flex-wrap gap-1.5 mt-2'; cIn.parentNode.insertBefore(cBox, cIn.nextSibling);
    var codes = function () { return cIn.value.split(/[\s,]+/).map(function (c) { return c.trim().toUpperCase(); }).filter(Boolean); };
    var drawC = function () {
      var have = codes(), sug = [X.myCountry].concat(X.nearby || []).filter(function (c, i, a) { return c && a.indexOf(c) === i && have.indexOf(c) < 0; });
      cBox.innerHTML = have.map(function (c) { return '<button type="button" data-crm="' + c + '" class="inline-flex items-center gap-1 rounded-full bg-brand-50 text-brand-700 text-xs px-2.5 py-1">' + esc((X.flags[c] || '') + ' ' + (X.cNames[c] || c)) + ' <span aria-hidden="true">×</span></button>'; }).join('')
        + sug.map(function (c) { return '<button type="button" data-cadd="' + c + '" class="inline-flex items-center gap-1 rounded-full border border-dashed border-ink-300 text-ink-600 text-xs px-2.5 py-1">+ ' + esc((X.flags[c] || '') + ' ' + (X.cNames[c] || c)) + '</button>'; }).join('');
    };
    cBox.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return; var h = codes();
      if (b.dataset.crm) h = h.filter(function (c) { return c !== b.dataset.crm; }); else if (h.indexOf(b.dataset.cadd) < 0) h.push(b.dataset.cadd);
      cIn.value = h.join(', '); cIn.dispatchEvent(new Event('input', { bubbles: true }));
    });
    cIn.addEventListener('input', drawC); drawC();
  }

  var objUrl = '';
  var fileIn = form.querySelector('input[type=file]');
  if (fileIn) fileIn.addEventListener('change', function () { if (objUrl) URL.revokeObjectURL(objUrl); objUrl = fileIn.files[0] ? URL.createObjectURL(fileIn.files[0]) : ''; draw(); });

  function money(n, cur) { try { return new Intl.NumberFormat(document.documentElement.lang || 'ar', { style: cur ? 'currency' : 'decimal', currency: cur || undefined, maximumFractionDigits: 2 }).format(n); } catch (e) { return n.toFixed(2) + ' ' + (cur || ''); } }

  function draw() {
    var tSel = el.target, opt = tSel && tSel.options[tSel.selectedIndex], parts = (val('target') || '').split('|'), cur = parts[2] || '';
    var accName = opt ? opt.textContent.split('·')[0].trim() : '—', mode = val('mode');
    // المعاينة
    var head = '<div class="flex items-center gap-2 mb-2"><div class="w-9 h-9 rounded-full bg-brand-100 grid place-items-center text-brand-700 font-bold">' + esc(accName.charAt(0)) + '</div><div><div class="text-sm font-semibold text-ink-900">' + esc(accName) + '</div><div class="text-[11px] text-ink-500">' + esc(X.sponsored) + '</div></div></div>';
    var foot = '<div class="flex justify-around border-t border-ink-100 mt-2 pt-2 text-xs text-ink-500"><span>' + esc(X.like) + '</span><span>' + esc(X.comment) + '</span><span>' + esc(X.share) + '</span></div>';
    var body;
    if (mode === 'boost') {
      var p = el.post, po = p && p.options[p.selectedIndex], txt = po && po.value ? po.textContent.split('·').slice(2).join('·').trim() : (val('post_manual') ? val('post_manual') : '');
      body = txt ? '<div class="text-sm text-ink-800 whitespace-pre-line">' + esc(txt) + '</div><div class="mt-2 text-[11px] text-brand-700">' + esc(X.boostHint) + '</div>' : '<div class="text-sm text-ink-400 py-6 text-center">' + esc(X.pickPost) + '</div>';
    } else {
      var img = objUrl || val('image_url'), link = val('link'), host = ''; try { host = link ? new URL(link).hostname : ''; } catch (e) {}
      body = '<div class="text-sm text-ink-800 whitespace-pre-line mb-2">' + (val('body') ? esc(val('body')) : '<span class="text-ink-400">' + esc(X.noText) + '</span>') + '</div>'
        + '<div class="-mx-4 aspect-[1.91/1] bg-ink-100 overflow-hidden">' + (img ? '<img src="' + esc(img) + '" alt="" class="w-full h-full object-cover" onerror="this.remove()">' : '') + '</div>'
        + '<div class="-mx-4 px-4 py-2 bg-ink-50 flex items-center gap-2"><div class="min-w-0 flex-1"><div class="text-[11px] text-ink-500 uppercase truncate" dir="ltr">' + esc(host) + '</div><div class="text-sm font-semibold text-ink-900 truncate">' + esc(val('headline') || X.noHead) + '</div></div>'
        + '<span class="shrink-0 rounded-md bg-ink-200 text-ink-800 text-xs font-semibold px-3 py-1.5">' + esc((X.ctas || {})[val('cta')] || '') + '</span></div>';
    }
    var pf = window.adcPrevFmt || 0, prevEl = document.getElementById('adcPrev');
    if (pf === 1 && mode !== 'boost') { // ستوري 9:16
      var simg = objUrl || val('image_url');
      prevEl.innerHTML = '<div class="relative mx-auto w-56 aspect-[9/16] rounded-2xl overflow-hidden bg-ink-800 text-white">' + (simg ? '<img src="' + esc(simg) + '" alt="" class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">' : '')
        + '<div class="absolute inset-x-0 top-0 p-3 bg-gradient-to-b from-black/60 to-transparent flex items-center gap-2 text-xs"><span class="w-7 h-7 rounded-full bg-white/30 grid place-items-center font-bold">' + esc(accName.charAt(0)) + '</span><b>' + esc(accName) + '</b><span class="opacity-75">' + esc(X.sponsored) + '</span></div>'
        + '<div class="absolute inset-x-0 bottom-0 p-3 bg-gradient-to-t from-black/70 to-transparent text-sm"><p class="line-clamp-3">' + esc(val('body') || X.noText) + '</p><div class="mt-2 mx-auto w-max rounded-full bg-white text-ink-900 text-xs font-semibold px-4 py-1.5">' + esc((X.ctas || {})[val('cta')] || '') + '</div></div></div>';
    } else if (pf === 2 && mode !== 'boost') { // انستجرام مربع
      var iimg = objUrl || val('image_url');
      prevEl.innerHTML = '<div class="flex items-center gap-2 mb-2"><div class="w-8 h-8 rounded-full bg-gradient-to-tr from-amber-400 to-pink-500 p-0.5"><div class="w-full h-full rounded-full bg-white grid place-items-center text-xs font-bold">' + esc(accName.charAt(0)) + '</div></div><div class="text-sm"><b>' + esc(accName) + '</b><div class="text-[11px] text-ink-500">' + esc(X.sponsored) + '</div></div></div>'
        + '<div class="-mx-4 aspect-square bg-ink-100 overflow-hidden">' + (iimg ? '<img src="' + esc(iimg) + '" alt="" class="w-full h-full object-cover" onerror="this.remove()">' : '') + '</div>'
        + '<div class="-mx-4 px-4 py-2 border-b border-ink-100 flex justify-between text-sm font-semibold text-brand-700"><span>' + esc((X.ctas || {})[val('cta')] || '') + '</span><span>›</span></div>'
        + '<p class="text-sm mt-2"><b>' + esc(accName) + '</b> ' + esc(val('body') || X.noText) + '</p>';
    } else prevEl.innerHTML = head + body + foot;
    // الميزانية
    var amt = parseFloat(val('budget')) || 0, type = val('budget_type'), s = val('start') ? new Date(val('start')) : new Date(), e = val('end') ? new Date(val('end')) : null;
    var days = e ? Math.max(1, Math.round((e - s) / 864e5) + 1) : 30, h = '';
    if (type === 'daily') h = '<div class="text-2xl font-bold text-ink-900 tabular-nums">' + esc(fmt(X.perDay, money(amt, cur))) + '</div><div class="text-sm text-ink-600 mt-1">' + esc(fmt(X.estimate, money(amt * days, cur), fmt(X.days, days))) + '</div>' + (e ? '' : '<div class="text-[11px] text-ink-500 mt-1">' + esc(X.openEnd) + '</div>');
    else h = '<div class="text-2xl font-bold text-ink-900 tabular-nums">' + esc(fmt(X.total, money(amt, cur))) + '</div>' + (e ? '<div class="text-sm text-ink-600 mt-1">' + esc(fmt(X.perDay, money(amt / days, cur))) + ' · ' + esc(fmt(X.days, days)) + '</div>' : '');
    var cpm = (X.cpm || {})[cur], P = X.p || {}, spendTot = type === 'daily' ? amt * days : amt;
    h += '<div class="mt-3 pt-3 border-t border-ink-100"><div class="text-xs font-semibold text-ink-500">' + esc(P.reach) + '</div>' + (cpm > 0 && spendTot > 0
      ? '<div class="text-sm font-semibold text-ink-900 mt-1">' + esc(fmt(P.reachTxt, Math.round(spendTot / cpm * 700).toLocaleString('en-US'), Math.round(spendTot / cpm * 1300).toLocaleString('en-US'))) + '</div><div class="text-[11px] text-ink-500">' + esc(fmt(P.reachBase, money(cpm, cur))) + '</div>'
      : '<div class="text-[11px] text-ink-500 mt-1">' + esc(P.reachNone) + '</div>') + '</div>';
    var ref = (X.ref || {})[cur]; if (type === 'daily' && ref > 0 && amt >= ref * 3) h += '<div id="adcOver" class="mt-2 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg p-2">' + esc(fmt(X.overWarn, (amt / ref).toFixed(1), money(ref, cur))) + '</div>';
    document.getElementById('adcBud').innerHTML = h;
    // الملخص
    var cs = (val('countries') || '').split(/[\s,]+/).filter(Boolean).map(function (c) { c = c.toUpperCase(); return (X.flags[c] || '') + ' ' + (X.cNames[c] || c); }).join('، ');
    var act = val('launch') === 'active';
    document.getElementById('adcSum').innerHTML = '<div><span class="text-ink-500">' + esc(X.acc) + ':</span> ' + esc(accName) + '</div>'
      + '<div><span class="text-ink-500">' + esc(X.audience) + ':</span> ' + esc(cs || '—') + ' · ' + esc(fmt(X.age, val('age_min') || 18, val('age_max') || 65)) + '</div>'
      + '<div class="mt-2 rounded-lg px-3 py-2 text-xs font-semibold ' + (act ? 'bg-amber-50 text-amber-800' : 'bg-ink-50 text-ink-700') + '">' + esc(act ? X.launchActive : X.launchPaused) + '</div>';
  }
  var t; form.addEventListener('input', function () { clearTimeout(t); t = setTimeout(draw, 120); }); form.addEventListener('change', draw); draw();

  // حماية من الخروج قبل المراجعة
  var dirty = false; form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = X.leave; } });
})();

// الدفعة التانية: نسخ إعلان واقتراح جمهور بالذكاء الاصطناعي + فحص قبل المراجعة
(function () {
  var X = window.ADC || {}, A = X.ai || {}, K = X.chk || {}, form = document.getElementById('adcForm');
  if (!form || form.dataset.adcAi) return; form.dataset.adcAi = '1';
  var el = form.elements, esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var fmt = function (s) { var a = [].slice.call(arguments, 1); return String(s).replace(/%s/g, function () { return a.shift(); }); };
  var val = function (n) { var f = el[n]; if (!f) return ''; if (f.length && !f.tagName) { for (var i = 0; i < f.length; i++) if (f[i].checked) return f[i].value; return ''; } return f.value; };
  var toast = function (m, k) { (window.aiToast || alert)(m, k); };
  function ai(action, extra) {
    var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('action', action); fd.append('goal', (X.goalsMap || {})[val('goal')] || val('goal'));
    Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
    return fetch(location.pathname + '?p=ad_create', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  var focusBox = document.createElement('div'); focusBox.className = 'rounded-xl border border-brand-200 bg-brand-50/60 p-3 space-y-2';
  focusBox.innerHTML = '<input id="adcFocus" class="input !bg-white text-sm" placeholder="' + esc(A.focus) + '" maxlength="300">'
    + '<div class="flex flex-wrap gap-2"><button type="button" id="adcAiCopy" class="btn-ghost text-sm">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(A.copyBtn) + '</button><button type="button" id="adcAiAud" class="btn-ghost text-sm">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(A.audBtn) + '</button></div>'
    + '<div id="adcAiOut" class="hidden grid gap-2"></div>';
  var head = el.headline && el.headline.closest('[data-mode]'); (head || el.budget.closest('section')).insertAdjacentElement('beforebegin', focusBox);
  var out = document.getElementById('adcAiOut');
  document.getElementById('adcAiCopy').onclick = function () {
    var b = this; b.disabled = true; out.classList.remove('hidden'); out.innerHTML = '<div class="text-sm text-ink-500">' + esc(A.wait) + '</div>';
    ai('ai_copy', { focus: document.getElementById('adcFocus').value }).then(function (j) {
      b.disabled = false;
      if (!j.ok || !j.variants) { out.innerHTML = '<div class="text-sm text-red-700">' + esc(A.err + ': ' + (j.error || '')) + '</div>'; return; }
      out.innerHTML = j.variants.slice(0, 3).map(function (v, i) { return '<div class="rounded-xl bg-white border border-ink-200 p-3 text-sm"><div class="text-[11px] text-brand-700 font-semibold mb-1">' + esc(v.angle || '') + '</div><b class="block text-ink-900">' + esc(v.headline) + '</b><p class="text-ink-700 whitespace-pre-line mt-1">' + esc(v.body) + '</p><button type="button" data-v="' + i + '" class="mt-2 link text-xs">' + esc(A.use) + '</button></div>'; }).join('');
      out.onclick = function (e) {
        var u = e.target.closest('[data-v]'); if (!u) return; var v = j.variants[+u.dataset.v];
        var nw = form.querySelector('input[name=mode][value=new]'); if (nw && !nw.checked) { nw.checked = true; nw.dispatchEvent(new Event('change', { bubbles: true })); }
        el.headline.value = (v.headline || '').slice(0, 100); el.body.value = v.body || ''; if (v.cta && el.cta.querySelector('option[value="' + v.cta + '"]')) el.cta.value = v.cta;
        form.dispatchEvent(new Event('input', { bubbles: true })); el.headline.scrollIntoView({ block: 'center', behavior: 'smooth' });
      };
    }).catch(function () { b.disabled = false; out.innerHTML = '<div class="text-sm text-red-700">' + esc(A.err) + '</div>'; });
  };
  document.getElementById('adcAiAud').onclick = function () {
    var b = this, o = el.target.selectedOptions[0]; if (!o) { toast(A.needAcc); return; }
    b.disabled = true; out.classList.remove('hidden'); out.innerHTML = '<div class="text-sm text-ink-500">' + esc(A.waitAud) + '</div>';
    ai('ai_audience', { focus: document.getElementById('adcFocus').value }).then(function (j) {
      b.disabled = false;
      if (!j.ok) { out.innerHTML = '<div class="text-sm text-red-700">' + esc(A.err + ': ' + (j.error || '')) + '</div>'; return; }
      if (j.countries && j.countries.length) { el.countries.value = j.countries.filter(function (c) { return /^[A-Za-z]{2}$/.test(c); }).join(', ').toUpperCase(); el.countries.dispatchEvent(new Event('input', { bubbles: true })); }
      if (j.age_min) el.age_min.value = Math.max(18, Math.min(65, +j.age_min)); if (j.age_max) el.age_max.value = Math.max(18, Math.min(65, +j.age_max));
      if (['all', 'male', 'female'].indexOf(j.gender) >= 0 && el.gender) el.gender.value = j.gender;
      out.innerHTML = '<div class="rounded-xl bg-white border border-ink-200 p-3 text-sm"><b class="text-lime-700">✓ ' + esc(A.applied) + '</b><p class="text-ink-700 mt-1">' + esc(j.why || '') + '</p><div class="miss text-xs text-ink-500 mt-1"></div></div>';
      var miss = out.querySelector('.miss');
      (j.interests || []).slice(0, 6).forEach(function (q) {
        fetch('index.php?p=ad_create&ajax=1&dim=interest&q=' + encodeURIComponent(q) + '&conn=' + encodeURIComponent(o.dataset.conn) + '&acc=' + encodeURIComponent(o.dataset.acc))
          .then(function (r) { return r.json(); }).then(function (d) {
            if (d.results && d.results[0] && window.adcAddInterest) window.adcAddInterest(d.results[0]); else miss.insertAdjacentHTML('beforeend', '<div>' + esc(fmt(A.noInt, q)) + '</div>');
          }).catch(function (er) { miss.insertAdjacentHTML('beforeend', '<div>' + esc(fmt(A.noInt, q)) + '</div>'); if (window.console) console.warn(er); });
      });
      form.dispatchEvent(new Event('input', { bubbles: true }));
    }).catch(function () { b.disabled = false; out.innerHTML = '<div class="text-sm text-red-700">' + esc(A.err) + '</div>'; });
  };

  // فحص قبل المراجعة
  function check() {
    var res = [], mode = val('mode'), body = val('body'), headl = val('headline'), txt = (headl + ' ' + body).toLowerCase();
    var add = function (lvl, m) { res.push([lvl, m]); };
    if (mode === 'boost') { if (!val('post') && !val('post_manual')) add('block', K.noPost); }
    else {
      (K.words || []).forEach(function (w) { if (txt.indexOf(w.toLowerCase()) >= 0) add('warn', fmt(K.wordHit, w)); });
      if (/(^|\s)(هل\s+(أنت|انت|إنت)|are\s+you\b)/i.test(txt)) add('warn', K.personal);
      if (body.length > 125) add('warn', fmt(K.longBody, body.length));
      if (headl.length > 40) add('warn', fmt(K.longHead, headl.length));
      var file = form.querySelector('input[type=file]'); if (!val('image_url') && !(file && file.files.length)) add('block', K.noImg);
      var link = val('link'); if (!link && val('goal') === 'traffic') add('block', K.noLink); else if (link && !/^https:\/\//i.test(link)) add('warn', K.badLink);
      var letters = (headl + body).replace(/[^A-Za-z]/g, ''); if (letters.length > 12 && letters.replace(/[^A-Z]/g, '').length / letters.length > 0.5) add('warn', K.caps);
      var em = (body.match(/\p{Extended_Pictographic}/gu) || []).length; if (em > 5) add('warn', fmt(K.emoji, em));
    }
    var a1 = +val('age_min') || 18, a2 = +val('age_max') || 65; if (a2 - a1 < 5) add('warn', fmt(K.narrow, a1, a2));
    if (val('start') && val('end') && val('end') < val('start')) add('block', K.endBefore);
    return res;
  }
  var passed = false;
  form.addEventListener('submit', function (e) {
    if (passed || window.adcSkipCheck) return; var res = check(); if (!res.length) return;
    e.preventDefault(); e.stopImmediatePropagation();
    var blocks = res.filter(function (r) { return r[0] === 'block'; }).length;
    var d = document.createElement('dialog'); d.className = 'w-[min(480px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="px-5 h-12 flex items-center border-b border-ink-200"><b class="text-sm">' + esc(K.title) + '</b></div><ul class="p-5 space-y-2 text-sm">'
      + res.map(function (r) { return '<li class="rounded-lg px-3 py-2 ' + (r[0] === 'block' ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-900') + '"><b>' + esc(r[0] === 'block' ? K.block : K.warn) + ':</b> ' + esc(r[1]) + '</li>'; }).join('')
      + '</ul><div class="px-5 pb-5 flex justify-end gap-2"><button type="button" class="btn-ghost fx">' + esc(K.fix) + '</button>' + (blocks ? '' : '<button type="button" class="btn-primary go">' + esc(K.go) + '</button>') + '</div>';
    document.body.appendChild(d); d.addEventListener('close', function () { d.remove(); }); d.showModal();
    d.querySelector('.fx').onclick = function () { d.close(); };
    var go = d.querySelector('.go'); if (go) go.onclick = function () { passed = true; d.close(); form.requestSubmit ? form.requestSubmit() : form.submit(); };
  }, true);
})();

// الدفعة التالتة: منتج من قاعدة المعرفة، قوالب، أشكال المعاينة، مسودات
(function () {
  var X = window.ADC || {}, P = X.p || {}, form = document.getElementById('adcForm');
  if (!form || form.dataset.adc3) return; form.dataset.adc3 = '1';
  var el = form.elements, esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var redraw = function () { form.dispatchEvent(new Event('input', { bubbles: true })); };
  // (29) منتج من قاعدة المعرفة
  var focus = document.getElementById('adcFocus');
  if (focus) {
    var prods = X.products || [], wrap = document.createElement('div');
    wrap.innerHTML = prods.length
      ? '<select id="adcProd" class="input !bg-white text-sm"><option value="">' + esc(P.pick) + '</option>' + prods.map(function (p, i) { return '<option value="' + i + '">' + esc(p.name + (p.price ? ' · ' + p.price : '')) + '</option>'; }).join('') + '</select>'
      : '<p class="text-xs text-ink-500">' + esc(P.none) + ' · <a class="link" href="' + esc(X.kbUrl) + '">' + esc(P.add) + '</a></p>';
    focus.parentNode.insertBefore(wrap, focus);
    var ps = document.getElementById('adcProd');
    if (ps) ps.addEventListener('change', function () {
      var p = prods[+ps.value]; if (!p) return;
      focus.value = [p.name, p.details, p.problem && ('المشكلة: ' + p.problem), p.audience && ('الجمهور: ' + p.audience), p.pod && ('الاختلاف: ' + p.pod)].filter(Boolean).join(' — ').slice(0, 300);
      if (el.link && !el.link.value && X.site) el.link.value = X.site;
      if (el.name && !el.name.value) el.name.value = p.name;
      redraw();
    });
  }
  // (32) قوالب حسب الهدف
  var first = form.querySelector('section');
  if (first && P.tpls) {
    var t = document.createElement('div'); t.className = 'card p-4'; t.id = 'adcTpls';
    t.innerHTML = '<div class="text-xs font-semibold text-ink-500 mb-2">' + esc(P.tpl) + '</div><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">' + P.tpls.map(function (x, i) { return '<button type="button" data-t="' + i + '" class="text-start rounded-xl border border-ink-200 p-3 hover:border-brand-400 hover:bg-brand-50 transition"><b class="block text-sm text-ink-900">' + esc(x[2]) + '</b><span class="text-xs text-ink-500">' + esc(x[3]) + '</span></button>'; }).join('') + '</div>';
    first.parentNode.insertBefore(t, first);
    t.addEventListener('click', function (e) {
      var b = e.target.closest('[data-t]'); if (!b) return; var x = P.tpls[+b.dataset.t];
      if (el.goal) el.goal.value = x[0];
      var m = form.querySelector('input[name=mode][value=' + x[1] + ']'); if (m) { m.checked = true; m.dispatchEvent(new Event('change', { bubbles: true })); }
      if (x[0] === 'awareness' && el.countries && X.myCountry) { el.countries.value = X.myCountry; el.countries.dispatchEvent(new Event('input', { bubbles: true })); }
      if (x[0] === 'traffic' && el.link && !el.link.value && X.site) el.link.value = X.site;
      [].forEach.call(t.querySelectorAll('[data-t]'), function (y) { y.classList.toggle('border-brand-500', y === b); y.classList.toggle('bg-brand-50', y === b); });
      redraw();
    });
  }
  // (31) أشكال المعاينة
  var prev = document.getElementById('adcPrev');
  if (prev && P.fmt) {
    var tabs = document.createElement('div'); tabs.className = 'flex gap-1 mb-3'; tabs.setAttribute('role', 'tablist');
    tabs.innerHTML = P.fmt.map(function (f, i) { return '<button type="button" role="tab" data-f="' + i + '" class="flex-1 rounded-lg text-xs py-1.5 border">' + esc(f) + '</button>'; }).join('');
    prev.parentNode.insertBefore(tabs, prev);
    var paint = function () { [].forEach.call(tabs.children, function (b) { var on = +b.dataset.f === (window.adcPrevFmt || 0); b.className = 'flex-1 rounded-lg text-xs py-1.5 border ' + (on ? 'bg-brand-600 text-white border-brand-600' : 'border-ink-200 text-ink-700'); b.setAttribute('aria-selected', on); }); };
    tabs.addEventListener('click', function (e) { var b = e.target.closest('[data-f]'); if (!b) return; window.adcPrevFmt = +b.dataset.f; paint(); redraw(); });
    paint();
  }
  // (37) مسودات
  var reviewBtn = [].filter.call(form.querySelectorAll('button'), function (b) { return !b.type || b.type === 'submit'; }).pop();
  if (reviewBtn) {
    var sb = document.createElement('button'); sb.type = 'button'; sb.className = 'btn-ghost ms-2'; sb.id = 'adcDraft'; sb.textContent = P.save;
    reviewBtn.insertAdjacentElement('afterend', sb);
    sb.onclick = function () {
      var a = form.querySelector('input[name=action]'); a.value = 'draft_save';
      var di = document.createElement('input'); di.type = 'hidden'; di.name = 'draft_id'; di.value = X.draftId || ''; form.appendChild(di);
      form.noValidate = true; window.adcSkipCheck = true; window.onbeforeunload = null;
      var f = form.querySelector('input[type=file]'); if (f) f.disabled = true;
      form.dispatchEvent(new Event('submit', { cancelable: true })); form.submit();
    };
  }
  if ((X.drafts || []).length) {
    var dc = document.createElement('details'); dc.className = 'card p-4'; dc.id = 'adcDrafts'; if (!X.draftId) dc.open = true;
    dc.innerHTML = '<summary class="cursor-pointer text-sm font-semibold text-ink-900">' + esc(P.drafts) + ' (' + X.drafts.length + ')</summary><div class="mt-3 divide-y divide-ink-100">' + X.drafts.map(function (d) {
      return '<div class="py-2 flex items-center gap-3 text-sm"><a class="link flex-1 truncate' + (d.id === X.draftId ? ' font-bold' : '') + '" href="' + esc(d.url) + '">' + esc(d.name) + '</a><span class="text-xs text-ink-500">' + esc(P.by.replace('%s', d.saved).replace('%s', d.by)) + '</span><button type="button" data-del="' + esc(d.id) + '" class="text-xs text-red-600 hover:underline">' + esc(P.del) + '</button></div>';
    }).join('') + '</div>';
    (document.getElementById('adcTpls') || form.querySelector('section')).insertAdjacentElement('beforebegin', dc);
    dc.addEventListener('click', function (e) {
      var b = e.target.closest('[data-del]'); if (!b || !confirm(P.delQ)) return;
      var f = document.createElement('form'); f.method = 'post'; f.hidden = true; f.innerHTML = '<input name="_csrf"><input name="action" value="draft_delete"><input name="draft_id">';
      f.elements._csrf.value = X.csrf; f.elements.draft_id.value = b.dataset.del; document.body.appendChild(f); f.submit();
    });
  }
})();
