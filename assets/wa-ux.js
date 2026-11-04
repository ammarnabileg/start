/* واتساب: حملة جديدة بمعاينة حية زي واتساب، قوالب كبطاقات، عدّ المستلمين، بحث في جهات الاتصال، ملخص */
(function () {
  var W = window.WAB, form = document.getElementById('wa-new'); if (!W || !form) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var sel = $('select.wa-tpl', form);
  $$('.wa-preview', form).forEach(function (x) { x.style.display = 'none'; x.classList.add('!hidden'); });

  /* ---------- تخطيط: الفورم + عمود المعاينة ---------- */
  var wrap = document.createElement('div'); wrap.className = 'grid lg:grid-cols-[1fr_22rem] gap-4 items-start';
  form.parentNode.insertBefore(wrap, form); wrap.appendChild(form);
  var aside = document.createElement('aside'); aside.className = 'lg:sticky lg:top-20 space-y-3';
  aside.innerHTML = '<div class="rounded-[2rem] border-[6px] border-ink-900 bg-ink-900 shadow-2xl overflow-hidden">'
    + '<div class="flex items-center gap-2 px-4 py-3 bg-[#075e54] text-[#fff]"><span class="w-8 h-8 rounded-full bg-[#fff]/20 flex items-center justify-center text-sm font-semibold"></span><div class="min-w-0"><b class="block text-sm truncate" id="wa-pv-biz"></b><span class="text-[10px] opacity-80">WhatsApp Business</span></div></div>'
    + '<div class="min-h-[20rem] p-3 space-y-2" style="background:#e5ddd5;background-image:radial-gradient(rgba(0,0,0,.05) 1px,transparent 1px);background-size:14px 14px" id="wa-pv"></div></div>'
    + '<div class="card p-4 text-sm space-y-2" id="wa-sum"></div>';
  wrap.appendChild(aside);
  $('#wa-pv-biz', aside).textContent = W.biz; aside.querySelector('.rounded-full').textContent = (W.biz || '?').trim().charAt(0);

  /* ---------- القوالب كبطاقات ---------- */
  var opts = [].slice.call(sel.options).filter(function (o) { return o.value; });
  if (opts.length) {
    sel.classList.add('hidden'); sel.removeAttribute('required');
    var g = document.createElement('div'); g.className = 'space-y-2';
    g.innerHTML = '<input type="search" class="input !mt-0 !h-9 !py-0 text-sm" dir="auto"><div class="grid sm:grid-cols-2 gap-2 max-h-80 overflow-y-auto p-0.5" id="wa-tpls"></div>';
    g.querySelector('input').placeholder = W.searchTpl;
    sel.after(g);
    var grid = $('#wa-tpls', g);
    opts.forEach(function (o) {
      var c = document.createElement('button'); c.type = 'button'; c.dataset.v = o.value;
      c.className = 'wa-tc text-start rounded-xl border border-ink-200 p-3 hover:border-brand-300 hover:bg-ink-50 transition';
      var nm = o.value.split('|'), cat = W.tplCats[o.value] || '';
      c.innerHTML = '<div class="flex items-center gap-2"><b class="text-xs text-ink-900 truncate flex-1" dir="ltr"></b><span class="text-[10px] text-ink-500 uppercase">' + esc(nm[1] || '') + '</span>' + (cat ? '<span class="rounded-md bg-ink-100 text-ink-600 px-1.5 text-[10px]">' + esc(W.cat[cat] || cat) + '</span>' : '') + '</div><p class="text-[11px] text-ink-600 mt-1 line-clamp-3 whitespace-pre-line" dir="auto"></p>';
      c.querySelector('b').textContent = nm[0]; c.querySelector('p').innerHTML = esc(o.dataset.body || '').replace(/\{\{(\w+)\}\}/g, '<span class="rounded bg-brand-100 text-brand-700 px-1">{{$1}}</span>');
      c.dataset.q = (o.value + ' ' + (o.dataset.body || '')).toLowerCase();
      c.onclick = function () { sel.value = o.value; sel.dispatchEvent(new Event('change', { bubbles: true })); paintTpl(); };
      grid.appendChild(c);
    });
    g.querySelector('input').addEventListener('input', function () { var v = this.value.trim().toLowerCase(); $$('.wa-tc', grid).forEach(function (c) { c.style.display = !v || c.dataset.q.indexOf(v) > -1 ? '' : 'none'; }); });
  }
  function paintTpl() { $$('.wa-tc').forEach(function (c) { var on = c.dataset.v === sel.value; ['border-brand-500', 'bg-brand-50', 'ring-2', 'ring-brand-500/20'].forEach(function (k) { c.classList.toggle(k, on); }); }); }

  /* ---------- المستلمين ---------- */
  var cBox = $('#modeContacts'), tBox = $('#modeTags');
  if (cBox && $$('.wa-crow', cBox).length > 3) {
    var tools = document.createElement('div'); tools.className = 'flex items-center gap-2 mb-2 sticky top-0 bg-white pb-2';
    tools.innerHTML = '<input type="search" class="input !mt-0 !h-8 !py-0 text-xs flex-1" dir="auto"><button type="button" class="link text-xs whitespace-nowrap"></button>';
    tools.querySelector('input').placeholder = W.searchC; tools.querySelector('button').textContent = W.selAll;
    cBox.prepend(tools);
    tools.querySelector('input').addEventListener('input', function () { var v = this.value.trim().toLowerCase(); $$('.wa-crow', cBox).forEach(function (r) { r.style.display = !v || r.dataset.q.indexOf(v) > -1 ? '' : 'none'; }); });
    tools.querySelector('button').onclick = function () { var vis = $$('.wa-crow', cBox).filter(function (r) { return r.style.display !== 'none'; }), on = vis.some(function (r) { return !r.querySelector('input').checked; }); vis.forEach(function (r) { r.querySelector('input').checked = on; }); update(); };
  }
  if (tBox) $$('label', tBox).forEach(function (l) { var i = l.querySelector('input'); if (!i) return; var n = W.contacts.filter(function (c) { return c.tags.indexOf(i.value) > -1; }).length; l.className = 'inline-flex items-center gap-1.5 rounded-full border border-ink-200 px-2.5 py-1 text-xs cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-700'; i.classList.add('accent-brand-600'); var s = document.createElement('span'); s.className = 'tabular-nums text-ink-400'; s.textContent = n; l.appendChild(s); });
  function audience() {
    var mode = ($('input[name=mode]:checked', form) || {}).value || 'tags';
    if (mode === 'contacts') { var ids = $$('input[name="contacts[]"]:checked', form).map(function (i) { return i.value; }); return { n: ids.length, exact: true, list: W.contacts.filter(function (c) { return ids.indexOf(c.id) > -1; }) }; }
    var tags = $$('input[name="tags[]"]:checked', form).map(function (i) { return i.value; });
    var list = tags.length ? W.contacts.filter(function (c) { return c.tags.some(function (t) { return tags.indexOf(t) > -1; }); }) : W.contacts;
    return { n: list.length, exact: false, list: list, all: !tags.length, more: W.contacts.length >= 200 };
  }

  /* ---------- المعاينة ---------- */
  function value(field, custom, c) {
    if (field === 'custom') return custom || '';
    if (!c) return field === 'name' ? W.sample : '';
    return { name: c.name, phone: c.phone, email: c.email, company: c.company }[field] || '';
  }
  function preview() {
    var pv = $('#wa-pv'), o = sel.options[sel.selectedIndex], body = o && o.value ? (o.dataset.body || '') : '';
    if (!body) { pv.innerHTML = '<p class="text-center text-xs text-ink-600 bg-[#fff]/70 rounded-lg px-3 py-2 mx-auto w-fit mt-24">' + esc(W.pickTpl) + '</p>'; return; }
    var a = audience(), c = a.list[0] || null, map = {};
    $$('.wa-map-row', form).forEach(function (r) { var k = r.querySelector('input[type=hidden]').value; if (!k) return; map[k] = value(r.querySelector('.wa-field').value, r.querySelector('.wa-custom').value, c); });
    var html = esc(body).replace(/\{\{(\w+)\}\}/g, function (m, k) { var v = map[k]; return v ? '<span class="rounded bg-[#d9fdd3] ring-1 ring-[#25d366]/50 px-0.5">' + esc(v) + '</span>' : '<span class="rounded bg-amber-200 px-0.5">{{' + esc(k) + '}}</span>'; });
    var now = new Date(), tm = now.getHours() + ':' + ('0' + now.getMinutes()).slice(-2);
    pv.innerHTML = '<div class="max-w-[85%] rounded-lg rounded-tl-none bg-[#fff] shadow-sm px-2.5 py-1.5 text-[13px] text-[#111b21] whitespace-pre-line break-words" dir="auto">' + html + '<span class="block text-end text-[10px] text-[#667781] mt-0.5" dir="ltr">' + tm + '</span></div>';
  }

  /* ---------- الملخص ---------- */
  function summary() {
    var a = audience(), o = sel.options[sel.selectedIndex], nm = (form.elements['name'] || {}).value || '—';
    var to = a.exact ? W.exact.replace('%s', a.n) : (a.all ? W.allSubs.replace('%s', a.n + (a.more ? '+' : '')) : W.approx.replace('%s', a.n + (a.more ? '+' : '')));
    $('#wa-sum').innerHTML = '<div class="flex justify-between gap-2"><span class="text-ink-500">' + esc(W.sumName) + '</span><b class="text-ink-900 truncate">' + esc(nm) + '</b></div>'
      + '<div class="flex justify-between gap-2"><span class="text-ink-500">' + esc(W.sumTpl) + '</span><b class="text-ink-900 truncate" dir="ltr">' + esc(o && o.value ? o.value.split('|')[0] : W.noTpl) + '</b></div>'
      + '<div class="flex justify-between gap-2"><span class="text-ink-500">' + esc(W.sumTo) + '</span><b class="' + (a.n ? 'text-brand-700' : 'text-red-600') + '">' + esc(to) + '</b></div>'
      + '<p class="text-[11px] text-ink-500 pt-1 border-t border-ink-100">' + esc(W.note) + '</p>';
  }
  function update() { paintTpl(); preview(); summary(); }
  form.addEventListener('input', update); form.addEventListener('change', function () { setTimeout(update, 0); });
  update();
})();
