@Tags(['screenshot'])
library;

// Screenshot of the REAL shared ReportChatView (loyalty_core) with sample data —
// proves the shipped widget renders (not a facsimile).
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
  t.view.physicalSize = const Size(390, 844);
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
  Directory('test/screenshots/out').createSync(recursive: true);
  File('test/screenshots/out/$name.png').writeAsBytesSync(bytes!.buffer.asUint8List());
  exit(0);
}

ReportMessage _m(String id, String role, String name, String body,
        {String? staffRole, bool mine = false, String? rName, String? rBody}) =>
    ReportMessage(
      id: id,
      senderRole: role,
      senderName: name,
      staffRole: staffRole,
      body: body,
      createdAt: DateTime(2026, 6, 18, 10, 16),
      isMine: mine,
      replyToId: rName == null ? null : 'x',
      replyToName: rName,
      replyToBody: rBody,
    );

void main() {
  setUpAll(_loadFonts);
  testWidgets('real customer chat', (t) {
    final messages = [
      _m('1', 'customer', 'سارة', 'ما اتسجّلتش نقاط زيارتي النهاردة رغم إن معايا الفاتورة 🧾', mine: true),
      _m('2', 'merchant', 'أحمد', 'أهلًا سارة 👋 ممكن رقم الفاتورة؟ بنراجعها حالًا.', staffRole: 'cashier'),
      _m('3', 'customer', 'سارة', 'INV-4471',
          mine: true, rName: 'أحمد · المتجر', rBody: 'ممكن رقم الفاتورة؟ بنراجعها حالًا.'),
      _m('4', 'merchant', 'منى', 'تمام! ضفنا 50 نقطة تعويض ✅', staffRole: 'branch_manager'),
      _m('5', 'admin', 'إدارة المنصّة', 'تم التحقق من المعاملة وإغلاق النزاع. شكرًا للطرفين 🌟'),
    ];
    return _shot(
      t,
      'report_chat_real',
      Scaffold(
        body: ReportChatView(
          title: 'مقهى الرواق',
          subtitle: 'محادثة البلاغ',
          subjectLabel: 'عملية نقاط — فاتورة INV-4471',
          status: 'resolved',
          messages: messages,
          onSend: (_, __) async {},
        ),
      ),
    );
  });
}
