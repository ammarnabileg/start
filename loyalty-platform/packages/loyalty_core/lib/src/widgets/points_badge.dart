import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// شارة نقاط صغيرة بهوية Hatchy.
class PointsBadge extends StatelessWidget {
  final int points;
  final String suffix;

  const PointsBadge({super.key, required this.points, this.suffix = 'نقطة'});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.primaryLight,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.star_rounded, size: 16, color: AppColors.primaryDark),
          const SizedBox(width: 4),
          Text('$points $suffix',
              style: const TextStyle(
                  fontWeight: FontWeight.w700, color: AppColors.onPrimary)),
        ],
      ),
    );
  }
}
