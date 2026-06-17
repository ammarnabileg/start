@Tags(['screenshot'])
library;

// Facsimile screenshots of EVERY customer screen (real DS widgets + SVG icons
// + sample data), 390x844 RTL. Run: flutter test test/screenshots/customer_all_test.dart
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:qr_flutter/qr_flutter.dart';

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
  // settle محدود: الشاشات الساكنة تستقر فورًا؛ المتحرّكة (أنيميشن لا نهائي)
  // تُلتقط بعد 3 ثوانٍ بدل التعليق.
  try {
    await t.pumpAndSettle(const Duration(milliseconds: 100),
        EnginePhase.sendSemanticsUpdate, const Duration(seconds: 3));
  } catch (_) {
    await t.pump(const Duration(milliseconds: 300));
  }
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

// ---- shared bits ----
Widget _nav(int i) => AppBottomNav(
      currentIndex: i,
      onTap: (_) {},
      items: const [
        AppBottomNavItem(icon: Icons.qr_code_2_rounded, label: 'الرئيسية'),
        AppBottomNavItem(icon: Icons.storefront_outlined, label: 'متاجري'),
        AppBottomNavItem(icon: Icons.notifications_none_rounded, label: 'الإشعارات'),
        AppBottomNavItem(icon: Icons.person_outline_rounded, label: 'حسابي'),
      ],
    );

Widget _field(String label, {IconData? icon, String? value}) => Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextField(
        controller: value == null ? null : TextEditingController(text: value),
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: icon == null ? null : AppIcon(icon),
        ),
      ),
    );

void main() {
  setUpAll(_loadFonts);

  // ===== AUTH / ONBOARDING =====
  testWidgets('c01 splash', (t) => _shot(t, 'c01_splash', const _Splash()));
  testWidgets('c02 welcome', (t) => _shot(t, 'c02_welcome', const _Welcome()));
  testWidgets('c03 onboarding', (t) => _shot(t, 'c03_onboarding', const _Onboarding()));
  testWidgets('c04 login', (t) => _shot(t, 'c04_login', const _Login()));
  testWidgets('c05 register', (t) => _shot(t, 'c05_register', const _Register()));
  testWidgets('c06 otp', (t) => _shot(t, 'c06_otp', const _Otp()));
  testWidgets('c07 forgot', (t) => _shot(t, 'c07_forgot', const _Forgot()));
  testWidgets('c08 notif priming', (t) => _shot(t, 'c08_notif_priming', const _Priming(
      icon: Icons.notifications_active_rounded,
      title: 'ابقَ على اطّلاع',
      body: 'فعّل الإشعارات لتصلك مكافآتك وعروض متاجرك المفضّلة أولًا بأول.')));
  testWidgets('c09 loc priming', (t) => _shot(t, 'c09_loc_priming', const _Priming(
      icon: Icons.location_on_rounded,
      title: 'متاجر قريبة منك',
      body: 'فعّل الموقع لنعرض لك المتاجر القريبة وعروضها أثناء تنقّلك.')));

  // ===== MAIN (shell tabs) =====
  testWidgets('c10 qr home', (t) => _shot(t, 'c10_qr_home', const _QrHome()));
  testWidgets('c11 my stores', (t) => _shot(t, 'c11_stores', const _Stores()));
  testWidgets('c12 notifications', (t) => _shot(t, 'c12_notifications', const _Notifications()));
  testWidgets('c13 profile', (t) => _shot(t, 'c13_profile', const _Profile()));
  testWidgets('c14 edit profile', (t) => _shot(t, 'c14_edit_profile', const _EditProfile()));
  testWidgets('c15 settings', (t) => _shot(t, 'c15_settings', const _Settings()));

  // ===== STORE DETAIL + FEATURES =====
  testWidgets('c16 store detail', (t) => _shot(t, 'c16_store_detail', const _StoreDetail()));
  testWidgets('c16b store levels', (t) => _shot(t, 'c16b_store_levels', const _StoreDetailLevels()));
  // كل تابات صفحة المتجر:
  testWidgets('st1 overview', (t) => _shot(t, 'store_tab_1_overview', const _StoreTab(0, _TabOverview())));
  testWidgets('st2 visits', (t) => _shot(t, 'store_tab_2_visits', const _StoreTab(1, _TabVisits())));
  testWidgets('st3 points', (t) => _shot(t, 'store_tab_3_points', const _StoreTab(2, _TabPoints())));
  testWidgets('st4 rewards', (t) => _shot(t, 'store_tab_4_rewards', const _StoreTab(3, _TabRewards())));
  testWidgets('st5 levels', (t) => _shot(t, 'store_tab_5_levels', const _StoreTab(4, _TabLevels())));
  testWidgets('st6 coupons', (t) => _shot(t, 'store_tab_6_coupons', const _StoreTab(5, _TabCoupons())));
  testWidgets('st7 questions', (t) => _shot(t, 'store_tab_7_questions', const _StoreTab(6, _TabQuestions())));
  testWidgets('st8 history', (t) => _shot(t, 'store_tab_8_history', const _StoreTab(7, _TabHistory())));
  testWidgets('c17 reward detail', (t) => _shot(t, 'c17_reward_detail', const _RewardDetail()));
  testWidgets('c18 show to cashier', (t) => _shot(t, 'c18_show_cashier', const _ShowCashier()));
  testWidgets('c19 wheel', (t) => _shot(t, 'c19_wheel', const _Wheel()));
  testWidgets('c20 my prizes', (t) => _shot(t, 'c20_my_prizes', const _MyPrizes()));
  testWidgets('c21 prize qr', (t) => _shot(t, 'c21_prize_qr', const _PrizeQr()));
  testWidgets('c22 leaderboard', (t) => _shot(t, 'c22_leaderboard', const _Leaderboard()));
  testWidgets('c23 referral', (t) => _shot(t, 'c23_referral', const _Referral()));
  testWidgets('c24 deliver confirm', (t) => _shot(t, 'c24_deliver_confirm', const _DeliverConfirm()));
  testWidgets('c25 report', (t) => _shot(t, 'c25_report', const _ReportForm()));
  testWidgets('c26 redeemed', (t) => _shot(t, 'c26_redeemed', const _RedeemedSuccess()));
}

