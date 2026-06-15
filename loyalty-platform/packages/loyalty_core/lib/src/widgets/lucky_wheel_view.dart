import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../models/lucky_wheel.dart';
import '../theme/app_colors.dart';

/// تحكّم في العجلة من الخارج: استدعِ spinTo(index) بعد ما السيرفر يحدّد النصيب.
class LuckyWheelController {
  _LuckyWheelViewState? _state;
  bool get isReady => _state != null;
  Future<void> spinTo(int index) async =>
      _state == null ? null : _state!._spinTo(index);
}

/// عجلة حظ مرسومة بهوية Hatchy + أنيميشن لفّ سلس.
class LuckyWheelView extends StatefulWidget {
  final List<WheelSegment> segments;
  final LuckyWheelController controller;
  final double size;
  const LuckyWheelView({
    super.key,
    required this.segments,
    required this.controller,
    this.size = 300,
  });

  @override
  State<LuckyWheelView> createState() => _LuckyWheelViewState();
}

class _LuckyWheelViewState extends State<LuckyWheelView>
    with SingleTickerProviderStateMixin {
  late final AnimationController _ac =
      AnimationController(vsync: this, duration: const Duration(seconds: 4));
  double _from = 0, _to = 0;

  // ألوان افتراضية متناوبة بهوية Hatchy لو المقطع مالوش لون.
  static const _palette = [
    AppColors.primary,
    AppColors.primaryDark,
    AppColors.goldTier,
    Color(0xFFFFD66B),
    Color(0xFFF5A800),
    Color(0xFFFFE08A),
  ];

  @override
  void initState() {
    super.initState();
    widget.controller._state = this;
  }

  @override
  void dispose() {
    _ac.dispose();
    widget.controller._state = null;
    super.dispose();
  }

  Future<void> _spinTo(int index) async {
    final n = widget.segments.length;
    if (n == 0) return;
    const pointer = -math.pi / 2; // المؤشّر في الأعلى
    final sector = 2 * math.pi / n;
    final center = index * sector + sector / 2;
    // 5 لفّات كاملة + المحاذاة، دايمًا للأمام.
    final base = (_to / (2 * math.pi)).ceil() * (2 * math.pi);
    final target = base + 5 * 2 * math.pi + (pointer - center);
    _from = _to;
    _to = target;
    _ac
      ..reset()
      ..duration = const Duration(milliseconds: 4200);
    await _ac.animateTo(1, curve: Curves.easeOutCubic);
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: widget.size,
      height: widget.size + 24,
      child: Stack(
        alignment: Alignment.topCenter,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 24),
            child: AnimatedBuilder(
              animation: _ac,
              builder: (_, __) {
                final angle = _from + (_to - _from) * _ac.value;
                return Transform.rotate(
                  angle: angle,
                  child: CustomPaint(
                    size: Size.square(widget.size),
                    painter: _WheelPainter(widget.segments, _palette),
                  ),
                );
              },
            ),
          ),
          // المؤشّر
          Positioned(
            top: 0,
            child: CustomPaint(
                size: const Size(34, 34), painter: _PointerPainter()),
          ),
          // محور المنتصف
          Positioned(
            top: 24 + widget.size / 2 - 26,
            child: Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                gradient: AppColors.buttonGradient,
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.surface, width: 4),
                boxShadow: const [
                  BoxShadow(
                      color: AppColors.shadow, blurRadius: 10, offset: Offset(0, 4)),
                ],
              ),
              child: const Icon(Icons.casino_rounded,
                  color: AppColors.onPrimary, size: 24),
            ),
          ),
        ],
      ),
    );
  }
}

class _WheelPainter extends CustomPainter {
  final List<WheelSegment> segments;
  final List<Color> palette;
  _WheelPainter(this.segments, this.palette);

  @override
  void paint(Canvas canvas, Size size) {
    final n = segments.length;
    if (n == 0) return;
    final center = size.center(Offset.zero);
    final radius = size.width / 2;
    final sector = 2 * math.pi / n;
    final rect = Rect.fromCircle(center: center, radius: radius);

    for (var i = 0; i < n; i++) {
      final start = i * sector;
      final color = segments[i].color ?? palette[i % palette.length];
      canvas.drawArc(
        rect,
        start,
        sector,
        true,
        Paint()..color = color..style = PaintingStyle.fill,
      );
      // فاصل أبيض
      canvas.drawArc(rect, start, sector, true,
          Paint()
            ..color = Colors.white.withValues(alpha: .9)
            ..style = PaintingStyle.stroke
            ..strokeWidth = 2);

      // النص
      final mid = start + sector / 2;
      final tp = TextPainter(
        text: TextSpan(
          text: segments[i].label,
          style: const TextStyle(
              color: AppColors.onPrimary,
              fontWeight: FontWeight.w800,
              fontSize: 13),
        ),
        textDirection: TextDirection.rtl,
        textAlign: TextAlign.center,
        maxLines: 2,
        ellipsis: '…',
      )..layout(maxWidth: radius * 0.62);

      canvas.save();
      canvas.translate(center.dx, center.dy);
      canvas.rotate(mid);
      // نضع النص على بُعد من المركز ونعكسه ليُقرأ للخارج
      canvas.translate(radius * 0.52, 0);
      canvas.rotate(math.pi);
      tp.paint(canvas, Offset(-tp.width / 2, -tp.height / 2));
      canvas.restore();
    }

    // إطار خارجي
    canvas.drawCircle(
        center,
        radius,
        Paint()
          ..color = AppColors.surface
          ..style = PaintingStyle.stroke
          ..strokeWidth = 8);
  }

  @override
  bool shouldRepaint(covariant _WheelPainter old) =>
      old.segments != segments;
}

class _PointerPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final p = Path()
      ..moveTo(size.width / 2, size.height)
      ..lineTo(0, 0)
      ..lineTo(size.width, 0)
      ..close();
    canvas.drawShadow(p, Colors.black26, 4, false);
    canvas.drawPath(p, Paint()..color = AppColors.error);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
