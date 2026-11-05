/* فورم بيانات الهوية: نسبة الاكتمال، فحص الملفات، اختيار شركة/شخص، ومنع الإرسال مرتين. */
(function () {
  var X = window.KYX; if (!X) return;
  var S = X.s;
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
  function f(str) { var a = [].slice.call(arguments, 1), i = 0; return String(str).replace(/%s/g, function () { return a[i++]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var form = $('#kyc'); if (!form) return;
  var cards = $$('.kyc-f', form), ok = $('#kyc-ok'), go = $('#kyc-go');
  $$('.kyc-n', form).forEach(function (b) { b.dataset.n = b.textContent; });

  function aud() { var r = $('input[name=aud]:checked', form); return r ? r.value : 'business'; }
  function reuse() { var r = $('input[name=reuse]:checked', form); return r && r.value; }
  function done(c) {
    var ins = $$('.kyc-in', c);
    if (!ins.length) return true;
    return ins.every(function (i) { return i.type === 'file' ? i.files && i.files.length && !i.dataset.bad : i.value.trim() !== ''; });
  }
  function paint() {
    var ru = reuse(), a = aud(), need = [], n = 0, k = 0;
    $('#kyc-fields').style.display = ru ? 'none' : '';
    cards.forEach(function (c) {
      var show = !(c.dataset.aud === 'business' && a === 'person');
      c.style.display = show ? '' : 'none';
      $$('input,textarea', c).forEach(function (i) { i.disabled = !show || !!ru; });
      if (!show || ru) return;
      n++;
      var d = done(c); if (d) k++; else need.push($('.font-medium', c).textContent);
      var b = $('.kyc-n', c); b.className = 'kyc-n w-7 h-7 shrink-0 rounded-full text-xs font-bold flex items-center justify-center ' + (d ? 'bg-lime-500 text-white' : 'bg-ink-100 text-ink-600');
      b.textContent = d ? '✓' : b.dataset.n;
    });
    var pct = ru ? 100 : n ? Math.round(k / n * 100) : 100;
    $('#kyc-bar').style.width = pct + '%'; $('#kyc-pct').textContent = pct + '%';
    $('#kyc-prog').textContent = ru ? '' : f(S.step, k, n);
    $('#kyc-miss').innerHTML = need.length ? '<li class="text-ink-500">' + esc(S.left) + '</li>' + need.slice(0, 6).map(function (t) { return '<li class="truncate" dir="auto">• ' + esc(t) + '</li>'; }).join('') : '';
    go.disabled = (!X.fix && !(ok && ok.checked)) || pct < 100;
  }

  /* الملفات */
  $$('.kyc-drop', form).forEach(function (lab) {
    var inp = $('input[type=file]', lab), fn = $('.kyc-fn', lab), fs = $('.kyc-fs', lab), base = fs.textContent;
    var show = function () {
      var file = inp.files && inp.files[0]; delete inp.dataset.bad;
      lab.classList.remove('border-red-300', 'border-lime-400', 'bg-lime-50/40');
      if (!file) { fn.textContent = S.pick; fs.textContent = base; return paint(); }
      var bad = file.size > 10 * 1024 * 1024 ? S.big : (!/^(application\/pdf|image\/(jpeg|png|webp))$/.test(file.type) ? S.type : '');
      fn.textContent = file.name; fs.textContent = bad || (Math.round(file.size / 1024) + ' KB');
      if (bad) { inp.dataset.bad = 1; lab.classList.add('border-red-300'); fs.className = 'kyc-fs block text-xs text-red-600'; }
      else { lab.classList.add('border-lime-400', 'bg-lime-50/40'); fs.className = 'kyc-fs block text-xs text-lime-700'; }
      paint();
    };
    inp.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(function (ev) { lab.addEventListener(ev, function (e) { e.preventDefault(); lab.classList.add('border-brand-500'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { lab.addEventListener(ev, function (e) { e.preventDefault(); lab.classList.remove('border-brand-500'); }); });
    lab.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) { inp.files = e.dataTransfer.files; show(); } });
  });

  form.addEventListener('input', paint);
  form.addEventListener('change', paint);
  form.addEventListener('submit', function (e) {
    paint();
    if (go.disabled || !confirm(X.fix ? S.confirmFix : S.confirm)) { e.preventDefault(); return; }
    setTimeout(function () { go.disabled = true; go.textContent = S.sending; }, 0);
  });
  paint();
})();
