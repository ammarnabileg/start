# Customer App — تقرير التدقيق البصري (UX / QA)

تطبيق العميل (Hatchy): Flutter · RTL عربي · go_router + Riverpod + Supabase.
الصور المرقّمة في هذا المجلد بترتيب رحلة المستخدم الفعلية.

## نموذج التوجيه (Routing)
`lib/router.dart` يعرّف **٦ مسارات تصريحية فقط**؛ معظم الشاشات تُفتح عبر
`Navigator.push(MaterialPageRoute(...))` لا عبر مسار go_router.

| Path | Widget | ملاحظة |
|---|---|---|
| `/splash` | `SplashScreen` | نقطة البداية |
| `/welcome` | `WelcomeScreen` | — |
| `/` | `QrHomeScreen` | تبويب 0 داخل `HomeShell` |
| `/stores` | `MyStoresScreen` | تبويب 1 |
| `/notifications` | `NotificationsScreen` | تبويب 2 |
| `/profile` | `ProfileScreen` | تبويب 3 |

**الحارس (Guard):** بلا جلسة Supabase وخارج `/welcome` أو `/splash` → تحويل إلى
`/welcome`. لا توجد حُرّاس أدوار (فصل الأدوار في تطبيق التاجر). التبويبات الأربعة
داخل `StatefulShellRoute.indexedStack`.

> **ثغرة توجيه:** `redirect` يفحص `currentSession != null` بشكل متزامن دون انتظار
> استعادة الجلسة، فبدء بارد بجلسة صالحة قيد الاستعادة قد يُقيَّم لحظيًّا كـ«غير
> مسجَّل» ويُحوّل للترحيب.

---

## 001 — Splash
- **Page Name:** شاشة البداية / `SplashScreen` (`splash/splash_screen.dart:11`)
- **Route:** `/splash`
- **User Role:** عام (قبل الدخول)
- **Screenshot:** `001-Splash.png`
- **Purpose:** شاشة إقلاع تقرّر مسار الدخول.
- **Available Actions:** لا أزرار؛ تحويل تلقائي بعد 600ms (مسجَّل → `/` ، أول مرة →
  `Onboarding` ، غير ذلك → `/welcome`).
- **Notes:** اعتماد على `FlutterSecureStorage` لقراءة `seen_onboarding`.
- **Potential UX Issues:** تأخير ثابت 600ms مهما كانت سرعة المصادقة؛ لا إعادة محاولة.
- **Potential Bugs:** قراءة التخزين (سطر 31) قد ترمي استثناءً غير مُلتقَط → توقف صامت
  على الـSplash بلا واجهة خطأ. خلط أنماط التنقّل (`push` للـOnboarding مقابل `go`).

## 002 — Welcome
- **Page Name:** أهلًا بك / `WelcomeScreen` (`auth/welcome_screen.dart:9`)
- **Route:** `/welcome`
- **User Role:** عام
- **Screenshot:** `002-Welcome.png`
- **Purpose:** مركز المصادقة.
- **Available Actions:** «إنشاء حساب جديد» → `RegisterScreen` ؛ «تسجيل الدخول» → `LoginScreen`.
- **Notes / UX / Bugs:** ساكنة بلا async — لا ملاحظات جوهرية.

## 003 — Onboarding
- **Page Name:** الجولة التعريفية / `OnboardingScreen` (`onboarding/onboarding_screen.dart:9`)
- **Route:** يُفتح بـ`push` من Splash (خارج go_router)
- **User Role:** عام (أول تشغيل)
- **Screenshot:** `003-Onboarding.png`
- **Purpose:** تعريف بالمزايا في 3 صفحات.
- **Available Actions:** «تخطّي» / «التالي» / «ابدأ الآن» / سحب PageView + مؤشّر نقاط.
- **Potential UX Issues:** الإنهاء دائمًا يذهب للترحيب حتى لو وُجدت جلسة؛ النقاط غير قابلة للنقر.
- **Potential Bugs:** لا معالجة لزر الرجوع.

## 004 — Login
- **Page Name:** تسجيل الدخول / `LoginScreen` (`auth/login_screen.dart:13`)
- **Route:** `push` من Welcome
- **User Role:** عام
- **Screenshot:** `004-Login.png` *(صُحِّحت لتطابق الواقع: هاتف + كلمة مرور)*
- **Purpose:** دخول برقم الجوال + كلمة المرور.
- **Available Actions:** حقل الجوال (أرقام، 10)، حقل كلمة المرور + زر الإظهار، «نسيت كلمة
  المرور؟» → `ForgotPasswordScreen`، «تسجيل الدخول» → `_submit`، إرسال من الكيبورد.
