import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import 'app_icon.dart';

/// شارة أيقونة موحّدة بهوية Hatchy: خلفية بلون فاتح (tint) + أيقونة بنفس اللون،
/// بحواف دائرية ناعمة وبدون أي بوردر أو ظل. هوية واحدة لكل أيقونات التطبيق.
class AppIconBadge extends StatelessWidget {
  final IconData icon;

  /// حجم المربّع.
  final double size;
  final double? iconSize;

  /// لون الهوية (الخلفية الفاتحة + الأيقونة). افتراضيًا الأساسي الذهبي.
  final Color? color;

  /// شكل دائري بدل المربّع الدائري الحواف.
  final bool circle;

  const AppIconBadge(
    this.icon, {
    super.key,
    this.size = 44,
    this.iconSize,
    this.color,
    this.circle = false,
  });

  @override
  Widget build(BuildContext context) {
    final c = color ?? AppColors.primaryDark;
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: c.withValues(alpha: .14),
        shape: circle ? BoxShape.circle : BoxShape.rectangle,
        borderRadius: circle ? null : BorderRadius.circular(size * 0.30),
      ),
      child: AppIcon(icon, size: iconSize ?? size * 0.5, color: c),
    );
  }
}
