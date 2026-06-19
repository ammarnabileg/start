# Merchant App — تقرير التدقيق البصري (UX / QA)

تطبيق التاجر (Hatchy Business): Flutter · RTL عربي · Riverpod + Supabase.
**لا يوجد `router.dart`** — التنقّل بالكامل عبر `Navigator.push/pushReplacement/pushAndRemoveUntil`.
الصور المرقّمة في هذا المجلد بترتيب رحلة المستخدم الفعلية.

## معمارية التنقّل والصلاحيات
- **الدخول:** `main.dart:45` → `SplashScreen` → (مسجَّل → `MerchantShell` ، غير ذلك → `MerchantWelcomeScreen`).
- **الـShell:** `IndexedStack` بأربعة تبويبات: `[Dashboard, Scanner, ManagementHub, BusinessProfile]`
  (الماسح هو الزرّ البارز الأوسط).
- **بوّابة الاشتراك** (`merchant_shell.dart:43-50`): `merchantEntitledProvider` → `false` تعرض
  `MerchantUnavailableScreen`؛ **خطأ → fail-open** (الخادم يفرض الاستحقاق على الكتابة).
- **الصلاحيات:** `permissionsProvider` (owner = الكل)؛ `ref.permCan(res,act)` **fail-closed** (false حتى التحميل).

> **تضارب بوّابات على مستوى المعمارية:** الـ**Hub** يخفي البلاطات بـ`perms.can` لكنه **fail-open عند
> `perms==null`** (كل البلاطات ظاهرة أثناء التحميل/الخطأ)، بينما الشاشات الفردية fail-closed. كما أن
> **Dashboard و SetupChecklist و BusinessProfile بلا أي بوّابة عميل** وتوجّه مباشرة لشاشات الإدارة/الإعدادات.

---

## 001 — Splash
- **Page Name:** شاشة البداية / `SplashScreen` (`features/splash/splash_screen.dart:11`)
- **Route:** دخول التطبيق (`main.dart:45`) → `pushReplacement`
- **User Role:** عام · **Screenshot:** `001-Splash.png`
- **Purpose:** شاشة إقلاع توجّه حسب حالة المصادقة (بعد 1200ms).
- **Available Actions:** لا شيء (تقدّم تلقائي).
- **Potential Bugs:** لا مسار خطأ لو رمى `isLoggedIn`؛ `mounted` مفحوص (سليم).

## 002 — Welcome
- **Page Name:** الترحيب / `MerchantWelcomeScreen` (`features/auth/welcome_screen.dart:10`)
- **Route:** من Splash · **Role:** عام · **Screenshot:** `002-Welcome.png`
- **Available Actions:** «تسجيل نشاط جديد» → Register؛ «تسجيل الدخول» → Login؛ «دخول موظف» → StaffLogin.
- **Potential UX Issues:** كل الأزرار `push` (تراكم المكدّس الخلفي).

## 003 — Login (المالك)
- **Page Name:** تسجيل الدخول / `MerchantLoginScreen` (`features/auth/login_screen.dart:12`)
- **Route:** من Welcome؛ نجاح → `pushAndRemoveUntil(MerchantShell)` · **Screenshot:** `003-Login.png`
- **Available Actions:** زر إظهار كلمة المرور؛ «نسيت كلمة المرور؟»؛ «دخول».
- **Forms/Validation:** المعرّف/كلمة المرور «مطلوب» فقط — **بلا حدّ أدنى للطول** (تفاوت مع التسجيل=6).
- **Potential Bugs:** «نسيت كلمة المرور؟» تعمل للبريد فقط وترفض الهاتف بصمت؛ `_forgotPassword` لا يضبط
  `_busy` → **خطر إرسال مزدوج**.

## 004 — OTP
- **Page Name:** تأكيد رقم الجوال / `MerchantOtpScreen` (`features/auth/otp_screen.dart:14`)
- **Route:** من Register؛ نجاح → `pushReplacement(PendingApprovalScreen)` · **Screenshot:** `004-OTP.png`
- **Forms/Validation:** يحجب عند `length < 4` بينما النص يقول «6 أرقام» (تضارب).
- **Potential Bugs:** `_resend` لا يضبط `_busy` → **سبام OTP بلا عدّاد/تهدئة**؛ لا إرسال تلقائي ولا رسالة نجاح.

