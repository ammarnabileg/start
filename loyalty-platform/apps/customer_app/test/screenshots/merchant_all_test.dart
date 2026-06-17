// Facsimile screenshots of EVERY merchant (store-admin) screen (real DS widgets
// + SVG icons + sample data), 390x844 RTL.
// Run: flutter test test/screenshots/merchant_all_test.dart
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';

const _w = 390.0, _h = 844.0;

Future<void> _loadFonts() async {
  final loader = FontLoader('Tajawal');
  for (final f in ['Tajawal-Regular.ttf', 'Tajawal-Bold.ttf']) {
    final bytes = File('test/screenshots/fonts/$f').readAsBytesSync();
    loader.addFont(Future.value(ByteData.view(Uint8List.fromList(bytes).buffer)));
  }
  await loader.load();
}

ThemeData _theme() {
  final base = ThemeData(
    useMaterial3: true,
    fontFamily: 'Tajawal',
    scaffoldBackgroundColor: AppColors.background,
    colorScheme: ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      primary: AppColors.primary,
      onPrimary: AppColors.onPrimary,
    ),
  );
  return base.copyWith(
    textTheme: base.textTheme
        .apply(bodyColor: AppColors.textPrimary, displayColor: AppColors.textPrimary),
  );
}

Future<void> _shot(WidgetTester t, String name, Widget child) async {
  t.view.physicalSize = const Size(_w, _h);
  t.view.devicePixelRatio = 1.0;
  addTearDown(t.view.resetPhysicalSize);
  addTearDown(t.view.resetDevicePixelRatio);
  final key = GlobalKey();
  await t.pumpWidget(RepaintBoundary(
    key: key,
    child: MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: _theme(),
      locale: const Locale('ar'),
      home: Directionality(textDirection: TextDirection.rtl, child: child),
    ),
  ));
  await t.pumpAndSettle(const Duration(seconds: 1));
  final boundary = key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  final image = await boundary.toImage(pixelRatio: 1.0);
  final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
  image.dispose();
  final dir = Directory('test/screenshots/out')..createSync(recursive: true);
  File('${dir.path}/$name.png').writeAsBytesSync(bytes!.buffer.asUint8List());
  // One screenshot per process (run with --plain-name): exit immediately
  // after saving to skip the flutter_test teardown that stalls this env.
  exit(0);
}

Widget _nav(int i) => AppBottomNav(
      currentIndex: i,
      onTap: (_) {},
      items: const [
        AppBottomNavItem(icon: Icons.dashboard_outlined, label: 'لوحة التحكم'),
        AppBottomNavItem(
            icon: Icons.qr_code_scanner_rounded, label: 'مسح', prominent: true),
        AppBottomNavItem(icon: Icons.tune_rounded, label: 'الإدارة'),
        AppBottomNavItem(icon: Icons.storefront_outlined, label: 'حسابي'),
      ],
    );

Widget _field(String label, {IconData? icon, String? value, int maxLines = 1}) =>
    Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextField(
        maxLines: maxLines,
        controller: value == null ? null : TextEditingController(text: value),
        decoration: InputDecoration(
            labelText: label, prefixIcon: icon == null ? null : AppIcon(icon)),
      ),
    );

Widget _listScaffold(String title, List<Widget> items,
        {Widget? fab, List<Widget>? actions}) =>
    Scaffold(
      appBar: AppBar(title: Text(title), actions: actions),
      floatingActionButton: fab,
      body: ListView(padding: const EdgeInsets.all(16), children: items),
    );

Widget _rowCard(IconData icon, String title, String sub, {Widget? trailing}) =>
    AppCard(
      margin: const EdgeInsets.only(bottom: 10),
      child: Row(children: [
        AppIconBadge(icon, size: 44),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(height: 2),
            Text(sub,
                style: const TextStyle(
                    color: AppColors.textSecondary, fontSize: 13)),
          ]),
        ),
        if (trailing != null) trailing,
      ]),
    );

Widget _fab() => FloatingActionButton(
    onPressed: () {},
    backgroundColor: AppColors.primary,
    foregroundColor: Colors.white,
    elevation: 4,
    shape: const CircleBorder(),
    child: const AppIcon(Icons.add, color: Colors.white, size: 28));

void main() {
  setUpAll(_loadFonts);

  testWidgets('m01 splash', (t) => _shot(t, 'm01_splash', const _Splash()));
  testWidgets('m02 welcome', (t) => _shot(t, 'm02_welcome', const _Welcome()));
  testWidgets('m03 login', (t) => _shot(t, 'm03_login', const _Login()));
  testWidgets('m04 otp', (t) => _shot(t, 'm04_otp', const _Otp()));
  testWidgets('m05 staff login', (t) => _shot(t, 'm05_staff_login', const _StaffLogin()));
  testWidgets('m06 register business', (t) => _shot(t, 'm06_register_business', const _RegisterBiz()));
  testWidgets('m07 pending approval', (t) => _shot(t, 'm07_pending', const _Pending()));
  testWidgets('m08 dashboard', (t) => _shot(t, 'm08_dashboard', const _Dashboard()));
  testWidgets('m09 scanner', (t) => _shot(t, 'm09_scanner', const _Scanner()));
  testWidgets('m10 customer profile', (t) => _shot(t, 'm10_customer_profile', const _CustomerProfile()));
  testWidgets('m11 management hub', (t) => _shot(t, 'm11_management', const _Management()));
  testWidgets('m12 business profile', (t) => _shot(t, 'm12_business_profile', const _BizProfile()));
  testWidgets('m13 edit business', (t) => _shot(t, 'm13_edit_business', const _EditBiz()));
  testWidgets('m14 map picker', (t) => _shot(t, 'm14_map_picker', const _MapPicker()));
  testWidgets('m15 branches', (t) => _shot(t, 'm15_branches', const _Branches()));
  testWidgets('m16 campaigns', (t) => _shot(t, 'm16_campaigns', const _Campaigns()));
  testWidgets('m17 rewards', (t) => _shot(t, 'm17_rewards', const _Rewards()));
  testWidgets('m18 levels', (t) => _shot(t, 'm18_levels', const _Levels()));
  testWidgets('m18b levels editor', (t) => _shot(t, 'm18b_levels_editor', const _LevelsEditor()));
  testWidgets('m19 wheel config', (t) => _shot(t, 'm19_wheel', const _WheelCfg()));
  testWidgets('m20 coupons', (t) => _shot(t, 'm20_coupons', const _Coupons()));
  testWidgets('m21 questions', (t) => _shot(t, 'm21_questions', const _Questions()));
  testWidgets('m22 responses', (t) => _shot(t, 'm22_responses', const _Responses()));
  testWidgets('m23 customers', (t) => _shot(t, 'm23_customers', const _Customers()));
  testWidgets('m24 staff', (t) => _shot(t, 'm24_staff', const _Staff()));
  testWidgets('m25 roles', (t) => _shot(t, 'm25_roles', const _Roles()));
  testWidgets('m26 pos', (t) => _shot(t, 'm26_pos', const _Pos()));
  testWidgets('m27 store leaderboard', (t) => _shot(t, 'm27_store_leaderboard', const _StoreLeaderboard()));
  testWidgets('m28 analytics', (t) => _shot(t, 'm28_analytics', const _Analytics()));
  testWidgets('m29 announcements', (t) => _shot(t, 'm29_announcements', const _Announcements()));
  testWidgets('m30 settings', (t) => _shot(t, 'm30_settings', const _MSettings()));
  testWidgets('m31 plans', (t) => _shot(t, 'm31_plans', const _Plans()));
  testWidgets('m32 subscription', (t) => _shot(t, 'm32_subscription', const _Subscription()));
  testWidgets('m33 unavailable', (t) => _shot(t, 'm33_unavailable', const _Unavailable()));
}

