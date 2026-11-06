// سحّاب منافسات اعتماد: بيفتح صفحة الزوار بمتصفح حقيقي (الموقع محمي ضد البوتات)، وبيقرا المنافسات النشطة
// صفحة صفحة على مهل، وبيبعتها للمنصة. شغّله كل ساعتين من cron. مرة في اليوم بيعمل لفة كاملة عشان المواعيد اللي اتمدت.
//
//   PLATFORM_URL=https://panel.example.com  INGEST_TOKEN=xxxx  node collector.js
//
// اختياري: MAX_PAGES (افتراضي 150)، DELAY_MS (افتراضي 8000)، FULL=1 (لفة كاملة دلوقتي)، DRY=1 (من غير إرسال)
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const PLATFORM = (process.env.PLATFORM_URL || '').replace(/\/+$/, '');
const TOKEN = process.env.INGEST_TOKEN || '';
const MAX_PAGES = +process.env.MAX_PAGES || 150;
const DELAY = +process.env.DELAY_MS || 8000;
const DRY = process.env.DRY === '1';
const STATE = process.env.STATE_FILE || path.join(__dirname, 'state.json');
const PAGE_SIZE = 24;
const BASE = (process.env.ETIMAD_BASE || 'https://tenders.etimad.sa').replace(/\/+$/, ''); // للاختبار على نسخة محلية

if (!DRY && (!PLATFORM || TOKEN.length < 32)) { console.error('PLATFORM_URL و INGEST_TOKEN مطلوبين (أو DRY=1)'); process.exit(2); }

const sleep = ms => new Promise(r => setTimeout(r, ms));
const jitter = () => DELAY + Math.floor(Math.random() * DELAY * 0.5);
const log = (...a) => console.log(new Date().toISOString(), ...a);

function loadState() { try { return JSON.parse(fs.readFileSync(STATE, 'utf8')); } catch (e) { return { seen: {}, lastFull: '' }; } }
function saveState(s) {
  // نحتفظ بآخر 20 ألف رقم بس
  const ids = Object.keys(s.seen); if (ids.length > 20000) { ids.sort((a, b) => s.seen[a] - s.seen[b]).slice(0, ids.length - 20000).forEach(k => delete s.seen[k]); }
  fs.writeFileSync(STATE, JSON.stringify(s));
}

async function send(items, note) {
  if (DRY) { log('DRY: كان هيتبعت', items.length, note || ''); return { ok: true, new: 0, updated: 0 }; }
  const r = await fetch(PLATFORM + '/index.php?p=tenders_ingest', { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + TOKEN }, body: JSON.stringify({ src: 'etimad', items, note }) });
  const j = await r.json().catch(() => ({ ok: false, error: 'HTTP ' + r.status }));
  if (!j.ok) throw new Error('المنصة رفضت: ' + (j.error || r.status));
  return j;
}

(async () => {
  const st = loadState();
  const today = new Date().toISOString().slice(0, 10);
  const full = process.env.FULL === '1' || st.lastFull !== today;
  log(full ? 'لفة كاملة' : 'تحديث سريع (لحد ما نلاقي منافسات شفناها)');
  const b = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
  const ctx = await b.newContext({ locale: 'ar-SA', userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36' });
  const p = await ctx.newPage();
  let total = 0, sent = { new: 0, updated: 0 }, knownRun = 0, pages = 0, err = '';
  try {
    await p.goto(BASE + '/Tender/AllTendersForVisitor', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await sleep(8000); // الحماية بتشتغل في الأول
    for (let n = 1; n <= MAX_PAGES; n++) {
      const res = await p.evaluate(async ({ n, size }) => {
        const r = await fetch('/Tender/AllSupplierTendersForVisitorAsync?PageSize=' + size + '&pageNumber=' + n + '&TenderCategory=2&PublishDateId=1&_=' + Date.now(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const t = await r.text(); try { return { s: r.status, d: JSON.parse(t) }; } catch (e) { return { s: r.status, bad: t.slice(0, 200) }; }
      }, { n, size: PAGE_SIZE });
      if (!res.d) { err = 'رد مش JSON (الحماية غالبًا): HTTP ' + res.s; break; }
      const items = res.d.data || []; pages++;
      if (!items.length) break;
      const fresh = items.filter(x => !st.seen[x.tenderId]).length;
      items.forEach(x => { st.seen[x.tenderId] = Date.now(); });
      const r = await send(items); sent.new += r.new || 0; sent.updated += r.updated || 0; total += items.length;
      log('صفحة', n, '—', items.length, 'منافسة،', fresh, 'جديدة على السحّاب. إجمالي اعتماد:', res.d.totalCount);
      if (!full) { knownRun = fresh ? 0 : knownRun + 1; if (knownRun >= 2) { log('صفحتين متتاليتين كلها معروفة، كده خلصنا'); break; } }
      if (n * PAGE_SIZE >= (res.d.totalCount || 0)) break;
      await sleep(jitter());
    }
  } catch (e) { err = String(e.message || e).slice(0, 180); }
  if (full && !err) st.lastFull = today;
  saveState(st);
  if (err) { log('وقف:', err); try { await send([], 'error: ' + err); } catch (e) {} }
  log('خلص:', pages, 'صفحة،', total, 'منافسة. في المنصة:', sent.new, 'جديدة،', sent.updated, 'اتحدثت');
  await b.close();
  process.exit(err && !total ? 1 : 0);
})();