## 005 — Staff Login
- **Page Name:** دخول موظف / `StaffLoginScreen` (`features/auth/staff_login_screen.dart:11`)
- **Route:** من Welcome؛ نجاح → Shell؛ `claimStaff` ثم `linked!=true` → `signOut` · **Screenshot:** `005-Staff-Login.png`
- **Available Actions:** الهاتف (`+966`)؛ «إرسال رمز التحقق»؛ OTP؛ «دخول»؛ «تغيير الرقم».
- **Forms/Validation:** هاتف `<9` → toast؛ **OTP `<4` يرجع بصمت بلا رسالة** (يبدو كزرّ ميّت).
- **Potential Bugs:** «دخول» بلا حارس `_busy` → إرسال مزدوج محتمل؛ خطأ التحويل يُسمّى «رمز غير صحيح».

## 006 — Register Business
- **Page Name:** تسجيل نشاط جديد / `RegisterBusinessScreen` (`features/auth/register_business_screen.dart:12`)
- **Route:** من Welcome → OTP مع `draft` · **Screenshot:** `006-Register-Business.png`
- **Available Actions:** اختيار الشعار؛ قائمة نوع النشاط؛ زر كلمة المرور؛ «اختيار من الخريطة» → MapPicker؛
  مربّع الشروط؛ «إرسال الطلب».
- **Forms/Validation:** الاسم مطلوب؛ هاتف `<9`؛ بريد `@` فقط؛ العنوان مطلوب؛ كلمة مرور `<6`؛ الشروط؛
  **الموقع غير مطلوب** (يُرسَل lat/lng=null).
- **Potential Bugs:** **`_pickLocation` يستدعي `setState` بعد await دون فحص `mounted`** (سطر 88)؛ **كلمة المرور
  تُحمَل كنصّ صريح في وسيط التنقّل** (119)؛ شعار `NetworkImage` بلا errorBuilder.

## 007 — Pending Approval
- **Page Name:** طلبك قيد المراجعة / `PendingApprovalScreen` (`features/auth/pending_approval_screen.dart:13`)
- **Route:** `pushReplacement` من OTP؛ الموافقة → `PlansScreen` · **Screenshot:** `007-Pending-Approval.png`
- **Available Actions:** «تحديث الحالة»؛ اشتراك realtime يقود الحالة.
- **States:** pending/approved/rejected. **ناقص:** `rows.isEmpty` مُتجاهَل وصفّ null → `'pending'`
  (متجر محذوف يبدو «قيد المراجعة» للأبد)؛ realtime بلا `onError`.
- **Potential Bugs:** **`_goToPlans` بلا حارس إعادة دخول → `pushReplacement` مزدوج** (realtime + يدوي)؛
  نصّ الرفض «تواصل معنا» بلا فعل تواصل (طريق مسدود).

## 008 — Subscription Plans
- **Page Name:** اختر باقتك / `PlansScreen` (`features/subscription/plans_screen.dart:11`)
- **Route:** من Pending · **Screenshot:** `036-Subscription-Plans.png`
- **Available Actions:** «ابدأ التجربة المجانية» → Shell؛ «اشترك» → حوار معلومات.
- **Potential UX Issues:** الباقات المدفوعة **عناصر نائبة** (تفعيل يدوي) رغم وسم «اشترك»؛ لا واجهة تحميل.

## 008 (tab) — Dashboard
- **Page Name:** لوحة التحكم / `DashboardScreen` (`features/dashboard/dashboard_screen.dart:15`)
- **Route:** تبويب 0 · **Role:** أي موظف (بلا بوّابة) · **Screenshot:** `008-Dashboard.png`
- **Available Actions:** فلتر الفرع (يظهر عند >1 فرع)؛ «مسح رمز العميل» → Scanner؛ **سحب للتحديث**؛ إعادة
  المحاولة؛ يضمّ `_TrialBanner` + `SetupChecklist`.
- **States:** Skeleton/Error/Empty للنشاط. **ناقص:** لا حالة صفرية لبطاقات الإحصاء.
- **Potential Bugs:** `_StatsGrid` يبني قائمة بطاقات مؤقتة ثم **يعيد التحويل `as StatCard`** (تخصيص ميّت)؛
  `highlight: i==0` فهرس سحري؛ تنسيق أرقام غير متّسق.
