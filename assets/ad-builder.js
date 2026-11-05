/* منشئ الحملات: حملة ← مجموعات ← إعلانات. الحالة كلها في AB.data، وبتتحفظ لوحدها.
   كل حقل بيظهر حسب AB.caps (قدرات المنصة في زيرنيو)، عشان مفيش اختيار يفشل وقت الإطلاق. */
(function () {
  var A = window.AB, root = document.getElementById('ab-app'); if (!A || !root) return;
  var S = A.s, C = A.caps, D = A.data, locked = !(A.state === 'draft' || A.state === 'failed') || !!(A.progress && (A.progress.done || []).length);
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var fmt = function (s) { var a = [].slice.call(arguments, 1); return String(s || '').replace(/%s/g, function () { return a.length ? a.shift() : ''; }); };
  var uid = function (p) { return p + Math.random().toString(16).slice(2, 8); };
  var clone = function (o) { return JSON.parse(JSON.stringify(o)); };
  var $ = function (s, r) { return (r || root).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || root).querySelectorAll(s)); };
  var sel = { t: 'campaign', s: 0, a: 0 };
  var money = function (n, cur) { try { return new Intl.NumberFormat('en-US', { style: cur ? 'currency' : 'decimal', currency: cur || undefined, maximumFractionDigits: 2 }).format(+n || 0); } catch (e) { return (+n || 0) + ' ' + (cur || ''); } };

  // ---- كل دول العالم بالعربي/الإنجليزي من المتصفح
  var ISO = 'AD AE AF AG AI AL AM AO AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BW BY BZ CA CD CF CG CH CI CK CL CM CN CO CR CU CV CW CY CZ DE DJ DK DM DO DZ EC EE EG ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GT GU GW GY HK HN HR HT HU ID IE IL IM IN IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TG TH TJ TL TM TN TO TR TT TV TW TZ UA UG US UY UZ VA VC VE VG VI VN VU WF WS XK YE YT ZA ZM ZW'.split(' ');
  var dn; try { dn = new Intl.DisplayNames([A.lang === 'ar' ? 'ar' : 'en'], { type: 'region' }); } catch (e) { dn = null; }
  var cname = function (c) { try { return dn ? dn.of(c) : c; } catch (e) { return c; } };
  var flag = function (c) { return c.length === 2 ? String.fromCodePoint.apply(null, c.split('').map(function (x) { return 127397 + x.charCodeAt(0); })) : ''; };
  var GROUPS = { gulf: ['SA', 'AE', 'KW', 'QA', 'BH', 'OM'], mena: ['SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'EG', 'JO', 'LB', 'IQ', 'MA', 'DZ', 'TN', 'LY', 'PS', 'SD', 'YE', 'SY', 'MR'], levant: ['JO', 'LB', 'PS', 'SY', 'IQ'], maghreb: ['MA', 'DZ', 'TN', 'LY', 'MR'], eu: ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'SE', 'DK', 'FI', 'IE', 'PT', 'PL', 'CZ', 'GR', 'RO', 'HU'], na: ['US', 'CA'] };

  // ---- الحفظ التلقائي
  var saveT, saving = false, dirty = false;
  function save(now) {
    if (locked) return; dirty = true; clearTimeout(saveT);
    saveT = setTimeout(function () {
      saving = true; paintSave(S.saving);
      var fd = new FormData(); fd.append('_csrf', A.csrf); fd.append('action', 'save'); fd.append('id', A.id); fd.append('data', JSON.stringify(D));
      fetch(A.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        saving = false; dirty = false; paintSave(j.ok ? fmt(S.savedAt, j.at) : (j.error || S.saveErr), !j.ok);
      }).catch(function () { saving = false; paintSave(S.saveErr, true); });
    }, now ? 0 : 700);
  }
  function paintSave(t, bad) { var e = $('#ab-save'); if (e) { e.textContent = t; e.className = 'text-xs ' + (bad ? 'text-red-600' : 'text-ink-500'); } }
  window.addEventListener('beforeunload', function (e) { if (dirty || saving) { e.preventDefault(); e.returnValue = ''; } });

  // ---- الفحص (قواعد ثابتة + قدرات المنصة)
  function issues() {
    var out = [], c = D.campaign, cbo = c.budgetLevel === 'campaign' && C.cbo;
    var add = function (lvl, msg, t, s, a) { out.push({ lvl: lvl, msg: msg, t: t, s: s, a: a }); };
    if (!String(c.name || '').trim()) add('err', S.iName, 'campaign');
    if (C.goals.indexOf(c.goal) < 0) add('err', S.iGoal, 'campaign');
    if (cbo && !(+c.budget.amount > 0)) add('err', S.iBudget, 'campaign');
    if (c.budget.type === 'lifetime' && cbo && !c.endDate) add('warn', S.iLifeEnd, 'campaign');
    if (c.startDate && c.endDate && c.endDate < c.startDate) add('err', S.iDates, 'campaign');
    if ((c.bidStrategy === 'LOWEST_COST_WITH_BID_CAP' || c.bidStrategy === 'COST_CAP') && !(+c.bidAmount > 0)) add('err', S.iBid, 'campaign');
    if (c.bidStrategy === 'LOWEST_COST_WITH_MIN_ROAS' && !(+c.roasFloor > 0)) add('err', S.iRoas, 'campaign');
    if (!D.adSets.length) add('err', S.iNoSets, 'campaign');
    D.adSets.forEach(function (st, si) {
      var tg = st.targeting;
      if (!cbo && !(+st.budget.amount > 0)) add('err', S.iSetBudget, 'adset', si);
      if (!(tg.countries || []).length && !(tg.regions || []).length && !(tg.cities || []).length) add('err', S.iGeo, 'adset', si);
      if (+tg.ageMax - +tg.ageMin < 5) add('warn', S.iNarrowAge, 'adset', si);
      if (!st.ads.length) add('err', S.iNoAds, 'adset', si);
      if (C.maxAdsPerSet && st.ads.length > C.maxAdsPerSet) add('err', fmt(S.iMaxAds, C.maxAdsPerSet), 'adset', si);
      st.ads.forEach(function (ad, ai) {
        var txt = (ad.headline + ' ' + ad.body + ' ' + (ad.headlines || []).join(' ') + ' ' + (ad.bodies || []).join(' ')).toLowerCase();
        if (!String(ad.body || '').trim() && !(ad.bodies || []).filter(Boolean).length) add('err', S.iBody, 'ad', si, ai);
        if (C.maxHeadline && String(ad.headline || '').length > C.maxHeadline) add('err', fmt(S.iHeadLen, C.maxHeadline), 'ad', si, ai);
        if (ad.format === 'image' && !/^https?:\/\//.test(ad.imageUrl || '')) add('err', S.iImg, 'ad', si, ai);
        if (ad.format === 'video' && !/^https?:\/\//.test(ad.videoUrl || '')) add('err', S.iVid, 'ad', si, ai);
        if (ad.format === 'carousel' && (ad.cards || []).length < 2) add('err', S.iCards, 'ad', si, ai);
        var needLink = ['lead_generation', 'page_visits'].indexOf(c.goal) < 0 && ['WHATSAPP_MESSAGE', 'MESSAGE_PAGE', 'INSTAGRAM_MESSAGE', 'CALL_NOW', 'LIKE_PAGE'].indexOf(ad.cta) < 0 && !ad.leadGenFormId;
        if (needLink && !/^https:\/\//.test(ad.linkUrl || '')) add('err', S.iLink, 'ad', si, ai);
        if (c.goal === 'lead_generation' && C.leadForm && !ad.leadGenFormId) add('err', S.iLeadForm, 'ad', si, ai);
        if (ad.cta === 'WHATSAPP_MESSAGE' && !/^\+\d{8,15}$/.test(ad.whatsappPhoneNumber || '')) add('err', S.iWa, 'ad', si, ai);
        var rw = (S.riskWords || []).filter(function (w) { return txt.indexOf(w) >= 0; })[0]; if (rw) add('warn', fmt(S.iRisk, rw), 'ad', si, ai);
      });
    });
    // تداخل الجماهير بين المجموعات (نفس الدول ونفس السن ومن غير اهتمامات مختلفة)
    for (var i = 0; i < D.adSets.length; i++) for (var j = i + 1; j < D.adSets.length; j++) {
      var a = D.adSets[i].targeting, b = D.adSets[j].targeting, key = function (t) { return [t.countries.slice().sort().join(','), t.ageMin, t.ageMax, t.gender, (t.interests || []).map(function (x) { return x.id; }).sort().join(','), t.audienceId].join('|'); };
      if (key(a) === key(b)) add('warn', fmt(S.iOverlap, setName(i), setName(j)), 'adset', j);
    }
    return out;
  }
  var setName = function (si) { return D.adSets[si].name || fmt(S.setN, si + 1); };
  var adName = function (si, ai) { return D.adSets[si].ads[ai].name || fmt(S.adN, ai + 1); };

  // ---- الرسم
  function render() {
    var iss = issues(), errs = iss.filter(function (x) { return x.lvl === 'err'; }).length;
    root.innerHTML = '<div class="flex flex-wrap items-center gap-2 mb-4">'
      + '<a href="' + esc(A.listUrl) + '" class="link text-sm">' + esc(S.back) + '</a><span class="text-ink-300">/</span>'
      + '<b class="text-ink-900 truncate max-w-[40ch]">' + esc(D.campaign.name || S.untitled) + '</b>'
      + '<span class="text-[11px] rounded bg-ink-100 text-ink-700 px-1.5 py-0.5">' + esc(A.platformLabel) + ' · ' + esc(D.accName) + ' · ' + esc(D.currency) + '</span>'
      + (locked ? '<span class="text-[11px] rounded bg-lime-100 text-lime-800 px-1.5 py-0.5">' + esc(S.lockedTag) + '</span>' : '')
      + '<span id="ab-save" class="text-xs text-ink-500 ms-auto">' + esc(locked ? '' : S.autosave) + '</span>'
      + '<span id="ab-tools" class="flex gap-2"></span></div>'
      + '<div class="grid gap-4 xl:grid-cols-[280px_minmax(0,1fr)_340px] items-start">'
      + '<aside class="card p-3 xl:sticky xl:top-4" id="ab-tree">' + tree(iss) + '</aside>'
      + '<main id="ab-editor" class="space-y-4">' + editor() + '</main>'
      + '<aside class="space-y-4 xl:sticky xl:top-4" id="ab-side">' + side(iss, errs) + '</aside></div>';
    if (locked) $$('#ab-editor input, #ab-editor select, #ab-editor textarea, #ab-editor button[data-x]').forEach(function (x) { x.disabled = true; });
    document.dispatchEvent(new CustomEvent('ab:render', { detail: { issues: iss } }));
  }
  function badge(iss, t, s, a) { var n = iss.filter(function (x) { return x.lvl === 'err' && x.t === t && (s == null || x.s === s) && (a == null || x.a === a); }).length; return n ? '<span class="ms-auto text-[10px] rounded-full bg-red-600 text-white px-1.5">' + n + '</span>' : ''; }
  function tree(iss) {
    var on = function (t, s, a) { return sel.t === t && (s == null || sel.s === s) && (a == null || sel.a === a); };
    var item = function (t, s, a, label, sub, ico, lvl) {
      return '<div class="group flex items-center gap-1.5 rounded-lg ' + (on(t, s, a) ? 'bg-brand-50 text-brand-800' : 'hover:bg-ink-50 text-ink-700') + '" style="padding-inline-start:' + (lvl * 14 + 6) + 'px">'
        + '<button type="button" data-go="' + t + '|' + (s == null ? '' : s) + '|' + (a == null ? '' : a) + '" class="flex-1 min-w-0 flex items-center gap-2 py-1.5 text-start text-sm"><span class="shrink-0 text-ink-400">' + ico + '</span><span class="truncate">' + esc(label) + '</span>' + (sub ? '<span class="text-[11px] text-ink-400 shrink-0">' + esc(sub) + '</span>' : '') + badge(iss, t, s, a) + '</button>'
        + (t === 'campaign' || locked ? '' : '<span class="hidden group-hover:flex shrink-0"><button type="button" data-x="dup|' + t + '|' + s + '|' + (a == null ? '' : a) + '" class="p-1 text-ink-400 hover:text-ink-900" title="' + esc(S.duplicate) + '">⧉</button><button type="button" data-x="del|' + t + '|' + s + '|' + (a == null ? '' : a) + '" class="p-1 text-ink-400 hover:text-red-600" title="' + esc(S.del) + '">✕</button></span>') + '</div>';
    };
    var cbo = D.campaign.budgetLevel === 'campaign' && C.cbo;
    var h = item('campaign', null, null, D.campaign.name || S.untitled, cbo && D.campaign.budget.amount ? money(D.campaign.budget.amount, D.currency) : '', '▣', 0);
    D.adSets.forEach(function (st, si) {
      h += item('adset', si, null, setName(si), !cbo && st.budget.amount ? money(st.budget.amount, D.currency) : '', '◫', 1);
      st.ads.forEach(function (ad, ai) { h += item('ad', si, ai, adName(si, ai), ad.format === 'video' ? '▶' : ad.format === 'carousel' ? '⧉' : '', '▭', 2); });
      if (!locked) h += '<button type="button" data-x="addad|' + si + '" class="text-xs link py-1" style="padding-inline-start:' + (2 * 14 + 10) + 'px">+ ' + esc(S.addAd) + '</button>';
    });
    if (!locked) h += '<button type="button" data-x="addset" class="mt-2 w-full rounded-lg border border-dashed border-ink-300 text-sm text-ink-600 py-2 hover:bg-ink-50">+ ' + esc(S.addSet) + '</button>';
    return h;
  }

  // ---- حقول مساعدة
  var field = function (label, inner, hint) { return '<label class="block"><span class="label">' + esc(label) + '</span>' + inner + (hint ? '<span class="block text-[11px] text-ink-500 mt-1">' + esc(hint) + '</span>' : '') + '</label>'; };
  var inp = function (path, val, attrs) { return '<input class="input" data-p="' + path + '" value="' + esc(val == null ? '' : val) + '" ' + (attrs || '') + '>'; };
  var selBox = function (path, val, opts) { return '<select class="input" data-p="' + path + '">' + opts.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(val) ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('') + '</select>'; };
  var card = function (title, body, extra) { return '<section class="card p-5 space-y-4"><div class="flex items-center gap-2"><h3 class="font-bold text-ink-900 me-auto">' + esc(title) + '</h3>' + (extra || '') + '</div>' + body + '</section>'; };
  var chips = function (items, rmKey) { return '<div class="flex flex-wrap gap-1.5">' + items.map(function (x, i) { return '<span class="inline-flex items-center gap-1 rounded-full bg-brand-50 text-brand-800 text-xs px-2.5 py-1">' + x[0] + (locked ? '' : '<button type="button" data-x="rm|' + rmKey + '|' + i + '" class="opacity-60 hover:opacity-100" aria-label="' + esc(S.del) + '">×</button>') + '</span>'; }).join('') + '</div>'; };

  function editor() {
    if (sel.t === 'campaign') return campEditor();
    if (sel.t === 'adset') return setEditor(sel.s);
    return adEditor(sel.s, sel.a);
  }
  function campEditor() {
    var c = D.campaign, cbo = c.budgetLevel === 'campaign';
    var goals = '<div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">' + C.goals.map(function (g) { var on = c.goal === g; return '<button type="button" data-set="campaign.goal" data-v="' + g + '" class="text-start rounded-xl border p-3 transition ' + (on ? 'border-brand-500 bg-brand-50' : 'border-ink-200 hover:border-ink-300') + '"><b class="block text-sm text-ink-900">' + esc((S.goals[g] || [g])[0]) + '</b><span class="text-[11px] text-ink-500">' + esc((S.goals[g] || ['', ''])[1]) + '</span></button>'; }).join('') + '</div>';
    var bud = (C.cbo ? '<div class="seg w-max">' + ['adset', 'campaign'].map(function (l) { return '<a href="#" data-set="campaign.budgetLevel" data-v="' + l + '" class="' + (c.budgetLevel === l ? 'on' : '') + '">' + esc(l === 'campaign' ? S.cbo : S.abo) + '</a>'; }).join('') + '</div><p class="text-[11px] text-ink-500">' + esc(cbo ? S.cboHint : S.aboHint) + '</p>' : '<p class="text-[11px] text-ink-500">' + esc(S.aboOnly) + '</p>')
      + (cbo && C.cbo ? '<div class="grid gap-3 sm:grid-cols-2">' + field(S.amount + ' (' + D.currency + ')', inp('campaign.budget.amount', c.budget.amount, 'type="number" min="1" step="0.01" dir="ltr"')) + field(S.budgetType, selBox('campaign.budget.type', c.budget.type, C.budgetTypes.map(function (t) { return [t, S[t]]; }))) + '</div>' : '');
    var bid = C.bid.length > 1 ? '<div class="grid gap-3 sm:grid-cols-2">' + field(S.bidStrategy, selBox('campaign.bidStrategy', c.bidStrategy, C.bid.map(function (b) { return [b, (S.bids[b] || b)]; })), (S.bidHints || {})[c.bidStrategy])
      + ((c.bidStrategy === 'LOWEST_COST_WITH_BID_CAP' || c.bidStrategy === 'COST_CAP') ? field(S.bidAmount + ' (' + D.currency + ')', inp('campaign.bidAmount', c.bidAmount, 'type="number" min="0" step="0.01" dir="ltr"')) : '')
      + (c.bidStrategy === 'LOWEST_COST_WITH_MIN_ROAS' ? field(S.roasFloor, inp('campaign.roasFloor', c.roasFloor, 'type="number" min="0" step="0.01" dir="ltr" placeholder="2.5"')) : '') + '</div>' : '';
    var dates = '<div class="grid gap-3 sm:grid-cols-2">' + field(S.start, inp('campaign.startDate', c.startDate, 'type="datetime-local"')) + field(S.end, inp('campaign.endDate', c.endDate, 'type="datetime-local"')) + '</div>';
    var special = C.special && C.special.length ? field(S.special, '<div class="flex flex-wrap gap-2">' + C.special.map(function (x) { var on = (c.specialAdCategories || []).indexOf(x) >= 0; return '<label class="chip ' + (on ? 'on' : '') + '"><input type="checkbox" class="hidden" data-toggle="campaign.specialAdCategories" value="' + x + '"' + (on ? ' checked' : '') + '>' + esc(S.specials[x] || x) + '</label>'; }).join('') + '</div>', S.specialHint) : '';
    var notes = (C.notes || []).map(function (n) { return '<p class="text-xs rounded-lg bg-amber-50 text-amber-900 px-3 py-2">' + esc((S.notesT || {})[n] || n) + '</p>'; }).join('');
    return card(S.campaign, notes + field(S.campName, inp('campaign.name', c.name, 'maxlength="200"'))) + card(S.goal, goals) + card(S.budgetBid, bud + bid) + card(S.schedule, dates + special);
  }
  function setEditor(si) {
    var st = D.adSets[si], tg = st.targeting, cbo = D.campaign.budgetLevel === 'campaign' && C.cbo, T = C.targeting, P = 'adSets.' + si;
    var head = card(S.adset, '<div class="grid gap-3 sm:grid-cols-2">' + field(S.setName, inp(P + '.name', st.name, 'maxlength="200" placeholder="' + esc(fmt(S.setN, si + 1)) + '"'))
      + (cbo ? '<p class="text-xs text-ink-500 self-end">' + esc(S.usesCbo) + '</p>' : field(S.amount + ' (' + D.currency + ')', inp(P + '.budget.amount', st.budget.amount, 'type="number" min="1" step="0.01" dir="ltr"')) + field(S.budgetType, selBox(P + '.budget.type', st.budget.type, C.budgetTypes.map(function (t) { return [t, S[t]]; })))) + '</div>');
    // الأماكن
    var geo = '<div class="flex flex-wrap gap-1.5">' + Object.keys(GROUPS).map(function (g) { return '<button type="button" data-x="grp|' + si + '|' + g + '" class="rounded-full border border-dashed border-ink-300 text-xs px-2.5 py-1 hover:bg-ink-50">+ ' + esc(S.groups[g]) + '</button>'; }).join('') + '</div>'
      + '<div class="relative"><input class="input ab-cty" data-si="' + si + '" data-k="countries" placeholder="' + esc(S.searchCountry) + '" autocomplete="off"><div class="ab-dd hidden absolute z-30 inset-x-0 mt-1 card max-h-64 overflow-y-auto"></div></div>'
      + chips(tg.countries.map(function (c) { return [flag(c) + ' ' + esc(cname(c))]; }), P + '.targeting.countries')
      + (T.regions || T.cities ? '<div class="relative">' + field(S.regionsCities, '<input class="input ab-geo" data-si="' + si + '" placeholder="' + esc(S.searchGeo) + '" autocomplete="off"><div class="ab-dd hidden absolute z-30 inset-x-0 mt-1 card max-h-64 overflow-y-auto"></div>', S.geoHint) + '</div>'
        + chips(tg.regions.concat(tg.cities).map(function (g) { return [esc(g.name) + ' <span class="opacity-60">' + esc(g.type === 'city' ? S.city : S.region) + (g.radius ? ' · ' + g.radius + 'km' : '') + '</span>']; }), P + '.targeting.geo') : '')
      + (T.excludeCountries ? '<details class="text-sm"><summary class="cursor-pointer text-ink-600">' + esc(S.exclude) + ' (' + tg.excludeCountries.length + ')</summary><div class="mt-2 space-y-2"><div class="relative"><input class="input ab-cty" data-si="' + si + '" data-k="excludeCountries" placeholder="' + esc(S.searchCountry) + '" autocomplete="off"><div class="ab-dd hidden absolute z-30 inset-x-0 mt-1 card max-h-64 overflow-y-auto"></div></div>' + chips(tg.excludeCountries.map(function (c) { return [flag(c) + ' ' + esc(cname(c))]; }), P + '.targeting.excludeCountries') + '</div></details>' : '');
    // الناس
    var people = '<div class="grid gap-3 sm:grid-cols-3">' + field(S.ageMin, inp(P + '.targeting.ageMin', tg.ageMin, 'type="number" min="13" max="65"')) + field(S.ageMax, inp(P + '.targeting.ageMax', tg.ageMax, 'type="number" min="13" max="65"'))
      + field(S.gender, selBox(P + '.targeting.gender', tg.gender, [['all', S.gAll], ['male', S.gMale], ['female', S.gFemale]])) + '</div>'
      + (T.interests ? field(S.interests, '<div class="relative"><input class="input ab-int" data-si="' + si + '" data-dim="interest" placeholder="' + esc(S.searchInterest) + '" autocomplete="off"><div class="ab-dd hidden absolute z-30 inset-x-0 mt-1 card max-h-64 overflow-y-auto"></div></div>', S.interestHint) + chips(tg.interests.map(function (x) { return [esc(x.name) + (x.size ? ' <span class="opacity-60">' + Math.round(x.size / 1e6) + 'M</span>' : '')]; }), P + '.targeting.interests') : '')
      + (T.work ? field(S.workPositions, '<div class="relative"><input class="input ab-int" data-si="' + si + '" data-dim="work_position" data-k="workPositions" placeholder="' + esc(S.searchWork) + '" autocomplete="off"><div class="ab-dd hidden absolute z-30 inset-x-0 mt-1 card max-h-64 overflow-y-auto"></div></div>') + chips(tg.workPositions.map(function (x) { return [esc(x.name)]; }), P + '.targeting.workPositions') : '');
    // الجمهور المخصص
    var aud = T.audience ? field(S.customAud, '<select class="input ab-aud" data-p="' + P + '.targeting.audienceId"><option value="">' + esc(S.none) + '</option>' + (window.__abAud || []).map(function (a) { return '<option value="' + esc(a.id) + '"' + (a.id === tg.audienceId ? ' selected' : '') + '>' + esc(a.name) + '</option>'; }).join('') + '</select>', S.audHint) : '';
    // أماكن الظهور
    var pl = T.placements && T.placements.length ? '<div class="seg w-max">' + ['auto', 'manual'].map(function (m) { return '<a href="#" data-set="' + P + '.targeting.placementsMode" data-v="' + m + '" class="' + (tg.placementsMode === m ? 'on' : '') + '">' + esc(m === 'auto' ? S.plAuto : S.plManual) + '</a>'; }).join('') + '</div>'
      + (tg.placementsMode === 'manual' ? '<div class="grid gap-2 sm:grid-cols-2">' + T.placements.map(function (p) { var on = tg.placements.indexOf(p) >= 0; return '<label class="flex items-center gap-2 text-sm rounded-lg border border-ink-200 px-3 py-2"><input type="checkbox" data-toggle="' + P + '.targeting.placements" value="' + esc(p) + '"' + (on ? ' checked' : '') + '>' + esc(S.placementsL[p] || p) + '</label>'; }).join('') + '</div>' : '<p class="text-[11px] text-ink-500">' + esc(S.plAutoHint) + '</p>') : '';
    var dev = T.devices ? field(S.devices, '<div class="flex flex-wrap gap-2">' + ['mobile', 'desktop'].map(function (x) { var on = tg.userDevice.indexOf(x) >= 0; return '<label class="chip ' + (on ? 'on' : '') + '"><input type="checkbox" class="hidden" data-toggle="' + P + '.targeting.userDevice" value="' + x + '"' + (on ? ' checked' : '') + '>' + esc(S['dev_' + x]) + '</label>'; }).join('') + '</div>', S.devHint) : '';
    return head + card(S.locations, geo) + card(S.people, people + aud) + (pl || dev ? card(S.placementsT, pl + dev) : '') + '<div id="ab-reach"></div>';
  }
  function adEditor(si, ai) {
    var ad = D.adSets[si].ads[ai], P = 'adSets.' + si + '.ads.' + ai;
    var fmts = '<div class="seg w-max">' + C.formats.map(function (f) { return '<a href="#" data-set="' + P + '.format" data-v="' + f + '" class="' + (ad.format === f ? 'on' : '') + '">' + esc(S.formats[f]) + '</a>'; }).join('') + '</div>';
    var media = ad.format === 'image' ? field(S.imageUrl, inp(P + '.imageUrl', ad.imageUrl, 'type="url" dir="ltr" placeholder="https://" data-media="image"'), S.imgHint)
      : ad.format === 'video' ? '<div class="grid gap-3 sm:grid-cols-2">' + field(S.videoUrl, inp(P + '.videoUrl', ad.videoUrl, 'type="url" dir="ltr" placeholder="https://" data-media="video"')) + field(S.thumbUrl, inp(P + '.thumbnailUrl', ad.thumbnailUrl, 'type="url" dir="ltr" placeholder="https://" data-media="image"')) + '</div>'
      : '<div class="space-y-3">' + (ad.cards || []).map(function (cd, k) { var Q = P + '.cards.' + k; return '<div class="rounded-xl border border-ink-200 p-3 grid gap-2 sm:grid-cols-2"><b class="text-xs text-ink-500 sm:col-span-2 flex">' + esc(fmt(S.cardN, k + 1)) + (locked ? '' : '<button type="button" data-x="rmcard|' + si + '|' + ai + '|' + k + '" class="ms-auto text-red-600">✕</button>') + '</b>' + field(S.imageUrl, inp(Q + '.imageUrl', cd.imageUrl, 'type="url" dir="ltr" data-media="image"')) + field(S.linkUrl, inp(Q + '.linkUrl', cd.linkUrl, 'type="url" dir="ltr"')) + field(S.headline, inp(Q + '.headline', cd.headline, 'maxlength="' + (C.maxHeadline || 255) + '"')) + field(S.cardDesc, inp(Q + '.description', cd.description)) + '</div>'; }).join('')
        + (locked || (ad.cards || []).length >= 10 ? '' : '<button type="button" data-x="addcard|' + si + '|' + ai + '" class="btn-ghost text-sm">+ ' + esc(S.addCard) + '</button>') + '</div>';
    var variants = function (k, label, max) {
      var list = ad[k] || []; return C.multiText ? field(label, '<div class="space-y-2">' + list.map(function (v, i) { return '<div class="flex gap-2">' + (k === 'bodies' ? '<textarea rows="2" class="input" data-p="' + P + '.' + k + '.' + i + '">' + esc(v) + '</textarea>' : '<input class="input" data-p="' + P + '.' + k + '.' + i + '" value="' + esc(v) + '" maxlength="' + (max || 255) + '">') + (locked ? '' : '<button type="button" data-x="rmvar|' + si + '|' + ai + '|' + k + '|' + i + '" class="text-ink-400 hover:text-red-600 px-2">✕</button>') + '</div>'; }).join('') + (locked || list.length >= 5 ? '' : '<button type="button" data-x="addvar|' + si + '|' + ai + '|' + k + '" class="link text-xs">+ ' + esc(S.addVariant) + '</button>') + '</div>', S.variantHint) : '';
    };
    var copy = field(S.headline, inp(P + '.headline', ad.headline, 'maxlength="' + (C.maxHeadline || 255) + '"'), C.maxHeadline ? fmt(S.maxChars, C.maxHeadline) : '')
      + field(S.body, '<textarea rows="4" class="input" data-p="' + P + '.body">' + esc(ad.body) + '</textarea>', S.bodyHint) + variants('headlines', S.moreHeadlines, C.maxHeadline) + variants('bodies', S.moreBodies);
    var cta = '<div class="grid gap-3 sm:grid-cols-2">' + field(S.cta, selBox(P + '.cta', ad.cta, C.ctas.map(function (x) { return [x, S.ctas[x] || x]; }))) + field(S.linkUrl, inp(P + '.linkUrl', ad.linkUrl, 'type="url" dir="ltr" placeholder="https://"')) + '</div>'
      + (ad.cta === 'WHATSAPP_MESSAGE' && C.whatsapp ? field(S.waNumber, inp(P + '.whatsappPhoneNumber', ad.whatsappPhoneNumber, 'dir="ltr" placeholder="+9665..."'), S.waHint) : '')
      + (C.leadForm && D.campaign.goal === 'lead_generation' ? field(S.leadForm, inp(P + '.leadGenFormId', ad.leadGenFormId, 'dir="ltr"'), S.leadFormHint) : '');
    return card(S.ad, '<div class="grid gap-3 sm:grid-cols-2">' + field(S.adName, inp(P + '.name', ad.name, 'maxlength="200" placeholder="' + esc(fmt(S.adN, ai + 1)) + '"')) + '<div><span class="label">' + esc(S.format) + '</span><div class="mt-1.5">' + fmts + '</div></div></div>')
      + card(S.media, media) + card(S.copy, copy) + card(S.ctaT, cta);
  }
  function side(iss, errs) {
    var c = D.campaign, cbo = c.budgetLevel === 'campaign' && C.cbo, nAds = 0, daily = 0;
    D.adSets.forEach(function (st) { nAds += st.ads.length; if (!cbo && st.budget.type === 'daily') daily += +st.budget.amount || 0; });
    if (cbo && c.budget.type === 'daily') daily = +c.budget.amount || 0;
    var sum = '<section class="card p-4 text-sm space-y-1.5"><b class="block text-ink-900 mb-1">' + esc(S.summary) + '</b>'
      + '<div class="flex justify-between"><span class="text-ink-500">' + esc(S.structure) + '</span><span>' + esc(fmt(S.structTxt, D.adSets.length, nAds)) + '</span></div>'
      + '<div class="flex justify-between"><span class="text-ink-500">' + esc(S.goal) + '</span><span>' + esc((S.goals[c.goal] || [c.goal])[0]) + '</span></div>'
      + (daily ? '<div class="flex justify-between"><span class="text-ink-500">' + esc(S.dailyTotal) + '</span><b dir="ltr">' + esc(money(daily, D.currency)) + '</b></div><div class="flex justify-between"><span class="text-ink-500">' + esc(S.monthly) + '</span><span dir="ltr">' + esc(money(daily * 30, D.currency)) + '</span></div>' : '')
      + '</section>';
    var chk = '<section class="card p-4"><div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900 me-auto">' + esc(S.checks) + '</b><span class="text-xs ' + (errs ? 'text-red-600' : 'text-lime-700') + '">' + esc(errs ? fmt(S.nErr, errs) : S.allOk) + '</span></div>'
      + (iss.length ? '<ul class="space-y-1.5 max-h-80 overflow-y-auto">' + iss.map(function (x) { var where = x.t === 'campaign' ? S.campaign : x.t === 'adset' ? setName(x.s) : setName(x.s) + ' › ' + adName(x.s, x.a); return '<li><button type="button" data-go="' + x.t + '|' + (x.s == null ? '' : x.s) + '|' + (x.a == null ? '' : x.a) + '" class="w-full text-start rounded-lg px-2.5 py-1.5 text-xs ' + (x.lvl === 'err' ? 'bg-red-50 text-red-800' : 'bg-amber-50 text-amber-900') + '"><b>' + esc(where) + ':</b> ' + esc(x.msg) + '</button></li>'; }).join('') + '</ul>' : '<p class="text-xs text-ink-500">' + esc(S.noIssues) + '</p>') + '</section>';
    return '<div id="ab-preview"></div>' + sum + chk + '<div id="ab-launch"></div>';
  }

  // ---- مسارات البيانات
  function getP(path) { return path.split('.').reduce(function (o, k) { return o == null ? o : o[k]; }, D); }
  function setP(path, v) { var ks = path.split('.'), o = D; for (var i = 0; i < ks.length - 1; i++) o = o[ks[i]]; o[ks[ks.length - 1]] = v; }
  var keepFocus = function () { var a = document.activeElement; return a && a.dataset && a.dataset.p ? { p: a.dataset.p, s: a.selectionStart, e: a.selectionEnd } : null; };
  function rerender() { var f = keepFocus(); render(); if (f) { var el = root.querySelector('[data-p="' + f.p + '"]'); if (el) { el.focus(); try { el.setSelectionRange(f.s, f.e); } catch (e) {} } } }
  var rt; function soft() { clearTimeout(rt); rt = setTimeout(function () { var t = $('#ab-tree'), side2 = $('#ab-side'); var iss = issues(); if (t) t.innerHTML = tree(iss); if (side2) side2.innerHTML = side(iss, iss.filter(function (x) { return x.lvl === 'err'; }).length); document.dispatchEvent(new CustomEvent('ab:render', { detail: { issues: iss, soft: true } })); }, 150); }

  root.addEventListener('input', function (e) {
    var p = e.target.dataset.p; if (!p || locked) return;
    var v = e.target.type === 'number' ? (e.target.value === '' ? '' : +e.target.value) : e.target.value; setP(p, v); save(); soft();
  });
  root.addEventListener('change', function (e) {
    var tg = e.target.dataset.toggle; if (tg && !locked) { var arr = getP(tg) || []; var i = arr.indexOf(e.target.value); if (e.target.checked && i < 0) arr.push(e.target.value); if (!e.target.checked && i >= 0) arr.splice(i, 1); setP(tg, arr); save(); rerender(); return; }
    if (e.target.tagName === 'SELECT' && e.target.dataset.p) { save(); rerender(); }
  });
  root.addEventListener('click', function (e) {
    var go = e.target.closest('[data-go]'), st = e.target.closest('[data-set]'), x = e.target.closest('[data-x]');
    if (go) { var g = go.dataset.go.split('|'); sel = { t: g[0], s: g[1] === '' ? 0 : +g[1], a: g[2] === '' ? 0 : +g[2] }; render(); window.scrollTo({ top: root.offsetTop - 10, behavior: 'smooth' }); return; }
    if (st && !locked) { e.preventDefault(); setP(st.dataset.set, st.dataset.v); if (st.dataset.set.slice(-7) === '.format') { var ad = getP(st.dataset.set.replace(/\.format$/, '')); if (st.dataset.v === 'carousel' && !(ad.cards || []).length) ad.cards = [{ imageUrl: '', linkUrl: '', headline: '', description: '' }, { imageUrl: '', linkUrl: '', headline: '', description: '' }]; } save(); rerender(); return; }
    if (!x || locked) return; var a = x.dataset.x.split('|'), cmd = a[0];
    if (cmd === 'addset') { var base = D.adSets[D.adSets.length - 1]; var ns = base ? clone(base) : null; if (ns) { ns.id = uid('s'); ns.name = ''; ns.ads = [newAd()]; } D.adSets.push(ns || { id: uid('s'), name: '', budget: { amount: '', type: 'daily' }, targeting: { countries: [A.myCountry], excludeCountries: [], regions: [], cities: [], ageMin: 18, ageMax: 65, gender: 'all', languages: [], interests: [], behaviors: [], workPositions: [], workIndustries: [], audienceId: '', audienceExclude: [], placementsMode: 'auto', placements: [], userDevice: [], userOs: [] }, ads: [newAd()] }); sel = { t: 'adset', s: D.adSets.length - 1, a: 0 }; }
    else if (cmd === 'addad') { var si = +a[1]; var prev = D.adSets[si].ads[D.adSets[si].ads.length - 1]; var na = prev ? clone(prev) : newAd(); na.id = uid('a'); na.name = ''; D.adSets[si].ads.push(na); sel = { t: 'ad', s: si, a: D.adSets[si].ads.length - 1 }; }
    else if (cmd === 'dup') { if (a[1] === 'adset') { var c2 = clone(D.adSets[+a[2]]); c2.id = uid('s'); c2.name = (c2.name || setName(+a[2])) + ' ' + S.copySuffix; c2.ads.forEach(function (q) { q.id = uid('a'); }); D.adSets.splice(+a[2] + 1, 0, c2); sel = { t: 'adset', s: +a[2] + 1, a: 0 }; } else { var c3 = clone(D.adSets[+a[2]].ads[+a[3]]); c3.id = uid('a'); c3.name = (c3.name || adName(+a[2], +a[3])) + ' ' + S.copySuffix; D.adSets[+a[2]].ads.splice(+a[3] + 1, 0, c3); sel = { t: 'ad', s: +a[2], a: +a[3] + 1 }; } }
    else if (cmd === 'del') { if (!confirm(S.delQ)) return; if (a[1] === 'adset') { D.adSets.splice(+a[2], 1); sel = { t: 'campaign', s: 0, a: 0 }; } else { D.adSets[+a[2]].ads.splice(+a[3], 1); sel = { t: 'adset', s: +a[2], a: 0 }; } }
    else if (cmd === 'rm') { var key = a[1], i = +a[2]; if (/\.targeting\.geo$/.test(key)) { var tgt = getP(key.replace(/\.geo$/, '')); var all = tgt.regions.concat(tgt.cities); var it = all[i]; tgt.regions = tgt.regions.filter(function (q) { return q !== it; }); tgt.cities = tgt.cities.filter(function (q) { return q !== it; }); } else getP(key).splice(i, 1); }
    else if (cmd === 'grp') { var t2 = D.adSets[+a[1]].targeting; GROUPS[a[2]].forEach(function (c) { if (t2.countries.indexOf(c) < 0) t2.countries.push(c); }); }
    else if (cmd === 'addcard') { D.adSets[+a[1]].ads[+a[2]].cards.push({ imageUrl: '', linkUrl: '', headline: '', description: '' }); }
    else if (cmd === 'rmcard') { D.adSets[+a[1]].ads[+a[2]].cards.splice(+a[3], 1); }
    else if (cmd === 'addvar') { var ad2 = D.adSets[+a[1]].ads[+a[2]]; (ad2[a[3]] = ad2[a[3]] || []).push(''); }
    else if (cmd === 'rmvar') { D.adSets[+a[1]].ads[+a[2]][a[3]].splice(+a[4], 1); }
    else return;
    save(); rerender();
  });
  function newAd() { return { id: uid('a'), name: '', format: C.formats[0] || 'image', headline: '', body: '', headlines: [], bodies: [], cta: C.ctas[0] || 'LEARN_MORE', linkUrl: '', imageUrl: '', videoUrl: '', thumbnailUrl: '', cards: [], leadGenFormId: '', whatsappPhoneNumber: '' }; }

  // ---- القوائم المنسدلة: دول (محلي)، مناطق/مدن واهتمامات (من المنصة، قراءة بس)
  var ddT;
  root.addEventListener('input', function (e) {
    var el = e.target, dd = el.parentNode.querySelector('.ab-dd'); if (!dd) return;
    var q = el.value.trim().toLowerCase();
    if (el.classList.contains('ab-cty')) {
      var tg = D.adSets[+el.dataset.si].targeting, k = el.dataset.k, have = tg[k];
      var res = ISO.filter(function (c) { return have.indexOf(c) < 0 && (!q || cname(c).toLowerCase().indexOf(q) >= 0 || c.toLowerCase() === q); }).slice(0, 30);
      dd.innerHTML = res.map(function (c) { return '<button type="button" data-cpick="' + el.dataset.si + '|' + k + '|' + c + '" class="block w-full text-start px-3 py-2 text-sm hover:bg-ink-50">' + flag(c) + ' ' + esc(cname(c)) + ' <span class="text-ink-400 text-xs">' + c + '</span></button>'; }).join('') || '<p class="p-3 text-xs text-ink-500">' + esc(S.noRes) + '</p>';
      dd.classList.remove('hidden'); return;
    }
    if (el.classList.contains('ab-geo') || el.classList.contains('ab-int')) {
      clearTimeout(ddT); if (q.length < 2) { dd.classList.add('hidden'); return; }
      var dim = el.classList.contains('ab-geo') ? 'geo' : (el.dataset.dim || 'interest');
      ddT = setTimeout(function () {
        dd.innerHTML = '<p class="p-3 text-xs text-ink-500">' + esc(S.searching) + '</p>'; dd.classList.remove('hidden');
        fetch(A.geoUrl + '&dim=' + encodeURIComponent(dim) + '&q=' + encodeURIComponent(el.value.trim()) + '&conn=' + encodeURIComponent(D.connId) + '&acc=' + encodeURIComponent(D.adAccountId), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          var list = (j.results || []).slice(0, 25); el._res = list;
          dd.innerHTML = j.error ? '<p class="p-3 text-xs text-red-700">' + esc(j.error) + '</p>' : list.length ? list.map(function (r, i) { return '<button type="button" data-rpick="' + i + '" class="block w-full text-start px-3 py-2 text-sm hover:bg-ink-50">' + esc(r.name) + ' <span class="text-ink-400 text-xs">' + esc([r.type, r.cc, r.path, r.size ? Math.round(r.size / 1e6) + 'M' : ''].filter(Boolean).join(' · ')) + '</span></button>'; }).join('') : '<p class="p-3 text-xs text-ink-500">' + esc(S.noRes) + '</p>';
        }).catch(function () { dd.innerHTML = '<p class="p-3 text-xs text-red-700">' + esc(S.searchErr) + '</p>'; });
      }, 300);
    }
  });
  root.addEventListener('focusin', function (e) { if (e.target.classList.contains('ab-cty')) e.target.dispatchEvent(new Event('input', { bubbles: true })); });
  root.addEventListener('click', function (e) {
    var cp = e.target.closest('[data-cpick]'), rp = e.target.closest('[data-rpick]');
    if (cp) { var a = cp.dataset.cpick.split('|'); var arr = D.adSets[+a[0]].targeting[a[1]]; if (arr.indexOf(a[2]) < 0) arr.push(a[2]); save(); rerender(); var el2 = root.querySelector('.ab-cty[data-k="' + a[1] + '"]'); if (el2) el2.focus(); return; }
    if (rp) {
      var box = rp.closest('.relative'), el = box.querySelector('input'), r = (el._res || [])[+rp.dataset.rpick], tg = D.adSets[+el.dataset.si].targeting; if (!r) return;
      if (el.classList.contains('ab-geo')) { var item = { key: r.id, name: r.name + (r.cc ? ' (' + r.cc + ')' : ''), type: /city/i.test(r.type) ? 'city' : 'region', cc: r.cc }; if (item.type === 'city') { var rad = prompt(S.radiusQ, '0'); if (rad && +rad > 0) item.radius = Math.min(80, +rad); tg.cities.push(item); } else tg.regions.push(item); }
      else { var k = el.dataset.k || 'interests'; if (!tg[k].some(function (x) { return x.id === r.id; })) tg[k].push({ id: r.id, name: r.name, size: r.size || null }); }
      save(); rerender(); return;
    }
    if (!e.target.closest('.relative')) $$('.ab-dd').forEach(function (d) { d.classList.add('hidden'); });
  });

  // ---- الجماهير المخصصة (قراءة بس)
  if (C.targeting.audience) fetch(A.url + '&ajax=audiences&id=' + A.id, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { window.__abAud = j.audiences || []; if (sel.t === 'adset') rerender(); }).catch(function () {});

  window.ABX = { D: D, A: A, S: S, C: C, save: save, rerender: rerender, issues: issues, sel: function () { return sel; }, go: function (t, s, a) { sel = { t: t, s: s || 0, a: a || 0 }; render(); }, esc: esc, fmt: fmt, money: money, clone: clone, uid: uid, newAd: newAd, cname: cname, flag: flag, locked: locked };
  render();
})();
