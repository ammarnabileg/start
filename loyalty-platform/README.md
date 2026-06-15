# منصة الولاء — Hatchy Loyalty Platform

منصة ولاء White-Label: أي محل/مطعم/كافيه يعمل برنامج ولاء خاص بيه. تطبيقين موبايل
بـ **Flutter** + **Supabase** (Auth/DB/Storage/Functions) + **Firebase** (Push/Analytics).
الهوية البصرية بستايل **Hatchy** (أصفر دافئ + كتكوت + RTL عربي).

> **التكلفة التشغيلية في البداية ≈ $0/شهر** (Supabase Free + Firebase Spark). راجع
> `00_PROJECT_OVERVIEW.md` لتفصيل التكلفة والأدوات المجانية.

---

## 📁 هيكل المشروع (Monorepo)

```
loyalty-platform/
├── 00_PROJECT_OVERVIEW.md      # الأساس المشترك (معمارية/تكلفة/أمان/design system)
├── CUSTOMER_APP.md             # مواصفات تطبيق العميل (شاشة بشاشة)
├── MERCHANT_APP.md             # مواصفات تطبيق التاجر (شاشة بشاشة)
├── README.md                   # (هذا الملف) دليل البناء والتشغيل
│
├── packages/
│   └── loyalty_core/           # حزمة مشتركة: Theme + Models + Widgets + QR(TOTP)
│
├── apps/
│   ├── customer_app/           # تطبيق العميل (Flutter)
│   └── merchant_app/           # تطبيق التاجر (Flutter)
│
├── supabase/
│   └── functions/              # Edge Functions (Deno/TypeScript) — المنطق الحسّاس
│       ├── _shared/            # cors / auth / qr (مشترك)
│       ├── verify-qr/          # التحقق من كود العميل + الربط
│       ├── add-points/         # إضافة نقاط (يفرض السقوف + المستوى)
│       ├── record-visit/       # تسجيل زيارة (زيارة/يوم)
│       ├── redeem-reward/      # العميل يبدأ الاستبدال (كود مؤقت)
│       ├── confirm-redemption/ # الكاشير يأكّد → الخصم الفعلي
│       └── answer-question/    # العميل يجاوب سؤال التاجر وياخد نقاط
│
└── sql/
    └── schema.sql              # كل الجداول + الفهارس + RLS + الدوال
```

---

## 🚀 خطوات التشغيل

### 1) Supabase
```bash
# أنشئ مشروع على supabase.com (Free tier)
# نفّذ المخطط:
#   Dashboard > SQL Editor > الصق محتوى sql/schema.sql > Run
# فعّل Phone Auth (Authentication > Providers > Phone)

# انشر الـ Edge Functions:
supabase functions deploy verify-qr add-points record-visit \
  redeem-reward confirm-redemption staff-redeem apply-coupon answer-question \
  send-announcement spin-wheel redeem-prize claim-staff delete-account \
  proximity-hit pos-api pos-keys
```

### 2) الحزمة المشتركة + التطبيقات
```bash
cd packages/loyalty_core && flutter pub get && cd -
cd apps/customer_app && flutter pub get && cd -
cd apps/merchant_app  && flutter pub get && cd -
```

### 3) التشغيل (المفاتيح تُمرّر وقت البناء — آمنة للمستودعات العامة)
```bash
flutter run \
  --dart-define=SUPABASE_URL=https://xxxx.supabase.co \
  --dart-define=SUPABASE_ANON_KEY=eyJhbGci...
```

### 4) Firebase (Push + Analytics)
- أنشئ مشروع Firebase وأضِف تطبيقَي Android/iOS.
- حط `google-services.json` (أندرويد) و `GoogleService-Info.plist` (iOS).
- استخدم `flutterfire configure` لتوليد `firebase_options.dart` لكل تطبيق.

---

## ✨ الميزات وخياراتها (كله قابل للتهيئة من التاجر)

كل إعدادات التاجر في جدول `merchant_settings` (صف لكل تاجر) — **التاجر يتحكّم في كل حاجة**:

