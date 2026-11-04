/* لوحة ✨: محادثة على مستوى المنصة + أوامر سريعة للصفحة الحالية */
(function () {
  var C = window.CP, P = window.AI_PRESETS || {}, I = P.insights || {};
  var panel = document.getElementById('cp'), thread = document.getElementById('cp-thread'), main = document.getElementById('main');
  if (!panel || !C) return;
  var form = document.getElementById('cp-form'), input = document.getElementById('cp-input'), send = document.getElementById('cp-send');
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };
  var loaded = false, busy = false, wide = store.get('cp-wide') === '1', lastField = null;

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
  function scroll() { thread.scrollTop = thread.scrollHeight; }
  // الرد كلمة كلمة: بنقرا الـ stream ونبعت كل جزء لـ onDelta
  function postStream(data, onDelta) {
    data.stream = 1;
    var fd = new FormData(); for (var k in data) fd.append(k, data[k]);
    return fetch('index.php?p=ai_task', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) {
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
    var text = '', raf = 0, started = false;
    box.innerHTML = '<div class="cp-md leading-relaxed space-y-1 break-words"></div>';
    var md = box.firstChild;
    return postStream(data, function (d) {
      text += d; started = true;
      if (!raf) raf = requestAnimationFrame(function () { raf = 0; md.innerHTML = liveMd(text) + '<span class="cp-caret inline-block w-1.5 h-4 align-middle bg-brand-500 animate-pulse ms-0.5"></span>'; scroll(); });
    }).then(function (d) { if (raf) cancelAnimationFrame(raf); fill(box, d); return d; });
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
    box.innerHTML = '<div class="cp-md leading-relaxed space-y-2 break-words"></div><div class="flex flex-wrap items-center gap-1.5 mt-2.5 pt-2 border-t border-ink-200/70"><button type="button" class="cp-copy text-[11px] rounded-md px-2 py-1 text-ink-500 hover:bg-ink-200/60"></button><button type="button" class="cp-ins text-[11px] rounded-md px-2 py-1 text-ink-500 hover:bg-ink-200/60"></button><span class="ms-auto text-[10px] text-ink-400" dir="ltr"></span></div>';
    var md = box.querySelector('.cp-md');
    if (d.csv != null) {
      var pre = document.createElement('pre'); pre.className = 'text-xs whitespace-pre-wrap max-h-60 overflow-auto bg-white rounded-lg border border-ink-200 p-2'; pre.dir = 'auto'; pre.textContent = d.csv; md.appendChild(pre);
      var a = document.createElement('a'); a.className = 'btn-primary !py-1 !px-2.5 !text-xs'; a.textContent = C.csv + ' (' + (d.rows || 0) + ')';
      a.href = URL.createObjectURL(new Blob(['﻿' + d.csv], { type: 'text/csv;charset=utf-8' })); a.download = 'ai-posts.csv'; md.appendChild(a);
    } else md.innerHTML = d.html || '';
    box.querySelector('span[dir]').textContent = d.meta || '';
    var cp = box.querySelector('.cp-copy'), ins = box.querySelector('.cp-ins');
    cp.textContent = '⧉ ' + C.copy; ins.textContent = '↵ ' + C.insert;
    cp.onclick = function () { navigator.clipboard && navigator.clipboard.writeText(text); cp.textContent = '✓'; setTimeout(function () { cp.textContent = '⧉ ' + C.copy; }, 1200); };
    if (!lastField || !document.body.contains(lastField)) ins.remove();
    else ins.onclick = function () { lastField.value = text; lastField.dispatchEvent(new Event('input', { bubbles: true })); lastField.focus(); lastField.classList.add('ring-4', 'ring-brand-500/20'); setTimeout(function () { lastField.classList.remove('ring-4', 'ring-brand-500/20'); }, 1200); };
    scroll();
  }
  function welcome() {
    if (thread.children.length) return;
    var w = el('<div class="cp-welcome text-center pt-6"><div class="mx-auto w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center shadow-lg shadow-brand-600/25">' + (C.sparkBig || '') + '</div><p class="mt-3 font-medium text-ink-900"></p><p class="text-xs text-ink-500 mt-1"></p><div class="cp-samples mt-4 space-y-2 text-start"></div></div>');
    w.querySelector('p').textContent = C.hi; w.querySelectorAll('p')[1].textContent = C.hiSub;
    if (C.chat) (C.samples || []).forEach(function (s) {
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
  function ask(q) {
    q = (q || '').trim(); if (!q || busy) return;
    clearWelcome(); busy = true; if (send) send.disabled = true;
    bubbleUser(q.replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }).replace(/\n/g, '<br>'));
    var box = bubbleAI(), withPage = document.getElementById('cp-page');
    var data = { task: 'chat', message: q, page_title: C.title };
    if (withPage && withPage.checked) data.page = pageText();
    streamInto(box, data).catch(function () { fill(box, { error: C.err }); })
      .finally(function () { busy = false; if (send) send.disabled = false; });
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
    form.addEventListener('submit', function (e) { e.preventDefault(); var q = input.value; input.value = ''; input.style.height = ''; ask(q); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); } });
    input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 160) + 'px'; });
  }
  setWide(wide);
  if (store.get('cp-open') === '1' && innerWidth >= 1024) setOpen(true);
})();
