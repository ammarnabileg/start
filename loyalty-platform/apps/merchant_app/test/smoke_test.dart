import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';

/// اختبارات منطق سريعة (بدون باك-إند) — تُشغّل في CI.
void main() {
  test('LoyaltyLevel.fromJson مع branchId', () {
    final l = LoyaltyLevel.fromJson(const {
      'id': '1',
      'merchant_id': 'm',
      'branch_id': 'b',
      'name': 'ذهبي',
      'threshold_lifetime_points': 1500,
    });
    expect(l.name, 'ذهبي');
    expect(l.branchId, 'b');
    expect(l.thresholdLifetimePoints, 1500);
  });

  test('genIdempotencyKey فريد وغير فارغ', () {
    final a = genIdempotencyKey();
    final b = genIdempotencyKey();
    expect(a, isNotEmpty);
    expect(a == b, isFalse);
  });

  test('PointsScope ذهابًا وإيابًا', () {
    expect(PointsScope.fromString('branch'), PointsScope.branch);
    expect(PointsScope.merchant.value, 'merchant');
  });
}
