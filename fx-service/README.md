# shohad fx-service — مؤثرات نص احترافية (Headless Chromium)

خدمة صغيرة ترندر عنوانًا بتأثير (ذهبي / ثري دي / ستيكر / نيون / حدود / ظل) وتُرجّع **PNG شفاف**،
والتطبيق (PHP) يركّبه على الصورة. العربي يُشكَّل بالمتصفّح فيطلع مظبوط 100%.

تعمل على **VPS** — مش على استضافة PHP المشتركة.

## التشغيل بـ Docker (موصى به)

```bash
cd fx-service
docker build -t shohad-fx .
docker run -d --name shohad-fx --restart unless-stopped \
  -e FX_TOKEN='ضع-توكن-سري-طويل-هنا' \
  -p 127.0.0.1:8080:8080 \
  shohad-fx
```

ثم ضَع reverse proxy (Nginx/Caddy) بـ HTTPS أمام `127.0.0.1:8080`، مثال Caddy:

```
fx.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

## التشغيل بدون Docker

```bash
cd fx-service
npm install                 # يتبع playwright install chromium تلقائيًا
FX_TOKEN='توكن-سري' PORT=8080 node server.js
# للإنتاج: pm2 start server.js --name shohad-fx   (بعد: pm2 set ... أو ملف env)
```

## الاختبار

```bash
curl -s localhost:8080/health
# {"ok":true}

curl -s -X POST localhost:8080/render -H 'Content-Type: application/json' \
  -d '{"token":"توكن-سري","text":"عنوان تجريبي","keyword":"تجريبي","effect":"sticker","color":"f59e0b","size":90,"width":760,"rtl":true,"font":"Cairo"}' \
  -o out.png
```

## الربط بالتطبيق

في إعدادات اللوحة (أو كمتغيرات بيئة على سيرفر PHP) اضبط:

- `fx_render_url`   = `https://fx.example.com`  (أو `FX_RENDER_URL`)
- `fx_render_token` = نفس `FX_TOKEN`             (أو `FX_RENDER_TOKEN`)

لو القيمتين فاضيتين، التطبيق يرجع تلقائيًا لمحرّك GD (التأثيرات الأساسية) — بلا أعطال.

## الحقول المقبولة في `/render`

| الحقل | النوع | ملاحظات |
|---|---|---|
| `token` | string | لازم يطابق `FX_TOKEN` (أو هيدر `x-fx-token`) |
| `text` | string | العنوان (يدعم `\n` للأسطر) |
| `keyword` | string | كلمة تُلوّن بلون الهوية داخل السطر (تُتجاهَل في gold/sticker) |
| `effect` | string | none / shadow / outline / sticker / 3d / gold / glow(=neon) |
| `color` | string | hex بدون # (لون الهوية) |
| `size` | int | حجم الخط px |
| `width` | int | عرض عمود النص px |
| `rtl` | bool | عربي = true |
| `align` | string | right / center / left |
| `font` | string | Cairo / Alexandria / Tajawal / … (اسم خط التطبيق) |
| `scale` | int | 1..3 دقّة الرندر (افتراضي 2) |

يرجّع `image/png` شفاف، أو JSON فيه `error` عند الفشل.

## ملاحظات أمان
- خلّي الخدمة خلف HTTPS وتوكن قوي؛ استقبل فقط من سيرفر التطبيق إن أمكن (firewall/allowlist).
- تعيد استخدام نسخة متصفّح واحدة؛ خفيفة على الموارد لكن خصّص ~512MB RAM.
