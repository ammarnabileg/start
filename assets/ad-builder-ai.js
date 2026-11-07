/* منشئ الحملات — الذكاء الاصطناعي (بناء الحملة كاملة، جمهور مجموعة، نسخ إعلان، مراجعة ميديا باير)
   + معاينة الإعلان + تقدير الوصول من المنصة. مفيش حاجة بتتطبق غير لما تضغط "طبّق". */
(function () {
  var X = window.ABX; if (!X) return; var A = X.A, S = X.S, C = X.C, D = X.D, esc = X.esc, fmt = X.fmt, I = S.ai || {};
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  function post(f) { var fd = new FormData(); fd.append('_csrf', A.csrf); fd.append('id', A.id); Object.keys(f).forEach(function (k) { fd.append(k, typeof f[k] === 'object' ? JSON.stringify(f[k]) : f[k]); }); return fetch(A.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  function ai(action, input) { X.save(true); return new Promise(function (res) { setTimeout(function () { post({ action: action, input: input || {} }).then(res).catch(function () { res({ ok: false, error: I.netErr }); }); }, 700); }); }
  function dialog(title, html, wide) {
    var d = document.createElement('dialog'); d.className = (wide ? 'w-[min(900px,96vw)]' : 'w-[min(520px,94vw)]') + ' p-0 rounded-2xl border border-ink-200 bg-white text-ink-900 shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm">✦ ' + esc(title) + '</b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100" aria-label="✕">✕</button></div><div class="bd p-5 max-h-[75vh] overflow-y-auto">' + html + '</div>';
    d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); }); document.body.appendChild(d); d.showModal(); return d;
  }
  var wait = function (el, t) { el.innerHTML = '<div class="flex items-center gap-3 text-sm text-ink-600 py-6 justify-center"><span class="inline-block w-4 h-4 rounded-full border-2 border-brand-500 border-t-transparent animate-spin"></span>' + esc(t) + '</div>'; };
  var clampAge = function (v, d) { v = +v; return v >= 13 && v <= 65 ? Math.round(v) : d; };
  // بحث الاهتمامات في المنصة (أول نتيجة) — قراءة بس
  function resolveInterests(queries) {
    if (!C.targeting.interests || !queries || !queries.length) return Promise.resolve([]);
    return Promise.all(queries.slice(0, 6).map(function (q) {
      return fetch(A.geoUrl + '&dim=interest&q=' + encodeURIComponent(q) + '&conn=' + encodeURIComponent(D.connId) + '&acc=' + encodeURIComponent(D.adAccountId), { credentials: 'same-origin' }).then(function (r) { return r.json(); })
        .then(function (j) { var x = (j.results || [])[0]; return x ? { id: x.id, name: x.name, size: x.size || null } : null; }).catch(function () { return null; });
    })).then(function (l) { var seen = {}; return l.filter(function (x) { if (!x || seen[x.id]) return false; seen[x.id] = 1; return true; }); });
  }
  function toTargeting(t, base) {
    var o = X.clone(base || D.adSets[0].targeting);
    var cc = (t.countries || []).map(function (c) { return String(c).toUpperCase(); }).filter(function (c) { return /^[A-Z]{2}$/.test(c); });
    if (cc.length) { o.countries = cc; o.regions = []; o.cities = []; }
    o.ageMin = clampAge(t.ageMin, 18); o.ageMax = clampAge(t.ageMax, 65); if (o.ageMax < o.ageMin) o.ageMax = 65;
    o.gender = ['all', 'male', 'female'].indexOf(t.gender) >= 0 ? t.gender : 'all'; o.interests = [];
    return o;
  }
  function toAd(a) {
    var ad = X.newAd(); ad.name = String(a.name || a.angle || '').slice(0, 200);
    ad.headline = String(a.headline || '').slice(0, C.maxHeadline || 255); ad.body = String(a.body || ''); if (C.maxBody) ad.body = ad.body.slice(0, C.maxBody);
    if (C.multiText) { ad.headlines = (a.headlines || []).map(String).filter(Boolean).slice(0, 4).map(function (h) { return h.slice(0, C.maxHeadline || 255); }); ad.bodies = (a.bodies || []).map(String).filter(Boolean).slice(0, 4); }
    if (C.ctas.indexOf(a.cta) >= 0) ad.cta = a.cta;
    return ad;
  }

  // ---- (1) ابني الحملة كلها — على مرحلتين: الاستراتيجية والمجموعات، وبعدين إعلانات كل مجموعة بتوجيه فني
  var P2 = A.ai2 || {};
  function toAd2(a) { var ad = toAd(a); if (a.format && C.formats.indexOf(a.format) >= 0) ad.format = a.format;
    if (ad.format === 'carousel') ad.cards = (a.cards || []).slice(0, 5).map(function (c) { return { imageUrl: '', linkUrl: '', headline: String(c.headline || '').slice(0, 80), description: String(c.description || '').slice(0, 120) }; });
    if (ad.format === 'carousel' && ad.cards.length < 2) ad.cards = [0, 1, 2].map(function () { return { imageUrl: '', linkUrl: '', headline: '', description: '' }; });
    if (a.art && typeof a.art === 'object') ad.art = { concept: String(a.art.concept || ''), composition: String(a.art.composition || ''), palette: String(a.art.palette || ''), overlay: String(a.art.overlay || ''), prompt: String(a.art.prompt || '') };
    ad.angle = String(a.angle || ''); return ad; }
  function buildDialog() {
    var prods = A.kbProducts || [], maxAds = C.maxAdsPerSet === 1 ? 1 : Math.min(7, C.maxAdsPerSet || 7);
    var d = dialog(I.build, '<div class="ai-in space-y-4 text-sm"><p class="text-ink-600">' + esc(P2.intro) + '</p>'
      + '<label class="block"><span class="label">' + esc(I.brief) + '</span><textarea class="input br" rows="3" placeholder="' + esc(P2.briefPh) + '"></textarea></label>'
      + '<div class="grid gap-3 sm:grid-cols-3"><label class="block"><span class="label">' + esc(I.product) + '</span>' + (prods.length ? '<select class="input pr"><option value="">' + esc(I.anyProduct) + '</option>' + prods.map(function (p) { return '<option>' + esc(p) + '</option>'; }).join('') + '</select>' : '<input class="input pr" placeholder="' + esc(I.productPh) + '">') + '</label>'
      + '<label class="block"><span class="label">' + esc(fmt(I.dailyBudget, D.currency)) + '</span><input class="input bu" type="number" min="1" step="1" dir="ltr" placeholder="100"></label>'
      + '<label class="block"><span class="label">' + esc(P2.markets) + '</span><input class="input mk" placeholder="' + esc(P2.marketsPh) + '"></label></div>'
      + '<div class="grid gap-3 sm:grid-cols-2"><div><span class="label">' + esc(P2.nSets) + '</span><div class="abx-seg ns">' + ['3', '4', '5'].map(function (n) { return '<button type="button" data-v="' + n + '" class="' + (n === '4' ? 'on' : '') + '">' + n + '</button>'; }).join('') + '</div></div>'
      + '<div><span class="label">' + esc(P2.nAds) + ' <b class="na-v">' + Math.min(4, maxAds) + '</b></span><input type="range" class="na w-full accent-brand-600" min="' + Math.min(3, maxAds) + '" max="' + maxAds + '" value="' + Math.min(4, maxAds) + '"' + (maxAds === 1 ? ' disabled' : '') + '></div></div>'
      + '<p class="text-[11px] text-ink-500">' + esc(P2.note) + '</p><div class="flex justify-end"><button type="button" class="btn-primary go">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + esc(I.buildGo) + '</button></div></div><div class="out"></div>', true);
    $('.ns', d).onclick = function (e) { var b2 = e.target.closest('[data-v]'); if (!b2) return; $$('.ns button', d).forEach(function (x) { x.classList.toggle('on', x === b2); }); };
    $('.na', d).oninput = function () { $('.na-v', d).textContent = this.value; };
    $('.go', d).onclick = function () {
      var brief = $('.br', d).value.trim(); if (brief.length < 5) { $('.br', d).focus(); return; }
      var inp = { brief: brief, product: $('.pr', d).value, budget: $('.bu', d).value, markets: $('.mk', d).value, nSets: +$('.ns .on', d).dataset.v, nAds: +$('.na', d).value };
      $('.ai-in', d).classList.add('hidden'); runBuild(d, inp);
    };
  }
  function stage(d, step, total, txt) { var out = $('.out', d), pg = $('.ai-pg', d);
    if (!pg) { out.insertAdjacentHTML('afterbegin', '<div class="ai-pg"><div class="flex items-center gap-2 text-sm"><span class="ai-spin"></span><b class="ai-pt"></b><span class="ms-auto text-xs text-ink-500 tabular-nums ai-pn"></span></div><div class="ai-bar"><i></i></div></div>'); pg = $('.ai-pg', d); }
    $('.ai-pt', pg).textContent = txt; $('.ai-pn', pg).textContent = step + ' / ' + total; $('.ai-bar i', pg).style.width = Math.round(step / total * 100) + '%'; pg.classList.toggle('done', step >= total); }
  function runBuild(d, inp) {
    var out = $('.out', d); out.innerHTML = ''; stage(d, 0, 1, P2.planning);
    ai('ai_plan', inp).then(function (j) {
      if (!j.ok) { out.innerHTML = '<p class="text-sm text-red-700">' + esc(j.error) + '</p><button type="button" class="btn-ghost mt-2 back">' + esc(I.again) + '</button>'; $('.back', d).onclick = function () { out.innerHTML = ''; $('.ai-in', d).classList.remove('hidden'); }; return; }
      var plan = j.r || {}, sets = (plan.adSets || []).slice(0, 5); if (!sets.length) { out.innerHTML = '<p class="text-sm text-red-700">' + esc(P2.empty) + '</p>'; return; }
      var total = sets.length + 1; stage(d, 1, total, fmt(P2.writing, 1, sets.length));
      out.insertAdjacentHTML('beforeend', planHtml(plan, sets, inp));
      var strategy = JSON.stringify({ campaign: plan.campaign, sets: sets.map(function (s) { return { name: s.name, persona: s.persona, stage: s.stage }; }) });
      (function next(i) {
        if (i >= sets.length) { stage(d, total, total, P2.ready); finish(d, plan, sets, inp); return; }
        stage(d, i + 1, total, fmt(P2.writing, i + 1, sets.length)); var box = $('[data-set="' + i + '"] .ai-ads', d); box.innerHTML = '<div class="ai-sk"></div><div class="ai-sk"></div>';
        ai('ai_ads', { brief: inp.brief, product: inp.product, strategy: strategy, setPlan: JSON.stringify(sets[i]), nAds: inp.nAds }).then(function (k) {
          if (!k.ok || !(k.r && (k.r.ads || []).length)) { box.innerHTML = '<p class="text-xs text-red-700">' + esc((k && k.error) || P2.empty) + ' <button type="button" class="underline" data-retry="' + i + '">' + esc(I.again) + '</button></p>'; sets[i]._ads = []; }
          else { sets[i]._ads = k.r.ads.slice(0, inp.nAds || 7); box.innerHTML = adsHtml(sets[i]._ads); }
          next(i + 1); });
      })(0);
      out.onclick = function (e) { var r = e.target.closest('[data-retry]'); if (!r) return; var i = +r.dataset.retry, box = r.closest('.ai-ads'); box.innerHTML = '<div class="ai-sk"></div>';
        ai('ai_ads', { brief: inp.brief, product: inp.product, strategy: strategy, setPlan: JSON.stringify(sets[i]), nAds: inp.nAds }).then(function (k) { if (k.ok && k.r && (k.r.ads || []).length) { sets[i]._ads = k.r.ads.slice(0, inp.nAds || 7); box.innerHTML = adsHtml(sets[i]._ads); } else box.innerHTML = '<p class="text-xs text-red-700">' + esc((k && k.error) || P2.empty) + '</p>'; }); };
    });
  }
  function planHtml(plan, sets, inp) { var c = plan.campaign || {}, goal = C.goals.indexOf(c.goal) >= 0 ? c.goal : D.campaign.goal, daily = +c.dailyBudget || +inp.budget || 0, share = sets.reduce(function (n, s) { return n + (+s.budgetShare || 0); }, 0) || 100;
    return '<div class="ai-plan space-y-3 mt-3"><div class="ai-hero"><b>' + esc(c.name || D.campaign.name) + '</b><span>' + esc((S.goals[goal] || [goal])[0]) + ' · ' + esc(c.budgetLevel === 'campaign' && C.cbo ? S.cbo : S.abo) + (daily ? ' · ' + esc(X.money(daily, D.currency)) + '/' + esc(I.day) : '') + '</span>' + (c.why ? '<p>' + esc(c.why) + '</p>' : '') + (c.kpis ? '<p class="ai-kpi">🎯 ' + esc(c.kpis) + '</p>' : '') + '</div>'
      + sets.map(function (s, i) { var t = s.targeting || {}, amt = daily ? Math.round(daily * (+s.budgetShare || 100 / sets.length) / share) : 0;
        return '<div class="ai-set" data-set="' + i + '"><div class="flex flex-wrap items-center gap-2"><span class="ai-n">' + (i + 1) + '</span><b class="text-ink-900">' + esc(s.name || fmt(S.setN, i + 1)) + '</b>' + (s.stage ? '<span class="ai-tag ' + (s.stage === 'warm' ? 'w' : '') + '">' + esc(s.stage === 'warm' ? P2.warm : P2.cold) + '</span>' : '') + (amt ? '<span class="ms-auto text-xs font-bold text-ink-700" dir="ltr">' + esc(X.money(amt, D.currency)) + '/' + esc(I.day) + '</span>' : '') + '</div>'
          + (s.persona ? '<p class="ai-per">👤 ' + esc(s.persona) + '</p>' : '') + '<p class="text-[11px] text-ink-500 mt-1">' + esc([(t.countries || []).map(function (c2) { return X.flag(c2) + ' ' + X.cname(c2); }).join('، '), (t.ageMin || 18) + '–' + (t.ageMax || 65), t.gender && t.gender !== 'all' ? S['g' + t.gender.charAt(0).toUpperCase() + t.gender.slice(1)] : '', (t.interestQueries || []).join(', ')].filter(Boolean).join(' · ')) + '</p>'
          + '<div class="ai-ads mt-2"></div></div>'; }).join('')
      + ((plan.tips || []).length ? '<div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900 dark:bg-[#2a1d05] dark:text-[#fcd34d]"><b>' + esc(I.tips) + '</b><ul class="list-disc ps-5 mt-1 space-y-0.5">' + plan.tips.slice(0, 6).map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul></div>' : '') + '<div class="ai-foot"></div></div>'; }
  function adsHtml(ads) { return '<div class="grid gap-2 md:grid-cols-2">' + ads.map(function (a) { var art = a.art || {};
    return '<div class="ai-ad"><div class="flex items-center gap-2"><span class="ai-fmt">' + esc((S.formats || {})[a.format] || a.format || '') + '</span><span class="text-[11px] font-semibold text-brand-700 truncate">' + esc(a.angle || '') + '</span></div><b class="block text-sm text-ink-900 mt-1" dir="auto">' + esc(a.headline || '') + '</b><p class="text-xs text-ink-700 mt-0.5 whitespace-pre-line line-clamp-4" dir="auto">' + esc(a.body || '') + '</p>'
      + (art.concept ? '<p class="ai-art">🎨 ' + esc(art.concept) + (art.overlay ? ' · <b>"' + esc(art.overlay) + '"</b>' : '') + '</p>' : '') + '</div>'; }).join('') + '</div>'; }
  function finish(d, plan, sets, inp) { var foot = $('.ai-foot', d), c = plan.campaign || {}, goal = C.goals.indexOf(c.goal) >= 0 ? c.goal : D.campaign.goal, daily = +c.dailyBudget || +inp.budget || 0, cbo = C.cbo && c.budgetLevel === 'campaign', share = sets.reduce(function (n, s) { return n + (+s.budgetShare || 0); }, 0) || 100;
    var nAds = sets.reduce(function (n, s) { return n + (s._ads || []).length; }, 0);
    foot.innerHTML = '<p class="text-[11px] text-ink-500">' + esc(fmt(P2.summary, sets.length, nAds)) + ' · ' + esc(I.applyNote) + '</p><div class="flex justify-end gap-2 mt-2"><button type="button" class="btn-ghost again">' + esc(I.again) + '</button><button type="button" class="btn-primary apply">' + esc(I.apply) + '</button></div>';
    $('.again', d).onclick = function () { $('.out', d).innerHTML = ''; $('.ai-in', d).classList.remove('hidden'); };
    $('.apply', d).onclick = function () {
      if (D.adSets.some(function (s) { return s.ads.some(function (a) { return a.body; }); }) && !confirm(I.replaceQ)) return;
      var btn = this; btn.disabled = true; btn.textContent = I.resolving;
      Promise.all(sets.map(function (s) { return resolveInterests((s.targeting || {}).interestQueries); })).then(function (ints) {
        if (c.name) D.campaign.name = String(c.name).slice(0, 200); D.campaign.goal = goal;
        D.campaign.budgetLevel = cbo ? 'campaign' : 'adset'; if (cbo && daily) D.campaign.budget = { amount: daily, type: 'daily' };
        var base = D.adSets[0] ? D.adSets[0].targeting : null; D.adSets.splice(0, D.adSets.length);
        sets.forEach(function (s, i) { var amt = daily ? Math.round(daily * (+s.budgetShare || 100 / sets.length) / share * 100) / 100 : '';
          var st = { id: X.uid('s'), name: String(s.name || '').slice(0, 200), budget: { amount: cbo ? '' : amt, type: 'daily' }, targeting: toTargeting(s.targeting || {}, base), ads: [] };
          st.targeting.interests = ints[i] || [];
          (s._ads || []).slice(0, C.maxAdsPerSet === 1 ? 1 : 7).forEach(function (a) { st.ads.push(toAd2(a)); }); if (!st.ads.length) st.ads.push(X.newAd());
          D.adSets.push(st); });
        d.close(); X.save(true); X.go('campaign'); if (window.fxToast) fxToast(I.applied, 'success'); else if (window.aiToast) aiToast(I.applied, 'ok'); });
    }; }

  // ---- (2) جمهور لمجموعة
  function audienceFor(si) {
    var d = dialog(fmt(I.audFor, D.adSets[si].name || fmt(S.setN, si + 1)), '<div class="out"></div>'); wait($('.out', d), I.thinking);
    ai('ai_audience', { set: si + 1 }).then(function (j) {
      var out = $('.out', d); if (!j.ok) { out.innerHTML = '<p class="text-sm text-red-700">' + esc(j.error) + '</p>'; return; }
      var r = j.r || {}, t = r.targeting || {};
      out.innerHTML = '<div class="space-y-3 text-sm"><b class="text-ink-900">' + esc(r.name || '') + '</b><p class="text-ink-600">' + esc(r.why || '') + '</p>'
        + '<p class="text-xs text-ink-500">' + esc([(t.countries || []).map(function (c2) { return X.flag(c2) + ' ' + X.cname(c2); }).join('، '), (t.ageMin || 18) + '-' + (t.ageMax || 65), t.gender && t.gender !== 'all' ? S['g' + t.gender.charAt(0).toUpperCase() + t.gender.slice(1)] : '', (t.interestQueries || []).join(', ')].filter(Boolean).join(' · ')) + '</p>'
        + '<div class="flex justify-end"><button type="button" class="btn-primary apply">' + esc(I.applyAud) + '</button></div></div>';
      $('.apply', d).onclick = function () {
        this.disabled = true; this.textContent = I.resolving;
        resolveInterests(t.interestQueries).then(function (ints) {
          var st = D.adSets[si]; st.targeting = toTargeting(t, st.targeting); st.targeting.interests = ints; if (!st.name && r.name) st.name = String(r.name).slice(0, 200);
          d.close(); X.save(true); X.go('adset', si);
        });
      };
    });
  }

  // ---- (3) نسخ لإعلان
  function copyFor(si, ai2) {
    var d = dialog(I.copyT, '<div class="out"></div>', true); wait($('.out', d), I.writing);
    ai('ai_copy', { set: si + 1, ad: ai2 + 1 }).then(function (j) {
      var out = $('.out', d); if (!j.ok) { out.innerHTML = '<p class="text-sm text-red-700">' + esc(j.error) + '</p>'; return; }
      var vs = (j.r.variants || []).slice(0, 3);
      out.innerHTML = '<div class="grid gap-3 md:grid-cols-3">' + vs.map(function (v, i) {
        return '<div class="rounded-xl border border-ink-200 p-3 flex flex-col text-sm"><span class="text-[11px] font-semibold text-brand-700">' + esc(v.angle || '') + '</span><b class="text-ink-900 mt-1">' + esc(v.headline || '') + '</b><p class="text-ink-700 mt-1 whitespace-pre-line flex-1">' + esc(v.body || '') + '</p>'
          + '<div class="flex gap-2 mt-3"><button type="button" data-use="' + i + '" class="btn-primary text-xs flex-1">' + esc(I.useThis) + '</button>' + (C.maxAdsPerSet === 1 ? '' : '<button type="button" data-new="' + i + '" class="btn-ghost text-xs">' + esc(I.asNew) + '</button>') + '</div></div>';
      }).join('') + '</div>';
      out.onclick = function (e) {
        var u = e.target.closest('[data-use]'), n = e.target.closest('[data-new]'); if (!u && !n) return;
        var v = vs[+(u || n).dataset[u ? 'use' : 'new']], ad = toAd(v), st = D.adSets[si];
        if (u) { var cur = st.ads[ai2]; ['headline', 'body', 'headlines', 'bodies', 'cta'].forEach(function (k) { cur[k] = ad[k]; }); if (!cur.name) cur.name = ad.name; d.close(); X.save(true); X.go('ad', si, ai2); }
        else { var base = st.ads[ai2]; ['format', 'imageUrl', 'videoUrl', 'thumbnailUrl', 'linkUrl', 'cards', 'leadGenFormId'].forEach(function (k) { ad[k] = X.clone(base[k]); }); st.ads.splice(ai2 + 1, 0, ad); d.close(); X.save(true); X.go('ad', si, ai2 + 1); }
      };
    });
  }

  // ---- (4) مراجعة ميديا باير
  function review() {
    var d = dialog(I.reviewT, '<div class="out"></div>', true); wait($('.out', d), I.reviewing);
    ai('ai_review', {}).then(function (j) {
      var out = $('.out', d); if (!j.ok) { out.innerHTML = '<p class="text-sm text-red-700">' + esc(j.error) + '</p>'; return; }
      var r = j.r || {}, sc = Math.max(0, Math.min(100, +r.score || 0)), tone = sc >= 75 ? 'text-lime-700' : sc >= 50 ? 'text-amber-700' : 'text-red-700';
      var lv = { high: ['bg-red-50 text-red-800', I.high], medium: ['bg-amber-50 text-amber-900', I.medium], low: ['bg-ink-50 text-ink-700', I.low] };
      out.innerHTML = '<div class="flex items-center gap-4 mb-4"><div class="text-4xl font-bold tabular-nums ' + tone + '">' + sc + '</div><p class="text-sm text-ink-700">' + esc(r.verdict || '') + '</p></div>'
        + '<ul class="space-y-2">' + (r.findings || []).slice(0, 10).map(function (f) {
          var l = lv[f.level] || lv.low, w = String(f.where || ''), m = w.match(/set:(\d+)/), m2 = w.match(/ad:(\d+)\.(\d+)/), go = m2 ? 'ad|' + (m2[1] - 1) + '|' + (m2[2] - 1) : m ? 'adset|' + (m[1] - 1) + '|' : 'campaign||';
          return '<li class="rounded-xl p-3 text-sm ' + l[0] + '"><div class="flex items-start gap-2"><b class="shrink-0">' + esc(l[1]) + '</b><div class="flex-1"><p>' + esc(f.msg || '') + '</p>' + (f.fix ? '<p class="text-xs mt-1 opacity-80">↳ ' + esc(f.fix) + '</p>' : '') + '</div><button type="button" data-jump="' + go + '" class="text-xs underline shrink-0">' + esc(I.goThere) + '</button></div></li>';
        }).join('') + '</ul>';
      out.onclick = function (e) { var b = e.target.closest('[data-jump]'); if (!b) return; var g = b.dataset.jump.split('|'); d.close(); if (g[0] === 'campaign' || D.adSets[+g[1]] && (g[0] !== 'ad' || D.adSets[+g[1]].ads[+g[2]])) X.go(g[0], +g[1] || 0, +g[2] || 0); };
    });
  }

  // ---- (5) معاينة الإعلان
  function preview() {
    var box = document.getElementById('ab-preview'), s = X.sel(); if (!box) return;
    if (s.t !== 'ad') { box.innerHTML = ''; return; }
    var ad = D.adSets[s.s] && D.adSets[s.s].ads[s.a]; if (!ad) return;
    var img = ad.format === 'carousel' ? ((ad.cards || [])[0] || {}).imageUrl : ad.format === 'video' ? ad.thumbnailUrl : ad.imageUrl, host = ''; try { host = ad.linkUrl ? new URL(ad.linkUrl).hostname : ''; } catch (e) {}
    var cta = (S.ctas || {})[ad.cta] || ad.cta;
    box.innerHTML = '<section class="card p-4"><div class="text-xs font-semibold text-ink-500 mb-3">' + esc(I.preview) + ' · ' + esc(A.platformLabel) + '</div><div class="rounded-xl border border-ink-200 overflow-hidden text-sm">'
      + '<div class="flex items-center gap-2 p-3"><span class="w-8 h-8 rounded-full bg-brand-100 grid place-items-center font-bold text-brand-700">' + esc((D.accName || '?').charAt(0)) + '</span><div><b class="block text-ink-900 text-xs">' + esc(D.accName) + '</b><span class="text-[10px] text-ink-500">' + esc(I.sponsored) + '</span></div></div>'
      + '<p class="px-3 pb-2 text-ink-800 whitespace-pre-line line-clamp-4">' + esc(ad.body || I.noBody) + '</p>'
      + '<div class="relative aspect-square bg-ink-100">' + (img ? '<img src="' + esc(img) + '" alt="" class="w-full h-full object-cover" onerror="this.remove()">' : '') + (ad.format === 'video' ? '<span class="absolute inset-0 grid place-items-center text-4xl text-white/90">▶</span>' : '') + (ad.format === 'carousel' ? '<span class="absolute bottom-2 end-2 text-[10px] rounded bg-black/60 text-white px-1.5">1/' + (ad.cards || []).length + '</span>' : '') + '</div>'
      + '<div class="flex items-center gap-2 p-3 bg-ink-50"><div class="min-w-0 flex-1"><div class="text-[10px] uppercase text-ink-500 truncate" dir="ltr">' + esc(host) + '</div><b class="block text-ink-900 truncate">' + esc(ad.headline || I.noHead) + '</b></div><span class="shrink-0 rounded-md bg-ink-200 text-ink-800 text-xs font-semibold px-3 py-1.5">' + esc(cta) + '</span></div></div>'
      + (ad.headlines && ad.headlines.filter(Boolean).length || ad.bodies && ad.bodies.filter(Boolean).length ? '<p class="text-[11px] text-ink-500 mt-2">' + esc(fmt(I.combos, (1 + (ad.headlines || []).filter(Boolean).length) * (1 + (ad.bodies || []).filter(Boolean).length))) + '</p>' : '')
      + '</section>';
  }

  // ---- (6) تقدير الوصول (بضغطة، عشان ما نستهلكش حدود المنصة)
  function reachBox() {
    var box = document.getElementById('ab-reach'), s = X.sel(); if (!box || s.t !== 'adset') return;
    if (!C.reach) { box.innerHTML = '<p class="text-xs text-ink-500 px-1">' + esc(I.noReach) + '</p>'; return; }
    box.innerHTML = '<section class="card p-4 flex flex-wrap items-center gap-3"><b class="text-sm text-ink-900 me-auto">' + esc(I.reachT) + '</b><span class="rv text-sm text-ink-700"></span><button type="button" class="btn-ghost text-sm rb">' + esc(I.reachBtn) + '</button></section>';
    $('.rb', box).onclick = function () {
      var b = this, rv = $('.rv', box); b.disabled = true; rv.textContent = I.estimating;
      post({ action: 'reach', si: s.s, targeting: D.adSets[s.s].targeting }).then(function (j) {
        b.disabled = false;
        if (!j.ok) { rv.textContent = j.error || I.reachErr; rv.className = 'rv text-sm text-red-700'; return; }
        if (!j.available) { rv.textContent = I.reachNA; return; }
        var f = function (n) { return n == null ? '?' : (+n).toLocaleString('en-US'); };
        rv.innerHTML = '<b dir="ltr">' + esc(f(j.lower) + ' – ' + f(j.upper)) + '</b> ' + esc(I.people) + (j.daily ? ' <span class="text-xs text-ink-500">· ' + esc(fmt(I.dailyReach, f(j.daily))) + '</span>' : '') + (j.ready === false ? ' <span class="text-xs text-amber-700">' + esc(I.notReady) + '</span>' : '');
      });
    };
  }

  // ---- ربط الأزرار بكل رسم
  document.addEventListener('ab:render', function (e) {
    var tools = document.getElementById('ab-tools');
    if (tools && !X.locked && !tools.dataset.on) { tools.dataset.on = '1'; tools.innerHTML = '<button type="button" id="ab-ai-build" class="btn-primary text-sm">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(I.build) + '</button><button type="button" id="ab-ai-review" class="btn-ghost text-sm">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(I.reviewT) + '</button>'; }
    preview();
    if (e.detail && e.detail.soft) return;
    reachBox();
    var s = X.sel(), head = $('#ab-editor section .flex.items-center'); if (!head || X.locked) return;
    if (s.t === 'adset') head.insertAdjacentHTML('beforeend', '<button type="button" id="ab-ai-aud" class="btn-ghost text-xs">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(I.audBtn) + '</button>');
    if (s.t === 'ad') head.insertAdjacentHTML('beforeend', '<button type="button" id="ab-ai-copy" class="btn-ghost text-xs">' + uic('spark', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(I.copyBtn) + '</button>');
  });
  document.addEventListener('click', function (e) {
    var t = e.target.closest('#ab-ai-build, #ab-ai-review, #ab-ai-aud, #ab-ai-copy'); if (!t) return; var s = X.sel();
    if (t.id === 'ab-ai-build') buildDialog(); else if (t.id === 'ab-ai-review') review(); else if (t.id === 'ab-ai-aud') audienceFor(s.s); else copyFor(s.s, s.a);
  });
  X.rerender();
})();
