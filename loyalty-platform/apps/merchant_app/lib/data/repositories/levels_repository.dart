import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع مستويات الولاء.
class LevelsRepository {
  LevelsRepository(this._client);
  final SupabaseClient _client;

  /// مستويات الستور (branchId = null) أو مستويات فرع محدّد.
  Future<List<Map<String, dynamic>>> fetchLevels(String merchantId,
      {String? branchId}) async {
    final base =
        _client.from('loyalty_levels').select().eq('merchant_id', merchantId);
    final filtered = branchId == null
        ? base.isFilter('branch_id', null)
        : base.eq('branch_id', branchId);
    final rows = await filtered.order('threshold_lifetime_points');
    return List<Map<String, dynamic>>.from(rows);
  }

  Future<void> insertLevel(Map<String, dynamic> payload) {
    return _client.from('loyalty_levels').insert(payload);
  }

  Future<void> updateLevel(String id, Map<String, dynamic> payload) {
    return _client.from('loyalty_levels').update(payload).eq('id', id);
  }
}

final levelsRepoProvider = Provider<LevelsRepository>(
    (ref) => LevelsRepository(ref.read(supabaseClientProvider)));
