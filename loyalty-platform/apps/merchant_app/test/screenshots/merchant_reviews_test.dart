@Tags(['screenshot'])
library;

// Facsimile screenshots of the merchant Reviews screen (list + reply sheet),
// 390x844 RTL. Mirrors features/management/reviews_screen.dart.
// Run: flutter test --run-skipped --plain-name "<name>" test/screenshots/merchant_reviews_test.dart
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
  final dir = Directory('test/screenshots/out')..createSync(recursive: true);
  File('${dir.path}/$name.png').writeAsBytesSync(bytes!.buffer.asUint8List());
  exit(0);
}

// ---- shared bits ----
Widget _stars(int n, {double size = 14}) => Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (var i = 1; i <= 5; i++)
          AppIcon(Icons.star_rounded,
              size: size,
              color: i <= n
                  ? AppColors.goldTier
                  : AppColors.textSecondary.withValues(alpha: .3)),
      ],
    );

Widget _summary(BuildContext context) => AppCard(
      child: Row(children: [
        Column(children: [
          Text('4.5',
              style: Theme.of(context)
                  .textTheme
                  .displaySmall
                  ?.copyWith(fontWeight: FontWeight.w900)),
          _stars(5, size: 16),
        ]),
        const SizedBox(width: 18),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('تقييم متجرك',
                  style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 4),
              Text('بناءً على 38 مراجعة',
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: AppColors.textSecondary)),
            ],
          ),
        ),
      ]),
    );

Widget _reviewCard(
  BuildContext context, {
  required String name,
  required int rating,
  required String comment,
  String? reply,
  bool hidden = false,
}) {
  final theme = Theme.of(context);
  return AppCard(
    margin: const EdgeInsets.only(bottom: 12),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        CircleAvatar(
            radius: 20,
            backgroundColor: AppColors.primaryLight,
            child: Text(name.substring(0, 1),
                style: const TextStyle(fontWeight: FontWeight.w800))),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Flexible(child: Text(name, style: theme.textTheme.titleMedium)),
              if (hidden) ...[
                const SizedBox(width: 8),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                      color: AppColors.textSecondary.withValues(alpha: .15),
                      borderRadius: BorderRadius.circular(AppRadii.pill)),
                  child: const Text('مخفية بالإشراف',
                      style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textSecondary)),
                ),
              ],
            ]),
            const SizedBox(height: 2),
            _stars(rating),
          ]),
        ),
        Text('2026/06/14',
            style: theme.textTheme.bodySmall
                ?.copyWith(color: AppColors.textSecondary)),
      ]),
      const SizedBox(height: 10),
      Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
            color: AppColors.surfaceCream,
            borderRadius: BorderRadius.circular(AppRadii.md)),
        child: Text(comment),
      ),
      if (reply != null) ...[
        const SizedBox(height: 10),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
              color: AppColors.primaryLight,
              borderRadius: BorderRadius.circular(AppRadii.md)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('ردّك',
                style: theme.textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark)),
            const SizedBox(height: 4),
            Text(reply),
          ]),
        ),
      ],
      const SizedBox(height: 6),
      Align(
        alignment: AlignmentDirectional.centerStart,
        child: TextButton.icon(
          onPressed: () {},
          icon: AppIcon(reply != null ? Icons.edit_outlined : Icons.send_rounded,
              size: 18),
          label: Text(reply != null ? 'تعديل الردّ' : 'الردّ'),
        ),
      ),
    ]),
  );
}

class _ReviewsList extends StatelessWidget {
  const _ReviewsList();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('التقييمات')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _summary(context),
          const SizedBox(height: 16),
          _reviewCard(context,
              name: 'سارة',
              rating: 5,
              comment: 'قهوة ممتازة وخدمة سريعة، والمكان مريح جدًا للعمل.',
              reply: 'شكرًا لكِ يا سارة 🌹 سعداء بزيارتك!'),
          _reviewCard(context,
              name: 'خالد',
              rating: 4,
              comment: 'تجربة جيدة بشكل عام، بس الانتظار كان طويل وقت الذروة.'),
        ],
      ),
    );
  }
}

/// شاشة الردّ (الـ bottom sheet) — معروضة أسفل الشاشة فوق خلفية معتمة.
class _ReplySheet extends StatelessWidget {
  const _ReplySheet();
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: const Color(0xCC1A1A1A),
      body: SafeArea(
        child: Column(
          children: [
            // ترويسة العميل الذي نردّ عليه (لمحة عن خلفية القائمة).
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: _reviewCard(context,
                  name: 'خالد',
                  rating: 4,
                  comment:
                      'تجربة جيدة بشكل عام، بس الانتظار كان طويل وقت الذروة.'),
            ),
            const Spacer(),
            // الـ bottom sheet
            Container(
              width: double.infinity,
              decoration: const BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: AppColors.textSecondary.withValues(alpha: .3),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text('الردّ على خالد', style: theme.textTheme.titleLarge),
                  const SizedBox(height: 12),
                  TextField(
                    controller: TextEditingController(
                        text:
                            'نعتذر عن الانتظار يا خالد، نعمل على تسريع الخدمة وقت الذروة 🙏'),
                    maxLines: 4,
                    decoration: const InputDecoration(
                      hintText: 'اكتب ردًّا لطيفًا لعميلك…',
                    ),
                  ),
                  const SizedBox(height: 12),
                  PrimaryButton(label: 'حفظ الردّ', onPressed: () {}),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

void main() {
  setUpAll(_loadFonts);
  testWidgets('m1 reviews list', (t) => _shot(t, 'm1_reviews', const _ReviewsList()));
  testWidgets('m2 reply sheet', (t) => _shot(t, 'm2_reply', const _ReplySheet()));
}