// ============================ FACSIMILES ============================

class _Splash extends StatelessWidget {
  const _Splash();
  @override
  Widget build(BuildContext context) => Scaffold(
        body: Container(
          decoration: const BoxDecoration(gradient: AppColors.darkGradient),
          child: const Center(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              AppIcon(Icons.storefront_rounded, size: 76, color: AppColors.primary),
              SizedBox(height: 18),
              Text('Hatchy للأعمال',
                  style: TextStyle(
                      fontSize: 30,
                      fontWeight: FontWeight.w900,
                      color: Colors.white)),
            ]),
          ),
        ),
      );
}

class _Welcome extends StatelessWidget {
  const _Welcome();
  @override
  Widget build(BuildContext context) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(children: [
              const Spacer(),
              const AppIcon(Icons.storefront_rounded,
                  size: 110, color: AppColors.primaryDark),
              const SizedBox(height: 24),
              Text('نمِّ ولاء عملائك',
                  style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 12),
              const Text('أنشئ برنامج نقاط ومكافآت لمتجرك في دقائق.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.textSecondary)),
              const Spacer(),
              const PrimaryButton(label: 'سجّل متجرك', onPressed: _noop),
              const SizedBox(height: 12),
              TextButton(onPressed: () {}, child: const Text('دخول الموظفين')),
            ]),
          ),
        ),
      );
}

class _Login extends StatelessWidget {
  const _Login();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          Text('دخول صاحب المتجر',
              style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 28),
          _field('رقم الجوال', icon: Icons.phone_outlined, value: '05xxxxxxxx'),
          const PrimaryButton(label: 'إرسال الرمز', onPressed: _noop),
        ]),
      );
}

class _Otp extends StatelessWidget {
  const _Otp();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          Text('رمز التحقق', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 28),
          Row(
            textDirection: TextDirection.ltr,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              for (final d in ['4', '7', '2', '9'])
                Container(
                  height: 64,
                  width: 64,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(AppRadii.md),
                      border:
                          Border.all(color: AppColors.primaryLight, width: 2)),
                  child: Text(d,
                      style: const TextStyle(
                          fontSize: 26, fontWeight: FontWeight.w800)),
                ),
            ],
          ),
          const SizedBox(height: 24),
          const PrimaryButton(label: 'تأكيد', onPressed: _noop),
        ]),
      );
}

class _StaffLogin extends StatelessWidget {
  const _StaffLogin();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('دخول الموظفين')),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          const AppIcon(Icons.badge_outlined,
              size: 56, color: AppColors.primaryDark),
          const SizedBox(height: 16),
          _field('اسم المستخدم', icon: Icons.person_outline, value: 'cashier01'),
          _field('رمز الدخول', icon: Icons.lock_outline),
          const SizedBox(height: 8),
          const PrimaryButton(
              label: 'دخول', icon: Icons.logout_rounded, onPressed: _noop),
        ]),
      );
}

class _RegisterBiz extends StatelessWidget {
  const _RegisterBiz();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('تسجيل متجر')),
        body: ListView(padding: const EdgeInsets.all(20), children: [
          _field('اسم المتجر', icon: Icons.storefront_outlined, value: 'مقهى الرواق'),
          _field('نوع النشاط', icon: Icons.category_outlined, value: 'مقهى'),
          _field('رقم الجوال', icon: Icons.phone_outlined, value: '05xxxxxxxx'),
          _field('المدينة', icon: Icons.location_on_outlined, value: 'الرياض'),
          const AppCard(
            child: Row(children: [
              AppIcon(Icons.map_outlined, color: AppColors.primaryDark),
              SizedBox(width: 12),
              Expanded(child: Text('تحديد الموقع على الخريطة')),
              AppIcon(Icons.chevron_left_rounded, color: AppColors.textSecondary),
            ]),
          ),
          const SizedBox(height: 16),
          const PrimaryButton(label: 'إنشاء المتجر', onPressed: _noop),
        ]),
      );
}

class _Pending extends StatelessWidget {
  const _Pending();
  @override
  Widget build(BuildContext context) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    height: 120,
                    width: 120,
                    decoration: const BoxDecoration(
                        color: AppColors.surfaceCream, shape: BoxShape.circle),
                    child: const AppIcon(Icons.hourglass_top_rounded,
                        size: 64, color: AppColors.primaryDark),
                  ),
                  const SizedBox(height: 24),
                  Text('قيد المراجعة',
                      style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 12),
                  const Text(
                      'طلب تسجيل متجرك قيد المراجعة من فريق Hatchy. سنعلمك فور الموافقة.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColors.textSecondary)),
                ]),
          ),
        ),
      );
}

