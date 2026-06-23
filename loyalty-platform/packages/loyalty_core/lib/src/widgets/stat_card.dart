import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'app_icon.dart';
import 'app_icon_badge.dart';

/// كارت إحصائية مدمج (رقم + عنوان + أيقونة) — للوحات التحكم وبطاقات الحالة.
class StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color? accent;
  final bool highlight;

  const StatCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.accent,
    this.highlight = false,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: highlight ? AppColors.goldGradient : null,
        color: highlight ? null : AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadii.lg),
        boxShadow: const [
          BoxShadow(color: AppColors.shadowSoft, blurRadius: 16, offset: Offset(0, 6)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (highlight)
            // على بطاقة ذهبية: شارة بيضاء بأيقونة ذهبية (تباين عكسي).
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .85),
                borderRadius: BorderRadius.circular(AppRadii.sm),
              ),
              child: AppIcon(icon, size: 20, color: AppColors.primaryDark),
            )
          else
            // الوضع العادي: شارة ذهبية متدرّجة + أيقونة بيضاء (استايل موحّد).
            AppIconBadge(icon, size: 40, iconSize: 20, color: accent),
          const SizedBox(height: 12),
          Text(value,
              style: Theme.of(context)
                  .textTheme
                  .headlineMedium
                  ?.copyWith(color: highlight ? AppColors.onPrimary : null)),
          const SizedBox(height: 2),
          Text(label,
              style: Theme.of(context).textTheme.bodySmall,
              maxLines: 1,
              overflow: TextOverflow.ellipsis),
        ],
      ),
    );
  }
}
