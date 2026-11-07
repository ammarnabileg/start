/* حملة سريعة: شكل حديث فوق نفس الفورم — الحساب والهدف ونوع الإعلان والإطلاق كروت، المنشورات كروت بصرية،
   الميزانية بأزرار +/−، السن بشريط، النوع أزرار. كل اختيار بيغيّر الحقل الأصلي ويطلق change عشان المعاينة والملخص يتحدثوا. */
(function () {
  var form = document.getElementById('adcForm'), U = window.ADCU; if (!form || !U) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(n, c) { return window.uic ? uic(n, c || 'w-5 h-5') : ''; }
  function fire(el) { el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }
  // الأقسام
  var SEC = [['target', '#2563eb', U.d1], ['megaphone', '#7c3aed', U.d2], ['wallet', '#16a34a', U.d3], ['users', '#db2777', U.d4], ['play', '#d97706', U.d5]];
  [].forEach.call(form.querySelectorAll(':scope > section.card'), function (s, i) { var h = s.querySelector('h2'), m = SEC[i]; if (!h || !m) return; s.classList.add('abx-card'); s.style.setProperty('--c', m[1]);
    var head = document.createElement('div'); head.className = 'flex items-center gap-3'; head.innerHTML = '<span class="abx-ic">' + ic(m[0]) + '</span><div><h2 class="font-bold text-ink-900">' + esc(h.textContent.replace(/^\d+\.\s*/, '')) + '</h2><small class="text-xs text-ink-500">' + esc(m[2]) + '</small></div><span class="adq-n">' + (i + 1) + '</span>'; h.replaceWith(head); });
  // قايمة → كروت
  function cards(sel, opts) { if (!sel) return; var box = document.createElement('div'); box.className = 'adq-cards ' + (opts.cls || '');
    box.innerHTML = [].map.call(sel.options, function (o) { if (!o.value) return ''; var m = (opts.map || {})[o.value] || [];
      return '<button type="button" data-v="' + esc(o.value) + '" class="adq-card' + (o.value === sel.value ? ' on' : '') + '">' + (m[0] ? '<span class="adq-ci" style="--c:' + (m[1] || '#2563eb') + '">' + ic(m[0]) + '</span>' : '') + '<b>' + esc(opts.label ? opts.label(o) : o.text) + '</b>' + (m[2] || opts.sub ? '<small>' + esc(m[2] || opts.sub(o)) + '</small>' : '') + '</button>'; }).join('');
    sel.classList.add('abx-hide'); sel.after(box);
    box.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (!b) return; sel.value = b.dataset.v; box.querySelectorAll('.adq-card').forEach(function (x) { x.classList.toggle('on', x === b); }); fire(sel); }); return box; }
  function radios(name, map) { var rs = [].slice.call(form.querySelectorAll('input[name="' + name + '"]')); if (!rs.length) return; var wrap = rs[0].closest('section'), host = rs[0].closest('label').parentNode, box = document.createElement('div'); box.className = 'adq-cards two';
    box.innerHTML = rs.map(function (r) { var m = map[r.value] || []; return '<button type="button" data-v="' + esc(r.value) + '" class="adq-card big' + (r.checked ? ' on' : '') + (m[3] ? ' ' + m[3] : '') + '"><span class="adq-ci" style="--c:' + (m[1] || '#2563eb') + '">' + ic(m[0] || 'dots') + '</span><b>' + esc(r.closest('label').textContent.trim()) + '</b><small>' + esc(m[2] || '') + '</small></button>'; }).join('');
    rs[0].closest('label').before(box); rs.forEach(function (r) { r.closest('label').classList.add('abx-hide'); });
    box.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (!b) return; var r = rs.filter(function (x) { return x.value === b.dataset.v; })[0]; r.checked = true; box.querySelectorAll('.adq-card').forEach(function (x) { x.classList.toggle('on', x === b); }); fire(r); }); }
  // الحساب
  cards(form.querySelector('#adcTarget'), { cls: 'acc', label: function (o) { return o.text.replace(/[—–]/g, '').replace(/·\s*[A-Z]{3}\b/, '').trim(); }, sub: function (o) { var c = o.value.split('|')[2]; return c ? U.cur + ' ' + c : ''; } });
  // الهدف
  var GM = { engagement: ['heart', '#db2777'], traffic: ['pointer', '#2563eb'], awareness: ['eye', '#7c3aed'], reach: ['eye', '#7c3aed'], video_views: ['play', '#ea580c'], video: ['play', '#ea580c'], leads: ['users', '#16a34a'], lead_generation: ['users', '#16a34a'], messages: ['chat', '#0891b2'], conversions: ['target', '#16a34a'], sales: ['wallet', '#16a34a'], app_installs: ['grid', '#64748b'] };
  cards(form.querySelector('select[name=goal]'), { cls: 'goal', map: GM });
  // نوع الإعلان والإطلاق
  radios('mode', { boost: ['up', '#2563eb', U.boostD], 'new': ['image', '#7c3aed', U.newD] });
  radios('launch', { paused: ['pause', '#16a34a', U.pausedD], active: ['play', '#dc2626', U.activeD, 'danger'] });
  // المنشورات ككروت
  var ps = form.querySelector('#adcPost'), tg = form.querySelector('#adcTarget');
  if (ps) { var pg = document.createElement('div'); pg.className = 'adq-posts'; ps.closest('div').after(pg); ps.classList.add('abx-hide');
    var paintPosts = function () { var conn = tg ? (tg.selectedOptions[0] || {}).dataset.conn : '', os = [].filter.call(ps.options, function (o) { return o.value && (!o.dataset.conn || o.dataset.conn === conn); });
      pg.innerHTML = os.length ? os.map(function (o) { var t = o.text.split(' · '), pf = /insta|انستجرام|إنستغرام|انستغرام/i.test(t[1] || '') ? 'insta' : 'fb'; return '<button type="button" data-v="' + esc(o.value) + '" class="adq-post' + (o.value === ps.value ? ' on' : '') + '"><span class="adq-pf ' + (/insta/.test(pf) ? 'ig' : 'fb') + '">' + (/insta/.test(pf) ? 'IG' : 'f') + '</span><p dir="auto">' + esc(t.slice(2).join(' · ') || '—') + '</p><small>' + esc(t[0] || '') + '</small></button>'; }).join('')
        : '<p class="text-xs text-ink-400">' + esc(U.noPosts) + '</p>'; };
    pg.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (!b) return; ps.value = b.dataset.v; pg.querySelectorAll('.adq-post').forEach(function (x) { x.classList.toggle('on', x === b); }); fire(ps); });
    if (tg) tg.addEventListener('change', paintPosts); paintPosts(); }
  // الميزانية
  var bu = form.querySelector('input[name=budget]');
  if (bu) { var w = document.createElement('div'); w.className = 'abx-step'; bu.parentNode.insertBefore(w, bu); w.innerHTML = '<button type="button" data-d="-1">−</button>'; w.appendChild(bu); w.insertAdjacentHTML('beforeend', '<button type="button" data-d="1">+</button>');
    var q = document.createElement('div'); q.className = 'abx-quick'; q.innerHTML = [5, 10, 20, 50, 100].map(function (x) { return '<button type="button" data-a="' + x + '">' + x + '</button>'; }).join(''); w.after(q);
    w.addEventListener('click', function (e) { var b = e.target.closest('[data-d]'); if (!b) return; var v = +bu.value || 0, s = v >= 100 ? 10 : v >= 20 ? 5 : 1; bu.value = Math.max(1, v + s * +b.dataset.d); fire(bu); });
    q.addEventListener('click', function (e) { var b = e.target.closest('[data-a]'); if (b) { bu.value = b.dataset.a; fire(bu); } }); }
  // قوايم قصيرة → أزرار
  ['budget_type', 'gender'].forEach(function (n) { var s = form.querySelector('select[name=' + n + ']'); if (!s) return; var g = document.createElement('div'); g.className = 'abx-seg';
    g.innerHTML = [].map.call(s.options, function (o) { return '<button type="button" data-v="' + esc(o.value) + '" class="' + (o.value === s.value ? 'on' : '') + '">' + esc(o.text) + '</button>'; }).join(''); s.classList.add('abx-hide'); s.after(g);
    g.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (!b) return; s.value = b.dataset.v; g.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); }); fire(s); }); });
  // السن
  var mn = form.querySelector('input[name=age_min]'), mx = form.querySelector('input[name=age_max]');
  if (mn && mx) { var lo = 18, hi = 65, box = document.createElement('div'); box.className = 'abx-age col-span-full';
    box.innerHTML = '<div class="flex items-center justify-between mb-1"><span class="label !mb-0">' + esc(U.age) + '</span><b data-al></b></div><div class="abx-rg"><i></i><input type="range" min="18" max="65" data-r="min"><input type="range" min="18" max="65" data-r="max"></div><div class="abx-quick">' + [[18, 24], [25, 34], [35, 44], [45, 65], [18, 65]].map(function (r) { return '<button type="button" data-ag="' + r[0] + '-' + r[1] + '">' + r[0] + '–' + r[1] + '</button>'; }).join('') + '</div>';
    var l1 = mn.closest('div'), l2 = mx.closest('div'); l1.parentNode.insertBefore(box, l1); l1.classList.add('abx-hide'); l2.classList.add('abx-hide');
    var r1 = box.querySelector('[data-r=min]'), r2 = box.querySelector('[data-r=max]');
    var paint = function () { var a = +mn.value || lo, b = +mx.value || hi; r1.value = a; r2.value = b; box.querySelector('[data-al]').textContent = a + ' – ' + b + (b >= hi ? '+' : ''); var bar = box.querySelector('i'); bar.style.insetInlineStart = (a - lo) / (hi - lo) * 100 + '%'; bar.style.insetInlineEnd = (hi - b) / (hi - lo) * 100 + '%'; };
    var set = function (a, b) { mn.value = a; mx.value = b; fire(mn); fire(mx); paint(); };
    r1.addEventListener('input', function () { set(Math.min(+r1.value, +r2.value - 1), +r2.value); }); r2.addEventListener('input', function () { set(+r1.value, Math.max(+r2.value, +r1.value + 1)); });
    box.addEventListener('click', function (e) { var b = e.target.closest('[data-ag]'); if (b) { var g = b.dataset.ag.split('-'); set(+g[0], +g[1]); } }); paint(); }
})();
