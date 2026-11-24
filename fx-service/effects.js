'use strict';
// يبني صفحة HTML لكتلة عنوان بتأثير مختار. يُصوّر العنصر #t بخلفية شفافة ويُركّبه PHP على الصورة.

const GOOGLE = 'https://fonts.googleapis.com/css2?' +
  ['Cairo:wght@700;900', 'Changa:wght@700;800', 'Tajawal:wght@700;800', 'Almarai:wght@700;800',
   'El+Messiri:wght@600;700', 'Reem+Kufi:wght@500;700', 'Amiri:wght@700', 'Alexandria:wght@700;800',
   'IBM+Plex+Sans+Arabic:wght@600;700'].map(f => 'family=' + f).join('&') + '&display=swap';

// خريطة الخط: مفتاح التطبيق -> عائلة CSS. auto => خط عرض افتراضي قوي.
const FONTS = {
  auto: 'Cairo', Cairo: 'Cairo', Alexandria: 'Alexandria', Tajawal: 'Tajawal', Almarai: 'Almarai',
  Changa: 'Changa', 'El Messiri': 'El Messiri', 'Reem Kufi': 'Reem Kufi', Amiri: 'Amiri',
  'IBM Plex Sans Arabic': 'IBM Plex Sans Arabic'
};

function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}
function hex(c, def) {
  c = String(c || '').trim();
  return /^#?[0-9a-fA-F]{6}$/.test(c) ? ('#' + c.replace('#', '')) : def;
}
// يغمّق/يفتّح لونًا hex بنسبة (0..1): k<1 أغمق، k>1 أفتح
function shade(h, k) {
  h = h.replace('#', '');
  let r = parseInt(h.slice(0, 2), 16), g = parseInt(h.slice(2, 4), 16), b = parseInt(h.slice(4, 6), 16);
  const f = x => Math.max(0, Math.min(255, Math.round(k <= 1 ? x * k : x + (255 - x) * (k - 1))));
  return '#' + [f(r), f(g), f(b)].map(x => x.toString(16).padStart(2, '0')).join('');
}

// يرجّع CSS للعنصر النصّي حسب التأثير. acc = لون الهوية. txt = لون التعبئة الأساسي.
function effectCss(effect, acc, size) {
  const s = Math.max(2, Math.round(size * 0.06));
  switch (effect) {
    case 'shadow':
      return `color:#fff;text-shadow:${s}px ${s}px 0 rgba(6,8,14,.55),0 2px 10px rgba(0,0,0,.4);`;
    case 'outline':
      return `color:#fff;-webkit-text-stroke:${Math.max(2, Math.round(size * 0.05))}px #0f1116;paint-order:stroke fill;` +
             `text-shadow:${s}px ${s}px 10px rgba(0,0,0,.45);`;
    case 'sticker':
      return `color:${acc};-webkit-text-stroke:${Math.max(4, Math.round(size * 0.11))}px #ffffff;paint-order:stroke fill;` +
             `text-shadow:0 ${Math.round(size * 0.09)}px ${Math.round(size * 0.1)}px rgba(0,0,0,.4);`;
    case '3d': {
      const d = shade(acc, 0.45), d2 = shade(acc, 0.3), layers = [];
      const depth = Math.max(4, Math.round(size * 0.09));
      for (let i = 1; i <= depth; i++) layers.push(`0 ${i}px 0 ${i <= 2 ? d2 : d}`);
      layers.push(`0 ${depth + 3}px ${Math.round(size * 0.12)}px rgba(0,0,0,.5)`);
      return `color:${acc};-webkit-text-stroke:2px ${shade(acc, 0.25)};paint-order:stroke fill;text-shadow:${layers.join(',')};`;
    }
    case 'gold': {
      const layers = [];
      const depth = Math.max(3, Math.round(size * 0.05));
      for (let i = 1; i <= depth; i++) layers.push(`0 ${i}px 0 #4a3608`);
      layers.push(`0 ${depth + 3}px ${Math.round(size * 0.12)}px rgba(0,0,0,.5)`);
      return `background:linear-gradient(180deg,#fff7cc 0%,#f5d142 42%,#c9971f 60%,#8a6a16 100%);` +
             `-webkit-background-clip:text;background-clip:text;color:transparent;` +
             `filter:drop-shadow(0 ${depth}px 2px rgba(0,0,0,.5));text-shadow:${layers.join(',')};`;
      // ملاحظة: مع التعبئة المتدرّجة، text-shadow تحت النص ما يبانش؛ نعتمد على drop-shadow للعمق
    }
    case 'glow':
    case 'neon':
      return `color:#f2fbff;text-shadow:0 0 ${Math.round(size * 0.07)}px ${acc},0 0 ${Math.round(size * 0.14)}px ${acc},` +
             `0 0 ${Math.round(size * 0.26)}px ${shade(acc, 0.8)},0 0 ${Math.round(size * 0.4)}px ${shade(acc, 0.7)};`;
    default:
      return 'color:#fff;text-shadow:0 2px 10px rgba(0,0,0,.45);';
  }
}

function buildHtml(o) {
  const text = esc(o.text).replace(/\n/g, '<br>');
  const kw = String(o.keyword || '').trim();
  const effect = String(o.effect || 'none');
  const acc = hex(o.color, '#2563eb');
  const width = Math.max(120, Math.min(2200, parseInt(o.width, 10) || 900));
  const size = Math.max(16, Math.min(400, parseInt(o.size, 10) || 90));
  const rtl = o.rtl !== false && o.rtl !== 'false';
  const align = ['right', 'left', 'center'].includes(o.align) ? o.align : (rtl ? 'right' : 'left');
  const fam = FONTS[o.font] || FONTS.auto;
  const lh = (o.lineHeight && +o.lineHeight) || (rtl ? 1.5 : 1.35);
  const pad = Math.round(size * 0.55); // هامش شفاف يستوعب الظل/التوهّج دون قص
  const css = effectCss(effect, acc, size);

  // تلوين كلمة مفتاحية داخل السطر بلون الهوية (إن لم يكن التأثير يلوّن النص كله)
  let body = text;
  if (kw && !['gold', 'sticker'].includes(effect)) {
    const safeKw = esc(kw);
    body = text.replace(new RegExp('(^|\\s)(' + safeKw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')(\\s|$)', 'u'),
      (m, a, b, c) => a + '<span style="color:' + acc + ';-webkit-text-fill-color:' + acc + '">' + b + '</span>' + c);
  }

  return `<!doctype html><html dir="${rtl ? 'rtl' : 'ltr'}"><head><meta charset="utf-8">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="${GOOGLE}" rel="stylesheet">
<style>
  html,body{margin:0;background:transparent}
  #wrap{display:inline-block;padding:${pad}px}
  #t{font-family:'${fam}',system-ui,sans-serif;font-weight:900;font-size:${size}px;line-height:${lh};
     width:${width}px;text-align:${align};word-break:break-word;${css}}
</style></head>
<body><div id="wrap"><div id="t">${body}</div></div></body></html>`;
}

module.exports = { buildHtml };
