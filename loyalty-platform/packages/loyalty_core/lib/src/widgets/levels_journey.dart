import 'package:flutter/material.dart';
import '../models/loyalty_level.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'app_icon.dart';

/// مسار المستويات بشكل "طريق متعرّج" (Journey) بهوية Hatchy.
/// كل عقدة = مستوى؛ المكتمل مضيء، الحالي بارز بعلامة "أنت هنا"، المقفول باهت.
/// يعتمد على نقاط العميل الكليّة (lifetime) في الستور/الفرع.
class LevelsJourney extends StatelessWidget {
  final List<LoyaltyLevel> levels;
  final int lifetimePoints;

  /// عنوان اختياري يظهر في بطاقة الرأس (مثلاً اسم المتجر/الفرع).
  final String? title;

  const LevelsJourney({
    super.key,
    required this.levels,
    required this.lifetimePoints,
    this.title,
  });

  @override
  Widget build(BuildContext context) {
    final sorted = [...levels]
      ..sort((a, b) =>
          a.thresholdLifetimePoints.compareTo(b.thresholdLifetimePoints));

    // المستوى الحالي = أعلى عتبة ≤ النقاط الكليّة.
    var currentIndex = -1;
    for (var i = 0; i < sorted.length; i++) {
      if (sorted[i].thresholdLifetimePoints <= lifetimePoints) currentIndex = i;
    }
    final next = currentIndex + 1 < sorted.length ? sorted[currentIndex + 1] : null;
    final toNext =
        next == null ? 0 : next.thresholdLifetimePoints - lifetimePoints;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _Header(
          title: title,
          lifetimePoints: lifetimePoints,
          currentName: currentIndex >= 0 ? sorted[currentIndex].name : 'مبتدئ',
          toNext: toNext,
          nextName: next?.name,
        ),
        const SizedBox(height: AppSpacing.sm),
        // الطريق المتعرّج (يبدأ من الأعلى = المستوى الأعلى، ونزولاً للبداية)
        LayoutBuilder(
          builder: (context, c) {
            final width = c.maxWidth;
            const spacing = 132.0;
            const topPad = 56.0;
            // نعرض من الأعلى (الأعلى مستوى) للأسفل (البداية) لإحساس "القمة".
            final ordered = sorted.reversed.toList();
            final n = ordered.length;
            final centers = <Offset>[
              for (var i = 0; i < n; i++)
                Offset(i.isEven ? width * 0.30 : width * 0.70,
                    topPad + spacing * i),
            ];
            final height = topPad + spacing * (n - 1) + 90;

            return SizedBox(
              height: height,
              width: width,
              child: Stack(
                children: [
                  // الريبون المتعرّج
                  Positioned.fill(
                    child: CustomPaint(painter: _PathPainter(centers)),
                  ),
                  // العُقد (الدائرة + لافتتها) — كل عنصر مثبّت داخل الحدود.
                  for (var i = 0; i < n; i++)
                    ..._nodeWidgets(context, ordered[i], centers[i], width,
                        sorted.length - 1 - i, currentIndex),
                ],
              ),
            );
          },
        ),
      ],
    );
  }

  List<Widget> _nodeWidgets(BuildContext context, LoyaltyLevel level,
      Offset center, double width, int realIndex, int currentIndex) {
    final reached = level.thresholdLifetimePoints <= lifetimePoints;
    final isCurrent = realIndex == currentIndex;
    final size = isCurrent ? 78.0 : 60.0;
    // العقدة على النصف الأيسر → اللافتة تتّجه يمينًا؛ وعلى النصف الأيمن → يسارًا،
    // أي دائمًا ناحية الداخل فلا تخرج عن حافة الشاشة.
    final onLeft = center.dx < width / 2;
    const gap = 8.0;

    final circle = Positioned(
      left: center.dx - size / 2,
      top: center.dy - size / 2,
      child: _NodeCircle(
          level: level, size: size, reached: reached, isCurrent: isCurrent),
    );

    final pill = isCurrent
        ? _Pill(text: 'أنت هنا', strong: true)
        : reached
            ? _Pill(text: level.name)
            : _Pill(
                text:
                    'باقٍ ${level.thresholdLifetimePoints - lifetimePoints}',
                muted: true);

    final pillPositioned = Positioned(
      top: center.dy - 17,
      left: onLeft ? center.dx + size / 2 + gap : null,
      right: onLeft ? null : width - (center.dx - size / 2) + gap,
      child: pill,
    );

    return [circle, pillPositioned];
  }
}

