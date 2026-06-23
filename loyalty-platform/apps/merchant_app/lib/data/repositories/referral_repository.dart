import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// برنامج إحالة التاجر (قراءة + حفظ).
class ReferralRepository {
  ReferralRepository(this._client);
  final SupabaseClient _client;

  Future<Map<String, dynamic>?> program(String merchantId) async {
    return _client
        .from('referral_programs')
        .select()
        .eq('merchant_id', merchantId)
        .maybeSingle();
  }

  Future<void> setProgram(
    String merchantId, {
    required bool enabled,
    required List<Map<String, dynamic>> milestones,
    int refereePoints = 0,
  }) {
    return _client.rpc('set_referral_program', params: {
      'p_merchant': merchantId,
      'p_enabled': enabled,
      'p_milestones': milestones,
      'p_referee_points': refereePoints,
    });
  }
}

final referralRepoProvider = Provider<ReferralRepository>(
    (ref) => ReferralRepository(ref.read(supabaseClientProvider)));