- **SetupChecklist (مضمَّن):** **`maybeWhen(orElse: SizedBox.shrink())` يختفي بصمت عند التحميل والخطأ**؛
  **بلا فحص صلاحية** — كاشير يقدر يفتح `StaffScreen` (مسار تجاوز بوّابة).

## 009 — Scanner + 010 Prize Deliver
- **Page Name:** مسح رمز العميل / `ScannerScreen` (`features/scanner/scanner_screen.dart:12`)
- **Route:** تبويب 1 + من Dashboard · **Screenshots:** `009-Scanner.png` · `010-Prize-Deliver.png`
- **Available Actions:** مفتاح الفلاش؛ `onDetect` يوجّه حسب بادئة QR: `p1.`→تسليم جائزة (`_PrizeDeliverSheet`)؛
  `r1.`→تأكيد استبدال؛ غير ذلك→`capturePresence`+`verifyQr`.
- **Modals:** ورقة تسليم الجائزة؛ **حوار حظر القرب (احتيال) 🚩** «محاولة خارج نطاق الفرع» (`barrierDismissible:false`).
- **Potential Bugs:** **لا تهدئة بعد النجاح + مفتاح idempotency جديد لكل `r1.`/`p1.` → خطر تسليم مزدوج**؛
  أيقونة الفلاش لا تعكس حالتها؛ زر «تم» في ورقة الجائزة قابل للنقر بغضّ النظر عن تأكيد العميل.

## 011 — Customer Profile
- **Page Name:** ملف العميل / `CustomerProfileScreen` (`features/scanner/customer_profile_screen.dart:11`)
- **Route:** `push` من Scanner · **Screenshot:** `011-Customer-Profile.png`
- **Available Actions (4 بلاطات):** تسجيل زيارة؛ إضافة نقاط (ورقة +10/+20/+50/+100)؛ استبدال مكافأة (ورقة
  اختيار)؛ تطبيق كوبون (حوار).
- **States:** غطاء انشغال؛ معالجة `FunctionException` (409→«قيد المعالجة»). **ناقص:** **حالة فارغة لمنتقي المكافآت**.
- **Potential Bugs:** **تحويلات غير آمنة على `data` الديناميكي** (`d['user']['id'] as String` …)؛ **`record-visit`
  بلا مفتاح idempotency** (245) → خطر تسجيل مزدوج؛ بطاقات المستوى/الزيارة لا تتحدّث بعد الإجراء.

## 012 — Management Hub
- **Page Name:** الإدارة / `ManagementHubScreen` (`features/management/management_hub_screen.dart:29`)
- **Route:** تبويب 2 · **Screenshot:** `012-Management-Hub.png`
- **Available Actions:** 20 بلاطة (انظر الشاشات 016-039)؛ كل بلاطة `push`.
- **Potential Bugs:** **بوّابة fail-open عند `perms==null`**؛ **إعادة استخدام الموارد:** `settings` يغطّي الإحالة +
  مفاتيح POS؛ `staff` يغطّي رسائل الموظفين + سجل النشاط؛ `reports` يربط البلاغات + التقييمات؛ لا حالة فارغة.

## 013 — Business Profile (تبويب «حسابي»)
- **Page Name:** حسابي / `BusinessProfileScreen` (`features/profile/business_profile_screen.dart:37`)
- **Route:** تبويب 3 · **Screenshot:** `013-Business-Profile.png`
- **Available Actions:** تعديل بيانات المتجر؛ إدارة الاشتراك؛ التحليلات؛ لوحة الصدارة؛ الإعدادات المتقدمة؛
  اللغة (ورقة)؛ تسجيل الخروج (حوار).
- **Potential Bugs (مهم):** **`DateTime.parse` بلا try/catch (217,220) يُعطِب بطاقة الاشتراك عند تاريخ مشوّه**؛
  `_signOut` لا يوجّه (يعتمد على مستمع المصادقة).

