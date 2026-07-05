import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع الكوبونات.
class CouponsRepository {
  CouponsRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> fetchCoupons(String merchantId) async {
    final rows = await _client
        .from('coupons')
        .select()
        .eq('merchant_id', merchantId)
        .order('created_at');
    return List<Map<String, dynamic>>.from(rows);
  }

  Future<String> insertCoupon(Map<String, dynamic> payload) async {
    final r =
        await _client.from('coupons').insert(payload).select('id').single();
    return r['id'] as String;
  }

  Future<void> updateCoupon(String id, Map<String, dynamic> payload) {
    return _client.from('coupons').update(payload).eq('id', id);
  }

  Future<void> deleteCoupon(String id) {
    return _client.from('coupons').delete().eq('id', id);
  }
}

final couponsRepoProvider = Provider<CouponsRepository>(
    (ref) => CouponsRepository(ref.read(supabaseClientProvider)));
