import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع الموظفين والأدوار.
class StaffRepository {
  StaffRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> fetchStaff(String merchantId) async {
    final rows = await _client
        .from('merchant_staff')
        .select()
        .eq('merchant_id', merchantId)
        .order('created_at');
    return List<Map<String, dynamic>>.from(rows);
  }

  Future<List<Map<String, dynamic>>> fetchBranchOptions(
      String merchantId) async {
    final rows = await _client
        .from('branches')
        .select('id, name')
        .eq('merchant_id', merchantId)
        .order('name');
    return List<Map<String, dynamic>>.from(rows);
  }

  Future<List<Map<String, dynamic>>> fetchRoleOptions(String merchantId) async {
    final rows = await _client
        .from('merchant_roles')
        .select('id, name')
        .eq('merchant_id', merchantId)
        .order('name');
    return List<Map<String, dynamic>>.from(rows);
  }

  Future<void> insertStaff(Map<String, dynamic> payload) {
    return _client.from('merchant_staff').insert(payload);
  }

  Future<void> updateStaff(String id, Map<String, dynamic> payload) {
    return _client.from('merchant_staff').update(payload).eq('id', id);
  }

  // ----- الأدوار -----

  Future<List<Map<String, dynamic>>> fetchRoles(String merchantId) async {
    final rows = await _client
        .from('merchant_roles')
        .select()
        .eq('merchant_id', merchantId)
        .order('is_system', ascending: false)
        .order('name');
    return List<Map<String, dynamic>>.from(rows);
  }

  Future<void> seedDefaultRoles(String merchantId) {
    return _client.rpc('seed_default_roles', params: {'p_merchant': merchantId});
  }

  Future<void> insertRole(Map<String, dynamic> payload) {
    return _client.from('merchant_roles').insert(payload);
  }

  Future<void> updateRole(String id, Map<String, dynamic> payload) {
    return _client.from('merchant_roles').update(payload).eq('id', id);
  }

  Future<void> deleteRole(String id) {
    return _client.from('merchant_roles').delete().eq('id', id);
  }
}

final staffRepoProvider = Provider<StaffRepository>(
    (ref) => StaffRepository(ref.read(supabaseClientProvider)));
