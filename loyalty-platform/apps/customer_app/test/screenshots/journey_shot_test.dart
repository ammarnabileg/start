// لقطة مفردة لمسار المستويات (Levels Journey) بهوية Hatchy.
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

void main() {
  testWidgets('levels journey', (t) async {
    await _loadFonts();
    t.view.physicalSize = const Size(390, 980);
    t.view.devicePixelRatio = 1;
    addTearDown(t.view.resetPhysicalSize);
    addTearDown(t.view.resetDevicePixelRatio);

    const levels = [
      LoyaltyLevel(
          id: 'b', merchantId: 'm', name: 'برونزي',
          thresholdLifetimePoints: 0, sortOrder: 0),
      LoyaltyLevel(
          id: 's', merchantId: 'm', name: 'فضي',
          thresholdLifetimePoints: 500, sortOrder: 1),
      LoyaltyLevel(
          id: 'g', merchantId: 'm', name: 'ذهبي',
          thresholdLifetimePoints: 1500, sortOrder: 2),
      LoyaltyLevel(
          id: 'p', merchantId: 'm', name: 'بلاتيني',
          thresholdLifetimePoints: 3000, sortOrder: 3),
    ];

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
        home: const Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(
            body: SingleChildScrollView(
              padding: EdgeInsets.all(16),
              child: LevelsJourney(
                levels: levels,
                lifetimePoints: 1240,
                title: 'رحلة مستوياتك',
              ),
            ),
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
    File('${dir.path}/09_levels_journey.png')
        .writeAsBytesSync(bytes!.buffer.asUint8List());
  });
}
