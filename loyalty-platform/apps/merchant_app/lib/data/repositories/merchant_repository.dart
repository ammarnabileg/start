import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع بيانات التاجر العامّة: صف merchants، الاشتراكات، ملخّص اللوحة.
class MerchantRepository {
  MerchantRepository(this._client);
  final SupabaseClient _client;

  Future<Map<String, dynamic>?> fetchMerchant(String merchantId) {
    return _client
        .from('merchants')
        .select()
        .eq('id', merchantId)
        .maybeSingle();
  }

  Future<Map<String, dynamic>> fetchMerchantSingle(String merchantId) {
    return _client.from('merchants').select().eq('id', merchantId).single();
  }

  Future<Map<String, dynamic>?> fetchMerchantStatus(String merchantId) {
    return _client
        .from('merchants')
        .select('status')
        .eq('id', merchantId)
        .maybeSingle();
  }

  /// بثّ حالة التاجر (للموافقة المعلّقة).
  Stream<List<Map<String, dynamic>>> watchMerchant(String merchantId) {
    return _client
        .from('merchants')
        .stream(primaryKey: ['id']).eq('id', merchantId);
  }

  Future<void> updateMerchant(
      String merchantId, Map<String, dynamic> payload) {
    return _client.from('merchants').update(payload).eq('id', merchantId);
  }

  /// إنشاء صف تاجر جديد (يرجّع الصف مع المعرّف).
  Future<Map<String, dynamic>> insertMerchant(Map<String, dynamic> payload) {
    return _client.from('merchants').insert(payload).select().single();
  }

  Future<void> insertStaff(Map<String, dynamic> payload) {
    return _client.from('merchant_staff').insert(payload);
  }

  // ----- الاشتراكات -----

  Future<Map<String, dynamic>?> fetchSubscription(String merchantId) {
    return _client
        .from('subscriptions')
        .select()
        .eq('merchant_id', merchantId)
        .maybeSingle();
  }

  Future<Map<String, dynamic>?> fetchLatestSubscription(String merchantId) {
    return _client
        .from('subscriptions')
        .select()
        .eq('merchant_id', merchantId)
        .order('created_at', ascending: false)
        .limit(1)
        .maybeSingle();
  }

  Future<void> upsertSubscription(Map<String, dynamic> payload) {
    return _client
        .from('subscriptions')
        .upsert(payload, onConflict: 'merchant_id');
  }

  // ----- الإعدادات -----

  Future<void> upsertSettings(Map<String, dynamic> payload) {
    return _client
        .from('merchant_settings')
        .upsert(payload, onConflict: 'merchant_id');
  }

  // ----- ملخّص اللوحة -----

  Future<Map<String, dynamic>> dashboardSummary({
    required String merchantId,
    required String? branchId,
  }) async {
    final res = await _client.rpc('dashboard_summary', params: {
      'p_merchant': merchantId,
      'p_branch': branchId,
    });
    return res as Map<String, dynamic>;
  }
}

final merchantRepoProvider = Provider<MerchantRepository>(
    (ref) => MerchantRepository(ref.read(supabaseClientProvider)));