class _Dashboard extends StatelessWidget {
  const _Dashboard();
  @override
  Widget build(BuildContext context) {
    final stats = [
      ('العملاء', '1,240', Icons.groups_2_outlined),
      ('زيارات اليوم', '86', Icons.event_available_rounded),
      ('نقاط موزّعة', '52,300', Icons.stars_rounded),
      ('مكافآت', '318', Icons.card_giftcard_rounded),
    ];
    return Scaffold(
      bottomNavigationBar: _nav(0),
      body: ListView(padding: EdgeInsets.zero, children: [
        const HeroHeader(
            title: 'لوحة التحكم',
            subtitle: 'مقهى الرواق',
            gradient: AppColors.darkGradient),
        Padding(
          padding: const EdgeInsets.all(16),
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.1,
              children: [
                for (final s in stats)
                  StatCard(icon: s.$3, label: s.$1, value: s.$2),
              ],
            ),
            const SizedBox(height: 16),
            const SectionHeader(title: 'آخر النشاطات'),
            for (final a in [
              ('إضافة 50 نقطة لعميل', 'منذ 4 د'),
              ('استبدال مكافأة بـ 100 نقطة', 'منذ 22 د'),
              ('تسجيل زيارة', 'منذ ساعة'),
            ])
              AppCard(
                margin: const EdgeInsets.only(top: 10),
                child: Row(children: [
                  const AppIcon(Icons.flash_on, color: AppColors.primaryDark),
                  const SizedBox(width: 12),
                  Expanded(child: Text(a.$1)),
                  Text(a.$2, style: Theme.of(context).textTheme.bodySmall),
                ]),
              ),
          ]),
        ),
      ]),
    );
  }
}

class _Scanner extends StatelessWidget {
  const _Scanner();
  @override
  Widget build(BuildContext context) => Scaffold(
        bottomNavigationBar: _nav(1),
        appBar: AppBar(title: const Text('مسح رمز العميل')),
        body: Stack(
          fit: StackFit.expand,
          children: [
            Container(color: Colors.black87),
            Center(
              child: Container(
                height: 250,
                width: 250,
                decoration: BoxDecoration(
                    border: Border.all(color: AppColors.primary, width: 4),
                    borderRadius: BorderRadius.circular(24)),
              ),
            ),
            Positioned(
              bottom: 80,
              left: 0,
              right: 0,
              child: Center(
                child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: .6),
                    borderRadius: BorderRadius.circular(AppRadii.pill)),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  AppIcon(Icons.qr_code_2_rounded,
                      color: AppColors.primary, size: 20),
                  SizedBox(width: 8),
                  Text('وجّه الكاميرا نحو رمز العميل',
                      style: TextStyle(
                          color: Colors.white, fontWeight: FontWeight.w600)),
                ]),
              ),
            ),
          ),
          ],
        ),
      );
}

class _CustomerProfile extends StatelessWidget {
  const _CustomerProfile();
  @override
  Widget build(BuildContext context) {
    final actions = [
      ('تسجيل زيارة', Icons.event_available_rounded),
      ('إضافة نقاط', Icons.add_circle_outline_rounded),
      ('استبدال مكافأة', Icons.redeem_rounded),
      ('تطبيق كوبون', Icons.confirmation_num_outlined),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('ملف العميل')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        AppCard(
          child: Row(children: [
            const CircleAvatar(
                radius: 26,
                backgroundColor: AppColors.primaryLight,
                child: Text('أ',
                    style:
                        TextStyle(fontSize: 20, fontWeight: FontWeight.w800))),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('أحمد خالد',
                      style: Theme.of(context).textTheme.titleLarge),
                  const Text('فضي',
                      style: TextStyle(color: AppColors.textSecondary)),
                ],
              ),
            ),
            Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                  color: AppColors.successBg,
                  borderRadius: BorderRadius.circular(16)),
              child: const Text('عميل جديد',
                  style: TextStyle(
                      color: AppColors.success, fontWeight: FontWeight.w700)),
            ),
          ]),
        ),
        const SizedBox(height: 16),
        const Row(children: [
          Expanded(
              child: StatCard(
                  icon: Icons.stars_rounded,
                  label: 'النقاط المتاحة',
                  value: '350',
                  highlight: true)),
          SizedBox(width: 12),
          Expanded(
              child: StatCard(
                  icon: Icons.event_available_rounded,
                  label: 'زيارة اليوم',
                  value: 'تم',
                  accent: AppColors.success)),
        ]),
        const SizedBox(height: 20),
        const SectionHeader(title: 'إجراءات'),
        const SizedBox(height: 12),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.6,
          children: [
            for (final a in actions)
              AppCard(
                child: Row(children: [
                  AppIconBadge(a.$2, size: 44, iconSize: 24),
                  const SizedBox(width: 12),
                  Expanded(
                      child: Text(a.$1,
                          style: Theme.of(context).textTheme.titleMedium,
                          maxLines: 2)),
                ]),
              ),
          ],
        ),
      ]),
    );
  }
}