class _NodeCircle extends StatelessWidget {
  final LoyaltyLevel level;
  final double size;
  final bool reached;
  final bool isCurrent;
  const _NodeCircle(
      {required this.level,
      required this.size,
      required this.reached,
      required this.isCurrent});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: isCurrent
            ? AppColors.buttonGradient
            : (reached ? AppColors.goldGradient : null),
        color: reached || isCurrent ? null : AppColors.surface,
        border: reached || isCurrent
            ? null
            : Border.all(color: AppColors.divider, width: 2),
        boxShadow: [
          BoxShadow(
            color: isCurrent
                ? AppColors.primary.withValues(alpha: .45)
                : AppColors.shadow,
            blurRadius: isCurrent ? 18 : 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: AppIcon(
        reached || isCurrent
            ? Icons.workspace_premium_rounded
            : Icons.lock_outline_rounded,
        color: reached || isCurrent ? AppColors.onPrimary : AppColors.textSecondary,
        size: size * 0.42,
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  final String text;
  final bool strong;
  final bool muted;
  const _Pill({required this.text, this.strong = false, this.muted = false});

  @override
  Widget build(BuildContext context) {
    final bg = strong
        ? AppColors.onPrimary
        : (muted ? AppColors.surfaceCream : AppColors.surface);
    final fg = strong ? Colors.white : AppColors.textPrimary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadii.pill),
        boxShadow: const [
          BoxShadow(color: AppColors.shadow, blurRadius: 10, offset: Offset(0, 4)),
        ],
      ),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 150),
        child: Text(text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style:
                TextStyle(color: fg, fontWeight: FontWeight.w800, fontSize: 13)),
      ),
    );
  }
}

/// بطاقة رأس المسار — المستوى الحالي + النقاط الكليّة + التقدّم للتالي.
class _Header extends StatelessWidget {
  final String? title;
  final int lifetimePoints;
  final String currentName;
  final int toNext;
  final String? nextName;
  const _Header({
    required this.title,
    required this.lifetimePoints,
    required this.currentName,
    required this.toNext,
    required this.nextName,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: AppColors.heroGradient,
        borderRadius: BorderRadius.circular(AppRadii.xl),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: .35),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              AppIcon(Icons.emoji_events_rounded,
                  color: AppColors.onPrimary, size: 22),
              const SizedBox(width: 8),
              Expanded(
                child: Text(title ?? 'رحلة مستوياتك',
                    style: Theme.of(context)
                        .textTheme
                        .titleLarge
                        ?.copyWith(color: AppColors.onPrimary)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('$lifetimePoints',
                  style: Theme.of(context)
                      .textTheme
                      .displayLarge
                      ?.copyWith(color: AppColors.onPrimary)),
              const SizedBox(width: 6),
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Text('نقطة كليّة · $currentName',
                    style: TextStyle(
                        color: AppColors.onPrimary.withValues(alpha: .9),
                        fontWeight: FontWeight.w600)),
              ),
            ],
          ),
          if (nextName != null) ...[
            const SizedBox(height: 4),
            Text('باقٍ $toNext نقطة للوصول إلى $nextName',
                style: TextStyle(
                    color: AppColors.onPrimary.withValues(alpha: .9))),
          ],
        ],
      ),
    );
  }
}

/// يرسم الطريق المتعرّج (ريبون) بين العُقد بهوية Hatchy.
class _PathPainter extends CustomPainter {
  final List<Offset> centers;
  _PathPainter(this.centers);

  @override
  void paint(Canvas canvas, Size size) {
    if (centers.length < 2) return;
    final path = Path()..moveTo(centers.first.dx, centers.first.dy);
    for (var i = 1; i < centers.length; i++) {
      final p0 = centers[i - 1];
      final p1 = centers[i];
      final midY = (p0.dy + p1.dy) / 2;
      path.cubicTo(p0.dx, midY, p1.dx, midY, p1.dx, p1.dy);
    }

    // ظل خفيف
    canvas.drawPath(
      path,
      Paint()
        ..color = AppColors.shadow
        ..style = PaintingStyle.stroke
        ..strokeWidth = 30
        ..strokeCap = StrokeCap.round
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 6),
    );
    // الريبون الأساسي
    canvas.drawPath(
      path,
      Paint()
        ..color = AppColors.primaryLight
        ..style = PaintingStyle.stroke
        ..strokeWidth = 26
        ..strokeCap = StrokeCap.round,
    );
    // خط منقّط داخلي (إحساس "المسار")
    final dashed = Paint()
      ..color = AppColors.onPrimary.withValues(alpha: .35)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round;
    final metrics = path.computeMetrics();
    for (final m in metrics) {
      var d = 0.0;
      while (d < m.length) {
        final seg = m.extractPath(d, d + 10);
        canvas.drawPath(seg, dashed);
        d += 22;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _PathPainter old) => old.centers != centers;
}
