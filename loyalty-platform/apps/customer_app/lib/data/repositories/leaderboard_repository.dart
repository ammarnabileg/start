import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع لوحات الصدارة (عامة / لكل تاجر).
class LeaderboardRepository {
  LeaderboardRepository(this._client);
  final SupabaseClient _client;

  String? get currentUserId => _client.auth.currentUser?.id;

  Future<List<LeaderboardEntry>> leaderboard({
    String? merchantId,
    String? branchId,
  }) async {
    final List<dynamic> rows;
    if (merchantId == null) {
      rows = await _client.rpc('global_leaderboard', params: {'p_limit': 50});
    } else {
      rows = await _client.rpc('store_leaderboard', params: {
        'p_merchant': merchantId,
        'p_branch': branchId,
        'p_limit': 50,
      });
    }
    return rows.map((r) => LeaderboardEntry.fromJson(r)).toList();
  }
}

final leaderboardRepoProvider = Provider<LeaderboardRepository>(
    (ref) => LeaderboardRepository(ref.read(supabaseClientProvider)));
