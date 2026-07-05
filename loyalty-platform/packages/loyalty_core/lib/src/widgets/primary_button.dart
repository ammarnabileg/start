import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'app_icon.dart';

enum AppButtonVariant { primary, secondary, ghost }

/// زرّ Hatchy: تدرّج مبهج + ضغطة بحركة scale + اهتزاز خفيف (haptic).
/// التوقيع متوافق مع النسخة القديمة (label/onPressed/icon/loading/expanded).
class PrimaryButton extends StatefulWidget {
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final bool expanded;
  final AppButtonVariant variant;

  const PrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
    this.expanded = true,
    this.variant = AppButtonVariant.primary,
  });

  @override
  State<PrimaryButton> createState() => _PrimaryButtonState();
}

class _PrimaryButtonState extends State<PrimaryButton> {
  bool _pressed = false;

  bool get _enabled => widget.onPressed != null && !widget.loading;

  @override
  Widget build(BuildContext context) {
    final isPrimary = widget.variant == AppButtonVariant.primary;
    final isGhost = widget.variant == AppButtonVariant.ghost;
    final fg = isPrimary ? AppColors.onPrimary : AppColors.textPrimary;

    final content = widget.loading
        ? SizedBox(
            height: 22,
            width: 22,
            child: CircularProgressIndicator(strokeWidth: 2.4, color: fg),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (widget.icon != null) ...[
                AppIcon(widget.icon!, size: 20, color: fg),
                const SizedBox(width: 8),
              ],
              Text(widget.label,
                  style: TextStyle(
                      fontSize: 16, fontWeight: FontWeight.w700, color: fg)),
            ],
          );

    final decoration = BoxDecoration(
      gradient: isPrimary && _enabled ? AppColors.buttonGradient : null,
      color: isPrimary
          ? (_enabled ? null : AppColors.primary.withValues(alpha: .4))
          : (isGhost ? Colors.transparent : AppColors.surfaceCream),
      borderRadius: BorderRadius.circular(AppRadii.pill),
      border: isGhost
          ? Border.all(color: AppColors.divider, width: 1.5)
          : null,
      boxShadow: isPrimary && _enabled && !_pressed
          ? [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: .35),
                blurRadius: 16,
                offset: const Offset(0, 6),
              )
            ]
          : null,
    );

    Widget button = AnimatedScale(
      scale: _pressed ? 0.97 : 1.0,
      duration: AppDurations.fast,
      child: AnimatedContainer(
        duration: AppDurations.fast,
        height: 56,
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 24),
        decoration: decoration,
        child: content,
      ),
    );

    button = GestureDetector(
      onTapDown: _enabled ? (_) => setState(() => _pressed = true) : null,
      onTapUp: _enabled ? (_) => setState(() => _pressed = false) : null,
      onTapCancel: _enabled ? () => setState(() => _pressed = false) : null,
      onTap: _enabled
          ? () {
              HapticFeedback.lightImpact();
              widget.onPressed!();
            }
          : null,
      child: button,
    );

    return widget.expanded
        ? SizedBox(width: double.infinity, child: button)
        : button;
  }
}
