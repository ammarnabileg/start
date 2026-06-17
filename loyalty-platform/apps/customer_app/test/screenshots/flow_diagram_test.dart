// يرسم دياجرام الطبقات لتدفّق "استلام هدية" ويصدّره كصورة.
// flutter test test/screenshots/flow_diagram_test.dart
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
      scaffoldBackgroundColor: AppColors.background);
  return base.copyWith(
      textTheme: base.textTheme.apply(
          bodyColor: AppColors.textPrimary, displayColor: AppColors.textPrimary));
}

class _Step {
  final String actor;
  final String title;
  final String desc;
  final IconData icon;
  final Color color;
  const _Step(this.actor, this.title, this.desc, this.icon, this.color);
}

const _steps = <_Step>[
  _Step('تطبيق العميل', 'اطلب الاستلام',
      'تضغط على الهدية → يظهر QR متجدّد للحماية.',
      Icons.qr_code_2_rounded, AppColors.primaryDark),
  _Step('تطبيق الكاشير', 'مسح الرمز',
      'الكاشير يمسح رمز الهدية المتغيّر.',
      Icons.qr_code_scanner_rounded, AppColors.info),
  _Step('Edge Function · redeem-prize', 'تحقّق + بدء التسليم',
      'يتحقق من التوكن والصلاحية ونطاق الفرع، ثم يحوّل الحالة إلى delivering.',
      Icons.shield_outlined, AppColors.textPrimary),
  _Step('قاعدة البيانات (RLS)', 'تحديث آمن',
      'user_prizes → delivering + تسجيل الكاشير/الفرع + إشعار العميل.',
      Icons.dashboard_rounded, AppColors.success),
  _Step('Realtime', 'بثّ لحظي',
      'حالة الهدية تُبثّ فورًا إلى شاشة العميل.',
      Icons.bolt_rounded, AppColors.warning),
  _Step('تطبيق العميل', 'تأكيد الاستلام',
      'نافذة "يتم تسليمك …" — موافق / إلغاء / إبلاغ.',
      Icons.card_giftcard_rounded, AppColors.primaryDark),
  _Step('Edge Function · confirm-prize', 'الحسم',
      'موافق → redeemed · إلغاء → won (تعود متاحة).',
      Icons.check_circle_rounded, AppColors.textPrimary),
  _Step('النتيجة', 'تم الاستلام ✓',
      'تظهر شاشة النجاح، ويتحدّث السجل والنقاط.',
      Icons.verified_outlined, AppColors.success),
];

void main() {
  setUpAll(_loadFonts);

  testWidgets('redeem flow diagram', (t) async {
    const w = 460.0, h = 1480.0;
    t.view.physicalSize = const Size(w, h);
    t.view.devicePixelRatio = 1.0;
    addTearDown(t.view.resetPhysicalSize);
    addTearDown(t.view.resetDevicePixelRatio);

    final key = GlobalKey();
    await t.pumpWidget(RepaintBoundary(
      key: key,
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: _theme(),
        home: const Directionality(
            textDirection: TextDirection.rtl, child: _FlowDiagram()),
      ),
    ));
    await t.pump(const Duration(milliseconds: 400));

    final boundary =
        key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 1.5);
    final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
    final dir = Directory('test/screenshots/out')..createSync(recursive: true);
    File('${dir.path}/redeem_flow_layers.png')
        .writeAsBytesSync(bytes!.buffer.asUint8List());
    exit(0);
  });
}

class _FlowDiagram extends StatelessWidget {
  const _FlowDiagram();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppColors.heroGradient),
        child: Column(children: [
          const SizedBox(height: 28),
          const AppIcon(Icons.card_giftcard_rounded,
              size: 40, color: AppColors.onPrimary),
          const SizedBox(height: 8),
          const Text('طبقات استلام الهدية',
              style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                  color: AppColors.onPrimary)),
          const SizedBox(height: 4),
          const Text('من الطلب حتى التأكيد — تأكيد طرفين',
              style: TextStyle(color: AppColors.onPrimary)),
          const SizedBox(height: 16),
          Expanded(
            child: Container(
              width: double.infinity,
              decoration: const BoxDecoration(
                color: AppColors.background,
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
              ),
              padding: const EdgeInsets.fromLTRB(16, 22, 16, 16),
              child: Column(
                children: [
                  for (var i = 0; i < _steps.length; i++)
                    _StepRow(
                        index: i,
                        step: _steps[i],
                        isLast: i == _steps.length - 1),
                ],
              ),
            ),
          ),
        ]),
      ),
    );
  }
}

class _StepRow extends StatelessWidget {
  final int index;
  final _Step step;
  final bool isLast;
  const _StepRow(
      {required this.index, required this.step, required this.isLast});

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // الخط الزمني (رقم + موصّل).
          Column(children: [
            Container(
              width: 36,
              height: 36,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                  gradient: AppColors.buttonGradient, shape: BoxShape.circle),
              child: Text('${index + 1}',
                  style: const TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w900)),
            ),
            if (!isLast)
              Expanded(
                child: Container(width: 3, color: AppColors.primaryLight),
              ),
          ]),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 12),
              child: AppCard(
                padding: const EdgeInsets.all(14),
                child: Row(children: [
                  AppIconBadge(step.icon, size: 44, color: step.color),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                              color: step.color.withValues(alpha: .12),
                              borderRadius: BorderRadius.circular(20)),
                          child: Text(step.actor,
                              style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  color: step.color)),
                        ),
                        const SizedBox(height: 4),
                        Text(step.title,
                            style: const TextStyle(
                                fontWeight: FontWeight.w800, fontSize: 15)),
                        const SizedBox(height: 2),
                        Text(step.desc,
                            style: const TextStyle(
                                color: AppColors.textSecondary, fontSize: 12)),
                      ],
                    ),
                  ),
                ]),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