## 014 — Edit Business
- **Page Name:** تعديل بيانات المتجر / `EditBusinessProfileScreen` (`features/profile/edit_business_screen.dart:20`)
- **Route:** من Profile + SetupChecklist · **Screenshot:** `014-Edit-Business.png`
- **Forms/Validation:** الاسم مطلوب؛ **الهاتف بلا تحقّق**؛ بريد فحص `@`+`.` متساهل.
- **Potential Bugs:** **`_changeLogo` فيه `try/finally` بلا `catch` — فشل الرفع يرمي بلا toast** (94-109)؛ الهاتف
  يقبل حروفًا؛ الحفظ قد يُفرّغ الشعار الحالي.

## 015 — Map Picker
- **Page Name:** اختيار الموقع / `MapPickerScreen` (`core/map_picker_screen.dart:16`)
- **Route:** من Register + محرّر الفروع · **Screenshot:** `015-Map-Picker.png`
- **Potential UX Issues/Bugs:** **بلا زر موقعي الحالي ولا بحث عنوان**؛ **رأس الدبّوس مُزاح 40px أعلى المركز
  المُعاد** (دقّة إحداثيات)؛ بلا معالجة فشل تحميل البلاطات.

## 016 — Branches
- **Page Name:** الفروع / `BranchesScreen` (`features/management/branches_screen.dart:19`)
- **Route:** بلاطة Hub (branches) · **Screenshot:** `016-Branches.png`
- **Available Actions:** FAB «فرع جديد»؛ بطاقة→محرّر؛ «اختيار الموقع»؛ مفتاح «مفعّل»؛ «حفظ»؛ حذف.
- **Modal:** `_BranchEditor` (ورقة). **Forms:** الاسم مطلوب؛ النطاق `int>0`؛ **BSSID نصّ حرّ بلا تحقّق MAC**.
- **Potential Bugs:** **يُحفظ الفرع بنطاق دون lat/lng → السياج بلا معنى**؛ **toast بعد pop**؛ لا سحب للتحديث.

## 017 — Visit Campaigns (بطاقات الأختام)
- **Page Name:** بطاقات الأختام / `CampaignsScreen` (`features/management/campaigns_screen.dart:21`)
- **Route:** بلاطة Hub (campaigns + `enableVisits`) · **Screenshots:** `017-Visit-Campaigns.png` · `018-Campaign-Editor.png`
- **Available Actions:** FAB؛ بطاقة→محرّر؛ 3 منتقيات صور؛ قائمة نوع الإجراء؛ مفتاح «مفعّلة»؛ `BranchTargetField`؛ حفظ؛ حذف.
- **Potential Bugs/UX:** **عنوان البلاطة «حملات الزيارة» ≠ عنوان الشاشة «بطاقات الأختام»**؛ النجاح لا يصفّر `_busy`؛
  نقاط/مكافآت بلا تحقّق (`?? 0`).

## 019 — Rewards
- **Page Name:** المكافآت / `RewardsManagementScreen` (`features/management/rewards_screen.dart:21`)
- **Route:** بلاطة Hub (rewards + `enableRewards`) · **Screenshot:** `019-Rewards.png`
- **Available Actions:** FAB؛ بطاقة→محرّر؛ منتقي صورة؛ مفاتيح «كمية غير محدودة»/«مفعّلة»؛ `BranchTargetField`؛ حفظ؛ حذف.
- **Forms:** الاسم مطلوب؛ نقاط `int>0`؛ كمية `int>=0`. **Bugs:** لا سحب للتحديث؛ النجاح لا يصفّر `_busy`؛ toast بعد pop.

## 020 — Levels + 021 Level Editor
- **Page Name:** المستويات / `LevelsScreen` (`features/management/levels_screen.dart:31`)
- **Route:** بلاطة Hub (levels + `enableLevels`) · **Screenshots:** `020-Levels.png` · `021-Level-Editor.png`
- **Available Actions:** منتقي الفرع (وضع لكل فرع)؛ FAB؛ بطاقة→محرّر؛ حفظ؛ حذف.
- **Potential Bugs:** **`DropdownButton.value` قد لا يطابق العناصر لو عُطِّل الفرع → تحطّم تأكيد** (80,124)؛
  وميض AppBar من `.when` المتداخل؛ النجاح لا يصفّر `_busy`.