- **Forms/Validation:** هاتف صالح = 10 أرقام تبدأ `05` أو 9 تبدأ `5`؛ كلمة المرور غير فارغة.
  الزرّ مُعطَّل حتى تصحّ الحقول. لا أخطاء حقول داخلية (تعطيل صامت فقط).
- **Potential UX Issues:** لا تغذية راجعة للخطأ أثناء الكتابة (تعطيل الزر فقط).
- **Potential Bugs:** `onSubmitted` يُعيد Future غير مُنتظَر (منخفض الخطورة). فحوص `mounted` سليمة.

## 005 — Register
- **Page Name:** إنشاء حساب جديد / `RegisterScreen` (`auth/register_screen.dart:12`)
- **Route:** `push` من Welcome
- **User Role:** عام
- **Screenshot:** `005-Register.png` *(صُحِّحت: + بريد + كلمة مرور + موافقة الشروط)*
- **Purpose:** إنشاء حساب يُطلق OTP.
- **Available Actions:** الاسم/الجوال/البريد(اختياري)/كلمة المرور(+شريط قوة)/تاريخ الميلاد
  (`showDatePicker`)/مربّع الشروط/«إنشاء الحساب» → إرسال OTP ثم `OtpScreen`.
- **Forms/Validation:** اسم ≥2؛ هاتف كما في الدخول؛ بريد اختياري مع `errorText` داخلي؛ كلمة
  مرور ≥8 مع قوة 0-4؛ يجب قبول الشروط.
- **Modals:** منتقي التاريخ.
- **Potential UX Issues:** مربّع الشروط **بلا رابط فعلي** للوثائق؛ خطأ البريد يظهر قبل
  إكمال النطاق (بلا debounce).
- **Potential Bugs:** فحوص `mounted` حاضرة.

## 006 — OTP
- **Page Name:** رمز التحقق / `OtpScreen` (`auth/otp_screen.dart:16`) — **6 خانات**
- **Route:** `pushReplacement`/`push` من Register/Login
- **User Role:** عام
- **Screenshot:** `006-OTP.png` *(صُحِّحت: 6 خانات / 30 ثانية)*
- **Purpose:** تحقق الهاتف؛ عند التسجيل يُنشئ الملف ويعيّن كلمة المرور.
- **Available Actions:** 6 خانات بتقدّم تلقائي/لصق موزّع/رجوع بالحذف؛ «تأكيد»؛ إعادة إرسال
  بعدّاد 30 ثانية.
- **Potential UX Issues:** تحقق تلقائي عند الخانة السادسة — لصق خاطئ يُطلق محاولة فاشلة فورًا.
- **Potential Bugs:** `_OtpBox` ينشئ `FocusNode` في `build` ولا يتخلّص منه (سطر 309) — تسرّب
  بسيط. فشل `updatePassword` يُبتلع بصمت (سطر 151) → قد يتعذّر الدخول بكلمة المرور لاحقًا.

## 007 — Forgot Password
- **Page Name:** استعادة الحساب / `ForgotPasswordScreen`
- **Route:** `push` من Login
- **User Role:** عام
- **Screenshot:** `007-Forgot-Password.png`
- **Purpose:** إرسال رمز لإعادة تعيين كلمة المرور.
- **Available Actions:** حقل الجوال + «إرسال الرمز».
- **Notes:** يتبع تدفّق OTP لإعادة التعيين.

## 008 — Notification Permission (Priming)
- **Page Name:** خلّيك على اطّلاع / `NotificationsPrimingScreen` (`auth/notifications_priming_screen.dart:9`)
- **Route:** `pushReplacement` من OTP (تدفّق التسجيل)
- **User Role:** مستخدم جديد
- **Screenshot:** `008-Notification-Permission.png`
- **Purpose:** تمهيد لطلب إذن الإشعارات.
- **Available Actions:** «تفعيل الإشعارات» → `PushService.registerForUser()` ثم `/` ؛ «ليس الآن» → `/`.
- **Potential UX Issues:** لا مؤشّر تحميل أثناء التسجيل (قد يبدو غير مستجيب).

