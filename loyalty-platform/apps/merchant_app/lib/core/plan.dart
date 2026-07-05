import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import 'merchant_providers.dart';

/// المزايا المتاحة في الباقة المجانية فقط (نقاط + تكرار زيارات).
/// أي ميزة خارج هذه القائمة تتطلب باقة مدفوعة (gold/enterprise) أو التجربة.
const kFreePlanFeatures = {'points', 'visits'};

/// المزايا المدفوعة (مقفولة على المجانية). مطابقة لمُحفّزات الباك-إند.
const kPaidFeatures = {
  'rewards',
  'levels',
  'coupons',
  'wheel',
  'questions',
  'referrals',
  'announcements',
};

/// هل تسمح الباقة [plan] بالميزة [feature]؟ (مطابقة لـ public.plan_allows).
bool planAllows(String plan, String feature) {
  if (kFreePlanFeatures.contains(feature)) return true;
  return const {'gold', 'enterprise', 'trial', 'monthly', 'yearly'}
      .contains(plan);
}

/// الباقة الفعلية للتاجر الحالي (free/gold/enterprise/trial...).
final merchantPlanProvider = FutureProvider.autoDispose<String>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  final res = await Supabase.instance.client
      .rpc('merchant_current_plan', params: {'p_merchant': staff.merchantId});
  return (res as String?) ?? 'free';
});

/// سعر الباقة حسب دولة المستخدم (عبر الـIP): داخل السعودية 199 ريال، خارجها $53.
class PlanPrice {
  final String display; // "199 ريال" أو "$53"
  final String period; // "شهريًا" / "/ month"
  final String currency; // SAR / USD
  final num amount;
  final String country;
  const PlanPrice({
    required this.display,
    required this.period,
    required this.currency,
    required this.amount,
    required this.country,
  });

  // افتراضي آمن لو تعذّر تحديد الدولة (الدولار).
  static const fallback = PlanPrice(
      display: r'$53',
      period: '/ month',
      currency: 'USD',
      amount: 53,
      country: 'XX');

  factory PlanPrice.fromJson(Map<String, dynamic> j) => PlanPrice(
        display: j['display'] as String? ?? fallback.display,
        period: j['period'] as String? ?? fallback.period,
        currency: j['currency'] as String? ?? 'USD',
        amount: (j['amount'] as num?) ?? 53,
        country: j['country'] as String? ?? 'XX',
      );
}

final planPriceProvider = FutureProvider.autoDispose<PlanPrice>((ref) async {
  try {
    final res =
        await Supabase.instance.client.functions.invoke('geo-price');
    final data = res.data;
    if (data is Map) {
      return PlanPrice.fromJson(Map<String, dynamic>.from(data));
    }
  } catch (_) {
    // الشبكة/الدالة غير متاحة → نرجع للدولار الافتراضي.
  }
  return PlanPrice.fallback;
});