## 022 — Lucky Wheel Config
- **Page Name:** عجلة الحظ / `WheelManagementScreen` (`features/management/wheel_screen.dart:39`)
- **Route:** بلاطة Hub (wheel) · **Screenshot:** `022-Lucky-Wheel-Config.png`
- **Available Actions:** «إضافة» مقطع؛ حذف مقطع (مُعطَّل عند ≤2)؛ ChoiceChips للنوع؛ ألوان؛ مفتاح «مفعّلة»؛ «حفظ العجلة».
- **Potential Bugs:** **`_EditableSegment` + `_previewController` لا يُتخلَّص منها أبدًا → تسرّب**؛ `_save` يطفّر
  `_wheelId`/`_removedIds` قبل اكتمال الـawaits → **حالة غير متّسقة عند فشل وسط الحفظ**.

## 023 — Coupons
- **Page Name:** الكوبونات / `CouponsScreen` (`features/management/coupons_screen.dart:27`)
- **Route:** بلاطة Hub (coupons + `enableCoupons`) · **Screenshot:** `023-Coupons.png`
- **Available Actions:** FAB؛ بطاقة→محرّر؛ قائمة النوع (نسبة/ثابت/منتج مجاني)؛ «من/إلى تاريخ»؛ `BranchTargetField`؛ حفظ؛ حذف.
- **Potential Bugs:** **بلا تحقّق `valid_from <= valid_to`** (نهاية قبل بداية مسموحة)؛ حدود الاستخدام بلا تحقّق؛ لا حالة فعّال/متوقّف.

## 024 — Questions + 025 Responses
- **Page Name:** الأسئلة / `QuestionsScreen` (`features/management/questions_screen.dart:33`) → الإجابات / `QuestionResponsesScreen`
- **Route:** بلاطة Hub (questions) · **Screenshots:** `024-Questions.png` · `025-Question-Responses.png`
- **Available Actions:** FAB؛ «عرض الإجابات»؛ «تعديل»؛ شرائح النوع؛ «إضافة خيار»؛ مفاتيح «إجباري»/«مفعّل»؛ حفظ.
- **Potential Bugs (مهم):** **التعديل يحذف+يعيد إدراج الخيارات بمعرّفات جديدة → يُيتّم عدّادات الإجابات
  التاريخية**؛ توزيع الإجابات يعدّ المعرّفات الحالية فقط → **عدّ ناقص بعد أي تعديل**؛ فلتر الفرع موعود بالتعليق لكنه غير منفَّذ.

## 026 — Customers
- **Page Name:** العملاء / `CustomersScreen` (`features/management/customers_screen.dart:95`)
- **Route:** بلاطة Hub (customers) · **Screenshot:** `026-Customers.png`
- **Available Actions:** بحث؛ «تصفية» (Badge) → `_FiltersSheet`؛ سحب+تمرير لا نهائي؛ بطاقة→`_CustomerDetailSheet`؛ واتساب.
- **Potential Bugs:** **البحث بلا debounce — RPC لكل ضغطة + مزوّد جديد كل مرة**؛ min>max غير محقَّق؛ فلتر الفرع
  يختفي بصمت عند الخطأ؛ `wa.me/<digits>` يُسقط رمز الدولة → رابط غير صالح للأرقام المحلية.

## 027 — Reports + Report Chat
- **Page Name:** البلاغات / `ReportsScreen` (`features/management/reports_screen.dart:24`) → `ReportChatScreen`
- **Route:** بلاطة Hub (reports) · **Screenshot:** `027-Reports.png` *(محادثة البلاغ بلا لقطة مخصّصة — انظر «شاشات بلا لقطة»)*
- **Available Actions:** سحب+المزيد؛ بطاقة→محادثة؛ «نسخ الرابط» للفيديو.
- **Potential UX Issues:** الفيديو نسخ-رابط فقط (بلا تشغيل)؛ **الحالة للقراءة — التاجر يردّ فقط ولا يحلّ**؛
  `ReportChatView` يعيد جلب الخيط كاملًا عند كل إرسال. الردّ مبوَّب بـ`reports:reply`.

