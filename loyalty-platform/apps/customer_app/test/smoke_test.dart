import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';

/// اختبارات منطق سريعة (بدون باك-إند) — تُشغّل في CI.
void main() {
  group('StampCampaign', () {
    test('تقدّم البطاقة + المتبقّي', () {
      final c = StampCampaign.fromJson(const {
        'id': '1',
        'name': 'بطاقة القهوة',
        'required_visits': 10,
        'action_type': 'purchase',
        'reward_name': 'قهوة مجانية',
      }, currentStamps: 7);
      expect(c.requiredCount, 10);
      expect(c.currentStamps, 7);
      expect(c.remaining, 3);
      expect(c.completed, isFalse);
      expect(c.actionVerb, isNotEmpty);
    });

    test('مكتملة عند بلوغ العدد المطلوب', () {
      const c = StampCampaign(
          id: '1', name: 'x', requiredCount: 5, currentStamps: 5);
      expect(c.completed, isTrue);
      expect(c.remaining, 0);
    });
  });

  group('UserStore', () {
    test('المتجر المتاح/المعلّق', () {
      const ok = UserStore(
          id: '1',
          userId: 'u',
          merchantId: 'm',
          availablePoints: 10,
          lifetimePoints: 20,
          merchantStatus: 'approved');
      expect(ok.merchantAvailable, isTrue);
      const suspended = UserStore(
          id: '1',
          userId: 'u',
          merchantId: 'm',
          availablePoints: 0,
          lifetimePoints: 0,
          merchantStatus: 'suspended');
      expect(suspended.merchantAvailable, isFalse);
    });
  });
}