/// شاشة نجاح استلام الهدية.
class _RedeemedSuccess extends StatelessWidget {
  const _RedeemedSuccess();
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('قهوة مجانية'), centerTitle: true),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              height: 96,
              width: 96,
              decoration: const BoxDecoration(
                  color: AppColors.successBg, shape: BoxShape.circle),
              child: const AppIcon(Icons.check_rounded,
                  size: 52, color: AppColors.success),
            ),
            const SizedBox(height: 20),
            Text('تم استلام الهدية ✓',
                style: theme.textTheme.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 8),
            Text('قهوة مجانية',
                style: theme.textTheme.bodyMedium
                    ?.copyWith(color: AppColors.textSecondary),
                textAlign: TextAlign.center),
            const SizedBox(height: 24),
            const PrimaryButton(label: 'تمام', expanded: false, onPressed: _noop),
          ]),
        ),
      ),
    );
  }
}

/// نافذة تأكيد استلام الهدية — موافق / إلغاء / إبلاغ.
class _DeliverConfirm extends StatelessWidget {
  const _DeliverConfirm();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black.withValues(alpha: .35),
      body: Align(
        alignment: Alignment.bottomCenter,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          decoration: const BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
                width: 44,
                height: 5,
                margin: const EdgeInsets.only(bottom: 18),
                decoration: BoxDecoration(
                    color: AppColors.divider,
                    borderRadius: BorderRadius.circular(3))),
            Container(
              height: 84,
              width: 84,
              decoration: const BoxDecoration(
                  gradient: AppColors.goldGradient, shape: BoxShape.circle),
              child: const AppIcon(Icons.card_giftcard_rounded,
                  size: 44, color: Colors.white),
            ),
            const SizedBox(height: 16),
            const Text('يتم تسليمك'),
            const SizedBox(height: 4),
            Text('قهوة مجانية',
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 6),
            const Text('من عجلة الحظ · مقهى الرواق',
                style: TextStyle(color: AppColors.textSecondary, fontSize: 13)),
            const SizedBox(height: 22),
            Row(children: [
              Expanded(
                child: OutlinedButton(
                    onPressed: () {},
                    style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: const Text('إلغاء')),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: FilledButton(
                    onPressed: () {},
                    style: FilledButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14)),
                    child: const Text('موافق')),
              ),
            ]),
            const SizedBox(height: 8),
            TextButton.icon(
                onPressed: () {},
                icon: const AppIcon(Icons.warning_amber_rounded,
                    size: 18, color: AppColors.error),
                label: const Text('إبلاغ عن مشكلة',
                    style: TextStyle(color: AppColors.error))),
          ]),
        ),
      ),
    );
  }
}

/// نموذج الإبلاغ — فيديو + المتجر تلقائيًا + رسالة + إرسال.
class _ReportForm extends StatelessWidget {
  const _ReportForm();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black.withValues(alpha: .35),
      body: Align(
        alignment: Alignment.bottomCenter,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          decoration: const BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('إبلاغ عن مشكلة',
                    style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 12),
                const AppCard(
                  child: Row(children: [
                    AppIcon(Icons.storefront_rounded,
                        color: AppColors.primaryDark),
                    SizedBox(width: 10),
                    Expanded(
                        child: Text('مقهى الرواق',
                            style: TextStyle(fontWeight: FontWeight.w700))),
                    Text('تلقائي',
                        style: TextStyle(
                            color: AppColors.textSecondary, fontSize: 12)),
                  ]),
                ),
                const SizedBox(height: 12),
                const AppCard(
                  child: Row(children: [
                    AppIconBadge(Icons.check_rounded,
                        size: 44, color: AppColors.success),
                    SizedBox(width: 12),
                    Expanded(
                        child: Text('تم إرفاق الفيديو — اضغط لإعادة التسجيل',
                            style: TextStyle(fontWeight: FontWeight.w600))),
                  ]),
                ),
                const SizedBox(height: 12),
                const TextField(
                  maxLines: 3,
                  decoration: InputDecoration(
                      labelText: 'رسالتك (اختياري)',
                      alignLabelWithHint: true),
                ),
                const SizedBox(height: 16),
                const PrimaryButton(
                    label: 'إرسال',
                    icon: Icons.send_rounded,
                    onPressed: _noop),
              ]),
        ),
      ),
    );
  }
}

// ============================ FACSIMILES ============================

class _Splash extends StatelessWidget {
  const _Splash();
  @override
  Widget build(BuildContext context) => Scaffold(
        body: Container(
          decoration: const BoxDecoration(gradient: AppColors.goldGradient),
          child: Center(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Container(
                height: 110,
                width: 110,
                decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: .25),
                    shape: BoxShape.circle),
                child: const AppIcon(Icons.egg_alt_rounded,
                    size: 64, color: AppColors.onPrimary),
              ),
              const SizedBox(height: 20),
              const Text('Hatchy',
                  style: TextStyle(
                      fontSize: 40,
                      fontWeight: FontWeight.w900,
                      color: AppColors.onPrimary)),
              const SizedBox(height: 6),
              const Text('برنامج الولاء الأذكى',
                  style: TextStyle(color: AppColors.onPrimary)),
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
              Container(
                height: 120,
                width: 120,
                decoration: const BoxDecoration(
                    color: AppColors.primaryLight, shape: BoxShape.circle),
                child: const AppIcon(Icons.egg_alt_rounded,
                    size: 70, color: AppColors.primaryDark),
              ),
              const SizedBox(height: 28),
              Text('أهلًا بك في Hatchy',
                  style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 12),
              const Text('اجمع نقاطك من متاجرك المفضّلة، واستبدلها بمكافآت رائعة.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.textSecondary)),
              const Spacer(),
              const PrimaryButton(label: 'ابدأ الآن', onPressed: _noop),
              const SizedBox(height: 12),
              TextButton(onPressed: () {}, child: const Text('لديّ حساب بالفعل')),
            ]),
          ),
        ),
      );
}

