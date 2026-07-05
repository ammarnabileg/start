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

  /// تسجيل تاجر جديد ذاتيًا عبر دالة آمنة (security definer) تنشئ صف التاجر
  /// وتربط المستخدم كمالك في معاملة واحدة — لأن RLS يمنع الإدراج المباشر في
  /// merchants ولأن المالك الجديد ليس عضوًا بعد. يرجّع معرّف التاجر الجديد.
  Future<String> registerMerchant(Map<String, dynamic> draft, String phone) async {
    final id = await _client.rpc('register_merchant', params: {
      'p_business_name': draft['business_name'],
      'p_business_type': draft['business_type'],
      'p_phone': draft['phone'] ?? phone,
      'p_email': draft['email'],
      'p_cr_number': draft['cr_number'],
      'p_logo_url': draft['logo_url'],
      'p_address': draft['address'],
    });
    return id as String;
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

  /// تبديل نطاق النقاط بأمان (هجرة المحافظ والمستويات حسب الاتجاه).
  /// mode: 'adopt' أو 'fresh' (يُستخدم عند التحويل لـ merchant).
  Future<void> applyPointsScope({
    required String merchantId,
    required String newScope,
    String mode = 'fresh',
    String? sourceBranch,
  }) {
    return _client.rpc('apply_points_scope', params: {
      'p_merchant': merchantId,
      'p_new_scope': newScope,
      'p_mode': mode,
      'p_source_branch': sourceBranch,
    });
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
