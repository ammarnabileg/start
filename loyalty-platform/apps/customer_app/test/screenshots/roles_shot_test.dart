@Tags(['screenshot'])
library;

// لقطة مفردة لشاشة الأدوار والصلاحيات (مصفوفة الصلاحيات).
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
  testWidgets('roles matrix', (t) async {
    await _loadFonts();
    t.view.physicalSize = const Size(390, 860);
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

    // صلاحيات تجريبية لدور "كاشير".
    const selected = {
      'customers': ['view'],
      'points': ['create'],
      'visits': ['create'],
      'prizes': ['redeem'],
    };
    const resources = ['customers', 'rewards', 'points', 'visits', 'prizes'];

    Widget chip(String label, bool on) => Container(
          margin: const EdgeInsets.only(left: 6),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          decoration: BoxDecoration(
            color: on ? AppColors.primary : AppColors.surfaceCream,
            borderRadius: BorderRadius.circular(AppRadii.pill),
          ),
          child: Text(label,
              style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: on ? AppColors.onPrimary : AppColors.textSecondary)),
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
            appBar: AppBar(title: const Text('تعديل دور: كاشير')),
            body: ListView(padding: const EdgeInsets.all(16), children: [
              const SectionHeader(title: 'الصلاحيات'),
              const SizedBox(height: 8),
              for (final r in resources)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(PermResource.label(r),
                            style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                                color: AppColors.textPrimary)),
                        const SizedBox(height: 10),
                        Wrap(
                          children: [
                            for (final a in PermAction.all)
                              chip(PermAction.label(a),
                                  (selected[r] ?? const []).contains(a)),
                            if (r == 'prizes')
                              chip('تفعيل',
                                  (selected[r] ?? const []).contains('redeem')),
                          ],
                        ),
                      ],
                    ),
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
    File('${dir.path}/12_roles.png')
        .writeAsBytesSync(bytes!.buffer.asUint8List());
  });
}