## 009 — Location Permission (Priming)
- **Page Name:** نبّهك وأنت قريب / `LocationPrimingScreen` (`auth/location_priming_screen.dart:7`)
- **Route:** `push` من **الإعدادات** عند أول تفعيل للقرب
- **User Role:** مستخدم مسجَّل
- **Screenshot:** `009-Location-Permission.png`
- **Purpose:** تمهيد لإذن الموقع.
- **Available Actions:** «تفعيل» / «لاحقًا».
- **Potential Bugs (مهم):** زرّ «تفعيل» **زرّ ميّت** — جسده تعليق `TODO` ويكتفي بـ`pop`
  (سطور 10-15). الطلب الفعلي للإذن وكتابة `proximity_opt_in` يحدثان في الإعدادات،
  فالزرّ يَعِد بفعلٍ لا ينفّذه.

## 010 — Home (QR) — تبويب «رمزي»
- **Page Name:** الرئيسية / `QrHomeScreen` (`qr/qr_home_screen.dart:13`)
- **Route:** `/`
- **User Role:** مستخدم مسجَّل
- **Screenshot:** `010-Home-QR.png`
- **Purpose:** هوية QR دوّارة للكاشير، بأقصى سطوع، تعمل بلا إنترنت.
- **Available Actions:** «ربط إحالة» → `ReferralLinkSheet` (bottom sheet)؛ حلقة عدّ تنازلي
  تتجدّد كل ثانية.
- **States:** loading / error (+ retry) / data — تغطية جيدة.
- **Notes:** ضبط السطوع إلى 1.0 واستعادته عبر `WidgetsBindingObserver` — دورة حياة سليمة.
- **Potential Bugs:** `user.id.substring(0,8)` يفترض طول ≥8 (UUID آمن)؛ أخطاء السطوع مُبتلعة (مقبول).

## 011 — My Stores — تبويب «متاجري»
- **Page Name:** متاجري / `MyStoresScreen` (`stores/my_stores_screen.dart:26`)
- **Route:** `/stores`
- **User Role:** مستخدم مسجَّل
- **Screenshot:** `011-My-Stores.png`
- **Purpose:** قائمة محافظ المتاجر المرتبطة + ملخّص إجمالي النقاط.
- **Available Actions:** سحب للتحديث؛ نقر البطاقة → `StoreDetailScreen`؛ نجمة المفضّلة (تحديث تفاؤلي).
- **States:** Skeleton / Error+retry / Empty («لا توجد متاجر بعد») / data — **تغطية كاملة**.
- **Potential UX Issues:** المتاجر غير المتاحة معتّمة (0.6) لكنها قابلة للنقر.

## 012 — Notifications — تبويب
- **Page Name:** الإشعارات / `NotificationsScreen` (`notifications/notifications_screen.dart:24`)
- **Route:** `/notifications`
- **User Role:** مستخدم مسجَّل
- **Screenshot:** `012-Notifications.png`
- **Purpose:** خلاصة إشعارات مُرقّمة الصفحات؛ تُعلَّم كمقروءة عند أول تحميل.
- **Available Actions:** سحب للتحديث + تمرير لا نهائي. **البطاقات غير قابلة للنقر** (لا توجيه).
- **Potential UX Issues:** «تعليم الكل مقروء» عند الفتح يمنع تمييز غير المقروء لاحقًا؛ لا حالة
  مقروء لكل عنصر، لا سحب-للحذف، لا فعل عند النقر — إشعارات تعريفية فقط.
- **Potential Bugs:** `markAllRead()` يُستدعى داخل بنّاء المزوّد كأثر جانبي (سطور 16-18) — قد
  يُومِض شارة العدّاد.

## 013 — Profile — تبويب «حسابي»
- **Page Name:** حسابي / `ProfileScreen` (`profile/profile_screen.dart:16`)
- **Route:** `/profile`
- **User Role:** مستخدم مسجَّل
- **Screenshot:** `013-Profile.png`
- **Purpose:** ملف المستخدم + مداخل التنقّل.
- **Available Actions:** تعديل الملف / لوحة الصدارة العامة / هداياي / دعوة صديق / بلاغاتي /
  الإشعارات والخصوصية / تسجيل الخروج / **حذف الحساب** (AlertDialog).
- **States:** Skeleton / Error (**بلا retry** — سطر 57) / data.
- **Modals:** حوار حذف الحساب.
- **Potential Bugs:** حالة الخطأ تفتقر زر إعادة المحاولة (خلافًا لبقية الشاشات).

