import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';

void main() {
  const m = 'merchant-1';
  final levels = [
    const LoyaltyLevel(
        id: 'bronze', merchantId: m, name: 'برونزي',
        thresholdLifetimePoints: 0, sortOrder: 0),
    const LoyaltyLevel(
        id: 'silver', merchantId: m, name: 'فضي',
        thresholdLifetimePoints: 500, sortOrder: 1),
    const LoyaltyLevel(
        id: 'gold', merchantId: m, name: 'ذهبي',
        thresholdLifetimePoints: 1500, sortOrder: 2),
  ];

  group('LoyaltyRules.applyEarn', () {
    test('earn increases available AND lifetime by the same amount', () {
      const w = WalletState(availablePoints: 100, lifetimePoints: 100);
      final r = LoyaltyRules.applyEarn(w, 50, levels);
      expect(r.availablePoints, 150);
      expect(r.lifetimePoints, 150);
    });

    test('earn promotes level when crossing a threshold', () {
      const w = WalletState(
          availablePoints: 480, lifetimePoints: 480, currentLevelId: 'bronze');
      final r = LoyaltyRules.applyEarn(w, 30, levels); // lifetime 510 → فضي
      expect(r.currentLevelId, 'silver');
    });
  });

  group('LoyaltyRules.applyRedeem', () {
    test('redeem subtracts from available only; lifetime unchanged', () {
      const w = WalletState(
          availablePoints: 350, lifetimePoints: 1500, currentLevelId: 'gold');
      final r = LoyaltyRules.applyRedeem(w, 100);
      expect(r.availablePoints, 250);
      expect(r.lifetimePoints, 1500); // ثابت
      expect(r.currentLevelId, 'gold'); // المستوى لا يتأثر بالاستبدال
    });

    test('redeem throws when balance is insufficient', () {
      const w = WalletState(availablePoints: 50, lifetimePoints: 50);
      expect(() => LoyaltyRules.applyRedeem(w, 100), throwsStateError);
    });
  });

  group('LoyaltyRules.levelForLifetime', () {
    test('returns highest qualifying level', () {
      expect(LoyaltyRules.levelForLifetime(levels, 0)?.id, 'bronze');
      expect(LoyaltyRules.levelForLifetime(levels, 499)?.id, 'bronze');
      expect(LoyaltyRules.levelForLifetime(levels, 500)?.id, 'silver');
      expect(LoyaltyRules.levelForLifetime(levels, 1600)?.id, 'gold');
    });

    test('pointsToNextLevel computes remaining to next threshold', () {
      expect(LoyaltyRules.pointsToNextLevel(levels, 480), 20); // → 500
      expect(LoyaltyRules.pointsToNextLevel(levels, 1500), 0); // أعلى مستوى
    });
  });
}
