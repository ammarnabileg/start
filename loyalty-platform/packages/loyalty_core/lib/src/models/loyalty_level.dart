import 'package:flutter/material.dart';
import '../theme/app_colors.dart';

/// مستوى ولاء. متطابق مع جدول public.loyalty_levels.
class LoyaltyLevel {
  final String id;
  final String merchantId;

  /// فرع المستوى — null يعني على مستوى الستور كله (الوضع المشترك).
  final String? branchId;
  final String name;
  final int thresholdLifetimePoints;
  final String? rewardDescription;
  final int sortOrder;

  const LoyaltyLevel({
    required this.id,
    required this.merchantId,
    required this.name,
    required this.thresholdLifetimePoints,
    this.branchId,
    this.rewardDescription,
    this.sortOrder = 0,
  });

  /// لون تقريبي للمستوى حسب الترتيب (للعرض فقط).
  Color get color => switch (sortOrder) {
        0 => AppColors.bronze,
        1 => AppColors.silver,
        2 => AppColors.goldTier,
        _ => AppColors.platinum,
      };

  factory LoyaltyLevel.fromJson(Map<String, dynamic> j) => LoyaltyLevel(
        id: j['id'] as String,
        merchantId: j['merchant_id'] as String,
        branchId: j['branch_id'] as String?,
        name: j['name'] as String,
        thresholdLifetimePoints: j['threshold_lifetime_points'] as int,
        rewardDescription: j['reward_description'] as String?,
        sortOrder: j['sort_order'] as int? ?? 0,
      );
}
