// geo-price: تسعير حسب الدولة عبر الـIP. الأساس 199 ريال (مربوط بالدولار ~$53).
// داخل السعودية → 199 ريال. خارجها → $53 بالدولار. عام (بدون مصادقة).
import { corsHeaders, json } from "../_shared/cors.ts";

// الأساس: 199 ريال / شهر. الريال مربوط بالدولار (3.75) → ~$53.
const BASE_SAR = 199;
const SAR_PER_USD = 3.75;
const USD = Math.round(BASE_SAR / SAR_PER_USD); // 53

function clientIp(req: Request): string | null {
  const xff = req.headers.get("x-forwarded-for");
  if (xff) return xff.split(",")[0].trim();
  return req.headers.get("x-real-ip");
}

// نحاول قراءة الدولة من ترويسات الـCDN أولًا، وإلا من خدمة GeoIP عبر الـIP.
async function detectCountry(req: Request): Promise<string | null> {
  const header = req.headers.get("cf-ipcountry") ||
    req.headers.get("x-vercel-ip-country") ||
    req.headers.get("x-country");
  if (header && header.length === 2) return header.toUpperCase();

  const ip = clientIp(req);
  if (!ip) return null;
  try {
    const r = await fetch(`https://ipapi.co/${ip}/country/`, {
      signal: AbortSignal.timeout(2500),
    });
    if (r.ok) {
      const c = (await r.text()).trim();
      if (c.length === 2) return c.toUpperCase();
    }
  } catch (_) {
    // الشبكة قد تكون مقيّدة — نرجع للافتراضي.
  }
  return null;
}

function priceFor(country: string | null) {
  if (country === "SA") {
    return {
      country,
      currency: "SAR",
      amount: BASE_SAR,
      period: "شهريًا",
      display: `${BASE_SAR} ريال`,
    };
  }
  // خارج السعودية (أو غير معروف) → الدولار.
  return {
    country: country ?? "XX",
    currency: "USD",
    amount: USD,
    period: "/ month",
    display: `$${USD}`,
  };
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }
  const country = await detectCountry(req);
  return json({ ...priceFor(country), base_sar: BASE_SAR });
});
