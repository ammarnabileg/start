// العملاء المحتملين: حالة كل عميل وقيمة الصفقة (حفظ تلقائي)، تواصل سريع، بحث وفلترة، فلترة بالحملة
(function () {
  var X = window.LDX; if (!X || document.body.dataset.ldUx) return; document.body.dataset.ldUx = '1';
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var rows = $$('.ld-row'), stage = '', camp = '', q = $('#ld-q');
  var toast = function (m, k) { (window.aiToast || function () {})(m, k); };
  var tone = { new: 'bg-brand-50 text-brand-700', contacted: 'bg-cyan-50 text-cyan-800', qualified: 'bg-amber-50 text-amber-800', converted: 'bg-lime-100 text-lime-800', lost: 'bg-ink-100 text-ink-500' };

  // ---- وقت نسبي + "جديد النهارده"
  rows.forEach(function (r) {
    var t = Date.parse(r.dataset.t); if (!t) return; var m = Math.max(1, Math.round((Date.now() - t) / 6e4)), s = m < 60 ? X.ago.m.replace('%s', m) : m < 1440 ? X.ago.h.replace('%s', Math.round(m / 60)) : X.ago.d.replace('%s', Math.round(m / 1440));
    var head = $('.text-xs.text-ink-500.flex', r) || r; head.insertAdjacentHTML('beforeend', '<span class="text-ink-400" title="' + esc(new Date(t).toLocaleString()) + '">' + esc(s) + '</span>');
    if (m < 1440) $('.font-semibold', r).insertAdjacentHTML('beforeend', ' <span class="text-[11px] rounded bg-lime-100 text-lime-800 px-1.5 py-0.5 align-middle">' + esc(X.fresh) + '</span>');
  });

  // ---- تواصل سريع
  rows.forEach(function (r) {
    var raw = r.dataset.phoneRaw || '', num = raw && window.phoneCheck ? window.phoneCheck(raw) : null, acts = $('.ld-acts', r), mail = ($('[dir=ltr]:not(.ld-ph)', r) || {}).textContent || '';
    var e164 = num || (/^\+?\d{8,15}$/.test(raw.replace(/[\s-]/g, '')) ? raw.replace(/[\s-]/g, '') : '');
    var btn = function (href, label, icon, extra) { return '<a href="' + esc(href) + '" ' + (extra || '') + ' class="tb h-9 px-2.5 text-xs gap-1" title="' + esc(label) + '" aria-label="' + esc(label) + '">' + icon + '<span class="hidden xl:inline">' + esc(label) + '</span></a>'; };
    var h = '';
    var land = false; try { var pp = window.libphonenumber && libphonenumber.parsePhoneNumberFromString(e164); land = !!pp && ['FIXED_LINE', 'TOLL_FREE'].indexOf(pp.getType()) >= 0; } catch (er) {}
    if (e164) {
      if (!land) h += btn('https://wa.me/' + e164.replace(/\D/g, ''), X.wa, '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .3-3.4-.7-2.9-1.2-4.7-4.1-4.8-4.3-.1-.2-1.2-1.6-1.2-3s.7-2.1 1-2.4c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/></svg>', 'target="_blank" rel="noopener"');
      h += btn('tel:' + e164, X.call, '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2"/></svg>');
      h += '<button type="button" class="ld-copy tb h-9 px-2.5 text-xs" data-v="' + esc(e164) + '" title="' + esc(X.copy) + '" aria-label="' + esc(X.copy) + '">⧉</button>';
      var ph = $('.ld-ph', r); if (ph && num) ph.textContent = num;
    }
    if (/@/.test(mail)) h += btn('mailto:' + mail.trim(), X.mail, '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>');
    acts.innerHTML = h;
  });
  document.addEventListener('click', function (e) {
    var c = e.target.closest('.ld-copy'); if (!c) return;
    (navigator.clipboard ? navigator.clipboard.writeText(c.dataset.v) : Promise.reject()).then(function () { toast(X.copied, 'ok'); }).catch(function () {});
  });

  // ---- حالة العميل + قيمة الصفقة + ملاحظة: حفظ تلقائي
  function paintStage(r) { var s = $('.ld-stage', r); s.className = 'ld-stage input !mt-0 !h-9 !py-0 !w-auto text-sm font-medium border-0 ' + (tone[s.value] || ''); r.dataset.stage = s.value; $('.ld-val', r).classList.toggle('hidden', s.value !== 'converted'); }
  var timers = {};
  function save(r) {
    clearTimeout(timers[r.dataset.id]);
    timers[r.dataset.id] = setTimeout(function () {
      var fd = new FormData(); fd.append('_csrf', X.csrf); fd.append('action', 'lead_save'); fd.append('lead_id', r.dataset.id); fd.append('campaign_id', r.dataset.camp);
      fd.append('stage', $('.ld-stage', r).value); fd.append('value', $('.ld-val', r).value); fd.append('note', $('.ld-note', r).value);
      fetch(X.url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (x) { return x.json(); }).then(function (j) { toast(j.ok ? X.saved : (j.error || X.err), j.ok ? 'ok' : 'err'); drawChips(); }).catch(function () { toast(X.err, 'err'); });
    }, 500);
  }
  rows.forEach(function (r) {
    paintStage(r);
    $('.ld-stage', r).addEventListener('change', function () { paintStage(r); save(r); if (this.value === 'converted') $('.ld-val', r).focus(); });
    $('.ld-val', r).addEventListener('input', function () { save(r); });
    $('.ld-note', r).addEventListener('input', function () { save(r); });
  });

  // ---- فلترة: بحث + حالة + حملة
  var chips = $('#ld-chips');
  function apply() {
    var v = q ? q.value.trim().toLowerCase() : '', n = 0;
    rows.forEach(function (r) { var ok = (!stage || r.dataset.stage === stage) && (!camp || r.dataset.camp === camp) && (!v || r.dataset.q.indexOf(v) >= 0 || (r.dataset.phoneRaw || '').replace(/\D/g, '').indexOf(v.replace(/\D/g, '') || '§') >= 0); r.style.display = ok ? '' : 'none'; if (ok) n++; });
    var c = $('#ld-count'); if (c) c.textContent = n;
    var e = $('#ld-empty'); if (!e && rows.length) { e = document.createElement('p'); e.id = 'ld-empty'; e.className = 'p-6 text-sm text-ink-500 text-center'; e.textContent = X.empty; rows[rows.length - 1].parentNode.appendChild(e); } if (e) e.hidden = n > 0;
  }
  function drawChips() {
    if (!chips) return; var cnt = {}; rows.forEach(function (r) { cnt[r.dataset.stage] = (cnt[r.dataset.stage] || 0) + 1; });
    chips.innerHTML = [['', X.all, rows.length]].concat(Object.keys(X.stages).map(function (k) { return [k, X.stages[k], cnt[k] || 0]; })).map(function (c) {
      return '<button type="button" data-s="' + c[0] + '" class="rounded-full text-xs px-2.5 py-1 border ' + (c[0] === stage ? 'bg-brand-600 text-white border-brand-600' : 'border-ink-200 text-ink-700 hover:bg-ink-50') + '">' + esc(c[1]) + ' <span class="opacity-70">' + c[2] + '</span></button>';
    }).join('') + (camp ? '<span class="ms-auto inline-flex items-center gap-2 text-xs rounded-full bg-amber-50 text-amber-800 px-2.5 py-1">' + esc(X.filterCamp) + ' <button type="button" id="ld-camp-x" class="font-semibold underline">' + esc(X.clear) + '</button></span>' : '');
  }
  if (chips) chips.addEventListener('click', function (e) { var b = e.target.closest('[data-s]'); if (b) { stage = b.dataset.s; drawChips(); apply(); } if (e.target.id === 'ld-camp-x') { camp = ''; drawChips(); apply(); hl(); } });
  if (q) q.addEventListener('input', apply);
  function hl() { $$('.ld-arow').forEach(function (r) { r.classList.toggle('bg-brand-50', !!camp && r.dataset.camp === camp); }); }
  $$('.ld-arow').forEach(function (r) { r.addEventListener('click', function (e) { if (e.target.closest('a')) return; camp = camp === r.dataset.camp ? '' : r.dataset.camp; drawChips(); apply(); hl(); var f = rows.filter(function (x) { return x.style.display !== 'none'; })[0]; if (f) f.scrollIntoView({ behavior: 'smooth', block: 'center' }); }); });
  drawChips();

  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key === '/' && q) { e.preventDefault(); q.focus(); }
  });
})();