class _Onboarding extends StatelessWidget {
  const _Onboarding();
  @override
  Widget build(BuildContext context) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(children: [
              Align(
                alignment: AlignmentDirectional.centerEnd,
                child: TextButton(onPressed: () {}, child: const Text('تخطّي')),
              ),
              const Spacer(),
              Container(
                height: 200,
                width: 200,
                decoration: const BoxDecoration(
                    color: AppColors.surfaceCream, shape: BoxShape.circle),
                child: const AppIcon(Icons.card_giftcard_rounded,
                    size: 110, color: AppColors.primaryDark),
              ),
              const SizedBox(height: 36),
              Text('مكافآت تستحقّها',
                  style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 12),
              const Text('كل عملية شراء تقرّبك من مكافأتك القادمة.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.textSecondary)),
              const Spacer(),
              Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                for (var i = 0; i < 3; i++)
                  Container(
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    height: 8,
                    width: i == 0 ? 24 : 8,
                    decoration: BoxDecoration(
                        color: i == 0 ? AppColors.primary : AppColors.surfaceCream,
                        borderRadius: BorderRadius.circular(4)),
                  ),
              ]),
              const SizedBox(height: 24),
              const PrimaryButton(label: 'التالي', onPressed: _noop),
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
          Text('تسجيل الدخول', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 6),
          const Text('أدخل رقم جوّالك لإرسال رمز التحقق',
              style: TextStyle(color: AppColors.textSecondary)),
          const SizedBox(height: 28),
          _field('رقم الجوال', icon: Icons.phone_outlined, value: '05x xxx xxxx'),
          const SizedBox(height: 8),
          const PrimaryButton(label: 'إرسال الرمز', onPressed: _noop),
        ]),
      );
}

class _Register extends StatelessWidget {
  const _Register();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('حساب جديد')),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          _field('الاسم', icon: Icons.person_outline, value: 'أحمد خالد'),
          _field('رقم الجوال', icon: Icons.phone_outlined, value: '05x xxx xxxx'),
          _field('تاريخ الميلاد (اختياري)', icon: Icons.cake_outlined),
          const SizedBox(height: 8),
          const PrimaryButton(label: 'متابعة', onPressed: _noop),
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
          const SizedBox(height: 6),
          const Text('أدخل الرمز المرسل إلى 05xxxxxxxx',
              style: TextStyle(color: AppColors.textSecondary)),
          const SizedBox(height: 28),
          Row(
            textDirection: TextDirection.ltr,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              for (final d in ['1', '2', '3', '4'])
                Container(
                  height: 64,
                  width: 64,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(AppRadii.md),
                      border: Border.all(color: AppColors.primaryLight, width: 2)),
                  child: Text(d,
                      style: const TextStyle(
                          fontSize: 26, fontWeight: FontWeight.w800)),
                ),
            ],
          ),
          const SizedBox(height: 24),
          const PrimaryButton(label: 'تأكيد', onPressed: _noop),
          const SizedBox(height: 12),
          Center(
              child: Text('إعادة الإرسال خلال 0:42',
                  style: Theme.of(context).textTheme.bodySmall)),
        ]),
      );
}

class _Forgot extends StatelessWidget {
  const _Forgot();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('استعادة الحساب')),
        body: ListView(padding: const EdgeInsets.all(24), children: [
          const AppIcon(Icons.lock_outline_rounded,
              size: 56, color: AppColors.primaryDark),
          const SizedBox(height: 16),
          const Text('أدخل رقم جوّالك وسنرسل لك رمزًا لإعادة التعيين.',
              style: TextStyle(color: AppColors.textSecondary)),
          const SizedBox(height: 24),
          _field('رقم الجوال', icon: Icons.phone_outlined),
          const SizedBox(height: 8),
          const PrimaryButton(label: 'إرسال الرمز', onPressed: _noop),
        ]),
      );
}

class _Priming extends StatelessWidget {
  final IconData icon;
  final String title, body;
  const _Priming({required this.icon, required this.title, required this.body});
  @override
  Widget build(BuildContext context) => Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(children: [
              const Spacer(),
              Container(
                height: 130,
                width: 130,
                decoration:
                    const BoxDecoration(color: AppColors.primaryLight, shape: BoxShape.circle),
                child: AppIcon(icon, size: 72, color: AppColors.primaryDark),
              ),
              const SizedBox(height: 28),
              Text(title, style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 12),
              Text(body,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: AppColors.textSecondary)),
              const Spacer(),
              const PrimaryButton(label: 'تفعيل', onPressed: _noop),
              const SizedBox(height: 12),
              TextButton(onPressed: () {}, child: const Text('لاحقًا')),
            ]),
          ),
        ),
      );
}

class _QrHome extends StatelessWidget {
  const _QrHome();
  @override
  Widget build(BuildContext context) => Scaffold(
        bottomNavigationBar: _nav(0),
        body: Column(children: [
          const HeroHeader(
              title: 'أهلاً، أحمد', subtitle: 'أرِ هذا الرمز للكاشير عند الدفع'),
          Expanded(
            child: Center(
              child: AppCard(
                padding: const EdgeInsets.all(28),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  QrImageView(
                      data: 'v1.demo.user.token',
                      size: 220,
                      backgroundColor: Colors.white),
                  const SizedBox(height: 18),
                  Text('أحمد خالد',
                      style: Theme.of(context).textTheme.titleMedium),
                  const Text('عضوية: 8F3A2C9D',
                      style: TextStyle(color: AppColors.textSecondary)),
                  const SizedBox(height: 14),
                  const Row(mainAxisSize: MainAxisSize.min, children: [
                    SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                            value: .6, strokeWidth: 3, color: AppColors.primary)),
                    SizedBox(width: 8),
                    Text('يتجدّد خلال 18 ث'),
                  ]),
                ]),
              ),
            ),
          ),
        ]),
      );
}