## 028 — Staff
- **Page Name:** الموظفين / `StaffScreen` (`features/management/staff_screen.dart:44`)
- **Route:** بلاطة Hub (staff) · **Screenshot:** `028-Staff.png`
- **Available Actions:** FAB؛ بطاقة→تعديل؛ قوائم الدور/الفرع/الدور المخصّص؛ مفتاح «تفعيل الهدايا»؛ حفظ. **بلا حذف**.
- **Potential Bugs:** **قيمة قائمة الفرع غير محروسة (269) — تأكيد/رمي لو غاب `branch_id` من الخيارات**؛ تضارب
  الدور المُعدَّد مع الدور المخصّص → صلاحيات غامضة؛ toast بعد pop.

## 029 — Roles & Permissions
- **Page Name:** الأدوار والصلاحيات / `RolesScreen` (`features/management/roles_screen.dart:29`)
- **Route:** بلاطة Hub (roles) · **Screenshot:** `029-Roles-Permissions.png`
- **Available Actions:** FAB؛ بطاقة→محرّر؛ مصفوفة `FilterChip` لكل مورد (view شرط لـcreate/edit/delete)؛ حذف.
- **Potential Bugs:** `seedDefaultRoles` قد **يبذر مرتين** عند سباق البناء؛ أدوار النظام قابلة للتعديل لغير المالك (عدا الحذف)؛ toast بعد pop.

## 030 — POS Integration
- **Page Name:** تكامل POS / `PosIntegrationScreen` (`features/management/pos_screen.dart:21`)
- **Route:** بلاطة Hub (settings) · **Screenshot:** `030-POS-Integration.png`
- **Available Actions:** FAB «مفتاح جديد»؛ نسخ المسار؛ «إلغاء» (إبطال)؛ نسخ curl؛ حوار كشف-مرّة.
- **Potential Bugs:** **المسار نصّ نائب `https://<YOUR-PROJECT>.supabase.co/...` — نسخه يعطي رابطًا غير عامل**؛
  **الإبطال بلا تأكيد** (نقرة واحدة دائمة)؛ `nameCtrl` لا يُتخلَّص منه.

## 031 — Store Leaderboard
- **Page Name:** لوحة الصدارة / `StoreLeaderboardScreen` (`features/leaderboard/store_leaderboard_screen.dart:27`)
- **Route:** بلاطة Hub (customers) + Profile · **Screenshot:** `031-Store-Leaderboard.png`
- **Available Actions:** `SegmentedButton` (الفرع الحالي/كل الفروع)؛ إعادة المحاولة؛ منصّة Top-3.
- **States:** loading/error/empty مُعالجة. **Bug:** موظف بلا `branchId` → «الفرع الحالي» يتصرّف كـ«الكل» بصمت (مربك).

## 032 — Analytics
- **Page Name:** التحليلات / `AnalyticsScreen` (`features/analytics/analytics_screen.dart:113`)
- **Route:** بلاطة Hub (analytics) + Profile · **Screenshot:** `032-Analytics.png`
- **Available Actions:** فترة `SegmentedButton`؛ شرائح الفروع؛ إعادة المحاولة؛ مخططات زيارات/نقاط.
- **Potential Bugs:** **تضارب عقد `returnRate` — التوثيق 0..1 مقابل تعليق 0..100، ويُعرض `.round()%`** → نسبة خاطئة؛
  فلتر الفرع يختفي بصمت عند الخطأ؛ لا تحديث يدوي عند النجاح.

## 033 — Announcements
- **Page Name:** الإعلانات / `AnnouncementsScreen` (`features/announcements/announcements_screen.dart:26`)
- **Route:** بلاطة Hub (announcements + `enableAnnouncements`) + Customers · **Screenshot:** `033-Announcements.png`
- **Available Actions:** عنوان؛ نصّ؛ «إرسال»؛ شريط الحصّة.
- **Potential Bugs (مهم):** **لا تأكيد قبل البثّ لكل العملاء — نقرة واحدة لا رجعة فيها وتستهلك الحصّة**؛ خطأ
  الحصّة يختفي بصمت؛ خطأ الخادم الخام يظهر.