class _Management extends StatelessWidget {
  const _Management();
  @override
  Widget build(BuildContext context) {
    final items = [
      ('المكافآت', Icons.card_giftcard_outlined),
      ('المستويات', Icons.workspace_premium_outlined),
      ('حملات الزيارات', Icons.event_repeat_rounded),
      ('عجلة الحظ', Icons.casino_outlined),
      ('الكوبونات', Icons.confirmation_num_outlined),
      ('الأسئلة', Icons.quiz_outlined),
      ('الفروع', Icons.store_mall_directory_outlined),
      ('الموظفون', Icons.groups_2_outlined),
      ('الأدوار', Icons.admin_panel_settings_outlined),
      ('تكامل POS', Icons.point_of_sale_rounded),
      ('التحليلات', Icons.insights_rounded),
      ('الإعدادات', Icons.settings_outlined),
    ];
    return Scaffold(
      bottomNavigationBar: _nav(2),
      appBar: AppBar(title: const Text('الإدارة'), centerTitle: true),
      body: GridView.count(
        crossAxisCount: 3,
        padding: const EdgeInsets.all(16),
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: .95,
        children: [
          for (final i in items)
            AppCard(
              padding: const EdgeInsets.all(10),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  AppIcon(i.$2, size: 28, color: AppColors.primaryDark),
                  const SizedBox(height: 8),
                  Text(i.$1,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                          fontSize: 12, fontWeight: FontWeight.w600)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _BizProfile extends StatelessWidget {
  const _BizProfile();
  @override
  Widget build(BuildContext context) => Scaffold(
        bottomNavigationBar: _nav(3),
        body: ListView(padding: EdgeInsets.zero, children: [
          const HeroHeader(
              title: 'مقهى الرواق',
              subtitle: 'مقهى · 3 فروع',
              gradient: AppColors.darkGradient),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(children: [
              _rowCard(Icons.edit_outlined, 'تعديل بيانات المتجر', 'الاسم، الشعار، الوصف'),
              _rowCard(Icons.credit_card_outlined, 'الاشتراك', 'الباقة الذهبية · نشطة'),
              _rowCard(Icons.point_of_sale_rounded, 'تكامل POS', 'مفاتيح API'),
              _rowCard(Icons.support_agent_outlined, 'الدعم', 'تواصل معنا'),
              _rowCard(Icons.logout_rounded, 'تسجيل الخروج', ''),
            ]),
          ),
        ]),
      );
}

class _EditBiz extends StatelessWidget {
  const _EditBiz();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('تعديل المتجر')),
        body: ListView(padding: const EdgeInsets.all(20), children: [
          Container(
            height: 110,
            decoration: BoxDecoration(
                color: AppColors.surfaceCream,
                borderRadius: BorderRadius.circular(AppRadii.lg)),
            child: const Center(
                child: AppIcon(Icons.add_a_photo_outlined,
                    size: 40, color: AppColors.primaryDark)),
          ),
          const SizedBox(height: 20),
          _field('اسم المتجر', icon: Icons.storefront_outlined, value: 'مقهى الرواق'),
          _field('الوصف', icon: Icons.short_text_rounded, value: 'أفضل قهوة مختصة', maxLines: 3),
          _field('رقم التواصل', icon: Icons.phone_outlined, value: '0112345678'),
          const SizedBox(height: 8),
          const PrimaryButton(label: 'حفظ', icon: Icons.check_rounded, onPressed: _noop),
        ]),
      );
}

class _MapPicker extends StatelessWidget {
  const _MapPicker();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('تحديد الموقع')),
        body: Stack(children: [
          Container(color: const Color(0xFFE8EDE9)),
          const Center(
              child: AppIcon(Icons.location_on_rounded,
                  size: 56, color: AppColors.error)),
          const Positioned(
            left: 16,
            right: 16,
            bottom: 24,
            child: Column(children: [
              AppCard(
                child: Row(children: [
                  AppIcon(Icons.location_on_outlined,
                      color: AppColors.primaryDark),
                  SizedBox(width: 12),
                  Expanded(
                      child: Text('طريق الملك فهد، حي العليا، الرياض',
                          maxLines: 2)),
                ]),
              ),
              SizedBox(height: 12),
              PrimaryButton(
                  label: 'تأكيد الموقع', icon: Icons.check_rounded, onPressed: _noop),
            ]),
          ),
        ]),
      );
}

class _Branches extends StatelessWidget {
  const _Branches();
  @override
  Widget build(BuildContext context) => _listScaffold(
        'الفروع',
        [
          _rowCard(Icons.store_mall_directory_outlined, 'الفرع الرئيسي', 'حي العليا · نشط',
              trailing: const AppIcon(Icons.chevron_left_rounded,
                  color: AppColors.textSecondary)),
          _rowCard(Icons.store_mall_directory_outlined, 'فرع النخيل', 'حي النخيل · نشط',
              trailing: const AppIcon(Icons.chevron_left_rounded,
                  color: AppColors.textSecondary)),
          _rowCard(Icons.store_mall_directory_outlined, 'فرع الورود', 'حي الورود · نشط',
              trailing: const AppIcon(Icons.chevron_left_rounded,
                  color: AppColors.textSecondary)),
        ],
        fab: _fab(),
      );
}

class _Campaigns extends StatelessWidget {
  const _Campaigns();
  @override
  Widget build(BuildContext context) => _listScaffold(
        'حملات الزيارات',
        [
          for (final c in [
            ('5 زيارات → قهوة مجانية', 'فعّالة · 42 مشارك'),
            ('10 زيارات → خصم 30%', 'فعّالة · 18 مشارك'),
            ('زيارة يوم الثلاثاء → نقاط مضاعفة', 'مجدولة'),
          ])
            _rowCard(Icons.event_repeat_rounded, c.$1, c.$2,
                trailing: Switch(value: true, onChanged: (_) {})),
        ],
        fab: _fab(),
      );
}

class _Rewards extends StatelessWidget {
  const _Rewards();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('المكافآت')),
        floatingActionButton: _fab(),
        body: GridView.count(
          crossAxisCount: 2,
          padding: const EdgeInsets.all(16),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.05,
          children: [
            for (final r in [
              ('قهوة مجانية', 100, true),
              ('خصم 20%', 250, true),
              ('كيكة', 400, true),
              ('وجبة عشاء', 600, false),
            ])
              Opacity(
                opacity: r.$3 ? 1 : .5,
                child: AppCard(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const AppIcon(Icons.card_giftcard_rounded,
                          size: 30, color: AppColors.primaryDark),
                      const SizedBox(height: 8),
                      Text(r.$1,
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 6),
                      PointsBadge(points: r.$2),
                    ],
                  ),
                ),
              ),
          ],
        ),
      );
}

