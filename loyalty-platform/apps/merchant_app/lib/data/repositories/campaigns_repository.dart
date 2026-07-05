import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع حملات الزيارات.
class CampaignsRepository {
  CampaignsRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> fetchCampaigns(String merchantId) async {
    final rows = await _client
        .from('visit_campaigns')
        .select()
        .eq('merchant_id', merchantId)
        .order('created_at');
    return List<Map<String, dynamic>>.from(rows);
  }

  Future<String> insertCampaign(Map<String, dynamic> payload) async {
    final r = await _client
        .from('visit_campaigns')
        .insert(payload)
        .select('id')
        .single();
    return r['id'] as String;
  }

  Future<void> updateCampaign(String id, Map<String, dynamic> payload) {
    return _client.from('visit_campaigns').update(payload).eq('id', id);
  }

  Future<void> deleteCampaign(String id) {
    return _client.from('visit_campaigns').delete().eq('id', id);
  }
}

final campaignsRepoProvider = Provider<CampaignsRepository>(
    (ref) => CampaignsRepository(ref.read(supabaseClientProvider)));
