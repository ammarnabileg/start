/* منشئ الحملات: شكل الفورمات — أيقونة لكل قسم، عدادات حروف حية، ميزانية بأزرار +/−، شريط عمر بمقبضين،
   القوايم القصيرة بقت أزرار، صورة مصغرة للرابط، ونص بيكبر لوحده. كل تعديل بيعدي على نفس مسار الحفظ (input/change). */
(function () {
  var X = window.ABX, A = window.AB, root = document.getElementById('ab-app'); if (!X || !A || !root || !A.ux) return;
  var S = X.S, U = A.ux, esc = X.esc, fmt = X.fmt;
  function ic(n, c) { return window.uic ? uic(n, c || 'w-5 h-5') : ''; }
  function fire(el) { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }
  var CARDS = [[S.campaign, 'flag', '#2563eb', U.dCampaign], [S.goal, 'target', '#7c3aed', U.dGoal], [S.budgetBid, 'wallet', '#16a34a', U.dBudget], [S.schedule, 'calendar', '#d97706', U.dSchedule],
    [S.adset, 'layers', '#2563eb', U.dAdset], [S.locations, 'globe', '#0891b2', U.dLoc], [S.people, 'users', '#db2777', U.dPeople], [S.placementsT, 'grid', '#7c3aed', U.dPlace],
    [S.ad, 'megaphone', '#2563eb', U.dAd], [S.media, 'image', '#0891b2', U.dMedia], [S.copy, 'type', '#d97706', U.dCopy], [S.ctaT, 'pointer', '#16a34a', U.dCta]];
  var LIM = { '.body': 125, '.description': 30, '.headline': 40 };

  function enhance() { var ed = document.getElementById('ab-editor'); if (!ed) return;
    // أقسام بأيقونة ولون ووصف
    ed.querySelectorAll(':scope > section.card').forEach(function (sec, i) { if (sec.dataset.ux) return; sec.dataset.ux = 1; var h = sec.querySelector('h3'); if (!h) return;
      var m = CARDS.filter(function (c) { return c[0] && h.textContent.trim() === c[0]; })[0] || ['', 'dots', '#64748b', ''];
      sec.classList.add('abx-card'); sec.style.setProperty('--c', m[2]); sec.style.animationDelay = i * 50 + 'ms';
      h.insertAdjacentHTML('beforebegin', '<span class="abx-ic">' + ic(m[1]) + '</span>'); if (m[3]) h.insertAdjacentHTML('afterend', '<small class="abx-sub">' + esc(m[3]) + '</small>');
      h.parentNode.classList.add('abx-head'); });
    // عدادات الحروف
    ed.querySelectorAll('input[data-p], textarea[data-p]').forEach(function (el) { if (el.dataset.cnt || /number|url|date|datetime/.test(el.type)) return;
      var p = el.dataset.p, max = +el.getAttribute('maxlength') || 0, soft = 0; Object.keys(LIM).forEach(function (k) { if (p.slice(-k.length) === k) soft = LIM[k]; });
      if (!soft) return; el.dataset.cnt = 1; var lim = soft || max, c = document.createElement('span'); c.className = 'abx-cnt'; c.dir = 'ltr'; el.parentNode.insertBefore(c, el.nextSibling);
      var up = function () { var n = el.value.length; c.textContent = n + ' / ' + lim; c.classList.toggle('warn', n > lim * .85 && n <= lim); c.classList.toggle('over', n > lim); }; el.addEventListener('input', up); up(); });
    // نص بيكبر لوحده
    ed.querySelectorAll('textarea[data-p]').forEach(function (t) { var fit = function () { t.style.height = 'auto'; t.style.height = Math.min(420, t.scrollHeight + 2) + 'px'; }; t.addEventListener('input', fit); fit(); });
    // الميزانية: أزرار +/− ونِسب سريعة
    ed.querySelectorAll('input[data-p$=".amount"]').forEach(function (el) { if (el.dataset.st) return; el.dataset.st = 1; var w = document.createElement('div'); w.className = 'abx-step'; el.parentNode.insertBefore(w, el);
      w.innerHTML = '<button type="button" data-d="-1">−</button>'; w.appendChild(el); w.insertAdjacentHTML('beforeend', '<span>' + esc(X.D.currency || '') + '</span><button type="button" data-d="1">+</button>');
      var q = document.createElement('div'); q.className = 'abx-quick'; q.innerHTML = [-25, 10, 25, 50].map(function (x) { return '<button type="button" data-pc="' + x + '">' + (x > 0 ? '+' : '') + x + '%</button>'; }).join('') + '<em data-mo></em>'; w.after(q);
      var mo = function () { var v = +el.value || 0, daily = !/lifetime/.test((ed.querySelector('select[data-p$="budget.type"]') || {}).value || 'daily'); q.querySelector('[data-mo]').textContent = v && daily ? fmt(U.month, X.money(v * 30, X.D.currency)) : ''; };
      w.addEventListener('click', function (e) { var b = e.target.closest('[data-d]'); if (!b) return; var v = +el.value || 0, s = v >= 100 ? 10 : v >= 20 ? 5 : 1; el.value = Math.max(1, v + s * +b.dataset.d); fire(el); mo(); });
      q.addEventListener('click', function (e) { var b = e.target.closest('[data-pc]'); if (!b) return; el.value = Math.max(1, Math.round((+el.value || 0) * (1 + +b.dataset.pc / 100) * 100) / 100); fire(el); mo(); });
      el.addEventListener('input', mo); mo(); });
    // العمر: شريط بمقبضين
    var mn = ed.querySelector('input[data-p$="targeting.ageMin"]'), mx = ed.querySelector('input[data-p$="targeting.ageMax"]');
    if (mn && mx && !mn.dataset.rg) { mn.dataset.rg = 1; var lo = +mn.min || 13, hi = +mn.max || 65, box = document.createElement('div'); box.className = 'abx-age';
      box.innerHTML = '<div class="flex items-center justify-between mb-1"><span class="label !mb-0">' + esc(U.age) + '</span><b data-al></b></div><div class="abx-rg"><i></i><input type="range" min="' + lo + '" max="' + hi + '" data-r="min"><input type="range" min="' + lo + '" max="' + hi + '" data-r="max"></div>' +
        '<div class="abx-quick">' + [[18, 24], [25, 34], [35, 44], [45, 65], [18, 65]].map(function (r) { return '<button type="button" data-ag="' + r[0] + '-' + r[1] + '">' + r[0] + '–' + r[1] + (r[1] === 65 ? '+' : '') + '</button>'; }).join('') + '</div>';
      var l1 = mn.closest('label'), l2 = mx.closest('label'); box.classList.add('col-span-full'); l1.parentNode.insertBefore(box, l1); l1.classList.add('abx-hide'); l2.classList.add('abx-hide');
      var r1 = box.querySelector('[data-r=min]'), r2 = box.querySelector('[data-r=max]');
      var paint = function () { var a = +mn.value || lo, b = +mx.value || hi; r1.value = a; r2.value = b; box.querySelector('[data-al]').textContent = a + ' – ' + b + (b >= hi ? '+' : ''); var bar = box.querySelector('i');
        bar.style.insetInlineStart = (a - lo) / (hi - lo) * 100 + '%'; bar.style.insetInlineEnd = (hi - b) / (hi - lo) * 100 + '%'; };
      var set = function (a, b) { mn.value = a; mx.value = b; fire(mn); fire(mx); paint(); };
      r1.addEventListener('input', function () { var a = Math.min(+r1.value, +r2.value - 1); set(a, +r2.value); }); r2.addEventListener('input', function () { var b = Math.max(+r2.value, +r1.value + 1); set(+r1.value, b); });
      box.addEventListener('click', function (e) { var b = e.target.closest('[data-ag]'); if (b) { var g = b.dataset.ag.split('-'); set(+g[0], +g[1]); } }); paint(); }
    // القوايم القصيرة → أزرار
    ed.querySelectorAll('select[data-p]').forEach(function (s) { if (s.dataset.seg || s.options.length < 2 || s.options.length > 4 || s.disabled) return; s.dataset.seg = 1;
      var g = document.createElement('div'); g.className = 'abx-seg'; g.innerHTML = [].map.call(s.options, function (o) { return '<button type="button" data-v="' + esc(o.value) + '" class="' + (o.value === s.value ? 'on' : '') + '">' + esc(o.text) + '</button>'; }).join('');
      s.classList.add('abx-hide'); s.after(g); g.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (!b || b.classList.contains('on')) return; s.value = b.dataset.v; g.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); fire(s); }); });
    // صورة مصغرة لرابط الصورة
    ed.querySelectorAll('input[data-p$="imageUrl"], input[data-p$="thumbnailUrl"]').forEach(function (el) { if (el.dataset.th) return; el.dataset.th = 1; var t = document.createElement('span'); t.className = 'abx-th'; var w = document.createElement('div'); w.className = 'abx-thw'; el.parentNode.insertBefore(w, el); w.appendChild(t); w.appendChild(el);
      var up = function () { var u = el.value.trim(); t.innerHTML = /^https?:\/\//.test(u) ? '<img src="' + esc(u) + '" alt="" onerror="this.parentNode.classList.add(\'bad\');this.remove()">' : ic('image', 'w-5 h-5'); t.classList.remove('bad'); }; el.addEventListener('change', up); el.addEventListener('input', function () { clearTimeout(el._t); el._t = setTimeout(up, 500); }); up(); });
    if (X.locked) ed.querySelectorAll('.abx-step button, .abx-quick button, .abx-seg button, .abx-age input').forEach(function (b) { b.disabled = true; });
  }
  // التوجيه الفني للإعلان (من الذكاء الاصطناعي)
  function artCard() { var ed = document.getElementById('ab-editor'), s = X.sel(), P2 = A.ai2 || {}; if (!ed || s.t !== 'ad' || ed.querySelector('.abx-art')) return; var ad = X.D.adSets[s.s] && X.D.adSets[s.s].ads[s.a]; if (!ad || !ad.art) return; var a = ad.art;
    var sec = document.createElement('section'); sec.className = 'card p-5 abx-art';
    sec.innerHTML = '<div class="flex items-center gap-2 mb-3"><span class="abx-ic" style="--c:#db2777">' + ic('image') + '</span><b class="text-ink-900 me-auto">' + esc(P2.artT) + '</b>' + (ad.angle ? '<span class="text-xs font-semibold text-brand-700">' + esc(ad.angle) + '</span>' : '') + '</div>' +
      '<dl class="grid gap-2 sm:grid-cols-2 text-sm">' + [['concept', P2.concept], ['composition', P2.composition], ['palette', P2.palette], ['overlay', P2.overlay]].filter(function (k) { return a[k[0]]; }).map(function (k) { return '<div class="rounded-xl bg-ink-50 p-3"><dt class="text-[11px] text-ink-500">' + esc(k[1]) + '</dt><dd class="text-ink-900 mt-0.5" dir="auto">' + esc(a[k[0]]) + '</dd></div>'; }).join('') + '</dl>' +
      (a.prompt ? '<div class="mt-3 rounded-xl border border-dashed border-ink-300 p-3"><span class="text-[11px] text-ink-500">' + esc(P2.prompt) + '</span><p class="text-xs text-ink-800 mt-1 font-mono" dir="ltr">' + esc(a.prompt) + '</p><div class="flex gap-2 mt-2"><button type="button" class="btn-ghost !py-1 !text-xs" data-cp>' + ic('copy', 'w-3.5 h-3.5') + esc(P2.copy) + '</button><a class="btn-ghost !py-1 !text-xs" target="_blank" href="' + esc(P2.designUrl) + '">' + ic('image', 'w-3.5 h-3.5') + esc(P2.design) + '</a></div></div>' : '');
    var first = ed.querySelector(':scope > section.card'); if (first) first.after(sec); else ed.prepend(sec);
    sec.addEventListener('click', function (e) { var b = e.target.closest('[data-cp]'); if (!b) return; if (navigator.clipboard) navigator.clipboard.writeText(a.prompt); b.lastChild.textContent = P2.copied; setTimeout(function () { b.lastChild.textContent = P2.copy; }, 1500); }); }
  document.addEventListener('ab:render', function (e) { if (!(e.detail && e.detail.soft)) { enhance(); artCard(); } });
  enhance();
})();
