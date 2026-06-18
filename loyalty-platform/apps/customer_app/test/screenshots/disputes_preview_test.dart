@Tags(['screenshot'])
library;

// PREVIEW mockups (v4 — polished + gamified) of the 3-party report/dispute chat
// (customer / merchant / admin). Facsimile only — for design sign-off.
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

// ============ thread data ============
class _Msg {
  final String role; // customer | merchant | admin
  final String name;
  final String text;
  final String time;
  final String? staffRole;
  final String? phone;
  final bool attachment;
  final String? replyToName;
  final String? replyToText;
  final bool hidden;
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
    this.hidden = false,
  });
}

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
      role: 'customer',
      name: 'سارة',
      phone: '0100 123 4567',
      text: 'طب انتو فين بقالي ساعة مستنية!! 😤',
      time: '10:18',
      hidden: true),
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

// ============ role styling ============
class _Role {
  final String label;
  final Color color;
  final String initialFallback;
  const _Role(this.label, this.color, this.initialFallback);
}

_Role _role(String r) => switch (r) {
      'merchant' => const _Role('المتجر', Color(0xFF1AA47C), 'م'),
      'admin' => _Role('إدارة المنصّة', AppColors.info, 'إ'),
      _ => _Role('عميل', AppColors.primaryDark, 'ع'),
    };

Widget _avatar(String name, Color color, {double size = 34}) => Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: LinearGradient(
            colors: [color, Color.lerp(color, Colors.black, .18)!],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight),
        shape: BoxShape.circle,
        boxShadow: [BoxShadow(color: color.withValues(alpha: .35), blurRadius: 6, offset: const Offset(0, 2))],
      ),
      alignment: Alignment.center,
      child: Text(name.trim().isEmpty ? '؟' : name.trim().substring(0, 1),
          style: TextStyle(
              color: Colors.white, fontWeight: FontWeight.w900, fontSize: size * .42)),
    );

Widget _attachmentChip(bool onDark) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: onDark ? Colors.white.withValues(alpha: .20) : AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
            color: onDark ? Colors.white24 : AppColors.textSecondary.withValues(alpha: .18)),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 30,
          height: 30,
          decoration: BoxDecoration(
              color: (onDark ? Colors.white : AppColors.primaryDark).withValues(alpha: .15),
              borderRadius: BorderRadius.circular(8)),
          child: AppIcon(Icons.image_outlined,
              size: 16, color: onDark ? AppColors.onPrimary : AppColors.primaryDark),
        ),
        const SizedBox(width: 8),
        Text('صورة الفاتورة.jpg',
            style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: onDark ? AppColors.onPrimary : AppColors.textPrimary)),
      ]),
    );