## 034 — Settings + 035 Scope Switch
- **Page Name:** الإعدادات / `MerchantSettingsScreen` (`features/settings/merchant_settings_screen.dart:11`)
- **Route:** Hub + Profile · **Screenshots:** `034-Settings.png` · `035-Scope-Switch.png`
- **Available Actions:** نطاق النقاط `RadioListTile` (مشترك/منفصل لكل فرع)؛ 10 مفاتيح ميزات؛ مفاتيح أمان
  (+حدّ شرطي)؛ حقول رقمية؛ هوية (اسم العلامة، لون hex)؛ «حفظ».
- **تأكيد تبديل النطاق:** متجر→فرع: يحجب بلا فروع، وإلا حوار؛ فرع→متجر: ورقة اختيار. ثم `applyPointsScope` + `upsertSettings`.
- **Potential Bugs:** **الهجرة + الحفظ awaitان منفصلان غير ذرّيين → عدم اتّساق عند فشل جزئي**؛ المستخدم للقراءة
  يقدر يبدّل كل شيء لكن بلا «حفظ»؛ بلا حارس تغييرات غير محفوظة؛ اسم العلامة/الـhex بلا تحقّق.

## 036 — Subscription Plans
- *(انظر 008 أعلاه — `036-Subscription-Plans.png`)*

## 037 — Manage Subscription
- **Page Name:** إدارة الاشتراك / `ManageSubscriptionScreen` (`features/subscription/manage_subscription_screen.dart:75`)
- **Route:** من Profile + Unavailable · **Screenshot:** `037-Subscription.png`
- **Available Actions:** «تواصل مع الدعم» (toast)؛ 3 بطاقات باقات.
- **Potential Bugs:** **بطاقات الباقات خاملة (بلا `onTap`)**؛ **`DateTime.parse` بلا حماية (136,138) يتحطّم عند تاريخ مشوّه**؛
  بريد الدعم toast عابر فقط.

## 038 — Feature Unavailable
- **Page Name:** متجرك غير متاح حاليًا / `MerchantUnavailableScreen` (`features/subscription/merchant_unavailable_screen.dart:8`)
- **Route:** تعرضها بوّابة الاشتراك في الـShell · **Screenshot:** `038-Feature-Unavailable.png`
- **Available Actions:** «إدارة الاشتراك»؛ بطاقة دعم ساكنة.
- **Potential UX Issues:** بلا مخرج تسجيل خروج/تبديل حساب → **طريق مسدود محتمل** لو فشلت شاشة الاشتراك أيضًا.

## 039 — Activity Log
- **Page Name:** سجل النشاط / `ActivityLogScreen` (`features/management/activity_log_screen.dart:82`)
- **Route:** بلاطة Hub (staff) · **Screenshot:** `039-Activity-Log.png`
- **Available Actions:** قائمة فلتر الموظف (اسم+هاتف)؛ سحب+تمرير لا نهائي.
- **States:** يعتمد على `PaginatedListView`؛ **لا فرع خطأ/تحميل صريح**.
- **Potential Bugs:** شهر/يوم بلا padding (176)؛ أنواع أكشن غير معروفة تُسرّب رموزًا خامًا؛ لا فلتر مدى تاريخي.

## 040 — Reviews + 041 Review Reply
- **Page Name:** التقييمات / `ReviewsScreen` (`features/management/reviews_screen.dart:28`)
- **Route:** بلاطة Hub (مورد `reports`) · **Screenshots:** `040-Reviews.png` · `041-Review-Reply.png`
- **Available Actions:** سحب للتحديث؛ المزيد؛ «الردّ»/«تعديل الردّ» → `_ReplySheet`؛ «حفظ الردّ».
- **Potential Bugs (مهم):** **لا بوّابة صلاحية على الردّ — أي حامل `reports:view` يقدر ينشر ردودًا (الإجراء
  الكتابي الوحيد بلا بوّابة)**؛ **ردّ فارغ/مسافات قابل للإرسال** (بلا تحقّق)؛ خطأ الملخّص غير مرئي.

---

## ملخّص الجودة (مرتَّب حسب الأولوية)

