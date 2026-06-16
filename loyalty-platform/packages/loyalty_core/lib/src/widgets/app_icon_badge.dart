import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import 'app_icon.dart';

/// شارة أيقونة موحّدة بهوية Hatchy: خلفية ذهبية متدرّجة + أيقونة بيضاء —
/// نفس استايل زر المسح البارز. تُستخدم لكل الأيقونات اللي خلفيتها ذهبية.
class AppIconBadge extends StatelessWidget {
  final IconData icon;

  /// حجم المربّع.
  final double size;
  final double? iconSize;

  /// لون أساس بديل (لو عايز شارة بلون دلالي بدل الذهبي) — تبقى بأيقونة بيضاء.
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
    final solid = color;
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        gradient: solid == null ? AppColors.buttonGradient : null,
        color: solid,
        shape: circle ? BoxShape.circle : BoxShape.rectangle,
        borderRadius: circle ? null : BorderRadius.circular(size * 0.32),
        boxShadow: [
          BoxShadow(
            color: (solid ?? AppColors.primary).withValues(alpha: .32),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: AppIcon(icon, size: iconSize ?? size * 0.5, color: Colors.white),
    );
  }
}
