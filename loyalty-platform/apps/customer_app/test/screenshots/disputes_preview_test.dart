@Tags(['screenshot'])
library;

// PREVIEW mockups of the 3-party report/dispute chat (customer / merchant /
// admin). Facsimile only — for design sign-off before implementation.
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

Future<void> _shot(WidgetTester t, String name, Widget child,
    {double w = 390, double h = 844}) async {
  t.view.physicalSize = Size(w, h);
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

// ============ shared thread data ============
class _Msg {
  final String role; // customer | merchant | admin
  final String name;
  final String text;
  final String time;
  final String? staffRole; // for merchant senders: كاشير / مديرة الفرع …
  final String? phone; // shown in the admin panel only
  final bool attachment;
  final String? replyToName; // quoted message author
  final String? replyToText; // quoted message snippet
  const _Msg({
    required this.role,
    required this.name,
    required this.text,
    required this.time,
    this.staffRole,
    this.phone,
    this.attachment = false,
    this.replyToName,
    this.replyToText,
  });
}

// نفس النزاع — لاحظ أن طرف التاجر يبعت من أكثر من موظّف (أحمد/منى).
const _thread = <_Msg>[
  _Msg(
      role: 'customer',
      name: 'سارة',
      phone: '0100 123 4567',
      text: 'ما اتسجّلتش نقاط زيارتي النهاردة رغم إن معايا الفاتورة 🧾',
      time: '10:02',
      attachment: true),
  _Msg(
      role: 'merchant',
      name: 'أحمد',
      staffRole: 'كاشير',
      phone: '0111 222 3344',
      text: 'أهلًا سارة 👋 ممكن رقم الفاتورة؟ بنراجعها حالًا.',
      time: '10:15'),
  _Msg(
      role: 'customer',
      name: 'سارة',
      phone: '0100 123 4567',
      text: 'INV-4471',
      time: '10:16',
      replyToName: 'أحمد · المتجر',
      replyToText: 'ممكن رقم الفاتورة؟ بنراجعها حالًا.'),
  _Msg(
      role: 'merchant',
      name: 'منى',
      staffRole: 'مديرة الفرع',
      phone: '0122 555 6677',
      text: 'تمام! ضفنا 50 نقطة تعويض ✅',
      time: '10:20'),
  _Msg(
      role: 'admin',
      name: 'إدارة المنصّة',
      text: 'تم التحقق من المعاملة وإغلاق النزاع. شكرًا للطرفين 🌟',
      time: '10:32',
      replyToName: 'منى · المتجر',
      replyToText: 'ضفنا 50 نقطة تعويض ✅'),
];

({String label, Color color, IconData icon}) _roleStyle(String role) => switch (role) {
      'merchant' => (label: 'المتجر', color: AppColors.primaryDark, icon: Icons.storefront_rounded),
      'admin' => (label: 'إدارة المنصّة', color: AppColors.info, icon: Icons.shield_outlined),
      _ => (label: 'عميل', color: AppColors.textSecondary, icon: Icons.person_rounded),
    };

Widget _attachmentChip(bool onDark) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: onDark ? Colors.white.withValues(alpha: .18) : AppColors.surface,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
            color: onDark ? Colors.white24 : AppColors.textSecondary.withValues(alpha: .2)),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        AppIcon(Icons.image_outlined,
            size: 16, color: onDark ? AppColors.onPrimary : AppColors.primaryDark),
        const SizedBox(width: 6),
        Text('صورة الفاتورة.jpg',
            style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: onDark ? AppColors.onPrimary : AppColors.textPrimary)),
      ]),
    );