| الخيار | الحقل | الافتراضي |
|---|---|---|
| **نطاق النقاط** | `points_scope` = `merchant` (مشترك بين الفروع) / `branch` (منفصل لكل فرع) | `branch` |
| تفعيل الزيارات/النقاط/المكافآت/المستويات | `enable_*` | مفعّلة |
| الكوبونات/الإحالة/الميلاد/القرب/GPS | `enable_*` | معطّلة |
| سقف النقاط للعملية | `max_points_per_txn` | 500 |
| السقف اليومي للموظف | `daily_points_per_staff` | 5000 |
| زيارة واحدة في اليوم | `one_visit_per_day` | true |
| تأكيد العميل على الاستبدال | `require_redemption_confirm` | true |
| مدة تجدّد QR | `qr_rotation_seconds` | 30 |
| لون/اسم العلامة (White-Label) | `primary_color_hex` / `brand_name` | — |

### 🧭 نطاق النقاط — الوضعان مدعومان بنفس الـ schema
- **مشترك (`merchant`):** محفظة واحدة للعميل عند التاجر (`branch_id = NULL`).
- **منفصل لكل فرع (`branch`):** محفظة لكل (عميل, تاجر, فرع).
- الدالة `get_or_create_wallet()` تحلّ المحفظة الصحيحة تلقائيًا حسب الإعداد، وكل
  الـ Edge Functions تستدعيها — فمفيش منطق مكرر.

### ❓ الأسئلة (Questions) — جديد
- التاجر يضيف أسئلة (`single_choice` / `multi_choice` / `text`) **بنقاط يحدّدها**.
- العميل يجاوب مرة واحدة → ياخد النقاط (عبر `answer-question`).
- التاجر يشوف كل الإجابات في لوحته (`question_responses`، محميّة بـ RLS).
- جداول: `merchant_questions` · `question_options` · `question_responses`.

### 🏆 لوحات الصدارة (Leaderboards) — جديد
- **عامة لكل التطبيق:** ترتيب العملاء بإجمالي النقاط عبر كل المتاجر — `global_leaderboard()`.
- **لكل ستور:** `store_leaderboard(p_merchant, p_branch)`:
  - `p_branch = NULL` → الستور ككل (تجميع عبر الفروع).
  - `p_branch = فرع` → ترتيب داخل الفرع (يناسب وضع النقاط المنفصلة لكل فرع).
- **الخصوصية:** تُحترم `users.leaderboard_opt_in` — المنسحب لا يظهر اسمه ولا مركزه.

---

## 🔐 الأمان (ملخّص — التفصيل في 00_PROJECT_OVERVIEW.md)
- **QR متغيّر (TOTP)** يُولّد على الجهاز ويُتحقّق على السيرفر — السكرين شوت القديم يفشل.
- **عزل التجار عبر RLS** على مستوى الـ DB، مش الكود.
- كل عملية حسّاسة (نقاط/زيارة/استبدال) تمرّ عبر **Edge Function** بمفتاح `service_role`
  بعد فرض القواعد (السقوف + زيارة/يوم + تأكيد الطرفين للاستبدال).

---

## ✅ البناء والتحقق (CI)
- `flutter analyze` نظيف للحزم الثلاث (loyalty_core / customer_app / merchant_app).
- اختبارات وحدة: **13 ناجحة** — `QrToken` (التوكن المتغيّر) و`LoyaltyRules` (منطق النقاط/المستويات). شغّلها: `cd packages/loyalty_core && flutter test`.
- **GitHub Actions** (`.github/workflows/flutter.yml`): يشغّل analyze + test على كل push، ويبني **APK** للتطبيقين (job منفصل) ويرفعهم كـ artifacts.
- مجلدات `android/ios` مولّدة (`flutter create`) فالمشروع جاهز للبناء. `minSdk = 23` (متطلّب Firebase/mobile_scanner).
- بناء الـ APK يتم في CI لأن مضيف Android SDK (`dl.google.com`) غير مسموح في بيئة التطوير دي.

## 🗺️ حالة التنفيذ (Scaffold)
هذا أساس عملي (scaffold) جاهز للبناء عليه — مش تطبيق نهائي:
- ✅ الحزمة المشتركة (theme/models/widgets/QR) — كاملة.
- ✅ Edge Functions الأساسية — كاملة ومتّسقة مع الـ schema.
- ✅ SQL schema كامل + RLS + الدوال.
- ✅ شاشة الـ QR (العميل) + الماسح وملف العميل (التاجر) — عاملة.
- ✅ متاجري + الإشعارات + لوحة الصدارة + الملف — عاملة.
- ⏳ باقي الشاشات موصوفة بالكامل في ملفّي `CUSTOMER_APP.md` و `MERCHANT_APP.md`
  (Onboarding/OTP/Store Detail tabs/إدارة المكافآت/التحليلات...) — تُبنى تباعًا.
