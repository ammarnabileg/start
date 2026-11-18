/*
 * قراءة الملفات الضخمة (حتى 1 جيجا وأكتر) في المتصفح، من غير ذكاء اصطناعي ومن غير حدود رفع السيرفر:
 * - PDF: بيتقرا صفحة صفحة بأجزاء من الملف (مش بيحمّله كله في الذاكرة). الصفحات الممسوحة والصور جوه الصفحات بتتقرا بـ OCR.
 * - Word / PowerPoint / Excel: بيتفتح كأرشيف ملف ملف، والنص والصور اللي جواه بتتقرا.
 * - الصور: OCR (عربي وإنجليزي). النصوص (TXT/CSV/HTML/JSON): على أجزاء.
 * النص بيتبعت للسيرفر كل كام صفحة ويتحفظ؛ لو النت فصل أو الجهاز قفل، اختيار نفس الملف تاني بيكمّل من آخر صفحة اتحفظت.
 * window.KBBig.read(file, { images: true, onProgress: fn({done,total,chars,unit,stage}) }) => Promise<{id, chars, method}>
 */
(function () {
  var ZIPJS = 'https://cdn.jsdelivr.net/npm/@zip.js/zip.js@2.7.52/index.min.js';
  var K = function () { return window.extractKit; };
  function csrf() { var m = document.querySelector('meta[name=csrf-token]'); return m ? m.content : ''; }
  function post(d) {
    var fd = new FormData(); Object.keys(d).forEach(function (k) { fd.append(k, d[k]); }); fd.append('_csrf', csrf());
    return fetch(location.pathname + '?p=knowledge', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.text(); })
      .then(function (t) { return JSON.parse(t.slice(t.indexOf('{'))); });
  }
  // إعادة المحاولة لحد ما النت يرجع (بتأخير متزايد لحد دقيقة)
  function postRetry(d, onWait) {
    var n = 0;
    return (function go() { return post(d).catch(function (e) { n++; if (onWait) onWait(n); return new Promise(function (r) { setTimeout(r, Math.min(60000, 1000 * Math.pow(2, Math.min(n, 6)))); }).then(go); }); })();
  }
  function decode(s) { var t = document.createElement('textarea'); t.innerHTML = s; return t.value; }
  function kind(file) {
    var n = (file.name || '').toLowerCase();
    if (/\.pdf$/.test(n) || file.type === 'application/pdf') return 'pdf';
    if (/\.(docx|pptx|xlsx)$/.test(n)) return n.split('.').pop();
    if (/^image\//.test(file.type) || /\.(png|jpe?g|webp|bmp|gif)$/.test(n)) return 'img';
    if (/\.(txt|md|csv|tsv|json|html?|xml)$/.test(n) || /^text\//.test(file.type)) return 'text';
    return '';
  }
  /* OCR لصورة/كانفاس كبير: نصغّره لحد 3500 بكسل (أكبر من كده مابيحسّنش القراءة وبيوقّع المتصفح) */
  function ocrBlob(blob) {
    return createImageBitmap(blob).then(function (bm) {
      var s = Math.min(1, 3500 / Math.max(bm.width, bm.height)), cv = document.createElement('canvas');
      cv.width = Math.max(1, Math.round(bm.width * s)); cv.height = Math.max(1, Math.round(bm.height * s)); cv.getContext('2d').drawImage(bm, 0, 0, cv.width, cv.height); bm.close && bm.close();
      return ocrCanvas(cv);
    });
  }
  function ocrCanvas(cv) {
    return K().ocrWorker().then(function (w) { return w.recognize(cv, {}, { blocks: true }); }).then(function (r) { cv.width = cv.height = 0; return K().ocrText(r); });
  }
  /* ---------- PDF: صفحة صفحة من غير ما الملف كله يتحمل في الذاكرة ---------- */
  function openPdf(file) {
    return K().loadPdf().then(function (pdfjs) {
      var tr = new pdfjs.PDFDataRangeTransport(file.size, new Uint8Array(0));
      tr.requestDataRange = function (b, e) { file.slice(b, e).arrayBuffer().then(function (buf) { tr.onDataRange(b, new Uint8Array(buf)); }); };
      return pdfjs.getDocument({ range: tr, length: file.size, disableAutoFetch: true, disableStream: true, rangeChunkSize: 1 << 20 }).promise.then(function (doc) {
        var IMG = [pdfjs.OPS.paintImageXObject, pdfjs.OPS.paintInlineImageXObject, pdfjs.OPS.paintImageXObjectRepeat, pdfjs.OPS.paintJpegXObject].filter(function (x) { return x != null; });
        return { total: doc.numPages, unit: function (i, opt) {
          return doc.getPage(i).then(function (page) {
            return page.getTextContent().then(function (c) {
              var t = K().pageText(c), short = t.replace(/\s/g, '').length < 25;
              var wantImg = opt.images ? page.getOperatorList().then(function (ol) { return ol.fnArray.some(function (f) { return IMG.indexOf(f) > -1; }); }) : Promise.resolve(false);
              return wantImg.then(function (hasImg) {
                if (!short && !hasImg) { page.cleanup(); return t; }
                var vp = page.getViewport({ scale: 2 }), s = Math.min(1, 4000 / Math.max(vp.width, vp.height)); vp = page.getViewport({ scale: 2 * s });
                var cv = document.createElement('canvas'); cv.width = vp.width; cv.height = vp.height; opt.stage('ocr');
                return page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise.then(function () { return ocrCanvas(cv); }).then(function (o) {
                  page.cleanup(); opt.used.ocr = true;
                  if (short) return o;
                  // النص اللي على الصور بس (من غير تكرار نص الصفحة)
                  var extra = o.split('\n').filter(function (l) { l = l.trim(); return l.length > 2 && t.indexOf(l) < 0; });
                  return extra.length ? t + '\n' + extra.join('\n') : t;
                });
              });
            });
          });
        } };
      });
    });
  }
  /* ---------- Word / PowerPoint / Excel: أرشيف بيتقرا ملف ملف ---------- */
  function xmlText(x, para, run) {
    return x.split(para).map(function (p) { var m = p.match(run) || []; return m.map(function (r) { return decode(r.replace(/<[^>]+>/g, '')); }).join(''); }).filter(function (l) { return l.trim(); }).join('\n');
  }
  function openZip(file, ext) {
    return import(ZIPJS).then(function (z) {
      var rd = new z.ZipReader(new z.BlobReader(file));
      return rd.getEntries().then(function (es) {
        var num = function (n) { return +((n.match(/(\d+)\.xml$/) || [0, 0])[1]); };
        var txt = es.filter(function (e) { return ext === 'docx' ? /^word\/(document|header\d*|footer\d*|footnotes|endnotes)\.xml$/.test(e.filename)
          : ext === 'pptx' ? /^ppt\/(slides\/slide|notesSlides\/notesSlide)\d+\.xml$/.test(e.filename) : /^xl\/(sharedStrings|worksheets\/sheet\d+)\.xml$/.test(e.filename); })
          .sort(function (a, b) { return (/sharedStrings|document/.test(a.filename) ? -1 : 0) - (/sharedStrings|document/.test(b.filename) ? -1 : 0) || num(a.filename) - num(b.filename); });
        var med = es.filter(function (e) { return /^(word|ppt|xl)\/media\/.+\.(png|jpe?g|gif|bmp|webp)$/i.test(e.filename) && (e.uncompressedSize || 0) > 15000; });
        var units = txt.concat(med), shared = [], sharedE = es.filter(function (e) { return e.filename === 'xl/sharedStrings.xml'; })[0];
        var parseShared = function (x) { shared = x.split(/<\/si>/).map(function (s) { return (s.match(/<t[^>]*>[^<]*<\/t>/g) || []).map(function (t) { return decode(t.replace(/<[^>]+>/g, '')); }).join(''); }); };
        return { total: units.length, close: function () { return rd.close(); }, unit: function (i, opt) {
          var e = units[i - 1];
          if (med.indexOf(e) > -1) { opt.stage('ocr'); opt.used.ocr = true; return e.getData(new z.BlobWriter()).then(ocrBlob).then(function (t) { return t ? '[' + e.filename.split('/').pop() + ']\n' + t : ''; }); }
          // استكمال بعد انقطاع في Excel: جدول النصوص المشتركة لازم يتقرا الأول
          var pre = ext === 'xlsx' && !shared.length && sharedE && e !== sharedE ? sharedE.getData(new z.TextWriter()).then(parseShared) : Promise.resolve();
          return pre.then(function () { return e.getData(new z.TextWriter()); }).then(function (x) {
            if (ext === 'docx') return xmlText(x, /<\/w:p>/, /<w:t[^>]*>[^<]*<\/w:t>/g);
            if (ext === 'pptx') return (/notesSlide/.test(e.filename) ? '' : '— ' + num(e.filename) + ' —\n') + xmlText(x, /<\/a:p>/, /<a:t[^>]*>[^<]*<\/a:t>/g);
            if (/sharedStrings/.test(e.filename)) { parseShared(x); return ''; }
            return x.split(/<\/row>/).map(function (row) { return (row.match(/<c\b[^>]*>[\s\S]*?<\/c>/g) || []).map(function (c) {
              var v = (c.match(/<v>([^<]*)<\/v>/) || [])[1], it = (c.match(/<t[^>]*>([^<]*)<\/t>/) || [])[1];
              return /t="s"/.test(c) ? (shared[+v] || '') : decode(it != null ? it : (v || ''));
            }).filter(function (v) { return v !== ''; }).join(' | '); }).filter(Boolean).join('\n');
          });
        } };
      });
    });
  }
  /* ---------- نصوص: أجزاء 2 ميجا ---------- */
  function openText(file) {
    var SZ = 2 * 1048576, dec = new TextDecoder('utf-8'), html = /\.html?$|\.xml$/i.test(file.name);
    return Promise.resolve({ total: Math.max(1, Math.ceil(file.size / SZ)), unit: function (i) {
      return file.slice((i - 1) * SZ, i * SZ).arrayBuffer().then(function (b) { var t = dec.decode(b, { stream: true }); return html ? decode(t.replace(/<(script|style)[\s\S]*?<\/\1>/gi, ' ').replace(/<[^>]+>/g, ' ')) : t; });
    } });
  }

  window.KBBig = {
    supports: function (file) { return !!kind(file); },
    read: function (file, o) {
      o = o || {}; var k = kind(file), used = { ocr: false }, stage = 'read', chars = 0;
      var opt = { images: o.images !== false, used: used, stage: function (s) { stage = s; } };
      var fp = [file.name, file.size, file.lastModified || 0].join('|');
      var open = k === 'pdf' ? openPdf(file) : k === 'text' ? openText(file) : k === 'img' ? Promise.resolve({ total: 1, unit: function () { opt.stage('ocr'); used.ocr = true; return ocrBlob(file); } }) : openZip(file, k);
      return open.then(function (src) {
        return postRetry({ action: 'kb_big_start', name: file.name, mime: file.type || '', size: file.size, fp: fp, total: src.total }).then(function (s) {
          if (!s.ok) throw new Error(s.error || 'start');
          chars = s.chars || 0; var id = s.id, buf = '', from = s.done + 1, i = s.done + 1;
          var tell = function () { if (o.onProgress) o.onProgress({ done: i - 1, total: src.total, chars: chars + buf.length, stage: stage, resumed: s.resumed }); };
          var flush = function (to) { if (to < from) return Promise.resolve(); var d = { action: 'kb_big_append', id: id, from: from, to: to, text: buf };
            return postRetry(d, function () { if (o.onProgress) o.onProgress({ done: i - 1, total: src.total, chars: chars + buf.length, stage: 'offline' }); }).then(function (r) { if (r.ok) chars = r.chars; buf = ''; from = to + 1; }); };
          tell();
          return (function next() {
            if (i > src.total) return flush(src.total).then(function () { if (src.close) src.close(); return postRetry({ action: 'kb_big_finish', id: id, method: k === 'pdf' ? (used.ocr ? 'pdfjs+ocr' : 'pdfjs') : k === 'img' ? 'ocr' : k + (used.ocr ? '+ocr' : '') }); })
              .then(function () { return { id: id, chars: chars, method: k }; });
            stage = 'read';
            return Promise.resolve(src.unit(i, opt)).catch(function () { return ''; }).then(function (t) {
              t = K().clean(t || ''); if (t) buf += (buf ? '\n\n' : '') + t; i++; tell();
              // نحفظ كل 10 وحدات أو 300 ألف حرف
              return (i - from >= 10 || buf.length > 300000 ? flush(i - 1) : Promise.resolve()).then(next);
            });
          })();
        });
      });
    }
  };
})();