class _Levels extends StatelessWidget {
  const _Levels();
  @override
  Widget build(BuildContext context) {
    final levels = [
      ('برونزي', 0, 'خصم 5% على كل طلب', AppColors.bronze),
      ('فضي', 500, 'قهوة مجانية شهريًا', AppColors.silver),
      ('ذهبي', 1500, 'خصم 15% + هدية ميلاد', AppColors.goldTier),
      ('بلاتيني', 3000, 'خصم 25% + أولوية', AppColors.primaryDark),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('المستويات')),
      floatingActionButton: FloatingActionButton.extended(
          onPressed: () {},
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          icon: const AppIcon(Icons.add, color: Colors.white),
          label: const Text('مستوى جديد',
              style: TextStyle(color: Colors.white))),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        // اختيار الفرع — يظهر فقط لما النقاط منفصلة لكل فرع.
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.divider)),
          child: Row(children: const [
            AppIcon(Icons.store_mall_directory_outlined,
                color: AppColors.primaryDark),
            SizedBox(width: 8),
            Text('الفرع:', style: TextStyle(fontWeight: FontWeight.w700)),
            SizedBox(width: 8),
            Expanded(child: Text('الفرع الرئيسي')),
            AppIcon(Icons.keyboard_arrow_down_rounded,
                color: AppColors.textSecondary),
          ]),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
              color: AppColors.surfaceCream,
              borderRadius: BorderRadius.circular(16)),
          child: const Row(children: [
            AppIcon(Icons.info_outline, color: AppColors.primaryDark),
            SizedBox(width: 10),
            Expanded(
                child: Text(
                    'النقاط منفصلة لكل فرع — هذه المستويات تخصّ الفرع المختار فقط.',
                    style: TextStyle(fontSize: 12))),
          ]),
        ),
        const SizedBox(height: 12),
        for (final l in levels)
          AppCard(
            margin: const EdgeInsets.only(bottom: 12),
            border: Border.all(color: l.$4.withValues(alpha: .35), width: 1.5),
            child: Row(children: [
              Container(
                  width: 6,
                  height: 48,
                  decoration: BoxDecoration(
                      color: l.$4, borderRadius: BorderRadius.circular(4))),
              const SizedBox(width: 12),
              CircleAvatar(
                  radius: 22,
                  backgroundColor: l.$4.withValues(alpha: .2),
                  child: AppIcon(Icons.workspace_premium_rounded, color: l.$4)),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.$1,
                        style: const TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 16)),
                    const SizedBox(height: 2),
                    Text(l.$3,
                        style: const TextStyle(
                            color: AppColors.textSecondary, fontSize: 12)),
                  ],
                ),
              ),
              PointsBadge(points: l.$2),
            ]),
          ),
      ]),
    );
  }
}

/// محرّر المستوى — يوضّح إن الأدمن بيحدّد الاسم + النقاط المطلوبة لكل مستوى.
class _LevelsEditor extends StatelessWidget {
  const _LevelsEditor();
  @override
  Widget build(BuildContext context) {
    Widget field(String label, String value, {IconData? icon}) => Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: InputDecorator(
            decoration: InputDecoration(
                labelText: label,
                prefixIcon: icon == null ? null : AppIcon(icon)),
            child: Text(value,
                style: const TextStyle(
                    fontWeight: FontWeight.w700, fontSize: 16)),
          ),
        );
    return Scaffold(
      appBar: AppBar(title: const Text('المستويات')),
      backgroundColor: Colors.black.withValues(alpha: .25),
      body: Align(
        alignment: Alignment.bottomCenter,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: const BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 44,
                  height: 5,
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                      color: AppColors.divider,
                      borderRadius: BorderRadius.circular(3)),
                ),
              ),
              Text('تعديل المستوى',
                  style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 16),
              field('اسم المستوى (برونزي/فضي…)', 'ذهبي',
                  icon: Icons.workspace_premium_outlined),
              field('العتبة (إجمالي النقاط Lifetime)', '1500',
                  icon: Icons.stars_rounded),
              field('وصف المكافأة', 'خصم 15% + هدية عيد الميلاد',
                  icon: Icons.card_giftcard_outlined),
              const SizedBox(height: 6),
              const PrimaryButton(
                  label: 'حفظ', icon: Icons.check_rounded, onPressed: _noop),
            ],
          ),
        ),
      ),
    );
  }
}

class _WheelCfg extends StatelessWidget {
  const _WheelCfg();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('عجلة الحظ')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          const AppCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                AppIcon(Icons.casino_rounded, color: AppColors.primaryDark),
                SizedBox(width: 10),
                Expanded(child: Text('تفعيل العجلة')),
                Text('تكلفة: 50 نقطة',
                    style: TextStyle(color: AppColors.textSecondary)),
              ]),
            ]),
          ),
          const SizedBox(height: 16),
          const SectionHeader(title: 'الجوائز (المقاطع)'),
          const SizedBox(height: 8),
          for (final s in [
            ('قهوة مجانية', 'وزن 1 · مخزون 20'),
            ('خصم 20%', 'وزن 2 · غير محدود'),
            ('+50 نقطة', 'وزن 3'),
            ('لا شيء', 'وزن 4'),
          ])
            _rowCard(Icons.card_giftcard_outlined, s.$1, s.$2,
                trailing: const AppIcon(Icons.edit_outlined,
                    color: AppColors.textSecondary)),
          const SizedBox(height: 8),
          const PrimaryButton(label: 'إضافة جائزة', icon: Icons.add, onPressed: _noop),
        ]),
      );
}

class _Coupons extends StatelessWidget {
  const _Coupons();
  @override
  Widget build(BuildContext context) => _listScaffold(
        'الكوبونات',
        [
          for (final c in [
            ('SUMMER20', 'خصم 20% · 142 استخدام'),
            ('WELCOME', 'هدية ترحيبية · 60 استخدام'),
            ('VIP50', 'خصم 50 ريال · منتهٍ'),
          ])
            _rowCard(Icons.confirmation_num_outlined, c.$1, c.$2,
                trailing: const AppIcon(Icons.copy_rounded,
                    color: AppColors.primaryDark)),
        ],
        fab: _fab(),
      );
}

