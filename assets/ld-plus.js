/* درج العميل في صفحة العملاء: نفس تفاعل نافذة العملاء المستهدفين
   (نتائج سريعة، Enter يحفظ، فلتر السجل، نسخ، المرحلة بتتحفظ فورًا، احتفال، اختصارات) */
(function () {
  var X = window.__ld, L = window.LDP; if (!X || !L) return;
  var dr = document.getElementById('ld-drawer'), form = X.form, body = document.getElementById('ld-log-body'), acts = document.getElementById('ld-acts'), tfBox = document.getElementById('ld-tf');
  if (!dr || !form) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function toast(m, k) { if (window.fxToast) fxToast(m, k || 'success'); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-3 h-3') : ''; }
  var KC = { call: '#16a34a', wa: '#22c55e', email: '#2563eb', meet: '#d97706', note: '#64748b', stage: '#7c3aed' }, AIC = { call: 'phone', wa: 'chat', email: 'mail', meet: 'users', note: 'file', stage: 'route' };
  var open = function () { return !dr.classList.contains('hidden'); };

  // نتائج سريعة بتتكتب في خانة "حصل إيه؟"
  var qr = document.getElementById('ld-qr');
  if (qr) qr.addEventListener('click', function (e) { var b = e.target.closest('[data-qr]'); if (!b || body.disabled) return; var v = b.dataset.qr;
    body.value = body.value.trim() ? body.value.trim() + ' · ' + v : v; b.classList.add('on'); setTimeout(function () { b.classList.remove('on'); }, 600); body.focus(); });
  // Enter = احفظ كملاحظة (من غير ما الفورم كله يتبعت)
  body.addEventListener('keydown', function (e) { if (e.key !== 'Enter' || e.shiftKey || e.isComposing) return; e.preventDefault(); if (!body.value.trim()) return;
    var b = document.querySelector('#ld-log-kinds [data-k="note"]'); if (b) b.click(); });

  // فلتر السجل حسب النوع
  var tf = '';
  function paintTf() { var li = [].slice.call(acts.querySelectorAll('li[data-k]')), cnt = {};
    li.forEach(function (x) { cnt[x.dataset.k] = (cnt[x.dataset.k] || 0) + 1; }); var ks = Object.keys(cnt); if (tf && !cnt[tf]) tf = '';
    tfBox.innerHTML = ks.length > 1 ? ['<button type="button" class="pd-tfb' + (tf ? '' : ' on') + '" data-tf="">' + esc(L.all) + '</button>'].concat(ks.map(function (k) {
      return '<button type="button" class="pd-tfb' + (tf === k ? ' on' : '') + '" data-tf="' + k + '" style="--k:' + (KC[k] || '#64748b') + '">' + ic(AIC[k] || 'file') + cnt[k] + '</button>'; })).join('') : '';
    li.forEach(function (x) { x.style.display = !tf || x.dataset.k === tf ? '' : 'none'; }); }
  tfBox.addEventListener('click', function (e) { var b = e.target.closest('[data-tf]'); if (!b) return; tf = b.dataset.tf; paintTf(); });
  new MutationObserver(paintTf).observe(acts, { childList: true });

  // زرار نسخ للرقم والإيميل في أزرار التواصل
  var quick = document.getElementById('ld-quick');
  function addCopy() { var l = X.cur(); if (!l || quick.querySelector('[data-copy]')) return; var h = '';
    if (l.phone) h += '<button type="button" class="chip text-xs" data-copy="' + esc(l.phone) + '" title="' + esc(L.copy) + '">' + ic('copy') + '<span dir="ltr">' + esc(l.phone) + '</span></button>';
    if (l.email) h += '<button type="button" class="chip text-xs" data-copy="' + esc(l.email) + '" title="' + esc(L.copy) + '">' + ic('copy') + '<span dir="ltr">' + esc(l.email) + '</span></button>';
    if (h) quick.insertAdjacentHTML('beforeend', h); }
  new MutationObserver(addCopy).observe(quick, { childList: true }); addCopy(); paintTf(); // لو الدرج اتفتح قبل الملف ده (#lead-ID)
  quick.addEventListener('click', function (e) { var c = e.target.closest('[data-copy]'); if (!c) return; e.preventDefault(); e.stopPropagation();
    var ok = function () { toast(L.copied); }; if (navigator.clipboard) navigator.clipboard.writeText(c.dataset.copy).then(ok, ok); else ok(); }, true);

  // المرحلة بتتحفظ أول ما تختارها (الخسارة لسه محتاجة سبب فبتفضل بزرار حفظ)
  form.addEventListener('change', function (e) { var r = e.target; if (r.name !== 'stage') return; var l = X.cur(); if (!l || !l.edit || r.value === l.stage || r.value === 'lost') return; var v = r.value;
    var fd = new FormData(); fd.append('action', 'bulk'); fd.append('op', 'stage'); fd.append('stage', v); fd.append('ids[]', l.id);
    X.post(fd).then(function (j) { if (!j || !j.ok) { toast((j && j.error) || L.err, 'error'); return; } l.stage = v;
      var st = document.getElementById('ld-d-stage'); if (st) st.innerHTML = '<span class="text-[11px] px-2 py-0.5 rounded-md ' + (X.stageColor[v] || '') + '">' + esc(X.D.stages[v] || v) + '</span> ' + X.nextBadge(l);
      X.draw(); if (v === 'won') { confetti(); toast(L.won); } else toast(L.saved); }).catch(function () { toast(L.err, 'error'); }); });

  // احتفال
  var fx = document.createElement('div'); fx.className = 'pointer-events-none fixed inset-0 z-[80] overflow-hidden'; document.body.appendChild(fx);
  function confetti() { if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return; var cs = ['#22c55e', '#2563eb', '#f59e0b', '#ec4899', '#06b6d4', '#a855f7'];
    for (var i = 0; i < 80; i++) { var p = document.createElement('i'); p.className = 'pd-cf'; p.style.left = Math.random() * 100 + '%'; p.style.background = cs[i % cs.length];
      p.style.animationDelay = Math.random() * .4 + 's'; p.style.animationDuration = 1.4 + Math.random() * 1.2 + 's'; fx.appendChild(p); }
    setTimeout(function () { fx.innerHTML = ''; }, 3200); }

  // اختصارات جوه الدرج + لوحة مساعدة
  var hd = dr.querySelector('#ld-form > div'), kb = document.createElement('div');
  kb.className = 'pd-pop hidden !top-16 !bottom-auto !w-72'; kb.id = 'ld-keys';
  kb.innerHTML = '<b class="block text-sm text-ink-900 mb-2">' + esc(L.keys) + '</b><ul class="space-y-1.5 text-xs text-ink-700">' + L.list.map(function (k) { return '<li class="flex items-center gap-2"><kbd class="pd-kbd">' + esc(k[0]) + '</kbd><span>' + esc(k[1]) + '</span></li>'; }).join('') + '</ul>';
  form.appendChild(kb);
  var kbtn = document.createElement('button'); kbtn.type = 'button'; kbtn.className = 'btn-ghost !px-2 !py-1 hidden sm:inline-flex'; kbtn.title = L.keys; kbtn.innerHTML = '<kbd class="pd-kbd">?</kbd>';
  var closeB = hd && hd.querySelector('[data-close]'); if (closeB) closeB.parentNode.insertBefore(kbtn, closeB);
  kbtn.addEventListener('click', function () { kb.classList.toggle('hidden'); });
  document.addEventListener('keydown', function (e) { if (!open() || e.ctrlKey || e.metaKey || e.altKey) return; var t = (e.target.tagName || '').toLowerCase(); if (t === 'input' || t === 'textarea' || t === 'select') return;
    var k = e.key.toLowerCase(), l = X.cur(); if (!l) return; var go = function (fn) { e.preventDefault(); fn(); };
    var st = [].slice.call(form.querySelectorAll('[name=stage]'));
    if (/^[1-9]$/.test(k) && st[+k - 1] && l.edit) return go(function () { st[+k - 1].click(); });
    if (k === 'n') return go(function () { var d = dr.querySelector('.ld-sec[data-tab="ct"]'); if (d) d.open = true; body.focus(); });
    if (k === 'w') return go(function () { var a = quick.querySelector('a[href*="wa.me"]'); if (a) a.click(); });
    if (k === 'c') return go(function () { var a = quick.querySelector('a[href^="tel:"]'); if (a) a.click(); });
    if (k === 'e') return go(function () { var a = quick.querySelector('a[href^="mailto:"]'); if (a) a.click(); });
    if (k === '?' || (e.shiftKey && k === '/')) return go(function () { kb.classList.toggle('hidden'); });
    if (k === 'escape' && !kb.classList.contains('hidden')) { e.preventDefault(); e.stopImmediatePropagation(); kb.classList.add('hidden'); } }, true);
})();
