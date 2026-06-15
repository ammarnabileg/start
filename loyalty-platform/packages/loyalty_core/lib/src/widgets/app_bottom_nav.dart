import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// عنصر في شريط التنقّل.
class AppBottomNavItem {
  final IconData icon;
  final IconData? activeIcon;
  final String label;

  /// عنصر بارز (زر دائري متدرّج) — مثل زر المسح عند التاجر.
  final bool prominent;

  const AppBottomNavItem({
    required this.icon,
    required this.label,
    this.activeIcon,
    this.prominent = false,
  });
}

/// شريط تنقّل سفلي عائم بهوية Hatchy:
/// حواف دائرية + ظل ناعم + مؤشّر pill متحرّك للعنصر النشط + اهتزاز خفيف.
/// مشترك بين تطبيقَي العميل والتاجر لتجربة موحّدة.
class AppBottomNav extends StatelessWidget {
  final List<AppBottomNavItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;
  final bool dark;

  const AppBottomNav({
    super.key,
    required this.items,
    required this.currentIndex,
    required this.onTap,
    this.dark = false,
  });

  @override
  Widget build(BuildContext context) {
    final bg = dark ? AppColors.darkSurface : AppColors.surface;
    return SafeArea(
      top: false,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        decoration: BoxDecoration(
          color: bg,
          borderRadius: BorderRadius.circular(AppRadii.pill),
          boxShadow: const [
            BoxShadow(color: AppColors.shadow, blurRadius: 24, offset: Offset(0, 8)),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            for (var i = 0; i < items.length; i++)
              _Slot(
                item: items[i],
                selected: i == currentIndex,
                dark: dark,
                onTap: () {
                  HapticFeedback.selectionClick();
                  onTap(i);
                },
              ),
          ],
        ),
      ),
    );
  }
}

class _Slot extends StatelessWidget {
  final AppBottomNavItem item;
  final bool selected;
  final bool dark;
  final VoidCallback onTap;

  const _Slot({
    required this.item,
    required this.selected,
    required this.dark,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    // زر بارز (دائري متدرّج) — مثل المسح.
    if (item.prominent) {
      return GestureDetector(
        onTap: onTap,
        child: Container(
          height: 54,
          width: 54,
          decoration: BoxDecoration(
            gradient: AppColors.buttonGradient,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: .45),
                blurRadius: 14,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Icon(item.activeIcon ?? item.icon,
              color: AppColors.onPrimary, size: 28),
        ),
      );
    }

    final activeColor = dark ? AppColors.gold : AppColors.primaryDark;
    final idle = AppColors.textSecondary;

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: AnimatedContainer(
        duration: AppDurations.normal,
        curve: Curves.easeOutCubic,
        padding: EdgeInsets.symmetric(
            horizontal: selected ? 16 : 14, vertical: 10),
        decoration: BoxDecoration(
          color: selected
              ? (dark
                  ? AppColors.gold.withValues(alpha: .18)
                  : AppColors.primaryLight)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(selected ? (item.activeIcon ?? item.icon) : item.icon,
                color: selected ? activeColor : idle, size: 24),
            // الاسم يظهر فقط للعنصر النشط (شكل أنظف).
            AnimatedSize(
              duration: AppDurations.normal,
              curve: Curves.easeOutCubic,
              child: selected
                  ? Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: Text(item.label,
                          style: TextStyle(
                              color: activeColor,
                              fontWeight: FontWeight.w700,
                              fontSize: 13)),
                    )
                  : const SizedBox.shrink(),
            ),
          ],
        ),
      ),
    );
  }
}
