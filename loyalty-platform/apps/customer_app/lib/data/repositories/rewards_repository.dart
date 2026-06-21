import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع المكافآت: بدء الاستبدال + متابعة حالة عملية الاستبدال.
class RewardsRepository {
  RewardsRepository(this._client);
  final SupabaseClient _client;

  /// بدء استبدال مكافأة عبر دالة الحافة. [idempotencyKey] يمنع إنشاء كودَي استلام.
  Future<Map<String, dynamic>?> redeemReward(String rewardId,
      {String? idempotencyKey}) async {
    final res = await _client.functions.invoke('redeem-reward', body: {
      'reward_id': rewardId,
      if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
    });
    return res.data as Map<String, dynamic>?;
  }

  /// شراء مكافأة بالنقاط (امتلاك فوري): يخصم النقاط ويضيف الهدية إلى "هداياي".
  /// يرجّع {prize_id, title, expires_at, available_points} أو يرمي رسالة الخطأ.
  Future<Map<String, dynamic>> buyReward(String rewardId,
      {String? branchId, String? idempotencyKey}) async {
    final res = await _client.functions.invoke('buy-reward', body: {
      'reward_id': rewardId,
      if (branchId != null) 'branch_id': branchId,
      if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
    });
    final data = res.data;
    if (data is Map && data['error'] != null) {
      throw Exception(data['error'] as String);
    }
    if (data is Map<String, dynamic>) return data;
    if (data is Map) return Map<String, dynamic>.from(data);
    throw Exception('تعذّر إتمام الشراء، حاول مرة أخرى');
  }

  /// بث لحظي لحالة عملية استبدال.
  Stream<List<Map<String, dynamic>>> redemptionStream(String redemptionId) {
    return _client
        .from('reward_redemptions')
        .stream(primaryKey: ['id']).eq('id', redemptionId);
  }

  /// قراءة حالة عملية الاستبدال (polling).
  Future<Map<String, dynamic>?> redemptionStatus(String redemptionId) {
    return _client
        .from('reward_redemptions')
        .select('status')
        .eq('id', redemptionId)
        .maybeSingle();
  }
}

final rewardsRepoProvider = Provider<RewardsRepository>(
    (ref) => RewardsRepository(ref.read(supabaseClientProvider)));
