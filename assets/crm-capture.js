/* أدوات جمع العملاء: QR، نسخ، صفحات الهبوط، واتساب متتبع، الإحالة، كارت البزنس */
(function () {
  var C = window.CAP || {}, $ = function (s, r) { return (r || document).querySelector(s); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function post(d) { var fd = new FormData(); Object.keys(d).forEach(function (k) { fd.append(k, d[k]); }); fd.append('_csrf', C.csrf); return fetch(location.pathname + '?p=crm_capture', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': C.csrf } }).then(function (r) { return r.json(); }); }
  function note(m) { if (window.toast) return window.toast(m); alert(m); }
  function copy(t, b) { (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { if (b) { var o = b.textContent; b.textContent = C.copied; setTimeout(function () { b.textContent = o; }, 1200); } }).catch(function () { prompt('', t); }); }
  function linkRow(u) { return '<div class="flex flex-wrap items-center gap-2 rounded-xl border border-lime-200 bg-lime-50 p-2 text-xs"><code class="flex-1 min-w-0 truncate" dir="ltr">' + esc(u) + '</code><button type="button" class="btn-ghost !py-1 !text-xs" data-copy="' + esc(u) + '">⧉</button><button type="button" class="btn-ghost !py-1 !text-xs" data-qr="' + esc(u) + '" data-qrn="">▦ QR</button></div>'; }
  var dlg = $('#qr-dlg');
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-copy]'); if (c) return copy(c.dataset.copy, c);
    var q = e.target.closest('[data-qr]');
    if (q) {
      if (!window.qrcode) return note(C.err);
      var qr = window.qrcode(0, 'M'); qr.addData(q.dataset.qr); qr.make();
      var url = qr.createDataURL(10, 16); $('#qr-img').src = url; var im = new Image(); im.onload = function () { var cv = document.createElement('canvas'); cv.width = im.width; cv.height = im.height; cv.getContext('2d').drawImage(im, 0, 0); $('#qr-dl').href = cv.toDataURL('image/png'); }; im.src = url; $('#qr-dl').href = url; $('#qr-dl').download = (q.dataset.qrn || 'qr').replace(/[^\w؀-ۿ-]+/g, '_') + '.png';
      $('#qr-n').textContent = q.dataset.qrn || q.dataset.qr; dlg.showModal(); return;
    }
    var f = $('#lp-form');
    if (e.target.closest('[data-lp-new]')) { f.reset(); f.old.value = ''; f.classList.remove('hidden'); f.title.focus(); return; }
    if (e.target.closest('[data-lp-cancel]')) { f.classList.add('hidden'); return; }
    var ed = e.target.closest('[data-lp-edit]');
    if (ed) { var x = JSON.parse(ed.dataset.lpEdit); f.reset(); ['title', 'slug', 'sub', 'body', 'img', 'wa', 'cta', 'color'].forEach(function (k) { if (f[k]) f[k].value = x[k] || (k === 'color' ? '#4f46e5' : ''); }); f.on.checked = !!x.on; f.old.value = x.slug; f.classList.remove('hidden'); f.scrollIntoView({ block: 'center' }); }
  });
  var wg = $('#wa-go');
  if (wg) wg.onclick = function () {
    post({ action: 'wa_make', phone: $('#wa-ph').value, msg: $('#wa-msg').value, label: $('#wa-lb').value }).then(function (j) { if (!j.ok) return note(j.error || C.err); $('#wa-out').innerHTML = linkRow(j.short); }).catch(function () { note(C.err); });
  };
  var rg = $('#ref-go');
  if (rg) rg.onclick = function () { var v = $('#ref-l').value; if (!v) return; post({ action: 'ref_link', lead: v }).then(function (j) { if (!j.ok) return note(j.error || C.err); $('#ref-out').innerHTML = linkRow(j.url); }).catch(function () { note(C.err); }); };
  var cf = $('#card-f'), form = $('#card-form'), st = $('#card-st');
  if (cf) cf.onchange = function () {
    var file = cf.files[0]; if (!file || !window.extractText) return; st.textContent = C.reading;
    window.extractText(file).then(function (r) {
      var text = r && r.text ? r.text : ''; if (!text.trim()) { st.textContent = C.none; return; }
      return post({ action: 'card_parse', text: text }).then(function (j) {
        ['name', 'phone', 'email', 'company', 'title'].forEach(function (k) { form[k].value = j[k] || ''; });
        form.classList.remove('hidden'); st.textContent = C.review;
      });
    }).catch(function () { st.textContent = C.err; });
  };
  if (form) form.onsubmit = function (e) {
    e.preventDefault(); var d = { action: 'card_save' }; ['name', 'phone', 'email', 'company', 'title', 'event'].forEach(function (k) { d[k] = form[k].value; });
    post(d).then(function (j) { if (!j.ok) return note(j.error || C.err); st.innerHTML = esc(j.merged ? C.merged : C.saved) + ' <a class="link" href="' + C.leads + '#lead-' + j.id + '">' + esc(C.open) + '</a>'; form.reset(); form.classList.add('hidden'); cf.value = ''; }).catch(function () { note(C.err); });
  };
})();

/* صفحة الهبوط من خدمة في ملف الشركة */
(function () {
  var K = window.LPKB, sel = document.getElementById('lp-from'); if (!K || !sel) return;
  sel.addEventListener('change', function () {
    var s = K.svc[+sel.value]; if (!s) return; var f = document.getElementById('lp-form');
    var set = function (n, v) { if (f.elements[n] && v) f.elements[n].value = v; };
    set('title', s.name);
    set('sub', [s.price ? K.price.replace('%s', s.price) : '', K.tagline].filter(Boolean).join(' · ').slice(0, 240));
    set('body', [s.details, K.about, K.delivery, K.area ? K.area2.replace('%s', K.area) : ''].filter(Boolean).join('\n\n').slice(0, 3000));
    set('wa', K.wa); set('cta', K.cta);
    if (f.elements.slug && !f.elements.slug.value) f.elements.slug.value = ('svc-' + (+sel.value + 1) + '-' + Date.now().toString(36).slice(-3));
  });
})();