Widget _bubble(BuildContext c, _Msg m, String viewer, {bool showPhone = false}) {
  final me = m.role == viewer;
  final rs = _roleStyle(m.role);
  final bg = me
      ? AppColors.primary
      : switch (m.role) {
          'merchant' => AppColors.primaryLight,
          'admin' => AppColors.info.withValues(alpha: .14),
          _ => AppColors.surfaceCream,
        };
  final fg = me ? AppColors.onPrimary : AppColors.textPrimary;

  // سطر الهوية: الاسم · الدور (+ دور الموظّف) [+ الموبايل في الأدمن فقط].
  final header = StringBuffer('${m.name} · ${rs.label}');
  if (m.staffRole != null) header.write(' (${m.staffRole})');
  if (showPhone && m.phone != null) header.write('  ·  ${m.phone}');

  return Align(
    alignment: me ? AlignmentDirectional.centerStart : AlignmentDirectional.centerEnd,
    child: Container(
      constraints: const BoxConstraints(maxWidth: 320),
      margin: const EdgeInsets.symmetric(vertical: 5),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          AppIcon(rs.icon,
              size: 13, color: me ? AppColors.onPrimary.withValues(alpha: .9) : rs.color),
          const SizedBox(width: 4),
          Flexible(
            child: Text(header.toString(),
                style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: me ? AppColors.onPrimary.withValues(alpha: .9) : rs.color)),
          ),
          if (!me) ...[
            const SizedBox(width: 6),
            AppIcon(Icons.format_quote_rounded,
                size: 15, color: AppColors.textSecondary.withValues(alpha: .8)),
          ],
        ]),
        // اقتباس الرسالة المُردّ عليها (Reply)
        if (m.replyToName != null) ...[
          const SizedBox(height: 8),
          Container(
            padding: const EdgeInsets.fromLTRB(10, 6, 10, 6),
            decoration: BoxDecoration(
              color: me ? Colors.white.withValues(alpha: .18) : Colors.black.withValues(alpha: .04),
              borderRadius: BorderRadius.circular(8),
              border: BorderDirectional(
                start: BorderSide(
                    color: me ? Colors.white70 : AppColors.primary, width: 3),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(m.replyToName!,
                    style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        color: me ? AppColors.onPrimary : AppColors.primaryDark)),
                const SizedBox(height: 1),
                Text(m.replyToText ?? '',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 11,
                        color: me
                            ? AppColors.onPrimary.withValues(alpha: .85)
                            : AppColors.textSecondary)),
              ],
            ),
          ),
        ],
        const SizedBox(height: 6),
        Text(m.text, style: TextStyle(color: fg, height: 1.45, fontSize: 14)),
        if (m.attachment) ...[const SizedBox(height: 8), _attachmentChip(me)],
        const SizedBox(height: 4),
        Align(
          alignment: AlignmentDirectional.centerEnd,
          child: Text(m.time,
              style: TextStyle(
                  fontSize: 10,
                  color: me ? AppColors.onPrimary.withValues(alpha: .75) : AppColors.textSecondary)),
        ),
      ]),
    ),
  );
}

Widget _subjectBanner() => Container(
      width: double.infinity,
      color: AppColors.surfaceCream,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(children: [
        const AppIcon(Icons.confirmation_num_outlined, size: 18, color: AppColors.primaryDark),
        const SizedBox(width: 8),
        const Expanded(
          child: Text('عن: عملية نقاط — فاتورة INV-4471',
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
          decoration: BoxDecoration(
              color: AppColors.success.withValues(alpha: .16),
              borderRadius: BorderRadius.circular(20)),
          child: const Text('محلول',
              style: TextStyle(
                  fontSize: 11, fontWeight: FontWeight.w800, color: AppColors.success)),
        ),
      ]),
    );

Widget _inputBar(String hint) => Material(
      color: AppColors.surface,
      elevation: 8,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
          child: Row(children: [
            Expanded(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                  color: AppColors.surfaceCream,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: Text(hint,
                    style: const TextStyle(color: AppColors.textSecondary, fontSize: 14)),
              ),
            ),
            const SizedBox(width: 8),
            Container(
              width: 46,
              height: 46,
              decoration: const BoxDecoration(
                  color: AppColors.primary, shape: BoxShape.circle),
              child: const AppIcon(Icons.send_rounded, color: AppColors.onPrimary),
            ),
          ]),
        ),
      ),
    );

// ============ Mobile chat (customer / merchant) ============
class _MobileChat extends StatelessWidget {
  final String viewer; // customer | merchant
  const _MobileChat(this.viewer);
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: const Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('نزاع #1842', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            Text('عميل: سارة · مقهى الرواق',
                style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
          ],
        ),
      ),
      body: Column(children: [
        _subjectBanner(),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            children: [for (final m in _thread) _bubble(context, m, viewer)],
          ),
        ),
        _inputBar('اكتب رسالة…'),
      ]),
    );
  }
}

// ============ Admin (web panel) chat ============
class _AdminChat extends StatelessWidget {
  const _AdminChat();

  Widget _navItem(String label, String icon, {bool active = false}) => Container(
        margin: const EdgeInsets.only(bottom: 4),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: active ? const Color(0xFFF59E0B) : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(children: [
          Text(icon, style: const TextStyle(fontSize: 16)),
          const SizedBox(width: 12),
          Text(label,
              style: TextStyle(
                  color: active ? Colors.white : const Color(0xFFCBD5E1),
                  fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                  fontSize: 14)),
        ]),
      );

