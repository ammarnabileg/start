/* صفحة الرسائل: طبقة تفاعل (مسودات، بحث فوري، بحث داخل المحادثة، أدوات الرسالة، زرار آخر رسالة، اختصارات) */
(function () {
  var U = window.IBXU, root = document.getElementById('ibx'); if (!U || !root) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch (e) {} } };
  var css = document.createElement('style');
  css.textContent = '@keyframes ibxIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}.ibx-new{animation:ibxIn .3s ease-out}'
    + 'mark.ibx-hl{background:rgb(250 204 21/.55);color:inherit;border-radius:3px;padding:0 1px}mark.ibx-hl.on{background:rgb(249 115 22/.75);outline:2px solid rgb(249 115 22/.5)}'
    + '#thread .ibx-day{position:sticky;top:.25rem;z-index:5}';
  document.head.appendChild(css);
  var conv = U.conv || '';
  var curConv = function () { return new URLSearchParams(location.search).get('c') || ''; };
  var toast = function (m) { if (window.aiToast) aiToast(m, 'ok'); };

  /* ---------- 1) مسودة لكل محادثة: بتتحفظ وانت بتكتب، وبتظهر في القايمة ---------- */
  var DK = 'ibx_draft_' + (U.ws || '') + '_';
  function compose() { return $('#ibx-compose textarea[name=message]'); }
  function grow(t) { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 160) + 'px'; }
  function decorateCompose() {
    var t = compose(); if (!t || t.__ux) return; t.__ux = true; conv = curConv();
    var d = conv && store.get(DK + conv); if (d && !t.value) { t.value = d; grow(t); }
    var meta = document.createElement('div'); meta.className = 'flex items-center gap-3 px-5 -mt-1 pb-1 text-[10px] text-ink-400';
    meta.innerHTML = '<span class="ibx-dr hidden text-amber-600"></span><span class="ibx-len ms-auto tabular-nums"></span>';
    var hint = t.closest('form').nextElementSibling; (hint && hint.tagName === 'P' ? hint : t.closest('form')).after(meta);
    function upd() {
      grow(t); var v = t.value; meta.querySelector('.ibx-len').textContent = v.length ? v.length + ' ' + U.chars : '';
      var dr = meta.querySelector('.ibx-dr'); dr.classList.toggle('hidden', !v.trim()); dr.textContent = '✎ ' + U.draftSaved;
      if (conv) store.set(DK + conv, v.trim() ? v : ''); markDrafts();
    }
    t.addEventListener('input', upd); upd();
    t.closest('form').addEventListener('submit', function () { if (conv) store.set(DK + conv, ''); setTimeout(markDrafts, 50); });
    // إيموجي سريع
    var em = document.createElement('button'); em.type = 'button'; em.className = 'p-2.5 rounded-lg hover:bg-ink-100 text-ink-500 relative'; em.title = U.emoji;
    em.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 007 0M9 9.5h.01M15 9.5h.01"/></svg>';
    t.before(em);
    var pop = null;
    em.onclick = function (e) {
      e.stopPropagation(); if (pop) { pop.remove(); pop = null; return; }
      pop = document.createElement('div'); pop.className = 'absolute bottom-full mb-2 start-0 z-30 w-64 grid grid-cols-8 gap-1 rounded-2xl border border-ink-200 bg-white p-2 shadow-xl';
      '😊 😂 😍 🙏 👍 👌 🙌 👏 ❤️ 🔥 ✨ 🎉 ✅ ⭐ 💯 🤝 😅 😉 🤔 😢 😮 👋 📦 🚚 💳 📍 ⏰ 📞 🎁 🛒 💬 ☕'.split(' ').forEach(function (x) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'h-7 rounded-lg hover:bg-ink-100 text-lg leading-none'; b.textContent = x;
        b.onclick = function (ev) { ev.stopPropagation(); var s = t.selectionStart, en = t.selectionEnd; t.value = t.value.slice(0, s) + x + t.value.slice(en); t.setSelectionRange(s + x.length, s + x.length); t.dispatchEvent(new Event('input', { bubbles: true })); t.focus(); };
        pop.appendChild(b);
      });
      em.appendChild(pop);
    };
    document.addEventListener('click', function () { if (pop) { pop.remove(); pop = null; } });
  }
  function markDrafts() {
    $$('.ibx-item').forEach(function (it) {
      var id = (it.dataset.key || '').split('|')[0], has = !!store.get(DK + id), tag = it.querySelector('.ibx-drtag');
      if (has && !tag) { var p = it.querySelector('p.text-xs'); if (p) { tag = document.createElement('span'); tag.className = 'ibx-drtag shrink-0 rounded-md bg-amber-100 text-amber-800 px-1.5 py-0.5 text-[10px] font-medium'; tag.textContent = U.draft; p.before(tag); } }
      if (!has && tag) tag.remove();
    });
  }

  /* ---------- 2) بحث فوري في القايمة المحمّلة (Enter = بحث في كل الرسائل) ---------- */
  var q = null;
  function instant() {
    q = $('#ibx-q'); if (!q) return; var v = q.value.trim().toLowerCase(), n = 0;
    $$('.ibx-item').forEach(function (it) {
      var name = it.querySelector('.text-sm.truncate'), snip = it.querySelector('p.text-xs');
      if (name && !name.__t) name.__t = name.textContent;
      var txt = ((name && name.__t) || '') + ' ' + (snip ? snip.textContent : ''), hit = !v || txt.toLowerCase().indexOf(v) > -1;
      var dsp = hit ? '' : 'none'; if (it.style.display !== dsp) it.style.display = dsp; if (hit) n++;
      if (name) { var h = esc(name.__t); if (v && name.__t.toLowerCase().indexOf(v) > -1) { var i = name.__t.toLowerCase().indexOf(v); h = esc(name.__t.slice(0, i)) + '<mark class="ibx-hl">' + esc(name.__t.slice(i, i + v.length)) + '</mark>' + esc(name.__t.slice(i + v.length)); } if (name.__h !== h) { name.__h = h; name.innerHTML = h; } }
    });
    var hintEl = $('#ibx-qhint');
    if (!hintEl && q) { hintEl = document.createElement('div'); hintEl.id = 'ibx-qhint'; hintEl.className = 'hidden px-3 py-2 text-[11px] text-ink-500 bg-ink-50 border-b border-ink-100'; var lst = $('.ibx-item') && $('.ibx-item').parentNode; if (lst) lst.prepend(hintEl); }
    if (hintEl) { var ht = U.instant.replace('%s', n); hintEl.classList.toggle('hidden', !v); if (hintEl.textContent !== ht) hintEl.textContent = ht; }
  }
  document.addEventListener('input', function (e) { if (e.target.id === 'ibx-q') instant(); });

  /* ---------- 3) أدوات على كل رسالة: نسخ واقتباس في الرد ---------- */
  function decorateThread() {
    var th = $('#thread'); if (!th) return;
    $$('#thread .flex.justify-center.my-2').forEach(function (d) { d.classList.add('ibx-day'); });
    $$('#thread .max-w-\\[80\\%\\].group').forEach(function (b) {
      if (b.__ux) return; b.__ux = true;
      var p = b.querySelector('p.whitespace-pre-line'); if (!p) return;
      var bar = document.createElement('div'); bar.className = 'ibx-mtools flex gap-1 mt-1 opacity-0 group-hover:opacity-100 transition text-[11px]';
      var out = !!b.closest('.justify-start');
      if (!out) bar.classList.add('justify-end');
      [['copy', U.copy, 'M8 8h11v11H8zM5 16V5h11'], ['quote', U.quote, 'M7 7h4v4H8l-1 3M14 7h4v4h-3l-1 3']].forEach(function (a) {
        var x = document.createElement('button'); x.type = 'button'; x.dataset.mt = a[0]; x.className = 'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-ink-500 hover:text-brand-700 hover:bg-brand-50';
        x.innerHTML = '<svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' + a[2] + '"/></svg><span></span>'; x.querySelector('span').textContent = a[1]; bar.appendChild(x);
      });
      b.appendChild(bar);
    });
  }
  document.addEventListener('click', function (e) {
    var x = e.target.closest('[data-mt]'); if (!x) return;
    var txt = (x.closest('.group').querySelector('p.whitespace-pre-line') || {}).textContent || '';
    if (x.dataset.mt === 'copy') { navigator.clipboard && navigator.clipboard.writeText(txt); x.querySelector('span').textContent = '✓ ' + U.copied; setTimeout(function () { x.querySelector('span').textContent = U.copy; }, 1200); }
    else { var t = compose(); if (!t) return; var qt = '«' + txt.trim().slice(0, 140) + (txt.trim().length > 140 ? '…' : '') + '»\n'; t.value = qt + t.value; t.focus(); t.setSelectionRange(t.value.length, t.value.length); t.dispatchEvent(new Event('input', { bubbles: true })); }
  });

  /* ---------- 4) زرار "آخر رسالة" + عدد الرسائل الجديدة وانت فوق ---------- */
  var th = null, jump = null, unseen = 0, lastCount = 0;
  function setupThread() {
    var t = $('#thread'); if (!t || t.__ux) return; t.__ux = true; th = t; unseen = 0; finder = null;
    jump = document.createElement('button'); jump.type = 'button';
    jump.className = 'hidden absolute start-1/2 -translate-x-1/2 rtl:translate-x-1/2 z-10 inline-flex items-center gap-1.5 rounded-full bg-ink-900 text-ink-50 dark:bg-ink-800 shadow-lg px-3 py-1.5 text-xs font-medium hover:scale-105 transition';
    th.parentNode.style.position = 'relative'; th.parentNode.appendChild(jump);
    var near = function () { return th.scrollHeight - th.scrollTop - th.clientHeight < 80; };
    var paint = function () { var pr = th.parentNode.getBoundingClientRect(), tr = th.getBoundingClientRect(); jump.style.bottom = Math.max(12, pr.bottom - tr.bottom + 12) + 'px'; jump.classList.toggle('hidden', near()); jump.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M6 13l6 6 6-6"/></svg>' + esc(unseen ? U.newMsgs.replace('%s', unseen) : U.latest); jump.classList.toggle('!bg-brand-600', !!unseen); };
    th.addEventListener('scroll', function () { if (near()) unseen = 0; paint(); });
    if (window.ResizeObserver) new ResizeObserver(paint).observe(th);
    jump.onclick = function () { th.scrollTo({ top: th.scrollHeight, behavior: 'smooth' }); unseen = 0; paint(); };
    lastCount = $$('#thread .max-w-\\[80\\%\\]').length;
    new MutationObserver(function () {
      var n = $$('#thread .max-w-\\[80\\%\\]').length;
      if (n > lastCount && !near()) unseen += n - lastCount;
      if (n > lastCount) $$('#thread .max-w-\\[80\\%\\]').slice(lastCount).forEach(function (x) { x.classList.add('ibx-new'); });
      var changed = n !== lastCount; lastCount = n; decorateThread(); paint(); if (changed && finder && finder.v) find(finder.v, true);
    }).observe(th, { childList: true });
    paint();
    var hdr = $('[data-ibx-archive]'); hdr = hdr && (hdr.closest('form') || hdr);
    if (hdr && !$('#ibx-findbtn')) {
      var fb = document.createElement('button'); fb.type = 'button'; fb.id = 'ibx-findbtn'; fb.className = 'tb w-9 h-9 !h-9 justify-center'; fb.title = U.findIn + ' (Ctrl+F)';
      fb.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/></svg>';
      fb.onclick = openFind; hdr.parentNode.insertBefore(fb, hdr);
    }
  }

  /* ---------- 5) بحث جوه المحادثة (Ctrl+F) ---------- */
  var finder = null;
  function find(v, keep) {
    $$('#thread mark.ibx-hl').forEach(function (m) { m.replaceWith(document.createTextNode(m.textContent)); });
    $$('#thread p.whitespace-pre-line').forEach(function (p) { p.normalize(); });
    finder.hits = []; finder.v = v; if (!v) { finder.cnt.textContent = ''; return; }
    var lv = v.toLowerCase();
    $$('#thread p.whitespace-pre-line').forEach(function (p) {
      var tx = p.textContent, low = tx.toLowerCase(), i = low.indexOf(lv); if (i < 0) return;
      var html = '', last = 0;
      while (i > -1) { html += esc(tx.slice(last, i)) + '<mark class="ibx-hl">' + esc(tx.slice(i, i + v.length)) + '</mark>'; last = i + v.length; i = low.indexOf(lv, last); }
      p.innerHTML = html + esc(tx.slice(last)); finder.hits = finder.hits.concat($$('mark.ibx-hl', p));
    });
    if (!keep) finder.i = finder.hits.length - 1; finder.i = Math.min(finder.i, finder.hits.length - 1); show();
  }
  function show() {
    finder.hits.forEach(function (m, k) { m.classList.toggle('on', k === finder.i); });
    finder.cnt.textContent = finder.hits.length ? (finder.i + 1) + '/' + finder.hits.length : U.noHits;
    var m = finder.hits[finder.i]; if (m) m.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
  function openFind() {
    if (!th) return;
    if (!finder) {
      var box = document.createElement('div'); box.className = 'absolute top-2 end-3 z-20 flex items-center gap-1 rounded-xl border border-ink-200 bg-white shadow-lg p-1';
      box.innerHTML = '<input class="input !mt-0 !h-8 !py-0 text-xs w-44" dir="auto"><span class="cnt text-[11px] text-ink-500 tabular-nums w-10 text-center"></span>'
        + '<button type="button" data-f="up" class="w-7 h-7 rounded-lg hover:bg-ink-100 text-ink-500">↑</button><button type="button" data-f="dn" class="w-7 h-7 rounded-lg hover:bg-ink-100 text-ink-500">↓</button><button type="button" data-f="x" class="w-7 h-7 rounded-lg hover:bg-ink-100 text-ink-500">✕</button>';
      th.parentNode.appendChild(box);
      finder = { box: box, inp: box.querySelector('input'), cnt: box.querySelector('.cnt'), hits: [], i: 0, v: '' };
      finder.inp.placeholder = U.findPh;
      finder.inp.addEventListener('input', function () { find(finder.inp.value.trim()); });
      finder.inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); step(e.shiftKey ? 1 : -1); } if (e.key === 'Escape') closeFind(); });
      box.addEventListener('click', function (e) { var b = e.target.closest('[data-f]'); if (!b) return; if (b.dataset.f === 'x') closeFind(); else step(b.dataset.f === 'up' ? -1 : 1); });
    }
    finder.box.classList.remove('hidden'); finder.inp.focus(); finder.inp.select();
  }
  function step(d) { if (!finder.hits.length) return; finder.i = (finder.i + d + finder.hits.length) % finder.hits.length; show(); }
  function closeFind() { if (!finder) return; find(''); finder.inp.value = ''; finder.box.classList.add('hidden'); }
  /* ---------- 6) أزرار سريعة على كل محادثة في القايمة (مقروءة / أرشفة) ---------- */
  function decorateList() {
    $$('.ibx-item').forEach(function (it) {
      if (it.querySelector('.ibx-qa') || !U.canAct) return;
      var qa = document.createElement('div'); qa.className = 'ibx-qa absolute top-2 end-2 hidden group-hover:flex items-center gap-0.5 rounded-lg border border-ink-200 bg-white/95 shadow-sm p-0.5';
      var mk = function (op, title, d) { var b = document.createElement('button'); b.type = 'button'; b.dataset.qa = op; b.title = title; b.className = 'w-7 h-7 rounded-md text-ink-500 hover:text-brand-700 hover:bg-brand-50 flex items-center justify-center'; b.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' + d + '"/></svg>'; qa.appendChild(b); };
      if (it.dataset.unread === '1') mk('read', U.markRead, 'M4 12l5 5L20 6');
      mk(U.archived ? 'unarchive' : 'archive', U.archived ? U.unarchive : U.archive, 'M4 7h16v3H4zM6 10v9h12v-9M10 14h4');
      it.appendChild(qa);
    });
    markDrafts(); instant();
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-qa]'); if (!b) return; e.preventDefault(); e.stopPropagation();
    var it = b.closest('.ibx-item'), op = b.dataset.qa; b.disabled = true;
    var fd = new FormData(); fd.append('action', 'bulk'); fd.append('op', op); fd.append('items[]', it.dataset.key);
    fetch(location.pathname + '?p=inbox', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': (document.querySelector('meta[name=csrf-token]') || {}).content || '' } }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.error) { alert(d.error); b.disabled = false; return; }
      if (op === 'read') { it.dataset.unread = '0'; var bd = it.querySelector('.bg-brand-600.rounded-full'); if (bd) bd.remove(); b.remove(); toast(U.doneRead); }
      else { it.style.transition = 'all .25s'; it.style.opacity = '0'; it.style.transform = 'translateX(' + (document.dir === 'rtl' ? '-' : '') + '30px)'; setTimeout(function () { it.remove(); }, 260); toast(op === 'archive' ? U.doneArchive : U.doneUnarchive); }
    }).catch(function () { b.disabled = false; });
  });

  /* ---------- 7) لوحة الاختصارات (?) ---------- */
  function help() {
    var d = document.createElement('dialog'); d.className = 'w-[min(440px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm text-ink-900"></b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div><div class="p-4 grid gap-1.5 text-sm"></div>';
    d.querySelector('b').textContent = U.keysTitle;
    U.keys.forEach(function (k) { var r = document.createElement('div'); r.className = 'flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-ink-50'; r.innerHTML = '<span class="text-ink-700"></span><span class="flex gap-1">' + k[0].split(' ').map(function (x) { return '<kbd class="min-w-[1.6rem] text-center rounded-md border border-ink-300 border-b-2 bg-ink-50 px-1.5 py-0.5 text-[11px] font-mono text-ink-700">' + esc(x) + '</kbd>'; }).join('') + '</span>'; r.querySelector('span').textContent = k[1]; d.querySelector('.grid').appendChild(r); });
    d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); }); document.body.appendChild(d); d.showModal();
  }
  function setupHelp() { if ($('#ibx-helpbtn')) return; var tbar = $('#ibx-q') && $('#ibx-q').closest('form'); if (!tbar) return; var hb = document.createElement('button'); hb.id = 'ibx-helpbtn'; hb.type = 'button'; hb.className = 'tb w-10 justify-center text-sm font-semibold text-ink-500'; hb.title = U.keysTitle + ' (?)'; hb.textContent = '?'; hb.onclick = help; tbar.after(hb); }
  document.addEventListener('keydown', function (e) {
    var typing = /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f' && th) { e.preventDefault(); openFind(); return; }
    if (typing) return;
    if (e.key === '?') { e.preventDefault(); help(); }
    if (e.key === 'g' && jump) { jump.click(); }
  });

  /* ---------- تشغيل + إعادة بعد كل تحديث/تنقل ---------- */
  var tm = null;
  function run() { setupHelp(); setupThread(); decorateCompose(); decorateThread(); decorateList(); }
  run();
  new MutationObserver(function () { if (tm) return; tm = setTimeout(function () { tm = null; run(); }, 80); }).observe(document.body, { childList: true, subtree: true });
})();