class _Stores extends StatelessWidget {
  const _Stores();
  @override
  Widget build(BuildContext context) {
    final stores = [
      ('مقهى الرواق', 'فرع العليا · فضي', 350, true),
      ('مطعم بيتزا تايم', 'الفرع الرئيسي · ذهبي', 1240, true),
      ('صالون لمسة', 'برونزي', 80, false),
    ];
    return Scaffold(
      bottomNavigationBar: _nav(1),
      appBar: AppBar(title: const Text('متاجري'), centerTitle: true),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        for (final s in stores)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Opacity(
              opacity: s.$4 ? 1 : .6,
              child: AppCard(
                child: Row(children: [
                  Container(
                      width: 56,
                      height: 56,
                      decoration: BoxDecoration(
                          color: AppColors.surfaceCream,
                          borderRadius: BorderRadius.circular(16)),
                      child: const AppIcon(Icons.storefront_rounded,
                          color: AppColors.primaryDark)),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(s.$1, style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 4),
                        Text(s.$2, style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                  if (s.$4)
                    PointsBadge(points: s.$3)
                  else
                    Container(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                          color: AppColors.textSecondary.withValues(alpha: .15),
                          borderRadius: BorderRadius.circular(20)),
                      child: const Text('غير متاح حاليًا',
                          style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: AppColors.textSecondary)),
                    ),
                ]),
              ),
            ),
          ),
      ]),
    );
  }
}

class _Notifications extends StatelessWidget {
  const _Notifications();
  @override
  Widget build(BuildContext context) {
    final items = [
      (Icons.stars_rounded, 'حصلت على 50 نقطة', 'مقهى الرواق · منذ 5 د'),
      (Icons.card_giftcard_rounded, 'مكافأة جديدة متاحة', 'بيتزا تايم · منذ ساعة'),
      (Icons.casino_rounded, 'لديك لفّة مجانية بانتظارك', 'منذ 3 ساعات'),
      (Icons.military_tech_rounded, 'ترقيت إلى المستوى الذهبي', 'أمس'),
    ];
    return Scaffold(
      bottomNavigationBar: _nav(2),
      appBar: AppBar(title: const Text('الإشعارات'), centerTitle: true),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        for (final n in items)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: AppCard(
              child: Row(children: [
                AppIconBadge(n.$1, size: 44),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(n.$2,
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 2),
                      Text(n.$3, style: Theme.of(context).textTheme.bodySmall),
                    ],
                  ),
                ),
              ]),
            ),
          ),
      ]),
    );
  }
}

class _Profile extends StatelessWidget {
  const _Profile();
  @override
  Widget build(BuildContext context) {
    final rows = [
      (Icons.person_outline, 'تعديل الملف الشخصي'),
      (Icons.card_giftcard_outlined, 'هداياي'),
      (Icons.emoji_events_outlined, 'إنجازاتي'),
      (Icons.share_rounded, 'دعوة الأصدقاء'),
      (Icons.settings_outlined, 'الإعدادات'),
      (Icons.support_agent_outlined, 'الدعم'),
    ];
    return Scaffold(
      bottomNavigationBar: _nav(3),
      body: ListView(padding: EdgeInsets.zero, children: [
        const HeroHeader(title: 'أحمد خالد', subtitle: '05xxxxxxxx'),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(children: [
            const Row(children: [
              Expanded(
                  child: StatCard(
                      icon: Icons.stars_rounded,
                      label: 'إجمالي النقاط',
                      value: '4,120',
                      highlight: true)),
              SizedBox(width: 12),
              Expanded(
                  child: StatCard(
                      icon: Icons.storefront_outlined,
                      label: 'متاجري',
                      value: '6')),
            ]),
            const SizedBox(height: 12),
            for (final r in rows)
              AppCard(
                margin: const EdgeInsets.only(bottom: 10),
                child: Row(children: [
                  AppIcon(r.$1, color: AppColors.primaryDark),
                  const SizedBox(width: 14),
                  Expanded(child: Text(r.$2)),
                  const AppIcon(Icons.chevron_left_rounded,
                      color: AppColors.textSecondary),
                ]),
              ),
          ]),
        ),
      ]),
    );
  }
}

class _EditProfile extends StatelessWidget {
  const _EditProfile();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('تعديل الملف الشخصي')),
        body: ListView(padding: const EdgeInsets.all(20), children: [
          Center(
            child: Stack(children: [
              const CircleAvatar(
                  radius: 48,
                  backgroundColor: AppColors.primaryLight,
                  child: Text('أ',
                      style: TextStyle(fontSize: 34, fontWeight: FontWeight.w800))),
              PositionedDirectional(
                bottom: 0,
                end: 0,
                child: Container(
                  padding: const EdgeInsets.all(6),
                  decoration: const BoxDecoration(
                      color: AppColors.primary, shape: BoxShape.circle),
                  child: const AppIcon(Icons.camera_alt_outlined,
                      size: 18, color: AppColors.onPrimary),
                ),
              ),
            ]),
          ),
          const SizedBox(height: 24),
          _field('الاسم', icon: Icons.person_outline, value: 'أحمد خالد'),
          _field('رقم الجوال', icon: Icons.phone_outlined, value: '05xxxxxxxx'),
          _field('البريد (اختياري)', icon: Icons.email_outlined),
          const SizedBox(height: 8),
          const PrimaryButton(label: 'حفظ', icon: Icons.check_rounded, onPressed: _noop),
        ]),
      );
}

class _Settings extends StatelessWidget {
  const _Settings();
  @override
  Widget build(BuildContext context) {
    Widget tile(IconData i, String t, {Widget? trailing}) => AppCard(
          margin: const EdgeInsets.only(bottom: 10),
          child: Row(children: [
            AppIcon(i, color: AppColors.primaryDark),
            const SizedBox(width: 14),
            Expanded(child: Text(t)),
            trailing ??
                const AppIcon(Icons.chevron_left_rounded,
                    color: AppColors.textSecondary),
          ]),
        );
    return Scaffold(
      appBar: AppBar(title: const Text('الإعدادات')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        const SectionHeader(title: 'عام'),
        const SizedBox(height: 8),
        tile(Icons.language_rounded, 'اللغة',
            trailing: const Text('العربية',
                style: TextStyle(color: AppColors.textSecondary))),
        tile(Icons.notifications_none_rounded, 'الإشعارات',
            trailing: Switch(value: true, onChanged: (_) {})),
        tile(Icons.location_on_outlined, 'خدمات الموقع',
            trailing: Switch(value: false, onChanged: (_) {})),
        const SizedBox(height: 12),
        const SectionHeader(title: 'الحساب'),
        const SizedBox(height: 8),
        tile(Icons.privacy_tip_outlined, 'الخصوصية'),
        tile(Icons.info_outline, 'عن التطبيق'),
        tile(Icons.logout_rounded, 'تسجيل الخروج'),
      ]),
    );
  }
}

