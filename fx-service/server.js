'use strict';
// خدمة رندر مؤثرات النص: تستقبل JSON وتُرجّع PNG شفاف للعنوان بالتأثير المطلوب.
// تُشغَّل على VPS (مش على استضافة PHP). التطبيق يناديها عبر HTTP بتوكن مشترك.

const express = require('express');
const crypto = require('crypto');
const { chromium } = require('playwright');
const { buildHtml } = require('./effects');

const PORT = parseInt(process.env.PORT || '8080', 10);
const TOKEN = process.env.FX_TOKEN || '';
const MAX_W = 2200, MAX_H = 2200;

let browser = null, launching = null;
async function getBrowser() {
  if (browser) return browser;
  if (!launching) launching = chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'] })
    .then(b => { browser = b; launching = null; b.on('disconnected', () => { browser = null; }); return b; });
  return launching;
}

// مقارنة توكن ثابتة الزمن
function okToken(t) {
  if (!TOKEN) return true; // بدون توكن = مفتوح (غير موصى به في الإنتاج)
  t = String(t || '');
  const a = Buffer.from(t), b = Buffer.from(TOKEN);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

const app = express();
app.use(express.json({ limit: '512kb' }));

app.get('/health', async (req, res) => {
  try { await getBrowser(); res.json({ ok: true }); }
  catch (e) { res.status(500).json({ ok: false, error: String(e && e.message || e) }); }
});

app.post('/render', async (req, res) => {
  const body = req.body || {};
  if (!okToken(body.token || req.get('x-fx-token'))) return res.status(401).json({ error: 'unauthorized' });
  if (!body.text || typeof body.text !== 'string') return res.status(422).json({ error: 'text required' });
  const scale = Math.max(1, Math.min(3, parseInt(body.scale, 10) || 2));
  let page;
  try {
    const b = await getBrowser();
    page = await b.newPage({ deviceScaleFactor: scale, viewport: { width: MAX_W, height: MAX_H } });
    await page.setContent(buildHtml(body), { waitUntil: 'networkidle', timeout: 15000 });
    await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
    const el = await page.$('#wrap');
    if (!el) throw new Error('no element');
    const png = await el.screenshot({ omitBackground: true, type: 'png' });
    res.set('Content-Type', 'image/png').set('Cache-Control', 'no-store').send(png);
  } catch (e) {
    res.status(500).json({ error: String(e && e.message || e) });
  } finally {
    if (page) await page.close().catch(() => {});
  }
});

app.listen(PORT, () => console.log('shohad fx-service listening on :' + PORT + (TOKEN ? ' (token on)' : ' (NO TOKEN)')));
