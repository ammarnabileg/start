# منصة الولاء — الأساس المشترك (Master Spec)

> **النوع:** منصة ولاء White-Label (أي محل / مطعم / كافيه يعمل برنامج ولاء خاص بيه).
> **التطبيقات:** تطبيقين موبايل بـ **Flutter** — تطبيق العميل + تطبيق التاجر — + لوحة أدمن ويب لاحقًا.
> **الستايل البصري:** نفس هوية **Hatchy** (أصفر دافئ + كتكوت + كروت دائرية + عربي RTL).
> **الفلسفة:** كل قرار (نقطة / زيارة / استبدال) السيرفر هو اللي بيتأكد منه. متثقش في جهاز العميل ولا الكاشير أبدًا.

هذا الملف هو **المرجع المشترك** بين التطبيقين. كل من `CUSTOMER_APP.md` و `MERCHANT_APP.md` يبني على المعرّف هنا (Database / Auth / Security / Design System) عشان نتجنّب التكرار.

---

## 0. القرار الأهم: تعدّد المستأجرين (Multi-Tenant)

- النقاط والمستويات **منفصلة لكل تاجر** — مش رصيد واحد على مستوى المنصة. العميل عنده محفظة ومستوى عند **كل تاجر لوحده**.
- ده بيأثّر على الـ schema كله: المفتاح الأساسي للمحفظة هو `(user_id, merchant_id, branch_id)` — **المحفظة منفصلة لكل فرع** (قرار متخذ).
- عزل التجار **مفروض على مستوى الداتابيز نفسه** عن طريق Supabase **Row Level Security (RLS)** — مش مجرد فلترة في الكود.

---

## 1. المعمارية (Architecture)

```
┌──────────────────┐   ┌──────────────────┐   ┌──────────────────┐
│  تطبيق العميل     │   │  تطبيق التاجر     │   │  لوحة أدمن (ويب)  │
│   (Flutter)      │   │   (Flutter)      │   │  (لاحقًا - Next) │
└────────┬─────────┘   └────────┬─────────┘   └────────┬─────────┘
         │                      │                      │
         └──────────────┬───────┴──────────────────────┘
                        │  HTTPS (supabase-flutter SDK)
              ┌─────────▼──────────────────────────────┐
              │            SUPABASE                     │
              │  • Auth (Phone OTP + JWT)               │
              │  • Postgres + RLS (عزل التجار)          │
              │  • Storage (لوجوهات / صور المكافآت)      │
              │  • Edge Functions (المنطق الحسّاس)       │
              │  • pg_cron (مهام مجدولة)                │
              └─────────┬──────────────────────────────┘
                        │ (Edge Function تستدعي)
              ┌─────────▼──────────────────────────────┐
              │            FIREBASE                      │
              │  • FCM (Push Notifications)              │
              │  • Analytics + Crashlytics (Tracking)    │
              └─────────────────────────────────────────┘
```

**قاعدة ذهبية:** أي عملية تغيّر رصيد/زيارة/استبدال **لازم تمرّ عبر Edge Function**، مش عبر `insert/update` مباشر من التطبيق. التطبيقات بتقرأ مباشرة (RLS بيحميها) لكن بتكتب الحاجات الحسّاسة عبر RPC / Edge Functions فقط.

---

## 2. الـ Tech Stack (كله مجاني أو بـ Free Tier سخي)

| الطبقة | الأداة | ليه؟ | التكلفة |
|---|---|---|---|
| الموبايل | **Flutter** (Dart) | كود واحد لـ iOS + Android = نص التكلفة والوقت | مجاني (مفتوح المصدر) |
| إدارة الحالة | **Riverpod** | بسيط، آمن للأنواع، testable | مجاني |
| الراوتنج | **go_router** | deep links للإشعارات | مجاني |
| Backend + DB | **Supabase** | Auth + Postgres + Storage + Functions في مكان واحد | Free tier (تحت) |
| الإشعارات | **Firebase Cloud Messaging (FCM)** | Push مجاني بالكامل، iOS + Android | **مجاني بلا حدود** |
| التحليلات/التتبّع | **Firebase Analytics + Crashlytics** | تتبّع الأحداث + الكراشات | **مجاني بلا حدود** |
| التصميم | **Figma** (Free) | design system + prototyping | مجاني |
| الخطوط العربية | **Tajawal / Cairo** (Google Fonts) | عربي نظيف يدعم RTL | مجاني |
| الأيقونات | **Lucide / Phosphor Icons** | متّسقة ومفتوحة المصدر | مجاني |
| CI/CD | **Codemagic** (500 دقيقة/شهر مجانًا) أو **GitHub Actions** | بناء آلي للتطبيقين | مجاني في البداية |
| إدارة الكود | **GitHub** | مستودعات خاصة مجانية | مجاني |
| رسم المخططات | **dbdiagram.io / Mermaid** | توثيق الـ schema | مجاني |
| QR generation | **qr_flutter** package | توليد الـ QR على الجهاز | مجاني |
| QR scanning | **mobile_scanner** package | كاميرا + قراءة QR (الأخف والأسرع) | مجاني |