// فقاعة رسالة بأفاتار وذيل وظلال ناعمة.
Widget _msgRow(BuildContext c, _Msg m, String viewer,
    {bool showPhone = false,
    bool adminView = false,
    bool highlighted = false,
    double maxWidth = 290}) {
  final me = m.role == viewer;
  final r = _role(m.role);

  final meBubble = BoxDecoration(
    gradient: AppColors.goldGradient,
    borderRadius: const BorderRadiusDirectional.only(
      topStart: Radius.circular(20),
      topEnd: Radius.circular(20),
      bottomEnd: Radius.circular(20),
      bottomStart: Radius.circular(6),
    ).resolve(TextDirection.rtl),
    boxShadow: [BoxShadow(color: AppColors.primary.withValues(alpha: .28), blurRadius: 10, offset: const Offset(0, 3))],
  );
  final otherBubble = BoxDecoration(
    color: AppColors.surface,
    borderRadius: const BorderRadiusDirectional.only(
      topStart: Radius.circular(20),
      topEnd: Radius.circular(20),
      bottomStart: Radius.circular(20),
      bottomEnd: Radius.circular(6),
    ).resolve(TextDirection.rtl),
    boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .05), blurRadius: 8, offset: const Offset(0, 2))],
    border: m.role == 'admin'
        ? Border.all(color: AppColors.info.withValues(alpha: .35))
        : null,
  );
  final fg = me ? AppColors.onPrimary : AppColors.textPrimary;

  final header = StringBuffer(m.name);
  if (m.staffRole != null) header.write(' · ${r.label} (${m.staffRole})');
  if (showPhone && m.phone != null) header.write('  ·  ${m.phone}');

  final bubble = Container(
    constraints: BoxConstraints(maxWidth: maxWidth),
    padding: const EdgeInsets.all(12),
    decoration: highlighted
        ? (me ? meBubble : otherBubble).copyWith(
            border: Border.all(color: AppColors.warning, width: 2.5),
            boxShadow: [BoxShadow(color: AppColors.warning.withValues(alpha: .5), blurRadius: 18)])
        : (me ? meBubble : otherBubble),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      if (!me || showPhone)
        Padding(
          padding: const EdgeInsets.only(bottom: 5),
          child: Text(header.toString(),
              style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w900,
                  color: me ? AppColors.onPrimary.withValues(alpha: .92) : r.color)),
        ),
      if (adminView && m.hidden) ...[
        Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
              color: AppColors.error.withValues(alpha: .10),
              borderRadius: BorderRadius.circular(10)),
          child: Row(children: [
            const AppIcon(Icons.visibility_off_outlined, size: 14, color: AppColors.error),
            const SizedBox(width: 6),
            const Expanded(
              child: Text('مخفية عن الطرفين — تظهر لك فقط',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: AppColors.error)),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                  color: AppColors.success, borderRadius: BorderRadius.circular(20)),
              child: const Text('إلغاء الإخفاء',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: Colors.white)),
            ),
          ]),
        ),
      ],
      if (m.replyToName != null)
        Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.fromLTRB(10, 7, 10, 7),
          decoration: BoxDecoration(
            color: me ? Colors.white.withValues(alpha: .20) : AppColors.surfaceCream,
            borderRadius: BorderRadius.circular(10),
            border: BorderDirectional(
                start: BorderSide(color: me ? Colors.white : AppColors.primary, width: 3)),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            Text(m.replyToName!,
                style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w900,
                    color: me ? AppColors.onPrimary : AppColors.primaryDark)),
            const SizedBox(height: 1),
            Text(m.replyToText ?? '',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                    fontSize: 11,
                    color: me ? AppColors.onPrimary.withValues(alpha: .85) : AppColors.textSecondary)),
          ]),
        ),
      Text(m.text, style: TextStyle(color: fg, height: 1.45, fontSize: 14.5)),
      if (m.attachment) ...[const SizedBox(height: 8), _attachmentChip(me)],
      const SizedBox(height: 5),
      Row(mainAxisSize: MainAxisSize.min, children: [
        Text(m.time,
            style: TextStyle(
                fontSize: 10,
                color: me ? AppColors.onPrimary.withValues(alpha: .8) : AppColors.textSecondary)),
        if (me) ...[
          const SizedBox(width: 4),
          Text('✓✓', style: TextStyle(fontSize: 11, color: AppColors.onPrimary.withValues(alpha: .9))),
        ],
        if (adminView) ...[
          const SizedBox(width: 10),
          const AppIcon(Icons.format_quote_rounded, size: 14, color: AppColors.textSecondary),
          const SizedBox(width: 10),
          AppIcon(m.hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined,
              size: 14, color: m.hidden ? AppColors.success : AppColors.textSecondary),
        ],
      ]),
    ]),
  );

  final opacity = (adminView && m.hidden) ? 0.65 : 1.0;
  return Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Align(
      alignment: me ? AlignmentDirectional.centerStart : AlignmentDirectional.centerEnd,
      child: Opacity(
        opacity: opacity,
        child: me
            ? bubble
            : Row(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                bubble,
                const SizedBox(width: 8),
                _avatar(m.name, r.color, size: 34),
              ]),
      ),
    ),
  );
}

