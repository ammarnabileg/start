/* بروفايل واتساب: معاينة حية زي شاشة واتساب، عدّادات، نسبة اكتمال، وفحص صورة البروفايل. */
(function () {
  var X = window.WPX; if (!X) return;
  var S = X.s;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var form = $('#wp-form'); if (!form) return;
  var v = function (n) { var el = form.elements[n]; return el ? el.value.trim() : ''; };
  var photo = X.photo, phIn = $('#wp-ph');

  function setPhoto(u) {
    var box = $('#pv-photo'), pv = $('#wp-ph-prev');
    if (u) { box.innerHTML = '<img src="' + esc(u) + '" class="w-full h-full object-cover" alt="" referrerpolicy="no-referrer">'; pv.innerHTML = '<img src="' + esc(u) + '" class="w-full h-full object-cover" alt="" referrerpolicy="no-referrer">'; }
  }
  function paint() {
    $('#pv-about').textContent = v('about') || '—';
    var cat = v('vertical') || X.cur; $('#pv-cat').textContent = cat ? (X.verticals[cat] || cat) : '';
    var rows = [[uic('file', 'w-4 h-4 text-ink-400'), v('description')], [uic('pin', 'w-4 h-4 text-ink-400'), v('address')], [uic('mail', 'w-4 h-4 text-ink-400'), v('email')], [uic('link', 'w-4 h-4 text-ink-400'), v('web1').replace(/^https?:\/\//, '')], [uic('link', 'w-4 h-4 text-ink-400'), v('web2').replace(/^https?:\/\//, '')]].filter(function (r) { return r[1]; });
    $('#pv-info').innerHTML = rows.length ? rows.map(function (r) { return '<div class="flex gap-2"><span>' + r[0] + '</span><span class="flex-1 min-w-0 break-words text-ink-800 ' + (r[0] === '🌐' || r[0] === '✉️' ? 'text-[#027eb5]' : '') + '" dir="auto">' + esc(r[1]) + '</span></div>'; }).join('') : '<div class="text-ink-400 text-xs">—</div>';
    $$('.wp-c').forEach(function (c) { var n = (form.elements[c.dataset.for].value || '').length, m = +c.dataset.max; c.textContent = n + ' / ' + m; c.className = 'wp-c text-[11px] ' + (n > m * 0.9 ? 'text-amber-700' : 'text-ink-400'); });
    var L = [['c_photo', !!(photo || (phIn && phIn.value)), 20], ['c_about', !!v('about'), 15], ['c_desc', v('description').length >= 80, 20], ['c_addr', !!v('address'), 15], ['c_email', !!v('email'), 10], ['c_web', !!(v('web1') || v('web2')), 10], ['c_cat', !!(v('vertical') || X.cur), 10]];
    var got = 0; L.forEach(function (c) { if (c[1]) got += c[2]; });
    var col = got >= 85 ? '#16a34a' : got >= 60 ? '#d97706' : '#dc2626';
    $('#wp-score').innerHTML = '<div class="flex items-center gap-4"><div class="text-3xl font-bold" style="color:' + col + '">' + got + '%</div><div class="flex-1"><div class="font-bold text-ink-900">' + esc(S.score) + '</div><div class="text-xs text-ink-500">' + esc(S.hint) + '</div>'
      + '<div class="h-2 rounded-full bg-ink-100 mt-2 overflow-hidden"><div class="h-full rounded-full transition-all" style="width:' + got + '%;background:' + col + '"></div></div></div></div>'
      + '<div class="flex flex-wrap gap-1.5 mt-3">' + L.map(function (c) { return '<span class="rounded-full text-[11px] px-2.5 py-1 ' + (c[1] ? 'bg-lime-50 text-lime-700' : 'bg-ink-50 text-ink-600') + '">' + (c[1] ? '✓ ' : '+ ') + esc(S[c[0]]) + '</span>'; }).join('') + '</div>';
  }
  if (photo) setPhoto(photo);

  /* فحص الصورة قبل الإرسال */
  if (phIn) {
    var check = function () {
      var u = phIn.value.trim(), w = $('#wp-ph-warn'); w.style.display = 'none';
      if (!/^https:\/\//i.test(u)) { paint(); return; }
      var img = new Image();
      img.onload = function () {
        setPhoto(u); var msg = '';
        if (Math.abs(img.naturalWidth / img.naturalHeight - 1) > 0.05) msg = f(S.sq, img.naturalWidth, img.naturalHeight);
        else if (img.naturalWidth < 192) msg = f(S.small, img.naturalWidth, img.naturalHeight);
        if (msg) { w.textContent = msg; w.style.display = ''; }
        paint();
      };
      img.onerror = function () { w.textContent = S.bad; w.style.display = ''; };
      img.src = u;
    };
    phIn.addEventListener('change', check); phIn.addEventListener('input', check);
  }

  /* تعديلات مش محفوظة */
  var dirty = false, sending = false;
  form.addEventListener('input', function () { dirty = true; $('#wp-dirty').style.display = ''; paint(); });
  form.addEventListener('change', function () { paint(); });
  form.addEventListener('submit', function () { sending = true; });
  window.addEventListener('beforeunload', function (e) { if (dirty && !sending) { e.preventDefault(); e.returnValue = S.leave; return S.leave; } });
  paint();
})();
