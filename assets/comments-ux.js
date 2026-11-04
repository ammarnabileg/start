/* صفحة التعليقات: نفس طبقة تفاعل الرسائل (مسودة لكل منشور، بحث فوري في المنشورات، بحث جوه التعليقات، نسخ/اقتباس، آخر تعليق، اللي من غير رد، اختصارات) */
(function () {
  var U = window.CMXU, root = document.getElementById('cmx'); if (!U || !root) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch (e) {} } };
  var css = document.createElement('style');
  css.textContent = '@keyframes cmIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}.cm-new{animation:cmIn .3s ease-out}'
    + 'mark.cm-hl{background:rgb(250 204 21/.55);color:inherit;border-radius:3px;padding:0 1px}mark.cm-hl.on{background:rgb(249 115 22/.75);outline:2px solid rgb(249 115 22/.5)}'
    + '.cm-target>div>.inline-block{box-shadow:0 0 0 3px rgb(var(--brand-500)/.35)}';
  document.head.appendChild(css);
  var post = function () { return new URLSearchParams(location.search).get('post') || ''; };
  var DK = 'cm_draft_' + (U.ws || '') + '_';
  var BODY = '.cm-item p.whitespace-pre-line';

  /* ---------- 1) مسودة لكل منشور + إيموجي + عدّاد ---------- */
  function box() { return $('#replyForm textarea[name=message]'); }
  function grow(t) { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 160) + 'px'; }
  function decorateCompose() {
    var t = box(); if (!t || t.__ux) return; t.__ux = true; var pid = post();
    var d = pid && store.get(DK + pid); if (d && !t.value) { t.value = d; grow(t); }
    var meta = document.createElement('div'); meta.className = 'flex items-center gap-3 px-5 -mt-1 pb-2 text-[10px] text-ink-400';
    meta.innerHTML = '<span class="cm-dr hidden text-amber-600"></span><span class="cm-len ms-auto tabular-nums"></span>';
    t.closest('form').after(meta);
    function upd() {
      grow(t); var v = t.value; meta.querySelector('.cm-len').textContent = v.length ? v.length + ' ' + U.chars : '';
      var dr = meta.querySelector('.cm-dr'); dr.classList.toggle('hidden', !v.trim()); dr.textContent = '✎ ' + U.draftSaved;
      if (pid) store.set(DK + pid, v.trim() ? v : ''); markDrafts();
    }
    t.addEventListener('input', upd); upd();
    t.closest('form').addEventListener('submit', function () { if (pid) store.set(DK + pid, ''); setTimeout(markDrafts, 50); });
    var em = document.createElement('button'); em.type = 'button'; em.className = 'shrink-0 p-2.5 rounded-lg hover:bg-ink-100 text-ink-500 relative'; em.title = U.emoji;
    em.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 007 0M9 9.5h.01M15 9.5h.01"/></svg>';
    t.before(em);
    var pop = null;
    em.onclick = function (e) {
      e.stopPropagation(); if (pop) { pop.remove(); pop = null; return; }
      pop = document.createElement('div'); pop.className = 'absolute bottom-full mb-2 start-0 z-30 w-64 grid grid-cols-8 gap-1 rounded-2xl border border-ink-200 bg-white p-2 shadow-xl';
      '😊 😂 😍 🙏 👍 👌 🙌 👏 ❤️ 🔥 ✨ 🎉 ✅ ⭐ 💯 🤝 😅 😉 🤔 😢 😮 👋 📦 🚚 💳 📍 ⏰ 📞 🎁 🛒 💬 📩'.split(' ').forEach(function (x) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'h-7 rounded-lg hover:bg-ink-100 text-lg leading-none'; b.textContent = x;
        b.onclick = function (ev) { ev.stopPropagation(); var s = t.selectionStart, en = t.selectionEnd; t.value = t.value.slice(0, s) + x + t.value.slice(en); t.setSelectionRange(s + x.length, s + x.length); t.dispatchEvent(new Event('input', { bubbles: true })); t.focus(); };
        pop.appendChild(b);
      });
      em.appendChild(pop);
    };
    document.addEventListener('click', function () { if (pop) { pop.remove(); pop = null; } });
  }
  function postLinks() { return $$('[data-live="list"] > a[href*="post="]'); }
  function markDrafts() {
    postLinks().forEach(function (a) {
      var id = new URL(a.href, location.href).searchParams.get('post') || '', has = !!store.get(DK + id), tag = a.querySelector('.cm-drtag');
      if (has && !tag) { var row = a.querySelector('.flex.items-center.gap-2.mt-1\\.5'); if (row) { tag = document.createElement('span'); tag.className = 'cm-drtag rounded-md bg-amber-100 text-amber-800 px-1.5 py-0.5 text-[10px] font-medium'; tag.textContent = U.draft; row.appendChild(tag); } }
      if (!has && tag) tag.remove();
    });
  }

  /* ---------- 2) بحث فوري في المنشورات ---------- */
  // الصفحة بتبدّل العمود كله عند تغيير المنشور، فبنركّب البحث وزرار الاختصارات من جديد كل مرة (والكلمة المكتوبة بتفضل)
  var q = null, qVal = '';
  function setupList() {
    var lst = $('[data-live="list"]'), listHead = lst && lst.previousElementSibling; if (!listHead || $('#cm-q')) { q = $('#cm-q'); return; }
    var wrap = document.createElement('div'); wrap.className = 'px-3 py-2 border-b border-ink-100 shrink-0';
    wrap.innerHTML = '<div class="relative"><svg class="w-4 h-4 absolute top-1/2 -translate-y-1/2 start-3 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/></svg><input type="search" id="cm-q" class="input !mt-0 !h-9 !py-0 ps-9 text-sm" dir="auto"></div><p id="cm-qhint" class="hidden text-[11px] text-ink-500 mt-1.5"></p>';
    listHead.after(wrap); q = $('#cm-q'); q.placeholder = U.searchPosts + ' (/)'; q.value = qVal;
    q.addEventListener('input', function () { qVal = q.value; instant(); });
    var hb = document.createElement('button'); hb.type = 'button'; hb.className = 'shrink-0 w-7 h-7 rounded-lg text-xs font-semibold text-ink-500 hover:bg-ink-100'; hb.title = U.keysTitle + ' (?)'; hb.textContent = '?'; hb.onclick = help;
    var fl = listHead.querySelector('.ms-auto'); if (fl) { fl.classList.remove('ms-auto'); fl.classList.add('ms-1'); } hb.classList.add('ms-auto'); listHead.appendChild(hb);
  }
  function instant() {
    if (!q) return; var v = q.value.trim().toLowerCase(), n = 0;
    postLinks().forEach(function (a) {
      var p = a.querySelector('p.line-clamp-2'); if (p && !p.__t) p.__t = p.textContent;
      var hit = !v || ((p && p.__t) || '').toLowerCase().indexOf(v) > -1, dsp = hit ? '' : 'none';
      if (a.style.display !== dsp) a.style.display = dsp; if (hit) n++;
      if (p) { var h = esc(p.__t), i = v ? p.__t.toLowerCase().indexOf(v) : -1; if (i > -1) h = esc(p.__t.slice(0, i)) + '<mark class="cm-hl">' + esc(p.__t.slice(i, i + v.length)) + '</mark>' + esc(p.__t.slice(i + v.length)); if (p.__h !== h) { p.__h = h; p.innerHTML = h; } }
    });
    var hint = $('#cm-qhint'), ht = U.instant.replace('%s', n); hint.classList.toggle('hidden', !v); if (hint.textContent !== ht) hint.textContent = ht;
  }

  /* ---------- 3) أدوات على كل تعليق: نسخ واقتباس ---------- */
  function decorateThread() {
    $$('.cm-item').forEach(function (it) {
      if (it.__ux) return; it.__ux = true;
      var acts = it.querySelector(':scope > .flex-1 > .flex.flex-wrap.items-center.gap-3'), p = it.querySelector(':scope > .flex-1 > .inline-block p.whitespace-pre-line'); if (!acts || !p) return;
      [['copy', U.copy], ['quote', U.quote]].forEach(function (a) { var x = document.createElement('button'); x.type = 'button'; x.dataset.mt = a[0]; x.className = 'hover:text-brand-700'; x.textContent = a[1]; acts.appendChild(x); });
    });
    counts();
  }
  document.addEventListener('click', function (e) {
    var x = e.target.closest('[data-mt]'); if (!x) return;
    var it = x.closest('.cm-item'), txt = (it.querySelector(':scope > .flex-1 > .inline-block p.whitespace-pre-line') || {}).textContent || '';
    if (x.dataset.mt === 'copy') { navigator.clipboard && navigator.clipboard.writeText(txt); var o = x.textContent; x.textContent = '✓ ' + U.copied; setTimeout(function () { x.textContent = o; }, 1200); }
    else {
      var t = box(); if (!t) return; var rb = it.querySelector('[data-reply-to]') || (it.parentNode.closest('.cm-item') || it).querySelector('[data-reply-to]'); if (rb) rb.click();
      t.value = '«' + txt.trim().slice(0, 140) + (txt.trim().length > 140 ? '…' : '') + '»\n' + t.value; t.focus(); t.setSelectionRange(t.value.length, t.value.length); t.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
  // التعليق اللي بترد عليه بيتعلّم
  document.addEventListener('click', function (e) {
    var r = e.target.closest('[data-reply-to]'); $$('.cm-target').forEach(function (x) { x.classList.remove('cm-target'); });
    if (r) { var it = r.closest('.cm-item'); if (it) it.classList.add('cm-target'); }
    if (e.target.closest('#cancelReply')) $$('.cm-target').forEach(function (x) { x.classList.remove('cm-target'); });
  });

  /* ---------- 4) عدّادات على الفلاتر + التالي من غير رد ---------- */
  function counts() {
    var all = $$('.cm-item[data-mine="0"]'), c = { unanswered: all.filter(function (x) { return x.dataset.answered === '0'; }).length, intent: all.filter(function (x) { return x.dataset.intent === '1'; }).length, bad: all.filter(function (x) { return x.dataset.bad === '1'; }).length };
    $$('[data-cm-filter]').forEach(function (b) {
      var n = c[b.dataset.cmFilter]; if (n == null) return; var s = b.querySelector('.cm-cnt');
      if (!s) { s = document.createElement('span'); s.className = 'cm-cnt ms-1 rounded-full px-1.5 text-[10px] tabular-nums'; b.appendChild(s); }
      var tx = String(n); if (s.textContent !== tx) s.textContent = tx; s.className = 'cm-cnt ms-1 rounded-full px-1.5 text-[10px] tabular-nums ' + (n ? (b.dataset.cmFilter === 'bad' ? 'bg-red-100 text-red-700' : b.dataset.cmFilter === 'intent' ? 'bg-lime-100 text-lime-700' : 'bg-brand-100 text-brand-700') : 'bg-ink-100 text-ink-400');
    });
  }
  var ui = -1;
  function nextUnanswered() {
    var list = $$('.cm-item[data-mine="0"][data-answered="0"]').filter(function (x) { return x.offsetParent; }); if (!list.length) { window.aiToast && aiToast(U.allAnswered, 'ok'); return; }
    ui = (ui + 1) % list.length; var it = list[ui];
    it.scrollIntoView({ block: 'center', behavior: 'smooth' }); $$('.cm-target').forEach(function (x) { x.classList.remove('cm-target'); }); it.classList.add('cm-target');
    var r = it.querySelector('[data-reply-to]'); if (r) r.click();
  }

  /* ---------- 5) زرار آخر تعليق + الجديد ---------- */
  var th = null, jump = null, unseen = 0, lastCount = 0, finder = null;
  function setupThread() {
    var t = $('#thread'); if (!t || t.__ux) return; t.__ux = true; th = t; unseen = 0; finder = null;
    jump = document.createElement('button'); jump.type = 'button';
    jump.className = 'hidden absolute start-1/2 -translate-x-1/2 rtl:translate-x-1/2 z-10 inline-flex items-center gap-1.5 rounded-full bg-ink-900 text-ink-50 dark:bg-ink-800 shadow-lg px-3 py-1.5 text-xs font-medium hover:scale-105 transition';
    th.parentNode.style.position = 'relative'; th.parentNode.appendChild(jump);
    var near = function () { return th.scrollHeight - th.scrollTop - th.clientHeight < 80; };
    var paint = function () { var pr = th.parentNode.getBoundingClientRect(), tr = th.getBoundingClientRect(); jump.style.bottom = Math.max(12, pr.bottom - tr.bottom + 12) + 'px'; jump.classList.toggle('hidden', near() || th.scrollHeight <= th.clientHeight + 80); jump.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M6 13l6 6 6-6"/></svg>' + esc(unseen ? U.newC.replace('%s', unseen) : U.latest); jump.classList.toggle('!bg-brand-600', !!unseen); };
    th.addEventListener('scroll', function () { if (near()) unseen = 0; paint(); });
    if (window.ResizeObserver) new ResizeObserver(paint).observe(th);
    jump.onclick = function () { th.scrollTo({ top: th.scrollHeight, behavior: 'smooth' }); unseen = 0; paint(); };
    lastCount = $$('.cm-item', th).length;
    new MutationObserver(function () {
      var n = $$('.cm-item', th).length;
      if (n > lastCount && !near()) unseen += n - lastCount;
      if (n > lastCount) $$('.cm-item', th).filter(function (x) { return !x.__ux; }).forEach(function (x) { x.classList.add('cm-new'); });
      var changed = n !== lastCount; lastCount = n; decorateThread(); paint(); if (changed && finder && finder.v) find(finder.v, true);
    }).observe(th, { childList: true, subtree: true });
    paint();
    var hdr = th.parentNode.querySelector('.h-16'); if (hdr && !$('#cm-findbtn')) {
      var mk = function (id, title, d, fn) { var b = document.createElement('button'); b.type = 'button'; b.id = id; b.className = 'tb w-9 !h-9 justify-center'; b.title = title; b.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' + d + '"/></svg>'; b.onclick = fn; hdr.appendChild(b); };
      mk('cm-nextbtn', U.nextUn + ' (u)', 'M4 12h12M12 6l6 6-6 6', nextUnanswered);
      mk('cm-findbtn', U.findIn + ' (Ctrl+F)', 'M11 17a6 6 0 100-12 6 6 0 000 12zM20 20l-4.5-4.5', openFind);
    }
  }

  /* ---------- 6) بحث جوه التعليقات ---------- */
  function find(v, keep) {
    $$('#thread mark.cm-hl').forEach(function (m) { m.replaceWith(document.createTextNode(m.textContent)); });
    $$('#thread ' + BODY).forEach(function (p) { p.normalize(); });
    finder.hits = []; finder.v = v; if (!v) { finder.cnt.textContent = ''; return; }
    var lv = v.toLowerCase();
    $$('#thread ' + BODY).forEach(function (p) {
      var tx = p.textContent, low = tx.toLowerCase(), i = low.indexOf(lv); if (i < 0) return;
      var html = '', last = 0;
      while (i > -1) { html += esc(tx.slice(last, i)) + '<mark class="cm-hl">' + esc(tx.slice(i, i + v.length)) + '</mark>'; last = i + v.length; i = low.indexOf(lv, last); }
      p.innerHTML = html + esc(tx.slice(last)); finder.hits = finder.hits.concat($$('mark.cm-hl', p));
    });
    if (!keep) finder.i = 0; finder.i = Math.max(0, Math.min(finder.i, finder.hits.length - 1)); show();
  }
  function show() {
    finder.hits.forEach(function (m, k) { m.classList.toggle('on', k === finder.i); });
    finder.cnt.textContent = finder.hits.length ? (finder.i + 1) + '/' + finder.hits.length : '0';
    var m = finder.hits[finder.i]; if (m) m.scrollIntoView({ block: 'center', behavior: 'smooth' });
  }
  function openFind() {
    if (!th) return;
    if (!finder) {
      var b = document.createElement('div'); b.className = 'absolute top-[4.5rem] end-3 z-20 flex items-center gap-1 rounded-xl border border-ink-200 bg-white shadow-lg p-1';
      b.innerHTML = '<input class="input !mt-0 !h-8 !py-0 text-xs w-44" dir="auto"><span class="cnt text-[11px] text-ink-500 tabular-nums w-10 text-center"></span><button type="button" data-f="up" class="w-7 h-7 rounded-lg hover:bg-ink-100 text-ink-500">↑</button><button type="button" data-f="dn" class="w-7 h-7 rounded-lg hover:bg-ink-100 text-ink-500">↓</button><button type="button" data-f="x" class="w-7 h-7 rounded-lg hover:bg-ink-100 text-ink-500">✕</button>';
      th.parentNode.appendChild(b);
      finder = { box: b, inp: b.querySelector('input'), cnt: b.querySelector('.cnt'), hits: [], i: 0, v: '' }; finder.inp.placeholder = U.findPh;
      finder.inp.addEventListener('input', function () { find(finder.inp.value.trim()); });
      finder.inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); step(e.shiftKey ? -1 : 1); } if (e.key === 'Escape') closeFind(); });
      b.addEventListener('click', function (e) { var x = e.target.closest('[data-f]'); if (!x) return; if (x.dataset.f === 'x') closeFind(); else step(x.dataset.f === 'up' ? -1 : 1); });
    }
    finder.box.classList.remove('hidden'); finder.inp.focus(); finder.inp.select();
  }
  function step(d) { if (!finder.hits.length) return; finder.i = (finder.i + d + finder.hits.length) % finder.hits.length; show(); }
  function closeFind() { if (!finder) return; find(''); finder.inp.value = ''; finder.box.classList.add('hidden'); }

  /* ---------- 7) الاختصارات ---------- */
  function help() {
    var d = document.createElement('dialog'); d.className = 'w-[min(440px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm text-ink-900"></b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div><div class="p-4 grid gap-1.5 text-sm"></div>';
    d.querySelector('b').textContent = U.keysTitle;
    U.keys.forEach(function (k) { var r = document.createElement('div'); r.className = 'flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-ink-50'; r.innerHTML = '<span class="text-ink-700"></span><span class="flex gap-1">' + k[0].split(' ').map(function (x) { return '<kbd class="min-w-[1.6rem] text-center rounded-md border border-ink-300 border-b-2 bg-ink-50 px-1.5 py-0.5 text-[11px] font-mono text-ink-700">' + esc(x) + '</kbd>'; }).join('') + '</span>'; r.querySelector('span').textContent = k[1]; d.querySelector('.grid').appendChild(r); });
    d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); }); document.body.appendChild(d); d.showModal();
  }
  document.addEventListener('keydown', function (e) {
    var typing = /INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.target.isContentEditable;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f' && th) { e.preventDefault(); openFind(); return; }
    if (typing || e.ctrlKey || e.metaKey || e.altKey) return;
    var ps = postLinks().filter(function (a) { return a.style.display !== 'none'; }), cur = ps.findIndex(function (a) { return new URL(a.href, location.href).searchParams.get('post') === post(); });
    if (e.key === 'j' || e.key === 'k') { var n = ps[Math.max(0, Math.min(ps.length - 1, cur + (e.key === 'j' ? 1 : -1)))]; if (n) { n.click(); } e.preventDefault(); }
    else if (e.key === '/' && q) { e.preventDefault(); q.focus(); }
    else if (e.key === 'r' && box()) { e.preventDefault(); box().focus(); }
    else if (e.key === 'u') { e.preventDefault(); nextUnanswered(); }
    else if (e.key === 'g' && jump) jump.click();
    else if (e.key === '?') { e.preventDefault(); help(); }
  });

  /* ---------- تشغيل وإعادة بعد كل تحديث/تنقل ---------- */
  var tm = null;
  function run() { [setupList, setupThread, decorateCompose, decorateThread, markDrafts, instant].forEach(function (f) { try { f(); } catch (e) { console.error('cmux ' + f.name + ': ' + e.message); } }); }
  run();
  new MutationObserver(function () { if (tm) return; tm = setTimeout(function () { tm = null; run(); }, 80); }).observe(document.body, { childList: true, subtree: true });
})();
