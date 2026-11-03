/* AnCharts: tiny dependency-free SVG line chart with hover tooltip, keyboard
 * navigation and toggleable series. Colors are CSS strings such as
 * "rgb(var(--brand-600))" so they follow light/dark themes automatically.
 *
 *   var c = AnCharts.line(el, {labels: [...], series: [{key, label, color, values, hidden, dash}],
 *                              fmt: function (v) {...}, height: 240, aria: 'Daily metrics'});
 *   c.toggle('reach', true);
 */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  function mk(tag, attrs, parent) {
    var n = document.createElementNS(NS, tag);
    for (var k in attrs) if (Object.prototype.hasOwnProperty.call(attrs, k)) n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }
  function defFmt(v) {
    v = +v || 0;
    var a = Math.abs(v);
    if (a >= 1e6) return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
    if (a >= 1e4) return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
    return (Math.round(v * 100) / 100).toLocaleString('en-US');
  }
  function niceMax(v) {
    if (v <= 0) return 1;
    var p = Math.pow(10, Math.floor(Math.log10(v))), n = v / p;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p;
  }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; });
  }

  function line(el, cfg) {
    var fmt = cfg.fmt || defFmt, H = cfg.height || 240, idx = -1;
    el.style.position = 'relative';
    el.setAttribute('dir', 'ltr');
    var tip = document.createElement('div');
    tip.className = 'pointer-events-none absolute z-10 hidden rounded-lg border border-ink-200 bg-white px-3 py-2 text-xs shadow-lg text-ink-800';
    tip.style.minWidth = '8rem';

    function visible() { return cfg.series.filter(function (s) { return !s.hidden; }); }

    function draw() {
      el.innerHTML = '';
      var W = Math.max(280, el.clientWidth || 600), padL = 44, padR = 12, padT = 10, padB = 26;
      var n = cfg.labels.length, vs = visible(), mx = 0;
      vs.forEach(function (s) { s.values.forEach(function (v) { if (+v > mx) mx = +v; }); });
      mx = niceMax(mx);
      var iw = W - padL - padR, ih = H - padT - padB;
      function X(i) { return padL + (n <= 1 ? iw / 2 : i / (n - 1) * iw); }
      function Y(v) { return padT + ih - (+v || 0) / mx * ih; }
      var svg = mk('svg', {viewBox: '0 0 ' + W + ' ' + H, width: '100%', height: H, role: 'img', tabindex: '0',
        'aria-label': cfg.aria || '', class: 'block focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 rounded'});
      for (var g = 0; g <= 4; g++) {
        var gy = padT + ih - g / 4 * ih;
        mk('line', {x1: padL, x2: W - padR, y1: gy, y2: gy, stroke: 'rgb(var(--ink-200))', 'stroke-dasharray': g ? '3 3' : ''}, svg);
        var tx = mk('text', {x: padL - 6, y: gy + 3, 'text-anchor': 'end', 'font-size': 10, fill: 'rgb(var(--ink-500))'}, svg);
        tx.textContent = fmt(mx * g / 4);
      }
      var step = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(iw / 70))));
      for (var i = 0; i < n; i += step) {
        var t = mk('text', {x: X(i), y: H - 8, 'text-anchor': i === 0 ? 'start' : (i + step >= n && n > 2 ? 'end' : 'middle'), 'font-size': 10, fill: 'rgb(var(--ink-500))'}, svg);
        t.textContent = cfg.labels[i];
      }
      vs.forEach(function (s) {
        var pts = s.values.map(function (v, i) { return X(i).toFixed(1) + ',' + Y(v).toFixed(1); }).join(' ');
        if (vs.length === 1 && !s.dash) mk('polygon', {points: X(0) + ',' + (padT + ih) + ' ' + pts + ' ' + X(n - 1) + ',' + (padT + ih), fill: s.color, opacity: 0.1}, svg);
        mk('polyline', {points: pts, fill: 'none', stroke: s.color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round', 'stroke-dasharray': s.dash ? '5 4' : ''}, svg);
        if (n <= 12) s.values.forEach(function (v, i) { mk('circle', {cx: X(i), cy: Y(v), r: 2.5, fill: s.color}, svg); });
      });
      var guide = mk('line', {y1: padT, y2: padT + ih, stroke: 'rgb(var(--ink-400))', 'stroke-width': 1, visibility: 'hidden'}, svg);
      var dots = vs.map(function (s) { return mk('circle', {r: 4, fill: s.color, stroke: 'rgb(var(--surface))', 'stroke-width': 2, visibility: 'hidden'}, svg); });
      mk('rect', {x: padL, y: padT, width: iw, height: ih, fill: 'transparent'}, svg);
      el.appendChild(svg);
      el.appendChild(tip);

      function show(i) {
        if (i < 0 || i >= n) { hide(); return; }
        idx = i;
        var x = X(i);
        guide.setAttribute('x1', x); guide.setAttribute('x2', x); guide.setAttribute('visibility', 'visible');
        vs.forEach(function (s, k) { dots[k].setAttribute('cx', x); dots[k].setAttribute('cy', Y(s.values[i])); dots[k].setAttribute('visibility', 'visible'); });
        var h = '<div class="font-semibold mb-1">' + esc(cfg.labels[i]) + '</div>';
        vs.forEach(function (s) {
          h += '<div class="flex items-center justify-between gap-3"><span class="flex items-center gap-1.5"><span style="background:' + s.color + '" class="inline-block w-2 h-2 rounded-full"></span>' + esc(s.label) + '</span><b class="tabular-nums">' + esc(fmt(s.values[i])) + '</b></div>';
        });
        (cfg.extra || []).forEach(function (s) {
          h += '<div class="flex justify-between gap-3 text-ink-500"><span>' + esc(s.label) + '</span><span class="tabular-nums">' + esc((s.fmt || fmt)(s.values[i])) + '</span></div>';
        });
        tip.innerHTML = h;
        tip.classList.remove('hidden');
        var scale = el.clientWidth / W, left = x * scale + 12;
        if (left + tip.offsetWidth > el.clientWidth) left = x * scale - tip.offsetWidth - 12;
        tip.style.left = Math.max(0, left) + 'px';
        tip.style.top = '4px';
        svg.setAttribute('aria-label', (cfg.aria || '') + ': ' + tip.textContent);
      }
      function hide() {
        tip.classList.add('hidden'); guide.setAttribute('visibility', 'hidden');
        dots.forEach(function (d) { d.setAttribute('visibility', 'hidden'); });
      }
      function at(ev) {
        var r = svg.getBoundingClientRect(), px = (ev.clientX - r.left) / r.width * W;
        return Math.round(n <= 1 ? 0 : (px - padL) / iw * (n - 1));
      }
      svg.addEventListener('mousemove', function (ev) { show(Math.min(n - 1, Math.max(0, at(ev)))); });
      svg.addEventListener('mouseleave', hide);
      svg.addEventListener('touchstart', function (ev) { if (ev.touches[0]) show(Math.min(n - 1, Math.max(0, at(ev.touches[0])))); }, {passive: true});
      svg.addEventListener('blur', hide);
      svg.addEventListener('keydown', function (ev) {
        if (ev.key === 'ArrowRight' || ev.key === 'ArrowLeft') {
          ev.preventDefault();
          show(Math.min(n - 1, Math.max(0, (idx < 0 ? 0 : idx) + (ev.key === 'ArrowRight' ? 1 : -1))));
        } else if (ev.key === 'Escape') hide();
      });
    }

    draw();
    var tm;
    window.addEventListener('resize', function () { clearTimeout(tm); tm = setTimeout(draw, 120); });
    return {
      toggle: function (key, on) {
        cfg.series.forEach(function (s) { if (s.key === key) s.hidden = !on; });
        draw();
      },
      redraw: draw
    };
  }

  window.AnCharts = {line: line, fmt: defFmt};
})();