// ============ gamified status stepper ============
Widget _stepper() {
  Widget dot(bool done, bool current) => Container(
        width: 28,
        height: 28,
        decoration: BoxDecoration(
          gradient: done ? AppColors.goldGradient : null,
          color: done ? null : AppColors.surfaceCream,
          shape: BoxShape.circle,
          border: current ? Border.all(color: AppColors.primaryDark, width: 2) : null,
        ),
        child: done
            ? const AppIcon(Icons.check_rounded, size: 16, color: AppColors.onPrimary)
            : null,
      );
  Widget line(bool done) => Expanded(
        child: Container(
            height: 3,
            margin: const EdgeInsets.symmetric(horizontal: 4),
            decoration: BoxDecoration(
                color: done ? AppColors.primary : AppColors.surfaceCream,
                borderRadius: BorderRadius.circular(2))),
      );
  Widget label(String t, bool active) => Expanded(
        child: Text(t,
            textAlign: TextAlign.center,
            style: TextStyle(
                fontSize: 11,
                fontWeight: active ? FontWeight.w900 : FontWeight.w600,
                color: active ? AppColors.primaryDark : AppColors.textSecondary)),
      );
  return Container(
    color: AppColors.surface,
    padding: const EdgeInsets.fromLTRB(28, 12, 28, 10),
    child: Column(children: [
      Row(children: [dot(true, false), line(true), dot(true, false), line(true), dot(true, true)]),
      const SizedBox(height: 6),
      Row(children: [label('فُتح', true), label('قيد المراجعة', true), label('تم الحل', true)]),
    ]),
  );
}

Widget _dateChip(String t) => Center(
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 6),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
        decoration: BoxDecoration(
            color: AppColors.textSecondary.withValues(alpha: .12),
            borderRadius: BorderRadius.circular(20)),
        child: Text(t,
            style: const TextStyle(
                fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.textSecondary)),
      ),
    );

Widget _resolutionCard() => Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [
          const Color(0xFF1AA47C).withValues(alpha: .16),
          AppColors.primaryLight.withValues(alpha: .5),
        ]),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFF1AA47C).withValues(alpha: .35)),
      ),
      child: Row(children: [
        const Text('🎉', style: TextStyle(fontSize: 30)),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('تم حل النزاع بنجاح',
                style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15)),
            const SizedBox(height: 2),
            Text('أُضيفت 50 نقطة تعويض لسارة · زمن الحل ٣٠ دقيقة',
                style: TextStyle(fontSize: 12, color: AppColors.textPrimary.withValues(alpha: .7))),
          ]),
        ),
      ]),
    );

// ============ Mobile chat (customer / merchant) ============
class _MobileChat extends StatelessWidget {
  final String viewer;
  final String? highlightText;
  const _MobileChat(this.viewer, {this.highlightText});

