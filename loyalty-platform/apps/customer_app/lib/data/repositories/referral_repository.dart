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
}

final referralRepoProvider = Provider<ReferralRepository>(
    (ref) => ReferralRepository(ref.read(supabaseClientProvider)));