### ثغرات الأمان/الصلاحيات
1. **Hub fail-open** — كل البلاطات ظاهرة أثناء تحميل الصلاحيات أو عند خطأ جلبها.
2. **ردّ التقييمات بلا بوّابة لكل-إجراء** — الإجراء الكتابي الوحيد بلا فحص.
3. **برنامج الإحالة قابل للتعديل والحفظ بـ`settings:view` فقط** (لا وضع للقراءة).
4. **سجل النشاط/رسائل الموظفين** مقصودان للمالك لكنهما مكشوفان لأي `staff:view`.
5. **Dashboard/SetupChecklist/BusinessProfile تتجاوز بوّابة الـHub** بالكامل.
6. **إفراط استخدام الموارد:** `settings` (إحالة + مفاتيح POS)، `staff` (رسائل + سجل)، `reports` (بلاغات + تقييمات).

### تحطّم/null-safety
7. **`DateTime.parse` بلا حماية:** `business_profile_screen.dart:217,220`، `manage_subscription_screen.dart:136,138`.
8. **تحويلات JSON ديناميكية بلا حماية:** customer_profile (23,164)، customers (32)، reports (75)، staff_messages (117)، analytics (168).
9. **`DropdownButton.value` خارج العناصر:** levels (80)، staff (269) — فرع مُعطَّل.
10. **`mounted` ناقص بعد await:** register_business (88).

### إجراء مزدوج/idempotency
11. **الماسح بلا تهدئة بعد النجاح** + مفتاح idempotency جديد لكل نداء → **تسليم مزدوج**.
12. **`record-visit` بلا مفتاح idempotency** (customer_profile:245).
13. **سباق `_goToPlans`** (pending approval) بلا حارس.
14. **`_resend` (OTP/forgot) لا يضبط `_busy`** → سبام بلا تهدئة.

### تسرّبات موارد
15. **Wheel `_EditableSegment` + `_previewController` لا تُتخلَّص**.
16. **POS `nameCtrl` لا يُتخلَّص**.

### سلامة البيانات
17. **تبدّل معرّفات خيارات الأسئلة** يُيتّم عدّادات الإجابات.
18. **حفظ متعدّد الخطوات غير ذرّي:** هجرة نطاق الإعدادات، إعادة كتابة خيارات الأسئلة، طفرة حفظ العجلة.

### فجوات UX/تحقّق
19. **لا تأكيد قبل بثّ الإعلان لكل العملاء**؛ **لا تأكيد قبل إبطال مفتاح POS**.
20. **نمط toast-بعد-pop** عبر محرّرات الفروع/الموظفين/الأدوار/الأسئلة.
21. **أخطاء async مُخفاة بصمت** (`SizedBox.shrink()`): SetupChecklist، فلتر فروع التحليلات، شريط حصّة
    الإعلانات، إعدادات الصدارة، قائمة فروع العملاء، ملخّص التقييمات.
22. **فجوات تحقّق:** الهاتف (دخول/تسجيل/موظف/تعديل)، BSSID (بلا MAC)، مدى تاريخ الكوبون (from>to)،
    أرقام الكوبون/الحملة/الإحالة/العجلة (`?? 0` صامت)، لون hex، ردّ تقييم فارغ.
23. **لا سحب للتحديث** على أي شاشة قائمة-محرّر (مكافآت/مستويات/كوبونات/حملات/فروع/موظفين/أدوار).
24. **تضارب التسمية:** Hub «حملات الزيارة» مقابل الشاشة «بطاقات الأختام».
25. **`BranchTargetField` تفاعلي في المحرّرات للقراءة**؛ إلغاء كل الشرائح يرجع للموحَّد بصمت.
26. **واجهات نائبة/خاملة:** أزرار الباقات المدفوعة، بطاقات إدارة الاشتراك، مسار POS النائب.

> **لم يُعثر على أي زرّ ميّت أو تنقّل مكسور في تطبيق التاجر** — كل معالج يصل لهدف فعلي.
> (الزرّ الميّت الوحيد في المنصّة كان في تطبيق العميل: «تفعيل» في `LocationPrimingScreen`.)

### شاشات الإدارة العميقة (أُضيفت لقطاتها الآن)
`SetupChecklist` (`Setup-Checklist.png`) · `ReportChatScreen` (`Report-Chat.png`) ·
`StaffMessagesScreen` (`Staff-Messages.png`) · `ReferralProgramScreen` (`Referral-Program.png`) —
أصبحت ضمن المجموعة المرقّمة (45 شاشة).

**التحليل الساكن:** `flutter analyze` على `merchant_app` = ✅ بلا مشاكل.