### الحزم الأساسية في Flutter (كلها مجانية/Open Source)

```yaml
# pubspec.yaml — مشترك بين التطبيقين
dependencies:
  supabase_flutter: ^2.x        # Auth + DB + Storage + Functions
  flutter_riverpod: ^2.x        # State management
  go_router: ^14.x              # Navigation + deep links
  firebase_core: ^3.x
  firebase_messaging: ^15.x     # Push
  firebase_analytics: ^11.x     # Tracking
  firebase_crashlytics: ^4.x    # Crash reports
  flutter_local_notifications: ^17.x  # عرض الإشعار والتطبيق مفتوح
  google_fonts: ^6.x            # Tajawal / Cairo
  intl: ^0.19.x                 # تنسيق التواريخ والأرقام بالعربي
  cached_network_image: ^3.x    # كاش للصور (لوجوهات/مكافآت)
  freezed_annotation + json_serializable  # models
  flutter_secure_storage: ^9.x  # تخزين التوكنات بأمان
```

> **تطبيق العميل** يضيف: `qr_flutter` (عرض QR) + `screen_brightness` (تعلية الإضاءة) + `geofence_service` (إشعار القرب).
> **تطبيق التاجر** يضيف: `mobile_scanner` (مسح QR) + `image_picker` (رفع صور المكافآت).

---

## 3. التكلفة (الهدف: أقرب ما يكون للصفر في البداية)

### Supabase Free Tier (كافي تمامًا للإطلاق + أول مئات التجار)
- 500 MB Postgres database
- 1 GB Storage (لوجوهات وصور — مع ضغط الصور قبل الرفع تكفي آلاف الصور)
- 50,000 مستخدم نشط شهريًا (MAU) للـ Auth
- 500K استدعاء Edge Function شهريًا
- 2 مليون Realtime messages
- **التكلفة: $0**

> لما نكبر: Supabase Pro = **$25/شهر**. منتقلش له غير لما الإيراد يغطّيه.

### Firebase Spark Plan (المجاني)
- **FCM: مجاني بلا حدود** (push notifications)
- **Analytics: مجاني بلا حدود**
- **Crashlytics: مجاني بلا حدود**
- **التكلفة: $0**

### تكاليف لا مفر منها (مش من المنصة)
| البند | التكلفة | ملاحظة |
|---|---|---|
| Apple Developer Account | **$99/سنة** | إجباري لنشر iOS — لا بديل مجاني |
| Google Play Developer | **$25 مرة واحدة** | دفعة واحدة مدى الحياة |
| دومين (اختياري للأدمن) | ~$10/سنة | اختياري |

### نموذج الإيراد (من الـ PRD)
- **$9/شهر** أو **$99/سنة** لكل تاجر.
- تجربة مجانية 30 يوم.
- **في النسخة الأولى: التحصيل يدوي** (تحويل/فاتورة) والأدمن يفعّل الاشتراك. الجداول جاهزة لبوابة الدفع، لكن ما نبنيش البوابة دلوقتي (توفير وقت + رسوم).
- **نقطة التعادل:** 3 تجار مشتركين شهري (27$) يغطّوا اشتراك Supabase Pro لما نحتاجه.

**الخلاصة:** التكلفة التشغيلية الفعلية في البداية = **$0/شهر** + **$124 رسوم متاجر لمرة واحدة/سنويًا**.

---

## 4. الـ Design System (هوية Hatchy)

مستخرج من صور Hatchy: أصفر دافئ مبهج + كتكوت كماسكوت + كروت بحواف دائرية كبيرة + ظلال ناعمة + عربي RTL.

