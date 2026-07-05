import '../models/loyalty_level.dart';

/// نتيجة عملية على المحفظة (available/lifetime/level).
class WalletState {
  final int availablePoints;
  final int lifetimePoints;
  final String? currentLevelId;

  const WalletState({
    required this.availablePoints,
    required this.lifetimePoints,
    this.currentLevelId,
  });

  WalletState copyWith({
    int? availablePoints,
    int? lifetimePoints,
    String? currentLevelId,
  }) =>
      WalletState(
        availablePoints: availablePoints ?? this.availablePoints,
        lifetimePoints: lifetimePoints ?? this.lifetimePoints,
        currentLevelId: currentLevelId ?? this.currentLevelId,
      );
}

/// قواعد الولاء — نسخة نقية (pure) تعكس منطق السيرفر (Edge Functions / SQL).
///
/// ⚠️ السيرفر هو مصدر الحقيقة. الكلاس ده للاستخدام في:
///   • حسابات العرض المتفائلة (optimistic UI)
///   • التوثيق التنفيذي للقواعد
///   • الاختبارات (يضمن إن المنطق متّسق ومفهوم)
class LoyaltyRules {
  LoyaltyRules._();

  /// earn: يزوّد available و lifetime بنفس القيمة، ثم يحدّث المستوى من lifetime.
  static WalletState applyEarn(
    WalletState w,
    int points,
    List<LoyaltyLevel> levels,
  ) {
    assert(points > 0, 'النقاط المكتسبة لازم تكون موجبة');
    final lifetime = w.lifetimePoints + points;
    return w.copyWith(
      availablePoints: w.availablePoints + points,
      lifetimePoints: lifetime,
      currentLevelId: levelForLifetime(levels, lifetime)?.id ?? w.currentLevelId,
    );
  }

  /// redeem: يخصم من available فقط. lifetime والمستوى لا يتغيّران أبدًا.
  /// يرمي [StateError] لو الرصيد غير كافٍ.
  static WalletState applyRedeem(WalletState w, int cost) {
    assert(cost > 0, 'تكلفة الاستبدال لازم تكون موجبة');
    if (w.availablePoints < cost) {
      throw StateError('النقاط المتاحة غير كافية للاستبدال');
    }
    return w.copyWith(availablePoints: w.availablePoints - cost);
  }

  /// أعلى مستوى عتبته (threshold_lifetime_points) ≤ lifetime.
  /// يرجّع null لو مفيش مستوى مؤهّل (lifetime أقل من كل العتبات).
  static LoyaltyLevel? levelForLifetime(
    List<LoyaltyLevel> levels,
    int lifetime,
  ) {
    LoyaltyLevel? best;
    for (final lvl in levels) {
      if (lvl.thresholdLifetimePoints <= lifetime) {
        if (best == null ||
            lvl.thresholdLifetimePoints > best.thresholdLifetimePoints) {
          best = lvl;
        }
      }
    }
    return best;
  }

  /// النقاط المتبقية للوصول للمستوى التالي (0 لو وصل للأعلى).
  static int pointsToNextLevel(List<LoyaltyLevel> levels, int lifetime) {
    final sorted = [...levels]..sort(
        (a, b) => a.thresholdLifetimePoints.compareTo(b.thresholdLifetimePoints));
    for (final lvl in sorted) {
      if (lvl.thresholdLifetimePoints > lifetime) {
        return lvl.thresholdLifetimePoints - lifetime;
      }
    }
    return 0;
  }
}
