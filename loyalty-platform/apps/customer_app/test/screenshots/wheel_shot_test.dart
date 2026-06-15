// لقطة مفردة لشاشة عجلة الحظ.
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
  testWidgets('lucky wheel', (t) async {
    await _loadFonts();
    t.view.physicalSize = const Size(390, 860);
    t.view.devicePixelRatio = 1;
    addTearDown(t.view.resetPhysicalSize);
    addTearDown(t.view.resetDevicePixelRatio);

    const segs = [
      WheelSegment(id: '1', wheelId: 'w', label: 'قهوة مجانية', kind: SegmentKind.reward),
      WheelSegment(id: '2', wheelId: 'w', label: 'خصم 20%', kind: SegmentKind.coupon),
      WheelSegment(id: '3', wheelId: 'w', label: '+50 نقطة', kind: SegmentKind.points, pointsValue: 50),
      WheelSegment(id: '4', wheelId: 'w', label: 'حظ أوفر', kind: SegmentKind.nothing),
      WheelSegment(id: '5', wheelId: 'w', label: 'كيكة', kind: SegmentKind.reward),
      WheelSegment(id: '6', wheelId: 'w', label: 'خصم 10%', kind: SegmentKind.coupon),
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
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(
            body: ListView(padding: EdgeInsets.zero, children: [
              const HeroHeader(
                  title: 'عجلة الحظ', subtitle: 'مقهى الرواق'),
              const SizedBox(height: 16),
              Center(
                child: LuckyWheelView(
                  segments: segs,
                  controller: LuckyWheelController(),
                  size: 300,
                ),
              ),
              const SizedBox(height: 16),
              const Padding(
                padding: EdgeInsets.all(16),
                child: Column(children: [
                  AppCard(
                    color: AppColors.surfaceCream,
                    child: Row(children: [
                      Icon(Icons.stars_rounded, color: AppColors.primaryDark),
                      SizedBox(width: 10),
                      Expanded(child: Text('تكلفة اللفّة')),
                      Text('50 نقطة',
                          style: TextStyle(fontWeight: FontWeight.w800)),
                    ]),
                  ),
                  SizedBox(height: 16),
                  PrimaryButton(
                      label: 'لِف الآن',
                      icon: Icons.casino_rounded,
                      onPressed: _noop),
                ]),
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
    File('${dir.path}/11_lucky_wheel.png')
        .writeAsBytesSync(bytes!.buffer.asUint8List());
  });
}

void _noop() {}