class _Questions extends StatelessWidget {
  const _Questions();
  @override
  Widget build(BuildContext context) => _listScaffold(
        'الأسئلة والاستبيانات',
        [
          for (final q in [
            ('ما رأيك في خدمتنا؟', 'اختيار واحد · +20 نقطة', Icons.radio_button_checked_rounded),
            ('أي المنتجات تفضّل؟', 'اختيار متعدد · +30 نقطة', Icons.check_box_rounded),
            ('اقتراحاتك لنا', 'نص حر · +10 نقطة', Icons.short_text_rounded),
          ])
            _rowCard(q.$3, q.$1, q.$2,
                trailing: const Text('142 رد',
                    style: TextStyle(
                        color: AppColors.primaryDark,
                        fontWeight: FontWeight.w700))),
        ],
        fab: _fab(),
      );
}

class _Responses extends StatelessWidget {
  const _Responses();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('ردود: ما رأيك في خدمتنا؟')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          AppCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('ممتازة', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: const LinearProgressIndicator(
                    value: .72,
                    minHeight: 10,
                    backgroundColor: AppColors.surfaceCream,
                    color: AppColors.primary),
              ),
              const SizedBox(height: 4),
              const Text('72% · 102 رد',
                  style: TextStyle(color: AppColors.textSecondary, fontSize: 12)),
            ]),
          ),
          const SizedBox(height: 10),
          for (final r in [
            ('أحمد خالد', 'خدمة رائعة وسريعة، شكرًا لكم!'),
            ('سارة العتيبي', 'القهوة ممتازة بس الانتظار طويل أحيانًا'),
            ('محمد القحطاني', 'تجربة جميلة بشكل عام'),
          ])
            AppCard(
              margin: const EdgeInsets.only(bottom: 10),
              child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      const AppIcon(Icons.format_quote_rounded,
                          color: AppColors.primaryLight),
                      const SizedBox(width: 8),
                      Text(r.$1,
                          style: const TextStyle(fontWeight: FontWeight.w700)),
                    ]),
                    const SizedBox(height: 6),
                    Text(r.$2,
                        style: const TextStyle(color: AppColors.textSecondary)),
                  ]),
            ),
        ]),
      );
}

class _Customers extends StatelessWidget {
  const _Customers();
  @override
  Widget build(BuildContext context) {
    final cs = [
      ('أحمد خالد', 'ذهبي · 24 زيارة', 1240),
      ('سارة العتيبي', 'فضي · 11 زيارة', 430),
      ('محمد القحطاني', 'برونزي · 3 زيارات', 90),
      ('نورة السبيعي', 'فضي · 8 زيارات', 360),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('العملاء'), actions: const [
        Padding(
            padding: EdgeInsetsDirectional.only(end: 8),
            child: AppIcon(Icons.campaign_outlined)),
      ]),
      body: Column(children: [
        const Padding(
          padding: EdgeInsets.all(16),
          child: TextField(
            decoration: InputDecoration(
                hintText: 'ابحث بالاسم أو رقم الجوال',
                prefixIcon: AppIcon(Icons.search_rounded)),
          ),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            children: [
              for (final c in cs)
                AppCard(
                  margin: const EdgeInsets.only(bottom: 10),
                  child: Row(children: [
                    CircleAvatar(
                        radius: 22,
                        backgroundColor: AppColors.primaryLight,
                        child: Text(c.$1.characters.first,
                            style:
                                const TextStyle(fontWeight: FontWeight.w800))),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(c.$1,
                              style: Theme.of(context).textTheme.titleMedium),
                          Text(c.$2,
                              style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                    ),
                    PointsBadge(points: c.$3),
                  ]),
                ),
            ],
          ),
        ),
      ]),
    );
  }
}

class _Staff extends StatelessWidget {
  const _Staff();
  @override
  Widget build(BuildContext context) => _listScaffold(
        'الموظفون',
        [
          for (final s in [
            ('خالد المطيري', 'مدير · الفرع الرئيسي', Icons.admin_panel_settings_outlined),
            ('عبير السالم', 'كاشير · فرع النخيل', Icons.badge_outlined),
            ('فهد العنزي', 'كاشير · الفرع الرئيسي', Icons.badge_outlined),
          ])
            _rowCard(s.$3, s.$1, s.$2,
                trailing: const AppIcon(Icons.chevron_left_rounded,
                    color: AppColors.textSecondary)),
        ],
        fab: FloatingActionButton.extended(
            onPressed: () {},
            backgroundColor: AppColors.primary,
            foregroundColor: Colors.white,
            icon: const AppIcon(Icons.person_add_alt_1_rounded,
                color: Colors.white),
            label: const Text('إضافة موظف',
                style: TextStyle(color: Colors.white))),
      );
}

class _Roles extends StatelessWidget {
  const _Roles();
  @override
  Widget build(BuildContext context) {
    const perms = ['عرض', 'إضافة', 'تعديل', 'حذف'];
    const rows = ['النقاط', 'المكافآت', 'العملاء', 'الموظفون'];
    return Scaffold(
      appBar: AppBar(title: const Text('دور: مدير فرع')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _field('اسم الدور', icon: Icons.badge_outlined, value: 'مدير فرع'),
        const SizedBox(height: 8),
        const SectionHeader(title: 'الصلاحيات'),
        const SizedBox(height: 8),
        AppCard(
          padding: const EdgeInsets.all(12),
          child: Column(children: [
            Row(children: [
              const Expanded(flex: 2, child: SizedBox()),
              for (final p in perms)
                Expanded(
                    child: Text(p,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                            fontSize: 11, fontWeight: FontWeight.w700))),
            ]),
            const Divider(),
            for (var r = 0; r < rows.length; r++) ...[
              Row(children: [
                Expanded(
                    flex: 2,
                    child: Text(rows[r],
                        style: const TextStyle(fontWeight: FontWeight.w600))),
                for (var c = 0; c < perms.length; c++)
                  Expanded(
                    child: Center(
                      child: AppIcon(
                        (c <= 2 - (r % 2))
                            ? Icons.check_circle_rounded
                            : Icons.remove_circle_outline,
                        size: 22,
                        color: (c <= 2 - (r % 2))
                            ? AppColors.success
                            : AppColors.textSecondary,
                      ),
                    ),
                  ),
              ]),
              if (r != rows.length - 1) const Divider(),
            ],
          ]),
        ),
      ]),
    );
  }
}

