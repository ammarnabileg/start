/* قاعدة المعرفة: تفاعل إضافي (تقدّم لكل قسم، تحقق فوري، تنقل بالكيبورد، أدوات ساعات العمل) */
(function () {
  var K = window.KB, root = document.getElementById('kb'); if (!root || !K) return;
  var U = K.ux || {};
  var secs = [].slice.call(root.querySelectorAll('[data-kb]')), order = secs.map(function (s) { return s.dataset.kb; });
  var go = function (k) { var b = root.querySelector('#kb-nav [data-kb-go="' + k + '"]'); if (b) b.click(); };
  var cur = function () { var s = secs.filter(function (x) { return !x.classList.contains('hidden'); })[0]; return s ? s.dataset.kb : null; };
  var css = document.createElement('style');
  css.textContent = '@keyframes kbIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}.kb-in{animation:kbIn .28s ease-out}'
    + '@keyframes kbPop{0%{transform:scale(1)}50%{transform:scale(1.9)}100%{transform:scale(1)}}.kb-pop{animation:kbPop .5s ease-out}'
    + '.kb-ok-ic{opacity:0;transform:scale(.6);transition:all .2s}.kb-ok .kb-ok-ic{opacity:1;transform:none}';
  document.head.appendChild(css);

  // 1) حركة عند فتح القسم
  new MutationObserver(function (m) { m.forEach(function (r) { var s = r.target; if (s.matches('[data-kb]') && !s.classList.contains('hidden') && r.oldValue && r.oldValue.indexOf('hidden') > -1) { s.classList.remove('kb-in'); void s.offsetWidth; s.classList.add('kb-in'); } }); })
    .observe(root, { subtree: true, attributes: true, attributeFilter: ['class'], attributeOldValue: true });

  // 2) تقدّم كل قسم: عدّاد في القايمة وشريط فوق الحقول
  function fieldsOf(form) { return [].filter.call(form.querySelectorAll('input[name^="d["],textarea[name^="d["]'), function (el) { return el.type !== 'checkbox' && el.type !== 'radio' && el.type !== 'hidden'; }); }
  function stat(form) {
    if (form.dataset.type === 'rows') { var n = [].filter.call(form.querySelectorAll('.kb-row'), function (r) { return [].some.call(r.querySelectorAll('[data-col]'), function (x) { return x.value.trim(); }); }).length; return { done: n, total: null }; }
    if (form.dataset.type !== 'fields') return null;
    var f = fieldsOf(form); return { done: f.filter(function (el) { return el.value.trim(); }).length, total: f.length };
  }
  root.querySelectorAll('.kb-form').forEach(function (form) {
    var sec = form.dataset.section, tab = root.querySelector('#kb-nav [data-kb-go="' + sec + '"]'), s0 = stat(form); if (!s0) return;
    var pill = document.createElement('span'); pill.className = 'kb-cnt-pill text-[10px] tabular-nums rounded-full px-1.5 py-0.5 bg-ink-100 text-ink-500'; if (tab) tab.insertBefore(pill, tab.querySelector('.kb-dot'));
    var bar = null;
    if (form.dataset.type === 'fields') {
      bar = document.createElement('div'); bar.className = 'flex items-center gap-3';
      bar.innerHTML = '<div class="flex-1 h-1.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-l from-lime-500 to-brand-600 transition-all duration-500" style="width:0"></div></div><span class="text-[11px] tabular-nums text-ink-500 whitespace-nowrap"></span>';
      var grid = form.querySelector('.grid'); grid.parentNode.insertBefore(bar, grid);
    }
    var was = null;
    function upd() {
      var s = stat(form);
      pill.textContent = s.total ? s.done + '/' + s.total : (s.done || '');
      pill.classList.toggle('hidden', !s.done && !s.total);
      var full = s.total ? s.done === s.total : s.done > 0;
      pill.className = 'kb-cnt-pill text-[10px] tabular-nums rounded-full px-1.5 py-0.5 ' + (full ? 'bg-lime-100 text-lime-700' : s.done ? 'bg-brand-50 text-brand-700' : 'bg-ink-100 text-ink-500') + (!s.done && !s.total ? ' hidden' : '');
      if (bar) { bar.querySelector('.h-full').style.width = (100 * s.done / s.total) + '%'; bar.querySelector('span').textContent = (U.filledOf || '%1 / %2').replace('%1', s.done).replace('%2', s.total); }
      if (was === false && full) { var d = tab && tab.querySelector('.kb-dot'); if (d) { d.classList.remove('kb-pop'); void d.offsetWidth; d.classList.add('kb-pop'); } }
      was = full;
    }
    form.addEventListener('input', upd); form.addEventListener('click', function () { setTimeout(upd, 0); }); upd();
    new MutationObserver(function () { setTimeout(upd, 0); }).observe(form, { childList: true, subtree: true });
  });

  // 3) حالة كل حقل: علامة ✓، تحقق فوري، وزرار فتح/اتصال
  var ok = '<svg class="kb-ok-ic w-3.5 h-3.5 text-lime-600 inline-block ms-1" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12l5 5L20 7"/></svg>';
  var rx = { url: /^(https?:\/\/)?([\w-]+\.)+[a-z]{2,}(\/\S*)?$/i, email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/, tel: /^\+?[\d\s()-]{7,20}$/ };
  root.querySelectorAll('.kb-form[data-type="fields"] label').forEach(function (l) {
    var el = l.querySelector('input,textarea'), cap = l.querySelector('.label'); if (!el || !cap) return;
    cap.insertAdjacentHTML('beforeend', ok);
    var kind = el.type, wrap = null, msg = null, act = null;
    if (rx[kind]) {
      wrap = document.createElement('div'); wrap.className = 'relative'; el.parentNode.insertBefore(wrap, el); wrap.appendChild(el);
      act = document.createElement('a'); act.target = '_blank'; act.rel = 'noopener'; act.className = 'hidden absolute top-1/2 -translate-y-1/2 right-2 rtl:right-auto rtl:left-2 mt-0.5 rounded-lg px-2 py-1 text-[11px] font-medium text-brand-700 bg-brand-50 hover:bg-brand-100';
      wrap.appendChild(act); el.classList.add('pr-20', 'rtl:pr-3', 'rtl:pl-20');
      msg = document.createElement('p'); msg.className = 'hidden text-[11px] text-red-600 mt-1'; l.appendChild(msg);
    }
    function chk() {
      var v = el.value.trim(); l.classList.toggle('kb-ok', !!v && (!rx[kind] || rx[kind].test(v)));
      if (!rx[kind]) return;
      var bad = v && !rx[kind].test(v);
      el.classList.toggle('!border-red-400', !!bad); msg.classList.toggle('hidden', !bad); msg.textContent = bad ? (U['bad_' + kind] || '') : '';
      var good = v && !bad; act.classList.toggle('hidden', !good);
      if (good) {
        if (kind === 'url') { act.href = /^https?:/i.test(v) ? v : 'https://' + v; act.textContent = U.open; }
        else if (kind === 'email') { act.href = 'mailto:' + v; act.textContent = U.mail; }
        else { var n = v.replace(/[^\d+]/g, ''); var wa = el.name === 'd[whatsapp]'; act.href = wa ? 'https://wa.me/' + n.replace(/^\+/, '').replace(/^0(?=1)/, '20') : 'tel:' + n; act.textContent = wa ? U.chat : U.call; }
      }
    }
    el.addEventListener('input', chk); el.addEventListener('blur', function () { if (kind === 'url' && el.value.trim() && !/^https?:/i.test(el.value.trim()) && rx.url.test(el.value.trim())) { el.value = 'https://' + el.value.trim(); el.dispatchEvent(new Event('input', { bubbles: true })); } });
    new MutationObserver(chk).observe(el, { attributes: true }); chk();
    if (el.tagName === 'TEXTAREA') { var fit = function () { el.style.height = 'auto'; el.style.height = Math.max(el.scrollHeight, 60) + 'px'; }; el.addEventListener('input', fit); setTimeout(fit, 0); }
  });

  // 4) ساعات العمل: مغلق بضغطة، ونفس الميعاد لكل الأيام
  var hf = root.querySelector('.kb-form[data-section="hours"]');
  if (hf) {
    var days = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'].map(function (d) { return hf.querySelector('[name="d[' + d + ']"]'); }).filter(Boolean);
    var fire = function (el) { el.dispatchEvent(new Event('input', { bubbles: true })); };
    var tb = document.createElement('div'); tb.className = 'flex flex-wrap gap-2';
    [['same', U.sameAll], ['weekdays', U.weekdays], ['clear', U.clearAll]].forEach(function (b) {
      var x = document.createElement('button'); x.type = 'button'; x.dataset.h = b[0]; x.className = 'rounded-full border border-ink-200 bg-white px-3 py-1 text-xs text-ink-700 hover:border-brand-300 hover:bg-brand-50 transition'; x.textContent = b[1]; tb.appendChild(x);
    });
    var g = hf.querySelector('.grid'); g.parentNode.insertBefore(tb, g);
    tb.addEventListener('click', function (e) {
      var b = e.target.closest('[data-h]'); if (!b) return;
      var src = days.filter(function (d) { return d.value.trim() && d.value.trim() !== U.closed; })[0];
      if (b.dataset.h === 'clear') days.forEach(function (d) { d.value = ''; fire(d); });
      else if (!src) { (days[0] || {}).focus && days[0].focus(); }
      else days.forEach(function (d) { var key = (d.name.match(/\[(\w+)\]/) || [])[1]; if (b.dataset.h === 'weekdays' && (U.weekend || ['fri']).indexOf(key) > -1) d.value = U.closed; else d.value = src.value; fire(d); });
    });
    days.forEach(function (d) {
      var c = document.createElement('button'); c.type = 'button'; c.className = 'absolute top-1/2 -translate-y-1/2 right-2 rtl:right-auto rtl:left-2 mt-0.5 rounded-lg px-2 py-1 text-[11px] text-ink-500 hover:text-red-600 hover:bg-red-50'; c.textContent = U.closed;
      var w = document.createElement('div'); w.className = 'relative'; d.parentNode.insertBefore(w, d); w.appendChild(d); w.appendChild(c); d.classList.add('pr-16', 'rtl:pr-3', 'rtl:pl-16');
      c.onclick = function () { d.value = d.value.trim() === U.closed ? '' : U.closed; fire(d); d.focus(); };
      var paint = function () { var cl = d.value.trim() === U.closed; d.classList.toggle('!bg-ink-100', cl); d.classList.toggle('!text-ink-500', cl); c.classList.toggle('!text-red-600', cl); };
      d.addEventListener('input', paint); paint();
    });
  }

  // 5) السابق / التالي في آخر كل قسم + Alt + الأسهم
  secs.forEach(function (s, i) {
    if (s.dataset.kb === 'test') return;
    var nav = document.createElement('div'); nav.className = 'mt-5 pt-4 border-t border-ink-100 flex items-center justify-between gap-2';
    var prev = order[i - 1], next = order[i + 1];
    var lbl = function (k) { var t = root.querySelector('#kb-nav [data-kb-go="' + k + '"] .flex-1'); return t ? t.textContent.trim() : ''; };
    nav.innerHTML = (prev ? '<button type="button" data-kb-go="' + prev + '" class="group inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs text-ink-600 hover:bg-ink-100"><span class="transition group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5 rtl:rotate-180">&larr;</span><span></span></button>' : '<span></span>')
      + (next ? '<button type="button" data-kb-go="' + next + '" class="group inline-flex items-center gap-1.5 rounded-xl bg-brand-50 px-3 py-2 text-xs font-medium text-brand-700 hover:bg-brand-100"><span></span><span class="transition group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5 rtl:rotate-180">&rarr;</span></button>' : '');
    var bs = nav.querySelectorAll('button');
    if (prev) bs[0].querySelectorAll('span')[1].textContent = lbl(prev);
    if (next) bs[bs.length - 1].querySelector('span').textContent = (U.next || '') + ': ' + lbl(next);
    s.appendChild(nav);
  });
  document.addEventListener('keydown', function (e) {
    if (!e.altKey || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) return;
    var i = order.indexOf(cur()); var j = i + (e.key === 'ArrowDown' ? 1 : -1); if (order[j]) { e.preventDefault(); go(order[j]); }
  });
  document.addEventListener('click', function () { setTimeout(function () { var s = root.querySelector('[data-kb]:not(.hidden)'); if (s && s.getBoundingClientRect().top < 60) s.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 0); });

  // 6) بيان التموضع: اضغط على أي جزء يوديك لخانته
  root.addEventListener('click', function (e) {
    var p = e.target.closest('.kb-pos-stmt [data-pgo]'); if (!p) return;
    go(p.dataset.pgo); setTimeout(function () { var el = root.querySelector('section[data-kb="' + p.dataset.pgo + '"] [name="d[' + p.dataset.pf + ']"]'); if (el) { el.focus(); el.classList.add('ring-4', 'ring-brand-500/20'); setTimeout(function () { el.classList.remove('ring-4', 'ring-brand-500/20'); }, 1200); } }, 60);
  });
  // 7) ربط الخدمات والمنتجات ↔ التموضع لكل منتج: كل خدمة ليها بطاقة تلقائيًا
  var sf = root.querySelector('.kb-form[data-section="services"]'), pf = root.querySelector('.kb-form[data-section="pos_offers"]');
  if (sf && pf) {
    var names = function () { return [].map.call(sf.querySelectorAll('.kb-row [data-col="name"]'), function (i) { return i.value.trim(); }).filter(Boolean); };
    var cards = function () { return [].slice.call(pf.querySelectorAll('.kb-row')); };
    var offerOf = function (c) { var i = c.querySelector('[data-col="offer"]'); return i ? i.value.trim() : ''; };
    var badge = function (c, kind) {
      var b = c.querySelector('.kb-link-badge'); if (!b) { b = document.createElement('span'); b.className = 'kb-link-badge absolute top-2.5 end-11 text-[10px] rounded-full px-2 py-0.5'; c.appendChild(b); }
      b.className = 'kb-link-badge absolute top-2.5 end-11 text-[10px] rounded-full px-2 py-0.5 ' + (kind === 'ok' ? 'bg-lime-100 text-lime-700' : 'bg-amber-100 text-amber-800');
      b.textContent = kind === 'ok' ? U.linked : U.orphan;
    };
    var syncing = false;
    function sync() {
      if (syncing) return; syncing = true;
      var ns = names(), have = cards().map(offerOf);
      ns.forEach(function (n) {
        if (have.indexOf(n) > -1) return;
        var empty = cards().filter(function (c) { return ![].some.call(c.querySelectorAll('[data-col]'), function (x) { return x.value.trim(); }); })[0];
        var c = empty || cards()[cards().length - 1].cloneNode(true);
        if (!empty) { c.querySelectorAll('[data-col]').forEach(function (x) { x.value = ''; }); var bb = c.querySelector('.kb-link-badge'); if (bb) bb.remove(); pf.querySelector('.kb-rows').appendChild(c); }
        c.querySelector('[data-col="offer"]').value = n; have.push(n);
      });
      cards().forEach(function (c) {
        var o = offerOf(c), inp = c.querySelector('[data-col="offer"]'), linked = !!o && ns.indexOf(o) > -1;
        inp.readOnly = linked; inp.classList.toggle('!bg-ink-50', linked); inp.title = linked ? U.linkedHint : '';
        if (o) badge(c, linked ? 'ok' : 'orphan'); else { var b = c.querySelector('.kb-link-badge'); if (b) b.remove(); }
        c.dataset.offer = o;
      });
      // ترتيب البطاقات زي ترتيب الخدمات
      var box = pf.querySelector('.kb-rows');
      ns.slice().reverse().forEach(function (n) { var c = cards().filter(function (x) { return offerOf(x) === n; })[0]; if (c) box.insertBefore(c, box.firstChild); });
      // زرار "التموضع" جنب كل خدمة
      sf.querySelectorAll('.kb-row').forEach(function (r) {
        var n = (r.querySelector('[data-col="name"]') || {}).value || '', btn = r.querySelector('.kb-to-pos');
        if (!btn) { btn = document.createElement('button'); btn.type = 'button'; btn.className = 'kb-to-pos self-center whitespace-nowrap rounded-lg px-2 py-1 text-[11px] font-medium text-brand-700 bg-brand-50 hover:bg-brand-100'; var del = r.querySelector('.kb-del'); r.insertBefore(btn, del); }
        btn.textContent = U.toPos; btn.classList.toggle('invisible', !n.trim()); btn.dataset.offer = n.trim();
        var c = cards().filter(function (x) { return offerOf(x) === n.trim(); })[0], done = c && [].filter.call(c.querySelectorAll('textarea[data-col]'), function (x) { return x.value.trim(); }).length;
        btn.textContent = U.toPos + (c ? ' ' + (done || 0) + '/5' : '');
      });
      syncing = false;
    }
    var st = null, later = function () { clearTimeout(st); st = setTimeout(sync, 350); };
    sf.addEventListener('input', later); pf.addEventListener('input', later);
    new MutationObserver(later).observe(sf.querySelector('.kb-rows'), { childList: true });
    new MutationObserver(later).observe(pf.querySelector('.kb-rows'), { childList: true });
    sf.addEventListener('click', function (e) {
      var b = e.target.closest('.kb-to-pos'); if (!b) return;
      go('pos_offers'); setTimeout(function () { var c = cards().filter(function (x) { return offerOf(x) === b.dataset.offer; })[0]; if (!c) return; c.scrollIntoView({ behavior: 'smooth', block: 'center' }); c.classList.add('ring-4', 'ring-brand-500/25'); setTimeout(function () { c.classList.remove('ring-4', 'ring-brand-500/25'); }, 1600); var f = c.querySelector('textarea[data-col]'); if (f) f.focus({ preventScroll: true }); }, 80);
    });
    // الخدمات اتعدّلت: المسميات القديمة اللي في الكروت نغيّرها لو اتغير اسم الخدمة وهي لسه بتكتب
    sf.addEventListener('focusin', function (e) { if (e.target.matches('[data-col="name"]')) e.target.__old = e.target.value.trim(); });
    sf.addEventListener('change', function (e) {
      var i = e.target; if (!i.matches('[data-col="name"]') || !i.__old || i.__old === i.value.trim()) return;
      var c = cards().filter(function (x) { return offerOf(x) === i.__old; })[0];
      if (c && names().filter(function (n) { return n === i.__old; }).length === 0) { c.querySelector('[data-col="offer"]').value = i.value.trim(); pf.dispatchEvent(new Event('input', { bubbles: true })); }
      i.__old = i.value.trim(); later();
    });
    sync();
  }
})();
