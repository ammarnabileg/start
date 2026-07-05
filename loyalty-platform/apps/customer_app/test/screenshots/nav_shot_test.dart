@Tags(['screenshot'])
library;

// لقطة لشاشة فيها الشريط السفلي (زر الـ QR البارز في النص).
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:qr_flutter/qr_flutter.dart';

Future<void> _loadFonts() async {
  final loader = FontLoader('Tajawal');
  for (final f in ['Tajawal-Regular.ttf', 'Tajawal-Bold.ttf']) {
    final bytes = File('test/screenshots/fonts/$f').readAsBytesSync();
    loader.addFont(Future.value(ByteData.view(bytes.buffer)));
  }
  await loader.load();
}

void main() {
  testWidgets('bottom nav', (t) async {
    await _loadFonts();
    t.view.physicalSize = const Size(390, 844);
    t.view.devicePixelRatio = 2;
    addTearDown(t.view.resetPhysicalSize);
    addTearDown(t.view.resetDevicePixelRatio);

    final theme = ThemeData(
      useMaterial3: true,
      fontFamily: 'Tajawal',
      scaffoldBackgroundColor: AppColors.background,
      colorScheme: ColorScheme.fromSeed(
          seedColor: AppColors.primary,
          primary: AppColors.primary,
          onPrimary: AppColors.onPrimary),
    );

    final key = GlobalKey();
    await t.pumpWidget(RepaintBoundary(
      key: key,
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: theme,
        locale: const Locale('ar'),
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(
            extendBody: true,
            bottomNavigationBar: AppBottomNav(
              currentIndex: 0,
              onTap: (_) {},
              items: const [
                AppBottomNavItem(
                    icon: Icons.qr_code_2_rounded,
                    label: 'رمزي',
                    prominent: true),
                AppBottomNavItem(
                    icon: Icons.storefront_outlined,
                    activeIcon: Icons.storefront_rounded,
                    label: 'متاجري'),
                AppBottomNavItem(
                    icon: Icons.notifications_none_rounded,
                    activeIcon: Icons.notifications_rounded,
                    label: 'الإشعارات'),
                AppBottomNavItem(
                    icon: Icons.person_outline_rounded,
                    activeIcon: Icons.person_rounded,
                    label: 'حسابي'),
              ],
            ),
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
                              size: 210,
                              backgroundColor: Colors.white),
                          const SizedBox(height: 16),
                          const Text('أحمد خالد',
                              style: TextStyle(
                                  fontSize: 17,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.textPrimary)),
                          const Text('عضوية: 8F3A2C9D',
                              style: TextStyle(color: AppColors.textSecondary)),
                          const SizedBox(height: 14),
                          const Row(mainAxisSize: MainAxisSize.min, children: [
                            SizedBox(
                                height: 18,
                                width: 18,
                                child: CircularProgressIndicator(
                                    value: .65,
                                    strokeWidth: 3,
                                    color: AppColors.primary)),
                            SizedBox(width: 8),
                            Text('يتجدّد خلال 19 ث'),
                          ]),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ));
    await t.pumpAndSettle(const Duration(milliseconds: 500));

    final boundary =
        key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 2);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    final dir = Directory('test/screenshots/out')..createSync(recursive: true);
    File('${dir.path}/13_bottom_nav.png')
        .writeAsBytesSync(bytes!.buffer.asUint8List());
  });
}
