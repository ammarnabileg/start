/* لوحة ✨: محادثة على مستوى المنصة + أوامر سريعة للصفحة الحالية */
(function () {
  var C = window.CP, P = window.AI_PRESETS || {}, I = P.insights || {};
  var panel = document.getElementById('cp'), thread = document.getElementById('cp-thread'), main = document.getElementById('main');
  if (!panel || !C) return;
  var form = document.getElementById('cp-form'), input = document.getElementById('cp-input'), send = document.getElementById('cp-send');
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };
  var loaded = false, busy = false, wide = store.get('cp-wide') === '1', lastField = null, ctrl = null, lastQ = '', stick = true;
  var SVG = function (d, c) { return '<svg class="' + (c || 'w-3.5 h-3.5') + '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="' + d + '"/></svg>'; };
  var IC = { copy: 'M8 8h11v11H8zM5 16V5h11', ins: 'M4 12h12M12 6l6 6-6 6M20 4v16', regen: 'M4 4v6h6M20 20v-6h-6M5.6 15a7 7 0 0012.3 2M18.4 9A7 7 0 006.1 7', up: 'M7 10v11H4V10zM7 10l4-7a2 2 0 012 2v4h6a2 2 0 012 2.3l-1.2 7A2 2 0 0117.8 21H7', down: 'M17 14V3h3v11zM17 14l-4 7a2 2 0 01-2-2v-4H5a2 2 0 01-2-2.3l1.2-7A2 2 0 016.2 3H17', send: 'M5 12h14M13 6l6 6-6 6', stop: 'M7 7h10v10H7z', mic: 'M12 3a3 3 0 013 3v6a3 3 0 01-6 0V6a3 3 0 013-3zM5 11a7 7 0 0014 0M12 18v3' };

  // آخر خانة نص كان المستخدم فيها (عشان "إدراج في الخانة")
  document.addEventListener('focusin', function (e) { var t = e.target; if ((t.tagName === 'TEXTAREA' || (t.tagName === 'INPUT' && t.type === 'text')) && !panel.contains(t)) lastField = t; });

  function post(data) {
    var fd = new FormData(); for (var k in data) fd.append(k, data[k]);
    return fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); });
  }
  function pageText() {
    var m = (main || document.body).cloneNode(true);
    m.querySelectorAll('script,style,nav,#sidebar,dialog').forEach(function (x) { x.remove(); });
    return (m.innerText || m.textContent || '').replace(/\n{3,}/g, '\n\n').slice(0, 6000);
  }
  function scroll(force) { if (force || stick) thread.scrollTop = thread.scrollHeight; jump.classList.toggle('hidden', stick || thread.scrollHeight - thread.clientHeight < 40); }
  // لو المستخدم طلع لفوق يقرا، ماننزلوش غصب عنه
  var jump = document.createElement('button'); jump.type = 'button'; jump.className = 'hidden absolute bottom-28 start-1/2 -translate-x-1/2 rtl:translate-x-1/2 z-10 rounded-full bg-white border border-ink-200 shadow-lg px-3 py-1.5 text-xs text-ink-700 hover:bg-ink-50';
  jump.textContent = '↓ ' + (C.newer || ''); panel.appendChild(jump);
  jump.onclick = function () { stick = true; scroll(true); };
  thread.addEventListener('scroll', function () { stick = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 60; if (stick) jump.classList.add('hidden'); });
  // الرد كلمة كلمة: بنقرا الـ stream ونبعت كل جزء لـ onDelta
  function postStream(data, onDelta) {
    data.stream = 1;
    var fd = new FormData(); for (var k in data) fd.append(k, data[k]);
    ctrl = window.AbortController ? new AbortController() : null;
    return fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() }, signal: ctrl ? ctrl.signal : undefined }).then(function (r) {
      var ct = r.headers.get('content-type') || '';
      if (ct.indexOf('text/event-stream') < 0 || !r.body || !window.TextDecoder) return r.json();
      var reader = r.body.getReader(), dec = new TextDecoder(), buf = '', final = null;
      function pump() {
        return reader.read().then(function (x) {
          if (x.done) return final || { error: C.err };
          buf += dec.decode(x.value, { stream: true });
          var parts = buf.split('\n\n'); buf = parts.pop();
          parts.forEach(function (ev) {
            ev.split('\n').forEach(function (line) {
              if (line.indexOf('data:') !== 0) return;
              try { var o = JSON.parse(line.slice(5)); } catch (e) { return; }
              if (o.d != null) onDelta(o.d); else final = o;
            });
          });
          return pump();
        });
      }
      return pump();
    });
  }
  // عرض مبدئي بسيط وهو بيكتب (الـ Markdown الكامل بييجي من السيرفر في الآخر)
  function liveMd(t) {
    var esc = t.replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; });
    return esc.split('\n').map(function (l) {
      l = l.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
      if (/^#{1,4}\s/.test(l)) return '<p class="font-semibold text-ink-900">' + l.replace(/^#{1,4}\s/, '') + '</p>';
      if (/^\s*[-*•]\s/.test(l)) return '<p class="ps-4 relative"><span class="absolute start-0">•</span>' + l.replace(/^\s*[-*•]\s/, '') + '</p>';
      return l === '' ? '<div class="h-1"></div>' : '<p>' + l + '</p>';
    }).join('');
  }
  function streamInto(box, data) {
    var text = '', raf = 0;
    box.innerHTML = '<div class="cp-md leading-relaxed space-y-1 break-words"></div>';
    var md = box.firstChild;
    return postStream(data, function (d) {
      text += d;
      if (!raf) raf = requestAnimationFrame(function () { raf = 0; md.innerHTML = liveMd(text) + '<span class="cp-caret inline-block w-1.5 h-4 align-middle bg-brand-500 animate-pulse ms-0.5"></span>'; scroll(); });
    }).then(function (d) { if (raf) cancelAnimationFrame(raf); fill(box, d); return d; })
      .catch(function (e) {
        if (raf) cancelAnimationFrame(raf);
        // إيقاف من المستخدم: نسيب اللي اتكتب
        if (e && e.name === 'AbortError') { fill(box, { html: liveMd(text) + '<p class="text-[11px] text-ink-400 mt-1">— ' + (C.stopped || '') + '</p>', text: text, meta: '' }); return; }
        throw e;
      });
  }
  function el(html) { var d = document.createElement('div'); d.innerHTML = html; return d.firstElementChild; }

  function bubbleUser(html, isAction) {
    var b = el('<div class="flex justify-end"><div class="max-w-[85%] rounded-2xl rounded-ee-md px-3.5 py-2 text-sm ' + (isAction ? 'bg-ink-100 text-ink-800 border border-ink-200' : 'bg-brand-600 text-[#fff]') + '"></div></div>');
    b.firstChild.innerHTML = html; thread.appendChild(b); scroll(); return b;
  }
  function bubbleAI() {
    var b = el('<div class="flex gap-2.5"><span class="w-7 h-7 shrink-0 rounded-lg bg-gradient-to-br from-brand-600 to-cyan-500 text-[#fff] flex items-center justify-center">' + (C.spark || '') + '</span><div class="cp-ai flex-1 min-w-0 rounded-2xl rounded-ss-md bg-ink-50 border border-ink-200 px-3.5 py-2.5 text-sm text-ink-800"><span class="inline-flex gap-1 py-1"><i class="w-1.5 h-1.5 rounded-full bg-ink-400 animate-bounce"></i><i class="w-1.5 h-1.5 rounded-full bg-ink-400 animate-bounce [animation-delay:.15s]"></i><i class="w-1.5 h-1.5 rounded-full bg-ink-400 animate-bounce [animation-delay:.3s]"></i></span></div></div>');
    thread.appendChild(b); scroll(); return b.querySelector('.cp-ai');
  }
  function fill(box, d) {
    if (d.error) { box.className = box.className.replace('bg-ink-50 border-ink-200', 'bg-red-50 border-red-200'); box.innerHTML = '<p class="text-red-700"></p>'; box.firstChild.textContent = d.error; scroll(); return; }
    var text = d.csv != null ? d.csv : (d.text || '');
    var btn = function (cls, ic, tip) { return '<button type="button" class="' + cls + ' w-7 h-7 rounded-lg flex items-center justify-center text-ink-400 hover:text-ink-900 hover:bg-ink-200/60 transition" title="' + tip + '">' + SVG(IC[ic]) + '</button>'; };
    box.innerHTML = '<div class="cp-md leading-relaxed space-y-2 break-words"></div>'
      + '<div class="flex flex-wrap items-center gap-0.5 mt-2.5 pt-2 border-t border-ink-200/70">' + btn('cp-copy', 'copy', C.copy) + btn('cp-ins', 'ins', C.insert) + btn('cp-regen', 'regen', C.regen) + btn('cp-up', 'up', C.good) + btn('cp-down', 'down', C.bad)
      + '<span class="ms-auto text-[10px] text-ink-400" dir="ltr"></span></div>'
      + '<div class="cp-refine flex flex-wrap gap-1.5 mt-2"></div>';
    var md = box.querySelector('.cp-md');
    if (d.csv != null) {
      var pre = document.createElement('pre'); pre.className = 'text-xs whitespace-pre-wrap max-h-60 overflow-auto bg-white rounded-lg border border-ink-200 p-2'; pre.dir = 'auto'; pre.textContent = d.csv; md.appendChild(pre);
      var a = document.createElement('a'); a.className = 'btn-primary !py-1 !px-2.5 !text-xs'; a.textContent = C.csv + ' (' + (d.rows || 0) + ')';
      a.href = URL.createObjectURL(new Blob(['﻿' + d.csv], { type: 'text/csv;charset=utf-8' })); a.download = 'ai-posts.csv'; md.appendChild(a);
    } else md.innerHTML = d.html || '';
    box.querySelector('span[dir]').textContent = d.meta || '';
    var cp = box.querySelector('.cp-copy'), ins = box.querySelector('.cp-ins'), rg = box.querySelector('.cp-regen'), q = box.dataset.q || lastQ;
    cp.onclick = function () { navigator.clipboard && navigator.clipboard.writeText(text); cp.innerHTML = SVG('M5 12l5 5L20 7'); cp.classList.add('text-lime-600'); setTimeout(function () { cp.innerHTML = SVG(IC.copy); cp.classList.remove('text-lime-600'); }, 1200); };
    if (q && C.chat) rg.onclick = function () { ask(q); }; else rg.remove();
    ['cp-up', 'cp-down'].forEach(function (k) { var b = box.querySelector('.' + k); b.onclick = function () { box.querySelectorAll('.cp-up,.cp-down').forEach(function (x) { x.classList.remove('text-brand-600', 'bg-brand-50'); }); b.classList.add('text-brand-600', 'bg-brand-50'); }; });
    // اقتراحات تعديل سريعة على الرد
    var rf = box.querySelector('.cp-refine');
    if (C.chat && text && d.csv == null) (C.refine || []).forEach(function (r) {
      var c = document.createElement('button'); c.type = 'button'; c.className = 'rounded-full border border-ink-200 bg-white px-2.5 py-1 text-[11px] text-ink-600 hover:border-brand-300 hover:text-brand-700 hover:bg-brand-50 transition';
      c.textContent = r[0]; c.onclick = function () { ask(r[1], r[0]); }; rf.appendChild(c);
    });
    // أحدث رد بس اللي يظهر تحته الاقتراحات
    thread.querySelectorAll('.cp-refine').forEach(function (x) { if (x !== rf) x.innerHTML = ''; });
    if (!lastField || !document.body.contains(lastField)) ins.remove();
    else ins.onclick = function () { lastField.value = text; lastField.dispatchEvent(new Event('input', { bubbles: true })); lastField.focus(); lastField.classList.add('ring-4', 'ring-brand-500/20'); setTimeout(function () { lastField.classList.remove('ring-4', 'ring-brand-500/20'); }, 1200); };
    scroll();
  }
  function welcome() {
    if (thread.children.length) return;
    var w = el('<div class="cp-welcome text-center pt-6"><div class="mx-auto w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center shadow-lg shadow-brand-600/25">' + (C.sparkBig || '') + '</div><p class="mt-3 font-medium text-ink-900"></p><p class="text-xs text-ink-500 mt-1"></p><div class="cp-samples mt-4 space-y-2 text-start"></div></div>');
    w.querySelector('p').textContent = C.hi; w.querySelectorAll('p')[1].textContent = C.hiSub;
    if (false) (C.samples || []).forEach(function (s) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm text-ink-700 hover:border-brand-300 hover:bg-brand-50 transition text-start'; b.textContent = s;
      b.onclick = function () { ask(s); }; w.querySelector('.cp-samples').appendChild(b);
    });
    thread.appendChild(w);
  }
  function clearWelcome() { var w = thread.querySelector('.cp-welcome'); if (w) w.remove(); }

  function load() {
    if (loaded) return; loaded = true;
    post({ task: 'chat_log' }).then(function (d) {
      (d.items || []).forEach(function (h) {
        if (h.role === 'user') bubbleUser(h.html, !!h.label);
        else { var box = bubbleAI(); fill(box, { html: h.html, text: (el('<div>' + h.html + '</div>').innerText), meta: h.meta }); }
      });
      welcome(); scroll();
    }).catch(welcome);
  }
  function setBusy(on) {
    busy = on;
    if (send) { send.innerHTML = SVG(on ? IC.stop : IC.send, 'w-4 h-4' + (on ? '' : ' rtl:-scale-x-100')); send.classList.toggle('bg-ink-900', on); send.classList.toggle('bg-brand-600', !on); send.title = on ? C.stop : ''; }
  }
  function ask(q, label) {
    q = (q || '').trim(); if (!q || busy) return;
    clearWelcome(); setBusy(true); stick = true;
    bubbleUser((label || q).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }).replace(/\n/g, '<br>'), !!label);
    lastQ = label ? lastQ : q;
    var box = bubbleAI(), withPage = document.getElementById('cp-page');
    box.dataset.q = q;
    var data = { task: 'chat', message: q, page_title: C.title };
    if (withPage && withPage.checked) data.page = pageText();
    streamInto(box, data).catch(function () { fill(box, { error: C.err }); })
      .finally(function () { setBusy(false); ctrl = null; });
  }
  function insight(key, extra) {
    var it = I[key]; if (!it || busy) return;
    clearWelcome();
    if (it.input && extra == null) {
      // أمر محتاج مدخلات: كارت صغير جوه المحادثة
      var card = el('<div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-3"><div class="text-sm font-medium text-ink-900 mb-2"></div><textarea rows="3" class="input !mt-0 text-sm"></textarea><div class="flex justify-end gap-2 mt-2"><button type="button" class="cp-x btn-ghost !py-1 !px-2.5 !text-xs">✕</button><button type="button" class="cp-run btn-primary !py-1 !px-3 !text-xs"></button></div></div>');
      var hd = card.querySelector('div'); hd.className += ' flex items-center gap-2 text-brand-700'; hd.innerHTML = it.icon; hd.appendChild(document.createTextNode(it.label)); var ta = card.querySelector('textarea'); ta.placeholder = it.input; card.querySelector('.cp-run').textContent = C.run;
      card.querySelector('.cp-x').onclick = function () { card.remove(); welcome(); };
      card.querySelector('.cp-run').onclick = function () { if (!ta.value.trim()) { ta.focus(); return; } card.remove(); insight(key, ta.value); };
      thread.appendChild(card); scroll(); ta.focus(); return;
    }
    busy = true;
    var lbl = document.createElement('span'); lbl.textContent = it.label;
    bubbleUser('<span class="inline-flex items-center gap-1.5 align-middle">' + it.icon + lbl.outerHTML + '</span>' + (extra ? '<div class="text-xs opacity-75 mt-1 line-clamp-2"></div>' : ''), true);
    if (extra) thread.lastChild.querySelector('.line-clamp-2').textContent = extra;
    var box = bubbleAI(), data = { task: 'insight', insight: key, text: extra || '' };
    if (it.page) data.page = pageText();
    (it.csv ? post(data).then(function (d) { fill(box, d); }) : streamInto(box, data)).catch(function () { fill(box, { error: C.err }); }).finally(function () { busy = false; });
  }

  function setOpen(on) {
    panel.classList.toggle('translate-x-full', !on); panel.classList.toggle('rtl:-translate-x-full', !on);
    panel.classList.toggle('opacity-0', !on); panel.classList.toggle('pointer-events-none', !on);
    if (main) main.classList.toggle('lg:pe-[29.5rem]', on && !wide);
    document.querySelectorAll('.cp-spark').forEach(function (b) { b.classList.toggle('ring-4', on); b.classList.toggle('ring-brand-500/25', on); });
    store.set('cp-open', on ? '1' : '');
    if (on) { load(); setTimeout(function () { if (input && innerWidth >= 1024) input.focus(); }, 300); }
  }
  function isOpen() { return !panel.classList.contains('pointer-events-none'); }
  function setWide(w) {
    wide = w; store.set('cp-wide', w ? '1' : '');
    panel.classList.toggle('lg:w-auto', w); panel.classList.toggle('lg:start-64', w); panel.classList.toggle('sm:w-[26rem]', !w);
    if (main) main.classList.toggle('lg:pe-[29.5rem]', isOpen() && !w);
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-cp-toggle]')) { setOpen(!isOpen()); return; }
    var ins = e.target.closest('[data-cp-insight]'); if (ins) { if (!isOpen()) setOpen(true); insight(ins.dataset.cpInsight); }
  });
  document.getElementById('cp-wide').onclick = function () { setWide(!wide); };
  document.getElementById('cp-clear').onclick = function () { if (!confirm(C.confirmClear)) return; post({ task: 'chat_clear' }); thread.innerHTML = ''; welcome(); };
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'j' || e.key === 'J')) { e.preventDefault(); setOpen(!isOpen()); }
    if (e.key === 'Escape' && isOpen() && !document.querySelector('dialog[open]')) setOpen(false);
  });
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (busy) { if (ctrl) ctrl.abort(); return; } // زرار الإرسال بيبقى إيقاف وهو بيكتب
      var q = input.value; if (slash.open && slash.run()) return; input.value = ''; input.style.height = ''; ask(q);
    });
    input.addEventListener('keydown', function (e) {
      if (slash.open && slash.key(e)) return;
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); }
      if (e.key === 'ArrowUp' && !input.value && lastQ) { e.preventDefault(); input.value = lastQ; }
    });
    input.addEventListener('input', function () { slash.update(); });
    input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 160) + 'px'; });
  }
  // قايمة "/" للأوامر
  var slash = (function () {
    var box = document.createElement('div'), items = [], idx = 0, api = { open: false };
    box.className = 'hidden absolute bottom-full mb-2 inset-x-0 max-h-72 overflow-y-auto rounded-2xl border border-ink-200 bg-white shadow-2xl p-1.5 z-20';
    if (form) { form.style.position = 'relative'; form.appendChild(box); }
    function all() {
      var out = Object.keys(I).filter(function (k) { return (C.page || []).indexOf(k) > -1; }).map(function (k) { return { label: I[k].label, icon: I[k].icon, run: function () { insight(k); } }; });
      (C.cmds || []).forEach(function (c) { out.push({ label: c[0], icon: SVG(c[2] || IC.send, 'w-4 h-4'), run: function () { if (c[1] === 'clear') document.getElementById('cp-clear').click(); else if (c[1] === 'page') { var x = document.getElementById('cp-page'); x.checked = !x.checked; } else if (c[1] === 'wide') setWide(!wide); else ask(c[1], c[0]); } }); });
      return out;
    }
    function render() {
      box.innerHTML = '';
      items.forEach(function (it, i) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'w-full flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-start ' + (i === idx ? 'bg-brand-50 text-brand-700' : 'text-ink-700 hover:bg-ink-100');
        b.innerHTML = it.icon; b.appendChild(document.createTextNode(it.label)); b.onmousedown = function (e) { e.preventDefault(); idx = i; api.run(); }; box.appendChild(b);
      });
      var on = box.children[idx]; if (on) on.scrollIntoView({ block: 'nearest' });
    }
    api.update = function () {
      var v = input.value;
      if (v.charAt(0) !== '/' || v.indexOf('\n') > -1) { box.classList.add('hidden'); api.open = false; return; }
      var q = v.slice(1).toLowerCase(); items = all().filter(function (it) { return !q || it.label.toLowerCase().indexOf(q) > -1; }); idx = 0;
      api.open = items.length > 0; box.classList.toggle('hidden', !api.open); render();
    };
    api.key = function (e) {
      if (e.key === 'ArrowDown') { idx = (idx + 1) % items.length; render(); e.preventDefault(); return true; }
      if (e.key === 'ArrowUp') { idx = (idx - 1 + items.length) % items.length; render(); e.preventDefault(); return true; }
      if (e.key === 'Escape') { input.value = ''; api.update(); e.preventDefault(); e.stopPropagation(); return true; }
      if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); api.run(); return true; }
      return false;
    };
    api.run = function () { var it = items[idx]; input.value = ''; input.style.height = ''; api.update(); if (it) it.run(); return !!it; };
    return api;
  })();

  // الإدخال بالصوت (لو المتصفح بيدعمه)
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition, mic = document.getElementById('cp-mic');
  if (mic && SR) {
    var rec = null;
    mic.classList.remove('hidden');
    mic.onclick = function () {
      if (rec) { rec.stop(); return; }
      rec = new SR(); rec.lang = document.documentElement.lang === 'ar' ? 'ar-EG' : 'en-US'; rec.interimResults = true; rec.continuous = false;
      var base = input.value ? input.value + ' ' : '';
      rec.onresult = function (e) { var t = ''; for (var i = 0; i < e.results.length; i++) t += e.results[i][0].transcript; input.value = base + t; input.dispatchEvent(new Event('input')); };
      rec.onend = function () { rec = null; mic.classList.remove('text-red-600', 'bg-red-50', 'animate-pulse'); input.focus(); };
      mic.classList.add('text-red-600', 'bg-red-50', 'animate-pulse'); rec.start();
    };
  }

  // سحب حافة اللوحة لتغيير عرضها
  var grip = document.getElementById('cp-grip'), cpW = parseInt(store.get('cp-w') || '0', 10) || 416;
  function applyW() {
    if (innerWidth < 1024 || wide) { panel.style.width = ''; if (main) main.style.paddingInlineEnd = ''; return; }
    panel.style.width = cpW + 'px';
    if (main) main.style.paddingInlineEnd = isOpen() ? (cpW + 56) + 'px' : '';
  }
  if (grip) grip.addEventListener('pointerdown', function (e) {
    e.preventDefault(); grip.setPointerCapture(e.pointerId); document.body.style.userSelect = 'none'; panel.style.transition = 'none';
    var rtl = document.documentElement.dir === 'rtl';
    function mv(ev) { var r = panel.getBoundingClientRect(); cpW = Math.max(340, Math.min(innerWidth - 420, rtl ? ev.clientX - r.left : r.right - ev.clientX)); applyW(); }
    function up() { grip.removeEventListener('pointermove', mv); document.body.style.userSelect = ''; panel.style.transition = ''; store.set('cp-w', cpW); }
    grip.addEventListener('pointermove', mv); grip.addEventListener('pointerup', up, { once: true });
  });
  window.addEventListener('resize', applyW);
  var _setOpen = setOpen; setOpen = function (on) { _setOpen(on); if (main) main.classList.remove('lg:pe-[29.5rem]'); applyW(); };
  var _setWide = setWide; setWide = function (w) { _setWide(w); if (main) main.classList.remove('lg:pe-[29.5rem]'); applyW(); };

  setWide(wide);
  if (store.get('cp-open') === '1' && innerWidth >= 1024) setOpen(true);
})();
