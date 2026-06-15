// يرندر واجهات تمثيلية (بمكوّنات الـ Design System الحقيقية + بيانات نموذجية)
// ويصدّرها كصور PNG. شغّله: flutter test test/screenshots/screenshot_test.dart
import 'dart:io';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:qr_flutter/qr_flutter.dart';

const _w = 390.0;
const _h = 844.0;
const _scale = 1.0;

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
    textTheme: base.textTheme.apply(
      bodyColor: AppColors.textPrimary,
      displayColor: AppColors.textPrimary,
      fontFamily: 'Tajawal',
    ),
  );
}

Future<void> _shot(WidgetTester tester, String name, Widget child) async {
  tester.view.physicalSize = const Size(_w * _scale, _h * _scale);
  tester.view.devicePixelRatio = _scale;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  final key = GlobalKey();
  await tester.pumpWidget(RepaintBoundary(
    key: key,
    child: MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: _theme(),
      locale: const Locale('ar'),
      home: Directionality(textDirection: TextDirection.rtl, child: child),
    ),
  ));
  await tester.pumpAndSettle(const Duration(milliseconds: 600));

  final boundary =
      key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  final image = await boundary.toImage(pixelRatio: _scale);
  final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
  image.dispose(); // مهم: تحرير الذاكرة بين الصور
  final dir = Directory('test/screenshots/out')..createSync(recursive: true);
  File('${dir.path}/$name.png').writeAsBytesSync(bytes!.buffer.asUint8List());
}

void main() {
  setUpAll(_loadFonts);

  testWidgets('01 customer — QR home', (t) async {
    await _shot(t, '01_customer_qr', const _QrHomeMock());
  });
  testWidgets('02 customer — my stores', (t) async {
    await _shot(t, '02_customer_stores', const _StoresMock());
  });
  testWidgets('03 customer — store detail', (t) async {
    await _shot(t, '03_customer_store_detail', const _StoreDetailMock());
  });
  testWidgets('04 customer — leaderboard', (t) async {
    await _shot(t, '04_customer_leaderboard', const _LeaderboardMock());
  });
  testWidgets('05 merchant — dashboard', (t) async {
    await _shot(t, '05_merchant_dashboard', const _DashboardMock());
  });
  testWidgets('06 merchant — customer profile', (t) async {
    await _shot(t, '06_merchant_customer', const _CustomerProfileMock());
  });
  testWidgets('07 merchant — customers list', (t) async {
    await _shot(t, '07_merchant_customers', const _CustomersListMock());
  });
  testWidgets('08 merchant — announcements quota', (t) async {
    await _shot(t, '08_merchant_announcements', const _AnnouncementsMock());
  });
}

// ============ Mockups (بمكوّنات حقيقية) ============

class _QrHomeMock extends StatelessWidget {
  const _QrHomeMock();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          const HeroHeader(
            title: 'أهلاً، أحمد',
            subtitle: 'أرِ هذا الرمز للكاشير عند الدفع',
          ),
          Expanded(
            child: Center(
              child: AppCard(
                padding: const EdgeInsets.all(28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
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
                    Row(mainAxisSize: MainAxisSize.min, children: const [
                      SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                              value: .6,
                              strokeWidth: 3,
                              color: AppColors.primary)),
                      SizedBox(width: 8),
                      Text('يتجدّد خلال 18 ث'),
                    ]),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StoresMock extends StatelessWidget {
  const _StoresMock();
  @override
  Widget build(BuildContext context) {
    final stores = [
      ('مقهى الرواق', 'فرع العليا · فضي', 350),
      ('مطعم بيتزا تايم', 'الفرع الرئيسي · ذهبي', 1240),
      ('صالون لمسة', 'برونزي', 80),
    ];
    return Scaffold(
      appBar: AppBar(title: const Text('متاجري'), centerTitle: true),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          for (final s in stores)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: AppCard(
                child: Row(children: [
                  Container(
                      width: 56,
                      height: 56,
                      decoration: BoxDecoration(
                          color: AppColors.surfaceCream,
                          borderRadius: BorderRadius.circular(16)),
                      child: const Icon(Icons.storefront,
                          color: AppColors.primaryDark)),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(s.$1,
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 4),
                        Text(s.$2,
                            style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                  PointsBadge(points: s.$3),
                ]),
              ),
            ),
        ],
      ),
    );
  }
}

class _StoreDetailMock extends StatelessWidget {
  const _StoreDetailMock();
  @override
  Widget build(BuildContext context) {
    final rewards = [
      ('قهوة مجانية', 100, true),
      ('خصم 20%', 250, true),
      ('كيكة', 400, false),
      ('وجبة', 600, false),
    ];
    return Scaffold(
      body: ListView(
        padding: EdgeInsets.zero,
        children: [
          const HeroHeader(title: 'مقهى الرواق', subtitle: 'مقهى · فرع العليا'),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionHeader(title: 'حالتك'),
                const SizedBox(height: 8),
                Row(children: const [
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
                  childAspectRatio: 1.3,
                  children: [
                    for (final r in rewards)
                      Opacity(
                        opacity: r.$3 ? 1 : .5,
                        child: AppCard(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(Icons.card_giftcard_rounded,
                                  size: 30, color: AppColors.primaryDark),
                              const SizedBox(height: 8),
                              Text(r.$1,
                                  style:
                                      Theme.of(context).textTheme.titleMedium),
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
        ],
      ),
    );
  }
}

class _LeaderboardMock extends StatelessWidget {
  const _LeaderboardMock();
  @override
  Widget build(BuildContext context) {
    Widget pillar(String medal, String name, int pts, double h, Gradient g) =>
        Expanded(
          child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
            Text(medal, style: const TextStyle(fontSize: 28)),
            const SizedBox(height: 4),
            Text(name,
                style: const TextStyle(fontWeight: FontWeight.w700),
                maxLines: 1),
            const SizedBox(height: 6),
            Container(
              height: h,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                  gradient: g,
                  borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(20))),
              child: Column(children: [
                Text('$pts',
                    style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.onPrimary)),
                const Text('نقطة',
                    style:
                        TextStyle(fontSize: 11, color: AppColors.onPrimary)),
              ]),
            ),
          ]),
        );
    return Scaffold(
      appBar: AppBar(title: const Text('لوحة الصدارة'), centerTitle: true),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
          pillar('🥈', 'سارة', 1820, 120,
              const LinearGradient(colors: [AppColors.silver, Color(0xFFCED4DA)])),
          const SizedBox(width: 10),
          pillar('🥇', 'أحمد', 2540, 165, AppColors.goldGradient),
          const SizedBox(width: 10),
          pillar('🥉', 'محمد', 1450, 100,
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
                        style:
                            const TextStyle(fontWeight: FontWeight.w800))),
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

class _DashboardMock extends StatelessWidget {
  const _DashboardMock();
  @override
  Widget build(BuildContext context) {
    final stats = [
      ('العملاء', '1,240', Icons.groups_2_rounded),
      ('زيارات اليوم', '86', Icons.event_available_rounded),
      ('نقاط موزّعة', '52,300', Icons.stars_rounded),
      ('مكافآت', '318', Icons.card_giftcard_rounded),
    ];
    return Scaffold(
      body: ListView(padding: EdgeInsets.zero, children: [
        const HeroHeader(
            title: 'لوحة التحكم',
            subtitle: 'مقهى الرواق',
            gradient: AppColors.darkGradient),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.5,
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
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: AppCard(
                  child: Row(children: [
                    const Icon(Icons.bolt_rounded,
                        color: AppColors.primaryDark),
                    const SizedBox(width: 12),
                    Expanded(child: Text(a.$1)),
                    Text(a.$2,
                        style: Theme.of(context).textTheme.bodySmall),
                  ]),
                ),
              ),
          ]),
        ),
      ]),
    );
  }
}