class _StoreDetail extends StatelessWidget {
  const _StoreDetail();
  @override
  Widget build(BuildContext context) {
    final tabs = ['نظرة عامة', 'الزيارات', 'النقاط', 'المكافآت', 'المستويات', 'الكوبونات'];
    final rewards = [
      ('قهوة مجانية', 100, true),
      ('خصم 20%', 250, true),
      ('كيكة', 400, false),
      ('وجبة', 600, false),
    ];
    return DefaultTabController(
      length: tabs.length,
      child: Scaffold(
        body: ListView(padding: EdgeInsets.zero, children: [
          const HeroHeader(title: 'مقهى الرواق', subtitle: 'مقهى · فرع العليا'),
          TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            labelColor: AppColors.primaryDark,
            indicatorColor: AppColors.primary,
            tabs: [for (final t in tabs) Tab(text: t)],
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionHeader(title: 'حالتك'),
                const SizedBox(height: 8),
                const Row(children: [
                  Expanded(
                      child: StatCard(
                          icon: Icons.military_tech_rounded,
                          label: 'المستوى',
                          value: 'فضي')),
                  SizedBox(width: 10),
                  Expanded(
                      child: StatCard(
                          icon: Icons.stars_rounded,
                          label: 'النقاط',
                          value: '350',
                          highlight: true)),
                  SizedBox(width: 10),
                  Expanded(
                      child: StatCard(
                          icon: Icons.event_repeat_rounded,
                          label: 'زيارات',
                          value: '12')),
                ]),
                const SizedBox(height: 18),
                const SectionHeader(title: 'المكافآت'),
                const SizedBox(height: 8),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 1.05,
                  children: [
                    for (final r in rewards)
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
              ],
            ),
          ),
        ]),
      ),
    );
  }
}

class _StoreDetailLevels extends StatelessWidget {
  const _StoreDetailLevels();
  @override
  Widget build(BuildContext context) {
    const tabs = ['نظرة عامة', 'الزيارات', 'النقاط', 'المكافآت', 'المستويات', 'الكوبونات'];
    LoyaltyLevel lvl(String id, String name, int th, int order, String reward) =>
        LoyaltyLevel(
          id: id,
          merchantId: 'm1',
          name: name,
          thresholdLifetimePoints: th,
          rewardDescription: reward,
          sortOrder: order,
        );
    final levels = [
      lvl('1', 'برونزي', 0, 0, 'خصم 5% على كل طلب'),
      lvl('2', 'فضي', 500, 1, 'قهوة مجانية شهريًا'),
      lvl('3', 'ذهبي', 1500, 2, 'خصم 15% + هدية عيد الميلاد'),
      lvl('4', 'بلاتيني', 3000, 3, 'خصم 25% + أولوية الخدمة'),
    ];
    return DefaultTabController(
      length: tabs.length,
      initialIndex: 4,
      child: Scaffold(
        body: Column(children: [
          const HeroHeader(title: 'مقهى الرواق', subtitle: 'مقهى · فرع العليا'),
          TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            labelColor: AppColors.primaryDark,
            unselectedLabelColor: AppColors.textSecondary,
            indicatorColor: AppColors.primary,
            tabs: [for (final t in tabs) Tab(text: t)],
          ),
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: LevelsJourney(
                levels: levels,
                lifetimePoints: 1850,
                title: 'رحلة مستوياتك',
              ),
            ),
          ),
        ]),
      ),
    );
  }
}

// ===== صفحة المتجر: غلاف موحّد (هيدر + تابات) + محتوى كل تاب =====
const _storeTabs = [
  'نظرة عامة', 'الزيارات', 'النقاط', 'المكافآت',
  'المستويات', 'الكوبونات', 'الأسئلة', 'السجل',
];

class _StoreTab extends StatelessWidget {
  final int index;
  final Widget body;
  const _StoreTab(this.index, this.body);
  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: _storeTabs.length,
      initialIndex: index,
      child: Scaffold(
        body: Column(children: [
          const HeroHeader(
              title: 'مقهى الرواق',
              subtitle: 'مقهى · فرع العليا',
              bottom: PointsBadge(points: 350)),
          Material(
            color: AppColors.surface,
            child: TabBar(
              isScrollable: true,
              tabAlignment: TabAlignment.start,
              labelColor: AppColors.primaryDark,
              unselectedLabelColor: AppColors.textSecondary,
              indicatorColor: AppColors.primary,
              tabs: [for (final t in _storeTabs) Tab(text: t)],
            ),
          ),
          Expanded(child: body),
        ]),
      ),
    );
  }
}

class _TabOverview extends StatelessWidget {
  const _TabOverview();
  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(16),
        children: const [
          SectionHeader(title: 'حالتك في المتجر'),
          SizedBox(height: 8),
          Row(children: [
            Expanded(
                child: StatCard(
                    icon: Icons.workspace_premium_outlined,
                    label: 'المستوى',
                    value: 'فضي',
                    highlight: true)),
            SizedBox(width: 12),
            Expanded(
                child: StatCard(
                    icon: Icons.star_rounded,
                    label: 'النقاط المتاحة',
                    value: '350')),
            SizedBox(width: 12),
            Expanded(
                child: StatCard(
                    icon: Icons.event_available_outlined,
                    label: 'إجمالي النقاط',
                    value: '1,850')),
          ]),
          SizedBox(height: 20),
          PrimaryButton(
              label: 'لوحة صدارة المتجر',
              icon: Icons.emoji_events_outlined,
              onPressed: _noop),
          SizedBox(height: 16),
          AppCard(
            child: Row(children: [
              AppIconBadge(Icons.casino_rounded, size: 44),
              SizedBox(width: 12),
              Expanded(
                  child: Text('جرّب عجلة الحظ',
                      style: TextStyle(fontWeight: FontWeight.w700))),
              AppIcon(Icons.chevron_left_rounded,
                  color: AppColors.textSecondary),
            ]),
          ),
        ],
      );
}

