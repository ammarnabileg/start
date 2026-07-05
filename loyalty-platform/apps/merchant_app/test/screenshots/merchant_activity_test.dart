@Tags(['screenshot'])
library;

// Facsimile screenshot of the merchant Activity Log (سجل النشاط) — mirrors
// features/management/activity_log_screen.dart. 390x844 RTL.
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

class _Act {
  final String name, phone, line, time;
  final IconData icon;
  final Color color;
  const _Act(this.name, this.phone, this.line, this.time, this.icon, this.color);
}

const _items = <_Act>[
  _Act('منى', '0100 123 4567', 'عدّلت مكافأة · قهوة مجانية', '2026/06/19 10:42',
      Icons.edit_outlined, AppColors.info),
  _Act('أحمد', '0111 222 3344', 'منح نقاطًا · +50 نقطة', '2026/06/19 10:31',
      Icons.add_rounded, AppColors.primaryDark),
  _Act('أحمد', '0111 222 3344', 'فشل قراءة QR · رمز منتهٍ أو غير صالح', '2026/06/19 10:30',
      Icons.qr_code_scanner_rounded, AppColors.warning),
  _Act('منى', '0100 123 4567', 'أضافت كوبون · SUMMER20', '2026/06/19 09:58',
      Icons.add_circle_outline_rounded, AppColors.success),
  _Act('سارة', '0122 555 6677', 'سلّمت جائزة · آيفون 15', '2026/06/18 20:14',
      Icons.casino_rounded, AppColors.goldTier),
  _Act('أحمد', '0111 222 3344', 'طبّق كوبونًا', '2026/06/18 19:03',
      Icons.confirmation_num_outlined, AppColors.error),
];

class _ActivityScreen extends StatelessWidget {
  const _ActivityScreen();
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('سجل النشاط')),
      body: Column(children: [
        // فلتر الموظّف
        const Padding(
          padding: EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: InputDecorator(
            decoration: InputDecoration(
              labelText: 'الموظّف',
              prefixIcon: AppIcon(Icons.badge_outlined),
            ),
            child: Row(children: [
              Expanded(child: Text('كل الموظفين')),
              AppIcon(Icons.keyboard_arrow_down_rounded,
                  color: AppColors.textSecondary),
            ]),
          ),
        ),
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: _items.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) {
              final a = _items[i];
              return AppCard(
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: a.color.withValues(alpha: .14),
                    child: AppIcon(a.icon, size: 18, color: a.color),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text.rich(TextSpan(children: [
                            TextSpan(
                                text: '${a.name} ',
                                style: theme.textTheme.titleSmall
                                    ?.copyWith(fontWeight: FontWeight.w800)),
                            TextSpan(
                                text: '· ${a.phone}',
                                style: theme.textTheme.bodySmall
                                    ?.copyWith(color: AppColors.textSecondary)),
                          ])),
                          const SizedBox(height: 2),
                          Text(a.line, style: theme.textTheme.bodyMedium),
                        ]),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    a.time.replaceFirst(' ', '\n'),
                    textAlign: TextAlign.end,
                    style: theme.textTheme.bodySmall?.copyWith(
                        color: AppColors.textSecondary, fontSize: 11),
                  ),
                ]),
              );
            },
          ),
        ),
      ]),
    );
  }
}

void main() {
  setUpAll(_loadFonts);
  testWidgets('activity log', (t) => _shot(t, 'activity_log', const _ActivityScreen()));
}