class _Pos extends StatelessWidget {
  const _Pos();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('تكامل نقاط البيع POS')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          const AppCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('مفتاح API', style: TextStyle(fontWeight: FontWeight.w700)),
              SizedBox(height: 8),
              Row(children: [
                Expanded(
                    child: Text('pos_live_8F3A•••••••••2C9D',
                        style: TextStyle(
                            fontFamily: 'monospace',
                            color: AppColors.textSecondary))),
                AppIcon(Icons.copy_rounded, color: AppColors.primaryDark),
              ]),
            ]),
          ),
          const SizedBox(height: 12),
          _rowCard(Icons.vpn_key_rounded, 'إنشاء مفتاح جديد', 'لكل فرع مفتاح مستقل'),
          _rowCard(Icons.shield_outlined, 'الصلاحيات', 'earn · redeem · lookup'),
          _rowCard(Icons.assignment_outlined, 'توثيق الـ API', 'دليل التكامل'),
          const SizedBox(height: 8),
          const PrimaryButton(
              label: 'إنشاء مفتاح', icon: Icons.vpn_key_rounded, onPressed: _noop),
        ]),
      );
}

class _StoreLeaderboard extends StatelessWidget {
  const _StoreLeaderboard();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('صدارة المتجر')),
        body: Column(children: [
          Container(
            margin: const EdgeInsets.all(16),
            padding: const EdgeInsets.all(4),
            decoration: BoxDecoration(
                color: AppColors.surfaceCream,
                borderRadius: BorderRadius.circular(AppRadii.pill)),
            child: Row(children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  decoration: BoxDecoration(
                      color: AppColors.primary,
                      borderRadius: BorderRadius.circular(AppRadii.pill)),
                  child: const Text('كل الفروع',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                          color: AppColors.onPrimary,
                          fontWeight: FontWeight.w700)),
                ),
              ),
              const Expanded(
                  child: Text('هذا الفرع', textAlign: TextAlign.center)),
            ]),
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                for (final e in [
                  ('أحمد خالد', 2540, 1),
                  ('سارة العتيبي', 1820, 2),
                  ('محمد القحطاني', 1450, 3),
                  ('نورة السبيعي', 980, 4),
                  ('خالد المطيري', 820, 5),
                ])
                  AppCard(
                    margin: const EdgeInsets.only(bottom: 10),
                    child: Row(children: [
                      CircleAvatar(
                          radius: 16,
                          backgroundColor: e.$3 <= 3
                              ? AppColors.primary
                              : AppColors.surfaceCream,
                          child: Text('${e.$3}',
                              style: TextStyle(
                                  fontWeight: FontWeight.w800,
                                  color: e.$3 <= 3
                                      ? AppColors.onPrimary
                                      : AppColors.textPrimary))),
                      const SizedBox(width: 12),
                      Expanded(child: Text(e.$1)),
                      PointsBadge(points: e.$2),
                    ]),
                  ),
              ],
            ),
          ),
        ]),
      );
}

class _Analytics extends StatelessWidget {
  const _Analytics();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('التحليلات')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          const Row(children: [
            Expanded(
                child: StatCard(
                    icon: Icons.trending_up_rounded,
                    label: 'النمو الشهري',
                    value: '+18%',
                    highlight: true)),
            SizedBox(width: 12),
            Expanded(
                child: StatCard(
                    icon: Icons.groups_2_outlined,
                    label: 'عملاء جدد',
                    value: '214')),
          ]),
          const SizedBox(height: 12),
          AppCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('النقاط الموزّعة أسبوعيًا',
                  style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 16),
              SizedBox(
                height: 140,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    for (final h in [60.0, 95.0, 50.0, 120.0, 80.0, 135.0, 100.0])
                      Container(
                        width: 26,
                        height: h,
                        decoration: const BoxDecoration(
                            gradient: AppColors.goldGradient,
                            borderRadius: BorderRadius.vertical(
                                top: Radius.circular(8))),
                      ),
                  ],
                ),
              ),
            ]),
          ),
          const SizedBox(height: 12),
          _rowCard(Icons.emoji_events_outlined, 'أفضل مكافأة', 'قهوة مجانية · 142 استبدال'),
          _rowCard(Icons.local_cafe_outlined, 'أوقات الذروة', '5م - 8م'),
        ]),
      );
}

class _Announcements extends StatelessWidget {
  const _Announcements();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('الإعلانات')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          AppCard(
            gradient: AppColors.goldGradient,
            child: Row(children: [
              Container(
                height: 48,
                width: 48,
                decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: .35),
                    borderRadius: BorderRadius.circular(16)),
                child: const AppIcon(Icons.campaign_rounded,
                    color: AppColors.onPrimary),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Text('أرسل إشعارًا لكل عملائك',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(color: AppColors.onPrimary)),
              ),
            ]),
          ),
          const SizedBox(height: 16),
          AppCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Row(children: [
                AppIcon(Icons.notifications_active_outlined,
                    color: AppColors.primaryDark),
                SizedBox(width: 8),
                Expanded(child: Text('رصيد الإشعارات هذا الشهر')),
                Text('1,840 / 2,000',
                    style: TextStyle(fontWeight: FontWeight.w800)),
              ]),
              const SizedBox(height: 10),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: const LinearProgressIndicator(
                    value: .92,
                    minHeight: 8,
                    backgroundColor: AppColors.surfaceCream,
                    color: AppColors.primary),
              ),
            ]),
          ),
          const SizedBox(height: 16),
          const AppCard(
            child: Column(children: [
              TextField(decoration: InputDecoration(labelText: 'عنوان الإشعار')),
              SizedBox(height: 12),
              TextField(maxLines: 3, decoration: InputDecoration(labelText: 'النص')),
            ]),
          ),
          const SizedBox(height: 20),
          const PrimaryButton(
              label: 'إرسال', icon: Icons.send_rounded, onPressed: _noop),
        ]),
      );
}

