/* أدوات كتابة المنشور (من غير AI): #1 قوالب حسب المجال · #3 نسخة لكل منصة · #4 فحص قبل النشر · #5 بنك هاشتاجات */
(function () {
  var X = window.CPT; if (!X) return;
  var T = X.tr, ta = document.getElementById('content'); if (!ta) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function setText(v) { ta.value = v; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus(); }
  function post(data) { var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); fd.append('_csrf', X.csrf); return fetch(location.pathname + '?p=compose', { method: 'POST', body: fd }).then(function (r) { return r.json(); }); }
  function selPlats() { return Array.prototype.map.call(document.querySelectorAll('input[name="accounts[]"]:checked'), function (c) { return { id: c.value, pf: c.dataset.plat || '' }; }); }
  var bar = document.getElementById('cpt-bar');
  // كل الأدوات بتفتح في نافذة واحدة شكلها موحد
  var dlg = document.createElement('dialog'); dlg.id = 'cpt-dlg'; dlg.className = 'mk-dlg !w-[min(880px,96vw)]'; dlg.innerHTML = '<div id="cpt-pane" class="p-5"></div>'; document.body.appendChild(dlg);
  var old = document.getElementById('cpt-pane'); if (old && old !== dlg.firstChild) old.remove();
  var pane = dlg.firstChild, kind = '';
  dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
  function show(html, k) { kind = k || ''; pane.innerHTML = html; if (!dlg.open) dlg.showModal(); }
  function hide() { if (dlg.open) dlg.close(); kind = ''; }
  function head(icon, title, extra) { return '<div class="flex items-center gap-3 mb-4"><span class="w-10 h-10 rounded-2xl bg-brand-50 text-brand-700 grid place-items-center shrink-0">' + uic(icon, 'w-5 h-5') + '</span><b class="text-lg text-ink-900 flex-1">' + esc(title) + '</b>' + (extra || '') + '<button type="button" class="p-2 rounded-xl text-ink-400 hover:bg-ink-100 hover:text-ink-900" data-x="close" aria-label="✕">' + uic('x', 'w-5 h-5') + '</button></div>'; }
  // نافذة صغيرة بدل prompt
  window.cptAsk = function (o) { return new Promise(function (res) {
    var d = document.createElement('dialog'); d.className = 'mk-dlg !w-[min(460px,94vw)]';
    d.innerHTML = '<form method="dialog" class="p-5 space-y-3"><b class="block text-ink-900 text-base"></b><p class="text-xs text-ink-500 hidden"></p><input class="input !mt-0" dir="auto"><div class="flex justify-end gap-2 pt-1"><button value="" class="btn-ghost" data-c></button><button value="ok" class="btn-primary" data-ok></button></div></form>';
    d.querySelector('b').textContent = o.title; var hp = d.querySelector('p'); if (o.hint) { hp.textContent = o.hint; hp.classList.remove('hidden'); }
    var inp = d.querySelector('input'); inp.value = o.value || ''; inp.placeholder = o.placeholder || ''; if (o.ltr) inp.dir = 'ltr';
    d.querySelector('[data-c]').textContent = T.cancel || '✕'; d.querySelector('[data-ok]').textContent = o.ok || T.save;
    document.body.appendChild(d); d.showModal(); inp.focus(); inp.select();
    d.addEventListener('close', function () { var v = d.returnValue === 'ok' ? inp.value : null; d.remove(); res(v); });
  }); };

  /* ---------- #1 القوالب: تصفح، بحث، استخدام، تعديل (أي قالب)، حذف، وقالب جديد ---------- */
  var tab = '', q = '', view = 'stage', tlang = (X.uiLang === 'en' ? 'en' : 'ar');
  function indSet() { return tlang === 'en' ? (X.tplEn || X.tpl) : (X.tplAr || X.tpl); }
  function stage(k) { return (X.journey || []).filter(function (s) { return 'j:' + s.k === k; })[0]; }
  function tplList(ind) {
    if (ind === '__mine') return X.mine || [];
    if (ind && ind.indexOf('j:') === 0) { var st = stage(ind); return st ? st.items.map(function (t) { return tlang === 'en' ? { n: t.en.n, f: t.en.f, w: t.en.w, b: t.en.b, p: t.p } : { n: t.n, f: t.f, w: t.w, b: t.b, p: t.p }; }) : []; }
    return indSet()[ind] || [];
  }
  function tplPane(ind) {
    var mine = X.mine || [], J = X.journey || [], inds = Object.keys(indSet());
    if (ind) { view = ind.indexOf('j:') === 0 ? 'stage' : (ind === '__mine' ? view : 'ind'); tab = ind; }
    var keys = view === 'stage' ? J.map(function (s) { return 'j:' + s.k; }) : inds;
    if (!tab || (tab !== '__mine' && keys.indexOf(tab) < 0)) tab = keys[0];
    var tabs = keys.concat(['__mine']).map(function (k, i) { var n = tplList(k).length, on = k === tab, st = stage(k);
      var label = k === '__mine' ? T.mine : st ? (tlang === 'en' ? st.en : st.ar) : k, sub = st ? (tlang === 'en' ? st.g_en : st.g_ar) : '';
      return '<button type="button" data-ind="' + esc(k) + '" class="tp-tab' + (on ? ' on' : '') + '">' + (st ? '<i class="tp-step">' + (i + 1) + '</i>' : k === '__mine' ? uic('star', 'w-4 h-4') : '') + '<span class="tp-tl"><b>' + esc(label) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span><span class="tp-n">' + n + '</span></button>'; }).join('');
    var bar = '<div class="tp-bar"><div class="tp-seg">' + [['stage', T.byStage || 'Stage'], ['ind', T.byInd || 'Industry']].map(function (v) { return '<button type="button" data-tview="' + v[0] + '" class="' + (view === v[0] ? 'on' : '') + '">' + esc(v[1]) + '</button>'; }).join('') + '</div>' +
      '<div class="tp-seg">' + [['ar', 'عربي'], ['en', 'English']].map(function (v) { return '<button type="button" data-tlang="' + v[0] + '" class="' + (tlang === v[0] ? 'on' : '') + '">' + v[1] + '</button>'; }).join('') + '</div></div>';
    show(head('copy', T.tplT, '<button type="button" class="btn-primary !py-1.5 !text-sm" data-x="newtpl">' + uic('plus', 'w-4 h-4') + esc(T.newTpl) + '</button>') + bar +
      '<div class="grid md:grid-cols-[230px_minmax(0,1fr)] gap-4"><nav class="tp-nav' + (view === 'stage' ? ' journey' : '') + ' space-y-1 md:max-h-[60vh] overflow-y-auto">' + tabs + '</nav><div class="min-w-0">' +
      '<label class="mk-search !w-full mb-3">' + uic('search') + '<input type="search" id="cpt-q" placeholder="' + esc(T.tplSearch) + '" value="' + esc(q) + '"></label><div id="cpt-tl" class="grid sm:grid-cols-2 gap-3 max-h-[56vh] overflow-y-auto p-0.5" dir="' + (tlang === 'en' && tab !== '__mine' ? 'ltr' : 'auto') + '"></div>' +
      '<p class="text-[11px] text-ink-400 mt-3">' + esc(T.tplHint) + '</p></div></div>', 'tpl');
    drawTpl(); var qi = document.getElementById('cpt-q'); qi.addEventListener('input', function () { q = qi.value; drawTpl(); });
  }
  function hl(t) { return esc(t).replace(/\[([^\]]{1,40})\]/g, '<mark class="rounded bg-amber-100 text-amber-900 px-0.5">[$1]</mark>'); }
  function drawTpl() {
    var list = tplList(tab), v = q.trim().toLowerCase(), box = document.getElementById('cpt-tl');
    var html = list.map(function (t, i) { if (v && (t.n + ' ' + t.b + ' ' + (t.f || '') + ' ' + (t.p || '')).toLowerCase().indexOf(v) < 0) return '';
      return '<div class="group rounded-2xl border border-ink-200 bg-white p-3 flex flex-col hover:border-brand-300 hover:shadow-md transition cp-pop"><div class="flex items-center gap-2"><b class="text-sm text-ink-900 flex-1 truncate">' + esc(t.n) + '</b>' +
        '<button type="button" class="p-1.5 rounded-lg text-ink-400 hover:bg-ink-100 hover:text-brand-700" data-edit="' + i + '" title="' + esc(T.edit) + '">' + uic('pencil', 'w-4 h-4') + '</button>' +
        (tab === '__mine' ? '<button type="button" class="p-1.5 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" data-del="' + i + '" title="' + esc(T.del) + '">' + uic('trash', 'w-4 h-4') + '</button>' : '') + '</div>' +
        (t.f || t.p ? '<div class="tp-meta">' + (t.f ? '<span class="tp-f">' + uic('spark', 'w-3 h-3') + esc(t.f) + '</span>' : '') + (t.p ? '<span class="tp-p">' + esc(t.p) + '</span>' : '') + '</div>' : '') +
        '<p class="text-xs text-ink-600 whitespace-pre-line line-clamp-5 mt-1.5 flex-1" dir="auto">' + hl(t.b) + '</p>' + (t.w ? '<p class="tp-w">' + uic('bulb', 'w-3.5 h-3.5') + '<span>' + esc(t.w) + '</span></p>' : '') + '<button type="button" class="btn-primary !py-1.5 !text-xs w-full mt-3 opacity-90 group-hover:opacity-100" data-use="' + i + '">' + esc(T.use) + '</button></div>'; }).join('');
    box.innerHTML = html || '<div class="sm:col-span-2 text-center py-10 text-sm text-ink-500">' + esc(tab === '__mine' && !list.length ? T.noMine : T.nothing) + '</div>';
  }
  function tplEdit(i, from) { // from: '__mine' أو مجال (نسخة في قوالبي) أو 'new'
    var t = from === 'new' ? { n: '', b: ta.value } : tplList(from)[i];
    show(head('pencil', from === 'new' ? T.newTpl : T.editTpl) + (from !== 'new' && from !== '__mine' ? '<p class="text-xs text-brand-700 bg-brand-50 rounded-xl px-3 py-2 mb-3">' + esc(T.copyNote) + '</p>' : '') +
      '<div class="space-y-3"><input id="cpt-en" class="input !mt-0" maxlength="40" placeholder="' + esc(T.tplName) + '" value="' + esc(t.n) + '"><textarea id="cpt-eb" rows="10" class="input !mt-0 text-sm leading-7" dir="auto" maxlength="3000">' + esc(t.b) + '</textarea>' +
      '<p class="text-[11px] text-ink-400">' + esc(T.tplHint) + '</p><div class="flex justify-end gap-2"><button type="button" class="btn-ghost" data-x="back">' + esc(T.cancel) + '</button><button type="button" class="btn-primary" data-x="savetpl2" data-i="' + i + '" data-from="' + esc(from) + '">' + esc(T.save) + '</button></div></div>', 'tpl');
    document.getElementById(from === 'new' ? 'cpt-en' : 'cpt-eb').focus();
  }

  /* ---------- #5 بنك الوسوم: مجموعات تتعدل وتتحذف، وكل وسم يتضاف لوحده ---------- */
  var tagEdit = -1;
  function inPost(h) { return (' ' + ta.value + ' ').indexOf(h) > -1; }
  function chip(h) { var on = inPost(h); return '<button type="button" data-ptag="' + esc(h) + '" class="rounded-full border px-2.5 py-1 text-xs transition ' + (on ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-200 bg-white text-brand-700 hover:border-brand-400 hover:bg-brand-50') + '" dir="auto">' + esc(h) + '</button>'; }
  function tagsPane() {
    var sets = X.tags || [];
    var cards = sets.map(function (s, i) {
      if (i === tagEdit) return '<div class="rounded-2xl border-2 border-brand-400 bg-brand-50/40 p-3 space-y-2"><input id="cpt-etn" class="input !mt-0 !py-1.5 text-sm" maxlength="30" value="' + esc(s.n) + '"><textarea id="cpt-etv" rows="3" class="input !mt-0 text-sm" dir="auto">' + esc(s.t) + '</textarea><div class="flex justify-end gap-2"><button type="button" class="btn-ghost !py-1 !text-xs" data-x="tagcancel">' + esc(T.cancel) + '</button><button type="button" class="btn-primary !py-1 !text-xs" data-x="tagupd" data-i="' + i + '">' + esc(T.save) + '</button></div></div>';
      var hs = s.t.match(/#[^\s#]+/g) || [];
      return '<div class="rounded-2xl border border-ink-200 bg-white p-3 hover:border-brand-300 hover:shadow-sm transition cp-pop"><div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900 flex-1 truncate">' + esc(s.n) + '</b><span class="text-[11px] text-ink-400">' + hs.length + '</span>' +
        '<button type="button" class="p-1.5 rounded-lg text-ink-400 hover:bg-ink-100 hover:text-brand-700" data-tedit="' + i + '" title="' + esc(T.edit) + '">' + uic('pencil', 'w-4 h-4') + '</button><button type="button" class="p-1.5 rounded-lg text-ink-400 hover:bg-red-50 hover:text-red-600" data-deltags="' + i + '" title="' + esc(T.del) + '">' + uic('trash', 'w-4 h-4') + '</button></div>' +
        '<div class="flex flex-wrap gap-1.5">' + hs.map(chip).join('') + '</div><button type="button" class="btn-ghost !py-1 !text-xs w-full mt-3" data-addtags="' + i + '">' + uic('plus', 'w-3.5 h-3.5') + esc(T.addAll) + '</button></div>';
    }).join('');
    var cur = (ta.value.match(/#[^\s#]+/g) || []).join(' ');
    show(head('hash', T.tagsT) +
      ((X.ptags || []).length ? '<div class="rounded-2xl border border-lime-200 bg-lime-50/50 p-3 mb-4"><div class="text-xs font-semibold text-lime-800 mb-2">' + esc(X.ptagsT) + '</div><div class="flex flex-wrap gap-1.5">' + X.ptags.map(chip).join('') + '</div></div>' : '') +
      (sets.length ? '<div class="grid sm:grid-cols-2 gap-3 max-h-[46vh] overflow-y-auto p-0.5">' + cards + '</div>' : '<div class="text-center py-8 text-sm text-ink-500">' + esc(T.noTags) + '</div>') +
      '<div class="mt-4 rounded-2xl border border-dashed border-ink-300 p-3"><div class="text-xs font-semibold text-ink-700 mb-2">' + esc(T.newSet) + '</div><div class="flex flex-wrap gap-2"><input id="cpt-tn" class="input !py-1.5 text-sm !mt-0 w-40" maxlength="30" placeholder="' + esc(T.tagName) + '"><input id="cpt-tv" class="input !py-1.5 text-sm !mt-0 flex-1 min-w-[200px]" dir="auto" placeholder="#عروض #خصومات" value="' + esc(cur) + '"><button type="button" class="btn-primary !py-1.5 !text-sm" data-x="savetags">' + esc(T.save) + '</button></div>' +
      '<p class="text-[11px] text-ink-400 mt-2">' + esc(T.tagsHint) + '</p></div>', 'tags');
  }
  function saveTags(msg) { return post({ action: 'cpt_save', kind: 'tags', data: JSON.stringify(X.tags) }).then(function (r) { if (r.ok) { toast(msg || T.saved); tagEdit = -1; tagsPane(); } }); }
  function saveTpl(msg, ind) { return post({ action: 'cpt_save', kind: 'tpl', data: JSON.stringify(X.mine) }).then(function (r) { if (r.ok) { toast(msg || T.saved); q = ''; tplPane(ind); } }); }

  /* ---------- #3 نسخة لكل منصة (بالقواعد) ---------- */
  var LIM = { twitter: 280, x: 280, threads: 500, bluesky: 300, instagram: 2200, tiktok: 2200, linkedin: 3000, facebook: 63206, pinterest: 500, youtube: 5000, googlebusiness: 1500 };
  function adapt(txt, pf) {
    var tags = txt.match(/#[^\s#]+/g) || [], body = txt.replace(/(^|\s)#[^\s#]+/g, ' ').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').replace(/[ \t]{2,}/g, ' ').trim();
    var links = body.match(/https?:\/\/\S+/g) || [];
    var maxT = { twitter: 2, x: 2, threads: 3, bluesky: 2, linkedin: 3, facebook: 3, instagram: 10, tiktok: 5, pinterest: 5, youtube: 3, googlebusiness: 0 }[pf]; if (maxT === undefined) maxT = 5;
    var out = body;
    if (pf === 'instagram' || pf === 'tiktok') { links.forEach(function (l) { out = out.replace(l, ''); }); if (links.length) out = out.trim() + '\n\n' + T.linkBio; } // اللينكات مابتتضغطش هناك
    if (pf === 'googlebusiness') tags = [];
    var tg = tags.slice(0, maxT).join(' '), lim = LIM[pf] || 5000;
    var full = (out.trim() + (tg ? (pf === 'instagram' ? '\n.\n.\n' : '\n\n') + tg : '')).trim();
    if (Array.from(full).length > lim) {
      var keep = lim - (tg ? tg.length + 2 : 0) - (links[0] && pf !== 'instagram' && pf !== 'tiktok' ? links[0].length + 2 : 0) - 1;
      var plain = out.replace(/https?:\/\/\S+/g, '').trim(); var cut = Array.from(plain).slice(0, Math.max(40, keep)).join('');
      cut = cut.replace(/\s+\S*$/, '') + '…';
      full = cut + (links[0] && pf !== 'instagram' && pf !== 'tiktok' ? '\n' + links[0] : '') + (tg ? '\n\n' + tg : '');
    }
    return full;
  }
  function makeVersions() {
    var s = selPlats(); if (!s.length) return toast(T.pickAcc); if (!ta.value.trim()) return toast(T.empty);
    var n = 0;
    s.forEach(function (a) {
      var p = document.querySelector('.panel[data-acc="' + a.id + '"]'); if (!p) return;
      var c = p.querySelector('textarea[name$="[custom]"]'); if (!c) return;
      var v = adapt(ta.value, a.pf); if (v === ta.value.trim()) return;
      c.value = v; c.dispatchEvent(new Event('input', { bubbles: true })); p.open = true; p.classList.add('ring-2', 'ring-lime-300'); setTimeout(function () { p.classList.remove('ring-2', 'ring-lime-300'); }, 1500); n++;
    });
    toast(n ? T.verDone.replace('%s', n) : T.verSame);
  }

  /* ---------- #4 فحص قبل النشر (بيتحدّث وانت بتكتب) ---------- */
  var chk = document.getElementById('cpt-check');
  function check() {
    if (!chk) return;
    var txt = ta.value, s = selPlats(), pfs = s.map(function (a) { return a.pf; }), issues = [], ok = [];
    var len = Array.from(txt.trim()).length, tags = (txt.match(/#[^\s#]+/g) || []).length, links = txt.match(/https?:\/\/\S+/g) || [];
    var hasMedia = !!(document.querySelector('#mediaPreview *') || document.querySelector('#libMedia *'));
    if (!len && !hasMedia) { chk.innerHTML = ''; return; }
    pfs.forEach(function (pf) { var lim = LIM[pf]; if (lim && len > lim) issues.push(T.cLong.replace('%s', pf).replace('%s', len).replace('%s', lim)); });
    if ((pfs.indexOf('instagram') > -1 || pfs.indexOf('tiktok') > -1 || pfs.indexOf('pinterest') > -1) && !hasMedia) issues.push(T.cMedia);
    if (pfs.indexOf('instagram') > -1 && tags > 30) issues.push(T.cTagsIg);
    if ((pfs.indexOf('facebook') > -1 || pfs.indexOf('linkedin') > -1) && tags > 5) issues.push(T.cTags.replace('%s', tags));
    if (links.length && pfs.indexOf('instagram') > -1) issues.push(T.cIgLink);
    links.forEach(function (l) { if (!/^https?:\/\/[^\s/]+\.[^\s/]+/.test(l) || /[)\].,،]$/.test(l)) issues.push(T.cBadLink.replace('%s', l.slice(0, 40))); });
    if (/[A-Z]{12,}/.test(txt)) issues.push(T.cCaps);
    if (/(.)\1{5,}/u.test(txt)) issues.push(T.cRepeat);
    if (len > 0 && len < 15 && !hasMedia) issues.push(T.cShort);
    if (/\{[a-z_]+\}/i.test(txt)) issues.push(T.cVar);
    if (/\[[^\]]{2,30}\]/.test(txt)) issues.push(T.cFill);
    var wi = document.getElementById('whenInput'), md = document.querySelector('input.mode:checked');
    if (wi && wi.value && (!md || md.value === 'schedule') && (X.black || []).length) { var wd = new Date(wi.value), dw = wd.getDay(), hm = wi.value.slice(11, 16); if ((X.black || []).some(function (b) { return b.days.indexOf(dw) > -1 && hm >= b.from && hm < b.to; })) issues.push(T.cBlack); }
    if (!issues.length) ok.push(T.cOk);
    chk.innerHTML = issues.map(function (x) { return '<span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 px-2 py-0.5">' + uic('alert', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(x) + '</span>'; }).join('') +
      ok.map(function (x) { return '<span class="inline-flex items-center gap-1 rounded-full bg-lime-50 border border-lime-200 text-lime-800 px-2 py-0.5">✓ ' + esc(x) + '</span>'; }).join('');
  }
  var ct = null; function later() { clearTimeout(ct); ct = setTimeout(check, 250); }
  ta.addEventListener('input', later); document.addEventListener('change', function (e) { if (e.target.name === 'accounts[]' || e.target.type === 'file' || e.target.id === 'whenInput' || e.target.classList.contains('mode')) later(); }); document.addEventListener('input', function (e) { if (e.target.id === 'whenInput') later(); });
  new MutationObserver(later).observe(document.getElementById('mediaPreview') || document.body, { childList: true, subtree: true });
  check();

  function toast(m) { var o = document.querySelector('.ld-toast'); if (o) o.remove(); var t = document.createElement('div'); t.className = 'ld-toast'; t.textContent = m; document.body.appendChild(t); setTimeout(function () { t.remove(); }, 3500); }

  /* ---------- #6 منشور من لينك ---------- */
  function fromLink() {
    window.cptAsk({ title: T.linkQ, value: 'https://', ltr: true, ok: T.go }).then(function (u) { if (!u || !/^https?:\/\//i.test(u.trim())) return;
    toast(T.linking);
    post({ action: 'from_link', url: u.trim() }).then(function (r) {
      if (!r.ok) return toast(r.error);
      if (ta.value.trim() && !confirm(T.replaceQ)) return;
      setText(r.text);
      if (r.lib && window.cpAddLib) { window.cpAddLib(r.lib); toast(T.imgAdded); }
    }); });
  }
  /* ---------- #7 اكتب عن الصورة ---------- */
  function firstImage() {
    var inp = document.getElementById('mediaInput');
    if (inp && inp.files && inp.files[0] && /^image\//.test(inp.files[0].type)) return shrink(inp.files[0]);
    var im = document.querySelector('#libMedia img, #mediaPreview img'); if (im && /^https?:/.test(im.src)) return Promise.resolve(im.src);
    return Promise.resolve(null);
  }
  function shrink(file) { // بنصغّر الصورة لحد 1024 عشان الطلب يبقى خفيف
    return new Promise(function (res) { var img = new Image(), url = URL.createObjectURL(file); img.onload = function () { var k = Math.min(1, 1024 / Math.max(img.width, img.height)), c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k); c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url); res(c.toDataURL('image/jpeg', 0.85)); }; img.onerror = function () { res(null); }; img.src = url; });
  }
  function imgCaption(btn) {
    firstImage().then(function (src) {
      if (!src) return toast(T.noImg);
      btn.disabled = true; toast(T.capping);
      post({ action: 'img_caption', image: src, note: ta.value.slice(0, 300) }).then(function (r) { btn.disabled = false; if (!r.ok) return toast(r.error); if (ta.value.trim() && !confirm(T.replaceQ)) return; setText(r.text); }).catch(function () { btn.disabled = false; });
    });
  }
  /* ---------- #8 #9 هوية الصورة: لوجو + علامة مائية + إطار (في المتصفح، قبل الرفع) ---------- */
  var BR = {}; try { BR = JSON.parse(localStorage.getItem('cpt-brand') || '{}'); } catch (e) {}
  function brandPane() {
    show('<div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900">' + uic('palette', 'w-4 h-4 inline-block align-[-3px]') + '' + esc(T.brandT) + '</b><button type="button" class="link text-xs ms-auto" data-x="close">✕</button></div><p class="text-[11px] text-ink-500 mb-2">' + esc(T.brandHint) + '</p>' +
      '<div class="grid sm:grid-cols-2 gap-2 text-xs"><label class="block">' + esc(T.logo) + '<input type="file" accept="image/png,image/jpeg,image/webp" id="cpt-logo" class="input !py-1 text-xs"></label>' +
      '<label class="block">' + esc(T.pos) + '<select id="cpt-pos" class="input !py-1 text-xs">' + [['br', T.posBR], ['bl', T.posBL], ['tr', T.posTR], ['tl', T.posTL]].map(function (o) { return '<option value="' + o[0] + '"' + (BR.pos === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('') + '</select></label>' +
      '<label class="block">' + esc(T.wm) + '<input id="cpt-wm" class="input !py-1 text-xs" dir="auto" value="' + esc(BR.wm || '') + '" placeholder="@yourbrand"></label>' +
      '<label class="block">' + esc(T.size) + '<input type="range" id="cpt-size" min="8" max="30" value="' + (BR.size || 16) + '" class="w-full"></label>' +
      '<label class="flex items-center gap-2"><input type="checkbox" id="cpt-frame" class="rounded"' + (BR.frame ? ' checked' : '') + '> ' + esc(T.frame) + ' <input type="color" id="cpt-color" value="' + (BR.color || '#4f46e5') + '" class="h-7 w-10 rounded"></label></div>' +
      (BR.logo ? '<div class="mt-2 flex items-center gap-2 text-[11px] text-ink-500"><img src="' + BR.logo + '" class="h-8 rounded bg-white border border-ink-200 p-0.5"> ✓</div>' : '') +
      '<div id="cpt-prev" class="mt-2"></div><button type="button" class="btn-primary !py-1.5 !text-xs w-full mt-2" data-x="brandgo">' + esc(T.apply) + '</button>');
    ['cpt-pos', 'cpt-wm', 'cpt-size', 'cpt-frame', 'cpt-color'].forEach(function (id) { var el = document.getElementById(id); if (el) { el.addEventListener('input', function () { readBR(); saveBR(); brandPreview(); }); el.addEventListener('change', function () { readBR(); saveBR(); brandPreview(); }); } }); brandPreview();
    document.getElementById('cpt-logo').addEventListener('change', function () { var f = this.files[0]; if (!f) return; var rd = new FileReader(); rd.onload = function () { BR.logo = rd.result; saveBR(); brandPane(); }; rd.readAsDataURL(f); });
  }
  function saveBR() { try { localStorage.setItem('cpt-brand', JSON.stringify(BR)); } catch (e) {} }
  function readBR() { BR.pos = document.getElementById('cpt-pos').value; BR.wm = document.getElementById('cpt-wm').value; BR.size = +document.getElementById('cpt-size').value; BR.frame = document.getElementById('cpt-frame').checked; BR.color = document.getElementById('cpt-color').value; saveBR(); }
  function brandFile(file) {
    return new Promise(function (res) {
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var W = img.width, H = img.height, c = document.createElement('canvas'), x = c.getContext('2d'), pad = BR.frame ? Math.round(Math.min(W, H) * 0.035) : 0;
        c.width = W + pad * 2; c.height = H + pad * 2;
        if (pad) { x.fillStyle = BR.color || '#4f46e5'; x.fillRect(0, 0, c.width, c.height); }
        x.drawImage(img, pad, pad, W, H);
        var m = Math.round(Math.min(W, H) * 0.03), pos = BR.pos || 'br', sz = (BR.size || 16) / 100;
        function place(w, h) { return { x: pos[1] === 'r' ? c.width - pad - m - w : pad + m, y: pos[0] === 'b' ? c.height - pad - m - h : pad + m }; }
        function done() { c.toBlob(function (b) { URL.revokeObjectURL(url); res(new File([b], file.name.replace(/\.\w+$/, '') + '-brand.jpg', { type: 'image/jpeg' })); }, 'image/jpeg', 0.92); }
        var drawWm = function (yOff) { if (!BR.wm) return; var fs = Math.max(14, Math.round(Math.min(W, H) * 0.035)); x.font = '600 ' + fs + 'px system-ui, Tahoma, sans-serif'; var tw = x.measureText(BR.wm).width; var p0 = place(tw, fs); x.fillStyle = 'rgba(0,0,0,.35)'; x.fillText(BR.wm, p0.x + 1, p0.y + fs - yOff + 1); x.fillStyle = 'rgba(255,255,255,.92)'; x.fillText(BR.wm, p0.x, p0.y + fs - yOff); };
        if (BR.logo) { var lg = new Image(); lg.onload = function () { var lw = Math.round(Math.min(W, H) * sz * 1.6), lh = Math.round(lw * lg.height / lg.width), p0 = place(lw, lh); x.globalAlpha = 0.95; x.drawImage(lg, p0.x, p0.y, lw, lh); x.globalAlpha = 1; drawWm(pos[0] === 'b' ? lh + m : -(lh + m)); done(); }; lg.onerror = function () { drawWm(0); done(); }; lg.src = BR.logo; }
        else { drawWm(0); done(); }
      };
      img.onerror = function () { res(file); }; img.src = url;
    });
  }
  // كل الصور المختارة: المرفوعة + من مكتبة الوسائط + المتولدة بالذكاء الاصطناعي
  function brandSources() {
    var out = [], inp = document.getElementById('mediaInput');
    [].slice.call((inp && inp.files) || []).forEach(function (f, i) { if (/^image\/(png|jpe?g|webp)$/.test(f.type) && !/-brand\./.test(f.name)) out.push({ kind: 'file', i: i, file: f }); });
    document.querySelectorAll('#libMedia > div').forEach(function (w) { var im = w.querySelector('img'); if (im) out.push({ kind: 'lib', el: w, src: im.src, name: (w.title || 'library') + '.jpg' }); });
    document.querySelectorAll('.hf-img[data-on="1"]').forEach(function (bt) { var im = bt.querySelector('img'); if (im) out.push({ kind: 'ai', el: bt, src: im.src, name: (bt.dataset.name || 'ai').replace(/\.\w+$/, '') + '.jpg' }); });
    return out;
  }
  function toFile(s) { if (s.file) return Promise.resolve(s.file); return fetch(s.src, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw 0; return r.blob(); }).then(function (b) { return new File([b], s.name, { type: b.type || 'image/jpeg' }); }); }
  var pvT = null;
  function brandPreview() { readBR(); clearTimeout(pvT); pvT = setTimeout(function () { var s = brandSources()[0], pr = document.getElementById('cpt-prev'); if (!pr) return;
    if (!s) { pr.innerHTML = '<p class="text-xs text-ink-400 text-center py-6 rounded-xl bg-ink-50">' + esc(T.noImg) + '</p>'; return; }
    toFile(s).then(brandFile).then(function (f) { pr.innerHTML = '<img src="' + URL.createObjectURL(f) + '" class="w-full max-h-72 object-contain rounded-xl border border-ink-200 bg-[repeating-conic-gradient(rgb(var(--ink-100))_0_25%,rgb(var(--ink-50))_0_50%)] bg-[length:16px_16px]">'; }).catch(function () {}); }, 200); }
  function brandApply() {
    readBR(); var src = brandSources(); if (!src.length) return toast(T.noImg);
    var inp = document.getElementById('mediaInput'), keep = [].slice.call(inp.files || []).filter(function (f, i) { return !src.some(function (s) { return s.kind === 'file' && s.i === i; }); });
    Promise.all(src.map(function (s) { return toFile(s).then(brandFile).then(function (f) { return { s: s, f: f }; }).catch(function () { return null; }); })).then(function (res) {
      res = res.filter(Boolean); var dt = new DataTransfer(); keep.concat(res.map(function (r) { return r.f; })).forEach(function (f) { dt.items.add(f); });
      res.forEach(function (r) { if (r.s.kind === 'lib') r.s.el.remove(); if (r.s.kind === 'ai') r.s.el.click(); }); // اتحولت لملفات مرفوعة عليها اللوجو
      inp.files = dt.files; inp.dispatchEvent(new Event('change', { bubbles: true })); toast(T.applied.replace('%s', res.length)); hide();
    });
  }

  bar.addEventListener('click', function (e) {
    var b = e.target.closest('[data-tool]'); if (!b) return;
    var k = b.dataset.tool;
    if (k === 'link') return fromLink();
    if (k === 'imgcap') return imgCaption(b);
    if (k === 'brand') return brandPane();
    if (k === 'tpl') return tplPane();
    if (k === 'tags') { tagEdit = -1; return tagsPane(); }
    if (k === 'ver') return makeVersions();
    if (k === 'coupon') {
      return window.cptAsk({ title: b.dataset.q, value: '', ok: T.go }).then(function (code) { if (code === null) return;
      post({ action: 'coupon_new', code: code, label: ta.value.slice(0, 150) }).then(function (j) {
        if (!j.ok) return alert(j.error || '');
        ta.value = ta.value.replace(/\s*$/, '') + '\n\n' + b.dataset.line.replace('%s', j.code); ta.dispatchEvent(new Event('input', { bubbles: true })); toast(T.saved);
      }); });
    }
    if (k === 'ab') { var abx = document.getElementById('cpt-ab'); abx.classList.toggle('hidden'); b.classList.toggle('on', !abx.classList.contains('hidden')); if (!abx.classList.contains('hidden')) { var at = abx.querySelector('textarea'); if (!at.value) at.value = ta.value; at.focus(); } return; }
  });
  pane.addEventListener('click', function (e) {
    var x = e.target.closest('[data-x],[data-ind],[data-tview],[data-tlang],[data-use],[data-del],[data-edit],[data-addtags],[data-deltags],[data-tedit],[data-ptag]'); if (!x) return;
    if (x.dataset.x === 'back') return tplPane();
    if (x.dataset.x === 'newtpl') return tplEdit(-1, 'new');
    if (x.dataset.edit !== undefined) return tplEdit(+x.dataset.edit, tab);
    if (x.dataset.x === 'savetpl2') {
      var en = document.getElementById('cpt-en').value.trim(), eb = document.getElementById('cpt-eb').value.trim(); if (!en || !eb) return toast(T.tplNeed);
      var it = { n: en.slice(0, 40), b: eb.slice(0, 3000) }; X.mine = X.mine || [];
      if (x.dataset.from === '__mine') X.mine[+x.dataset.i] = it; else X.mine = X.mine.concat([it]).slice(-30);
      return saveTpl(T.saved, '__mine');
    }
    if (x.dataset.tedit !== undefined) { tagEdit = +x.dataset.tedit; tagsPane(); var f = document.getElementById('cpt-etv'); if (f) f.focus(); return; }
    if (x.dataset.x === 'tagcancel') { tagEdit = -1; return tagsPane(); }
    if (x.dataset.x === 'tagupd') { var nn = document.getElementById('cpt-etn').value.trim(), tt = (document.getElementById('cpt-etv').value.match(/#[^\s#]+/g) || []).join(' '); if (!nn || !tt) return toast(T.tagNeed); X.tags[+x.dataset.i] = { n: nn.slice(0, 30), t: tt.slice(0, 600) }; return saveTags(); }
    if (x.dataset.x === 'close') return hide();
    if (x.dataset.x === 'brandgo') return brandApply();
    if (x.dataset.ind) { q = ''; return tplPane(x.dataset.ind); }
    if (x.dataset.tview) { view = x.dataset.tview; tab = ''; q = ''; return tplPane(); }
    if (x.dataset.tlang) { tlang = x.dataset.tlang; if (view === 'ind') tab = ''; return tplPane(tab || undefined); }
    if (x.dataset.use !== undefined) {
      var t = tplList(tab)[+x.dataset.use];
      if (ta.value.trim() && !confirm(T.replaceQ)) return;
      setText(t.b); hide();
      var m = /\[[^\]]+\]/.exec(ta.value); if (m) { ta.setSelectionRange(m.index, m.index + m[0].length); } // أول خانة تتملي بتبقى متعلّمة
      return;
    }
    if (x.dataset.del !== undefined) { if (!confirm(T.delQ)) return; X.mine.splice(+x.dataset.del, 1); return saveTpl(T.deleted, '__mine'); }
    if (x.dataset.x === 'savetpl') {
      if (!ta.value.trim()) return toast(T.empty); var n = prompt(T.tplName); if (!n) return;
      X.mine = (X.mine || []).concat([{ n: n.slice(0, 40), b: ta.value.slice(0, 3000) }]).slice(-30);
      post({ action: 'cpt_save', kind: 'tpl', data: JSON.stringify(X.mine) }).then(function (r) { if (r.ok) { toast(T.saved); tplPane(); } }); return;
    }
    if (x.dataset.addtags !== undefined) { var s = X.tags[+x.dataset.addtags], add = (s.t.match(/#[^\s#]+/g) || []).filter(function (h) { return !inPost(h); }).join(' '); if (add) { var v = ta.value.replace(/\s+$/, ''); ta.value = (v ? v + '\n\n' : '') + add; ta.dispatchEvent(new Event('input', { bubbles: true })); } toast(T.added); return tagsPane(); }
    if (x.dataset.ptag !== undefined) { var h = x.dataset.ptag;
      if (inPost(h)) ta.value = ta.value.replace(new RegExp('\\s?' + h.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?=\\s|$)', 'g'), '').replace(/\s+$/, '');
      else ta.value = ta.value.replace(/\s*$/, '') + (ta.value.trim() ? ' ' : '') + h;
      ta.dispatchEvent(new Event('input', { bubbles: true })); return tagsPane(); }
    if (x.dataset.deltags !== undefined) { if (!confirm(T.delQ)) return; X.tags.splice(+x.dataset.deltags, 1); return saveTags(T.deleted); }
    if (x.dataset.x === 'savetags') {
      var nm = document.getElementById('cpt-tn').value.trim(), tv = (document.getElementById('cpt-tv').value.match(/#[^\s#]+/g) || []).join(' ');
      if (!nm || !tv) return toast(T.tagNeed);
      X.tags = (X.tags || []).concat([{ n: nm.slice(0, 30), t: tv.slice(0, 600) }]).slice(-30);
      saveTags();
    }
  });
})();
