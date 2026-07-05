import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع التحليلات (RPC analytics_summary + الفروع).
class AnalyticsRepository {
  AnalyticsRepository(this._client);
  final SupabaseClient _client;

  Future<Map<String, dynamic>> summary({
    required String merchantId,
    required String? branchId,
    required String since,
  }) async {
    final res = await _client.rpc('analytics_summary', params: {
      'p_merchant': merchantId,
      'p_branch': branchId,
      'p_since': since,
    });
    return res as Map<String, dynamic>;
  }

  Future<List<Map<String, dynamic>>> fetchBranches(String merchantId) async {
    final rows = await _client
        .from('branches')
        .select('id, name')
        .eq('merchant_id', merchantId)
        .order('name');
    return List<Map<String, dynamic>>.from(rows);
  }
}

final analyticsRepoProvider = Provider<AnalyticsRepository>(
    (ref) => AnalyticsRepository(ref.read(supabaseClientProvider)));
