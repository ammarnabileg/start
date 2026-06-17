import 'package:flutter_test/flutter_test.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:merchant_app/core/merchant_providers.dart';

/// اختبارات منطق سريعة (بدون باك-إند) — تُشغّل في CI.
void main() {
  group('Permissions.can — تقييد الأدوار', () {
    MerchantRole role(Map<String, dynamic> perms) => MerchantRole(
        id: 'r', merchantId: 'm', name: 'دور', permissions: perms);

    test('المالك يملك كل الصلاحيات', () {
      const p = Permissions(legacyRole: 'merchant_owner');
      expect(p.can('rewards', 'delete'), isTrue);
      expect(p.can('staff', 'create'), isTrue);
    });

    test('دور «عرض فقط» يرى ولا يعدّل', () {
      final p = Permissions(
          legacyRole: 'cashier', role: role({'rewards': ['view']}));
      expect(p.can('rewards', 'view'), isTrue);
      expect(p.can('rewards', 'create'), isFalse);
      expect(p.can('rewards', 'edit'), isFalse);
      expect(p.can('rewards', 'delete'), isFalse);
    });

    test('دور مخصّص كامل يضيف ويعدّل ويحذف', () {
      final p = Permissions(
          legacyRole: 'cashier',
          role: role({'rewards': ['view', 'create', 'edit', 'delete']}));
      expect(p.can('rewards', 'create'), isTrue);
      expect(p.can('rewards', 'delete'), isTrue);
    });

    test('الدور المخصّص هو الفيصل ويتجاوز الدور القديم', () {
      // موظف دوره القديم «مدير» لكن أُسند له دور «عرض فقط» → يُقيَّد فعليًا.
      final p = Permissions(
          legacyRole: 'manager', role: role({'rewards': ['view']}));
      expect(p.can('rewards', 'create'), isFalse);
    });

    test('احتياطي الدور القديم (مدير) عند غياب دور مخصّص', () {
      const p = Permissions(legacyRole: 'manager');
      expect(p.can('rewards', 'create'), isTrue);
      expect(p.can('rewards', 'delete'), isTrue);
      expect(p.can('staff', 'create'), isFalse); // الموظفون للمالك فقط
    });

    test('احتياطي الكاشير: إجراءات الماسح فقط', () {
      const p = Permissions(legacyRole: 'cashier');
      expect(p.can('points', 'create'), isTrue);
      expect(p.can('prizes', 'redeem'), isTrue);
      expect(p.can('rewards', 'create'), isFalse);
    });
  });

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