## 014 — Edit Profile
- **Page Name:** تعديل الملف / `EditProfileScreen` (`profile/edit_profile_screen.dart:12`)
- **Route:** `push` من Profile
- **Screenshot:** `014-Edit-Profile.png`
- **Purpose:** تعديل الصورة/الاسم/البريد/الميلاد.
- **Available Actions:** اختيار/رفع الصورة (+سبينر)، الحقول، «حفظ» → `updateProfile` ثم إبطال المزوّد.
- **Forms/Validation:** اسم ≥2؛ بريد اختياري مع `errorText`.
- **Potential Bugs:** بذر القيم مرة واحدة (`_initialized`)؛ صورة فاشلة الحفظ تبقى حتى إعادة التحميل.

## 015 — Settings (الإشعارات والخصوصية)
- **Page Name:** الإشعارات والخصوصية / `SettingsScreen` (`profile/settings_screen.dart:20`)
- **Route:** `push` من Profile
- **Screenshot:** `015-Settings.png`
- **Purpose:** مفاتيح الإشعارات/القرب/ظهور الصدارة/المشاركة + سياسة الخصوصية + اللغة + تصدير + حذف.
- **Available Actions:** مفاتيح Switch (push/proximity/leaderboard/share)؛ سياسة الخصوصية
  (`launchUrl`)؛ منتقي اللغة (bottom sheet)؛ «تصدير بياناتي» (`Share.shareXFiles`)؛ حذف الحساب.
