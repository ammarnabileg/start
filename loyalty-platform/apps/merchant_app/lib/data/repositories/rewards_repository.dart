import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع المكافآت.
class RewardsRepository {
  RewardsRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> fetchRewards(String merchantId) async {
    final rows = await _client
        .from('rewards')
        .select()
        .eq('merchant_id', merchantId)
        .order('created_at');
    return List<Map<String, dynamic>>.from(rows);
  }

  /// المكافآت النشطة لاختيارها أثناء الاستبدال (id, name, points_cost).
  Future<List<dynamic>> fetchActiveRewards({String? merchantId}) async {
    var q = _client
        .from('rewards')
        .select('id, name, points_cost')
        .eq('active', true);
    if (merchantId != null) q = q.eq('merchant_id', merchantId);
    return await q.order('points_cost');
  }

  Future<String> insertReward(Map<String, dynamic> payload) async {
    final r =
        await _client.from('rewards').insert(payload).select('id').single();
    return r['id'] as String;
  }

  Future<void> updateReward(String id, Map<String, dynamic> payload) {
    return _client.from('rewards').update(payload).eq('id', id);
  }
}

final rewardsRepoProvider = Provider<RewardsRepository>(
    (ref) => RewardsRepository(ref.read(supabaseClientProvider)));
