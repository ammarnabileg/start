import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع الفروع.
class BranchesRepository {
  BranchesRepository(this._client);
  final SupabaseClient _client;

  /// كل الفروع (إدارة الفروع).
  Future<List<Map<String, dynamic>>> fetchBranches(String merchantId) async {
    final rows = await _client
        .from('branches')
        .select()
        .eq('merchant_id', merchantId)
        .order('created_at');
    return List<Map<String, dynamic>>.from(rows);
  }

  /// قائمة مختصرة (id, name) للفلاتر.
  Future<List<Map<String, dynamic>>> fetchBranchOptions(
      String merchantId) async {
    final rows = await _client
        .from('branches')
        .select('id, name')
        .eq('merchant_id', merchantId)
        .order('name');
    return List<Map<String, dynamic>>.from(rows);
  }

  /// الفروع النشطة فقط (id, name).
  Future<List<Map<String, dynamic>>> fetchActiveBranchOptions(
      String merchantId) async {
    final rows = await _client
        .from('branches')
        .select('id, name')
        .eq('merchant_id', merchantId)
        .eq('active', true)
        .order('name');
    return List<Map<String, dynamic>>.from(rows);
  }

  Future<void> insertBranch(Map<String, dynamic> payload) {
    return _client.from('branches').insert(payload);
  }

  Future<void> updateBranch(String id, Map<String, dynamic> payload) {
    return _client.from('branches').update(payload).eq('id', id);
  }
}

final branchesRepoProvider = Provider<BranchesRepository>(
    (ref) => BranchesRepository(ref.read(supabaseClientProvider)));
