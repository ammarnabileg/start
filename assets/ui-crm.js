(function () {
  var C = window.CRM; if (!C) return;
  var T = C.tr;
  var grid = document.getElementById('crm-grid');
  var q = document.getElementById('crm-q');
  var none = document.getElementById('crm-none');
  var filter = '';
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function post(data) {
    var fd = new FormData(); fd.append('_csrf', C.csrf);
    for (var k in data) fd.append(k, data[k]);
    return fetch(C.url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); });
  }

  function apply() {
    var s = (q.value || '').trim().toLowerCase(), shown = 0;
    grid.querySelectorAll('.crm-co').forEach(function (c) {
      var ok = filter === 'hid' ? c.dataset.hid === '1' : c.dataset.hid !== '1';
      if (filter === 'new') ok = ok && c.dataset.new === '1';
      if (filter === 'rev') ok = ok && +c.dataset.rev > 0;
      if (s) ok = ok && c.dataset.q.indexOf(s) !== -1;
      c.style.display = ok ? '' : 'none'; if (ok) shown++;
    });
    none.classList.toggle('hidden', shown > 0 || !grid.children.length);
  }
  document.querySelectorAll('#crm-chips .chip').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('#crm-chips .chip').forEach(function (x) { x.classList.toggle('on', x === b); });
      filter = b.dataset.f; apply();
    });
  });
  q.addEventListener('input', apply);

  var rf = document.getElementById('crm-refresh');
  if (rf) rf.addEventListener('submit', function () { var b = rf.querySelector('button'); b.disabled = true; document.getElementById('crm-spin').classList.add('animate-spin'); });

  // اخفاء / رجوع
  grid.addEventListener('click', function (e) {
    var h = e.target.closest('.crm-hide'); if (!h) return;
    e.stopPropagation();
    var card = h.closest('.crm-co');
    post({ action: h.dataset.act, id: card.dataset.id }).then(function (r) {
      if (!r.ok) return;
      var hid = h.dataset.act === 'hide';
      card.dataset.hid = hid ? '1' : '0'; h.dataset.act = hid ? 'unhide' : 'hide'; h.textContent = hid ? '↺' : '✕';
      apply();
    });
  });

  // الدرج الجانبي
  var dr = document.getElementById('crm-drawer'), body = document.getElementById('crm-d-body'), left = document.getElementById('crm-left');
  var curCard = null, lastFocus = null;
  function close() { dr.classList.add('hidden'); dr.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; if (lastFocus) lastFocus.focus(); }
  dr.querySelectorAll('[data-close]').forEach(function (x) { x.addEventListener('click', close); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !dr.classList.contains('hidden')) close(); });
  function setLeft(n) { if (n != null) left.textContent = n; }
  function copyBtn(v) { return '<button type="button" class="crm-copy text-[11px] text-brand-700 hover:underline shrink-0" data-v="' + esc(v) + '">' + T.copy + '</button>'; }
  function leadHtml(l) {
    var ph = l.phone ? '<div class="flex items-center gap-2"><span>' + uic('phone', 'w-4 h-4 inline-block align-[-3px]') + '</span><a class="text-ink-900 font-medium" dir="ltr" href="tel:' + esc(l.phone) + '">' + esc(l.phone) + '</a>' + copyBtn(l.phone) +
      (l.wa ? '<a class="text-[11px] text-lime-700 hover:underline" target="_blank" rel="noopener" href="https://wa.me/' + esc(l.wa) + '">WhatsApp</a>' : '') + '</div>'
      : '<div class="flex items-center gap-2 text-ink-500"><span>' + uic('phone', 'w-4 h-4 inline-block align-[-3px]') + '</span>' + (l.pending ? '<span class="animate-pulse">' + T.pending + '</span>' : T.nophone) + '</div>';
    var em = l.email ? '<div class="flex items-center gap-2 min-w-0"><span>' + uic('mail', 'w-4 h-4 inline-block align-[-3px]') + '</span><a class="text-ink-900 font-medium truncate" dir="ltr" href="mailto:' + esc(l.email) + '">' + esc(l.email) + '</a>' + copyBtn(l.email) + '</div>'
      : '<div class="flex items-center gap-2 text-ink-500"><span>' + uic('mail', 'w-4 h-4 inline-block align-[-3px]') + '</span>' + T.noemail + '</div>';
    return '<div class="mt-3 space-y-1.5 text-sm rounded-xl bg-lime-50/70 border border-lime-200 p-3">' + ph + em + '</div>';
  }
  function personHtml(p) {
    var hint = [];
    if (p.has_email) hint.push('<span class="text-[10px] px-1.5 py-0.5 rounded bg-ink-100 text-ink-600">' + T.hasE + '</span>');
    if (p.has_phone) hint.push('<span class="text-[10px] px-1.5 py-0.5 rounded bg-ink-100 text-ink-600">' + T.hasP + '</span>');
    var act = p.lead ? leadHtml(p.lead) + '<div class="text-xs text-lime-700 mt-2">' + T.added + '</div>'
      : (C.canReveal ? '<button type="button" class="crm-rev btn-primary w-full mt-3 text-sm" data-p="' + esc(p.id) + '">🔓 ' + T.reveal + '</button>' : '<div class="text-xs text-ink-400 mt-3">' + T.noperm + '</div>');
    var initials = (p.name || '?').trim().split(/\s+/).map(function (w) { return w.charAt(0); }).slice(0, 2).join('');
    return '<div class="crm-p rounded-2xl border border-ink-200 p-4" data-p="' + esc(p.id) + '"><div class="flex items-start gap-3">' +
      '<div class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-500 to-cyan-500 text-white text-sm font-bold grid place-items-center shrink-0">' + esc(initials.toUpperCase()) + '</div>' +
      '<div class="min-w-0 flex-1"><div class="font-semibold text-ink-900">' + esc(p.name) + '</div><div class="text-xs text-ink-500">' + esc(p.title || '—') + '</div>' +
      (hint.length ? '<div class="flex gap-1 mt-1.5">' + hint.join('') + '</div>' : '') + '</div></div><div class="crm-act">' + act + '</div></div>';
  }
  function open(card) {
    curCard = card; lastFocus = document.activeElement;
    document.getElementById('crm-d-title').textContent = card.querySelector('.crm-name').textContent;
    var sub = card.querySelector('.text-xs.text-ink-500'); document.getElementById('crm-d-sub').textContent = sub ? sub.textContent : '';
    body.innerHTML = '<div class="space-y-3">' + [1, 2, 3].map(function () { return '<div class="h-24 rounded-2xl bg-ink-100 animate-pulse"></div>'; }).join('') + '<div class="text-xs text-ink-500 text-center">' + T.loading + '</div></div>';
    left.textContent = '…';
    dr.classList.remove('hidden'); dr.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
    dr.querySelector('[data-close].btn-ghost').focus();
    fetch(C.url + (C.url.indexOf('?') < 0 ? '?' : '&') + 'ajax=people&id=' + encodeURIComponent(card.dataset.id), { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); }).then(function (r) {
        if (curCard !== card) return;
        setLeft(r.left);
        var h = '';
        if (!r.ok) h += '<div class="rounded-xl ' + (r.plan ? 'bg-amber-50 text-amber-900 border border-amber-200' : 'bg-red-50 text-red-700') + ' text-sm p-3">' + esc(r.error || T.err) + (r.plan ? ' <a class="link" target="_blank" rel="noopener" href="https://www.apollo.io/pricing">' + esc(T.plans) + ' ↗</a>' : '') + '</div>';
        if (r.plan) h += manualForm();
        if (r.people) h += r.people.length ? r.people.map(personHtml).join('') : '<div class="text-sm text-ink-500 text-center py-6">' + T.none + '</div>';
        if (r.known && r.known.length) h += '<div class="text-xs font-semibold text-ink-500 pt-2">' + T.other + '</div>' + r.known.map(function (l) { return personHtml({ id: l.ext, name: l.name, title: l.title, lead: l }); }).join('');
        h += '<a class="link text-sm block text-center pt-2" href="' + esc(C.leadsUrl) + '">' + T.open + '</a>';
        body.innerHTML = h;
      }).catch(function () { body.innerHTML = '<div class="rounded-xl bg-red-50 text-red-700 text-sm p-3">' + T.err + '</div>'; });
  }
  function manualForm() {
    var inp = function (n, ph, t, dir) { return '<input name="' + n + '" type="' + (t || 'text') + '" class="input !h-9 text-sm" placeholder="' + esc(ph) + '"' + (dir ? ' dir="ltr"' : '') + '>'; };
    return '<form class="crm-man rounded-2xl border border-ink-200 p-3 space-y-2"><div class="text-sm font-semibold text-ink-900">＋ ' + esc(T.manualH) + '</div>' +
      inp('name', T.mName) + inp('title', T.mTitle) + '<div class="grid grid-cols-2 gap-2">' + inp('phone', T.mPhone, 'tel', 1) + inp('email', T.mEmail, 'email', 1) + '</div>' +
      '<button class="btn-primary w-full !text-sm">' + esc(T.mAdd) + '</button><div class="crm-man-msg text-xs"></div></form>';
  }
  body.addEventListener('submit', function (e) {
    var f = e.target.closest('.crm-man'); if (!f) return; e.preventDefault();
    var d = { action: 'manual_lead', company: curCard.dataset.id }; ['name', 'title', 'phone', 'email'].forEach(function (k) { d[k] = f.elements[k].value; });
    post(d).then(function (r) {
      var m = f.querySelector('.crm-man-msg');
      if (!r.ok) { m.className = 'crm-man-msg text-xs text-red-600'; m.textContent = r.error || T.err; return; }
      f.insertAdjacentHTML('beforebegin', personHtml({ id: r.lead.ext || ('l' + r.lead.id), name: r.lead.name, title: r.lead.title, lead: r.lead }));
      f.reset(); m.className = 'crm-man-msg text-xs text-lime-700'; m.textContent = r.merged ? T.merged : T.added;
      var lc = document.getElementById('crm-leads'); if (!r.merged) lc.textContent = +lc.textContent + 1;
      curCard.dataset.rev = +curCard.dataset.rev + 1;
    });
  });
  grid.addEventListener('click', function (e) { var c = e.target.closest('.crm-co'); if (c && !e.target.closest('.crm-hide')) open(c); });
  grid.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('crm-co')) { e.preventDefault(); open(e.target); } });

  body.addEventListener('click', function (e) {
    var cp = e.target.closest('.crm-copy');
    if (cp) { navigator.clipboard && navigator.clipboard.writeText(cp.dataset.v); cp.textContent = T.copied; setTimeout(function () { cp.textContent = T.copy; }, 1200); return; }
    var b = e.target.closest('.crm-rev'); if (!b) return;
    b.disabled = true; b.textContent = T.revealing;
    post({ action: 'reveal', company: curCard.dataset.id, person: b.dataset.p }).then(function (r) {
      var box = b.closest('.crm-act');
      if (!r.ok) { b.disabled = false; b.textContent = '🔓 ' + T.reveal; box.insertAdjacentHTML('beforeend', '<div class="text-xs text-red-600 mt-2">' + esc(r.error || T.err) + '</div>'); return; }
      var nm = b.closest('.crm-p').querySelector('.font-semibold'); if (nm && r.lead.name) nm.textContent = r.lead.name;
      box.innerHTML = leadHtml(r.lead) + '<div class="text-xs text-lime-700 mt-2">' + (r.merged ? T.merged : T.added) + '</div>';
      if (!r.again) {
        setLeft(r.left);
        var u = document.getElementById('crm-used'); u.textContent = +u.textContent + 1;
        var lc = document.getElementById('crm-leads'); if (!r.merged) lc.textContent = +lc.textContent + 1;
        curCard.dataset.rev = +curCard.dataset.rev + 1;
      }
    }).catch(function () { b.disabled = false; b.textContent = '🔓 ' + T.reveal; });
  });
})();