class _TabVisits extends StatelessWidget {
  const _TabVisits();
  @override
  Widget build(BuildContext context) {
    List<DateTime> d(int n) =>
        [for (var i = n; i > 0; i--) DateTime(2026, 6, 30 - i * 3)];
    return ListView(padding: const EdgeInsets.all(16), children: [
      StampCard(
        campaign: StampCampaign(
          id: '1',
          name: 'اشترِ 9 قهوة، العاشرة مجانًا',
          description: 'بطاقة ولاء القهوة — اجمع أختامك واستمتع.',
          actionType: 'purchase',
          actionLabel: 'قهوة',
          requiredCount: 10,
          pointsPerStamp: 5,
          rewardName: 'قهوة مجانية',
          currentStamps: 7,
          stampDates: d(7),
        ),
      ),
      const SizedBox(height: 14),
      StampCard(
        campaign: StampCampaign(
          id: '2',
          name: 'تبرّع 5 مرات، واحصل على شارة',
          description: 'ساهم معنا في دعم المجتمع.',
          actionType: 'donation',
          actionLabel: 'تبرّع',
          requiredCount: 5,
          pointsPerStamp: 20,
          rewardName: 'هدية خاصة',
          currentStamps: 5,
          stampDates: d(5),
        ),
      ),
    ]);
  }
}

class _TabPoints extends StatelessWidget {
  const _TabPoints();
  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const AppCard(
            gradient: AppColors.goldGradient,
            child: Column(children: [
              Text('النقاط المتاحة',
                  style: TextStyle(color: AppColors.onPrimary)),
              SizedBox(height: 4),
              Text('350',
                  style: TextStyle(
                      fontSize: 40,
                      fontWeight: FontWeight.w900,
                      color: AppColors.onPrimary)),
              Divider(height: 24, color: Colors.white54),
              Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [
                _MiniStat(label: 'إجمالي مكتسب', value: '1,850'),
                _MiniStat(label: 'تم استبداله', value: '1,500'),
              ]),
            ]),
          ),
          const SizedBox(height: 16),
          const SectionHeader(title: 'كيف تكسب نقاطًا؟'),
          const SizedBox(height: 8),
          for (final r in const [
            (Icons.event_available_rounded, 'سجّل زيارة', '+10 نقاط'),
            (Icons.quiz_outlined, 'أجب عن سؤال', '+20 نقطة'),
            (Icons.share_rounded, 'ادعُ صديقًا', '+100 نقطة'),
          ])
            AppCard(
              margin: const EdgeInsets.only(bottom: 10),
              child: Row(children: [
                AppIconBadge(r.$1, size: 44),
                const SizedBox(width: 12),
                Expanded(child: Text(r.$2)),
                Text(r.$3,
                    style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.primaryDark)),
              ]),
            ),
        ],
      );
}

class _MiniStat extends StatelessWidget {
  final String label, value;
  const _MiniStat({required this.label, required this.value});
  @override
  Widget build(BuildContext context) => Column(children: [
        Text(value,
            style: const TextStyle(
                fontWeight: FontWeight.w900,
                fontSize: 18,
                color: AppColors.onPrimary)),
        Text(label,
            style: const TextStyle(color: AppColors.onPrimary, fontSize: 12)),
      ]);
}

class _TabRewards extends StatelessWidget {
  const _TabRewards();
  @override
  Widget build(BuildContext context) => GridView.count(
        crossAxisCount: 2,
        padding: const EdgeInsets.all(16),
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.05,
        children: [
          for (final r in const [
            ('قهوة مجانية', 100, true),
            ('خصم 20%', 250, true),
            ('كيكة', 400, false),
            ('وجبة عشاء', 600, false),
          ])
            Opacity(
              opacity: r.$3 ? 1 : .5,
              child: AppCard(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const AppIconBadge(Icons.card_giftcard_rounded, size: 46),
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
      );
}

class _TabLevels extends StatelessWidget {
  const _TabLevels();
  @override
  Widget build(BuildContext context) {
    LoyaltyLevel lvl(String id, String n, int th, int o) => LoyaltyLevel(
        id: id, merchantId: 'm', name: n, thresholdLifetimePoints: th, sortOrder: o);
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: LevelsJourney(
        levels: [
          lvl('1', 'برونزي', 0, 0),
          lvl('2', 'فضي', 500, 1),
          lvl('3', 'ذهبي', 1500, 2),
          lvl('4', 'بلاتيني', 3000, 3),
        ],
        lifetimePoints: 1850,
        title: 'رحلة مستوياتك',
      ),
    );
  }
}

class _TabCoupons extends StatelessWidget {
  const _TabCoupons();
  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          for (final c in const [
            ('SUMMER20', 'خصم 20% على طلبك', 'تنتهي 5 أغسطس'),
            ('WELCOME', 'هدية ترحيبية', 'تنتهي 20 يوليو'),
          ])
            AppCard(
              margin: const EdgeInsets.only(bottom: 12),
              child: Row(children: [
                const AppIconBadge(Icons.confirmation_num_outlined, size: 46),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(c.$1,
                          style: const TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 18,
                              letterSpacing: 1)),
                      Text(c.$2,
                          style: Theme.of(context).textTheme.bodySmall),
                      const SizedBox(height: 2),
                      Text(c.$3,
                          style: const TextStyle(
                              color: AppColors.error, fontSize: 12)),
                    ],
                  ),
                ),
                const AppIcon(Icons.copy_rounded, color: AppColors.primaryDark),
              ]),
            ),
        ],
      );
}

class _TabQuestions extends StatelessWidget {
  const _TabQuestions();
  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          AppCard(
            margin: const EdgeInsets.only(bottom: 12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Row(children: [
                AppIconBadge(Icons.quiz_outlined, size: 44),
                SizedBox(width: 12),
                Expanded(
                    child: Text('ما رأيك في خدمتنا؟',
                        style: TextStyle(fontWeight: FontWeight.w800))),
                Text('+20',
                    style: TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.primaryDark)),
              ]),
              const SizedBox(height: 12),
              for (final o in const ['ممتازة', 'جيدة', 'تحتاج تحسين'])
                Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceCream,
                    borderRadius: BorderRadius.circular(AppRadii.md),
                  ),
                  child: Text(o),
                ),
            ]),
          ),
          const AppCard(
            child: Row(children: [
              AppIconBadge(Icons.short_text_rounded, size: 44),
              SizedBox(width: 12),
              Expanded(
                  child: Text('اقتراحاتك لنا',
                      style: TextStyle(fontWeight: FontWeight.w800))),
              Text('تمت الإجابة',
                  style: TextStyle(color: AppColors.success, fontSize: 12)),
            ]),
          ),
        ],
      );
}