  Widget _partyBadge(String role) {
    final rs = _roleStyle(role);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
          color: rs.color.withValues(alpha: .12),
          borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        AppIcon(rs.icon, size: 14, color: rs.color),
        const SizedBox(width: 6),
        Text(rs.label,
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: rs.color)),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: const Color(0xFFF3F4F6),
      body: Row(children: [
        // sidebar
        Container(
          width: 240,
          color: const Color(0xFF111827),
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(6, 6, 6, 16),
              child: Text('Hatchy',
                  style: TextStyle(
                      color: Color(0xFFF59E0B),
                      fontSize: 24,
                      fontWeight: FontWeight.w900)),
            ),
            _navItem('لوحة التحكم', '▣'),
            _navItem('التجار (CRM)', '🏪'),
            _navItem('المستخدمون', '👥'),
            _navItem('البلاغات والنزاعات', '⚑', active: true),
            _navItem('التقييمات', '⭐'),
            _navItem('مركز المحتوى', '📝'),
          ]),
        ),
        // main
        Expanded(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                Text('نزاع #1842',
                    style: theme.textTheme.headlineSmall
                        ?.copyWith(fontWeight: FontWeight.w900)),
                const SizedBox(width: 14),
                _partyBadge('customer'),
                const SizedBox(width: 6),
                _partyBadge('merchant'),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFE5E7EB))),
                  child: const Row(children: [
                    Text('الحالة: ', style: TextStyle(color: AppColors.textSecondary)),
                    Text('محلول ▾', style: TextStyle(fontWeight: FontWeight.w800)),
                  ]),
                ),
                const SizedBox(width: 10),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
                  decoration: BoxDecoration(
                      color: const Color(0xFFDC2626),
                      borderRadius: BorderRadius.circular(10)),
                  child: const Text('إغلاق النزاع',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
                ),
              ]),
              const SizedBox(height: 10),
              Row(children: [
                const AppIcon(Icons.confirmation_num_outlined,
                    size: 18, color: AppColors.primaryDark),
                const SizedBox(width: 8),
                Text('عن: عملية نقاط — فاتورة INV-4471',
                    style: theme.textTheme.bodyMedium
                        ?.copyWith(fontWeight: FontWeight.w700)),
              ]),
              const SizedBox(height: 16),
              // conversation card
              Expanded(
                child: Container(
                  decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE5E7EB))),
                  padding: const EdgeInsets.all(20),
                  child: ListView(
                    children: [
                      for (final m in _thread)
                        _bubble(context, m, 'admin', showPhone: true)
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              // admin reply box
              Container(
                decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE5E7EB))),
                padding: const EdgeInsets.all(14),
                child: Column(children: [
                  // مؤشّر «ترد على رسالة معيّنة»
                  Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceCream,
                      borderRadius: BorderRadius.circular(10),
                      border: const BorderDirectional(
                          start: BorderSide(color: AppColors.primary, width: 3)),
                    ),
                    child: Row(children: [
                      const AppIcon(Icons.format_quote_rounded,
                          size: 16, color: AppColors.primaryDark),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Text('ترد على: منى · المتجر (مديرة الفرع) · 0122 555 6677',
                                style: TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primaryDark)),
                            Text('ضفنا 50 نقطة تعويض ✅',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                    fontSize: 12, color: AppColors.textSecondary)),
                          ],
                        ),
                      ),
                      const AppIcon(Icons.cancel_outlined,
                          size: 18, color: AppColors.textSecondary),
                    ]),
                  ),
                  Row(children: [
                  Expanded(
                    child: Container(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                      decoration: BoxDecoration(
                          color: const Color(0xFFF9FAFB),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFFE5E7EB))),
                      child: const Text('اكتب ردًّا إداريًا للطرفين…',
                          style: TextStyle(color: AppColors.textSecondary)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
                    decoration: BoxDecoration(
                        color: const Color(0xFFF59E0B),
                        borderRadius: BorderRadius.circular(10)),
                    child: const Text('إرسال كردّ إداري',
                        style: TextStyle(
                            color: Colors.white, fontWeight: FontWeight.w800)),
                  ),
                ]),
                ]),
              ),
            ]),
          ),
        ),
      ]),
    );
  }
}

void main() {
  setUpAll(_loadFonts);
  testWidgets('d1 customer chat', (t) => _shot(t, 'dispute_1_customer', const _MobileChat('customer')));
  testWidgets('d2 merchant chat', (t) => _shot(t, 'dispute_2_merchant', const _MobileChat('merchant')));
  testWidgets('d3 admin chat',
      (t) => _shot(t, 'dispute_3_admin', const _AdminChat(), w: 1180, h: 820));
}