### الألوان (Color Tokens)

```dart
// lib/core/theme/app_colors.dart
class AppColors {
  // Primary — الأصفر بتاع Hatchy
  static const primary       = Color(0xFFFFC42E); // الأصفر الأساسي (الأزرار، الهيدر)
  static const primaryDark   = Color(0xFFF5A800); // عند الضغط / التدرّج
  static const primaryLight  = Color(0xFFFFE08A); // خلفيات خفيفة

  // Backgrounds — كريمي دافئ
  static const background    = Color(0xFFFFFDF6); // خلفية الشاشات
  static const surface       = Color(0xFFFFFFFF); // الكروت
  static const surfaceCream  = Color(0xFFFFF6E0); // كروت ثانوية

  // Text — بني داكن (مش أسود) عشان الدفء
  static const textPrimary   = Color(0xFF3D2B1F);
  static const textSecondary = Color(0xFF8A7560);
  static const onPrimary     = Color(0xFF2E1F14); // نص فوق الأصفر

  // Dark mode (زي داشبورد التاجر الداكن في الصور)
  static const darkBg        = Color(0xFF121212);
  static const darkSurface   = Color(0xFF1E1E1E);
  static const gold          = Color(0xFFE6B422); // accent ذهبي للداكن

  // Semantic
  static const success = Color(0xFF34C759);
  static const warning = Color(0xFFFF9F0A);
  static const error   = Color(0xFFFF3B30);
  static const info    = Color(0xFF5AC8FA);

  // المستويات (Loyalty Levels)
  static const bronze   = Color(0xFFCD7F32);
  static const silver   = Color(0xFFB0B0B0);
  static const goldTier = Color(0xFFFFD700);
  static const platinum = Color(0xFFE5E4E2);
}
```

### الخطوط (Typography)
- **العربي:** `Tajawal` (الأساسي) — نظيف وعصري ويدعم الأوزان. بديل: `Cairo`.
- **الإنجليزي/الأرقام:** نفس Tajawal يدعمهم، أو `Inter` للأرقام.
- المقاسات: Display 28/Bold · H1 24/Bold · H2 20/SemiBold · Body 16/Regular · Caption 13/Regular.
- **مهم:** استخدم `GoogleFonts.tajawalTextTheme()` مع `MaterialApp(locale: ar, textDirection: rtl)`.

### المكوّنات (Components) — شكل Hatchy
- **الكروت:** `borderRadius: 24`, ظل ناعم `BoxShadow(blur: 20, color: black.opacity(0.05))`, خلفية بيضا/كريمي.
- **الأزرار الأساسية:** خلفية صفرا `primary`, نص بني داكن `onPrimary`, `borderRadius: 28` (شبه pill), padding مريح، أيقونة على الجنب.
- **الأزرار الثانوية:** outline بني فاتح أو خلفية كريمي.
- **الأيقونات داخل دوائر صفرا:** زي الصور (النجمة/الكأس/القبعة فوق الكروت).
- **الكتكوت (Mascot):** يظهر في: Splash · Onboarding · الحالات الفاضية (Empty States) · النجاح. (الأصول: تُولّد بـ AI مرة واحدة — راجع قسم الأصول).
- **الأنيميشن:** بسيط ومبهج — `flutter_animate` (مجاني) للـ micro-interactions.

### قواعد عامة (تمشي على كل الشاشات)
- **RTL في كل حاجة** من أول يوم. الأيقونات الاتجاهية تتعكس، النصوص تتحاذى يمين.
- **اللغة:** عربي افتراضي + إنجليزي اختياري في الإعدادات (يقلب الـ layout لـ LTR).
- **حالات كل شاشة إجبارية:** Loading (skeleton مش شاشة بيضا) · Empty (رسمة كتكوت + نص ودود + زرار) · Error (رسالة + إعادة المحاولة) · Success (toast/شاشة تأكيد بعلامة صح).
- **الأذونات:** ممنوع طلب إذن (كاميرا/إشعارات/موقع) من غير شاشة تمهيدية تشرح "ليه" قبله.
- **الأوفلاين:** bar علوي "لا يوجد اتصال بالإنترنت".
- **انتهاء الجلسة:** لو التوكن خلص → رجوع لشاشة الدخول برسالة "انتهت الجلسة، الرجاء تسجيل الدخول".

