import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع عجلة الحظ: تحميل العجلة، اللفّ، وهدايا العميل.
class WheelRepository {
  WheelRepository(this._client);
  final SupabaseClient _client;

  String? get currentUserId => _client.auth.currentUser?.id;

  /// عجلة حظ التاجر النشطة (إن وُجدت).
  Future<LuckyWheel?> activeWheel(String merchantId) async {
    final row = await _client
        .from('lucky_wheels')
        .select('*, wheel_segments(*)')
        .eq('merchant_id', merchantId)
        .eq('active', true)
        .limit(1)
        .maybeSingle();
    if (row == null) return null;
    return LuckyWheel.fromJson(row);
  }

  /// لفّ العجلة عبر دالة الحافة. [idempotencyKey] يمنع خصم/منح مزدوج عند الإعادة.
  Future<Map<String, dynamic>?> spinWheel(String wheelId,
      {String? idempotencyKey}) async {
    final res = await _client.functions.invoke('spin-wheel', body: {
      'wheel_id': wheelId,
      if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
    });
    return res.data as Map<String, dynamic>?;
  }

  /// هدايا العميل المكسوبة (status = won) — مرقّمة.
  Future<List<UserPrize>> myPrizes({
    required int offset,
    required int limit,
  }) async {
    final uid = _client.auth.currentUser!.id;
    final rows = await _client
        .from('user_prizes')
        .select('*, merchants(business_name)')
        .eq('user_id', uid)
        .eq('status', 'won')
        .order('created_at', ascending: false)
        .range(offset, offset + limit - 1);
    return (rows as List).map((r) {
      final m = r as Map<String, dynamic>;
      final merchant = m['merchants'] as Map<String, dynamic>?;
      return UserPrize.fromJson({
        ...m,
        'merchant_name': merchant?['business_name'],
      });
    }).toList();
  }

  /// بث لحظي لحالة هدية محددة.
  Stream<List<Map<String, dynamic>>> prizeStatusStream(String prizeId) {
    return _client
        .from('user_prizes')
        .stream(primaryKey: ['id']).eq('id', prizeId);
  }

  /// تأكيد الاستلام (موافق) أو إلغاء التسليم.
  Future<void> confirmPrize(String prizeId, {required bool confirm}) {
    return _client.functions.invoke('confirm-prize', body: {
      'prize_id': prizeId,
      'action': confirm ? 'confirm' : 'cancel',
    });
  }

  /// إرسال بلاغ (رسالة + فيديو توثيق اختياري) عن متجر/فرع/هدية.
  Future<void> submitReport({
    String? merchantId,
    String? branchId,
    String? prizeId,
    String? message,
    String? videoUrl,
  }) {
    return _client.functions.invoke('submit-report', body: {
      'merchant_id': merchantId,
      'branch_id': branchId,
      'prize_id': prizeId,
      'message': message,
      'video_url': videoUrl,
    });
  }
}

final wheelRepoProvider = Provider<WheelRepository>(
    (ref) => WheelRepository(ref.read(supabaseClientProvider)));
