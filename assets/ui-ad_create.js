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
    document.getElementById('adcPrev').innerHTML = head + body + foot;
    // الميزانية
    var amt = parseFloat(val('budget')) || 0, type = val('budget_type'), s = val('start') ? new Date(val('start')) : new Date(), e = val('end') ? new Date(val('end')) : null;
    var days = e ? Math.max(1, Math.round((e - s) / 864e5) + 1) : 30, h = '';
    if (type === 'daily') h = '<div class="text-2xl font-bold text-ink-900 tabular-nums">' + esc(fmt(X.perDay, money(amt, cur))) + '</div><div class="text-sm text-ink-600 mt-1">' + esc(fmt(X.estimate, money(amt * days, cur), fmt(X.days, days))) + '</div>' + (e ? '' : '<div class="text-[11px] text-ink-500 mt-1">' + esc(X.openEnd) + '</div>');
    else h = '<div class="text-2xl font-bold text-ink-900 tabular-nums">' + esc(fmt(X.total, money(amt, cur))) + '</div>' + (e ? '<div class="text-sm text-ink-600 mt-1">' + esc(fmt(X.perDay, money(amt / days, cur))) + ' · ' + esc(fmt(X.days, days)) + '</div>' : '');
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
