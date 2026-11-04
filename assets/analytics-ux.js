/* التحليلات: الكروت بتتحكم في الرسم، تصدير CSV/PNG، أرقام بتعد، فترات جاهزة، اختصارات */
(function () {
  var A = window.ANX; if (!A) return;
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
  var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };

  /* ---------- الكروت ← الرسم ---------- */
  function pick(card) {
    var keys = card.dataset.metric.split(','), tg = $$('#an-daily-toggles input');
    if (!tg.length) return;
    tg.forEach(function (i) { var on = i.dataset.k !== 'prev' && keys.indexOf(i.dataset.k) > -1; if (i.checked !== on) { i.checked = on; i.dispatchEvent(new Event('change')); } });
    $$('.an-kpi').forEach(function (c) { var on = c === card; c.classList.toggle('ring-2', on); c.classList.toggle('ring-brand-500', on); c.classList.toggle('border-brand-300', on); });
    var ch = $('#an-daily'); if (ch) { var r = ch.getBoundingClientRect(); if (r.top < 80 || r.bottom > innerHeight) ch.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }
  $$('.an-kpi[data-metric]').forEach(function (c) {
    c.addEventListener('click', function () { pick(c); });
    c.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(c); } });
  });

  /* ---------- أرقام بتعد ---------- */
  $$('.an-kpi bdi').forEach(function (b) {
    var raw = b.textContent.trim(), m = raw.match(/^([+]?)([\d,]+)$/); if (!m) return;
    var to = parseInt(m[2].replace(/,/g, ''), 10); if (!to) return; var t0 = null;
    (function f(ts) { t0 = t0 || ts; var p = Math.min(1, (ts - t0) / 800); b.textContent = m[1] + Math.round(to * (1 - Math.pow(1 - p, 3))).toLocaleString('en-US'); if (p < 1) requestAnimationFrame(f); else b.textContent = raw; })(performance.now());
  });

  /* ---------- تصدير ---------- */
  function download(name, blob) { var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500); }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-export]'); if (!b || !window.__anDaily) return;
    var cfg = window.__anDaily.cfg, vis = cfg.series.filter(function (s) { return !s.hidden; }), stamp = new Date().toISOString().slice(0, 10);
    if (b.dataset.export === 'csv') {
      var rows = [[A.date].concat(vis.map(function (s) { return s.label; })).concat((cfg.extra || []).map(function (x) { return x.label; }))];
      cfg.labels.forEach(function (l, i) { rows.push([l].concat(vis.map(function (s) { return s.values[i] || 0; })).concat((cfg.extra || []).map(function (x) { return x.values[i] || 0; }))); });
      var csv = '﻿' + rows.map(function (r) { return r.map(function (c) { c = String(c); return /[",\n]/.test(c) ? '"' + c.replace(/"/g, '""') + '"' : c; }).join(','); }).join('\n');
      download('analytics-' + stamp + '.csv', new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    } else {
      var svg = $('#an-daily svg'); if (!svg) return;
      // نسخة من الرسم بألوان حقيقية بدل متغيرات CSS عشان تتحول لصورة صح
      var clone = svg.cloneNode(true), src = [svg].concat($$('*', svg)), dst = [clone].concat($$('*', clone));
      src.forEach(function (el, i) { var cs = getComputedStyle(el), d = dst[i]; ['fill', 'stroke'].forEach(function (k) { var v = el.getAttribute(k); if (v && v.indexOf('var(') > -1) d.setAttribute(k, cs[k]); }); if (el.tagName === 'text') { d.setAttribute('fill', cs.fill); d.setAttribute('font-family', cs.fontFamily); d.setAttribute('font-size', cs.fontSize); } });
      var r = svg.getBoundingClientRect(), k = 2, W = Math.round(r.width), H = Math.round(r.height);
      clone.setAttribute('width', W); clone.setAttribute('height', H); clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
      var img = new Image(), url = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(clone)], { type: 'image/svg+xml' }));
      img.onload = function () {
        var c = document.createElement('canvas'); c.width = W * k; c.height = (H + 40) * k; var g = c.getContext('2d'); g.scale(k, k);
        g.fillStyle = getComputedStyle(document.body).backgroundColor || '#fff'; g.fillRect(0, 0, W, H + 40);
        g.fillStyle = getComputedStyle(document.body).color || '#111'; g.font = '600 14px ' + getComputedStyle(document.body).fontFamily; g.textAlign = document.documentElement.dir === 'rtl' ? 'right' : 'left';
        g.fillText(A.chart + ' · ' + vis.map(function (s) { return s.label; }).join('، '), g.textAlign === 'right' ? W - 8 : 8, 22);
        g.drawImage(img, 0, 36, W, H); URL.revokeObjectURL(url);
        c.toBlob(function (bl) { download('analytics-' + stamp + '.png', bl); }, 'image/png');
      };
      img.src = url;
    }
  });

  /* ---------- فترات جاهزة في التاريخ المخصص ---------- */
  var pr = $('#an-presets');
  if (pr) {
    var f = $('#an-from'), t = $('#an-to'), iso = function (d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); };
    A.presets.forEach(function (p) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'rounded-full border border-ink-200 px-2.5 py-1 text-[11px] text-ink-700 hover:border-brand-400 hover:bg-brand-50'; b.textContent = p[0];
      b.onclick = function () { var n = new Date(), a, z = n; if (p[1] === 'month') a = new Date(n.getFullYear(), n.getMonth(), 1); else if (p[1] === 'lastmonth') { a = new Date(n.getFullYear(), n.getMonth() - 1, 1); z = new Date(n.getFullYear(), n.getMonth(), 0); } else a = new Date(n.getFullYear(), 0, 1); f.value = iso(a); t.value = iso(z); };
      pr.appendChild(b);
    });
  }

  /* ---------- شريط الفلاتر: ظل لما ينزل ---------- */
  var bar = $('#an-bar');
  if (bar) { var sh = function () { bar.firstElementChild.classList.toggle('shadow-lg', window.scrollY > 80); }; addEventListener('scroll', sh, { passive: true }); sh(); }

  /* ---------- أفضل وقت: اضغط على أي خانة = منشور جديد في أقرب يوم وساعة زيها ---------- */
  if (A.compose) $$('.an-cell').forEach(function (c) {
    c.classList.add('cursor-pointer', 'transition', 'hover:ring-2', 'hover:ring-brand-500', 'hover:scale-110');
    c.title = (c.title ? c.title + '\n' : '') + A.schedHint;
    c.addEventListener('click', function () {
      var dow = (+c.dataset.d + 1) % 7, h = +c.dataset.h, d = new Date(); d.setMinutes(0, 0, 0);
      var add = (dow - d.getDay() + 7) % 7; if (add === 0 && d.getHours() >= h) add = 7; d.setDate(d.getDate() + add); d.setHours(h);
      var p2 = function (n) { return ('0' + n).slice(-2); };
      location.href = A.compose + '&when=' + d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate()) + 'T' + p2(h) + ':00';
    });
  });

  /* ---------- اختصارات ---------- */
  function help() {
    var d = document.createElement('dialog'); d.className = 'w-[min(440px,94vw)] p-0 rounded-2xl border border-ink-200 bg-white shadow-2xl backdrop:bg-ink-900/40';
    d.innerHTML = '<div class="flex items-center px-5 h-12 border-b border-ink-200"><b class="flex-1 text-sm text-ink-900"></b><button type="button" class="x p-1.5 rounded-lg text-ink-400 hover:bg-ink-100">✕</button></div><div class="p-4 grid gap-1.5 text-sm"></div>';
    d.querySelector('b').textContent = A.keysTitle;
    A.keys.forEach(function (k) { var r = document.createElement('div'); r.className = 'flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-ink-50'; r.innerHTML = '<span class="text-ink-700"></span><span class="flex gap-1">' + k[0].split(' ').map(function (x) { return '<kbd class="min-w-[1.6rem] text-center rounded-md border border-ink-300 border-b-2 bg-ink-50 px-1.5 py-0.5 text-[11px] font-mono text-ink-700">' + esc(x) + '</kbd>'; }).join('') + '</span>'; r.querySelector('span').textContent = k[1]; d.querySelector('.grid').appendChild(r); });
    d.querySelector('.x').onclick = function () { d.close(); }; d.addEventListener('close', function () { d.remove(); }); document.body.appendChild(d); d.showModal();
  }
  var hb = $('#an-help'); if (hb) hb.onclick = help;
  document.addEventListener('keydown', function (e) {
    if (/INPUT|TEXTAREA|SELECT/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey) return;
    var rng = $('#an-bar [data-key="' + e.key + '"]');
    if (rng) { location.href = rng.href; return; }
    if ((e.key === '[' || e.key === ']') && A.tabs.length) { var i = A.tab + (e.key === ']' ? 1 : -1); if (A.tabs[i]) location.href = A.tabs[i]; return; }
    if (e.key === 'a') { var ac = $('#an-acc'); if (ac) { ac.open = true; e.preventDefault(); } }
    if (e.key === '?') { e.preventDefault(); help(); }
  });
})();
