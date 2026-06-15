// لقطة مفردة للوحة الصدارة بعد استبدال الإيموجي بأيقونات.
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';

Future<void> _loadFonts() async {
  final loader = FontLoader('Tajawal');
  for (final f in ['Tajawal-Regular.ttf', 'Tajawal-Bold.ttf']) {
    final bytes = File('test/screenshots/fonts/$f').readAsBytesSync();
    loader.addFont(Future.value(ByteData.view(bytes.buffer)));
  }
  await loader.load();
}

Widget _pillar(Color medal, String name, int pts, double h, Gradient g) =>
    Expanded(
      child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
        Icon(Icons.workspace_premium_rounded, color: medal, size: 32),
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

void main() {
  testWidgets('leaderboard', (t) async {
    await _loadFonts();
    t.view.physicalSize = const Size(390, 720);
    t.view.devicePixelRatio = 1;
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
            appBar: AppBar(
                title: const Text('لوحة الصدارة'), centerTitle: true),
            body: ListView(padding: const EdgeInsets.all(16), children: [
              Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                _pillar(AppColors.silver, 'سارة', 1820, 120,
                    const LinearGradient(
                        colors: [AppColors.silver, Color(0xFFCED4DA)])),
                const SizedBox(width: 10),
                _pillar(AppColors.goldTier, 'أحمد', 2540, 165,
                    AppColors.goldGradient),
                const SizedBox(width: 10),
                _pillar(AppColors.bronze, 'محمد', 1450, 100,
                    const LinearGradient(
                        colors: [AppColors.bronze, Color(0xFFB07A4A)])),
              ]),
              const SizedBox(height: 16),
              const SectionHeader(title: 'بقية القائمة'),
              for (final e in [('نورة', 980, 4), ('خالد', 820, 5)])
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
          ),
        ),
      ),
    ));
    await t.pumpAndSettle(const Duration(milliseconds: 400));

    final boundary =
        key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final image = await boundary.toImage();
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    image.dispose();
    final dir = Directory('test/screenshots/out')..createSync(recursive: true);
    File('${dir.path}/10_leaderboard_fixed.png')
        .writeAsBytesSync(bytes!.buffer.asUint8List());
  });
}