class _TabHistory extends StatelessWidget {
  const _TabHistory();
  @override
  Widget build(BuildContext context) {
    Widget row(IconData i, String t, String date, String amt, Color c) => AppCard(
          margin: const EdgeInsets.only(bottom: 10),
          child: Row(children: [
            AppIconBadge(i, size: 40, color: c),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(t, style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text(date, style: Theme.of(context).textTheme.bodySmall),
                ],
              ),
            ),
            Text(amt,
                style: TextStyle(fontWeight: FontWeight.w800, color: c)),
          ]),
        );
    return ListView(padding: const EdgeInsets.all(16), children: [
      row(Icons.add, 'إضافة نقاط', 'اليوم 4:20 م', '+50', AppColors.success),
      row(Icons.redeem_rounded, 'استبدال: قهوة مجانية', 'أمس', '-100',
          AppColors.error),
      row(Icons.event_available_rounded, 'تسجيل زيارة', 'منذ 3 أيام', '+10',
          AppColors.success),
      row(Icons.casino_rounded, 'لفّة عجلة الحظ', 'منذ أسبوع', '-50',
          AppColors.error),
    ]);
  }
}

class _RewardDetail extends StatelessWidget {
  const _RewardDetail();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('تفاصيل المكافأة')),
        body: ListView(padding: const EdgeInsets.all(20), children: [
          Container(
            height: 170,
            decoration: BoxDecoration(
                gradient: AppColors.goldGradient,
                borderRadius: BorderRadius.circular(AppRadii.lg)),
            child: const Center(
                child: AppIcon(Icons.card_giftcard_rounded,
                    size: 80, color: AppColors.onPrimary)),
          ),
          const SizedBox(height: 20),
          Text('قهوة مجانية', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: 8),
          const Text('استبدل نقاطك بكوب قهوة من اختيارك. صالحة في جميع الفروع.',
              style: TextStyle(color: AppColors.textSecondary)),
          const SizedBox(height: 16),
          const AppCard(
            child: Row(children: [
              AppIcon(Icons.stars_rounded, color: AppColors.primaryDark),
              SizedBox(width: 12),
              Expanded(child: Text('التكلفة')),
              PointsBadge(points: 100),
            ]),
          ),
          const SizedBox(height: 8),
          const AppCard(
            child: Row(children: [
              AppIcon(Icons.account_balance_wallet_outlined,
                  color: AppColors.primaryDark),
              SizedBox(width: 12),
              Expanded(child: Text('رصيدك الحالي')),
              Text('350 نقطة', style: TextStyle(fontWeight: FontWeight.w800)),
            ]),
          ),
          const SizedBox(height: 24),
          const PrimaryButton(
              label: 'استبدال الآن', icon: Icons.redeem_rounded, onPressed: _noop),
        ]),
      );
}

class _ShowCashier extends StatelessWidget {
  const _ShowCashier();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('استلام المكافأة'), centerTitle: true),
        body: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text('قهوة مجانية', style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 8),
              const Text('أرِ هذا الرمز للكاشير',
                  style: TextStyle(color: AppColors.textSecondary)),
              const SizedBox(height: 24),
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(AppRadii.lg),
                    border: Border.all(color: AppColors.primaryLight, width: 2)),
                child: QrImageView(
                    data: 'r1.demo.redemption',
                    size: 220,
                    backgroundColor: Colors.white),
              ),
              const SizedBox(height: 24),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                decoration: BoxDecoration(
                    color: AppColors.surfaceCream,
                    borderRadius: BorderRadius.circular(AppRadii.pill)),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  const AppIcon(Icons.timer_outlined,
                      size: 20, color: AppColors.primaryDark),
                  const SizedBox(width: 8),
                  Text('صالح لمدة 04:32',
                      style: Theme.of(context)
                          .textTheme
                          .titleLarge
                          ?.copyWith(color: AppColors.primaryDark)),
                ]),
              ),
            ]),
          ),
        ),
      );
}

class _Wheel extends StatelessWidget {
  const _Wheel();
  @override
  Widget build(BuildContext context) {
    final segs = [
      ('قهوة', AppColors.primary),
      ('خصم', AppColors.goldTier),
      ('+50', AppColors.silver),
      ('لا شيء', AppColors.surfaceCream),
      ('كيكة', AppColors.bronze),
      ('+100', AppColors.primaryDark),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('عجلة الحظ'), centerTitle: true),
      body: Column(children: [
        const SizedBox(height: 24),
        Expanded(
          child: Center(
            child: Container(
              height: 260,
              width: 260,
              decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.primary, width: 8),
                  gradient: SweepGradient(colors: [for (final s in segs) s.$2])),
              child: const Center(
                child: CircleAvatar(
                    radius: 34,
                    backgroundColor: Colors.white,
                    child: AppIcon(Icons.casino_rounded,
                        size: 34, color: AppColors.primaryDark)),
              ),
            ),
          ),
        ),
        const Padding(
          padding: EdgeInsets.all(20),
          child: Column(children: [
            AppCard(
              child: Row(children: [
                AppIcon(Icons.stars_rounded, color: AppColors.primaryDark),
                SizedBox(width: 12),
                Expanded(child: Text('تكلفة اللفّة')),
                Text('50 نقطة', style: TextStyle(fontWeight: FontWeight.w800)),
              ]),
            ),
            SizedBox(height: 16),
            PrimaryButton(
                label: 'لُفّ الآن', icon: Icons.casino_rounded, onPressed: _noop),
          ]),
        ),
      ]),
    );
  }
}

