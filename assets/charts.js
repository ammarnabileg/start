/* AnCharts: نفس الواجهة القديمة (line / fmt) بس الرسم بقى بـ Apache ECharts.
 * محتاج assets/vendor/echarts.min.js قبله. لو ECharts مش موجود، بيرجع للرسم SVG البسيط.
 *
 *   var c = AnCharts.line(el, {labels: [...], series: [{key, label, color, values, hidden, dash}],
 *                              fmt: function (v) {...}, height: 240, aria: 'Daily metrics', extra: [{label, values, fmt}]});
 *   c.toggle('reach', true); c.redraw();
 *
 *   AnCharts.bars(el, {labels, values, texts, title, color})   // أعمدة (الصرف اليومي مثلاً)
 *   أي عنصر فيه data-echart="bar" و data-cfg='{"labels":[],"values":[],"texts":[]}' بيترسم لوحده.
 *
 * الألوان نصوص CSS زي "rgb(var(--brand-600))": بتتحول لألوان حقيقية وقت الرسم، وبيترسم من جديد لما الوضع الداكن يتغير.
 */
(function () {
  'use strict';
  function defFmt(v) {
    v = +v || 0;
    var a = Math.abs(v);
    if (a >= 1e6) return (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
    if (a >= 1e4) return (v / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
    return (Math.round(v * 100) / 100).toLocaleString('en-US');
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  var probe;
  function color(css) {
    if (!css || css.indexOf('var(') < 0) return css;
    if (!probe) { probe = document.createElement('span'); probe.style.display = 'none'; document.body.appendChild(probe); }
    probe.style.color = ''; probe.style.color = css;
    return getComputedStyle(probe).color || css;
  }
  var tok = function (n) { return color('rgb(var(--' + n + '))'); };
  var charts = [];
  function track(c) { charts.push(c); return c; }
  // إعادة الرسم لما الوضع يتغير (الكلاس dark على html) أو المقاس يتغير
  new MutationObserver(function () { charts.forEach(function (c) { c.redraw(); }); }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(function () { charts.forEach(function (c) { c.resize(); }); }, 120); });

  function baseAxis() {
    return {
      axisLine: { lineStyle: { color: tok('ink-200') } }, axisTick: { show: false },
      axisLabel: { color: tok('ink-500'), fontSize: 10, fontFamily: 'Alexandria, system-ui, sans-serif' },
      splitLine: { lineStyle: { color: tok('ink-200'), type: [3, 3] } }
    };
  }
  function tooltipBox() {
    return { trigger: 'axis', confine: true, backgroundColor: tok('surface'), borderColor: tok('ink-200'), padding: [8, 12],
      textStyle: { color: tok('ink-800'), fontSize: 12, fontFamily: 'Alexandria, system-ui, sans-serif' }, extraCssText: 'border-radius:10px;box-shadow:0 8px 24px rgba(13,22,48,.12);',
      axisPointer: { type: 'line', lineStyle: { color: tok('ink-400') } } };
  }

  function line(el, cfg) {
    if (!window.echarts) return legacy(el, cfg);
    var fmt = cfg.fmt || defFmt, H = cfg.height || 240, n = cfg.labels.length, chart;
    el.style.height = H + 'px'; el.setAttribute('dir', 'ltr'); el.setAttribute('role', 'img'); el.setAttribute('aria-label', cfg.aria || ''); el.tabIndex = 0;
    function option() {
      var vs = cfg.series.filter(function (s) { return !s.hidden; }), zoom = n > 45;
      return {
        animationDuration: 500, grid: { left: 44, right: 12, top: 10, bottom: zoom ? 46 : 26 },
        tooltip: Object.assign(tooltipBox(), { formatter: function (ps) {
          if (!ps.length) return ''; var i = ps[0].dataIndex, h = '<div style="font-weight:600;margin-bottom:4px">' + esc(cfg.labels[i]) + '</div>';
          ps.forEach(function (p) { h += '<div style="display:flex;justify-content:space-between;gap:12px;align-items:center"><span><span style="display:inline-block;width:8px;height:8px;border-radius:9px;background:' + p.color + ';margin-inline-end:6px"></span>' + esc(p.seriesName) + '</span><b style="font-variant-numeric:tabular-nums">' + esc(fmt(p.value)) + '</b></div>'; });
          (cfg.extra || []).forEach(function (s) { h += '<div style="display:flex;justify-content:space-between;gap:12px;opacity:.7"><span>' + esc(s.label) + '</span><span>' + esc((s.fmt || fmt)(s.values[i])) + '</span></div>'; });
          return h;
        } }),
        xAxis: Object.assign(baseAxis(), { type: 'category', data: cfg.labels, boundaryGap: false, splitLine: { show: false } }),
        yAxis: Object.assign(baseAxis(), { type: 'value', splitNumber: 4, axisLine: { show: false }, axisLabel: Object.assign(baseAxis().axisLabel, { formatter: function (v) { return fmt(v); } }) }),
        dataZoom: zoom ? [{ type: 'inside' }, { type: 'slider', height: 18, bottom: 4, borderColor: tok('ink-200'), fillerColor: color('rgb(var(--brand-500) / .15)'), handleStyle: { color: tok('brand-500') }, textStyle: { color: tok('ink-500'), fontSize: 10 } }] : [],
        series: vs.map(function (s) {
          var c = color(s.color);
          return { name: s.label, type: 'line', data: s.values.map(function (v) { return +v || 0; }), smooth: false, showSymbol: n <= 31, symbolSize: 5,
            lineStyle: { width: 2, color: c, type: s.dash ? [5, 4] : 'solid' }, itemStyle: { color: c },
            areaStyle: vs.length === 1 && !s.dash ? { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: c.replace('rgb(', 'rgba(').replace(')', ', .22)') }, { offset: 1, color: c.replace('rgb(', 'rgba(').replace(')', ', 0)') }]) } : undefined,
            emphasis: { focus: 'series' } };
        })
      };
    }
    function draw() { if (!chart) chart = echarts.init(el, null, { renderer: 'svg' }); chart.setOption(option(), true); }
    draw();
    // كيبورد: الأسهم بتتنقل بين الأيام
    var idx = -1;
    el.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' && e.key !== 'Escape') return; e.preventDefault();
      if (e.key === 'Escape') { chart.dispatchAction({ type: 'hideTip' }); return; }
      idx = Math.min(n - 1, Math.max(0, (idx < 0 ? 0 : idx) + (e.key === 'ArrowRight' ? 1 : -1)));
      chart.dispatchAction({ type: 'showTip', seriesIndex: 0, dataIndex: idx });
    });
    return track({
      toggle: function (key, on) { cfg.series.forEach(function (s) { if (s.key === key) s.hidden = !on; }); draw(); },
      redraw: draw, resize: function () { chart && chart.resize(); }
    });
  }

  function bars(el, cfg) {
    if (!window.echarts) return null;
    var chart, texts = cfg.texts || cfg.values.map(defFmt);
    el.setAttribute('dir', 'ltr'); if (!el.style.height) el.style.height = (cfg.height || 160) + 'px';
    function draw() {
      if (!chart) { el.innerHTML = ''; chart = echarts.init(el, null, { renderer: 'svg' }); }
      chart.setOption({
        animationDuration: 500, grid: { left: 8, right: 8, top: 8, bottom: 22, containLabel: false },
        tooltip: Object.assign(tooltipBox(), { axisPointer: { type: 'shadow', shadowStyle: { color: color('rgb(var(--ink-400) / .12)') } }, formatter: function (ps) { var i = ps[0].dataIndex; return '<b>' + esc(cfg.labels[i]) + '</b><br>' + esc(texts[i]); } }),
        xAxis: Object.assign(baseAxis(), { type: 'category', data: cfg.labels, axisLabel: Object.assign(baseAxis().axisLabel, { interval: 'auto', hideOverlap: true }) }),
        yAxis: { type: 'value', show: false },
        series: [{ type: 'bar', data: cfg.values.map(function (v) { return +v || 0; }), barMaxWidth: 28, itemStyle: { color: color(cfg.color || 'rgb(var(--brand-500))'), borderRadius: [4, 4, 0, 0] },
          emphasis: { itemStyle: { color: color('rgb(var(--brand-700))') } } }]
      }, true);
    }
    draw();
    return track({ redraw: draw, resize: function () { chart && chart.resize(); } });
  }
  function autoBars() {
    [].forEach.call(document.querySelectorAll('[data-echart="bar"]:not([data-echart-on])'), function (el) {
      el.setAttribute('data-echart-on', '1');
      try { if (bars(el, JSON.parse(el.getAttribute('data-cfg')))) { var nx = el.nextElementSibling; if (nx && nx.classList.contains('justify-between')) nx.remove(); } } catch (e) {}
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoBars); else autoBars();

  // ---- الرسم القديم (SVG) كاحتياطي لو ECharts ما اتحملش
  var NS = 'http://www.w3.org/2000/svg';
  function mk(tag, attrs, parent) { var n = document.createElementNS(NS, tag); for (var k in attrs) n.setAttribute(k, attrs[k]); if (parent) parent.appendChild(n); return n; }
  function niceMax(v) { if (v <= 0) return 1; var p = Math.pow(10, Math.floor(Math.log10(v))), n = v / p; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p; }
  function legacy(el, cfg) {
    var fmt = cfg.fmt || defFmt, H = cfg.height || 240;
    function draw() {
      el.innerHTML = ''; el.setAttribute('dir', 'ltr');
      var W = Math.max(280, el.clientWidth || 600), pl = 44, pr = 12, pt = 10, pb = 26, n = cfg.labels.length, vs = cfg.series.filter(function (s) { return !s.hidden; }), mx = 0;
      vs.forEach(function (s) { s.values.forEach(function (v) { if (+v > mx) mx = +v; }); }); mx = niceMax(mx);
      var iw = W - pl - pr, ih = H - pt - pb, X = function (i) { return pl + (n <= 1 ? iw / 2 : i / (n - 1) * iw); }, Y = function (v) { return pt + ih - (+v || 0) / mx * ih; };
      var svg = mk('svg', { viewBox: '0 0 ' + W + ' ' + H, width: '100%', height: H, role: 'img', 'aria-label': cfg.aria || '' });
      for (var g = 0; g <= 4; g++) { var gy = pt + ih - g / 4 * ih; mk('line', { x1: pl, x2: W - pr, y1: gy, y2: gy, stroke: 'rgb(var(--ink-200))' }, svg); mk('text', { x: pl - 6, y: gy + 3, 'text-anchor': 'end', 'font-size': 10, fill: 'rgb(var(--ink-500))' }, svg).textContent = fmt(mx * g / 4); }
      vs.forEach(function (s) { mk('polyline', { points: s.values.map(function (v, i) { return X(i) + ',' + Y(v); }).join(' '), fill: 'none', stroke: s.color, 'stroke-width': 2, 'stroke-dasharray': s.dash ? '5 4' : '' }, svg); });
      el.appendChild(svg);
    }
    draw();
    return track({ toggle: function (key, on) { cfg.series.forEach(function (s) { if (s.key === key) s.hidden = !on; }); draw(); }, redraw: draw, resize: draw });
  }

  window.AnCharts = { line: line, bars: bars, fmt: defFmt };
})();
