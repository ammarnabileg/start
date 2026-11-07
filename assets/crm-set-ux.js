/* إعدادات العملاء تفاعلية: المراحل (شريط المراحل حي + ألوان بالضغط)، الـ Pipelines كروت، الحقول (نوع بالأيقونات + اختيارات كشرائح + معاينة)،
   التاجات (جملة "لو … فيها … ← تاج" + تجربة حية)، قالب الإيميل (معاينة بتتغير وانت بتكتب). الفورمات نفسها والأسماء زي ما هي فالحفظ مايتغيرش. */
(function () {
  var C = window.CSET; if (!C) return; var T = C.T;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return [].slice.call((r || document).querySelectorAll(s)); }
  function form(a) { var i = $('input[name=action][value="' + a + '"]'); return i ? i.form : null; }
  var RO = !C.mgr;
  // الحفظ بيعمل reload كامل عشان الشكل التفاعلي يرجع بعد الحفظ
  $$('form[method=post]').forEach(function (f) { f.setAttribute('data-no-ajax', ''); });

  /* زرار حذف بدل الـ checkbox */
  function trash(cb) { var row = cb.closest('div'), lb = cb.closest('label'); if (!lb) return; lb.className = 'cs-del'; lb.title = T.del; lb.lastChild.nodeType === 3 && (lb.lastChild.textContent = '');
    lb.insertAdjacentHTML('beforeend', ic('trash'));
    cb.classList.add('sr-only'); cb.addEventListener('change', function () { row.classList.toggle('cs-gone', cb.checked); }); }

  /* ---------- المراحل ---------- */
  var fs = form('stages');
  if (fs) { fs.classList.add('cs-st');
    var strip = document.createElement('div'); strip.className = 'cs-strip'; fs.before(strip);
    var rows = $$('div.flex', fs).filter(function (r) { return r.querySelector('[name^="label["]'); });
    function sw(sel, onPick) { sel.classList.add('hidden'); var box = document.createElement('div'); box.className = 'cs-sw';
      box.innerHTML = [].map.call(sel.options, function (o) { return '<button type="button" data-c="' + o.value + '" class="' + esc(C.pal[o.value]) + (o.selected ? ' on' : '') + '" title="' + esc(o.value) + '"' + (RO ? ' disabled' : '') + '></button>'; }).join('');
      box.addEventListener('click', function (e) { var b = e.target.closest('[data-c]'); if (!b) return; sel.value = b.dataset.c; $$('[data-c]', box).forEach(function (x) { x.classList.toggle('on', x === b); }); onPick && onPick(b.dataset.c); });
      sel.after(box); return box; }
    function paint() { strip.innerHTML = rows.filter(function (r) { return !r.classList.contains('cs-gone'); }).map(function (r) { var p = r.querySelector('span.rounded-full'), n = r.querySelector('.cs-cnt');
      return '<span class="' + esc(p.className.replace(/w-24|shrink-0|text-center/g, '')) + '">' + esc(p.textContent) + (n ? ' <b>' + esc(n.textContent) + '</b>' : '') + '</span>'; }).join('<i>' + ic('chevron-left', 'w-3 h-3') + '</i>');
      var nl = fs.new_label; if (nl && nl.value.trim()) strip.insertAdjacentHTML('beforeend', '<i>' + ic('chevron-left', 'w-3 h-3') + '</i><span class="' + esc(C.pal[fs.new_color.value]) + ' cs-new">' + esc(nl.value) + '</span>'); }
    rows.forEach(function (r) { var p = r.querySelector('span.rounded-full'), inp = r.querySelector('[name^="label["]'), sel = r.querySelector('select');
      p.classList.add('hidden'); r.classList.add('cs-row'); r.insertAdjacentHTML('afterbegin', '<span class="cs-grip">' + ic('grid', 'w-3.5 h-3.5') + '</span>');
      sw(sel, function (c) { p.className = 'rounded-full hidden ' + C.pal[c]; paint(); });
      inp.addEventListener('input', function () { p.textContent = inp.value || '…'; paint(); });
      var n = r.querySelector('.w-10'); if (n) { n.className = 'cs-cnt'; n.title = T.leads; }
      var d = r.querySelector('input[name^="del["]'); if (d) { trash(d); d.addEventListener('change', paint); } });
    if (fs.new_label) { sw(fs.new_color, paint); fs.new_label.addEventListener('input', paint); fs.new_label.closest('div').classList.add('cs-add'); }
    paint(); }

  /* ---------- الـ Pipelines ---------- */
  var fp = form('pipes');
  if (fp) { fp.classList.add('cs-pp'); $$('[name^="pl["]', fp).forEach(function (i) { var r = i.closest('div'); r.classList.add('cs-pipe'); r.insertAdjacentHTML('afterbegin', '<span class="cs-pi">' + ic('route') + '</span>');
      var d = r.querySelector('[name^="pdel["]'); if (d) trash(d); else r.insertAdjacentHTML('beforeend', '<em class="cs-main">' + esc(T.main) + '</em>'); });
    if (fp.new_pipe) fp.new_pipe.classList.add('cs-addin'); }

  /* ---------- الحقول المخصصة ---------- */
  var ff = form('fields');
  if (ff) { var TY = { text: ['type', T.text, T.textEx], number: ['hash', T.num, '12000'], select: ['list', T.sel, ''] };
    function chipsFor(inp) { inp.classList.add('hidden'); var box = document.createElement('div'); box.className = 'hx-chips cs-chips';
      function vals() { return inp.value.split(/[,،]/).map(function (x) { return x.trim(); }).filter(Boolean); }
      function draw() { box.innerHTML = vals().map(function (v) { return '<span class="hx-chip">' + esc(v) + (RO ? '' : '<button type="button" data-rm="' + esc(v) + '">×</button>') + '</span>'; }).join('') + (RO ? '' : '<input placeholder="' + esc(T.optAdd) + '">'); }
      box.addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { inp.value = vals().filter(function (v) { return v !== b.dataset.rm; }).join('، '); draw(); inp.dispatchEvent(new Event('input', { bubbles: true })); } else { var i = box.querySelector('input'); i && i.focus(); } });
      box.addEventListener('keydown', function (e) { if (e.target.tagName !== 'INPUT' || (e.key !== 'Enter' && e.key !== ',' && e.key !== '،')) return; e.preventDefault(); var v = e.target.value.trim(); if (!v) return;
        inp.value = vals().concat([v]).join('، '); draw(); box.querySelector('input').focus(); inp.dispatchEvent(new Event('input', { bubbles: true })); });
      inp.after(box); draw(); }
    $$('[name^="fopts["]', ff).forEach(chipsFor);
    $$('[name^="flabel["]', ff).forEach(function (i) { var r = i.closest('div'); r.classList.add('cs-row'); var tg = r.querySelector('span.rounded'); if (tg) { var k = Object.keys(TY).filter(function (k) { return TY[k][1] === tg.textContent; })[0]; tg.className = 'cs-type'; if (k) tg.innerHTML = ic(TY[k][0], 'w-3.5 h-3.5') + ' ' + esc(tg.textContent); }
      var d = r.querySelector('[name^="fdel["]'); if (d) trash(d); });
    var sel = ff.new_type; if (sel) { sel.classList.add('hidden'); var seg = document.createElement('div'); seg.className = 'cs-seg';
      seg.innerHTML = Object.keys(TY).map(function (k) { return '<button type="button" data-ty="' + k + '"' + (sel.value === k ? ' class="on"' : '') + (RO ? ' disabled' : '') + '>' + ic(TY[k][0], 'w-3.5 h-3.5') + ' ' + esc(TY[k][1]) + '</button>'; }).join(''); sel.after(seg);
      var no = ff.new_opts; chipsFor(no); var nbox = no.nextElementSibling; nbox.classList.add('w-full');
      var pv = document.createElement('div'); pv.className = 'cs-fpv'; ff.new_label.closest('div').after(pv);
      function prev() { var k = sel.value, l = ff.new_label.value.trim(); nbox.style.display = k === 'select' ? '' : 'none'; pv.style.display = l ? '' : 'none';
        var ops = no.value.split(/[,،]/).map(function (x) { return x.trim(); }).filter(Boolean);
        pv.innerHTML = '<small>' + esc(T.fPrev) + '</small><label><span>' + esc(l) + '</span>' + (k === 'select' ? '<select class="input !mt-0 !py-1 text-sm">' + (ops.length ? ops : ['—']).map(function (o) { return '<option>' + esc(o) + '</option>'; }).join('') + '</select>' : '<input class="input !mt-0 !py-1 text-sm" placeholder="' + esc(TY[k][2]) + '"' + (k === 'number' ? ' type="number"' : '') + '>') + '</label>'; }
      seg.addEventListener('click', function (e) { var b = e.target.closest('[data-ty]'); if (!b) return; sel.value = b.dataset.ty; $$('[data-ty]', seg).forEach(function (x) { x.classList.toggle('on', x === b); }); prev(); });
      ff.addEventListener('input', prev); prev(); } }

  /* ---------- التاجات الأوتوماتيك ---------- */
  var ft = form('tagrules');
  if (ft) { var tr = $$('select[name="field[]"]', ft).map(function (s) { return s.closest('div'); }), first = tr[0];
    function deco(r) { if (r.dataset.cs) return; r.dataset.cs = 1; r.className = 'cs-rule'; var s = r.querySelector('select'), h = r.querySelector('[name="has[]"]'), t = r.querySelector('[name="tag[]"]');
      r.insertAdjacentHTML('afterbegin', '<b>' + esc(T.if) + '</b>'); s.after(Object.assign(document.createElement('b'), { textContent: T.has })); h.after(Object.assign(document.createElement('b'), { innerHTML: '← ' + esc(T.tag) }));
      t.classList.add('cs-tagin'); if (!RO) r.insertAdjacentHTML('beforeend', '<button type="button" class="cs-x" data-xr title="' + esc(T.del) + '">' + ic('x') + '</button>'); }
    tr.forEach(deco);
    // نسيب صف فاضي واحد بس
    var blanks = tr.filter(function (r) { return !r.querySelector('[name="has[]"]').value; }); blanks.slice(1).forEach(function (r) { r.remove(); });
    var last = ft.querySelector('.cs-rule:last-of-type');
    if (!RO) { var add = document.createElement('button'); add.type = 'button'; add.className = 'cs-addrule'; add.innerHTML = '+ ' + esc(T.addRule);
      var lastRule = $$('.cs-rule', ft).pop(); lastRule.after(add);
      add.addEventListener('click', function () { var c = first.cloneNode(true); delete c.dataset.cs; $$('input', c).forEach(function (i) { i.value = ''; }); c.querySelector('select').value = 'any'; add.before(c); c.querySelector('[name="has[]"]').focus(); test(); }); }
    ft.addEventListener('click', function (e) { var x = e.target.closest('[data-xr]'); if (!x) return; var r = x.closest('.cs-rule'); if ($$('.cs-rule', ft).length > 1) r.remove(); else $$('input', r).forEach(function (i) { i.value = ''; }); test(); });
    var box = document.createElement('div'); box.className = 'cs-test';
    box.innerHTML = '<b>' + ic('zap') + ' ' + esc(T.tryT) + '</b><div class="grid sm:grid-cols-2 gap-2"><input class="input !mt-0 !py-1 text-sm" data-tf="company" placeholder="' + esc(T.tryCo) + '" dir="auto"><input class="input !mt-0 !py-1 text-sm" data-tf="phone" placeholder="' + esc(T.tryPh) + '" dir="ltr"></div><div class="cs-out"></div>';
    ft.after(box);
    function test() { var co = box.querySelector('[data-tf=company]').value.toLowerCase(), ph = box.querySelector('[data-tf=phone]').value.replace(/\D/g, ''), hits = [];
      $$('.cs-rule', ft).forEach(function (r) { var f = r.querySelector('select').value, h = r.querySelector('[name="has[]"]').value.trim().toLowerCase(), t = r.querySelector('[name="tag[]"]').value.trim(), ok = false;
        if (h && t) { if (f === 'phone') ok = ph && ph.indexOf(h.replace(/\D/g, '')) === 0; else if (f === 'company' || f === 'any' || f === 'name' || f === 'title') ok = co.indexOf(h) >= 0; }
        r.classList.toggle('cs-hit', ok); if (ok && hits.indexOf(t) < 0) hits.push(t); });
      var o = box.querySelector('.cs-out'); o.innerHTML = (co || ph) ? (hits.length ? hits.map(function (t) { return '<span class="cs-tag">#' + esc(t) + '</span>'; }).join('') : '<small>' + esc(T.noTag) + '</small>') : ''; }
    box.addEventListener('input', test); ft.addEventListener('input', test);
    var ap = ft.querySelector('[name=apply]'); if (ap) ap.closest('label').classList.add('cs-apply'); }

  /* ---------- قالب الإيميل ---------- */
  var fm = form('mailtpl');
  if (fm) { var pvBox = fm.nextElementSibling && fm.nextElementSibling.querySelector('.rounded-xl');
    function mail() { if (!pvBox) return; var on = fm.on.checked, col = /^#[0-9a-f]{6}$/i.test(fm.color.value) ? fm.color.value : '#4f46e5', lg = fm.logo.value.trim(), sig = fm.sig.value.trim(), ft2 = fm.footer.value.trim();
      var body = '<div style="font-size:15px;line-height:1.8">' + esc(T.mailBody).replace(/\n/g, '<br>') + '</div>', un = '<a style="color:#94a3b8">' + esc(T.unsub) + '</a>';
      pvBox.innerHTML = !on ? '<div style="padding:20px;font-family:Tahoma,Arial,sans-serif">' + body + '<p style="font-size:11px;color:#94a3b8;margin-top:24px">' + un + '</p></div>' :
        '<div style="background:#f1f5f9;padding:20px 12px"><div style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;font-family:Tahoma,Arial,sans-serif;box-shadow:0 8px 24px -12px rgb(0 0 0/.2)">' +
        '<div style="background:' + col + ';padding:18px 24px;transition:background .3s">' + (/^https?:\/\//i.test(lg) ? '<img src="' + esc(lg) + '" alt="" style="max-height:44px;max-width:180px">' : '<b style="font-size:18px;color:#fff">' + esc(C.ws) + '</b>') + '</div>' +
        '<div style="padding:24px;color:#0f172a">' + body + (sig ? '<div style="margin-top:20px;padding-top:14px;border-top:1px solid #e2e8f0;color:#475569;font-size:14px">' + esc(sig).replace(/\n/g, '<br>') + '</div>' : '') +
        '<p style="margin-top:18px"><a style="display:inline-block;background:' + col + ';color:#fff;padding:10px 18px;border-radius:10px;font-size:14px;text-decoration:none">' + esc(T.cta) + '</a></p></div>' +
        '<div style="padding:14px 24px;background:#f8fafc;font-size:11px;color:#94a3b8">' + (ft2 ? esc(ft2) + '<br>' : '') + un + '</div></div></div>'; }
    var cl = fm.color; if (cl) { var row = document.createElement('div'); row.className = 'cs-cols'; row.innerHTML = C.brand.map(function (c) { return '<button type="button" style="background:' + c + '" data-mc="' + c + '"' + (RO ? ' disabled' : '') + '></button>'; }).join(''); cl.closest('div').after(row);
      row.addEventListener('click', function (e) { var b = e.target.closest('[data-mc]'); if (!b) return; cl.value = b.dataset.mc; mail(); }); }
    // اللوجو: من مكتبة الوسائط (رفع أو اختيار) بدل ما يلصق رابط
    var lg = fm.logo; if (lg) { lg.classList.add('hidden'); var lb2 = lg.closest('label'), box2 = document.createElement('div'); box2.className = 'cs-logo';
      function drawLogo() { var u = lg.value.trim(); box2.innerHTML = (u ? '<img src="' + esc(u) + '" alt="">' : '<span class="cs-logo-ph">' + ic('image', 'w-6 h-6') + '</span>') +
        '<div class="flex flex-col gap-1"><button type="button" class="btn-ghost !py-1 !text-xs" data-lpick' + (RO ? ' disabled' : '') + '>' + ic('upload') + ' ' + esc(u ? T.logoCh : T.logoUp) + '</button>' + (u && !RO ? '<button type="button" class="text-[11px] text-red-600" data-lrm>' + esc(T.logoRm) + '</button>' : '') + '</div>'; }
      lb2.appendChild(box2); drawLogo();
      box2.addEventListener('click', function (e) {
        if (e.target.closest('[data-lrm]')) { lg.value = ''; drawLogo(); lg.dispatchEvent(new Event('input', { bubbles: true })); return; }
        if (!e.target.closest('[data-lpick]') || !window.mediaLoad) return;
        mediaLoad(function () { mediaPick({ accept: 'image', public: true }).then(function (r) { if (!r || !r[0]) return; var u = r[0].url || ''; if (u && !/^https?:/i.test(u)) u = new URL(u, location.href).href;
          lg.value = u; drawLogo(); lg.dispatchEvent(new Event('input', { bubbles: true })); }); }); }); }
    var tg = fm.on; if (tg) { var lb = tg.closest('label'); lb.classList.add('hx-sw'); tg.after(document.createElement('i')); }
    fm.addEventListener('input', mail); fm.addEventListener('change', mail); mail(); }

  /* ---------- الحفظ: زرار بيتفعّل لما يحصل تغيير ---------- */
  $$('form[method=post]').forEach(function (f) { var b = f.querySelector('.btn-primary'); if (!b) return; b.classList.add('cs-save'); b.disabled = true; var on = function () { b.disabled = false; b.classList.add('dirty'); };
    f.addEventListener('input', on); f.addEventListener('change', on); f.addEventListener('click', function (e) { if (e.target.closest('[data-c],[data-ty],[data-mc],[data-xr],[data-rm],.cs-addrule')) on(); }); });
  var nav = $('.mb-4.flex.flex-wrap.gap-1\\.5'); if (nav && window.self !== window.top) nav.classList.add('hidden');
  // جوه لوحة المصادر: نعرض الأقسام المطلوبة بس (#stages,pipes) بنفس شكل اللوحة
  if (window.self !== window.top) { document.documentElement.classList.add('cs-embed'); var want = decodeURIComponent(location.hash.replace('#', '')).split(',').filter(Boolean);
    if (want.length) $$('section[id]').forEach(function (s) { var on = want.indexOf(s.id) >= 0; s.classList.toggle('hidden', !on); if (on) s.classList.remove('xl:col-span-2'); });
    var g = $('.grid.xl\\:grid-cols-2'); if (g) g.className = 'grid gap-3'; }
})();