class _MyPrizes extends StatelessWidget {
  const _MyPrizes();
  @override
  Widget build(BuildContext context) {
    final prizes = [
      ('قهوة مجانية', 'مقهى الرواق', 'سارية حتى 20 يوليو'),
      ('خصم 20%', 'بيتزا تايم', 'سارية حتى 5 أغسطس'),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('هداياي'), centerTitle: true),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        for (final p in prizes)
          AppCard(
            margin: const EdgeInsets.only(bottom: 12),
            child: Row(children: [
              Container(
                height: 52,
                width: 52,
                decoration: BoxDecoration(
                    gradient: AppColors.goldGradient,
                    borderRadius: BorderRadius.circular(14)),
                child: const AppIcon(Icons.card_giftcard_rounded,
                    color: AppColors.onPrimary),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(p.$1, style: Theme.of(context).textTheme.titleMedium),
                    Text(p.$2, style: Theme.of(context).textTheme.bodySmall),
                    const SizedBox(height: 2),
                    Text(p.$3,
                        style: const TextStyle(
                            color: AppColors.success, fontSize: 12)),
                  ],
                ),
              ),
              const AppIcon(Icons.qr_code_2_rounded, color: AppColors.primaryDark),
            ]),
          ),
      ]),
    );
  }
}

class _PrizeQr extends StatelessWidget {
  const _PrizeQr();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('هديتك'), centerTitle: true),
        body: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const AppIcon(Icons.card_giftcard_rounded,
                  size: 56, color: AppColors.primaryDark),
              const SizedBox(height: 12),
              Text('قهوة مجانية', style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 4),
              const Text('من عجلة الحظ · مقهى الرواق',
                  style: TextStyle(color: AppColors.textSecondary)),
              const SizedBox(height: 24),
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(AppRadii.lg),
                    border: Border.all(color: AppColors.primaryLight, width: 2)),
                child: QrImageView(
                    data: 'p1.demo.prize',
                    size: 220,
                    backgroundColor: Colors.white),
              ),
              const SizedBox(height: 20),
              const Text('أرِ الرمز للكاشير لاستلام هديتك',
                  style: TextStyle(color: AppColors.textSecondary)),
            ]),
          ),
        ),
      );
}

class _Leaderboard extends StatelessWidget {
  const _Leaderboard();
  @override
  Widget build(BuildContext context) {
    Widget pillar(Color medal, String name, int pts, double h, Gradient g) =>
        Expanded(
          child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
            AppIcon(Icons.workspace_premium_rounded, color: medal, size: 30),
            const SizedBox(height: 4),
            Text(name,
                style: const TextStyle(fontWeight: FontWeight.w700), maxLines: 1),
            const SizedBox(height: 6),
            Container(
              height: h,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                  gradient: g,
                  borderRadius:
                      const BorderRadius.vertical(top: Radius.circular(20))),
              child: Column(children: [
                Text('$pts',
                    style: const TextStyle(
                        fontWeight: FontWeight.w800, color: AppColors.onPrimary)),
                const Text('نقطة',
                    style: TextStyle(fontSize: 11, color: AppColors.onPrimary)),
              ]),
            ),
          ]),
        );
    return Scaffold(
      appBar: AppBar(title: const Text('لوحة الصدارة'), centerTitle: true),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
          pillar(AppColors.silver, 'سارة', 1820, 120,
              const LinearGradient(colors: [AppColors.silver, Color(0xFFCED4DA)])),
          const SizedBox(width: 10),
          pillar(AppColors.goldTier, 'أحمد', 2540, 165, AppColors.goldGradient),
          const SizedBox(width: 10),
          pillar(AppColors.bronze, 'محمد', 1450, 100,
              const LinearGradient(colors: [AppColors.bronze, Color(0xFFB07A4A)])),
        ]),
        const SizedBox(height: 16),
        const SectionHeader(title: 'بقية القائمة'),
        for (final e in [('نورة', 980, 4), ('خالد', 820, 5), ('ليان', 640, 6)])
          Padding(
            padding: const EdgeInsets.only(top: 10),
            child: AppCard(
              child: Row(children: [
                CircleAvatar(
                    radius: 16,
                    backgroundColor: AppColors.surfaceCream,
                    child: Text('${e.$3}',
                        style: const TextStyle(fontWeight: FontWeight.w800))),
                const SizedBox(width: 12),
                Expanded(child: Text(e.$1)),
                PointsBadge(points: e.$2),
              ]),
            ),
          ),
      ]),
    );
  }
}

class _Referral extends StatelessWidget {
  const _Referral();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('دعوة الأصدقاء'), centerTitle: true),
        body: ListView(padding: const EdgeInsets.all(20), children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
                gradient: AppColors.goldGradient,
                borderRadius: BorderRadius.circular(AppRadii.lg)),
            child: const Column(children: [
              AppIcon(Icons.share_rounded, size: 48, color: AppColors.onPrimary),
              SizedBox(height: 12),
              Text('ادعُ صديقًا، واكسبا معًا',
                  style: TextStyle(
                      color: AppColors.onPrimary,
                      fontSize: 20,
                      fontWeight: FontWeight.w800)),
              SizedBox(height: 6),
              Text('100 نقطة لك و50 لصديقك عند أول زيارة',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: AppColors.onPrimary)),
            ]),
          ),
          const SizedBox(height: 20),
          const SectionHeader(title: 'رمز الدعوة'),
          const SizedBox(height: 8),
          AppCard(
            child: Row(children: [
              const Expanded(
                  child: Text('AHMAD-2024',
                      style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          letterSpacing: 2))),
              IconButton(
                  onPressed: () {},
                  icon: const AppIcon(Icons.copy_rounded,
                      color: AppColors.primaryDark)),
            ]),
          ),
          const SizedBox(height: 16),
          const Row(children: [
            Expanded(
                child: StatCard(
                    icon: Icons.groups_rounded, label: 'دعوات ناجحة', value: '7')),
            SizedBox(width: 12),
            Expanded(
                child: StatCard(
                    icon: Icons.stars_rounded,
                    label: 'نقاط مكتسبة',
                    value: '700',
                    highlight: true)),
          ]),
          const SizedBox(height: 20),
          const PrimaryButton(
              label: 'مشاركة الرمز', icon: Icons.share_rounded, onPressed: _noop),
        ]),
      );
}

void _noop() {}