class _CustomerProfileMock extends StatelessWidget {
  const _CustomerProfileMock();
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
                    style: TextStyle(
                        fontSize: 20, fontWeight: FontWeight.w800))),
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
              child: const Text('عميل جديد 🎉',
                  style: TextStyle(
                      color: AppColors.success, fontWeight: FontWeight.w700)),
            ),
          ]),
        ),
        const SizedBox(height: 16),
        Row(children: const [
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
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(a.$2, size: 30, color: AppColors.primaryDark),
                    const SizedBox(height: 8),
                    Text(a.$1,
                        style: Theme.of(context).textTheme.titleMedium),
                  ],
                ),
              ),
          ],
        ),
      ]),
    );
  }
}

class _CustomersListMock extends StatelessWidget {
  const _CustomersListMock();
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
            padding: EdgeInsets.only(left: 8),
            child: Icon(Icons.campaign_outlined)),
      ]),
      body: Column(children: [
        const Padding(
          padding: EdgeInsets.all(16),
          child: TextField(
            decoration: InputDecoration(
                hintText: 'ابحث بالاسم أو رقم الجوال',
                prefixIcon: Icon(Icons.search_rounded)),
          ),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            children: [
              for (final c in cs)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: AppCard(
                    child: Row(children: [
                      CircleAvatar(
                          radius: 22,
                          backgroundColor: AppColors.primaryLight,
                          child: Text(c.$1.characters.first,
                              style: const TextStyle(
                                  fontWeight: FontWeight.w800))),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(c.$1,
                                style:
                                    Theme.of(context).textTheme.titleMedium),
                            Text(c.$2,
                                style:
                                    Theme.of(context).textTheme.bodySmall),
                          ],
                        ),
                      ),
                      PointsBadge(points: c.$3),
                    ]),
                  ),
                ),
            ],
          ),
        ),
      ]),
    );
  }
}

class _AnnouncementsMock extends StatelessWidget {
  const _AnnouncementsMock();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
              child:
                  const Icon(Icons.campaign_rounded, color: AppColors.onPrimary),
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
            Row(children: const [
              Icon(Icons.notifications_active_outlined,
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
                  value: .08,
                  minHeight: 8,
                  backgroundColor: AppColors.surfaceCream,
                  color: AppColors.primary),
            ),
            const SizedBox(height: 6),
            const Text('الحد الأقصى يحدّده مزوّد المنصة.',
                style:
                    TextStyle(color: AppColors.textSecondary, fontSize: 12)),
          ]),
        ),
        const SizedBox(height: 16),
        const SectionHeader(title: 'محتوى الإعلان'),
        const SizedBox(height: 8),
        const AppCard(
          child: Column(children: [
            TextField(
                decoration: InputDecoration(labelText: 'عنوان الإشعار')),
            SizedBox(height: 12),
            TextField(
                maxLines: 3,
                decoration: InputDecoration(labelText: 'النص')),
          ]),
        ),
        const SizedBox(height: 20),
        const PrimaryButton(
            label: 'إرسال', icon: Icons.send_rounded, onPressed: _noop),
      ]),
    );
  }
}

void _noop() {}
