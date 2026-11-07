/* عميل من بطاقة عمل: سحب/تصوير/لصق الصورة، قراءة النص في المتصفح، الصورة جنب الخانات،
   سطور النص كشرائح: اضغط سطر وبعدين خانة (أو اسحبه عليها)، كشف التكرار لحظيًا، ووضع "صوّر اللي بعدها" للمعارض */
(function () {
  var C = window.PCARD, d = document.getElementById('pr-card'); if (!C || !d) return; var T = C.T, B = document.getElementById('pc-body');
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-4 h-4') : ''; }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function post(o) { var fd = new FormData(); fd.append('_csrf', C.csrf); Object.keys(o).forEach(function (k) { fd.append(k, o[k]); });
    return fetch(C.api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': C.csrf } }).then(function (r) { return r.text(); }).then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); }); }
  function norm(p) { return String(p || '').replace(/\D/g, '').slice(-9); }
  var F = ['name', 'phone', 'email', 'company', 'title', 'site'], ev = '', keepEv = false, n = 0, img = '', pick = null;
  try { ev = localStorage.getItem('pc-ev') || ''; keepEv = !!ev; } catch (e) {}
  function drop() { B.innerHTML = '<label class="pc-drop" tabindex="0"><input type="file" accept="image/*" capture="environment" hidden><span class="pc-drop-ic">' + ic('image', 'w-9 h-9') + '</span><b>' + esc(T.dropT) + '</b><small>' + esc(T.dropD) + '</small></label>'; }
  function form(j) { var lines = (j.lines || []).filter(Boolean);
    B.innerHTML = '<div class="pc-grid"><div class="pc-side"><div class="pc-img"><img src="' + img + '" alt=""><button type="button" class="pc-re" data-re>' + ic('refresh') + ' ' + esc(T.retake) + '</button></div>' +
      (lines.length ? '<p class="pc-hint">' + esc(T.lines) + '</p><div class="pc-lines">' + lines.map(function (l) { return '<button type="button" draggable="true" data-ln>' + esc(l) + '</button>'; }).join('') + '</div>' : '') + '</div>' +
      '<form class="pc-f" data-no-ajax>' + F.map(function (k) { return '<label class="pr-fl' + (j[k] ? ' pc-auto' : '') + '" data-fk="' + k + '"><input name="' + k + '" value="' + esc(j[k] || '') + '" placeholder=" " dir="' + (k === 'phone' || k === 'email' || k === 'site' ? 'ltr' : 'auto') + '"><span>' + esc(T[k]) + '</span>' +
        (k === 'phone' ? '<i class="pc-acts"></i>' : '') + '</label>'; }).join('') +
      '<label class="pr-fl" data-fk="event"><input name="event" value="' + esc(keepEv ? ev : '') + '" placeholder=" " dir="auto"><span>' + esc(T.event) + '</span></label><label class="pc-keep"><input type="checkbox" name="keep"' + (keepEv ? ' checked' : '') + '> ' + esc(T.keep) + '</label>' +
      '<p class="pc-dup hidden"></p><div class="pc-btns"><button type="submit" class="btn-primary" data-go="open">' + esc(T.save) + '</button><button type="submit" class="btn-ghost" data-go="next">' + ic('image') + ' ' + esc(T.saveNext) + '</button></div></form></div>';
    check(); }
  function check() { var f = B.querySelector('.pc-f'); if (!f) return; var ph = norm(f.phone.value), em = f.email.value.trim().toLowerCase(), hit = null;
    if (window.PRList && (ph.length >= 8 || em.indexOf('@') > 0)) PRList.rows().some(function (r) { var x = {}; try { x = JSON.parse(r.dataset.j); } catch (e) {} if ((ph.length >= 8 && norm(x.phone) === ph) || (em && (x.email || '').toLowerCase() === em)) { hit = x; return true; } });
    var p = B.querySelector('.pc-dup'); p.classList.toggle('hidden', !hit); if (hit) p.textContent = T.dup.replace('%s', hit.name);
    var a = B.querySelector('.pc-acts'), w = f.phone.value.replace(/\D/g, ''); a.innerHTML = w.length >= 8 ? '<a href="https://wa.me/' + w + '" target="_blank" rel="noopener" title="' + esc(T.wa) + '">' + ic('chat') + '</a><a href="tel:' + esc(f.phone.value) + '" title="' + esc(T.call) + '">' + ic('phone') + '</a>' : ''; }
  function read(file) { if (!file || !/^image\//.test(file.type)) return; var r = new FileReader(); r.onload = function () { img = r.result; };  r.readAsDataURL(file);
    B.innerHTML = '<div class="pc-scan"><div class="pc-bar"><i></i></div><b>' + esc(T.reading) + '</b></div>';
    if (!window.extractText) { toast(T.err, 'error'); return drop(); }
    extractText(file, function (p) { var i = B.querySelector('.pc-bar i'); if (i && p != null) i.style.width = Math.round((p > 1 ? p : p * 100)) + '%'; }).then(function (r) { var t = r && r.text ? r.text : '';
      if (!t.trim()) { drop(); return toast(T.none, 'error'); }
      return post({ action: 'card_parse', text: t }).then(function (j) { if (!j.ok) throw 0; form(j); }); }).catch(function () { drop(); toast(T.err, 'error'); }); }
  function open() { var m = document.getElementById('pr-add-menu'); if (m && m.hidePopover) try { m.hidePopover(); } catch (e) {} drop(); d.showModal(); }
  var mn = document.getElementById('pr-add-menu'), mb = document.querySelector('[popovertarget="pr-add-menu"]');
  if (mn && mb) mn.addEventListener('toggle', function (e) { if (e.newState !== 'open') return; var r = mb.getBoundingClientRect(), w = mn.offsetWidth;
    mn.style.top = (r.bottom + 6) + 'px'; mn.style.left = Math.max(8, Math.min(innerWidth - w - 8, (document.dir === 'rtl' ? r.right - w : r.left))) + 'px'; });
  // اختيار الـ Pipelines (بعض/كل)
  var pm = document.getElementById('pr-plm'), pb = document.querySelector('[popovertarget="pr-plm"]');
  if (pm) { var ks = [].slice.call(pm.querySelectorAll('[data-plk]')), all = pm.querySelector('[data-plall]'), hid = pm.querySelector('[name=pl]');
    function sync() { var on = ks.filter(function (k) { return k.checked; }); all.checked = on.length === ks.length; all.indeterminate = on.length > 0 && on.length < ks.length; hid.value = all.checked ? '' : on.map(function (k) { return k.value; }).join(','); pm.querySelector('.btn-primary').disabled = !on.length; }
    all.addEventListener('change', function () { ks.forEach(function (k) { k.checked = all.checked; }); sync(); }); ks.forEach(function (k) { k.addEventListener('change', sync); }); sync();
    pm.addEventListener('toggle', function (e) { if (e.newState !== 'open') return; var r = pb.getBoundingClientRect(); pm.style.top = (r.bottom + 6) + 'px'; pm.style.left = Math.max(8, Math.min(innerWidth - pm.offsetWidth - 8, document.dir === 'rtl' ? r.right - pm.offsetWidth : r.left)) + 'px'; }); }
  var cb = document.getElementById('pr-card-btn'); if (cb) cb.addEventListener('click', open);
  ['pr-add-btn', 'pr-import-btn'].forEach(function (id) { var ab = document.getElementById(id); if (ab) ab.addEventListener('click', function () { var m = document.getElementById('pr-add-menu'); if (m && m.hidePopover) try { m.hidePopover(); } catch (e) {} }); });
  d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('[data-close]')) return d.close();
    if (e.target.closest('[data-re]')) return drop();
    var l = e.target.closest('[data-ln]'); if (l) { B.querySelectorAll('[data-ln]').forEach(function (x) { x.classList.toggle('on', x === l && !l.classList.contains('on')); }); pick = l.classList.contains('on') ? l : null; B.classList.toggle('pc-picking', !!pick); return; }
    var fk = e.target.closest('[data-fk]'); if (fk && pick) { e.preventDefault(); put(fk, pick.textContent); } });
  function put(fk, v) { var i = fk.querySelector('input'); i.value = v; fk.classList.add('pc-flash'); setTimeout(function () { fk.classList.remove('pc-flash'); }, 600);
    if (pick) { pick.classList.remove('on'); pick.classList.add('used'); pick = null; } B.classList.remove('pc-picking'); check(); }
  d.addEventListener('change', function (e) { if (e.target.type === 'file') read(e.target.files[0]); });
  d.addEventListener('input', function (e) { if (e.target.closest('.pc-f')) { var fk = e.target.closest('[data-fk]'); if (fk) fk.classList.remove('pc-auto'); check(); } });
  d.addEventListener('dragstart', function (e) { var l = e.target.closest && e.target.closest('[data-ln]'); if (l) e.dataTransfer.setData('text/plain', l.textContent), pick = l; });
  d.addEventListener('dragover', function (e) { if (e.target.closest('[data-fk],.pc-drop')) { e.preventDefault(); var z = e.target.closest('[data-fk],.pc-drop'); z.classList.add('over'); } });
  d.addEventListener('dragleave', function (e) { var z = e.target.closest && e.target.closest('[data-fk],.pc-drop'); if (z) z.classList.remove('over'); });
  d.addEventListener('drop', function (e) { var z = e.target.closest('[data-fk],.pc-drop'); if (!z) return; e.preventDefault(); z.classList.remove('over');
    if (z.classList.contains('pc-drop')) return read(e.dataTransfer.files[0]); put(z, e.dataTransfer.getData('text/plain')); });
  document.addEventListener('paste', function (e) { if (!d.open || !B.querySelector('.pc-drop')) return; var it = [].filter.call(e.clipboardData.items || [], function (x) { return /^image\//.test(x.type); })[0]; if (it) read(it.getAsFile()); });
  d.addEventListener('submit', function (e) { var f = e.target.closest('.pc-f'); if (!f) return; e.preventDefault(); var go = (e.submitter && e.submitter.dataset.go) || 'open';
    if (!f.phone.value.trim() && !f.email.value.trim()) { f.phone.focus(); return toast(T.need, 'error'); }
    keepEv = f.keep.checked; ev = f.event.value; try { keepEv ? localStorage.setItem('pc-ev', ev) : localStorage.removeItem('pc-ev'); } catch (x) {}
    var o = { action: 'card_save', event: ev, pl: (window.PR && PR.pl) || '' }; F.forEach(function (k) { o[k] = f[k].value; }); var bs = f.querySelectorAll('button'); bs.forEach(function (b) { b.disabled = true; });
    post(o).then(function (j) { bs.forEach(function (b) { b.disabled = false; }); if (!j.ok) return toast(j.error || T.err, 'error'); toast(j.merged ? T.merged : T.saved); n++;
      document.getElementById('pc-n').textContent = T.count.replace('%s', n);
      if (go === 'next') { drop(); var inp = B.querySelector('input[type=file]'); if (inp) inp.click(); } else location.href = C.self + '&lead=' + j.id; })
      .catch(function () { bs.forEach(function (b) { b.disabled = false; }); toast(T.err, 'error'); }); });
})();