### الأصول (Assets) — مجانية
- **الماسكوت (الكتكوت):** يُولّد مرة واحدة بـ AI (مثلًا عبر أداة توليد صور) بعدة حالات (يلوّح / يقرأ / يحتفل / فاضي) ويُصدّر PNG شفاف. تكلفة لمرة واحدة.
- استخدم **SVG** للأيقونات (أخف) و **WebP** للصور (أصغر من PNG بـ 30%).
- **اضغط كل صورة قبل الرفع** (`flutter_image_compress`) — بيوفّر Storage و bandwidth.

---

## 5. قاعدة البيانات (Database Schema — Postgres)

> كل الجداول فيها `id uuid default gen_random_uuid()` و `created_at timestamptz default now()`.
> العزل عبر RLS على `merchant_id`. الأنواع تقريبية.

### الجداول الأساسية

| الجدول | الوصف | مفاتيح/قيود مهمة |
|---|---|---|
| `users` | المستخدمين (العملاء) | `phone` فريد (التسجيل بيه) · `qr_secret` (لتوليد QR متغيّر) · `referral_code` · `referred_by` · `push_opt_in` · `proximity_opt_in` · `date_of_birth` (حسّاس — PDPL) |
| `device_tokens` | توكنات FCM للإشعارات | `user_id` · `token` · `platform` (ios/android) |
| `merchants` | التجار | `business_name` · `business_type` · `status` (pending/approved/rejected/suspended) · `logo_url` |
| `branches` | الفروع | `merchant_id` · `lat`/`lng` · `geofence_radius_m` (افتراضي 150) · `active` |
| `subscriptions` | الاشتراكات (تُملأ يدويًا) | `merchant_id` · `plan` (trial/monthly/yearly) · `status` · `trial_ends_at` · `current_period_end` |
| `merchant_staff` | الموظفين | `merchant_id` · `branch_id` · `role` (manager/cashier/branch_manager) · `status` |
| `user_stores` ⭐ | **محفظة العميل عند كل فرع** | `(user_id, merchant_id, branch_id)` **فريد** · `available_points` · `lifetime_points` (لا تُخصم أبدًا → تحدد المستوى) · `current_level_id` |
| `visit_campaigns` | حملات الزيارة | `required_visits` · `reward_name`/`reward_image_url` · `active` |
| `user_visits` | سجل الزيارات | `(user_id, merchant_id, visit_date)` **فريد** (يمنع زيارتين في اليوم) · `branch_id` · `source` (qr_scan/gps_checkin) · `scanned_by_staff_id` |
| `points_transactions` | سجل النقاط | `user_store_id` · `type` (earn/redeem/adjust) · `points` · `staff_id` · `reason` · `branch_id` |
| `rewards` | المكافآت | `merchant_id` · `points_cost` · `stock_qty` (null = غير محدود) · `active` |
| `reward_redemptions` | سجل الاستبدال | `user_id`/`merchant_id`/`reward_id` · `points_spent` · `staff_id` · `branch_id` |
| `loyalty_levels` | المستويات | `name` · `threshold_lifetime_points` · `sort_order` |
| `coupons` | الكوبونات | `code` (فريد لكل تاجر) · `type` (percent/fixed/free_item) · `value` · `valid_from`/`valid_to` · `usage_limit`/`per_user_limit` |
| `coupon_redemptions` | استبدال الكوبونات | `coupon_id` · `user_id` · `staff_id` |
| `referrals` | الإحالات | `referrer_id` · `referee_id` · `status` (pending/qualified/rewarded) · `qualifying_event` · `reward_granted_at` |
| `proximity_notifications_log` | منع تكرار إشعار القرب | `user_id`/`merchant_id`/`branch_id` · `last_notified_at` (للـ cooldown) |
| `notifications` | الإشعارات داخل التطبيق | `user_id` · `type` · `title` · `body` · `data` (jsonb) · `read_at` |
| `merchant_settings` ⭐ | **كل خيارات التاجر** (White-Label) | `points_scope` (merchant/branch) · `enable_*` (تفعيل الميزات) · السقوف · `qr_rotation_seconds` · `primary_color_hex` |
| `merchant_questions` | أسئلة التاجر (بنقاط) | `type` (single_choice/multi_choice/text) · `points_reward` · `active` |
| `question_options` | خيارات السؤال | `question_id` · `label` |
| `question_responses` | إجابات العملاء | `(question_id, user_id)` **فريد** · `answer_text` / `selected_option_ids` · `points_awarded` |

