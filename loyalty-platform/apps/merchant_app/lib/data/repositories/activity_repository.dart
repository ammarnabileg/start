import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// سجل نشاط المتجر (Audit trail) — مين عمل كل أكشن.
class ActivityRepository {
  ActivityRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> activity(String merchantId,
      {String? staffId, int limit = 30, int offset = 0}) async {
    final rows = await _client.rpc('merchant_activity', params: {
      'p_merchant': merchantId,
      'p_staff': staffId,
      'p_limit': limit,
      'p_offset': offset,
    });
    return List<Map<String, dynamic>>.from((rows as List?) ?? const []);
  }
}

final activityRepoProvider = Provider<ActivityRepository>(
    (ref) => ActivityRepository(ref.read(supabaseClientProvider)));
