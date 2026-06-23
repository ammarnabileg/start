import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع لوحة الصدارة (RPC store_leaderboard).
class LeaderboardRepository {
  LeaderboardRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> storeLeaderboard({
    required String merchantId,
    required String? branchId,
    required int limit,
  }) async {
    final rows = await _client.rpc('store_leaderboard', params: {
      'p_merchant': merchantId,
      'p_branch': branchId,
      'p_limit': limit,
    });
    return List<Map<String, dynamic>>.from(rows as List);
  }
}

final leaderboardRepoProvider = Provider<LeaderboardRepository>(
    (ref) => LeaderboardRepository(ref.read(supabaseClientProvider)));