**خيارات التاجر (`merchant_settings`):** التاجر يتحكّم في كل حاجة — نطاق النقاط (مشترك/منفصل لكل فرع)، تفعيل/تعطيل أي ميزة، حدود الأمان، تجدّد الـ QR، والعلامة. الدالة `get_or_create_wallet()` تحلّ المحفظة الصحيحة حسب `points_scope`.

**لوحات الصدارة (دوال):** `global_leaderboard()` (كل التطبيق) · `store_leaderboard(merchant, branch)` (الستور كامل لو branch=NULL أو فرع محدد) · `my_global_rank()`. تحترم `users.leaderboard_opt_in`.

**عرض العملاء + الإشعارات بحد أقصى (يحدّده مالك النظام):**
- التاجر يعرض عملاءه بكل خصائصهم عبر `merchant_customers(merchant, search, limit, offset)` (محمي بعضوية التاجر، مجمّع عبر الفروع).
- إرسال إشعار جماعي عبر `send-announcement` — يفرض **حدًّا شهريًا** يحدّده مالك المنصة في `platform_settings.default_notifications_monthly_quota` (افتراضي عام) أو `merchant_limits.notifications_monthly_quota` (override لكل تاجر). جدولان **يكتب فيهما الأدمن فقط** (service_role)، والتاجر يقرأ حدّه فقط.
- الاستهلاك المتبقّي: `merchant_notification_usage(merchant)` ويُسجّل كل إرسال في `notification_campaigns`.

**أداء:** `dashboard_summary(merchant, branch)` يحسب كل مقاييس لوحة التحكم في **استدعاء واحد** بدل ~10 round-trips (تجميع على السيرفر بدل سحب كل الصفوف للعميل). شاشة الـ QR تعيد توليد الكود فقط عند تغيّر النافذة الزمنية (مش كل ثانية). فهارس إضافية لأنماط الاستعلام الفعلية.

### منطق النقاط (Source of Truth)
- `earn` → يزوّد `available_points` **و** `lifetime_points` بنفس القيمة.
- `redeem` → يخصم من `available_points` **فقط** (lifetime ثابت).
- `adjust` → تعديل يدوي بصلاحية.
- **حساب المستوى:** بعد كل `earn` يقارن `lifetime_points` بالعتبات → لو عدّى عتبة جديدة يطلّعه مستوى أعلى **بدون خصم**.

### قرار الفروع ✅ (مُتخذ: منفصلة لكل فرع)
- المحفظة **منفصلة لكل فرع** — مفتاح = `(user_id, merchant_id, branch_id)`.
- يعني العميل عنده رصيد ومستوى مختلف عند كل فرع للتاجر نفسه. الزيارة والنقاط والاستبدال بتتقيّد كلها بـ `branch_id` بتاع الفرع اللي حصلت فيه.
- **نتيجة مهمة:** كل مسح QR لازم يحدّد الفرع الحالي للكاشير (من `merchant_staff.branch_id`) قبل أي عملية. ولو العميل أول مرة في الفرع ده → تتعمل محفظة جديدة (`user_stores` صف جديد) حتى لو عنده محفظة في فرع تاني لنفس التاجر.

> ملف SQL كامل للجداول + الفهارس + RLS جاهز في `sql/schema.sql` — يُنفّذ مباشرة على Supabase (SQL Editor). راجع المخطط البصري على dbdiagram.io.

---

## 6. الأمان ومنع التلاعب (الجزء ده مش اختياري)

