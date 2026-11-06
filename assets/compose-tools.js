/* أدوات كتابة المنشور (من غير AI): #1 قوالب حسب المجال · #3 نسخة لكل منصة · #4 فحص قبل النشر · #5 بنك هاشتاجات */
(function () {
  var X = window.CPT; if (!X) return;
  var T = X.tr, ta = document.getElementById('content'); if (!ta) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function setText(v) { ta.value = v; ta.dispatchEvent(new Event('input', { bubbles: true })); ta.focus(); }
  function post(data) { var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k]); }); fd.append('_csrf', X.csrf); return fetch(location.pathname + '?p=compose', { method: 'POST', body: fd }).then(function (r) { return r.json(); }); }
  function selPlats() { return Array.prototype.map.call(document.querySelectorAll('input[name="accounts[]"]:checked'), function (c) { return { id: c.value, pf: c.dataset.plat || '' }; }); }
  var bar = document.getElementById('cpt-bar'), pane = document.getElementById('cpt-pane');
  function show(html) { pane.innerHTML = html; pane.classList.remove('hidden'); }
  function hide() { pane.classList.add('hidden'); pane.innerHTML = ''; }

  /* ---------- #1 القوالب ---------- */
  function tplPane() {
    var inds = Object.keys(X.tpl), mine = X.mine || [];
    show('<div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900">📋 ' + esc(T.tplT) + '</b><button type="button" class="link text-xs ms-auto" data-x="close">✕</button></div>' +
      '<div class="flex flex-wrap gap-1.5 mb-2">' + (mine.length ? '<button type="button" class="chip text-xs on" data-ind="__mine">⭐ ' + esc(T.mine) + '</button>' : '') + inds.map(function (k, i) { return '<button type="button" class="chip text-xs' + (!mine.length && !i ? ' on' : '') + '" data-ind="' + esc(k) + '">' + esc(k) + '</button>'; }).join('') + '</div>' +
      '<div id="cpt-tl" class="grid sm:grid-cols-2 gap-2"></div>' +
      '<div class="flex items-center gap-2 mt-2 pt-2 border-t border-ink-100"><button type="button" class="link text-xs" data-x="savetpl">＋ ' + esc(T.saveTpl) + '</button><span class="text-[11px] text-ink-400">' + esc(T.tplHint) + '</span></div>');
    drawTpl(mine.length ? '__mine' : inds[0]);
  }
  function drawTpl(ind) {
    pane.querySelectorAll('[data-ind]').forEach(function (b) { b.classList.toggle('on', b.dataset.ind === ind); });
    var list = ind === '__mine' ? (X.mine || []) : X.tpl[ind];
    document.getElementById('cpt-tl').innerHTML = list.map(function (t, i) {
      return '<div class="rounded-xl border border-ink-200 bg-white p-2.5 hover:border-brand-300 transition"><div class="flex items-center gap-2"><b class="text-xs text-ink-900">' + esc(t.n) + '</b>' +
        (ind === '__mine' ? '<button type="button" class="ms-auto text-ink-400 hover:text-red-600 text-xs" data-del="' + i + '">✕</button>' : '') + '</div>' +
        '<p class="text-[11px] text-ink-600 whitespace-pre-line line-clamp-4 mt-1" dir="auto">' + esc(t.b) + '</p><button type="button" class="btn-ghost !py-1 !text-xs w-full mt-2" data-use="' + i + '" data-from="' + esc(ind) + '">' + esc(T.use) + '</button></div>';
    }).join('');
  }

  /* ---------- #5 بنك الهاشتاجات ---------- */
  function tagsPane() {
    var sets = X.tags || [];
    show('<div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900"># ' + esc(T.tagsT) + '</b><button type="button" class="link text-xs ms-auto" data-x="close">✕</button></div>' +
      (sets.length ? '<div class="space-y-1.5">' + sets.map(function (s, i) { return '<div class="flex items-center gap-2 rounded-lg border border-ink-200 px-2 py-1.5"><b class="text-xs text-ink-800 shrink-0">' + esc(s.n) + '</b><span class="text-[11px] text-brand-700 truncate flex-1" dir="auto">' + esc(s.t) + '</span><button type="button" class="chip text-xs" data-addtags="' + i + '">＋</button><button type="button" class="text-ink-400 hover:text-red-600 text-xs" data-deltags="' + i + '">✕</button></div>'; }).join('') + '</div>'
        : '<p class="text-xs text-ink-500">' + esc(T.noTags) + '</p>') +
      '<div class="flex gap-1.5 mt-2 pt-2 border-t border-ink-100"><input id="cpt-tn" class="input !py-1 text-xs !mt-0 w-28" placeholder="' + esc(T.tagName) + '"><input id="cpt-tv" class="input !py-1 text-xs !mt-0 flex-1" dir="auto" placeholder="#عروض #مصر"><button type="button" class="btn-primary !py-1 !text-xs" data-x="savetags">' + esc(T.save) + '</button></div>' +
      '<p class="text-[11px] text-ink-400 mt-1">' + esc(T.tagsHint) + '</p>');
    var cur = (ta.value.match(/#[^\s#]+/g) || []).join(' '); if (cur) document.getElementById('cpt-tv').value = cur;
  }

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
    chk.innerHTML = issues.map(function (x) { return '<span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 px-2 py-0.5">⚠ ' + esc(x) + '</span>'; }).join('') +
      ok.map(function (x) { return '<span class="inline-flex items-center gap-1 rounded-full bg-lime-50 border border-lime-200 text-lime-800 px-2 py-0.5">✓ ' + esc(x) + '</span>'; }).join('');
  }
  var ct = null; function later() { clearTimeout(ct); ct = setTimeout(check, 250); }
  ta.addEventListener('input', later); document.addEventListener('change', function (e) { if (e.target.name === 'accounts[]' || e.target.type === 'file' || e.target.id === 'whenInput' || e.target.classList.contains('mode')) later(); }); document.addEventListener('input', function (e) { if (e.target.id === 'whenInput') later(); });
  new MutationObserver(later).observe(document.getElementById('mediaPreview') || document.body, { childList: true, subtree: true });
  check();

  function toast(m) { var o = document.querySelector('.ld-toast'); if (o) o.remove(); var t = document.createElement('div'); t.className = 'ld-toast'; t.textContent = m; document.body.appendChild(t); setTimeout(function () { t.remove(); }, 3500); }

  /* ---------- #6 منشور من لينك ---------- */
  function fromLink() {
    var u = prompt(T.linkQ, 'https://'); if (!u || !/^https?:\/\//i.test(u.trim())) return;
    toast(T.linking);
    post({ action: 'from_link', url: u.trim() }).then(function (r) {
      if (!r.ok) return toast(r.error);
      if (ta.value.trim() && !confirm(T.replaceQ)) return;
      setText(r.text);
      if (r.lib && window.cpAddLib) { window.cpAddLib(r.lib); toast(T.imgAdded); }
    });
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
    show('<div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900">🎨 ' + esc(T.brandT) + '</b><button type="button" class="link text-xs ms-auto" data-x="close">✕</button></div><p class="text-[11px] text-ink-500 mb-2">' + esc(T.brandHint) + '</p>' +
      '<div class="grid sm:grid-cols-2 gap-2 text-xs"><label class="block">' + esc(T.logo) + '<input type="file" accept="image/png,image/jpeg,image/webp" id="cpt-logo" class="input !py-1 text-xs"></label>' +
      '<label class="block">' + esc(T.pos) + '<select id="cpt-pos" class="input !py-1 text-xs">' + [['br', T.posBR], ['bl', T.posBL], ['tr', T.posTR], ['tl', T.posTL]].map(function (o) { return '<option value="' + o[0] + '"' + (BR.pos === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('') + '</select></label>' +
      '<label class="block">' + esc(T.wm) + '<input id="cpt-wm" class="input !py-1 text-xs" dir="auto" value="' + esc(BR.wm || '') + '" placeholder="@yourbrand"></label>' +
      '<label class="block">' + esc(T.size) + '<input type="range" id="cpt-size" min="8" max="30" value="' + (BR.size || 16) + '" class="w-full"></label>' +
      '<label class="flex items-center gap-2"><input type="checkbox" id="cpt-frame" class="rounded"' + (BR.frame ? ' checked' : '') + '> ' + esc(T.frame) + ' <input type="color" id="cpt-color" value="' + (BR.color || '#4f46e5') + '" class="h-7 w-10 rounded"></label></div>' +
      (BR.logo ? '<div class="mt-2 flex items-center gap-2 text-[11px] text-ink-500"><img src="' + BR.logo + '" class="h-8 rounded bg-white border border-ink-200 p-0.5"> ✓</div>' : '') +
      '<div id="cpt-prev" class="mt-2"></div><button type="button" class="btn-primary !py-1.5 !text-xs w-full mt-2" data-x="brandgo">' + esc(T.apply) + '</button>');
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
  function brandApply() {
    readBR();
    var inp = document.getElementById('mediaInput'); if (!inp || !inp.files || !inp.files.length) return toast(T.noImg);
    var fs = Array.prototype.slice.call(inp.files);
    Promise.all(fs.map(function (f) { return /^image\/(png|jpe?g|webp)$/.test(f.type) && !/-brand\./.test(f.name) ? brandFile(f) : Promise.resolve(f); })).then(function (out) {
      var dt = new DataTransfer(); out.forEach(function (f) { dt.items.add(f); }); inp.files = dt.files; inp.dispatchEvent(new Event('change', { bubbles: true }));
      var n = out.filter(function (f) { return /-brand\./.test(f.name); }).length; toast(T.applied.replace('%s', n));
      var pr = document.getElementById('cpt-prev'); if (pr && out[0]) pr.innerHTML = '<img src="' + URL.createObjectURL(out[0]) + '" class="max-h-40 rounded-lg border border-ink-200">';
    });
  }

  bar.addEventListener('click', function (e) {
    var b = e.target.closest('[data-tool]'); if (!b) return;
    var k = b.dataset.tool;
    if (k === 'link') return fromLink();
    if (k === 'imgcap') return imgCaption(b);
    if (k === 'brand') return pane.classList.contains('hidden') || !pane.querySelector('#cpt-logo') ? brandPane() : hide();
    if (k === 'tpl') return pane.classList.contains('hidden') || !pane.querySelector('#cpt-tl') ? tplPane() : hide();
    if (k === 'tags') return pane.classList.contains('hidden') || !pane.querySelector('#cpt-tv') ? tagsPane() : hide();
    if (k === 'ver') return makeVersions();
    if (k === 'coupon') {
      var code = prompt(b.dataset.q, ''); if (code === null) return;
      post({ action: 'coupon_new', code: code, label: ta.value.slice(0, 150) }).then(function (j) {
        if (!j.ok) return alert(j.error || '');
        ta.value = ta.value.replace(/\s*$/, '') + '\n\n' + b.dataset.line.replace('%s', j.code); ta.dispatchEvent(new Event('input', { bubbles: true }));
      });
      return;
    }
    if (k === 'ab') { var abx = document.getElementById('cpt-ab'); abx.classList.toggle('hidden'); b.classList.toggle('on', !abx.classList.contains('hidden')); if (!abx.classList.contains('hidden')) { var at = abx.querySelector('textarea'); if (!at.value) at.value = ta.value; at.focus(); } return; }
  });
  pane.addEventListener('click', function (e) {
    var x = e.target.closest('[data-x],[data-ind],[data-use],[data-del],[data-addtags],[data-deltags]'); if (!x) return;
    if (x.dataset.x === 'close') return hide();
    if (x.dataset.x === 'brandgo') return brandApply();
    if (x.dataset.ind) return drawTpl(x.dataset.ind);
    if (x.dataset.use !== undefined) {
      var t = (x.dataset.from === '__mine' ? X.mine : X.tpl[x.dataset.from])[+x.dataset.use];
      if (ta.value.trim() && !confirm(T.replaceQ)) return;
      setText(t.b); hide();
      var m = /\[[^\]]+\]/.exec(ta.value); if (m) { ta.setSelectionRange(m.index, m.index + m[0].length); } // أول خانة تتملي بتبقى متعلّمة
      return;
    }
    if (x.dataset.del !== undefined) { X.mine.splice(+x.dataset.del, 1); post({ action: 'cpt_save', kind: 'tpl', data: JSON.stringify(X.mine) }); return drawTpl(X.mine.length ? '__mine' : Object.keys(X.tpl)[0]); }
    if (x.dataset.x === 'savetpl') {
      if (!ta.value.trim()) return toast(T.empty); var n = prompt(T.tplName); if (!n) return;
      X.mine = (X.mine || []).concat([{ n: n.slice(0, 40), b: ta.value.slice(0, 3000) }]).slice(-30);
      post({ action: 'cpt_save', kind: 'tpl', data: JSON.stringify(X.mine) }).then(function (r) { if (r.ok) { toast(T.saved); tplPane(); } }); return;
    }
    if (x.dataset.addtags !== undefined) { var s = X.tags[+x.dataset.addtags]; var v = ta.value.replace(/\s+$/, ''); setText((v ? v + '\n\n' : '') + s.t); return; }
    if (x.dataset.deltags !== undefined) { X.tags.splice(+x.dataset.deltags, 1); post({ action: 'cpt_save', kind: 'tags', data: JSON.stringify(X.tags) }); return tagsPane(); }
    if (x.dataset.x === 'savetags') {
      var nm = document.getElementById('cpt-tn').value.trim(), tv = (document.getElementById('cpt-tv').value.match(/#[^\s#]+/g) || []).join(' ');
      if (!nm || !tv) return toast(T.tagNeed);
      X.tags = (X.tags || []).concat([{ n: nm.slice(0, 30), t: tv.slice(0, 600) }]).slice(-30);
      post({ action: 'cpt_save', kind: 'tags', data: JSON.stringify(X.tags) }).then(function (r) { if (r.ok) { toast(T.saved); tagsPane(); } });
    }
  });
})();
