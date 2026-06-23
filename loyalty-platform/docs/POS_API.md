# POS Integration API — منصة الولاء

واجهة برمجية (Server-to-Server) تتيح لأنظمة الكاشير (POS) التكامل مع المنصّة:
البحث عن عميل، إضافة نقاط من الفاتورة، تسجيل زيارة، واستبدال مكافأة — بدون تطبيق.

> كل عملية مقيّدة تلقائيًا بـ **التاجر صاحب المفتاح** وفرعه (عزل كامل).

---

## 1) المصادقة (Authentication)
كل طلب يحمل مفتاح API في الترويسة:

```
x-api-key: pos_live_xxxxxxxxxxxxxxxx
```

- التاجر يولّد/يلغي المفاتيح من تطبيق صاحب المتجر → **الإدارة → تكامل POS**.
- المفتاح الخام يظهر **مرة واحدة فقط** عند الإنشاء (نخزّن hash فقط).
- يمكن ربط المفتاح بفرع معيّن (كاشير/تيرمينال) فتُنسب العمليات لذلك الفرع.

---

## 2) نقطة النهاية (Endpoint)

```
POST https://<YOUR-PROJECT>.supabase.co/functions/v1/pos-api
Content-Type: application/json
x-api-key: pos_live_xxx
```

الجسم يحدّد `action` والمعاملات. تعريف العميل بأحد:
- `phone` (رقم جوال العميل) — الأنسب عند الكاشير، أو
- `customer_id` (معرّف العضوية).

---

## 3) العمليات (Actions)

### `lookup` — استعلام رصيد العميل
```json
{ "action": "lookup", "phone": "+9665xxxxxxx" }
```
**الرد:**
```json
{
  "customer_id": "uuid",
  "name": "أحمد خالد",
  "available_points": 350,
  "lifetime_points": 1500,
  "level": "فضي"
}
```

### `earn` — إضافة نقاط من فاتورة
- مرّر `amount` (قيمة الفاتورة) فتُحسب النقاط = `amount × earn_rate` (إعداد التاجر)،
  أو مرّر `points` مباشرة.
```json
{ "action": "earn", "phone": "+9665xxxxxxx", "amount": 120 }
```
**الرد:**
```json
{ "earned": 120, "available_points": 470, "lifetime_points": 1620 }
```
> يخضع لسقف العملية الواحدة (`max_points_per_txn`) ويحدّث مستوى العميل تلقائيًا.

### `visit` — تسجيل زيارة
```json
{ "action": "visit", "phone": "+9665xxxxxxx" }
```
**الرد:** `{ "recorded": true }` — أو خطأ 409 لو سُجّلت زيارة اليوم بالفعل.

### `redeem` — استبدال مكافأة
```json
{ "action": "redeem", "phone": "+9665xxxxxxx", "reward_id": "uuid" }
```
**الرد:** `{ "redeemed": true, "remaining_points": 370 }`

---

## 4) الأخطاء
| الكود | المعنى |
|---|---|
| 401 | مفتاح API مفقود/غير صالح |
| 404 | العميل غير موجود |
| 409 | تكرار (زيارة اليوم مثلًا) |
| 422 | قيمة غير صحيحة / نقاط غير كافية |

الجسم: `{ "error": "..." }`.

---

## 5) مثال كامل (cURL)
```bash
curl -X POST https://<YOUR-PROJECT>.supabase.co/functions/v1/pos-api \
  -H "x-api-key: pos_live_xxxxxxxxxxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"action":"earn","phone":"+9665xxxxxxx","amount":120}'
```

---

## 6) أمان وملاحظات
- خزّن المفتاح في خادم نظام الـ POS فقط — **لا تضعه في تطبيق العميل**.
- ألغِ المفتاح فورًا لو تسرّب (الإلغاء فوري).
- كل العمليات تُسجَّل في `points_transactions` / `user_visits` / `reward_redemptions`
  مع نسبتها لفرع المفتاح، فتظهر في تحليلات التاجر.
- التوسّع: أضِف مفتاحًا لكل فرع/تيرمينال لعزل وتتبّع أدقّ.