| التهديد | الحل |
|---|---|
| **QR مسروق/سكرين شوت** | الـ QR **متغيّر** — التطبيق يولّد توكن عمره 30-60 ثانية (TOTP-style من `qr_secret`)، والسيرفر يتأكد منه عبر Edge Function. السكرين شوت القديم يترفض. |
| **زيارتين في نفس اليوم** | قيد فريد `(user_id, merchant_id, visit_date)` على مستوى الـ DB — مش مجرد فحص في الكود. |
| **موظف بيضخّ نقاط** | سقف للعملية الواحدة + سقف يومي لكل موظف ولكل تاجر + تنبيه لو اتعدّى. كله يتفرض في الـ Edge Function. |
| **موظف مش أمين يفضّي حساب** | تأكيد الطرفين للاستبدال: العميل يبدأ، والكاشير يأكّد، والخصم يحصل لحظة التأكيد فقط (راجع شاشة "أرِ للكاشير"). |
| **تزوير GPS** | تزييف الموقع سهل → الـ check-in **مساعد فقط**، مش إثبات قوي، وبدون مكافآت قيّمة مربوطة بيه لوحده. للإثبات القوي: اربطه بمسح QR. |
| **إحالة وهمية / إحالة ذاتية** | مكافأة الإحالة ما تُصرف إلا بعد حدث مؤهّل حقيقي (أول عملية متأكد منها) + تأكيد رقم الجوال (OTP). |
| **عزل التجار** | RLS على كل جدول: التاجر يشوف صفوف `merchant_id` بتاعه بس. مفروض على الـ DB. |
| **التوكنات** | Supabase JWT عمره قصير + refresh token تلقائي. تُخزّن في `flutter_secure_storage` (Keychain/Keystore). |

### قاعدة RLS (نموذج للعزل)
```sql
-- مثال: التاجر يشوف مكافآته فقط
alter table rewards enable row level security;
create policy "merchant_owns_rewards" on rewards
  for all using (
    merchant_id in (
      select merchant_id from merchant_staff
      where user_id = auth.uid()
    )
  );
```

### PDPL (حماية البيانات السعودية)
- موافقة صريحة منفصلة على: **الموقع** و **تاريخ الميلاد** (بيانات حساسة).
- "حذف الحساب" إجباري قانونيًا (شاشة تأكيد قوية + حذف فعلي للبيانات).
- سياسة خصوصية واضحة: إيه اللي بيتخزّن، وقد إيه، وليه.
- الموقع في الخلفية (proximity) أصعب إذن — اطلبه فقط بعد أول متجر يتضاف، واشرح السبب.

---

## 7. الـ Edge Functions (المنطق الحسّاس — Deno/TypeScript)

كل دالة بتفرض القواعد على السيرفر. التطبيق **بيستدعيها** ولا بيحسب حاجة بنفسه.

| الدالة | الوظيفة | يستدعيها |
|---|---|---|
| `verify-qr` | يتأكد من توكن الـ QR المتغيّر ويرجّع `user_id` | تطبيق التاجر (بعد المسح) |
| `record-visit` | يسجّل زيارة (يفرض قاعدة زيارة/يوم) | تطبيق التاجر |
| `add-points` | يضيف نقاط (يفرض السقوف + يحدّث المستوى) | تطبيق التاجر |
| `redeem-reward` | يبدأ استبدال (ينشئ كود مؤقت 5 دقائق) | تطبيق العميل |
| `confirm-redemption` | الكاشير يأكّد → الخصم الفعلي يحصل هنا (المحفظة حسب فرعه) | تطبيق التاجر |
| `answer-question` | العميل يجاوب سؤال التاجر → ياخد النقاط المحدّدة | تطبيق العميل |
| `send-announcement` | التاجر يبعت إشعار جماعي لعملائه — **يفرض الحد الشهري** اللي حدّده مالك المنصة | تطبيق التاجر |
| `apply-coupon` | يتحقق ويطبّق كوبون | تطبيق التاجر |
| `link-store` | ربط العميل بالتاجر تلقائيًا أول مسح | داخل `verify-qr` |
| `process-referral` | يفعّل مكافأة الإحالة بعد الحدث المؤهّل | trigger داخلي |
| `send-push` | يبعت إشعار FCM (خدمة إرسال واحدة + قوالب) | كل الدوال اللي فوق |
| `cron-birthday-rewards` | pg_cron يومي → مكافأة عيد ميلاد + إشعار | مجدول (pg_cron) |
| `cron-expire-coupons` | pg_cron → إلغاء الكوبونات المنتهية | مجدول |
| `cron-trial-reminders` | تذكير قرب انتهاء التجربة | مجدول |

### الإشعارات (Push) — البنية
- **FCM** لأندرويد و iOS (مجاني). التوكنات في `device_tokens` مع `opt-in`.
- خدمة إرسال **واحدة** (`send-push`) + **قوالب** للأنواع: نقاط اتضافت / مكافأة جاهزة / طلعت مستوى / مكافأة قربت تخلص / إعلان من التاجر / قريب من متجر / هدية ميلاد.
- كل إشعار يُسجّل كمان في جدول `notifications` (عشان يظهر في تاب الإشعارات داخل التطبيق).

