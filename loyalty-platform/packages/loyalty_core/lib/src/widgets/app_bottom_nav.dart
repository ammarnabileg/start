import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'app_icon.dart';

/// عنصر في شريط التنقّل.
class AppBottomNavItem {
  final IconData icon;
  final IconData? activeIcon;
  final String label;

  /// عنصر بارز (زر دائري متدرّج في نص الشريط) — مثل الـ QR/المسح.
  final bool prominent;

  const AppBottomNavItem({
    required this.icon,
    required this.label,
    this.activeIcon,
    this.prominent = false,
  });
}

/// شريط تنقّل سفلي عائم بهوية Hatchy:
/// حواف دائرية + ظل ناعم + مؤشّر pill متحرّك + زر بارز **في النص** يطفو فوق الشريط.
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
    final prominentIndex = items.indexWhere((i) => i.prominent);
    if (prominentIndex < 0) return _simpleBar();

    // العناصر العادية (بدون البارز) مع فهارسها الأصلية.
    final others = <MapEntry<int, AppBottomNavItem>>[
      for (var i = 0; i < items.length; i++)
        if (!items[i].prominent) MapEntry(i, items[i]),
    ];
    final half = (others.length / 2).ceil();
    final left = others.sublist(0, half);
    final right = others.sublist(half);
    // نوازن الجانبين بإضافة خانات فارغة للجانب الأقصر ليبقى الزر في النص تمامًا.
    final pad = left.length - right.length;

    return SafeArea(
      top: false,
      child: SizedBox(
        height: 78,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            // الشريط
            Positioned(
              left: 16,
              right: 16,
              bottom: 8,
              child: Container(
                height: 64,
                padding: const EdgeInsets.symmetric(horizontal: 6),
                decoration: _barDecoration(),
                child: Row(
                  children: [
                    for (final e in left)
                      Expanded(child: _slot(e.key, e.value)),
                    const SizedBox(width: 72), // فراغ للزر البارز
                    for (final e in right)
                      Expanded(child: _slot(e.key, e.value)),
                    for (var i = 0; i < pad; i++)
                      const Expanded(child: SizedBox.shrink()),
                  ],
                ),
              ),
            ),
            // الزر البارز في النص (يطفو فوق الشريط)
            Positioned(
              left: 0,
              right: 0,
              bottom: 24,
              child: Center(
                child: _ProminentButton(
                  item: items[prominentIndex],
                  selected: currentIndex == prominentIndex,
                  onTap: () {
                    HapticFeedback.mediumImpact();
                    onTap(prominentIndex);
                  },
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _simpleBar() {
    return SafeArea(
      top: false,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        decoration: _barDecoration(),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            for (var i = 0; i < items.length; i++) _slot(i, items[i]),
          ],
        ),
      ),
    );
  }

  BoxDecoration _barDecoration() => BoxDecoration(
        color: dark ? AppColors.darkSurface : AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadii.pill),
        boxShadow: const [
          BoxShadow(color: AppColors.shadow, blurRadius: 24, offset: Offset(0, 8)),
        ],
      );

  Widget _slot(int index, AppBottomNavItem item) {
    final selected = index == currentIndex;
    final activeColor = dark ? AppColors.gold : AppColors.primaryDark;
    final color = selected ? activeColor : AppColors.textSecondary;
    // تصميم عمودي (أيقونة فوق نص) — يتقلّص النص بأمان داخل الخانة فلا يطفح أبدًا.
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () {
        HapticFeedback.selectionClick();
        onTap(index);
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 2),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            AppIcon(selected ? (item.activeIcon ?? item.icon) : item.icon,
                color: color, size: 24),
            const SizedBox(height: 3),
            Text(item.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: TextStyle(
                    color: color,
                    fontSize: 10.5,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}

class _ProminentButton extends StatelessWidget {
  final AppBottomNavItem item;
  final bool selected;
  final VoidCallback onTap;
  const _ProminentButton(
      {required this.item, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: AppDurations.normal,
        curve: Curves.easeOutBack,
        height: 60,
        width: 60,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          gradient: AppColors.buttonGradient,
          shape: BoxShape.circle,
          border: Border.all(color: AppColors.surface, width: 4),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: selected ? .6 : .4),
              blurRadius: selected ? 20 : 14,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        child: AppIcon(item.activeIcon ?? item.icon,
            color: Colors.white, size: 24),
      ),
    );
  }
}