  @override
  Widget build(BuildContext context) {
    final other = viewer == 'customer' ? _role('merchant') : _role('customer');
    final otherName = viewer == 'customer' ? 'مقهى الرواق' : 'سارة';
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Column(children: [
        // gradient header
        Container(
          decoration: const BoxDecoration(gradient: AppColors.goldGradient),
          child: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 6, 16, 14),
              child: Row(children: [
                const AppIcon(Icons.chevron_left_rounded, color: AppColors.onPrimary),
                const SizedBox(width: 6),
                _avatar(otherName, Colors.white.withValues(alpha: .25), size: 40),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(otherName,
                        style: const TextStyle(
                            color: AppColors.onPrimary, fontWeight: FontWeight.w900, fontSize: 16)),
                    Text('نزاع #1842 · ${other.label}',
                        style: TextStyle(color: AppColors.onPrimary.withValues(alpha: .85), fontSize: 12)),
                  ]),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: .22),
                      borderRadius: BorderRadius.circular(20)),
                  child: const Text('⚡ رد خلال ٨ د',
                      style: TextStyle(
                          color: AppColors.onPrimary, fontSize: 11, fontWeight: FontWeight.w800)),
                ),
              ]),
            ),
          ),
        ),
        _stepper(),
        // subject chip
        Container(
          width: double.infinity,
          color: AppColors.surfaceCream,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
          child: Row(children: [
            const AppIcon(Icons.confirmation_num_outlined, size: 17, color: AppColors.primaryDark),
            const SizedBox(width: 8),
            const Expanded(
              child: Text('عن: عملية نقاط — فاتورة INV-4471',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12.5)),
            ),
          ]),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 12),
            children: [
              _dateChip('اليوم · ١٨ يونيو'),
              for (final m in _thread)
                if (!m.hidden)
                  _msgRow(context, m, viewer,
                      highlighted: highlightText != null && m.text == highlightText),
              _resolutionCard(),
            ],
          ),
        ),
        // input bar
        Material(
          color: AppColors.surface,
          elevation: 10,
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              child: Row(children: [
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    decoration: BoxDecoration(
                        color: AppColors.surfaceCream, borderRadius: BorderRadius.circular(24)),
                    child: const Text('اكتب رسالة…',
                        style: TextStyle(color: AppColors.textSecondary, fontSize: 14)),
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  width: 48,
                  height: 48,
                  decoration: const BoxDecoration(
                      gradient: AppColors.goldGradient, shape: BoxShape.circle),
                  child: const AppIcon(Icons.send_rounded, color: AppColors.onPrimary),
                ),
              ]),
            ),
          ),
        ),
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
          gradient: active ? AppColors.goldGradient : null,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(children: [
          Text(icon, style: const TextStyle(fontSize: 16)),
          const SizedBox(width: 12),
          Text(label,
              style: TextStyle(
                  color: active ? Colors.white : const Color(0xFFCBD5E1),
                  fontWeight: active ? FontWeight.w900 : FontWeight.w600,
                  fontSize: 14)),
        ]),
      );

  Widget _partyBadge(String role, String name) {
    final r = _role(role);
    return Container(
      padding: const EdgeInsets.fromLTRB(6, 4, 12, 4),
      decoration: BoxDecoration(
          color: r.color.withValues(alpha: .10),
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: r.color.withValues(alpha: .3))),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        _avatar(name, r.color, size: 24),
        const SizedBox(width: 8),
        Text('$name · ${r.label}',
            style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800, color: r.color)),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      backgroundColor: const Color(0xFFF3F4F6),
      body: Row(children: [
        Container(
          width: 240,
          color: const Color(0xFF111827),
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(6, 6, 6, 16),
              child: Text('Hatchy',
                  style: TextStyle(color: Color(0xFFF59E0B), fontSize: 24, fontWeight: FontWeight.w900)),
            ),
            _navItem('لوحة التحكم', '▣'),
            _navItem('التجار (CRM)', '🏪'),
            _navItem('المستخدمون', '👥'),
            _navItem('البلاغات والنزاعات', '⚑', active: true),
            _navItem('التقييمات', '⭐'),
          ]),
        ),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.all(26),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                Text('نزاع #1842',
                    style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
                const SizedBox(width: 14),
                _partyBadge('customer', 'سارة'),
                const SizedBox(width: 8),
                _partyBadge('merchant', 'مقهى الرواق'),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                  decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: const Color(0xFFE5E7EB))),
                  child: const Text('الحالة: محلول ▾', style: TextStyle(fontWeight: FontWeight.w800)),
                ),
                const SizedBox(width: 10),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                      color: const Color(0xFFDC2626), borderRadius: BorderRadius.circular(10)),
                  child: const Text('إغلاق النزاع',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
                ),
              ]),
              const SizedBox(height: 14),
              // card with stepper + subject + conversation
              Expanded(
                child: Container(
                  decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFE5E7EB))),
                  clipBehavior: Clip.antiAlias,
                  child: Column(children: [
                    _stepper(),
                    Container(height: 1, color: const Color(0xFFF0F0F0)),
                    Expanded(
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(20, 14, 20, 14),
                        children: [
                          _dateChip('اليوم · ١٨ يونيو'),
                          for (final m in _thread)
                            _msgRow(context, m, 'admin',
                                showPhone: true, adminView: true, maxWidth: 560),
                        ],
                      ),
                    ),
                  ]),
                ),
              ),
              const SizedBox(height: 14),
              // admin reply box with reply-to chip
              Container(
                decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE5E7EB))),
                padding: const EdgeInsets.all(14),
                child: Column(children: [
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
                      const AppIcon(Icons.format_quote_rounded, size: 16, color: AppColors.primaryDark),
                      const SizedBox(width: 8),
                      const Expanded(
                        child: Text('ترد على: منى · المتجر (مديرة الفرع) · 0122 555 6677',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.primaryDark)),
                      ),
                      const AppIcon(Icons.cancel_outlined, size: 18, color: AppColors.textSecondary),
                    ]),
                  ),
                  Row(children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
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
                      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
                      decoration: BoxDecoration(
                          gradient: AppColors.goldGradient, borderRadius: BorderRadius.circular(10)),
                      child: const Text('إرسال كردّ إداري',
                          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
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
      (t) => _shot(t, 'dispute_3_admin', const _AdminChat(), w: 1180, h: 860));
  testWidgets(
      'd4 jump to original',
      (t) => _shot(t, 'dispute_4_jump',
          const _MobileChat('customer', highlightText: 'أهلًا سارة 👋 ممكن رقم الفاتورة؟ بنراجعها حالًا.')));
}