---

## 8. النطاق والمراحل (Scope & Phasing)

### المرحلة 1 — النواة (MVP — أطلق بيها)
تطبيق العميل (تسجيل/QR/متاجر/صفحة المتجر) + تطبيق التاجر (تسجيل/Scanner/إدارة) + Backend + لوحة أدمن بسيطة + Push + GPS Check-in (مساعد).

### المرحلة 2 — بعد ثبات النواة
Referral · Coupons · Birthday Rewards · Multi-Branch · Analytics Dashboard · Proximity Notifications.

### المرحلة 3 — بعدين (مؤجّلة)
POS Integration (مهم — API للـ POS) · AI Insights · Marketing Campaigns · WhatsApp · بوابة الدفع.

> **نصيحة خبير:** متبنيش Referral/Coupons قبل ما يكون فيه ناس فعلًا بتستخدم وبتدفع. قيمتها صفر وانت عند صفر مستخدم. ركّز النواة على: **العميل يجمّع، الكاشير يمسح ويضيف بسرعة.**

### تقدير زمني تقريبي (مطوّر Flutter واحد + Backend)
| المرحلة | المدة التقريبية |
|---|---|
| إعداد المشروع + Design System + Auth + DB + RLS | 2-3 أسابيع |
| تطبيق العميل (نواة) | 3-4 أسابيع |
| تطبيق التاجر (نواة) | 3-4 أسابيع |
| Edge Functions + Push + اختبار | 2 أسابيع |
| لوحة أدمن بسيطة + إطلاق | 1-2 أسبوع |
| **الإجمالي للنواة** | **~11-15 أسبوع** |

---

## 9. معايير القبول (Acceptance) — إزاي نعرف إنه اشتغل صح

- **ربط المتجر:** مسح QR لعميل جديد يظهّر المتجر فورًا عنده وعند التاجر.
- **سقف الزيارة:** محاولة زيارتين لنفس العميل في نفس اليوم → التانية تترفض.
- **النقاط:** `earn` بـ100 يزوّد available و lifetime بـ100. `redeem` بـ100 يخصم من available بس و lifetime زي ما هو.
- **المستوى:** تعدية عتبة تطلّع المستوى بدون خصم.
- **العزل:** تاجر (أ) ما يشوفش بأي API داتا تاجر (ب).
- **الـ QR المتغيّر:** سكرين شوت قديم يترفض بعد انتهاء عمره.
- **الـ GPS:** check-in بره النطاق يترفض، وزيارة GPS ما تعدّيش سقف اليوم.
- **الإحالة:** المكافأة ما تُصرف إلا بعد الحدث المؤهّل.
- **إشعار القرب:** إشعار مرة واحدة في حدود الـ cooldown، ومفيش إشعارات لمتاجر مش في القايمة.

---

## 10. هيكل مجلدات Flutter (Feature-First + Clean)

```
lib/
├── main.dart
├── core/
│   ├── theme/            # app_colors, app_typography, app_theme
│   ├── config/           # supabase/firebase init, env
│   ├── router/           # go_router
│   ├── network/          # supabase client wrapper, error handling
│   ├── localization/     # ar/en, RTL
│   └── widgets/          # أزرار/كروت/حالات (Empty/Error/Loading) مشتركة
├── features/
│   ├── auth/             # data / domain / presentation
│   ├── stores/           # (العميل) أو merchants/management (التاجر)
│   ├── qr/  أو  scanner/
│   ├── rewards/
│   ├── notifications/
│   └── profile/
└── shared/
    └── models/           # freezed models متطابقة مع جداول الـ DB
```

> نفس البنية في التطبيقين عشان إعادة الاستخدام والتعلّم. الـ `core/` و `shared/models/` ممكن يتحطوا في **package محلي مشترك** (`packages/loyalty_core`) يستوردوه التطبيقين — توفير ضخم في الصيانة.

---

## 11. ملفات المشروع

```
loyalty-platform/
├── 00_PROJECT_OVERVIEW.md   ← (هذا الملف) الأساس المشترك
├── CUSTOMER_APP.md          ← تطبيق العميل (شاشة بشاشة)
├── MERCHANT_APP.md          ← تطبيق التاجر (شاشة بشاشة)
└── sql/
    └── schema.sql           ← جداول + فهارس + RLS (جاهز للتنفيذ على Supabase)
```
