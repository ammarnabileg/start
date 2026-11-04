/*
 * استخراج النص في المتصفح، من غير ذكاء اصطناعي ومن غير برامج على السيرفر:
 * - PDF: مكتبة PDF.js (Mozilla). الصفحات الممسوحة (صور) بتتقرا بـ OCR.
 * - الصور: Tesseract.js (OCR) بالعربي والإنجليزي.
 * window.extractText(file, onProgress) => Promise<{text, method, pages}>
 */
(function () {
  var PDFJS = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.10.38/build/';
  var TESS = 'https://cdn.jsdelivr.net/npm/tesseract.js@7.0.0/dist/tesseract.min.js';
  var pdfjsP = null, tessP = null, worker = null;

  function loadPdf() {
    if (!pdfjsP) pdfjsP = import(PDFJS + 'pdf.min.mjs').then(function (m) { m.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.mjs'; return m; });
    return pdfjsP;
  }
  function loadTess() {
    if (!tessP) tessP = new Promise(function (ok, bad) { if (window.Tesseract) return ok(window.Tesseract); var s = document.createElement('script'); s.src = TESS; s.onload = function () { ok(window.Tesseract); }; s.onerror = bad; document.head.appendChild(s); });
    return tessP;
  }
  function ocrWorker(onProgress) {
    if (worker) return worker;
    worker = loadTess().then(function (T) {
      return T.createWorker(['ara', 'eng'], 1, { logger: function (m) { if (m.status === 'recognizing text' && onProgress) onProgress(m.progress); } });
    });
    return worker;
  }
  function clean(t) {
    // NFKC: اشكال الحروف العربية المرسومة (ــ) للحروف العادية
    if (t.normalize) t = t.normalize('NFKC');
    return t.replace(/[\u200e\u200f\u202a-\u202e\u2066-\u2069]/g, '').replace(/[ \t]+/g, ' ').replace(/ *\n */g, '\n').replace(/\n{3,}/g, '\n\n').trim();
  }
  // صفحة PDF لنص: بنجمع الكلام سطر سطر حسب مكانه، والسطر العربي من اليمين للشمال
  function pageText(content) {
    var lines = [];
    content.items.forEach(function (it) {
      if (!it.str) return;
      var y = Math.round(it.transform[5]), x = it.transform[4], ln = null;
      for (var i = 0; i < lines.length; i++) if (Math.abs(lines[i].y - y) <= 3) { ln = lines[i]; break; }
      if (!ln) { ln = { y: y, items: [] }; lines.push(ln); }
      ln.items.push({ x: x, w: it.width || 0, s: it.str, rtl: it.dir === 'rtl' || /[\u0600-\u06ff]/.test(it.str) });
    });
    lines.sort(function (a, b) { return b.y - a.y; });
    return lines.map(function (ln) {
      var rtl = ln.items.filter(function (i) { return i.rtl; }).length > ln.items.length / 2;
      ln.items.sort(function (a, b) { return rtl ? b.x - a.x : a.x - b.x; });
      var out = '', prev = null;
      ln.items.forEach(function (i) {
        if (prev) { var gap = rtl ? prev.x - (i.x + i.w) : i.x - (prev.x + prev.w); if (gap > 2 && !/\s$/.test(out) && !/^\s/.test(i.s)) out += ' '; }
        out += i.s; prev = i;
      });
      return out;
    }).join('\n');
  }

  window.extractText = function (file, onProgress) {
    var prog = function (p, label) { if (onProgress) onProgress(Math.max(0, Math.min(1, p)), label || ''); };
    var name = (file.name || '').toLowerCase();
    if (/^image\//.test(file.type) || /\.(png|jpe?g|webp|bmp)$/.test(name)) {
      prog(0, 'ocr');
      return ocrWorker(function (p) { prog(p, 'ocr'); }).then(function (w) { return w.recognize(file); })
        .then(function (r) { return { text: clean(r.data.text || ''), method: 'ocr', pages: 1 }; });
    }
    if (file.type === 'application/pdf' || /\.pdf$/.test(name)) {
      return Promise.all([loadPdf(), file.arrayBuffer()]).then(function (a) {
        return a[0].getDocument({ data: a[1] }).promise;
      }).then(function (doc) {
        var n = Math.min(doc.numPages, 200), parts = [], ocrPages = 0;
        return (function next(i) {
          if (i > n) return { text: clean(parts.join('\n\n')), method: ocrPages ? (ocrPages === n ? 'ocr' : 'pdfjs+ocr') : 'pdfjs', pages: n };
          return doc.getPage(i).then(function (page) {
            return page.getTextContent().then(function (c) {
              var t = pageText(c);
              prog((i - 1) / n, 'pdf');
              if (t.replace(/\s/g, '').length >= 25 || ocrPages >= 30) { parts.push(t); return next(i + 1); }
              // صفحة ممسوحة: نرسمها ونقراها بـ OCR (حد أقصى 30 صفحة)
              var vp = page.getViewport({ scale: 2 }), cv = document.createElement('canvas');
              cv.width = vp.width; cv.height = vp.height;
              return page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise
                .then(function () { return ocrWorker(function (p) { prog((i - 1 + p) / n, 'ocr'); }); })
                .then(function (w) { return w.recognize(cv); })
                .then(function (r) { ocrPages++; parts.push(r.data.text || ''); return next(i + 1); });
            });
          });
        })(1);
      });
    }
    return Promise.resolve(null);
  };
})();
