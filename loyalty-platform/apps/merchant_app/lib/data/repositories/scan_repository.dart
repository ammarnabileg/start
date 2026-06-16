import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع المسح (Edge Functions): التحقق من QR، إضافة نقاط، تسجيل زيارة،
/// استبدال موظف، تطبيق كوبون، تأكيد الاستبدال، تفعيل هدية.
class ScanRepository {
  ScanRepository(this._client);
  final SupabaseClient _client;

  Future<FunctionResponse> redeemPrize(String payload) {
    return _client.functions
        .invoke('redeem-prize', body: {'payload': payload});
  }

  Future<FunctionResponse> confirmRedemption(String redemptionId) {
    return _client.functions
        .invoke('confirm-redemption', body: {'redemption_id': redemptionId});
  }

  Future<FunctionResponse> verifyQr(String payload) {
    return _client.functions.invoke('verify-qr', body: {'payload': payload});
  }

  /// استدعاء عام لدوال إجراءات العميل (add-points/record-visit/staff-redeem/apply-coupon).
  /// لو مرّ [idempotencyKey] يُضاف إلى الجسم لمنع الازدواج عند إعادة المحاولة.
  Future<FunctionResponse> invoke(
    String fn,
    Map<String, dynamic> body, {
    String? idempotencyKey,
  }) {
    final payload = idempotencyKey == null
        ? body
        : {...body, 'idempotency_key': idempotencyKey};
    return _client.functions.invoke(fn, body: payload);
  }
}

final scanRepoProvider = Provider<ScanRepository>(
    (ref) => ScanRepository(ref.read(supabaseClientProvider)));