class _MSettings extends StatelessWidget {
  const _MSettings();
  @override
  Widget build(BuildContext context) {
    Widget toggle(IconData i, String t, bool v) => AppCard(
          margin: const EdgeInsets.only(bottom: 10),
          child: Row(children: [
            AppIcon(i, color: AppColors.primaryDark),
            const SizedBox(width: 14),
            Expanded(child: Text(t)),
            Switch(value: v, onChanged: (_) {}),
          ]),
        );
    return Scaffold(
      appBar: AppBar(title: const Text('إعدادات المتجر')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        const SectionHeader(title: 'نظام النقاط'),
        const SizedBox(height: 8),
        toggle(Icons.stars_rounded, 'تفعيل النقاط', true),
        toggle(Icons.workspace_premium_outlined, 'تفعيل المستويات', true),
        const AppCard(
          margin: EdgeInsets.only(bottom: 10),
          child: Row(children: [
            AppIcon(Icons.account_balance_wallet_outlined,
                color: AppColors.primaryDark),
            SizedBox(width: 14),
            Expanded(child: Text('نطاق النقاط')),
            Text('مشترك بين الفروع',
                style: TextStyle(color: AppColors.textSecondary)),
          ]),
        ),
        const SizedBox(height: 12),
        const SectionHeader(title: 'الأمان'),
        const SizedBox(height: 8),
        toggle(Icons.shield_outlined, 'تأكيد الطرفين للاستبدال', true),
        toggle(Icons.groups_rounded, 'الإحالات', false),
      ]),
    );
  }
}

class _Plans extends StatelessWidget {
  const _Plans();
  @override
  Widget build(BuildContext context) {
    Widget plan(String name, String price, List<String> feats, bool hot) => AppCard(
          margin: const EdgeInsets.only(bottom: 14),
          border: hot ? Border.all(color: AppColors.primary, width: 2) : null,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Text(name,
                  style: const TextStyle(
                      fontSize: 20, fontWeight: FontWeight.w800)),
              const Spacer(),
              if (hot)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                      color: AppColors.primary,
                      borderRadius: BorderRadius.circular(20)),
                  child: const Text('الأكثر شيوعًا',
                      style: TextStyle(
                          color: AppColors.onPrimary,
                          fontSize: 11,
                          fontWeight: FontWeight.w700)),
                ),
            ]),
            const SizedBox(height: 6),
            Text(price,
                style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                    color: AppColors.primaryDark)),
            const SizedBox(height: 12),
            for (final f in feats)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(children: [
                  const AppIcon(Icons.check_circle_rounded,
                      size: 18, color: AppColors.success),
                  const SizedBox(width: 8),
                  Expanded(child: Text(f)),
                ]),
              ),
          ]),
        );
    return Scaffold(
      appBar: AppBar(title: const Text('الباقات')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        plan('المجانية', '0 ريال/شهر',
            ['فرع واحد', 'حتى 100 عميل', 'نقاط ومكافآت أساسية'], false),
        plan('الذهبية', '199 ريال/شهر',
            ['فروع غير محدودة', 'عملاء غير محدودين', 'عجلة الحظ + الإحالات', 'تحليلات متقدمة'],
            true),
        plan('المؤسسات', 'تواصل معنا',
            ['كل مزايا الذهبية', 'تكامل POS مخصّص', 'دعم أولوية'], false),
      ]),
    );
  }
}

class _Subscription extends StatelessWidget {
  const _Subscription();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('الاشتراك')),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          const AppCard(
            gradient: AppColors.goldGradient,
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                AppIcon(Icons.workspace_premium_rounded,
                    color: AppColors.onPrimary, size: 30),
                SizedBox(width: 10),
                Text('الباقة الذهبية',
                    style: TextStyle(
                        color: AppColors.onPrimary,
                        fontSize: 20,
                        fontWeight: FontWeight.w800)),
              ]),
              SizedBox(height: 10),
              Text('نشطة · تتجدّد في 14 يوليو 2026',
                  style: TextStyle(color: AppColors.onPrimary)),
            ]),
          ),
          const SizedBox(height: 16),
          _rowCard(Icons.credit_card_outlined, 'طريقة الدفع', 'Visa •••• 4242'),
          _rowCard(Icons.history_rounded, 'سجلّ الفواتير', '12 فاتورة'),
          _rowCard(Icons.repeat_rounded, 'تغيير الباقة', 'ترقية أو تخفيض'),
          const SizedBox(height: 8),
          OutlinedButton(
              onPressed: () {},
              child: const Text('إلغاء الاشتراك',
                  style: TextStyle(color: AppColors.error))),
        ]),
      );
}

class _Unavailable extends StatelessWidget {
  const _Unavailable();
  @override
  Widget build(BuildContext context) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    height: 120,
                    width: 120,
                    decoration: const BoxDecoration(
                        color: AppColors.surfaceCream, shape: BoxShape.circle),
                    child: const AppIcon(Icons.lock_outline_rounded,
                        size: 60, color: AppColors.textSecondary),
                  ),
                  const SizedBox(height: 24),
                  Text('متجرك غير متاح حاليًا',
                      style: Theme.of(context).textTheme.headlineSmall),
                  const SizedBox(height: 12),
                  const Text(
                      'انتهت صلاحية اشتراكك أو تم تعليق المتجر. نقاط عملائك محفوظة وستعود فور تجديد الاشتراك.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColors.textSecondary)),
                  const SizedBox(height: 24),
                  const PrimaryButton(
                      label: 'إدارة الاشتراك',
                      icon: Icons.credit_card_outlined,
                      onPressed: _noop),
                  const SizedBox(height: 12),
                  TextButton(
                      onPressed: () {},
                      child: const Text('تواصل مع الدعم')),
                ]),
          ),
        ),
      );
}

void _noop() {}
