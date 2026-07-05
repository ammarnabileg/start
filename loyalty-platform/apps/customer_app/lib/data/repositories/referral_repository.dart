import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع الإحالات: مَن دعاهم العميل وحالتهم.
class ReferralRepository {
  ReferralRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> myReferrals() async {
    final uid = _client.auth.currentUser!.id;
    final rows = await _client
        .from('referrals')
        .select()
        .eq('referrer_id', uid)
        .order('created_at', ascending: false);
    return (rows as List).cast<Map<String, dynamic>>();
  }

  /// ربط المُحيل العام بكود إحالة.
  Future<void> setReferrerByCode(String code) =>
      _client.rpc('set_referrer', params: {'p_code': code.trim()});

  /// فكّ الارتباط العام.
  Future<void> clearReferrer() => _client.rpc('clear_referrer');

  /// تقدّم إحالة العميل عند متجر (count + milestones + granted + enabled).
  Future<Map<String, dynamic>> progress(String merchantId) async {
    final res = await _client
        .rpc('my_referral_progress', params: {'p_merchant': merchantId});
    return (res as Map?)?.cast<String, dynamic>() ?? const {};
  }
}

final referralRepoProvider = Provider<ReferralRepository>(
    (ref) => ReferralRepository(ref.read(supabaseClientProvider)));
