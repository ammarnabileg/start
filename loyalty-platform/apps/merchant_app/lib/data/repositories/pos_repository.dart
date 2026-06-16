import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع تكامل نقاط البيع (POS): دالة pos-keys + قائمة المفاتيح.
class PosRepository {
  PosRepository(this._client);
  final SupabaseClient _client;

  Future<List<dynamic>> fetchKeys(String merchantId) async {
    final rows = await _client
        .from('pos_api_keys')
        .select()
        .eq('merchant_id', merchantId)
        .order('created_at', ascending: false);
    return rows as List;
  }

  Future<FunctionResponse> createKey(String name) {
    return _client.functions
        .invoke('pos-keys', body: {'action': 'create', 'name': name});
  }

  Future<FunctionResponse> revokeKey(String keyId) {
    return _client.functions
        .invoke('pos-keys', body: {'action': 'revoke', 'key_id': keyId});
  }
}

final posRepoProvider = Provider<PosRepository>(
    (ref) => PosRepository(ref.read(supabaseClientProvider)));
