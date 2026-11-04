/* قاعدة المعرفة: تنقل بين الأقسام، حفظ تلقائي، رفع ملفات، وتجربة الرد */
(function () {
  var K = window.KB, root = document.getElementById('kb'); if (!root || !K) return;
  var csrf = function () { return (document.querySelector('meta[name=csrf-token]') || {}).content || ''; };
  function post(fd) { return fetch('index.php?p=knowledge', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) { return r.json(); }); }

  // التنقل
  function go(k) {
    root.querySelectorAll('[data-kb]').forEach(function (s) { s.classList.toggle('hidden', s.dataset.kb !== k); });
    root.querySelectorAll('.kb-tab').forEach(function (b) { var on = b.dataset.kbGo === k; b.classList.toggle('bg-brand-50', on); b.classList.toggle('text-brand-700', on); b.classList.toggle('font-medium', on); });
    history.replaceState(null, '', '#' + k);
  }
  document.addEventListener('click', function (e) { var b = e.target.closest('[data-kb-go]'); if (b) { go(b.dataset.kbGo); if (innerWidth < 1024) root.scrollIntoView({ behavior: 'smooth' }); } });
  function fromHash() { var start = location.hash.slice(1); if (!start || !root.querySelector('[data-kb="' + start + '"]')) start = 'files'; go(start); }
  fromHash(); window.addEventListener('hashchange', fromHash);

  function progress() {
    var dots = root.querySelectorAll('.kb-dot'), done = 0; dots.forEach(function (d) { if (d.classList.contains('bg-lime-500')) done++; });
    document.getElementById('kb-done').textContent = done;
    document.getElementById('kb-ring').setAttribute('stroke-dasharray', (97.4 * done / K.total).toFixed(1) + ' 97.4');
  }
  function dot(k, ok) { var d = root.querySelector('[data-dot="' + k + '"]'); if (d) { d.classList.toggle('bg-lime-500', ok); d.classList.toggle('bg-ink-300', !ok); progress(); } }

  // الحفظ التلقائي (بعد ما يبطّل كتابة بثانية)
  function collect(form) {
    var fd = new FormData(form);
    form.querySelectorAll('.kb-row').forEach(function (row, i) { row.querySelectorAll('[data-col]').forEach(function (el) { fd.append('d[rows][' + i + '][' + el.dataset.col + ']', el.value); }); });
    fd.append('action', 'kb_save'); fd.append('section', form.dataset.section); return fd;
  }
  var timers = {};
  function save(form) {
    clearTimeout(timers[form.dataset.section]);
    timers[form.dataset.section] = setTimeout(function () {
      var badge = form.querySelector('.kb-saved');
      post(collect(form)).then(function (d) {
        if (d.error) { window.aiToast ? aiToast(d.error) : alert(d.error); return; }
        badge.classList.remove('opacity-0'); setTimeout(function () { badge.classList.add('opacity-0'); }, 1400);
        dot(form.dataset.section, !!d.filled);
      }).catch(function () { window.aiToast ? aiToast(K.err) : alert(K.err); });
    }, 700);
  }
  root.querySelectorAll('.kb-form').forEach(function (f) {
    f.addEventListener('input', function () { save(f); }); f.addEventListener('change', function () { save(f); });
    f.addEventListener('submit', function (e) { e.preventDefault(); });
  });
  // صفوف الخدمات والأسئلة
  root.addEventListener('click', function (e) {
    var add = e.target.closest('.kb-add');
    if (add) {
      var form = add.closest('form'), rows = form.querySelector('.kb-rows'), last = rows.querySelector('.kb-row:last-child');
      var n = last.cloneNode(true); n.querySelectorAll('[data-col]').forEach(function (x) { x.value = ''; }); rows.appendChild(n); n.querySelector('[data-col]').focus(); return;
    }
    var del = e.target.closest('.kb-del');
    if (del) { var f = del.closest('form'), row = del.closest('.kb-row'); if (f.querySelectorAll('.kb-row').length > 1) row.remove(); else row.querySelectorAll('[data-col]').forEach(function (x) { x.value = ''; }); save(f); }
  });
  /* ---------- الملء/التحديث بالذكاء الاصطناعي: اقتراح الأول، ومفيش حفظ غير لما المستخدم يعتمد ---------- */
  function esc(t) { return String(t == null ? '' : t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function optLabel(form, name, val) { var el = form.querySelector('[name="d[' + name + ']' + (Array.isArray(val) ? '[]' : '') + '"][value="' + val + '"]') || form.querySelector('[name="d[' + name + '][]"][value="' + val + '"]'); if (!el) { var o = form.querySelector('select[name="d[' + name + ']"] option[value="' + val + '"]'); return o ? o.textContent : val; } var l = el.closest('label'); return l ? l.textContent.trim() : val; }
  function current(form) {
    var type = form.dataset.type, o = {};
    if (type === 'rows') { o.rows = [].map.call(form.querySelectorAll('.kb-row'), function (r) { var x = {}; r.querySelectorAll('[data-col]').forEach(function (el) { x[el.dataset.col] = el.value; }); return x; }).filter(function (x) { return Object.values(x).join('').trim(); }); return o; }
    form.querySelectorAll('[name^="d["]').forEach(function (el) {
      var m = el.name.match(/^d\[(\w+)\](\[\])?$/); if (!m) return;
      if (m[2]) { o[m[1]] = o[m[1]] || []; if (el.checked) o[m[1]].push(el.value); } else o[m[1]] = el.value;
    });
    return o;
  }
  function applyData(form, d) {
    var type = form.dataset.type;
    if (type === 'rows') {
      var box = form.querySelector('.kb-rows'), tpl = box.querySelector('.kb-row');
      box.querySelectorAll('.kb-row').forEach(function (r, i) { if (i) r.remove(); });
      tpl.querySelectorAll('[data-col]').forEach(function (x) { x.value = ''; });
      (d.rows || []).forEach(function (r, i) { var row = i ? tpl.cloneNode(true) : tpl; row.querySelectorAll('[data-col]').forEach(function (el) { el.value = r[el.dataset.col] || ''; }); if (i) box.appendChild(row); });
    } else {
      Object.keys(d).forEach(function (k) {
        var v = d[k];
        if (Array.isArray(v)) form.querySelectorAll('[name="d[' + k + '][]"]').forEach(function (c) { c.checked = v.indexOf(c.value) > -1; });
        else { var el = form.querySelector('[name="d[' + k + ']"]'); if (el && !(k === 'email' && !v)) el.value = v; }
      });
    }
    form.querySelectorAll('textarea[data-col]').forEach(function (t) { t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px'; });
    form.querySelectorAll('input,textarea,select').forEach(function (el) { if (el.closest('.kb-sug')) return; el.classList.add('ring-4', 'ring-brand-500/20'); setTimeout(function () { el.classList.remove('ring-4', 'ring-brand-500/20'); }, 1800); });
    save(form);
  }
  // كارت الاقتراح: بيعرض الجديد والمتعدّل، والمستخدم يختار
  function suggest(form, d, meta) {
    var old = form.querySelector('.kb-sug'); if (old) old.remove();
    var type = form.dataset.type, cur = current(form), body = '', changes = 0;
    var tag = function (kind) { return '<span class="ms-1.5 rounded-md px-1.5 py-0.5 text-[10px] font-medium ' + (kind === 'new' ? 'bg-lime-100 text-lime-700' : kind === 'chg' ? 'bg-amber-100 text-amber-700' : 'bg-ink-100 text-ink-500') + '">' + (kind === 'new' ? K.newTag : kind === 'chg' ? K.changed : K.same) + '</span>'; };
    if (type === 'rows') {
      var key = form.dataset.section === 'faq' ? 'q' : (form.dataset.section === 'pos_offers' ? 'offer' : 'name');
      body = (d.rows || []).map(function (r, i) {
        var match = (cur.rows || []).filter(function (c) { return (c[key] || '').trim() === (r[key] || '').trim(); })[0];
        var kind = !match ? 'new' : (JSON.stringify(match) === JSON.stringify(Object.assign({}, match, r)) ? 'same' : 'chg'); if (kind !== 'same') changes++;
        var main = form.dataset.section === 'pos_offers' ? '<b class="text-ink-900">' + esc(r.offer) + '</b>' + tag(kind) + ['audience', 'problem', 'frame', 'pod', 'rtb'].map(function (c) { return r[c] ? '<div class="text-ink-600 mt-0.5"><span class="text-ink-400">' + esc((K.cols || {})[c] || c) + ':</span> ' + esc(r[c]) + '</div>' : ''; }).join('') : form.dataset.section === 'faq' ? '<b class="text-ink-900">' + esc(r.q) + '</b>' + tag(kind) + '<div class="text-ink-600 mt-0.5">' + esc(r.a) + '</div>'
          : '<b class="text-ink-900">' + esc(r.name) + '</b>' + (r.price ? ' <span class="text-brand-700">— ' + esc(r.price) + '</span>' : '') + tag(kind) + (r.details ? '<div class="text-ink-600 mt-0.5">' + esc(r.details) + '</div>' : '');
        return '<label class="flex items-start gap-2.5 rounded-xl border border-ink-200 bg-white px-3 py-2 cursor-pointer hover:border-brand-300"><input type="checkbox" class="kb-pick mt-1" data-i="' + i + '" checked><span class="flex-1 text-sm">' + main + '</span></label>';
      }).join('');
      // اللي موجود ومش في الاقتراح بيفضل (مابنمسحش حاجة المستخدم كاتبها)
      (cur.rows || []).forEach(function (c) { if (!(d.rows || []).some(function (r) { return (r[key] || '').trim() === (c[key] || '').trim(); })) d.rows.push(c); });
    } else {
      body = Object.keys(d).map(function (k) {
        var v = d[k], c = cur[k]; if (k === 'email') return '';
        var same = JSON.stringify(Array.isArray(v) ? v.slice().sort() : String(v || '').trim()) === JSON.stringify(Array.isArray(c) ? (c || []).slice().sort() : String(c || '').trim());
        if (!same) changes++;
        var shown = Array.isArray(v) ? v.map(function (x) { return '<span class="inline-block rounded-lg bg-brand-50 text-brand-700 px-2 py-0.5 text-xs m-0.5">' + esc(optLabel(form, k, x)) + '</span>'; }).join('') : (form.querySelector('select[name="d[' + k + ']"]') ? esc(optLabel(form, k, v)) : '<div class="whitespace-pre-wrap">' + esc(v) + '</div>');
        return '<div class="rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm">' + (Object.keys(d).length > 1 ? '<div class="text-[11px] text-ink-500 mb-1">' + esc((K.labels || {})[k] || k) + tag(same ? 'same' : (c && String(c).length ? 'chg' : 'new')) + '</div>' : '') + shown + '</div>';
      }).join('');
    }
    var card = document.createElement('div');
    card.className = 'kb-sug rounded-2xl border-2 border-brand-300 bg-brand-50/60 p-4 space-y-3';
    card.innerHTML = '<div class="flex items-start gap-2"><span class="w-7 h-7 rounded-lg bg-gradient-to-br from-brand-600 to-cyan-500 text-[#fff] flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg></span><div class="flex-1"><b class="text-sm text-ink-900">' + K.sugTitle + '</b><p class="text-xs text-ink-500 mt-0.5">' + (changes ? K.sugHint : K.noChange) + '</p></div></div>'
      + '<div class="space-y-2 max-h-[26rem] overflow-y-auto">' + body + '</div>'
      + '<div class="flex flex-wrap items-center gap-2"><button type="button" class="kb-apply btn-primary !py-1.5 !text-xs">' + K.apply + '</button><button type="button" class="kb-dismiss btn-ghost !py-1.5 !text-xs">' + K.dismiss + '</button><span class="ms-auto text-[10px] text-ink-400" dir="ltr">' + esc(meta || '') + '</span></div>';
    form.querySelector('.kb-saved').closest('.flex.items-start').after(card);
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    card.querySelector('.kb-dismiss').onclick = function () { card.remove(); pendingCount(); };
    card.querySelector('.kb-apply').onclick = function () {
      var final = d;
      if (type === 'rows') {
        var off = [].map.call(card.querySelectorAll('.kb-pick:not(:checked)'), function (c) { return +c.dataset.i; });
        final = { rows: d.rows.filter(function (r, i) { return off.indexOf(i) < 0; }) };
      }
      form.__undo = cur; form.querySelector('.kb-undo').classList.remove('hidden');
      applyData(form, final); card.remove(); pendingCount();
      var lbl = form.querySelector('.kb-fill span'); if (lbl) lbl.textContent = lbl.dataset.lUpdate;
    };
    pendingCount();
  }
  // عدد الاقتراحات المستنية جنب كل قسم في القايمة
  function pendingCount() {
    root.querySelectorAll('.kb-tab').forEach(function (t) {
      var f = root.querySelector('[data-kb="' + t.dataset.kbGo + '"] .kb-sug'), b = t.querySelector('.kb-pend');
      if (f && !b) { b = document.createElement('span'); b.className = 'kb-pend w-2 h-2 rounded-full bg-brand-500 animate-pulse'; t.appendChild(b); }
      if (!f && b) b.remove();
    });
  }
  // ملء قسم؛ ينفع يتوقف في أي لحظة (form.__ac)
  var STOP = '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>';
  function fill(form, mode) {
    var btn = form.querySelector('.kb-fill'), lbl = btn.querySelector('span'), txt = lbl.textContent, ico = btn.innerHTML;
    var ac = window.AbortController ? new AbortController() : null; form.__ac = ac;
    btn.classList.add('kb-running', '!border-red-200', '!bg-red-50', '!text-red-700'); btn.innerHTML = STOP + '<span></span>'; btn.querySelector('span').textContent = K.filling + ' · ' + K.stop; btn.title = K.stop;
    var fd = new FormData(); fd.append('action', 'kb_fill'); fd.append('section', form.dataset.section); fd.append('mode', mode || 'update');
    return fetch('index.php?p=knowledge', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf() }, signal: ac ? ac.signal : undefined }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.error) { window.aiToast ? aiToast(d.error) : alert(d.error); return false; }
      suggest(form, d.data || {}, d.meta); return true;
    }).catch(function (e) { if (e && e.name === 'AbortError') { window.aiToast && aiToast(K.stopped); return 'stopped'; } window.aiToast ? aiToast(K.err) : alert(K.err); return false; })
      .finally(function () { form.__ac = null; btn.classList.remove('kb-running', '!border-red-200', '!bg-red-50', '!text-red-700'); btn.innerHTML = ico; btn.title = ''; btn.querySelector('span').textContent = txt; });
  }
  root.addEventListener('click', function (e) {
    var b = e.target.closest('.kb-fill'); if (b) { var fm = b.closest('form'); if (fm.__ac) { fm.__ac.abort(); return; } fill(fm, 'update'); return; }
    var u = e.target.closest('.kb-undo');
    if (u) { var f = u.closest('form'); if (f.__undo) { applyData(f, f.__undo); f.__undo = null; u.classList.add('hidden'); } }
  });
  // اختيار الأقسام اللي الذكاء الاصطناعي يملاها: واحد أو كذا أو الكل
  function isFull(sec) { var d = root.querySelector('[data-dot="' + sec + '"]'); return !!(d && d.classList.contains('bg-lime-500')); }
  function secLabel(sec) { var d = root.querySelector('[data-dot="' + sec + '"]'), a = d && d.closest('a,button'); return a ? a.textContent.trim() : sec; }
  var batch = null;
  function runFill(forms, keep, btn) {
    var lbl = btn.querySelector('span'), txt = lbl.textContent; batch = { stop: false };
    var me = batch; btn.classList.add('!border-red-200', '!bg-red-50', '!text-red-700');
    function end(stopped) { batch = null; btn.classList.remove('!border-red-200', '!bg-red-50', '!text-red-700'); lbl.textContent = txt; if (stopped) window.aiToast && aiToast(K.stoppedAll); }
    (function next(i) {
      if (me.stop) return end(true);
      if (i >= forms.length) { end(false); go(forms[0].dataset.section); return; }
      lbl.textContent = K.stop + ' · ' + (i + 1) + ' / ' + forms.length; go(forms[i].dataset.section);
      fill(forms[i], isFull(forms[i].dataset.section) && keep ? 'update' : 'fill').then(function (r) { if (r === 'stopped') me.stop = true; next(i + 1); });
    })(0);
  }
  // إيقاف الدفعة كلها: يقف الطلب الحالي ومايكملش الباقي (الاقتراحات اللي خلصت بتفضل)
  function stopBatch() { if (!batch) return false; batch.stop = true; root.querySelectorAll('.kb-form').forEach(function (f) { if (f.__ac) f.__ac.abort(); }); return true; }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && (batch || root.querySelector('.kb-running'))) { if (!stopBatch()) root.querySelectorAll('.kb-form').forEach(function (f) { if (f.__ac) f.__ac.abort(); }); } });
  var pickBtn = document.getElementById('kb-ai-pick');
  if (pickBtn) pickBtn.addEventListener('click', function () {
    if (stopBatch()) return;
    var forms = [].slice.call(root.querySelectorAll('.kb-form'));
    var d = document.createElement('dialog'); d.className = 'w-[min(520px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40';
    var chips = [['all', K.pAll], ['empty', K.pEmpty], ['full', K.pFull], ['none', K.pNone]].map(function (c) { return '<button type="button" data-q="' + c[0] + '" class="rounded-full border border-ink-200 px-3 py-1 text-xs hover:bg-brand-50 hover:border-brand-300">' + c[1] + '</button>'; }).join('');
    d.innerHTML = '<div class="flex items-center px-5 h-14 border-b border-ink-200"><b class="flex-1 text-ink-900"></b><button type="button" class="x p-2 rounded-lg text-ink-400 hover:bg-ink-100">\u2715</button></div>'
      + '<div class="p-5 space-y-4"><p class="hint text-xs text-ink-500 leading-relaxed"></p><div class="flex flex-wrap gap-2">' + chips + '</div>'
      + '<div class="lst grid sm:grid-cols-2 gap-1.5 max-h-72 overflow-y-auto"></div>'
      + '<div class="mode rounded-xl bg-ink-50 border border-ink-200 p-3 text-xs space-y-1.5"><div class="font-medium text-ink-700"></div><label class="flex items-center gap-2"><input type="radio" name="kbm" value="keep" checked> <span class="k"></span></label><label class="flex items-center gap-2"><input type="radio" name="kbm" value="new"> <span class="n"></span></label></div>'
      + '<div class="flex justify-end gap-2"><button type="button" class="cn btn-ghost !py-1.5 !text-xs"></button><button type="button" class="ok btn-primary !py-1.5 !text-xs"></button></div></div>';
    d.querySelector('b').textContent = K.pickTitle; d.querySelector('.hint').textContent = K.pickHint;
    d.querySelector('.mode div').textContent = K.mLbl; d.querySelector('.mode .k').textContent = K.mKeep; d.querySelector('.mode .n').textContent = K.mNew;
    d.querySelector('.cn').textContent = K.cancel;
    var lst = d.querySelector('.lst'), ok = d.querySelector('.ok');
    forms.forEach(function (f) {
      var sec = f.dataset.section, full = isFull(sec), l = document.createElement('label');
      l.className = 'flex items-center gap-2 rounded-xl border border-ink-200 px-3 py-2 text-sm cursor-pointer hover:bg-ink-50 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50';
      l.innerHTML = '<input type="checkbox" class="accent-brand-600"><span class="flex-1 truncate"></span><span class="text-[10px] rounded-full px-1.5 py-0.5 ' + (full ? 'bg-lime-100 text-lime-700' : 'bg-ink-100 text-ink-500') + '"></span>';
      l.querySelector('input').checked = !full; l.querySelector('input').dataset.sec = sec; l.querySelector('input').dataset.full = full ? '1' : '';
      l.querySelector('span').textContent = secLabel(sec); l.querySelectorAll('span')[1].textContent = full ? K.stFull : K.stEmpty;
      lst.appendChild(l);
    });
    function sync() {
      var on = [].filter.call(lst.querySelectorAll('input'), function (i) { return i.checked; });
      ok.textContent = K.go + (on.length ? ' (' + on.length + ')' : ''); ok.disabled = !on.length;
      d.querySelector('.mode').classList.toggle('hidden', !on.some(function (i) { return i.dataset.full; }));
    }
    lst.addEventListener('change', sync);
    d.querySelectorAll('[data-q]').forEach(function (c) { c.onclick = function () { var q = c.dataset.q; lst.querySelectorAll('input').forEach(function (i) { i.checked = q === 'all' || (q === 'empty' && !i.dataset.full) || (q === 'full' && !!i.dataset.full); }); sync(); }; });
    ok.onclick = function () {
      var secs = [].filter.call(lst.querySelectorAll('input'), function (i) { return i.checked; }).map(function (i) { return i.dataset.sec; });
      var keep = d.querySelector('[name=kbm]:checked').value === 'keep'; d.close();
      runFill(forms.filter(function (f) { return secs.indexOf(f.dataset.section) > -1; }), keep, pickBtn);
    };
    d.querySelector('.x').onclick = d.querySelector('.cn').onclick = function () { d.close(); };
    d.addEventListener('close', function () { d.remove(); });
    document.body.appendChild(d); sync(); d.showModal();
  });

  // بيان التموضع: جملة واحدة بتتجمع من الخطوات الأربعة وانت بتكتب
  function posVal(sec) { var t = root.querySelector('.kb-form[data-section="' + sec + '"] textarea[name="d[text]"]'); return t ? t.value.trim() : ''; }
  function strip(l) { return l.replace(/^[^:\uff1a]{1,25}[:\uff1a]\s*/, '').replace(/[.\u3002\s]+$/, ''); }
  function first(sec) { var l = posVal(sec).split('\n').filter(function (x) { return x.trim() && !/^(\u0645\u062b\u0627\u0644|example)/i.test(x); }); return l.length ? strip(l[0]) : ''; }
  function posStmt() {
    var box = root.querySelectorAll('.kb-pos-stmt'); if (!box.length) return;
    var fv = function (sec, k) { var el = root.querySelector('.kb-form[data-section="' + sec + '"] [name="d[' + k + ']"]'); var l = el ? el.value.split('\n').filter(function (x) { return x.trim(); }) : []; return l.length ? strip(l[0]) : ''; };
    var vals = [fv('audience', 'who'), fv('audience', 'problem'), fv('frame_ref', 'category'), first('pod'), first('rtb')], e = K.posEmpty;
    var tgt = [['audience', 'who'], ['audience', 'problem'], ['frame_ref', 'category'], ['pod', 'text'], ['rtb', 'text']];
    var html = K.posTpl.replace(/%(\d)/g, function (m, i) { var v = vals[i - 1], t = tgt[i - 1]; return '<button type="button" data-pgo="' + t[0] + '" data-pf="' + t[1] + '" class="rounded-md px-0.5 transition hover:bg-brand-100 hover:ring-2 hover:ring-brand-200 ' + (v ? 'font-semibold text-ink-900' : 'text-ink-400 border-b border-dashed border-ink-300') + '">' + esc(v || e) + '</button>'; });
    box.forEach(function (b) { if (b.__h !== html) { b.__h = html; b.innerHTML = html; } });
  }
  root.addEventListener('input', function (e) { if (e.target.closest('.kb-form[data-section="audience"],.kb-form[data-section="frame_ref"],.kb-form[data-section="pod"],.kb-form[data-section="rtb"]')) posStmt(); });
  new MutationObserver(posStmt).observe(root, { subtree: true, attributes: true, attributeFilter: ['class'] }); posStmt();

  // تكبير خانة التفاصيل مع الكتابة
  root.addEventListener('input', function (e) { if (e.target.matches('textarea[data-col]')) { e.target.style.height = 'auto'; e.target.style.height = e.target.scrollHeight + 'px'; } });

  // ألوان الهوية: دواير من الأكواد المكتوبة
  var colors = root.querySelector('[name="d[colors]"]'), sw = document.getElementById('kb-swatches');
  function swatches() { if (!sw) return; sw.innerHTML = ''; (colors.value.match(/#[0-9a-f]{3,8}\b/gi) || []).forEach(function (c) { var s = document.createElement('span'); s.className = 'w-8 h-8 rounded-full border border-ink-200 shadow-sm'; s.style.background = c; s.title = c; sw.appendChild(s); }); }
  if (colors) { colors.addEventListener('input', swatches); swatches(); }

  // الملفات
  var input = document.getElementById('kb-file'), drop = document.getElementById('kb-drop'), list = document.getElementById('kb-files');
  // تصغير صورة لحد ما تبقى أقل من الحد (أقصى ضلع 2400 ثم أقل)
  function shrink(file, max) {
    return createImageBitmap(file).then(function (img) {
      var side = 2400;
      return (function tryOnce(q) {
        var k = Math.min(1, side / Math.max(img.width, img.height)), c = document.createElement('canvas');
        c.width = Math.round(img.width * k); c.height = Math.round(img.height * k); c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        return new Promise(function (ok) { c.toBlob(ok, 'image/jpeg', q); }).then(function (b) {
          if (b && b.size <= max) return b;
          if (side <= 800) return null;
          side = Math.round(side * 0.7); return tryOnce(0.8);
        });
      })(0.85);
    }).catch(function () { return null; });
  }
  // رفع الملف الأصلي على أجزاء صغيرة أقل من حد السيرفر
  function chunkUp(id, file) {
    var size = Math.max(262144, Math.min(Math.floor((K.maxUp || 2097152) * 0.9), 4194304)), n = Math.max(1, Math.ceil(file.size / size));
    return (function next(i) {
      if (i >= n) return Promise.resolve(true);
      var f = new FormData(); f.append('action', 'kb_chunk'); f.append('id', id); f.append('i', i); f.append('name', file.name); f.append('last', i === n - 1 ? '1' : '0');
      f.append('part', file.slice(i * size, (i + 1) * size), 'part');
      return post(f).then(function (d) { if (!d.ok) throw new Error(d.error || 'chunk'); return next(i + 1); });
    })(0);
  }
  // إعادة القراءة: من النسخة اللي على السيرفر، ولو مفيش (ملف قديم) نطلب الملف من الجهاز
  function reread(row) {
    var g = new FormData(); g.append('action', 'kb_file_raw'); g.append('id', row.dataset.id);
    return fetch('index.php?p=knowledge', { method: 'POST', body: g, headers: { 'X-CSRF-Token': csrf() } }).then(function (r) {
      if (!r.ok) return false;
      var ext = r.headers.get('X-Ext') || 'bin';
      return r.blob().then(function (b) {
        var base = (row.querySelector('.kb-name') || {}).textContent || 'file';
        var f = new File([b], base.replace(/\.[^.]*$/, '') + '.' + ext, { type: ext === 'pdf' ? 'application/pdf' : (/^(png|jpe?g|webp|bmp)$/.test(ext) ? 'image/' + ext.replace('jpg', 'jpeg') : '') });
        upload([f], row.dataset.id, { keepName: true, fromOrig: true }); return true;
      });
    }).catch(function () { return false; });
  }
  function upload(files, replace, opt) {
    opt = opt || {};
    var okAny = false, bad = false;
    // ملف ورا التاني (الـ OCR تقيل)، ونحدّث الصفحة مرة واحدة في الآخر
    [].slice.call(files).reduce(function (chain, file) { return chain.then(function () { return one(file); }); }, Promise.resolve())
      .then(function () { if (okAny && !bad) { location.hash = 'files'; location.reload(); } });
    function one(file) {
      var ph = document.createElement('div'); ph.className = 'rounded-xl border border-brand-200 bg-brand-50/50 p-3 text-sm flex items-center gap-3';
      ph.innerHTML = '<svg class="w-4 h-4 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg><span class="flex-1 truncate"></span><span class="text-xs text-ink-500"></span>';
      ph.querySelector('span').textContent = file.name; var st = ph.querySelectorAll('span')[1]; st.textContent = K.uploading; list.prepend(ph);
      var fd = new FormData(); fd.append('action', 'kb_upload'); fd.append('file', file); if (replace) fd.append('replace', replace);
      // PDF والصور: المتصفح بيطلّع النص الأول (PDF.js / OCR) عشان مانستخدمش الذكاء الاصطناعي
      var local = (window.extractText && /\.(pdf|png|jpe?g|webp|bmp)$/i.test(file.name))
        ? Promise.race([window.extractText(file, function (p, kind) { st.textContent = (kind === 'ocr' ? K.ocr : K.reading) + ' ' + Math.round(p * 100) + '%'; }),
            new Promise(function (r) { setTimeout(function () { r(null); }, 180000); })]).catch(function () { return null; })
        : Promise.resolve(null);
      var max = K.maxUp || 15728640;
      return local.then(function (r) {
        st.textContent = K.uploading;
        // النص اتقرا في المتصفح: نبعت النص بس (أخف بكتير، ومفيش مشكلة حجم على السيرفر)
        if (r && r.text && r.text.replace(/\s/g, '').length >= 15) {
          var t = new FormData(); t.append('action', 'kb_upload'); t.append('client_name', file.name); t.append('client_size', file.size);
          if (replace) t.append('replace', replace); if (opt.keepName) t.append('keep_name', '1'); t.append('client_text', r.text); t.append('client_method', r.method);
          // الأصل بيترفع بعدها على أجزاء (لإعادة القراءة بعدين)، إلا لو هو أصلًا على السيرفر
          return post(t).then(function (d) { if (d.ok && !opt.fromOrig && file.size <= 15728640) { st.textContent = K.saving; return chunkUp(d.id, file).then(function () { return d; }, function () { return d; }); } return d; });
        }
        if (opt.fromOrig) { var rr = new FormData(); rr.append('action', 'kb_reread'); rr.append('id', replace); return post(rr);
        }
        if (file.size <= max) return post(fd);
        // صورة كبيرة: نصغّرها الأول
        if (/^image\//.test(file.type)) { st.textContent = K.shrink; return shrink(file, max).then(function (b) {
          if (!b) return { error: file.name + ': ' + K.tooBig.replace('%s', +(max / 1048576).toFixed(1)) };
          var s2 = new FormData(); s2.append('action', 'kb_upload'); if (replace) s2.append('replace', replace); s2.append('file', b, file.name.replace(/\.\w+$/, '') + '.jpg'); return post(s2);
        }); }
        return { error: file.name + ': ' + K.tooBig.replace('%s', +(max / 1048576).toFixed(1)) };
      }).then(function (d) {
        if (d.error) { bad = true; ph.className = 'rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700'; ph.textContent = d.error; return; }
        okAny = true; ph.className = 'rounded-xl border border-lime-200 bg-lime-50 p-3 text-sm flex items-center gap-3'; st.textContent = '✓ ' + d.chars;
      }).catch(function () { bad = true; ph.className = 'rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700'; ph.textContent = file.name + ': ' + K.err; });
    }
  }
  if (input) input.addEventListener('change', function () { upload(input.files); input.value = ''; });
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('border-brand-500', 'bg-brand-50'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('border-brand-500', 'bg-brand-50'); }); });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) upload(e.dataTransfer.files); });
  }
  if (list) {
    list.addEventListener('click', function (e) {
      var row = e.target.closest('.kb-file'); if (!row) return;
      if (e.target.closest('.kb-ren')) {
        var nm = row.querySelector('.kb-name'); if (!nm || row.querySelector('.kb-name-in')) return;
        var old = nm.textContent, inp = document.createElement('input'), done = false;
        inp.className = 'kb-name-in input !mt-0 !py-1 text-sm w-full'; inp.value = old; inp.dir = 'auto';
        nm.replaceWith(inp); e.target.closest('.kb-ren').classList.add('hidden'); inp.focus(); inp.select();
        var finish = function (save) {
          if (done) return; done = true; var v = inp.value.trim();
          var back = function (txt) { nm.textContent = txt; inp.replaceWith(nm); row.querySelector('.kb-ren').classList.remove('hidden'); };
          if (!save || !v || v === old) return back(old);
          var f = new FormData(); f.append('action', 'kb_file_rename'); f.append('id', row.dataset.id); f.append('name', v);
          post(f).then(function (d) { back(d.ok ? d.name : old); if (d.error) alert(d.error); }).catch(function () { back(old); });
        };
        inp.addEventListener('keydown', function (k) { if (k.key === 'Enter') { k.preventDefault(); finish(true); } else if (k.key === 'Escape') finish(false); });
        inp.addEventListener('blur', function () { finish(true); });
        return;
      }
      if (e.target.closest('.kb-reread')) {
        var btn = e.target.closest('.kb-reread'); btn.disabled = true;
        reread(row).then(function (ok) {
          btn.disabled = false; if (ok) return;
          var pick = document.createElement('input'); pick.type = 'file'; pick.accept = input ? input.accept : '';
          pick.onchange = function () { if (pick.files[0]) upload([pick.files[0]], row.dataset.id); };
          pick.click();
        }); return;
      }
      if (e.target.closest('.kb-view')) {
        var pv = row.querySelector('.kb-prev'); pv.classList.toggle('hidden');
        if (pv.dataset.full || pv.classList.contains('hidden')) return;
        pv.dataset.full = '1';
        // النص الكامل، وتقدر تعدّله (تمسح سطور الـ OCR اللي ملهاش معنى مثلًا)
        var g = new FormData(); g.append('action', 'kb_file_get'); g.append('id', row.dataset.id);
        post(g).then(function (d) {
          if (d.error) return;
          pv.innerHTML = '<p class="text-xs text-ink-500 mb-2"></p><textarea class="input !mt-0 text-xs font-mono w-full" rows="16" dir="auto"></textarea><div class="flex items-center gap-3 mt-2"><button type="button" class="kb-save-txt btn-primary !py-1.5 !text-xs"></button><span class="kb-cnt text-xs text-ink-500"></span></div>';
          pv.querySelector('p').textContent = K.editHint; var ta = pv.querySelector('textarea'), cnt = pv.querySelector('.kb-cnt'), btn = pv.querySelector('button');
          ta.value = d.content; btn.textContent = K.saveText;
          var upd = function () { cnt.textContent = ta.value.length.toLocaleString() + ' ' + K.chars; }; upd(); ta.addEventListener('input', upd);
          btn.onclick = function () {
            var f = new FormData(); f.append('action', 'kb_file_save'); f.append('id', row.dataset.id); f.append('content', ta.value);
            btn.disabled = true; post(f).then(function (r) { btn.disabled = false; cnt.textContent = (r.ok ? '✓ ' + K.saved + ' · ' : '') + ta.value.length.toLocaleString() + ' ' + K.chars; });
          };
        });
        return;
      }
      if (e.target.closest('.kb-rm')) { if (!confirm(K.del)) return; var fd = new FormData(); fd.append('action', 'kb_file_delete'); fd.append('id', row.dataset.id); post(fd).then(function () { row.remove(); if (!list.querySelector('.kb-file')) dot('files', false); }); }
    });
    list.addEventListener('change', function (e) {
      var c = e.target.closest('.kb-on'); if (!c) return; var row = c.closest('.kb-file');
      var fd = new FormData(); fd.append('action', 'kb_file_toggle'); fd.append('id', row.dataset.id); fd.append('on', c.checked ? '1' : '0');
      post(fd).then(function () { row.classList.toggle('opacity-60', !c.checked); });
    });
  }

  // جرّب الرد
  var ch = 'message', msg = document.getElementById('kb-msg'), out = document.getElementById('kb-out'), run = document.getElementById('kb-run');
  document.querySelectorAll('#kb-ch [data-ch]').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); ch = a.dataset.ch; document.querySelectorAll('#kb-ch a').forEach(function (x) { x.classList.toggle('on', x === a); }); }); });
  document.querySelectorAll('#kb-samples button').forEach(function (b) { b.addEventListener('click', function () { msg.value = b.textContent; msg.focus(); }); });
  if (run) run.addEventListener('click', function () {
    if (!msg.value.trim()) { msg.focus(); return; }
    run.disabled = true; out.innerHTML = '<div class="rounded-2xl border border-ink-200 bg-ink-50 p-4 text-sm text-ink-500 animate-pulse">…</div>';
    var fd = new FormData(); fd.append('action', 'kb_test'); fd.append('message', msg.value); fd.append('channel', ch);
    post(fd).then(function (d) {
      if (d.error) { out.innerHTML = '<p class="text-sm text-red-600"></p>'; out.firstChild.textContent = d.error; return; }
      var hand = d.action === 'handoff';
      out.innerHTML = '<div class="rounded-2xl border p-4 ' + (hand ? 'border-amber-200 bg-amber-50' : 'border-lime-200 bg-lime-50') + '">'
        + '<div class="flex flex-wrap items-center gap-2 text-sm font-semibold ' + (hand ? 'text-amber-800' : 'text-lime-800') + '"><span class="kb-act"></span><span class="ms-auto text-xs font-normal text-ink-500 kb-conf"></span></div>'
        + '<p class="kb-reason text-xs text-ink-600 mt-1"></p>'
        + '<div class="mt-3 rounded-xl bg-white border border-ink-200 p-3 text-sm whitespace-pre-wrap kb-reply" dir="auto"></div>'
        + '<p class="text-[10px] text-ink-400 mt-2 kb-meta" dir="ltr"></p></div>';
      out.querySelector('.kb-act').textContent = hand ? K.handoff : K.reply;
      out.querySelector('.kb-conf').textContent = K.conf + ': ' + Math.round((d.confidence || 0) * 100) + '%';
      out.querySelector('.kb-reason').textContent = d.reason ? K.reason + ': ' + d.reason : '';
      out.querySelector('.kb-reply').textContent = d.reply || '—';
      out.querySelector('.kb-meta').textContent = (d.model || '') + (d.cost != null ? ' · $' + (+d.cost).toFixed(5) : '');
    }).catch(function () { out.textContent = K.err; }).finally(function () { run.disabled = false; });
  });
})();
