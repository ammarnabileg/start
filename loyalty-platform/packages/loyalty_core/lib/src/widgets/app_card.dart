import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// كارت Hatchy: حواف دائرية + ظل ناعم + ضغطة بحركة عند وجود onTap.
/// التوقيع متوافق مع القديم (child/padding/onTap/color) + إضافات اختيارية.
class AppCard extends StatefulWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final VoidCallback? onTap;
  final Color? color;
  final Gradient? gradient;
  final BoxBorder? border;
  final double radius;

  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.margin,
    this.onTap,
    this.color,
    this.gradient,
    this.border,
    this.radius = AppRadii.xl,
  });

  @override
  State<AppCard> createState() => _AppCardState();
}

class _AppCardState extends State<AppCard> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final tappable = widget.onTap != null;
    final bg = widget.gradient == null
        ? (widget.color ??
            Theme.of(context).cardTheme.color ??
            AppColors.surface)
        : null;

    final card = AnimatedScale(
      scale: _pressed ? 0.985 : 1.0,
      duration: AppDurations.fast,
      child: AnimatedContainer(
        duration: AppDurations.fast,
        padding: widget.padding,
        decoration: BoxDecoration(
          color: bg,
          gradient: widget.gradient,
          border: widget.border,
          borderRadius: BorderRadius.circular(widget.radius),
          boxShadow: [
            BoxShadow(
              color: _pressed ? AppColors.shadowSoft : AppColors.shadow,
              blurRadius: _pressed ? 10 : 20,
              offset: Offset(0, _pressed ? 4 : 8),
            ),
          ],
        ),
        child: widget.child,
      ),
    );

    final wrapped = widget.margin == null
        ? card
        : Padding(padding: widget.margin!, child: card);

    if (!tappable) return wrapped;

    return GestureDetector(
      onTapDown: (_) => setState(() => _pressed = true),
      onTapUp: (_) => setState(() => _pressed = false),
      onTapCancel: () => setState(() => _pressed = false),
      onTap: widget.onTap,
      child: wrapped,
    );
  }
}
