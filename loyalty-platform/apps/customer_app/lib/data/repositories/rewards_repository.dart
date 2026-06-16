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