- **Modals:** ورقة اللغة، حوار الحذف.
- **States:** Skeleton / Error+retry / data (المفاتيح مُعطَّلة أثناء `_busy`).
- **Potential Bugs:** `_onProximityChanged` ينتظر `push` التمهيد **دون فحص `context.mounted`**
  قبل `_updateFlag` (سطور 56-60). ولأن زر التمهيد ميّت (#009) فالإذن لا يُطلب فعليًا وقد
  يعمل `ProximityService.start()` بلا إذن نظام.

## 016 — Store Detail
- **Page Name:** صفحة المتجر / `StoreDetailScreen` (`stores/store_detail_screen.dart:95`)
- **Route:** `push` من بطاقة المتجر
- **Screenshot:** `016-Store-Detail.png`
- **Purpose:** تفاصيل المتجر بتبويبات **ديناميكية** حسب `MerchantSettings` (حتى 9 تبويبات).
- **Available Actions:** انظر تبويبات 018-026 أدناه.
- **Modals/Sheets:** حوار كود الكوبون (920)؛ ورقة التقييم `_RatingSheet` (1464)؛ حوار حذف التقييم (1413).

## 017 — Store Levels
- **Page Name:** مستويات المتجر / `_LevelsTab` (`store_detail_screen.dart:727`)
- **Screenshot:** `017-Store-Levels.png`
- **Purpose:** رحلة المستويات + بطاقات المزايا (`LevelsJourney`).

## 018–026 — Store Detail Tabs
| # | الشاشة | الويدجت | إجراءات/حالات بارزة |
|---|---|---|---|
| 018 | نظرة عامة | `_OverviewTab` (203) | StatCards؛ «صدارة المتجر»؛ بطاقة عجلة الحظ؛ بطاقة تقدّم الإحالة؛ مفتاح المشاركة |
| 019 | الزيارات | `_VisitsTab` (481) | loading/error+retry/empty/data؛ بطاقات أختام؛ سحب للتحديث |
| 020 | النقاط | `_PointsTab` (515) | «استبدل نقاطك» → `animateTo(3)` |
| 021 | المكافآت | `_RewardsTab` (571) | شبكة؛ «استبدال» → `RewardDetailScreen`؛ منطق متاح/نفد/غير متاح |
| 022 | المستويات | `_LevelsTab` (727) | `LevelsJourney` + مزايا |
| 023 | الكوبونات | `_CouponsTab` (830) | «استخدام» → حوار يعرض الكود للكاشير |
| 024 | الأسئلة | `_QuestionsTab` (948) | نماذج single/multi/text → نقاط |
| 025 | التقييمات | `_ReviewsTab` (1163) | ملخّص؛ «قيّم» → `_RatingSheet`؛ تعديل/حذف تقييمي |
| 026 | السجل | `_HistoryTab` (1595) | معاملات مُرقّمة الصفحات |

- **Forms/Validation:** `_QuestionCard`: single=اختيار، multi=≥1، text=غير فارغ؛ خطأ الخادم في
  `_error`؛ بعد الإجابة «تمت الإجابة ✓». `_RatingSheet`: تقييم ≥1 نجمة («اختر تقييمًا من 1 إلى 5»).
- **Potential Bugs (مهم):**
  - **020 «استبدل نقاطك» يثبّت `animateTo(3)`** بينما التبويبات شرطية؛ عند تعطيل الزيارات
    يصبح الفهرس 3 = المستويات/الكوبونات لا المكافآت → **يقفز للتبويب الخطأ** (سطر 561).
  - `DateTime.parse(validTo)` للكوبون (900) بلا حماية — يرمي عند تاريخ مشوّه من الخادم.
  - نقرة «عجلة الحظ» تنتقل حتى دون عجلة فعّالة (نقرة بلا أثر مرئي قبل الـEmptyView).

## 027 — Reward Detail
- **Page Name:** تفاصيل المكافأة / `RewardDetailScreen` (`rewards/reward_detail_screen.dart:10`)
- **Screenshot:** `027-Reward-Detail.png`
- **Available Actions:** «استبدال الآن» → حوار تأكيد → `redeemReward` (مع idempotency) → `ShowToCashierScreen`.
- **States:** متاح/نفد/«تحتاج X نقطة إضافية».
- **Potential Bugs:** يعالج `data==null || data['error']` (سطر 58)؛ `mounted` سليم.

## 028 — Show QR To Cashier
- **Page Name:** عرض للكاشير / `ShowToCashierScreen` (`rewards/show_to_cashier_screen.dart:12`)
- **Screenshot:** `028-Show-QR-To-Cashier.png`
- **Purpose:** QR موقَّع دوّار (`r1`) + عدّاد صلاحية + حالة حيّة مؤكَّد/منتهٍ.
- **States:** pending / confirmed (نجاح → 036) / expired.
- **Potential Bugs:** `redemption['redemption_id'] as String` (41) و`expires_at` (44) بلا حماية في
  `initState` — يرمي لو غابت من ردّ الـedge function.

## 029 — Lucky Wheel
- **Page Name:** عجلة الحظ / `WheelScreen` (`wheel/wheel_screen.dart:17`)
- **Screenshot:** `029-Lucky-Wheel.png`
- **Available Actions:** «لِف الآن» → `_spin` → نتيجة prize/points + تنقّل؛ «هداياي».
- **States:** loading/error+retry/empty/data.
- **Potential Bugs:** `wheelRepoProvider.currentUserId!` (74) **فكّ إجباري** — انقطاع الجلسة أثناء اللف = NPE.

## 030 — My Prizes
- **Page Name:** هداياي / `MyPrizesScreen` (`wheel/my_prizes_screen.dart:21`)
- **Screenshot:** `030-My-Prizes.png`
- **Available Actions:** قائمة مُرقّمة؛ نقر الجائزة → `PrizeQrScreen`؛ empty «لا توجد هدايا بعد».

## 031 — Prize QR
- **Page Name:** رمز الجائزة / `PrizeQrScreen` (`wheel/prize_qr_screen.dart:13`)
- **Screenshot:** `031-Prize-QR.png`
- **Purpose:** QR دوّار (`p1`) + حالة حيّة.
- **States:** pending / redeemed (نجاح) / expired.
- **Modals:** عند `delivering` → `_DeliverSheet` (غير قابلة للإغلاق/السحب: موافق/إلغاء/إبلاغ)؛
  مسار الإبلاغ → مسجّل فيديو ثم `_ReportSheet`.
- **Potential Bugs:** `_tick` يستدعي `setState` كل ثانية (مفحوص `mounted`).

## 032 — Leaderboard
- **Page Name:** لوحة الصدارة / `LeaderboardScreen` (`leaderboard/leaderboard_screen.dart:25`)
- **Screenshot:** `032-Leaderboard.png`
- **Purpose:** صدارة عامة (من الملف) أو لكل متجر (من نظرة عامة).
- **States:** loading/error+retry/empty/data — تغطية كاملة. يعالج <3 مشاركين.

## 033 — Referral
- **Page Name:** دعوة صديق / `ReferralScreen` (`referral/referral_screen.dart:18`)
- **Screenshot:** `033-Referral.png`
- **Available Actions:** «نسخ» (clipboard) / «مشاركة» (`Share.share`)؛ قائمة الإحالات بشارات
  pending/qualified/rewarded. **ورقة مرتبطة:** `ReferralLinkSheet` (من الرئيسية) — QR للكود +
  ربط بالكود + «امسح كود صديقك» (`ReferralScanScreen`) + «فكّ الارتباط».
- **States:** تغطية كاملة (loading/error+retry/empty/data).
- **Potential Bugs:** `ReferralScanScreen._onDetect` يستدعي `Navigator.pop` بلا فحص `mounted`
  (186) — خطر منخفض (يحرسه `_done`).

## 034 — Deliver Confirm (Sheet)
- **Page Name:** تأكيد التسليم / `_DeliverSheet` (داخل `prize_qr_screen.dart`)
- **Screenshot:** `034-Deliver-Confirm.png`
- **Purpose:** ورقة سفلية إجبارية عند تسليم جائزة: موافق/إلغاء/إبلاغ.

## 035 — Report Form (Sheet)
- **Page Name:** إبلاغ عن مشكلة / `_ReportSheet` (`prize_qr_screen.dart:369`)
- **Screenshot:** `035-Report-Form.png`
- **Forms/Validation:** «أضف رسالة أو فيديو» (واحد على الأقل، سطر 386)؛ يرفق المتجر/الفرع تلقائيًا.

## 036 — Redeemed Success
- **Page Name:** تم الاستلام / `_ResultView` (نجاح)
- **Screenshot:** `036-Redeemed-Success.png`
- **Purpose:** تأكيد بصري لإتمام الاستبدال/التسليم.

## 037 — Report Chat
- **Page Name:** محادثة البلاغ / `ReportChatScreen` (`reports/report_chat_screen.dart:13`)
- **Screenshot:** `037-Report-Chat.png`
- **Purpose:** محادثة ثلاثية الأطراف عبر `ReportChatView` المشترك.
- **Available Actions:** إرسال/تعديل/ردّ على رسالة.
- **Potential UX Issues:** كل إرسال/تعديل يُعيد جلب كامل الخيط (43-54) — وميض ولا تحديث تفاؤلي.

## 038–041 — Dispute Views (3 أطراف + قفز)
- **Screenshots:** `038-Dispute-Customer-View.png` · `039-Dispute-Merchant-View.png` ·
  `040-Dispute-Admin-View.png` · `041-Dispute-Jump-To-Message.png`
- **Purpose:** عرض النزاع من زاوية العميل/التاجر/الأدمن + ميزة «القفز للرسالة الأصلية».
- **Notes:** مولّدة من `disputes_preview_test.dart`؛ تُظهر اقتباس الرد والإبراز عند القفز.

---

## ملخّص الجودة (Cross-cutting)

**حالات ناقصة/ضعيفة**
- Splash بلا مسار خطأ (توقف صامت عند فشل التخزين/المصادقة).
- Profile حالة الخطأ بلا retry.
- Notifications بطاقات بلا تفاعل؛ «تعليم الكل مقروء» أثر جانبي في بناء المزوّد.

**أزرار ميّتة/مضلِّلة**
- `LocationPrimingScreen` زر «تفعيل» = `TODO` stub.
- `_PointsTab` «استبدل نقاطك» `animateTo(3)` يكسر عند إخفاء تبويبات شرطية.

**مخاطر null-safety / futures غير منتظَرة**
- `wheel_screen.dart:74` فكّ `currentUserId!`.
- `show_to_cashier_screen.dart:41,44` تحويلات/parse بلا حماية.
- `store_detail_screen.dart:900` `DateTime.parse(validTo)` بلا حماية.
- `otp_screen.dart:309` `FocusNode` غير مُتخلَّص منه في `build`.
- `referral_link_sheet.dart:186` `Navigator.pop` بلا فحص `mounted`.

**تطابق اللقطة ↔ الكود (صُحِّح في هذا التدقيق)**
- `004-Login`: كانت تعرض دخول OTP؛ صُحِّحت إلى هاتف+كلمة مرور.
- `006-OTP`: كانت 4 خانات/0:42؛ صُحِّحت إلى 6 خانات/0:30.
- `005-Register`: أُضيف البريد + كلمة المرور + موافقة الشروط.

**التحليل الساكن:** `flutter analyze` على `customer_app` = ✅ بلا مشاكل.
